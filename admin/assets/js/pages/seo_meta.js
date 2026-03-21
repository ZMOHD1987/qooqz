(function () {
    'use strict';

    var CFG, CSRF, STRINGS, CAN_CREATE, CAN_EDIT, CAN_DELETE;
    var FALLBACK_LANGS = ['ar', 'en', 'fr', 'tr', 'ur', 'de', 'es'];
    var PER_PAGE = 25;
    var currentPage = 1;
    var currentFilters = {};

    function reloadConfig() {
        CFG        = window.SEO_META_CONFIG || {};
        CSRF       = CFG.csrfToken  || '';
        STRINGS    = CFG.strings    || {};
        CAN_CREATE = !!CFG.canCreate;
        CAN_EDIT   = !!CFG.canEdit;
        CAN_DELETE = !!CFG.canDelete;
    }
    reloadConfig();

    /* ── i18n ─────────────────────────────────────────── */
    function t(key, fallback) {
        var keys = key.split('.');
        var val = STRINGS;
        for (var i = 0; i < keys.length; i++) {
            if (val && typeof val === 'object' && keys[i] in val) {
                val = val[keys[i]];
            } else {
                return fallback || key;
            }
        }
        return (typeof val === 'string') ? val : (fallback || key);
    }

    /* ── XSS escape ───────────────────────────────────── */
    function esc(str) {
        if (str == null) return '';
        var d = document.createElement('div');
        d.appendChild(document.createTextNode(String(str)));
        return d.innerHTML;
    }

    /* ── Toast notifications ──────────────────────────── */
    function showNotification(message, type) {
        type = type || 'info';
        var container = document.getElementById('smNotifications');
        if (!container) {
            container = document.createElement('div');
            container.id = 'smNotifications';
            container.className = 'sm-notifications';
            var pageContainer = document.getElementById('seoMetaPageContainer');
            if (pageContainer) pageContainer.insertBefore(container, pageContainer.firstChild);
            else document.body.appendChild(container);
        }
        var toast = document.createElement('div');
        toast.className = 'sm-toast sm-toast-' + type;
        toast.textContent = message;
        var closeBtn = document.createElement('span');
        closeBtn.className = 'sm-toast-close';
        closeBtn.textContent = '\u00d7';
        closeBtn.onclick = function () { toast.remove(); };
        toast.appendChild(closeBtn);
        container.appendChild(toast);
        setTimeout(function () { toast.remove(); }, 4000);
    }

    /* ── Form Card helpers (inline card, NOT modal) ───── */
    function showFormCard() {
        var card = document.getElementById('seoMetaFormCard');
        if (card) card.style.display = '';
    }

    function hideFormCard() {
        var card = document.getElementById('seoMetaFormCard');
        if (card) card.style.display = 'none';
    }

    /* ── Translations Modal helpers ───────────────────── */
    function openTranslationsModal() {
        var m = document.getElementById('translationsModal');
        if (m) m.style.display = 'flex';
    }

    function closeTranslationsModal() {
        var m = document.getElementById('translationsModal');
        if (m) m.style.display = 'none';
    }

    /* ── Open Add form ────────────────────────────────── */
    function openAddForm() {
        var form = document.getElementById('seoMetaForm');
        if (form) form.reset();
        var idEl = document.getElementById('seoMetaId');
        if (idEl) idEl.value = '';
        var title = document.getElementById('seoMetaFormTitle');
        if (title) title.textContent = t('modal.add_title', 'Add SEO Record');
        showFormCard();
        // scroll to form
        var card = document.getElementById('seoMetaFormCard');
        if (card) card.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    /* ── Open Edit form ───────────────────────────────── */
    function editSeoMeta(id) {
        fetch('/api/seo_meta?id=' + encodeURIComponent(id))
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (d.success && d.data) {
                    var rec = d.data;
                    var idEl = document.getElementById('seoMetaId');
                    if (idEl) idEl.value = rec.id;
                    var et = document.getElementById('smEntityType');
                    if (et) et.value = rec.entity_type || '';
                    var ei = document.getElementById('smEntityId');
                    if (ei) ei.value = rec.entity_id || '';
                    var cu = document.getElementById('smCanonicalUrl');
                    if (cu) cu.value = rec.canonical_url || '';
                    var rb = document.getElementById('smRobots');
                    if (rb) rb.value = rec.robots || 'index,follow';
                    var sm = document.getElementById('smSchemaMarkup');
                    if (sm) sm.value = rec.schema_markup || '';
                    var title = document.getElementById('seoMetaFormTitle');
                    if (title) title.textContent = t('modal.edit_title', 'Edit SEO Record');
                    showFormCard();
                    var card = document.getElementById('seoMetaFormCard');
                    if (card) card.scrollIntoView({ behavior: 'smooth', block: 'start' });
                } else {
                    showNotification(d.message || t('unknown_error', 'Unknown error'), 'error');
                }
            })
            .catch(function () {
                showNotification(t('unknown_error', 'Unknown error'), 'error');
            });
    }

    /* ── Save SEO Meta ────────────────────────────────── */
    function saveSeoMeta(formData) {
        var editId = document.getElementById('seoMetaId').value;
        var method = editId ? 'PUT' : 'POST';
        var body = {
            entity_type:   formData.get('entity_type'),
            entity_id:     formData.get('entity_id'),
            canonical_url: formData.get('canonical_url'),
            robots:        formData.get('robots'),
            schema_markup: formData.get('schema_markup')
        };
        if (editId) body.id = editId;

        fetch('/api/seo_meta', {
            method:  method,
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
            body:    JSON.stringify(body)
        })
        .then(function (r) { return r.json(); })
        .then(function (d) {
            if (d.success) {
                hideFormCard();
                var form = document.getElementById('seoMetaForm');
                if (form) form.reset();
                var idEl = document.getElementById('seoMetaId');
                if (idEl) idEl.value = '';
                showNotification(t('saved', 'Saved successfully'), 'success');
                loadSeoMeta(currentFilters);
            } else {
                showNotification(d.message || t('unknown_error', 'Unknown error'), 'error');
            }
        })
        .catch(function () {
            showNotification(t('unknown_error', 'Unknown error'), 'error');
        });
    }

    /* ── Delete SEO Meta ──────────────────────────────── */
    function deleteSeoMeta(id) {
        if (!confirm(t('confirm_delete', 'Are you sure you want to delete this SEO record?'))) return;
        fetch('/api/seo_meta?id=' + encodeURIComponent(id), {
            method:  'DELETE',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF }
        })
        .then(function (r) { return r.json(); })
        .then(function (d) {
            if (d.success) {
                showNotification(t('deleted', 'Deleted successfully'), 'success');
                loadSeoMeta(currentFilters);
            } else {
                showNotification(d.message || t('delete_failed', 'Delete failed'), 'error');
            }
        });
    }

    /* ── Load SEO Meta list ───────────────────────────── */
    function loadSeoMeta(params) {
        params = params || {};
        var page = params.page || 1;
        currentPage    = page;
        currentFilters = params;
        var query = [];
        if (params.search)      query.push('search='      + encodeURIComponent(params.search));
        if (params.entity_type) query.push('entity_type=' + encodeURIComponent(params.entity_type));
        query.push('limit='  + PER_PAGE);
        query.push('offset=' + ((page - 1) * PER_PAGE));
        var url = '/api/seo_meta' + (query.length ? '?' + query.join('&') : '');

        fetch(url)
            .then(function (r) { return r.json(); })
            .then(function (d) {
                var tbody = document.getElementById('seoMetaBody');
                if (!tbody) return;
                tbody.innerHTML = '';
                var total = 0;

                if (d.success && d.data && d.data.items && d.data.items.length > 0) {
                    total = d.data.meta ? d.data.meta.total : d.data.items.length;
                    d.data.items.forEach(function (item) {
                        var tr = document.createElement('tr');
                        tr.innerHTML =
                            '<td>' + esc(item.id) + '</td>' +
                            '<td><span class="badge badge-info">' + esc(item.entity_type) + '</span></td>' +
                            '<td>' + esc(item.entity_id) + '</td>' +
                            '<td class="sm-url-cell" title="' + esc(item.canonical_url || '') + '">' + esc(item.canonical_url || '—') + '</td>' +
                            '<td>' + esc(item.robots || '') + '</td>' +
                            '<td>' + esc(item.created_at || '') + '</td>' +
                            '<td>' +
                                (CAN_EDIT   ? '<button class="btn btn-sm btn-info edit-btn" data-id="'   + esc(item.id) + '">'
                                                + '<i class="fas fa-edit"></i> '  + esc(t('table.edit', 'Edit'))   + '</button> ' : '') +
                                '<button class="btn btn-sm btn-secondary translations-btn" data-id="' + esc(item.id) + '">'
                                    + '<i class="fas fa-language"></i> ' + esc(t('table.translations', 'Translations')) + '</button> ' +
                                (CAN_DELETE ? '<button class="btn btn-sm btn-danger delete-btn" data-id="' + esc(item.id) + '">'
                                                + '<i class="fas fa-trash"></i> ' + esc(t('table.delete', 'Delete')) + '</button>'  : '') +
                            '</td>';
                        tbody.appendChild(tr);
                    });
                } else {
                    var tr = document.createElement('tr');
                    var td = document.createElement('td');
                    td.setAttribute('colspan', '7');
                    td.className   = 'text-center';
                    td.textContent = t('no_items', 'No SEO records found');
                    tr.appendChild(td);
                    tbody.appendChild(tr);
                }

                renderPagination(page, total);
            })
            .catch(function () {
                showNotification(t('unknown_error', 'Unknown error'), 'error');
            });
    }

    /* ── Pagination ───────────────────────────────────── */
    function renderPagination(page, total) {
        var totalPages = Math.max(1, Math.ceil(total / PER_PAGE));
        var start = total > 0 ? (page - 1) * PER_PAGE + 1 : 0;
        var end   = Math.min(page * PER_PAGE, total);

        var infoEl = document.getElementById('paginationInfo');
        if (infoEl) infoEl.textContent = start + '-' + end + ' ' + t('pagination.of', 'of') + ' ' + total;

        var pagEl = document.getElementById('pagination');
        if (!pagEl) return;
        pagEl.innerHTML = '';
        if (totalPages <= 1) return;

        var prevBtn = document.createElement('button');
        prevBtn.innerHTML = '&laquo;';
        prevBtn.disabled  = (page <= 1);
        prevBtn.addEventListener('click', function () { goToPage(page - 1); });
        pagEl.appendChild(prevBtn);

        for (var i = 1; i <= totalPages; i++) {
            if (i === 1 || i === totalPages || (i >= page - 2 && i <= page + 2)) {
                (function (pg) {
                    var btn = document.createElement('button');
                    btn.textContent = pg;
                    if (pg === page) btn.className = 'active';
                    btn.addEventListener('click', function () { goToPage(pg); });
                    pagEl.appendChild(btn);
                })(i);
            } else if (i === page - 3 || i === page + 3) {
                var sp = document.createElement('span');
                sp.className   = 'pagination-ellipsis';
                sp.textContent = '…';
                pagEl.appendChild(sp);
            }
        }

        var nextBtn = document.createElement('button');
        nextBtn.innerHTML = '&raquo;';
        nextBtn.disabled  = (page >= totalPages);
        nextBtn.addEventListener('click', function () { goToPage(page + 1); });
        pagEl.appendChild(nextBtn);
    }

    function goToPage(page) {
        var params = {};
        for (var k in currentFilters) { if (k !== 'page') params[k] = currentFilters[k]; }
        params.page = page;
        loadSeoMeta(params);
    }

    /* ── Filter ───────────────────────────────────────── */
    function filterSeoMeta() {
        var search     = document.getElementById('filterSearch');
        var entityType = document.getElementById('filterEntityType');
        loadSeoMeta({
            search:      search      ? search.value      : '',
            entity_type: entityType  ? entityType.value  : '',
            page: 1
        });
    }

    function clearFilters() {
        var s = document.getElementById('filterSearch');
        var e = document.getElementById('filterEntityType');
        if (s) s.value = '';
        if (e) e.value = '';
        loadSeoMeta({ page: 1 });
    }

    /* ── Translations (history modal) ─────────────────── */
    var currentTranslationSeoMetaId = null;

    function openTransModal(seoMetaId) {
        currentTranslationSeoMetaId = seoMetaId;
        var el = document.getElementById('transSeoMetaId');
        if (el) el.value = seoMetaId;
        loadTranslations(seoMetaId);
        openTranslationsModal();
    }

    function loadTranslations(seoMetaId) {
        fetch('/api/seo_meta/translations?seo_meta_id=' + encodeURIComponent(seoMetaId))
            .then(function (r) { return r.json(); })
            .then(function (d) {
                var tbody = document.getElementById('translationsBody');
                if (!tbody) return;
                tbody.innerHTML = '';
                var items = [];
                if (d.data && d.data.items)    items = d.data.items;
                else if (Array.isArray(d.data)) items = d.data;

                if (items.length > 0) {
                    items.forEach(function (item) {
                        var tr = document.createElement('tr');
                        tr.innerHTML =
                            '<td>' + esc(item.language_code) + '</td>' +
                            '<td>' + esc(item.meta_title  || '') + '</td>' +
                            '<td>' + esc(item.og_title    || '') + '</td>' +
                            '<td><button class="btn btn-sm btn-danger delete-translation-btn" data-id="' + esc(item.id) + '">' +
                                '<i class="fas fa-trash"></i> ' + t('table.delete', 'Delete') + '</button></td>';
                        tbody.appendChild(tr);
                    });
                } else {
                    var tr = document.createElement('tr');
                    var td = document.createElement('td');
                    td.setAttribute('colspan', '4');
                    td.className   = 'text-center';
                    td.textContent = t('no_translations', 'No translations found');
                    tr.appendChild(td);
                    tbody.appendChild(tr);
                }
            });
    }

    function saveTranslation() {
        var seoMetaId = currentTranslationSeoMetaId;
        if (!seoMetaId) { showNotification(t('unknown_error', 'Unknown error'), 'error'); return; }

        function gv(id) { var el = document.getElementById(id); return el ? el.value : ''; }

        fetch('/api/seo_meta/translations', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
            body:    JSON.stringify({
                seo_meta_id:      seoMetaId,
                language_code:    gv('transLangCode'),
                meta_title:       gv('transMetaTitle'),
                meta_description: gv('transMetaDescription'),
                meta_keywords:    gv('transMetaKeywords'),
                og_title:         gv('transOgTitle'),
                og_description:   gv('transOgDescription'),
                og_image:         gv('transOgImage')
            })
        })
        .then(function (r) { return r.json(); })
        .then(function (d) {
            if (d.success) {
                showNotification(t('saved', 'Saved successfully'), 'success');
                ['transMetaTitle','transMetaDescription','transMetaKeywords','transOgTitle','transOgDescription','transOgImage']
                    .forEach(function (id) { var el = document.getElementById(id); if (el) el.value = ''; });
                loadTranslations(seoMetaId);
            } else {
                showNotification(d.message || t('unknown_error', 'Unknown error'), 'error');
            }
        });
    }

    function deleteTranslation(id) {
        if (!confirm(t('confirm_delete_translation', 'Are you sure you want to delete this translation?'))) return;
        fetch('/api/seo_meta/translations?id=' + encodeURIComponent(id), {
            method:  'DELETE',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF }
        })
        .then(function (r) { return r.json(); })
        .then(function (d) {
            if (d.success) {
                showNotification(t('deleted', 'Deleted successfully'), 'success');
                if (currentTranslationSeoMetaId) loadTranslations(currentTranslationSeoMetaId);
            } else {
                showNotification(d.message || t('delete_failed', 'Delete failed'), 'error');
            }
        });
    }

    /* ── Load languages for translations modal ─────────── */
    function loadLanguages() {
        var select = document.getElementById('transLangCode');
        if (!select) return;
        fetch('/api/languages')
            .then(function (r) { return r.json(); })
            .then(function (d) {
                select.innerHTML = '';
                var items = [];
                if (d.success && d.data) {
                    items = Array.isArray(d.data) ? d.data : (d.data.items || []);
                }
                if (!items.length) {
                    items = FALLBACK_LANGS.map(function (c) { return { code: c }; });
                }
                items.forEach(function (lang) {
                    var opt = document.createElement('option');
                    opt.value       = lang.code || lang.language_code || lang.id;
                    opt.textContent = lang.native_name || lang.name || lang.code || lang.id;
                    select.appendChild(opt);
                });
            })
            .catch(function () {
                select.innerHTML = '';
                FALLBACK_LANGS.forEach(function (code) {
                    var opt = document.createElement('option');
                    opt.value = opt.textContent = code;
                    select.appendChild(opt);
                });
            });
    }

    /* ── Init ─────────────────────────────────────────── */
    function init() {
        reloadConfig();

        /* Add button */
        var btnAdd = document.getElementById('btnAddSeoMeta');
        if (btnAdd) btnAdd.addEventListener('click', function () { openAddForm(); });

        /* Close form card */
        var btnClose = document.getElementById('btnCloseForm');
        if (btnClose) btnClose.addEventListener('click', function () { hideFormCard(); });

        var btnCancel = document.getElementById('btnCancelForm');
        if (btnCancel) btnCancel.addEventListener('click', function () { hideFormCard(); });

        /* Form submit */
        var form = document.getElementById('seoMetaForm');
        if (form) form.addEventListener('submit', function (e) {
            e.preventDefault();
            saveSeoMeta(new FormData(this));
        });

        /* Filter */
        var btnFilt = document.getElementById('btnFilter');
        if (btnFilt) btnFilt.addEventListener('click', function () { filterSeoMeta(); });

        var btnClear = document.getElementById('btnClearFilters');
        if (btnClear) btnClear.addEventListener('click', function () { clearFilters(); });

        var searchInput = document.getElementById('filterSearch');
        if (searchInput) searchInput.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') { e.preventDefault(); filterSeoMeta(); }
        });

        /* Translations modal buttons */
        var btnCloseTrans = document.getElementById('btnCloseTransModal');
        if (btnCloseTrans) btnCloseTrans.addEventListener('click', function () { closeTranslationsModal(); });

        var btnCancelTrans = document.getElementById('btnCancelTransModal');
        if (btnCancelTrans) btnCancelTrans.addEventListener('click', function () { closeTranslationsModal(); });

        var transOverlay = document.getElementById('translationsModalOverlay');
        if (transOverlay) transOverlay.addEventListener('click', function () { closeTranslationsModal(); });

        var btnAddTrans = document.getElementById('btnAddTranslation');
        if (btnAddTrans) btnAddTrans.addEventListener('click', function () { saveTranslation(); });

        /* Delegated events */
        document.addEventListener('click', function (e) {
            var editBtn = e.target.closest('.edit-btn');
            if (editBtn) { editSeoMeta(editBtn.dataset.id); return; }

            var deleteBtn = e.target.closest('.delete-btn');
            if (deleteBtn) { deleteSeoMeta(deleteBtn.dataset.id); return; }

            var transBtn = e.target.closest('.translations-btn');
            if (transBtn) { openTransModal(transBtn.dataset.id); return; }

            var delTransBtn = e.target.closest('.delete-translation-btn');
            if (delTransBtn) { deleteTranslation(delTransBtn.dataset.id); return; }
        });

        loadLanguages();
        loadSeoMeta();
    }

    /* Fragment/SPA support */
    window.page = { run: init };
    if (window.Admin && Admin.page && typeof Admin.page.register === 'function') {
        Admin.page.register('seo_meta', init);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
