<?php
declare(strict_types=1);
/**
 * /admin/fragments/CertificateManagement.php
 * VERSION: 3.0 — PERFORMANCE OPTIMIZED
 */

$isAjax     = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
$isEmbedded = isset($_GET['embedded']) || isset($_POST['embedded']);
$isFragment = $isAjax || $isEmbedded;

if ($isFragment) { require_once __DIR__ . '/../includes/admin_context.php'; }
else             { require_once __DIR__ . '/../includes/header.php'; }

if (!is_admin_logged_in()) {
    if ($isFragment) { http_response_code(401); echo json_encode(['error'=>'Not authenticated']); exit; }
    header('Location: /admin/login.php'); exit;
}

$user      = admin_user();
$lang      = admin_lang();
$dir       = admin_dir();
$csrf      = admin_csrf();
$tenantId  = admin_tenant_id();

$canAudit     = can('certificates_audits.manage') || can_edit_all('certificates_audits') || is_super_admin();
$canVerifyPay = can('certificates_payments.manage') || can_edit_all('certificates_payments') || is_super_admin();
$canIssue     = can('certificates_issued.manage') || can_edit_all('certificates_issued') || is_super_admin();
$canViewReq   = can_view_all('certificates_requests') || can_view_own('certificates_requests') || can_view_tenant('certificates_requests') || is_super_admin();

if (!$canViewReq && !is_super_admin()) {
    if ($isFragment) { http_response_code(403); echo json_encode(['error'=>'Access denied']); exit; }
    die('Access denied');
}

function __cm_t($key, $fallback = '') {
    if (function_exists('i18n_get')) { $v = i18n_get($key); return $v ?? ($fallback ?? $key); }
    return $fallback ?? $key;
}
?>
<?php if ($isFragment): ?>
<link rel="stylesheet" href="/admin/assets/css/pages/CertificateManagement.css?v=<?= time() ?>">
<?php endif; ?>

<!-- ══ PERFORMANCE: Inline critical CSS for instant skeleton rendering ══ -->
<style>
/* ── Skeleton shimmer animation ── */
@keyframes cm-shimmer {
    0%   { background-position: -468px 0; }
    100% { background-position: 468px 0; }
}
.cm-skeleton {
    display: inline-block;
    width: 100%;
    height: 14px;
    border-radius: 4px;
    background: linear-gradient(to right, #f0f0f0 8%, #e0e0e0 18%, #f0f0f0 33%);
    background-size: 936px 104px;
    animation: cm-shimmer 1.2s linear infinite;
}
.cm-skeleton-row td { padding: 10px 12px; }

/* ── Tab panel: hide by default, show active ── */
.cm-tab-panel { display: none; }
.cm-tab-panel.active { display: block; }

/* ── Detail skeleton ── */
.cm-detail-skeleton { padding: 8px; }
.cm-detail-skeleton .cm-skeleton { margin-bottom: 10px; display: block; }
</style>

<div class="cm-page" id="certMgmtPage" dir="<?= htmlspecialchars($dir) ?>">

    <!-- ══ PAGE HEADER ══════════════════════════════════════════════════ -->
    <div class="cm-header">
        <div class="cm-header-left">
            <div class="cm-header-icon"><i class="fas fa-shield-alt"></i></div>
            <div>
                <h1 class="cm-title" data-i18n="page.title">Certificate Management</h1>
                <p class="cm-subtitle" data-i18n="page.subtitle">Audit, payment verification &amp; issuance workflow</p>
            </div>
        </div>
        <div class="cm-header-right">
            <div class="cm-stats" id="cmStats">
                <div class="cm-stat-item" id="statPending">
                    <span class="cm-stat-num" id="statPendingNum"><span class="cm-skeleton" style="width:30px;height:22px;display:inline-block;"></span></span>
                    <span class="cm-stat-lbl" data-i18n="stats.pending">Pending Audit</span>
                </div>
                <div class="cm-stat-item" id="statPayment">
                    <span class="cm-stat-num" id="statPaymentNum"><span class="cm-skeleton" style="width:30px;height:22px;display:inline-block;"></span></span>
                    <span class="cm-stat-lbl" data-i18n="stats.payment">Awaiting Payment</span>
                </div>
                <div class="cm-stat-item" id="statApproved">
                    <span class="cm-stat-num" id="statApprovedNum"><span class="cm-skeleton" style="width:30px;height:22px;display:inline-block;"></span></span>
                    <span class="cm-stat-lbl" data-i18n="stats.approved">Approved</span>
                </div>
                <div class="cm-stat-item" id="statIssued">
                    <span class="cm-stat-num" id="statIssuedNum"><span class="cm-skeleton" style="width:30px;height:22px;display:inline-block;"></span></span>
                    <span class="cm-stat-lbl" data-i18n="stats.issued">Issued</span>
                </div>
            </div>
        </div>
    </div>

    <!-- ══ TABS ═══════════════════════════════════════════════════════════ -->
    <div class="cm-tabs">
        <button class="cm-tab active" data-tab="requests" id="tabRequests">
            <i class="fas fa-file-alt"></i>
            <span data-i18n="tabs.requests">Requests</span>
            <span class="cm-tab-badge" id="badgeRequests"></span>
        </button>
        <?php if ($canAudit): ?>
        <button class="cm-tab" data-tab="audits" id="tabAudits">
            <i class="fas fa-search"></i>
            <span data-i18n="tabs.audits">Audits</span>
            <span class="cm-tab-badge" id="badgeAudits"></span>
        </button>
        <?php endif; ?>
        <?php if ($canVerifyPay): ?>
        <button class="cm-tab" data-tab="payments" id="tabPayments">
            <i class="fas fa-credit-card"></i>
            <span data-i18n="tabs.payments">Payments</span>
            <span class="cm-tab-badge" id="badgePayments"></span>
        </button>
        <?php endif; ?>
        <?php if ($canIssue): ?>
        <button class="cm-tab" data-tab="issued" id="tabIssued">
            <i class="fas fa-stamp"></i>
            <span data-i18n="tabs.issued">Issued</span>
        </button>
        <?php endif; ?>
        <button class="cm-tab" data-tab="logs" id="tabLogs">
            <i class="fas fa-history"></i>
            <span data-i18n="tabs.logs">Logs</span>
        </button>
        <button class="cm-tab" data-tab="allocations" id="tabAllocations">
            <i class="fas fa-tasks"></i>
            <span data-i18n="tabs.allocations">Allocations</span>
        </button>
        <button class="cm-tab" data-tab="settings" id="tabSettings">
            <i class="fas fa-cog"></i>
            <span data-i18n="tabs.settings">Settings</span>
        </button>
    </div>

    <!-- ══ TAB: REQUESTS ════════════════════════════════════════════════ -->
    <div class="cm-tab-panel active" id="panelRequests">
        <div class="cm-card cm-filters-card">
            <div class="cm-filters-grid">
                <div class="cm-filter-group">
                    <label data-i18n="filters.search">Search</label>
                    <input type="text" id="reqSearch" class="form-control" data-i18n-placeholder="filters.search_placeholder" placeholder="Importer name...">
                </div>
                <?php if (is_super_admin()): ?>
                <div class="cm-filter-group">
                    <label data-i18n="filters.tenant_id">Tenant ID</label>
                    <input type="number" id="reqTenantFilter" class="form-control" value="<?= (int)$tenantId ?>">
                </div>
                <?php endif; ?>
                <div class="cm-filter-group">
                    <label data-i18n="filters.entity">Entity</label>
                    <select id="reqEntityFilter" class="form-control">
                        <option value="" data-i18n="filters.all_entities">All Entities</option>
                    </select>
                </div>
                <div class="cm-filter-group">
                    <label data-i18n="filters.status">Status</label>
                    <select id="reqStatusFilter" class="form-control">
                        <option value="">All</option>
                        <option value="draft">Draft</option>
                        <option value="under_review">Under Review</option>
                        <option value="payment_pending">Payment Pending</option>
                        <option value="approved">Approved</option>
                        <option value="rejected">Rejected</option>
                        <option value="issued">Issued</option>
                    </select>
                </div>
                <div class="cm-filter-group">
                    <label data-i18n="filters.auditor">Auditor</label>
                    <select id="reqAuditorFilter" class="form-control">
                        <option value="">All</option>
                    </select>
                </div>
                <div class="cm-filter-group cm-filter-actions">
                    <button id="btnReqFilter" class="btn btn-secondary" data-i18n="filters.apply">Apply</button>
                    <button id="btnReqReset"  class="btn btn-outline"   data-i18n="filters.reset">Reset</button>
                </div>
            </div>
        </div>

        <div class="cm-card">
            <div id="reqLoading" class="cm-loading" style="display:none"><div class="cm-spinner"></div><p>Loading...</p></div>
            <div id="reqTable" style="display:block">
                <div class="table-responsive">
                    <table class="cm-data-table" id="requestsTable">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <?php if (is_super_admin()): ?><th>Tenant</th><?php endif; ?>
                                <th data-i18n="req_table.entity">Entity</th>
                                <th data-i18n="req_table.certificate">Certificate</th>
                                <th data-i18n="req_table.importer">Importer</th>
                                <th>Country</th>
                                <th>Type</th>
                                <th data-i18n="req_table.status">Status</th>
                                <th data-i18n="req_table.auditor">Auditor</th>
                                <th data-i18n="req_table.created">Created</th>
                                <th data-i18n="req_table.actions">Actions</th>
                            </tr>
                        </thead>
                        <!-- Pre-render skeleton rows for instant visual feedback -->
                        <tbody id="reqTableBody">
                            <?php for($i=0;$i<6;$i++): ?>
                            <tr class="cm-skeleton-row">
                                <?php $cols = is_super_admin() ? 11 : 10; for($j=0;$j<$cols;$j++): ?>
                                <td><div class="cm-skeleton"></div></td>
                                <?php endfor; ?>
                            </tr>
                            <?php endfor; ?>
                        </tbody>
                    </table>
                </div>
                <div class="cm-pagination-wrap">
                    <div class="cm-pagination-info"><span id="reqPaginationInfo"></span></div>
                    <div id="reqPagination" class="pagination"></div>
                </div>
            </div>
            <div id="reqEmpty" class="cm-empty-state" style="display:none">
                <div class="cm-empty-icon">📋</div>
                <h3 data-i18n="req_table.empty_title">No Requests Found</h3>
                <p data-i18n="req_table.empty_msg">Adjust filters to find requests</p>
            </div>
        </div>
    </div>

    <!-- ══ TAB: AUDITS ══════════════════════════════════════════════════ -->
    <?php if ($canAudit): ?>
    <div class="cm-tab-panel" id="panelAudits">
        <div class="cm-card cm-filters-card">
            <div class="cm-filters-grid">
                <div class="cm-filter-group">
                    <label data-i18n="filters.request_id">Request ID</label>
                    <input type="number" id="auditReqIdFilter" class="form-control" placeholder="e.g. 42">
                </div>
                <div class="cm-filter-group">
                    <label data-i18n="filters.audit_status">Audit Status</label>
                    <select id="auditStatusFilter" class="form-control">
                        <option value="">All</option>
                        <option value="pending">Pending</option>
                        <option value="approved">Approved</option>
                        <option value="rejected">Rejected</option>
                    </select>
                </div>
                <div class="cm-filter-group cm-filter-actions">
                    <button id="btnAuditFilter" class="btn btn-secondary">Apply</button>
                    <button id="btnAuditReset"  class="btn btn-outline">Reset</button>
                </div>
            </div>
        </div>
        <div class="cm-card">
            <div id="auditLoading" class="cm-loading" style="display:none"><div class="cm-spinner"></div><p>Loading...</p></div>
            <div id="auditTable" style="display:none">
                <div class="table-responsive">
                    <table class="cm-data-table">
                        <thead><tr>
                            <th>ID</th><th>Request</th><th>Entity</th><th>Auditor</th>
                            <th>Status</th><th>Audit Date</th><th>Notes</th><th>Actions</th>
                        </tr></thead>
                        <tbody id="auditTableBody"></tbody>
                    </table>
                </div>
                <div class="cm-pagination-wrap">
                    <div class="cm-pagination-info"><span id="auditPaginationInfo"></span></div>
                    <div id="auditPagination" class="pagination"></div>
                </div>
            </div>
            <div id="auditEmpty" class="cm-empty-state" style="display:none"><div class="cm-empty-icon">🔍</div><h3>No Audits Found</h3></div>
        </div>
    </div>
    <?php endif; ?>

    <!-- ══ TAB: PAYMENTS ════════════════════════════════════════════════ -->
    <?php if ($canVerifyPay): ?>
    <div class="cm-tab-panel" id="panelPayments">
        <div class="cm-card cm-filters-card">
            <div class="cm-filters-grid">
                <div class="cm-filter-group">
                    <label>Request ID</label>
                    <input type="number" id="payReqIdFilter" class="form-control" placeholder="e.g. 42">
                </div>
                <div class="cm-filter-group">
                    <label>Payment Status</label>
                    <select id="payStatusFilter" class="form-control">
                        <option value="">All</option>
                        <option value="waiting_verification">Waiting Verification</option>
                        <option value="verified">Verified</option>
                        <option value="rejected">Rejected</option>
                    </select>
                </div>
                <div class="cm-filter-group cm-filter-actions">
                    <button id="btnPayFilter" class="btn btn-secondary">Apply</button>
                    <button id="btnPayReset"  class="btn btn-outline">Reset</button>
                </div>
            </div>
        </div>
        <div class="cm-card">
            <div class="cm-card-header" style="padding:15px; display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid var(--border-color);">
                <h4>Payments</h4>
                <button class="btn btn-sm btn-primary" onclick="CertificateManagement.openAddPayment()">
                    <i class="fas fa-plus"></i> Add Payment
                </button>
            </div>
            <div id="payLoading" class="cm-loading" style="display:none"><div class="cm-spinner"></div><p>Loading...</p></div>
            <div id="payTable" style="display:none">
                <div class="table-responsive">
                    <table class="cm-data-table">
                        <thead><tr>
                            <th>ID</th><th>Request</th><th>Type</th><th>Amount</th>
                            <th>Reference</th><th>Date</th><th>Receipt</th><th>Status</th><th>Actions</th>
                        </tr></thead>
                        <tbody id="payTableBody"></tbody>
                    </table>
                </div>
                <div class="cm-pagination-wrap">
                    <div class="cm-pagination-info"><span id="payPaginationInfo"></span></div>
                    <div id="payPagination" class="pagination"></div>
                </div>
            </div>
            <div id="payEmpty" class="cm-empty-state" style="display:none"><div class="cm-empty-icon">💳</div><h3>No Payments Found</h3></div>
        </div>
    </div>
    <?php endif; ?>

    <!-- ══ TAB: ISSUED ══════════════════════════════════════════════════ -->
    <?php if ($canIssue): ?>
    <div class="cm-tab-panel" id="panelIssued">
        <div class="cm-card cm-filters-card">
            <div class="cm-filters-grid">
                <div class="cm-filter-group">
                    <label>Certificate Number</label>
                    <input type="text" id="issuedSearchFilter" class="form-control" placeholder="e.g. CERT-2025-001">
                </div>
                <div class="cm-filter-group">
                    <label>Cancelled</label>
                    <select id="issuedCancelledFilter" class="form-control">
                        <option value="">All</option>
                        <option value="0">Active</option>
                        <option value="1">Cancelled</option>
                    </select>
                </div>
                <div class="cm-filter-group cm-filter-actions">
                    <button id="btnIssuedFilter" class="btn btn-secondary">Apply</button>
                    <button id="btnIssuedReset"  class="btn btn-outline">Reset</button>
                </div>
            </div>
        </div>
        <div class="cm-card">
            <div id="issuedLoading" class="cm-loading" style="display:none"><div class="cm-spinner"></div><p>Loading...</p></div>
            <div id="issuedTable" style="display:none">
                <div class="table-responsive">
                    <table class="cm-data-table">
                        <thead><tr>
                            <th>ID</th><th>Cert Number</th><th>Version</th><th>Issued At</th>
                            <th>Valid Until</th><th>Issued By</th><th>Language</th><th>Status</th><th>Actions</th>
                        </tr></thead>
                        <tbody id="issuedTableBody"></tbody>
                    </table>
                </div>
                <div class="cm-pagination-wrap">
                    <div class="cm-pagination-info"><span id="issuedPaginationInfo"></span></div>
                    <div id="issuedPagination" class="pagination"></div>
                </div>
            </div>
            <div id="issuedEmpty" class="cm-empty-state" style="display:none"><div class="cm-empty-icon">🏅</div><h3>No Issued Certificates</h3></div>
        </div>
    </div>
    <?php endif; ?>

    <!-- ══ TAB: LOGS ════════════════════════════════════════════════════ -->
    <div class="cm-tab-panel" id="panelLogs">
        <div class="cm-card cm-filters-card">
            <div class="cm-filters-grid">
                <div class="cm-filter-group">
                    <label>Request ID</label>
                    <input type="number" id="logReqIdFilter" class="form-control" placeholder="e.g. 42">
                </div>
                <div class="cm-filter-group">
                    <label>Action Type</label>
                    <select id="logActionFilter" class="form-control">
                        <option value="">All</option>
                        <option value="create">create</option>
                        <option value="update">update</option>
                        <option value="approve">approve</option>
                        <option value="audit">audit</option>
                        <option value="payment_sent">payment_sent</option>
                        <option value="issue">issue</option>
                        <option value="reject">reject</option>
                    </select>
                </div>
                <div class="cm-filter-group cm-filter-actions">
                    <button id="btnLogFilter" class="btn btn-secondary">Apply</button>
                    <button id="btnLogReset"  class="btn btn-outline">Reset</button>
                </div>
            </div>
        </div>
        <div class="cm-card">
            <div id="logLoading" class="cm-loading" style="display:none"><div class="cm-spinner"></div><p>Loading...</p></div>
            <div id="logTable" style="display:none">
                <div class="table-responsive">
                    <table class="cm-data-table">
                        <thead><tr><th>ID</th><th>Request</th><th>User</th><th>Action</th><th>Notes</th><th>Date</th></tr></thead>
                        <tbody id="logTableBody"></tbody>
                    </table>
                </div>
                <div class="cm-pagination-wrap">
                    <div class="cm-pagination-info"><span id="logPaginationInfo"></span></div>
                    <div id="logPagination" class="pagination"></div>
                </div>
            </div>
            <div id="logEmpty" class="cm-empty-state" style="display:none"><div class="cm-empty-icon">📜</div></div>
        </div>
    </div>

    <!-- ══ TAB: ALLOCATIONS ═════════════════════════════════════════════ -->
    <div class="cm-tab-panel" id="panelAllocations">
        <div class="cm-card cm-filters-card">
            <div class="cm-filters-grid">
                <div class="cm-filter-group">
                    <label>Receipt ID</label>
                    <input type="number" id="allocReceiptFilter" class="form-control" placeholder="e.g. 1000">
                </div>
                <div class="cm-filter-group">
                    <label>Version ID</label>
                    <input type="number" id="allocCertFilter" class="form-control" placeholder="e.g. 101">
                </div>
                <div class="cm-filter-group cm-filter-actions">
                    <button id="btnAllocFilter" class="btn btn-secondary">Apply</button>
                    <button id="btnAllocReset"  class="btn btn-outline">Reset</button>
                </div>
            </div>
        </div>
        <div class="cm-card">
            <div class="cm-card-header" style="padding:15px; display:flex; justify-content:space-between; align-items:center;">
                <h4>Certificate Receipt Allocations</h4>
                <button class="btn btn-sm btn-primary" onclick="CertificateManagement.openAllocationModal()">
                    <i class="fas fa-plus"></i> Add Allocation
                </button>
            </div>
            <div id="allocLoading" class="cm-loading" style="display:none"><div class="cm-spinner"></div><p>Loading...</p></div>
            <div id="allocTable" style="display:none">
                <div class="table-responsive">
                    <table class="cm-data-table">
                        <thead><tr>
                            <th>ID</th><th>Receipt #</th><th>Certificate</th>
                            <th>Fee Rule</th><th>Allocated Amount</th><th>Created At</th><th>Actions</th>
                        </tr></thead>
                        <tbody id="allocTableBody"></tbody>
                    </table>
                </div>
                <div class="cm-pagination-wrap">
                    <div class="cm-pagination-info"><span id="allocPaginationInfo"></span></div>
                    <div id="allocPagination" class="pagination"></div>
                </div>
            </div>
            <div id="allocEmpty" class="cm-empty-state" style="display:none"><div class="cm-empty-icon">📊</div><h3>No Allocations Found</h3></div>
        </div>
    </div>

    <!-- ══ TAB: SETTINGS ════════════════════════════════════════════════ -->
    <div class="cm-tab-panel" id="panelSettings">
        <div class="settings-container">
            <div class="settings-sidebar">
                <div class="settings-nav-item active" data-sub="certs"><i class="fas fa-certificate"></i> Certificates</div>
                <div class="settings-nav-item" data-sub="editions"><i class="fas fa-file-contract"></i> Editions</div>
                <div class="settings-nav-item" data-sub="fees"><i class="fas fa-dollar-sign"></i> Fee Rules</div>
            </div>
            <div class="settings-main">
                <div class="settings-sub-panel active" id="subPanelCerts">
                    <div class="cm-card-header" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;">
                        <h4>Certificate Types</h4>
                        <button class="btn btn-sm btn-primary" onclick="CertificateManagement.openCertModal()"><i class="fas fa-plus"></i></button>
                    </div>
                    <div class="table-responsive">
                        <table class="cm-data-table">
                            <thead><tr><th>ID</th><th>Code</th><th>Name</th><th>Active</th><th>Actions</th></tr></thead>
                            <tbody id="certsTableBody"></tbody>
                        </table>
                    </div>
                </div>
                <div class="settings-sub-panel" id="subPanelEditions" style="display:none">
                    <div class="cm-card-header" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;">
                        <h4>Versions &amp; Editions</h4>
                        <button class="btn btn-sm btn-primary" onclick="CertificateManagement.openEditionModal()"><i class="fas fa-plus"></i></button>
                    </div>
                    <div class="table-responsive">
                        <table class="cm-data-table">
                            <thead><tr><th>ID</th><th>Cert</th><th>Code</th><th>Lang</th><th>Scope</th><th>Version</th><th>Actions</th></tr></thead>
                            <tbody id="editionsTableBody"></tbody>
                        </table>
                    </div>
                </div>
                <div class="settings-sub-panel" id="subPanelFees" style="display:none">
                    <div class="cm-card-header" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;">
                        <h4>Fee Rules</h4>
                        <button class="btn btn-sm btn-primary" onclick="CertificateManagement.openFeeModal()"><i class="fas fa-plus"></i></button>
                    </div>
                    <div class="table-responsive">
                        <table class="cm-data-table">
                            <thead><tr><th>ID</th><th>Type</th><th>Min Items</th><th>Max Items</th><th>Fee</th><th>Actions</th></tr></thead>
                            <tbody id="feesTableBody"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ══ MODALS ════════════════════════════════════════════════════════ -->

    <!-- Certificate Type Modal -->
    <div id="modalCert" class="cm-modal-backdrop" style="display:none">
        <div class="cm-modal">
            <div class="cm-modal-header">
                <h3 class="cm-modal-title" id="certModalTitle">Certificate Type</h3>
                <button class="cm-modal-close" onclick="closeModal('modalCert')">&times;</button>
            </div>
            <div class="cm-modal-body">
                <form id="formCert">
                    <input type="hidden" name="id" id="cert_id">
                    <div class="form-group"><label>Code <span class="req">*</span></label><input type="text" id="cert_code" class="form-control" required></div>
                    <div class="form-group"><label>Description <span class="req">*</span></label><textarea id="cert_desc" class="form-control" rows="3" required></textarea></div>
                    <div class="form-group"><label><input type="checkbox" id="cert_active" checked> Active</label></div>
                </form>
            </div>
            <div class="cm-modal-footer">
                <button class="btn btn-outline" onclick="closeModal('modalCert')">Cancel</button>
                <button class="btn btn-primary" onclick="CertificateManagement.saveCert()">Save</button>
            </div>
        </div>
    </div>

    <!-- Edition Modal -->
    <div id="modalEdition" class="cm-modal-backdrop" style="display:none">
        <div class="cm-modal">
            <div class="cm-modal-header">
                <h3 class="cm-modal-title" id="editionModalTitle">Edition / Version</h3>
                <button class="cm-modal-close" onclick="closeModal('modalEdition')">&times;</button>
            </div>
            <div class="cm-modal-body">
                <form id="formEdition">
                    <input type="hidden" id="ed_id">
                    <div class="form-row">
                        <div class="form-group"><label>Certificate Type <span class="req">*</span></label><select id="ed_cert_id" class="form-select" required></select></div>
                        <div class="form-group"><label>Edition Code <span class="req">*</span></label><input type="text" id="ed_code" class="form-control" required></div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label>Language <span class="req">*</span></label>
                            <select id="ed_lang" class="form-select" required><option value="ar">Arabic</option><option value="en">English</option></select>
                        </div>
                        <div class="form-group"><label>Version <span class="req">*</span></label><input type="text" id="ed_ver" class="form-control" required></div>
                    </div>
                    <div class="form-group"><label>Scope</label><input type="text" id="ed_scope" class="form-control"></div>
                </form>
            </div>
            <div class="cm-modal-footer">
                <button class="btn btn-outline" onclick="closeModal('modalEdition')">Cancel</button>
                <button class="btn btn-primary" onclick="CertificateManagement.saveEdition()">Save</button>
            </div>
        </div>
    </div>

    <!-- Fee Rule Modal -->
    <div id="modalFee" class="cm-modal-backdrop" style="display:none">
        <div class="cm-modal">
            <div class="cm-modal-header">
                <h3 class="cm-modal-title" id="feeModalTitle">Fee Rule</h3>
                <button class="cm-modal-close" onclick="closeModal('modalFee')">&times;</button>
            </div>
            <div class="cm-modal-body">
                <form id="formFee">
                    <input type="hidden" id="fee_id">
                    <div class="form-group"><label>Fee Type <span class="req">*</span></label><input type="text" id="fee_type" class="form-control" required></div>
                    <div class="form-row">
                        <div class="form-group"><label>Min Items</label><input type="number" id="fee_min" class="form-control" value="0"></div>
                        <div class="form-group"><label>Max Items</label><input type="number" id="fee_max" class="form-control" placeholder="Leave empty for unlimited"></div>
                    </div>
                    <div class="form-group"><label>Fee Amount <span class="req">*</span></label><input type="number" step="0.01" id="fee_amount" class="form-control" required></div>
                </form>
            </div>
            <div class="cm-modal-footer">
                <button class="btn btn-outline" onclick="closeModal('modalFee')">Cancel</button>
                <button class="btn btn-primary" onclick="CertificateManagement.saveFee()">Save</button>
            </div>
        </div>
    </div>

    <!-- Allocation Modal — body injected dynamically by JS -->
    <div id="modalAllocation" class="cm-modal-backdrop" style="display:none">
        <div class="cm-modal">
            <div class="cm-modal-header">
                <h3 class="cm-modal-title" id="allocModalTitle">Add Allocation</h3>
                <button class="cm-modal-close" onclick="closeModal('modalAllocation')">&times;</button>
            </div>
            <div class="cm-modal-body" id="allocModalBody">
                <!-- Injected by JS openAllocationModal() -->
            </div>
        </div>
    </div>

    <!-- Audit Modal -->
    <div id="modalAudit" class="cm-modal-backdrop" style="display:none">
        <div class="cm-modal">
            <div class="cm-modal-header">
                <h3 class="cm-modal-title" data-i18n="audit.modal.title">Conduct Audit</h3>
                <button class="cm-modal-close" id="btnCloseAuditModal"><i class="fas fa-times"></i></button>
            </div>
            <div class="cm-modal-body">
                <div class="cm-request-summary" id="auditRequestSummary">
                    <div class="cm-skeleton" style="height:18px;margin-bottom:8px;"></div>
                    <div class="cm-skeleton" style="height:18px;width:70%;"></div>
                </div>
                <div class="cm-section-label" data-i18n="audit.modal.items">Request Items</div>
                <div id="auditItemsList" class="cm-items-readonly"><div class="cm-skeleton" style="height:60px;"></div></div>
                <div class="cm-section-label" data-i18n="audit.modal.decision">Audit Decision</div>
                <form id="auditForm" novalidate>
                    <input type="hidden" id="auditFormRequestId">
                    <input type="hidden" id="auditFormId">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Decision <span class="req">*</span></label>
                            <select id="auditFormStatus" class="form-control" required>
                                <option value="approved">Approve</option>
                                <option value="rejected">Reject</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Audit Date <span class="req">*</span></label>
                            <input type="datetime-local" id="auditFormDate" class="form-control" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Notes</label>
                        <textarea id="auditFormNotes" class="form-control" rows="3"></textarea>
                    </div>
                    <div class="form-group" id="auditAssignRow">
                        <label>Assign Auditor</label>
                        <select id="auditAssignUser" class="form-control">
                            <option value="">— No Change —</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="cm-modal-footer">
                <button class="btn btn-outline" id="btnCancelAudit">Cancel</button>
                <button class="btn btn-primary" id="btnSubmitAudit">
                    <i class="fas fa-check"></i> Submit Audit
                </button>
            </div>
        </div>
    </div>

    <!-- Payment Modal -->
    <div id="modalPayment" class="cm-modal-backdrop" style="display:none">
        <div class="cm-modal">
            <div class="cm-modal-header">
                <h3 class="cm-modal-title" data-i18n="pay.modal.title">Verify Payment</h3>
                <button class="cm-modal-close" id="btnClosePayModal"><i class="fas fa-times"></i></button>
            </div>
            <div class="cm-modal-body">
                <div id="paymentDetails" class="cm-payment-details"></div>
                <form id="paymentForm" novalidate>
                    <input type="hidden" id="payFormId">
                    <input type="hidden" id="payFormRequestId" name="request_id">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Payment Reference</label>
                            <input type="text" id="payFormRef" class="form-control" placeholder="e.g. TR-998877">
                        </div>
                        <div class="form-group">
                            <label>Payment Date</label>
                            <input type="date" id="payFormDate" class="form-control">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Payment Type</label>
                            <select id="payFormType" class="form-control">
                                <option value="initial">Initial</option>
                                <option value="correction">Correction</option>
                                <option value="reissue">Reissue</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Amount (AED)</label>
                            <input type="number" step="0.01" id="payFormAmount" class="form-control" placeholder="0.00">
                            <input type="hidden" id="payFormCurrency" value="AED">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Verification Decision <span class="req">*</span></label>
                            <select id="payFormStatus" class="form-control" required>
                                <option value="waiting_verification">Waiting</option>
                                <option value="verified">Verify (Accept)</option>
                                <option value="rejected">Reject</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Notes</label>
                            <textarea id="payFormNotes" class="form-control" rows="1"></textarea>
                        </div>
                    </div>
                </form>
            </div>
            <div class="cm-modal-footer">
                <button class="btn btn-outline" id="btnCancelPay">Cancel</button>
                <button class="btn btn-primary" id="btnSubmitPay">
                    <i class="fas fa-check-circle"></i> Confirm
                </button>
            </div>
        </div>
    </div>

    <!-- Request Detail Modal -->
    <div id="modalDetail" class="cm-modal-backdrop" style="display:none">
        <div class="cm-modal cm-modal-lg">
            <div class="cm-modal-header">
                <h3 class="cm-modal-title" id="detailModalTitle">Request Details</h3>
                <button class="cm-modal-close" id="btnCloseDetail"><i class="fas fa-times"></i></button>
            </div>
            <div class="cm-modal-body" id="detailModalBody">
                <div class="cm-loading"><div class="cm-spinner"></div></div>
            </div>
            <div class="cm-modal-footer">
                <button class="btn btn-outline" id="btnCloseDetailFooter">Close</button>
                <?php if ($canAudit): ?>
                <button class="btn btn-warning" id="btnAuditFromDetail" style="display:none">
                    <i class="fas fa-search"></i> Audit This Request
                </button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Toast -->
    <div id="cmToast" class="cm-toast" style="display:none"></div>

    <script>
    window.APP_CONFIG = window.APP_CONFIG || {};
    window.APP_CONFIG.TENANT_ID = <?= (int)$tenantId ?>;
    window.USER_LANGUAGE = '<?= addslashes($lang) ?>';

    window.CERT_MGMT_CFG = {
        apiRequests     : '/api/certificates_requests',
        apiAudits       : '/api/certificates_audits',
        apiPayments     : '/api/certificates_payments',
        apiIssued       : '/api/certificates_issued',
        apiLogs         : '/api/certificates_logs',
        apiItems        : '/api/certificates_request_items',
        apiCertificates : '/api/certificates',
        apiEditions     : '/api/certificate_editions',
        apiEntities     : '/api/entities',
        apiProducts     : '/api/certificates_products',
        apiProductsTrans: '/api/certificates_products_translations',
        apiVersions     : '/api/certificates_versions',
        apiAllocations  : '/api/certificate_receipt_allocations',
        apiTenantUsers  : '/api/tenant_users',
        apiFees         : '/api/certificates_fee_rules',
        csrfToken       : '<?= addslashes($csrf) ?>',
        lang            : '<?= addslashes($lang) ?>',
        perPage         : 25,
        perms           : <?= json_encode([
            'canAudit'     => $canAudit,
            'canVerifyPay' => $canVerifyPay,
            'canIssue'     => $canIssue,
            'isSuperAdmin' => is_super_admin(),
        ], JSON_UNESCAPED_UNICODE) ?>
    };
    </script>

</div><!-- /#certMgmtPage -->

<?php if ($isFragment): ?>
<script src="/admin/assets/js/admin_framework.js?v=<?= time() ?>"></script>
<script src="/admin/assets/js/pages/CertificateManagement.js?v=<?= time() ?>"></script>
<script>
(function(){
    let t=0;
    const iv=setInterval(()=>{
        if(window.AdminFramework && window.CertificateManagement?.init){
            clearInterval(iv);
            window.CertificateManagement.init().catch(console.error);
        } else if(++t>80){ clearInterval(iv); console.error('CertificateManagement timeout'); }
    },100);
})();
</script>
<?php else: ?>
<script src="/admin/assets/js/pages/CertificateManagement.js?v=<?= time() ?>"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    window.CertificateManagement?.init().catch(console.error);
});
</script>
<?php endif; ?>

<?php if (!$isFragment) require_once __DIR__ . '/../includes/footer.php'; ?>