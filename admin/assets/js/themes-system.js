/**
 * themes-system.js
 * Theme management - Products-pattern IIFE module
 * List → Form with tabs (Info, Design, Colors, Fonts, Buttons, Cards, Homepage)
 */
(function() {
    'use strict';

    // ════════════════════════════════════════
    // CONFIG & STATE
    // ════════════════════════════════════════
    const CFG = (typeof THEMES_CONFIG !== 'undefined') ? THEMES_CONFIG : {};
    const API = CFG.API || {};
    const TENANT_ID = CFG.TENANT_ID || 1;
    const LANG = CFG.LANG || 'en';

    const state = {
        themes: [],
        editingThemeId: null,
        i18n: {},
        // Related data for current theme
        designSettings: [],
        colorSettings: [],
        fontSettings: [],
        buttonStyles: [],
        cardStyles: [],
        homepageSections: [],
        systemSettings: []
    };

    // DOM elements cache
    const el = {};

    // ════════════════════════════════════════
    // INIT
    // ════════════════════════════════════════
    function init() {
        console.log('[ThemesSystem] init()');
        cacheElements();
        bindEvents();
        loadI18n();
        loadThemes();
    }

    function cacheElements() {
        const $ = id => document.getElementById(id);
        el.alertsContainer = $('alertsContainer');
        // List view
        el.listView = $('themesListView');
        el.loading = $('themesLoading');
        el.tableContainer = $('themesTableContainer');
        el.tableBody = $('themesTableBody');
        el.empty = $('themesEmpty');
        el.search = $('themeSearch');
        el.statusFilter = $('themeStatusFilter');
        el.btnAdd = $('btnAddTheme');
        // Form view
        el.formView = $('themeFormView');
        el.formTitle = $('formTitle');
        el.btnCancel = $('btnCancelForm');
        el.btnSave = $('btnSaveTheme');
        el.btnDelete = $('btnDeleteTheme');
        // Theme fields
        el.themeId = $('themeId');
        el.themeName = $('themeName');
        el.themeSlug = $('themeSlug');
        el.themeDescription = $('themeDescription');
        el.themeVersion = $('themeVersion');
        el.themeAuthor = $('themeAuthor');
        el.themeThumbnailUrl = $('themeThumbnailUrl');
        el.themePreviewUrl = $('themePreviewUrl');
        el.themeIsActive = $('themeIsActive');
        el.themeIsDefault = $('themeIsDefault');
        // Settings lists
        el.designSettingsList = $('designSettingsList');
        el.colorSettingsList = $('colorSettingsList');
        el.fontSettingsList = $('fontSettingsList');
        el.buttonStylesList = $('buttonStylesList');
        el.cardStylesList = $('cardStylesList');
        el.homepageSectionsList = $('homepageSectionsList');
        el.systemSettingsList = $('systemSettingsList');
    }

    function bindEvents() {
        // List view
        if (el.btnAdd) el.btnAdd.onclick = () => showForm();
        if (el.search) el.search.oninput = filterThemes;
        if (el.statusFilter) el.statusFilter.onchange = filterThemes;
        // Form view
        if (el.btnCancel) el.btnCancel.onclick = hideForm;
        if (el.btnSave) el.btnSave.onclick = saveTheme;
        if (el.btnDelete) el.btnDelete.onclick = deleteTheme;
        // Form tabs
        document.querySelectorAll('.themes-page .form-tab').forEach(tab => {
            tab.onclick = function() {
                const target = this.getAttribute('data-tab');
                document.querySelectorAll('.themes-page .form-tab').forEach(t => t.classList.remove('active'));
                this.classList.add('active');
                document.querySelectorAll('.themes-page .tab-content').forEach(c => {
                    c.style.display = 'none';
                    c.classList.remove('active');
                });
                const tabEl = document.getElementById('tab-' + target);
                if (tabEl) {
                    tabEl.style.display = 'block';
                    tabEl.classList.add('active');
                }
            };
        });
        // Settings add/save/cancel buttons
        bindSettingsButtons('Design', 'design');
        bindSettingsButtons('Color', 'color');
        bindSettingsButtons('Font', 'font');
        bindSettingsButtons('Button', 'button');
        bindSettingsButtons('Card', 'card');
        bindSettingsButtons('Section', 'section');
        bindSettingsButtons('System', 'system');
    }

    function bindSettingsButtons(name, prefix) {
        const btnAdd = document.getElementById('btnAdd' + name);
        const btnSave = document.getElementById('btnSave' + name);
        const btnCancel = document.getElementById('btnCancel' + name);
        const form = document.getElementById(prefix + 'Form');
        if (btnAdd) btnAdd.onclick = () => {
            resetSettingForm(prefix);
            if (form) form.style.display = 'block';
        };
        if (btnCancel) btnCancel.onclick = () => {
            if (form) form.style.display = 'none';
        };
        if (btnSave) btnSave.onclick = () => saveSetting(prefix);
    }

    // ════════════════════════════════════════
    // i18n
    // ════════════════════════════════════════
    async function loadI18n() {
        try {
            const res = await fetch('/languages/AdminUiTheme/' + LANG + '.json');
            if (res.ok) {
                state.i18n = await res.json();
                applyI18n();
            }
        } catch (e) {
            console.warn('[ThemesSystem] i18n load failed:', e);
        }
    }

    function t(key, fallback) {
        const parts = key.split('.');
        let val = state.i18n;
        for (const p of parts) {
            if (val && typeof val === 'object' && p in val) {
                val = val[p];
            } else {
                return fallback || key;
            }
        }
        return (typeof val === 'string') ? val : (fallback || key);
    }

    function applyI18n() {
        document.querySelectorAll('.themes-page [data-i18n]').forEach(el => {
            const key = el.getAttribute('data-i18n');
            const translated = t(key);
            if (translated !== key) el.textContent = translated;
        });
        document.querySelectorAll('.themes-page [data-i18n-placeholder]').forEach(el => {
            const key = el.getAttribute('data-i18n-placeholder');
            const translated = t(key);
            if (translated !== key) el.placeholder = translated;
        });
    }

    // ════════════════════════════════════════
    // THEMES LIST
    // ════════════════════════════════════════
    async function loadThemes() {
        showLoading(true);
        try {
            const url = API.themes + '?tenant_id=' + TENANT_ID + '&format=json';
            const res = await fetch(url);
            const json = await res.json();
            state.themes = extractItems(json);
            renderThemes(state.themes);
        } catch (e) {
            console.error('[ThemesSystem] loadThemes error:', e);
            showAlert('error', t('theme_manager.messages.error.load_failed', 'Failed to load themes'));
        } finally {
            showLoading(false);
        }
    }

    function extractItems(json) {
        if (!json) return [];
        if (json.success && json.data) {
            if (Array.isArray(json.data)) return json.data;
            if (json.data.items && Array.isArray(json.data.items)) return json.data.items;
            if (json.data.data && Array.isArray(json.data.data)) return json.data.data;
        }
        if (Array.isArray(json)) return json;
        return [];
    }

    function renderThemes(themes) {
        if (!el.tableBody) return;
        if (!themes || themes.length === 0) {
            if (el.tableContainer) el.tableContainer.style.display = 'none';
            if (el.empty) el.empty.style.display = 'flex';
            return;
        }
        if (el.tableContainer) el.tableContainer.style.display = 'block';
        if (el.empty) el.empty.style.display = 'none';

        el.tableBody.innerHTML = themes.map(th => {
            const statusClass = th.is_active ? 'badge-success' : 'badge-secondary';
            const statusText = th.is_active ? t('theme_manager.status.active', 'Active') : t('theme_manager.status.inactive', 'Inactive');
            const defaultBadge = th.is_default ? '<span class="badge badge-primary">' + t('theme_manager.status.default', 'Default') + '</span>' : '';
            const name = escapeHtml(th.name || '');
            const slug = escapeHtml(th.slug || '');
            return '<tr>' +
                '<td>' + th.id + '</td>' +
                '<td><strong>' + name + '</strong></td>' +
                '<td><code>' + slug + '</code></td>' +
                '<td>' + escapeHtml(th.version || '1.0.0') + '</td>' +
                '<td><span class="badge ' + statusClass + '">' + statusText + '</span></td>' +
                '<td>' + defaultBadge + '</td>' +
                '<td class="actions-cell">' +
                    '<button class="btn btn-sm btn-primary" onclick="ThemesSystem.editTheme(' + th.id + ')">' +
                        '<i class="fas fa-edit"></i> ' + t('theme_manager.table.actions.edit', 'Edit') +
                    '</button> ' +
                    '<button class="btn btn-sm btn-danger" onclick="ThemesSystem.removeTheme(' + th.id + ')">' +
                        '<i class="fas fa-trash"></i> ' + t('theme_manager.table.actions.delete', 'Delete') +
                    '</button>' +
                '</td>' +
            '</tr>';
        }).join('');
    }

    function filterThemes() {
        let filtered = state.themes;
        const search = (el.search && el.search.value || '').toLowerCase();
        const status = el.statusFilter ? el.statusFilter.value : '';
        if (search) {
            filtered = filtered.filter(th =>
                (th.name || '').toLowerCase().includes(search) ||
                (th.slug || '').toLowerCase().includes(search)
            );
        }
        if (status !== '') {
            filtered = filtered.filter(th => String(th.is_active) === status);
        }
        renderThemes(filtered);
    }

    // ════════════════════════════════════════
    // FORM: SHOW / HIDE
    // ════════════════════════════════════════
    function showForm(themeId) {
        state.editingThemeId = themeId || null;
        // Reset form
        const form = document.getElementById('themeForm');
        if (form) form.reset();
        if (el.themeId) el.themeId.value = '';
        if (el.themeVersion) el.themeVersion.value = '1.0.0';
        if (el.themeIsActive) el.themeIsActive.value = '1';
        if (el.themeIsDefault) el.themeIsDefault.checked = false;

        // Reset tabs to first
        document.querySelectorAll('.themes-page .form-tab').forEach(t => t.classList.remove('active'));
        document.querySelectorAll('.themes-page .tab-content').forEach(c => {
            c.style.display = 'none';
            c.classList.remove('active');
        });
        const firstTab = document.querySelector('.themes-page .form-tab');
        const infoTab = document.getElementById('tab-info');
        if (firstTab) firstTab.classList.add('active');
        if (infoTab) { infoTab.style.display = 'block'; infoTab.classList.add('active'); }

        // Clear settings lists
        clearAllSettingsLists();
        hideAllSettingForms();

        if (themeId) {
            // Edit mode
            if (el.formTitle) el.formTitle.textContent = t('theme_manager.form.edit_title', 'Edit Theme');
            if (el.btnDelete) el.btnDelete.style.display = 'inline-block';
            populateThemeForm(themeId);
            loadAllRelatedData(themeId);
        } else {
            // Add mode
            if (el.formTitle) el.formTitle.textContent = t('theme_manager.form.add_title', 'Add Theme');
            if (el.btnDelete) el.btnDelete.style.display = 'none';
        }

        // Show form, hide list
        if (el.listView) el.listView.style.display = 'none';
        if (el.formView) el.formView.style.display = 'block';
    }

    function hideForm() {
        if (el.formView) el.formView.style.display = 'none';
        if (el.listView) el.listView.style.display = 'block';
        state.editingThemeId = null;
    }

    function populateThemeForm(themeId) {
        const theme = state.themes.find(th => String(th.id) === String(themeId));
        if (!theme) return;
        if (el.themeId) el.themeId.value = theme.id;
        if (el.themeName) el.themeName.value = theme.name || '';
        if (el.themeSlug) el.themeSlug.value = theme.slug || '';
        if (el.themeDescription) el.themeDescription.value = theme.description || '';
        if (el.themeVersion) el.themeVersion.value = theme.version || '1.0.0';
        if (el.themeAuthor) el.themeAuthor.value = theme.author || '';
        if (el.themeThumbnailUrl) el.themeThumbnailUrl.value = theme.thumbnail_url || '';
        if (el.themePreviewUrl) el.themePreviewUrl.value = theme.preview_url || '';
        if (el.themeIsActive) el.themeIsActive.value = theme.is_active ? '1' : '0';
        if (el.themeIsDefault) el.themeIsDefault.checked = !!theme.is_default;
    }

    // ════════════════════════════════════════
    // SAVE / DELETE THEME
    // ════════════════════════════════════════
    async function saveTheme() {
        const name = (el.themeName && el.themeName.value || '').trim();
        let slug = (el.themeSlug && el.themeSlug.value || '').trim();
        if (!name) {
            showAlert('warning', t('theme_manager.form.fields.name.required', 'Theme name is required'));
            return;
        }
        if (!slug) {
            slug = name.toLowerCase().replace(/[^a-z0-9\u0600-\u06FF]+/g, '-').replace(/^-|-$/g, '');
            if (el.themeSlug) el.themeSlug.value = slug;
        }

        const themeId = el.themeId ? el.themeId.value : '';
        const isEdit = !!themeId;

        const payload = {
            tenant_id: TENANT_ID,
            name: name,
            slug: slug,
            description: (el.themeDescription && el.themeDescription.value || '').trim(),
            version: (el.themeVersion && el.themeVersion.value || '1.0.0').trim(),
            author: (el.themeAuthor && el.themeAuthor.value || '').trim(),
            thumbnail_url: (el.themeThumbnailUrl && el.themeThumbnailUrl.value || '').trim() || null,
            preview_url: (el.themePreviewUrl && el.themePreviewUrl.value || '').trim() || null,
            is_active: el.themeIsActive ? parseInt(el.themeIsActive.value) : 1,
            is_default: el.themeIsDefault ? (el.themeIsDefault.checked ? 1 : 0) : 0
        };

        if (isEdit) payload.id = parseInt(themeId);

        try {
            const url = isEdit ? API.themes + '?id=' + themeId : API.themes;
            const res = await fetch(url, {
                method: isEdit ? 'PUT' : 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const json = await res.json();
            if (json.success) {
                const savedId = isEdit ? parseInt(themeId) : (json.data && json.data.id);
                showAlert('success', t('theme_manager.messages.success.save', 'Theme saved successfully'));
                await loadThemes();
                hideForm();
            } else {
                showAlert('error', json.message || t('theme_manager.messages.error.save_failed', 'Failed to save'));
            }
        } catch (e) {
            console.error('[ThemesSystem] saveTheme error:', e);
            showAlert('error', t('theme_manager.messages.error.save_failed', 'Failed to save'));
        }
    }

    async function deleteTheme() {
        const themeId = el.themeId ? el.themeId.value : '';
        if (!themeId) return;
        if (!confirm(t('theme_manager.messages.confirm.delete', 'Are you sure you want to delete this theme?'))) return;

        try {
            const res = await fetch(API.themes + '?id=' + themeId, {
                method: 'DELETE',
                headers: { 'Content-Type': 'application/json' }
            });
            const json = await res.json();
            if (json.success) {
                showAlert('success', t('theme_manager.messages.success.delete', 'Theme deleted'));
                await loadThemes();
                hideForm();
            } else {
                showAlert('error', json.message || t('theme_manager.messages.error.delete_failed', 'Failed to delete'));
            }
        } catch (e) {
            console.error('[ThemesSystem] deleteTheme error:', e);
            showAlert('error', t('theme_manager.messages.error.delete_failed', 'Failed to delete'));
        }
    }

    async function removeTheme(themeId) {
        if (!confirm(t('theme_manager.messages.confirm.delete', 'Are you sure you want to delete this theme?'))) return;
        try {
            const res = await fetch(API.themes + '?id=' + themeId, {
                method: 'DELETE',
                headers: { 'Content-Type': 'application/json' }
            });
            const json = await res.json();
            if (json.success) {
                showAlert('success', t('theme_manager.messages.success.delete', 'Theme deleted'));
                await loadThemes();
            } else {
                showAlert('error', json.message || 'Failed to delete');
            }
        } catch (e) {
            showAlert('error', 'Failed to delete');
        }
    }

    // ════════════════════════════════════════
    // LOAD ALL RELATED DATA FOR A THEME
    // ════════════════════════════════════════
    async function loadAllRelatedData(themeId) {
        await Promise.all([
            loadSettings('design', API.designSettings, themeId),
            loadSettings('color', API.colorSettings, themeId),
            loadSettings('font', API.fontSettings, themeId),
            loadSettings('button', API.buttonStyles, themeId),
            loadSettings('card', API.cardStyles, themeId),
            loadSettings('section', API.homepageSections, themeId),
            loadSettings('system', API.systemSettings, themeId)
        ]);
    }

    async function loadSettings(type, apiUrl, themeId) {
        if (!apiUrl) return;
        try {
            const url = apiUrl + '?theme_id=' + themeId + '&tenant_id=' + TENANT_ID + '&format=json';
            const res = await fetch(url);
            const json = await res.json();
            const items = extractItems(json);
            state[type + 'Settings'] = items;
            renderSettingsList(type, items);
        } catch (e) {
            console.warn('[ThemesSystem] loadSettings(' + type + ') error:', e);
        }
    }

    // ════════════════════════════════════════
    // RENDER SETTINGS LISTS
    // ════════════════════════════════════════
    function getSettingsListEl(type) {
        const map = {
            design: el.designSettingsList,
            color: el.colorSettingsList,
            font: el.fontSettingsList,
            button: el.buttonStylesList,
            card: el.cardStylesList,
            section: el.homepageSectionsList,
            system: el.systemSettingsList
        };
        return map[type];
    }

    function renderSettingsList(type, items) {
        const listEl = getSettingsListEl(type);
        if (!listEl) return;

        if (!items || items.length === 0) {
            listEl.innerHTML = '<div class="empty-settings">No items found</div>';
            return;
        }

        listEl.innerHTML = items.map(item => {
            const itemId = item.id;
            let display = '';

            if (type === 'design') {
                display = '<strong>' + escapeHtml(item.setting_name || item.setting_key || '') + '</strong>' +
                          ' <span class="badge badge-secondary">' + escapeHtml(item.setting_type || 'text') + '</span>' +
                          ' <span class="badge badge-info">' + escapeHtml(item.category || '') + '</span>' +
                          '<div class="setting-value">' + escapeHtml(String(item.setting_value || '').substring(0, 100)) + '</div>';
            } else if (type === 'color') {
                display = '<span class="color-swatch" style="background:' + escapeHtml(item.color_value || '#000') + '"></span> ' +
                          '<strong>' + escapeHtml(item.setting_name || item.setting_key || '') + '</strong>' +
                          ' <code>' + escapeHtml(item.color_value || '') + '</code>' +
                          ' <span class="badge badge-info">' + escapeHtml(item.category || '') + '</span>';
            } else if (type === 'font') {
                display = '<strong>' + escapeHtml(item.setting_name || item.setting_key || '') + '</strong>' +
                          ' <span style="font-family:' + escapeHtml(item.font_family || '') + '">' + escapeHtml(item.font_family || '') + '</span>' +
                          ' <span class="badge badge-info">' + escapeHtml(item.category || '') + '</span>';
            } else if (type === 'button') {
                display = '<strong>' + escapeHtml(item.name || '') + '</strong>' +
                          ' <span class="badge badge-secondary">' + escapeHtml(item.button_type || '') + '</span>' +
                          ' <span class="color-swatch" style="background:' + escapeHtml(item.background_color || '#007bff') + '"></span>' +
                          ' <span class="color-swatch" style="background:' + escapeHtml(item.text_color || '#fff') + ';border:1px solid #ccc"></span>';
            } else if (type === 'card') {
                display = '<strong>' + escapeHtml(item.name || '') + '</strong>' +
                          ' <span class="badge badge-secondary">' + escapeHtml(item.card_type || '') + '</span>' +
                          ' <span class="color-swatch" style="background:' + escapeHtml(item.background_color || '#fff') + ';border:1px solid #ccc"></span>';
            } else if (type === 'section') {
                display = '<strong>' + escapeHtml(item.title || item.section_type || '') + '</strong>' +
                          ' <span class="badge badge-secondary">' + escapeHtml(item.section_type || '') + '</span>' +
                          ' <span class="badge badge-info">' + escapeHtml(item.layout_type || '') + '</span>' +
                          (item.is_active ? ' <span class="badge badge-success">Active</span>' : ' <span class="badge badge-secondary">Inactive</span>');
            } else if (type === 'system') {
                display = '<strong>' + escapeHtml(item.setting_key || '') + '</strong>' +
                          ' <span class="badge badge-secondary">' + escapeHtml(item.setting_type || 'text') + '</span>' +
                          ' <span class="badge badge-info">' + escapeHtml(item.category || '') + '</span>' +
                          '<div class="setting-value">' + escapeHtml(String(item.setting_value || '').substring(0, 100)) + '</div>' +
                          (item.is_public ? ' <span class="badge badge-success">Public</span>' : '');
            }

            return '<div class="settings-item" data-id="' + itemId + '">' +
                '<div class="settings-item-content">' + display + '</div>' +
                '<div class="settings-item-actions">' +
                    '<button class="btn btn-xs btn-primary" onclick="ThemesSystem.editSetting(\'' + type + '\',' + itemId + ')">' +
                        '<i class="fas fa-edit"></i></button> ' +
                    '<button class="btn btn-xs btn-danger" onclick="ThemesSystem.deleteSetting(\'' + type + '\',' + itemId + ')">' +
                        '<i class="fas fa-trash"></i></button>' +
                '</div></div>';
        }).join('');
    }

    function clearAllSettingsLists() {
        ['design', 'color', 'font', 'button', 'card', 'section', 'system'].forEach(type => {
            const listEl = getSettingsListEl(type);
            if (listEl) listEl.innerHTML = '';
            state[type + 'Settings'] = [];
        });
    }

    function hideAllSettingForms() {
        ['design', 'color', 'font', 'button', 'card', 'section', 'system'].forEach(prefix => {
            const form = document.getElementById(prefix + 'Form');
            if (form) form.style.display = 'none';
        });
    }

    // ════════════════════════════════════════
    // SETTINGS CRUD
    // ════════════════════════════════════════
    function resetSettingForm(prefix) {
        const idField = document.getElementById(prefix + 'Id');
        if (idField) idField.value = '';

        // Reset all inputs in the form
        const form = document.getElementById(prefix + 'Form');
        if (form) {
            form.querySelectorAll('input:not([type=hidden]), textarea, select').forEach(f => {
                if (f.type === 'checkbox') f.checked = true;
                else if (f.type === 'color') f.value = f.defaultValue || '#000000';
                else if (f.type === 'number') f.value = f.defaultValue || '0';
                else if (f.tagName === 'SELECT') f.selectedIndex = 0;
                else f.value = '';
            });
        }
    }

    function getApiForType(type) {
        const map = {
            design: API.designSettings,
            color: API.colorSettings,
            font: API.fontSettings,
            button: API.buttonStyles,
            card: API.cardStyles,
            section: API.homepageSections,
            system: API.systemSettings
        };
        return map[type];
    }

    function collectSettingData(prefix) {
        const $ = id => document.getElementById(id);
        const themeId = el.themeId ? parseInt(el.themeId.value) : null;

        if (prefix === 'design') {
            return {
                theme_id: themeId,
                tenant_id: TENANT_ID,
                setting_key: ($(prefix + 'Key') && $(prefix + 'Key').value || '').trim(),
                setting_name: ($(prefix + 'Name') && $(prefix + 'Name').value || '').trim(),
                setting_value: ($(prefix + 'Value') && $(prefix + 'Value').value || '').trim(),
                setting_type: $('designType') ? $('designType').value : 'text',
                category: $('designCategory') ? $('designCategory').value : 'other',
                is_active: $('designIsActive') ? parseInt($('designIsActive').value) : 1,
                sort_order: $('designSortOrder') ? parseInt($('designSortOrder').value) || 0 : 0
            };
        } else if (prefix === 'color') {
            return {
                theme_id: themeId,
                tenant_id: TENANT_ID,
                setting_key: ($('colorKey') && $('colorKey').value || '').trim(),
                setting_name: ($('colorName') && $('colorName').value || '').trim(),
                color_value: $('colorValue') ? $('colorValue').value : '#000000',
                category: $('colorCategory') ? $('colorCategory').value : 'other',
                is_active: $('colorIsActive') ? parseInt($('colorIsActive').value) : 1,
                sort_order: $('colorSortOrder') ? parseInt($('colorSortOrder').value) || 0 : 0
            };
        } else if (prefix === 'font') {
            return {
                theme_id: themeId,
                tenant_id: TENANT_ID,
                setting_key: ($('fontKey') && $('fontKey').value || '').trim(),
                setting_name: ($('fontName') && $('fontName').value || '').trim(),
                font_family: ($('fontFamily') && $('fontFamily').value || '').trim(),
                font_size: ($('fontSize') && $('fontSize').value || '').trim() || null,
                font_weight: ($('fontWeight') && $('fontWeight').value || '').trim() || null,
                line_height: ($('fontLineHeight') && $('fontLineHeight').value || '').trim() || null,
                category: $('fontCategory') ? $('fontCategory').value : 'other',
                is_active: $('fontIsActive') ? parseInt($('fontIsActive').value) : 1,
                sort_order: $('fontSortOrder') ? parseInt($('fontSortOrder').value) || 0 : 0
            };
        } else if (prefix === 'button') {
            return {
                theme_id: themeId,
                tenant_id: TENANT_ID,
                name: ($('buttonName') && $('buttonName').value || '').trim(),
                slug: ($('buttonSlug') && $('buttonSlug').value || '').trim(),
                button_type: $('buttonType') ? $('buttonType').value : 'primary',
                background_color: $('buttonBgColor') ? $('buttonBgColor').value : '#007bff',
                text_color: $('buttonTextColor') ? $('buttonTextColor').value : '#ffffff',
                border_color: $('buttonBorderColor') ? $('buttonBorderColor').value : null,
                border_width: $('buttonBorderWidth') ? parseInt($('buttonBorderWidth').value) || 0 : 0,
                border_radius: $('buttonBorderRadius') ? parseInt($('buttonBorderRadius').value) || 4 : 4,
                padding: ($('buttonPadding') && $('buttonPadding').value || '10px 20px').trim(),
                font_size: ($('buttonFontSize') && $('buttonFontSize').value || '14px').trim(),
                font_weight: ($('buttonFontWeight') && $('buttonFontWeight').value || 'normal').trim(),
                hover_background_color: $('buttonHoverBg') ? $('buttonHoverBg').value : null,
                hover_text_color: $('buttonHoverText') ? $('buttonHoverText').value : null,
                hover_border_color: $('buttonHoverBorder') ? $('buttonHoverBorder').value : null,
                is_active: $('buttonIsActive') ? parseInt($('buttonIsActive').value) : 1
            };
        } else if (prefix === 'card') {
            return {
                theme_id: themeId,
                tenant_id: TENANT_ID,
                name: ($('cardName') && $('cardName').value || '').trim(),
                slug: ($('cardSlug') && $('cardSlug').value || '').trim(),
                card_type: $('cardType') ? $('cardType').value : 'product',
                background_color: $('cardBgColor') ? $('cardBgColor').value : '#FFFFFF',
                border_color: $('cardBorderColor') ? $('cardBorderColor').value : '#E0E0E0',
                border_width: $('cardBorderWidth') ? parseInt($('cardBorderWidth').value) || 1 : 1,
                border_radius: $('cardBorderRadius') ? parseInt($('cardBorderRadius').value) || 8 : 8,
                shadow_style: ($('cardShadow') && $('cardShadow').value || 'none').trim(),
                padding: ($('cardPadding') && $('cardPadding').value || '16px').trim(),
                hover_effect: $('cardHoverEffect') ? $('cardHoverEffect').value : 'none',
                text_align: $('cardTextAlign') ? $('cardTextAlign').value : 'left',
                image_aspect_ratio: ($('cardImageRatio') && $('cardImageRatio').value || '1:1').trim(),
                is_active: $('cardIsActive') ? parseInt($('cardIsActive').value) : 1
            };
        } else if (prefix === 'section') {
            return {
                theme_id: themeId,
                tenant_id: TENANT_ID,
                section_type: $('sectionType') ? $('sectionType').value : 'other',
                title: ($('sectionTitle') && $('sectionTitle').value || '').trim() || null,
                subtitle: ($('sectionSubtitle') && $('sectionSubtitle').value || '').trim() || null,
                layout_type: $('sectionLayout') ? $('sectionLayout').value : 'grid',
                items_per_row: $('sectionItemsPerRow') ? parseInt($('sectionItemsPerRow').value) || 4 : 4,
                background_color: $('sectionBgColor') ? $('sectionBgColor').value : '#FFFFFF',
                text_color: $('sectionTextColor') ? $('sectionTextColor').value : '#000000',
                padding: ($('sectionPadding') && $('sectionPadding').value || '40px 0').trim(),
                custom_css: ($('sectionCustomCss') && $('sectionCustomCss').value || '').trim() || null,
                custom_html: ($('sectionCustomHtml') && $('sectionCustomHtml').value || '').trim() || null,
                data_source: ($('sectionDataSource') && $('sectionDataSource').value || '').trim() || null,
                is_active: $('sectionIsActive') ? ($('sectionIsActive').checked ? 1 : 0) : 1,
                sort_order: $('sectionSortOrder') ? parseInt($('sectionSortOrder').value) || 0 : 0
            };
        } else if (prefix === 'system') {
            return {
                tenant_id: TENANT_ID,
                setting_key: ($('systemKey') && $('systemKey').value || '').trim(),
                setting_value: ($('systemValue') && $('systemValue').value || '').trim(),
                setting_type: $('systemType') ? $('systemType').value : 'text',
                category: ($('systemCategory') && $('systemCategory').value || '').trim(),
                description: ($('systemDescription') && $('systemDescription').value || '').trim() || null,
                is_public: $('systemIsPublic') ? parseInt($('systemIsPublic').value) : 0,
                is_editable: $('systemIsEditable') ? parseInt($('systemIsEditable').value) : 1
            };
        }
        return {};
    }

    function populateSettingForm(prefix, item) {
        const $ = id => document.getElementById(id);
        const idField = $(prefix + 'Id');
        if (idField) idField.value = item.id;

        if (prefix === 'design') {
            if ($('designKey')) $('designKey').value = item.setting_key || '';
            if ($('designName')) $('designName').value = item.setting_name || '';
            if ($('designValue')) $('designValue').value = item.setting_value || '';
            if ($('designType')) $('designType').value = item.setting_type || 'text';
            if ($('designCategory')) $('designCategory').value = item.category || 'other';
            if ($('designIsActive')) $('designIsActive').value = item.is_active != null ? String(item.is_active) : '1';
            if ($('designSortOrder')) $('designSortOrder').value = item.sort_order || 0;
        } else if (prefix === 'color') {
            if ($('colorKey')) $('colorKey').value = item.setting_key || '';
            if ($('colorName')) $('colorName').value = item.setting_name || '';
            if ($('colorValue')) $('colorValue').value = item.color_value || '#000000';
            if ($('colorCategory')) $('colorCategory').value = item.category || 'other';
            if ($('colorIsActive')) $('colorIsActive').value = item.is_active != null ? String(item.is_active) : '1';
            if ($('colorSortOrder')) $('colorSortOrder').value = item.sort_order || 0;
        } else if (prefix === 'font') {
            if ($('fontKey')) $('fontKey').value = item.setting_key || '';
            if ($('fontName')) $('fontName').value = item.setting_name || '';
            if ($('fontFamily')) $('fontFamily').value = item.font_family || '';
            if ($('fontSize')) $('fontSize').value = item.font_size || '';
            if ($('fontWeight')) $('fontWeight').value = item.font_weight || '';
            if ($('fontLineHeight')) $('fontLineHeight').value = item.line_height || '';
            if ($('fontCategory')) $('fontCategory').value = item.category || 'other';
            if ($('fontIsActive')) $('fontIsActive').value = item.is_active != null ? String(item.is_active) : '1';
            if ($('fontSortOrder')) $('fontSortOrder').value = item.sort_order || 0;
        } else if (prefix === 'button') {
            if ($('buttonName')) $('buttonName').value = item.name || '';
            if ($('buttonSlug')) $('buttonSlug').value = item.slug || '';
            if ($('buttonType')) $('buttonType').value = item.button_type || 'primary';
            if ($('buttonBgColor')) $('buttonBgColor').value = item.background_color || '#007bff';
            if ($('buttonTextColor')) $('buttonTextColor').value = item.text_color || '#ffffff';
            if ($('buttonBorderColor')) $('buttonBorderColor').value = item.border_color || '#000000';
            if ($('buttonBorderWidth')) $('buttonBorderWidth').value = item.border_width || 0;
            if ($('buttonBorderRadius')) $('buttonBorderRadius').value = item.border_radius || 4;
            if ($('buttonPadding')) $('buttonPadding').value = item.padding || '10px 20px';
            if ($('buttonFontSize')) $('buttonFontSize').value = item.font_size || '14px';
            if ($('buttonFontWeight')) $('buttonFontWeight').value = item.font_weight || 'normal';
            if ($('buttonHoverBg')) $('buttonHoverBg').value = item.hover_background_color || '#000000';
            if ($('buttonHoverText')) $('buttonHoverText').value = item.hover_text_color || '#000000';
            if ($('buttonHoverBorder')) $('buttonHoverBorder').value = item.hover_border_color || '#000000';
            if ($('buttonIsActive')) $('buttonIsActive').value = item.is_active != null ? String(item.is_active) : '1';
        } else if (prefix === 'card') {
            if ($('cardName')) $('cardName').value = item.name || '';
            if ($('cardSlug')) $('cardSlug').value = item.slug || '';
            if ($('cardType')) $('cardType').value = item.card_type || 'product';
            if ($('cardBgColor')) $('cardBgColor').value = item.background_color || '#FFFFFF';
            if ($('cardBorderColor')) $('cardBorderColor').value = item.border_color || '#E0E0E0';
            if ($('cardBorderWidth')) $('cardBorderWidth').value = item.border_width || 1;
            if ($('cardBorderRadius')) $('cardBorderRadius').value = item.border_radius || 8;
            if ($('cardShadow')) $('cardShadow').value = item.shadow_style || 'none';
            if ($('cardPadding')) $('cardPadding').value = item.padding || '16px';
            if ($('cardHoverEffect')) $('cardHoverEffect').value = item.hover_effect || 'none';
            if ($('cardTextAlign')) $('cardTextAlign').value = item.text_align || 'left';
            if ($('cardImageRatio')) $('cardImageRatio').value = item.image_aspect_ratio || '1:1';
            if ($('cardIsActive')) $('cardIsActive').value = item.is_active != null ? String(item.is_active) : '1';
        } else if (prefix === 'section') {
            if ($('sectionType')) $('sectionType').value = item.section_type || 'other';
            if ($('sectionTitle')) $('sectionTitle').value = item.title || '';
            if ($('sectionSubtitle')) $('sectionSubtitle').value = item.subtitle || '';
            if ($('sectionLayout')) $('sectionLayout').value = item.layout_type || 'grid';
            if ($('sectionItemsPerRow')) $('sectionItemsPerRow').value = item.items_per_row || 4;
            if ($('sectionSortOrder')) $('sectionSortOrder').value = item.sort_order || 0;
            if ($('sectionBgColor')) $('sectionBgColor').value = item.background_color || '#FFFFFF';
            if ($('sectionTextColor')) $('sectionTextColor').value = item.text_color || '#000000';
            if ($('sectionPadding')) $('sectionPadding').value = item.padding || '40px 0';
            if ($('sectionDataSource')) $('sectionDataSource').value = item.data_source || '';
            if ($('sectionCustomCss')) $('sectionCustomCss').value = item.custom_css || '';
            if ($('sectionCustomHtml')) $('sectionCustomHtml').value = item.custom_html || '';
            if ($('sectionIsActive')) $('sectionIsActive').checked = !!item.is_active;
        } else if (prefix === 'system') {
            if ($('systemKey')) $('systemKey').value = item.setting_key || '';
            if ($('systemValue')) $('systemValue').value = item.setting_value || '';
            if ($('systemType')) $('systemType').value = item.setting_type || 'text';
            if ($('systemCategory')) $('systemCategory').value = item.category || '';
            if ($('systemDescription')) $('systemDescription').value = item.description || '';
            if ($('systemIsPublic')) $('systemIsPublic').value = item.is_public != null ? String(item.is_public) : '0';
            if ($('systemIsEditable')) $('systemIsEditable').value = item.is_editable != null ? String(item.is_editable) : '1';
        }
    }

    async function saveSetting(prefix) {
        const apiUrl = getApiForType(prefix);
        if (!apiUrl) return;

        const idField = document.getElementById(prefix + 'Id');
        const itemId = idField ? idField.value : '';
        const isEdit = !!itemId;
        const data = collectSettingData(prefix);

        if (isEdit) data.id = parseInt(itemId);

        try {
            const url = isEdit ? apiUrl + '?id=' + itemId : apiUrl;
            const res = await fetch(url, {
                method: isEdit ? 'PUT' : 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });
            const json = await res.json();
            if (json.success) {
                showAlert('success', 'Saved successfully');
                const form = document.getElementById(prefix + 'Form');
                if (form) form.style.display = 'none';
                // Reload this settings list
                const themeId = el.themeId ? el.themeId.value : state.editingThemeId;
                if (themeId) await loadSettings(prefix, apiUrl, themeId);
            } else {
                showAlert('error', json.message || 'Failed to save');
            }
        } catch (e) {
            console.error('[ThemesSystem] saveSetting(' + prefix + ') error:', e);
            showAlert('error', 'Failed to save');
        }
    }

    function editSetting(type, itemId) {
        const stateKey = type + 'Settings';
        const items = state[stateKey] || [];
        const item = items.find(i => String(i.id) === String(itemId));
        if (!item) return;

        resetSettingForm(type);
        populateSettingForm(type, item);
        const form = document.getElementById(type + 'Form');
        if (form) form.style.display = 'block';

        // Switch to the correct tab
        const tabMap = { design: 'design', color: 'colors', font: 'fonts', button: 'buttons', card: 'cards', section: 'homepage', system: 'system' };
        const tabName = tabMap[type];
        if (tabName) {
            document.querySelectorAll('.themes-page .form-tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.themes-page .tab-content').forEach(c => {
                c.style.display = 'none';
                c.classList.remove('active');
            });
            const tabBtn = document.querySelector('.themes-page .form-tab[data-tab="' + tabName + '"]');
            const tabContent = document.getElementById('tab-' + tabName);
            if (tabBtn) tabBtn.classList.add('active');
            if (tabContent) { tabContent.style.display = 'block'; tabContent.classList.add('active'); }
        }
    }

    async function deleteSetting(type, itemId) {
        if (!confirm('Are you sure you want to delete this item?')) return;
        const apiUrl = getApiForType(type);
        if (!apiUrl) return;

        try {
            const res = await fetch(apiUrl + '?id=' + itemId, {
                method: 'DELETE',
                headers: { 'Content-Type': 'application/json' }
            });
            const json = await res.json();
            if (json.success) {
                showAlert('success', 'Deleted successfully');
                const themeId = el.themeId ? el.themeId.value : state.editingThemeId;
                if (themeId) await loadSettings(type, apiUrl, themeId);
            } else {
                showAlert('error', json.message || 'Failed to delete');
            }
        } catch (e) {
            showAlert('error', 'Failed to delete');
        }
    }

    // ════════════════════════════════════════
    // UTILITIES
    // ════════════════════════════════════════
    function showLoading(show) {
        if (el.loading) el.loading.style.display = show ? 'flex' : 'none';
        if (el.tableContainer && show) el.tableContainer.style.display = 'none';
        if (el.empty && show) el.empty.style.display = 'none';
    }

    function showAlert(type, message) {
        if (!el.alertsContainer) return;
        const alertClass = type === 'error' ? 'alert-danger' : type === 'warning' ? 'alert-warning' : 'alert-success';
        const alertEl = document.createElement('div');
        alertEl.className = 'alert ' + alertClass;
        alertEl.innerHTML = '<span>' + escapeHtml(message) + '</span>' +
                           '<button class="alert-close" onclick="this.parentElement.remove()">&times;</button>';
        el.alertsContainer.appendChild(alertEl);
        setTimeout(() => { if (alertEl.parentElement) alertEl.remove(); }, 5000);
    }

    function escapeHtml(str) {
        if (!str) return '';
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    // ════════════════════════════════════════
    // PUBLIC API
    // ════════════════════════════════════════
    window.ThemesSystem = {
        init: init,
        editTheme: function(id) { showForm(id); },
        removeTheme: removeTheme,
        editSetting: editSetting,
        deleteSetting: deleteSetting
    };

})();