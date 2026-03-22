<?php
declare(strict_types=1);

/**
 * /admin/fragments/tenant.php
 * Production Version
 *
 * ✅ Uses admin_context.php helpers (not PermissionsFramework.php)
 * ✅ Role-based + resource-based permission checks
 * ✅ Super-admin sees all tenants, regular admin scoped to own tenant
 * ✅ Tabs: Basic Info | Users (embedded tenant_users) | Addresses (embedded addresses)
 * ✅ Colors and sizing from database CSS variables (via ADMIN_UI theme)
 * ✅ i18n translation loader (languages/Tenants/{lang}.json)
 * ✅ Embedded / AJAX / standalone mode switching
 * ✅ No inline permission management – delegated to JS module + API
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
    }
    header('Location: /admin/login.php');
    exit;
}

// ════════════════════════════════════════════════════════════
// USER CONTEXT & PERMISSIONS
// ════════════════════════════════════════════════════════════
$user      = admin_user();
$lang      = admin_lang();
$dir       = admin_dir();
$csrf      = admin_csrf();
$tenantId  = admin_tenant_id();
$userId    = admin_user_id();
$apiBase   = '/api';

// Image type ID for tenant logo (mirrors image_types table row id=21)
define('TENANT_LOGO_IMAGE_TYPE_ID', 21);

// Resource-based permissions
$canManage     = can('tenants.manage') || can('tenants.create');
$canViewAll    = can_view_all('tenants')    || is_super_admin();
$canViewOwn    = can_view_own('tenants');
$canViewTenant = can_view_tenant('tenants');
$canCreate     = can_create('tenants')     || is_super_admin() || $canManage;
$canEditAll    = can_edit_all('tenants')   || is_super_admin();
$canEditOwn    = can_edit_own('tenants');
$canDeleteAll  = can_delete_all('tenants') || is_super_admin();
$canDeleteOwn  = can_delete_own('tenants');

$canView   = $canViewAll || $canViewOwn || $canViewTenant;
$canEdit   = $canEditAll || $canEditOwn  || $canManage;
$canDelete = $canDeleteAll || $canDeleteOwn || $canManage;

if (!$canView && !is_super_admin()) {
    if ($isFragment) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        exit;
    }
    http_response_code(403);
    die('Access denied: You do not have permission to view tenants');
}

// ════════════════════════════════════════════════════════════
// TRANSLATION HELPER
// ════════════════════════════════════════════════════════════
if (!function_exists('__t')) {
    function __t(string $key, string $fallback = ''): string
    {
        if (function_exists('i18n_get')) {
            $v = i18n_get($key);
            return $v ?? ($fallback ?: $key);
        }
        return $fallback ?: $key;
    }
}

?>
<?php if ($isFragment): ?>
<link rel="stylesheet" href="/admin/assets/css/pages/tenant.css?v=<?= assetVer() ?>">
<?php endif; ?>

<meta data-page="tenants"
      data-i18n-files="/languages/Tenants/<?= rawurlencode($lang) ?>.json">

<div class="page-container" id="tenantsPageContainer" dir="<?= htmlspecialchars($dir) ?>">

    <!-- Page Header -->
    <div class="page-header">
        <div class="page-header-content">
            <h1 class="page-title" data-i18n="page.title">
                <i class="fas fa-building"></i>
                <?= __t('page.title', 'Tenants') ?>
            </h1>
            <p class="page-subtitle" data-i18n="page.subtitle">
                <?= __t('page.subtitle', 'Manage multi-tenant organisations') ?>
            </p>
        </div>
        <div class="page-header-actions">
            <?php if ($canCreate): ?>
            <button id="btnAddTenant" class="btn btn-primary">
                <i class="fas fa-plus"></i>
                <span data-i18n="page.add_new"><?= __t('page.add_new', 'Add Tenant') ?></span>
            </button>
            <?php endif; ?>
            <button id="btnRefresh" class="btn btn-secondary btn-sm">
                <i class="fas fa-sync-alt"></i>
                <span data-i18n="page.refresh"><?= __t('page.refresh', 'Refresh') ?></span>
            </button>
        </div>
    </div>

    <!-- Form Card -->
    <div id="tenantFormContainer" class="card form-card" style="display:none">
        <div class="card-header">
            <h3 class="card-title" id="formTitle">
                <i class="fas fa-building"></i>
                <span data-i18n="form.add_title"><?= __t('form.add_title', 'Add Tenant') ?></span>
            </h3>
            <button type="button" class="btn btn-secondary cancel-btn" id="btnCloseForm" aria-label="Close">
                <i class="fas fa-times" aria-hidden="true"></i>
            </button>
        </div>
        <div class="card-body">
            <form id="tenantForm" novalidate>
                <input type="hidden" id="formId"      name="id">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">

                <!-- Tabs Navigation -->
                <div class="form-tabs" id="tenantFormTabs">
                    <button type="button" class="tab-btn active" data-tab="tab-basic">
                        <i class="fas fa-info-circle"></i>
                        <span data-i18n="tabs.basic"><?= __t('tabs.basic', 'Basic Info') ?></span>
                    </button>
                    <button type="button" class="tab-btn" data-tab="tab-domains" id="tabBtnDomains" disabled>
                        <i class="fas fa-globe"></i>
                        <span data-i18n="tabs.domains"><?= __t('tabs.domains', 'Domains') ?></span>
                    </button>
                    <button type="button" class="tab-btn" data-tab="tab-users" id="tabBtnUsers" disabled>
                        <i class="fas fa-users"></i>
                        <span data-i18n="tabs.users"><?= __t('tabs.users', 'Users') ?></span>
                    </button>
                    <button type="button" class="tab-btn" data-tab="tab-addresses" id="tabBtnAddresses" disabled>
                        <i class="fas fa-map-marker-alt"></i>
                        <span data-i18n="tabs.addresses"><?= __t('tabs.addresses', 'Addresses') ?></span>
                    </button>
                    <button type="button" class="tab-btn" data-tab="tab-categories" id="tabBtnCategories" disabled>
                        <i class="fas fa-tags"></i>
                        <span data-i18n="tabs.categories"><?= __t('tabs.categories', 'Categories') ?></span>
                    </button>
                    <button type="button" class="tab-btn" data-tab="tab-media" id="tabBtnMedia" disabled>
                        <i class="fas fa-image"></i>
                        <span data-i18n="tabs.media"><?= __t('tabs.media', 'Media') ?></span>
                    </button>
                    <button type="button" class="tab-btn" data-tab="tab-studio" id="tabBtnStudio" disabled>
                        <i class="fas fa-photo-video"></i>
                        <span data-i18n="tabs.studio"><?= __t('tabs.studio', 'Media Studio') ?></span>
                    </button>
                </div>

                <!-- Tab: Basic Info -->
                <div class="tab-content active" id="tab-basic">
                    <div class="form-grid">

                        <div class="form-group">
                            <label for="formName" class="required">
                                <i class="fas fa-signature"></i>
                                <span data-i18n="form.fields.name.label">
                                    <?= __t('form.fields.name.label', 'Tenant Name') ?>
                                </span>
                            </label>
                            <input type="text" id="formName" name="name" class="form-control"
                                   data-i18n-placeholder="form.fields.name.placeholder"
                                   placeholder="<?= __t('form.fields.name.placeholder', 'Enter tenant name') ?>"
                                   required minlength="3" maxlength="150">
                            <small class="form-text" data-i18n="form.fields.name.hint">
                                <?= __t('form.fields.name.hint', 'Minimum 3 characters, maximum 150') ?>
                            </small>
                            <div class="invalid-feedback" data-i18n="form.validation.name_required">
                                <?= __t('form.validation.name_required', 'Please enter a valid tenant name') ?>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="formOwnerUserId" class="required">
                                <i class="fas fa-user-shield"></i>
                                <span data-i18n="form.fields.owner_user_id.label">
                                    <?= __t('form.fields.owner_user_id.label', 'Owner User ID') ?>
                                </span>
                            </label>
                            <input type="number" id="formOwnerUserId" name="owner_user_id" class="form-control"
                                   data-i18n-placeholder="form.fields.owner_user_id.placeholder"
                                   placeholder="<?= __t('form.fields.owner_user_id.placeholder', 'Enter owner user ID') ?>"
                                   required min="1">
                            <small class="form-text" data-i18n="form.fields.owner_user_id.hint">
                                <?= __t('form.fields.owner_user_id.hint', 'The user who owns this tenant') ?>
                            </small>
                            <div class="invalid-feedback" data-i18n="form.validation.owner_required">
                                <?= __t('form.validation.owner_required', 'Please enter a valid user ID') ?>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="formStatus" class="required">
                                <i class="fas fa-toggle-on"></i>
                                <span data-i18n="form.fields.status.label">
                                    <?= __t('form.fields.status.label', 'Status') ?>
                                </span>
                            </label>
                            <select id="formStatus" name="status" class="form-control" required>
                                <option value="active" data-i18n="form.fields.status.active">
                                    <?= __t('form.fields.status.active', 'Active') ?>
                                </option>
                                <option value="suspended" data-i18n="form.fields.status.suspended">
                                    <?= __t('form.fields.status.suspended', 'Suspended') ?>
                                </option>
                            </select>
                        </div>

                    </div><!-- /.form-grid -->

                    <div class="form-actions">
                        <button type="submit" id="btnSubmitForm" class="btn btn-primary">
                            <i class="fas fa-save"></i>
                            <span data-i18n="form.buttons.save">
                                <?= __t('form.buttons.save', 'Save Tenant') ?>
                            </span>
                        </button>
                        <button type="button" id="btnCancelForm" class="btn btn-secondary cancel-btn">
                            <i class="fas fa-times" aria-hidden="true"></i>
                            <span data-i18n="form.buttons.cancel">
                                <?= __t('form.buttons.cancel', 'Cancel') ?>
                            </span>
                        </button>
                    </div>
                </div><!-- /#tab-basic -->

                <!-- Tab: Domains -->
                <div class="tab-content" id="tab-domains" style="display:none">
                    <div id="tenantDomainsPanel" class="domains-panel">
                        <div class="domains-panel-header">
                            <h4 data-i18n="domains.tab_title">
                                <i class="fas fa-globe"></i>
                                <?= __t('domains.tab_title', 'Domain Management') ?>
                            </h4>
                            <?php if ($canEdit): ?>
                            <button type="button" id="btnAddDomain" class="btn btn-primary btn-sm">
                                <i class="fas fa-plus"></i>
                                <span data-i18n="domains.add"><?= __t('domains.add', 'Add Domain') ?></span>
                            </button>
                            <?php endif; ?>
                        </div>

                        <!-- Inline add/edit domain form -->
                        <div id="domainFormInline" class="domain-form-inline" style="display:none">
                            <div class="domain-form-header">
                                <i class="fas fa-globe"></i>
                                <span id="domainFormTitle" class="domain-form-title">
                                    <?= __t('domains.add', 'Add Domain') ?>
                                </span>
                            </div>
                            <input type="hidden" id="newDomainId">
                            <div class="form-grid">

                                <!-- domain -->
                                <div class="form-group">
                                    <label for="newDomainInput" class="required">
                                        <i class="fas fa-globe"></i>
                                        <span data-i18n="domains.fields.domain.label">
                                            <?= __t('domains.fields.domain.label', 'Domain') ?>
                                        </span>
                                    </label>
                                    <input type="text" id="newDomainInput" class="form-control"
                                           data-i18n-placeholder="domains.fields.domain.placeholder"
                                           placeholder="<?= __t('domains.fields.domain.placeholder', 'e.g. acme-corp.example.com') ?>"
                                           maxlength="255">
                                    <small class="form-text" data-i18n="domains.fields.domain.hint">
                                        <?= __t('domains.fields.domain.hint', 'Lowercase letters, numbers, dots and hyphens only') ?>
                                    </small>
                                </div>

                                <!-- type -->
                                <div class="form-group">
                                    <label for="newDomainType">
                                        <i class="fas fa-tag"></i>
                                        <span data-i18n="domains.fields.type.label">
                                            <?= __t('domains.fields.type.label', 'Type') ?>
                                        </span>
                                    </label>
                                    <select id="newDomainType" class="form-control">
                                        <option value="custom"    data-i18n="domains.custom"><?= __t('domains.custom', 'Custom') ?></option>
                                        <option value="subdomain" data-i18n="domains.subdomain"><?= __t('domains.subdomain', 'Subdomain') ?></option>
                                        <option value="alias"     data-i18n="domains.alias"><?= __t('domains.alias', 'Alias') ?></option>
                                        <option value="primary"   data-i18n="domains.primary"><?= __t('domains.primary', 'Primary') ?></option>
                                    </select>
                                </div>

                                <!-- ssl_status -->
                                <div class="form-group">
                                    <label for="newDomainSslStatus">
                                        <i class="fas fa-lock"></i>
                                        <span data-i18n="domains.fields.ssl_status.label">
                                            <?= __t('domains.fields.ssl_status.label', 'SSL Status') ?>
                                        </span>
                                    </label>
                                    <select id="newDomainSslStatus" class="form-control">
                                        <option value="none"    data-i18n="domains.ssl_none"><?= __t('domains.ssl_none', 'No SSL') ?></option>
                                        <option value="pending" data-i18n="domains.ssl_pending"><?= __t('domains.ssl_pending', 'SSL Pending') ?></option>
                                        <option value="active"  data-i18n="domains.ssl_active"><?= __t('domains.ssl_active', 'SSL Active') ?></option>
                                        <option value="failed"  data-i18n="domains.ssl_failed"><?= __t('domains.ssl_failed', 'SSL Failed') ?></option>
                                    </select>
                                </div>

                                <!-- ssl_expires_at -->
                                <div class="form-group">
                                    <label for="newDomainSslExpiresAt">
                                        <i class="fas fa-calendar-times"></i>
                                        <span data-i18n="domains.fields.ssl_expires_at.label">
                                            <?= __t('domains.fields.ssl_expires_at.label', 'SSL Expires At') ?>
                                        </span>
                                    </label>
                                    <input type="datetime-local" id="newDomainSslExpiresAt" class="form-control">
                                    <small class="form-text" data-i18n="domains.fields.ssl_expires_at.hint">
                                        <?= __t('domains.fields.ssl_expires_at.hint', 'Leave blank if SSL has no expiry or is not active') ?>
                                    </small>
                                </div>

                                <!-- verification_token -->
                                <div class="form-group">
                                    <label for="newDomainVerificationToken">
                                        <i class="fas fa-key"></i>
                                        <span data-i18n="domains.fields.verification_token.label">
                                            <?= __t('domains.fields.verification_token.label', 'Verification Token') ?>
                                        </span>
                                    </label>
                                    <input type="text" id="newDomainVerificationToken" class="form-control"
                                           data-i18n-placeholder="domains.fields.verification_token.placeholder"
                                           placeholder="<?= __t('domains.fields.verification_token.placeholder', 'Auto-generated or enter manually') ?>"
                                           maxlength="128">
                                    <small class="form-text" data-i18n="domains.fields.verification_token.hint">
                                        <?= __t('domains.fields.verification_token.hint', 'Used for DNS TXT-record or HTTP-file domain verification') ?>
                                    </small>
                                </div>

                                <!-- verified_at -->
                                <div class="form-group">
                                    <label for="newDomainVerifiedAt">
                                        <i class="fas fa-calendar-check"></i>
                                        <span data-i18n="domains.fields.verified_at.label">
                                            <?= __t('domains.fields.verified_at.label', 'Verified At') ?>
                                        </span>
                                    </label>
                                    <input type="datetime-local" id="newDomainVerifiedAt" class="form-control">
                                    <small class="form-text" data-i18n="domains.fields.verified_at.hint">
                                        <?= __t('domains.fields.verified_at.hint', 'Date and time the domain ownership was confirmed') ?>
                                    </small>
                                </div>

                                <!-- meta -->
                                <div class="form-group form-group-full">
                                    <label for="newDomainMeta">
                                        <i class="fas fa-code"></i>
                                        <span data-i18n="domains.fields.meta.label">
                                            <?= __t('domains.fields.meta.label', 'Meta (JSON)') ?>
                                        </span>
                                    </label>
                                    <textarea id="newDomainMeta" class="form-control" rows="3"
                                              data-i18n-placeholder="domains.fields.meta.placeholder"
                                              placeholder="<?= __t('domains.fields.meta.placeholder', '{"key":"value"} — optional extra data') ?>"></textarea>
                                    <small class="form-text" data-i18n="domains.fields.meta.hint">
                                        <?= __t('domains.fields.meta.hint', 'Optional JSON object for additional domain metadata') ?>
                                    </small>
                                </div>

                            </div><!-- /.form-grid -->

                            <!-- Checkboxes row -->
                            <div class="form-row-checks">

                                <div class="form-group form-group-check">
                                    <label class="check-label">
                                        <input type="checkbox" id="newDomainIsVerified" value="1">
                                        <i class="fas fa-shield-alt"></i>
                                        <span data-i18n="domains.fields.is_verified.label">
                                            <?= __t('domains.fields.is_verified.label', 'Verified') ?>
                                        </span>
                                    </label>
                                    <small class="form-text" data-i18n="domains.fields.is_verified.hint">
                                        <?= __t('domains.fields.is_verified.hint', 'Mark domain as already verified') ?>
                                    </small>
                                </div>

                                <div class="form-group form-group-check">
                                    <label class="check-label">
                                        <input type="checkbox" id="newDomainRedirectToPrimary" value="1">
                                        <i class="fas fa-directions"></i>
                                        <span data-i18n="domains.fields.redirect_to_primary.label">
                                            <?= __t('domains.fields.redirect_to_primary.label', 'Redirect to Primary') ?>
                                        </span>
                                    </label>
                                    <small class="form-text" data-i18n="domains.fields.redirect_to_primary.hint">
                                        <?= __t('domains.fields.redirect_to_primary.hint', 'Redirect all traffic on this domain to the primary domain') ?>
                                    </small>
                                </div>

                            </div><!-- /.form-row-checks -->

                            <div class="form-actions form-actions-compact">
                                <button type="button" id="btnSaveDomain" class="btn btn-primary btn-sm">
                                    <i class="fas fa-save"></i>
                                    <span data-i18n="domains.buttons.save"><?= __t('domains.buttons.save', 'Save Domain') ?></span>
                                </button>
                                <button type="button" id="btnCancelDomain" class="btn btn-secondary cancel-btn">
                                    <i class="fas fa-times" aria-hidden="true"></i>
                                    <span data-i18n="domains.buttons.cancel"><?= __t('domains.buttons.cancel', 'Cancel') ?></span>
                                </button>
                            </div>
                        </div>

                        <!-- Domains list -->
                        <div id="domainsList" class="domains-list">
                            <div class="sub-fragment-placeholder" id="domainsPlaceholder">
                                <i class="fas fa-globe fa-2x"></i>
                                <p data-i18n="domains.no_domains">
                                    <?= __t('domains.no_domains', 'No domains registered yet') ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div><!-- /#tab-domains -->

                <!-- Tab: Users -->
                <div class="tab-content" id="tab-users" style="display:none">
                    <div id="tenantUsersContainer" class="sub-fragment-container">
                        <div class="sub-fragment-placeholder">
                            <i class="fas fa-users fa-2x"></i>
                            <p data-i18n="tabs.users"><?= __t('tabs.users', 'Users') ?></p>
                            <small><?= __t('page.save_first', 'Save the tenant first to manage users') ?></small>
                        </div>
                    </div>
                </div>

                <!-- Tab: Addresses -->
                <div class="tab-content" id="tab-addresses" style="display:none">
                    <div id="tenantAddressesContainer" class="sub-fragment-container">
                        <div class="sub-fragment-placeholder">
                            <i class="fas fa-map-marker-alt fa-2x"></i>
                            <p data-i18n="tabs.addresses"><?= __t('tabs.addresses', 'Addresses') ?></p>
                            <small><?= __t('page.save_first', 'Save the tenant first to manage addresses') ?></small>
                        </div>
                    </div>
                </div>

                <!-- Tab: Categories -->
                <div class="tab-content" id="tab-categories" style="display:none">
                    <div id="tenantCategoriesPanel" class="domains-panel">
                        <div class="domains-panel-header">
                            <h4>
                                <i class="fas fa-tags"></i>
                                <span data-i18n="categories.tab_title"><?= __t('categories.tab_title', 'Category Management') ?></span>
                            </h4>
                        </div>

                        <!-- Toolbar: search + bulk actions -->
                        <div class="cat-tree-toolbar">
                            <input type="text" id="catTreeSearch" class="form-control cat-tree-search"
                                   data-i18n-placeholder="categories.search_placeholder"
                                   placeholder="<?= __t('categories.search_placeholder', 'Search categories…') ?>">
                            <?php if ($canEdit): ?>
                            <button type="button" id="btnCatSelectAll" class="btn btn-secondary btn-sm">
                                <i class="fas fa-check-square"></i>
                                <span data-i18n="categories.select_all"><?= __t('categories.select_all', 'Select All') ?></span>
                            </button>
                            <button type="button" id="btnCatDeselectAll" class="btn btn-secondary btn-sm">
                                <i class="far fa-square"></i>
                                <span data-i18n="categories.deselect_all"><?= __t('categories.deselect_all', 'Deselect All') ?></span>
                            </button>
                            <?php endif; ?>
                        </div>

                        <!-- Tree container -->
                        <div id="tenantCategoryTree" class="cat-tree-wrap">
                            <div class="sub-fragment-placeholder" id="tenantCategoriesPlaceholder">
                                <i class="fas fa-tags fa-2x"></i>
                                <p data-i18n="categories.no_categories">
                                    <?= __t('categories.no_categories', 'No categories assigned yet') ?>
                                </p>
                            </div>
                        </div>

                        <!-- Save bar -->
                        <?php if ($canEdit): ?>
                        <div class="cat-tree-save-bar">
                            <button type="button" id="btnSaveCategoryTree" class="btn btn-primary btn-sm">
                                <i class="fas fa-save"></i>
                                <span data-i18n="categories.buttons.save"><?= __t('categories.buttons.save', 'Save Categories') ?></span>
                            </button>
                            <span id="catTreeStatus" class="cat-row-meta"></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Tab: Media (Tenant Logo) -->
                <div class="tab-content" id="tab-media" style="display:none">
                    <div class="media-section" style="margin-bottom: 30px;">
                        <h5 style="margin-bottom: 15px; color: var(--text-primary);" data-i18n="form.sections.logo">
                            <?= __t('form.sections.logo', 'Tenant Logo') ?>
                        </h5>
                        <div class="image-upload-section">
                            <?php if ($canEdit): ?>
                            <button type="button" id="btnSelectTenantLogo" class="btn btn-secondary" style="margin-bottom: 15px;" data-i18n="form.media.select_logo">
                                <?= __t('form.media.select_logo', 'Select Logo from Studio') ?>
                            </button>
                            <?php endif; ?>
                            <div id="tenantLogoPreview" class="single-image-preview">
                                <div class="placeholder" data-i18n="form.media.no_logo">
                                    <?= __t('form.media.no_logo', 'No logo selected') ?>
                                </div>
                            </div>
                            <input type="text" id="tenantLogoUrlDisplay" class="form-control url-display" readonly
                                   data-i18n-placeholder="form.media.logo_url"
                                   placeholder="<?= __t('form.media.logo_url', 'Logo URL will appear here') ?>">
                        </div>
                    </div>
                </div><!-- /#tab-media -->

                <!-- Tab: Media Studio (inline embed) -->
                <div class="tab-content" id="tab-studio" style="display:none">
                    <div id="tenantStudioPanel" class="domains-panel">
                        <div class="domains-panel-header">
                            <h4>
                                <i class="fas fa-photo-video"></i>
                                <span data-i18n="tabs.studio"><?= __t('tabs.studio', 'Media Studio') ?></span>
                            </h4>
                        </div>
                        <div id="tenantStudioContainer" class="studio-inline-wrap">
                            <div class="sub-fragment-placeholder" id="tenantStudioPlaceholder">
                                <i class="fas fa-photo-video fa-2x"></i>
                                <p data-i18n="studio.placeholder"><?= __t('studio.placeholder', 'Select a tenant to load its media studio') ?></p>
                            </div>
                        </div>
                    </div>
                </div><!-- /#tab-studio -->

            </form>
        </div>
    </div><!-- /#tenantFormContainer -->

    <!-- Filters Card -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-filter"></i>
                <span data-i18n="filters.search"><?= __t('filters.search', 'Search') ?></span>
            </h3>
        </div>
        <div class="card-body">
            <div class="filters-grid">

                <div class="form-group">
                    <label for="searchInput">
                        <i class="fas fa-search"></i>
                        <span data-i18n="filters.search"><?= __t('filters.search', 'Search') ?></span>
                    </label>
                    <input type="text" id="searchInput" class="form-control"
                           data-i18n-placeholder="filters.search_placeholder"
                           placeholder="<?= __t('filters.search_placeholder', 'Search by name or domain') ?>">
                </div>

                <div class="form-group">
                    <label for="statusFilter">
                        <i class="fas fa-toggle-on"></i>
                        <span data-i18n="filters.status"><?= __t('filters.status', 'Status') ?></span>
                    </label>
                    <select id="statusFilter" class="form-control">
                        <option value=""         data-i18n="filters.all_statuses"><?= __t('filters.all_statuses', 'All Statuses') ?></option>
                        <option value="active"   data-i18n="table.status.active"><?= __t('table.status.active', 'Active') ?></option>
                        <option value="suspended" data-i18n="table.status.suspended"><?= __t('table.status.suspended', 'Suspended') ?></option>
                    </select>
                </div>

                <?php if (is_super_admin()): ?>
                <div class="form-group">
                    <label for="ownerFilter">
                        <i class="fas fa-user"></i>
                        <span data-i18n="filters.owner"><?= __t('filters.owner', 'Owner User ID') ?></span>
                    </label>
                    <input type="number" id="ownerFilter" class="form-control" min="1"
                           data-i18n-placeholder="filters.owner_placeholder"
                           placeholder="<?= __t('filters.owner_placeholder', 'Filter by owner ID') ?>">
                </div>
                <?php endif; ?>

                <div class="form-group filter-actions-group">
                    <label>&nbsp;</label>
                    <div class="filter-actions">
                        <button id="btnApplyFilters" class="btn btn-secondary">
                            <i class="fas fa-check"></i>
                            <span data-i18n="filters.apply"><?= __t('filters.apply', 'Apply') ?></span>
                        </button>
                        <button id="btnResetFilters" class="btn btn-secondary">
                            <i class="fas fa-undo"></i>
                            <span data-i18n="filters.reset"><?= __t('filters.reset', 'Reset') ?></span>
                        </button>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- Table Card -->
    <div class="card">
        <div class="card-body">

            <div id="tableLoading" class="loading-state">
                <div class="spinner"></div>
                <p data-i18n="page.loading"><?= __t('page.loading', 'Loading tenants') ?></p>
            </div>

            <div id="tableContainer" style="display:none">
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th data-i18n="table.headers.id"><?= __t('table.headers.id', 'ID') ?></th>
                                <th data-i18n="table.headers.logo"><?= __t('table.headers.logo', 'Logo') ?></th>
                                <th data-i18n="table.headers.name"><?= __t('table.headers.name', 'Name') ?></th>
                                <th data-i18n="table.headers.owner"><?= __t('table.headers.owner', 'Owner') ?></th>
                                <th data-i18n="table.headers.status"><?= __t('table.headers.status', 'Status') ?></th>
                                <th data-i18n="table.headers.created"><?= __t('table.headers.created', 'Created') ?></th>
                                <th data-i18n="table.headers.updated"><?= __t('table.headers.updated', 'Updated') ?></th>
                                <th data-i18n="table.headers.actions"><?= __t('table.headers.actions', 'Actions') ?></th>
                            </tr>
                        </thead>
                        <tbody id="tableBody"></tbody>
                    </table>
                </div>
                <div class="table-footer">
                    <div id="paginationInfo" class="pagination-info"></div>
                    <div id="pagination"     class="pagination"></div>
                </div>
            </div>

            <div id="emptyState" class="empty-state" style="display:none">
                <div class="empty-icon"><i class="fas fa-building"></i></div>
                <h3 data-i18n="table.empty.title"><?= __t('table.empty.title', 'No Tenants Found') ?></h3>
                <p   data-i18n="table.empty.message"><?= __t('table.empty.message', 'No tenants match your current filters') ?></p>
                <?php if ($canCreate): ?>
                <button class="btn btn-primary" onclick="Tenants.add()">
                    <i class="fas fa-plus"></i>
                    <span data-i18n="table.empty.add_first"><?= __t('table.empty.add_first', 'Create First Tenant') ?></span>
                </button>
                <?php endif; ?>
            </div>

            <div id="errorState" class="error-state" style="display:none">
                <div class="error-icon"><i class="fas fa-exclamation-triangle"></i></div>
                <h3 data-i18n="messages.error.load_failed"><?= __t('messages.error.load_failed', 'Failed to load tenants') ?></h3>
                <p id="errorMessage"></p>
                <button id="btnRetry" class="btn btn-secondary">
                    <i class="fas fa-redo"></i>
                    <span data-i18n="page.retry"><?= __t('page.retry', 'Retry') ?></span>
                </button>
            </div>

        </div>
    </div>

</div><!-- /#tenantsPageContainer -->

<!-- Media Studio Modal -->
<div id="tenantMediaModal" class="modal" style="display:none">
    <div class="modal-content">
        <span class="close" id="tenantMediaClose">&times;</span>
        <iframe id="tenantMediaFrame" style="width:100%; height:80vh; border:none; display:block;"></iframe>
    </div>
</div>

<!-- Client-side globals -->
<script type="text/javascript">
window.APP_CONFIG = window.APP_CONFIG || {};
window.APP_CONFIG.API_BASE       = window.APP_CONFIG.API_BASE       || '<?= $apiBase ?>';
window.APP_CONFIG.TENANT_ID      = window.APP_CONFIG.TENANT_ID      || <?= $tenantId ?>;
window.APP_CONFIG.CSRF_TOKEN     = window.APP_CONFIG.CSRF_TOKEN     || '<?= addslashes($csrf) ?>';
window.APP_CONFIG.USER_ID        = window.APP_CONFIG.USER_ID        || <?= $userId ?>;
window.APP_CONFIG.IS_SUPER_ADMIN = <?= is_super_admin() ? 'true' : 'false' ?>;

window.USER_LANGUAGE  = window.USER_LANGUAGE  || '<?= addslashes($lang) ?>';
window.USER_DIRECTION = window.USER_DIRECTION || '<?= addslashes($dir) ?>';
window.CSRF_TOKEN     = window.CSRF_TOKEN     || '<?= addslashes($csrf) ?>';

if (!window.ADMIN_UI) {
    window.ADMIN_UI = <?= json_encode($GLOBALS['ADMIN_UI'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
}

window.PAGE_PERMISSIONS = <?= json_encode([
    'canCreate'     => $canCreate,
    'canEdit'       => $canEdit,
    'canDelete'     => $canDelete,
    'canView'       => $canView,
    'canViewAll'    => $canViewAll,
    'canViewOwn'    => $canViewOwn,
    'canViewTenant' => $canViewTenant,
    'canEditAll'    => $canEditAll,
    'canEditOwn'    => $canEditOwn,
    'canDeleteAll'  => $canDeleteAll,
    'canDeleteOwn'  => $canDeleteOwn,
    'isSuperAdmin'  => is_super_admin()
], JSON_UNESCAPED_UNICODE) ?>;

window.TENANTS_CONFIG = {
    apiUrl:                 '<?= $apiBase ?>/tenants',
    domainsApiUrl:          '<?= $apiBase ?>/tenant_domains',
    tenantUsersUrl:         '/admin/fragments/tenant_users.php',
    addressesUrl:           '/admin/fragments/addresses.php',
    tenantCategoriesApiUrl: '<?= $apiBase ?>/categories-tenants',
    categoriesApiUrl:       '<?= $apiBase ?>/categories',
    imagesApiUrl:           '<?= $apiBase ?>/images',
    mediaStudioBase:        '/admin/fragments/media_studio.php',
    logoImageTypeId:        <?= TENANT_LOGO_IMAGE_TYPE_ID ?>,
    csrfToken:      '<?= addslashes($csrf) ?>',
    lang:           '<?= addslashes($lang) ?>',
    itemsPerPage:   25
};
</script>

<script id="pagePermissions" type="application/json">
<?= json_encode([
    'canCreate'     => $canCreate,
    'canEdit'       => $canEdit,
    'canDelete'     => $canDelete,
    'canView'       => $canView,
    'canViewAll'    => $canViewAll,
    'canViewOwn'    => $canViewOwn,
    'canViewTenant' => $canViewTenant,
    'canEditAll'    => $canEditAll,
    'canEditOwn'    => $canEditOwn,
    'canDeleteAll'  => $canDeleteAll,
    'canDeleteOwn'  => $canDeleteOwn,
    'isSuperAdmin'  => is_super_admin()
], JSON_UNESCAPED_UNICODE) ?>
</script>

<!-- Translation loader -->
<script type="text/javascript">
(function () {
    async function applyTranslations() {
        try {
            const lang = window.USER_LANGUAGE || 'en';
            const url  = `/languages/Tenants/${encodeURIComponent(lang)}.json`;
            console.log('[Tenants] Loading translations from', url);
            const res  = await fetch(url, { credentials: 'same-origin' });
            if (!res.ok) throw new Error('Translation fetch failed: ' + res.status);
            const translations = await res.json();
            window.TENANTS_TRANSLATIONS = translations;
            const container = document.getElementById('tenantsPageContainer');
            if (!container) return;
            container.querySelectorAll('[data-i18n]').forEach(el => {
                const key = el.getAttribute('data-i18n');
                const txt = key.split('.').reduce(
                    (o, k) => (o && o[k] !== undefined) ? o[k] : null, translations
                );
                if (txt !== null && txt !== undefined) {
                    if (el.tagName === 'INPUT' && el.hasAttribute('placeholder')) {
                        el.placeholder = txt;
                    } else {
                        el.textContent = txt;
                    }
                }
            });
            container.querySelectorAll('[data-i18n-placeholder]').forEach(el => {
                const key = el.getAttribute('data-i18n-placeholder');
                const txt = key.split('.').reduce(
                    (o, k) => (o && o[k] !== undefined) ? o[k] : null, translations
                );
                if (txt !== null && txt !== undefined) el.placeholder = txt;
            });
            console.log('[Tenants] Translations applied');
        } catch (err) {
            console.warn('[Tenants] Translation load/apply failed:', err);
        }
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', applyTranslations);
    } else {
        setTimeout(applyTranslations, 50);
    }
})();
</script>

<?php if ($isFragment): ?>
<script src="/admin/assets/js/admin_framework.js?v=<?= assetVer() ?>"></script>
<script src="/admin/assets/js/pages/tenant.js?v=<?= assetVer() ?>"></script>
<script>
(function () {
    console.log('[Tenants] Embedded mode - waiting for framework & module...');
    let attempts = 0, maxAttempts = 50;
    const interval = setInterval(function () {
        attempts++;
        if (window.AdminFramework && window.Tenants && typeof window.Tenants.init === 'function') {
            clearInterval(interval);
            console.log('[Tenants] Module ready - initializing...');
            try {
                const p = window.Tenants.init();
                if (p && typeof p.then === 'function') {
                    p.then(() => console.log('[Tenants] Initialized')).catch(e => console.error('[Tenants] Init failed', e));
                } else {
                    console.log('[Tenants] Initialized (sync)');
                }
            } catch (e) {
                console.error('[Tenants] Init threw', e);
            }
        } else if (attempts > maxAttempts) {
            clearInterval(interval);
            console.error('[Tenants] Timeout. Framework:', !!window.AdminFramework, 'Module:', !!window.Tenants);
        } else if (attempts % 10 === 0) {
            console.log('[Tenants] waiting...', attempts, '/', maxAttempts);
        }
    }, 100);
})();
</script>
<?php else: ?>
<script src="/admin/assets/js/pages/tenant.js?v=<?= assetVer() ?>"></script>
<script>
(function () {
    function tryInit() {
        if (window.Tenants && typeof window.Tenants.init === 'function') {
            const p = window.Tenants.init();
            if (p && typeof p.then === 'function') {
                p.catch(e => console.error('[Tenants] Init failed', e));
            }
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

<?php
if (!$isFragment) {
    require_once __DIR__ . '/../includes/footer.php';
}