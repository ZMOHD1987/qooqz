<?php
// admin/fragments/product_studio.php
// Simple image studio fragment: upload, crop (client-side), compress, manage images for a product.
// Requires admin/assets/js/pages/product_studio.js and upload endpoint /api/upload_image.php

if (session_status() === PHP_SESSION_NONE) session_start();
$langBase = __DIR__ . '/../../languages/admin';
$locale = $_SESSION['preferred_language'] ?? 'en';
$I18N = [];
if (is_readable($langBase . '/' . $locale . '.json')) $I18N = json_decode(file_get_contents($langBase . '/' . $locale . '.json'), true);
function t_local($k, $def='') { global $I18N; $flat = []; $f = function($a,&$out,$p=''){ foreach($a as $k=>$v){ $key = $p===''?$k:($p.'.'.$k); if(is_array($v)) $f($v,$out,$key); else {$out[$key]=$v; $parts=explode('.',$key); $s=end($parts); if(!isset($out[$s])) $out[$s]=$v;} } }; $f($I18N,$flat); return $flat[$k] ?? $def ?? $k; }

?>
<link rel="stylesheet" href="/admin/assets/css/pages/banners.css">
<div id="productStudio" style="max-width:1000px;margin:12px auto;">
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
      <div id="studioCanvasWrap" style="border:1px solid #e6eef0;padding:8px;border-radius:8px;background:#fff;">
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
  window.CSRF_TOKEN = "<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES); ?>";
</script>
<script src="/admin/assets/js/pages/product_studio.js" defer></script>