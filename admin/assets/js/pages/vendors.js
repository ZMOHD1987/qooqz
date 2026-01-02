/**
 * admin/assets/js/pages/vendors.js
 * Theme-integrated Vendors admin UI script
 * 
 * Integration with bootstrap_admin_ui.php theme system:
 * - Uses window.ADMIN_UI for theme data (colors, buttons, settings)
 * - RBAC via window.ADMIN_UI.user.permissions
 * - i18n via window.ADMIN_UI.strings
 * - Themed buttons via window.ADMIN_UI.theme.buttons_map
 */

(function () {
  'use strict';

  // ---------- Configuration ----------
  const API = '/api/vendors.php';
  const COUNTRIES_API = '/api/helpers/countries.php';
  const CITIES_API = '/api/helpers/cities.php';
  const RETRY_ON_403 = true;
  const DEBUG = false;
  const IMAGE_PROCESSING = false;

  // Image processing specs (if IMAGE_PROCESSING true)
  const IMAGE_SPECS = {
    logo: { w: 600, h: 600, quality: 0.85 },
    cover: { w: 1400, h: 787, quality: 0.86 },
    banner: { w: 1600, h: 400, quality: 0.86 }
  };

  // ---------- Runtime state from window.ADMIN_UI (bootstrap_admin_ui.php) ----------
  const ADMIN_UI = window.ADMIN_UI || {};
  let CSRF = ADMIN_UI.csrf_token || window.CSRF_TOKEN || '';
  let CURRENT = ADMIN_UI.user || window.CURRENT_USER || {};
  const THEME = ADMIN_UI.theme || {};
  const STRINGS = ADMIN_UI.strings || {};
  const LANGS = window.AVAILABLE_LANGUAGES || [{ code: 'en', name: 'English', strings: {} }];
  const PREF_LANG = ADMIN_UI.lang || window.ADMIN_LANG || (CURRENT.preferred_language || 'en');
  const LANG_DIRECTION = ADMIN_UI.direction || window.LANG_DIRECTION || 'ltr';
  const IS_ADMIN = !!(CURRENT.role_id && Number(CURRENT.role_id) === 1);

  // ---------- Helpers ----------
  const $ = id => document.getElementById(id);
  const log = (...args) => { if (DEBUG) console.log('[vendors.js]', ...args); };
  function escapeHtml(s) { return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
  
  // i18n helper using ADMIN_UI.strings
  function t(key, fallback = '') {
    return STRINGS[key] || fallback || key;
  }

  // ---------- DOM refs ----------
  const refs = {
    tbody: $('vendorsTbody'),
    vendorsCount: $('vendorsCount'),
    vendorSearch: $('vendorSearch'),
    vendorRefresh: $('vendorRefresh'),
    vendorNewBtn: $('vendorNewBtn'),

    // فلاتر البحث المتقدمة
    filterStatus: $('filterStatus'),
    filterVerified: $('filterVerified'),
    filterCountry: $('filterCountry'),
    filterCity: $('filterCity'),
    filterPhone: $('filterPhone'),
    filterEmail: $('filterEmail'),
    filterClear: $('filterClear'),

    formSection: $('vendorFormSection'),
    form: $('vendorForm'),
    formTitle: $('vendorFormTitle'),
    saveBtn: $('vendorSaveBtn'),
    resetBtn: $('vendorResetBtn'),
    errorsBox: $('vendorFormErrors'),

    translationsArea: $('vendor_translations_area'),
    addLangBtn: $('vendorAddLangBtn'),

    parentWrap: $('parentVendorWrap'),

    previewLogo: $('preview_logo'),
    previewCover: $('preview_cover'),
    previewBanner: $('preview_banner'),

    logoInput: $('vendor_logo'),
    coverInput: $('vendor_cover'),
    bannerInput: $('vendor_banner'),

    btnGetCoords: $('btnGetCoords'),
    
    // حقل is_branch لعرض/إخفاء parent vendor
    isBranchCheckbox: $('vendor_is_branch')
  };

  // Fill missing refs with null to avoid undefined access
  Object.keys(refs).forEach(k => { if (!refs[k]) refs[k] = null; });

  // ---------- نظام الإشعارات في أعلى الصفحة ----------
  function showNotification(message, type = 'info', duration = 5000) {
    // إنشاء عنصر الإشعار إذا لم يكن موجوداً
    let notificationArea = $('#notificationArea');
    if (!notificationArea) {
      notificationArea = document.createElement('div');
      notificationArea.id = 'notificationArea';
      notificationArea.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 9999;
        max-width: 400px;
      `;
      document.body.appendChild(notificationArea);
    }

    // إنشاء الإشعار
    const notification = document.createElement('div');
    notification.className = 'notification';
    notification.style.cssText = `
      padding: 12px 20px;
      margin-bottom: 10px;
      border-radius: 8px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.15);
      color: white;
      font-family: system-ui, -apple-system, sans-serif;
      font-size: 14px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      animation: slideIn 0.3s ease;
      min-width: 300px;
      max-width: 400px;
      word-break: break-word;
    `;

    // تحديد لون حسب النوع
    const colors = {
      success: '#10b981',
      error: '#ef4444',
      warning: '#f59e0b',
      info: '#3b82f6'
    };
    notification.style.backgroundColor = colors[type] || colors.info;

    // محتوى الإشعار
    notification.innerHTML = `
      <div style="flex: 1; margin-right: 10px;">
        ${escapeHtml(message)}
      </div>
      <button class="notification-close" style="background: none; border: none; color: white; font-size: 18px; cursor: pointer; padding: 0; margin-left: 10px;">
        ×
      </button>
    `;

    // إضافة الإشعار إلى المنطقة
    notificationArea.appendChild(notification);

    // زر الإغلاق
    notification.querySelector('.notification-close').addEventListener('click', () => {
      notification.style.animation = 'slideOut 0.3s ease';
      setTimeout(() => notification.remove(), 300);
    });

    // إزالة تلقائية بعد المدة
    if (duration > 0) {
      setTimeout(() => {
        if (notification.parentNode) {
          notification.style.animation = 'slideOut 0.3s ease';
          setTimeout(() => notification.remove(), 300);
        }
      }, duration);
    }

    // إضافة أنيميشن CSS إذا لم تكن موجودة
    if (!document.querySelector('#notification-styles')) {
      const style = document.createElement('style');
      style.id = 'notification-styles';
      style.textContent = `
        @keyframes slideIn {
          from {
            transform: translateX(100%);
            opacity: 0;
          }
          to {
            transform: translateX(0);
            opacity: 1;
          }
        }
        @keyframes slideOut {
          from {
            transform: translateX(0);
            opacity: 1;
          }
          to {
            transform: translateX(100%);
            opacity: 0;
          }
        }
      `;
      document.head.appendChild(style);
    }

    return notification;
  }

  // ---------- Network helpers ----------
  async function fetchJson(url, opts = {}) {
    opts.credentials = 'include';
    const res = await fetch(url, opts);
    let body = null;
    try { body = await res.json(); } catch (e) { body = null; }
    if (!res.ok) throw { status: res.status, body };
    return body;
  }

  async function fetchCurrentUserAndCsrf() {
    try {
      const j = await fetchJson(`${API}?action=current_user`);
      CSRF = j.csrf_token || j.csrf || CSRF;
      CURRENT = j.user || CURRENT;
      window.CSRF_TOKEN = CSRF;
      window.CURRENT_USER = CURRENT;
      log('fetched user+csrf', { csrf: !!CSRF, userId: CURRENT?.id });
      return { csrf: CSRF, user: CURRENT };
    } catch (err) {
      log('fetchCurrentUserAndCsrf failed', err);
      return { csrf: CSRF, user: CURRENT };
    }
  }

  async function postFormData(fd) {
    if (!fd) fd = new FormData();
    await fetchCurrentUserAndCsrf();
    fd.set('csrf_token', CSRF || '');
    const headers = { 'X-CSRF-Token': CSRF || '' };
    const res = await fetch(API, { method: 'POST', body: fd, credentials: 'include', headers });
    let json = null;
    try { json = await res.json(); } catch (e) { json = null; }
    if (!res.ok) throw { status: res.status, body: json };
    return json;
  }

  async function postWithRetry(fd) {
    try {
      return await postFormData(fd);
    } catch (err) {
      if (RETRY_ON_403 && err && err.status === 403) {
        log('403 detected - refresh CSRF and retry');
        await fetchCurrentUserAndCsrf();
        fd.set('csrf_token', CSRF || '');
        return await postFormData(fd);
      }
      throw err;
    }
  }

  // ---------- UI error helpers ----------
  function setError(msg) {
    if (!refs.errorsBox) return;
    if (!msg) { refs.errorsBox.style.display = 'none'; refs.errorsBox.textContent = ''; return; }
    refs.errorsBox.style.display = 'block';
    refs.errorsBox.textContent = msg;
  }

  function clearFieldErrors() {
    if (!refs.form) return;
    refs.form.querySelectorAll('.field-error').forEach(el => el.remove());
    refs.form.querySelectorAll('.field-invalid').forEach(el => el.classList.remove('field-invalid'));
  }

  function showFieldErrors(errors) {
    clearFieldErrors();
    if (!refs.form) return;
    for (const key in errors) {
      const msgs = errors[key];
      const message = Array.isArray(msgs) ? msgs.join(', ') : String(msgs);
      const idMap = {
        store_name: 'vendor_store_name',
        slug: 'vendor_slug',
        phone: 'vendor_phone',
        email: 'vendor_email',
        website_url: 'vendor_website',
        postal_code: 'vendor_postal',
        service_radius: 'vendor_radius',
        average_response_time: 'vendor_average_response_time',
        country_id: 'vendor_country',
        city_id: 'vendor_city',
        parent_vendor_id: 'vendor_parent_id'
      };
      const elId = idMap[key] || ('vendor_' + key);
      const el = $(elId);
      if (el) {
        el.classList.add('field-invalid');
        const span = document.createElement('div');
        span.className = 'field-error';
        span.style.color = '#b91c1c';
        span.style.marginTop = '4px';
        span.textContent = message;
        el.parentNode.insertBefore(span, el.nextSibling);
      } else {
        setError((refs.errorsBox.textContent ? refs.errorsBox.textContent + ' | ' : '') + `${key}: ${message}`);
      }
    }
  }

  // ---------- Countries / Cities / Parents ----------
  async function loadCountries(selectedId = '') {
    const sel = $('vendor_country');
    if (!sel) return;
    sel.innerHTML = `<option value="">${t('loading_countries','Loading countries...')}</option>`;
    try {
      const data = await fetchJson(COUNTRIES_API);
      sel.innerHTML = `<option value="">-- ${t('select_country','select country')} --</option>`;
      (data || []).forEach(c => {
        const opt = document.createElement('option');
        opt.value = c.id;
        opt.textContent = c.name + (c.iso2 ? ` (${c.iso2})` : '');
        sel.appendChild(opt);
      });
      if (selectedId) sel.value = selectedId;
    } catch (err) {
      sel.innerHTML = `<option value="">${t('failed_load','Failed to load')}</option>`;
      log('loadCountries', err);
    }
  }

  async function loadCities(countryId, selectedId = '') {
    const sel = $('vendor_city');
    if (!sel) return;
    if (!countryId) { sel.innerHTML = `<option value="">${t('select_country_first','Select country first')}</option>`; return; }
    sel.innerHTML = `<option value="">${t('loading_cities','Loading cities...')}</option>`;
    try {
      const data = await fetchJson(`${CITIES_API}?country_id=${encodeURIComponent(countryId)}`);
      sel.innerHTML = `<option value="">-- ${t('select_city','select city')} --</option>`;
      (data || []).forEach(c => {
        const opt = document.createElement('option');
        opt.value = c.id;
        opt.textContent = c.name;
        sel.appendChild(opt);
      });
      if (selectedId) sel.value = selectedId;
    } catch (err) {
      sel.innerHTML = `<option value="">${t('failed_load','Failed to load')}</option>`;
      log('loadCities', err);
    }
  }

  async function loadParentVendors(excludeId = 0) {
    const sel = $('vendor_parent_id');
    if (!sel) return;
    sel.innerHTML = `<option value="">${t('loading_parents','Loading...')}</option>`;
    try {
      const j = await fetchJson(`${API}?parents=1`);
      const rows = j.data || [];
      sel.innerHTML = `<option value="">-- ${t('select_parent','select parent')} --</option>`;
      rows.forEach(v => {
        if (excludeId && Number(excludeId) === Number(v.id)) return;
        const opt = document.createElement('option');
        opt.value = v.id;
        opt.textContent = v.store_name + (v.slug ? ` (${v.slug})` : '');
        sel.appendChild(opt);
      });
    } catch (err) {
      sel.innerHTML = `<option value="">${t('failed_load','Failed to load')}</option>`;
      log('loadParentVendors', err);
    }
  }

  // ---------- وظيفة لعرض/إخفاء حقل parent vendor ----------
  function toggleParentVendorField() {
    if (!refs.parentWrap || !refs.isBranchCheckbox) return;
    if (refs.isBranchCheckbox.checked) {
      refs.parentWrap.style.display = 'block';
      loadParentVendors($('vendor_id')?.value || 0);
    } else {
      refs.parentWrap.style.display = 'none';
      if ($('vendor_parent_id')) $('vendor_parent_id').value = '';
    }
  }

  // bind country change to load cities
  if ($('vendor_country')) $('vendor_country').addEventListener('change', function () { loadCities(this.value); });

  // ---------- Image preview (simple) ----------
  function previewImage(container, fileOrUrl) {
    if (!container) return;
    container.innerHTML = '';
    if (!fileOrUrl) return;
    const img = document.createElement('img');
    img.style.maxWidth = '240px';
    img.style.maxHeight = '160px';
    img.style.display = 'block';
    if (typeof fileOrUrl === 'string') {
      img.src = fileOrUrl;
      container.appendChild(img);
      return;
    }
    const fr = new FileReader();
    fr.onload = e => { img.src = e.target.result; container.appendChild(img); };
    fr.readAsDataURL(fileOrUrl);
  }

  if (refs.logoInput) refs.logoInput.addEventListener('change', function () { const f = this.files[0]; previewImage(refs.previewLogo, f); this._processed = null; });
  if (refs.coverInput) refs.coverInput.addEventListener('change', function () { const f = this.files[0]; previewImage(refs.previewCover, f); this._processed = null; });
  if (refs.bannerInput) refs.bannerInput.addEventListener('change', function () { const f = this.files[0]; previewImage(refs.previewBanner, f); this._processed = null; });

  // ---------- Translations panel ----------
  function addTranslationPanel(code = '', name = '') {
    const container = refs.translationsArea;
    if (!container) return null;
    if (!code) {
      code = prompt('Language code (e.g., ar)');
      if (!code) return null;
    }
    code = String(code).trim();
    if (!code) return null;
    if (container.querySelector(`.tr-lang-panel[data-lang="${code}"]`)) return null;

    const panel = document.createElement('div');
    panel.className = 'tr-lang-panel';
    panel.dataset.lang = code;
    panel.style.border = '1px solid #eef2f7';
    panel.style.padding = '8px';
    panel.style.marginBottom = '8px';
    panel.innerHTML = `
      <div style="display:flex;justify-content:space-between;align-items:center;">
        <strong>${escapeHtml(name || code)} (${escapeHtml(code)})</strong>
        <div>
          <button class="btn small toggle-lang" type="button">Collapse</button>
          <button class="btn small danger remove-lang" type="button">Remove</button>
        </div>
      </div>
      <div class="tr-body" style="margin-top:8px;">
        <label>Description <textarea class="tr-desc" rows="3" style="width:100%"></textarea></label>
        <label>Return policy <textarea class="tr-return" rows="2" style="width:100%"></textarea></label>
        <label>Shipping policy <textarea class="tr-shipping" rows="2" style="width:100%"></textarea></label>
        <label>Meta title <input class="tr-meta-title" type="text" style="width:100%"></label>
        <label>Meta description <input class="tr-meta-desc" type="text" style="width:100%"></label>
      </div>
    `;
    container.appendChild(panel);

    panel.querySelector('.remove-lang').addEventListener('click', () => panel.remove());
    panel.querySelector('.toggle-lang').addEventListener('click', () => {
      const body = panel.querySelector('.tr-body');
      if (!body) return;
      if (body.style.display === 'none') { body.style.display = 'block'; panel.querySelector('.toggle-lang').textContent = 'Collapse'; }
      else { body.style.display = 'none'; panel.querySelector('.toggle-lang').textContent = 'Open'; }
    });

    if (LANG_DIRECTION === 'rtl') panel.querySelectorAll('textarea,input').forEach(i => i.setAttribute('dir','rtl'));
    return panel;
  }
  if (refs.addLangBtn) refs.addLangBtn.addEventListener('click', () => addTranslationPanel('', ''));

  function collectTranslations() {
    const out = {};
    const container = refs.translationsArea;
    if (!container) return out;
    container.querySelectorAll('.tr-lang-panel').forEach(p => {
      const lang = p.dataset.lang;
      const desc = p.querySelector('.tr-desc')?.value || '';
      const rp = p.querySelector('.tr-return')?.value || '';
      const sp = p.querySelector('.tr-shipping')?.value || '';
      const mt = p.querySelector('.tr-meta-title')?.value || '';
      const md = p.querySelector('.tr-meta-desc')?.value || '';
      if (desc || rp || sp || mt || md) out[lang] = { description: desc, return_policy: rp, shipping_policy: sp, meta_title: mt, meta_description: md };
    });
    return out;
  }

  // ---------- collectFormData (comprehensive & robust) ----------
  function collectFormData() {
    const fd = new FormData();
    fd.append('action', 'save');
    fd.append('id', $('vendor_id')?.value || 0);

    // canonical list of server fields we want to send
    const serverFields = [
      'store_name','slug','vendor_type','store_type','is_branch','parent_vendor_id','branch_code',
      'inherit_settings','inherit_products','inherit_commission',
      'phone','mobile','email','website_url','registration_number','tax_number',
      'country_id','city_id','address','postal_code','latitude','longitude',
      'commission_rate','service_radius','accepts_online_booking','average_response_time',
      'status','suspension_reason','is_verified','is_featured','approved_at'
    ];

    // mapping of common DOM ids to server field names (covers variants)
    const domCandidates = {
      store_name: ['vendor_store_name','store_name'],
      slug: ['vendor_slug','slug'],
      vendor_type: ['vendor_type','vendor_vendor_type'],
      store_type: ['vendor_store_type','store_type'],
      is_branch: ['vendor_is_branch','is_branch'],
      parent_vendor_id: ['vendor_parent_id','parent_vendor_id'],
      branch_code: ['vendor_branch_code','branch_code'],
      inherit_settings: ['inherit_settings','vendor_inherit_settings'],
      inherit_products: ['inherit_products','vendor_inherit_products'],
      inherit_commission: ['inherit_commission','vendor_inherit_commission'],
      phone: ['vendor_phone','phone'],
      mobile: ['vendor_mobile','mobile'],
      email: ['vendor_email','email'],
      website_url: ['vendor_website','website_url','vendor_site'],
      registration_number: ['vendor_registration_number','registration_number'],
      tax_number: ['vendor_tax_number','tax_number'],
      country_id: ['vendor_country','country_id'],
      city_id: ['vendor_city','city_id'],
      address: ['vendor_address','address'],
      postal_code: ['vendor_postal','vendor_postal_code','postal_code'],
      latitude: ['vendor_latitude','latitude'],
      longitude: ['vendor_longitude','longitude'],
      commission_rate: ['vendor_commission','commission_rate'],
      service_radius: ['vendor_radius','service_radius'],
      accepts_online_booking: ['vendor_accepts_online_booking','accepts_online_booking'],
      average_response_time: ['vendor_average_response_time','average_response_time'],
      status: ['vendor_status','status'],
      suspension_reason: ['vendor_suspension_reason','suspension_reason'],
      is_verified: ['vendor_is_verified','is_verified'],
      is_featured: ['vendor_is_featured','is_featured'],
      approved_at: ['vendor_approved_at','approved_at']
    };

    function getValFromDomCandidates(field) {
      const cands = domCandidates[field] || ['vendor_' + field];
      for (const id of cands) {
        const el = document.getElementById(id);
        if (!el) continue;
        if (el.type === 'checkbox') return el.checked ? '1' : '0';
        return el.value == null ? '' : String(el.value);
      }
      return '';
    }

    serverFields.forEach(f => fd.set(f, getValFromDomCandidates(f)));

    // Files - server expects vendor_logo/vendor_cover/vendor_banner
    const logo = document.getElementById('vendor_logo');
    if (logo && logo._processed) fd.set('vendor_logo', logo._processed, logo._processed.name);
    else if (logo && logo.files && logo.files[0]) fd.set('vendor_logo', logo.files[0]);

    const cover = document.getElementById('vendor_cover');
    if (cover && cover._processed) fd.set('vendor_cover', cover._processed, cover._processed.name);
    else if (cover && cover.files && cover.files[0]) fd.set('vendor_cover', cover.files[0]);

    const banner = document.getElementById('vendor_banner');
    if (banner && banner._processed) fd.set('vendor_banner', banner._processed, banner._processed.name);
    else if (banner && banner.files && banner.files[0]) fd.set('vendor_banner', banner.files[0]);

    // Translations (always send JSON string)
    fd.set('translations', JSON.stringify(collectTranslations()));

    if (DEBUG) {
      const dbg = {};
      for (const p of fd.entries()) dbg[p[0]] = (p[1] instanceof File) ? `[File] ${p[1].name}` : p[1];
      console.log('collectFormData ->', dbg);
    }

    return fd;
  }

  // ---------- Save logic ----------
  async function saveVendor() {
    setError('');
    clearFieldErrors();

    // client-side required checks
    const required = ['vendor_store_name','vendor_email','vendor_phone','vendor_country'];
    const clientErr = {};
    required.forEach(id => {
      const el = document.getElementById(id);
      if (!el || !String(el.value || '').trim()) clientErr[id.replace('vendor_','')] = [`${id} required`];
    });
    if (Object.keys(clientErr).length) { showFieldErrors(clientErr); setError(t('fix_errors','Please fix errors')); return; }

    const fd = collectFormData();

    // ensure country present
    if (!fd.get('country_id')) {
      showFieldErrors({ country_id: ['Country id is required'] });
      setError(t('country_required','Please select a country before saving.'));
      document.getElementById('vendor_country')?.focus();
      return;
    }

    try {
      const res = await postWithRetry(fd);
      if (!res || !res.success) {
        if (res && res.errors) { showFieldErrors(res.errors || {}); setError(res.message || t('validation_failed','Validation failed')); }
        else setError(res.message || t('save_failed','Save failed'));
        return;
      }
      resetForm();
      await loadList();
      showNotification(t('saved','Saved successfully'), 'success', 3000);
    } catch (err) {
      log('saveVendor', err);
      if (err && err.status === 422 && err.body && err.body.errors) {
        showFieldErrors(err.body.errors);
        setError(err.body.message || t('validation_failed','Validation failed'));
        return;
      }
      if (err && err.status === 403) {
        showNotification(t('forbidden','Forbidden — session expired or no permission.'), 'error', 5000);
        return;
      }
      showNotification(t('network_error','Network or server error'), 'error', 5000);
    }
  }

  // ---------- Edit / Delete / Toggle ----------
  function resetForm() {
    if (!refs.form) return;
    refs.form.reset();
    if (refs.translationsArea) refs.translationsArea.innerHTML = '';
    addTranslationPanel(PREF_LANG, LANGS.find(l => l.code === PREF_LANG)?.name || PREF_LANG);
    if ($('vendor_id')) $('vendor_id').value = 0;
    if (refs.previewLogo) refs.previewLogo.innerHTML = '';
    if (refs.previewCover) refs.previewCover.innerHTML = '';
    if (refs.previewBanner) refs.previewBanner.innerHTML = '';
    ['vendor_logo','vendor_cover','vendor_banner'].forEach(id => { const el = document.getElementById(id); if (el) el._processed = null; });
    clearFieldErrors(); setError('');
    if (refs.parentWrap) refs.parentWrap.style.display = 'none';
    // إعادة تعيين checkbox is_branch
    if (refs.isBranchCheckbox) refs.isBranchCheckbox.checked = false;
    const sect = refs.formSection;
    if (sect) window.scrollTo({ top: sect.offsetTop - 20, behavior: 'smooth' });
  }

  async function openEdit(id) {
    setError(''); clearFieldErrors();
    try {
      const j = await fetchJson(`${API}?_fetch_row=1&id=${encodeURIComponent(id)}`);
      if (!j || !j.success) { 
        showNotification(j?.message || t('load_failed','Load failed'), 'error', 5000);
        return; 
      }
      const v = j.data;

      if ($('vendor_id')) $('vendor_id').value = v.id || 0;

      // fill fields: includes postal_code, website_url, service_radius, average_response_time
      const fillMap = {
        vendor_store_name: v.store_name || '',
        vendor_slug: v.slug || '',
        vendor_type: v.vendor_type || '',
        vendor_store_type: v.store_type || '',
        vendor_branch_code: v.branch_code || '',
        vendor_phone: v.phone || '',
        vendor_mobile: v.mobile || '',
        vendor_email: v.email || '',
        vendor_website: v.website_url || '',
        vendor_registration_number: v.registration_number || '',
        vendor_tax_number: v.tax_number || '',
        vendor_postal: v.postal_code || '',
        vendor_address: v.address || '',
        vendor_latitude: v.latitude || '',
        vendor_longitude: v.longitude || '',
        vendor_commission: v.commission_rate || '',
        vendor_radius: v.service_radius || '',
        vendor_average_response_time: v.average_response_time || ''
      };
      Object.keys(fillMap).forEach(id => { const el = document.getElementById(id); if (el) el.value = fillMap[id]; });

      // checkboxes
      if ($('vendor_is_branch')) $('vendor_is_branch').checked = !!v.is_branch;
      if ($('inherit_settings')) $('inherit_settings').checked = (v.inherit_settings == 1);
      if ($('inherit_products')) $('inherit_products').checked = (v.inherit_products == 1);
      if ($('inherit_commission')) $('inherit_commission').checked = (v.inherit_commission == 1);
      if ($('vendor_accepts_online_booking')) $('vendor_accepts_online_booking').checked = (v.accepts_online_booking == 1);

      // التحكم في عرض حقل parent vendor بناءً على is_branch
      toggleParentVendorField();

      // countries/cities
      await loadCountries(v.country_id || '');
      if (v.country_id) {
        if (document.getElementById('vendor_country')) document.getElementById('vendor_country').value = v.country_id;
        await loadCities(v.country_id, v.city_id || '');
      }

      // translations
      if (refs.translationsArea) refs.translationsArea.innerHTML = '';
      if (v.translations) {
        for (const lang of Object.keys(v.translations)) {
          addTranslationPanel(lang, lang);
          const panel = refs.translationsArea.querySelector(`.tr-lang-panel[data-lang="${lang}"]`);
          if (!panel) continue;
          const data = v.translations[lang] || {};
          panel.querySelector('.tr-desc') && (panel.querySelector('.tr-desc').value = data.description || '');
          panel.querySelector('.tr-return') && (panel.querySelector('.tr-return').value = data.return_policy || '');
          panel.querySelector('.tr-shipping') && (panel.querySelector('.tr-shipping').value = data.shipping_policy || '');
          panel.querySelector('.tr-meta-title') && (panel.querySelector('.tr-meta-title').value = data.meta_title || '');
          panel.querySelector('.tr-meta-desc') && (panel.querySelector('.tr-meta-desc').value = data.meta_description || '');
        }
      } else addTranslationPanel(PREF_LANG, LANGS.find(l => l.code === PREF_LANG)?.name || PREF_LANG);

      // admin-only
      if (IS_ADMIN) {
        if ($('vendor_status')) $('vendor_status').value = v.status || 'pending';
        if ($('vendor_is_verified')) $('vendor_is_verified').checked = !!v.is_verified;
        if ($('vendor_is_featured')) $('vendor_is_featured').checked = !!v.is_featured;
      }

      await loadParentVendors(v.id);
      if (v.parent_vendor_id && $('vendor_parent_id')) $('vendor_parent_id').value = v.parent_vendor_id;

      // show previews if urls provided
      if (v.logo_url && refs.previewLogo) previewImage(refs.previewLogo, v.logo_url);
      if (v.cover_image_url && refs.previewCover) previewImage(refs.previewCover, v.cover_image_url);
      if (v.banner_url && refs.previewBanner) previewImage(refs.previewBanner, v.banner_url);

      const sect = refs.formSection;
      if (sect) window.scrollTo({ top: sect.offsetTop - 20, behavior: 'smooth' });

    } catch (err) {
      log('openEdit error', err);
      if (err && err.status === 403) { 
        showNotification(t('forbidden','Forbidden — session expired or no permission.'), 'error', 5000);
        await fetchCurrentUserAndCsrf(); 
      }
      else showNotification(t('error_loading','Error loading vendor'), 'error', 5000);
    }
  }

  // ---------- Delete/Toggle ----------
  function doDelete(id) {
    if (!confirm(t('confirm_delete','Delete vendor #') + id + '?')) return;
    const fd = new FormData(); fd.append('action', 'delete'); fd.append('id', id);
    postWithRetry(fd).then(res => {
      if (!res || !res.success) {
        showNotification(t('delete_failed','Delete failed: ') + (res?.message || ''), 'error', 5000);
      } else { 
        resetForm(); 
        loadList(); 
        showNotification(t('deleted','Deleted'), 'success', 3000);
      }
    }).catch(err => { 
      log('delete', err); 
      showNotification(t('network_error','Network error'), 'error', 5000);
    });
  }

  function toggleVerify(id, value) {
    if (!confirm(t('confirm_toggle_verify','Toggle verification?'))) return;
    const fd = new FormData(); fd.append('action', 'toggle_verify'); fd.append('id', id); fd.append('value', value);
    postWithRetry(fd).then(res => {
      if (!res || !res.success) {
        showNotification(t('failed','Failed: ') + (res?.message || ''), 'error', 5000);
      } else {
        loadList();
        showNotification(t('updated','Updated'), 'success', 3000);
      }
    }).catch(err => { 
      log('toggleVerify', err); 
      showNotification(t('network_error','Network error'), 'error', 5000);
    });
  }

  // ---------- Wire UI and init ----------
  function wireUI() {
    if (refs.vendorSearch) {
      let timer = null;
      refs.vendorSearch.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(loadList, 300); });
    }
    if (refs.vendorRefresh) refs.vendorRefresh.addEventListener('click', loadList);
    if (refs.vendorNewBtn) refs.vendorNewBtn.addEventListener('click', () => { resetForm(); if (refs.formTitle) refs.formTitle.textContent = t('create_edit','Create / Edit Vendor'); loadParentVendors(); });
    if (refs.saveBtn) refs.saveBtn.addEventListener('click', saveVendor);
    if (refs.resetBtn) refs.resetBtn.addEventListener('click', resetForm);
    if (refs.addLangBtn) refs.addLangBtn.addEventListener('click', () => addTranslationPanel('', ''));
    if (refs.btnGetCoords) refs.btnGetCoords.addEventListener('click', () => {
      setError('');
      if (!navigator.geolocation) { 
        showNotification(t('geolocation_not_supported','Geolocation not supported'), 'error', 5000);
        return; 
      }
      setError(t('getting_location','Getting current location...'));
      navigator.geolocation.getCurrentPosition(pos => {
        if ($('vendor_latitude')) $('vendor_latitude').value = pos.coords.latitude.toFixed(7);
        if ($('vendor_longitude')) $('vendor_longitude').value = pos.coords.longitude.toFixed(7);
        setError('');
      }, err => setError(t('could_not_get_location','Could not get location: ') + (err.message || err.code)), { enableHighAccuracy:false, timeout:10000, maximumAge:60000 });
    });
    
    // إضافة حدث لعرض/إخفاء حقل parent vendor
    if (refs.isBranchCheckbox) {
      refs.isBranchCheckbox.addEventListener('change', toggleParentVendorField);
    }
    
    // إضافة أحداث لفلاتر البحث المتقدمة
    if (refs.filterClear) {
      refs.filterClear.addEventListener('click', () => {
        if (refs.filterStatus) refs.filterStatus.value = '';
        if (refs.filterVerified) refs.filterVerified.value = '';
        if (refs.filterCountry) refs.filterCountry.value = '';
        if (refs.filterCity) refs.filterCity.value = '';
        if (refs.filterPhone) refs.filterPhone.value = '';
        if (refs.filterEmail) refs.filterEmail.value = '';
        loadList();
      });
    }
    
    // إضافة أحداث التغيير للفلاتر
    const filters = ['filterStatus', 'filterVerified', 'filterCountry', 'filterCity', 'filterPhone', 'filterEmail'];
    filters.forEach(filterName => {
      if (refs[filterName]) {
        refs[filterName].addEventListener('change', loadList);
      }
    });
    
    // Ensure at least one translation panel exists
    if (refs.translationsArea && refs.translationsArea.children.length === 0) addTranslationPanel(PREF_LANG, LANGS.find(l => l.code === PREF_LANG)?.name || PREF_LANG);
  }

  // ---------- Initialization ----------
  (async function init() {
    await fetchCurrentUserAndCsrf();
    // apply direction
    if (LANG_DIRECTION === 'rtl') {
      document.documentElement.dir = 'rtl';
      if (refs.form) refs.form.style.direction = 'rtl';
    } else {
      document.documentElement.dir = 'ltr';
      if (refs.form) refs.form.style.direction = 'ltr';
    }
    wireUI();
    await loadCountries();
    // تحميل قائمة البلدان للفلتر
    await loadFilterCountries();
    resetForm();
    await loadList();
    // expose debug helpers
    window.vendorsAdmin = {
      collectFormData: () => {
        try {
          const fd = collectFormData();
          const out = {};
          for (const e of fd.entries()) out[e[0]] = (e[1] instanceof File) ? `[File] ${e[1].name}` : e[1];
          return out;
        } catch (e) { return { error: e.message }; }
      },
      reload: async () => { await loadCountries(); await loadList(); }
    };
  })();

  // ---------- loadFilterCountries للفلتر ----------
  async function loadFilterCountries() {
    const sel = refs.filterCountry;
    if (!sel) return;
    sel.innerHTML = `<option value="">-- ${t('all_countries','All countries')} --</option>`;
    try {
      const data = await fetchJson(COUNTRIES_API);
      (data || []).forEach(c => {
        const opt = document.createElement('option');
        opt.value = c.id;
        opt.textContent = c.name + (c.iso2 ? ` (${c.iso2})` : '');
        sel.appendChild(opt);
      });
    } catch (err) {
      log('loadFilterCountries', err);
    }
  }

  // ---------- loadList & render مع الفلترة ----------
  async function loadList() {
    if (!refs.tbody) return;
    refs.tbody.innerHTML = `<tr><td colspan="7" style="text-align:center;color:#6b7280;padding:18px">${t('loading','Loading...')}</td></tr>`;
    try {
      const params = new URLSearchParams();
      
      // البحث العادي
      const q = (refs.vendorSearch && refs.vendorSearch.value) ? refs.vendorSearch.value.trim() : '';
      if (q) params.append('search', q);
      
      // الفلترة المتقدمة
      if (refs.filterStatus && refs.filterStatus.value) params.append('status', refs.filterStatus.value);
      if (refs.filterVerified && refs.filterVerified.value) params.append('is_verified', refs.filterVerified.value);
      if (refs.filterCountry && refs.filterCountry.value) params.append('country_id', refs.filterCountry.value);
      if (refs.filterCity && refs.filterCity.value) params.append('city_id', refs.filterCity.value);
      if (refs.filterPhone && refs.filterPhone.value) params.append('phone', refs.filterPhone.value);
      if (refs.filterEmail && refs.filterEmail.value) params.append('email', refs.filterEmail.value);
      
      const url = API + (params.toString() ? '?' + params.toString() : '');
      const j = await fetchJson(url);
      const rows = j.data || j.data?.data || j || [];
      const total = j.total ?? (Array.isArray(rows) ? rows.length : 0);
      if (refs.vendorsCount) refs.vendorsCount.textContent = total;
      renderTable(Array.isArray(rows) ? rows : []);
    } catch (err) {
      log('loadList', err);
      refs.tbody.innerHTML = `<tr><td colspan="7" style="text-align:center;color:#b91c1c;padding:18px">${t('error_loading','Error loading')}</td></tr>`;
    }
  }

  function renderTable(rows) {
    if (!refs.tbody) return;
    if (!rows || rows.length === 0) {
      refs.tbody.innerHTML = `<tr><td colspan="7" style="text-align:center;color:#6b7280;padding:18px">${t('no_vendors','No vendors')}</td></tr>`;
      return;
    }
    refs.tbody.innerHTML = '';
    rows.forEach(v => {
      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td style="padding:8px;border-bottom:1px solid #eef2f7">${escapeHtml(String(v.id))}</td>
        <td style="padding:8px;border-bottom:1px solid #eef2f7">${escapeHtml(v.store_name)}<br><small>${escapeHtml(v.slug||'')}</small></td>
        <td style="padding:8px;border-bottom:1px solid #eef2f7">${escapeHtml(v.email||'')}</td>
        <td style="padding:8px;border-bottom:1px solid #eef2f7">${escapeHtml(v.vendor_type||'')}</td>
        <td style="padding:8px;border-bottom:1px solid #eef2f7">${escapeHtml(v.status||'')}</td>
        <td style="padding:8px;border-bottom:1px solid #eef2f7;text-align:center">${v.is_verified ? '<strong>Yes</strong>' : 'No'}</td>
        <td style="padding:8px;border-bottom:1px solid #eef2f7">
          <button class="btn editBtn" data-id="${escapeHtml(String(v.id))}">${t('edit','Edit')}</button>
          <button class="btn danger delBtn" data-id="${escapeHtml(String(v.id))}">${t('delete','Delete')}</button>
          ${IS_ADMIN ? `<button class="btn small verifyBtn" data-id="${escapeHtml(String(v.id))}" data-ver="${v.is_verified?0:1}">${v.is_verified? t('unverify','Unverify') : t('verify','Verify')}</button>` : ''}
        </td>`;
      refs.tbody.appendChild(tr);
    });
    refs.tbody.querySelectorAll('.editBtn').forEach(b => b.addEventListener('click', e => openEdit(b.dataset.id)));
    refs.tbody.querySelectorAll('.delBtn').forEach(b => b.addEventListener('click', e => doDelete(b.dataset.id)));
    refs.tbody.querySelectorAll('.verifyBtn').forEach(b => b.addEventListener('click', e => toggleVerify(b.dataset.id, b.dataset.ver)));
  }

  // ---------- Expose save action to global (optional) ----------
  window.saveVendor = saveVendor;

})();