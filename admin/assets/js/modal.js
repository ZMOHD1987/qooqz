(function () {
  'use strict';

  // Ensure AdminModal namespace
  window.AdminModal = window.AdminModal || {};

  /* ---------------------------
     Ensure container
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
     i18n helpers
     --------------------------- */
  function tKey(key, fallback) {
    if (!key) return fallback || '';
    try {
      // First, check window.ADMIN_UI.strings
      var ui = window.ADMIN_UI || {};
      var parts = key.split('.');
      var cur = ui.strings || {};
      for (var i = 0; i < parts.length; i++) {
        if (!cur || typeof cur !== 'object' || !(parts[i] in cur)) { cur = undefined; break; }
        cur = cur[parts[i]];
      }
      if (typeof cur === 'string') return cur;
      // Fallback to I18nLoader or _admin
      if (window.I18nLoader && window.I18nLoader.translateFragment) {
        // Use cache if available
        if (window.I18nLoader.cache && window.I18nLoader.cache[key]) return window.I18nLoader.cache[key];
      }
      if (window._admin && typeof window._admin.resolveKey === 'function') {
        var v = window._admin.resolveKey(key);
        if (v !== null) return v;
      }
    } catch (e) {
      console.warn('tKey error', e);
    }
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

  function mergeInjectedTranslations(pageName, options) {
    options = options || {};
    try {
      window.ADMIN_UI = window.ADMIN_UI || {};
      window.ADMIN_UI.strings = window.ADMIN_UI.strings || {};
      // Merge from globals if present
      var candidates = [pageName ? '__' + pageName + 'Translations' : null, '__PageTranslations', 'pageTranslations'].filter(Boolean);
      candidates.forEach(function (k) {
        try {
          var obj = window[k];
          if (obj && typeof obj === 'object') {
            var src = (obj.strings && typeof obj.strings === 'object') ? obj.strings : obj;
            Object.assign(window.ADMIN_UI.strings, src);
            if (obj.direction) window.ADMIN_UI.direction = obj.direction;
          }
        } catch (e) {}
      });
    } catch (e) { console.warn('mergeInjectedTranslations failed', e); }
  }

  /* ---------------------------
     Button/text translation
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
          } catch (e) {}
        });
      });
    } catch (e) {
      console.warn('translateButtons error', e);
    }
  }

  /* ---------------------------
     Apply translations
     --------------------------- */
  function applyTranslationsTo(root) {
    root = root || document;
    try {
      if (window.I18nLoader && typeof window.I18nLoader.translateFragment === 'function') {
        window.I18nLoader.translateFragment(root);
        return;
      }
      if (window._admin && typeof window._admin.applyTranslations === 'function') {
        window._admin.applyTranslations(root);
        return;
      }
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
     Insert HTML into panel
     --------------------------- */
  function insertHtmlIntoPanel(html, panel) {
    var tmp = document.createElement('div');
    tmp.innerHTML = html;
    var pageName = getPageNameFromElement(tmp) || null;
    var newContent = tmp.firstElementChild || tmp;
    safeReplaceContent(panel, panel.firstElementChild, newContent);
    return runScripts(panel).then(function () {
      mergeInjectedTranslations(pageName, { fetchIfMissing: true });
      applyTranslationsTo(panel);
      translateButtons(panel);
      if (window.Admin && typeof window.Admin.syncThemeVarsFromAdminUI === 'function') {
        window.Admin.syncThemeVarsFromAdminUI();
        window.Admin.generateComponentStyles && window.Admin.generateComponentStyles();
      }
      return;
    });
  }

  /* ---------------------------
     Modal open/close
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
  AdminModal.openModalByUrl = openModalByUrl;
  AdminModal.openModal = openModalWithHtml;
  AdminModal.closeModal = closeModal;
  AdminModal.isOpen = function () {
    var c = ensureContainer();
    try { return !!(c.backdrop && c.backdrop.style.display !== 'none'); } catch (e) { return false; }
  };

  // Color Slider Modal Support
  AdminModal.showColorPicker = function (options) {
    options = options || {};
    var title = options.title || 'Select Color';
    var onSelect = options.onSelect || function () {};
    var onCancel = options.onCancel || function () {};

    var html = '<div style="padding:20px;">';
    html += '<h2 style="margin-top:0;">' + title + '</h2>';
    html += '<div id="modalColorSlider" data-color-slider></div>';
    html += '<div style="margin-top:20px;display:flex;gap:10px;justify-content:flex-end;">';
    html += '<button class="btn btn-secondary" onclick="AdminModal.closeModal()">Cancel</button>';
    html += '<button class="btn btn-primary" id="confirmColorBtn">Select</button>';
    html += '</div>';
    html += '</div>';

    return openModalWithHtml(html).then(function (panel) {
      if (typeof requestAnimationFrame !== 'undefined') {
        requestAnimationFrame(function() {
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
                        AdminModal.closeModal();
                      };
                    }
                  }
                });
              }
            } catch (e) {
              console.error('Failed to initialize color slider in modal', e);
            }
          }, 50);
        });
      } else {
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
                      AdminModal.closeModal();
                    };
                  }
                }
              });
            }
          } catch (e) {
            console.error('Failed to initialize color slider in modal', e);
          }
        }, 100);
      }
    });
  };

  if (!window.ImageStudio) {
    window.ImageStudio = {
      open: function (opts) { return openImageStudio(opts || {}); }
    };
  }

  /* ---------------------------
     postMessage bridge
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
     MutationObserver
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
     CSS injection
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

  // Init
  try {
    var initialPage = getPageNameFromElement(document);
    mergeInjectedTranslations(initialPage, { fetchIfMissing: true });
    applyTranslationsTo(document);
  } catch (e) { console.warn(e); }
  try { translateButtons(document); } catch (e) {}
  startObserver();

})();