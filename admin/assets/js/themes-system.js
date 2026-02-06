(function () {
    'use strict';

    console.log('%c[Themes] Initializing...', 'color:#3b82f6;font-weight:bold');

    // ════════════════════════════════════════════════════════════
    // CONFIG & STATE
    // ════════════════════════════════════════════════════════════

    const APP_CONFIG = window.APP_CONFIG || {
        API_BASE: '/api',
        TENANT_ID: 1,
        CSRF_TOKEN: ''
    };

    const APP_STATE = {
        themes: [],
        selectedThemeId: null,
        currentTab: 'themes'
    };

    // ════════════════════════════════════════════════════════════
    // API HELPERS
    // ════════════════════════════════════════════════════════════

    async function apiCall(endpoint, options = {}) {
        const url = `${APP_CONFIG.API_BASE}${endpoint}`;

        const config = {
            method: options.method || 'GET',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': APP_CONFIG.CSRF_TOKEN
            },
            credentials: 'same-origin'
        };

        if (options.body) {
            config.body = JSON.stringify(options.body);
        }

        console.log(`[API] ${config.method} ${url}`, options.body || '');

        try {
            const response = await fetch(url, config);
            const text = await response.text();

            console.log(`[API] Response (${response.status}):`, text.substring(0, 300));

            // Debug full response
            console.log(`[API] Full Response:`, {
                status: response.status,
                statusText: response.statusText,
                headers: Object.fromEntries(response.headers.entries()),
                body: text.substring(0, 500)
            });

            // Check if response is empty
            if (!text.trim()) {
                throw new Error('Empty response from server');
            }

            // Check if response is HTML (error page)
            if (text.trim().startsWith('<')) {
                console.error('[API] Received HTML response instead of JSON:', text.substring(0, 200));
                throw new Error('Server returned HTML instead of JSON (check API endpoint)');
            }

            const data = JSON.parse(text);

            if (!response.ok || !data.success) {
                throw new Error(data.message || 'Request failed');
            }

            return data;
        } catch (error) {
            console.error('[API] Error:', error);

            // If it's a JSON parse error, provide more details
            if (error instanceof SyntaxError) {
                console.error('[API] JSON Parse Error. Raw response:', error);
                throw new Error('Invalid JSON response from server: ' + error.message);
            }

            throw error;
        }
    }

    // ════════════════════════════════════════════════════════════
    // UI HELPERS
    // ════════════════════════════════════════════════════════════

    function showAlert(type, message) {
        const container = document.getElementById('alertsContainer');
        if (!container) return;

        const alert = document.createElement('div');
        alert.className = `alert alert-${type}`;
        alert.innerHTML = `
            <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
            <span>${escapeHtml(message)}</span>
        `;

        container.appendChild(alert);

        setTimeout(() => {
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 300);
        }, 5000);
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text || '';
        return div.innerHTML;
    }

    function formatDate(dateStr) {
        if (!dateStr) return '-';
        const date = new Date(dateStr);
        return date.toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric'
        });
    }

    // ════════════════════════════════════════════════════════════
    // TABS
    // ════════════════════════════════════════════════════════════

    function initTabs() {
        document.querySelectorAll('.main-tab').forEach(tab => {
            tab.addEventListener('click', function () {
                const tabName = this.dataset.tab;

                document.querySelectorAll('.main-tab').forEach(t => t.classList.remove('active'));
                this.classList.add('active');

                document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
                const content = document.getElementById('tab-' + tabName);
                if (content) content.classList.add('active');

                APP_STATE.currentTab = tabName;

                // Auto-load data for each tab
                if (tabName === 'design') loadDesignThemes();
                if (tabName === 'colors') loadColorsThemes();
                if (tabName === 'fonts') loadFontsThemes();
                if (tabName === 'buttons') loadButtons();
                if (tabName === 'cards') loadCards();
                if (tabName === 'homepage') loadHomepageSections();
            });
        });
    }

    // ════════════════════════════════════════════════════════════
    // THEMES MANAGEMENT
    // ════════════════════════════════════════════════════════════

    async function loadThemes() {
        const loading = document.getElementById('themesLoading');
        const content = document.getElementById('themesContent');
        const empty = document.getElementById('themesEmpty');
        const grid = document.getElementById('themesGrid');

        if (!loading || !grid) return;

        loading.style.display = 'block';
        content.style.display = 'none';
        empty.style.display = 'none';

        try {
            const data = await apiCall(`/themes?tenant_id=${APP_CONFIG.TENANT_ID}`);

            if (!data || !data.data) {
                throw new Error('Invalid data format received');
            }

            APP_STATE.themes = data.data || [];

            if (APP_STATE.themes.length === 0) {
                loading.style.display = 'none';
                empty.style.display = 'block';
                return;
            }

            grid.innerHTML = APP_STATE.themes.map(theme => `
                <div class="theme-card ${theme.is_active ? 'active' : ''} ${theme.is_default ? 'default' : ''}">
                    ${theme.thumbnail_url ? `<img src="${escapeHtml(theme.thumbnail_url)}" alt="${escapeHtml(theme.name)}" class="theme-thumbnail">` : '<div class="theme-thumbnail-placeholder"><i class="fas fa-palette"></i></div>'}
                    <div class="theme-info">
                        <h4 class="theme-name">${escapeHtml(theme.name)}</h4>
                        <p class="theme-slug">${escapeHtml(theme.slug)}</p>
                        <p class="theme-description">${escapeHtml(theme.description || 'No description')}</p>
                        <div class="theme-meta">
                            <span class="theme-version">v${escapeHtml(theme.version)}</span>
                            <span class="theme-author">by ${escapeHtml(theme.author || 'Unknown')}</span>
                        </div>
                        <div class="theme-badges">
                            ${theme.is_active ? '<span class="badge badge-success">Active</span>' : ''}
                            ${theme.is_default ? '<span class="badge badge-primary">Default</span>' : ''}
                        </div>
                    </div>
                    <div class="theme-actions">
                        <button class="btn btn-sm btn-primary" onclick="ThemesApp.editTheme(${theme.id})">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn btn-sm btn-danger" onclick="ThemesApp.deleteTheme(${theme.id}, '${escapeHtml(theme.name).replace(/'/g, "\\'")}')">
                            <i class="fas fa-trash"></i>
                        </button>
                        ${theme.preview_url ? `<a href="${escapeHtml(theme.preview_url)}" target="_blank" class="btn btn-sm btn-secondary"><i class="fas fa-eye"></i></a>` : ''}
                    </div>
                </div>
            `).join('');

            loading.style.display = 'none';
            content.style.display = 'block';

        } catch (error) {
            console.error('[Themes] Error:', error);
            showAlert('error', `Failed to load themes: ${error.message}`);
            loading.style.display = 'none';
            empty.style.display = 'block';
        }
    }

    function openThemeModal(themeId = null) {
        const modal = document.getElementById('themeModal');
        const title = document.getElementById('themeModalTitle');

        document.getElementById('themeForm').reset();
        document.getElementById('themeId').value = '';

        if (themeId) {
            const theme = APP_STATE.themes.find(t => t.id === themeId);
            if (theme) {
                title.textContent = 'Edit Theme';
                document.getElementById('themeId').value = theme.id;
                document.getElementById('themeName').value = theme.name;
                document.getElementById('themeSlug').value = theme.slug;
                document.getElementById('themeDescription').value = theme.description || '';
                document.getElementById('themeVersion').value = theme.version;
                document.getElementById('themeAuthor').value = theme.author || '';
                document.getElementById('themeThumbnailUrl').value = theme.thumbnail_url || '';
                document.getElementById('themePreviewUrl').value = theme.preview_url || '';
                document.getElementById('themeIsActive').checked = theme.is_active;
                document.getElementById('themeIsDefault').checked = theme.is_default;
            }
        } else {
            title.textContent = 'Add New Theme';
        }

        modal.classList.add('active');
    }

    function closeThemeModal() {
        document.getElementById('themeModal').classList.remove('active');
    }

    async function saveTheme() {
        const themeId = document.getElementById('themeId').value;
        const name = document.getElementById('themeName').value.trim();
        const slug = document.getElementById('themeSlug').value.trim();
        const description = document.getElementById('themeDescription').value.trim();
        const version = document.getElementById('themeVersion').value.trim();
        const author = document.getElementById('themeAuthor').value.trim();
        const thumbnailUrl = document.getElementById('themeThumbnailUrl').value.trim();
        const previewUrl = document.getElementById('themePreviewUrl').value.trim();
        const isActive = document.getElementById('themeIsActive').checked;
        const isDefault = document.getElementById('themeIsDefault').checked;

        if (!name || !slug) {
            showAlert('warning', 'Please fill required fields');
            return;
        }

        try {
            const payload = {
                tenant_id: APP_CONFIG.TENANT_ID,
                name: name,
                slug: slug,
                description: description,
                version: version,
                author: author,
                thumbnail_url: thumbnailUrl,
                preview_url: previewUrl,
                is_active: isActive ? 1 : 0,
                is_default: isDefault ? 1 : 0
            };

            if (themeId) {
                payload.id = parseInt(themeId);
                await apiCall(`/themes/${payload.id}`, { method: 'PUT', body: payload });
            } else {
                await apiCall('/themes', { method: 'POST', body: payload });
            }

            showAlert('success', themeId ? 'Theme updated!' : 'Theme created!');
            closeThemeModal();
            loadThemes();

        } catch (error) {
            showAlert('error', 'Failed to save: ' + error.message);
        }
    }

    async function deleteTheme(themeId, themeName) {
        if (!confirm(`Delete theme "${themeName}"?`)) return;

        try {
            await apiCall(`/themes/${themeId}`, {
                method: 'DELETE',
                body: { tenant_id: APP_CONFIG.TENANT_ID }
            });

            showAlert('success', 'Theme deleted!');
            loadThemes();

        } catch (error) {
            showAlert('error', 'Failed to delete: ' + error.message);
        }
    }

    // ════════════════════════════════════════════════════════════
    // DESIGN SETTINGS
    // ════════════════════════════════════════════════════════════

    async function loadDesignThemes() {
        const selector = document.getElementById('designThemeSelector');
        if (!selector) return;

        if (APP_STATE.themes.length === 0) await loadThemes();

        selector.innerHTML = APP_STATE.themes.map(theme => `
            <div class="theme-card" onclick="ThemesApp.selectDesignTheme(${theme.id}, '${escapeHtml(theme.name).replace(/'/g, "\\'")}')">
                <div class="theme-icon">
                    <i class="fas fa-cog"></i>
                </div>
                <div class="theme-name">${escapeHtml(theme.name)}</div>
                <div class="theme-slug">${escapeHtml(theme.slug)}</div>
            </div>
        `).join('');
    }

    async function selectDesignTheme(themeId, themeName) {
        APP_STATE.selectedThemeId = themeId;

        document.querySelectorAll('#designThemeSelector .theme-card').forEach(c => c.classList.remove('selected'));
        event.currentTarget.classList.add('selected');

        document.getElementById('designThemeName').textContent = themeName;
        document.getElementById('designCard').style.display = 'block';

        const loading = document.getElementById('designLoading');
        const content = document.getElementById('designContent');
        const grid = document.getElementById('designSettingsGrid');

        loading.style.display = 'block';
        content.style.display = 'none';

        try {
            const data = await apiCall(`/design_settings?theme_id=${themeId}`);
            const settings = data.data || [];

            grid.innerHTML = settings.map(setting => `
                <div class="setting-item">
                    <label class="setting-label">${escapeHtml(setting.setting_name)}</label>
                    <div class="setting-input">
                        ${getSettingInput(setting)}
                    </div>
                    <small class="setting-desc">${escapeHtml(setting.category)}</small>
                </div>
            `).join('');

            loading.style.display = 'none';
            content.style.display = 'block';

        } catch (error) {
            showAlert('error', 'Failed to load design settings: ' + error.message);
            loading.style.display = 'none';
        }
    }

    function getSettingInput(setting) {
        const id = `setting_${setting.id}`;

        switch (setting.setting_type) {
            case 'text':
                return `<input type="text" id="${id}" class="form-control" value="${escapeHtml(setting.setting_value || '')}">`;
            case 'number':
                return `<input type="number" id="${id}" class="form-control" value="${setting.setting_value || 0}">`;
            case 'color':
                return `<input type="color" id="${id}" class="form-control" value="${setting.setting_value || '#000000'}">`;
            case 'boolean':
                return `<input type="checkbox" id="${id}" class="checkbox" ${setting.setting_value ? 'checked' : ''}>`;
            case 'select':
                return `<select id="${id}" class="form-control">
                    <option value="option1" ${setting.setting_value === 'option1' ? 'selected' : ''}>Option 1</option>
                    <option value="option2" ${setting.setting_value === 'option2' ? 'selected' : ''}>Option 2</option>
                </select>`;
            default:
                return `<input type="text" id="${id}" class="form-control" value="${escapeHtml(setting.setting_value || '')}">`;
        }
    }

    async function saveDesignSettings() {
        if (!APP_STATE.selectedThemeId) return;

        try {
            const updates = [];

            document.querySelectorAll('#designSettingsGrid .setting-item').forEach(item => {
                const input = item.querySelector('input, select');
                if (!input) return;

                const settingId = parseInt(input.id.replace('setting_', ''));
                let value = input.type === 'checkbox' ? (input.checked ? 1 : 0) : input.value;

                updates.push({
                    id: settingId,
                    setting_value: value
                });
            });

            await apiCall('/design_settings', {
                method: 'PUT',
                body: { updates }
            });

            showAlert('success', `Updated ${updates.length} design settings!`);

        } catch (error) {
            showAlert('error', 'Failed to save: ' + error.message);
        }
    }

    // ════════════════════════════════════════════════════════════
    // COLORS, FONTS, BUTTONS, CARDS, HOMEPAGE - COMPLETE
    // ════════════════════════════════════════════════════════════

    async function loadColorsThemes() {
        const selector = document.getElementById('colorsThemeSelector');
        if (!selector) return;

        if (APP_STATE.themes.length === 0) await loadThemes();

        selector.innerHTML = APP_STATE.themes.map(theme => `
            <div class="theme-card" onclick="ThemesApp.selectColorsTheme(${theme.id}, '${escapeHtml(theme.name).replace(/'/g, "\\'")}')">
                <div class="theme-icon">
                    <i class="fas fa-paint-brush"></i>
                </div>
                <div class="theme-name">${escapeHtml(theme.name)}</div>
                <div class="theme-slug">${escapeHtml(theme.slug)}</div>
            </div>
        `).join('');
    }

    async function selectColorsTheme(themeId, themeName) {
        APP_STATE.selectedThemeId = themeId;

        document.querySelectorAll('#colorsThemeSelector .theme-card').forEach(c => c.classList.remove('selected'));
        event.currentTarget.classList.add('selected');

        document.getElementById('colorsThemeName').textContent = themeName;
        document.getElementById('colorsCard').style.display = 'block';

        const loading = document.getElementById('colorsLoading');
        const content = document.getElementById('colorsContent');
        const grid = document.getElementById('colorSettingsGrid');

        loading.style.display = 'block';
        content.style.display = 'none';

        try {
            const data = await apiCall(`/color_settings?theme_id=${themeId}`);
            const settings = data.data || [];

            grid.innerHTML = settings.map(setting => `
                <div class="color-setting-item">
                    <label class="setting-label">${escapeHtml(setting.setting_name)}</label>
                    <div class="color-input-group">
                        <input type="color" id="color_${setting.id}" class="form-control color-picker" value="${setting.color_value || '#000000'}">
                        <input type="text" class="form-control color-hex" value="${setting.color_value || '#000000'}" readonly>
                    </div>
                    <small class="setting-desc">${escapeHtml(setting.category)}</small>
                </div>
            `).join('');

            // Bind color picker events
            document.querySelectorAll('.color-picker').forEach(picker => {
                picker.addEventListener('input', function () {
                    const hexInput = this.parentElement.querySelector('.color-hex');
                    hexInput.value = this.value;
                });
            });

            loading.style.display = 'none';
            content.style.display = 'block';

        } catch (error) {
            showAlert('error', 'Failed to load color settings: ' + error.message);
            loading.style.display = 'none';
        }
    }

    async function saveColorSettings() {
        if (!APP_STATE.selectedThemeId) return;

        try {
            const updates = [];

            document.querySelectorAll('#colorSettingsGrid .color-setting-item').forEach(item => {
                const picker = item.querySelector('.color-picker');
                if (!picker) return;

                const settingId = parseInt(picker.id.replace('color_', ''));
                const value = picker.value;

                updates.push({
                    id: settingId,
                    color_value: value
                });
            });

            await apiCall('/color_settings', {
                method: 'PUT',
                body: { updates }
            });

            showAlert('success', `Updated ${updates.length} color settings!`);

        } catch (error) {
            showAlert('error', 'Failed to save: ' + error.message);
        }
    }

    async function loadFontsThemes() {
        const selector = document.getElementById('fontsThemeSelector');
        if (!selector) return;

        if (APP_STATE.themes.length === 0) await loadThemes();

        selector.innerHTML = APP_STATE.themes.map(theme => `
            <div class="theme-card" onclick="ThemesApp.selectFontsTheme(${theme.id}, '${escapeHtml(theme.name).replace(/'/g, "\\'")}')">
                <div class="theme-icon">
                    <i class="fas fa-font"></i>
                </div>
                <div class="theme-name">${escapeHtml(theme.name)}</div>
                <div class="theme-slug">${escapeHtml(theme.slug)}</div>
            </div>
        `).join('');
    }

    async function selectFontsTheme(themeId, themeName) {
        APP_STATE.selectedThemeId = themeId;

        document.querySelectorAll('#fontsThemeSelector .theme-card').forEach(c => c.classList.remove('selected'));
        event.currentTarget.classList.add('selected');

        document.getElementById('fontsThemeName').textContent = themeName;
        document.getElementById('fontsCard').style.display = 'block';

        const loading = document.getElementById('fontsLoading');
        const content = document.getElementById('fontsContent');
        const grid = document.getElementById('fontSettingsGrid');

        loading.style.display = 'block';
        content.style.display = 'none';

        try {
            const data = await apiCall(`/font_settings?theme_id=${themeId}`);
            const settings = data.data || [];

            grid.innerHTML = settings.map(setting => `
                <div class="font-setting-item">
                    <label class="setting-label">${escapeHtml(setting.setting_name)}</label>
                    <div class="font-inputs">
                        <select id="font_family_${setting.id}" class="form-control font-family">
                            <option value="Arial" ${setting.font_family === 'Arial' ? 'selected' : ''}>Arial</option>
                            <option value="Helvetica" ${setting.font_family === 'Helvetica' ? 'selected' : ''}>Helvetica</option>
                            <option value="Times New Roman" ${setting.font_family === 'Times New Roman' ? 'selected' : ''}>Times New Roman</option>
                            <option value="Georgia" ${setting.font_family === 'Georgia' ? 'selected' : ''}>Georgia</option>
                        </select>
                        <input type="text" id="font_size_${setting.id}" class="form-control font-size" placeholder="14px" value="${setting.font_size || ''}">
                        <input type="text" id="font_weight_${setting.id}" class="form-control font-weight" placeholder="normal" value="${setting.font_weight || ''}">
                        <input type="text" id="line_height_${setting.id}" class="form-control line-height" placeholder="1.5" value="${setting.line_height || ''}">
                    </div>
                    <small class="setting-desc">${escapeHtml(setting.category)}</small>
                </div>
            `).join('');

            loading.style.display = 'none';
            content.style.display = 'block';

        } catch (error) {
            showAlert('error', 'Failed to load font settings: ' + error.message);
            loading.style.display = 'none';
        }
    }

    async function saveFontSettings() {
        if (!APP_STATE.selectedThemeId) return;

        try {
            const updates = [];

            document.querySelectorAll('#fontSettingsGrid .font-setting-item').forEach(item => {
                const family = item.querySelector('.font-family');
                const size = item.querySelector('.font-size');
                const weight = item.querySelector('.font-weight');
                const height = item.querySelector('.line-height');

                if (!family) return;

                const settingId = parseInt(family.id.replace('font_family_', ''));

                updates.push({
                    id: settingId,
                    font_family: family.value,
                    font_size: size.value,
                    font_weight: weight.value,
                    line_height: height.value
                });
            });

            await apiCall('/font_settings', {
                method: 'PUT',
                body: { updates }
            });

            showAlert('success', `Updated ${updates.length} font settings!`);

        } catch (error) {
            showAlert('error', 'Failed to save: ' + error.message);
        }
    }

    async function loadButtons() {
        const loading = document.getElementById('buttonsLoading');
        const content = document.getElementById('buttonsContent');
        const empty = document.getElementById('buttonsEmpty');
        const grid = document.getElementById('buttonStylesGrid');

        if (!loading || !grid) return;

        loading.style.display = 'block';
        content.style.display = 'none';
        empty.style.display = 'none';

        try {
            const data = await apiCall(`/button_styles?tenant_id=${APP_CONFIG.TENANT_ID}`);

            const buttons = data.data || [];

            if (buttons.length === 0) {
                loading.style.display = 'none';
                empty.style.display = 'block';
                return;
            }

            grid.innerHTML = buttons.map(button => `
                <div class="button-style-card">
                    <div class="button-preview">
                        <button style="
                            background-color: ${button.background_color};
                            color: ${button.text_color};
                            border: ${button.border_width}px solid ${button.border_color};
                            border-radius: ${button.border_radius}px;
                            padding: ${button.padding};
                            font-size: ${button.font_size};
                            font-weight: ${button.font_weight};
                        " onmouseover="this.style.backgroundColor='${button.hover_background_color || button.background_color}'"
                           onmouseout="this.style.backgroundColor='${button.background_color}'">
                            ${escapeHtml(button.name)}
                        </button>
                    </div>
                    <div class="button-style-info">
                        <h4>${escapeHtml(button.name)}</h4>
                        <p>${escapeHtml(button.button_type)}</p>
                    </div>
                    <div class="button-style-actions">
                        <button class="btn btn-sm btn-primary" onclick="ThemesApp.editButton(${button.id})">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn btn-sm btn-danger" onclick="ThemesApp.deleteButton(${button.id}, '${escapeHtml(button.name).replace(/'/g, "\\'")}')">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            `).join('');

            loading.style.display = 'none';
            content.style.display = 'block';

        } catch (error) {
            console.error('[Buttons] Error:', error);
            showAlert('error', 'Failed to load button styles: ' + error.message);
            loading.style.display = 'none';
            empty.style.display = 'block';
        }
    }

    async function openButtonModal(buttonId = null) {
        const modal = document.getElementById('buttonModal');
        const title = document.getElementById('buttonModalTitle');

        document.getElementById('buttonForm').reset();
        document.getElementById('buttonId').value = '';

        if (buttonId) {
            title.textContent = 'Edit Button Style';
            try {
                const data = await apiCall(`/button_styles/${buttonId}`);
                const button = data.data;

                document.getElementById('buttonId').value = button.id;
                document.getElementById('buttonName').value = button.name || '';
                document.getElementById('buttonSlug').value = button.slug || '';
                document.getElementById('buttonType').value = button.button_type || 'primary';
                document.getElementById('buttonBgColor').value = button.background_color || '#007bff';
                document.getElementById('buttonTextColor').value = button.text_color || '#ffffff';
                document.getElementById('buttonBorderColor').value = button.border_color || '#007bff';
                document.getElementById('buttonBorderWidth').value = button.border_width || 0;
                document.getElementById('buttonBorderRadius').value = button.border_radius || 4;
                document.getElementById('buttonPadding').value = button.padding || '10px 20px';
                document.getElementById('buttonFontSize').value = button.font_size || '14px';
                document.getElementById('buttonFontWeight').value = button.font_weight || 'normal';
                document.getElementById('buttonHoverBgColor').value = button.hover_background_color || '';
                document.getElementById('buttonHoverTextColor').value = button.hover_text_color || '';
                document.getElementById('buttonHoverBorderColor').value = button.hover_border_color || '';
            } catch (error) {
                showAlert('error', 'Failed to load button: ' + error.message);
            }
        } else {
            title.textContent = 'Add New Button Style';
        }

        modal.classList.add('active');
    }

    function closeButtonModal() {
        document.getElementById('buttonModal').classList.remove('active');
    }

    async function saveButton() {
        const buttonId = document.getElementById('buttonId').value;
        const name = document.getElementById('buttonName').value.trim();
        const slug = document.getElementById('buttonSlug').value.trim();
        const buttonType = document.getElementById('buttonType').value;
        const bgColor = document.getElementById('buttonBgColor').value;
        const textColor = document.getElementById('buttonTextColor').value;
        const borderColor = document.getElementById('buttonBorderColor').value;
        const borderWidth = parseInt(document.getElementById('buttonBorderWidth').value) || 0;
        const borderRadius = parseInt(document.getElementById('buttonBorderRadius').value) || 4;
        const padding = document.getElementById('buttonPadding').value.trim();
        const fontSize = document.getElementById('buttonFontSize').value.trim();
        const fontWeight = document.getElementById('buttonFontWeight').value.trim();
        const hoverBgColor = document.getElementById('buttonHoverBgColor').value;
        const hoverTextColor = document.getElementById('buttonHoverTextColor').value;
        const hoverBorderColor = document.getElementById('buttonHoverBorderColor').value;

        if (!name || !slug) {
            showAlert('warning', 'Please fill required fields');
            return;
        }

        try {
            const payload = {
                tenant_id: APP_CONFIG.TENANT_ID,
                theme_id: APP_STATE.selectedThemeId,
                name: name,
                slug: slug,
                button_type: buttonType,
                background_color: bgColor,
                text_color: textColor,
                border_color: borderColor,
                border_width: borderWidth,
                border_radius: borderRadius,
                padding: padding,
                font_size: fontSize,
                font_weight: fontWeight,
                hover_background_color: hoverBgColor,
                hover_text_color: hoverTextColor,
                hover_border_color: hoverBorderColor
            };

            if (buttonId) {
                payload.id = parseInt(buttonId);
                await apiCall(`/button_styles/${payload.id}`, { method: 'PUT', body: payload });
            } else {
                await apiCall('/button_styles', { method: 'POST', body: payload });
            }

            showAlert('success', buttonId ? 'Button style updated!' : 'Button style created!');
            closeButtonModal();
            loadButtons();

        } catch (error) {
            showAlert('error', 'Failed to save: ' + error.message);
        }
    }

    async function deleteButton(buttonId, buttonName) {
        if (!confirm(`Delete button style "${buttonName}"?`)) return;

        try {
            await apiCall(`/button_styles/${buttonId}`, {
                method: 'DELETE',
                body: { tenant_id: APP_CONFIG.TENANT_ID }
            });

            showAlert('success', 'Button style deleted!');
            loadButtons();

        } catch (error) {
            showAlert('error', 'Failed to delete: ' + error.message);
        }
    }

    // ════════════════════════════════════════════════════════════
    // CARDS AND HOMEPAGE - SIMILAR TO BUTTONS
    // ════════════════════════════════════════════════════════════

    async function loadCards() {
        const loading = document.getElementById('cardsLoading');
        const content = document.getElementById('cardsContent');
        const empty = document.getElementById('cardsEmpty');
        const grid = document.getElementById('cardStylesGrid');

        if (!loading || !grid) return;

        loading.style.display = 'block';
        content.style.display = 'none';
        empty.style.display = 'none';

        try {
            const data = await apiCall(`/card_styles?tenant_id=${APP_CONFIG.TENANT_ID}`);

            const cards = data.data || [];

            if (cards.length === 0) {
                loading.style.display = 'none';
                empty.style.display = 'block';
                return;
            }

            grid.innerHTML = cards.map(card => `
                <div class="card-style-card">
                    <div class="card-preview" style="
                        background-color: ${card.background_color};
                        border: ${card.border_width}px solid ${card.border_color};
                        border-radius: ${card.border_radius}px;
                        box-shadow: ${card.shadow_style === 'small' ? '0 1px 3px rgba(0,0,0,0.12)' : card.shadow_style === 'medium' ? '0 4px 6px rgba(0,0,0,0.12)' : card.shadow_style === 'large' ? '0 10px 15px rgba(0,0,0,0.12)' : 'none'};
                        padding: ${card.padding};
                        width: 200px;
                        height: 100px;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        color: #666;
                        font-size: 14px;
                    ">
                        ${escapeHtml(card.name)}
                    </div>
                    <div class="card-style-info">
                        <h4>${escapeHtml(card.name)}</h4>
                        <p>${escapeHtml(card.card_type)}</p>
                    </div>
                    <div class="card-style-actions">
                        <button class="btn btn-sm btn-primary" onclick="ThemesApp.editCard(${card.id})">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn btn-sm btn-danger" onclick="ThemesApp.deleteCard(${card.id}, '${escapeHtml(card.name).replace(/'/g, "\\'")}')">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            `).join('');

            loading.style.display = 'none';
            content.style.display = 'block';

        } catch (error) {
            console.error('[Cards] Error:', error);
            showAlert('error', 'Failed to load card styles: ' + error.message);
            loading.style.display = 'none';
            empty.style.display = 'block';
        }
    }

    async function openCardModal(cardId = null) {
        const modal = document.getElementById('cardModal');
        const title = document.getElementById('cardModalTitle');

        document.getElementById('cardForm').reset();
        document.getElementById('cardId').value = '';

        if (cardId) {
            title.textContent = 'Edit Card Style';
            try {
                const data = await apiCall(`/card_styles/${cardId}`);
                const card = data.data;

                document.getElementById('cardId').value = card.id;
                document.getElementById('cardName').value = card.name || '';
                document.getElementById('cardSlug').value = card.slug || '';
                document.getElementById('cardType').value = card.card_type || 'product';
                document.getElementById('cardBgColor').value = card.background_color || '#ffffff';
                document.getElementById('cardBorderColor').value = card.border_color || '#e0e0e0';
                document.getElementById('cardBorderWidth').value = card.border_width || 1;
                document.getElementById('cardBorderRadius').value = card.border_radius || 8;
                document.getElementById('cardShadowStyle').value = card.shadow_style || 'none';
                document.getElementById('cardPadding').value = card.padding || '16px';
                document.getElementById('cardHoverEffect').value = card.hover_effect || 'none';
                document.getElementById('cardTextAlign').value = card.text_align || 'left';
                document.getElementById('cardAspectRatio').value = card.image_aspect_ratio || '1:1';
            } catch (error) {
                showAlert('error', 'Failed to load card: ' + error.message);
            }
        } else {
            title.textContent = 'Add New Card Style';
        }

        modal.classList.add('active');
    }

    function closeCardModal() {
        document.getElementById('cardModal').classList.remove('active');
    }

    async function saveCard() {
        const cardId = document.getElementById('cardId').value;
        const name = document.getElementById('cardName').value.trim();
        const slug = document.getElementById('cardSlug').value.trim();
        const cardType = document.getElementById('cardType').value;
        const bgColor = document.getElementById('cardBgColor').value;
        const borderColor = document.getElementById('cardBorderColor').value;
        const borderWidth = parseInt(document.getElementById('cardBorderWidth').value) || 1;
        const borderRadius = parseInt(document.getElementById('cardBorderRadius').value) || 8;
        const shadowStyle = document.getElementById('cardShadowStyle').value;
        const padding = document.getElementById('cardPadding').value.trim();
        const hoverEffect = document.getElementById('cardHoverEffect').value;
        const textAlign = document.getElementById('cardTextAlign').value;
        const aspectRatio = document.getElementById('cardAspectRatio').value.trim();

        if (!name || !slug) {
            showAlert('warning', 'Please fill required fields');
            return;
        }

        try {
            const payload = {
                tenant_id: APP_CONFIG.TENANT_ID,
                theme_id: APP_STATE.selectedThemeId,
                name: name,
                slug: slug,
                card_type: cardType,
                background_color: bgColor,
                border_color: borderColor,
                border_width: borderWidth,
                border_radius: borderRadius,
                shadow_style: shadowStyle,
                padding: padding,
                hover_effect: hoverEffect,
                text_align: textAlign,
                image_aspect_ratio: aspectRatio
            };

            if (cardId) {
                payload.id = parseInt(cardId);
                await apiCall(`/card_styles/${payload.id}`, { method: 'PUT', body: payload });
            } else {
                await apiCall('/card_styles', { method: 'POST', body: payload });
            }

            showAlert('success', cardId ? 'Card style updated!' : 'Card style created!');
            closeCardModal();
            loadCards();

        } catch (error) {
            showAlert('error', 'Failed to save: ' + error.message);
        }
    }

    async function deleteCard(cardId, cardName) {
        if (!confirm(`Delete card style "${cardName}"?`)) return;

        try {
            await apiCall(`/card_styles/${cardId}`, {
                method: 'DELETE',
                body: { tenant_id: APP_CONFIG.TENANT_ID }
            });

            showAlert('success', 'Card style deleted!');
            loadCards();

        } catch (error) {
            showAlert('error', 'Failed to delete: ' + error.message);
        }
    }

    async function loadHomepageSections() {
        const loading = document.getElementById('homepageLoading');
        const content = document.getElementById('homepageContent');
        const empty = document.getElementById('homepageEmpty');
        const list = document.getElementById('sectionsList');

        if (!loading || !list) return;

        loading.style.display = 'block';
        content.style.display = 'none';
        empty.style.display = 'none';

        try {
            const data = await apiCall(`/homepage_sections?tenant_id=${APP_CONFIG.TENANT_ID}`);

            const sections = data.data || [];

            if (sections.length === 0) {
                loading.style.display = 'none';
                empty.style.display = 'block';
                return;
            }

            list.innerHTML = sections.map(section => `
                <div class="section-item">
                    <div class="section-info">
                        <h4>${escapeHtml(section.title || section.section_type)}</h4>
                        <p>${escapeHtml(section.subtitle || '')}</p>
                        <small>Type: ${escapeHtml(section.section_type)} | Layout: ${escapeHtml(section.layout_type)} | Order: ${section.sort_order}</small>
                    </div>
                    <div class="section-actions">
                        <button class="btn btn-sm btn-primary" onclick="ThemesApp.editSection(${section.id})">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn btn-sm btn-danger" onclick="ThemesApp.deleteSection(${section.id}, '${escapeHtml(section.title || section.section_type).replace(/'/g, "\\'")}')">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            `).join('');

            loading.style.display = 'none';
            content.style.display = 'block';

        } catch (error) {
            console.error('[Homepage] Error:', error);
            showAlert('error', 'Failed to load homepage sections: ' + error.message);
            loading.style.display = 'none';
            empty.style.display = 'block';
        }
    }

    async function openSectionModal(sectionId = null) {
        const modal = document.getElementById('sectionModal');
        const title = document.getElementById('sectionModalTitle');

        document.getElementById('sectionForm').reset();
        document.getElementById('sectionId').value = '';

        if (sectionId) {
            title.textContent = 'Edit Homepage Section';
            try {
                const data = await apiCall(`/homepage_sections/${sectionId}`);
                const section = data.data;

                document.getElementById('sectionId').value = section.id;
                document.getElementById('sectionType').value = section.section_type || 'slider';
                document.getElementById('sectionTitle').value = section.title || '';
                document.getElementById('sectionSubtitle').value = section.subtitle || '';
                document.getElementById('sectionLayoutType').value = section.layout_type || 'grid';
                document.getElementById('sectionItemsPerRow').value = section.items_per_row || 4;
                document.getElementById('sectionBgColor').value = section.background_color || '#ffffff';
                document.getElementById('sectionTextColor').value = section.text_color || '#000000';
                document.getElementById('sectionPadding').value = section.padding || '40px 0';
                document.getElementById('sectionCustomCss').value = section.custom_css || '';
                document.getElementById('sectionCustomHtml').value = section.custom_html || '';
                document.getElementById('sectionDataSource').value = section.data_source || '';
                document.getElementById('sectionSortOrder').value = section.sort_order || 0;
            } catch (error) {
                showAlert('error', 'Failed to load section: ' + error.message);
            }
        } else {
            title.textContent = 'Add New Homepage Section';
        }

        modal.classList.add('active');
    }

    function closeSectionModal() {
        document.getElementById('sectionModal').classList.remove('active');
    }

    async function saveSection() {
        const sectionId = document.getElementById('sectionId').value;
        const sectionType = document.getElementById('sectionType').value;
        const title = document.getElementById('sectionTitle').value.trim();
        const subtitle = document.getElementById('sectionSubtitle').value.trim();
        const layoutType = document.getElementById('sectionLayoutType').value;
        const itemsPerRow = parseInt(document.getElementById('sectionItemsPerRow').value) || 4;
        const bgColor = document.getElementById('sectionBgColor').value;
        const textColor = document.getElementById('sectionTextColor').value;
        const padding = document.getElementById('sectionPadding').value.trim();
        const customCss = document.getElementById('sectionCustomCss').value.trim();
        const customHtml = document.getElementById('sectionCustomHtml').value.trim();
        const dataSource = document.getElementById('sectionDataSource').value.trim();
        const sortOrder = parseInt(document.getElementById('sectionSortOrder').value) || 0;

        try {
            const payload = {
                tenant_id: APP_CONFIG.TENANT_ID,
                theme_id: APP_STATE.selectedThemeId,
                section_type: sectionType,
                title: title,
                subtitle: subtitle,
                layout_type: layoutType,
                items_per_row: itemsPerRow,
                background_color: bgColor,
                text_color: textColor,
                padding: padding,
                custom_css: customCss,
                custom_html: customHtml,
                data_source: dataSource,
                sort_order: sortOrder
            };

            if (sectionId) {
                payload.id = parseInt(sectionId);
                await apiCall(`/homepage_sections/${payload.id}`, { method: 'PUT', body: payload });
            } else {
                await apiCall('/homepage_sections', { method: 'POST', body: payload });
            }

            showAlert('success', sectionId ? 'Homepage section updated!' : 'Homepage section created!');
            closeSectionModal();
            loadHomepageSections();

        } catch (error) {
            showAlert('error', 'Failed to save: ' + error.message);
        }
    }

    async function deleteSection(sectionId, sectionName) {
        if (!confirm(`Delete homepage section "${sectionName}"?`)) return;

        try {
            await apiCall(`/homepage_sections/${sectionId}`, {
                method: 'DELETE',
                body: { tenant_id: APP_CONFIG.TENANT_ID }
            });

            showAlert('success', 'Homepage section deleted!');
            loadHomepageSections();

        } catch (error) {
            showAlert('error', 'Failed to delete: ' + error.message);
        }
    }

    // ════════════════════════════════════════════════════════════
    // REFRESH ALL
    // ════════════════════════════════════════════════════════════

    function refreshAll() {
        loadThemes();
        showAlert('info', 'Refreshing...', 2000);
    }

    // ════════════════════════════════════════════════════════════
    // INITIALIZE
    // ════════════════════════════════════════════════════════════

    function init() {
        initTabs();
        loadThemes();

        // Close modals on background click
        document.addEventListener('click', e => {
            if (e.target.classList.contains('modal')) {
                e.target.classList.remove('active');
            }
        });

        console.log('%c[Themes] Ready!', 'color:#10b981;font-weight:bold');
    }

    // ════════════════════════════════════════════════════════════
    // EXPORT PUBLIC API
    // ════════════════════════════════════════════════════════════

    window.ThemesApp = {
        // Themes
        loadThemes,
        openThemeModal,
        closeThemeModal,
        saveTheme,
        editTheme: openThemeModal,
        deleteTheme,

        // Design
        selectDesignTheme,
        saveDesignSettings,

        // Colors
        selectColorsTheme,
        saveColorSettings,

        // Fonts
        selectFontsTheme,
        saveFontSettings,

        // Buttons
        loadButtons,
        openButtonModal,
        closeButtonModal,
        saveButton,
        editButton: openButtonModal,
        deleteButton,

        // Cards
        loadCards,
        openCardModal,
        closeCardModal,
        saveCard,
        editCard: openCardModal,
        deleteCard,

        // Homepage
        loadHomepageSections,
        openSectionModal,
        closeSectionModal,
        saveSection,
        editSection: openSectionModal,
        deleteSection,

        // General
        refreshAll
    };

    // Auto-init
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();