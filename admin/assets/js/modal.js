/*!
 * admin/assets/js/modal.js
 *
 * Modal loader + i18n-aware button/text translator for the admin UI.
 * Modified to rely ONLY on server-provided data (window.ADMIN_UI) and
 * on any page-specific translation objects injected into the page (e.g. window.__PageTranslations).
 *
 * - Does NOT fetch JSON files from /languages by default (but can fetch page translations from server if configured)
 * - Merges injected translations (Admin.i18n.mergeInjected if available) or from several known global names
 * - Loads/executes scripts inside fetched fragments (skips data-no-run)
 * - Detects meta[data-page] or data-page attributes to tell Admin.i18n which page to merge.
 * - Applies translations using AdminI18n / window._admin / window.ADMIN_UI (fallback).
 * - Safe button/text translation that preserves icon-only buttons (sets aria-label instead).
 *
 * Save as UTF-8 without BOM.
 */
(function () {
  'use strict';

  if (window.AdminModal) return; // already initialized

  /* ---------------------------
     Utility / DOM helpers
     --------------------------- */

  function ensureContainer() {
    var backdrop = document.getElementById('adminModalBackdrop');
    if (!backdrop) {
      backdrop = document.createElement('div');
      backdrop.id = 'adminModalBackdrop';
      backdrop.className = 'admin-modal-backdrop';
      backdrop.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,0.35);display:none;align-items:center;justify-content:center;z-index:13000;padding:20px;overflow:auto;';
      document.body.appendChild(backdrop);
    }
    var panel = backdrop.querySelector('.admin-modal-panel');
    if (!panel) {
      panel = document.createElement('div');
      panel.className = 'admin-modal-panel';
      panel.style.cssText = 'width:920px;max-width:100%;max-height:90vh;overflow:auto;background:#fff;border-radius:8px;box-shadow:0 8px 30px rgba(0,0,0,0.2);';
      backdrop.appendChild(panel);
    }
    return { backdrop: backdrop, panel: panel };
  }

  function isElement(obj) { return obj && obj.nodeType === 1; }

  function safeReplaceContent(parent, oldChild, newChild) {
    try {
      if (oldChild && oldChild.parentNode === parent) {
        parent.replaceChild(newChild, oldChild);
      } else {
        while (parent.firstChild) parent.removeChild(parent.firstChild);
        parent.appendChild(newChild);
      }
    } catch (e) {
      while (parent.firstChild) parent.removeChild(parent.firstChild);
      parent.appendChild(newChild);
      console.warn('safeReplaceContent fallback used', e);
    }
  }

  /* ---------------------------
     Script runner for fragments
     --------------------------- */

  function runScripts(container) {
    var scripts = Array.prototype.slice.call(container.querySelectorAll('script'));
    var externals = scripts.filter(function (s) { return s.src && !s.hasAttribute('data-no-run'); });
    var inlines = scripts.filter(function (s) { return !s.src && !s.hasAttribute('data-no-run'); });

    externals.concat(inlines).forEach(function (s) { if (s.parentNode) s.parentNode.removeChild(s); });

    return externals.reduce(function (p, script) {
      return p.then(function () {
        return new Promise(function (resolve) {
          try {
            var tag = document.createElement('script');
            if (script.type) tag.type = script.type;
            tag.src = script.src;
            tag.async = false;
            Array.prototype.slice.call(script.attributes).forEach(function (attr) {
              try { tag.setAttribute(attr.name, attr.value); } catch (e) {}
            });
            tag.onload = function () { resolve(); };
            tag.onerror = function (ev) { console.error('Failed to load script', script.src, ev); resolve(); };
            document.head.appendChild(tag);
          } catch (err) {
            console.error('runScripts external error', err);
            resolve();
          }
        });
      });
    }, Promise.resolve()).then(function () {
      inlines.forEach(function (script) {
        try {
          var s = document.createElement('script');
          if (script.type) s.type = script.type;
          s.text = script.textContent;
          Array.prototype.slice.call(script.attributes).forEach(function (attr) {
            if (attr.name !== 'src') try { s.setAttribute(attr.name, attr.value); } catch (e) {}
          });
          container.appendChild(s);
          container.removeChild(s);
        } catch (e) {
          console.warn('Inline script execution failed', e);
        }
      });
      return;
    });
  }

  /* ---------------------------
     Lightweight deepMerge
     --------------------------- */

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

  /* ---------------------------
     i18n helpers (server-driven only, extended)
     --------------------------- */

  // Resolve translation key strictly from window.ADMIN_UI (no network unless configured)
  function tKey(key, fallback) {
    if (!key) return fallback || '';
    try {
      if (window.AdminI18n && typeof AdminI18n.tKey === 'function') return AdminI18n.tKey(key, fallback);
      if (window._admin && typeof window._admin.resolveKey === 'function') {
        var v = window._admin.resolveKey(key);
        if (v !== null) return v;
      }
      var ui = window.ADMIN_UI || {};
      // dot lookup in ADMIN_UI.strings first
      var parts = key.split('.');
      var cur = ui.strings || {};
      for (var i = 0; i < parts.length; i++) {
        if (!cur || typeof cur !== 'object' || !(parts[i] in cur)) { cur = undefined; break; }
        cur = cur[parts[i]];
      }
      if (typeof cur === 'string') return cur;
      // fallback to top-level ADMIN_UI
      cur = ui;
      for (i = 0; i < parts.length; i++) {
        if (!cur || typeof cur !== 'object' || !(parts[i] in cur)) { cur = undefined; break; }
        cur = cur[parts[i]];
      }
      if (typeof cur === 'string') return cur;
      // legacy fallbacks
      if (ui.strings && ui.strings[key]) return ui.strings[key];
      if (ui.buttons && ui.buttons[key]) return ui.buttons[key];
      if (ui.labels && ui.labels[key]) return ui.labels[key];
    } catch (e) {
      console.warn('tKey error', e);
    }
    try { window.I18N_MISSING_KEYS = window.I18N_MISSING_KEYS || {}; window.I18N_MISSING_KEYS[key] = (window.I18N_MISSING_KEYS[key] || 0) + 1; } catch (e) {}
    return fallback || key;
  }

  function getPageNameFromElement(el) {
    try {
      var metaPage = el.querySelector && el.querySelector('meta[data-page]');
      if (metaPage) return metaPage.getAttribute('data-page') || metaPage.dataset.page || null;
      if (el.getAttribute && el.getAttribute('data-page')) return el.getAttribute('data-page');
      return null;
    } catch (e) { return null; }
  }

  // Merge injected translations from many known globals and optionally fetch from server
  function mergeInjectedTranslations(pageName, options) {
    options = options || {};
    var fetchIfMissing = options.fetchIfMissing === true;
    try {
      // Prefer core merge if available
      if (window.Admin && window.Admin.i18n && typeof window.Admin.i18n.mergeInjected === 'function') {
        window.Admin.i18n.mergeInjected(pageName);
      }

      window.ADMIN_UI = window.ADMIN_UI || {};
      window.ADMIN_UI.strings = window.ADMIN_UI.strings || {};

      // Known global variable candidates (case variants)
      var candidates = [];
      if (pageName) {
        candidates.push('__' + pageName + 'Translations');
        candidates.push('__' + pageName.replace(/[^A-Za-z0-9]/g, '') + 'Translations');
        var parts = pageName.split(/[_\-\s]+/).filter(Boolean);
        if (parts.length) {
          var pascal = parts.map(function (p) { return p.charAt(0).toUpperCase() + p.slice(1); }).join('');
          var camel = parts.map(function (p, i) { return i === 0 ? p : p.charAt(0).toUpperCase() + p.slice(1); }).join('');
          candidates.push('__' + pascal + 'Translations', '__' + camel + 'Translations');
        }
      }
      // common fallbacks and variants
      candidates = candidates.concat(['__PageTranslations','__UsersTranslations','__usersTranslations','__UsersManagementTranslations','pageTranslations','page_translation','window.pageTranslations','window.i18n_users','i18n_users','window.i18n_users']);

      var found = false;
      candidates.forEach(function (k) {
        try {
          // allow both window['name'] and direct names if defined globally
          var obj = (typeof window[k] !== 'undefined') ? window[k] : (window.hasOwnProperty(k) ? window[k] : null);
          if (!obj && k.indexOf('window.') === 0) {
            var name = k.split('.').slice(1).join('.');
            obj = window[name];
          }
          if (obj && typeof obj === 'object') {
            found = true;
            var src = (obj.strings && typeof obj.strings === 'object') ? obj.strings : obj;
            deepMerge(window.ADMIN_UI.strings, src || {});
            if (obj.direction) window.ADMIN_UI.direction = obj.direction;
          }
        } catch (e) { /* ignore per-candidate errors */ }
      });

      // If not found and fetch is allowed, try to fetch translations from server endpoint.
      // Endpoint can be configured in window.ADMIN_UI.translations_endpoint (expects page param appended or ?page=)
      if (!found && fetchIfMissing && pageName) {
        // avoid duplicate fetches
        window._fetchedPageTranslations = window._fetchedPageTranslations || {};
        if (!window._fetchedPageTranslations[pageName]) {
          window._fetchedPageTranslations[pageName] = true;
          var ep = (window.ADMIN_UI && window.ADMIN_UI.translations_endpoint) || '/admin/api/translations.php';
          var sep = ep.indexOf('?') === -1 ? '?' : '&';
          var url = ep + sep + 'page=' + encodeURIComponent(pageName);
          try {
            fetch(url, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
              .then(function (res) { if (!res.ok) throw new Error('HTTP ' + res.status); return res.json(); })
              .then(function (json) {
                try {
                  var payload = json && (json.strings || json) ? (json.strings || json) : null;
                  if (payload && typeof payload === 'object') {
                    deepMerge(window.ADMIN_UI.strings, payload);
                    // apply theme sync if server included theme chunk (optional)
                    if (window.Admin && typeof window.Admin.syncThemeVarsFromAdminUI === 'function') {
                      try { window.Admin.syncThemeVarsFromAdminUI(); window.Admin.generateComponentStyles && window.Admin.generateComponentStyles(); } catch (e) {}
                    }
                    // fire an event so other modules can reapply translations
                    try { window.dispatchEvent && window.dispatchEvent(new CustomEvent('dc:lang:loaded', { detail: { lang: (window.ADMIN_UI.lang || document.documentElement.lang || 'en') } })); } catch (e) {}
                  }
                } catch (e) { console.warn('merge fetched translations error', e); }
              })
              .catch(function (err) { /* fetch errors are non-fatal */ console.warn('translations fetch failed', url, err); });
          } catch (e) { console.warn('translations fetch exception', e); }
        }
      }

      // After merging translations ensure Admin core (if present) applies direction & theme if needed
      try {
        if (window.Admin && window.Admin.i18n && typeof window.Admin.i18n.mergeInjected === 'function') {
          // already merged above
        }
        if (window.Admin && typeof window.Admin.syncThemeVarsFromAdminUI === 'function') {
          try { window.Admin.syncThemeVarsFromAdminUI(); window.Admin.generateComponentStyles && window.Admin.generateComponentStyles(); } catch (e) {}
        }
      } catch (e) { /* ignore */ }

    } catch (e) { console.warn('mergeInjectedTranslations failed', e); }
  }

  /* ---------------------------
     Button/text translation (safe)
     --------------------------- */

  function isIconOnly(el) {
    if (!el) return false;
    var txt = (el.textContent || '').trim();
    var hasIcon = !!(el.querySelector && (el.querySelector('svg, i, .icon') !== null));
    if (!txt) return hasIcon;
    if (/[A-Za-z0-9\u0600-\u06FF]/.test(txt)) return false;
    return txt.length <= 2 && hasIcon;
  }

  function setButtonTextPreserve(btn, text) {
    if (!btn) return;
    // remove visible text nodes but keep icons
    var nodes = Array.prototype.slice.call(btn.childNodes);
    nodes.forEach(function (n) {
      if (n.nodeType === Node.TEXT_NODE && n.textContent.trim()) n.parentNode.removeChild(n);
    });
    btn.appendChild(document.createTextNode(' ' + text));
  }

  var BTN_MAP = [
    { sel: '#btnAddNew', key: 'buttons.add_new' },
    { sel: '#btnSave, button[type="submit"].btn-save', key: 'buttons.save' },
    { sel: '#btnCancelForm, .btn-cancel', key: 'buttons.cancel' },
    { sel: '#btnChooseImage', key: 'labels.choose_image' },
    { sel: '#btnClearImage', key: 'labels.remove_image' },
    { sel: '.edit-btn', key: 'actions.edit' },
    { sel: '.delete-btn', key: 'actions.delete' },
    { sel: '#btnAddLang, .btn-add-lang', key: 'labels.translation_add' }
  ];

  function translateButtons(root) {
    root = root || document;
    try {
      BTN_MAP.forEach(function (m) {
        var els = Array.prototype.slice.call(root.querySelectorAll(m.sel));
        if (!els || !els.length) return;
        els.forEach(function (el) {
          try {
            var declared = el.getAttribute && el.getAttribute('data-i18n');
            var useKey = declared && declared.length ? declared : m.key;
            var v = tKey(useKey, null);
            if (!v || v === useKey) return;
            if (isIconOnly(el)) {
              el.setAttribute('aria-label', v);
              el.setAttribute('data-i18n', useKey);
            } else {
              setButtonTextPreserve(el, v);
              el.setAttribute('data-i18n', useKey);
            }
          } catch (e) { /* ignore per-element errors */ }
        });
      });
    } catch (e) {
      console.warn('translateButtons error', e);
    }
  }

  /* ---------------------------
     Apply translations to a fragment
     --------------------------- */

  function applyTranslationsTo(root) {
    root = root || document;
    try {
      if (window.AdminI18n && typeof AdminI18n.translateFragment === 'function') {
        AdminI18n.translateFragment(root);
        return;
      }
      if (window._admin && typeof window._admin.applyTranslations === 'function') {
        window._admin.applyTranslations(root);
        return;
      }
      // fallback: translate common buttons/texts and data-i18n nodes using window.ADMIN_UI
      translateButtons(root);
      var STR = (window.ADMIN_UI && window.ADMIN_UI.strings) || {};
      Array.prototype.forEach.call(root.querySelectorAll('[data-i18n]'), function (el) {
        try {
          var key = el.getAttribute('data-i18n');
          if (!key) return;
          var parts = key.split('.');
          var cur = STR;
          for (var i = 0; i < parts.length; i++) { if (!cur) break; cur = cur[parts[i]]; }
          if (typeof cur === 'string') el.textContent = cur;
        } catch (e) {}
      });
      Array.prototype.forEach.call(root.querySelectorAll('[data-i18n-placeholder]'), function (el) {
        try {
          var key = el.getAttribute('data-i18n-placeholder');
          if (!key) return;
          var parts = key.split('.');
          var cur = STR;
          for (var i = 0; i < parts.length; i++) { if (!cur) break; cur = cur[parts[i]]; }
          if (typeof cur === 'string') el.placeholder = cur;
        } catch (e) {}
      });
    } catch (e) { console.warn('applyTranslationsTo error', e); }
  }

  /* ---------------------------
     Insert HTML into panel (i18n-aware, server-driven)
     --------------------------- */

  function insertHtmlIntoPanel(html, panel) {
    var tmp = document.createElement('div');
    tmp.innerHTML = html;

    // Determine page name from fragment (meta[data-page] or data-page)
    var pageName = getPageNameFromElement(tmp) || null;

    // Replace content now
    var newContent = tmp.firstElementChild || tmp;
    safeReplaceContent(panel, panel.firstElementChild, newContent);

    // Run scripts (loads externals and executes inlines)
    return runScripts(panel).then(function () {
      // Merge any injected translations that scripts may have defined (window.__PageTranslations etc.)
      mergeInjectedTranslations(pageName, { fetchIfMissing: true });

      // Apply translations and translate buttons
      try { applyTranslationsTo(panel); } catch (e) { console.warn('translate panel failed', e); }
      try { translateButtons(panel); } catch (e) {}

      // Ensure theme vars/styles are synced in case fragment included theme info
      try {
        if (window.Admin && typeof window.Admin.syncThemeVarsFromAdminUI === 'function') {
          window.Admin.syncThemeVarsFromAdminUI();
          window.Admin.generateComponentStyles && window.Admin.generateComponentStyles();
        }
      } catch (e) {}

      return;
    });
  }

  /* ---------------------------
     Modal open/close with cleanup
     --------------------------- */

  function openModalByUrl(url, options) {
    options = options || {};
    var container = ensureContainer();
    var backdrop = container.backdrop;
    var panel = container.panel;

    panel.innerHTML = '<div style="padding:28px;text-align:center;">' + (tKey('strings.loading','Loading...')) + '</div>';
    backdrop.style.display = 'flex';
    document.body.classList.add('modal-open');

    return fetch(url, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then(function (res) {
        if (!res.ok) {
          return res.text().then(function (t) {
            throw new Error('HTTP ' + res.status + ' when loading ' + url + '\n' + t);
          });
        }
        return res.text();
      })
      .then(function (html) {
        return insertHtmlIntoPanel(html, panel).then(function () {
          try { if (panel._adminModalCleanup && typeof panel._adminModalCleanup === 'function') panel._adminModalCleanup(); } catch (e) {}
          function backdropClickHandler(e) { if (e.target === backdrop) AdminModal.closeModal(); }
          function escKeyHandler(e) { if (e.key === 'Escape') AdminModal.closeModal(); }
          backdrop.addEventListener('click', backdropClickHandler);
          document.addEventListener('keydown', escKeyHandler);
          panel._adminModalCleanup = function () {
            try { backdrop.removeEventListener('click', backdropClickHandler); } catch (e) {}
            try { document.removeEventListener('keydown', escKeyHandler); } catch (e) {}
          };
          if (options.onOpen && typeof options.onOpen === 'function') {
            try { options.onOpen(panel); } catch (e) { console.warn(e); }
          }
          return panel;
        });
      })
      .catch(function (err) {
        try { backdrop.style.display = 'none'; } catch (e) {}
        try { document.body.classList.remove('modal-open'); } catch (e) {}
        console.error('openModalByUrl error', err);
        throw err;
      });
  }

  function openModalWithHtml(html, options) {
    options = options || {};
    var container = ensureContainer();
    var backdrop = container.backdrop;
    var panel = container.panel;
    return insertHtmlIntoPanel(html, panel).then(function () {
      backdrop.style.display = 'flex';
      document.body.classList.add('modal-open');
      try { if (panel._adminModalCleanup && typeof panel._adminModalCleanup === 'function') panel._adminModalCleanup(); } catch (e) {}
      function backdropClickHandler(e) { if (e.target === backdrop) AdminModal.closeModal(); }
      function escKeyHandler(e) { if (e.key === 'Escape') AdminModal.closeModal(); }
      backdrop.addEventListener('click', backdropClickHandler);
      document.addEventListener('keydown', escKeyHandler);
      panel._adminModalCleanup = function () {
        try { backdrop.removeEventListener('click', backdropClickHandler); } catch (e) {}
        try { document.removeEventListener('keydown', escKeyHandler); } catch (e) {}
      };
      if (options.onOpen && typeof options.onOpen === 'function') {
        try { options.onOpen(panel); } catch (e) { console.warn(e); }
      }
      return panel;
    }).catch(function (err) {
      console.error('openModalWithHtml error', err);
      throw err;
    });
  }

  function closeModal() {
    var container = ensureContainer();
    var backdrop = container.backdrop;
    var panel = container.panel;
    try {
      if (panel && panel._adminModalCleanup && typeof panel._adminModalCleanup === 'function') {
        try { panel._adminModalCleanup(); } catch (e) { console.warn(e); }
        panel._adminModalCleanup = null;
      }
    } catch (e) { console.warn('modal cleanup failed', e); }
    try { backdrop.style.display = 'none'; } catch (e) {}
    try { panel.innerHTML = ''; } catch (e) {}
    try { document.body.classList.remove('modal-open'); } catch (e) {}
  }

  /* ---------------------------
     ImageStudio helper
     --------------------------- */

  function openImageStudio(opts) {
    opts = opts || {};
    var ownerType = opts.ownerType || opts.owner_type || '';
    var ownerId = opts.ownerId || opts.owner_id || 0;
    var url = '/admin/fragments/images.php?owner_type=' + encodeURIComponent(ownerType) + '&owner_id=' + encodeURIComponent(ownerId);
    return new Promise(function (resolve, reject) {
      openModalByUrl(url, {
        onOpen: function (panel) {
          function onSelect(ev) {
            try {
              if (ev && ev.detail && ev.detail.url) resolve(ev.detail.url);
              else resolve(null);
            } finally {
              window.removeEventListener('ImageStudio:selected', onSelect);
              try { AdminModal.closeModal(); } catch (e) {}
            }
          }
          function onClose() {
            try { resolve(null); } finally {
              window.removeEventListener('ImageStudio:close', onClose);
              try { AdminModal.closeModal(); } catch (e) {}
            }
          }
          window.addEventListener('ImageStudio:selected', onSelect);
          window.addEventListener('ImageStudio:close', onClose);
        }
      }).catch(function (err) { reject(err); });
    });
  }

  /* ---------------------------
     Expose API
     --------------------------- */

  window.AdminModal = {
    openModalByUrl: openModalByUrl,
    openModal: openModalWithHtml,
    closeModal: closeModal,
    isOpen: function () {
      var c = ensureContainer();
      try { return !!(c.backdrop && c.backdrop.style.display !== 'none'); } catch (e) { return false; }
    }
  };

  if (!window.ImageStudio) {
    window.ImageStudio = {
      open: function (opts) { return openImageStudio(opts || {}); }
    };
  }

  /* ---------------------------
     postMessage bridge → CustomEvent
     --------------------------- */

  window.addEventListener('message', function (ev) {
    try {
      if (!ev || !ev.data) return;
      var d = ev.data;
      if ((d.type === 'image_selected' || d.type === 'ImageStudio:selected') && d.url) {
        try { window.dispatchEvent(new CustomEvent('ImageStudio:selected', { detail: { url: d.url } })); } catch (e) {}
      }
      if (d.type === 'ImageStudio:close' || d.type === 'image_closed') {
        try { window.dispatchEvent(new CustomEvent('ImageStudio:close')); } catch (e) {}
      }
    } catch (err) { console.warn('modal message handler', err); }
  }, false);

  /* ---------------------------
     MutationObserver: auto-translate dynamic content
     --------------------------- */

  var mo;
  function startObserver() {
    if (!window.MutationObserver) return;
    if (mo) return;
    mo = new MutationObserver(function (mutList) {
      mutList.forEach(function (m) {
        Array.prototype.slice.call(m.addedNodes).forEach(function (node) {
          if (!node || node.nodeType !== 1) return;
          try {
            var pageName = getPageNameFromElement(node);
            mergeInjectedTranslations(pageName, { fetchIfMissing: true });
            applyTranslationsTo(node);
            translateButtons(node);
          } catch (e) {}
        });
      });
    });
    mo.observe(document.body, { childList: true, subtree: true });
  }

  /* ---------------------------
     Minimal CSS injection
     --------------------------- */

  (function injectStyles() {
    if (document.getElementById('adminModalStyles')) return;
    var css = '\
#adminModalBackdrop { -webkit-overflow-scrolling: touch; }\
.admin-modal-panel img { max-width:100%; height:auto; }\
.admin-modal-panel .btn { cursor:pointer; }';
    var s = document.createElement('style');
    s.id = 'adminModalStyles';
    try { s.appendChild(document.createTextNode(css)); } catch (e) { s.innerHTML = css; }
    document.head.appendChild(s);
  })();

  // init translations for current document and start observer
  try {
    var initialPage = getPageNameFromElement(document);
    mergeInjectedTranslations(initialPage, { fetchIfMissing: true });
    applyTranslationsTo(document);
  } catch (e) { console.warn(e); }
  try { translateButtons(document); } catch (e) {}
  startObserver();

  // reapply translations on language change event
  window.addEventListener('language:changed', function () {
    try { mergeInjectedTranslations(getPageNameFromElement(document), { fetchIfMissing: true }); } catch (e) {}
    try { applyTranslationsTo(document); } catch (e) {}
    try { translateButtons(document); } catch (e) {}
  });

  /* ---------------------------
     Color Slider Modal Support
     --------------------------- */
  
  // Helper to show color picker in a modal
  AdminModal.showColorPicker = function (options) {
    options = options || {};
    var title = options.title || 'Select Color';
    var onSelect = options.onSelect || function () {};
    var onCancel = options.onCancel || function () {};

    var html = '<div style="padding:20px;">';
    html += '<h2 style="margin-top:0;">' + title + '</h2>';
    html += '<div id="modalColorSlider" data-color-slider></div>';
    html += '<div style="margin-top:20px;display:flex;gap:10px;justify-content:flex-end;">';
    html += '<button class="btn btn-secondary" onclick="AdminModal.close()">Cancel</button>';
    html += '<button class="btn btn-primary" id="confirmColorBtn">Select</button>';
    html += '</div>';
    html += '</div>';

    AdminModal.show(html);

    // Initialize color slider in modal after a short delay
    setTimeout(function () {
      try {
        var container = document.getElementById('modalColorSlider');
        if (container && window.ColorSlider) {
          ColorSlider.render(container, {
            onSelect: function (color) {
              var btn = document.getElementById('confirmColorBtn');
              if (btn) {
                btn.onclick = function () {
                  onSelect(color);
                  AdminModal.close();
                };
              }
            }
          });
        }
      } catch (e) {
        console.error('Failed to initialize color slider in modal', e);
      }
    }, 100);
  };

})();