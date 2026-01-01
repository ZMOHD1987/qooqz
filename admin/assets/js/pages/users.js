/*!
 * htdocs/admin/assets/js/pages/users.js
 * Final revised Users fragment client script
 *
 * - Waits for DOMContentLoaded before binding
 * - Robust save/change-password handlers (ensures id is sent)
 * - Translation-aware: uses window.ADMIN_UI or loads /admin/languages/admin/{lang}.json
 * - Fetch + XHR fallback, CSRF handling
 * - Lightweight debug toggle via window.__enableUsersDebug(true)
 *
 * Save as UTF-8 without BOM.
 */
(function () {
  'use strict';

  // CONFIG
  var API = '/api/users/users.php';
  var LANG_BASE = '/admin/languages/admin';
  var ROOT_SELECTOR = '#adminUsers';
  var DEBUG = false;

  function dbg() { if (!DEBUG) return; console.log.apply(console, arguments); }

  // Small DOM helpers
  function $ (sel, root) { root = root || document; return root.querySelector(sel); }
  function $all(sel, root) { root = root || document; return Array.prototype.slice.call(root.querySelectorAll(sel || '')); }

  // TRANSLATION HELPERS
  function resolveKey(obj, key) {
    if (!obj || !key) return null;
    var parts = key.split('.');
    var cur = obj;
    for (var i = 0; i < parts.length; i++) {
      if (!cur || !Object.prototype.hasOwnProperty.call(cur, parts[i])) return null;
      cur = cur[parts[i]];
    }
    return cur;
  }

  function tKey(key, fallback) {
    fallback = (typeof fallback === 'undefined') ? key : fallback;
    try {
      if (window.AdminI18n && typeof window.AdminI18n.tKey === 'function') {
        var v = window.AdminI18n.tKey(key, null);
        if (v != null) return v;
      }
    } catch (e) { /* ignore */ }
    if (window.ADMIN_UI) {
      var v2 = resolveKey(window.ADMIN_UI, key);
      if (v2 != null) return v2;
      var v3 = resolveKey(window.ADMIN_UI, 'strings.' + key);
      if (v3 != null) return v3;
    }
    return fallback;
  }

  function translateFragment(rootNode) {
    rootNode = rootNode || document;
    var nodes = rootNode.querySelectorAll('[data-i18n]');
    nodes.forEach(function (node) {
      var key = node.getAttribute('data-i18n');
      if (!key) return;
      var val = null;
      try {
        if (window.AdminI18n && typeof window.AdminI18n.tKey === 'function') val = window.AdminI18n.tKey(key, null);
      } catch (e) { val = null; }
      if (val === null) val = resolveKey(window.ADMIN_UI, key) || resolveKey(window.ADMIN_UI, 'strings.' + key);
      if (val != null) {
        var attr = node.getAttribute('data-i18n-attr');
        if (attr) node.setAttribute(attr, val);
        else {
          var tag = node.tagName && node.tagName.toLowerCase();
          if (tag === 'input' || tag === 'textarea') {
            if (node.hasAttribute('data-i18n-placeholder')) node.placeholder = val;
            else node.value = val;
          } else node.textContent = val;
        }
      }
    });
  }

  function ensureTranslations(cb) {
    cb = (typeof cb === 'function') ? cb : function () {};
    if (window.ADMIN_UI && Object.keys(window.ADMIN_UI).length > 0) {
      translateFragment(document);
      return cb();
    }
    var lang = (window.ADMIN_LANG || document.documentElement.lang || 'en').split('-')[0];
    var url = LANG_BASE + '/' + lang + '.json';
    fetch(url, { credentials: 'same-origin' }).then(function (res) {
      if (!res.ok) throw new Error('Lang file load failed: ' + res.status);
      return res.json();
    }).then(function (json) {
      window.ADMIN_UI = window.ADMIN_UI || {};
      Object.keys(json).forEach(function (k) { window.ADMIN_UI[k] = json[k]; });
      translateFragment(document);
      cb();
    }).catch(function (err) {
      dbg('Could not load translations:', err);
      if (window.AdminI18n && typeof window.AdminI18n.loadLanguage === 'function') {
        window.AdminI18n.loadLanguage(lang).then(function () {
          if (typeof window.AdminI18n.translateFragment === 'function') window.AdminI18n.translateFragment(document);
          else translateFragment(document);
          cb();
        }).catch(function () { cb(); });
      } else cb();
    });
  }

  // NETWORK HELPERS (fetch with XHR fallback)
  function postFormData(fd, cb) {
    dbg('postFormData entries:');
    try { fd.forEach(function(v,k){ dbg('  ', k, '=', v); }); } catch (e) { dbg('fd.iteration failed', e); }

    fetch(API, { method: 'POST', credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: fd })
      .then(function (res) {
        return res.text().then(function (text) {
          dbg('raw response text:', text);
          try { return cb(null, JSON.parse(text)); } catch (e) { return cb(null, { success: false, raw: text }); }
        });
      })
      .catch(function (err) {
        dbg('fetch failed, trying XHR fallback', err);
        try {
          var xhr = new XMLHttpRequest();
          xhr.open('POST', API, true);
          xhr.withCredentials = true;
          xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
          xhr.onreadystatechange = function () {
            if (xhr.readyState === 4) {
              dbg('XHR responseText:', xhr.responseText);
              try { return cb(null, JSON.parse(xhr.responseText)); } catch (e) { return cb(null, { success: false, raw: xhr.responseText }); }
            }
          };
          xhr.onerror = function (e) { return cb(e); };
          xhr.send(fd);
        } catch (e2) {
          return cb(e2 || new Error('Request failed'));
        }
      });
  }

  // MAIN
  function onReady() {
    var root = document.querySelector(ROOT_SELECTOR);
    if (!root) {
      dbg('users fragment not found:', ROOT_SELECTOR);
      return;
    }

    // DOM refs
    var tbody = $ ('#usersTbody', root);
    var status = $ ('#usersStatus', root);
    var refreshBtn = $ ('#usersRefresh', root);
    var userFormWrap = $ ('#userFormWrap', root);
    var userForm = $ ('#userForm', root);
    var changePwdWrap = $ ('#changePwdWrap', root);
    var changePwdForm = $ ('#changePwdForm', root);

    function getCSRF() {
      // prefer hidden in userForm, else root data-csrf
      try {
        if (userForm) {
          var ip = userForm.querySelector('input[name="csrf_token"]');
          if (ip && ip.value) return ip.value;
        }
        if (root.dataset && root.dataset.csrf) return root.dataset.csrf;
        return window.ADMIN_CSRF || '';
      } catch (e) { return ''; }
    }

    function setStatus(msg, isErr) {
      if (!status) return;
      status.textContent = msg || '';
      status.style.color = isErr ? '#b91c1c' : '#064e3b';
      if (!isErr) setTimeout(function () { status.textContent = ''; }, 3000);
    }

    function escapeHtml(s) { return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

    // Render rows
    function renderRows(rows) {
      if (!tbody) return;
      tbody.innerHTML = '';
      if (!rows || rows.length === 0) {
        var tr = document.createElement('tr');
        tr.innerHTML = '<td colspan="7" style="text-align:center;color:#666;">' + escapeHtml(tKey('users.empty','No users')) + '</td>';
        tbody.appendChild(tr);
        return;
      }
      rows.forEach(function (u) {
        var tr = document.createElement('tr');
        tr.dataset.id = u.id;
        tr.innerHTML = ''
          + '<td>' + escapeHtml(u.id) + '</td>'
          + '<td>' + escapeHtml(u.username || '') + '</td>'
          + '<td>' + escapeHtml(u.email || '') + '</td>'
          + '<td>' + escapeHtml(u.preferred_language || '') + '</td>'
          + '<td>' + escapeHtml(u.role_id || '') + '</td>'
          + '<td><button class="btn small toggle-active" data-id="' + escapeHtml(u.id) + '">' + escapeHtml(u.is_active ? tKey('users.active','Active') : tKey('users.inactive','Inactive')) + '</button></td>'
          + '<td><button class="btn small edit-user" data-id="' + escapeHtml(u.id) + '">' + escapeHtml(tKey('actions.edit','Edit')) + '</button> <button class="btn small danger delete-user" data-id="' + escapeHtml(u.id) + '">' + escapeHtml(tKey('actions.delete','Delete')) + '</button></td>';
        tbody.appendChild(tr);
      });
    }

    // fetch list
    function fetchList() {
      if (!tbody) return;
      tbody.innerHTML = '<tr><td colspan="7">' + escapeHtml(tKey('strings.loading','Loading...')) + '</td></tr>';
      fetch(API + '?format=json', { credentials: 'same-origin' }).then(function (r) {
        if (!r.ok) throw new Error('HTTP ' + r.status);
        return r.json();
      }).then(function (json) {
        if (!json || !json.data) { setStatus(tKey('error','Error loading'), true); tbody.innerHTML = '<tr><td colspan="7">' + escapeHtml(tKey('error','Error loading')) + '</td></tr>'; return; }
        renderRows(json.data);
      }).catch(function (err) { console.error('fetchList error', err); setStatus(tKey('error','Error loading'), true); tbody.innerHTML = '<tr><td colspan="7">' + escapeHtml(tKey('error','Error loading')) + '</td></tr>'; });
    }

    // load single
    function loadUser(id) {
      fetch(API + '?id=' + encodeURIComponent(id) + '&format=json', { credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (json) { if (!json || !json.data) return setStatus(tKey('error','User not found'), true); populateForm(json.data); })
        .catch(function (err) { console.error('loadUser error', err); setStatus(tKey('error','Error'), true); });
    }

    // populate form and ensure pwd_user_id is set
    function populateForm(u) {
      if (!userFormWrap || !userForm) return;
      userFormWrap.style.display = 'block';
      var set = function (sel, v) { var el = userForm.querySelector(sel); if (el) el.value = (v === null || typeof v === 'undefined') ? '' : v; };
      set('#user_id', u.id || 0);
      set('#user_username', u.username || '');
      set('#user_email', u.email || '');
      set('#user_lang', u.preferred_language || '');
      set('#user_role', u.role_id || '');
      set('#user_phone', u.phone || '');
      set('#user_timezone', u.timezone || '');
      // set pwd_user_id in change password form
      try {
        var pwdEl = document.querySelector('#pwd_user_id');
        if (pwdEl) pwdEl.value = u.id || 0;
      } catch (e) { dbg('populate: could not set pwd_user_id', e); }
      if (changePwdWrap) changePwdWrap.style.display = 'none';
    }

    // POST helpers
    function saveUserForm() {
      if (!userForm) return;
      try {
        var fd = new FormData(userForm);
        fd.set('action','save');
        // ensure id present
        var idEl = userForm.querySelector('#user_id');
        if (idEl) fd.set('id', idEl.value || '0');
        // ensure csrf
        var token = fd.get('csrf_token') || getCSRF();
        if (!token) dbg('Warning: CSRF token empty for save');
        fd.set('csrf_token', token);

        // debug output
        var dump = {};
        fd.forEach(function(v,k){ dump[k]=v; });
        dbg('Submitting user save with FormData:', dump);

        // basic client validation
        var username = fd.get('username') || '';
        var email = fd.get('email') || '';
        if (!username || username.trim() === '' || !email || email.trim() === '') {
          setStatus(tKey('messages.required','This field is required'), true);
          return;
        }

        postFormData(fd, function (err, json) {
          if (err) { console.error('save error', err); setStatus(tKey('error','Request failed'), true); return; }
          dbg('Save response JSON:', json);
          if (json && json.success) {
            setStatus(tKey('users.saved_success','Saved successfully'));
            fetchList();
            if (userFormWrap) userFormWrap.style.display = 'none';
          } else {
            var msg = (json && json.message) ? json.message : (json && json.raw ? json.raw : 'Save failed');
            setStatus(msg, true);
            if (DEBUG) { console.warn('Save failed details:', json); alert('Save failed: ' + msg); }
          }
        });
      } catch (e) { console.error('saveUserForm exception', e); setStatus(tKey('error','Request failed'), true); }
    }

    function deleteUser(id) {
      if (!confirm(tKey('confirm_delete','Are you sure you want to delete this item?'))) return;
      var fd = new FormData();
      fd.append('action','delete');
      fd.append('id', id);
      fd.append('csrf_token', getCSRF());
      postFormData(fd, function (err, json) {
        if (err) { console.error(err); setStatus(tKey('error','Request failed'), true); return; }
        if (json && json.success) { setStatus(tKey('users.deleted_success','Deleted successfully')); fetchList(); }
        else setStatus((json && json.message) || 'Delete failed', true);
      });
    }

    function toggleActive(id, btn) {
      var fd = new FormData();
      fd.append('action','toggle_active');
      fd.append('id', id);
      fd.append('csrf_token', getCSRF());
      postFormData(fd, function (err, json) {
        if (err) { console.error(err); setStatus(tKey('error','Request failed'), true); return; }
        if (json && json.success) {
          if (btn && json.data) btn.textContent = json.data.is_active ? tKey('users.active','Active') : tKey('users.inactive','Inactive');
          setStatus(tKey('users.saved_success','Updated successfully'));
        } else setStatus((json && json.message) || 'Toggle failed', true);
      });
    }

    // Robust change-password handler
    if (changePwdForm) {
      changePwdForm.addEventListener('submit', function (ev) {
        ev.preventDefault();
        var formEl = changePwdForm;
        var pid = 0;
        var pwdIdEl = formEl.querySelector('#pwd_user_id');
        if (pwdIdEl && pwdIdEl.value) pid = parseInt(pwdIdEl.value,10) || 0;
        if (!pid) {
          var mainIdEl = document.querySelector('#user_id');
          if (mainIdEl && mainIdEl.value) pid = parseInt(mainIdEl.value,10) || 0;
        }
        if (!pid || pid === 0) {
          var fallbackMsg = 'Please open a user before changing password.';
          setStatus(typeof tKey === 'function' ? tKey('users.select_user_first', fallbackMsg) : fallbackMsg, true);
          return;
        }
        // ensure hidden field exists and set
        try {
          if (pwdIdEl) pwdIdEl.value = pid;
          else {
            var hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = 'id';
            hidden.id = 'pwd_user_id';
            hidden.value = pid;
            formEl.appendChild(hidden);
            pwdIdEl = hidden;
          }
        } catch (e) { dbg('Could not set pwd_user_id', e); }

        var fd = new FormData(formEl);
        fd.set('action','change_password');
        fd.set('id', pid);
        fd.set('csrf_token', getCSRF());
        postFormData(fd, function (err, json) {
          if (err) { console.error(err); setStatus(tKey('error','Request failed'), true); return; }
          if (json && json.success) {
            setStatus(tKey('users.password_changed','Password changed'));
            if (changePwdWrap) changePwdWrap.style.display = 'none';
            formEl.reset();
          } else {
            setStatus((json && json.message) || 'Password change failed', true);
          }
        });
      });
    }

    // Event delegation for table actions
    if (tbody) {
      tbody.addEventListener('click', function (ev) {
        var t = ev.target;
        var editBtn = t.closest('.edit-user');
        if (editBtn) { loadUser(editBtn.dataset.id); return; }
        var delBtn = t.closest('.delete-user');
        if (delBtn) { deleteUser(delBtn.dataset.id); return; }
        var togBtn = t.closest('.toggle-active');
        if (togBtn) { toggleActive(togBtn.dataset.id, togBtn); return; }
      });
    }

    // Bind UI controls
    if (refreshBtn) refreshBtn.addEventListener('click', fetchList);

    // Bind submit handler for save
    if (userForm) {
      try {
        if (!userForm.__users_save_bound) {
          userForm.addEventListener('submit', function (ev) { ev.preventDefault(); saveUserForm(); });
          userForm.__users_save_bound = true;
        } else dbg('userForm submit already bound');
      } catch (e) { console.error('bind submit error', e); }
    } else dbg('userForm not found');

    // form action buttons
    var cancelBtn = $ ('#userCancelBtn', root);
    if (cancelBtn) cancelBtn.addEventListener('click', function () { if (userFormWrap) userFormWrap.style.display = 'none'; });

    var deleteBtn = $ ('#userDeleteBtn', root);
    if (deleteBtn) deleteBtn.addEventListener('click', function () {
      var idEl = userForm ? userForm.querySelector('#user_id') : null;
      var id = idEl ? idEl.value : null;
      if (!id) return;
      deleteUser(id);
      if (userFormWrap) userFormWrap.style.display = 'none';
    });

    var pwdBtn = $ ('#userPwdBtn', root);
    if (pwdBtn) pwdBtn.addEventListener('click', function () {
      if (!changePwdWrap) return;
      changePwdWrap.style.display = (changePwdWrap.style.display === 'none' || changePwdWrap.style.display === '') ? 'block' : 'none';
    });

    // changePwdForm cancel button
    if (changePwdForm) {
      var cancelPwdBtn = changePwdForm.querySelector('#cancelPwdBtn');
      if (cancelPwdBtn) cancelPwdBtn.addEventListener('click', function () { if (changePwdWrap) changePwdWrap.style.display = 'none'; });
    }

    // expose reload
    window.ADMIN_RELOAD_USERS = fetchList;

    // apply translations then load list
    ensureTranslations(function () {
      translateFragment(document);
      fetchList();
    });

    // language change
    window.addEventListener('language:changed', function () {
      ensureTranslations(function () { translateFragment(document); fetchList(); });
    });
  } // end onReady

  // init
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', onReady);
  else setTimeout(onReady, 0);

  // debug toggle
  window.__enableUsersDebug = function (v) { DEBUG = !!v; dbg('users.js debug:', DEBUG); };

})();