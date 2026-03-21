<?php
declare(strict_types=1);

/**
 * /admin/fragments/queues.php
 * Queue Management - Production Ready
 */

$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
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
$dir      = in_array($lang, ['ar', 'he', 'fa', 'ur']) ? 'rtl' : 'ltr';
$csrf     = admin_csrf();
$tenantId = admin_tenant_id();
$userId   = admin_user_id();

$canManageQueues = can('manage_settings') || is_super_admin();
$canDelete       = $canManageQueues;

if (!$canManageQueues) {
    http_response_code(403);
    die('Access denied');
}

$apiBase = '/api';

// Translation helper
$_qStrings = [];
$_qAllowedLangs = ['ar','en','fr','tr','ur','de','es','fa','he','hi','zh','ja','ko','pt','ru','it','nl','sv','pl','th','vi','id','ms','bn','sw','tl'];
$_qSafeLang = in_array($lang, $_qAllowedLangs, true) ? $lang : 'en';
$_qLangFile = __DIR__ . '/../../languages/Queues/' . $_qSafeLang . '.json';
if (file_exists($_qLangFile)) {
    $_qJson = json_decode(file_get_contents($_qLangFile), true);
    if (isset($_qJson['strings'])) {
        $_qStrings = $_qJson['strings'];
    }
}

if (!function_exists('_qt')) {
    function _qt(string $key, string $fallback = ''): string {
        global $_qStrings;
        $keys = explode('.', $key);
        $val  = $_qStrings;
        foreach ($keys as $k) {
            if (is_array($val) && isset($val[$k])) {
                $val = $val[$k];
            } else {
                return $fallback ?: $key;
            }
        }
        return is_string($val) ? $val : ($fallback ?: $key);
    }
}
?>

<link rel="stylesheet" href="/admin/assets/css/pages/queues.css?v=<?= time() ?>">

<div class="page-container" id="queuesPageContainer" dir="<?= htmlspecialchars($dir) ?>">

    <!-- Page Header -->
    <div class="page-header">
        <h1><?= htmlspecialchars(_qt('title', 'Queue Management'), ENT_QUOTES, 'UTF-8') ?></h1>
        <p><?= htmlspecialchars(_qt('subtitle', 'Monitor and manage background job queues'), ENT_QUOTES, 'UTF-8') ?></p>
        <div class="page-header-actions">
            <button id="btnRefresh" class="btn btn-secondary"><?= htmlspecialchars(_qt('actions.refresh', 'Refresh'), ENT_QUOTES, 'UTF-8') ?></button>
            <button id="btnArchiveDone" class="btn btn-info"><?= htmlspecialchars(_qt('actions.archive', 'Archive Done'), ENT_QUOTES, 'UTF-8') ?></button>
            <button id="btnOpenPurge" class="btn btn-danger"><?= htmlspecialchars(_qt('actions.purge', 'Purge Old'), ENT_QUOTES, 'UTF-8') ?></button>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="stats-row" id="statsRow">
        <div class="stat-card stat-total">
            <div class="stat-label"><?= htmlspecialchars(_qt('stats.total', 'Total Jobs'), ENT_QUOTES, 'UTF-8') ?></div>
            <div class="stat-value" id="statTotal">0</div>
        </div>
        <div class="stat-card stat-pending">
            <div class="stat-label"><?= htmlspecialchars(_qt('stats.pending', 'Pending'), ENT_QUOTES, 'UTF-8') ?></div>
            <div class="stat-value" id="statPending">0</div>
        </div>
        <div class="stat-card stat-working">
            <div class="stat-label"><?= htmlspecialchars(_qt('stats.working', 'Working'), ENT_QUOTES, 'UTF-8') ?></div>
            <div class="stat-value" id="statWorking">0</div>
        </div>
        <div class="stat-card stat-done">
            <div class="stat-label"><?= htmlspecialchars(_qt('stats.done', 'Done'), ENT_QUOTES, 'UTF-8') ?></div>
            <div class="stat-value" id="statDone">0</div>
        </div>
        <div class="stat-card stat-failed">
            <div class="stat-label"><?= htmlspecialchars(_qt('stats.failed', 'Failed'), ENT_QUOTES, 'UTF-8') ?></div>
            <div class="stat-value" id="statFailed">0</div>
        </div>
    </div>

    <!-- Filter Bar -->
    <div class="card">
        <div class="card-body filter-bar">
            <input type="text" id="filterSearch" class="form-control" placeholder="<?= htmlspecialchars(_qt('filter.search_placeholder', 'Search...'), ENT_QUOTES, 'UTF-8') ?>">
            <select id="filterQueue" class="form-control">
                <option value=""><?= htmlspecialchars(_qt('filter.all_queues', 'All Queues'), ENT_QUOTES, 'UTF-8') ?></option>
            </select>
            <select id="filterPriority" class="form-control">
                <option value=""><?= htmlspecialchars(_qt('filter.all_priorities', 'All Priorities'), ENT_QUOTES, 'UTF-8') ?></option>
                <option value="low"><?= htmlspecialchars(_qt('status.priority.low', 'Low'), ENT_QUOTES, 'UTF-8') ?></option>
                <option value="normal"><?= htmlspecialchars(_qt('status.priority.normal', 'Normal'), ENT_QUOTES, 'UTF-8') ?></option>
                <option value="high"><?= htmlspecialchars(_qt('status.priority.high', 'High'), ENT_QUOTES, 'UTF-8') ?></option>
                <option value="urgent"><?= htmlspecialchars(_qt('status.priority.urgent', 'Urgent'), ENT_QUOTES, 'UTF-8') ?></option>
            </select>
            <select id="filterStatus" class="form-control">
                <option value=""><?= htmlspecialchars(_qt('filter.all_statuses', 'All Statuses'), ENT_QUOTES, 'UTF-8') ?></option>
                <option value="0"><?= htmlspecialchars(_qt('status.pending', 'Pending'), ENT_QUOTES, 'UTF-8') ?></option>
                <option value="1"><?= htmlspecialchars(_qt('status.working', 'Working'), ENT_QUOTES, 'UTF-8') ?></option>
                <option value="2"><?= htmlspecialchars(_qt('status.done', 'Done'), ENT_QUOTES, 'UTF-8') ?></option>
                <option value="3"><?= htmlspecialchars(_qt('status.failed', 'Failed'), ENT_QUOTES, 'UTF-8') ?></option>
            </select>
            <button id="btnFilter" class="btn btn-primary"><?= htmlspecialchars(_qt('filter.apply', 'Filter'), ENT_QUOTES, 'UTF-8') ?></button>
            <button id="btnClearFilters" class="btn btn-secondary"><?= htmlspecialchars(_qt('filter.clear', 'Clear Filters'), ENT_QUOTES, 'UTF-8') ?></button>
        </div>
    </div>

    <!-- Data Table -->
    <div class="card">
        <div class="card-body" style="overflow-x:auto;">
            <table class="data-table" id="queuesTable">
                <thead>
                    <tr>
                        <th><?= htmlspecialchars(_qt('table.id', 'ID'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th><?= htmlspecialchars(_qt('table.queue', 'Queue'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th><?= htmlspecialchars(_qt('table.job_type', 'Job Type'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th><?= htmlspecialchars(_qt('table.entity', 'Entity'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th><?= htmlspecialchars(_qt('table.priority', 'Priority'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th><?= htmlspecialchars(_qt('table.status', 'Status'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th><?= htmlspecialchars(_qt('table.attempts', 'Attempts'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th><?= htmlspecialchars(_qt('table.error', 'Error'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th><?= htmlspecialchars(_qt('table.created_at', 'Created'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th><?= htmlspecialchars(_qt('table.actions', 'Actions'), ENT_QUOTES, 'UTF-8') ?></th>
                    </tr>
                </thead>
                <tbody id="queuesBody">
                    <tr id="emptyStateRow">
                        <td colspan="10" class="text-center"><?= htmlspecialchars(_qt('table.no_records', 'No jobs found'), ENT_QUOTES, 'UTF-8') ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="pagination-wrapper">
            <div class="pagination-info">
                <span><?= htmlspecialchars(_qt('pagination.showing', 'Showing'), ENT_QUOTES, 'UTF-8') ?></span>
                <span id="paginationInfo">0-0 <?= htmlspecialchars(_qt('pagination.of', 'of'), ENT_QUOTES, 'UTF-8') ?> 0</span>
            </div>
            <div class="pagination" id="pagination"></div>
        </div>
    </div>

    <!-- View Job Modal -->
    <div id="viewJobModal" class="modal" style="display:none;">
        <div class="modal-content">
            <h3><?= htmlspecialchars(_qt('modal.view_title', 'Job Details'), ENT_QUOTES, 'UTF-8') ?></h3>
            <div id="viewJobContent" class="job-details"></div>
            <div class="form-actions">
                <button type="button" class="btn btn-secondary btn-close-modal" data-modal="viewJobModal"><?= htmlspecialchars(_qt('form.close', 'Close'), ENT_QUOTES, 'UTF-8') ?></button>
            </div>
        </div>
    </div>

    <!-- Purge Modal -->
    <div id="purgeModal" class="modal" style="display:none;">
        <div class="modal-content">
            <h3><?= htmlspecialchars(_qt('modal.purge_title', 'Purge Old Jobs'), ENT_QUOTES, 'UTF-8') ?></h3>
            <div class="form-group">
                <label><?= htmlspecialchars(_qt('modal.purge_status', 'Status to purge'), ENT_QUOTES, 'UTF-8') ?></label>
                <select id="purgeStatus" class="form-control">
                    <option value="done"><?= htmlspecialchars(_qt('status.done', 'Done'), ENT_QUOTES, 'UTF-8') ?></option>
                    <option value="failed"><?= htmlspecialchars(_qt('status.failed', 'Failed'), ENT_QUOTES, 'UTF-8') ?></option>
                </select>
            </div>
            <div class="form-group">
                <label><?= htmlspecialchars(_qt('modal.purge_days', 'Older than (days)'), ENT_QUOTES, 'UTF-8') ?></label>
                <input type="number" id="purgeDays" class="form-control" value="30" min="1">
            </div>
            <p class="text-danger"><?= htmlspecialchars(_qt('modal.purge_confirm', 'Are you sure? This cannot be undone.'), ENT_QUOTES, 'UTF-8') ?></p>
            <div class="form-actions">
                <button id="btnConfirmPurge" class="btn btn-danger"><?= htmlspecialchars(_qt('form.confirm', 'Confirm'), ENT_QUOTES, 'UTF-8') ?></button>
                <button type="button" class="btn btn-secondary btn-close-modal" data-modal="purgeModal"><?= htmlspecialchars(_qt('form.cancel', 'Cancel'), ENT_QUOTES, 'UTF-8') ?></button>
            </div>
        </div>
    </div>

</div>

<script>
window.QUEUES_CONFIG = {
    apiBase:   <?= json_encode($apiBase) ?>,
    csrfToken: <?= json_encode($csrf) ?>,
    lang:      <?= json_encode($_qSafeLang) ?>,
    dir:       <?= json_encode($dir) ?>,
    strings:   <?= json_encode($_qStrings, JSON_UNESCAPED_UNICODE) ?>,
    canDelete: <?= json_encode($canDelete) ?>
};
</script>
<script src="/admin/assets/js/pages/queues.js?v=<?= time() ?>"></script>

<?php if (!$isFragment) require_once __DIR__ . '/../includes/footer.php'; ?>