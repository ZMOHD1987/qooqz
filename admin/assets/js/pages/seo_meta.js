(function () {
    'use strict';

    var CFG, CSRF, STRINGS, CAN_CREATE, CAN_EDIT, CAN_DELETE;
    var FALLBACK_LANGS = ['ar', 'en', 'fr', 'tr', 'ur', 'de', 'es'];
    var PER_PAGE = 25;
    var currentPage = 1;
    var currentFilters = {};
    var currentEditId = null; // null = new record mode

    function reloadConfig() {
        CFG         = window.SEO_META_CONFIG || {};
        CSRF        = CFG.csrfToken  || '';
        STRINGS     = CFG.strings    || {};
        CAN_CREATE  = !!CFG.canCreate;
        CAN_EDIT    = !!CFG.canEdit;
        CAN_DELETE  = !!CFG.canDelete;
    }
    reloadConfig();

    /* ── Translation helper ─────────────────────────────── */
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

    /* ── XSS escape ────────────────────────────────────── */
    function esc(str) {
        if (str == null) return '';
        var d = document.createElement('div');
        d.appendChild(document.createTextNode(String(str)));
        return d.innerHTML;
    }

    /* ── DOM value helpers ─────────────────────────────── */
    function getVal(id) {
        var el = document.getElementById(id);
        return el ? el.value.trim() : '';
    }
    function setVal(id, val) {
        var el = document.getElementById(id);
        if (el) el.value = (val != null) ? val : '';
    }

    /* ── Toast notifications ───────────────────────────── */
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
        setTimeout(function () { toast.remove(); }, 4500);
    }

    /* ── Modal helpers (translations history modal) ────── */
    function openModal(id)  { var el = document.getElementById(id); if (el) el.style.display = 'block'; }
    function closeModal(id) { var el = document.getElementById(id); if (el) el.style.display = 'none';  }

    /* ─────────────────────────────────────────────────────
       INLINE FORM (Edit-First UX)
       ───────────────────────────────────────────────────── */

    /** Clear only the translation fields in the inline form */
    function clearTranslationFields() {
        ['smMetaTitle', 'smMetaDesc', 'smMetaKeywords', 'smOgTitle', 'smOgDesc', 'smOgImage']
            .forEach(function (id) { setVal(id, ''); });
    }

    /** Reset entire form to "New Record" state */
    function resetForm() {
        currentEditId = null;
        var form = document.getElementById('seoMetaForm');
        if (form) form.reset();
        setVal('seoMetaId', '');
        clearTranslationFields();

        var title = document.getElementById('seoFormTitle');
        if (title) title.textContent = t('modal.add_title', 'Add SEO Record');

        var badge = document.getElementById('seoFormModeBadge');
        if (badge) {
            badge.textContent = t('form.mode_new', 'New');
            badge.className = 'seo-form-mode';
        }
    }

    /** Populate the inline form with a SEO record */
    function loadIntoForm(rec) {
        currentEditId = rec.id;
        setVal('seoMetaId',     rec.id);
        setVal('smEntityType',  rec.entity_type  || 'product');
        setVal('smEntityId',    rec.entity_id    || '');
        setVal('smCanonicalUrl',rec.canonical_url || '');
        setVal('smRobots',      rec.robots       || 'index,follow');
        setVal('smSchemaMarkup',rec.schema_markup || '');

        var title = document.getElementById('seoFormTitle');
        if (title) title.textContent = t('modal.edit_title', 'Edit SEO Record');

        var badge = document.getElementById('seoFormModeBadge');
        if (badge) {
            badge.textContent = t('form.mode_editing', 'Editing');
            badge.className   = 'seo-form-mode is-editing';
        }

        // Load translation for currently selected language
        var lang = getVal('smTransLang');
        loadTranslationIntoForm(rec.id, lang);

        // Scroll form into view (smooth, only if outside viewport)
        var panel = document.getElementById('seoFormPanel');
        if (panel) panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    /** Load a single translation (for selected language) into the inline form */
    function loadTranslationIntoForm(seoMetaId, langCode) {
        clearTranslationFields();
        if (!seoMetaId || !langCode) return;

        fetch('/api/seo_meta/translations?seo_meta_id=' + encodeURIComponent(seoMetaId) +
              '&language_code=' + encodeURIComponent(langCode))
            .then(function (r) { return r.json(); })
            .then(function (d) {
                var rec = null;
                if (d.data && d.data.items && d.data.items.length > 0) {
                    rec = d.data.items[0];
                } else if (Array.isArray(d.data) && d.data.length > 0) {
                    rec = d.data[0];
                } else if (d.data && d.data.id) {
                    rec = d.data;
                }
                if (rec) {
                    setVal('smMetaTitle',    rec.meta_title);
                    setVal('smMetaDesc',     rec.meta_description);
                    setVal('smMetaKeywords', rec.meta_keywords);
                    setVal('smOgTitle',      rec.og_title);
                    setVal('smOgDesc',       rec.og_description);
                    setVal('smOgImage',      rec.og_image);
                }
            })
            .catch(function () { /* non-fatal */ });
    }

    /* ─────────────────────────────────────────────────────
       LOAD SEO META LIST
       ───────────────────────────────────────────────────── */
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
                        // Highlight row if it is the one currently being edited
                        if (currentEditId && String(item.id) === String(currentEditId)) {
                            tr.className = 'seo-row-active';
                        }
                        tr.innerHTML =
                            '<td>' + esc(item.id) + '</td>' +
                            '<td><span class="badge badge-info">' + esc(item.entity_type) + '</span></td>' +
                            '<td>' + esc(item.entity_id) + '</td>' +
                            '<td class="seo-url-cell"><span title="' + esc(item.canonical_url || '') + '">' + esc(item.canonical_url || '—') + '</span></td>' +
                            '<td>' + esc(item.robots || '') + '</td>' +
                            '<td class="seo-row-actions">' +
                                (CAN_EDIT   ? '<button class="btn btn-sm btn-info edit-btn"         data-id="' + esc(item.id) + '">' + t('table.edit',         'Edit')         + '</button> ' : '') +
                                             '<button class="btn btn-sm btn-secondary translations-btn" data-id="' + esc(item.id) + '">' + t('table.translations', 'Translations') + '</button> ' +
                                (CAN_DELETE ? '<button class="btn btn-sm btn-danger delete-btn"      data-id="' + esc(item.id) + '">' + t('table.delete',       'Delete')       + '</button>'  : '') +
                            '</td>';
                        tbody.appendChild(tr);
                    });
                } else {
                    var tr = document.createElement('tr');
                    var td = document.createElement('td');
                    td.setAttribute('colspan', '6');
                    td.className = 'text-center';
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

    /* ─────────────────────────────────────────────────────
       PAGINATION
       ───────────────────────────────────────────────────── */
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
        prevBtn.className = 'pagination-btn';
        prevBtn.innerHTML = '&laquo;';
        prevBtn.disabled  = (page <= 1);
        prevBtn.addEventListener('click', function () { goToPage(page - 1); });
        pagEl.appendChild(prevBtn);

        for (var i = 1; i <= totalPages; i++) {
            if (i === 1 || i === totalPages || (i >= page - 2 && i <= page + 2)) {
                var btn = document.createElement('button');
                btn.className = 'pagination-btn' + (i === page ? ' active' : '');
                btn.textContent = i;
                (function (pg) { btn.addEventListener('click', function () { goToPage(pg); }); })(i);
                pagEl.appendChild(btn);
            } else if (i === page - 3 || i === page + 3) {
                var sp = document.createElement('span');
                sp.className   = 'pagination-ellipsis';
                sp.textContent = '…';
                pagEl.appendChild(sp);
            }
        }

        var nextBtn = document.createElement('button');
        nextBtn.className = 'pagination-btn';
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

    /* ─────────────────────────────────────────────────────
       EDIT SEO RECORD
       ───────────────────────────────────────────────────── */
    function editSeoMeta(id) {
        fetch('/api/seo_meta?id=' + encodeURIComponent(id))
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (d.success && d.data) {
                    loadIntoForm(d.data);
                } else {
                    showNotification(d.message || t('unknown_error', 'Unknown error'), 'error');
                }
            })
            .catch(function () {
                showNotification(t('unknown_error', 'Unknown error'), 'error');
            });
    }

    /* ─────────────────────────────────────────────────────
       SAVE (main record + optional translation)
       ───────────────────────────────────────────────────── */
    function saveSeoMeta(formData) {
        var id     = getVal('seoMetaId');
        var method = id ? 'PUT' : 'POST';

        // Collect translation fields
        var langValue = getVal('smTransLang');
        var metaTitle = getVal('smMetaTitle');
        var metaDesc  = getVal('smMetaDesc');
        var metaKw    = getVal('smMetaKeywords');
        var ogTitle   = getVal('smOgTitle');
        var ogDesc    = getVal('smOgDesc');
        var ogImage   = getVal('smOgImage');
        var hasTranslation = !!(metaTitle || metaDesc || metaKw || ogTitle || ogDesc || ogImage);

        var body = {
            entity_type:   formData.get('entity_type'),
            entity_id:     formData.get('entity_id'),
            canonical_url: formData.get('canonical_url'),
            robots:        formData.get('robots'),
            schema_markup: formData.get('schema_markup')
        };
        if (id) body.id = id;

        fetch('/api/seo_meta', {
            method:  method,
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
            body:    JSON.stringify(body)
        })
        .then(function (r) { return r.json(); })
        .then(function (d) {
            if (!d.success) {
                showNotification(d.message || t('unknown_error', 'Unknown error'), 'error');
                return;
            }
            var recordId = (d.data && d.data.id) ? d.data.id : id;

            // Save translation if any translation field was filled
            if (hasTranslation && langValue && recordId) {
                fetch('/api/seo_meta/translations', {
                    method:  'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
                    body:    JSON.stringify({
                        seo_meta_id:      recordId,
                        language_code:    langValue,
                        meta_title:       metaTitle,
                        meta_description: metaDesc,
                        meta_keywords:    metaKw,
                        og_title:         ogTitle,
                        og_description:   ogDesc,
                        og_image:         ogImage
                    })
                })
                .then(function (r) { return r.json(); })
                .then(function (td) {
                    if (!td.success) {
                        showNotification(td.message || t('unknown_error', 'Unknown error'), 'error');
                    } else {
                        showNotification(t('saved', 'Saved successfully'), 'success');
                        loadSeoMeta(currentFilters);
                    }
                })
                .catch(function () {
                    showNotification(t('unknown_error', 'Unknown error'), 'error');
                });
            } else {
                showNotification(t('saved', 'Saved successfully'), 'success');
                loadSeoMeta(currentFilters);
            }
        })
        .catch(function () {
            showNotification(t('unknown_error', 'Unknown error'), 'error');
        });
    }

    /* ─────────────────────────────────────────────────────
       DELETE SEO RECORD
       ───────────────────────────────────────────────────── */
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
                // If we were editing this record, reset the form
                if (currentEditId && String(currentEditId) === String(id)) resetForm();
                loadSeoMeta(currentFilters);
            } else {
                showNotification(d.message || t('delete_failed', 'Delete failed'), 'error');
            }
        });
    }

    /* ─────────────────────────────────────────────────────
       TRANSLATIONS HISTORY MODAL
       ───────────────────────────────────────────────────── */
    var currentTranslationSeoMetaId = null;

    function openTranslationsModal(seoMetaId) {
        currentTranslationSeoMetaId = seoMetaId;
        setVal('transSeoMetaId', seoMetaId);
        loadTranslations(seoMetaId);
        openModal('translationsModal');
    }

    function loadTranslations(seoMetaId) {
        fetch('/api/seo_meta/translations?seo_meta_id=' + encodeURIComponent(seoMetaId))
            .then(function (r) { return r.json(); })
            .then(function (d) {
                var tbody = document.getElementById('translationsBody');
                if (!tbody) return;
                tbody.innerHTML = '';
                var items = [];
                if (d.data && d.data.items)   items = d.data.items;
                else if (Array.isArray(d.data)) items = d.data;

                if (items.length > 0) {
                    items.forEach(function (item) {
                        var tr = document.createElement('tr');
                        tr.innerHTML =
                            '<td>' + esc(item.language_code) + '</td>' +
                            '<td>' + esc(item.meta_title  || '') + '</td>' +
                            '<td>' + esc(item.og_title    || '') + '</td>' +
                            '<td><button class="btn btn-sm btn-danger delete-translation-btn" data-id="' + esc(item.id) + '">' +
                                t('table.delete', 'Delete') + '</button></td>';
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

        fetch('/api/seo_meta/translations', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
            body:    JSON.stringify({
                seo_meta_id:      seoMetaId,
                language_code:    getVal('transLangCode'),
                meta_title:       getVal('transMetaTitle'),
                meta_description: getVal('transMetaDescription'),
                meta_keywords:    getVal('transMetaKeywords'),
                og_title:         getVal('transOgTitle'),
                og_description:   getVal('transOgDescription'),
                og_image:         getVal('transOgImage')
            })
        })
        .then(function (r) { return r.json(); })
        .then(function (d) {
            if (d.success) {
                showNotification(t('saved', 'Saved successfully'), 'success');
                ['transMetaTitle','transMetaDescription','transMetaKeywords','transOgTitle','transOgDescription','transOgImage']
                    .forEach(function (id) { setVal(id, ''); });
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

    /* ─────────────────────────────────────────────────────
       FILTER
       ───────────────────────────────────────────────────── */
    function filterSeoMeta() {
        loadSeoMeta({
            search:      getVal('filterSearch'),
            entity_type: getVal('filterEntityType'),
            page: 1
        });
    }

    function clearFilters() {
        setVal('filterSearch', '');
        setVal('filterEntityType', '');
        loadSeoMeta({ page: 1 });
    }

    /* ─────────────────────────────────────────────────────
       LOAD LANGUAGES (populates both selectors)
       ───────────────────────────────────────────────────── */
    function loadLanguages() {
        var selIds = ['smTransLang', 'transLangCode'];
        var selects = selIds.map(function (id) { return document.getElementById(id); }).filter(Boolean);
        if (!selects.length) return;

        var adminLang = CFG.lang || 'en';

        fetch('/api/languages')
            .then(function (r) { return r.json(); })
            .then(function (d) {
                var items = [];
                if (d.success && d.data) {
                    items = Array.isArray(d.data) ? d.data : (d.data.items || []);
                }
                if (!items.length) {
                    items = FALLBACK_LANGS.map(function (c) { return { code: c, native_name: c }; });
                }
                selects.forEach(function (sel) {
                    sel.innerHTML = '';
                    items.forEach(function (lang) {
                        var opt = document.createElement('option');
                        opt.value       = lang.code || lang.language_code || lang.id;
                        opt.textContent = lang.native_name || lang.name || lang.code || lang.id;
                        sel.appendChild(opt);
                    });
                    // Default to admin language
                    for (var i = 0; i < sel.options.length; i++) {
                        if (sel.options[i].value === adminLang) { sel.selectedIndex = i; break; }
                    }
                });
            })
            .catch(function () {
                selects.forEach(function (sel) {
                    sel.innerHTML = '';
                    FALLBACK_LANGS.forEach(function (code) {
                        var opt = document.createElement('option');
                        opt.value = opt.textContent = code;
                        sel.appendChild(opt);
                    });
                });
            });
    }

    /* ─────────────────────────────────────────────────────
       INIT
       ───────────────────────────────────────────────────── */
    function init() {
        reloadConfig();

        /* "Add new" → reset form */
        var btnAdd = document.getElementById('btnAddSeoMeta');
        if (btnAdd) btnAdd.addEventListener('click', function () { resetForm(); });

        /* "New Record" button inside form */
        var btnReset = document.getElementById('btnResetForm');
        if (btnReset) btnReset.addEventListener('click', function () { resetForm(); });

        /* Form submit */
        var form = document.getElementById('seoMetaForm');
        if (form) form.addEventListener('submit', function (e) {
            e.preventDefault();
            saveSeoMeta(new FormData(this));
        });

        /* Filter buttons */
        var btnFilt = document.getElementById('btnFilter');
        if (btnFilt) btnFilt.addEventListener('click', function () { filterSeoMeta(); });

        var btnClear = document.getElementById('btnClearFilters');
        if (btnClear) btnClear.addEventListener('click', function () { clearFilters(); });

        var searchInput = document.getElementById('filterSearch');
        if (searchInput) searchInput.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') { e.preventDefault(); filterSeoMeta(); }
        });

        /* Close modal buttons */
        document.querySelectorAll('.btn-close-modal').forEach(function (btn) {
            btn.addEventListener('click', function () { closeModal(btn.dataset.modal); });
        });

        /* Language change in inline form → reload translation */
        var transLang = document.getElementById('smTransLang');
        if (transLang) transLang.addEventListener('change', function () {
            if (currentEditId) {
                loadTranslationIntoForm(currentEditId, this.value);
            } else {
                clearTranslationFields();
            }
        });

        /* Translations history: add button */
        var btnAddTrans = document.getElementById('btnAddTranslation');
        if (btnAddTrans) btnAddTrans.addEventListener('click', function () { saveTranslation(); });

        /* Delegated events: edit, delete, translations, delete-translation */
        document.addEventListener('click', function (e) {
            var editBtn = e.target.closest('.edit-btn');
            if (editBtn) { editSeoMeta(editBtn.dataset.id); return; }

            var deleteBtn = e.target.closest('.delete-btn');
            if (deleteBtn) { deleteSeoMeta(deleteBtn.dataset.id); return; }

            var transBtn = e.target.closest('.translations-btn');
            if (transBtn) { openTranslationsModal(transBtn.dataset.id); return; }

            var delTransBtn = e.target.closest('.delete-translation-btn');
            if (delTransBtn) { deleteTranslation(delTransBtn.dataset.id); return; }
        });

        /* Populate language dropdowns, then load table */
        loadLanguages();
        loadSeoMeta();
    }

    /* Fragment / SPA support */
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
