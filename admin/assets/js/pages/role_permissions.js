/*!
 * admin/assets/js/pages/role_permissions.js
 * Complete client for Role <-> Permission assignments
 *
 * Features:
 *  - Loads roles & permissions lookups
 *  - Lists assignments (with search, role/permission filters, pagination)
 *  - Assign permission to role (create)
 *  - Remove assignment (delete)
 *  - Inline validation error display
 *  - Respects CSRF token and ADMIN_UI/user permissions exposed on window
 *
 * Expects these globals set by admin/fragments/role_permissions.php:
 *  - window.API_ROLE_PERMISSIONS (string)  -> '/api/routes/Role_permissions.php'
 *  - window.CSRF_TOKEN
 *  - window.ADMIN_UI (optional) with admin UI api paths
 *  - window.I18N_FLAT (optional) translations
 *  - window.USER_INFO
 *  - window.THEME (optional) for colors
 */

(function () {
  'use strict';

  var API = window.API_ROLE_PERMISSIONS || '/api/routes/Role_permissions.php';
  var CSRF = window.CSRF_TOKEN || '';
  var ADMIN_UI = window.ADMIN_UI || {};
  var I18N = window.I18N_FLAT || {};
  var USER = window.USER_INFO || {};
  var THEME = window.THEME || {};
  var DIRECTION = window.DIRECTION || 'ltr';

  // permission check: role_id==1 OR roles contain super_admin/admin OR user.permissions contains manage_role_permissions
  var CAN_MANAGE = !!(
    (USER && Number(USER.role_id) === 1) ||
    (USER && Array.isArray(USER.roles) && (USER.roles.indexOf('super_admin') !== -1 || USER.roles.indexOf('admin') !== -1)) ||
    (USER && Array.isArray(USER.permissions) && USER.permissions.indexOf('manage_role_permissions') !== -1)
  );

  // DOM refs
  var root = document.getElementById('adminRolePermissions');
  if (!root) return;

  var roleFilter = document.getElementById('rpRoleFilter');
  var permissionFilter = document.getElementById('rpPermissionFilter');
  var searchInput = document.getElementById('rpSearch');
  var refreshBtn = document.getElementById('rpRefresh');
  var newBtn = document.getElementById('rpNew');
  var statusEl = document.getElementById('rpStatus');
  var tableBody = document.getElementById('rpTbody');
  var pager = document.getElementById('rpPager');

  var formWrap = document.getElementById('rpFormWrap');
  var form = document.getElementById('rpForm');
  var rpId = document.getElementById('rpId');
  var rpRole = document.getElementById('rpRole');
  var rpPermission = document.getElementById('rpPermission');
  var rpCancel = document.getElementById('rpCancel');

  // state
  var rolesCache = [];
  var permsCache = [];
  var assignments = []; // full list from server
  var filtered = [];
  var page = 1;
  var perPage = 10;

  // i18n helper
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
    statusEl.style.color = isError ? (THEME && THEME.colors_map && THEME.colors_map['error'] ? THEME.colors_map['error'] : '#b91c1c') : (THEME && THEME.colors_map && THEME.colors_map['primary'] ? THEME.colors_map['primary'] : '#064e3b');
  }

  // fetch helpers
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

  // lookups: roles & permissions
  function loadLookups(cb) {
    // Use direct routes paths
    var rolesApi = '/api/routes/roles.php';
    var permsApi = '/api/routes/permissions.php';

    Promise.all([
      fetchJson(rolesApi + '?format=json').catch(function () { return { data: [] }; }),
      fetchJson(permsApi + '?format=json').catch(function () { return { data: [] }; })
    ]).then(function (arr) {
      var rolesRes = arr[0] && arr[0].data ? arr[0].data : [];
      var permsRes = arr[1] && arr[1].data ? arr[1].data : [];
      rolesCache = rolesRes;
      permsCache = permsRes;
      populateSelectors();
      if (typeof cb === 'function') cb(null);
    }).catch(function (err) {
      console.error('loadLookups error', err);
      if (typeof cb === 'function') cb(err);
    });
  }

  function populateSelectors() {
    if (roleFilter) {
      roleFilter.innerHTML = '<option value="">' + esc(t('role_permissions.filter_all_roles', 'All roles')) + '</option>';
      rolesCache.forEach(function (r) {
        var o = document.createElement('option');
        o.value = r.id;
        o.textContent = r.display_name || r.key_name || ('role ' + r.id);
        roleFilter.appendChild(o);
      });
    }
    if (permissionFilter) {
      permissionFilter.innerHTML = '<option value="">' + esc(t('role_permissions.filter_all_permissions', 'All permissions')) + '</option>';
      permsCache.forEach(function (p) {
        var o = document.createElement('option');
        o.value = p.id;
        o.textContent = p.display_name || p.key_name || ('perm ' + p.id);
        permissionFilter.appendChild(o);
      });
    }
    if (rpRole) {
      rpRole.innerHTML = '<option value="">' + esc(t('role_permissions.select_role', 'Select role')) + '</option>';
      rolesCache.forEach(function (r) {
        var o = document.createElement('option');
        o.value = r.id;
        o.textContent = r.display_name || r.key_name || ('role ' + r.id);
        rpRole.appendChild(o);
      });
    }
    if (rpPermission) {
      rpPermission.innerHTML = '<option value="">' + esc(t('role_permissions.select_permission', 'Select permission')) + '</option>';
      permsCache.forEach(function (p) {
        var o = document.createElement('option');
        o.value = p.id;
        o.textContent = p.display_name || p.key_name || ('perm ' + p.id);
        rpPermission.appendChild(o);
      });
    }
  }

  // load list of assignments
  function loadList() {
    setStatus(t('role_permissions.loading', 'Loading...'));
    var params = [];
    if (roleFilter && roleFilter.value) params.push('role_id=' + encodeURIComponent(roleFilter.value));
    if (permissionFilter && permissionFilter.value) params.push('permission_id=' + encodeURIComponent(permissionFilter.value));
    if (searchInput && searchInput.value) params.push('q=' + encodeURIComponent(searchInput.value));
    params.push('format=json');
    var url = API + (params.length ? ('?' + params.join('&')) : '');
    fetchJson(url, { method: 'GET' })
      .then(function (json) {
        assignments = Array.isArray(json.data) ? json.data : [];
        filtered = assignments.slice();
        page = 1;
        renderTable();
        renderPager();
        setStatus('');
      })
      .catch(function (err) {
        console.error('loadList error', err);
        setStatus(err.message || t('role_permissions.error_loading', 'Error loading'), true);
        assignments = []; filtered = [];
        renderTable(); renderPager();
      });
  }

  // render table
  function renderTable() {
    if (!tableBody) return;
    tableBody.innerHTML = '';
    if (!filtered || filtered.length === 0) {
      var tr = document.createElement('tr');
      tr.innerHTML = '<td colspan="5" style="padding:12px;text-align:center;color:#666;">' + esc(t('role_permissions.no_entries', 'No assignments found')) + '</td>';
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
        actions = '<button class="btn editBtn" data-id="' + esc(it.id) + '" data-role="' + esc(it.role_id) + '" data-perm="' + esc(it.permission_id) + '" style="margin-right:6px;">' + esc(t('role_permissions.btn_edit', 'Edit')) + '</button>';
        actions += '<button class="btn danger removeBtn" data-id="' + esc(it.id) + '">' + esc(t('role_permissions.btn_remove', 'Remove')) + '</button>';
      }
      var roleName = esc(it.role_display || it.role_key || ('role ' + (it.role_id || '')));
      var permName = esc(it.permission_display || it.permission_key || ('perm ' + (it.permission_id || '')));
      tr.innerHTML = '<td style="padding:10px;border-bottom:1px solid var(--theme-border,#333);">' + esc(it.id) + '</td>'
                   + '<td style="padding:10px;border-bottom:1px solid var(--theme-border,#333);">' + roleName + '</td>'
                   + '<td style="padding:10px;border-bottom:1px solid var(--theme-border,#333);">' + permName + '</td>'
                   + '<td style="padding:10px;border-bottom:1px solid var(--theme-border,#333);">' + esc(it.created_at || '') + '</td>'
                   + '<td style="padding:10px;border-bottom:1px solid var(--theme-border,#333);text-align:right;">' + actions + '</td>';
      tableBody.appendChild(tr);
    });

    // bind edit handlers
    var edits = tableBody.querySelectorAll('.editBtn');
    edits.forEach(function (b) {
      b.addEventListener('click', function () {
        var id = this.getAttribute('data-id');
        var roleId = this.getAttribute('data-role');
        var permId = this.getAttribute('data-perm');
        if (!CAN_MANAGE) { alert(t('role_permissions.no_permission_notice', 'You do not have permission')); return; }
        openEdit(id, roleId, permId);
      });
    });

    // bind remove handlers
    var removes = tableBody.querySelectorAll('.removeBtn');
    removes.forEach(function (b) {
      b.addEventListener('click', function () {
        var id = this.getAttribute('data-id');
        if (!CAN_MANAGE) { alert(t('role_permissions.no_permission_notice', 'You do not have permission')); return; }
        if (!confirm(t('role_permissions.confirm_remove', 'Remove this assignment?'))) return;
        removeAssignment(id);
      });
    });
  }

  // render pager
  function renderPager() {
    if (!pager) return;
    pager.innerHTML = '';
    var total = filtered.length || 0;
    var totalPages = Math.max(1, Math.ceil(total / perPage));
    var info = document.createElement('span');
    info.textContent = total === 0 ? t('role_permissions.no_entries', 'No assignments') : ('Showing ' + (total === 0 ? 0 : ((page - 1) * perPage + 1)) + '-' + Math.min(total, page * perPage) + ' of ' + total);
    pager.appendChild(info);

    var prev = document.createElement('button');
    prev.className = 'btn';
    prev.textContent = t('role_permissions.prev', 'Prev');
    prev.disabled = page <= 1;
    prev.style.marginLeft = '8px';
    prev.addEventListener('click', function () { if (page > 1) { page--; renderTable(); renderPager(); } });
    pager.appendChild(prev);

    var next = document.createElement('button');
    next.className = 'btn';
    next.textContent = t('role_permissions.next', 'Next');
    next.disabled = page >= totalPages;
    next.style.marginLeft = '6px';
    next.addEventListener('click', function () { if (page < totalPages) { page++; renderTable(); renderPager(); } });
    pager.appendChild(next);
  }

  // remove assignment
  function removeAssignment(id) {
    setStatus(t('role_permissions.processing', 'Processing...'));
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
              setStatus(j.message || t('role_permissions.deleted', 'Deleted'));
              // reload list
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
      .catch(function (err) { console.error(err); setStatus(t('role_permissions.error_delete', 'Error deleting'), true); });
  }

  // open assign form
  function openAssign() {
    if (!CAN_MANAGE) { alert(t('role_permissions.no_permission_notice', 'You do not have permission')); return; }
    if (!formWrap) return;
    clearFormErrors();
    form.reset();
    if (rpId) rpId.value = '';
    if (formWrap) formWrap.style.display = 'block';
    var title = document.getElementById('rpFormTitle');
    if (title) title.textContent = t('role_permissions.form_title', 'Assign Permission to Role');
    if (rpRole) rpRole.focus();
  }

  // open edit form
  function openEdit(id, roleId, permId) {
    if (!CAN_MANAGE) { alert(t('role_permissions.no_permission_notice', 'You do not have permission')); return; }
    if (!formWrap) return;
    clearFormErrors();
    form.reset();
    if (rpId) rpId.value = id;
    if (rpRole) rpRole.value = roleId;
    if (rpPermission) rpPermission.value = permId;
    if (formWrap) formWrap.style.display = 'block';
    var title = document.getElementById('rpFormTitle');
    if (title) title.textContent = t('role_permissions.form_title_edit', 'Edit Role Permission');
    if (rpRole) rpRole.focus();
  }

  // validation helpers
  function clearFormErrors() {
    var prev = form.querySelectorAll('.field-error');
    prev.forEach(function (el) { el.parentNode && el.parentNode.removeChild(el); });
  }
  function showFieldError(fieldName, msg) {
    var field = form.querySelector('[name="' + fieldName + '"]');
    if (!field) { setStatus(msg, true); return; }
    var err = document.createElement('div');
    err.className = 'field-error';
    err.style.color = (THEME && THEME.colors_map && THEME.colors_map['error']) ? THEME.colors_map['error'] : '#b91c1c';
    err.style.fontSize = '13px';
    err.style.marginTop = '6px';
    err.textContent = msg;
    field.parentNode && field.parentNode.insertBefore(err, field.nextSibling);
  }

  // save assignment (create)
  function saveAssign(e) {
    if (e && e.preventDefault) e.preventDefault();
    if (!CAN_MANAGE) { alert(t('role_permissions.no_permission_notice', 'You do not have permission')); return; }
    if (!form) return;

    clearFormErrors();
    var fd = new FormData(form);
    
    // Remove empty id field to avoid validation errors
    if (!fd.get('id') || fd.get('id') === '') {
      fd.delete('id');
    }
    
    fd.set('action', 'save');
    if (CSRF) fd.set('csrf_token', CSRF);

    // basic client-side checks
    var missing = false;
    if (!fd.get('role_id') || fd.get('role_id') === '') { showFieldError('role_id', t('role_permissions.error_role_required', 'Role is required')); missing = true; }
    if (!fd.get('permission_id') || fd.get('permission_id') === '') { showFieldError('permission_id', t('role_permissions.error_permission_required', 'Permission is required')); missing = true; }
    if (missing) { setStatus(t('role_permissions.error_save', 'Validation error'), true); return; }

    setStatus(t('role_permissions.processing', 'Processing...'));
    fetchText(API, { method: 'POST', body: fd })
      .then(function (res) {
        if (res.status >= 200 && res.status < 300) {
          try {
            var j = JSON.parse(res.text || '{}');
            if (j.success) {
              setStatus(j.message || t('role_permissions.saved', 'Saved'));
              if (formWrap) formWrap.style.display = 'none';
              loadList();
              return;
            } else {
              if (j.errors && typeof j.errors === 'object') {
                Object.keys(j.errors).forEach(function (k) { showFieldError(k, j.errors[k]); });
                setStatus(t('role_permissions.error_save', 'Validation error'), true);
              } else {
                setStatus(j.message || t('role_permissions.error_save', 'Error saving'), true);
              }
            }
          } catch (e) { setStatus('Invalid JSON', true); console.error(res.text); }
        } else {
          // try parse body to show errors
          try {
            var parsed = JSON.parse(res.text || '{}');
            if (parsed && parsed.errors) {
              Object.keys(parsed.errors).forEach(function (k) { showFieldError(k, parsed.errors[k]); });
              setStatus(parsed.message || t('role_permissions.error_save', 'Validation error'), true);
            } else {
              setStatus('HTTP ' + res.status + (parsed && parsed.message ? (': ' + parsed.message) : ''), true);
            }
          } catch (e) {
            setStatus('HTTP ' + res.status, true);
          }
        }
      })
      .catch(function (err) { console.error(err); setStatus(t('role_permissions.error_save', 'Error saving'), true); });
  }

  // search filter (client-side)
  function applySearchFilter() {
    var q = searchInput && searchInput.value ? String(searchInput.value).trim().toLowerCase() : '';
    filtered = assignments.filter(function (it) {
      if (roleFilter && roleFilter.value && String(it.role_id) !== String(roleFilter.value)) return false;
      if (permissionFilter && permissionFilter.value && String(it.permission_id) !== String(permissionFilter.value)) return false;
      if (!q) return true;
      if (String(it.id).indexOf(q) !== -1) return true;
      var roleText = (it.role_display || it.role_key || '').toLowerCase();
      var permText = (it.permission_display || it.permission_key || '').toLowerCase();
      if (roleText.indexOf(q) !== -1) return true;
      if (permText.indexOf(q) !== -1) return true;
      return false;
    });
    page = 1;
    renderTable();
    renderPager();
  }

  // wire events
  if (roleFilter) roleFilter.addEventListener('change', loadList);
  if (permissionFilter) permissionFilter.addEventListener('change', loadList);
  if (refreshBtn) refreshBtn.addEventListener('click', loadList);
  if (newBtn) newBtn.addEventListener('click', openAssign);
  if (rpCancel) rpCancel.addEventListener('click', function () { if (formWrap) formWrap.style.display = 'none'; });
  if (form) form.addEventListener('submit', saveAssign);
  if (searchInput) {
    var tmr = null;
    searchInput.addEventListener('input', function () { clearTimeout(tmr); tmr = setTimeout(applySearchFilter, 160); });
  }

  // init
  document.addEventListener('DOMContentLoaded', function () {
    try { if (DIRECTION === 'rtl') root.setAttribute('dir', 'rtl'); } catch (e) {}
    loadLookups(function () {
      loadList();
    });
  });

  // expose for debugging
  window._rolePermissionsAdmin = {
    reload: loadList,
    lookups: function () { return { roles: rolesCache, permissions: permsCache }; },
    cache: function () { return assignments; }
  };

})();