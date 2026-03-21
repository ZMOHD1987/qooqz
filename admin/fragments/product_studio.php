<?php
// admin/fragments/product_studio.php
// Simple image studio fragment: upload, crop (client-side), compress, manage images for a product.
// Requires admin/assets/js/pages/product_studio.js and upload endpoint /api/upload_image.php

$isAjax     = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
$isEmbedded = isset($_GET['embedded']) || isset($_POST['embedded']);
$isFragment = $isAjax || $isEmbedded;

if ($isFragment) {
    require_once __DIR__ . '/../includes/admin_context.php';
} else {
    require_once __DIR__ . '/../includes/header.php';
}

if (!is_admin_logged_in()) {
    if ($isFragment) { http_response_code(401); echo json_encode(['error' => 'Not authenticated']); exit; }
    else { header('Location: /admin/login.php'); exit; }
}

$lang = admin_lang();
$dir  = in_array($lang, ['ar','he','fa','ur']) ? 'rtl' : 'ltr';
$csrf = admin_csrf();

$langBase = __DIR__ . '/../../languages/admin';
$I18N = [];
if (is_readable($langBase . '/' . $lang . '.json')) $I18N = json_decode(file_get_contents($langBase . '/' . $lang . '.json'), true);
function t_local($k, $def='') { global $I18N; $flat = []; $f = function($a,&$out,$p=''){ foreach($a as $k=>$v){ $key = $p===''?$k:($p.'.'.$k); if(is_array($v)) $f($v,$out,$key); else {$out[$key]=$v; $parts=explode('.',$key); $s=end($parts); if(!isset($out[$s])) $out[$s]=$v;} } }; $f($I18N,$flat); return $flat[$k] ?? $def ?? $k; }

?>
<link rel="stylesheet" href="/admin/assets/css/pages/banners.css">
<div id="productStudio" dir="<?= $dir ?>" style="max-width:1000px;margin:12px auto;">
  <h3><?php echo htmlspecialchars(t_local('studio.title','Image Studio')); ?></h3>
  <p><?php echo htmlspecialchars(t_local('studio.desc','Upload, crop and manage product images')); ?></p>

  <div style="display:flex;gap:12px;flex-wrap:wrap;">
    <div style="flex:1 1 320px;">
      <label class="muted">Select images (multiple)</label>
      <input id="studioFiles" type="file" accept="image/*" multiple>
      <div id="studioThumbnails" style="margin-top:12px;display:flex;gap:8px;flex-wrap:wrap;"></div>
    </div>

    <div style="flex:1 1 320px;">
      <label class="muted">Crop / Preview</label>
      <div id="studioCanvasWrap" style="border:1px solid var(--border-color);padding:8px;border-radius:8px;background:var(--surface-color);">
        <canvas id="studioCanvas" style="max-width:100%;border-radius:6px;"></canvas>
      </div>
      <div style="margin-top:8px;">
        <button id="studioUploadBtn" class="btn primary">Upload</button>
        <button id="studioClearBtn" class="btn">Clear</button>
      </div>
    </div>
  </div>

  <div style="margin-top:16px;">
    <strong>Uploaded images</strong>
    <div id="studioUploaded" style="display:flex;gap:8px;flex-wrap:wrap;margin-top:8px;"></div>
  </div>
</div>

<script>
  window.PRODUCT_STUDIO_CONFIG = {
      csrfToken: "<?php echo htmlspecialchars($csrf, ENT_QUOTES); ?>"
  };
  window.CSRF_TOKEN = window.PRODUCT_STUDIO_CONFIG.csrfToken;
</script>
<script src="/admin/assets/js/pages/product_studio.js" defer></script>
<?php if (!$isFragment) require_once __DIR__ . '/../includes/footer.php'; ?>