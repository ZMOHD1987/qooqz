// /admin/assets/js/pages/entities_payment.js
(function(){
    'use strict';

    var CFG, API_BASE, CSRF, STRINGS, entityId, CAN_EDIT, CAN_DELETE, IS_SUPER;
    var paymentMethodsMap = {};

    function reloadConfig(){
        CFG = window.ENTITIES_PAYMENT_CONFIG || {};
        API_BASE = CFG.apiBase || '/api';
        CSRF = CFG.csrfToken || '';
        STRINGS = CFG.strings || {};
        entityId = CFG.entityId || 0;
        CAN_EDIT = !!CFG.canEdit;
        CAN_DELETE = !!CFG.canDelete;
        IS_SUPER = !!CFG.isSuperAdmin;
    }

    function t(key, fallback){
        var keys = key.split('.');
        var val = STRINGS;
        for(var i = 0; i < keys.length; i++){
            if(val && typeof val === 'object' && keys[i] in val){
                val = val[keys[i]];
            } else {
                return fallback || key;
            }
        }
        return (typeof val === 'string') ? val : (fallback || key);
    }

    function esc(str){
        if(str == null) return '';
        var d = document.createElement('div');
        d.appendChild(document.createTextNode(String(str)));
        return d.innerHTML;
    }

    function showNotification(msg, type){
        type = type || 'info';
        var container = document.getElementById('notificationContainer');
        if(!container){
            container = document.createElement('div');
            container.id = 'notificationContainer';
            container.style.cssText = 'position:fixed;top:20px;right:20px;z-index:10000;max-width:400px;';
            document.body.appendChild(container);
        }
        var div = document.createElement('div');
        div.className = 'notification notification-' + type;
        div.textContent = msg;
        div.style.cssText = 'padding:12px 20px;margin-bottom:10px;border-radius:6px;color:#fff;font-size:14px;cursor:pointer;' +
            (type === 'success' ? 'background:var(--success-color,#28a745);' :
             type === 'error' ? 'background:var(--danger-color,#dc3545);' :
             'background:var(--info-color,#17a2b8);');
        container.appendChild(div);
        div.addEventListener('click', function(){ div.remove(); });
        setTimeout(function(){ div.remove(); }, 4000);
    }

    function openModal(id){ document.getElementById(id).style.display = 'block'; }
    function closeModal(id){ document.getElementById(id).style.display = 'none'; }

    // Load payment methods from /api/payment_methods into dropdown
    function loadPaymentMethodOptions(){
        fetch(API_BASE + '/payment_methods?limit=200')
        .then(function(r){ return r.json(); })
        .then(function(d){
            var sel = document.getElementById('pmPaymentMethodId');
            var filterSel = document.getElementById('pmFilterMethod');
            if(sel) while(sel.options.length > 1) sel.remove(1);
            if(filterSel) while(filterSel.options.length > 1) filterSel.remove(1);
            if(d.success && d.data){
                var items = d.data.items || (Array.isArray(d.data) ? d.data : []);
                items.forEach(function(pm){
                    paymentMethodsMap[pm.id] = pm.method_name || pm.gateway_name || pm.method_key;
                    var label = pm.method_name + (pm.gateway_name ? ' (' + pm.gateway_name + ')' : '');
                    if(sel){
                        var opt = document.createElement('option');
                        opt.value = pm.id;
                        opt.textContent = label;
                        sel.appendChild(opt);
                    }
                    if(filterSel){
                        var opt2 = document.createElement('option');
                        opt2.value = pm.id;
                        opt2.textContent = label;
                        filterSel.appendChild(opt2);
                    }
                });
            }
        })
        .catch(function(err){ console.error('Failed to load payment methods:', err); });
    }

    // Verify tenant and load its entities (super admin)
    function verifyTenant(tenantId){
        var display = document.getElementById('tenantNameDisplay');
        var filter = document.getElementById('globalEntityFilter');
        if(!tenantId || tenantId < 1){
            if(display){ display.style.display='none'; }
            return;
        }
        fetch(API_BASE + '/tenants?id=' + tenantId)
        .then(function(r){ return r.json(); })
        .then(function(d){
            if(d.success && d.data){
                var items = d.data.items || (Array.isArray(d.data) ? d.data : []);
                var tenant = null;
                for(var i=0; i<items.length; i++){
                    if(parseInt(items[i].id) === parseInt(tenantId)){
                        tenant = items[i]; break;
                    }
                }
                if(!tenant && items.length > 0) tenant = items[0];
                if(tenant){
                    if(display){
                        display.textContent = '✓ ' + (tenant.name || 'Tenant #' + tenantId);
                        display.style.display='block';
                        display.style.color='var(--success-color, green)';
                    }
                    // Load entities for this tenant
                    loadEntitiesForTenant(tenantId);
                } else {
                    if(display){
                        display.textContent = '✗ ' + t('tenant_not_found', 'Tenant not found');
                        display.style.display='block';
                        display.style.color='var(--danger-color, red)';
                    }
                }
            }
        })
        .catch(function(err){
            console.error('Tenant verify error:', err);
            if(display){
                display.textContent = '✗ Error';
                display.style.display='block';
                display.style.color='var(--danger-color, red)';
            }
        });
    }

    // Load entities for a specific tenant into the entity dropdown
    function loadEntitiesForTenant(tenantId){
        var filter = document.getElementById('globalEntityFilter');
        if(!filter) return;
        // Clear existing options except "All"
        while(filter.options.length > 1) filter.remove(1);
        var url = API_BASE + '/entities?limit=200';
        if(tenantId) url += '&tenant_id=' + tenantId;
        fetch(url)
        .then(function(r){ return r.json(); })
        .then(function(d){
            if(d.success && d.data){
                var items = d.data.items || (Array.isArray(d.data) ? d.data : []);
                items.forEach(function(ent){
                    var opt = document.createElement('option');
                    opt.value = ent.id;
                    opt.textContent = ent.store_name || ent.name || ('Entity #' + ent.id);
                    filter.appendChild(opt);
                });
            }
        })
        .catch(function(err){ console.error('Failed to load entities:', err); });
    }

    // Load entity filter based on user role
    function loadEntitySelector(){
        var filter = document.getElementById('globalEntityFilter');

        if(IS_SUPER){
            // Super admin: tenant input → verify → entity cascade
            var tenantInput = document.getElementById('tenantIdInput');
            var btnVerify = document.getElementById('btnVerifyTenant');

            if(btnVerify && tenantInput){
                btnVerify.addEventListener('click', function(){
                    var tid = parseInt(tenantInput.value);
                    if(tid > 0) verifyTenant(tid);
                });
                tenantInput.addEventListener('keypress', function(e){
                    if(e.key === 'Enter'){
                        var tid = parseInt(tenantInput.value);
                        if(tid > 0) verifyTenant(tid);
                    }
                });
            }

            // Load ALL entities initially (no tenant filter)
            if(filter) loadEntitiesForTenant(0);

            if(filter){
                filter.addEventListener('change', function(){
                    entityId = filter.value ? parseInt(filter.value) : 0;
                    loadPayments();
                    loadBanks();
                });
            }

            // Show tabs and load ALL data immediately
            entityId = 0;
            var tabs = document.querySelector('.content-tabs');
            if(tabs) tabs.style.display = 'block';
            loadPayments();
            loadBanks();

        } else if(filter){
            // Tenant admin: load own tenant's entities
            var tenantId = CFG.tenantId || 0;
            loadEntitiesForTenant(tenantId);

            filter.addEventListener('change', function(){
                entityId = filter.value ? parseInt(filter.value) : 0;
                if(entityId){
                    var tabs = document.querySelector('.content-tabs');
                    if(tabs) tabs.style.display = 'block';
                    loadPayments();
                    loadBanks();
                }
            });

        } else {
            // Entity user: load own entity data directly
            var tabs = document.querySelector('.content-tabs');
            if(tabs) tabs.style.display = 'block';
            loadPayments();
            loadBanks();
        }
    }

    // Collect payment filters
    function collectPaymentFilters(){
        var params = [];
        var search = document.getElementById('pmFilterSearch');
        var method = document.getElementById('pmFilterMethod');
        var status = document.getElementById('pmFilterStatus');
        var dateFrom = document.getElementById('pmFilterDateFrom');
        var dateTo = document.getElementById('pmFilterDateTo');
        if(search && search.value.trim()) params.push('search=' + encodeURIComponent(search.value.trim()));
        if(method && method.value) params.push('payment_method_id=' + encodeURIComponent(method.value));
        if(status && status.value !== '') params.push('is_active=' + encodeURIComponent(status.value));
        if(dateFrom && dateFrom.value) params.push('date_from=' + encodeURIComponent(dateFrom.value));
        if(dateTo && dateTo.value) params.push('date_to=' + encodeURIComponent(dateTo.value));
        return params;
    }

    // Load Payment Methods table
    function loadPayments(){
        if(!entityId && !IS_SUPER) return;
        var url = API_BASE + '/entity_payment_methods';
        var params = [];
        if(entityId) params.push('entity_id=' + entityId);
        var filters = collectPaymentFilters();
        params = params.concat(filters);
        if(params.length) url += '?' + params.join('&');
        fetch(url)
        .then(function(r){ return r.json(); })
        .then(function(d){
            var tbody = document.getElementById('paymentMethodsBody');
            if(!tbody) return;
            tbody.innerHTML = '';
            if(d.success && d.data){
                var items = Array.isArray(d.data) ? d.data : (d.data.items || []);
                if(items.length === 0){
                    var cols = IS_SUPER && !entityId ? 7 : 6;
                    tbody.innerHTML = '<tr><td colspan="' + cols + '" style="text-align:center;">' + t('no_records', 'No records found') + '</td></tr>';
                    return;
                }
                var showAll = IS_SUPER && !entityId;
                items.forEach(function(p){
                    var methodName = p.method_name || paymentMethodsMap[p.payment_method_id] || p.gateway_name || '-';
                    var tr = document.createElement('tr');
                    var actionsHtml = '';
                    if(CAN_EDIT){
                        actionsHtml += '<button class="btn btn-sm btn-info edit-payment-btn" data-id="' + esc(p.id) + '">' + t('table.edit', 'Edit') + '</button> ';
                    }
                    if(CAN_DELETE){
                        actionsHtml += '<button class="btn btn-sm btn-danger delete-payment-btn" data-id="' + esc(p.id) + '">' + t('table.delete', 'Delete') + '</button>';
                    }
                    tr.innerHTML =
                        '<td>' + esc(p.id) + '</td>' +
                        (showAll ? '<td>' + esc(p.entity_name || 'Entity #' + p.entity_id) + '</td>' : '') +
                        '<td>' + esc(methodName) + '</td>' +
                        '<td>' + esc(p.account_email || '') + '</td>' +
                        '<td>' + esc(p.account_id || '') + '</td>' +
                        '<td>' + (p.is_active ? t('table.yes', 'Yes') : t('table.no', 'No')) + '</td>' +
                        '<td>' + actionsHtml + '</td>';
                    tbody.appendChild(tr);
                });
            }
        })
        .catch(function(err){ console.error('Failed to load payments:', err); });
    }

    // Collect bank filters
    function collectBankFilters(){
        var params = [];
        var search = document.getElementById('bankFilterSearch');
        var status = document.getElementById('bankFilterStatus');
        var dateFrom = document.getElementById('bankFilterDateFrom');
        var dateTo = document.getElementById('bankFilterDateTo');
        if(search && search.value.trim()) params.push('search=' + encodeURIComponent(search.value.trim()));
        if(status && status.value !== '') params.push('is_active=' + encodeURIComponent(status.value));
        if(dateFrom && dateFrom.value) params.push('date_from=' + encodeURIComponent(dateFrom.value));
        if(dateTo && dateTo.value) params.push('date_to=' + encodeURIComponent(dateTo.value));
        return params;
    }

    // Load Bank Accounts table
    function loadBanks(){
        if(!entityId && !IS_SUPER) return;
        var url = API_BASE + '/entity_bank_accounts';
        var params = [];
        if(entityId) params.push('entity_id=' + entityId);
        var filters = collectBankFilters();
        params = params.concat(filters);
        if(params.length) url += '?' + params.join('&');
        fetch(url)
        .then(function(r){ return r.json(); })
        .then(function(d){
            var tbody = document.getElementById('bankAccountsBody');
            if(!tbody) return;
            tbody.innerHTML = '';
            if(d.success && d.data){
                var items = Array.isArray(d.data) ? d.data : (d.data.items || []);
                if(items.length === 0){
                    var cols = IS_SUPER && !entityId ? 10 : 9;
                    tbody.innerHTML = '<tr><td colspan="' + cols + '" style="text-align:center;">' + t('no_records', 'No records found') + '</td></tr>';
                    return;
                }
                var showAll = IS_SUPER && !entityId;
                items.forEach(function(b){
                    var tr = document.createElement('tr');
                    var actionsHtml = '';
                    if(CAN_EDIT){
                        actionsHtml += '<button class="btn btn-sm btn-info edit-bank-btn" data-id="' + esc(b.id) + '">' + t('table.edit', 'Edit') + '</button> ';
                    }
                    if(CAN_DELETE){
                        actionsHtml += '<button class="btn btn-sm btn-danger delete-bank-btn" data-id="' + esc(b.id) + '">' + t('table.delete', 'Delete') + '</button>';
                    }
                    tr.innerHTML =
                        '<td>' + esc(b.id) + '</td>' +
                        (showAll ? '<td>' + esc(b.entity_name || 'Entity #' + b.entity_id) + '</td>' : '') +
                        '<td>' + esc(b.bank_name) + '</td>' +
                        '<td>' + esc(b.account_holder_name) + '</td>' +
                        '<td>' + esc(b.account_number) + '</td>' +
                        '<td>' + esc(b.iban || '') + '</td>' +
                        '<td>' + esc(b.swift_code || '') + '</td>' +
                        '<td>' + (b.is_primary ? t('table.yes', 'Yes') : t('table.no', 'No')) + '</td>' +
                        '<td>' + (b.is_verified ? t('table.yes', 'Yes') : t('table.no', 'No')) + '</td>' +
                        '<td>' + actionsHtml + '</td>';
                    tbody.appendChild(tr);
                });
            }
        })
        .catch(function(err){ console.error('Failed to load banks:', err); });
    }

    // Delegated click handlers for edit/delete
    function setupClickHandlers(){
        document.addEventListener('click', function(e){
            // Edit Payment
            var editPm = e.target.closest('.edit-payment-btn');
            if(editPm){
                var recId = editPm.dataset.id;
                fetch(API_BASE + '/entity_payment_methods?entity_id=' + entityId + '&id=' + recId)
                .then(function(r){ return r.json(); })
                .then(function(d){
                    if(d.success && d.data){
                        var rec = d.data;
                        document.getElementById('pmEditId').value = rec.id;
                        document.getElementById('pmPaymentMethodId').value = rec.payment_method_id || '';
                        document.getElementById('pmEmail').value = rec.account_email || '';
                        document.getElementById('pmAccountId').value = rec.account_id || '';
                        document.getElementById('pmActive').value = rec.is_active ? '1' : '0';
                        document.getElementById('paymentModalTitle').textContent = t('payment_methods.edit', 'Edit Payment Method');
                        openModal('paymentMethodModal');
                    }
                });
                return;
            }

            // Edit Bank
            var editBa = e.target.closest('.edit-bank-btn');
            if(editBa){
                var recId2 = editBa.dataset.id;
                fetch(API_BASE + '/entity_bank_accounts?entity_id=' + entityId + '&id=' + recId2)
                .then(function(r){ return r.json(); })
                .then(function(d){
                    if(d.success && d.data){
                        var rec = d.data;
                        document.getElementById('baEditId').value = rec.id;
                        document.getElementById('baBankName').value = rec.bank_name || '';
                        document.getElementById('baHolderName').value = rec.account_holder_name || '';
                        document.getElementById('baAccountNumber').value = rec.account_number || '';
                        document.getElementById('baIban').value = rec.iban || '';
                        document.getElementById('baSwift').value = rec.swift_code || '';
                        document.getElementById('baPrimary').value = rec.is_primary ? '1' : '0';
                        document.getElementById('baVerified').value = rec.is_verified ? '1' : '0';
                        document.getElementById('bankModalTitle').textContent = t('bank_accounts.edit', 'Edit Bank Account');
                        openModal('bankAccountModal');
                    }
                });
                return;
            }

            // Delete Payment
            var delPm = e.target.closest('.delete-payment-btn');
            if(delPm){
                if(!confirm(t('confirm_delete_payment', 'Delete this payment method?'))) return;
                fetch(API_BASE + '/entity_payment_methods?id=' + delPm.dataset.id + '&entity_id=' + entityId, {
                    method: 'DELETE',
                    headers: {'X-CSRF-TOKEN': CSRF}
                }).then(function(r){ return r.json(); }).then(function(d){
                    if(d.success){ showNotification(t('deleted', 'Deleted'), 'success'); loadPayments(); }
                    else showNotification(d.message || t('delete_failed', 'Delete failed'), 'error');
                });
                return;
            }

            // Delete Bank
            var delBa = e.target.closest('.delete-bank-btn');
            if(delBa){
                if(!confirm(t('confirm_delete_bank', 'Delete this bank account?'))) return;
                fetch(API_BASE + '/entity_bank_accounts?id=' + delBa.dataset.id + '&entity_id=' + entityId, {
                    method: 'DELETE',
                    headers: {'X-CSRF-TOKEN': CSRF}
                }).then(function(r){ return r.json(); }).then(function(d){
                    if(d.success){ showNotification(t('deleted', 'Deleted'), 'success'); loadBanks(); }
                    else showNotification(d.message || t('delete_failed', 'Delete failed'), 'error');
                });
                return;
            }
        });
    }

    function init(){
        reloadConfig();

        // Close modal buttons
        document.querySelectorAll('.btn-close-modal').forEach(function(btn){
            btn.addEventListener('click', function(){ closeModal(btn.dataset.modal); });
        });

        // Tab switching
        document.querySelectorAll('.tab-btn').forEach(function(btn){
            btn.addEventListener('click', function(){
                document.querySelectorAll('.tab-btn').forEach(function(b){ b.classList.remove('active'); });
                document.querySelectorAll('.tab-content').forEach(function(c){ c.classList.remove('active'); });
                btn.classList.add('active');
                var target = document.getElementById('tab-' + btn.dataset.tab);
                if(target) target.classList.add('active');
            });
        });

        // Filter Payment Methods
        var btnFilterPm = document.getElementById('btnFilterPayments');
        if(btnFilterPm) btnFilterPm.addEventListener('click', function(){ loadPayments(); });
        var btnClearPm = document.getElementById('btnClearPaymentFilters');
        if(btnClearPm) btnClearPm.addEventListener('click', function(){
            var s = document.getElementById('pmFilterSearch'); if(s) s.value = '';
            var m = document.getElementById('pmFilterMethod'); if(m) m.value = '';
            var st = document.getElementById('pmFilterStatus'); if(st) st.value = '';
            var df = document.getElementById('pmFilterDateFrom'); if(df) df.value = '';
            var dt = document.getElementById('pmFilterDateTo'); if(dt) dt.value = '';
            loadPayments();
        });

        // Filter Bank Accounts
        var btnFilterBa = document.getElementById('btnFilterBanks');
        if(btnFilterBa) btnFilterBa.addEventListener('click', function(){ loadBanks(); });
        var btnClearBa = document.getElementById('btnClearBankFilters');
        if(btnClearBa) btnClearBa.addEventListener('click', function(){
            var s = document.getElementById('bankFilterSearch'); if(s) s.value = '';
            var st = document.getElementById('bankFilterStatus'); if(st) st.value = '';
            var df = document.getElementById('bankFilterDateFrom'); if(df) df.value = '';
            var dt = document.getElementById('bankFilterDateTo'); if(dt) dt.value = '';
            loadBanks();
        });

        // Add Payment button
        var btnAddPm = document.getElementById('btnAddPayment');
        if(btnAddPm){
            btnAddPm.addEventListener('click', function(){
                document.getElementById('paymentModalTitle').textContent = t('payment_methods.add', 'Add Payment Method');
                document.getElementById('paymentMethodForm').reset();
                document.getElementById('pmEditId').value = '';
                openModal('paymentMethodModal');
            });
        }

        // Add Bank button
        var btnAddBa = document.getElementById('btnAddBank');
        if(btnAddBa){
            btnAddBa.addEventListener('click', function(){
                document.getElementById('bankModalTitle').textContent = t('bank_accounts.add', 'Add Bank Account');
                document.getElementById('bankAccountForm').reset();
                document.getElementById('baEditId').value = '';
                openModal('bankAccountModal');
            });
        }

        // Submit Payment Method
        var pmForm = document.getElementById('paymentMethodForm');
        if(pmForm){
            pmForm.addEventListener('submit', function(e){
                e.preventDefault();
                if(!entityId){ showNotification(t('select_entity_first', 'Please select an entity first'), 'error'); return; }
                var editId = document.getElementById('pmEditId').value;
                var payload = {
                    entity_id: entityId,
                    payment_method_id: parseInt(document.getElementById('pmPaymentMethodId').value) || 0,
                    account_email: document.getElementById('pmEmail').value,
                    account_id: document.getElementById('pmAccountId').value,
                    is_active: parseInt(document.getElementById('pmActive').value)
                };
                if(editId) payload.id = parseInt(editId);
                fetch(API_BASE + '/entity_payment_methods?entity_id=' + entityId, {
                    method: editId ? 'PUT' : 'POST',
                    headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF},
                    body: JSON.stringify(payload)
                }).then(function(r){ return r.json(); }).then(function(d){
                    if(d.success){
                        closeModal('paymentMethodModal');
                        pmForm.reset();
                        document.getElementById('pmEditId').value = '';
                        showNotification(t('saved', 'Saved successfully'), 'success');
                        loadPayments();
                    } else {
                        showNotification(d.message || t('unknown_error', 'Error'), 'error');
                    }
                });
            });
        }

        // Submit Bank Account
        var baForm = document.getElementById('bankAccountForm');
        if(baForm){
            baForm.addEventListener('submit', function(e){
                e.preventDefault();
                if(!entityId){ showNotification(t('select_entity_first', 'Please select an entity first'), 'error'); return; }
                var editId = document.getElementById('baEditId').value;
                fetch(API_BASE + '/entity_bank_accounts', {
                    method: editId ? 'PUT' : 'POST',
                    headers: {'X-CSRF-TOKEN': CSRF},
                    body: new FormData(baForm)
                }).then(function(r){ return r.json(); }).then(function(d){
                    if(d.success){
                        closeModal('bankAccountModal');
                        baForm.reset();
                        document.getElementById('baEditId').value = '';
                        showNotification(t('saved', 'Saved successfully'), 'success');
                        loadBanks();
                    } else {
                        showNotification(d.message || t('unknown_error', 'Error'), 'error');
                    }
                });
            });
        }

        setupClickHandlers();
        loadPaymentMethodOptions();
        loadEntitySelector();

        if(entityId){
            loadPayments();
            loadBanks();
        }
    }

    // Init: handle both DOMContentLoaded and dynamic fragment loading
    if(document.readyState === 'loading'){
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();