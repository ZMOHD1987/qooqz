(function(){
    'use strict';

    const CONFIG = window.TENANT_CATEGORIES_CONFIG || {};
    const API = CONFIG.apiUrl || '/api/categories-tenants';
    const TENANTS_API = CONFIG.tenantsUrl || '/api/tenants';
    const CATEGORIES_API = CONFIG.categoriesUrl || '/api/categories';
    const TRANSLATIONS_URL = CONFIG.translationsUrl || '/languages/Tenant_categories/en.json';

    const state = {
        page: 1,
        perPage: 25,
        filters: {},
        items: [],
        tenants: [],
        categories: [],
        permissions: CONFIG.permissions || {},
        isSuperAdmin: CONFIG.isSuperAdmin || false
    };

    let el = {};
    let translations = {};

    // تحميل الترجمات
    async function loadTranslations() {
        try {
            const response = await fetch(TRANSLATIONS_URL);
            if (response.ok) {
                translations = await response.json();
            } else {
                console.warn('[TenantCategories] Translations file not found, using defaults');
            }
        } catch (error) {
            console.error('[TenantCategories] Error loading translations:', error);
        }
    }

    // دالة الترجمة
    function t(key, placeholders = {}) {
        let text = translations[key] || key;
        Object.keys(placeholders).forEach(p => {
            text = text.replace(new RegExp(`{${p}}`, 'g'), placeholders[p]);
        });
        return text;
    }

    // تحديث النصوص بالترجمة
    function applyTranslations() {
        const container = document.getElementById('tenantCategoriesPage');
        if (!container) return;

        container.querySelectorAll('[data-i18n]').forEach(elem => {
            const key = elem.getAttribute('data-i18n');
            if (key.includes('_placeholder')) {
                elem.setAttribute('placeholder', t(key));
            } else {
                elem.textContent = t(key);
            }
        });

        // Update placeholder-only attributes separately
        container.querySelectorAll('[data-i18n-placeholder]').forEach(elem => {
            const key = elem.getAttribute('data-i18n-placeholder');
            elem.setAttribute('placeholder', t(key));
        });
    }

    // دالة الإشعارات
    function showNotification(message, type = 'success') {
        if (!el.notificationsContainer) return;

        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.textContent = message;

        el.notificationsContainer.appendChild(notification);

        // إزالة تلقائياً بعد 5 ثوان
        setTimeout(() => {
            notification.classList.add('fade-out');
            setTimeout(() => notification.remove(), 300);
        }, 5000);

        // إزالة عند النقر
        notification.onclick = () => {
            notification.classList.add('fade-out');
            setTimeout(() => notification.remove(), 300);
        };
    }

    // تحميل التنانتس والكاتيجوريز
    async function loadDropdowns() {
        try {
            const fetchOptions = { credentials: 'same-origin' };

            // بناء URL الفئات: تجاهل فلتر tenant_categories، وإظهار القوائم الرئيسية فقط
            // لا نُمرر tenant_id حتى تظهر كافة الفئات المتاحة في النظام للاختيار منها
            const catParams = new URLSearchParams({
                format: 'json',
                limit: 1000,
                lang: CONFIG.lang || 'ar',
                skip_tc_filter: 1,
                parent_id: 0
            });

            const promises = [
                fetch(`${CATEGORIES_API}?${catParams}`, fetchOptions)
            ];
            if (state.isSuperAdmin) {
                promises.push(fetch(`${TENANTS_API}?format=json&limit=1000`, fetchOptions));
            }

            const results = await Promise.all(promises);
            const [categoriesRes, tenantsRes] = results;

            if (categoriesRes && categoriesRes.ok) {
                const categoriesData = await categoriesRes.json();
                if (categoriesData.success && categoriesData.data) {
                    const items = categoriesData.data.items || categoriesData.data;
                    if (Array.isArray(items)) {
                        state.categories = items;
                        populateSelect('tenantCategoryCategoryId', state.categories);
                        populateDatalist('categoriesList', state.categories, 'id', 'name');
                        populateDatalist('filterCategoriesList', state.categories, 'id', 'name');
                    }
                }
            }

            if (tenantsRes && tenantsRes.ok) {
                const tenantsData = await tenantsRes.json();
                if (tenantsData.success && tenantsData.data) {
                    const items = tenantsData.data.items || tenantsData.data;
                    if (Array.isArray(items)) {
                        state.tenants = items;
                        populateDatalist('tenantsList', state.tenants, 'id', 'name');
                        populateDatalist('filterTenantsList', state.tenants, 'id', 'name');
                    }
                }
            }
        } catch (error) {
            console.error('[TenantCategories] Load dropdowns error:', error);
        }
    }

    // تعبئة قائمة select
    function populateSelect(selectId, data) {
        const select = document.getElementById(selectId);
        if (!select || !Array.isArray(data)) return;
        const currentVal = select.value;
        // Keep placeholder option
        const placeholder = select.querySelector('option[value=""]');
        select.innerHTML = '';
        if (placeholder) select.appendChild(placeholder);
        data.forEach(item => {
            const option = document.createElement('option');
            option.value = item.id;
            option.textContent = `${item.name || item.id} (#${item.id})`;
            select.appendChild(option);
        });
        if (currentVal) select.value = currentVal;
    }

    // تعبئة datalist
    function populateDatalist(datalistId, data, valueKey, textKey) {
        const datalist = document.getElementById(datalistId);
        if (!datalist || !Array.isArray(data)) return;
        datalist.innerHTML = '';
        data.forEach(item => {
            const option = document.createElement('option');
            option.value = item[textKey] || item[valueKey];
            option.setAttribute('data-id', item[valueKey]);
            datalist.appendChild(option);
        });
    }

    // الحصول على ID من datalist (بالاسم أو الرقم)
    function getIdFromDatalist(datalistId, displayValue) {
        const datalist = document.getElementById(datalistId);
        if (!datalist) return null;
        const trimmed = (displayValue || '').trim();
        if (!trimmed) return null;

        // بحث بالاسم أولاً
        const options = datalist.querySelectorAll('option');
        for (let option of options) {
            if (option.value === trimmed) {
                return option.getAttribute('data-id');
            }
        }

        // إذا كانت قيمة رقمية، تحقق من data-id مباشرة
        if (/^\d+$/.test(trimmed)) {
            for (let option of options) {
                if (option.getAttribute('data-id') === trimmed) {
                    return trimmed;
                }
            }
        }

        return null;
    }

    function setDisplayFromId(hiddenId, displayId, datalistId, idValue) {
        const datalist = document.getElementById(datalistId);
        if (!datalist || !idValue) return;
        const options = datalist.querySelectorAll('option');
        for (let option of options) {
            if (option.getAttribute('data-id') === idValue.toString()) {
                const displayEl = document.getElementById(displayId);
                const hiddenEl = document.getElementById(hiddenId);
                if (displayEl) displayEl.value = option.value;
                if (hiddenEl) hiddenEl.value = idValue;
                return;
            }
        }
    }

    // تحميل البيانات
    async function loadData(page = 1) {
        try {
            showLoading();

            state.page = page;
            const params = new URLSearchParams({
                page: page,
                limit: state.perPage,
                format: 'json'
            });

            // إضافة الفلاتر
            Object.keys(state.filters).forEach(key => {
                if (state.filters[key] !== '' && state.filters[key] !== null && state.filters[key] !== undefined) {
                    params.set(key, state.filters[key]);
                }
            });

            // فلتر المستأجر للغير سوبر أدمن
            if (!state.isSuperAdmin && CONFIG.tenantId) {
                params.set('tenant_id', CONFIG.tenantId);
            }

            const response = await fetch(`${API}?${params}`, {
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json' }
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }

            const result = await response.json();

            if (result.success && result.data) {
                state.items = Array.isArray(result.data) ? result.data : (result.data.items || []);
                const total = Array.isArray(result.data) ? result.data.length : (result.data.meta?.total || state.items.length);
                const meta = {
                    total: total,
                    page: page,
                    per_page: state.perPage,
                    last_page: Math.ceil(total / state.perPage) || 1
                };
                renderTable();
                updatePagination(meta);
                updateResultsCount(meta.total);
                if (state.items.length > 0) {
                    showTable();
                } else {
                    showEmpty();
                }
            } else {
                showEmpty();
            }
        } catch (error) {
            console.error('[TenantCategories] Load error:', error);
            showError(t('error_loading'));
        }
    }

    // عرض الجدول
    function renderTable() {
        if (!el.tableBody || state.items.length === 0) {
            showEmpty();
            return;
        }

        let html = '';
        state.items.forEach(item => {
            const statusText = item.is_active ? t('toggle_active') : t('toggle_inactive');
            const createdDate = item.created_at ? new Date(item.created_at).toLocaleDateString() : '-';

            html += `
                <tr>
                    <td>${item.id}</td>
                    ${state.isSuperAdmin ? `<td>${item.tenant_id}</td>` : ''}
                    <td><strong>${escapeHtml(item.tenant_name || '-')}</strong></td>
                    <td>${item.category_id}</td>
                    <td><strong>${escapeHtml(item.category_name || '-')}</strong></td>
                    <td>${item.sort_order ?? 0}</td>
                    ${state.isSuperAdmin ? `<td>
                        <button class="btn btn-sm ${item.is_active ? 'btn-success' : 'btn-danger'}"
                                onclick="TenantCategories.toggleStatus(${item.id}, ${item.is_active ? 0 : 1})">
                            ${statusText}
                        </button>
                    </td>` : ''}
                    <td>${createdDate}</td>
                    <td>
                        <div class="table-actions">
                            ${state.permissions.canEdit ? `
                                <button class="btn btn-sm btn-outline" onclick="TenantCategories.edit(${item.id})" title="${t('edit_button')}">
                                    <i class="fas fa-edit"></i>
                                </button>
                            ` : ''}
                            ${state.permissions.canDelete ? `
                                <button class="btn btn-sm btn-danger" onclick="TenantCategories.remove(${item.id})" title="${t('delete_button')}">
                                    <i class="fas fa-trash"></i>
                                </button>
                            ` : ''}
                        </div>
                    </td>
                </tr>
            `;
        });

        el.tableBody.innerHTML = html;
    }

    // عرض النموذج
    function showForm(isEdit = false, data = null) {
        if (!el.formContainer) return;

        el.formContainer.style.display = 'block';
        if (el.form) el.form.reset();
        if (el.formId) el.formId.value = '';

        if (el.formTitle) {
            el.formTitle.textContent = isEdit ? t('form_edit_title') : t('form_add_title');
        }

        if (isEdit && data) {
            if (el.formId) el.formId.value = data.id;
            if (state.isSuperAdmin && el.tenantDisplay) {
                setDisplayFromId('tenantCategoryTenantIdHidden', 'tenantCategoryTenantId', 'tenantsList', data.tenant_id);
            }
            // Set category in select or datalist
            if (el.categorySelect) {
                el.categorySelect.value = data.category_id;
            } else if (el.categoryHidden) {
                el.categoryHidden.value = data.category_id;
                setDisplayFromId('tenantCategoryCategoryIdHidden', 'tenantCategoryCategoryId', 'categoriesList', data.category_id);
            }
            if (el.sortOrder) el.sortOrder.value = data.sort_order ?? 0;
            if (state.isSuperAdmin && el.isActive) el.isActive.value = data.is_active ?? 1;
            if (el.btnDelete) el.btnDelete.style.display = 'inline-block';
        } else {
            if (el.btnDelete) el.btnDelete.style.display = 'none';
        }

        el.formContainer.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    // حفظ البيانات
    async function saveData(e) {
        if (e) e.preventDefault();

        const id = el.formId ? el.formId.value.trim() : '';
        const isEdit = !!id;

        // تحديد tenant_id
        let tenantId = CONFIG.tenantId;
        if (state.isSuperAdmin && el.tenantDisplay) {
            const tenantDisplay = el.tenantDisplay.value.trim();
            tenantId = getIdFromDatalist('tenantsList', tenantDisplay);
            if (!tenantId) {
                showNotification(t('validation_tenant'), 'error');
                return;
            }
        }

        // تحديد category_id من select أو datalist
        let categoryId = null;
        if (el.categorySelect) {
            categoryId = el.categorySelect.value;
        } else if (el.categoryDisplay) {
            const categoryDisplay = el.categoryDisplay.value.trim();
            categoryId = getIdFromDatalist('categoriesList', categoryDisplay);
            // السماح بإدخال رقم مباشر
            if (!categoryId && /^\d+$/.test(categoryDisplay)) {
                categoryId = categoryDisplay;
            }
        }

        if (!categoryId) {
            showNotification(t('validation_category'), 'error');
            return;
        }

        const data = {
            tenant_id: parseInt(tenantId),
            category_id: parseInt(categoryId),
            sort_order: el.sortOrder ? (parseInt(el.sortOrder.value) || 0) : 0,
            is_active: (state.isSuperAdmin && el.isActive) ? (parseInt(el.isActive.value) || 1) : 1
        };

        if (isEdit) {
            data.id = parseInt(id);
        }

        try {
            if (el.btnSave) {
                el.btnSave.innerHTML = '<i class="fas fa-spinner fa-spin"></i> ' + t('save_button');
                el.btnSave.disabled = true;
            }

            const url = isEdit ? `${API}/${data.id}` : API;
            const method = isEdit ? 'PUT' : 'POST';

            const response = await fetch(url, {
                method: method,
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': CONFIG.csrfToken || ''
                },
                body: JSON.stringify(data)
            });

            const result = await response.json();

            if (el.btnSave) {
                el.btnSave.innerHTML = '<i class="fas fa-save"></i> ' + t('save_button');
                el.btnSave.disabled = false;
            }

            if (result.success) {
                showNotification(isEdit ? t('alert_updated') : t('alert_added'), 'success');
                hideForm();
                loadData(state.page);
            } else {
                showNotification(result.message || t('alert_error'), 'error');
            }
        } catch (error) {
            console.error('[TenantCategories] Save error:', error);
            if (el.btnSave) {
                el.btnSave.innerHTML = '<i class="fas fa-save"></i> ' + t('save_button');
                el.btnSave.disabled = false;
            }
            showNotification(t('alert_error'), 'error');
        }
    }

    // حذف بيانات
    async function deleteData(id) {
        if (!confirm(t('confirm_delete'))) return;

        try {
            const response = await fetch(`${API}/${id}`, {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': CONFIG.csrfToken || ''
                },
                body: JSON.stringify({ id: id })
            });

            const result = await response.json();

            if (result.success) {
                showNotification(t('alert_deleted'), 'success');
                loadData(state.page);
            } else {
                showNotification(result.message || t('alert_error'), 'error');
            }
        } catch (error) {
            console.error('[TenantCategories] Delete error:', error);
            showNotification(t('alert_error'), 'error');
        }
    }

    // تبديل الحالة
    async function toggleStatus(id, newStatus) {
        try {
            const response = await fetch(`${API}/${id}`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': CONFIG.csrfToken || ''
                },
                body: JSON.stringify({ is_active: newStatus })
            });

            const result = await response.json();

            if (result.success) {
                showNotification(t('alert_updated'), 'success');
                loadData(state.page);
            } else {
                showNotification(result.message || t('alert_error'), 'error');
            }
        } catch (error) {
            console.error('[TenantCategories] Toggle status error:', error);
            showNotification(t('alert_error'), 'error');
        }
    }

    // تطبيق الفلاتر
    function applyFilters() {
        state.filters = {};

        if (state.isSuperAdmin && el.filterTenantHidden && el.filterTenantHidden.value) {
            state.filters.tenant_id = el.filterTenantHidden.value;
        }

        if (el.filterCategoryHidden && el.filterCategoryHidden.value) {
            state.filters.category_id = el.filterCategoryHidden.value;
        }

        if (state.isSuperAdmin && el.filterStatus && el.filterStatus.value !== '') {
            state.filters.is_active = el.filterStatus.value;
        }

        loadData(1);
    }

    // إعادة تعيين الفلاتر
    function resetFilters() {
        if (state.isSuperAdmin && el.filterTenant) el.filterTenant.value = '';
        if (state.isSuperAdmin && el.filterTenantHidden) el.filterTenantHidden.value = '';
        if (el.filterCategory) el.filterCategory.value = '';
        if (el.filterCategoryHidden) el.filterCategoryHidden.value = '';
        if (state.isSuperAdmin && el.filterStatus) el.filterStatus.value = '';

        state.filters = {};
        loadData(1);
    }

    // مساعدات العرض
    function showLoading() {
        if (el.tableLoading) el.tableLoading.style.display = 'block';
        if (el.tableContainer) el.tableContainer.style.display = 'none';
        if (el.emptyState) el.emptyState.style.display = 'none';
        if (el.errorState) el.errorState.style.display = 'none';
    }

    function showTable() {
        if (el.tableLoading) el.tableLoading.style.display = 'none';
        if (el.tableContainer) el.tableContainer.style.display = 'block';
        if (el.emptyState) el.emptyState.style.display = 'none';
        if (el.errorState) el.errorState.style.display = 'none';
    }

    function showEmpty() {
        if (el.tableLoading) el.tableLoading.style.display = 'none';
        if (el.tableContainer) el.tableContainer.style.display = 'none';
        if (el.emptyState) el.emptyState.style.display = 'block';
        if (el.errorState) el.errorState.style.display = 'none';
        if (el.tableBody) el.tableBody.innerHTML = '';
        updateResultsCount(0);
    }

    function showError(message) {
        if (el.tableLoading) el.tableLoading.style.display = 'none';
        if (el.tableContainer) el.tableContainer.style.display = 'none';
        if (el.emptyState) el.emptyState.style.display = 'none';
        if (el.errorState) {
            el.errorState.style.display = 'block';
            if (el.errorMessage) {
                el.errorMessage.textContent = message || t('error_loading');
            }
        }
    }

    function hideForm() {
        if (el.formContainer) el.formContainer.style.display = 'none';
        if (el.form) el.form.reset();
    }

    function updatePagination(meta) {
        if (!el.paginationInfo || !el.btnPrev || !el.btnNext) return;

        const currentPage = meta.page || 1;
        const perPage = meta.per_page || state.perPage;
        const total = meta.total || 0;
        const totalPages = Math.ceil(total / perPage) || 1;

        const from = total > 0 ? ((currentPage - 1) * perPage) + 1 : 0;
        const to = Math.min(currentPage * perPage, total);

        el.paginationInfo.textContent = t('showing_results', { from, to, total });

        el.btnPrev.disabled = currentPage <= 1;
        el.btnNext.disabled = currentPage >= totalPages;

        el.btnPrev.onclick = () => loadData(currentPage - 1);
        el.btnNext.onclick = () => loadData(currentPage + 1);

        if (el.paginationWrapper) {
            el.paginationWrapper.style.display = total > 0 ? 'flex' : 'none';
        }
    }

    function updateResultsCount(total) {
        if (!el.resultsCount || !el.resultsCountText) return;

        el.resultsCountText.textContent = total > 0
            ? `${total} ${t('results_found')}`
            : t('no_records');
        el.resultsCount.style.display = 'block';
    }

    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // التهيئة
    function init() {
        el = {
            formContainer: document.getElementById('tenantCategoryFormContainer'),
            form: document.getElementById('tenantCategoryForm'),
            formTitle: document.getElementById('formTitle'),
            formId: document.getElementById('tenantCategoryId'),
            tenantDisplay: document.getElementById('tenantCategoryTenantId'),
            tenantHidden: document.getElementById('tenantCategoryTenantIdHidden'),
            categorySelect: document.getElementById('tenantCategoryCategoryId'),
            categoryDisplay: document.getElementById('tenantCategoryCategoryIdText'),
            categoryHidden: document.getElementById('tenantCategoryCategoryIdHidden'),
            sortOrder: document.getElementById('tenantCategorySortOrder'),
            isActive: document.getElementById('tenantCategoryIsActive'),
            btnSave: document.getElementById('btnSaveTenantCategory'),
            btnCancel: document.getElementById('btnCancelTenantCategoryForm'),
            btnDelete: document.getElementById('btnDeleteTenantCategory'),
            btnClose: document.getElementById('btnCloseTenantCategoryForm'),

            tableBody: document.getElementById('tenantCategoryTableBody'),
            tableLoading: document.getElementById('tenantCategoryTableLoading'),
            tableContainer: document.getElementById('tenantCategoryTableContainer'),
            emptyState: document.getElementById('tenantCategoryEmptyState'),
            errorState: document.getElementById('tenantCategoryErrorState'),
            errorMessage: document.getElementById('tenantCategoryErrorMessage'),

            filterTenant: document.getElementById('tenantCategoryFilterTenant'),
            filterTenantHidden: document.getElementById('tenantCategoryFilterTenantHidden'),
            filterCategory: document.getElementById('tenantCategoryFilterCategory'),
            filterCategoryHidden: document.getElementById('tenantCategoryFilterCategoryHidden'),
            filterStatus: document.getElementById('tenantCategoryFilterStatus'),
            btnApply: document.getElementById('btnApplyTenantCategoryFilters'),
            btnReset: document.getElementById('btnResetTenantCategoryFilters'),

            resultsCount: document.getElementById('tenantCategoryResultsCount'),
            resultsCountText: document.getElementById('tenantCategoryResultsCountText'),
            paginationInfo: document.getElementById('tenantCategoryPaginationInfo'),
            btnPrev: document.getElementById('btnPrevTenantCategoryPage'),
            btnNext: document.getElementById('btnNextTenantCategoryPage'),
            paginationWrapper: document.querySelector('.pagination-wrapper'),
            btnRetry: document.getElementById('btnRetryTenantCategories'),
            btnAdd: document.getElementById('btnAddTenantCategory'),
            notificationsContainer: document.getElementById('notificationsContainer')
        };

        if (el.form) el.form.onsubmit = saveData;
        if (el.btnCancel) el.btnCancel.onclick = hideForm;
        if (el.btnClose) el.btnClose.onclick = hideForm;
        if (el.btnApply) el.btnApply.onclick = applyFilters;
        if (el.btnReset) el.btnReset.onclick = resetFilters;
        if (el.btnRetry) el.btnRetry.onclick = () => loadData(state.page);
        if (el.btnAdd) el.btnAdd.onclick = () => showForm(false);
        if (el.btnDelete) el.btnDelete.onclick = () => {
            if (el.formId && el.formId.value) {
                deleteData(parseInt(el.formId.value));
            }
        };

        if (el.tenantDisplay) {
            el.tenantDisplay.addEventListener('input', function() {
                const id = getIdFromDatalist('tenantsList', this.value);
                if (el.tenantHidden) el.tenantHidden.value = id || '';
            });
        }

        if (el.filterTenant) {
            el.filterTenant.addEventListener('input', function() {
                const id = getIdFromDatalist('filterTenantsList', this.value);
                if (el.filterTenantHidden) el.filterTenantHidden.value = id || '';
            });
        }

        if (el.filterCategory) {
            el.filterCategory.addEventListener('input', function() {
                const id = getIdFromDatalist('filterCategoriesList', this.value);
                if (el.filterCategoryHidden) el.filterCategoryHidden.value = id || '';
            });
        }

        loadTranslations().then(() => {
            applyTranslations();
            loadDropdowns().then(() => loadData());
        });
    }

    window.TenantCategories = {
        init,
        load: loadData,
        add: () => showForm(false),
        edit: async (id) => {
            try {
                const response = await fetch(`${API}/${id}?format=json`, {
                    credentials: 'same-origin'
                });
                const result = await response.json();
                if (result.success && result.data) {
                    const item = Array.isArray(result.data) ? result.data[0] : result.data;
                    if (item) showForm(true, item);
                    else showNotification(t('alert_error'), 'error');
                } else {
                    showNotification(t('alert_error'), 'error');
                }
            } catch (error) {
                console.error('[TenantCategories] Edit error:', error);
                showNotification(t('alert_error'), 'error');
            }
        },
        remove: deleteData,
        toggleStatus: toggleStatus
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        setTimeout(init, 100);
    }

    window.page = window.page || {};
    window.page.run = init;

})();