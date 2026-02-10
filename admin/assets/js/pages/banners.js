/*!
 * admin/assets/js/pages/banners.js
 * Robust Banner management client
 *
 * - Uses window.ADMIN_UI, window.I18N_FLAT, window.API_BANNERS, window.CSRF_TOKEN
 * - Handles missing endpoints gracefully (falls back to local cache)
 * - Tries multiple upload endpoints
 * - Defensive: no uncaught exceptions if DOM elements missing
 */

(function () {
  'use strict';

  // Config from server
  var ADMIN_UI = window.ADMIN_UI || {};
  var I18N_FLAT = window.I18N_FLAT || {};
  var USER_INFO = window.USER_INFO || ADMIN_UI.user || {};
  var THEME = window.THEME || ADMIN_UI.theme || {};
  var LANG = window.LANG || ADMIN_UI.lang || 'en';
  var DIRECTION = window.DIRECTION || ADMIN_UI.direction || 'ltr';
  var CSRF_TOKEN = window.CSRF_TOKEN || ADMIN_UI.csrf_token || '';
  var API = window.API_BANNERS || (ADMIN_UI.api && ADMIN_UI.api.banners) || '/api/banners';
  var UPLOAD_CANDIDATES = [
    (ADMIN_UI.api && ADMIN_UI.api.upload_image) || null,
    API + '/upload',
    '/api/upload_image.php'
  ].filter(Boolean);

  // Permission
  var CAN_MANAGE_BANNERS = !!(USER_INFO && Array.isArray(USER_INFO.permissions) && USER_INFO.permissions.indexOf('manage_banners') !== -1);

  // Minimal fallbacks (English)
  var FALLBACKS = {
    'banners.loading': 'Loading...',
    'banners.no_banners': 'No banners found',
    'banners.btn_edit': 'Edit',
    'banners.btn_delete': 'Delete',
    'banners.btn_toggle': 'Toggle',
    'banners.btn_save': 'Save',
    'banners.btn_new': 'Add Banner',
    'banners.btn_refresh': 'Refresh',
    'banners.btn_cancel': 'Cancel',
    'banners.yes': 'Yes',
    'banners.no': 'No',
    'banners.confirm_delete': 'Are you sure?',
    'banners.processing': 'Processing...',
    'banners.saved_success': 'Saved successfully',
    'banners.deleted_success': 'Deleted successfully',
    'banners.toggled_success': 'Toggled successfully',
    'banners.error_loading': 'Error loading banners',
    'banners.error_save': 'Error saving banner',
    'banners.error_delete': 'Error deleting banner',
    'banners.error_toggle': 'Error toggling banner',
    'banners.error_fetch': 'Error fetching data',
    'banners.uploading': 'Uploading...',
    'banners.uploaded': 'Uploaded',
    'banners.no_permission_notice': 'You do not have permission'
  };

  // Helpers
  function t(key) {
    if (!key) return '';
    if (I18N_FLAT && typeof I18N_FLAT[key] !== 'undefined' && I18N_FLAT[key] !== '') return I18N_FLAT[key];
    if (ADMIN_UI && ADMIN_UI.strings && typeof ADMIN_UI.strings[key] !== 'undefined' && ADMIN_UI.strings[key] !== '') return ADMIN_UI.strings[key];
    return FALLBACKS[key] || key.split('.').pop().replace(/_/g, ' ');
  }
  function qs(sel, ctx) { ctx = ctx || document; return ctx.querySelector(sel); }
  function qsa(sel, ctx) { ctx = ctx || document; return Array.prototype.slice.call(ctx.querySelectorAll(sel || '')); }
  function getEl(id) { return document.getElementById(id); }
  function esc(s) { return String(s == null ? '' : s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

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
  function postJson(url, body) {
    var opts = { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' }, body: JSON.stringify(body || {}) };
    return fetchJson(url, opts);
  }
  function postForm(url, fd) {
    var opts = { method: 'POST', credentials: 'same-origin', body: fd };
    return fetchJson(url, opts);
  }

  // UI Elements
  var tbody = getEl('bannersTbody');
  var countEl = getEl('bannersCount');
  var statusEl = getEl('bannersStatus');
  var searchEl = getEl('bannerSearch');
  var formWrap = getEl('bannerFormWrap');
  var bannerForm = getEl('bannerForm');
  var imgInput = getEl('banner_image_file');
  var mobInput = getEl('banner_mobile_image_file');

  var bannersCache = [];

  function setStatus(msg, isError) {
    if (!statusEl) return;
    statusEl.textContent = msg || '';
    statusEl.style.color = isError ? (THEME && THEME.colors_map && THEME.colors_map['error'] ? THEME.colors_map['error'] : '#b91c1c') : (THEME && THEME.colors_map && THEME.colors_map['primary'] ? THEME.colors_map['primary'] : '#064e3b');
  }

  function renderTable(rows) {
    if (!tbody) return;
    tbody.innerHTML = '';
    if (!rows || rows.length === 0) {
      tbody.innerHTML = '<tr><td colspan="6" style="padding:12px;text-align:center;color:#666;">' + esc(t('banners.no_banners')) + '</td></tr>';
      if (countEl) countEl.textContent = '0';
      return;
    }
    if (countEl) countEl.textContent = String(rows.length);
    rows.forEach(function (b) {
      var id = esc(b.id);
      var title = esc(b.title || '');
      var img = b.image_url ? '<img src="' + esc(b.image_url) + '" style="max-height:50px;border-radius:4px">' : '';
      var pos = esc(b.position || '');
      var activeLabel = b.is_active ? t('banners.yes') : t('banners.no');
      var actions = '';
      if (CAN_MANAGE_BANNERS) {
        actions += '<button class="btn editBtn" data-id="' + esc(b.id) + '">' + esc(t('banners.btn_edit')) + '</button> ';
        actions += '<button class="btn danger delBtn" data-id="' + esc(b.id) + '">' + esc(t('banners.btn_delete')) + '</button> ';
        actions += '<button class="btn toggleBtn" data-id="' + esc(b.id) + '" data-active="' + (b.is_active ? '1' : '0') + '">' + esc(t('banners.btn_toggle')) + '</button>';
      }
      var tr = document.createElement('tr');
      tr.innerHTML = '<td style="padding:8px;border-bottom:1px solid #eee;">' + id + '</td>'
        + '<td style="padding:8px;border-bottom:1px solid #eee;">' + title + '</td>'
        + '<td style="padding:8px;border-bottom:1px solid #eee;">' + img + '</td>'
        + '<td style="padding:8px;border-bottom:1px solid #eee;">' + pos + '</td>'
        + '<td style="padding:8px;border-bottom:1px solid #eee;text-align:center;">' + esc(activeLabel) + '</td>'
        + '<td style="padding:8px;border-bottom:1px solid #eee;">' + actions + '</td>';
      tbody.appendChild(tr);
    });

    // attach handlers
    qsa('.editBtn', tbody).forEach(function (btn) {
      btn.addEventListener('click', function () { openEdit(this.getAttribute('data-id')); });
    });
    qsa('.delBtn', tbody).forEach(function (btn) {
      btn.addEventListener('click', function () {
        var id = this.getAttribute('data-id');
        if (!CAN_MANAGE_BANNERS) { alert(t('banners.no_permission_notice')); return; }
        if (confirm(t('banners.confirm_delete'))) deleteBanner(id);
      });
    });
    qsa('.toggleBtn', tbody).forEach(function (btn) {
      btn.addEventListener('click', function () {
        var id = this.getAttribute('data-id');
        var cur = parseInt(this.getAttribute('data-active') || '0', 10);
        toggleActive(id, cur ? 0 : 1);
      });
    });
  }

  // Load list
  function loadBanners() {
    setStatus(t('banners.loading'));
    fetchJson(API + '?format=json', { method: 'GET' })
      .then(function (json) {
        var rows = [];
        if (!json) rows = [];
        else if (Array.isArray(json.data)) rows = json.data;
        else if (Array.isArray(json.rows)) rows = json.rows;
        else if (Array.isArray(json)) rows = json;
        else if (json.success && Array.isArray(json.data)) rows = json.data;
        bannersCache = rows || [];
        renderTable(bannersCache);
        setStatus('');
      })
      .catch(function (err) {
        console.warn('list error', err);
        // If 404 or Not Found, show empty list but keep page usable
        bannersCache = bannersCache || [];
        renderTable(bannersCache);
        setStatus((err && err.message) ? err.message : t('banners.error_loading'), true);
      });
  }

  // Open edit with robust fallbacks:
  // 1) Try server fetch with ?_fetch_row=1&id=
  // 2) If 404/Not Found, try RESTful GET /api/banners/{id}
  // 3) If still fails, try local bannersCache
  function openEdit(id) {
    setStatus(t('banners.loading'));
    fetchJson(API + '?_fetch_row=1&id=' + encodeURIComponent(id), { method: 'GET' })
      .then(function (json) {
        var row = (json && json.data) ? json.data : (json || {});
        populateForm(row);
        setStatus('');
      })
      .catch(function (err) {
        // If server not support, try RESTful endpoint
        fetchJson(API + '/' + encodeURIComponent(id), { method: 'GET' })
          .then(function (json2) {
            var row2 = (json2 && json2.data) ? json2.data : (json2 || {});
            populateForm(row2);
            setStatus('');
          })
          .catch(function (err2) {
            // fallback: find in local cache
            var local = bannersCache.find(function (b) { return String(b.id) === String(id); });
            if (local) {
              populateForm(local);
              setStatus('');
            } else {
              console.error('openEdit failed (server+local)', err, err2);
              setStatus(t('banners.error_fetch'), true);
            }
          });
      });
  }

  // Populate form from banner object
  function populateForm(b) {
    if (!formWrap) return;
    formWrap.style.display = 'block';
    safeSet('bannerId', b.id || '');
    safeSet('bannerTitle', b.title || '');
    safeSet('bannerImageUrl', b.image_url || '');
    safeSet('banner_mobile_image_url', b.mobile_image_url || '');
    safeSet('bannerPosition', b.position || '');
    safeSet('bannerIsActive', b.is_active ? 1 : 0);
    var prev = getEl('banner_image_preview'); if (prev) prev.innerHTML = b.image_url ? '<img src="' + esc(b.image_url) + '" style="max-height:80px">' : '';
    var mprev = getEl('banner_mobile_image_preview'); if (mprev) mprev.innerHTML = b.mobile_image_url ? '<img src="' + esc(b.mobile_image_url) + '" style="max-height:80px">' : '';
    var hidden = getEl('banner_translations'); if (hidden) hidden.value = JSON.stringify(b.translations || {});
  }

  // Toggle active
  function toggleActive(id, newState) {
    if (!CAN_MANAGE_BANNERS) { alert(t('banners.no_permission_notice')); return; }
    setStatus(t('banners.processing'));
    var payload = { action: 'toggle_active', id: id, is_active: newState ? 1 : 0 };
    if (CSRF_TOKEN) payload.csrf_token = CSRF_TOKEN;
    postJson(API, payload).then(function (res) {
      if (res && (res.success || res.ok)) {
        setStatus(res.message || t('banners.toggled_success'));
        bannersCache = bannersCache.map(function (b) { if (String(b.id) === String(id)) b.is_active = newState ? 1 : 0; return b; });
        renderTable(filterBanners(searchEl && searchEl.value ? searchEl.value : ''));
      } else {
        fallbackToggleSave(id, newState);
      }
    }).catch(function (err) {
      console.warn('toggle error', err);
      fallbackToggleSave(id, newState);
    });
  }

  function fallbackToggleSave(id, newState) {
    var b = bannersCache.find(function (x) { return String(x.id) === String(id); }) || { id: id, title: '' };
    var fd = new FormData();
    fd.append('action', 'save');
    fd.append('id', id);
    fd.append('title', b.title || '');
    fd.append('is_active', newState ? 1 : 0);
    if (CSRF_TOKEN) fd.append('csrf_token', CSRF_TOKEN);
    postForm(API, fd).then(function (res) {
      if (res && (res.success || res.ok)) {
        setStatus(res.message || t('banners.saved_success'));
        bannersCache = bannersCache.map(function (x) { if (String(x.id) === String(id)) x.is_active = newState ? 1 : 0; return x; });
        renderTable(filterBanners(searchEl && searchEl.value ? searchEl.value : ''));
      } else setStatus(res && res.message ? res.message : t('banners.error_save'), true);
    }).catch(function (err) { console.error(err); setStatus(t('banners.error_save'), true); });
  }

  // Delete
  function deleteBanner(id) {
    if (!CAN_MANAGE_BANNERS) { alert(t('banners.no_permission_notice')); return; }
    setStatus(t('banners.processing'));
    var payload = { action: 'delete', id: id };
    if (CSRF_TOKEN) payload.csrf_token = CSRF_TOKEN;
    postJson(API, payload).then(function (res) {
      if (res && (res.success || res.ok)) {
        setStatus(res.message || t('banners.deleted_success'));
        bannersCache = bannersCache.filter(function (b) { return String(b.id) !== String(id); });
        renderTable(filterBanners(searchEl && searchEl.value ? searchEl.value : ''));
      } else setStatus(res && res.message ? res.message : t('banners.error_delete'), true);
    }).catch(function (err) { console.error(err); setStatus(t('banners.error_delete'), true); });
  }

  // Save (create/update)
  function saveBannerFromForm() {
    if (!CAN_MANAGE_BANNERS) { alert(t('banners.no_permission_notice')); return; }
    if (!bannerForm) { setStatus('Form not found', true); return; }
    // collect translations if present
    var translationsHidden = getEl('banner_translations');
    if (translationsHidden) {
      try { translationsHidden.value = JSON.stringify(collectTranslationInputs()); } catch (e) { translationsHidden.value = '{}'; }
    }
    var fd = new FormData(bannerForm);
    fd.set('action', 'save');
    if (CSRF_TOKEN) fd.set('csrf_token', CSRF_TOKEN);
    setStatus(t('banners.processing'));
    postForm(API, fd).then(function (res) {
      if (res && (res.success || res.ok)) {
        setStatus(res.message || t('banners.saved_success'));
        if (formWrap) formWrap.style.display = 'none';
        setTimeout(loadBanners, 600);
      } else setStatus(res && res.message ? res.message : t('banners.error_save'), true);
    }).catch(function (err) { console.error(err); setStatus(t('banners.error_save'), true); });
  }

  // Upload: try multiple candidate endpoints sequentially
  function tryUpload(file, cb, progressCb) {
    if (!file) return cb({ message: 'No file' });
    var idx = 0;
    function attempt() {
      if (idx >= UPLOAD_CANDIDATES.length) return cb({ message: 'No upload endpoint available' });
      var url = UPLOAD_CANDIDATES[idx++];
      var xhr = new XMLHttpRequest();
      var fd = new FormData();
      fd.append('file', file);
      if (CSRF_TOKEN) fd.append('csrf_token', CSRF_TOKEN);
      xhr.open('POST', url, true);
      xhr.withCredentials = true;
      xhr.upload.onprogress = function (e) { if (e.lengthComputable && typeof progressCb === 'function') progressCb(Math.round(e.loaded / e.total * 100)); };
      xhr.onload = function () {
        if (xhr.status >= 200 && xhr.status < 300) {
          try {
            var j = JSON.parse(xhr.responseText || '{}');
            // accept common shapes
            var urlOut = (j && (j.url || (j.data && j.data.url) || (j.data && j.data.file && j.data.file.url))) || null;
            if (urlOut) return cb(null, urlOut, j);
            // else try next candidate
            attempt();
          } catch (e) { attempt(); }
        } else {
          attempt();
        }
      };
      xhr.onerror = function () { attempt(); };
      xhr.send(fd);
    }
    attempt();
  }

  // Wire file inputs
  if (imgInput) imgInput.addEventListener('change', function () {
    var f = this.files && this.files[0]; if (!f) return;
    var status = getEl('imageUploadStatus');
    if (status) status.textContent = t('banners.uploading');
    tryUpload(f, function (err, url) {
      if (err) { if (status) status.textContent = err.message || t('banners.error_save'); return; }
      var field = getEl('bannerImageUrl'); if (field) field.value = url;
      var prev = getEl('banner_image_preview'); if (prev) prev.innerHTML = '<img src="' + esc(url) + '" style="max-height:80px">';
      if (status) status.textContent = t('banners.uploaded');
    }, function (p) { var s = getEl('imageUploadStatus'); if (s) s.textContent = t('banners.uploading') + ' ' + p + '%'; });
  });

  if (mobInput) mobInput.addEventListener('change', function () {
    var f = this.files && this.files[0]; if (!f) return;
    var status = getEl('mobileImageUploadStatus');
    if (status) status.textContent = t('banners.uploading');
    tryUpload(f, function (err, url) {
      if (err) { if (status) status.textContent = err.message || t('banners.error_save'); return; }
      var field = getEl('banner_mobile_image_url'); if (field) field.value = url;
      var prev = getEl('banner_mobile_image_preview'); if (prev) prev.innerHTML = '<img src="' + esc(url) + '" style="max-height:80px">';
      if (status) status.textContent = t('banners.uploaded');
    }, function (p) { var s = getEl('mobileImageUploadStatus'); if (s) s.textContent = t('banners.uploading') + ' ' + p + '%'; });
  });

  // Collect translations inline table if exists
  function collectTranslationInputs() {
    var out = {};
    var rows = qsa('#translationsInlineTable tbody tr');
    rows.forEach(function (row) {
      var code = row.getAttribute('data-lang'); if (!code) return;
      var title = (qs('.tr-title[data-lang="' + code + '"]', row) || qs('.tr-title', row) || { value: '' }).value || '';
      var subtitle = (qs('.tr-subtitle[data-lang="' + code + '"]', row) || qs('.tr-subtitle', row) || { value: '' }).value || '';
      var link_text = (qs('.tr-linktext[data-lang="' + code + '"]', row) || qs('.tr-linktext', row) || { value: '' }).value || '';
      if (title || subtitle || link_text) out[code] = { title: title, subtitle: subtitle, link_text: link_text };
    });
    return out;
  }

  // Safe setter
  function safeSet(id, val) { var el = getEl(id); if (!el) return; try { if (el.type === 'checkbox') el.checked = !!val; else el.value = val === null || typeof val === 'undefined' ? '' : val; } catch (e) {} }

  // Bind UI events
  if (bannerForm) bannerForm.addEventListener('submit', function (e) { e.preventDefault(); saveBannerFromForm(); });
  var saveBtn = getEl('bannerSaveBtn'); if (saveBtn) saveBtn.addEventListener('click', function (e) { e.preventDefault(); saveBannerFromForm(); });

  var newBtn = getEl('btnNew') || getEl('bannerNewBtn');
  if (newBtn) newBtn.addEventListener('click', function () {
    if (!CAN_MANAGE_BANNERS) { alert(t('banners.no_permission_notice')); return; }
    if (formWrap) formWrap.style.display = 'block';
    safeSet('bannerId', '');
    if (bannerForm) bannerForm.reset();
    var hidden = getEl('banner_translations'); if (hidden) hidden.value = '{}';
  });

  var cancelBtn = getEl('btnCancelForm') || getEl('bannerCancelBtn');
  if (cancelBtn) cancelBtn.addEventListener('click', function () { if (formWrap) formWrap.style.display = 'none'; });

  // Search debounce
  var searchTimer = null;
  if (searchEl) searchEl.addEventListener('input', function () { clearTimeout(searchTimer); var q = String(this.value || ''); searchTimer = setTimeout(function () { renderTable(filterBanners(q)); }, 180); });

  function filterBanners(q) {
    if (!q) return bannersCache.slice();
    q = q.trim().toLowerCase();
    return bannersCache.filter(function (b) {
      if (String(b.id).indexOf(q) !== -1) return true;
      if ((b.title || '').toLowerCase().indexOf(q) !== -1) return true;
      if ((b.subtitle || '').toLowerCase().indexOf(q) !== -1) return true;
      if ((b.position || '').toLowerCase().indexOf(q) !== -1) return true;
      return false;
    });
  }

  var refreshBtn = getEl('btnRefresh') || getEl('bannerRefresh');
  if (refreshBtn) refreshBtn.addEventListener('click', loadBanners);

  // Initial
  document.addEventListener('DOMContentLoaded', function () {
    loadBanners();
    if (!CAN_MANAGE_BANNERS) {
      qsa('#bannerForm input, #bannerForm select, #bannerForm button, #banner_image_file, #banner_mobile_image_file, #bannerSaveBtn, #bannerDeleteBtn, #bannerNewBtn').forEach(function (el) { try { el.disabled = true; } catch (e) {} });
      var nb = getEl('bannerNewBtn'); if (nb) nb.style.display = 'none';
    }
    if (DIRECTION && DIRECTION === 'rtl') { var r = getEl('adminBanners'); if (r) r.setAttribute('dir','rtl'); }
  });

  // Expose debug
  window._bannersAdmin = { load: loadBanners, cache: function () { return bannersCache; }, api: API };

})();