// admin/assets/js/pages/vendors.js
// Complete Vendors admin UI script — final 100% version
// - Robust CSRF handling (fetches current_user & CSRF before mutating requests)
// - Ensures country_id and city_id are always appended to FormData
// - Shows per-field validation errors and general errors
// - Retries once on 403 after refreshing CSRF
// - Loads countries, cities, parent vendors; image preview/processing; translations panel
// - Matches admin/fragments/vendors.php element IDs

(function () {
  'use strict';

  const API = '/api/vendors.php';
  const COUNTRIES_API = '/api/helpers/countries.php';
  const CITIES_API = '/api/helpers/cities.php';

  // Runtime state
  let CSRF = window.CSRF_TOKEN || '';
  let CURRENT = window.CURRENT_USER || {};
  const LANGS = window.AVAILABLE_LANGUAGES || [{ code: 'en', name: 'English', strings: {} }];
  const PREF_LANG = window.ADMIN_LANG || (CURRENT.preferred_language || 'en');
  const IS_ADMIN = !!(CURRENT.role_id && Number(CURRENT.role_id) === 1);

  // Short helpers
  const $ = id => document.getElementById(id);
  const log = (...args) => console.log('[vendors.js]', ...args);

  // DOM refs
  const tbody = $('vendorsTbody');
  const vendorsCount = $('vendorsCount');
  const vendorSearch = $('vendorSearch');
  const vendorRefresh = $('vendorRefresh');
  const vendorNewBtn = $('vendorNewBtn');

  const form = $('vendorForm');
  const formTitle = $('vendorFormTitle');
  const saveBtn = $('vendorSaveBtn');
  const resetBtn = $('vendorResetBtn');
  const errorsBox = $('vendorFormErrors');

  const translationsArea = $('vendor_translations_area');
  const addLangBtn = $('vendorAddLangBtn');

  const parentWrap = $('parentVendorWrap');

  const previewLogo = $('preview_logo');
  const previewCover = $('preview_cover');
  const previewBanner = $('preview_banner');

  const logoInput = $('vendor_logo');
  const coverInput = $('vendor_cover');
  const bannerInput = $('vendor_banner');

  // i18n strings (optional)
  let STRINGS = {};
  (function loadStrings() {
    const lang = LANGS.find(l => l.code === PREF_LANG) || LANGS[0];
    STRINGS = lang.strings || {};
  })();
  function t(k, fallback) { return STRINGS[k] || fallback || k; }

  // Fetch wrapper returning parsed JSON or throwing {status, body}
  async function fetchJson(url, opts = {}) {
    opts.credentials = 'include';
    try {
      const res = await fetch(url, opts);
      let data = null;
      try { data = await res.json(); } catch (e) { data = null; }
      if (!res.ok) throw { status: res.status, body: data };
      return data;
    } catch (err) {
      throw err;
    }
  }

  // Get current user + CSRF token from API
  async function fetchCurrentUserAndCsrf() {
    try {
      const j = await fetchJson(API + '?action=current_user');
      CSRF = j.csrf_token || CSRF || '';
      CURRENT = j.user || CURRENT || {};
      // update globals for other scripts
      window.CSRF_TOKEN = CSRF;
      window.CURRENT_USER = CURRENT;
      return { csrf: CSRF, user: CURRENT };
    } catch (e) {
      log('fetchCurrentUserAndCsrf failed', e);
      return { csrf: CSRF, user: CURRENT };
    }
  }

  // POST FormData with CSRF header + field. Ensures latest CSRF is used.
  async function postFormData(fd) {
    if (!fd) fd = new FormData();
    // ensure latest CSRF
    await fetchCurrentUserAndCsrf();
    fd.set('csrf_token', CSRF || '');
    const headers = { 'X-CSRF-Token': CSRF || '' };
    try {
      const res = await fetch(API, { method: 'POST', body: fd, credentials: 'include', headers });
      let json = null;
      try { json = await res.json(); } catch (e) { json = null; }
      if (!res.ok) throw { status: res.status, body: json };
      return json;
    } catch (err) {
      throw err;
    }
  }

  // Error UI helpers
  function setError(msg) {
    if (!errorsBox) return;
    if (!msg) { errorsBox.style.display = 'none'; errorsBox.textContent = ''; return; }
    errorsBox.style.display = 'block';
    errorsBox.textContent = msg;
  }

  function clearFieldErrors() {
    if (!form) return;
    form.querySelectorAll('.field-error').forEach(e => e.remove());
    form.querySelectorAll('.field-invalid').forEach(e => e.classList.remove('field-invalid'));
  }

  function showFieldErrors(errors) {
    clearFieldErrors();
    if (!form) return;
    for (const key in errors) {
      const msgs = errors[key];
      const idMap = {
        store_name: 'vendor_store_name',
        slug: 'vendor_slug',
        phone: 'vendor_phone',
        email: 'vendor_email',
        country_id: 'vendor_country',
        city_id: 'vendor_city',
        parent_vendor_id: 'vendor_parent_id'
      };
      const elId = idMap[key] || ('vendor_' + key);
      const el = $(elId);
      const message = Array.isArray(msgs) ? msgs.join(', ') : String(msgs);
      if (el) {
        el.classList.add('field-invalid');
        const span = document.createElement('div');
        span.className = 'field-error';
        span.style.color = '#b91c1c';
        span.style.fontSize = '13px';
        span.style.marginTop = '4px';
        span.textContent = message;
        el.parentNode.insertBefore(span, el.nextSibling);
      } else {
        setError((errorsBox.textContent ? errorsBox.textContent + ' | ' : '') + `${key}: ${message}`);
      }
    }
  }

  // ---------------- Countries / Cities / Parents ----------------
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
    } catch (e) {
      sel.innerHTML = `<option value="">${t('failed_load','Failed to load')}</option>`;
      log('loadCountries error', e);
    }
  }

  async function loadCities(countryId, selectedId = '') {
    const sel = $('vendor_city');
    if (!sel) return;
    if (!countryId) { sel.innerHTML = `<option value="">${t('select_country_first','Select country first')}</option>`; return; }
    sel.innerHTML = `<option value="">${t('loading_cities','Loading cities...')}</option>`;
    try {
      const data = await fetchJson(CITIES_API + '?country_id=' + encodeURIComponent(countryId));
      sel.innerHTML = `<option value="">-- ${t('select_city','select city')} --</option>`;
      (data || []).forEach(c => {
        const opt = document.createElement('option');
        opt.value = c.id;
        opt.textContent = c.name;
        sel.appendChild(opt);
      });
      if (selectedId) sel.value = selectedId;
    } catch (e) {
      sel.innerHTML = `<option value="">${t('failed_load','Failed to load')}</option>`;
      log('loadCities error', e);
    }
  }

  if ($('vendor_country')) $('vendor_country').addEventListener('change', function () { loadCities(this.value); });

  async function loadParentVendors(excludeId = 0) {
    const sel = $('vendor_parent_id');
    if (!sel) return;
    sel.innerHTML = `<option value="">${t('loading_parents','Loading...')}</option>`;
    try {
      const j = await fetchJson(API + '?parents=1');
      const rows = j.data || [];
      sel.innerHTML = `<option value="">-- ${t('select_parent','select parent')} --</option>`;
      rows.forEach(v => {
        if (excludeId && Number(excludeId) === Number(v.id)) return;
        const opt = document.createElement('option');
        opt.value = v.id;
        opt.textContent = v.store_name + (v.slug ? ` (${v.slug})` : '');
        sel.appendChild(opt);
      });
    } catch (e) {
      sel.innerHTML = `<option value="">${t('failed_load','Failed to load')}</option>`;
      log('loadParentVendors error', e);
    }
  }

  if ($('vendor_is_branch') && parentWrap) {
    $('vendor_is_branch').addEventListener('change', function () {
      parentWrap.style.display = this.checked ? 'block' : 'none';
      if (this.checked) loadParentVendors(Number($('vendor_id')?.value || 0));
    });
  }

  // ---------------- Image processing & preview ----------------
  function processImageFile(file, targetW, targetH, quality = 0.86) {
    return new Promise((resolve, reject) => {
      if (!file || !file.type || !file.type.startsWith('image/')) return resolve(file);
      const img = new Image();
      const fr = new FileReader();
      fr.onload = e => img.src = e.target.result;
      img.onload = () => {
        const canvas = document.createElement('canvas');
        canvas.width = targetW;
        canvas.height = targetH;
        const ctx = canvas.getContext('2d');
        const sw = img.width, sh = img.height;
        const tr = targetW / targetH, sr = sw / sh;
        let sx, sy, sW, sH;
        if (sr > tr) { sH = sh; sW = Math.round(sh * tr); sx = Math.round((sw - sW) / 2); sy = 0; }
        else { sW = sw; sH = Math.round(sw / tr); sx = 0; sy = Math.round((sh - sH) / 2); }
        ctx.drawImage(img, sx, sy, sW, sH, 0, 0, targetW, targetH);
        canvas.toBlob(blob => {
          if (!blob) return resolve(file);
          const out = new File([blob], (file.name.replace(/\.[^/.]+$/, '')) + '.jpg', { type: 'image/jpeg' });
          resolve(out);
        }, 'image/jpeg', quality);
      };
      fr.onerror = reject;
      fr.readAsDataURL(file);
    });
  }

  function previewImage(container, file, maxW = 240) {
    if (!container) return;
    container.innerHTML = '';
    if (!file) return;
    const img = document.createElement('img');
    img.style.maxWidth = maxW + 'px';
    img.style.maxHeight = '160px';
    img.style.display = 'block';
    const fr = new FileReader();
    fr.onload = e => img.src = e.target.result;
    fr.readAsDataURL(file);
    container.appendChild(img);
  }

  if (logoInput) logoInput.addEventListener('change', async function () {
    const f = this.files[0]; if (!f) return; const p = await processImageFile(f, 600, 600, 0.86); previewImage(previewLogo, p, 120); this._processed = p;
  });
  if (coverInput) coverInput.addEventListener('change', async function () {
    const f = this.files[0]; if (!f) return; const p = await processImageFile(f, 1400, 787, 0.86); previewImage(previewCover, p, 240); this._processed = p;
  });
  if (bannerInput) bannerInput.addEventListener('change', async function () {
    const f = this.files[0]; if (!f) return; const p = await processImageFile(f, 1600, 400, 0.86); previewImage(previewBanner, p, 320); this._processed = p;
  });

  // ---------------- Translations panel ----------------
  function addTranslationPanel(code = '', name = '') {
    if (!translationsArea) return;
    if (!code) {
      code = prompt('Language code (e.g., ar)');
      if (!code) return;
    }
    if (translationsArea.querySelector('.tr-lang-panel[data-lang="'+code+'"]')) return;
    const panel = document.createElement('div');
    panel.className = 'tr-lang-panel';
    panel.dataset.lang = code;
    panel.style.border = '1px solid #eef2f7';
    panel.style.padding = '8px';
    panel.style.marginBottom = '8px';
    panel.innerHTML = `<div style="display:flex;justify-content:space-between;align-items:center;">
      <strong>${escapeHtml(name || code)} (${escapeHtml(code)})</strong>
      <div><button class="btn small toggle-lang">Collapse</button> <button class="btn small danger remove-lang">Remove</button></div>
    </div>
    <div style="margin-top:8px;">
      <label>Description <textarea class="tr-desc" rows="3" style="width:100%"></textarea></label>
      <label>Return policy <textarea class="tr-return" rows="2" style="width:100%"></textarea></label>
      <label>Shipping policy <textarea class="tr-shipping" rows="2" style="width:100%"></textarea></label>
      <label>Meta title <input class="tr-meta-title" style="width:100%"></label>
      <label>Meta description <input class="tr-meta-desc" style="width:100%"></label>
    </div>`;
    translationsArea.appendChild(panel);
    panel.querySelector('.remove-lang').addEventListener('click', () => panel.remove());
    panel.querySelector('.toggle-lang').addEventListener('click', () => {
      const body = panel.querySelector('div[style*="margin-top"]');
      if (!body) return;
      if (body.style.display === 'none') { body.style.display = 'block'; panel.querySelector('.toggle-lang').textContent = 'Collapse'; }
      else { body.style.display = 'none'; panel.querySelector('.toggle-lang').textContent = 'Open'; }
    });
  }

  if (addLangBtn) addLangBtn.addEventListener('click', () => addTranslationPanel('', ''));

  function collectTranslations() {
    const out = {};
    if (!translationsArea) return out;
    translationsArea.querySelectorAll('.tr-lang-panel').forEach(p => {
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

  // ---------------- Table list ----------------
  async function loadList() {
    if (!tbody) return;
    tbody.innerHTML = `<tr><td colspan="7" style="text-align:center;color:#6b7280;padding:18px;">${t('loading','Loading...')}</td></tr>`;
    try {
      const q = (vendorSearch?.value || '').trim();
      let url = API;
      if (q) url += '?search=' + encodeURIComponent(q);
      const j = await fetchJson(url);
      const rows = j.data || j.data?.data || j;
      const total = j.total ?? (Array.isArray(rows) ? rows.length : (rows ? 1 : 0));
      if (vendorsCount) vendorsCount.textContent = total;
      renderTable(Array.isArray(rows) ? rows : []);
    } catch (e) {
      log('loadList error', e);
      tbody.innerHTML = `<tr><td colspan="7" style="text-align:center;color:#b91c1c;padding:18px;">${t('error_loading','Error loading')}</td></tr>`;
    }
  }

  function renderTable(rows) {
    if (!tbody) return;
    if (!rows || rows.length === 0) { tbody.innerHTML = `<tr><td colspan="7" style="text-align:center;color:#6b7280;padding:18px;">${t('no_vendors','No vendors')}</td></tr>`; return; }
    tbody.innerHTML = '';
    rows.forEach(v => {
      const tr = document.createElement('tr');
      tr.innerHTML = `<td style="padding:8px;border-bottom:1px solid #eef2f7">${escapeHtml(String(v.id))}</td>
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
      tbody.appendChild(tr);
    });
    tbody.querySelectorAll('.editBtn').forEach(b => b.addEventListener('click', e => openEdit(b.dataset.id)));
    tbody.querySelectorAll('.delBtn').forEach(b => b.addEventListener('click', e => doDelete(b.dataset.id)));
    tbody.querySelectorAll('.verifyBtn').forEach(b => b.addEventListener('click', e => toggleVerify(b.dataset.id, b.dataset.ver)));
  }

  // ---------------- Collect & save ----------------
  function collectFormData() {
    const fd = new FormData();
    fd.append('action', 'save');
    fd.append('id', $('vendor_id')?.value || 0);

    const mapFields = [
      'store_name','slug','vendor_type','store_type','is_branch','parent_vendor_id','branch_code','inherit_settings','inherit_products','inherit_commission',
      'phone','mobile','email','website_url','registration_number','tax_number',
      // country_id and city_id appended explicitly below
      'postal_code','address','latitude','longitude','commission_rate','service_radius',
      'accepts_online_booking','average_response_time'
    ];
    mapFields.forEach(k => {
      const el = $('vendor_' + k);
      if (!el) return;
      if (el.type === 'checkbox') fd.append(k, el.checked ? '1' : '0');
      else fd.append(k, el.value || '');
    });

    // Append country & city explicitly (guarantee presence)
    const countryEl = $('vendor_country');
    const cityEl = $('vendor_city');
    const countryVal = countryEl ? (countryEl.value || '') : '';
    const cityVal = cityEl ? (cityEl.value || '') : '';
    fd.set('country_id', countryVal);
    fd.set('city_id', cityVal);

    // Admin-only
    if (IS_ADMIN) {
      const st = $('vendor_status'); if (st) fd.set('status', st.value || '');
      const v = $('vendor_is_verified'); if (v) fd.set('is_verified', v.checked ? '1' : '0');
      const f = $('vendor_is_featured'); if (f) fd.set('is_featured', f.checked ? '1' : '0');
    }

    // Files
    if (logoInput && logoInput._processed) fd.append('logo', logoInput._processed, logoInput._processed.name);
    else if (logoInput && logoInput.files[0]) fd.append('logo', logoInput.files[0]);

    if (coverInput && coverInput._processed) fd.append('cover', coverInput._processed, coverInput._processed.name);
    else if (coverInput && coverInput.files[0]) fd.append('cover', coverInput.files[0]);

    if (bannerInput && bannerInput._processed) fd.append('banner', bannerInput._processed, bannerInput._processed.name);
    else if (bannerInput && bannerInput.files[0]) fd.append('banner', bannerInput.files[0]);

    fd.set('translations', JSON.stringify(collectTranslations()));

    return fd;
  }

  // Save with client validation and retry on CSRF failure
  async function saveVendor() {
    setError('');
    clearFieldErrors();

    // client-side required checks
    const required = ['vendor_store_name','vendor_email','vendor_phone','vendor_country'];
    const clientErr = {};
    required.forEach(id => {
      const el = $(id);
      if (!el || !String(el.value || '').trim()) clientErr[id.replace('vendor_','')] = [`${id} required`];
    });
    if (Object.keys(clientErr).length) { showFieldErrors(clientErr); setError(t('fix_errors','Please fix errors')); return; }

    const fd = collectFormData();

    // DEBUG: log FormData entries for verification (remove when stable)
    try {
      const entries = {};
      for (const pair of fd.entries()) {
        if (pair[1] instanceof File) entries[pair[0]] = '[File] ' + (pair[1].name || '');
        else entries[pair[0]] = pair[1];
      }
      console.log('FormData to be sent:', entries);
    } catch (e) {
      console.log('Could not inspect FormData:', e);
    }

    // Ensure country_id present
    const c = fd.get('country_id') || '';
    if (!c) {
      showFieldErrors({ country_id: ['Country id is required'] });
      setError('Please select a country before saving.');
      const el = $('vendor_country'); if (el) el.focus();
      return;
    }

    try {
      const res = await postFormData(fd);
      if (!res || !res.success) {
        if (res && res.errors) { showFieldErrors(res.errors || {}); setError(res.message || t('validation_failed','Validation failed')); }
        else setError(res.message || t('save_failed','Save failed'));
        return;
      }
      resetForm();
      await loadList();
      alert(t('saved','Saved successfully'));
    } catch (err) {
      if (err && err.status === 403) {
        log('403 received, refreshing CSRF and retrying once');
        await fetchCurrentUserAndCsrf();
        fd.set('csrf_token', CSRF || '');
        try {
          const res2 = await postFormData(fd);
          if (!res2 || !res2.success) {
            if (res2 && res2.errors) { showFieldErrors(res2.errors || {}); setError(res2.message || t('validation_failed','Validation failed')); }
            else setError(res2.message || t('save_failed','Save failed'));
            return;
          }
          resetForm();
          await loadList();
          alert(t('saved','Saved successfully'));
          return;
        } catch (err2) {
          log('retry failed', err2);
          setError(t('forbidden_csrf','Forbidden or invalid CSRF token — please refresh and re-login if needed.'));
          return;
        }
      }

      if (err && err.status === 422 && err.body && err.body.errors) {
        showFieldErrors(err.body.errors);
        setError(err.body.message || t('validation_failed','Validation failed'));
        return;
      }

      log('saveVendor unexpected error', err);
      setError(t('network_error','Network or server error'));
    }
  }

  if (saveBtn) saveBtn.addEventListener('click', saveVendor);

  // ---------------- Reset / Edit / Delete ----------------
  function resetForm() {
    if (!form) return;
    form.reset();
    translationsArea && (translationsArea.innerHTML = '');
    addTranslationPanel && addTranslationPanel(PREF_LANG, LANGS.find(l=>l.code===PREF_LANG)?.name || PREF_LANG);
    $('vendor_id') && ($('vendor_id').value = 0);
    if (previewLogo) previewLogo.innerHTML = '';
    if (previewCover) previewCover.innerHTML = '';
    if (previewBanner) previewBanner.innerHTML = '';
    ['vendor_logo','vendor_cover','vendor_banner'].forEach(id => { const el = $(id); if (el) el._processed = null; });
    clearFieldErrors(); setError('');
    if (parentWrap) parentWrap.style.display = 'none';
    window.scrollTo({ top: $('vendorFormSection')?.offsetTop - 20 || 0, behavior: 'smooth' });
  }
  if (resetBtn) resetBtn.addEventListener('click', resetForm);

  async function openEdit(id) {
    setError('');
    clearFieldErrors();
    try {
      const j = await fetchJson(API + '?_fetch_row=1&id=' + encodeURIComponent(id) + '&with_stats=1');
      if (!j || !j.success) { setError(j?.message || t('load_failed','Load failed')); return; }
      const v = j.data;
      $('vendor_id') && ($('vendor_id').value = v.id || 0);
      const map = {
        store_name:'vendor_store_name', slug:'vendor_slug', vendor_type:'vendor_type', store_type:'vendor_store_type',
        phone:'vendor_phone', mobile:'vendor_mobile', email:'vendor_email', website_url:'vendor_website',
        registration_number:'vendor_registration_number', tax_number:'vendor_tax_number', postal_code:'vendor_postal',
        address:'vendor_address', latitude:'vendor_latitude', longitude:'vendor_longitude',
        commission_rate:'vendor_commission', service_radius:'vendor_radius'
      };
      Object.keys(map).forEach(k => { const el = $(map[k]); if (el) el.value = v[k] ?? ''; });

      if ($('vendor_is_branch')) { $('vendor_is_branch').checked = !!v.is_branch; parentWrap.style.display = v.is_branch ? 'block' : 'none'; }

      // load countries first, then set country + cities
      await loadCountries(v.country_id || '');
      if (v.country_id) {
        $('vendor_country') && ($('vendor_country').value = v.country_id);
        await loadCities(v.country_id, v.city_id || '');
      }

      translationsArea && (translationsArea.innerHTML = '');
      if (v.translations) {
        Object.keys(v.translations).forEach(lang => {
          addTranslationPanel(lang, lang);
          const panel = translationsArea.querySelector('.tr-lang-panel[data-lang="'+lang+'"]');
          if (panel) {
            panel.querySelector('.tr-desc') && (panel.querySelector('.tr-desc').value = v.translations[lang].description || '');
            panel.querySelector('.tr-return') && (panel.querySelector('.tr-return').value = v.translations[lang].return_policy || '');
            panel.querySelector('.tr-shipping') && (panel.querySelector('.tr-shipping').value = v.translations[lang].shipping_policy || '');
            panel.querySelector('.tr-meta-title') && (panel.querySelector('.tr-meta-title').value = v.translations[lang].meta_title || '');
            panel.querySelector('.tr-meta-desc') && (panel.querySelector('.tr-meta-desc').value = v.translations[lang].meta_description || '');
          }
        });
      } else addTranslationPanel(PREF_LANG, LANGS.find(l=>l.code===PREF_LANG)?.name || PREF_LANG);

      if (IS_ADMIN) {
        $('vendor_status') && ($('vendor_status').value = v.status || 'pending');
        $('vendor_is_verified') && ($('vendor_is_verified').checked = !!v.is_verified);
        $('vendor_is_featured') && ($('vendor_is_featured').checked = !!v.is_featured);
      }

      await loadParentVendors(v.id);
      if (v.parent_vendor_id && $('vendor_parent_id')) $('vendor_parent_id').value = v.parent_vendor_id;

      if (v.stats) log('Vendor stats', v.stats);

      window.scrollTo({ top: $('vendorFormSection')?.offsetTop - 20 || 0, behavior: 'smooth' });
    } catch (e) {
      if (e && e.status === 403) {
        setError(t('forbidden','Forbidden — session expired or no permission. Please refresh or login.'));
        await fetchCurrentUserAndCsrf();
      } else {
        log('openEdit error', e);
        setError(t('error_loading','Error loading vendor'));
      }
    }
  }

  function doDelete(id) {
    if (!confirm(t('confirm_delete','Delete vendor #') + id + '?')) return;
    const fd = new FormData(); fd.append('action','delete'); fd.append('id', id);
    postFormData(fd).then(j => { if (!j.success) alert(t('delete_failed','Delete failed: ') + (j.message || '')); else { resetForm(); loadList(); alert(t('deleted','Deleted')); } }).catch(e=>{ log('delete error', e); alert(t('network_error','Network error')); });
  }

  function toggleVerify(id, value) {
    if (!confirm(t('confirm_toggle_verify','Toggle verification?'))) return;
    const fd = new FormData(); fd.append('action','toggle_verify'); fd.append('id', id); fd.append('value', value);
    postFormData(fd).then(j => { if (!j.success) alert(t('failed','Failed: ') + (j.message || '')); else loadList(); }).catch(e=>{ log('toggle error', e); alert(t('network_error','Network error')); });
  }

  // Utility
  function escapeHtml(s) { return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

  // Init
  (async function init() {
    log('vendors.js init - refreshing current user & CSRF');
    await fetchCurrentUserAndCsrf();
    await loadCountries();
    resetForm();
    await loadList();

    if (vendorRefresh) vendorRefresh.addEventListener('click', loadList);
    if (vendorNewBtn) vendorNewBtn.addEventListener('click', () => { resetForm(); formTitle.textContent = t('create_edit','Create / Edit Vendor'); loadParentVendors(); });
    if (vendorSearch) vendorSearch.addEventListener('input', () => setTimeout(loadList, 300));
    addTranslationPanel && addTranslationPanel(PREF_LANG, LANGS.find(l=>l.code===PREF_LANG)?.name || PREF_LANG);
  })();

})();