<?php
/**
 * admin/fragments/languages.php
 * Fully dynamic languages UI, supports external/internal access
 */
declare(strict_types=1);

// Detect if accessed externally (outside admin)
$isExternal = isset($_GET['external']) || (isset($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], '/admin/') === false);

/* =======================
   Bootstrap Admin UI (skip if external)
======================= */
$ADMIN_UI_PAYLOAD = [];
if (!$isExternal) {
    $bootstrap = __DIR__ . '/../../api/bootstrap_admin_ui.php';
    if (is_readable($bootstrap)) {
        try { require_once $bootstrap; } catch (Throwable $e) {}
    }
    $ADMIN_UI_PAYLOAD = $GLOBALS['ADMIN_UI'] ?? [];
}

$user       = $ADMIN_UI_PAYLOAD['user'] ?? [];
$lang       = $ADMIN_UI_PAYLOAD['lang'] ?? 'en';
$direction  = $ADMIN_UI_PAYLOAD['direction'] ?? 'ltr';
$strings    = $ADMIN_UI_PAYLOAD['strings'] ?? [];
$theme      = $ADMIN_UI_PAYLOAD['theme'] ?? [];

/* =======================
   Helpers
======================= */
if (!function_exists('flatten_strings')) {
    function flatten_strings(array $src): array {
        $out = [];
        $stack = [['p'=>'','n'=>$src]];
        while ($stack) {
            $it = array_pop($stack);
            foreach ((array)$it['n'] as $k=>$v) {
                $key = $it['p'] === '' ? $k : $it['p'].'.'.$k;
                if (is_array($v)) $stack[] = ['p'=>$key,'n'=>$v];
                else {
                    $out[$key] = (string)$v;
                    $short = basename(str_replace('.','/',$key));
                    $out[$short] ??= (string)$v;
                }
            }
        }
        return $out;
    }
}
$flat = flatten_strings($strings);

if (!function_exists('gs')) {
    function gs(string $k, array $flat, string $d=''): string {
        if (!empty($flat[$k])) return $flat[$k];
        $s = basename(str_replace('.','/',$k));
        return $flat[$s] ?? ($d !== '' ? $d : $s);
    }
}

/* =======================
   Permissions Check (skip if external)
======================= */
$canManage = false;
if (!$isExternal && !empty($user['role_id']) && (int)$user['role_id'] === 1) $canManage = true;
if (!$isExternal && !$canManage && !empty($user['roles'])) {
    if (in_array('super_admin',$user['roles'],true) || in_array('manage_settings',$user['roles'],true)) $canManage = true;
}

$csrf = htmlspecialchars((string)($_SESSION['csrf_token'] ?? ''), ENT_QUOTES);
$apiPath = $ADMIN_UI_PAYLOAD['api']['languages'] ?? '/api/routes/languages.php';
?>

<!doctype html>
<html lang="<?= htmlspecialchars($lang,ENT_QUOTES) ?>" dir="<?= htmlspecialchars($direction,ENT_QUOTES) ?>">
<head>
    <meta charset="utf-8">
    <title><?= htmlspecialchars(gs('languages.page_title',$flat,'Languages'),ENT_QUOTES) ?></title>
    <link rel="stylesheet" href="/admin/assets/css/admin-theme.css">
    <link rel="stylesheet" href="/admin/assets/css/pages/languages.css">
    
    <!-- Dynamic theme from tables + original theme backgrounds -->
    <style>
        :root {
            --page-header-margin: <?= htmlspecialchars($theme['page_header_margin'] ?? '20px', ENT_QUOTES) ?>;
            --page-header-font-size: <?= htmlspecialchars($theme['page_header_font_size'] ?? '22px', ENT_QUOTES) ?>;
            --btn-padding: <?= htmlspecialchars($theme['btn_padding'] ?? '8px 14px', ENT_QUOTES) ?>;
            --btn-border-radius: <?= htmlspecialchars($theme['btn_border_radius'] ?? '6px', ENT_QUOTES) ?>;
            --primary-color: <?= htmlspecialchars($theme['primary_color'] ?? '#007bff', ENT_QUOTES) ?>;
            --primary-text: <?= htmlspecialchars($theme['primary_text'] ?? '#fff', ENT_QUOTES) ?>;
            --wrapper-bg: <?= htmlspecialchars($theme['wrapper_bg'] ?? 'linear-gradient(135deg, #f5f6fa 0%, #ffffff 100%)', ENT_QUOTES) ?>; /* Original theme background */
            --wrapper-border-radius: <?= htmlspecialchars($theme['wrapper_border_radius'] ?? '10px', ENT_QUOTES) ?>;
            --wrapper-padding: <?= htmlspecialchars($theme['wrapper_padding'] ?? '15px', ENT_QUOTES) ?>;
            --wrapper-shadow: <?= htmlspecialchars($theme['wrapper_shadow'] ?? '0 4px 12px rgba(0,0,0,0.06)', ENT_QUOTES) ?>;
            --table-header-bg: <?= htmlspecialchars($theme['table_header_bg'] ?? '#f5f6fa', ENT_QUOTES) ?>;
            --table-border: <?= htmlspecialchars($theme['table_border'] ?? '1px solid #eee', ENT_QUOTES) ?>;
            --table-cell-padding: <?= htmlspecialchars($theme['table_cell_padding'] ?? '12px', ENT_QUOTES) ?>;
            --status-active-bg: <?= htmlspecialchars($theme['status_active_bg'] ?? '#e6f7ee', ENT_QUOTES) ?>;
            --status-active-color: <?= htmlspecialchars($theme['status_active_color'] ?? '#0a7b3e', ENT_QUOTES) ?>;
            --status-inactive-bg: <?= htmlspecialchars($theme['status_inactive_bg'] ?? '#fdecea', ENT_QUOTES) ?>;
            --status-inactive-color: <?= htmlspecialchars($theme['status_inactive_color'] ?? '#b71c1c', ENT_QUOTES) ?>;
            --actions-font-size: <?= htmlspecialchars($theme['actions_font_size'] ?? '18px', ENT_QUOTES) ?>;
            --loading-font-size: <?= htmlspecialchars($theme['loading_font_size'] ?? '14px', ENT_QUOTES) ?>;
            --loading-padding: <?= htmlspecialchars($theme['loading_padding'] ?? '20px', ENT_QUOTES) ?>;
            --status-padding: <?= htmlspecialchars($theme['status_padding'] ?? '4px 10px', ENT_QUOTES) ?>;
            --status-border-radius: <?= htmlspecialchars($theme['status_border_radius'] ?? '20px', ENT_QUOTES) ?>;
            --status-font-size: <?= htmlspecialchars($theme['status_font_size'] ?? '12px', ENT_QUOTES) ?>;
            --toast-bg: rgba(0, 0, 0, 0.8); /* Non-table format */
            --toast-color: #fff; /* Non-table format */
            --toast-padding: 10px 15px; /* Non-table format */
            --toast-border-radius: 5px; /* Non-table format */
            --pagination-input-width: 60px; /* Non-table format */
        }
    </style>
</head>
<body>

<div id="adminLanguages" class="admin-page">
    <div class="page-header">
        <h2><?= htmlspecialchars(gs('languages.page_title',$flat,'Languages'),ENT_QUOTES) ?></h2>
        <?php if (!$isExternal && $canManage): ?>
            <button id="langNew" class="btn primary">
                <?= htmlspecialchars(gs('languages.buttons.new',$flat,'New'),ENT_QUOTES) ?>
            </button>
        <?php endif; ?>
    </div>

    <div class="notification-area" id="langNotification"></div>
    
    <!-- In-page toast notifications -->
    <div id="toastContainer" style="position:fixed;top:20px;right:20px;z-index:1000;"></div>

    <div class="filters-section">
        <div class="search-box">
            <input id="langSearch" type="search" 
                   placeholder="<?= htmlspecialchars(gs('languages.search_placeholder',$flat,'Search languages...'),ENT_QUOTES) ?>">
            <button type="button" id="langClearSearch" class="btn icon">×</button>
        </div>
        
        <div class="filters-grid">
            <div class="filter-control">
                <label><?= htmlspecialchars(gs('languages.table.direction',$flat,'Direction'),ENT_QUOTES) ?></label>
                <select id="langDirectionFilter" class="filter-select">
                    <option value=""><?= htmlspecialchars(gs('languages.filters.all_directions',$flat,'All Directions'),ENT_QUOTES) ?></option>
                    <option value="ltr">LTR</option>
                    <option value="rtl">RTL</option>
                </select>
            </div>
        </div>
        
        <div class="filters-actions">
            <div class="pagination-info" id="langTotalInfo">
                <?= htmlspecialchars(gs('languages.messages.loading',$flat,'Loading...'),ENT_QUOTES) ?>
            </div>
            
            <div class="filters-right">
                <button type="button" id="langResetFilters" class="btn outline">
                    <?= htmlspecialchars(gs('languages.buttons.clear_filters',$flat,'Clear Filters'),ENT_QUOTES) ?>
                </button>
                <button type="button" id="langRefresh" class="btn primary">
                    <?= htmlspecialchars(gs('languages.filters.refresh',$flat,'Refresh'),ENT_QUOTES) ?>
                </button>
            </div>
        </div>
    </div>

    <div class="table-section">
        <div class="table-container">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th width="60"><?= htmlspecialchars(gs('languages.table.id',$flat,'ID'),ENT_QUOTES) ?></th>
                        <th><?= htmlspecialchars(gs('languages.table.code',$flat,'Code'),ENT_QUOTES) ?></th>
                        <th><?= htmlspecialchars(gs('languages.table.name',$flat,'Name'),ENT_QUOTES) ?></th>
                        <th width="120"><?= htmlspecialchars(gs('languages.table.direction',$flat,'Direction'),ENT_QUOTES) ?></th>
                        <th width="140"><?= htmlspecialchars(gs('languages.table.actions',$flat,'Actions'),ENT_QUOTES) ?></th>
                    </tr>
                </thead>
                <tbody id="langTbody">
                    <tr>
                        <td colspan="5" class="loading-row">
                            <div class="loading-spinner"></div>
                            <div><?= htmlspecialchars(gs('languages.messages.loading',$flat,'Loading...'),ENT_QUOTES) ?></div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <!-- Enhanced pagination below table -->
        <div class="pagination-section">
            <div class="pagination-info" id="langPageInfo">
                <?= htmlspecialchars(gs('languages.messages.loading',$flat,'Loading...'),ENT_QUOTES) ?>
            </div>
            
            <div class="pagination-controls" id="langPager">
                <button id="langPrev" class="btn outline" disabled>Previous</button>
                <input id="langPageInput" type="number" min="1" style="width:var(--pagination-input-width);margin:0 10px;text-align:center;" placeholder="Page">
                <span id="langTotalPages">of 1</span>
                <button id="langNext" class="btn outline" disabled>Next</button>
            </div>
        </div>
    </div>

    <?php if (!$isExternal && $canManage): ?>
    <div id="langFormWrap" class="form-section" style="display:none;">
        <h3 id="langFormTitle"></h3>
        <form id="langForm" autocomplete="off">
            <input type="hidden" name="id" id="langId">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">

            <div class="form-grid">
                <div class="form-group">
                    <label><?= htmlspecialchars(gs('languages.form.code_label',$flat,'Code'),ENT_QUOTES) ?></label>
                    <input name="code" class="form-input" maxlength="8" required>
                </div>
                
                <div class="form-group">
                    <label><?= htmlspecialchars(gs('languages.form.name_label',$flat,'Name'),ENT_QUOTES) ?></label>
                    <input name="name" class="form-input" required>
                </div>
                
                <div class="form-group">
                    <label><?= htmlspecialchars(gs('languages.form.direction_label',$flat,'Direction'),ENT_QUOTES) ?></label>
                    <select name="direction" class="form-select">
                        <option value="ltr">LTR</option>
                        <option value="rtl">RTL</option>
                    </select>
                </div>
            </div>

            <div class="form-actions">
                <button type="button" id="langCancel" class="btn outline">
                    <?= htmlspecialchars(gs('languages.buttons.cancel',$flat,'Cancel'),ENT_QUOTES) ?>
                </button>
                <button type="submit" class="btn primary">
                    <?= htmlspecialchars(gs('languages.buttons.save',$flat,'Save'),ENT_QUOTES) ?>
                </button>
            </div>
        </form>
    </div>
    <?php endif; ?>
</div>

<script>
window.ADMIN_UI   = <?= json_encode($ADMIN_UI_PAYLOAD, JSON_UNESCAPED_UNICODE) ?>;
window.I18N_FLAT  = <?= json_encode($flat, JSON_UNESCAPED_UNICODE) ?>;
window.USER_INFO  = window.ADMIN_UI.user || {};
window.THEME      = window.ADMIN_UI.theme || {};
window.LANG       = "<?= htmlspecialchars($lang, ENT_QUOTES) ?>";
window.DIRECTION  = "<?= htmlspecialchars($direction, ENT_QUOTES) ?>";
window.CSRF_TOKEN = "<?= $csrf ?>";
window.IS_EXTERNAL = <?= $isExternal ? 'true' : 'false' ?>;

window.LANGUAGES_CONFIG = {
    apiUrl: "<?= addslashes($apiPath) ?>",
    csrfToken: window.CSRF_TOKEN,
    itemsPerPage: 25
};
</script>
<script src="/admin/assets/js/pages/languages.js" defer></script>
</body>
</html>