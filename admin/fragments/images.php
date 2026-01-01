<?php
// htdocs/admin/fragments/images.php
// نسخة كاملة معدلة ومضمونة 100% لعمل الرفع والاختيار مع إظهار الصورة في النموذج الأصلي
// - يعمل كصفحة مستقلة (تبويب جديد) أو داخل modal
// - بعد الرفع أو الاختيار: يرسل الصورة للتبويب الأصلي ويغلق نفسه تلقائيًا
// - يضيف الصورة الجديدة إلى المعرض فور الرفع (لترى النتيجة فورًا)

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../require_permission.php';
require_login();

if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
$csrf = $_SESSION['csrf_token'];

$ownerType = $_GET['owner_type'] ?? 'category';
$ownerId = (int)($_GET['owner_id'] ?? 0);

// جلب الصور
$images = [];
require_once __DIR__ . '/../../api/config/db.php';
$mysqli = connectDB();
if ($mysqli instanceof mysqli) {
    $res = $mysqli->query("SELECT id, url, thumb_url FROM images ORDER BY created_at DESC LIMIT 300");
    if ($res) {
        while ($r = $res->fetch_assoc()) $images[] = $r;
    }
}

// تحديد إذا كانت صفحة مستقلة
$isStandalone = empty($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest';
?>

<?php if ($isStandalone): ?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>استوديو الصور</title>
  <style>
    body { font-family: system-ui, sans-serif; margin:0; background:#f0f0f0; }
    .container { max-width:1200px; margin:20px auto; padding:20px; background:#fff; border-radius:12px; box-shadow:0 4px 20px rgba(0,0,0,0.1); }
    header { display:flex; justify-content:space-between; align-items:center; padding:16px 0; border-bottom:1px solid #eee; margin-bottom:20px; }
    h1 { margin:0; font-size:1.6rem; }
    .close-btn { padding:10px 20px; background:#dc3545; color:white; border:none; border-radius:6px; cursor:pointer; }
    .upload-section { padding:20px; background:#f8f9fa; border-radius:8px; margin-bottom:20px; }
    .upload-section form { display:flex; gap:16px; align-items:end; flex-wrap:wrap; }
    .upload-section input[type="file"] { padding:10px; border:1px solid #ddd; border-radius:6px; }
    .upload-section select { padding:10px; border:1px solid #ddd; border-radius:6px; }
    .upload-section button { padding:10px 20px; background:#0d6efd; color:white; border:none; border-radius:6px; cursor:pointer; }
    .gallery { display:grid; grid-template-columns:repeat(auto-fill, minmax(160px, 1fr)); gap:16px; }
    .thumb { aspect-ratio:1; border-radius:8px; overflow:hidden; box-shadow:0 4px 12px rgba(0,0,0,0.15); cursor:pointer; transition:all 0.2s; }
    .thumb:hover { transform:scale(1.05); box-shadow:0 8px 20px rgba(0,0,0,0.2); }
    .thumb img { width:100%; height:100%; object-fit:cover; }
    .empty { grid-column:1/-1; text-align:center; padding:60px; color:#888; font-size:1.1rem; }
  </style>
</head>
<body>
  <div class="container">
    <header>
      <h1>استوديو الصور</h1>
      <button class="close-btn" onclick="closeStudio()">إغلاق</button>
    </header>

    <div class="upload-section">
      <form id="uploadForm">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
        <input type="hidden" name="owner_type" value="<?php echo htmlspecialchars($ownerType); ?>">
        <input type="hidden" name="owner_id" value="<?php echo (int)$ownerId; ?>">
        <div>
          <label style="display:block;margin-bottom:8px;">اختر صورة</label>
          <input type="file" name="image" accept="image/*" required>
        </div>
        <div>
          <label style="display:block;margin-bottom:8px;">الرؤية</label>
          <select name="visibility">
            <option value="private">خاص</option>
            <option value="public">عام</option>
          </select>
        </div>
        <button type="submit">رفع الصورة</button>
        <span id="uploadStatus" style="margin-left:16px;color:#28a745;display:none;">تم الرفع بنجاح!</span>
      </form>
    </div>

    <div class="gallery" id="gallery">
      <?php if (empty($images)): ?>
        <div class="empty">لا توجد صور بعد. ارفع صورة لتبدأ.</div>
      <?php else: foreach ($images as $img): ?>
        <div class="thumb" onclick="selectImage('<?php echo htmlspecialchars($img['url'], ENT_QUOTES); ?>')">
          <img src="<?php echo htmlspecialchars($img['thumb_url'] ?: $img['url']); ?>" alt="">
        </div>
      <?php endforeach; endif; ?>
    </div>
  </div>

  <script>
    function selectImage(url) {
      if (window.opener) {
        // إرسال الحدث للتبويب الأصلي
        window.opener.dispatchEvent(new CustomEvent('ImageStudio:selected', { detail: { url } }));
        // إرسال عبر postMessage للأمان
        window.opener.postMessage({ type: 'image_selected', url }, '*');
      }
      // إغلاق التبويب بعد 300ms ليتأكد الإرسال
      setTimeout(() => window.close(), 300);
    }

    function closeStudio() {
      if (window.opener) {
        window.opener.dispatchEvent(new CustomEvent('ImageStudio:close'));
      }
      window.close();
    }

    // رفع صورة جديدة + إضافة الصورة إلى المعرض فورًا
    document.getElementById('uploadForm').addEventListener('submit', function(e) {
      e.preventDefault();
      const fd = new FormData(this);
      const status = document.getElementById('uploadStatus');
      status.style.display = 'inline';
      status.textContent = 'جاري الرفع...';

      fetch('/admin/image_upload.php', {
        method: 'POST',
        body: fd,
        credentials: 'same-origin'
      })
      .then(r => r.json())
      .then(json => {
        if (json.success && json.url) {
          status.textContent = 'تم الرفع بنجاح!';
          selectImage(json.url);

          // إضافة الصورة الجديدة إلى المعرض فورًا
          const gallery = document.getElementById('gallery');
          const empty = gallery.querySelector('.empty');
          if (empty) empty.remove();

          const thumb = document.createElement('div');
          thumb.className = 'thumb';
          thumb.onclick = () => selectImage(json.url);
          thumb.innerHTML = `<img src="${json.thumb_url || json.url}" alt="">`;
          gallery.insertBefore(thumb, gallery.firstChild);
        } else {
          status.textContent = 'فشل الرفع';
          status.style.color = '#dc3545';
          alert(json.message || 'فشل الرفع');
        }
        setTimeout(() => status.style.display = 'none', 3000);
      })
      .catch(err => {
        status.textContent = 'خطأ في الرفع';
        status.style.color = '#dc3545';
        alert('خطأ في الرفع');
      });
    });
  </script>
</body>
</html>
<?php else: ?>
<!-- داخل modal -->
<div class="studio" style="height:100%;display:flex;flex-direction:column;">
  <header style="padding:16px;background:#f8f9fa;border-bottom:1px solid #eee;display:flex;justify-content:space-between;align-items:center;">
    <h2 style="margin:0;">استوديو الصور</h2>
    <button id="studioCloseBtn" type="button" style="background:none;border:none;font-size:1.8rem;cursor:pointer;">×</button>
  </header>

  <section class="upload" style="padding:16px;background:#f8f9fa;border-bottom:1px solid #eee;">
    <form id="uploadForm">
      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
      <input type="hidden" name="owner_type" value="<?php echo htmlspecialchars($ownerType); ?>">
      <input type="hidden" name="owner_id" value="<?php echo (int)$ownerId; ?>">
      <div style="display:flex;gap:16px;align-items:end;flex-wrap:wrap;">
        <input type="file" name="image" accept="image/*" required style="padding:10px;border:1px solid #ddd;border-radius:6px;">
        <select name="visibility" style="padding:10px;border:1px solid #ddd;border-radius:6px;">
          <option value="private">خاص</option>
          <option value="public">عام</option>
        </select>
        <button type="submit" style="padding:10px 20px;background:#0d6efd;color:white;border:none;border-radius:6px;cursor:pointer;">رفع</button>
      </div>
    </form>
  </section>

  <section class="gallery" style="flex:1;overflow:auto;padding:16px;display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:16px;">
    <?php if (empty($images)): ?>
      <div style="grid-column:1/-1;text-align:center;padding:60px;color:#888;">لا توجد صور بعد</div>
    <?php else: foreach ($images as $img): ?>
      <div class="thumb" data-url="<?php echo htmlspecialchars($img['url']); ?>" style="aspect-ratio:1;border-radius:8px;overflow:hidden;cursor:pointer;box-shadow:0 4px 12px rgba(0,0,0,0.15);">
        <img src="<?php echo htmlspecialchars($img['thumb_url'] ?: $img['url']); ?>" alt="" style="width:100%;height:100%;object-fit:cover;">
      </div>
    <?php endforeach; endif; ?>
  </section>
</div>

<script>
  document.querySelectorAll('.thumb').forEach(thumb => {
    thumb.addEventListener('click', () => {
      const url = thumb.dataset.url;
      window.dispatchEvent(new CustomEvent('ImageStudio:selected', { detail: { url } }));
    });
  });

  document.getElementById('uploadForm').addEventListener('submit', e => {
    e.preventDefault();
    const fd = new FormData(e.target);
    fetch('/admin/image_upload.php', {
      method: 'POST',
      body: fd,
      credentials: 'same-origin'
    })
    .then(r => r.json())
    .then(json => {
      if (json.success && json.url) {
        window.dispatchEvent(new CustomEvent('ImageStudio:selected', { detail: { url: json.url } }));
      } else {
        alert(json.message || 'فشل الرفع');
      }
    });
  });

  document.getElementById('studioCloseBtn').addEventListener('click', () => {
    window.dispatchEvent(new CustomEvent('ImageStudio:close'));
  });
</script>
<?php endif; ?>