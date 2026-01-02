/**
 * admin/assets/js/pages/vendors.js
 * Vendors Management UI - Works with MVC structure via /api/routes/vendors.php
 */

(function () {
  'use strict';

  // ---------- Configuration ----------
  const API = '/api/routes/vendors.php'; // Main API endpoint
  const COUNTRIES_API = '/api/helpers/countries.php';
  const CITIES_API = '/api/helpers/cities.php';
  const DEBUG = false;

  // ---------- Runtime state from bootstrap_admin_ui.php ----------
  const ADMIN_UI = window.ADMIN_UI || {};
  let CSRF_TOKEN = ADMIN_UI.csrf_token || window.CSRF_TOKEN || '';
  const CURRENT_USER = ADMIN_UI.user || window.CURRENT_USER || {};
  const STRINGS = ADMIN_UI.strings || {};
  const AVAILABLE_LANGS = window.AVAILABLE_LANGUAGES || [];
  const PREF_LANG = ADMIN_UI.lang || window.ADMIN_LANG || 'en';
  const LANG_DIRECTION = ADMIN_UI.direction || window.LANG_DIRECTION || 'ltr';
  const IS_ADMIN = CURRENT_USER.role_id && Number(CURRENT_USER.role_id) === 1;

  // ---------- Helpers ----------
  const $ = (id) => document.getElementById(id);
  const log = (...args) => { if (DEBUG) console.log('[vendors.js]', ...args); };

  function escapeHtml(str) {
    return String(str || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }

  function t(key, fallback = '') {
    return STRINGS[key] || fallback || key;
  }

  // ---------- DOM References ----------
  const refs = {
    // Table section
    tbody: $('vendorsTbody'),
    vendorsCount: $('vendorsCount'),
    vendorSearch: $('vendorSearch'),
    vendorRefresh: $('vendorRefresh'),
    vendorNewBtn: $('vendorNewBtn'),

    // Advanced filters
    filterStatus: $('filterStatus'),
    filterVerified: $('filterVerified'),
    filterCountry: $('filterCountry'),
    filterCity: $('filterCity'),
    filterPhone: $('filterPhone'),
    filterEmail: $('filterEmail'),
    filterClear: $('filterClear'),

    // Form section
    formSection: $('vendorFormSection'),
    form: $('vendorForm'),
    formTitle: $('vendorFormTitle'),
    saveBtn: $('vendorSaveBtn'),
    resetBtn: $('vendorResetBtn'),
    errorsBox: $('vendorFormErrors'),

    // Translations
    translationsArea: $('vendor_translations_area'),
    addLangBtn: $('vendorAddLangBtn'),

    // Parent vendor
    parentWrap: $('parentVendorWrap'),
    isBranchCheckbox: $('vendor_is_branch'),

    // Image previews
    previewLogo: $('preview_logo'),
    previewCover: $('preview_cover'),
    previewBanner: $('preview_banner'),

    // Image inputs
    logoInput: $('vendor_logo'),
    coverInput: $('vendor_cover'),
    bannerInput: $('vendor_banner'),

    // Coordinates
    btnGetCoords: $('btnGetCoords')
  };

  // ---------- Notification System ----------
  function showNotification(message, type = 'info', duration = 5000) {
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

    const colors = {
      success: '#10b981',
      error: '#ef4444',
      warning: '#f59e0b',
      info: '#3b82f6'
    };
    notification.style.backgroundColor = colors[type] || colors.info;

    notification.innerHTML = `
      <div style="flex: 1; margin-right: 10px;">
        ${escapeHtml(message)}
      </div>
      <button class="notification-close" style="background: none; border: none; color: white; font-size: 18px; cursor: pointer; padding: 0; margin-left: 10px;">
        ×
      </button>
    `;

    notificationArea.appendChild(notification);

    notification.querySelector('.notification-close').addEventListener('click', () => {
      notification.style.animation = 'slideOut 0.3s ease';
      setTimeout(() => notification.remove(), 300);
    });

    if (duration > 0) {
      setTimeout(() => {
        if (notification.parentNode) {
          notification.style.animation = 'slideOut 0.3s ease';
          setTimeout(() => notification.remove(), 300);
        }
      }, duration);
    }

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

  // ---------- Network Functions ----------
  async function fetchJson(url, opts = {}) {
    opts.credentials = 'include';
    log('Fetching:', url);
    const res = await fetch(url, opts);
    let body = null;
    try { 
      body = await res.json(); 
    } catch (e) { 
      log('Failed to parse JSON:', e);
      body = null; 
    }
    if (!res.ok) {
      log('Request failed:', { status: res.status, body });
      throw { status: res.status, body };
    }
    return body;
  }

  async function postFormData(fd) {
    if (!fd) fd = new FormData();
    if (CSRF_TOKEN) {
      fd.set('csrf_token', CSRF_TOKEN);
    }
    
    const res = await fetch(API, { 
      method: 'POST', 
      body: fd, 
      credentials: 'include',
      headers: {
        'X-CSRF-Token': CSRF_TOKEN || ''
      }
    });
    
    let json = null;
    try { 
      json = await res.json(); 
    } catch (e) { 
      log('Failed to parse JSON response:', e);
      json = null; 
    }
    
    if (!res.ok) {
      log('POST request failed:', { status: res.status, json });
      throw { status: res.status, body: json };
    }
    
    return json;
  }

  // ---------- Error Handling ----------
  function setError(msg) {
    if (!refs.errorsBox) return;
    if (!msg) {
      refs.errorsBox.style.display = 'none';
      refs.errorsBox.textContent = '';
      return;
    }
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
        span.style.fontSize = '12px';
        span.textContent = message;
        el.parentNode.insertBefore(span, el.nextSibling);
      } else {
        setError((refs.errorsBox.textContent ? refs.errorsBox.textContent + ' | ' : '') + `${key}: ${message}`);
      }
    }
  }

  // ---------- Countries & Cities ----------
  async function loadCountries(selectId = 'vendor_country', selectedId = '') {
    const sel = $(selectId);
    if (!sel) return;
    
    sel.innerHTML = `<option value="">${t('loading_countries', 'Loading countries...')}</option>`;
    
    try {
      const response = await fetchJson(`${COUNTRIES_API}?lang=${encodeURIComponent(PREF_LANG)}&scope=all`);
      const countries = response.data || response || [];
      
      sel.innerHTML = `<option value="">-- ${t('select_country', 'Select country')} --</option>`;
      countries.forEach(c => {
        const opt = document.createElement('option');
        opt.value = c.id;
        opt.textContent = c.name + (c.iso2 ? ` (${c.iso2})` : '');
        sel.appendChild(opt);
      });
      
      if (selectedId) sel.value = selectedId;
      log(`Loaded ${countries.length} countries`);
    } catch (err) {
      sel.innerHTML = `<option value="">${t('failed_load', 'Failed to load')}</option>`;
      log('loadCountries error:', err);
    }
  }

  async function loadCities(countryId, selectId = 'vendor_city', selectedId = '') {
    const sel = $(selectId);
    if (!sel) return;
    
    if (!countryId) {
      sel.innerHTML = `<option value="">${t('select_country_first', 'Select country first')}</option>`;
      return;
    }
    
    sel.innerHTML = `<option value="">${t('loading_cities', 'Loading cities...')}</option>`;
    
    try {
      const response = await fetchJson(`${CITIES_API}?country_id=${encodeURIComponent(countryId)}&lang=${encodeURIComponent(PREF_LANG)}&scope=all`);
      const cities = response.data || response || [];
      
      sel.innerHTML = `<option value="">-- ${t('select_city', 'Select city')} --</option>`;
      cities.forEach(c => {
        const opt = document.createElement('option');
        opt.value = c.id;
        opt.textContent = c.name;
        sel.appendChild(opt);
      });
      
      if (selectedId) sel.value = selectedId;
      log(`Loaded ${cities.length} cities for country ${countryId}`);
    } catch (err) {
      sel.innerHTML = `<option value="">${t('failed_load', 'Failed to load')}</option>`;
      log('loadCities error:', err);
    }
  }

  async function loadParentVendors(excludeId = 0) {
    const sel = $('vendor_parent_id');
    if (!sel) return;
    
    sel.innerHTML = `<option value="">${t('loading_parents', 'Loading...')}</option>`;
    
    try {
      const response = await fetchJson(`${API}?parents=1`);
      const rows = response.data || [];
      
      sel.innerHTML = `<option value="">-- ${t('select_parent', 'Select parent')} --</option>`;
      rows.forEach(v => {
        if (excludeId && Number(excludeId) === Number(v.id)) return;
        const opt = document.createElement('option');
        opt.value = v.id;
        opt.textContent = v.store_name + (v.slug ? ` (${v.slug})` : '');
        sel.appendChild(opt);
      });
    } catch (err) {
      sel.innerHTML = `<option value="">${t('failed_load', 'Failed to load')}</option>`;
      log('loadParentVendors error:', err);
    }
  }

  // ---------- Parent Vendor Toggle ----------
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

  // ---------- Image Preview ----------
  function previewImage(containerId, fileOrUrl) {
    const container = $(containerId);
    if (!container) return;
    
    container.innerHTML = '';
    if (!fileOrUrl) return;
    
    const img = document.createElement('img');
    img.style.cssText = `
      max-width: 240px;
      max-height: 160px;
      border-radius: 8px;
      object-fit: cover;
      display: block;
    `;
    
    if (typeof fileOrUrl === 'string') {
      img.src = fileOrUrl;
      container.appendChild(img);
      return;
    }
    
    const fr = new FileReader();
    fr.onload = e => {
      img.src = e.target.result;
      container.appendChild(img);
    };
    fr.readAsDataURL(fileOrUrl);
  }

  if (refs.logoInput) {
    refs.logoInput.addEventListener('change', function() {
      const file = this.files[0];
      previewImage('preview_logo', file);
    });
  }
  
  if (refs.coverInput) {
    refs.coverInput.addEventListener('change', function() {
      const file = this.files[0];
      previewImage('preview_cover', file);
    });
  }
  
  if (refs.bannerInput) {
    refs.bannerInput.addEventListener('change', function() {
      const file = this.files[0];
      previewImage('preview_banner', file);
    });
  }

  // ---------- Translations Management ----------
  function addTranslationPanel(code = '', name = '') {
    if (!refs.translationsArea) return null;
    
    if (!code) {
      code = prompt(t('enter_lang_code', 'Enter language code (e.g., ar):'));
      if (!code) return null;
    }
    
    code = String(code).trim().toLowerCase();
    if (!code) return null;
    
    if (refs.translationsArea.querySelector(`.tr-lang-panel[data-lang="${code}"]`)) {
      showNotification(t('lang_exists', 'Language already added'), 'warning');
      return null;
    }

    const panel = document.createElement('div');
    panel.className = 'tr-lang-panel';
    panel.dataset.lang = code;
    panel.style.cssText = `
      border: 1px solid #e5e7eb;
      border-radius: 8px;
      padding: 16px;
      margin-bottom: 16px;
      background: #f9fafb;
    `;
    
    panel.innerHTML = `
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
        <div>
          <strong style="font-size: 16px;">${escapeHtml(name || code.toUpperCase())}</strong>
          <span style="color: #6b7280; font-size: 14px; margin-left: 8px;">(${escapeHtml(code)})</span>
        </div>
        <div>
          <button type="button" class="btn small toggle-lang" style="margin-right: 8px;">${t('collapse', 'Collapse')}</button>
          <button type="button" class="btn small danger remove-lang">${t('remove', 'Remove')}</button>
        </div>
      </div>
      <div class="tr-body">
        <div style="margin-bottom: 12px;">
          <label style="display: block; margin-bottom: 4px; font-weight: 500;">${t('description', 'Description')}</label>
          <textarea name="translations[${code}][description]" rows="3" style="width: 100%; padding: 8px; border: 1px solid #d1d5db; border-radius: 4px;"></textarea>
        </div>
        <div style="margin-bottom: 12px;">
          <label style="display: block; margin-bottom: 4px; font-weight: 500;">${t('return_policy', 'Return Policy')}</label>
          <textarea name="translations[${code}][return_policy]" rows="2" style="width: 100%; padding: 8px; border: 1px solid #d1d5db; border-radius: 4px;"></textarea>
        </div>
        <div style="margin-bottom: 12px;">
          <label style="display: block; margin-bottom: 4px; font-weight: 500;">${t('shipping_policy', 'Shipping Policy')}</label>
          <textarea name="translations[${code}][shipping_policy]" rows="2" style="width: 100%; padding: 8px; border: 1px solid #d1d5db; border-radius: 4px;"></textarea>
        </div>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
          <div>
            <label style="display: block; margin-bottom: 4px; font-weight: 500;">${t('meta_title', 'Meta Title')}</label>
            <input type="text" name="translations[${code}][meta_title]" style="width: 100%; padding: 8px; border: 1px solid #d1d5db; border-radius: 4px;">
          </div>
          <div>
            <label style="display: block; margin-bottom: 4px; font-weight: 500;">${t('meta_description', 'Meta Description')}</label>
            <input type="text" name="translations[${code}][meta_description]" style="width: 100%; padding: 8px; border: 1px solid #d1d5db; border-radius: 4px;">
          </div>
        </div>
      </div>
    `;
    
    refs.translationsArea.appendChild(panel);

    panel.querySelector('.remove-lang').addEventListener('click', () => {
      panel.remove();
    });
    
    panel.querySelector('.toggle-lang').addEventListener('click', () => {
      const body = panel.querySelector('.tr-body');
      if (!body) return;
      
      if (body.style.display === 'none') {
        body.style.display = 'block';
        panel.querySelector('.toggle-lang').textContent = t('collapse', 'Collapse');
      } else {
        body.style.display = 'none';
        panel.querySelector('.toggle-lang').textContent = t('expand', 'Expand');
      }
    });

    if (LANG_DIRECTION === 'rtl') {
      panel.querySelectorAll('textarea, input').forEach(el => el.setAttribute('dir', 'rtl'));
    }
    
    return panel;
  }

  if (refs.addLangBtn) {
    refs.addLangBtn.addEventListener('click', () => addTranslationPanel('', ''));
  }

  function collectTranslations() {
    const translations = {};
    
    if (!refs.translationsArea) return translations;
    
    refs.translationsArea.querySelectorAll('.tr-lang-panel').forEach(panel => {
      const lang = panel.dataset.lang;
      const data = {};
      
      const desc = panel.querySelector(`[name="translations[${lang}][description]"]`)?.value || '';
      const returnPolicy = panel.querySelector(`[name="translations[${lang}][return_policy]"]`)?.value || '';
      const shippingPolicy = panel.querySelector(`[name="translations[${lang}][shipping_policy]"]`)?.value || '';
      const metaTitle = panel.querySelector(`[name="translations[${lang}][meta_title]"]`)?.value || '';
      const metaDesc = panel.querySelector(`[name="translations[${lang}][meta_description]"]`)?.value || '';
      
      if (desc || returnPolicy || shippingPolicy || metaTitle || metaDesc) {
        translations[lang] = {
          description: desc,
          return_policy: returnPolicy,
          shipping_policy: shippingPolicy,
          meta_title: metaTitle,
          meta_description: metaDesc
        };
      }
    });
    
    return translations;
  }

  // ---------- Form Data Collection ----------
  function collectFormData() {
    const fd = new FormData();
    fd.append('action', 'save');
    fd.append('id', $('vendor_id')?.value || '0');

    const fieldMapping = {
      // Basic info
      'vendor_store_name': 'store_name',
      'vendor_slug': 'slug',
      'vendor_type': 'vendor_type',
      'vendor_store_type': 'store_type',
      'vendor_branch_code': 'branch_code',
      
      // Contact info
      'vendor_phone': 'phone',
      'vendor_mobile': 'mobile',
      'vendor_email': 'email',
      'vendor_website': 'website_url',
      'vendor_registration_number': 'registration_number',
      'vendor_tax_number': 'tax_number',
      
      // Location
      'vendor_country': 'country_id',
      'vendor_city': 'city_id',
      'vendor_address': 'address',
      'vendor_postal': 'postal_code',
      'vendor_latitude': 'latitude',
      'vendor_longitude': 'longitude',
      
      // Business settings
      'vendor_commission': 'commission_rate',
      'vendor_radius': 'service_radius',
      'vendor_average_response_time': 'average_response_time',
      'vendor_suspension_reason': 'suspension_reason',
      'vendor_approved_at': 'approved_at'
    };

    Object.entries(fieldMapping).forEach(([inputId, fieldName]) => {
      const el = $(inputId);
      if (el) {
        fd.append(fieldName, el.value || '');
      }
    });

    // Checkboxes
    const checkboxes = {
      'vendor_is_branch': 'is_branch',
      'vendor_accepts_online_booking': 'accepts_online_booking',
      'vendor_is_verified': 'is_verified',
      'vendor_is_featured': 'is_featured'
    };

    Object.entries(checkboxes).forEach(([checkboxId, fieldName]) => {
      const el = $(checkboxId);
      if (el) {
        fd.append(fieldName, el.checked ? '1' : '0');
      }
    });

    // Inherit settings (if they exist)
    ['inherit_settings', 'inherit_products', 'inherit_commission'].forEach(field => {
      const el = $(field);
      if (el) {
        fd.append(field, el.checked ? '1' : '0');
      }
    });

    // Parent vendor
    if ($('vendor_parent_id')) {
      fd.append('parent_vendor_id', $('vendor_parent_id').value || '');
    }

    // Status (admin only)
    if (IS_ADMIN && $('vendor_status')) {
      fd.append('status', $('vendor_status').value || 'pending');
    }

    // Files
    ['logo', 'cover', 'banner'].forEach(type => {
      const input = $(`vendor_${type}`);
      if (input && input.files && input.files[0]) {
        fd.append(`vendor_${type}`, input.files[0]);
      }
    });

    // Translations
    const translations = collectTranslations();
    if (Object.keys(translations).length > 0) {
      fd.append('translations', JSON.stringify(translations));
    }

    if (DEBUG) {
      const debugData = {};
      for (const [key, value] of fd.entries()) {
        debugData[key] = value instanceof File ? `[File: ${value.name}]` : value;
      }
      console.log('FormData:', debugData);
    }

    return fd;
  }

  // ---------- Save Vendor ----------
  async function saveVendor() {
    setError('');
    clearFieldErrors();

    // Client-side validation
    const requiredFields = ['vendor_store_name', 'vendor_email', 'vendor_phone', 'vendor_country'];
    const clientErrors = {};
    
    requiredFields.forEach(fieldId => {
      const el = $(fieldId);
      if (el && !el.value.trim()) {
        const fieldName = fieldId.replace('vendor_', '');
        clientErrors[fieldName] = [t('field_required', 'This field is required')];
      }
    });
    
    if (Object.keys(clientErrors).length > 0) {
      showFieldErrors(clientErrors);
      setError(t('fix_errors', 'Please fix the errors below'));
      return;
    }

    const fd = collectFormData();

    try {
      const response = await postFormData(fd);
      
      if (!response.success) {
        if (response.errors) {
          showFieldErrors(response.errors);
        }
        setError(response.message || t('save_failed', 'Save failed'));
        return;
      }
      
      resetForm();
      await loadList();
      showNotification(t('saved', 'Vendor saved successfully'), 'success');
      
    } catch (error) {
      log('Save error:', error);
      
      if (error.status === 422 && error.body && error.body.errors) {
        showFieldErrors(error.body.errors);
        setError(error.body.message || t('validation_failed', 'Validation failed'));
        return;
      }
      
      if (error.status === 403) {
        showNotification(t('forbidden', 'Forbidden - session expired or no permission'), 'error');
        return;
      }
      
      showNotification(t('network_error', 'Network or server error'), 'error');
    }
  }

  // ---------- Reset Form ----------
  function resetForm() {
    if (!refs.form) return;
    
    refs.form.reset();
    
    if ($('vendor_id')) $('vendor_id').value = '0';
    
    if (refs.translationsArea) {
      refs.translationsArea.innerHTML = '';
      addTranslationPanel(PREF_LANG, AVAILABLE_LANGS.find(l => l.code === PREF_LANG)?.name || PREF_LANG);
    }
    
    // Clear image previews
    ['preview_logo', 'preview_cover', 'preview_banner'].forEach(id => {
      const el = $(id);
      if (el) el.innerHTML = '';
    });
    
    // Clear file inputs
    ['logo', 'cover', 'banner'].forEach(type => {
      const input = $(`vendor_${type}`);
      if (input) input.value = '';
    });
    
    // Hide parent vendor field
    if (refs.parentWrap) refs.parentWrap.style.display = 'none';
    if (refs.isBranchCheckbox) refs.isBranchCheckbox.checked = false;
    
    // Clear errors
    clearFieldErrors();
    setError('');
    
    // Update form title
    if (refs.formTitle) refs.formTitle.textContent = t('create_vendor', 'Create New Vendor');
    
    // Scroll to form
    if (refs.formSection) {
      refs.formSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
  }

  // ---------- Edit Vendor ----------
  async function openEdit(id) {
    setError('');
    clearFieldErrors();
    
    try {
      const response = await fetchJson(`${API}?_fetch_row=1&id=${encodeURIComponent(id)}`);
      
      if (!response.success || !response.data) {
        showNotification(response?.message || t('load_failed', 'Failed to load vendor'), 'error');
        return;
      }
      
      const vendor = response.data;
      
      // Set vendor ID
      if ($('vendor_id')) $('vendor_id').value = vendor.id || '0';
      
      // Fill basic fields
      const fieldMap = {
        'store_name': 'vendor_store_name',
        'slug': 'vendor_slug',
        'vendor_type': 'vendor_type',
        'store_type': 'vendor_store_type',
        'branch_code': 'vendor_branch_code',
        'phone': 'vendor_phone',
        'mobile': 'vendor_mobile',
        'email': 'vendor_email',
        'website_url': 'vendor_website',
        'registration_number': 'vendor_registration_number',
        'tax_number': 'vendor_tax_number',
        'postal_code': 'vendor_postal',
        'address': 'vendor_address',
        'latitude': 'vendor_latitude',
        'longitude': 'vendor_longitude',
        'commission_rate': 'vendor_commission',
        'service_radius': 'vendor_radius',
        'average_response_time': 'vendor_average_response_time',
        'suspension_reason': 'vendor_suspension_reason',
        'approved_at': 'vendor_approved_at'
      };
      
      Object.entries(fieldMap).forEach(([vendorKey, fieldId]) => {
        const el = $(fieldId);
        if (el && vendor[vendorKey] !== undefined) {
          el.value = vendor[vendorKey] || '';
        }
      });
      
      // Set checkboxes
      if ($('vendor_is_branch')) $('vendor_is_branch').checked = !!vendor.is_branch;
      if ($('vendor_accepts_online_booking')) $('vendor_accepts_online_booking').checked = !!vendor.accepts_online_booking;
      
      // Set inherit checkboxes if they exist
      ['inherit_settings', 'inherit_products', 'inherit_commission'].forEach(field => {
        const el = $(field);
        if (el && vendor[field] !== undefined) {
          el.checked = !!vendor[field];
        }
      });
      
      // Toggle parent vendor field based on is_branch
      toggleParentVendorField();
      
      // Load countries and cities
      await loadCountries('vendor_country', vendor.country_id || '');
      if (vendor.country_id) {
        await loadCities(vendor.country_id, 'vendor_city', vendor.city_id || '');
      }
      
      // Load parent vendors and select if exists
      await loadParentVendors(vendor.id);
      if (vendor.parent_vendor_id && $('vendor_parent_id')) {
        $('vendor_parent_id').value = vendor.parent_vendor_id;
      }
      
      // Load translations
      if (refs.translationsArea) {
        refs.translationsArea.innerHTML = '';
        
        if (vendor.translations && Object.keys(vendor.translations).length > 0) {
          Object.entries(vendor.translations).forEach(([lang, data]) => {
            const panel = addTranslationPanel(lang, lang.toUpperCase());
            if (panel && data) {
              if (data.description) panel.querySelector(`[name="translations[${lang}][description]"]`).value = data.description;
              if (data.return_policy) panel.querySelector(`[name="translations[${lang}][return_policy]"]`).value = data.return_policy;
              if (data.shipping_policy) panel.querySelector(`[name="translations[${lang}][shipping_policy]"]`).value = data.shipping_policy;
              if (data.meta_title) panel.querySelector(`[name="translations[${lang}][meta_title]"]`).value = data.meta_title;
              if (data.meta_description) panel.querySelector(`[name="translations[${lang}][meta_description]"]`).value = data.meta_description;
            }
          });
        } else {
          addTranslationPanel(PREF_LANG, AVAILABLE_LANGS.find(l => l.code === PREF_LANG)?.name || PREF_LANG);
        }
      }
      
      // Admin only fields
      if (IS_ADMIN) {
        if ($('vendor_status')) $('vendor_status').value = vendor.status || 'pending';
        if ($('vendor_is_verified')) $('vendor_is_verified').checked = !!vendor.is_verified;
        if ($('vendor_is_featured')) $('vendor_is_featured').checked = !!vendor.is_featured;
      }
      
      // Load image previews
      if (vendor.logo_url) previewImage('preview_logo', vendor.logo_url);
      if (vendor.cover_image_url) previewImage('preview_cover', vendor.cover_image_url);
      if (vendor.banner_url) previewImage('preview_banner', vendor.banner_url);
      
      // Update form title
      if (refs.formTitle) {
        refs.formTitle.textContent = t('edit_vendor', 'Edit Vendor') + ': ' + escapeHtml(vendor.store_name || '');
      }
      
      // Scroll to form
      if (refs.formSection) {
        refs.formSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }
      
    } catch (error) {
      log('Edit error:', error);
      
      if (error.status === 403) {
        showNotification(t('forbidden', 'Forbidden - session expired or no permission'), 'error');
      } else {
        showNotification(t('error_loading', 'Error loading vendor data'), 'error');
      }
    }
  }

  // ---------- Delete Vendor ----------
  async function doDelete(id) {
    if (!confirm(t('confirm_delete', 'Are you sure you want to delete vendor #') + id + '?')) {
      return;
    }
    
    try {
      const fd = new FormData();
      fd.append('action', 'delete');
      fd.append('id', id);
      
      const response = await postFormData(fd);
      
      if (!response.success) {
        showNotification(response?.message || t('delete_failed', 'Delete failed'), 'error');
        return;
      }
      
      resetForm();
      await loadList();
      showNotification(t('deleted', 'Vendor deleted successfully'), 'success');
      
    } catch (error) {
      log('Delete error:', error);
      showNotification(t('network_error', 'Network error'), 'error');
    }
  }

  // ---------- Toggle Verification ----------
  async function toggleVerify(id, value) {
    if (!confirm(t('confirm_toggle_verify', 'Toggle verification status?'))) {
      return;
    }
    
    try {
      const fd = new FormData();
      fd.append('action', 'toggle_verify');
      fd.append('id', id);
      fd.append('value', value);
      
      const response = await postFormData(fd);
      
      if (!response.success) {
        showNotification(response?.message || t('toggle_failed', 'Toggle failed'), 'error');
        return;
      }
      
      await loadList();
      showNotification(t('updated', 'Verification status updated'), 'success');
      
    } catch (error) {
      log('Toggle verify error:', error);
      showNotification(t('network_error', 'Network error'), 'error');
    }
  }

  // ---------- Load Filter Countries ----------
  async function loadFilterCountries() {
    if (!refs.filterCountry) return;
    
    refs.filterCountry.innerHTML = `<option value="">-- ${t('all_countries', 'All Countries')} --</option>`;
    
    try {
      const response = await fetchJson(`${COUNTRIES_API}?lang=${encodeURIComponent(PREF_LANG)}&scope=all`);
      const countries = response.data || response || [];
      
      countries.forEach(c => {
        const opt = document.createElement('option');
        opt.value = c.id;
        opt.textContent = c.name + (c.iso2 ? ` (${c.iso2})` : '');
        refs.filterCountry.appendChild(opt);
      });
      
      log(`Loaded ${countries.length} filter countries`);
    } catch (err) {
      log('loadFilterCountries error:', err);
    }
  }

  // ---------- Load Filter Cities ----------
  async function loadFilterCities(countryId) {
    if (!refs.filterCity) return;
    
    if (!countryId) {
      refs.filterCity.innerHTML = `<option value="">-- ${t('all_cities', 'All Cities')} --</option>`;
      return;
    }
    
    refs.filterCity.innerHTML = `<option value="">${t('loading_cities', 'Loading cities...')}</option>`;
    
    try {
      const response = await fetchJson(`${CITIES_API}?country_id=${encodeURIComponent(countryId)}&lang=${encodeURIComponent(PREF_LANG)}&scope=all`);
      const cities = response.data || response || [];
      
      refs.filterCity.innerHTML = `<option value="">-- ${t('all_cities', 'All Cities')} --</option>`;
      cities.forEach(c => {
        const opt = document.createElement('option');
        opt.value = c.id;
        opt.textContent = c.name;
        refs.filterCity.appendChild(opt);
      });
      
      log(`Loaded ${cities.length} filter cities for country ${countryId}`);
    } catch (err) {
      refs.filterCity.innerHTML = `<option value="">-- ${t('all_cities', 'All Cities')} --</option>`;
      log('loadFilterCities error:', err);
    }
  }

  // ---------- Load Vendors List ----------
  async function loadList() {
    if (!refs.tbody) return;
    
    refs.tbody.innerHTML = `<tr><td colspan="7" style="text-align:center;color:#6b7280;padding:40px;">${t('loading', 'Loading...')}</td></tr>`;
    
    try {
      const params = new URLSearchParams();
      
      // Search
      if (refs.vendorSearch && refs.vendorSearch.value.trim()) {
        params.append('search', refs.vendorSearch.value.trim());
      }
      
      // Advanced filters
      if (refs.filterStatus && refs.filterStatus.value) {
        params.append('status', refs.filterStatus.value);
      }
      
      if (refs.filterVerified && refs.filterVerified.value) {
        params.append('is_verified', refs.filterVerified.value);
      }
      
      if (refs.filterCountry && refs.filterCountry.value) {
        params.append('country_id', refs.filterCountry.value);
      }
      
      if (refs.filterCity && refs.filterCity.value) {
        params.append('city_id', refs.filterCity.value);
      }
      
      if (refs.filterPhone && refs.filterPhone.value) {
        params.append('phone', refs.filterPhone.value);
      }
      
      if (refs.filterEmail && refs.filterEmail.value) {
        params.append('email', refs.filterEmail.value);
      }
      
      const url = API + (params.toString() ? '?' + params.toString() : '');
      log('Loading list from:', url);
      
      const response = await fetchJson(url);
      const rows = response.data || [];
      const total = response.total || rows.length;
      
      if (refs.vendorsCount) {
        refs.vendorsCount.textContent = total;
      }
      
      renderTable(rows);
      
    } catch (error) {
      log('Load list error:', error);
      refs.tbody.innerHTML = `<tr><td colspan="7" style="text-align:center;color:#b91c1c;padding:40px;">${t('error_loading', 'Error loading data')}</td></tr>`;
    }
  }

  // ---------- Render Table ----------
  function renderTable(rows) {
    if (!refs.tbody) return;
    
    if (!rows || rows.length === 0) {
      refs.tbody.innerHTML = `<tr><td colspan="7" style="text-align:center;color:#6b7280;padding:40px;">${t('no_vendors', 'No vendors found')}</td></tr>`;
      return;
    }
    
    refs.tbody.innerHTML = '';
    
    rows.forEach(vendor => {
      const tr = document.createElement('tr');
      tr.style.cssText = 'border-bottom: 1px solid #e5e7eb;';
      
      tr.innerHTML = `
        <td style="padding: 12px; text-align: center;">${escapeHtml(vendor.id)}</td>
        <td style="padding: 12px;">
          <div style="font-weight: 600; color: #111827;">${escapeHtml(vendor.store_name)}</div>
          <div style="font-size: 12px; color: #6b7280; margin-top: 4px;">${escapeHtml(vendor.slug || '')}</div>
        </td>
        <td style="padding: 12px;">${escapeHtml(vendor.email || '')}</td>
        <td style="padding: 12px;">
          <span style="background: #f3f4f6; padding: 4px 8px; border-radius: 4px; font-size: 12px;">
            ${escapeHtml(vendor.vendor_type || '')}
          </span>
        </td>
        <td style="padding: 12px;">
          <span class="status-badge status-${vendor.status || 'pending'}">
            ${escapeHtml(vendor.status || 'pending')}
          </span>
        </td>
        <td style="padding: 12px; text-align: center;">
          ${vendor.is_verified ? 
            '<span style="color: #10b981; font-weight: 600;">✓ ' + t('yes', 'Yes') + '</span>' : 
            '<span style="color: #6b7280;">✗ ' + t('no', 'No') + '</span>'}
        </td>
        <td style="padding: 12px;">
          <button class="btn editBtn" data-id="${escapeHtml(vendor.id)}" style="margin-right: 8px;">
            ${t('edit', 'Edit')}
          </button>
          <button class="btn danger delBtn" data-id="${escapeHtml(vendor.id)}" style="margin-right: 8px;">
            ${t('delete', 'Delete')}
          </button>
          ${IS_ADMIN ? `
            <button class="btn small verifyBtn" data-id="${escapeHtml(vendor.id)}" data-ver="${vendor.is_verified ? 0 : 1}">
              ${vendor.is_verified ? t('unverify', 'Unverify') : t('verify', 'Verify')}
            </button>
          ` : ''}
        </td>
      `;
      
      refs.tbody.appendChild(tr);
    });
    
    // Add event listeners to buttons
    refs.tbody.querySelectorAll('.editBtn').forEach(btn => {
      btn.addEventListener('click', (e) => {
        e.preventDefault();
        openEdit(btn.dataset.id);
      });
    });
    
    refs.tbody.querySelectorAll('.delBtn').forEach(btn => {
      btn.addEventListener('click', (e) => {
        e.preventDefault();
        doDelete(btn.dataset.id);
      });
    });
    
    refs.tbody.querySelectorAll('.verifyBtn').forEach(btn => {
      btn.addEventListener('click', (e) => {
        e.preventDefault();
        toggleVerify(btn.dataset.id, btn.dataset.ver);
      });
    });
  }

  // ---------- Wire UI Events ----------
  function wireUI() {
    // Search
    if (refs.vendorSearch) {
      let searchTimer;
      refs.vendorSearch.addEventListener('input', () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(loadList, 300);
      });
    }
    
    // Refresh button
    if (refs.vendorRefresh) {
      refs.vendorRefresh.addEventListener('click', loadList);
    }
    
    // New vendor button
    if (refs.vendorNewBtn) {
      refs.vendorNewBtn.addEventListener('click', () => {
        resetForm();
        if (refs.formTitle) {
          refs.formTitle.textContent = t('create_vendor', 'Create New Vendor');
        }
        loadParentVendors();
      });
    }
    
    // Save button
    if (refs.saveBtn) {
      refs.saveBtn.addEventListener('click', saveVendor);
    }
    
    // Reset button
    if (refs.resetBtn) {
      refs.resetBtn.addEventListener('click', resetForm);
    }
    
    // Add language button
    if (refs.addLangBtn) {
      refs.addLangBtn.addEventListener('click', () => addTranslationPanel('', ''));
    }
    
    // Get coordinates button
    if (refs.btnGetCoords) {
      refs.btnGetCoords.addEventListener('click', () => {
        if (!navigator.geolocation) {
          showNotification(t('geolocation_not_supported', 'Geolocation is not supported by your browser'), 'error');
          return;
        }
        
        setError(t('getting_location', 'Getting current location...'));
        
        navigator.geolocation.getCurrentPosition(
          (position) => {
            if ($('vendor_latitude')) {
              $('vendor_latitude').value = position.coords.latitude.toFixed(7);
            }
            if ($('vendor_longitude')) {
              $('vendor_longitude').value = position.coords.longitude.toFixed(7);
            }
            setError('');
            showNotification(t('location_updated', 'Location updated'), 'success');
          },
          (error) => {
            setError(t('location_error', 'Could not get location: ') + error.message);
          },
          {
            enableHighAccuracy: false,
            timeout: 10000,
            maximumAge: 60000
          }
        );
      });
    }
    
    // Is branch checkbox
    if (refs.isBranchCheckbox) {
      refs.isBranchCheckbox.addEventListener('change', toggleParentVendorField);
    }
    
    // Country change (for cities)
    if ($('vendor_country')) {
      $('vendor_country').addEventListener('change', function() {
        loadCities(this.value, 'vendor_city');
      });
    }
    
    // Filter country change
    if (refs.filterCountry) {
      refs.filterCountry.addEventListener('change', function() {
        loadFilterCities(this.value);
      });
    }
    
    // Clear filters button
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
    
    // Filter change events
    const filters = ['filterStatus', 'filterVerified', 'filterCountry', 'filterCity', 'filterPhone', 'filterEmail'];
    filters.forEach(filterName => {
      if (refs[filterName]) {
        refs[filterName].addEventListener('change', loadList);
      }
    });
    
    // Apply language direction
    if (LANG_DIRECTION === 'rtl') {
      document.documentElement.dir = 'rtl';
      if (refs.form) refs.form.style.direction = 'rtl';
    } else {
      document.documentElement.dir = 'ltr';
      if (refs.form) refs.form.style.direction = 'ltr';
    }
  }

  // ---------- Initialize ----------
  (async function init() {
    log('Initializing vendors management...');
    
    // Wire UI events
    wireUI();
    
    // Load initial data
    await loadCountries('vendor_country');
    await loadFilterCountries();
    
    // Reset form (creates default translation panel)
    resetForm();
    
    // Load vendors list
    await loadList();
    
    // Expose functions for debugging
    if (DEBUG) {
      window.vendorsAdmin = {
        loadList,
        resetForm,
        collectFormData: () => {
          const fd = collectFormData();
          const data = {};
          for (const [key, value] of fd.entries()) {
            data[key] = value instanceof File ? `[File: ${value.name}]` : value;
          }
          return data;
        }
      };
    }
    
    showNotification(t('ready', 'Vendors management ready'), 'info', 2000);
    
  })();

  // ---------- Expose save function globally (optional) ----------
  window.saveVendor = saveVendor;

})();
