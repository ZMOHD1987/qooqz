<?php
// htdocs/admin/fragments/menus_list.php
// Menus / Categories admin fragment (i18n via translation files, English defaults)
// - List, search (AJAX), add, edit, delete (AJAX)
// - Inline form with translations panels
// - All UI text obtained via JS from window.ADMIN_UI using tKey()
// - Save as UTF-8 without BOM

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../require_permission.php';
require_login_and_permission('manage_categories');

require_once __DIR__ . '/../../api/config/db.php';
$mysqli = connectDB();
if (!$mysqli || $mysqli->connect_error) {
    http_response_code(500);
    echo 'Database connection error';
    exit;
}

// CSRF token
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
$CSRF = $_SESSION['csrf_token'];

// ---------- AJAX: fetch single row as JSON (including translations) ----------
if (!empty($_GET['_fetch_row']) && $_GET['_fetch_row'] == '1' && !empty($_GET['id'])) {
    header('Content-Type: application/json; charset=utf-8');
    $id = (int) $_GET['id'];
    $stmt = $mysqli->prepare("SELECT id, parent_id, name, slug, description, image_url, sort_order, is_active FROM categories WHERE id = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res && $row = $res->fetch_assoc()) {
            // fetch translations
            $translations = [];
            $tq = $mysqli->prepare("SELECT language_code,name,slug,description,meta_title,meta_description,meta_keywords FROM category_translations WHERE category_id = ?");
            if ($tq) {
                $tq->bind_param('i', $id);
                $tq->execute();
                $tres = $tq->get_result();
                while ($tr = $tres->fetch_assoc()) {
                    $translations[$tr['language_code']] = [
                        'name' => $tr['name'],
                        'slug' => $tr['slug'],
                        'description' => $tr['description'],
                        'meta_title' => $tr['meta_title'],
                        'meta_description' => $tr['meta_description'],
                        'meta_keywords' => $tr['meta_keywords']
                    ];
                }
                $tq->close();
            }
            $row['translations'] = $translations;
            echo json_encode(['success' => true, 'data' => $row], JSON_UNESCAPED_UNICODE);
        } else {
            echo json_encode(['success' => false, 'message' => 'Not found'], JSON_UNESCAPED_UNICODE);
        }
        $stmt->close();
    } else {
        echo json_encode(['success' => false, 'message' => 'Query failed'], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

// ---------- Normal page: list rows and render HTML ----------
$q = trim((string)($_GET['q'] ?? ''));

// Build query safely
$sql = "SELECT id, parent_id, name, slug, description, image_url, sort_order, is_active FROM categories";
$params = [];
$types = '';
if ($q !== '') {
    $sql .= " WHERE name LIKE ? OR slug LIKE ? OR description LIKE ?";
    $like = '%' . $q . '%';
    $params = [$like, $like, $like];
    $types = 'sss';
}
$sql .= " ORDER BY parent_id ASC, sort_order ASC, id DESC";

$rows = [];
$stmt = $mysqli->prepare($sql);
if ($stmt) {
    if ($types) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) $rows[] = $r;
    $stmt->close();
}

// languages list for translations panel
$langs = ['ar' => ['name' => 'Arabic'], 'en' => ['name' => 'English']];
$resl = $mysqli->query("SELECT code, name FROM languages ORDER BY code ASC");
if ($resl) {
    $langs = [];
    while ($l = $resl->fetch_assoc()) $langs[$l['code']] = $l;
}

$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
if (!$isAjax) require_once __DIR__ . '/../includes/header.php';
?>
<link rel="stylesheet" href="/admin/assets/css/pages/menus_list.css">

<section class="container" style="padding:20px;">
  <h1 data-i18n="categories.list.title">Categories</h1>

  <div style="margin-bottom:16px;display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;">
    <form id="searchForm" method="get" style="display:flex;gap:8px;align-items:center;">
      <input id="searchInput" type="search" name="q" value="<?php echo htmlspecialchars($q, ENT_QUOTES, 'UTF-8'); ?>"
             data-i18n-placeholder="categories.list.search_placeholder"
             placeholder="<?php echo htmlspecialchars('Search name, slug, description...', ENT_QUOTES, 'UTF-8'); ?>"
             style="padding:6px 8px;border:1px solid #ddd;border-radius:6px;">
      <button class="btn" type="submit" data-i18n="buttons.search">Search</button>
      <?php if ($q): ?><a class="btn" href="/admin/fragments/menus_list.php" data-i18n="categories.list.clear">Clear</a><?php endif; ?>
    </form>

    <div style="display:flex;gap:8px;align-items:center;">
      <button id="btnAddNew" class="btn btn-add" data-i18n="buttons.add_new">+ Add new</button>
    </div>
  </div>

  <p id="countText" data-i18n="table.count">Categories <?php echo count($rows); ?></p>

  <div style="overflow-x:auto;">
    <table style="width:100%;border-collapse:collapse;">
      <thead style="background:#f8f9fa;">
        <tr>
          <th style="padding:10px;border-bottom:2px solid #ddd;">ID</th>
          <th style="padding:10px;border-bottom:2px solid #ddd;" data-i18n="categories.list.image">Image</th>
          <th style="padding:10px;border-bottom:2px solid #ddd;" data-i18n="categories.list.name">Name</th>
          <th style="padding:10px;border-bottom:2px solid #ddd;" data-i18n="categories.list.slug">Slug</th>
          <th style="padding:10px;border-bottom:2px solid #ddd;" data-i18n="categories.list.description">Description</th>
          <th style="padding:10px;border-bottom:2px solid #ddd;" data-i18n="categories.list.status">Status</th>
          <th style="padding:10px;border-bottom:2px solid #ddd;" data-i18n="categories.list.actions">Actions</th>
        </tr>
      </thead>
      <tbody id="tableBody">
        <?php if (empty($rows)): ?>
          <tr><td colspan="7" style="text-align:center;padding:28px;color:#666;" data-i18n="categories.list.empty">No categories</td></tr>
        <?php else: foreach ($rows as $r): ?>
          <tr data-id="<?php echo (int)$r['id']; ?>">
            <td style="padding:10px;border-bottom:1px solid #eee;"><?php echo (int)$r['id']; ?></td>
            <td style="padding:10px;border-bottom:1px solid #eee;">
              <?php if (!empty($r['image_url'])): ?>
                <img src="<?php echo htmlspecialchars($r['image_url'], ENT_QUOTES, 'UTF-8'); ?>" style="width:64px;height:44px;object-fit:cover;border-radius:4px;" alt="">
              <?php else: ?>—<?php endif; ?>
            </td>
            <td style="padding:10px;border-bottom:1px solid #eee;"><?php echo htmlspecialchars($r['name'], ENT_QUOTES, 'UTF-8'); ?></td>
            <td style="padding:10px;border-bottom:1px solid #eee;"><?php echo htmlspecialchars($r['slug'], ENT_QUOTES, 'UTF-8'); ?></td>
            <td style="padding:10px;border-bottom:1px solid #eee;"><?php echo htmlspecialchars(mb_strimwidth($r['description'] ?? '', 0, 120, '...'), ENT_QUOTES, 'UTF-8'); ?></td>
            <td style="padding:10px;border-bottom:1px solid #eee;" data-i18n="<?php echo $r['is_active'] ? 'status.active' : 'status.inactive'; ?>">
              <?php echo $r['is_active'] ? 'Active' : 'Inactive'; ?>
            </td>
            <td style="padding:10px;border-bottom:1px solid #eee;">
              <button class="edit-btn btn small" data-id="<?php echo (int)$r['id']; ?>" data-i18n="actions.edit">Edit</button>
              <button class="delete-btn btn danger small" data-id="<?php echo (int)$r['id']; ?>" data-i18n="actions.delete">Delete</button>
            </td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>

  <!-- inline form container -->
  <div id="inlineFormContainer" style="display:none;margin-top:20px;padding:16px;background:#fff;border-radius:8px;border:1px solid #eee;">
    <h2 id="formTitle" data-i18n="categories.form.title_create">Create Category</h2>

    <form id="categoryForm" onsubmit="return false;">
      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($CSRF, ENT_QUOTES, 'UTF-8'); ?>">
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="id" id="form_id" value="0">

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
        <div>
          <label data-i18n="categories.form.name">Name *</label>
          <input type="text" name="name" id="form_name" required style="width:100%;padding:8px;border:1px solid #ccc;border-radius:4px;">
        </div>
        <div>
          <label data-i18n="categories.form.slug">Slug</label>
          <input type="text" name="slug" id="form_slug" style="width:100%;padding:8px;border:1px solid #ccc;border-radius:4px;">
        </div>
        <div>
          <label data-i18n="categories.form.parent">Parent</label>
          <select name="parent_id" id="form_parent_id" style="width:100%;padding:8px;border:1px solid #ccc;border-radius:4px;">
            <option value="0">-- Root --</option>
            <?php foreach ($rows as $p): ?>
              <option value="<?php echo (int)$p['id']; ?>"><?php echo htmlspecialchars($p['name'], ENT_QUOTES, 'UTF-8'); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label data-i18n="categories.form.sort_order">Sort order</label>
          <input type="number" name="sort_order" id="form_sort_order" value="0" style="width:100%;padding:8px;border:1px solid #ccc;border-radius:4px;">
        </div>
      </div>

      <div style="margin-top:12px;">
        <label data-i18n="categories.form.description">Description</label>
        <textarea name="description" id="form_description" rows="4" style="width:100%;padding:8px;border:1px solid #ccc;border-radius:4px;"></textarea>
      </div>

      <div style="margin-top:12px;display:flex;gap:16px;align-items:center;">
        <div style="width:180px;height:120px;border:1px dashed #ddd;display:flex;align-items:center;justify-content:center;background:#fafafa;">
          <img id="form_image_preview" src="" style="display:none;max-width:100%;max-height:100%;object-fit:contain;border-radius:4px;" alt="">
          <span id="form_image_placeholder" style="color:#999;" data-i18n="categories.form.image">No image</span>
        </div>
        <div>
          <input type="hidden" name="image_url" id="form_image_url">
          <button type="button" id="btnChooseImage" class="btn" data-i18n="labels.choose_image">Choose / Upload image</button>
          <button type="button" id="btnClearImage" class="btn" style="display:none;margin-top:8px;" data-i18n="labels.remove_image">Remove</button>
        </div>
      </div>

      <!-- Translations UI -->
      <div style="margin-top:16px;border:1px solid #eee;padding:12px;border-radius:6px;">
        <strong data-i18n="labels.translations">Translations</strong>
        <div style="display:flex;gap:8px;align-items:center;margin-top:8px;">
          <select id="langSelect" style="padding:6px;">
            <?php foreach ($langs as $code => $ld): ?>
              <option value="<?php echo htmlspecialchars($code, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(strtoupper($code).' — '.$ld['name'], ENT_QUOTES, 'UTF-8'); ?></option>
            <?php endforeach; ?>
          </select>
          <button type="button" id="btnAddLang" class="btn" data-i18n="labels.translation_add">Add translation</button>
        </div>
        <div id="translationsContainer" style="margin-top:12px;"></div>
        <div style="margin-top:8px;color:#666;font-size:13px;" data-i18n="labels.translation_note">When saving, fields will be sent as translations[code][field]</div>
      </div>

      <div style="margin-top:12px;">
        <label><input type="checkbox" name="is_active" id="form_is_active" value="1" checked> <span data-i18n="categories.form.is_active">Active</span></label>
      </div>

      <div style="margin-top:12px;text-align:right;">
        <button type="submit" id="btnSave" class="btn btn-save" data-i18n="buttons.save">Save</button>
        <button type="button" id="btnCancelForm" class="btn btn-cancel" data-i18n="buttons.cancel">Cancel</button>
      </div>
    </form>
  </div>
</section>

<script>
// menus_list fragment - JS (i18n via translation files only)
(function () {
  'use strict';

  // Helpers
  function qs(sel, root) { return (root || document).querySelector(sel); }
  function qsa(sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); }
  function escapeHtml(s) { return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;'); }
  function truncate(s, n) { s = String(s || ''); return s.length > n ? s.slice(0,n) + '...' : s; }

  // Resolve translation key using AdminI18n/_admin.resolveKey or window.ADMIN_UI
  function tKey(key, fallback) {
    if (!key) return fallback || '';
    try {
      if (window.AdminI18n && typeof AdminI18n.tKey === 'function') {
        return AdminI18n.tKey(key, fallback);
      }
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
      if (ui.strings && ui.strings[key]) return ui.strings[key];
      if (ui.buttons && ui.buttons[key]) return ui.buttons[key];
    } catch (e) {}
    return fallback || key;
  }

  // Robust JSON parse
  function safeJsonParse(txt) {
    if (typeof txt !== 'string') return null;
    txt = txt.replace(/^\uFEFF/, '').trim();
    if (!txt) return null;
    try { return JSON.parse(txt); } catch (e) {}
    var first = txt.indexOf('{'), last = txt.lastIndexOf('}');
    if (first !== -1 && last !== -1 && last > first) {
      try { return JSON.parse(txt.substring(first, last+1)); } catch (e) {}
    }
    return null;
  }

  // Create row with translations-only labels
  function createRowElement(d) {
    var statusText = (d && (d.is_active == 1 || d.is_active === true || String(d.is_active) === '1'))
      ? tKey('status.active', 'Active')
      : tKey('status.inactive', 'Inactive');
    var editLabel = tKey('actions.edit', 'Edit');
    var deleteLabel = tKey('actions.delete', 'Delete');

    var tr = document.createElement('tr');
    tr.setAttribute('data-id', d.id || '');
    tr.innerHTML = ''
      + '<td style="padding:10px;border-bottom:1px solid #eee;">' + escapeHtml(d.id || '') + '</td>'
      + '<td style="padding:10px;border-bottom:1px solid #eee;">' + (d.image_url ? '<img src="' + escapeHtml(d.image_url) + '" style="width:64px;height:44px;object-fit:cover;border-radius:4px;" alt="">' : '—') + '</td>'
      + '<td style="padding:10px;border-bottom:1px solid #eee;">' + escapeHtml(d.name || '') + '</td>'
      + '<td style="padding:10px;border-bottom:1px solid #eee;">' + escapeHtml(d.slug || '') + '</td>'
      + '<td style="padding:10px;border-bottom:1px solid #eee;">' + escapeHtml(truncate(d.description || '', 120)) + '</td>'
      + '<td style="padding:10px;border-bottom:1px solid #eee;">' + escapeHtml(statusText) + '</td>'
      + '<td style="padding:10px;border-bottom:1px solid #eee;">'
        + '<button class="edit-btn btn small" data-id="' + escapeHtml(d.id || '') + '" data-i18n="actions.edit">' + escapeHtml(editLabel) + '</button> '
        + '<button class="delete-btn btn danger small" data-id="' + escapeHtml(d.id || '') + '" data-i18n="actions.delete">' + escapeHtml(deleteLabel) + '</button>'
        + '</td>';
    return tr;
  }

  // Refresh static translations (placeholders, buttons, count, headers)
  function refreshStaticTranslations(root) {
    root = root || document;
    // Apply generic applyTranslations if available
    try {
      if (window.AdminI18n && typeof AdminI18n.translateFragment === 'function') {
        AdminI18n.translateFragment(root);
      } else if (window._admin && typeof window._admin.applyTranslations === 'function') {
        window._admin.applyTranslations(root);
      }
    } catch (e) { console.warn('applyTranslations failed', e); }

    // Fallback / additional fixes (only set text if translation exists)
    function safeSet(el, key, fallback) {
      if (!el) return;
      var v = tKey(key, null);
      if (v && v !== key) {
        // preserve icons
        Array.prototype.slice.call(el.childNodes).forEach(function(n){ if (n.nodeType===Node.TEXT_NODE && n.textContent.trim()) n.parentNode.removeChild(n); });
        el.appendChild(document.createTextNode(' ' + v));
        el.setAttribute('data-i18n', key);
      } else if (fallback) {
        // set english fallback but don't override if element already had meaningful text
        if (!(el.textContent || '').trim()) el.appendChild(document.createTextNode(' ' + fallback));
      }
    }

    safeSet(qs('#btnAddNew', root), 'buttons.add_new', '+ Add new');
    safeSet(qs('#searchForm button[type="submit"]', root), 'buttons.search', 'Search');
    var searchInput = qs('#searchForm input[name="q"]', root);
    if (searchInput) {
      var ph = tKey('categories.list.search_placeholder', 'Search name, slug, description...');
      if (ph) searchInput.setAttribute('placeholder', ph);
    }

    var cntEl = qs('#countText', root);
    if (cntEl) {
      var n = cntEl.dataset.count ? cntEl.dataset.count : (cntEl.textContent || '').replace(/[^\d]/g,'');
      var tpl = tKey('table.count', 'Categories {count}');
      if (tpl && tpl.indexOf('{count}') !== -1) cntEl.textContent = tpl.replace('{count}', n);
      else cntEl.textContent = tpl + (n ? ' ' + n : '');
    }

    // Translate any buttons inside table rows
    qsa('#tableBody tr').forEach(function (tr) {
      var edit = tr.querySelector('.edit-btn');
      if (edit) safeSet(edit, 'actions.edit', 'Edit');
      var del = tr.querySelector('.delete-btn');
      if (del) safeSet(del, 'actions.delete', 'Delete');
      var tds = tr.querySelectorAll('td');
      if (tds && tds[5]) {
        var txt = (tds[5].textContent || '').trim();
        if (txt) {
          if (/Active/i.test(txt)) tds[5].textContent = tKey('status.active','Active');
          else if (/Inactive/i.test(txt)) tds[5].textContent = tKey('status.inactive','Inactive');
        }
      }
    });
  }

  // Toast
  function toast(msg, timeout) {
    timeout = timeout || 1500;
    var el = document.createElement('div');
    el.textContent = msg;
    el.style.cssText = 'position:fixed;right:20px;bottom:20px;background:#0b6ea8;color:#fff;padding:8px 12px;border-radius:6px;z-index:20000;';
    document.body.appendChild(el);
    setTimeout(function(){ el.style.opacity = '0'; el.style.transition = 'opacity .4s'; setTimeout(function(){ if (el.parentNode) el.parentNode.removeChild(el); },400); }, timeout);
  }

  // Variables for DOM nodes
  var inline = qs('#inlineFormContainer');
  var form = qs('#categoryForm');
  var tableBody = qs('#tableBody');
  var addBtn = qs('#btnAddNew');
  var cancelBtn = qs('#btnCancelForm');
  var chooseBtn = qs('#btnChooseImage');
  var clearBtn = qs('#btnClearImage');
  var langSelect = qs('#langSelect');
  var btnAddLang = qs('#btnAddLang');
  var translationsContainer = qs('#translationsContainer');
  var searchForm = qs('#searchForm');
  var countText = qs('#countText');

  // Translation panel creator (keeps names translations[code][field])
  function createTranslationPanel(code, data) {
    data = data || {};
    var panel = document.createElement('div');
    panel.className = 'trans-panel';
    panel.dataset.lang = code;
    panel.style.cssText = 'border:1px dashed #ddd;padding:8px;margin-bottom:8px;border-radius:6px;';
    panel.innerHTML = ''
      + '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;"><strong>' + escapeHtml(code.toUpperCase()) + '</strong><button type="button" class="btn small danger remove-lang" data-i18n="actions.delete">' + tKey('actions.delete','Delete') + '</button></div>'
      + '<div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">'
      + '<label>' + tKey('categories.form.name','Name') + '<input type="text" name="translations['+escapeHtml(code)+'][name]" value="'+(data.name?escapeHtml(data.name):'')+'" style="width:100%;padding:6px;margin-top:4px;"></label>'
      + '<label>' + tKey('categories.form.slug','Slug') + '<input type="text" name="translations['+escapeHtml(code)+'][slug]" value="'+(data.slug?escapeHtml(data.slug):'')+'" style="width:100%;padding:6px;margin-top:4px;"></label>'
      + '</div>'
      + '<label style="display:block;margin-top:8px;">' + tKey('categories.form.description','Description') + '<textarea name="translations['+escapeHtml(code)+'][description]" rows="2" style="width:100%;padding:6px;margin-top:4px;">'+(data.description?escapeHtml(data.description):'')+'</textarea></label>'
      + '<label style="display:block;margin-top:8px;">' + tKey('forms.meta_title','Meta Title') + '<input type="text" name="translations['+escapeHtml(code)+'][meta_title]" value="'+(data.meta_title?escapeHtml(data.meta_title):'')+'" style="width:100%;padding:6px;margin-top:4px;"></label>'
      + '<label style="display:block;margin-top:8px;">' + tKey('forms.meta_description','Meta Description') + '<textarea name="translations['+escapeHtml(code)+'][meta_description]" rows="2" style="width:100%;padding:6px;margin-top:4px;">'+(data.meta_description?escapeHtml(data.meta_description):'')+'</textarea></label>';
    return panel;
  }

  function addTranslation(code, data) {
    if (!translationsContainer) return;
    if (!code) return;
    if (translationsContainer.querySelector('.trans-panel[data-lang="'+code+'"]')) return alert(tKey('validation_failed','Translation already exists'));
    translationsContainer.appendChild(createTranslationPanel(code, data || {}));
  }

  // translation add/remove handlers
  if (btnAddLang) btnAddLang.addEventListener('click', function () {
    var code = (langSelect && langSelect.value) ? langSelect.value : '';
    if (!code) return;
    addTranslation(code, {});
  });

  if (translationsContainer) {
    translationsContainer.addEventListener('click', function (e) {
      if (e.target.classList.contains('remove-lang')) {
        var panel = e.target.closest('.trans-panel');
        if (!panel) return;
        if (!confirm(tKey('confirm','Delete translation?'))) return;
        panel.remove();
      }
    });
  }

  // Add new handler (show inline form)
  if (addBtn) addBtn.addEventListener('click', function () {
    if (qs('#form_id')) qs('#form_id').value = '0';
    if (form) form.reset();
    if (translationsContainer) translationsContainer.innerHTML = '';
    if (inline) inline.style.display = 'block';
    try { qs('#form_name').focus(); } catch (e) {}
  });

  if (cancelBtn) cancelBtn.addEventListener('click', function () {
    if (!form) return;
    form.reset();
    if (translationsContainer) translationsContainer.innerHTML = '';
    if (inline) inline.style.display = 'none';
  });

  // Delegated edit/delete handling
  if (tableBody) {
    tableBody.addEventListener('click', function (ev) {
      var edit = ev.target.closest('.edit-btn');
      if (edit) {
        var id = edit.getAttribute('data-id') || (edit.closest('tr') && edit.closest('tr').dataset.id);
        if (!id) return alert(tKey('error','Record id missing'));
        fetch('/admin/fragments/menus_list.php?_fetch_row=1&id=' + encodeURIComponent(id), { credentials: 'same-origin', headers: {'X-Requested-With':'XMLHttpRequest'} })
          .then(function (res) { return res.text(); })
          .then(function (txt) {
            var json = safeJsonParse(txt);
            if (!json) { console.error('fetch_row invalid JSON', txt); return alert(tKey('error','Failed to fetch record')); }
            if (!(json && json.success && json.data)) { console.warn('fetch_row returned', json); return alert(tKey('error','Failed to fetch record')); }
            var d = json.data;
            if (qs('#form_id')) qs('#form_id').value = d.id || 0;
            if (qs('#form_name')) qs('#form_name').value = d.name || '';
            if (qs('#form_slug')) qs('#form_slug').value = d.slug || '';
            if (qs('#form_description')) qs('#form_description').value = d.description || '';
            if (qs('#form_parent_id')) qs('#form_parent_id').value = d.parent_id || 0;
            if (qs('#form_sort_order')) qs('#form_sort_order').value = d.sort_order || 0;
            if (qs('#form_is_active')) qs('#form_is_active').checked = !!d.is_active;
            if (d.image_url && qs('#form_image_url')) {
              qs('#form_image_url').value = d.image_url;
              if (qs('#form_image_preview')) { qs('#form_image_preview').src = d.image_url; qs('#form_image_preview').style.display = ''; }
              if (qs('#form_image_placeholder')) qs('#form_image_placeholder').style.display = 'none';
              if (clearBtn) clearBtn.style.display = '';
            }
            if (translationsContainer) {
              translationsContainer.innerHTML = '';
              if (d.translations && typeof d.translations === 'object') {
                for (var lang in d.translations) {
                  if (Object.prototype.hasOwnProperty.call(d.translations, lang)) addTranslation(lang, d.translations[lang]);
                }
              }
            }
            if (inline) inline.style.display = 'block';
            try { qs('#form_name').focus(); } catch (e) {}
          }).catch(function (err) { console.error('fetch_row error', err); alert(tKey('error','Error fetching record')); });
        return;
      }

      var del = ev.target.closest('.delete-btn');
      if (del) {
        var id = del.getAttribute('data-id');
        if (!id) return alert(tKey('error','Record id missing'));
        if (!confirm(tKey('confirm_delete','Confirm delete?'))) return;
        var fd = new FormData();
        fd.append('action', 'delete');
        fd.append('id', id);
        fd.append('csrf_token', '<?php echo htmlspecialchars($CSRF, ENT_QUOTES, 'UTF-8'); ?>');

        fetch('/admin/menu_actions.php', { method: 'POST', credentials: 'same-origin', headers: {'X-Requested-With':'XMLHttpRequest'}, body: fd })
          .then(function (res) { return res.text(); })
          .then(function (txt) {
            var json = safeJsonParse(txt);
            if (!json) { console.error('delete invalid JSON', txt); return alert(tKey('error','Invalid server response')); }
            if (json && json.success) {
              var tr = document.querySelector('tr[data-id="'+id+'"]');
              if (tr) tr.remove();
              toast(tKey('deleted_success','Deleted successfully'));
              try {
                var cnt = parseInt((countText.textContent||'').replace(/[^\d]/g,''),10)||0;
                countText.textContent = tKey('table.count','Categories {count}').replace('{count}', Math.max(0,cnt-1));
              } catch (e) {}
            } else {
              alert((json && json.message) ? json.message : tKey('error','Delete failed'));
            }
          }).catch(function (err) { console.error('delete request failed', err); alert(tKey('error','Error deleting')); });
      }
    });
  }

  // Image chooser (popup)
  if (chooseBtn) {
    chooseBtn.addEventListener('click', function () {
      var ownerId = qs('#form_id') ? qs('#form_id').value : 0;
      // prefer ImageStudio.open if available
      if (window.ImageStudio && typeof window.ImageStudio.open === 'function') {
        ImageStudio.open({ ownerType: 'category', ownerId: ownerId }).then(function(url) {
          if (url) {
            if (qs('#form_image_url')) qs('#form_image_url').value = url;
            if (qs('#form_image_preview')) { qs('#form_image_preview').src = url; qs('#form_image_preview').style.display = ''; }
            if (qs('#form_image_placeholder')) qs('#form_image_placeholder').style.display = 'none';
            if (clearBtn) clearBtn.style.display = '';
          }
        }).catch(function(err){ console.error('ImageStudio.open failed', err); alert(tKey('error','Image studio error')); });
        return;
      }
      var url = '/admin/fragments/images.php?owner_type=category&owner_id=' + encodeURIComponent(ownerId) + '&_standalone=1';
      var w = window.open(url, 'ImageStudio', 'width=1000,height=700,scrollbars=yes');
      if (!w) alert(tKey('error','Popup blocked — allow popups for this site'));
    });
  }

  window.addEventListener('message', function (e) {
    try {
      if (!e || !e.data) return;
      var d = e.data;
      if ((d.type === 'image_selected' || d.type === 'ImageStudio:selected') && d.url) {
        if (qs('#form_image_url')) qs('#form_image_url').value = d.url;
        if (qs('#form_image_preview')) { qs('#form_image_preview').src = d.url; qs('#form_image_preview').style.display = ''; }
        if (qs('#form_image_placeholder')) qs('#form_image_placeholder').style.display = 'none';
        if (clearBtn) clearBtn.style.display = '';
      }
    } catch (err) { console.warn('message handler error', err); }
  }, false);

  if (clearBtn) clearBtn.addEventListener('click', function () {
    if (qs('#form_image_url')) qs('#form_image_url').value = '';
    if (qs('#form_image_preview')) qs('#form_image_preview').style.display = 'none';
    if (qs('#form_image_placeholder')) qs('#form_image_placeholder').style.display = '';
    clearBtn.style.display = 'none';
  });

  // Save form (AJAX)
  if (form) {
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var nameVal = (qs('#form_name') || { value: '' }).value.trim();
      if (!nameVal) { alert(tKey('validation_failed','Name is required')); qs('#form_name').focus(); return; }

      var fd = new FormData(form);
      if (!fd.get('action')) fd.set('action', 'save');
      if (!fd.get('csrf_token')) fd.set('csrf_token', '<?php echo htmlspecialchars($CSRF, ENT_QUOTES, 'UTF-8'); ?>');

      var submitBtn = form.querySelector('[type="submit"]');
      if (submitBtn) submitBtn.disabled = true;

      fetch('/admin/menu_actions.php', { method: 'POST', credentials: 'same-origin', headers: {'X-Requested-With':'XMLHttpRequest'}, body: fd })
        .then(function (res) { return res.text(); })
        .then(function (txt) {
          var json = safeJsonParse(txt);
          if (!json) { console.error('save invalid JSON', txt); alert(tKey('error','Invalid server response — check console')); return; }
          if (!(json && json.success)) { alert((json && json.message) ? json.message : tKey('error','Save failed')); console.warn('save response', json, txt); return; }

          var savedId = json.id ? String(json.id) : (qs('#form_id') ? qs('#form_id').value : '');
          if (qs('#form_id')) qs('#form_id').value = savedId;

          var rowData = {};
          if (json.data && typeof json.data === 'object') { rowData = json.data; rowData.id = rowData.id ? String(rowData.id) : savedId; }
          else {
            rowData = { id: savedId, name: qs('#form_name').value, slug: qs('#form_slug').value, description: qs('#form_description').value, image_url: qs('#form_image_url') ? qs('#form_image_url').value : '', is_active: (qs('#form_is_active') && qs('#form_is_active').checked) ? 1 : 0 };
          }

          var existingTr = document.querySelector('tr[data-id="'+ rowData.id +'"]');
          var newTr = createRowElement(rowData);
          if (existingTr) existingTr.parentNode.replaceChild(newTr, existingTr);
          else {
            var tbody = qs('#tableBody');
            if (tbody && tbody.firstChild) tbody.insertBefore(newTr, tbody.firstChild);
            else if (tbody) tbody.appendChild(newTr);
          }

          // Reapply translations to newly inserted row and static bits
          refreshStaticTranslations(document);

          toast(tKey('saved_success','Saved successfully'));

          try { if (json.created) { var cnt = parseInt((countText.textContent||'').replace(/[^\d]/g,''),10)||0; countText.textContent = tKey('table.count','Categories {count}').replace('{count}', cnt+1); } } catch(e){}
          try { qs('#form_name').focus(); } catch (e) {}
        }).catch(function (err) {
          console.error('save request failed', err);
          alert(tKey('error','Error saving'));
        }).finally(function () { if (submitBtn) submitBtn.disabled = false; });
    });
  }

  // AJAX search
  if (searchForm) {
    searchForm.addEventListener('submit', function (ev) {
      ev.preventDefault();
      var q = (searchForm.querySelector('input[name="q"]') || { value: '' }).value.trim();
      var url = '/admin/fragments/menus_list.php?q=' + encodeURIComponent(q);
      var loader = document.createElement('tr');
      loader.innerHTML = '<td colspan="7" style="padding:20px;text-align:center;color:#666;">' + tKey('strings.loading','Searching...') + '</td>';
      tableBody.innerHTML = '';
      tableBody.appendChild(loader);

      fetch(url, { credentials: 'same-origin', headers: {'X-Requested-With':'XMLHttpRequest'} })
        .then(function (res) { if (!res.ok) throw new Error('HTTP ' + res.status); return res.text(); })
        .then(function (html) {
          var parser = new DOMParser();
          var doc = parser.parseFromString(html, 'text/html');
          var newTbody = doc.querySelector('#tableBody');
          if (newTbody) tableBody.innerHTML = newTbody.innerHTML;
          else {
            var rows = doc.querySelectorAll('#tableBody tr');
            if (rows && rows.length) { tableBody.innerHTML = ''; rows.forEach(function (r) { tableBody.appendChild(r.cloneNode(true)); }); }
            else tableBody.innerHTML = '<tr><td colspan="7" style="padding:20px;text-align:center;color:#666;">' + tKey('categories.list.no_results','No results') + '</td></tr>';
          }
          var newCount = doc.querySelector('#countText');
          if (newCount && countText) countText.textContent = newCount.textContent;
          // Reapply translations on new content
          refreshStaticTranslations(document);
          try { var rect = tableBody.getBoundingClientRect(); window.scrollTo({ top: window.pageYOffset + rect.top - 80, behavior: 'smooth' }); } catch (e) {}
        }).catch(function (err) {
          console.error('Search error', err);
          tableBody.innerHTML = '<tr><td colspan="7" style="padding:20px;text-align:center;color:#e74c3c;">' + tKey('error','Search error') + '</td></tr>';
        });
    });
  }

  // Apply translations on initial load
  try { refreshStaticTranslations(document); } catch (e) {}

  // Listen for language changes
  if (window.Admin && typeof Admin.on === 'function') {
    Admin.on('language:changed', function () { refreshStaticTranslations(document); });
  } else {
    window.addEventListener('language:changed', function () { refreshStaticTranslations(document); });
  }

  // expose helpers for debugging
  window._menusListSafeJsonParse = safeJsonParse;
  window._menusList_tKey = tKey;

})();
</script>

<?php if (!$isAjax) require_once __DIR__ . '/../includes/footer.php'; ?>