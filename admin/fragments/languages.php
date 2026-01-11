<?php
/**
 * admin/fragments/languages.php
 * Languages management UI
 */

declare(strict_types=1);

/* ================= Bootstrap Admin UI ================= */
$bootstrap = __DIR__ . '/../../api/bootstrap_admin_ui.php';
if (is_readable($bootstrap)) {
    try { require_once $bootstrap; } catch (Throwable $e) {}
}

/* ================= Payload ================= */
$ADMIN_UI_PAYLOAD = $ADMIN_UI_PAYLOAD ?? ($GLOBALS['ADMIN_UI'] ?? []);

$user      = $ADMIN_UI_PAYLOAD['user'] ?? [];
$lang      = $ADMIN_UI_PAYLOAD['lang'] ?? 'en';
$direction = $ADMIN_UI_PAYLOAD['direction'] ?? 'ltr';
$strings   = $ADMIN_UI_PAYLOAD['strings'] ?? [];
$theme     = $ADMIN_UI_PAYLOAD['theme'] ?? [];

/* ================= I18N Helpers ================= */
function flatten_strings(array $src): array {
    $out = [];
    $stack = [['p'=>'','n'=>$src]];
    while ($stack) {
        ['p'=>$p,'n'=>$n] = array_pop($stack);
        if (!is_array($n)) continue;
        foreach ($n as $k => $v) {
            $key = $p === '' ? $k : "$p.$k";
            if (is_array($v)) $stack[] = ['p'=>$key,'n'=>$v];
            else {
                $out[$key] = (string)$v;
                $short = basename(str_replace('.', '/', $key));
                if (!isset($out[$short])) $out[$short] = (string)$v;
            }
        }
    }
    return $out;
}
$flat = flatten_strings($strings);

function t(string $k, array $flat, string $d = ''): string {
    if (!empty($flat[$k])) return $flat[$k];
    $s = basename(str_replace('.', '/', $k));
    return $flat[$s] ?? ($d ?: $s);
}

/* ================= Permissions ================= */
$canManage = false;
if (!empty($user['role_id']) && (int)$user['role_id'] === 1) $canManage = true;
if (!$canManage && !empty($user['roles']) && is_array($user['roles'])) {
    if (in_array('super_admin', $user['roles'], true)) $canManage = true;
}
if (!$canManage && !empty($user['permissions']) && is_array($user['permissions'])) {
    if (in_array('manage_settings', $user['permissions'], true)) $canManage = true;
}

/* ================= CSRF ================= */
if (empty($_SESSION['csrf_token'])) {
    try { $_SESSION['csrf_token'] = bin2hex(random_bytes(16)); }
    catch (Throwable $e) { $_SESSION['csrf_token'] = bin2hex(openssl_random_pseudo_bytes(16)); }
}
$csrf = htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES);

/* ================= API Path ================= */
$apiPath = $ADMIN_UI_PAYLOAD['api']['languages'] ?? '/api/routes/languages.php';

?><!doctype html>
<html lang="<?= htmlspecialchars($lang) ?>" dir="<?= htmlspecialchars($direction) ?>">
<head>
    <meta charset="utf-8">
    <title><?= htmlspecialchars(t('languages.title', $flat, 'Languages')) ?></title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <link rel="stylesheet" href="/admin/assets/css/pages/languages.css">
</head>
<body>

<div id="adminLanguages" style="max-width:1200px;margin:16px auto;padding:12px;">

    <h2><?= htmlspecialchars(t('languages.title', $flat, 'Languages')) ?></h2>

    <?php if (!$canManage): ?>
        <div class="alert alert-warning">
            <?= htmlspecialchars(t('languages.no_permission', $flat, 'You do not have permission')) ?>
        </div>
    <?php endif; ?>

    <div class="toolbar">
        <button id="langRefresh" class="btn primary">
            <?= htmlspecialchars(t('refresh', $flat, 'Refresh')) ?>
        </button>
        <?php if ($canManage): ?>
            <button id="langNew" class="btn primary">
                <?= htmlspecialchars(t('add_language', $flat, 'Add Language')) ?>
            </button>
        <?php endif; ?>
    </div>

    <div id="langStatus" class="status">
        <?= htmlspecialchars(t('loading', $flat, 'Loading...')) ?>
    </div>

    <div class="table-wrap">
        <table id="languagesTable">
            <thead>
                <tr>
                    <th><?= htmlspecialchars(t('code', $flat, 'Code')) ?></th>
                    <th><?= htmlspecialchars(t('name', $flat, 'Name')) ?></th>
                    <th><?= htmlspecialchars(t('direction', $flat, 'Direction')) ?></th>
                    <th style="text-align:right"><?= htmlspecialchars(t('actions', $flat, 'Actions')) ?></th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>

    <?php if ($canManage): ?>
    <div id="languageFormWrap" style="display:none;margin-top:16px;">
        <h3 id="languageFormTitle"></h3>
        <form id="languageForm">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <input type="hidden" name="code_old" id="lang_code_old">

            <div class="form-row">
                <label><?= htmlspecialchars(t('code', $flat, 'Code')) ?></label>
                <input name="code" id="lang_code" maxlength="8" required>
            </div>

            <div class="form-row">
                <label><?= htmlspecialchars(t('name', $flat, 'Name')) ?></label>
                <input name="name" id="lang_name" required>
            </div>

            <div class="form-row">
                <label><?= htmlspecialchars(t('direction', $flat, 'Direction')) ?></label>
                <select name="direction" id="lang_direction">
                    <option value="ltr">LTR</option>
                    <option value="rtl">RTL</option>
                </select>
            </div>

            <div class="form-actions">
                <button type="button" id="langCancel" class="btn">
                    <?= htmlspecialchars(t('cancel', $flat, 'Cancel')) ?>
                </button>
                <button type="submit" class="btn primary">
                    <?= htmlspecialchars(t('save', $flat, 'Save')) ?>
                </button>
            </div>
        </form>
    </div>
    <?php endif; ?>

</div>

<script>
window.ADMIN_UI      = <?= json_encode($ADMIN_UI_PAYLOAD, JSON_UNESCAPED_UNICODE) ?> || {};
window.I18N_FLAT     = <?= json_encode($flat, JSON_UNESCAPED_UNICODE) ?> || {};
window.USER_INFO     = ADMIN_UI.user || {};
window.THEME         = ADMIN_UI.theme || {};
window.LANG          = '<?= $lang ?>';
window.DIRECTION     = '<?= $direction ?>';
window.CSRF_TOKEN    = '<?= $csrf ?>';
window.API_LANGUAGES = '<?= addslashes($apiPath) ?>';

(function(){
  try{
    document.documentElement.lang = LANG;
    document.documentElement.dir  = DIRECTION;
  }catch(e){}
})();
</script>

<script src="/admin/assets/js/pages/languages.js" defer></script>
</body>
</html>
