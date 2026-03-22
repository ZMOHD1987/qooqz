(function(){
'use strict';

var CFG = {};
var S = {};
var PER_PAGE = 25;
var currentPage = 1;
var currentFilters = {};

function reloadConfig() {
    CFG = window.STOCK_MOVEMENTS_CONFIG || {};
    S = CFG.strings || {};
}

function t(key, fb) {
    var parts = key.split('.');
    var v = S;
    for (var i = 0; i < parts.length; i++) {
        if (!v || typeof v !== 'object') return fb || key;
        v = v[parts[i]];
    }
    return (typeof v === 'string') ? v : (fb || key);
}

function esc(s) { var d = document.createElement('div'); d.textContent = s; return d.innerHTML; }

function showNotification(msg, type) {
    var n = document.createElement('div');
    n.className = 'notification notification-' + (type || 'info');
    n.textContent = msg;
    document.body.appendChild(n);
    setTimeout(function(){ n.style.opacity = '0'; setTimeout(function(){ n.remove(); }, 300); }, 3000);
}

function openModal(id) { document.getElementById(id).style.display = 'block'; }
function closeModal(id) { document.getElementById(id).style.display = 'none'; }

/* ── Stats ── */
function loadStats() {
    fetch('/api/product_stock_movements?stats=1')
        .then(function(r){ return r.json(); })
        .then(function(d){
            if (d.success && d.data) {
                document.getElementById('statTotal').textContent = d.data.total_movements || 0;
                document.getElementById('statRestocked').textContent = d.data.total_restocked || 0;
                document.getElementById('statSold').textContent = d.data.total_sold || 0;
                document.getElementById('statReturned').textContent = d.data.total_returned || 0;
            }
        })
        .catch(function(){});
}

/* ── List ── */
function loadMovements(page) {
    currentPage = page || 1;
    var offset = (currentPage - 1) * PER_PAGE;
    var url = '/api/product_stock_movements?limit=' + PER_PAGE + '&offset=' + offset;
    if (currentFilters.search) url += '&search=' + encodeURIComponent(currentFilters.search);
    if (currentFilters.type) url += '&type=' + encodeURIComponent(currentFilters.type);
    if (currentFilters.date_from) url += '&date_from=' + encodeURIComponent(currentFilters.date_from);
    if (currentFilters.date_to) url += '&date_to=' + encodeURIComponent(currentFilters.date_to);

    fetch(url)
        .then(function(r){ return r.json(); })
        .then(function(d){
            var tbody = document.getElementById('movementsBody');
            tbody.innerHTML = '';
            if (d.success && d.data && d.data.items && d.data.items.length > 0) {
                d.data.items.forEach(function(item){
                    var typeClass = item.type === 'restock' ? 'badge-success' : (item.type === 'sale' ? 'badge-danger' : (item.type === 'return' ? 'badge-warning' : 'badge-info'));
                    var qtyPrefix = parseInt(item.change_quantity) > 0 ? '+' : '';
                    var tr = document.createElement('tr');
                    tr.innerHTML =
                        '<td>' + esc(String(item.id)) + '</td>' +
                        '<td>' + esc(item.product_name || '') + ' <small>(#' + esc(String(item.product_id)) + ')</small></td>' +
                        '<td>' + (item.variant_id ? esc(String(item.variant_id)) : '-') + '</td>' +
                        '<td><span class="badge ' + typeClass + '">' + t('types.' + item.type, item.type) + '</span></td>' +
                        '<td><strong>' + qtyPrefix + esc(String(item.change_quantity)) + '</strong></td>' +
                        '<td>' + (item.reference_id ? esc(String(item.reference_id)) : '-') + '</td>' +
                        '<td>' + esc(item.notes || '-') + '</td>' +
                        '<td>' + esc(item.created_at || '-') + '</td>' +
                        '<td class="actions-cell">' +
                            (CFG.canEdit ? '<button class="btn btn-sm btn-info btn-edit" data-id="' + item.id + '">' + t('form.edit', 'Edit') + '</button> ' : '') +
                            (CFG.canDelete ? '<button class="btn btn-sm btn-danger btn-delete" data-id="' + item.id + '">' + t('form.delete', 'Delete') + '</button>' : '') +
                        '</td>';
                    tbody.appendChild(tr);
                });
                renderPagination(d.data);
            } else {
                tbody.innerHTML = '<tr><td colspan="9" class="text-center">' + t('table.no_records', 'No movements found') + '</td></tr>';
                document.getElementById('paginationInfo').textContent = '';
                document.getElementById('pagination').innerHTML = '';
            }
        })
        .catch(function(){ showNotification(t('messages.error', 'Error loading data'), 'error'); });
}

/* ── Pagination ── */
function renderPagination(meta) {
    var total = meta.total || 0;
    var totalPages = meta.total_pages || Math.ceil(total / PER_PAGE) || 1;
    var start = ((currentPage - 1) * PER_PAGE) + 1;
    var end = Math.min(currentPage * PER_PAGE, total);
    document.getElementById('paginationInfo').textContent = t('pagination.showing', 'Showing') + ' ' + start + '-' + end + ' ' + t('pagination.of', 'of') + ' ' + total;

    var pag = document.getElementById('pagination');
    pag.innerHTML = '';
    if (totalPages <= 1) return;

    var prev = document.createElement('button');
    prev.className = 'btn btn-sm' + (currentPage <= 1 ? ' disabled' : '');
    prev.textContent = t('pagination.prev', '‹');
    prev.disabled = currentPage <= 1;
    prev.addEventListener('click', function(){ if(currentPage > 1) loadMovements(currentPage - 1); });
    pag.appendChild(prev);

    for (var i = 1; i <= totalPages; i++) {
        if (totalPages > 7 && i > 2 && i < totalPages - 1 && Math.abs(i - currentPage) > 1) {
            if (i === 3 || i === totalPages - 2) {
                var el = document.createElement('span');
                el.className = 'pagination-ellipsis';
                el.textContent = '...';
                pag.appendChild(el);
            }
            continue;
        }
        (function(pageNum){
            var btn = document.createElement('button');
            btn.className = 'btn btn-sm' + (pageNum === currentPage ? ' btn-primary active' : '');
            btn.textContent = String(pageNum);
            btn.addEventListener('click', function(){ loadMovements(pageNum); });
            pag.appendChild(btn);
        })(i);
    }

    var next = document.createElement('button');
    next.className = 'btn btn-sm' + (currentPage >= totalPages ? ' disabled' : '');
    next.textContent = t('pagination.next', '›');
    next.disabled = currentPage >= totalPages;
    next.addEventListener('click', function(){ if(currentPage < totalPages) loadMovements(currentPage + 1); });
    pag.appendChild(next);
}

/* ── Scan Barcode ── */
function scanBarcode() {
    var barcode = document.getElementById('barcodeInput').value.trim();
    if (!barcode) return;

    fetch('/api/product_stock_movements?barcode=' + encodeURIComponent(barcode))
        .then(function(r){ return r.json(); })
        .then(function(d){
            var resultEl = document.getElementById('barcodeResult');
            if (d.success && d.data) {
                resultEl.style.display = 'block';
                resultEl.style.color = 'var(--success-color, #10b981)';
                resultEl.textContent = t('messages.product_found', 'Product found') + ': ' + (d.data.product_name || '') + ' (#' + d.data.id + ')';
                // Populate the add movement form
                document.getElementById('productIdInput').value = d.data.id;
                if (d.data.variant_id) {
                    document.getElementById('variantIdInput').value = d.data.variant_id;
                }
                lookupProduct();
            } else {
                resultEl.style.display = 'block';
                resultEl.style.color = 'var(--danger-color, #ef4444)';
                resultEl.textContent = t('messages.barcode_not_found', 'Barcode not found');
            }
        })
        .catch(function(){
            showNotification(t('messages.error', 'An error occurred'), 'error');
        });
}

/* ── Lookup Product ── */
function lookupProduct() {
    var productId = document.getElementById('productIdInput').value;
    var nameEl = document.getElementById('productName');
    if (!productId) { nameEl.textContent = ''; return; }

    fetch('/api/products?id=' + encodeURIComponent(productId))
        .then(function(r){ return r.json(); })
        .then(function(d){
            if (d.success && d.data) {
                var name = d.data.name || d.data.product_name || '';
                nameEl.textContent = name ? t('messages.product_found', 'Product found') + ': ' + name : '';
                nameEl.className = 'lookup-name';
            } else {
                nameEl.textContent = t('messages.product_not_found', 'Product not found');
                nameEl.className = 'lookup-name error';
            }
        })
        .catch(function(){
            nameEl.textContent = t('messages.product_not_found', 'Product not found');
            nameEl.className = 'lookup-name error';
        });
}

/* ── Save Movement ── */
function saveMovement(e) {
    e.preventDefault();
    var editId = document.getElementById('movementId').value;
    var isEdit = editId && parseInt(editId) > 0;
    var payload = {
        product_id: parseInt(document.getElementById('productIdInput').value) || 0,
        change_quantity: parseInt(document.getElementById('changeQuantity').value) || 0,
        type: document.getElementById('movementType').value
    };

    var variantId = document.getElementById('variantIdInput').value;
    if (variantId) payload.variant_id = parseInt(variantId);

    var referenceId = document.getElementById('referenceId').value;
    if (referenceId) payload.reference_id = parseInt(referenceId);

    var notes = document.getElementById('movementNotes').value;
    if (notes) payload.notes = notes;

    var url = '/api/product_stock_movements';
    var method = 'POST';
    if (isEdit) {
        payload.id = parseInt(editId);
        url += '?id=' + editId;
        method = 'PUT';
    }

    fetch(url, {
        method: method,
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
    .then(function(r){ return r.json(); })
    .then(function(d){
        if (d.success) {
            closeModal('movementModal');
            showNotification(t('messages.saved', 'Movement saved successfully'), 'success');
            document.getElementById('movementForm').reset();
            document.getElementById('movementId').value = '';
            document.getElementById('productName').textContent = '';
            loadMovements(currentPage);
            loadStats();
        } else {
            showNotification(d.message || t('messages.error', 'Error'), 'error');
        }
    })
    .catch(function(){ showNotification(t('messages.error', 'Error'), 'error'); });
}

/* ── Delete Movement ── */
function deleteMovement(id) {
    if (!confirm(t('messages.confirm_delete', 'Are you sure you want to delete?'))) return;

    fetch('/api/product_stock_movements?id=' + id, { method: 'DELETE' })
        .then(function(r){ return r.json(); })
        .then(function(d){
            if (d.success) {
                showNotification(t('messages.deleted', 'Movement deleted'), 'success');
                loadMovements(currentPage);
                loadStats();
            } else {
                showNotification(d.message || t('messages.error', 'Error'), 'error');
            }
        })
        .catch(function(){ showNotification(t('messages.error', 'Error'), 'error'); });
}

/* ── Edit Movement ── */
function editMovement(id) {
    fetch('/api/product_stock_movements?id=' + id)
        .then(function(r){ return r.json(); })
        .then(function(d){
            if (d.success && d.data) {
                var item = d.data;
                document.getElementById('movementId').value = item.id;
                document.getElementById('productIdInput').value = item.product_id || '';
                document.getElementById('variantIdInput').value = item.variant_id || '';
                document.getElementById('movementType').value = item.type || 'restock';
                document.getElementById('changeQuantity').value = item.change_quantity || 0;
                document.getElementById('referenceId').value = item.reference_id || '';
                document.getElementById('movementNotes').value = item.notes || '';
                document.getElementById('modalTitle').textContent = t('form.edit', 'Edit') + ' #' + id;
                lookupProduct();
                openModal('movementModal');
            } else {
                showNotification(d.message || t('messages.error', 'Error'), 'error');
            }
        })
        .catch(function(){ showNotification(t('messages.error', 'Error'), 'error'); });
}

/* ── SKU Lookup ── */
function skuLookup() {
    var sku = document.getElementById('skuInput').value.trim();
    if (!sku) return;

    fetch('/api/product_stock_movements?sku=' + encodeURIComponent(sku))
        .then(function(r){ return r.json(); })
        .then(function(d){
            var resultEl = document.getElementById('barcodeResult');
            if (d.success && d.data) {
                resultEl.style.display = 'block';
                resultEl.style.color = 'var(--success-color, #10b981)';
                resultEl.textContent = t('messages.product_found', 'Product found') + ': ' + (d.data.product_name || '') + ' (#' + d.data.id + ')';
                document.getElementById('productIdInput').value = d.data.id;
                if (d.data.variant_id) {
                    document.getElementById('variantIdInput').value = d.data.variant_id;
                }
                lookupProduct();
                openModal('movementModal');
            } else {
                resultEl.style.display = 'block';
                resultEl.style.color = 'var(--danger-color, #ef4444)';
                resultEl.textContent = t('messages.sku_not_found', 'SKU not found');
            }
        })
        .catch(function(){
            showNotification(t('messages.error', 'An error occurred'), 'error');
        });
}

/* ── Camera Scanner ── */
var cameraStream = null;
var cameraInterval = null;

function startCameraScanner() {
    var container = document.getElementById('cameraContainer');
    var video = document.getElementById('cameraVideo');

    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
        showNotification(t('messages.camera_not_supported', 'Camera not supported on this device'), 'error');
        return;
    }

    container.style.display = 'block';

    navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } })
        .then(function(stream) {
            cameraStream = stream;
            video.srcObject = stream;

            // Create BarcodeDetector once if available
            var detector = null;
            if (window.BarcodeDetector) {
                detector = new BarcodeDetector({ formats: ['ean_13', 'ean_8', 'code_128', 'code_39', 'qr_code', 'upc_a', 'upc_e'] });
            }

            // Poll frames for barcode detection
            var SCAN_INTERVAL_MS = 800;
            cameraInterval = setInterval(function() {
                var canvas = document.getElementById('cameraCanvas');
                var ctx = canvas.getContext('2d');
                canvas.width = video.videoWidth;
                canvas.height = video.videoHeight;
                ctx.drawImage(video, 0, 0);

                if (detector) {
                    detector.detect(canvas)
                        .then(function(barcodes) {
                            if (barcodes.length > 0) {
                                var code = barcodes[0].rawValue;
                                stopCameraScanner();
                                document.getElementById('barcodeInput').value = code;
                                scanBarcode();
                            }
                        })
                        .catch(function() { /* ignore detection errors */ });
                }
            }, SCAN_INTERVAL_MS);
        })
        .catch(function(err) {
            showNotification(t('messages.camera_error', 'Cannot access camera: ') + err.message, 'error');
            container.style.display = 'none';
        });
}

function stopCameraScanner() {
    if (cameraInterval) {
        clearInterval(cameraInterval);
        cameraInterval = null;
    }
    if (cameraStream) {
        cameraStream.getTracks().forEach(function(track) { track.stop(); });
        cameraStream = null;
    }
    var video = document.getElementById('cameraVideo');
    if (video) video.srcObject = null;
    document.getElementById('cameraContainer').style.display = 'none';
}

/* ── Clear Filters ── */
function clearFilters() {
    document.getElementById('searchInput').value = '';
    document.getElementById('typeFilter').value = '';
    document.getElementById('dateFrom').value = '';
    document.getElementById('dateTo').value = '';
    currentFilters = {};
    loadMovements(1);
}

/* ── Init ── */
function init() {
    reloadConfig();
    loadStats();
    loadMovements(1);

    // Filter button
    document.getElementById('btnFilter').addEventListener('click', function(){
        currentFilters = {
            search: document.getElementById('searchInput').value,
            type: document.getElementById('typeFilter').value,
            date_from: document.getElementById('dateFrom').value,
            date_to: document.getElementById('dateTo').value
        };
        loadMovements(1);
    });

    // Clear filters
    document.getElementById('btnClearFilter').addEventListener('click', clearFilters);

    // Add movement modal
    document.getElementById('btnAddMovement').addEventListener('click', function(){
        document.getElementById('movementForm').reset();
        document.getElementById('movementId').value = '';
        document.getElementById('productName').textContent = '';
        document.getElementById('modalTitle').textContent = t('add_movement', 'Add Movement');
        openModal('movementModal');
    });

    // Close modal
    document.getElementById('btnCloseModal').addEventListener('click', function(){ closeModal('movementModal'); });
    document.getElementById('btnCancelModal').addEventListener('click', function(){ closeModal('movementModal'); });

    // Save movement
    document.getElementById('movementForm').addEventListener('submit', saveMovement);

    // Barcode scan
    document.getElementById('btnScanBarcode').addEventListener('click', scanBarcode);
    document.getElementById('barcodeInput').addEventListener('keypress', function(e){
        if (e.key === 'Enter') { e.preventDefault(); scanBarcode(); }
    });

    // Product lookup
    document.getElementById('btnLookupProduct').addEventListener('click', lookupProduct);

    // Delete delegation
    document.getElementById('movementsBody').addEventListener('click', function(e){
        var btnDel = e.target.closest('.btn-delete');
        if (btnDel) deleteMovement(parseInt(btnDel.getAttribute('data-id')));
        var btnEdit = e.target.closest('.btn-edit');
        if (btnEdit) editMovement(parseInt(btnEdit.getAttribute('data-id')));
    });

    // SKU lookup
    var skuBtn = document.getElementById('btnSearchSku');
    if (skuBtn) skuBtn.addEventListener('click', skuLookup);
    var skuInput = document.getElementById('skuInput');
    if (skuInput) skuInput.addEventListener('keypress', function(e){
        if (e.key === 'Enter') { e.preventDefault(); skuLookup(); }
    });

    // Camera scanner
    var camBtn = document.getElementById('btnCameraScanner');
    if (camBtn) camBtn.addEventListener('click', startCameraScanner);
    var stopBtn = document.getElementById('btnStopCamera');
    if (stopBtn) stopBtn.addEventListener('click', stopCameraScanner);

    // Search on Enter
    document.getElementById('searchInput').addEventListener('keypress', function(e){
        if (e.key === 'Enter') {
            e.preventDefault();
            document.getElementById('btnFilter').click();
        }
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
} else {
    init();
}

})();