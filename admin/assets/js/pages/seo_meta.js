/**
 * /admin/assets/js/pages/seo_meta.js
 * SEO Meta Management — Production v2.0
 *
 * ─ التغييرات عن النسخة السابقة ─────────────────────────────
 * • btn-info → btn-primary في كل مكان
 * • showState() تُدير loading/empty/error/table
 * • credentials: 'same-origin' على كل fetch
 * • ESC يُغلق form card
 * • notify() بـ sm- prefix (يتطابق مع CSS)
 * • كل الألوان من CSS vars — لا hardcoded
 * ─────────────────────────────────────────────────────────────
 */
(function () {
    'use strict';

    // ════════════════════════════════════════════════════════
    // CONFIG
    // ════════════════════════════════════════════════════════
    var CFG, CSRF, STRINGS, CAN_CREATE, CAN_EDIT, CAN_DELETE;
    var FALLBACK_LANGS = ['ar', 'en', 'fr', 'tr', 'ur', 'de', 'es'];
    var PER_PAGE = 25;
    var currentPage    = 1;
    var currentFilters = {};
    var translationsCache = {};
    var langsLoaded = false;

    function reloadConfig() {
        CFG        = window.SEO_META_CONFIG || {};
        CSRF       = CFG.csrfToken || '';
        STRINGS    = CFG.strings   || {};
        CAN_CREATE = !!CFG.canCreate;
        CAN_EDIT   = !!CFG.canEdit;
        CAN_DELETE = !!CFG.canDelete;
    }

    // ════════════════════════════════════════════════════════
    // HELPERS
    // ════════════════════════════════════════════════════════
    function t(key, fallback) {
        var keys = key.split('.');
        var val  = STRINGS;
        for (var i = 0; i < keys.length; i++) {
            if (val && typeof val === 'object' && keys[i] in val) {
                val = val[keys[i]];
            } else {
                return fallback || key;
            }
        }
        return typeof val === 'string' ? val : (fallback || key);
    }

    function esc(str) {
        if (str == null) return '';
        var d = document.createElement('div');
        d.appendChild(document.createTextNode(String(str)));
        return d.innerHTML;
    }

    function formatDate(str) {
        if (!str) return '—';
        try {
            return new Date(str).toLocaleDateString(undefined, {
                year: 'numeric', month: 'short', day: 'numeric'
            });
        } catch (_) { return str; }
    }

    // ════════════════════════════════════════════════════════
    // TOAST NOTIFICATIONS  (sm- prefix → matches CSS)
    // ════════════════════════════════════════════════════════
    function notify(message, type) {
        type = type || 'info';
        var container = document.getElementById('smNotifications');
        if (!container) {
            container = document.createElement('div');
            container.id = 'smNotifications';
            container.className = 'sm-notifications';
            var page = document.getElementById('seoMetaPageContainer');
            (page || document.body).insertBefore(container, (page || document.body).firstChild);
        }
        var toast = document.createElement('div');
        toast.className = 'sm-toast sm-toast-' + type;
        toast.setAttribute('role', 'alert');

        var msg = document.createElement('span');
        msg.textContent = message;
        toast.appendChild(msg);

        var close = document.createElement('button');
        close.className = 'sm-toast-close';
        close.setAttribute('aria-label', 'Close');
        close.textContent = '\u00d7';
        close.addEventListener('click', function () { toast.remove(); });
        toast.appendChild(close);

        container.appendChild(toast);
        setTimeout(function () { if (toast.parentNode) toast.remove(); }, 4500);
    }

    // ════════════════════════════════════════════════════════
    // TABLE STATE
    // ════════════════════════════════════════════════════════
    function showState(state, errorMsg) {
        var loading   = document.getElementById('smLoading');
        var empty     = document.getElementById('smEmpty');
        var error     = document.getElementById('smError');
        var container = document.getElementById('smTableContainer');

        [loading, empty, error, container].forEach(function (el) {
            if (el) el.style.display = 'none';
        });

        switch (state) {
            case 'loading': if (loading)   loading.style.display   = 'flex';  break;
            case 'empty':   if (empty)     empty.style.display     = 'flex';  break;
            case 'error':
                if (error) error.style.display = 'flex';
                if (errorMsg) {
                    var p = document.getElementById('smErrorMessage');
                    if (p) p.textContent = errorMsg;
                }
                break;
            default:        if (container) container.style.display = 'block'; break;
        }
    }

    // ════════════════════════════════════════════════════════
    // FORM CARD HELPERS
    // ════════════════════════════════════════════════════════
    function showFormCard() {
        var card = document.getElementById('seoMetaFormCard');
        if (card) {
            card.style.display = '';
            setTimeout(function () {
                card.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }, 50);
        }
    }

    function hideFormCard() {
        var card = document.getElementById('seoMetaFormCard');
        if (card) card.style.display = 'none';
        var form = document.getElementById('seoMetaForm');
        if (form) form.reset();
        var idEl = document.getElementById('seoMetaId');
        if (idEl) idEl.value = '';
        switchTab('sm-general');
        hideAddTransPanel();
        var tabBtn = document.getElementById('tabTranslationsBtn');
        if (tabBtn) tabBtn.style.display = 'none';
    }

    // ════════════════════════════════════════════════════════
    // TABS
    // ════════════════════════════════════════════════════════
    function switchTab(tabName) {
        var card = document.getElementById('seoMetaFormCard');
        if (!card) return;
        card.querySelectorAll('.tab-btn').forEach(function (btn) {
            var active = btn.dataset.tab === tabName;
            btn.classList.toggle('active', active);
            btn.setAttribute('aria-selected', String(active));
        });
        card.querySelectorAll('.tab-content').forEach(function (pane) {
            var active = pane.id === 'tab-' + tabName;
            pane.classList.toggle('active', active);
            pane.style.display = active ? '' : 'none';
        });
    }

    // ════════════════════════════════════════════════════════
    // OPEN ADD FORM
    // ════════════════════════════════════════════════════════
    function openAddForm() {
        var form  = document.getElementById('seoMetaForm');
        if (form) form.reset();
        var idEl  = document.getElementById('seoMetaId');
        if (idEl) idEl.value = '';
        var title = document.getElementById('seoMetaFormTitle');
        if (title) title.textContent = t('modal.add_title', 'Add SEO Record');
        var tabBtn = document.getElementById('tabTranslationsBtn');
        if (tabBtn) tabBtn.style.display = 'none';
        switchTab('sm-general');
        showFormCard();
    }

    // ════════════════════════════════════════════════════════
    // OPEN EDIT FORM
    // ════════════════════════════════════════════════════════
    function editSeoMeta(id, activeTab) {
        activeTab = activeTab || 'sm-general';
        fetch('/api/seo_meta?id=' + encodeURIComponent(id), { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (!d.success || !d.data) {
                    notify(d.message || t('unknown_error', 'Unknown error'), 'error');
                    return;
                }
                var rec = d.data;
                setField('seoMetaId',     rec.id);
                setField('smEntityType',  rec.entity_type);
                setField('smEntityId',    rec.entity_id);
                setField('smCanonicalUrl',rec.canonical_url);
                setField('smRobots',      rec.robots || 'index,follow');
                setField('smSchemaMarkup',rec.schema_markup);

                var tabBtn = document.getElementById('tabTranslationsBtn');
                if (tabBtn) tabBtn.style.display = '';
                var title = document.getElementById('seoMetaFormTitle');
                if (title) title.textContent = t('modal.edit_title', 'Edit SEO Record');
                var transIdEl = document.getElementById('transSeoMetaId');
                if (transIdEl) transIdEl.value = rec.id;

                hideAddTransPanel();
                switchTab(activeTab);
                showFormCard();

                if (activeTab === 'sm-translations') {
                    loadTranslations(rec.id);
                }
            })
            .catch(function (err) {
                console.error('[SeoMeta] editSeoMeta error:', err);
                notify(t('network_error', 'Network error'), 'error');
            });
    }

    function setField(id, value) {
        var el = document.getElementById(id);
        if (!el) return;
        el.value = value != null ? value : '';
    }

    // ════════════════════════════════════════════════════════
    // SAVE SEO META
    // ════════════════════════════════════════════════════════
    function saveSeoMeta(formData) {
        var editId = document.getElementById('seoMetaId').value;
        var body = {
            entity_type:   formData.get('entity_type'),
            entity_id:     formData.get('entity_id'),
            canonical_url: formData.get('canonical_url'),
            robots:        formData.get('robots'),
            schema_markup: formData.get('schema_markup'),
        };
        if (editId) body.id = editId;

        fetch('/api/seo_meta', {
            method:  editId ? 'PUT' : 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
            credentials: 'same-origin',
            body: JSON.stringify(body),
        })
        .then(function (r) { return r.json(); })
        .then(function (d) {
            if (d.success) {
                hideFormCard();
                notify(t('saved', 'Saved successfully'), 'success');
                loadSeoMeta(currentFilters);
            } else {
                notify(d.message || t('unknown_error', 'Unknown error'), 'error');
            }
        })
        .catch(function (err) {
            console.error('[SeoMeta] saveSeoMeta error:', err);
            notify(t('network_error', 'Network error'), 'error');
        });
    }

    // ════════════════════════════════════════════════════════
    // DELETE SEO META
    // ════════════════════════════════════════════════════════
    function deleteSeoMeta(id) {
        if (!confirm(t('confirm_delete', 'Are you sure you want to delete this SEO record?'))) return;
        fetch('/api/seo_meta?id=' + encodeURIComponent(id), {
            method:  'DELETE',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
            credentials: 'same-origin',
        })
        .then(function (r) { return r.json(); })
        .then(function (d) {
            if (d.success) {
                notify(t('deleted', 'Deleted successfully'), 'success');
                loadSeoMeta(currentFilters);
            } else {
                notify(d.message || t('delete_failed', 'Delete failed'), 'error');
            }
        })
        .catch(function (err) {
            console.error('[SeoMeta] deleteSeoMeta error:', err);
            notify(t('network_error', 'Network error'), 'error');
        });
    }

    // ════════════════════════════════════════════════════════
    // LOAD SEO META LIST
    // ════════════════════════════════════════════════════════
    function loadSeoMeta(params) {
        params = params || {};
        currentPage    = params.page || 1;
        currentFilters = params;

        var query = [];
        if (params.search)      query.push('search='      + encodeURIComponent(params.search));
        if (params.entity_type) query.push('entity_type=' + encodeURIComponent(params.entity_type));
        query.push('limit='  + PER_PAGE);
        query.push('offset=' + ((currentPage - 1) * PER_PAGE));

        var url = '/api/seo_meta' + (query.length ? '?' + query.join('&') : '');
        showState('loading');

        fetch(url, { credentials: 'same-origin' })
            .then(function (r) { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
            .then(function (d) {
                var tbody = document.getElementById('seoMetaBody');
                if (!tbody) return;
                tbody.innerHTML = '';

                var items = (d.data && d.data.items) ? d.data.items : [];
                var total = (d.data && d.data.meta)  ? d.data.meta.total : items.length;

                if (!d.success || items.length === 0) {
                    showState('empty');
                    renderPagination(1, 0);
                    return;
                }

                showState('table');
                items.forEach(function (item) {
                    var tr = document.createElement('tr');
                    tr.innerHTML =
                        '<td>' + esc(item.id) + '</td>' +
                        '<td><span class="badge badge-info">' + esc(item.entity_type) + '</span></td>' +
                        '<td>' + esc(item.entity_id) + '</td>' +
                        '<td class="sm-url-cell" title="' + esc(item.canonical_url || '') + '">' +
                            esc(item.canonical_url || '\u2014') +
                        '</td>' +
                        '<td>' + esc(item.robots || '') + '</td>' +
                        '<td>' + esc(formatDate(item.created_at)) + '</td>' +
                        '<td>' +
                            '<div class="table-actions">' +
                            (CAN_EDIT
                                ? '<button class="btn btn-sm btn-primary edit-btn" data-id="' + esc(item.id) + '" aria-label="' + t('table.edit','Edit') + '">' +
                                  '<i class="fas fa-edit" aria-hidden="true"></i></button> '
                                : '') +
                            (CAN_EDIT
                                ? '<button class="btn btn-sm btn-secondary translations-btn" data-id="' + esc(item.id) + '" aria-label="' + t('table.translations','Translations') + '">' +
                                  '<i class="fas fa-language" aria-hidden="true"></i></button> '
                                : '') +
                            (CAN_DELETE
                                ? '<button class="btn btn-sm btn-danger delete-btn" data-id="' + esc(item.id) + '" aria-label="' + t('table.delete','Delete') + '">' +
                                  '<i class="fas fa-trash" aria-hidden="true"></i></button>'
                                : '') +
                            '</div>' +
                        '</td>';
                    tbody.appendChild(tr);
                });

                renderPagination(currentPage, total);
            })
            .catch(function (err) {
                console.error('[SeoMeta] loadSeoMeta error:', err);
                showState('error', err.message || t('unknown_error', 'Failed to load'));
            });
    }

    // ════════════════════════════════════════════════════════
    // PAGINATION
    // ════════════════════════════════════════════════════════
    function renderPagination(page, total) {
        var totalPages = Math.max(1, Math.ceil(total / PER_PAGE));
        var start = total > 0 ? (page - 1) * PER_PAGE + 1 : 0;
        var end   = Math.min(page * PER_PAGE, total);

        var infoEl = document.getElementById('paginationInfo');
        if (infoEl) {
            infoEl.textContent = total > 0
                ? start + '\u2013' + end + ' ' + t('pagination.of', 'of') + ' ' + total
                : t('pagination.no_results', 'No results');
        }

        var pagEl = document.getElementById('pagination');
        if (!pagEl) return;
        pagEl.innerHTML = '';
        if (totalPages <= 1) return;

        function makeBtn(label, targetPage, active, disabled) {
            var btn = document.createElement('button');
            btn.className = 'pagination-btn' + (active ? ' active' : '');
            btn.innerHTML = label;
            btn.disabled  = !!disabled;
            if (!disabled) btn.addEventListener('click', function () { goToPage(targetPage); });
            return btn;
        }

        pagEl.appendChild(makeBtn('&laquo;', page - 1, false, page <= 1));
        for (var i = 1; i <= totalPages; i++) {
            if (i === 1 || i === totalPages || (i >= page - 2 && i <= page + 2)) {
                pagEl.appendChild(makeBtn(String(i), i, i === page, i === page));
            } else if (i === page - 3 || i === page + 3) {
                var sp = document.createElement('span');
                sp.className = 'pagination-dots';
                sp.textContent = '\u2026';
                pagEl.appendChild(sp);
            }
        }
        pagEl.appendChild(makeBtn('&raquo;', page + 1, false, page >= totalPages));
    }

    function goToPage(page) {
        var params = {};
        for (var k in currentFilters) { if (k !== 'page') params[k] = currentFilters[k]; }
        params.page = page;
        loadSeoMeta(params);
    }

    // ════════════════════════════════════════════════════════
    // FILTERS
    // ════════════════════════════════════════════════════════
    function applyFilters() {
        var search     = document.getElementById('filterSearch');
        var entityType = document.getElementById('filterEntityType');
        loadSeoMeta({
            search:      search     ? search.value     : '',
            entity_type: entityType ? entityType.value : '',
            page: 1,
        });
    }

    function clearFilters() {
        var s = document.getElementById('filterSearch');
        var e = document.getElementById('filterEntityType');
        if (s) s.value = '';
        if (e) e.value = '';
        loadSeoMeta({ page: 1 });
    }

    // ════════════════════════════════════════════════════════
    // TRANSLATIONS
    // ════════════════════════════════════════════════════════
    function loadTranslations(seoMetaId) {
        fetch('/api/seo_meta/translations?seo_meta_id=' + encodeURIComponent(seoMetaId), {
            credentials: 'same-origin',
        })
        .then(function (r) { return r.json(); })
        .then(function (d) {
            translationsCache = {};
            var tbody = document.getElementById('translationsBody');
            if (!tbody) return;
            tbody.innerHTML = '';

            var items = [];
            if (d.data && d.data.items)    items = d.data.items;
            else if (Array.isArray(d.data)) items = d.data;

            if (items.length === 0) {
                var tr = document.createElement('tr');
                var td = document.createElement('td');
                td.setAttribute('colspan', '4');
                td.style.textAlign = 'center';
                td.textContent = t('no_translations', 'No translations found');
                tr.appendChild(td);
                tbody.appendChild(tr);
            } else {
                items.forEach(function (item) {
                    translationsCache[item.id] = item;
                    tbody.appendChild(buildTransSummaryRow(item));
                });
            }
        })
        .catch(function (err) {
            console.error('[SeoMeta] loadTranslations error:', err);
            notify(t('unknown_error', 'Unknown error'), 'error');
        });
    }

    function buildTransSummaryRow(item) {
        var tr = document.createElement('tr');
        tr.id = 'trans-row-' + item.id;
        tr.innerHTML =
            '<td><span class="lang-badge">' + esc(item.language_code) + '</span></td>' +
            '<td>' + esc(item.meta_title || '') + '</td>' +
            '<td>' + esc(item.og_title || '') + '</td>' +
            '<td>' +
                '<div class="table-actions">' +
                '<button type="button" class="btn btn-sm btn-primary edit-trans-btn" data-id="' + esc(item.id) + '">' +
                    '<i class="fas fa-edit" aria-hidden="true"></i>' +
                '</button> ' +
                '<button type="button" class="btn btn-sm btn-danger delete-translation-btn" data-id="' + esc(item.id) + '">' +
                    '<i class="fas fa-trash" aria-hidden="true"></i>' +
                '</button>' +
                '</div>' +
            '</td>';
        return tr;
    }

    function toggleTransDetailRow(id) {
        var existing = document.getElementById('trans-detail-' + id);
        if (existing) { existing.remove(); return; }

        var item       = translationsCache[id];
        var summaryRow = document.getElementById('trans-row-' + id);
        if (!item || !summaryRow) return;

        var detailTr = document.createElement('tr');
        detailTr.id        = 'trans-detail-' + id;
        detailTr.className = 'trans-detail-row';
        detailTr.innerHTML =
            '<td colspan="4">' +
            '<div class="sm-trans-edit-form">' +
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
                        '<i class="fas fa-save" aria-hidden="true"></i> ' + t('form.save', 'Save') +
                    '</button> ' +
                    '<button type="button" class="btn btn-sm btn-secondary cancel-trans-edit-btn" data-id="' + esc(id) + '">' +
                        t('form.cancel', 'Cancel') +
                    '</button>' +
                '</div>' +
            '</div>' +
            '</td>';
        summaryRow.after(detailTr);
    }

    function updateTranslation(id) {
        var detailRow = document.getElementById('trans-detail-' + id);
        if (!detailRow) return;
        var item       = translationsCache[id];
        var transIdEl  = document.getElementById('transSeoMetaId');
        var seoMetaId  = transIdEl ? transIdEl.value : '';

        function gvc(cls) {
            var el = detailRow.querySelector('.' + cls);
            return el ? el.value : '';
        }

        fetch('/api/seo_meta/translations', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
            credentials: 'same-origin',
            body: JSON.stringify({
                seo_meta_id:      seoMetaId,
                language_code:    item.language_code,
                meta_title:       gvc('tei-meta-title'),
                meta_description: gvc('tei-meta-desc'),
                meta_keywords:    gvc('tei-meta-keywords'),
                og_title:         gvc('tei-og-title'),
                og_description:   gvc('tei-og-desc'),
                og_image:         gvc('tei-og-image'),
            }),
        })
        .then(function (r) { return r.json(); })
        .then(function (d) {
            if (d.success) {
                notify(t('saved', 'Saved successfully'), 'success');
                loadTranslations(seoMetaId);
            } else {
                notify(d.message || t('unknown_error', 'Unknown error'), 'error');
            }
        })
        .catch(function (err) {
            console.error('[SeoMeta] updateTranslation error:', err);
            notify(t('network_error', 'Network error'), 'error');
        });
    }

    function deleteTranslation(id) {
        if (!confirm(t('confirm_delete_translation', 'Delete this translation?'))) return;
        var transIdEl = document.getElementById('transSeoMetaId');
        var seoMetaId = transIdEl ? transIdEl.value : '';

        fetch('/api/seo_meta/translations?id=' + encodeURIComponent(id), {
            method:  'DELETE',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
            credentials: 'same-origin',
        })
        .then(function (r) { return r.json(); })
        .then(function (d) {
            if (d.success) {
                notify(t('deleted', 'Deleted successfully'), 'success');
                if (seoMetaId) loadTranslations(seoMetaId);
            } else {
                notify(d.message || t('delete_failed', 'Delete failed'), 'error');
            }
        })
        .catch(function (err) {
            console.error('[SeoMeta] deleteTranslation error:', err);
            notify(t('network_error', 'Network error'), 'error');
        });
    }

    // ── Add Translation Panel ─────────────────────────────
    function showAddTransPanel() {
        var panel = document.getElementById('addTransPanel');
        if (!panel) return;
        panel.style.display = '';
        if (!langsLoaded) loadLanguages();
    }

    function hideAddTransPanel() {
        var panel = document.getElementById('addTransPanel');
        if (panel) panel.style.display = 'none';
        ['transMetaTitle','transOgTitle','transMetaKeywords',
         'transMetaDescription','transOgDescription','transOgImage'].forEach(function (id) {
            var el = document.getElementById(id);
            if (el) el.value = '';
        });
    }

    function saveNewTranslation() {
        var transIdEl = document.getElementById('transSeoMetaId');
        var seoMetaId = transIdEl ? transIdEl.value : '';
        if (!seoMetaId) { notify(t('unknown_error', 'Unknown error'), 'error'); return; }

        function gv(id) { var el = document.getElementById(id); return el ? el.value : ''; }

        fetch('/api/seo_meta/translations', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
            credentials: 'same-origin',
            body: JSON.stringify({
                seo_meta_id:      seoMetaId,
                language_code:    gv('transLangCode'),
                meta_title:       gv('transMetaTitle'),
                meta_description: gv('transMetaDescription'),
                meta_keywords:    gv('transMetaKeywords'),
                og_title:         gv('transOgTitle'),
                og_description:   gv('transOgDescription'),
                og_image:         gv('transOgImage'),
            }),
        })
        .then(function (r) { return r.json(); })
        .then(function (d) {
            if (d.success) {
                notify(t('saved', 'Saved successfully'), 'success');
                hideAddTransPanel();
                loadTranslations(seoMetaId);
            } else {
                notify(d.message || t('unknown_error', 'Unknown error'), 'error');
            }
        })
        .catch(function (err) {
            console.error('[SeoMeta] saveNewTranslation error:', err);
            notify(t('network_error', 'Network error'), 'error');
        });
    }

    // ── Load Languages ────────────────────────────────────
    function loadLanguages() {
        var select = document.getElementById('transLangCode');
        if (!select) return;
        fetch('/api/languages', { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                select.innerHTML = '';
                var items = Array.isArray(d.data) ? d.data : (d.data && d.data.items ? d.data.items : []);
                if (!items.length) items = FALLBACK_LANGS.map(function (c) { return { code: c }; });
                items.forEach(function (lang) {
                    var opt = document.createElement('option');
                    opt.value       = lang.code || lang.language_code || lang.id;
                    opt.textContent = lang.native_name || lang.name || lang.code || lang.id;
                    select.appendChild(opt);
                });
                langsLoaded = true;
            })
            .catch(function () {
                var select2 = document.getElementById('transLangCode');
                if (!select2) return;
                select2.innerHTML = '';
                FALLBACK_LANGS.forEach(function (code) {
                    var opt = document.createElement('option');
                    opt.value = opt.textContent = code;
                    select2.appendChild(opt);
                });
                langsLoaded = true;
            });
    }

    // ════════════════════════════════════════════════════════
    // INIT
    // ════════════════════════════════════════════════════════
    function init() {
        reloadConfig();

        // ESC closes form card
        document.addEventListener('keydown', function (e) {
            if (e.key !== 'Escape') return;
            var card = document.getElementById('seoMetaFormCard');
            if (card && card.style.display !== 'none') hideFormCard();
        });

        // Tab clicks
        var card = document.getElementById('seoMetaFormCard');
        if (card) {
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
            // Init hidden tab panes
            card.querySelectorAll('.tab-content:not(.active)').forEach(function (pane) {
                pane.style.display = 'none';
            });
        }

        // Add button
        document.getElementById('btnAddSeoMeta')?.addEventListener('click', openAddForm);
        document.getElementById('btnAddSeoMetaEmpty')?.addEventListener('click', openAddForm);

        // Close / Cancel form
        document.getElementById('btnCloseForm')?.addEventListener('click', hideFormCard);
        document.getElementById('btnCancelForm')?.addEventListener('click', hideFormCard);

        // Form submit
        var form = document.getElementById('seoMetaForm');
        if (form) form.addEventListener('submit', function (e) {
            e.preventDefault();
            saveSeoMeta(new FormData(this));
        });

        // Filters
        document.getElementById('btnFilter')?.addEventListener('click', applyFilters);
        document.getElementById('btnClearFilters')?.addEventListener('click', clearFilters);
        document.getElementById('filterSearch')?.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') { e.preventDefault(); applyFilters(); }
        });

        // Retry
        document.getElementById('btnRetry')?.addEventListener('click', function () {
            loadSeoMeta(currentFilters);
        });

        // Translations panel
        document.getElementById('btnShowAddTransForm')?.addEventListener('click', showAddTransPanel);
        document.getElementById('btnSaveNewTrans')?.addEventListener('click', saveNewTranslation);
        document.getElementById('btnCancelAddTrans')?.addEventListener('click', hideAddTransPanel);

        // Event delegation
        document.addEventListener('click', function (e) {
            var editBtn = e.target.closest('.edit-btn');
            if (editBtn) { editSeoMeta(editBtn.dataset.id, 'sm-general'); return; }

            var transBtn = e.target.closest('.translations-btn');
            if (transBtn) { editSeoMeta(transBtn.dataset.id, 'sm-translations'); return; }

            var deleteBtn = e.target.closest('.delete-btn');
            if (deleteBtn) { deleteSeoMeta(deleteBtn.dataset.id); return; }

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

    // ════════════════════════════════════════════════════════
    // REGISTER
    // ════════════════════════════════════════════════════════
    window.page = { run: init };
    if (window.Admin && window.Admin.page && typeof window.Admin.page.register === 'function') {
        window.Admin.page.register('seo_meta', init);
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

}());