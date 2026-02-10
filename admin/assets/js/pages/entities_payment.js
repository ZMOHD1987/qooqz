// /admin/assets/js/pages/entities_payment.js

document.addEventListener('DOMContentLoaded', function () {
    const CFG = window.ENTITIES_PAYMENT_CONFIG || {};
    const API = CFG.apiBase || '/api';
    const TXT = CFG.texts || {};
    let currentEntityId = CFG.entityId || 0;

    // DOM refs
    const entitySelector = document.getElementById('entitySelector');
    const entitySelectorWrap = document.getElementById('entitySelectorWrap');
    const pmBody = document.getElementById('paymentMethodsBody');
    const pmLoading = document.getElementById('pmLoading');
    const pmEmpty = document.getElementById('pmEmpty');
    const pmTableWrap = document.getElementById('pmTableWrap');
    const baBody = document.getElementById('bankAccountsBody');
    const baLoading = document.getElementById('baLoading');
    const baEmpty = document.getElementById('baEmpty');
    const baTableWrap = document.getElementById('baTableWrap');
    const pmModal = document.getElementById('paymentMethodModal');
    const baModal = document.getElementById('bankAccountModal');
    const pmForm = document.getElementById('paymentMethodForm');
    const baForm = document.getElementById('bankAccountForm');
    const notification = document.getElementById('notification');

    // ===========================
    // Helpers
    // ===========================
    function esc(str) {
        if (str == null) return '';
        var d = document.createElement('div');
        d.appendChild(document.createTextNode(str));
        return d.innerHTML;
    }

    function formatDate(val) {
        if (!val) return '-';
        var d = new Date(val);
        if (isNaN(d.getTime())) return esc(val);
        return d.toLocaleDateString(CFG.lang || undefined, {
            year: 'numeric', month: 'short', day: 'numeric',
            hour: '2-digit', minute: '2-digit'
        });
    }

    function badgeYesNo(flag) {
        return flag
            ? '<span class="badge-yes">' + (TXT.yes || 'Yes') + '</span>'
            : '<span class="badge-no">' + (TXT.no || 'No') + '</span>';
    }

    function notify(msg, type) {
        if (!notification) return;
        notification.textContent = msg;
        notification.className = type + ' show';
        setTimeout(function () { notification.className = ''; }, 3000);
    }

    function openModal(el) { if (el) el.classList.add('open'); }
    function closeModal(el) { if (el) el.classList.remove('open'); }

    function apiRequest(url, opts) {
        opts = opts || {};
        var headers = opts.headers || {};
        headers['X-CSRF-TOKEN'] = CFG.csrfToken;
        if (opts.body && typeof opts.body === 'object') {
            headers['Content-Type'] = 'application/json';
            opts.body = JSON.stringify(opts.body);
        }
        opts.headers = headers;
        return fetch(url, opts).then(function (r) { return r.json(); });
    }

    // ===========================
    // Tabs
    // ===========================
    document.querySelectorAll('.pay-tab-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.querySelectorAll('.pay-tab-btn').forEach(function (b) { b.classList.remove('active'); });
            document.querySelectorAll('.pay-tab-content').forEach(function (c) { c.classList.remove('active'); });
            btn.classList.add('active');
            var target = document.getElementById('tab-' + btn.dataset.tab);
            if (target) target.classList.add('active');
        });
    });

    // ===========================
    // Entity selector
    // ===========================
    if (!currentEntityId && entitySelector) {
        if (entitySelectorWrap) entitySelectorWrap.style.display = '';
        fetch(API + '/entities')
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (!d.success || !d.data) return;
                var items = Array.isArray(d.data) ? d.data : (d.data.items || []);
                items.forEach(function (ent) {
                    var opt = document.createElement('option');
                    opt.value = ent.id;
                    opt.textContent = ent.store_name || ent.name;
                    if (ent.is_main) opt.setAttribute('data-main', '1');
                    entitySelector.appendChild(opt);
                });
                // Auto-select main entity
                var mainOpt = entitySelector.querySelector('option[data-main="1"]');
                if (mainOpt) {
                    entitySelector.value = mainOpt.value;
                    currentEntityId = parseInt(mainOpt.value, 10);
                    loadData();
                }
            });
        entitySelector.addEventListener('change', function () {
            currentEntityId = parseInt(entitySelector.value, 10) || 0;
            loadData();
        });
    } else {
        if (entitySelectorWrap) entitySelectorWrap.style.display = 'none';
    }

    // ===========================
    // Data loading
    // ===========================
    function loadData() {
        loadPayments();
        loadBanks();
    }

    function loadPayments() {
        if (!currentEntityId) return;
        if (pmLoading) pmLoading.style.display = '';
        if (pmTableWrap) pmTableWrap.style.display = 'none';
        if (pmEmpty) pmEmpty.style.display = 'none';

        apiRequest(API + '/entity_payment_methods?entity_id=' + currentEntityId)
            .then(function (d) {
                if (pmLoading) pmLoading.style.display = 'none';
                if (!d.success) return;
                var items = d.data || [];
                if (items.length === 0) {
                    if (pmEmpty) pmEmpty.style.display = '';
                    if (pmTableWrap) pmTableWrap.style.display = 'none';
                    return;
                }
                if (pmTableWrap) pmTableWrap.style.display = '';
                pmBody.innerHTML = '';
                items.forEach(function (p) {
                    var tr = document.createElement('tr');
                    var editData = encodeURIComponent(JSON.stringify({
                        gateway_name: p.gateway_name || '',
                        account_email: p.account_email || '',
                        account_id: p.account_id || '',
                        is_active: p.is_active ? 1 : 0
                    }));
                    tr.innerHTML =
                        '<td>' + esc(p.id) + '</td>' +
                        '<td>' + esc(p.gateway_name) + '</td>' +
                        '<td>' + (p.account_email ? esc(p.account_email) : '-') + '</td>' +
                        '<td>' + (p.account_id ? esc(p.account_id) : '-') + '</td>' +
                        '<td>' + badgeYesNo(p.is_active) + '</td>' +
                        '<td>' + formatDate(p.created_at) + '</td>' +
                        '<td><div class="actions-cell">' +
                            (CFG.canManage
                                ? '<button type="button" class="btn-sm pm-edit" data-id="' + p.id + '" data-row="' + editData + '">Edit</button>' +
                                  '<button type="button" class="btn-sm pm-delete" data-id="' + p.id + '">Delete</button>'
                                : '') +
                        '</div></td>';
                    pmBody.appendChild(tr);
                });
            });
    }

    function loadBanks() {
        if (!currentEntityId) return;
        if (baLoading) baLoading.style.display = '';
        if (baTableWrap) baTableWrap.style.display = 'none';
        if (baEmpty) baEmpty.style.display = 'none';

        apiRequest(API + '/entity_bank_accounts?entity_id=' + currentEntityId)
            .then(function (d) {
                if (baLoading) baLoading.style.display = 'none';
                if (!d.success) return;
                var items = d.data || [];
                if (items.length === 0) {
                    if (baEmpty) baEmpty.style.display = '';
                    if (baTableWrap) baTableWrap.style.display = 'none';
                    return;
                }
                if (baTableWrap) baTableWrap.style.display = '';
                baBody.innerHTML = '';
                items.forEach(function (b) {
                    var editData = encodeURIComponent(JSON.stringify({
                        bank_name: b.bank_name || '',
                        account_holder_name: b.account_holder_name || '',
                        account_number: b.account_number || '',
                        iban: b.iban || '',
                        swift_code: b.swift_code || '',
                        is_primary: b.is_primary ? 1 : 0,
                        is_verified: b.is_verified ? 1 : 0
                    }));
                    var tr = document.createElement('tr');
                    tr.innerHTML =
                        '<td>' + esc(b.id) + '</td>' +
                        '<td>' + esc(b.bank_name) + '</td>' +
                        '<td>' + esc(b.account_holder_name) + '</td>' +
                        '<td>' + esc(b.account_number) + '</td>' +
                        '<td>' + (b.iban ? esc(b.iban) : '-') + '</td>' +
                        '<td>' + (b.swift_code ? esc(b.swift_code) : '-') + '</td>' +
                        '<td>' + badgeYesNo(b.is_primary) + '</td>' +
                        '<td>' + badgeYesNo(b.is_verified) + '</td>' +
                        '<td>' + formatDate(b.created_at) + '</td>' +
                        '<td><div class="actions-cell">' +
                            (CFG.canManage
                                ? '<button type="button" class="btn-sm ba-edit" data-id="' + b.id + '" data-row="' + editData + '">Edit</button>' +
                                  '<button type="button" class="btn-sm ba-delete" data-id="' + b.id + '">Delete</button>'
                                : '') +
                        '</div></td>';
                    baBody.appendChild(tr);
                });
            });
    }

    // ===========================
    // Payment Method modal
    // ===========================
    function resetPmForm() {
        pmForm.reset();
        document.getElementById('pmId').value = '';
    }

    document.getElementById('btnAddPaymentMethod')?.addEventListener('click', function () {
        resetPmForm();
        document.getElementById('pmFormTitle').textContent = TXT.addPaymentMethod || 'Add Payment Method';
        openModal(pmModal);
    });

    document.getElementById('pmCancel')?.addEventListener('click', function () {
        closeModal(pmModal);
        resetPmForm();
    });

    pmModal?.addEventListener('click', function (e) {
        if (e.target === pmModal) { closeModal(pmModal); resetPmForm(); }
    });

    // Edit / Delete payment method (delegated)
    pmBody?.addEventListener('click', function (e) {
        var btn = e.target.closest('.pm-edit');
        if (btn) {
            var data = JSON.parse(decodeURIComponent(btn.dataset.row));
            document.getElementById('pmId').value = btn.dataset.id;
            document.getElementById('pmGateway').value = data.gateway_name;
            document.getElementById('pmEmail').value = data.account_email;
            document.getElementById('pmAccountId').value = data.account_id;
            document.getElementById('pmActive').value = String(data.is_active);
            document.getElementById('pmFormTitle').textContent = TXT.editPaymentMethod || 'Edit Payment Method';
            openModal(pmModal);
            return;
        }

        var delBtn = e.target.closest('.pm-delete');
        if (delBtn) {
            if (!confirm(TXT.confirmDelete || 'Are you sure you want to delete this item?')) return;
            apiRequest(API + '/entity_payment_methods', {
                method: 'DELETE',
                body: { id: parseInt(delBtn.dataset.id, 10), entity_id: currentEntityId }
            }).then(function (d) {
                if (d.success) {
                    notify(TXT.deleted || 'Deleted', 'success');
                    loadPayments();
                } else {
                    notify(TXT.deleteFailed || 'Delete failed', 'error');
                }
            });
        }
    });

    pmForm?.addEventListener('submit', function (e) {
        e.preventDefault();
        var pmIdVal = document.getElementById('pmId').value;
        var isEdit = !!pmIdVal;
        var payload = {
            entity_id: currentEntityId,
            gateway_name: document.getElementById('pmGateway').value,
            account_email: document.getElementById('pmEmail').value,
            account_id: document.getElementById('pmAccountId').value,
            is_active: parseInt(document.getElementById('pmActive').value, 10) || 0
        };
        if (isEdit) payload.id = parseInt(pmIdVal, 10);

        apiRequest(API + '/entity_payment_methods', {
            method: isEdit ? 'PUT' : 'POST',
            body: payload
        }).then(function (d) {
            if (d.success) {
                closeModal(pmModal);
                resetPmForm();
                notify(TXT.saved || 'Saved', 'success');
                loadPayments();
            } else {
                notify(TXT.saveFailed || 'Save failed', 'error');
            }
        });
    });

    // ===========================
    // Bank Account modal
    // ===========================
    function resetBaForm() {
        baForm.reset();
        document.getElementById('baId').value = '';
    }

    document.getElementById('btnAddBankAccount')?.addEventListener('click', function () {
        resetBaForm();
        document.getElementById('baFormTitle').textContent = TXT.addBankAccount || 'Add Bank Account';
        openModal(baModal);
    });

    document.getElementById('baCancel')?.addEventListener('click', function () {
        closeModal(baModal);
        resetBaForm();
    });

    baModal?.addEventListener('click', function (e) {
        if (e.target === baModal) { closeModal(baModal); resetBaForm(); }
    });

    // Edit / Delete bank account (delegated)
    baBody?.addEventListener('click', function (e) {
        var btn = e.target.closest('.ba-edit');
        if (btn) {
            var data = JSON.parse(decodeURIComponent(btn.dataset.row));
            document.getElementById('baId').value = btn.dataset.id;
            document.getElementById('baBankName').value = data.bank_name;
            document.getElementById('baHolderName').value = data.account_holder_name;
            document.getElementById('baAccountNumber').value = data.account_number;
            document.getElementById('baIban').value = data.iban;
            document.getElementById('baSwift').value = data.swift_code;
            document.getElementById('baPrimary').value = String(data.is_primary);
            var verifiedEl = document.getElementById('baVerified');
            if (verifiedEl) verifiedEl.value = String(data.is_verified);
            document.getElementById('baFormTitle').textContent = TXT.editBankAccount || 'Edit Bank Account';
            openModal(baModal);
            return;
        }

        var delBtn = e.target.closest('.ba-delete');
        if (delBtn) {
            if (!confirm(TXT.confirmDelete || 'Are you sure you want to delete this item?')) return;
            apiRequest(API + '/entity_bank_accounts', {
                method: 'DELETE',
                body: { id: parseInt(delBtn.dataset.id, 10), entity_id: currentEntityId }
            }).then(function (d) {
                if (d.success) {
                    notify(TXT.deleted || 'Deleted', 'success');
                    loadBanks();
                } else {
                    notify(TXT.deleteFailed || 'Delete failed', 'error');
                }
            });
        }
    });

    baForm?.addEventListener('submit', function (e) {
        e.preventDefault();
        var baIdVal = document.getElementById('baId').value;
        var isEdit = !!baIdVal;
        var payload = {
            entity_id: currentEntityId,
            bank_name: document.getElementById('baBankName').value,
            account_holder_name: document.getElementById('baHolderName').value,
            account_number: document.getElementById('baAccountNumber').value,
            iban: document.getElementById('baIban').value,
            swift_code: document.getElementById('baSwift').value,
            is_primary: parseInt(document.getElementById('baPrimary').value, 10) || 0,
            is_verified: parseInt((document.getElementById('baVerified') || {}).value || '0', 10) || 0
        };
        if (isEdit) payload.id = parseInt(baIdVal, 10);

        apiRequest(API + '/entity_bank_accounts', {
            method: isEdit ? 'PUT' : 'POST',
            body: payload
        }).then(function (d) {
            if (d.success) {
                closeModal(baModal);
                resetBaForm();
                notify(TXT.saved || 'Saved', 'success');
                loadBanks();
            } else {
                notify(TXT.saveFailed || 'Save failed', 'error');
            }
        });
    });

    // ===========================
    // Initial load
    // ===========================
    if (currentEntityId) {
        loadData();
    }
});
