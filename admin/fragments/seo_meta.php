<?php
declare(strict_types=1);

/**
 * /admin/fragments/seo_meta.php
 * SEO Meta Management - Production Ready
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
$canManageSeoMeta = can('manage_settings') || is_super_admin();
$canCreate        = $canManageSeoMeta;
$canEdit          = $canManageSeoMeta;
$canDelete        = $canManageSeoMeta;

// Access check
if (!$canManageSeoMeta) {
    http_response_code(403);
    die('Access denied');
}

// API base
$apiBase = '/api';

// ════════════════════════════════════════════════════════════
// TRANSLATION HELPERS
// ════════════════════════════════════════════════════════════
$_stStrings = [];
$_stAllowedLangs = ['ar', 'en', 'fr', 'tr', 'ur', 'de', 'es', 'fa', 'he', 'hi', 'zh', 'ja', 'ko', 'pt', 'ru', 'it', 'nl', 'sv', 'pl', 'th', 'vi', 'id', 'ms', 'bn', 'sw', 'tl'];
$_stSafeLang = in_array($lang, $_stAllowedLangs, true) ? $lang : 'en';
$_stLangFile = __DIR__ . '/../../languages/SeoMeta/' . $_stSafeLang . '.json';
if (file_exists($_stLangFile)) {
    $_stJson = json_decode(file_get_contents($_stLangFile), true);
    if (isset($_stJson['strings'])) {
        $_stStrings = $_stJson['strings'];
    }
}

if (!function_exists('_st')) {
    function _st($key, $fallback = '') {
        global $_stStrings;
        $keys = explode('.', $key);
        $val = $_stStrings;
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

<link rel="stylesheet" href="/admin/assets/css/pages/seo_meta.css?v=<?= time() ?>">
<meta data-page="seo_meta"
      data-i18n-files="/languages/SeoMeta/<?= rawurlencode($lang) ?>.json">

<div class="page-container" id="seoMetaPageContainer" dir="<?= htmlspecialchars($dir) ?>">

    <!-- Page Header -->
    <div class="page-header">
        <h1 data-i18n="title"><?= htmlspecialchars(_st('title', 'SEO Meta Management'), ENT_QUOTES, 'UTF-8') ?></h1>
        <p data-i18n="subtitle"><?= htmlspecialchars(_st('subtitle', 'Manage SEO metadata for products, categories, entities and pages'), ENT_QUOTES, 'UTF-8') ?></p>
        <div class="page-header-actions">
            <?php if ($canCreate): ?>
                <button id="btnAddSeoMeta" class="btn btn-primary" data-i18n="add_new"><?= htmlspecialchars(_st('add_new', 'Add SEO Record'), ENT_QUOTES, 'UTF-8') ?></button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Filter Bar -->
    <div class="card">
        <div class="card-body filter-bar">
            <input type="text" id="filterSearch" class="form-control" placeholder="<?= htmlspecialchars(_st('filter.search_placeholder', 'Search by canonical URL...'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-placeholder="filter.search_placeholder">
            <select id="filterEntityType" class="form-control" data-i18n="filter.entity_type">
                <option value="" data-i18n="filter.all_entity_types"><?= htmlspecialchars(_st('filter.all_entity_types', 'All Entity Types'), ENT_QUOTES, 'UTF-8') ?></option>
                <option value="product" data-i18n="filter.product"><?= htmlspecialchars(_st('filter.product', 'Product'), ENT_QUOTES, 'UTF-8') ?></option>
                <option value="category" data-i18n="filter.category"><?= htmlspecialchars(_st('filter.category', 'Category'), ENT_QUOTES, 'UTF-8') ?></option>
                <option value="entity" data-i18n="filter.entity"><?= htmlspecialchars(_st('filter.entity', 'Entity'), ENT_QUOTES, 'UTF-8') ?></option>
                <option value="page" data-i18n="filter.page"><?= htmlspecialchars(_st('filter.page', 'Page'), ENT_QUOTES, 'UTF-8') ?></option>
            </select>
            <button id="btnFilter" class="btn btn-primary" data-i18n="filter.apply"><?= htmlspecialchars(_st('filter.apply', 'Filter'), ENT_QUOTES, 'UTF-8') ?></button>
            <button id="btnClearFilters" class="btn btn-secondary" data-i18n="filter.clear"><?= htmlspecialchars(_st('filter.clear', 'Clear Filters'), ENT_QUOTES, 'UTF-8') ?></button>
        </div>
    </div>

    <!-- Data Table -->
    <div class="card">
        <div class="card-body">
            <table class="data-table" id="seoMetaTable">
                <thead>
                    <tr>
                        <th data-i18n="table.id"><?= htmlspecialchars(_st('table.id', 'ID'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th data-i18n="table.entity_type"><?= htmlspecialchars(_st('table.entity_type', 'Entity Type'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th data-i18n="table.entity_id"><?= htmlspecialchars(_st('table.entity_id', 'Entity ID'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th data-i18n="table.canonical_url"><?= htmlspecialchars(_st('table.canonical_url', 'Canonical URL'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th data-i18n="table.robots"><?= htmlspecialchars(_st('table.robots', 'Robots'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th data-i18n="table.created"><?= htmlspecialchars(_st('table.created', 'Created'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th data-i18n="table.actions"><?= htmlspecialchars(_st('table.actions', 'Actions'), ENT_QUOTES, 'UTF-8') ?></th>
                    </tr>
                </thead>
                <tbody id="seoMetaBody">
                    <tr id="emptyStateRow">
                        <td colspan="7" class="text-center" data-i18n="table.no_records"><?= htmlspecialchars(_st('table.no_records', 'No records found'), ENT_QUOTES, 'UTF-8') ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <!-- Pagination -->
        <div class="pagination-wrapper">
            <div class="pagination-info">
                <span data-i18n="pagination.showing"><?= htmlspecialchars(_st('pagination.showing', 'Showing'), ENT_QUOTES, 'UTF-8') ?></span>
                <span id="paginationInfo">0-0 <?= htmlspecialchars(_st('pagination.of', 'of'), ENT_QUOTES, 'UTF-8') ?> 0</span>
            </div>
            <div class="pagination" id="pagination"></div>
        </div>
    </div>

    <!-- Add/Edit SEO Meta Modal -->
    <div id="seoMetaModal" class="modal" style="display:none;">
        <div class="modal-content">
            <h3 id="seoMetaModalTitle" data-i18n="modal.add_title"><?= htmlspecialchars(_st('modal.add_title', 'Add SEO Record'), ENT_QUOTES, 'UTF-8') ?></h3>
            <form id="seoMetaForm">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                <input type="hidden" name="id" id="seoMetaId" value="">
                <div class="form-group">
                    <label data-i18n="form.entity_type"><?= htmlspecialchars(_st('form.entity_type', 'Entity Type'), ENT_QUOTES, 'UTF-8') ?> *</label>
                    <select name="entity_type" id="smEntityType" class="form-control" required>
                        <option value="product" data-i18n="entity_type.product"><?= htmlspecialchars(_st('entity_type.product', 'Product'), ENT_QUOTES, 'UTF-8') ?></option>
                        <option value="category" data-i18n="entity_type.category"><?= htmlspecialchars(_st('entity_type.category', 'Category'), ENT_QUOTES, 'UTF-8') ?></option>
                        <option value="entity" data-i18n="entity_type.entity"><?= htmlspecialchars(_st('entity_type.entity', 'Entity'), ENT_QUOTES, 'UTF-8') ?></option>
                        <option value="page" data-i18n="entity_type.page"><?= htmlspecialchars(_st('entity_type.page', 'Page'), ENT_QUOTES, 'UTF-8') ?></option>
                    </select>
                </div>
                <div class="form-group">
                    <label data-i18n="form.entity_id"><?= htmlspecialchars(_st('form.entity_id', 'Entity ID'), ENT_QUOTES, 'UTF-8') ?> *</label>
                    <input type="number" name="entity_id" id="smEntityId" class="form-control" required>
                </div>
                <div class="form-group">
                    <label data-i18n="form.canonical_url"><?= htmlspecialchars(_st('form.canonical_url', 'Canonical URL'), ENT_QUOTES, 'UTF-8') ?></label>
                    <input type="text" name="canonical_url" id="smCanonicalUrl" class="form-control">
                </div>
                <div class="form-group">
                    <label data-i18n="form.robots"><?= htmlspecialchars(_st('form.robots', 'Robots'), ENT_QUOTES, 'UTF-8') ?></label>
                    <select name="robots" id="smRobots" class="form-control">
                        <option value="index,follow">index,follow</option>
                        <option value="noindex,nofollow">noindex,nofollow</option>
                        <option value="index,nofollow">index,nofollow</option>
                        <option value="noindex,follow">noindex,follow</option>
                    </select>
                </div>
                <div class="form-group">
                    <label data-i18n="form.schema_markup"><?= htmlspecialchars(_st('form.schema_markup', 'Schema Markup (JSON)'), ENT_QUOTES, 'UTF-8') ?></label>
                    <textarea name="schema_markup" id="smSchemaMarkup" class="form-control" rows="4"></textarea>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary" data-i18n="form.save"><?= htmlspecialchars(_st('form.save', 'Save'), ENT_QUOTES, 'UTF-8') ?></button>
                    <button type="button" class="btn btn-secondary btn-close-modal" data-modal="seoMetaModal" data-i18n="form.cancel"><?= htmlspecialchars(_st('form.cancel', 'Cancel'), ENT_QUOTES, 'UTF-8') ?></button>
                </div>
            </form>
        </div>
    </div>

    <!-- Translations Modal -->
    <div id="translationsModal" class="modal" style="display:none;">
        <div class="modal-content">
            <h3 id="translationsModalTitle" data-i18n="translations.title"><?= htmlspecialchars(_st('translations.title', 'SEO Translations'), ENT_QUOTES, 'UTF-8') ?></h3>
            <input type="hidden" id="transSeoMetaId" value="">
            <div class="form-group">
                <label data-i18n="translations.language"><?= htmlspecialchars(_st('translations.language', 'Language'), ENT_QUOTES, 'UTF-8') ?></label>
                <select id="transLangCode" class="form-control">
                    <!-- Languages loaded dynamically from /api/languages -->
                </select>
            </div>
            <div class="form-group">
                <label data-i18n="translations.meta_title"><?= htmlspecialchars(_st('translations.meta_title', 'Meta Title'), ENT_QUOTES, 'UTF-8') ?></label>
                <input type="text" id="transMetaTitle" class="form-control">
            </div>
            <div class="form-group">
                <label data-i18n="translations.meta_description"><?= htmlspecialchars(_st('translations.meta_description', 'Meta Description'), ENT_QUOTES, 'UTF-8') ?></label>
                <textarea id="transMetaDescription" class="form-control" rows="3"></textarea>
            </div>
            <div class="form-group">
                <label data-i18n="translations.meta_keywords"><?= htmlspecialchars(_st('translations.meta_keywords', 'Meta Keywords'), ENT_QUOTES, 'UTF-8') ?></label>
                <input type="text" id="transMetaKeywords" class="form-control">
            </div>
            <div class="form-group">
                <label data-i18n="translations.og_title"><?= htmlspecialchars(_st('translations.og_title', 'OG Title'), ENT_QUOTES, 'UTF-8') ?></label>
                <input type="text" id="transOgTitle" class="form-control">
            </div>
            <div class="form-group">
                <label data-i18n="translations.og_description"><?= htmlspecialchars(_st('translations.og_description', 'OG Description'), ENT_QUOTES, 'UTF-8') ?></label>
                <textarea id="transOgDescription" class="form-control" rows="3"></textarea>
            </div>
            <div class="form-group">
                <label data-i18n="translations.og_image"><?= htmlspecialchars(_st('translations.og_image', 'OG Image'), ENT_QUOTES, 'UTF-8') ?></label>
                <input type="text" id="transOgImage" class="form-control">
            </div>
            <div class="form-actions">
                <button id="btnAddTranslation" class="btn btn-primary" data-i18n="translations.add"><?= htmlspecialchars(_st('translations.add', 'Add Translation'), ENT_QUOTES, 'UTF-8') ?></button>
                <button type="button" class="btn btn-secondary btn-close-modal" data-modal="translationsModal" data-i18n="form.cancel"><?= htmlspecialchars(_st('form.cancel', 'Cancel'), ENT_QUOTES, 'UTF-8') ?></button>
            </div>
            <table class="data-table" id="translationsTable">
                <thead>
                    <tr>
                        <th data-i18n="translations.language"><?= htmlspecialchars(_st('translations.language', 'Language'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th data-i18n="translations.meta_title"><?= htmlspecialchars(_st('translations.meta_title', 'Meta Title'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th data-i18n="translations.og_title"><?= htmlspecialchars(_st('translations.og_title', 'OG Title'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th data-i18n="table.actions"><?= htmlspecialchars(_st('table.actions', 'Actions'), ENT_QUOTES, 'UTF-8') ?></th>
                    </tr>
                </thead>
                <tbody id="translationsBody"></tbody>
            </table>
        </div>
    </div>

</div>

<script>
window.SEO_META_CONFIG = {
    apiBase:   <?= json_encode($apiBase) ?>,
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
<script src="/admin/assets/js/pages/seo_meta.js?v=<?= time() ?>"></script>

<?php if (!$isFragment) require_once __DIR__ . '/../includes/footer.php'; ?>