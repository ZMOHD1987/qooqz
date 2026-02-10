/**
 * Admin UI Framework - Production Version
 * Version: 1.0.1
 * Fixed and optimized for production use
 */
(function() {
    'use strict';

    // ════════════════════════════════════════════════════════════
    // FRAMEWORK NAMESPACE
    // ════════════════════════════════════════════════════════════
    window.AdminFramework = {
        version: '1.0.1',
        config: {
            apiBase: '/api',
            perPage: 10,
            debounceDelay: 300,
            notificationDuration: 3000
        }
    };

    const AF = window.AdminFramework;

    // ════════════════════════════════════════════════════════════
    // UTILITIES
    // ════════════════════════════════════════════════════════════
    
    AF.$ = function(id) {
        return document.getElementById(id);
    };

    AF.$$ = function(selector, parent = document) {
        return parent.querySelectorAll(selector);
    };

    AF.escapeHtml = function(text) {
        if (text === null || text === undefined) return '';
        const div = document.createElement('div');
        div.textContent = String(text);
        return div.innerHTML;
    };

    AF.formatDate = function(dateString, options = {}) {
        if (!dateString) return 'N/A';
        try {
            const defaults = {
                year: 'numeric',
                month: 'short',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            };
            return new Date(dateString).toLocaleDateString('en-US', { ...defaults, ...options });
        } catch (e) {
            return dateString;
        }
    };

    AF.debounce = function(func, wait) {
        let timeout;
        return function(...args) {
            clearTimeout(timeout);
            timeout = setTimeout(() => func.apply(this, args), wait);
        };
    };

    AF.getCSRF = function() {
        return window.CSRF_TOKEN || 
               document.querySelector('meta[name="csrf-token"]')?.content || 
               '';
    };

    // ════════════════════════════════════════════════════════════
    // NOTIFICATIONS
    // ════════════════════════════════════════════════════════════
    
    AF.notify = function(message, type = 'info') {
        console.log(`[${type.toUpperCase()}]`, message);

        const notification = document.createElement('div');
        notification.className = `af-notification af-notification-${type}`;
        notification.innerHTML = `
            <div class="af-notification-content">
                <span class="af-notification-icon">
                    ${type === 'success' ? '✓' : type === 'error' ? '✗' : type === 'warning' ? '⚠' : 'ℹ'}
                </span>
                <span class="af-notification-message">${AF.escapeHtml(message)}</span>
                <button class="af-notification-close" onclick="this.parentElement.parentElement.remove()">&times;</button>
            </div>
        `;

        document.body.appendChild(notification);

        setTimeout(() => {
            notification.classList.add('af-notification-show');
        }, 10);

        setTimeout(() => {
            notification.classList.remove('af-notification-show');
            setTimeout(() => notification.remove(), 300);
        }, AF.config.notificationDuration);
    };

    AF.success = (msg) => AF.notify(msg, 'success');
    AF.error = (msg) => AF.notify(msg, 'error');
    AF.warning = (msg) => AF.notify(msg, 'warning');
    AF.info = (msg) => AF.notify(msg, 'info');

    // ════════════════════════════════════════════════════════════
    // API CALLS
    // ════════════════════════════════════════════════════════════
    
    AF.api = async function(url, options = {}) {
        try {
            const defaultOptions = {
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': AF.getCSRF(),
                    'X-Requested-With': 'XMLHttpRequest'
                }
            };

            const response = await fetch(url, { ...defaultOptions, ...options });

            if (!response.ok) {
                if (response.status === 401) {
                    AF.error('Session expired. Please login again.');
                    setTimeout(() => window.location.reload(), 2000);
                    throw new Error('Unauthorized');
                }
                
                const errorData = await response.json().catch(() => ({}));
                throw new Error(errorData.message || `HTTP ${response.status}: ${response.statusText}`);
            }

            const data = await response.json();

            if (data.success === false) {
                throw new Error(data.message || 'API request failed');
            }

            return data;
        } catch (error) {
            console.error('[API Error]', url, error);
            throw error;
        }
    };

    AF.get = (url) => AF.api(url, { method: 'GET' });
    AF.post = (url, data) => AF.api(url, { method: 'POST', body: JSON.stringify(data) });
    AF.put = (url, data) => AF.api(url, { method: 'PUT', body: JSON.stringify(data) });
    AF.delete = (url, data) => AF.api(url, { method: 'DELETE', body: JSON.stringify(data) });

    // ════════════════════════════════════════════════════════════
    // FORM HELPERS
    // ════════════════════════════════════════════════════════════
    
    AF.Form = {
        show: function(containerId, title = '') {
            const container = AF.$(containerId);
            if (!container) return;

            container.style.display = 'block';
            
            const titleElement = container.querySelector('.card-title, #formTitle, [data-form-title]');
            if (titleElement && title) {
                titleElement.textContent = title;
            }

            setTimeout(() => {
                container.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }, 100);
        },

        hide: function(containerId) {
            const container = AF.$(containerId);
            if (!container) return;

            container.style.display = 'none';
            
            const form = container.querySelector('form');
            if (form) {
                form.reset();
                form.classList.remove('was-validated');
            }
        },

        fill: function(formId, data) {
            const form = AF.$(formId);
            if (!form) return;

            Object.keys(data).forEach(key => {
                const field = form.querySelector(`[name="${key}"]`);
                if (field) {
                    if (field.type === 'checkbox') {
                        field.checked = !!data[key];
                    } else if (field.type === 'radio') {
                        const radio = form.querySelector(`[name="${key}"][value="${data[key]}"]`);
                        if (radio) radio.checked = true;
                    } else {
                        field.value = data[key] || '';
                    }
                }
            });
        },

        getData: function(formId) {
            const form = AF.$(formId);
            if (!form) return {};

            const formData = new FormData(form);
            const data = {};

            for (const [key, value] of formData.entries()) {
                data[key] = value;
            }

            return data;
        },

        validate: function(formId) {
            const form = AF.$(formId);
            if (!form) return false;

            if (!form.checkValidity()) {
                form.classList.add('was-validated');
                return false;
            }

            return true;
        }
    };

    // ════════════════════════════════════════════════════════════
    // TABLE HELPERS - FIXED
    // ════════════════════════════════════════════════════════════
    
    AF.Table = {
        showLoading: function(config) {
            const { loading, container, empty, error } = config;
            
            if (loading) loading.style.display = 'flex';
            if (container) container.style.display = 'none';
            if (empty) empty.style.display = 'none';
            if (error) error.style.display = 'none';
        },

        showTable: function(config) {
            const { loading, container, empty, error } = config;
            
            if (loading) loading.style.display = 'none';
            if (container) container.style.display = 'block';
            if (empty) empty.style.display = 'none';
            if (error) error.style.display = 'none';
        },

        showEmpty: function(config) {
            const { loading, container, empty, error } = config;
            
            if (loading) loading.style.display = 'none';
            if (container) container.style.display = 'none';
            if (empty) empty.style.display = 'flex';
            if (error) error.style.display = 'none';
        },

        showError: function(config, message = 'An error occurred') {
            const { loading, container, empty, error, errorMessage } = config;
            
            if (loading) loading.style.display = 'none';
            if (container) container.style.display = 'none';
            if (empty) empty.style.display = 'none';
            if (error) error.style.display = 'flex';
            if (errorMessage) errorMessage.textContent = message;
        },

        // ✅ FIXED: Handle both array and string
        render: function(tbody, rows) {
            if (!tbody) {
                console.error('[AF.Table.render] tbody element not found');
                return;
            }

            let html = '';
            
            if (Array.isArray(rows)) {
                html = rows.join('');
            } else if (typeof rows === 'string') {
                html = rows;
            } else {
                console.error('[AF.Table.render] rows must be array or string, got:', typeof rows);
                return;
            }

            tbody.innerHTML = html;
        },

        renderPagination: function(paginationEl, infoEl, meta) {
            if (!paginationEl || !meta) return;

            const { page = 1, total = 0, per_page = 10, last_page } = meta;
            const totalPages = last_page || Math.ceil(total / per_page);
            
            // Update info
            if (infoEl) {
                const start = total > 0 ? ((page - 1) * per_page) + 1 : 0;
                const end = Math.min(page * per_page, total);
                infoEl.textContent = `Showing ${start} to ${end} of ${total} results`;
            }

            // Build pagination
            let html = `<button class="pagination-btn" ${page === 1 ? 'disabled' : ''} data-page="${page - 1}">
                <i class="fas fa-chevron-left"></i> Previous
            </button>`;

            // Page numbers
            const maxVisible = 7;
            let startPage = Math.max(1, page - Math.floor(maxVisible / 2));
            let endPage = Math.min(totalPages, startPage + maxVisible - 1);

            if (endPage - startPage < maxVisible - 1) {
                startPage = Math.max(1, endPage - maxVisible + 1);
            }

            if (startPage > 1) {
                html += `<button class="pagination-btn" data-page="1">1</button>`;
                if (startPage > 2) {
                    html += '<span class="pagination-dots">...</span>';
                }
            }

            for (let i = startPage; i <= endPage; i++) {
                html += `<button class="pagination-btn ${i === page ? 'active' : ''}" 
                         data-page="${i}" ${i === page ? 'disabled' : ''}>${i}</button>`;
            }

            if (endPage < totalPages) {
                if (endPage < totalPages - 1) {
                    html += '<span class="pagination-dots">...</span>';
                }
                html += `<button class="pagination-btn" data-page="${totalPages}">${totalPages}</button>`;
            }

            html += `<button class="pagination-btn" ${page === totalPages ? 'disabled' : ''} data-page="${page + 1}">
                Next <i class="fas fa-chevron-right"></i>
            </button>`;

            paginationEl.innerHTML = html;
        }
    };

    // ════════════════════════════════════════════════════════════
    // MODAL HELPERS
    // ════════════════════════════════════════════════════════════
    
    AF.Modal = {
        confirm: function(message, onConfirm, onCancel = null) {
            if (confirm(message)) {
                if (typeof onConfirm === 'function') onConfirm();
            } else {
                if (typeof onCancel === 'function') onCancel();
            }
        },

        alert: function(message, type = 'info') {
            AF.notify(message, type);
        }
    };

    // ════════════════════════════════════════════════════════════
    // LOADING STATE
    // ════════════════════════════════════════════════════════════
    
    AF.Loading = {
        show: function(buttonEl, text = 'Loading...') {
            if (!buttonEl) return;
            buttonEl.disabled = true;
            buttonEl.dataset.originalText = buttonEl.innerHTML;
            buttonEl.innerHTML = `<i class="fas fa-spinner fa-spin"></i> ${text}`;
        },

        hide: function(buttonEl) {
            if (!buttonEl) return;
            buttonEl.disabled = false;
            if (buttonEl.dataset.originalText) {
                buttonEl.innerHTML = buttonEl.dataset.originalText;
                delete buttonEl.dataset.originalText;
            }
        }
    };

    // ════════════════════════════════════════════════════════════
    // BADGE HELPER
    // ════════════════════════════════════════════════════════════
    
    AF.badge = function(text, type = 'info') {
        const typeMap = {
            success: 'badge-success',
            error: 'badge-danger',
            danger: 'badge-danger',
            warning: 'badge-warning',
            info: 'badge-info',
            primary: 'badge-primary',
            secondary: 'badge-secondary'
        };
        
        const className = typeMap[type] || 'badge-info';
        return `<span class="badge ${className}">${AF.escapeHtml(text)}</span>`;
    };

    // ════════════════════════════════════════════════════════════
    // ACTIONS BUTTONS
    // ════════════════════════════════════════════════════════════
    
    AF.actionButton = function(icon, onclick, title = '', variant = 'primary') {
        const className = variant ? `btn btn-sm btn-${variant}` : 'btn btn-sm';
        return `<button onclick="${onclick}" class="${className}" title="${AF.escapeHtml(title)}">
            <i class="fas fa-${icon}"></i>
        </button>`;
    };

    // ════════════════════════════════════════════════════════════
    // CACHE SYSTEM
    // ════════════════════════════════════════════════════════════
    
    AF.Cache = {
        data: new Map(),

        set: function(key, value, ttl = 300000) {
            this.data.set(key, {
                value: value,
                expires: Date.now() + ttl
            });
        },

        get: function(key) {
            const item = this.data.get(key);
            if (!item) return null;
            
            if (Date.now() > item.expires) {
                this.data.delete(key);
                return null;
            }
            
            return item.value;
        },

        clear: function(key) {
            if (key) {
                this.data.delete(key);
            } else {
                this.data.clear();
            }
        }
    };

    // ════════════════════════════════════════════════════════════
    // INITIALIZATION
    // ════════════════════════════════════════════════════════════
    
    console.log('%c════════════════════════════════════', 'color:#3b82f6');
    console.log('%c✓ Admin Framework Loaded', 'color:#10b981;font-weight:bold');
    console.log('%cVersion:', 'color:#94a3b8', AF.version);
    console.log('%c════════════════════════════════════', 'color:#3b82f6');

})();