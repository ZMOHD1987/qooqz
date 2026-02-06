<?php
/**
 * admin/includes/footer.php
 * Dynamic footer with DB-driven theme/colors consistent with header.php
 */
declare(strict_types=1);

// If API/XHR/JSON request, do not output footer
$uri = $_SERVER['REQUEST_URI'] ?? '';
$xhr = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower((string)$_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
$acceptJson = stripos((string)($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json') !== false;
if ($xhr || $acceptJson || strpos((string)$uri, '/api/') === 0) {
    return;
}

// ════════════════════════════════════════════════════════════
// USE THE SAME PAYLOAD AS HEADER.PHP
// ════════════════════════════════════════════════════════════
$payload = $GLOBALS['ADMIN_UI'] ?? [];
$theme = $payload['theme'] ?? [];
$user = $payload['user'] ?? [];
$lang = $payload['lang'] ?? 'en';
$dir = $payload['direction'] ?? 'ltr';

// ════════════════════════════════════════════════════════════
// EXTRACT COLORS FROM THEME (SAME AS HEADER.PHP)
// ════════════════════════════════════════════════════════════
$colors = [];
foreach ($theme['color_settings'] ?? [] as $c) {
    if (!empty($c['color_value'])) {
        $colors[$c['setting_key']] = $c['color_value'];
    }
}

// Get specific colors
$sidebarBg = $colors['sidebar_background'] ?? '#4B0082';
$sidebarText = $colors['sidebar_text'] ?? '#FFFFFF';
$primaryColor = $colors['primary_color'] ?? '#3b82f6';
$dangerColor = $colors['danger_color'] ?? '#ef4444';
$sidebarHover = $colors['sidebar_hover'] ?? '#6A0DAD';
$sidebarActive = $colors['sidebar_active'] ?? '#8A2BE2';
$infoColor = $colors['info_color'] ?? '#3b82f6';
$successColor = $colors['success_color'] ?? '#10b981';
$warningColor = $colors['warning_color'] ?? '#f59e0b';

// Get design settings for footer text
$footerText = '© ' . date('Y') . ' Admin Panel';
foreach ($theme['design_settings'] ?? [] as $d) {
    if (($d['setting_key'] ?? '') === 'footer_text') {
        $footerText = $d['setting_value'] ?? $footerText;
        break;
    }
}

// If there's a brand name in strings, use it
$brand = $payload['strings']['brand'] ?? 'Admin';
?>
    </main> <!-- #adminMainContent -->
  </div> <!-- .admin-layout -->

  <footer class="admin-footer" role="contentinfo">
    <div class="container">
      <small data-i18n="footer.copyright"><?= htmlspecialchars($footerText, ENT_QUOTES, 'UTF-8') ?></small>
    </div>
  </footer>

<style>
/* Dynamic CSS Variables from DB (same as header) */
:root {
    <?php foreach ($colors as $key => $value): ?>
    --<?= htmlspecialchars($key) ?>: <?= htmlspecialchars($value) ?>;
    <?php endforeach; ?>
}

/* Footer styles using the same colors as header */
.admin-footer {
    background: var(--sidebar_background, #4B0082) !important;
    color: var(--sidebar_text, #FFFFFF) !important;
    border-top: 1px solid var(--primary_color, #3b82f6);
    padding: 1rem 0;
    margin-top: auto;
}

.admin-footer .container {
    display: flex;
    justify-content: center;
    align-items: center;
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 1rem;
}

.admin-footer small {
    font-size: 0.875rem;
    opacity: 0.9;
}
</style>

<script>
(function(){
  'use strict';
  
  window.Admin = window.Admin || {};

  // Sidebar toggle (persist in localStorage)
  (function(){
    const toggle = document.getElementById('sidebarToggle');
    const sidebar = document.getElementById('adminSidebar');
    const backdrop = document.querySelector('.sidebar-backdrop');
    if (!toggle || !sidebar) return;

    const stateKey = 'admin_sidebar_collapsed';
    try {
      const collapsed = localStorage.getItem(stateKey);
      if (collapsed === '1') document.body.classList.add('sidebar-collapsed');
    } catch(e){}

    function setCollapsed(val) {
      if (val) document.body.classList.toggle('sidebar-collapsed', val);
      try { localStorage.setItem(stateKey, val ? '1' : '0'); } catch(e){}
    }

    toggle.addEventListener('click', function(e){
      e.preventDefault();
      const isCollapsed = document.body.classList.toggle('sidebar-collapsed');
      setCollapsed(isCollapsed);
    });

    if (backdrop) {
      backdrop.addEventListener('click', function(){ document.body.classList.remove('sidebar-open'); });
    }
  })();

  // fetchAndInsert: simple fragment loader
  window.Admin.fetchAndInsert = function(url, targetSelector) {
    const target = document.querySelector(targetSelector);
    if (!target) return Promise.reject(new Error('Target not found'));
    return fetch(url, { credentials: 'same-origin' })
      .then(res => res.ok ? res.text() : Promise.reject(new Error('HTTP ' + res.status)))
      .then(html => { target.innerHTML = html; return html; });
  };

  // AJAX helper
  window.Admin.ajax = function(url, opts = {}) {
    opts = Object.assign({method:'GET', headers:{}, credentials:'same-origin'}, opts);
    return fetch(url, opts)
      .then(res => res.headers.get('content-type').includes('json') ? res.json() : res.text());
  };

  // Apply theme from DB
  window.Admin.applyTheme = function(theme) {
    if (!theme || !Array.isArray(theme.colors)) return;
    const root = document.documentElement;
    theme.colors.forEach(c => {
      if (c.setting_key && c.color_value) {
        root.style.setProperty('--' + c.setting_key, c.color_value);
      }
    });
  };

  // Apply initial theme from PHP
  <?php if (!empty($theme)): ?>
    window.Admin.applyTheme(<?= json_encode(['colors' => $theme['color_settings'] ?? []]) ?>);
  <?php endif; ?>

  // Notify (uses dynamic colors)
  window.Admin.notify = function(msg, type = 'info') {
    const toast = document.createElement('div');
    toast.style.position = 'fixed';
    toast.style.bottom = '20px';
    toast.style.right = '20px';
    toast.style.padding = '10px';
    toast.style.borderRadius = '5px';
    toast.style.color = '#fff';
    toast.style.zIndex = '10000';

    // Get colors from CSS variables (set by theme)
    const rootStyles = getComputedStyle(document.documentElement);
    const colors = {
      info: rootStyles.getPropertyValue('--info_color') || '#3b82f6',
      success: rootStyles.getPropertyValue('--success_color') || '#10b981',
      warning: rootStyles.getPropertyValue('--warning_color') || '#f59e0b',
      error: rootStyles.getPropertyValue('--danger_color') || '#ef4444',
      default: rootStyles.getPropertyValue('--primary_color') || '#4B0082'
    };

    toast.style.background = colors[type] || colors.default;
    toast.textContent = msg;
    document.body.appendChild(toast);
    setTimeout(() => document.body.removeChild(toast), 4000);
  };

  // Bind links with data-load-url
  document.addEventListener('click', function(e){
    const a = e.target.closest('a[data-load-url]');
    if (!a) return;
    e.preventDefault();
    const url = a.getAttribute('data-load-url');
    const target = a.getAttribute('data-target') || '#adminMainContent';
    window.Admin.fetchAndInsert(url, target).catch(() => {
      window.Admin.notify('Failed to load', 'error');
    });
  });

})();
</script>

</body>
</html>