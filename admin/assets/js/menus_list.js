// htdocs/admin/assets/js/menus_list.js
// Page-specific JavaScript for the Categories / Menus listing.
// - i18n-safe: uses tKey() to read translations from window.ADMIN_UI
// - Matches markup in fragments/menus_list.php (ids/classes: #btnAddNew, .edit-btn, .delete-btn, #categoryForm, etc.)
// - Uses CSRF from hidden input or window.CSRF_TOKEN
// - Robust JSON handling and emits language change event handling
// - Save as UTF-8 without BOM.

(function () {
  'use strict';

  // Utilities
  function $(sel, ctx) { return (ctx || document).querySelector(sel); }
  function $all(sel, ctx) { return Array.prototype.slice.call((ctx || document).querySelectorAll(sel)); }
  function escapeHtml(s) { return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;'); }

  // Translation resolver - tries _admin.resolveKey then nested ADMIN_UI
  function tKey(key, fallback) {
    if (!key) return fallback || '';
    try {
      if (window._admin && typeof window._admin.resolveKey === 'function') {
        var v = window._admin.resolveKey(key);
        if (v !== null) return v;
      }
      var ui = window.ADMIN_UI || {};
      var parts = key.split('.');
      var cur = ui;
      for (var i = 0; i < parts.length; i++) {
        if (cur && Object.prototype.hasOwnProperty.call(cur, parts[i])) cur = cur[parts[i]];
        else { cur = null; break; }
      }
      if (typeof cur === 'string') return cur;
      // fallback lookup in flat maps
      if (ui.strings && ui.strings[key]) return ui.strings[key];
      if (ui.buttons && ui.buttons[key]) return ui.buttons[key];
    } catch (e) { /* ignore */ }
    return fallback || key;
  }

  // Read CSRF token from page hidden input or global
  function getCsrf() {
    var el = document.querySelector('input[name="csrf_token"]');
    if (el && el.value) return el.value;
    if (window.CSRF_TOKEN) return window.CSRF_TOKEN;
    if (window.Admin && typeof Admin.getCsrf === 'function') return Admin.getCsrf();
    return '';
  }

  // Robust JSON parse
  function safeJsonParse(txt) {
    if (typeof txt !== 'string') return null;
    txt = txt.replace(/^\uFEFF/, '').trim();
    if (!txt) return null;
    try { return JSON.parse(txt); } catch (e) {}
    var first = txt.indexOf('{'), last = txt.lastIndexOf('}');
    if (first !== -1 && last !== -1 && last > first) {
      try { return JSON.parse(txt.substring(first, last + 1)); } catch (e) {}
    }
    return null;
  }

  // Create/update table row using translations for buttons/status
  function createRowElement(d) {
    var statusText = (d && (d.is_active == 1 || d.is_active === true || String(d.is_active) === '1'))
      ? tKey('status.active', 'نشط')
      : tKey('status.inactive', 'غير نشط');
    var editLabel = tKey('actions.edit', 'تعديل');
    var deleteLabel = tKey('actions.delete', 'حذف');

    var tr = document.createElement('tr');
    tr.setAttribute('data-id', d.id || '');
    tr.innerHTML = ''
      + '<td style="padding:10px;border-bottom:1px solid #eee;">' + escapeHtml(d.id || '') + '</td>'
      + '<td style="padding:10px;border-bottom:1px solid #eee;">' + (d.image_url ? '<img src="' + escapeHtml(d.image_url) + '" style="width:64px;height:44px;object-fit:cover;border-radius:4px;" alt="">' : '—') + '</td>'
      + '<td style="padding:10px;border-bottom:1px solid #eee;">' + escapeHtml(d.name || '') + '</td>'
      + '<td style="padding:10px;border-bottom:1px solid #eee;">' + escapeHtml(d.slug || '') + '</td>'
      + '<td style="padding:10px;border-bottom:1px solid #eee;">' + escapeHtml((d.description || '').toString().slice(0,120)) + '</td>'
      + '<td style="padding:10px;border-bottom:1px solid #eee;">' + escapeHtml(statusText) + '</td>'
      + '<td style="padding:10px;border-bottom:1px solid #eee;">'
        + '<button class="edit-btn btn small" data-id="' + escapeHtml(d.id || '') + '">' + escapeHtml(editLabel) + '</button> '
        + '<button class="delete-btn btn danger small" data-id="' + escapeHtml(d.id || '') + '">' + escapeHtml(deleteLabel) + '</button>'
        + '</td>';
    return tr;
  }

  // Refresh static translated bits on page (headers, add/search button, placeholder, count)
  function refreshStaticTranslations(root) {
    root = root || document;
    var addBtn = $('#btnAddNew', root) || $('#btnAddCategory', root);
    if (addBtn) addBtn.textContent = tKey('buttons.add_new', addBtn.textContent || '+ إضافة جديد');

    var searchBtn = (root.querySelector && root.querySelector('#searchForm button[type="submit"]')) || null;
    if (searchBtn) searchBtn.textContent = tKey('buttons.search', searchBtn.textContent || 'بحث');

    var searchInput = root.querySelector && root.querySelector('#searchForm input[name="q"]');
    if (searchInput) {
      var ph = tKey('categories.list.search_placeholder') || tKey('search_placeholder') || searchInput.getAttribute('placeholder') || '';
      searchInput.placeholder = ph;
    }

    // table headers (attempt to update ones with data-i18n)
    $all('[data-i18n]', root).forEach(function (el) {
      var key = el.getAttribute('data-i18n');
      if (!key) return;
      var val = tKey(key);
      if (val) el.textContent = val;
    });

    // update count text
    var cntEl = $('#countText', root);
    if (cntEl) {
      var n = (cntEl.textContent || '').replace(/[^\d]/g, '') || cntEl.dataset.count || '';
      var tpl = tKey('categories.list.title') || tKey('table.count') || cntEl.textContent || 'عدد التصنيفات: {count}';
      if (tpl.indexOf && tpl.indexOf('{count}') !== -1) cntEl.textContent = tpl.replace('{count}', n);
      else cntEl.textContent = tpl + (n ? ' ' + n : '');
    }
  }

  // Main initializer exported
  function initMenusList(root) {
    root = root || document;

    // Ensure selectors in our fragment (some markup uses different ids)
    var addBtn = $('#btnAddNew', root) || $('#btnAddCategory', root);
    if (addBtn) {
      addBtn.addEventListener('click', function () {
        // open inline form in fragment: show container if exists
        var inline = $('#inlineFormContainer', root);
        if (inline) {
          // reset form for add
          var form = $('#categoryForm', root);
          if (form) { form.reset(); var fid = $('#form_id', form); if (fid) fid.value = '0'; }
          inline.style.display = 'block';
          try { $('#form_name', inline).focus(); } catch (e) {}
        } else {
          // fallback to modal/page - try AdminUI
          if (window.AdminUI && typeof window.AdminUI.openModal === 'function') {
            AdminUI.openModal('/admin/fragments/menu_edit.php'); // optional endpoint
          } else {
            window.location.href = '/admin/fragments/menus_list.php';
          }
        }
      });
    }

    // Delegated handler: edit buttons (existing fragment uses .edit-btn and inline form)
    (function attachEditDelegation() {
      var tbody = $('#tableBody', root);
      if (!tbody) return;
      tbody.addEventListener('click', function (ev) {
        var btn = ev.target.closest && ev.target.closest('.edit-btn');
        if (!btn) return;
        var id = btn.getAttribute('data-id') || (btn.closest('tr') && btn.closest('tr').dataset.id);
        if (!id) return alert(tKey('error','Invalid id'));
        // Use existing inline edit mechanism in fragment: fetch row and populate
        fetch('/admin/fragments/menus_list.php?_fetch_row=1&id=' + encodeURIComponent(id), { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
          .then(function (res) { return res.text(); })
          .then(function (txt) {
            var json = safeJsonParse(txt);
            if (!json || !json.success || !json.data) { console.error('fetch_row invalid', txt); return alert(tKey('error','تعذر جلب بيانات السجل')); }
            var d = json.data;
            var form = $('#categoryForm', root);
            if (!form) return;
            if ($('#form_id', form)) $('#form_id', form).value = d.id || 0;
            if ($('#form_name', form)) $('#form_name', form).value = d.name || '';
            if ($('#form_slug', form)) $('#form_slug', form).value = d.slug || '';
            if ($('#form_description', form)) $('#form_description', form).value = d.description || '';
            if ($('#form_parent_id', form)) $('#form_parent_id', form).value = d.parent_id || 0;
            if ($('#form_sort_order', form)) $('#form_sort_order', form).value = d.sort_order || 0;
            if ($('#form_is_active', form)) $('#form_is_active', form).checked = !!d.is_active;
            if ($('#form_image_url', form) && d.image_url) {
              $('#form_image_url', form).value = d.image_url;
              var prev = $('#form_image_preview', form); if (prev) { prev.src = d.image_url; prev.style.display = ''; }
            }
            // translations: if fragment supports #translationsContainer add panels
            if (d.translations && typeof d.translations === 'object') {
              var tc = $('#translationsContainer', form);
              if (tc) { tc.innerHTML = ''; for (var lang in d.translations) if (Object.prototype.hasOwnProperty.call(d.translations, lang)) {
                // create panels the same way as fragment expects (reuse createTranslationPanel if defined globally)
                if (window.createTranslationPanel && typeof window.createTranslationPanel === 'function') {
                  tc.appendChild(window.createTranslationPanel(lang, d.translations[lang]));
                } else {
                  // basic injection
                  var wrapper = document.createElement('div');
                  wrapper.innerHTML = '<strong>' + escapeHtml(lang.toUpperCase()) + '</strong>';
                  tc.appendChild(wrapper);
                }
              } }
            }
            // show form
            var inline = $('#inlineFormContainer', root);
            if (inline) inline.style.display = 'block';
            // focus name
            try { $('#form_name', root).focus(); } catch (e) {}
          }).catch(function (err) { console.error('edit fetch failed', err); alert(tKey('error','خطأ في جلب السجل')); });
      });
    })();

    // Delegated delete (.delete-btn)
    (function attachDeleteDelegation() {
      var tbody = $('#tableBody', root);
      if (!tbody) return;
      tbody.addEventListener('click', function (ev) {
        var btn = ev.target.closest && ev.target.closest('.delete-btn');
        if (!btn) return;
        var id = btn.getAttribute('data-id');
        if (!id) return alert(tKey('error','Invalid id'));
        var msg = tKey('confirm_delete') || tKey('messages.confirm_delete') || 'Are you sure?';
        if (!confirm(msg)) return;
        var fd = new FormData();
        fd.append('action', 'delete');
        fd.append('id', id);
        fd.append('csrf_token', getCsrf());
        btn.disabled = true;
        fetch('/admin/menu_actions.php', { method: 'POST', credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: fd })
          .then(function (res) { return res.text(); })
          .then(function (txt) {
            var json = safeJsonParse(txt);
            if (!json) { console.error('delete invalid', txt); alert(tKey('error','استجابة غير صالحة من الخادم')); return; }
            if (json.success) {
              var tr = document.querySelector('tr[data-id="' + id + '"]');
              if (tr) tr.remove();
              // update count display
              var cntEl = $('#countText', root);
              if (cntEl) {
                var cnt = parseInt((cntEl.textContent || '').replace(/[^\d]/g,''),10) || 0;
                cntEl.textContent = tKey('categories.list.title','عدد التصنيفات') + ': ' + Math.max(0, cnt - 1);
              }
              toast(tKey('deleted_success','تم الحذف بنجاح'));
            } else {
              alert(json.message || tKey('error','فشل الحذف'));
            }
          }).catch(function (err) { console.error('delete request error', err); alert(tKey('error','خطأ أثناء الحذف')); })
          .finally(function () { btn.disabled = false; });
      });
    })();

    // Form submit handling (if inline form present): keep inline behavior minimal here
    (function attachFormHandler() {
      var form = $('#categoryForm', root);
      if (!form) return;
      form.addEventListener('submit', function (ev) {
        ev.preventDefault();
        var submitBtn = form.querySelector('[type="submit"]');
        if (submitBtn) submitBtn.disabled = true;

        var fd = new FormData(form);
        if (!fd.get('csrf_token')) fd.set('csrf_token', getCsrf());
        if (!fd.get('action')) fd.set('action', 'save');

        fetch('/admin/menu_actions.php', { method: 'POST', credentials: 'same-origin', headers: {'X-Requested-With':'XMLHttpRequest'}, body: fd })
          .then(function (res) { return res.text(); })
          .then(function (txt) {
            var json = safeJsonParse(txt);
            if (!json) { console.error('save invalid json', txt); alert(tKey('error','استجابة غير صالحة من الخادم')); return; }
            if (!json.success) { alert(json.message || tKey('error','فشل الحفظ')); return; }

            // update or add row
            var savedId = json.id ? String(json.id) : (form.querySelector('#form_id') ? form.querySelector('#form_id').value : '');
            if (form.querySelector('#form_id')) form.querySelector('#form_id').value = savedId;

            var rowData = (json.data && typeof json.data === 'object') ? json.data : {
              id: savedId,
              name: form.querySelector('#form_name') ? form.querySelector('#form_name').value : '',
              slug: form.querySelector('#form_slug') ? form.querySelector('#form_slug').value : '',
              description: form.querySelector('#form_description') ? form.querySelector('#form_description').value : '',
              image_url: form.querySelector('#form_image_url') ? form.querySelector('#form_image_url').value : '',
              is_active: form.querySelector('#form_is_active') && form.querySelector('#form_is_active').checked ? 1 : 0
            };

            var existing = document.querySelector('tr[data-id="' + (rowData.id || '') + '"]');
            var newTr = createRowElement(rowData);
            if (existing) existing.parentNode.replaceChild(newTr, existing);
            else {
              var tbody = $('#tableBody', root);
              if (tbody) tbody.insertBefore(newTr, tbody.firstChild);
            }

            // If translations were returned/updated, keep them in the form (we don't reset)
            toast(tKey('saved_success','تم الحفظ بنجاح'));
          }).catch(function (err) { console.error('save failed', err); alert(tKey('error','خطأ أثناء الحفظ')); })
          .finally(function () { if (submitBtn) submitBtn.disabled = false; });
      });
    })();

    // Apply static translations on init
    refreshStaticTranslations(root);

    // Listen for language changes (Admin.emit or custom event)
    if (window.Admin && typeof window.Admin.on === 'function') {
      Admin.on('language:changed', function () { refreshStaticTranslations(root); });
    } else {
      window.addEventListener('language:changed', function () { refreshStaticTranslations(root); });
    }

    console.info('menus_list.js initialized');
  }

  // Expose
  window.MenusList = window.MenusList || {};
  window.MenusList.init = initMenusList;

  // Auto-init when included directly
  if (document.readyState !== 'loading') initMenusList(document);
  else document.addEventListener('DOMContentLoaded', function () { initMenusList(document); });

})();