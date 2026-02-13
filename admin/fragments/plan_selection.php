<?php
declare(strict_types=1);

$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
          strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
$isEmbedded = isset($_GET['embedded']) || isset($_POST['embedded']);
$isFragment = $isAjax || $isEmbedded;

if ($isFragment) {
    require_once __DIR__ . '/../includes/admin_context.php';
} else {
    require_once __DIR__ . '/../includes/header.php';
}

$payload = $GLOBALS['ADMIN_UI'] ?? [];
$user = $payload['user'] ?? (function_exists('admin_user') ? admin_user() : []);
$roles = $user['roles'] ?? [];
$lang = $payload['lang'] ?? ($user['preferred_language'] ?? 'en');
$dir = $payload['direction'] ?? (in_array($lang, ['ar','he','fa','ur']) ? 'rtl' : 'ltr');
$csrf = $payload['csrf_token'] ?? (function_exists('admin_csrf') ? admin_csrf() : '');

$tenantId = $payload['tenant_id'] ?? ($_SESSION['tenant_id'] ?? 0);
$userId   = $user['id'] ?? ($_SESSION['user_id'] ?? 0);
$isSuperAdmin = in_array('super_admin', $roles, true);

// Translation helper
$_psLangCode = in_array($lang, ['ar','en']) ? $lang : 'en';
$_psStringsFile = __DIR__ . '/../../languages/PlanSelection/' . $_psLangCode . '.json';
$_psStrings = file_exists($_psStringsFile) ? (json_decode(file_get_contents($_psStringsFile), true) ?: []) : [];
function _ps(string $key, string $fallback = ''): string {
    global $_psStrings;
    $parts = explode('.', $key);
    $val = $_psStrings;
    foreach ($parts as $p) {
        if (!is_array($val) || !isset($val[$p])) return $fallback ?: $key;
        $val = $val[$p];
    }
    return is_string($val) ? $val : ($fallback ?: $key);
}
?>

<div id="planSelectionPage" class="plan-selection-page" dir="<?= $dir ?>">
    <div class="page-header">
        <h2><?= _ps('title', 'Subscription Plans') ?></h2>
        <p class="page-subtitle"><?= _ps('subtitle', 'Choose a plan that fits your needs') ?></p>
    </div>

    <!-- Current Plan Info -->
    <div id="currentPlanInfo" class="current-plan-info" style="display:none;">
        <div class="current-plan-card">
            <h4><?= _ps('current_plan', 'Your Current Plan') ?></h4>
            <div id="currentPlanDetails"></div>
        </div>
    </div>

    <!-- Plan Cards Grid -->
    <div id="planCardsGrid" class="plan-cards-grid">
        <div class="loading-spinner"><?= _ps('loading', 'Loading plans...') ?></div>
    </div>
</div>

<link rel="stylesheet" href="assets/css/pages/plan_selection.css?v=<?= time() ?>">
<script>
window.PLAN_SELECTION_CONFIG = {
    apiBase: '/api',
    csrfToken: <?= json_encode($csrf) ?>,
    tenantId: <?= (int)$tenantId ?>,
    userId: <?= (int)$userId ?>,
    isSuperAdmin: <?= $isSuperAdmin ? 'true' : 'false' ?>,
    lang: <?= json_encode($lang) ?>,
    strings: <?= json_encode($_psStrings) ?>
};
</script>
<script src="assets/js/pages/plan_selection.js?v=<?= time() ?>"></script>
