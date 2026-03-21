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

    var selectedPlanId = null;
    var selectedPlanData = null;
    var selectedTenantId = null;
    var selectedPaymentMethod = null;

    window.selectPlan = function(planId) {
        var tenantId = C.tenantId;
        if (C.isSuperAdmin && !tenantId) {
            var input = prompt(t('enter_tenant_id', 'Enter tenant ID:'));
            if (!input) return;
            tenantId = parseInt(input);
        }
        if (!tenantId) { alert(t('no_tenant', 'No tenant selected')); return; }

        selectedPlanId = planId;
        selectedTenantId = tenantId;

        // Load plan details and show payment step
        fetch(C.apiBase + '/subscription_plans?id=' + planId, { credentials: 'include' })
        .then(function(r) { return r.json(); })
        .then(function(d) {
            var plan = d.data && d.data.items ? d.data.items[0] : (d.data || null);
            if (!plan) { showNotification(t('error', 'Plan not found'), 'error'); return; }
            selectedPlanData = plan;

            // Hide plans, show payment step
            document.getElementById('planCardsGrid').style.display = 'none';
            var ci = document.getElementById('currentPlanInfo');
            if (ci) ci.style.display = 'none';

            var ps = document.getElementById('paymentStep');
            ps.style.display = 'block';
            document.getElementById('paymentStepAmount').textContent =
                t('payment.amount', 'Amount') + ': ' + plan.price + ' ' + (plan.currency_code || 'SAR') +
                ' / ' + plan.billing_period;

            loadPlatformPaymentOptions();
        })
        .catch(function(e) { showNotification(e.message || t('error', 'Error'), 'error'); });
    };

    function loadPlatformPaymentOptions() {
        var list = document.getElementById('paymentMethodsList');
        list.innerHTML = '<div class="loading-spinner">' + t('loading', 'Loading...') + '</div>';

        Promise.all([
            fetch(C.apiBase + '/entity_bank_accounts?entity_id=1', { credentials: 'include' }).then(function(r) { return r.json(); }),
            fetch(C.apiBase + '/entity_payment_methods?entity_id=1', { credentials: 'include' }).then(function(r) { return r.json(); })
        ]).then(function(results) {
            var banks = Array.isArray(results[0].data) ? results[0].data :
                       (results[0].data && results[0].data.items ? results[0].data.items : []);
            var methods = Array.isArray(results[1].data) ? results[1].data :
                         (results[1].data && results[1].data.items ? results[1].data.items : []);

            var html = '';

            // Bank transfer option
            if (banks.length > 0) {
                html += '<div class="payment-method-card" onclick="selectPaymentMethod(\'bank_transfer\')" data-method="bank_transfer">';
                html += '<div class="pm-icon">🏦</div>';
                html += '<div class="pm-info"><h4>' + t('payment.bank_transfer', 'Bank Transfer') + '</h4>';
                html += '<p>' + t('payment.bank_desc', 'Transfer to our bank account') + '</p></div></div>';
                window._platformBanks = banks;
            }

            // Payment gateway options
            for (var i = 0; i < methods.length; i++) {
                var m = methods[i];
                var name = m.method_name || m.gateway_name || 'Payment Gateway';
                html += '<div class="payment-method-card" onclick="selectPaymentMethod(\'gateway_' + m.id + '\')" data-method="gateway_' + m.id + '">';
                html += '<div class="pm-icon">' + (m.icon_url ? '<img src="' + esc(m.icon_url) + '" alt="">' : '💳') + '</div>';
                html += '<div class="pm-info"><h4>' + esc(name) + '</h4>';
                html += '<p>' + esc(m.account_email || m.method_key || '') + '</p></div></div>';
            }

            if (!html) html = '<p class="no-methods">' + t('payment.no_methods', 'No payment methods available') + '</p>';
            list.innerHTML = html;
        }).catch(function() {
            list.innerHTML = '<p class="error-msg">' + t('payment.error_loading', 'Error loading payment options') + '</p>';
        });
    }

    window.selectPaymentMethod = function(method) {
        selectedPaymentMethod = method;
        var cards = document.querySelectorAll('.payment-method-card');
        for (var i = 0; i < cards.length; i++) cards[i].classList.remove('selected');
        var clicked = document.querySelector('[data-method="' + method + '"]');
        if (clicked) clicked.classList.add('selected');

        document.getElementById('bankTransferDetails').style.display = 'none';
        document.getElementById('cardPaymentForm').style.display = 'none';
        document.getElementById('paymentConfirmation').style.display = 'none';

        if (method === 'bank_transfer') {
            var banks = window._platformBanks || [];
            var html = '';
            for (var i = 0; i < banks.length; i++) {
                var b = banks[i];
                html += '<div class="bank-detail-row"><strong>' + t('payment.bank_name', 'Bank') + ':</strong> ' + esc(b.bank_name) + '</div>';
                html += '<div class="bank-detail-row"><strong>' + t('payment.account_holder', 'Account Holder') + ':</strong> ' + esc(b.account_holder_name) + '</div>';
                if (b.account_number) html += '<div class="bank-detail-row"><strong>' + t('payment.account_number', 'Account Number') + ':</strong> ' + esc(b.account_number) + '</div>';
                if (b.iban) html += '<div class="bank-detail-row"><strong>IBAN:</strong> ' + esc(b.iban) + '</div>';
                if (b.swift_code) html += '<div class="bank-detail-row"><strong>SWIFT:</strong> ' + esc(b.swift_code) + '</div>';
                if (i < banks.length - 1) html += '<hr>';
            }
            document.getElementById('bankAccountInfo').innerHTML = html;
            document.getElementById('bankTransferDetails').style.display = 'block';
        } else {
            document.getElementById('cardPaymentForm').style.display = 'block';
        }
    };

    window.confirmBankTransfer = function() {
        createSubscriptionAndPayment('bank_transfer', null);
    };

    window.processCardPayment = function() {
        var cardNum = (document.getElementById('cardNumber').value || '').replace(/\s/g, '');
        var expiry = document.getElementById('cardExpiry').value || '';
        var cvv = document.getElementById('cardCVV').value || '';
        var name = document.getElementById('cardholderName').value || '';
        if (cardNum.length < 13) { showNotification(t('payment.invalid_card', 'Invalid card number'), 'error'); return; }
        if (!/^(0[1-9]|1[0-2])\/\d{2}$/.test(expiry)) { showNotification(t('payment.invalid_expiry', 'Invalid expiry'), 'error'); return; }
        var parts = expiry.split('/');
        var expMonth = parseInt(parts[0]); var expYear = 2000 + parseInt(parts[1]);
        var now = new Date(); if (expYear < now.getFullYear() || (expYear === now.getFullYear() && expMonth < now.getMonth()+1)) {
            showNotification(t('payment.expired_card', 'Card is expired'), 'error'); return; }
        if (cvv.length < 3) { showNotification(t('payment.invalid_cvv', 'Invalid CVV'), 'error'); return; }
        if (!name.trim()) { showNotification(t('payment.enter_name', 'Enter cardholder name'), 'error'); return; }
        // Note: In production, card data should be tokenized via gateway SDK (Stripe Elements, etc.)
        // This creates a pending payment record - actual charge happens server-side via gateway API
        var arr = new Uint32Array(2);
        crypto.getRandomValues(arr);
        createSubscriptionAndPayment(selectedPaymentMethod.replace('gateway_', ''), 'TXN-' + arr[0].toString(36) + arr[1].toString(36));
    };

    function createSubscriptionAndPayment(gateway, transactionId) {
        var plan = selectedPlanData;
        var startDate = new Date().toISOString().split('T')[0];
        var bp = plan.billing_period || 'monthly';
        var endDate = calcEndDate(startDate, bp);
        var trialEnd = (plan.trial_period_days && parseInt(plan.trial_period_days) > 0) ?
            calcEndDate(startDate, plan.trial_period_days + ' days') : null;

        // Try upgrade first, then create new
        fetch(C.apiBase + '/subscriptions', {
            method: 'PUT', credentials: 'include',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ upgrade: true, plan_id: selectedPlanId, tenant_id: selectedTenantId })
        })
        .then(function(r) { return r.json(); })
        .then(function(d) {
            if (!d.success) {
                return fetch(C.apiBase + '/subscriptions', {
                    method: 'POST', credentials: 'include',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        tenant_id: selectedTenantId, plan_id: selectedPlanId,
                        billing_period: bp, price: plan.price,
                        currency_code: plan.currency_code || 'SAR',
                        start_date: startDate, end_date: endDate,
                        trial_end_date: trialEnd, next_billing_date: endDate,
                        status: trialEnd ? 'trial' : 'active'
                    })
                }).then(function(r) { return r.json(); });
            }
            return d;
        })
        .then(function(d) {
            if (!d.success) { showNotification(d.message || t('error', 'Error'), 'error'); return; }
            var subId = d.data && d.data.id ? d.data.id : 0;
            var invId = d.data && d.data.invoice_id ? d.data.invoice_id : 0;

            var rnd = new Uint32Array(1);
            crypto.getRandomValues(rnd);
            var payNum = 'PAY-' + new Date().toISOString().slice(0,10).replace(/-/g,'') + '-' + (rnd[0] % 90000 + 10000);
            return fetch(C.apiBase + '/subscription_payments', {
                method: 'POST', credentials: 'include',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    payment_number: payNum, invoice_id: invId,
                    subscription_id: subId, tenant_id: selectedTenantId,
                    amount: plan.price, currency_code: plan.currency_code || 'SAR',
                    payment_gateway: gateway === 'bank_transfer' ? 'bank_transfer' : gateway,
                    gateway_transaction_id: transactionId, status: 'pending'
                })
            }).then(function(r) { return r.json(); });
        })
        .then(function(pd) {
            if (pd && pd.success) {
                document.getElementById('paymentMethodsList').style.display = 'none';
                document.getElementById('bankTransferDetails').style.display = 'none';
                document.getElementById('cardPaymentForm').style.display = 'none';
                document.getElementById('paymentConfirmation').style.display = 'block';
                var msg = gateway === 'bank_transfer'
                    ? t('payment.bank_success', 'Subscription will activate after transfer verification.')
                    : t('payment.card_success', 'Payment submitted. Subscription is being activated.');
                document.getElementById('confirmationMessage').textContent = msg;
                showNotification(t('subscribed', 'Successfully subscribed!'), 'success');
            } else if (pd) {
                showNotification(pd.message || t('error', 'Error'), 'error');
            }
        })
        .catch(function(e) { showNotification(e.message || t('error', 'Error'), 'error'); });
    }

    window.backToPlans = function() {
        document.getElementById('paymentStep').style.display = 'none';
        document.getElementById('planCardsGrid').style.display = 'grid';
        var ci = document.getElementById('currentPlanInfo');
        if (ci) ci.style.display = 'block';
        loadCurrentPlan();
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