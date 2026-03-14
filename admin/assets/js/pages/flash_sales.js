(function(){
'use strict';

var CFG = window.FLASH_SALES_CONFIG || {};
var S = CFG.strings || {};
var PER_PAGE = 25;
var currentPage = 1;
var currentFilters = {};

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
    fetch('/api/flash_sales?stats=1')
        .then(function(r){ return r.json(); })
        .then(function(d){
            if (d.success && d.data) {
                document.getElementById('statTotal').textContent = d.data.total || 0;
                document.getElementById('statActive').textContent = d.data.active || 0;
                document.getElementById('statUpcoming').textContent = d.data.upcoming || 0;
                document.getElementById('statEnded').textContent = d.data.ended || 0;
            }
        })
        .catch(function(){});
}

/* ── List ── */
function loadFlashSales(page) {
    currentPage = page || 1;
    var offset = (currentPage - 1) * PER_PAGE;
    var url = '/api/flash_sales?limit=' + PER_PAGE + '&offset=' + offset;
    if (currentFilters.search) url += '&search=' + encodeURIComponent(currentFilters.search);
    if (currentFilters.status) url += '&status=' + encodeURIComponent(currentFilters.status);
    if (currentFilters.is_active) url += '&is_active=' + encodeURIComponent(currentFilters.is_active);

    fetch(url)
        .then(function(r){ return r.json(); })
        .then(function(d){
            var tbody = document.getElementById('flashSalesBody');
            tbody.innerHTML = '';
            if (d.success && d.data && d.data.items && d.data.items.length > 0) {
                d.data.items.forEach(function(item){
                    var now = new Date();
                    var start = new Date(item.start_date);
                    var end = new Date(item.end_date);
                    var status = now < start ? 'upcoming' : (now > end ? 'ended' : 'active');
                    var statusClass = status === 'active' ? 'badge-success' : (status === 'upcoming' ? 'badge-info' : 'badge-secondary');
                    var discount = item.discount_type === 'percentage' ? item.discount_value + '%' : '$' + item.discount_value;

                    var tr = document.createElement('tr');
                    tr.innerHTML =
                        '<td>' + esc(String(item.id)) + '</td>' +
                        '<td>' + esc(item.sale_name) + '</td>' +
                        '<td>' + esc(discount) + '</td>' +
                        '<td>' + esc(item.start_date) + '</td>' +
                        '<td>' + esc(item.end_date) + '</td>' +
                        '<td><span class="badge ' + statusClass + '">' + esc(status) + '</span></td>' +
                        '<td>' + esc(String(item.total_products || 0)) + '</td>' +
                        '<td>' + esc(String(item.total_sales || 0)) + '</td>' +
                        '<td class="actions-cell">' +
                            '<button class="btn btn-sm btn-info btn-products" data-id="' + item.id + '">' + t('products.title', 'Products') + '</button> ' +
                            '<button class="btn btn-sm btn-secondary btn-translations" data-id="' + item.id + '">' + t('translations.title', 'Translations') + '</button> ' +
                            '<button class="btn btn-sm btn-primary btn-edit" data-id="' + item.id + '">' + t('edit', 'Edit') + '</button> ' +
                            '<button class="btn btn-sm btn-danger btn-delete" data-id="' + item.id + '">' + t('delete', 'Delete') + '</button>' +
                        '</td>';
                    tbody.appendChild(tr);
                });
                renderPagination(d.data);
            } else {
                tbody.innerHTML = '<tr><td colspan="9" class="text-center">' + t('table.no_records', 'No records found') + '</td></tr>';
                document.getElementById('paginationInfo').textContent = '';
                document.getElementById('pagination').innerHTML = '';
            }
        })
        .catch(function(e){ showNotification(t('messages.error', 'Error loading data'), 'error'); });
}

/* ── Pagination ── */
function renderPagination(meta) {
    var total = meta.total || 0;
    var totalPages = meta.total_pages || 1;
    var start = ((currentPage - 1) * PER_PAGE) + 1;
    var end = Math.min(currentPage * PER_PAGE, total);
    document.getElementById('paginationInfo').textContent = t('pagination.showing', 'Showing') + ' ' + start + '-' + end + ' ' + t('pagination.of', 'of') + ' ' + total;

    var pag = document.getElementById('pagination');
    pag.innerHTML = '';
    if (totalPages <= 1) return;

    var prev = document.createElement('button');
    prev.className = 'btn btn-sm' + (currentPage <= 1 ? ' disabled' : '');
    prev.textContent = '‹';
    prev.disabled = currentPage <= 1;
    prev.addEventListener('click', function(){ if(currentPage > 1) loadFlashSales(currentPage - 1); });
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
            btn.addEventListener('click', function(){ loadFlashSales(pageNum); });
            pag.appendChild(btn);
        })(i);
    }

    var next = document.createElement('button');
    next.className = 'btn btn-sm' + (currentPage >= totalPages ? ' disabled' : '');
    next.textContent = '›';
    next.disabled = currentPage >= totalPages;
    next.addEventListener('click', function(){ if(currentPage < totalPages) loadFlashSales(currentPage + 1); });
    pag.appendChild(next);
}

/* ── CRUD ── */
function editFlashSale(id) {
    fetch('/api/flash_sales?id=' + id)
        .then(function(r){ return r.json(); })
        .then(function(d){
            if (d.success && d.data) {
                var item = d.data;
                document.getElementById('flashSaleId').value = item.id;
                document.getElementById('saleName').value = item.sale_name || '';
                document.getElementById('discountType').value = item.discount_type || 'percentage';
                document.getElementById('discountValue').value = item.discount_value || '';
                document.getElementById('maxDiscount').value = item.max_discount_amount || '';
                document.getElementById('startDate').value = (item.start_date || '').replace(' ', 'T').substring(0, 16);
                document.getElementById('endDate').value = (item.end_date || '').replace(' ', 'T').substring(0, 16);
                document.getElementById('saleDescription').value = item.description || '';
                document.getElementById('bannerImage').value = item.banner_image || '';
                document.getElementById('isActive').value = item.is_active != null ? String(item.is_active) : '1';
                if (document.getElementById('entitySelect')) {
                    document.getElementById('entitySelect').value = item.entity_id || '';
                }
                document.getElementById('modalTitle').textContent = t('modal.edit_title', 'Edit Flash Sale');
                openModal('flashSaleModal');
            }
        })
        .catch(function(){ showNotification(t('messages.error', 'Error'), 'error'); });
}

function deleteFlashSale(id) {
    if (!confirm(t('messages.confirm_delete', 'Are you sure you want to delete this?'))) return;
    fetch('/api/flash_sales?id=' + id, { method: 'DELETE' })
        .then(function(r){ return r.json(); })
        .then(function(d){
            if (d.success) { showNotification(t('messages.deleted', 'Deleted'), 'success'); loadFlashSales(currentPage); loadStats(); }
            else showNotification(d.message || t('messages.error', 'Error'), 'error');
        })
        .catch(function(){ showNotification(t('messages.error', 'Error'), 'error'); });
}

/* ── Products ── */
var currentFlashSaleId = 0;

function loadProducts(fid) {
    currentFlashSaleId = fid;
    document.getElementById('addProductFlashSaleId').value = fid;
    fetch('/api/flash_sale_products?flash_sale_id=' + fid)
        .then(function(r){ return r.json(); })
        .then(function(d){
            var tbody = document.getElementById('productsBody');
            tbody.innerHTML = '';
            var items = d.success ? (Array.isArray(d.data) ? d.data : (d.data && d.data.items ? d.data.items : [])) : [];
            if (items.length === 0) {
                tbody.innerHTML = '<tr><td colspan="9" class="text-center">' + t('table.no_records', 'No records') + '</td></tr>';
                return;
            }
            items.forEach(function(p){
                var tr = document.createElement('tr');
                tr.innerHTML =
                    '<td>' + esc(p.product_name || String(p.product_id)) + '</td>' +
                    '<td>' + esc(String(p.original_price)) + '</td>' +
                    '<td>' + esc(String(p.sale_price)) + '</td>' +
                    '<td>' + esc(String(p.discount_percentage || 0)) + '%</td>' +
                    '<td>' + esc(String(p.stock_quantity || 0)) + '</td>' +
                    '<td>' + esc(String(p.sold_quantity || 0)) + '</td>' +
                    '<td>' + esc(String(p.max_quantity_per_user || 5)) + '</td>' +
                    '<td>' + (p.is_active ? t('yes', 'Yes') : t('no', 'No')) + '</td>' +
                    '<td><button class="btn btn-sm btn-danger btn-delete-product" data-id="' + p.id + '">' + t('delete', 'Delete') + '</button></td>';
                tbody.appendChild(tr);
            });
            openModal('productsModal');
        })
        .catch(function(){ showNotification(t('messages.error', 'Error'), 'error'); });
}

function loadProductOptions() {
    var sel = document.getElementById('productSelect');
    if (sel.options.length > 1) return;
    fetch('/api/products?limit=200')
        .then(function(r){ return r.json(); })
        .then(function(d){
            var items = d.success ? (d.data && d.data.items ? d.data.items : (Array.isArray(d.data) ? d.data : [])) : [];
            items.forEach(function(p){
                var opt = document.createElement('option');
                opt.value = p.id;
                opt.textContent = p.name || p.sku || p.slug || ('Product #' + p.id);
                sel.appendChild(opt);
            });
        })
        .catch(function(){});
}

/* ── Translations ── */
function loadTranslations(fid) {
    document.getElementById('transFlashSaleId').value = fid;
    fetch('/api/flash_sales_translations?flash_sale_id=' + fid)
        .then(function(r){ return r.json(); })
        .then(function(d){
            var tbody = document.getElementById('translationsBody');
            tbody.innerHTML = '';
            var items = d.success ? (Array.isArray(d.data) ? d.data : []) : [];
            if (items.length === 0) {
                tbody.innerHTML = '<tr><td colspan="4" class="text-center">' + t('table.no_records', 'No records') + '</td></tr>';
            } else {
                items.forEach(function(tr_item){
                    var tr = document.createElement('tr');
                    tr.innerHTML =
                        '<td>' + esc(tr_item.language_code) + '</td>' +
                        '<td>' + esc(tr_item.field_name) + '</td>' +
                        '<td>' + esc(tr_item.value) + '</td>' +
                        '<td><button class="btn btn-sm btn-danger btn-delete-trans" data-id="' + tr_item.id + '">' + t('delete', 'Delete') + '</button></td>';
                    tbody.appendChild(tr);
                });
            }
            openModal('translationsModal');
        })
        .catch(function(){ showNotification(t('messages.error', 'Error'), 'error'); });

    // Load languages
    var langSel = document.getElementById('transLang');
    if (langSel.options.length === 0) {
        fetch('/api/languages')
            .then(function(r){ return r.json(); })
            .then(function(d){
                var langs = d.success ? (d.data && d.data.items ? d.data.items : (Array.isArray(d.data) ? d.data : [])) : [];
                langs.forEach(function(l){
                    var opt = document.createElement('option');
                    opt.value = l.code || l.language_code || l.id;
                    opt.textContent = l.name || l.code || l.id;
                    langSel.appendChild(opt);
                });
            })
            .catch(function(){
                ['ar','en','fr','de','es','ja','zh'].forEach(function(c){
                    var opt = document.createElement('option');
                    opt.value = c; opt.textContent = c;
                    langSel.appendChild(opt);
                });
            });
    }
}

/* ── Entity Options ── */
function loadEntityOptions() {
    var sel = document.getElementById('entitySelect');
    if (!sel || sel.options.length > 1) return;
    fetch('/api/entities?limit=100')
        .then(function(r){ return r.json(); })
        .then(function(d){
            var items = d.success ? (d.data && d.data.items ? d.data.items : (Array.isArray(d.data) ? d.data : [])) : [];
            items.forEach(function(e){
                var opt = document.createElement('option');
                opt.value = e.id;
                opt.textContent = e.store_name || e.id;
                sel.appendChild(opt);
            });
        })
        .catch(function(){});
}

/* ── Init ── */
function init() {
    loadStats();
    loadFlashSales(1);
    loadProductOptions();
    loadEntityOptions();

    // Add button
    document.getElementById('btnAddFlashSale').addEventListener('click', function(){
        document.getElementById('flashSaleForm').reset();
        document.getElementById('flashSaleId').value = '';
        document.getElementById('modalTitle').textContent = t('modal.add_title', 'Add Flash Sale');
        openModal('flashSaleModal');
    });

    // Close modals
    document.getElementById('btnCloseModal').addEventListener('click', function(){ closeModal('flashSaleModal'); });
    document.getElementById('btnCancelModal').addEventListener('click', function(){ closeModal('flashSaleModal'); });
    document.getElementById('btnCloseProducts').addEventListener('click', function(){ closeModal('productsModal'); });
    document.getElementById('btnCloseAddProduct').addEventListener('click', function(){ closeModal('addProductModal'); });
    document.getElementById('btnCancelAddProduct').addEventListener('click', function(){ closeModal('addProductModal'); });
    document.getElementById('btnCloseTranslations').addEventListener('click', function(){ closeModal('translationsModal'); });

    // Form submit
    document.getElementById('flashSaleForm').addEventListener('submit', function(e){
        e.preventDefault();
        var id = document.getElementById('flashSaleId').value;
        var payload = {
            sale_name: document.getElementById('saleName').value,
            discount_type: document.getElementById('discountType').value,
            discount_value: document.getElementById('discountValue').value,
            max_discount_amount: document.getElementById('maxDiscount').value || null,
            start_date: document.getElementById('startDate').value.replace('T', ' ') + ':00',
            end_date: document.getElementById('endDate').value.replace('T', ' ') + ':00',
            description: document.getElementById('saleDescription').value,
            banner_image: document.getElementById('bannerImage').value,
            is_active: document.getElementById('isActive').value,
            entity_id: document.getElementById('entitySelect').value ? parseInt(document.getElementById('entitySelect').value) : null
        };
        if (id) payload.id = parseInt(id);

        fetch('/api/flash_sales', {
            method: id ? 'PUT' : 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
        .then(function(r){ return r.json(); })
        .then(function(d){
            if (d.success) {
                closeModal('flashSaleModal');
                showNotification(id ? t('messages.updated', 'Updated') : t('messages.created', 'Created'), 'success');
                loadFlashSales(currentPage);
                loadStats();
            } else {
                showNotification(d.message || t('messages.error', 'Error'), 'error');
            }
        })
        .catch(function(){ showNotification(t('messages.error', 'Error'), 'error'); });
    });

    // Filter
    document.getElementById('btnFilter').addEventListener('click', function(){
        currentFilters = {
            search: document.getElementById('searchInput').value,
            status: document.getElementById('statusFilter').value,
            is_active: document.getElementById('activeFilter').value
        };
        loadFlashSales(1);
    });
    document.getElementById('btnClearFilter').addEventListener('click', function(){
        document.getElementById('searchInput').value = '';
        document.getElementById('statusFilter').value = '';
        document.getElementById('activeFilter').value = '';
        currentFilters = {};
        loadFlashSales(1);
    });

    // Add product form
    document.getElementById('btnAddProduct').addEventListener('click', function(){
        openModal('addProductModal');
    });
    document.getElementById('addProductForm').addEventListener('submit', function(e){
        e.preventDefault();
        var origP = parseFloat(document.getElementById('prodOrigPrice').value);
        var saleP = parseFloat(document.getElementById('prodSalePrice').value);
        var discPct = origP > 0 ? Math.max(0, Math.round((origP - saleP) / origP * 10000) / 100) : 0;
        var payload = {
            flash_sale_id: parseInt(document.getElementById('addProductFlashSaleId').value),
            product_id: parseInt(document.getElementById('productSelect').value),
            original_price: origP,
            sale_price: saleP,
            discount_percentage: discPct,
            stock_quantity: parseInt(document.getElementById('prodStock').value) || 0,
            max_quantity_per_user: parseInt(document.getElementById('prodMaxPerUser').value) || 5,
            is_active: 1
        };
        fetch('/api/flash_sale_products', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
        .then(function(r){ return r.json(); })
        .then(function(d){
            if (d.success) {
                closeModal('addProductModal');
                showNotification(t('messages.created', 'Added'), 'success');
                loadProducts(currentFlashSaleId);
            } else {
                showNotification(d.message || t('messages.error', 'Error'), 'error');
            }
        })
        .catch(function(){ showNotification(t('messages.error', 'Error'), 'error'); });
    });

    // Save translation
    document.getElementById('btnSaveTranslation').addEventListener('click', function(){
        var fid = document.getElementById('transFlashSaleId').value;
        var payload = {
            flash_sale_id: parseInt(fid),
            language_code: document.getElementById('transLang').value,
            field_name: document.getElementById('transField').value,
            value: document.getElementById('transValue').value
        };
        fetch('/api/flash_sales_translations', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
        .then(function(r){ return r.json(); })
        .then(function(d){
            if (d.success) {
                showNotification(t('messages.created', 'Saved'), 'success');
                document.getElementById('transValue').value = '';
                loadTranslations(parseInt(fid));
            } else {
                showNotification(d.message || t('messages.error', 'Error'), 'error');
            }
        })
        .catch(function(){ showNotification(t('messages.error', 'Error'), 'error'); });
    });

    // Delegated click events
    document.addEventListener('click', function(e){
        var target = e.target;
        if (target.classList.contains('btn-edit')) { editFlashSale(parseInt(target.getAttribute('data-id'))); }
        else if (target.classList.contains('btn-delete')) { deleteFlashSale(parseInt(target.getAttribute('data-id'))); }
        else if (target.classList.contains('btn-products')) { loadProducts(parseInt(target.getAttribute('data-id'))); }
        else if (target.classList.contains('btn-translations')) { loadTranslations(parseInt(target.getAttribute('data-id'))); }
        else if (target.classList.contains('btn-delete-product')) {
            if (confirm(t('messages.confirm_delete', 'Delete?'))) {
                fetch('/api/flash_sale_products?id=' + target.getAttribute('data-id'), { method: 'DELETE' })
                    .then(function(r){ return r.json(); })
                    .then(function(d){
                        if (d.success) { showNotification(t('messages.deleted', 'Deleted'), 'success'); loadProducts(currentFlashSaleId); }
                        else showNotification(d.message, 'error');
                    });
            }
        }
        else if (target.classList.contains('btn-delete-trans')) {
            if (confirm(t('messages.confirm_delete', 'Delete?'))) {
                fetch('/api/flash_sales_translations?id=' + target.getAttribute('data-id'), { method: 'DELETE' })
                    .then(function(r){ return r.json(); })
                    .then(function(d){
                        if (d.success) {
                            showNotification(t('messages.deleted', 'Deleted'), 'success');
                            loadTranslations(parseInt(document.getElementById('transFlashSaleId').value));
                        } else showNotification(d.message, 'error');
                    });
            }
        }
    });
}

// Fragment-compatible init
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
} else {
    init();
}

})();