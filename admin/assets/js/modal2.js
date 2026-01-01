/*!
 * admin/assets/js/modal.js
 *
 * Simple, fast modal loader – fully compatible with server-driven i18n & theme
 * - Relies on window.ADMIN_UI.strings and window._admin.applyTranslations from admin_core.js
 * - No JSON fetching, no complex injection detection
 * - Executes scripts in fragments
 * - Supports ImageStudio
 */

(function () {
  'use strict';

  if (window.AdminModal) return;

  // ---------------------------
  // Container creation
  // ---------------------------
  function ensureContainer() {
    let backdrop = document.getElementById('adminModalBackdrop');
    if (!backdrop) {
      backdrop = document.createElement('div');
      backdrop.id = 'adminModalBackdrop';
      backdrop.className = 'admin-modal-backdrop';
      backdrop.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,0.35);display:none;align-items:center;justify-content:center;z-index:13000;padding:20px;overflow:auto;';
      document.body.appendChild(backdrop);
    }

    let panel = backdrop.querySelector('.admin-modal-panel');
    if (!panel) {
      panel = document.createElement('div');
      panel.className = 'admin-modal-panel';
      panel.style.cssText = 'width:920px;max-width:100%;max-height:90vh;overflow:auto;background:#fff;border-radius:8px;box-shadow:0 8px 30px rgba(0,0,0,0.2);position:relative;';
      backdrop.appendChild(panel);
    }

    return { backdrop, panel };
  }

  // ---------------------------
  // Run scripts in fragment
  // ---------------------------
  function runScripts(container) {
    const scripts = [...container.querySelectorAll('script')];

    // Remove old scripts to avoid duplicates
    scripts.forEach(s => s.parentNode?.removeChild(s));

    // Load external scripts sequentially
    return scripts
      .filter(s => s.src && !s.hasAttribute('data-no-run'))
      .reduce((promise, script) => {
        return promise.then(() => {
          return new Promise(resolve => {
            const newScript = document.createElement('script');
            newScript.src = script.src;
            newScript.async = false;
            [...script.attributes].forEach(attr => {
              if (attr.name !== 'src') newScript.setAttribute(attr.name, attr.value);
            });
            newScript.onload = newScript.onerror = resolve;
            document.head.appendChild(newScript);
          });
        });
      }, Promise.resolve())
      .then(() => {
        // Execute inline scripts
        scripts
          .filter(s => !s.src && !s.hasAttribute('data-no-run'))
          .forEach(script => {
            const newScript = document.createElement('script');
            newScript.textContent = script.textContent;
            [...script.attributes].forEach(attr => {
              if (attr.name !== 'src') newScript.setAttribute(attr.name, attr.value);
            });
            container.appendChild(newScript);
            container.removeChild(newScript);
          });
      });
  }

  // ---------------------------
  // Insert HTML + translate + run scripts
  // ---------------------------
  function insertContent(html, panel) {
    const temp = document.createElement('div');
    temp.innerHTML = html;
    const content = temp.firstElementChild || temp;

    // Replace panel content
    while (panel.firstChild) panel.removeChild(panel.firstChild);
    panel.appendChild(content);

    // Run scripts
    return runScripts(panel).then(() => {
      // Apply translations using admin_core.js (fast & reliable)
      if (window._admin?.applyTranslations) {
        window._admin.applyTranslations(panel);
      }

      // Apply permissions if needed
      if (window.Admin?.applyPermsToContainer) {
        window.Admin.applyPermsToContainer(panel);
      }

      // Init any page-specific JS
      if (window.Admin?.initPageFromFragment) {
        window.Admin.initPageFromFragment(panel);
      }
    });
  }

  // ---------------------------
  // Open modal by URL
  // ---------------------------
  function openModalByUrl(url, options = {}) {
    const { backdrop, panel } = ensureContainer();

    panel.innerHTML = '<div style="padding:40px;text-align:center;font-size:18px;color:#666;">Loading...</div>';
    backdrop.style.display = 'flex';
    document.body.classList.add('modal-open');

    return fetch(url, {
      credentials: 'same-origin',
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
      .then(res => {
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        return res.text();
      })
      .then(html => insertContent(html, panel))
      .then(() => {
        // Cleanup previous handlers
        if (panel._cleanup) panel._cleanup();

        const close = () => AdminModal.closeModal();

        const clickHandler = e => e.target === backdrop && close();
        const escHandler = e => e.key === 'Escape' && close();

        backdrop.addEventListener('click', clickHandler);
        document.addEventListener('keydown', escHandler);

        panel._cleanup = () => {
          backdrop.removeEventListener('click', clickHandler);
          document.removeEventListener('keydown', escHandler);
        };

        options.onOpen?.(panel);
        return panel;
      })
      .catch(err => {
        console.error('Modal load failed:', err);
        panel.innerHTML = '<div style="padding:40px;color:#c0392b;text-align:center;">Failed to load content</div>';
      });
  }

  // ---------------------------
  // Open modal with raw HTML
  // ---------------------------
  function openModalWithHtml(html, options = {}) {
    const { backdrop, panel } = ensureContainer();
    backdrop.style.display = 'flex';
    document.body.classList.add('modal-open');

    return insertContent(html, panel).then(() => {
      if (panel._cleanup) panel._cleanup();

      const close = () => AdminModal.closeModal();
      const clickHandler = e => e.target === backdrop && close();
      const escHandler = e => e.key === 'Escape' && close();

      backdrop.addEventListener('click', clickHandler);
      document.addEventListener('keydown', escHandler);

      panel._cleanup = () => {
        backdrop.removeEventListener('click', clickHandler);
        document.removeEventListener('keydown', escHandler);
      };

      options.onOpen?.(panel);
      return panel;
    });
  }

  // ---------------------------
  // Close modal
  // ---------------------------
  function closeModal() {
    const { backdrop, panel } = ensureContainer();
    if (panel._cleanup) {
      panel._cleanup();
      panel._cleanup = null;
    }
    backdrop.style.display = 'none';
    panel.innerHTML = '';
    document.body.classList.remove('modal-open');
  }

  // ---------------------------
  // ImageStudio helper
  // ---------------------------
  function openImageStudio(opts = {}) {
    const ownerType = opts.ownerType || opts.owner_type || '';
    const ownerId = opts.ownerId || opts.owner_id || 0;
    const url = `/admin/fragments/images.php?owner_type=${encodeURIComponent(ownerType)}&owner_id=${encodeURIComponent(ownerId)}`;

    return new Promise((resolve, reject) => {
      openModalByUrl(url, {
        onOpen: () => {
          const onSelect = (e) => {
            resolve(e.detail?.url || null);
            window.removeEventListener('ImageStudio:selected', onSelect);
            AdminModal.closeModal();
          };
          const onClose = () => {
            resolve(null);
            window.removeEventListener('ImageStudio:close', onClose);
            AdminModal.closeModal();
          };

          window.addEventListener('ImageStudio:selected', onSelect);
          window.addEventListener('ImageStudio:close', onClose);
        }
      }).catch(reject);
    });
  }

  // ---------------------------
  // CSS injection
  // ---------------------------
  if (!document.getElementById('adminModalStyles')) {
    const style = document.createElement('style');
    style.id = 'adminModalStyles';
    style.textContent = `
      #adminModalBackdrop { -webkit-overflow-scrolling: touch; }
      .admin-modal-panel img { max-width:100%; height:auto; }
      .admin-modal-panel .btn { cursor:pointer; }
    `;
    document.head.appendChild(style);
  }

  // ---------------------------
  // postMessage bridge for ImageStudio
  // ---------------------------
  window.addEventListener('message', (ev) => {
    if (!ev.data) return;
    const d = ev.data;
    if ((d.type === 'image_selected' || d.type === 'ImageStudio:selected') && d.url) {
      window.dispatchEvent(new CustomEvent('ImageStudio:selected', { detail: { url: d.url } }));
    }
    if (d.type === 'ImageStudio:close' || d.type === 'image_closed') {
      window.dispatchEvent(new CustomEvent('ImageStudio:close'));
    }
  });

  // ---------------------------
  // Expose API
  // ---------------------------
  window.AdminModal = {
    openModalByUrl,
    openModal: openModalWithHtml,
    closeModal,
    isOpen: () => ensureContainer().backdrop.style.display !== 'none'
  };

  if (!window.ImageStudio) {
    window.ImageStudio = { open: openImageStudio };
  }

  console.log('AdminModal ready – server-driven i18n compatible');
})();