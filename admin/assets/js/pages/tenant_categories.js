(function(){
    'use strict';
    
    const CONFIG = window.TENANT_CATEGORIES_CONFIG || {};
    const AF = window.AdminFramework || {};
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
                console.warn('Translations file not found, using defaults');
            }
        } catch (error) {
            console.error('Error loading translations:', error);
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
        
        container.querySelectorAll('[data-i18n]').forEach(el => {
            const key = el.getAttribute('data-i18n');
            if (key.includes('_placeholder')) {
                el.setAttribute('placeholder', t(key));
            } else {
                el.textContent = t(key);
            }
        });
    }
    
    // دالة الإشعارات
    function showNotification(message, type = 'success') {
        console.log('Attempting to show notification:', message, type);
        if (!el.notificationsContainer) {
            console.error('Notifications container not found');
            return;
        }
        console.log('Notifications container found:', el.notificationsContainer);
        
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.textContent = message;
        
        el.notificationsContainer.appendChild(notification);
        console.log('Notification appended');
        
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
            const [tenantsRes, categoriesRes] = await Promise.all([
                fetch(`${TENANTS_API}?format=json&limit=1000`, { credentials: 'same-origin' }),
                fetch(`${CATEGORIES_API}?format=json&limit=1000&lang=${CONFIG.lang}`, { credentials: 'same-origin' })
            ]);
            
            if (tenantsRes.ok) {
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
            
            if (categoriesRes.ok) {
                const categoriesData = await categoriesRes.json();
                if (categoriesData.success && categoriesData.data) {
                    const items = categoriesData.data.items || categoriesData.data;
                    if (Array.isArray(items)) {
                        state.categories = items;
                        populateDatalist('categoriesList', state.categories, 'id', 'name');
                        populateDatalist('filterCategoriesList', state.categories, 'id', 'name');
                    }
                }
            }
        } catch (error) {
            console.error('[TenantCategories] Load dropdowns error:', error);
        }
    }
    
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
    
    function getIdFromDatalist(datalistId, displayValue) {
        const datalist = document.getElementById(datalistId);
        if (!datalist) return null;
        const options = datalist.querySelectorAll('option');
        for (let option of options) {
            if (option.value === displayValue) {
                return option.getAttribute('data-id');
            }
        }
        return null;
    }
    
    function setDisplayFromId(hiddenId, displayId, datalistId, idValue) {
        const datalist = document.getElementById(datalistId);
        if (!datalist) return;
        const options = datalist.querySelectorAll('option');
        for (let option of options) {
            if (option.getAttribute('data-id') === idValue.toString()) {
                document.getElementById(displayId).value = option.value;
                document.getElementById(hiddenId).value = idValue;
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
                format: 'json',
                ...state.filters
            });
            
            const response = await fetch(`${API}?${params}`, {
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json' }
            });
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            
            const result = await response.json();
            
            const meta = { total: (result.data ? result.data.length : 0), page: 1, per_page: state.perPage, last_page: 1 };
            
            if (result.success && result.data) {
                state.items = result.data;
                renderTable();
                updatePagination(meta);
                updateResultsCount(meta.total);
                showTable();
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
            const statusClass = item.is_active ? 'badge-success' : 'badge-danger';
            const statusText = item.is_active ? t('toggle_active') : t('toggle_inactive');
            const createdDate = new Date(item.created_at).toLocaleDateString();
            
            html += `
                <tr>
                    <td>${item.id}</td>
                    ${state.isSuperAdmin ? `<td>${item.tenant_id}</td>` : ''}
                    <td><strong>${escapeHtml(item.tenant_name || '-')}</strong></td>
                    <td>${item.category_id}</td>
                    <td><strong>${escapeHtml(item.category_name || '-')}</strong></td>
                    <td>${item.sort_order}</td>
                    ${state.isSuperAdmin ? `<td>
                        <button class="btn btn-sm ${item.is_active ? 'btn-success' : 'btn-danger'}" 
                                onclick="TenantCategories.toggleStatus(${item.id}, ${item.is_active ? 0 : 1})" 
                                style="padding: 4px 8px; font-size: 12px;">
                            ${statusText}
                        </button>
                    </td>` : ''}
                    <td>${createdDate}</td>
                    <td>
                        <div class="table-actions" style="display: flex; gap: 8px;">
                            ${state.permissions.canEdit ? `
                                <button class="btn btn-sm btn-outline" onclick="TenantCategories.edit(${item.id})" 
                                        style="padding: 4px 8px; border: 1px solid #d1d5db; background: white; color: #374151; border-radius: 4px; font-size: 12px;">
                                    <i class="fas fa-edit"></i>
                                </button>
                            ` : ''}
                            ${state.permissions.canDelete ? `
                                <button class="btn btn-sm btn-danger" onclick="TenantCategories.remove(${item.id})" 
                                        style="padding: 4px 8px; background-color: #ef4444; color: white; border: none; border-radius: 4px; font-size: 12px;">
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
        el.form.reset();
        el.formId.value = '';
        
        el.formTitle.textContent = isEdit ? t('form_edit_title') : t('form_add_title');
        
        if (isEdit && data) {
            el.formId.value = data.id;
            if (state.isSuperAdmin) {
                setDisplayFromId('tenantCategoryTenantIdHidden', 'tenantCategoryTenantId', 'tenantsList', data.tenant_id);
            }
            setDisplayFromId('tenantCategoryCategoryIdHidden', 'tenantCategoryCategoryId', 'categoriesList', data.category_id);
            el.sortOrder.value = data.sort_order || 0;
            if (state.isSuperAdmin) {
                el.isActive.value = data.is_active || 1;
            }
            el.btnDelete.style.display = 'inline-block';
        } else {
            el.btnDelete.style.display = 'none';
        }
        
        el.formContainer.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
    
    // حفظ البيانات
    (function(){
    'use strict';
    
    const CONFIG = window.TENANT_CATEGORIES_CONFIG || {};
    const AF = window.AdminFramework || {};
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
                console.warn('Translations file not found, using defaults');
            }
        } catch (error) {
            console.error('Error loading translations:', error);
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
        
        container.querySelectorAll('[data-i18n]').forEach(el => {
            const key = el.getAttribute('data-i18n');
            if (key.includes('_placeholder')) {
                el.setAttribute('placeholder', t(key));
            } else {
                el.textContent = t(key);
            }
        });
    }
    
    // دالة الإشعارات
    function showNotification(message, type = 'success') {
        console.log('Attempting to show notification:', message, type);
        if (!el.notificationsContainer) {
            console.error('Notifications container not found');
            return;
        }
        console.log('Notifications container found:', el.notificationsContainer);
        
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.textContent = message;
        
        el.notificationsContainer.appendChild(notification);
        console.log('Notification appended');
        
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
            const [tenantsRes, categoriesRes] = await Promise.all([
                fetch(`${TENANTS_API}?format=json&limit=1000`, { credentials: 'same-origin' }),
                fetch(`${CATEGORIES_API}?format=json&limit=1000&lang=${CONFIG.lang}`, { credentials: 'same-origin' })
            ]);
            
            if (tenantsRes.ok) {
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
            
            if (categoriesRes.ok) {
                const categoriesData = await categoriesRes.json();
                if (categoriesData.success && categoriesData.data) {
                    const items = categoriesData.data.items || categoriesData.data;
                    if (Array.isArray(items)) {
                        state.categories = items;
                        populateDatalist('categoriesList', state.categories, 'id', 'name');
                        populateDatalist('filterCategoriesList', state.categories, 'id', 'name');
                    }
                }
            }
        } catch (error) {
            console.error('[TenantCategories] Load dropdowns error:', error);
        }
    }
    
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
    
    function getIdFromDatalist(datalistId, displayValue) {
        const datalist = document.getElementById(datalistId);
        if (!datalist) return null;
        const options = datalist.querySelectorAll('option');
        for (let option of options) {
            if (option.value === displayValue) {
                return option.getAttribute('data-id');
            }
        }
        return null;
    }
    
    function setDisplayFromId(hiddenId, displayId, datalistId, idValue) {
        const datalist = document.getElementById(datalistId);
        if (!datalist) return;
        const options = datalist.querySelectorAll('option');
        for (let option of options) {
            if (option.getAttribute('data-id') === idValue.toString()) {
                document.getElementById(displayId).value = option.value;
                document.getElementById(hiddenId).value = idValue;
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
                format: 'json',
                ...state.filters
            });
            
            const response = await fetch(`${API}?${params}`, {
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json' }
            });
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            
            const result = await response.json();
            
            const meta = { total: (result.data ? result.data.length : 0), page: 1, per_page: state.perPage, last_page: 1 };
            
            if (result.success && result.data) {
                state.items = result.data;
                renderTable();
                updatePagination(meta);
                updateResultsCount(meta.total);
                showTable();
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
            const statusClass = item.is_active ? 'badge-success' : 'badge-danger';
            const statusText = item.is_active ? t('toggle_active') : t('toggle_inactive');
            const createdDate = new Date(item.created_at).toLocaleDateString();
            
            html += `
                <tr>
                    <td>${item.id}</td>
                    ${state.isSuperAdmin ? `<td>${item.tenant_id}</td>` : ''}
                    <td><strong>${escapeHtml(item.tenant_name || '-')}</strong></td>
                    <td>${item.category_id}</td>
                    <td><strong>${escapeHtml(item.category_name || '-')}</strong></td>
                    <td>${item.sort_order}</td>
                    ${state.isSuperAdmin ? `<td>
                        <button class="btn btn-sm ${item.is_active ? 'btn-success' : 'btn-danger'}" 
                                onclick="TenantCategories.toggleStatus(${item.id}, ${item.is_active ? 0 : 1})" 
                                style="padding: 4px 8px; font-size: 12px;">
                            ${statusText}
                        </button>
                    </td>` : ''}
                    <td>${createdDate}</td>
                    <td>
                        <div class="table-actions" style="display: flex; gap: 8px;">
                            ${state.permissions.canEdit ? `
                                <button class="btn btn-sm btn-outline" onclick="TenantCategories.edit(${item.id})" 
                                        style="padding: 4px 8px; border: 1px solid #d1d5db; background: white; color: #374151; border-radius: 4px; font-size: 12px;">
                                    <i class="fas fa-edit"></i>
                                </button>
                            ` : ''}
                            ${state.permissions.canDelete ? `
                                <button class="btn btn-sm btn-danger" onclick="TenantCategories.remove(${item.id})" 
                                        style="padding: 4px 8px; background-color: #ef4444; color: white; border: none; border-radius: 4px; font-size: 12px;">
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
        el.form.reset();
        el.formId.value = '';
        
        el.formTitle.textContent = isEdit ? t('form_edit_title') : t('form_add_title');
        
        if (isEdit && data) {
            el.formId.value = data.id;
            if (state.isSuperAdmin) {
                setDisplayFromId('tenantCategoryTenantIdHidden', 'tenantCategoryTenantId', 'tenantsList', data.tenant_id);
            }
            setDisplayFromId('tenantCategoryCategoryIdHidden', 'tenantCategoryCategoryId', 'categoriesList', data.category_id);
            el.sortOrder.value = data.sort_order || 0;
            if (state.isSuperAdmin) {
                el.isActive.value = data.is_active || 1;
            }
            el.btnDelete.style.display = 'inline-block';
        } else {
            el.btnDelete.style.display = 'none';
        }
        
        el.formContainer.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
    
    // حفظ البيانات
    async function saveData(e) {
        if (e) e.preventDefault();
        
        const id = el.formId.value.trim();
        const isEdit = !!id;
        
        let tenantId = CONFIG.tenantId;
        if (state.isSuperAdmin) {
            const tenantDisplay = el.tenantDisplay.value.trim();
            tenantId = getIdFromDatalist('tenantsList', tenantDisplay);
            if (!tenantId) {
                showNotification(t('validation_tenant'), 'error');
                return;
            }
        }
        
        const categoryDisplay = el.categoryDisplay.value.trim();
        const categoryId = getIdFromDatalist('categoriesList', categoryDisplay);
        if (!categoryId) {
            showNotification(t('validation_category'), 'error');
            return;
        }
        
        const data = {
            tenant_id: parseInt(tenantId),
            category_id: parseInt(categoryId),
            sort_order: parseInt(el.sortOrder.value) || 0
        };
        
        if (state.isSuperAdmin) {
            data.is_active = parseInt(el.isActive.value) || 1;
        } else {
            data.is_active = 1;
        }
        
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
                console.log('Save success, showing notification');
                showNotification(isEdit ? t('alert_updated') : t('alert_added'), 'success');
                hideForm();
                loadData(state.page);
            } else {
                console.log('Save failed, showing error notification');
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
        if (!confirm(t('confirm_delete'))) {
            return;
        }
        
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
                console.log('Delete success, showing notification');
                showNotification(t('alert_deleted'), 'success');
                loadData(state.page);
            } else {
                console.log('Delete failed, showing error notification');
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
                console.log('Toggle success, showing notification');
                showNotification(t('alert_updated'), 'success');
                loadData(state.page);
            } else {
                console.log('Toggle failed, showing error notification');
                showNotification(t('alert_error'), 'error');
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
        if (!el.paginationInfo || !el.btnPrev || !el.btnNext || !el.paginationWrapper) return;
        
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
        
        el.paginationWrapper.style.display = total > 0 ? 'flex' : 'none';
    }
    
    function updateResultsCount(total) {
        if (!el.resultsCount || !el.resultsCountText) return;
        
        if (total > 0) {
            el.resultsCountText.textContent = `${total} ${t('results_found')}`;
            el.resultsCount.style.display = 'block';
        } else {
            el.resultsCountText.textContent = t('no_records');
            el.resultsCount.style.display = 'block';
        }
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
            pageTitle: document.querySelector('.page-title'),
            pageSubtitle: document.querySelector('.page-subtitle'),
            formContainer: document.getElementById('tenantCategoryFormContainer'),
            form: document.getElementById('tenantCategoryForm'),
            formTitle: document.getElementById('formTitle'),
            formId: document.getElementById('tenantCategoryId'),
            tenantDisplay: document.getElementById('tenantCategoryTenantId'),
            tenantHidden: document.getElementById('tenantCategoryTenantIdHidden'),
            categoryDisplay: document.getElementById('tenantCategoryCategoryId'),
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
        
        // Adjust notification position for RTL
        if (CONFIG.lang === 'ar') {
            el.notificationsContainer.style.left = '20px';
            el.notificationsContainer.style.right = 'auto';
        } else {
            el.notificationsContainer.style.right = '20px';
            el.notificationsContainer.style.left = 'auto';
        }
        
        if (!el.notificationsContainer) {
            console.error('Notifications container not found');
        } else {
            console.log('Notifications container initialized:', el.notificationsContainer);
            // Test notification - remove after testing
            showNotification('Test notification - page loaded', 'info');
        }
        
        if (el.form) el.form.onsubmit = saveData;
        if (el.btnCancel) el.btnCancel.onclick = hideForm;
        if (el.btnClose) el.btnClose.onclick = hideForm;
        if (el.btnApply) el.btnApply.onclick = applyFilters;
        if (el.btnReset) el.btnReset.onclick = resetFilters;
        if (el.btnRetry) el.btnRetry.onclick = () => loadData(state.page);
        if (el.btnAdd) el.btnAdd.onclick = () => showForm(false);
        if (el.btnDelete) el.btnDelete.onclick = () => {
            if (el.formId.value) {
                deleteData(parseInt(el.formId.value));
            }
        };
        
        if (el.tenantDisplay) {
            el.tenantDisplay.addEventListener('input', function() {
                const id = getIdFromDatalist('tenantsList', this.value);
                el.tenantHidden.value = id || '';
            });
        }
        if (el.categoryDisplay) {
            el.categoryDisplay.addEventListener('input', function() {
                const id = getIdFromDatalist('categoriesList', this.value);
                el.categoryHidden.value = id || '';
            });
        }
        if (el.filterTenant) {
            el.filterTenant.addEventListener('input', function() {
                const id = getIdFromDatalist('filterTenantsList', this.value);
                el.filterTenantHidden.value = id || '';
            });
        }
        if (el.filterCategory) {
            el.filterCategory.addEventListener('input', function() {
                const id = getIdFromDatalist('filterCategoriesList', this.value);
                el.filterCategoryHidden.value = id || '';
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
                if (result.success && result.data && result.data.length > 0) {
                    showForm(true, result.data[0]);
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
    // حذف بيانات
    async function deleteData(id) {
        if (!confirm(t('confirm_delete'))) {
            return;
        }
        
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
                console.log('Delete success, showing notification');
                showNotification(t('alert_deleted'), 'success');
                loadData(state.page);
            } else {
                console.log('Delete failed, showing error notification');
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
                console.log('Toggle success, showing notification');
                showNotification(t('alert_updated'), 'success');
                loadData(state.page);
            } else {
                console.log('Toggle failed, showing error notification');
                showNotification(t('alert_error'), 'error');
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
        if (!el.paginationInfo || !el.btnPrev || !el.btnNext || !el.paginationWrapper) return;
        
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
        
        el.paginationWrapper.style.display = total > 0 ? 'flex' : 'none';
    }
    
    function updateResultsCount(total) {
        if (!el.resultsCount || !el.resultsCountText) return;
        
        if (total > 0) {
            el.resultsCountText.textContent = `${total} ${t('results_found')}`;
            el.resultsCount.style.display = 'block';
        } else {
            el.resultsCountText.textContent = t('no_records');
            el.resultsCount.style.display = 'block';
        }
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
            pageTitle: document.querySelector('.page-title'),
            pageSubtitle: document.querySelector('.page-subtitle'),
            formContainer: document.getElementById('tenantCategoryFormContainer'),
            form: document.getElementById('tenantCategoryForm'),
            formTitle: document.getElementById('formTitle'),
            formId: document.getElementById('tenantCategoryId'),
            tenantDisplay: document.getElementById('tenantCategoryTenantId'),
            tenantHidden: document.getElementById('tenantCategoryTenantIdHidden'),
            categoryDisplay: document.getElementById('tenantCategoryCategoryId'),
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
        
        // Adjust notification position for RTL
        if (CONFIG.lang === 'ar') {
            el.notificationsContainer.style.left = '20px';
            el.notificationsContainer.style.right = 'auto';
        } else {
            el.notificationsContainer.style.right = '20px';
            el.notificationsContainer.style.left = 'auto';
        }
        
        if (!el.notificationsContainer) {
            console.error('Notifications container not found');
        } else {
            console.log('Notifications container initialized:', el.notificationsContainer);
            // Test notification - remove after testing
            showNotification('Test notification - page loaded', 'info');
        }
        
        if (el.form) el.form.onsubmit = saveData;
        if (el.btnCancel) el.btnCancel.onclick = hideForm;
        if (el.btnClose) el.btnClose.onclick = hideForm;
        if (el.btnApply) el.btnApply.onclick = applyFilters;
        if (el.btnReset) el.btnReset.onclick = resetFilters;
        if (el.btnRetry) el.btnRetry.onclick = () => loadData(state.page);
        if (el.btnAdd) el.btnAdd.onclick = () => showForm(false);
        if (el.btnDelete) el.btnDelete.onclick = () => {
            if (el.formId.value) {
                deleteData(parseInt(el.formId.value));
            }
        };
        
        if (el.tenantDisplay) {
            el.tenantDisplay.addEventListener('input', function() {
                const id = getIdFromDatalist('tenantsList', this.value);
                el.tenantHidden.value = id || '';
            });
        }
        if (el.categoryDisplay) {
            el.categoryDisplay.addEventListener('input', function() {
                const id = getIdFromDatalist('categoriesList', this.value);
                el.categoryHidden.value = id || '';
            });
        }
        if (el.filterTenant) {
            el.filterTenant.addEventListener('input', function() {
                const id = getIdFromDatalist('filterTenantsList', this.value);
                el.filterTenantHidden.value = id || '';
            });
        }
        if (el.filterCategory) {
            el.filterCategory.addEventListener('input', function() {
                const id = getIdFromDatalist('filterCategoriesList', this.value);
                el.filterCategoryHidden.value = id || '';
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
                if (result.success && result.data && result.data.length > 0) {
                    showForm(true, result.data[0]);
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