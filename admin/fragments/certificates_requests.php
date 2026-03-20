<?php
declare(strict_types=1);
/**
 * /admin/fragments/certificates_requests.php
 * VERSION: 4.0 — ULTRA FAST LOADING
 * Removed source_country_id (not in table), all 21 columns mapped.
 */

$isAjax     = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
$isEmbedded = isset($_GET['embedded']) || isset($_POST['embedded']);
$isFragment = $isAjax || $isEmbedded;

if ($isFragment) { require_once __DIR__ . '/../includes/admin_context.php'; }
else             { require_once __DIR__ . '/../includes/header.php'; }

if (!is_admin_logged_in()) {
    if ($isFragment) { http_response_code(401); echo json_encode(['error' => 'Not authenticated']); exit; }
    header('Location: /admin/login.php'); exit;
}

$user      = admin_user();
$lang      = admin_lang();
$dir       = admin_dir();
$csrf      = admin_csrf();
$tenantId  = admin_tenant_id();

$canCreate    = can_create('certificates_requests')  || can('certificates_requests.manage');
$canEditAll   = can_edit_all('certificates_requests');
$canEditOwn   = can_edit_own('certificates_requests');
$canDeleteAll = can_delete_all('certificates_requests');
$canDeleteOwn = can_delete_own('certificates_requests');
$canViewAll   = can_view_all('certificates_requests');
$canViewOwn   = can_view_own('certificates_requests');
$canViewTenant= can_view_tenant('certificates_requests');
$canView      = $canViewAll || $canViewOwn || $canViewTenant;
$canEdit      = $canEditAll || $canEditOwn || can('certificates_requests.manage');
$canDelete    = $canDeleteAll || $canDeleteOwn || can('certificates_requests.manage');

if (!$canView && !is_super_admin()) {
    if ($isFragment) { http_response_code(403); echo json_encode(['error' => 'Access denied']); exit; }
    die('Access denied');
}

function __t($key, $fallback = '') {
    if (function_exists('i18n_get')) { $v = i18n_get($key); return $v ?? ($fallback ?? $key); }
    return $fallback ?? $key;
}

$isSuperAdmin = is_super_admin();
$colCount     = $isSuperAdmin ? 16 : 15; // for skeleton colspan
?>

<?php if ($isFragment): ?>
<link rel="stylesheet" href="/admin/assets/css/pages/certificates_requests.css?v=<?= time() ?>">
<?php endif; ?>

<!-- ══ PERFORMANCE: Inline critical CSS for instant skeleton ══ -->
<style>
@keyframes cr-shimmer{0%{background-position:-468px 0}100%{background-position:468px 0}}
.cr-skel{display:inline-block;width:100%;height:13px;border-radius:3px;
  background:linear-gradient(to right,#f0f0f0 8%,#e0e0e0 18%,#f0f0f0 33%);
  background-size:936px 104px;animation:cr-shimmer 1.2s linear infinite;}
.cr-skel-row td{padding:10px 12px;}
</style>

<div class="page-container" id="certReqPage" dir="<?= htmlspecialchars($dir) ?>">

    <!-- ══ HEADER ════════════════════════════════════════════════════════ -->
    <div class="page-header">
        <div class="page-header-content">
            <h1 class="page-title" id="pt-title"><?= __t('certificates_requests.title', 'Certificates Requests') ?></h1>
            <p class="page-subtitle" id="pt-subtitle"><?= __t('certificates_requests.subtitle', 'Manage certificate requests') ?></p>
        </div>
        <div class="page-header-actions">
            <?php if ($canCreate || $isSuperAdmin): ?>
            <button id="btnAddRequest" class="btn btn-primary">
                <i class="fas fa-plus"></i>
                <span id="btn-add-label"><?= __t('certificates_requests.add_new', 'Add Request') ?></span>
            </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- ══ FORM ══════════════════════════════════════════════════════════ -->
    <div id="requestFormContainer" class="card form-card" style="display:none">
        <div class="card-header">
            <h3 class="card-title" id="formTitle">Add Request</h3>
            <button type="button" class="btn btn-sm btn-outline" id="btnCloseForm">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="card-body">
            <form id="requestForm" novalidate autocomplete="off">
                <input type="hidden" id="formId" name="id">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">

                <!-- §1 Ownership -->
                <fieldset class="form-section">
                    <legend><i class="fas fa-building"></i> <span data-i18n="form.sections.ownership">Ownership</span></legend>
                    <div class="form-row">
                        <?php if ($isSuperAdmin): ?>
                        <div class="form-group">
                            <label for="fTenantId"><span data-i18n="form.fields.tenant_id.label">Tenant ID</span> <span class="req">*</span></label>
                            <input type="number" id="fTenantId" name="tenant_id" class="form-control"
                                   value="<?= (int)$tenantId ?>" required min="1">
                            <div id="tenantInfo" class="field-hint"></div>
                        </div>
                        <?php else: ?>
                        <input type="hidden" name="tenant_id" value="<?= (int)$tenantId ?>">
                        <?php endif; ?>
                        <div class="form-group">
                            <label for="fEntityId"><span data-i18n="form.fields.entity.label">Entity</span> <span class="req">*</span></label>
                            <select id="fEntityId" name="entity_id" class="form-control" required>
                                <option value="" data-i18n="form.fields.entity.select">Select Entity</option>
                            </select>
                        </div>
                    </div>
                </fieldset>

                <!-- §2 Certificate Definition -->
                <fieldset class="form-section">
                    <legend><i class="fas fa-certificate"></i> <span data-i18n="form.sections.certificate">Certificate Definition</span></legend>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="fCertificateId"><span data-i18n="form.fields.certificate.label">Certificate</span> <span class="req">*</span></label>
                            <select id="fCertificateId" name="certificate_id" class="form-control" required>
                                <option value="" data-i18n="form.fields.certificate.select">Select Certificate</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="fCertificateEditionId"><span data-i18n="form.fields.edition.label">Edition</span></label>
                            <select id="fCertificateEditionId" name="certificate_edition_id" class="form-control">
                                <option value="" data-i18n="form.fields.edition.select">Select Edition</option>
                            </select>
                            <div class="field-hint" id="editionHint"></div>
                        </div>
                        <div class="form-group">
                            <label for="fCertificateType"><span data-i18n="form.fields.certificate_type.label">GCC Scope</span></label>
                            <select id="fCertificateType" name="certificate_type" class="form-control">
                                <option value="gcc"     data-i18n="form.fields.certificate_type.gcc">GCC</option>
                                <option value="non_gcc" data-i18n="form.fields.certificate_type.non_gcc">Non-GCC</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="fOperationType"><span data-i18n="form.fields.operation_type.label">Operation Type</span></label>
                            <select id="fOperationType" name="operation_type" class="form-control">
                                <option value="export"    data-i18n="form.fields.operation_type.export">Export</option>
                                <option value="re_export" data-i18n="form.fields.operation_type.re_export">Re-export</option>
                            </select>
                        </div>
                    </div>
                </fieldset>

                <!-- §3 Importer -->
                <fieldset class="form-section">
                    <legend><i class="fas fa-user-tie"></i> <span data-i18n="form.sections.importer">Importer</span></legend>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="fImporterName"><span data-i18n="form.fields.importer_name.label">Importer Name</span> <span class="req">*</span></label>
                            <input type="text" id="fImporterName" name="importer_name" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="fImporterCountryId"><span data-i18n="form.fields.importer_country.label">Importer Country</span></label>
                            <select id="fImporterCountryId" name="importer_country_id" class="form-control">
                                <option value="" data-i18n="form.fields.importer_country.select">Select Country</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group" style="flex:1">
                            <label for="fImporterAddress"><span data-i18n="form.fields.importer_address.label">Importer Address</span> <span class="req">*</span></label>
                            <textarea id="fImporterAddress" name="importer_address" class="form-control" rows="2" required></textarea>
                        </div>
                    </div>
                </fieldset>

                <!-- §4 Shipment -->
                <fieldset class="form-section">
                    <legend><i class="fas fa-ship"></i> <span data-i18n="form.sections.shipment">Shipment</span></legend>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="fTransportMethod"><span data-i18n="form.fields.transport_method.label">Transport Method</span></label>
                            <select id="fTransportMethod" name="transport_method" class="form-control">
                                <option value="sea"  data-i18n="form.fields.transport_method.sea">Sea</option>
                                <option value="land" data-i18n="form.fields.transport_method.land">Land</option>
                                <option value="air"  data-i18n="form.fields.transport_method.air">Air</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="fShipmentCondition"><span data-i18n="form.fields.shipment_condition.label">Shipment Condition</span></label>
                            <select id="fShipmentCondition" name="shipment_condition" class="form-control">
                                <option value="1" data-i18n="form.fields.shipment_condition.chilled">Chilled</option>
                                <option value="2" data-i18n="form.fields.shipment_condition.dry">Dry</option>
                                <option value="3" data-i18n="form.fields.shipment_condition.frozen">Frozen</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="fIssueDate"><span data-i18n="form.fields.issue_date.label">Issue Date</span></label>
                            <input type="date" id="fIssueDate" name="issue_date" class="form-control">
                        </div>
                    </div>
                </fieldset>

                <!-- §5 Status & Audit -->
                <fieldset class="form-section">
                    <legend><i class="fas fa-tasks"></i> <span data-i18n="form.sections.status">Status &amp; Audit</span></legend>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="fStatus"><span data-i18n="form.fields.status.label">Request Status</span></label>
                            <select id="fStatus" name="status" class="form-control">
                                <option value="draft"           data-i18n="form.fields.status.draft">Draft</option>
                                <option value="under_review"    data-i18n="form.fields.status.under_review">Under Review</option>
                                <option value="payment_pending" data-i18n="form.fields.status.payment_pending">Payment Pending</option>
                                <option value="approved"        data-i18n="form.fields.status.approved">Approved</option>
                                <option value="rejected"        data-i18n="form.fields.status.rejected">Rejected</option>
                                <option value="issued"          data-i18n="form.fields.status.issued">Issued</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="fPaymentStatus"><span data-i18n="form.fields.payment_status.label">Payment Status</span></label>
                            <select id="fPaymentStatus" name="payment_status" class="form-control">
                                <option value=""        data-i18n="form.fields.payment_status.none">— No Payment —</option>
                                <option value="unpaid"  data-i18n="form.fields.payment_status.unpaid">Unpaid</option>
                                <option value="waiting" data-i18n="form.fields.payment_status.waiting">Waiting</option>
                                <option value="paid"    data-i18n="form.fields.payment_status.paid">Paid</option>
                                <option value="rejected"data-i18n="form.fields.payment_status.rejected">Rejected</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="fAuditorUserId"><span data-i18n="form.fields.auditor.label">Assigned Auditor</span></label>
                            <select id="fAuditorUserId" name="auditor_user_id" class="form-control">
                                <option value="" data-i18n="form.fields.auditor.none">No Auditor</option>
                            </select>
                        </div>
                    </div>

                    <!-- Audit info (read-only) -->
                    <div id="auditInfoRow" style="display:none">
                        <hr style="margin:8px 0">
                        <p style="font-size:12px;font-weight:600;color:#64748b;margin-bottom:6px;">
                            <i class="fas fa-search"></i> <span data-i18n="form.audit_info.title">Audit Information</span>
                        </p>
                        <div class="form-row">
                            <div class="form-group">
                                <label data-i18n="form.audit_info.status">Audit Status</label>
                                <div id="auditStatusDisplay" class="form-control-static"></div>
                            </div>
                            <div class="form-group">
                                <label data-i18n="form.audit_info.date">Audit Date</label>
                                <div id="auditDateDisplay" class="form-control-static"></div>
                            </div>
                            <div class="form-group" style="flex:2">
                                <label data-i18n="form.audit_info.notes">Audit Notes</label>
                                <div id="auditNotesDisplay" class="form-control-static"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Issued info (read-only) -->
                    <div id="issuedInfoRow" style="display:none">
                        <hr style="margin:8px 0">
                        <p style="font-size:12px;font-weight:600;color:#64748b;margin-bottom:6px;">
                            <i class="fas fa-stamp"></i> <span data-i18n="form.issued_info.title">Issued Certificate Info</span>
                        </p>
                        <div class="form-row">
                            <div class="form-group">
                                <label data-i18n="form.issued_info.cert_number">Certificate Number</label>
                                <div id="issuedCertNumDisplay" class="form-control-static"></div>
                            </div>
                            <div class="form-group">
                                <label data-i18n="form.issued_info.issued_at">Issued At</label>
                                <div id="issuedAtDisplay" class="form-control-static"></div>
                            </div>
                            <div class="form-group">
                                <label data-i18n="form.issued_info.printable_until">Printable Until</label>
                                <div id="printableUntilDisplay" class="form-control-static"></div>
                            </div>
                        </div>
                    </div>
                </fieldset>

                <!-- §6 Details -->
                <fieldset class="form-section">
                    <legend><i class="fas fa-align-left"></i> <span data-i18n="form.sections.details">Details</span></legend>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="fDescription"><span data-i18n="form.fields.description.label">Description</span></label>
                            <textarea id="fDescription" name="description" class="form-control" rows="3"></textarea>
                        </div>
                        <div class="form-group">
                            <label for="fNotes"><span data-i18n="form.fields.notes.label">Notes</span></label>
                            <textarea id="fNotes" name="notes" class="form-control" rows="3"></textarea>
                        </div>
                    </div>
                </fieldset>

                <!-- §7 Translations -->
                <fieldset class="form-section">
                    <legend><i class="fas fa-language"></i> <span data-i18n="form.sections.translations">Translations</span></legend>
                    <div id="requestTranslations" class="translation-panels"></div>
                    <div style="display:flex;gap:8px;margin-top:10px;align-items:center;">
                        <select id="langSelectRequest" class="form-control" style="flex:1;max-width:240px;">
                            <option value="" data-i18n="form.translations.choose_lang">Choose Language</option>
                        </select>
                        <button type="button" id="addLangBtnRequest" class="btn btn-outline">
                            <i class="fas fa-plus"></i>
                            <span data-i18n="form.translations.add">Add Translation</span>
                        </button>
                    </div>
                </fieldset>

                <!-- §8 Items Table -->
                <fieldset class="form-section">
                    <legend><i class="fas fa-boxes"></i> <span data-i18n="form.sections.items">Request Items (Products)</span></legend>

                    <!-- Product selector + preview -->
                    <div style="display:flex;gap:8px;align-items:center;margin-bottom:8px;flex-wrap:wrap;">
                        <select id="productSelectorForAdd" class="form-control" style="min-width:260px;flex:1;max-width:420px;">
                            <option value="" data-i18n="form.items.select_product">
                                <?php if (true): // products load lazily ?>
                                    Loading products…
                                <?php endif; ?>
                            </option>
                        </select>
                        <button type="button" id="addItemBtn" class="btn btn-secondary">
                            <i class="fas fa-plus"></i>
                            <span data-i18n="form.items.add_item">Add to Table</span>
                        </button>
                    </div>
                    <!-- Preview card injected here by JS -->

                    <div style="overflow-x:auto;">
                        <table id="itemsTable" style="width:100%;border-collapse:collapse;font-size:12px;min-width:860px;">
                            <thead>
                                <tr style="background:#e2e8f0;">
                                    <th class="th-i">#</th>
                                    <th class="th-i" style="min-width:150px;" data-i18n="form.items.product">Product</th>
                                    <th class="th-i" style="min-width:110px;" data-i18n="form.items.brand">Brand</th>
                                    <th class="th-i" style="min-width:140px;" data-i18n="form.items.origin_country">Origin Country</th>
                                    <th class="th-i" style="min-width:80px;"  data-i18n="form.items.quantity">Qty <span class="req">*</span></th>
                                    <th class="th-i" style="min-width:90px;"  data-i18n="form.items.net_weight">Net Wt</th>
                                    <th class="th-i" style="min-width:75px;"  data-i18n="form.items.weight_unit">Unit</th>
                                    <th class="th-i" style="min-width:115px;" data-i18n="form.items.production_date">Prod Date</th>
                                    <th class="th-i" style="min-width:115px;" data-i18n="form.items.expiry_date">Exp Date</th>
                                    <th class="th-i" style="min-width:120px;" data-i18n="form.items.notes">Notes</th>
                                    <th class="th-i" style="width:36px;"></th>
                                </tr>
                            </thead>
                            <tbody id="itemsTableBody">
                                <tr id="itemsEmptyRow">
                                    <td colspan="11" style="text-align:center;color:#94a3b8;padding:28px;font-style:italic;"
                                        data-i18n="form.items.empty">
                                        No items — select a product and click «Add to Table»
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </fieldset>

                <!-- Form actions -->
                <div class="form-actions" style="margin-top:16px;display:flex;gap:10px;">
                    <button type="submit" class="btn btn-primary" id="btnSubmitForm">
                        <i class="fas fa-save"></i>
                        <span id="btn-submit-label" data-i18n="form.buttons.save">Save Request</span>
                    </button>
                    <button type="button" class="btn btn-outline" id="btnCancelForm" data-i18n="form.buttons.cancel">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ══ FILTERS ════════════════════════════════════════════════════════ -->
    <div class="card filter-card">
        <div class="card-body">
            <div class="filters-grid">
                <div class="filter-group">
                    <label data-i18n="filters.search">Search</label>
                    <input type="text" id="searchInput" class="form-control"
                           data-i18n-placeholder="filters.search_placeholder" placeholder="Importer name...">
                </div>
                <?php if ($isSuperAdmin): ?>
                <div class="filter-group">
                    <label data-i18n="filters.tenant_id">Tenant ID</label>
                    <input type="number" id="tenantFilter" class="form-control" value="<?= (int)$tenantId ?>">
                </div>
                <?php endif; ?>
                <div class="filter-group">
                    <label data-i18n="filters.entity">Entity</label>
                    <select id="entityFilter" class="form-control">
                        <option value="" data-i18n="filters.all_entities">All Entities</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label data-i18n="filters.certificate">Certificate</label>
                    <select id="certificateFilter" class="form-control">
                        <option value="" data-i18n="filters.all_certificates">All</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label data-i18n="filters.gcc_type">GCC Type</label>
                    <select id="certTypeFilter" class="form-control">
                        <option value="" data-i18n="filters.all">All</option>
                        <option value="gcc">GCC</option>
                        <option value="non_gcc">Non-GCC</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label data-i18n="filters.status">Status</label>
                    <select id="statusFilter" class="form-control">
                        <option value="" data-i18n="filters.all">All</option>
                        <option value="draft"           data-i18n="form.fields.status.draft">Draft</option>
                        <option value="under_review"    data-i18n="form.fields.status.under_review">Under Review</option>
                        <option value="payment_pending" data-i18n="form.fields.status.payment_pending">Payment Pending</option>
                        <option value="approved"        data-i18n="form.fields.status.approved">Approved</option>
                        <option value="rejected"        data-i18n="form.fields.status.rejected">Rejected</option>
                        <option value="issued"          data-i18n="form.fields.status.issued">Issued</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label data-i18n="filters.payment_status">Payment</label>
                    <select id="payStatusFilter" class="form-control">
                        <option value="" data-i18n="filters.all">All</option>
                        <option value="unpaid">Unpaid</option>
                        <option value="waiting">Waiting</option>
                        <option value="paid">Paid</option>
                        <option value="rejected">Rejected</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label data-i18n="filters.transport">Transport</label>
                    <select id="transportFilter" class="form-control">
                        <option value="" data-i18n="filters.all">All</option>
                        <option value="sea">Sea</option>
                        <option value="land">Land</option>
                        <option value="air">Air</option>
                    </select>
                </div>
                <div class="filter-actions">
                    <button id="btnApplyFilters" class="btn btn-secondary" data-i18n="filters.apply">Apply</button>
                    <button id="btnResetFilters" class="btn btn-outline"   data-i18n="filters.reset">Reset</button>
                </div>
            </div>
        </div>
    </div>

    <div id="resultsCount" style="display:none;margin-bottom:8px;font-size:13px;color:#64748b;">
        <span id="resultsCountText"></span>
    </div>

    <!-- ══ LIST TABLE ═════════════════════════════════════════════════════ -->
    <div class="card table-card">
        <div class="card-body">
            <!-- No separate loading-state div — we show skeleton rows directly in tbody -->
            <div id="tableLoading" class="loading-state" style="display:none">
                <div class="spinner"></div><p>Loading…</p>
            </div>
            <div id="tableContainer" style="display:block">
                <div class="table-responsive">
                    <table class="data-table" id="requestsTable">
                        <thead>
                            <tr>
                                <th data-i18n="table.headers.id">ID</th>
                                <?php if ($isSuperAdmin): ?>
                                <th data-i18n="table.headers.tenant">Tenant</th>
                                <?php endif; ?>
                                <th data-i18n="table.headers.entity">Entity</th>
                                <th data-i18n="table.headers.certificate">Certificate</th>
                                <th data-i18n="table.headers.edition">Edition</th>
                                <th data-i18n="table.headers.importer">Importer</th>
                                <th data-i18n="table.headers.country">Country</th>
                                <th data-i18n="table.headers.gcc">GCC</th>
                                <th data-i18n="table.headers.operation">Operation</th>
                                <th data-i18n="table.headers.shipment">Shipment</th>
                                <th data-i18n="table.headers.transport">Transport</th>
                                <th data-i18n="table.headers.status">Status</th>
                                <th data-i18n="table.headers.payment">Payment</th>
                                <th data-i18n="table.headers.issue_date">Issue Date</th>
                                <th data-i18n="table.headers.created">Created</th>
                                <th data-i18n="table.headers.actions">Actions</th>
                            </tr>
                        </thead>
                        <!-- Pre-rendered skeleton rows = instant visual feedback -->
                        <tbody id="tableBody">
                            <?php for ($i = 0; $i < 6; $i++): ?>
                            <tr class="cr-skel-row">
                                <?php for ($j = 0; $j < $colCount; $j++): ?>
                                <td><div class="cr-skel"></div></td>
                                <?php endfor; ?>
                            </tr>
                            <?php endfor; ?>
                        </tbody>
                    </table>
                </div>
                <div class="pagination-wrapper">
                    <div class="pagination-info">
                        <span data-i18n="pagination.showing">Showing</span>
                        <span id="paginationInfo">…</span>
                    </div>
                    <div class="pagination" id="pagination"></div>
                </div>
            </div>

            <div id="emptyState" class="empty-state" style="display:none">
                <div class="empty-icon">📄</div>
                <h3 data-i18n="table.empty.title">No Requests Found</h3>
                <p data-i18n="table.empty.message">Start by adding a certificate request</p>
                <?php if ($canCreate || $isSuperAdmin): ?>
                <button class="btn btn-primary" onclick="CertificatesRequests.add()">
                    <i class="fas fa-plus"></i>
                    <span data-i18n="table.empty.add_first">Add First Request</span>
                </button>
                <?php endif; ?>
            </div>

            <div id="errorState" class="error-state" style="display:none">
                <div class="error-icon">⚠️</div>
                <h3 data-i18n="messages.error.load_failed">Error Loading Data</h3>
                <p id="errorMessage"></p>
                <button id="btnRetry" class="btn btn-secondary" data-i18n="certificates_requests.retry">Retry</button>
            </div>
        </div>
    </div>
</div><!-- /#certReqPage -->

<style>
.form-section{border:1px solid #e2e8f0;border-radius:8px;padding:14px 16px;margin-bottom:14px;}
.form-section legend{font-size:13px;font-weight:600;color:#475569;padding:0 8px;}
.form-section .req{color:#ef4444;}
.field-hint{font-size:11px;margin-top:3px;}
.form-control-static{padding:5px 8px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:5px;font-size:13px;min-height:32px;}
.translation-panel{border:1px solid #e2e8f0;border-radius:6px;padding:12px;margin-bottom:10px;background:#fafafa;}
.translation-panel-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;}
.translation-panel-header h5{margin:0;font-size:13px;font-weight:600;}
.th-i{padding:7px 8px;text-align:left;font-size:11px;font-weight:600;color:#475569;white-space:nowrap;border-bottom:2px solid #cbd5e1;}
#itemsTable td{padding:3px 5px;border-bottom:1px solid #f1f5f9;vertical-align:middle;}
#itemsTable tbody tr:hover{background:#f8fafc;}
#itemsTable input,#itemsTable select{padding:3px 5px;font-size:12px;border:1px solid #cbd5e1;border-radius:4px;width:100%;box-sizing:border-box;background:#fff;height:27px;}
#itemsTable input:focus,#itemsTable select:focus{border-color:#3b82f6;outline:none;box-shadow:0 0 0 2px rgba(59,130,246,.15);}
.badge{display:inline-block;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:600;}
.badge-gcc,.badge-non_gcc{background:#ede9fe;color:#5b21b6;}
.badge-draft{background:#f1f5f9;color:#475569;}
.badge-under_review{background:#fef3c7;color:#92400e;}
.badge-payment_pending{background:#fde8d8;color:#9a3412;}
.badge-approved{background:#dcfce7;color:#166534;}
.badge-rejected{background:#fee2e2;color:#991b1b;}
.badge-issued{background:#dbeafe;color:#1e40af;}
.badge-unpaid{background:#fee2e2;color:#991b1b;}
.badge-waiting{background:#fef3c7;color:#92400e;}
.badge-paid{background:#dcfce7;color:#166534;}
</style>

<script>
window.APP_CONFIG = window.APP_CONFIG || {};
window.APP_CONFIG.TENANT_ID = <?= (int)$tenantId ?>;
window.USER_LANGUAGE        = '<?= addslashes($lang) ?>';
window.PAGE_PERMISSIONS     = <?= json_encode([
    'canCreate'    => $canCreate,
    'canEdit'      => $canEdit,
    'canDelete'    => $canDelete,
    'canViewAll'   => $canViewAll,
    'canViewOwn'   => $canViewOwn,
    'canViewTenant'=> $canViewTenant,
    'canEditAll'   => $canEditAll,
    'canEditOwn'   => $canEditOwn,
    'canDeleteAll' => $canDeleteAll,
    'canDeleteOwn' => $canDeleteOwn,
    'isSuperAdmin' => $isSuperAdmin,
], JSON_UNESCAPED_UNICODE) ?>;

window.CERT_REQ_CFG = {
    apiRequests            : '/api/certificates_requests',
    apiTranslations        : '/api/certificates_requests_translations',
    apiItems               : '/api/certificates_request_items',
    apiCertificates        : '/api/certificates',
    apiEditions            : '/api/certificate_editions',
    apiIssued              : '/api/certificates_issued',
    apiAudits              : '/api/certificates_audits',
    apiEntities            : '/api/entities',
    apiProducts            : '/api/certificates_products',
    apiProductsTranslations: '/api/certificates_products_translations',
    apiUnits               : '/api/units',
    apiLanguages           : '/api/languages',
    apiTenants             : '/api/tenants',
    apiCountries           : '/api/countries',
    apiBrands              : '/api/brands',
    csrfToken              : '<?= addslashes($csrf) ?>',
    lang                   : '<?= addslashes($lang) ?>',
    itemsPerPage           : 25
};
</script>

<?php if ($isFragment): ?>
<script src="/admin/assets/js/admin_framework.js?v=<?= time() ?>"></script>
<script src="/admin/assets/js/pages/certificates_requests.js?v=<?= time() ?>"></script>
<script>
(function () {
    let t = 0;
    const iv = setInterval(() => {
        if (window.AdminFramework && window.CertificatesRequests?.init) {
            clearInterval(iv);
            window.CertificatesRequests.init().catch(console.error);
        } else if (++t > 80) {
            clearInterval(iv);
            console.error('CertificatesRequests timeout');
        }
    }, 100);
})();
</script>
<?php else: ?>
<script src="/admin/assets/js/pages/certificates_requests.js?v=<?= time() ?>"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    window.CertificatesRequests?.init().catch(console.error);
});
</script>
<?php endif; ?>

<?php if (!$isFragment) require_once __DIR__ . '/../includes/footer.php'; ?>