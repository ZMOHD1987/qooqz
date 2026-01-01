<?php
// htdocs/admin/fragments/menu_form.php
// Fragment: Category create/edit form (AJAX-ready) designed to open inside the admin side-panel.
// - Meta for AdminLoader: data-page="menu_form", data-assets-js/css point to per-page assets.
// - Shows all fields, language selection for translations (panels appended below), image field, meta fields.
// - Form submits via AJAX (menu_form.js) to /admin/fragments/menus_list.php (action=save).
// - On success JS dispatches 'menus:saved' and 'menus:refresh' then closes panel.
//
// Save as UTF-8 without BOM.

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../require_permission.php';
require_login_and_permission('manage_categories');

require_once __DIR__ . '/../../api/config/db.php';
$mysqli = connectDB();
if (!($mysqli instanceof mysqli)) {
    http_response_code(500);
    echo '<div class="error">خطأ: لا يمكن الاتصال بقاعدة البيانات.</div>';
    exit;
}

// Load server-side UI translations if header didn't provide them
$langData = $GLOBALS['ADMIN_UI'] ?? [];
function trans($key, $fallback = '') {
    global $langData;
    if (!$key) return $fallback;
    $parts = explode('.', $key);
    $node = $langData;
    foreach ($parts as $p) {
        if (!is_array($node) || !array_key_exists($p, $node)) return $fallback;
        $node = $node[$p];
    }
    return is_string($node) ? $node : $fallback;
}

// CSRF token
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
$CSRF = $_SESSION['csrf_token'];

// Load available languages (for translation tabs)
$langs = [];
$res = $mysqli->query("SELECT code,name,direction FROM languages ORDER BY code ASC");
if ($res && $res->num_rows > 0) {
    while ($r = $res->fetch_assoc()) $langs[$r['code']] = $r;
} else {
    // fallback minimal set
    $langs = [
        'en'=>['code'=>'en','name'=>'English','direction'=>'ltr'],
        'ar'=>['code'=>'ar','name'=>'العربية','direction'=>'rtl']
    ];
}

// load parent categories for select (exclude self when editing)
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$parents = [];
$stmt = $mysqli->prepare("SELECT id,name FROM categories WHERE id != ? ORDER BY name ASC");
if ($stmt) {
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $pres = $stmt->get_result();
    while ($p = $pres->fetch_assoc()) $parents[] = $p;
    $stmt->close();
}

// load category if editing
$category = [
    'id'=>0,'parent_id'=>0,'name'=>'','slug'=>'','description'=>'','image_url'=>'','sort_order'=>0,'is_active'=>1
];
$translations = [];
if ($id > 0) {
    $stmt = $mysqli->prepare("SELECT id,parent_id,name,slug,description,image_url,sort_order,is_active FROM categories WHERE id=? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('i',$id);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res && $row = $res->fetch_assoc()) $category = $row;
        $stmt->close();
    }
    // load translations if table exists
    $stmt = $mysqli->prepare("SELECT language_code,name,slug,description,meta_title,meta_description,meta_keywords FROM category_translations WHERE category_id=?");
    if ($stmt) {
        $stmt->bind_param('i',$id);
        $stmt->execute();
        $tres = $stmt->get_result();
        if ($tres) while ($t = $tres->fetch_assoc()) $translations[$t['language_code']] = $t;
        $stmt->close();
    }
}

// Page assets: AdminLoader will read these and load CSS/JS
$cssHref = '/admin/assets/css/pages/menu_form.css';
$jsSrc   = '/admin/assets/js/pages/menu_form.js';

// Meta for AdminLoader
?>
<meta data-page="menu_form" data-assets-css="<?php echo htmlspecialchars($cssHref, ENT_QUOTES, 'UTF-8'); ?>" data-assets-js="<?php echo htmlspecialchars($jsSrc, ENT_QUOTES, 'UTF-8'); ?>">

<div class="category-form" lang="<?php echo htmlspecialchars($langs[$_SESSION['preferred_language']]['code'] ?? ($_SESSION['preferred_language'] ?? 'ar'), ENT_QUOTES, 'UTF-8'); ?>" style="padding:14px;max-width:920px;">
  <header class="form-header" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
    <h2 class="form-title"><?php echo $category['id'] ? htmlspecialchars(trans('categories.form.title_edit','Edit category')) : htmlspecialchars(trans('categories.form.title_create','Create category')); ?></h2>
    <div>
      <button type="button" class="btn panel-close" aria-label="<?php echo htmlspecialchars(trans('buttons.close','Close')); ?>">×</button>
    </div>
  </header>

  <form id="categoryForm" method="post" action="/admin/fragments/menus_list.php" data-ajax="true" novalidate>
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($CSRF, ENT_QUOTES, 'UTF-8'); ?>">
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="id" value="<?php echo (int)$category['id']; ?>">

    <div class="form-grid" style="display:grid;grid-template-columns:1fr 340px;gap:12px;">
      <div class="col-main">
        <div class="field-group">
          <label for="name"><?php echo htmlspecialchars(trans('categories.form.name','Name')); ?></label>
          <input id="name" name="name" type="text" required value="<?php echo htmlspecialchars($category['name'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="<?php echo htmlspecialchars(trans('categories.form.name_placeholder','Category name')); ?>" />
        </div>

        <div class="field-row" style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
          <div class="field-group">
            <label for="slug"><?php echo htmlspecialchars(trans('categories.form.slug','Slug')); ?></label>
            <input id="slug" name="slug" type="text" value="<?php echo htmlspecialchars($category['slug'], ENT_QUOTES, 'UTF-8'); ?>" />
          </div>
          <div class="field-group">
            <label for="parent_id"><?php echo htmlspecialchars(trans('categories.form.parent','Parent')); ?></label>
            <select id="parent_id" name="parent_id">
              <option value="0">-- <?php echo htmlspecialchars(trans('categories.form.parent_none','None')); ?> --</option>
              <?php foreach ($parents as $p): if ((int)$p['id'] === (int)$category['id']) continue; ?>
                <option value="<?php echo (int)$p['id']; ?>" <?php if ((int)$p['id'] === (int)$category['parent_id']) echo 'selected'; ?>><?php echo htmlspecialchars($p['name'], ENT_QUOTES, 'UTF-8'); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="field-group">
          <label for="description"><?php echo htmlspecialchars(trans('categories.form.description','Description')); ?></label>
          <textarea id="description" name="description" rows="5"><?php echo htmlspecialchars($category['description'], ENT_QUOTES, 'UTF-8'); ?></textarea>
        </div>

        <fieldset class="translations-fieldset" style="margin-top:8px;border:1px solid #eee;padding:10px;border-radius:6px;">
          <legend><?php echo htmlspecialchars(trans('categories.form.translations','Translations')); ?></legend>

          <div style="display:flex;gap:8px;align-items:center;margin-bottom:8px;flex-wrap:wrap;">
            <label for="lang_select" style="margin:0;"><?php echo htmlspecialchars(trans('categories.form.add_language','Add language')); ?></label>
            <select id="lang_select">
              <?php foreach ($langs as $code => $ldata): ?>
                <option value="<?php echo htmlspecialchars($code, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(strtoupper($code).' — '.$ldata['name'], ENT_QUOTES, 'UTF-8'); ?></option>
              <?php endforeach; ?>
            </select>
            <button type="button" id="addLangBtn" class="btn small"><?php echo htmlspecialchars(trans('categories.form.add','Add')); ?></button>
            <small class="text-muted"><?php echo htmlspecialchars(trans('categories.form.translations_note','Add translations per language below')); ?></small>
          </div>

          <div id="translations_container" style="display:flex;flex-direction:column;gap:8px;">
            <?php foreach ($translations as $lc => $t): ?>
              <div class="lang-panel" data-lang="<?php echo htmlspecialchars($lc, ENT_QUOTES, 'UTF-8'); ?>">
                <div style="display:flex;justify-content:space-between;align-items:center;">
                  <strong><?php echo htmlspecialchars(strtoupper($lc), ENT_QUOTES, 'UTF-8'); ?></strong>
                  <button type="button" class="btn small removeLangBtn"><?php echo htmlspecialchars(trans('categories.form.remove','Remove')); ?></button>
                </div>
                <input type="hidden" name="translations[<?php echo htmlspecialchars($lc, ENT_QUOTES, 'UTF-8'); ?>][language_code]" value="<?php echo htmlspecialchars($lc, ENT_QUOTES, 'UTF-8'); ?>">
                <label><?php echo htmlspecialchars(trans('categories.form.name','Name')); ?><input name="translations[<?php echo htmlspecialchars($lc, ENT_QUOTES, 'UTF-8'); ?>][name]" type="text" value="<?php echo htmlspecialchars($t['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"></label>
                <label><?php echo htmlspecialchars(trans('categories.form.slug','Slug')); ?><input name="translations[<?php echo htmlspecialchars($lc, ENT_QUOTES, 'UTF-8'); ?>][slug]" type="text" value="<?php echo htmlspecialchars($t['slug'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"></label>
                <label><?php echo htmlspecialchars(trans('categories.form.description','Description')); ?><textarea name="translations[<?php echo htmlspecialchars($lc, ENT_QUOTES, 'UTF-8'); ?>][description]"><?php echo htmlspecialchars($t['description'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea></label>
                <label><?php echo htmlspecialchars(trans('categories.form.meta_title','Meta title')); ?><input name="translations[<?php echo htmlspecialchars($lc, ENT_QUOTES, 'UTF-8'); ?>][meta_title]" type="text" value="<?php echo htmlspecialchars($t['meta_title'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"></label>
                <label><?php echo htmlspecialchars(trans('categories.form.meta_description','Meta description')); ?><textarea name="translations[<?php echo htmlspecialchars($lc, ENT_QUOTES, 'UTF-8'); ?>][meta_description]"><?php echo htmlspecialchars($t['meta_description'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea></label>
              </div>
            <?php endforeach; ?>
          </div>
        </fieldset>
      </div>

      <aside class="col-side" style="min-width:320px;">
        <div style="border:1px solid #f0f0f0;padding:10px;border-radius:6px;margin-bottom:12px;">
          <h3 style="margin:0 0 8px 0;"><?php echo htmlspecialchars(trans('categories.form.image','Image')); ?></h3>
          <div style="display:flex;gap:8px;align-items:center;">
            <div style="width:180px;height:120px;border:1px dashed #ddd;display:flex;align-items:center;justify-content:center;background:#fafafa;">
              <?php if (!empty($category['image_url'])): ?>
                <img id="image_preview" src="<?php echo htmlspecialchars($category['image_url'], ENT_QUOTES, 'UTF-8'); ?>" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:4px" />
              <?php else: ?>
                <img id="image_preview" src="" alt="" style="display:none;width:100%;height:100%;object-fit:cover;border-radius:4px" />
                <span id="image_placeholder" style="color:#999"><?php echo htmlspecialchars(trans('categories.form.no_image','No image')); ?></span>
              <?php endif; ?>
            </div>
            <div style="flex:1;">
              <input type="hidden" id="image_url" name="image_url" value="<?php echo htmlspecialchars($category['image_url'], ENT_QUOTES, 'UTF-8'); ?>">
              <button type="button" id="chooseImageBtn" class="btn" data-image-studio data-owner-type="category" data-owner-id="<?php echo (int)$category['id']; ?>" data-image-target="#image_url"><?php echo htmlspecialchars(trans('categories.form.choose_image','Choose / upload')); ?></button>
              <div class="text-muted" style="margin-top:6px;"><?php echo htmlspecialchars(trans('categories.form.image_hint','Ideal: 1200px, preview 300x300')); ?></div>
            </div>
          </div>
        </div>

        <div style="border:1px solid #f0f0f0;padding:10px;border-radius:6px;margin-bottom:12px;">
          <h3 style="margin:0 0 8px 0;"><?php echo htmlspecialchars(trans('categories.form.options','Options')); ?></h3>
          <label><?php echo htmlspecialchars(trans('categories.form.sort_order','Sort order')); ?><input type="number" name="sort_order" value="<?php echo (int)$category['sort_order']; ?>"></label>
          <div style="margin-top:8px;display:flex;align-items:center;gap:8px;">
            <label><?php echo htmlspecialchars(trans('categories.form.active','Active')); ?></label>
            <input type="checkbox" name="is_active" value="1" <?php if (!empty($category['is_active'])) echo 'checked'; ?>>
          </div>
        </div>

        <div style="display:flex;gap:8px;justify-content:flex-end;">
          <button type="submit" class="btn primary" id="saveBtn"><?php echo htmlspecialchars(trans('buttons.save','Save')); ?></button>
          <button type="button" class="btn panel-close"><?php echo htmlspecialchars(trans('buttons.cancel','Cancel')); ?></button>
        </div>
      </aside>
    </div>
  </form>
</div>

<script data-no-run="1"></script>

<?php
// fragment may be loaded standalone — no footer when AJAX
?>