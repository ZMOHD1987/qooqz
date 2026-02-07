/**
 * themes-system.js — IIFE matching categories.js pattern
 */
(function() {
    'use strict';
    const AF = window.AdminFramework;
    const CFG = () => window.THEMES_CONFIG || {};
    const API = {
        themes: '/api/themes',
        design: '/api/design_settings',
        colors: '/api/color_settings',
        fonts: '/api/font_settings',
        buttons: '/api/button_styles',
        cards: '/api/card_styles',
        system: '/api/system_settings'
    };

    let el = {};
    let state = { currentThemeId: null, translations: {} };

    /* ─── Helpers ─── */
    function $(id) { return document.getElementById(id); }
    function esc(s) { if (s == null) return ''; const d = document.createElement('div'); d.textContent = String(s); return d.innerHTML; }
    function tid() { return CFG().TENANT_ID || 1; }
    function lang() { return CFG().USER_LANG || 'en'; }
    function t(key, fb) { return state.translations[key] || fb || key; }
    function extractItems(json) {
        if (!json) return [];
        const d = json.data || json;
        if (Array.isArray(d)) return d;
        if (d && Array.isArray(d.items)) return d.items;
        if (d && Array.isArray(d.data)) return d.data;
        return [];
    }

    async function api(url, opts) {
        const res = await fetch(url, Object.assign({ credentials: 'same-origin', headers: { 'Content-Type': 'application/json' } }, opts || {}));
        return res.json();
    }

    function notify(msg, type) {
        if (AF && AF.toast) AF.toast(msg, type || 'info');
        else alert(msg);
    }

    /* ─── i18n ─── */
    async function loadTranslations() {
        try {
            const res = await fetch('/admin/languages/AdminUiTheme/' + lang() + '.json');
            if (!res.ok) return;
            const json = await res.json();
            state.translations = flattenObj(json);
            applyTranslations();
            const dir = json.direction || (lang() === 'ar' ? 'rtl' : 'ltr');
            const page = $('themesPage');
            if (page) page.dir = dir;
        } catch (e) { /* translations optional */ }
    }

    function flattenObj(obj, prefix) {
        let result = {};
        for (const k in obj) {
            const key = prefix ? prefix + '.' + k : k;
            if (typeof obj[k] === 'object' && obj[k] !== null && !Array.isArray(obj[k])) {
                Object.assign(result, flattenObj(obj[k], key));
            } else {
                result[key] = obj[k];
            }
        }
        return result;
    }

    function applyTranslations() {
        const page = $('themesPage');
        if (!page) return;
        page.querySelectorAll('[data-i18n]').forEach(function(el) {
            const key = el.getAttribute('data-i18n');
            if (state.translations[key]) el.textContent = state.translations[key];
        });
        page.querySelectorAll('[data-i18n-placeholder]').forEach(function(el) {
            const key = el.getAttribute('data-i18n-placeholder');
            if (state.translations[key]) el.placeholder = state.translations[key];
        });
    }

    /* ─── Init ─── */
    function init() {
        el = {
            list: $('themesList'),
            form: $('themeForm'),
            tbody: $('themesTableBody'),
            formTitle: $('formTitle'),
            themeId: $('themeId'),
            btnAdd: $('btnAddTheme'),
            btnCancel: $('btnCancel'),
            btnCancelMain: $('btnCancelMain'),
            btnSave: $('btnSave'),
            search: $('searchThemes'),
            filterStatus: $('filterStatus'),
            // Info fields
            fName: $('fName'), fSlug: $('fSlug'), fDescription: $('fDescription'),
            fVersion: $('fVersion'), fAuthor: $('fAuthor'),
            fThumbnailUrl: $('fThumbnailUrl'), fPreviewUrl: $('fPreviewUrl'),
            fIsActive: $('fIsActive'), fIsDefault: $('fIsDefault')
        };

        if (el.btnAdd) el.btnAdd.onclick = function() { showForm(); };
        if (el.btnCancel) el.btnCancel.onclick = hideForm;
        if (el.btnCancelMain) el.btnCancelMain.onclick = hideForm;
        if (el.btnSave) el.btnSave.onclick = saveTheme;
        if (el.search) el.search.oninput = function() { loadThemes(); };
        if (el.filterStatus) el.filterStatus.onchange = function() { loadThemes(); };

        // Tab buttons
        var page = $('themesPage');
        if (page) {
            page.querySelectorAll('.tab-btn').forEach(function(btn) {
                btn.onclick = function() {
                    page.querySelectorAll('.tab-btn').forEach(function(b) { b.classList.remove('active'); });
                    page.querySelectorAll('.tab-panel').forEach(function(p) { p.classList.remove('active'); });
                    btn.classList.add('active');
                    var panel = $('tab' + btn.getAttribute('data-tab').charAt(0).toUpperCase() + btn.getAttribute('data-tab').slice(1));
                    if (panel) panel.classList.add('active');
                };
            });
        }

        // Inline form toggles
        setupInlineForm('Design', 'design');
        setupInlineForm('Color', 'colors');
        setupInlineForm('Font', 'fonts');
        setupInlineForm('Button', 'buttons');
        setupInlineForm('Card', 'cards');
        setupInlineForm('System', 'system');

        loadTranslations();
        loadThemes();
    }

    function setupInlineForm(name, prefix) {
        var btnAdd = $('btnAdd' + name);
        var btnSave = $('btnSave' + name);
        var btnCancel = $('btnCancel' + name);
        var form = $(prefix + 'Form');
        if (btnAdd) btnAdd.onclick = function() { if (form) { clearInlineForm(prefix); form.classList.add('show'); } };
        if (btnCancel) btnCancel.onclick = function() { if (form) form.classList.remove('show'); };
        if (btnSave) btnSave.onclick = function() { saveSetting(prefix); };
    }

    /* ─── List / Form Toggle ─── */
    function showForm(themeId) {
        state.currentThemeId = themeId || null;
        if (el.form) el.form.style.display = 'block';
        if (el.list) el.list.style.display = 'none';
        if (el.themeId) el.themeId.value = themeId || '';
        if (el.formTitle) el.formTitle.textContent = themeId ? t('form.edit_title', 'Edit Theme') : t('form.add_title', 'Add Theme');

        // Reset tabs to first
        var page = $('themesPage');
        if (page) {
            page.querySelectorAll('.tab-btn').forEach(function(b, i) { b.classList.toggle('active', i === 0); });
            page.querySelectorAll('.tab-panel').forEach(function(p, i) { p.classList.toggle('active', i === 0); });
        }

        if (themeId) {
            loadThemeData(themeId);
        } else {
            clearForm();
        }
    }

    function hideForm() {
        if (el.form) el.form.style.display = 'none';
        if (el.list) el.list.style.display = 'block';
        state.currentThemeId = null;
        clearForm();
    }

    function clearForm() {
        ['fName','fSlug','fDescription','fVersion','fAuthor','fThumbnailUrl','fPreviewUrl'].forEach(function(id) {
            if (el[id]) el[id].value = '';
        });
        if (el.fVersion) el.fVersion.value = '1.0.0';
        if (el.fIsActive) el.fIsActive.value = '1';
        if (el.fIsDefault) el.fIsDefault.value = '0';
        if (el.themeId) el.themeId.value = '';
        // Clear settings lists
        ['designList','colorsList','fontsList','buttonsList','cardsList'].forEach(function(id) {
            var e = $(id);
            if (e) e.innerHTML = '<div class="empty-state">' + t('common.no_items', 'No items yet') + '</div>';
        });
    }

    /* ─── Load Themes List ─── */
    async function loadThemes() {
        if (el.tbody) el.tbody.innerHTML = '<tr><td colspan="7" class="loading-state"><div class="spinner"></div></td></tr>';
        try {
            var url = API.themes + '?tenant_id=' + tid() + '&format=json';
            var search = el.search ? el.search.value.trim() : '';
            var status = el.filterStatus ? el.filterStatus.value : '';
            if (search) url += '&search=' + encodeURIComponent(search);
            if (status !== '') url += '&is_active=' + status;
            var json = await api(url);
            var items = extractItems(json);
            renderThemes(items);
        } catch (e) {
            if (el.tbody) el.tbody.innerHTML = '<tr><td colspan="7" class="empty-state">' + t('errors.load_failed', 'Failed to load') + '</td></tr>';
        }
    }

    function renderThemes(items) {
        if (!el.tbody) return;
        if (!items || items.length === 0) {
            el.tbody.innerHTML = '<tr><td colspan="7" class="empty-state">' + t('common.no_items', 'No themes found') + '</td></tr>';
            return;
        }
        el.tbody.innerHTML = items.map(function(th) {
            var canEdit = CFG().CAN_EDIT;
            var canDelete = CFG().CAN_DELETE;
            return '<tr>' +
                '<td>' + esc(th.id) + '</td>' +
                '<td><strong>' + esc(th.name) + '</strong></td>' +
                '<td>' + esc(th.slug) + '</td>' +
                '<td>' + esc(th.version || '1.0.0') + '</td>' +
                '<td>' + (String(th.is_active) === '1'
                    ? '<span class="badge badge-success">' + t('common.active', 'Active') + '</span>'
                    : '<span class="badge badge-danger">' + t('common.inactive', 'Inactive') + '</span>') + '</td>' +
                '<td>' + (String(th.is_default) === '1' ? '<span class="badge badge-info">' + t('common.default', 'Default') + '</span>' : '-') + '</td>' +
                '<td class="actions-cell">' +
                    (canEdit ? '<button class="btn btn-outline btn-sm" onclick="ThemesSystem.edit(' + th.id + ')"><i class="fas fa-edit"></i></button>' : '') +
                    (canDelete ? '<button class="btn btn-danger btn-sm" onclick="ThemesSystem.remove(' + th.id + ')"><i class="fas fa-trash"></i></button>' : '') +
                '</td></tr>';
        }).join('');
    }

    /* ─── Load Theme Data for Edit ─── */
    async function loadThemeData(themeId) {
        try {
            var json = await api(API.themes + '?id=' + themeId + '&tenant_id=' + tid() + '&format=json');
            var items = extractItems(json);
            var theme = items.find(function(t) { return String(t.id) === String(themeId); });
            if (!theme && json.data && !Array.isArray(json.data)) theme = json.data;
            if (theme) {
                if (el.fName) el.fName.value = theme.name || '';
                if (el.fSlug) el.fSlug.value = theme.slug || '';
                if (el.fDescription) el.fDescription.value = theme.description || '';
                if (el.fVersion) el.fVersion.value = theme.version || '1.0.0';
                if (el.fAuthor) el.fAuthor.value = theme.author || '';
                if (el.fThumbnailUrl) el.fThumbnailUrl.value = theme.thumbnail_url || '';
                if (el.fPreviewUrl) el.fPreviewUrl.value = theme.preview_url || '';
                if (el.fIsActive) el.fIsActive.value = String(theme.is_active ?? 1);
                if (el.fIsDefault) el.fIsDefault.value = String(theme.is_default ?? 0);
            }
            // Load all related data
            loadSettingsList('design', 'designList', themeId);
            loadSettingsList('colors', 'colorsList', themeId);
            loadSettingsList('fonts', 'fontsList', themeId);
            loadSettingsList('buttons', 'buttonsList', themeId);
            loadSettingsList('cards', 'cardsList', themeId);
            loadSettingsList('system', 'systemList', themeId);
        } catch (e) {
            notify(t('errors.load_failed', 'Failed to load theme data'), 'error');
        }
    }

    /* ─── Load Settings List ─── */
    async function loadSettingsList(type, containerId, themeId) {
        var container = $(containerId);
        if (!container) return;
        container.innerHTML = '<div class="loading-state"><div class="spinner"></div></div>';
        try {
            var url = API[type] + '?tenant_id=' + tid() + '&format=json';
            if (themeId && type !== 'system') url += '&theme_id=' + themeId;
            var json = await api(url);
            var items = extractItems(json);
            if (!items.length) {
                container.innerHTML = '<div class="empty-state">' + t('common.no_items', 'No items yet') + '</div>';
                return;
            }
            container.innerHTML = items.map(function(item) { return renderSettingItem(type, item); }).join('');
        } catch (e) {
            container.innerHTML = '<div class="empty-state">' + t('errors.load_failed', 'Failed to load') + '</div>';
        }
    }

    function renderSettingItem(type, item) {
        var name = esc(item.name || item.setting_name || item.setting_key || '');
        var detail = '';
        var canEdit = CFG().CAN_EDIT;
        var canDelete = CFG().CAN_DELETE;
        var activeTag = String(item.is_active) === '0' ? ' <span class="status-inactive">[inactive]</span>' : '';

        if (type === 'design') {
            detail = esc(item.setting_key || '') + ' = ' + esc(item.setting_value || '') + ' (' + esc(item.category || '') + ')';
        } else if (type === 'colors') {
            detail = '<span class="color-swatch" style="background:' + esc(item.color_value || '#000') + '"></span>' + esc(item.color_value || '') + ' (' + esc(item.category || '') + ')';
        } else if (type === 'fonts') {
            detail = esc(item.font_family || '') + ' ' + esc(item.font_size || '') + ' ' + esc(item.font_weight || '');
        } else if (type === 'buttons') {
            detail = '<span class="color-swatch" style="background:' + esc(item.background_color || '#000') + '"></span>' + esc(item.button_type || '') + ' - ' + esc(item.font_size || '');
        } else if (type === 'cards') {
            detail = esc(item.card_type || '') + ' - ' + esc(item.hover_effect || 'none') + ' - ' + esc(item.text_align || 'left');
        } else if (type === 'system') {
            detail = esc(item.setting_key || '') + ' = ' + esc((item.setting_value || '').substring(0, 50)) + ' (' + esc(item.category || '') + ')';
        }

        return '<div class="setting-item">' +
            '<div class="setting-item-info"><div class="setting-item-name">' + name + activeTag + '</div><div class="setting-item-detail">' + detail + '</div></div>' +
            '<div class="setting-item-actions">' +
                (canEdit ? '<button class="btn btn-outline btn-sm" onclick="ThemesSystem.editSetting(\'' + type + '\',' + item.id + ')"><i class="fas fa-edit"></i></button>' : '') +
                (canDelete ? '<button class="btn btn-danger btn-sm" onclick="ThemesSystem.deleteSetting(\'' + type + '\',' + item.id + ')"><i class="fas fa-trash"></i></button>' : '') +
            '</div></div>';
    }

    /* ─── Save Theme ─── */
    async function saveTheme() {
        var name = el.fName ? el.fName.value.trim() : '';
        if (!name) { notify(t('errors.name_required', 'Name is required'), 'error'); return; }

        var slug = el.fSlug ? el.fSlug.value.trim() : '';
        if (!slug) slug = name.toLowerCase().replace(/[^a-z0-9\u0600-\u06FF]+/g, '-').replace(/^-|-$/g, '');

        var data = {
            name: name, slug: slug,
            description: el.fDescription ? el.fDescription.value : '',
            version: el.fVersion ? el.fVersion.value : '1.0.0',
            author: el.fAuthor ? el.fAuthor.value : '',
            thumbnail_url: el.fThumbnailUrl ? el.fThumbnailUrl.value : '',
            preview_url: el.fPreviewUrl ? el.fPreviewUrl.value : '',
            is_active: el.fIsActive ? parseInt(el.fIsActive.value) : 1,
            is_default: el.fIsDefault ? parseInt(el.fIsDefault.value) : 0,
            tenant_id: tid()
        };

        var themeId = el.themeId ? el.themeId.value : '';
        try {
            var json;
            if (themeId) {
                data.id = parseInt(themeId);
                json = await api(API.themes + '?id=' + themeId + '&tenant_id=' + tid(), { method: 'PUT', body: JSON.stringify(data) });
            } else {
                json = await api(API.themes + '?tenant_id=' + tid(), { method: 'POST', body: JSON.stringify(data) });
            }
            if (json.success) {
                notify(t('messages.saved', 'Theme saved successfully'), 'success');
                hideForm();
                loadThemes();
            } else {
                notify(json.message || t('errors.save_failed', 'Save failed'), 'error');
            }
        } catch (e) {
            notify(t('errors.save_failed', 'Save failed'), 'error');
        }
    }

    /* ─── Delete Theme ─── */
    async function deleteTheme(id) {
        if (!confirm(t('messages.confirm_delete', 'Are you sure you want to delete this theme?'))) return;
        try {
            var json = await api(API.themes + '?id=' + id + '&tenant_id=' + tid(), { method: 'DELETE', body: JSON.stringify({ id: id }) });
            if (json.success) {
                notify(t('messages.deleted', 'Theme deleted'), 'success');
                loadThemes();
            } else {
                notify(json.message || t('errors.delete_failed', 'Delete failed'), 'error');
            }
        } catch (e) {
            notify(t('errors.delete_failed', 'Delete failed'), 'error');
        }
    }

    /* ─── Save Setting (generic for all types) ─── */
    async function saveSetting(type) {
        var themeId = state.currentThemeId;
        if (!themeId) { notify(t('errors.save_theme_first', 'Save the theme first'), 'error'); return; }

        var data = collectSettingData(type);
        if (!data) return;
        data.theme_id = parseInt(themeId);
        data.tenant_id = tid();

        var existingId = getInlineFormId(type);
        try {
            var json;
            if (existingId) {
                data.id = parseInt(existingId);
                json = await api(API[type] + '?id=' + existingId + '&tenant_id=' + tid(), { method: 'PUT', body: JSON.stringify(data) });
            } else {
                json = await api(API[type] + '?tenant_id=' + tid(), { method: 'POST', body: JSON.stringify(data) });
            }
            if (json.success) {
                notify(t('messages.setting_saved', 'Setting saved'), 'success');
                var form = $(type + 'Form');
                if (form) form.classList.remove('show');
                loadSettingsList(type, type === 'colors' ? 'colorsList' : type + 'List', themeId);
            } else {
                notify(json.message || t('errors.save_failed', 'Save failed'), 'error');
            }
        } catch (e) {
            notify(t('errors.save_failed', 'Save failed'), 'error');
        }
    }

    function collectSettingData(type) {
        if (type === 'design') {
            var key = $('dsKey') ? $('dsKey').value.trim() : '';
            var name = $('dsName') ? $('dsName').value.trim() : '';
            if (!key || !name) { notify(t('errors.fields_required', 'Key and Name are required'), 'error'); return null; }
            return { setting_key: key, setting_name: name, setting_value: $('dsValue') ? $('dsValue').value : '', setting_type: $('dsType') ? $('dsType').value : 'text', category: $('dsCategory') ? $('dsCategory').value : 'other', sort_order: $('dsSortOrder') ? parseInt($('dsSortOrder').value) || 0 : 0, is_active: $('dsIsActive') ? parseInt($('dsIsActive').value) : 1 };
        }
        if (type === 'colors') {
            var key = $('csKey') ? $('csKey').value.trim() : '';
            var name = $('csName') ? $('csName').value.trim() : '';
            if (!key || !name) { notify(t('errors.fields_required', 'Key and Name are required'), 'error'); return null; }
            return { setting_key: key, setting_name: name, color_value: $('csValue') ? $('csValue').value : '#000000', category: $('csCategory') ? $('csCategory').value : 'other', sort_order: $('csSortOrder') ? parseInt($('csSortOrder').value) || 0 : 0, is_active: $('csIsActive') ? parseInt($('csIsActive').value) : 1 };
        }
        if (type === 'fonts') {
            var key = $('fsKey') ? $('fsKey').value.trim() : '';
            var name = $('fsName') ? $('fsName').value.trim() : '';
            if (!key || !name) { notify(t('errors.fields_required', 'Key and Name are required'), 'error'); return null; }
            return { setting_key: key, setting_name: name, font_family: $('fsFamily') ? $('fsFamily').value : '', font_size: $('fsSize') ? $('fsSize').value : '', font_weight: $('fsWeight') ? $('fsWeight').value : '', line_height: $('fsLineHeight') ? $('fsLineHeight').value : '', category: $('fsCategory') ? $('fsCategory').value : 'other', sort_order: $('fsSortOrder') ? parseInt($('fsSortOrder').value) || 0 : 0, is_active: $('fsIsActive') ? parseInt($('fsIsActive').value) : 1 };
        }
        if (type === 'buttons') {
            var name = $('bsName') ? $('bsName').value.trim() : '';
            if (!name) { notify(t('errors.name_required', 'Name is required'), 'error'); return null; }
            var slug = $('bsSlug') ? $('bsSlug').value.trim() : '';
            if (!slug) slug = name.toLowerCase().replace(/[^a-z0-9]+/g, '-');
            return { name: name, slug: slug, button_type: $('bsType') ? $('bsType').value : 'primary', background_color: $('bsBgColor') ? $('bsBgColor').value : '#3b82f6', text_color: $('bsTextColor') ? $('bsTextColor').value : '#ffffff', border_color: $('bsBorderColor') ? $('bsBorderColor').value : '#3b82f6', border_width: $('bsBorderWidth') ? parseInt($('bsBorderWidth').value) || 0 : 0, border_radius: $('bsBorderRadius') ? parseInt($('bsBorderRadius').value) || 4 : 4, padding: $('bsPadding') ? $('bsPadding').value : '10px 20px', font_size: $('bsFontSize') ? $('bsFontSize').value : '14px', font_weight: $('bsFontWeight') ? $('bsFontWeight').value : 'normal', hover_background_color: $('bsHoverBg') ? $('bsHoverBg').value : null, hover_text_color: $('bsHoverText') ? $('bsHoverText').value : null, hover_border_color: $('bsHoverBorder') ? $('bsHoverBorder').value : null, is_active: $('bsIsActive') ? parseInt($('bsIsActive').value) : 1 };
        }
        if (type === 'cards') {
            var name = $('crdName') ? $('crdName').value.trim() : '';
            if (!name) { notify(t('errors.name_required', 'Name is required'), 'error'); return null; }
            var slug = $('crdSlug') ? $('crdSlug').value.trim() : '';
            if (!slug) slug = name.toLowerCase().replace(/[^a-z0-9]+/g, '-');
            return { name: name, slug: slug, card_type: $('crdType') ? $('crdType').value : 'product', background_color: $('crdBgColor') ? $('crdBgColor').value : '#FFFFFF', border_color: $('crdBorderColor') ? $('crdBorderColor').value : '#E0E0E0', border_width: $('crdBorderWidth') ? parseInt($('crdBorderWidth').value) || 1 : 1, border_radius: $('crdBorderRadius') ? parseInt($('crdBorderRadius').value) || 8 : 8, shadow_style: $('crdShadow') ? $('crdShadow').value : 'none', padding: $('crdPadding') ? $('crdPadding').value : '16px', hover_effect: $('crdHover') ? $('crdHover').value : 'none', text_align: $('crdTextAlign') ? $('crdTextAlign').value : 'left', image_aspect_ratio: $('crdAspectRatio') ? $('crdAspectRatio').value : '1:1', is_active: $('crdIsActive') ? parseInt($('crdIsActive').value) : 1 };
        }
        if (type === 'system') {
            var key = $('sysKey') ? $('sysKey').value.trim() : '';
            if (!key) { notify(t('errors.fields_required', 'Key is required'), 'error'); return null; }
            return { setting_key: key, setting_value: $('sysValue') ? $('sysValue').value : '', setting_type: $('sysType') ? $('sysType').value : 'text', category: $('sysCategory') ? $('sysCategory').value : 'general', description: $('sysDescription') ? $('sysDescription').value : '', is_public: $('sysIsPublic') ? parseInt($('sysIsPublic').value) : 0, is_editable: $('sysIsEditable') ? parseInt($('sysIsEditable').value) : 1 };
        }
        return null;
    }

    function getInlineFormId(type) {
        var idField = { design: 'dsId', colors: 'csId', fonts: 'fsId', buttons: 'bsId', cards: 'crdId', system: 'sysId' }[type];
        var f = $(idField);
        return f ? f.value : '';
    }

    function clearInlineForm(type) {
        var fields = {
            design: ['dsKey','dsName','dsValue','dsType','dsCategory','dsSortOrder','dsIsActive','dsId'],
            colors: ['csKey','csName','csValue','csCategory','csSortOrder','csIsActive','csId'],
            fonts: ['fsKey','fsName','fsFamily','fsSize','fsWeight','fsLineHeight','fsCategory','fsSortOrder','fsIsActive','fsId'],
            buttons: ['bsName','bsSlug','bsType','bsBgColor','bsTextColor','bsBorderColor','bsBorderWidth','bsBorderRadius','bsPadding','bsFontSize','bsFontWeight','bsHoverBg','bsHoverText','bsHoverBorder','bsIsActive','bsId'],
            cards: ['crdName','crdSlug','crdType','crdBgColor','crdBorderColor','crdBorderWidth','crdBorderRadius','crdPadding','crdShadow','crdHover','crdTextAlign','crdAspectRatio','crdIsActive','crdId'],
            system: ['sysKey','sysValue','sysType','sysCategory','sysDescription','sysIsPublic','sysIsEditable','sysId']
        }[type] || [];
        fields.forEach(function(id) {
            var f = $(id);
            if (f) {
                if (f.tagName === 'SELECT') f.selectedIndex = 0;
                else if (f.type === 'color') { /* keep default */ }
                else if (f.type === 'number') f.value = '0';
                else f.value = '';
            }
        });
    }

    /* ─── Edit Setting ─── */
    async function editSetting(type, id) {
        try {
            var url = API[type] + '?id=' + id + '&tenant_id=' + tid() + '&format=json';
            var json = await api(url);
            var items = extractItems(json);
            var item = items.find(function(i) { return String(i.id) === String(id); });
            if (!item && json.data && !Array.isArray(json.data)) item = json.data;
            if (!item) { notify(t('errors.not_found', 'Item not found'), 'error'); return; }

            clearInlineForm(type);
            populateInlineForm(type, item);
            var form = $(type + 'Form');
            if (form) form.classList.add('show');

            // Switch to the correct tab
            var page = $('themesPage');
            if (page) {
                page.querySelectorAll('.tab-btn').forEach(function(btn) {
                    var isTarget = btn.getAttribute('data-tab') === (type === 'cards' ? 'buttons' : type);
                    btn.classList.toggle('active', isTarget);
                });
                page.querySelectorAll('.tab-panel').forEach(function(p) {
                    var panelTab = p.id.replace('tab', '').toLowerCase();
                    p.classList.toggle('active', panelTab === (type === 'cards' ? 'buttons' : type));
                });
            }
        } catch (e) {
            notify(t('errors.load_failed', 'Failed to load'), 'error');
        }
    }

    function populateInlineForm(type, item) {
        if (type === 'design') {
            if ($('dsKey')) $('dsKey').value = item.setting_key || '';
            if ($('dsName')) $('dsName').value = item.setting_name || '';
            if ($('dsValue')) $('dsValue').value = item.setting_value || '';
            if ($('dsType')) $('dsType').value = item.setting_type || 'text';
            if ($('dsCategory')) $('dsCategory').value = item.category || 'other';
            if ($('dsSortOrder')) $('dsSortOrder').value = item.sort_order || 0;
            if ($('dsIsActive')) $('dsIsActive').value = String(item.is_active ?? 1);
            if ($('dsId')) $('dsId').value = item.id;
        } else if (type === 'colors') {
            if ($('csKey')) $('csKey').value = item.setting_key || '';
            if ($('csName')) $('csName').value = item.setting_name || '';
            if ($('csValue')) $('csValue').value = item.color_value || '#000000';
            if ($('csCategory')) $('csCategory').value = item.category || 'other';
            if ($('csSortOrder')) $('csSortOrder').value = item.sort_order || 0;
            if ($('csIsActive')) $('csIsActive').value = String(item.is_active ?? 1);
            if ($('csId')) $('csId').value = item.id;
        } else if (type === 'fonts') {
            if ($('fsKey')) $('fsKey').value = item.setting_key || '';
            if ($('fsName')) $('fsName').value = item.setting_name || '';
            if ($('fsFamily')) $('fsFamily').value = item.font_family || '';
            if ($('fsSize')) $('fsSize').value = item.font_size || '';
            if ($('fsWeight')) $('fsWeight').value = item.font_weight || '';
            if ($('fsLineHeight')) $('fsLineHeight').value = item.line_height || '';
            if ($('fsCategory')) $('fsCategory').value = item.category || 'other';
            if ($('fsSortOrder')) $('fsSortOrder').value = item.sort_order || 0;
            if ($('fsIsActive')) $('fsIsActive').value = String(item.is_active ?? 1);
            if ($('fsId')) $('fsId').value = item.id;
        } else if (type === 'buttons') {
            if ($('bsName')) $('bsName').value = item.name || '';
            if ($('bsSlug')) $('bsSlug').value = item.slug || '';
            if ($('bsType')) $('bsType').value = item.button_type || 'primary';
            if ($('bsBgColor')) $('bsBgColor').value = item.background_color || '#3b82f6';
            if ($('bsTextColor')) $('bsTextColor').value = item.text_color || '#ffffff';
            if ($('bsBorderColor')) $('bsBorderColor').value = item.border_color || '#3b82f6';
            if ($('bsBorderWidth')) $('bsBorderWidth').value = item.border_width ?? 0;
            if ($('bsBorderRadius')) $('bsBorderRadius').value = item.border_radius ?? 4;
            if ($('bsPadding')) $('bsPadding').value = item.padding || '10px 20px';
            if ($('bsFontSize')) $('bsFontSize').value = item.font_size || '14px';
            if ($('bsFontWeight')) $('bsFontWeight').value = item.font_weight || 'normal';
            if ($('bsHoverBg')) $('bsHoverBg').value = item.hover_background_color || '#000000';
            if ($('bsHoverText')) $('bsHoverText').value = item.hover_text_color || '#000000';
            if ($('bsHoverBorder')) $('bsHoverBorder').value = item.hover_border_color || '#000000';
            if ($('bsIsActive')) $('bsIsActive').value = String(item.is_active ?? 1);
            if ($('bsId')) $('bsId').value = item.id;
        } else if (type === 'cards') {
            if ($('crdName')) $('crdName').value = item.name || '';
            if ($('crdSlug')) $('crdSlug').value = item.slug || '';
            if ($('crdType')) $('crdType').value = item.card_type || 'product';
            if ($('crdBgColor')) $('crdBgColor').value = item.background_color || '#FFFFFF';
            if ($('crdBorderColor')) $('crdBorderColor').value = item.border_color || '#E0E0E0';
            if ($('crdBorderWidth')) $('crdBorderWidth').value = item.border_width ?? 1;
            if ($('crdBorderRadius')) $('crdBorderRadius').value = item.border_radius ?? 8;
            if ($('crdPadding')) $('crdPadding').value = item.padding || '16px';
            if ($('crdShadow')) $('crdShadow').value = item.shadow_style || 'none';
            if ($('crdHover')) $('crdHover').value = item.hover_effect || 'none';
            if ($('crdTextAlign')) $('crdTextAlign').value = item.text_align || 'left';
            if ($('crdAspectRatio')) $('crdAspectRatio').value = item.image_aspect_ratio || '1:1';
            if ($('crdIsActive')) $('crdIsActive').value = String(item.is_active ?? 1);
            if ($('crdId')) $('crdId').value = item.id;
        } else if (type === 'system') {
            if ($('sysKey')) $('sysKey').value = item.setting_key || '';
            if ($('sysValue')) $('sysValue').value = item.setting_value || '';
            if ($('sysType')) $('sysType').value = item.setting_type || 'text';
            if ($('sysCategory')) $('sysCategory').value = item.category || 'general';
            if ($('sysDescription')) $('sysDescription').value = item.description || '';
            if ($('sysIsPublic')) $('sysIsPublic').value = String(item.is_public ?? 0);
            if ($('sysIsEditable')) $('sysIsEditable').value = String(item.is_editable ?? 1);
            if ($('sysId')) $('sysId').value = item.id;
        }
    }

    /* ─── Delete Setting ─── */
    async function deleteSetting(type, id) {
        if (!confirm(t('messages.confirm_delete', 'Delete this item?'))) return;
        try {
            var json = await api(API[type] + '?id=' + id + '&tenant_id=' + tid(), { method: 'DELETE', body: JSON.stringify({ id: id }) });
            if (json.success) {
                notify(t('messages.deleted', 'Deleted'), 'success');
                var listId = type === 'colors' ? 'colorsList' : type + 'List';
                loadSettingsList(type, listId, state.currentThemeId);
            } else {
                notify(json.message || t('errors.delete_failed', 'Delete failed'), 'error');
            }
        } catch (e) {
            notify(t('errors.delete_failed', 'Delete failed'), 'error');
        }
    }

    /* ─── Public API ─── */
    window.ThemesSystem = {
        init: init,
        edit: function(id) { showForm(id); },
        remove: deleteTheme,
        editSetting: editSetting,
        deleteSetting: deleteSetting
    };
    window.page = { run: init };
})();
