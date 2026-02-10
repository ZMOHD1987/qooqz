<?php
declare(strict_types=1);

/**
 * /admin/fragments/entities_Payment.php
 * Entity Payment Management - Production Ready
 */

$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
          strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
$isEmbedded = isset($_GET['embedded']) || isset($_POST['embedded']);
$isFragment = $isAjax || $isEmbedded;

// Load context
if ($isFragment) {
    require_once __DIR__ . '/../includes/admin_context.php';
} else {
    require_once __DIR__ . '/../includes/header.php';
}

// Auth check
if (!is_admin_logged_in()) {
    if ($isFragment) {
        http_response_code(401);
        echo json_encode(['error' => 'Not authenticated']);
        exit;
    } else {
        header('Location: /admin/login.php');
        exit;
    }
}

// User / Tenant context
$user     = admin_user();
$lang     = admin_lang();
$dir      = admin_dir();
$csrf     = admin_csrf();
$tenantId = admin_tenant_id();
$userId   = admin_user_id();

// Permissions
$canManagePayment = can('manage_entities') || can('manage_entity_payments') || is_super_admin();
$canView          = can_view_all('entities') || can_view_own('entities') || can_view_tenant('entities') || is_super_admin();

// Access check
if (!$canView) {
    http_response_code(403);
    die('Access denied');
}

// Entity ID (optional) – use GET, fallback to session entity
$entityId = isset($_GET['entity_id']) && (int)$_GET['entity_id'] > 0
    ? (int)$_GET['entity_id']
    : (isset($_SESSION['entity_id']) && (int)$_SESSION['entity_id'] > 0
        ? (int)$_SESSION['entity_id']
        : 0);

// API base
$apiBase = '/api';
?>

<link rel="stylesheet" href="/admin/assets/css/pages/entities_payment.css?v=<?= time() ?>">

<div class="page-container" id="entitiesPaymentPageContainer" dir="<?= htmlspecialchars($dir) ?>">

    <!-- Page Header -->
    <div class="page-header">
        <h1>Entity Payment Methods</h1>
        <p>Manage payment gateways and bank accounts</p>
        <div class="page-header-actions">
            <?php if ($canManagePayment): ?>
                <button id="btnAddPayment" class="btn btn-primary">Add Payment</button>
                <button id="btnAddBank" class="btn btn-primary">Add Bank</button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Entity Selector -->
    <?php if (!$entityId): ?>
    <div class="card">
        <div class="card-body">
            <select id="entitySelector" class="form-control">
                <option value="">Select Entity...</option>
            </select>
            <button id="btnLoadEntityPayments" class="btn btn-primary" disabled>Load</button>
        </div>
    </div>
    <?php endif; ?>

    <!-- Tabs -->
    <div class="content-tabs" <?= $entityId ? '' : 'style="display:none;"' ?>>
        <button class="tab-btn active" data-tab="payment_methods">Payment Methods</button>
        <button class="tab-btn" data-tab="bank_accounts">Bank Accounts</button>

        <!-- Payment Methods -->
        <div class="tab-content active" id="tab-payment_methods">
            <table class="data-table" id="paymentMethodsTable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Gateway</th>
                        <th>Account Email</th>
                        <th>Account ID</th>
                        <th>Active</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="paymentMethodsBody"></tbody>
            </table>
        </div>

        <!-- Bank Accounts -->
        <div class="tab-content" id="tab-bank_accounts">
            <table class="data-table" id="bankAccountsTable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Bank Name</th>
                        <th>Account Holder</th>
                        <th>Account Number</th>
                        <th>IBAN</th>
                        <th>SWIFT Code</th>
                        <th>Primary</th>
                        <th>Verified</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="bankAccountsBody"></tbody>
            </table>
        </div>
    </div>

    <!-- Payment Method Modal -->
    <div id="paymentMethodModal" class="modal" style="display:none;">
        <div class="modal-content">
            <h3 id="paymentModalTitle">Add Payment Method</h3>
            <form id="paymentMethodForm">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                <input type="hidden" name="entity_id" value="<?= $entityId ?>">
                <input type="hidden" name="id" id="pmEditId" value="">
                <div class="form-group">
                    <label>Gateway *</label>
                    <select name="gateway_name" id="pmGateway" class="form-control" required>
                        <option value="">Select</option>
                        <option value="stripe">Stripe</option>
                        <option value="paypal">PayPal</option>
                        <option value="moyasar">Moyasar</option>
                        <option value="tap">Tap</option>
                        <option value="paytabs">PayTabs</option>
                        <option value="credit_card">Credit Card</option>
                        <option value="bank_transfer">Bank Transfer</option>
                        <option value="cash">Cash</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Account Email</label>
                    <input type="email" name="account_email" id="pmEmail" class="form-control">
                </div>
                <div class="form-group">
                    <label>Account ID</label>
                    <input type="text" name="account_id" id="pmAccountId" class="form-control">
                </div>
                <div class="form-group">
                    <label>Active</label>
                    <select name="is_active" id="pmActive" class="form-control">
                        <option value="1">Yes</option>
                        <option value="0">No</option>
                    </select>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Save</button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal('paymentMethodModal')">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Bank Account Modal -->
    <div id="bankAccountModal" class="modal" style="display:none;">
        <div class="modal-content">
            <h3 id="bankModalTitle">Add Bank Account</h3>
            <form id="bankAccountForm">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                <input type="hidden" name="entity_id" value="<?= $entityId ?>">
                <input type="hidden" name="id" id="baEditId" value="">
                <div class="form-group">
                    <label>Bank Name *</label>
                    <input type="text" name="bank_name" id="baBankName" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Account Holder *</label>
                    <input type="text" name="account_holder_name" id="baHolderName" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Account Number *</label>
                    <input type="text" name="account_number" id="baAccountNumber" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>IBAN</label>
                    <input type="text" name="iban" id="baIban" class="form-control">
                </div>
                <div class="form-group">
                    <label>SWIFT Code</label>
                    <input type="text" name="swift_code" id="baSwift" class="form-control">
                </div>
                <div class="form-group">
                    <label>Primary</label>
                    <select name="is_primary" id="baPrimary" class="form-control">
                        <option value="0">No</option>
                        <option value="1">Yes</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Verified</label>
                    <select name="is_verified" id="baVerified" class="form-control">
                        <option value="0">No</option>
                        <option value="1">Yes</option>
                    </select>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Save</button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal('bankAccountModal')">Cancel</button>
                </div>
            </form>
        </div>
    </div>

</div>

<script>
(function(){
    var API_BASE = '<?= $apiBase ?>';
    var CSRF = <?= json_encode($csrf) ?>;

    // Modal helpers
    window.openModal = function(id){ document.getElementById(id).style.display='block'; };
    window.closeModal = function(id){ document.getElementById(id).style.display='none'; };

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

    // Add Payment / Bank buttons
    document.getElementById('btnAddPayment')?.addEventListener('click', function(){
        document.getElementById('paymentModalTitle').textContent = 'Add Payment Method';
        document.getElementById('paymentMethodForm').reset();
        document.getElementById('pmEditId').value = '';
        openModal('paymentMethodModal');
    });
    document.getElementById('btnAddBank')?.addEventListener('click', function(){
        document.getElementById('bankModalTitle').textContent = 'Add Bank Account';
        document.getElementById('bankAccountForm').reset();
        document.getElementById('baEditId').value = '';
        openModal('bankAccountModal');
    });

    // Entity selector logic
    var entitySelector = document.getElementById('entitySelector');
    var btnLoad = document.getElementById('btnLoadEntityPayments');
    var entityId = <?= $entityId ?>;

    if(entitySelector){
        fetch(API_BASE + '/entities')
        .then(function(r){ return r.json(); })
        .then(function(d){
            if(d.success && d.data && d.data.items){
                var defaultEntityId = null;
                d.data.items.forEach(function(ent){
                    var opt = document.createElement('option');
                    opt.value = ent.id;
                    opt.textContent = ent.store_name || ent.name || ('Entity #' + ent.id);
                    if(ent.is_main == 1 && !defaultEntityId) defaultEntityId = ent.id;
                    entitySelector.appendChild(opt);
                });

                if(!defaultEntityId && d.data.items.length > 0) defaultEntityId = d.data.items[0].id;

                if(defaultEntityId){
                    entitySelector.value = defaultEntityId;
                    if(btnLoad) btnLoad.disabled = false;
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
                        document.querySelector('.content-tabs').style.display = 'block';
                        loadPayments();
                        loadBanks();
                    });
                }

                if(defaultEntityId && btnLoad){
                    btnLoad.click();
                }
            }
        });
    }

    // Submit Payment Method
    document.getElementById('paymentMethodForm')?.addEventListener('submit', function(e){
        e.preventDefault();
        if(!entityId){ alert('Please select an entity first.'); return; }
        var editId = document.getElementById('pmEditId').value;
        var method = editId ? 'PUT' : 'POST';
        fetch(API_BASE + '/entity_payment_methods', {
            method: method,
            headers: {'X-CSRF-TOKEN': CSRF},
            body: new FormData(this)
        }).then(function(r){ return r.json(); }).then(function(d){
            if(d.success){
                closeModal('paymentMethodModal');
                document.getElementById('paymentMethodForm').reset();
                document.getElementById('pmEditId').value = '';
                alert('Saved');
                loadPayments();
            } else {
                alert('Error: ' + (d.message || 'Unknown error'));
            }
        });
    });

    // Submit Bank Account
    document.getElementById('bankAccountForm')?.addEventListener('submit', function(e){
        e.preventDefault();
        if(!entityId){ alert('Please select an entity first.'); return; }
        var editId = document.getElementById('baEditId').value;
        var method = editId ? 'PUT' : 'POST';
        fetch(API_BASE + '/entity_bank_accounts', {
            method: method,
            headers: {'X-CSRF-TOKEN': CSRF},
            body: new FormData(this)
        }).then(function(r){ return r.json(); }).then(function(d){
            if(d.success){
                closeModal('bankAccountModal');
                document.getElementById('bankAccountForm').reset();
                document.getElementById('baEditId').value = '';
                alert('Saved');
                loadBanks();
            } else {
                alert('Error: ' + (d.message || 'Unknown error'));
            }
        });
    });

    // XSS escape helper
    function esc(str){
        if(str == null) return '';
        var d = document.createElement('div');
        d.appendChild(document.createTextNode(String(str)));
        return d.innerHTML;
    }

    // Load Payment Methods
    function loadPayments(){
        if(!entityId) return;
        fetch(API_BASE + '/entity_payment_methods?entity_id=' + entityId)
        .then(function(r){ return r.json(); })
        .then(function(d){
            var tbody = document.getElementById('paymentMethodsBody');
            tbody.innerHTML = '';
            if(d.success && d.data){
                var items = Array.isArray(d.data) ? d.data : (d.data.items || []);
                items.forEach(function(p){
                    var tr = document.createElement('tr');
                    tr.innerHTML =
                        '<td>' + esc(p.id) + '</td>' +
                        '<td>' + esc(p.gateway_name) + '</td>' +
                        '<td>' + esc(p.account_email || '') + '</td>' +
                        '<td>' + esc(p.account_id || '') + '</td>' +
                        '<td>' + (p.is_active ? 'Yes' : 'No') + '</td>' +
                        '<td>' +
                            '<button class="btn btn-sm btn-info edit-payment-btn" data-id="' + esc(p.id) + '">Edit</button> ' +
                            '<button class="btn btn-sm btn-danger delete-payment-btn" data-id="' + esc(p.id) + '">Delete</button>' +
                        '</td>';
                    tbody.appendChild(tr);
                });
            }
        });
    }

    // Load Bank Accounts
    function loadBanks(){
        if(!entityId) return;
        fetch(API_BASE + '/entity_bank_accounts?entity_id=' + entityId)
        .then(function(r){ return r.json(); })
        .then(function(d){
            var tbody = document.getElementById('bankAccountsBody');
            tbody.innerHTML = '';
            if(d.success && d.data){
                var items = Array.isArray(d.data) ? d.data : (d.data.items || []);
                items.forEach(function(b){
                    var tr = document.createElement('tr');
                    tr.innerHTML =
                        '<td>' + esc(b.id) + '</td>' +
                        '<td>' + esc(b.bank_name) + '</td>' +
                        '<td>' + esc(b.account_holder_name) + '</td>' +
                        '<td>' + esc(b.account_number) + '</td>' +
                        '<td>' + esc(b.iban || '') + '</td>' +
                        '<td>' + esc(b.swift_code || '') + '</td>' +
                        '<td>' + (b.is_primary ? 'Yes' : 'No') + '</td>' +
                        '<td>' + (b.is_verified ? 'Yes' : 'No') + '</td>' +
                        '<td>' +
                            '<button class="btn btn-sm btn-info edit-bank-btn" data-id="' + esc(b.id) + '">Edit</button> ' +
                            '<button class="btn btn-sm btn-danger delete-bank-btn" data-id="' + esc(b.id) + '">Delete</button>' +
                        '</td>';
                    tbody.appendChild(tr);
                });
            }
        });
    }

    // Edit Payment - delegated click
    document.addEventListener('click', function(e){
        var editPm = e.target.closest('.edit-payment-btn');
        if(editPm){
            var recId = editPm.dataset.id;
            fetch(API_BASE + '/entity_payment_methods?entity_id=' + entityId + '&id=' + recId)
            .then(function(r){ return r.json(); })
            .then(function(d){
                if(d.success && d.data){
                    var rec = d.data;
                    document.getElementById('pmEditId').value = rec.id;
                    document.getElementById('pmGateway').value = rec.gateway_name || '';
                    document.getElementById('pmEmail').value = rec.account_email || '';
                    document.getElementById('pmAccountId').value = rec.account_id || '';
                    document.getElementById('pmActive').value = rec.is_active ? '1' : '0';
                    document.getElementById('paymentModalTitle').textContent = 'Edit Payment Method';
                    openModal('paymentMethodModal');
                }
            });
            return;
        }

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
                    document.getElementById('bankModalTitle').textContent = 'Edit Bank Account';
                    openModal('bankAccountModal');
                }
            });
            return;
        }

        // Delete Payment
        var delPm = e.target.closest('.delete-payment-btn');
        if(delPm){
            if(!confirm('Are you sure you want to delete this payment method?')) return;
            var delId = delPm.dataset.id;
            fetch(API_BASE + '/entity_payment_methods?id=' + delId + '&entity_id=' + entityId, {
                method: 'DELETE',
                headers: {'X-CSRF-TOKEN': CSRF}
            }).then(function(r){ return r.json(); }).then(function(d){
                if(d.success){ alert('Deleted'); loadPayments(); }
                else alert('Error: ' + (d.message || 'Delete failed'));
            });
            return;
        }

        // Delete Bank
        var delBa = e.target.closest('.delete-bank-btn');
        if(delBa){
            if(!confirm('Are you sure you want to delete this bank account?')) return;
            var delId2 = delBa.dataset.id;
            fetch(API_BASE + '/entity_bank_accounts?id=' + delId2 + '&entity_id=' + entityId, {
                method: 'DELETE',
                headers: {'X-CSRF-TOKEN': CSRF}
            }).then(function(r){ return r.json(); }).then(function(d){
                if(d.success){ alert('Deleted'); loadBanks(); }
                else alert('Error: ' + (d.message || 'Delete failed'));
            });
            return;
        }
    });

    // Load initial data if entityId exists
    if(entityId){
        loadPayments();
        loadBanks();
    }
})();
</script>

<?php if (!$isFragment) require_once __DIR__ . '/../includes/footer.php'; ?>
