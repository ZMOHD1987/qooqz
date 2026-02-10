(function(){
    'use strict';

    /**
     * /admin/assets/js/pages/products.js
     * Products Management Module - Complete Implementation
     * Based on Categories pattern with advanced product features
     */

    // ════════════════════════════════════════════════════════════
    // CONFIGURATION & STATE
    // ════════════════════════════════════════════════════════════
    const CONFIG = window.PRODUCTS_CONFIG || {};
    const AF = window.AdminFramework || {};
    const PERMS = window.PAGE_PERMISSIONS || {};
    
    const API = {
        products: CONFIG.apiUrl || '/api/products',
        categories: CONFIG.categoriesApi || '/api/categories',
        brands: CONFIG.brandsApi || '/api/brands',
        productTypes: CONFIG.productTypesApi || '/api/product_types',
        attributes: CONFIG.attributesApi || '/api/product_attributes',
        attributeValues: CONFIG.attributeValuesApi || '/api/product_attribute_values',
        currencies: CONFIG.currenciesApi || '/api/currencies',
        languages: CONFIG.languagesApi || '/api/languages',
        images: CONFIG.imagesApi || '/api/images',
        tenants: CONFIG.tenantsApi || '/api/tenants'
    };

    const state = {
        page: 1,
        perPage: CONFIG.itemsPerPage || 25,
        total: 0,
        products: [],
        categories: [],
        brands: [],
        productTypes: [],
        attributes: [],
        languages: [],
        currencies: [],
        filters: {},
        currentProduct: null,
        selectedImages: [],
        selectedCategories: [],
        productAttributes: [],
        productVariants: [],
        permissions: PERMS,
        language: window.USER_LANGUAGE || CONFIG.lang || 'en',
        direction: window.USER_DIRECTION || 'ltr',
        csrfToken: window.CSRF_TOKEN || CONFIG.csrfToken || '',
        tenantId: window.APP_CONFIG?.TENANT_ID || 1
    };

    let el = {}; // DOM elements cache
    let translations = {}; // i18n translations
    let _messageListenerAdded = false; // prevent duplicate message listeners

    // ════════════════════════════════════════════════════════════
    // TRANSLATIONS
    // ════════════════════════════════════════════════════════════
    async function loadTranslations(lang) {
        try {
            const url = `/languages/Product/${encodeURIComponent(lang || state.language)}.json`;
            console.log('[Products] Loading translations:', url);
            const res = await fetch(url, { credentials: 'same-origin' });
            if (!res.ok) throw new Error(`Failed to load translations: ${res.status}`);
            const raw = await res.json();
            const s = raw.strings || raw;
            // Build translations matching data-i18n keys used in products.php
            translations = buildTranslationsMap(s);
            // Set direction from translation file
            if (raw.direction) setDirectionForLang(raw.direction === 'rtl' ? 'ar' : 'en');
            console.log('[Products] Translations loaded');
            applyTranslations();
        } catch (err) {
            console.warn('[Products] Translation load failed:', err);
            translations = {};
        }
    }

    function buildTranslationsMap(s) {
        const g = s.general || {};
        const p = s.pricing || {};
        const inv = s.inventory || {};
        const dim = s.dimensions || {};
        const med = s.media || {};
        const cat = s.categories || {};
        const attr = s.attributes || {};
        const vr = s.variants || {};
        const tr = s.translations || {};
        const val = s.validation || {};
        const msg = s.messages || {};
        return {
            // products.* keys
            products: {
                title: s.title || s.products,
                subtitle: s.product || s.title,
                add_new: s.create,
                loading: s.loading,
                retry: s.refresh || 'Retry'
            },
            // tabs.* keys (top-level, NOT under form)
            tabs: {
                general: g.general, pricing: p.pricing, inventory: inv.inventory,
                attributes: attr.attributes, variants: vr.variants,
                images: med.images, categories: cat.categories,
                translations: tr.translations
            },
            // form.* keys
            form: {
                add_title: s.create,
                edit_title: s.edit,
                fields: {
                    name: { label: g.name, placeholder: g.name, required: val.name_required },
                    sku: { label: g.sku, placeholder: g.sku },
                    slug: { label: g.slug, placeholder: g.slug },
                    barcode: { label: g.barcode, placeholder: g.barcode },
                    product_type: { label: g.type },
                    brand: { label: g.brand },
                    main_category: { label: cat.categories },
                    sub_category: { label: cat.hierarchy_info },
                    categories: { label: cat.categories },
                    price: { label: p.price },
                    compare_price: { label: p.compare_at_price },
                    cost_price: { label: p.cost_price },
                    tax_rate: { label: p.tax_rate },
                    currency: { label: g.select || 'Currency' },
                    pricing_type: { label: p.pricing },
                    stock_quantity: { label: inv.stock_quantity },
                    low_stock_threshold: { label: inv.low_stock_threshold },
                    stock_status: { label: inv.stock_status,
                        in_stock: inv.in_stock, out_of_stock: inv.out_of_stock, on_backorder: inv.on_backorder
                    },
                    manage_stock: { label: inv.manage_stock, yes: g.yes, no: g.no },
                    allow_backorder: { label: inv.allow_backorder, yes: g.yes, no: g.no },
                    featured: { label: s.featured || 'Featured', yes: g.yes, no: g.no },
                    bestseller: { label: s.bestseller || 'Bestseller', yes: g.yes, no: g.no },
                    new: { label: s.new_product || 'New', yes: g.yes, no: g.no },
                    status: { label: s.active || 'Status', active: s.active, inactive: s.inactive },
                    weight: { label: dim.weight },
                    length: { label: dim.length },
                    width: { label: dim.width },
                    height: { label: dim.height },
                    weight_unit: { label: dim.weight },
                    dimension_unit: { label: dim.dimensions },
                    images: { label: med.images },
                    short_description: { label: g.short_description },
                    description: { label: g.description },
                    specifications: { label: tr.specifications },
                    meta_title: { label: tr.meta_title },
                    meta_description: { label: tr.meta_description },
                    meta_keywords: { label: tr.meta_keywords }
                },
                buttons: {
                    save: s.save, cancel: s.cancel,
                    add_attribute: attr.add_attribute,
                    add_variant: vr.variants,
                    generate_variants: vr.generate_variants
                },
                sections: { physical: dim.dimensions },
                translations: { select_lang: tr.add_language }
            },
            // filters.* keys
            filters: {
                search: s.search_placeholder,
                search_placeholder: s.search_placeholder,
                product_type: g.type, brand: g.brand,
                status: inv.stock_status, tenant_id: 'Tenant',
                tenant_placeholder: 'Tenant ID',
                status_options: { all: s.total, active: s.active, inactive: s.inactive },
                apply: s.save, reset: s.cancel
            },
            // common.* keys
            common: { select_image: med.select_from_studio },
            // table.* keys
            table: {
                headers: {
                    id: 'ID', tenant: 'Tenant', image: med.images,
                    name: g.name, sku: g.sku, type: g.type,
                    price: p.price, stock: inv.stock_quantity,
                    status: s.active, actions: s.actions
                },
                empty: {
                    title: s.no_products,
                    message: s.create || 'Add your first product',
                    add_first: s.create
                },
                actions: { delete: s.delete }
            },
            // pagination.* keys
            pagination: { showing: s.total || 'Showing' },
            // messages.* keys
            messages: {
                error: { load_failed: msg.server_error || 'Error loading data' }
            },
            // strings used internally
            strings: { save_success: s.save_success, update_success: s.update_success,
                delete_confirm: s.delete_confirm, delete_success: s.delete_success,
                saving: s.saving, loading: s.loading
            }
        };
    }

    function t(key, fallback = '') {
        const keys = key.split('.');
        let val = translations;
        for (const k of keys) {
            if (val && typeof val === 'object' && k in val) {
                val = val[k];
            } else {
                return fallback || key;
            }
        }
        return val !== undefined && val !== null ? String(val) : (fallback || key);
    }

    function applyTranslations() {
        const container = document.getElementById('productsPageContainer');
        if (!container) return;
        
        container.querySelectorAll('[data-i18n]').forEach(elem => {
            const key = elem.getAttribute('data-i18n');
            const txt = t(key);
            if (txt !== key) {
                if (elem.tagName === 'INPUT' && elem.type !== 'submit' && elem.type !== 'button') {
                    if (elem.hasAttribute('placeholder')) elem.placeholder = txt;
                } else {
                    elem.textContent = txt;
                }
            }
        });

        container.querySelectorAll('[data-i18n-placeholder]').forEach(elem => {
            const key = elem.getAttribute('data-i18n-placeholder');
            const txt = t(key);
            if (txt !== key) elem.placeholder = txt;
        });

        console.log('[Products] Translations applied to DOM');
    }

    function setDirectionForLang(lang) {
        const container = document.getElementById('productsPageContainer');
        if (container) {
            container.setAttribute('dir', lang === 'ar' ? 'rtl' : 'ltr');
        }
        state.direction = lang === 'ar' ? 'rtl' : 'ltr';
    }

    // ════════════════════════════════════════════════════════════
    // API HELPERS
    // ════════════════════════════════════════════════════════════
    async function apiCall(url, options = {}) {
        const defaults = {
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        };

        if (options.method && options.method !== 'GET') {
            defaults.headers['X-CSRF-Token'] = state.csrfToken;
        }

        const config = { ...defaults, ...options };
        if (config.headers && options.headers) {
            config.headers = { ...defaults.headers, ...options.headers };
        }

        try {
            const res = await fetch(url, config);
            const contentType = res.headers.get('content-type');
            
            if (contentType && contentType.includes('application/json')) {
                const data = await res.json();
                if (!res.ok) {
                    throw new Error(data.error || data.message || `HTTP ${res.status}`);
                }
                return data;
            } else {
                const text = await res.text();
                if (!res.ok) {
                    throw new Error(text || `HTTP ${res.status}`);
                }
                try {
                    return JSON.parse(text);
                } catch {
                    return { success: true, data: text };
                }
            }
        } catch (err) {
            console.error('[Products] API call failed:', url, err);
            throw err;
        }
    }

    // ════════════════════════════════════════════════════════════
    // DATA LOADING
    // ════════════════════════════════════════════════════════════
    async function loadProducts(page = 1) {
        try {
            console.log('[Products] Loading page:', page);

            showLoading();

            state.page = page;
            const params = new URLSearchParams({
                page: page,
                limit: state.perPage,
                tenant_id: state.tenantId,
                lang: state.language,
                format: 'json'
            });

            // Add filters (skip empty values)
            Object.entries(state.filters).forEach(([key, val]) => {
                if (val !== undefined && val !== null && val !== '') {
                    params.set(key, val);
                }
            });

            console.log('[Products] API URL:', `${API.products}?${params}`);

            const result = await apiCall(`${API.products}?${params}`);
            console.log('[Products] API response:', result);

            if (result.success && result.data) {
                // API returns { data: { items: [], meta: {} } }
                const items = result.data.items || result.data;
                const meta = result.data.meta || result.meta || {};
                
                state.products = Array.isArray(items) ? items : [];
                state.total = meta.total || state.products.length;
                
                await renderTable(state.products);
                updatePagination(meta.total !== undefined ? meta : { page, per_page: state.perPage, total: state.total });
                updateResultsCount(state.total);
                
                showTable();
            } else {
                throw new Error(result.error || result.message || 'Invalid response format');
            }
        } catch (err) {
            console.error('[Products] Load failed:', err);
            showError(err.message || t('messages.error.load_failed', 'Failed to load products'));
        }
    }

    async function loadDropdownData() {
        try {
            // Load product types
            try {
                const typesResult = await apiCall(`${API.productTypes}?format=json&lang=${state.language}`);
                if (typesResult.success) {
                    // product_types returns { data: { data: [...], total: N } }
                    const typesData = typesResult.data?.data || typesResult.data?.items || typesResult.data;
                    state.productTypes = Array.isArray(typesData) ? typesData : [];
                    populateDropdown(el.prodType, state.productTypes, 'id', 'name', t('form.fields.product_type.select', 'Select product type'));
                    populateDropdown(el.typeFilter, state.productTypes, 'id', 'name', t('filters.all_types', 'All Types'));
                }
            } catch (err) {
                console.warn('[Products] Failed to load product types:', err);
            }

            // Load brands
            try {
                const brandsResult = await apiCall(`${API.brands}?format=json&tenant_id=${state.tenantId}&lang=${state.language}`);
                if (brandsResult.success) {
                    // brands returns { data: [...] } (array directly)
                    const brandsData = Array.isArray(brandsResult.data) ? brandsResult.data : (brandsResult.data?.items || brandsResult.data?.data || []);
                    state.brands = brandsData;
                    populateDropdown(el.prodBrand, state.brands, 'id', 'name', t('form.fields.brand.select', 'Select brand'));
                    populateDropdown(el.brandFilter, state.brands, 'id', 'name', t('filters.all_brands', 'All Brands'));
                }
            } catch (err) {
                console.warn('[Products] Failed to load brands:', err);
            }

            // Load categories (fetch ALL for tree - need page & limit)
            try {
                const categoriesResult = await apiCall(`${API.categories}?page=1&limit=1000&tenant_id=${state.tenantId}&lang=${state.language}&format=json`);
                if (categoriesResult.success) {
                    // categories returns { data: { items: [...], meta: {} } }
                    const categoriesData = categoriesResult.data?.items || categoriesResult.data;
                    state.categories = Array.isArray(categoriesData) ? categoriesData : [];
                    renderCategoriesTree();
                    populateMainCategoryDropdown();
                }
            } catch (err) {
                console.warn('[Products] Failed to load categories:', err);
            }

            // Load currencies from API
            try {
                const currenciesResult = await apiCall(`${API.currencies}?format=json`);
                if (currenciesResult.success) {
                    const currData = Array.isArray(currenciesResult.data) ? currenciesResult.data : (currenciesResult.data?.items || currenciesResult.data?.data || []);
                    state.currencies = currData;
                    populateDropdown(el.prodCurrency, state.currencies, 'code', 'name', t('form.fields.currency.select', 'Select currency'));
                }
            } catch (err) {
                console.warn('[Products] Failed to load currencies from API:', err);
                // Fallback: populate with common currencies
                state.currencies = [
                    { code: 'SAR', name: 'SAR - Saudi Riyal' },
                    { code: 'USD', name: 'USD - US Dollar' },
                    { code: 'EUR', name: 'EUR - Euro' },
                    { code: 'GBP', name: 'GBP - British Pound' },
                    { code: 'AED', name: 'AED - UAE Dirham' }
                ];
                populateDropdown(el.prodCurrency, state.currencies, 'code', 'name', t('form.fields.currency.select', 'Select currency'));
            }

            // Load attributes
            try {
                const attributesResult = await apiCall(`${API.attributes}?format=json&lang=${state.language}`);
                if (attributesResult.success) {
                    const attrData = Array.isArray(attributesResult.data) ? attributesResult.data : (attributesResult.data?.items || attributesResult.data?.data || []);
                    state.attributes = attrData;
                    populateAttributeSelect(state.attributes);
                }
            } catch (err) {
                console.warn('[Products] Failed to load attributes:', err);
            }

            // Load languages
            try {
                const languagesResult = await apiCall(`${API.languages}?format=json`);
                if (languagesResult.success) {
                    // languages returns { data: { items: [...], meta: {} } }
                    const langsData = languagesResult.data?.items || languagesResult.data;
                    state.languages = Array.isArray(langsData) ? langsData : [];
                    populateDropdown(el.prodLangSelect, state.languages, 'code', 'name', t('form.translations.select_lang', 'Select language'));
                }
            } catch (err) {
                console.warn('[Products] Failed to load languages:', err);
            }

            console.log('[Products] Dropdown data loaded');
        } catch (err) {
            console.error('[Products] Failed to load dropdown data:', err);
        }
    }

    function populateDropdown(selectEl, data, valueKey, textKey, placeholder = '') {
        if (!selectEl) return;
        
        selectEl.innerHTML = '';
        
        if (placeholder) {
            const opt = document.createElement('option');
            opt.value = '';
            opt.textContent = placeholder;
            selectEl.appendChild(opt);
        }

        data.forEach(item => {
            const opt = document.createElement('option');
            opt.value = item[valueKey];
            opt.textContent = item[textKey];
            selectEl.appendChild(opt);
        });
    }

    function populateAttributeSelect(attributes) {
        if (!el.attrSelect) return;
        
        el.attrSelect.innerHTML = '<option value="">' + t('form.attributes.select', 'Select attribute') + '</option>';
        
        attributes.forEach(attr => {
            const opt = document.createElement('option');
            opt.value = attr.id;
            opt.textContent = attr.name || attr.slug;
            opt.dataset.type = attr.attribute_type_id;
            opt.dataset.isVariation = attr.is_variation || 0;
            el.attrSelect.appendChild(opt);
        });
    }

    // ════════════════════════════════════════════════════════════
    // RENDERING
    // ════════════════════════════════════════════════════════════
    async function renderTable(items) {
        console.log('[Products] Rendering table with', items?.length || 0, 'items');

        if (!el.tbody) {
            console.error('[Products] tbody element not found!');
            return;
        }

        if (!items || !items.length) {
            console.log('[Products] No items, showing empty state');
            showEmpty();
            return;
        }

        const isSuperAdmin = state.permissions.isSuperAdmin;

        el.tbody.innerHTML = items.map(prod => {
            const image = prod.main_image_url || prod.image_url || '';
            const name = prod.name || prod.slug || `Product #${prod.id}`;
            const price = prod.price ? Number(prod.price).toFixed(2) : '0.00';
            const currency = prod.currency_code || 'SAR';
            const stock = prod.stock_quantity || 0;
            const statusBadge = prod.is_active == 1 
                ? `<span class="badge badge-active">${t('table.status.active', 'Active')}</span>`
                : `<span class="badge badge-inactive">${t('table.status.inactive', 'Inactive')}</span>`;

            const canEdit = state.permissions.canEdit || state.permissions.canEditAll || 
                           (state.permissions.canEditOwn && prod.created_by_user_id == window.APP_CONFIG?.USER_ID);
            const canDelete = state.permissions.canDelete || state.permissions.canDeleteAll || 
                             (state.permissions.canDeleteOwn && prod.created_by_user_id == window.APP_CONFIG?.USER_ID);
            
            return `
                <tr data-id="${prod.id}">
                    <td>${esc(prod.id)}</td>
                    ${isSuperAdmin ? `<td>${esc(prod.tenant_id || '')}</td>` : ''}
                    <td>
                        ${image ? `<img src="${esc(image)}" alt="${esc(name)}" style="width:50px;height:50px;object-fit:cover;border-radius:4px;">` : '📦'}
                    </td>
                    <td><strong>${esc(name)}</strong><br><small style="color:var(--text-secondary,#94a3b8);">${esc(prod.sku || '')}</small></td>
                    <td>${esc(prod.sku || '-')}</td>
                    <td>${esc(prod.product_type_name || '-')}</td>
                    <td>${price} ${esc(currency)}</td>
                    <td>${esc(stock)}</td>
                    <td>${statusBadge}</td>
                    <td>
                        <div class="table-actions">
                            ${canEdit ? `<button class="btn btn-sm btn-secondary" onclick="Products.edit(${prod.id})" title="${t('table.actions.edit', 'Edit')}">
                                <i class="fas fa-edit"></i>
                            </button>` : ''}
                            ${state.permissions.canDuplicate ? `<button class="btn btn-sm btn-secondary" onclick="Products.duplicate(${prod.id})" title="${t('table.actions.duplicate', 'Duplicate')}">
                                <i class="fas fa-copy"></i>
                            </button>` : ''}
                            ${canDelete ? `<button class="btn btn-sm btn-danger" onclick="Products.remove(${prod.id})" title="${t('table.actions.delete', 'Delete')}">
                                <i class="fas fa-trash"></i>
                            </button>` : ''}
                        </div>
                    </td>
                </tr>
            `;
        }).join('');

        console.log('[Products] Table rendered');
    }

    // ════════════════════════════════════════════════════════════
    // FORM MANAGEMENT
    // ════════════════════════════════════════════════════════════
    function showForm(product = null) {
        // Re-cache form elements in case they weren't found during init
        if (!el.formContainer) el.formContainer = document.getElementById('productFormContainer');
        if (!el.form) el.form = document.getElementById('productForm');
        if (!el.formTitle) el.formTitle = document.getElementById('formTitle');
        if (!el.formId) el.formId = document.getElementById('formId');
        if (!el.prodTenantId) el.prodTenantId = document.getElementById('prodTenantId');

        if (!el.formContainer || !el.form) {
            console.error('[Products] showForm: formContainer or form not found in DOM');
            return;
        }

        state.currentProduct = product;
        state.selectedImages = [];
        state.selectedCategories = [];
        state.productAttributes = [];
        state.productVariants = [];

        el.form.reset();

        // Reset all tabs to show General tab
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach(c => c.style.display = 'none');
        const generalTab = document.querySelector('.tab-btn[data-tab="general"]');
        const generalContent = document.getElementById('tab-general');
        if (generalTab) generalTab.classList.add('active');
        if (generalContent) generalContent.style.display = 'block';

        if (product) {
            if (el.formTitle) el.formTitle.textContent = t('form.edit_title', 'Edit Product');
            if (el.formId) el.formId.value = product.id || '';
            if (el.prodName) el.prodName.value = product.name || '';
            if (el.prodSku) el.prodSku.value = product.sku || '';
            if (el.prodSlug) el.prodSlug.value = product.slug || '';
            if (el.prodBarcode) el.prodBarcode.value = product.barcode || '';
            if (el.prodType) el.prodType.value = product.product_type_id || '';
            if (el.prodBrand) el.prodBrand.value = product.brand_id || '';
            if (el.prodIsActive) el.prodIsActive.value = product.is_active || '1';
            if (el.prodIsFeatured) el.prodIsFeatured.value = product.is_featured || '0';
            if (el.prodIsBestseller) el.prodIsBestseller.value = product.is_bestseller || '0';
            if (el.prodIsNew) el.prodIsNew.value = product.is_new || '0';
            
            // Pricing
            if (el.prodPrice) el.prodPrice.value = product.price || '';
            if (el.prodComparePrice) el.prodComparePrice.value = product.compare_at_price || '';
            if (el.prodCostPrice) el.prodCostPrice.value = product.cost_price || '';
            if (el.prodCurrency) el.prodCurrency.value = product.currency_code || 'SAR';
            if (el.prodTaxRate) el.prodTaxRate.value = product.tax_rate || '';
            
            // Inventory
            if (el.prodStockQty) el.prodStockQty.value = product.stock_quantity || '0';
            if (el.prodLowStock) el.prodLowStock.value = product.low_stock_threshold || '5';
            if (el.prodStockStatus) el.prodStockStatus.value = product.stock_status || 'in_stock';
            if (el.prodManageStock) el.prodManageStock.value = product.manage_stock || '1';
            if (el.prodAllowBackorder) el.prodAllowBackorder.value = product.allow_backorder || '0';

            if (el.btnDeleteProduct) el.btnDeleteProduct.style.display = state.permissions.canDelete ? 'inline-block' : 'none';

            // Load related data from separate API tables
            if (product.id) {
                loadProductImages(product.id);
                loadProductCategories(product.id);
                loadProductAttributes(product.id);
                loadProductVariants(product.id);
                loadProductTranslations(product.id);
                loadProductPricing(product.id);
                loadPhysicalAttributes(product.id);
            }
        } else {
            if (el.formTitle) el.formTitle.textContent = t('form.add_title', 'Add Product');
            if (el.formId) el.formId.value = '';
            if (el.btnDeleteProduct) el.btnDeleteProduct.style.display = 'none';
            if (el.prodTenantId) el.prodTenantId.value = state.tenantId;
            // Clear images preview
            if (el.prodImagesPreview) el.prodImagesPreview.innerHTML = '';
            // Clear attributes list
            if (el.prodAttributesList) el.prodAttributesList.innerHTML = '';
            // Clear variants list
            if (el.prodVariantsList) el.prodVariantsList.innerHTML = '';
            // Clear translations
            if (el.prodTranslations) el.prodTranslations.innerHTML = '';
            // Reset category dropdowns
            if (el.prodMainCategory) el.prodMainCategory.value = '';
            if (el.prodSubCategory) {
                el.prodSubCategory.innerHTML = '<option value="">' + t('form.fields.sub_category.select', 'Select sub category') + '</option>';
            }
            // Render categories tree for new product
            renderCategoriesTree();
        }

        el.formContainer.style.display = 'block';
        el.formContainer.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    function hideForm() {
        if (el.formContainer) {
            el.formContainer.style.display = 'none';
        }
        state.currentProduct = null;
        if (el.form) el.form.reset();
    }

    // ════════════════════════════════════════════════════════════
    // TAB MANAGEMENT
    // ════════════════════════════════════════════════════════════
    function initTabs() {
        const tabButtons = document.querySelectorAll('.tab-btn');
        const tabContents = document.querySelectorAll('.tab-content');

        tabButtons.forEach(btn => {
            btn.addEventListener('click', () => {
                const targetTab = btn.dataset.tab;
                
                tabButtons.forEach(b => b.classList.remove('active'));
                tabContents.forEach(c => c.style.display = 'none');
                
                btn.classList.add('active');
                const targetContent = document.getElementById(`tab-${targetTab}`);
                if (targetContent) targetContent.style.display = 'block';
            });
        });
    }

    // ════════════════════════════════════════════════════════════
    // FORM SUBMISSION
    // ════════════════════════════════════════════════════════════
    async function saveProduct(e) {
        e.preventDefault();

        if (!validateForm()) {
            showNotification(t('messages.validation_failed', 'Please fill all required fields'), 'error');
            return;
        }

        try {
            const formData = new FormData(el.form);
            const productId = el.formId.value;
            const isEdit = !!productId;

            // Build product data object
            const productData = {
                name: formData.get('name'),
                sku: formData.get('sku') || null,
                slug: formData.get('slug') || generateSlug(formData.get('name')),
                barcode: formData.get('barcode') || null,
                product_type_id: formData.get('product_type_id') || null,
                brand_id: formData.get('brand_id') || null,
                tenant_id: formData.get('tenant_id') || state.tenantId,
                is_active: formData.get('is_active') || '1',
                is_featured: formData.get('is_featured') || '0',
                is_bestseller: formData.get('is_bestseller') || '0',
                is_new: formData.get('is_new') || '0',
                
                // Inventory
                stock_quantity: formData.get('stock_quantity') || '0',
                low_stock_threshold: formData.get('low_stock_threshold') || '5',
                stock_status: formData.get('stock_status') || 'in_stock',
                manage_stock: formData.get('manage_stock') || '1',
                allow_backorder: formData.get('allow_backorder') || '0',
                
                // Related data
                translations: collectTranslations(),
                categories: state.selectedCategories,
                attributes: state.productAttributes,
                variants: state.productVariants
            };

            if (isEdit) {
                productData.id = productId;
            }

            // Use the correct URL format (no path-based routing)
            const url = API.products;
            const method = isEdit ? 'PUT' : 'POST';

            const result = await apiCall(url, {
                method: method,
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(productData)
            });

            if (result.success) {
                const savedProductId = isEdit ? productId : (result.data?.id || result.data?.items?.[0]?.id);

                // Save pricing data separately via product_pricing API
                await savePricingData(savedProductId, formData);

                // Save physical attributes separately
                await savePhysicalAttributes(savedProductId, formData);

                // Save product categories
                await saveProductCategories(savedProductId, isEdit);

                // Save product attribute assignments
                await saveProductAttributeAssignments(savedProductId, isEdit);

                // Save product variants
                await saveProductVariants(savedProductId, isEdit);

                // Save product translations
                const translations = collectTranslations();
                if (Object.keys(translations).length > 0) {
                    await saveProductTranslations(savedProductId, translations);
                }

                showNotification(
                    isEdit ? t('messages.updated', 'Product updated successfully') : t('messages.created', 'Product created successfully'),
                    'success'
                );
                hideForm();
                loadProducts(state.page);
            } else {
                throw new Error(result.error || result.message || 'Save failed');
            }
        } catch (err) {
            console.error('[Products] Save failed:', err);
            showNotification(err.message || t('messages.error.save_failed', 'Failed to save product'), 'error');
        }
    }

    function generateSlug(name) {
        if (!name) return '';
        return name.toLowerCase()
            .replace(/[^\w\s-]/g, '')
            .replace(/[\s_]+/g, '-')
            .replace(/^-+|-+$/g, '')
            .substring(0, 255);
    }

    async function savePricingData(productId, formData) {
        try {
            const price = formData.get('price');
            if (price === null || price === undefined || price === '') return;

            const pricingData = {
                product_id: parseInt(productId),
                variant_id: null,
                price: parseFloat(price) || 0,
                compare_at_price: parseFloat(formData.get('compare_at_price')) || null,
                cost_price: parseFloat(formData.get('cost_price')) || null,
                currency_code: formData.get('currency_code') || 'SAR',
                tax_rate: parseFloat(formData.get('tax_rate')) || null,
                pricing_type: 'fixed',
                is_active: 1
            };

            // Check if pricing record already exists for this product
            let existingId = null;
            try {
                const existing = await apiCall(`/api/product_pricing?product_id=${productId}&format=json`);
                if (existing.success) {
                    const items = existing.data?.items || (Array.isArray(existing.data) ? existing.data : []);
                    const found = items.find(p => !p.variant_id || p.variant_id === null);
                    if (found) existingId = found.id;
                }
            } catch (e) { console.warn('[Products] Check existing pricing:', e); }

            if (existingId) {
                pricingData.id = parseInt(existingId);
                await apiCall('/api/product_pricing', {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(pricingData)
                });
            } else {
                await apiCall('/api/product_pricing', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(pricingData)
                });
            }
        } catch (err) {
            console.warn('[Products] Failed to save pricing:', err);
        }
    }

    async function savePhysicalAttributes(productId, formData) {
        try {
            const weight = formData.get('weight');
            const length = formData.get('length');
            const width = formData.get('width');
            const height = formData.get('height');
            if (!weight && !length && !width && !height) return;

            const physicalData = {
                product_id: parseInt(productId),
                weight: parseFloat(weight) || null,
                length: parseFloat(length) || null,
                width: parseFloat(width) || null,
                height: parseFloat(height) || null,
                weight_unit: formData.get('weight_unit') || 'kg',
                dimension_unit: formData.get('dimension_unit') || 'cm'
            };

            // Physical attributes repo does upsert (INSERT or UPDATE) based on product_id
            await apiCall('/api/product_physical_attributes', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(physicalData)
            });
        } catch (err) {
            console.warn('[Products] Failed to save physical attributes:', err);
        }
    }

    async function saveProductCategories(productId, isEdit = false) {
        try {
            // عند التعديل، حذف التصنيفات القديمة أولاً
            if (isEdit) {
                try {
                    // جلب التصنيفات الموجودة وحذفها
                    const existingResult = await apiCall(`/api/product_categories?product_id=${productId}&format=json`);
                    if (existingResult.success) {
                        const existingItems = existingResult.data?.items || (Array.isArray(existingResult.data) ? existingResult.data : []);
                        for (const item of existingItems) {
                            await apiCall('/api/product_categories', {
                                method: 'DELETE',
                                headers: { 'Content-Type': 'application/json' },
                                body: JSON.stringify({ id: parseInt(item.id) })
                            });
                        }
                    }
                } catch (err) {
                    console.warn('[Products] Failed to clear old categories:', err);
                }
            }

            // حفظ التصنيفات الجديدة
            for (const categoryId of state.selectedCategories) {
                await apiCall('/api/product_categories', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        product_id: parseInt(productId),
                        category_id: parseInt(categoryId),
                        is_primary: state.selectedCategories.indexOf(categoryId) === 0 ? 1 : 0,
                        sort_order: state.selectedCategories.indexOf(categoryId)
                    })
                });
            }
        } catch (err) {
            console.warn('[Products] Failed to save categories:', err);
        }
    }

    async function saveProductTranslations(productId, translations) {
        try {
            // Load existing translations to determine create vs update
            let existingTranslations = [];
            try {
                const existing = await apiCall(`/api/product_translations?product_id=${productId}&format=json`);
                if (existing.success) {
                    existingTranslations = Array.isArray(existing.data) ? existing.data : (existing.data?.items || []);
                }
            } catch (e) { console.warn('[Products] Check existing translations:', e); }

            for (const [langCode, trans] of Object.entries(translations)) {
                const transData = {
                    product_id: parseInt(productId),
                    language_code: langCode,
                    name: trans.name || '',
                    short_description: trans.short_description || '',
                    description: trans.description || '',
                    specifications: trans.specifications || '',
                    meta_title: trans.meta_title || '',
                    meta_description: trans.meta_description || '',
                    meta_keywords: trans.meta_keywords || ''
                };

                // Check if translation for this language already exists
                const existingTrans = existingTranslations.find(t => t.language_code === langCode);

                if (existingTrans) {
                    transData.id = parseInt(existingTrans.id);
                    await apiCall('/api/product_translations', {
                        method: 'PUT',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(transData)
                    });
                } else {
                    await apiCall('/api/product_translations', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(transData)
                    });
                }
            }
        } catch (err) {
            console.warn('[Products] Failed to save translations:', err);
        }
    }

    async function saveProductAttributeAssignments(productId, isEdit = false) {
        try {
            // عند التعديل، حذف التعيينات القديمة أولاً
            if (isEdit) {
                try {
                    await apiCall(`/api/product_attribute_assignments/by_product?product_id=${productId}`, {
                        method: 'DELETE'
                    });
                } catch (err) {
                    console.warn('[Products] Failed to clear old attribute assignments:', err);
                }
            }

            // حفظ التعيينات الجديدة
            for (const attr of state.productAttributes) {
                if (!attr.attribute_id) continue;

                const assignmentData = {
                    product_id: parseInt(productId),
                    attribute_id: parseInt(attr.attribute_id),
                    attribute_value_id: attr.attribute_value_id ? parseInt(attr.attribute_value_id) : null,
                    custom_value: attr.custom_value || attr.value || null
                };

                await apiCall('/api/product_attribute_assignments', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(assignmentData)
                });
            }
        } catch (err) {
            console.warn('[Products] Failed to save attribute assignments:', err);
        }
    }

    async function saveProductVariants(productId, isEdit = false) {
        try {
            if (!state.productVariants || state.productVariants.length === 0) return;

            // حفظ كل نسخة (variant)
            for (const variant of state.productVariants) {
                const variantData = {
                    product_id: parseInt(productId),
                    sku: variant.sku || null,
                    barcode: variant.barcode || null,
                    stock_quantity: parseInt(variant.stock_quantity) || 0,
                    low_stock_threshold: parseInt(variant.low_stock_threshold) || 5,
                    is_active: variant.is_active !== undefined ? parseInt(variant.is_active) : 1,
                    is_default: variant.is_default !== undefined ? parseInt(variant.is_default) : 0
                };

                // إذا كان لديه id = تحديث، وإلا = إنشاء جديد
                if (variant.id) {
                    variantData.id = parseInt(variant.id);
                }

                const method = variant.id ? 'PUT' : 'POST';

                const result = await apiCall(`/api/product_variants?tenant_id=${state.tenantId}`, {
                    method: method,
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(variantData)
                });

                // حفظ اسم النسخة كترجمة
                if (result.success && variant.name) {
                    const variantId = variant.id || result.data?.id;
                    if (variantId) {
                        try {
                            await apiCall(`/api/product_variants?tenant_id=${state.tenantId}`, {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json' },
                                body: JSON.stringify({
                                    variant_id: parseInt(variantId),
                                    translation: {
                                        language_code: state.language,
                                        name: variant.name
                                    }
                                })
                            });
                        } catch (err) {
                            console.warn('[Products] Failed to save variant translation:', err);
                        }
                    }
                }
            }
        } catch (err) {
            console.warn('[Products] Failed to save variants:', err);
        }
    }

    function validateForm() {
        let isValid = true;

        // Validate required fields - only name is required, SKU is auto-generated
        const requiredFields = [el.prodName];
        
        requiredFields.forEach(field => {
            if (!field || !field.value.trim()) {
                isValid = false;
                if (field) {
                    field.classList.add('is-invalid');
                    field.addEventListener('input', () => field.classList.remove('is-invalid'), { once: true });
                }
            }
        });

        return isValid;
    }

    // ════════════════════════════════════════════════════════════
    // ATTRIBUTES MANAGEMENT
    // ════════════════════════════════════════════════════════════
    async function addAttribute() {
        if (!el.attrSelect || !el.attrSelect.value) return;

        const attrId = el.attrSelect.value;
        const attrOption = el.attrSelect.options[el.attrSelect.selectedIndex];
        const attrName = attrOption.textContent;
        const attrType = attrOption.dataset.type;

        // Check if already added
        if (state.productAttributes.find(a => a.attribute_id == attrId)) {
            showNotification(t('messages.attribute_exists', 'Attribute already added'), 'warning');
            return;
        }

        // تحميل قيم السمة من API
        let attrValues = [];
        try {
            const valuesResult = await apiCall(`${API.attributeValues}?attribute_id=${encodeURIComponent(attrId)}&format=json`);
            if (valuesResult.success) {
                attrValues = Array.isArray(valuesResult.data) ? valuesResult.data : (valuesResult.data?.items || []);
            }
        } catch (err) {
            console.warn('[Products] Failed to load attribute values:', err);
        }

        const attr = {
            attribute_id: attrId,
            attribute_name: attrName,
            attribute_type: attrType,
            value: '',
            custom_value: '',
            attribute_value_id: null,
            available_values: attrValues
        };

        state.productAttributes.push(attr);
        renderAttributes();
        el.attrSelect.value = '';
    }

    function renderAttributes() {
        if (!el.prodAttributesList) return;

        el.prodAttributesList.innerHTML = state.productAttributes.map((attr, idx) => {
            // إذا كانت هناك قيم محددة مسبقاً، عرضها كقائمة منسدلة + حقل نص مخصص
            if (attr.available_values && attr.available_values.length > 0) {
                const options = attr.available_values.map(v => 
                    `<option value="${esc(v.id)}" ${String(v.id) === String(attr.attribute_value_id) ? 'selected' : ''}>${esc(v.label || v.value || v.name)}</option>`
                ).join('');
                return `
                    <div class="attribute-item" data-index="${idx}">
                        <label>${esc(attr.attribute_name)}</label>
                        <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                            <select class="form-control" style="flex:1;min-width:150px;" onchange="Products.updateAttributeValueId(${idx}, this.value)">
                                <option value="">${t('form.attributes.select_value', 'Select value')}</option>
                                ${options}
                            </select>
                            <input type="text" class="form-control" style="flex:1;min-width:120px;" 
                                   value="${esc(attr.custom_value || '')}" 
                                   placeholder="${t('form.attributes.custom_value', 'Custom value (optional)')}"
                                   onchange="Products.updateCustomValue(${idx}, this.value)">
                            <button type="button" class="btn btn-sm btn-danger" onclick="Products.removeAttribute(${idx})">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                `;
            }
            // خلاف ذلك حقل نص حر فقط
            return `
                <div class="attribute-item" data-index="${idx}">
                    <label>${esc(attr.attribute_name)}</label>
                    <div style="display:flex;gap:8px;align-items:center;">
                        <input type="text" class="form-control" value="${esc(attr.custom_value || attr.value || '')}" 
                               onchange="Products.updateCustomValue(${idx}, this.value)">
                        <button type="button" class="btn btn-sm btn-danger" onclick="Products.removeAttribute(${idx})">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
            `;
        }).join('');
    }

    function updateAttributeValue(index, value) {
        if (state.productAttributes[index]) {
            state.productAttributes[index].value = value;
            state.productAttributes[index].custom_value = value;
        }
    }

    function updateAttributeValueId(index, valueId) {
        if (state.productAttributes[index]) {
            state.productAttributes[index].attribute_value_id = valueId;
            // البحث عن قيمة النص من القائمة
            const vals = state.productAttributes[index].available_values || [];
            const found = vals.find(v => v.id == valueId);
            if (found) {
                state.productAttributes[index].value = found.value || found.label || '';
            }
        }
    }

    function updateCustomValue(index, value) {
        if (state.productAttributes[index]) {
            state.productAttributes[index].custom_value = value;
            state.productAttributes[index].value = value;
        }
    }

    function removeAttribute(index) {
        state.productAttributes.splice(index, 1);
        renderAttributes();
    }

    // ════════════════════════════════════════════════════════════
    // VARIANTS MANAGEMENT
    // ════════════════════════════════════════════════════════════
    function addVariant() {
        const variant = {
            id: null,
            sku: '',
            barcode: '',
            name: '',
            stock_quantity: 0,
            price: '',
            is_active: 1,
            is_default: 0
        };

        state.productVariants.push(variant);
        renderVariants();
    }

    function renderVariants() {
        if (!el.prodVariantsList) return;

        el.prodVariantsList.innerHTML = state.productVariants.map((variant, idx) => `
            <div class="variant-item card" data-index="${idx}" style="margin-bottom:12px; padding:12px;">
                <div class="form-row">
                    <div class="form-group" style="flex:1;">
                        <label>Name</label>
                        <input type="text" class="form-control" value="${esc(variant.name || '')}"
                               onchange="Products.updateVariantField(${idx}, 'name', this.value)">
                    </div>
                    <div class="form-group" style="flex:1;">
                        <label>SKU</label>
                        <input type="text" class="form-control" value="${esc(variant.sku || '')}"
                               placeholder="Auto-generated"
                               onchange="Products.updateVariantField(${idx}, 'sku', this.value)">
                    </div>
                    <div class="form-group" style="flex:1;">
                        <label>Barcode</label>
                        <input type="text" class="form-control" value="${esc(variant.barcode || '')}"
                               onchange="Products.updateVariantField(${idx}, 'barcode', this.value)">
                    </div>
                    <div class="form-group" style="width:100px;">
                        <label>Stock</label>
                        <input type="number" class="form-control" value="${esc(variant.stock_quantity || 0)}"
                               onchange="Products.updateVariantField(${idx}, 'stock_quantity', this.value)">
                    </div>
                    <div style="display:flex;align-items:flex-end;padding-bottom:8px;">
                        <button type="button" class="btn btn-sm btn-danger" onclick="Products.removeVariant(${idx})">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            </div>
        `).join('');
    }

    function updateVariantField(index, field, value) {
        if (state.productVariants[index]) {
            state.productVariants[index][field] = value;
        }
    }

    function removeVariant(index) {
        state.productVariants.splice(index, 1);
        renderVariants();
    }

    function generateVariantsFromAttributes() {
        // Collect variation attributes that have values
        const variationAttrs = state.productAttributes.filter(a => !!(a.attribute_value_id || a.value));

        if (variationAttrs.length === 0) {
            alert(t('strings.no_attributes', 'Please add attributes with values first'));
            return;
        }

        // Group attributes by attribute_id, dedup values by id
        const grouped = {};
        variationAttrs.forEach(a => {
            const key = a.attribute_id;
            if (!grouped[key]) grouped[key] = { name: a.attribute_name || a.slug || `Attr ${key}`, values: [] };
            const valId = a.attribute_value_id;
            if (valId && grouped[key].values.some(v => v.id === valId)) return; // skip duplicate
            const label = a.value_label || a.value || a.custom_value || `Value ${valId}`;
            grouped[key].values.push({ id: valId, label });
        });

        // Generate cartesian product of all attribute value combinations
        const attrKeys = Object.keys(grouped);
        const combinations = cartesian(attrKeys.map(k => grouped[k].values));

        if (combinations.length === 0) {
            alert(t('strings.no_combinations', 'No attribute combinations found'));
            return;
        }

        // Create variants from combinations
        const baseSku = document.getElementById('prodSku')?.value || 'VAR';
        combinations.forEach((combo, i) => {
            const nameParts = combo.map(v => v.label);
            const variant = {
                id: null,
                sku: `${baseSku}-${i + 1}`,
                barcode: '',
                name: nameParts.join(' / '),
                stock_quantity: 0,
                is_active: 1,
                is_default: i === 0 ? 1 : 0,
                _attributeValues: combo // Store attribute values for saving to product_variant_attributes
            };
            state.productVariants.push(variant);
        });

        renderVariants();
    }

    function cartesian(arrays) {
        if (arrays.length === 0) return [[]];
        const [first, ...rest] = arrays;
        const restCombos = cartesian(rest);
        const result = [];
        first.forEach(val => {
            restCombos.forEach(combo => {
                result.push([val, ...combo]);
            });
        });
        return result;
    }

    // ════════════════════════════════════════════════════════════
    // IMAGES MANAGEMENT
    // ════════════════════════════════════════════════════════════
    function openMediaStudio() {
        if (!state.currentProduct?.id) {
            showNotification(t('messages.save_first', 'Please save the product first before adding images'), 'warning');
            return;
        }
        if (el.mediaModal && el.mediaFrame) {
            el.mediaModal.style.display = 'block';
            // Pass product id as owner_id and image_type_id=2 for product images
            el.mediaFrame.src = `/admin/fragments/media_studio.php?embedded=1&tenant_id=${state.tenantId}&lang=${state.language}&owner_id=${state.currentProduct.id}&image_type_id=2`;
        }
    }

    function closeMediaStudio() {
        if (el.mediaModal) {
            el.mediaModal.style.display = 'none';
        }
    }

    function renderProductImages() {
        if (!el.prodImagesPreview) return;

        el.prodImagesPreview.innerHTML = state.selectedImages.map((img, idx) => `
            <div class="image-item" data-index="${idx}" style="position:relative; display:inline-block; margin:8px;">
                <img src="${esc(img.url || img.thumb_url)}" style="width:100px; height:100px; object-fit:cover; border-radius:4px;">
                <button type="button" class="btn btn-sm btn-danger" 
                        style="position:absolute; top:4px; right:4px; padding:2px 6px;"
                        onclick="Products.removeImage(${idx})">
                    <i class="fas fa-times"></i>
                </button>
                ${idx === 0 ? '<span style="position:absolute;bottom:4px;left:4px;background:rgba(0,0,0,0.7);color:white;padding:2px 6px;border-radius:4px;font-size:10px;">Main</span>' : ''}
            </div>
        `).join('');
    }

    function removeImage(index) {
        state.selectedImages.splice(index, 1);
        renderProductImages();
    }

    // ════════════════════════════════════════════════════════════
    // CATEGORIES DROPDOWNS (Main / Sub)
    // ════════════════════════════════════════════════════════════
    function populateMainCategoryDropdown() {
        if (!el.prodMainCategory) return;

        // القوائم الرئيسية = التي ليس لها أب (parent_id = null)
        const mainCategories = state.categories.filter(cat => !cat.parent_id);
        
        el.prodMainCategory.innerHTML = '<option value="">' + t('form.fields.main_category.select', 'Select main category') + '</option>';
        
        mainCategories.forEach(cat => {
            const opt = document.createElement('option');
            opt.value = cat.id;
            opt.textContent = cat.name;
            el.prodMainCategory.appendChild(opt);
        });

        // Clear sub category
        if (el.prodSubCategory) {
            el.prodSubCategory.innerHTML = '<option value="">' + t('form.fields.sub_category.select', 'Select sub category') + '</option>';
        }
    }

    function onMainCategoryChange() {
        const mainCatId = el.prodMainCategory ? el.prodMainCategory.value : '';
        
        if (!el.prodSubCategory) return;

        el.prodSubCategory.innerHTML = '<option value="">' + t('form.fields.sub_category.select', 'Select sub category') + '</option>';

        if (!mainCatId) return;

        // القوائم الفرعية = التي parent_id = القائمة الرئيسية المختارة (+ أحفادها)
        const subCategories = state.categories.filter(cat => String(cat.parent_id) === String(mainCatId));
        
        subCategories.forEach(cat => {
            const opt = document.createElement('option');
            opt.value = cat.id;
            opt.textContent = cat.name;
            el.prodSubCategory.appendChild(opt);

            // إضافة الأحفاد (المستوى الثاني)
            const grandChildren = state.categories.filter(gc => String(gc.parent_id) === String(cat.id));
            grandChildren.forEach(gc => {
                const gcOpt = document.createElement('option');
                gcOpt.value = gc.id;
                gcOpt.textContent = '  ↳ ' + gc.name;
                el.prodSubCategory.appendChild(gcOpt);
            });
        });

        // Also sync with selectedCategories - add main category
        if (!state.selectedCategories.includes(parseInt(mainCatId))) {
            state.selectedCategories.push(parseInt(mainCatId));
        }
        renderCategoriesTree();
    }

    function onSubCategoryChange() {
        const subCatId = el.prodSubCategory ? el.prodSubCategory.value : '';
        if (subCatId) {
            const numId = parseInt(subCatId);
            if (!state.selectedCategories.includes(numId)) {
                state.selectedCategories.push(numId);
            }
            renderCategoriesTree();
        }
    }

    // ════════════════════════════════════════════════════════════
    // CATEGORIES TREE
    // ════════════════════════════════════════════════════════════
    function renderCategoriesTree() {
        if (!el.prodCategoriesTree) return;

        const buildTree = (categories, parentId = null) => {
            return categories
                .filter(cat => cat.parent_id == parentId)
                .map(cat => {
                    const isSelected = state.selectedCategories.includes(cat.id);
                    const children = buildTree(categories, cat.id);
                    
                    return `
                        <div class="category-node" style="margin-left:${parentId ? '20px' : '0'};">
                            <label style="display:flex;align-items:center;gap:8px;padding:4px 0;">
                                <input type="checkbox" value="${cat.id}" 
                                       ${isSelected ? 'checked' : ''}
                                       onchange="Products.toggleCategory(${cat.id}, this.checked)">
                                <span>${esc(cat.name)}</span>
                            </label>
                            ${children ? `<div class="category-children">${children}</div>` : ''}
                        </div>
                    `;
                }).join('');
        };

        el.prodCategoriesTree.innerHTML = buildTree(state.categories);
    }

    function toggleCategory(categoryId, checked) {
        if (checked) {
            if (!state.selectedCategories.includes(categoryId)) {
                state.selectedCategories.push(categoryId);
            }
        } else {
            state.selectedCategories = state.selectedCategories.filter(id => id != categoryId);
        }
        syncCategoryDropdownsFromSelection();
    }

    function syncCategoryDropdownsFromSelection() {
        // Sync dropdowns with selectedCategories
        if (!el.prodMainCategory || !el.prodSubCategory) return;

        // Find if any selected category is a main category (no parent)
        let mainCatId = '';
        let subCatId = '';
        for (const catId of state.selectedCategories) {
            const cat = state.categories.find(c => c.id == catId);
            if (cat && !cat.parent_id) {
                mainCatId = String(catId);
            } else if (cat && cat.parent_id) {
                subCatId = String(catId);
                // Also select the parent as main if not already
                if (!mainCatId) mainCatId = String(cat.parent_id);
            }
        }

        el.prodMainCategory.value = mainCatId;
        if (mainCatId) {
            onMainCategoryChange();
            if (subCatId) {
                el.prodSubCategory.value = subCatId;
            }
        }
    }

    // ════════════════════════════════════════════════════════════
    // TRANSLATIONS
    // ════════════════════════════════════════════════════════════
    function addTranslation() {
        const langCode = el.prodLangSelect?.value;
        if (!langCode) return;

        const langName = el.prodLangSelect.options[el.prodLangSelect.selectedIndex].textContent;
        
        // Check if already added
        const existingPanel = document.querySelector(`[data-lang="${langCode}"]`);
        if (existingPanel) {
            showNotification(t('messages.translation_exists', 'Translation already added'), 'warning');
            return;
        }

        const panel = createTranslationPanel(langCode, langName, {});
        if (el.prodTranslations) {
            el.prodTranslations.appendChild(panel);
        }
        
        el.prodLangSelect.value = '';
    }

    function createTranslationPanel(langCode, langName, data) {
        const panel = document.createElement('div');
        panel.className = 'translation-panel';
        panel.dataset.lang = langCode;
        
        panel.innerHTML = `
            <div class="translation-panel-header">
                <h5><i class="fas fa-language"></i> ${esc(langName)} (${esc(langCode)})</h5>
                <button type="button" class="btn btn-sm btn-danger" onclick="this.closest('.translation-panel').remove()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="translation-panel-body">
                <div class="form-group">
                    <label>${t('form.fields.name.label', 'Name')}</label>
                    <input type="text" class="form-control trans-name" value="${esc(data.name || '')}" data-lang="${langCode}">
                </div>
                <div class="form-group">
                    <label>${t('form.fields.short_description.label', 'Short Description')}</label>
                    <textarea class="form-control trans-short-desc" rows="2" data-lang="${langCode}">${esc(data.short_description || '')}</textarea>
                </div>
                <div class="form-group">
                    <label>${t('form.fields.description.label', 'Description')}</label>
                    <textarea class="form-control trans-desc" rows="4" data-lang="${langCode}">${esc(data.description || '')}</textarea>
                </div>
                <div class="form-group">
                    <label>${t('form.fields.specifications.label', 'Specifications')}</label>
                    <textarea class="form-control trans-specifications" rows="3" data-lang="${langCode}">${esc(data.specifications || '')}</textarea>
                </div>
                <div class="form-group">
                    <label>${t('form.fields.meta_title.label', 'Meta Title')}</label>
                    <input type="text" class="form-control trans-meta-title" value="${esc(data.meta_title || '')}" data-lang="${langCode}">
                </div>
                <div class="form-group">
                    <label>${t('form.fields.meta_description.label', 'Meta Description')}</label>
                    <textarea class="form-control trans-meta-desc" rows="2" data-lang="${langCode}">${esc(data.meta_description || '')}</textarea>
                </div>
                <div class="form-group">
                    <label>${t('form.fields.meta_keywords.label', 'Meta Keywords')}</label>
                    <input type="text" class="form-control trans-meta-keywords" value="${esc(data.meta_keywords || '')}" data-lang="${langCode}">
                </div>
            </div>
        `;
        
        return panel;
    }

    function collectTranslations() {
        const translations = {};
        
        document.querySelectorAll('.translation-panel').forEach(panel => {
            const lang = panel.dataset.lang;
            const name = panel.querySelector('.trans-name')?.value || '';
            const shortDesc = panel.querySelector('.trans-short-desc')?.value || '';
            const desc = panel.querySelector('.trans-desc')?.value || '';
            const specifications = panel.querySelector('.trans-specifications')?.value || '';
            const metaTitle = panel.querySelector('.trans-meta-title')?.value || '';
            const metaDesc = panel.querySelector('.trans-meta-desc')?.value || '';
            const metaKeywords = panel.querySelector('.trans-meta-keywords')?.value || '';
            
            if (name || shortDesc || desc || specifications || metaTitle || metaDesc || metaKeywords) {
                translations[lang] = {
                    name: name,
                    short_description: shortDesc,
                    description: desc,
                    specifications: specifications,
                    meta_title: metaTitle,
                    meta_description: metaDesc,
                    meta_keywords: metaKeywords
                };
            }
        });
        
        return translations;
    }

    async function loadProductTranslations(productId) {
        try {
            console.log('[Products] Loading translations for product:', productId);
            const result = await apiCall(`/api/product_translations?product_id=${productId}&format=json`);
            if (result.success) {
                const items = Array.isArray(result.data) ? result.data : (result.data?.items || []);
                if (el.prodTranslations) el.prodTranslations.innerHTML = '';
                items.forEach(trans => {
                    const langName = state.languages.find(l => l.code === trans.language_code)?.name || trans.language_code;
                    const panel = createTranslationPanel(trans.language_code, langName, {
                        name: trans.name || '',
                        short_description: trans.short_description || '',
                        description: trans.description || '',
                        specifications: trans.specifications || '',
                        meta_title: trans.meta_title || '',
                        meta_description: trans.meta_description || '',
                        meta_keywords: trans.meta_keywords || ''
                    });
                    if (el.prodTranslations) el.prodTranslations.appendChild(panel);
                });
            }
        } catch (err) {
            console.warn('[Products] Failed to load translations:', err);
        }
    }

    async function loadProductImages(productId) {
        try {
            console.log('[Products] Loading images for product:', productId);
            // image_type_id = 2 for products
            const result = await apiCall(`/api/images/by_owner?owner_id=${productId}&image_type_id=2`);
            if (result.success) {
                const images = Array.isArray(result.data) ? result.data : [];
                state.selectedImages = images;
                renderProductImages();
            }
        } catch (err) {
            console.warn('[Products] Failed to load images:', err);
        }
    }

    async function loadProductPricing(productId) {
        try {
            console.log('[Products] Loading pricing for product:', productId);
            const result = await apiCall(`/api/product_pricing?product_id=${productId}&format=json`);
            if (result.success) {
                const items = result.data?.items || (Array.isArray(result.data) ? result.data : []);
                // استخدام أول سعر نشط
                const pricing = items.find(p => p.is_active == 1) || items[0];
                if (pricing) {
                    if (el.prodPrice) el.prodPrice.value = pricing.price || '';
                    if (el.prodComparePrice) el.prodComparePrice.value = pricing.compare_at_price || '';
                    if (el.prodCostPrice) el.prodCostPrice.value = pricing.cost_price || '';
                    if (el.prodCurrency) el.prodCurrency.value = pricing.currency_code || 'SAR';
                    if (el.prodTaxRate) el.prodTaxRate.value = pricing.tax_rate || '';
                }
            }
        } catch (err) {
            console.warn('[Products] Failed to load pricing:', err);
        }
    }

    async function loadPhysicalAttributes(productId) {
        try {
            console.log('[Products] Loading physical attributes for product:', productId);
            const result = await apiCall(`/api/product_physical_attributes?product_id=${productId}&format=json`);
            if (result.success) {
                const items = result.data?.items || (Array.isArray(result.data) ? result.data : []);
                const phys = items.find(p => p.product_id == productId) || items[0];
                if (phys) {
                    if (el.prodWeight) el.prodWeight.value = phys.weight || '';
                    if (el.prodLength) el.prodLength.value = phys.length || '';
                    if (el.prodWidth) el.prodWidth.value = phys.width || '';
                    if (el.prodHeight) el.prodHeight.value = phys.height || '';
                    if (el.prodWeightUnit) el.prodWeightUnit.value = phys.weight_unit || 'kg';
                    if (el.prodDimensionUnit) el.prodDimensionUnit.value = phys.dimension_unit || 'cm';
                }
            }
        } catch (err) {
            console.warn('[Products] Failed to load physical attributes:', err);
        }
    }

    async function loadProductCategories(productId) {
        try {
            console.log('[Products] Loading categories for product:', productId);
            const result = await apiCall(`/api/product_categories?product_id=${productId}&format=json`);
            if (result.success) {
                const items = result.data?.items || (Array.isArray(result.data) ? result.data : []);
                state.selectedCategories = items.map(item => parseInt(item.category_id));
                renderCategoriesTree();
                syncCategoryDropdownsFromSelection();
            }
        } catch (err) {
            console.warn('[Products] Failed to load categories:', err);
        }
    }

    async function loadProductAttributes(productId) {
        try {
            console.log('[Products] Loading attributes for product:', productId);
            const result = await apiCall(`/api/product_attribute_assignments/by_product?product_id=${productId}`);
            if (result.success) {
                const items = Array.isArray(result.data) ? result.data : (result.data?.items || []);
                
                // بناء قائمة السمات مع تحميل القيم المتاحة لكل سمة
                const attrs = [];
                for (const item of items) {
                    // تحميل القيم المتاحة لهذه السمة
                    let availableValues = [];
                    try {
                        const valuesResult = await apiCall(`${API.attributeValues}?attribute_id=${encodeURIComponent(item.attribute_id)}&format=json`);
                        if (valuesResult.success) {
                            availableValues = Array.isArray(valuesResult.data) ? valuesResult.data : (valuesResult.data?.items || []);
                        }
                    } catch (err) {
                        console.warn('[Products] Failed to load values for attribute:', item.attribute_id, err);
                    }

                    // البحث عن اسم السمة من state.attributes
                    const attrInfo = state.attributes.find(a => String(a.id) === String(item.attribute_id));
                    
                    attrs.push({
                        attribute_id: item.attribute_id,
                        attribute_name: attrInfo?.name || item.attribute_name || item.attribute_slug || `Attribute #${item.attribute_id}`,
                        attribute_type: item.attribute_type_id || attrInfo?.attribute_type_id || '',
                        value: item.custom_value || item.value || '',
                        custom_value: item.custom_value || '',
                        attribute_value_id: item.attribute_value_id || null,
                        available_values: availableValues
                    });
                }
                
                state.productAttributes = attrs;
                renderAttributes();
            }
        } catch (err) {
            console.warn('[Products] Failed to load attributes:', err);
        }
    }

    async function loadProductVariants(productId) {
        try {
            console.log('[Products] Loading variants for product:', productId);
            const result = await apiCall(`/api/product_variants?product_id=${productId}&tenant_id=${state.tenantId}&language_code=${state.language}&format=json`);
            if (result.success) {
                const items = result.data?.items || (Array.isArray(result.data) ? result.data : []);
                state.productVariants = items.map(v => ({
                    id: v.id,
                    sku: v.sku || '',
                    barcode: v.barcode || '',
                    name: v.name || '',
                    stock_quantity: v.stock_quantity || 0,
                    price: v.price || '',
                    is_active: v.is_active || 1,
                    is_default: v.is_default || 0
                }));
                renderVariants();
            }
        } catch (err) {
            console.warn('[Products] Failed to load variants:', err);
        }
    }

    // ════════════════════════════════════════════════════════════
    // DELETE & DUPLICATE
    // ══════════════════���═════════════════════════════════════════
    async function deleteProduct(id) {
        if (!confirm(t('messages.confirm_delete', 'Are you sure you want to delete this product?'))) {
            return;
        }

        try {
            const result = await apiCall(API.products, {
                method: 'DELETE',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: id })
            });
            
            if (result.success) {
                showNotification(t('messages.deleted', 'Product deleted successfully'), 'success');
                hideForm();
                loadProducts(state.page);
            } else {
                throw new Error(result.error || 'Delete failed');
            }
        } catch (err) {
            console.error('[Products] Delete failed:', err);
            showNotification(err.message || t('messages.error.delete_failed', 'Failed to delete product'), 'error');
        }
    }

    async function duplicateProduct(id) {
        try {
            const result = await apiCall(`${API.products}?id=${id}&format=json&lang=${state.language}`);
            
            if (result.success && result.data) {
                const productData = result.data;
                const product = { ...productData };
                delete product.id;
                const uid = Math.random().toString(36).substring(2, 8);
                product.name = `${product.name || ''} (Copy)`;
                product.sku = `${product.sku || ''}-copy-${uid}`;
                product.slug = `${product.slug || ''}-copy-${uid}`;
                // مسح الباركود لتجنب خطأ الإدخال المكرر
                product.barcode = null;
                
                showForm(product);
            } else {
                throw new Error('Failed to load product for duplication');
            }
        } catch (err) {
            console.error('[Products] Duplicate failed:', err);
            showNotification(err.message || t('messages.error.duplicate_failed', 'Failed to duplicate product'), 'error');
        }
    }

    // ════════════════════════════════════════════════════════════
    // FILTERS & PAGINATION
    // ════════════════════════════════════════════════════════════
    function applyFilters() {
        state.filters = {};
        
        if (el.searchInput?.value) state.filters.search = el.searchInput.value;
        if (el.tenantFilter?.value) state.filters.tenant_id = el.tenantFilter.value;
        if (el.typeFilter?.value) state.filters.product_type_id = el.typeFilter.value;
        if (el.brandFilter?.value) state.filters.brand_id = el.brandFilter.value;
        if (el.statusFilter?.value) state.filters.is_active = el.statusFilter.value;

        loadProducts(1);
    }

    function resetFilters() {
        state.filters = {};
        
        if (el.searchInput) el.searchInput.value = '';
        if (el.tenantFilter) el.tenantFilter.value = state.tenantId;
        if (el.typeFilter) el.typeFilter.value = '';
        if (el.brandFilter) el.brandFilter.value = '';
        if (el.statusFilter) el.statusFilter.value = '';

        loadProducts(1);
    }

    function updatePagination(meta) {
        if (!el.pagination || !el.paginationInfo) return;

        const { page = 1, per_page = 25, total = 0 } = meta;
        const totalPages = Math.ceil(total / per_page);
        const start = total > 0 ? (page - 1) * per_page + 1 : 0;
        const end = Math.min(page * per_page, total);

        el.paginationInfo.textContent = `${start}-${end} of ${total}`;

        if (totalPages <= 1) {
            el.pagination.innerHTML = '';
            return;
        }

        let html = '';

        // Previous button
        html += `<button class="pagination-btn" ${page <= 1 ? 'disabled' : ''} onclick="Products.load(${page - 1})">
            <i class="fas fa-chevron-left"></i>
        </button>`;

        // Page numbers
        for (let i = 1; i <= totalPages; i++) {
            if (i === 1 || i === totalPages || (i >= page - 2 && i <= page + 2)) {
                html += `<button class="pagination-btn ${i === page ? 'active' : ''}" onclick="Products.load(${i})">${i}</button>`;
            } else if (i === page - 3 || i === page + 3) {
                html += `<span class="pagination-ellipsis">...</span>`;
            }
        }

        // Next button
        html += `<button class="pagination-btn" ${page >= totalPages ? 'disabled' : ''} onclick="Products.load(${page + 1})">
            <i class="fas fa-chevron-right"></i>
        </button>`;

        el.pagination.innerHTML = html;
    }

    function updateResultsCount(total) {
        if (el.resultsCount && el.resultsCountText) {
            el.resultsCountText.textContent = `${total} ${t('products.found', 'products found')}`;
            el.resultsCount.style.display = total > 0 ? 'block' : 'none';
        }
    }

    // ════════════════════════════════════════════════════════════
    // UI STATE HELPERS
    // ════════════════════════════════════════════════════════════
    function showLoading() {
        if (el.loading) {
            el.loading.innerHTML = `<div class="spinner"></div><p>${t('products.loading', 'Loading...')}</p>`;
            el.loading.style.display = 'flex';
        }
        if (el.container) el.container.style.display = 'none';
        if (el.empty) el.empty.style.display = 'none';
        if (el.error) el.error.style.display = 'none';
    }

    function showTable() {
        if (el.loading) el.loading.style.display = 'none';
        if (el.container) el.container.style.display = 'block';
        if (el.empty) el.empty.style.display = 'none';
        if (el.error) el.error.style.display = 'none';
    }

    function showEmpty() {
        if (el.loading) el.loading.style.display = 'none';
        if (el.container) el.container.style.display = 'none';
        if (el.error) el.error.style.display = 'none';
        if (el.empty) {
            el.empty.innerHTML = `
                <div class="empty-icon">📦</div>
                <h3>${t('table.empty.title', 'No Products Found')}</h3>
                <p>${t('table.empty.message', 'Start by adding your first product')}</p>
                ${state.permissions.canCreate ? `<button class="btn btn-primary" onclick="Products.add()">
                    <i class="fas fa-plus"></i> ${t('table.empty.add_first', 'Add First Product')}
                </button>` : ''}
            `;
            el.empty.style.display = 'flex';
        }
        if (el.tbody) el.tbody.innerHTML = '';
    }

    function showError(message) {
        if (el.loading) el.loading.style.display = 'none';
        if (el.container) el.container.style.display = 'none';
        if (el.empty) el.empty.style.display = 'none';
        if (el.error) {
            if (el.errorMessage) el.errorMessage.textContent = message;
            el.error.style.display = 'flex';
        }
    }

    function showNotification(message, type = 'info') {
        if (AF.notify) {
            AF.notify(message, type);
        } else {
            alert(message);
        }
    }

    // ════════════════════════════════════════════════════════════
    // UTILITIES
    // ════════════════════════════════════════════════════════════
    function esc(text) {
        if (text === null || text === undefined) return '';
        const div = document.createElement('div');
        div.textContent = String(text);
        return div.innerHTML;
    }

    // ════════════════════════════════════════════════════════════
    // INITIALIZATION
    // ════════════════════════════════════════════════════════════
    async function init() {
        console.log('[Products] Initializing...');

        // Always use document.getElementById for reliability in fragment mode
        const $id = (id) => document.getElementById(id);

        // Cache DOM elements
        el = {
            // Containers
            container: $id('tableContainer'),
            loading: $id('tableLoading'),
            empty: $id('emptyState'),
            error: $id('errorState'),
            errorMessage: $id('errorMessage'),
            
            // Form
            formContainer: $id('productFormContainer'),
            form: $id('productForm'),
            formTitle: $id('formTitle'),
            formId: $id('formId'),
            
            // Form fields - General
            prodName: $id('prodName'),
            prodSku: $id('prodSku'),
            prodSlug: $id('prodSlug'),
            prodBarcode: $id('prodBarcode'),
            prodType: $id('prodType'),
            prodBrand: $id('prodBrand'),
            prodMainCategory: $id('prodMainCategory'),
            prodSubCategory: $id('prodSubCategory'),
            prodIsActive: $id('prodIsActive'),
            prodIsFeatured: $id('prodIsFeatured'),
            prodIsBestseller: $id('prodIsBestseller'),
            prodIsNew: $id('prodIsNew'),
            prodTenantId: $id('prodTenantId'),
            
            // Form fields - Pricing
            prodPrice: $id('prodPrice'),
            prodComparePrice: $id('prodComparePrice'),
            prodCostPrice: $id('prodCostPrice'),
            prodCurrency: $id('prodCurrency'),
            prodTaxRate: $id('prodTaxRate'),
            
            // Form fields - Inventory
            prodStockQty: $id('prodStockQty'),
            prodLowStock: $id('prodLowStock'),
            prodStockStatus: $id('prodStockStatus'),
            prodManageStock: $id('prodManageStock'),
            prodAllowBackorder: $id('prodAllowBackorder'),
            
            // Form fields - Physical
            prodWeight: $id('prodWeight'),
            prodLength: $id('prodLength'),
            prodWidth: $id('prodWidth'),
            prodHeight: $id('prodHeight'),
            prodWeightUnit: $id('prodWeightUnit'),
            prodDimensionUnit: $id('prodDimensionUnit'),
            
            // Attributes
            attrSelect: $id('attrSelect'),
            btnAddAttribute: $id('btnAddAttribute'),
            prodAttributesList: $id('prodAttributesList'),
            
            // Variants
            btnGenerateVariants: $id('btnGenerateVariants'),
            btnAddVariant: $id('btnAddVariant'),
            prodVariantsList: $id('prodVariantsList'),
            
            // Images
            prodSelectImageBtn: $id('prodSelectImageBtn'),
            prodImagesPreview: $id('prodImagesPreview'),
            mediaModal: $id('prodMediaStudioModal'),
            mediaFrame: $id('prodMediaStudioFrame'),
            mediaClose: $id('prodMediaStudioClose'),
            
            // Categories
            prodCategoriesTree: $id('prodCategoriesTree'),
            
            // Translations
            prodTranslations: $id('prodTranslations'),
            prodLangSelect: $id('prodLangSelect'),
            prodAddLangBtn: $id('prodAddLangBtn'),
            
            // Table
            tbody: $id('tableBody'),
            
            // Filters
            searchInput: $id('searchInput'),
            tenantFilter: $id('tenantFilter'),
            typeFilter: $id('typeFilter'),
            brandFilter: $id('brandFilter'),
            statusFilter: $id('statusFilter'),
            
            // Buttons
            btnSubmit: $id('btnSubmitForm'),
            btnAdd: $id('btnAddProduct'),
            btnClose: $id('btnCloseForm'),
            btnCancel: $id('btnCancelForm'),
            btnApply: $id('btnApplyFilters'),
            btnReset: $id('btnResetFilters'),
            btnRetry: $id('btnRetry'),
            btnDeleteProduct: $id('btnDeleteProduct'),
            
            // Pagination
            pagination: $id('pagination'),
            paginationInfo: $id('paginationInfo'),
            resultsCount: $id('resultsCount'),
            resultsCountText: $id('resultsCountText')
        };

        // Log DOM element detection for debugging
        console.log('[Products] DOM elements found:', {
            form: !!el.form,
            formContainer: !!el.formContainer,
            btnAdd: !!el.btnAdd,
            btnSubmit: !!el.btnSubmit,
            tbody: !!el.tbody,
            prodMainCategory: !!el.prodMainCategory,
            prodSubCategory: !!el.prodSubCategory,
            prodType: !!el.prodType,
            prodBrand: !!el.prodBrand,
            prodCurrency: !!el.prodCurrency
        });

        // Load translations
        await loadTranslations(state.language);

        // Setup event listeners (use onXxx to prevent duplicate handlers on re-init)
        if (el.form) {
            el.form.onsubmit = saveProduct;
            console.log('[Products] ✓ Form submit handler attached');
        } else {
            console.error('[Products] ✗ Form element not found!');
        }
        if (el.btnAdd) {
            el.btnAdd.onclick = function() { showForm(); };
            console.log('[Products] ✓ Add button handler attached');
        } else {
            console.error('[Products] ✗ Add button not found!');
        }
        if (el.btnClose) el.btnClose.onclick = hideForm;
        if (el.btnCancel) el.btnCancel.onclick = hideForm;
        if (el.btnApply) el.btnApply.onclick = applyFilters;
        if (el.btnReset) el.btnReset.onclick = resetFilters;
        if (el.btnRetry) el.btnRetry.onclick = function() { loadProducts(state.page); };
        if (el.btnDeleteProduct) el.btnDeleteProduct.onclick = function() {
            if (state.currentProduct) deleteProduct(state.currentProduct.id);
        };
        
        // Attributes
        if (el.btnAddAttribute) el.btnAddAttribute.onclick = addAttribute;
        
        // Variants
        if (el.btnAddVariant) el.btnAddVariant.onclick = addVariant;
        if (el.btnGenerateVariants) el.btnGenerateVariants.onclick = generateVariantsFromAttributes;
        
        // Images
        if (el.prodSelectImageBtn) el.prodSelectImageBtn.onclick = openMediaStudio;
        if (el.mediaClose) el.mediaClose.onclick = closeMediaStudio;
        
        // Translations
        if (el.prodAddLangBtn) el.prodAddLangBtn.onclick = addTranslation;

        // Main category → Sub category cascade
        if (el.prodMainCategory) el.prodMainCategory.onchange = onMainCategoryChange;
        if (el.prodSubCategory) el.prodSubCategory.onchange = onSubCategoryChange;
        
        // Media Studio message listener (only add once to prevent accumulation)
        if (!_messageListenerAdded) {
            _messageListenerAdded = true;
            window.addEventListener('message', function(e) {
                if (e.data && e.data.type === 'media-selected') {
                    state.selectedImages = e.data.images || [];
                    renderProductImages();
                    closeMediaStudio();
                }
            });
        }

        // Initialize tabs
        initTabs();

        // Load dropdown data (categories, brands, types, currencies, etc.)
        await loadDropdownData();

        // Load initial data
        await loadProducts(1);

        console.log('[Products] ✓ Initialized successfully');
    }

    // ════════════════════════════════════════════════════════════
    // PUBLIC API
    // ════════════════════════════════════════════════════════════
    window.Products = {
        init,
        load: loadProducts,
        add: () => showForm(),
        edit: async (id) => {
            try {
                const result = await apiCall(`${API.products}?id=${id}&format=json&lang=${state.language}&tenant_id=${state.tenantId}`);
                if (result.success && result.data) {
                    showForm(result.data);
                } else {
                    throw new Error('Product not found');
                }
            } catch (err) {
                console.error('[Products] Edit failed:', err);
                showNotification(err.message || t('messages.error.load_failed', 'Failed to load product'), 'error');
            }
        },
        remove: deleteProduct,
        duplicate: duplicateProduct,
        updateAttributeValue,
        updateAttributeValueId,
        updateCustomValue,
        removeAttribute,
        updateVariantField,
        removeVariant,
        generateVariantsFromAttributes,
        removeImage,
        toggleCategory,
        setLanguage: async (lang) => {
            state.language = lang;
            await loadTranslations(lang);
            setDirectionForLang(lang);
            loadProducts(state.page);
        }
    };

    // Fragment support
    window.page = { run: init };

    // Auto-init: matches categories.js pattern (which works)
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            if (window.AdminFramework && !window.page.__fragment_init) {
                init().catch(function(e) { console.error('[Products] Auto-init failed:', e); });
            }
        });
    } else {
        if (window.AdminFramework && !window.page.__fragment_init) {
            init().catch(function(e) { console.error('[Products] Auto-init failed:', e); });
        }
    }
    window.page.__fragment_init = false;

    console.log('[Products] Module loaded');

})();