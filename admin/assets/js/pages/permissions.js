/*!
 * admin/assets/js/pages/permissions.js
 * Full CRUD UI for permissions list (updated to call /api/routes/permissions.php by default)
 */
(function () {
  'use strict';

  // Config / globals
  var ADMIN_UI = window.ADMIN_UI || {};
  var I18N = window.I18N_FLAT || {};
  var USER = window.USER_INFO || {};
  var THEME = window.THEME || {};
  var LANG = window.LANG || 'en';
  var DIRECTION = window.DIRECTION || 'ltr';
  var CSRF = window.CSRF_TOKEN || '';
  // DEFAULT API: updated to point to routes/permissions.php as requested
  var API = window.API_PERMISSIONS || '/api/routes/permissions.php';

  // Permission check
  var CAN_MANAGE = !!(
    (USER && Number(USER.role_id) === 1) ||
    (USER && Array.isArray(USER.roles) && (USER.roles.indexOf('super_admin') !== -1 || USER.roles.indexOf('admin') !== -1)) ||
    (USER && Array.isArray(USER.permissions) && USER.permissions.indexOf('manage_permissions') !== -1)
  );

  // DOM
  var root = document.getElementById('adminPermissions');
  if (!root) return;

  var searchInput = document.getElementById('permSearch');
  var refreshBtn = document.getElementById('permRefresh');
  var newBtn = document.getElementById('permNew');
  var statusEl = document.getElementById('permStatus');
  var tableBody = document.getElementById('permTbody');
  var formWrap = document.getElementById('permFormWrap');
  var permForm = document.getElementById('permForm');
  var permIdEl = document.getElementById('permId');
  var permKeyEl = document.getElementById('permKey');
  var permDisplayEl = document.getElementById('permDisplay');
  var permDescEl = document.getElementById('permDesc');
  var permSaveBtn = document.getElementById('permSave');
  var permCancelBtn = document.getElementById('permCancel');

  var pagerWrap = document.getElementById('permPager');
  if (!pagerWrap) {
    pagerWrap = document.createElement('div');
    pagerWrap.id = 'permPager';
    pagerWrap.style.marginTop = '12px';
    root.appendChild(pagerWrap);
  }

  // State
  var cache = [];
  var filtered = [];
  var currentPage = 1;
  var perPage = 10;
  var perPageOptions = [5, 10, 20, 50];

  // Helpers
  function t(key, fallback) {
    if (!key) return fallback || '';
    if (I18N && typeof I18N[key] !== 'undefined' && I18N[key] !== '') return I18N[key];
    return fallback || (key.split('.').pop().replace(/_/g, ' '));
  }
  function esc(s) { return String(s == null ? '' : s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
  function setStatus(msg, isError) {
    if (!statusEl) return;
    statusEl.textContent = msg || '';
    statusEl.style.color = isError ? (THEME && THEME.colors_map && THEME.colors_map['error'] ? THEME.colors_map['error'] : '#b91c1c') : (THEME && THEME.colors_map && THEME.colors_map['primary'] ? THEME.colors_map['primary'] : '#064e3b');
  }

  function fetchText(url, opts) {
    opts = opts || {};
    opts.credentials = opts.credentials || 'same-origin';
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
      try { return JSON.parse(res.text || 'null'); } catch (e) { throw new Error('Invalid JSON response'); }
    });
  }
  function postForm(url, formData) {
    var opts = { method: 'POST', body: formData, credentials: 'same-origin' };
    return fetchJson(url, opts);
  }

  // Load list
  function loadPermissions() {
    setStatus(t('permissions.loading', 'Loading...'));
    var url = API + '?format=json';
    fetchJson(url, { method: 'GET' })
      .then(function (json) {
        var rows = [];
        if (!json) rows = [];
        else if (Array.isArray(json.data)) rows = json.data;
        else if (Array.isArray(json)) rows = json;
        else if (Array.isArray(json.rows)) rows = json.rows;
        else if (json.success && Array.isArray(json.data)) rows = json.data;
        cache = rows || [];
        applyFilter();
        setStatus('');
      })
      .catch(function (err) {
        console.error('loadPermissions error', err);
        setStatus(err.message || t('permissions.error_loading', 'Error loading'), true);
        cache = [];
        applyFilter();
      });
  }

  function applyFilter() {
    var q = (searchInput && searchInput.value) ? String(searchInput.value).trim().toLowerCase() : '';
    if (!q) filtered = cache.slice();
    else {
      filtered = cache.filter(function (p) {
        if (!p) return false;
        if (String(p.id).indexOf(q) !== -1) return true;
        if ((p.key_name || '').toLowerCase().indexOf(q) !== -1) return true;
        if ((p.display_name || '').toLowerCase().indexOf(q) !== -1) return true;
        if ((p.description || '').toLowerCase().indexOf(q) !== -1) return true;
        return false;
      });
    }
    currentPage = 1;
    renderTable();
    renderPager();
  }

  function renderTable() {
    if (!tableBody) return;
    tableBody.innerHTML = '';
    if (!filtered || filtered.length === 0) {
      var tr = document.createElement('tr');
      tr.innerHTML = '<td colspan="5" style="padding:12px;text-align:center;color:#666;">' + esc(t('permissions.no_permissions', 'No permissions found')) + '</td>';
      tableBody.appendChild(tr);
      return;
    }
    var total = filtered.length;
    var start = (currentPage - 1) * perPage;
    var end = Math.min(total, start + perPage);
    var pageItems = filtered.slice(start, end);
    pageItems.forEach(function (p) {
      var tr = document.createElement('tr');
      var actions = '';
      if (CAN_MANAGE) {
        actions = '<button class="btn editBtn" data-id="' + esc(p.id) + '">' + esc(t('permissions.btn_edit','Edit')) + '</button> '
                + '<button class="btn danger delBtn" data-id="' + esc(p.id) + '">' + esc(t('permissions.btn_delete','Delete')) + '</button>';
      }
      tr.innerHTML = '<td style="padding:10px;border-bottom:1px solid #eee;">' + esc(p.id) + '</td>'
                   + '<td style="padding:10px;border-bottom:1px solid #eee;"><strong>' + esc(p.key_name) + '</strong></td>'
                   + '<td style="padding:10px;border-bottom:1px solid #eee;">' + esc(p.display_name || '') + '</td>'
                   + '<td style="padding:10px;border-bottom:1px solid #eee;">' + esc(p.description || '') + '</td>'
                   + '<td style="padding:10px;border-bottom:1px solid #eee;text-align:right;">' + actions + '</td>';
      tableBody.appendChild(tr);
    });

    var editBtns = tableBody.querySelectorAll('.editBtn');
    editBtns.forEach(function (b) {
      b.addEventListener('click', function () {
        var id = this.getAttribute('data-id');
        openEdit(id);
      });
    });
    var delBtns = tableBody.querySelectorAll('.delBtn');
    delBtns.forEach(function (b) {
      b.addEventListener('click', function () {
        var id = this.getAttribute('data-id');
        if (!CAN_MANAGE) { alert(t('permissions.no_permission_notice','You do not have permission')); return; }
        if (!confirm(t('permissions.confirm_delete','Are you sure?'))) return;
        deletePermission(id);
      });
    });
  }

  function renderPager() {
    if (!pagerWrap) return;
    pagerWrap.innerHTML = '';
    var total = filtered.length || 0;
    var totalPages = Math.max(1, Math.ceil(total / perPage));
    var perSel = document.createElement('select');
    perSel.style.marginRight = '8px';
    perPageOptions.forEach(function (opt) {
      var o = document.createElement('option');
      o.value = opt;
      o.textContent = opt + ' / page';
      if (opt === perPage) o.selected = true;
      perSel.appendChild(o);
    });
    perSel.addEventListener('change', function () {
      perPage = Number(this.value) || perPage;
      currentPage = 1;
      renderTable();
      renderPager();
    });
    pagerWrap.appendChild(perSel);

    var info = document.createElement('span');
    info.style.marginRight = '12px';
    info.textContent = (total === 0) ? t('permissions.no_permissions','No permissions') : ('Showing ' + ((filtered.length) ? ((currentPage - 1) * perPage + 1) : 0) + '-' + Math.min(filtered.length, currentPage * perPage) + ' of ' + filtered.length);
    pagerWrap.appendChild(info);

    var prev = document.createElement('button');
    prev.className = 'btn';
    prev.textContent = t('permissions.prev','Prev');
    prev.disabled = currentPage <= 1;
    prev.addEventListener('click', function () { if (currentPage > 1) { currentPage--; renderTable(); renderPager(); } });
    pagerWrap.appendChild(prev);

    var maxButtons = 7;
    var startPage = Math.max(1, currentPage - Math.floor(maxButtons / 2));
    var endPage = Math.min(totalPages, startPage + maxButtons - 1);
    if (endPage - startPage < maxButtons - 1) startPage = Math.max(1, endPage - maxButtons + 1);

    for (var p = startPage; p <= endPage; p++) {
      (function (pp) {
        var b = document.createElement('button');
        b.className = 'btn small';
        b.style.margin = '0 4px';
        b.textContent = String(pp);
        if (pp === currentPage) { b.style.fontWeight = '700'; b.disabled = true; }
        b.addEventListener('click', function () { currentPage = pp; renderTable(); renderPager(); });
        pagerWrap.appendChild(b);
      })(p);
    }

    var next = document.createElement('button');
    next.className = 'btn';
    next.textContent = t('permissions.next','Next');
    next.disabled = currentPage >= totalPages;
    next.addEventListener('click', function () { if (currentPage < totalPages) { currentPage++; renderTable(); renderPager(); } });
    pagerWrap.appendChild(next);
  }

  function openEdit(id) {
    setStatus(t('permissions.loading','Loading...'));
    var found = cache.find(function (x) { return String(x.id) === String(id); });
    if (found) {
      populateForm(found);
      setStatus('');
      return;
    }
    fetchJson(API + '?_fetch_row=1&id=' + encodeURIComponent(id))
      .then(function (json) {
        var row = json && json.data ? json.data : null;
        if (row) populateForm(row);
        setStatus('');
      })
      .catch(function (err) { console.error(err); setStatus(t('permissions.error_fetch','Error fetching data'), true); });
  }

  function populateForm(p) {
    if (!formWrap) return;
    formWrap.style.display = 'block';
    if (permIdEl) permIdEl.value = p.id || '';
    if (permKeyEl) permKeyEl.value = p.key_name || '';
    if (permDisplayEl) permDisplayEl.value = p.display_name || '';
    if (permDescEl) permDescEl.value = p.description || '';
    var title = document.getElementById('permFormTitle');
    if (title) title.textContent = p.id ? (t('permissions.form_title_edit','Edit Permission')) : (t('permissions.form_title_create','Create Permission'));
    if (permKeyEl) permKeyEl.focus();
  }

  function deletePermission(id) {
    if (!CAN_MANAGE) { alert(t('permissions.no_permission_notice','You do not have permission')); return; }
    setStatus(t('permissions.processing','Processing...'));
    var fd = new FormData();
    fd.append('action', 'delete');
    fd.append('id', id);
    if (CSRF) fd.append('csrf_token', CSRF);
    fetchText(API, { method: 'POST', body: fd })
      .then(function (res) {
        if (res.status >= 200 && res.status < 300) {
          try {
            var j = JSON.parse(res.text || '{}');
            if (j && j.success) {
              setStatus(j.message || t('permissions.deleted_success','Deleted'));
              cache = cache.filter(function (x) { return String(x.id) !== String(id); });
              applyFilter();
              return;
            } else {
              setStatus(j.message || t('permissions.error_delete','Delete failed'), true);
            }
          } catch (e) { setStatus('Invalid JSON', true); }
        } else {
          setStatus('HTTP ' + res.status, true);
        }
      })
      .catch(function (err) { console.error(err); setStatus(t('permissions.error_delete','Error deleting'), true); });
  }

  function saveFromForm(e) {
    if (e && e.preventDefault) e.preventDefault();
    if (!CAN_MANAGE) { alert(t('permissions.no_permission_notice','You do not have permission')); return; }
    if (!permForm) return;
    var fd = new FormData(permForm);
    fd.set('action', 'save');
    if (CSRF) fd.set('csrf_token', CSRF);

    setStatus(t('permissions.processing','Processing...'));
    fetchText(API, { method: 'POST', body: fd })
      .then(function (res) {
        if (res.status >= 200 && res.status < 300) {
          try {
            var j = JSON.parse(res.text || '{}');
            if (j && j.success) {
              setStatus(j.message || t('permissions.saved_success','Saved'));
              if (formWrap) formWrap.style.display = 'none';
              setTimeout(loadPermissions, 300);
              return;
            } else {
              if (j.errors) {
                console.warn('validation errors', j.errors);
                setStatus(t('permissions.error_save','Validation error'), true);
              } else {
                setStatus(j.message || t('permissions.error_save','Error saving'), true);
              }
            }
          } catch (e) { setStatus('Invalid JSON', true); console.error(res.text); }
        } else {
          setStatus('HTTP ' + res.status, true);
        }
      })
      .catch(function (err) { console.error(err); setStatus(t('permissions.error_save','Error saving'), true); });
  }

  if (searchInput) {
    var searchTimer = null;
    searchInput.addEventListener('input', function () {
      clearTimeout(searchTimer);
      searchTimer = setTimeout(function () { applyFilter(); }, 180);
    });
  }
  if (refreshBtn) refreshBtn.addEventListener('click', loadPermissions);
  if (newBtn) newBtn.addEventListener('click', function () {
    if (!CAN_MANAGE) { alert(t('permissions.no_permission_notice','You do not have permission')); return; }
    if (formWrap) formWrap.style.display = 'block';
    if (permForm) permForm.reset();
    if (permIdEl) permIdEl.value = '';
    var title = document.getElementById('permFormTitle');
    if (title) title.textContent = t('permissions.form_title_create','Create Permission');
    if (permKeyEl) permKeyEl.focus();
  });
  if (permCancelBtn) permCancelBtn.addEventListener('click', function () { if (formWrap) formWrap.style.display = 'none'; });
  if (permForm) permForm.addEventListener('submit', saveFromForm);

  document.addEventListener('DOMContentLoaded', function () {
    if (DIRECTION === 'rtl') {
      var doc = document.getElementById('adminPermissions');
      if (doc) doc.setAttribute('dir', 'rtl');
    }
    loadPermissions();
  });

  window._permissionsAdmin = {
    reload: loadPermissions,
    cache: function () { return cache; },
    filtered: function () { return filtered; }
  };

})();
