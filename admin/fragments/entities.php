<?php
declare(strict_types=1);

/**
 * /admin/fragments/entities.php
 * Complete Entity Management System
 * 
 * ✅ Multi-language support
 * ✅ Entity settings, working hours, attributes
 * ✅ Media studio integration for logos, covers, licenses
 * ✅ Address management via embedded fragment
 * ✅ Advanced filtering and search
 */

// ════════════════════════════════════════════════════════════
// DETECT REQUEST TYPE
// ════════════════════════════════════════════════════════════
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
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
$user = admin_user();
$lang = admin_lang();
$dir = in_array($lang, ['ar', 'he', 'fa', 'ur']) ? 'rtl' : 'ltr';
$csrf = admin_csrf();
$tenantId = admin_tenant_id();
$userId = admin_user_id();

// ════════════════════════════════════════════════════════════
// CHECK PERMISSIONS
// ════════════════════════════════════════════════════════════
$canManageEntities = can('entities.manage') || can('entities.create');
$canViewAll = can_view_all('entities');
$canViewOwn = can_view_own('entities');
$canViewTenant = can_view_tenant('entities');
$canCreate = can_create('entities');
$canEditAll = can_edit_all('entities');
$canEditOwn = can_edit_own('entities');
$canDeleteAll = can_delete_all('entities');
$canDeleteOwn = can_delete_own('entities');

$canView = $canViewAll || $canViewOwn || $canViewTenant;
$canEdit = $canEditAll || $canEditOwn || $canManageEntities;
$canDelete = $canDeleteAll || $canDeleteOwn || $canManageEntities;

if (!$canView && !is_super_admin()) {
    if ($isFragment) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        exit;
    } else {
        http_response_code(403);
        die('Access denied: You do not have permission to view entities');
    }
}

// ════════════════════════════════════════════════════════════
// TRANSLATION HELPERS
// ════════════════════════════════════════════════════════════
function __t($key, $fallback = '') {
    if (function_exists('i18n_get')) {
        $v = i18n_get($key);
        return $v ?? ($fallback ?? $key);
    }
    return $fallback ?? $key;
}

function __tr($key, $replacements = []) {
    $text = __t($key, $key);
    foreach ($replacements as $ph => $val) {
        $text = str_replace("{" . $ph . "}", (string)$val, $text);
    }
    return $text;
}

// ════════════════════════════════════════════════════════════
// API BASE
// ════════════════════════════════════════════════════════════
$apiBase = '/api';

?>
<!-- Force load CSS if embedded -->
<?php if ($isFragment): ?>
<link rel="stylesheet" href="/admin/assets/css/pages/entities.css?v=<?= time() ?>">
<?php endif; ?>

<!-- Page Meta -->
<meta data-page="entities"
      data-i18n-files="/languages/Entities/<?= rawurlencode($lang) ?>.json">

<!-- Page Container -->
<div class="page-container" id="entitiesPageContainer" dir="<?= htmlspecialchars($dir) ?>">

    <!-- Page Header -->
    <div class="page-header">
        <div class="page-header-content">
            <h1 class="page-title" data-i18n="entities.title"><?= __t('entities.title', 'Entities') ?></h1>
            <p class="page-subtitle" data-i18n="entities.subtitle"><?= __t('entities.subtitle', 'Manage your entities and branches') ?></p>
        </div>
        <div class="page-header-actions">
            <?php if ($canCreate): ?>
            <button id="btnAddEntity" class="btn btn-primary">
                <i class="fas fa-plus"></i>
                <span data-i18n="entities.add_new"><?= __t('entities.add_new', 'Add Entity') ?></span>
            </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Form Container -->
    <div id="entityFormContainer" class="card form-card" style="display:none">
        <div class="card-header">
            <h3 class="card-title" id="formTitle" data-i18n="form.add_title"><?= __t('form.add_title', 'Add Entity') ?></h3>
            <button type="button" class="btn btn-sm btn-outline" id="btnCloseForm" aria-label="<?= __t('accessibility.close', 'Close') ?>">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="card-body">
            <form id="entityForm" novalidate>
                <!-- Hidden Fields -->
                <input type="hidden" id="formId" name="id">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                <input type="hidden" id="entityTenantId" name="tenant_id" value="<?= $tenantId ?>">
                <input type="hidden" id="entityUserId" name="user_id" value="<?= $userId ?>">
                <input type="hidden" id="entityTranslationsData" name="translations_data">
                <input type="hidden" id="entityAttributesData" name="attributes_data">
                <input type="hidden" id="entitySettingsData" name="settings_data">
                <input type="hidden" id="entityWorkingHoursData" name="working_hours_data">
                
                <!-- Media Fields (for image URLs) -->
                <input type="hidden" id="entityLogoUrl" name="entity_logo">
                <input type="hidden" id="entityCoverUrl" name="entity_cover">
                <input type="hidden" id="entityLicenseUrl" name="entity_license">

                <!-- Tabs Navigation -->
                <div class="form-tabs">
                    <button type="button" class="tab-btn active" data-tab="basic">
                        <i class="fas fa-info-circle"></i>
                        <span data-i18n="tabs.basic"><?= __t('tabs.basic', 'Basic Info') ?></span>
                    </button>
                    <button type="button" class="tab-btn" data-tab="contact">
                        <i class="fas fa-address-book"></i>
                        <span data-i18n="tabs.contact"><?= __t('tabs.contact', 'Contact') ?></span>
                    </button>
                    <button type="button" class="tab-btn" data-tab="settings">
                        <i class="fas fa-cog"></i>
                        <span data-i18n="tabs.settings"><?= __t('tabs.settings', 'Settings') ?></span>
                    </button>
                    <button type="button" class="tab-btn" data-tab="working_hours">
                        <i class="fas fa-clock"></i>
                        <span data-i18n="tabs.working_hours"><?= __t('tabs.working_hours', 'Working Hours') ?></span>
                    </button>
                    <button type="button" class="tab-btn" data-tab="attributes">
                        <i class="fas fa-list-alt"></i>
                        <span data-i18n="tabs.attributes"><?= __t('tabs.attributes', 'Attributes') ?></span>
                    </button>
                    <button type="button" class="tab-btn" data-tab="media">
                        <i class="fas fa-images"></i>
                        <span data-i18n="tabs.media"><?= __t('tabs.media', 'Media') ?></span>
                    </button>
                    <button type="button" class="tab-btn" data-tab="address">
                        <i class="fas fa-map-marker-alt"></i>
                        <span data-i18n="tabs.address"><?= __t('tabs.address', 'Address') ?></span>
                    </button>
                    <button type="button" class="tab-btn" data-tab="translations">
                        <i class="fas fa-language"></i>
                        <span data-i18n="tabs.translations"><?= __t('tabs.translations', 'Translations') ?></span>
                    </button>
                </div>

                <!-- Tab: Basic Info -->
                <div class="tab-content active" id="tab-basic">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="entitySlug" data-i18n="form.fields.slug.label">
                                <?= __t('form.fields.slug.label', 'Slug') ?>
                            </label>
                            <input type="text" id="entitySlug" name="slug" class="form-control"
                                   data-i18n-placeholder="form.fields.slug.placeholder"
                                   placeholder="<?= __t('form.fields.slug.placeholder', 'store-slug') ?>">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="entityBranchCode" data-i18n="form.fields.branch_code.label">
                                <?= __t('form.fields.branch_code.label', 'Branch Code') ?>
                            </label>
                            <input type="text" id="entityBranchCode" name="branch_code" class="form-control"
                                   data-i18n-placeholder="form.fields.branch_code.placeholder"
                                   placeholder="<?= __t('form.fields.branch_code.placeholder', 'BR001') ?>">
                        </div>
                    </div>

                    <!-- Entity Type (Main / Branch) -->
                    <div class="form-row">
                        <div class="form-group">
                            <label for="entityType" data-i18n="form.fields.entity_type.label">
                                <?= __t('form.fields.entity_type.label', 'Entity Type') ?>
                            </label>
                            <select id="entityType" name="entity_type" class="form-control">
                                <option value="main" data-i18n="form.fields.entity_type.main"><?= __t('form.fields.entity_type.main', 'Main Entity') ?></option>
                                <option value="branch" data-i18n="form.fields.entity_type.branch"><?= __t('form.fields.entity_type.branch', 'Branch') ?></option>
                            </select>
                        </div>
                    </div>

                    <!-- Parent Entity (shown only for branches) -->
                    <div id="parentIdGroup" style="display:none">
                        <div class="form-row">
                            <div class="form-group full-width">
                                <label for="entityParentSearch" data-i18n="form.fields.parent_entity.label">
                                    <?= __t('form.fields.parent_entity.label', 'Search Parent Entity') ?>
                                </label>
                                <input type="text" id="entityParentSearch" class="form-control"
                                       style="margin-bottom:6px;"
                                       data-i18n-placeholder="form.fields.parent_entity.search_placeholder"
                                       placeholder="<?= __t('form.fields.parent_entity.search_placeholder', 'Type to filter entities...') ?>">
                                <select id="entityParentSelect" class="form-control" size="5" style="height:auto;">
                                    <option value=""><?= __t('form.fields.parent_entity.placeholder', '— Select parent entity —') ?></option>
                                </select>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group full-width">
                                <label for="entityParentId" data-i18n="form.fields.parent_id.label">
                                    <?= __t('form.fields.parent_id.label', 'Parent Entity ID') ?>
                                </label>
                                <div style="display:flex; gap:8px; align-items:flex-start;">
                                    <input type="number" id="entityParentId" name="parent_id" class="form-control"
                                           min="1" style="max-width:200px;"
                                           data-i18n-placeholder="form.fields.parent_id.placeholder"
                                           placeholder="<?= __t('form.fields.parent_id.placeholder', 'Enter parent entity ID') ?>">
                                    <button type="button" id="btnValidateParent" class="btn btn-outline">
                                        <i class="fas fa-search"></i>
                                        <?= __t('form.fields.parent_id.validate', 'Validate') ?>
                                    </button>
                                </div>
                                <div id="parentValidationResult" style="display:none; margin-top:6px; font-size:0.875rem; padding:6px 10px; border-radius:4px;"></div>
                            </div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="entityVendorType" data-i18n="form.fields.vendor_type.label">
                                <?= __t('form.fields.vendor_type.label', 'Vendor Type') ?>
                            </label>
                            <select id="entityVendorType" name="vendor_type" class="form-control">
                                <option value="product_seller" data-i18n="form.fields.vendor_type.product_seller">Product Seller</option>
                                <option value="service_provider" data-i18n="form.fields.vendor_type.service_provider">Service Provider</option>
                                <option value="both" data-i18n="form.fields.vendor_type.both">Both</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="entityStoreType" data-i18n="form.fields.store_type.label">
                                <?= __t('form.fields.store_type.label', 'Store Type') ?>
                            </label>
                            <select id="entityStoreType" name="store_type" class="form-control">
                                <option value="individual" data-i18n="form.fields.store_type.individual">Individual</option>
                                <option value="company" data-i18n="form.fields.store_type.company">Company</option>
                                <option value="brand" data-i18n="form.fields.store_type.brand">Brand</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="entityRegistrationNumber" data-i18n="form.fields.registration_number.label">
                                <?= __t('form.fields.registration_number.label', 'Registration Number') ?>
                            </label>
                            <input type="text" id="entityRegistrationNumber" name="registration_number" class="form-control"
                                   data-i18n-placeholder="form.fields.registration_number.placeholder"
                                   placeholder="<?= __t('form.fields.registration_number.placeholder', 'CR123456') ?>">
                        </div>

                        <div class="form-group">
                            <label for="entityTaxNumber" data-i18n="form.fields.tax_number.label">
                                <?= __t('form.fields.tax_number.label', 'Tax Number') ?>
                            </label>
                            <input type="text" id="entityTaxNumber" name="tax_number" class="form-control"
                                   data-i18n-placeholder="form.fields.tax_number.placeholder"
                                   placeholder="<?= __t('form.fields.tax_number.placeholder', 'VAT123456') ?>">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="entityStatus" data-i18n="form.fields.status.label">
                                <?= __t('form.fields.status.label', 'Status') ?>
                            </label>
                            <select id="entityStatus" name="status" class="form-control">
                                <option value="pending" data-i18n="form.fields.status.pending">Pending</option>
                                <option value="approved" data-i18n="form.fields.status.approved">Approved</option>
                                <option value="suspended" data-i18n="form.fields.status.suspended">Suspended</option>
                                <option value="rejected" data-i18n="form.fields.status.rejected">Rejected</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="entityIsVerified" data-i18n="form.fields.is_verified.label">
                                <?= __t('form.fields.is_verified.label', 'Verified') ?>
                            </label>
                            <select id="entityIsVerified" name="is_verified" class="form-control">
                                <option value="0" data-i18n="form.fields.is_verified.no">No</option>
                                <option value="1" data-i18n="form.fields.is_verified.yes">Yes</option>
                            </select>
                        </div>
                    </div>

                    <!-- Timezone -->
                    <div class="form-row">
                        <div class="form-group">
                            <label for="entityTimezoneId" data-i18n="form.fields.timezone.label">
                                <?= __t('form.fields.timezone.label', 'Timezone') ?>
                            </label>
                            <select id="entityTimezoneId" name="timezone_id" class="form-control">
                                <option value=""><?= __t('form.fields.timezone.placeholder', '— Select timezone —') ?></option>
                                <!-- populated by JS from /api/timezones -->
                            </select>
                        </div>
                    </div>

                    <!-- English Content (primary language) -->
                    <div class="english-content-section" style="margin-top:28px;padding-top:20px;border-top:2px solid var(--primary-color,#3b82f6);">
                        <h4 style="margin-bottom:16px;color:var(--text-primary,#fff);display:flex;align-items:center;gap:10px;">
                            <span style="background:var(--primary-color,#3b82f6);color:#fff;padding:3px 10px;border-radius:20px;font-size:0.75rem;font-weight:700;">EN</span>
                            <?= __t('form.sections.english_content', 'English Content') ?>
                            <span style="color:var(--text-secondary,#94a3b8);font-size:0.8rem;font-weight:400;margin-left:6px;">(<?= __t('form.sections.english_required', 'Default language — required') ?>)</span>
                        </h4>

                        <div class="form-row">
                            <div class="form-group" style="flex:1;">
                                <label for="enEntityName" class="required"><?= __t('form.fields.en_store_name.label', 'Store Name (English)') ?></label>
                                <input type="text" id="enEntityName" name="en_store_name" class="form-control" required
                                       placeholder="<?= __t('form.fields.en_store_name.placeholder', 'Enter store name in English') ?>">
                                <div class="invalid-feedback"><?= __t('form.fields.en_store_name.required', 'English store name is required') ?></div>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group" style="flex:1;">
                                <label for="enEntityDescription"><?= __t('form.fields.en_description.label', 'Description (English)') ?></label>
                                <textarea id="enEntityDescription" name="en_description" class="form-control" rows="3"
                                          placeholder="<?= __t('form.fields.en_description.placeholder', 'Enter entity description in English') ?>"></textarea>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group" style="flex:1;">
                                <label for="enEntityMetaTitle"><?= __t('form.fields.en_meta_title.label', 'Meta Title (English)') ?></label>
                                <input type="text" id="enEntityMetaTitle" name="en_meta_title" class="form-control"
                                       placeholder="<?= __t('form.fields.en_meta_title.placeholder', 'SEO meta title in English') ?>">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group" style="flex:1;">
                                <label for="enEntityMetaDescription"><?= __t('form.fields.en_meta_description.label', 'Meta Description (English)') ?></label>
                                <textarea id="enEntityMetaDescription" name="en_meta_description" class="form-control" rows="2"
                                          placeholder="<?= __t('form.fields.en_meta_description.placeholder', 'SEO meta description in English') ?>"></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tab: Contact -->
                <div class="tab-content" id="tab-contact" style="display:none">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="entityPhone" class="required" data-i18n="form.fields.phone.label">
                                <?= __t('form.fields.phone.label', 'Phone') ?>
                            </label>
                            <input type="text" id="entityPhone" name="phone" class="form-control" required
                                   data-i18n-placeholder="form.fields.phone.placeholder"
                                   placeholder="<?= __t('form.fields.phone.placeholder', '+966 50 123 4567') ?>">
                        </div>

                        <div class="form-group">
                            <label for="entityMobile" data-i18n="form.fields.mobile.label">
                                <?= __t('form.fields.mobile.label', 'Mobile') ?>
                            </label>
                            <input type="text" id="entityMobile" name="mobile" class="form-control"
                                   data-i18n-placeholder="form.fields.mobile.placeholder"
                                   placeholder="<?= __t('form.fields.mobile.placeholder', '+966 55 123 4567') ?>">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="entityEmail" class="required" data-i18n="form.fields.email.label">
                                <?= __t('form.fields.email.label', 'Email') ?>
                            </label>
                            <input type="email" id="entityEmail" name="email" class="form-control" required
                                   data-i18n-placeholder="form.fields.email.placeholder"
                                   placeholder="<?= __t('form.fields.email.placeholder', 'store@example.com') ?>">
                        </div>

                        <div class="form-group">
                            <label for="entityWebsite" data-i18n="form.fields.website.label">
                                <?= __t('form.fields.website.label', 'Website') ?>
                            </label>
                            <input type="url" id="entityWebsite" name="website_url" class="form-control"
                                   data-i18n-placeholder="form.fields.website.placeholder"
                                   placeholder="<?= __t('form.fields.website.placeholder', 'https://example.com') ?>">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group full-width">
                            <label for="entitySuspensionReason" data-i18n="form.fields.suspension_reason.label">
                                <?= __t('form.fields.suspension_reason.label', 'Suspension Reason') ?>
                            </label>
                            <textarea id="entitySuspensionReason" name="suspension_reason" class="form-control" rows="3"
                                      data-i18n-placeholder="form.fields.suspension_reason.placeholder"
                                      placeholder="<?= __t('form.fields.suspension_reason.placeholder', 'Reason for suspension...') ?>"></textarea>
                        </div>
                    </div>
                </div>

                <!-- Tab: Settings -->
                <div class="tab-content" id="tab-settings" style="display:none">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="settingAutoAcceptOrders" data-i18n="form.fields.auto_accept_orders.label">
                                <?= __t('form.fields.auto_accept_orders.label', 'Auto Accept Orders') ?>
                            </label>
                            <select id="settingAutoAcceptOrders" name="auto_accept_orders" class="form-control">
                                <option value="0" data-i18n="form.fields.auto_accept_orders.no">No</option>
                                <option value="1" data-i18n="form.fields.auto_accept_orders.yes">Yes</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="settingAllowCod" data-i18n="form.fields.allow_cod.label">
                                <?= __t('form.fields.allow_cod.label', 'Allow Cash on Delivery') ?>
                            </label>
                            <select id="settingAllowCod" name="allow_cod" class="form-control">
                                <option value="0" data-i18n="form.fields.allow_cod.no">No</option>
                                <option value="1" data-i18n="form.fields.allow_cod.yes">Yes</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="settingMinOrderAmount" data-i18n="form.fields.min_order_amount.label">
                                <?= __t('form.fields.min_order_amount.label', 'Min Order Amount') ?>
                            </label>
                            <input type="number" id="settingMinOrderAmount" name="min_order_amount" class="form-control" step="0.01" min="0" value="0.00">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="settingAllowOnlineBooking" data-i18n="form.fields.allow_online_booking.label">
                                <?= __t('form.fields.allow_online_booking.label', 'Allow Online Booking') ?>
                            </label>
                            <select id="settingAllowOnlineBooking" name="allow_online_booking" class="form-control">
                                <option value="0" data-i18n="form.fields.allow_online_booking.no">No</option>
                                <option value="1" data-i18n="form.fields.allow_online_booking.yes">Yes</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="settingBookingWindowDays" data-i18n="form.fields.booking_window_days.label">
                                <?= __t('form.fields.booking_window_days.label', 'Booking Window (Days)') ?>
                            </label>
                            <input type="number" id="settingBookingWindowDays" name="booking_window_days" class="form-control" min="0" value="0">
                        </div>

                        <div class="form-group">
                            <label for="settingMaxBookingsPerSlot" data-i18n="form.fields.max_bookings_per_slot.label">
                                <?= __t('form.fields.max_bookings_per_slot.label', 'Max Bookings per Slot') ?>
                            </label>
                            <input type="number" id="settingMaxBookingsPerSlot" name="max_bookings_per_slot" class="form-control" min="0" value="0">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="settingShowReviews" data-i18n="form.fields.show_reviews.label">
                                <?= __t('form.fields.show_reviews.label', 'Show Reviews') ?>
                            </label>
                            <select id="settingShowReviews" name="show_reviews" class="form-control">
                                <option value="1" data-i18n="form.fields.show_reviews.yes">Yes</option>
                                <option value="0" data-i18n="form.fields.show_reviews.no">No</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="settingShowContactInfo" data-i18n="form.fields.show_contact_info.label">
                                <?= __t('form.fields.show_contact_info.label', 'Show Contact Info') ?>
                            </label>
                            <select id="settingShowContactInfo" name="show_contact_info" class="form-control">
                                <option value="1" data-i18n="form.fields.show_contact_info.yes">Yes</option>
                                <option value="0" data-i18n="form.fields.show_contact_info.no">No</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="settingFeaturedInApp" data-i18n="form.fields.featured_in_app.label">
                                <?= __t('form.fields.featured_in_app.label', 'Featured in App') ?>
                            </label>
                            <select id="settingFeaturedInApp" name="featured_in_app" class="form-control">
                                <option value="0" data-i18n="form.fields.featured_in_app.no">No</option>
                                <option value="1" data-i18n="form.fields.featured_in_app.yes">Yes</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Tab: Working Hours -->
                <div class="tab-content" id="tab-working_hours" style="display:none">
                    <div class="working-hours-container">
                        <h4 style="margin-bottom: 15px; color: var(--text-primary);" data-i18n="form.sections.working_hours">
                            <?= __t('form.sections.working_hours', 'Working Hours') ?>
                        </h4>
                        
                        <div id="workingHoursList">
                            <!-- Days will be generated by JavaScript -->
                        </div>
                        
                        <div class="form-actions" style="margin-top: 20px;">
                            <button type="button" id="btnApplyToAll" class="btn btn-secondary" data-i18n="form.buttons.apply_to_all">
                                <?= __t('form.buttons.apply_to_all', 'Apply to All Days') ?>
                            </button>
                            <button type="button" id="btnResetHours" class="btn btn-outline" data-i18n="form.buttons.reset_hours">
                                <?= __t('form.buttons.reset_hours', 'Reset Hours') ?>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Tab: Attributes -->
                <div class="tab-content" id="tab-attributes" style="display:none">
                    <div style="display:flex; gap:10px; margin-bottom:15px;">
                        <select id="entityAttrSelect" class="form-control" style="flex:1;"></select>
                        <button type="button" id="btnAddEntityAttribute" class="btn btn-primary" data-i18n="form.buttons.add_attribute">
                            <?= __t('form.buttons.add_attribute', 'Add Attribute') ?>
                        </button>
                    </div>
                    <div id="entityAttributesList"></div>
                </div>

                <!-- Tab: Media -->
                <div class="tab-content" id="tab-media" style="display:none">
                    <!-- Logo -->
                    <div class="media-section" style="margin-bottom: 30px;">
                        <h5 style="margin-bottom: 15px; color: var(--text-primary);" data-i18n="form.sections.logo">
                            <?= __t('form.sections.logo', 'Entity Logo') ?>
                        </h5>
                        <div class="image-upload-section">
                            <button type="button" data-image-type="4" class="btnSelectMedia btn btn-secondary" style="margin-bottom: 15px;" data-i18n="common.select_image">
                                <?= __t('common.select_image', 'Select Logo from Studio') ?>
                            </button>
                            <div id="logoPreview" class="single-image-preview">
                                <div class="placeholder" data-i18n="form.media.no_logo">
                                    <?= __t('form.media.no_logo', 'No logo selected') ?>
                                </div>
                            </div>
                            <input type="text" id="logoUrlDisplay" class="form-control url-display" readonly 
                                   placeholder="<?= __t('form.media.logo_url', 'Logo URL will appear here') ?>">
                        </div>
                    </div>

                    <!-- Cover -->
                    <div class="media-section" style="margin-bottom: 30px;">
                        <h5 style="margin-bottom: 15px; color: var(--text-primary);" data-i18n="form.sections.cover">
                            <?= __t('form.sections.cover', 'Entity Cover Image') ?>
                        </h5>
                        <div class="image-upload-section">
                            <button type="button" data-image-type="5" class="btnSelectMedia btn btn-secondary" style="margin-bottom: 15px;" data-i18n="common.select_image">
                                <?= __t('common.select_image', 'Select Cover from Studio') ?>
                            </button>
                            <div id="coverPreview" class="single-image-preview">
                                <div class="placeholder" data-i18n="form.media.no_cover">
                                    <?= __t('form.media.no_cover', 'No cover image selected') ?>
                                </div>
                            </div>
                            <input type="text" id="coverUrlDisplay" class="form-control url-display" readonly 
                                   placeholder="<?= __t('form.media.cover_url', 'Cover URL will appear here') ?>">
                        </div>
                    </div>

                    <!-- License -->
                    <div class="media-section">
                        <h5 style="margin-bottom: 15px; color: var(--text-primary);" data-i18n="form.sections.license">
                            <?= __t('form.sections.license', 'Entity License') ?>
                        </h5>
                        <div class="image-upload-section">
                            <button type="button" data-image-type="6" class="btnSelectMedia btn btn-secondary" style="margin-bottom: 15px;" data-i18n="common.select_image">
                                <?= __t('common.select_image', 'Select License from Studio') ?>
                            </button>
                            <div id="licensePreview" class="single-image-preview">
                                <div class="placeholder" data-i18n="form.media.no_license">
                                    <?= __t('form.media.no_license', 'No license document selected') ?>
                                </div>
                            </div>
                            <input type="text" id="licenseUrlDisplay" class="form-control url-display" readonly 
                                   placeholder="<?= __t('form.media.license_url', 'License URL will appear here') ?>">
                        </div>
                    </div>
                </div>

                <!-- Tab: Address -->
                <div class="tab-content" id="tab-address" style="display:none">
                    <div class="address-section">
                        <h4 style="margin-bottom: 15px; color: var(--text-primary);" data-i18n="form.sections.address">
                            <?= __t('form.sections.address', 'Entity Address') ?>
                        </h4>
                        <div id="addressEmbeddedContainer" style="border: 1px solid var(--border-color); border-radius: 8px; padding: 15px; background: var(--card-bg);">
                            <div class="loading-state" id="addressLoading">
                                <div class="spinner"></div>
                                <p data-i18n="common.loading"><?= __t('common.loading', 'Loading address form...') ?></p>
                            </div>
                            <!-- Address fragment will be loaded here -->
                        </div>
                        <input type="hidden" id="addressData" name="address_data">
                    </div>
                </div>

                <!-- Tab: Translations -->
                <div class="tab-content" id="tab-translations" style="display:none">
                    <div class="translations-section">
                        <h4 style="margin-bottom:12px; color:var(--text-primary,#fff); border-bottom:1px solid var(--border-color,#263044); padding-bottom:8px;">
                            <i class="fas fa-language"></i> <?= __t('form.sections.translations', 'Translations') ?>
                        </h4>
                        <p style="font-size:0.88rem;color:var(--text-secondary,#94a3b8);margin-bottom:16px;padding:10px 14px;background:var(--card-bg,#081127);border-radius:6px;border:1px solid var(--border-color,#263044);">
                            <i class="fas fa-info-circle" style="color:var(--primary-color,#3b82f6);margin-<?= $dir === 'rtl' ? 'left' : 'right' ?>:6px;"></i>
                            <?= __t('form.translations.english_note', 'The') ?>
                            <strong style="color:var(--text-primary,#fff);">English</strong>
                            <?= __t('form.translations.english_in_basic', 'translation fields are in the') ?>
                            <strong style="color:var(--text-primary,#fff);"><?= __t('tabs.basic', 'Basic Info') ?></strong>
                            <?= __t('form.translations.tab_hint', 'tab. Use this tab to add translations for other languages (Arabic, French, etc.).') ?>
                        </p>
                        <div id="entityTranslations" class="translation-panels"></div>
                        <div class="form-group" style="margin-top:12px;">
                            <label for="entityLangSelect" data-i18n="form.translations.select_lang">Select Language</label>
                            <div style="display:flex; gap:8px; align-items:flex-end;">
                                <select id="entityLangSelect" class="form-control" style="flex:1;">
                                    <option value=""><?= __t('form.translations.choose_lang', 'Choose language') ?></option>
                                </select>
                                <button type="button" id="entityAddLangBtn" class="btn btn-primary">
                                    <i class="fas fa-plus"></i> <?= __t('form.translations.add_translation', 'Add Translation') ?>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary" id="btnSubmitForm">
                        <i class="fas fa-save"></i>
                        <span data-i18n="form.buttons.save"><?= __t('form.buttons.save', 'Save') ?></span>
                    </button>
                    <button type="button" class="btn btn-outline" id="btnCancelForm" data-i18n="form.buttons.cancel">
                        <?= __t('form.buttons.cancel', 'Cancel') ?>
                    </button>
                    <?php if ($canDelete): ?>
                    <button type="button" id="btnDeleteEntity" class="btn btn-danger" style="display:none">
                        <i class="fas fa-trash"></i>
                        <span data-i18n="table.actions.delete"><?= __t('table.actions.delete', 'Delete') ?></span>
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
                    <label for="searchInput" data-i18n="filters.search">
                        <?= __t('filters.search', 'Search') ?>
                    </label>
                    <input type="text" id="searchInput" class="form-control"
                           data-i18n-placeholder="filters.search_placeholder"
                           placeholder="<?= __t('filters.search_placeholder', 'Search entities...') ?>">
                </div>

                <?php if (is_super_admin()): ?>
                <div class="filter-group">
                    <label for="tenantFilter" data-i18n="filters.tenant_id">
                        <?= __t('filters.tenant_id', 'Tenant ID') ?>
                    </label>
                    <input type="number" id="tenantFilter" class="form-control" value="<?= $tenantId ?>"
                           data-i18n-placeholder="filters.tenant_placeholder"
                           placeholder="<?= __t('filters.tenant_placeholder', 'Filter by tenant') ?>">
                </div>
                <?php endif; ?>

                <div class="filter-group">
                    <label for="statusFilter" data-i18n="filters.status">
                        <?= __t('filters.status', 'Status') ?>
                    </label>
                    <select id="statusFilter" class="form-control">
                        <option value="" data-i18n="filters.status_options.all">All Status</option>
                        <option value="pending" data-i18n="filters.status_options.pending">Pending</option>
                        <option value="approved" data-i18n="filters.status_options.approved">Approved</option>
                        <option value="suspended" data-i18n="filters.status_options.suspended">Suspended</option>
                        <option value="rejected" data-i18n="filters.status_options.rejected">Rejected</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label for="vendorTypeFilter" data-i18n="filters.vendor_type">Vendor Type</label>
                    <select id="vendorTypeFilter" class="form-control">
                        <option value="">All Types</option>
                        <option value="product_seller">Product Seller</option>
                        <option value="service_provider">Service Provider</option>
                        <option value="both">Both</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label for="storeTypeFilter" data-i18n="filters.store_type">Store Type</label>
                    <select id="storeTypeFilter" class="form-control">
                        <option value="">All Types</option>
                        <option value="individual">Individual</option>
                        <option value="company">Company</option>
                        <option value="brand">Brand</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label for="verifiedFilter" data-i18n="filters.verified">Verified</label>
                    <select id="verifiedFilter" class="form-control">
                        <option value="">All</option>
                        <option value="1">Verified</option>
                        <option value="0">Not Verified</option>
                    </select>
                </div>

                <div class="filter-actions">
                    <button id="btnApplyFilters" class="btn btn-secondary" data-i18n="filters.apply">
                        <?= __t('filters.apply', 'Apply') ?>
                    </button>
                    <button id="btnResetFilters" class="btn btn-outline" data-i18n="filters.reset">
                        <?= __t('filters.reset', 'Reset') ?>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Results Count -->
    <div id="resultsCount" class="results-count" style="padding:12px 16px; margin-bottom:12px; background:var(--card-bg,#081127); border:1px solid var(--border-color,#263044); border-radius:8px; display:none;">
        <span style="color:var(--text-secondary,#94a3b8); font-size:0.9rem;">
            <i class="fas fa-building"></i> 
            <span id="resultsCountText"></span>
        </span>
    </div>

    <!-- Table -->
    <div class="card table-card">
        <div class="card-body">
            <div id="tableLoading" class="loading-state">
                <div class="spinner"></div>
                <p data-i18n="entities.loading"><?= __t('entities.loading', 'Loading...') ?></p>
            </div>

            <div id="tableContainer" style="display:none">
                <div class="table-responsive">
                    <table class="data-table" id="entitiesTable">
                        <thead>
                            <tr>
                                <th data-i18n="table.headers.id">ID</th>
                                <?php if (is_super_admin()): ?>
                                <th data-i18n="table.headers.tenant">Tenant</th>
                                <?php endif; ?>
                                <th data-i18n="table.headers.logo">Logo</th>
                                <th data-i18n="table.headers.store_name">Store Name</th>
                                <th data-i18n="table.headers.branch_code">Branch Code</th>
                                <th data-i18n="table.headers.vendor_type">Vendor Type</th>
                                <th data-i18n="table.headers.phone">Phone</th>
                                <th data-i18n="table.headers.email">Email</th>
                                <th data-i18n="table.headers.status">Status</th>
                                <th data-i18n="table.headers.verified">Verified</th>
                                <th data-i18n="table.headers.actions">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="tableBody"></tbody>
                    </table>
                </div>

                <div class="pagination-wrapper">
                    <div class="pagination-info">
                        <span data-i18n="pagination.showing">Showing</span>
                        <span id="paginationInfo">0-0 of 0</span>
                    </div>
                    <div class="pagination" id="pagination"></div>
                </div>
            </div>

            <div id="emptyState" class="empty-state" style="display:none">
                <div class="empty-icon">🏢</div>
                <h3 data-i18n="table.empty.title">No Entities Found</h3>
                <p data-i18n="table.empty.message">Start by adding your first entity</p>
                <?php if ($canCreate): ?>
                <button class="btn btn-primary" onclick="if(window.Entities)window.Entities.add()">
                    <i class="fas fa-plus"></i>
                    <span data-i18n="table.empty.add_first">Add First Entity</span>
                </button>
                <?php endif; ?>
            </div>

            <div id="errorState" class="error-state" style="display:none">
                <div class="error-icon">⚠️</div>
                <h3 data-i18n="messages.error.load_failed">Error Loading Data</h3>
                <p id="errorMessage"></p>
                <button id="btnRetry" class="btn btn-secondary" data-i18n="entities.retry">Retry</button>
            </div>
        </div>
    </div>

    <!-- Media Studio Modal -->
    <div id="mediaStudioModal" class="modal" style="display:none">
        <div class="modal-content">
            <span class="close" id="mediaStudioClose">&times;</span>
            <iframe id="mediaStudioFrame" style="width:100%; height:500px; border:none;"></iframe>
        </div>
    </div>

</div>

<!-- Expose client-side globals for the module -->
<script type="text/javascript">
window.APP_CONFIG = window.APP_CONFIG || {};
window.APP_CONFIG.API_BASE = window.APP_CONFIG.API_BASE || '<?= $apiBase ?>';
window.APP_CONFIG.TENANT_ID = window.APP_CONFIG.TENANT_ID || <?= $tenantId ?>;
window.APP_CONFIG.CSRF_TOKEN = window.APP_CONFIG.CSRF_TOKEN || '<?= addslashes($csrf) ?>';
window.APP_CONFIG.USER_ID = window.APP_CONFIG.USER_ID || <?= admin_user_id() ?>;

window.USER_LANGUAGE = window.USER_LANGUAGE || '<?= addslashes($lang) ?>';
window.USER_DIRECTION = window.USER_DIRECTION || '<?= addslashes($dir) ?>';
window.CSRF_TOKEN = window.CSRF_TOKEN || '<?= addslashes($csrf) ?>';

// Page permissions available to JS
window.PAGE_PERMISSIONS = <?= json_encode([
    'canCreate' => $canCreate,
    'canEdit' => $canEdit,
    'canDelete' => $canDelete,
    'canViewAll' => $canViewAll,
    'canViewOwn' => $canViewOwn,
    'canViewTenant' => $canViewTenant,
    'canEditAll' => $canEditAll,
    'canEditOwn' => $canEditOwn,
    'canDeleteAll' => $canDeleteAll,
    'canDeleteOwn' => $canDeleteOwn,
    'isSuperAdmin' => is_super_admin()
], JSON_UNESCAPED_UNICODE) ?>;
</script>

<script type="text/javascript">
window.ENTITIES_CONFIG = {
    apiUrl: '<?= $apiBase ?>/entities',
    attributesApi: '<?= $apiBase ?>/entities_attributes',
    attributeValuesApi: '<?= $apiBase ?>/entities_attribute_values',
    settingsApi: '<?= $apiBase ?>/entity_settings',
    workingHoursApi: '<?= $apiBase ?>/entities_working_hours',
    languagesApi: '<?= $apiBase ?>/languages',
    timezonesApi: '<?= $apiBase ?>/timezones',
    tenantsApi: '<?= $apiBase ?>/tenants',
    entityTypesApi: '<?= $apiBase ?>/entity_types',
    addressesApi: '<?= $apiBase ?>/addresses',
    csrfToken: '<?= addslashes($csrf) ?>',
    lang: '<?= addslashes($lang) ?>',
    itemsPerPage: 25,
    mediaStudioBase: '/admin/fragments/media_studio.php',
    addressesFragment: '/admin/fragments/addresses.php'
};
</script>

<!-- Translation loader (runs early) -->
<script type="text/javascript">
(function(){
    async function applyTranslations() {
        try {
            const lang = window.USER_LANGUAGE || 'en';
            const url = `/languages/Entities/${encodeURIComponent(lang)}.json`;
            console.log('[Entities] Loading translations from', url);
            const res = await fetch(url, { credentials: 'same-origin' });
            if (!res.ok) throw new Error('Translation fetch failed: ' + res.status);
            const translations = await res.json();
            window.ENTITIES_TRANSLATIONS = translations;
            // apply translations to elements with data-i18n
            const container = document.getElementById('entitiesPageContainer');
            if (!container) return;
            container.querySelectorAll('[data-i18n]').forEach(el => {
                const key = el.getAttribute('data-i18n');
                const txt = key.split('.').reduce((o,k) => (o && o[k] !== undefined) ? o[k] : null, translations);
                if (txt !== null && txt !== undefined) {
                    if (el.tagName === 'INPUT' && el.hasAttribute('placeholder')) {
                        el.placeholder = txt;
                    } else {
                        el.textContent = txt;
                    }
                }
            });
            // placeholders
            container.querySelectorAll('[data-i18n-placeholder]').forEach(el=>{
                const key = el.getAttribute('data-i18n-placeholder');
                const txt = key.split('.').reduce((o,k) => (o && o[k] !== undefined) ? o[k] : null, translations);
                if (txt !== null && txt !== undefined) el.placeholder = txt;
            });
            console.log('[Entities] Translations applied');
        } catch (err) {
            console.warn('[Entities] Translation load/apply failed:', err);
        }
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', applyTranslations);
    } else {
        setTimeout(applyTranslations, 50);
    }
})();
</script>

<!-- Page Permissions JSON for scripts that prefer it in DOM -->
<script id="pagePermissions" type="application/json">
<?= json_encode([
    'canCreate' => $canCreate,
    'canEdit' => $canEdit,
    'canDelete' => $canDelete,
    'canViewAll' => $canViewAll,
    'canViewOwn' => $canViewOwn,
    'canViewTenant' => $canViewTenant,
    'canEditAll' => $canEditAll,
    'canEditOwn' => $canEditOwn,
    'canDeleteAll' => $canDeleteAll,
    'canDeleteOwn' => $canDeleteOwn,
    'isSuperAdmin' => is_super_admin()
], JSON_UNESCAPED_UNICODE) ?>
</script>

<script id="ENTITIES_INITIAL_PAYLOAD" type="application/json">
<?= json_encode(['items' => [], 'meta' => ['page' => 1, 'per_page' => 25, 'total' => 0]]) ?>
</script>

<!-- Load AdminFramework + Page module when embedded; otherwise load normally -->
<?php if ($isFragment): ?>
<script src="/admin/assets/js/admin_framework.js?v=<?= time() ?>"></script>
<script src="/admin/assets/js/pages/entities.js?v=<?= time() ?>"></script>

<script>
(function(){
    console.log('[Entities] Embedded mode - waiting for module...');
    var attempts = 0, maxAttempts = 50;
    var interval = setInterval(function(){
        attempts++;
        if (window.Entities && typeof window.Entities.init === 'function') {
            clearInterval(interval);
            console.log('[Entities] Module ready - initializing (attempt ' + attempts + ')...');
            try {
                var maybePromise = window.Entities.init();
                if (maybePromise && typeof maybePromise.then === 'function') {
                    maybePromise.then(function(){
                        console.log('[Entities] ✓ Initialized successfully');
                    }).catch(function(e){
                        console.error('[Entities] Init failed:', e);
                    });
                }
            } catch (e) {
                console.error('[Entities] Init threw:', e);
            }
        } else if (attempts > maxAttempts) {
            clearInterval(interval);
            console.error('[Entities] Timeout waiting for module after ' + (maxAttempts * 100) + 'ms');
        }
    }, 100);
})();
</script>
<?php else: ?>
<script src="/admin/assets/js/pages/entities.js?v=<?= time() ?>"></script>
<script>
// Standalone mode init
(function(){
    function tryInit() {
        if (window.Entities && typeof window.Entities.init === 'function') {
            window.Entities.init().catch(function(e){ console.error('[Entities] Init failed', e); });
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
// Load footer if standalone
if (!$isFragment) {
    require_once __DIR__ . '/../includes/footer.php';
}
?>