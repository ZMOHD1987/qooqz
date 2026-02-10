(function () {
  'use strict';
  const API = '/api/products.php';
  const META_API = '/api/product_meta.php';
  const MEDIA_API = '/api/media.php';
  const CSRF = window.CSRF_TOKEN || '';
  const USER = window.CURRENT_USER || {};
  const PREF_LANG = USER.preferred_language || 'en';
  const IS_ADMIN = USER.role_id === 1;

  const notice = document.getElementById('productsNotice');
  const productsTbody = document.getElementById('productsTbody');
  const productsCount = document.getElementById('productsCount');
  const productSearch = document.getElementById('productSearch');
  const productRefresh = document.getElementById('productRefresh');
  const productNewBtn = document.getElementById('productNewBtn');
  const formWrap = document.getElementById('productFormWrap');
  const productForm = document.getElementById('productForm');
  const saveBtn = document.getElementById('productSaveBtn');
  const cancelBtn = document.getElementById('productCancelBtn');
  const deleteBtn = document.getElementById('productDeleteBtn');
  const errorsBox = document.getElementById('productErrors');

  const input_id = document.getElementById('product_id');
  const input_name = document.getElementById('product_name');
  const input_sku = document.getElementById('product_sku');
  const input_slug = document.getElementById('product_slug');
  const input_barcode = document.getElementById('product_barcode');
  const select_type = document.getElementById('product_type');
  const select_brand = document.getElementById('product_brand_id');
  const select_manufacturer = document.getElementById('product_manufacturer_id');
  const input_published_at = document.getElementById('product_published_at');
  const input_description = document.getElementById('product_description');
  const input_price = document.getElementById('product_price');
  const input_compare = document.getElementById('product_compare_at_price');
  const input_cost = document.getElementById('product_cost_price');
  const input_stock = document.getElementById('product_stock_quantity');
  const input_low = document.getElementById('product_low_stock_threshold');
  const select_stock_status = document.getElementById('product_stock_status');
  const select_manage_stock = document.getElementById('product_manage_stock');
  const select_allow_backorder = document.getElementById('product_allow_backorder');
  const input_tax = document.getElementById('product_tax_rate');
  const input_weight = document.getElementById('product_weight');
  const input_length = document.getElementById('product_length');
  const input_width = document.getElementById('product_width');
  const input_height = document.getElementById('product_height');
  const imagesInput = document.getElementById('product_images_files');
  const imagesPreview = document.getElementById('product_images_preview');
  const mediaStudioBtn = document.getElementById('mediaStudioBtn');
  const categoryList = document.getElementById('categoryList');
  const attrSelect = document.getElementById('attr_select');
  const attrAddBtn = document.getElementById('attr_add_btn');
  const attributesList = document.getElementById('product_attributes_list');
  const translationsArea = document.getElementById('product_translations_area');
  const toggleTranslationsBtn = document.getElementById('toggleTranslationsBtn');
  const fillFromDefaultBtn = document.getElementById('fillFromDefaultBtn');
  const addLangBtn = document.getElementById('addLangBtn');
  const generateVariantsBtn = document.getElementById('generateVariantsBtn');
  const variantsList = document.getElementById('product_variants_list');
  const dimensionsSection = document.querySelector('[data-section="dimensions"]');
  const variantsSection = document.querySelector('[data-section="variants"]');
  const inventorySection = document.querySelector('[data-section="inventory"]');

  let META = null;
  let MEDIA = [];

  function setNotice(msg, isError) {
    notice.textContent = msg || '';
    notice.style.color = isError ? '#b91c1c' : '#064e3b';
  }

  function showErrors(obj) {
    if (!errorsBox) return;
    if (!obj) { errorsBox.style.display = 'none'; errorsBox.innerHTML = ''; return; }
    let html = '<ul>';
    if (typeof obj === 'string') html += '<li>' + escapeHtml(obj) + '</li>';
    else for (let k in obj) html += '<li><strong>' + escapeHtml(k) + '</strong>: ' + escapeHtml(obj[k]) + '</li>';
    html += '</ul>';
    errorsBox.style.display = 'block';
    errorsBox.innerHTML = html;
    formWrap.scrollIntoView({ behavior: 'smooth' });
  }

  function escapeHtml(s) { return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

  function fetchJson(url, opts) {
    opts = opts || {}; opts.credentials = 'include';
    return fetch(url, opts).then(r => r.text().then(t => { try { return JSON.parse(t); } catch(e){ return { success:false, message:t }; } }));
  }

  function loadMeta(lang, categories = []) {
    lang = lang || PREF_LANG;
    let url = META_API + '?lang=' + encodeURIComponent(lang);
    if (categories.length) url += '&categories=' + encodeURIComponent(categories.join(','));
    return fetchJson(url).then(res => {
      if (!res.success) { setNotice('Failed to load metadata: ' + (res.message||''), true); return null; }
      META = res.data || {};
      populateMeta();
      return META;
    }).catch(err => { console.error(err); setNotice('Meta load failed', true); });
  }

  function populateMeta() {
    select_brand.innerHTML = '<option value="">— Choose —</option>';
    (META.brands || []).forEach(b => { 
      let o = document.createElement('option'); 
      o.value = b.id; 
      o.textContent = b.name_translated || b.slug || ('brand-'+b.id); 
      select_brand.appendChild(o); 
    });

    select_manufacturer.innerHTML = '<option value="">— Choose —</option>';
    (META.manufacturers || []).forEach(m => { 
      let o = document.createElement('option'); 
      o.value = m.id; 
      o.textContent = m.name || m.slug || ('manufacturer-'+m.id); 
      select_manufacturer.appendChild(o); 
    });

    attrSelect.innerHTML = '<option value="">— Choose Attribute —</option>';
    (META.attributes || []).forEach(a => { 
      let o = document.createElement('option'); 
      o.value = a.id; 
      o.textContent = a.name_translated || a.slug || ('attr-'+a.id); 
      o.dataset.isVariation = a.is_variation; 
      attrSelect.appendChild(o); 
    });

    buildCategoryTree(META.categories || []);
  }

  function buildCategoryTree(nodes, parent = null, level = 0) {
    nodes.forEach(node => {
      const li = document.createElement('li');
      li.style.marginLeft = (level * 20) + 'px';
      li.style.marginTop = '4px';
      
      const id = node.id;
      const name = node.name_translated || node.name || node.slug || node.id;
      
      const radio = document.createElement('input');
      radio.type = 'radio';
      radio.name = 'primary_category';
      radio.value = id;
      radio.id = 'cat_radio_' + id;
      radio.style.marginRight = '6px';
      
      const check = document.createElement('input');
      check.type = 'checkbox';
      check.value = id;
      check.id = 'cat_check_' + id;
      check.style.marginRight = '6px';
      
      const label = document.createElement('label');
      label.htmlFor = 'cat_check_' + id;
      label.textContent = name;
      label.style.cursor = 'pointer';
      
      li.appendChild(radio);
      li.appendChild(check);
      li.appendChild(label);
      
      if (node.children && node.children.length) {
        const toggle = document.createElement('button');
        toggle.type = 'button';
        toggle.textContent = '+';
        toggle.style.marginLeft = '8px';
        toggle.style.padding = '2px 6px';
        const childUl = document.createElement('ul');
        childUl.style.listStyle = 'none';
        childUl.style.paddingLeft = '0';
        childUl.style.marginTop = '4px';
        childUl.style.display = 'none';
        
        toggle.addEventListener('click', () => {
          if (childUl.style.display === 'none') {
            childUl.style.display = 'block';
            toggle.textContent = '-';
          } else {
            childUl.style.display = 'none';
            toggle.textContent = '+';
          }
        });
        
        li.appendChild(toggle);
        li.appendChild(childUl);
        
        buildCategoryTree(node.children, childUl, level + 1);
      }
      
      if (parent) {
        parent.appendChild(li);
      } else {
        categoryList.appendChild(li);
      }
    });
  }

  function updateAttributes() {
    const selected = Array.from(categoryList.querySelectorAll('input[type="checkbox"]:checked')).map(o => o.value);
    loadMeta(PREF_LANG, selected).then(() => {
      attributesList.innerHTML = '';
    });
  }

  function loadList() {
    setNotice('Loading products...');
    const q = productSearch && productSearch.value ? '&q=' + encodeURIComponent(productSearch.value) : '';
    fetchJson(API + '?format=json' + q).then(res => {
      if (!res.success) throw new Error(res.message || 'Failed');
      const list = Array.isArray(res.data) ? res.data : (res.data && Array.isArray(res.data.data) ? res.data.data : res.data || []);
      renderTable(list);
      setNotice('');
    }).catch(err => { console.error(err); setNotice('Load failed: '+(err.message||err), true); });
  }

  function renderTable(list) {
    if (!productsTbody) return;
    productsTbody.innerHTML = '';
    if (!list || list.length === 0) { 
      productsTbody.innerHTML = '<tr><td colspan="7" style="padding:12px;text-align:center;color:#6b7280;">No products</td></tr>'; 
      productsCount && (productsCount.textContent = '0'); 
      return; 
    }
    productsCount && (productsCount.textContent = String(list.length));
    list.forEach(p => {
      const tr = document.createElement('tr');
      tr.innerHTML = `<td style="padding:8px;">${escapeHtml(p.id)}</td>
        <td style="padding:8px;">${escapeHtml(p.title||p.name||'')}</td>
        <td style="padding:8px;">${escapeHtml(p.sku||'')}<br/><small>${escapeHtml(p.slug||'')}</small></td>
        <td style="padding:8px;">${escapeHtml(p.product_type||'')}</td>
        <td style="padding:8px;text-align:center;">${p.stock_quantity||0}</td>
        <td style="padding:8px;text-align:center;"><button class="btn toggleActiveBtn" data-id="${escapeHtml(p.id)}" data-active="${p.is_active?1:0}">${p.is_active?'Yes':'No'}</button></td>
        <td style="padding:8px;"><button class="btn editBtn" data-id="${escapeHtml(p.id)}">Edit</button> <button class="btn danger delBtn" data-id="${escapeHtml(p.id)}">Delete</button></td>`;
      productsTbody.appendChild(tr);
    });
    productsTbody.querySelectorAll('.editBtn').forEach(b => b.addEventListener('click', e => openEdit(b.dataset.id)));
    productsTbody.querySelectorAll('.delBtn').forEach(b => b.addEventListener('click', e => { if (confirm('Delete?')) doDelete(b.dataset.id); }));
    productsTbody.querySelectorAll('.toggleActiveBtn').forEach(b => b.addEventListener('click', e => toggleActive(b.dataset.id, b.dataset.active==1?0:1)));
  }

  function openNew() {
    productForm.reset && productForm.reset();
    input_id.value = 0;
    imagesPreview.innerHTML = '';
    attributesList.innerHTML = '';
    variantsList.innerHTML = '';
    categoryList.querySelectorAll('input').forEach(i => { i.checked = false; });
    document.querySelectorAll('.tr-lang-panel input, .tr-lang-panel textarea').forEach(i => i.value = '');
    showErrors(null);
    formWrap.style.display = 'block';
    deleteBtn.style.display = 'none';
    toggleFieldsByType('simple');
  }

  function openEdit(id) {
    setNotice('Loading product...');
    fetchJson(API + '?_fetch_row=1&id=' + encodeURIComponent(id)).then(res => {
      if (!res.success) throw new Error(res.message || 'Failed');
      populateForm(res.data || res.data.product || res.data);
      setNotice('');
    }).catch(err => { console.error(err); setNotice('Load error: '+(err.message||err), true); });
  }

  function populateForm(p) {
    input_id.value = p.id || 0;
    input_name.value = p.name || p.title || '';
    input_sku.value = p.sku || '';
    input_slug.value = p.slug || '';
    input_barcode && (input_barcode.value = p.barcode || '');
    select_type.value = p.product_type || 'simple';
    select_brand.value = p.brand_id || '';
    select_manufacturer.value = p.manufacturer_id || '';
    input_published_at.value = p.published_at ? p.published_at.slice(0,16) : '';
    input_description.value = p.description || '';
    input_price.value = (p.pricing && p.pricing.price) ? p.pricing.price : '';
    input_compare.value = (p.pricing && p.pricing.compare_at_price) ? p.pricing.compare_at_price : '';
    input_cost.value = (p.pricing && p.pricing.cost_price) ? p.pricing.cost_price : '';
    input_stock.value = p.stock_quantity || 0;
    input_low.value = p.low_stock_threshold || 5;
    select_stock_status.value = p.stock_status || 'in_stock';
    select_manage_stock.value = p.manage_stock ? 1 : 0;
    select_allow_backorder.value = p.allow_backorder ? 1 : 0;
    input_tax.value = p.tax_rate ?? '';
    input_weight.value = p.weight ?? '';
    input_length.value = p.length ?? '';
    input_width.value = p.width ?? '';
    input_height.value = p.height ?? '';

    categoryList.querySelectorAll('input').forEach(i => { i.checked = false; });
    if (p.categories && p.categories.length) {
      const primary = p.categories.find(c => c.is_primary == 1);
      if (primary) {
        const radio = categoryList.querySelector('input[type="radio"][value="' + primary.category_id + '"]');
        if (radio) {
          radio.checked = true;
          document.getElementById('product_category_primary').value = primary.category_id;
        }
      }
      p.categories.forEach(cat => {
        const check = categoryList.querySelector('input[type="checkbox"][value="' + cat.category_id + '"]');
        if (check) check.checked = true;
      });
    }

    attributesList.innerHTML = '';
    if (p.attributes && p.attributes.length) p.attributes.forEach(a => addAttributeItem(a.attribute_id, a.attribute_value_id, a.custom_value));

    if (p.translations) {
      Object.keys(p.translations).forEach(lang => {
        if (!document.querySelector(`.tr-lang-panel[data-lang="${lang}"]`)) addTranslationPanel(lang);
        const panel = document.querySelector(`.tr-lang-panel[data-lang="${lang}"]`);
        const entry = p.translations[lang] || {};
        panel.querySelector('.tr-name').value = entry.name || '';
        panel.querySelector('.tr-short').value = entry.short_description || '';
        panel.querySelector('.tr-desc').value = entry.description || '';
        panel.querySelector('.tr-spec').value = entry.specifications || '';
        panel.querySelector('.tr-meta-title').value = entry.meta_title || '';
        panel.querySelector('.tr-meta-desc').value = entry.meta_description || '';
        panel.querySelector('.tr-meta-keys').value = entry.meta_keywords || '';
      });
    }

    variantsList.innerHTML = '';
    if (p.variants && p.variants.length) p.variants.forEach(v => addVariantRow(v));

    imagesPreview.innerHTML = '';
    if (p.media && p.media.length) {
      p.media.forEach(m => {
        const url = m.file_url || m.image_url || m.url;
        if (!url) return;
        const d = document.createElement('div'); 
        d.style.width='90px'; d.style.height='90px'; d.style.border='1px solid #e6eef0'; 
        d.style.borderRadius='8px'; d.style.overflow='hidden'; d.style.display='flex'; 
        d.style.alignItems='center'; d.style.justifyContent='center';
        const im = document.createElement('img'); im.src = url; im.style.maxWidth='100%'; im.style.maxHeight='100%'; 
        d.appendChild(im); imagesPreview.appendChild(d);
      });
    }

    deleteBtn.style.display = p.id ? 'inline-block' : 'none';
    formWrap.style.display = 'block';
    toggleFieldsByType(select_type.value);
  }

  function toggleFieldsByType(type) {
    if (dimensionsSection) dimensionsSection.style.display = (type === 'digital' || type === 'bundle') ? 'none' : 'block';
    if (variantsSection) variantsSection.style.display = (type === 'variable') ? 'block' : 'none';
    if (inventorySection) inventorySection.style.display = (type !== 'bundle') ? 'block' : 'none';
  }

  function collectTranslations() {
    const out = {};
    document.querySelectorAll('.tr-lang-panel').forEach(panel => {
      const lang = panel.getAttribute('data-lang');
      const name = panel.querySelector('.tr-name').value.trim();
      const shortt = panel.querySelector('.tr-short').value.trim();
      const desc = panel.querySelector('.tr-desc').value.trim();
      const spec = panel.querySelector('.tr-spec').value.trim();
      const mt = panel.querySelector('.tr-meta-title').value.trim();
      const md = panel.querySelector('.tr-meta-desc').value.trim();
      const mk = panel.querySelector('.tr-meta-keys').value.trim();
      if (name || shortt || desc || spec || mt || md || mk) {
        out[lang] = { name, short_description: shortt, description: desc, specifications: spec, meta_title: mt, meta_description: md, meta_keywords: mk };
      }
    });
    document.getElementById('product_translations').value = JSON.stringify(out);
    return out;
  }

  function addTranslationPanel(langCode) {
    const langName = prompt('Language name (e.g., Arabic)', '');
    if (!langCode || !langName) return;
    const panel = document.createElement('div');
    panel.className = 'tr-lang-panel';
    panel.dataset.lang = langCode;
    panel.style.border = '1px solid #eef2f7';
    panel.style.padding = '8px';
    panel.style.borderRadius = '6px';
    panel.style.marginBottom = '8px';
    panel.innerHTML = `
      <div style="display:flex;align-items:center;gap:8px;">
        <strong style="flex:1;">${escapeHtml(langName)} (${escapeHtml(langCode)})</strong>
        <button type="button" class="btn small toggle-lang" data-lang="${escapeHtml(langCode)}">Collapse</button>
        <button type="button" class="btn small danger remove-lang" data-lang="${escapeHtml(langCode)}">Remove</button>
      </div>
      <div class="tr-lang-body" style="margin-top:8px;">
        <label style="display:block;margin-bottom:6px;">Name <input class="tr-name" style="width:100%;"></label>
        <label style="display:block;margin-bottom:6px;">Short description <input class="tr-short" style="width:100%;"></label>
        <label style="display:block;margin-bottom:6px;">Description <textarea class="tr-desc" rows="4" style="width:100%;"></textarea></label>
        <label style="display:block;margin-bottom:6px;">Specifications <textarea class="tr-spec" rows="3" style="width:100%;"></textarea></label>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-top:6px;">
          <label>Meta title <input class="tr-meta-title" style="width:100%;"></label>
          <label>Meta keywords <input class="tr-meta-keys" style="width:100%;"></label>
        </div>
        <label style="display:block;margin-top:6px;">Meta description <input class="tr-meta-desc" style="width:100%;"></label>
      </div>
    `;
    translationsArea.appendChild(panel);
  }

  function addAttributeItem(attrId, valId, custom) {
    const wrap = document.createElement('div'); 
    wrap.className = 'attr-item'; 
    wrap.style.display = 'flex'; 
    wrap.style.gap = '8px'; 
    wrap.style.marginBottom = '8px'; 
    wrap.style.alignItems = 'center';
    
    const sel = document.createElement('select'); 
    sel.style.minWidth='140px'; 
    sel.innerHTML = '<option value="">— Attribute —</option>';
    Array.from(attrSelect.options).forEach(o => { 
      if (!o.value) return; 
      const opt = o.cloneNode(true); 
      sel.appendChild(opt); 
    });
    if (attrId) sel.value = attrId;
    
    const valSel = document.createElement('select'); 
    valSel.style.minWidth='140px'; 
    valSel.innerHTML = '<option value="">— Value —</option>';
    
    const customInput = document.createElement('input'); 
    customInput.type = 'text'; 
    customInput.placeholder = 'custom value'; 
    customInput.style.flex = '1'; 
    if (custom) customInput.value = custom;
    
    const remove = document.createElement('button'); 
    remove.type='button'; 
    remove.className='btn'; 
    remove.textContent='Remove'; 
    remove.addEventListener('click', ()=>wrap.remove());
    
    sel.addEventListener('change', ()=>populateAttrValues(sel, valSel));
    populateAttrValues(sel, valSel);
    if (valId) valSel.value = valId;
    
    wrap.appendChild(sel); 
    wrap.appendChild(valSel); 
    wrap.appendChild(customInput); 
    wrap.appendChild(remove);
    attributesList.appendChild(wrap);
  }

  function populateAttrValues(sel, valSel) {
    valSel.innerHTML = '<option value="">— Value —</option>';
    if (!META || !sel.value) return;
    const attr = (META.attributes || []).find(a => String(a.id) === String(sel.value));
    if (!attr || !attr.values) return;
    attr.values.forEach(v => { 
      const o = document.createElement('option'); 
      o.value = v.id; 
      o.textContent = v.label_translated || v.value; 
      valSel.appendChild(o); 
    });
  }

  function collectAttributes() {
    const arr = [];
    attributesList.querySelectorAll('.attr-item').forEach(w => {
      const sel = w.querySelector('select'), valSel = w.querySelectorAll('select')[1], custom = w.querySelector('input[type="text"]');
      const aid = sel ? sel.value : ''; 
      const vid = valSel ? valSel.value : ''; 
      const cv = custom ? custom.value : '';
      if (!aid) return;
      arr.push({ attribute_id: parseInt(aid,10), attribute_value_id: vid ? parseInt(vid,10) : null, custom_value: cv });
    });
    document.getElementById('product_attributes').value = JSON.stringify(arr);
    return arr;
  }

  function collectCategories() {
    const arr = [];
    const primaryRadio = categoryList.querySelector('input[type="radio"]:checked');
    if (primaryRadio) {
      arr.push(parseInt(primaryRadio.value,10));
      document.getElementById('product_category_primary').value = primaryRadio.value;
    }
    
    const checks = categoryList.querySelectorAll('input[type="checkbox"]:checked');
    Array.from(checks).forEach(check => {
      if (primaryRadio && check.value === primaryRadio.value) return;
      arr.push(parseInt(check.value,10));
    });
    
    document.getElementById('product_categories_json').value = JSON.stringify(arr);
    return arr;
  }

  function addVariantRow(v = {}) {
    const row = document.createElement('div'); 
    row.className = 'variant-row'; 
    row.style.display = 'flex'; 
    row.style.gap = '8px'; 
    row.style.marginBottom = '8px';
    row.innerHTML = `
      <input type="text" placeholder="SKU" value="${escapeHtml(v.sku || '')}" style="width:120px;">
      <input type="number" placeholder="Stock" value="${v.stock_quantity || 0}" style="width:80px;">
      <input type="text" placeholder="Price" value="${escapeHtml(v.price || '')}" style="width:80px;">
      <label>Active <input type="checkbox" ${v.is_active !== 0 ? 'checked' : ''}></label>
      <button type="button" class="btn small danger remove-variant">Remove</button>
    `;
    row.querySelector('.remove-variant').addEventListener('click', () => row.remove());
    variantsList.appendChild(row);
  }

  function generateVariants() {
    const attrs = collectAttributes().filter(a => {
      const attrOpt = Array.from(attrSelect.options).find(o => o.value == a.attribute_id);
      return attrOpt && attrOpt.dataset.isVariation == '1' && a.attribute_value_id;
    });
    if (!attrs.length) return alert('No variation attributes selected');
    
    const attrValues = attrs.map(a => {
      const attr = META.attributes.find(at => at.id == a.attribute_id);
      return attr.values.map(v => ({ attrId: a.attribute_id, valId: v.id, label: v.value }));
    });
    
    function cartesian(...args) {
      return args.reduce((a, b) => a.flatMap(d => b.map(e => [d, e].flat())));
    }
    
    const combos = cartesian(...attrValues);
    variantsList.innerHTML = '';
    combos.forEach(combo => {
      const v = { sku: '', stock_quantity: 0, price: '', is_active: 1 };
      addVariantRow(v);
    });
  }

  function collectVariants() {
    const out = [];
    variantsList.querySelectorAll('.variant-row').forEach(row => {
      const inputs = row.querySelectorAll('input');
      const sku = inputs[0] ? inputs[0].value : '';
      const qty = inputs[1] ? parseInt(inputs[1].value||0,10) : 0;
      const price = inputs[2] ? inputs[2].value : '';
      const act = inputs[3] ? (inputs[3].checked ? 1 : 0) : 1;
      out.push({ sku: sku, stock_quantity: qty, price: price, is_active: act });
    });
    document.getElementById('product_variants').value = JSON.stringify(out);
    return out;
  }

  function saveProduct(e) {
    if (e) e.preventDefault();
    showErrors(null);
    setNotice('Saving...');
    
    collectTranslations();
    collectAttributes();
    collectVariants();
    collectCategories();
    
    const fd = new FormData(productForm);
    if (!fd.get('action')) fd.append('action','save');
    if (!fd.get('csrf_token') && CSRF) fd.append('csrf_token', CSRF);
    
    fd.set('translations', document.getElementById('product_translations').value || '{}');
    fd.set('attributes', document.getElementById('product_attributes').value || '[]');
    fd.set('variants', document.getElementById('product_variants').value || '[]');
    fd.set('categories', document.getElementById('product_categories_json').value || '[]');
    
    fetch(API, { method: 'POST', body: fd, credentials: 'include' })
      .then(r => r.text().then(t => ({ status: r.status, text: t })))
      .then(res => {
        let body;
        try { body = JSON.parse(res.text); } catch(e) { body = { success:false, message: res.text }; }
        if (res.status >= 200 && res.status < 300 && body.success) {
          setNotice(body.message || 'Saved');
          formWrap.style.display = 'none';
          loadList();
        } else {
          if (body && body.errors) showErrors(body.errors);
          else showErrors(body && body.message ? body.message : 'Save failed');
          setNotice((body && body.message) ? body.message : ('HTTP ' + res.status), true);
          console.error('Save failed', body);
        }
      }).catch(err => { console.error(err); setNotice('Network error: '+err.message, true); });
  }

  function doDelete(id) {
    if (!confirm('Delete product #' + id + '?')) return;
    const fd = new FormData(); 
    fd.append('action','delete'); 
    fd.append('id', id); 
    if (CSRF) fd.append('csrf_token', CSRF);
    fetch(API, { method:'POST', body: fd, credentials:'include' })
      .then(r=>r.json())
      .then(j=>{ 
        if (!j.success) setNotice(j.message||'Delete failed', true); 
        else { setNotice(j.message||'Deleted'); loadList(); } 
      }).catch(err => setNotice('Delete error: '+err.message, true));
  }

  function toggleActive(id, newState) {
    const fd = new FormData(); 
    fd.append('action','toggle_active'); 
    fd.append('id', id); 
    fd.append('is_active', newState?1:0); 
    if (CSRF) fd.append('csrf_token', CSRF);
    fetch(API, { method:'POST', body: fd, credentials:'include' })
      .then(r=>r.json())
      .then(j=>{ 
        if (!j.success) setNotice(j.message||'Update failed', true); 
        else { setNotice(j.message||'Updated'); loadList(); } 
      }).catch(err => setNotice('Error: '+err.message, true));
  }

  if (imagesInput) imagesInput.addEventListener('change', function () {
    imagesPreview.innerHTML = '';
    Array.from(this.files).forEach(f => {
      const reader = new FileReader();
      reader.onload = ev => {
        const d = document.createElement('div'); 
        d.style.width='90px'; d.style.height='90px'; d.style.border='1px solid #e6eef0'; 
        d.style.borderRadius='8px'; d.style.overflow='hidden'; d.style.display='flex'; 
        d.style.alignItems='center'; d.style.justifyContent='center';
        const im = document.createElement('img'); 
        im.src = ev.target.result; 
        im.style.maxWidth='100%'; im.style.maxHeight='100%'; 
        d.appendChild(im); imagesPreview.appendChild(d);
      };
      reader.readAsDataURL(f);
    });
  });

  function loadMedia() {
    fetchJson(MEDIA_API + '?user_id=' + USER.id).then(res => {
      if (res.success) MEDIA = res.data || [];
    });
  }

  function openMediaStudio() {
    const modal = document.createElement('div'); 
    modal.style.position = 'fixed'; modal.style.top = '0'; modal.style.left = '0'; 
    modal.style.width = '100%'; modal.style.height = '100%'; modal.style.background = 'rgba(0,0,0,0.5)'; 
    modal.style.display = 'flex'; modal.style.alignItems = 'center'; modal.style.justifyContent = 'center';
    
    const content = document.createElement('div'); 
    content.style.background = '#fff'; content.style.padding = '20px'; 
    content.style.borderRadius = '8px'; content.style.maxWidth = '600px'; 
    content.style.maxHeight = '80%'; content.style.overflow = 'auto';
    
    content.innerHTML = '<h3>Select from Studio</h3><div id="mediaList" style="display:flex;gap:8px;flex-wrap:wrap;"></div><button class="btn" onclick="this.parentNode.parentNode.remove()">Close</button>';
    
    MEDIA.forEach(m => {
      const d = document.createElement('div'); 
      d.style.width='90px'; d.style.height='90px'; d.style.border='1px solid #e6eef0'; d.style.cursor='pointer';
      const im = document.createElement('img'); 
      im.src = m.url; im.style.maxWidth='100%'; im.style.maxHeight='100%'; 
      d.appendChild(im);
      d.addEventListener('click', () => {
        const pd = d.cloneNode(true); imagesPreview.appendChild(pd);
        const hi = document.createElement('input'); 
        hi.type='hidden'; hi.name='existing_media[]'; hi.value = m.id; 
        productForm.appendChild(hi);
      });
      content.querySelector('#mediaList').appendChild(d);
    });
    
    modal.appendChild(content);
    document.body.appendChild(modal);
    modal.addEventListener('click', e => { if (e.target === modal) modal.remove(); });
  }

  if (toggleTranslationsBtn) toggleTranslationsBtn.addEventListener('click', function () {
    translationsArea.style.display = translationsArea.style.display === 'block' ? 'none' : 'block';
    this.textContent = translationsArea.style.display === 'block' ? 'Hide Translations' : 'Show Translations';
  });

  translationsArea.addEventListener('click', function (e) {
    if (e.target.classList.contains('toggle-lang')) {
      const lang = e.target.dataset.lang;
      const body = this.querySelector(`.tr-lang-panel[data-lang="${lang}"] .tr-lang-body`);
      body.style.display = body.style.display === 'none' ? 'block' : 'none';
      e.target.textContent = body.style.display === 'none' ? 'Open' : 'Collapse';
    } else if (e.target.classList.contains('remove-lang')) {
      const lang = e.target.dataset.lang;
      this.querySelector(`.tr-lang-panel[data-lang="${lang}"]`).remove();
    }
  });

  if (fillFromDefaultBtn) fillFromDefaultBtn.addEventListener('click', function () {
    const val = input_name.value || '';
    if (!val) return alert('Default name is empty');
    document.querySelectorAll('.tr-name').forEach(i => { if (!i.value) i.value = val; });
  });

  if (addLangBtn) addLangBtn.addEventListener('click', () => {
    const langCode = prompt('Language code (e.g., ar)', '');
    if (langCode) addTranslationPanel(langCode);
  });

  categoryList.addEventListener('change', updateAttributes);

  if (productNewBtn) productNewBtn.addEventListener('click', openNew);
  if (productRefresh) productRefresh.addEventListener('click', loadList);
  if (productSearch) productSearch.addEventListener('input', () => setTimeout(loadList, 350));
  if (saveBtn) saveBtn.addEventListener('click', saveProduct);
  if (cancelBtn) cancelBtn.addEventListener('click', () => formWrap.style.display = 'none');
  if (deleteBtn) deleteBtn.addEventListener('click', () => { const id = input_id.value; if (id) doDelete(id); });
  if (attrAddBtn) attrAddBtn.addEventListener('click', ()=>{ if (!attrSelect.value) return alert('Choose attribute first'); addAttributeItem(attrSelect.value, null, ''); });
  if (select_type) select_type.addEventListener('change', () => toggleFieldsByType(select_type.value));
  if (generateVariantsBtn) generateVariantsBtn.addEventListener('click', generateVariants);
  if (mediaStudioBtn) mediaStudioBtn.addEventListener('click', openMediaStudio);

  loadMeta().then(() => { loadList(); loadMedia(); });
})();