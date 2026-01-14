/**
 * admin/assets/js/pages/products.js
 * Products Management UI - Works with MVC structure via /api/routes/products.php
 * Created from scratch following vendors.js pattern
 */

(function () {
  'use strict';

  // ---------- Configuration ----------
  const API = '/api/routes/products.php';
  const DEBUG = true;

  // ---------- Runtime state ----------
  const ADMIN_UI = window.ADMIN_UI || {};
  let CSRF_TOKEN = ADMIN_UI.csrf_token || window.CSRF_TOKEN || '';
  const CURRENT_USER = ADMIN_UI.user || window.CURRENT_USER || {};
  const STRINGS = ADMIN_UI.strings || {};
  const AVAILABLE_LANGS = window.AVAILABLE_LANGUAGES || ['en', 'ar'];
  const PREF_LANG = ADMIN_UI.lang || window.ADMIN_LANG || 'en';
  const IS_ADMIN = CURRENT_USER.role_id && Number(CURRENT_USER.role_id) === 1;

  if (DEBUG) {
    console.log('[products.js] CSRF Token:', CSRF_TOKEN ? 'Yes' : 'No');
    console.log('[products.js] Is Admin:', IS_ADMIN);
  }

  // ---------- Helpers ----------
  const $ = (id) => document.getElementById(id);
  const log = (...args) => { if (DEBUG) console.log('[products.js]', ...args); };

  function escapeHtml(str) {
    return String(str || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }

  function t(key, fallback = '') {
    return STRINGS[key] || fallback || key;
  }

  // ---------- DOM References ----------
  const refs = {
    tbody: $('productsTbody'),
    productsCount: $('productsCount'),
    productSearch: $('productSearch'),
    productRefresh: $('productRefresh'),
    productNewBtn: $('productNewBtn'),
    filterVendor: $('filterVendor'),
    filterStatus: $('filterStatus'),
    formSection: $('productFormSection'),
    form: $('productForm'),
    formTitle: $('productFormTitle'),
    saveBtn: $('productSaveBtn'),
    resetBtn: $('productResetBtn'),
    closeFormBtn: $('productCloseForm'),
    errorsBox: $('productFormErrors'),
    translationsArea: $('product_translations_area'),
    addLangBtn: $('productAddLangBtn')
  };

  // ---------- State ----------
  let products = [];
  let currentProductId = null;
  let translationLangs = [];

  // ---------- API Functions ----------
  async function apiCall(endpoint, options = {}) {
    try {
      const url = endpoint.startsWith('http') ? endpoint : API + (endpoint.startsWith('?') ? endpoint : '?' + endpoint);
      log('API Call:', url, options);
      
      const response = await fetch(url, {
        ...options,
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
          ...options.headers
        }
      });

      const data = await response.json();
      log('API Response:', data);
      
      if (!response.ok && !data.success) {
        throw new Error(data.message || 'Request failed');
      }
      
      return data;
    } catch (error) {
      log('API Error:', error);
      throw error;
    }
  }

  // ---------- Load Products ----------
  async function loadProducts() {
    try {
      const search = refs.productSearch?.value || '';
      const vendorId = refs.filterVendor?.value || '';
      const status = refs.filterStatus?.value || '';
      
      let query = '_fetch_all=1';
      if (search) query += `&search=${encodeURIComponent(search)}`;
      if (vendorId) query += `&vendor_id=${vendorId}`;
      if (status !== '') query += `&is_active=${status}`;

      const result = await apiCall(query);
      products = result.data || [];
      
      renderProductsTable();
      updateCount();
    } catch (error) {
      showError('Failed to load products: ' + error.message);
    }
  }

  // ---------- Render Table ----------
  function renderProductsTable() {
    if (!refs.tbody) return;
    
    if (products.length === 0) {
      refs.tbody.innerHTML = '<tr><td colspan="8">No products found</td></tr>';
      return;
    }

    refs.tbody.innerHTML = products.map(p => {
      const name = (p.translations && p.translations[PREF_LANG]?.name) || p.slug || '';
      const price = (p.pricing && p.pricing[0]?.price) || 'N/A';
      const status = p.is_active ? '<span class="badge badge-success">Active</span>' : '<span class="badge badge-secondary">Inactive</span>';
      
      return `
        <tr data-id="${p.id}">
          <td>${p.id}</td>
          <td>${escapeHtml(p.sku)}</td>
          <td>${escapeHtml(name)}</td>
          <td>${p.vendor_id || ''}</td>
          <td>${escapeHtml(price)}</td>
          <td>${p.stock_quantity || 0}</td>
          <td>${status}</td>
          <td>
            <button class="btn-edit" onclick="editProduct(${p.id})">Edit</button>
            <button class="btn-delete" onclick="deleteProduct(${p.id})">Delete</button>
          </td>
        </tr>
      `;
    }).join('');
  }

  // ---------- Update Count ----------
  function updateCount() {
    if (refs.productsCount) {
      refs.productsCount.textContent = `Total: ${products.length} products`;
    }
  }

  // ---------- Show Error ----------
  function showError(message) {
    if (refs.errorsBox) {
      refs.errorsBox.innerHTML = `<div class="error">${escapeHtml(message)}</div>`;
      refs.errorsBox.style.display = 'block';
      setTimeout(() => {
        refs.errorsBox.style.display = 'none';
      }, 5000);
    } else {
      alert(message);
    }
  }

  // ---------- Show Success ----------
  function showSuccess(message) {
    if (refs.errorsBox) {
      refs.errorsBox.innerHTML = `<div class="success">${escapeHtml(message)}</div>`;
      refs.errorsBox.style.display = 'block';
      setTimeout(() => {
        refs.errorsBox.style.display = 'none';
      }, 3000);
    }
  }

  // ---------- Open Form ----------
  function openForm(product = null) {
    currentProductId = product ? product.id : null;
    
    if (refs.formTitle) {
      refs.formTitle.textContent = product ? 'Edit Product' : 'Add Product';
    }
    
    if (refs.form) {
      refs.form.reset();
    }
    
    if (product) {
      $('product_id').value = product.id;
      $('product_sku').value = product.sku || '';
      $('product_slug').value = product.slug || '';
      $('product_barcode').value = product.barcode || '';
      $('product_product_type').value = product.product_type || 'simple';
      $('product_stock_quantity').value = product.stock_quantity || 0;
      $('product_is_active').value = product.is_active ? '1' : '0';
      
      // Load translations
      if (product.translations) {
        translationLangs = Object.keys(product.translations);
        renderTranslations(product.translations);
      }
    } else {
      translationLangs = [PREF_LANG];
      renderTranslations({});
    }
    
    if (refs.formSection) {
      refs.formSection.style.display = 'block';
    }
  }

  // ---------- Close Form ----------
  function closeForm() {
    if (refs.formSection) {
      refs.formSection.style.display = 'none';
    }
    currentProductId = null;
    translationLangs = [];
  }

  // ---------- Render Translations ----------
  function renderTranslations(translations = {}) {
    if (!refs.translationsArea) return;
    
    refs.translationsArea.innerHTML = translationLangs.map(lang => {
      const trans = translations[lang] || {};
      return `
        <div class="translation-block" data-lang="${lang}">
          <h4>Language: ${lang}</h4>
          <div class="form-group">
            <label>Name</label>
            <input type="text" name="translations[${lang}][name]" value="${escapeHtml(trans.name || '')}" />
          </div>
          <div class="form-group">
            <label>Description</label>
            <textarea name="translations[${lang}][description]" rows="3">${escapeHtml(trans.description || '')}</textarea>
          </div>
          <button type="button" class="btn-remove" onclick="removeLang('${lang}')">Remove</button>
        </div>
      `;
    }).join('');
  }

  // ---------- Add Language ----------
  function addLanguage() {
    const availableLangs = AVAILABLE_LANGS.filter(l => !translationLangs.includes(l));
    if (availableLangs.length === 0) {
      alert('All languages added');
      return;
    }
    
    const newLang = availableLangs[0];
    translationLangs.push(newLang);
    renderTranslations();
  }

  // ---------- Remove Language ----------
  window.removeLang = function(lang) {
    translationLangs = translationLangs.filter(l => l !== lang);
    renderTranslations();
  };

  // ---------- Save Product ----------
  async function saveProduct(e) {
    e.preventDefault();
    
    try {
      const formData = new FormData(refs.form);
      const data = {};
      
      formData.forEach((value, key) => {
        if (key.startsWith('translations[')) {
          // Parse translation fields
          const match = key.match(/translations\[([^\]]+)\]\[([^\]]+)\]/);
          if (match) {
            const [, lang, field] = match;
            if (!data.translations) data.translations = {};
            if (!data.translations[lang]) data.translations[lang] = {};
            data.translations[lang][field] = value;
          }
        } else {
          data[key] = value;
        }
      });
      
      const isEdit = currentProductId !== null;
      const endpoint = isEdit ? `_update=1&id=${currentProductId}` : '_create=1';
      
      const result = await apiCall(endpoint, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
      });
      
      showSuccess(result.message || (isEdit ? 'Product updated' : 'Product created'));
      closeForm();
      loadProducts();
    } catch (error) {
      showError('Save failed: ' + error.message);
    }
  }

  // ---------- Edit Product ----------
  window.editProduct = async function(id) {
    try {
      const result = await apiCall(`_fetch_row=1&id=${id}`);
      if (result.success && result.data) {
        openForm(result.data);
      }
    } catch (error) {
      showError('Failed to load product: ' + error.message);
    }
  };

  // ---------- Delete Product ----------
  window.deleteProduct = async function(id) {
    if (!confirm('Are you sure you want to delete this product?')) return;
    
    try {
      const result = await apiCall(`_delete=1&id=${id}`, { method: 'POST' });
      showSuccess(result.message || 'Product deleted');
      loadProducts();
    } catch (error) {
      showError('Delete failed: ' + error.message);
    }
  };

  // ---------- Event Listeners ----------
  if (refs.productNewBtn) {
    refs.productNewBtn.addEventListener('click', () => openForm());
  }

  if (refs.productRefresh) {
    refs.productRefresh.addEventListener('click', loadProducts);
  }

  if (refs.productSearch) {
    refs.productSearch.addEventListener('input', debounce(loadProducts, 300));
  }

  if (refs.filterVendor) {
    refs.filterVendor.addEventListener('change', loadProducts);
  }

  if (refs.filterStatus) {
    refs.filterStatus.addEventListener('change', loadProducts);
  }

  if (refs.form) {
    refs.form.addEventListener('submit', saveProduct);
  }

  if (refs.resetBtn) {
    refs.resetBtn.addEventListener('click', () => refs.form.reset());
  }

  if (refs.closeFormBtn) {
    refs.closeFormBtn.addEventListener('click', closeForm);
  }

  if (refs.addLangBtn) {
    refs.addLangBtn.addEventListener('click', addLanguage);
  }

  // ---------- Debounce Utility ----------
  function debounce(func, wait) {
    let timeout;
    return function(...args) {
      clearTimeout(timeout);
      timeout = setTimeout(() => func.apply(this, args), wait);
    };
  }

  // ---------- Initialize ----------
  document.addEventListener('DOMContentLoaded', () => {
    log('Products page initialized');
    loadProducts();
  });

})();
