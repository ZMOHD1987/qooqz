/**
 * /admin/assets/js/pages/media_studio.js — Production v2.0
 *
 * ─ التغييرات ─────────────────────────────────────────────────
 * • الترجمات من CONFIG.strings فقط — لا fetch مكرّر
 * • notify() بـ ms- prefix يتطابق مع CSS
 * • showState() موحّدة (msLoading/msEmpty/msError/msTableContainer)
 * • btn-outline للتعديل → btn-primary
 * • credentials: 'same-origin' على كل fetch
 * • ESC يُغلق الـ form cards
 * • Admin.page.register + window.page
 * • toggle switch: ms-toggle بدل toggle-switch
 * • image type badge: ms-type-badge بدل image-type-badge
 * ─────────────────────────────────────────────────────────────
 */
(function () {
    'use strict';

    const CFG    = window.MEDIA_STUDIO_CONFIG || {};
    const API    = CFG.apiUrl       || '/api/images';
    const IMG_TYPES_API = CFG.imageTypesApi || '/api/image-types';
    const SET_MAIN_API  = CFG.setMainApi    || '/api/images/set_main';

    // ── i18n — من CONFIG.strings فقط ─────────────────────────
    const S = CFG.strings || {};
    function t(key, fallback) {
        return typeof S[key] === 'string' ? S[key] : (fallback || key);
    }

    // ── State ─────────────────────────────────────────────────
    const state = {
        page:                1,
        perPage:             25,
        filters:             {},
        items:               [],
        imageTypes:          [],
        selectedItems:       [],
        studioCopyMode:      false,
        studioCopySelectedId:null,
    };

    let el = {};

    // ════════════════════════════════════════════════════════
    // FETCH HELPER
    // ════════════════════════════════════════════════════════
    async function apiFetch(url, options = {}) {
        const defaults = {
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-Token':     CFG.csrfToken || '',
            },
        };
        // لا نُضيف Content-Type إذا body هو FormData
        if (options.body && !(options.body instanceof FormData)) {
            defaults.headers['Content-Type'] = 'application/json';
        }
        const config = {
            ...defaults,
            ...options,
            headers: { ...defaults.headers, ...(options.headers || {}) },
        };
        const res  = await fetch(url, config);
        const data = await res.json().catch(() => ({}));
        return data; // لا نُلقي خطأ — نُعيد البيانات ونتعامل معها في الدالة
    }

    function esc(txt) {
        if (txt == null) return '';
        const d = document.createElement('div');
        d.textContent = String(txt);
        return d.innerHTML;
    }

    // ════════════════════════════════════════════════════════
    // TOAST NOTIFICATIONS  (ms- prefix → matches CSS)
    // ════════════════════════════════════════════════════════
    function notify(message, type = 'info') {
        const AF = window.AdminFramework;
        if (AF && !CFG.embedded) {
            if (type === 'success' && AF.success) return AF.success(message);
            if (type === 'error'   && AF.error)   return AF.error(message);
            if (type === 'warning' && AF.warning)  return AF.warning(message);
            if (AF.notify) return AF.notify(message, type);
        }

        let container = document.getElementById('msNotifications');
        if (!container) {
            container = document.createElement('div');
            container.id = 'msNotifications';
            container.className = 'ms-notifications';
            document.body.appendChild(container);
        }
        const toast = document.createElement('div');
        toast.className = `ms-toast ms-toast-${type}`;
        toast.setAttribute('role', 'alert');

        const msg = document.createElement('span');
        msg.textContent = message;
        toast.appendChild(msg);

        const close = document.createElement('button');
        close.className = 'ms-toast-close';
        close.setAttribute('aria-label', 'Close');
        close.textContent = '\u00d7';
        close.addEventListener('click', () => toast.remove());
        toast.appendChild(close);

        container.appendChild(toast);
        setTimeout(() => { if (toast.parentNode) toast.remove(); }, 4500);
    }

    // ════════════════════════════════════════════════════════
    // TABLE STATE
    // ════════════════════════════════════════════════════════
    function showState(which, msg = '') {
        const loading   = document.getElementById('msLoading');
        const empty     = document.getElementById('msEmpty');
        const error     = document.getElementById('msError');
        const container = document.getElementById('msTableContainer');
        const errMsg    = document.getElementById('msErrorMessage');

        [loading, empty, error, container].forEach(e => { if (e) e.style.display = 'none'; });

        switch (which) {
            case 'loading': if (loading)   loading.style.display   = 'flex';  break;
            case 'empty':   if (empty)     empty.style.display     = 'flex';  break;
            case 'error':
                if (error)  error.style.display = 'flex';
                if (errMsg && msg) errMsg.textContent = msg;
                break;
            default:        if (container) container.style.display = 'block'; break;
        }
    }

    // ════════════════════════════════════════════════════════
    // IMAGE TYPES
    // ════════════════════════════════════════════════════════
    async function loadImageTypes() {
        try {
            const data = await apiFetch(IMG_TYPES_API);
            if (data.success && data.data) {
                state.imageTypes = data.data;
                populateDatalist('imageTypesList', state.imageTypes);
                populateDatalist('filterImageTypesList', state.imageTypes);
            }
        } catch (e) {
            console.warn('[MediaStudio] loadImageTypes:', e);
        }
    }

    function populateDatalist(datalistId, items) {
        const dl = document.getElementById(datalistId);
        if (!dl || !Array.isArray(items)) return;
        dl.innerHTML = '';
        items.forEach(item => {
            const o = document.createElement('option');
            o.value = item.name || item.id;
            o.setAttribute('data-id', item.id);
            dl.appendChild(o);
        });
    }

    function getIdFromDatalist(datalistId, displayValue) {
        const dl = document.getElementById(datalistId);
        const trimmed = (displayValue || '').trim();
        if (!dl || !trimmed) return null;
        for (const o of dl.querySelectorAll('option')) {
            if (o.value === trimmed) return o.getAttribute('data-id');
        }
        if (/^\d+$/.test(trimmed)) return trimmed;
        return null;
    }

    function setDisplayFromId(hiddenId, displayId, datalistId, idValue) {
        if (!idValue) return;
        const dl = document.getElementById(datalistId);
        if (!dl) return;
        for (const o of dl.querySelectorAll('option')) {
            if (o.getAttribute('data-id') === String(idValue)) {
                const d = document.getElementById(displayId);
                const h = document.getElementById(hiddenId);
                if (d) d.value = o.value;
                if (h) h.value = idValue;
                return;
            }
        }
    }

    function getImageTypeBadge(imageTypeId) {
        const type = state.imageTypes.find(tp => tp.id == imageTypeId);
        if (!type) return `<span class="ms-type-badge ms-type-badge--unknown">Unknown</span>`;
        const icon  = type.icon  || 'fa-image';
        const color = type.color || 'var(--primary-color, #3b82f6)';
        return `<span class="ms-type-badge" style="background:${esc(color)};color:#fff;" title="${esc(type.name)}">
                    <i class="fas ${esc(icon)}" aria-hidden="true"></i> ${esc(type.name)}
                </span>`;
    }

    // ════════════════════════════════════════════════════════
    // LOAD DATA
    // ════════════════════════════════════════════════════════
    async function loadData(page = 1) {
        showState('loading');
        state.page = page;

        const params = new URLSearchParams({ page, limit: state.perPage, format: 'json', ...state.filters });

        try {
            const result = await apiFetch(`${API}?${params}`, {
                headers: { 'Accept': 'application/json' },
            });

            if (result.success && result.data?.data?.length) {
                state.items = result.data.data;
                showState('table');
                renderTable();
                renderPagination(result.data.meta || {});
            } else if (result.success) {
                state.items = [];
                showState('empty');
            } else {
                showState('error', result.message || t('error_loading', 'Failed to load'));
            }
        } catch (e) {
            console.error('[MediaStudio] loadData:', e);
            showState('error', e.message || t('error_loading', 'Failed to load'));
        }
    }

    // ════════════════════════════════════════════════════════
    // RENDER TABLE
    // ════════════════════════════════════════════════════════
    function renderTable() {
        const tbody = document.getElementById('imageTableBody');
        if (!tbody) return;

        tbody.innerHTML = state.items.map(item => {
            const date = item.created_at ? new Date(item.created_at).toLocaleDateString() : '—';

            // ✅ btn-primary للتعديل
            const editBtn = CFG.permissions?.canEdit
                ? `<button class="btn btn-sm btn-primary ms-edit-btn" data-id="${esc(item.id)}" aria-label="${t('edit','Edit')}">
                       <i class="fas fa-edit" aria-hidden="true"></i>
                   </button>`
                : '';
            const delBtn = CFG.permissions?.canDelete
                ? `<button class="btn btn-sm btn-danger ms-del-btn" data-id="${esc(item.id)}" aria-label="${t('delete','Delete')}">
                       <i class="fas fa-trash" aria-hidden="true"></i>
                   </button>`
                : '';

            const visBadge = item.visibility === 'public'
                ? `<span class="badge badge-active">${esc(item.visibility)}</span>`
                : `<span class="badge badge-secondary">${esc(item.visibility)}</span>`;

            return `
                <tr data-id="${esc(item.id)}">
                    <td><input type="checkbox" class="ms-checkbox" value="${esc(item.id)}" aria-label="Select"></td>
                    <td><img src="${esc(item.thumb_url || item.url)}" alt="${esc(item.filename || '')}" loading="lazy"></td>
                    <td>${esc(item.id)}</td>
                    <td>${esc(item.filename || '—')}</td>
                    <td>${esc(item.owner_id)}</td>
                    <td>${getImageTypeBadge(item.image_type_id)}</td>
                    <td>${visBadge}</td>
                    <td>
                        <label class="ms-toggle">
                            <input type="checkbox" class="ms-main-toggle" data-id="${esc(item.id)}" ${item.is_main == 1 ? 'checked' : ''}>
                            <span class="ms-toggle-slider"></span>
                        </label>
                    </td>
                    <td>${esc(item.sort_order ?? 0)}</td>
                    <td>${esc(date)}</td>
                    <td>
                        <div class="table-actions">
                            ${editBtn}
                            ${delBtn}
                        </div>
                    </td>
                </tr>`;
        }).join('');

        // Events
        tbody.querySelectorAll('.ms-main-toggle').forEach(toggle => {
            toggle.addEventListener('change', handleMainToggle);
        });
        tbody.querySelectorAll('.ms-edit-btn').forEach(b =>
            b.addEventListener('click', () => editImage(b.dataset.id)));
        tbody.querySelectorAll('.ms-del-btn').forEach(b =>
            b.addEventListener('click', () => deleteData(b.dataset.id)));
        tbody.querySelectorAll('.ms-checkbox').forEach(cb =>
            cb.addEventListener('change', updateSelectedItems));
    }

    // ════════════════════════════════════════════════════════
    // PAGINATION
    // ════════════════════════════════════════════════════════
    function renderPagination(meta) {
        const total      = meta.total || 0;
        const perPage    = meta.per_page || state.perPage;
        const totalPages = Math.max(1, Math.ceil(total / perPage));
        const start = total > 0 ? (state.page - 1) * perPage + 1 : 0;
        const end   = Math.min(state.page * perPage, total);

        const infoEl = document.getElementById('msPaginationInfo');
        if (infoEl) infoEl.textContent = total > 0 ? `${start}–${end} / ${total}` : t('no_records', 'No records');

        const pagEl = document.getElementById('msPagination');
        if (!pagEl) return;
        pagEl.innerHTML = '';
        if (totalPages <= 1) return;

        const makeBtn = (label, target, active = false, disabled = false) => {
            const btn = document.createElement('button');
            btn.className = 'pagination-btn' + (active ? ' active' : '');
            btn.innerHTML = label;
            btn.disabled  = disabled;
            if (!disabled) btn.addEventListener('click', () => loadData(target));
            return btn;
        };

        pagEl.appendChild(makeBtn('&laquo;', state.page - 1, false, state.page <= 1));
        for (let i = 1; i <= totalPages; i++) {
            if (i === 1 || i === totalPages || (i >= state.page - 2 && i <= state.page + 2)) {
                pagEl.appendChild(makeBtn(String(i), i, i === state.page, i === state.page));
            } else if (i === state.page - 3 || i === state.page + 3) {
                const sp = document.createElement('span');
                sp.className = 'pagination-dots';
                sp.textContent = '\u2026';
                pagEl.appendChild(sp);
            }
        }
        pagEl.appendChild(makeBtn('&raquo;', state.page + 1, false, state.page >= totalPages));
    }

    // ════════════════════════════════════════════════════════
    // SELECTION
    // ════════════════════════════════════════════════════════
    function updateSelectedItems() {
        const checked = document.querySelectorAll('#imageTableBody .ms-checkbox:checked');
        state.selectedItems = Array.from(checked).map(cb => parseInt(cb.value));

        // Delete selected button
        if (el.btnDeleteSelected) {
            el.btnDeleteSelected.style.display = state.selectedItems.length > 0 ? 'inline-flex' : 'none';
        }

        // Selection bar
        if (CFG.mode === 'select' && el.selectionBar) {
            el.selectionBar.classList.toggle('visible', state.selectedItems.length > 0);
            if (el.selectionCount) el.selectionCount.textContent = state.selectedItems.length;
        }

        // Row highlight
        document.querySelectorAll('#imageTableBody tr').forEach(tr => {
            const cb = tr.querySelector('.ms-checkbox');
            tr.classList.toggle('selected', cb?.checked);
        });
    }

    async function handleSelectionConfirm() {
        if (state.selectedItems.length === 0) {
            notify(t('no_items_selected_alert', 'Please select an image first'), 'error');
            return;
        }
        const selectedObjects = state.items.filter(item => state.selectedItems.includes(item.id));
        if (!selectedObjects.length) return;

        // Auto-assign if autoFill provided
        const newOwnerId = CFG.autoFill?.owner_id  ? parseInt(CFG.autoFill.owner_id)  : null;
        const newTypeId  = CFG.autoFill?.image_type_id ? parseInt(CFG.autoFill.image_type_id) : null;

        if (newOwnerId || newTypeId) {
            const updates = selectedObjects
                .filter(img => (newOwnerId && img.owner_id != newOwnerId) || (newTypeId && img.image_type_id != newTypeId))
                .map(img => {
                    const fd = new FormData();
                    fd.append('csrf_token', CFG.csrfToken || '');
                    fd.append('_method', 'PUT');
                    if (newOwnerId) fd.append('owner_id', newOwnerId);
                    if (newTypeId)  fd.append('image_type_id', newTypeId);
                    fd.append('tenant_id', CFG.tenantId);
                    return fetch(`${API}/${img.id}`, { method: 'POST', body: fd, credentials: 'same-origin' });
                });
            if (updates.length) await Promise.allSettled(updates);
        }

        const eventDetail = CFG.selectionLimit === 1 ? selectedObjects[0] : selectedObjects;
        window.dispatchEvent(new CustomEvent('ImageStudio:selected', { detail: eventDetail }));
        if (window.parent && window.parent !== window) {
            window.parent.dispatchEvent(new CustomEvent('ImageStudio:selected', { detail: eventDetail }));
            window.parent.dispatchEvent(new CustomEvent('ImageStudio:close', {}));
        } else {
            notify(t('selection_confirmed', 'Selection confirmed'), 'success');
        }
    }

    // ════════════════════════════════════════════════════════
    // ADD / UPLOAD FORM
    // ════════════════════════════════════════════════════════
    function showAddForm() {
        hideEditForm();
        if (!el.addImageContainer) return;

        if (CFG.autoFill) {
            if (el.uploadOwnerId)          el.uploadOwnerId.value          = CFG.autoFill.owner_id      || '';
            if (el.uploadImageTypeIdHidden)el.uploadImageTypeIdHidden.value = CFG.autoFill.image_type_id || '';
            if (el.uploadTenantId)         el.uploadTenantId.value         = CFG.autoFill.tenant_id     || CFG.tenantId;
            if (el.uploadUserId)           el.uploadUserId.value           = CFG.autoFill.user_id       || '';
        }

        if (el.uploadForm) el.uploadForm.reset();
        if (el.uploadFileList) { el.uploadFileList.innerHTML = ''; el.uploadFileList.style.display = 'none'; }
        switchAddTab('upload');
        el.addImageContainer.style.display = 'block';
        setTimeout(() => el.addImageContainer.scrollIntoView({ behavior: 'smooth', block: 'start' }), 50);
    }

    function hideAddForm() {
        if (el.addImageContainer) el.addImageContainer.style.display = 'none';
        if (el.uploadForm) el.uploadForm.reset();
        if (el.uploadFileList) { el.uploadFileList.innerHTML = ''; el.uploadFileList.style.display = 'none'; }
        exitStudioCopyMode();
    }

    function switchAddTab(tabName) {
        if (el.addTabUpload) el.addTabUpload.style.display = tabName === 'upload' ? 'block' : 'none';
        if (el.addTabStudio) el.addTabStudio.style.display = tabName === 'studio' ? 'block' : 'none';
        document.querySelectorAll('.ms-tab-btn').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.tab === tabName);
        });
    }

    // ════════════════════════════════════════════════════════
    // EDIT FORM
    // ════════════════════════════════════════════════════════
    function showEditForm(isEdit = false, data = null) {
        hideAddForm();
        if (!el.formContainer) return;

        if (el.imageForm) el.imageForm.reset();
        if (el.formId) el.formId.value = '';

        const title = document.getElementById('imageFormTitle');
        if (title) title.textContent = isEdit ? t('form_edit_title', 'Edit Image') : t('form_add_title', 'Add Image');
        if (el.btnDelete) el.btnDelete.style.display = isEdit ? 'inline-flex' : 'none';

        if (isEdit && data) {
            if (el.formId)       el.formId.value      = data.id;
            if (el.ownerId)      el.ownerId.value      = data.owner_id || '';
            setDisplayFromId('imageTypeIdHidden', 'imageTypeDisplay', 'imageTypesList', data.image_type_id);
            if (el.filename)     el.filename.value     = data.filename   || '';
            if (el.url)          el.url.value          = data.url        || '';
            if (el.thumbUrl)     el.thumbUrl.value     = data.thumb_url  || '';
            if (el.mimeType)     el.mimeType.value     = data.mime_type  || 'image/jpeg';
            if (el.visibility)   el.visibility.value   = data.visibility || 'private';
            if (el.isMain)       el.isMain.value       = data.is_main    ? '1' : '0';
            if (el.sortOrder)    el.sortOrder.value    = data.sort_order || 0;
            if (el.imageTenantId)el.imageTenantId.value = data.tenant_id || CFG.tenantId;
            if (el.imageUserId)  el.imageUserId.value  = data.user_id   || CFG.autoFill?.user_id || '';
        } else if (CFG.autoFill) {
            if (el.ownerId)       el.ownerId.value       = CFG.autoFill.owner_id   || '';
            if (el.imageTenantId) el.imageTenantId.value = CFG.autoFill.tenant_id  || CFG.tenantId;
            if (el.imageUserId)   el.imageUserId.value   = CFG.autoFill.user_id    || '';
            setDisplayFromId('imageTypeIdHidden', 'imageTypeDisplay', 'imageTypesList', CFG.autoFill.image_type_id || '');
        }

        el.formContainer.style.display = 'block';
        setTimeout(() => el.formContainer.scrollIntoView({ behavior: 'smooth', block: 'start' }), 50);
    }

    function hideEditForm() {
        if (el.formContainer) el.formContainer.style.display = 'none';
        if (el.imageForm) el.imageForm.reset();
    }

    async function editImage(id) {
        try {
            const result = await apiFetch(`${API}/${id}?format=json`);
            if (result.success && result.data) {
                showEditForm(true, result.data);
            } else {
                notify(t('alert_error', 'Error'), 'error');
            }
        } catch (e) {
            console.error('[MediaStudio] editImage:', e);
            notify(t('alert_error', 'Error'), 'error');
        }
    }

    // ════════════════════════════════════════════════════════
    // SAVE (edit form)
    // ════════════════════════════════════════════════════════
    async function saveData(e) {
        if (e) e.preventDefault();

        const idValue    = el.formId?.value?.trim() || '';
        const isEdit     = !!idValue;
        const ownerId    = parseInt(el.ownerId?.value || 0);
        const typeId     = parseInt(getIdFromDatalist('imageTypesList', el.imageTypeDisplay?.value) || 0);
        const urlValue   = el.url?.value?.trim() || '';

        if (!ownerId)  { notify(t('Owner ID is required', 'Owner ID is required'), 'error'); return; }
        if (!typeId)   { notify(t('Image type is required', 'Image type is required'), 'error'); return; }
        if (!urlValue) { notify(t('URL is required', 'URL is required'), 'error'); return; }

        const fd = new FormData();
        fd.append('csrf_token',    CFG.csrfToken || '');
        fd.append('owner_id',      ownerId);
        fd.append('image_type_id', typeId);
        fd.append('tenant_id',     parseInt(el.imageTenantId?.value || CFG.tenantId));
        fd.append('user_id',       parseInt(el.imageUserId?.value || 0));
        fd.append('url',           urlValue);
        if (el.thumbUrl?.value)  fd.append('thumb_url',  el.thumbUrl.value.trim());
        if (el.filename?.value)  fd.append('filename',   el.filename.value.trim());
        if (el.mimeType?.value)  fd.append('mime_type',  el.mimeType.value.trim());
        fd.append('visibility',    el.visibility?.value  || 'private');
        fd.append('is_main',       el.isMain?.value      || '0');
        fd.append('sort_order',    parseInt(el.sortOrder?.value || 0));

        let method = 'POST';
        let url    = API;
        if (isEdit) {
            fd.append('_method', 'PUT');
            url = `${API}/${parseInt(idValue)}`;
        }

        if (el.btnSaveImage) { el.btnSaveImage.disabled = true; el.btnSaveImage.innerHTML = '<i class="fas fa-spinner fa-spin"></i>'; }

        try {
            const result = await fetch(url, { method, body: fd, credentials: 'same-origin' }).then(r => r.json());
            notify(result.message || (isEdit ? t('alert_updated','Updated') : t('alert_added','Added')),
                   result.success ? 'success' : 'error');
            if (result.success) { hideEditForm(); await loadData(state.page); }
        } catch (err) {
            console.error('[MediaStudio] saveData:', err);
            notify(t('alert_error', 'Error'), 'error');
        } finally {
            if (el.btnSaveImage) {
                el.btnSaveImage.disabled = false;
                el.btnSaveImage.innerHTML = `<i class="fas fa-save" aria-hidden="true"></i> ${t('save_button','Save')}`;
            }
        }
    }

    // ════════════════════════════════════════════════════════
    // UPLOAD
    // ════════════════════════════════════════════════════════
    async function uploadData(e) {
        if (e) e.preventDefault();
        const files = el.uploadImages?.files;
        if (!files?.length) { notify(t('validation_select_files', 'Please select files'), 'error'); return; }

        const fd = new FormData();
        fd.append('csrf_token',    CFG.csrfToken || '');
        fd.append('owner_id',      parseInt(el.uploadOwnerId?.value || 0));
        fd.append('image_type_id', parseInt(el.uploadImageTypeIdHidden?.value || 0));
        fd.append('tenant_id',     parseInt(el.uploadTenantId?.value || CFG.tenantId));
        fd.append('user_id',       parseInt(el.uploadUserId?.value || 0));
        fd.append('visibility',    'public');
        for (const f of files) fd.append('images[]', f);

        if (el.btnUploadSave) { el.btnUploadSave.disabled = true; el.btnUploadSave.innerHTML = '<i class="fas fa-spinner fa-spin"></i>'; }

        try {
            const result = await fetch(API, { method: 'POST', body: fd, credentials: 'same-origin' }).then(r => r.json());
            notify(result.message || t('alert_uploaded', 'Uploaded'), result.success ? 'success' : 'error');
            if (result.success) { hideAddForm(); await loadData(state.page); }
        } catch (err) {
            console.error('[MediaStudio] uploadData:', err);
            notify(t('alert_error', 'Error'), 'error');
        } finally {
            if (el.btnUploadSave) {
                el.btnUploadSave.disabled = false;
                el.btnUploadSave.innerHTML = `<i class="fas fa-upload" aria-hidden="true"></i> ${t('upload_button','Upload')}`;
            }
        }
    }

    // ════════════════════════════════════════════════════════
    // DELETE
    // ════════════════════════════════════════════════════════
    async function deleteData(id) {
        if (!confirm(t('confirm_delete', 'Delete this image?'))) return;
        const result = await apiFetch(`${API}/${id}`, { method: 'DELETE' });
        notify(result.message || t('alert_deleted', 'Deleted'), result.success ? 'success' : 'error');
        if (result.success) { hideEditForm(); await loadData(state.page); }
    }

    async function deleteSelected() {
        if (!state.selectedItems.length) return;
        if (!confirm(t('confirm_delete_selected', 'Delete selected images?'))) return;
        await Promise.allSettled(
            state.selectedItems.map(id => apiFetch(`${API}/${id}`, { method: 'DELETE' }))
        );
        notify(t('alert_deleted_selected', 'Deleted selected'), 'success');
        state.selectedItems = [];
        await loadData(state.page);
    }

    async function handleMainToggle(e) {
        const toggle = e.target;
        const id     = toggle.dataset.id;
        const item   = state.items.find(i => i.id == id);
        if (!item) return;
        try {
            const result = await apiFetch(SET_MAIN_API, {
                method:  'POST',
                headers: { 'Content-Type': 'application/json' },
                body:    JSON.stringify({
                    image_id:      id,
                    owner_id:      item.owner_id,
                    image_type_id: item.image_type_id,
                    tenant_id:     CFG.tenantId,
                }),
            });
            if (!result.success) {
                toggle.checked = !toggle.checked;
                notify(result.message || t('alert_error', 'Error'), 'error');
            }
        } catch (_) {
            toggle.checked = !toggle.checked;
            notify(t('alert_error', 'Error'), 'error');
        }
    }

    // ════════════════════════════════════════════════════════
    // FILTERS
    // ════════════════════════════════════════════════════════
    function applyFilters() {
        state.filters = {};
        const fname = el.filterFilename?.value?.trim();
        if (fname) state.filters.q = fname;
        const typeId = el.filterTypeHidden?.value;
        if (typeId) state.filters.image_type_id = typeId;
        const owner = el.filterOwnerId?.value;
        if (owner) state.filters.owner_id = parseInt(owner);
        const vis = el.filterVisibility?.value;
        if (vis) state.filters.visibility = vis;
        loadData(1);
    }

    function resetFilters() {
        if (el.filterFilename)   el.filterFilename.value   = '';
        if (el.filterType)       el.filterType.value       = '';
        if (el.filterTypeHidden) el.filterTypeHidden.value = '';
        if (el.filterOwnerId)    el.filterOwnerId.value    = '';
        if (el.filterVisibility) el.filterVisibility.value = '';
        state.filters = {};
        loadData(1);
    }

    // ════════════════════════════════════════════════════════
    // STUDIO COPY MODE
    // ════════════════════════════════════════════════════════
    function enterStudioCopyMode() {
        state.studioCopyMode = true;
        if (el.addImageContainer) el.addImageContainer.style.display = 'none';
        if (el.studioCopyBar)     el.studioCopyBar.style.display     = 'flex';
        if (el.btnConfirmCopy)    el.btnConfirmCopy.disabled         = true;
        state.studioCopySelectedId = null;
        document.querySelectorAll('#imageTableBody tr').forEach(tr => tr.classList.remove('studio-copy-selected'));
    }

    function exitStudioCopyMode() {
        if (!state.studioCopyMode) return;
        state.studioCopyMode       = false;
        state.studioCopySelectedId = null;
        if (el.studioCopyBar) el.studioCopyBar.style.display = 'none';
        document.querySelectorAll('#imageTableBody tr').forEach(tr => tr.classList.remove('studio-copy-selected'));
    }

    async function confirmStudioCopy() {
        const srcId  = state.studioCopySelectedId;
        const srcImg = state.items.find(img => img.id === srcId);
        if (!srcId || !srcImg) { notify(t('no_items_selected_alert', 'Select an image first'), 'error'); return; }

        const fd = new FormData();
        fd.append('csrf_token',    CFG.csrfToken || '');
        fd.append('owner_id',      CFG.autoFill?.owner_id      || srcImg.owner_id);
        fd.append('image_type_id', CFG.autoFill?.image_type_id || srcImg.image_type_id);
        fd.append('tenant_id',     CFG.tenantId);
        fd.append('user_id',       CFG.autoFill?.user_id       || srcImg.user_id || 0);
        fd.append('url',           srcImg.url);
        fd.append('thumb_url',     srcImg.thumb_url   || '');
        fd.append('filename',      srcImg.filename    || '');
        fd.append('mime_type',     srcImg.mime_type   || 'image/jpeg');
        fd.append('size',          srcImg.size        || 0);
        fd.append('visibility',    srcImg.visibility  || 'private');
        fd.append('is_main',       srcImg.is_main     || 0);
        fd.append('sort_order',    srcImg.sort_order  || 0);

        if (el.btnConfirmCopy) { el.btnConfirmCopy.disabled = true; el.btnConfirmCopy.innerHTML = '<i class="fas fa-spinner fa-spin"></i>'; }

        const result = await fetch(API, { method: 'POST', body: fd, credentials: 'same-origin' }).then(r => r.json()).catch(() => ({}));

        if (el.btnConfirmCopy) {
            el.btnConfirmCopy.disabled = false;
            el.btnConfirmCopy.innerHTML = `<i class="fas fa-check" aria-hidden="true"></i> ${t('use_image','Use This Image')}`;
        }

        notify(result.message || (result.success ? t('alert_added','Added') : t('alert_error','Error')),
               result.success ? 'success' : 'error');
        if (result.success) { exitStudioCopyMode(); await loadData(state.page); }
    }

    // ════════════════════════════════════════════════════════
    // FILE LIST DISPLAY
    // ════════════════════════════════════════════════════════
    function updateFileList(files) {
        if (!el.uploadFileList) return;
        if (!files?.length) { el.uploadFileList.style.display = 'none'; el.uploadFileList.innerHTML = ''; return; }
        el.uploadFileList.style.display = 'block';
        el.uploadFileList.innerHTML = Array.from(files).map(f =>
            `<div class="ms-file-item"><i class="fas fa-image" aria-hidden="true"></i> <span>${esc(f.name)}</span> <small>(${Math.round(f.size / 1024)} KB)</small></div>`
        ).join('');
    }

    // ════════════════════════════════════════════════════════
    // INIT
    // ════════════════════════════════════════════════════════
    async function init() {
        el = {
            addImageContainer:     document.getElementById('addImageContainer'),
            addTabUpload:          document.getElementById('addTabUpload'),
            addTabStudio:          document.getElementById('addTabStudio'),
            studioCopyBar:         document.getElementById('studioCopyBar'),
            btnConfirmCopy:        document.getElementById('btnConfirmCopy'),
            btnCancelCopy:         document.getElementById('btnCancelCopy'),
            formContainer:         document.getElementById('imageFormContainer'),
            imageForm:             document.getElementById('imageForm'),
            uploadForm:            document.getElementById('uploadForm'),
            formId:                document.getElementById('imageId'),
            ownerId:               document.getElementById('imageOwnerId'),
            imageTypeDisplay:      document.getElementById('imageTypeDisplay'),
            imageTypeHidden:       document.getElementById('imageTypeIdHidden'),
            filename:              document.getElementById('imageFilename'),
            url:                   document.getElementById('imageUrl'),
            thumbUrl:              document.getElementById('imageThumbUrl'),
            mimeType:              document.getElementById('imageMimeType'),
            visibility:            document.getElementById('imageVisibility'),
            isMain:                document.getElementById('imageIsMain'),
            sortOrder:             document.getElementById('imageSortOrder'),
            imageTenantId:         document.getElementById('imageTenantId'),
            imageUserId:           document.getElementById('imageUserId'),
            uploadOwnerId:         document.getElementById('uploadOwnerId'),
            uploadImageTypeIdHidden:document.getElementById('uploadImageTypeIdHidden'),
            uploadTenantId:        document.getElementById('uploadTenantId'),
            uploadUserId:          document.getElementById('uploadUserId'),
            uploadImages:          document.getElementById('uploadImages'),
            uploadDropZone:        document.getElementById('uploadDropZone'),
            uploadFileList:        document.getElementById('uploadFileList'),
            btnSaveImage:          document.getElementById('btnSaveImage'),
            btnUploadSave:         document.getElementById('btnUploadSave'),
            btnCancelImageForm:    document.getElementById('btnCancelImageForm'),
            btnCancelUploadForm:   document.getElementById('btnCancelUploadForm'),
            btnDelete:             document.getElementById('btnDeleteImage'),
            btnCloseImageForm:     document.getElementById('btnCloseImageForm'),
            btnCloseAddForm:       document.getElementById('btnCloseAddForm'),
            btnEnterStudioCopy:    document.getElementById('btnEnterStudioCopy'),
            btnCancelStudioTab:    document.getElementById('btnCancelStudioTab'),
            btnSelectConfirm:      document.getElementById('btnSelectConfirm'),
            btnConfirmSelectionBar:document.getElementById('btnConfirmSelectionBar'),
            selectionBar:          document.getElementById('selectionBar'),
            selectionCount:        document.getElementById('selectionCount'),
            selectAll:             document.getElementById('selectAllImages'),
            filterFilename:        document.getElementById('imageFilterFilename'),
            filterType:            document.getElementById('imageFilterType'),
            filterTypeHidden:      document.getElementById('imageFilterTypeHidden'),
            filterOwnerId:         document.getElementById('imageFilterOwnerId'),
            filterVisibility:      document.getElementById('imageFilterVisibility'),
            btnApply:              document.getElementById('btnApplyImageFilters'),
            btnReset:              document.getElementById('btnResetImageFilters'),
            btnDeleteSelected:     document.getElementById('btnDeleteSelected'),
            btnAddImageEmpty:      document.getElementById('btnAddImageEmpty'),
            btnRetry:              document.getElementById('btnRetryImages'),
        };

        // ESC closes forms
        document.addEventListener('keydown', e => {
            if (e.key !== 'Escape') return;
            if (el.addImageContainer?.style.display !== 'none') { hideAddForm(); return; }
            if (el.formContainer?.style.display !== 'none') { hideEditForm(); return; }
            exitStudioCopyMode();
        });

        // Form events
        if (el.imageForm)           el.imageForm.addEventListener('submit', saveData);
        if (el.uploadForm)          el.uploadForm.addEventListener('submit', uploadData);
        if (el.btnCancelImageForm)  el.btnCancelImageForm.onclick  = hideEditForm;
        if (el.btnCloseImageForm)   el.btnCloseImageForm.onclick   = hideEditForm;
        if (el.btnCancelUploadForm) el.btnCancelUploadForm.onclick = hideAddForm;
        if (el.btnCloseAddForm)     el.btnCloseAddForm.onclick     = hideAddForm;
        if (el.btnCancelStudioTab)  el.btnCancelStudioTab.onclick  = hideAddForm;
        if (el.btnEnterStudioCopy)  el.btnEnterStudioCopy.onclick  = enterStudioCopyMode;
        if (el.btnConfirmCopy)      el.btnConfirmCopy.onclick      = confirmStudioCopy;
        if (el.btnCancelCopy)       el.btnCancelCopy.onclick       = exitStudioCopyMode;
        if (el.btnDelete)           el.btnDelete.onclick = () => { if (el.formId?.value) deleteData(el.formId.value); };

        // Header / filter buttons
        document.querySelectorAll('#btnAddImage, #btnAddImageEmpty').forEach(b =>
            b?.addEventListener('click', showAddForm));
        if (el.btnApply)  el.btnApply.onclick   = applyFilters;
        if (el.btnReset)  el.btnReset.onclick   = resetFilters;
        if (el.btnRetry)  el.btnRetry.onclick   = () => loadData(state.page);
        if (el.btnDeleteSelected) el.btnDeleteSelected.onclick = deleteSelected;

        // Selection
        if (el.btnSelectConfirm)      el.btnSelectConfirm.onclick      = handleSelectionConfirm;
        if (el.btnConfirmSelectionBar)el.btnConfirmSelectionBar.onclick = handleSelectionConfirm;
        if (el.selectAll) {
            el.selectAll.addEventListener('change', () => {
                document.querySelectorAll('#imageTableBody .ms-checkbox').forEach(cb => {
                    cb.checked = el.selectAll.checked;
                });
                updateSelectedItems();
            });
        }

        // Tabs
        document.querySelectorAll('.ms-tab-btn').forEach(btn => {
            btn.addEventListener('click', () => switchAddTab(btn.dataset.tab));
        });

        // Drag & drop
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
        if (el.uploadImages) el.uploadImages.addEventListener('change', () => updateFileList(el.uploadImages.files));

        // Image type datalist
        if (el.imageTypeDisplay) {
            el.imageTypeDisplay.addEventListener('input', function () {
                const id = getIdFromDatalist('imageTypesList', this.value);
                if (el.imageTypeHidden) el.imageTypeHidden.value = id || '';
            });
        }
        if (el.filterType) {
            el.filterType.addEventListener('input', function () {
                const id = getIdFromDatalist('filterImageTypesList', this.value);
                if (el.filterTypeHidden) el.filterTypeHidden.value = id || '';
            });
        }

        // Table delegation — studio copy + row click in select mode
        document.getElementById('imageTableBody')?.addEventListener('click', e => {
            if (state.studioCopyMode
                && !e.target.closest('button')
                && !e.target.closest('input')
                && !e.target.closest('a')) {
                const tr = e.target.closest('tr');
                if (tr) {
                    document.querySelectorAll('#imageTableBody tr').forEach(r => r.classList.remove('studio-copy-selected'));
                    tr.classList.add('studio-copy-selected');
                    state.studioCopySelectedId = parseInt(tr.dataset.id);
                    if (el.btnConfirmCopy) el.btnConfirmCopy.disabled = false;
                }
                return;
            }
            if (CFG.mode === 'select'
                && !e.target.closest('button')
                && !e.target.closest('input')
                && !e.target.closest('a')) {
                const tr = e.target.closest('tr');
                if (tr) {
                    const cb = tr.querySelector('.ms-checkbox');
                    if (cb) {
                        cb.checked = !cb.checked;
                        if (cb.checked && CFG.selectionLimit === 1) {
                            document.querySelectorAll('#imageTableBody .ms-checkbox').forEach(c => {
                                if (c !== cb) c.checked = false;
                            });
                        }
                        updateSelectedItems();
                    }
                }
            }
        });

        // Embedded mode setup
        if (CFG.embedded) {
            document.body.classList.add('embedded-mode');
            if (CFG.mode === 'select' && el.btnSelectConfirm) {
                el.btnSelectConfirm.style.display = 'inline-flex';
            }
            if (CFG.action === 'add' || CFG.action === 'upload') showAddForm();
        }

        await loadImageTypes();
        await loadData();
    }

    // ════════════════════════════════════════════════════════
    // REGISTER
    // ════════════════════════════════════════════════════════
    window.MediaStudio = { init, load: loadData, add: showAddForm, edit: editImage, remove: deleteData };
    window.page = { run: init };

    if (window.Admin?.page?.register) {
        window.Admin.page.register('media_studio', init);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

}());