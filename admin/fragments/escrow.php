<?php
declare(strict_types=1);

/**
 * /admin/fragments/escrow.php
 * Escrow Management – Admin Fragment
 *
 * ✅ Uses role-based + resource-based permissions
 * ✅ Compatible with tenant_users table
 * ✅ Full multi-language translation support
 * ✅ Relations: currencies, orders, entity_types, entities
 * ✅ Production-ready with all APIs integrated
 */

// ════════════════════════════════════════════════════════════
// DETECT REQUEST TYPE
// ════════════════════════════════════════════════════════════
$isAjax     = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
              strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
$isEmbedded = isset($_GET['embedded']) || isset($_POST['embedded']);
$isFragment = $isAjax || $isEmbedded;

// ════════════════════════════════════════════════════════════
// LOAD CONTEXT / HEADER
// ════════════════════════════════════════════════════════════
if ($isFragment) {
    require_once __DIR__ . '/../includes/admin_context.php';
} else {
    require_once __DIR__ . '/../includes/header.php';
}

// ════════════════════════════════════════════════════════════
// VERIFY USER IS LOGGED IN
// ════════════════════════════════════════════════════════════
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

// ════════════════════════════════════════════════════════════
// GET USER CONTEXT & PERMISSIONS
// ════════════════════════════════════════════════════════════
$user     = admin_user();
$lang     = admin_lang();
$dir      = admin_dir();
$csrf     = admin_csrf();
$tenantId = admin_tenant_id();

// ════════════════════════════════════════════════════════════
// CHECK PERMISSIONS
// ════════════════════════════════════════════════════════════

// Method 1: Role-based permissions
$canManageEscrow = can('escrow.manage') || can('escrow.create');

// Method 2: Resource-based permissions
$canViewAll    = can_view_all('escrow');
$canViewOwn    = can_view_own('escrow');
$canViewTenant = can_view_tenant('escrow');
$canCreate     = can_create('escrow')  || $canManageEscrow;
$canEditAll    = can_edit_all('escrow');
$canEditOwn    = can_edit_own('escrow');
$canDeleteAll  = can_delete_all('escrow');
$canDeleteOwn  = can_delete_own('escrow');

// Combined permissions for UI
$canView   = $canViewAll || $canViewOwn || $canViewTenant || $canManageEscrow || is_super_admin();
$canEdit   = $canEditAll || $canEditOwn || $canManageEscrow || is_super_admin();
$canDelete = $canDeleteAll || $canDeleteOwn || $canManageEscrow || is_super_admin();

if (!$canView) {
    if ($isFragment) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        exit;
    }
    http_response_code(403);
    die('Access denied: You do not have permission to view escrow transactions');
}

// ════════════════════════════════════════════════════════════
// TRANSLATION HELPERS
// ════════════════════════════════════════════════════════════
if (!function_exists('__escrow_t')) {
    function __escrow_t($key, $fallback = '') {
        if (function_exists('i18n_get')) {
            $v = i18n_get($key);
            return $v ?? ($fallback ?? $key);
        }
        return $fallback ?? $key;
    }
}

if (!function_exists('__escrow_tr')) {
    function __escrow_tr($key, $replacements = []) {
        $text = __escrow_t($key, $key);
        foreach ($replacements as $ph => $val) {
            $text = str_replace('{' . $ph . '}', (string)$val, $text);
        }
        return $text;
    }
}

// ════════════════════════════════════════════════════════════
// API BASE
// ════════════════════════════════════════════════════════════
$apiBase = '/api';

// ════════════════════════════════════════════════════════════
// DB-DRIVEN CSS VARS HELPER (Escrow)
// ════════════════════════════════════════════════════════════
if (!function_exists('renderEscrowFragmentThemeVars')) {
    function renderEscrowFragmentThemeVars(array $theme): void {
        echo ':root {' . PHP_EOL;
        foreach ($theme['color_settings'] ?? [] as $c) {
            if (empty($c['setting_key']) || !isset($c['color_value'])) continue;
            $k = htmlspecialchars($c['setting_key'], ENT_QUOTES);
            $h = htmlspecialchars(str_replace('_', '-', $c['setting_key']), ENT_QUOTES);
            $v = htmlspecialchars($c['color_value'], ENT_QUOTES);
            echo "    --{$k}: {$v};" . PHP_EOL;
            if ($h !== $k) echo "    --{$h}: {$v};" . PHP_EOL;
        }
        foreach ($theme['font_settings'] ?? [] as $f) {
            if (empty($f['setting_key'])) continue;
            $sk = htmlspecialchars($f['setting_key'], ENT_QUOTES);
            $sh = htmlspecialchars(str_replace('_', '-', $f['setting_key']), ENT_QUOTES);
            if (!empty($f['font_family'])) {
                $ff = htmlspecialchars($f['font_family'], ENT_QUOTES);
                echo "    --{$sk}-family: {$ff};" . PHP_EOL;
                if ($sh !== $sk) echo "    --{$sh}-family: {$ff};" . PHP_EOL;
            }
            if (!empty($f['font_size'])) {
                $fs = htmlspecialchars($f['font_size'], ENT_QUOTES);
                echo "    --{$sk}-size: {$fs};" . PHP_EOL;
                if ($sh !== $sk) echo "    --{$sh}-size: {$fs};" . PHP_EOL;
            }
            if (!empty($f['font_weight'])) {
                $fw = htmlspecialchars($f['font_weight'], ENT_QUOTES);
                echo "    --{$sk}-weight: {$fw};" . PHP_EOL;
                if ($sh !== $sk) echo "    --{$sh}-weight: {$fw};" . PHP_EOL;
            }
        }
        foreach ($theme['design_settings'] ?? [] as $d) {
            if (empty($d['setting_key']) || !isset($d['setting_value'])) continue;
            $dk = htmlspecialchars($d['setting_key'], ENT_QUOTES);
            $dh = htmlspecialchars(str_replace('_', '-', $d['setting_key']), ENT_QUOTES);
            $dv = htmlspecialchars($d['setting_value'], ENT_QUOTES);
            echo "    --{$dk}: {$dv};" . PHP_EOL;
            if ($dh !== $dk) echo "    --{$dh}: {$dv};" . PHP_EOL;
        }
        foreach ($theme['button_styles'] ?? [] as $b) {
            if (empty($b['slug'])) continue;
            $slug = preg_replace('/[^a-z0-9_-]/', '-', strtolower((string)$b['slug']));
            if (!empty($b['background_color'])) echo "    --btn-{$slug}-bg: " . htmlspecialchars($b['background_color'], ENT_QUOTES) . ';' . PHP_EOL;
            if (!empty($b['text_color']))       echo "    --btn-{$slug}-color: " . htmlspecialchars($b['text_color'], ENT_QUOTES) . ';' . PHP_EOL;
            if (!empty($b['border_color']))     echo "    --btn-{$slug}-border: " . htmlspecialchars($b['border_color'], ENT_QUOTES) . ';' . PHP_EOL;
            if (!empty($b['border_radius']))    echo "    --btn-{$slug}-radius: " . htmlspecialchars((string)$b['border_radius'], ENT_QUOTES) . 'px;' . PHP_EOL;
        }
        foreach ($theme['card_styles'] ?? [] as $cs) {
            if (empty($cs['slug'])) continue;
            $slug = preg_replace('/[^a-z0-9_-]/', '-', strtolower((string)$cs['slug']));
            if (!empty($cs['background_color'])) echo "    --card-{$slug}-bg: " . htmlspecialchars($cs['background_color'], ENT_QUOTES) . ';' . PHP_EOL;
            if (!empty($cs['border_color']))     echo "    --card-{$slug}-border: " . htmlspecialchars($cs['border_color'], ENT_QUOTES) . ';' . PHP_EOL;
            if (!empty($cs['border_radius']))    echo "    --card-{$slug}-radius: " . htmlspecialchars((string)$cs['border_radius'], ENT_QUOTES) . 'px;' . PHP_EOL;
            if (!empty($cs['shadow_style']))     echo "    --card-{$slug}-shadow: " . htmlspecialchars($cs['shadow_style'], ENT_QUOTES) . ';' . PHP_EOL;
            if (!empty($cs['padding']))          echo "    --card-{$slug}-padding: " . htmlspecialchars($cs['padding'], ENT_QUOTES) . ';' . PHP_EOL;
        }
        echo '}' . PHP_EOL;
    }
}

?>
<!-- DB-driven CSS vars (all settings, colors, fonts, cards, buttons from database) -->
<style id="db-theme-vars-escrow">
<?php renderEscrowFragmentThemeVars($GLOBALS['ADMIN_UI']['theme'] ?? []); ?>
<?php if (!empty($GLOBALS['ADMIN_UI']['theme']['generated_css'])): ?>
<?= $GLOBALS['ADMIN_UI']['theme']['generated_css'] ?>
<?php endif; ?>
</style>
<!-- Structural layout CSS (uses only var() for all visual properties) -->
<link rel="stylesheet" href="/admin/assets/css/pages/escrow.css?v=<?= time() ?>">

<!-- Page Meta -->
<meta data-page="escrow"
      data-assets-css="/admin/assets/css/pages/escrow.css"
      data-i18n-files="/languages/escrow/<?= rawurlencode($lang) ?>.json">

<!-- Page Container -->
<div class="page-container" id="escrowPageContainer" dir="<?= htmlspecialchars($dir) ?>">

    <!-- Page Header -->
    <div class="page-header">
        <div class="page-header-content">
            <h1 class="page-title" data-i18n="escrow.title"><?= __escrow_t('escrow.title', 'Escrow Transactions') ?></h1>
            <p class="page-subtitle" data-i18n="escrow.subtitle"><?= __escrow_t('escrow.subtitle', 'Manage escrow transactions, disputes and ledger') ?></p>
        </div>
        <?php if ($canCreate): ?>
        <div class="page-header-actions">
            <button id="esc-btnAdd" class="btn btn-primary">
                <i class="fas fa-plus"></i>
                <span data-i18n="escrow.add_new"><?= __escrow_t('escrow.add_new', 'New Escrow') ?></span>
            </button>
        </div>
        <?php endif; ?>
    </div>

    <!-- Form Container -->
    <div id="esc-formContainer" class="card form-card" style="display:none">
        <div class="card-header">
            <h3 class="card-title" id="esc-formTitle" data-i18n="form.add_title"><?= __escrow_t('form.add_title', 'New Escrow Transaction') ?></h3>
            <button type="button" class="btn btn-sm btn-outline" id="esc-btnCloseForm"
                    aria-label="<?= __escrow_t('accessibility.close', 'Close') ?>">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="card-body">
            <form id="esc-form" novalidate>
                <!-- Hidden Fields -->
                <input type="hidden" id="esc-formId"   name="id">
                <input type="hidden" name="csrf_token"  value="<?= htmlspecialchars($csrf) ?>">
                <input type="hidden" name="tenant_id"   value="<?= $tenantId ?>">

                <!-- Tabs Navigation -->
                <div class="form-tabs">
                    <button type="button" class="tab-btn active" data-tab="details">
                        <i class="fas fa-info-circle"></i>
                        <span data-i18n="form.tabs.details"><?= __escrow_t('form.tabs.details', 'Details') ?></span>
                    </button>
                    <button type="button" class="tab-btn" data-tab="parties">
                        <i class="fas fa-users"></i>
                        <span data-i18n="form.tabs.parties"><?= __escrow_t('form.tabs.parties', 'Parties') ?></span>
                    </button>
                    <button type="button" class="tab-btn" data-tab="disputes">
                        <i class="fas fa-exclamation-triangle"></i>
                        <span data-i18n="form.tabs.disputes"><?= __escrow_t('form.tabs.disputes', 'Disputes') ?></span>
                    </button>
                    <button type="button" class="tab-btn" data-tab="history">
                        <i class="fas fa-history"></i>
                        <span data-i18n="form.tabs.history"><?= __escrow_t('form.tabs.history', 'Status History') ?></span>
                    </button>
                    <button type="button" class="tab-btn" data-tab="ledger">
                        <i class="fas fa-book"></i>
                        <span data-i18n="form.tabs.ledger"><?= __escrow_t('form.tabs.ledger', 'Ledger') ?></span>
                    </button>
                </div>

                <!-- ═══════════════════════════ Tab: Details ═══════════════════════════ -->
                <div class="tab-content active" id="esc-tab-details" style="display:block">

                    <div class="form-row">
                        <div class="form-group">
                            <label for="esc-escrowNumber" data-i18n="form.fields.escrow_number.label">
                                <?= __escrow_t('form.fields.escrow_number.label', 'Escrow Number') ?>
                            </label>
                            <input type="text" id="esc-escrowNumber" name="escrow_number" class="form-control"
                                   data-i18n-placeholder="form.fields.escrow_number.placeholder"
                                   placeholder="<?= __escrow_t('form.fields.escrow_number.placeholder', 'Auto-generated if empty') ?>"
                                   readonly>
                        </div>
                        <div class="form-group">
                            <label for="esc-status" data-i18n="form.fields.status.label">
                                <?= __escrow_t('form.fields.status.label', 'Status') ?>
                            </label>
                            <select id="esc-status" name="status" class="form-control">
                                <option value="pending"    data-i18n="status.pending"><?= __escrow_t('status.pending', 'Pending') ?></option>
                                <option value="funded"     data-i18n="status.funded"><?= __escrow_t('status.funded', 'Funded') ?></option>
                                <option value="in_transit" data-i18n="status.in_transit"><?= __escrow_t('status.in_transit', 'In Transit') ?></option>
                                <option value="delivered"  data-i18n="status.delivered"><?= __escrow_t('status.delivered', 'Delivered') ?></option>
                                <option value="released"   data-i18n="status.released"><?= __escrow_t('status.released', 'Released') ?></option>
                                <option value="disputed"   data-i18n="status.disputed"><?= __escrow_t('status.disputed', 'Disputed') ?></option>
                                <option value="refunded"   data-i18n="status.refunded"><?= __escrow_t('status.refunded', 'Refunded') ?></option>
                                <option value="cancelled"  data-i18n="status.cancelled"><?= __escrow_t('status.cancelled', 'Cancelled') ?></option>
                            </select>
                        </div>
                    </div>

                    <!-- Order linkage -->
                    <div class="form-row">
                        <div class="form-group">
                            <label for="esc-orderId" data-i18n="form.fields.order_id.label">
                                <?= __escrow_t('form.fields.order_id.label', 'Linked Order') ?>
                            </label>
                            <select id="esc-orderId" name="order_id" class="form-control">
                                <option value=""><?= __escrow_t('form.fields.order_id.select', 'Select order (optional)') ?></option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="esc-autoReleaseDays" data-i18n="form.fields.auto_release_days.label">
                                <?= __escrow_t('form.fields.auto_release_days.label', 'Auto-Release Days') ?>
                            </label>
                            <input type="number" id="esc-autoReleaseDays" name="auto_release_days"
                                   class="form-control" min="1" value="7">
                        </div>
                    </div>

                    <!-- Amount & Currency (linked to currencies table) -->
                    <div class="form-row">
                        <div class="form-group">
                            <label for="esc-amount" data-i18n="form.fields.amount.label">
                                <?= __escrow_t('form.fields.amount.label', 'Amount') ?> <span class="required">*</span>
                            </label>
                            <input type="number" id="esc-amount" name="amount" class="form-control"
                                   step="0.01" min="0" required>
                            <div class="invalid-feedback" data-i18n="form.fields.amount.required">
                                <?= __escrow_t('form.fields.amount.required', 'Amount is required') ?>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="esc-escrowFee" data-i18n="form.fields.escrow_fee.label">
                                <?= __escrow_t('form.fields.escrow_fee.label', 'Escrow Fee') ?>
                            </label>
                            <input type="number" id="esc-escrowFee" name="escrow_fee" class="form-control"
                                   step="0.01" min="0" value="0">
                        </div>
                        <div class="form-group">
                            <label for="esc-currencyCode" data-i18n="form.fields.currency_code.label">
                                <?= __escrow_t('form.fields.currency_code.label', 'Currency') ?>
                            </label>
                            <select id="esc-currencyCode" name="currency_code" class="form-control">
                                <option value=""><?= __escrow_t('form.fields.currency_code.select', 'Select currency') ?></option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="esc-notes" data-i18n="form.fields.notes.label">
                            <?= __escrow_t('form.fields.notes.label', 'Notes') ?>
                        </label>
                        <textarea id="esc-notes" name="notes" class="form-control" rows="3"
                                  data-i18n-placeholder="form.fields.notes.placeholder"
                                  placeholder="<?= __escrow_t('form.fields.notes.placeholder', 'Additional notes…') ?>"></textarea>
                    </div>
                </div>

                <!-- ═══════════════════════════ Tab: Parties ═══════════════════════════ -->
                <div class="tab-content" id="esc-tab-parties" style="display:none">
                    <p style="color:var(--text-secondary,#94a3b8); font-size:0.85rem; margin-bottom:16px;" data-i18n="form.parties.info">
                        <?= __escrow_t('form.parties.info', 'Select the buyer and seller entities involved in this escrow transaction.') ?>
                    </p>

                    <!-- Buyer -->
                    <div style="border:1px solid rgba(255,255,255,0.1); border-radius:8px; padding:16px; margin-bottom:16px;">
                        <h4 style="color:var(--text-primary,#fff); margin-bottom:12px; display:flex; align-items:center; gap:8px;">
                            <i class="fas fa-user-tag" style="color:var(--primary-color,#3b82f6);"></i>
                            <span data-i18n="form.sections.buyer"><?= __escrow_t('form.sections.buyer', 'Buyer') ?></span>
                        </h4>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="esc-buyerEntityType" data-i18n="form.fields.buyer_entity_type.label">
                                    <?= __escrow_t('form.fields.buyer_entity_type.label', 'Buyer Entity Type') ?>
                                    <span class="required">*</span>
                                </label>
                                <select id="esc-buyerEntityType" name="buyer_entity_type" class="form-control" required>
                                    <option value=""><?= __escrow_t('form.fields.buyer_entity_type.select', 'Select entity type') ?></option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="esc-buyerEntityId" data-i18n="form.fields.buyer_entity_id.label">
                                    <?= __escrow_t('form.fields.buyer_entity_id.label', 'Buyer Entity') ?>
                                    <span class="required">*</span>
                                </label>
                                <select id="esc-buyerEntityId" name="buyer_entity_id" class="form-control" required>
                                    <option value=""><?= __escrow_t('form.fields.buyer_entity_id.select', 'Select buyer') ?></option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Seller -->
                    <div style="border:1px solid rgba(255,255,255,0.1); border-radius:8px; padding:16px;">
                        <h4 style="color:var(--text-primary,#fff); margin-bottom:12px; display:flex; align-items:center; gap:8px;">
                            <i class="fas fa-store" style="color:var(--success-color,#22c55e);"></i>
                            <span data-i18n="form.sections.seller"><?= __escrow_t('form.sections.seller', 'Seller') ?></span>
                        </h4>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="esc-sellerEntityType" data-i18n="form.fields.seller_entity_type.label">
                                    <?= __escrow_t('form.fields.seller_entity_type.label', 'Seller Entity Type') ?>
                                    <span class="required">*</span>
                                </label>
                                <select id="esc-sellerEntityType" name="seller_entity_type" class="form-control" required>
                                    <option value=""><?= __escrow_t('form.fields.seller_entity_type.select', 'Select entity type') ?></option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="esc-sellerEntityId" data-i18n="form.fields.seller_entity_id.label">
                                    <?= __escrow_t('form.fields.seller_entity_id.label', 'Seller Entity') ?>
                                    <span class="required">*</span>
                                </label>
                                <select id="esc-sellerEntityId" name="seller_entity_id" class="form-control" required>
                                    <option value=""><?= __escrow_t('form.fields.seller_entity_id.select', 'Select seller') ?></option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ═══════════════════════════ Tab: Disputes ═══════════════════════════ -->
                <div class="tab-content" id="esc-tab-disputes" style="display:none">
                    <div id="esc-disputesList">
                        <p style="color:var(--text-secondary,#94a3b8);text-align:center;padding:20px" data-i18n="disputes.empty">
                            <?= __escrow_t('disputes.empty', 'No disputes for this escrow') ?>
                        </p>
                    </div>
                </div>

                <!-- ═══════════════════════════ Tab: Status History ═══════════════════════════ -->
                <div class="tab-content" id="esc-tab-history" style="display:none">
                    <div id="esc-historyList">
                        <p style="color:var(--text-secondary,#94a3b8);text-align:center;padding:20px" data-i18n="history.empty">
                            <?= __escrow_t('history.empty', 'No status history yet') ?>
                        </p>
                    </div>
                </div>

                <!-- ═══════════════════════════ Tab: Ledger ═══════════════════════════ -->
                <div class="tab-content" id="esc-tab-ledger" style="display:none">
                    <div id="esc-ledgerList">
                        <p style="color:var(--text-secondary,#94a3b8);text-align:center;padding:20px" data-i18n="ledger.empty">
                            <?= __escrow_t('ledger.empty', 'No ledger entries yet') ?>
                        </p>
                    </div>
                </div>

                <div class="form-actions">
                    <?php if ($canEdit): ?>
                    <button type="submit" class="btn btn-primary" id="esc-btnSave">
                        <i class="fas fa-save"></i>
                        <span data-i18n="form.buttons.save"><?= __escrow_t('form.buttons.save', 'Save') ?></span>
                    </button>
                    <?php endif; ?>
                    <button type="button" class="btn btn-outline" id="esc-btnCancelForm" data-i18n="form.buttons.cancel">
                        <?= __escrow_t('form.buttons.cancel', 'Cancel') ?>
                    </button>
                    <?php if ($canDelete): ?>
                    <button type="button" id="esc-btnDelete" class="btn btn-danger" style="display:none">
                        <i class="fas fa-trash"></i>
                        <span data-i18n="form.buttons.delete"><?= __escrow_t('form.buttons.delete', 'Delete') ?></span>
                    </button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Filters -->
    <div class="card filter-card">
        <div class="card-body">
            <div class="filters-grid">
                <div class="filter-group">
                    <label for="esc-searchInput" data-i18n="filters.search">
                        <?= __escrow_t('filters.search', 'Search') ?>
                    </label>
                    <input type="text" id="esc-searchInput" class="form-control"
                           data-i18n-placeholder="filters.search_placeholder"
                           placeholder="<?= __escrow_t('filters.search_placeholder', 'Search by escrow number…') ?>">
                </div>
                <div class="filter-group">
                    <label for="esc-statusFilter" data-i18n="filters.status">
                        <?= __escrow_t('filters.status', 'Status') ?>
                    </label>
                    <select id="esc-statusFilter" class="form-control">
                        <option value="" data-i18n="filters.all_statuses"><?= __escrow_t('filters.all_statuses', 'All Statuses') ?></option>
                        <option value="pending"    data-i18n="status.pending"><?= __escrow_t('status.pending', 'Pending') ?></option>
                        <option value="funded"     data-i18n="status.funded"><?= __escrow_t('status.funded', 'Funded') ?></option>
                        <option value="in_transit" data-i18n="status.in_transit"><?= __escrow_t('status.in_transit', 'In Transit') ?></option>
                        <option value="delivered"  data-i18n="status.delivered"><?= __escrow_t('status.delivered', 'Delivered') ?></option>
                        <option value="released"   data-i18n="status.released"><?= __escrow_t('status.released', 'Released') ?></option>
                        <option value="disputed"   data-i18n="status.disputed"><?= __escrow_t('status.disputed', 'Disputed') ?></option>
                        <option value="refunded"   data-i18n="status.refunded"><?= __escrow_t('status.refunded', 'Refunded') ?></option>
                        <option value="cancelled"  data-i18n="status.cancelled"><?= __escrow_t('status.cancelled', 'Cancelled') ?></option>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="esc-currencyFilter" data-i18n="filters.currency">
                        <?= __escrow_t('filters.currency', 'Currency') ?>
                    </label>
                    <select id="esc-currencyFilter" class="form-control">
                        <option value="" data-i18n="filters.all_currencies"><?= __escrow_t('filters.all_currencies', 'All Currencies') ?></option>
                    </select>
                </div>
                <div class="filter-actions">
                    <button id="esc-btnApplyFilters" class="btn btn-secondary" data-i18n="filters.apply">
                        <?= __escrow_t('filters.apply', 'Apply Filters') ?>
                    </button>
                    <button id="esc-btnResetFilters" class="btn btn-outline" data-i18n="filters.reset">
                        <?= __escrow_t('filters.reset', 'Reset') ?>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Table -->
    <div class="card table-card">
        <div class="card-body">
            <div id="esc-tableLoading" class="loading-state">
                <div class="spinner"></div>
                <p data-i18n="escrow.loading"><?= __escrow_t('escrow.loading', 'Loading escrow transactions…') ?></p>
            </div>
            <div id="esc-tableContainer" style="display:none">
                <div class="table-responsive">
                    <table class="data-table" id="esc-table">
                        <thead>
                            <tr>
                                <th data-i18n="table.headers.id"><?= __escrow_t('table.headers.id', 'ID') ?></th>
                                <th data-i18n="table.headers.escrow_number"><?= __escrow_t('table.headers.escrow_number', 'Escrow #') ?></th>
                                <th data-i18n="table.headers.order"><?= __escrow_t('table.headers.order', 'Order') ?></th>
                                <th data-i18n="table.headers.buyer"><?= __escrow_t('table.headers.buyer', 'Buyer') ?></th>
                                <th data-i18n="table.headers.seller"><?= __escrow_t('table.headers.seller', 'Seller') ?></th>
                                <th data-i18n="table.headers.amount"><?= __escrow_t('table.headers.amount', 'Amount') ?></th>
                                <th data-i18n="table.headers.status"><?= __escrow_t('table.headers.status', 'Status') ?></th>
                                <th data-i18n="table.headers.created_at"><?= __escrow_t('table.headers.created_at', 'Created') ?></th>
                                <th data-i18n="table.headers.actions"><?= __escrow_t('table.headers.actions', 'Actions') ?></th>
                            </tr>
                        </thead>
                        <tbody id="esc-tableBody"></tbody>
                    </table>
                </div>
                <div class="pagination-wrapper">
                    <div class="pagination-info"><span id="esc-paginationInfo">0-0 / 0</span></div>
                    <div class="pagination" id="esc-pagination"></div>
                </div>
            </div>
            <div id="esc-emptyState" class="empty-state" style="display:none">
                <div class="empty-icon">🔒</div>
                <h3 data-i18n="table.empty.title"><?= __escrow_t('table.empty.title', 'No Escrow Transactions Found') ?></h3>
                <p data-i18n="table.empty.message"><?= __escrow_t('table.empty.message', 'There are no escrow transactions matching your criteria.') ?></p>
                <?php if ($canCreate): ?>
                <button class="btn btn-primary" id="esc-btnAddFirst"
                        onclick="document.getElementById('esc-btnAdd')?.click()"
                        data-i18n="table.empty.add_first">
                    <?= __escrow_t('table.empty.add_first', 'Create First Escrow') ?>
                </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Expose client-side globals for the module -->
<script type="text/javascript">
window.APP_CONFIG = window.APP_CONFIG || {};
window.APP_CONFIG.API_BASE   = window.APP_CONFIG.API_BASE   || '<?= $apiBase ?>';
window.APP_CONFIG.TENANT_ID  = window.APP_CONFIG.TENANT_ID  || <?= $tenantId ?>;
window.APP_CONFIG.CSRF_TOKEN = window.APP_CONFIG.CSRF_TOKEN || '<?= addslashes($csrf) ?>';
window.APP_CONFIG.USER_ID    = window.APP_CONFIG.USER_ID    || <?= admin_user_id() ?>;

window.USER_LANGUAGE = window.USER_LANGUAGE || '<?= addslashes($lang) ?>';
window.USER_DIRECTION = window.USER_DIRECTION || '<?= addslashes($dir) ?>';
window.CSRF_TOKEN    = window.CSRF_TOKEN    || '<?= addslashes($csrf) ?>';

if (!window.ADMIN_UI) {
    window.ADMIN_UI = <?= json_encode($GLOBALS['ADMIN_UI'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
}

window.PAGE_PERMISSIONS = <?= json_encode([
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
    'isSuperAdmin' => is_super_admin()
], JSON_UNESCAPED_UNICODE) ?>;
</script>

<script type="text/javascript">
window.ESCROW_CONFIG = {
    apiUrl:          '<?= $apiBase ?>/escrow_transactions',
    historyApi:      '<?= $apiBase ?>/escrow_status_history',
    disputesApi:     '<?= $apiBase ?>/escrow_disputes',
    ledgerApi:       '<?= $apiBase ?>/escrow_ledger',
    currenciesApi:   '<?= $apiBase ?>/currencies',
    ordersApi:       '<?= $apiBase ?>/orders',
    entityTypesApi:  '<?= $apiBase ?>/entity_types',
    entitiesApi:     '<?= $apiBase ?>/entities',
    itemsPerPage:    20,
    lang:            '<?= addslashes($lang) ?>',
    tenantId:        <?= $tenantId ?>,
    csrfToken:       '<?= addslashes($csrf) ?>'
};
</script>

<!-- Page Permissions JSON for scripts that prefer it in DOM -->
<script id="escrowPagePermissions" type="application/json">
<?= json_encode([
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
    'isSuperAdmin' => is_super_admin()
], JSON_UNESCAPED_UNICODE) ?>
</script>

<?php if ($isFragment): ?>
<script src="/admin/assets/js/admin_framework.js?v=<?= time() ?>"></script>
<script src="/admin/assets/js/pages/escrow.js?v=<?= time() ?>"></script>

<script>
(function () {
    'use strict';
    var attempts = 0, maxAttempts = 50;
    var interval = setInterval(function () {
        attempts++;
        if (window.Escrow && typeof window.Escrow.init === 'function') {
            clearInterval(interval);
            try {
                var maybePromise = window.Escrow.init();
                if (maybePromise && typeof maybePromise.then === 'function') {
                    maybePromise.then(function () {
                        console.log('[Escrow] ✓ Initialized successfully');
                    }).catch(function (e) {
                        console.error('[Escrow] Init failed:', e);
                    });
                }
            } catch (e) {
                console.error('[Escrow] Init threw:', e);
            }
        } else if (attempts > maxAttempts) {
            clearInterval(interval);
            console.error('[Escrow] Timeout waiting for module after ' + (maxAttempts * 100) + 'ms');
        }
    }, 100);
})();
</script>
<?php else: ?>
<script src="/admin/assets/js/admin_framework.js?v=<?= time() ?>"></script>
<script src="/admin/assets/js/pages/escrow.js?v=<?= time() ?>"></script>
<script>
(function () {
    'use strict';
    function tryInit() {
        if (window.Escrow && typeof window.Escrow.init === 'function') {
            window.Escrow.init().catch(function (e) { console.error('[Escrow] Init failed', e); });
        }
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', tryInit);
    } else {
        tryInit();
    }
})();
</script>
<?php endif; ?>

<?php if (!$isFragment) require_once __DIR__ . '/../includes/footer.php'; ?>