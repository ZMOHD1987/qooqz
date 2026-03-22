/**
 * admin/assets/js/modal.js
 * Production Modal Manager – fully dynamic, uses CSS variables.
 */

(function() {
    'use strict';

    // Ensure namespace
    window.AdminModal = window.AdminModal || {};

    let backdrop = null;
    let panel = null;
    let cleanupFn = null;

    /**
     * Create modal container (if not exists) with CSS classes only.
     */
    function ensureContainer() {
        if (!backdrop) {
            backdrop = document.createElement('div');
            backdrop.id = 'adminModalBackdrop';
            backdrop.className = 'admin-modal-backdrop';
            backdrop.style.display = 'none';          // Only display is inline; all other styles come from CSS.
            document.body.appendChild(backdrop);
        }
        if (!panel && backdrop) {
            panel = document.createElement('div');
            panel.className = 'admin-modal-panel';
            backdrop.appendChild(panel);
        }
        return { backdrop, panel };
    }

    /**
     * Run scripts inside a fragment (reuse existing function if available).
     */
    function runScripts(container) {
        if (window.AdminFramework && typeof window.AdminFramework.runFragmentScripts === 'function') {
            return window.AdminFramework.runFragmentScripts(container);
        }
        // Fallback implementation
        const scripts = Array.from(container.querySelectorAll('script:not([data-no-run])'));
        const promises = scripts.map(script => {
            if (script.src) {
                return new Promise((resolve, reject) => {
                    const newScript = document.createElement('script');
                    Array.from(script.attributes).forEach(attr => newScript.setAttribute(attr.name, attr.value));
                    newScript.onload = resolve;
                    newScript.onerror = reject;
                    document.head.appendChild(newScript);
                    script.remove();
                });
            } else {
                // Inline script
                const newScript = document.createElement('script');
                newScript.textContent = script.textContent;
                Array.from(script.attributes).forEach(attr => newScript.setAttribute(attr.name, attr.value));
                container.appendChild(newScript);
                script.remove();
                return Promise.resolve();
            }
        });
        return Promise.all(promises);
    }

    /**
     * Apply translations to the modal content.
     */
    function applyTranslationsTo(container) {
        if (window.I18nLoader && typeof window.I18nLoader.translateFragment === 'function') {
            window.I18nLoader.translateFragment(container);
        } else if (window._admin && typeof window._admin.applyTranslations === 'function') {
            window._admin.applyTranslations(container);
        } else {
            // Minimal fallback
            const strings = (window.ADMIN_UI && window.ADMIN_UI.strings) || {};
            container.querySelectorAll('[data-i18n]').forEach(el => {
                const key = el.getAttribute('data-i18n');
                const parts = key.split('.');
                let val = strings;
                for (let p of parts) if (val && typeof val === 'object') val = val[p];
                if (typeof val === 'string') el.textContent = val;
            });
            container.querySelectorAll('[data-i18n-placeholder]').forEach(el => {
                const key = el.getAttribute('data-i18n-placeholder');
                const parts = key.split('.');
                let val = strings;
                for (let p of parts) if (val && typeof val === 'object') val = val[p];
                if (typeof val === 'string') el.placeholder = val;
            });
        }
    }

    /**
     * Insert HTML into the panel and run scripts.
     */
    function insertHtmlIntoPanel(html) {
        const tmp = document.createElement('div');
        tmp.innerHTML = html;
        const newContent = tmp.firstElementChild || tmp;
        // Clear panel
        while (panel.firstChild) panel.removeChild(panel.firstChild);
        panel.appendChild(newContent);
        return runScripts(panel).then(() => {
            applyTranslationsTo(panel);
            // Notify any page‑specific initialisation
            if (window.page && window.page.run && typeof window.page.run === 'function') {
                window.page.run();
            }
            return panel;
        });
    }

    /**
     * Open modal by fetching URL.
     */
    function openModalByUrl(url, options = {}) {
        const { backdrop, panel } = ensureContainer();
        if (!backdrop || !panel) return Promise.reject('Modal container not available');

        // Show loading indicator
        panel.innerHTML = `<div style="padding:28px;text-align:center;">Loading...</div>`;
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
        .then(html => insertHtmlIntoPanel(html))
        .then(() => {
            // Attach event handlers
            if (cleanupFn) cleanupFn();

            const onBackdropClick = e => {
                if (e.target === backdrop) AdminModal.closeModal();
            };
            const onKeyDown = e => {
                if (e.key === 'Escape') AdminModal.closeModal();
            };
            backdrop.addEventListener('click', onBackdropClick);
            document.addEventListener('keydown', onKeyDown);

            cleanupFn = () => {
                backdrop.removeEventListener('click', onBackdropClick);
                document.removeEventListener('keydown', onKeyDown);
            };

            if (options.onOpen && typeof options.onOpen === 'function') {
                options.onOpen(panel);
            }
            return panel;
        })
        .catch(err => {
            console.error('Failed to open modal', err);
            AdminModal.closeModal();
            throw err;
        });
    }

    /**
     * Open modal with pre‑rendered HTML string.
     */
    function openModalWithHtml(html, options = {}) {
        const { backdrop, panel } = ensureContainer();
        if (!backdrop || !panel) return Promise.reject('Modal container not available');

        backdrop.style.display = 'flex';
        document.body.classList.add('modal-open');

        return insertHtmlIntoPanel(html)
            .then(() => {
                if (cleanupFn) cleanupFn();

                const onBackdropClick = e => {
                    if (e.target === backdrop) AdminModal.closeModal();
                };
                const onKeyDown = e => {
                    if (e.key === 'Escape') AdminModal.closeModal();
                };
                backdrop.addEventListener('click', onBackdropClick);
                document.addEventListener('keydown', onKeyDown);

                cleanupFn = () => {
                    backdrop.removeEventListener('click', onBackdropClick);
                    document.removeEventListener('keydown', onKeyDown);
                };

                if (options.onOpen && typeof options.onOpen === 'function') {
                    options.onOpen(panel);
                }
                return panel;
            })
            .catch(err => {
                console.error('Failed to open modal with HTML', err);
                AdminModal.closeModal();
                throw err;
            });
    }

    /**
     * Close modal and clean up.
     */
    function closeModal() {
        const { backdrop, panel } = ensureContainer();
        if (cleanupFn) cleanupFn();
        cleanupFn = null;
        backdrop.style.display = 'none';
        document.body.classList.remove('modal-open');
        // Clear content
        while (panel.firstChild) panel.removeChild(panel.firstChild);
    }

    /**
     * Check if modal is open.
     */
    function isOpen() {
        return backdrop && backdrop.style.display !== 'none';
    }

    // Expose public API
    AdminModal.openModalByUrl = openModalByUrl;
    AdminModal.openModal = openModalWithHtml;
    AdminModal.closeModal = closeModal;
    AdminModal.isOpen = isOpen;

    // ImageStudio helper (uses this modal system)
    if (!window.ImageStudio) {
        window.ImageStudio = {
            open: function(opts = {}) {
                const ownerType = opts.ownerType || opts.owner_type || '';
                const ownerId = opts.ownerId || opts.owner_id || 0;
                const url = `/admin/fragments/images.php?owner_type=${encodeURIComponent(ownerType)}&owner_id=${encodeURIComponent(ownerId)}`;
                return new Promise((resolve, reject) => {
                    openModalByUrl(url, {
                        onOpen: function(panel) {
                            const onSelect = (ev) => {
                                if (ev && ev.detail && ev.detail.url) resolve(ev.detail.url);
                                else resolve(null);
                                window.removeEventListener('ImageStudio:selected', onSelect);
                                closeModal();
                            };
                            const onClose = () => {
                                resolve(null);
                                window.removeEventListener('ImageStudio:close', onClose);
                            };
                            window.addEventListener('ImageStudio:selected', onSelect);
                            window.addEventListener('ImageStudio:close', onClose);
                        }
                    }).catch(reject);
                });
            }
        };
    }

    // Color picker modal (if ColorSlider exists)
    AdminModal.showColorPicker = function(options = {}) {
        const title = options.title || 'Select Color';
        const onSelect = options.onSelect || function() {};
        const html = `
            <div class="modal-body">
                <h3 class="modal-title">${title}</h3>
                <div id="modalColorSlider" data-color-slider style="margin: 1rem 0;"></div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" onclick="AdminModal.closeModal()">Cancel</button>
                    <button class="btn btn-primary" id="confirmColorBtn">Select</button>
                </div>
            </div>
        `;
        return openModalWithHtml(html).then(() => {
            const container = document.getElementById('modalColorSlider');
            if (container && window.ColorSlider) {
                window.ColorSlider.render(container, {
                    onSelect: (color) => {
                        const confirmBtn = document.getElementById('confirmColorBtn');
                        if (confirmBtn) {
                            confirmBtn.onclick = () => {
                                onSelect(color);
                                closeModal();
                            };
                        }
                    }
                });
            }
        });
    };

    // Clean up on page unload (optional)
    window.addEventListener('beforeunload', () => {
        if (cleanupFn) cleanupFn();
    });

    // Ensure container is ready (but don't show it)
    ensureContainer();
})();