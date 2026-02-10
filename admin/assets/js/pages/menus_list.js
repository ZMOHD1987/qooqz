// htdocs/admin/assets/js/modal.js
// Robust modal loader and ImageStudio helper for the admin UI.
// - Exposes window.AdminModal with methods:
//    - openModalByUrl(url, options) -> Promise<HTMLElement>
//    - openModal(htmlString, options) -> HTMLElement
//    - closeModal() -> void
//    - isOpen() -> boolean
// - Exposes window.ImageStudio.open(opts) which uses openModalByUrl to load /admin/fragments/images.php
// - Safely inserts/replaces modal content and runs scripts (skips scripts with data-no-run).
// - Protects against "Failed to execute 'replaceChild' on 'Node': The node to be replaced is not a child of this node."
//
// Usage:
//  AdminModal.openModalByUrl('/admin/fragments/images.php?owner_type=category&owner_id=1')
//    .then(modalInner => { /* modal loaded */ })
//    .catch(err => console.error(err));
//
// Save as UTF-8 without BOM.

(function () {
  'use strict';

  if (window.AdminModal) return; // already loaded

  // Create modal container on first use
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

  // Utility: safely replace child's content (avoid replaceChild error)
  function safeReplaceContent(parent, oldChild, newChild) {
    try {
      if (oldChild && oldChild.parentNode === parent) {
        parent.replaceChild(newChild, oldChild);
      } else {
        // fallback: clear parent and append newChild
        while (parent.firstChild) parent.removeChild(parent.firstChild);
        parent.appendChild(newChild);
      }
    } catch (e) {
      // ultimate fallback: clear and append
      while (parent.firstChild) parent.removeChild(parent.firstChild);
      parent.appendChild(newChild);
      console.warn('safeReplaceContent fallback used', e);
    }
  }

  // Run scripts inside a container: load external scripts sequentially and run inline scripts
  // Skip <script> with data-no-run attribute.
  function runScripts(container) {
    var scripts = Array.prototype.slice.call(container.querySelectorAll('script'));
    var externals = scripts.filter(function (s) { return s.src && !s.hasAttribute('data-no-run'); });
    var inlines = scripts.filter(function (s) { return !s.src && !s.hasAttribute('data-no-run'); });

    // Remove original scripts to avoid double-run when inserting HTML
    externals.concat(inlines).forEach(function (s) { s.parentNode && s.parentNode.removeChild(s); });

    // load external scripts in sequence to preserve order
    return externals.reduce(function (p, script) {
      return p.then(function () {
        return new Promise(function (resolve, reject) {
          try {
            var tag = document.createElement('script');
            tag.src = script.src;
            if (script.type) tag.type = script.type;
            tag.async = false;
            // preserve attributes (integrity, crossorigin etc)
            Array.prototype.slice.call(script.attributes).forEach(function (attr) {
              try { tag.setAttribute(attr.name, attr.value); } catch (e) {}
            });
            tag.onload = function () { resolve(); };
            tag.onerror = function (ev) {
              console.error('Failed to load script', script.src, ev);
              // resolve to continue loading remaining scripts instead of rejecting
              resolve();
            };
            document.head.appendChild(tag);
          } catch (err) {
            console.error('runScripts external error', err);
            resolve();
          }
        });
      });
    }, Promise.resolve()).then(function () {
      // execute inline scripts in inserted order (by creating new script nodes)
      inlines.forEach(function (script) {
        try {
          var s = document.createElement('script');
          if (script.type) s.type = script.type;
          s.text = script.textContent;
          // copy attributes to new tag except src
          Array.prototype.slice.call(script.attributes).forEach(function (attr) {
            if (attr.name !== 'src') try { s.setAttribute(attr.name, attr.value); } catch (e) {}
          });
          // Append to container to execute in its context
          container.appendChild(s);
          // remove to avoid clutter
          container.removeChild(s);
        } catch (e) {
          console.warn('Inline script execution failed', e);
        }
      });
      return;
    });
  }

  // Read meta tags like <meta data-page=... data-assets-js=...>
  function readMetaFromFragment(fragmentEl) {
    var meta = fragmentEl.querySelector('meta[data-page]');
    if (!meta) return null;
    return {
      page: meta.getAttribute('data-page'),
      css: meta.getAttribute('data-assets-css'),
      js: meta.getAttribute('data-assets-js')
    };
  }

  // Insert HTML into modal panel safely and run inner scripts
  function insertHtmlIntoPanel(html, panel) {
    // Create a temporary wrapper
    var tmp = document.createElement('div');
    tmp.innerHTML = html;
    // find the element to insert: prefer fragment root element (first child)
    var newContent = tmp.firstElementChild || tmp;
    // Use safe replace
    safeReplaceContent(panel, panel.firstElementChild, newContent);
    // run scripts inside newly inserted content
    return runScripts(panel);
  }

  // Open modal by loading URL and inserting content
  function openModalByUrl(url, options) {
    options = options || {};
    var container = ensureContainer();
    var backdrop = container.backdrop;
    var panel = container.panel;

    // show loader
    panel.innerHTML = '<div style="padding:28px;text-align:center;">تحميل…</div>';
    backdrop.style.display = 'flex';

    // fetch HTML
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
        // insert HTML
        return insertHtmlIntoPanel(html, panel).then(function () {
          // attach close on backdrop click (only when clicking outside panel)
          backdrop.removeEventListener('click', backdropClickHandler);
          backdrop.addEventListener('click', backdropClickHandler);
          // trap Esc key to close modal
          document.addEventListener('keydown', escKeyHandler);
          // optional onOpen callback
          if (options.onOpen && typeof options.onOpen === 'function') {
            try { options.onOpen(panel); } catch (e) { console.warn(e); }
          }
          return panel;
        });
      })
      .catch(function (err) {
        // hide backdrop if loading failed
        try { backdrop.style.display = 'none'; } catch (e) {}
        console.error('openModalByUrl error', err);
        throw err;
      });

    function backdropClickHandler(e) {
      // close only when clicking backdrop (not panel children)
      if (e.target === backdrop) AdminModal.closeModal();
    }
    function escKeyHandler(e) {
      if (e.key === 'Escape') AdminModal.closeModal();
    }
  }

  // Open modal with already available HTML string (useful to avoid extra fetch)
  function openModalWithHtml(html, options) {
    options = options || {};
    var container = ensureContainer();
    var backdrop = container.backdrop;
    var panel = container.panel;
    try {
      insertHtmlIntoPanel(html, panel).then(function () {
        backdrop.style.display = 'flex';
        backdrop.addEventListener('click', backdropClickHandler);
        document.addEventListener('keydown', escKeyHandler);
        if (options.onOpen && typeof options.onOpen === 'function') {
          try { options.onOpen(panel); } catch (e) { console.warn(e); }
        }
      });
    } catch (e) {
      console.error('openModalWithHtml error', e);
      throw e;
    }
    function backdropClickHandler(e) {
      if (e.target === backdrop) AdminModal.closeModal();
    }
    function escKeyHandler(e) {
      if (e.key === 'Escape') AdminModal.closeModal();
    }
    return panel;
  }

  function closeModal() {
    var container = ensureContainer();
    var backdrop = container.backdrop;
    var panel = container.panel;
    // remove event listeners (safe)
    backdrop.removeEventListener('click', function () {});
    document.removeEventListener('keydown', function () {});
    // close animation (simple)
    try { backdrop.style.display = 'none'; } catch (e) {}
    // clear panel content to free memory
    try { panel.innerHTML = ''; } catch (e) {}
  }

  // Public ImageStudio helper that opens images fragment and returns a Promise that resolves to the selected URL
  function openImageStudio(opts) {
    opts = opts || {};
    var ownerType = opts.ownerType || opts.owner_type || '';
    var ownerId = opts.ownerId || opts.owner_id || 0;
    var url = '/admin/fragments/images.php?owner_type=' + encodeURIComponent(ownerType) + '&owner_id=' + encodeURIComponent(ownerId);
    return new Promise(function (resolve, reject) {
      openModalByUrl(url, {
        onOpen: function (panel) {
          // listen for selection events from the fragment scripts
          function onSelect(ev) {
            try {
              if (ev && ev.detail && ev.detail.url) {
                resolve(ev.detail.url);
              } else {
                resolve(null);
              }
            } finally {
              window.removeEventListener('ImageStudio:selected', onSelect);
              AdminModal.closeModal();
            }
          }
          window.addEventListener('ImageStudio:selected', onSelect);
        }
      }).catch(function (err) {
        reject(err);
      });
    });
  }

  // Expose API
  window.AdminModal = {
    openModalByUrl: openModalByUrl,
    openModal: openModalWithHtml,
    closeModal: closeModal,
    isOpen: function () { return !!(document.getElementById('adminModalBackdrop') && document.getElementById('adminModalBackdrop').style.display !== 'none'); }
  };

  // Expose ImageStudio if not already defined
  if (!window.ImageStudio) {
    window.ImageStudio = {
      open: function (opts) { return openImageStudio(opts || {}); }
    };
  }

  // Basic styles (append once)
  (function injectStyles() {
    if (document.getElementById('adminModalStyles')) return;
    var css = `
#adminModalBackdrop { -webkit-overflow-scrolling: touch; }
.admin-modal-panel img { max-width:100%; height:auto; }
.admin-modal-panel .btn { cursor:pointer; }
`;
    var s = document.createElement('style');
    s.id = 'adminModalStyles';
    s.innerHTML = css;
    document.head.appendChild(s);
  })();

})();