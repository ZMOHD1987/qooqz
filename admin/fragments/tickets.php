<?php
declare(strict_types=1);

/**
 * /admin/fragments/tickets.php
 * Production Version — Support Tickets Management
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
$canManageTickets = can('tickets.manage') || can('tickets.create');
$canViewAll       = can_view_all('tickets');
$canViewOwn       = can_view_own('tickets');
$canCreate        = can_create('tickets');
$canEdit          = can_edit_all('tickets') || $canManageTickets;
$canDelete        = can_delete_all('tickets') || $canManageTickets;
$canView          = $canViewAll || $canViewOwn;

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

// ════════════════════════════════════════════════════════════
// TRANSLATION HELPER (server-side fallback)
// Reads from flat JSON structure — no "strings" wrapper
// ════════════════════════════════════════════════════════════
function __t(string $key, string $fallback = ''): string
{
    if (function_exists('i18n_get')) {
        $v = i18n_get($key);
        return $v ?? ($fallback !== '' ? $fallback : $key);
    }
    return $fallback !== '' ? $fallback : $key;
}

// Theme CSS vars and generated_css are provided by header.php
// via <style id="dynamic-theme-vars"> and <style id="dynamic-theme-db">.
// No per-fragment duplication needed.

$apiBase  = '/api';
$langSafe = rawurlencode($lang);
$v        = time(); // cache-bust
?>
<link rel="stylesheet" href="/admin/assets/css/pages/tickets.css?v=<?= $v ?>">

<meta data-page="tickets"
      data-assets-css="/admin/assets/css/pages/tickets.css"
      data-i18n-files="/languages/tickets/<?= $langSafe ?>.json">

<div class="page-container" id="ticketsPageContainer" dir="<?= htmlspecialchars($dir) ?>">

    <!-- ── Page Header ─────────────────────────────────── -->
    <div class="page-header">
        <div class="page-header-content">
            <h1 class="page-title" data-i18n="tickets.title">
                <?= __t('tickets.title', 'Support Tickets') ?>
            </h1>
            <p class="page-subtitle" data-i18n="tickets.subtitle">
                <?= __t('tickets.subtitle', 'Manage customer support requests') ?>
            </p>
        </div>
        <div class="page-header-actions">
            <?php if ($canCreate): ?>
            <button id="btnAddTicket" class="btn btn-primary">
                <i class="fas fa-plus"></i>
                <span data-i18n="tickets.add_new"><?= __t('tickets.add_new', 'New Ticket') ?></span>
            </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- ── Form Container ──────────────────────────────── -->
    <div id="ticketFormContainer" class="card form-card" style="display:none">
        <div class="card-header">
            <h3 class="card-title" id="formTitle" data-i18n="form.add_title">
                <?= __t('form.add_title', 'New Support Ticket') ?>
            </h3>
            <button type="button" class="btn btn-sm btn-outline" id="btnCloseForm">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="card-body">
            <form id="ticketForm" novalidate>
                <input type="hidden" id="formId"         name="id">
                <input type="hidden"                      name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                <input type="hidden" id="ticketTenantId" name="tenant_id"  value="<?= $tenantId ?>">

                <!-- Tabs -->
                <div class="form-tabs">
                    <button type="button" class="tab-btn active" data-tab="details">
                        <i class="fas fa-info-circle"></i>
                        <span data-i18n="tabs.details"><?= __t('tabs.details', 'Details') ?></span>
                    </button>
                    <button type="button" class="tab-btn" data-tab="messages">
                        <i class="fas fa-comments"></i>
                        <span data-i18n="tabs.messages"><?= __t('tabs.messages', 'Messages') ?></span>
                    </button>
                    <button type="button" class="tab-btn" data-tab="history">
                        <i class="fas fa-history"></i>
                        <span data-i18n="tabs.history"><?= __t('tabs.history', 'Status History') ?></span>
                    </button>
                </div>

                <!-- Tab: Details -->
                <div class="tab-content active" id="tab-details">
                    <div class="form-row">
                        <div class="form-group" style="flex:2">
                            <label for="ticketSubject" data-i18n="form.fields.subject.label">
                                <?= __t('form.fields.subject.label', 'Subject') ?>
                            </label>
                            <input type="text" id="ticketSubject" name="subject"
                                   class="form-control" required
                                   data-i18n-placeholder="form.fields.subject.placeholder"
                                   placeholder="<?= __t('form.fields.subject.placeholder', 'Enter ticket subject') ?>">
                        </div>
                        <div class="form-group">
                            <label for="ticketCategory" data-i18n="form.fields.category.label">
                                <?= __t('form.fields.category.label', 'Category') ?>
                            </label>
                            <select id="ticketCategory" name="category_id" class="form-control"></select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="ticketUser" data-i18n="form.fields.user.label">
                                <?= __t('form.fields.user.label', 'Customer') ?>
                            </label>
                            <select id="ticketUser" name="user_id" class="form-control"></select>
                        </div>
                        <div class="form-group">
                            <label for="ticketOrder" data-i18n="form.fields.order.label">
                                <?= __t('form.fields.order.label', 'Related Order') ?>
                            </label>
                            <select id="ticketOrder" name="order_id" class="form-control"></select>
                        </div>
                        <div class="form-group">
                            <label for="ticketEntity" data-i18n="form.fields.entity.label">
                                <?= __t('form.fields.entity.label', 'Entity') ?>
                            </label>
                            <select id="ticketEntity" name="entity_id" class="form-control"></select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="ticketDescription" data-i18n="form.fields.description.label">
                            <?= __t('form.fields.description.label', 'Description') ?>
                        </label>
                        <textarea id="ticketDescription" name="description"
                                  class="form-control" rows="5"
                                  data-i18n-placeholder="form.fields.description.placeholder"
                                  placeholder="<?= __t('form.fields.description.placeholder', 'Describe the issue in detail') ?>"></textarea>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="ticketStatus" data-i18n="form.fields.status.label">
                                <?= __t('form.fields.status.label', 'Status') ?>
                            </label>
                            <select id="ticketStatus" name="status" class="form-control">
                                <option value="open"              data-i18n="status.open">
                                    <?= __t('status.open', 'Open') ?>
                                </option>
                                <option value="pending"           data-i18n="status.pending">
                                    <?= __t('status.pending', 'Pending') ?>
                                </option>
                                <option value="awaiting_customer" data-i18n="status.awaiting_customer">
                                    <?= __t('status.awaiting_customer', 'Awaiting Customer') ?>
                                </option>
                                <option value="awaiting_vendor"   data-i18n="status.awaiting_vendor">
                                    <?= __t('status.awaiting_vendor', 'Awaiting Vendor') ?>
                                </option>
                                <option value="in_progress"       data-i18n="status.in_progress">
                                    <?= __t('status.in_progress', 'In Progress') ?>
                                </option>
                                <option value="resolved"          data-i18n="status.resolved">
                                    <?= __t('status.resolved', 'Resolved') ?>
                                </option>
                                <option value="closed"            data-i18n="status.closed">
                                    <?= __t('status.closed', 'Closed') ?>
                                </option>
                                <option value="cancelled"         data-i18n="status.cancelled">
                                    <?= __t('status.cancelled', 'Cancelled') ?>
                                </option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="ticketPriority" data-i18n="form.fields.priority.label">
                                <?= __t('form.fields.priority.label', 'Priority') ?>
                            </label>
                            <select id="ticketPriority" name="priority" class="form-control">
                                <option value="low"    data-i18n="priority.low">
                                    <?= __t('priority.low', 'Low') ?>
                                </option>
                                <option value="normal" data-i18n="priority.normal">
                                    <?= __t('priority.normal', 'Normal') ?>
                                </option>
                                <option value="high"   data-i18n="priority.high">
                                    <?= __t('priority.high', 'High') ?>
                                </option>
                                <option value="urgent" data-i18n="priority.urgent">
                                    <?= __t('priority.urgent', 'Urgent') ?>
                                </option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="ticketAssigned" data-i18n="form.fields.assigned_to.label">
                                <?= __t('form.fields.assigned_to.label', 'Assigned To') ?>
                            </label>
                            <select id="ticketAssigned" name="assigned_to" class="form-control"></select>
                        </div>
                    </div>
                </div><!-- /tab-details -->

                <!-- Tab: Messages -->
                <div class="tab-content" id="tab-messages" style="display:none">
                    <div id="ticketMessagesList" class="messages-list"></div>
                    <div class="reply-section"
                         style="margin-top:20px;padding-top:15px;border-top:1px solid var(--border-color)">
                        <div class="form-group">
                            <label for="ticketReply" data-i18n="form.fields.reply.label">
                                <?= __t('form.fields.reply.label', 'Add Reply') ?>
                            </label>
                            <textarea id="ticketReply" class="form-control" rows="3"
                                      data-i18n-placeholder="form.fields.reply.placeholder"
                                      placeholder="<?= __t('form.fields.reply.placeholder', 'Type your response here...') ?>"></textarea>
                        </div>
                        <div style="display:flex;align-items:center;gap:12px">
                            <button type="button" id="btnSendReply" class="btn btn-primary">
                                <i class="fas fa-paper-plane"></i>
                                <span data-i18n="form.buttons.send_reply">
                                    <?= __t('form.buttons.send_reply', 'Send Reply') ?>
                                </span>
                            </button>
                            <label style="display:flex;align-items:center;gap:6px;
                                          color:var(--text-secondary);font-size:0.9rem;cursor:pointer">
                                <input type="checkbox" id="replyInternal">
                                <span data-i18n="form.fields.internal_note">
                                    <?= __t('form.fields.internal_note', 'Internal Note?') ?>
                                </span>
                            </label>
                        </div>
                    </div>
                </div><!-- /tab-messages -->

                <!-- Tab: History -->
                <div class="tab-content" id="tab-history" style="display:none">
                    <div id="ticketHistoryList"></div>
                </div><!-- /tab-history -->

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary" id="btnSubmitForm">
                        <i class="fas fa-save"></i>
                        <span data-i18n="form.buttons.save"><?= __t('form.buttons.save', 'Save Ticket') ?></span>
                    </button>
                    <button type="button" class="btn btn-outline" id="btnCancelForm"
                            data-i18n="form.buttons.cancel">
                        <?= __t('form.buttons.cancel', 'Cancel') ?>
                    </button>
                    <?php if ($canDelete): ?>
                    <button type="button" id="btnDeleteTicket" class="btn btn-danger" style="display:none">
                        <i class="fas fa-trash"></i>
                        <span data-i18n="form.buttons.delete">
                            <?= __t('form.buttons.delete', 'Delete Ticket') ?>
                        </span>
                    </button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- ── Filters ─────────────────────────────────────── -->
    <div class="card filter-card">
        <div class="card-body">
            <div class="filters-grid">
                <div class="filter-group">
                    <label for="searchInput" data-i18n="filters.search">
                        <?= __t('filters.search', 'Search') ?>
                    </label>
                    <input type="text" id="searchInput" class="form-control"
                           data-i18n-placeholder="filters.search_placeholder"
                           placeholder="<?= __t('filters.search_placeholder', 'Search by subject or #ID...') ?>">
                </div>
                <div class="filter-group">
                    <label for="statusFilter" data-i18n="filters.status">
                        <?= __t('filters.status', 'Status') ?>
                    </label>
                    <select id="statusFilter" class="form-control">
                        <option value=""                  data-i18n="filters.all_statuses">
                            <?= __t('filters.all_statuses', 'All Statuses') ?>
                        </option>
                        <option value="open"              data-i18n="status.open">
                            <?= __t('status.open', 'Open') ?>
                        </option>
                        <option value="pending"           data-i18n="status.pending">
                            <?= __t('status.pending', 'Pending') ?>
                        </option>
                        <option value="awaiting_customer" data-i18n="status.awaiting_customer">
                            <?= __t('status.awaiting_customer', 'Awaiting Customer') ?>
                        </option>
                        <option value="awaiting_vendor"   data-i18n="status.awaiting_vendor">
                            <?= __t('status.awaiting_vendor', 'Awaiting Vendor') ?>
                        </option>
                        <option value="in_progress"       data-i18n="status.in_progress">
                            <?= __t('status.in_progress', 'In Progress') ?>
                        </option>
                        <option value="resolved"          data-i18n="status.resolved">
                            <?= __t('status.resolved', 'Resolved') ?>
                        </option>
                        <option value="closed"            data-i18n="status.closed">
                            <?= __t('status.closed', 'Closed') ?>
                        </option>
                        <option value="cancelled"         data-i18n="status.cancelled">
                            <?= __t('status.cancelled', 'Cancelled') ?>
                        </option>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="priorityFilter" data-i18n="filters.priority">
                        <?= __t('filters.priority', 'Priority') ?>
                    </label>
                    <select id="priorityFilter" class="form-control">
                        <option value=""       data-i18n="filters.all_priorities">
                            <?= __t('filters.all_priorities', 'All Priorities') ?>
                        </option>
                        <option value="low"    data-i18n="priority.low">
                            <?= __t('priority.low', 'Low') ?>
                        </option>
                        <option value="normal" data-i18n="priority.normal">
                            <?= __t('priority.normal', 'Normal') ?>
                        </option>
                        <option value="high"   data-i18n="priority.high">
                            <?= __t('priority.high', 'High') ?>
                        </option>
                        <option value="urgent" data-i18n="priority.urgent">
                            <?= __t('priority.urgent', 'Urgent') ?>
                        </option>
                    </select>
                </div>
                <div class="filter-actions">
                    <button id="btnApplyFilters" class="btn btn-secondary" data-i18n="filters.apply">
                        <?= __t('filters.apply', 'Apply Filters') ?>
                    </button>
                    <button id="btnResetFilters" class="btn btn-outline" data-i18n="filters.reset">
                        <?= __t('filters.reset', 'Reset') ?>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Table ───────────────────────────────────────── -->
    <div class="card table-card">
        <div class="card-body">
            <div id="tableLoading" class="loading-state">
                <div class="spinner"></div>
                <p data-i18n="tickets.loading"><?= __t('tickets.loading', 'Loading tickets...') ?></p>
            </div>
            <div id="tableContainer" style="display:none">
                <div class="table-responsive">
                    <table class="data-table" id="ticketsTable">
                        <thead>
                            <tr>
                                <th data-i18n="table.headers.id">
                                    <?= __t('table.headers.id', 'ID') ?>
                                </th>
                                <th data-i18n="table.headers.subject">
                                    <?= __t('table.headers.subject', 'Subject') ?>
                                </th>
                                <th data-i18n="table.headers.customer">
                                    <?= __t('table.headers.customer', 'Customer') ?>
                                </th>
                                <th data-i18n="table.headers.category">
                                    <?= __t('table.headers.category', 'Category') ?>
                                </th>
                                <th data-i18n="table.headers.priority">
                                    <?= __t('table.headers.priority', 'Priority') ?>
                                </th>
                                <th data-i18n="table.headers.status">
                                    <?= __t('table.headers.status', 'Status') ?>
                                </th>
                                <th data-i18n="table.headers.updated">
                                    <?= __t('table.headers.updated', 'Last Updated') ?>
                                </th>
                                <th data-i18n="table.headers.actions">
                                    <?= __t('table.headers.actions', 'Actions') ?>
                                </th>
                            </tr>
                        </thead>
                        <tbody id="tableBody"></tbody>
                    </table>
                </div>
                <div class="pagination-wrapper">
                    <div class="pagination-info">
                        <span id="paginationInfo">
                            0-0 <?= __t('pagination.of', 'of') ?> 0
                        </span>
                    </div>
                    <div class="pagination" id="pagination"></div>
                </div>
            </div>
            <div id="emptyState" class="empty-state" style="display:none">
                <div class="empty-icon">🎟️</div>
                <h3 data-i18n="table.empty.title">
                    <?= __t('table.empty.title', 'No Tickets Found') ?>
                </h3>
                <p data-i18n="table.empty.message">
                    <?= __t('table.empty.message', 'There are no support tickets matching your criteria.') ?>
                </p>
                <?php if ($canCreate): ?>
                <button class="btn btn-primary" onclick="document.getElementById('btnAddTicket').click()"
                        data-i18n="table.empty.add_first">
                    <?= __t('table.empty.add_first', 'Create First Ticket') ?>
                </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div><!-- /page-container -->

<!-- ════════════════════════════════════════════════════════
     GLOBAL CONFIG
     ════════════════════════════════════════════════════════ -->
<script>
window.APP_CONFIG = {
    API_BASE  : '<?= $apiBase ?>',
    TENANT_ID : <?= $tenantId ?>,
    CSRF_TOKEN: '<?= addslashes($csrf) ?>'
};
window.USER_LANGUAGE    = '<?= addslashes($lang) ?>';
window.ADMIN_LANG       = window.ADMIN_LANG || window.USER_LANGUAGE;
window.PAGE_PERMISSIONS = <?= json_encode([
    'canCreate' => $canCreate,
    'canEdit'   => $canEdit,
    'canDelete' => $canDelete,
]) ?>;
window.TICKETS_CONFIG = {
    apiUrl       : '<?= $apiBase ?>/support_tickets',
    categoriesApi: '<?= $apiBase ?>/ticket_categories',
    messagesApi  : '<?= $apiBase ?>/ticket_messages',
    historyApi   : '<?= $apiBase ?>/ticket_status_history',
    usersApi     : '<?= $apiBase ?>/users',
    ordersApi    : '<?= $apiBase ?>/orders',
    entitiesApi  : '<?= $apiBase ?>/entities',
    lang         : '<?= addslashes($lang) ?>',
    itemsPerPage : 20,
    tenantId     : <?= $tenantId ?>
};
</script>

<!-- ════════════════════════════════════════════════════════
     EAGER i18n LOADER
     Fetches /languages/tickets/{lang}.json and merges the
     flat keys directly into window.TRANSLATIONS so that
     t('tickets.title') resolves without any "strings" wrapper.
     ════════════════════════════════════════════════════════ -->
<script>
(function () {
    var lang = '<?= addslashes($lang) ?>';
    var url  = '/languages/tickets/' + encodeURIComponent(lang) + '.json';

    // Already cached by a previous fragment load — merge immediately.
    if (window._i18nCache && window._i18nCache[url]) {
        window.TRANSLATIONS = Object.assign({}, window.TRANSLATIONS || {}, window._i18nCache[url]);
        return;
    }

    // Fetch async and expose as a Promise for tryInit() to await.
    window._ticketsI18nReady = fetch(url, { credentials: 'same-origin' })
        .then(function (r) { return r.ok ? r.json() : {}; })
        .then(function (data) {
            //
            // KEY FIX: The JSON has a flat structure (no "strings" wrapper).
            // We strip the meta key "direction" and merge everything else
            // into window.TRANSLATIONS so dot-notation lookups work:
            //   t('tickets.title')  →  TRANSLATIONS.tickets.title  ✓
            //   t('status.open')    →  TRANSLATIONS.status.open    ✓
            //
            var clean = {};
            Object.keys(data).forEach(function (k) {
                if (k !== 'direction') clean[k] = data[k];
            });

            window.TRANSLATIONS = Object.assign({}, window.TRANSLATIONS || {}, clean);

            // Store direction for RTL layouts
            if (data.direction) window.PAGE_DIRECTION = data.direction;

            // Cache for subsequent fragment visits
            window._i18nCache       = window._i18nCache || {};
            window._i18nCache[url]  = clean;

            // Re-apply [data-i18n] translations if the framework is ready
            if (window._admin && typeof window._admin.applyTranslations === 'function') {
                window._admin.applyTranslations();
            }
        })
        .catch(function (err) {
            console.warn('[Tickets] i18n load failed for:', url, err);
        });
}());
</script>

<script src="/admin/assets/js/admin_framework.js?v=<?= $v ?>"></script>
<script src="/admin/assets/js/pages/tickets.js?v=<?= $v ?>"></script>

<!-- ════════════════════════════════════════════════════════
     INIT ORCHESTRATOR
     Calls Tickets.init() only after BOTH conditions are met:
       1. window.Tickets is defined (tickets.js loaded)
       2. window.TRANSLATIONS has ticket keys (i18n loaded)
     ════════════════════════════════════════════════════════ -->
<script>
(function () {
    var initialized = false;
    var pollTimer;

    function cleanup() {
        clearInterval(pollTimer);
        window.removeEventListener('admin:i18n:applied', onI18nApplied);
    }

    function tryInit() {
        if (initialized) return;
        if (!window.Tickets || typeof window.Tickets.init !== 'function') return;

        // Await the eager fetch Promise (resolves immediately if already done)
        var i18nReady = window._ticketsI18nReady || Promise.resolve();
        i18nReady.then(function () {
            if (initialized) return;

            // Guard: TRANSLATIONS must contain at least the "tickets" key
            if (!window.TRANSLATIONS || !window.TRANSLATIONS.tickets) return;

            initialized = true;
            cleanup();
            window.Tickets.init();
        });
    }

    // Primary trigger: fired by admin framework after applyTranslations()
    function onI18nApplied() { tryInit(); }
    window.addEventListener('admin:i18n:applied', onI18nApplied);

    // Immediate attempt — covers re-visits where everything is already ready
    tryInit();

    // Fallback poll — max 6 s (60 × 100 ms)
    var pollCount = 0;
    pollTimer = setInterval(function () {
        pollCount++;
        tryInit();
        if (initialized || pollCount >= 60) {
            cleanup();
            if (!initialized) {
                console.warn('[Tickets] init timed out — forcing start');
                initialized = true;
                if (window.Tickets && typeof window.Tickets.init === 'function') {
                    window.Tickets.init();
                }
            }
        }
    }, 100);
}());
</script>

<?php if (!$isFragment) require_once __DIR__ . '/../includes/footer.php'; ?>