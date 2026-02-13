(function(){
    'use strict';
    var C = window.PLAN_SELECTION_CONFIG || {};
    var S = C.strings || {};

    function t(key, fb) {
        var parts = key.split('.');
        var val = S;
        for (var i = 0; i < parts.length; i++) {
            if (!val || typeof val !== 'object') return fb || key;
            val = val[parts[i]];
        }
        return (typeof val === 'string') ? val : (fb || key);
    }

    function init() {
        loadPlans();
        loadCurrentPlan();
    }

    function loadPlans() {
        fetch(C.apiBase + '/subscription_plans?is_active=1&limit=50', { credentials: 'include' })
        .then(function(r) { return r.json(); })
        .then(function(d) {
            var items = d.data && d.data.items ? d.data.items : (Array.isArray(d.data) ? d.data : []);
            renderPlanCards(items);
        })
        .catch(function() {
            var grid = document.getElementById('planCardsGrid');
            if (grid) grid.innerHTML = '<p class="error-msg">' + t('error_loading', 'Error loading plans') + '</p>';
        });
    }

    function loadCurrentPlan() {
        if (!C.tenantId) return;
        fetch(C.apiBase + '/subscriptions?tenant_id=' + C.tenantId + '&status=active', { credentials: 'include' })
        .then(function(r) { return r.json(); })
        .then(function(d) {
            var items = d.data && d.data.items ? d.data.items : [];
            if (items.length > 0) {
                var sub = items[0];
                var info = document.getElementById('currentPlanInfo');
                var details = document.getElementById('currentPlanDetails');
                if (info && details) {
                    info.style.display = 'block';
                    details.innerHTML =
                        '<p><strong>' + t('plan_name', 'Plan') + ':</strong> ' + esc(sub.plan_name || 'Plan #' + sub.plan_id) + '</p>' +
                        '<p><strong>' + t('status', 'Status') + ':</strong> <span class="badge badge-' + sub.status + '">' + sub.status + '</span></p>' +
                        '<p><strong>' + t('billing', 'Billing') + ':</strong> ' + sub.billing_period + '</p>' +
                        '<p><strong>' + t('price', 'Price') + ':</strong> ' + sub.price + ' ' + (sub.currency_code || 'SAR') + '</p>' +
                        '<p><strong>' + t('end_date', 'End Date') + ':</strong> ' + (sub.end_date || '-') + '</p>';
                }
            }
        })
        .catch(function(){});
    }

    function renderPlanCards(plans) {
        var grid = document.getElementById('planCardsGrid');
        if (!grid) return;
        if (plans.length === 0) {
            grid.innerHTML = '<p class="no-plans">' + t('no_plans', 'No plans available') + '</p>';
            return;
        }
        var html = '';
        for (var i = 0; i < plans.length; i++) {
            var p = plans[i];
            var featured = p.is_featured == 1 ? ' featured' : '';
            html += '<div class="plan-card' + featured + '" data-plan-id="' + p.id + '">';
            if (p.is_featured == 1) html += '<div class="featured-badge">' + t('featured', 'Popular') + '</div>';
            html += '<div class="plan-header"><h3>' + esc(p.plan_name) + '</h3>';
            html += '<span class="plan-type">' + esc(p.plan_type || '') + '</span></div>';
            html += '<div class="plan-price"><span class="price-amount">' + p.price + '</span>';
            html += '<span class="price-currency">' + (p.currency_code || 'SAR') + '</span>';
            html += '<span class="price-period">/ ' + esc(p.billing_period) + '</span></div>';
            if (p.setup_fee && parseFloat(p.setup_fee) > 0) {
                html += '<p class="setup-fee">' + t('setup_fee', 'Setup fee') + ': ' + p.setup_fee + ' ' + (p.currency_code || 'SAR') + '</p>';
            }
            html += '<ul class="plan-features">';
            if (p.max_products) html += '<li>✓ ' + t('max_products', 'Products') + ': ' + (p.max_products == 0 ? t('unlimited', 'Unlimited') : p.max_products) + '</li>';
            if (p.max_branches) html += '<li>✓ ' + t('max_branches', 'Branches') + ': ' + (p.max_branches == 0 ? t('unlimited', 'Unlimited') : p.max_branches) + '</li>';
            if (p.max_orders_per_month) html += '<li>✓ ' + t('max_orders', 'Orders/month') + ': ' + (p.max_orders_per_month == 0 ? t('unlimited', 'Unlimited') : p.max_orders_per_month) + '</li>';
            if (p.max_staff) html += '<li>✓ ' + t('max_staff', 'Staff') + ': ' + (p.max_staff == 0 ? t('unlimited', 'Unlimited') : p.max_staff) + '</li>';
            if (p.analytics_access == 1) html += '<li>✓ ' + t('analytics', 'Analytics') + '</li>';
            if (p.priority_support == 1) html += '<li>✓ ' + t('priority_support', 'Priority Support') + '</li>';
            if (p.featured_listing == 1) html += '<li>✓ ' + t('featured_listing', 'Featured Listing') + '</li>';
            if (p.custom_domain == 1) html += '<li>✓ ' + t('custom_domain', 'Custom Domain') + '</li>';
            if (p.api_access == 1) html += '<li>✓ ' + t('api_access', 'API Access') + '</li>';
            html += '</ul>';
            if (p.trial_period_days && parseInt(p.trial_period_days) > 0) {
                html += '<p class="trial-info">' + t('trial', 'Free trial') + ': ' + p.trial_period_days + ' ' + t('days', 'days') + '</p>';
            }
            html += '<button class="btn-select-plan" onclick="selectPlan(' + p.id + ')">' + t('select_plan', 'Select Plan') + '</button>';
            html += '</div>';
        }
        grid.innerHTML = html;
    }

    window.selectPlan = function(planId) {
        if (!confirm(t('confirm_select', 'Subscribe to this plan?'))) return;
        var tenantId = C.tenantId;
        if (C.isSuperAdmin && !tenantId) {
            var input = prompt(t('enter_tenant_id', 'Enter tenant ID:'));
            if (!input) return;
            tenantId = parseInt(input);
        }
        if (!tenantId) { alert(t('no_tenant', 'No tenant selected')); return; }

        // Try upgrade first, fallback to create
        fetch(C.apiBase + '/subscriptions', {
            method: 'PUT',
            credentials: 'include',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ upgrade: true, plan_id: planId, tenant_id: tenantId })
        })
        .then(function(r) { return r.json(); })
        .then(function(d) {
            if (d.success) {
                showNotification(t('subscribed', 'Successfully subscribed!'), 'success');
                loadCurrentPlan();
            } else {
                // If no active subscription to upgrade, create new
                fetch(C.apiBase + '/subscription_plans?id=' + planId, { credentials: 'include' })
                .then(function(r) { return r.json(); })
                .then(function(pd) {
                    var plan = pd.data && pd.data.items ? pd.data.items[0] : (pd.data || null);
                    if (!plan) plan = pd.data;
                    var startDate = new Date().toISOString().split('T')[0];
                    var bp = plan.billing_period || 'monthly';
                    var endDate = calcEndDate(startDate, bp);
                    var trialEnd = (plan.trial_period_days && parseInt(plan.trial_period_days) > 0) ?
                        calcEndDate(startDate, plan.trial_period_days + ' days') : null;

                    return fetch(C.apiBase + '/subscriptions', {
                        method: 'POST',
                        credentials: 'include',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            tenant_id: tenantId,
                            plan_id: planId,
                            billing_period: bp,
                            price: plan.price,
                            currency_code: plan.currency_code || 'SAR',
                            start_date: startDate,
                            end_date: endDate,
                            trial_end_date: trialEnd,
                            next_billing_date: endDate,
                            status: trialEnd ? 'trial' : 'active'
                        })
                    });
                })
                .then(function(r) { return r.json(); })
                .then(function(d2) {
                    if (d2.success) {
                        showNotification(t('subscribed', 'Successfully subscribed!'), 'success');
                        loadCurrentPlan();
                    } else {
                        showNotification(d2.message || t('error', 'Error'), 'error');
                    }
                });
            }
        })
        .catch(function(e) { showNotification(e.message || t('error', 'Error'), 'error'); });
    };

    function calcEndDate(start, period) {
        var d = new Date(start);
        switch(period) {
            case 'daily': d.setDate(d.getDate() + 1); break;
            case 'weekly': d.setDate(d.getDate() + 7); break;
            case 'monthly': d.setMonth(d.getMonth() + 1); break;
            case 'quarterly': d.setMonth(d.getMonth() + 3); break;
            case 'yearly': d.setFullYear(d.getFullYear() + 1); break;
            case 'lifetime': d.setFullYear(d.getFullYear() + 100); break;
            default: d.setMonth(d.getMonth() + 1);
        }
        return d.toISOString().split('T')[0];
    }

    function esc(s) { var d = document.createElement('div'); d.textContent = s || ''; return d.innerHTML; }

    function showNotification(msg, type) {
        var n = document.createElement('div');
        n.className = 'notification notification-' + (type || 'info');
        n.textContent = msg;
        document.body.appendChild(n);
        setTimeout(function(){ n.style.opacity = '0'; setTimeout(function(){ n.remove(); }, 300); }, 3000);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
