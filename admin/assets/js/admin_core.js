/*!
 * admin/assets/js/admin_core.js
 * Final production-ready admin client core (server-driven theme & i18n).
 *
 * Responsibilities:
 * - Reads window.ADMIN_UI injected by header.php/bootstrap_admin_ui.php
 * - Applies CSS variables from theme.colors_map / theme.colors
 * - Loads fonts, generates component CSS rules (.btn-<slug>, .card-<slug>) from DB tables
 * - Merges translations, provides fetch/form helpers, RBAC utilities, sidebar/search/notifications
 *
 * Save as UTF-8 without BOM.
 */
(function () {
  'use strict';

  if (window.Admin && window.Admin.__installed) return;
  window.Admin = window.Admin || {};
  Admin.__installed = true;

  // Toggleable logger
  Admin.debug = false;
  Admin.log = function () { if (Admin.debug && console && console.log) console.log.apply(console, arguments); };
  Admin.warn = function () { if (console && console.warn) console.warn.apply(console, arguments); };
  Admin.error = function () { if (console && console.error) console.error.apply(console, arguments); };

  // DOM ready helper
  function domReady(fn) {
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', fn);
    else setTimeout(fn, 0);
  }
  Admin.domReady = domReady;

  // Utilities
  function safeSlug(s) {
    return String(s || '').toLowerCase().replace(/[^a-z0-9_-]/g, '-').replace(/-+/g, '-').replace(/^-|-$/g, '');
  }

  function deepMerge(dest, src) {
    if (!src || typeof src !== 'object') return dest || {};
    dest = dest || {};
    Object.keys(src).forEach(function (k) {
      var sv = src[k];
      if (sv && typeof sv === 'object' && !Array.isArray(sv)) {
        dest[k] = dest[k] || {};
        deepMerge(dest[k], sv);
      } else {
        dest[k] = sv;
      }
    });
    return dest;
  }

  // -----------------------
  // Color normalization helpers
  // -----------------------
  function normalizeExplicitColor(v) {
    if (v === undefined || v === null) return null;
    var s = String(v).trim();
    if (!s) return null;
    // common typo "transpa" -> "transparent"
    if (/^transpa/i.test(s)) return 'transparent';
    // css var
    if (/^var\(--/.test(s)) return s;
    // rgb/rgba/hsl/hsla
    if (/^(rgb|rgba|hsl|hsla)\(/i.test(s)) return s;
    // hex with '#'
    if (/^#([A-Fa-f0-9]{3}|[A-Fa-f0-9]{6})$/.test(s)) return s.toUpperCase();
    // hex without '#'
    if (/^[A-Fa-f0-9]{6}$/.test(s)) return ('#' + s).toUpperCase();
    if (/^[A-Fa-f0-9]{3}$/.test(s)) return ('#' + s).toUpperCase();
    // simple color names
    if (/^[a-z\-]+$/i.test(s)) return s.toLowerCase();
    return null;
  }

  function looksLikeColor(v) {
    return !!normalizeExplicitColor(v);
  }

  // -----------------------
  // Admin user / RBAC
  // -----------------------
  Admin.ADMIN_USER = window.ADMIN_USER || (window.ADMIN_UI && window.ADMIN_UI.user) || {};
  (function normalizeAdminUser() {
    var u = Admin.ADMIN_USER;
    if (!u) { Admin.ADMIN_USER = {}; return; }
    if (!Array.isArray(u.permissions)) {
      if (u.permissions && typeof u.permissions === 'string') u.permissions = [u.permissions];
      else u.permissions = [];
    }
    if (!u.role && u.role_id) u.role = u.role_id;
    if (typeof u.role === 'string' && /^\d+$/.test(u.role)) u.role = parseInt(u.role, 10);
  })();

  Admin.isSuper = function () {
    try {
      var r = Admin.ADMIN_USER && (Admin.ADMIN_USER.role || Admin.ADMIN_USER.role_id);
      if (!r) return false;
      return (r === 1 || r === '1' || String(r).toLowerCase() === 'super_admin' || String(r).toLowerCase() === 'admin');
    } catch (e) { return false; }
  };

  Admin.can = function (perm) {
    try {
      if (!perm) return true;
      if (Admin.isSuper()) return true;
      var perms = Admin.ADMIN_USER && Array.isArray(Admin.ADMIN_USER.permissions) ? Admin.ADMIN_USER.permissions : [];
      if (Array.isArray(perm)) return perm.some(function (p) { return perms.indexOf(p) !== -1; });
      if (String(perm).indexOf('|') !== -1) {
        var parts = String(perm).split('|').map(function (s) { return s.trim(); }).filter(Boolean);
        return parts.some(function (p) { return perms.indexOf(p) !== -1; });
      }
      return perms.indexOf(perm) !== -1;
    } catch (e) { Admin.warn('Admin.can error', e); return false; }
  };

  Admin.canAll = function (perm) {
    try {
      if (!perm) return true;
      if (Admin.isSuper()) return true;
      var perms = Admin.ADMIN_USER && Array.isArray(Admin.ADMIN_USER.permissions) ? Admin.ADMIN_USER.permissions : [];
      var parts = Array.isArray(perm) ? perm : String(perm).split('|').map(function (s) { return s.trim(); }).filter(Boolean);
      return parts.every(function (p) { return perms.indexOf(p) !== -1; });
    } catch (e) { Admin.warn('Admin.canAll error', e); return false; }
  };

  Admin.applyPermsToContainer = function (container) {
    container = container || document;
    try {
      Array.prototype.slice.call(container.querySelectorAll('[data-require-perm]')).forEach(function (el) {
        var spec = (el.getAttribute('data-require-perm') || '').trim();
        if (!spec) return;
        if (!Admin.can(spec)) {
          if (el.getAttribute('data-remove-without-perm') === '1') el.remove(); else el.style.display = 'none';
        } else { el.style.display = ''; }
      });
      Array.prototype.slice.call(container.querySelectorAll('[data-require-all]')).forEach(function (el) {
        var spec = (el.getAttribute('data-require-all') || '').trim();
        if (!spec) return;
        if (!Admin.canAll(spec)) {
          if (el.getAttribute('data-remove-without-perm') === '1') el.remove(); else el.style.display = 'none';
        } else { el.style.display = ''; }
      });
      Array.prototype.slice.call(container.querySelectorAll('[data-hide-without-perm]')).forEach(function (el) {
        var spec = (el.getAttribute('data-hide-without-perm') || '').trim();
        if (!spec) return;
        if (!Admin.can(spec)) el.remove();
      });
    } catch (e) { Admin.warn('applyPermsToContainer error', e); }
  };

  // -----------------------
  // Asset loader
  // -----------------------
  Admin.asset = (function () {
    var loadedCss = {};
    var loadedJs = {};
    var loadingJs = {};

    function loadCss(href) {
      if (!href) return Promise.resolve();
      if (loadedCss[href] || document.querySelector('link[rel="stylesheet"][href="' + href + '"]')) {
        loadedCss[href] = true;
        return Promise.resolve();
      }
      return new Promise(function (resolve) {
        var l = document.createElement('link');
        l.rel = 'stylesheet';
        l.href = href;
        l.onload = function () { loadedCss[href] = true; resolve(); };
        l.onerror = function () { Admin.warn('CSS load failed', href); loadedCss[href] = true; resolve(); };
        document.head.appendChild(l);
      });
    }

    function loadJs(src) {
      if (!src) return Promise.resolve();
      if (loadedJs[src] || document.querySelector('script[src="' + src + '"]')) {
        loadedJs[src] = true;
        return Promise.resolve();
      }
      if (loadingJs[src]) return loadingJs[src];
      var p = new Promise(function (resolve) {
        var s = document.createElement('script');
        s.src = src;
        s.defer = true;
        s.onload = function () { loadedJs[src] = true; delete loadingJs[src]; resolve(); };
        s.onerror = function () { Admin.warn('JS load failed', src); delete loadingJs[src]; resolve(); };
        document.head.appendChild(s);
      });
      loadingJs[src] = p;
      return p;
    }

    return { loadCss: loadCss, loadJs: loadJs };
  })();

  // -----------------------
  // Theme: apply vars & generate component styles
  // -----------------------
  function ensureThemeStyleContainer() {
    var style = document.getElementById('theme-component-styles');
    if (!style) {
      style = document.createElement('style');
      style.id = 'theme-component-styles';
      // append near end of head so it overrides earlier CSS
      document.head.appendChild(style);
    }
    return style;
  }

  function syncThemeVarsFromAdminUI() {
    try {
      if (!window.ADMIN_UI || !window.ADMIN_UI.theme) return;
      var theme = window.ADMIN_UI.theme || {};
      var root = document.documentElement;

      // Prefer server-provided map for direct assignments
      var cmap = theme.colors_map && typeof theme.colors_map === 'object' ? theme.colors_map : null;

      if (cmap) {
        Object.keys(cmap).forEach(function (k) {
          try {
            var rawVal = cmap[k];
            var norm = normalizeExplicitColor(rawVal);
            var valToSet = norm || rawVal;
            if ((valToSet === null || valToSet === undefined || String(valToSet).trim() === '') && valToSet !== 'transparent') return;
            root.style.setProperty('--theme-' + safeSlug(k), String(valToSet));
          } catch (e) { /* ignore per-key failures */ }
        });
      } else if (Array.isArray(theme.colors)) {
        theme.colors.forEach(function (c) {
          if (!c || !c.setting_key) return;
          var rawKey = String(c.setting_key || '');
          rawKey = rawKey.replace(/(_color|_main|_bg|_background)$/i, '');
          rawKey = rawKey.replace(/_/g, '-');
          var key = '--theme-' + safeSlug(rawKey);
          var val = c.color_value || null;
          var norm = normalizeExplicitColor(val);
          var valToSet = norm || val;
          if (valToSet !== null && valToSet !== undefined && String(valToSet).trim() !== '') {
            try { root.style.setProperty(key, String(valToSet)); } catch (e) {}
          }
        });
      }

      // Provide canonical fallbacks many templates expect
      try {
        var map = cmap || (theme.colors_map ? theme.colors_map : {});
        if (map['primary'] && !getComputedStyle(root).getPropertyValue('--theme-primary')) root.style.setProperty('--theme-primary', map['primary']);
        if (map['primary-hover'] && !getComputedStyle(root).getPropertyValue('--theme-primary-hover')) root.style.setProperty('--theme-primary-hover', map['primary-hover']);
        if (map['background'] && !getComputedStyle(root).getPropertyValue('--theme-background')) root.style.setProperty('--theme-background', map['background']);
        if (map['text-primary'] && !getComputedStyle(root).getPropertyValue('--theme-text-primary')) root.style.setProperty('--theme-text-primary', map['text-primary']);
      } catch (e) { /* ignore */ }

      // Designs -> css vars
      if (theme.designs && typeof theme.designs === 'object') {
        Object.keys(theme.designs).forEach(function (k) {
          try {
            var val = theme.designs[k];
            if (val === null || val === undefined) return;
            root.style.setProperty('--theme-' + safeSlug(k), String(val));
          } catch (e) {}
        });
      }

      // Fonts: choose body font then load candidates
      if (Array.isArray(theme.fonts) && theme.fonts.length) {
        var bodyFont = null, first = theme.fonts[0];
        theme.fonts.forEach(function (f) { if (!f) return; if (f.category === 'body' && f.font_family) bodyFont = f; });
        var chosen = bodyFont || first;
        if (chosen && chosen.font_family) {
          try { root.style.setProperty('--theme-font-family', chosen.font_family); } catch (e) {}
        }
        theme.fonts.forEach(function (f) {
          if (!f) return;
          if (f.font_url) Admin.asset.loadCss(f.font_url);
          else if (f.font_family && String(f.font_family).trim()) {
            var family = String(f.font_family).trim();
            var gurl = 'https://fonts.googleapis.com/css2?family=' + encodeURIComponent(family.replace(/\s+/g, '+')) + '&display=swap';
            Admin.asset.loadCss(gurl);
          }
        });
      }

      // Direction
      if (window.ADMIN_UI.direction) {
        try { document.documentElement.dir = window.ADMIN_UI.direction; } catch (e) {}
      }

      // Regenerate component CSS
      generateComponentStyles();
    } catch (e) {
      Admin.warn('syncThemeVarsFromAdminUI failed', e);
    }
  }

  function generateComponentStyles() {
    try {
      var theme = window.ADMIN_UI && window.ADMIN_UI.theme ? window.ADMIN_UI.theme : null;
      var styleEl = ensureThemeStyleContainer();
      var rules = [];

      // Resolve color preference: colors_map -> explicit normalized -> transparent
      function resolveColor(explicit, typeKey, slugKey) {
        var map = theme && theme.colors_map ? theme.colors_map : {};
        if (typeKey && map && map[typeKey]) return 'var(--theme-' + safeSlug(typeKey) + ')';
        if (slugKey && map && map[slugKey]) return 'var(--theme-' + safeSlug(slugKey) + ')';
        var n = normalizeExplicitColor(explicit);
        if (n) return n;
        return explicit || 'transparent';
      }

      // Buttons: high-specificity (.btn.btn-<slug>) and fallback single-class
      if (theme && Array.isArray(theme.buttons)) {
        theme.buttons.forEach(function (b) {
          if (!b || !b.slug) return;
          var slug = safeSlug(b.slug);
          var high = '.btn.btn-' + slug;
          var low = '.btn-' + slug;

          var bg = resolveColor(b.background_color, b.button_type, b.slug);
          var explicitText = normalizeExplicitColor(b.text_color) || null;
          var color = explicitText ? explicitText : 'var(--theme-text-primary)';
          var borderColor = resolveColor(b.border_color, b.button_type + '-border', b.slug + '-border') || 'var(--theme-border)';
          var bw = (typeof b.border_width !== 'undefined' ? b.border_width : 0);
          var br = ((b.border_radius || 6)) + 'px';
          var padding = b.padding || '12px 24px';
          var fz = b.font_size || '14px';
          var fw = b.font_weight || '600';

          rules.push(high + '{' +
            'background:' + bg + ' !important;' +
            'color:' + color + ' !important;' +
            'border:' + (bw || 0) + 'px solid ' + borderColor + ' !important;' +
            'border-radius:' + br + ' !important;' +
            'padding:' + padding + ' !important;' +
            'font-size:' + fz + ' !important;' +
            'font-weight:' + fw + ' !important;' +
            'cursor:pointer;display:inline-block;text-decoration:none;}');

          rules.push(low + '{' +
            'background:' + bg + ';' +
            'color:' + color + ';' +
            'border:' + (bw || 0) + 'px solid ' + borderColor + ';' +
            'border-radius:' + br + ';' +
            'padding:' + padding + ';' +
            'font-size:' + fz + ';' +
            'font-weight:' + fw + ';' +
            'cursor:pointer;display:inline-block;text-decoration:none;}');

          if (b.hover_background_color || b.hover_text_color || b.hover_border_color) {
            var hoverBg = resolveColor(b.hover_background_color, b.button_type, b.slug) || bg;
            var hoverExplicitText = normalizeExplicitColor(b.hover_text_color) || null;
            var hoverColor = hoverExplicitText ? hoverExplicitText : 'var(--theme-text-primary)';
            var hoverBorder = resolveColor(b.hover_border_color, b.button_type + '-border', b.slug + '-border') || borderColor;
            rules.push(high + ':hover, ' + low + ':hover{ background:' + hoverBg + ' !important; color:' + hoverColor + ' !important; border-color:' + hoverBorder + ' !important; }');
          }
        });
      }

      // Cards: same pattern
      if (theme && Array.isArray(theme.cards)) {
        theme.cards.forEach(function (c) {
          if (!c || !c.slug) return;
          var slug = safeSlug(c.slug);
          var high = '.card.card-' + slug;
          var low = '.card-' + slug;

          var bg = resolveColor(c.background_color, c.card_type, c.slug) || 'var(--theme-surface)';
          var border = resolveColor(c.border_color, c.card_type + '-border', c.slug + '-border') || 'var(--theme-border)';
          var bw = (typeof c.border_width !== 'undefined' ? c.border_width : 1);
          var br = ((c.border_radius || 8)) + 'px';
          var pad = c.padding || '16px';
          var shadow = (c.shadow_style && c.shadow_style !== 'none') ? c.shadow_style : 'none';
          var ta = c.text_align || 'left';

          rules.push(high + '{ background:' + bg + ' !important; border:' + (bw || 0) + 'px solid ' + border + ' !important; border-radius:' + br + ' !important; padding:' + pad + ' !important; box-shadow:' + shadow + ' !important; text-align:' + ta + ' !important; }');
          rules.push(low + '{ background:' + bg + '; border:' + (bw || 0) + 'px solid ' + border + '; border-radius:' + br + '; padding:' + pad + '; box-shadow:' + shadow + '; text-align:' + ta + '; }');

          if (c.hover_effect && c.hover_effect !== 'none') {
            var hoverRule = '';
            switch (c.hover_effect) {
              case 'lift': hoverRule = 'transform:translateY(-6px);box-shadow:0 10px 30px rgba(0,0,0,0.08);'; break;
              case 'zoom': hoverRule = 'transform:scale(1.02);'; break;
              case 'shadow': hoverRule = 'box-shadow:0 12px 36px rgba(0,0,0,0.12);'; break;
              case 'border': hoverRule = 'border-color:rgba(0,0,0,0.12);'; break;
              case 'bright': hoverRule = 'filter:brightness(1.03);'; break;
              default: hoverRule = ''; break;
            }
            if (hoverRule) rules.push(high + ':hover{ transition: all 180ms ease; ' + hoverRule + ' }');
          }
        });
      }

      styleEl.textContent = rules.join('\n');
    } catch (e) {
      Admin.warn('generateComponentStyles failed', e);
    }
  }

  // -----------------------
  // i18n: merging and lookup
  // -----------------------
  Admin.i18n = Admin.i18n || {};
  (function (I18n) {
    function getLang() {
      return window.ADMIN_LANG || (document.documentElement && document.documentElement.lang) || (window.ADMIN_UI && window.ADMIN_UI.lang) || 'en';
    }

    I18n.mergeInjected = function (pageName) {
      try {
        window.ADMIN_UI = window.ADMIN_UI || {};
        window.ADMIN_UI.strings = window.ADMIN_UI.strings || {};

        // page-specific injected object (if any)
        var pageTr = window.__PageTranslations || null;
        if (pageTr && typeof pageTr === 'object') {
          var src = pageTr.strings && typeof pageTr.strings === 'object' ? pageTr.strings : pageTr;
          deepMerge(window.ADMIN_UI.strings, src || {});
          if (pageTr.direction) window.ADMIN_UI.direction = pageTr.direction;
        }

        if (window.ADMIN_UI && window.ADMIN_UI.__bootstrap_strings && typeof window.ADMIN_UI.__bootstrap_strings === 'object') {
          deepMerge(window.ADMIN_UI.strings, window.ADMIN_UI.__bootstrap_strings);
        }

        if (window.ADMIN_UI && window.ADMIN_UI.direction) {
          try { document.documentElement.dir = window.ADMIN_UI.direction; } catch (e) {}
        }
      } catch (e) { Admin.warn('i18n.mergeInjected error', e); }
    };

    I18n.getLang = getLang;
    I18n.loadPaths = function (paths, root, pageName) {
      try { I18n.mergeInjected(pageName); } catch (e) { Admin.warn('i18n.loadPaths merge error', e); }
      try { window.dispatchEvent && window.dispatchEvent(new CustomEvent('dc:lang:loaded', { detail: { lang: getLang() } })); } catch (e) {}
      return Promise.resolve();
    };
  })(Admin.i18n);

  // Apply translations (data-i18n)
  (function () {
    function lookup(path) {
      try {
        if (!path || !window.ADMIN_UI) return null;
        var parts = String(path).split('.');
        var cur = window.ADMIN_UI.strings || {};
        for (var i = 0; i < parts.length; i++) {
          if (!cur || typeof cur !== 'object' || !(parts[i] in cur)) { cur = undefined; break; }
          cur = cur[parts[i]];
        }
        if (cur !== undefined) return (typeof cur === 'string' || typeof cur === 'number') ? cur : null;
        // fallback to top-level ADMIN_UI
        cur = window.ADMIN_UI;
        for (i = 0; i < parts.length; i++) {
          if (!cur || typeof cur !== 'object' || !(parts[i] in cur)) { cur = undefined; break; }
          cur = cur[parts[i]];
        }
        if (cur !== undefined) return (typeof cur === 'string' || typeof cur === 'number') ? cur : null;
      } catch (e) { Admin.warn('lookup error', e); }
      return null;
    }

    function applyTranslations(root) {
      root = root || document;
      try {
        Array.prototype.slice.call(root.querySelectorAll('[data-i18n]')).forEach(function (node) {
          var key = node.getAttribute('data-i18n');
          if (!key) return;
          var val = lookup(key);
          if (val !== null && val !== undefined) {
            if (node.getAttribute && node.getAttribute('data-i18n-html') === '1') node.innerHTML = val;
            else node.textContent = val;
          }
        });

        Array.prototype.slice.call(root.querySelectorAll('[data-i18n-placeholder]')).forEach(function (node) {
          var key = node.getAttribute('data-i18n-placeholder');
          if (!key) return;
          var val = lookup(key);
          if (val !== null && val !== undefined) node.setAttribute('placeholder', val);
        });

        Array.prototype.slice.call(root.querySelectorAll('[data-i18n-value]')).forEach(function (node) {
          var key = node.getAttribute('data-i18n-value');
          if (!key) return;
          var val = lookup(key);
          if (val !== null && val !== undefined) node.value = val;
        });
      } catch (e) { Admin.warn('applyTranslations error', e); }
    }

    window._admin = window._admin || {};
    window._admin.applyTranslations = applyTranslations;
    window._admin.resolveKey = function (k) { return (typeof k === 'string') ? (function(){ try { return lookup(k); } catch(e){return null;} })() : null; };
  })();

  // -----------------------
  // fetch/form helpers
  // -----------------------
  Admin.fetchJson = function (url, options) {
    options = options || {};
    if (!options.credentials) options.credentials = 'same-origin';
    options.headers = options.headers || {};
    return fetch(url, options).then(function (res) {
      return res.text().then(function (txt) {
        var parsed = null;
        try { parsed = txt ? JSON.parse(txt) : null; } catch (e) {}
        return { ok: res.ok, status: res.status, data: parsed, raw: txt };
      });
    });
  };

  Admin.fetchAndInsert = function (url, targetSelector) {
    var target = document.querySelector(targetSelector);
    if (!target) return Promise.reject(new Error('Target not found: ' + targetSelector));
    var loader = document.createElement('div'); loader.className = 'inline-loader'; loader.textContent = (window.ADMIN_UI && ADMIN_UI.strings && ADMIN_UI.strings.loading) ? ADMIN_UI.strings.loading : 'Loading...';
    target.innerHTML = ''; target.appendChild(loader);
    return fetch(url, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then(function (res) { if (!res.ok) throw new Error('HTTP ' + res.status); return res.text(); })
      .then(function (html) {
        target.innerHTML = html;
        Admin.runScripts && Admin.runScripts(target);
        try { if (window._admin && typeof window._admin.applyTranslations === 'function') window._admin.applyTranslations(target); } catch (e) { Admin.warn(e); }
        Admin.applyPermsToContainer(target);
        Admin.initPageFromFragment && Admin.initPageFromFragment(target);
        return target;
      }).catch(function (err) {
        Admin.error('fetchAndInsert error', err);
        target.innerHTML = '<div style="padding:20px;color:#c0392b;text-align:center;">Error loading content</div>';
        throw err;
      });
  };

  Admin.formAjax = function (form, options) {
    if (!form) throw new Error('form element required');
    options = options || {};
    if (form._adminFormAjaxBound) return;
    form._adminFormAjaxBound = true;

    form.addEventListener('submit', function (ev) {
      ev.preventDefault();
      var submits = Array.prototype.slice.call(form.querySelectorAll('[type="submit"], button[data-submit]'));
      submits.forEach(function (b) { b.disabled = true; });

      var fd = new FormData(form);
      if (!fd.get('csrf_token')) {
        var cs = Admin.getCsrf && Admin.getCsrf();
        if (cs) fd.set('csrf_token', cs);
      }

      Admin.fetchJson(form.action || window.location.href, { method: 'POST', body: fd })
        .then(function (res) {
          if (res && res.data && (res.data.success || res.data.ok)) {
            if (typeof options.onSuccess === 'function') options.onSuccess(res.data);
            Admin.log('formAjax success', res.data);
          } else {
            if (typeof options.onError === 'function') options.onError(res);
            Admin.warn('formAjax failed', res);
          }
        })
        .catch(function (err) {
          Admin.error('formAjax request error', err);
          if (typeof options.onError === 'function') options.onError({ error: err });
        })
        .finally(function () { submits.forEach(function (b) { b.disabled = false; }); });
    });
  };

  // helper to run inline scripts that were inserted
  function runScripts(container) {
    var scripts = Array.prototype.slice.call(container.querySelectorAll('script'));
    scripts.forEach(function (old) {
      try {
        if (old.getAttribute('data-no-run') === '1') return;
        var type = (old.type || 'text/javascript').toLowerCase();
        if (type !== 'text/javascript' && type !== 'application/javascript') return;
        if (old.src) {
          if (document.querySelector('script[src="' + old.src + '"]')) return;
          var s = document.createElement('script'); s.src = old.src; s.async = false;
          document.body.appendChild(s);
        } else {
          var inline = document.createElement('script');
          inline.textContent = old.textContent;
          document.body.appendChild(inline);
          document.body.removeChild(inline);
        }
      } catch (e) { Admin.warn('inline script eval error', e); }
    });
  }
  Admin.runScripts = runScripts;

  // -----------------------
  // UI: sidebar, notifications, search
  // -----------------------
  function initSidebar() {
    try {
      // skip if an external implementation registered itself
      if (window.SidebarToggle && window.SidebarToggle.__installed) {
        Admin.log('External SidebarToggle present; skipping built-in initSidebar');
        return;
      }
      var toggle = document.getElementById('sidebarToggle');
      var sidebar = document.getElementById('adminSidebar') || document.querySelector('.admin-sidebar');
      var backdrop = document.querySelector('.sidebar-backdrop');
      if (!toggle || !sidebar) { Admin.warn('Sidebar elements missing'); return; }

      var bound = toggle._adminSidebarBound;
      if (!bound) {
        toggle.addEventListener('click', function (e) {
          e.preventDefault();
          e.stopPropagation();
          document.body.classList.toggle('sidebar-open');
          toggle.setAttribute('aria-expanded', document.body.classList.contains('sidebar-open') ? 'true' : 'false');
        }, { passive: false });
        toggle._adminSidebarBound = true;
      }

      if (backdrop && !backdrop._adminBound) {
        backdrop.addEventListener('click', function (e) { e.preventDefault(); document.body.classList.remove('sidebar-open'); toggle.setAttribute('aria-expanded', 'false'); }, { passive: true });
        backdrop._adminBound = true;
      }

      // close on Escape once
      if (!document._adminSidebarKeyBound) {
        document.addEventListener('keydown', function (e) {
          if (e.key === 'Escape' || e.keyCode === 27) {
            if (document.body.classList.contains('sidebar-open')) {
              document.body.classList.remove('sidebar-open');
              toggle.setAttribute('aria-expanded', 'false');
            }
          }
        });
        document._adminSidebarKeyBound = true;
      }
    } catch (e) { Admin.warn('initSidebar error', e); }
  }

  function initNotifications() {
    try {
      var btn = document.getElementById('notifBtn');
      var cnt = document.getElementById('notifCount');
      if (!btn || !cnt) return;
      btn.addEventListener('click', function (e) {
        e.preventDefault();
        var count = parseInt(cnt.textContent || '0', 10) || 0;
        alert((window.ADMIN_UI && window.ADMIN_UI.strings && window.ADMIN_UI.strings.notifications_popup) ? window.ADMIN_UI.strings.notifications_popup.replace('{count}', count) : 'You have ' + count + ' notifications.');
      });
    } catch (e) { Admin.warn('initNotifications error', e); }
  }

  function initSearch() {
    try {
      var input = document.getElementById('adminSearch');
      var btn = document.getElementById('searchBtn');
      function run() { if (!input) return; var q = input.value.trim(); if (!q) return; window.location.href = '/admin/search.php?q=' + encodeURIComponent(q); }
      if (input) input.addEventListener('keydown', function (e) { if (e.key === 'Enter') run(); });
      if (btn) btn.addEventListener('click', function (e) { e.preventDefault(); run(); });
    } catch (e) { Admin.warn('initSearch error', e); }
  }

  // -----------------------
  // Page registry + fragment init
  // -----------------------
  Admin.page = (function () {
    var modules = {};
    return {
      register: function (name, fn) { modules[name] = fn; },
      run: function (name, ctx) {
        var fn = modules[name];
        if (typeof fn === 'function') {
          try { fn(ctx || {}); Admin.log('Admin.page.run', name); }
          catch (e) { Admin.error('Admin.page.run error ' + name, e); }
        } else Admin.log('Admin.page: no module for', name);
      },
      _modules: modules
    };
  })();

  function readMetaFrom(root) {
    root = root || document;
    var meta = root.querySelector && root.querySelector('meta[data-page], meta[data-assets-js], meta[data-assets-css]');
    if (!meta) return null;
    return {
      page: meta.getAttribute('data-page') || meta.getAttribute('data-init') || meta.dataset.page,
      css: meta.getAttribute('data-assets-css') || meta.dataset.assetsCss,
      js: meta.getAttribute('data-assets-js') || meta.dataset.assetsJs
    };
  }

  function initPageFromFragment(root) {
    var info = readMetaFrom(root);
    if (!info) {
      try { if (window._admin && typeof window._admin.applyTranslations === 'function') window._admin.applyTranslations(root); } catch (e) { Admin.warn(e); }
      Admin.applyPermsToContainer(root);
      return Promise.resolve();
    }

    Admin.i18n.loadPaths([], root, info.page);

    var cssList = info.css ? info.css.split(',').map(function (s) { return s.trim(); }).filter(Boolean) : [];
    var jsList = info.js ? info.js.split(',').map(function (s) { return s.trim(); }).filter(Boolean) : [];

    return Promise.all(cssList.map(Admin.asset.loadCss)).then(function () {
      return Promise.all(jsList.map(Admin.asset.loadJs));
    }).then(function () {
      try { if (window._admin && typeof window._admin.applyTranslations === 'function') window._admin.applyTranslations(root); } catch (e) { Admin.warn(e); }
      Admin.applyPermsToContainer(root);

      if (info.page) {
        if (Admin.page._modules[info.page]) {
          try { Admin.page.run(info.page, { meta: root.querySelector('meta[data-page]') }); return; } catch (e) { Admin.error(e); }
        }
      }
    }).catch(function (err) {
      Admin.warn('initPageFromFragment asset load error', err);
    });
  }
  Admin.initPageFromFragment = initPageFromFragment;

  // -----------------------
  // Misc helpers
  // -----------------------
  Admin.getCsrf = function () {
    var el = document.querySelector('input[name="csrf_token"]');
    if (el) return el.value;
    var meta = document.querySelector('meta[name="csrf-token"]');
    if (meta) return meta.getAttribute('content');
    return (window.ADMIN_UI && window.ADMIN_UI.csrf_token) ? window.ADMIN_UI.csrf_token : '';
  };

  Admin.openModal = function (urlOrHtml, options) {
    options = options || {};
    return new Promise(function (resolve, reject) {
      try {
        var backdrop = document.querySelector('.admin-modal-backdrop');
        if (!backdrop) {
          backdrop = document.createElement('div');
          backdrop.className = 'admin-modal-backdrop';
          backdrop.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,0.35);display:flex;align-items:center;justify-content:center;z-index:14000;padding:16px;overflow:auto;';
          document.body.appendChild(backdrop);
        }
        var panel = document.createElement('div');
        panel.className = 'admin-modal-panel';
        panel.style.cssText = 'width:920px;max-width:100%;max-height:90vh;overflow:auto;background:#fff;border-radius:8px;box-shadow:0 8px 30px rgba(0,0,0,0.2);position:relative;';
        backdrop.innerHTML = '';
        backdrop.appendChild(panel);

        function close() { backdrop.remove(); resolve(null); }

        if (typeof urlOrHtml === 'string' && (urlOrHtml.indexOf('<') === -1 && (urlOrHtml.indexOf('/') === 0 || urlOrHtml.match(/^https?:\/\//)))) {
          fetch(urlOrHtml, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (res) { if (!res.ok) throw new Error('HTTP ' + res.status); return res.text(); })
            .then(function (html) { panel.innerHTML = html; runScripts(panel); if (options.onOpen) try { options.onOpen(panel); } catch (e) { Admin.error(e); } })
            .catch(function (err) { Admin.error('openModal fetch failed', err); panel.innerHTML = '<div style="padding:20px;color:#c0392b">Failed to load</div>'; });
        } else {
          panel.innerHTML = urlOrHtml || '';
          runScripts(panel);
          if (options.onOpen) try { options.onOpen(panel); } catch (e) { Admin.error(e); }
        }

        backdrop.addEventListener('click', function (ev) { if (ev.target === backdrop) close(); });
        document.addEventListener('keydown', function onKey(e) { if (e.key === 'Escape') { document.removeEventListener('keydown', onKey); close(); } });
      } catch (err) { reject(err); }
    });
  };

  // -----------------------
  // Init
  // -----------------------
  function init() {
    Admin.log('Admin core init');

    // ensure ADMIN_USER synced with injected payload
    try {
      if (window.ADMIN_UI && window.ADMIN_UI.user) {
        Admin.ADMIN_USER = window.ADMIN_UI.user;
        if (!Array.isArray(Admin.ADMIN_USER.permissions)) {
          if (Admin.ADMIN_USER.permissions && typeof Admin.ADMIN_USER.permissions === 'string') Admin.ADMIN_USER.permissions = [Admin.ADMIN_USER.permissions];
          else Admin.ADMIN_USER.permissions = [];
        }
      }
    } catch (e) { Admin.warn('sync user failed', e); }

    // theme vars + component styles
    try { syncThemeVarsFromAdminUI(); } catch (e) { Admin.warn('theme sync failed', e); }

    // translations
    try {
      var meta = document.querySelector('meta[data-page]');
      var pageName = meta ? (meta.getAttribute('data-page') || meta.dataset.page) : null;
      Admin.i18n.loadPaths([], document, pageName);
      if (window._admin && typeof window._admin.applyTranslations === 'function') window._admin.applyTranslations(document);
      Admin.log('i18n applied at init');
    } catch (e) { Admin.warn('initial i18n merge failed', e); }

    // UI wiring
    try { initSidebar(); } catch (e) { Admin.warn('initSidebar failed', e); }
    try { initNotifications(); } catch (e) { Admin.warn('initNotifications failed', e); }
    try { initSearch(); } catch (e) { Admin.warn('initSearch failed', e); }

    // permissions
    try { Admin.applyPermsToContainer(document); } catch (e) { Admin.warn(e); }

    // auto init page meta if present (assets and page module)
    try {
      var meta2 = document.querySelector('meta[data-page]');
      if (meta2) {
        var pageName2 = meta2.getAttribute('data-page') || meta2.dataset.page;
        if (pageName2) {
          Admin.log('Auto init page:', pageName2);
          var css = meta2.getAttribute('data-assets-css') || meta2.dataset.assetsCss || '';
          var js = meta2.getAttribute('data-assets-js') || meta2.dataset.assetsJs || '';
          var cssList = css ? css.split(',').map(function(s){ return s.trim(); }).filter(Boolean) : [];
          var jsList = js ? js.split(',').map(function(s){ return s.trim(); }).filter(Boolean) : [];
          Admin.i18n.loadPaths([], document, pageName2);
          Promise.all(cssList.map(Admin.asset.loadCss)).then(function () {
            return Promise.all(jsList.map(Admin.asset.loadJs));
          }).then(function () {
            if (Admin.page._modules[pageName2]) Admin.page.run(pageName2, { meta: meta2 });
            else Admin.log('No page module registered for', pageName2);
          }).catch(function (err) { Admin.warn('Auto page init asset load failed', err); if (Admin.page._modules[pageName2]) Admin.page.run(pageName2, { meta: meta2 }); });
        }
      }
    } catch (e) { Admin.warn('auto init page failed', e); }
  }

  domReady(init);

  // public API
  Admin.syncThemeVarsFromAdminUI = syncThemeVarsFromAdminUI;
  Admin.generateComponentStyles = generateComponentStyles;

  Admin.log('admin_core ready');

})();