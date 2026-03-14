<?php
declare(strict_types=1);

// Bootstrap UI
$bootstrap = __DIR__ . '/../../api/bootstrap_admin_ui.php';
if (is_readable($bootstrap)) {
    try { require_once $bootstrap; } catch (Throwable $e) {}
}

$ADMIN_UI_PAYLOAD = $ADMIN_UI_PAYLOAD ?? ($GLOBALS['ADMIN_UI'] ?? []);
$lang = $ADMIN_UI_PAYLOAD['lang'] ?? 'ar';
$direction = $ADMIN_UI_PAYLOAD['direction'] ?? 'rtl';
$strings = $ADMIN_UI_PAYLOAD['strings'] ?? [];
$theme = $ADMIN_UI_PAYLOAD['theme'] ?? [];

// Translations helper
function t($key, $fallback = '') {
    global $strings;
    return $strings[$key] ?? $fallback;
}

$csrf = htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES);
$userId = $_SESSION['user']['id'] ?? 1;
$tenantId = $_SESSION['tenant_id'] ?? 1;
$primaryColor = $theme['colors_map']['primary'] ?? '#007bff';
$background = $theme['colors_map']['background'] ?? '#0a0a0a';
$textPrimary = $theme['colors_map']['text-primary'] ?? '#ffffff';
$borderColor = $theme['colors_map']['border'] ?? '#333333';
$fontFamily = $theme['fonts'][0]['font_family'] ?? 'Arial, sans-serif';

$ownerType = $_GET['owner_type'] ?? 'general';
$ownerId = (int)($_GET['owner_id'] ?? 0);

// جلب أنواع الصور من قاعدة البيانات
$imageTypes = [];
try {
    $stmt = $GLOBALS['ADMIN_DB']->query("SELECT id, name, description FROM image_types ORDER BY id");
    $imageTypes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $imageTypes = [
        ['id' => 1, 'name' => 'product', 'description' => 'صور المنتجات'],
        ['id' => 2, 'name' => 'category', 'description' => 'صور الأقسام'],
        ['id' => 3, 'name' => 'user', 'description' => 'صور المستخدمين'],
        ['id' => 4, 'name' => 'general', 'description' => 'صور عامة'],
        ['id' => 5, 'name' => 'banner', 'description' => 'بانر'],
        ['id' => 6, 'name' => 'logo', 'description' => 'لوجو'],
        ['id' => 7, 'name' => 'store', 'description' => 'واجهة المتجر'],
        ['id' => 8, 'name' => 'document', 'description' => 'مستندات'],
    ];
}

// جلب الصور من /api/images
$images = [];
$total = 0;
$currentPage = 1;
$limit = 20;

try {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443 ? 'https' : 'http';
    $apiUrl = $scheme . '://' . $_SERVER['HTTP_HOST'] . "/api/images?limit=$limit&tenant_id=$tenantId";
    if ($ownerId > 0) {
        $apiUrl .= "&owner_id=$ownerId";
    }
    if (isset($_GET['image_type_id']) && $_GET['image_type_id'] > 0) {
        $apiUrl .= "&image_type_id=" . (int)$_GET['image_type_id'];
    }
    
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => 'Accept-Language: ' . $lang,
            'ignore_errors' => true
        ]
    ]);
    
    $response = file_get_contents($apiUrl, false, $context);
    $data = json_decode($response, true);
    
    if ($data && $data['success']) {
        $images = $data['data'] ?? [];
        $total = $data['meta']['total'] ?? count($images);
    }
} catch (Exception $e) {
    error_log("Media Studio API Error: " . $e->getMessage());
}

$isStandalone = empty($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest';
?>

<?php if ($isStandalone): ?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang) ?>" dir="<?= htmlspecialchars($direction) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t('media_studio.title', 'Media Studio') ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/cropperjs/dist/cropper.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <style>
        :root {
            --primary: <?= $primaryColor ?>;
            --background: <?= $background ?>;
            --text-primary: <?= $textPrimary ?>;
            --border: <?= $borderColor ?>;
            --font-family: <?= $fontFamily ?>;
        }
        body { font-family: var(--font-family); background: var(--background); color: var(--text-primary); margin: 0; padding: 20px; direction: <?= $direction ?>; }
        .container { max-width: 1400px; margin: 0 auto; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 10px; }
        .filters { display: flex; gap: 10px; margin-bottom: 20px; flex-wrap: wrap; }
        .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 15px; }
        .item { border: 2px solid var(--border); border-radius: 8px; overflow: hidden; cursor: pointer; position: relative; transition: transform 0.2s, box-shadow 0.2s; }
        .item:hover { transform: scale(1.02); box-shadow: 0 4px 12px rgba(0,0,0,0.3); }
        .item.selected { border-color: var(--primary); box-shadow: 0 0 8px var(--primary); }
        .item img { width: 100%; height: 140px; object-fit: cover; transition: opacity 0.3s; }
        .item .info { padding: 8px; background: rgba(0,0,0,0.7); color: white; position: absolute; bottom: 0; left: 0; right: 0; font-size: 12px; }
        .item .actions { position: absolute; top: 5px; right: 5px; display: flex; gap: 5px; }
        .item .actions button { background: rgba(0,0,0,0.7); color: white; border: none; padding: 5px; cursor: pointer; border-radius: 3px; transition: background 0.2s; }
        .item .actions button:hover { background: rgba(0,0,0,0.9); }
        .actions { display: flex; gap: 10px; margin-bottom: 20px; flex-wrap: wrap; }
        .btn { padding: 10px 15px; border: none; border-radius: 4px; cursor: pointer; background: var(--primary); color: white; font-size: 14px; transition: background 0.2s; }
        .btn:hover { background: #0056b3; }
        .btn.danger { background: #dc3545; }
        .btn.danger:hover { background: #c82333; }
        .btn.secondary { background: var(--border); color: var(--text-primary); }
        .btn.secondary:hover { background: #555; }
        .modal { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); display: none; align-items: center; justify-content: center; z-index: 1000; opacity: 0; transition: opacity 0.3s ease; }
        .modal.show { opacity: 1; }
        .modal-content { background: var(--background); width: 90%; max-width: 700px; padding: 20px; border-radius: 8px; position: relative; box-shadow: 0 4px 6px rgba(0,0,0,0.5); transform: scale(0.9); transition: transform 0.3s ease; }
        .modal.show .modal-content { transform: scale(1); }
        .modal .close { position: absolute; top: 10px; right: 10px; font-size: 24px; cursor: pointer; transition: color 0.2s; }
        .modal .close:hover { color: var(--primary); }
        .pagination { display: flex; justify-content: center; gap: 5px; margin-top: 20px; }
        .pagination button { padding: 8px 12px; border: 1px solid var(--border); background: var(--background); color: var(--text-primary); cursor: pointer; transition: background 0.2s; }
        .pagination button:hover { background: var(--primary); color: white; }
        .pagination button.active { background: var(--primary); color: white; }
        .empty { text-align: center; padding: 60px; color: var(--text-primary); font-size: 1.1rem; }
        .preview { display: none; position: fixed; top: 10px; right: 10px; width: 200px; height: 200px; border: 2px solid var(--primary); border-radius: 8px; overflow: hidden; z-index: 1001; }
        .preview img { width: 100%; height: 100%; object-fit: cover; }
        .loading { display: none; text-align: center; padding: 20px; color: var(--primary); }
        .upload-section { margin-bottom: 20px; padding: 15px; background: rgba(0,0,0,0.1); border-radius: 8px; }
        .upload-section h4 { margin-top: 0; }
        .crop-options { background: var(--background); padding: 15px; border-radius: 8px; margin-top: 10px; }
        .crop-options label { display: block; margin-bottom: 5px; }
        .crop-options select { width: 100%; padding: 8px; border: 1px solid var(--border); border-radius: 4px; background: var(--background); color: var(--text-primary); margin-bottom: 10px; transition: border 0.2s; }
        .crop-options select:focus { border-color: var(--primary); }
        .crop-actions { display: flex; gap: 10px; margin-top: 10px; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; }
        .form-group input, .form-group select { width: 100%; padding: 8px; border: 1px solid var(--border); border-radius: 4px; background: var(--background); color: var(--text-primary); transition: border 0.2s; }
        .form-group input:focus, .form-group select:focus { border-color: var(--primary); }
        @media (max-width: 768px) {
            .header { flex-direction: column; align-items: flex-start; }
            .grid { grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); }
        }
        .item.main { border-color: #28a745; }
        .item.main::after { content: 'رئيسي'; position: absolute; top: 5px; left: 5px; background: #28a745; color: white; padding: 2px 5px; border-radius: 3px; font-size: 10px; }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1><?= t('media_studio.title', 'Media Studio') ?></h1>
        <div class="actions">
            <button id="uploadBtn" class="btn"><?= t('media_studio.upload', 'Upload') ?></button>
            <button id="deleteBtn" class="btn danger" style="display: none;"><?= t('media_studio.delete_selected', 'Delete Selected') ?></button>
            <button id="cropBtn" class="btn" style="display: none;"><?= t('media_studio.crop', 'Crop') ?></button>
            <button id="downloadBtn" class="btn" style="display: none;"><?= t('media_studio.download_selected', 'Download Selected') ?></button>
            <button id="pasteBtn" class="btn"><?= t('media_studio.paste', 'Paste') ?></button>
            <button id="useBtn" class="btn secondary" style="display: none;"><?= t('media_studio.use_selected', 'Use Selected') ?></button>
        </div>
    </div>
    
    <!-- قسم رفع الصور -->
    <div class="upload-section" id="uploadSection">
        <h4><?= t('media_studio.upload_new', 'Upload New Images') ?></h4>
        <div style="display: flex; gap: 10px; flex-wrap: wrap; align-items: center;">
            <input type="file" id="uploadInput" multiple accept="image/*" style="flex: 1; padding: 8px; border: 1px solid var(--border); border-radius: 4px; background: var(--background); color: var(--text-primary);">
            <input type="number" id="uploadOwnerId" placeholder="<?= t('media_studio.owner_id', 'Owner ID (optional)') ?>" value="" style="width: 120px; padding: 8px; border: 1px solid var(--border); border-radius: 4px; background: var(--background); color: var(--text-primary);">
            <select id="uploadImageTypeId" style="width: 200px; padding: 8px; border: 1px solid var(--border); border-radius: 4px; background: var(--background); color: var(--text-primary);">
                <option value=""><?= t('media_studio.select_image_type', 'Select Image Type (optional)') ?></option>
                <?php foreach ($imageTypes as $type): ?>
                    <option value="<?= $type['id'] ?>" <?= ($ownerType === $type['name']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($type['name']) ?> - <?= htmlspecialchars($type['description']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <select id="uploadVisibility" style="width: 120px; padding: 8px; border: 1px solid var(--border); border-radius: 4px; background: var(--background); color: var(--text-primary);">
                <option value="private"><?= t('media_studio.private', 'Private') ?></option>
                <option value="public"><?= t('media_studio.public', 'Public') ?></option>
            </select>
            <button id="uploadSubmitBtn" class="btn"><?= t('media_studio.upload', 'Upload') ?></button>
        </div>
        <p style="margin-top: 10px; font-size: 12px; color: #888;">يمكن رفع الصور بدون تحديد مالك أو نوع، ثم تحديثها لاحقاً عند الاستخدام.</p>
    </div>
    
    <!-- الفلاتر -->
    <div class="filters">
        <input type="text" id="searchInput" placeholder="<?= t('media_studio.search', 'Search by filename...') ?>" style="flex: 1; padding: 8px; border: 1px solid var(--border); border-radius: 4px; background: var(--background); color: var(--text-primary);">
        <select id="imageTypeFilter" style="width: 200px; padding: 8px; border: 1px solid var(--border); border-radius: 4px; background: var(--background); color: var(--text-primary);">
            <option value=""><?= t('media_studio.all_types', 'All Types') ?></option>
            <?php foreach ($imageTypes as $type): ?>
                <option value="<?= $type['id'] ?>"><?= htmlspecialchars($type['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <input type="number" id="ownerIdFilter" placeholder="<?= t('media_studio.owner_id', 'Owner ID') ?>" value="<?= $ownerId ?>" style="width: 120px; padding: 8px; border: 1px solid var(--border); border-radius: 4px; background: var(--background); color: var(--text-primary);">
        <select id="visibilityFilter" style="width: 120px; padding: 8px; border: 1px solid var(--border); border-radius: 4px; background: var(--background); color: var(--text-primary);">
            <option value=""><?= t('media_studio.all_visibility', 'All Visibility') ?></option>
            <option value="public">Public</option>
            <option value="private">Private</option>
        </select>
        <button id="applyFilterBtn" class="btn"><?= t('media_studio.apply_filter', 'Apply Filter') ?></button>
    </div>
    
    <div class="loading" id="loading"><?= t('media_studio.loading', 'Loading...') ?></div>
    
    <div class="grid" id="mediaGrid">
        <?php if (empty($images)): ?>
            <div class="empty"><?= t('media_studio.no_images', 'No images yet. Upload some!') ?></div>
        <?php else: foreach ($images as $img): ?>
            <div class="item <?= $img['is_main'] ? 'main' : '' ?>" data-id="<?= $img['id'] ?>" data-url="<?= htmlspecialchars($img['url']) ?>" data-filename="<?= htmlspecialchars($img['filename'] ?? '') ?>" data-thumb="<?= htmlspecialchars($img['thumb_url'] ?? $img['url']) ?>" data-owner-id="<?= $img['owner_id'] ?>" data-image-type-id="<?= $img['image_type_id'] ?>" data-visibility="<?= $img['visibility'] ?>" data-is-main="<?= $img['is_main'] ?>" data-sort-order="<?= $img['sort_order'] ?>" data-mime-type="<?= htmlspecialchars($img['mime_type'] ?? '') ?>" data-size="<?= $img['size'] ?? 0 ?>">
                <img src="<?= htmlspecialchars($img['thumb_url'] ?? $img['url']) ?>" alt="<?= htmlspecialchars($img['filename'] ?? '') ?>">
                <div class="info">
                    <?= htmlspecialchars(substr($img['filename'] ?? 'Image', 0, 15)) ?><?= strlen($img['filename'] ?? '') > 15 ? '...' : '' ?><br>
                    <small><?= htmlspecialchars($img['image_type_name'] ?? '') ?> | <?= $img['visibility'] ?> | Main: <?= $img['is_main'] ? 'Yes' : 'No' ?> | <?= number_format(($img['size'] ?? 0) / 1024, 2) ?> KB</small>
                </div>
                <div class="actions">
                    <button class="edit-btn" data-id="<?= $img['id'] ?>" title="Edit">✏️</button>
                    <button class="set-main-btn" data-id="<?= $img['id'] ?>" title="Set as Main">⭐</button>
                </div>
            </div>
        <?php endforeach; endif; ?>
    </div>
    
    <?php if ($total > $limit): ?>
    <div class="pagination" id="paginationControls">
        <button id="prevPage" title="Previous">←</button>
        <span id="pageInfo">1 / 1</span>
        <button id="nextPage" title="Next">→</button>
    </div>
    <?php endif; ?>
</div>

<div id="preview" class="preview">
    <img id="previewImg" src="" alt="Preview">
</div>

<!-- Crop Modal -->
<div id="cropModal" class="modal">
    <div class="modal-content">
        <span class="close" id="cropClose">&times;</span>
        <h3><?= t('media_studio.crop_image', 'Crop Image') ?></h3>
        <div style="width:100%;height:400px;overflow:hidden;">
            <img id="cropImage" style="max-width:100%; max-height:100%;">
        </div>
        
        <!-- Crop Options -->
        <div class="crop-options">
            <label><?= t('media_studio.aspect_ratio', 'Aspect Ratio:') ?></label>
            <select id="aspectRatio">
                <option value="NaN"><?= t('media_studio.free_ratio', 'Free Ratio') ?></option>
                <option value="1">1:1 (Square)</option>
                <option value="4/3">4:3 (Standard)</option>
                <option value="16/9">16:9 (Widescreen)</option>
                <option value="3/2">3:2 (Classic)</option>
                <option value="9/16">9:16 (Portrait)</option>
                <option value="21/9">21:9 (Ultrawide)</option>
            </select>
            
            <label><?= t('media_studio.crop_size', 'Crop Size:') ?></label>
            <select id="cropSize">
                <option value=""><?= t('media_studio.custom_size', 'Custom Size') ?></option>
                <option value="800x600">800x600 (Small)</option>
                <option value="1024x768">1024x768 (Medium)</option>
                <option value="1920x1080">1920x1080 (HD)</option>
                <option value="2560x1440">2560x1440 (2K)</option>
                <option value="3840x2160">3840x2160 (4K)</option>
                <option value="1200x630">1200x630 (Social Media)</option>
                <option value="1080x1080">1080x1080 (Instagram Square)</option>
                <option value="1080x1350">1080x1350 (Instagram Story)</option>
                <option value="1200x675">1200x675 (YouTube Thumbnail)</option>
            </select>
            
            <div class="crop-actions">
                <button id="cropRotateLeft" class="btn">↶ <?= t('media_studio.rotate_left', 'Rotate Left') ?></button>
                <button id="cropRotateRight" class="btn">↷ <?= t('media_studio.rotate_right', 'Rotate Right') ?></button>
                <button id="cropFlipHorizontal" class="btn">↔️ <?= t('media_studio.flip_horizontal', 'Flip Horizontal') ?></button>
                <button id="cropFlipVertical" class="btn">↕️ <?= t('media_studio.flip_vertical', 'Flip Vertical') ?></button>
            </div>
        </div>
        
        <div class="actions" style="margin-top: 10px;">
            <button id="cropSaveBtn" class="btn"><?= t('media_studio.save_crop', 'Save Cropped') ?></button>
            <button id="cropCancelBtn" class="btn danger"><?= t('media_studio.cancel', 'Cancel') ?></button>
        </div>
    </div>
</div>

<!-- Edit Modal -->
<div id="editModal" class="modal">
    <div class="modal-content">
        <span class="close" id="editClose">&times;</span>
        <h3>Edit Image</h3>
        <form id="editForm">
            <input type="hidden" id="editId" name="id">
            <div class="form-group">
                <label>Owner ID:</label>
                <input type="number" id="editOwnerId" name="owner_id">
            </div>
            <div class="form-group">
                <label>Image Type ID:</label>
                <select id="editImageTypeId" name="image_type_id">
                    <?php foreach ($imageTypes as $type): ?>
                        <option value="<?= $type['id'] ?>"><?= htmlspecialchars($type['name']) ?> - <?= htmlspecialchars($type['description']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Filename:</label>
                <input type="text" id="editFilename" name="filename">
            </div>
            <div class="form-group">
                <label>URL:</label>
                <input type="text" id="editUrl" name="url" required>
            </div>
            <div class="form-group">
                <label>Thumb URL:</label>
                <input type="text" id="editThumbUrl" name="thumb_url">
            </div>
            <div class="form-group">
                <label>MIME Type:</label>
                <input type="text" id="editMimeType" name="mime_type">
            </div>
            <div class="form-group">
                <label>Size (bytes):</label>
                <input type="number" id="editSize" name="size">
            </div>
            <div class="form-group">
                <label>Visibility:</label>
                <select id="editVisibility" name="visibility">
                    <option value="private">Private</option>
                    <option value="public">Public</option>
                </select>
            </div>
            <div class="form-group">
                <label>Is Main:</label>
                <select id="editIsMain" name="is_main">
                    <option value="0">No</option>
                    <option value="1">Yes</option>
                </select>
            </div>
            <div class="form-group">
                <label>Sort Order:</label>
                <input type="number" id="editSortOrder" name="sort_order">
            </div>
            <div class="actions">
                <button type="submit" class="btn">Save</button>
                <button type="button" id="editCancelBtn" class="btn danger">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/cropperjs/dist/cropper.min.js"></script>
<script>
window.CSRF = '<?= $csrf ?>';
window.LANG = '<?= $lang ?>';
window.USER_ID = <?= $userId ?>;
window.TENANT_ID = <?= $tenantId ?>;
window.TRANSLATIONS = <?= json_encode($strings) ?>;
window.API_IMAGES = window.location.origin + '/api/images';
window.OWNER_TYPE = '<?= $ownerType ?>';
window.OWNER_ID = <?= $ownerId ?>;
window.IMAGE_TYPES = <?= json_encode($imageTypes) ?>;
</script>
<script src="/admin/assets/js/pages/media_studio.js"></script>
</body>
</html>
<?php else: ?>
<!-- داخل modal -->
<div class="studio" style="height:100%;display:flex;flex-direction:column;">
    <header style="padding:16px;background:#f8f9fa;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;">
        <h2 style="margin:0;"><?= t('media_studio.title', 'Media Studio') ?></h2>
        <button id="studioCloseBtn" type="button" style="background:none;border:none;font-size:1.8rem;cursor:pointer;">×</button>
    </header>
    
    <section class="upload" style="padding:16px;background:#f8f9fa;border-bottom:1px solid var(--border);">
        <form id="uploadForm">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <input type="hidden" name="tenant_id" value="<?= $tenantId ?>">
            <input type="hidden" name="owner_id" value="<?= $ownerId ?>">
            <input type="hidden" name="owner_type" value="<?= htmlspecialchars($ownerType) ?>">
            
            <div style="display:flex;gap:16px;align-items:end;flex-wrap:wrap;">
                <input type="file" name="image" accept="image/*" required style="padding:10px;border:1px solid var(--border);border-radius:6px;background: var(--background);color: var(--text-primary);">
                
                <select name="image_type_id" required style="padding:10px;border:1px solid var(--border);border-radius:6px;background: var(--background);color: var(--text-primary);">
                    <option value=""><?= t('media_studio.select_image_type', 'Select Image Type') ?></option>
                    <?php foreach ($imageTypes as $type): ?>
                        <option value="<?= $type['id'] ?>" <?= ($ownerType === $type['name']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($type['description']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                
                <select name="visibility" style="padding:10px;border:1px solid var(--border);border-radius:6px;background: var(--background);color: var(--text-primary);">
                    <option value="private"><?= t('media_studio.private', 'Private') ?></option>
                    <option value="public"><?= t('media_studio.public', 'Public') ?></option>
                </select>
                
                <button type="submit" style="padding:10px 20px;background: var(--primary);color:white;border:none;border-radius:6px;cursor:pointer;">
                    <?= t('media_studio.upload', 'Upload') ?>
                </button>
            </div>
        </form>
    </section>
    
    <section class="gallery" style="flex:1;overflow:auto;padding:16px;display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:16px;">
        <?php if (empty($images)): ?>
            <div style="grid-column:1/-1;text-align:center;padding:60px;color:#888;">
                <?= t('media_studio.no_images', 'No images yet') ?>
            </div>
        <?php else: foreach ($images as $img): ?>
            <div class="item" 
                 data-url="<?= htmlspecialchars($img['url']) ?>" 
                 data-id="<?= $img['id'] ?>"
                 data-thumb="<?= htmlspecialchars($img['thumb_url'] ?? $img['url']) ?>"
                 style="aspect-ratio:1;border-radius:8px;overflow:hidden;cursor:pointer;box-shadow:0 4px 12px rgba(0,0,0,0.15);">
                <img src="<?= htmlspecialchars($img['thumb_url'] ?? $img['url']) ?>" 
                     alt="" 
                     style="width:100%;height:100%;object-fit:cover;">
            </div>
        <?php endforeach; endif; ?>
    </section>
    
    <section class="actions" style="padding:16px;border-top:1px solid var(--border);display:flex;gap:10px;">
        <button id="selectBtn" class="btn" style="display:none;"><?= t('media_studio.select', 'Select') ?></button>
    </section>
</div>

<script>
let selectedImage = null;

document.querySelectorAll('.item').forEach(item => {
    item.addEventListener('click', () => {
        document.querySelectorAll('.item').forEach(i => i.style.border = 'none');
        item.style.border = '3px solid <?= $primaryColor ?>';
        selectedImage = item.dataset;
        document.getElementById('selectBtn').style.display = 'block';
    });
});

document.getElementById('selectBtn').addEventListener('click', () => {
    if (selectedImage) {
        window.dispatchEvent(new CustomEvent('ImageStudio:selected', { 
            detail: { 
                url: selectedImage.url,
                id: selectedImage.id,
                thumb_url: selectedImage.thumb
            } 
        }));
        // إغلاق النموذج مباشرة بعد الاختيار
        window.dispatchEvent(new CustomEvent('ImageStudio:close'));
    }
});

document.getElementById('uploadForm').addEventListener('submit', async e => {
    e.preventDefault();
    const fd = new FormData(e.target);
    
    try {
        const res = await fetch(window.location.origin + '/api/images', { 
            method: 'POST', 
            body: fd 
        });
        const json = await res.json();
        
        if (json.success && json.data && json.data[0]) {
            const image = json.data[0];
            // إرسال الحدث مع البيانات الكاملة وإغلاق النموذج
            window.dispatchEvent(new CustomEvent('ImageStudio:selected', { 
                detail: { 
                    url: image.url,
                    id: image.id,
                    thumb_url: image.thumb_url
                } 
            }));
            // إغلاق النموذج مباشرة بعد الرفع الناجح
            window.dispatchEvent(new CustomEvent('ImageStudio:close'));
        } else {
            alert(json.message || '<?= t('media_studio.upload_failed', 'Upload failed') ?>');
        }
    } catch (error) {
        alert('<?= t('media_studio.upload_error', 'Upload error') ?>');
    }
});

document.getElementById('studioCloseBtn').addEventListener('click', () => {
    window.dispatchEvent(new CustomEvent('ImageStudio:close'));
});
</script>
<?php endif; ?>