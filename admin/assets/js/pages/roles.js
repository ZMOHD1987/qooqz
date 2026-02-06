/**
 * admin/assets/js/pages/roles.js
 * Complete client for Roles management
 * Updated to use /api/roles endpoint
 */

(function () {
  'use strict';

  // Use /api/roles endpoint
  var API = window.API_ROLES || '/api/roles';
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
  var currentPage = 1;
  var perPage = 10;
  var perPageOptions = [5, 10, 20, 50];

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
      return date.toLocaleDateString();
    } catch (e) {
      return dateString;
    }
  }

  function setStatus(msg, isError) {
    if (!statusEl) return;
    statusEl.textContent = msg || '';
    statusEl.style.color = isError ? 
      (THEME && THEME.colors_map && THEME.colors_map['error'] ? THEME.colors_map['error'] : '#EF4444') : 
      (THEME && THEME.colors_map && THEME.colors_map['primary'] ? THEME.colors_map['primary'] : '#10B981');
  }

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

  function loadList() {
    setStatus(t('roles.loading', 'Loading...'));
    
    fetchJson(API, { method: 'GET' })
      .then(function (data) {
        if (data && data.success && Array.isArray(data.data)) {
          roles = data.data;
        } else if (Array.isArray(data)) {
          roles = data;
        } else {
          roles = [];
        }
        
        filtered = roles.slice();
        currentPage = 1;
        renderTable();
        renderPager();
        setStatus('');
      })
      .catch(function (err) {
        console.error('loadList error', err);
        setStatus(err.message || t('roles.error_loading', 'Error loading'), true);
        roles = []; 
        filtered = [];
        renderTable(); 
        renderPager();
      });
  }

  function renderTable() {
    if (!tableBody) return;
    tableBody.innerHTML = '';
    
    if (!filtered || filtered.length === 0) {
      var tr = document.createElement('tr');
      tr.innerHTML = '<td colspan="4" style="padding:12px;text-align:center;color:#666;">' + 
        esc(t('roles.no_entries', 'No roles found')) + '</td>';
      tableBody.appendChild(tr);
      return;
    }
    
    var total = filtered.length;
    var start = (currentPage - 1) * perPage;
    var end = Math.min(total, start + perPage);
    var pageItems = filtered.slice(start, end);
    
    pageItems.forEach(function (role) {
      var tr = document.createElement('tr');
      var actions = '';
      
      if (CAN_MANAGE) {
        actions = '<button class="btn editBtn" data-id="' + esc(role.id) + '">' + 
                  esc(t('roles.btn_edit', 'Edit')) + '</button> ' +
                  '<button class="btn danger deleteBtn" data-id="' + esc(role.id) + '">' + 
                  esc(t('roles.btn_delete', 'Delete')) + '</button>';
      }
      
      tr.innerHTML = '<td style="padding:10px;border-bottom:1px solid var(--theme-border,#e5e7eb);">' + esc(role.id) + '</td>' +
                     '<td style="padding:10px;border-bottom:1px solid var(--theme-border,#e5e7eb);"><strong>' + esc(role.key_name) + '</strong></td>' +
                     '<td style="padding:10px;border-bottom:1px solid var(--theme-border,#e5e7eb);">' + esc(role.display_name) + '</td>' +
                     '<td style="padding:10px;border-bottom:1px solid var(--theme-border,#e5e7eb);text-align:' + (DIRECTION === 'rtl' ? 'left' : 'right') + ';">' + actions + '</td>';
      tableBody.appendChild(tr);
    });

    // Attach event listeners
    var editBtns = tableBody.querySelectorAll('.editBtn');
    editBtns.forEach(function (b) {
      b.addEventListener('click', function () {
        var id = this.getAttribute('data-id');
        openEdit(id);
      });
    });

    var deleteBtns = tableBody.querySelectorAll('.deleteBtn');
    deleteBtns.forEach(function (b) {
      b.addEventListener('click', function () {
        var id = this.getAttribute('data-id');
        if (!CAN_MANAGE) { 
          alert(t('roles.no_permission_notice', 'You do not have permission')); 
          return; 
        }
        if (!confirm(t('roles.confirm_delete', 'Are you sure you want to delete this role?'))) return;
        deleteRole(id);
      });
    });
  }

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
      t('roles.no_entries', 'No roles') : 
      ('Showing ' + start + '-' + end + ' of ' + total);
    pager.appendChild(info);

    // Previous button
    var prev = document.createElement('button');
    prev.className = 'btn';
    prev.textContent = t('roles.prev', 'Prev');
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
    next.textContent = t('roles.next', 'Next');
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

  function deleteRole(id) {
    if (!CAN_MANAGE) { 
      alert(t('roles.no_permission_notice', 'You do not have permission')); 
      return; 
    }
    
    setStatus(t('roles.processing', 'Processing...'));
    
    fetchJson(API, {
      method: 'DELETE',
      body: { id: id }
    })
    .then(function (data) {
      if (data && data.success) {
        setStatus(t('roles.deleted', 'Role deleted successfully'));
        roles = roles.filter(function (x) { return String(x.id) !== String(id); });
        filtered = roles.slice();
        renderTable();
        renderPager();
        
        // Hide form if editing the deleted role
        if (formWrap.style.display !== 'none' && rolesId && rolesId.value === String(id)) {
          formWrap.style.display = 'none';
        }
      } else {
        setStatus(data.message || t('roles.error_delete', 'Delete failed'), true);
      }
    })
    .catch(function (err) { 
      console.error(err); 
      setStatus(err.message || t('roles.error_delete', 'Error deleting'), true); 
    });
  }

  function openNew() {
    if (!CAN_MANAGE) { 
      alert(t('roles.no_permission_notice', 'You do not have permission')); 
      return; 
    }
    if (!formWrap) return;
    
    clearFormErrors();
    form.reset();
    if (rolesId) rolesId.value = '';
    formWrap.style.display = 'block';
    
    var title = document.getElementById('rolesFormTitle');
    if (title) {
      title.textContent = t('roles.form_title', 'Add New Role');
    }
    
    if (rolesKeyName) rolesKeyName.focus();
    window.scrollTo({ top: formWrap.offsetTop - 20, behavior: 'smooth' });
  }

  function openEdit(id) {
    var found = roles.find(function (x) { return String(x.id) === String(id); });
    if (found) {
      populateForm(found);
      return;
    }
    
    setStatus(t('roles.loading', 'Loading...'));
    fetchJson(API + '?id=' + encodeURIComponent(id), { method: 'GET' })
      .then(function (data) {
        var row = data && data.data ? data.data : null;
        if (row) {
          populateForm(row);
        }
        setStatus('');
      })
      .catch(function (err) { 
        console.error(err); 
        setStatus(t('roles.error_fetch', 'Error fetching data'), true); 
      });
  }

  function populateForm(role) {
    if (!formWrap) return;
    clearFormErrors();
    
    if (rolesId) rolesId.value = role.id || '';
    if (rolesKeyName) rolesKeyName.value = role.key_name || '';
    if (rolesDisplayName) rolesDisplayName.value = role.display_name || '';
    formWrap.style.display = 'block';
    
    var title = document.getElementById('rolesFormTitle');
    if (title) {
      title.textContent = role.id ? 
        t('roles.form_title_edit', 'Edit Role') : 
        t('roles.form_title', 'Add New Role');
    }
    
    if (rolesKeyName) rolesKeyName.focus();
    window.scrollTo({ top: formWrap.offsetTop - 20, behavior: 'smooth' });
  }

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
      THEME.colors_map['error'] : '#EF4444';
    err.style.fontSize = '13px';
    err.style.marginTop = '6px';
    err.textContent = msg;
    
    if (field.parentNode) {
      field.parentNode.insertBefore(err, field.nextSibling);
    }
  }

  function saveRole(e) {
    if (e && e.preventDefault) e.preventDefault();
    if (!CAN_MANAGE) { 
      alert(t('roles.no_permission_notice', 'You do not have permission')); 
      return; 
    }
    if (!form) return;

    clearFormErrors();
    
    var data = {
      key_name: rolesKeyName ? rolesKeyName.value.trim() : '',
      display_name: rolesDisplayName ? rolesDisplayName.value.trim() : ''
    };
    
    var id = rolesId ? rolesId.value : '';
    if (id) data.id = id;
    
    // Validate required fields
    var missing = false;
    if (!data.key_name) { 
      showFieldError('key_name', t('roles.error_key_required', 'Key name is required')); 
      missing = true; 
    }
    if (!data.display_name) { 
      showFieldError('display_name', t('roles.error_display_required', 'Display name is required')); 
      missing = true; 
    }
    
    if (missing) { 
      setStatus(t('roles.error_save', 'Validation error'), true); 
      return; 
    }

    setStatus(t('roles.processing', 'Processing...'));
    
    var method = id ? 'PUT' : 'POST';
    
    fetchJson(API, {
      method: method,
      body: data
    })
    .then(function (response) {
      if (response && response.success) {
        setStatus(t('roles.saved', 'Role saved successfully'));
        
        // Reset form and hide it
        if (form) form.reset();
        if (rolesId) rolesId.value = '';
        if (formWrap) formWrap.style.display = 'none';
        
        // Reload roles list
        setTimeout(loadList, 500);
      } else {
        setStatus(response.message || t('roles.error_save', 'Error saving'), true);
      }
    })
    .catch(function (err) { 
      console.error(err); 
      setStatus(err.message || t('roles.error_save', 'Error saving'), true); 
    });
  }

  function applySearch() {
    var q = searchInput && searchInput.value ? 
      String(searchInput.value).trim().toLowerCase() : '';
    
    if (!q) {
      filtered = roles.slice();
    } else {
      filtered = roles.filter(function (role) {
        if (!role) return false;
        if (String(role.id).indexOf(q) !== -1) return true;
        var keyText = (role.key_name || '').toLowerCase();
        var displayText = (role.display_name || '').toLowerCase();
        if (keyText.indexOf(q) !== -1) return true;
        if (displayText.indexOf(q) !== -1) return true;
        return false;
      });
    }
    
    currentPage = 1;
    renderTable();
    renderPager();
  }

  function initEventListeners() {
    if (refreshBtn) {
      refreshBtn.addEventListener('click', loadList);
    }
    
    if (newBtn) {
      newBtn.addEventListener('click', openNew);
    }
    
    if (rolesCancel) {
      rolesCancel.addEventListener('click', function () { 
        if (formWrap) {
          formWrap.style.display = 'none';
          form.reset();
        }
      });
    }
    
    if (form) {
      form.addEventListener('submit', saveRole);
    }
    
    if (searchInput) {
      var searchTimer = null;
      searchInput.addEventListener('input', function () {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(applySearch, 300);
      });
    }
  }

  function init() {
    if (DIRECTION === 'rtl') {
      try { 
        root.setAttribute('dir', 'rtl'); 
      } catch (e) {}
    }
    
    initEventListeners();
    loadList();
  }

  // Start when DOM is ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

  // Expose to global scope for debugging
  window._rolesAdmin = {
    reload: loadList,
    getCache: function() { return roles; },
    getFiltered: function() { return filtered; }
  };

})();