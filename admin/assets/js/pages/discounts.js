(function(){
'use strict';

var CFG = {};
var S = {};
var PER_PAGE = 25;
var currentPage = 1;
var currentFilters = {};
var currentDiscountId = 0;
var redemptionsPage = 1;

function reloadConfig() {
    CFG = window.DISCOUNTS_CONFIG || {};
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

function generateCode() {
    var chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    var code = '';
    for (var i = 0; i < 10; i++) code += chars.charAt(Math.floor(Math.random() * chars.length));
    return code;
}

/* ── Currencies ── */
function loadCurrencies() {
    var select = document.getElementById('currencyCode');
    if (!select) return;
    fetch('/api/currencies')
        .then(function(r){ return r.json(); })
        .then(function(d){
            var items = [];
            if (d.success && Array.isArray(d.data)) {
                items = d.data;
            } else if (Array.isArray(d)) {
                items = d;
            }
            items.forEach(function(c){
                var opt = document.createElement('option');
                opt.value = c.code || c.currency_code || '';
                opt.textContent = (c.code || c.currency_code || '') + (c.name ? ' - ' + c.name : '');
                select.appendChild(opt);
            });
        })
        .catch(function(){});
}

/* ── Stats ── */
function loadStats() {
    fetch('/api/discounts?stats=1')
        .then(function(r){ return r.json(); })
        .then(function(d){
            if (d.success && d.data) {
                document.getElementById('statTotal').textContent = d.data.total || 0;
                document.getElementById('statActive').textContent = d.data.active || 0;
                document.getElementById('statExpired').textContent = d.data.expired || 0;
                document.getElementById('statRedemptions').textContent = d.data.total_redemptions || 0;
            }
        })
        .catch(function(){});
}

/* ── List ── */
function loadDiscounts(page) {
    currentPage = page || 1;
    var offset = (currentPage - 1) * PER_PAGE;
    var url = '/api/discounts?limit=' + PER_PAGE + '&offset=' + offset;
    if (currentFilters.search) url += '&search=' + encodeURIComponent(currentFilters.search);
    if (currentFilters.type) url += '&type=' + encodeURIComponent(currentFilters.type);
    if (currentFilters.status) url += '&status=' + encodeURIComponent(currentFilters.status);
    if (currentFilters.date_from) url += '&date_from=' + encodeURIComponent(currentFilters.date_from);
    if (currentFilters.date_to) url += '&date_to=' + encodeURIComponent(currentFilters.date_to);
    var entity = document.getElementById('entitySelector').value;
    if (entity) url += '&entity_id=' + encodeURIComponent(entity);

    fetch(url)
        .then(function(r){ return r.json(); })
        .then(function(d){
            var tbody = document.getElementById('discountsBody');
            tbody.innerHTML = '';
            if (d.success && d.data && d.data.items && d.data.items.length > 0) {
                d.data.items.forEach(function(item){
                    var statusClass = item.status === 'active' ? 'badge-success' : (item.status === 'scheduled' ? 'badge-info' : (item.status === 'expired' ? 'badge-secondary' : 'badge-warning'));
                    var tr = document.createElement('tr');
                    tr.innerHTML =
                        '<td>' + esc(String(item.id)) + '</td>' +
                        '<td><strong>' + esc(item.code || '') + '</strong></td>' +
                        '<td>' + esc(item.name || '') + '</td>' +
                        '<td>' + esc(item.type || '') + '</td>' +
                        '<td><span class="badge ' + statusClass + '">' + esc(item.status || '') + '</span></td>' +
                        '<td>' + esc(String(item.priority || 0)) + '</td>' +
                        '<td>' + esc(item.starts_at || '-') + '</td>' +
                        '<td>' + esc(item.ends_at || '-') + '</td>' +
                        '<td>' + esc(String(item.current_redemptions || item.redemptions_count || 0)) + '</td>' +
                        '<td class="actions-cell">' +
                            '<button class="btn btn-sm btn-info btn-translations" data-id="' + item.id + '">' + t('translations.title', 'Translations') + '</button> ' +
                            '<button class="btn btn-sm btn-secondary btn-scopes" data-id="' + item.id + '">' + t('scopes.title', 'Scopes') + '</button> ' +
                            '<button class="btn btn-sm btn-info btn-conditions" data-id="' + item.id + '">' + t('conditions.title', 'Conditions') + '</button> ' +
                            '<button class="btn btn-sm btn-secondary btn-actions" data-id="' + item.id + '">' + t('actions.title', 'Actions') + '</button> ' +
                            '<button class="btn btn-sm btn-warning btn-exclusions" data-id="' + item.id + '">' + t('exclusions.title', 'Exclusions') + '</button> ' +
                            '<button class="btn btn-sm btn-info btn-redemptions" data-id="' + item.id + '">' + t('redemptions.title', 'Redemptions') + '</button> ' +
                            '<button class="btn btn-sm btn-primary btn-edit" data-id="' + item.id + '">' + t('edit', 'Edit') + '</button> ' +
                            '<button class="btn btn-sm btn-danger btn-delete" data-id="' + item.id + '">' + t('delete', 'Delete') + '</button>' +
                        '</td>';
                    tbody.appendChild(tr);
                });
                renderPagination(d.data);
            } else {
                tbody.innerHTML = '<tr><td colspan="10" class="text-center">' + t('table.no_records', 'No records found') + '</td></tr>';
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
    prev.textContent = '‹';
    prev.disabled = currentPage <= 1;
    prev.addEventListener('click', function(){ if(currentPage > 1) loadDiscounts(currentPage - 1); });
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
            btn.addEventListener('click', function(){ loadDiscounts(pageNum); });
            pag.appendChild(btn);
        })(i);
    }

    var next = document.createElement('button');
    next.className = 'btn btn-sm' + (currentPage >= totalPages ? ' disabled' : '');
    next.textContent = '›';
    next.disabled = currentPage >= totalPages;
    next.addEventListener('click', function(){ if(currentPage < totalPages) loadDiscounts(currentPage + 1); });
    pag.appendChild(next);
}

/* ── Save Discount ── */
function saveDiscount(e) {
    e.preventDefault();
    var id = document.getElementById('discountId').value;
    var payload = {
        entity_id: document.getElementById('entitySelect').value ? parseInt(document.getElementById('entitySelect').value) : null,
        type: document.getElementById('discountType').value,
        code: document.getElementById('discountCode').value,
        auto_apply: parseInt(document.getElementById('autoApply').value),
        priority: parseInt(document.getElementById('discountPriority').value) || 0,
        is_stackable: parseInt(document.getElementById('isStackable').value),
        currency_code: document.getElementById('currencyCode').value || null,
        max_redemptions: document.getElementById('maxRedemptions').value ? parseInt(document.getElementById('maxRedemptions').value) : null,
        max_redemptions_per_user: document.getElementById('maxRedemptionsPerUser').value ? parseInt(document.getElementById('maxRedemptionsPerUser').value) : null,
        starts_at: document.getElementById('startsAt').value ? document.getElementById('startsAt').value.replace('T', ' ') + ':00' : null,
        ends_at: document.getElementById('endsAt').value ? document.getElementById('endsAt').value.replace('T', ' ') + ':00' : null,
        status: document.getElementById('discountStatus').value
    };
    if (id) payload.id = parseInt(id);

    fetch('/api/discounts', {
        method: id ? 'PUT' : 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
    .then(function(r){ return r.json(); })
    .then(function(d){
        if (d.success) {
            closeModal('discountModal');
            showNotification(id ? t('messages.updated', 'Updated') : t('messages.created', 'Created'), 'success');
            loadDiscounts(currentPage);
            loadStats();
        } else {
            showNotification(d.message || t('messages.error', 'Error'), 'error');
        }
    })
    .catch(function(){ showNotification(t('messages.error', 'Error'), 'error'); });
}

/* ── Edit Discount ── */
function editDiscount(id) {
    fetch('/api/discounts?id=' + id)
        .then(function(r){ return r.json(); })
        .then(function(d){
            if (d.success && d.data) {
                var item = d.data;
                document.getElementById('discountId').value = item.id;
                document.getElementById('entitySelect').value = item.entity_id || '';
                document.getElementById('discountType').value = item.type || 'percentage';
                document.getElementById('discountCode').value = item.code || '';
                document.getElementById('autoApply').value = item.auto_apply != null ? String(item.auto_apply) : '0';
                document.getElementById('discountPriority').value = item.priority || 0;
                document.getElementById('isStackable').value = item.is_stackable != null ? String(item.is_stackable) : '0';
                document.getElementById('currencyCode').value = item.currency_code || '';
                document.getElementById('maxRedemptions').value = item.max_redemptions || '';
                document.getElementById('maxRedemptionsPerUser').value = item.max_redemptions_per_user || '';
                document.getElementById('startsAt').value = (item.starts_at || '').replace(' ', 'T').substring(0, 16);
                document.getElementById('endsAt').value = (item.ends_at || '').replace(' ', 'T').substring(0, 16);
                document.getElementById('discountStatus').value = item.status || 'active';
                document.getElementById('modalTitle').textContent = t('modal.edit_title', 'Edit Discount');
                openModal('discountModal');
            }
        })
        .catch(function(){ showNotification(t('messages.error', 'Error'), 'error'); });
}

/* ── Delete Discount ── */
function deleteDiscount(id) {
    if (!confirm(t('messages.confirm_delete', 'Are you sure you want to delete this?'))) return;
    fetch('/api/discounts?id=' + id, { method: 'DELETE' })
        .then(function(r){ return r.json(); })
        .then(function(d){
            if (d.success) { showNotification(t('messages.deleted', 'Deleted'), 'success'); loadDiscounts(currentPage); loadStats(); }
            else showNotification(d.message || t('messages.error', 'Error'), 'error');
        })
        .catch(function(){ showNotification(t('messages.error', 'Error'), 'error'); });
}

/* ── Translations ── */
function openTranslationsModal(discountId) {
    currentDiscountId = discountId;
    document.getElementById('transDiscountId').value = discountId;
    fetch('/api/discount_translations?discount_id=' + discountId)
        .then(function(r){ return r.json(); })
        .then(function(d){
            var tbody = document.getElementById('translationsBody');
            tbody.innerHTML = '';
            var items = d.success ? (Array.isArray(d.data) ? d.data : (d.data && d.data.items ? d.data.items : [])) : [];
            if (items.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6" class="text-center">' + t('table.no_records', 'No records') + '</td></tr>';
            } else {
                items.forEach(function(tr_item){
                    var tr = document.createElement('tr');
                    tr.innerHTML =
                        '<td>' + esc(tr_item.language_code || '') + '</td>' +
                        '<td>' + esc(tr_item.name || '') + '</td>' +
                        '<td>' + esc(tr_item.description || '') + '</td>' +
                        '<td>' + esc(tr_item.terms_conditions || '') + '</td>' +
                        '<td>' + esc(tr_item.marketing_badge || '') + '</td>' +
                        '<td><button class="btn btn-sm btn-danger btn-delete-trans" data-id="' + tr_item.id + '">' + t('delete', 'Delete') + '</button></td>';
                    tbody.appendChild(tr);
                });
            }
            openModal('translationsModal');
        })
        .catch(function(){ showNotification(t('messages.error', 'Error'), 'error'); });

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

function saveTranslation() {
    var discountId = document.getElementById('transDiscountId').value;
    var payload = {
        discount_id: parseInt(discountId),
        language_code: document.getElementById('transLang').value,
        name: document.getElementById('transName').value,
        description: document.getElementById('transDescription').value,
        terms_conditions: document.getElementById('transTermsConditions').value,
        marketing_badge: document.getElementById('transMarketingBadge').value
    };
    fetch('/api/discount_translations', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
    .then(function(r){ return r.json(); })
    .then(function(d){
        if (d.success) {
            showNotification(t('messages.created', 'Saved'), 'success');
            document.getElementById('transName').value = '';
            document.getElementById('transDescription').value = '';
            document.getElementById('transTermsConditions').value = '';
            document.getElementById('transMarketingBadge').value = '';
            openTranslationsModal(parseInt(discountId));
        } else {
            showNotification(d.message || t('messages.error', 'Error'), 'error');
        }
    })
    .catch(function(){ showNotification(t('messages.error', 'Error'), 'error'); });
}

function deleteTranslation(id) {
    if (!confirm(t('messages.confirm_delete', 'Delete?'))) return;
    fetch('/api/discount_translations?id=' + id, { method: 'DELETE' })
        .then(function(r){ return r.json(); })
        .then(function(d){
            if (d.success) {
                showNotification(t('messages.deleted', 'Deleted'), 'success');
                openTranslationsModal(parseInt(document.getElementById('transDiscountId').value));
            } else showNotification(d.message || t('messages.error', 'Error'), 'error');
        })
        .catch(function(){ showNotification(t('messages.error', 'Error'), 'error'); });
}

/* ── Scopes ── */
function openScopesModal(discountId) {
    currentDiscountId = discountId;
    document.getElementById('scopesDiscountId').value = discountId;
    fetch('/api/discount_scopes?discount_id=' + discountId)
        .then(function(r){ return r.json(); })
        .then(function(d){
            var tbody = document.getElementById('scopesBody');
            tbody.innerHTML = '';
            var items = d.success ? (Array.isArray(d.data) ? d.data : (d.data && d.data.items ? d.data.items : [])) : [];
            if (items.length === 0) {
                tbody.innerHTML = '<tr><td colspan="4" class="text-center">' + t('table.no_records', 'No records') + '</td></tr>';
            } else {
                items.forEach(function(item){
                    var tr = document.createElement('tr');
                    tr.innerHTML =
                        '<td>' + esc(item.scope_type || '') + '</td>' +
                        '<td>' + esc(String(item.scope_id || '')) + '</td>' +
                        '<td class="scope-name-cell">...</td>' +
                        '<td><button class="btn btn-sm btn-danger btn-delete-scope" data-id="' + item.id + '">' + t('delete', 'Delete') + '</button></td>';
                    tbody.appendChild(tr);
                    var nameCell = tr.querySelector('.scope-name-cell');
                    resolveScopeName(item.scope_type, item.scope_id, function(name){ nameCell.textContent = name; });
                });
            }
            openModal('scopesModal');
        })
        .catch(function(){ showNotification(t('messages.error', 'Error'), 'error'); });
}

function saveScope() {
    var discountId = document.getElementById('scopesDiscountId').value;
    var payload = {
        discount_id: parseInt(discountId),
        scope_type: document.getElementById('scopeType').value,
        scope_id: document.getElementById('scopeId').value
    };
    fetch('/api/discount_scopes', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
    .then(function(r){ return r.json(); })
    .then(function(d){
        if (d.success) {
            showNotification(t('messages.created', 'Saved'), 'success');
            document.getElementById('scopeId').value = '';
            openScopesModal(parseInt(discountId));
        } else {
            showNotification(d.message || t('messages.error', 'Error'), 'error');
        }
    })
    .catch(function(){ showNotification(t('messages.error', 'Error'), 'error'); });
}

function deleteScope(id) {
    if (!confirm(t('messages.confirm_delete', 'Delete?'))) return;
    fetch('/api/discount_scopes?id=' + id, { method: 'DELETE' })
        .then(function(r){ return r.json(); })
        .then(function(d){
            if (d.success) {
                showNotification(t('messages.deleted', 'Deleted'), 'success');
                openScopesModal(parseInt(document.getElementById('scopesDiscountId').value));
            } else showNotification(d.message || t('messages.error', 'Error'), 'error');
        })
        .catch(function(){ showNotification(t('messages.error', 'Error'), 'error'); });
}

/* ── Scope Name Lookup ── */
var _scopeLookupTimer = null;
function lookupScopeName() {
    var scopeType = document.getElementById('scopeType').value;
    var scopeId = document.getElementById('scopeId').value.trim();
    var nameEl = document.getElementById('scopeIdName');
    if (!nameEl) return;
    if (!scopeId || scopeType === 'all') { nameEl.textContent = ''; nameEl.className = 'lookup-name'; return; }
    var url = '';
    if (scopeType === 'entity') url = '/api/entities?id=' + encodeURIComponent(scopeId);
    else if (scopeType === 'product') url = '/api/products?id=' + encodeURIComponent(scopeId);
    else if (scopeType === 'category') url = '/api/categories?id=' + encodeURIComponent(scopeId);
    else { nameEl.textContent = ''; return; }
    nameEl.textContent = '...';
    nameEl.className = 'lookup-name';
    fetch(url).then(function(r){ return r.json(); }).then(function(d){
        if (d.success && d.data) {
            var item = Array.isArray(d.data) ? d.data[0] : d.data;
            var name = item ? (item.store_name || item.name || item.slug || '') : '';
            nameEl.textContent = name ? '✓ ' + name : t('messages.not_found', 'Not found');
            nameEl.className = name ? 'lookup-name' : 'lookup-name error';
        } else { nameEl.textContent = t('messages.not_found', 'Not found'); nameEl.className = 'lookup-name error'; }
    }).catch(function(){ nameEl.textContent = ''; });
}

function resolveScopeName(scopeType, scopeId, callback) {
    if (!scopeId || scopeType === 'all') { callback('—'); return; }
    var url = '';
    if (scopeType === 'entity') url = '/api/entities?id=' + encodeURIComponent(scopeId);
    else if (scopeType === 'product') url = '/api/products?id=' + encodeURIComponent(scopeId);
    else if (scopeType === 'category') url = '/api/categories?id=' + encodeURIComponent(scopeId);
    else { callback('—'); return; }
    fetch(url).then(function(r){ return r.json(); }).then(function(d){
        if (d.success && d.data) {
            var item = Array.isArray(d.data) ? d.data[0] : d.data;
            callback(item ? (item.store_name || item.name || item.slug || '—') : '—');
        } else callback('—');
    }).catch(function(){ callback('—'); });
}

/* ── Conditions ── */
function openConditionsModal(discountId) {
    currentDiscountId = discountId;
    document.getElementById('conditionsDiscountId').value = discountId;
    fetch('/api/discount_conditions?discount_id=' + discountId)
        .then(function(r){ return r.json(); })
        .then(function(d){
            var tbody = document.getElementById('conditionsBody');
            tbody.innerHTML = '';
            var items = d.success ? (Array.isArray(d.data) ? d.data : (d.data && d.data.items ? d.data.items : [])) : [];
            if (items.length === 0) {
                tbody.innerHTML = '<tr><td colspan="4" class="text-center">' + t('table.no_records', 'No records') + '</td></tr>';
            } else {
                items.forEach(function(item){
                    var tr = document.createElement('tr');
                    tr.innerHTML =
                        '<td>' + esc(item.condition_type || '') + '</td>' +
                        '<td>' + esc(item.operator || '') + '</td>' +
                        '<td>' + esc(String(item.condition_value || item.value || '')) + '</td>' +
                        '<td><button class="btn btn-sm btn-danger btn-delete-condition" data-id="' + item.id + '">' + t('delete', 'Delete') + '</button></td>';
                    tbody.appendChild(tr);
                });
            }
            openModal('conditionsModal');
        })
        .catch(function(){ showNotification(t('messages.error', 'Error'), 'error'); });
}

function saveCondition() {
    var discountId = document.getElementById('conditionsDiscountId').value;
    var payload = {
        discount_id: parseInt(discountId),
        condition_type: document.getElementById('conditionType').value,
        operator: document.getElementById('conditionOperator').value,
        condition_value: document.getElementById('conditionValue').value
    };
    fetch('/api/discount_conditions', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
    .then(function(r){ return r.json(); })
    .then(function(d){
        if (d.success) {
            showNotification(t('messages.created', 'Saved'), 'success');
            document.getElementById('conditionType').value = '';
            document.getElementById('conditionOperator').value = '';
            document.getElementById('conditionValue').value = '';
            openConditionsModal(parseInt(discountId));
        } else {
            showNotification(d.message || t('messages.error', 'Error'), 'error');
        }
    })
    .catch(function(){ showNotification(t('messages.error', 'Error'), 'error'); });
}

function deleteCondition(id) {
    if (!confirm(t('messages.confirm_delete', 'Delete?'))) return;
    fetch('/api/discount_conditions?id=' + id, { method: 'DELETE' })
        .then(function(r){ return r.json(); })
        .then(function(d){
            if (d.success) {
                showNotification(t('messages.deleted', 'Deleted'), 'success');
                openConditionsModal(parseInt(document.getElementById('conditionsDiscountId').value));
            } else showNotification(d.message || t('messages.error', 'Error'), 'error');
        })
        .catch(function(){ showNotification(t('messages.error', 'Error'), 'error'); });
}

/* ── Actions ── */
function openActionsModal(discountId) {
    currentDiscountId = discountId;
    document.getElementById('actionsDiscountId').value = discountId;
    fetch('/api/discount_actions?discount_id=' + discountId)
        .then(function(r){ return r.json(); })
        .then(function(d){
            var tbody = document.getElementById('actionsBody');
            tbody.innerHTML = '';
            var items = d.success ? (Array.isArray(d.data) ? d.data : (d.data && d.data.items ? d.data.items : [])) : [];
            if (items.length === 0) {
                tbody.innerHTML = '<tr><td colspan="3" class="text-center">' + t('table.no_records', 'No records') + '</td></tr>';
            } else {
                items.forEach(function(item){
                    var tr = document.createElement('tr');
                    tr.innerHTML =
                        '<td>' + esc(item.action_type || '') + '</td>' +
                        '<td>' + esc(String(item.action_value || '')) + '</td>' +
                        '<td><button class="btn btn-sm btn-danger btn-delete-action" data-id="' + item.id + '">' + t('delete', 'Delete') + '</button></td>';
                    tbody.appendChild(tr);
                });
            }
            openModal('actionsModal');
        })
        .catch(function(){ showNotification(t('messages.error', 'Error'), 'error'); });
}

function saveAction() {
    var discountId = document.getElementById('actionsDiscountId').value;
    var payload = {
        discount_id: parseInt(discountId),
        action_type: document.getElementById('actionType').value,
        action_value: document.getElementById('actionValue').value
    };
    fetch('/api/discount_actions', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
    .then(function(r){ return r.json(); })
    .then(function(d){
        if (d.success) {
            showNotification(t('messages.created', 'Saved'), 'success');
            document.getElementById('actionValue').value = '';
            openActionsModal(parseInt(discountId));
        } else {
            showNotification(d.message || t('messages.error', 'Error'), 'error');
        }
    })
    .catch(function(){ showNotification(t('messages.error', 'Error'), 'error'); });
}

function deleteAction(id) {
    if (!confirm(t('messages.confirm_delete', 'Delete?'))) return;
    fetch('/api/discount_actions?id=' + id, { method: 'DELETE' })
        .then(function(r){ return r.json(); })
        .then(function(d){
            if (d.success) {
                showNotification(t('messages.deleted', 'Deleted'), 'success');
                openActionsModal(parseInt(document.getElementById('actionsDiscountId').value));
            } else showNotification(d.message || t('messages.error', 'Error'), 'error');
        })
        .catch(function(){ showNotification(t('messages.error', 'Error'), 'error'); });
}

/* ── Exclusions ── */
function openExclusionsModal(discountId) {
    currentDiscountId = discountId;
    document.getElementById('exclusionsDiscountId').value = discountId;
    fetch('/api/discount_exclusions?discount_id=' + discountId)
        .then(function(r){ return r.json(); })
        .then(function(d){
            var tbody = document.getElementById('exclusionsBody');
            tbody.innerHTML = '';
            var items = d.success ? (Array.isArray(d.data) ? d.data : (d.data && d.data.items ? d.data.items : [])) : [];
            if (items.length === 0) {
                tbody.innerHTML = '<tr><td colspan="2" class="text-center">' + t('table.no_records', 'No records') + '</td></tr>';
            } else {
                items.forEach(function(item){
                    var tr = document.createElement('tr');
                    tr.innerHTML =
                        '<td>' + esc(item.excluded_discount_name || String(item.excluded_discount_id || '')) + '</td>' +
                        '<td><button class="btn btn-sm btn-danger btn-delete-exclusion" data-id="' + item.id + '">' + t('delete', 'Delete') + '</button></td>';
                    tbody.appendChild(tr);
                });
            }
            openModal('exclusionsModal');
        })
        .catch(function(){ showNotification(t('messages.error', 'Error'), 'error'); });

    // Load discounts for exclusion selector
    var sel = document.getElementById('excludeDiscountSelect');
    if (sel.options.length <= 1) {
        fetch('/api/discounts?limit=200')
            .then(function(r){ return r.json(); })
            .then(function(d){
                var items = d.success ? (d.data && d.data.items ? d.data.items : (Array.isArray(d.data) ? d.data : [])) : [];
                items.forEach(function(item){
                    if (item.id !== discountId) {
                        var opt = document.createElement('option');
                        opt.value = item.id;
                        opt.textContent = item.code || item.name || ('Discount #' + item.id);
                        sel.appendChild(opt);
                    }
                });
            })
            .catch(function(){});
    }
}

function saveExclusion() {
    var discountId = document.getElementById('exclusionsDiscountId').value;
    var payload = {
        discount_id: parseInt(discountId),
        excluded_discount_id: parseInt(document.getElementById('excludeDiscountSelect').value)
    };
    fetch('/api/discount_exclusions', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
    .then(function(r){ return r.json(); })
    .then(function(d){
        if (d.success) {
            showNotification(t('messages.created', 'Saved'), 'success');
            openExclusionsModal(parseInt(discountId));
        } else {
            showNotification(d.message || t('messages.error', 'Error'), 'error');
        }
    })
    .catch(function(){ showNotification(t('messages.error', 'Error'), 'error'); });
}

function deleteExclusion(id) {
    if (!confirm(t('messages.confirm_delete', 'Delete?'))) return;
    fetch('/api/discount_exclusions?id=' + id, { method: 'DELETE' })
        .then(function(r){ return r.json(); })
        .then(function(d){
            if (d.success) {
                showNotification(t('messages.deleted', 'Deleted'), 'success');
                openExclusionsModal(parseInt(document.getElementById('exclusionsDiscountId').value));
            } else showNotification(d.message || t('messages.error', 'Error'), 'error');
        })
        .catch(function(){ showNotification(t('messages.error', 'Error'), 'error'); });
}

/* ── Redemptions ── */
function openRedemptionsModal(discountId) {
    currentDiscountId = discountId;
    document.getElementById('redemptionsDiscountId').value = discountId;
    redemptionsPage = 1;
    loadRedemptions(discountId, 1);
    openModal('redemptionsModal');
}

function loadRedemptions(discountId, page) {
    redemptionsPage = page || 1;
    var offset = (redemptionsPage - 1) * PER_PAGE;
    fetch('/api/discount_redemptions?discount_id=' + discountId + '&limit=' + PER_PAGE + '&offset=' + offset)
        .then(function(r){ return r.json(); })
        .then(function(d){
            var tbody = document.getElementById('redemptionsBody');
            tbody.innerHTML = '';
            var items = d.success ? (Array.isArray(d.data) ? d.data : (d.data && d.data.items ? d.data.items : [])) : [];
            if (items.length === 0) {
                tbody.innerHTML = '<tr><td colspan="5" class="text-center">' + t('table.no_records', 'No records') + '</td></tr>';
                document.getElementById('redemptionsPaginationInfo').textContent = '';
                document.getElementById('redemptionsPagination').innerHTML = '';
            } else {
                items.forEach(function(item){
                    var tr = document.createElement('tr');
                    tr.innerHTML =
                        '<td>' + esc(String(item.user_id || '')) + '</td>' +
                        '<td>' + esc(String(item.order_id || '')) + '</td>' +
                        '<td>' + esc(String(item.amount_discounted || '')) + '</td>' +
                        '<td>' + esc(item.currency_code || '') + '</td>' +
                        '<td>' + esc(item.redeemed_at || '') + '</td>';
                    tbody.appendChild(tr);
                });
                renderRedemptionsPagination(d.data, discountId);
            }
        })
        .catch(function(){ showNotification(t('messages.error', 'Error'), 'error'); });
}

function renderRedemptionsPagination(meta, discountId) {
    var total = meta.total || 0;
    var totalPages = meta.total_pages || Math.ceil(total / PER_PAGE) || 1;
    var start = ((redemptionsPage - 1) * PER_PAGE) + 1;
    var end = Math.min(redemptionsPage * PER_PAGE, total);
    document.getElementById('redemptionsPaginationInfo').textContent = t('pagination.showing', 'Showing') + ' ' + start + '-' + end + ' ' + t('pagination.of', 'of') + ' ' + total;

    var pag = document.getElementById('redemptionsPagination');
    pag.innerHTML = '';
    if (totalPages <= 1) return;

    var prev = document.createElement('button');
    prev.className = 'btn btn-sm' + (redemptionsPage <= 1 ? ' disabled' : '');
    prev.textContent = '‹';
    prev.disabled = redemptionsPage <= 1;
    prev.addEventListener('click', function(){ if(redemptionsPage > 1) loadRedemptions(discountId, redemptionsPage - 1); });
    pag.appendChild(prev);

    for (var i = 1; i <= totalPages; i++) {
        if (totalPages > 7 && i > 2 && i < totalPages - 1 && Math.abs(i - redemptionsPage) > 1) {
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
            btn.className = 'btn btn-sm' + (pageNum === redemptionsPage ? ' btn-primary active' : '');
            btn.textContent = String(pageNum);
            btn.addEventListener('click', function(){ loadRedemptions(discountId, pageNum); });
            pag.appendChild(btn);
        })(i);
    }

    var next = document.createElement('button');
    next.className = 'btn btn-sm' + (redemptionsPage >= totalPages ? ' disabled' : '');
    next.textContent = '›';
    next.disabled = redemptionsPage >= totalPages;
    next.addEventListener('click', function(){ if(redemptionsPage < totalPages) loadRedemptions(discountId, redemptionsPage + 1); });
    pag.appendChild(next);
}

/* ── Entity Options ── */
/* ── Tenant/Entity Cascade ── */
function verifyTenant() {
    var input = document.getElementById('tenantIdInput');
    var nameEl = document.getElementById('tenantName');
    if (!input) return;
    var tid = parseInt(input.value);
    if (!tid || tid < 1) {
        nameEl.style.display = 'none';
        return;
    }
    fetch('/api/tenants?id=' + tid)
        .then(function(r){ return r.json(); })
        .then(function(d){
            var tData = null;
            if (d.success && d.data) {
                if (d.data.items && Array.isArray(d.data.items)) {
                    for (var i = 0; i < d.data.items.length; i++) {
                        if (parseInt(d.data.items[i].id) === tid) { tData = d.data.items[i]; break; }
                    }
                    if (!tData && d.data.items.length > 0) tData = d.data.items[0];
                } else if (d.data.name || d.data.id) {
                    tData = d.data;
                }
            }
            if (tData) {
                var name = tData.name || tData.domain || 'Tenant #' + tid;
                nameEl.textContent = '✓ ' + name;
                nameEl.style.display = 'block';
                nameEl.style.color = 'var(--success-color,#28a745)';
                loadEntitiesByTenant(tid);
            } else {
                nameEl.textContent = '✗ ' + t('tenant_not_found', 'Tenant not found');
                nameEl.style.display = 'block';
                nameEl.style.color = 'var(--danger-color,#dc3545)';
            }
        })
        .catch(function(){
            nameEl.textContent = '✗ ' + t('error', 'Error');
            nameEl.style.display = 'block';
            nameEl.style.color = 'var(--danger-color,#dc3545)';
        });
}

function loadEntitiesByTenant(tenantId) {
    var selectors = [document.getElementById('entitySelector'), document.getElementById('entitySelect')];
    var url = '/api/entities?limit=200';
    if (tenantId) url += '&tenant_id=' + tenantId;
    fetch(url)
        .then(function(r){ return r.json(); })
        .then(function(d){
            var items = d.success ? (d.data && d.data.items ? d.data.items : (Array.isArray(d.data) ? d.data : [])) : [];
            selectors.forEach(function(sel){
                if (!sel) return;
                // Keep first "All Entities" option, remove the rest
                while (sel.options.length > 1) sel.remove(1);
                items.forEach(function(e){
                    var opt = document.createElement('option');
                    opt.value = e.id;
                    opt.textContent = e.store_name || e.name || ('Entity #' + e.id);
                    sel.appendChild(opt);
                });
            });
            // Auto-select if only 1 entity
            if (items.length === 1) {
                selectors.forEach(function(sel){
                    if (sel && sel.options.length === 2) sel.selectedIndex = 1;
                });
            }
        })
        .catch(function(){});
}

function loadEntityOptions() {
    var selectors = [document.getElementById('entitySelector'), document.getElementById('entitySelect')];
    selectors.forEach(function(sel){
        if (!sel || sel.options.length > 1) return;
    });
    fetch('/api/entities?limit=100')
        .then(function(r){ return r.json(); })
        .then(function(d){
            var items = d.success ? (d.data && d.data.items ? d.data.items : (Array.isArray(d.data) ? d.data : [])) : [];
            selectors.forEach(function(sel){
                if (!sel || sel.options.length > 1) return;
                items.forEach(function(e){
                    var opt = document.createElement('option');
                    opt.value = e.id;
                    opt.textContent = e.store_name || e.name || e.id;
                    sel.appendChild(opt);
                });
            });
        })
        .catch(function(){});
}

/* ── Init ── */
function init() {
    reloadConfig();
    loadStats();
    loadCurrencies();

    // Tenant/Entity cascade based on role
    if (CFG.isSuperAdmin) {
        // Super admin: show tenant input, load all entities initially
        loadEntityOptions();
        var btnVerify = document.getElementById('btnVerifyTenant');
        if (btnVerify) {
            btnVerify.addEventListener('click', verifyTenant);
            // Also verify on Enter key
            var tenantInput = document.getElementById('tenantIdInput');
            if (tenantInput) {
                tenantInput.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') { e.preventDefault(); verifyTenant(); }
                });
            }
        }
    } else if (CFG.tenantId) {
        // Tenant admin: load their entities
        loadEntitiesByTenant(CFG.tenantId);
    } else if (CFG.entityId) {
        // Entity user: auto-set entity
        var sel = document.getElementById('entitySelector');
        if (sel) {
            var opt = document.createElement('option');
            opt.value = CFG.entityId;
            opt.textContent = t('my_entity', 'My Entity');
            opt.selected = true;
            sel.appendChild(opt);
            sel.disabled = true;
        }
        var formSel = document.getElementById('entitySelect');
        if (formSel) {
            var formOpt = document.createElement('option');
            formOpt.value = CFG.entityId;
            formOpt.textContent = t('my_entity', 'My Entity');
            formOpt.selected = true;
            formSel.appendChild(formOpt);
            formSel.disabled = true;
        }
    } else {
        loadEntityOptions();
    }

    loadDiscounts(1);

    // Add button
    document.getElementById('btnAddDiscount').addEventListener('click', function(){
        document.getElementById('discountForm').reset();
        document.getElementById('discountId').value = '';
        document.getElementById('modalTitle').textContent = t('modal.add_title', 'Add Discount');
        openModal('discountModal');
    });

    // Generate code
    document.getElementById('btnGenerateCode').addEventListener('click', function(){
        document.getElementById('discountCode').value = generateCode();
    });

    // Close modals
    document.getElementById('btnCloseModal').addEventListener('click', function(){ closeModal('discountModal'); });
    document.getElementById('btnCancelModal').addEventListener('click', function(){ closeModal('discountModal'); });
    document.getElementById('btnCloseTranslations').addEventListener('click', function(){ closeModal('translationsModal'); });
    document.getElementById('btnCloseScopes').addEventListener('click', function(){ closeModal('scopesModal'); });
    document.getElementById('btnCloseConditions').addEventListener('click', function(){ closeModal('conditionsModal'); });
    document.getElementById('btnCloseActions').addEventListener('click', function(){ closeModal('actionsModal'); });
    document.getElementById('btnCloseExclusions').addEventListener('click', function(){ closeModal('exclusionsModal'); });
    document.getElementById('btnCloseRedemptions').addEventListener('click', function(){ closeModal('redemptionsModal'); });

    // Form submit
    document.getElementById('discountForm').addEventListener('submit', saveDiscount);

    // Filter
    document.getElementById('btnFilter').addEventListener('click', function(){
        currentFilters = {
            search: document.getElementById('searchInput').value,
            type: document.getElementById('typeFilter').value,
            status: document.getElementById('statusFilter').value,
            date_from: document.getElementById('dateFrom').value,
            date_to: document.getElementById('dateTo').value
        };
        loadDiscounts(1);
    });
    document.getElementById('btnClearFilter').addEventListener('click', function(){
        document.getElementById('searchInput').value = '';
        document.getElementById('typeFilter').value = '';
        document.getElementById('statusFilter').value = '';
        document.getElementById('dateFrom').value = '';
        document.getElementById('dateTo').value = '';
        currentFilters = {};
        loadDiscounts(1);
    });

    // Entity selector change
    document.getElementById('entitySelector').addEventListener('change', function(){
        loadDiscounts(1);
        loadStats();
    });

    // Save sub-entity buttons
    document.getElementById('btnSaveTranslation').addEventListener('click', saveTranslation);
    document.getElementById('btnAddScope').addEventListener('click', saveScope);
    document.getElementById('scopeId').addEventListener('input', function(){
        clearTimeout(_scopeLookupTimer);
        _scopeLookupTimer = setTimeout(lookupScopeName, 400);
    });
    document.getElementById('scopeType').addEventListener('change', function(){
        var label = document.getElementById('scopeIdLabel');
        var type = this.value;
        if (label) {
            var labels = { product: t('scopes.product_id', 'Product ID'), category: t('scopes.category_id', 'Category ID'), entity: t('scopes.entity_id', 'Entity ID'), brand: t('scopes.brand', 'Brand'), all: t('scopes.scope_id', 'Scope ID') };
            label.textContent = labels[type] || t('scopes.scope_id', 'Scope ID');
        }
        lookupScopeName();
    });
    document.getElementById('btnAddCondition').addEventListener('click', saveCondition);
    document.getElementById('btnAddAction').addEventListener('click', saveAction);
    document.getElementById('btnAddExclusion').addEventListener('click', saveExclusion);

    // Delegated click events
    document.addEventListener('click', function(e){
        var target = e.target;
        if (target.classList.contains('btn-edit')) { editDiscount(parseInt(target.getAttribute('data-id'))); }
        else if (target.classList.contains('btn-delete')) { deleteDiscount(parseInt(target.getAttribute('data-id'))); }
        else if (target.classList.contains('btn-translations')) { openTranslationsModal(parseInt(target.getAttribute('data-id'))); }
        else if (target.classList.contains('btn-scopes')) { openScopesModal(parseInt(target.getAttribute('data-id'))); }
        else if (target.classList.contains('btn-conditions')) { openConditionsModal(parseInt(target.getAttribute('data-id'))); }
        else if (target.classList.contains('btn-actions')) { openActionsModal(parseInt(target.getAttribute('data-id'))); }
        else if (target.classList.contains('btn-exclusions')) { openExclusionsModal(parseInt(target.getAttribute('data-id'))); }
        else if (target.classList.contains('btn-redemptions')) { openRedemptionsModal(parseInt(target.getAttribute('data-id'))); }
        else if (target.classList.contains('btn-delete-trans')) { deleteTranslation(parseInt(target.getAttribute('data-id'))); }
        else if (target.classList.contains('btn-delete-scope')) { deleteScope(parseInt(target.getAttribute('data-id'))); }
        else if (target.classList.contains('btn-delete-condition')) { deleteCondition(parseInt(target.getAttribute('data-id'))); }
        else if (target.classList.contains('btn-delete-action')) { deleteAction(parseInt(target.getAttribute('data-id'))); }
        else if (target.classList.contains('btn-delete-exclusion')) { deleteExclusion(parseInt(target.getAttribute('data-id'))); }
    });
}

// Fragment-compatible init
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
} else {
    init();
}

})();