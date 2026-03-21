(function() {
    'use strict';

    // Configuration
    let CFG, CSRF, STRINGS, CAN_CREATE, CAN_EDIT, CAN_DELETE;
    const FALLBACK_LANGS = ['ar', 'en', 'fr', 'tr', 'ur', 'de', 'es'];
    const PER_PAGE = 25;
    let currentPage = 1;
    let currentFilters = {};

    function reloadConfig() {
        CFG = window.BAD_WORDS_CONFIG || {};
        CSRF = CFG.csrfToken || '';
        STRINGS = CFG.strings || {};
        CAN_CREATE = !!CFG.canCreate;
        CAN_EDIT = !!CFG.canEdit;
        CAN_DELETE = !!CFG.canDelete;
    }
    reloadConfig();

    // Translation helper (dot notation)
    function t(key, fallback) {
        let val = STRINGS;
        const parts = key.split('.');
        for (let i = 0; i < parts.length; i++) {
            if (val && typeof val === 'object' && parts[i] in val) {
                val = val[parts[i]];
            } else {
                return fallback || key;
            }
        }
        return (typeof val === 'string') ? val : (fallback || key);
    }

    // Escape HTML
    function esc(str) {
        if (str == null) return '';
        const div = document.createElement('div');
        div.appendChild(document.createTextNode(String(str)));
        return div.innerHTML;
    }

    // Modal helpers (flex for centering)
    function openModal(id) {
        const modal = document.getElementById(id);
        if (modal) modal.style.display = 'flex';
    }
    function closeModal(id) {
        const modal = document.getElementById(id);
        if (modal) modal.style.display = 'none';
    }

    // Toast notifications
    function showNotification(message, type = 'info') {
        let container = document.getElementById('bwNotifications');
        if (!container) {
            container = document.createElement('div');
            container.id = 'bwNotifications';
            container.className = 'bw-notifications';
            const pageContainer = document.getElementById('badWordsPageContainer');
            if (pageContainer) pageContainer.insertBefore(container, pageContainer.firstChild);
            else document.body.appendChild(container);
        }
        const toast = document.createElement('div');
        toast.className = `bw-toast bw-toast-${type}`;
        toast.textContent = message;
        const closeBtn = document.createElement('span');
        closeBtn.className = 'bw-toast-close';
        closeBtn.textContent = '\u00d7';
        closeBtn.onclick = () => toast.remove();
        toast.appendChild(closeBtn);
        container.appendChild(toast);
        setTimeout(() => toast.remove(), 4000);
    }

    // Severity badge class
    function severityClass(level) {
        switch (String(level)) {
            case 'low': return 'badge-low';
            case 'medium': return 'badge-medium';
            case 'high': return 'badge-high';
            default: return 'badge-secondary';
        }
    }

    // Load words from API
    function loadBadWords(params = {}) {
        const page = params.page || 1;
        currentPage = page;
        currentFilters = params;
        const query = [];
        if (params.search) query.push(`search=${encodeURIComponent(params.search)}`);
        if (params.severity) query.push(`severity=${encodeURIComponent(params.severity)}`);
        if (params.is_active !== undefined && params.is_active !== '')
            query.push(`is_active=${encodeURIComponent(params.is_active)}`);
        query.push(`limit=${PER_PAGE}`);
        query.push(`offset=${(page - 1) * PER_PAGE}`);
        const url = `/api/bad_words${query.length ? '?' + query.join('&') : ''}`;

        fetch(url)
            .then(r => r.json())
            .then(d => {
                const tbody = document.getElementById('badWordsBody');
                tbody.innerHTML = '';
                let total = 0;
                if (d.success && d.data && d.data.items && d.data.items.length > 0) {
                    total = d.data.meta ? d.data.meta.total : d.data.items.length;
                    d.data.items.forEach(item => {
                        const tr = document.createElement('tr');
                        tr.innerHTML = `
                            <td>${esc(item.id)}</td>
                            <td>${esc(item.word)}</td>
                            <td><span class="badge ${severityClass(item.severity)}">${esc(item.severity)}</span></td>
                            <td>${item.is_regex ? t('table.yes', 'Yes') : t('table.no', 'No')}</td>
                            <td>${item.is_active ? t('table.yes', 'Yes') : t('table.no', 'No')}</td>
                            <td>${esc(item.created_at || '')}</td>
                            <td>
                                ${CAN_EDIT ? `<button class="btn btn-sm btn-info edit-btn" data-id="${esc(item.id)}">${t('table.edit', 'Edit')}</button> ` : ''}
                                <button class="btn btn-sm btn-secondary translations-btn" data-id="${esc(item.id)}">${t('table.translations', 'Translations')}</button>
                                ${CAN_DELETE ? `<button class="btn btn-sm btn-danger delete-btn" data-id="${esc(item.id)}">${t('table.delete', 'Delete')}</button>` : ''}
                            </td>
                        `;
                        tbody.appendChild(tr);
                    });
                } else {
                    const tr = document.createElement('tr');
                    const td = document.createElement('td');
                    td.setAttribute('colspan', '7');
                    td.style.textAlign = 'center';
                    td.textContent = t('no_items', 'No bad words found');
                    tr.appendChild(td);
                    tbody.appendChild(tr);
                }
                renderPagination(page, total);
            })
            .catch(err => {
                console.error('Error loading bad words:', err);
                showNotification('Failed to load data', 'error');
            });
    }

    // Pagination rendering
    function renderPagination(page, total) {
        const totalPages = Math.max(1, Math.ceil(total / PER_PAGE));
        const start = total > 0 ? (page - 1) * PER_PAGE + 1 : 0;
        const end = Math.min(page * PER_PAGE, total);

        const infoEl = document.getElementById('paginationInfo');
        if (infoEl) infoEl.textContent = `${start}-${end} ${t('pagination.of', 'of')} ${total}`;

        const pagEl = document.getElementById('pagination');
        if (!pagEl) return;
        pagEl.innerHTML = '';
        if (totalPages <= 1) return;

        const prevBtn = document.createElement('button');
        prevBtn.className = 'pagination-btn';
        prevBtn.innerHTML = '&laquo;';
        prevBtn.disabled = (page <= 1);
        prevBtn.addEventListener('click', () => goToPage(page - 1));
        pagEl.appendChild(prevBtn);

        for (let i = 1; i <= totalPages; i++) {
            if (i === 1 || i === totalPages || (i >= page - 2 && i <= page + 2)) {
                const pageBtn = document.createElement('button');
                pageBtn.className = `pagination-btn${i === page ? ' active' : ''}`;
                pageBtn.textContent = i;
                pageBtn.addEventListener('click', (function(p) { return function() { goToPage(p); }; })(i));
                pagEl.appendChild(pageBtn);
            } else if (i === page - 3 || i === page + 3) {
                const ellipsis = document.createElement('span');
                ellipsis.className = 'pagination-ellipsis';
                ellipsis.textContent = '...';
                pagEl.appendChild(ellipsis);
            }
        }

        const nextBtn = document.createElement('button');
        nextBtn.className = 'pagination-btn';
        nextBtn.innerHTML = '&raquo;';
        nextBtn.disabled = (page >= totalPages);
        nextBtn.addEventListener('click', () => goToPage(page + 1));
        pagEl.appendChild(nextBtn);
    }

    function goToPage(page) {
        const params = { ...currentFilters };
        delete params.page;
        params.page = page;
        loadBadWords(params);
    }

    // Add / Edit word modal
    function openAddModal() {
        document.getElementById('badWordForm').reset();
        document.getElementById('badWordId').value = '';
        document.getElementById('badWordModalTitle').textContent = t('add_word', 'Add Word');
        openModal('badWordModal');
    }

    function openEditModal(id) {
        fetch(`/api/bad_words?id=${encodeURIComponent(id)}`)
            .then(r => r.json())
            .then(d => {
                if (d.success && d.data) {
                    const rec = d.data;
                    document.getElementById('badWordId').value = rec.id;
                    document.getElementById('bwWord').value = rec.word || '';
                    document.getElementById('bwSeverity').value = rec.severity || '';
                    document.getElementById('bwIsRegex').checked = !!rec.is_regex;
                    document.getElementById('bwIsActive').checked = !!rec.is_active;
                    document.getElementById('badWordModalTitle').textContent = t('edit_word', 'Edit Word');
                    openModal('badWordModal');
                } else {
                    showNotification(d.message || 'Failed to load word data', 'error');
                }
            })
            .catch(err => {
                console.error('[Edit] Error:', err);
                showNotification('Network error while loading word', 'error');
            });
    }

    function saveBadWord(formData) {
        const editId = document.getElementById('badWordId').value;
        const method = editId ? 'PUT' : 'POST';
        const body = {
            word: formData.get('word'),
            severity: formData.get('severity'),
            is_regex: formData.get('is_regex') ? 1 : 0,
            is_active: formData.get('is_active') ? 1 : 0
        };
        if (editId) body.id = editId;

        fetch('/api/bad_words', {
            method,
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
            body: JSON.stringify(body)
        })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                closeModal('badWordModal');
                document.getElementById('badWordForm').reset();
                document.getElementById('badWordId').value = '';
                showNotification(t('saved', 'Saved successfully'), 'success');
                loadBadWords();
            } else {
                showNotification(d.message || t('unknown_error', 'Unknown error'), 'error');
            }
        })
        .catch(err => {
            console.error('[Save] Error:', err);
            showNotification('Network error while saving', 'error');
        });
    }

    function deleteBadWord(id) {
        if (!confirm(t('confirm_delete', 'Are you sure you want to delete this word?'))) return;
        fetch(`/api/bad_words?id=${encodeURIComponent(id)}`, {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF }
        })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                showNotification(t('deleted', 'Deleted successfully'), 'success');
                loadBadWords();
            } else {
                showNotification(d.message || t('delete_failed', 'Delete failed'), 'error');
            }
        })
        .catch(err => {
            console.error('[Delete] Error:', err);
            showNotification('Network error while deleting', 'error');
        });
    }

    // Translations management
    let currentTranslationBadWordId = null;
    let currentEditingTranslationId = null;

    function openTranslationsModal(badWordId) {
        currentTranslationBadWordId = badWordId;
        currentEditingTranslationId = null;
        document.getElementById('transLangCode').value = '';
        document.getElementById('transWord').value = '';
        document.getElementById('btnAddTranslation').style.display = 'inline-flex';
        document.getElementById('btnUpdateTranslation').style.display = 'none';

        fetch(`/api/bad_words/translations?bad_word_id=${encodeURIComponent(badWordId)}`)
            .then(r => r.json())
            .then(d => {
                const tbody = document.getElementById('translationsBody');
                tbody.innerHTML = '';
                let items = [];
                if (d.data && d.data.items) items = d.data.items;
                else if (Array.isArray(d.data)) items = d.data;
                if (items.length > 0) {
                    items.forEach(item => {
                        const tr = document.createElement('tr');
                        tr.innerHTML = `
                            <td>${esc(item.language_code)}</td>
                            <td>${esc(item.word)}</td>
                            <td>
                                <button class="btn btn-sm btn-info edit-translation-btn"
                                        data-id="${esc(item.id)}"
                                        data-lang="${esc(item.language_code)}"
                                        data-word="${esc(item.word)}">
                                    ${t('table.edit', 'Edit')}
                                </button>
                                <button class="btn btn-sm btn-danger delete-translation-btn"
                                        data-id="${esc(item.id)}">
                                    ${t('table.delete', 'Delete')}
                                </button>
                            </td>
                        `;
                        tbody.appendChild(tr);
                    });
                } else {
                    const tr = document.createElement('tr');
                    const td = document.createElement('td');
                    td.setAttribute('colspan', '3');
                    td.style.textAlign = 'center';
                    td.textContent = t('no_translations', 'No translations found');
                    tr.appendChild(td);
                    tbody.appendChild(tr);
                }
                openModal('translationsModal');
            })
            .catch(err => {
                console.error('[Translations] Error loading:', err);
                showNotification('Failed to load translations', 'error');
            });
    }

    function saveTranslation(badWordId, langCode, word) {
        fetch('/api/bad_words/translations', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
            body: JSON.stringify({ bad_word_id: badWordId, language_code: langCode, word: word })
        })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                showNotification(t('saved', 'Saved successfully'), 'success');
                openTranslationsModal(badWordId);
            } else {
                showNotification(d.message || t('unknown_error', 'Unknown error'), 'error');
            }
        })
        .catch(err => {
            console.error('[Save translation] Error:', err);
            showNotification('Network error while saving translation', 'error');
        });
    }

    function updateTranslation(transId, badWordId, langCode, word) {
        fetch('/api/bad_words/translations', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
            body: JSON.stringify({ id: transId, bad_word_id: badWordId, language_code: langCode, word: word })
        })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                showNotification(t('saved', 'Updated successfully'), 'success');
                openTranslationsModal(badWordId);
            } else {
                showNotification(d.message || t('unknown_error', 'Unknown error'), 'error');
            }
        })
        .catch(err => {
            console.error('[Update translation] Error:', err);
            showNotification('Network error while updating translation', 'error');
        });
    }

    function deleteTranslation(id) {
        if (!confirm(t('confirm_delete_translation', 'Are you sure you want to delete this translation?'))) return;
        fetch(`/api/bad_words/translations?id=${encodeURIComponent(id)}`, {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF }
        })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                showNotification(t('deleted', 'Deleted successfully'), 'success');
                if (currentTranslationBadWordId) openTranslationsModal(currentTranslationBadWordId);
            } else {
                showNotification(d.message || t('delete_failed', 'Delete failed'), 'error');
            }
        })
        .catch(err => {
            console.error('[Delete translation] Error:', err);
            showNotification('Network error while deleting translation', 'error');
        });
    }

    // Text check
    function checkText() {
        const textInput = document.getElementById('textCheckInput');
        const text = textInput ? textInput.value : '';
        if (!text) {
            showNotification(t('enter_text', 'Please enter text to check'), 'warning');
            return;
        }

        fetch('/api/bad_words/check', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
            body: JSON.stringify({ text })
        })
        .then(r => r.json())
        .then(d => {
            const resultsDiv = document.getElementById('textCheckResults');
            resultsDiv.innerHTML = '';
            if (d.success && d.data && d.data.found && d.data.found.length > 0) {
                const ul = document.createElement('ul');
                d.data.found.forEach(match => {
                    const li = document.createElement('li');
                    li.textContent = `${match.word || ''} (${t('table.severity', 'Severity')}: ${match.severity || ''})`;
                    ul.appendChild(li);
                });
                resultsDiv.appendChild(ul);
            } else if (d.success) {
                resultsDiv.textContent = t('no_bad_words_found', 'No bad words found in the text.');
            } else {
                resultsDiv.textContent = d.message || t('check_failed', 'Check failed');
            }
        })
        .catch(err => {
            console.error('[Check text] Error:', err);
            showNotification('Network error while checking text', 'error');
        });
    }

    // Filters
    function filterBadWords() {
        const search = document.getElementById('filterSearch').value;
        const severity = document.getElementById('filterSeverity').value;
        let status = document.getElementById('filterStatus').value;
        if (status === 'active') status = '1';
        else if (status === 'inactive') status = '0';
        loadBadWords({ search, severity, is_active: status, page: 1 });
    }

    function clearFilters() {
        document.getElementById('filterSearch').value = '';
        document.getElementById('filterSeverity').value = '';
        document.getElementById('filterStatus').value = '';
        loadBadWords({ page: 1 });
    }

    // Load languages for dropdown
    function loadLanguages() {
        const select = document.getElementById('transLangCode');
        if (!select) return;
        fetch('/api/languages')
            .then(r => r.json())
            .then(d => {
                select.innerHTML = '';
                let items = [];
                if (d.success && d.data) items = Array.isArray(d.data) ? d.data : (d.data.items || []);
                if (items.length > 0) {
                    items.forEach(lang => {
                        const opt = document.createElement('option');
                        opt.value = lang.code || lang.language_code || lang.id;
                        opt.textContent = lang.native_name || lang.name || lang.code || lang.id;
                        select.appendChild(opt);
                    });
                } else {
                    FALLBACK_LANGS.forEach(code => {
                        const opt = document.createElement('option');
                        opt.value = code;
                        opt.textContent = code;
                        select.appendChild(opt);
                    });
                }
            })
            .catch(() => {
                FALLBACK_LANGS.forEach(code => {
                    const opt = document.createElement('option');
                    opt.value = code;
                    opt.textContent = code;
                    select.appendChild(opt);
                });
            });
    }

    // Init
    function init() {
        reloadConfig();

        // Close modals
        document.querySelectorAll('.btn-close-modal').forEach(btn => {
            btn.addEventListener('click', () => closeModal(btn.dataset.modal));
        });

        // Add word
        const btnAdd = document.getElementById('btnAddWord');
        if (btnAdd) btnAdd.addEventListener('click', openAddModal);

        // Form submit
        const form = document.getElementById('badWordForm');
        if (form) form.addEventListener('submit', e => {
            e.preventDefault();
            saveBadWord(new FormData(form));
        });

        // Filters
        const btnFilter = document.getElementById('btnFilter');
        if (btnFilter) btnFilter.addEventListener('click', filterBadWords);
        const btnClear = document.getElementById('btnClearFilters');
        if (btnClear) btnClear.addEventListener('click', clearFilters);
        const searchInput = document.getElementById('filterSearch');
        if (searchInput) searchInput.addEventListener('keydown', e => {
            if (e.key === 'Enter') { e.preventDefault(); filterBadWords(); }
        });

        // Text check
        const btnOpenCheck = document.getElementById('btnOpenCheckText');
        if (btnOpenCheck) btnOpenCheck.addEventListener('click', () => openModal('textCheckModal'));
        const btnDoCheck = document.getElementById('btnCheckText');
        if (btnDoCheck) btnDoCheck.addEventListener('click', checkText);

        // Translations: add and update
        const btnAddTranslation = document.getElementById('btnAddTranslation');
        if (btnAddTranslation) {
            btnAddTranslation.addEventListener('click', () => {
                const langCode = document.getElementById('transLangCode').value;
                const word = document.getElementById('transWord').value;
                if (!word) { showNotification(t('enter_text', 'Please enter a word'), 'warning'); return; }
                if (currentEditingTranslationId) {
                    // Update existing translation
                    updateTranslation(currentEditingTranslationId, currentTranslationBadWordId, langCode, word);
                } else {
                    // Create new translation
                    saveTranslation(currentTranslationBadWordId, langCode, word);
                }
            });
        }
        const btnUpdateTranslation = document.getElementById('btnUpdateTranslation');
        if (btnUpdateTranslation) {
            btnUpdateTranslation.addEventListener('click', () => {
                const langCode = document.getElementById('transLangCode').value;
                const word = document.getElementById('transWord').value;
                if (!word) { showNotification(t('enter_text', 'Please enter a word'), 'warning'); return; }
                updateTranslation(currentEditingTranslationId, currentTranslationBadWordId, langCode, word);
            });
        }

        // Event delegation for dynamic buttons
        document.addEventListener('click', e => {
            const editBtn = e.target.closest('.edit-btn');
            if (editBtn) { openEditModal(editBtn.dataset.id); return; }

            const deleteBtn = e.target.closest('.delete-btn');
            if (deleteBtn) { deleteBadWord(deleteBtn.dataset.id); return; }

            const transBtn = e.target.closest('.translations-btn');
            if (transBtn) { openTranslationsModal(transBtn.dataset.id); return; }

            const delTransBtn = e.target.closest('.delete-translation-btn');
            if (delTransBtn) { deleteTranslation(delTransBtn.dataset.id); return; }

            const editTransBtn = e.target.closest('.edit-translation-btn');
            if (editTransBtn) {
                // Pre‑fill the translation modal without an extra API call
                currentEditingTranslationId = editTransBtn.dataset.id;
                document.getElementById('transLangCode').value = editTransBtn.dataset.lang;
                document.getElementById('transWord').value = editTransBtn.dataset.word;
                document.getElementById('btnAddTranslation').style.display = 'none';
                document.getElementById('btnUpdateTranslation').style.display = 'inline-flex';
                // The modal is already open (translations modal), so we don't open it again.
                return;
            }
        });

        loadLanguages();
        loadBadWords();
    }

    // Register for fragment navigation
    window.page = { run: init };
    if (window.Admin && Admin.page && typeof Admin.page.register === 'function') {
        Admin.page.register('bad_words', init);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
