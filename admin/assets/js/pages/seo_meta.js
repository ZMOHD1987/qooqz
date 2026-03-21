(function () {
    'use strict';

    var CFG, CSRF, STRINGS, CAN_CREATE, CAN_EDIT, CAN_DELETE;
    var FALLBACK_LANGS = ['ar', 'en', 'fr', 'tr', 'ur', 'de', 'es'];
    var PER_PAGE = 25;
    var currentPage = 1;
    var currentFilters = {};
    var translationsCache = {};   // keyed by translation id
    var langsLoaded = false;

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

    /* ── Form Card helpers ────────────────────────────── */
    function showFormCard() {
        var card = document.getElementById('seoMetaFormCard');
        if (card) card.style.display = '';
    }

    function hideFormCard() {
        var card = document.getElementById('seoMetaFormCard');
        if (card) card.style.display = 'none';
        switchTab('sm-general');
        hideAddTransPanel();
    }

    /* ── Tab switching ────────────────────────────────── */
    function switchTab(tabName) {
        var card = document.getElementById('seoMetaFormCard');
        if (!card) return;
        card.querySelectorAll('.tab-btn').forEach(function (btn) {
            btn.classList.remove('active');
        });
        card.querySelectorAll('.tab-content').forEach(function (pane) {
            pane.classList.remove('active');
            pane.style.display = 'none';
        });
        var activeBtn  = card.querySelector('.tab-btn[data-tab="' + tabName + '"]');
        var activePane = document.getElementById('tab-' + tabName);
        if (activeBtn)  activeBtn.classList.add('active');
        if (activePane) { activePane.classList.add('active'); activePane.style.display = ''; }
    }

    /* ── Open Add form ────────────────────────────────── */
    function openAddForm() {
        var form = document.getElementById('seoMetaForm');
        if (form) form.reset();
        var idEl = document.getElementById('seoMetaId');
        if (idEl) idEl.value = '';

        var transTabBtn = document.getElementById('tabTranslationsBtn');
        if (transTabBtn) transTabBtn.style.display = 'none';

        var title = document.getElementById('seoMetaFormTitle');
        if (title) title.textContent = t('modal.add_title', 'Add SEO Record');

        switchTab('sm-general');
        showFormCard();
        var card = document.getElementById('seoMetaFormCard');
        if (card) card.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    /* ── Open Edit form ───────────────────────────────── */
    function editSeoMeta(id, activeTab) {
        activeTab = activeTab || 'sm-general';
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

                    var transTabBtn = document.getElementById('tabTranslationsBtn');
                    if (transTabBtn) transTabBtn.style.display = '';

                    var title = document.getElementById('seoMetaFormTitle');
                    if (title) title.textContent = t('modal.edit_title', 'Edit SEO Record');

                    var transIdEl = document.getElementById('transSeoMetaId');
                    if (transIdEl) transIdEl.value = rec.id;

                    hideAddTransPanel();
                    switchTab(activeTab);
                    showFormCard();
                    var card = document.getElementById('seoMetaFormCard');
                    if (card) card.scrollIntoView({ behavior: 'smooth', block: 'start' });

                    if (activeTab === 'sm-translations') {
                        loadTranslations(rec.id);
                    }
                } else {
                    showNotification(d.message || t('unknown_error', 'Unknown error'), 'error');
                }
            })
            .catch(function () {
                showNotification(t('unknown_error', 'Unknown error'), 'error');
            });
    }

    /* ── Save SEO Meta (General tab) ─────────────────── */
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
                            '<td class="sm-url-cell" title="' + esc(item.canonical_url || '') + '">' + esc(item.canonical_url || '\u2014') + '</td>' +
                            '<td>' + esc(item.robots || '') + '</td>' +
                            '<td>' + esc(item.created_at || '') + '</td>' +
                            '<td>' +
                                (CAN_EDIT ? '<button class="btn btn-sm btn-info edit-btn" data-id="' + esc(item.id) + '">'
                                    + '<i class="fas fa-edit"></i> ' + esc(t('table.edit', 'Edit')) + '</button> ' : '') +
                                (CAN_EDIT ? '<button class="btn btn-sm btn-secondary translations-btn" data-id="' + esc(item.id) + '">'
                                    + '<i class="fas fa-language"></i> ' + esc(t('table.translations', 'Translations')) + '</button> ' : '') +
                                (CAN_DELETE ? '<button class="btn btn-sm btn-danger delete-btn" data-id="' + esc(item.id) + '">'
                                    + '<i class="fas fa-trash"></i> ' + esc(t('table.delete', 'Delete')) + '</button>' : '') +
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
                sp.textContent = '\u2026';
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
            search:      search     ? search.value     : '',
            entity_type: entityType ? entityType.value : '',
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

    /* ══════════════════════════════════════════════════════
       TRANSLATIONS – editable rows + add panel
    ══════════════════════════════════════════════════════ */

    /* ── Load & render all translations ──────────────── */
    function loadTranslations(seoMetaId) {
        fetch('/api/seo_meta/translations?seo_meta_id=' + encodeURIComponent(seoMetaId))
            .then(function (r) { return r.json(); })
            .then(function (d) {
                translationsCache = {};
                var tbody = document.getElementById('translationsBody');
                if (!tbody) return;
                tbody.innerHTML = '';

                var items = [];
                if (d.data && d.data.items)    items = d.data.items;
                else if (Array.isArray(d.data)) items = d.data;

                if (items.length > 0) {
                    items.forEach(function (item) {
                        translationsCache[item.id] = item;
                        tbody.appendChild(buildTransSummaryRow(item));
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
            })
            .catch(function () {
                showNotification(t('unknown_error', 'Unknown error'), 'error');
            });
    }

    /* ── Build a summary (read-only) translation row ── */
    function buildTransSummaryRow(item) {
        var tr = document.createElement('tr');
        tr.id = 'trans-row-' + item.id;
        tr.innerHTML =
            '<td><span class="lang-badge">' + esc(item.language_code) + '</span></td>' +
            '<td>' + esc(item.meta_title || '') + '</td>' +
            '<td>' + esc(item.og_title || '') + '</td>' +
            '<td>' +
                '<button type="button" class="btn btn-sm btn-info edit-trans-btn" data-id="' + esc(item.id) + '">' +
                    '<i class="fas fa-edit"></i> ' + t('table.edit', 'Edit') + '</button> ' +
                '<button type="button" class="btn btn-sm btn-danger delete-translation-btn" data-id="' + esc(item.id) + '">' +
                    '<i class="fas fa-trash"></i> ' + t('table.delete', 'Delete') + '</button>' +
            '</td>';
        return tr;
    }

    /* ── Toggle inline-edit detail row ───────────────── */
    function toggleTransDetailRow(id) {
        var existing = document.getElementById('trans-detail-' + id);
        if (existing) {
            existing.remove();
            return;
        }
        var item = translationsCache[id];
        if (!item) return;
        var summaryRow = document.getElementById('trans-row-' + id);
        if (!summaryRow) return;

        var detailTr = document.createElement('tr');
        detailTr.id = 'trans-detail-' + id;
        detailTr.className = 'trans-detail-row';
        detailTr.innerHTML =
            '<td colspan="4">' +
            '<div class="trans-edit-form">' +
                '<div class="form-row">' +
                    '<div class="form-group">' +
                        '<label>' + t('translations.meta_title', 'Meta Title') + '</label>' +
                        '<input type="text" class="form-control tei-meta-title" value="' + esc(item.meta_title || '') + '">' +
                    '</div>' +
                    '<div class="form-group">' +
                        '<label>' + t('translations.og_title', 'OG Title') + '</label>' +
                        '<input type="text" class="form-control tei-og-title" value="' + esc(item.og_title || '') + '">' +
                    '</div>' +
                '</div>' +
                '<div class="form-group">' +
                    '<label>' + t('translations.meta_description', 'Meta Description') + '</label>' +
                    '<textarea class="form-control tei-meta-desc" rows="2">' + esc(item.meta_description || '') + '</textarea>' +
                '</div>' +
                '<div class="form-row">' +
                    '<div class="form-group">' +
                        '<label>' + t('translations.meta_keywords', 'Meta Keywords') + '</label>' +
                        '<input type="text" class="form-control tei-meta-keywords" value="' + esc(item.meta_keywords || '') + '">' +
                    '</div>' +
                    '<div class="form-group">' +
                        '<label>' + t('translations.og_image', 'OG Image') + '</label>' +
                        '<input type="text" class="form-control tei-og-image" value="' + esc(item.og_image || '') + '">' +
                    '</div>' +
                '</div>' +
                '<div class="form-group">' +
                    '<label>' + t('translations.og_description', 'OG Description') + '</label>' +
                    '<textarea class="form-control tei-og-desc" rows="2">' + esc(item.og_description || '') + '</textarea>' +
                '</div>' +
                '<div class="form-actions">' +
                    '<button type="button" class="btn btn-sm btn-primary save-trans-edit-btn" data-id="' + esc(id) + '">' +
                        '<i class="fas fa-save"></i> ' + t('form.save', 'Save') + '</button> ' +
                    '<button type="button" class="btn btn-sm btn-secondary cancel-trans-edit-btn" data-id="' + esc(id) + '">' +
                        t('form.cancel', 'Cancel') + '</button>' +
                '</div>' +
            '</div>' +
            '</td>';
        summaryRow.after(detailTr);
    }

    /* ── Update an existing translation ──────────────── */
    function updateTranslation(id) {
        var detailRow = document.getElementById('trans-detail-' + id);
        if (!detailRow) return;
        var item = translationsCache[id];
        var transIdEl = document.getElementById('transSeoMetaId');
        var seoMetaId = transIdEl ? transIdEl.value : '';

        function gvc(cls) {
            var el = detailRow.querySelector('.' + cls);
            return el ? el.value : '';
        }

        fetch('/api/seo_meta/translations', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
            body:    JSON.stringify({
                seo_meta_id:      seoMetaId,
                language_code:    item.language_code,
                meta_title:       gvc('tei-meta-title'),
                meta_description: gvc('tei-meta-desc'),
                meta_keywords:    gvc('tei-meta-keywords'),
                og_title:         gvc('tei-og-title'),
                og_description:   gvc('tei-og-desc'),
                og_image:         gvc('tei-og-image')
            })
        })
        .then(function (r) { return r.json(); })
        .then(function (d) {
            if (d.success) {
                showNotification(t('saved', 'Saved successfully'), 'success');
                loadTranslations(seoMetaId);
            } else {
                showNotification(d.message || t('unknown_error', 'Unknown error'), 'error');
            }
        })
        .catch(function () {
            showNotification(t('unknown_error', 'Unknown error'), 'error');
        });
    }

    /* ── Delete an existing translation ──────────────── */
    function deleteTranslation(id) {
        if (!confirm(t('confirm_delete_translation', 'Are you sure you want to delete this translation?'))) return;
        var transIdEl = document.getElementById('transSeoMetaId');
        var seoMetaId = transIdEl ? transIdEl.value : '';

        fetch('/api/seo_meta/translations?id=' + encodeURIComponent(id), {
            method:  'DELETE',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF }
        })
        .then(function (r) { return r.json(); })
        .then(function (d) {
            if (d.success) {
                showNotification(t('deleted', 'Deleted successfully'), 'success');
                if (seoMetaId) loadTranslations(seoMetaId);
            } else {
                showNotification(d.message || t('delete_failed', 'Delete failed'), 'error');
            }
        });
    }

    /* ── Show / hide "Add Translation" panel ─────────── */
    function showAddTransPanel() {
        var panel = document.getElementById('addTransPanel');
        if (!panel) return;
        panel.style.display = '';
        // Load languages lazily
        var select = document.getElementById('transLangCode');
        if (select && !langsLoaded) loadLanguages();
    }

    function hideAddTransPanel() {
        var panel = document.getElementById('addTransPanel');
        if (panel) panel.style.display = 'none';
        clearAddTransForm();
    }

    function clearAddTransForm() {
        ['transMetaTitle','transOgTitle','transMetaKeywords','transMetaDescription','transOgDescription','transOgImage']
            .forEach(function (id) { var el = document.getElementById(id); if (el) el.value = ''; });
    }

    /* ── Save a new translation ───────────────────────── */
    function saveNewTranslation() {
        var transIdEl = document.getElementById('transSeoMetaId');
        var seoMetaId = transIdEl ? transIdEl.value : '';
        if (!seoMetaId) {
            showNotification(t('unknown_error', 'Unknown error'), 'error');
            return;
        }

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
                hideAddTransPanel();
                loadTranslations(seoMetaId);
            } else {
                showNotification(d.message || t('unknown_error', 'Unknown error'), 'error');
            }
        })
        .catch(function () {
            showNotification(t('unknown_error', 'Unknown error'), 'error');
        });
    }

    /* ── Load languages list (lazy) ───────────────────── */
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
                langsLoaded = true;
            })
            .catch(function () {
                select.innerHTML = '';
                FALLBACK_LANGS.forEach(function (code) {
                    var opt = document.createElement('option');
                    opt.value = opt.textContent = code;
                    select.appendChild(opt);
                });
                langsLoaded = true;
            });
    }

    /* ── Init ─────────────────────────────────────────── */
    function init() {
        reloadConfig();

        // Hide all non-active tab-content panes on load
        var card = document.getElementById('seoMetaFormCard');
        if (card) {
            card.querySelectorAll('.tab-content').forEach(function (pane) {
                if (!pane.classList.contains('active')) pane.style.display = 'none';
            });

            // Tab button clicks
            card.addEventListener('click', function (e) {
                var tabBtn = e.target.closest('.tab-btn');
                if (!tabBtn) return;
                var tabName = tabBtn.dataset.tab;
                if (!tabName) return;
                switchTab(tabName);
                if (tabName === 'sm-translations') {
                    var transIdEl = document.getElementById('transSeoMetaId');
                    if (transIdEl && transIdEl.value) loadTranslations(transIdEl.value);
                }
            });
        }

        /* Add button */
        var btnAdd = document.getElementById('btnAddSeoMeta');
        if (btnAdd) btnAdd.addEventListener('click', function () { openAddForm(); });

        /* Close / Cancel form */
        var btnClose = document.getElementById('btnCloseForm');
        if (btnClose) btnClose.addEventListener('click', function () { hideFormCard(); });

        var btnCancel = document.getElementById('btnCancelForm');
        if (btnCancel) btnCancel.addEventListener('click', function () { hideFormCard(); });

        /* Form submit (General tab) */
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

        /* "Add Translation" panel buttons */
        var btnShowAdd = document.getElementById('btnShowAddTransForm');
        if (btnShowAdd) btnShowAdd.addEventListener('click', function () { showAddTransPanel(); });

        var btnSaveNew = document.getElementById('btnSaveNewTrans');
        if (btnSaveNew) btnSaveNew.addEventListener('click', function () { saveNewTranslation(); });

        var btnCancelAdd = document.getElementById('btnCancelAddTrans');
        if (btnCancelAdd) btnCancelAdd.addEventListener('click', function () { hideAddTransPanel(); });

        /* Delegated table-row events */
        document.addEventListener('click', function (e) {
            // Main table
            var editBtn = e.target.closest('.edit-btn');
            if (editBtn) { editSeoMeta(editBtn.dataset.id, 'sm-general'); return; }

            var transBtn = e.target.closest('.translations-btn');
            if (transBtn) { editSeoMeta(transBtn.dataset.id, 'sm-translations'); return; }

            var deleteBtn = e.target.closest('.delete-btn');
            if (deleteBtn) { deleteSeoMeta(deleteBtn.dataset.id); return; }

            // Translations table – Edit/Save/Cancel/Delete
            var editTransBtn = e.target.closest('.edit-trans-btn');
            if (editTransBtn) { toggleTransDetailRow(editTransBtn.dataset.id); return; }

            var saveTransBtn = e.target.closest('.save-trans-edit-btn');
            if (saveTransBtn) { updateTranslation(saveTransBtn.dataset.id); return; }

            var cancelTransBtn = e.target.closest('.cancel-trans-edit-btn');
            if (cancelTransBtn) {
                var detRow = document.getElementById('trans-detail-' + cancelTransBtn.dataset.id);
                if (detRow) detRow.remove();
                return;
            }

            var delTransBtn = e.target.closest('.delete-translation-btn');
            if (delTransBtn) { deleteTranslation(delTransBtn.dataset.id); return; }
        });

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