/**
 * admin/assets/js/pages/vendor_working_hours.js
 * Vendor Working Hours Management - Respects translations and theme from ADMIN_UI_PAYLOAD
 */
(function () {
    'use strict';

    const cfg = window.VWH_CONFIG;
    if (!cfg) {
        console.error('VWH_CONFIG is not defined');
        return;
    }

    const trans = cfg.translations || {};
    const days = cfg.days || {};

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

    // Get day name
    const getDayName = (dayNumber) => days[dayNumber] || dayNumber;

    const dom = {
        tbody: document.getElementById('vwhTbody'),
        formWrap: document.getElementById('vwhFormWrap'),
        form: document.getElementById('vwhForm'),
        vendorFilter: document.getElementById('vwhVendorFilter'),
        dayFilter: document.getElementById('vwhDayFilter'),
        vendorSelect: document.getElementById('vwhVendor'),
        idInput: document.getElementById('vwhId'),
        daySelect: document.getElementById('vwhDay'),
        openInput: document.getElementById('vwhOpen'),
        closeInput: document.getElementById('vwhClose'),
        closedCheck: document.getElementById('vwhClosed'),
        formTitle: document.getElementById('vwhFormTitle'),
        newBtn: document.getElementById('vwhNew'),
        refreshBtn: document.getElementById('vwhRefresh'),
        resetBtn: document.getElementById('vwhResetFilters'),
        cancelBtn: document.getElementById('vwhCancel')
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

        if (dom.vendorSelect) {
            $(dom.vendorSelect).select2({
                dropdownParent: $(dom.formWrap),
                width: '100%',
                placeholder: t('select_vendor', 'Select Vendor')
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

    /* Load Working Hours Table */
    async function loadTable() {
        dom.tbody.innerHTML = `<tr><td colspan="7" style="text-align:center;padding:40px;">${t('loading', 'Loading...')}</td></tr>`;

        const params = new URLSearchParams({
            vendor_id: dom.vendorFilter.value,
            day_of_week: dom.dayFilter.value
        });

        try {
            const r = await fetch(`${cfg.apiUrl}?${params.toString()}`);
            const j = await r.json();

            dom.tbody.innerHTML = '';

            if (j.success && j.data && j.data.length > 0) {
                j.data.forEach(row => {
                    const tr = document.createElement('tr');
                    const vendorName = row.vendor_name || row.store_name || `Vendor ${row.vendor_id}`;
                    const dayName = getDayName(row.day_of_week);
                    const openTime = row.open_time || '-';
                    const closeTime = row.close_time || '-';
                    const isClosed = row.is_closed == 1;
                    const closedBadge = isClosed ? 
                        `<span style="color: var(--theme-error);">✓</span>` : 
                        `<span style="color: var(--theme-text-secondary);">—</span>`;

                    tr.innerHTML = `
                        <td>${row.id}</td>
                        <td>${vendorName}</td>
                        <td>${dayName}</td>
                        <td>${openTime}</td>
                        <td>${closeTime}</td>
                        <td style="text-align:center;">${closedBadge}</td>
                        <td style="text-align:center;">
                            <button class="vwh-btn btn-blue edit-btn" style="padding:4px 12px;font-size:0.85rem;" title="${t('edit', 'Edit')}">
                                ✏️
                            </button>
                            <button class="vwh-btn btn-gray delete-btn" style="padding:4px 12px;font-size:0.85rem;background: var(--theme-error);" title="${t('delete', 'Delete')}">
                                🗑
                            </button>
                        </td>
                    `;

                    tr.querySelector('.edit-btn').onclick = () => editRow(row);
                    tr.querySelector('.delete-btn').onclick = () => deleteRow(row.id);

                    dom.tbody.appendChild(tr);
                });
            } else {
                dom.tbody.innerHTML = `<tr><td colspan="7" style="text-align:center;padding:40px;color: var(--theme-text-secondary);">${t('no_data', 'No data found')}</td></tr>`;
            }
        } catch (e) {
            console.error('Error loading data:', e);
            dom.tbody.innerHTML = `<tr><td colspan="7" style="text-align:center;color: var(--theme-error);">${t('error_loading', 'Error loading data')}</td></tr>`;
        }
    }

    /* Open Form for New Entry */
    function openFormNew() {
        dom.formTitle.textContent = t('add_hours', 'Add Working Hours');
        dom.form.reset();
        dom.idInput.value = '';
        dom.formWrap.style.display = 'flex';
    }

    /* Open Form for Edit */
    function editRow(row) {
        dom.formTitle.textContent = t('edit_hours', 'Edit Working Hours');
        dom.idInput.value = row.id;
        
        if (dom.vendorSelect) {
            dom.vendorSelect.value = row.vendor_id;
            if (select2Initialized && $(dom.vendorSelect).data('select2')) {
                $(dom.vendorSelect).trigger('change');
            }
        }
        
        if (dom.daySelect) dom.daySelect.value = row.day_of_week;
        if (dom.openInput) dom.openInput.value = row.open_time || '';
        if (dom.closeInput) dom.closeInput.value = row.close_time || '';
        if (dom.closedCheck) dom.closedCheck.checked = row.is_closed == 1;

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
    if (dom.refreshBtn) dom.refreshBtn.onclick = () => { loadTable(); loadVendors(); };
    if (dom.cancelBtn) dom.cancelBtn.onclick = closeForm;
    if (dom.resetBtn) {
        dom.resetBtn.onclick = () => {
            if (dom.vendorFilter) dom.vendorFilter.value = '';
            if (dom.dayFilter) dom.dayFilter.value = '';
            if (select2Initialized && $(dom.vendorFilter).data('select2')) {
                $(dom.vendorFilter).val(null).trigger('change');
            }
            loadTable();
        };
    }

    if (dom.dayFilter) dom.dayFilter.onchange = loadTable;

    // Handle checkbox to disable/enable time inputs
    if (dom.closedCheck) {
        dom.closedCheck.onchange = () => {
            const disabled = dom.closedCheck.checked;
            if (dom.openInput) dom.openInput.disabled = disabled;
            if (dom.closeInput) dom.closeInput.disabled = disabled;
        };
    }

    // Initialize
    loadVendors();
    loadTable();
})();
