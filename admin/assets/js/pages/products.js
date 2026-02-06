(function () {
    'use strict';

    // Configuration from PHP
    const API = window.API_BASE || '/api/product';
    const META_API = window.PRODUCT_META_API || '/api/product_meta';
    const CATEGORIES_API = window.CATEGORIES_API || '/api/categories';
    const MEDIA_API = window.MEDIA_API || '/api/media';
    const VENDORS_API = window.VENDORS_API || '/api/vendors';
    const CSRF_TOKEN = window.CSRF_TOKEN || '';
    const USER = window.CURRENT_USER || {};
    const USER_VENDOR_ID = window.USER_VENDOR_ID || 0;
    const IS_ADMIN = window.IS_ADMIN || false;
    const TRANSLATIONS = window.TRANSLATIONS || {};
    const AVAILABLE_LANGUAGES = window.AVAILABLE_LANGUAGES || [
        { code: 'en', name: 'English' },
        { code: 'ar', name: 'العربية' }
    ];

    // DOM Elements
    const noticeEl = document.getElementById('productsNotice');
    const productsTbody = document.getElementById('productsTbody');
    const productsCount = document.getElementById('productsCount');
    const productSearch = document.getElementById('productSearch');
    const productRefresh = document.getElementById('productRefresh');
    const productNewBtn = document.getElementById('productNewBtn');
    const productNewBtn2 = document.getElementById('productNewBtn2');
    const formWrap = document.getElementById('productFormWrap');
    const productForm = document.getElementById('productForm');
    const saveBtn = document.getElementById('productSaveBtn');
    const cancelBtn = document.getElementById('productCancelBtn');
    const deleteBtn = document.getElementById('productDeleteBtn');
    const errorsBox = document.getElementById('productErrors');

    // Form fields
    const inputId = document.getElementById('product_id');
    const inputVendorId = document.getElementById('product_vendor_id');
    const inputVendorSelect = document.getElementById('product_vendor_select');
    const inputName = document.getElementById('product_name');
    const inputSku = document.getElementById('product_sku');
    const inputSlug = document.getElementById('product_slug');
    const inputBarcode = document.getElementById('product_barcode');
    const selectType = document.getElementById('product_type');
    const selectBrand = document.getElementById('product_brand_id');
    const selectManufacturer = document.getElementById('product_manufacturer_id');
    const inputPublishedAt = document.getElementById('product_published_at');
    const inputDescription = document.getElementById('product_description');
    const inputPrice = document.getElementById('product_price');
    const inputComparePrice = document.getElementById('product_compare_at_price');
    const inputCostPrice = document.getElementById('product_cost_price');
    const inputStock = document.getElementById('product_stock_quantity');
    const inputLowStock = document.getElementById('product_low_stock_threshold');
    const selectStockStatus = document.getElementById('product_stock_status');
    const selectManageStock = document.getElementById('product_manage_stock');
    const selectAllowBackorder = document.getElementById('product_allow_backorder');
    const inputTax = document.getElementById('product_tax_rate');
    const inputWeight = document.getElementById('product_weight');
    const inputLength = document.getElementById('product_length');
    const inputWidth = document.getElementById('product_width');
    const inputHeight = document.getElementById('product_height');

    // Media
    const imagesInput = document.getElementById('product_images_files');
    const imagesPreview = document.getElementById('product_images_preview');
    const mediaStudioBtn = document.getElementById('mediaStudioBtn');

    // Categories
    const categoryList = document.getElementById('categoryList');

    // Attributes
    const attrSelect = document.getElementById('attr_select');
    const attrAddBtn = document.getElementById('attr_add_btn');
    const attributesList = document.getElementById('product_attributes_list');

    // Translations
    const translationsArea = document.getElementById('product_translations_area');
    const toggleTranslationsBtn = document.getElementById('toggleTranslationsBtn');
    const fillFromDefaultBtn = document.getElementById('fillFromDefaultBtn');
    const addLangBtn = document.getElementById('addLangBtn');

    // Variants
    const generateVariantsBtn = document.getElementById('generateVariantsBtn');
    const variantsList = document.getElementById('product_variants_list');
    const variantsSection = document.getElementById('variantsSection');

    // State
    let metaData = null;
    let mediaItems = [];
    let vendorsList = [];

    // Utility Functions
    function showNotice(message, type = 'info') {
        if (!noticeEl) return;
        
        noticeEl.textContent = message;
        noticeEl.className = 'status-notice';
        
        if (type === 'success') {
            noticeEl.classList.add('success');
        } else if (type === 'error') {
            noticeEl.classList.add('error');
        }
        
        if (type === 'success') {
            setTimeout(() => {
                noticeEl.textContent = '';
                noticeEl.className = 'status-notice';
            }, 3000);
        }
    }

    function showErrors(errors) {
        if (!errorsBox) return;
        
        if (!errors) {
            errorsBox.style.display = 'none';
            errorsBox.innerHTML = '';
            return;
        }
        
        let html = '';
        if (typeof errors === 'string') {
            html = `<p>${escapeHtml(errors)}</p>`;
        } else if (Array.isArray(errors)) {
            html = '<ul>';
            errors.forEach(error => {
                html += `<li>${escapeHtml(error)}</li>`;
            });
            html += '</ul>';
        } else if (typeof errors === 'object') {
            html = '<ul>';
            Object.entries(errors).forEach(([field, message]) => {
                html += `<li><strong>${escapeHtml(field)}</strong>: ${escapeHtml(message)}</li>`;
            });
            html += '</ul>';
        }
        
        errorsBox.innerHTML = html;
        errorsBox.style.display = 'block';
        formWrap.scrollIntoView({ behavior: 'smooth' });
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function getPreferredLanguage() {
        return USER.preferred_language || 'en';
    }

    function getTranslation(key, fallback = '') {
        const keys = key.split('.');
        let value = TRANSLATIONS;
        
        for (const k of keys) {
            if (value && typeof value === 'object' && k in value) {
                value = value[k];
            } else {
                return fallback || key;
            }
        }
        
        return value || fallback || key;
    }

    // API Functions
    async function fetchJson(url, options = {}) {
        const defaultOptions = {
            credentials: 'include',
            headers: {
                'Accept': 'application/json',
                'X-CSRF-Token': CSRF_TOKEN
            }
        };

        const mergedOptions = { ...defaultOptions, ...options };
        
        try {
            const response = await fetch(url, mergedOptions);
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            
            const text = await response.text();
            
            try {
                return JSON.parse(text);
            } catch {
                return {
                    success: false,
                    message: text || 'Invalid JSON response'
                };
            }
        } catch (error) {
            console.error('Fetch error:', error);
            return {
                success: false,
                message: error.message || 'Network error'
            };
        }
    }

    async function postFormData(url, formData) {
        try {
            const response = await fetch(url, {
                method: 'POST',
                body: formData,
                credentials: 'include'
            });
            
            const text = await response.text();
            
            try {
                return JSON.parse(text);
            } catch {
                return {
                    success: false,
                    message: text || 'Invalid JSON response'
                };
            }
        } catch (error) {
            console.error('Post error:', error);
            return {
                success: false,
                message: error.message || 'Network error'
            };
        }
    }

    // Load Vendors (for admin)
    async function loadVendors() {
        if (!IS_ADMIN) return;
        
        try {
            const result = await fetchJson(`${VENDORS_API}?format=json`);
            if (result.success) {
                vendorsList = result.data || [];
                if (inputVendorSelect) {
                    inputVendorSelect.innerHTML = '<option value="">' + getTranslation('vendor.choose', '— اختر تاجر —') + '</option>';
                    vendorsList.forEach(vendor => {
                        const option = document.createElement('option');
                        option.value = vendor.id;
                        option.textContent = vendor.name || vendor.shop_name || `Vendor ${vendor.id}`;
                        inputVendorSelect.appendChild(option);
                    });
                }
            }
        } catch (error) {
            console.error('Error loading vendors:', error);
        }
    }

    // Meta Data Loading
    async function loadMetaData() {
        showNotice(getTranslation('loading', 'Loading...'));
        
        const lang = getPreferredLanguage();
        const url = `${META_API}?lang=${encodeURIComponent(lang)}`;
        
        const result = await fetchJson(url);
        
        if (result.success) {
            metaData = result.data || {};
            populateMetaData();
            showNotice('', 'info');
        } else {
            showNotice(result.message || getTranslation('meta_load_failed', 'Failed to load metadata'), 'error');
        }
        
        return metaData;
    }

    function populateMetaData() {
        // Populate brands
        if (selectBrand && metaData.brands) {
            selectBrand.innerHTML = '<option value="">' + getTranslation('general.choose', '— Choose —') + '</option>';
            metaData.brands.forEach(brand => {
                const option = document.createElement('option');
                option.value = brand.id;
                option.textContent = brand.name_translated || brand.name || brand.slug || `Brand ${brand.id}`;
                selectBrand.appendChild(option);
            });
        }

        // Populate manufacturers
        if (selectManufacturer && metaData.manufacturers) {
            selectManufacturer.innerHTML = '<option value="">' + getTranslation('general.choose', '— Choose —') + '</option>';
            metaData.manufacturers.forEach(manufacturer => {
                const option = document.createElement('option');
                option.value = manufacturer.id;
                option.textContent = manufacturer.name || manufacturer.slug || `Manufacturer ${manufacturer.id}`;
                selectManufacturer.appendChild(option);
            });
        }

        // Populate attributes
        if (attrSelect && metaData.attributes) {
            attrSelect.innerHTML = '<option value="">' + getTranslation('attributes.choose_attribute', '— Choose Attribute —') + '</option>';
            metaData.attributes.forEach(attr => {
                const option = document.createElement('option');
                option.value = attr.id;
                option.textContent = attr.name_translated || attr.name || attr.slug || `Attribute ${attr.id}`;
                option.dataset.isVariation = attr.is_variation ? '1' : '0';
                attrSelect.appendChild(option);
            });
        }

        // Build category tree
        if (categoryList && metaData.categories) {
            buildCategoryTree(metaData.categories);
        }
    }

    function buildCategoryTree(categories, parentUl = categoryList, level = 0) {
        categories.forEach(category => {
            const li = document.createElement('li');
            li.style.marginLeft = `${level * 20}px`;
            li.style.marginTop = '5px';

            // Radio button for primary category
            const radio = document.createElement('input');
            radio.type = 'radio';
            radio.name = 'primary_category';
            radio.value = category.id;
            radio.id = `cat_radio_${category.id}`;
            radio.style.marginRight = '8px';

            // Checkbox for category selection
            const checkbox = document.createElement('input');
            checkbox.type = 'checkbox';
            checkbox.value = category.id;
            checkbox.id = `cat_check_${category.id}`;
            checkbox.style.marginRight = '8px';

            // Label
            const label = document.createElement('label');
            label.htmlFor = `cat_check_${category.id}`;
            label.textContent = category.name_translated || category.name || category.slug || `Category ${category.id}`;
            label.style.cursor = 'pointer';
            label.style.fontSize = '14px';

            li.appendChild(radio);
            li.appendChild(checkbox);
            li.appendChild(label);

            // Add toggle button for children
            if (category.children && category.children.length > 0) {
                const toggleBtn = document.createElement('button');
                toggleBtn.type = 'button';
                toggleBtn.textContent = '+';
                toggleBtn.style.marginLeft = '10px';
                toggleBtn.style.padding = '2px 6px';
                toggleBtn.style.fontSize = '12px';
                toggleBtn.style.borderRadius = '3px';
                toggleBtn.style.border = '1px solid var(--border)';
                toggleBtn.style.background = 'white';
                toggleBtn.style.cursor = 'pointer';

                const childUl = document.createElement('ul');
                childUl.style.listStyle = 'none';
                childUl.style.paddingLeft = '0';
                childUl.style.marginTop = '5px';
                childUl.style.display = 'none';

                toggleBtn.addEventListener('click', () => {
                    if (childUl.style.display === 'none') {
                        childUl.style.display = 'block';
                        toggleBtn.textContent = '-';
                    } else {
                        childUl.style.display = 'none';
                        toggleBtn.textContent = '+';
                    }
                });

                li.appendChild(toggleBtn);
                li.appendChild(childUl);
                buildCategoryTree(category.children, childUl, level + 1);
            }

            parentUl.appendChild(li);
        });
    }

    // Product List Management
    async function loadProducts(search = '') {
        showNotice(getTranslation('loading', 'Loading...'));
        
        let url = `${API}?format=json`;
        if (search) {
            url += `&q=${encodeURIComponent(search)}`;
        }
        
        // Add vendor filter for non-admin users
        if (!IS_ADMIN && USER_VENDOR_ID) {
            url += `&vendor_id=${USER_VENDOR_ID}`;
        }
        
        const result = await fetchJson(url);
        
        if (result.success) {
            const products = Array.isArray(result.data) ? result.data : 
                           (result.data && Array.isArray(result.data.data) ? result.data.data : []);
            renderProductsTable(products);
            showNotice('', 'info');
        } else {
            showNotice(result.message || getTranslation('load_failed', 'Failed to load products'), 'error');
            renderProductsTable([]);
        }
    }

    function renderProductsTable(products) {
        if (!productsTbody) return;
        
        productsTbody.innerHTML = '';
        
        if (!products || products.length === 0) {
            productsTbody.innerHTML = `
                <tr>
                    <td colspan="7" style="text-align: center; padding: 40px; color: var(--text-secondary);">
                        ${getTranslation('no_products', 'No products found')}
                    </td>
                </tr>
            `;
            productsCount.textContent = '0';
            return;
        }
        
        productsCount.textContent = products.length.toString();
        
        products.forEach(product => {
            const tr = document.createElement('tr');
            
            // Get translated name
            const lang = getPreferredLanguage();
            let productName = product.name || product.title || '';
            if (product.translations && product.translations[lang]) {
                productName = product.translations[lang].name || productName;
            }
            
            tr.innerHTML = `
                <td>${escapeHtml(product.id)}</td>
                <td>
                    <div style="font-weight: 500; color: var(--text-primary);">${escapeHtml(productName)}</div>
                    ${product.slug ? `<div style="font-size: 12px; color: var(--text-secondary);">/${escapeHtml(product.slug)}</div>` : ''}
                </td>
                <td>${escapeHtml(product.sku || '')}</td>
                <td>
                    <span style="padding: 4px 8px; background: #f0f9ff; color: #0369a1; border-radius: 4px; font-size: 12px;">
                        ${escapeHtml(product.product_type || 'simple')}
                    </span>
                </td>
                <td style="text-align: center; font-weight: 500;">
                    ${product.stock_quantity || 0}
                </td>
                <td style="text-align: center;">
                    <button class="toggleActiveBtn btn small ${product.is_active ? 'primary' : 'outline'}" 
                            data-id="${escapeHtml(product.id)}" 
                            data-active="${product.is_active ? 1 : 0}">
                        ${product.is_active ? getTranslation('general.yes', 'Yes') : getTranslation('general.no', 'No')}
                    </button>
                </td>
                <td>
                    <button class="editBtn btn small outline" data-id="${escapeHtml(product.id)}">
                        ${getTranslation('table.edit', 'Edit')}
                    </button>
                    ${IS_ADMIN || USER_VENDOR_ID === product.vendor_id ? `
                    <button class="deleteBtn btn small danger" data-id="${escapeHtml(product.id)}">
                        ${getTranslation('table.delete', 'Delete')}
                    </button>
                    ` : ''}
                </td>
            `;
            
            productsTbody.appendChild(tr);
        });
        
        // Add event listeners
        document.querySelectorAll('.editBtn').forEach(btn => {
            btn.addEventListener('click', () => openEdit(btn.dataset.id));
        });
        
        document.querySelectorAll('.deleteBtn').forEach(btn => {
            btn.addEventListener('click', () => deleteProduct(btn.dataset.id));
        });
        
        document.querySelectorAll('.toggleActiveBtn').forEach(btn => {
            btn.addEventListener('click', () => toggleActive(btn.dataset.id, btn.dataset.active === '1' ? 0 : 1));
        });
    }

    // Product Form Management
    function openNew() {
        resetForm();
        inputId.value = '0';
        
        // Set vendor ID
        if (IS_ADMIN && inputVendorSelect) {
            // Admin can select vendor
            inputVendorSelect.value = '';
        } else if (!IS_ADMIN && USER_VENDOR_ID) {
            // Non-admin uses their vendor_id
            inputVendorId.value = USER_VENDOR_ID;
        }
        
        formWrap.style.display = 'block';
        deleteBtn.style.display = 'none';
        updateFormVisibility();
        showErrors(null);
        
        // Scroll to form
        formWrap.scrollIntoView({ behavior: 'smooth' });
    }

    async function openEdit(id) {
        showNotice(getTranslation('loading', 'Loading...'));
        
        const result = await fetchJson(`${API}?_fetch_row=1&id=${encodeURIComponent(id)}`);
        
        if (result.success) {
            const product = result.data || result.data?.product || result.data;
            
            // Check permission (non-admin can only edit their own products)
            if (!IS_ADMIN && product.vendor_id != USER_VENDOR_ID) {
                showNotice(getTranslation('permission_denied', 'Permission denied'), 'error');
                return;
            }
            
            populateForm(product);
            formWrap.style.display = 'block';
            deleteBtn.style.display = IS_ADMIN || product.vendor_id == USER_VENDOR_ID ? 'inline-block' : 'none';
            updateFormVisibility();
            showErrors(null);
            showNotice('', 'info');
            
            // Scroll to form
            formWrap.scrollIntoView({ behavior: 'smooth' });
        } else {
            showNotice(result.message || getTranslation('load_error', 'Failed to load product'), 'error');
        }
    }

    function populateForm(product) {
        // Reset form first
        resetForm();
        
        // Basic info
        inputId.value = product.id || '0';
        inputName.value = product.name || product.title || '';
        inputSku.value = product.sku || '';
        inputSlug.value = product.slug || '';
        inputBarcode.value = product.barcode || '';
        selectType.value = product.product_type || 'simple';
        selectBrand.value = product.brand_id || '';
        selectManufacturer.value = product.manufacturer_id || '';
        
        // Set vendor ID
        if (IS_ADMIN && inputVendorSelect && product.vendor_id) {
            inputVendorSelect.value = product.vendor_id;
        } else if (inputVendorId && product.vendor_id) {
            inputVendorId.value = product.vendor_id;
        }
        
        // Dates
        if (product.published_at) {
            try {
                const date = new Date(product.published_at);
                if (!isNaN(date.getTime())) {
                    inputPublishedAt.value = date.toISOString().slice(0, 16);
                }
            } catch (e) {
                console.error('Error parsing date:', e);
            }
        }
        
        // Description - use default language first
        inputDescription.value = product.description || '';
        
        // Pricing
        if (product.pricing && typeof product.pricing === 'object') {
            inputPrice.value = product.pricing.price || '';
            inputComparePrice.value = product.pricing.compare_at_price || '';
            inputCostPrice.value = product.pricing.cost_price || '';
        } else {
            // Try direct fields
            inputPrice.value = product.price || '';
            inputComparePrice.value = product.compare_at_price || '';
            inputCostPrice.value = product.cost_price || '';
        }
        
        // Inventory
        inputStock.value = product.stock_quantity || 0;
        inputLowStock.value = product.low_stock_threshold || 5;
        selectStockStatus.value = product.stock_status || 'in_stock';
        selectManageStock.value = product.manage_stock ? '1' : '0';
        selectAllowBackorder.value = product.allow_backorder ? '1' : '0';
        inputTax.value = product.tax_rate || '15.00';
        
        // Dimensions
        inputWeight.value = product.weight || '';
        inputLength.value = product.length || '';
        inputWidth.value = product.width || '';
        inputHeight.value = product.height || '';
        
        // Categories
        if (product.categories && Array.isArray(product.categories)) {
            product.categories.forEach(cat => {
                const radio = document.querySelector(`input[type="radio"][value="${cat.category_id}"]`);
                const checkbox = document.querySelector(`input[type="checkbox"][value="${cat.category_id}"]`);
                
                if (radio) radio.checked = cat.is_primary == 1;
                if (checkbox) checkbox.checked = true;
                
                if (cat.is_primary == 1) {
                    document.getElementById('product_category_primary').value = cat.category_id;
                }
            });
        }
        
        // Attributes
        if (product.attributes && Array.isArray(product.attributes)) {
            product.attributes.forEach(attr => {
                addAttributeItem(attr.attribute_id, attr.attribute_value_id, attr.custom_value);
            });
        }
        
        // Translations
        if (product.translations && typeof product.translations === 'object') {
            Object.entries(product.translations).forEach(([langCode, translation]) => {
                updateTranslationPanel(langCode, translation);
            });
        }
        
        // Variants
        if (product.variants && Array.isArray(product.variants)) {
            product.variants.forEach(variant => {
                addVariantRow(variant);
            });
        }
        
        // Media
        if (product.media && Array.isArray(product.media)) {
            product.media.forEach(media => {
                const url = media.file_url || media.image_url || media.url;
                if (url) {
                    addMediaPreview(url);
                }
            });
        }
    }

    function updateTranslationPanel(langCode, translation) {
        let panel = translationsArea.querySelector(`.tr-lang-panel[data-lang="${langCode}"]`);
        
        if (!panel) {
            // Create new panel if doesn't exist
            const langInfo = AVAILABLE_LANGUAGES.find(l => l.code === langCode) || { name: langCode };
            addLanguagePanel(langCode, langInfo.name);
            panel = translationsArea.querySelector(`.tr-lang-panel[data-lang="${langCode}"]`);
        }
        
        if (panel) {
            panel.querySelector('.tr-name').value = translation.name || '';
            panel.querySelector('.tr-short').value = translation.short_description || '';
            panel.querySelector('.tr-desc').value = translation.description || '';
            panel.querySelector('.tr-spec').value = translation.specifications || '';
            panel.querySelector('.tr-meta-title').value = translation.meta_title || '';
            panel.querySelector('.tr-meta-keys').value = translation.meta_keywords || '';
            panel.querySelector('.tr-meta-desc').value = translation.meta_description || '';
        }
    }

    function resetForm() {
        productForm.reset();
        imagesPreview.innerHTML = '';
        attributesList.innerHTML = '';
        variantsList.innerHTML = '';
        
        // Reset category selections
        if (categoryList) {
            categoryList.querySelectorAll('input').forEach(input => {
                input.checked = false;
            });
        }
        
        // Reset translations
        translationsArea.querySelectorAll('.tr-lang-panel').forEach(panel => {
            panel.querySelector('.tr-name').value = '';
            panel.querySelector('.tr-short').value = '';
            panel.querySelector('.tr-desc').value = '';
            panel.querySelector('.tr-spec').value = '';
            panel.querySelector('.tr-meta-title').value = '';
            panel.querySelector('.tr-meta-keys').value = '';
            panel.querySelector('.tr-meta-desc').value = '';
        });
        
        // Reset vendor selection for admin
        if (IS_ADMIN && inputVendorSelect) {
            inputVendorSelect.value = '';
        }
    }

    function updateFormVisibility() {
        const type = selectType.value;
        
        // Show/hide variants section
        if (variantsSection) {
            variantsSection.style.display = type === 'variable' ? 'block' : 'none';
        }
    }

    // Attribute Management
    function addAttributeItem(attributeId = '', valueId = '', customValue = '') {
        const itemDiv = document.createElement('div');
        itemDiv.className = 'attr-item';
        
        // Attribute select
        const attrSelect = document.createElement('select');
        attrSelect.className = 'form-select';
        attrSelect.style.flex = '1';
        attrSelect.innerHTML = '<option value="">' + getTranslation('attributes.choose_attribute', '— Choose Attribute —') + '</option>';
        
        if (metaData && metaData.attributes) {
            metaData.attributes.forEach(attr => {
                const option = document.createElement('option');
                option.value = attr.id;
                option.textContent = attr.name_translated || attr.name || attr.slug || `Attribute ${attr.id}`;
                if (attr.id == attributeId) option.selected = true;
                attrSelect.appendChild(option);
            });
        }
        
        // Value select
        const valueSelect = document.createElement('select');
        valueSelect.className = 'form-select';
        valueSelect.style.flex = '1';
        valueSelect.innerHTML = '<option value="">' + getTranslation('attributes.choose_value', '— Choose Value —') + '</option>';
        
        // Custom value input
        const customInput = document.createElement('input');
        customInput.type = 'text';
        customInput.className = 'form-input';
        customInput.style.flex = '2';
        customInput.placeholder = getTranslation('attributes.custom_value', 'Custom value');
        customInput.value = customValue || '';
        
        // Remove button
        const removeBtn = document.createElement('button');
        removeBtn.type = 'button';
        removeBtn.className = 'btn small danger';
        removeBtn.textContent = getTranslation('attributes.remove', 'Remove');
        removeBtn.addEventListener('click', () => itemDiv.remove());
        
        // Populate values when attribute is selected
        attrSelect.addEventListener('change', function() {
            valueSelect.innerHTML = '<option value="">' + getTranslation('attributes.choose_value', '— Choose Value —') + '</option>';
            
            if (this.value && metaData && metaData.attributes) {
                const attr = metaData.attributes.find(a => a.id == this.value);
                if (attr && attr.values) {
                    attr.values.forEach(val => {
                        const option = document.createElement('option');
                        option.value = val.id;
                        option.textContent = val.label_translated || val.value;
                        if (val.id == valueId) option.selected = true;
                        valueSelect.appendChild(option);
                    });
                }
            }
        });
        
        // Trigger change if attribute is pre-selected
        if (attributeId) {
            setTimeout(() => {
                attrSelect.value = attributeId;
                const event = new Event('change');
                attrSelect.dispatchEvent(event);
                
                // Set value after a delay to ensure options are loaded
                setTimeout(() => {
                    if (valueId && valueSelect) {
                        valueSelect.value = valueId;
                    }
                }, 100);
            }, 100);
        }
        
        itemDiv.appendChild(attrSelect);
        itemDiv.appendChild(valueSelect);
        itemDiv.appendChild(customInput);
        itemDiv.appendChild(removeBtn);
        attributesList.appendChild(itemDiv);
    }

    function collectAttributes() {
        const attributes = [];
        document.querySelectorAll('.attr-item').forEach(item => {
            const attrSelect = item.querySelector('select');
            const valueSelect = item.querySelectorAll('select')[1];
            const customInput = item.querySelector('input[type="text"]');
            
            if (attrSelect && attrSelect.value) {
                attributes.push({
                    attribute_id: parseInt(attrSelect.value),
                    attribute_value_id: valueSelect && valueSelect.value ? parseInt(valueSelect.value) : null,
                    custom_value: customInput ? customInput.value : ''
                });
            }
        });
        
        document.getElementById('product_attributes').value = JSON.stringify(attributes);
        return attributes;
    }

    // Variant Management
    function addVariantRow(variant = {}) {
        const rowDiv = document.createElement('div');
        rowDiv.className = 'variant-row';
        
        const skuInput = document.createElement('input');
        skuInput.type = 'text';
        skuInput.className = 'form-input';
        skuInput.placeholder = getTranslation('general.sku', 'SKU');
        skuInput.value = variant.sku || '';
        skuInput.style.flex = '1';
        
        const stockInput = document.createElement('input');
        stockInput.type = 'number';
        stockInput.className = 'form-input';
        stockInput.placeholder = getTranslation('inventory.stock', 'Stock');
        stockInput.value = variant.stock_quantity || 0;
        stockInput.style.flex = '1';
        
        const priceInput = document.createElement('input');
        priceInput.type = 'text';
        priceInput.className = 'form-input';
        priceInput.placeholder = getTranslation('pricing.price', 'Price');
        priceInput.value = variant.price || '';
        priceInput.style.flex = '1';
        
        const activeLabel = document.createElement('label');
        activeLabel.style.display = 'flex';
        activeLabel.style.alignItems = 'center';
        activeLabel.style.gap = '5px';
        activeLabel.style.flex = '1';
        
        const activeCheckbox = document.createElement('input');
        activeCheckbox.type = 'checkbox';
        activeCheckbox.checked = variant.is_active !== 0;
        activeLabel.appendChild(activeCheckbox);
        activeLabel.appendChild(document.createTextNode(getTranslation('products.active', 'Active')));
        
        const removeBtn = document.createElement('button');
        removeBtn.type = 'button';
        removeBtn.className = 'btn small danger';
        removeBtn.textContent = getTranslation('attributes.remove', 'Remove');
        removeBtn.addEventListener('click', () => rowDiv.remove());
        
        rowDiv.appendChild(skuInput);
        rowDiv.appendChild(stockInput);
        rowDiv.appendChild(priceInput);
        rowDiv.appendChild(activeLabel);
        rowDiv.appendChild(removeBtn);
        
        variantsList.appendChild(rowDiv);
    }

    function generateVariants() {
        const attributes = collectAttributes().filter(attr => {
            const attrOption = Array.from(attrSelect.options).find(opt => opt.value == attr.attribute_id);
            return attrOption && attrOption.dataset.isVariation == '1' && attr.attribute_value_id;
        });
        
        if (attributes.length === 0) {
            alert(getTranslation('variants.no_variation_attributes', 'No variation attributes selected'));
            return;
        }
        
        // Clear existing variants
        variantsList.innerHTML = '';
        
        // Generate variants (simplified - in real app, generate all combinations)
        addVariantRow({ sku: '', stock_quantity: 0, price: '', is_active: 1 });
        addVariantRow({ sku: '', stock_quantity: 0, price: '', is_active: 1 });
    }

    function collectVariants() {
        const variants = [];
        document.querySelectorAll('.variant-row').forEach(row => {
            const inputs = row.querySelectorAll('input');
            variants.push({
                sku: inputs[0]?.value || '',
                stock_quantity: parseInt(inputs[1]?.value || 0),
                price: inputs[2]?.value || '',
                is_active: inputs[3]?.checked ? 1 : 0
            });
        });
        
        document.getElementById('product_variants').value = JSON.stringify(variants);
        return variants;
    }

    // Category Management
    function collectCategories() {
        const categories = [];
        const primary = document.querySelector('input[name="primary_category"]:checked');
        
        if (primary) {
            categories.push(parseInt(primary.value));
            document.getElementById('product_category_primary').value = primary.value;
        }
        
        document.querySelectorAll('input[type="checkbox"]:checked').forEach(checkbox => {
            if (primary && checkbox.value === primary.value) return;
            categories.push(parseInt(checkbox.value));
        });
        
        document.getElementById('product_categories_json').value = JSON.stringify(categories);
        return categories;
    }

    // Translation Management
    function collectTranslations() {
        const translations = {};
        
        document.querySelectorAll('.tr-lang-panel').forEach(panel => {
            const lang = panel.dataset.lang;
            const name = panel.querySelector('.tr-name')?.value.trim() || '';
            const shortDesc = panel.querySelector('.tr-short')?.value.trim() || '';
            const desc = panel.querySelector('.tr-desc')?.value.trim() || '';
            const spec = panel.querySelector('.tr-spec')?.value.trim() || '';
            const metaTitle = panel.querySelector('.tr-meta-title')?.value.trim() || '';
            const metaKeywords = panel.querySelector('.tr-meta-keys')?.value.trim() || '';
            const metaDesc = panel.querySelector('.tr-meta-desc')?.value.trim() || '';
            
            if (name || shortDesc || desc || spec || metaTitle || metaKeywords || metaDesc) {
                translations[lang] = {
                    name,
                    short_description: shortDesc,
                    description: desc,
                    specifications: spec,
                    meta_title: metaTitle,
                    meta_keywords: metaKeywords,
                    meta_description: metaDesc
                };
            }
        });
        
        document.getElementById('product_translations').value = JSON.stringify(translations);
        return translations;
    }

    function addLanguagePanel(langCode, displayName) {
        if (translationsArea.querySelector(`[data-lang="${langCode}"]`)) {
            alert(getTranslation('translations.language_exists', 'Language already exists'));
            return;
        }
        
        const panel = document.createElement('div');
        panel.className = 'tr-lang-panel';
        panel.dataset.lang = langCode;
        
        panel.innerHTML = `
            <div class="tr-lang-header">
                <strong>${escapeHtml(displayName)} (${escapeHtml(langCode)})</strong>
                <button type="button" class="btn small toggle-lang" data-lang="${escapeHtml(langCode)}">
                    ${getTranslation('translations.collapse', 'Collapse')}
                </button>
            </div>
            <div class="tr-lang-body">
                <div style="display: grid; gap: 10px;">
                    <div>
                        <label class="form-label">${getTranslation('general.name', 'Name')}</label>
                        <input class="tr-name form-input" data-lang="${escapeHtml(langCode)}">
                    </div>
                    <div>
                        <label class="form-label">${getTranslation('translations.short_description', 'Short Description')}</label>
                        <input class="tr-short form-input" data-lang="${escapeHtml(langCode)}">
                    </div>
                    <div>
                        <label class="form-label">${getTranslation('general.description', 'Description')}</label>
                        <textarea class="tr-desc form-textarea" data-lang="${escapeHtml(langCode)}" rows="3"></textarea>
                    </div>
                    <div>
                        <label class="form-label">${getTranslation('translations.specifications', 'Specifications')}</label>
                        <textarea class="tr-spec form-textarea" data-lang="${escapeHtml(langCode)}" rows="2"></textarea>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                        <div>
                            <label class="form-label">${getTranslation('translations.meta_title', 'Meta Title')}</label>
                            <input class="tr-meta-title form-input" data-lang="${escapeHtml(langCode)}">
                        </div>
                        <div>
                            <label class="form-label">${getTranslation('translations.meta_keywords', 'Meta Keywords')}</label>
                            <input class="tr-meta-keys form-input" data-lang="${escapeHtml(langCode)}">
                        </div>
                    </div>
                    <div>
                        <label class="form-label">${getTranslation('translations.meta_description', 'Meta Description')}</label>
                        <input class="tr-meta-desc form-input" data-lang="${escapeHtml(langCode)}">
                    </div>
                </div>
            </div>
        `;
        
        translationsArea.appendChild(panel);
    }

    // Media Management
    function addMediaPreview(url) {
        const mediaItem = document.createElement('div');
        mediaItem.className = 'media-item';
        
        const img = document.createElement('img');
        img.src = url;
        img.alt = '';
        img.style.width = '100%';
        img.style.height = '100%';
        img.style.objectFit = 'cover';
        
        mediaItem.appendChild(img);
        imagesPreview.appendChild(mediaItem);
    }

    async function openMediaStudio() {
        // Load media items
        const result = await fetchJson(MEDIA_API);
        
        if (!result.success) {
            alert(getTranslation('media.load_failed', 'Failed to load media'));
            return;
        }
        
        mediaItems = result.data || [];
        
        // Create modal
        const modal = document.createElement('div');
        modal.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
        `;
        
        const modalContent = document.createElement('div');
        modalContent.style.cssText = `
            background: white;
            border-radius: 8px;
            padding: 20px;
            max-width: 800px;
            max-height: 80vh;
            overflow: auto;
            width: 90%;
        `;
        
        const title = document.createElement('h3');
        title.textContent = getTranslation('media.studio', 'Media Studio');
        title.style.marginBottom = '20px';
        
        const grid = document.createElement('div');
        grid.style.cssText = `
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            gap: 10px;
            margin-bottom: 20px;
        `;
        
        mediaItems.forEach(media => {
            const item = document.createElement('div');
            item.style.cssText = `
                cursor: pointer;
                border: 2px solid transparent;
                border-radius: 6px;
                overflow: hidden;
                transition: border-color 0.2s;
            `;
            
            item.addEventListener('click', () => {
                addMediaPreview(media.url);
                modal.remove();
            });
            
            const img = document.createElement('img');
            img.src = media.thumbnail_url || media.url;
            img.style.width = '100%';
            img.style.height = '80px';
            img.style.objectFit = 'cover';
            
            item.appendChild(img);
            grid.appendChild(item);
        });
        
        const closeBtn = document.createElement('button');
        closeBtn.textContent = getTranslation('general.close', 'Close');
        closeBtn.className = 'btn outline';
        closeBtn.style.marginLeft = 'auto';
        closeBtn.addEventListener('click', () => modal.remove());
        
        modalContent.appendChild(title);
        modalContent.appendChild(grid);
        modalContent.appendChild(closeBtn);
        modal.appendChild(modalContent);
        
        modal.addEventListener('click', (e) => {
            if (e.target === modal) modal.remove();
        });
        
        document.body.appendChild(modal);
    }

    // Save Product
    async function saveProduct(e) {
        e.preventDefault();
        
        // Validate required fields
        if (!inputName.value.trim()) {
            showErrors(getTranslation('validation.name_required', 'Product name is required'));
            return;
        }
        
        if (!inputPrice.value.trim()) {
            showErrors(getTranslation('validation.price_required', 'Price is required'));
            return;
        }
        
        showNotice(getTranslation('saving', 'Saving...'));
        showErrors(null);
        
        // Collect all data
        collectTranslations();
        collectAttributes();
        collectVariants();
        collectCategories();
        
        // Update vendor_id if admin selected one
        if (IS_ADMIN && inputVendorSelect && inputVendorSelect.value) {
            inputVendorId.value = inputVendorSelect.value;
        }
        
        const formData = new FormData(productForm);
        
        // Log form data for debugging
        console.log('Sending form data:');
        for (let [key, value] of formData.entries()) {
            console.log(key + ':', value);
        }
        
        try {
            const result = await postFormData(API, formData);
            
            console.log('Save result:', result);
            
            if (result.success) {
                showNotice(result.message || getTranslation('save_success', 'Product saved successfully'), 'success');
                formWrap.style.display = 'none';
                await loadProducts(productSearch.value);
            } else {
                showNotice(result.message || getTranslation('save_failed', 'Failed to save product'), 'error');
                showErrors(result.errors || result.message);
            }
        } catch (error) {
            console.error('Save error:', error);
            showNotice(error.message || getTranslation('network_error', 'Network error'), 'error');
        }
    }

    // Delete Product
    async function deleteProduct(id) {
        if (!confirm(getTranslation('confirm_delete', 'Are you sure you want to delete this product?'))) {
            return;
        }
        
        showNotice(getTranslation('deleting', 'Deleting...'));
        
        const formData = new FormData();
        formData.append('action', 'delete');
        formData.append('id', id);
        formData.append('csrf_token', CSRF_TOKEN);
        
        const result = await postFormData(API, formData);
        
        if (result.success) {
            showNotice(result.message || getTranslation('delete_success', 'Product deleted successfully'), 'success');
            await loadProducts(productSearch.value);
            formWrap.style.display = 'none';
        } else {
            showNotice(result.message || getTranslation('delete_failed', 'Failed to delete product'), 'error');
        }
    }

    // Toggle Active Status
    async function toggleActive(id, newState) {
        const formData = new FormData();
        formData.append('action', 'toggle_active');
        formData.append('id', id);
        formData.append('is_active', newState);
        formData.append('csrf_token', CSRF_TOKEN);
        
        const result = await postFormData(API, formData);
        
        if (result.success) {
            showNotice(getTranslation('update_success', 'Updated successfully'), 'success');
            await loadProducts(productSearch.value);
        } else {
            showNotice(result.message || getTranslation('update_failed', 'Update failed'), 'error');
        }
    }

    // Event Listeners
    function setupEventListeners() {
        // New product buttons
        if (productNewBtn) {
            productNewBtn.addEventListener('click', openNew);
        }
        if (productNewBtn2) {
            productNewBtn2.addEventListener('click', openNew);
        }
        
        // Refresh button
        if (productRefresh) {
            productRefresh.addEventListener('click', () => loadProducts(productSearch.value));
        }
        
        // Search input
        if (productSearch) {
            let searchTimeout;
            productSearch.addEventListener('input', () => {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    loadProducts(productSearch.value);
                }, 500);
            });
        }
        
        // Save button
        if (saveBtn) {
            saveBtn.addEventListener('click', saveProduct);
        }
        
        // Cancel button
        if (cancelBtn) {
            cancelBtn.addEventListener('click', () => {
                formWrap.style.display = 'none';
                showErrors(null);
            });
        }
        
        // Delete button
        if (deleteBtn) {
            deleteBtn.addEventListener('click', () => {
                const id = inputId.value;
                if (id && id !== '0') {
                    deleteProduct(id);
                }
            });
        }
        
        // Product type change
        if (selectType) {
            selectType.addEventListener('change', updateFormVisibility);
        }
        
        // Add attribute button
        if (attrAddBtn) {
            attrAddBtn.addEventListener('click', () => {
                if (!attrSelect.value) {
                    alert(getTranslation('attributes.choose_first', 'Please choose an attribute first'));
                    return;
                }
                addAttributeItem();
            });
        }
        
        // Generate variants button
        if (generateVariantsBtn) {
            generateVariantsBtn.addEventListener('click', generateVariants);
        }
        
        // Media studio button
        if (mediaStudioBtn) {
            mediaStudioBtn.addEventListener('click', openMediaStudio);
        }
        
        // Toggle translations
        if (toggleTranslationsBtn) {
            toggleTranslationsBtn.addEventListener('click', () => {
                if (translationsArea.style.display === 'none') {
                    translationsArea.style.display = 'block';
                    toggleTranslationsBtn.textContent = getTranslation('translations.hide_translations', 'Hide Translations');
                } else {
                    translationsArea.style.display = 'none';
                    toggleTranslationsBtn.textContent = getTranslation('translations.show_translations', 'Show Translations');
                }
            });
        }
        
        // Fill from default
        if (fillFromDefaultBtn) {
            fillFromDefaultBtn.addEventListener('click', () => {
                const defaultName = inputName.value;
                if (!defaultName) {
                    alert(getTranslation('translations.empty_default', 'Default name is empty'));
                    return;
                }
                
                document.querySelectorAll('.tr-name').forEach(input => {
                    if (!input.value) {
                        input.value = defaultName;
                    }
                });
            });
        }
        
        // Add language
        if (addLangBtn) {
            addLangBtn.addEventListener('click', () => {
                const langCode = prompt(getTranslation('translations.enter_code', 'Enter language code (e.g., fr):'), '');
                if (!langCode) return;
                
                const langName = prompt(getTranslation('translations.enter_name', 'Enter language name:'), langCode);
                if (!langName) return;
                
                addLanguagePanel(langCode, langName);
            });
        }
        
        // Toggle language panels
        translationsArea.addEventListener('click', (e) => {
            if (e.target.classList.contains('toggle-lang')) {
                const lang = e.target.dataset.lang;
                const body = e.target.closest('.tr-lang-panel').querySelector('.tr-lang-body');
                
                if (body.style.display === 'none') {
                    body.style.display = 'block';
                    e.target.textContent = getTranslation('translations.collapse', 'Collapse');
                } else {
                    body.style.display = 'none';
                    e.target.textContent = getTranslation('translations.expand', 'Expand');
                }
            }
        });
        
        // Image upload preview
        if (imagesInput) {
            imagesInput.addEventListener('change', function() {
                const files = Array.from(this.files);
                files.forEach(file => {
                    if (file.type.startsWith('image/')) {
                        const reader = new FileReader();
                        reader.onload = (e) => {
                            addMediaPreview(e.target.result);
                        };
                        reader.readAsDataURL(file);
                    }
                });
            });
        }
    }

    // Initialize
    async function init() {
        setupEventListeners();
        await Promise.all([
            loadMetaData(),
            IS_ADMIN ? loadVendors() : Promise.resolve()
        ]);
        await loadProducts();
    }

    // Start when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();