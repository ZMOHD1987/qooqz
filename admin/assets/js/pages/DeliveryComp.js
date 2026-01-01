// admin/assets/js/pages/DeliveryCompany.js
// Complete admin client for Delivery Companies
// - Uses endpoints:
//   window.COUNTRIES_API -> /api/helpers/countries.php
//   window.CITIES_API    -> /api/helpers/cities.php
//   window.PARENTS_API   -> /api/routes/shipping.php?action=parents
//   window.API_BASE      -> /api/routes/shipping.php (list/get/create/update/delete)
// - Honors session language via window.ADMIN_LANG
// - Populates filters and form selects (countries, cities, parents) with translations
// - Supports CRUD and translations fieldset
(function () {
  'use strict';

  const API = (window.API_BASE && String(window.API_BASE).trim()) || '/api/routes/shipping.php';
  const COUNTRIES_API = (window.COUNTRIES_API && String(window.COUNTRIES_API).trim()) || '/api/helpers/countries.php';
  const CITIES_API = (window.CITIES_API && String(window.CITIES_API).trim()) || '/api/helpers/cities.php';
  const PARENTS_API = (window.PARENTS_API && String(window.PARENTS_API).trim()) || (API + '?action=parents');

  const CSRF = window.CSRF_TOKEN || '';
  const CURRENT = window.CURRENT_USER || {};
  const LANGS = window.AVAILABLE_LANGUAGES || [{ code: 'en', name: 'English', strings: {} }];
  const PREF_LANG = window.ADMIN_LANG || (CURRENT.preferred_language || CURRENT.lang || 'en');

  const $ = id => document.getElementById(id);
  const tbody = $('deliveryCompaniesTbody');
  const countEl = $('deliveryCompaniesCount');
  const searchInput = $('deliveryCompanySearch');
  const phoneFilter = $('deliveryCompanyFilterPhone');
  const emailFilter = $('deliveryCompanyFilterEmail');
  const countryFilter = $('deliveryCompanyFilterCountry');
  const cityFilter = $('deliveryCompanyFilterCity');
  const activeFilter = $('deliveryCompanyFilterActive');
  const refreshBtn = $('deliveryCompanyRefresh');
  const newBtn = $('deliveryCompanyNewBtn');

  const form = $('deliveryCompanyForm');
  const saveBtn = $('deliveryCompanySaveBtn');
  const resetBtn = $('deliveryCompanyResetBtn');
  const errorsBox = $('deliveryCompanyFormErrors');

  const translationsArea = $('deliveryCompany_translations_area');
  const addLangBtn = $('deliveryCompanyAddLangBtn');

  const previewLogo = $('preview_delivery_logo');
  const logoInput = $('delivery_company_logo');

  const parentSelect = $('delivery_company_parent');
  const countrySelect = $('delivery_company_country');
  const citySelect = $('delivery_company_city');

  // i18n strings (optional)
  let STRINGS = (LANGS.find(l => l.code === PREF_LANG) || LANGS[0]).strings || {};
  function t(k, d) { return STRINGS[k] || d || k; }

  // Fetch helpers
  async function fetchJson(url, opts = {}) {
    opts.credentials = opts.credentials || 'include';
    try {
      const res = await fetch(url, opts);
      const txt = await res.text();
      try { return JSON.parse(txt); } catch (e) { return txt; }
    } catch (err) { throw err; }
  }

  async function postFormData(fd, actionOverride) {
    try { if (actionOverride) fd.set('action', actionOverride); } catch (e) {}
    if (CSRF && !fd.get('csrf_token')) fd.set('csrf_token', CSRF);
    const res = await fetch(API, { method: 'POST', body: fd, credentials: 'include' });
    const txt = await res.text();
    try { return JSON.parse(txt); } catch (e) { return txt; }
  }

  // Load countries (for filter or form). uses lang param
  async function loadCountries(selected = '', forFilter = false) {
    const tgt = forFilter ? countryFilter : countrySelect;
    if (!tgt) return;
    tgt.innerHTML = `<option value="">${t('loading_countries','Loading...')}</option>`;
    try {
      const url = COUNTRIES_API + '?lang=' + encodeURIComponent(PREF_LANG);
      const j = await fetchJson(url);
      const rows = (j && j.success) ? j.data : (Array.isArray(j) ? j : []);
      tgt.innerHTML = `<option value="">-- ${t('select_country','select country')} --</option>`;
      rows.forEach(c => {
        const opt = document.createElement('option');
        opt.value = c.id;
        opt.textContent = (c.name || c.title || c.name_local || '') + (c.iso2 ? ` (${c.iso2})` : '');
        tgt.appendChild(opt);
      });
      if (selected) tgt.value = selected;
    } catch (e) {
      tgt.innerHTML = `<option value="">${t('failed_load','Failed to load')}</option>`;
      console.error('loadCountries error', e);
    }
  }

  // Load cities (for filter or form)
  async function loadCities(countryId, selected = '', forFilter = false) {
    const tgt = forFilter ? cityFilter : citySelect;
    if (!tgt) return;
    if (!countryId) { tgt.innerHTML = `<option value="">${t('select_country_first','Select country first')}</option>`; return; }
    tgt.innerHTML = `<option value="">${t('loading_cities','Loading cities...')}</option>`;
    try {
      const url = CITIES_API + '?country_id=' + encodeURIComponent(countryId) + '&lang=' + encodeURIComponent(PREF_LANG);
      const j = await fetchJson(url);
      const rows = (j && j.success) ? j.data : (Array.isArray(j) ? j : []);
      tgt.innerHTML = `<option value="">-- ${t('select_city','select city')} --</option>`;
      rows.forEach(ci => {
        const opt = document.createElement('option');
        opt.value = ci.id;
        opt.textContent = ci.name || ci.title || '';
        tgt.appendChild(opt);
      });
      if (selected) tgt.value = selected;
    } catch (e) {
      tgt.innerHTML = `<option value="">${t('failed_load','Failed to load')}</option>`;
      console.error('loadCities error', e);
    }
  }

  // Load parent companies
  async function loadParentCompanies(selected = '') {
    if (!parentSelect) return;
    parentSelect.innerHTML = `<option value="">${t('loading','Loading...')}</option>`;
    try {
      const url = PARENTS_API + '&lang=' + encodeURIComponent(PREF_LANG);
      const j = await fetchJson(url);
      const rows = (j && j.success) ? j.data : (Array.isArray(j) ? j : []);
      parentSelect.innerHTML = `<option value="">-- ${t('no_parent','No parent')} --</option>`;
      rows.forEach(r => {
        const opt = document.createElement('option');
        opt.value = r.id;
        opt.textContent = r.name || r.title || ('#' + r.id);
        parentSelect.appendChild(opt);
      });
      if (selected) parentSelect.value = selected;
    } catch (e) {
      parentSelect.innerHTML = `<option value="">${t('failed_load','Failed to load')}</option>`;
      console.error('loadParentCompanies error', e);
    }
  }

  // Logo preview
  if (logoInput) logoInput.addEventListener('change', function () {
    const f = this.files[0]; if (!f || !previewLogo) return;
    const fr = new FileReader(); fr.onload = e => { previewLogo.innerHTML = `<img src="${e.target.result}" style="max-height:80px">`; }; fr.readAsDataURL(f);
  });

  // Translations panels
  function addTranslationPanel(code = '', name = '') {
    if (!translationsArea) return;
    if (!code) code = prompt('Language code (e.g., ar)');
    if (!code) return;
    if (translationsArea.querySelector(`.tr-lang-panel[data-lang="${code}"]`)) return;
    const panel = document.createElement('div');
    panel.className = 'tr-lang-panel';
    panel.dataset.lang = code;
    panel.style.border = '1px solid #eef2f7';
    panel.style.padding = '8px';
    panel.style.marginBottom = '8px';
    panel.innerHTML = `<div style="display:flex;justify-content:space-between"><strong>${name||code} (${code})</strong><div><button class="btn small toggle">Collapse</button> <button class="btn small danger remove">Remove</button></div></div>
      <div class="body" style="margin-top:8px">
        <label>Description <textarea class="tr-desc" rows="3" style="width:100%"></textarea></label>
        <label>Terms <textarea class="tr-terms" rows="2" style="width:100%"></textarea></label>
      </div>`;
    translationsArea.appendChild(panel);
    panel.querySelector('.remove').addEventListener('click', ()=>panel.remove());
    panel.querySelector('.toggle').addEventListener('click', ()=> {
      const bd = panel.querySelector('.body'); bd.style.display = bd.style.display === 'none' ? 'block' : 'none';
    });
  }
  if (addLangBtn) addLangBtn.addEventListener('click', ()=>addTranslationPanel('', ''));

  function collectTranslations() {
    const out = {};
    if (!translationsArea) return out;
    translationsArea.querySelectorAll('.tr-lang-panel').forEach(p => {
      const code = p.dataset.lang;
      const desc = p.querySelector('.tr-desc')?.value || '';
      const terms = p.querySelector('.tr-terms')?.value || '';
      if (desc || terms) out[code] = { description: desc, terms: terms };
    });
    return out;
  }

  // Build list query for filters
  function buildListQuery() {
    const params = [];
    const qParts = [];
    const q = (searchInput?.value || '').trim();
    const phone = (phoneFilter?.value || '').trim();
    const email = (emailFilter?.value || '').trim();
    if (q) qParts.push(q);
    if (phone) qParts.push(phone);
    if (email) qParts.push(email);
    if (qParts.length) params.push('q=' + encodeURIComponent(qParts.join(' ')));
    const countryId = countryFilter?.value || '';
    if (countryId) params.push('country_id=' + encodeURIComponent(countryId));
    const cityId = cityFilter?.value || '';
    if (cityId) params.push('city_id=' + encodeURIComponent(cityId));
    const isActive = activeFilter?.value;
    if (typeof isActive !== 'undefined' && isActive !== '') params.push('is_active=' + encodeURIComponent(isActive));
    if (PREF_LANG) params.push('lang=' + encodeURIComponent(PREF_LANG));
    return params.length ? ('&' + params.join('&')) : '';
  }

  // Load list
  async function loadList() {
    if (!tbody) return;
    tbody.innerHTML = `<tr><td colspan="7" style="text-align:center;padding:18px">${t('loading','Loading...')}</td></tr>`;
    try {
      const q = buildListQuery();
      const url = API + '?action=list' + q;
      const j = await fetchJson(url);
      const rows = (j && j.success) ? j.data : (Array.isArray(j) ? j : []);
      const total = j && (typeof j.total !== 'undefined') ? j.total : (Array.isArray(rows) ? rows.length : 0);
      if (countEl) countEl.textContent = total;
      renderTable(rows || []);
    } catch (e) {
      console.error('loadList failed', e);
      tbody.innerHTML = `<tr><td colspan="7" style="text-align:center;padding:18px;color:#b91c1c">${t('error_loading','Error loading')}</td></tr>`;
    }
  }

  function renderTable(rows) {
    if (!tbody) return;
    if (!rows || rows.length === 0) { tbody.innerHTML = `<tr><td colspan="7" style="text-align:center;padding:18px">${t('no_companies','No companies')}</td></tr>`; return; }
    tbody.innerHTML = '';
    rows.forEach(r => {
      const countryCity = ((r.country_name || '') + (r.city_name ? (' / ' + r.city_name) : '')).trim();
      const isActiveLabel = r.is_active ? 'Active' : 'Inactive';
      const tr = document.createElement('tr');
      tr.innerHTML = `<td style="padding:8px;border-bottom:1px solid #eef2f7">${escapeHtml(r.id)}</td>
        <td style="padding:8px;border-bottom:1px solid #eef2f7">${escapeHtml(r.name || '')}</td>
        <td style="padding:8px;border-bottom:1px solid #eef2f7">${escapeHtml(r.email||'')}</td>
        <td style="padding:8px;border-bottom:1px solid #eef2f7">${escapeHtml(r.phone||'')}</td>
        <td style="padding:8px;border-bottom:1px solid #eef2f7">${escapeHtml(countryCity || '')}</td>
        <td style="padding:8px;border-bottom:1px solid #eef2f7" data-id="${escapeHtml(r.id)}">${isActiveLabel} <button class="btn small toggle-active" data-id="${escapeHtml(r.id)}" data-active="${r.is_active ? 1 : 0}">Toggle</button></td>
        <td style="padding:8px;border-bottom:1px solid #eef2f7">
          <button class="btn edit" data-id="${escapeHtml(r.id)}">Edit</button>
          <button class="btn danger del" data-id="${escapeHtml(r.id)}">Delete</button>
        </td>`;
      tbody.appendChild(tr);
    });
    Array.from(tbody.querySelectorAll('.edit')).forEach(b => b.addEventListener('click', e => openEdit(b.dataset.id)));
    Array.from(tbody.querySelectorAll('.del')).forEach(b => b.addEventListener('click', e => doDelete(b.dataset.id)));
    Array.from(tbody.querySelectorAll('.toggle-active')).forEach(b => b.addEventListener('click', e => toggleActive(b.dataset.id, b)));
  }

  // Toggle active
  async function toggleActive(id, btnEl) {
    if (!confirm('Toggle active for company #' + id + '?')) return;
    let current = parseInt(btnEl.getAttribute('data-active') || '0', 10);
    const newVal = current ? 0 : 1;
    const fd = new FormData();
    fd.set('id', id);
    fd.set('is_active', newVal ? '1' : '0');
    try {
      const res = await postFormData(fd, 'update_company');
      if (!res || !res.success) { alert(res?.message || 'Failed to update'); return; }
      await loadList();
    } catch (e) {
      console.error('toggleActive error', e);
      alert('Network or server error');
    }
  }

  // Open edit
  async function openEdit(id) {
    try {
      const j = await fetchJson(API + '?action=get&id=' + encodeURIComponent(id) + '&lang=' + encodeURIComponent(PREF_LANG));
      if (!j || !j.success) { alert(j?.message || 'Load failed'); return; }
      const v = j.data;
      setFieldValue('delivery_company_id', v.id || 0);
      setFieldValue('delivery_company_name', v.name || '');
      setFieldValue('delivery_company_slug', v.slug || '');
      setFieldValue('delivery_company_phone', v.phone || '');
      setFieldValue('delivery_company_email', v.email || '');
      setFieldValue('delivery_company_website', v.website_url || '');
      setFieldValue('delivery_company_api_url', v.api_url || '');
      setFieldValue('delivery_company_api_key', v.api_key || '');
      setFieldValue('delivery_company_tracking', v.tracking_url || '');
      setFieldValue('delivery_company_rating', (typeof v.rating_average !== 'undefined' ? Number(v.rating_average).toFixed(2) : '0.00'));
      // parent & countries & cities (cascade)
      await loadParentCompanies(v.parent_id || '');
      await loadCountries(v.country_id || '', false);
      if (v.country_id) { countrySelect.value = v.country_id; await loadCities(v.country_id, v.city_id || '', false); }
      if ($('delivery_company_is_active')) $('delivery_company_is_active').checked = !!v.is_active;
      translationsArea.innerHTML = '';
      if (v.translations) {
        Object.keys(v.translations).forEach(lang => {
          addTranslationPanel(lang, lang);
          const panel = translationsArea.querySelector(`.tr-lang-panel[data-lang="${lang}"]`);
          if (panel) {
            const descEl = panel.querySelector('.tr-desc');
            const termsEl = panel.querySelector('.tr-terms');
            if (descEl) descEl.value = v.translations[lang].description || '';
            if (termsEl) termsEl.value = v.translations[lang].terms || '';
          }
        });
      } else addTranslationPanel(PREF_LANG, (LANGS.find(l=>l.code===PREF_LANG) || {}).name || PREF_LANG);
      if (v.logo_url && previewLogo) previewLogo.innerHTML = `<img src="${v.logo_url}" style="max-height:80px">`;
      const formSection = $('deliveryCompanyFormSection');
      if (formSection && formSection.scrollIntoView) formSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
    } catch (e) {
      console.error('openEdit error', e);
      alert('Error loading company');
    }
  }

  function setFieldValue(id, value) {
    const el = $(id);
    if (!el) return;
    if (el.type === 'checkbox') el.checked = !!value;
    else el.value = value;
  }

  // collect form data & action
  function collectFormDataAndAction() {
    const fd = new FormData();
    const id = parseInt($('delivery_company_id')?.value || 0, 10) || 0;
    const isNew = id === 0;
    const action = isNew ? 'create_company' : 'update_company';
    if (!isNew) fd.set('id', id);

    // gather form fields
    if (form) {
      const elements = form.querySelectorAll('input[name],select[name],textarea[name]');
      elements.forEach(el => {
        const name = el.getAttribute('name');
        if (!name) return;
        if (el.type === 'file') {
          const files = el.files;
          if (files && files.length) for (let i=0;i<files.length;i++) fd.append(name, files[i]);
        } else if (el.type === 'checkbox') {
          fd.set(name, el.checked ? '1' : '0');
        } else {
          fd.set(name, el.value ?? '');
        }
      });
    }

    // ensure country/city included
    if (countrySelect) fd.set('country_id', countrySelect.value || '');
    if (citySelect) fd.set('city_id', citySelect.value || '');

    // rating normalization
    const ratingEl = $('delivery_company_rating');
    if (ratingEl) {
      let rraw = String(ratingEl.value || '').trim().replace(',', '.');
      let rval = parseFloat(rraw);
      if (!isFinite(rval)) rval = 0.0;
      fd.set('rating_average', rval.toFixed(2));
    }

    try { fd.set('translations', JSON.stringify(collectTranslations())); } catch (e) { fd.set('translations', '{}'); }

    const csrfEl = form ? form.querySelector('input[name="csrf_token"]') : null;
    if (csrfEl && csrfEl.value) fd.set('csrf_token', csrfEl.value);
    else if (CSRF) fd.set('csrf_token', CSRF);

    return { fd, action, isNew };
  }

  // Save
  async function saveCompany() {
    if (!errorsBox) return;
    errorsBox.style.display = 'none'; errorsBox.textContent = '';
    const { fd, action, isNew } = collectFormDataAndAction();
    try {
      const res = await postFormData(fd, action);
      if (!res) { alert('No response'); return; }
      if (!res.success) {
        if (res.errors) {
          errorsBox.style.display = 'block';
          errorsBox.textContent = res.message || 'Validation failed';
        } else alert(res.message || 'Save failed');
        return;
      }
      if (isNew && res.id) {
        const idEl = $('delivery_company_id'); if (idEl) idEl.value = res.id;
      }
      alert(res.message || (isNew ? 'Created' : 'Saved'));
      resetForm();
      await loadList();
    } catch (e) {
      console.error('saveCompany error', e);
      alert('Network or server error');
    }
  }

  function resetForm() {
    if (form) form.reset();
    if (translationsArea) translationsArea.innerHTML = '';
    addTranslationPanel(PREF_LANG, (LANGS.find(l=>l.code===PREF_LANG) || {}).name || PREF_LANG);
    if (previewLogo) previewLogo.innerHTML = '';
    const idEl = $('delivery_company_id'); if (idEl) idEl.value = 0;
    if (errorsBox) { errorsBox.style.display = 'none'; errorsBox.textContent = ''; }
    loadParentCompanies();
    loadCountries();
    loadCities('', '');
  }

  // delete
  async function doDelete(id) {
    if (!confirm('Delete company #' + id + '?')) return;
    const fd = new FormData();
    fd.set('id', id);
    try {
      const res = await postFormData(fd, 'delete_company');
      if (!res || !res.success) { alert(res?.message || 'Delete failed'); return; }
      await loadList();
    } catch (e) {
      console.error('doDelete error', e);
      alert('Network or server error');
    }
  }

  // events & init
  (async function init() {
    try {
      // preload selects
      await Promise.all([
        loadCountries('', false),
        loadCountries('', true),
        loadParentCompanies()
      ]);
      if (countryFilter) countryFilter.addEventListener('change', e => loadCities(e.target.value, '', true));
      if (countrySelect) countrySelect.addEventListener('change', e => {
        loadCities(e.target.value, '', false);
      });

      [searchInput, phoneFilter, emailFilter, countryFilter, cityFilter, activeFilter].forEach(el => {
        if (!el) return;
        el.addEventListener('input', () => setTimeout(loadList, 300));
        el.addEventListener('change', () => setTimeout(loadList, 100));
      });

      if (refreshBtn) refreshBtn.addEventListener('click', loadList);
      if (newBtn) newBtn.addEventListener('click', () => { resetForm(); const formSection = $('deliveryCompanyFormSection'); if (formSection) formSection.scrollIntoView({behavior:'smooth'}); });
      if (saveBtn) saveBtn.addEventListener('click', saveCompany);
      if (resetBtn) resetBtn.addEventListener('click', resetForm);

      addTranslationPanel && addTranslationPanel(PREF_LANG, (LANGS.find(l=>l.code===PREF_LANG) || {}).name || PREF_LANG);

      await loadList();
    } catch (e) {
      console.error('init error', e);
    }
  })();

  function escapeHtml(s) { return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

})();