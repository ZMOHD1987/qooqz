<?php
declare(strict_types=1);

/**
 * /admin/fragments/bad_words.php
 * Bad Words Management — Production v2.0
 * ─ متوافق مع header.php v3.0 (single source of truth)
 * ─ لا hardcoded styles — كل التنسيق من CSS variables
 * ─ assetVer() بدل time() لـ cache-busting صحيح
 */

$isAjax     = !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
              && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
$isEmbedded = isset($_GET['embedded']) || isset($_POST['embedded']);
$isFragment = $isAjax || $isEmbedded;

// ── Context ──────────────────────────────────────────────────
if ($isFragment) {
    require_once __DIR__ . '/../includes/admin_context.php';
} else {
    require_once __DIR__ . '/../includes/header.php';
}

// ── Auth ─────────────────────────────────────────────────────
if (!is_admin_logged_in()) {
    if ($isFragment) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Not authenticated']);
        exit;
    }
    header('Location: /admin/login.php');
    exit;
}

// ── Context vars ─────────────────────────────────────────────
$user     = admin_user();
$lang     = admin_lang();
$dir      = in_array($lang, ['ar', 'he', 'fa', 'ur'], true) ? 'rtl' : 'ltr';
$csrf     = admin_csrf();
$tenantId = admin_tenant_id();
$userId   = admin_user_id();

// ── Permissions ──────────────────────────────────────────────
$canManage = can('manage_bad_words') || is_super_admin();
$canCreate = $canManage;
$canEdit   = $canManage;
$canDelete = $canManage;

if (!$canManage) {
    http_response_code(403);
    exit('Access denied');
}

$apiBase  = '/api';
$entityId = 0;

// ── Translations (server-side fallback) ──────────────────────
$_bwStrings     = [];
$_bwAllowedLangs = [
    'ar','en','fr','tr','ur','de','es','fa','he','hi',
    'zh','ja','ko','pt','ru','it','nl','sv','pl','th',
    'vi','id','ms','bn','sw','tl',
];
$_bwSafeLang = in_array($lang, $_bwAllowedLangs, true) ? $lang : 'en';
$_bwLangFile = __DIR__ . '/../../languages/BadWords/' . $_bwSafeLang . '.json';

if (file_exists($_bwLangFile)) {
    $_bwJson = json_decode(file_get_contents($_bwLangFile), true);
    if (isset($_bwJson['strings'])) {
        $_bwStrings = $_bwJson['strings'];
    }
}

/**
 * Translate dot-notation key — PHP fallback only.
 * Runtime translations handled by data-i18n attributes via admin_core.js.
 */
function _bwt(string $key, string $fallback = ''): string
{
    global $_bwStrings;
    $parts = explode('.', $key);
    $val   = $_bwStrings;
    foreach ($parts as $k) {
        if (is_array($val) && isset($val[$k])) {
            $val = $val[$k];
        } else {
            return $fallback !== '' ? $fallback : $key;
        }
    }
    return is_string($val) ? $val : ($fallback !== '' ? $fallback : $key);
}

// assetVer() مُعرَّفة في header.php — نُعرِّفها هنا فقط عند fragment مستقل
if (!function_exists('assetVer')) {
    function assetVer(string $path): string
    {
        static $cache = [];
        if (!isset($cache[$path])) {
            $full         = $_SERVER['DOCUMENT_ROOT'] . $path;
            $cache[$path] = file_exists($full) ? (string) filemtime($full) : '0';
        }
        return $cache[$path];
    }
}
?>
<link rel="stylesheet"
      href="/admin/assets/css/pages/bad_words.css?v=<?= assetVer('/admin/assets/css/pages/bad_words.css') ?>">

<meta data-page="bad_words"
      data-i18n-files="/languages/BadWords/<?= rawurlencode($_bwSafeLang) ?>.json">

<div class="page-container" id="badWordsPageContainer" dir="<?= htmlspecialchars($dir, ENT_QUOTES, 'UTF-8') ?>">

    <!-- ═══════════════════════════════════════════
         PAGE HEADER
    ════════════════════════════════════════════ -->
    <div class="page-header">
        <div class="page-header-content">
            <h1 class="page-title" data-i18n="title">
                <?= htmlspecialchars(_bwt('title', 'Bad Words Management'), ENT_QUOTES, 'UTF-8') ?>
            </h1>
            <p class="page-subtitle" data-i18n="subtitle">
                <?= htmlspecialchars(_bwt('subtitle', 'Manage blocked words and phrases'), ENT_QUOTES, 'UTF-8') ?>
            </p>
        </div>
        <div class="page-header-actions">
            <?php if ($canCreate): ?>
            <button id="btnAddWord" class="btn btn-primary" data-i18n="add_word">
                <i class="fas fa-plus" aria-hidden="true"></i>
                <?= htmlspecialchars(_bwt('add_word', 'Add Word'), ENT_QUOTES, 'UTF-8') ?>
            </button>
            <?php endif; ?>
            <button id="btnOpenCheckText" class="btn btn-secondary" data-i18n="text_check.title">
                <i class="fas fa-search" aria-hidden="true"></i>
                <?= htmlspecialchars(_bwt('text_check.title', 'Check Text'), ENT_QUOTES, 'UTF-8') ?>
            </button>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════
         FILTER BAR
    ════════════════════════════════════════════ -->
    <div class="card">
        <div class="card-body">
            <div class="filters-grid">
                <div class="filter-group">
                    <label class="filter-label" for="filterSearch" data-i18n="filter.search">
                        <?= htmlspecialchars(_bwt('filter.search', 'Search'), ENT_QUOTES, 'UTF-8') ?>
                    </label>
                    <input type="text"
                           id="filterSearch"
                           class="form-control"
                           placeholder="<?= htmlspecialchars(_bwt('filter.search_placeholder', 'Search words...'), ENT_QUOTES, 'UTF-8') ?>"
                           data-i18n-placeholder="filter.search_placeholder">
                </div>

                <div class="filter-group">
                    <label class="filter-label" for="filterSeverity" data-i18n="filter.severity">
                        <?= htmlspecialchars(_bwt('filter.severity', 'Severity'), ENT_QUOTES, 'UTF-8') ?>
                    </label>
                    <select id="filterSeverity" class="form-control">
                        <option value=""       data-i18n="filter.all_severities">
                            <?= htmlspecialchars(_bwt('filter.all_severities', 'All Severities'), ENT_QUOTES, 'UTF-8') ?>
                        </option>
                        <option value="low"    data-i18n="severity.low">
                            <?= htmlspecialchars(_bwt('severity.low',    'Low'),    ENT_QUOTES, 'UTF-8') ?>
                        </option>
                        <option value="medium" data-i18n="severity.medium">
                            <?= htmlspecialchars(_bwt('severity.medium', 'Medium'), ENT_QUOTES, 'UTF-8') ?>
                        </option>
                        <option value="high"   data-i18n="severity.high">
                            <?= htmlspecialchars(_bwt('severity.high',   'High'),   ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    </select>
                </div>

                <div class="filter-group">
                    <label class="filter-label" for="filterStatus" data-i18n="filter.status">
                        <?= htmlspecialchars(_bwt('filter.status', 'Status'), ENT_QUOTES, 'UTF-8') ?>
                    </label>
                    <select id="filterStatus" class="form-control">
                        <option value=""         data-i18n="filter.all_statuses">
                            <?= htmlspecialchars(_bwt('filter.all_statuses', 'All Statuses'), ENT_QUOTES, 'UTF-8') ?>
                        </option>
                        <option value="active"   data-i18n="filter.active">
                            <?= htmlspecialchars(_bwt('filter.active',   'Active'),   ENT_QUOTES, 'UTF-8') ?>
                        </option>
                        <option value="inactive" data-i18n="filter.inactive">
                            <?= htmlspecialchars(_bwt('filter.inactive', 'Inactive'), ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    </select>
                </div>

                <div class="filter-group">
                    <label class="filter-label" aria-hidden="true">&nbsp;</label>
                    <div class="filter-buttons">
                        <button id="btnFilter" class="btn btn-primary" data-i18n="filter.apply">
                            <?= htmlspecialchars(_bwt('filter.apply', 'Filter'), ENT_QUOTES, 'UTF-8') ?>
                        </button>
                        <button id="btnClearFilters" class="btn btn-secondary" data-i18n="filter.clear">
                            <?= htmlspecialchars(_bwt('filter.clear', 'Clear'), ENT_QUOTES, 'UTF-8') ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════
         DATA TABLE
    ════════════════════════════════════════════ -->
    <div class="card">
        <div class="card-body">

            <div id="bwLoading" class="loading-state" style="display:none;">
                <div class="spinner" role="status" aria-label="Loading"></div>
                <p data-i18n="loading">
                    <?= htmlspecialchars(_bwt('loading', 'Loading...'), ENT_QUOTES, 'UTF-8') ?>
                </p>
            </div>

            <div id="bwEmpty" class="empty-state" style="display:none;">
                <div class="empty-icon"><i class="fas fa-ban" aria-hidden="true"></i></div>
                <h3 data-i18n="table.no_records">
                    <?= htmlspecialchars(_bwt('table.no_records', 'No records found'), ENT_QUOTES, 'UTF-8') ?>
                </h3>
                <?php if ($canCreate): ?>
                <button id="btnAddWordEmpty" class="btn btn-primary" data-i18n="add_word">
                    <?= htmlspecialchars(_bwt('add_word', 'Add Word'), ENT_QUOTES, 'UTF-8') ?>
                </button>
                <?php endif; ?>
            </div>

            <div id="bwError" class="error-state" style="display:none;">
                <div class="error-icon"><i class="fas fa-exclamation-triangle" aria-hidden="true"></i></div>
                <h3 data-i18n="error.title">
                    <?= htmlspecialchars(_bwt('error.title', 'Something went wrong'), ENT_QUOTES, 'UTF-8') ?>
                </h3>
                <p id="bwErrorMessage"></p>
                <button id="btnRetry" class="btn btn-primary" data-i18n="retry">
                    <?= htmlspecialchars(_bwt('retry', 'Retry'), ENT_QUOTES, 'UTF-8') ?>
                </button>
            </div>

            <div id="bwTableContainer" class="table-responsive">
                <table class="data-table" id="badWordsTable" aria-label="Bad Words">
                    <thead>
                        <tr>
                            <th data-i18n="table.id">
                                <?= htmlspecialchars(_bwt('table.id',       'ID'),       ENT_QUOTES, 'UTF-8') ?>
                            </th>
                            <th data-i18n="table.word">
                                <?= htmlspecialchars(_bwt('table.word',     'Word'),     ENT_QUOTES, 'UTF-8') ?>
                            </th>
                            <th data-i18n="table.severity">
                                <?= htmlspecialchars(_bwt('table.severity', 'Severity'), ENT_QUOTES, 'UTF-8') ?>
                            </th>
                            <th data-i18n="table.regex">
                                <?= htmlspecialchars(_bwt('table.regex',    'Regex'),    ENT_QUOTES, 'UTF-8') ?>
                            </th>
                            <th data-i18n="table.active">
                                <?= htmlspecialchars(_bwt('table.active',   'Active'),   ENT_QUOTES, 'UTF-8') ?>
                            </th>
                            <th data-i18n="table.created">
                                <?= htmlspecialchars(_bwt('table.created',  'Created'),  ENT_QUOTES, 'UTF-8') ?>
                            </th>
                            <th data-i18n="table.actions">
                                <?= htmlspecialchars(_bwt('table.actions',  'Actions'),  ENT_QUOTES, 'UTF-8') ?>
                            </th>
                        </tr>
                    </thead>
                    <tbody id="badWordsBody"></tbody>
                </table>
            </div>
        </div>

        <div class="pagination-wrapper">
            <div class="pagination-info" id="paginationInfo" aria-live="polite"></div>
            <div class="pagination" id="pagination" role="navigation" aria-label="Pagination"></div>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════
         MODAL: ADD / EDIT WORD
    ════════════════════════════════════════════ -->
    <div id="badWordModal"
         class="bw-modal-backdrop"
         role="dialog"
         aria-modal="true"
         aria-labelledby="badWordModalTitle"
         style="display:none;">
        <div class="bw-modal-panel">
            <div class="bw-modal-header">
                <h3 id="badWordModalTitle" data-i18n="modal.add_title">
                    <?= htmlspecialchars(_bwt('modal.add_title', 'Add Bad Word'), ENT_QUOTES, 'UTF-8') ?>
                </h3>
                <button type="button"
                        class="btn-close-modal icon-btn"
                        data-modal="badWordModal"
                        aria-label="Close">
                    <i class="fas fa-times" aria-hidden="true"></i>
                </button>
            </div>
            <div class="bw-modal-body">
                <form id="badWordForm" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="id" id="badWordId" value="">

                    <div class="form-group">
                        <label class="required" for="bwWord" data-i18n="form.word">
                            <?= htmlspecialchars(_bwt('form.word', 'Word'), ENT_QUOTES, 'UTF-8') ?>
                        </label>
                        <input type="text"
                               name="word"
                               id="bwWord"
                               class="form-control"
                               required
                               autocomplete="off">
                        <div class="invalid-feedback" data-i18n="form.word_required">
                            <?= htmlspecialchars(_bwt('form.word_required', 'Word is required'), ENT_QUOTES, 'UTF-8') ?>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="bwSeverity" data-i18n="form.severity">
                            <?= htmlspecialchars(_bwt('form.severity', 'Severity'), ENT_QUOTES, 'UTF-8') ?>
                        </label>
                        <select name="severity" id="bwSeverity" class="form-control">
                            <option value="low"    data-i18n="severity.low">
                                <?= htmlspecialchars(_bwt('severity.low',    'Low'),    ENT_QUOTES, 'UTF-8') ?>
                            </option>
                            <option value="medium" data-i18n="severity.medium" selected>
                                <?= htmlspecialchars(_bwt('severity.medium', 'Medium'), ENT_QUOTES, 'UTF-8') ?>
                            </option>
                            <option value="high"   data-i18n="severity.high">
                                <?= htmlspecialchars(_bwt('severity.high',   'High'),   ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        </select>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-check-label" for="bwIsRegex">
                                <input type="checkbox" name="is_regex" id="bwIsRegex" value="1">
                                <span data-i18n="form.is_regex">
                                    <?= htmlspecialchars(_bwt('form.is_regex', 'Is Regex'), ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </label>
                        </div>
                        <div class="form-group">
                            <label class="form-check-label" for="bwIsActive">
                                <input type="checkbox" name="is_active" id="bwIsActive" value="1" checked>
                                <span data-i18n="form.is_active">
                                    <?= htmlspecialchars(_bwt('form.is_active', 'Is Active'), ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </label>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary" data-i18n="form.save">
                            <i class="fas fa-save" aria-hidden="true"></i>
                            <?= htmlspecialchars(_bwt('form.save', 'Save'), ENT_QUOTES, 'UTF-8') ?>
                        </button>
                        <button type="button"
                                class="btn btn-secondary btn-close-modal"
                                data-modal="badWordModal"
                                data-i18n="form.cancel">
                            <?= htmlspecialchars(_bwt('form.cancel', 'Cancel'), ENT_QUOTES, 'UTF-8') ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════
         MODAL: TRANSLATIONS
    ════════════════════════════════════════════ -->
    <div id="translationsModal"
         class="bw-modal-backdrop"
         role="dialog"
         aria-modal="true"
         aria-labelledby="translationsModalTitle"
         style="display:none;">
        <div class="bw-modal-panel bw-modal-panel--wide">
            <div class="bw-modal-header">
                <h3 id="translationsModalTitle" data-i18n="translations.title">
                    <?= htmlspecialchars(_bwt('translations.title', 'Word Translations'), ENT_QUOTES, 'UTF-8') ?>
                </h3>
                <button type="button"
                        class="btn-close-modal icon-btn"
                        data-modal="translationsModal"
                        aria-label="Close">
                    <i class="fas fa-times" aria-hidden="true"></i>
                </button>
            </div>
            <div class="bw-modal-body">
                <input type="hidden" id="transWordId" value="">

                <div class="form-row">
                    <div class="form-group">
                        <label for="transLangCode" data-i18n="translations.language">
                            <?= htmlspecialchars(_bwt('translations.language', 'Language'), ENT_QUOTES, 'UTF-8') ?>
                        </label>
                        <select id="transLangCode" class="form-control"></select>
                    </div>
                    <div class="form-group">
                        <label for="transWord" data-i18n="translations.translated_word">
                            <?= htmlspecialchars(_bwt('translations.translated_word', 'Translated Word'), ENT_QUOTES, 'UTF-8') ?>
                        </label>
                        <input type="text" id="transWord" class="form-control" autocomplete="off">
                    </div>
                </div>

                <div class="form-actions">
                    <button id="btnSaveTranslation" class="btn btn-primary">
                        <i class="fas fa-save" aria-hidden="true"></i>
                        <span id="btnSaveTranslationLabel" data-i18n="translations.add">
                            <?= htmlspecialchars(_bwt('translations.add', 'Add Translation'), ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    </button>
                    <button type="button"
                            class="btn btn-secondary btn-close-modal"
                            data-modal="translationsModal"
                            data-i18n="form.cancel">
                        <?= htmlspecialchars(_bwt('form.cancel', 'Cancel'), ENT_QUOTES, 'UTF-8') ?>
                    </button>
                </div>

                <div class="table-responsive" style="margin-top:1rem;">
                    <table class="data-table" id="translationsTable">
                        <thead>
                            <tr>
                                <th data-i18n="translations.language">
                                    <?= htmlspecialchars(_bwt('translations.language', 'Language'), ENT_QUOTES, 'UTF-8') ?>
                                </th>
                                <th data-i18n="translations.word">
                                    <?= htmlspecialchars(_bwt('translations.word', 'Word'), ENT_QUOTES, 'UTF-8') ?>
                                </th>
                                <th data-i18n="table.actions">
                                    <?= htmlspecialchars(_bwt('table.actions', 'Actions'), ENT_QUOTES, 'UTF-8') ?>
                                </th>
                            </tr>
                        </thead>
                        <tbody id="translationsBody"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════
         MODAL: TEXT CHECK
    ════════════════════════════════════════════ -->
    <div id="textCheckModal"
         class="bw-modal-backdrop"
         role="dialog"
         aria-modal="true"
         style="display:none;">
        <div class="bw-modal-panel">
            <div class="bw-modal-header">
                <h3 data-i18n="text_check.title">
                    <?= htmlspecialchars(_bwt('text_check.title', 'Check Text'), ENT_QUOTES, 'UTF-8') ?>
                </h3>
                <button type="button"
                        class="btn-close-modal icon-btn"
                        data-modal="textCheckModal"
                        aria-label="Close">
                    <i class="fas fa-times" aria-hidden="true"></i>
                </button>
            </div>
            <div class="bw-modal-body">
                <div class="form-group">
                    <label for="textCheckInput" data-i18n="text_check.input_label">
                        <?= htmlspecialchars(_bwt('text_check.input_label', 'Text to check'), ENT_QUOTES, 'UTF-8') ?>
                    </label>
                    <textarea id="textCheckInput" class="form-control" rows="5"></textarea>
                </div>
                <div class="form-actions">
                    <button id="btnCheckText" class="btn btn-primary" data-i18n="text_check.check">
                        <i class="fas fa-search" aria-hidden="true"></i>
                        <?= htmlspecialchars(_bwt('text_check.check', 'Check'), ENT_QUOTES, 'UTF-8') ?>
                    </button>
                    <button type="button"
                            class="btn btn-secondary btn-close-modal"
                            data-modal="textCheckModal"
                            data-i18n="form.cancel">
                        <?= htmlspecialchars(_bwt('form.cancel', 'Cancel'), ENT_QUOTES, 'UTF-8') ?>
                    </button>
                </div>
                <div id="textCheckResults" class="check-results" style="display:none;"></div>
            </div>
        </div>
    </div>

</div><!-- /.page-container -->

<script>
window.BAD_WORDS_CONFIG = {
    apiBase:   <?= json_encode($apiBase,    JSON_UNESCAPED_SLASHES) ?>,
    csrfToken: <?= json_encode($csrf) ?>,
    entityId:  <?= (int) $entityId ?>,
    lang:      <?= json_encode($_bwSafeLang) ?>,
    dir:       <?= json_encode($dir) ?>,
    strings:   <?= json_encode($_bwStrings, JSON_UNESCAPED_UNICODE) ?>,
    canCreate: <?= json_encode($canCreate) ?>,
    canEdit:   <?= json_encode($canEdit) ?>,
    canDelete: <?= json_encode($canDelete) ?>
};
</script>
<script src="/admin/assets/js/pages/bad_words.js?v=<?= assetVer('/admin/assets/js/pages/bad_words.js') ?>"></script>

<?php if (!$isFragment) require_once __DIR__ . '/../includes/footer.php'; ?>