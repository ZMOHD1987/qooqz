<?php
// admin/fragments/products.php
// Updated fragment: includes hidden action input and ensures form fields match the JS expectations.
// Place at: admin/fragments/products.php

if (session_status() === PHP_SESSION_NONE) session_start();

// CSRF
if (empty($_SESSION['csrf_token'])) {
    try { $_SESSION['csrf_token'] = bin2hex(random_bytes(16)); } catch (Exception $e) { $_SESSION['csrf_token'] = bin2hex(openssl_random_pseudo_bytes(16)); }
}
$csrf = htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES);

// languages list (basic fallback; product_meta.php will provide full meta)
$langBase = __DIR__ . '/../../languages/admin';
$languages = [];
if (is_dir($langBase)) {
    foreach (glob($langBase . '/*.json') as $f) {
        $code = pathinfo($f, PATHINFO_FILENAME);
        $json = @json_decode(@file_get_contents($f), true) ?: [];
        $languages[] = ['code'=>$code,'name'=>$json['name'] ?? strtoupper($code),'direction'=>$json['direction'] ?? 'ltr'];
    }
}
if (empty($languages)) $languages = [['code'=>'en','name'=>'English','direction'=>'ltr']];
?>
<link rel="stylesheet" href="/admin/assets/css/pages/products.css">

<div id="adminProducts" class="admin-fragment" dir="<?php echo htmlspecialchars($languages[0]['direction'] ?? 'ltr'); ?>" style="max-width:1200px;margin:18px auto;font-family:Inter,Arial,Helvetica,sans-serif;">
  <header style="display:flex;align-items:center;gap:12px;margin-bottom:12px;">
    <h2 style="margin:0;">Products</h2>
    <div style="margin-left:auto;color:#6b7280;"><?php echo htmlspecialchars($_SESSION['user']['username'] ?? ($_SESSION['user_id'] ?? 'guest')); ?></div>
  </header>

  <div id="productsNotice" class="status" style="min-height:22px;margin-bottom:8px;color:#064e3b;"></div>

  <div class="tools" style="display:flex;align-items:center;gap:8px;margin-bottom:12px;">
    <input id="productSearch" type="search" placeholder="Search name, sku, slug..." style="padding:8px;border:1px solid #e6eef0;border-radius:999px;width:320px;">
    <button id="productRefresh" class="btn">Refresh</button>
    <button id="productNewBtn" class="btn primary">Create Product</button>
    <span style="margin-left:auto;color:#6b7280;">Total: <span id="productsCount">‑</span></span>
  </div>

  <div class="table-wrap" style="margin-bottom:16px;">
    <table id="productsTable" style="width:100%;min-width:900px;">
      <thead>
        <tr>
          <th style="width:70px">ID</th>
          <th>Title</th>
          <th style="width:160px">SKU / Slug</th>
          <th style="width:120px">Type</th>
          <th style="width:120px;text-align:center">Stock</th>
          <th style="width:120px;text-align:center">Active</th>
          <th style="width:240px">Actions</th>
        </tr>
      </thead>
      <tbody id="productsTbody"><tr><td colspan="7" style="padding:12px;text-align:center;color:#6b7280;">Loading...</td></tr></tbody>
    </table>
  </div>

  <div id="productFormWrap" class="form-wrap" style="display:none;">
    <div id="productErrors" style="display:none;color:#b91c1c;margin-bottom:8px;"></div>

    <form id="productForm" autocomplete="off" enctype="multipart/form-data" style="display:grid;grid-template-columns:1fr 380px;gap:14px;">
      <!-- required hidden/meta fields -->
      <input type="hidden" id="product_id" name="id" value="0">
      <input type="hidden" id="product_translations" name="translations" value="">
      <input type="hidden" id="product_attributes" name="attributes" value="">
      <input type="hidden" id="product_variants" name="variants" value="">
      <input type="hidden" id="product_categories_json" name="categories" value="">
      <!-- IMPORTANT: include action hidden so servers that rely on form submission receive it -->
      <input type="hidden" id="product_action" name="action" value="save">
      <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">

      <!-- Left column -->
      <div>
        <section>
          <h3>General</h3>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
            <label>Default Name (EN)<input id="product_name" name="name" type="text"></label>
            <label>SKU<input id="product_sku" name="sku" type="text"></label>
          </div>

          <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-top:8px;">
            <label>Slug<input id="product_slug" name="slug" type="text"></label>
            <label>Barcode<input id="product_barcode" name="barcode" type="text"></label>
          </div>

          <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-top:8px;">
            <label>Product Type
              <select id="product_type" name="product_type">
                <option value="simple">simple</option>
                <option value="variable">variable</option>
                <option value="digital">digital</option>
                <option value="bundle">bundle</option>
              </select>
            </label>
            <label>Brand<select id="product_brand_id" name="brand_id"></select></label>
          </div>

          <div style="margin-top:8px;">
            <label>Description<textarea id="product_description" name="description" rows="5"></textarea></label>
          </div>
        </section>

        <section style="margin-top:12px;">
          <h4>Pricing</h4>
          <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px;">
            <label>Price<input id="product_price" name="price" type="text"></label>
            <label>Compare at<input id="product_compare_at_price" name="compare_at_price" type="text"></label>
            <label>Cost price<input id="product_cost_price" name="cost_price" type="text"></label>
          </div>
        </section>

        <section style="margin-top:12px;">
          <h4>Inventory & Shipping</h4>
          <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px;">
            <label>Stock Qty<input id="product_stock_quantity" name="stock_quantity" type="number" value="0"></label>
            <label>Low Threshold<input id="product_low_stock_threshold" name="low_stock_threshold" type="number" value="5"></label>
            <label>Stock Status
              <select id="product_stock_status" name="stock_status"><option value="in_stock">in_stock</option><option value="out_of_stock">out_of_stock</option><option value="on_backorder">on_backorder</option></select>
            </label>
          </div>

          <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px;margin-top:8px;">
            <label>Manage stock<select id="product_manage_stock" name="manage_stock"><option value="1">Yes</option><option value="0">No</option></select></label>
            <label>Allow backorder<select id="product_allow_backorder" name="allow_backorder"><option value="0">No</option><option value="1">Yes</option></select></label>
            <label>Tax Rate (%)<input id="product_tax_rate" name="tax_rate" type="text" value="15.00"></label>
          </div>

          <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px;margin-top:8px;">
            <label>Weight<input id="product_weight" name="weight" type="text"></label>
            <label>Length<input id="product_length" name="length" type="text"></label>
            <label>Width<input id="product_width" name="width" type="text"></label>
          </div>
          <div style="margin-top:8px;"><label>Height<input id="product_height" name="height" type="text"></label></div>
        </section>

        <section style="margin-top:12px;">
          <h4>Variants</h4>
          <div id="variantControls">
            <small class="muted">Generate variants from attributes or add manually.</small>
            <div style="margin-top:8px;"><button id="generateVariantsBtn" type="button" class="btn">Generate Variants</button></div>
          </div>
          <div id="product_variants_list"></div>
        </section>
      </div>

      <!-- Right column -->
      <aside>
        <section style="margin-bottom:12px;">
          <h4>Images / Media</h4>
          <input id="product_images_files" type="file" name="images[]" multiple accept="image/*">
          <div id="product_images_preview" style="display:flex;gap:8px;flex-wrap:wrap;margin-top:8px;"></div>
          <div style="margin-top:8px;display:flex;gap:8px;">
            <button id="openStudioBtn" type="button" class="btn">Open Studio</button>
            <button id="uploadImagesBtn" type="button" class="btn primary">Upload</button>
          </div>
        </section>

        <section style="margin-bottom:12px;">
          <h4>Categories</h4>
          <label>Primary<select id="product_category_primary" name="category_id"></select></label>
          <label style="display:block;margin-top:8px;">Secondary (multi)<select id="product_categories" multiple style="width:100%;min-height:120px;padding:8px;"></select></label>
          <small class="muted">Selections saved as categories[] and aggregated JSON</small>
        </section>

        <section style="margin-bottom:12px;">
          <h4>Attributes</h4>
          <div style="display:flex;gap:8px;margin-bottom:8px;">
            <select id="attr_select" style="flex:1;padding:8px;border:1px solid #e6eef0;border-radius:8px;"></select>
            <button id="attr_add_btn" type="button" class="btn">Add</button>
          </div>
          <div id="product_attributes_list"></div>
        </section>

        <section style="margin-bottom:12px;">
          <h4>Translations</h4>
          <button id="toggleTranslationsBtn" class="btn">Toggle Translations</button>
          <div id="product_translations_area" style="display:none;margin-top:8px;border:1px dashed #e6eef0;padding:8px;border-radius:8px;">
            <table id="productTranslationsTable" style="width:100%;border-collapse:collapse;">
              <thead><tr><th>Lang</th><th>Title</th><th>Short description</th></tr></thead>
              <tbody>
                <?php foreach ($languages as $lg): $code = htmlspecialchars($lg['code']); ?>
                <tr data-lang="<?php echo $code; ?>">
                  <td style="padding:6px;"><?php echo htmlspecialchars($lg['name']); ?> (<?php echo $code; ?>)</td>
                  <td style="padding:6px"><input class="tr-name" data-lang="<?php echo $code; ?>" style="width:100%;"></td>
                  <td style="padding:6px"><input class="tr-short" data-lang="<?php echo $code; ?>" style="width:100%;"></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </section>

        <section style="margin-top:12px;text-align:right;">
          <button id="productDeleteBtn" class="btn danger" style="display:none;">Delete</button>
          <button id="productCancelBtn" class="btn">Cancel</button>
          <button id="productSaveBtn" class="btn primary">Save</button>
        </section>
      </aside>
    </form>
  </div>
</div>

<script>
  window.CSRF_TOKEN = "<?php echo $csrf; ?>";
</script>

<script src="/admin/assets/js/pages/products.js" defer></script>