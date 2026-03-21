(function(){
'use strict';

var CFG = {};
var S = {};
var PER_PAGE = 25;
var activeTab = 'transactions';
var pages = { transactions: 1, invoices: 1, payments: 1, credit_notes: 1, balances: 1 };
var filters = { transactions: {}, invoices: {}, payments: {}, credit_notes: {}, balances: {} };

function reloadConfig() {
    CFG = window.COMMISSIONS_CONFIG || {};
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

function formatCurrency(amount, currency) {
    var n = parseFloat(amount) || 0;
    return n.toFixed(2) + ' ' + (currency || 'SAR');
}

function formatDate(dateStr) {
    if (!dateStr) return '-';
    return String(dateStr).substring(0, 10);
}

/* ── Tabs ── */
function switchTab(tab) {
    activeTab = tab;
    var btns = document.querySelectorAll('.tab-btn');
    var contents = document.querySelectorAll('.tab-content');
    for (var i = 0; i < btns.length; i++) {
        btns[i].classList.toggle('active', btns[i].getAttribute('data-tab') === tab);
    }
    var tabMap = {
        transactions: 'tabTransactions',
        invoices: 'tabInvoices',
        payments: 'tabPayments',
        credit_notes: 'tabCreditNotes',
        balances: 'tabBalances'
    };
    for (var j = 0; j < contents.length; j++) {
        contents[j].classList.toggle('active', contents[j].id === tabMap[tab]);
    }
    var addBtn = document.getElementById('btnAddItem');
    if (addBtn) {
        var labels = {
            transactions: t('add_transaction', 'Add Transaction'),
            invoices: t('add_invoice', 'Add Invoice'),
            payments: t('record_payment', 'Record Payment'),
            credit_notes: t('add_credit_note', 'Add Credit Note'),
            balances: ''
        };
        if (tab === 'balances') {
            addBtn.style.display = 'none';
        } else {
            addBtn.style.display = '';
            addBtn.textContent = '+ ' + labels[tab];
        }
    }
    loadTabData(tab);
}

function loadTabData(tab) {
    if (tab === 'transactions') { loadTransactionStats(); loadTransactions(pages.transactions); }
    else if (tab === 'invoices') { loadInvoiceStats(); loadInvoices(pages.invoices); }
    else if (tab === 'payments') { loadPayments(pages.payments); }
    else if (tab === 'credit_notes') { loadCreditNotes(pages.credit_notes); }
    else if (tab === 'balances') { loadBalances(pages.balances); }
}

/* ── Pagination helper ── */
function renderPagination(containerId, infoId, total, currentPage, loadFn) {
    var totalPages = Math.ceil(total / PER_PAGE) || 1;
    var start = ((currentPage - 1) * PER_PAGE) + 1;
    var end = Math.min(currentPage * PER_PAGE, total);
    var info = document.getElementById(infoId);
    if (info) info.textContent = t('pagination.showing', 'Showing') + ' ' + start + '-' + end + ' ' + t('pagination.of', 'of') + ' ' + total;

    var pag = document.getElementById(containerId);
    if (!pag) return;
    pag.innerHTML = '';
    if (totalPages <= 1) return;

    var prev = document.createElement('button');
    prev.className = 'btn btn-sm' + (currentPage <= 1 ? ' disabled' : '');
    prev.textContent = '\u2039';
    prev.disabled = currentPage <= 1;
    prev.addEventListener('click', function(){ if(currentPage > 1) loadFn(currentPage - 1); });
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
        (function(pn){
            var btn = document.createElement('button');
            btn.className = 'btn btn-sm' + (pn === currentPage ? ' btn-primary active' : '');
            btn.textContent = String(pn);
            btn.addEventListener('click', function(){ loadFn(pn); });
            pag.appendChild(btn);
        })(i);
    }

    var next = document.createElement('button');
    next.className = 'btn btn-sm' + (currentPage >= totalPages ? ' disabled' : '');
    next.textContent = '\u203A';
    next.disabled = currentPage >= totalPages;
    next.addEventListener('click', function(){ if(currentPage < totalPages) loadFn(currentPage + 1); });
    pag.appendChild(next);
}

function statusBadge(status) {
    var map = {
        pending: 'badge-warning', invoiced: 'badge-info', paid: 'badge-success',
        cancelled: 'badge-secondary', draft: 'badge-secondary', issued: 'badge-info',
        partially_paid: 'badge-warning', overdue: 'badge-danger', void: 'badge-secondary'
    };
    return '<span class="badge ' + (map[status] || 'badge-secondary') + '">' + esc(status || '') + '</span>';
}

/* ══════════════════════════════════════
   TRANSACTIONS
   ══════════════════════════════════════ */
function loadTransactionStats() {
    fetch(CFG.apiBase + '/commission_transactions?stats=1')
        .then(function(r){ return r.json(); })
        .then(function(d){
            if (d.success && d.data) {
                document.getElementById('txnStatTotal').textContent = d.data.total || 0;
                document.getElementById('txnStatPending').textContent = d.data.pending || 0;
                document.getElementById('txnStatInvoiced').textContent = d.data.invoiced || 0;
                document.getElementById('txnStatPaid').textContent = d.data.paid || 0;
                document.getElementById('txnStatCancelled').textContent = d.data.cancelled || 0;
            }
        }).catch(function(){});
}

function loadTransactions(page) {
    pages.transactions = page || 1;
    var offset = (pages.transactions - 1) * PER_PAGE;
    var url = CFG.apiBase + '/commission_transactions?limit=' + PER_PAGE + '&offset=' + offset;
    var f = filters.transactions;
    if (f.entity_id) url += '&entity_id=' + encodeURIComponent(f.entity_id);
    if (f.status) url += '&status=' + encodeURIComponent(f.status);
    if (f.transaction_type) url += '&transaction_type=' + encodeURIComponent(f.transaction_type);
    if (f.date_from) url += '&date_from=' + encodeURIComponent(f.date_from);
    if (f.date_to) url += '&date_to=' + encodeURIComponent(f.date_to);

    fetch(url)
        .then(function(r){ return r.json(); })
        .then(function(d){
            var tbody = document.getElementById('txnBody');
            tbody.innerHTML = '';
            if (d.success && d.data && d.data.items && d.data.items.length > 0) {
                d.data.items.forEach(function(item){
                    var tr = document.createElement('tr');
                    tr.innerHTML =
                        '<td>' + esc(String(item.id)) + '</td>' +
                        '<td>' + esc(String(item.entity_id || '')) + '</td>' +
                        '<td>' + esc(String(item.order_id || '-')) + '</td>' +
                        '<td>' + esc(item.transaction_type || '') + '</td>' +
                        '<td>' + formatCurrency(item.base_amount, item.currency_code) + '</td>' +
                        '<td>' + esc(String(item.commission_rate || 0)) + '%</td>' +
                        '<td>' + formatCurrency(item.commission_amount, item.currency_code) + '</td>' +
                        '<td>' + statusBadge(item.status) + '</td>' +
                        '<td>' + formatDate(item.created_at) + '</td>' +
                        '<td class="actions-cell">' +
                            '<button class="btn btn-sm btn-primary btn-txn-edit" data-id="' + item.id + '">' + t('edit', 'Edit') + '</button> ' +
                            '<button class="btn btn-sm btn-danger btn-txn-delete" data-id="' + item.id + '">' + t('delete', 'Delete') + '</button>' +
                        '</td>';
                    tbody.appendChild(tr);
                });
                renderPagination('txnPagination', 'txnPaginationInfo', d.data.meta.total, pages.transactions, loadTransactions);
            } else {
                tbody.innerHTML = '<tr><td colspan="10" class="text-center">' + t('table.no_records', 'No records found') + '</td></tr>';
                document.getElementById('txnPaginationInfo').textContent = '';
                document.getElementById('txnPagination').innerHTML = '';
            }
        }).catch(function(){ showNotification(t('messages.error', 'Error'), 'error'); });
}

function saveTransaction(e) {
    e.preventDefault();
    var id = document.getElementById('txnId').value;
    var payload = {
        tenant_id: CFG.tenantId || 1,
        entity_id: parseInt(document.getElementById('txnEntityId').value) || 0,
        order_id: document.getElementById('txnOrderId').value ? parseInt(document.getElementById('txnOrderId').value) : null,
        order_date: document.getElementById('txnOrderDate').value || new Date().toISOString().slice(0, 19).replace('T', ' '),
        transaction_type: document.getElementById('txnType').value,
        order_amount: parseFloat(document.getElementById('txnOrderAmount').value) || 0,
        commission_amount: parseFloat(document.getElementById('txnCommissionAmount').value) || 0,
        vat_amount: parseFloat(document.getElementById('txnVatAmount').value) || 0,
        net_commission: parseFloat(document.getElementById('txnNetCommission').value) || 0,
        currency_code: document.getElementById('txnCurrency').value || 'SAR',
        status: document.getElementById('txnStatus').value
    };
    if (id) payload.id = parseInt(id);

    fetch(CFG.apiBase + '/commission_transactions', {
        method: id ? 'PUT' : 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
    .then(function(r){ return r.json(); })
    .then(function(d){
        if (d.success) {
            closeModal('txnModal');
            showNotification(t('messages.' + (id ? 'updated' : 'created'), id ? 'Updated' : 'Created'), 'success');
            loadTransactions(pages.transactions);
            loadTransactionStats();
        } else {
            showNotification(d.message || t('messages.error', 'Error'), 'error');
        }
    }).catch(function(){ showNotification(t('messages.error', 'Error'), 'error'); });
}

function editTransaction(id) {
    fetch(CFG.apiBase + '/commission_transactions?id=' + id)
        .then(function(r){ return r.json(); })
        .then(function(d){
            if (d.success && d.data) {
                var item = d.data;
                document.getElementById('txnId').value = item.id;
                document.getElementById('txnEntityId').value = item.entity_id || '';
                document.getElementById('txnOrderId').value = item.order_id || '';
                document.getElementById('txnOrderDate').value = item.order_date ? item.order_date.replace(' ', 'T').slice(0, 16) : '';
                document.getElementById('txnType').value = item.transaction_type || 'sale';
                document.getElementById('txnOrderAmount').value = item.order_amount || 0;
                document.getElementById('txnCommissionAmount').value = item.commission_amount || 0;
                document.getElementById('txnVatAmount').value = item.vat_amount || 0;
                document.getElementById('txnNetCommission').value = item.net_commission || 0;
                document.getElementById('txnCurrency').value = item.currency_code || 'SAR';
                document.getElementById('txnStatus').value = item.status || 'pending';
                document.getElementById('txnModalTitle').textContent = t('modal.edit_transaction', 'Edit Commission Transaction');
                openModal('txnModal');
            }
        }).catch(function(){ showNotification(t('messages.error', 'Error'), 'error'); });
}

function deleteTransaction(id) {
    if (!confirm(t('messages.confirm_delete', 'Are you sure?'))) return;
    fetch(CFG.apiBase + '/commission_transactions?id=' + id, { method: 'DELETE' })
        .then(function(r){ return r.json(); })
        .then(function(d){
            if (d.success) {
                showNotification(t('messages.deleted', 'Deleted'), 'success');
                loadTransactions(pages.transactions);
                loadTransactionStats();
            } else {
                showNotification(d.message || t('messages.error', 'Error'), 'error');
            }
        }).catch(function(){ showNotification(t('messages.error', 'Error'), 'error'); });
}

/* ══════════════════════════════════════
   INVOICES
   ══════════════════════════════════════ */
function loadInvoiceStats() {
    fetch(CFG.apiBase + '/commission_invoices?stats=1')
        .then(function(r){ return r.json(); })
        .then(function(d){
            if (d.success && d.data) {
                document.getElementById('cinvStatTotal').textContent = d.data.total || 0;
                document.getElementById('cinvStatPaid').textContent = d.data.paid || 0;
                document.getElementById('cinvStatPending').textContent = d.data.pending || 0;
                document.getElementById('cinvStatOverdue').textContent = d.data.overdue || 0;
            }
        }).catch(function(){});
}

function loadInvoices(page) {
    pages.invoices = page || 1;
    var offset = (pages.invoices - 1) * PER_PAGE;
    var url = CFG.apiBase + '/commission_invoices?limit=' + PER_PAGE + '&offset=' + offset;
    var f = filters.invoices;
    if (f.entity_id) url += '&entity_id=' + encodeURIComponent(f.entity_id);
    if (f.status) url += '&status=' + encodeURIComponent(f.status);
    if (f.invoice_type) url += '&invoice_type=' + encodeURIComponent(f.invoice_type);

    fetch(url)
        .then(function(r){ return r.json(); })
        .then(function(d){
            var tbody = document.getElementById('cinvBody');
            tbody.innerHTML = '';
            if (d.success && d.data && d.data.items && d.data.items.length > 0) {
                d.data.items.forEach(function(item){
                    var tr = document.createElement('tr');
                    tr.innerHTML =
                        '<td>' + esc(String(item.id)) + '</td>' +
                        '<td><strong>' + esc(item.invoice_number || '') + '</strong></td>' +
                        '<td>' + esc(String(item.entity_id || '')) + '</td>' +
                        '<td>' + esc(item.invoice_type || '') + '</td>' +
                        '<td>' + formatCurrency(item.grand_total || item.total_amount, item.currency_code) + '</td>' +
                        '<td>' + formatCurrency(item.total_vat || item.tax_amount, item.currency_code) + '</td>' +
                        '<td>' + statusBadge(item.status) + '</td>' +
                        '<td>' + formatDate(item.due_date) + '</td>' +
                        '<td>' + formatDate(item.paid_at) + '</td>' +
                        '<td class="actions-cell">' +
                            '<button class="btn btn-sm btn-primary btn-cinv-edit" data-id="' + item.id + '">' + t('edit', 'Edit') + '</button> ' +
                            '<button class="btn btn-sm btn-danger btn-cinv-delete" data-id="' + item.id + '">' + t('delete', 'Delete') + '</button>' +
                        '</td>';
                    tbody.appendChild(tr);
                });
                renderPagination('cinvPagination', 'cinvPaginationInfo', d.data.meta.total, pages.invoices, loadInvoices);
            } else {
                tbody.innerHTML = '<tr><td colspan="10" class="text-center">' + t('table.no_records', 'No records found') + '</td></tr>';
                document.getElementById('cinvPaginationInfo').textContent = '';
                document.getElementById('cinvPagination').innerHTML = '';
            }
        }).catch(function(){ showNotification(t('messages.error', 'Error'), 'error'); });
}

function saveInvoice(e) {
    e.preventDefault();
    var id = document.getElementById('cinvId').value;
    var totalAmount = parseFloat(document.getElementById('cinvTotalAmount').value) || 0;
    var taxAmount = parseFloat(document.getElementById('cinvTaxAmount').value) || 0;
    var payload = {
        tenant_id: parseInt(CFG.tenantId) || 1,
        entity_id: parseInt(document.getElementById('cinvEntityId').value) || 0,
        invoice_number: document.getElementById('cinvInvoiceNumber').value || null,
        invoice_type: document.getElementById('cinvInvoiceType').value,
        total_commission: totalAmount,
        total_vat: taxAmount,
        grand_total: totalAmount + taxAmount,
        currency_code: document.getElementById('cinvCurrency').value || 'SAR',
        due_date: document.getElementById('cinvDueDate').value || null,
        period_start: document.getElementById('cinvPeriodStart').value || null,
        period_end: document.getElementById('cinvPeriodEnd').value || null,
        status: document.getElementById('cinvStatus').value,
        notes: document.getElementById('cinvNotes').value || null
    };
    if (id) payload.id = parseInt(id);

    fetch(CFG.apiBase + '/commission_invoices', {
        method: id ? 'PUT' : 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
    .then(function(r){ return r.json(); })
    .then(function(d){
        if (d.success) {
            closeModal('cinvModal');
            showNotification(t('messages.' + (id ? 'updated' : 'created'), id ? 'Updated' : 'Created'), 'success');
            loadInvoices(pages.invoices);
            loadInvoiceStats();
        } else {
            showNotification(d.message || t('messages.error', 'Error'), 'error');
        }
    }).catch(function(){ showNotification(t('messages.error', 'Error'), 'error'); });
}

function editInvoice(id) {
    fetch(CFG.apiBase + '/commission_invoices?id=' + id)
        .then(function(r){ return r.json(); })
        .then(function(d){
            if (d.success && d.data) {
                var item = d.data;
                document.getElementById('cinvId').value = item.id;
                document.getElementById('cinvEntityId').value = item.entity_id || '';
                document.getElementById('cinvInvoiceNumber').value = item.invoice_number || '';
                document.getElementById('cinvInvoiceType').value = item.invoice_type || 'monthly';
                document.getElementById('cinvTotalAmount').value = item.grand_total || item.total_amount || 0;
                document.getElementById('cinvTaxAmount').value = item.total_vat || item.tax_amount || 0;
                document.getElementById('cinvCurrency').value = item.currency_code || 'SAR';
                document.getElementById('cinvDueDate').value = (item.due_date || '').substring(0, 10);
                document.getElementById('cinvPeriodStart').value = (item.period_start || '').substring(0, 10);
                document.getElementById('cinvPeriodEnd').value = (item.period_end || '').substring(0, 10);
                document.getElementById('cinvStatus').value = item.status || 'draft';
                document.getElementById('cinvNotes').value = item.notes || '';
                document.getElementById('cinvModalTitle').textContent = t('modal.edit_invoice', 'Edit Commission Invoice');
                openModal('cinvModal');
            }
        }).catch(function(){ showNotification(t('messages.error', 'Error'), 'error'); });
}

function deleteInvoice(id) {
    if (!confirm(t('messages.confirm_delete', 'Are you sure?'))) return;
    fetch(CFG.apiBase + '/commission_invoices?id=' + id, { method: 'DELETE' })
        .then(function(r){ return r.json(); })
        .then(function(d){
            if (d.success) {
                showNotification(t('messages.deleted', 'Deleted'), 'success');
                loadInvoices(pages.invoices);
                loadInvoiceStats();
            } else {
                showNotification(d.message || t('messages.error', 'Error'), 'error');
            }
        }).catch(function(){ showNotification(t('messages.error', 'Error'), 'error'); });
}

function generateInvoiceNumber() {
    var ts = Date.now().toString(36).toUpperCase();
    var rand = Math.random().toString(36).substring(2, 6).toUpperCase();
    document.getElementById('cinvInvoiceNumber').value = 'CINV-' + ts + '-' + rand;
    showNotification(t('messages.invoice_generated', 'Invoice number generated'), 'info');
}

/* ══════════════════════════════════════
   PAYMENTS
   ══════════════════════════════════════ */
function loadPayments(page) {
    pages.payments = page || 1;
    var offset = (pages.payments - 1) * PER_PAGE;
    var url = CFG.apiBase + '/commission_payments?limit=' + PER_PAGE + '&offset=' + offset;
    var f = filters.payments;
    if (f.entity_id) url += '&entity_id=' + encodeURIComponent(f.entity_id);
    if (f.invoice_id) url += '&commission_invoice_id=' + encodeURIComponent(f.invoice_id);
    if (f.is_cancelled !== undefined && f.is_cancelled !== '') url += '&is_cancelled=' + encodeURIComponent(f.is_cancelled);

    fetch(url)
        .then(function(r){ return r.json(); })
        .then(function(d){
            var tbody = document.getElementById('payBody');
            tbody.innerHTML = '';
            if (d.success && d.data && d.data.items && d.data.items.length > 0) {
                d.data.items.forEach(function(item){
                    var tr = document.createElement('tr');
                    var cancelledBadge = parseInt(item.is_cancelled) ?
                        '<span class="badge badge-danger">' + t('yes', 'Yes') + '</span>' :
                        '<span class="badge badge-success">' + t('no', 'No') + '</span>';
                    tr.innerHTML =
                        '<td>' + esc(String(item.id)) + '</td>' +
                        '<td>' + esc(String(item.commission_invoice_id || '-')) + '</td>' +
                        '<td>' + esc(String(item.entity_id || '')) + '</td>' +
                        '<td>' + formatCurrency(item.amount_paid, item.currency_code) + '</td>' +
                        '<td>' + esc(item.payment_method || '-') + '</td>' +
                        '<td>' + esc(item.payment_number || '-') + '</td>' +
                        '<td>' + formatDate(item.paid_at) + '</td>' +
                        '<td>' + cancelledBadge + '</td>' +
                        '<td class="actions-cell">' +
                            '<button class="btn btn-sm btn-primary btn-pay-edit" data-id="' + item.id + '">' + t('edit', 'Edit') + '</button> ' +
                            '<button class="btn btn-sm btn-danger btn-pay-delete" data-id="' + item.id + '">' + t('delete', 'Delete') + '</button>' +
                        '</td>';
                    tbody.appendChild(tr);
                });
                renderPagination('payPagination', 'payPaginationInfo', d.data.meta.total, pages.payments, loadPayments);
            } else {
                tbody.innerHTML = '<tr><td colspan="9" class="text-center">' + t('table.no_records', 'No records found') + '</td></tr>';
                document.getElementById('payPaginationInfo').textContent = '';
                document.getElementById('payPagination').innerHTML = '';
            }
        }).catch(function(){ showNotification(t('messages.error', 'Error'), 'error'); });
}

function recordPayment(e) {
    e.preventDefault();
    var id = document.getElementById('payId').value;
    var payDate = document.getElementById('payDate').value;
    var payload = {
        tenant_id: parseInt(CFG.tenantId) || 1,
        entity_id: parseInt(document.getElementById('payEntityId').value) || 0,
        commission_invoice_id: parseInt(document.getElementById('payInvoiceId').value) || 0,
        amount_paid: parseFloat(document.getElementById('payAmount').value) || 0,
        currency_code: document.getElementById('payCurrency').value || 'SAR',
        payment_method: document.getElementById('payMethod').value || null,
        payment_number: document.getElementById('payReference').value || ('CPAY-' + Date.now().toString(36).toUpperCase()),
        paid_at: payDate ? payDate + ' 00:00:00' : new Date().toISOString().substring(0, 19).replace('T', ' ')
    };
    if (id) payload.id = parseInt(id);

    fetch(CFG.apiBase + '/commission_payments', {
        method: id ? 'PUT' : 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
    .then(function(r){ return r.json(); })
    .then(function(d){
        if (d.success) {
            closeModal('payModal');
            showNotification(t('messages.' + (id ? 'updated' : 'payment_recorded'), id ? 'Updated' : 'Payment recorded'), 'success');
            loadPayments(pages.payments);
        } else {
            showNotification(d.message || t('messages.error', 'Error'), 'error');
        }
    }).catch(function(){ showNotification(t('messages.error', 'Error'), 'error'); });
}

function editPayment(id) {
    fetch(CFG.apiBase + '/commission_payments?id=' + id)
        .then(function(r){ return r.json(); })
        .then(function(d){
            if (d.success && d.data) {
                var item = d.data;
                document.getElementById('payId').value = item.id;
                document.getElementById('payEntityId').value = item.entity_id || '';
                document.getElementById('payInvoiceId').value = item.commission_invoice_id || '';
                document.getElementById('payAmount').value = item.amount_paid || item.amount || 0;
                document.getElementById('payCurrency').value = item.currency_code || 'SAR';
                document.getElementById('payMethod').value = item.payment_method || '';
                document.getElementById('payReference').value = item.payment_number || '';
                document.getElementById('payDate').value = (item.paid_at || '').substring(0, 10);
                document.getElementById('payModalTitle').textContent = t('modal.edit_payment', 'Edit Payment');
                openModal('payModal');
            }
        }).catch(function(){ showNotification(t('messages.error', 'Error'), 'error'); });
}

function deletePayment(id) {
    if (!confirm(t('messages.confirm_delete', 'Are you sure?'))) return;
    fetch(CFG.apiBase + '/commission_payments?id=' + id, { method: 'DELETE' })
        .then(function(r){ return r.json(); })
        .then(function(d){
            if (d.success) {
                showNotification(t('messages.deleted', 'Deleted'), 'success');
                loadPayments(pages.payments);
            } else {
                showNotification(d.message || t('messages.error', 'Error'), 'error');
            }
        }).catch(function(){ showNotification(t('messages.error', 'Error'), 'error'); });
}

/* ══════════════════════════════════════
   CREDIT NOTES
   ══════════════════════════════════════ */
function loadCreditNotes(page) {
    pages.credit_notes = page || 1;
    var offset = (pages.credit_notes - 1) * PER_PAGE;
    var url = CFG.apiBase + '/commission_credit_notes?limit=' + PER_PAGE + '&offset=' + offset;
    var f = filters.credit_notes;
    if (f.tenant_id) url += '&tenant_id=' + encodeURIComponent(f.tenant_id);
    if (f.status) url += '&status=' + encodeURIComponent(f.status);

    fetch(url)
        .then(function(r){ return r.json(); })
        .then(function(d){
            var tbody = document.getElementById('cnBody');
            tbody.innerHTML = '';
            if (d.success && d.data && d.data.items && d.data.items.length > 0) {
                d.data.items.forEach(function(item){
                    var tr = document.createElement('tr');
                    tr.innerHTML =
                        '<td>' + esc(String(item.id)) + '</td>' +
                        '<td><strong>' + esc(item.credit_note_number || '') + '</strong></td>' +
                        '<td>' + esc(String(item.invoice_id || '-')) + '</td>' +
                        '<td>' + esc(String(item.related_transaction_id || '')) + '</td>' +
                        '<td>' + formatCurrency(item.credit_amount, item.currency_code) + '</td>' +
                        '<td>' + esc((item.reason || '').substring(0, 50)) + '</td>' +
                        '<td>' + statusBadge(item.status) + '</td>' +
                        '<td>' + formatDate(item.created_at) + '</td>' +
                        '<td class="actions-cell">' +
                            '<button class="btn btn-sm btn-primary btn-cn-edit" data-id="' + item.id + '">' + t('edit', 'Edit') + '</button> ' +
                            '<button class="btn btn-sm btn-danger btn-cn-delete" data-id="' + item.id + '">' + t('delete', 'Delete') + '</button>' +
                        '</td>';
                    tbody.appendChild(tr);
                });
                renderPagination('cnPagination', 'cnPaginationInfo', d.data.meta.total, pages.credit_notes, loadCreditNotes);
            } else {
                tbody.innerHTML = '<tr><td colspan="9" class="text-center">' + t('table.no_records', 'No records found') + '</td></tr>';
                document.getElementById('cnPaginationInfo').textContent = '';
                document.getElementById('cnPagination').innerHTML = '';
            }
        }).catch(function(){ showNotification(t('messages.error', 'Error'), 'error'); });
}

function saveCreditNote(e) {
    e.preventDefault();
    var id = document.getElementById('cnId').value;
    var creditAmount = parseFloat(document.getElementById('cnAmount').value) || 0;
    var payload = {
        tenant_id: parseInt(CFG.tenantId) || 1,
        invoice_id: parseInt(document.getElementById('cnInvoiceId').value) || 0,
        related_transaction_id: parseInt(document.getElementById('cnTransactionId').value) || 0,
        credit_amount: creditAmount,
        credit_commission: parseFloat(document.getElementById('cnCreditCommission').value) || 0,
        credit_vat: parseFloat(document.getElementById('cnCreditVat').value) || 0,
        net_credit: parseFloat(document.getElementById('cnNetCredit').value) || 0,
        status: document.getElementById('cnStatus').value,
        reason: document.getElementById('cnReason').value || null
    };
    if (id) payload.id = parseInt(id);

    fetch(CFG.apiBase + '/commission_credit_notes', {
        method: id ? 'PUT' : 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
    .then(function(r){ return r.json(); })
    .then(function(d){
        if (d.success) {
            closeModal('cnModal');
            showNotification(t('messages.' + (id ? 'updated' : 'created'), id ? 'Updated' : 'Created'), 'success');
            loadCreditNotes(pages.credit_notes);
        } else {
            showNotification(d.message || t('messages.error', 'Error'), 'error');
        }
    }).catch(function(){ showNotification(t('messages.error', 'Error'), 'error'); });
}

function editCreditNote(id) {
    fetch(CFG.apiBase + '/commission_credit_notes?id=' + id)
        .then(function(r){ return r.json(); })
        .then(function(d){
            if (d.success && d.data) {
                var item = d.data;
                document.getElementById('cnId').value = item.id;
                document.getElementById('cnInvoiceId').value = item.invoice_id || '';
                document.getElementById('cnTransactionId').value = item.related_transaction_id || '';
                document.getElementById('cnAmount').value = item.credit_amount || 0;
                document.getElementById('cnCreditCommission').value = item.credit_commission || 0;
                document.getElementById('cnCreditVat').value = item.credit_vat || 0;
                document.getElementById('cnNetCredit').value = item.net_credit || 0;
                document.getElementById('cnStatus').value = item.status || 'draft';
                document.getElementById('cnReason').value = item.reason || '';
                document.getElementById('cnModalTitle').textContent = t('modal.edit_credit_note', 'Edit Credit Note');
                openModal('cnModal');
            }
        }).catch(function(){ showNotification(t('messages.error', 'Error'), 'error'); });
}

function deleteCreditNote(id) {
    if (!confirm(t('messages.confirm_delete', 'Are you sure?'))) return;
    fetch(CFG.apiBase + '/commission_credit_notes?id=' + id, { method: 'DELETE' })
        .then(function(r){ return r.json(); })
        .then(function(d){
            if (d.success) {
                showNotification(t('messages.deleted', 'Deleted'), 'success');
                loadCreditNotes(pages.credit_notes);
            } else {
                showNotification(d.message || t('messages.error', 'Error'), 'error');
            }
        }).catch(function(){ showNotification(t('messages.error', 'Error'), 'error'); });
}

/* ══════════════════════════════════════
   FINANCIAL BALANCES
   ══════════════════════════════════════ */
function loadBalances(page) {
    pages.balances = page || 1;
    var offset = (pages.balances - 1) * PER_PAGE;
    var url = CFG.apiBase + '/entity_financial_balances?limit=' + PER_PAGE + '&offset=' + offset;

    fetch(url)
        .then(function(r){ return r.json(); })
        .then(function(d){
            var tbody = document.getElementById('balBody');
            tbody.innerHTML = '';
            if (d.success && d.data && d.data.items && d.data.items.length > 0) {
                d.data.items.forEach(function(item){
                    var tr = document.createElement('tr');
                    var balance = (parseFloat(item.total_commission) || 0) - (parseFloat(item.total_paid) || 0);
                    var balClass = balance > 0 ? 'color:var(--warning-color,#f59e0b)' : 'color:var(--success-color,#10b981)';
                    tr.innerHTML =
                        '<td>' + esc(String(item.entity_id || '')) + '</td>' +
                        '<td><strong>' + esc(item.entity_name || '-') + '</strong></td>' +
                        '<td>' + formatCurrency(item.total_sales, item.currency_code) + '</td>' +
                        '<td>' + formatCurrency(item.total_commission, item.currency_code) + '</td>' +
                        '<td>' + formatCurrency(item.total_paid, item.currency_code) + '</td>' +
                        '<td style="' + balClass + ';font-weight:700">' + formatCurrency(balance, item.currency_code) + '</td>';
                    tbody.appendChild(tr);
                });
                renderPagination('balPagination', 'balPaginationInfo', d.data.meta.total, pages.balances, loadBalances);
            } else {
                tbody.innerHTML = '<tr><td colspan="6" class="text-center">' + t('table.no_records', 'No records found') + '</td></tr>';
                document.getElementById('balPaginationInfo').textContent = '';
                document.getElementById('balPagination').innerHTML = '';
            }
        }).catch(function(){ showNotification(t('messages.error', 'Error'), 'error'); });
}

/* ══════════════════════════════════════
   INIT & EVENT BINDINGS
   ══════════════════════════════════════ */
function resetTxnForm() {
    document.getElementById('txnId').value = '';
    document.getElementById('txnForm').reset();
    document.getElementById('txnModalTitle').textContent = t('modal.add_transaction', 'Add Commission Transaction');
}

function resetCinvForm() {
    document.getElementById('cinvId').value = '';
    document.getElementById('cinvForm').reset();
    document.getElementById('cinvModalTitle').textContent = t('modal.add_invoice', 'Add Commission Invoice');
}

function resetPayForm() {
    document.getElementById('payId').value = '';
    document.getElementById('payForm').reset();
    document.getElementById('payModalTitle').textContent = t('modal.record_payment', 'Record Payment');
}

function resetCnForm() {
    document.getElementById('cnId').value = '';
    document.getElementById('cnForm').reset();
    document.getElementById('cnCreditCommission').value = '0';
    document.getElementById('cnCreditVat').value = '0';
    document.getElementById('cnNetCredit').value = '0';
    document.getElementById('cnModalTitle').textContent = t('modal.add_credit_note', 'Add Credit Note');
}

function init() {
    reloadConfig();

    // Tab clicks
    var tabBtns = document.querySelectorAll('.tab-btn');
    for (var i = 0; i < tabBtns.length; i++) {
        tabBtns[i].addEventListener('click', function(){ switchTab(this.getAttribute('data-tab')); });
    }

    // Add button
    var addBtn = document.getElementById('btnAddItem');
    if (addBtn) {
        addBtn.addEventListener('click', function(){
            if (activeTab === 'transactions') { resetTxnForm(); openModal('txnModal'); }
            else if (activeTab === 'invoices') { resetCinvForm(); openModal('cinvModal'); }
            else if (activeTab === 'payments') { resetPayForm(); openModal('payModal'); }
            else if (activeTab === 'credit_notes') { resetCnForm(); openModal('cnModal'); }
        });
    }

    // Forms
    var txnForm = document.getElementById('txnForm');
    if (txnForm) txnForm.addEventListener('submit', saveTransaction);
    var cinvForm = document.getElementById('cinvForm');
    if (cinvForm) cinvForm.addEventListener('submit', saveInvoice);
    var payForm = document.getElementById('payForm');
    if (payForm) payForm.addEventListener('submit', recordPayment);
    var cnForm = document.getElementById('cnForm');
    if (cnForm) cnForm.addEventListener('submit', saveCreditNote);

    // Modal close buttons
    var modalClosers = [
        ['btnCloseTxnModal', 'txnModal'], ['btnCancelTxnModal', 'txnModal'],
        ['btnCloseCinvModal', 'cinvModal'], ['btnCancelCinvModal', 'cinvModal'],
        ['btnClosePayModal', 'payModal'], ['btnCancelPayModal', 'payModal'],
        ['btnCloseCnModal', 'cnModal'], ['btnCancelCnModal', 'cnModal']
    ];
    modalClosers.forEach(function(pair){
        var el = document.getElementById(pair[0]);
        if (el) el.addEventListener('click', function(){ closeModal(pair[1]); });
    });

    // Auto-generate invoice number
    var btnGenInvNum = document.getElementById('btnGenInvNum');
    if (btnGenInvNum) btnGenInvNum.addEventListener('click', generateInvoiceNumber);

    // Auto-calculate commission amount
    var txnBaseAmount = document.getElementById('txnBaseAmount');
    var txnCommissionRate = document.getElementById('txnCommissionRate');
    if (txnBaseAmount && txnCommissionRate) {
        var autoCalc = function() {
            var base = parseFloat(txnBaseAmount.value) || 0;
            var rate = parseFloat(txnCommissionRate.value) || 0;
            var commEl = document.getElementById('txnCommissionAmount');
            if (commEl) commEl.value = (base * rate / 100).toFixed(2);
        };
        txnBaseAmount.addEventListener('input', autoCalc);
        txnCommissionRate.addEventListener('input', autoCalc);
    }

    // Transaction filters
    var btnTxnFilter = document.getElementById('btnTxnFilter');
    if (btnTxnFilter) btnTxnFilter.addEventListener('click', function(){
        filters.transactions.entity_id = document.getElementById('txnFilterEntity').value;
        filters.transactions.status = document.getElementById('txnFilterStatus').value;
        filters.transactions.transaction_type = document.getElementById('txnFilterType').value;
        filters.transactions.date_from = document.getElementById('txnFilterDateFrom').value;
        filters.transactions.date_to = document.getElementById('txnFilterDateTo').value;
        loadTransactions(1);
    });
    var btnTxnClear = document.getElementById('btnTxnClear');
    if (btnTxnClear) btnTxnClear.addEventListener('click', function(){
        document.getElementById('txnFilterEntity').value = '';
        document.getElementById('txnFilterStatus').value = '';
        document.getElementById('txnFilterType').value = '';
        document.getElementById('txnFilterDateFrom').value = '';
        document.getElementById('txnFilterDateTo').value = '';
        filters.transactions = {};
        loadTransactions(1);
    });

    // Invoice filters
    var btnCinvFilter = document.getElementById('btnCinvFilter');
    if (btnCinvFilter) btnCinvFilter.addEventListener('click', function(){
        filters.invoices.entity_id = document.getElementById('cinvFilterEntity').value;
        filters.invoices.status = document.getElementById('cinvFilterStatus').value;
        filters.invoices.invoice_type = document.getElementById('cinvFilterType').value;
        loadInvoices(1);
    });
    var btnCinvClear = document.getElementById('btnCinvClear');
    if (btnCinvClear) btnCinvClear.addEventListener('click', function(){
        document.getElementById('cinvFilterEntity').value = '';
        document.getElementById('cinvFilterStatus').value = '';
        document.getElementById('cinvFilterType').value = '';
        filters.invoices = {};
        loadInvoices(1);
    });

    // Payment filters
    var btnPayFilter = document.getElementById('btnPayFilter');
    if (btnPayFilter) btnPayFilter.addEventListener('click', function(){
        filters.payments.entity_id = document.getElementById('payFilterEntity').value;
        filters.payments.invoice_id = document.getElementById('payFilterInvoice').value;
        filters.payments.is_cancelled = document.getElementById('payFilterCancelled').value;
        loadPayments(1);
    });
    var btnPayClear = document.getElementById('btnPayClear');
    if (btnPayClear) btnPayClear.addEventListener('click', function(){
        document.getElementById('payFilterEntity').value = '';
        document.getElementById('payFilterInvoice').value = '';
        document.getElementById('payFilterCancelled').value = '';
        filters.payments = {};
        loadPayments(1);
    });

    // Credit Note filters
    var btnCnFilter = document.getElementById('btnCnFilter');
    if (btnCnFilter) btnCnFilter.addEventListener('click', function(){
        filters.credit_notes.tenant_id = document.getElementById('cnFilterTenant').value;
        filters.credit_notes.status = document.getElementById('cnFilterStatus').value;
        loadCreditNotes(1);
    });
    var btnCnClear = document.getElementById('btnCnClear');
    if (btnCnClear) btnCnClear.addEventListener('click', function(){
        document.getElementById('cnFilterTenant').value = '';
        document.getElementById('cnFilterStatus').value = '';
        filters.credit_notes = {};
        loadCreditNotes(1);
    });

    // Delegated click handlers for table actions
    document.addEventListener('click', function(e) {
        var btn = e.target.closest('button');
        if (!btn) return;
        var id = parseInt(btn.getAttribute('data-id'));
        if (!id) return;

        if (btn.classList.contains('btn-txn-edit')) editTransaction(id);
        else if (btn.classList.contains('btn-txn-delete')) deleteTransaction(id);
        else if (btn.classList.contains('btn-cinv-edit')) editInvoice(id);
        else if (btn.classList.contains('btn-cinv-delete')) deleteInvoice(id);
        else if (btn.classList.contains('btn-pay-edit')) editPayment(id);
        else if (btn.classList.contains('btn-pay-delete')) deletePayment(id);
        else if (btn.classList.contains('btn-cn-edit')) editCreditNote(id);
        else if (btn.classList.contains('btn-cn-delete')) deleteCreditNote(id);
    });

    // Load initial tab
    loadTabData('transactions');
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
} else {
    init();
}

})();