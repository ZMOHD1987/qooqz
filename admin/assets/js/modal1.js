/**
 * admin/assets/js/modal.js
 *
 * Robust modal loader + i18n-aware button/text translator for the admin UI.
 * Extended i18n handling: accepts many translation file name patterns and
 * will attempt to load page-specific or custom paths embedded in fragments.
 *
 * - Exposes window.AdminModal and window.ImageStudio
 * - Loads/executes scripts inside fetched fragments (skips data-no-run)
 * - Detects meta[data-page], meta[data-i18n-files] or data-i18n-files attributes
 *   inside loaded HTML and attempts to load/merge translation JSONs before applying translations.
 * - Respects window.ADMIN_UI.__skipGlobalLoad (if set by fragment) to skip global {lang}.json
 * - Applies translations using AdminI18n / window._admin / window.ADMIN_UI (fallback)
 * - Safe button/text translation that preserves icon-only buttons (sets aria-label instead)
 * - Stores cleanup handlers to avoid event listener leaks
 * - Forwards postMessage from fragments to CustomEvents used by ImageStudio
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

  // Safely replace content to avoid replaceChild errors
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
    // filter scripts to run (skip data-no-run)
    var externals = scripts.filter(function (s) { return s.src && !s.hasAttribute('data-no-run'); });
    var inlines = scripts.filter(function (s) { return !s.src && !s.hasAttribute('data-no-run'); });

    // remove originals to prevent double-run when re-inserting HTML
    externals.concat(inlines).forEach(function (s) { if (s.parentNode) s.parentNode.removeChild(s); });

    // load external scripts sequentially
    return externals.reduce(function (p, script) {
      return p.then(function () {
        return new Promise(function (resolve) {
          try {
            var tag = document.createElement('script');
            if (script.type) tag.type = script.type;
            tag.src = script.src;
            tag.async = false;
            // copy attributes (integrity, crossorigin etc)
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
      // execute inline scripts in order by creating new script nodes
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
     Lightweight deepMerge & json loader used by modal for i18n merging
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

  function loadJson(url) {
    return fetch(url, { credentials: 'same-origin', cache: 'no-cache' }).then(function (res) {
      if (!res.ok) return Promise.reject(new Error('Failed to load ' + url + ' status ' + res.status));
      return res.json();
    });
  }

  /* ---------------------------
     i18n helpers (single source + flexible loader)
     --------------------------- */

  function tKey(key, fallback) {
    if (!key) return fallback || '';
    try {
      if (window.AdminI18n && typeof AdminI18n.tKey === 'function') return AdminI18n.tKey(key, fallback);
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
      if (ui.labels && ui.labels[key]) return ui.labels[key];
    } catch (e) {
      console.warn('tKey error', e);
    }
    try {
      window.I18N_MISSING_KEYS = window.I18N_MISSING_KEYS || {};
      window.I18N_MISSING_KEYS[key] = (window.I18N_MISSING_KEYS[key] || 0) + 1;
    } catch (e) {}
    return fallback || key;
  }

  /* Build candidate i18n file paths from HTML content */
  function buildCandidatesFromElement(el) {
    var candidates = [];
    try {
      var lang = window.ADMIN_LANG || document.documentElement.lang || 'en';
      var base = (window.LANG_BASE || '/languages/admin').replace(/\/$/, '');
      // 1) custom meta: meta[data-i18n-files] (comma separated URLs relative to site or absolute)
      var metaFiles = el.querySelector && el.querySelector('meta[data-i18n-files]');
      if (metaFiles) {
        var raw = metaFiles.getAttribute('data-i18n-files') || '';
        raw.split(',').map(function (s) { return s.trim(); }).filter(Boolean).forEach(function (p) {
          candidates.push(p);
        });
      }
      // 2) data-i18n-files attribute on root element
      var rootAttr = el.getAttribute && el.getAttribute('data-i18n-files');
      if (rootAttr) {
        rootAttr.split(',').map(function (s) { return s.trim(); }).filter(Boolean).forEach(function (p) { candidates.push(p); });
      }
      // 3) meta[data-page] -> page-specific path pattern
      var metaPage = el.querySelector && el.querySelector('meta[data-page]');
      var pageName = null;
      if (metaPage) pageName = metaPage.getAttribute('data-page') || metaPage.dataset.page;
      // also check root element data-page
      if (!pageName && el.getAttribute && el.getAttribute('data-page')) pageName = el.getAttribute('data-page');
      if (pageName) {
        // prefer page-specific: /languages/admin/<Page>/{lang}_{Page}.json
        candidates.push(base + '/' + encodeURIComponent(pageName) + '/' + encodeURIComponent(lang) + '_' + encodeURIComponent(pageName) + '.json');
      }
      // 4) if no page-specific candidate added, optionally include global
      // But we will not force add; respect __skipGlobalLoad later.
      candidates.push(base + '/' + encodeURIComponent(lang) + '.json');
    } catch (e) { console.warn('buildCandidatesFromElement error', e); }
    // unique
    return candidates.filter(function (v, i, a) { return v && a.indexOf(v) === i; });
  }

  /* Load and merge i18n JSONs for the provided element before applying translations.
     Respects window.ADMIN_UI.__skipGlobalLoad to skip global {lang}.json. */
  function loadAndMergeI18nForElement(el) {
    var cands = buildCandidatesFromElement(el);
    if (!cands || cands.length === 0) return Promise.resolve();
    window.ADMIN_UI = window.ADMIN_UI || {};
    window.ADMIN_UI.strings = window.ADMIN_UI.strings || {};
    var skipGlobal = !!(window.ADMIN_UI && window.ADMIN_UI.__skipGlobalLoad);
    // filter if skipGlobal and candidate matches global pattern (/languages/admin/{lang}.json)
    var filtered = cands.filter(function (u) {
      if (!skipGlobal) return true;
      try {
        var lang = window.ADMIN_LANG || document.documentElement.lang || 'en';
        var globalPattern = '/languages/admin/' + encodeURIComponent(lang) + '.json';
        if (u.indexOf(globalPattern) !== -1) return false;
      } catch (e) {}
      return true;
    });
    // sequential load
    return filtered.reduce(function (p, url) {
      return p.then(function () {
        return loadJson(url).then(function (json) {
          if (json) {
            deepMerge(window.ADMIN_UI.strings, json.strings || json || {});
            if (json.direction) window.ADMIN_UI.direction = json.direction;
          }
        }).catch(function (err) {
          // continue even if a candidate fails
          console.info('Modal i18n candidate skipped/failed:', url, err && err.message);
        });
      });
    }, Promise.resolve());
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
      } else if (window._admin && typeof window._admin.applyTranslations === 'function') {
        window._admin.applyTranslations(root);
      } else {
        // fallback: translate common buttons/texts and attributes
        translateButtons(root);
        // translate data-i18n simple elements
        var STR = (window.ADMIN_UI && window.ADMIN_UI.strings) || {};
        Array.prototype.forEach.call(root.querySelectorAll('[data-i18n]'), function (el) {
          try {
            var key = el.getAttribute('data-i18n');
            var parts = key.split('.');
            var cur = STR;
            for (var i = 0; i < parts.length; i++) { if (!cur) break; cur = cur[parts[i]]; }
            if (typeof cur === 'string') el.textContent = cur;
          } catch (e) {}
        });
        Array.prototype.forEach.call(root.querySelectorAll('[data-i18n-placeholder]'), function (el) {
          try {
            var key = el.getAttribute('data-i18n-placeholder');
            var parts = key.split('.');
            var cur = STR;
            for (var i = 0; i < parts.length; i++) { if (!cur) break; cur = cur[parts[i]]; }
            if (typeof cur === 'string') el.placeholder = cur;
          } catch (e) {}
        });
      }
    } catch (e) { console.warn('applyTranslationsTo error', e); }
  }

  /* ---------------------------
     Insert HTML into panel (i18n-aware)
     --------------------------- */

  function insertHtmlIntoPanel(html, panel) {
    var tmp = document.createElement('div');
    tmp.innerHTML = html;

    // Attempt to load/merge i18n JSON candidates found inside the loaded fragment
    return loadAndMergeI18nForElement(tmp).then(function () {
      var newContent = tmp.firstElementChild || tmp;
      safeReplaceContent(panel, panel.firstElementChild, newContent);
      // run scripts then apply translations
      return runScripts(panel).then(function () {
        try { applyTranslationsTo(panel); } catch (e) { console.warn('translate panel failed', e); }
        try { translateButtons(panel); } catch (e) {}
      });
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
          // remove old handlers if any
          try { if (panel._adminModalCleanup && typeof panel._adminModalCleanup === 'function') panel._adminModalCleanup(); } catch (e) {}
          // create handlers and store cleanup
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
            // Attempt to load/merge i18n candidates found in the newly added node
            loadAndMergeI18nForElement(node).then(function () {
              applyTranslationsTo(node);
              translateButtons(node);
            }).catch(function () {
              applyTranslationsTo(node);
              translateButtons(node);
            });
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
  try { applyTranslationsTo(document); } catch (e) {}
  try { translateButtons(document); } catch (e) {}
  startObserver();

  // reapply translations on language change event
  window.addEventListener('language:changed', function () {
    try { applyTranslationsTo(document); } catch (e) {}
    try { translateButtons(document); } catch (e) {}
  });

})();