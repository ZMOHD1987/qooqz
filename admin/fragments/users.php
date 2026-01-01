<?php
// htdocs/admin/fragments/users.php
// Users fragment (list + inline edit) — updated to ensure:
// - CSS (/admin/assets/css/pages/users.css) and JS (/admin/assets/js/pages/users.js) are injected when fragment is embedded
// - Translation loader runs to apply data-i18n keys (works with AdminI18n if present or loads /admin/languages/admin/{lang}.json)
// - Uses users.headers.* keys for table headers
//
// Save as UTF-8 without BOM.

if (session_status() === PHP_SESSION_NONE) session_start();

$authFile = __DIR__ . '/../_auth.php';
if (!is_readable($authFile)) {
    http_response_code(500);
    echo "<div class='err'>Missing auth bootstrap: _auth.php</div>";
    exit;
}
require_once $authFile;

// permission
if (isset($rbac) && method_exists($rbac,'requirePermission')) {
    try { $rbac->requirePermission(['manage_users']); }
    catch (Throwable $e) { echo "<div class='err'>Forbidden</div>"; exit; }
}

// CSRF
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
$CSRF = $_SESSION['csrf_token'];

// assets (adjust base if needed)
$cssPath = '/admin/assets/css/pages/users.css';
$jsPath  = '/admin/assets/js/pages/users.js';
$langBase = '/languages/admin'; // translation files: en.json, ar.json

?>
<!-- Fragment root -->
<div id="adminUsers" class="admin-fragment users-fragment" data-csrf="<?= htmlspecialchars($CSRF, ENT_QUOTES, 'UTF-8') ?>">
  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;">
    <h2 data-i18n="users.title">Users</h2>
    <div>
      <button id="usersRefresh" class="btn" data-i18n="buttons.refresh">Refresh</button>
    </div>
  </div>

  <div id="usersStatus" class="status" aria-live="polite"></div>

  <div style="overflow:auto;">
    <table id="usersTable" class="table">
      <thead>
        <tr>
          <th data-i18n="users.headers.id">ID</th>
          <th data-i18n="users.headers.username">Username</th>
          <th data-i18n="users.headers.email">Email</th>
          <th data-i18n="users.headers.language">Language</th>
          <th data-i18n="users.headers.role">Role</th>
          <th data-i18n="users.headers.is_active">Active</th>
          <th data-i18n="users.headers.actions">Actions</th>
        </tr>
      </thead>
      <tbody id="usersTbody">
        <tr><td colspan="7" data-i18n="loading">Loading...</td></tr>
      </tbody>
    </table>
  </div>

  <!-- Inline edit / change password form -->
  <div id="userFormWrap" class="inline-form" style="display:none;margin-top:16px;">
    <h3 id="userFormTitle" data-i18n="users.edit_title">Edit user</h3>
    <form id="userForm">
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="id" id="user_id" value="0">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($CSRF, ENT_QUOTES, 'UTF-8') ?>">

      <div class="form-grid">
        <label>
          <div data-i18n="users.username">Username</div>
          <input type="text" name="username" id="user_username" class="input" required>
        </label>
        <label>
          <div data-i18n="users.email">Email</div>
          <input type="email" name="email" id="user_email" class="input" required>
        </label>
        <label>
          <div data-i18n="users.preferred_language">Language</div>
          <input type="text" name="preferred_language" id="user_lang" class="input">
        </label>
        <label>
          <div data-i18n="users.role">Role ID</div>
          <input type="number" name="role_id" id="user_role" class="input">
        </label>
        <label>
          <div data-i18n="users.phone">Phone</div>
          <input type="text" name="phone" id="user_phone" class="input">
        </label>
        <label>
          <div data-i18n="users.timezone">Timezone</div>
          <input type="text" name="timezone" id="user_timezone" class="input">
        </label>
      </div>

      <div class="form-actions" style="margin-top:12px;">
        <button type="submit" class="btn primary" id="userSaveBtn" data-i18n="buttons.save">Save</button>
        <button type="button" class="btn" id="userCancelBtn" data-i18n="buttons.cancel">Cancel</button>
        <button type="button" class="btn danger" id="userDeleteBtn" data-i18n="actions.delete">Delete</button>
        <button type="button" class="btn" id="userPwdBtn" data-i18n="users.change_password_btn">Change password</button>
      </div>
    </form>

    <!-- Change password panel -->
    <div id="changePwdWrap" style="display:none;margin-top:12px;">
      <h4 data-i18n="users.change_password_title">Change password</h4>
      <form id="changePwdForm">
        <input type="hidden" name="action" value="change_password">
        <input type="hidden" name="id" id="pwd_user_id" value="0">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($CSRF, ENT_QUOTES, 'UTF-8') ?>">
        <label>
          <div data-i18n="users.new_password">New password</div>
          <input type="password" name="new_password" id="new_password" class="input" required>
        </label>
        <div style="margin-top:8px;">
          <button type="submit" class="btn primary" data-i18n="buttons.save">Save password</button>
          <button type="button" class="btn" id="cancelPwdBtn" data-i18n="buttons.cancel">Cancel</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Loader: inject CSS/JS and ensure translations applied -->
<script>
(function(){
  'use strict';
  var cssHref = '<?= addslashes($cssPath) ?>';
  var jsSrc   = '<?= addslashes($jsPath) ?>';
  var langBase = '<?= addslashes($langBase) ?>';

  // inject CSS once
  function ensureCss() {
    if (document.querySelector('link[data-admin-users-css]')) return;
    var links = document.querySelectorAll('link[rel="stylesheet"]');
    for (var i=0;i<links.length;i++){
      if (links[i].href && links[i].href.indexOf(cssHref) !== -1) {
        links[i].setAttribute('data-admin-users-css','1');
        return;
      }
    }
    var l = document.createElement('link');
    l.rel = 'stylesheet';
    l.href = cssHref + '?v=1';
    l.setAttribute('data-admin-users-css','1');
    document.head.appendChild(l);
  }

  // inject JS once
  function ensureJs(cb) {
    if (document.querySelector('script[data-admin-users-js]')) { if (cb) cb(); return; }
    var s = document.createElement('script');
    s.src = jsSrc + '?v=1';
    s.defer = true;
    s.setAttribute('data-admin-users-js','1');
    s.onload = function(){ if (cb) cb(); };
    s.onerror = function(){ console.warn('Failed to load users.js'); if (cb) cb(); };
    document.head.appendChild(s);
  }

  // simple translator: prefer AdminI18n if available, otherwise load language JSON and apply to nodes with data-i18n
  function resolveKey(obj, key) {
    if (!obj || !key) return null;
    var parts = key.split('.');
    var cur = obj;
    for (var i=0;i<parts.length;i++){
      if (!cur) return null;
      cur = cur[parts[i]];
    }
    return cur;
  }

  function translateFragment(root, translations) {
    root = root || document;
    var nodes = root.querySelectorAll('[data-i18n]');
    nodes.forEach(function(node){
      var key = node.getAttribute('data-i18n');
      var val = null;
      if (window.AdminI18n && typeof window.AdminI18n.tKey === 'function') {
        try { val = window.AdminI18n.tKey(key, null); } catch(e){ val = null; }
      }
      if (val === null) val = resolveKey(translations, key) || resolveKey(window.ADMIN_UI, key);
      if (val != null) {
        var attr = node.getAttribute('data-i18n-attr');
        if (attr) node.setAttribute(attr, val);
        else {
          var tag = node.tagName && node.tagName.toLowerCase();
          if (tag === 'input' || tag === 'textarea') {
            if (node.hasAttribute('data-i18n-placeholder')) node.placeholder = val;
            else node.value = val;
          } else node.textContent = val;
        }
      }
    });
  }

  function loadLangAndApply(cb) {
    var htmlLang = (document.documentElement.lang || 'en').split('-')[0];
    var lang = window.ADMIN_LANG || htmlLang || 'en';
    var url = langBase + '/' + lang + '.json';
    // if ADMIN_UI already populated for this lang, apply immediately
    if (window.ADMIN_UI && window.ADMIN_UI.lang && window.ADMIN_UI.lang.split && window.ADMIN_UI.lang.split('-')[0] === lang) {
      translateFragment(document, window.ADMIN_UI);
      if (cb) cb();
      return;
    }
    fetch(url, { credentials:'same-origin' }).then(function(res){
      if (!res.ok) throw new Error('Lang file load failed');
      return res.json();
    }).then(function(json){
      window.ADMIN_UI = window.ADMIN_UI || {};
      // merge shallow
      Object.keys(json).forEach(function(k){ window.ADMIN_UI[k] = json[k]; });
      translateFragment(document, window.ADMIN_UI);
      if (cb) cb();
    }).catch(function(err){
      // fallback: if AdminI18n present, try to use it
      if (window.AdminI18n && typeof window.AdminI18n.loadLanguage === 'function') {
        window.AdminI18n.loadLanguage(lang).then(function(){ if (typeof window.AdminI18n.translateFragment === 'function') window.AdminI18n.translateFragment(document); if (cb) cb(); }).catch(function(){ if (cb) cb(); });
      } else {
        console.warn('Could not load translations', err);
        if (cb) cb();
      }
    });
  }

  // apply minimal fallback styles if stylesheet doesn't apply within short time
  function applyFallbackStylesIfNeeded(){
    setTimeout(function(){
      var el = document.querySelector('.users-fragment .btn');
      var applied = false;
      if (el) {
        var bg = window.getComputedStyle(el).backgroundColor || '';
        applied = bg && bg !== 'rgba(0, 0, 0, 0)' && bg !== 'transparent';
      }
      if (!applied) {
        var fid = 'users-fallback-styles';
        if (document.getElementById(fid)) return;
        var style = document.createElement('style');
        style.id = fid;
        style.textContent = "\
.users-fragment .table{width:100%;border-collapse:collapse;margin-top:8px;font-size:14px}\
.users-fragment .table th,.users-fragment .table td{padding:10px;border-bottom:1px solid #e5e7eb;text-align:left}\
.users-fragment .btn{display:inline-block;padding:6px 10px;border-radius:6px;background:#f3f4f6;border:1px solid #e5e7eb;color:#111;text-decoration:none;cursor:pointer}\
.users-fragment .btn.primary{background:#0b6ea8;color:#fff}\
.users-fragment .inline-form{margin-top:16px;padding:12px;border:1px solid #eee;border-radius:8px;background:#fff}\
.users-fragment .form-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}\
.users-fragment .input{width:100%;padding:8px;border:1px solid #ddd;border-radius:6px}\
";
        document.head.appendChild(style);
      }
    }, 350);
  }

  // Run
  ensureCss();
  ensureJs(function(){
    loadLangAndApply(function(){
      applyFallbackStylesIfNeeded();
    });
  });

  // re-apply on language change
  window.addEventListener('language:changed', function(e){
    loadLangAndApply();
  });

})();
</script>