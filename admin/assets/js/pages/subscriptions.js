(function(){
'use strict';

var CFG = {};
var S = {};
var PER_PAGE = 25;
var activeTab = 'plans';
var pages = { plans: 1, subscriptions: 1, invoices: 1, escrow: 1 };
var filters = { plans: {}, subscriptions: {}, invoices: {}, escrow: {} };

function reloadConfig() {
    CFG = window.SUBSCRIPTIONS_CONFIG || {};
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

/* ── Tabs ── */
function switchTab(tab) {
    activeTab = tab;
    var btns = document.querySelectorAll('.tab-btn');
    var contents = document.querySelectorAll('.tab-content');
    for (var i = 0; i < btns.length; i++) {
        btns[i].classList.toggle('active', btns[i].getAttribute('data-tab') === tab);
    }
    var tabMap = { plans: 'tabPlans', subscriptions: 'tabSubscriptions', invoices: 'tabInvoices', escrow: 'tabEscrow' };
    for (var j = 0; j < contents.length; j++) {
        contents[j].classList.toggle('active', contents[j].id === tabMap[tab]);
    }
    var addBtn = document.getElementById('btnAddItem');
    if (addBtn) {
        var labels = { plans: t('add_plan', 'Add Plan'), subscriptions: t('add_subscription', 'Add Subscription'), invoices: t('add_invoice', 'Add Invoice'), escrow: t('add_escrow', 'Add Escrow') };
        addBtn.textContent = '+ ' + labels[tab];
    }
    loadTabData(tab);
}

function loadTabData(tab) {
    if (tab === 'plans') { loadPlanStats(); loadPlans(pages.plans); }
    else if (tab === 'subscriptions') { loadSubStats(); loadSubs(pages.subscriptions); }
    else if (tab === 'invoices') { loadInvStats(); loadInvoices(pages.invoices); }
    else if (tab === 'escrow') { loadEscStats(); loadEscrow(pages.escrow); }
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
        active: 'badge-success', trial: 'badge-info', paused: 'badge-warning',
        cancelled: 'badge-secondary', expired: 'badge-secondary', suspended: 'badge-danger',
        pending: 'badge-warning', paid: 'badge-success', overdue: 'badge-danger',
        refunded: 'badge-info', funded: 'badge-info', in_transit: 'badge-warning',
        delivered: 'badge-success', released: 'badge-success', disputed: 'badge-danger'
    };
    return '<span class="badge ' + (map[status] || 'badge-secondary') + '">' + esc(status || '') + '</span>';
}

/* ══════════════════════════════════════
   PLANS
   ══════════════════════════════════════ */
function loadPlanStats() {
    fetch('/api/subscription_plans?stats=1')
        .then(function(r){ return r.json(); })
        .then(function(d){
            if (d.success && d.data) {
                document.getElementById('planStatTotal').textContent = d.data.total || 0;
                document.getElementById('planStatActive').textContent = d.data.active || 0;
                document.getElementById('planStatInactive').textContent = d.data.inactive || 0;
                document.getElementById('planStatFeatured').textContent = d.data.featured || 0;
            }
        }).catch(function(){});
}

function loadPlans(page) {
    pages.plans = page || 1;
    var offset = (pages.plans - 1) * PER_PAGE;
    var url = '/api/subscription_plans?limit=' + PER_PAGE + '&offset=' + offset;
    var f = filters.plans;
    if (f.search) url += '&search=' + encodeURIComponent(f.search);
    if (f.plan_type) url += '&plan_type=' + encodeURIComponent(f.plan_type);
    if (f.billing_period) url += '&billing_period=' + encodeURIComponent(f.billing_period);

    fetch(url)
        .then(function(r){ return r.json(); })
        .then(function(d){
            var tbody = document.getElementById('plansBody');
            tbody.innerHTML = '';
            if (d.success && d.data && d.data.items && d.data.items.length > 0) {
                d.data.items.forEach(function(item){
                    var tr = document.createElement('tr');
                    tr.innerHTML =
                        '<td>' + esc(String(item.id)) + '</td>' +
                        '<td><strong>' + esc(item.plan_name || '') + '</strong></td>' +
                        '<td>' + esc(item.code || '-') + '</td>' +
                        '<td>' + esc(item.plan_type || '') + '</td>' +
                        '<td>' + esc(item.billing_period || '') + '</td>' +
                        '<td>' + esc(String(item.price || 0)) + ' ' + esc(item.currency_code || '') + '</td>' +
                        '<td>' + (parseInt(item.is_active) ? '<span class="badge badge-success">' + t('yes', 'Yes') + '</span>' : '<span class="badge badge-secondary">' + t('no', 'No') + '</span>') + '</td>' +
                        '<td class="actions-cell">' +
                            '<button class="btn btn-sm btn-info btn-plan-trans" data-id="' + item.id + '">' + t('translations.title', 'Translations') + '</button> ' +
                            '<button class="btn btn-sm btn-primary btn-plan-edit" data-id="' + item.id + '">' + t('edit', 'Edit') + '</button> ' +
                            '<button class="btn btn-sm btn-danger btn-plan-delete" data-id="' + item.id + '">' + t('delete', 'Delete') + '</button>' +
                        '</td>';
                    tbody.appendChild(tr);
                });
                renderPagination('plansPagination', 'plansPaginationInfo', d.data.meta.total, pages.plans, loadPlans);
            } else {
                tbody.innerHTML = '<tr><td colspan="8" class="text-center">' + t('table.no_records', 'No records found') + '</td></tr>';
                document.getElementById('plansPaginationInfo').textContent = '';
                document.getElementById('plansPagination').innerHTML = '';
            }
        }).catch(function(){ showNotification(t('messages.error', 'Error'), 'error'); });
}

function savePlan(e) {
    e.preventDefault();
    var id = document.getElementById('planId').value;
    var payload = {
        plan_name: document.getElementById('planName').value,
        code: document.getElementById('planCode').value || null,
        plan_type: document.getElementById('planType').value,
        billing_period: document.getElementById('planBillingPeriod').value,
        price: parseFloat(document.getElementById('planPrice').value) || 0,
        currency_code: document.getElementById('planCurrency').value || 'SAR',
        setup_fee: parseFloat(document.getElementById('planSetupFee').value) || 0,
        commission_rate: parseFloat(document.getElementById('planCommission').value) || 0,
        max_products: document.getElementById('planMaxProducts').value ? parseInt(document.getElementById('planMaxProducts').value) : null,
        max_branches: document.getElementById('planMaxBranches').value ? parseInt(document.getElementById('planMaxBranches').value) : null,
        max_orders_per_month: document.getElementById('planMaxOrders').value ? parseInt(document.getElementById('planMaxOrders').value) : null,
        max_staff: document.getElementById('planMaxStaff').value ? parseInt(document.getElementById('planMaxStaff').value) : null,
        analytics_access: parseInt(document.getElementById('planAnalytics').value),
        priority_support: parseInt(document.getElementById('planPrioritySupport').value),
        featured_listing: parseInt(document.getElementById('planFeaturedListing').value),
        custom_domain: parseInt(document.getElementById('planCustomDomain').value),
        api_access: parseInt(document.getElementById('planApiAccess').value),
        trial_period_days: parseInt(document.getElementById('planTrialDays').value) || 0,
        is_active: parseInt(document.getElementById('planIsActive').value),
        sort_order: parseInt(document.getElementById('planSortOrder').value) || 0
    };
    if (id) payload.id = parseInt(id);

    fetch('/api/subscription_plans', {
        method: id ? 'PUT' : 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
    .then(function(r){ return r.json(); })
    .then(function(d){
        if (d.success) {
            closeModal('planModal');
            showNotification(t('messages.' + (id ? 'updated' : 'created'), id ? 'Updated' : 'Created'), 'success');
            loadPlans(pages.plans);
            loadPlanStats();
        } else {
            showNotification(d.message || t('messages.error', 'Error'), 'error');
        }
    }).catch(function(){ showNotification(t('messages.error', 'Error'), 'error'); });
}

function editPlan(id) {
    fetch('/api/subscription_plans?id=' + id)
        .then(function(r){ return r.json(); })
        .then(function(d){
            if (d.success && d.data) {
                var item = d.data;
                document.getElementById('planId').value = item.id;
                document.getElementById('planName').value = item.plan_name || '';
                document.getElementById('planCode').value = item.code || '';
                document.getElementById('planType').value = item.plan_type || 'basic';
                document.getElementById('planBillingPeriod').value = item.billing_period || 'monthly';
                document.getElementById('planPrice').value = item.price || 0;
                document.getElementById('planCurrency').value = item.currency_code || 'SAR';
                document.getElementById('planSetupFee').value = item.setup_fee || 0;
                document.getElementById('planCommission').value = item.commission_rate || 0;
                document.getElementById('planMaxProducts').value = item.max_products || '';
                document.getElementById('planMaxBranches').value = item.max_branches || '';
                document.getElementById('planMaxOrders').value = item.max_orders_per_month || '';
                document.getElementById('planMaxStaff').value = item.max_staff || '';
                document.getElementById('planAnalytics').value = String(item.analytics_access || 0);
                document.getElementById('planPrioritySupport').value = String(item.priority_support || 0);
                document.getElementById('planFeaturedListing').value = String(item.featured_listing || 0);
                document.getElementById('planCustomDomain').value = String(item.custom_domain || 0);
                document.getElementById('planApiAccess').value = String(item.api_access || 0);
                document.getElementById('planTrialDays').value = item.trial_period_days || 0;
                document.getElementById('planIsActive').value = String(item.is_active != null ? item.is_active : 1);
                document.getElementById('planSortOrder').value = item.sort_order || 0;
                document.getElementById('planModalTitle').textContent = t('modal.edit_plan', 'Edit Subscription Plan');
                openModal('planModal');
            }
        }).catch(function(){ showNotification(t('messages.error', 'Error'), 'error'); });
}

function deletePlan(id) {
    if (!confirm(t('messages.confirm_delete', 'Are you sure?'))) return;
    fetch('/api/subscription_plans?id=' + id, { method: 'DELETE' })
        .then(function(r){ return r.json(); })
        .then(function(d){
            if (d.success) {
                showNotification(t('messages.deleted', 'Deleted'), 'success');
                loadPlans(pages.plans);
                loadPlanStats();
            } else {
                showNotification(d.message || t('messages.error', 'Error'), 'error');
            }
        }).catch(function(){ showNotification(t('messages.error', 'Error'), 'error'); });
}

/* ══════════════════════════════════════
   SUBSCRIPTIONS
   ══════════════════════════════════════ */
function loadSubStats() {
    fetch('/api/subscriptions?stats=1')
        .then(function(r){ return r.json(); })
        .then(function(d){
            if (d.success && d.data) {
                document.getElementById('subStatTotal').textContent = d.data.total || 0;
                document.getElementById('subStatActive').textContent = d.data.active || 0;
                document.getElementById('subStatTrial').textContent = d.data.trial || 0;
                document.getElementById('subStatCancelled').textContent = d.data.cancelled || 0;
            }
        }).catch(function(){});
}

function loadSubs(page) {
    pages.subscriptions = page || 1;
    var offset = (pages.subscriptions - 1) * PER_PAGE;
    var url = '/api/subscriptions?limit=' + PER_PAGE + '&offset=' + offset;
    var f = filters.subscriptions;
    if (f.search) url += '&search=' + encodeURIComponent(f.search);
    if (f.status) url += '&status=' + encodeURIComponent(f.status);

    fetch(url)
        .then(function(r){ return r.json(); })
        .then(function(d){
            var tbody = document.getElementById('subsBody');
            tbody.innerHTML = '';
            if (d.success && d.data && d.data.items && d.data.items.length > 0) {
                d.data.items.forEach(function(item){
                    var tr = document.createElement('tr');
                    tr.innerHTML =
                        '<td>' + esc(String(item.id)) + '</td>' +
                        '<td><strong>' + esc(item.subscription_number || '') + '</strong></td>' +
                        '<td>' + esc(String(item.tenant_id || '')) + '</td>' +
                        '<td>' + statusBadge(item.status) + '</td>' +
                        '<td>' + esc(item.billing_period || '') + '</td>' +
                        '<td>' + esc(String(item.price || 0)) + ' ' + esc(item.currency_code || '') + '</td>' +
                        '<td>' + esc(item.start_date || '-') + '</td>' +
                        '<td>' + esc(item.next_billing_date || '-') + '</td>' +
                        '<td class="actions-cell">' +
                            '<button class="btn btn-sm btn-primary btn-sub-edit" data-id="' + item.id + '">' + t('edit', 'Edit') + '</button> ' +
                            '<button class="btn btn-sm btn-danger btn-sub-delete" data-id="' + item.id + '">' + t('delete', 'Delete') + '</button>' +
                        '</td>';
                    tbody.appendChild(tr);
                });
                renderPagination('subsPagination', 'subsPaginationInfo', d.data.meta.total, pages.subscriptions, loadSubs);
            } else {
                tbody.innerHTML = '<tr><td colspan="9" class="text-center">' + t('table.no_records', 'No records found') + '</td></tr>';
                document.getElementById('subsPaginationInfo').textContent = '';
                document.getElementById('subsPagination').innerHTML = '';
            }
        }).catch(function(){ showNotification(t('messages.error', 'Error'), 'error'); });
}

var plansCache = [];
function loadPlanOptions() {
    fetch('/api/subscription_plans?limit=100&is_active=1')
        .then(function(r){ return r.json(); })
        .then(function(d){
            var select = document.getElementById('subPlanId');
            if (!select) return;
            var current = select.value;
            select.innerHTML = '<option value="">' + t('form.select_plan', 'Select Plan...') + '</option>';
            if (d.success && d.data && d.data.items) {
                plansCache = d.data.items;
                d.data.items.forEach(function(p){
                    var opt = document.createElement('option');
                    opt.value = p.id;
                    opt.textContent = p.plan_name + ' (' + p.plan_type + ' - ' + p.billing_period + ' - ' + p.price + ' ' + (p.currency_code || 'SAR') + ')';
                    opt.setAttribute('data-plan', JSON.stringify(p));
                    select.appendChild(opt);
                });
            }
            if (current) select.value = current;
            select.onchange = function() { autoFillFromPlan(this.value); };
        }).catch(function(){});
}

function autoFillFromPlan(planId) {
    if (!planId) return;
    var plan = null;
    for (var i = 0; i < plansCache.length; i++) {
        if (String(plansCache[i].id) === String(planId)) { plan = plansCache[i]; break; }
    }
    if (!plan) return;
    var bp = document.getElementById('subBillingPeriod');
    var pr = document.getElementById('subPrice');
    var cur = document.getElementById('subCurrency');
    var sd = document.getElementById('subStartDate');
    var ed = document.getElementById('subEndDate');
    var td = document.getElementById('subTrialEndDate');
    var nb = document.getElementById('subNextBilling');
    var st = document.getElementById('subStatus');
    if (bp) bp.value = plan.billing_period;
    if (pr) pr.value = plan.price;
    if (cur) cur.value = plan.currency_code || 'SAR';
    var today = new Date();
    var startStr = today.toISOString().split('T')[0];
    if (sd) sd.value = startStr;
    var endDate = new Date(today);
    switch (plan.billing_period) {
        case 'daily': endDate.setDate(endDate.getDate() + 1); break;
        case 'weekly': endDate.setDate(endDate.getDate() + 7); break;
        case 'monthly': endDate.setMonth(endDate.getMonth() + 1); break;
        case 'quarterly': endDate.setMonth(endDate.getMonth() + 3); break;
        case 'yearly': endDate.setFullYear(endDate.getFullYear() + 1); break;
        case 'lifetime': endDate.setFullYear(endDate.getFullYear() + 100); break;
    }
    if (ed) ed.value = endDate.toISOString().split('T')[0];
    if (nb) nb.value = endDate.toISOString().split('T')[0];
    if (plan.trial_period_days > 0) {
        var trialEnd = new Date(today);
        trialEnd.setDate(trialEnd.getDate() + plan.trial_period_days);
        if (td) td.value = trialEnd.toISOString().split('T')[0];
        if (st) st.value = 'trial';
    } else {
        if (td) td.value = '';
        if (st) st.value = 'active';
    }
}

function saveSub(e) {
    e.preventDefault();
    var id = document.getElementById('subId').value;
    var payload = {
        tenant_id: parseInt(document.getElementById('subTenantId').value) || 0,
        plan_id: parseInt(document.getElementById('subPlanId').value) || 0,
        billing_period: document.getElementById('subBillingPeriod').value,
        price: parseFloat(document.getElementById('subPrice').value) || 0,
        status: document.getElementById('subStatus').value,
        currency_code: document.getElementById('subCurrency').value || 'SAR',
        start_date: document.getElementById('subStartDate').value || null,
        end_date: document.getElementById('subEndDate').value || null,
        trial_end_date: document.getElementById('subTrialEndDate').value || null,
        next_billing_date: document.getElementById('subNextBilling').value || null,
        auto_renew: parseInt(document.getElementById('subAutoRenew').value)
    };
    if (id) payload.id = parseInt(id);

    fetch('/api/subscriptions', {
        method: id ? 'PUT' : 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
    .then(function(r){ return r.json(); })
    .then(function(d){
        if (d.success) {
            closeModal('subModal');
            showNotification(t('messages.' + (id ? 'updated' : 'created'), id ? 'Updated' : 'Created'), 'success');
            loadSubs(pages.subscriptions);
            loadSubStats();
        } else {
            showNotification(d.message || t('messages.error', 'Error'), 'error');
        }
    }).catch(function(){ showNotification(t('messages.error', 'Error'), 'error'); });
}

function editSub(id) {
    loadPlanOptions();
    fetch('/api/subscriptions?id=' + id)
        .then(function(r){ return r.json(); })
        .then(function(d){
            if (d.success && d.data) {
                var item = d.data;
                document.getElementById('subId').value = item.id;
                document.getElementById('subTenantId').value = item.tenant_id || '';
                document.getElementById('subPlanId').value = item.plan_id || '';
                document.getElementById('subBillingPeriod').value = item.billing_period || 'monthly';
                document.getElementById('subPrice').value = item.price || 0;
                document.getElementById('subStatus').value = item.status || 'trial';
                document.getElementById('subCurrency').value = item.currency_code || 'SAR';
                document.getElementById('subStartDate').value = (item.start_date || '').substring(0, 10);
                document.getElementById('subEndDate').value = (item.end_date || '').substring(0, 10);
                document.getElementById('subTrialEndDate').value = (item.trial_end_date || '').substring(0, 10);
                document.getElementById('subNextBilling').value = (item.next_billing_date || '').substring(0, 10);
                document.getElementById('subAutoRenew').value = String(item.auto_renew != null ? item.auto_renew : 1);
                document.getElementById('subModalTitle').textContent = t('modal.edit_subscription', 'Edit Subscription');
                openModal('subModal');
            }
        }).catch(function(){ showNotification(t('messages.error', 'Error'), 'error'); });
}

function deleteSub(id) {
    if (!confirm(t('messages.confirm_delete', 'Are you sure?'))) return;
    fetch('/api/subscriptions?id=' + id, { method: 'DELETE' })
        .then(function(r){ return r.json(); })
        .then(function(d){
            if (d.success) {
                showNotification(t('messages.deleted', 'Deleted'), 'success');
                loadSubs(pages.subscriptions);
                loadSubStats();
            } else {
                showNotification(d.message || t('messages.error', 'Error'), 'error');
            }
        }).catch(function(){ showNotification(t('messages.error', 'Error'), 'error'); });
}

/* ══════════════════════════════════════
   INVOICES
   ══════════════════════════════════════ */
function loadInvStats() {
    fetch('/api/subscription_invoices?stats=1')
        .then(function(r){ return r.json(); })
        .then(function(d){
            if (d.success && d.data) {
                document.getElementById('invStatTotal').textContent = d.data.total || 0;
                document.getElementById('invStatPaid').textContent = d.data.paid || 0;
                document.getElementById('invStatPending').textContent = d.data.pending || 0;
                document.getElementById('invStatOverdue').textContent = d.data.overdue || 0;
            }
        }).catch(function(){});
}

function loadInvoices(page) {
    pages.invoices = page || 1;
    var offset = (pages.invoices - 1) * PER_PAGE;
    var url = '/api/subscription_invoices?limit=' + PER_PAGE + '&offset=' + offset;
    var f = filters.invoices;
    if (f.search) url += '&search=' + encodeURIComponent(f.search);
    if (f.status) url += '&status=' + encodeURIComponent(f.status);

    fetch(url)
        .then(function(r){ return r.json(); })
        .then(function(d){
            var tbody = document.getElementById('invBody');
            tbody.innerHTML = '';
            if (d.success && d.data && d.data.items && d.data.items.length > 0) {
                d.data.items.forEach(function(item){
                    var tr = document.createElement('tr');
                    var actions = '<button class="btn btn-sm btn-primary btn-inv-edit" data-id="' + item.id + '">' + t('edit', 'Edit') + '</button> ';
                    if (item.status === 'pending' || item.status === 'overdue') {
                        actions += '<button class="btn btn-sm btn-success btn-inv-pay" data-id="' + item.id + '">' + t('messages.mark_paid', 'Mark Paid') + '</button> ';
                    }
                    actions += '<button class="btn btn-sm btn-danger btn-inv-delete" data-id="' + item.id + '">' + t('delete', 'Delete') + '</button>';
                    tr.innerHTML =
                        '<td>' + esc(String(item.id)) + '</td>' +
                        '<td><strong>' + esc(item.invoice_number || '') + '</strong></td>' +
                        '<td>' + esc(String(item.tenant_id || '')) + '</td>' +
                        '<td>' + esc(String(item.amount || 0)) + '</td>' +
                        '<td>' + esc(String(item.total_amount || 0)) + ' ' + esc(item.currency_code || '') + '</td>' +
                        '<td>' + statusBadge(item.status) + '</td>' +
                        '<td>' + esc(item.due_date || '-') + '</td>' +
                        '<td>' + esc(item.paid_at || '-') + '</td>' +
                        '<td class="actions-cell">' + actions + '</td>';
                    tbody.appendChild(tr);
                });
                renderPagination('invPagination', 'invPaginationInfo', d.data.meta.total, pages.invoices, loadInvoices);
            } else {
                tbody.innerHTML = '<tr><td colspan="9" class="text-center">' + t('table.no_records', 'No records found') + '</td></tr>';
                document.getElementById('invPaginationInfo').textContent = '';
                document.getElementById('invPagination').innerHTML = '';
            }
        }).catch(function(){ showNotification(t('messages.error', 'Error'), 'error'); });
}

function saveInvoice(e) {
    e.preventDefault();
    var id = document.getElementById('invId').value;
    var payload = {
        subscription_id: parseInt(document.getElementById('invSubId').value) || 0,
        tenant_id: parseInt(document.getElementById('invTenantId').value) || 0,
        amount: parseFloat(document.getElementById('invAmount').value) || 0,
        tax_amount: parseFloat(document.getElementById('invTaxAmount').value) || 0,
        total_amount: parseFloat(document.getElementById('invTotalAmount').value) || 0,
        currency_code: document.getElementById('invCurrency').value || 'SAR',
        billing_period_start: document.getElementById('invBillingStart').value || null,
        billing_period_end: document.getElementById('invBillingEnd').value || null,
        due_date: document.getElementById('invDueDate').value || null,
        status: document.getElementById('invStatus').value,
        notes: document.getElementById('invNotes').value || null
    };
    if (id) payload.id = parseInt(id);

    fetch('/api/subscription_invoices', {
        method: id ? 'PUT' : 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
    .then(function(r){ return r.json(); })
    .then(function(d){
        if (d.success) {
            closeModal('invModal');
            showNotification(t('messages.' + (id ? 'updated' : 'created'), id ? 'Updated' : 'Created'), 'success');
            loadInvoices(pages.invoices);
            loadInvStats();
        } else {
            showNotification(d.message || t('messages.error', 'Error'), 'error');
        }
    }).catch(function(){ showNotification(t('messages.error', 'Error'), 'error'); });
}

function editInvoice(id) {
    fetch('/api/subscription_invoices?id=' + id)
        .then(function(r){ return r.json(); })
        .then(function(d){
            if (d.success && d.data) {
                var item = d.data;
                document.getElementById('invId').value = item.id;
                document.getElementById('invSubId').value = item.subscription_id || '';
                document.getElementById('invTenantId').value = item.tenant_id || '';
                document.getElementById('invAmount').value = item.amount || 0;
                document.getElementById('invTaxAmount').value = item.tax_amount || 0;
                document.getElementById('invTotalAmount').value = item.total_amount || 0;
                document.getElementById('invCurrency').value = item.currency_code || 'SAR';
                document.getElementById('invBillingStart').value = (item.billing_period_start || '').substring(0, 10);
                document.getElementById('invBillingEnd').value = (item.billing_period_end || '').substring(0, 10);
                document.getElementById('invDueDate').value = (item.due_date || '').substring(0, 10);
                document.getElementById('invStatus').value = item.status || 'pending';
                document.getElementById('invNotes').value = item.notes || '';
                document.getElementById('invModalTitle').textContent = t('modal.edit_invoice', 'Edit Invoice');
                openModal('invModal');
            }
        }).catch(function(){ showNotification(t('messages.error', 'Error'), 'error'); });
}

function markInvoicePaid(id) {
    var pm = prompt(t('form.payment_method', 'Payment method') + ':');
    if (pm === null) return;
    fetch('/api/subscription_invoices', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ mark_paid: true, id: id, payment_method: pm, transaction_id: '' })
    })
    .then(function(r){ return r.json(); })
    .then(function(d){
        if (d.success) {
            showNotification(t('messages.paid_success', 'Invoice marked as paid'), 'success');
            loadInvoices(pages.invoices);
            loadInvStats();
        } else {
            showNotification(d.message || t('messages.error', 'Error'), 'error');
        }
    }).catch(function(){ showNotification(t('messages.error', 'Error'), 'error'); });
}

function deleteInvoice(id) {
    if (!confirm(t('messages.confirm_delete', 'Are you sure?'))) return;
    fetch('/api/subscription_invoices?id=' + id, { method: 'DELETE' })
        .then(function(r){ return r.json(); })
        .then(function(d){
            if (d.success) {
                showNotification(t('messages.deleted', 'Deleted'), 'success');
                loadInvoices(pages.invoices);
                loadInvStats();
            } else {
                showNotification(d.message || t('messages.error', 'Error'), 'error');
            }
        }).catch(function(){ showNotification(t('messages.error', 'Error'), 'error'); });
}

/* ══════════════════════════════════════
   ESCROW
   ══════════════════════════════════════ */
function loadEscStats() {
    fetch('/api/escrow_transactions?stats=1')
        .then(function(r){ return r.json(); })
        .then(function(d){
            if (d.success && d.data) {
                document.getElementById('escStatTotal').textContent = d.data.total || 0;
                document.getElementById('escStatReleased').textContent = d.data.released || 0;
                document.getElementById('escStatPending').textContent = d.data.pending || 0;
                document.getElementById('escStatDisputed').textContent = d.data.disputed || 0;
            }
        }).catch(function(){});
}

function loadEscrow(page) {
    pages.escrow = page || 1;
    var offset = (pages.escrow - 1) * PER_PAGE;
    var url = '/api/escrow_transactions?limit=' + PER_PAGE + '&offset=' + offset;
    var f = filters.escrow;
    if (f.search) url += '&search=' + encodeURIComponent(f.search);
    if (f.status) url += '&status=' + encodeURIComponent(f.status);

    fetch(url)
        .then(function(r){ return r.json(); })
        .then(function(d){
            var tbody = document.getElementById('escBody');
            tbody.innerHTML = '';
            if (d.success && d.data && d.data.items && d.data.items.length > 0) {
                d.data.items.forEach(function(item){
                    var tr = document.createElement('tr');
                    tr.innerHTML =
                        '<td>' + esc(String(item.id)) + '</td>' +
                        '<td><strong>' + esc(item.escrow_number || '') + '</strong></td>' +
                        '<td>' + esc(String(item.order_id || '')) + '</td>' +
                        '<td>' + esc(String(item.buyer_id || '')) + '</td>' +
                        '<td>' + esc(String(item.seller_id || '')) + '</td>' +
                        '<td>' + esc(String(item.amount || 0)) + ' ' + esc(item.currency_code || '') + '</td>' +
                        '<td>' + statusBadge(item.status) + '</td>' +
                        '<td class="actions-cell">' +
                            '<button class="btn btn-sm btn-primary btn-esc-edit" data-id="' + item.id + '">' + t('edit', 'Edit') + '</button> ' +
                            '<button class="btn btn-sm btn-danger btn-esc-delete" data-id="' + item.id + '">' + t('delete', 'Delete') + '</button>' +
                        '</td>';
                    tbody.appendChild(tr);
                });
                renderPagination('escPagination', 'escPaginationInfo', d.data.meta.total, pages.escrow, loadEscrow);
            } else {
                tbody.innerHTML = '<tr><td colspan="8" class="text-center">' + t('table.no_records', 'No records found') + '</td></tr>';
                document.getElementById('escPaginationInfo').textContent = '';
                document.getElementById('escPagination').innerHTML = '';
            }
        }).catch(function(){ showNotification(t('messages.error', 'Error'), 'error'); });
}

function saveEscrow(e) {
    e.preventDefault();
    var id = document.getElementById('escId').value;
    var payload = {
        order_id: parseInt(document.getElementById('escOrderId').value) || 0,
        buyer_id: parseInt(document.getElementById('escBuyerId').value) || 0,
        seller_id: parseInt(document.getElementById('escSellerId').value) || 0,
        seller_type: document.getElementById('escSellerType').value || 'vendor',
        amount: parseFloat(document.getElementById('escAmount').value) || 0,
        currency_code: document.getElementById('escCurrencyCode').value || 'SAR',
        escrow_fee: parseFloat(document.getElementById('escFee').value) || 0,
        status: document.getElementById('escStatus').value,
        tenant_id: parseInt(document.getElementById('escTenantId').value) || 0
    };
    if (id) payload.id = parseInt(id);

    fetch('/api/escrow_transactions', {
        method: id ? 'PUT' : 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
    .then(function(r){ return r.json(); })
    .then(function(d){
        if (d.success) {
            closeModal('escModal');
            showNotification(t('messages.' + (id ? 'updated' : 'created'), id ? 'Updated' : 'Created'), 'success');
            loadEscrow(pages.escrow);
            loadEscStats();
        } else {
            showNotification(d.message || t('messages.error', 'Error'), 'error');
        }
    }).catch(function(){ showNotification(t('messages.error', 'Error'), 'error'); });
}

function editEscrow(id) {
    fetch('/api/escrow_transactions?id=' + id)
        .then(function(r){ return r.json(); })
        .then(function(d){
            if (d.success && d.data) {
                var item = d.data;
                document.getElementById('escId').value = item.id;
                document.getElementById('escOrderId').value = item.order_id || '';
                document.getElementById('escTenantId').value = item.tenant_id || '';
                document.getElementById('escBuyerId').value = item.buyer_id || '';
                document.getElementById('escSellerId').value = item.seller_id || '';
                document.getElementById('escSellerType').value = item.seller_type || 'vendor';
                document.getElementById('escAmount').value = item.amount || 0;
                document.getElementById('escCurrencyCode').value = item.currency_code || 'SAR';
                document.getElementById('escFee').value = item.escrow_fee || 0;
                document.getElementById('escStatus').value = item.status || 'pending';
                document.getElementById('escModalTitle').textContent = t('modal.edit_escrow', 'Edit Escrow Transaction');
                openModal('escModal');
            }
        }).catch(function(){ showNotification(t('messages.error', 'Error'), 'error'); });
}

function deleteEscrow(id) {
    if (!confirm(t('messages.confirm_delete', 'Are you sure?'))) return;
    fetch('/api/escrow_transactions?id=' + id, { method: 'DELETE' })
        .then(function(r){ return r.json(); })
        .then(function(d){
            if (d.success) {
                showNotification(t('messages.deleted', 'Deleted'), 'success');
                loadEscrow(pages.escrow);
                loadEscStats();
            } else {
                showNotification(d.message || t('messages.error', 'Error'), 'error');
            }
        }).catch(function(){ showNotification(t('messages.error', 'Error'), 'error'); });
}

/* ══════════════════════════════════════
   TRANSLATIONS
   ══════════════════════════════════════ */
function openTranslations(planId) {
    document.getElementById('transPlanId').value = planId;
    loadTranslations(planId);
    openModal('translationsModal');
}

function loadTranslations(planId) {
    fetch('/api/subscription_plan_translations?plan_id=' + planId)
        .then(function(r){ return r.json(); })
        .then(function(d){
            var tbody = document.getElementById('translationsBody');
            tbody.innerHTML = '';
            var items = d.success ? (Array.isArray(d.data) ? d.data : []) : [];
            items.forEach(function(item){
                var tr = document.createElement('tr');
                tr.innerHTML =
                    '<td>' + esc(item.language_code || '') + '</td>' +
                    '<td>' + esc(item.plan_name || '') + '</td>' +
                    '<td>' + esc((item.description || '').substring(0, 50)) + '</td>' +
                    '<td class="actions-cell">' +
                        '<button class="btn btn-sm btn-danger btn-trans-delete" data-id="' + item.id + '">' + t('delete', 'Delete') + '</button>' +
                    '</td>';
                tbody.appendChild(tr);
            });
        }).catch(function(){});
}

function saveTranslation() {
    var planId = document.getElementById('transPlanId').value;
    var payload = {
        plan_id: parseInt(planId),
        language_code: document.getElementById('transLang').value,
        plan_name: document.getElementById('transPlanName').value,
        description: document.getElementById('transDescription').value || null,
        features: document.getElementById('transFeatures').value || null
    };

    fetch('/api/subscription_plan_translations', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
    .then(function(r){ return r.json(); })
    .then(function(d){
        if (d.success) {
            showNotification(t('messages.created', 'Saved'), 'success');
            loadTranslations(planId);
            document.getElementById('transPlanName').value = '';
            document.getElementById('transDescription').value = '';
            document.getElementById('transFeatures').value = '';
        } else {
            showNotification(d.message || t('messages.error', 'Error'), 'error');
        }
    }).catch(function(){ showNotification(t('messages.error', 'Error'), 'error'); });
}

function deleteTranslation(id) {
    var planId = document.getElementById('transPlanId').value;
    fetch('/api/subscription_plan_translations?id=' + id, { method: 'DELETE' })
        .then(function(r){ return r.json(); })
        .then(function(d){
            if (d.success) {
                showNotification(t('messages.deleted', 'Deleted'), 'success');
                loadTranslations(planId);
            }
        }).catch(function(){});
}

/* ══════════════════════════════════════
   INIT & EVENT BINDINGS
   ══════════════════════════════════════ */
function resetPlanForm() {
    document.getElementById('planId').value = '';
    document.getElementById('planForm').reset();
    document.getElementById('planModalTitle').textContent = t('modal.add_plan', 'Add Subscription Plan');
}

function resetSubForm() {
    document.getElementById('subId').value = '';
    document.getElementById('subForm').reset();
    document.getElementById('subModalTitle').textContent = t('modal.add_subscription', 'Add Subscription');
}

function resetInvForm() {
    document.getElementById('invId').value = '';
    document.getElementById('invForm').reset();
    document.getElementById('invModalTitle').textContent = t('modal.add_invoice', 'Add Invoice');
}

function resetEscForm() {
    document.getElementById('escId').value = '';
    document.getElementById('escForm').reset();
    document.getElementById('escModalTitle').textContent = t('modal.add_escrow', 'Add Escrow Transaction');
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
            if (activeTab === 'plans') { resetPlanForm(); openModal('planModal'); }
            else if (activeTab === 'subscriptions') { resetSubForm(); loadPlanOptions(); openModal('subModal'); }
            else if (activeTab === 'invoices') { resetInvForm(); openModal('invModal'); }
            else if (activeTab === 'escrow') { resetEscForm(); openModal('escModal'); }
        });
    }

    // Forms
    var planForm = document.getElementById('planForm');
    if (planForm) planForm.addEventListener('submit', savePlan);
    var subForm = document.getElementById('subForm');
    if (subForm) subForm.addEventListener('submit', saveSub);
    var invForm = document.getElementById('invForm');
    if (invForm) invForm.addEventListener('submit', saveInvoice);
    var escForm = document.getElementById('escForm');
    if (escForm) escForm.addEventListener('submit', saveEscrow);

    // Modal close buttons
    var modalClosers = [
        ['btnClosePlanModal', 'planModal'], ['btnCancelPlanModal', 'planModal'],
        ['btnCloseSubModal', 'subModal'], ['btnCancelSubModal', 'subModal'],
        ['btnCloseInvModal', 'invModal'], ['btnCancelInvModal', 'invModal'],
        ['btnCloseEscModal', 'escModal'], ['btnCancelEscModal', 'escModal'],
        ['btnCloseTranslations', 'translationsModal']
    ];
    modalClosers.forEach(function(pair){
        var el = document.getElementById(pair[0]);
        if (el) el.addEventListener('click', function(){ closeModal(pair[1]); });
    });

    // Translation save
    var btnSaveTrans = document.getElementById('btnSaveTranslation');
    if (btnSaveTrans) btnSaveTrans.addEventListener('click', saveTranslation);

    // Plan filters
    var btnPlanFilter = document.getElementById('btnPlanFilter');
    if (btnPlanFilter) btnPlanFilter.addEventListener('click', function(){
        filters.plans.search = document.getElementById('planSearchInput').value;
        filters.plans.plan_type = document.getElementById('planTypeFilter').value;
        filters.plans.billing_period = document.getElementById('planPeriodFilter').value;
        loadPlans(1);
    });
    var btnPlanClear = document.getElementById('btnPlanClear');
    if (btnPlanClear) btnPlanClear.addEventListener('click', function(){
        document.getElementById('planSearchInput').value = '';
        document.getElementById('planTypeFilter').value = '';
        document.getElementById('planPeriodFilter').value = '';
        filters.plans = {};
        loadPlans(1);
    });

    // Sub filters
    var btnSubFilter = document.getElementById('btnSubFilter');
    if (btnSubFilter) btnSubFilter.addEventListener('click', function(){
        filters.subscriptions.search = document.getElementById('subSearchInput').value;
        filters.subscriptions.status = document.getElementById('subStatusFilter').value;
        loadSubs(1);
    });
    var btnSubClear = document.getElementById('btnSubClear');
    if (btnSubClear) btnSubClear.addEventListener('click', function(){
        document.getElementById('subSearchInput').value = '';
        document.getElementById('subStatusFilter').value = '';
        filters.subscriptions = {};
        loadSubs(1);
    });

    // Invoice filters
    var btnInvFilter = document.getElementById('btnInvFilter');
    if (btnInvFilter) btnInvFilter.addEventListener('click', function(){
        filters.invoices.search = document.getElementById('invSearchInput').value;
        filters.invoices.status = document.getElementById('invStatusFilter').value;
        loadInvoices(1);
    });
    var btnInvClear = document.getElementById('btnInvClear');
    if (btnInvClear) btnInvClear.addEventListener('click', function(){
        document.getElementById('invSearchInput').value = '';
        document.getElementById('invStatusFilter').value = '';
        filters.invoices = {};
        loadInvoices(1);
    });

    // Escrow filters
    var btnEscFilter = document.getElementById('btnEscFilter');
    if (btnEscFilter) btnEscFilter.addEventListener('click', function(){
        filters.escrow.search = document.getElementById('escSearchInput').value;
        filters.escrow.status = document.getElementById('escStatusFilter').value;
        loadEscrow(1);
    });
    var btnEscClear = document.getElementById('btnEscClear');
    if (btnEscClear) btnEscClear.addEventListener('click', function(){
        document.getElementById('escSearchInput').value = '';
        document.getElementById('escStatusFilter').value = '';
        filters.escrow = {};
        loadEscrow(1);
    });

    // Delegated click handlers for table actions
    document.addEventListener('click', function(e) {
        var btn = e.target.closest('button');
        if (!btn) return;
        var id = parseInt(btn.getAttribute('data-id'));
        if (!id) return;

        if (btn.classList.contains('btn-plan-edit')) editPlan(id);
        else if (btn.classList.contains('btn-plan-delete')) deletePlan(id);
        else if (btn.classList.contains('btn-plan-trans')) openTranslations(id);
        else if (btn.classList.contains('btn-sub-edit')) editSub(id);
        else if (btn.classList.contains('btn-sub-delete')) deleteSub(id);
        else if (btn.classList.contains('btn-inv-edit')) editInvoice(id);
        else if (btn.classList.contains('btn-inv-delete')) deleteInvoice(id);
        else if (btn.classList.contains('btn-inv-pay')) markInvoicePaid(id);
        else if (btn.classList.contains('btn-esc-edit')) editEscrow(id);
        else if (btn.classList.contains('btn-esc-delete')) deleteEscrow(id);
        else if (btn.classList.contains('btn-trans-delete')) deleteTranslation(id);
    });

    // Load initial tab
    loadTabData('plans');
}

function exportInvoicesCSV() {
    var params = '?export=csv';
    var statusEl = document.getElementById('invoiceFilterStatus');
    var dateFromEl = document.getElementById('invoiceFilterDateFrom');
    var dateToEl = document.getElementById('invoiceFilterDateTo');
    if (statusEl && statusEl.value) params += '&status=' + statusEl.value;
    if (dateFromEl && dateFromEl.value) params += '&date_from=' + dateFromEl.value;
    if (dateToEl && dateToEl.value) params += '&date_to=' + dateToEl.value;
    window.location.href = '/api/subscription_invoices' + params;
}
window.exportInvoicesCSV = exportInvoicesCSV;

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
} else {
    init();
}

})();