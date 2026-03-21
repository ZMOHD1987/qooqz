<?php
declare(strict_types=1);

/**
 * /admin/fragments/bad_words.php
 * Bad Words Management - Production Ready
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
$dir      = in_array($lang, ['ar', 'he', 'fa', 'ur']) ? 'rtl' : 'ltr';
$csrf     = admin_csrf();
$tenantId = admin_tenant_id();
$userId   = admin_user_id();

// Permissions
$canManageBadWords = can('manage_bad_words') || is_super_admin();
$canCreate         = $canManageBadWords;
$canEdit           = $canManageBadWords;
$canDelete         = $canManageBadWords;

// Access check
if (!$canManageBadWords) {
    http_response_code(403);
    die('Access denied');
}

// Entity ID (optional)
$entityId = 0;

// API base
$apiBase = '/api';

// ════════════════════════════════════════════════════════════
// TRANSLATION HELPERS
// ════════════════════════════════════════════════════════════
$_bwStrings = [];
$_bwAllowedLangs = ['ar', 'en', 'fr', 'tr', 'ur', 'de', 'es', 'fa', 'he', 'hi', 'zh', 'ja', 'ko', 'pt', 'ru', 'it', 'nl', 'sv', 'pl', 'th', 'vi', 'id', 'ms', 'bn', 'sw', 'tl'];
$_bwSafeLang = in_array($lang, $_bwAllowedLangs, true) ? $lang : 'en';
$_bwLangFile = __DIR__ . '/../../languages/BadWords/' . $_bwSafeLang . '.json';
if (file_exists($_bwLangFile)) {
    $_bwJson = json_decode(file_get_contents($_bwLangFile), true);
    if (isset($_bwJson['strings'])) {
        $_bwStrings = $_bwJson['strings'];
    }
}

if (!function_exists('_bwt')) {
    function _bwt($key, $fallback = '') {
        global $_bwStrings;
        $keys = explode('.', $key);
        $val = $_bwStrings;
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

<link rel="stylesheet" href="/admin/assets/css/pages/bad_words.css?v=<?= time() ?>">
<meta data-page="bad_words"
      data-i18n-files="/languages/BadWords/<?= rawurlencode($lang) ?>.json">

<div class="page-container" id="badWordsPageContainer" dir="<?= htmlspecialchars($dir) ?>">

    <!-- Page Header -->
    <div class="page-header">
        <h1 data-i18n="title"><?= htmlspecialchars(_bwt('title', 'Bad Words Management'), ENT_QUOTES, 'UTF-8') ?></h1>
        <p data-i18n="subtitle"><?= htmlspecialchars(_bwt('subtitle', 'Manage blocked words and phrases'), ENT_QUOTES, 'UTF-8') ?></p>
        <div class="page-header-actions">
            <?php if ($canCreate): ?>
                <button id="btnAddWord" class="btn btn-primary" data-i18n="add_word"><?= htmlspecialchars(_bwt('add_word', 'Add Word'), ENT_QUOTES, 'UTF-8') ?></button>
            <?php endif; ?>
            <button id="btnOpenCheckText" class="btn btn-secondary" data-i18n="text_check.title"><?= htmlspecialchars(_bwt('text_check.title', 'Check Text'), ENT_QUOTES, 'UTF-8') ?></button>
        </div>
    </div>

    <!-- Filter Bar -->
    <div class="card">
        <div class="card-body filter-bar">
            <input type="text" id="filterSearch" class="form-control" placeholder="<?= htmlspecialchars(_bwt('filter.search_placeholder', 'Search words...'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-placeholder="filter.search_placeholder">
            <select id="filterSeverity" class="form-control" data-i18n="filter.severity">
                <option value="" data-i18n="filter.all_severities"><?= htmlspecialchars(_bwt('filter.all_severities', 'All Severities'), ENT_QUOTES, 'UTF-8') ?></option>
                <option value="low" data-i18n="severity.low"><?= htmlspecialchars(_bwt('severity.low', 'Low'), ENT_QUOTES, 'UTF-8') ?></option>
                <option value="medium" data-i18n="severity.medium"><?= htmlspecialchars(_bwt('severity.medium', 'Medium'), ENT_QUOTES, 'UTF-8') ?></option>
                <option value="high" data-i18n="severity.high"><?= htmlspecialchars(_bwt('severity.high', 'High'), ENT_QUOTES, 'UTF-8') ?></option>
            </select>
            <select id="filterStatus" class="form-control" data-i18n="filter.status">
                <option value="" data-i18n="filter.all_statuses"><?= htmlspecialchars(_bwt('filter.all_statuses', 'All Statuses'), ENT_QUOTES, 'UTF-8') ?></option>
                <option value="active" data-i18n="filter.active"><?= htmlspecialchars(_bwt('filter.active', 'Active'), ENT_QUOTES, 'UTF-8') ?></option>
                <option value="inactive" data-i18n="filter.inactive"><?= htmlspecialchars(_bwt('filter.inactive', 'Inactive'), ENT_QUOTES, 'UTF-8') ?></option>
            </select>
            <button id="btnFilter" class="btn btn-primary" data-i18n="filter.apply"><?= htmlspecialchars(_bwt('filter.apply', 'Filter'), ENT_QUOTES, 'UTF-8') ?></button>
            <button id="btnClearFilters" class="btn btn-secondary" data-i18n="filter.clear"><?= htmlspecialchars(_bwt('filter.clear', 'Clear Filters'), ENT_QUOTES, 'UTF-8') ?></button>
        </div>
    </div>

    <!-- Data Table -->
    <div class="card">
        <div class="card-body">
            <table class="data-table" id="badWordsTable">
                <thead>
                    <tr>
                        <th data-i18n="table.id"><?= htmlspecialchars(_bwt('table.id', 'ID'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th data-i18n="table.word"><?= htmlspecialchars(_bwt('table.word', 'Word'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th data-i18n="table.severity"><?= htmlspecialchars(_bwt('table.severity', 'Severity'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th data-i18n="table.regex"><?= htmlspecialchars(_bwt('table.regex', 'Regex'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th data-i18n="table.active"><?= htmlspecialchars(_bwt('table.active', 'Active'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th data-i18n="table.created"><?= htmlspecialchars(_bwt('table.created', 'Created'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th data-i18n="table.actions"><?= htmlspecialchars(_bwt('table.actions', 'Actions'), ENT_QUOTES, 'UTF-8') ?></th>
                    </tr>
                </thead>
                <tbody id="badWordsBody">
                    <tr id="emptyStateRow">
                        <td colspan="7" class="text-center" data-i18n="table.no_records"><?= htmlspecialchars(_bwt('table.no_records', 'No records found'), ENT_QUOTES, 'UTF-8') ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <!-- Pagination -->
        <div class="pagination-wrapper">
            <div class="pagination-info">
                <span data-i18n="pagination.showing"><?= htmlspecialchars(_bwt('pagination.showing', 'Showing'), ENT_QUOTES, 'UTF-8') ?></span>
                <span id="paginationInfo">0-0 <?= htmlspecialchars(_bwt('pagination.of', 'of'), ENT_QUOTES, 'UTF-8') ?> 0</span>
            </div>
            <div class="pagination" id="pagination"></div>
        </div>
    </div>

    <!-- Add/Edit Word Modal -->
    <div id="badWordModal" class="modal" style="display:none;">
        <div class="modal-content">
            <h3 id="badWordModalTitle" data-i18n="modal.add_title"><?= htmlspecialchars(_bwt('modal.add_title', 'Add Bad Word'), ENT_QUOTES, 'UTF-8') ?></h3>
            <form id="badWordForm">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                <input type="hidden" name="id" id="badWordId" value="">
                <div class="form-group">
                    <label data-i18n="form.word"><?= htmlspecialchars(_bwt('form.word', 'Word'), ENT_QUOTES, 'UTF-8') ?> *</label>
                    <input type="text" name="word" id="bwWord" class="form-control" required>
                </div>
                <div class="form-group">
                    <label data-i18n="form.severity"><?= htmlspecialchars(_bwt('form.severity', 'Severity'), ENT_QUOTES, 'UTF-8') ?></label>
                    <select name="severity" id="bwSeverity" class="form-control">
                        <option value="low" data-i18n="severity.low"><?= htmlspecialchars(_bwt('severity.low', 'Low'), ENT_QUOTES, 'UTF-8') ?></option>
                        <option value="medium" data-i18n="severity.medium"><?= htmlspecialchars(_bwt('severity.medium', 'Medium'), ENT_QUOTES, 'UTF-8') ?></option>
                        <option value="high" data-i18n="severity.high"><?= htmlspecialchars(_bwt('severity.high', 'High'), ENT_QUOTES, 'UTF-8') ?></option>
                    </select>
                </div>
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="is_regex" id="bwIsRegex" value="1">
                        <span data-i18n="form.is_regex"><?= htmlspecialchars(_bwt('form.is_regex', 'Is Regex'), ENT_QUOTES, 'UTF-8') ?></span>
                    </label>
                </div>
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="is_active" id="bwIsActive" value="1" checked>
                        <span data-i18n="form.is_active"><?= htmlspecialchars(_bwt('form.is_active', 'Is Active'), ENT_QUOTES, 'UTF-8') ?></span>
                    </label>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary" data-i18n="form.save"><?= htmlspecialchars(_bwt('form.save', 'Save'), ENT_QUOTES, 'UTF-8') ?></button>
                    <button type="button" class="btn btn-secondary btn-close-modal" data-modal="badWordModal" data-i18n="form.cancel"><?= htmlspecialchars(_bwt('form.cancel', 'Cancel'), ENT_QUOTES, 'UTF-8') ?></button>
                </div>
            </form>
        </div>
    </div>

    <!-- Translations Modal -->
    <div id="translationsModal" class="modal" style="display:none;">
        <div class="modal-content">
            <h3 id="translationsModalTitle" data-i18n="translations.title"><?= htmlspecialchars(_bwt('translations.title', 'Word Translations'), ENT_QUOTES, 'UTF-8') ?></h3>
            <input type="hidden" id="transWordId" value="">
            <div class="form-group">
                <label data-i18n="translations.language"><?= htmlspecialchars(_bwt('translations.language', 'Language'), ENT_QUOTES, 'UTF-8') ?></label>
                <select id="transLangCode" class="form-control">
                    <!-- Languages loaded dynamically from /api/languages -->
                </select>
            </div>
            <div class="form-group">
                <label data-i18n="translations.translated_word"><?= htmlspecialchars(_bwt('translations.translated_word', 'Translated Word'), ENT_QUOTES, 'UTF-8') ?></label>
                <input type="text" id="transWord" class="form-control">
            </div>
            <div class="form-actions">
                <button id="btnAddTranslation" class="btn btn-primary" data-i18n="translations.add"><?= htmlspecialchars(_bwt('translations.add', 'Add Translation'), ENT_QUOTES, 'UTF-8') ?></button>
                <button type="button" class="btn btn-secondary btn-close-modal" data-modal="translationsModal" data-i18n="form.cancel"><?= htmlspecialchars(_bwt('form.cancel', 'Cancel'), ENT_QUOTES, 'UTF-8') ?></button>
            </div>
            <table class="data-table" id="translationsTable">
                <thead>
                    <tr>
                        <th data-i18n="translations.language"><?= htmlspecialchars(_bwt('translations.language', 'Language'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th data-i18n="translations.word"><?= htmlspecialchars(_bwt('translations.word', 'Word'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th data-i18n="table.actions"><?= htmlspecialchars(_bwt('table.actions', 'Actions'), ENT_QUOTES, 'UTF-8') ?></th>
                    </tr>
                </thead>
                <tbody id="translationsBody"></tbody>
            </table>
        </div>
    </div>

    <!-- Text Check Modal -->
    <div id="textCheckModal" class="modal" style="display:none;">
        <div class="modal-content">
            <h3 id="textCheckModalTitle" data-i18n="text_check.title"><?= htmlspecialchars(_bwt('text_check.title', 'Check Text'), ENT_QUOTES, 'UTF-8') ?></h3>
            <div class="form-group">
                <label data-i18n="text_check.input_label"><?= htmlspecialchars(_bwt('text_check.input_label', 'Text to check'), ENT_QUOTES, 'UTF-8') ?></label>
                <textarea id="textCheckInput" class="form-control" rows="4"></textarea>
            </div>
            <div class="form-actions">
                <button id="btnCheckText" class="btn btn-primary" data-i18n="text_check.check"><?= htmlspecialchars(_bwt('text_check.check', 'Check'), ENT_QUOTES, 'UTF-8') ?></button>
                <button type="button" class="btn btn-secondary btn-close-modal" data-modal="textCheckModal" data-i18n="form.cancel"><?= htmlspecialchars(_bwt('form.cancel', 'Cancel'), ENT_QUOTES, 'UTF-8') ?></button>
            </div>
            <div id="textCheckResults"></div>
        </div>
    </div>

</div>

<script>
window.BAD_WORDS_CONFIG = {
    apiBase:   <?= json_encode($apiBase) ?>,
    csrfToken: <?= json_encode($csrf) ?>,
    entityId:  <?= $entityId ?>,
    lang:      <?= json_encode($_bwSafeLang) ?>,
    dir:       <?= json_encode($dir) ?>,
    strings:   <?= json_encode($_bwStrings, JSON_UNESCAPED_UNICODE) ?>,
    canCreate: <?= json_encode($canCreate) ?>,
    canEdit:   <?= json_encode($canEdit) ?>,
    canDelete: <?= json_encode($canDelete) ?>
};
</script>
<script src="/admin/assets/js/pages/bad_words.js?v=<?= time() ?>"></script>

<?php if (!$isFragment) require_once __DIR__ . '/../includes/footer.php'; ?>