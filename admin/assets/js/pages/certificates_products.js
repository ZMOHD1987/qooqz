/**
 * Certificates Products Management
 * Production version – fully compatible with actual API responses.
 * 
 * التعديلات:
 * - إضافة حقل بلد المنشأ (origin_country_id) مع تحميل الدول من API.
 */

(function () {
    'use strict';

    // ----------------------------------------------------------------------
    // الاعتماديات (Dependencies)
    // ----------------------------------------------------------------------
    const AF = window.AdminFramework;
    const CFG = window.CERTIFICATES_PRODUCTS_CONFIG || {};

    // ----------------------------------------------------------------------
    // نقاط نهاية API (Endpoints)
    // ----------------------------------------------------------------------
    const API           = CFG.apiUrl          || '/api/certificates_products';
    const TRANS_API     = CFG.translationsApi || '/api/certificates_products_translations';
    const ENTITIES_API  = CFG.entitiesApi     || '/api/entities';
    const BRANDS_API    = CFG.brandsApi       || '/api/brands';
    const LANG_API      = CFG.languagesApi    || '/api/languages';
    const UNITS_API     = CFG.unitsApi        || '/api/units';
    const TENANTS_API   = CFG.tenantsApi      || '/api/tenants';
    const COUNTRIES_API = CFG.countriesApi    || '/api/countries';   // جديد

    // ----------------------------------------------------------------------
    // الحالة العامة (State)
    // ----------------------------------------------------------------------
    const state = {
        page        : 1,
        perPage     : 25,
        filters     : {},
        permissions : window.PAGE_PERMISSIONS || {},
        translations: window.CERTIFICATES_PRODUCTS_TRANSLATIONS || {},
        language    : window.USER_LANGUAGE || 'ar',
        products    : [],
        entities    : [],      // جميع الكيانات (قد تكون من عدة مستأجرين)
        brands      : [],      // جميع العلامات التجارية
        languages   : [],
        units       : [],
        countries   : [],      // جديد: الدول
        currentTenantId: window.APP_CONFIG?.TENANT_ID || 1,
        currentTenantName: ''  // سيتم تعيينه بعد التحقق من المستأجر (للسوبر أدمن)
    };

    // ----------------------------------------------------------------------
    // المتغيرات العامة للعناصر (DOM elements)
    // ----------------------------------------------------------------------
    let el = {};
    let deletedTranslations = [];
    let verifyTenantTimeout = null;

    // ======================================================================
    // 1. دوال المساعدة للترجمة (Translation helpers)
    // ======================================================================

    async function loadTranslations(lang) {
        lang = lang || state.language || 'ar';
        const url = `/languages/CertificatesProducts/${encodeURIComponent(lang)}.json`;
        try {
            const res = await fetch(url, { credentials: 'same-origin' });
            if (!res.ok) throw new Error(`HTTP ${res.status}`);
            state.translations = await res.json();
            state.language = lang;
            applyTranslations();
            setDirection(lang);
            return true;
        } catch (err) {
            console.warn('[CertificatesProducts] Translation load failed for', lang, err.message);
            if (lang !== 'en') return loadTranslations('en');
            state.translations = fallbackTranslations();
            applyTranslations();
            return false;
        }
    }

    function applyTranslations() {
        const container = document.getElementById('certificatesProductsPageContainer');
        if (!container) return;
        container.querySelectorAll('[data-i18n]').forEach(node => {
            const val = getT(node.getAttribute('data-i18n'));
            if (val) {
                if (node.tagName === 'INPUT' && node.hasAttribute('placeholder')) {
                    node.placeholder = val;
                } else {
                    node.textContent = val;
                }
            }
        });
        container.querySelectorAll('[data-i18n-placeholder]').forEach(node => {
            const val = getT(node.getAttribute('data-i18n-placeholder'));
            if (val) node.placeholder = val;
        });
    }

    function getT(key, fallback = '') {
        if (!key) return fallback;
        const val = key.split('.').reduce((o, k) => (o && o[k] !== undefined ? o[k] : null), state.translations);
        return (val !== null && val !== undefined) ? String(val) : (fallback || key);
    }

    function tReplace(key, vars = {}) {
        let txt = getT(key, key);
        Object.entries(vars).forEach(([k, v]) => {
            txt = txt.replace(new RegExp(`{${k}}`, 'g'), v);
        });
        return txt;
    }

    function setDirection(lang) {
        const rtlLangs = ['ar','he','fa','ur','ps'];
        const isRtl = rtlLangs.includes(String(lang).slice(0, 2).toLowerCase());
        const dir = isRtl ? 'rtl' : 'ltr';
        const cont = document.getElementById('certificatesProductsPageContainer');
        if (cont) {
            cont.dir = dir;
            cont.classList.toggle('rtl', isRtl);
            cont.classList.toggle('ltr', !isRtl);
        }
    }

    function fallbackTranslations() {
        return {
            certificates_products: { title: 'Certificates Products', subtitle: '', add_new: 'Add Product', loading: 'Loading...', retry: 'Retry' },
            table: {
                headers: { id:'ID', tenant:'Tenant', entity:'Entity', brand:'Brand', name:'Name', code:'Code', net_weight:'Net Weight', unit:'Unit', sample_status:'Sample Status', condition:'Condition', origin_country:'Origin Country', actions:'Actions' },
                actions: { edit:'Edit', delete:'Delete', confirm_delete:'Are you sure?' },
                empty: { title:'No Products', message:'Add a product', add_first:'Add First Product' }
            },
            filters: {
                search:'Search', search_placeholder:'Search...', all_entities:'All Entities', all_brands:'All Brands', all_status:'All', all_conditions:'All', all_countries:'All Countries', origin_country:'Origin Country',
                apply:'Apply', reset:'Reset',
                sample_status: { normal:'Normal', tested:'Tested', rejected:'Rejected' },
                condition: { chilled:'Chilled', frozen:'Frozen', dry:'Dry' }
            },
            form: {
                add_title:'Add Product', edit_title:'Edit Product',
                fields: {
                    entity:{ label:'Entity', select:'Select entity' },
                    brand:{ label:'Brand', select:'Select brand' },
                    product_code:{ label:'Product Code', placeholder:'Enter code' },
                    net_weight:{ label:'Net Weight', placeholder:'0.000' },
                    weight_unit:{ label:'Weight Unit' },
                    sample_status:{ label:'Sample Status', normal:'Normal', tested:'Tested', rejected:'Rejected' },
                    product_condition:{ label:'Condition', chilled:'Chilled', frozen:'Frozen', dry:'Dry' },
                    origin_country:{ label:'Origin Country', select:'Select country' },
                    name:{ label:'Name' }
                },
                translations: {
                    title:'Translations', select_lang:'Select Language', choose_lang:'Choose language',
                    add:'Add Translation', remove:'Remove', name_in_lang:'Name in {lang}'
                },
                buttons: { save:'Save', cancel:'Cancel', saving:'Saving...', updating:'Updating...' }
            },
            messages: {
                success: { created:'Created successfully', updated:'Updated successfully', deleted:'Deleted successfully' },
                error: { load_failed:'Load failed', save_failed:'Save failed', delete_failed:'Delete failed', not_found:'Not found' }
            },
            pagination: { showing:'Showing' },
            accessibility: { close:'Close' }
        };
    }

    // ======================================================================
    // 2. دوال المساعدة لـ API (معالجة الاستجابات المختلفة)
    // ======================================================================

    function buildUrl(base, params = {}) {
        const clean = {};
        Object.entries(params).forEach(([k, v]) => {
            if (v !== null && v !== undefined && v !== '') clean[k] = v;
        });
        const qs = new URLSearchParams(clean).toString();
        return qs ? `${base}?${qs}` : base;
    }

    async function apiGet(url) {
        const res = await AF.get(url);
        if (res && res.data !== undefined) {
            if (res.data && typeof res.data === 'object' && 'data' in res.data && Array.isArray(res.data.data)) {
                return res.data.data;
            }
            if (res.data && typeof res.data === 'object' && 'items' in res.data) {
                return res.data;
            }
            return res.data;
        }
        return res;
    }

    function extractItems(data) {
        if (!data) return [];
        if (Array.isArray(data)) return data;
        if (data.items && Array.isArray(data.items)) return data.items;
        if (data.data && Array.isArray(data.data)) return data.data;
        if (typeof data === 'object' && data.id) return [data];
        return [];
    }

    // ======================================================================
    // 3. تحميل البيانات المساعدة (الكيانات، العلامات التجارية، اللغات، الوحدات، الدول)
    // ======================================================================

    /**
     * تحميل جميع البيانات المساعدة بالتوازي
     */
    async function loadDependencies() {
        await Promise.all([
            loadLanguages(),
            loadAllEntitiesAndBrands(),
            loadUnits(),
            loadCountries()   // جديد: تحميل الدول
        ]);
    }

    /**
     * تحميل جميع الكيانات والعلامات التجارية (لكل المستأجرين إذا كان السوبر أدمن، أو للمستأجر الحالي)
     */
    async function loadAllEntitiesAndBrands() {
        const params = { limit: 1000, lang: state.language };
        
        if (!state.permissions.isSuperAdmin) {
            params.tenant_id = state.currentTenantId;
        }

        try {
            // تحميل الكيانات
            const entitiesUrl = buildUrl(ENTITIES_API, params);
            const entitiesData = await apiGet(entitiesUrl);
            const entitiesItems = extractItems(entitiesData);
            state.entities = entitiesItems.map(e => ({
                id: e.id,
                name: e.store_name || e.name || `Entity #${e.id}`,
                tenant_id: e.tenant_id
            }));

            // تحميل العلامات التجارية
            const brandsUrl = buildUrl(BRANDS_API, params);
            const brandsData = await apiGet(brandsUrl);
            const brandsItems = extractItems(brandsData);
            state.brands = brandsItems.map(b => ({
                id: b.id,
                name: b.name || `Brand #${b.id}`,
                tenant_id: b.tenant_id
            }));

            // ملء القوائم المنسدلة
            populateEntityDropdowns();
            populateBrandDropdowns();
        } catch (e) {
            console.warn('[CertificatesProducts] loading entities/brands failed', e.message);
            state.entities = [];
            state.brands = [];
            populateEntityDropdowns();
            populateBrandDropdowns();
        }
    }

    /**
     * تحميل كيانات وعلامات مستأجر معين وإضافتها إلى القوائم الحالية (دون مسح السابقة)
     */
    async function loadEntitiesAndBrandsForTenant(tenantId) {
        if (!tenantId) return;
        const hasEntitiesForTenant = state.entities.some(e => e.tenant_id == tenantId);
        const hasBrandsForTenant = state.brands.some(b => b.tenant_id == tenantId);
        if (hasEntitiesForTenant && hasBrandsForTenant) return;

        const params = { tenant_id: tenantId, limit: 1000, lang: state.language };
        try {
            const entitiesUrl = buildUrl(ENTITIES_API, params);
            const entitiesData = await apiGet(entitiesUrl);
            const entitiesItems = extractItems(entitiesData);
            const newEntities = entitiesItems.map(e => ({
                id: e.id,
                name: e.store_name || e.name || `Entity #${e.id}`,
                tenant_id: e.tenant_id
            }));
            newEntities.forEach(ne => {
                if (!state.entities.some(e => e.id === ne.id && e.tenant_id === ne.tenant_id)) {
                    state.entities.push(ne);
                }
            });

            const brandsUrl = buildUrl(BRANDS_API, params);
            const brandsData = await apiGet(brandsUrl);
            const brandsItems = extractItems(brandsData);
            const newBrands = brandsItems.map(b => ({
                id: b.id,
                name: b.name || `Brand #${b.id}`,
                tenant_id: b.tenant_id
            }));
            newBrands.forEach(nb => {
                if (!state.brands.some(b => b.id === nb.id && b.tenant_id === nb.tenant_id)) {
                    state.brands.push(nb);
                }
            });

            populateEntityDropdowns();
            populateBrandDropdowns();
        } catch (e) {
            console.warn('[CertificatesProducts] loading entities/brands for tenant', tenantId, 'failed', e.message);
        }
    }

    async function loadLanguages() {
        if (state.languages.length > 0) return;
        try {
            const url = buildUrl(LANG_API, { limit: 100 });
            const data = await apiGet(url);
            state.languages = extractItems(data);
            populateLangSelect();
        } catch (e) {
            console.warn('[CertificatesProducts] languages load failed', e.message);
        }
    }

    async function loadUnits() {
        try {
            const url = buildUrl(UNITS_API, { lang: state.language, limit: 100 });
            const data = await apiGet(url);
            state.units = Array.isArray(data) ? data : (data.data && Array.isArray(data.data) ? data.data : []);
            populateWeightUnitDropdown();
        } catch (e) {
            console.warn('[CertificatesProducts] units load failed, using defaults', e.message);
            state.units = [{ code: 'kg', name: 'kg' }, { code: 'g', name: 'g' }, { code: 'ml', name: 'ml' }, { code: 'ton', name: 'ton' }, { code: 'pcs', name: 'pcs' }];
            populateWeightUnitDropdown();
        }
    }

    // ========== جديد: تحميل الدول ==========
    async function loadCountries() {
        try {
            const url = buildUrl(COUNTRIES_API, { lang: state.language, limit: 500 });
            const data = await apiGet(url);
            state.countries = extractItems(data).map(c => ({
                id: c.id,
                name: c.name || `Country #${c.id}`,
                code: c.code
            }));
            populateCountryDropdowns();
        } catch (e) {
            console.warn('[CertificatesProducts] countries load failed', e.message);
            state.countries = [];
            populateCountryDropdowns();
        }
    }

    // ======================================================================
    // 4. دوال ملء القوائم المنسدلة
    // ======================================================================

    function populateLangSelect() {
        if (!el.langSelect) return;
        el.langSelect.innerHTML = `<option value="">${getT('form.translations.choose_lang')}</option>`;
        state.languages.forEach(l => {
            const opt = document.createElement('option');
            opt.value = l.code;
            opt.textContent = `${l.code.toUpperCase()} — ${l.name || ''}`;
            el.langSelect.appendChild(opt);
        });
    }

    function populateEntityDropdowns() {
        const sel = getT('form.fields.entity.select');
        const all = getT('filters.all_entities');
        [el.entityId, el.entityFilter].forEach((s, i) => {
            if (!s) return;
            s.innerHTML = `<option value="">${i === 0 ? sel : all}</option>`;
            const sorted = [...state.entities].sort((a, b) => a.name.localeCompare(b.name));
            sorted.forEach(e => {
                const opt = document.createElement('option');
                opt.value = e.id;
                opt.textContent = e.name;
                opt.dataset.tenantId = e.tenant_id;
                s.appendChild(opt);
            });
        });
    }

    function populateBrandDropdowns() {
        const sel = getT('form.fields.brand.select');
        const all = getT('filters.all_brands');
        [el.brandId, el.brandFilter].forEach((s, i) => {
            if (!s) return;
            s.innerHTML = `<option value="">${i === 0 ? sel : all}</option>`;
            const sorted = [...state.brands].sort((a, b) => a.name.localeCompare(b.name));
            sorted.forEach(b => {
                const opt = document.createElement('option');
                opt.value = b.id;
                opt.textContent = b.name;
                opt.dataset.tenantId = b.tenant_id;
                s.appendChild(opt);
            });
        });
    }

    function populateWeightUnitDropdown() {
        if (!el.weightUnit) return;
        el.weightUnit.innerHTML = '';
        state.units.forEach(u => {
            const opt = document.createElement('option');
            opt.value = u.code || u;
            opt.textContent = u.name || u.code || u;
            el.weightUnit.appendChild(opt);
        });
    }

    // ========== جديد: ملء قوائم الدول ==========
    function populateCountryDropdowns() {
        const sel = getT('form.fields.origin_country.select');
        const all = getT('filters.all_countries');
        [el.originCountryId, el.originCountryFilter].forEach((s, i) => {
            if (!s) return;
            s.innerHTML = `<option value="">${i === 0 ? sel : all}</option>`;
            const sorted = [...state.countries].sort((a, b) => a.name.localeCompare(b.name));
            sorted.forEach(c => {
                const opt = document.createElement('option');
                opt.value = c.id;
                opt.textContent = c.name;
                s.appendChild(opt);
            });
        });
    }

    // ======================================================================
    // 5. التحقق من المستأجر (للسوبر أدمن فقط)
    // ======================================================================

    async function verifyTenant(tenantId) {
        if (!tenantId || isNaN(tenantId)) {
            showTenantMsg(getT('messages.error.tenant_required', 'Tenant ID required'), false);
            return false;
        }
        try {
            const url = `${TENANTS_API}/${tenantId}`;
            const data = await apiGet(url);
            if (!data || !data.id) throw new Error('Tenant not found');
            showTenantMsg(`${data.name || ''} ${data.domain ? '(' + data.domain + ')' : ''}`, true);
            state.currentTenantName = data.name || '';
            if (data.id != state.currentTenantId) {
                state.currentTenantId = data.id;
                await loadEntitiesAndBrandsForTenant(data.id);
            }
            return true;
        } catch (e) {
            showTenantMsg(e.message, false);
            return false;
        }
    }

    function showTenantMsg(msg, valid) {
        const info = document.getElementById('tenantInfo');
        if (!info) return;
        info.innerHTML = `<small style="color:${valid ? 'green' : 'red'}">${escHtml(msg)}</small>`;
    }

    // ======================================================================
    // 6. تحميل وعرض المنتجات
    // ======================================================================

    async function load(page = 1) {
        showLoading();
        state.page = page;
        try {
            const params = {
                page: page,
                limit: state.perPage,
                lang: state.language,
                ...state.filters
            };

            if (state.filters.tenant_id) {
                params.tenant_id = state.filters.tenant_id;
            } else if (!state.permissions.isSuperAdmin) {
                params.tenant_id = state.currentTenantId;
            }

            const url = buildUrl(API, params);
            const data = await apiGet(url);
            let items = extractItems(data);
            const meta = data.meta || {
                total: items.length,
                page: page,
                per_page: state.perPage,
                from: (page - 1) * state.perPage + 1,
                to: Math.min(page * state.perPage, items.length)
            };

            // إضافة أسماء الكيانات والعلامات التجارية والدول
            items = items.map(item => {
                const entity = state.entities.find(e => e.id == item.entity_id);
                const brand = state.brands.find(b => b.id == item.brand_id);
                const country = state.countries.find(c => c.id == item.origin_country_id);   // جديد
                return {
                    ...item,
                    entity_name: entity?.name || item.entity_id,
                    brand_name: brand?.name || item.brand_id,
                    country_name: country?.name || item.origin_country_id,   // جديد
                    tenant_name: item.tenant_name || state.currentTenantName || item.tenant_id
                };
            });

            renderTable(items, meta);
            updatePagination(meta);
        } catch (err) {
            console.error('[CertificatesProducts] load error', err);
            showError(err.message);
        }
    }

    function renderTable(items, meta) {
        state.products = items || [];
        if (!el.tbody) return;

        if (!items || items.length === 0) {
            showEmpty();
            return;
        }

        el.tbody.innerHTML = items.map(item => {
            const sampleLabel = getT(`form.fields.sample_status.${item.sample_status}`, item.sample_status || '-');
            const conditionLabel = getT(`form.fields.product_condition.${item.product_condition}`, item.product_condition || '-');
            const tenantCol = state.permissions.isSuperAdmin ? `<td>${escHtml(item.tenant_name)}</td>` : '';
            const editBtn = (state.permissions.canEdit || state.permissions.isSuperAdmin)
                ? `<button class="btn btn-sm btn-outline" onclick="CertificatesProducts.edit(${item.id})">${getT('table.actions.edit')}</button>` : '';
            const delBtn = (state.permissions.canDelete || state.permissions.isSuperAdmin)
                ? `<button class="btn btn-sm btn-danger" onclick="CertificatesProducts.remove(${item.id})">${getT('table.actions.delete')}</button>` : '';
            return `<tr>
                <td>${item.id}</td>
                ${tenantCol}
                <td>${escHtml(item.entity_name)}</td>
                <td>${escHtml(item.brand_name)}</td>
                <td><strong>${escHtml(item.name || '-')}</strong></td>
                <td>${escHtml(item.entity_product_code || '-')}</td>
                <td>${item.net_weight ?? ''}</td>
                <td>${escHtml(item.weight_unit || 'kg')}</td>
                <td><span class="badge badge-${item.sample_status}">${sampleLabel}</span></td>
                <td><span class="badge badge-${item.product_condition}">${conditionLabel}</span></td>
                <td>${escHtml(item.country_name)}</td>  <!-- جديد: عمود بلد المنشأ -->
                <td><div class="table-actions">${editBtn}${delBtn}</div></td>
            </tr>`;
        }).join('');

        if (el.container) el.container.style.display = 'block';
        if (el.empty) el.empty.style.display = 'none';
        if (el.error) el.error.style.display = 'none';
        if (el.loading) el.loading.style.display = 'none';

        if (el.resultsCount && el.resultsCountText) {
            el.resultsCountText.textContent = `${meta.total ?? items.length} record${(meta.total??items.length)!==1?'s':''}`;
            el.resultsCount.style.display = 'block';
        }
    }

    function updatePagination(meta) {
        if (!el.paginationInfo) return;
        const total = meta.total || 0;
        const from = meta.from || 0;
        const to = meta.to || 0;
        el.paginationInfo.textContent = `${from}–${to} of ${total}`;
        if (AF.Table?.renderPagination) {
            AF.Table.renderPagination(el.pagination, el.paginationInfo, meta);
        }
    }

    // ======================================================================
    // 7. عمليات CRUD
    // ======================================================================

    async function save(e) {
        e.preventDefault();
        if (!AF.Form.validate('productForm')) return;

        const fd = AF.Form.getData('productForm');
        const id = el.formId.value.trim();
        const isEdit = !!id;

        const translations = [];
        if (el.translations) {
            el.translations.querySelectorAll('[data-lang]').forEach(panel => {
                const code = panel.dataset.lang;
                translations.push({
                    language_code: code,
                    name: panel.querySelector(`[name="translations[${code}][name]"]`)?.value || ''
                });
            });
        }

        const body = {
            tenant_id: state.currentTenantId,
            entity_id: fd.entity_id ? parseInt(fd.entity_id) : null,
            brand_id: fd.brand_id ? parseInt(fd.brand_id) : null,
            entity_product_code: fd.entity_product_code || null,
            net_weight: fd.net_weight ? parseFloat(fd.net_weight) : null,
            weight_unit: fd.weight_unit || 'kg',
            sample_status: fd.sample_status || 'normal',
            product_condition: fd.product_condition || 'dry',
            origin_country_id: fd.origin_country_id ? parseInt(fd.origin_country_id) : 1   // جديد: إرسال بلد المنشأ، الافتراضي 1
        };
        if (isEdit) body.id = parseInt(id);

        try {
            AF.Loading.show(el.btnSubmit, getT(isEdit ? 'form.buttons.updating' : 'form.buttons.saving'));

            const response = await AF.api(API, {
                method: isEdit ? 'PUT' : 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(body)
            });

            if (!response?.success) throw new Error(response?.message || getT('messages.error.save_failed'));

            const savedId = response.data?.id || (isEdit ? parseInt(id) : null);

            if (savedId && translations.length > 0) {
                await saveTranslations(savedId, translations);
            }

            if (deletedTranslations.length > 0) {
                await deleteTranslations(deletedTranslations);
            }

            AF.success(getT(isEdit ? 'messages.success.updated' : 'messages.success.created'));
            hideForm();
            deletedTranslations = [];
            await load(state.page);
        } catch (err) {
            console.error('[CertificatesProducts] save error', err);
            AF.error(err.message || getT('messages.error.save_failed'));
        } finally {
            AF.Loading.hide(el.btnSubmit);
        }
    }

    async function saveTranslations(productId, translations) {
        for (const tr of translations) {
            if (!tr.name) continue;
            try {
                await AF.api(TRANS_API, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        product_id: productId,
                        language_code: tr.language_code,
                        name: tr.name
                    })
                });
            } catch (e) {
                console.warn('[CertificatesProducts] translation save failed for', tr.language_code, e.message);
            }
        }
    }

    async function deleteTranslations(list) {
        for (const tr of list) {
            if (!tr.product_id) continue;
            try {
                await AF.api(TRANS_API, {
                    method: 'DELETE',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        product_id: tr.product_id,
                        language_code: tr.language_code
                    })
                });
            } catch (e) {
                console.warn('[CertificatesProducts] translation delete failed', e.message);
            }
        }
    }

    async function edit(id) {
        try {
            const url = buildUrl(API, { id, lang: state.language, tenant_id: state.currentTenantId });
            const data = await apiGet(url);
            const item = Array.isArray(data) ? data[0] : data;
            if (!item || !item.id) throw new Error(getT('messages.error.not_found'));

            if (item.tenant_id && item.tenant_id != state.currentTenantId) {
                await loadEntitiesAndBrandsForTenant(item.tenant_id);
            }

            resetForm();
            showForm(getT('form.edit_title'));

            el.formId.value = String(item.id);
            el.entityId.value = item.entity_id ? String(item.entity_id) : '';
            el.brandId.value = item.brand_id ? String(item.brand_id) : '';
            el.productCode.value = item.entity_product_code || '';
            el.netWeight.value = item.net_weight ?? '';
            el.weightUnit.value = item.weight_unit || 'kg';
            el.sampleStatus.value = item.sample_status || 'normal';
            el.productCondition.value = item.product_condition || 'dry';
            el.originCountryId.value = item.origin_country_id ? String(item.origin_country_id) : '';   // جديد

            deletedTranslations = [];
            try {
                const tUrl = buildUrl(TRANS_API, { product_id: id, limit: 100 });
                const tData = await apiGet(tUrl);
                extractItems(tData).forEach(tr => createTranslationPanel(tr.language_code, tr));
            } catch (e) {
                console.warn('[CertificatesProducts] translations load failed for edit', e.message);
            }

            setTimeout(() => {
                document.getElementById('productFormContainer')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }, 150);
        } catch (err) {
            console.error('[CertificatesProducts] edit error', err);
            AF.error(getT('messages.error.load_failed'));
        }
    }

    function add() {
        resetForm();
        showForm(getT('form.add_title'));
        deletedTranslations = [];
        // تعيين القيمة الافتراضية لبلد المنشأ (اختياري)
        if (el.originCountryId && state.countries.length > 0) {
            // محاولة تعيين البلد ذو id = 1 كافتراضي
            const defaultCountry = state.countries.find(c => c.id == 1);
            if (defaultCountry) el.originCountryId.value = 1;
        }
    }

    async function remove(id) {
        AF.Modal.confirm(getT('table.actions.confirm_delete'), async () => {
            try {
                await AF.api(API, {
                    method: 'DELETE',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id, tenant_id: state.currentTenantId })
                });
                AF.success(getT('messages.success.deleted'));
                await load(state.page);
            } catch (err) {
                console.error('[CertificatesProducts] delete error', err);
                AF.error(getT('messages.error.delete_failed'));
            }
        });
    }

    // ======================================================================
    // 8. لوحات الترجمة (دون حقل brand)
    // ======================================================================

    function createTranslationPanel(code, data = {}) {
        if (!el.translations) return;
        const existing = el.translations.querySelector(`[data-lang="${code}"]`);
        if (existing) existing.remove();

        const langUp = code.toUpperCase();
        const div = document.createElement('div');
        div.className = 'translation-panel';
        div.dataset.lang = code;
        div.innerHTML = `
            <div class="translation-panel-header">
                <h5><i class="fas fa-globe"></i> ${langUp}</h5>
                <button type="button" class="remove btn btn-sm btn-danger">${getT('form.translations.remove', 'Remove')}</button>
            </div>
            <div class="translation-panel-body">
                <div class="form-row">
                    <div class="form-group">
                        <label>${getT('form.fields.name.label', 'Name')} *</label>
                        <input class="form-control" name="translations[${code}][name]" value="${escHtml(data.name || '')}" placeholder="${tReplace('form.translations.name_in_lang', { lang: langUp })}" required>
                    </div>
                </div>
            </div>
        `;
        div.querySelector('.remove').onclick = () => {
            const productId = el.formId?.value ? parseInt(el.formId.value) : null;
            deletedTranslations.push({ language_code: code, product_id: productId });
            div.remove();
        };
        el.translations.appendChild(div);
    }

    // ======================================================================
    // 9. الفلاتر
    // ======================================================================

    function applyFilters() {
        state.filters = {};
        if (el.searchInput?.value.trim()) state.filters.entity_product_code = el.searchInput.value.trim();
        if (el.tenantFilter?.value && state.permissions.isSuperAdmin) state.filters.tenant_id = el.tenantFilter.value.trim();
        if (el.entityFilter?.value) state.filters.entity_id = el.entityFilter.value;
        if (el.brandFilter?.value) state.filters.brand_id = el.brandFilter.value;
        if (el.sampleStatusFilter?.value) state.filters.sample_status = el.sampleStatusFilter.value;
        if (el.conditionFilter?.value) state.filters.product_condition = el.conditionFilter.value;
        if (el.originCountryFilter?.value) state.filters.origin_country_id = el.originCountryFilter.value;   // جديد
        load(1);
    }

    function resetFilters() {
        ['searchInput', 'entityFilter', 'brandFilter', 'sampleStatusFilter', 'conditionFilter', 'originCountryFilter'].forEach(k => {
            if (el[k]) el[k].value = '';
        });
        if (el.tenantFilter && state.permissions.isSuperAdmin) {
            el.tenantFilter.value = '';
        }
        state.filters = {};
        load(1);
    }

    // ======================================================================
    // 10. دوال واجهة المستخدم المساعدة
    // ======================================================================

    function showLoading() {
        if (el.loading) {
            el.loading.innerHTML = `<div class="spinner"></div><p>${getT('certificates_products.loading')}</p>`;
            el.loading.style.display = 'flex';
        }
        if (el.container) el.container.style.display = 'none';
        if (el.empty) el.empty.style.display = 'none';
        if (el.error) el.error.style.display = 'none';
    }

    function showEmpty() {
        if (el.empty) {
            el.empty.style.display = 'flex';
            const t = el.empty.querySelector('h3');
            const m = el.empty.querySelector('p');
            const b = el.empty.querySelector('button');
            if (t) t.textContent = getT('table.empty.title');
            if (m) m.textContent = getT('table.empty.message');
            if (b) {
                b.innerHTML = `<i class="fas fa-plus"></i> ${getT('table.empty.add_first')}`;
                b.onclick = add;
            }
        }
        if (el.container) el.container.style.display = 'none';
        if (el.error) el.error.style.display = 'none';
        if (el.loading) el.loading.style.display = 'none';
        if (el.tbody) el.tbody.innerHTML = '';
    }

    function showError(msg) {
        if (el.error) {
            el.error.style.display = 'flex';
            const m = el.error.querySelector('#errorMessage');
            if (m) m.textContent = msg;
        }
        if (el.container) el.container.style.display = 'none';
        if (el.empty) el.empty.style.display = 'none';
        if (el.loading) el.loading.style.display = 'none';
    }

    function showForm(title) {
        const fc = document.getElementById('productFormContainer');
        if (!fc) return;
        fc.style.display = 'block';
        const t = fc.querySelector('.card-title');
        if (t) t.textContent = title || '';
    }

    function hideForm() {
        const fc = document.getElementById('productFormContainer');
        if (fc) fc.style.display = 'none';
    }

    function resetForm() {
        if (el.form) el.form.reset();
        if (el.formId) el.formId.value = '';
        if (el.translations) el.translations.innerHTML = '';
        el.form?.classList.remove('was-validated');
    }

    function escHtml(str) {
        if (!str) return '';
        return String(str).replace(/[&<>"]/g, m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[m]));
    }

    // ======================================================================
    // 11. التهيئة (Initialization)
    // ======================================================================

    async function init() {
        console.log('[CertificatesProducts] init start');

        await loadTranslations(state.language);

        el = {
            loading: document.getElementById('tableLoading'),
            container: document.getElementById('tableContainer'),
            empty: document.getElementById('emptyState'),
            error: document.getElementById('errorState'),
            tbody: document.getElementById('tableBody'),
            pagination: document.getElementById('pagination'),
            paginationInfo: document.getElementById('paginationInfo'),
            form: document.getElementById('productForm'),
            formId: document.getElementById('formId'),
            entityId: document.getElementById('entityId'),
            brandId: document.getElementById('brandId'),
            productCode: document.getElementById('productCode'),
            netWeight: document.getElementById('netWeight'),
            weightUnit: document.getElementById('weightUnit'),
            sampleStatus: document.getElementById('sampleStatus'),
            productCondition: document.getElementById('productCondition'),
            originCountryId: document.getElementById('originCountryId'),   // جديد
            btnSubmit: document.getElementById('btnSubmitForm'),
            btnAdd: document.getElementById('btnAddProduct'),
            btnClose: document.getElementById('btnCloseForm'),
            btnCancel: document.getElementById('btnCancelForm'),
            btnDelete: document.getElementById('btnDeleteProduct'),
            searchInput: document.getElementById('searchInput'),
            tenantFilter: document.getElementById('tenantFilter'),
            entityFilter: document.getElementById('entityFilter'),
            brandFilter: document.getElementById('brandFilter'),
            sampleStatusFilter: document.getElementById('sampleStatusFilter'),
            conditionFilter: document.getElementById('conditionFilter'),
            originCountryFilter: document.getElementById('originCountryFilter'),   // جديد
            btnApply: document.getElementById('btnApplyFilters'),
            btnReset: document.getElementById('btnResetFilters'),
            btnRetry: document.getElementById('btnRetry'),
            langSelect: document.getElementById('langSelect'),
            addLangBtn: document.getElementById('addLangBtn'),
            translations: document.getElementById('productTranslations'),
            resultsCount: document.getElementById('resultsCount'),
            resultsCountText: document.getElementById('resultsCountText'),
            tenantIdInput: document.getElementById('tenantId')
        };

        if (state.permissions.isSuperAdmin && el.tenantIdInput) {
            el.tenantIdInput.addEventListener('input', e => {
                clearTimeout(verifyTenantTimeout);
                const v = e.target.value.trim();
                if (!v) {
                    showTenantMsg('Tenant ID required', false);
                    return;
                }
                verifyTenantTimeout = setTimeout(() => verifyTenant(parseInt(v)), 600);
            });
            if (el.tenantIdInput.value) {
                await verifyTenant(parseInt(el.tenantIdInput.value));
            }
        }

        // تحميل كل البيانات المساعدة
        await loadDependencies();

        // ربط الأحداث
        if (el.form) el.form.onsubmit = save;
        if (el.btnAdd) el.btnAdd.onclick = add;
        if (el.btnClose) el.btnClose.onclick = hideForm;
        if (el.btnCancel) el.btnCancel.onclick = hideForm;
        if (el.btnApply) el.btnApply.onclick = applyFilters;
        if (el.btnReset) el.btnReset.onclick = resetFilters;
        if (el.btnRetry) el.btnRetry.onclick = () => load(state.page);
        if (el.addLangBtn) {
            el.addLangBtn.onclick = () => {
                const code = el.langSelect?.value;
                if (code) createTranslationPanel(code, {});
            };
        }

        await load(1);
        console.log('[CertificatesProducts] init complete');
    }

    // ======================================================================
    // واجهة العامة (Public API)
    // ======================================================================
    window.CertificatesProducts = { init, load, edit, remove, add };

    // ======================================================================
    // التشغيل التلقائي إذا كان الإطار متاحاً
    // ======================================================================
    if (window.AdminFramework && !window.__certProductsInit) {
        window.__certProductsInit = true;
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', init);
        } else {
            init();
        }
    }
})();