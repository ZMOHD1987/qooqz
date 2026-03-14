<?php
declare(strict_types=1);
/**
 * admin/fragments/TenantCertificates.php
 * Client Version Dashboard – Fragment version
 */

$isAjax     = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
$isEmbedded = isset($_GET['embedded']) || isset($_POST['embedded']);
$isFragment = $isAjax || $isEmbedded;

// Load context if requested as fragment, otherwise assume it's included in a page with context
if ($isFragment) {
    require_once __DIR__ . '/../includes/admin_context.php';
}

if (!is_admin_logged_in()) {
    if ($isFragment) {
        http_response_code(401);
        echo json_encode(['error' => 'Not authenticated']);
        exit;
    }
    header('Location: /admin/login.php');
    exit;
}

$tenantId = admin_tenant_id();
$user     = admin_user();
$lang     = admin_lang();
$dir      = admin_dir();
$csrf     = admin_csrf();
?>

<?php if ($isFragment): ?>
<link rel="stylesheet" href="/admin/assets/css/pages/tenant_certificates.css?v=<?= time() ?>">
<?php endif; ?>

<div class="tp-page" id="tenantCertificatesRoot" dir="<?= htmlspecialchars($dir) ?>" data-lang="<?= htmlspecialchars($lang) ?>" data-tenant-id="<?= (int)$tenantId ?>" data-csrf="<?= htmlspecialchars($csrf) ?>">
    
    <!-- Header -->
    <div class="tp-header">
        <div class="tp-header-left">
            <div class="tp-header-icon"><i class="fas fa-user-shield"></i></div>
            <div>
                <h1 class="tp-title" data-i18n="portal.welcome" data-i18n-vars='{"user":"<?= htmlspecialchars($user['username']) ?>"}'>Welcome, <?= htmlspecialchars($user['username']) ?> 👋</h1>
                <p class="tp-subtitle" data-i18n="portal.subtitle">Manage your certificates and products here</p>
            </div>
        </div>
    </div>

    <!-- Tabs -->
    <div class="tp-tabs">
        <button class="tp-tab active" data-tab="dashboard">
            <i class="fas fa-th-large"></i> <span data-i18n="tabs.dashboard">Dashboard</span>
        </button>
        <button class="tp-tab" data-tab="requests">
            <i class="fas fa-file-alt"></i> <span data-i18n="tabs.requests">Requests</span>
        </button>
        <button class="tp-tab" data-tab="products">
            <i class="fas fa-box"></i> <span data-i18n="tabs.products">Products</span>
        </button>
        <button class="tp-tab" data-tab="issued">
            <i class="fas fa-stamp"></i> <span data-i18n="tabs.issued">Issued</span>
        </button>
        <button class="tp-tab" data-tab="notifications">
            <i class="fas fa-bell"></i> <span data-i18n="tabs.notifications">Notifications</span>
        </button>
    </div>

    <!-- PANEL: DASHBOARD -->
    <div class="tp-tab-panel active" id="panelDashboard">
        <div class="tp-dashboard-grid">
            <!-- Active Requests Card -->
            <div class="tp-stats-card tp-stats-summary" style="padding:0; overflow:hidden;">
                <div class="tp-card-header">
                    <h3 data-i18n="portal.table.title">Active Requests</h3>
                    <button class="btn-tp btn-view" onclick="TenantCertificates.loadDashboard()">
                        <i class="fas fa-sync-alt"></i> <span data-i18n="portal.table.refresh">Refresh</span>
                    </button>
                </div>
                <div style="overflow-x:auto;">
                    <table class="tp-table">
                        <thead>
                            <tr>
                                <th data-i18n="portal.table.headers.id">ID</th>
                                <th data-i18n="portal.table.headers.entity">Entity</th>
                                <th data-i18n="portal.table.headers.status">Status</th>
                                <th data-i18n="portal.table.headers.payment">Payment</th>
                                <th data-i18n="portal.table.headers.date">Date</th>
                                <th data-i18n="portal.table.headers.actions">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="tpDashboardTableBody"></tbody>
                    </table>
                </div>
            </div>

            <!-- Registration Links (AJAX fragments) -->
            <div class="tp-card">
                <div class="tp-card-header"><h3 data-i18n="portal.links.title">Registration Links</h3></div>
                <div class="tp-links-grid">
                    <button class="tp-link-card" data-fragment="/admin/fragments/certificates_requests?embedded=1">
                        <i class="fas fa-file-signature"></i>
                        <span data-i18n="portal.links.register_request">Register Request</span>
                    </button>
                    <button class="tp-link-card" data-fragment="/admin/fragments/certificates_products?embedded=1">
                        <i class="fas fa-cube"></i>
                        <span data-i18n="portal.links.register_product">Register Product</span>
                    </button>
                    <button class="tp-link-card" data-fragment="/admin/fragments/brands?embedded=1">
                        <i class="fas fa-tag"></i>
                        <span data-i18n="portal.links.register_brand">Register Brand</span>
                    </button>
                    <button class="tp-link-card" data-fragment="/admin/fragments/entities?embedded=1">
                        <i class="fas fa-building"></i>
                        <span data-i18n="portal.links.my_data">My Data</span>
                    </button>
                </div>
            </div>

            <!-- Lifecycle Tracker -->
            <div class="tp-lifecycle">
                <div class="tp-lifecycle-title">
                    <i class="fas fa-stream" style="color:var(--tp-accent)"></i>
                    <span data-i18n="portal.lifecycle.title">Status Tracker</span>
                </div>
                <div class="tp-steps" id="tpSteps">
                    <div class="tp-step"><div class="tp-step-dot">1</div><div class="tp-step-lbl" data-i18n="portal.lifecycle.under_review">Under Review</div></div>
                    <div class="tp-step"><div class="tp-step-dot">2</div><div class="tp-step-lbl" data-i18n="portal.lifecycle.approved">Approved</div></div>
                    <div class="tp-step"><div class="tp-step-dot">3</div><div class="tp-step-lbl" data-i18n="portal.lifecycle.waiting_payment">Waiting Payment</div></div>
                    <div class="tp-step"><div class="tp-step-dot">4</div><div class="tp-step-lbl" data-i18n="portal.lifecycle.verified">Verified</div></div>
                    <div class="tp-step"><div class="tp-step-dot">5</div><div class="tp-step-lbl" data-i18n="portal.lifecycle.ready">Ready</div></div>
                </div>
            </div>
        </div>
    </div>

    <!-- PANEL: REQUESTS -->
    <div class="tp-tab-panel" id="panelRequests" style="display:none">
        <div class="tp-card">
            <div class="tp-card-header">
                <h3 data-i18n="tabs.requests">All Requests</h3>
            </div>
            <div style="overflow-x:auto;">
                <table class="tp-table">
                    <thead>
                        <tr>
                            <th data-i18n="table.headers.id">ID</th>
                            <th data-i18n="table.headers.entity">Entity</th>
                            <th data-i18n="table.headers.importer">Importer</th>
                            <th data-i18n="table.headers.status">Status</th>
                            <th data-i18n="table.headers.payment">Payment</th>
                            <th data-i18n="table.headers.created">Created</th>
                            <th data-i18n="table.headers.actions">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="reqTableBody"></tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- PANEL: PRODUCTS -->
    <div class="tp-tab-panel" id="panelProducts" style="display:none">
        <div class="tp-card">
            <div class="tp-card-header">
                <h3 data-i18n="tabs.products">Products</h3>
            </div>
            <div style="overflow-x:auto;">
                <table class="tp-table">
                    <thead>
                        <tr>
                            <th data-i18n="prod_table.id">ID</th>
                            <th data-i18n="prod_table.entity">Entity</th>
                            <th data-i18n="prod_table.brand">Brand</th>
                            <th data-i18n="prod_table.name">Name</th>
                            <th data-i18n="prod_table.code">Code</th>
                            <th data-i18n="prod_table.country">Country of Origin</th>
                        </tr>
                    </thead>
                    <tbody id="prodTableBody"></tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- PANEL: ISSUED -->
    <div class="tp-tab-panel" id="panelIssued" style="display:none">
        <div class="tp-card">
            <div class="tp-card-header">
                <h3 data-i18n="tabs.issued">Issued Certificates</h3>
            </div>
            <div style="overflow-x:auto;">
                <table class="tp-table">
                    <thead>
                        <tr>
                            <th data-i18n="iss_table.id">ID</th>
                            <th data-i18n="iss_table.cert_number">Number</th>
                            <th data-i18n="iss_table.issued_at">Issued At</th>
                            <th data-i18n="iss_table.printable_until">Valid Until</th>
                            <th data-i18n="iss_table.actions">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="issuedTableBody"></tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- PANEL: NOTIFICATIONS -->
    <div class="tp-tab-panel" id="panelNotifications" style="display:none">
        <div class="tp-card">
            <div class="tp-card-header">
                <h3 data-i18n="tabs.notifications">Notifications</h3>
                <button class="btn-tp btn-view" onclick="TenantCertificates.markAllRead()" data-i18n="notifications.mark_all">Mark all as read</button>
            </div>
            <div id="notifList" class="tp-notif-list">
                <!-- Loaded via JS -->
            </div>
        </div>
    </div>

    <!-- Fragment Modal (for AJAX-loaded forms) -->
    <div id="fragmentModal" class="tp-modal-backdrop" style="display:none;">
        <div class="tp-modal tp-modal-lg">
            <div class="tp-modal-header">
                <h3 class="tp-modal-title" id="fragmentModalTitle"></h3>
                <button class="tp-modal-close" onclick="TenantCertificates.closeFragmentModal()">&times;</button>
            </div>
            <div class="tp-modal-body" id="fragmentModalBody"></div>
        </div>
    </div>

    <!-- Modal: Request Details -->
    <div id="modalRequestDetails" class="tp-modal-backdrop" style="display:none">
        <div class="tp-modal" style="max-width: 900px;">
            <div class="tp-modal-header">
                <h3 class="tp-modal-title" id="detModalTitle">Request Details</h3>
                <button class="tp-modal-close" onclick="TenantCertificates.closeRequestDetails()">&times;</button>
            </div>
            <div class="tp-modal-body" id="detContent"></div>
            <div class="tp-modal-footer">
                <button class="btn-tp btn-view" onclick="TenantCertificates.closeRequestDetails()" data-i18n="common.close">Close</button>
            </div>
        </div>
    </div>

    <!-- Modal: Notification Details -->
    <div id="modalNotifDetails" class="tp-modal-backdrop" style="display:none">
        <div class="tp-modal" style="max-width: 600px;">
            <div class="tp-modal-header">
                <h3 class="tp-modal-title" id="notifModalTitle">Notification</h3>
                <button class="tp-modal-close" onclick="TenantCertificates.closeNotifDetails()">&times;</button>
            </div>
            <div class="tp-modal-body">
                <div id="notifModalMeta" class="tp-small" style="margin-bottom:15px; color:var(--tp-text-s);"></div>
                <div id="notifModalMessage" style="line-height:1.6; font-size:1.1rem;"></div>
            </div>
            <div class="tp-modal-footer">
                <button class="btn-tp btn-view" onclick="TenantCertificates.closeNotifDetails()" data-i18n="common.close">Close</button>
            </div>
        </div>
    </div>

</div>

<?php if ($isFragment): ?>
<script src="/admin/assets/js/pages/tenant_certificates.js?v=<?= time() ?>"></script>
<?php endif; ?>