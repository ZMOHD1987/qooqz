<?php
declare(strict_types=1);

/**
 * /admin/fragments/notification.php
 * Production – Notification System Admin Panel (5 tabs)
 */

$isAjax     = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
              strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
$isEmbedded = isset($_GET['embedded']) || isset($_POST['embedded']);
$isFragment = $isAjax || $isEmbedded;

if ($isFragment) {
    require_once __DIR__ . '/../includes/admin_context.php';
} else {
    require_once __DIR__ . '/../includes/header.php';
}

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

$user     = admin_user();
$lang     = admin_lang();
$dir      = admin_dir();
$csrf     = admin_csrf();
$tenantId = admin_tenant_id();

// Permissions
$canCreate   = can_create('notifications')   || can('notifications.manage');
$canViewAll  = can_view_all('notifications')  || is_super_admin();
$canViewOwn  = can_view_own('notifications');
$canEditAll  = can_edit_all('notifications')  || can('notifications.manage');
$canDeleteAll= can_delete_all('notifications')|| can('notifications.manage');
$canView     = $canViewAll || $canViewOwn;
$canEdit     = $canEditAll;
$canDelete   = $canDeleteAll;

if (!$canView && !is_super_admin()) {
    if ($isFragment) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        exit;
    } else {
        http_response_code(403);
        die('Access denied');
    }
}

function __t($key, $fallback = '') {
    if (function_exists('i18n_get')) {
        $v = i18n_get($key);
        return $v ?? ($fallback ?? $key);
    }
    return $fallback ?? $key;
}

$apiBase = '/api';
?>
<?php if ($isFragment): ?>
<link rel="stylesheet" href="/admin/assets/css/pages/notification.css?v=<?= time() ?>">
<?php endif; ?>

<meta data-page="notifications"
      data-i18n-files="/admin/languages/Notifications/<?= rawurlencode($lang) ?>.json">

<div class="page-container" id="notificationPageContainer" dir="<?= htmlspecialchars($dir) ?>">

    <!-- Page Header -->
    <div class="page-header">
        <div class="page-header-content">
            <h1 class="page-title" data-i18n="notifications.title"><?= __t('notifications.title', 'Notifications') ?></h1>
            <p class="page-subtitle" data-i18n="notifications.subtitle"><?= __t('notifications.subtitle', 'Manage system notifications and delivery channels') ?></p>
        </div>
        <div class="page-header-actions">
            <?php if ($canCreate): ?>
            <button id="btnAddRecord" class="btn btn-primary">
                <i class="fas fa-plus"></i>
                <span id="btnAddLabel"><?= __t('types.add_new', 'Add') ?></span>
            </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Tabs -->
    <div class="notif-tabs">
        <button class="notif-tab-btn active" data-tab="types">
            <i class="fas fa-tags"></i>
            <span data-i18n="notifications.tabs.types"><?= __t('notifications.tabs.types', 'Types') ?></span>
        </button>
        <button class="notif-tab-btn" data-tab="list">
            <i class="fas fa-bell"></i>
            <span data-i18n="notifications.tabs.list"><?= __t('notifications.tabs.list', 'Notifications') ?></span>
        </button>
        <button class="notif-tab-btn" data-tab="channels">
            <i class="fas fa-broadcast-tower"></i>
            <span data-i18n="notifications.tabs.channels"><?= __t('notifications.tabs.channels', 'Channels') ?></span>
        </button>
        <button class="notif-tab-btn" data-tab="counters">
            <i class="fas fa-tally"></i>
            <span data-i18n="notifications.tabs.counters"><?= __t('notifications.tabs.counters', 'Counters') ?></span>
        </button>
        <button class="notif-tab-btn" data-tab="deliveries">
            <i class="fas fa-paper-plane"></i>
            <span data-i18n="notifications.tabs.deliveries"><?= __t('notifications.tabs.deliveries', 'Deliveries') ?></span>
        </button>
    </div>

    <!-- ── FORM CONTAINER (shared, reused per tab) ── -->
    <div id="notifFormContainer" class="card form-card" style="display:none">
        <div class="card-header">
            <h3 class="card-title" id="notifFormTitle"><?= __t('form.add_title', 'Add Record') ?></h3>
            <button type="button" class="btn btn-sm btn-outline" id="btnCloseForm">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="card-body">
            <form id="notifForm" novalidate>
                <input type="hidden" id="formId"     name="id">
                <input type="hidden" id="formTab"    name="_tab">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">

                <!-- ── TYPES fields ── -->
                <div class="notif-form-section" data-form-tab="types">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="fCode" class="required" data-i18n="form.fields.code.label"><?= __t('form.fields.code.label','Code') ?></label>
                            <input type="text" id="fCode" name="code" class="form-control"
                                   placeholder="<?= __t('form.fields.code.placeholder','Enter code') ?>"
                                   data-i18n-placeholder="form.fields.code.placeholder">
                        </div>
                        <div class="form-group">
                            <label for="fName" class="required" data-i18n="form.fields.name.label"><?= __t('form.fields.name.label','Name') ?></label>
                            <input type="text" id="fName" name="name" class="form-control"
                                   placeholder="<?= __t('form.fields.name.placeholder','Enter name') ?>"
                                   data-i18n-placeholder="form.fields.name.placeholder">
                        </div>
                        <div class="form-group">
                            <label for="fIsActive" data-i18n="form.fields.is_active.label"><?= __t('form.fields.is_active.label','Status') ?></label>
                            <select id="fIsActive" name="is_active" class="form-control">
                                <option value="1" data-i18n="form.fields.is_active.active"><?= __t('form.fields.is_active.active','Active') ?></option>
                                <option value="0" data-i18n="form.fields.is_active.inactive"><?= __t('form.fields.is_active.inactive','Inactive') ?></option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="fDescription" data-i18n="form.fields.description.label"><?= __t('form.fields.description.label','Description') ?></label>
                        <textarea id="fDescription" name="description" class="form-control" rows="2"
                                  placeholder="<?= __t('form.fields.description.placeholder','Enter description') ?>"
                                  data-i18n-placeholder="form.fields.description.placeholder"></textarea>
                    </div>
                    <div class="form-group">
                        <label for="fDefaultTemplate" data-i18n="form.fields.default_template.label"><?= __t('form.fields.default_template.label','Default Template') ?></label>
                        <textarea id="fDefaultTemplate" name="default_template" class="form-control" rows="4"
                                  placeholder="<?= __t('form.fields.default_template.placeholder','Enter default template') ?>"
                                  data-i18n-placeholder="form.fields.default_template.placeholder"></textarea>
                    </div>
                </div>

                <!-- ── NOTIFICATIONS fields ── -->
                <div class="notif-form-section" data-form-tab="list" style="display:none">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="fTenantId" class="required" data-i18n="form.fields.tenant_id.label"><?= __t('form.fields.tenant_id.label','Tenant ID') ?></label>
                            <input type="number" id="fTenantId" name="tenant_id" class="form-control"
                                   value="<?= $tenantId ?>" <?= is_super_admin() ? '' : 'readonly' ?>
                                   data-lookup="tenant">
                            <span class="id-lookup-hint" data-for="fTenantId"></span>
                        </div>
                        <div class="form-group">
                            <label for="fNotifTypeId" data-i18n="form.fields.notification_type_id.label"><?= __t('form.fields.notification_type_id.label','Notification Type') ?></label>
                            <select id="fNotifTypeId" name="notification_type_id" class="form-control">
                                <option value="">-- Select Type --</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="fPriority" data-i18n="form.fields.priority.label"><?= __t('form.fields.priority.label','Priority') ?></label>
                            <select id="fPriority" name="priority" class="form-control">
                                <option value="low"    data-i18n="form.fields.priority.low"><?= __t('form.fields.priority.low','Low') ?></option>
                                <option value="normal" data-i18n="form.fields.priority.normal" selected><?= __t('form.fields.priority.normal','Normal') ?></option>
                                <option value="high"   data-i18n="form.fields.priority.high"><?= __t('form.fields.priority.high','High') ?></option>
                                <option value="urgent" data-i18n="form.fields.priority.urgent"><?= __t('form.fields.priority.urgent','Urgent') ?></option>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="fSenderEntityId" data-i18n="form.fields.sender_entity_id.label"><?= __t('form.fields.sender_entity_id.label','Sender Entity ID') ?></label>
                            <input type="number" id="fSenderEntityId" name="sender_entity_id" class="form-control"
                                   placeholder="<?= __t('form.fields.sender_entity_id.placeholder','Optional') ?>"
                                   data-lookup="entity">
                            <span class="id-lookup-hint" data-for="fSenderEntityId"></span>
                        </div>
                        <div class="form-group">
                            <label for="fEntityId" data-i18n="form.fields.entity_id.label"><?= __t('form.fields.entity_id.label','Entity ID') ?></label>
                            <input type="number" id="fEntityId" name="entity_id" class="form-control"
                                   placeholder="<?= __t('form.fields.entity_id.placeholder','Optional') ?>"
                                   data-lookup="entity">
                            <span class="id-lookup-hint" data-for="fEntityId"></span>
                        </div>
                        <div class="form-group">
                            <label for="fExpiresAt" data-i18n="form.fields.expires_at.label"><?= __t('form.fields.expires_at.label','Expires At') ?></label>
                            <input type="datetime-local" id="fExpiresAt" name="expires_at" class="form-control">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="fTitle" class="required" data-i18n="form.fields.title.label"><?= __t('form.fields.title.label','Title') ?></label>
                        <input type="text" id="fTitle" name="title" class="form-control"
                               placeholder="<?= __t('form.fields.title.placeholder','Enter title') ?>">
                    </div>
                    <div class="form-group">
                        <label for="fMessage" class="required" data-i18n="form.fields.message.label"><?= __t('form.fields.message.label','Message') ?></label>
                        <textarea id="fMessage" name="message" class="form-control" rows="3"
                                  placeholder="<?= __t('form.fields.message.placeholder','Enter message') ?>"></textarea>
                    </div>
                    <div class="form-group">
                        <label for="fData" data-i18n="form.fields.data.label"><?= __t('form.fields.data.label','Data (JSON)') ?></label>
                        <textarea id="fData" name="data" class="form-control" rows="2"
                                  placeholder='{"key": "value"}'></textarea>
                    </div>
                </div>

                <!-- ── CHANNELS fields ── -->
                <div class="notif-form-section" data-form-tab="channels" style="display:none">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="fChCode" class="required" data-i18n="form.fields.code.label"><?= __t('form.fields.code.label','Code') ?></label>
                            <input type="text" id="fChCode" name="code" class="form-control"
                                   placeholder="<?= __t('form.fields.code.placeholder','Enter code') ?>">
                        </div>
                        <div class="form-group">
                            <label for="fChName" class="required" data-i18n="form.fields.name.label"><?= __t('form.fields.name.label','Name') ?></label>
                            <input type="text" id="fChName" name="name" class="form-control"
                                   placeholder="<?= __t('form.fields.name.placeholder','Enter name') ?>">
                        </div>
                        <div class="form-group">
                            <label for="fChIsActive" data-i18n="form.fields.is_active.label"><?= __t('form.fields.is_active.label','Status') ?></label>
                            <select id="fChIsActive" name="is_active" class="form-control">
                                <option value="1"><?= __t('form.fields.is_active.active','Active') ?></option>
                                <option value="0"><?= __t('form.fields.is_active.inactive','Inactive') ?></option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- ── COUNTERS fields ── -->
                <div class="notif-form-section" data-form-tab="counters" style="display:none">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="fCtrTenantId" class="required" data-i18n="form.fields.tenant_id.label"><?= __t('form.fields.tenant_id.label','Tenant ID') ?></label>
                            <input type="number" id="fCtrTenantId" name="tenant_id" class="form-control"
                                   value="<?= $tenantId ?>" <?= is_super_admin() ? '' : 'readonly' ?>
                                   data-lookup="tenant">
                            <span class="id-lookup-hint" data-for="fCtrTenantId"></span>
                        </div>
                        <div class="form-group">
                            <label for="fRecipientType" class="required" data-i18n="form.fields.recipient_type.label"><?= __t('form.fields.recipient_type.label','Recipient Type') ?></label>
                            <select id="fRecipientType" name="recipient_type" class="form-control">
                                <option value="user"   data-i18n="form.fields.recipient_type.user"><?= __t('form.fields.recipient_type.user','User') ?></option>
                                <option value="entity" data-i18n="form.fields.recipient_type.entity"><?= __t('form.fields.recipient_type.entity','Entity') ?></option>
                                <option value="tenant" data-i18n="form.fields.recipient_type.tenant"><?= __t('form.fields.recipient_type.tenant','Tenant') ?></option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="fRecipientId" class="required" data-i18n="form.fields.recipient_id.label"><?= __t('form.fields.recipient_id.label','Recipient ID') ?></label>
                            <input type="number" id="fRecipientId" name="recipient_id" class="form-control"
                                   placeholder="<?= __t('form.fields.recipient_id.placeholder','Enter ID') ?>"
                                   data-lookup="recipient">
                            <span class="id-lookup-hint" data-for="fRecipientId"></span>
                        </div>
                        <div class="form-group">
                            <label for="fUnreadCount" data-i18n="form.fields.unread_count.label"><?= __t('form.fields.unread_count.label','Unread Count') ?></label>
                            <input type="number" id="fUnreadCount" name="unread_count" class="form-control" value="0" min="0">
                        </div>
                    </div>
                </div>

                <!-- ── DELIVERIES fields ── -->
                <div class="notif-form-section" data-form-tab="deliveries" style="display:none">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="fNotifId" class="required" data-i18n="form.fields.notification_id.label"><?= __t('form.fields.notification_id.label','Notification ID') ?></label>
                            <input type="number" id="fNotifId" name="notification_id" class="form-control"
                                   data-lookup="notification">
                            <span class="id-lookup-hint" data-for="fNotifId"></span>
                        </div>
                        <div class="form-group">
                            <label for="fChannelId" class="required" data-i18n="form.fields.channel_id.label"><?= __t('form.fields.channel_id.label','Channel') ?></label>
                            <select id="fChannelId" name="channel_id" class="form-control">
                                <option value="">-- Select Channel --</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="fDeliveryStatus" data-i18n="form.fields.delivery_status.label"><?= __t('form.fields.delivery_status.label','Delivery Status') ?></label>
                            <select id="fDeliveryStatus" name="delivery_status" class="form-control">
                                <option value="pending" data-i18n="form.fields.delivery_status.pending"><?= __t('form.fields.delivery_status.pending','Pending') ?></option>
                                <option value="sent"    data-i18n="form.fields.delivery_status.sent"><?= __t('form.fields.delivery_status.sent','Sent') ?></option>
                                <option value="failed"  data-i18n="form.fields.delivery_status.failed"><?= __t('form.fields.delivery_status.failed','Failed') ?></option>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="fAttempts" data-i18n="form.fields.attempts.label"><?= __t('form.fields.attempts.label','Attempts') ?></label>
                            <input type="number" id="fAttempts" name="attempts" class="form-control" value="0" min="0">
                        </div>
                        <div class="form-group">
                            <label for="fSentAt" data-i18n="form.fields.sent_at.label"><?= __t('form.fields.sent_at.label','Sent At') ?></label>
                            <input type="datetime-local" id="fSentAt" name="sent_at" class="form-control">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="fErrorMessage" data-i18n="form.fields.error_message.label"><?= __t('form.fields.error_message.label','Error Message') ?></label>
                        <textarea id="fErrorMessage" name="error_message" class="form-control" rows="2"
                                  placeholder="<?= __t('form.fields.error_message.placeholder','Error message if any') ?>"></textarea>
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary" id="btnSubmitForm">
                        <i class="fas fa-save"></i>
                        <span data-i18n="form.buttons.save"><?= __t('form.buttons.save','Save') ?></span>
                    </button>
                    <button type="button" class="btn btn-outline" id="btnCancelForm" data-i18n="form.buttons.cancel">
                        <?= __t('form.buttons.cancel','Cancel') ?>
                    </button>
                    <?php if ($canDelete): ?>
                    <button type="button" id="btnDeleteRecord" class="btn btn-danger" style="display:none">
                        <i class="fas fa-trash"></i>
                        <span data-i18n="table.actions.delete"><?= __t('table.actions.delete','Delete') ?></span>
                    </button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- ── FILTER CARD ── -->
    <div class="card filter-card">
        <div class="card-body">
            <div class="filters-grid">
                <div class="filter-group">
                    <label for="searchInput" data-i18n="filters.search"><?= __t('filters.search','Search') ?></label>
                    <input type="text" id="searchInput" class="form-control"
                           placeholder="<?= __t('filters.search_placeholder','Search...') ?>"
                           data-i18n-placeholder="filters.search_placeholder">
                </div>
                <!-- Status filter (Types + Channels) -->
                <div class="filter-group" id="filterStatusGroup">
                    <label for="statusFilter" data-i18n="filters.status"><?= __t('filters.status','Status') ?></label>
                    <select id="statusFilter" class="form-control">
                        <option value="" data-i18n="filters.status_options.all"><?= __t('filters.status_options.all','All Status') ?></option>
                        <option value="1" data-i18n="filters.status_options.active"><?= __t('filters.status_options.active','Active') ?></option>
                        <option value="0" data-i18n="filters.status_options.inactive"><?= __t('filters.status_options.inactive','Inactive') ?></option>
                    </select>
                </div>
                <!-- Priority filter (Notifications) -->
                <div class="filter-group" id="filterPriorityGroup" style="display:none">
                    <label for="priorityFilter" data-i18n="filters.priority"><?= __t('filters.priority','Priority') ?></label>
                    <select id="priorityFilter" class="form-control">
                        <option value="" data-i18n="filters.priority_options.all"><?= __t('filters.priority_options.all','All Priorities') ?></option>
                        <option value="low"    data-i18n="filters.priority_options.low"><?= __t('filters.priority_options.low','Low') ?></option>
                        <option value="normal" data-i18n="filters.priority_options.normal"><?= __t('filters.priority_options.normal','Normal') ?></option>
                        <option value="high"   data-i18n="filters.priority_options.high"><?= __t('filters.priority_options.high','High') ?></option>
                        <option value="urgent" data-i18n="filters.priority_options.urgent"><?= __t('filters.priority_options.urgent','Urgent') ?></option>
                    </select>
                </div>
                <!-- Delivery status filter (Deliveries) -->
                <div class="filter-group" id="filterDeliveryStatusGroup" style="display:none">
                    <label for="deliveryStatusFilter" data-i18n="filters.delivery_status"><?= __t('filters.delivery_status','Delivery Status') ?></label>
                    <select id="deliveryStatusFilter" class="form-control">
                        <option value="" data-i18n="filters.delivery_status_options.all"><?= __t('filters.delivery_status_options.all','All') ?></option>
                        <option value="pending" data-i18n="filters.delivery_status_options.pending"><?= __t('filters.delivery_status_options.pending','Pending') ?></option>
                        <option value="sent"    data-i18n="filters.delivery_status_options.sent"><?= __t('filters.delivery_status_options.sent','Sent') ?></option>
                        <option value="failed"  data-i18n="filters.delivery_status_options.failed"><?= __t('filters.delivery_status_options.failed','Failed') ?></option>
                    </select>
                </div>
                <!-- Recipient type filter (Counters) -->
                <div class="filter-group" id="filterRecipientTypeGroup" style="display:none">
                    <label for="recipientTypeFilter" data-i18n="filters.recipient_type"><?= __t('filters.recipient_type','Recipient Type') ?></label>
                    <select id="recipientTypeFilter" class="form-control">
                        <option value="" data-i18n="filters.recipient_type_options.all"><?= __t('filters.recipient_type_options.all','All') ?></option>
                        <option value="user"   data-i18n="filters.recipient_type_options.user"><?= __t('filters.recipient_type_options.user','User') ?></option>
                        <option value="entity" data-i18n="filters.recipient_type_options.entity"><?= __t('filters.recipient_type_options.entity','Entity') ?></option>
                        <option value="tenant" data-i18n="filters.recipient_type_options.tenant"><?= __t('filters.recipient_type_options.tenant','Tenant') ?></option>
                    </select>
                </div>
                <?php if (is_super_admin()): ?>
                <div class="filter-group" id="filterTenantGroup">
                    <label for="tenantFilter" data-i18n="filters.tenant_id"><?= __t('filters.tenant_id','Tenant') ?></label>
                    <input type="number" id="tenantFilter" class="form-control"
                           value="<?= $tenantId ?>"
                           placeholder="<?= __t('filters.tenant_placeholder','Filter by tenant') ?>">
                </div>
                <?php endif; ?>
                <div class="filter-actions">
                    <button id="btnApplyFilters" class="btn btn-secondary" data-i18n="filters.apply"><?= __t('filters.apply','Apply') ?></button>
                    <button id="btnResetFilters" class="btn btn-outline"    data-i18n="filters.reset"><?= __t('filters.reset','Reset') ?></button>
                </div>
            </div>
        </div>
    </div>

    <!-- Results count -->
    <div id="resultsCount" class="results-count" style="display:none">
        <span><i class="fas fa-list"></i> <span id="resultsCountText"></span></span>
    </div>

    <!-- Table card -->
    <div class="card table-card">
        <div class="card-body">
            <div id="tableLoading" class="loading-state">
                <div class="spinner"></div>
                <p data-i18n="notifications.loading"><?= __t('notifications.loading','Loading...') ?></p>
            </div>
            <div id="tableContainer" style="display:none">
                <div class="table-responsive">
                    <table class="data-table" id="notifTable">
                        <thead><tr id="tableHead"></tr></thead>
                        <tbody id="tableBody"></tbody>
                    </table>
                </div>
                <div class="pagination-wrapper">
                    <div class="pagination-info">
                        <span data-i18n="pagination.showing"><?= __t('pagination.showing','Showing') ?></span>
                        <span id="paginationInfo">0-0 of 0</span>
                    </div>
                    <div class="pagination" id="pagination"></div>
                </div>
            </div>
            <div id="emptyState" class="empty-state" style="display:none">
                <div class="empty-icon">🔔</div>
                <h3 id="emptyTitle"></h3>
                <p id="emptyMessage"></p>
                <?php if ($canCreate): ?>
                <button class="btn btn-primary" onclick="window.Notifications && window.Notifications.add()">
                    <i class="fas fa-plus"></i> <span id="emptyAddLabel"></span>
                </button>
                <?php endif; ?>
            </div>
            <div id="errorState" class="error-state" style="display:none">
                <div class="error-icon">⚠️</div>
                <h3 data-i18n="messages.error.load_failed"><?= __t('messages.error.load_failed','Error Loading Data') ?></h3>
                <p id="errorMessage"></p>
                <button id="btnRetry" class="btn btn-secondary" data-i18n="notifications.retry"><?= __t('notifications.retry','Retry') ?></button>
            </div>
        </div>
    </div>

</div>

<!-- Global config -->
<script type="text/javascript">
window.APP_CONFIG = window.APP_CONFIG || {};
window.APP_CONFIG.API_BASE   = window.APP_CONFIG.API_BASE   || '<?= $apiBase ?>';
window.APP_CONFIG.TENANT_ID  = window.APP_CONFIG.TENANT_ID  || <?= $tenantId ?>;
window.APP_CONFIG.CSRF_TOKEN = window.APP_CONFIG.CSRF_TOKEN || '<?= addslashes($csrf) ?>';
window.APP_CONFIG.USER_ID    = window.APP_CONFIG.USER_ID    || <?= admin_user_id() ?>;
window.USER_LANGUAGE   = window.USER_LANGUAGE   || '<?= addslashes($lang) ?>';
window.USER_DIRECTION  = window.USER_DIRECTION  || '<?= addslashes($dir) ?>';
window.CSRF_TOKEN      = window.CSRF_TOKEN      || '<?= addslashes($csrf) ?>';
window.PAGE_PERMISSIONS = <?= json_encode([
    'canCreate'  => $canCreate,
    'canEdit'    => $canEdit,
    'canDelete'  => $canDelete,
    'canViewAll' => $canViewAll,
    'canEditAll' => $canEditAll,
    'isSuperAdmin' => is_super_admin()
], JSON_UNESCAPED_UNICODE) ?>;
window.NOTIFICATIONS_CONFIG = {
    api: {
        types:     '<?= $apiBase ?>/notification_types',
        list:      '<?= $apiBase ?>/notifications',
        channels:  '<?= $apiBase ?>/notification_channels',
        counters:  '<?= $apiBase ?>/notification_counters',
        deliveries:'<?= $apiBase ?>/notification_deliveries'
    },
    tenantId:  <?= $tenantId ?>,
    csrfToken: '<?= addslashes($csrf) ?>',
    lang:      '<?= addslashes($lang) ?>',
    itemsPerPage: 25
};
</script>

<!-- Translation loader -->
<script type="text/javascript">
(function(){
    async function applyTranslations() {
        try {
            const lang = window.USER_LANGUAGE || 'en';
            const url  = `/languages/Notifications/${encodeURIComponent(lang)}.json`;
            const res  = await fetch(url, { credentials: 'same-origin' });
            if (!res.ok) throw new Error('Translation fetch failed: ' + res.status);
            const translations = await res.json();
            window.NOTIF_TRANSLATIONS = translations;
            const container = document.getElementById('notificationPageContainer');
            if (!container) return;
            container.querySelectorAll('[data-i18n]').forEach(el => {
                const key = el.getAttribute('data-i18n');
                const txt = key.split('.').reduce((o,k) => (o && o[k] !== undefined) ? o[k] : null, translations);
                if (txt !== null && txt !== undefined) el.textContent = txt;
            });
            container.querySelectorAll('[data-i18n-placeholder]').forEach(el => {
                const key = el.getAttribute('data-i18n-placeholder');
                const txt = key.split('.').reduce((o,k) => (o && o[k] !== undefined) ? o[k] : null, translations);
                if (txt !== null && txt !== undefined) el.placeholder = txt;
            });
        } catch(err) {
            console.warn('[Notifications] Translation load failed:', err);
        }
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', applyTranslations);
    } else {
        setTimeout(applyTranslations, 50);
    }
})();
</script>

<!-- Load scripts -->
<?php if ($isFragment): ?>
<script src="/admin/assets/js/admin_framework.js?v=<?= time() ?>"></script>
<script src="/admin/assets/js/pages/notification.js?v=<?= time() ?>"></script>
<script>
(function(){
    let attempts = 0, maxAttempts = 50;
    const interval = setInterval(function(){
        attempts++;
        if (window.AdminFramework && window.Notifications && typeof window.Notifications.init === 'function') {
            clearInterval(interval);
            try {
                const p = window.Notifications.init();
                if (p && typeof p.then === 'function') {
                    p.then(()=>console.log('[Notifications] init done')).catch(e=>console.error('[Notifications] init error',e));
                }
            } catch(e) {
                console.error('[Notifications] init threw', e);
            }
        } else if (attempts > maxAttempts) {
            clearInterval(interval);
            console.error('[Notifications] Timeout: Framework:', !!window.AdminFramework, 'Module:', !!window.Notifications);
        }
    }, 100);
})();
</script>
<?php else: ?>
<script src="/admin/assets/js/pages/notification.js?v=<?= time() ?>"></script>
<?php endif; ?>

<?php
if (!$isFragment) {
    require_once __DIR__ . '/../includes/footer.php';
}
?>
