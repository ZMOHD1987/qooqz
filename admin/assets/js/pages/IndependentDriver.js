/**
 * admin/assets/js/pages/IndependentDriver.js
 * Version: 2.0 - Fixed API endpoint (2026-01-02)
 * 
 * Final JS for Independent Drivers (RBAC-aware, responsive)
 * - Works with server-provided translations merged into window.ADMIN_UI.strings
 * - Prefers server helpers (window.Admin.fetchJson / window.Admin.can) when available
 * - Features: list, get, create, update, delete, upload previews, inline status change, debounced search
 * 
 * API ENDPOINT: /api/routes/independent_drivers.php (line 143)
 * If you see 404 errors for /api/independent-drivers, clear browser cache!
 *
 * Save as UTF-8 without BOM.
 */
(function () {
  'use strict';

  // mark loaded with version
  window.__IndependentDriverLoaded = true;
  window.__IndependentDriverVersion = '2.0-fixed';
  console.log('=== IndependentDriver.js v2.0 loaded ===');
  console.log('API endpoint will be: /api/routes/independent_drivers.php');

  /* ---------------------- Utilities ---------------------- */
  function safeJsonParse(txt) {
    try { return txt ? JSON.parse(txt) : null; } catch (e) { return null; }
  }

  function getNested(obj, path) {
    if (!obj || !path) return undefined;
    var parts = String(path).split('.');
    var cur = obj;
    for (var i = 0; i < parts.length; i++) {
      if (!cur || typeof cur !== 'object' || !(parts[i] in cur)) return undefined;
      cur = cur[parts[i]];
    }
    return cur;
  }

  function esc(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  /* ---------------------- Module initializer ---------------------- */
  function factoryInit(root) {
    root = root || document;
    var app = root.querySelector ? root.querySelector('#independent-driver-app') : null;
    if (!app) return;

    // Read config from fragment attributes and merged ADMIN_UI if present
    var attrPerms = (app.getAttribute('data-permissions') || '[]');
    var attrPermsArr = safeJsonParse(attrPerms) || [];
    var fragCfg = {
      userId: app.getAttribute('data-user-id') || null,
      roleId: app.getAttribute('data-role-id') || null,
      isAdmin: app.getAttribute('data-is-admin') === '1',
      permissions: Array.isArray(attrPermsArr) ? attrPermsArr : [],
      csrf: app.getAttribute('data-csrf') || ''
    };

    var ADMIN_UI = window.ADMIN_UI || {};
    var GLOBAL_USER = (ADMIN_UI && ADMIN_UI.user) || window.ADMIN_USER || null;

    // CFG priorities: ADMIN_UI.user -> fragment attrs -> fallback window.__IndependentDriverConfig
    var CFG = window.__IndependentDriverConfig ? Object.assign({}, window.__IndependentDriverConfig) : {};
    CFG = Object.assign({}, fragCfg, CFG);
    if (GLOBAL_USER && typeof GLOBAL_USER === 'object') {
      CFG.permissions = Array.isArray(GLOBAL_USER.permissions) ? GLOBAL_USER.permissions : (GLOBAL_USER.permissions ? [GLOBAL_USER.permissions] : []);
      CFG.userId = GLOBAL_USER.id || CFG.userId;
      CFG.roleId = GLOBAL_USER.role || CFG.roleId;
      CFG.isAdmin = (GLOBAL_USER.role === 1) || CFG.isAdmin;
      CFG.csrf = CFG.csrf || GLOBAL_USER.csrf || window.CSRF_TOKEN || '';
    } else {
      CFG.permissions = Array.isArray(CFG.permissions) ? CFG.permissions : [];
      CFG.csrf = CFG.csrf || window.CSRF_TOKEN || '';
    }

    // Permission helpers (prefer window.Admin.can / canAll)
    function can(p) {
      if (!p) return true;
      if (window.Admin && typeof window.Admin.isSuper === 'function' && window.Admin.isSuper()) return true;
      if (window.Admin && typeof window.Admin.can === 'function') return window.Admin.can(p);
      if (CFG.isAdmin) return true;
      if (p.indexOf('|') === -1) return CFG.permissions.indexOf(p) !== -1;
      return p.split('|').map(function (s) { return s.trim(); }).some(function (k) { return CFG.permissions.indexOf(k) !== -1; });
    }
    function canAll(p) {
      if (!p) return true;
      if (window.Admin && typeof window.Admin.canAll === 'function') return window.Admin.canAll(p);
      if (CFG.isAdmin) return true;
      var parts = Array.isArray(p) ? p : String(p).split('|').map(function (s) { return s.trim(); }).filter(Boolean);
      return parts.every(function (k) { return CFG.permissions.indexOf(k) !== -1; });
    }

    // Apply translations within container from window.ADMIN_UI.strings
    function applyTranslations(container) {
      container = container || document;
      var S = (window.ADMIN_UI && window.ADMIN_UI.strings) ? window.ADMIN_UI.strings : {};
      // data-i18n-key -> text
      Array.prototype.forEach.call(container.querySelectorAll('[data-i18n-key]'), function (el) {
        var key = el.getAttribute('data-i18n-key');
        var v = getNested(S, key);
        if (typeof v === 'string') el.textContent = v;
      });
      // data-i18n-placeholder -> placeholder
      Array.prototype.forEach.call(container.querySelectorAll('[data-i18n-placeholder]'), function (el) {
        var key = el.getAttribute('data-i18n-placeholder');
        var v = getNested(S, key);
        if (typeof v === 'string') el.placeholder = v;
      });
      // data-i18n-value -> value
      Array.prototype.forEach.call(container.querySelectorAll('[data-i18n-value]'), function (el) {
        var key = el.getAttribute('data-i18n-value');
        var v = getNested(S, key);
        if (typeof v === 'string') {
          if (el.tagName === 'INPUT' || el.tagName === 'BUTTON') el.value = v;
          else el.textContent = v;
        }
      });
    }

    // Reveal form fields hidden by permission attributes (server may set data-hide-without-perm etc.)
    function revealForm(container) {
      container = container || app;
      Array.prototype.forEach.call(container.querySelectorAll('[data-hide-without-perm],[data-require-perm],[data-remove-without-perm]'), function (el) {
        try {
          el.removeAttribute('data-hide-without-perm');
          el.removeAttribute('data-require-perm');
          el.removeAttribute('data-remove-without-perm');
          el.style.display = '';
          el.style.visibility = '';
          el.removeAttribute('aria-hidden');
        } catch (e) { /* ignore */ }
      });
      Array.prototype.forEach.call(container.querySelectorAll('input,select,textarea,button'), function (el) {
        try { el.disabled = false; el.hidden = false; el.style.display = ''; el.style.visibility = ''; el.removeAttribute('aria-hidden'); } catch (e) { }
      });
    }

    // DOM helpers
    function $(sel, ctx) { ctx = ctx || app; return ctx.querySelector(sel); }
    function $all(sel, ctx) { ctx = ctx || app; return Array.prototype.slice.call(ctx.querySelectorAll(sel)); }

    /* ---------------------- Elements & state ---------------------- */
    var API = '/api/routes/independent_drivers.php';
    console.log('IndependentDriver API endpoint:', API);
    
    var tableBody = $('#idrv-table tbody') || (app.querySelector('#idrv-table') && app.querySelector('#idrv-table').tBodies[0]);
    var searchInput = $('#idrv-search');
    var filterStatus = $('#idrv-filter-status');
    var filterVehicle = $('#idrv-filter-vehicle');
    var createBtn = $('#idrv-create-btn');

    var form = $('#idrv-form');
    var saveBtn = $('#idrv-save');
    var resetBtn = $('#idrv-reset');
    var msgEl = $('#idrv-form-message');

    var licenseInput = $('#idrv-license_photo');
    var idInput = $('#idrv-id_photo');
    var licensePreview = $('#idrv-license-preview');
    var idPreview = $('#idrv-id-preview');

    var CURRENT_USER_ID = CFG.userId ? String(CFG.userId) : null;
    var IS_ADMIN = !!CFG.isAdmin;

    function setMessage(txt, type) {
      if (!msgEl) return;
      msgEl.textContent = txt || '';
      if (type === 'error') msgEl.style.color = 'crimson';
      else msgEl.style.color = '';
    }
    function clearMessage() { setMessage(''); }

    // network helper: prefer Admin.fetchJson
    async function fetchJson(url, opts) {
      opts = opts || {};
      if (window.Admin && typeof window.Admin.fetchJson === 'function') {
        try { return await window.Admin.fetchJson(url, opts); } catch (e) { /* fallback */ }
      }
      opts.credentials = opts.credentials || 'include';
      var res = await fetch(url, opts);
      var txt = await res.text();
      var data = safeJsonParse(txt);
      return { ok: res.ok, status: res.status, data: data, raw: txt };
    }

    // debounce utility
    var searchTimer = null;
    function debounce(fn, wait) {
      return function () {
        var args = arguments;
        if (searchTimer) clearTimeout(searchTimer);
        searchTimer = setTimeout(function () { fn.apply(null, args); }, wait || 300);
      };
    }

    /* ---------------------- Table / CRUD ---------------------- */
    async function fetchList() {
      setMessage(getNested(window.ADMIN_UI, 'strings.page.loading') || 'Loading...');
      var params = new URLSearchParams();
      params.set('action', 'list');
      var q = (searchInput && searchInput.value.trim()) || '';
      if (q) params.set('q', q);
      var st = (filterStatus && filterStatus.value) || '';
      if (st) params.set('status', st);
      var vt = (filterVehicle && filterVehicle.value) || '';
      if (vt) params.set('vehicle_type', vt);
      try {
        var r = await fetchJson(API + '?' + params.toString());
        if (!r.ok) { setMessage('Load failed: ' + r.status, 'error'); return; }
        var json = r.data || {};
        var rows = json.data || (Array.isArray(json) ? json : []);
        renderTable(rows);
        clearMessage();
      } catch (err) {
        console.error('fetchList error', err);
        setMessage(getNested(window.ADMIN_UI, 'strings.page.load_error') || 'Failed to load list', 'error');
      }
    }

    function renderTable(rows) {
      if (!tableBody) return;
      tableBody.innerHTML = '';
      if (!rows || rows.length === 0) {
        tableBody.innerHTML = '<tr><td colspan="8" style="text-align:center;color:#666">' + (getNested(window.ADMIN_UI, 'strings.page.no_results') || 'No drivers found') + '</td></tr>';
        return;
      }
      rows.forEach(function (r) {
        var tr = document.createElement('tr');

        var statusCell = IS_ADMIN ? ('<select class="inline-status" data-id="' + esc(r.id) + '">' +
          '<option value="active"' + (r.status === 'active' ? ' selected' : '') + '>' + (getNested(window.ADMIN_UI, 'strings.page.active') || 'Active') + '</option>' +
          '<option value="inactive"' + (r.status === 'inactive' ? ' selected' : '') + '>' + (getNested(window.ADMIN_UI, 'strings.page.inactive') || 'Inactive') + '</option>' +
          '<option value="busy"' + (r.status === 'busy' ? ' selected' : '') + '>' + (getNested(window.ADMIN_UI, 'strings.page.busy') || 'Busy') + '</option>' +
          '<option value="offline"' + (r.status === 'offline' ? ' selected' : '') + '>' + (getNested(window.ADMIN_UI, 'strings.page.offline') || 'Offline') + '</option>' +
          '</select>') : esc(r.status || '');

        var licenseThumb = r.license_photo_url ? ('<img src="' + esc(r.license_photo_url) + '" style="max-width:60px;max-height:40px;object-fit:cover;border-radius:4px;border:1px solid #ddd">') : '';
        var idThumb = r.id_photo_url ? ('<img src="' + esc(r.id_photo_url) + '" style="max-width:60px;max-height:40px;object-fit:cover;border-radius:4px;border:1px solid #ddd">') : '';

        var canEditRecord = IS_ADMIN || (String(r.user_id) === String(CURRENT_USER_ID) && can('edit_drivers'));
        var canDeleteRecord = IS_ADMIN || (String(r.user_id) === String(CURRENT_USER_ID) && can('delete_drivers'));

        var actions = [];
        actions.push('<button class="btn idrv-edit" data-id="' + esc(r.id) + '">' + (getNested(window.ADMIN_UI, 'strings.page.edit') || 'Edit') + '</button>');
        if (canDeleteRecord) actions.push('<button class="btn idrv-delete" data-id="' + esc(r.id) + '">' + (getNested(window.ADMIN_UI, 'strings.page.delete') || 'Delete') + '</button>');
        if (licenseThumb) actions.push('<button class="btn idrv-view-photo" data-url="' + esc(r.license_photo_url) + '">📷</button>');
        if (idThumb) actions.push('<button class="btn idrv-view-photo" data-url="' + esc(r.id_photo_url) + '">🪪</button>');

        tr.innerHTML = '' +
          '<td>' + esc(r.id) + '</td>' +
          '<td>' + esc(r.name || r.full_name || '') + '</td>' +
          '<td>' + esc(r.phone || '') + '</td>' +
          '<td>' + esc(r.vehicle_number || '') + '</td>' +
          '<td>' + esc(r.vehicle_type || '') + '</td>' +
          '<td>' + esc(r.license_number || '') + '</td>' +
          '<td>' + statusCell + '</td>' +
          '<td><div style="display:flex;gap:6px;flex-wrap:wrap">' + actions.join('') + '</div></td>';
        tableBody.appendChild(tr);
      });
    }

    // inline status change (delegated)
    tableBody && tableBody.addEventListener('change', async function (e) {
      var sel = e.target.closest && e.target.closest('.inline-status');
      if (!sel) return;
      var id = sel.dataset.id;
      var status = sel.value;
      try {
        var fd = new FormData();
        fd.append('id', id);
        fd.append('status', status);
        if (CFG.csrf) fd.append('csrf_token', CFG.csrf);
        var res = await fetchJson(API + '?action=update', { method: 'POST', body: fd });
        if (!res.ok || !(res.data && res.data.success)) {
          alert((res.data && res.data.message) ? res.data.message : (getNested(window.ADMIN_UI, 'strings.page.update_failed') || 'Update failed'));
          fetchList();
        }
      } catch (err) {
        console.error('status update error', err);
        alert(getNested(window.ADMIN_UI, 'strings.page.update_failed') || 'Update failed');
      }
    });

    // delegated clicks in table
    tableBody && tableBody.addEventListener('click', function (e) {
      var editBtn = e.target.closest && e.target.closest('.idrv-edit');
      var delBtn = e.target.closest && e.target.closest('.idrv-delete');
      var viewBtn = e.target.closest && e.target.closest('.idrv-view-photo');
      if (editBtn) {
        var id = editBtn.dataset.id;
        loadForEdit(id);
      } else if (delBtn) {
        var idd = delBtn.dataset.id;
        if (!confirm(getNested(window.ADMIN_UI, 'strings.page.confirm_delete') || 'Are you sure?')) return;
        deleteDriver(idd).then(fetchList);
      } else if (viewBtn) {
        var url = viewBtn.dataset.url;
        if (url) window.open(url, '_blank');
      }
    });

    /* ---------------------- File previews & local controls ---------------------- */
    function previewFile(inputEl, previewEl) {
      if (!inputEl || !previewEl) return;
      previewEl.innerHTML = '';
      var f = inputEl.files && inputEl.files[0];
      if (!f) return;
      if (f.size > (5 * 1024 * 1024)) { setMessage(getNested(window.ADMIN_UI, 'strings.page.file_too_large') || 'File too large (max 5MB)', 'error'); inputEl.value = ''; return; }
      var reader = new FileReader();
      reader.onload = function (ev) {
        var img = document.createElement('img');
        img.src = ev.target.result;
        img.style.maxWidth = '140px';
        img.style.maxHeight = '110px';
        img.style.objectFit = 'cover';
        img.style.border = '1px solid #ddd';
        img.style.borderRadius = '6px';
        previewEl.appendChild(img);
        var btn = document.createElement('button'); btn.type = 'button'; btn.className = 'btn idrv-remove-local-photo'; btn.textContent = getNested(window.ADMIN_UI, 'strings.page.remove') || 'Remove';
        btn.addEventListener('click', function () { inputEl.value = ''; previewEl.innerHTML = ''; });
        previewEl.appendChild(btn);
      };
      reader.readAsDataURL(f);
    }
    licenseInput && licenseInput.addEventListener('change', function () { previewFile(licenseInput, licensePreview); });
    idInput && idInput.addEventListener('change', function () { previewFile(idInput, idPreview); });

    // server-photo removal (adds hidden input)
    app.addEventListener('click', function (e) {
      var del = e.target.closest && e.target.closest('.idrv-del-photo');
      if (!del) return;
      var field = del.dataset.field;
      if (!field) return;
      var hid = form.querySelector('input[name="' + field + '"]');
      if (!hid) {
        hid = document.createElement('input'); hid.type = 'hidden'; hid.name = field; hid.value = '1';
        form.appendChild(hid);
      } else hid.value = '1';
      var parent = del.closest('.idrv-preview-item');
      if (parent) parent.remove();
    });

    /* ---------------------- Form actions ---------------------- */
    createBtn && createBtn.addEventListener('click', function () {
      try { form.reset(); licensePreview.innerHTML = ''; idPreview.innerHTML = ''; $all('input[name^="delete_"]', form).forEach(function (i) { i.remove(); }); clearMessage(); } catch (e) { }
    });
    resetBtn && resetBtn.addEventListener('click', function () { try { form.reset(); licensePreview.innerHTML = ''; idPreview.innerHTML = ''; $all('input[name^="delete_"]', form).forEach(function (i) { i.remove(); }); clearMessage(); } catch (e) { } });

    saveBtn && saveBtn.addEventListener('click', async function () {
      clearMessage();
      var name = ($('#idrv-name', form) && $('#idrv-name', form).value.trim()) || '';
      var phone = ($('#idrv-phone', form) && $('#idrv-phone', form).value.trim()) || '';
      var license_num = ($('#idrv-license_number', form) && $('#idrv-license_number', form).value.trim()) || '';
      var vehicle_type = ($('#idrv-vehicle_type', form) && $('#idrv-vehicle_type', form).value) || '';

      if (!name) return setMessage(getNested(window.ADMIN_UI, 'strings.page.validation.name_required') || 'Name is required', 'error');
      if (!phone) return setMessage(getNested(window.ADMIN_UI, 'strings.page.validation.phone_required') || 'Phone is required', 'error');
      if (!vehicle_type) return setMessage(getNested(window.ADMIN_UI, 'strings.page.validation.vehicle_required') || 'Vehicle type is required', 'error');
      if (!license_num) return setMessage(getNested(window.ADMIN_UI, 'strings.page.validation.license_required') || 'License number is required', 'error');

      var id = ($('#idrv-id', form) && $('#idrv-id', form).value) || '';
      var action = id ? 'update' : 'create';
      var fd = new FormData(form);
      if (CFG.csrf) fd.set('csrf_token', CFG.csrf);

      setMessage(getNested(window.ADMIN_UI, 'strings.page.saving') || 'Saving...');
      try {
        var res = await fetchJson(API + '?action=' + action, { method: 'POST', body: fd });
        if (!res.ok) { setMessage('Save failed: ' + res.status, 'error'); return; }
        var json = res.data || {};
        if (json && json.success) {
          setMessage(json.message || (getNested(window.ADMIN_UI, 'strings.page.saved') || 'Saved'));
          form.reset();
          licensePreview.innerHTML = ''; idPreview.innerHTML = ''; $all('input[name^="delete_"]', form).forEach(function (i) { i.remove(); });
          fetchList();
        } else {
          setMessage((json && json.message) ? json.message : (getNested(window.ADMIN_UI, 'strings.page.save_failed') || 'Save failed'), 'error');
          console.error('Save response', json);
        }
      } catch (err) {
        console.error('save error', err);
        setMessage(getNested(window.ADMIN_UI, 'strings.page.save_failed') || 'Save failed (see console)', 'error');
      }
    });

    // load for edit
    async function loadForEdit(id) {
      setMessage(getNested(window.ADMIN_UI, 'strings.page.loading') || 'Loading...');
      try {
        var res = await fetchJson(API + '?action=get&id=' + encodeURIComponent(id));
        if (!res.ok) { setMessage('Load failed: ' + res.status, 'error'); return; }
        var json = res.data || {};
        if (!(json && json.success && json.data)) { setMessage(getNested(window.ADMIN_UI, 'strings.page.record_load_failed') || 'Failed to load record', 'error'); return; }
        var d = json.data;
        try {
          $('#idrv-id', form).value = d.id || '';
          $('#idrv-name', form).value = d.name || d.full_name || '';
          $('#idrv-phone', form).value = d.phone || '';
          $('#idrv-email', form).value = d.email || '';
          $('#idrv-vehicle_type', form).value = d.vehicle_type || '';
          $('#idrv-vehicle_number', form).value = d.vehicle_number || '';
          $('#idrv-license_number', form).value = d.license_number || '';
          $('#idrv-status', form).value = d.status || 'active';
        } catch (e) { /* ignore individual assignments */ }

        licensePreview.innerHTML = ''; idPreview.innerHTML = '';
        if (d.license_photo_url) {
          var w = document.createElement('div'); w.className = 'idrv-preview-item';
          w.innerHTML = '<img src="' + esc(d.license_photo_url) + '" style="max-width:140px;max-height:110px;object-fit:cover;border:1px solid #ddd;border-radius:6px">';
          if (can('manage_driver_docs') || IS_ADMIN || String(d.user_id) === String(CURRENT_USER_ID)) {
            var btn = document.createElement('button'); btn.type = 'button'; btn.className = 'btn idrv-del-photo'; btn.dataset.field = 'delete_license'; btn.textContent = getNested(window.ADMIN_UI, 'strings.page.remove') || 'Remove';
            w.appendChild(btn);
          }
          licensePreview.appendChild(w);
        }
        if (d.id_photo_url) {
          var w2 = document.createElement('div'); w2.className = 'idrv-preview-item';
          w2.innerHTML = '<img src="' + esc(d.id_photo_url) + '" style="max-width:140px;max-height:110px;object-fit:cover;border:1px solid #ddd;border-radius:6px">';
          if (can('manage_driver_docs') || IS_ADMIN || String(d.user_id) === String(CURRENT_USER_ID)) {
            var btn2 = document.createElement('button'); btn2.type = 'button'; btn2.className = 'btn idrv-del-photo'; btn2.dataset.field = 'delete_id'; btn2.textContent = getNested(window.ADMIN_UI, 'strings.page.remove') || 'Remove';
            w2.appendChild(btn2);
          }
          idPreview.appendChild(w2);
        }
        clearMessage();
      } catch (err) {
        console.error('loadForEdit error', err);
        setMessage(getNested(window.ADMIN_UI, 'strings.page.record_load_failed') || 'Load failed', 'error');
      }
    }

    // delete driver
    async function deleteDriver(id) {
      try {
        var fd = new FormData(); fd.append('id', id);
        if (CFG.csrf) fd.append('csrf_token', CFG.csrf);
        var res = await fetchJson(API + '?action=delete', { method: 'POST', body: fd });
        if (!res.ok) { alert(getNested(window.ADMIN_UI, 'strings.page.delete_failed') || 'Delete failed: ' + res.status); return; }
        var json = res.data || {};
        if (!(json && json.success)) alert((json && json.message) ? json.message : (getNested(window.ADMIN_UI, 'strings.page.delete_failed') || 'Delete failed'));
      } catch (err) {
        console.error('deleteDriver error', err);
        alert(getNested(window.ADMIN_UI, 'strings.page.delete_failed') || 'Delete failed');
      }
    }

    // wire search/filter events (debounced)
    if (searchInput) searchInput.addEventListener('input', debounce(fetchList, 400));
    if (filterStatus) filterStatus.addEventListener('change', fetchList);
    if (filterVehicle) filterVehicle.addEventListener('change', fetchList);

    // init
    (function initFragment() {
      // apply server translations and perms to container
      try {
        applyTranslations(app);
      } catch (e) { console.warn('applyTranslations failed', e); }
      try {
        if (window.Admin && typeof window.Admin.applyPermsToContainer === 'function') window.Admin.applyPermsToContainer(app);
      } catch (e) { /* ignore */ }

      // reveal form fields if server intended them visible
      revealForm(app);

      // initial load
      fetchList();
    })();
  } // end factoryInit

  // Register module if Admin.page exists; otherwise run on DOMContentLoaded
  if (window.Admin && window.Admin.page && typeof window.Admin.page.register === 'function') {
    window.Admin.page.register('independent_driver', function (ctx) {
      var root = (ctx && ctx.meta && ctx.meta.ownerDocument) ? ctx.meta.ownerDocument : document;
      factoryInit(root);
    });
  } else {
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', function () { factoryInit(document); });
    else factoryInit(document);
  }

})();