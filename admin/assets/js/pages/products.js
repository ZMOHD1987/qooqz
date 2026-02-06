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

    // ════════════════════════════════════════════════════════════
    // TRANSLATIONS
    // ════════════════════════════════════════════════════════════
    async function loadTranslations(lang) {
        try {
            const url = `/languages/Products/${encodeURIComponent(lang || state.language)}.json`;
            console.log('[Products] Loading translations:', url);
            const res = await fetch(url, { credentials: 'same-origin' });
            if (!res.ok) throw new Error(`Failed to load translations: ${res.status}`);
            translations = await res.json();
            console.log('[Products] Translations loaded');
            applyTranslations();
        } catch (err) {
            console.warn('[Products] Translation load failed:', err);
            translations = {};
        }
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

            // Load categories
            try {
                const categoriesResult = await apiCall(`${API.categories}?format=json&tenant_id=${state.tenantId}&lang=${state.language}`);
                if (categoriesResult.success) {
                    // categories returns { data: { items: [...], meta: {} } }
                    const categoriesData = categoriesResult.data?.items || categoriesResult.data;
                    state.categories = Array.isArray(categoriesData) ? categoriesData : [];
                    renderCategoriesTree();
                }
            } catch (err) {
                console.warn('[Products] Failed to load categories:', err);
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
        if (!el.formContainer || !el.form) return;

        state.currentProduct = product;
        state.selectedImages = [];
        state.selectedCategories = [];
        state.productAttributes = [];
        state.productVariants = [];

        el.form.reset();
        
        if (product) {
            el.formTitle.textContent = t('form.edit_title', 'Edit Product');
            el.formId.value = product.id || '';
            el.prodName.value = product.name || '';
            el.prodSku.value = product.sku || '';
            el.prodSlug.value = product.slug || '';
            el.prodBarcode.value = product.barcode || '';
            el.prodType.value = product.product_type_id || '';
            el.prodBrand.value = product.brand_id || '';
            el.prodIsActive.value = product.is_active || '1';
            el.prodIsFeatured.value = product.is_featured || '0';
            el.prodIsBestseller.value = product.is_bestseller || '0';
            el.prodIsNew.value = product.is_new || '0';
            
            // Pricing
            el.prodPrice.value = product.price || '';
            el.prodComparePrice.value = product.compare_at_price || '';
            el.prodCostPrice.value = product.cost_price || '';
            el.prodCurrency.value = product.currency_code || 'SAR';
            el.prodTaxRate.value = product.tax_rate || '';
            
            // Inventory
            el.prodStockQty.value = product.stock_quantity || '0';
            el.prodLowStock.value = product.low_stock_threshold || '5';
            el.prodStockStatus.value = product.stock_status || 'in_stock';
            el.prodManageStock.value = product.manage_stock || '1';
            el.prodAllowBackorder.value = product.allow_backorder || '0';
            
            // Physical attributes
            el.prodWeight.value = product.weight || '';
            el.prodLength.value = product.length || '';
            el.prodWidth.value = product.width || '';
            el.prodHeight.value = product.height || '';

            if (el.btnDeleteProduct) el.btnDeleteProduct.style.display = state.permissions.canDelete ? 'inline-block' : 'none';

            // Load related data
            loadProductImages(product.id);
            loadProductCategories(product.id);
            loadProductAttributes(product.id);
            loadProductVariants(product.id);
            loadProductTranslations(product.id);
        } else {
            el.formTitle.textContent = t('form.add_title', 'Add Product');
            el.formId.value = '';
            if (el.btnDeleteProduct) el.btnDeleteProduct.style.display = 'none';
            el.prodTenantId.value = state.tenantId;
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
                sku: formData.get('sku'),
                slug: formData.get('slug') || generateSlug(formData.get('name')),
                barcode: formData.get('barcode'),
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
                if (state.selectedCategories.length > 0) {
                    await saveProductCategories(savedProductId);
                }

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

            await apiCall('/api/product_pricing', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(pricingData)
            });
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
                weight_unit: 'kg',
                dimension_unit: 'cm'
            };

            await apiCall('/api/product_physical_attributes', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(physicalData)
            });
        } catch (err) {
            console.warn('[Products] Failed to save physical attributes:', err);
        }
    }

    async function saveProductCategories(productId) {
        try {
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
            for (const [langCode, trans] of Object.entries(translations)) {
                await apiCall('/api/product_translations', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        product_id: parseInt(productId),
                        language_code: langCode,
                        name: trans.name || '',
                        short_description: trans.short_description || '',
                        description: trans.description || ''
                    })
                });
            }
        } catch (err) {
            console.warn('[Products] Failed to save translations:', err);
        }
    }

    function validateForm() {
        let isValid = true;

        // Validate required fields
        const requiredFields = [el.prodName, el.prodSku];
        
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
    function addAttribute() {
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

        const attr = {
            attribute_id: attrId,
            attribute_name: attrName,
            attribute_type: attrType,
            value: ''
        };

        state.productAttributes.push(attr);
        renderAttributes();
        el.attrSelect.value = '';
    }

    function renderAttributes() {
        if (!el.prodAttributesList) return;

        el.prodAttributesList.innerHTML = state.productAttributes.map((attr, idx) => `
            <div class="attribute-item" data-index="${idx}">
                <label>${esc(attr.attribute_name)}</label>
                <div style="display:flex;gap:8px;align-items:center;">
                    <input type="text" class="form-control" value="${esc(attr.value || '')}" 
                           onchange="Products.updateAttributeValue(${idx}, this.value)">
                    <button type="button" class="btn btn-sm btn-danger" onclick="Products.removeAttribute(${idx})">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
        `).join('');
    }

    function updateAttributeValue(index, value) {
        if (state.productAttributes[index]) {
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
                        <label>SKU</label>
                        <input type="text" class="form-control" value="${esc(variant.sku || '')}"
                               onchange="Products.updateVariantField(${idx}, 'sku', this.value)">
                    </div>
                    <div class="form-group" style="flex:1;">
                        <label>Name</label>
                        <input type="text" class="form-control" value="${esc(variant.name || '')}"
                               onchange="Products.updateVariantField(${idx}, 'name', this.value)">
                    </div>
                    <div class="form-group" style="flex:1;">
                        <label>Price</label>
                        <input type="number" step="0.01" class="form-control" value="${esc(variant.price || '')}"
                               onchange="Products.updateVariantField(${idx}, 'price', this.value)">
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
                            ${children.length > 0 ? `<div class="category-children">${children.join('')}</div>` : ''}
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
                    <label>Name</label>
                    <input type="text" class="form-control trans-name" value="${esc(data.name || '')}" data-lang="${langCode}">
                </div>
                <div class="form-group">
                    <label>Short Description</label>
                    <textarea class="form-control trans-short-desc" rows="2" data-lang="${langCode}">${esc(data.short_description || '')}</textarea>
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea class="form-control trans-desc" rows="4" data-lang="${langCode}">${esc(data.description || '')}</textarea>
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
            
            if (name || shortDesc || desc) {
                translations[lang] = {
                    name: name,
                    short_description: shortDesc,
                    description: desc
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
                        description: trans.description || ''
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

    async function loadProductCategories(productId) {
        try {
            console.log('[Products] Loading categories for product:', productId);
            const result = await apiCall(`/api/product_categories?product_id=${productId}&format=json`);
            if (result.success) {
                const items = result.data?.items || (Array.isArray(result.data) ? result.data : []);
                state.selectedCategories = items.map(item => parseInt(item.category_id));
                renderCategoriesTree();
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
                state.productAttributes = items.map(item => ({
                    attribute_id: item.attribute_id,
                    attribute_name: item.attribute_name || item.name || `Attribute #${item.attribute_id}`,
                    attribute_type: item.attribute_type_id || '',
                    value: item.custom_value || '',
                    attribute_value_id: item.attribute_value_id || null
                }));
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

        // Cache DOM elements
        el = {
            // Containers
            container: AF.$('tableContainer'),
            loading: AF.$('tableLoading'),
            empty: AF.$('emptyState'),
            error: AF.$('errorState'),
            errorMessage: AF.$('errorMessage'),
            
            // Form
            formContainer: AF.$('productFormContainer'),
            form: AF.$('productForm'),
            formTitle: AF.$('formTitle'),
            formId: AF.$('formId'),
            
            // Form fields - General
            prodName: AF.$('prodName'),
            prodSku: AF.$('prodSku'),
            prodSlug: AF.$('prodSlug'),
            prodBarcode: AF.$('prodBarcode'),
            prodType: AF.$('prodType'),
            prodBrand: AF.$('prodBrand'),
            prodIsActive: AF.$('prodIsActive'),
            prodIsFeatured: AF.$('prodIsFeatured'),
            prodIsBestseller: AF.$('prodIsBestseller'),
            prodIsNew: AF.$('prodIsNew'),
            prodTenantId: AF.$('prodTenantId'),
            
            // Form fields - Pricing
            prodPrice: AF.$('prodPrice'),
            prodComparePrice: AF.$('prodComparePrice'),
            prodCostPrice: AF.$('prodCostPrice'),
            prodCurrency: AF.$('prodCurrency'),
            prodTaxRate: AF.$('prodTaxRate'),
            
            // Form fields - Inventory
            prodStockQty: AF.$('prodStockQty'),
            prodLowStock: AF.$('prodLowStock'),
            prodStockStatus: AF.$('prodStockStatus'),
            prodManageStock: AF.$('prodManageStock'),
            prodAllowBackorder: AF.$('prodAllowBackorder'),
            
            // Form fields - Physical
            prodWeight: AF.$('prodWeight'),
            prodLength: AF.$('prodLength'),
            prodWidth: AF.$('prodWidth'),
            prodHeight: AF.$('prodHeight'),
            
            // Attributes
            attrSelect: AF.$('attrSelect'),
            btnAddAttribute: AF.$('btnAddAttribute'),
            prodAttributesList: AF.$('prodAttributesList'),
            
            // Variants
            btnGenerateVariants: AF.$('btnGenerateVariants'),
            btnAddVariant: AF.$('btnAddVariant'),
            prodVariantsList: AF.$('prodVariantsList'),
            
            // Images
            prodSelectImageBtn: AF.$('prodSelectImageBtn'),
            prodImagesPreview: AF.$('prodImagesPreview'),
            mediaModal: AF.$('prodMediaStudioModal'),
            mediaFrame: AF.$('prodMediaStudioFrame'),
            mediaClose: AF.$('prodMediaStudioClose'),
            
            // Categories
            prodCategoriesTree: AF.$('prodCategoriesTree'),
            
            // Translations
            prodTranslations: AF.$('prodTranslations'),
            prodLangSelect: AF.$('prodLangSelect'),
            prodAddLangBtn: AF.$('prodAddLangBtn'),
            
            // Table
            tbody: AF.$('tableBody'),
            
            // Filters
            searchInput: AF.$('searchInput'),
            tenantFilter: AF.$('tenantFilter'),
            typeFilter: AF.$('typeFilter'),
            brandFilter: AF.$('brandFilter'),
            statusFilter: AF.$('statusFilter'),
            
            // Buttons
            btnSubmit: AF.$('btnSubmitForm'),
            btnAdd: AF.$('btnAddProduct'),
            btnClose: AF.$('btnCloseForm'),
            btnCancel: AF.$('btnCancelForm'),
            btnApply: AF.$('btnApplyFilters'),
            btnReset: AF.$('btnResetFilters'),
            btnRetry: AF.$('btnRetry'),
            btnDeleteProduct: AF.$('btnDeleteProduct'),
            
            // Pagination
            pagination: AF.$('pagination'),
            paginationInfo: AF.$('paginationInfo'),
            resultsCount: AF.$('resultsCount'),
            resultsCountText: AF.$('resultsCountText')
        };

        // Load translations
        await loadTranslations(state.language);

        // Setup event listeners
        if (el.form) el.form.addEventListener('submit', saveProduct);
        if (el.btnAdd) el.btnAdd.addEventListener('click', () => showForm());
        if (el.btnClose) el.btnClose.addEventListener('click', hideForm);
        if (el.btnCancel) el.btnCancel.addEventListener('click', hideForm);
        if (el.btnApply) el.btnApply.addEventListener('click', applyFilters);
        if (el.btnReset) el.btnReset.addEventListener('click', resetFilters);
        if (el.btnRetry) el.btnRetry.addEventListener('click', () => loadProducts(state.page));
        if (el.btnDeleteProduct) el.btnDeleteProduct.addEventListener('click', () => {
            if (state.currentProduct) deleteProduct(state.currentProduct.id);
        });
        
        // Attributes
        if (el.btnAddAttribute) el.btnAddAttribute.addEventListener('click', addAttribute);
        
        // Variants
        if (el.btnAddVariant) el.btnAddVariant.addEventListener('click', addVariant);
        
        // Images
        if (el.prodSelectImageBtn) el.prodSelectImageBtn.addEventListener('click', openMediaStudio);
        if (el.mediaClose) el.mediaClose.addEventListener('click', closeMediaStudio);
        
        // Translations
        if (el.prodAddLangBtn) el.prodAddLangBtn.addEventListener('click', addTranslation);
        
        // Media Studio message listener
        window.addEventListener('message', (e) => {
            if (e.data && e.data.type === 'media-selected') {
                state.selectedImages = e.data.images || [];
                renderProductImages();
                closeMediaStudio();
            }
        });

        // Initialize tabs
        initTabs();

        // Load dropdown data
        await loadDropdownData();

        // Load initial data
        await loadProducts(1);

        console.log('[Products] Initialized successfully');
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
        removeAttribute,
        updateVariantField,
        removeVariant,
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
    
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            if (window.AdminFramework && !window.page.__fragment_init) {
                init().catch(err => console.error('[Products] Init failed:', err));
            }
        });
    } else {
        if (window.AdminFramework && !window.page.__fragment_init) {
            init().catch(err => console.error('[Products] Init failed:', err));
        }
    }
    
    window.page.__fragment_init = false;

    console.log('[Products] Module loaded');

})();
