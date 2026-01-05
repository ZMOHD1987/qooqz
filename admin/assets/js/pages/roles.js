/*!
 * admin/assets/js/pages/roles.js
 * Complete client for Roles management
 */

(function () {
  'use strict';

  var API = window.API_ROLES || '/api/controllers/RolesController.php';
  var CSRF = window.CSRF_TOKEN || '';
  var I18N = window.I18N_FLAT || {};
  var USER = window.USER_INFO || {};
  var THEME = window.THEME || {};
  var DIRECTION = window.DIRECTION || 'ltr';

  var CAN_MANAGE = !!(
    (USER && Number(USER.role_id) === 1) ||
    (USER && Array.isArray(USER.roles) && (USER.roles.indexOf('super_admin') !== -1 || USER.roles.indexOf('admin') !== -1)) ||
    (USER && Array.isArray(USER.permissions) && USER.permissions.indexOf('manage_roles') !== -1)
  );

  var root = document.getElementById('adminRoles');
  if (!root) return;

  var searchInput = document.getElementById('rolesSearch');
  var refreshBtn = document.getElementById('rolesRefresh');
  var newBtn = document.getElementById('rolesNew');
  var statusEl = document.getElementById('rolesStatus');
  var tableBody = document.getElementById('rolesTbody');
  var pager = document.getElementById('rolesPager');

  var formWrap = document.getElementById('rolesFormWrap');
  var form = document.getElementById('rolesForm');
  var rolesId = document.getElementById('rolesId');
  var rolesKeyName = document.getElementById('rolesKeyName');
  var rolesDisplayName = document.getElementById('rolesDisplayName');
  var rolesCancel = document.getElementById('rolesCancel');

  var roles = [];
  var filtered = [];
  var page = 1;
  var perPage = 10;

  function t(key, fallback) {
    if (!key) return fallback || '';
    if (I18N && typeof I18N[key] !== 'undefined' && I18N[key] !== '') return I18N[key];
    return fallback || key.split('.').pop().replace(/_/g, ' ');
  }

  function esc(v) {
    return String(v == null ? '' : v)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function setStatus(msg, isError) {
    if (!statusEl) return;
    statusEl.textContent = msg || '';
    statusEl.style.color = isError ? (THEME && THEME.colors_map && THEME.colors_map['error'] ? THEME.colors_map['error'] : '#EF4444') : (THEME && THEME.colors_map && THEME.colors_map['primary'] ? THEME.colors_map['primary'] : '#FF0000');
  }

  function fetchText(url, opts) {
    opts = opts || {};
    opts.credentials = 'same-origin';
    return fetch(url, opts).then(function (r) {
      return r.text().then(function (text) {
        return { ok: r.ok, status: r.status, text: text };
      });
    });
  }

  function fetchJson(url, opts) {
    return fetchText(url, opts).then(function (res) {
      if (!res.ok) {
        var msg = 'HTTP ' + res.status;
        try { var parsed = JSON.parse(res.text); msg = parsed.message || msg; } catch (e) {}
        var err = new Error(msg);
        err.status = res.status;
        err.body = res.text;
        throw err;
      }
      try { return JSON.parse(res.text || 'null'); } catch (e) { throw new Error('Invalid JSON'); }
    });
  }

  function loadList() {
    setStatus(t('roles.loading', 'Loading...'));
    fetchJson(API + '?format=json', { method: 'GET' })
      .then(function (json) {
        roles = Array.isArray(json.data) ? json.data : [];
        filtered = roles.slice();
        page = 1;
        renderTable();
        renderPager();
        setStatus('');
      })
      .catch(function (err) {
        console.error('loadList error', err);
        setStatus(err.message || t('roles.error_loading', 'Error loading'), true);
        roles = []; filtered = [];
        renderTable(); renderPager();
      });
  }

  function renderTable() {
    if (!tableBody) return;
    tableBody.innerHTML = '';
    if (!filtered || filtered.length === 0) {
      var tr = document.createElement('tr');
      tr.innerHTML = '<td colspan="4" style="padding:12px;text-align:center;color:#666;">' + esc(t('roles.no_entries', 'No roles found')) + '</td>';
      tableBody.appendChild(tr);
      return;
    }
    var total = filtered.length;
    var start = (page - 1) * perPage;
    var end = Math.min(total, start + perPage);
    var pageItems = filtered.slice(start, end);
    pageItems.forEach(function (it) {
      var tr = document.createElement('tr');
      var actions = '';
      if (CAN_MANAGE) {
        actions = '<button class="btn editBtn" data-id="' + esc(it.id) + '" data-key="' + esc(it.key_name) + '" data-display="' + esc(it.display_name) + '" style="margin-right:6px;">' + esc(t('roles.btn_edit', 'Edit')) + '</button>';
        actions += '<button class="btn danger deleteBtn" data-id="' + esc(it.id) + '">' + esc(t('roles.btn_delete', 'Delete')) + '</button>';
      }
      tr.innerHTML = '<td style="padding:10px;border-bottom:1px solid var(--theme-border,#333);">' + esc(it.id) + '</td>'
                   + '<td style="padding:10px;border-bottom:1px solid var(--theme-border,#333);">' + esc(it.key_name) + '</td>'
                   + '<td style="padding:10px;border-bottom:1px solid var(--theme-border,#333);">' + esc(it.display_name) + '</td>'
                   + '<td style="padding:10px;border-bottom:1px solid var(--theme-border,#333);text-align:' + (DIRECTION === 'rtl' ? 'left' : 'right') + ';">' + actions + '</td>';
      tableBody.appendChild(tr);
    });

    var edits = tableBody.querySelectorAll('.editBtn');
    edits.forEach(function (b) {
      b.addEventListener('click', function () {
        var id = this.getAttribute('data-id');
        var key = this.getAttribute('data-key');
        var display = this.getAttribute('data-display');
        if (!CAN_MANAGE) { alert(t('roles.no_permission_notice', 'You do not have permission')); return; }
        openEdit(id, key, display);
      });
    });

    var deletes = tableBody.querySelectorAll('.deleteBtn');
    deletes.forEach(function (b) {
      b.addEventListener('click', function () {
        var id = this.getAttribute('data-id');
        if (!CAN_MANAGE) { alert(t('roles.no_permission_notice', 'You do not have permission')); return; }
        if (!confirm(t('roles.confirm_delete', 'Delete this role?'))) return;
        deleteRole(id);
      });
    });
  }

  function renderPager() {
    if (!pager) return;
    pager.innerHTML = '';
    var total = filtered.length || 0;
    var totalPages = Math.max(1, Math.ceil(total / perPage));
    var info = document.createElement('span');
    info.textContent = total === 0 ? t('roles.no_entries', 'No roles') : ('Showing ' + (total === 0 ? 0 : ((page - 1) * perPage + 1)) + '-' + Math.min(total, page * perPage) + ' of ' + total);
    pager.appendChild(info);

    var prev = document.createElement('button');
    prev.className = 'btn';
    prev.textContent = t('roles.prev', 'Prev');
    prev.disabled = page <= 1;
    prev.style.marginLeft = '8px';
    prev.addEventListener('click', function () { if (page > 1) { page--; renderTable(); renderPager(); } });
    pager.appendChild(prev);

    var next = document.createElement('button');
    next.className = 'btn';
    next.textContent = t('roles.next', 'Next');
    next.disabled = page >= totalPages;
    next.style.marginLeft = '6px';
    next.addEventListener('click', function () { if (page < totalPages) { page++; renderTable(); renderPager(); } });
    pager.appendChild(next);
  }

  function deleteRole(id) {
    setStatus(t('roles.processing', 'Processing...'));
    var fd = new FormData();
    fd.append('action', 'delete');
    fd.append('id', id);
    if (CSRF) fd.append('csrf_token', CSRF);
    fetchText(API, { method: 'POST', body: fd })
      .then(function (res) {
        if (res.status >= 200 && res.status < 300) {
          try {
            var j = JSON.parse(res.text || '{}');
            if (j.success) {
              setStatus(j.message || t('roles.deleted', 'Deleted'));
              loadList();
              return;
            } else {
              if (j.errors) {
                var first = Object.keys(j.errors)[0];
                setStatus(j.errors[first] || j.message || 'Delete failed', true);
              } else setStatus(j.message || 'Delete failed', true);
            }
          } catch (e) { setStatus('Invalid JSON', true); }
        } else {
          setStatus('HTTP ' + res.status, true);
        }
      })
      .catch(function (err) { console.error(err); setStatus(t('roles.error_delete', 'Error deleting'), true); });
  }

  function openNew() {
    if (!CAN_MANAGE) { alert(t('roles.no_permission_notice', 'You do not have permission')); return; }
    if (!formWrap) return;
    clearFormErrors();
    form.reset();
    if (rolesId) rolesId.value = '';
    if (formWrap) formWrap.style.display = 'block';
    var title = document.getElementById('rolesFormTitle');
    if (title) title.textContent = t('roles.form_title', 'Add New Role');
    if (rolesKeyName) rolesKeyName.focus();
  }

  function openEdit(id, key, display) {
    if (!CAN_MANAGE) { alert(t('roles.no_permission_notice', 'You do not have permission')); return; }
    if (!formWrap) return;
    clearFormErrors();
    form.reset();
    if (rolesId) rolesId.value = id;
    if (rolesKeyName) rolesKeyName.value = key;
    if (rolesDisplayName) rolesDisplayName.value = display;
    if (formWrap) formWrap.style.display = 'block';
    var title = document.getElementById('rolesFormTitle');
    if (title) title.textContent = t('roles.form_title_edit', 'Edit Role');
    if (rolesKeyName) rolesKeyName.focus();
  }

  function clearFormErrors() {
    var prev = form.querySelectorAll('.field-error');
    prev.forEach(function (el) { el.parentNode && el.parentNode.removeChild(el); });
  }

  function showFieldError(fieldName, msg) {
    var field = form.querySelector('[name="' + fieldName + '"]');
    if (!field) { setStatus(msg, true); return; }
    var err = document.createElement('div');
    err.className = 'field-error';
    err.style.color = (THEME && THEME.colors_map && THEME.colors_map['error']) ? THEME.colors_map['error'] : '#EF4444';
    err.style.fontSize = '13px';
    err.style.marginTop = '6px';
    err.textContent = msg;
    field.parentNode && field.parentNode.insertBefore(err, field.nextSibling);
  }

  function saveRole(e) {
    if (e && e.preventDefault) e.preventDefault();
    if (!CAN_MANAGE) { alert(t('roles.no_permission_notice', 'You do not have permission')); return; }
    if (!form) return;

    clearFormErrors();
    var fd = new FormData(form);
    
    if (!fd.get('id') || fd.get('id') === '') {
      fd.delete('id');
    }
    
    fd.set('action', 'save');
    if (CSRF) fd.set('csrf_token', CSRF);

    var missing = false;
    if (!fd.get('key_name') || fd.get('key_name') === '') { showFieldError('key_name', t('roles.error_key_required', 'Key name is required')); missing = true; }
    if (!fd.get('display_name') || fd.get('display_name') === '') { showFieldError('display_name', t('roles.error_display_required', 'Display name is required')); missing = true; }
    if (missing) { setStatus(t('roles.error_save', 'Validation error'), true); return; }

    setStatus(t('roles.processing', 'Processing...'));
    fetchText(API, { method: 'POST', body: fd })
      .then(function (res) {
        if (res.status >= 200 && res.status < 300) {
          try {
            var j = JSON.parse(res.text || '{}');
            if (j.success) {
              setStatus(j.message || t('roles.saved', 'Saved'));
              if (formWrap) formWrap.style.display = 'none';
              loadList();
              return;
            } else {
              if (j.errors && typeof j.errors === 'object') {
                Object.keys(j.errors).forEach(function (k) { showFieldError(k, j.errors[k]); });
                setStatus(t('roles.error_save', 'Validation error'), true);
              } else {
                setStatus(j.message || t('roles.error_save', 'Error saving'), true);
              }
            }
          } catch (e) { setStatus('Invalid JSON', true); console.error(res.text); }
        } else {
          try {
            var parsed = JSON.parse(res.text || '{}');
            if (parsed && parsed.errors) {
              Object.keys(parsed.errors).forEach(function (k) { showFieldError(k, parsed.errors[k]); });
              setStatus(parsed.message || t('roles.error_save', 'Validation error'), true);
            } else {
              setStatus('HTTP ' + res.status + (parsed && parsed.message ? (': ' + parsed.message) : ''), true);
            }
          } catch (e) {
            setStatus('HTTP ' + res.status, true);
          }
        }
      })
      .catch(function (err) { console.error(err); setStatus(t('roles.error_save', 'Error saving'), true); });
  }

  function applySearch() {
    var q = searchInput && searchInput.value ? String(searchInput.value).trim().toLowerCase() : '';
    filtered = roles.filter(function (it) {
      if (!q) return true;
      if (String(it.id).indexOf(q) !== -1) return true;
      var keyText = (it.key_name || '').toLowerCase();
      var displayText = (it.display_name || '').toLowerCase();
      if (keyText.indexOf(q) !== -1) return true;
      if (displayText.indexOf(q) !== -1) return true;
      return false;
    });
    page = 1;
    renderTable();
    renderPager();
  }

  if (refreshBtn) refreshBtn.addEventListener('click', loadList);
  if (newBtn) newBtn.addEventListener('click', openNew);
  if (rolesCancel) rolesCancel.addEventListener('click', function () { if (formWrap) formWrap.style.display = 'none'; });
  if (form) form.addEventListener('submit', saveRole);
  if (searchInput) {
    var tmr = null;
    searchInput.addEventListener('input', function () { clearTimeout(tmr); tmr = setTimeout(applySearch, 160); });
  }

  document.addEventListener('DOMContentLoaded', function () {
    try { if (DIRECTION === 'rtl') root.setAttribute('dir', 'rtl'); } catch (e) {}
    loadList();
  });

  window._rolesAdmin = {
    reload: loadList,
    cache: function () { return roles; }
  };

})();
