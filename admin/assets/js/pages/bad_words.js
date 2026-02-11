(function(){
    var CFG = window.BAD_WORDS_CONFIG || {};
    var API_BASE = CFG.apiBase || '';
    var CSRF = CFG.csrfToken || '';
    var STRINGS = CFG.strings || {};
    var CAN_CREATE = CFG.canCreate || false;
    var CAN_EDIT = CFG.canEdit || false;
    var CAN_DELETE = CFG.canDelete || false;
    var FALLBACK_LANGS = ['ar','en','fr','tr','ur','de','es'];

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

    // Severity badge CSS class
    function severityClass(level){
        switch(String(level)){
            case 'low': return 'badge-low';
            case 'medium': return 'badge-medium';
            case 'high': return 'badge-high';
            default: return 'badge-secondary';
        }
    }

    // Load Bad Words
    function loadBadWords(params){
        params = params || {};
        var query = [];
        if(params.search) query.push('search=' + encodeURIComponent(params.search));
        if(params.severity) query.push('severity=' + encodeURIComponent(params.severity));
        if(params.is_active !== undefined && params.is_active !== '') query.push('is_active=' + encodeURIComponent(params.is_active));
        if(params.limit) query.push('limit=' + encodeURIComponent(params.limit));
        if(params.offset) query.push('offset=' + encodeURIComponent(params.offset));
        var url = API_BASE + '/bad_words' + (query.length ? '?' + query.join('&') : '');

        fetch(url)
        .then(function(r){ return r.json(); })
        .then(function(d){
            var tbody = document.getElementById('badWordsBody');
            tbody.innerHTML = '';
            if(d.success && d.data && d.data.items && d.data.items.length > 0){
                d.data.items.forEach(function(item){
                    var tr = document.createElement('tr');
                    tr.innerHTML =
                        '<td>' + esc(item.id) + '</td>' +
                        '<td>' + esc(item.word) + '</td>' +
                        '<td><span class="badge ' + severityClass(item.severity) + '">' + esc(item.severity) + '</span></td>' +
                        '<td>' + (item.is_regex ? t('table.yes', 'Yes') : t('table.no', 'No')) + '</td>' +
                        '<td>' + (item.is_active ? t('table.yes', 'Yes') : t('table.no', 'No')) + '</td>' +
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
                td.textContent = t('no_items', 'No bad words found');
                tr.appendChild(td);
                tbody.appendChild(tr);
            }
        });
    }

    // Open Add Modal
    function openAddModal(){
        document.getElementById('badWordForm').reset();
        document.getElementById('badWordId').value = '';
        document.getElementById('badWordModalTitle').textContent = t('add_word', 'Add Word');
        openModal('badWordModal');
    }

    // Open Edit Modal
    function openEditModal(id){
        fetch(API_BASE + '/bad_words?id=' + encodeURIComponent(id))
        .then(function(r){ return r.json(); })
        .then(function(d){
            if(d.success && d.data){
                var rec = d.data;
                document.getElementById('badWordId').value = rec.id;
                document.getElementById('bwWord').value = rec.word || '';
                document.getElementById('bwSeverity').value = rec.severity || '';
                document.getElementById('bwIsRegex').checked = !!rec.is_regex;
                document.getElementById('bwIsActive').checked = !!rec.is_active;
                document.getElementById('badWordModalTitle').textContent = t('edit_word', 'Edit Word');
                openModal('badWordModal');
            }
        });
    }

    // Save Bad Word
    function saveBadWord(formData){
        var editId = document.getElementById('badWordId').value;
        var method = editId ? 'PUT' : 'POST';
        var body = {
            word: formData.get('word'),
            severity: formData.get('severity'),
            is_regex: formData.get('is_regex') ? 1 : 0,
            is_active: formData.get('is_active') ? 1 : 0
        };
        if(editId) body.id = editId;

        fetch(API_BASE + '/bad_words', {
            method: method,
            headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF},
            body: JSON.stringify(body)
        }).then(function(r){ return r.json(); }).then(function(d){
            if(d.success){
                closeModal('badWordModal');
                document.getElementById('badWordForm').reset();
                document.getElementById('badWordId').value = '';
                alert(t('saved', 'Saved successfully'));
                loadBadWords();
            } else {
                alert(d.message || t('unknown_error', 'Unknown error'));
            }
        });
    }

    // Delete Bad Word
    function deleteBadWord(id){
        if(!confirm(t('confirm_delete', 'Are you sure you want to delete this word?'))) return;
        fetch(API_BASE + '/bad_words?id=' + encodeURIComponent(id), {
            method: 'DELETE',
            headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF}
        }).then(function(r){ return r.json(); }).then(function(d){
            if(d.success){ alert(t('deleted', 'Deleted successfully')); loadBadWords(); }
            else alert(d.message || t('delete_failed', 'Delete failed'));
        });
    }

    // Current translations context
    var currentTranslationBadWordId = null;

    // Open Translations Modal
    function openTranslationsModal(badWordId){
        currentTranslationBadWordId = badWordId;
        fetch(API_BASE + '/bad_words/translations?bad_word_id=' + encodeURIComponent(badWordId))
        .then(function(r){ return r.json(); })
        .then(function(d){
            var tbody = document.getElementById('translationsBody');
            tbody.innerHTML = '';
            if(d.success && d.data && d.data.items && d.data.items.length > 0){
                d.data.items.forEach(function(item){
                    var tr = document.createElement('tr');
                    tr.innerHTML =
                        '<td>' + esc(item.language_code) + '</td>' +
                        '<td>' + esc(item.word) + '</td>' +
                        '<td>' +
                            '<button class="btn btn-sm btn-danger delete-translation-btn" data-id="' + esc(item.id) + '">' + t('table.delete', 'Delete') + '</button>' +
                        '</td>';
                    tbody.appendChild(tr);
                });
            } else {
                var tr = document.createElement('tr');
                var td = document.createElement('td');
                td.setAttribute('colspan', '3');
                td.style.textAlign = 'center';
                td.textContent = t('no_translations', 'No translations found');
                tr.appendChild(td);
                tbody.appendChild(tr);
            }
            openModal('translationsModal');
        });
    }

    // Save Translation
    function saveTranslation(badWordId, langCode, word){
        fetch(API_BASE + '/bad_words/translations', {
            method: 'POST',
            headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF},
            body: JSON.stringify({bad_word_id: badWordId, language_code: langCode, word: word})
        }).then(function(r){ return r.json(); }).then(function(d){
            if(d.success){
                alert(t('saved', 'Saved successfully'));
                openTranslationsModal(badWordId);
            } else {
                alert(d.message || t('unknown_error', 'Unknown error'));
            }
        });
    }

    // Delete Translation
    function deleteTranslation(id){
        if(!confirm(t('confirm_delete_translation', 'Are you sure you want to delete this translation?'))) return;
        fetch(API_BASE + '/bad_words/translations?id=' + encodeURIComponent(id), {
            method: 'DELETE',
            headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF}
        }).then(function(r){ return r.json(); }).then(function(d){
            if(d.success){
                alert(t('deleted', 'Deleted successfully'));
                if(currentTranslationBadWordId) openTranslationsModal(currentTranslationBadWordId);
            } else {
                alert(d.message || t('delete_failed', 'Delete failed'));
            }
        });
    }

    // Check Text
    function checkText(){
        var textInput = document.getElementById('textCheckInput');
        var text = textInput ? textInput.value : '';
        if(!text){ alert(t('enter_text', 'Please enter text to check')); return; }

        var body = {text: text};

        fetch(API_BASE + '/bad_words/check', {
            method: 'POST',
            headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF},
            body: JSON.stringify(body)
        }).then(function(r){ return r.json(); }).then(function(d){
            var resultsDiv = document.getElementById('textCheckResults');
            resultsDiv.innerHTML = '';
            if(d.success && d.data){
                if(d.data.matches && d.data.matches.length > 0){
                    var ul = document.createElement('ul');
                    d.data.matches.forEach(function(match){
                        var li = document.createElement('li');
                        li.textContent = (match.word || '') + ' (' + t('table.severity', 'Severity') + ': ' + (match.severity || '') + ')';
                        ul.appendChild(li);
                    });
                    resultsDiv.appendChild(ul);
                } else {
                    resultsDiv.textContent = t('no_bad_words_found', 'No bad words found in the text.');
                }
            } else {
                resultsDiv.textContent = d.message || t('check_failed', 'Check failed');
            }
        });
    }

    // Filter Bad Words
    function filterBadWords(){
        var search = document.getElementById('filterSearch');
        var severity = document.getElementById('filterSeverity');
        var status = document.getElementById('filterStatus');
        loadBadWords({
            search: search ? search.value : '',
            severity: severity ? severity.value : '',
            is_active: status ? status.value : ''
        });
    }

    // Load languages dynamically from /api/languages
    function loadLanguages(){
        var select = document.getElementById('transLangCode');
        if(!select) return;
        fetch(API_BASE + '/languages')
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
        // Close modal buttons
        document.querySelectorAll('.btn-close-modal').forEach(function(btn){
            btn.addEventListener('click', function(){ closeModal(btn.dataset.modal); });
        });

        // Add Word button
        var btnAdd = document.getElementById('btnAddWord');
        if(btnAdd) btnAdd.addEventListener('click', function(){ openAddModal(); });

        // Bad Word Form submit
        var form = document.getElementById('badWordForm');
        if(form) form.addEventListener('submit', function(e){
            e.preventDefault();
            saveBadWord(new FormData(this));
        });

        // Filter button
        var btnFilt = document.getElementById('btnFilter');
        if(btnFilt) btnFilt.addEventListener('click', function(){ filterBadWords(); });

        // Search input Enter key
        var searchInput = document.getElementById('filterSearch');
        if(searchInput) searchInput.addEventListener('keydown', function(e){
            if(e.key === 'Enter'){ e.preventDefault(); filterBadWords(); }
        });

        // Open Check Text modal button
        var btnCheck = document.getElementById('btnOpenCheckText');
        if(btnCheck) btnCheck.addEventListener('click', function(){ openModal('textCheckModal'); });

        // Check Text button
        var btnDoCheck = document.getElementById('btnCheckText');
        if(btnDoCheck) btnDoCheck.addEventListener('click', function(){ checkText(); });

        // Add Translation button
        var btnTrans = document.getElementById('btnAddTranslation');
        if(btnTrans) btnTrans.addEventListener('click', function(){
            var langCode = document.getElementById('transLangCode');
            var word = document.getElementById('transWord');
            if(!word || !word.value){ alert(t('enter_text', 'Please enter a word')); return; }
            saveTranslation(currentTranslationBadWordId, langCode ? langCode.value : '', word.value);
        });

        // Event delegation for edit, delete, translations buttons
        document.addEventListener('click', function(e){
            var editBtn = e.target.closest('.edit-btn');
            if(editBtn){
                openEditModal(editBtn.dataset.id);
                return;
            }

            var deleteBtn = e.target.closest('.delete-btn');
            if(deleteBtn){
                deleteBadWord(deleteBtn.dataset.id);
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

        // Auto-load bad words table
        loadBadWords();
    }

    // Fragment support
    window.page = { run: init };

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
