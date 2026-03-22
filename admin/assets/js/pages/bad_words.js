/**
 * /admin/assets/js/pages/bad_words.js
 * Bad Words Management — Production v2.0
 *
 * ─ التغييرات عن النسخة السابقة ─────────────────────────────
 * • badge class: badge-low/medium/high → badge-severity-low/medium/high
 * • btn-info → btn-primary (لا يعتمد على DB button_styles)
 * • Translations modal: زر واحد (btnSaveTranslation) يتغير نصه
 *   بدلاً من إخفاء/إظهار زرين منفصلين
 * • AF.Table helpers بدل innerHTML المتكررة لتقليل XSS surface
 * • States (loading/empty/error/table) تُدار عبر showState()
 * • ESC يُغلق أي modal مفتوح
 * ─────────────────────────────────────────────────────────────
 */
(function () {
    'use strict';

    // ════════════════════════════════════════════════════════
    // CONFIG
    // ════════════════════════════════════════════════════════
    let CFG, CSRF, STRINGS, CAN_CREATE, CAN_EDIT, CAN_DELETE;
    const FALLBACK_LANGS = ['ar', 'en', 'fr', 'tr', 'ur', 'de', 'es'];
    const PER_PAGE = 25;

    let currentPage    = 1;
    let currentFilters = {};

    // Translation state
    let currentTransBadWordId   = null;
    let currentEditingTransId   = null;   // null = add mode, string = edit mode

    // ════════════════════════════════════════════════════════
    // BOOT
    // ════════════════════════════════════════════════════════
    function reloadConfig() {
        CFG        = window.BAD_WORDS_CONFIG || {};
        CSRF       = CFG.csrfToken || '';
        STRINGS    = CFG.strings   || {};
        CAN_CREATE = !!CFG.canCreate;
        CAN_EDIT   = !!CFG.canEdit;
        CAN_DELETE = !!CFG.canDelete;
    }

    // ════════════════════════════════════════════════════════
    // HELPERS
    // ════════════════════════════════════════════════════════

    /** Dot-notation translation */
    function t(key, fallback) {
        const parts = key.split('.');
        let val = STRINGS;
        for (let i = 0; i < parts.length; i++) {
            if (val && typeof val === 'object' && parts[i] in val) {
                val = val[parts[i]];
            } else {
                return fallback || key;
            }
        }
        return typeof val === 'string' ? val : (fallback || key);
    }

    /** Safe HTML escape */
    function esc(str) {
        if (str == null) return '';
        const d = document.createElement('div');
        d.appendChild(document.createTextNode(String(str)));
        return d.innerHTML;
    }

    /** Severity → CSS class (badge-severity-*) */
    function severityClass(level) {
        const map = { low: 'badge-severity-low', medium: 'badge-severity-medium', high: 'badge-severity-high' };
        return map[String(level)] || 'badge-secondary';
    }

    /** Format date string */
    function formatDate(str) {
        if (!str) return '—';
        try {
            return new Date(str).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
        } catch (_) {
            return str;
        }
    }

    // ════════════════════════════════════════════════════════
    // TOAST NOTIFICATIONS
    // ════════════════════════════════════════════════════════
    function notify(message, type = 'info') {
        let container = document.getElementById('bwNotifications');
        if (!container) {
            container = document.createElement('div');
            container.id = 'bwNotifications';
            container.className = 'bw-notifications';
            const page = document.getElementById('badWordsPageContainer');
            (page || document.body).insertBefore(container, (page || document.body).firstChild);
        }

        const toast = document.createElement('div');
        toast.className = `bw-toast bw-toast-${type}`;
        toast.setAttribute('role', 'alert');
        toast.setAttribute('aria-live', 'assertive');

        const msg = document.createElement('span');
        msg.textContent = message;
        toast.appendChild(msg);

        const close = document.createElement('button');
        close.className = 'bw-toast-close';
        close.setAttribute('aria-label', 'Close');
        close.textContent = '\u00d7';
        close.addEventListener('click', () => toast.remove());
        toast.appendChild(close);

        container.appendChild(toast);
        setTimeout(() => toast && toast.remove && toast.remove(), 4500);
    }

    // ════════════════════════════════════════════════════════
    // MODAL SYSTEM
    // ════════════════════════════════════════════════════════
    function openModal(id) {
        const el = document.getElementById(id);
        if (el) {
            el.style.display = 'flex';
            // Focus first interactive element
            const first = el.querySelector('input:not([type="hidden"]), select, textarea, button');
            if (first) setTimeout(() => first.focus(), 50);
        }
    }

    function closeModal(id) {
        const el = document.getElementById(id);
        if (el) el.style.display = 'none';
    }

    // Close on ESC
    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') return;
        ['badWordModal', 'translationsModal', 'textCheckModal'].forEach(id => {
            const el = document.getElementById(id);
            if (el && el.style.display !== 'none') closeModal(id);
        });
    });

    // ════════════════════════════════════════════════════════
    // TABLE STATE MANAGEMENT
    // ════════════════════════════════════════════════════════
    function showState(state, errorMsg) {
        const loading   = document.getElementById('bwLoading');
        const empty     = document.getElementById('bwEmpty');
        const error     = document.getElementById('bwError');
        const container = document.getElementById('bwTableContainer');

        [loading, empty, error, container].forEach(el => {
            if (el) el.style.display = 'none';
        });

        switch (state) {
            case 'loading':
                if (loading) loading.style.display = 'flex';
                break;
            case 'empty':
                if (empty) empty.style.display = 'flex';
                break;
            case 'error':
                if (error) error.style.display = 'flex';
                if (errorMsg) {
                    const p = document.getElementById('bwErrorMessage');
                    if (p) p.textContent = errorMsg;
                }
                break;
            case 'table':
            default:
                if (container) container.style.display = 'block';
                break;
        }
    }

    // ════════════════════════════════════════════════════════
    // LOAD BAD WORDS
    // ════════════════════════════════════════════════════════
    function loadBadWords(params = {}) {
        currentPage    = params.page || 1;
        currentFilters = params;

        const q = [];
        if (params.search)     q.push('search='    + encodeURIComponent(params.search));
        if (params.severity)   q.push('severity='  + encodeURIComponent(params.severity));
        if (params.is_active !== undefined && params.is_active !== '')
            q.push('is_active=' + encodeURIComponent(params.is_active));
        q.push('limit='  + PER_PAGE);
        q.push('offset=' + ((currentPage - 1) * PER_PAGE));

        const url = '/api/bad_words' + (q.length ? '?' + q.join('&') : '');

        showState('loading');

        fetch(url, { credentials: 'same-origin' })
            .then(r => { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
            .then(d => {
                const tbody = document.getElementById('badWordsBody');
                if (!tbody) return;
                tbody.innerHTML = '';

                const items = d.data?.items || [];
                const total = d.data?.meta?.total ?? items.length;

                if (!d.success || items.length === 0) {
                    showState('empty');
                    renderPagination(1, 0);
                    return;
                }

                showState('table');

                items.forEach(item => {
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td>${esc(item.id)}</td>
                        <td><strong>${esc(item.word)}</strong></td>
                        <td>
                            <span class="badge ${severityClass(item.severity)}">
                                ${esc(t('severity.' + item.severity, item.severity))}
                            </span>
                        </td>
                        <td>${item.is_regex
                            ? '<span class="badge badge-info">' + t('table.yes', 'Yes') + '</span>'
                            : '<span class="badge badge-secondary">' + t('table.no', 'No') + '</span>'
                        }</td>
                        <td>${item.is_active
                            ? '<span class="badge badge-active">'   + t('table.yes', 'Yes') + '</span>'
                            : '<span class="badge badge-inactive">' + t('table.no',  'No')  + '</span>'
                        }</td>
                        <td>${esc(formatDate(item.created_at))}</td>
                        <td>
                            <div class="table-actions">
                                ${CAN_EDIT
                                    ? `<button class="btn btn-sm btn-primary edit-btn"
                                               data-id="${esc(item.id)}"
                                               aria-label="${t('table.edit', 'Edit')}">
                                           <i class="fas fa-edit" aria-hidden="true"></i>
                                       </button>`
                                    : ''}
                                <button class="btn btn-sm btn-secondary translations-btn"
                                        data-id="${esc(item.id)}"
                                        aria-label="${t('table.translations', 'Translations')}">
                                    <i class="fas fa-language" aria-hidden="true"></i>
                                </button>
                                ${CAN_DELETE
                                    ? `<button class="btn btn-sm btn-danger delete-btn"
                                               data-id="${esc(item.id)}"
                                               aria-label="${t('table.delete', 'Delete')}">
                                           <i class="fas fa-trash" aria-hidden="true"></i>
                                       </button>`
                                    : ''}
                            </div>
                        </td>
                    `;
                    tbody.appendChild(tr);
                });

                renderPagination(currentPage, total);
            })
            .catch(err => {
                console.error('[BadWords] loadBadWords error:', err);
                showState('error', err.message || t('load_failed', 'Failed to load data'));
            });
    }

    // ════════════════════════════════════════════════════════
    // PAGINATION
    // ════════════════════════════════════════════════════════
    function renderPagination(page, total) {
        const totalPages = Math.max(1, Math.ceil(total / PER_PAGE));
        const start = total > 0 ? (page - 1) * PER_PAGE + 1 : 0;
        const end   = Math.min(page * PER_PAGE, total);

        const infoEl = document.getElementById('paginationInfo');
        if (infoEl) {
            infoEl.textContent = total > 0
                ? `${start}–${end} ${t('pagination.of', 'of')} ${total}`
                : t('pagination.no_results', 'No results');
        }

        const pagEl = document.getElementById('pagination');
        if (!pagEl) return;
        pagEl.innerHTML = '';
        if (totalPages <= 1) return;

        const makeBtn = (label, targetPage, isActive = false, disabled = false) => {
            const btn = document.createElement('button');
            btn.className = 'pagination-btn' + (isActive ? ' active' : '');
            btn.innerHTML = label;
            btn.disabled  = disabled;
            if (!disabled) btn.addEventListener('click', () => goToPage(targetPage));
            return btn;
        };

        pagEl.appendChild(makeBtn('&laquo;', page - 1, false, page <= 1));

        for (let i = 1; i <= totalPages; i++) {
            if (i === 1 || i === totalPages || (i >= page - 2 && i <= page + 2)) {
                pagEl.appendChild(makeBtn(String(i), i, i === page, i === page));
            } else if (i === page - 3 || i === page + 3) {
                const dots = document.createElement('span');
                dots.className = 'pagination-dots';
                dots.textContent = '…';
                pagEl.appendChild(dots);
            }
        }

        pagEl.appendChild(makeBtn('&raquo;', page + 1, false, page >= totalPages));
    }

    function goToPage(page) {
        const params = { ...currentFilters, page };
        delete params.page;
        loadBadWords({ ...params, page });
    }

    // ════════════════════════════════════════════════════════
    // ADD / EDIT WORD
    // ════════════════════════════════════════════════════════
    function openAddModal() {
        const form  = document.getElementById('badWordForm');
        const title = document.getElementById('badWordModalTitle');
        if (form)  form.reset();
        document.getElementById('badWordId').value = '';
        if (title) title.textContent = t('modal.add_title', 'Add Bad Word');
        openModal('badWordModal');
    }

    function openEditModal(id) {
        fetch('/api/bad_words?id=' + encodeURIComponent(id), { credentials: 'same-origin' })
            .then(r => r.json())
            .then(d => {
                if (!d.success || !d.data) {
                    notify(d.message || t('load_failed', 'Failed to load record'), 'error');
                    return;
                }
                const rec = d.data;
                document.getElementById('badWordId').value    = rec.id;
                document.getElementById('bwWord').value       = rec.word       || '';
                document.getElementById('bwSeverity').value   = rec.severity   || 'medium';
                document.getElementById('bwIsRegex').checked  = !!rec.is_regex;
                document.getElementById('bwIsActive').checked = !!rec.is_active;

                const title = document.getElementById('badWordModalTitle');
                if (title) title.textContent = t('modal.edit_title', 'Edit Bad Word');

                openModal('badWordModal');
            })
            .catch(err => {
                console.error('[BadWords] openEditModal error:', err);
                notify(t('network_error', 'Network error'), 'error');
            });
    }

    function saveBadWord(formData) {
        const editId = document.getElementById('badWordId').value;
        const body = {
            word:      formData.get('word'),
            severity:  formData.get('severity'),
            is_regex:  formData.get('is_regex')  ? 1 : 0,
            is_active: formData.get('is_active') ? 1 : 0,
        };
        if (editId) body.id = editId;

        fetch('/api/bad_words', {
            method:  editId ? 'PUT' : 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
            credentials: 'same-origin',
            body: JSON.stringify(body),
        })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                closeModal('badWordModal');
                document.getElementById('badWordForm')?.reset();
                document.getElementById('badWordId').value = '';
                notify(t('saved', 'Saved successfully'), 'success');
                loadBadWords({ ...currentFilters, page: currentPage });
            } else {
                notify(d.message || t('unknown_error', 'Unknown error'), 'error');
            }
        })
        .catch(err => {
            console.error('[BadWords] saveBadWord error:', err);
            notify(t('network_error', 'Network error'), 'error');
        });
    }

    function deleteBadWord(id) {
        if (!confirm(t('confirm_delete', 'Are you sure you want to delete this word?'))) return;

        fetch('/api/bad_words?id=' + encodeURIComponent(id), {
            method:  'DELETE',
            headers: { 'X-CSRF-TOKEN': CSRF },
            credentials: 'same-origin',
        })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                notify(t('deleted', 'Deleted successfully'), 'success');
                loadBadWords({ ...currentFilters, page: currentPage });
            } else {
                notify(d.message || t('delete_failed', 'Delete failed'), 'error');
            }
        })
        .catch(err => {
            console.error('[BadWords] deleteBadWord error:', err);
            notify(t('network_error', 'Network error'), 'error');
        });
    }

    // ════════════════════════════════════════════════════════
    // TRANSLATIONS
    // ════════════════════════════════════════════════════════

    /** Resets the translations form to "add" mode */
    function resetTransForm() {
        currentEditingTransId = null;
        document.getElementById('transLangCode').value = '';
        document.getElementById('transWord').value     = '';

        const label = document.getElementById('btnSaveTranslationLabel');
        if (label) label.textContent = t('translations.add', 'Add Translation');
    }

    function openTranslationsModal(badWordId) {
        currentTransBadWordId = badWordId;
        resetTransForm();

        fetch('/api/bad_words/translations?bad_word_id=' + encodeURIComponent(badWordId), {
            credentials: 'same-origin',
        })
        .then(r => r.json())
        .then(d => {
            const tbody = document.getElementById('translationsBody');
            if (!tbody) return;
            tbody.innerHTML = '';

            const items = d.data?.items || (Array.isArray(d.data) ? d.data : []);

            if (items.length === 0) {
                const tr  = document.createElement('tr');
                const td  = document.createElement('td');
                td.setAttribute('colspan', '3');
                td.style.textAlign = 'center';
                td.textContent = t('no_translations', 'No translations found');
                tr.appendChild(td);
                tbody.appendChild(tr);
            } else {
                items.forEach(item => {
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td>${esc(item.language_code)}</td>
                        <td>${esc(item.word)}</td>
                        <td>
                            <div class="table-actions">
                                <button class="btn btn-sm btn-primary edit-translation-btn"
                                        data-id="${esc(item.id)}"
                                        data-lang="${esc(item.language_code)}"
                                        data-word="${esc(item.word)}">
                                    <i class="fas fa-edit" aria-hidden="true"></i>
                                </button>
                                <button class="btn btn-sm btn-danger delete-translation-btn"
                                        data-id="${esc(item.id)}">
                                    <i class="fas fa-trash" aria-hidden="true"></i>
                                </button>
                            </div>
                        </td>
                    `;
                    tbody.appendChild(tr);
                });
            }

            openModal('translationsModal');
        })
        .catch(err => {
            console.error('[BadWords] openTranslationsModal error:', err);
            notify(t('load_failed', 'Failed to load translations'), 'error');
        });
    }

    function saveTranslation() {
        const langCode = document.getElementById('transLangCode').value;
        const word     = document.getElementById('transWord').value.trim();

        if (!word) {
            notify(t('enter_word', 'Please enter a word'), 'warning');
            return;
        }

        const isEdit = !!currentEditingTransId;
        const body   = {
            bad_word_id:   currentTransBadWordId,
            language_code: langCode,
            word,
        };
        if (isEdit) body.id = currentEditingTransId;

        fetch('/api/bad_words/translations', {
            method:  isEdit ? 'PUT' : 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
            credentials: 'same-origin',
            body: JSON.stringify(body),
        })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                notify(t('saved', 'Saved successfully'), 'success');
                resetTransForm();
                openTranslationsModal(currentTransBadWordId);
            } else {
                notify(d.message || t('unknown_error', 'Unknown error'), 'error');
            }
        })
        .catch(err => {
            console.error('[BadWords] saveTranslation error:', err);
            notify(t('network_error', 'Network error'), 'error');
        });
    }

    function deleteTranslation(id) {
        if (!confirm(t('confirm_delete_translation', 'Delete this translation?'))) return;

        fetch('/api/bad_words/translations?id=' + encodeURIComponent(id), {
            method:  'DELETE',
            headers: { 'X-CSRF-TOKEN': CSRF },
            credentials: 'same-origin',
        })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                notify(t('deleted', 'Deleted successfully'), 'success');
                openTranslationsModal(currentTransBadWordId);
            } else {
                notify(d.message || t('delete_failed', 'Delete failed'), 'error');
            }
        })
        .catch(err => {
            console.error('[BadWords] deleteTranslation error:', err);
            notify(t('network_error', 'Network error'), 'error');
        });
    }

    // ════════════════════════════════════════════════════════
    // TEXT CHECK
    // ════════════════════════════════════════════════════════
    function checkText() {
        const input = document.getElementById('textCheckInput');
        const text  = input ? input.value.trim() : '';

        if (!text) {
            notify(t('enter_text', 'Please enter text to check'), 'warning');
            return;
        }

        const resultsDiv = document.getElementById('textCheckResults');
        if (resultsDiv) {
            resultsDiv.style.display = 'none';
            resultsDiv.innerHTML = '';
        }

        fetch('/api/bad_words/check', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
            credentials: 'same-origin',
            body: JSON.stringify({ text }),
        })
        .then(r => r.json())
        .then(d => {
            if (!resultsDiv) return;
            resultsDiv.style.display = 'block';

            if (d.success && d.data?.found?.length > 0) {
                resultsDiv.innerHTML = '<p class="check-found">' + t('text_check.found_words', 'Found bad words:') + '</p>';
                const ul = document.createElement('ul');
                d.data.found.forEach(match => {
                    const li = document.createElement('li');
                    li.textContent = match.word + ' ('
                        + t('table.severity', 'Severity') + ': '
                        + t('severity.' + match.severity, match.severity) + ')';
                    ul.appendChild(li);
                });
                resultsDiv.appendChild(ul);
            } else if (d.success) {
                resultsDiv.innerHTML = '<p class="check-clean">' + t('no_bad_words_found', 'No bad words found.') + '</p>';
            } else {
                resultsDiv.textContent = d.message || t('check_failed', 'Check failed');
            }
        })
        .catch(err => {
            console.error('[BadWords] checkText error:', err);
            notify(t('network_error', 'Network error'), 'error');
        });
    }

    // ════════════════════════════════════════════════════════
    // FILTERS
    // ════════════════════════════════════════════════════════
    function applyFilters() {
        const search   = document.getElementById('filterSearch')?.value   || '';
        const severity = document.getElementById('filterSeverity')?.value || '';
        let   status   = document.getElementById('filterStatus')?.value   || '';

        if (status === 'active')   status = '1';
        if (status === 'inactive') status = '0';

        loadBadWords({ search, severity, is_active: status, page: 1 });
    }

    function clearFilters() {
        const search   = document.getElementById('filterSearch');
        const severity = document.getElementById('filterSeverity');
        const status   = document.getElementById('filterStatus');
        if (search)   search.value   = '';
        if (severity) severity.value = '';
        if (status)   status.value   = '';
        loadBadWords({ page: 1 });
    }

    // ════════════════════════════════════════════════════════
    // LOAD LANGUAGES (for translations dropdown)
    // ════════════════════════════════════════════════════════
    function loadLanguages() {
        const select = document.getElementById('transLangCode');
        if (!select) return;

        fetch('/api/languages', { credentials: 'same-origin' })
            .then(r => r.json())
            .then(d => {
                select.innerHTML = '';
                const items = Array.isArray(d.data) ? d.data : (d.data?.items || []);

                if (items.length > 0) {
                    items.forEach(lang => {
                        const opt = document.createElement('option');
                        opt.value       = lang.code || lang.language_code || lang.id;
                        opt.textContent = lang.native_name || lang.name || lang.code || lang.id;
                        select.appendChild(opt);
                    });
                } else {
                    FALLBACK_LANGS.forEach(code => {
                        const opt = document.createElement('option');
                        opt.value = opt.textContent = code;
                        select.appendChild(opt);
                    });
                }
            })
            .catch(() => {
                const select2 = document.getElementById('transLangCode');
                if (!select2) return;
                FALLBACK_LANGS.forEach(code => {
                    const opt = document.createElement('option');
                    opt.value = opt.textContent = code;
                    select2.appendChild(opt);
                });
            });
    }

    // ════════════════════════════════════════════════════════
    // INIT
    // ════════════════════════════════════════════════════════
    function init() {
        reloadConfig();

        // Close modal buttons
        document.querySelectorAll('.btn-close-modal').forEach(btn => {
            btn.addEventListener('click', () => closeModal(btn.dataset.modal));
        });

        // Add word
        document.getElementById('btnAddWord')?.addEventListener('click', openAddModal);
        document.getElementById('btnAddWordEmpty')?.addEventListener('click', openAddModal);

        // Form submit
        document.getElementById('badWordForm')?.addEventListener('submit', e => {
            e.preventDefault();
            saveBadWord(new FormData(e.currentTarget));
        });

        // Filters
        document.getElementById('btnFilter')?.addEventListener('click', applyFilters);
        document.getElementById('btnClearFilters')?.addEventListener('click', clearFilters);
        document.getElementById('filterSearch')?.addEventListener('keydown', e => {
            if (e.key === 'Enter') { e.preventDefault(); applyFilters(); }
        });

        // Retry
        document.getElementById('btnRetry')?.addEventListener('click', () =>
            loadBadWords({ ...currentFilters, page: currentPage })
        );

        // Text check
        document.getElementById('btnOpenCheckText')?.addEventListener('click', () => {
            document.getElementById('textCheckResults').style.display = 'none';
            openModal('textCheckModal');
        });
        document.getElementById('btnCheckText')?.addEventListener('click', checkText);

        // Translations — single save button
        document.getElementById('btnSaveTranslation')?.addEventListener('click', saveTranslation);

        // Event delegation — dynamic table buttons
        document.addEventListener('click', e => {
            // Main table
            const editBtn = e.target.closest('.edit-btn');
            if (editBtn) { openEditModal(editBtn.dataset.id); return; }

            const deleteBtn = e.target.closest('.delete-btn');
            if (deleteBtn) { deleteBadWord(deleteBtn.dataset.id); return; }

            const transBtn = e.target.closest('.translations-btn');
            if (transBtn) { openTranslationsModal(transBtn.dataset.id); return; }

            // Translations sub-table
            const editTransBtn = e.target.closest('.edit-translation-btn');
            if (editTransBtn) {
                // Enter edit mode — update form and label, no extra API call
                currentEditingTransId = editTransBtn.dataset.id;
                document.getElementById('transLangCode').value = editTransBtn.dataset.lang;
                document.getElementById('transWord').value     = editTransBtn.dataset.word;

                const label = document.getElementById('btnSaveTranslationLabel');
                if (label) label.textContent = t('translations.update', 'Update Translation');

                document.getElementById('transWord')?.focus();
                return;
            }

            const delTransBtn = e.target.closest('.delete-translation-btn');
            if (delTransBtn) { deleteTranslation(delTransBtn.dataset.id); return; }
        });

        loadLanguages();
        loadBadWords();
    }

    // ════════════════════════════════════════════════════════
    // REGISTER  — supports fragment navigation & direct load
    // ════════════════════════════════════════════════════════
    window.page = { run: init };

    if (window.Admin?.page?.register) {
        window.Admin.page.register('bad_words', init);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

}());