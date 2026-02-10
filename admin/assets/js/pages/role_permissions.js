/**
 * admin/assets/js/pages/role_permissions.js
 * Complete client for Role <-> Permission assignments
 * Updated to use /api/Role_permissions endpoint
 */

(function () {
  'use strict';

  // Use /api/Role_permissions endpoint
  var API = window.API_ROLE_PERMISSIONS || '/api/Role_permissions';
  var CSRF = window.CSRF_TOKEN || '';
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
  var currentPage = 1;
  var perPage = 10;
  var perPageOptions = [5, 10, 20, 50];

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

  function formatDate(dateString) {
    if (!dateString) return '';
    try {
      var date = new Date(dateString);
      return date.toLocaleDateString() + ' ' + date.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
    } catch (e) {
      return dateString;
    }
  }

  function setStatus(msg, isError) {
    if (!statusEl) return;
    statusEl.textContent = msg || '';
    statusEl.style.color = isError ? 
      (THEME && THEME.colors_map && THEME.colors_map['error'] ? THEME.colors_map['error'] : '#b91c1c') : 
      (THEME && THEME.colors_map && THEME.colors_map['primary'] ? THEME.colors_map['primary'] : '#064e3b');
  }

  // fetch JSON helper
  function fetchJson(url, options) {
    var opts = {
      method: options.method || 'GET',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-Token': CSRF
      },
      credentials: 'same-origin'
    };
    
    if (options.body) {
      opts.body = JSON.stringify(options.body);
    }
    
    return fetch(url, opts)
      .then(function(response) {
        if (!response.ok) {
          return response.json().then(function(err) {
            throw new Error(err.message || 'HTTP ' + response.status);
          }).catch(function() {
            throw new Error('HTTP ' + response.status);
          });
        }
        return response.json();
      })
      .then(function(data) {
        if (data && data.error) {
          throw new Error(data.error);
        }
        return data;
      });
  }

  // lookups: roles & permissions
  function loadLookups(cb) {
    // Use new API endpoints
    var rolesApi = '/api/roles';
    var permsApi = '/api/permissions';

    Promise.all([
      fetchJson(rolesApi, { method: 'GET' }).catch(function () { return { data: [] }; }),
      fetchJson(permsApi, { method: 'GET' }).catch(function () { return { data: [] }; })
    ]).then(function (arr) {
      var rolesData = arr[0];
      var permsData = arr[1];
      
      // Extract data from response
      rolesCache = (rolesData && rolesData.success && Array.isArray(rolesData.data)) ? 
        rolesData.data : 
        (Array.isArray(rolesData) ? rolesData : []);
      
      permsCache = (permsData && permsData.success && Array.isArray(permsData.data)) ? 
        permsData.data : 
        (Array.isArray(permsData) ? permsData : []);
      
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
    
    var url = API + (params.length ? ('?' + params.join('&')) : '');
    
    fetchJson(url, { method: 'GET' })
      .then(function (data) {
        if (data && data.success && Array.isArray(data.data)) {
          assignments = data.data;
        } else if (Array.isArray(data)) {
          assignments = data;
        } else {
          assignments = [];
        }
        
        filtered = assignments.slice();
        currentPage = 1;
        renderTable();
        renderPager();
        setStatus('');
      })
      .catch(function (err) {
        console.error('loadList error', err);
        setStatus(err.message || t('role_permissions.error_loading', 'Error loading'), true);
        assignments = []; 
        filtered = [];
        renderTable(); 
        renderPager();
      });
  }

  // render table
  function renderTable() {
    if (!tableBody) return;
    tableBody.innerHTML = '';
    
    if (!filtered || filtered.length === 0) {
      var tr = document.createElement('tr');
      tr.innerHTML = '<td colspan="5" style="padding:12px;text-align:center;color:#666;">' + 
        esc(t('role_permissions.no_entries', 'No assignments found')) + '</td>';
      tableBody.appendChild(tr);
      return;
    }
    
    var total = filtered.length;
    var start = (currentPage - 1) * perPage;
    var end = Math.min(total, start + perPage);
    var pageItems = filtered.slice(start, end);
    
    pageItems.forEach(function (assignment) {
      var tr = document.createElement('tr');
      var actions = '';
      
      if (CAN_MANAGE) {
        actions = '<button class="btn danger removeBtn" data-id="' + esc(assignment.id) + '">' + 
                  esc(t('role_permissions.btn_remove', 'Remove')) + '</button>';
      }
      
      var roleName = esc(assignment.role_display || assignment.role_key || 
                        ('role ' + (assignment.role_id || '')));
      var permName = esc(assignment.permission_display || assignment.permission_key || 
                        ('perm ' + (assignment.permission_id || '')));
      
      tr.innerHTML = '<td style="padding:10px;border-bottom:1px solid var(--theme-border,#e5e7eb);">' + 
                     esc(assignment.id) + '</td>' +
                     '<td style="padding:10px;border-bottom:1px solid var(--theme-border,#e5e7eb);"><strong>' + 
                     roleName + '</strong></td>' +
                     '<td style="padding:10px;border-bottom:1px solid var(--theme-border,#e5e7eb);">' + 
                     permName + '</td>' +
                     '<td style="padding:10px;border-bottom:1px solid var(--theme-border,#e5e7eb);">' + 
                     esc(formatDate(assignment.created_at || assignment.assigned_at || '')) + '</td>' +
                     '<td style="padding:10px;border-bottom:1px solid var(--theme-border,#e5e7eb);text-align:' + 
                     (DIRECTION === 'rtl' ? 'left' : 'right') + ';">' + actions + '</td>';
      tableBody.appendChild(tr);
    });

    // bind remove handlers
    var removeBtns = tableBody.querySelectorAll('.removeBtn');
    removeBtns.forEach(function (b) {
      b.addEventListener('click', function () {
        var id = this.getAttribute('data-id');
        if (!CAN_MANAGE) { 
          alert(t('role_permissions.no_permission_notice', 'You do not have permission')); 
          return; 
        }
        if (!confirm(t('role_permissions.confirm_remove', 'Are you sure you want to remove this assignment?'))) return;
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
    
    // Items per page selector
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
    pager.appendChild(perSel);

    // Info text
    var info = document.createElement('span');
    info.style.marginRight = '12px';
    var start = total === 0 ? 0 : (currentPage - 1) * perPage + 1;
    var end = Math.min(total, currentPage * perPage);
    info.textContent = total === 0 ? 
      t('role_permissions.no_entries', 'No assignments') : 
      ('Showing ' + start + '-' + end + ' of ' + total);
    pager.appendChild(info);

    // Previous button
    var prev = document.createElement('button');
    prev.className = 'btn';
    prev.textContent = t('role_permissions.prev', 'Prev');
    prev.disabled = currentPage <= 1;
    prev.addEventListener('click', function () { 
      if (currentPage > 1) { 
        currentPage--; 
        renderTable(); 
        renderPager(); 
      } 
    });
    pager.appendChild(prev);

    // Page buttons
    var maxButtons = 7;
    var startPage = Math.max(1, currentPage - Math.floor(maxButtons / 2));
    var endPage = Math.min(totalPages, startPage + maxButtons - 1);
    if (endPage - startPage < maxButtons - 1) {
      startPage = Math.max(1, endPage - maxButtons + 1);
    }

    for (var p = startPage; p <= endPage; p++) {
      (function (pageNum) {
        var b = document.createElement('button');
        b.className = 'btn small';
        b.style.margin = '0 4px';
        b.textContent = String(pageNum);
        if (pageNum === currentPage) { 
          b.style.fontWeight = '700'; 
          b.disabled = true; 
        }
        b.addEventListener('click', function () { 
          currentPage = pageNum; 
          renderTable(); 
          renderPager(); 
        });
        pager.appendChild(b);
      })(p);
    }

    // Next button
    var next = document.createElement('button');
    next.className = 'btn';
    next.textContent = t('role_permissions.next', 'Next');
    next.disabled = currentPage >= totalPages;
    next.addEventListener('click', function () { 
      if (currentPage < totalPages) { 
        currentPage++; 
        renderTable(); 
        renderPager(); 
      } 
    });
    pager.appendChild(next);
  }

  // remove assignment
  function removeAssignment(id) {
    if (!CAN_MANAGE) { 
      alert(t('role_permissions.no_permission_notice', 'You do not have permission')); 
      return; 
    }
    
    setStatus(t('role_permissions.processing', 'Processing...'));
    
    fetchJson(API, {
      method: 'DELETE',
      body: { id: id }
    })
    .then(function (data) {
      if (data && data.success) {
        setStatus(t('role_permissions.deleted', 'Assignment removed successfully'));
        assignments = assignments.filter(function (x) { return String(x.id) !== String(id); });
        filtered = assignments.slice();
        renderTable();
        renderPager();
      } else {
        setStatus(data.message || t('role_permissions.error_delete', 'Delete failed'), true);
      }
    })
    .catch(function (err) { 
      console.error(err); 
      setStatus(err.message || t('role_permissions.error_delete', 'Error deleting'), true); 
    });
  }

  // open assign form
  function openAssign() {
    if (!CAN_MANAGE) { 
      alert(t('role_permissions.no_permission_notice', 'You do not have permission')); 
      return; 
    }
    if (!formWrap) return;
    
    clearFormErrors();
    form.reset();
    if (rpId) rpId.value = '';
    formWrap.style.display = 'block';
    
    var title = document.getElementById('rpFormTitle');
    if (title) {
      title.textContent = t('role_permissions.form_title', 'Assign Permission to Role');
    }
    
    if (rpRole) rpRole.focus();
    window.scrollTo({ top: formWrap.offsetTop - 20, behavior: 'smooth' });
  }

  // validation helpers
  function clearFormErrors() {
    if (!form) return;
    var prev = form.querySelectorAll('.field-error');
    prev.forEach(function (el) { 
      if (el.parentNode) el.parentNode.removeChild(el); 
    });
  }
  
  function showFieldError(fieldName, msg) {
    var field = form.querySelector('[name="' + fieldName + '"]');
    if (!field) { 
      setStatus(msg, true); 
      return; 
    }
    
    var err = document.createElement('div');
    err.className = 'field-error';
    err.style.color = (THEME && THEME.colors_map && THEME.colors_map['error']) ? 
      THEME.colors_map['error'] : '#b91c1c';
    err.style.fontSize = '13px';
    err.style.marginTop = '6px';
    err.textContent = msg;
    
    if (field.parentNode) {
      field.parentNode.insertBefore(err, field.nextSibling);
    }
  }

  // save assignment (create)
  function saveAssign(e) {
    if (e && e.preventDefault) e.preventDefault();
    if (!CAN_MANAGE) { 
      alert(t('role_permissions.no_permission_notice', 'You do not have permission')); 
      return; 
    }
    if (!form) return;

    clearFormErrors();
    
    var data = {
      role_id: rpRole ? rpRole.value : '',
      permission_id: rpPermission ? rpPermission.value : ''
    };
    
    // Validate required fields
    var missing = false;
    if (!data.role_id) { 
      showFieldError('role_id', t('role_permissions.error_role_required', 'Role is required')); 
      missing = true; 
    }
    if (!data.permission_id) { 
      showFieldError('permission_id', t('role_permissions.error_permission_required', 'Permission is required')); 
      missing = true; 
    }
    
    if (missing) { 
      setStatus(t('role_permissions.error_save', 'Validation error'), true); 
      return; 
    }

    setStatus(t('role_permissions.processing', 'Processing...'));
    
    fetchJson(API, {
      method: 'POST',
      body: data
    })
    .then(function (response) {
      if (response && response.success) {
        setStatus(t('role_permissions.saved', 'Assignment saved successfully'));
        
        // Reset form and hide it
        if (form) form.reset();
        if (rpId) rpId.value = '';
        if (formWrap) formWrap.style.display = 'none';
        
        // Reload assignments list
        setTimeout(loadList, 500);
      } else {
        setStatus(response.message || t('role_permissions.error_save', 'Error saving'), true);
      }
    })
    .catch(function (err) { 
      console.error(err); 
      setStatus(err.message || t('role_permissions.error_save', 'Error saving'), true); 
    });
  }

  // search filter (client-side)
  function applySearchFilter() {
    var q = searchInput && searchInput.value ? 
      String(searchInput.value).trim().toLowerCase() : '';
    
    if (!q) {
      filtered = assignments.slice();
    } else {
      filtered = assignments.filter(function (assignment) {
        if (roleFilter && roleFilter.value && String(assignment.role_id) !== String(roleFilter.value)) return false;
        if (permissionFilter && permissionFilter.value && String(assignment.permission_id) !== String(permissionFilter.value)) return false;
        if (!q) return true;
        if (String(assignment.id).indexOf(q) !== -1) return true;
        var roleText = (assignment.role_display || assignment.role_key || '').toLowerCase();
        var permText = (assignment.permission_display || assignment.permission_key || '').toLowerCase();
        if (roleText.indexOf(q) !== -1) return true;
        if (permText.indexOf(q) !== -1) return true;
        return false;
      });
    }
    
    currentPage = 1;
    renderTable();
    renderPager();
  }

  // wire events
  function initEventListeners() {
    if (roleFilter) roleFilter.addEventListener('change', applySearchFilter);
    if (permissionFilter) permissionFilter.addEventListener('change', applySearchFilter);
    if (refreshBtn) refreshBtn.addEventListener('click', loadList);
    if (newBtn) newBtn.addEventListener('click', openAssign);
    if (rpCancel) rpCancel.addEventListener('click', function () { 
      if (formWrap) {
        formWrap.style.display = 'none';
        form.reset();
      }
    });
    if (form) form.addEventListener('submit', saveAssign);
    if (searchInput) {
      var searchTimer = null;
      searchInput.addEventListener('input', function () {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(applySearchFilter, 300);
      });
    }
  }

  // init
  function init() {
    try { 
      if (DIRECTION === 'rtl') root.setAttribute('dir', 'rtl'); 
    } catch (e) {}
    
    initEventListeners();
    loadLookups(function () {
      loadList();
    });
  }

  // Start when DOM is ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

  // expose for debugging
  window._rolePermissionsAdmin = {
    reload: loadList,
    lookups: function () { return { roles: rolesCache, permissions: permsCache }; },
    cache: function () { return assignments; }
  };

})();