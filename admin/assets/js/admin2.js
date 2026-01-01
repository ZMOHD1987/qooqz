/*!
 * admin/assets/js/admin_core.js
 *
 * Admin client core – fully server-driven theme & i18n (2025 updated)
 * - Uses only window.ADMIN_UI injected by server (no network requests for translations)
 * - Applies theme variables, generates dynamic button/card styles
 * - Simple, fast translation using data-i18n + nested dot notation
 * - RBAC, fetch helpers, modal, sidebar, etc.
 */

(function () {
  'use strict';

  if (window.Admin && window.Admin.__installed) return;
  window.Admin = window.Admin || {};
  Admin.__installed = true;

  // Debug toggle
  Admin.debug = false;
  Admin.log   = (...args) => Admin.debug && console.log('[Admin]', ...args);
  Admin.warn  = (...args) => console.warn('[Admin]', ...args);
  Admin.error = (...args) => console.error('[Admin]', ...args);

  // DOM ready
  Admin.domReady = (fn) => {
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', fn);
    else setTimeout(fn, 0);
  };

  // Utils
  const safeSlug = (s) => String(s || '').toLowerCase().replace(/[^a-z0-9_-]/g, '-').replace(/-+/g, '-').replace(/^-|-$/g, '');

  const getNested = (obj, path) => {
    if (!obj || !path) return undefined;
    return path.split('.').reduce((cur, part) => (cur && typeof cur === 'object' && part in cur) ? cur[part] : undefined, obj);
  };

  // -----------------------
  // User & Permissions
  // -----------------------
  Admin.ADMIN_USER = window.ADMIN_UI?.user || {};
  Admin.isSuper = () => {
    const role = Admin.ADMIN_USER.role || Admin.ADMIN_USER.role_id;
    return role === 1 || role === '1' || /^super_admin|admin$/i.test(String(role));
  };

  Admin.can = (perm) => {
    if (!perm || Admin.isSuper()) return true;
    const perms = Array.isArray(Admin.ADMIN_USER.permissions) ? Admin.ADMIN_USER.permissions : [];
    if (Array.isArray(perm)) return perm.some(p => perms.includes(p));
    if (perm.includes('|')) return perm.split('|').some(p => perms.includes(p.trim()));
    return perms.includes(perm);
  };

  Admin.canAll = (perm) => {
    if (!perm || Admin.isSuper()) return true;
    const perms = Array.isArray(Admin.ADMIN_USER.permissions) ? Admin.ADMIN_USER.permissions : [];
    const list = Array.isArray(perm) ? perm : perm.split('|').map(p => p.trim());
    return list.every(p => perms.includes(p));
  };

  Admin.applyPermsToContainer = (container = document) => {
    container.querySelectorAll('[data-require-perm]').forEach(el => {
      const spec = el.dataset.requirePerm?.trim();
      if (spec && !Admin.can(spec)) {
        el.dataset.removeWithoutPerm === '1' ? el.remove() : el.style.display = 'none';
      } else {
        el.style.display = '';
      }
    });
    container.querySelectorAll('[data-require-all]').forEach(el => {
      const spec = el.dataset.requireAll?.trim();
      if (spec && !Admin.canAll(spec)) {
        el.dataset.removeWithoutPerm === '1' ? el.remove() : el.style.display = 'none';
      } else {
        el.style.display = '';
      }
    });
    container.querySelectorAll('[data-hide-without-perm]').forEach(el => {
      const spec = el.dataset.hideWithoutPerm?.trim();
      if (spec && !Admin.can(spec)) el.remove();
    });
  };

  // -----------------------
  // Asset loader
  // -----------------------
  Admin.asset = (() => {
    const loadedCss = {}, loadedJs = {}, loadingJs = {};
    return {
      loadCss: (href) => {
        if (!href || loadedCss[href] || document.querySelector(`link[href="${href}"]`)) {
          loadedCss[href] = true;
          return Promise.resolve();
        }
        return new Promise(resolve => {
          const link = document.createElement('link');
          link.rel = 'stylesheet';
          link.href = href;
          link.onload = () => { loadedCss[href] = true; resolve(); };
          link.onerror = () => { Admin.warn('CSS load failed:', href); resolve(); };
          document.head.appendChild(link);
        });
      },
      loadJs: (src) => {
        if (!src || loadedJs[src] || document.querySelector(`script[src="${src}"]`)) {
          loadedJs[src] = true;
          return Promise.resolve();
        }
        if (loadingJs[src]) return loadingJs[src];
        const promise = new Promise(resolve => {
          const script = document.createElement('script');
          script.src = src;
          script.defer = true;
          script.onload = () => { loadedJs[src] = true; delete loadingJs[src]; resolve(); };
          script.onerror = () => { Admin.warn('JS load failed:', src); delete loadingJs[src]; resolve(); };
          document.head.appendChild(script);
        });
        loadingJs[src] = promise;
        return promise;
      }
    };
  })();

  // -----------------------
  // Theme application
  // -----------------------
  function applyTheme() {
    if (!window.ADMIN_UI?.theme) return;
    const theme = window.ADMIN_UI.theme;
    const root = document.documentElement;

    // Colors
    if (Array.isArray(theme.colors)) {
      theme.colors.forEach(c => {
        if (c.setting_key && c.color_value) {
          const key = '--theme-' + safeSlug(c.setting_key);
          root.style.setProperty(key, c.color_value);
        }
      });
    }

    // Designs (logo_url, header_height, etc.)
    if (theme.designs && typeof theme.designs === 'object') {
      Object.entries(theme.designs).forEach(([k, v]) => {
        const key = '--theme-' + safeSlug(k);
        root.style.setProperty(key, String(v));
        if (k === 'logo_url') root.style.setProperty('--theme-logo-url', `url('${v}')`);
        if (k === 'header_height') root.style.setProperty('--header-height', String(v));
      });
    }

    // Fonts
    if (Array.isArray(theme.fonts) && theme.fonts.length) {
      const bodyFont = theme.fonts.find(f => f.category === 'body') || theme.fonts[0];
      if (bodyFont?.font_family) {
        root.style.setProperty('--font-family', bodyFont.font_family);
      }
      theme.fonts.forEach(f => {
        if (f.font_url) Admin.asset.loadCss(f.font_url);
      });
    }

    // Direction
    if (window.ADMIN_UI.direction) {
      document.documentElement.dir = window.ADMIN_UI.direction;
    }

    // Generate dynamic button/card styles
    generateDynamicStyles();
  }

  function generateDynamicStyles() {
    const theme = window.ADMIN_UI?.theme;
    if (!theme) return;

    const styleEl = document.getElementById('theme-component-styles') || (() => {
      const el = document.createElement('style');
      el.id = 'theme-component-styles';
      document.head.appendChild(el);
      return el;
    })();

    const rules = [];

    // Buttons
    if (Array.isArray(theme.buttons)) {
      theme.buttons.forEach(b => {
        if (!b.slug) return;
        const cls = 'btn-' + safeSlug(b.slug);
        const styles = [
          `background:${b.background_color || 'transparent'}`,
          `color:${b.text_color || '#000'}`,
          `border:${b.border_width || 0}px solid ${b.border_color || 'transparent'}`,
          `border-radius:${(b.border_radius || 4)}px`,
          `padding:${b.padding || '10px 20px'}`,
          `font-size:${b.font_size || '14px'}`,
          `font-weight:${b.font_weight || 'normal'}`
        ].join(';');
        rules.push(`.${cls}{${styles};cursor:pointer;display:inline-block;text-decoration:none;}`);
        if (b.hover_background_color || b.hover_text_color) {
          rules.push(`.${cls}:hover{background:${b.hover_background_color || b.background_color};color:${b.hover_text_color || b.text_color};}`);
        }
      });
    }

    // Cards
    if (Array.isArray(theme.cards)) {
      theme.cards.forEach(c => {
        if (!c.slug) return;
        const cls = 'card-' + safeSlug(c.slug);
        const styles = [
          `background:${c.background_color || '#fff'}`,
          `border:${c.border_width || 0}px solid ${c.border_color || 'transparent'}`,
          `border-radius:${(c.border_radius || 6)}px`,
          `padding:${c.padding || '16px'}`,
          `box-shadow:${c.shadow_style && c.shadow_style !== 'none' ? c.shadow_style : 'none'}`,
          `text-align:${c.text_align || 'left'}`
        ].join(';');
        rules.push(`.${cls}{${styles};}`);
        if (c.hover_effect && c.hover_effect !== 'none') {
          let hover = '';
          switch (c.hover_effect) {
            case 'lift': hover = 'transform:translateY(-6px);box-shadow:0 10px 30px rgba(0,0,0,0.08);'; break;
            case 'zoom': hover = 'transform:scale(1.02);'; break;
            case 'shadow': hover = 'box-shadow:0 12px 36px rgba(0,0,0,0.12);'; break;
          }
          if (hover) rules.push(`.${cls}:hover{transition:all 180ms ease;${hover}}`);
        }
      });
    }

    styleEl.textContent = rules.join('\n');
  }

  // -----------------------
  // Translation (server-driven – no network!)
  // -----------------------
  const applyTranslations = (root = document) => {
    root.querySelectorAll('[data-i18n]').forEach(el => {
      const key = el.dataset.i18n;
      if (!key) return;
      const value = getNested(window.ADMIN_UI?.strings, key);
      if (value !== undefined) el.textContent = value;
    });
    root.querySelectorAll('[data-i18n-placeholder]').forEach(el => {
      const key = el.dataset.i18nPlaceholder;
      if (!key) return;
      const value = getNested(window.ADMIN_UI?.strings, key);
      if (value !== undefined) el.placeholder = value;
    });
  };

  window._admin = window._admin || {};
  window._admin.applyTranslations = applyTranslations;

  // -----------------------
  // Fetch & Insert
  // -----------------------
  Admin.fetchAndInsert = (url, selector) => {
    const target = document.querySelector(selector);
    if (!target) return Promise.reject('Target not found');

    target.innerHTML = '<div class="inline-loader">Loading...</div>';

    return fetch(url, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then(res => {
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        return res.text();
      })
      .then(html => {
        target.innerHTML = html;
        Admin.runScripts?.(target);
        applyTranslations(target);
        Admin.applyPermsToContainer(target);
        Admin.initPageFromFragment?.(target);
      })
      .catch(err => {
        target.innerHTML = '<div style="padding:20px;color:#c0392b;text-align:center;">Error loading content</div>';
        Admin.error('fetchAndInsert failed', err);
      });
  };

  Admin.runScripts = (container) => {
    container.querySelectorAll('script').forEach(old => {
      if (old.dataset.noRun === '1') return;
      if (old.src) {
        if (document.querySelector(`script[src="${old.src}"]`)) return;
        const s = document.createElement('script');
        s.src = old.src;
        s.async = false;
        document.body.appendChild(s);
      } else {
        const s = document.createElement('script');
        s.textContent = old.textContent;
        document.body.appendChild(s);
        document.body.removeChild(s);
      }
    });
  };

  // -----------------------
  // UI Init
  // -----------------------
  function initUI() {
    // Sidebar
    const toggle = document.getElementById('sidebarToggle');
    const sidebar = document.getElementById('adminSidebar');
    const backdrop = document.querySelector('.sidebar-backdrop');

    if (toggle && sidebar) {
      toggle.addEventListener('click', () => {
        document.body.classList.toggle('sidebar-open');
        toggle.setAttribute('aria-expanded', document.body.classList.contains('sidebar-open'));
      });
      if (backdrop) {
        backdrop.addEventListener('click', () => document.body.classList.remove('sidebar-open'));
      }
    }

    // Search
    const searchInput = document.getElementById('adminSearch');
    const searchBtn = document.getElementById('searchBtn');
    if (searchInput) {
      searchInput.addEventListener('keydown', e => e.key === 'Enter' && searchBtn?.click());
    }
    if (searchBtn) {
      searchBtn.addEventListener('click', () => {
        const q = searchInput?.value.trim();
        if (q) location.href = `/admin/search.php?q=${encodeURIComponent(q)}`;
      });
    }
  }

  // -----------------------
  // CSRF & Modal
  // -----------------------
  Admin.getCsrf = () => window.ADMIN_UI?.csrf_token || '';

  Admin.openModal = (content, options = {}) => {
    // simple modal implementation (ممكن توسيعه لاحقاً)
    return Promise.resolve(content);
  };

  // -----------------------
  // Init
  // -----------------------
  Admin.domReady(() => {
    Admin.log('admin_core initializing...');

    applyTheme();
    applyTranslations(document);
    Admin.applyPermsToContainer(document);
    initUI();

    // Auto-load default fragment if needed (example for dashboard)
    const main = document.getElementById('adminMainContent');
    if (main && main.dataset.defaultLoad) {
      Admin.fetchAndInsert(main.dataset.defaultLoad, '#adminMainContent');
    }

    Admin.log('admin_core ready – server-driven i18n & theme');
  });

  // Expose for debugging
  Admin.applyTranslations = applyTranslations;
  Admin.applyTheme = applyTheme;

})();