(function () {
    'use strict';

    const cfg = window.VWH_CONFIG;
    if (!cfg) {
        console.error('VWH_CONFIG is not defined');
        return;
    }

    // دالة للحصول على اسم اليوم
    function getDayName(dayNumber) {
        return cfg.days?.[dayNumber] || dayNumber;
    }

    // دالة للحصول على نص مترجم
    function t(key) {
        return cfg.translations?.[key] || key;
    }

    function initApp() {
        const dom = {
            tbody: document.getElementById('vwhTbody'),
            formWrap: document.getElementById('vwhFormWrap'),
            form: document.getElementById('vwhForm'),

            vendorFilter: document.getElementById('vwhVendorFilter'),
            dayFilter: document.getElementById('vwhDayFilter'),

            vendorSelect: document.getElementById('vwhVendor'),
            daySelect: document.getElementById('vwhDay'),
            openInput: document.getElementById('vwhOpen'),
            closeInput: document.getElementById('vwhClose'),
            closedCheck: document.getElementById('vwhClosed'),
            
            resetBtn: document.getElementById('vwhResetFilters')
        };

        if (!dom.tbody || !dom.form) {
            console.warn('Vendor Working Hours: Required DOM elements not found');
            return;
        }

        // تتبع حالة تهيئة Select2
        let select2Initialized = false;

        /* =======================
           تهيئة Select2 بشكل آمن (مرة واحدة فقط)
        ======================= */
        function initSelect2Once() {
            if (select2Initialized || typeof jQuery === 'undefined' || !jQuery().select2) {
                return;
            }

            // تهيئة الفلتر
            if (dom.vendorFilter) {
                $(dom.vendorFilter).select2({
                    placeholder: t('all_vendors'),
                    allowClear: true,
                    width: '100%',
                    dir: cfg.isRTL ? 'rtl' : 'ltr'
                }).on('change', loadTable);
            }

            // تهيئة النموذج
            if (dom.vendorSelect) {
                $(dom.vendorSelect).select2({
                    dropdownParent: cfg.isStandalone ? null : $(dom.formWrap),
                    width: '100%',
                    placeholder: t('select_vendor'),
                    dir: cfg.isRTL ? 'rtl' : 'ltr'
                });
            }

            select2Initialized = true;
            console.log('Select2 initialized successfully for', cfg.lang, 'language');
        }

        /* =======================
           تحديث قائمة التجار
        ======================= */
        function updateVendorsDropdowns(vendorsData) {
            if (!vendorsData || !Array.isArray(vendorsData)) {
                console.error('Invalid vendors data');
                return false;
            }

            const options = vendorsData.map(v =>
                `<option value="${v.id}">[ID: ${v.id}] ${v.store_name || 'Unknown'}</option>`
            ).join('');

            // تحديث الفلتر
            if (dom.vendorFilter) {
                const currentValue = dom.vendorFilter.value;
                dom.vendorFilter.innerHTML = '<option></option>' + options;
                if (currentValue) {
                    dom.vendorFilter.value = currentValue;
                }
                if (select2Initialized) {
                    $(dom.vendorFilter).trigger('change');
                }
            }

            // تحديث النموذج
            if (dom.vendorSelect) {
                const currentValue = dom.vendorSelect.value;
                const selectOption = `<option value="">${t('select_vendor')}</option>`;
                dom.vendorSelect.innerHTML = selectOption + options;
                if (currentValue) {
                    dom.vendorSelect.value = currentValue;
                }
                if (select2Initialized) {
                    $(dom.vendorSelect).trigger('change');
                }
            }
            
            return true;
        }

        /* =======================
           تحميل التجار
        ======================= */
        async function loadVendors() {
            try {
                console.log('Loading vendors from:', cfg.vendorsUrl);
                const res = await fetch(cfg.vendorsUrl);
                
                if (!res.ok) {
                    throw new Error(`HTTP error: ${res.status}`);
                }
                
                const json = await res.json();
                console.log('Vendors API response:', json);

                if (json.success && json.data && Array.isArray(json.data)) {
                    updateVendorsDropdowns(json.data);
                    console.log(`Loaded ${json.data.length} vendors for language: ${cfg.lang}`);
                    return json.data;
                } else {
                    throw new Error(json.message || t('error_loading'));
                }
            } catch (e) {
                console.error('Error loading vendors:', e);
                return null;
            }
        }

        /* =======================
           تحميل الجدول
        ======================= */
        async function loadTable() {
            if (!dom.tbody) return;
            
            dom.tbody.innerHTML = `<tr><td colspan="7" style="text-align:center;color:${cfg.theme.textSecondary};padding:30px;">${t('loading')}</td></tr>`;

            let vendorId = '';
            if (dom.vendorFilter) {
                vendorId = dom.vendorFilter.value;
            }

            const day = dom.dayFilter ? dom.dayFilter.value : '';

            const params = new URLSearchParams({
                vendor_id: vendorId,
                day_of_week: day
            });

            try {
                const res = await fetch(`${cfg.apiUrl}?${params}`);
                const json = await res.json();

                if (json.success && json.data && json.data.length) {
                    dom.tbody.innerHTML = json.data.map(r => `
                        <tr>
                            <td>${r.id}</td>
                            <td><span style="color:${cfg.theme.textPrimary};font-weight:600;">${r.vendor_name || 'N/A'}</span></td>
                            <td style="color:${cfg.theme.primary};">${getDayName(r.day_of_week)}</td>
                            <td>${r.open_time || '-'}</td>
                            <td>${r.close_time || '-'}</td>
                            <td style="text-align:center;">${r.is_closed == 1 ? '✔' : ''}</td>
                            <td style="text-align:center;">
                                <button class="vwh-btn btn-gray"
                                    onclick="vwhEditRow(
                                        ${r.id},
                                        ${r.vendor_id},
                                        ${r.day_of_week},
                                        '${(r.open_time || '').replace(/'/g, "\\'")}',
                                        '${(r.close_time || '').replace(/'/g, "\\'")}',
                                        ${r.is_closed || 0}
                                    )">${t('edit')}</button>
                                <button class="vwh-btn btn-danger"
                                    style="margin-inline-start:6px;"
                                    onclick="vwhDeleteRow(${r.id})">${t('delete')}</button>
                            </td>
                        </tr>
                    `).join('');
                } else {
                    dom.tbody.innerHTML = `<tr><td colspan="7" style="text-align:center;color:${cfg.theme.textSecondary};padding:40px;">${t('no_data')}</td></tr>`;
                }
            } catch (e) {
                console.error('Load table error:', e);
                dom.tbody.innerHTML = `<tr><td colspan="7" style="text-align:center;color:red;padding:40px;">${t('error_loading')}</td></tr>`;
            }
        }

        /* =======================
           إعادة ضبط الفلاتر
        ======================= */
        if (dom.resetBtn) {
            dom.resetBtn.onclick = () => {
                if (dom.vendorFilter) {
                    dom.vendorFilter.value = '';
                    if (select2Initialized) {
                        $(dom.vendorFilter).val(null).trigger('change');
                    }
                }
                
                if (dom.dayFilter) {
                    dom.dayFilter.value = '';
                }
                
                loadTable();
            };
        }

        /* =======================
           إضافة جديد
        ======================= */
        document.getElementById('vwhNew').onclick = () => {
            dom.form.reset();
            document.getElementById('vwhId').value = '';

            if (dom.vendorSelect) {
                dom.vendorSelect.value = '';
                if (select2Initialized) {
                    $(dom.vendorSelect).val(null).trigger('change');
                }
            }
            
            if (dom.daySelect) dom.daySelect.value = '0';
            if (dom.openInput) dom.openInput.value = '';
            if (dom.closeInput) dom.closeInput.value = '';
            if (dom.closedCheck) dom.closedCheck.checked = false;

            document.getElementById('vwhFormTitle').innerText = t('add_hours');
            dom.formWrap.style.display = 'flex';
        };

        if (document.getElementById('vwhCancel')) {
            document.getElementById('vwhCancel').onclick = () => dom.formWrap.style.display = 'none';
        }

        /* =======================
           الحفظ
        ======================= */
        dom.form.onsubmit = async (e) => {
            e.preventDefault();

            const fd = new FormData(dom.form);
            fd.append('action', 'save');

            if (dom.closedCheck && !dom.closedCheck.checked) {
                fd.set('is_closed', '0');
            }

            try {
                const res = await fetch(cfg.apiUrl, {
                    method: 'POST',
                    body: fd,
                    headers: { 
                        'X-CSRF-Token': cfg.csrfToken,
                        'Accept': 'application/json'
                    }
                });

                const json = await res.json();
                if (json.success) {
                    dom.formWrap.style.display = 'none';
                    loadTable();
                } else {
                    alert(json.message || t('save_error'));
                }
            } catch (e) {
                console.error('Save error:', e);
                alert(t('save_error'));
            }
        };

        /* =======================
           التعديل
        ======================= */
        window.vwhEditRow = (id, vendorId, day, open, close, closed) => {
            document.getElementById('vwhId').value = id;
            
            if (dom.daySelect) dom.daySelect.value = day;
            if (dom.openInput) dom.openInput.value = open;
            if (dom.closeInput) dom.closeInput.value = close;
            if (dom.closedCheck) dom.closedCheck.checked = closed == 1;

            if (dom.vendorSelect) {
                dom.vendorSelect.value = vendorId;
                if (select2Initialized) {
                    $(dom.vendorSelect).val(vendorId).trigger('change');
                }
            }

            document.getElementById('vwhFormTitle').innerText = t('edit_hours');
            dom.formWrap.style.display = 'flex';
        };

        /* =======================
           الحذف
        ======================= */
        window.vwhDeleteRow = async (id) => {
            if (!confirm(t('confirm_delete'))) return;

            const fd = new FormData();
            fd.append('action', 'delete');
            fd.append('id', id);
            fd.append('csrf_token', cfg.csrfToken);

            try {
                const res = await fetch(cfg.apiUrl, {
                    method: 'POST',
                    body: fd,
                    headers: { 
                        'X-CSRF-Token': cfg.csrfToken,
                        'Accept': 'application/json'
                    }
                });
                
                const json = await res.json();
                if (!json.success) {
                    alert(json.message || t('delete_error'));
                }
            } catch (e) {
                console.error('Delete error:', e);
                alert(t('delete_error'));
            }

            loadTable();
        };

        if (document.getElementById('vwhRefresh')) {
            document.getElementById('vwhRefresh').onclick = () => {
                loadVendors().then(loadTable);
            };
        }

        /* =======================
           البدء
        ======================= */
        console.log('Initializing Vendor Working Hours app...');
        console.log('Language:', cfg.lang);
        console.log('Direction:', cfg.direction);
        console.log('Theme colors:', cfg.theme);
        
        // 1. تهيئة Select2 (مرة واحدة فقط)
        initSelect2Once();
        
        // 2. تحميل التجار
        loadVendors().then(() => {
            // 3. تحميل الجدول
            loadTable();
            console.log('App initialized successfully for language:', cfg.lang);
        }).catch(e => {
            console.error('Initialization error:', e);
            // حاول تحميل الجدول على أي حال
            loadTable();
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initApp);
    } else {
        initApp();
    }
})();