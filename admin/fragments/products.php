<?php
// admin/fragments/products.php
if (session_status() === PHP_SESSION_NONE) session_start();

// CSRF token
if (empty($_SESSION['csrf_token'])) {
    try { $_SESSION['csrf_token'] = bin2hex(random_bytes(16)); } catch (Exception $e) { $_SESSION['csrf_token'] = bin2hex(openssl_random_pseudo_bytes(16)); }
}
$csrf = htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES);

// تحميل الترجمات بناءً على لغة المستخدم
$langBase = dirname(__DIR__, 2) . '/languages/admin';
$userLang = $_SESSION['user']['preferred_language'] ?? 'en';
$langFile = $langBase . '/' . $userLang . '.json';

// ترجمة افتراضية - الإنجليزية
$defaultLangFile = $langBase . '/en.json';

$langData = [];
if (file_exists($langFile)) {
    $langData = json_decode(file_get_contents($langFile), true);
} elseif (file_exists($defaultLangFile)) {
    $langData = json_decode(file_get_contents($defaultLangFile), true);
}

// دالة الترجمة
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

// تحميل اللغات المتاحة
$languages = [];
$languagesDir = dirname(__DIR__, 2) . '/languages/admin';
if (is_dir($languagesDir)) {
    foreach (glob($languagesDir . '/*.json') as $f) {
        $code = pathinfo($f, PATHINFO_FILENAME);
        $json = @json_decode(@file_get_contents($f), true) ?: [];
        $languages[] = [
            'code' => $code, 
            'name' => $json['name'] ?? strtoupper($code), 
            'direction' => $json['direction'] ?? 'ltr'
        ];
    }
}
if (empty($languages)) $languages = [['code' => 'en', 'name' => 'English', 'direction' => 'ltr']];
?>
<link rel="stylesheet" href="/admin/assets/css/pages/products.css">
<div id="adminProducts" class="admin-fragment" style="max-width:1200px;margin:18px auto;">
  <header style="display:flex;align-items:center;gap:12px;margin-bottom:12px;">
    <h2 style="margin:0;"><?php echo trans('admin.products.title', 'Products'); ?></h2>
    <div style="margin-left:auto;color:#6b7280;"><?php echo htmlspecialchars($_SESSION['user']['username'] ?? 'guest'); ?></div>
  </header>
  <div id="productsNotice" class="status" style="min-height:22px;margin-bottom:8px;color:#064e3b;"></div>
  <div class="tools" style="display:flex;align-items:center;gap:8px;margin-bottom:12px;">
    <input id="productSearch" type="search" placeholder="<?php echo trans('admin.products.search_placeholder', 'Search name, sku, slug...'); ?>" style="padding:8px;border:1px solid #e6eef0;border-radius:999px;width:320px;">
    <button id="productRefresh" class="btn"><?php echo trans('admin.products.refresh', 'Refresh'); ?></button>
    <button id="productNewBtn" class="btn primary"><?php echo trans('admin.products.create', 'Create Product'); ?></button>
    <span style="margin-left:auto;color:#6b7280;"><?php echo trans('admin.products.total', 'Total'); ?>: <span id="productsCount">‑</span></span>
  </div>
  <div class="table-wrap" style="margin-bottom:16px;">
    <table id="productsTable" style="width:100%;min-width:900px;">
      <thead>
        <tr>
          <th style="width:70px">ID</th>
          <th><?php echo trans('admin.general.name', 'Name'); ?></th>
          <th style="width:160px"><?php echo trans('admin.general.sku', 'SKU'); ?> / <?php echo trans('admin.general.slug', 'Slug'); ?></th>
          <th style="width:120px"><?php echo trans('admin.general.type', 'Type'); ?></th>
          <th style="width:120px;text-align:center"><?php echo trans('admin.inventory.stock_quantity', 'Stock'); ?></th>
          <th style="width:120px;text-align:center"><?php echo trans('admin.products.active', 'Active'); ?></th>
          <th style="width:240px"><?php echo trans('admin.products.actions', 'Actions'); ?></th>
        </tr>
      </thead>
      <tbody id="productsTbody"><tr><td colspan="7" style="padding:12px;text-align:center;color:#6b7280;"><?php echo trans('admin.products.loading', 'Loading...'); ?></td></tr></tbody>
    </table>
  </div>
  <div id="productFormWrap" class="form-wrap" style="display:none;">
    <div id="productErrors" style="display:none;color:#b91c1c;margin-bottom:8px;"></div>
    <form id="productForm" autocomplete="off" enctype="multipart/form-data" style="display:grid;grid-template-columns:1fr 420px;gap:14px;">
      <input type="hidden" id="product_id" name="id" value="0">
      <input type="hidden" id="product_translations" name="translations" value="">
      <input type="hidden" id="product_attributes" name="attributes" value="">
      <input type="hidden" id="product_variants" name="variants" value="">
      <input type="hidden" id="product_categories_json" name="categories" value="">
      <input type="hidden" id="product_action" name="action" value="save">
      <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
      
      <div>
        <section>
          <h3><?php echo trans('admin.general.general', 'General'); ?></h3>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
            <label><?php echo trans('admin.general.default_name', 'Default name'); ?> <input id="product_name" name="name" type="text"></label>
            <label><?php echo trans('admin.general.sku', 'SKU'); ?> <input id="product_sku" name="sku" type="text"></label>
          </div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-top:8px;">
            <label><?php echo trans('admin.general.slug', 'Slug'); ?> <input id="product_slug" name="slug" type="text"></label>
            <label><?php echo trans('admin.general.barcode', 'Barcode'); ?> <input id="product_barcode" name="barcode" type="text"></label>
          </div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-top:8px;">
            <label><?php echo trans('admin.general.type', 'Type'); ?>
              <select id="product_type" name="product_type">
                <option value="simple"><?php echo trans('admin.general.simple', 'simple'); ?></option>
                <option value="variable"><?php echo trans('admin.general.variable', 'variable'); ?></option>
                <option value="digital"><?php echo trans('admin.general.digital', 'digital'); ?></option>
                <option value="bundle"><?php echo trans('admin.general.bundle', 'bundle'); ?></option>
              </select>
            </label>
            <label><?php echo trans('admin.general.brand', 'Brand'); ?> <select id="product_brand_id" name="brand_id"></select></label>
          </div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-top:8px;">
            <label><?php echo trans('admin.general.manufacturer', 'Manufacturer'); ?> <select id="product_manufacturer_id" name="manufacturer_id"></select></label>
            <label><?php echo trans('admin.general.published_at', 'Published at'); ?> <input id="product_published_at" name="published_at" type="datetime-local"></label>
          </div>
          <div style="margin-top:8px;">
            <label><?php echo trans('admin.general.short_description', 'Short description'); ?> <input id="product_description" name="description" type="text"></label>
          </div>
        </section>
        <section style="margin-top:12px;">
          <h4><?php echo trans('admin.pricing.pricing', 'Pricing'); ?></h4>
          <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px;">
            <label><?php echo trans('admin.pricing.price', 'Price'); ?> <input id="product_price" name="price" type="text"></label>
            <label><?php echo trans('admin.pricing.compare_at_price', 'Compare at'); ?> <input id="product_compare_at_price" name="compare_at_price" type="text"></label>
            <label><?php echo trans('admin.pricing.cost_price', 'Cost price'); ?> <input id="product_cost_price" name="cost_price" type="text"></label>
          </div>
        </section>
        <section data-section="inventory" style="margin-top:12px;">
          <h4><?php echo trans('admin.inventory.inventory', 'Inventory'); ?></h4>
          <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px;">
            <label><?php echo trans('admin.inventory.stock_quantity', 'Stock Qty'); ?> <input id="product_stock_quantity" name="stock_quantity" type="number" value="0"></label>
            <label><?php echo trans('admin.inventory.low_stock_threshold', 'Low threshold'); ?> <input id="product_low_stock_threshold" name="low_stock_threshold" type="number" value="5"></label>
            <label><?php echo trans('admin.inventory.stock_status', 'Stock status'); ?>
              <select id="product_stock_status" name="stock_status">
                <option value="in_stock"><?php echo trans('admin.inventory.in_stock', 'in_stock'); ?></option>
                <option value="out_of_stock"><?php echo trans('admin.inventory.out_of_stock', 'out_of_stock'); ?></option>
                <option value="on_backorder"><?php echo trans('admin.inventory.on_backorder', 'on_backorder'); ?></option>
              </select>
            </label>
          </div>
          <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px;margin-top:8px;">
            <label><?php echo trans('admin.inventory.manage_stock', 'Manage stock'); ?><select id="product_manage_stock" name="manage_stock"><option value="1"><?php echo trans('admin.general.yes', 'Yes'); ?></option><option value="0"><?php echo trans('admin.general.no', 'No'); ?></option></select></label>
            <label><?php echo trans('admin.inventory.allow_backorder', 'Allow backorder'); ?><select id="product_allow_backorder" name="allow_backorder"><option value="0"><?php echo trans('admin.general.no', 'No'); ?></option><option value="1"><?php echo trans('admin.general.yes', 'Yes'); ?></option></select></label>
            <label><?php echo trans('admin.pricing.tax_rate', 'Tax rate'); ?> <input id="product_tax_rate" name="tax_rate" type="text" value="15.00"></label>
          </div>
        </section>
        <section data-section="dimensions" style="margin-top:12px;">
          <h4><?php echo trans('admin.dimensions.dimensions', 'Dimensions'); ?></h4>
          <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px;">
            <label><?php echo trans('admin.dimensions.weight', 'Weight'); ?> <input id="product_weight" name="weight" type="text"></label>
            <label><?php echo trans('admin.dimensions.length', 'Length'); ?> <input id="product_length" name="length" type="text"></label>
            <label><?php echo trans('admin.dimensions.width', 'Width'); ?> <input id="product_width" name="width" type="text"></label>
          </div>
          <div style="margin-top:8px;"><label><?php echo trans('admin.dimensions.height', 'Height'); ?> <input id="product_height" name="height" type="text"></label></div>
        </section>
        <section data-section="variants" style="margin-top:12px;display:none;">
          <h4><?php echo trans('admin.variants.variants', 'Variants'); ?></h4>
          <div id="variantControls" style="margin-bottom:8px;">
            <small class="muted"><?php echo trans('admin.variants.generate_from_attributes', 'Generate variants from attributes or add manually.'); ?></small>
            <div style="margin-top:8px;"><button id="generateVariantsBtn" type="button" class="btn"><?php echo trans('admin.variants.generate_variants', 'Generate Variants'); ?></button></div>
          </div>
          <div id="product_variants_list"></div>
        </section>
      </div>
      
      <aside>
        <section>
          <h4><?php echo trans('admin.media.images', 'Images / Media'); ?></h4>
          <input id="product_images_files" type="file" name="images[]" multiple accept="image/*,video/*">
          <button id="mediaStudioBtn" type="button" class="btn"><?php echo trans('admin.media.select_from_studio', 'Select from Studio'); ?></button>
          <div id="product_images_preview" style="display:flex;gap:8px;flex-wrap:wrap;margin-top:8px;"></div>
        </section>
        <section style="margin-top:12px;">
          <h4><?php echo trans('admin.categories.categories', 'Categories'); ?></h4>
          <div id="categoryTree" style="max-height:200px;overflow:auto;border:1px solid #e6eef0;border-radius:8px;padding:8px;">
            <ul id="categoryList" style="list-style:none;padding-left:0;margin:0;"></ul>
          </div>
          <input type="hidden" id="product_category_primary" name="category_id" value="">
          <input type="hidden" id="product_categories_hidden" name="categories" value="">
          <small class="muted"><?php echo trans('admin.categories.hierarchy_info', 'Select one primary category (radio) and multiple secondary (checkbox).'); ?></small>
        </section>
        <section style="margin-top:12px;">
          <h4><?php echo trans('admin.attributes.attributes', 'Attributes'); ?></h4>
          <div style="display:flex;gap:8px;margin-bottom:8px;">
            <select id="attr_select" style="flex:1;padding:8px;"></select>
            <button id="attr_add_btn" type="button" class="btn"><?php echo trans('admin.attributes.add_attribute', 'Add'); ?></button>
          </div>
          <div id="product_attributes_list"></div>
        </section>
        <section style="margin-top:12px;">
          <h4><?php echo trans('admin.translations.translations', 'Translations'); ?></h4>
          <div style="display:flex;gap:8px;margin-bottom:8px;">
            <button id="toggleTranslationsBtn" type="button" class="btn"><?php echo trans('admin.translations.toggle_translations', 'Toggle Translations'); ?></button>
            <button id="fillFromDefaultBtn" type="button" class="btn"><?php echo trans('admin.translations.fill_from_default', 'Fill from default name'); ?></button>
            <button id="addLangBtn" type="button" class="btn"><?php echo trans('admin.translations.add_language', 'Add Language'); ?></button>
            <small class="muted"><?php echo trans('admin.translations.each_language_panel', 'Each language panel contains full fields.'); ?></small>
          </div>
          <div id="product_translations_area" style="display:none;margin-top:8px;border:1px dashed #e6eef0;padding:8px;border-radius:8px;max-height:420px;overflow:auto;">
            <?php foreach ($languages as $lg): 
              $code = htmlspecialchars($lg['code']); 
              $lname = htmlspecialchars($lg['name']); 
            ?>
            <div class="tr-lang-panel" data-lang="<?php echo $code; ?>" style="border:1px solid #eef2f7;padding:8px;border-radius:6px;margin-bottom:8px;">
              <div style="display:flex;align-items:center;gap:8px;">
                <strong style="flex:1;"><?php echo $lname; ?> (<?php echo $code; ?>)</strong>
                <button type="button" class="btn small toggle-lang" data-lang="<?php echo $code; ?>"><?php echo trans('admin.translations.collapse', 'Collapse'); ?></button>
              </div>
              <div class="tr-lang-body" style="margin-top:8px;">
                <label style="display:block;margin-bottom:6px;"><?php echo trans('admin.general.name', 'Name'); ?> <input class="tr-name" data-lang="<?php echo $code; ?>" style="width:100%;"></label>
                <label style="display:block;margin-bottom:6px;"><?php echo trans('admin.translations.short_description', 'Short description'); ?> <input class="tr-short" data-lang="<?php echo $code; ?>" style="width:100%;"></label>
                <label style="display:block;margin-bottom:6px;"><?php echo trans('admin.general.description', 'Description'); ?> <textarea class="tr-desc" data-lang="<?php echo $code; ?>" rows="4" style="width:100%;"></textarea></label>
                <label style="display:block;margin-bottom:6px;"><?php echo trans('admin.translations.specifications', 'Specifications'); ?> <textarea class="tr-spec" data-lang="<?php echo $code; ?>" rows="3" style="width:100%;"></textarea></label>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-top:6px;">
                  <label><?php echo trans('admin.translations.meta_title', 'Meta title'); ?> <input class="tr-meta-title" data-lang="<?php echo $code; ?>" style="width:100%;"></label>
                  <label><?php echo trans('admin.translations.meta_keywords', 'Meta keywords'); ?> <input class="tr-meta-keys" data-lang="<?php echo $code; ?>" style="width:100%;"></label>
                </div>
                <label style="display:block;margin-top:6px;"><?php echo trans('admin.translations.meta_description', 'Meta description'); ?> <input class="tr-meta-desc" data-lang="<?php echo $code; ?>" style="width:100%;"></label>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        </section>
        <section style="margin-top:12px;text-align:right;">
          <button id="productDeleteBtn" class="btn danger" style="display:none;"><?php echo trans('admin.products.delete', 'Delete'); ?></button>
          <button id="productCancelBtn" class="btn"><?php echo trans('admin.products.cancel', 'Cancel'); ?></button>
          <button id="productSaveBtn" class="btn primary"><?php echo trans('admin.products.save', 'Save'); ?></button>
        </section>
      </aside>
    </form>
  </div>
</div>
<script>
  window.CSRF_TOKEN = "<?php echo $csrf; ?>";
  window.AVAILABLE_LANGUAGES = <?php echo json_encode($languages, JSON_UNESCAPED_UNICODE); ?>;
  window.CURRENT_USER = <?php echo json_encode($_SESSION['user'] ?? [], JSON_UNESCAPED_UNICODE); ?>;
  // تمرير الترجمات للـ JavaScript
  window.TRANSLATIONS = {
    products: {
      delete_confirm: "<?php echo trans('admin.products.delete_confirm', 'Delete product?'); ?>",
      delete_success: "<?php echo trans('admin.products.delete_success', 'Product deleted successfully'); ?>",
      save_success: "<?php echo trans('admin.products.save_success', 'Product saved successfully'); ?>",
      update_success: "<?php echo trans('admin.products.update_success', 'Product updated successfully'); ?>",
      loading: "<?php echo trans('admin.products.loading', 'Loading...'); ?>",
      saving: "<?php echo trans('admin.messages.saving', 'Saving...'); ?>",
      deleting: "<?php echo trans('admin.messages.deleting', 'Deleting...'); ?>",
      no_products: "<?php echo trans('admin.products.no_products', 'No products'); ?>"
    },
    general: {
      choose: "<?php echo trans('admin.general.choose', 'Choose'); ?>",
      yes: "<?php echo trans('admin.general.yes', 'Yes'); ?>",
      no: "<?php echo trans('admin.general.no', 'No'); ?>",
      select: "<?php echo trans('admin.general.select', 'Select'); ?>"
    },
    attributes: {
      choose_attribute: "<?php echo trans('admin.attributes.attribute', 'Attribute'); ?>",
      choose_value: "<?php echo trans('admin.attributes.value', 'Value'); ?>",
      custom_value: "<?php echo trans('admin.attributes.custom_value', 'custom value'); ?>",
      remove: "<?php echo trans('admin.attributes.remove', 'Remove'); ?>"
    },
    variants: {
      variant_sku: "<?php echo trans('admin.variants.variant_sku', 'SKU'); ?>",
      variant_stock: "<?php echo trans('admin.variants.variant_stock', 'Stock'); ?>",
      variant_price: "<?php echo trans('admin.variants.variant_price', 'Price'); ?>",
      variant_active: "<?php echo trans('admin.variants.variant_active', 'Active'); ?>",
      remove_variant: "<?php echo trans('admin.variants.remove_variant', 'Remove'); ?>",
      generate_from_attributes: "<?php echo trans('admin.variants.generate_from_attributes', 'Generate variants from attributes'); ?>"
    },
    translations: {
      language_code: "<?php echo trans('admin.translations.enter_language_code', 'Language code (e.g., ar)'); ?>",
      language_name: "<?php echo trans('admin.translations.enter_language_name', 'Language name (e.g., Arabic)'); ?>",
      collapse: "<?php echo trans('admin.translations.collapse', 'Collapse'); ?>",
      open: "<?php echo trans('admin.translations.open', 'Open'); ?>",
      remove_language: "<?php echo trans('admin.translations.remove_language', 'Remove'); ?>"
    },
    messages: {
      are_you_sure: "<?php echo trans('admin.messages.are_you_sure', 'Are you sure?'); ?>",
      network_error: "<?php echo trans('admin.messages.network_error', 'Network error'); ?>",
      server_error: "<?php echo trans('admin.messages.server_error', 'Server error'); ?>"
    },
    validation: {
      sku_required: "<?php echo trans('admin.validation.sku_required', 'SKU is required'); ?>",
      name_required: "<?php echo trans('admin.validation.name_required', 'Product name is required'); ?>"
    }
  };
</script>
<script src="/admin/assets/js/pages/products.js" defer></script>