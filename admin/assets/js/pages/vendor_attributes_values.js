(function () {
    'use strict';
    
    // التحقق من وجود التكوين
    const cfg = window.VAV_CONFIG;
    if (!cfg) {
        console.error('VAV_CONFIG is not defined');
        return;
    }
    
    // دالة لتهيئة التطبيق بعد تحميل DOM
    function initApp() {
        const dom = {
            tbody: document.getElementById('vavTbody'),
            formWrap: document.getElementById('vavFormWrap'),
            form: document.getElementById('vavForm'),
            vFilter: document.getElementById('vavVendorFilter'),
            aFilter: document.getElementById('vavAttributeFilter'),
            vSelect: document.getElementById('vavVendor'),
            aSelect: document.getElementById('vavAttribute'),
            search: document.getElementById('vavSearch'),
            resetBtn: document.getElementById('vavResetFilters')
        };
        
        // التحقق من وجود العناصر الأساسية
        if (!dom.tbody || !dom.form) {
            console.warn('Vendor Attributes: Required DOM elements not found');
            return;
        }

        /** تفعيل محرك البحث الذكي (Select2) */
        function initSelect2() {
            // في الداشبورد، jQuery قد تكون محملة مسبقاً
            if (typeof jQuery !== 'undefined' && jQuery().select2) {
                const $vFilter = $(dom.vFilter);
                const $aFilter = $(dom.aFilter);

                // إعداد فلاتر البحث مع خاصية المسح (Clear)
                $vFilter.select2({ 
                    placeholder: cfg.lang == 'ar' ? "كل الموردين" : "All Vendors",
                    allowClear: true,
                    width: '100%'
                });
                $aFilter.select2({ 
                    placeholder: cfg.lang == 'ar' ? "كل الخصائص" : "All Attributes",
                    allowClear: true,
                    width: '100%'
                });
                
                $(dom.vSelect).select2({ 
                    dropdownParent: cfg.isStandalone ? null : $(dom.formWrap), 
                    width: '100%' 
                });
                $(dom.aSelect).select2({ 
                    dropdownParent: cfg.isStandalone ? null : $(dom.formWrap), 
                    width: '100%' 
                });

                // تحديث الجدول عند التغيير أو المسح
                $vFilter.on('change.select2', () => loadTable());
                $aFilter.on('change.select2', () => loadTable());
            } else {
                console.warn('Select2 not available, using native selects');
            }
        }

        /** تحميل الموارد (التجار والخصائص) */
        async function loadResources() {
            try {
                const [vRes, aRes] = await Promise.all([
                    fetch(cfg.vendorsUrl),
                    fetch(cfg.attrsUrl)
                ]);

                const vJson = await vRes.json();
                if (vJson.success && vJson.data) {
                    const html = vJson.data.map(v => 
                        `<option value="${v.id}">[ID: ${v.id}] ${v.store_name}</option>`
                    ).join('');
                    dom.vSelect.innerHTML = '<option value="">-- اختر مورد --</option>' + html;
                    // نترك الخيار الأول فارغاً ليعمل الـ Placeholder والـ Clear بشكل صحيح
                    dom.vFilter.innerHTML = `<option></option>` + html;
                }

                const aJson = await aRes.json();
                if (aJson.success && aJson.data) {
                    const html = aJson.data.map(a => `<option value="${a.id}">${a.display_name}</option>`).join('');
                    dom.aSelect.innerHTML = '<option value="">-- اختر خاصية --</option>' + html;
                    dom.aFilter.innerHTML = `<option></option>` + html;
                }

                initSelect2();
            } catch (e) { 
                console.error("Error loading resources", e); 
                dom.tbody.innerHTML = '<tr><td colspan="5" style="text-align:center; color:red;">خطأ في تحميل البيانات</td></tr>';
            }
        }

        /** جلب بيانات الجدول الرئيسي */
        async function loadTable() {
            if (!dom.tbody) return;
            dom.tbody.innerHTML = '<tr><td colspan="5" style="text-align:center; padding:30px; color:#666;">جاري التحميل...</td></tr>';

            try {
                // جلب القيم - إذا كانت فارغة ستُرسل كنص فارغ للسيرفر
                let vendorId = '';
                let attributeId = '';
                
                if (typeof jQuery !== 'undefined' && jQuery().select2 && dom.vFilter) {
                    vendorId = $(dom.vFilter).val() || '';
                } else if (dom.vFilter) {
                    vendorId = dom.vFilter.value;
                }
                
                if (typeof jQuery !== 'undefined' && jQuery().select2 && dom.aFilter) {
                    attributeId = $(dom.aFilter).val() || '';
                } else if (dom.aFilter) {
                    attributeId = dom.aFilter.value;
                }
                
                const searchText = dom.search.value;

                const p = new URLSearchParams({
                    vendor_id: vendorId,
                    attribute_id: attributeId,
                    search: searchText
                });

                const res = await fetch(`${cfg.apiUrl}?${p}`);
                const json = await res.json();
                
                if (json.success && json.data && json.data.length > 0) {
                    dom.tbody.innerHTML = json.data.map(i => `
                        <tr>
                            <td>${i.id}</td>
                            <td><span style="color:#fff; font-weight:600;">${i.vendor_name || 'N/A'}</span></td>
                            <td style="color:#3b82f6;">${i.attribute_slug}</td>
                            <td>${i.value}</td>
                            <td style="text-align:center;">
                                <button type="button" class="vav-btn btn-gray" onclick="vavEditRow(${i.id},${i.vendor_id},${i.attribute_id},'${i.value.replace(/'/g, "\\'")}')">Edit</button>
                                <button type="button" class="vav-btn" style="background:#450a0a; color:#f87171; margin-inline-start:5px;" onclick="vavDeleteRow(${i.id})">Delete</button>
                            </td>
                        </tr>`).join('');
                } else { 
                    dom.tbody.innerHTML = `<tr><td colspan="5" style="text-align:center; padding:40px; color:#666;">${cfg.lang == 'ar' ? 'لا توجد بيانات (اختر مورد أو خاصية لعرض نتائجها)' : 'No results found'}</td></tr>`; 
                }
            } catch (e) { 
                console.error('Error loading table:', e);
                dom.tbody.innerHTML = '<tr><td colspan="5" style="text-align:center; color:red;">Error loading table</td></tr>'; 
            }
        }

        // زر إعادة ضبط كل الفلاتر
        if (dom.resetBtn) {
            dom.resetBtn.onclick = () => {
                if (typeof jQuery !== 'undefined' && jQuery().select2) {
                    $(dom.vFilter).val(null).trigger('change');
                    $(dom.aFilter).val(null).trigger('change');
                } else {
                    dom.vFilter.value = '';
                    dom.aFilter.value = '';
                }
                dom.search.value = '';
                loadTable();
            };
        }

        // إدارة النافذة (فتح/إغلاق)
        document.getElementById('vavNew').onclick = () => {
            dom.form.reset();
            if (typeof jQuery !== 'undefined' && jQuery().select2) {
                $(dom.vSelect).val(null).trigger('change');
                $(dom.aSelect).val(null).trigger('change');
            } else {
                dom.vSelect.value = '';
                dom.aSelect.value = '';
            }
            document.getElementById('vavId').value = '';
            document.getElementById('vavFormTitle').innerText = cfg.lang == 'ar' ? 'إضافة سجل جديد' : 'New Record';
            dom.formWrap.style.display = 'flex';
        };

        document.getElementById('vavCancel').onclick = () => dom.formWrap.style.display = 'none';

        // الحفظ (AJAX)
        dom.form.onsubmit = async (e) => {
            e.preventDefault();
            const fd = new FormData(dom.form);
            fd.append('action', 'save');
            try {
                const res = await fetch(cfg.apiUrl, { 
                    method: 'POST', 
                    body: fd, 
                    headers: { 'X-CSRF-Token': cfg.csrfToken } 
                });
                const j = await res.json();
                if (j.success) { 
                    dom.formWrap.style.display = 'none'; 
                    loadTable(); 
                } else {
                    alert(j.message || "Save error");
                }
            } catch (e) { 
                alert("Save Error"); 
                console.error('Save error:', e);
            }
        };

        // التعديل
        window.vavEditRow = (id, vId, aId, val) => {
            document.getElementById('vavId').value = id;
            if (typeof jQuery !== 'undefined' && jQuery().select2) {
                $(dom.vSelect).val(vId).trigger('change');
                $(dom.aSelect).val(aId).trigger('change');
            } else {
                dom.vSelect.value = vId;
                dom.aSelect.value = aId;
            }
            document.getElementById('vavValue').value = val;
            document.getElementById('vavFormTitle').innerText = cfg.lang == 'ar' ? 'تعديل السجل' : 'Edit Record';
            dom.formWrap.style.display = 'flex';
        };

        // الحذف
        window.vavDeleteRow = async (id) => {
            if (!confirm(cfg.lang == 'ar' ? 'هل أنت متأكد من الحذف؟' : 'Are you sure?')) return;
            const fd = new FormData();
            fd.append('action', 'delete'); 
            fd.append('id', id);
            fd.append('csrf_token', cfg.csrfToken);
            
            await fetch(cfg.apiUrl, { 
                method: 'POST', 
                body: fd, 
                headers: { 'X-CSRF-Token': cfg.csrfToken } 
            });
            loadTable();
        };

        // تحديث عند البحث النصي (Debounce 500ms)
        dom.search.oninput = () => { 
            clearTimeout(window.vavT); 
            window.vavT = setTimeout(loadTable, 500); 
        };
        
        document.getElementById('vavRefresh').onclick = loadTable;

        // الانطلاق
        loadResources().then(loadTable);
    }
    
    // انتظر حتى يتم تحميل DOM
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initApp);
    } else {
        initApp();
    }
})();
