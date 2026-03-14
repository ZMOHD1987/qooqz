(function(){
    'use strict';
    var CFG, CSRF, STRINGS, CAN_CREATE, CAN_EDIT, CAN_DELETE;
    var FALLBACK_LANGS = ['ar','en','fr','tr','ur','de','es'];
    var PER_PAGE = 25;
    var currentPage = 1;
    var currentFilters = {};

    function reloadConfig(){
        CFG = window.SEO_META_CONFIG || {};
        CSRF = CFG.csrfToken || '';
        STRINGS = CFG.strings || {};
        CAN_CREATE = !!CFG.canCreate;
        CAN_EDIT = !!CFG.canEdit;
        CAN_DELETE = !!CFG.canDelete;
    }
    reloadConfig();

    // Translation helper - resolves dot-separated keys
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

    // XSS escape helper
    function esc(str){
        if(str == null) return '';
        var d = document.createElement('div');
        d.appendChild(document.createTextNode(String(str)));
        return d.innerHTML;
    }

    // Modal helpers
    function openModal(id){ document.getElementById(id).style.display='block'; }
    function closeModal(id){ document.getElementById(id).style.display='none'; }

    // In-page notification (toast) instead of alert()
    function showNotification(message, type){
        type = type || 'info';
        var container = document.getElementById('smNotifications');
        if(!container){
            container = document.createElement('div');
            container.id = 'smNotifications';
            container.className = 'sm-notifications';
            var pageContainer = document.getElementById('seoMetaPageContainer');
            if(pageContainer) pageContainer.insertBefore(container, pageContainer.firstChild);
            else document.body.appendChild(container);
        }
        var toast = document.createElement('div');
        toast.className = 'sm-toast sm-toast-' + type;
        toast.textContent = message;
        var closeBtn = document.createElement('span');
        closeBtn.className = 'sm-toast-close';
        closeBtn.textContent = '\u00d7';
        closeBtn.onclick = function(){ toast.remove(); };
        toast.appendChild(closeBtn);
        container.appendChild(toast);
        setTimeout(function(){ toast.remove(); }, 4000);
    }

    // Load SEO Meta records
    function loadSeoMeta(params){
        params = params || {};
        var page = params.page || 1;
        currentPage = page;
        currentFilters = params;
        var query = [];
        if(params.search) query.push('search=' + encodeURIComponent(params.search));
        if(params.entity_type) query.push('entity_type=' + encodeURIComponent(params.entity_type));
        query.push('limit=' + PER_PAGE);
        query.push('offset=' + ((page - 1) * PER_PAGE));
        var url = '/api/seo_meta' + (query.length ? '?' + query.join('&') : '');

        fetch(url)
        .then(function(r){ return r.json(); })
        .then(function(d){
            var tbody = document.getElementById('seoMetaBody');
            tbody.innerHTML = '';
            var total = 0;
            if(d.success && d.data && d.data.items && d.data.items.length > 0){
                total = d.data.meta ? d.data.meta.total : d.data.items.length;
                d.data.items.forEach(function(item){
                    var tr = document.createElement('tr');
                    tr.innerHTML =
                        '<td>' + esc(item.id) + '</td>' +
                        '<td><span class="badge badge-info">' + esc(item.entity_type) + '</span></td>' +
                        '<td>' + esc(item.entity_id) + '</td>' +
                        '<td>' + esc(item.canonical_url || '') + '</td>' +
                        '<td>' + esc(item.robots || '') + '</td>' +
                        '<td>' + esc(item.created_at || '') + '</td>' +
                        '<td>' +
                            (CAN_EDIT ? '<button class="btn btn-sm btn-info edit-btn" data-id="' + esc(item.id) + '">' + t('table.edit', 'Edit') + '</button> ' : '') +
                            '<button class="btn btn-sm btn-secondary translations-btn" data-id="' + esc(item.id) + '">' + t('table.translations', 'Translations') + '</button> ' +
                            (CAN_DELETE ? '<button class="btn btn-sm btn-danger delete-btn" data-id="' + esc(item.id) + '">' + t('table.delete', 'Delete') + '</button>' : '') +
                        '</td>';
                    tbody.appendChild(tr);
                });
            } else {
                var tr = document.createElement('tr');
                var td = document.createElement('td');
                td.setAttribute('colspan', '7');
                td.style.textAlign = 'center';
                td.textContent = t('no_items', 'No SEO records found');
                tr.appendChild(td);
                tbody.appendChild(tr);
            }
            renderPagination(page, total);
        });
    }

    // Render Pagination
    function renderPagination(page, total){
        var totalPages = Math.max(1, Math.ceil(total / PER_PAGE));
        var start = total > 0 ? (page - 1) * PER_PAGE + 1 : 0;
        var end = Math.min(page * PER_PAGE, total);

        var infoEl = document.getElementById('paginationInfo');
        if(infoEl) infoEl.textContent = start + '-' + end + ' ' + t('pagination.of', 'of') + ' ' + total;

        var pagEl = document.getElementById('pagination');
        if(!pagEl) return;
        pagEl.innerHTML = '';

        if(totalPages <= 1) return;

        // Previous button
        var prevBtn = document.createElement('button');
        prevBtn.className = 'pagination-btn';
        prevBtn.innerHTML = '&laquo;';
        prevBtn.disabled = (page <= 1);
        prevBtn.addEventListener('click', function(){ goToPage(page - 1); });
        pagEl.appendChild(prevBtn);

        // Page numbers
        for(var i = 1; i <= totalPages; i++){
            if(i === 1 || i === totalPages || (i >= page - 2 && i <= page + 2)){
                var pageBtn = document.createElement('button');
                pageBtn.className = 'pagination-btn' + (i === page ? ' active' : '');
                pageBtn.textContent = i;
                (function(pg){ pageBtn.addEventListener('click', function(){ goToPage(pg); }); })(i);
                pagEl.appendChild(pageBtn);
            } else if(i === page - 3 || i === page + 3){
                var ellipsis = document.createElement('span');
                ellipsis.className = 'pagination-ellipsis';
                ellipsis.textContent = '...';
                pagEl.appendChild(ellipsis);
            }
        }

        // Next button
        var nextBtn = document.createElement('button');
        nextBtn.className = 'pagination-btn';
        nextBtn.innerHTML = '&raquo;';
        nextBtn.disabled = (page >= totalPages);
        nextBtn.addEventListener('click', function(){ goToPage(page + 1); });
        pagEl.appendChild(nextBtn);
    }

    // Navigate to page
    function goToPage(page){
        var params = {};
        for(var k in currentFilters){ if(k !== 'page') params[k] = currentFilters[k]; }
        params.page = page;
        loadSeoMeta(params);
    }

    // Open Add Modal
    function openAddModal(){
        document.getElementById('seoMetaForm').reset();
        document.getElementById('seoMetaId').value = '';
        document.getElementById('seoMetaModalTitle').textContent = t('modal.add_title', 'Add SEO Record');
        openModal('seoMetaModal');
    }

    // Open Edit Modal
    function editSeoMeta(id){
        fetch('/api/seo_meta?id=' + encodeURIComponent(id))
        .then(function(r){ return r.json(); })
        .then(function(d){
            if(d.success && d.data){
                var rec = d.data;
                document.getElementById('seoMetaId').value = rec.id;
                document.getElementById('smEntityType').value = rec.entity_type || '';
                document.getElementById('smEntityId').value = rec.entity_id || '';
                document.getElementById('smCanonicalUrl').value = rec.canonical_url || '';
                document.getElementById('smRobots').value = rec.robots || 'index,follow';
                document.getElementById('smSchemaMarkup').value = rec.schema_markup || '';
                document.getElementById('seoMetaModalTitle').textContent = t('modal.edit_title', 'Edit SEO Record');
                openModal('seoMetaModal');
            }
        });
    }

    // Save SEO Meta
    function saveSeoMeta(formData){
        var editId = document.getElementById('seoMetaId').value;
        var method = editId ? 'PUT' : 'POST';
        var body = {
            entity_type: formData.get('entity_type'),
            entity_id: formData.get('entity_id'),
            canonical_url: formData.get('canonical_url'),
            robots: formData.get('robots'),
            schema_markup: formData.get('schema_markup')
        };
        if(editId) body.id = editId;

        fetch('/api/seo_meta', {
            method: method,
            headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF},
            body: JSON.stringify(body)
        }).then(function(r){ return r.json(); }).then(function(d){
            if(d.success){
                closeModal('seoMetaModal');
                document.getElementById('seoMetaForm').reset();
                document.getElementById('seoMetaId').value = '';
                showNotification(t('saved', 'Saved successfully'), 'success');
                loadSeoMeta();
            } else {
                showNotification(d.message || t('unknown_error', 'Unknown error'), 'error');
            }
        });
    }

    // Delete SEO Meta
    function deleteSeoMeta(id){
        if(!confirm(t('confirm_delete', 'Are you sure you want to delete this SEO record?'))) return;
        fetch('/api/seo_meta?id=' + encodeURIComponent(id), {
            method: 'DELETE',
            headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF}
        }).then(function(r){ return r.json(); }).then(function(d){
            if(d.success){ showNotification(t('deleted', 'Deleted successfully'), 'success'); loadSeoMeta(); }
            else showNotification(d.message || t('delete_failed', 'Delete failed'), 'error');
        });
    }

    // Current translations context
    var currentTranslationSeoMetaId = null;

    // Open Translations Modal
    function openTranslationsModal(seoMetaId){
        currentTranslationSeoMetaId = seoMetaId;
        document.getElementById('transSeoMetaId').value = seoMetaId;
        loadTranslations(seoMetaId);
        openModal('translationsModal');
    }

    // Load Translations
    function loadTranslations(seoMetaId){
        fetch('/api/seo_meta/translations?seo_meta_id=' + encodeURIComponent(seoMetaId))
        .then(function(r){ return r.json(); })
        .then(function(d){
            var tbody = document.getElementById('translationsBody');
            tbody.innerHTML = '';
            var items = [];
            if(d.data && d.data.items) { items = d.data.items; }
            else if(Array.isArray(d.data)) { items = d.data; }
            if(items.length > 0){
                items.forEach(function(item){
                    var tr = document.createElement('tr');
                    tr.innerHTML =
                        '<td>' + esc(item.language_code) + '</td>' +
                        '<td>' + esc(item.meta_title || '') + '</td>' +
                        '<td>' + esc(item.og_title || '') + '</td>' +
                        '<td>' +
                            '<button class="btn btn-sm btn-danger delete-translation-btn" data-id="' + esc(item.id) + '">' + t('table.delete', 'Delete') + '</button>' +
                        '</td>';
                    tbody.appendChild(tr);
                });
            } else {
                var tr = document.createElement('tr');
                var td = document.createElement('td');
                td.setAttribute('colspan', '4');
                td.style.textAlign = 'center';
                td.textContent = t('no_translations', 'No translations found');
                tr.appendChild(td);
                tbody.appendChild(tr);
            }
        });
    }

    // Save Translation
    function saveTranslation(){
        var seoMetaId = currentTranslationSeoMetaId;
        var langCode = document.getElementById('transLangCode');
        var metaTitle = document.getElementById('transMetaTitle');
        var metaDescription = document.getElementById('transMetaDescription');
        var metaKeywords = document.getElementById('transMetaKeywords');
        var ogTitle = document.getElementById('transOgTitle');
        var ogDescription = document.getElementById('transOgDescription');
        var ogImage = document.getElementById('transOgImage');

        if(!seoMetaId){ showNotification(t('unknown_error', 'Unknown error'), 'error'); return; }

        fetch('/api/seo_meta/translations', {
            method: 'POST',
            headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF},
            body: JSON.stringify({
                seo_meta_id: seoMetaId,
                language_code: langCode ? langCode.value : '',
                meta_title: metaTitle ? metaTitle.value : '',
                meta_description: metaDescription ? metaDescription.value : '',
                meta_keywords: metaKeywords ? metaKeywords.value : '',
                og_title: ogTitle ? ogTitle.value : '',
                og_description: ogDescription ? ogDescription.value : '',
                og_image: ogImage ? ogImage.value : ''
            })
        }).then(function(r){ return r.json(); }).then(function(d){
            if(d.success){
                showNotification(t('saved', 'Saved successfully'), 'success');
                // Clear translation form fields
                if(metaTitle) metaTitle.value = '';
                if(metaDescription) metaDescription.value = '';
                if(metaKeywords) metaKeywords.value = '';
                if(ogTitle) ogTitle.value = '';
                if(ogDescription) ogDescription.value = '';
                if(ogImage) ogImage.value = '';
                loadTranslations(seoMetaId);
            } else {
                showNotification(d.message || t('unknown_error', 'Unknown error'), 'error');
            }
        });
    }

    // Delete Translation
    function deleteTranslation(id){
        if(!confirm(t('confirm_delete_translation', 'Are you sure you want to delete this translation?'))) return;
        fetch('/api/seo_meta/translations?id=' + encodeURIComponent(id), {
            method: 'DELETE',
            headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF}
        }).then(function(r){ return r.json(); }).then(function(d){
            if(d.success){
                showNotification(t('deleted', 'Deleted successfully'), 'success');
                if(currentTranslationSeoMetaId) loadTranslations(currentTranslationSeoMetaId);
            } else {
                showNotification(d.message || t('delete_failed', 'Delete failed'), 'error');
            }
        });
    }

    // Filter SEO Meta
    function filterSeoMeta(){
        var search = document.getElementById('filterSearch');
        var entityType = document.getElementById('filterEntityType');
        loadSeoMeta({
            search: search ? search.value : '',
            entity_type: entityType ? entityType.value : '',
            page: 1
        });
    }

    // Clear Filters
    function clearFilters(){
        var search = document.getElementById('filterSearch');
        var entityType = document.getElementById('filterEntityType');
        if(search) search.value = '';
        if(entityType) entityType.value = '';
        loadSeoMeta({ page: 1 });
    }

    // Load languages dynamically from /api/languages
    function loadLanguages(){
        var select = document.getElementById('transLangCode');
        if(!select) return;
        fetch('/api/languages')
        .then(function(r){ return r.json(); })
        .then(function(d){
            select.innerHTML = '';
            var items = [];
            if(d.success && d.data){
                items = Array.isArray(d.data) ? d.data : (d.data.items || []);
            }
            if(items.length > 0){
                items.forEach(function(lang){
                    var opt = document.createElement('option');
                    opt.value = lang.code || lang.language_code || lang.id;
                    opt.textContent = (lang.native_name || lang.name || lang.code || lang.id);
                    select.appendChild(opt);
                });
            } else {
                // Fallback if API returns no items
                FALLBACK_LANGS.forEach(function(code){
                    var opt = document.createElement('option');
                    opt.value = code;
                    opt.textContent = code;
                    select.appendChild(opt);
                });
            }
        })
        .catch(function(){
            // Fallback on error
            FALLBACK_LANGS.forEach(function(code){
                var opt = document.createElement('option');
                opt.value = code;
                opt.textContent = code;
                select.appendChild(opt);
            });
        });
    }

    // Init function - called when DOM is ready
    function init(){
        // Re-read config (may have been updated by inline script on re-navigation)
        reloadConfig();

        // Close modal buttons
        document.querySelectorAll('.btn-close-modal').forEach(function(btn){
            btn.addEventListener('click', function(){ closeModal(btn.dataset.modal); });
        });

        // Add SEO Meta button
        var btnAdd = document.getElementById('btnAddSeoMeta');
        if(btnAdd) btnAdd.addEventListener('click', function(){ openAddModal(); });

        // SEO Meta Form submit
        var form = document.getElementById('seoMetaForm');
        if(form) form.addEventListener('submit', function(e){
            e.preventDefault();
            saveSeoMeta(new FormData(this));
        });

        // Filter button
        var btnFilt = document.getElementById('btnFilter');
        if(btnFilt) btnFilt.addEventListener('click', function(){ filterSeoMeta(); });

        // Clear Filters button
        var btnClear = document.getElementById('btnClearFilters');
        if(btnClear) btnClear.addEventListener('click', function(){ clearFilters(); });

        // Search input Enter key
        var searchInput = document.getElementById('filterSearch');
        if(searchInput) searchInput.addEventListener('keydown', function(e){
            if(e.key === 'Enter'){ e.preventDefault(); filterSeoMeta(); }
        });

        // Add Translation button
        var btnTrans = document.getElementById('btnAddTranslation');
        if(btnTrans) btnTrans.addEventListener('click', function(){
            saveTranslation();
        });

        // Event delegation for edit, delete, translations buttons
        document.addEventListener('click', function(e){
            var editBtn = e.target.closest('.edit-btn');
            if(editBtn){
                editSeoMeta(editBtn.dataset.id);
                return;
            }

            var deleteBtn = e.target.closest('.delete-btn');
            if(deleteBtn){
                deleteSeoMeta(deleteBtn.dataset.id);
                return;
            }

            var transBtn = e.target.closest('.translations-btn');
            if(transBtn){
                openTranslationsModal(transBtn.dataset.id);
                return;
            }

            var delTransBtn = e.target.closest('.delete-translation-btn');
            if(delTransBtn){
                deleteTranslation(delTransBtn.dataset.id);
                return;
            }
        });

        // Load languages for translation dropdown
        loadLanguages();

        // Auto-load SEO meta table
        loadSeoMeta();
    }

    // Fragment support - register with admin framework for re-navigation
    window.page = { run: init };
    if(window.Admin && Admin.page && typeof Admin.page.register === 'function'){
        Admin.page.register('seo_meta', init);
    }

    // Auto-init: handle both fresh page load and dynamic fragment loading
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function(){
            init();
        });
    } else {
        // DOM already loaded (fragment loaded dynamically)
        init();
    }
})();