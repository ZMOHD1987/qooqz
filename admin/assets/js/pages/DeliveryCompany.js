/**
 * admin/assets/js/pages/DeliveryCompany.js
 *
 * Self-contained page script for Delivery Companies.
 * - Relies ONLY on server-injected data:
 *     1) window.__DeliveryCompanyTranslations (preferred page file injected by fragment)
 *     2) window.ADMIN_UI.strings (provided by htdocs/api/bootstrap.php)
 * - Does NOT fetch language files or call any I18nLoader or external loader.
 * - Defensive: will work if none of the above exist (falls back to keys/defaults).
 *
 * Save as UTF-8 without BOM.
 */
(function () {
  'use strict';

  // --- Utilities ------------------------------------------------------------
  function safeParse(txt) {
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
  function mergeDeep(dest, src) {
    dest = dest || {};
    src = src || {};
    Object.keys(src).forEach(function (k) {
      var v = src[k];
      if (v && typeof v === 'object' && !Array.isArray(v)) {
        dest[k] = dest[k] || {};
        mergeDeep(dest[k], v);
      } else dest[k] = v;
    });
    return dest;
  }
  function escapeHtml(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }
  function qs(id, ctx) { ctx = ctx || document; return ctx.getElementById ? ctx.getElementById(id) : ctx.querySelector('#' + id); }
  function qsa(sel, ctx) { ctx = ctx || document; try { return Array.prototype.slice.call(ctx.querySelectorAll(sel)); } catch (e) { return []; } }

  // --- Runtime config & endpoints ------------------------------------------
  // These globals are expected to be injected server-side by the fragment:
  // window.API_BASE, window.COUNTRIES_API, window.CITIES_API, window.PARENTS_API,
  // window.CSRF_TOKEN, window.ADMIN_LANG, window.ADMIN_DIR, window.CURRENT_USER, window.DELIVERY_COMPANY_VIEW
  var API = (window.API_BASE && String(window.API_BASE).trim()) || '/api/routes/DeliveryCompany.php';
  var COUNTRIES_API = (window.COUNTRIES_API && String(window.COUNTRIES_API).trim()) || '/api/helpers/countries.php';
  var CITIES_API = (window.CITIES_API && String(window.CITIES_API).trim()) || '/api/helpers/cities.php';
  var PARENTS_API = (window.PARENTS_API && String(window.PARENTS_API).trim()) || (API + '?action=parents');
  var CSRF = window.CSRF_TOKEN || '';
  var PREF_LANG = window.ADMIN_LANG || document.documentElement.lang || 'en';
  var VIEW = window.DELIVERY_COMPANY_VIEW || (window.CURRENT_USER ? { user_id: window.CURRENT_USER.id || 0, is_admin: (window.CURRENT_USER.role_id === 1), permissions: window.CURRENT_USER.permissions || [] } : { user_id: 0, is_admin: false, permissions: [] });

  // --- DOM references (defensive) ------------------------------------------
  var root = document.getElementById('adminDeliveryCompanies') || document;
  var tbody = qs('deliveryCompaniesTbody', root);
  var countEl = qs('deliveryCompaniesCount', root);
  var searchInput = qs('deliveryCompanySearch', root);
  var phoneFilter = qs('deliveryCompanyFilterPhone', root);
  var emailFilter = qs('deliveryCompanyFilterEmail', root);
  var countryFilter = qs('deliveryCompanyFilterCountry', root);
  var cityFilter = qs('deliveryCompanyFilterCity', root);
  var activeFilter = qs('deliveryCompanyFilterActive', root);
  var refreshBtn = qs('deliveryCompanyRefresh', root);
  var newBtn = qs('deliveryCompanyNewBtn', root);

  var form = qs('deliveryCompanyForm', root);
  var saveBtn = qs('deliveryCompanySaveBtn', root);
  var resetBtn = qs('deliveryCompanyResetBtn', root);
  var errorsBox = qs('deliveryCompanyFormErrors', root);

  var translationsArea = qs('deliveryCompany_translations_area', root);
  var addLangBtn = qs('deliveryCompanyAddLangBtn', root);

  var previewLogo = qs('preview_delivery_logo', root);
  var logoInput = qs('delivery_company_logo', root);

  var parentSelect = qs('delivery_company_parent', root);
  var countrySelect = qs('delivery_company_country', root);
  var citySelect = qs('delivery_company_city', root);

  // --- Translations: server-injected ONLY ----------------------------------
  // Priority:
  // 1) window.__DeliveryCompanyTranslations (fragment injected server-side file)
  // 2) window.ADMIN_UI.strings (provided by api/bootstrap.php)
  // 3) otherwise STRINGS empty and UI falls back to keys/defaults (no client fetch)
  var PAGE_TR = (typeof window !== 'undefined' && window.__DeliveryCompanyTranslations && typeof window.__DeliveryCompanyTranslations === 'object') ? window.__DeliveryCompanyTranslations : null;
  var BOOTSTRAP_TR = (typeof window !== 'undefined' && window.ADMIN_UI && window.ADMIN_UI.strings && typeof window.ADMIN_UI.strings === 'object') ? window.ADMIN_UI.strings : null;

  var STRINGS = {};
  function initStrings() {
    return new Promise(function (resolve) {
      try {
        // 1) page-specific translations injected by server fragment
        if (PAGE_TR) {
          var src = PAGE_TR.strings && typeof PAGE_TR.strings === 'object' ? PAGE_TR.strings : PAGE_TR;
          mergeDeep(STRINGS, src || {});
          if (PAGE_TR.direction) try { document.documentElement.dir = PAGE_TR.direction; } catch (e) {}
          return resolve();
        }

        // 2) bootstrap-admin translations
        if (BOOTSTRAP_TR) {
          mergeDeep(STRINGS, BOOTSTRAP_TR);
          if (window.ADMIN_UI && window.ADMIN_UI.direction) try { document.documentElement.dir = window.ADMIN_UI.direction; } catch (e) {}
          return resolve();
        }

        // 3) no client fetch by design — resolve with empty STRINGS
        return resolve();
      } catch (e) {
        // never throw — translation init must not break the page
        console.warn('initStrings error', e);
        return resolve();
      }
    });
  }

  function t(key, fallback) {
    if (!key) return fallback || '';
    var v = getNested(STRINGS, key);
    if (typeof v === 'string') return v;
    return fallback || key;
  }

  function applyTranslations(rootEl) {
    rootEl = rootEl || root;
    // text content
    qsa('[data-i18n]', rootEl).forEach(function (el) {
      try {
        var k = el.getAttribute('data-i18n');
        var v = getNested(STRINGS, k);
        if (typeof v === 'string') el.textContent = v;
      } catch (e) { /* ignore individual failures */ }
    });
    // placeholders
    qsa('[data-i18n-placeholder]', rootEl).forEach(function (el) {
      try {
        var k = el.getAttribute('data-i18n-placeholder');
        var v = getNested(STRINGS, k);
        if (typeof v === 'string') el.placeholder = v;
      } catch (e) {}
    });
    // html
    qsa('[data-i18n-html]', rootEl).forEach(function (el) {
      try {
        var k = el.getAttribute('data-i18n-html');
        var v = getNested(STRINGS, k);
        if (typeof v === 'string') el.innerHTML = v;
      } catch (e) {}
    });
  }

  // --- Permission helpers --------------------------------------------------
  function isAdmin() {
    if (VIEW && typeof VIEW.is_admin !== 'undefined') return !!VIEW.is_admin;
    if (window.CURRENT_USER && typeof window.CURRENT_USER.role_id !== 'undefined') return window.CURRENT_USER.role_id === 1;
    return false;
  }
  function hasPerm(name) {
    if (!name) return false;
    if (isAdmin()) return true;
    var perms = (VIEW && Array.isArray(VIEW.permissions)) ? VIEW.permissions : (window.CURRENT_USER && Array.isArray(window.CURRENT_USER.permissions) ? window.CURRENT_USER.permissions : []);
    return Array.isArray(perms) && perms.indexOf(name) !== -1;
  }

  // --- Network helpers -----------------------------------------------------
  async function fetchJson(url, opts) {
    opts = opts || {};
    opts.credentials = opts.credentials || 'include';
    var res = await fetch(url, opts);
    var txt = await res.text();
    try { return JSON.parse(txt); } catch (e) { return txt; }
  }

  async function postFormData(fd, actionOverride) {
    try { if (actionOverride) fd.set('action', actionOverride); } catch(e){}
    if (CSRF && !fd.get('csrf_token')) fd.set('csrf_token', CSRF);
    var res = await fetch(API, { method: 'POST', body: fd, credentials: 'include' });
    var txt = await res.text();
    try { return JSON.parse(txt); } catch (e) { return txt; }
  }

  // --- Select loaders ------------------------------------------------------
  async function loadCountries(selected, forFilter) {
    var tgt = forFilter ? countryFilter : countrySelect;
    if (!tgt) return;
    tgt.innerHTML = '<option>' + t('messages.loading','Loading...') + '</option>';
    try {
      var j = await fetchJson(COUNTRIES_API + '?lang=' + encodeURIComponent(PREF_LANG));
      var rows = (j && j.success) ? j.data : (Array.isArray(j) ? j : []);
      tgt.innerHTML = '<option value="">' + (t('placeholders.select_country','Select country')) + '</option>';
      (rows || []).forEach(function(c){
        var o = document.createElement('option'); o.value = c.id; o.textContent = c.name || c.title || ''; tgt.appendChild(o);
      });
      if (selected) tgt.value = selected;
      applyTranslations(tgt);
    } catch (e) {
      tgt.innerHTML = '<option>' + t('messages.error_occurred','Failed to load') + '</option>';
    }
  }

  async function loadCities(countryId, selected, forFilter) {
    var tgt = forFilter ? cityFilter : citySelect;
    if (!tgt) return;
    if (!countryId) { tgt.innerHTML = '<option>' + t('placeholders.select_country_first','Select country first') + '</option>'; return; }
    tgt.innerHTML = '<option>' + t('messages.loading','Loading...') + '</option>';
    try {
      var url = CITIES_API + '?country_id=' + encodeURIComponent(countryId) + '&lang=' + encodeURIComponent(PREF_LANG);
      var j = await fetchJson(url);
      var rows = (j && j.success) ? j.data : (Array.isArray(j) ? j : []);
      tgt.innerHTML = '<option value="">' + (t('placeholders.select_city','Select city')) + '</option>';
      (rows || []).forEach(function(ci){
        var o = document.createElement('option'); o.value = ci.id; o.textContent = ci.name || ci.title || ''; tgt.appendChild(o);
      });
      if (selected) tgt.value = selected;
      applyTranslations(tgt);
    } catch (e) {
      tgt.innerHTML = '<option>' + t('messages.error_occurred','Failed to load') + '</option>';
    }
  }

  async function loadParentCompanies(selected) {
    if (!parentSelect) return;
    parentSelect.innerHTML = '<option>' + t('messages.loading','Loading...') + '</option>';
    try {
      var j = await fetchJson(PARENTS_API + '&lang=' + encodeURIComponent(PREF_LANG));
      var rows = (j && j.success) ? j.data : (Array.isArray(j) ? j : []);
      parentSelect.innerHTML = '<option value="">' + (t('delivery_company.no_parent','No parent')) + '</option>';
      (rows||[]).forEach(function(r){
        var o = document.createElement('option'); o.value = r.id; o.textContent = r.name || r.title || ('#' + r.id); parentSelect.appendChild(o);
      });
      if (selected) parentSelect.value = selected;
      applyTranslations(parentSelect);
    } catch (e) {
      parentSelect.innerHTML = '<option>' + t('messages.error_occurred','Failed to load') + '</option>';
    }
  }

  // --- Logo preview --------------------------------------------------------
  if (logoInput && previewLogo) {
    logoInput.addEventListener('change', function () {
      var f = this.files && this.files[0]; if (!f) { previewLogo.innerHTML = ''; return; }
      var fr = new FileReader(); fr.onload = function (ev) { previewLogo.innerHTML = '<img src="'+ev.target.result+'" style="max-height:80px">'; }; fr.readAsDataURL(f);
    });
  }

  // --- Translation panels --------------------------------------------------
  function addTranslationPanel(code, name) {
    if (!translationsArea) return;
    if (!code) code = prompt(t('placeholders.enter_language_code','Language code (e.g., ar)'));
    if (!code) return;
    if (translationsArea.querySelector('.tr-lang-panel[data-lang="'+code+'"]')) return;
    var panel = document.createElement('div'); panel.className = 'tr-lang-panel'; panel.dataset.lang = code;
    panel.style.border = '1px solid #eef2f7'; panel.style.padding = '8px'; panel.style.marginBottom = '8px';
    panel.innerHTML = '<div style="display:flex;justify-content:space-between;align-items:center"><strong>'+escapeHtml(name||code)+' ('+escapeHtml(code)+')</strong><div><button class="btn small toggle">'+t('actions.toggle','Toggle')+'</button> <button class="btn small danger remove">'+t('actions.delete','Remove')+'</button></div></div>' +
      '<div class="body" style="margin-top:8px"><label>'+t('placeholders.description','Description')+'<textarea class="tr-desc" rows="3" style="width:100%"></textarea></label>' +
      '<label style="display:block;margin-top:6px">'+t('placeholders.terms','Terms')+'<textarea class="tr-terms" rows="2" style="width:100%"></textarea></label></div>';
    translationsArea.appendChild(panel);
    panel.querySelector('.remove').addEventListener('click', function(){ panel.remove(); });
    panel.querySelector('.toggle').addEventListener('click', function(){ var bd = panel.querySelector('.body'); bd.style.display = bd.style.display === 'none' ? 'block' : 'none'; });
  }
  if (addLangBtn) addLangBtn.addEventListener('click', function(){ addTranslationPanel('', ''); });

  function collectTranslations() {
    var out = {};
    if (!translationsArea) return out;
    qsa('.tr-lang-panel', translationsArea).forEach(function(p){
      var code = p.dataset.lang || '';
      if (!code) return;
      var desc = p.querySelector('.tr-desc') ? p.querySelector('.tr-desc').value : '';
      var terms = p.querySelector('.tr-terms') ? p.querySelector('.tr-terms').value : '';
      if (desc || terms) out[code] = { description: desc, terms: terms };
    });
    return out;
  }

  // --- List rendering & CRUD helpers --------------------------------------
  function buildListQuery() {
    var params = [];
    var qParts = [];
    var q = (searchInput && searchInput.value || '').trim();
    var phone = (phoneFilter && phoneFilter.value || '').trim();
    var email = (emailFilter && emailFilter.value || '').trim();
    if (q) qParts.push(q);
    if (phone) qParts.push(phone);
    if (email) qParts.push(email);
    if (qParts.length) params.push('q=' + encodeURIComponent(qParts.join(' ')));
    var countryId = (countryFilter && countryFilter.value) || '';
    if (countryId) params.push('country_id=' + encodeURIComponent(countryId));
    var cityId = (cityFilter && cityFilter.value) || '';
    if (cityId) params.push('city_id=' + encodeURIComponent(cityId));
    var isActive = (typeof activeFilter !== 'undefined' && activeFilter !== null) ? (activeFilter.value || '') : '';
    if (isActive !== '') params.push('is_active=' + encodeURIComponent(isActive));
    if (!isAdmin() && VIEW && VIEW.user_id) params.push('user_id=' + encodeURIComponent(VIEW.user_id));
    if (PREF_LANG) params.push('lang=' + encodeURIComponent(PREF_LANG));
    return params.length ? ('&' + params.join('&')) : '';
  }

  async function loadList() {
    if (!tbody) return;
    tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;padding:18px">'+t('messages.loading','Loading...')+'</td></tr>';
    try {
      var q = buildListQuery();
      var j = await fetchJson(API + '?action=list' + q);
      var rows = (j && j.success) ? j.data : (Array.isArray(j) ? j : []);
      var total = (j && typeof j.total !== 'undefined') ? j.total : (Array.isArray(rows) ? rows.length : 0);
      if (countEl) countEl.textContent = total;
      renderTable(rows || []);
      applyTranslations(root);
    } catch (e) {
      console.error('loadList', e);
      tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;padding:18px;color:#b91c1c">'+t('messages.error_occurred','Error loading')+'</td></tr>';
    }
  }

  function renderTable(rows) {
    if (!tbody) return;
    if (!rows || rows.length === 0) { tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;padding:18px">'+t('messages.no_companies_found','No companies')+'</td></tr>'; return; }
    tbody.innerHTML = '';
    rows.forEach(function (r) {
      var countryCity = ((r.country_name || '') + (r.city_name ? (' / ' + r.city_name) : '')).trim();
      var isActiveLabel = r.is_active ? t('statuses.active','Active') : t('statuses.inactive','Inactive');

      var ownerId = parseInt(r.user_id || r.user || 0, 10);
      var canEdit = isAdmin() || hasPerm('edit_delivery_companies') || (VIEW && parseInt(VIEW.user_id,10) === ownerId);
      var canDelete = isAdmin() || hasPerm('delete_delivery_companies') || (VIEW && parseInt(VIEW.user_id,10) === ownerId);
      var canToggle = canEdit || hasPerm('approve_delivery_companies');

      var editBtn = canEdit ? '<button class="btn edit" data-id="'+escapeHtml(r.id)+'">'+t('actions.edit','Edit')+'</button>' : '';
      var delBtn = canDelete ? '<button class="btn danger del" data-id="'+escapeHtml(r.id)+'">'+t('actions.delete','Delete')+'</button>' : '';
      var toggleBtn = canToggle ? '<button class="btn small toggle-active" data-id="'+escapeHtml(r.id)+'" data-active="'+(r.is_active ? 1 : 0)+'">'+t('actions.toggle','Toggle')+'</button>' : '<span class="muted">'+t('actions.toggle','Toggle')+'</span>';

      var tr = document.createElement('tr');
      tr.innerHTML = '<td style="padding:8px;border-bottom:1px solid #eef2f7">'+escapeHtml(r.id)+'</td>' +
        '<td style="padding:8px;border-bottom:1px solid #eef2f7">'+escapeHtml(r.name||'')+'</td>' +
        '<td style="padding:8px;border-bottom:1px solid #eef2f7">'+escapeHtml(r.email||'')+'</td>' +
        '<td style="padding:8px;border-bottom:1px solid #eef2f7">'+escapeHtml(r.phone||'')+'</td>' +
        '<td style="padding:8px;border-bottom:1px solid #eef2f7">'+escapeHtml(countryCity||'')+'</td>' +
        '<td style="padding:8px;border-bottom:1px solid #eef2f7" data-id="'+escapeHtml(r.id)+'">'+isActiveLabel+' '+toggleBtn+'</td>' +
        '<td style="padding:8px;border-bottom:1px solid #eef2f7">'+editBtn+' '+delBtn+'</td>';
      tbody.appendChild(tr);
    });

    qsa('.edit', tbody).forEach(function(b){ b.addEventListener('click', function(){ openEdit(b.dataset.id); }); });
    qsa('.del', tbody).forEach(function(b){ b.addEventListener('click', function(){ doDelete(b.dataset.id); }); });
    qsa('.toggle-active', tbody).forEach(function(b){ b.addEventListener('click', function(){ toggleActive(b.dataset.id, b); }); });
  }

  async function toggleActive(id, btnEl) {
    if (!confirm(t('confirm.toggle_message','Toggle active for company') + ' #' + id + '?')) return;
    var current = parseInt(btnEl.getAttribute('data-active') || '0', 10);
    var newVal = current ? 0 : 1;
    var fd = new FormData(); fd.set('id', id); fd.set('is_active', newVal ? '1' : '0');
    try {
      var res = await postFormData(fd, 'update_company');
      if (!res || !res.success) { alert(res && res.message ? res.message : t('messages.error_occurred','Failed to update')); return; }
      await loadList();
    } catch (e) {
      console.error('toggleActive', e);
      alert(t('messages.error_occurred','Network or server error'));
    }
  }

  // --- open/edit/save/delete implementations (same defensive patterns) -------
  async function openEdit(id) {
    try {
      revealForm();
      var j = await fetchJson(API + '?action=get&id=' + encodeURIComponent(id) + '&lang=' + encodeURIComponent(PREF_LANG));
      if (!j || !j.success) { alert(j && j.message ? j.message : t('messages.error_occurred','Load failed')); return; }
      var v = j.data || {};

      var idEl = qs('delivery_company_id', root);
      if (idEl) idEl.value = v.id || 0;

      await loadParentCompanies(v.parent_id || '');
      await loadCountries(v.country_id || '', false);
      if (v.country_id) await loadCities(v.country_id, v.city_id || '', false);

      setFieldValue('delivery_company_name', v.name || '');
      setFieldValue('delivery_company_slug', v.slug || '');
      setFieldValue('delivery_company_phone', v.phone || '');
      setFieldValue('delivery_company_email', v.email || '');
      setFieldValue('delivery_company_website', v.website_url || '');
      setFieldValue('delivery_company_api_url', v.api_url || '');
      setFieldValue('delivery_company_api_key', v.api_key || '');
      setFieldValue('delivery_company_tracking', v.tracking_url || '');
      setFieldValue('delivery_company_rating', (typeof v.rating_average !== 'undefined') ? Number(v.rating_average).toFixed(2) : '0.00');

      var activeEl = qs('delivery_company_is_active', root);
      if (activeEl) activeEl.checked = !!v.is_active;

      if (translationsArea) translationsArea.innerHTML = '';
      if (v.translations && typeof v.translations === 'object') {
        Object.keys(v.translations).forEach(function(lang){
          addTranslationPanel(lang, lang);
          var panel = translationsArea.querySelector('.tr-lang-panel[data-lang="'+lang+'"]');
          if (panel) {
            var d = panel.querySelector('.tr-desc'); var t2 = panel.querySelector('.tr-terms');
            if (d) d.value = v.translations[lang].description || '';
            if (t2) t2.value = v.translations[lang].terms || '';
          }
        });
      } else addTranslationPanel(PREF_LANG, PREF_LANG);

      if (v.logo_url && previewLogo) previewLogo.innerHTML = '<img src="'+escapeHtml(v.logo_url)+'" style="max-height:80px">';

      var section = qs('deliveryCompanyFormSection', root);
      if (section && section.scrollIntoView) section.scrollIntoView({ behavior: 'smooth', block: 'start' });

      applyTranslations(root);
    } catch (e) {
      console.error('openEdit', e);
      alert(t('messages.error_occurred','Error loading company'));
    }
  }

  function setFieldValue(id, value) {
    try {
      var el = qs(id, root);
      if (!el) return;
      if (el.type === 'checkbox') { el.checked = !!value; el.dispatchEvent(new Event('change', { bubbles: true })); return; }
      if (el.tagName === 'INPUT' || el.tagName === 'SELECT' || el.tagName === 'TEXTAREA') { el.value = value == null ? '' : value; el.dispatchEvent(new Event('change', { bubbles: true })); return; }
      el.textContent = value == null ? '' : String(value);
    } catch (e) { console.warn('setFieldValue', id, e); }
  }

  function collectFormData() {
    var fd = new FormData();
    var idEl = qs('delivery_company_id', root);
    var id = idEl ? (idEl.value || '0') : '0';
    if (id && id !== '0') fd.set('id', id);

    if (form) qsa('input[name],select[name],textarea[name]', form).forEach(function(el){
      var name = el.getAttribute('name'); if (!name) return;
      if (el.type === 'file') { if (el.files && el.files.length) for (var i=0;i<el.files.length;i++) fd.append(name, el.files[i]); }
      else if (el.type === 'checkbox') fd.set(name, el.checked ? '1' : '0');
      else fd.set(name, el.value || '');
    });

    if (countrySelect) fd.set('country_id', countrySelect.value || '');
    if (citySelect) fd.set('city_id', citySelect.value || '');

    var ratingEl = qs('delivery_company_rating', root);
    if (ratingEl) {
      var rv = String(ratingEl.value || '').replace(',', '.'); var num = parseFloat(rv); if (!isFinite(num)) num = 0.0;
      fd.set('rating_average', num.toFixed(2));
    }

    try { fd.set('translations', JSON.stringify(collectTranslations())); } catch (e) { fd.set('translations', '{}'); }

    var csrfEl = form ? form.querySelector('input[name="csrf_token"]') : null;
    if (csrfEl && csrfEl.value) fd.set('csrf_token', csrfEl.value); else if (CSRF) fd.set('csrf_token', CSRF);

    return fd;
  }

  async function saveCompany() {
    if (errorsBox) { errorsBox.style.display = 'none'; errorsBox.textContent = ''; }
    var idEl = qs('delivery_company_id', root);
    var isNew = !(idEl && idEl.value && idEl.value !== '0');
    var action = isNew ? 'create_company' : 'update_company';
    var fd = collectFormData();
    try {
      var res = await postFormData(fd, action);
      if (!res) { alert(t('messages.error_occurred','No response')); return; }
      if (!res.success) {
        if (res.errors && errorsBox) { errorsBox.style.display = 'block'; errorsBox.textContent = res.message || t('messages.error_occurred','Validation failed'); }
        else alert(res.message || t('messages.error_occurred','Save failed'));
        return;
      }
      if (isNew && res.id && idEl) idEl.value = res.id;
      alert(res.message || (isNew ? t('messages.saved_successfully','Created') : t('messages.saved_successfully','Saved')));
      resetForm();
      await loadList();
    } catch (e) {
      console.error('saveCompany', e);
      alert(t('messages.error_occurred','Network or server error'));
    }
  }

  function resetForm() {
    if (form) form.reset();
    if (translationsArea) translationsArea.innerHTML = '';
    try { addTranslationPanel(PREF_LANG, PREF_LANG); } catch (e) {}
    if (previewLogo) previewLogo.innerHTML = '';
    var idEl = qs('delivery_company_id', root); if (idEl) idEl.value = '0';
    if (errorsBox) { errorsBox.style.display = 'none'; errorsBox.textContent = ''; }
    loadParentCompanies(); loadCountries(); loadCities('', '');
  }

  async function doDelete(id) {
    if (!confirm(t('confirm.delete_message','Delete company') + ' #' + id + '?')) return;
    var fd = new FormData(); fd.set('id', id);
    try {
      var res = await postFormData(fd, 'delete_company');
      if (!res || !res.success) { alert(res && res.message ? res.message : t('messages.error_occurred','Delete failed')); return; }
      await loadList();
    } catch (e) {
      console.error('doDelete', e);
      alert(t('messages.error_occurred','Network or server error'));
    }
  }

  function revealForm() { try { var s = qs('deliveryCompanyFormSection', root); if (s) { s.style.display=''; s.style.visibility='visible'; s.style.opacity='1'; s.scrollIntoView && s.scrollIntoView({behavior:'smooth', block:'start'}); } var f = qs('deliveryCompanyForm', root); if (f) { f.style.display=''; f.style.visibility='visible'; f.style.opacity='1'; } } catch (e) {} }

  // --- init ----------------------------------------------------------------
  (async function init() {
    try {
      // Build STRINGS from server-injected sources only
      // Merge order: page-specific translations (fragment) -> bootstrap ADMIN_UI.strings
      if (typeof window !== 'undefined') {
        // If fragment injected full translation object (not yet merged), merge it
        if (window.__DeliveryCompanyTranslations && typeof window.__DeliveryCompanyTranslations === 'object') {
          var src = window.__DeliveryCompanyTranslations.strings ? window.__DeliveryCompanyTranslations.strings : window.__DeliveryCompanyTranslations;
          mergeDeep(STRINGS, src || {});
          if (window.__DeliveryCompanyTranslations.direction) try { document.documentElement.dir = window.__DeliveryCompanyTranslations.direction; } catch (e) {}
        }
        // Merge bootstrap strings afterwards if present but do not override existing keys
        if (window.ADMIN_UI && window.ADMIN_UI.strings && typeof window.ADMIN_UI.strings === 'object') {
          mergeDeep(STRINGS, window.ADMIN_UI.strings);
          if (window.ADMIN_UI.direction) try { document.documentElement.dir = window.ADMIN_UI.direction; } catch (e) {}
        }
      }

      await initStrings(); // defensive no-op if already merged
      applyTranslations(root);

      // Load selects and other remote data (these are normal API requests)
      await Promise.all([ loadCountries('', false), loadCountries('', true), loadParentCompanies() ]);

      if (countryFilter) countryFilter.addEventListener('change', function (e) { loadCities(e.target.value, '', true); });
      if (countrySelect) countrySelect.addEventListener('change', function (e) { loadCities(e.target.value, '', false); });

      var watch = [searchInput, phoneFilter, emailFilter, countryFilter, cityFilter, activeFilter];
      watch.forEach(function(el){ if (!el) return; el.addEventListener('input', function(){ setTimeout(loadList, 300); }); el.addEventListener('change', function(){ setTimeout(loadList, 150); }); });

      if (refreshBtn) refreshBtn.addEventListener('click', loadList);
      if (newBtn) newBtn.addEventListener('click', function(){ resetForm(); var s = qs('deliveryCompanyFormSection', root); s && s.scrollIntoView({ behavior:'smooth' }); });
      if (saveBtn) saveBtn.addEventListener('click', saveCompany);
      if (resetBtn) resetBtn.addEventListener('click', resetForm);
      if (addLangBtn) addLangBtn.addEventListener('click', function(){ addTranslationPanel('', ''); });

      applyTranslations(root);
      await loadList();
    } catch (e) {
      console.error('DeliveryCompany init error', e);
    }
  })();

  // expose API
  window.DeliveryCompany = window.DeliveryCompany || {};
  window.DeliveryCompany.reload = loadList;
  window.DeliveryCompany.openEdit = openEdit;

})();