(function () {
  'use strict';

  /* ================= Globals ================= */
  var ADMIN_UI = window.ADMIN_UI || {};
  var I18N = window.I18N_FLAT || {};
  var USER = window.USER_INFO || {};
  var THEME = window.THEME || {};
  var LANG = window.LANG || 'en';
  var DIRECTION = window.DIRECTION || 'ltr';
  var CSRF = window.CSRF_TOKEN || '';
  var API = window.LANGUAGES_CONFIG ? window.LANGUAGES_CONFIG.apiUrl : '/api/routes/languages.php';
  var PER_PAGE = window.LANGUAGES_CONFIG ? window.LANGUAGES_CONFIG.itemsPerPage : 25;
  var IS_EXTERNAL = window.IS_EXTERNAL || false;

  /* ================= Permissions ================= */
  var CAN_MANAGE = !IS_EXTERNAL && !!(
    (USER && Number(USER.role_id) === 1) ||
    (USER && Array.isArray(USER.roles) && USER.roles.indexOf('super_admin') !== -1) ||
    (USER && Array.isArray(USER.permissions) && USER.permissions.indexOf('manage_settings') !== -1)
  );

  /* ================= DOM ================= */
  var root = document.getElementById('adminLanguages');
  if (!root) return;

  var searchEl = document.getElementById('langSearch');
  var clearSearchBtn = document.getElementById('langClearSearch');
  var directionFilter = document.getElementById('langDirectionFilter');
  var resetFiltersBtn = document.getElementById('langResetFilters');
  var refreshBtn = document.getElementById('langRefresh');
  var newBtn = document.getElementById('langNew');
  var statusEl = document.getElementById('langNotification');
  var totalInfoEl = document.getElementById('langTotalInfo');
  var pageInfoEl = document.getElementById('langPageInfo');
  var pagerEl = document.getElementById('langPager');
  var prevBtn = document.getElementById('langPrev');
  var nextBtn = document.getElementById('langNext');
  var pageInput = document.getElementById('langPageInput');
  var totalPagesEl = document.getElementById('langTotalPages');
  var toastContainer = document.getElementById('toastContainer');
  var tableBody = document.getElementById('langTbody');

  var formWrap = document.getElementById('langFormWrap');
  var form = document.getElementById('langForm');
  var idEl = document.getElementById('langId');
  var codeEl = form.querySelector('[name="code"]');
  var nameEl = form.querySelector('[name="name"]');
  var dirEl = form.querySelector('[name="direction"]');
  var cancelBtn = document.getElementById('langCancel');
  var titleEl = document.getElementById('langFormTitle');

  /* ================= State ================= */
  var cache = [];
  var filteredCache = [];
  var currentPage = 1;
  var totalPages = 1;
  var searchTerm = '';
  var filters = { direction: '' };

  /* ================= Helpers ================= */
  function t(key, fallback) {
    if (I18N && I18N[key]) return I18N[key];
    return fallback || key.split('.').pop();
  }

  function esc(s) {
    return String(s == null ? '' : s)
      .replace(/&/g,'&amp;')
      .replace(/</g,'&lt;')
      .replace(/>/g,'&gt;')
      .replace(/"/g,'&quot;');
  }

  function setStatus(msg, isError) {
    if (!statusEl) return;
    statusEl.textContent = msg || '';
    statusEl.style.color = isError ? '#b91c1c' : '#065f46';
  }

  function showToast(msg, isError) {
    if (!toastContainer) return;
    var toast = document.createElement('div');
    toast.style.cssText = 'background:var(--toast-bg);color:var(--toast-color);padding:var(--toast-padding);border-radius:var(--toast-border-radius);margin-bottom:10px;opacity:0;transition:opacity 0.3s;';
    toast.textContent = msg;
    toastContainer.appendChild(toast);
    setTimeout(function () { toast.style.opacity = 1; }, 10);
    setTimeout(function () {
      toast.style.opacity = 0;
      setTimeout(function () { toast.remove(); }, 300);
    }, 3000);
  }

  function fetchJson(url, opts) {
    opts = opts || {};
    opts.credentials = 'same-origin';
    return fetch(url, opts).then(function (r) {
      if (!r.ok) throw new Error('HTTP ' + r.status);
      return r.json();
    });
  }

  function buildQueryParams() {
    var params = new URLSearchParams();
    params.append('page', currentPage);
    params.append('per_page', PER_PAGE);
    if (searchTerm) params.append('search', searchTerm);
    if (filters.direction) params.append('direction', filters.direction);
    return params.toString();
  }

  /* ================= Load ================= */
  function loadLanguages() {
    setStatus(t('languages.messages.loading', 'Loading...'));
    var query = buildQueryParams();
    fetchJson(API + '?' + query)
      .then(function (json) {
        cache = Array.isArray(json.data) ? json.data : (Array.isArray(json) ? json : []);
        filteredCache = cache;
        totalPages = json.total_pages || Math.ceil(cache.length / PER_PAGE);
        render();
        setStatus('');
      })
      .catch(function (err) {
        console.error(err);
        cache = [];
        filteredCache = [];
        render();
        setStatus(t('languages.messages.error_loading', 'Error loading languages'), true);
      });
  }

  /* ================= Filter & Search ================= */
  function applyFilters() {
    filteredCache = cache.filter(function (item) {
      if (searchTerm && !item.name.toLowerCase().includes(searchTerm.toLowerCase()) && !item.code.toLowerCase().includes(searchTerm.toLowerCase())) return false;
      if (filters.direction && item.direction !== filters.direction) return false;
      return true;
    });
    totalPages = Math.ceil(filteredCache.length / PER_PAGE);
    currentPage = Math.min(currentPage, totalPages) || 1;
    render();
  }

  /* ================= Render ================= */
  function render() {
    if (!tableBody) return;
    tableBody.innerHTML = '';

    if (!filteredCache.length) {
      tableBody.innerHTML =
        '<tr><td colspan="5" style="text-align:center;color:#666;">' +
        esc(t('languages.messages.no_data', 'No languages found')) +
        '</td></tr>';
      return;
    }

    var start = (currentPage - 1) * PER_PAGE;
    var pageItems = filteredCache.slice(start, start + PER_PAGE);

    pageItems.forEach(function (l) {
      var actions = '';
      if (CAN_MANAGE) {
        actions =
          '<button class="btn edit" data-id="' + esc(l.id) + '">' + esc(t('languages.buttons.edit', 'Edit')) + '</button> ' +
          '<button class="btn danger del" data-id="' + esc(l.id) + '">' + esc(t('languages.buttons.delete', 'Delete')) + '</button>';
      }

      var tr = document.createElement('tr');
      tr.innerHTML =
        '<td>' + esc(l.id) + '</td>' +
        '<td>' + esc(l.code) + '</td>' +
        '<td>' + esc(l.name) + '</td>' +
        '<td>' + esc((l.direction || '').toUpperCase()) + '</td>' +
        '<td style="text-align:right">' + actions + '</td>';

      tableBody.appendChild(tr);
    });

    bindRowActions();
    updatePagination();
    updateInfo();
  }

  function bindRowActions() {
    tableBody.querySelectorAll('.edit').forEach(function (b) {
      b.addEventListener('click', function () {
        openEdit(this.getAttribute('data-id'));
      });
    });

    tableBody.querySelectorAll('.del').forEach(function (b) {
      b.addEventListener('click', function () {
        var id = this.getAttribute('data-id');
        if (!confirm(t('languages.messages.confirm_delete', 'Are you sure?'))) return;
        deleteLanguage(id);
      });
    });
  }

  function updatePagination() {
    if (pageInput) pageInput.value = currentPage;
    if (totalPagesEl) totalPagesEl.textContent = 'of ' + totalPages;
    if (prevBtn) prevBtn.disabled = currentPage <= 1;
    if (nextBtn) nextBtn.disabled = currentPage >= totalPages;
  }

  function updateInfo() {
    if (totalInfoEl) totalInfoEl.textContent = t('pagination.total', 'Total: ') + filteredCache.length;
    if (pageInfoEl) pageInfoEl.textContent = t('pagination.page', 'Page ') + currentPage + ' of ' + totalPages;
  }

  /* ================= Form ================= */
  function openEdit(id) {
    var lang = cache.find(function (x) { return x.id == id; });
    if (!lang) return;

    formWrap.style.display = 'block';
    titleEl.textContent = t('languages.form.edit_title', 'Edit Language');

    idEl.value = lang.id;
    codeEl.value = lang.code;
    nameEl.value = lang.name;
    dirEl.value = lang.direction || 'ltr';
  }

  function openNew() {
    form.reset();
    idEl.value = '';
    titleEl.textContent = t('languages.form.add_title', 'Add Language');
    formWrap.style.display = 'block';
    codeEl.focus();
  }

  function saveLanguage(e) {
    e.preventDefault();
    if (!CAN_MANAGE) return;

    var fd = new FormData(form);
    fd.append('action', 'save');
    if (CSRF) fd.append('csrf_token', CSRF);

    setStatus(t('languages.messages.processing', 'Processing...'));
    fetchJson(API, { method: 'POST', body: fd })
      .then(function (j) {
        if (j.success) {
          setStatus(j.message || t('languages.messages.saved', 'Saved'));
          showToast(t('languages.messages.saved', 'Language saved successfully!'));
          formWrap.style.display = 'none';
          loadLanguages();
        } else {
          setStatus(j.message || t('languages.messages.error_save', 'Save failed'), true);
          showToast(j.message || t('languages.messages.error_save', 'Save failed'), true);
        }
      })
      .catch(function (err) {
        console.error(err);
        setStatus(t('languages.messages.error_save', 'Save failed'), true);
        showToast(t('languages.messages.error_save', 'Save failed'), true);
      });
  }

  function deleteLanguage(id) {
    if (!CAN_MANAGE) return;

    var fd = new FormData();
    fd.append('action', 'delete');
    fd.append('id', id);
    if (CSRF) fd.append('csrf_token', CSRF);

    setStatus(t('languages.messages.processing', 'Processing...'));
    fetchJson(API, { method: 'POST', body: fd })
      .then(function (j) {
        if (j.success) {
          setStatus(j.message || t('languages.messages.deleted', 'Deleted'));
          showToast(t('languages.messages.deleted', 'Language deleted successfully!'));
          loadLanguages();
        } else {
          setStatus(j.message || t('languages.messages.error_delete', 'Delete failed'), true);
          showToast(j.message || t('languages.messages.error_delete', 'Delete failed'), true);
        }
      })
      .catch(function (err) {
        console.error(err);
        setStatus(t('languages.messages.error_delete', 'Delete failed'), true);
        showToast(t('languages.messages.error_delete', 'Delete failed'), true);
      });
  }

  /* ================= Events ================= */
  if (searchEl) {
    searchEl.addEventListener('input', function () {
      searchTerm = this.value.trim();
      applyFilters();
    });
  }
  if (clearSearchBtn) clearSearchBtn.addEventListener('click', function () {
    searchEl.value = '';
    searchTerm = '';
    applyFilters();
  });
  if (directionFilter) directionFilter.addEventListener('change', function () {
    filters.direction = this.value;
    applyFilters();
  });
  if (resetFiltersBtn) resetFiltersBtn.addEventListener('click', function () {
    searchEl.value = '';
    searchTerm = '';
    directionFilter.value = '';
    filters.direction = '';
    applyFilters();
  });
  if (refreshBtn) refreshBtn.addEventListener('click', loadLanguages);
  if (newBtn && CAN_MANAGE) newBtn.addEventListener('click', function () {
    openNew();
    showToast(t('languages.messages.adding', 'Adding new language...'));
  });
  if (cancelBtn) cancelBtn.addEventListener('click', function () {
    formWrap.style.display = 'none';
  });
  if (form && CAN_MANAGE) form.addEventListener('submit', saveLanguage);
  if (prevBtn) prevBtn.addEventListener('click', function () {
    if (currentPage > 1) {
      currentPage--;
      render();
    }
  });
  if (nextBtn) nextBtn.addEventListener('click', function () {
    if (currentPage < totalPages) {
      currentPage++;
      render();
    }
  });
  if (pageInput) pageInput.addEventListener('change', function () {
    var page = parseInt(this.value);
    if (page >= 1 && page <= totalPages) {
      currentPage = page;
      render();
    } else {
      this.value = currentPage;
    }
  });

  document.addEventListener('DOMContentLoaded', function () {
    loadLanguages();
  });

  /* ================= Debug ================= */
  window._languagesAdmin = {
    reload: loadLanguages,
    cache: function () { return cache; }
  };

})();