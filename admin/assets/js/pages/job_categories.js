/**
 * Job Categories Management - Production Version with Full Translation Support
 * Version: 1.0.0
 * Compatible with AdminFramework and fragments
 * Supports automatic RTL/LTR direction based on language
 */
(function () {
    'use strict';

    const AF = window.AdminFramework;
    const API = '/api/job_categories';
    const LANG_API = '/api/languages';
    
    // Get image type ID from page meta tag (set in PHP)
    const metaTag = document.querySelector('meta[data-page="job_categories"]');
    const IMAGE_TYPE_ID = metaTag ? parseInt(metaTag.dataset.imageTypeId) : 11;

    const state = {
        page: 1,
        perPage: 25,
        filters: {},
        permissions: {},
        translations: {},
        language: window.USER_LANGUAGE || 'en',
        categories: [],
        parents: []
    };

    let el = {};
    let availableLanguages = [];
    let imageTypes = [];
    let deletedTranslations = [];

    // ----------------------------
    // Direction helper
    // Note: RTL language list should match the backend configuration
    // ----------------------------
    function setDirectionForLang(lang) {
        if (!lang) return;
        // RTL languages - should be synchronized with backend
        const rtlLangs = ['ar', 'he', 'fa', 'ur', 'ps'];
        const isRtl = rtlLangs.includes(String(lang).toLowerCase().substring(0, 2));
        const dir = isRtl ? 'rtl' : 'ltr';

        try { document.documentElement.dir = dir; } catch (e) { /* ignore */ }

        if (document.body) {
            document.body.classList.toggle('rtl', isRtl);
            document.body.classList.toggle('ltr', !isRtl);
        }

        const container = document.getElementById('jobCategoriesPageContainer') || document.querySelector('.page-container');
        if (container) {
            container.dir = dir;
            container.classList.toggle('rtl', isRtl);
            container.classList.toggle('ltr', !isRtl);
        }

        document.querySelectorAll('.flip-on-rtl').forEach(el => {
            el.classList.toggle('is-rtl', isRtl);
        });

        console.log('[JobCategories] direction applied:', dir);
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
            
            // Find job_category type (id=11) and set it as default
            const jobCategoryType = imageTypes.find(t => t.id === IMAGE_TYPE_ID);
            if (jobCategoryType) {
                const o = document.createElement('option');
                o.value = jobCategoryType.id;
                o.textContent = jobCategoryType.name;
                o.dataset.description = jobCategoryType.description || '';
                o.selected = true;
                el.imageTypeSelect.appendChild(o);
                if (el.imageTypeDesc) el.imageTypeDesc.textContent = jobCategoryType.description || '';
            } else {
                // Fallback if type 11 not found
                el.imageTypeSelect.innerHTML = '<option value="11">Job Category</option>';
            }
        } catch (e) {
            console.warn('Failed to load image types', e);
            el.imageTypeSelect.innerHTML = '<option value="11">Job Category</option>';
        }
    }

    // ----------------------------
    // CREATE TRANSLATION PANEL
    // ----------------------------
    function createTranslationPanel(code, data = {}) {
        if (!el.translations) return;

        const existingPanel = el.translations.querySelector(`[data-lang="${code}"]`);
        if (existingPanel) {
            existingPanel.remove();
        }

        const langUpper = code.toUpperCase();
        const namePlaceholder = tReplace('form.translations.name_in_lang', { lang: langUpper });
        const descPlaceholder = tReplace('form.translations.description_in_lang', { lang: langUpper });
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
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea class="form-control" name="translations[${code}][description]" rows="3" placeholder="${descPlaceholder}">${esc(data.description || '')}</textarea>
                </div>
            </div>
        `;

        div.querySelector('.remove').onclick = (e) => {
            e.preventDefault();
            e.stopPropagation();

            const categoryId = el.formId?.value ? parseInt(el.formId.value) : null;
            deletedTranslations.push({
                language_code: code,
                category_id: categoryId
            });

            console.log(`[JobCategories] Translation marked for deletion: ${code}, category: ${categoryId}`);
            div.remove();
        };

        el.translations.appendChild(div);
        console.log(`[JobCategories] Translation panel created for: ${code}`);
    }

    // ----------------------------
    // TRANSLATION SYSTEM
    // ----------------------------
    async function loadTranslations(lang = state.language) {
        try {
            console.log('[JobCategories] Loading translations for:', lang);
            const response = await fetch(`/languages/JobCategories/${lang}.json`, { credentials: 'same-origin' });
            if (!response.ok) throw new Error(`Failed to load translations: ${response.status}`);
            const data = await response.json();
            state.translations = data;
            state.language = lang;
            console.log('[JobCategories] Translations loaded successfully');

            const container = document.getElementById('jobCategoriesPageContainer');
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
            
            container.querySelectorAll('[data-i18n-placeholder]').forEach(el => {
                const key = el.getAttribute('data-i18n-placeholder');
                const txt = key.split('.').reduce((o, k) => (o && o[k] !== undefined) ? o[k] : null, state.translations);
                if (txt !== null && txt !== undefined) el.placeholder = txt;
            });

            return true;
        } catch (error) {
            console.error('[JobCategories] Failed to load translations:', error);
            if (lang !== 'en') {
                console.log('[JobCategories] Falling back to English');
                return loadTranslations('en');
            }
            state.translations = getFallbackTranslations();
            return true;
        }
    }

    function getFallbackTranslations() {
        return {
            job_categories: {
                title: "Job Categories",
                subtitle: "Manage job categories and classifications",
                add_new: "Add Job Category",
                loading: "Loading...",
                no_data: "No data available",
                error: "An error occurred",
                retry: "Retry"
            },
            table: {
                headers: {
                    id: "ID",
                    image: "Image",
                    name: "Name",
                    slug: "Slug",
                    parent: "Parent",
                    sort_order: "Sort Order",
                    status: "Status",
                    actions: "Actions"
                },
                actions: {
                    edit: "Edit",
                    delete: "Delete",
                    confirm_delete: "Are you sure you want to delete this category?"
                }
            },
            form: {
                add_title: "Add Job Category",
                edit_title: "Edit Job Category",
                fields: {
                    parent: { label: "Parent Category", none: "None (Top Level)" },
                    slug: { label: "Slug", placeholder: "category-slug" },
                    sort_order: { label: "Sort Order", placeholder: "0" },
                    is_active: { label: "Status", active: "Active", inactive: "Inactive" }
                },
                translations: {
                    title: "Translations",
                    choose_lang: "Choose language",
                    add: "Add Translation",
                    remove: "Remove",
                    name_in_lang: "Name ({lang})",
                    description_in_lang: "Description ({lang})"
                },
                actions: { save: "Save", cancel: "Cancel" }
            },
            filters: {
                search: "Search",
                parent: "Parent Category",
                status: "Status",
                apply: "Apply Filters",
                all_parents: "All Categories",
                all_statuses: "All Statuses",
                active: "Active",
                inactive: "Inactive"
            },
            messages: {
                category_created: "Category created successfully",
                category_updated: "Category updated successfully",
                category_deleted: "Category deleted successfully",
                delete_failed: "Failed to delete category",
                media_studio_unavailable: "Media Studio not available. Please enter URL manually."
            }
        };
    }

    function t(key, fallback = '') {
        const keys = key.split('.');
        let value = state.translations;
        for (const k of keys) {
            if (value && typeof value === 'object' && k in value) {
                value = value[k];
            } else {
                return fallback || key;
            }
        }
        return typeof value === 'string' ? value : fallback || key;
    }

    function tReplace(key, replacements = {}) {
        let text = t(key, key);
        for (const [placeholder, value] of Object.entries(replacements)) {
            text = text.replace(new RegExp(`\\{${placeholder}\\}`, 'g'), value);
        }
        return text;
    }

    function esc(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    // ----------------------------
    // LOAD PARENT CATEGORIES
    // ----------------------------
    async function loadParentCategories() {
        try {
            const res = await AF.get(`${API}?per_page=1000&is_active=1`);
            if (res.success && res.data) {
                state.parents = res.data.items || res.data || [];
                populateParentSelect(el.categoryParent, state.parents);
                populateParentSelect(el.filterParent, state.parents, true);
            }
        } catch (e) {
            console.warn('Failed to load parent categories', e);
        }
    }

    function populateParentSelect(selectEl, parents, includeAll = false) {
        if (!selectEl) return;
        const currentId = el.formId?.value ? parseInt(el.formId.value) : null;
        
        selectEl.innerHTML = includeAll 
            ? `<option value="">${t('filters.all_parents')}</option>` 
            : `<option value="">${t('form.fields.parent.none')}</option>`;
        
        parents.forEach(cat => {
            // Prevent circular references: don't show self as parent option
            // Note: Backend should validate deep hierarchical loops (A->B->C->A)
            if (currentId && cat.id === currentId) return;
            const o = document.createElement('option');
            o.value = cat.id;
            o.textContent = cat.name || `Category ${cat.id}`;
            selectEl.appendChild(o);
        });
    }

    // ----------------------------
    // LOAD CATEGORIES
    // ----------------------------
    async function loadCategories() {
        if (!el.tableBody) return;

        el.tableBody.innerHTML = `
            <tr>
                <td colspan="8" class="loading-state">
                    <div class="spinner"></div>
                    <p>${t('job_categories.loading')}</p>
                </td>
            </tr>
        `;

        try {
            const params = new URLSearchParams({
                page: state.page,
                per_page: state.perPage,
                ...state.filters
            });

            const res = await AF.get(`${API}?${params}`);
            
            if (!res.success) {
                throw new Error(res.message || 'Failed to load categories');
            }

            const data = res.data;
            state.categories = data.items || data || [];
            
            // Extract pagination metadata from data.meta
            const meta = data.meta || {};
            const total = meta.total || data.total || state.categories.length;
            const currentPage = meta.page || data.current_page || state.page;
            const totalPages = meta.total_pages || data.total_pages || Math.ceil(total / state.perPage);

            renderTable(state.categories);
            renderPagination(currentPage, totalPages, total);
            updateResultsCount(total);

        } catch (error) {
            console.error('[JobCategories] Load error:', error);
            el.tableBody.innerHTML = `
                <tr>
                    <td colspan="8" class="error-state">
                        <i class="fas fa-exclamation-triangle" style="font-size:2rem;color:#ef4444;"></i>
                        <p>${t('job_categories.error')}</p>
                        <button class="btn btn-primary" onclick="window.JobCategoriesApp.loadCategories()">
                            ${t('job_categories.retry')}
                        </button>
                    </td>
                </tr>
            `;
        }
    }

    // ----------------------------
    // RENDER TABLE
    // ----------------------------
    function renderTable(categories) {
        if (!el.tableBody) return;

        if (!categories || categories.length === 0) {
            el.tableBody.innerHTML = `
                <tr>
                    <td colspan="8" class="empty-state">
                        <i class="fas fa-inbox" style="font-size:2rem;color:#64748b;"></i>
                        <p>${t('job_categories.no_data')}</p>
                    </td>
                </tr>
            `;
            return;
        }

        el.tableBody.innerHTML = categories.map(cat => {
            const parentName = cat.parent_id ? (state.parents.find(p => p.id === cat.parent_id)?.name || '-') : '-';
            const statusClass = cat.is_active ? 'badge-active' : 'badge-inactive';
            const statusText = cat.is_active ? t('form.fields.is_active.active') : t('form.fields.is_active.inactive');
            
            const imageHtml = cat.image_url 
                ? `<img src="${esc(cat.image_url)}" alt="${esc(cat.name || '')}" style="width:40px;height:40px;object-fit:cover;border-radius:4px;">` 
                : '<i class="fas fa-image" style="font-size:1.5rem;color:#64748b;"></i>';

            return `
                <tr>
                    <td>${cat.id}</td>
                    <td>${imageHtml}</td>
                    <td>${esc(cat.name || '-')}</td>
                    <td>${esc(cat.slug || '-')}</td>
                    <td>${esc(parentName)}</td>
                    <td>${cat.sort_order || 0}</td>
                    <td><span class="badge ${statusClass}">${statusText}</span></td>
                    <td>
                        <div class="table-actions">
                            <button class="btn btn-sm btn-outline" onclick="window.JobCategoriesApp.editCategory(${cat.id})" title="${t('table.actions.edit')}">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-sm btn-danger" onclick="window.JobCategoriesApp.deleteCategory(${cat.id})" title="${t('table.actions.delete')}">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            `;
        }).join('');
    }

    // ----------------------------
    // RENDER PAGINATION
    // ----------------------------
    function renderPagination(currentPage, totalPages, total) {
        if (!el.paginationWrapper) return;

        if (totalPages <= 1) {
            el.paginationWrapper.style.display = 'none';
            return;
        }

        el.paginationWrapper.style.display = 'flex';

        const start = ((currentPage - 1) * state.perPage) + 1;
        const end = Math.min(currentPage * state.perPage, total);
        
        if (el.paginationRange) el.paginationRange.textContent = `${start}-${end}`;
        if (el.paginationTotal) el.paginationTotal.textContent = total;

        if (!el.paginationButtons) return;

        let buttons = '';
        
        if (currentPage > 1) {
            buttons += `<button onclick="window.JobCategoriesApp.goToPage(${currentPage - 1})">&laquo;</button>`;
        }

        for (let i = 1; i <= totalPages; i++) {
            if (i === currentPage) {
                buttons += `<button class="active">${i}</button>`;
            } else if (i === 1 || i === totalPages || Math.abs(i - currentPage) <= 2) {
                buttons += `<button onclick="window.JobCategoriesApp.goToPage(${i})">${i}</button>`;
            } else if (i === currentPage - 3 || i === currentPage + 3) {
                buttons += `<button disabled>...</button>`;
            }
        }

        if (currentPage < totalPages) {
            buttons += `<button onclick="window.JobCategoriesApp.goToPage(${currentPage + 1})">&raquo;</button>`;
        }

        el.paginationButtons.innerHTML = buttons;
    }

    function updateResultsCount(total) {
        if (!el.resultsCount) return;
        if (total > 0) {
            el.resultsCount.style.display = 'block';
            el.resultsCount.querySelector('span').textContent = tReplace('results.count', { count: total });
        } else {
            el.resultsCount.style.display = 'none';
        }
    }

    function goToPage(page) {
        state.page = page;
        loadCategories();
    }

    // ----------------------------
    // SHOW FORM
    // ----------------------------
    function showForm() {
        if (el.formContainer) el.formContainer.style.display = 'block';
        if (el.listContainer) el.listContainer.style.display = 'none';
        document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach(tab => tab.classList.remove('active'));
        const basicBtn = document.querySelector('.tab-btn[data-tab="basic"]');
        const basicTab = document.getElementById('tab-basic');
        if (basicBtn) basicBtn.classList.add('active');
        if (basicTab) basicTab.classList.add('active');
    }

    function hideForm() {
        if (el.formContainer) el.formContainer.style.display = 'none';
        if (el.listContainer) el.listContainer.style.display = 'block';
        resetForm();
    }

    function resetForm() {
        if (el.form) el.form.reset();
        if (el.formId) el.formId.value = '';
        if (el.formTitle) el.formTitle.textContent = t('form.add_title');
        if (el.categoryImageUrl) el.categoryImageUrl.value = '';
        if (el.categoryIconUrl) el.categoryIconUrl.value = '';
        if (el.categoryImagePreview) el.categoryImagePreview.innerHTML = '';
        if (el.categoryIconPreview) el.categoryIconPreview.innerHTML = '';
        if (el.translations) el.translations.innerHTML = '';
        deletedTranslations = [];
    }

    // ----------------------------
    // ADD/EDIT CATEGORY
    // ----------------------------
    async function editCategory(id) {
        try {
            // Load category with translations
            const res = await AF.get(`${API}/${id}?with_translations=1&tenant_id=${state.tenantId || 1}&lang=${state.language}`);
            if (!res.success || !res.data) {
                throw new Error('Failed to load category');
            }

            const cat = res.data;
            showForm();

            if (el.formId) el.formId.value = cat.id;
            if (el.formTitle) el.formTitle.textContent = t('form.edit_title');
            if (el.categoryTenantId) el.categoryTenantId.value = cat.tenant_id || state.tenantId || 1;
            if (el.categoryParent) el.categoryParent.value = cat.parent_id || '';
            if (el.categorySlug) el.categorySlug.value = cat.slug || '';
            if (el.categorySortOrder) el.categorySortOrder.value = cat.sort_order || 0;
            if (el.categoryIsActive) el.categoryIsActive.value = cat.is_active ? '1' : '0';
            if (el.categoryImageUrl) el.categoryImageUrl.value = cat.image_url || '';
            if (el.categoryIconUrl) el.categoryIconUrl.value = cat.icon_url || '';

            if (cat.image_url && el.categoryImagePreview) {
                el.categoryImagePreview.innerHTML = `<img src="${esc(cat.image_url)}" alt="Category Image" style="max-width:200px;border-radius:8px;">`;
            }
            if (cat.icon_url && el.categoryIconPreview) {
                el.categoryIconPreview.innerHTML = `<img src="${esc(cat.icon_url)}" alt="Category Icon" style="max-width:100px;border-radius:8px;">`;
            }

            // Load translations
            if (cat.translations && Array.isArray(cat.translations)) {
                cat.translations.forEach(tr => {
                    createTranslationPanel(tr.language_code, tr);
                });
            }

            await loadParentCategories();

        } catch (error) {
            console.error('[JobCategories] Edit error:', error);
            AF.error(error.message || 'Failed to load category');
        }
    }

    // ----------------------------
    // SAVE CATEGORY
    // ----------------------------
    async function saveCategory(formData) {
    const id = el.formId?.value;
    const method = id ? 'PUT' : 'POST';
    const url = id ? `${API}/${id}` : API;

    try {
        // Collect translations as array
        const translations = [];
        document.querySelectorAll('.translation-panel').forEach(panel => {
            const lang = panel.dataset.lang;
            const nameInput = panel.querySelector(`[name="translations[${lang}][name]"]`);
            const descInput = panel.querySelector(`[name="translations[${lang}][description]"]`);
            
            if (nameInput && nameInput.value.trim()) {
                translations.push({
                    language_code: lang,
                    name: nameInput.value.trim(),
                    description: descInput ? descInput.value.trim() : ''
                });
            }
        });

        // Build payload as plain object (will be sent as JSON)
        const payload = {
            tenant_id: el.categoryTenantId?.value || null,
            parent_id: el.categoryParent?.value || null,
            slug: el.categorySlug?.value || null,
            sort_order: parseInt(el.categorySortOrder?.value) || 0,
            is_active: el.categoryIsActive?.value === '1' ? 1 : 0,
            image_url: el.categoryImageUrl?.value || null,
            icon_url: el.categoryIconUrl?.value || null,
            translations: translations,  // Array of objects
            deleted_translations: deletedTranslations
        };

        console.log('[JobCategories] Payload:', payload);

        // Send as JSON instead of FormData
        const res = await fetch(url, {
            method: method,
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin',
            body: JSON.stringify(payload)
        });

        const result = await res.json();
        
        if (result.success) {
            AF.success(id ? t('messages.category_updated') : t('messages.category_created'));
            hideForm();
            loadCategories();
            loadParentCategories();
        } else {
            throw new Error(result.message || 'Failed to save category');
        }

    } catch (error) {
        console.error('[JobCategories] Save error:', error);
        AF.error(error.message || 'Failed to save category');
    }
}
    // ----------------------------
    // DELETE CATEGORY
    // ----------------------------
    async function deleteCategory(id) {
        if (!confirm(t('table.actions.confirm_delete'))) return;

        try {
            const res = await AF.delete(`${API}/${id}`);
            if (res.success) {
                AF.success(t('messages.category_deleted'));
                loadCategories();
                loadParentCategories();
            } else {
                throw new Error(res.message || 'Failed to delete category');
            }
        } catch (error) {
            console.error('[JobCategories] Delete error:', error);
            AF.error(error.message || t('messages.delete_failed'));
        }
    }

    // ----------------------------
    // MEDIA STUDIO INTEGRATION
    // ----------------------------
    let _currentImageType = null;
    
    function openMediaStudio(targetField) {
        _currentImageType = targetField;
        
        if (el.mediaModal && el.mediaFrame) {
            el.mediaModal.style.display = 'flex';
            // Use actual category ID if editing, or generate a temporary ID for new categories
            const categoryId = el.formId?.value || `temp_${Date.now()}`;
            el.mediaFrame.src = `/admin/fragments/media_studio.php?embedded=1&tenant_id=${state.tenantId || 1}&lang=${state.language}&owner_id=${categoryId}&image_type_id=${IMAGE_TYPE_ID}`;
            el.mediaFrame.dataset.targetField = targetField;
        }
    }
    
    function closeMediaStudio() {
        if (el.mediaModal) {
            el.mediaModal.style.display = 'none';
            _currentImageType = null;
        }
    }
    
    function handleMediaMessage(event) {
        if (!event.data || typeof event.data !== 'object') return;
        
        if (event.data.type === 'media-selected' || event.data.type === 'image-selected') {
            const imageUrl = event.data.url || event.data.imageUrl;
            const targetField = _currentImageType;
            
            if (imageUrl && targetField) {
                if (targetField === 'image') {
                    if (el.categoryImageUrl) el.categoryImageUrl.value = imageUrl;
                    if (el.categoryImagePreview) {
                        el.categoryImagePreview.innerHTML = `<img src="${esc(imageUrl)}" alt="Category Image" style="max-width:200px;border-radius:8px;">`;
                    }
                } else if (targetField === 'icon') {
                    if (el.categoryIconUrl) el.categoryIconUrl.value = imageUrl;
                    if (el.categoryIconPreview) {
                        el.categoryIconPreview.innerHTML = `<img src="${esc(imageUrl)}" alt="Category Icon" style="max-width:100px;border-radius:8px;">`;
                    }
                }
                closeMediaStudio();
            }
        }
    }

    function esc(str) {
        if (!str) return '';
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    // ----------------------------
    // APPLY FILTERS
    // ----------------------------
    function applyFilters() {
        state.filters = {};
        state.page = 1;

        if (el.filterSearch?.value) state.filters.search = el.filterSearch.value;
        if (el.filterParent?.value) state.filters.parent_id = el.filterParent.value;
        if (el.filterStatus?.value !== '') state.filters.is_active = el.filterStatus.value;

        loadCategories();
    }

    // ----------------------------
    // TAB SWITCHING
    // ----------------------------
    function setupTabs() {
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const tabName = btn.dataset.tab;
                document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
                document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
                btn.classList.add('active');
                const tab = document.getElementById(`tab-${tabName}`);
                if (tab) tab.classList.add('active');
            });
        });
    }

    // ----------------------------
    // INIT
    // ----------------------------
    async function init() {
        console.log('[JobCategories] Initializing...');

        // Cache elements
        el = {
            form: document.getElementById('jobCategoryForm'),
            formContainer: document.getElementById('jobCategoryFormContainer'),
            listContainer: document.getElementById('jobCategoryListContainer'),
            formId: document.getElementById('formId'),
            formTitle: document.getElementById('formTitle'),
            categoryTenantId: document.getElementById('categoryTenantId'),
            categoryParent: document.getElementById('categoryParent'),
            categorySlug: document.getElementById('categorySlug'),
            categorySortOrder: document.getElementById('categorySortOrder'),
            categoryIsActive: document.getElementById('categoryIsActive'),
            categoryImageUrl: document.getElementById('categoryImageUrl'),
            categoryIconUrl: document.getElementById('categoryIconUrl'),
            categoryImagePreview: document.getElementById('categoryImagePreview'),
            categoryIconPreview: document.getElementById('categoryIconPreview'),
            langSelect: document.getElementById('langSelect'),
            translations: document.getElementById('translationsContainer'),
            imageTypeSelect: document.getElementById('imageTypeSelect'),
            imageTypeDesc: document.getElementById('imageTypeDesc'),
            tableBody: document.getElementById('catTableBody'),
            filterSearch: document.getElementById('filterSearch'),
            filterParent: document.getElementById('filterParent'),
            filterStatus: document.getElementById('filterStatus'),
            paginationWrapper: document.getElementById('paginationWrapper'),
            paginationRange: document.getElementById('paginationRange'),
            paginationTotal: document.getElementById('paginationTotal'),
            paginationButtons: document.getElementById('paginationButtons'),
            resultsCount: document.getElementById('resultsCount'),
            mediaModal: document.getElementById('mediaStudioModal'),
            mediaFrame: document.getElementById('mediaStudioFrame'),
            mediaClose: document.getElementById('mediaStudioClose')
        };

        // Get tenant_id from window config or meta tag
        const metaTag = document.querySelector('meta[data-page="job_categories"]');
        state.tenantId = (window.APP_CONFIG && window.APP_CONFIG.TENANT_ID) || 
                        (metaTag && metaTag.dataset.tenantId) || 1;

        // Load translations and set direction
        await loadTranslations(state.language);
        setDirectionForLang(state.language);

        // Load data
        await Promise.all([
            loadLanguages(),
            loadImageTypes(),
            loadParentCategories(),
            loadCategories()
        ]);

        // Setup tabs
        setupTabs();

        // Event listeners
        document.getElementById('btnAddJobCategory')?.addEventListener('click', () => {
            resetForm();
            showForm();
        });

        document.getElementById('btnCloseForm')?.addEventListener('click', hideForm);
        document.getElementById('btnCancel')?.addEventListener('click', hideForm);

        el.form?.addEventListener('submit', (e) => {
            e.preventDefault();
            saveCategory(new FormData(el.form));
        });

        document.getElementById('btnAddTranslation')?.addEventListener('click', () => {
            const lang = el.langSelect?.value;
            if (!lang) {
                AF.warning(t('form.translations.choose_lang'));
                return;
            }
            createTranslationPanel(lang);
            el.langSelect.value = '';
        });

        document.getElementById('btnSelectCategoryImage')?.addEventListener('click', () => {
            openMediaStudio('image');
        });

        document.getElementById('btnSelectCategoryIcon')?.addEventListener('click', () => {
            openMediaStudio('icon');
        });

        document.getElementById('btnApplyFilters')?.addEventListener('click', applyFilters);

        el.filterSearch?.addEventListener('keyup', (e) => {
            if (e.key === 'Enter') applyFilters();
        });
        
        // Media studio close button
        if (el.mediaClose) el.mediaClose.onclick = closeMediaStudio;
        
        // Media studio message listener
        window.addEventListener('message', handleMediaMessage);

        console.log('[JobCategories] Initialized successfully');
    }

    // ----------------------------
    // PUBLIC API
    // ----------------------------
    window.JobCategoriesApp = {
        init,
        loadCategories,
        editCategory,
        deleteCategory,
        goToPage
    };

    // Auto-init
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();