// /admin/assets/js/pages/entities_payment.js

document.addEventListener('DOMContentLoaded', function() {

    const entitySelector = document.getElementById('entitySelector');
    const btnLoad = document.getElementById('btnLoadEntityPayments');
    const entityId = window.entityId || 0;
    const csrfToken = document.querySelector('input[name="csrf_token"]')?.value || '';

    // ===========================
    // Utility functions
    // ===========================
    function openModal(id){ document.getElementById(id).style.display = 'block'; }
    function closeModal(id){ document.getElementById(id).style.display = 'none'; }
    function showAlert(msg){ alert(msg); }

    // ===========================
    // Entity selection logic
    // ===========================
    if(entitySelector){
        fetch('/api/entities')
            .then(r => r.json())
            .then(d => {
                if(d.success && d.data){
                    var items = Array.isArray(d.data) ? d.data : (d.data.items || []);
                    items.forEach(ent => {
                        const opt = document.createElement('option');
                        opt.value = ent.id;
                        opt.textContent = ent.store_name || ent.name;
                        entitySelector.appendChild(opt);
                    });
                    entitySelector.addEventListener('change', () => {
                        if(btnLoad) btnLoad.disabled = !entitySelector.value;
                    });
                }
            });
    }

    btnLoad?.addEventListener('click', () => {
        if(!entitySelector.value){
            showAlert('Please select an entity first.');
            return;
        }
        loadPayments(entitySelector.value);
        loadBanks(entitySelector.value);
        var contentTabs = document.querySelector('.content-tabs');
        if(contentTabs) contentTabs.style.display = '';
    });

    // ===========================
    // Tab switching
    // ===========================
    document.querySelectorAll('[data-tab]').forEach(function(tabLink) {
        tabLink.addEventListener('click', function(e) {
            e.preventDefault();
            document.querySelectorAll('[data-tab]').forEach(function(t) { t.classList.remove('active'); });
            document.querySelectorAll('.tab-pane').forEach(function(p) { p.style.display = 'none'; });
            tabLink.classList.add('active');
            var target = document.getElementById('tab-' + tabLink.dataset.tab);
            if(target) target.style.display = '';
        });
    });

    // ===========================
    // Load Payments
    // ===========================
    function loadPayments(id){
        id = id || entityId;
        if(!id) return;
        fetch('/api/entity_payment_methods?entity_id=' + id)
            .then(r => r.json())
            .then(d => {
                const tbody = document.getElementById('paymentMethodsBody');
                tbody.innerHTML = '';
                if(d.success && d.data){
                    var items = Array.isArray(d.data) ? d.data : (d.data.items || []);
                    items.forEach(p => {
                        const tr = document.createElement('tr');
                        tr.innerHTML =
                            '<td>' + esc(p.id) + '</td>' +
                            '<td>' + esc(p.gateway_name) + '</td>' +
                            '<td>' + esc(p.account_email || '') + '</td>' +
                            '<td>' + esc(p.account_id || '') + '</td>' +
                            '<td>' + (p.is_active ? 'Yes' : 'No') + '</td>' +
                            '<td>' +
                                '<button class="btn btn-sm btn-info edit-btn" data-id="' + p.id + '" data-type="payment">Edit</button> ' +
                                '<button class="btn btn-sm btn-danger delete-btn" data-id="' + p.id + '" data-type="payment">Delete</button>' +
                            '</td>';
                        tbody.appendChild(tr);
                    });
                }
            });
    }

    // ===========================
    // Load Banks
    // ===========================
    function loadBanks(id){
        id = id || entityId;
        if(!id) return;
        fetch('/api/entity_bank_accounts?entity_id=' + id)
            .then(r => r.json())
            .then(d => {
                const tbody = document.getElementById('bankAccountsBody');
                tbody.innerHTML = '';
                if(d.success && d.data){
                    var items = Array.isArray(d.data) ? d.data : (d.data.items || []);
                    items.forEach(b => {
                        const tr = document.createElement('tr');
                        tr.innerHTML =
                            '<td>' + esc(b.id) + '</td>' +
                            '<td>' + esc(b.bank_name) + '</td>' +
                            '<td>' + esc(b.account_holder_name) + '</td>' +
                            '<td>' + esc(b.account_number) + '</td>' +
                            '<td>' + esc(b.iban || '') + '</td>' +
                            '<td>' + (b.is_primary ? 'Yes' : 'No') + '</td>' +
                            '<td>' + (b.is_verified ? 'Yes' : 'No') + '</td>' +
                            '<td>' +
                                '<button class="btn btn-sm btn-info edit-btn" data-id="' + b.id + '" data-type="bank">Edit</button> ' +
                                '<button class="btn btn-sm btn-danger delete-btn" data-id="' + b.id + '" data-type="bank">Delete</button>' +
                            '</td>';
                        tbody.appendChild(tr);
                    });
                }
            });
    }

    // ===========================
    // XSS escape helper
    // ===========================
    function esc(str) {
        if (str == null) return '';
        var d = document.createElement('div');
        d.appendChild(document.createTextNode(String(str)));
        return d.innerHTML;
    }

    // ===========================
    // Add buttons
    // ===========================
    document.getElementById('btnAddPaymentMethod')?.addEventListener('click', function() {
        document.getElementById('pmFormTitle').textContent = 'Add Payment Method';
        document.getElementById('pmId').value = '';
        document.getElementById('paymentMethodForm').reset();
        var pmEntityId = document.getElementById('pmEntityId');
        if(pmEntityId) pmEntityId.value = entityId || (entitySelector ? entitySelector.value : '');
        openModal('paymentMethodModal');
    });

    document.getElementById('btnAddBankAccount')?.addEventListener('click', function() {
        document.getElementById('baFormTitle').textContent = 'Add Bank Account';
        document.getElementById('baId').value = '';
        document.getElementById('bankAccountForm').reset();
        var baEntityId = document.getElementById('baEntityId');
        if(baEntityId) baEntityId.value = entityId || (entitySelector ? entitySelector.value : '');
        openModal('bankAccountModal');
    });

    // ===========================
    // Form Submissions
    // ===========================
    const paymentForm = document.getElementById('paymentMethodForm');
    const bankForm = document.getElementById('bankAccountForm');

    if(paymentForm){
        paymentForm.addEventListener('submit', function(e){
            e.preventDefault();
            const id = entityId || (entitySelector ? entitySelector.value : 0);
            if(!id){ showAlert('Please select an entity first.'); return; }

            // Set entity_id in the hidden field
            var pmEntityId = document.getElementById('pmEntityId');
            if(pmEntityId) pmEntityId.value = id;

            const formData = new FormData(this);
            const editId = document.getElementById('pmId').value;
            const method = editId ? 'PUT' : 'POST';

            fetch('/api/entity_payment_methods', {
                method: method,
                headers: {'X-CSRF-TOKEN': csrfToken},
                body: formData
            })
            .then(r => r.json())
            .then(d => {
                if(d.success){
                    closeModal('paymentMethodModal');
                    showAlert('Payment method saved successfully');
                    loadPayments(id);
                } else {
                    showAlert('Error: ' + (d.message || 'Unknown error'));
                }
            });
        });
    }

    if(bankForm){
        bankForm.addEventListener('submit', function(e){
            e.preventDefault();
            const id = entityId || (entitySelector ? entitySelector.value : 0);
            if(!id){ showAlert('Please select an entity first.'); return; }

            // Set entity_id in the hidden field
            var baEntityId = document.getElementById('baEntityId');
            if(baEntityId) baEntityId.value = id;

            const formData = new FormData(this);
            const editId = document.getElementById('baId').value;
            const method = editId ? 'PUT' : 'POST';

            fetch('/api/entity_bank_accounts', {
                method: method,
                headers: {'X-CSRF-TOKEN': csrfToken},
                body: formData
            })
            .then(r => r.json())
            .then(d => {
                if(d.success){
                    closeModal('bankAccountModal');
                    showAlert('Bank account saved successfully');
                    loadBanks(id);
                } else {
                    showAlert('Error: ' + (d.message || 'Unknown error'));
                }
            });
        });
    }

    // ===========================
    // Edit & Delete Buttons (delegated)
    // ===========================
    document.addEventListener('click', function(e){
        // Edit button
        if(e.target.classList.contains('edit-btn')){
            const type = e.target.dataset.type;
            const recId = e.target.dataset.id;
            const currentId = entityId || (entitySelector ? entitySelector.value : 0);

            if(type === 'payment'){
                // Fetch the record and populate form
                fetch('/api/entity_payment_methods?entity_id=' + currentId)
                    .then(r => r.json())
                    .then(d => {
                        if(d.success && d.data){
                            var items = Array.isArray(d.data) ? d.data : (d.data.items || []);
                            var rec = items.find(function(p){ return String(p.id) === String(recId); });
                            if(rec){
                                document.getElementById('pmId').value = rec.id;
                                document.getElementById('pmGateway').value = rec.gateway_name || '';
                                document.getElementById('pmEmail').value = rec.account_email || '';
                                document.getElementById('pmAccountId').value = rec.account_id || '';
                                document.getElementById('pmActive').value = rec.is_active ? '1' : '0';
                                var pmEntityId = document.getElementById('pmEntityId');
                                if(pmEntityId) pmEntityId.value = currentId;
                                document.getElementById('pmFormTitle').textContent = 'Edit Payment Method';
                                openModal('paymentMethodModal');
                            }
                        }
                    });
            } else if(type === 'bank'){
                fetch('/api/entity_bank_accounts?entity_id=' + currentId)
                    .then(r => r.json())
                    .then(d => {
                        if(d.success && d.data){
                            var items = Array.isArray(d.data) ? d.data : (d.data.items || []);
                            var rec = items.find(function(b){ return String(b.id) === String(recId); });
                            if(rec){
                                document.getElementById('baId').value = rec.id;
                                document.getElementById('baBankName').value = rec.bank_name || '';
                                document.getElementById('baHolderName').value = rec.account_holder_name || '';
                                document.getElementById('baAccountNumber').value = rec.account_number || '';
                                document.getElementById('baIban').value = rec.iban || '';
                                document.getElementById('baSwift').value = rec.swift_code || '';
                                document.getElementById('baPrimary').value = rec.is_primary ? '1' : '0';
                                var verifiedEl = document.getElementById('baVerified');
                                if(verifiedEl) verifiedEl.value = rec.is_verified ? '1' : '0';
                                var baEntityId = document.getElementById('baEntityId');
                                if(baEntityId) baEntityId.value = currentId;
                                document.getElementById('baFormTitle').textContent = 'Edit Bank Account';
                                openModal('bankAccountModal');
                            }
                        }
                    });
            }
        }

        // Delete button
        if(e.target.classList.contains('delete-btn')){
            const type = e.target.dataset.type;
            const recId = e.target.dataset.id;
            const currentId = entityId || (entitySelector ? entitySelector.value : 0);

            if(!confirm('Are you sure you want to delete this record?')) return;

            const endpoint = type === 'payment' ? '/api/entity_payment_methods' : '/api/entity_bank_accounts';
            fetch(endpoint + '?id=' + recId + '&entity_id=' + currentId, {
                method: 'DELETE',
                headers: {'X-CSRF-TOKEN': csrfToken}
            })
            .then(r => r.json())
            .then(d => {
                if(d.success){
                    showAlert('Deleted successfully');
                    if(type === 'payment') loadPayments(currentId);
                    else loadBanks(currentId);
                } else {
                    showAlert('Delete failed: ' + (d.message || 'Unknown error'));
                }
            });
        }
    });

    // ===========================
    // Load initial data if entityId exists
    // ===========================
    if(entityId){
        loadPayments(entityId);
        loadBanks(entityId);
        var contentTabs = document.querySelector('.content-tabs');
        if(contentTabs) contentTabs.style.display = '';
    }

});
