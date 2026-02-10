/**
 * Categories Management - Production Version with Full Translation Support
 * Version: 4.1.0 - Fixed issues with translations deletion and table display
 * Compatible with AdminFramework and fragments
 * Supports automatic RTL/LTR direction based on language
 */
(function () {
    'use strict';

    const AF = window.AdminFramework;
    const API = '/api/categories';
    const LANG_API = '/api/languages';
    const TENANT_API = '/api/tenants';

    const state = {
        page: 1,
        perPage: 25,
        filters: {},
        permissions: {},
        translations: {},
        language: window.USER_LANGUAGE || 'ar',
        categories: [], // تخزين البيانات المحملة
        parents: [] // تخزين الفئات الرئيسية
    };

    let el = {};
    let availableLanguages = [];
    let imageTypes = [];
    let deletedTranslations = []; // مصفوفة لتتبع الترجمات المحذوفة

    // ----------------------------
    // Direction helper
    // ----------------------------
    function setDirectionForLang(lang) {
        if (!lang) return;
        const rtlLangs = ['ar', 'he', 'fa', 'ur', 'ps'];
        const isRtl = rtlLangs.includes(String(lang).toLowerCase().substring(0, 2));
        const dir = isRtl ? 'rtl' : 'ltr';

        try { document.documentElement.dir = dir; } catch (e) { /* ignore */ }

        if (document.body) {
            document.body.classList.toggle('rtl', isRtl);
            document.body.classList.toggle('ltr', !isRtl);
        }

        const container = document.getElementById('categoriesPageContainer') || document.querySelector('.page-container');
        if (container) {
            container.dir = dir;
            container.classList.toggle('rtl', isRtl);
            container.classList.toggle('ltr', !isRtl);
        }

        // flip helper icons if used
        document.querySelectorAll('.flip-on-rtl').forEach(el => {
            el.classList.toggle('is-rtl', isRtl);
        });

        console.log('[Categories] direction applied:', dir);
    }

    // ----------------------------
    // LOAD LANGUAGES
    // ----------------------------
    async function loadLanguages() {
        if (!el.langSelect) return;
        el.langSelect.innerHTML = `<option value="">${t('form.translations.choose_lang')}</option>`;
        try {
            const res = await fetch(`${LANG_API}?format=json`, { credentials: 'same-origin' });
            if (!res.ok) throw new Error('Failed to load languages');
            const data = await res.json();
            availableLanguages = data.data?.items || data.data || data || [];
            availableLanguages.forEach(l => {
                const o = document.createElement('option');
                o.value = l.code;
                o.textContent = `${l.code.toUpperCase()} — ${l.name}`;
                el.langSelect.appendChild(o);
            });
        } catch (e) {
            console.warn('Failed to load languages', e);
        }
    }

    // ----------------------------
    // LOAD IMAGE TYPES
    // ----------------------------
    async function loadImageTypes() {
        if (!el.imageTypeSelect) return;
        try {
            const res = await fetch('/api/image-types', { credentials: 'same-origin' });
            if (!res.ok) throw new Error('Failed to load image types');
            const data = await res.json();
            imageTypes = data.data || [];
            el.imageTypeSelect.innerHTML = '';
            imageTypes.forEach(type => {
                const o = document.createElement('option');
                o.value = type.id;
                o.textContent = type.name;
                o.dataset.description = type.description || '';
                el.imageTypeSelect.appendChild(o);
                // Pre-select 'category' type
                if (type.name === 'category') {
                    el.imageTypeSelect.value = type.id;
                    if (el.imageTypeDesc) el.imageTypeDesc.textContent = type.description || '';
                }
            });
            // Add change listener
            el.imageTypeSelect.onchange = () => {
                const selected = imageTypes.find(t => t.id == el.imageTypeSelect.value);
                if (el.imageTypeDesc) el.imageTypeDesc.textContent = selected?.description || '';
            };
        } catch (e) {
            console.warn('Failed to load image types', e);
            el.imageTypeSelect.innerHTML = '<option value="1">category</option>';
        }
    }

    // ----------------------------
    // VERIFY TENANT
    // ----------------------------
    async function verifyTenant() {
        if (!el.tenantId || !el.tenantInfo) return;
        const id = el.tenantId.value.trim();
        if (!id || isNaN(id)) {
            el.tenantInfo.innerHTML = '';
            return;
        }
        try {
            const res = await fetch(`${TENANT_API}/${id}`, { credentials: 'same-origin' });
            if (!res.ok) throw new Error('Tenant verification failed');
            const data = await res.json();
            const tenant = data.data || data;
            if (tenant) {
                el.tenantInfo.innerHTML = `<small style="color:green;">${tenant.name} (${tenant.domain || 'No domain'})</small>`;
            } else {
                el.tenantInfo.innerHTML = '<small style="color:red;">Invalid tenant ID</small>';
            }
        } catch (e) {
            el.tenantInfo.innerHTML = '<small style="color:red;">Error verifying tenant</small>';
        }
    }

    // ----------------------------
    // CREATE TRANSLATION PANEL - FIXED
    // ----------------------------
    function createTranslationPanel(code, data = {}) {
        if (!el.translations) return;

        // التحقق إذا كانت اللوحة موجودة بالفعل
        const existingPanel = el.translations.querySelector(`[data-lang="${code}"]`);
        if (existingPanel) {
            existingPanel.remove(); // إزالة القديم قبل إضافة الجديد
        }

        const langUpper = code.toUpperCase();
        const namePlaceholder = tReplace('form.translations.name_in_lang', { lang: langUpper });
        const slugPlaceholder = tReplace('form.translations.slug_in_lang', { lang: langUpper });
        const descPlaceholder = tReplace('form.translations.description_in_lang', { lang: langUpper });
        const metaTitlePlaceholder = 'Meta Title (' + langUpper + ')';
        const metaDescPlaceholder = 'Meta Description (' + langUpper + ')';
        const metaKeywordsPlaceholder = 'Meta Keywords (' + langUpper + ')';
        const removeText = t('form.translations.remove');

        const div = document.createElement('div');
        div.className = 'translation-panel';
        div.dataset.lang = code;
        div.innerHTML = `
            <div class="translation-panel-header">
                <h5><i class="fas fa-globe"></i> ${langUpper}</h5>
                <button type="button" class="remove btn btn-sm btn-danger">${removeText}</button>
            </div>
            <div class="translation-panel-body">
                <div class="form-row">
                    <div class="form-group">
                        <label>Name *</label>
                        <input class="form-control" name="translations[${code}][name]" value="${esc(data.name || '')}" placeholder="${namePlaceholder}" required>
                    </div>
                    <div class="form-group">
                        <label>Slug *</label>
                        <input class="form-control" name="translations[${code}][slug]" value="${esc(data.slug || '')}" placeholder="${slugPlaceholder}" required>
                    </div>
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea class="form-control" name="translations[${code}][description]" rows="2" placeholder="${descPlaceholder}">${esc(data.description || '')}</textarea>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Meta Title</label>
                        <input class="form-control" name="translations[${code}][meta_title]" value="${esc(data.meta_title || '')}" placeholder="${metaTitlePlaceholder}">
                    </div>
                    <div class="form-group">
                        <label>Meta Keywords</label>
                        <input class="form-control" name="translations[${code}][meta_keywords]" value="${esc(data.meta_keywords || '')}" placeholder="${metaKeywordsPlaceholder}">
                    </div>
                </div>
                <div class="form-group">
                    <label>Meta Description</label>
                    <textarea class="form-control" name="translations[${code}][meta_description]" rows="2" placeholder="${metaDescPlaceholder}">${esc(data.meta_description || '')}</textarea>
                </div>
            </div>
        `;

        // إضافة حدث الحذف بشكل صحيح
        div.querySelector('.remove').onclick = (e) => {
            e.preventDefault();
            e.stopPropagation();

            // تسجيل اللغة للحذف
            const categoryId = el.formId?.value ? parseInt(el.formId.value) : null;
            deletedTranslations.push({
                language_code: code,
                category_id: categoryId
            });

            console.log(`[Categories] Translation marked for deletion: ${code}, category: ${categoryId}`);

            // إزالة اللوحة من DOM
            div.remove();
        };

        el.translations.appendChild(div);
        console.log(`[Categories] Translation panel created for: ${code}`);
    }

    // ----------------------------
    // TRANSLATION SYSTEM
    // ----------------------------
    async function loadTranslations(lang = state.language) {
        try {
            console.log('[Categories] Loading translations for:', lang);
            const response = await fetch(`/languages/Categories/${lang}.json`, { credentials: 'same-origin' });
            if (!response.ok) throw new Error(`Failed to load translations: ${response.status}`);
            const data = await response.json();
            state.translations = data;
            state.language = lang;
            console.log('[Categories] Translations loaded successfully');

            // Apply translations to elements with data-i18n
            const container = document.getElementById('categoriesPageContainer');
            if (!container) return true;
            container.querySelectorAll('[data-i18n]').forEach(el => {
                const key = el.getAttribute('data-i18n');
                const txt = key.split('.').reduce((o, k) => (o && o[k] !== undefined) ? o[k] : null, state.translations);
                if (txt !== null && txt !== undefined) {
                    if (el.tagName === 'INPUT' && el.hasAttribute('placeholder')) {
                        el.placeholder = txt;
                    } else {
                        el.textContent = txt;
                    }
                }
            });
            // placeholders
            container.querySelectorAll('[data-i18n-placeholder]').forEach(el => {
                const key = el.getAttribute('data-i18n-placeholder');
                const txt = key.split('.').reduce((o, k) => (o && o[k] !== undefined) ? o[k] : null, state.translations);
                if (txt !== null && txt !== undefined) el.placeholder = txt;
            });

            return true;
        } catch (error) {
            console.error('[Categories] Failed to load translations:', error);
            if (lang !== 'en') {
                console.log('[Categories] Falling back to English');
                return loadTranslations('en');
            }
            state.translations = getFallbackTranslations();
            return true;
        }
    }

    function getFallbackTranslations() {
        return {
            categories: {
                title: "Categories",
                subtitle: "Manage product and content categories",
                add_new: "Add Category",
                loading: "Loading...",
                no_data: "No data available",
                error: "An error occurred",
                retry: "Retry"
            },
            table: {
                headers: {
                    id: "ID",
                    tenant: "Tenant",
                    image: "Image",
                    name: "Name",
                    slug: "Slug",
                    parent: "Parent",
                    sort_order: "Sort Order",
                    status: "Status",
                    featured: "Featured",
                    actions: "Actions"
                },
                actions: {
                    edit: "Edit",
                    delete: "Delete",
                    duplicate: "Duplicate",
                    confirm_delete: "Are you sure you want to delete this category? This action cannot be undone."
                },
                status: {
                    active: "Active",
                    inactive: "Inactive"
                },
                empty: {
                    title: "No Categories Found",
                    message: "Start by adding categories",
                    add_first: "Add First Category"
                }
            },
            filters: {
                search: "Search",
                search_placeholder: "Search...",
                tenant_id: "Tenant ID",
                tenant_placeholder: "Filter by tenant",
                parent_id: "Parent ID",
                parent_options: { all: "All Parents" },
                status: "Status",
                status_options: {
                    all: "All Status",
                    active: "Active",
                    inactive: "Inactive"
                },
                featured: "Featured",
                featured_options: {
                    all: "All",
                    yes: "Featured",
                    no: "Not Featured"
                },
                apply: "Apply",
                reset: "Reset"
            },
            form: {
                add_title: "Add Category",
                edit_title: "Edit Category",
                fields: {
                    tenant_id: {
                        label: "Tenant ID"
                    },
                    name: {
                        label: "Name",
                        placeholder: "Enter category name"
                    },
                    slug: {
                        label: "Slug",
                        placeholder: "Enter slug"
                    },
                    parent_id: {
                        label: "Parent Category",
                        none: "None (Root)"
                    },
                    sort_order: {
                        label: "Sort Order",
                        placeholder: "Sort order"
                    },
                    status: {
                        label: "Status",
                        active: "Active",
                        inactive: "Inactive"
                    },
                    featured: {
                        label: "Featured",
                        no: "No",
                        yes: "Yes"
                    },
                    description: {
                        label: "Description",
                        placeholder: "Enter description"
                    },
                    image: {
                        label: "Image"
                    }
                },
                translations: {
                    select_lang: "Select Language",
                    choose_lang: "Choose language",
                    name_in_lang: "Name in {lang}",
                    slug_in_lang: "Slug in {lang}",
                    description_in_lang: "Description in {lang}",
                    remove: "Remove",
                    add_translation: "Add Translation"
                },
                buttons: {
                    save: "Save",
                    cancel: "Cancel",
                    saving: "Saving...",
                    updating: "Updating..."
                }
            },
            messages: {
                success: {
                    created: "Category created successfully",
                    updated: "Category updated successfully",
                    deleted: "Category deleted successfully",
                    duplicated: "Category duplicated successfully"
                },
                error: {
                    load_failed: "Failed to load data",
                    save_failed: "Failed to save data",
                    delete_failed: "Failed to delete data",
                    duplicate_failed: "Failed to duplicate data",
                    not_found: "Item not found"
                }
            },
            validation: {
                required: "Required"
            },
            common: {
                select_image: "Select Image",
                duplicate: "Duplicate"
            },
            accessibility: {
                close: "Close"
            },
            pagination: {
                showing: "Showing"
            }
        };
    }

    function t(key, fallback = '') {
        const keys = key.split('.');
        let value = state.translations;
        for (const k of keys) {
            value = value && value[k];
        }
        return value || fallback || key;
    }

    function tReplace(key, replacements = {}) {
        let text = t(key);
        for (const [placeholder, value] of Object.entries(replacements)) {
            text = text.replace(new RegExp(`{${placeholder}}`, 'g'), value);
        }
        return text;
    }

    // ----------------------------
    // API RESPONSE NORMALIZER
    // ----------------------------
    function normalizeApiResponse(response) {
        console.log('[Categories] Normalizing API response:', response);

        let wrapper = null;
        if (response && typeof response === 'object' && response.data !== undefined) wrapper = response;

        const topMeta = response && typeof response === 'object' && response.meta ? response.meta : null;
        const payload = wrapper ? wrapper.data : response;
        const metaFromPayload = payload && typeof payload === 'object' && payload.meta ? payload.meta : null;
        const meta = topMeta || metaFromPayload || null;

        console.log('[Categories] Normalized - payload:', payload, 'meta:', meta);
        return { payload, meta };
    }

    // ----------------------------
    // LOAD PARENTS
    // ----------------------------
    async function loadParents() {
        try {
            console.log('[Categories] Loading parents');
            const params = new URLSearchParams({
                parents: '1',
                limit: 1000,
                tenant_id: window.APP_CONFIG?.TENANT_ID || 1,
                lang: state.language,
                format: 'json'
            });
            const response = await AF.get(`${API}?${params}`);
            const { payload } = normalizeApiResponse(response);
            let items = [];
            if (Array.isArray(payload)) items = payload;
            else if (payload && Array.isArray(payload.items)) items = payload.items;
            else if (payload && Array.isArray(payload.data)) items = payload.data;
            state.parents = items;

            // Populate dropdowns
            if (el.formParentId) {
                el.formParentId.innerHTML = `<option value="">${t('form.fields.parent_id.none')}</option>`;
                items.forEach(p => {
                    const opt = document.createElement('option');
                    opt.value = p.id;
                    opt.textContent = p.name || `Category ${p.id}`;
                    el.formParentId.appendChild(opt);
                });
            }
            if (el.parentFilter) {
                el.parentFilter.innerHTML = `<option value="">${t('filters.parent_options.all')}</option>`;
                items.forEach(p => {
                    const opt = document.createElement('option');
                    opt.value = p.id;
                    opt.textContent = p.name || `Category ${p.id}`;
                    el.parentFilter.appendChild(opt);
                });
            }
            console.log('[Categories] Parents loaded:', items.length);
        } catch (err) {
            console.warn('[Categories] Failed to load parents', err);
        }
    }

    // ----------------------------
    // RENDER FUNCTIONS - FIXED
    // ----------------------------
    async function renderTable(items) {
        console.log('[Categories] Rendering table with', items?.length || 0, 'items');

        // حفظ البيانات في حالة
        state.categories = items || [];

        if (!el.tbody) {
            console.error('[Categories] tbody element not found!');
            return;
        }

        // إخفاء حالات التحميل
        if (el.loading) el.loading.style.display = 'none';

        // إخفاء حالات الخطأ
        if (el.error) el.error.style.display = 'none';

        // حالة عدم وجود عناصر
        if (!items || !items.length) {
            console.log('[Categories] No items to display, showing empty state');
            if (el.empty) {
                el.empty.innerHTML = `
                    <div class="empty-icon">📁</div>
                    <h3>${t('table.empty.title')}</h3>
                    <p>${t('table.empty.message')}</p>
                    ${state.permissions.canCreate ? `<button class="btn btn-primary" onclick="Categories.add()">${t('table.empty.add_first')}</button>` : ''}
                `;
                el.empty.style.display = 'block';
            }
            if (el.container) el.container.style.display = 'none';
            el.tbody.innerHTML = '';

            // تحديث عرض النتائج
            if (el.resultsCount && el.resultsCountText) {
                el.resultsCountText.textContent = '0 records found';
                el.resultsCount.style.display = 'block';
            }
            return;
        }

        // إخفاء حالة عدم وجود بيانات
        if (el.empty) el.empty.style.display = 'none';

        // بناء HTML للجدول
        let html = '';
        for (const item of items) {
            const imageUrl = item.image_url || '/assets/images/no-image.png';
            const image = `<img src="${esc(imageUrl)}" width="50" height="50" style="object-fit:cover;border-radius:4px">`;

            const name = item.name || t('validation.required', 'Unknown');
            const slug = item.slug || t('validation.required', 'N/A');
            const parent = item.parent_name || 'Root';
            const sortOrder = item.sort_order ?? 0;
            const statusText = item.is_active ? t('table.status.active') : t('table.status.inactive');
            const statusClass = item.is_active ? 'badge-success' : 'badge-danger';
            const featuredText = item.is_featured ? t('form.fields.featured.yes') : t('form.fields.featured.no');

            html += `
                <tr>
                    <td>${item.id}</td>
                    <td>${item.tenant_id}</td>
                    <td>${image}</td>
                    <td><strong>${esc(name)}</strong></td>
                    <td>${esc(slug)}</td>
                    <td>${esc(parent)}</td>
                    <td>${sortOrder}</td>
                    <td>
                        <span class="badge ${statusClass}" style="background-color: ${item.is_active ? '#10b981' : '#ef4444'}; color: white; padding: 4px 8px; border-radius: 4px; font-size: 12px;">
                            ${statusText}
                        </span>
                    </td>
                    <td>${featuredText}</td>
                    <td>
                        <div class="table-actions" style="display: flex; gap: 8px;">
                            ${state.permissions.canEdit ? `<button class="btn btn-sm btn-outline" onclick="Categories.edit(${item.id})" style="padding: 4px 8px; border: 1px solid #d1d5db; background: white; color: #374151; border-radius: 4px; font-size: 12px;">${t('table.actions.edit')}</button>` : ''}
                            ${state.permissions.canDelete ? `<button class="btn btn-sm btn-danger" onclick="Categories.remove(${item.id})" style="padding: 4px 8px; background-color: #ef4444; color: white; border: none; border-radius: 4px; font-size: 12px;">${t('table.actions.delete')}</button>` : ''}
                        </div>
                    </td>
                </tr>
            `;
        }

        el.tbody.innerHTML = html;
        if (el.container) el.container.style.display = 'block';

        console.log('[Categories] Table rendered successfully with', items.length, 'items');
    }

    // ----------------------------
    // FORM FUNCTIONS - FIXED
    // ----------------------------
    async function save(e) {
        e.preventDefault();

        if (!AF.Form.validate('categoryForm')) return;

        const formData = AF.Form.getData('categoryForm');
        const id = el.formId.value.trim();
        const isEdit = !!id;

        // ----------------------------
        // جمع الترجمات الحالية
        // ----------------------------
        const translations = [];
        el.translations.querySelectorAll('[data-lang]').forEach(panel => {
            const code = panel.dataset.lang;
            translations.push({
                language_code: code,
                name: panel.querySelector(`[name="translations[${code}][name]"]`)?.value || '',
                slug: panel.querySelector(`[name="translations[${code}][slug]"]`)?.value || '',
                description: panel.querySelector(`[name="translations[${code}][description]"]`)?.value || '',
                meta_title: panel.querySelector(`[name="translations[${code}][meta_title]"]`)?.value || '',
                meta_description: panel.querySelector(`[name="translations[${code}][meta_description]"]`)?.value || '',
                meta_keywords: panel.querySelector(`[name="translations[${code}][meta_keywords]"]`)?.value || ''
            });
        });

        // ----------------------------
        // جمع الترجمات المحذوفة
        // ----------------------------
        const deletions = [...deletedTranslations];
        deletedTranslations = []; // إعادة تهيئة المصفوفة

        const data = {
            tenant_id: window.APP_CONFIG?.TENANT_ID || 1,
            name: formData.name || '',
            slug: formData.slug || '',
            parent_id: formData.parent_id === '' ? null : parseInt(formData.parent_id),
            sort_order: parseInt(formData.sort_order) || 0,
            is_active: formData.is_active === '1' ? 1 : 0,
            is_featured: formData.is_featured === '1' ? 1 : 0,
            description: formData.description || '',
            image_id: formData.image_id ? parseInt(formData.image_id) : null,
            translations: translations,
            deleted_translations: deletions
        };

        if (isEdit) data.id = parseInt(id);

        console.log('[Categories] Saving data:', data);

        try {
            AF.Loading.show(el.btnSubmit, isEdit ? t('form.buttons.updating') : t('form.buttons.saving'));

            const response = await AF.api(`${API}${isEdit ? '/' + data.id : ''}`, {
                method: isEdit ? 'PUT' : 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });

            console.log('[Categories] Save response:', response);

            if (response?.success) {
                AF.success(isEdit ? t('messages.success.updated') : t('messages.success.created'));
                AF.Form.hide('categoryFormContainer');

                // إعادة تحميل البيانات
                await load(state.page);
            } else {
                const msg = response?.message || t('messages.error.save_failed');
                AF.error(msg);
            }

        } catch (err) {
            console.error('[Categories] Save error:', err);
            AF.error(err?.message || t('messages.error.save_failed'));
        } finally {
            AF.Loading.hide(el.btnSubmit);
        }
    }

    async function edit(id) {
        console.log('[Categories] Starting edit for ID:', id);
        try {
            // جلب بيانات الفئة مع كل الترجمات
            const response = await AF.get(`${API}/${id}?format=json&lang=${state.language}&tenant_id=${window.APP_CONFIG?.TENANT_ID || 1}&all_translations=1`);
            const { payload } = normalizeApiResponse(response);

            // تحديد العنصر الصحيح مهما كان شكل payload
            let item = null;
            if (Array.isArray(payload)) item = payload.find(i => i.id == id) || payload[0] || null;
            else if (payload && Array.isArray(payload.items)) item = payload.items.find(i => i.id == id) || payload.items[0] || null;
            else if (payload && (payload.id || payload.name)) item = payload;
            else if (payload && payload.data && Array.isArray(payload.data)) item = payload.data.find(i => i.id == id) || null;

            if (!item) throw new Error(t('messages.error.not_found', 'Item not found'));

            // إعادة تهيئة النموذج
            el.form.reset();
            el.form.classList.remove('was-validated');
            if (el.translations) el.translations.innerHTML = '';

            AF.Form.show('categoryFormContainer', t('form.edit_title'));

            // إعادة تهيئة مصفوفة الترجمات المحذوفة
            deletedTranslations = [];

            el.formId.value = String(item.id || '');
            el.formName.value = item.name || '';
            el.formSlug.value = item.slug || '';
            el.formParentId.value = item.parent_id ? String(item.parent_id) : '';
            el.formSortOrder.value = String(item.sort_order || 0);
            el.formIsActive.value = item.is_active ? '1' : '0';
            el.formIsFeatured.value = item.is_featured ? '1' : '0';
            el.formDescription.value = item.description || '';
            el.imageId.value = item.image_id ? String(item.image_id) : '';

            // تحميل الصورة
            let imageUrl = '/assets/images/no-image.png';
            let thumbUrl = '/assets/images/no-image.png';

            // Determine image URL
            const tenantId = window.APP_CONFIG?.TENANT_ID || 1;

            if (item.image_url && item.image_url !== '/assets/images/no-image.png') {
                imageUrl = item.image_url;
                thumbUrl = item.thumb_url || item.image_url;
            } else if (item.image_id) {
                try {
                    const resImg = await fetch(`/api/images/${item.image_id}`);
                    const dataImg = await resImg.json();
                    if (dataImg?.url) imageUrl = dataImg.url;
                    if (dataImg?.thumb_url) thumbUrl = dataImg.thumb_url;
                    else if (dataImg?.url) thumbUrl = dataImg.url;
                } catch (err) {
                    console.warn('[Categories] Failed to fetch image by ID', err);
                }
            } else {
                // Fetch by owner_id (Category ID) and type=1 (Category)
                try {
                    const resImg = await fetch(`/api/images?tenant_id=${tenantId}&owner_id=${item.id}&image_type_id=1`);
                    const dataImg = await resImg.json();
                    if (dataImg?.data?.length) {
                        imageUrl = dataImg.data[0].url;
                        thumbUrl = dataImg.data[0].thumb_url || imageUrl;
                        // Pre-fill image ID if found
                        if (el.imageId && !el.imageId.value) el.imageId.value = dataImg.data[0].id;
                    }
                } catch (err) {
                    console.warn('[Categories] Failed to fetch image by Owner', err);
                }
            }

            if (el.imagePreview) el.imagePreview.src = thumbUrl || imageUrl;

            // Update links
            const linksContainer = document.getElementById('catImageLinks');
            if (linksContainer) {
                if (item.image_id || (item.image_url && item.image_url !== '/assets/images/no-image.png')) {
                    linksContainer.innerHTML = `
                        <a href="${esc(imageUrl)}" target="_blank" style="text-decoration:none; color:#3b82f6;"><i class="fas fa-expand"></i> Large</a>
                        <a href="${esc(thumbUrl)}" target="_blank" style="text-decoration:none; color:#64748b;"><i class="fas fa-compress"></i> Thumbnail</a>
                    `;
                } else {
                    linksContainer.innerHTML = '';
                }
            }

            // تحميل كل الترجمات
            if (item.translations) {
                console.log('[Categories] Loading translations:', item.translations);
                if (Array.isArray(item.translations)) {
                    item.translations.forEach(tr => {
                        if (tr.language_code) createTranslationPanel(tr.language_code, tr);
                    });
                } else if (typeof item.translations === 'object') {
                    Object.entries(item.translations).forEach(([code, tr]) => {
                        createTranslationPanel(code, tr);
                    });
                }
            }

            // تمرير الـ scroll للنموذج
            setTimeout(() => {
                const container = AF.$('categoryFormContainer');
                if (container) container.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }, 200);

            console.log(`[Categories] Edit form loaded for category ID ${id}`);
        } catch (err) {
            console.error('[Categories] Edit error:', err);
            AF.error(t('messages.error.load_failed'));
        }
    }

    async function remove(id) {
        AF.Modal.confirm(t('table.actions.confirm_delete'), async () => {
            try {
                await AF.delete(`${API}/${id}`, { id: id, tenant_id: window.APP_CONFIG?.TENANT_ID || 1 });
                AF.success(t('messages.success.deleted'));
                load();
            } catch (err) {
                console.error('[Categories] Delete error:', err);
                AF.error(t('messages.error.delete_failed'));
            }
        });
    }

    function add() {
        console.log('[Categories] Opening new form');
        el.form.reset();
        el.form.classList.remove('was-validated');
        el.formId.value = '';
        if (el.imagePreview) el.imagePreview.src = '/assets/images/no-image.png';
        el.imageId.value = '';

        // إعادة تهيئة مصفوفة الترجمات المحذوفة
        deletedTranslations = [];

        // Clear translation panels
        if (el.translations) el.translations.innerHTML = '';

        // Reset image type to category
        if (el.imageTypeSelect) {
            const categoryType = imageTypes.find(t => t.name === 'category');
            if (categoryType) {
                el.imageTypeSelect.value = categoryType.id;
                if (el.imageTypeDesc) el.imageTypeDesc.textContent = categoryType.description || '';
            }
        }
        AF.Form.show('categoryFormContainer', t('form.add_title'));
    }

    function selectImage() {
        console.log('[Categories] Select image clicked');
        const modal = AF.$('catMediaStudioModal');
        const iframe = AF.$('catMediaStudioFrame');
        if (iframe) {
            const tenantId = window.APP_CONFIG?.TENANT_ID || 1;
            const ownerId = el.formId.value ? el.formId.value : 0;
            // Force image_type_id=1 (categories) and lock params
            iframe.src = `/admin/fragments/media_studio.php?embedded=1&tenant_id=${tenantId}&owner_id=${ownerId}&image_type_id=1&mode=select`;
        }
        if (modal) modal.style.display = 'block';

        // Setup close button for modal (if not already handled)
        const closeBtn = document.getElementById('catMediaStudioClose');
        if (closeBtn) {
            closeBtn.onclick = () => { if (modal) modal.style.display = 'none'; };
        }
    }

    // ----------------------------
    // DATA LOADING - FIXED
    // ----------------------------
    async function load(page = 1) {
        try {
            console.log('[Categories] Loading page:', page);

            // إظهار حالة التحميل
            if (el.loading) {
                el.loading.innerHTML = `<div class="spinner"></div><p>${t('categories.loading')}</p>`;
                el.loading.style.display = 'block';
            }
            if (el.container) el.container.style.display = 'none';
            if (el.empty) el.empty.style.display = 'none';
            if (el.error) el.error.style.display = 'none';

            state.page = page;
            const params = new URLSearchParams({
                page: page,
                limit: state.perPage,
                tenant_id: window.APP_CONFIG?.TENANT_ID || 1,
                lang: state.language,
                format: 'json',
                ...state.filters
            });

            console.log('[Categories] Loading from:', `${API}?${params}`);
            const response = await AF.get(`${API}?${params}`);
            console.log('[Categories] Raw response:', response);

            const { payload, meta } = normalizeApiResponse(response);

            let items = [];
            if (Array.isArray(payload)) {
                items = payload;
            } else if (payload && Array.isArray(payload.items)) {
                items = payload.items;
            } else if (payload && Array.isArray(payload.data)) {
                items = payload.data;
            } else if (payload && typeof payload === 'object' && payload.id) {
                items = [payload];
            } else if (payload && typeof payload === 'object') {
                items = Object.values(payload).filter(item => item && typeof item === 'object' && item.id);
            }

            console.log('[Categories] Extracted items (raw):', items);

            // Fetch images for each item
            if (items.length > 0) {
                const tenantId = window.APP_CONFIG?.TENANT_ID || 1;
                // Using image_type_id=1 for Categories (id=1 is category, id=2 is product)
                const imageTypeId = 1;

                try {
                    console.log('[Categories] Fetching images for items...');
                    items = await Promise.all(items.map(async (item) => {
                        try {
                            const res = await fetch(`/api/images?tenant_id=${tenantId}&owner_id=${item.id}&image_type_id=${imageTypeId}`);
                            const data = await res.json();
                            let imageUrl = data?.data?.length ? data.data[0].url : null;
                            // Fallback to item.image_url if fetch returns nothing but item has one
                            if (!imageUrl && item.image_url) imageUrl = item.image_url;
                            return { ...item, image_url: imageUrl }; // Normalize to image_url
                        } catch (e) {
                            console.warn(`[Categories] Failed to fetch image for item ${item.id}`, e);
                            return item;
                        }
                    }));
                } catch (err) {
                    console.error('[Categories] Image fetch error:', err);
                }
            }

            const finalMeta = meta || {
                total: items.length,
                page: page,
                per_page: state.perPage,
                total_pages: Math.ceil(items.length / state.perPage) || 1,
                from: items.length ? ((page - 1) * state.perPage) + 1 : 0,
                to: items.length ? Math.min(page * state.perPage, (meta?.total || items.length)) : 0
            };

            console.log('[Categories] Final items with items:', items.length, 'meta:', finalMeta);

            // Update Pagination
            if (el.pagination && typeof AF.Table !== 'undefined' && typeof AF.Table.renderPagination === 'function') {
                AF.Table.renderPagination(el.pagination, el.paginationInfo, finalMeta);
            } else if (el.paginationInfo) {
                // Manual fallback for pagination info
                const total = finalMeta.total || 0;
                const from = items.length ? ((finalMeta.page - 1) * (finalMeta.per_page || state.perPage)) + 1 : 0;
                const to = items.length ? Math.min(finalMeta.page * (finalMeta.per_page || state.perPage), total) : 0;

                // Fix "Showing 0 to 0 of 0" if data exists but meta is wrong
                const displayFrom = total > 0 && from === 0 ? 1 : from;
                const displayTo = total > 0 && to === 0 ? items.length : to;

                el.paginationInfo.textContent = `Showing ${displayFrom} to ${displayTo} of ${total} results`;
            }

            // Render Table
            await renderTable(items);

            // Update Results Count
            if (el.resultsCount && el.resultsCountText) {
                const total = finalMeta.total || items.length || 0;
                if (total > 0) {
                    el.resultsCountText.textContent = `${total} record${total !== 1 ? 's' : ''} found`;
                } else {
                    el.resultsCountText.textContent = 'No records found';
                }
                el.resultsCount.style.display = 'block';
            }
        } catch (err) {
            console.error('[Categories] Load error:', err);
            if (el.loading) el.loading.style.display = 'none';
            if (el.container) el.container.style.display = 'none';
            if (el.empty) el.empty.style.display = 'none';
            if (el.error) {
                el.error.innerHTML = `
                    <div class="error-icon">⚠️</div>
                    <h3>${t('messages.error.load_failed')}</h3>
                    <p id="errorMessage">${err.message}</p>
                    <button id="btnRetry" class="btn btn-secondary">${t('categories.retry')}</button>
                `;
                el.error.style.display = 'block';

                // إضافة حدث إعادة المحاولة
                setTimeout(() => {
                    const retryBtn = document.getElementById('btnRetry');
                    if (retryBtn) {
                        retryBtn.onclick = () => load(state.page);
                    }
                }, 100);
            }
            if (el.tbody) el.tbody.innerHTML = '';
        }
    }

    function applyFilters() {
        state.filters = {};
        if (el.searchInput) {
            const s = el.searchInput.value.trim();
            if (s) state.filters.search = s;
        }
        if (el.tenantFilter) {
            const t = el.tenantFilter.value.trim();
            if (t && t !== window.APP_CONFIG?.TENANT_ID.toString()) state.filters.tenant_id = t;
        }
        if (el.parentFilter) {
            const p = el.parentFilter.value.trim();
            if (p) state.filters.parent_id = p;
        }
        if (el.statusFilter) {
            const st = el.statusFilter.value;
            if (st !== '') state.filters.is_active = st;
        }
        if (el.featuredFilter) {
            const ft = el.featuredFilter.value;
            if (ft !== '') state.filters.is_featured = ft;
        }
        load(1);
    }

    function resetFilters() {
        if (el.searchInput) el.searchInput.value = '';
        if (el.tenantFilter) el.tenantFilter.value = window.APP_CONFIG?.TENANT_ID || 1;
        if (el.parentFilter) el.parentFilter.value = '';
        if (el.statusFilter) el.statusFilter.value = '';
        if (el.featuredFilter) el.featuredFilter.value = '';
        state.filters = {};
        load(1);
    }

    // ----------------------------
    // UTILITIES
    // ----------------------------
    function esc(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // ----------------------------
    // INITIALIZATION
    // ----------------------------
    async function init() {
        console.log('[Categories] Initializing...');
        const translationsLoaded = await loadTranslations();
        if (translationsLoaded) console.log('[Categories] Translations ready');
        else console.warn('[Categories] Using default texts');

        setDirectionForLang(state.language || window.USER_LANGUAGE || 'en');

        el = {
            loading: AF.$('tableLoading'),
            container: AF.$('tableContainer'),
            empty: AF.$('emptyState'),
            error: AF.$('errorState'),
            errorMessage: AF.$('errorMessage'),
            tbody: AF.$('tableBody'),
            pagination: AF.$('pagination'),
            paginationInfo: AF.$('paginationInfo'),
            form: AF.$('categoryForm'),
            formId: AF.$('formId'),
            formName: AF.$('catName'),
            formSlug: AF.$('catSlug'),
            formParentId: AF.$('catParentId'),
            formSortOrder: AF.$('catSortOrder'),
            formIsActive: AF.$('catIsActive'),
            formIsFeatured: AF.$('catIsFeatured'),
            formDescription: AF.$('catDescription'),
            imagePreview: AF.$('catImagePreview'),
            imageId: AF.$('catImageId'),
            selectImageBtn: AF.$('catSelectImageBtn'),
            searchInput: AF.$('searchInput'),
            tenantFilter: AF.$('tenantFilter'),
            parentFilter: AF.$('parentFilter'),
            statusFilter: AF.$('statusFilter'),
            featuredFilter: AF.$('featuredFilter'),
            btnSubmit: AF.$('btnSubmitForm'),
            btnAdd: AF.$('btnAddCategory'),
            btnClose: AF.$('btnCloseForm'),
            btnCancel: AF.$('btnCancelForm'),
            btnApply: AF.$('btnApplyFilters'),
            btnReset: AF.$('btnResetFilters'),
            btnRetry: AF.$('btnRetry'),
            langSelect: AF.$('catLangSelect'),
            addLangBtn: AF.$('catAddLangBtn'),
            translations: AF.$('catTranslations'),
            tenantId: AF.$('catTenantId'),
            tenantInfo: AF.$('tenantInfo'),
            imageTypeSelect: AF.$('catImageType'),
            imageTypeDesc: AF.$('catImageTypeDesc'),
            resultsCount: AF.$('resultsCount'),
            resultsCountText: AF.$('resultsCountText')
        };

        try {
            const permsScript = AF.$('pagePermissions');
            if (permsScript) state.permissions = JSON.parse(permsScript.textContent);
        } catch (e) {
            state.permissions = {
                canCreate: true,
                canEdit: true,
                canDelete: true,
                canDuplicate: false
            };
        }

        await loadImageTypes();
        await loadLanguages();
        await loadParents();

        // Listen for ImageStudio events from iframe
        const studioFrame = AF.$('catMediaStudioFrame');
        if (studioFrame) {
            studioFrame.onload = () => {
                try {
                    const studioWin = studioFrame.contentWindow;
                    if (!studioWin) return;

                    // Listen for selection inside iframe
                    studioWin.addEventListener('ImageStudio:selected', (e) => {
                        console.log('[Categories] Image selected:', e.detail);
                        const img = e.detail;
                        if (el.imageId) el.imageId.value = img.id;
                        if (el.imagePreview) el.imagePreview.src = img.thumb_url || img.url;

                        // Update links
                        const linksContainer = document.getElementById('catImageLinks');
                        if (linksContainer) {
                            linksContainer.innerHTML = `
                                <a href="${esc(img.url)}" target="_blank" style="text-decoration:none; color:#3b82f6;"><i class="fas fa-expand"></i> Large</a>
                                <a href="${esc(img.thumb_url || img.url)}" target="_blank" style="text-decoration:none; color:#64748b;"><i class="fas fa-compress"></i> Thumbnail</a>
                            `;
                        }
                    });

                    // Listen for close inside iframe
                    studioWin.addEventListener('ImageStudio:close', () => {
                        const modal = AF.$('catMediaStudioModal');
                        if (modal) modal.style.display = 'none';
                    });

                    // Inject styles to hide locked fields
                    const urlParams = new URLSearchParams(studioWin.location.search);
                    if (urlParams.get('image_type_id')) {
                        const typeSelect = studioWin.document.querySelector('select[name="image_type_id"]');
                        if (typeSelect) {
                            typeSelect.style.pointerEvents = 'none';
                            typeSelect.style.background = '#eee';
                        }
                        const ownerInput = studioWin.document.querySelector('input[name="owner_id"]');
                        if (ownerInput) {
                            // Assuming ownerIdFilter exists or just input
                            const ownerFilter = studioWin.document.getElementById('ownerIdFilter');
                            if (ownerFilter) {
                                ownerFilter.readOnly = true;
                                ownerFilter.style.background = '#eee';
                            }
                        }
                    }

                } catch (err) {
                    console.warn('[Categories] Cannot attach events to iframe (CORS?)', err);
                }
            };
        }

        // Backup listener just in case custom event logic changes
        window.addEventListener('ImageStudio:close', () => {
            const modal = AF.$('catMediaStudioModal');
            if (modal) modal.style.display = 'none';
        });

        // إعداد الأحداث
        if (el.form) el.form.onsubmit = save;
        if (el.selectImageBtn) el.selectImageBtn.onclick = selectImage;
        if (el.btnAdd) el.btnAdd.onclick = add;
        if (el.btnClose) el.btnClose.onclick = () => AF.Form.hide('categoryFormContainer');
        if (el.btnCancel) el.btnCancel.onclick = () => AF.Form.hide('categoryFormContainer');
        if (el.btnApply) el.btnApply.onclick = applyFilters;
        if (el.btnReset) el.btnReset.onclick = resetFilters;
        if (el.btnRetry) el.btnRetry.onclick = () => load(state.page);
        if (el.addLangBtn) el.addLangBtn.onclick = () => {
            const code = el.langSelect.value;
            if (code) createTranslationPanel(code, {});
        };
        if (el.tenantId) el.tenantId.oninput = verifyTenant;

        // تحميل البيانات
        load();
        console.log('[Categories] Initialized successfully!');
    }

    // ----------------------------
    // PUBLIC API
    // ----------------------------
    window.Categories = {
        init,
        load,
        edit,
        remove,
        add,
        setLanguage: async (lang) => {
            await loadTranslations(lang);
            setDirectionForLang(lang);
            load(state.page);
        }
    };

    // fragment support
    window.page = { run: init };
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            if (window.AdminFramework && !window.page.__fragment_init) init();
        });
    } else {
        if (window.AdminFramework && !window.page.__fragment_init) init();
    }
    window.page.__fragment_init = false;

})();