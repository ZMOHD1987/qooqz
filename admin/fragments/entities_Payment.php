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
$canManagePayment = can('manage_entities') || can('manage_entity_payments');
$canView          = can_view_all('entities') || can_view_own('entities') || can_view_tenant('entities');

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
            <h3>Add Payment Method</h3>
            <form id="paymentMethodForm">
                <input type="hidden" name="csrf_token" value="<?= addslashes($csrf) ?>">
                <input type="hidden" name="entity_id" value="<?= $entityId ?>">
                <div>
                    <label>Gateway</label>
                    <select name="gateway_name" required>
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
                <div>
                    <label>Account Email</label>
                    <input type="email" name="account_email">
                </div>
                <div>
                    <label>Account ID</label>
                    <input type="text" name="account_id">
                </div>
                <div>
                    <label>Active</label>
                    <select name="is_active">
                        <option value="1">Yes</option>
                        <option value="0">No</option>
                    </select>
                </div>
                <button type="submit">Save</button>
                <button type="button" onclick="closeModal('paymentMethodModal')">Cancel</button>
            </form>
        </div>
    </div>

    <!-- Bank Account Modal -->
    <div id="bankAccountModal" class="modal" style="display:none;">
        <div class="modal-content">
            <h3>Add Bank Account</h3>
            <form id="bankAccountForm">
                <input type="hidden" name="csrf_token" value="<?= addslashes($csrf) ?>">
                <input type="hidden" name="entity_id" value="<?= $entityId ?>">
                <div>
                    <label>Bank Name</label>
                    <input type="text" name="bank_name" required>
                </div>
                <div>
                    <label>Account Holder</label>
                    <input type="text" name="account_holder_name" required>
                </div>
                <div>
                    <label>Account Number</label>
                    <input type="text" name="account_number" required>
                </div>
                <div>
                    <label>IBAN</label>
                    <input type="text" name="iban">
                </div>
                <div>
                    <label>Primary</label>
                    <select name="is_primary">
                        <option value="0">No</option>
                        <option value="1">Yes</option>
                    </select>
                </div>
                <div>
                    <label>Verified</label>
                    <select name="is_verified">
                        <option value="0">No</option>
                        <option value="1">Yes</option>
                    </select>
                </div>
                <button type="submit">Save</button>
                <button type="button" onclick="closeModal('bankAccountModal')">Cancel</button>
            </form>
        </div>
    </div>

</div>

<script>
function openModal(id){ document.getElementById(id).style.display='block'; }
function closeModal(id){ document.getElementById(id).style.display='none'; }

// Add Payment / Bank buttons
document.getElementById('btnAddPayment')?.addEventListener('click',()=>openModal('paymentMethodModal'));
document.getElementById('btnAddBank')?.addEventListener('click',()=>openModal('bankAccountModal'));

// Entity selector logic
const entitySelector = document.getElementById('entitySelector');
const btnLoad = document.getElementById('btnLoadEntityPayments');
let entityId = <?= $entityId ?>;

if(entitySelector){
    fetch('<?= $apiBase ?>/entities')
    .then(r=>r.json())
    .then(d=>{
        if(d.success && d.data && d.data.items){
            let defaultEntityId = null;
            d.data.items.forEach(ent=>{
                const opt=document.createElement('option');
                opt.value=ent.id; opt.textContent=ent.store_name;
                // Check if this is the main entity, or just use the first one if we haven't found a main one yet
                // Note: The API returns is_main as 0 or 1.
                if(ent.is_main == 1 && !defaultEntityId) defaultEntityId = ent.id;
                entitySelector.appendChild(opt);
            });

            // If no main entity found, use the first one
            if(!defaultEntityId && d.data.items.length > 0) defaultEntityId = d.data.items[0].id;
            
            // Auto-select UI update
            if(defaultEntityId){
                entitySelector.value = defaultEntityId;
                btnLoad.disabled = false;
            }

            entitySelector.addEventListener('change',()=>{
                btnLoad.disabled = !entitySelector.value;
            });
            
            // Handle Load button
            btnLoad.addEventListener('click', () => {
                const val = entitySelector.value;
                if(!val) return;
                
                entityId = parseInt(val);
                
                // Update hidden inputs in modals
                document.querySelectorAll('input[name="entity_id"]').forEach(inp => {
                    inp.value = entityId;
                });

                // Show tabs
                document.querySelector('.content-tabs').style.display = 'block';
                
                // Load data
                loadPayments();
                loadBanks();
            });

            // Trigger load if auto-selected
            if(defaultEntityId){
                btnLoad.click();
            }
        }
    });
}

// Submit Payment Method
document.getElementById('paymentMethodForm').addEventListener('submit', function(e){
    e.preventDefault();
    if(entityId===0){ alert('Please select an entity first.'); return; }
    fetch('<?= $apiBase ?>/entity_payment_methods', {
        method:'POST',
        headers:{'X-CSRF-TOKEN':'<?= addslashes($csrf) ?>'},
        body:new FormData(this)
    }).then(r=>r.json()).then(d=>{
        if(d.success){ closeModal('paymentMethodModal'); alert('Saved'); loadPayments(); }
        else alert('Error: '+d.message);
    });
});

// Submit Bank Account
document.getElementById('bankAccountForm').addEventListener('submit', function(e){
    e.preventDefault();
    if(entityId===0){ alert('Please select an entity first.'); return; }
    fetch('<?= $apiBase ?>/entity_bank_accounts', {
        method:'POST',
        headers:{'X-CSRF-TOKEN':'<?= addslashes($csrf) ?>'},
        body:new FormData(this)
    }).then(r=>r.json()).then(d=>{
        if(d.success){ closeModal('bankAccountModal'); alert('Saved'); loadBanks(); }
        else alert('Error: '+d.message);
    });
});

// Load Payments / Banks
function loadPayments(){
    if(entityId===0) return;
    fetch('<?= $apiBase ?>/entity_payment_methods?entity_id='+entityId)
    .then(r=>r.json()).then(d=>{
        const tbody=document.getElementById('paymentMethodsBody');
        tbody.innerHTML='';
        if(d.success && d.data){
            d.data.forEach(p=>{
                const tr=document.createElement('tr');
                tr.innerHTML=`<td>${p.id}</td><td>${p.gateway_name}</td><td>${p.account_email||''}</td>
                                <td>${p.account_id||''}</td><td>${p.is_active? 'Yes':'No'}</td>
                                <td><button onclick="editPayment(${p.id})">Edit</button></td>`;
                tbody.appendChild(tr);
            });
        }
    });
}

function loadBanks(){
    if(entityId===0) return;
    fetch('<?= $apiBase ?>/entity_bank_accounts?entity_id='+entityId)
    .then(r=>r.json()).then(d=>{
        const tbody=document.getElementById('bankAccountsBody');
        tbody.innerHTML='';
        if(d.success && d.data){
            d.data.forEach(b=>{
                const tr=document.createElement('tr');
                tr.innerHTML=`<td>${b.id}</td><td>${b.bank_name}</td><td>${b.account_holder_name}</td>
                                <td>${b.account_number}</td><td>${b.iban||''}</td>
                                <td>${b.is_primary? 'Yes':'No'}</td>
                                <td>${b.is_verified? 'Yes':'No'}</td>
                                <td><button onclick="editBank(${b.id})">Edit</button></td>`;
                tbody.appendChild(tr);
            });
        }
    });
}

// Optional edit handlers
function editPayment(id){ alert('Edit Payment '+id); }
function editBank(id){ alert('Edit Bank '+id); }

// Load initial data if entityId exists
if(entityId!==0){
    loadPayments();
    loadBanks();
}
</script>

<?php if (!$isFragment) require_once __DIR__ . '/../includes/footer.php'; ?>
