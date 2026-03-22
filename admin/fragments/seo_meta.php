<?php
declare(strict_types=1);

/**
 * /admin/fragments/seo_meta.php
 * SEO Meta Management — Production v2.0
 * ─ متوافق مع header.php v3.0 (single source of truth)
 * ─ assetVer() بدل time()
 * ─ sm-modal-* بدل .modal لتجنب التعارض
 * ─ filters-grid / filter-group / filter-buttons
 * ─ loading / empty / error states
 */

$isAjax     = !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
              && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
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
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Not authenticated']);
        exit;
    }
    header('Location: /admin/login.php');
    exit;
}

$user     = admin_user();
$lang     = admin_lang();
$dir      = in_array($lang, ['ar', 'he', 'fa', 'ur'], true) ? 'rtl' : 'ltr';
$csrf     = admin_csrf();
$tenantId = admin_tenant_id();
$userId   = admin_user_id();

$canManage = can('manage_settings') || is_super_admin();
$canCreate = $canManage;
$canEdit   = $canManage;
$canDelete = $canManage;

if (!$canManage) {
    http_response_code(403);
    exit('Access denied');
}

$apiBase = '/api';

// ── Translations ─────────────────────────────────────────────
$_stStrings     = [];
$_stAllowedLangs = [
    'ar','en','fr','tr','ur','de','es','fa','he','hi',
    'zh','ja','ko','pt','ru','it','nl','sv','pl','th',
    'vi','id','ms','bn','sw','tl',
];
$_stSafeLang = in_array($lang, $_stAllowedLangs, true) ? $lang : 'en';
$_stLangFile = __DIR__ . '/../../languages/SeoMeta/' . $_stSafeLang . '.json';

if (file_exists($_stLangFile)) {
    $_stJson = json_decode(file_get_contents($_stLangFile), true);
    if (isset($_stJson['strings'])) {
        $_stStrings = $_stJson['strings'];
    }
}

function _st(string $key, string $fallback = ''): string
{
    global $_stStrings;
    $parts = explode('.', $key);
    $val   = $_stStrings;
    foreach ($parts as $k) {
        if (is_array($val) && isset($val[$k])) {
            $val = $val[$k];
        } else {
            return $fallback !== '' ? $fallback : $key;
        }
    }
    return is_string($val) ? $val : ($fallback !== '' ? $fallback : $key);
}

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
      href="/admin/assets/css/pages/seo_meta.css?v=<?= assetVer('/admin/assets/css/pages/seo_meta.css') ?>">

<meta data-page="seo_meta"
      data-i18n-files="/languages/SeoMeta/<?= rawurlencode($_stSafeLang) ?>.json">

<div class="page-container" id="seoMetaPageContainer" dir="<?= htmlspecialchars($dir, ENT_QUOTES, 'UTF-8') ?>">

    <!-- ═══════════════════════════════════════════
         PAGE HEADER
    ════════════════════════════════════════════ -->
    <div class="page-header">
        <div class="page-header-content">
            <h1 class="page-title" data-i18n="title">
                <?= htmlspecialchars(_st('title', 'SEO Meta Management'), ENT_QUOTES, 'UTF-8') ?>
            </h1>
            <p class="page-subtitle" data-i18n="subtitle">
                <?= htmlspecialchars(_st('subtitle', 'Manage SEO metadata for your content'), ENT_QUOTES, 'UTF-8') ?>
            </p>
        </div>
        <div class="page-header-actions">
            <?php if ($canCreate): ?>
            <button id="btnAddSeoMeta" class="btn btn-primary" data-i18n="add_new">
                <i class="fas fa-plus" aria-hidden="true"></i>
                <?= htmlspecialchars(_st('add_new', 'Add SEO Record'), ENT_QUOTES, 'UTF-8') ?>
            </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════
         ADD / EDIT FORM CARD
    ════════════════════════════════════════════ -->
    <div id="seoMetaFormCard" class="card sm-form-card" style="display:none;">
        <div class="card-header">
            <h3 class="card-title" id="seoMetaFormTitle" data-i18n="modal.add_title">
                <?= htmlspecialchars(_st('modal.add_title', 'Add SEO Record'), ENT_QUOTES, 'UTF-8') ?>
            </h3>
            <button type="button" id="btnCloseForm" class="icon-btn" aria-label="Close">
                <i class="fas fa-times" aria-hidden="true"></i>
            </button>
        </div>
        <div class="card-body">
            <form id="seoMetaForm" novalidate>
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="id" id="seoMetaId" value="">

                <!-- Tabs -->
                <div class="form-tabs" role="tablist">
                    <button type="button" class="tab-btn active" data-tab="sm-general" role="tab" aria-selected="true">
                        <i class="fas fa-info-circle" aria-hidden="true"></i>
                        <span data-i18n="tabs.general">
                            <?= htmlspecialchars(_st('tabs.general', 'General'), ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    </button>
                    <button type="button" class="tab-btn" data-tab="sm-translations"
                            id="tabTranslationsBtn" role="tab" aria-selected="false" style="display:none;">
                        <i class="fas fa-language" aria-hidden="true"></i>
                        <span data-i18n="tabs.translations">
                            <?= htmlspecialchars(_st('tabs.translations', 'Translations'), ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    </button>
                </div>

                <!-- ─ Tab: General ────────────────────────── -->
                <div class="tab-content active" id="tab-sm-general" role="tabpanel">
                    <div class="form-row">
                        <div class="form-group">
                            <label class="required" for="smEntityType" data-i18n="form.entity_type">
                                <?= htmlspecialchars(_st('form.entity_type', 'Entity Type'), ENT_QUOTES, 'UTF-8') ?>
                            </label>
                            <select name="entity_type" id="smEntityType" class="form-control" required>
                                <option value="product"  data-i18n="entity_type.product">
                                    <?= htmlspecialchars(_st('entity_type.product',  'Product'),  ENT_QUOTES, 'UTF-8') ?>
                                </option>
                                <option value="category" data-i18n="entity_type.category">
                                    <?= htmlspecialchars(_st('entity_type.category', 'Category'), ENT_QUOTES, 'UTF-8') ?>
                                </option>
                                <option value="entity"   data-i18n="entity_type.entity">
                                    <?= htmlspecialchars(_st('entity_type.entity',   'Entity'),   ENT_QUOTES, 'UTF-8') ?>
                                </option>
                                <option value="page"     data-i18n="entity_type.page">
                                    <?= htmlspecialchars(_st('entity_type.page',     'Page'),     ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="required" for="smEntityId" data-i18n="form.entity_id">
                                <?= htmlspecialchars(_st('form.entity_id', 'Entity ID'), ENT_QUOTES, 'UTF-8') ?>
                            </label>
                            <input type="number" name="entity_id" id="smEntityId"
                                   class="form-control" required min="1">
                            <div class="invalid-feedback" data-i18n="form.entity_id_required">
                                <?= htmlspecialchars(_st('form.entity_id_required', 'Entity ID is required'), ENT_QUOTES, 'UTF-8') ?>
                            </div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="smCanonicalUrl" data-i18n="form.canonical_url">
                                <?= htmlspecialchars(_st('form.canonical_url', 'Canonical URL'), ENT_QUOTES, 'UTF-8') ?>
                            </label>
                            <input type="text" name="canonical_url" id="smCanonicalUrl"
                                   class="form-control" placeholder="https://example.com/page"
                                   autocomplete="off">
                        </div>
                        <div class="form-group">
                            <label for="smRobots" data-i18n="form.robots">
                                <?= htmlspecialchars(_st('form.robots', 'Robots'), ENT_QUOTES, 'UTF-8') ?>
                            </label>
                            <select name="robots" id="smRobots" class="form-control">
                                <option value="index,follow">index,follow</option>
                                <option value="noindex,nofollow">noindex,nofollow</option>
                                <option value="index,nofollow">index,nofollow</option>
                                <option value="noindex,follow">noindex,follow</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="smSchemaMarkup" data-i18n="form.schema_markup">
                            <?= htmlspecialchars(_st('form.schema_markup', 'Schema Markup (JSON)'), ENT_QUOTES, 'UTF-8') ?>
                        </label>
                        <textarea name="schema_markup" id="smSchemaMarkup" class="form-control sm-schema-textarea"
                                  rows="4" placeholder='{"@context":"https://schema.org"}'
                                  spellcheck="false"></textarea>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary" data-i18n="form.save">
                            <i class="fas fa-save" aria-hidden="true"></i>
                            <?= htmlspecialchars(_st('form.save', 'Save'), ENT_QUOTES, 'UTF-8') ?>
                        </button>
                        <button type="button" id="btnCancelForm" class="btn btn-secondary" data-i18n="form.cancel">
                            <?= htmlspecialchars(_st('form.cancel', 'Cancel'), ENT_QUOTES, 'UTF-8') ?>
                        </button>
                    </div>
                </div><!-- /tab-sm-general -->

                <!-- ─ Tab: Translations ───────────────────── -->
                <div class="tab-content" id="tab-sm-translations" role="tabpanel" style="display:none;">
                    <input type="hidden" id="transSeoMetaId" value="">

                    <div class="sm-trans-toolbar">
                        <button type="button" id="btnShowAddTransForm" class="btn btn-primary btn-sm">
                            <i class="fas fa-plus" aria-hidden="true"></i>
                            <span data-i18n="translations.add">
                                <?= htmlspecialchars(_st('translations.add', 'Add Translation'), ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        </button>
                    </div>

                    <!-- Add translation panel -->
                    <div id="addTransPanel" class="sm-trans-add-panel" style="display:none;">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="transLangCode" data-i18n="translations.language">
                                    <?= htmlspecialchars(_st('translations.language', 'Language'), ENT_QUOTES, 'UTF-8') ?>
                                </label>
                                <select id="transLangCode" class="form-control"></select>
                            </div>
                            <div class="form-group">
                                <label for="transMetaTitle" data-i18n="translations.meta_title">
                                    <?= htmlspecialchars(_st('translations.meta_title', 'Meta Title'), ENT_QUOTES, 'UTF-8') ?>
                                </label>
                                <input type="text" id="transMetaTitle" class="form-control" autocomplete="off">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="transOgTitle" data-i18n="translations.og_title">
                                    <?= htmlspecialchars(_st('translations.og_title', 'OG Title'), ENT_QUOTES, 'UTF-8') ?>
                                </label>
                                <input type="text" id="transOgTitle" class="form-control" autocomplete="off">
                            </div>
                            <div class="form-group">
                                <label for="transMetaKeywords" data-i18n="translations.meta_keywords">
                                    <?= htmlspecialchars(_st('translations.meta_keywords', 'Meta Keywords'), ENT_QUOTES, 'UTF-8') ?>
                                </label>
                                <input type="text" id="transMetaKeywords" class="form-control" autocomplete="off">
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="transMetaDescription" data-i18n="translations.meta_description">
                                <?= htmlspecialchars(_st('translations.meta_description', 'Meta Description'), ENT_QUOTES, 'UTF-8') ?>
                            </label>
                            <textarea id="transMetaDescription" class="form-control" rows="2"></textarea>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="transOgDescription" data-i18n="translations.og_description">
                                    <?= htmlspecialchars(_st('translations.og_description', 'OG Description'), ENT_QUOTES, 'UTF-8') ?>
                                </label>
                                <textarea id="transOgDescription" class="form-control" rows="2"></textarea>
                            </div>
                            <div class="form-group">
                                <label for="transOgImage" data-i18n="translations.og_image">
                                    <?= htmlspecialchars(_st('translations.og_image', 'OG Image'), ENT_QUOTES, 'UTF-8') ?>
                                </label>
                                <input type="text" id="transOgImage" class="form-control"
                                       placeholder="https://..." autocomplete="off">
                            </div>
                        </div>
                        <div class="form-actions">
                            <button type="button" id="btnSaveNewTrans" class="btn btn-primary" data-i18n="form.save">
                                <i class="fas fa-save" aria-hidden="true"></i>
                                <?= htmlspecialchars(_st('form.save', 'Save'), ENT_QUOTES, 'UTF-8') ?>
                            </button>
                            <button type="button" id="btnCancelAddTrans" class="btn btn-secondary" data-i18n="form.cancel">
                                <?= htmlspecialchars(_st('form.cancel', 'Cancel'), ENT_QUOTES, 'UTF-8') ?>
                            </button>
                        </div>
                    </div><!-- /addTransPanel -->

                    <div class="table-responsive" style="margin-top:1rem;">
                        <table class="data-table" id="translationsTable">
                            <thead>
                                <tr>
                                    <th data-i18n="translations.language">
                                        <?= htmlspecialchars(_st('translations.language', 'Language'), ENT_QUOTES, 'UTF-8') ?>
                                    </th>
                                    <th data-i18n="translations.meta_title">
                                        <?= htmlspecialchars(_st('translations.meta_title', 'Meta Title'), ENT_QUOTES, 'UTF-8') ?>
                                    </th>
                                    <th data-i18n="translations.og_title">
                                        <?= htmlspecialchars(_st('translations.og_title', 'OG Title'), ENT_QUOTES, 'UTF-8') ?>
                                    </th>
                                    <th data-i18n="table.actions">
                                        <?= htmlspecialchars(_st('table.actions', 'Actions'), ENT_QUOTES, 'UTF-8') ?>
                                    </th>
                                </tr>
                            </thead>
                            <tbody id="translationsBody"></tbody>
                        </table>
                    </div>
                </div><!-- /tab-sm-translations -->

            </form>
        </div>
    </div><!-- /seoMetaFormCard -->

    <!-- ═══════════════════════════════════════════
         FILTER BAR
    ════════════════════════════════════════════ -->
    <div class="card">
        <div class="card-body">
            <div class="filters-grid">
                <div class="filter-group">
                    <label class="filter-label" for="filterSearch" data-i18n="filter.search">
                        <?= htmlspecialchars(_st('filter.search', 'Search'), ENT_QUOTES, 'UTF-8') ?>
                    </label>
                    <input type="text" id="filterSearch" class="form-control"
                           placeholder="<?= htmlspecialchars(_st('filter.search_placeholder', 'Search by canonical URL...'), ENT_QUOTES, 'UTF-8') ?>"
                           data-i18n-placeholder="filter.search_placeholder">
                </div>
                <div class="filter-group">
                    <label class="filter-label" for="filterEntityType" data-i18n="table.entity_type">
                        <?= htmlspecialchars(_st('table.entity_type', 'Entity Type'), ENT_QUOTES, 'UTF-8') ?>
                    </label>
                    <select id="filterEntityType" class="form-control">
                        <option value=""         data-i18n="filter.all_entity_types">
                            <?= htmlspecialchars(_st('filter.all_entity_types', 'All Types'), ENT_QUOTES, 'UTF-8') ?>
                        </option>
                        <option value="product"  data-i18n="filter.product">
                            <?= htmlspecialchars(_st('filter.product',  'Product'),  ENT_QUOTES, 'UTF-8') ?>
                        </option>
                        <option value="category" data-i18n="filter.category">
                            <?= htmlspecialchars(_st('filter.category', 'Category'), ENT_QUOTES, 'UTF-8') ?>
                        </option>
                        <option value="entity"   data-i18n="filter.entity">
                            <?= htmlspecialchars(_st('filter.entity',   'Entity'),   ENT_QUOTES, 'UTF-8') ?>
                        </option>
                        <option value="page"     data-i18n="filter.page">
                            <?= htmlspecialchars(_st('filter.page',     'Page'),     ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    </select>
                </div>
                <div class="filter-group">
                    <label class="filter-label" aria-hidden="true">&nbsp;</label>
                    <div class="filter-buttons">
                        <button id="btnFilter" class="btn btn-primary" data-i18n="filter.apply">
                            <i class="fas fa-search" aria-hidden="true"></i>
                            <?= htmlspecialchars(_st('filter.apply', 'Filter'), ENT_QUOTES, 'UTF-8') ?>
                        </button>
                        <button id="btnClearFilters" class="btn btn-secondary" data-i18n="filter.clear">
                            <?= htmlspecialchars(_st('filter.clear', 'Clear'), ENT_QUOTES, 'UTF-8') ?>
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

            <div id="smLoading" class="loading-state" style="display:none;">
                <div class="spinner" role="status"></div>
                <p data-i18n="loading">
                    <?= htmlspecialchars(_st('loading', 'Loading...'), ENT_QUOTES, 'UTF-8') ?>
                </p>
            </div>

            <div id="smEmpty" class="empty-state" style="display:none;">
                <div class="empty-icon"><i class="fas fa-search" aria-hidden="true"></i></div>
                <h3 data-i18n="table.no_records">
                    <?= htmlspecialchars(_st('table.no_records', 'No records found'), ENT_QUOTES, 'UTF-8') ?>
                </h3>
                <?php if ($canCreate): ?>
                <button id="btnAddSeoMetaEmpty" class="btn btn-primary" data-i18n="add_new">
                    <?= htmlspecialchars(_st('add_new', 'Add SEO Record'), ENT_QUOTES, 'UTF-8') ?>
                </button>
                <?php endif; ?>
            </div>

            <div id="smError" class="error-state" style="display:none;">
                <div class="error-icon"><i class="fas fa-exclamation-triangle" aria-hidden="true"></i></div>
                <h3 data-i18n="error.title">
                    <?= htmlspecialchars(_st('error.title', 'Something went wrong'), ENT_QUOTES, 'UTF-8') ?>
                </h3>
                <p id="smErrorMessage"></p>
                <button id="btnRetry" class="btn btn-primary" data-i18n="retry">
                    <?= htmlspecialchars(_st('retry', 'Retry'), ENT_QUOTES, 'UTF-8') ?>
                </button>
            </div>

            <div id="smTableContainer" class="table-responsive">
                <table class="data-table" id="seoMetaTable" aria-label="SEO Meta">
                    <thead>
                        <tr>
                            <th data-i18n="table.id">
                                <?= htmlspecialchars(_st('table.id',           'ID'),            ENT_QUOTES, 'UTF-8') ?>
                            </th>
                            <th data-i18n="table.entity_type">
                                <?= htmlspecialchars(_st('table.entity_type',  'Entity Type'),   ENT_QUOTES, 'UTF-8') ?>
                            </th>
                            <th data-i18n="table.entity_id">
                                <?= htmlspecialchars(_st('table.entity_id',    'Entity ID'),     ENT_QUOTES, 'UTF-8') ?>
                            </th>
                            <th data-i18n="table.canonical_url">
                                <?= htmlspecialchars(_st('table.canonical_url','Canonical URL'), ENT_QUOTES, 'UTF-8') ?>
                            </th>
                            <th data-i18n="table.robots">
                                <?= htmlspecialchars(_st('table.robots',       'Robots'),        ENT_QUOTES, 'UTF-8') ?>
                            </th>
                            <th data-i18n="table.created">
                                <?= htmlspecialchars(_st('table.created',      'Created'),       ENT_QUOTES, 'UTF-8') ?>
                            </th>
                            <th data-i18n="table.actions">
                                <?= htmlspecialchars(_st('table.actions',      'Actions'),       ENT_QUOTES, 'UTF-8') ?>
                            </th>
                        </tr>
                    </thead>
                    <tbody id="seoMetaBody"></tbody>
                </table>
            </div>
        </div>

        <div class="pagination-wrapper">
            <div class="pagination-info" id="paginationInfo" aria-live="polite"></div>
            <div class="pagination" id="pagination" role="navigation" aria-label="Pagination"></div>
        </div>
    </div>

</div><!-- /.page-container -->

<script>
window.SEO_META_CONFIG = {
    apiBase:   <?= json_encode($apiBase,    JSON_UNESCAPED_SLASHES) ?>,
    csrfToken: <?= json_encode($csrf) ?>,
    lang:      <?= json_encode($_stSafeLang) ?>,
    dir:       <?= json_encode($dir) ?>,
    tenantId:  <?= json_encode($tenantId) ?>,
    strings:   <?= json_encode($_stStrings, JSON_UNESCAPED_UNICODE) ?>,
    canCreate: <?= json_encode($canCreate) ?>,
    canEdit:   <?= json_encode($canEdit) ?>,
    canDelete: <?= json_encode($canDelete) ?>
};
</script>
<script src="/admin/assets/js/pages/seo_meta.js?v=<?= assetVer('/admin/assets/js/pages/seo_meta.js') ?>"></script>

<?php if (!$isFragment) require_once __DIR__ . '/../includes/footer.php'; ?>