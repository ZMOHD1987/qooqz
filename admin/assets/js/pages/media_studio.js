(function () {
    'use strict';

    const CONFIG = window.MEDIA_STUDIO_CONFIG || {};
    const API = CONFIG.apiUrl || '/api/images';

    const state = {
        page: 1,
        perPage: 25,
        filters: {},
        items: [],
        imageTypes: [],
        permissions: CONFIG.permissions || {},
        isSuperAdmin: CONFIG.isSuperAdmin || false,
        selectedItems: [],
        isLoading: false,
        abortController: null
    };

    let el = {};
    let translations = {};

    // Load translations
    async function loadTranslations() {
        try {
            const response = await fetch(CONFIG.translationsUrl);
            if (response.ok) {
                translations = await response.json();
            } else {
                console.warn('[MediaStudio] Translations file not found, using defaults');
            }
        } catch (error) {
            console.error('[MediaStudio] Load translations error:', error);
        }
    }

    // Translation helper
    function t(key, placeholders = {}) {
        let text = translations[key] || key;
        Object.keys(placeholders).forEach(p => {
            text = text.replace(new RegExp(`{${p}}`, 'g'), placeholders[p]);
        });
        return text;
    }

    // Apply translations
    function applyTranslations() {
        const container = document.getElementById('mediaStudioPage');
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

    // Notifications
    function showNotification(message, type = 'success') {
        if (!el.notificationsContainer) {
            console.warn('[MediaStudio] Notifications container not found');
            alert(`${type}: ${message}`);
            return;
        }

        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.textContent = message;

        el.notificationsContainer.appendChild(notification);

        // Remove after 5 seconds
        setTimeout(() => {
            notification.classList.add('fade-out');
            setTimeout(() => notification.remove(), 300);
        }, 5000);

        // Remove on click
        notification.onclick = () => {
            notification.classList.add('fade-out');
            setTimeout(() => notification.remove(), 300);
        };
    }

    // Load image types
    async function loadImageTypes() {
        try {
            const response = await fetch('/api/image-types', { credentials: 'same-origin' });
            if (response.ok) {
                const data = await response.json();
                if (data.success && data.data) {
                    state.imageTypes = data.data;
                    populateDatalist('imageTypesList', state.imageTypes, 'id', 'name');
                    populateDatalist('filterImageTypesList', state.imageTypes, 'id', 'name');
                }
            } else {
                console.warn('[MediaStudio] Load image types failed:', response.status);
            }
        } catch (error) {
            console.error('[MediaStudio] Load image types error:', error);
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

    // Load data
    // Handle Selection Confirm
    async function handleSelectionConfirm() {
        if (state.selectedItems.length === 0) {
            showNotification(t('no_items_selected_alert', { defaultValue: 'Please select an image first' }), 'error');
            return;
        }

        // Fetch details of selected items
        const selectedObjects = state.items.filter(item => state.selectedItems.includes(item.id));

        if (selectedObjects.length === 0) return;

        // Auto-assign owner_id and image_type_id if present in CONFIG.autoFill
        // This ensures that "selecting" an image also "registers" it to the context
        if (CONFIG.autoFill && (CONFIG.autoFill.owner_id || CONFIG.autoFill.image_type_id)) {
            const updates = [];
            const newOwnerId = CONFIG.autoFill.owner_id ? parseInt(CONFIG.autoFill.owner_id) : null;
            const newTypeId = CONFIG.autoFill.image_type_id ? parseInt(CONFIG.autoFill.image_type_id) : null;

            selectedObjects.forEach(img => {
                let needsUpdate = false;
                const payload = {
                    id: img.id,
                    tenant_id: CONFIG.tenantId
                };

                if (newOwnerId && img.owner_id != newOwnerId) {
                    payload.owner_id = newOwnerId;
                    needsUpdate = true;
                    img.owner_id = newOwnerId; // Optimistic update
                }
                if (newTypeId && img.image_type_id != newTypeId) {
                    payload.image_type_id = newTypeId;
                    needsUpdate = true;
                    img.image_type_id = newTypeId; // Optimistic update
                }

                if (needsUpdate) {
                    updates.push(payload);
                }
            });

            if (updates.length > 0) {
                try {
                    if (el.btnSelectConfirm) {
                        el.btnSelectConfirm.disabled = true;
                        el.btnSelectConfirm.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
                    }
                    if (el.btnConfirmSelectionBar) {
                        el.btnConfirmSelectionBar.disabled = true;
                        el.btnConfirmSelectionBar.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
                    }

                    // Process updates sequentially or parallel
                    // Using parallel for speed
                    await Promise.all(updates.map(data => {
                        const formData = new FormData();
                        Object.keys(data).forEach(key => formData.append(key, data[key]));
                        formData.append('_method', 'PUT'); // Simulate PUT
                        formData.append('csrf_token', CONFIG.csrfToken || '');

                        return fetch(`${API}/${data.id}`, {
                            method: 'POST',
                            body: formData,
                            credentials: 'same-origin'
                        });
                    }));

                    console.log('[MediaStudio] Auto-assigned images to current context');

                } catch (err) {
                    console.error('[MediaStudio] Failed to auto-assign images:', err);
                    showNotification(t('alert_error_assign', { defaultValue: 'Failed to assign images to current context' }), 'error');
                    // Continue anyway? Or stop? User said "MUST register", so maybe we should stop if critical.
                    // But dispatching the ID might be enough for the parent form to save it too.
                } finally {
                    if (el.btnSelectConfirm) {
                        el.btnSelectConfirm.disabled = false;
                        el.btnSelectConfirm.innerHTML = '<i class="fas fa-check"></i> ' + (t('confirm_select', { defaultValue: 'Confirm Selection' }));
                    }
                    if (el.btnConfirmSelectionBar) {
                        el.btnConfirmSelectionBar.disabled = false;
                        el.btnConfirmSelectionBar.innerHTML = '<i class="fas fa-check"></i> Confirm Selection';
                    }
                }
            }
        }

        // Dispatch Event
        const eventDetail = (CONFIG.selectionLimit === 1) ? selectedObjects[0] : selectedObjects;

        console.log('[MediaStudio] Dispatching selection:', eventDetail);

        // Dispatch to window (for standalone)
        window.dispatchEvent(new CustomEvent('ImageStudio:selected', { detail: eventDetail }));

        // Dispatch to parent (for iframe)
        if (window.parent && window.parent !== window) {
            window.parent.dispatchEvent(new CustomEvent('ImageStudio:selected', { detail: eventDetail }));
            window.parent.dispatchEvent(new CustomEvent('ImageStudio:close', {}));
        } else {
            // If standalone, maybe close window?
            showNotification(t('selection_confirmed', { defaultValue: 'Selection confirmed' }), 'success');
            if (window.opener) window.close();
        }
    }

    // Load data
    async function loadData(page = 1) {
        if (state.isLoading) {
            console.warn('[MediaStudio] Already loading, skipping');
            return;
        }

        // Cancel previous request
        if (state.abortController) {
            state.abortController.abort();
        }

        state.abortController = new AbortController();
        state.isLoading = true;

        try {
            showLoading();

            state.page = page;
            const params = new URLSearchParams({
                page: page,
                limit: state.perPage,
                format: 'json',
                ...state.filters
            });

            console.log('[MediaStudio] Loading data with params:', params.toString());

            const response = await fetch(`${API}?${params}`, {
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json' },
                signal: state.abortController.signal
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }

            const result = await response.json();
            console.log('[MediaStudio] API response:', result);

            if (result.success && result.data && Array.isArray(result.data.data)) {
                state.items = result.data.data;
                console.log('[MediaStudio] Loaded items:', state.items.length);
                renderTable();
                updatePagination(result.data.meta || {});
                updateResultsCount(result.data.meta?.total || 0);
                showTable();
            } else {
                console.warn('[MediaStudio] No data or failed:', result);
                state.items = [];
                showEmpty();
            }
        } catch (error) {
            if (error.name === 'AbortError') {
                console.log('[MediaStudio] Request aborted');
                return;
            }
            console.error('[MediaStudio] Load error:', error);
            state.items = [];
            showError(t('error_loading'));
        } finally {
            state.isLoading = false;
            state.abortController = null;
        }
    }
    // Render table
    function renderTable() {
        if (!el.tableBody || !Array.isArray(state.items) || state.items.length === 0) {
            console.warn('[MediaStudio] No table body or items');
            showEmpty();
            return;
        }

        console.log('[MediaStudio] Rendering table with items:', state.items);

        let html = '';
        state.items.forEach(item => {
            const isMain = item.is_main == 1;
            const createdDate = new Date(item.created_at).toLocaleDateString();
            const imageTypeName = getImageTypeName(item.image_type_id);

            html += `
                <tr data-id="${item.id}">
                    <td><input type="checkbox" class="image-checkbox" value="${item.id}"></td>
                    <td>
                        <img src="${item.thumb_url || item.url}" alt="${item.filename || ''}" style="width: 50px; height: 50px; object-fit: cover; border-radius: 4px;">
                    </td>
                    <td>${item.id}</td>
                    <td>${escapeHtml(item.filename || '')}</td>
                    <td>${item.owner_id}</td>
                    <td>${escapeHtml(imageTypeName)}</td>
                    <td><span class="badge badge-${item.visibility === 'public' ? 'success' : 'secondary'}">${item.visibility}</span></td>
                    <td>
                        <label class="toggle-switch">
                            <input type="checkbox" class="is-main-toggle" data-id="${item.id}" ${isMain ? 'checked' : ''}>
                            <span class="toggle-slider"></span>
                        </label>
                    </td>
                    <td>${item.sort_order}</td>
                    <td>${createdDate}</td>
                    <td>
                        <div class="table-actions">
                            ${state.permissions.canEdit ? `<button class="btn btn-sm btn-outline edit-btn" data-id="${item.id}" title="${t('edit')}"><i class="fas fa-edit"></i></button>` : ''}
                            ${state.permissions.canDelete ? `<button class="btn btn-sm btn-danger delete-btn" data-id="${item.id}" title="${t('delete')}"><i class="fas fa-trash"></i></button>` : ''}
                        </div>
                    </td>
                </tr>
            `;
        });

        el.tableBody.innerHTML = html;

        // Bind events for toggles
        el.tableBody.querySelectorAll('.is-main-toggle').forEach(toggle => {
            toggle.addEventListener('change', handleMainToggle);
        });

        console.log('[MediaStudio] Table rendered');
    }

    // Show simplified "Add Image" form (Upload + From Studio tabs)
    function showAddForm() {
        hideForm();
        if (!el.addImageContainer) return;

        // Sync hidden fields from CONFIG.autoFill
        if (CONFIG.autoFill) {
            if (el.uploadOwnerId)            el.uploadOwnerId.value            = CONFIG.autoFill.owner_id      || '';
            if (el.uploadImageTypeIdHidden)  el.uploadImageTypeIdHidden.value  = CONFIG.autoFill.image_type_id || '';
            if (el.uploadTenantId)           el.uploadTenantId.value           = CONFIG.autoFill.tenant_id     || CONFIG.tenantId;
            if (el.uploadUserId)             el.uploadUserId.value             = CONFIG.autoFill.user_id       || '';
        }

        // Reset upload form
        if (el.uploadForm) el.uploadForm.reset();
        if (el.uploadFileList) { el.uploadFileList.innerHTML = ''; el.uploadFileList.style.display = 'none'; }

        // Show Upload tab by default
        switchAddTab('upload');

        el.addImageContainer.style.display = 'block';
        el.addImageContainer.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    function switchAddTab(tabName) {
        if (el.addTabUpload) el.addTabUpload.style.display  = tabName === 'upload'  ? 'block' : 'none';
        if (el.addTabStudio) el.addTabStudio.style.display  = tabName === 'studio'  ? 'block' : 'none';
        document.querySelectorAll('.add-tab-btn').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.tab === tabName);
        });
    }

    // Show full Edit form for existing records
    function showForm(isEdit = false, data = null) {
        if (!el.formContainer) return;

        // Hide add form and upload form
        if (el.addImageContainer) el.addImageContainer.style.display = 'none';
        exitStudioCopyMode();

        el.formContainer.style.display = 'block';
        el.form.reset();
        el.formId.value = '';

        if (el.formTitle) el.formTitle.textContent = isEdit ? t('form_edit_title') : t('form_add_title');

        if (isEdit && data) {
            el.formId.value = data.id;
            if (el.ownerId)        el.ownerId.value        = data.owner_id;
            setDisplayFromId('imageTypeIdHidden', 'imageTypeId', 'imageTypesList', data.image_type_id);
            if (el.filename)       el.filename.value       = data.filename   || '';
            if (el.url)            el.url.value            = data.url        || '';
            if (el.thumbUrl)       el.thumbUrl.value       = data.thumb_url  || '';
            if (el.mimeType)       el.mimeType.value       = data.mime_type  || '';
            if (el.size)           el.size.value           = data.size       || '';
            if (el.visibility)     el.visibility.value     = data.visibility || 'private';
            if (el.isMain)         el.isMain.value         = data.is_main    || 0;
            if (el.sortOrder)      el.sortOrder.value      = data.sort_order || 0;
            if (el.imageTenantId)  el.imageTenantId.value  = data.tenant_id  || CONFIG.tenantId;
            if (el.imageUserId)    el.imageUserId.value    = data.user_id    || CONFIG.autoFill?.user_id || '';
            if (el.btnDelete)      el.btnDelete.style.display = 'inline-block';
        } else {
            if (CONFIG.autoFill) {
                if (el.ownerId)       el.ownerId.value       = CONFIG.autoFill.owner_id || '';
                setDisplayFromId('imageTypeIdHidden', 'imageTypeId', 'imageTypesList', CONFIG.autoFill.image_type_id || '');
                if (el.imageTenantId) el.imageTenantId.value = CONFIG.autoFill.tenant_id || CONFIG.tenantId;
                if (el.imageUserId)   el.imageUserId.value   = CONFIG.autoFill.user_id   || '';
            }
            if (el.btnDelete) el.btnDelete.style.display = 'none';
        }

        el.formContainer.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    // Legacy showUploadForm — now delegates to showAddForm (upload tab)
    function showUploadForm() {
        showAddForm();
        switchAddTab('upload');
    }

    // ─────────────────────────────────────────────────────────
    // Studio Copy Mode – pick existing image to add as new
    // ─────────────────────────────────────────────────────────

    function enterStudioCopyMode() {
        state.studioCopyMode = true;

        if (el.addImageContainer) el.addImageContainer.style.display = 'none';
        if (el.studioCopyBar)     el.studioCopyBar.style.display     = 'flex';
        if (el.btnConfirmCopy)    el.btnConfirmCopy.disabled         = true;

        // Reset any existing selection
        state.studioCopySelectedId = null;
        if (el.tableBody) el.tableBody.querySelectorAll('tr').forEach(tr => tr.classList.remove('studio-copy-selected'));

        // Scroll to grid
        const grid = document.getElementById('mediaStudioPage');
        if (grid) grid.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    function exitStudioCopyMode() {
        if (!state.studioCopyMode) return;
        state.studioCopyMode         = false;
        state.studioCopySelectedId   = null;
        if (el.studioCopyBar)  el.studioCopyBar.style.display = 'none';
        if (el.tableBody) el.tableBody.querySelectorAll('tr').forEach(tr => tr.classList.remove('studio-copy-selected'));
    }

    async function confirmStudioCopy() {
        const srcId = state.studioCopySelectedId;
        if (!srcId) {
            showNotification(t('no_items_selected_alert') || 'Please select an image first', 'error');
            return;
        }

        const srcImg = state.items.find(img => img.id === srcId);
        if (!srcImg) { showNotification(t('alert_error'), 'error'); return; }

        const data = {
            owner_id:       CONFIG.autoFill?.owner_id      || srcImg.owner_id,
            image_type_id:  CONFIG.autoFill?.image_type_id || srcImg.image_type_id,
            tenant_id:      CONFIG.tenantId,
            user_id:        CONFIG.autoFill?.user_id        || srcImg.user_id || 0,
            url:            srcImg.url,
            thumb_url:      srcImg.thumb_url   || '',
            filename:       srcImg.filename    || '',
            mime_type:      srcImg.mime_type   || 'image/jpeg',
            size:           srcImg.size        || 0,
            visibility:     srcImg.visibility  || 'private',
            is_main:        srcImg.is_main     || 0,
            sort_order:     srcImg.sort_order  || 0,
            csrf_token:     CONFIG.csrfToken   || '',
        };

        try {
            if (el.btnConfirmCopy) {
                el.btnConfirmCopy.disabled = true;
                el.btnConfirmCopy.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            }

            const formData = new FormData();
            Object.entries(data).forEach(([k, v]) => { if (v !== null && v !== undefined) formData.append(k, v); });

            const response = await fetch(API, { method: 'POST', body: formData, credentials: 'same-origin' });
            const result   = await response.json();

            if (el.btnConfirmCopy) {
                el.btnConfirmCopy.disabled = false;
                el.btnConfirmCopy.innerHTML = '<i class="fas fa-check"></i> <span>' + (t('use_image') || 'Use This Image') + '</span>';
            }

            showNotification(result.message || (result.success ? t('alert_added') : t('alert_error')), result.success ? 'success' : 'error');

            if (result.success) {
                exitStudioCopyMode();
                loadData(state.page);
            }
        } catch (err) {
            console.error('[MediaStudio] confirmStudioCopy error:', err);
            if (el.btnConfirmCopy) {
                el.btnConfirmCopy.disabled = false;
                el.btnConfirmCopy.innerHTML = '<i class="fas fa-check"></i> <span>' + (t('use_image') || 'Use This Image') + '</span>';
            }
            showNotification(t('alert_error'), 'error');
        }
    }

    // Save data
    async function saveData(e) {
        if (e) e.preventDefault();

        const idValue = el.formId.value.trim();
        const isEdit = !!idValue;

        // 1️⃣ التحقق من الحقول الأساسية
        const ownerId = parseInt(el.ownerId.value);
        if (!ownerId || ownerId <= 0) {
            showNotification(t('Owner ID must be a positive integer'), 'error');
            return;
        }

        const imageTypeId = parseInt(getIdFromDatalist('imageTypesList', el.imageTypeDisplay.value));
        if (!imageTypeId || imageTypeId <= 0) {
            showNotification(t('Image type ID must be a positive integer'), 'error');
            return;
        }

        const urlValue = el.url.value.trim();
        if (!urlValue) {
            showNotification(t('URL is required'), 'error');
            return;
        }

        let filenameValue = el.filename.value.trim();
        if (!filenameValue && urlValue) {
            // Auto-generate filename from URL
            try {
                const urlObj = new URL(urlValue);
                filenameValue = urlObj.pathname.split('/').pop() || 'image_' + Date.now();
            } catch (e) {
                filenameValue = 'image_' + Date.now();
            }
        }

        // 2️⃣ تجهيز بيانات الإرسال
        const data = {
            owner_id: ownerId,
            image_type_id: imageTypeId,
            tenant_id: parseInt(el.imageTenantId?.value || CONFIG.tenantId),
            user_id: parseInt(el.imageUserId?.value || CONFIG.autoFill?.user_id || 0),
            filename: filenameValue,
            url: urlValue,
            thumb_url: el.thumbUrl.value.trim() || null,
            mime_type: el.mimeType.value.trim() || 'image/jpeg',
            size: parseInt(el.size.value) || null,
            visibility: el.visibility.value || 'private',
            is_main: parseInt(el.isMain.value) || 0,
            sort_order: parseInt(el.sortOrder.value) || 0
        };

        if (isEdit) {
            const id = parseInt(idValue);
            if (!id || id <= 0) {
                showNotification(t('ID is required'), 'error');
                return;
            }
            data.id = id;
        }

        // 3️⃣ إرسال البيانات
        try {
            if (el.btnSave) {
                el.btnSave.innerHTML = '<i class="fas fa-spinner fa-spin"></i> ' + t('save_button');
                el.btnSave.disabled = true;
            }

            const url = isEdit ? `${API}/${data.id}` : API;
            let method = isEdit ? 'PUT' : 'POST';

            const formData = new FormData();
            Object.keys(data).forEach(key => {
                if (data[key] !== null) formData.append(key, data[key]);
            });
            formData.append('csrf_token', CONFIG.csrfToken || '');

            // ✅ الحل: تحويل PUT إلى POST مع _method
            if (isEdit) {
                method = 'POST';
                formData.append('_method', 'PUT');
            }

            const response = await fetch(url, {
                method: method,
                body: formData,
                credentials: 'same-origin'
            });

            const result = await response.json();
            console.log('[MediaStudio] Save response:', result);

            if (el.btnSave) {
                el.btnSave.innerHTML = '<i class="fas fa-save"></i> ' + t('save_button');
                el.btnSave.disabled = false;
            }

            showNotification(result.message || (isEdit ? t('alert_updated') : t('alert_added')), result.success ? 'success' : 'error');

            if (result.success) {
                hideForm();
                loadData(state.page);
            }

        } catch (error) {
            console.error('[MediaStudio] Save error:', error);
            if (el.btnSave) {
                el.btnSave.innerHTML = '<i class="fas fa-save"></i> ' + t('save_button');
                el.btnSave.disabled = false;
            }
            showNotification(t('alert_error'), 'error');
        }
    }


    // Upload data
    async function uploadData(e) {
        if (e) e.preventDefault();

        const files = el.uploadImages ? el.uploadImages.files : null;
        if (!files || files.length === 0) {
            showNotification(t('validation_select_files') || 'Please select at least one file', 'error');
            return;
        }

        const data = {
            owner_id:      parseInt(el.uploadOwnerId?.value       || 0),
            image_type_id: parseInt(el.uploadImageTypeIdHidden?.value || 0),
            tenant_id:     parseInt(el.uploadTenantId?.value      || CONFIG.tenantId),
            user_id:       parseInt(el.uploadUserId?.value        || CONFIG.autoFill?.user_id || 0),
            visibility:    'public',
            sort_order:    0,
        };

        try {
            if (el.btnUploadSave) {
                el.btnUploadSave.innerHTML = '<i class="fas fa-spinner fa-spin"></i> ' + t('upload_button');
                el.btnUploadSave.disabled = true;
            }

            const formData = new FormData();
            Object.keys(data).forEach(key => { formData.append(key, data[key]); });
            formData.append('csrf_token', CONFIG.csrfToken || '');

            // Add files
            for (let i = 0; i < files.length; i++) {
                formData.append('images[]', files[i]);
            }

            console.log('[MediaStudio] Uploading files:', files.length);

            const response = await fetch(API, { method: 'POST', body: formData, credentials: 'same-origin' });
            const result   = await response.json();
            console.log('[MediaStudio] Upload response:', result);

            if (el.btnUploadSave) {
                el.btnUploadSave.innerHTML = '<i class="fas fa-upload"></i> <span>' + t('upload_button') + '</span>';
                el.btnUploadSave.disabled = false;
            }

            showNotification(result.message || t('alert_uploaded'), result.success ? 'success' : 'error');

            if (result.success) {
                hideAddForm();
                loadData(state.page);
            }
        } catch (error) {
            console.error('[MediaStudio] Upload error:', error);
            if (el.btnUploadSave) {
                el.btnUploadSave.innerHTML = '<i class="fas fa-upload"></i> <span>' + t('upload_button') + '</span>';
                el.btnUploadSave.disabled = false;
            }
            showNotification(t('alert_error'), 'error');
        }
    }

    // Delete data
    async function deleteData(id) {
        if (!confirm(t('confirm_delete'))) {
            return;
        }

        try {
            console.log('[MediaStudio] Deleting item:', id);

            const response = await fetch(`${API}/${id}`, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-Token': CONFIG.csrfToken || ''
                }
            });

            const result = await response.json();
            console.log('[MediaStudio] Delete response:', result);

            showNotification(result.message || t('alert_deleted'), result.success ? 'success' : 'error');

            if (result.success) {
                loadData(state.page);
            }
        } catch (error) {
            console.error('[MediaStudio] Delete error:', error);
            showNotification(t('alert_error'), 'error');
        }
    }

    // Delete selected
    async function deleteSelected() {
        const selectedIds = state.selectedItems;
        if (selectedIds.length === 0) return;

        if (!confirm(t('confirm_delete_selected', { count: selectedIds.length }))) {
            return;
        }

        try {
            for (const id of selectedIds) {
                await fetch(`${API}/${id}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-Token': CONFIG.csrfToken || ''
                    }
                });
            }

            showNotification(t('alert_deleted_selected'), 'success');
            loadData(state.page);
            state.selectedItems = [];
            updateDeleteSelectedButton();
        } catch (error) {
            console.error('[MediaStudio] Delete selected error:', error);
            showNotification(t('alert_error'), 'error');
        }
    }

    // Handle main toggle
    async function handleMainToggle(e) {
        const toggle = e.target;
        const id = toggle.dataset.id;
        const item = state.items.find(i => i.id == id);

        if (!item) return;

        try {
            console.log('[MediaStudio] Toggling main for:', id, item.is_main);

            const response = await fetch(`${API}/set_main`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': CONFIG.csrfToken || ''
                },
                body: JSON.stringify({
                    image_id: id,
                    owner_id: item.owner_id,
                    image_type_id: item.image_type_id,
                    tenant_id: CONFIG.tenantId
                })
            });

            const result = await response.json();
            console.log('[MediaStudio] Toggle response:', result);

            if (!result.success) {
                // Revert toggle
                toggle.checked = !toggle.checked;
                showNotification(result.message || t('alert_error'), 'error');
            }
        } catch (error) {
            // Revert toggle
            toggle.checked = !toggle.checked;
            console.error('[MediaStudio] Toggle main error:', error);
            showNotification(t('alert_error'), 'error');
        }
    }

    // Apply filters
    function applyFilters() {
        state.filters = {};

        const filename = el.filterFilename?.value.trim();
        if (filename) state.filters.q = filename;

        const imageTypeId = el.filterTypeHidden?.value;
        if (imageTypeId) state.filters.image_type_id = imageTypeId;

        const ownerId = el.filterOwnerId?.value;
        if (ownerId) state.filters.owner_id = parseInt(ownerId);

        const visibility = el.filterVisibility?.value;
        if (visibility) state.filters.visibility = visibility;

        console.log('[MediaStudio] Applying filters:', state.filters);

        loadData(1);
    }

    // Reset filters
    function resetFilters() {
        if (el.filterFilename) el.filterFilename.value = '';
        if (el.filterType) el.filterType.value = '';
        if (el.filterTypeHidden) el.filterTypeHidden.value = '';
        if (el.filterOwnerId) el.filterOwnerId.value = '';
        if (el.filterVisibility) el.filterVisibility.value = '';

        state.filters = {};
        loadData(1);
    }

    // Display helpers
    function showLoading() {
        if (el.loading) el.loading.style.display = 'block';
        if (el.tableContainer) el.tableContainer.style.display = 'none';
        if (el.empty) el.empty.style.display = 'none';
        if (el.error) el.error.style.display = 'none';
    }

    function showTable() {
        if (el.loading) el.loading.style.display = 'none';
        if (el.tableContainer) el.tableContainer.style.display = 'block';
        if (el.empty) el.empty.style.display = 'none';
        if (el.error) el.error.style.display = 'none';
    }

    function showEmpty() {
        if (el.loading) el.loading.style.display = 'none';
        if (el.tableContainer) el.tableContainer.style.display = 'none';
        if (el.empty) el.empty.style.display = 'block';
        if (el.error) el.error.style.display = 'none';
        if (el.tableBody) el.tableBody.innerHTML = '';
        updateResultsCount(0);
    }

    function showError(message) {
        if (el.loading) el.loading.style.display = 'none';
        if (el.tableContainer) el.tableContainer.style.display = 'none';
        if (el.empty) el.empty.style.display = 'none';
        if (el.error) {
            el.error.style.display = 'block';
            if (el.errorMessage) el.errorMessage.textContent = message || t('error_loading');
        }
    }

    function hideForm() {
        if (el.formContainer) el.formContainer.style.display = 'none';
        if (el.form) el.form.reset();
    }

    function hideAddForm() {
        if (el.addImageContainer) el.addImageContainer.style.display = 'none';
        if (el.uploadForm) el.uploadForm.reset();
        if (el.uploadFileList) { el.uploadFileList.innerHTML = ''; el.uploadFileList.style.display = 'none'; }
        exitStudioCopyMode();
    }

    // Legacy alias so existing code that calls hideUploadForm() still works
    function hideUploadForm() {
        hideAddForm();
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

    function updateDeleteSelectedButton() {
        if (el.btnDeleteSelected) {
            el.btnDeleteSelected.style.display = state.selectedItems.length > 0 ? 'inline-block' : 'none';
        }
    }

    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function getImageTypeName(id) {
        const type = state.imageTypes.find(t => t.id == id);
        return type ? type.name : 'Unknown';
    }

    // Initialize
    function init() {
        el = {
            pageTitle: document.querySelector('.page-title'),
            pageSubtitle: document.querySelector('.page-subtitle'),
            formContainer: document.getElementById('imageFormContainer'),
            formContainer: document.getElementById('imageFormContainer'),
            addImageContainer: document.getElementById('addImageContainer'),
            addTabUpload: document.getElementById('addTabUpload'),
            addTabStudio: document.getElementById('addTabStudio'),
            studioCopyBar: document.getElementById('studioCopyBar'),
            btnConfirmCopy: document.getElementById('btnConfirmCopy'),
            btnCancelCopy: document.getElementById('btnCancelCopy'),
            form: document.getElementById('imageForm'),
            uploadForm: document.getElementById('uploadForm'),
            formTitle: document.getElementById('formTitle'),
            formId: document.getElementById('imageId'),
            ownerId: document.getElementById('imageOwnerId'),
            imageTypeDisplay: document.getElementById('imageTypeId'),
            imageTypeHidden: document.getElementById('imageTypeIdHidden'),
            filename: document.getElementById('imageFilename'),
            url: document.getElementById('imageUrl'),
            thumbUrl: document.getElementById('imageThumbUrl'),
            mimeType: document.getElementById('imageMimeType'),
            size: document.getElementById('imageSize'),
            visibility: document.getElementById('imageVisibility'),
            isMain: document.getElementById('imageIsMain'),
            sortOrder: document.getElementById('imageSortOrder'),
            imageTenantId: document.getElementById('imageTenantId'),
            imageUserId: document.getElementById('imageUserId'),
            uploadOwnerId: document.getElementById('uploadOwnerId'),
            uploadImageTypeIdHidden: document.getElementById('uploadImageTypeIdHidden'),
            uploadTenantId: document.getElementById('uploadTenantId'),
            uploadUserId: document.getElementById('uploadUserId'),
            uploadImages: document.getElementById('uploadImages'),
            uploadDropZone: document.getElementById('uploadDropZone'),
            uploadFileList: document.getElementById('uploadFileList'),
            btnSave: document.getElementById('btnSaveImage'),
            btnUploadSave: document.getElementById('btnUploadSave'),
            btnCancel: document.getElementById('btnCancelImageForm'),
            btnCancelUpload: document.getElementById('btnCancelUploadForm'),
            btnDelete: document.getElementById('btnDeleteImage'),
            btnClose: document.getElementById('btnCloseImageForm'),
            btnCloseAdd: document.getElementById('btnCloseAddForm'),
            btnEnterStudioCopy: document.getElementById('btnEnterStudioCopy'),
            btnCancelStudioTab: document.getElementById('btnCancelStudioTab'),

            btnSelectConfirm: document.getElementById('btnSelectConfirm'),

            table: document.getElementById('imagesTable'),
            tableBody: document.getElementById('imageTableBody'),
            loading: document.getElementById('imageGridLoading'),
            tableContainer: document.getElementById('imageGridContainer'),
            empty: document.getElementById('imageEmptyState'),
            error: document.getElementById('imageErrorState'),
            errorMessage: document.getElementById('imageErrorMessage'),

            filterFilename: document.getElementById('imageFilterFilename'),
            filterType: document.getElementById('imageFilterType'),
            filterTypeHidden: document.getElementById('imageFilterTypeHidden'),
            filterOwnerId: document.getElementById('imageFilterOwnerId'),
            filterVisibility: document.getElementById('imageFilterVisibility'),
            btnApply: document.getElementById('btnApplyImageFilters'),
            btnReset: document.getElementById('btnResetImageFilters'),
            btnDeleteSelected: document.getElementById('btnDeleteSelected'),

            resultsCount: document.getElementById('imageResultsCount'),
            resultsCountText: document.getElementById('imageResultsCountText'),
            paginationInfo: document.getElementById('imagePaginationInfo'),
            btnPrev: document.getElementById('btnPrevImagePage'),
            btnNext: document.getElementById('btnNextImagePage'),
            paginationWrapper: document.getElementById('imagePaginationWrapper'),
            btnRetry: document.getElementById('btnRetryImages'),
            btnAdd: document.getElementById('btnAddImage'),

            // Selection Bar
            selectionBar: document.getElementById('selectionBar'),
            selectionCount: document.getElementById('selectionCount'),
            btnConfirmSelectionBar: document.getElementById('btnConfirmSelectionBar'),

            selectAll: document.getElementById('selectAllImages'),
            notificationsContainer: document.getElementById('notificationsContainer')
        };

        // Extend state for studio copy mode
        state.studioCopyMode       = false;
        state.studioCopySelectedId = null;

        // Handle Embedded Mode
        if (CONFIG.embedded) {
            document.body.classList.add('embedded-mode');

            if (CONFIG.mode === 'select') {
                if (el.btnSelectConfirm) {
                    el.btnSelectConfirm.style.display = 'inline-block';
                    el.btnSelectConfirm.onclick = handleSelectionConfirm;
                }
            }

            // Auto-Run Actions
            if (CONFIG.action === 'add') {
                showAddForm();
            } else if (CONFIG.action === 'upload') {
                showAddForm();
            }
        }

        // ── Edit form events ──
        if (el.form)       el.form.onsubmit        = saveData;
        if (el.btnCancel)  el.btnCancel.onclick     = hideForm;
        if (el.btnClose)   el.btnClose.onclick      = hideForm;
        if (el.btnDelete)  el.btnDelete.onclick     = () => { if (el.formId.value) deleteData(parseInt(el.formId.value)); };

        // ── Add form events ──
        if (el.uploadForm)         el.uploadForm.onsubmit         = uploadData;
        if (el.btnCancelUpload)    el.btnCancelUpload.onclick     = hideAddForm;
        if (el.btnCloseAdd)        el.btnCloseAdd.onclick         = hideAddForm;
        if (el.btnCancelStudioTab) el.btnCancelStudioTab.onclick  = hideAddForm;
        if (el.btnEnterStudioCopy) el.btnEnterStudioCopy.onclick  = enterStudioCopyMode;

        // ── Studio Copy Bar events ──
        if (el.btnConfirmCopy) el.btnConfirmCopy.onclick = confirmStudioCopy;
        if (el.btnCancelCopy)  el.btnCancelCopy.onclick  = exitStudioCopyMode;

        // ── Tab switching ──
        document.querySelectorAll('.add-tab-btn').forEach(btn => {
            btn.addEventListener('click', () => switchAddTab(btn.dataset.tab));
        });

        // ── Drag & Drop upload zone ──
        if (el.uploadDropZone) {
            el.uploadDropZone.addEventListener('dragover', e => { e.preventDefault(); el.uploadDropZone.classList.add('drag-over'); });
            el.uploadDropZone.addEventListener('dragleave', () => el.uploadDropZone.classList.remove('drag-over'));
            el.uploadDropZone.addEventListener('drop', e => {
                e.preventDefault();
                el.uploadDropZone.classList.remove('drag-over');
                if (e.dataTransfer.files.length) {
                    const dt = new DataTransfer();
                    Array.from(e.dataTransfer.files).forEach(f => dt.items.add(f));
                    el.uploadImages.files = dt.files;
                    updateFileList(dt.files);
                }
            });
        }

        if (el.uploadImages) {
            el.uploadImages.addEventListener('change', () => updateFileList(el.uploadImages.files));
        }

        // ── Filter/pagination events ──
        if (el.btnApply) el.btnApply.onclick = applyFilters;
        if (el.btnReset) el.btnReset.onclick = resetFilters;
        if (el.btnRetry) el.btnRetry.onclick = () => loadData(state.page);
        if (el.btnAdd)   el.btnAdd.onclick   = showAddForm;
        if (el.btnDeleteSelected) el.btnDeleteSelected.onclick = deleteSelected;

        if (el.imageTypeDisplay) {
            el.imageTypeDisplay.addEventListener('input', function () {
                const id = getIdFromDatalist('imageTypesList', this.value);
                el.imageTypeHidden.value = id || '';
            });
        }
        if (el.filterType) {
            el.filterType.addEventListener('input', function () {
                const id = getIdFromDatalist('filterImageTypesList', this.value);
                el.filterTypeHidden.value = id || '';
            });
        }

        // Select all checkbox
        if (el.selectAll) {
            el.selectAll.addEventListener('change', function () {
                const checkboxes = el.tableBody.querySelectorAll('.image-checkbox');
                checkboxes.forEach(cb => cb.checked = this.checked);
                updateSelectedItems();
            });
        }

        // Table event delegation
        if (el.tableBody) {
            el.tableBody.addEventListener('change', function (e) {
                if (e.target.classList.contains('image-checkbox')) {
                    if (e.target.checked && CONFIG.mode === 'select' && CONFIG.selectionLimit === 1) {
                        el.tableBody.querySelectorAll('.image-checkbox').forEach(cb => {
                            if (cb !== e.target) cb.checked = false;
                        });
                    }
                    updateSelectedItems();
                }
            });
            el.tableBody.addEventListener('click', function (e) {
                // Studio copy mode: clicking a row picks it
                if (state.studioCopyMode && !e.target.closest('button') && !e.target.closest('input') && !e.target.closest('a')) {
                    const tr = e.target.closest('tr');
                    if (tr) {
                        el.tableBody.querySelectorAll('tr').forEach(r => r.classList.remove('studio-copy-selected'));
                        tr.classList.add('studio-copy-selected');
                        state.studioCopySelectedId = parseInt(tr.dataset.id);
                        if (el.btnConfirmCopy) el.btnConfirmCopy.disabled = false;
                    }
                    return;
                }

                // Normal select mode: clicking row selects checkbox
                if (CONFIG.mode === 'select' && !e.target.closest('button') && !e.target.closest('input') && !e.target.closest('a')) {
                    const tr = e.target.closest('tr');
                    if (tr) {
                        const cb = tr.querySelector('.image-checkbox');
                        if (cb) {
                            cb.checked = !cb.checked;
                            if (cb.checked && CONFIG.selectionLimit === 1) {
                                el.tableBody.querySelectorAll('.image-checkbox').forEach(other => {
                                    if (other !== cb) other.checked = false;
                                });
                            }
                            updateSelectedItems();
                        }
                    }
                }

                if (e.target.closest('.edit-btn')) {
                    const id = e.target.closest('.edit-btn').dataset.id;
                    editImage(id);
                } else if (e.target.closest('.delete-btn')) {
                    const id = e.target.closest('.delete-btn').dataset.id;
                    deleteData(id);
                }
            });
        }

        if (el.btnConfirmSelectionBar) el.btnConfirmSelectionBar.onclick = handleSelectionConfirm;

        loadTranslations().then(() => {
            applyTranslations();
            loadImageTypes().then(() => loadData());
        });
    }

    // Update the file list display in the upload zone
    function updateFileList(files) {
        if (!el.uploadFileList) return;
        if (!files || files.length === 0) {
            el.uploadFileList.style.display = 'none';
            el.uploadFileList.innerHTML = '';
            return;
        }
        el.uploadFileList.style.display = 'block';
        const items = Array.from(files).map(f =>
            `<div class="upload-file-item"><i class="fas fa-image"></i> <span>${escapeHtml(f.name)}</span> <small>(${Math.round(f.size/1024)} KB)</small></div>`
        ).join('');
        el.uploadFileList.innerHTML = items;
    }

    async function editImage(id) {
        try {
            console.log('[MediaStudio] Editing item:', id);

            const response = await fetch(`${API}/${id}?format=json`, {
                credentials: 'same-origin'
            });
            const result = await response.json();
            console.log('[MediaStudio] Edit response:', result);

            if (result.success && result.data) {
                showForm(true, result.data);
            } else {
                showNotification(t('alert_error'), 'error');
            }
        } catch (error) {
            console.error('[MediaStudio] Edit error:', error);
            showNotification(t('alert_error'), 'error');
        }
    }

    function updateSelectedItems() {
        const checkboxes = el.tableBody.querySelectorAll('.image-checkbox:checked');
        state.selectedItems = Array.from(checkboxes).map(cb => parseInt(cb.value));
        updateDeleteSelectedButton();

        // Update Selection Bar
        if (CONFIG.mode === 'select' && el.selectionBar) {
            if (state.selectedItems.length > 0) {
                el.selectionBar.classList.add('visible');
                if (el.selectionCount) el.selectionCount.textContent = state.selectedItems.length;
            } else {
                el.selectionBar.classList.remove('visible');
            }
        }

        // Highlight rows
        el.tableBody.querySelectorAll('tr').forEach(tr => tr.classList.remove('selected'));
        checkboxes.forEach(cb => {
            const tr = cb.closest('tr');
            if (tr) tr.classList.add('selected');
        });
    }

    window.MediaStudio = {
        init,
        load: loadData,
        add:    showAddForm,
        upload: showAddForm,
        edit:   editImage,
        remove: deleteData
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        setTimeout(init, 100);
    }

    window.page = window.page || {};
    window.page.run = init;

})();