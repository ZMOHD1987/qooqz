// /admin/assets/js/pages/entities_payment.js

document.addEventListener('DOMContentLoaded', function() {

    const entitySelector = document.getElementById('entitySelector');
    const btnLoad = document.getElementById('btnLoadEntityPayments');
    const entityId = window.entityId || 0;
    const csrfToken = document.querySelector('input[name="csrf_token"]').value;

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
                    d.data.forEach(ent => {
                        const opt = document.createElement('option');
                        opt.value = ent.id;
                        opt.textContent = ent.store_name || ent.name;
                        entitySelector.appendChild(opt);
                    });
                    entitySelector.addEventListener('change', () => {
                        btnLoad.disabled = !entitySelector.value;
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
        document.querySelector('.content-tabs').style.display = '';
    });

    // ===========================
    // Load Payments
    // ===========================
    function loadPayments(id = entityId){
        if(!id) return;
        fetch(`/api/entity_payment_methods?entity_id=${id}`)
            .then(r => r.json())
            .then(d => {
                const tbody = document.getElementById('paymentMethodsBody');
                tbody.innerHTML = '';
                if(d.success && d.data){
                    d.data.forEach(p => {
                        const tr = document.createElement('tr');
                        tr.innerHTML = `
                            <td>${p.id}</td>
                            <td>${p.gateway_name}</td>
                            <td>${p.account_email || ''}</td>
                            <td>${p.account_id || ''}</td>
                            <td>${p.is_active ? 'Yes' : 'No'}</td>
                            <td><button class="edit-btn" data-id="${p.id}" data-type="payment">Edit</button></td>
                        `;
                        tbody.appendChild(tr);
                    });
                }
            });
    }

    // ===========================
    // Load Banks
    // ===========================
    function loadBanks(id = entityId){
        if(!id) return;
        fetch(`/api/entity_bank_accounts?entity_id=${id}`)
            .then(r => r.json())
            .then(d => {
                const tbody = document.getElementById('bankAccountsBody');
                tbody.innerHTML = '';
                if(d.success && d.data){
                    d.data.forEach(b => {
                        const tr = document.createElement('tr');
                        tr.innerHTML = `
                            <td>${b.id}</td>
                            <td>${b.bank_name}</td>
                            <td>${b.account_holder_name}</td>
                            <td>${b.account_number}</td>
                            <td>${b.iban || ''}</td>
                            <td>${b.is_primary ? 'Yes' : 'No'}</td>
                            <td>${b.is_verified ? 'Yes' : 'No'}</td>
                            <td><button class="edit-btn" data-id="${b.id}" data-type="bank">Edit</button></td>
                        `;
                        tbody.appendChild(tr);
                    });
                }
            });
    }

    // ===========================
    // Form Submissions
    // ===========================
    const paymentForm = document.getElementById('paymentMethodForm');
    const bankForm = document.getElementById('bankAccountForm');

    if(paymentForm){
        paymentForm.addEventListener('submit', function(e){
            e.preventDefault();
            const id = entityId || entitySelector?.value;
            if(!id){ showAlert('Please select an entity first.'); return; }

            const formData = new FormData(this);
            fetch('/api/entity_payment_methods', {
                method: 'POST',
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
                    showAlert('Error: ' + d.message);
                }
            });
        });
    }

    if(bankForm){
        bankForm.addEventListener('submit', function(e){
            e.preventDefault();
            const id = entityId || entitySelector?.value;
            if(!id){ showAlert('Please select an entity first.'); return; }

            const formData = new FormData(this);
            fetch('/api/entity_bank_accounts', {
                method: 'POST',
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
                    showAlert('Error: ' + d.message);
                }
            });
        });
    }

    // ===========================
    // Edit Buttons
    // ===========================
    document.addEventListener('click', function(e){
        if(e.target.classList.contains('edit-btn')){
            const type = e.target.dataset.type;
            const id = e.target.dataset.id;
            showAlert(`Edit ${type} ID: ${id}`); // Replace with actual edit logic
        }
    });

    // ===========================
    // Load initial data if entityId exists
    // ===========================
    if(entityId){
        loadPayments(entityId);
        loadBanks(entityId);
        document.querySelector('.content-tabs').style.display = '';
    }

});
