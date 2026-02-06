(function () {
    'use strict';

    const API = window.API_IMAGES;
    const CSRF = window.CSRF;
    const USER_ID = window.USER_ID;
    const TENANT_ID = window.TENANT_ID;
    const IMAGE_TYPES = window.IMAGE_TYPES || [];
    const TRANSLATIONS = window.TRANSLATIONS || {};

    function t(key, fallback = key) {
        return TRANSLATIONS[key] || fallback;
    }

    // DOM Elements
    const grid = document.getElementById('mediaGrid');
    const uploadInput = document.getElementById('uploadInput');
    const uploadBtn = document.getElementById('uploadBtn');
    const uploadSubmitBtn = document.getElementById('uploadSubmitBtn');
    const deleteBtn = document.getElementById('deleteBtn');
    const cropBtn = document.getElementById('cropBtn');
    const downloadBtn = document.getElementById('downloadBtn');
    const pasteBtn = document.getElementById('pasteBtn');
    const useBtn = document.getElementById('useBtn');
    const searchInput = document.getElementById('searchInput');
    const imageTypeFilter = document.getElementById('imageTypeFilter');
    const ownerIdFilter = document.getElementById('ownerIdFilter');
    const visibilityFilter = document.getElementById('visibilityFilter');
    const applyFilterBtn = document.getElementById('applyFilterBtn');
    const paginationControls = document.getElementById('paginationControls');
    const prevPageBtn = document.getElementById('prevPage');
    const nextPageBtn = document.getElementById('nextPage');
    const pageInfo = document.getElementById('pageInfo');
    const cropModal = document.getElementById('cropModal');
    const cropImage = document.getElementById('cropImage');
    const cropSaveBtn = document.getElementById('cropSaveBtn');
    const cropCancelBtn = document.getElementById('cropCancelBtn');
    const cropClose = document.getElementById('cropClose');
    const preview = document.getElementById('preview');
    const previewImg = document.getElementById('previewImg');
    const loading = document.getElementById('loading');
    
    // Crop controls
    const aspectRatio = document.getElementById('aspectRatio');
    const cropSize = document.getElementById('cropSize');
    const cropRotateLeft = document.getElementById('cropRotateLeft');
    const cropRotateRight = document.getElementById('cropRotateRight');
    const cropFlipHorizontal = document.getElementById('cropFlipHorizontal');
    const cropFlipVertical = document.getElementById('cropFlipVertical');

    // Upload fields
    const uploadOwnerId = document.getElementById('uploadOwnerId');
    const uploadImageTypeId = document.getElementById('uploadImageTypeId');
    const uploadVisibility = document.getElementById('uploadVisibility');

    let selected = new Set();
    let currentPage = 1;
    let totalPages = 1;
    let totalItems = 0;
    let cropper = null;
    let currentCropId = null;
    let currentFilters = {};
    let sortable = null;

    // Initialize
    function init() {
        loadMedia();
        setupEventListeners();
        setupDragAndDrop();
        updateButtons();
    }

    // Setup event listeners
    function setupEventListeners() {
        // Upload
        uploadBtn.addEventListener('click', () => uploadInput.click());
        uploadSubmitBtn.addEventListener('click', handleUpload);
        uploadInput.addEventListener('change', handleUpload);

        // Actions
        deleteBtn.addEventListener('click', handleDelete);
        cropBtn.addEventListener('click', handleCrop);
        downloadBtn.addEventListener('click', handleDownload);
        pasteBtn.addEventListener('click', handlePaste);
        useBtn.addEventListener('click', handleUse);

        // Filters
        applyFilterBtn.addEventListener('click', () => loadMedia(1));
        searchInput.addEventListener('input', debounce(() => loadMedia(1), 500));

        // Crop controls
        aspectRatio.addEventListener('change', updateCropAspectRatio);
        cropSize.addEventListener('change', updateCropSize);
        cropRotateLeft.addEventListener('click', () => cropper && cropper.rotate(-45));
        cropRotateRight.addEventListener('click', () => cropper && cropper.rotate(45));
        cropFlipHorizontal.addEventListener('click', () => cropper && cropper.scaleX(-cropper.getData().scaleX || -1));
        cropFlipVertical.addEventListener('click', () => cropper && cropper.scaleY(-cropper.getData().scaleY || -1));

        // Crop modal
        cropSaveBtn.addEventListener('click', handleCropSave);
        cropCancelBtn.addEventListener('click', closeCropModal);
        cropClose.addEventListener('click', closeCropModal);

        // Inline edit
        grid.addEventListener('click', (e) => {
            if (e.target.classList.contains('edit-btn')) {
                e.stopPropagation();
                toggleInlineEdit(e.target.dataset.id);
            } else if (e.target.classList.contains('save-edit-btn')) {
                e.stopPropagation();
                handleInlineEditSave(e.target.dataset.id);
            } else if (e.target.classList.contains('cancel-edit-btn')) {
                e.stopPropagation();
                toggleInlineEdit(e.target.dataset.id);
            } else if (e.target.classList.contains('set-main-btn')) {
                e.stopPropagation();
                handleSetMain(e.target.dataset.id);
            }
        });

        // Pagination
        if (prevPageBtn) prevPageBtn.addEventListener('click', () => loadMedia(currentPage - 1));
        if (nextPageBtn) nextPageBtn.addEventListener('click', () => loadMedia(currentPage + 1));

        // Close modal when clicking outside
        window.addEventListener('click', (e) => {
            if (e.target === cropModal) closeCropModal();
        });
    }

    // Setup drag and drop for sorting
    function setupDragAndDrop() {
        if (typeof Sortable !== 'undefined') {
            sortable = new Sortable(grid, {
                animation: 150,
                ghostClass: 'sortable-ghost',
                chosenClass: 'sortable-chosen',
                onEnd: handleSortEnd
            });
        }
    }

    // Handle sort end
    async function handleSortEnd(evt) {
        const items = Array.from(grid.children);
        for (let i = 0; i < items.length; i++) {
            const item = items[i];
            const id = item.dataset.id;
            const fd = new FormData();
            fd.append('id', id);
            fd.append('sort_order', i);
            fd.append('tenant_id', TENANT_ID);
            fd.append('csrf_token', CSRF);
            
            try {
                await fetch(API, { method: 'POST', body: fd });
            } catch (err) {
                console.error('Sort update error:', err);
            }
        }
        loadMedia(currentPage); // Refresh to show updated order
    }

    // Load media with pagination and filters
    async function loadMedia(page = 1) {
        currentPage = page;
        showLoading(true);
        
        currentFilters = {
            tenant_id: TENANT_ID,
            page: currentPage,
            limit: 20,
            q: searchInput.value.trim(),
            image_type_id: imageTypeFilter.value,
            owner_id: ownerIdFilter.value,
            visibility: visibilityFilter.value
        };

        const params = new URLSearchParams();
        Object.entries(currentFilters).forEach(([key, value]) => {
            if (value) params.append(key, value);
        });

        try {
            const res = await fetch(`${API}?${params}`);
            if (!res.ok) {
                throw new Error(`HTTP ${res.status}: ${res.statusText}`);
            }
            const data = await res.json();
            
            if (data.success) {
                renderGrid(data.data || []);
                totalItems = data.meta?.total || 0;
                totalPages = Math.ceil(totalItems / 20);
                updatePagination();
            } else {
                throw new Error(data.message || 'فشل تحميل الصور');
            }
        } catch (err) {
            console.error('خطأ في التحميل:', err);
            grid.innerHTML = `<div class="empty">خطأ في تحميل الصور: ${err.message}</div>`;
        } finally {
            showLoading(false);
        }
    }

    // Render grid
    function renderGrid(media) {
        grid.innerHTML = '';
        
        if (!media.length) {
            grid.innerHTML = `<div class="empty">لا توجد صور</div>`;
            return;
        }
        
        media.forEach(m => {
            const isSelected = selected.has(m.id);
            const item = document.createElement('div');
            item.className = `item ${isSelected ? 'selected' : ''} ${m.is_main ? 'main' : ''}`;
            item.dataset.id = m.id;
            item.dataset.url = m.url;
            item.dataset.filename = m.filename || '';
            item.dataset.thumb = m.thumb_url || m.url;
            item.dataset.ownerId = m.owner_id || '';
            item.dataset.imageTypeId = m.image_type_id || '';
            item.dataset.visibility = m.visibility || '';
            item.dataset.isMain = m.is_main || 0;
            item.dataset.sortOrder = m.sort_order || 0;
            item.dataset.mimeType = m.mime_type || '';
            item.dataset.size = m.size || 0;
            
            item.innerHTML = `
                <img src="${m.thumb_url || m.url}" alt="${m.filename || ''}" 
                     onerror="this.src='${m.url}'; this.onerror=null;">
                <div class="info">
                    ${ (m.filename || 'صورة').substring(0, 15) }${ (m.filename || '').length > 15 ? '...' : '' }<br>
                    <small>${m.image_type_name || ''} | ${m.visibility} | رئيسي: ${m.is_main ? 'نعم' : 'لا'} | ${ (m.size / 1024).toFixed(2) } KB</small>
                </div>
                <div class="actions">
                    <button class="edit-btn" data-id="${m.id}" title="تعديل">✏️</button>
                    <button class="set-main-btn" data-id="${m.id}" title="تعيين كرئيسي">⭐</button>
                </div>
                <div class="inline-edit" id="edit-${m.id}">
                    <div class="form-group">
                        <label>معرف المالك:</label>
                        <input type="number" id="edit-owner-${m.id}" value="${m.owner_id}">
                    </div>
                    <div class="form-group">
                        <label>نوع الصورة:</label>
                        <select id="edit-type-${m.id}">
                            <option value="">اختر</option>
                            ${IMAGE_TYPES.map(type => `<option value="${type.id}" ${m.image_type_id == type.id ? 'selected' : ''}>${type.name} - ${type.description}</option>`).join('')}
                        </select>
                    </div>
                    <div class="form-group">
                        <label>الاسم:</label>
                        <input type="text" id="edit-filename-${m.id}" value="${m.filename || ''}">
                    </div>
                    <div class="form-group">
                        <label>الظهور:</label>
                        <select id="edit-visibility-${m.id}">
                            <option value="private" ${m.visibility == 'private' ? 'selected' : ''}>خاص</option>
                            <option value="public" ${m.visibility == 'public' ? 'selected' : ''}>عام</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>رئيسي:</label>
                        <select id="edit-main-${m.id}">
                            <option value="0" ${!m.is_main ? 'selected' : ''}>لا</option>
                            <option value="1" ${m.is_main ? 'selected' : ''}>نعم</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>ترتيب:</label>
                        <input type="number" id="edit-order-${m.id}" value="${m.sort_order}">
                    </div>
                    <button class="save-edit-btn btn" data-id="${m.id}">حفظ</button>
                    <button class="cancel-edit-btn btn danger" data-id="${m.id}">إلغاء</button>
                </div>
            `;
            
            item.addEventListener('click', (e) => {
                if (!e.target.classList.contains('edit-btn') && !e.target.classList.contains('save-edit-btn') && !e.target.classList.contains('cancel-edit-btn') && !e.target.classList.contains('set-main-btn')) {
                    toggleSelect(item, m.id);
                }
            });
            
            grid.appendChild(item);
        });
    }

    // Toggle select
    function toggleSelect(el, id) {
        if (selected.has(id)) {
            selected.delete(id);
            el.classList.remove('selected');
        } else {
            selected.add(id);
            el.classList.add('selected');
        }
        
        updateButtons();
        updatePreview();
    }

    // Update preview
    function updatePreview() {
        if (selected.size === 1) {
            const selectedId = Array.from(selected)[0];
            const item = document.querySelector(`[data-id="${selectedId}"]`);
            if (item) {
                previewImg.src = item.dataset.thumb;
                preview.style.display = 'block';
                return;
            }
        }
        preview.style.display = 'none';
    }

    // Update buttons visibility
    function updateButtons() {
        const hasSelected = selected.size > 0;
        deleteBtn.style.display = hasSelected ? 'inline-block' : 'none';
        cropBtn.style.display = selected.size === 1 ? 'inline-block' : 'none';
        downloadBtn.style.display = hasSelected ? 'inline-block' : 'none';
        useBtn.style.display = hasSelected ? 'inline-block' : 'none';
    }

    // Update pagination
    function updatePagination() {
        if (!paginationControls) return;
        
        if (pageInfo) pageInfo.textContent = `${currentPage} / ${totalPages}`;
        if (prevPageBtn) {
            prevPageBtn.disabled = currentPage <= 1;
            prevPageBtn.style.opacity = currentPage <= 1 ? '0.5' : '1';
        }
        if (nextPageBtn) {
            nextPageBtn.disabled = currentPage >= totalPages;
            nextPageBtn.style.opacity = currentPage >= totalPages ? '0.5' : '1';
        }
    }

    // Handle upload
    async function handleUpload() {
        if (!uploadInput.files.length) {
            alert('اختر ملفات');
            return;
        }

        const ownerId = uploadOwnerId.value.trim();
        const imageTypeId = uploadImageTypeId.value;
        
        const fd = new FormData();
        for (let file of uploadInput.files) {
            fd.append('files[]', file);
        }
        if (ownerId) fd.append('owner_id', ownerId);
        if (imageTypeId) fd.append('image_type_id', imageTypeId);
        fd.append('tenant_id', TENANT_ID);
        fd.append('visibility', uploadVisibility.value);
        fd.append('csrf_token', CSRF);

        await performUpload(fd);
    }

    // Perform upload
    async function performUpload(formData) {
        showLoading(true);
        
        try {
            const res = await fetch(API, { 
                method: 'POST', 
                body: formData 
            });
            if (!res.ok) {
                throw new Error(`HTTP ${res.status}: ${res.statusText}`);
            }
            const data = await res.json();
            
            if (data.success) {
                alert('تم الرفع بنجاح');
                uploadInput.value = '';
                uploadOwnerId.value = '';
                uploadImageTypeId.value = '';
                uploadVisibility.value = 'private';
                loadMedia(currentPage);
            } else {
                throw new Error(data.message || 'فشل الرفع');
            }
        } catch (err) {
            console.error('خطأ في الرفع:', err);
            alert(`خطأ في الرفع: ${err.message}`);
        } finally {
            showLoading(false);
        }
    }

    // Handle delete
    async function handleDelete() {
        if (!selected.size) return;
        
        if (!confirm(`حذف ${selected.size} صورة محددة؟`)) return;
        
        showLoading(true);
        
        try {
            const res = await fetch(API, {
                method: 'DELETE',
                headers: { 
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': CSRF
                },
                body: JSON.stringify({ 
                    ids: Array.from(selected),
                    tenant_id: TENANT_ID
                })
            });
            if (!res.ok) {
                throw new Error(`HTTP ${res.status}: ${res.statusText}`);
            }
            const data = await res.json();
            
            if (data.success) {
                alert('تم حذف الصور بنجاح');
                selected.clear();
                updateButtons();
                loadMedia(currentPage);
            } else {
                throw new Error(data.message || 'فشل الحذف');
            }
        } catch (err) {
            console.error('خطأ في الحذف:', err);
            alert(`خطأ في الحذف: ${err.message}`);
        } finally {
            showLoading(false);
        }
    }

    // Handle crop
    function handleCrop() {
        if (selected.size !== 1) return;
        
        const selectedId = Array.from(selected)[0];
        const item = document.querySelector(`[data-id="${selectedId}"]`);
        if (!item) return;
        
        currentCropId = selectedId;
        cropImage.src = item.dataset.url;
        cropModal.style.display = 'flex';
        setTimeout(() => cropModal.classList.add('show'), 10);
        
        if (cropper) cropper.destroy();
        
        cropper = new Cropper(cropImage, {
            aspectRatio: NaN,
            viewMode: 2,
            dragMode: 'crop',
            autoCropArea: 1,
            responsive: true,
            restore: false,
            checkCrossOrigin: false,
            checkOrientation: true,
            modal: true,
            guides: true,
            center: true,
            highlight: false,
            background: true,
            cropBoxMovable: true,
            cropBoxResizable: true,
            toggleDragModeOnDblclick: true,
            minContainerWidth: 200,
            minContainerHeight: 100,
            minCanvasWidth: 50,
            minCanvasHeight: 50,
            minCropBoxWidth: 10,
            minCropBoxHeight: 10,
            ready() {
                console.log('Cropper جاهز');
            },
            crop(event) {
                // معاينة اختيارية
            }
        });
    }

    // Update crop aspect ratio
    function updateCropAspectRatio() {
        if (!cropper) return;
        
        const value = aspectRatio.value;
        if (value === 'NaN') {
            cropper.setAspectRatio(NaN);
        } else {
            cropper.setAspectRatio(eval(value));
        }
    }

    // Update crop size
    function updateCropSize() {
        if (!cropper) return;
        
        const value = cropSize.value;
        if (value) {
            const [width, height] = value.split('x').map(Number);
            cropper.setData({
                width: width,
                height: height
            });
        }
    }

    // Handle crop save
    async function handleCropSave() {
        if (!cropper || !currentCropId) return;
        
        showLoading(true);
        
        try {
            const canvas = cropper.getCroppedCanvas({
                width: 1024,
                height: 768,
                fillColor: '#fff',
                imageSmoothingEnabled: true,
                imageSmoothingQuality: 'high'
            });
            
            canvas.toBlob(async (blob) => {
                const fd = new FormData();
                fd.append('file', blob, `cropped_${Date.now()}.png`);
                fd.append('id', currentCropId);
                fd.append('tenant_id', TENANT_ID);
                fd.append('action', 'crop');
                fd.append('csrf_token', CSRF);
                
                const res = await fetch(API, { 
                    method: 'POST', 
                    body: fd 
                });
                if (!res.ok) {
                    throw new Error(`HTTP ${res.status}: ${res.statusText}`);
                }
                const data = await res.json();
                
                if (data.success) {
                    alert('تم قص الصورة بنجاح');
                    closeCropModal();
                    loadMedia(currentPage);
                } else {
                    throw new Error(data.message || 'فشل القص');
                }
            }, 'image/png', 0.95);
            
        } catch (err) {
            console.error('خطأ في القص:', err);
            alert(`خطأ في القص: ${err.message}`);
        } finally {
            showLoading(false);
        }
    }

    // Close crop modal
    function closeCropModal() {
        cropModal.classList.remove('show');
        setTimeout(() => cropModal.style.display = 'none', 300);
        if (cropper) {
            cropper.destroy();
            cropper = null;
        }
        currentCropId = null;
    }

    // Handle download
    async function handleDownload() {
        if (!selected.size) return;
        
        showLoading(true);
        
        try {
            const urls = Array.from(selected).map(id => {
                const item = document.querySelector(`[data-id="${id}"]`);
                return item ? item.dataset.url : null;
            }).filter(Boolean);
            
            if (urls.length === 1) {
                const a = document.createElement('a');
                a.href = urls[0];
                a.download = urls[0].split('/').pop() || 'image.png';
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
            } else {
                const zip = new JSZip();
                const promises = urls.map(async (url, index) => {
                    try {
                        const response = await fetch(url);
                        if (!response.ok) throw new Error(`HTTP ${response.status}`);
                        const blob = await response.blob();
                        const filename = url.split('/').pop() || `image_${index + 1}.png`;
                        zip.file(filename, blob);
                    } catch (error) {
                        console.error(`فشل تحميل ${url}:`, error);
                    }
                });
                
                await Promise.all(promises);
                const zipBlob = await zip.generateAsync({ 
                    type: 'blob',
                    compression: 'DEFLATE',
                    compressionOptions: { level: 6 }
                });
                
                const zipUrl = URL.createObjectURL(zipBlob);
                const a = document.createElement('a');
                a.href = zipUrl;
                a.download = 'images.zip';
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                
                setTimeout(() => URL.revokeObjectURL(zipUrl), 1000);
            }
        } catch (err) {
            console.error('خطأ في التحميل:', err);
            alert(`خطأ في التحميل: ${err.message}`);
        } finally {
            showLoading(false);
        }
    }

    // Handle paste
    async function handlePaste() {
        try {
            const clipboardItems = await navigator.clipboard.read();
            let imagesFound = false;
            
            for (const item of clipboardItems) {
                for (const type of item.types) {
                    if (type.startsWith('image/')) {
                        imagesFound = true;
                        const blob = await item.getType(type);
                        const fd = new FormData();
                        fd.append('files[]', blob, `pasted_${Date.now()}.${type.split('/')[1]}`);
                        fd.append('owner_id', uploadOwnerId.value || '0');
                        fd.append('image_type_id', uploadImageTypeId.value || '4');
                        fd.append('tenant_id', TENANT_ID);
                        fd.append('visibility', uploadVisibility.value || 'private');
                        fd.append('csrf_token', CSRF);
                        
                        await performUpload(fd);
                    }
                }
            }
            
            if (!imagesFound) {
                alert('لا توجد صور في الحافظة');
            }
        } catch (err) {
            console.error('خطأ في اللصق:', err);
            alert('فشل في اللصق من الحافظة. تأكد من السماح بصلاحيات الحافظة.');
        }
    }

    // Handle use selected images
    async function handleUse() {
        if (!selected.size) return;
        
        let ownerId = ownerIdFilter.value || uploadOwnerId.value;
        let imageTypeId = imageTypeFilter.value || uploadImageTypeId.value;
        
        if (!ownerId) ownerId = prompt('أدخل معرف المالك للصور:');
        if (!imageTypeId) imageTypeId = prompt('أدخل معرف نوع الصور للصور:');
        
        if (!ownerId || !imageTypeId) {
            alert('معرف المالك ومعرف نوع الصور مطلوبان');
            return;
        }
        
        showLoading(true);
        
        try {
            const selectedIds = Array.from(selected);
            
            for (let i = 0; i < selectedIds.length; i++) {
                const id = selectedIds[i];
                const fd = new FormData();
                fd.append('id', id);
                fd.append('owner_id', ownerId);
                fd.append('image_type_id', imageTypeId);
                fd.append('is_main', i === 0 ? '1' : '0');
                fd.append('tenant_id', TENANT_ID);
                fd.append('csrf_token', CSRF);
                
                const res = await fetch(API, { method: 'POST', body: fd });
                if (!res.ok) {
                    throw new Error(`فشل تحديث الصورة ${id}: HTTP ${res.status}`);
                }
                const data = await res.json();
                if (!data.success) {
                    throw new Error(`فشل تحديث الصورة ${id}: ${data.message}`);
                }
            }
            
            alert('تم تعيين وتحديث الصور بنجاح');
            selected.clear();
            updateButtons();
            loadMedia(currentPage);
            
            const items = selectedIds.map(id => {
                const item = document.querySelector(`[data-id="${id}"]`);
                return item ? {
                    id: id,
                    url: item.dataset.url,
                    filename: item.dataset.filename,
                    thumb_url: item.dataset.thumb
                } : null;
            }).filter(Boolean);
            
            if (window.opener) {
                window.opener.postMessage({ 
                    type: 'imagesSelected', 
                    data: { items } 
                }, '*');
            }
            
            window.dispatchEvent(new CustomEvent('imagesSelected', { 
                detail: { items } 
            }));
            
        } catch (err) {
            console.error('خطأ في الاستخدام:', err);
            alert(`خطأ في الاستخدام: ${err.message}`);
        } finally {
            showLoading(false);
        }
    }

    // Toggle inline edit
    function toggleInlineEdit(id) {
        const editDiv = document.getElementById(`edit-${id}`);
        if (editDiv) {
            editDiv.classList.toggle('show');
        }
    }

    // Handle inline edit save
    async function handleInlineEditSave(id) {
        const ownerId = document.getElementById(`edit-owner-${id}`).value;
        const imageTypeId = document.getElementById(`edit-type-${id}`).value;
        const filename = document.getElementById(`edit-filename-${id}`).value;
        const visibility = document.getElementById(`edit-visibility-${id}`).value;
        const isMain = document.getElementById(`edit-main-${id}`).value;
        const sortOrder = document.getElementById(`edit-order-${id}`).value;
        
        const fd = new FormData();
        fd.append('id', id);
        fd.append('owner_id', ownerId);
        fd.append('image_type_id', imageTypeId);
        fd.append('filename', filename);
        fd.append('visibility', visibility);
        fd.append('is_main', isMain);
        fd.append('sort_order', sortOrder);
        fd.append('tenant_id', TENANT_ID);
        fd.append('csrf_token', CSRF);
        
        showLoading(true);
        
        try {
            const res = await fetch(API, {
                method: 'POST',
                body: fd
            });
            if (!res.ok) {
                throw new Error(`HTTP ${res.status}: ${res.statusText}`);
            }
            const data = await res.json();
            
            if (data.success) {
                alert('تم تحديث الصورة بنجاح');
                toggleInlineEdit(id);
                loadMedia(currentPage);
            } else {
                throw new Error(data.message || 'فشل التحديث');
            }
        } catch (err) {
            console.error('خطأ في التعديل:', err);
            alert(`خطأ في التعديل: ${err.message}`);
        } finally {
            showLoading(false);
        }
    }

    // Handle set main
    async function handleSetMain(id) {
        const item = document.querySelector(`[data-id="${id}"]`);
        if (!item) return;
        
        const ownerId = item.dataset.ownerId;
        const imageTypeId = item.dataset.imageTypeId;
        
        if (!ownerId || !imageTypeId) {
            alert('معرف المالك ومعرف نوع الصور مطلوبان للتعيين كرئيسي');
            return;
        }
        
        if (!confirm('تعيين هذه الصورة كرئيسية؟ سيتم إلغاء الرئيسية للصور الأخرى لنفس المالك والنوع.')) return;
        
        showLoading(true);
        
        try {
            const res = await fetch(API, {
                method: 'POST',
                headers: { 
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': CSRF
                },
                body: JSON.stringify({ 
                    action: 'set_main',
                    owner_id: ownerId,
                    image_type_id: imageTypeId,
                    image_id: id,
                    tenant_id: TENANT_ID
                })
            });
            if (!res.ok) {
                throw new Error(`HTTP ${res.status}: ${res.statusText}`);
            }
            const data = await res.json();
            
            if (data.success) {
                alert('تم تعيين الصورة كرئيسية بنجاح');
                loadMedia(currentPage);
            } else {
                throw new Error(data.message || 'فشل التعيين');
            }
        } catch (err) {
            console.error('خطأ في التعيين:', err);
            alert(`خطأ في التعيين: ${err.message}`);
        } finally {
            showLoading(false);
        }
    }

    // Show/hide loading
    function showLoading(show) {
        if (loading) loading.style.display = show ? 'block' : 'none';
        if (grid) grid.style.opacity = show ? '0.5' : '1';
    }

    // Utility: Debounce function
    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    // Make functions available globally
    window.MediaStudio = {
        loadMedia,
        closeCropModal,
        handleUpload,
        handleDelete,
        handleCrop,
        handleDownload,
        handlePaste,
        handleUse,
        handleEdit: toggleInlineEdit,
        closeEditModal: toggleInlineEdit,
        handleSetMain,
        getSelectedCount: () => selected.size
    };

    // Initialize
    init();
})();