/**
 * admin/assets/js/admin_core.js
 *
 * Admin client core (reworked): strict server-driven i18n.
 * - Relies ONLY on htdocs/api/bootstrap.php (window.ADMIN_UI) and
 *   server-injected page translations (e.g. window.__DeliveryCompanyTranslations).
 * - Does NOT fetch any language files client-side (no admin-i18n.js, no i18n_loader.js).
 * - Deep-merges injected translations into window.ADMIN_UI.strings (non-destructive).
 * - Keeps RBAC helpers, asset loader, modal, fetch/insert, page init, etc.
 *
 * Save as UTF-8 without BOM.
 */
(function () {
  'use strict';

  if (window.Admin && window.Admin.__installed) return;
  window.Admin = window.Admin || {};
  Admin.__installed = true;

  // Simple logger (toggleable)
  Admin.debug = true;
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

  function getNested(obj, path) {
    if (!obj || !path) return undefined;
    var parts = String(path).split('.');
    var cur = obj;
    for (var i = 0; i < parts.length; i++) {
      if (!cur || typeof cur !== 'object' || !(parts[i] in cur)) return undefined;
      cur = cur[parts[i]];
    }
    return cur;
  }

  // RBAC / Permission helpers
  Admin.ADMIN_USER = window.ADMIN_USER || (window.ADMIN_UI && window.ADMIN_UI.user) || {};
  (function normalizeAdminUser() {
    var u = Admin.ADMIN_USER;
    if (!u) { Admin.ADMIN_USER = {}; return; }
    if (!Array.isArray(u.permissions)) {
      if (u.permissions && typeof u.permissions === 'string') u.permissions = [u.permissions];
      else u.permissions = [];
    }
    if (typeof u.role === 'string' && /^\d+$/.test(u.role)) u.role = parseInt(u.role, 10);
    // also support role_id naming
    if (!u.role && u.role_id) u.role = u.role_id;
  })();

  Admin.isSuper = function () {
    var r = Admin.ADMIN_USER && (Admin.ADMIN_USER.role || Admin.ADMIN_USER.role_id);
    if (!r) return false;
    return (r === 1 || r === '1' || String(r).toLowerCase() === 'super_admin' || String(r).toLowerCase() === 'admin');
  };

  Admin.can = function (perm) {
    try {
      if (!perm) return true;
      if (Admin.isSuper()) return true;
      var perms = Admin.ADMIN_USER && Array.isArray(Admin.ADMIN_USER.permissions) ? Admin.ADMIN_USER.permissions : [];
      if (Array.isArray(perm)) {
        return perm.some(function (p) { return perms.indexOf(p) !== -1; });
      }
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
        } else {
          el.style.display = '';
        }
      });
      Array.prototype.slice.call(container.querySelectorAll('[data-require-all]')).forEach(function (el) {
        var spec = (el.getAttribute('data-require-all') || '').trim();
        if (!spec) return;
        if (!Admin.canAll(spec)) {
          if (el.getAttribute('data-remove-without-perm') === '1') el.remove(); else el.style.display = 'none';
        } else {
          el.style.display = '';
        }
      });
      Array.prototype.slice.call(container.querySelectorAll('[data-hide-without-perm]')).forEach(function (el) {
        var spec = (el.getAttribute('data-hide-without-perm') || '').trim();
        if (!spec) return;
        if (!Admin.can(spec)) el.remove();
      });
    } catch (e) { Admin.warn('applyPermsToContainer error', e); }
  };

  // Asset loader
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

  // i18n: server-only approach (no network fetch)
  Admin.i18n = Admin.i18n || {};
  (function (I18n) {
    function getLang() {
      return window.ADMIN_LANG || (document.documentElement && document.documentElement.lang) || 'en';
    }

    // Build candidate names (kept for compatibility but not used to fetch)
    I18n.buildCandidates = function (pageName) {
      var lang = getLang();
      var candidates = [];
      candidates.push('/languages/admin/' + encodeURIComponent(lang) + '.json');
      if (pageName) {
        candidates.push('/languages/admin/' + encodeURIComponent(pageName) + '/' + encodeURIComponent(lang) + '_' + encodeURIComponent(pageName) + '.json');
      }
      return candidates;
    };

    // Helper: try to find injected page translations variables in window
    function findInjectedPageTranslations(pageName) {
      if (!pageName) return null;
      var n = String(pageName || '');
      var variants = [];

      // original
      variants.push(n);
      // remove non-alnum
      variants.push(n.replace(/[^A-Za-z0-9]/g, ''));
      // camelCase / PascalCase
      var parts = n.split(/[_\-\s]+/).filter(Boolean);
      if (parts.length) {
        var camel = parts.map(function (p, i) { return i === 0 ? p.charAt(0).toUpperCase() + p.slice(1) : p.charAt(0).toUpperCase() + p.slice(1); }).join(''); // Pascal
        variants.push(camel);
        var lowerCamel = parts.map(function (p, i) { return i === 0 ? p.toLowerCase() : p.charAt(0).toUpperCase() + p.slice(1); }).join('');
        variants.push(lowerCamel);
      }

      // kebab/underscore normalized
      variants = variants.concat([n.toLowerCase(), n.toUpperCase()]);

      for (var i = 0; i < variants.length; i++) {
        var key = '__' + variants[i] + 'Translations';
        if (window[key] && typeof window[key] === 'object') return window[key];
      }
      // also support generic __PageTranslations (fallback)
      if (window.__PageTranslations && typeof window.__PageTranslations === 'object') return window.__PageTranslations;
      return null;
    }

    // Merge server-injected translations into ADMIN_UI.strings
    I18n.mergeInjected = function (pageName) {
      try {
        window.ADMIN_UI = window.ADMIN_UI || {};
        window.ADMIN_UI.strings = window.ADMIN_UI.strings || {};

        // 1) page-specific injected object (fragment)
        var pageTr = findInjectedPageTranslations(pageName);
        if (pageTr && typeof pageTr === 'object') {
          var src = pageTr.strings && typeof pageTr.strings === 'object' ? pageTr.strings : pageTr;
          deepMerge(window.ADMIN_UI.strings, src || {});
          if (pageTr.direction) window.ADMIN_UI.direction = pageTr.direction;
          Admin.log('i18n: merged page-injected translations for', pageName);
        }

        // 2) bootstrap-provided strings (do not overwrite existing keys)
        if (window.ADMIN_UI && window.ADMIN_UI.__bootstrap_strings && typeof window.ADMIN_UI.__bootstrap_strings === 'object') {
          // Some bootstrap implementations may place raw strings under ADMIN_UI.__bootstrap_strings to avoid clobbering.
          deepMerge(window.ADMIN_UI.strings, window.ADMIN_UI.__bootstrap_strings);
        } else if (window.ADMIN_UI && window.ADMIN_UI.strings && typeof window.ADMIN_UI.strings === 'object') {
          // already present — merge again (safe)
          deepMerge(window.ADMIN_UI.strings, window.ADMIN_UI.strings);
        }

        // ensure direction
        if (window.ADMIN_UI && window.ADMIN_UI.direction) {
          try { document.documentElement.dir = window.ADMIN_UI.direction; } catch (e) {}
        }
      } catch (e) { Admin.warn('i18n.mergeInjected error', e); }
    };

    // No-op loader that only relies on injected translations.
    I18n.loadPaths = function (paths, root, pageName) {
      // Merge injected translations synchronously, then resolve.
      try {
        I18n.mergeInjected(pageName || (root && root.querySelector && (root.querySelector('meta[data-page]') || {}).getAttribute && (root.querySelector('meta[data-page]') || {}).getAttribute('data-page')));
      } catch (e) { Admin.warn('i18n.loadPaths merge error', e); }
      try { window.dispatchEvent && window.dispatchEvent(new CustomEvent('dc:lang:loaded', { detail: { lang: getLang() } })); } catch (e) {}
      return Promise.resolve();
    };

    I18n.getLang = getLang;
  })(Admin.i18n);

  // Page registry & auto-init from <meta data-page>
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
      // still apply translations and permissions
      try { if (window._admin && typeof window._admin.applyTranslations === 'function') window._admin.applyTranslations(root); } catch (e) { Admin.warn(e); }
      Admin.applyPermsToContainer(root);
      return Promise.resolve();
    }

    // Merge injected translations (no network)
    Admin.i18n.loadPaths([], root, info.page);

    var cssList = info.css ? info.css.split(',').map(function (s) { return s.trim(); }).filter(Boolean) : [];
    var jsList = info.js ? info.js.split(',').map(function (s) { return s.trim(); }).filter(Boolean) : [];

    return Promise.all(cssList.map(Admin.asset.loadCss)).then(function () {
      return Promise.all(jsList.map(Admin.asset.loadJs));
    }).then(function () {
      // apply translations & perms
      try { if (window._admin && typeof window._admin.applyTranslations === 'function') window._admin.applyTranslations(root); } catch (e) { Admin.warn(e); }
      Admin.applyPermsToContainer(root);

      if (info.page) {
        // try registered module first
        if (Admin.page._modules[info.page]) {
          try { Admin.page.run(info.page, { meta: root.querySelector('meta[data-page]') }); return; } catch (e) { Admin.error(e); }
        }
        // try global initializers
        var parts = (info.page || '').split(/[_\-]/);
        var pascal = parts.map(function (p) { return p.charAt(0).toUpperCase() + p.slice(1); }).join('');
        var camel = parts.map(function (p, i) { return i === 0 ? p : p.charAt(0).toUpperCase() + p.slice(1); }).join('');
        if (window[pascal] && typeof window[pascal].init === 'function') { try { window[pascal].init(root); return; } catch (e) { Admin.error(e); } }
        if (window[camel] && typeof window[camel].init === 'function') { try { window[camel].init(root); return; } catch (e) { Admin.error(e); } }
      }
      // fallback initializer
      if (window.PageInitializers && window.PageInitializers['default'] && typeof window.PageInitializers['default'].init === 'function') {
        try { window.PageInitializers['default'].init(root); } catch (e) { Admin.error(e); }
      }
    }).catch(function (err) {
      Admin.warn('initPageFromFragment asset load error', err);
    });
  }
  Admin.initPageFromFragment = initPageFromFragment;

  // CSRF helper
  Admin.getCsrf = function () {
    var el = document.querySelector('input[name="csrf_token"]');
    if (el) return el.value;
    var meta = document.querySelector('meta[name="csrf-token"]');
    if (meta) return meta.getAttribute('content');
    return (window.ADMIN_UI && window.ADMIN_UI.csrf_token) ? window.ADMIN_UI.csrf_token : '';
  };

  // fetchJson wrapper
  Admin.fetchJson = function (url, options) {
    options = options || {};
    if (!options.credentials) options.credentials = 'same-origin';
    options.headers = options.headers || {};
    return fetch(url, options).then(function (res) {
      return res.text().then(function (txt) {
        var parsed = null;
        try { parsed = txt ? JSON.parse(txt) : null; } catch (e) { /* not JSON */ }
        return { ok: res.ok, status: res.status, data: parsed, raw: txt };
      });
    });
  };

  // formAjax
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
        var cs = Admin.getCsrf();
        if (cs) fd.set('csrf_token', cs);
      }

      Admin.fetchJson(form.action || window.location.href, { method: 'POST', body: fd })
        .then(function (res) {
          if (res && res.data && res.data.success) {
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

  // hijackSubmit
  Admin.hijackSubmit = function (form, handler) {
    if (!form || typeof handler !== 'function') throw new Error('form and handler required');
    function onSubmitCapture(ev) {
      var target = ev.target;
      if (!target) return;
      if (target !== form && !form.contains(target)) return;
      ev.preventDefault();
      ev.stopImmediatePropagation && ev.stopImmediatePropagation();
      try { handler(ev); } catch (err) { Admin.error('hijackSubmit handler error', err); }
    }
    document.addEventListener('submit', onSubmitCapture, true);
    return function unbind() { try { document.removeEventListener('submit', onSubmitCapture, true); } catch (e) {} };
  };

  // runScripts (safe)
  function runScripts(container) {
    var scripts = Array.prototype.slice.call(container.querySelectorAll('script'));
    scripts.forEach(function (old) {
      if (old.getAttribute('data-no-run') === '1') return;
      var type = (old.type || 'text/javascript').toLowerCase();
      if (type !== 'text/javascript' && type !== 'application/javascript') return;
      if (old.src) {
        if (document.querySelector('script[src="' + old.src + '"]')) return;
        var s = document.createElement('script'); s.src = old.src; s.async = false;
        document.body.appendChild(s);
      } else {
        try {
          var inline = document.createElement('script');
          inline.textContent = old.textContent;
          document.body.appendChild(inline);
          document.body.removeChild(inline);
        } catch (e) { Admin.warn('inline script eval error', e); }
      }
    });
  }
  Admin.runScripts = runScripts;

  // openModal
  Admin.openModal = function (urlOrHtml, options) {
    options = options || {};
    if (typeof urlOrHtml === 'string' && window.AdminModal && typeof window.AdminModal.openModalByUrl === 'function') {
      return window.AdminModal.openModalByUrl(urlOrHtml, options);
    }
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
            .then(function (html) {
              panel.innerHTML = html;
              runScripts(panel);
              if (options.onOpen) try { options.onOpen(panel); } catch (e) { Admin.error(e); }
            }).catch(function (err) { Admin.error('openModal fetch failed', err); panel.innerHTML = '<div style="padding:20px;color:#c0392b">Failed to load</div>'; });
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

  // openImageStudio
  Admin.openImageStudio = function (opts) {
    opts = opts || {};
    if (window.ImageStudio && typeof window.ImageStudio.open === 'function') return window.ImageStudio.open(opts);
    var url = '/admin/fragments/images.php?owner_type=' + encodeURIComponent(opts.ownerType || '') + '&owner_id=' + encodeURIComponent(opts.ownerId || 0) + '&_standalone=1';
    var popup = window.open(url, 'ImageStudio', 'width=1000,height=700,scrollbars=yes');
    return new Promise(function (resolve) {
      if (!popup) { alert('النافذة المنبثقة محجوزة — اسمح بالنوافذ المنبثقة لموقعك'); resolve(null); return; }
      function onMsg(e) {
        if (!e || !e.data) return;
        var d = e.data;
        if ((d.type === 'ImageStudio:selected' || d.type === 'image_selected') && d.url) { window.removeEventListener('message', onMsg); try { popup.close(); } catch (e) {} resolve(d.url); }
        if (d.type === 'ImageStudio:close') { window.removeEventListener('message', onMsg); resolve(null); }
      }
      window.addEventListener('message', onMsg);
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
        runScripts(target);
        try { if (window._admin && typeof window._admin.applyTranslations === 'function') window._admin.applyTranslations(target); } catch (e) { Admin.warn(e); }
        Admin.applyPermsToContainer(target);
        initPageFromFragment(target);
        return target;
      }).catch(function (err) {
        Admin.error('fetchAndInsert error', err);
        target.innerHTML = '<div style="padding:20px;color:#c0392b;text-align:center;">Error loading content</div>';
        throw err;
      });
  };

  window.AdminUI = window.AdminUI || {};
  window.AdminUI.fetchAndInsert = Admin.fetchAndInsert;

  // Sidebar init
  function initSidebar() {
    var toggle = document.getElementById('sidebarToggle');
    var sidebar = document.getElementById('adminSidebar');
    var backdrop = document.querySelector('.sidebar-backdrop');
    if (!toggle || !sidebar) { Admin.warn('Sidebar elements missing'); return; }
    toggle.addEventListener('click', function (e) {
      e.preventDefault();
      document.body.classList.toggle('sidebar-open');
      toggle.setAttribute('aria-expanded', document.body.classList.contains('sidebar-open') ? 'true' : 'false');
    });
    if (backdrop) backdrop.addEventListener('click', function (e) { e.preventDefault(); document.body.classList.remove('sidebar-open'); toggle.setAttribute('aria-expanded', 'false'); });
    sidebar.addEventListener('click', function (e) {
      var a = e.target.closest && e.target.closest('a');
      if (a && window.innerWidth < 700) setTimeout(function () { document.body.classList.remove('sidebar-open'); toggle.setAttribute('aria-expanded', 'false'); }, 200);
    });
  }

  // i18n apply translations helper (idempotent)
  (function () {
    function resolveKey(path) {
      if (!path || !window.ADMIN_UI) return null;
      var parts = path.split('.');
      var cur = window.ADMIN_UI;
      for (var i = 0; i < parts.length; i++) {
        if (cur && Object.prototype.hasOwnProperty.call(cur, parts[i])) cur = cur[parts[i]];
        else return null;
      }
      return (typeof cur === 'string' || typeof cur === 'number') ? cur : null;
    }

    function applyTranslations(root) {
      root = root || document;
      try {
        root.querySelectorAll('[data-i18n]').forEach(function (node) {
          var key = node.getAttribute('data-i18n');
          if (!key) return;
          var val = resolveKey(key);
          if (val !== null) node.textContent = val;
        });
        // placeholders
        root.querySelectorAll('[data-i18n-placeholder]').forEach(function (node) {
          var key = node.getAttribute('data-i18n-placeholder');
          if (!key) return;
          var val = resolveKey(key);
          if (val !== null) node.placeholder = val;
        });
      } catch (e) { Admin.warn('applyTranslations error', e); }
    }

    window._admin = window._admin || {};
    window._admin.applyTranslations = applyTranslations;
  })();

  // Global click handler for data attributes
  document.addEventListener('click', function (e) {
    if (e.defaultPrevented) return;
    var el = e.target.closest && e.target.closest('[data-modal-url], [data-load-url]');
    if (!el) return;
    var modalUrl = el.getAttribute('data-modal-url');
    var loadUrl = el.getAttribute('data-load-url');
    if (modalUrl) {
      e.preventDefault();
      Admin.openModal(modalUrl);
      return;
    }
    if (loadUrl) {
      e.preventDefault();
      var sidebarParent = el.closest && el.closest('#adminSidebar');
      if (sidebarParent) sidebarParent.querySelectorAll('a').forEach(function (a) { a.classList.remove('active'); });
      el.classList && el.classList.add('active');
      Admin.fetchAndInsert(loadUrl, '#adminMainContent').catch(function () { window.location.href = loadUrl; });
      return;
    }
  }, false);

  // Notifications & Search
  function initNotifications() {
    var btn = document.getElementById('notifBtn');
    var cnt = document.getElementById('notifCount');
    if (!btn || !cnt) return;
    btn.addEventListener('click', function (e) { e.preventDefault(); var count = parseInt(cnt.textContent||'0',10); alert((ADMIN_UI && ADMIN_UI.strings && ADMIN_UI.strings.notifications_popup) ? ADMIN_UI.strings.notifications_popup.replace('{count}', count) : 'You have ' + count + ' notifications.'); });
  }
  function initSearch() {
    var input = document.getElementById('adminSearch');
    var btn = document.getElementById('searchBtn');
    function run() { if (!input) return; var q = input.value.trim(); if (!q) return; window.location.href = '/admin/search.php?q=' + encodeURIComponent(q); }
    if (input) input.addEventListener('keydown', function (e) { if (e.key === 'Enter') run(); });
    if (btn) btn.addEventListener('click', function (e) { e.preventDefault(); run(); });
  }

  // Initialization on DOM ready
  function init() {
    Admin.log('Admin core init');

    // Merge server-injected translations immediately (no network)
    try {
      // Merge page-specific injection if any (meta data-page may be present)
      var meta = document.querySelector('meta[data-page]');
      var pageName = meta ? (meta.getAttribute('data-page') || meta.dataset.page) : null;
      Admin.i18n.loadPaths([], document, pageName);
      if (window._admin && typeof window._admin.applyTranslations === 'function') window._admin.applyTranslations(document);
    } catch (e) { Admin.warn('initial i18n merge failed', e); }

    initSidebar();
    initNotifications();
    initSearch();

    // Apply permissions to the initial document
    try { Admin.applyPermsToContainer(document); } catch (e) { Admin.warn(e); }

    // Auto init page meta if present (load assets, run page module)
    var meta = document.querySelector('meta[data-page]');
    if (meta) {
      var pageName = meta.getAttribute('data-page') || meta.dataset.page;
      if (pageName) {
        Admin.log('Auto init page:', pageName);
        var css = meta.getAttribute('data-assets-css') || meta.dataset.assetsCss || '';
        var js = meta.getAttribute('data-assets-js') || meta.dataset.assetsJs || '';
        var cssList = css ? css.split(',').map(function(s){ return s.trim(); }).filter(Boolean) : [];
        var jsList = js ? js.split(',').map(function(s){ return s.trim(); }).filter(Boolean) : [];

        // Merge injected translations (again, defensive)
        Admin.i18n.loadPaths([], document, pageName);

        Promise.all(cssList.map(Admin.asset.loadCss)).then(function () {
          return Promise.all(jsList.map(Admin.asset.loadJs));
        }).then(function () {
          if (Admin.page._modules[pageName]) Admin.page.run(pageName, { meta: meta });
          else Admin.log('No page module registered for', pageName);
        }).catch(function (err) { Admin.warn('Auto page init asset load failed', err); if (Admin.page._modules[pageName]) Admin.page.run(pageName, { meta: meta }); });
      }
    }
  }
  domReady(init);

  // Expose helpers for pages
  Admin.initPageFromFragment = initPageFromFragment;
  Admin.hijackSubmit = Admin.hijackSubmit;
  Admin.formAjax = Admin.formAjax;
  Admin.fetchJson = Admin.fetchJson;
  Admin.openImageStudio = Admin.openImageStudio;

  Admin.log('admin_core ready (server-driven i18n only)');
})();