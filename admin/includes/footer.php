<?php
/**
 * admin/includes/footer.php
 * Simple and quiet footer for admin pages.
 */
declare(strict_types=1);

// If API/XHR, do not output footer
$uri = $_SERVER['REQUEST_URI'] ?? '';
$xhr = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower((string)$_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
$acceptJson = stripos((string)($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json') !== false;
if ($xhr || $acceptJson || strpos((string)$uri, '/api/') === 0) {
    return;
}
?>
    </main> <!-- #adminMainContent -->
  </div> <!-- .admin-layout -->

  <footer class="admin-footer" role="contentinfo">
    <div class="container">
      <small>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($ADMIN_UI_PAYLOAD['strings']['brand'] ?? 'Admin', ENT_QUOTES, 'UTF-8'); ?> — All rights reserved.</small>
    </div>
  </footer>

  <script>
  (function(){
    // Ensure Admin namespace
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
        if (val) document.body.classList.add('sidebar-collapsed');
        else document.body.classList.remove('sidebar-collapsed');
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
        .then(html => {
          target.innerHTML = html;
          return html;
        });
    };

    // AJAX helper
    window.Admin.ajax = function(url, opts = {}) {
      opts = Object.assign({method:'GET', headers:{}, credentials:'same-origin'}, opts);
      return fetch(url, opts)
        .then(res => res.headers.get('content-type').includes('json') ? res.json() : res.text())
        .then(data => res.ok ? data : Promise.reject(new Error('Request failed')));
    };

    // Theme apply
    window.Admin.applyTheme = function(theme) {
      if (!theme) return;
      const root = document.documentElement;
      if (theme.colors && Array.isArray(theme.colors)) {
        theme.colors.forEach(c => {
          if (c.setting_key && c.color_value) {
            root.style.setProperty('--theme-' + c.setting_key.replace(/[^a-z0-9_-]/ig, '-'), c.color_value);
          }
        });
      }
    };

    // Apply initial theme
    if (window.ADMIN_UI && window.ADMIN_UI.theme) {
      window.Admin.applyTheme(window.ADMIN_UI.theme);
    }

    // Notify
    window.Admin.notify = function(msg, type = 'info') {
      const toast = document.createElement('div');
      toast.style.position = 'fixed';
      toast.style.bottom = '20px';
      toast.style.right = '20px';
      toast.style.background = type === 'error' ? '#f87171' : '#374151';
      toast.style.color = '#fff';
      toast.style.padding = '10px';
      toast.style.borderRadius = '5px';
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