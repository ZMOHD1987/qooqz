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
            if(!sel) return;
            while(sel.options.length > 1) sel.remove(1);
            if(d.success && d.data){
                var items = d.data.items || (Array.isArray(d.data) ? d.data : []);
                items.forEach(function(pm){
                    paymentMethodsMap[pm.id] = pm.method_name || pm.gateway_name || pm.method_key;
                    var opt = document.createElement('option');
                    opt.value = pm.id;
                    opt.textContent = pm.method_name + (pm.gateway_name ? ' (' + pm.gateway_name + ')' : '');
                    sel.appendChild(opt);
                });
            }
        })
        .catch(function(err){ console.error('Failed to load payment methods:', err); });
    }

    // Load entity selector
    function loadEntitySelector(){
        var entitySelector = document.getElementById('entitySelector');
        var btnLoad = document.getElementById('btnLoadEntityPayments');
        if(!entitySelector) return;

        // Super admin: tenant verification + entity cascade
        if(IS_SUPER){
            var btnVerify = document.getElementById('btnVerifyTenant');
            var tenantInput = document.getElementById('tenantIdInput');
            var tenantName = document.getElementById('tenantNameDisplay');

            if(btnVerify && tenantInput){
                btnVerify.addEventListener('click', function(){
                    var tid = parseInt(tenantInput.value);
                    if(!tid){ showNotification(t('enter_tenant_id', 'Enter Tenant ID'), 'error'); return; }
                    fetch(API_BASE + '/tenants?id=' + tid)
                    .then(function(r){ return r.json(); })
                    .then(function(d){
                        var tData = null;
                        if(d.success && d.data){
                            if(d.data.items && Array.isArray(d.data.items)){
                                for(var i = 0; i < d.data.items.length; i++){
                                    if(parseInt(d.data.items[i].id) === tid){ tData = d.data.items[i]; break; }
                                }
                                if(!tData && d.data.items.length > 0) tData = d.data.items[0];
                            } else if(d.data.name || d.data.id){
                                tData = d.data;
                            }
                        }
                        if(tData){
                            if(tenantName) tenantName.textContent = tData.name || ('Tenant #' + tData.id);
                            if(tenantName) tenantName.classList.remove('error');
                            loadEntitiesByTenant(tid);
                        } else {
                            if(tenantName){ tenantName.textContent = t('tenant_not_found', 'Tenant not found'); tenantName.classList.add('error'); }
                            while(entitySelector.options.length > 1) entitySelector.remove(1);
                            if(btnLoad) btnLoad.disabled = true;
                        }
                    })
                    .catch(function(){
                        if(tenantName){ tenantName.textContent = t('tenant_not_found', 'Tenant not found'); tenantName.classList.add('error'); }
                    });
                });
            }

            // Auto-verify current tenant
            if(tenantInput && parseInt(tenantInput.value) > 0){
                setTimeout(function(){ if(btnVerify) btnVerify.click(); }, 200);
            }
        } else {
            // Non-super admin: load own entities
            loadEntitiesByTenant(CFG.tenantId || 0);
        }

        entitySelector.addEventListener('change', function(){
            if(btnLoad) btnLoad.disabled = !entitySelector.value;
        });

        if(btnLoad){
            btnLoad.addEventListener('click', function(){
                var val = entitySelector.value;
                if(!val) return;
                entityId = parseInt(val);
                document.querySelectorAll('input[name="entity_id"]').forEach(function(inp){
                    inp.value = entityId;
                });
                var tabs = document.querySelector('.content-tabs');
                if(tabs) tabs.style.display = 'block';
                loadPayments();
                loadBanks();
            });
        }
    }

    // Load entities by tenant ID into the selector
    function loadEntitiesByTenant(tenantId){
        var entitySelector = document.getElementById('entitySelector');
        var btnLoad = document.getElementById('btnLoadEntityPayments');
        if(!entitySelector) return;

        while(entitySelector.options.length > 1) entitySelector.remove(1);

        var url = API_BASE + '/entities?limit=200';
        if(tenantId && tenantId > 0) url += '&tenant_id=' + tenantId;

        fetch(url)
        .then(function(r){ return r.json(); })
        .then(function(d){
            if(d.success && d.data){
                var items = d.data.items || (Array.isArray(d.data) ? d.data : []);
                var defaultId = null;
                items.forEach(function(ent){
                    var opt = document.createElement('option');
                    opt.value = ent.id;
                    opt.textContent = ent.store_name || ent.name || ('Entity #' + ent.id);
                    if(ent.is_main == 1 && !defaultId) defaultId = ent.id;
                    entitySelector.appendChild(opt);
                });
                if(!defaultId && items.length > 0) defaultId = items[0].id;
                if(defaultId){
                    entitySelector.value = defaultId;
                    if(btnLoad) btnLoad.disabled = false;
                }
                if(defaultId && btnLoad) btnLoad.click();
            }
        })
        .catch(function(err){ console.error('Failed to load entities:', err); });
    }

    // Load Payment Methods table
    function loadPayments(){
        if(!entityId && !IS_SUPER) return;
        var url = API_BASE + '/entity_payment_methods';
        if(entityId) url += '?entity_id=' + entityId;
        fetch(url)
        .then(function(r){ return r.json(); })
        .then(function(d){
            var tbody = document.getElementById('paymentMethodsBody');
            if(!tbody) return;
            tbody.innerHTML = '';
            if(d.success && d.data){
                var items = Array.isArray(d.data) ? d.data : (d.data.items || []);
                if(items.length === 0){
                    tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;">' + t('no_records', 'No records found') + '</td></tr>';
                    return;
                }
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

    // Load Bank Accounts table
    function loadBanks(){
        if(!entityId && !IS_SUPER) return;
        var url = API_BASE + '/entity_bank_accounts';
        if(entityId) url += '?entity_id=' + entityId;
        fetch(url)
        .then(function(r){ return r.json(); })
        .then(function(d){
            var tbody = document.getElementById('bankAccountsBody');
            if(!tbody) return;
            tbody.innerHTML = '';
            if(d.success && d.data){
                var items = Array.isArray(d.data) ? d.data : (d.data.items || []);
                if(items.length === 0){
                    tbody.innerHTML = '<tr><td colspan="9" style="text-align:center;">' + t('no_records', 'No records found') + '</td></tr>';
                    return;
                }
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
