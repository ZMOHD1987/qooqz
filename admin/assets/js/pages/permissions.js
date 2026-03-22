/**
 * admin/assets/js/pages/permissions.js
 * Full CRUD UI for permissions list - Updated to use /api/permissions endpoint
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
  // Use /api/permissions endpoint
  var API = window.API_PERMISSIONS || '/api/permissions';

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
  
  function esc(s) { 
    return String(s == null ? '' : s)
      .replace(/&/g,'&amp;')
      .replace(/</g,'&lt;')
      .replace(/>/g,'&gt;')
      .replace(/"/g,'&quot;');
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

  // Load permissions list
  function loadPermissions() {
    setStatus(t('permissions.loading', 'Loading...'));
    
    fetchJson(API, { method: 'GET' })
      .then(function (data) {
        if (data && data.success && Array.isArray(data.data)) {
          cache = data.data;
        } else if (Array.isArray(data)) {
          cache = data;
        } else {
          cache = [];
        }
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

  // Apply search filter
  function applyFilter() {
    var q = (searchInput && searchInput.value) ? String(searchInput.value).trim().toLowerCase() : '';
    if (!q) {
      filtered = cache.slice();
    } else {
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

  // Render permissions table
  function renderTable() {
    if (!tableBody) return;
    tableBody.innerHTML = '';
    
    if (!filtered || filtered.length === 0) {
      var tr = document.createElement('tr');
      tr.innerHTML = '<td colspan="6" style="padding:12px;text-align:center;color:#666;">' + 
        esc(t('permissions.no_permissions', 'No permissions found')) + '</td>';
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
        actions = '<button class="btn editBtn" data-id="' + esc(p.id) + '">' + 
                  esc(t('permissions.btn_edit','Edit')) + '</button> ' +
                  '<button class="btn danger delBtn" data-id="' + esc(p.id) + '">' + 
                  esc(t('permissions.btn_delete','Delete')) + '</button>';
      }
      
      tr.innerHTML = '<td style="padding:10px;border-bottom:1px solid #eee;">' + esc(p.id) + '</td>' +
                     '<td style="padding:10px;border-bottom:1px solid #eee;"><strong>' + esc(p.key_name) + '</strong></td>' +
                     '<td style="padding:10px;border-bottom:1px solid #eee;">' + esc(p.display_name || '') + '</td>' +
                     '<td style="padding:10px;border-bottom:1px solid #eee;">' + esc(p.description || '') + '</td>' +
                     '<td style="padding:10px;border-bottom:1px solid #eee;">' + esc(formatDate(p.created_at)) + '</td>' +
                     '<td style="padding:10px;border-bottom:1px solid #eee;text-align:right;">' + actions + '</td>';
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
    
    var delBtns = tableBody.querySelectorAll('.delBtn');
    delBtns.forEach(function (b) {
      b.addEventListener('click', function () {
        var id = this.getAttribute('data-id');
        if (!CAN_MANAGE) { 
          alert(t('permissions.no_permission_notice','You do not have permission')); 
          return; 
        }
        if (!confirm(t('permissions.confirm_delete','Are you sure you want to delete this permission?'))) return;
        deletePermission(id);
      });
    });
  }

  // Render pagination
  function renderPager() {
    if (!pagerWrap) return;
    pagerWrap.innerHTML = '';
    
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
    pagerWrap.appendChild(perSel);

    // Info text
    var info = document.createElement('span');
    info.style.marginRight = '12px';
    var start = total === 0 ? 0 : (currentPage - 1) * perPage + 1;
    var end = Math.min(total, currentPage * perPage);
    info.textContent = total === 0 ? 
      t('permissions.no_permissions','No permissions') : 
      ('Showing ' + start + '-' + end + ' of ' + total);
    pagerWrap.appendChild(info);

    // Previous button
    var prev = document.createElement('button');
    prev.className = 'btn';
    prev.textContent = t('permissions.prev','Prev');
    prev.disabled = currentPage <= 1;
    prev.addEventListener('click', function () { 
      if (currentPage > 1) { 
        currentPage--; 
        renderTable(); 
        renderPager(); 
      } 
    });
    pagerWrap.appendChild(prev);

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
        pagerWrap.appendChild(b);
      })(p);
    }

    // Next button
    var next = document.createElement('button');
    next.className = 'btn';
    next.textContent = t('permissions.next','Next');
    next.disabled = currentPage >= totalPages;
    next.addEventListener('click', function () { 
      if (currentPage < totalPages) { 
        currentPage++; 
        renderTable(); 
        renderPager(); 
      } 
    });
    pagerWrap.appendChild(next);
  }

  // Open edit form
  function openEdit(id) {
    var found = cache.find(function (x) { return String(x.id) === String(id); });
    if (found) {
      populateForm(found);
      return;
    }
    
    setStatus(t('permissions.loading','Loading...'));
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
        setStatus(t('permissions.error_fetch','Error fetching data'), true); 
      });
  }

  // Populate form with permission data
  function populateForm(permission) {
    if (!formWrap) return;
    formWrap.style.display = 'block';
    
    if (permIdEl) permIdEl.value = permission.id || '';
    if (permKeyEl) permKeyEl.value = permission.key_name || '';
    if (permDisplayEl) permDisplayEl.value = permission.display_name || '';
    if (permDescEl) permDescEl.value = permission.description || '';
    
    var title = document.getElementById('permFormTitle');
    if (title) {
      title.textContent = permission.id ? 
        t('permissions.form_title_edit','Edit Permission') : 
        t('permissions.form_title_create','Create Permission');
    }
    
    if (permKeyEl) permKeyEl.focus();
    window.scrollTo({ top: formWrap.offsetTop - 20, behavior: 'smooth' });
  }

  // Delete permission
  function deletePermission(id) {
    if (!CAN_MANAGE) { 
      alert(t('permissions.no_permission_notice','You do not have permission')); 
      return; 
    }
    
    setStatus(t('permissions.processing','Processing...'));
    
    fetchJson(API, {
      method: 'DELETE',
      body: { id: id }
    })
    .then(function (data) {
      if (data && data.success) {
        setStatus(t('permissions.deleted_success','Permission deleted successfully'));
        cache = cache.filter(function (x) { return String(x.id) !== String(id); });
        applyFilter();
        
        // Hide form if editing the deleted item
        if (formWrap.style.display !== 'none' && permIdEl && permIdEl.value === String(id)) {
          formWrap.style.display = 'none';
        }
      } else {
        setStatus(data.message || t('permissions.error_delete','Delete failed'), true);
      }
    })
    .catch(function (err) { 
      console.error(err); 
      setStatus(err.message || t('permissions.error_delete','Error deleting'), true); 
    });
  }

  // Save permission (create or update)
  function saveFromForm(e) {
    if (e && e.preventDefault) e.preventDefault();
    if (!CAN_MANAGE) { 
      alert(t('permissions.no_permission_notice','You do not have permission')); 
      return; 
    }
    if (!permForm) return;
    
    var formData = new FormData(permForm);
    var data = {
      key_name: formData.get('key_name'),
      display_name: formData.get('display_name'),
      description: formData.get('description')
    };
    
    var id = formData.get('id');
    if (id) data.id = id;
    
    // Validate required fields
    if (!data.key_name || !data.display_name) {
      setStatus(t('permissions.error_required','Key and Display name are required'), true);
      return;
    }
    
    setStatus(t('permissions.processing','Processing...'));
    
    var method = id ? 'PUT' : 'POST';
    
    fetchJson(API, {
      method: method,
      body: data
    })
    .then(function (response) {
      if (response && response.success) {
        setStatus(t('permissions.saved_success','Permission saved successfully'));
        
        // Reset form and hide it
        if (permForm) permForm.reset();
        if (permIdEl) permIdEl.value = '';
        if (formWrap) formWrap.style.display = 'none';
        
        // Reload permissions list
        setTimeout(loadPermissions, 500);
      } else {
        setStatus(response.message || t('permissions.error_save','Error saving'), true);
      }
    })
    .catch(function (err) { 
      console.error(err); 
      setStatus(err.message || t('permissions.error_save','Error saving'), true); 
    });
  }

  // Initialize event listeners
  function initEventListeners() {
    if (searchInput) {
      var searchTimer = null;
      searchInput.addEventListener('input', function () {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(function () { 
          applyFilter(); 
        }, 300);
      });
    }
    
    if (refreshBtn) {
      refreshBtn.addEventListener('click', loadPermissions);
    }
    
    if (newBtn) {
      newBtn.addEventListener('click', function () {
        if (!CAN_MANAGE) { 
          alert(t('permissions.no_permission_notice','You do not have permission')); 
          return; 
        }
        
        if (formWrap) formWrap.style.display = 'block';
        if (permForm) permForm.reset();
        if (permIdEl) permIdEl.value = '';
        
        var title = document.getElementById('permFormTitle');
        if (title) {
          title.textContent = t('permissions.form_title_create','Create Permission');
        }
        
        if (permKeyEl) {
          permKeyEl.focus();
        }
        
        window.scrollTo({ top: formWrap.offsetTop - 20, behavior: 'smooth' });
      });
    }
    
    if (permCancelBtn) {
      permCancelBtn.addEventListener('click', function () { 
        if (formWrap) {
          formWrap.style.display = 'none';
          permForm.reset();
        }
      });
    }
    
    if (permForm) {
      permForm.addEventListener('submit', saveFromForm);
    }
  }

  // Initialize on DOM ready
  function init() {
    if (DIRECTION === 'rtl') {
      var container = document.getElementById('adminPermissions');
      if (container) container.setAttribute('dir', 'rtl');
    }
    
    initEventListeners();
    loadPermissions();
  }

  // Start when DOM is ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

  // Expose to global scope for debugging
  window._permissionsAdmin = {
    reload: loadPermissions,
    getCache: function() { return cache; },
    getFiltered: function() { return filtered; }
  };

})();