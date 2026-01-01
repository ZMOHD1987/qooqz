<?php
/**
 * admin/includes/footer.php
 *
 * Complementary footer for admin header.php + dashboard.php
 * - Closes layout elements
 * - Provides client-side helpers: Admin.fetchAndInsert, Admin.ajax, sidebar toggle, theme apply, i18n hooks
 * - Uses window.ADMIN_UI injected by header.php
 *
 * Save as UTF-8 without BOM.
 */
declare(strict_types=1);

// If this is API/XHR, do not output footer HTML
$uri = $_SERVER['REQUEST_URI'] ?? '';
$xhr = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower((string)$_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
$acceptJson = stripos((string)($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json') !== false;
if ($xhr || $acceptJson || strpos((string)$uri, '/api/') === 0) {
    return;
}
?>
    </main> <!-- #adminMainContent -->
  </div> <!-- .admin-layout -->

  <footer class="admin-footer" role="contentinfo" aria-hidden="false">
    <div class="container">
      <small>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($ADMIN_UI_PAYLOAD['strings']['brand'] ?? 'Admin', ENT_QUOTES | ENT_SUBSTITUTE); ?> — All rights reserved.</small>
    </div>
  </footer>

  <!-- Optional inline script area for critical admin behaviors -->
  <script>
  (function(){
    // Ensure global Admin namespace
    window.Admin = window.Admin || {};
    const ADMIN = window.Admin;

    // CSRF token helper (from header)
    ADMIN.csrfToken = window.CSRF_TOKEN || (window.ADMIN_UI && window.ADMIN_UI.csrf_token) || '';

    // Simple logger prefix
    function log() {
      if (console && console.log) {
        console.log.apply(console, ['[ADMIN]'].concat(Array.from(arguments)));
      }
    }

    // Sidebar toggle behavior (persist state in localStorage)
    (function setupSidebarToggle(){
      const toggle = document.getElementById('sidebarToggle');
      const sidebar = document.getElementById('adminSidebar');
      const backdrop = document.querySelector('.sidebar-backdrop');
      if (!toggle || !sidebar) return;

      const stateKey = 'admin_sidebar_collapsed';
      // apply saved state
      try {
        const collapsed = localStorage.getItem(stateKey);
        if (collapsed === '1') document.body.classList.add('sidebar-collapsed');
      } catch(e){}

      function setCollapsed(val) {
        if (val) document.body.classList.add('sidebar-collapsed'); else document.body.classList.remove('sidebar-collapsed');
        try { localStorage.setItem(stateKey, val ? '1' : '0'); } catch(e){}
      }

      toggle.addEventListener('click', function(e){
        e.preventDefault();
        const isCollapsed = document.body.classList.toggle('sidebar-collapsed');
        setCollapsed(isCollapsed);
        // dispatch event
        window.dispatchEvent(new CustomEvent('admin:sidebar:toggled',{detail:{collapsed:isCollapsed}}));
      });

      // backdrop hides sidebar on small screens
      if (backdrop) {
        backdrop.addEventListener('click', function(){ document.body.classList.remove('sidebar-open'); });
      }

      // expose utility
      ADMIN.toggleSidebar = () => toggle.click();
      log('Sidebar ready');
    })();

    // fetchAndInsert: fetch an HTML fragment and insert into targetSelector
    // returns a Promise
    ADMIN.fetchAndInsert = async function(url, targetSelector, options = {}) {
      const target = (typeof targetSelector === 'string') ? document.querySelector(targetSelector) : targetSelector;
      if (!target) return Promise.reject(new Error('Target element not found: ' + targetSelector));
      options = options || {};
      const method = options.method || 'GET';
      const headers = options.headers || {};
      // include default Accept
      headers['Accept'] = headers['Accept'] || 'text/html,application/xhtml+xml,application/xml';
      // include CSRF for modifying requests
      if (['POST','PUT','DELETE','PATCH'].includes(method.toUpperCase())) {
        if (ADMIN.csrfToken) {
          headers['X-CSRF-Token'] = ADMIN.csrfToken;
        }
      }
      try {
        const resp = await fetch(url, {method, headers, credentials: 'same-origin', body: options.body || null});
        if (!resp.ok) {
          const text = await resp.text().catch(()=>null);
          log('fetchAndInsert: non-OK response', resp.status, url, text);
          throw new Error('Request failed: ' + resp.status);
        }
        const html = await resp.text();
        // insert HTML
        target.innerHTML = html;
        // execute inline scripts inside the inserted HTML
        const scripts = Array.from(target.querySelectorAll('script'));
        for (const s of scripts) {
          try {
            const newScript = document.createElement('script');
            // copy attributes
            for (const attr of s.attributes) newScript.setAttribute(attr.name, attr.value);
            if (s.src) {
              // external script: append to body to load
              newScript.src = s.src;
              document.body.appendChild(newScript);
            } else {
              newScript.text = s.textContent;
              document.head.appendChild(newScript).parentNode.removeChild(newScript);
            }
          } catch (se) { console.warn('Script execution failed', se); }
        }
        // dispatch event
        window.dispatchEvent(new CustomEvent('admin:fragment:loaded', {detail:{url, target}}));
        return html;
      } catch (err) {
        log('fetchAndInsert error', err);
        throw err;
      }
    };

    // AJAX helper with JSON response and CSRF
    ADMIN.ajax = async function(url, opts = {}) {
      opts = Object.assign({method:'GET', headers:{}, credentials:'same-origin'}, opts || {});
      const method = opts.method.toUpperCase();
      opts.headers = opts.headers || {};
      // default JSON headers
      if (!opts.headers['Accept']) opts.headers['Accept'] = 'application/json';
      if (method !== 'GET' && method !== 'HEAD') {
        // ensure JSON content-type if body present and not FormData
        if (opts.body && !(opts.body instanceof FormData) && !opts.headers['Content-Type']) {
          opts.headers['Content-Type'] = 'application/json; charset=utf-8';
        }
        // CSRF
        if (ADMIN.csrfToken && !opts.headers['X-CSRF-Token']) opts.headers['X-CSRF-Token'] = ADMIN.csrfToken;
      }
      try {
        const resp = await fetch(url, opts);
        const contentType = resp.headers.get('content-type') || '';
        if (contentType.indexOf('application/json') !== -1) {
          const json = await resp.json();
          if (!resp.ok) {
            throw Object.assign(new Error('Request failed'), {status: resp.status, body: json});
          }
          return json;
        } else {
          const text = await resp.text();
          if (!resp.ok) throw Object.assign(new Error('Request failed'), {status: resp.status, body: text});
          return text;
        }
      } catch (e) {
        log('AJAX error', e);
        throw e;
      }
    };

    // Theme application: apply JS theme object to CSS variables (client-side)
    ADMIN.applyTheme = function(theme) {
      if (!theme) return;
      try {
        const root = document.documentElement;
        if (theme.colors && Array.isArray(theme.colors)) {
          theme.colors.forEach(c => {
            if (!c.setting_key) return;
            const key = c.setting_key.replace(/[^a-z0-9_-]/ig, '-').toLowerCase();
            const val = c.color_value || '';
            if (val !== '') root.style.setProperty('--theme-' + key, val);
          });
        }
        if (theme.designs && typeof theme.designs === 'object') {
          Object.keys(theme.designs).forEach(k => {
            const key = k.replace(/[^a-z0-9_-]/ig, '-').toLowerCase();
            const val = theme.designs[k];
            if (val !== '') root.style.setProperty('--theme-' + key, val);
          });
        }
        log('Theme applied (client-side)', theme.id || theme.slug || '');
      } catch (e) {
        console.warn('applyTheme failed', e);
      }
    };

    // Apply initial theme from ADMIN_UI if present
    try {
      if (window.ADMIN_UI && window.ADMIN_UI.theme) {
        ADMIN.applyTheme(window.ADMIN_UI.theme);
      }
    } catch(e){}

    // Simple notifier API (example)
    ADMIN.notify = function(msg, opts = {}) {
      opts = Object.assign({type:'info', timeout:4000}, opts);
      // simple toast
      let container = document.getElementById('adminToastContainer');
      if (!container) {
        container = document.createElement('div');
        container.id = 'adminToastContainer';
        container.style.position = 'fixed';
        container.style.right = '18px';
        container.style.bottom = '18px';
        container.style.zIndex = 99999;
        document.body.appendChild(container);
      }
      const toast = document.createElement('div');
      toast.className = 'admin-toast admin-toast-' + opts.type;
      toast.style.marginTop = '8px';
      toast.style.padding = '10px 14px';
      toast.style.background = opts.type === 'error' ? '#ffefef' : '#111827';
      toast.style.color = opts.type === 'error' ? '#b91c1c' : '#fff';
      toast.style.borderRadius = '6px';
      toast.style.boxShadow = '0 4px 10px rgba(0,0,0,0.08)';
      toast.textContent = msg;
      container.appendChild(toast);
      setTimeout(()=>{ toast.style.transition = 'opacity 300ms'; toast.style.opacity = 0; setTimeout(()=>container.removeChild(toast),350); }, opts.timeout);
      return toast;
    };

    // Expose for console debugging
    window.Admin = ADMIN;
    log('Admin helpers loaded');

    // Auto-bind links with data-load-url to fetch fragments via JS
    document.addEventListener('click', function(e){
      const a = e.target.closest && e.target.closest('a[data-load-url]');
      if (!a) return;
      e.preventDefault();
      const url = a.getAttribute('data-load-url') || a.href;
      const target = a.getAttribute('data-target') || '#adminMainContent';
      ADMIN.fetchAndInsert(url, target).catch(err => {
        ADMIN.notify('Failed to load: ' + url, {type:'error'});
        console.warn('Fragment load failed', err);
      });
    });

    // Small accessibility: close modals on Escape
    document.addEventListener('keydown', function(e){
      if (e.key === 'Escape') {
        const openModal = document.querySelector('.modal.open');
        if (openModal && typeof window.Modal !== 'undefined') {
          try { window.Modal.close(openModal); } catch(e){}
        }
      }
    });

  })();
  </script>

  <!-- Optional page-specific scripts enqueued by server can be printed here -->
  <?php
  if (function_exists('print_footer_scripts')) {
      try { print_footer_scripts(); } catch (Throwable $e) { @file_put_contents(__DIR__ . '/../../api/error_debug.log', "[".date('c')."] print_footer_scripts error: ".$e->getMessage().PHP_EOL, FILE_APPEND); }
  }
  ?>

</body>
</html>