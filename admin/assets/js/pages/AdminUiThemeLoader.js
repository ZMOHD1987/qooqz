// ✅ ملف JavaScript المبسط لإدارة النسق
(function() {
    'use strict';

    // ✅ عناصر DOM الأساسية
    const elements = {
        themeManager: document.getElementById('themeManager'),
        themeModal: document.getElementById('themeModal'),
        themeForm: document.getElementById('themeForm'),
        themeGrid: document.querySelector('.theme-grid'),
        btnCreate: document.querySelector('[data-action="create-theme"]')
    };

    // ✅ API Endpoints
    const API = {
        themes: '/api/admin_ui/themes',
        design: '/api/admin_ui/design_settings',
        colors: '/api/admin_ui/color_settings',
        fonts: '/api/admin_ui/font_settings',
        buttons: '/api/admin_ui/button_styles',
        cards: '/api/admin_ui/card_styles',
        css: '/api/admin_ui/css',
        upload: '/api/admin_ui/upload_image'
    };

    // ✅ CSRF Token
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || 
                     window.CSRF_TOKEN || 
                     document.querySelector('input[name="csrf_token"]')?.value;

    // ✅ تهيئة الصفحة
    function init() {
        if (!elements.themeManager) return;
        
        loadThemes();
        bindEvents();
        setupColorPickers();
    }

    // ✅ تحميل النسق
    async function loadThemes() {
        try {
            showLoading();
            
            const response = await fetch(API.themes);
            if (!response.ok) throw new Error('فشل في تحميل النسق');
            
            const themes = await response.json();
            renderThemes(themes);
            
        } catch (error) {
            showError('تعذر تحميل النسق: ' + error.message);
        }
    }

    // ✅ عرض النسق
    function renderThemes(themes) {
        if (!elements.themeGrid) return;
        
        if (!themes || themes.length === 0) {
            elements.themeGrid.innerHTML = `
                <div class="empty-state">
                    <div class="empty-icon">🎨</div>
                    <h3>لا توجد نسق</h3>
                    <p>ابدأ بإنشاء أول نسق لك</p>
                    <button class="btn btn-primary" onclick="openThemeForm()">
                        <i class="fas fa-plus"></i> إنشاء نسق
                    </button>
                </div>
            `;
            return;
        }
        
        let html = '';
        themes.forEach(theme => {
            const isActive = theme.is_active == 1;
            const isDefault = theme.is_default == 1;
            
            html += `
            <div class="theme-card ${isActive ? 'active' : ''}" data-theme-id="${theme.id}">
                <div class="theme-preview" style="background: ${theme.primary_color || '#3b82f6'}"></div>
                <div class="theme-info">
                    <h4>${escapeHtml(theme.name)}</h4>
                    <p>${escapeHtml(theme.description || '')}</p>
                    <div class="theme-meta">
                        <span class="badge ${isActive ? 'badge-success' : 'badge-secondary'}">
                            ${isActive ? 'نشط' : 'غير نشط'}
                        </span>
                        ${isDefault ? '<span class="badge badge-info">افتراضي</span>' : ''}
                        <span class="version">v${escapeHtml(theme.version || '1.0.0')}</span>
                    </div>
                    <div class="theme-actions">
                        <button class="btn btn-sm btn-outline" onclick="editTheme(${theme.id})">
                            <i class="fas fa-edit"></i>
                        </button>
                        ${!isActive ? `
                        <button class="btn btn-sm btn-success" onclick="activateTheme(${theme.id})">
                            <i class="fas fa-check"></i>
                        </button>` : ''}
                        ${!isDefault ? `
                        <button class="btn btn-sm btn-danger" onclick="deleteTheme(${theme.id})">
                            <i class="fas fa-trash"></i>
                        </button>` : ''}
                    </div>
                </div>
            </div>
            `;
        });
        
        elements.themeGrid.innerHTML = html;
    }

    // ✅ فتح نموذج إنشاء نسق
    function openThemeForm(themeId = null) {
        if (themeId) {
            // تحميل بيانات النسق للتعديل
            loadTheme(themeId);
        } else {
            // إنشاء جديد
            document.getElementById('modalTitle').textContent = 'إنشاء نسق جديد';
            document.getElementById('themeId').value = '';
            document.getElementById('themeForm').reset();
        }
        
        const modal = new bootstrap.Modal(elements.themeModal);
        modal.show();
    }

    // ✅ تحميل بيانات نسق للتعديل
    async function loadTheme(themeId) {
        try {
            const response = await fetch(`${API.themes}/${themeId}`);
            if (!response.ok) throw new Error('فشل في تحميل البيانات');
            
            const theme = await response.json();
            
            document.getElementById('modalTitle').textContent = 'تعديل النسق';
            document.getElementById('themeId').value = theme.id;
            document.getElementById('themeName').value = theme.name || '';
            document.getElementById('themeSlug').value = theme.slug || '';
            document.getElementById('themeDescription').value = theme.description || '';
            document.getElementById('themeVersion').value = theme.version || '1.0.0';
            document.getElementById('themeAuthor').value = theme.author || '';
            document.getElementById('themeIsActive').checked = theme.is_active == 1;
            document.getElementById('themeIsDefault').checked = theme.is_default == 1;
            
        } catch (error) {
            alert('خطأ في تحميل البيانات: ' + error.message);
        }
    }

    // ✅ حفظ النسق
    async function saveTheme() {
        try {
            const form = elements.themeForm;
            const formData = new FormData(form);
            const data = Object.fromEntries(formData);
            
            // تحويل القيم المنطقية
            data.is_active = data.is_active ? 1 : 0;
            data.is_default = data.is_default ? 1 : 0;
            
            const method = data.id ? 'PUT' : 'POST';
            const url = data.id ? `${API.themes}/${data.id}` : API.themes;
            
            const response = await fetch(url, {
                method: method,
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfToken
                },
                body: JSON.stringify(data)
            });
            
            if (!response.ok) {
                const error = await response.json();
                throw new Error(error.message || 'فشل في الحفظ');
            }
            
            const result = await response.json();
            
            alert('تم حفظ النسق بنجاح');
            bootstrap.Modal.getInstance(elements.themeModal).hide();
            loadThemes();
            
        } catch (error) {
            alert('خطأ في الحفظ: ' + error.message);
        }
    }

    // ✅ تفعيل النسق
    async function activateTheme(themeId) {
        if (!confirm('هل تريد تفعيل هذا النسق؟ سيتم إلغاء تفعيل النسق الحالي.')) {
            return;
        }
        
        try {
            const response = await fetch(`${API.themes}/${themeId}`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfToken
                },
                body: JSON.stringify({ is_active: 1 })
            });
            
            if (!response.ok) throw new Error('فشل في التفعيل');
            
            alert('تم تفعيل النسق بنجاح');
            loadThemes();
            
            // إعادة تحميل الصفحة لتطبيق النسق الجديد
            setTimeout(() => location.reload(), 1000);
            
        } catch (error) {
            alert('خطأ في التفعيل: ' + error.message);
        }
    }

    // ✅ حذف النسق
    async function deleteTheme(themeId) {
        if (!confirm('هل أنت متأكد من حذف هذا النسق؟ لا يمكن التراجع عن هذا الإجراء.')) {
            return;
        }
        
        try {
            const response = await fetch(`${API.themes}/${themeId}`, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-Token': csrfToken
                }
            });
            
            if (!response.ok) throw new Error('فشل في الحذف');
            
            alert('تم حذف النسق بنجاح');
            loadThemes();
            
        } catch (error) {
            alert('خطأ في الحذف: ' + error.message);
        }
    }

    // ✅ إنشاء Color Picker مبسط
    function setupColorPickers() {
        document.querySelectorAll('.color-picker').forEach(input => {
            input.addEventListener('input', function() {
                const preview = this.nextElementSibling;
                if (preview && preview.classList.contains('color-preview')) {
                    preview.style.backgroundColor = this.value;
                }
            });
        });
    }

    // ✅ إعداد Color Palette
    function setupColorPalette() {
        const defaultColors = [
            '#3b82f6', '#10b981', '#ef4444', '#f59e0b', '#8b5cf6',
            '#06b6d4', '#84cc16', '#f97316', '#ec4899', '#64748b'
        ];
        
        const container = document.getElementById('colorPalette');
        if (!container) return;
        
        let html = '<div class="color-palette-grid">';
        defaultColors.forEach(color => {
            html += `
            <div class="color-item" data-color="${color}" onclick="selectColor('${color}')">
                <div class="color-preview" style="background: ${color}"></div>
            </div>
            `;
        });
        html += '</div>';
        
        container.innerHTML = html;
    }

    // ✅ اختيار لون من البليتة
    function selectColor(color) {
        const activeInput = document.querySelector('.color-picker:focus');
        if (activeInput) {
            activeInput.value = color;
            activeInput.dispatchEvent(new Event('input'));
        }
    }

    // ✅ عرض حالة التحميل
    function showLoading() {
        if (elements.themeGrid) {
            elements.themeGrid.innerHTML = `
                <div class="loading-state">
                    <div class="spinner"></div>
                    <p>جاري التحميل...</p>
                </div>
            `;
        }
    }

    // ✅ عرض حالة الخطأ
    function showError(message) {
        if (elements.themeGrid) {
            elements.themeGrid.innerHTML = `
                <div class="error-state">
                    <div class="error-icon">⚠️</div>
                    <h3>خطأ في التحميل</h3>
                    <p>${escapeHtml(message)}</p>
                    <button class="btn btn-secondary" onclick="loadThemes()">
                        <i class="fas fa-redo"></i> إعادة المحاولة
                    </button>
                </div>
            `;
        }
    }

    // ✅ تهريب HTML
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // ✅ ربط الأحداث
    function bindEvents() {
        // زر إنشاء جديد
        if (elements.btnCreate) {
            elements.btnCreate.addEventListener('click', () => openThemeForm());
        }
        
        // حفظ النموذج
        const btnSave = document.getElementById('btnSaveTheme');
        if (btnSave) {
            btnSave.addEventListener('click', saveTheme);
        }
        
        // Auto-generate slug from name
        const nameInput = document.getElementById('themeName');
        if (nameInput) {
            nameInput.addEventListener('input', function() {
                const slugInput = document.getElementById('themeSlug');
                if (slugInput && !slugInput.value) {
                    const slug = this.value
                        .toLowerCase()
                        .replace(/[^a-z0-9]+/g, '-')
                        .replace(/^-|-$/g, '');
                    slugInput.value = slug;
                }
            });
        }
    }

    // ✅ جعل الدوال متاحة عالميًا
    window.openThemeForm = openThemeForm;
    window.editTheme = openThemeForm;
    window.activateTheme = activateTheme;
    window.deleteTheme = deleteTheme;
    window.selectColor = selectColor;

    // ✅ البدء عند تحميل الصفحة
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();