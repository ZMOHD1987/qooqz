<?php
// htdocs/admin/fragments/images.php
// Image Studio UI — يمكن تحميله داخل modal (AJAX) أو في popup.
// Accepts optional $_GET['owner_type'] and $_GET['owner_id'] to prefill upload form.
// Returns full HTML fragment (for modal) or standalone page.

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/require_permission.php';
// require_login() — allow any logged user to open studio
require_login();

$currentUser = get_current_user();
$userId = $currentUser['id'] ?? ($_SESSION['user_id'] ?? 0);
$isAdmin = (!empty($_SESSION['role_id']) && (int)$_SESSION['role_id'] === 1) || (!empty($currentUser['role']) && (int)$currentUser['role'] === 1);

require_once __DIR__ . '/../api/config/db.php';
$mysqli = connectDB();

// owner defaults from query (ImageStudio.open will set them)
$ownerType = isset($_GET['owner_type']) ? trim($_GET['owner_type']) : '';
$ownerId = isset($_GET['owner_id']) ? (int)$_GET['owner_id'] : 0;

// fetch images visible to user
$sql = "SELECT id, owner_type, owner_id, filename, url, thumb_url, created_by, created_at, visibility FROM images WHERE 1=1 ";
$params = [];
$types = '';
if (!$isAdmin) {
    $sql .= " AND (created_by = ? OR visibility = 'public') ";
    $params[] = $userId; $types .= 'i';
}
$sql .= " ORDER BY created_at DESC LIMIT 300";
$stmt = $mysqli->prepare($sql);
if ($stmt && $types) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$res = $stmt->get_result();
$images = [];
while ($r = $res->fetch_assoc()) $images[] = $r;
$stmt->close();

// This file can be loaded via AJAX into a modal; output only fragment
?>
<div class="studio" role="dialog" aria-label="استوديو الصور">
  <header style="display:flex;justify-content:space-between;align-items:center">
    <h2>استوديو الصور</h2>
    <button id="studioCloseBtn" type="button" class="btn">إغلاق</button>
  </header>

  <section class="upload" style="margin:12px 0;">
    <form id="uploadForm" enctype="multipart/form-data" method="post" action="/admin/image_upload.php">
      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
      <input type="hidden" name="owner_type" id="owner_type" value="<?php echo htmlspecialchars($ownerType); ?>">
      <input type="hidden" name="owner_id" id="owner_id" value="<?php echo (int)$ownerId; ?>">
      <input type="file" name="image" accept="image/*" required>
      <label>الرؤية:
        <select name="visibility">
          <option value="private">خاص</option>
          <option value="public">عام</option>
        </select>
      </label>
      <button type="submit" class="btn">رفع</button>
    </form>
  </section>

  <section class="gallery" style="display:flex;flex-wrap:wrap;gap:8px;">
    <?php if (empty($images)): ?>
      <p>لا توجد صور حتى الآن.</p>
    <?php else: foreach ($images as $img): ?>
      <div class="thumb" data-url="<?php echo htmlspecialchars($img['url']); ?>" style="width:120px;height:90px;border:1px solid #eee;cursor:pointer;overflow:hidden;display:flex;align-items:center;justify-content:center">
        <img src="<?php echo htmlspecialchars($img['thumb_url'] ?: $img['url']); ?>" alt="" style="width:100%;height:100%;object-fit:cover"/>
      </div>
    <?php endforeach; endif; ?>
  </section>
</div>

<script>
(function(){
  // hook close and selection (works inside modal)
  var closeBtn = document.getElementById('studioCloseBtn');
  if (closeBtn) closeBtn.addEventListener('click', function(){ 
    // emit event to parent modal controller
    window.dispatchEvent(new CustomEvent('ImageStudio:close')); 
  });

  document.querySelectorAll('.thumb').forEach(function(t){
    t.addEventListener('click', function(){
      var url = this.getAttribute('data-url');
      // dispatch selection event (parent page will listen)
      window.dispatchEvent(new CustomEvent('ImageStudio:selected', { detail: { url: url } }));
    });
  });

  // AJAX upload handling: intercept form submit and send via fetch, return JSON
  var form = document.getElementById('uploadForm');
  if (form) {
    form.addEventListener('submit', function(e){
      e.preventDefault();
      var fd = new FormData(form);
      fetch(form.action, { method: 'POST', credentials: 'same-origin', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function(r){ return r.json(); })
        .then(function(json){
          if (json && json.success) {
            // new image uploaded, send selection to parent
            window.dispatchEvent(new CustomEvent('ImageStudio:selected', { detail: { url: json.url } }));
          } else {
            alert((json && json.message) ? json.message : 'Upload failed');
          }
        }).catch(function(err){
          console.error('upload error', err); alert('Upload error: ' + err.message);
        });
    });
  }
})();
</script>