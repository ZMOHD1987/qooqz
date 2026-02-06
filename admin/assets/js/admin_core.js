/*!
 * admin/assets/js/admin_core.js
 * Production Version - Complete with theme, i18n, permissions, and dynamic page loading
 * Version: 2.0.0
 */
(function () {
  'use strict';

  // ════════════════════════════════════════════════════════════
  // PREVENT DOUBLE INITIALIZATION
  // ════════════════════════════════════════════════════════════
  if (window.Admin && window.Admin.__installed) {
    console.warn('[Admin] Already installed, skipping...');
    return;
  }

  // ════════════════════════════════════════════════════════════
  // CORE SETUP
  // ════════════════════════════════════════════════════════════
  window.Admin = window.Admin || {};
  Admin.__installed = true;
  Admin.version = '2.0.0';
  
  Admin.debug = true;
  Admin.log = function () { 
    if (Admin.debug && console?.log) console.log('[Admin]', ...arguments); 
  };
  Admin.warn = function () { 
    if (console?.warn) console.warn('[Admin]', ...arguments); 
  };
  Admin.error = function () { 
    if (console?.error) console.error('[Admin]', ...arguments); 
  };

  // ════════════════════════════════════════════════════════════
  // UTILITY FUNCTIONS
  // ════════════════════════════════════════════════════════════
  
  function domReady(fn) {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', fn);
    } else {
      setTimeout(fn, 0);
    }
  }
  Admin.domReady = domReady;

  function safeSlug(s) {
    return String(s || '')
      .toLowerCase()
      .replace(/[^a-z0-9_-]/g, '-')
      .replace(/-+/g, '-')
      .replace(/^-|-$/g, '');
  }

  function deepMerge(dest, src) {
    if (!src || typeof src !== 'object') return dest || {};
    dest = dest || {};
    Object.keys(src).forEach(k => {
      const sv = src[k];
      if (sv && typeof sv === 'object' && !Array.isArray(sv)) {
        dest[k] = dest[k] || {};
        deepMerge(dest[k], sv);
      } else {
        dest[k] = sv;
      }
    });
    return dest;
  }

  function normalizeExplicitColor(v) {
    if (v === undefined || v === null) return null;
    const s = String(v).trim();
    if (!s) return null;
    if (/^transpa/i.test(s)) return 'transparent';
    if (/^var\(--/.test(s)) return s;
    if (/^(rgb|rgba|hsl|hsla)\(/i.test(s)) return s;
    if (/^#([A-Fa-f0-9]{3}|[A-Fa-f0-9]{6}|[A-Fa-f0-9]{8})$/.test(s)) return s.toUpperCase();
    if (/^[A-Fa-f0-9]{6}$/.test(s)) return ('#' + s).toUpperCase();
    if (/^[A-Fa-f0-9]{3}$/.test(s)) return ('#' + s).toUpperCase();
    if (/^[a-z\-]+$/i.test(s)) return s.toLowerCase();
    return null;
  }

  // ════════════════════════════════════════════════════════════
  // RBAC (Role-Based Access Control)
  // ════════════════════════════════════════════════════════════
  
  Admin.ADMIN_USER = window.ADMIN_USER || window.ADMIN_UI?.user || {};

  (function normalizeAdminUser() {
    const u = Admin.ADMIN_USER;
    if (!u) {
      Admin.ADMIN_USER = {};
      return;
    }
    if (!Array.isArray(u.permissions)) {
      u.permissions = u.permissions ? [u.permissions] : [];
    }
    if (!u.role && u.role_id) u.role = u.role_id;
    if (typeof u.role === 'string' && /^\d+$/.test(u.role)) {
      u.role = parseInt(u.role, 10);
    }
  })();

  Admin.isSuper = () => {
    try {
      const r = Admin.ADMIN_USER?.role || Admin.ADMIN_USER?.role_id;
      if (!r) return false;
      return r === 1 || r === '1' || String(r).toLowerCase() === 'super_admin';
    } catch {
      return false;
    }
  };

  Admin.can = (perm) => {
    if (!perm) return true;
    if (Admin.isSuper()) return true;
    const perms = Admin.ADMIN_USER?.permissions || [];
    if (Array.isArray(perm)) return perm.some(p => perms.includes(p));
    if (String(perm).includes('|')) {
      return String(perm)
        .split('|')
        .map(s => s.trim())
        .filter(Boolean)
        .some(p => perms.includes(p));
    }
    return perms.includes(perm);
  };

  Admin.canAll = (perm) => {
    if (!perm) return true;
    if (Admin.isSuper()) return true;
    const perms = Admin.ADMIN_USER?.permissions || [];
    const parts = Array.isArray(perm)
      ? perm
      : String(perm)
          .split('|')
          .map(s => s.trim())
          .filter(Boolean);
    return parts.every(p => perms.includes(p));
  };

  Admin.applyPermsToContainer = (container = document) => {
    try {
      // data-require-perm (any)
      container.querySelectorAll('[data-require-perm]').forEach(el => {
        const spec = el.getAttribute('data-require-perm')?.trim();
        if (!spec) return;
        if (!Admin.can(spec)) {
          el.getAttribute('data-remove-without-perm') === '1'
            ? el.remove()
            : (el.style.display = 'none');
        } else {
          el.style.display = '';
        }
      });

      // data-require-all (all)
      container.querySelectorAll('[data-require-all]').forEach(el => {
        const spec = el.getAttribute('data-require-all')?.trim();
        if (!spec) return;
        if (!Admin.canAll(spec)) {
          el.getAttribute('data-remove-without-perm') === '1'
            ? el.remove()
            : (el.style.display = 'none');
        } else {
          el.style.display = '';
        }
      });

      // data-hide-without-perm (remove)
      container.querySelectorAll('[data-hide-without-perm]').forEach(el => {
        const spec = el.getAttribute('data-hide-without-perm')?.trim();
        if (!spec) return;
        if (!Admin.can(spec)) el.remove();
      });
    } catch (e) {
      Admin.warn('applyPermsToContainer error', e);
    }
  };

  // ════════════════════════════════════════════════════════════
  // ASSET LOADER
  // ════════════════════════════════════════════════════════════
  
  Admin.asset = (() => {
    const loadedCss = {};
    const loadedJs = {};
    const loadingJs = {};

    function loadCss(href) {
      if (!href) return Promise.resolve();
      if (loadedCss[href] || document.querySelector(`link[rel="stylesheet"][href="${href}"]`)) {
        loadedCss[href] = true;
        return Promise.resolve();
      }
      return new Promise(resolve => {
        const l = document.createElement('link');
        l.rel = 'stylesheet';
        l.href = href;
        l.onload = () => {
          loadedCss[href] = true;
          resolve();
        };
        l.onerror = () => {
          Admin.warn('CSS load failed:', href);
          loadedCss[href] = true;
          resolve();
        };
        document.head.appendChild(l);
      });
    }

    function loadJs(src) {
      if (!src) return Promise.resolve();
      
      // Check if already loaded
      if (loadedJs[src] || document.querySelector(`script[src="${src}"]`)) {
        loadedJs[src] = true;
        return Promise.resolve();
      }
      
      // Check if currently loading
      if (loadingJs[src]) return loadingJs[src];

      const p = new Promise((resolve, reject) => {
        const s = document.createElement('script');
        s.src = src;
        s.defer = false;
        s.async = false;
        
        s.onload = () => {
          loadedJs[src] = true;
          delete loadingJs[src];
          Admin.log('✓ Loaded JS:', src);
          
          // Dispatch event
          window.dispatchEvent(new CustomEvent('admin:script:loaded', {
            detail: { src }
          }));
          
          resolve();
        };
        
        s.onerror = (err) => {
          Admin.error('✗ JS load failed:', src, err);
          delete loadingJs[src];
          reject(err);
        };
        
        document.head.appendChild(s);
      });

      loadingJs[src] = p;
      return p;
    }

    return { loadCss, loadJs };
  })();

  // ════════════════════════════════════════════════════════════
  // THEME APPLICATION
  // ════════════════════════════════════════════════════════════
  
  function ensureThemeStyleContainer() {
    let style = document.getElementById('theme-component-styles');
    if (!style) {
      style = document.createElement('style');
      style.id = 'theme-component-styles';
      document.head.appendChild(style);
    }
    return style;
  }

  function syncThemeVarsFromAdminUI() {
    try {
      if (!window.ADMIN_UI?.theme) {
        Admin.warn('⚠ No ADMIN_UI.theme found');
        return;
      }

      const themeData = window.ADMIN_UI.theme;
      const root = document.documentElement;

      Admin.log('═══════════════════════════════════');
      Admin.log('🎨 Applying Theme');
      Admin.log('═══════════════════════════════════');

      // 1. Apply generated_css
      if (themeData.generated_css) {
        let genStyle = document.getElementById('theme-generated-db');
        if (!genStyle) {
          genStyle = document.createElement('style');
          genStyle.id = 'theme-generated-db';
          document.head.appendChild(genStyle);
        }
        genStyle.textContent = themeData.generated_css;
        Admin.log('✓ Applied generated_css');
      }

      // 2. Apply color_settings
      if (Array.isArray(themeData.color_settings)) {
        Admin.log('🎨 Colors:', themeData.color_settings.length);
        themeData.color_settings.forEach(c => {
          if (!c?.setting_key || !c?.color_value) return;
          const key = '--' + safeSlug(c.setting_key);
          const val = normalizeExplicitColor(c.color_value) || c.color_value;
          root.style.setProperty(key, String(val));
        });
      }

      // 3. Apply font_settings
      if (Array.isArray(themeData.font_settings)) {
        Admin.log('🔤 Fonts:', themeData.font_settings.length);
        themeData.font_settings.forEach(f => {
          if (!f?.setting_key) return;
          const base = '--' + safeSlug(f.setting_key);

          if (f.font_family) {
            root.style.setProperty(base + '-family', f.font_family);

            // Load Google Font
            if (f.font_url) {
              Admin.asset.loadCss(f.font_url);
            } else if (!/system|arial|verdana|sans-serif/i.test(f.font_family)) {
              const gurl = `https://fonts.googleapis.com/css2?family=${encodeURIComponent(
                f.font_family.replace(/\s+/g, '+')
              )}&display=swap`;
              Admin.asset.loadCss(gurl);
            }
          }

          if (f.font_size) root.style.setProperty(base + '-size', f.font_size);
          if (f.font_weight) root.style.setProperty(base + '-weight', f.font_weight);
        });
      }

      // 4. Apply design_settings
      if (Array.isArray(themeData.design_settings)) {
        Admin.log('⚙ Designs:', themeData.design_settings.length);
        themeData.design_settings.forEach(d => {
          if (!d?.setting_key || !d?.setting_value) return;
          const key = '--' + safeSlug(d.setting_key);
          root.style.setProperty(key, d.setting_value);
        });
      }

      // 5. Direction
      if (window.ADMIN_UI.direction) {
        document.documentElement.dir = window.ADMIN_UI.direction;
        Admin.log('📐 Direction:', window.ADMIN_UI.direction);
      }

      // 6. Generate component styles
      generateComponentStyles();

      Admin.log('✅ Theme applied');
    } catch (e) {
      Admin.error('❌ syncThemeVarsFromAdminUI failed', e);
    }
  }

  function generateComponentStyles() {
    try {
      const themeData = window.ADMIN_UI?.theme;
      if (!themeData) return;

      const styleEl = ensureThemeStyleContainer();
      const rules = [];

      // Buttons
      if (Array.isArray(themeData.button_styles)) {
        themeData.button_styles.forEach(b => {
          if (!b?.slug) return;

          const slug = safeSlug(b.slug);
          const sel = `.btn-${slug}, .btn.${slug}`;

          let css = `${sel} {`;
          if (b.background_color) css += `background-color: ${b.background_color} !important;`;
          if (b.text_color) css += `color: ${b.text_color} !important;`;
          if (b.border_color && b.border_width)
            css += `border: ${b.border_width}px solid ${b.border_color} !important;`;
          if (b.border_radius) css += `border-radius: ${b.border_radius}px !important;`;
          if (b.padding) css += `padding: ${b.padding} !important;`;
          if (b.font_size) css += `font-size: ${b.font_size} !important;`;
          if (b.font_weight) css += `font-weight: ${b.font_weight} !important;`;
          css += 'cursor: pointer; display: inline-block; transition: all 0.2s;}';

          rules.push(css);

          // Hover
          if (b.hover_background_color || b.hover_text_color) {
            let hcss = `${sel}:hover {`;
            if (b.hover_background_color)
              hcss += `background-color: ${b.hover_background_color} !important;`;
            if (b.hover_text_color) hcss += `color: ${b.hover_text_color} !important;`;
            hcss += '}';
            rules.push(hcss);
          }
        });
      }

      // Cards
      if (Array.isArray(themeData.card_styles)) {
        themeData.card_styles.forEach(c => {
          if (!c?.slug) return;

          const slug = safeSlug(c.slug);
          const sel = `.card-${slug}, .card.${slug}`;

          let css = `${sel} {`;
          if (c.background_color) css += `background-color: ${c.background_color} !important;`;
          if (c.border_color && c.border_width)
            css += `border: ${c.border_width}px solid ${c.border_color} !important;`;
          if (c.border_radius) css += `border-radius: ${c.border_radius}px !important;`;
          if (c.padding) css += `padding: ${c.padding} !important;`;
          if (c.shadow_style) css += `box-shadow: ${c.shadow_style} !important;`;
          if (c.text_align) css += `text-align: ${c.text_align} !important;`;
          css += 'transition: all 0.2s;}';

          rules.push(css);

          // Hover
          if (c.hover_effect && c.hover_effect !== 'none') {
            let hcss = `${sel}:hover {`;
            switch (c.hover_effect) {
              case 'lift':
                hcss += 'transform: translateY(-6px); box-shadow: 0 10px 30px rgba(0,0,0,0.15);';
                break;
              case 'zoom':
                hcss += 'transform: scale(1.03);';
                break;
              case 'shadow':
                hcss += 'box-shadow: 0 12px 36px rgba(0,0,0,0.2);';
                break;
              case 'border':
                hcss += 'border-color: var(--primary-color, #6366f1);';
                break;
              case 'bright':
                hcss += 'filter: brightness(1.05);';
                break;
            }
            hcss += '}';
            rules.push(hcss);
          }
        });
      }

      styleEl.textContent = rules.join('\n');
      Admin.log(`✓ Generated ${rules.length} component rules`);
    } catch (e) {
      Admin.error('generateComponentStyles failed', e);
    }
  }

  // ════════════════════════════════════════════════════════════
  // I18N (INTERNATIONALIZATION)
  // ════════════════════════════════════════════════════════════
  
  window.I18nLoader = {
    cache: {},
    async loadTranslations(url) {
      if (this.cache[url]) {
        Admin.log('📦 Cached:', url);
        return this.cache[url];
      }
      try {
        Admin.log('📥 Loading:', url);
        const response = await fetch(url);
        if (!response.ok) throw new Error('Failed: ' + url);
        const data = await response.json();
        this.cache[url] = data;
        Admin.log('✓ Loaded:', url, '→', Object.keys(data).length, 'keys');
        return data;
      } catch (err) {
        Admin.warn('Translation load error:', url, err);
        return {};
      }
    }
  };

  Admin.i18n = Admin.i18n || {};

  (function (I18n) {
    I18n.getLang = () =>
      window.ADMIN_LANG || document.documentElement.lang || window.ADMIN_UI?.lang || 'en';

    I18n.mergeInjected = () => {
      if (!window.ADMIN_UI) window.ADMIN_UI = {};
      if (!window.ADMIN_UI.strings) window.ADMIN_UI.strings = {};

      if (window.__PageTranslations) {
        deepMerge(
          window.ADMIN_UI.strings,
          window.__PageTranslations.strings || window.__PageTranslations
        );
      }

      if (window.ADMIN_UI.__bootstrap_strings) {
        deepMerge(window.ADMIN_UI.strings, window.ADMIN_UI.__bootstrap_strings);
      }
    };

    function getNestedValue(obj, path) {
      if (!path || typeof path !== 'string') return undefined;

      const keys = path.split('.');
      let current = obj;

      for (const key of keys) {
        if (current && typeof current === 'object' && key in current) {
          current = current[key];
        } else {
          return undefined;
        }
      }

      return current;
    }

    I18n.applyTranslations = async (root = document) => {
      try {
        Admin.log('═══════════════════════════════════');
        Admin.log('🌐 Applying Translations');
        Admin.log('═══════════════════════════════════');

        const metas = root.querySelectorAll('meta[data-i18n-files]');
        let allTranslations = {};

        // Load all translation files
        for (const meta of metas) {
          const files = (meta.getAttribute('data-i18n-files') || '')
            .split(',')
            .map(f => f.trim())
            .filter(Boolean);

          for (const file of files) {
            const url = file.replace(/\{lang\}/g, I18n.getLang());

            try {
              const data = await I18nLoader.loadTranslations(url);

              if (data && typeof data === 'object') {
                Object.keys(data).forEach(key => {
                  if (!allTranslations[key]) {
                    allTranslations[key] = data[key];
                  } else if (typeof data[key] === 'object' && !Array.isArray(data[key])) {
                    deepMerge(allTranslations[key], data[key]);
                  } else {
                    allTranslations[key] = data[key];
                  }
                });
              }
            } catch (err) {
              Admin.warn('Failed:', url, err);
            }
          }
        }

        // Merge with ADMIN_UI.strings
        if (window.ADMIN_UI?.strings) {
          Admin.log('📥 Merging ADMIN_UI.strings');
          deepMerge(allTranslations, window.ADMIN_UI.strings);
        }

        Admin.log('📦 Total keys:', Object.keys(allTranslations).length);

        // Store globally
        window.TRANSLATIONS = allTranslations;

        // Translate helper
        const translateElement = (el, attr, prop = 'textContent') => {
          const key = el.getAttribute(attr);
          if (!key) return;

          let val = getNestedValue(allTranslations, key);
          if (val === undefined) val = allTranslations[key];

          if (val === undefined || val === null) {
            Admin.warn(`❌ Missing: [${key}]`);
            return;
          }

          val = String(val);

          if (prop === 'innerHTML') {
            el.innerHTML = val;
          } else if (prop === 'placeholder') {
            el.placeholder = val;
          } else if (prop === 'title') {
            el.title = val;
          } else if (prop === 'aria-label') {
            el.setAttribute('aria-label', val);
          } else {
            el[prop] = val;
          }
        };

        // Apply translations
        root.querySelectorAll('[data-i18n]').forEach(el => translateElement(el, 'data-i18n'));
        root
          .querySelectorAll('[data-i18n-placeholder]')
          .forEach(el => translateElement(el, 'data-i18n-placeholder', 'placeholder'));
        root
          .querySelectorAll('[data-i18n-title]')
          .forEach(el => translateElement(el, 'data-i18n-title', 'title'));
        root
          .querySelectorAll('[data-i18n-aria-label]')
          .forEach(el => translateElement(el, 'data-i18n-aria-label', 'aria-label'));
        root
          .querySelectorAll('[data-i18n-html]')
          .forEach(el => translateElement(el, 'data-i18n-html', 'innerHTML'));

        Admin.log('✅ Translations applied');

        window.dispatchEvent(
          new CustomEvent('admin:i18n:applied', {
            detail: { root, translations: allTranslations }
          })
        );
      } catch (err) {
        Admin.error('❌ applyTranslations failed', err);
      }
    };

    I18n.t = (key, fallback = '') => {
      if (!key) return fallback;

      if (window.TRANSLATIONS) {
        const val = getNestedValue(window.TRANSLATIONS, key);
        if (val !== undefined && val !== null) return String(val);
      }

      if (window.ADMIN_UI?.strings) {
        const val = getNestedValue(window.ADMIN_UI.strings, key);
        if (val !== undefined && val !== null) return String(val);

        const directVal = window.ADMIN_UI.strings[key];
        if (directVal !== undefined && directVal !== null) return String(directVal);
      }

      return fallback;
    };

    I18n.translate = I18n.t;
  })(Admin.i18n);

  // Expose for compatibility
  window._admin = window._admin || {};
  window._admin.applyTranslations = Admin.i18n.applyTranslations;
  window._admin.t = Admin.i18n.t;
  window.t = Admin.i18n.t;

  // ═��══════════════════════════════════════════════════════════
  // SCRIPT RUNNER (FIXED)
  // ════════════════════════════════════════════════════════════
  
  function runScripts(container) {
    const scripts = [...container.querySelectorAll('script')];

    Admin.log('🔧 runScripts:', scripts.length, 'scripts');

    scripts.forEach((old, idx) => {
      try {
        if (old.getAttribute('data-no-run') === '1') {
          Admin.log(`  [${idx}] Skipped (data-no-run)`);
          return;
        }

        const type = (old.type || 'text/javascript').toLowerCase();
        if (type !== 'text/javascript' && type !== 'application/javascript') {
          Admin.log(`  [${idx}] Skipped (type: ${type})`);
          return;
        }

        if (old.src) {
          // External script
          const srcUrl = old.src;

          if (document.querySelector(`script[src="${srcUrl}"]`)) {
            Admin.log(`  [${idx}] Already loaded: ${srcUrl}`);
            return;
          }

          Admin.log(`  [${idx}] Loading: ${srcUrl}`);

          const s = document.createElement('script');
          s.src = srcUrl;
          s.async = false;
          s.defer = false;

          s.onload = () => {
            Admin.log(`    ✓ Loaded: ${srcUrl}`);
            window.dispatchEvent(
              new CustomEvent('admin:script:loaded', {
                detail: { src: srcUrl }
              })
            );
          };

          s.onerror = err => {
            Admin.error(`    ✗ Failed: ${srcUrl}`, err);
          };

          document.body.appendChild(s);
        } else {
          // Inline script
          const code = old.textContent || old.innerHTML;

          if (!code.trim()) {
            Admin.log(`  [${idx}] Empty inline`);
            return;
          }

          Admin.log(`  [${idx}] Running inline (${code.length} chars)`);

          try {
            const fn = new Function(code);
            fn.call(window);
            Admin.log(`    ✓ Executed`);
          } catch (evalErr) {
            Admin.error(`    ✗ Error:`, evalErr);
            Admin.error('Code:', code.substring(0, 200));
          }
        }
      } catch (e) {
        Admin.error(`  [${idx}] Error:`, e);
      }
    });

    Admin.log('✓ runScripts completed');
  }
  Admin.runScripts = runScripts;

  // ════════════════════════════════════════════════════════════
  // FETCH & INSERT (FIXED)
  // ════════════════════════════════════════════════════════════
  
  Admin.fetchAndInsert = async (url, targetSelector) => {
    Admin.log('═══════════════════════════════════');
    Admin.log('📥 fetchAndInsert');
    Admin.log('  URL:', url);
    Admin.log('  Target:', targetSelector);
    Admin.log('═══════════════════════════════════');

    const target = document.querySelector(targetSelector);
    if (!target) throw new Error('Target not found: ' + targetSelector);

    const loader = document.createElement('div');
    loader.className = 'inline-loader';
    loader.textContent = Admin.i18n.t('loading', 'Loading...');
    target.innerHTML = '';
    target.appendChild(loader);

    try {
      const response = await fetch(url, {
        credentials: 'same-origin',
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      });

      if (!response.ok) throw new Error('HTTP ' + response.status);

      const html = await response.text();
      Admin.log('✓ Received:', html.length, 'bytes');

      target.innerHTML = html;
      Admin.log('✓ HTML inserted');

      // Run scripts FIRST
      Admin.runScripts(target);

      // Wait for scripts to initialize
      await new Promise(resolve => setTimeout(resolve, 150));

      // Apply permissions
      Admin.applyPermsToContainer(target);

      // Apply theme
      syncThemeVarsFromAdminUI();

      // Apply translations LAST
      await Admin.i18n.applyTranslations(target);

      Admin.log('✅ fetchAndInsert completed');

      window.dispatchEvent(
        new CustomEvent('admin:content:loaded', {
          detail: { target, url }
        })
      );

      return target;
    } catch (err) {
      Admin.error('❌ fetchAndInsert error:', err);
      target.innerHTML =
        '<div style="padding:20px;color:#c0392b;text-align:center;">Error loading content</div>';
      throw err;
    }
  };

  // ════════════════════════════════════════════════════════════
  // OTHER HELPERS
  // ════════════════════════════════════════════════════════════
  
  Admin.fetchJson = (url, options = {}) => {
    if (!options.credentials) options.credentials = 'same-origin';
    options.headers = options.headers || {};
    return fetch(url, options).then(res =>
      res.text().then(txt => {
        let parsed = null;
        try {
          parsed = txt ? JSON.parse(txt) : null;
        } catch {}
        return { ok: res.ok, status: res.status, data: parsed, raw: txt };
      })
    );
  };

  Admin.formAjax = (form, options = {}) => {
    if (!form) throw new Error('form element required');
    if (form._adminFormAjaxBound) return;
    form._adminFormAjaxBound = true;

    form.addEventListener('submit', ev => {
      ev.preventDefault();

      const submits = [...form.querySelectorAll('[type="submit"], button[data-submit]')];
      submits.forEach(b => (b.disabled = true));

      const fd = new FormData(form);
      if (!fd.get('csrf_token')) {
        const cs = Admin.getCsrf();
        if (cs) fd.set('csrf_token', cs);
      }

      Admin.fetchJson(form.action || window.location.href, {
        method: 'POST',
        body: fd
      })
        .then(res => {
          if (res?.data && (res.data.success || res.data.ok)) {
            if (typeof options.onSuccess === 'function') options.onSuccess(res.data);
            Admin.log('formAjax success', res.data);
          } else {
            if (typeof options.onError === 'function') options.onError(res);
            Admin.warn('formAjax failed', res);
          }
        })
        .catch(err => {
          Admin.error('formAjax error', err);
          if (typeof options.onError === 'function') options.onError({ error: err });
        })
        .finally(() => submits.forEach(b => (b.disabled = false)));
    });
  };

  Admin.getCsrf = () => {
    const el = document.querySelector('input[name="csrf_token"]');
    if (el) return el.value;
    const meta = document.querySelector('meta[name="csrf-token"]');
    if (meta) return meta.getAttribute('content');
    return window.ADMIN_UI?.csrf_token || '';
  };

  // ════════════════════════════════════════════════════════════
  // SIDEBAR
  // ════════════════════════════════════════════════════════════
  
  function initSidebar() {
    try {
      if (window.SidebarToggle?.__installed) return;

      const toggle = document.getElementById('sidebarToggle');
      const sidebar =
        document.getElementById('adminSidebar') || document.querySelector('.admin-sidebar');
      const backdrop = document.querySelector('.sidebar-backdrop');

      if (!toggle || !sidebar) return;

      if (!toggle._adminSidebarBound) {
        toggle.addEventListener(
          'click',
          e => {
            e.preventDefault();
            e.stopPropagation();
            document.body.classList.toggle('sidebar-open');
            toggle.setAttribute(
              'aria-expanded',
              document.body.classList.contains('sidebar-open') ? 'true' : 'false'
            );
          },
          { passive: false }
        );
        toggle._adminSidebarBound = true;
      }

      if (backdrop && !backdrop._adminBound) {
        backdrop.addEventListener('click', () => {
          document.body.classList.remove('sidebar-open');
          toggle.setAttribute('aria-expanded', 'false');
        });
        backdrop._adminBound = true;
      }

      sidebar.addEventListener('click', e => {
        const a = e.target?.closest('a');
        if (!a) return;
        const href = a.getAttribute('href') || '';
        if (
          window.innerWidth <= 900 &&
          href &&
          !href.startsWith('#') &&
          !href.startsWith('javascript:')
        ) {
          setTimeout(() => {
            document.body.classList.remove('sidebar-open');
            toggle.setAttribute('aria-expanded', 'false');
          }, 120);
        }
      });

      if (!document._adminSidebarKeyBound) {
        document.addEventListener('keydown', e => {
          if (
            (e.key === 'Escape' || e.keyCode === 27) &&
            document.body.classList.contains('sidebar-open')
          ) {
            document.body.classList.remove('sidebar-open');
            toggle.setAttribute('aria-expanded', 'false');
          }
        });
        document._adminSidebarKeyBound = true;
      }
    } catch (e) {
      Admin.warn('initSidebar error', e);
    }
  }

  // ════════════════════════════════════════════════════════════
  // NOTIFICATIONS
  // ════════════════════════════════════════════════════════════
  
  function initNotifications() {
    try {
      const btn = document.getElementById('notifBtn');
      const cnt = document.getElementById('notifCount');
      if (!btn || !cnt) return;

      btn.addEventListener('click', e => {
        e.preventDefault();
        const count = parseInt(cnt.textContent || '0', 10) || 0;
        alert(
          Admin.i18n
            .t('notifications_popup', 'You have {count} notifications.')
            .replace('{count}', count)
        );
      });
    } catch (e) {
      Admin.warn('initNotifications error', e);
    }
  }

  // ════════════════════════════════════════════════════════════
  // SEARCH
  // ═════════════════════════════════════════���══════════════════
  
  function initSearch() {
    try {
      const input = document.getElementById('adminSearch');
      const btn = document.getElementById('searchBtn');

      const run = () => {
        if (!input) return;
        const q = input.value.trim();
        if (!q) return;
        window.location.href = '/admin/search.php?q=' + encodeURIComponent(q);
      };

      if (input)
        input.addEventListener('keydown', e => {
          if (e.key === 'Enter') run();
        });
      if (btn)
        btn.addEventListener('click', e => {
          e.preventDefault();
          run();
        });
    } catch (e) {
      Admin.warn('initSearch error', e);
    }
  }

  // ════════════════════════════════════════════════════════════
  // PAGE SYSTEM
  // ════════════════════════════════════════════════════════════
  
  Admin.page = (() => {
    const modules = {};
    return {
      register: (name, fn) => {
        modules[name] = fn;
      },
      run: (name, ctx) => {
        const fn = modules[name];
        if (typeof fn === 'function') {
          try {
            fn(ctx || {});
            Admin.log('Admin.page.run', name);
          } catch (e) {
            Admin.error('Admin.page.run error ' + name, e);
          }
        }
      },
      _modules: modules
    };
  })();

  function readMetaFrom(root = document) {
    const meta = root.querySelector('meta[data-page], meta[data-assets-js], meta[data-assets-css]');
    if (!meta) return null;
    return {
      page: meta.getAttribute('data-page') || meta.dataset.page,
      css: meta.getAttribute('data-assets-css') || meta.dataset.assetsCss,
      js: meta.getAttribute('data-assets-js') || meta.dataset.assetsJs
    };
  }

  async function initPageFromFragment(root) {
    const info = readMetaFrom(root);
    if (!info) {
      Admin.applyPermsToContainer(root);
      await Admin.i18n.applyTranslations(root);
      syncThemeVarsFromAdminUI();
      return;
    }

    const cssList = info.css
      ? info.css
          .split(',')
          .map(s => s.trim())
          .filter(Boolean)
      : [];
    const jsList = info.js
      ? info.js
          .split(',')
          .map(s => s.trim())
          .filter(Boolean)
      : [];

    try {
      await Promise.all(cssList.map(Admin.asset.loadCss));
      await Promise.all(jsList.map(Admin.asset.loadJs));

      Admin.applyPermsToContainer(root);
      syncThemeVarsFromAdminUI();
      await Admin.i18n.applyTranslations(root);

      if (info.page && Admin.page._modules[info.page]) {
        Admin.page.run(info.page, { meta: root.querySelector('meta[data-page]') });
      }
    } catch (err) {
      Admin.warn('initPageFromFragment failed', err);
    }
  }
  Admin.initPageFromFragment = initPageFromFragment;

  // ════════════════════════════════════════════════════════════
  // MODAL
  // ════════════════════════════════════════════════════════════
  
  Admin.openModal = (urlOrHtml, options = {}) => {
    return new Promise((resolve, reject) => {
      try {
        let backdrop = document.querySelector('.admin-modal-backdrop');
        if (!backdrop) {
          backdrop = document.createElement('div');
          backdrop.className = 'admin-modal-backdrop';
          backdrop.style.cssText =
            'position:fixed;inset:0;background:rgba(0,0,0,0.35);display:flex;align-items:center;justify-content:center;z-index:14000;padding:16px;overflow:auto;';
          document.body.appendChild(backdrop);
        }

        const panel = document.createElement('div');
        panel.className = 'admin-modal-panel';
        panel.style.cssText =
          'width:920px;max-width:100%;max-height:90vh;overflow:auto;background:#fff;border-radius:8px;box-shadow:0 8px 30px rgba(0,0,0,0.2);position:relative;';
        backdrop.innerHTML = '';
        backdrop.appendChild(panel);

        const close = () => {
          backdrop.remove();
          resolve(null);
        };

        if (
          typeof urlOrHtml === 'string' &&
          urlOrHtml.indexOf('<') === -1 &&
          (urlOrHtml.startsWith('/') || urlOrHtml.match(/^https?:\/\//))
        ) {
          fetch(urlOrHtml, {
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
          })
            .then(res => {
              if (!res.ok) throw new Error('HTTP ' + res.status);
              return res.text();
            })
            .then(async html => {
              panel.innerHTML = html;
              runScripts(panel);
              syncThemeVarsFromAdminUI();
              await Admin.i18n.applyTranslations(panel);
              if (options.onOpen)
                try {
                  options.onOpen(panel);
                } catch (e) {
                  Admin.error(e);
                }
            })
            .catch(err => {
              Admin.error('openModal fetch failed', err);
              panel.innerHTML =
                '<div style="padding:20px;color:#c0392b">Failed to load</div>';
            });
        } else {
          panel.innerHTML = urlOrHtml || '';
          runScripts(panel);
          syncThemeVarsFromAdminUI();
          Admin.i18n.applyTranslations(panel);
          if (options.onOpen)
            try {
              options.onOpen(panel);
            } catch (e) {
              Admin.error(e);
            }
        }

        backdrop.addEventListener('click', ev => {
          if (ev.target === backdrop) close();
        });

        document.addEventListener('keydown', function onKey(e) {
          if (e.key === 'Escape') {
            document.removeEventListener('keydown', onKey);
            close();
          }
        });
      } catch (err) {
        reject(err);
      }
    });
  };

  // ════════════════════════════════════════════════════════════
  // TOAST NOTIFICATIONS
  // ════════════════════════════════════════════════════════════
  
  Admin.notify = (msg, opts = {}) => {
    const type = opts.type || 'info';
    const timeout = opts.timeout || 3000;

    let container = document.getElementById('admin-toast-container');
    if (!container) {
      container = document.createElement('div');
      container.id = 'admin-toast-container';
      container.style.cssText = 'position:fixed;top:20px;right:20px;z-index:99999;';
      document.body.appendChild(container);
    }

    const toast = document.createElement('div');
    toast.className = 'admin-toast admin-toast-' + type;
    toast.style.cssText =
      'background:#1f2937;color:#fff;padding:12px 20px;margin-bottom:10px;border-radius:6px;box-shadow:0 4px 12px rgba(0,0,0,0.15);min-width:250px;';
    if (type === 'success') toast.style.background = '#10b981';
    if (type === 'error') toast.style.background = '#ef4444';
    toast.textContent = msg;

    container.appendChild(toast);

    setTimeout(() => {
      toast.style.transition = 'opacity 0.3s';
      toast.style.opacity = '0';
      setTimeout(() => toast.remove(), 300);
    }, timeout);
  };

  // ════════════════════════════════════════════════════════════
  // INITIALIZATION
  // ════════════════════════════════════════════════════════════
  
  async function init() {
    Admin.log('═══════════════════════════════════');
    Admin.log('🚀 Admin Core v' + Admin.version);
    Admin.log('═══════════════════════════════════');

    try {
      if (window.ADMIN_UI?.user) {
        Admin.ADMIN_USER = window.ADMIN_UI.user;
        if (!Array.isArray(Admin.ADMIN_USER.permissions)) {
          Admin.ADMIN_USER.permissions = Admin.ADMIN_USER.permissions
            ? [Admin.ADMIN_USER.permissions]
            : [];
        }
      }
    } catch (e) {
      Admin.warn('sync user failed', e);
    }

    try {
      syncThemeVarsFromAdminUI();
    } catch (e) {
      Admin.error('theme sync failed', e);
    }

    try {
      Admin.i18n.mergeInjected();
    } catch (e) {
      Admin.warn('i18n merge failed', e);
    }

    try {
      initSidebar();
    } catch (e) {
      Admin.warn('initSidebar failed', e);
    }

    try {
      initNotifications();
    } catch (e) {
      Admin.warn('initNotifications failed', e);
    }

    try {
      initSearch();
    } catch (e) {
      Admin.warn('initSearch failed', e);
    }

    try {
      Admin.applyPermsToContainer(document);
    } catch (e) {
      Admin.warn(e);
    }

    try {
      const meta = document.querySelector('meta[data-page]');
      if (meta) {
        const pageName = meta.getAttribute('data-page') || meta.dataset.page;
        const css = meta.getAttribute('data-assets-css') || meta.dataset.assetsCss || '';
        const js = meta.getAttribute('data-assets-js') || meta.dataset.assetsJs || '';
        const cssList = css
          ? css
              .split(',')
              .map(s => s.trim())
              .filter(Boolean)
          : [];
        const jsList = js
          ? js
              .split(',')
              .map(s => s.trim())
              .filter(Boolean)
          : [];

        await Promise.all(cssList.map(Admin.asset.loadCss));
        await Promise.all(jsList.map(Admin.asset.loadJs));
        await Admin.i18n.applyTranslations(document);

        if (pageName && Admin.page._modules[pageName]) {
          Admin.page.run(pageName, { meta });
        }
      } else {
        await Admin.i18n.applyTranslations(document);
      }
    } catch (e) {
      Admin.error('auto init page failed', e);
    }

    Admin.log('✅ Admin Core Ready');
  }

  domReady(init);

  // Expose theme functions
  Admin.syncThemeVarsFromAdminUI = syncThemeVarsFromAdminUI;
  Admin.generateComponentStyles = generateComponentStyles;
})();