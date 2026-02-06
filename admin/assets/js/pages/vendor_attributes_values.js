(function() {
    'use strict';
    
    if (!window.VAV_CONFIG) {
        console.error('VAV_CONFIG is not defined');
        return;
    }

    // ===== استخراج البيانات من الجداول =====
    const CONFIG = window.VAV_CONFIG;
    const API_URL = CONFIG.apiUrl;
    const CSRF_TOKEN = CONFIG.csrfToken || "";
    const USER_INFO = window.USER_INFO || {};
    const I18N = window.I18N_FLAT || {};
    const THEME = window.THEME || {};
    const IS_STANDALONE = CONFIG.isStandalone || false;
    const DIRECTION = window.DIRECTION || 'ltr';
    
    // ===== متغيرات الترقيم =====
    let currentPage = 1;
    let totalPages = 1;
    let totalItems = 0;
    let itemsPerPage = getDesignValue('items_per_page') || 10;
    let allData = []; // لتخزين البيانات الكاملة في حالة الترقيم على العميل
    
    // ===== دوال الجلب من الجداول =====
    function getThemeValue(type, key, defaultValue = null) {
        if (!THEME[type]) return defaultValue;
        
        if (THEME[type + '_map'] && THEME[type + '_map'][key]) {
            return THEME[type + '_map'][key];
        }
        
        if (Array.isArray(THEME[type])) {
            const item = THEME[type].find(item => 
                item.setting_key === key || 
                item.category === key || 
                item.slug === key ||
                item.button_type === key ||
                item.card_type === key
            );
            return item || defaultValue;
        }
        
        return defaultValue;
    }

    function getColor(key) {
        if (THEME.colors_map && THEME.colors_map[key]) {
            return THEME.colors_map[key];
        }
        
        const color = getThemeValue('colors', key);
        if (color && color.color_value) return color.color_value;
        return null;
    }

    function getFont(key = 'primary') {
        if (THEME.fonts_map && THEME.fonts_map[key]) {
            return THEME.fonts_map[key];
        }
        
        const font = getThemeValue('fonts', key);
        if (font && font.font_family) return font.font_family;
        return null;
    }

    function getButtonStyle(type) {
        if (THEME.buttons_map && THEME.buttons_map[type]) {
            return THEME.buttons_map[type];
        }
        
        const button = getThemeValue('buttons', type);
        if (button) return button;
        return null;
    }

    function getCardStyle(type) {
        if (THEME.cards_map && THEME.cards_map[type]) {
            return THEME.cards_map[type];
        }
        
        const card = getThemeValue('cards', type);
        if (card) return card;
        return null;
    }

    function getDesignValue(key) {
        if (THEME.designs && THEME.designs[key]) {
            return THEME.designs[key];
        }
        return null;
    }

    // ===== إنشاء خرائط للموضوع =====
    function createThemeMaps() {
        if (!THEME.fonts_map && Array.isArray(THEME.fonts)) {
            THEME.fonts_map = {};
            THEME.fonts.forEach(font => {
                if (font.setting_key) {
                    THEME.fonts_map[font.setting_key] = font;
                }
            });
        }
    }

    // ===== تطبيق الأنماط من الجداول =====
    function applyDynamicStyles() {
        // حذف الأنماط السابقة إذا وجدت
        const existingStyle = document.getElementById('vav-dynamic-styles');
        if (existingStyle) existingStyle.remove();
        
        createThemeMaps();
        
        const style = document.createElement('style');
        style.id = 'vav-dynamic-styles';
        
        let css = ':root {';
        
        // 1. الألوان من الجداول
        if (THEME.colors_map) {
            Object.entries(THEME.colors_map).forEach(([key, value]) => {
                if (value) {
                    const cleanKey = key.replace(/_/g, '-');
                    css += `--color-${cleanKey}: ${value};`;
                }
            });
        } else if (Array.isArray(THEME.colors)) {
            THEME.colors.forEach(color => {
                if (color.setting_key && color.color_value) {
                    const cleanKey = color.setting_key.replace(/_/g, '-');
                    css += `--color-${cleanKey}: ${color.color_value};`;
                }
            });
        }
        
        // 2. الخطوط من الجداول
        if (THEME.fonts_map) {
            Object.entries(THEME.fonts_map).forEach(([key, font]) => {
                if (font.font_family) {
                    const cleanKey = key.replace(/_/g, '-');
                    css += `--font-${cleanKey}: ${font.font_family};`;
                }
            });
        } else if (Array.isArray(THEME.fonts)) {
            THEME.fonts.forEach(font => {
                if (font.setting_key && font.font_family) {
                    const cleanKey = font.setting_key.replace(/_/g, '-');
                    css += `--font-${cleanKey}: ${font.font_family};`;
                }
            });
        }
        
        // 3. قيم التصميم من الجداول
        if (THEME.designs) {
            Object.entries(THEME.designs).forEach(([key, value]) => {
                if (value) {
                    const cleanKey = key.replace(/_/g, '-');
                    css += `--design-${cleanKey}: ${value};`;
                }
            });
        }
        
        css += '}';
        
        // 4. أنماط الأزرار من الجداول
        if (THEME.buttons_map) {
            Object.entries(THEME.buttons_map).forEach(([type, button]) => {
                if (button && button.button_type) {
                    const btnClass = `.btn-${type}`;
                    css += `${btnClass} {`;
                    
                    if (button.background_color) css += `background-color: ${button.background_color};`;
                    if (button.text_color) css += `color: ${button.text_color};`;
                    if (button.border_color) css += `border-color: ${button.border_color};`;
                    if (button.border_width) css += `border-width: ${button.border_width}px;`;
                    if (button.border_radius) css += `border-radius: ${button.border_radius}px;`;
                    if (button.padding) css += `padding: ${button.padding};`;
                    if (button.font_size) css += `font-size: ${button.font_size};`;
                    if (button.font_weight) css += `font-weight: ${button.font_weight};`;
                    
                    css += `}`;
                    
                    // أنماط hover
                    css += `${btnClass}:hover {`;
                    if (button.hover_background_color) css += `background-color: ${button.hover_background_color};`;
                    if (button.hover_text_color) css += `color: ${button.hover_text_color};`;
                    if (button.hover_border_color) css += `border-color: ${button.hover_border_color};`;
                    css += `}`;
                }
            });
        }
        
        // 5. أنماط البطاقات من الجداول
        if (THEME.cards_map) {
            Object.entries(THEME.cards_map).forEach(([type, card]) => {
                if (card && card.card_type) {
                    const cardClass = `.card-${type}`;
                    css += `${cardClass} {`;
                    
                    if (card.background_color) css += `background-color: ${card.background_color};`;
                    if (card.border_color) css += `border-color: ${card.border_color};`;
                    if (card.border_width) css += `border-width: ${card.border_width}px;`;
                    if (card.border_radius) css += `border-radius: ${card.border_radius}px;`;
                    if (card.shadow_style && card.shadow_style !== 'none') css += `box-shadow: ${card.shadow_style};`;
                    if (card.padding) css += `padding: ${card.padding};`;
                    if (card.text_align) css += `text-align: ${card.text_align};`;
                    
                    // تأثيرات hover
                    if (card.hover_effect && card.hover_effect !== 'none') {
                        css += `transition: all 0.3s ease;`;
                        css += `${cardClass}:hover {`;
                        if (card.hover_effect === 'shadow') css += `box-shadow: 0 8px 25px rgba(0,0,0,0.15);`;
                        if (card.hover_effect === 'scale') css += `transform: scale(1.02);`;
                        if (card.hover_effect === 'lift') css += `transform: translateY(-5px);`;
                        css += `}`;
                    }
                    
                    css += `}`;
                }
            });
        }
        
        // 6. أنماط الترقيم
        css += `
            .pagination {
                display: flex;
                justify-content: center;
                align-items: center;
                margin-top: 20px;
                padding: 15px;
                background: var(--color-white, #ffffff);
                border-radius: var(--design-border-radius, 8px);
                box-shadow: var(--design-shadow, 0 2px 4px rgba(0,0,0,0.1));
            }
            
            .pagination-info {
                margin: 0 15px;
                color: var(--color-text-secondary, #6c757d);
                font-size: 14px;
            }
            
            .pagination-buttons {
                display: flex;
                gap: 5px;
            }
            
            .page-btn {
                min-width: 36px;
                height: 36px;
                display: flex;
                align-items: center;
                justify-content: center;
                border: 1px solid var(--color-border, #dee2e6);
                border-radius: 4px;
                background: var(--color-white, #ffffff);
                color: var(--color-text-primary, #212529);
                cursor: pointer;
                font-size: 14px;
                transition: all 0.2s ease;
            }
            
            .page-btn:hover {
                background: var(--color-hover-background, #f8f9fa);
                border-color: var(--color-primary, #007bff);
            }
            
            .page-btn.active {
                background: var(--color-primary, #007bff);
                color: white;
                border-color: var(--color-primary, #007bff);
            }
            
            .page-btn.disabled {
                opacity: 0.5;
                cursor: not-allowed;
                background: var(--color-background-secondary, #f1f3f5);
            }
            
            .page-btn.disabled:hover {
                background: var(--color-background-secondary, #f1f3f5);
                border-color: var(--color-border, #dee2e6);
            }
            
            .pagination-input {
                width: 60px;
                padding: 8px;
                border: 1px solid var(--color-border, #dee2e6);
                border-radius: 4px;
                text-align: center;
                margin: 0 10px;
                font-size: 14px;
            }
            
            .pagination-input:focus {
                outline: none;
                border-color: var(--color-primary, #007bff);
                box-shadow: 0 0 0 3px var(--color-primary-light, rgba(0, 123, 255, 0.25));
            }
            
            .items-per-page {
                margin-left: 20px;
                display: flex;
                align-items: center;
                gap: 8px;
            }
            
            .items-per-page select {
                padding: 6px 10px;
                border: 1px solid var(--color-border, #dee2e6);
                border-radius: 4px;
                background: var(--color-white, #ffffff);
                color: var(--color-text-primary, #212529);
                font-size: 14px;
            }
            
            .pagination-ellipsis {
                min-width: 36px;
                height: 36px;
                display: flex;
                align-items: center;
                justify-content: center;
                color: var(--color-text-secondary, #6c757d);
            }
        `;
        
        // 7. أنماط عامة
        css += `
            body {
                font-family: var(--font-primary, var(--font-body, 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif));
                direction: ${DIRECTION};
                ${DIRECTION === 'rtl' ? 'text-align: right;' : 'text-align: left;'}
            }
            
            .admin-page {
                padding: var(--design-page-padding, 20px);
                background: var(--color-background, #f8f9fa);
            }
            
            .table-section {
                position: relative;
            }
            
            .table-container {
                background: var(--color-white, #ffffff);
                border-radius: var(--design-border-radius, 8px);
                box-shadow: var(--design-shadow, 0 2px 4px rgba(0,0,0,0.1));
                overflow: hidden;
            }
            
            .admin-table {
                width: 100%;
                border-collapse: collapse;
            }
            
            .admin-table th {
                background: var(--color-background-secondary, #f1f3f5);
                color: var(--color-text-primary, #212529);
                font-weight: 600;
                padding: var(--design-table-cell-padding, 12px 16px);
                border-bottom: 2px solid var(--color-border, #dee2e6);
            }
            
            .admin-table td {
                padding: var(--design-table-cell-padding, 12px 16px);
                border-bottom: 1px solid var(--color-border, #dee2e6);
            }
            
            .admin-table tr:hover {
                background: var(--color-hover-background, #f8f9fa);
            }
            
            .form-section {
                background: var(--color-white, #ffffff);
                border-radius: var(--design-border-radius, 8px);
                padding: var(--design-form-padding, 24px);
                margin-top: 20px;
                box-shadow: var(--design-shadow, 0 2px 4px rgba(0,0,0,0.1));
            }
            
            .form-group {
                margin-bottom: var(--design-form-group-spacing, 16px);
            }
            
            .form-label {
                display: block;
                margin-bottom: var(--design-label-spacing, 8px);
                color: var(--color-text-primary, #212529);
                font-weight: 500;
            }
            
            .form-input, .form-select {
                width: 100%;
                padding: var(--design-input-padding, 10px 12px);
                border: 1px solid var(--color-border, #dee2e6);
                border-radius: var(--design-input-border-radius, 4px);
                font-size: var(--design-input-font-size, 14px);
                transition: border-color 0.15s ease-in-out;
            }
            
            .form-input:focus, .form-select:focus {
                outline: none;
                border-color: var(--color-primary, #007bff);
                box-shadow: 0 0 0 3px var(--color-primary-light, rgba(0, 123, 255, 0.25));
            }
            
            .notification-area {
                position: fixed;
                top: 20px;
                ${DIRECTION === 'rtl' ? 'left: 20px;' : 'right: 20px;'}
                z-index: 1000;
                max-width: 400px;
            }
        `;
        
        style.textContent = css;
        document.head.appendChild(style);
    }

    // ===== دوال المساعدة =====
    function translate(key) {
        if (I18N[key]) return I18N[key];
        const shortKey = key.split('.').pop();
        return I18N[shortKey] || key;
    }

    function showNotification(message, type = 'info') {
        const notificationEl = document.getElementById('vavNotification');
        if (!notificationEl) return;

        const colors = {
            success: getColor('success') || '#28a745',
            error: getColor('error') || '#dc3545',
            info: getColor('info') || '#17a2b8',
            warning: getColor('warning') || '#ffc107'
        };

        const notification = document.createElement('div');
        notification.className = `notification-${type}`;
        notification.style.display = 'flex';
        notification.style.justifyContent = 'space-between';
        notification.style.alignItems = 'center';
        notification.style.marginBottom = '10px';
        notification.style.backgroundColor = colors[type];
        notification.style.color = 'white';
        notification.style.padding = '12px 20px';
        notification.style.borderRadius = getDesignValue('border_radius') || '6px';
        notification.style.boxShadow = getDesignValue('shadow') || '0 4px 6px rgba(0,0,0,0.1)';
        
        notification.innerHTML = `
            <span>${message}</span>
            <button class="close-notification" style="
                background: transparent;
                border: none;
                color: white;
                font-size: 20px;
                cursor: pointer;
                ${DIRECTION === 'rtl' ? 'margin-right: 10px;' : 'margin-left: 10px;'}
            ">&times;</button>
        `;

        notification.querySelector('.close-notification').onclick = () => {
            notification.remove();
        };

        notificationEl.appendChild(notification);

        setTimeout(() => {
            if (notification.parentElement) {
                notification.remove();
            }
        }, 5000);
    }

    // ===== دوال الترقيم =====
    function calculatePagination() {
        totalPages = Math.ceil(totalItems / itemsPerPage);
        if (currentPage > totalPages) {
            currentPage = totalPages || 1;
        }
    }

    function getPaginatedData(data) {
        if (!data || data.length === 0) return [];
        
        const startIndex = (currentPage - 1) * itemsPerPage;
        const endIndex = startIndex + itemsPerPage;
        return data.slice(startIndex, endIndex);
    }

    function createPagination() {
        const paginationContainer = document.getElementById('vavPagination');
        if (!paginationContainer) return;
        
        calculatePagination();
        
        if (totalItems === 0) {
            paginationContainer.innerHTML = '';
            return;
        }
        
        // إعداد خيارات العناصر لكل صفحة
        const perPageOptions = [5, 10, 25, 50, 100];
        const perPageHtml = perPageOptions.map(num => 
            `<option value="${num}" ${num === itemsPerPage ? 'selected' : ''}>${num}</option>`
        ).join('');
        
        let buttonsHtml = '';
        
        // زر الصفحة الأولى
        buttonsHtml += `
            <button class="page-btn first-page ${currentPage === 1 ? 'disabled' : ''}" 
                    ${currentPage === 1 ? 'disabled' : ''}>
                &laquo;
            </button>
            <button class="page-btn prev-page ${currentPage === 1 ? 'disabled' : ''}" 
                    ${currentPage === 1 ? 'disabled' : ''}>
                &lsaquo;
            </button>
        `;
        
        // إنشاء أزرار الصفحات
        const maxVisiblePages = 5;
        let startPage = Math.max(1, currentPage - Math.floor(maxVisiblePages / 2));
        let endPage = Math.min(totalPages, startPage + maxVisiblePages - 1);
        
        if (endPage - startPage + 1 < maxVisiblePages) {
            startPage = Math.max(1, endPage - maxVisiblePages + 1);
        }
        
        // النقاط الثلاثة في البداية
        if (startPage > 1) {
            buttonsHtml += `<span class="pagination-ellipsis">...</span>`;
        }
        
        // أزرار الصفحات
        for (let i = startPage; i <= endPage; i++) {
            buttonsHtml += `
                <button class="page-btn ${i === currentPage ? 'active' : ''}" data-page="${i}">
                    ${i}
                </button>
            `;
        }
        
        // النقاط الثلاثة في النهاية
        if (endPage < totalPages) {
            buttonsHtml += `<span class="pagination-ellipsis">...</span>`;
        }
        
        // زر الصفحة التالية والأخيرة
        buttonsHtml += `
            <button class="page-btn next-page ${currentPage === totalPages ? 'disabled' : ''}" 
                    ${currentPage === totalPages ? 'disabled' : ''}>
                &rsaquo;
            </button>
            <button class="page-btn last-page ${currentPage === totalPages ? 'disabled' : ''}" 
                    ${currentPage === totalPages ? 'disabled' : ''}>
                &raquo;
            </button>
        `;
        
        // بناء HTML كامل
        paginationContainer.innerHTML = `
            <div class="pagination">
                <div class="pagination-info">
                    ${translate('vendor_attributes.pagination.showing')} 
                    <strong>${Math.min((currentPage - 1) * itemsPerPage + 1, totalItems)}-${Math.min(currentPage * itemsPerPage, totalItems)}</strong> 
                    ${translate('vendor_attributes.pagination.of')} 
                    <strong>${totalItems}</strong> 
                    ${translate('vendor_attributes.pagination.items')}
                </div>
                
                <div class="pagination-buttons">
                    ${buttonsHtml}
                </div>
                
                <div class="pagination-jump">
                    <input type="number" class="pagination-input" 
                           min="1" max="${totalPages}" 
                           value="${currentPage}"
                           placeholder="${translate('vendor_attributes.pagination.page')}">
                    <button class="page-btn go-page">${translate('vendor_attributes.pagination.go')}</button>
                </div>
                
                <div class="items-per-page">
                    <span>${translate('vendor_attributes.pagination.per_page')}:</span>
                    <select class="per-page-select">
                        ${perPageHtml}
                    </select>
                </div>
            </div>
        `;
        
        // إضافة مستمعي الأحداث
        setupPaginationEvents();
        
        // تطبيق الأنماط على أزرار الترقيم
        setTimeout(applyStylesToElements, 0);
    }

    function setupPaginationEvents() {
        const paginationContainer = document.getElementById('vavPagination');
        if (!paginationContainer) return;
        
        // الانتقال للصفحة الأولى
        const firstBtn = paginationContainer.querySelector('.first-page');
        if (firstBtn) {
            firstBtn.addEventListener('click', () => {
                if (currentPage > 1) {
                    currentPage = 1;
                    renderCurrentPage();
                }
            });
        }
        
        // الصفحة السابقة
        const prevBtn = paginationContainer.querySelector('.prev-page');
        if (prevBtn) {
            prevBtn.addEventListener('click', () => {
                if (currentPage > 1) {
                    currentPage--;
                    renderCurrentPage();
                }
            });
        }
        
        // الصفحات المحددة
        const pageBtns = paginationContainer.querySelectorAll('.page-btn[data-page]');
        pageBtns.forEach(btn => {
            btn.addEventListener('click', (e) => {
                const page = parseInt(e.target.dataset.page);
                if (page !== currentPage) {
                    currentPage = page;
                    renderCurrentPage();
                }
            });
        });
        
        // الصفحة التالية
        const nextBtn = paginationContainer.querySelector('.next-page');
        if (nextBtn) {
            nextBtn.addEventListener('click', () => {
                if (currentPage < totalPages) {
                    currentPage++;
                    renderCurrentPage();
                }
            });
        }
        
        // الصفحة الأخيرة
        const lastBtn = paginationContainer.querySelector('.last-page');
        if (lastBtn) {
            lastBtn.addEventListener('click', () => {
                if (currentPage < totalPages) {
                    currentPage = totalPages;
                    renderCurrentPage();
                }
            });
        }
        
        // الذهاب إلى صفحة محددة
        const jumpInput = paginationContainer.querySelector('.pagination-input');
        const goBtn = paginationContainer.querySelector('.go-page');
        
        if (jumpInput && goBtn) {
            goBtn.addEventListener('click', () => {
                const page = parseInt(jumpInput.value);
                if (page >= 1 && page <= totalPages && page !== currentPage) {
                    currentPage = page;
                    renderCurrentPage();
                }
            });
            
            jumpInput.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') {
                    const page = parseInt(jumpInput.value);
                    if (page >= 1 && page <= totalPages && page !== currentPage) {
                        currentPage = page;
                        renderCurrentPage();
                    }
                }
            });
        }
        
        // تغيير عدد العناصر لكل صفحة
        const perPageSelect = paginationContainer.querySelector('.per-page-select');
        if (perPageSelect) {
            perPageSelect.addEventListener('change', (e) => {
                itemsPerPage = parseInt(e.target.value);
                currentPage = 1; // العودة للصفحة الأولى
                renderCurrentPage();
            });
        }
    }

    function renderCurrentPage() {
        if (!allData || allData.length === 0) {
            updateTableWithPagination([]);
            return;
        }
        
        const paginatedData = getPaginatedData(allData);
        updateTableWithPagination(paginatedData);
        createPagination();
    }

    function updateTableWithPagination(data) {
        if (!elements.vavTbody) return;
        
        if (!data || data.length === 0) {
            elements.vavTbody.innerHTML = `
                <tr>
                    <td colspan="5" class="vav-loading">
                        ${translate('vendor_attributes.messages.no_data')}
                    </td>
                </tr>
            `;
            return;
        }
        
        elements.vavTbody.innerHTML = data.map(item => {
            const primaryColor = getColor('primary') || '#3b82f6';
            const dangerColor = getColor('danger') || '#ef4444';
            const btnMargin = DIRECTION === 'rtl' ? 'margin-right' : 'margin-left';
            
            return `
            <tr>
                <td>${item.id}</td>
                <td><strong>${item.vendor_name || 'N/A'}</strong></td>
                <td style="color:${primaryColor};">${item.attribute_slug}</td>
                <td>${item.value}</td>
                <td style="text-align:${DIRECTION === 'rtl' ? 'left' : 'right'};">
                    <button onclick="window.editVendorAttribute(${item.id}, ${item.vendor_id}, ${item.attribute_id}, '${item.value.replace(/'/g, "\\'")}')" 
                            class="btn btn-outline">
                        ${translate('vendor_attributes.buttons.edit')}
                    </button>
                    <button onclick="window.deleteVendorAttribute(${item.id})" 
                            class="btn btn-danger" style="${btnMargin}: 8px;">
                        ${translate('vendor_attributes.buttons.delete')}
                    </button>
                </td>
            </tr>
            `;
        }).join('');
        
        // تطبيق الأنماط على الصفوف الجديدة
        setTimeout(applyStylesToElements, 0);
    }

    // ===== تطبيق الأنماط على العناصر =====
    function applyStylesToElements() {
        // تطبيق أنماط الصفحة الرئيسية
        const adminPage = document.getElementById('vendorAttributes');
        if (adminPage) {
            const cardStyle = getCardStyle('default') || getCardStyle('primary');
            if (cardStyle) {
                adminPage.className = 'admin-page card-primary';
            }
        }
        
        // تطبيق أنماط الأزرار
        const buttons = document.querySelectorAll('button');
        buttons.forEach(btn => {
            if (btn.id === 'vavNew') {
                btn.className = 'btn btn-primary';
            } else if (btn.id === 'vavCancel' || btn.id === 'vavResetFilters') {
                btn.className = 'btn btn-outline';
            } else if (btn.id === 'vavRefresh') {
                btn.className = 'btn btn-secondary';
            } else if (btn.classList.contains('btn-danger')) {
                btn.className = 'btn btn-danger';
            }
        });
        
        // تطبيق أنماط الجدول
        const tableContainer = document.querySelector('.table-section .table-container');
        if (tableContainer) {
            const cardStyle = getCardStyle('table') || getCardStyle('default');
            if (cardStyle) {
                tableContainer.className = 'table-container card-table';
            }
        }
        
        // تطبيق أنماط النموذج
        const formSection = document.getElementById('vavFormWrap');
        if (formSection) {
            const cardStyle = getCardStyle('form') || getCardStyle('default');
            if (cardStyle) {
                formSection.className = 'form-section card-form';
            }
        }
        
        // تطبيق أنماط الترقيم
        const pagination = document.querySelector('.pagination');
        if (pagination) {
            const cardStyle = getCardStyle('pagination') || getCardStyle('default');
            if (cardStyle) {
                pagination.className = 'pagination card-pagination';
            }
        }
    }

    // ===== عناصر DOM =====
    const elements = {};
    const elementIds = [
        'vendorAttributes', 'vavTbody', 'vavForm', 'vavFormWrap', 'vavFormTitle',
        'vavId', 'vavNew', 'vavCancel', 'vavRefresh', 'vavResetFilters',
        'vavSearch', 'vavNotification', 'vavValue', 'vavVendor', 'vavAttribute',
        'vavVendorFilter', 'vavAttributeFilter', 'vavPagination'
    ];

    elementIds.forEach(id => {
        const element = document.getElementById(id);
        if (element) elements[id] = element;
    });

    // ===== تهيئة Select2 =====
    function initSelect2() {
        if (typeof jQuery !== 'undefined' && jQuery().select2) {
            // فلاتر البحث
            $('#vavVendorFilter').select2({
                placeholder: translate('vendor_attributes.filters.all_vendors'),
                allowClear: true,
                width: '100%',
                dropdownParent: IS_STANDALONE ? null : $('#vendorAttributes')
            }).on('change', () => {
                currentPage = 1;
                loadTable();
            });
            
            $('#vavAttributeFilter').select2({
                placeholder: translate('vendor_attributes.filters.all_attributes'),
                allowClear: true,
                width: '100%',
                dropdownParent: IS_STANDALONE ? null : $('#vendorAttributes')
            }).on('change', () => {
                currentPage = 1;
                loadTable();
            });
            
            // قوائم النموذج
            $('#vavVendor').select2({
                dropdownParent: IS_STANDALONE ? null : $('#vavFormWrap'),
                width: '100%'
            });
            
            $('#vavAttribute').select2({
                dropdownParent: IS_STANDALONE ? null : $('#vavFormWrap'),
                width: '100%'
            });
        }
    }

    // ===== تحميل الموارد =====
    async function loadResources() {
        try {
            const [vendorsRes, attrsRes] = await Promise.all([
                fetch(CONFIG.vendorsUrl),
                fetch(CONFIG.attrsUrl)
            ]);

            const vendorsJson = await vendorsRes.json();
            const attrsJson = await attrsRes.json();

            if (vendorsJson.success && vendorsJson.data) {
                const vendorOptions = vendorsJson.data.map(v => 
                    `<option value="${v.id}">${v.store_name} (ID: ${v.id})</option>`
                ).join('');
                
                document.getElementById('vavVendorFilter').innerHTML = 
                    `<option value="">${translate('vendor_attributes.filters.all_vendors')}</option>` + vendorOptions;
                
                document.getElementById('vavVendor').innerHTML = 
                    `<option value="">${translate('vendor_attributes.form.select_vendor')}</option>` + vendorOptions;
            }

            if (attrsJson.success && attrsJson.data) {
                const attrOptions = attrsJson.data.map(a => 
                    `<option value="${a.id}">${a.display_name} (${a.slug})</option>`
                ).join('');
                
                document.getElementById('vavAttributeFilter').innerHTML = 
                    `<option value="">${translate('vendor_attributes.filters.all_attributes')}</option>` + attrOptions;
                
                document.getElementById('vavAttribute').innerHTML = 
                    `<option value="">${translate('vendor_attributes.form.select_attribute')}</option>` + attrOptions;
            }

            initSelect2();
        } catch (error) {
            console.error('Error loading resources:', error);
            showNotification(translate('vendor_attributes.messages.error_loading_resources'), 'error');
        }
    }

    // ===== تحميل البيانات =====
    async function loadTable() {
        if (!elements.vavTbody) return;

        elements.vavTbody.innerHTML = `
            <tr>
                <td colspan="5" class="loading-row">
                    <div class="loading-spinner"></div>
                    <div>${translate('vendor_attributes.messages.loading_data')}</div>
                </td>
            </tr>
        `;

        try {
            // بناء معلمات البحث
            const params = new URLSearchParams();
            
            let vendorId = '';
            let attributeId = '';
            
            if (typeof jQuery !== 'undefined' && jQuery().select2) {
                vendorId = $('#vavVendorFilter').val() || '';
                attributeId = $('#vavAttributeFilter').val() || '';
            } else {
                const vendorFilter = document.getElementById('vavVendorFilter');
                const attributeFilter = document.getElementById('vavAttributeFilter');
                vendorId = vendorFilter ? vendorFilter.value : '';
                attributeId = attributeFilter ? attributeFilter.value : '';
            }
            
            if (vendorId) params.append('vendor_id', vendorId);
            if (attributeId) params.append('attribute_id', attributeId);
            
            const searchText = elements.vavSearch ? elements.vavSearch.value : '';
            if (searchText) params.append('search', searchText);

            const response = await fetch(`${API_URL}?${params.toString()}`);
            const data = await response.json();

            if (!data.success || !data.data || data.data.length === 0) {
                allData = [];
                totalItems = 0;
                updateTableWithPagination([]);
                createPagination();
                return;
            }

            // تخزين البيانات الكاملة
            allData = data.data;
            totalItems = allData.length;
            
            // عرض الصفحة الأولى
            renderCurrentPage();

        } catch (error) {
            console.error('Error loading table:', error);
            elements.vavTbody.innerHTML = `
                <tr>
                    <td colspan="5" class="vav-error">
                        ${translate('vendor_attributes.messages.error_loading_table')}
                    </td>
                </tr>
            `;
            showNotification(translate('vendor_attributes.messages.error_loading_table'), 'error');
        }
    }

    // ===== إدارة النموذج =====
    function showForm() {
        if (elements.vavFormWrap) {
            elements.vavFormWrap.style.display = 'block';
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
    }

    function hideForm() {
        if (elements.vavFormWrap) {
            elements.vavFormWrap.style.display = 'none';
            resetForm();
        }
    }

    function resetForm() {
        if (elements.vavForm) {
            elements.vavForm.reset();
        }
        if (elements.vavId) {
            elements.vavId.value = '';
        }
        if (elements.vavFormTitle) {
            elements.vavFormTitle.textContent = translate('vendor_attributes.form.add_title');
        }
        if (typeof jQuery !== 'undefined' && jQuery().select2) {
            $('#vavVendor').val(null).trigger('change');
            $('#vavAttribute').val(null).trigger('change');
        }
    }

    // ===== معالجات الأحداث العامة =====
    window.editVendorAttribute = function(id, vendorId, attributeId, value) {
        if (elements.vavId) elements.vavId.value = id;
        
        if (typeof jQuery !== 'undefined' && jQuery().select2) {
            $('#vavVendor').val(vendorId).trigger('change');
            $('#vavAttribute').val(attributeId).trigger('change');
        } else {
            const vendorSelect = document.getElementById('vavVendor');
            const attributeSelect = document.getElementById('vavAttribute');
            if (vendorSelect) vendorSelect.value = vendorId;
            if (attributeSelect) attributeSelect.value = attributeId;
        }
        
        if (elements.vavValue) elements.vavValue.value = value;
        if (elements.vavFormTitle) {
            elements.vavFormTitle.textContent = translate('vendor_attributes.form.edit_title');
        }
        
        showForm();
    };

    window.deleteVendorAttribute = async function(id) {
        if (!confirm(translate('vendor_attributes.messages.delete_confirm'))) return;
        
        const formData = new FormData();
        formData.append('action', 'delete');
        formData.append('id', id);
        formData.append('csrf_token', CSRF_TOKEN);
        
        try {
            const response = await fetch(API_URL, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-CSRF-Token': CSRF_TOKEN
                }
            });
            
            const data = await response.json();
            
            if (data.success) {
                showNotification(translate('vendor_attributes.messages.deleted_successfully'), 'success');
                loadTable();
            } else {
                showNotification(data.message || translate('vendor_attributes.messages.delete_error'), 'error');
            }
        } catch (error) {
            console.error('Error deleting:', error);
            showNotification(translate('vendor_attributes.messages.delete_error'), 'error');
        }
    };

    // ===== إعداد أحداث المستخدم =====
    function setupEventListeners() {
        // حدث تقديم النموذج
        if (elements.vavForm) {
            elements.vavForm.addEventListener('submit', async function(e) {
                e.preventDefault();
                
                const formData = new FormData(this);
                const action = elements.vavId && elements.vavId.value 
                    ? 'update' 
                    : 'create';
                
                formData.append('action', action);
                formData.append('csrf_token', CSRF_TOKEN);

                try {
                    const response = await fetch(API_URL, {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'X-CSRF-Token': CSRF_TOKEN
                        }
                    });
                    
                    const data = await response.json();

                    if (data.success) {
                        const message = action === 'create' 
                            ? translate('vendor_attributes.messages.created_successfully')
                            : translate('vendor_attributes.messages.updated_successfully');
                        
                        showNotification(message, 'success');
                        hideForm();
                        loadTable();
                    } else {
                        showNotification(data.message || translate('vendor_attributes.messages.save_error'), 'error');
                    }
                } catch (error) {
                    console.error('Error saving:', error);
                    showNotification(translate('vendor_attributes.messages.save_error'), 'error');
                }
            });
        }

        // أحداث الأزرار
        if (elements.vavNew) {
            elements.vavNew.addEventListener('click', function() {
                resetForm();
                showForm();
            });
        }

        if (elements.vavCancel) {
            elements.vavCancel.addEventListener('click', hideForm);
        }

        if (elements.vavRefresh) {
            elements.vavRefresh.addEventListener('click', function() {
                loadTable();
                showNotification(translate('vendor_attributes.messages.data_refreshed'), 'info');
            });
        }

        // زر مسح الفلاتر
        if (elements.vavResetFilters) {
            elements.vavResetFilters.addEventListener('click', function() {
                if (typeof jQuery !== 'undefined' && jQuery().select2) {
                    $('#vavVendorFilter').val(null).trigger('change');
                    $('#vavAttributeFilter').val(null).trigger('change');
                } else {
                    const vendorFilter = document.getElementById('vavVendorFilter');
                    const attributeFilter = document.getElementById('vavAttributeFilter');
                    if (vendorFilter) vendorFilter.value = '';
                    if (attributeFilter) attributeFilter.value = '';
                }
                
                if (elements.vavSearch) elements.vavSearch.value = '';
                currentPage = 1;
                loadTable();
                showNotification(translate('vendor_attributes.messages.filters_cleared'), 'info');
            });
        }

        // البحث المتأخر
        let searchTimeout;
        if (elements.vavSearch) {
            elements.vavSearch.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    currentPage = 1;
                    loadTable();
                }, 500);
            });
        }
    }

    // ===== تهيئة التطبيق =====
    async function initialize() {
        try {
            // تطبيق الأنماط من الجداول
            applyDynamicStyles();
            applyStylesToElements();
            
            // تحميل الموارد
            await loadResources();
            
            // إعداد مستمعي الأحداث
            setupEventListeners();
            
            // تحميل البيانات الأولية
            await loadTable();

        } catch (error) {
            console.error('Error initializing vendor attributes:', error);
            showNotification(translate('vendor_attributes.messages.initialization_failed'), 'error');
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initialize);
    } else {
        initialize();
    }

})();