/**
 * admin/assets/js/pages/vendor_attributes_values.js
 * Vendor Attributes Values Management - Respects translations and theme from ADMIN_UI_PAYLOAD
 */
(function () {
    'use strict';

    const cfg = window.VAV_CONFIG;
    if (!cfg) {
        console.error('VAV_CONFIG is not defined');
        return;
    }

    const trans = cfg.translations || {};

    // Translation helper with fallback
    const t = (key, fallback = '') => {
        const parts = key.split('.');
        let value = trans;
        for (const part of parts) {
            if (value && typeof value === 'object' && part in value) {
                value = value[part];
            } else {
                return fallback || key;
            }
        }
        return typeof value === 'string' ? value : fallback || key;
    };

    const dom = {
        tbody: document.getElementById('vavTbody'),
        formWrap: document.getElementById('vavFormWrap'),
        form: document.getElementById('vavForm'),
        vendorFilter: document.getElementById('vavVendorFilter'),
        attributeFilter: document.getElementById('vavAttributeFilter'),
        searchInput: document.getElementById('vavSearch'),
        vendorSelect: document.getElementById('vavVendor'),
        attributeSelect: document.getElementById('vavAttribute'),
        valueInput: document.getElementById('vavValue'),
        idInput: document.getElementById('vavId'),
        formTitle: document.getElementById('vavFormTitle'),
        newBtn: document.getElementById('vavNew'),
        refreshBtn: document.getElementById('vavRefresh'),
        resetBtn: document.getElementById('vavResetFilters'),
        cancelBtn: document.getElementById('vavCancel')
    };

    if (!dom.tbody || !dom.form) {
        console.warn('Required DOM elements not found');
        return;
    }

    let select2Initialized = false;

    /* Initialize Select2 */
    function initSelect2() {
        if (select2Initialized || typeof jQuery === 'undefined' || !jQuery().select2) {
            return;
        }

        if (dom.vendorFilter) {
            $(dom.vendorFilter).select2({
                placeholder: t('all_vendors', 'All Vendors'),
                allowClear: true,
                width: '100%'
            }).on('change', loadTable);
        }

        if (dom.attributeFilter) {
            $(dom.attributeFilter).select2({
                placeholder: t('all_attributes', 'All Attributes'),
                allowClear: true,
                width: '100%'
            }).on('change', loadTable);
        }

        if (dom.vendorSelect) {
            $(dom.vendorSelect).select2({
                dropdownParent: $(dom.formWrap),
                width: '100%',
                placeholder: t('select_vendor', 'Select Vendor')
            });
        }

        if (dom.attributeSelect) {
            $(dom.attributeSelect).select2({
                dropdownParent: $(dom.formWrap),
                width: '100%',
                placeholder: t('select_attribute', 'Select Attribute')
            });
        }

        select2Initialized = true;
    }

    /* Load Vendors */
    async function loadVendors() {
        try {
            const r = await fetch(cfg.vendorsUrl);
            const j = await r.json();
            
            if (j.success && j.data) {
                const options = j.data.map(v =>
                    `<option value="${v.id}">${v.store_name || v.name || 'Vendor ' + v.id}</option>`
                ).join('');

                if (dom.vendorFilter) {
                    dom.vendorFilter.innerHTML = `<option value="">${t('all_vendors', 'All Vendors')}</option>` + options;
                }
                if (dom.vendorSelect) {
                    dom.vendorSelect.innerHTML = `<option value="">${t('select_vendor', 'Select Vendor')}</option>` + options;
                }

                initSelect2();
            }
        } catch (e) {
            console.error('Error loading vendors:', e);
        }
    }

    /* Load Attributes */
    async function loadAttributes() {
        try {
            const r = await fetch(cfg.attrsUrl);
            const j = await r.json();
            
            if (j.success && j.data) {
                const options = j.data.map(a =>
                    `<option value="${a.id}">${a.name || a.key_name || 'Attribute ' + a.id}</option>`
                ).join('');

                if (dom.attributeFilter) {
                    dom.attributeFilter.innerHTML = `<option value="">${t('all_attributes', 'All Attributes')}</option>` + options;
                }
                if (dom.attributeSelect) {
                    dom.attributeSelect.innerHTML = `<option value="">${t('select_attribute', 'Select Attribute')}</option>` + options;
                }

                initSelect2();
            }
        } catch (e) {
            console.error('Error loading attributes:', e);
        }
    }

    /* Load Table */
    async function loadTable() {
        dom.tbody.innerHTML = `<tr><td colspan="5" style="text-align:center;padding:40px;">${t('loading', 'Loading...')}</td></tr>`;

        const params = new URLSearchParams({
            vendor_id: dom.vendorFilter.value,
            attribute_id: dom.attributeFilter.value,
            q: dom.searchInput.value
        });

        try {
            const r = await fetch(`${cfg.apiUrl}?${params.toString()}`);
            const j = await r.json();

            dom.tbody.innerHTML = '';

            if (j.success && j.data && j.data.length > 0) {
                j.data.forEach(row => {
                    const tr = document.createElement('tr');
                    const vendorName = row.vendor_name || row.store_name || `Vendor ${row.vendor_id}`;
                    const attributeName = row.attribute_name || `Attribute ${row.attribute_id}`;
                    const value = row.value || '-';

                    tr.innerHTML = `
                        <td>${row.id}</td>
                        <td>${vendorName}</td>
                        <td>${attributeName}</td>
                        <td>${value}</td>
                        <td style="text-align:center;">
                            <button class="vav-btn btn-blue edit-btn" style="padding:4px 12px;font-size:0.85rem;" title="${t('edit', 'Edit')}">
                                ✏️
                            </button>
                            <button class="vav-btn btn-gray delete-btn" style="padding:4px 12px;font-size:0.85rem;background: var(--theme-error);" title="${t('delete', 'Delete')}">
                                🗑
                            </button>
                        </td>
                    `;

                    tr.querySelector('.edit-btn').onclick = () => editRow(row);
                    tr.querySelector('.delete-btn').onclick = () => deleteRow(row.id);

                    dom.tbody.appendChild(tr);
                });
            } else {
                dom.tbody.innerHTML = `<tr><td colspan="5" style="text-align:center;padding:40px;color: var(--theme-text-secondary);">${t('no_data', 'No data found')}</td></tr>`;
            }
        } catch (e) {
            console.error('Error loading data:', e);
            dom.tbody.innerHTML = `<tr><td colspan="5" style="text-align:center;color: var(--theme-error);">${t('error_loading', 'Error loading data')}</td></tr>`;
        }
    }

    /* Open Form for New Entry */
    function openFormNew() {
        dom.formTitle.textContent = t('add_attribute_value', 'Add Attribute Value');
        dom.form.reset();
        dom.idInput.value = '';
        dom.formWrap.style.display = 'flex';
    }

    /* Open Form for Edit */
    function editRow(row) {
        dom.formTitle.textContent = t('edit_attribute_value', 'Edit Attribute Value');
        dom.idInput.value = row.id;
        
        if (dom.vendorSelect) {
            dom.vendorSelect.value = row.vendor_id;
            if (select2Initialized && $(dom.vendorSelect).data('select2')) {
                $(dom.vendorSelect).trigger('change');
            }
        }
        
        if (dom.attributeSelect) {
            dom.attributeSelect.value = row.attribute_id;
            if (select2Initialized && $(dom.attributeSelect).data('select2')) {
                $(dom.attributeSelect).trigger('change');
            }
        }
        
        if (dom.valueInput) dom.valueInput.value = row.value || '';

        dom.formWrap.style.display = 'flex';
    }

    /* Close Form */
    function closeForm() {
        dom.formWrap.style.display = 'none';
        dom.form.reset();
        dom.idInput.value = '';
    }

    /* Submit Form */
    dom.form.onsubmit = async (e) => {
        e.preventDefault();
        const fd = new FormData(dom.form);
        const id = dom.idInput.value;
        fd.append('action', id ? 'update' : 'create');
        fd.set('csrf_token', cfg.csrfToken);

        try {
            const r = await fetch(cfg.apiUrl, { method: 'POST', body: fd });
            const j = await r.json();
            
            if (j.success) {
                closeForm();
                loadTable();
            } else {
                alert(j.message || t('error_save', 'Error saving data'));
            }
        } catch (err) {
            console.error('Submit error:', err);
            alert(t('error_save', 'Error saving data'));
        }
    };

    /* Delete Row */
    async function deleteRow(id) {
        if (!confirm(t('confirm_delete', 'Are you sure you want to delete this entry?'))) {
            return;
        }

        const fd = new FormData();
        fd.append('id', id);
        fd.append('action', 'delete');
        fd.set('csrf_token', cfg.csrfToken);

        try {
            const r = await fetch(cfg.apiUrl, { method: 'POST', body: fd });
            const j = await r.json();
            
            if (j.success) {
                loadTable();
            } else {
                alert(j.message || t('error_delete', 'Error deleting entry'));
            }
        } catch (err) {
            console.error('Delete error:', err);
            alert(t('error_delete', 'Error deleting entry'));
        }
    }

    /* Event Listeners */
    if (dom.newBtn) dom.newBtn.onclick = openFormNew;
    if (dom.refreshBtn) {
        dom.refreshBtn.onclick = () => {
            loadTable();
            loadVendors();
            loadAttributes();
        };
    }
    if (dom.cancelBtn) dom.cancelBtn.onclick = closeForm;
    if (dom.resetBtn) {
        dom.resetBtn.onclick = () => {
            if (dom.vendorFilter) dom.vendorFilter.value = '';
            if (dom.attributeFilter) dom.attributeFilter.value = '';
            if (dom.searchInput) dom.searchInput.value = '';
            
            if (select2Initialized) {
                if ($(dom.vendorFilter).data('select2')) {
                    $(dom.vendorFilter).val(null).trigger('change');
                }
                if ($(dom.attributeFilter).data('select2')) {
                    $(dom.attributeFilter).val(null).trigger('change');
                }
            }
            
            loadTable();
        };
    }

    // Search with debounce
    let searchTimer;
    if (dom.searchInput) {
        dom.searchInput.oninput = () => {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(loadTable, 400);
        };
    }

    // Initialize
    loadVendors();
    loadAttributes();
    loadTable();
})();
