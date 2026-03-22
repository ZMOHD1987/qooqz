(function () {
    'use strict';

    /**
     * /admin/assets/js/pages/escrow.js
     * Escrow Management Module
     *
     * Relations: currencies (/api/currencies), orders (/api/orders),
     *            entity_types (/api/entity_types), entities (/api/entities)
     */

    const CONFIG = window.ESCROW_CONFIG || {};
    const PERMS  = window.PAGE_PERMISSIONS || {};

    const API = {
        escrow:      CONFIG.apiUrl         || '/api/escrow_transactions',
        history:     CONFIG.historyApi     || '/api/escrow_status_history',
        disputes:    CONFIG.disputesApi    || '/api/escrow_disputes',
        ledger:      CONFIG.ledgerApi      || '/api/escrow_ledger',
        currencies:  CONFIG.currenciesApi  || '/api/currencies',
        orders:      CONFIG.ordersApi      || '/api/orders',
        entityTypes: CONFIG.entityTypesApi || '/api/entity_types',
        entities:    CONFIG.entitiesApi    || '/api/entities'
    };

    const state = {
        page: 1, perPage: CONFIG.itemsPerPage || 20, total: 0,
        transactions: [], currentTransaction: null,
        disputesList: [], historyList: [], ledgerList: [],
        currencies: [], orders: [], entityTypes: [], entities: [],
        filters: {}, permissions: PERMS,
        lang: CONFIG.lang || (window.APP_CONFIG && window.APP_CONFIG.LANG) || 'en',
        csrfToken: CONFIG.csrfToken || (window.APP_CONFIG && window.APP_CONFIG.CSRF_TOKEN ? window.APP_CONFIG.CSRF_TOKEN : ''),
        tenantId: CONFIG.tenantId || (window.APP_CONFIG && window.APP_CONFIG.TENANT_ID ? window.APP_CONFIG.TENANT_ID : 1)
    };

    let el = {};

    // ─── Translation helper ───────────────────────────────────
    function t(key, fb) {
        if (window._admin && typeof window._admin.t === 'function') {
            const val = window._admin.t(key);
            if (val && val !== key) return val;
        }
        if (window.TRANSLATIONS) {
            const parts = key.split('.');
            let val = window.TRANSLATIONS;
            for (const p of parts) {
                if (val == null || typeof val !== 'object') { val = undefined; break; }
                val = val[p];
            }
            if (val !== undefined && val !== null && typeof val === 'string') return val;
        }
        return fb !== undefined ? fb : key;
    }

    function esc(text) {
        if (text === null || text === undefined) return '';
        const d = document.createElement('div');
        d.textContent = String(text);
        return d.innerHTML;
    }

    // ─── API helper ───────────────────────────────────────────
    async function apiCall(url, opts) {
        const defaults = {
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        };
        if (opts && opts.method && opts.method !== 'GET') {
            defaults.headers['X-CSRF-Token'] = state.csrfToken;
        }
        const config = Object.assign({}, defaults, opts || {});
        if (opts && opts.headers) {
            config.headers = Object.assign({}, defaults.headers, opts.headers);
        }
        const res  = await fetch(url, config);
        const data = await res.json().catch(function () { return {}; });
        if (!res.ok) throw new Error(data.error || 'HTTP ' + res.status);
        return data;
    }

    // ─── Populate dropdown ────────────────────────────────────
    function populateDropdown(selectEl, data, valueKey, textKey, placeholder) {
        if (!selectEl) return;
        selectEl.innerHTML = '';
        if (placeholder !== undefined) {
            const opt = document.createElement('option');
            opt.value = '';
            opt.textContent = placeholder;
            selectEl.appendChild(opt);
        }
        (data || []).forEach(function (item) {
            const opt = document.createElement('option');
            opt.value = item[valueKey];
            opt.textContent = item[textKey];
            selectEl.appendChild(opt);
        });
    }

    // ─── Load all dropdown reference data ─────────────────────
    async function loadDropdownData() {
        // Currencies
        try {
            const res = await apiCall(API.currencies + '?format=json');
            if (res.success) {
                const data = Array.isArray(res.data) ? res.data
                    : (res.data && res.data.items ? res.data.items : (res.data && res.data.data ? res.data.data : []));
                state.currencies = data;
            }
        } catch (err) {
            console.warn('[Escrow] Failed to load currencies:', err);
            state.currencies = [
                { code: 'SAR', name: 'SAR – Saudi Riyal' },
                { code: 'USD', name: 'USD – US Dollar' },
                { code: 'EUR', name: 'EUR – Euro' },
                { code: 'AED', name: 'AED – UAE Dirham' }
            ];
        }
        populateDropdown(el.currencyCode, state.currencies, 'code', 'name',
            t('form.fields.currency_code.select', 'Select currency'));
        // Also populate the filter dropdown
        populateDropdown(document.getElementById('esc-currencyFilter'), state.currencies, 'code', 'code',
            t('filters.all_currencies', 'All Currencies'));

        // Entity types
        try {
            const res = await apiCall(API.entityTypes + '?format=json');
            if (res.success) {
                const data = Array.isArray(res.data) ? res.data
                    : (res.data && res.data.items ? res.data.items : (res.data && res.data.data ? res.data.data : []));
                state.entityTypes = data;
            }
        } catch (err) {
            console.warn('[Escrow] Failed to load entity types:', err);
        }
        populateDropdown(el.buyerEntityType, state.entityTypes, 'code', 'name',
            t('form.fields.buyer_entity_type.select', 'Select entity type'));
        populateDropdown(el.sellerEntityType, state.entityTypes, 'code', 'name',
            t('form.fields.seller_entity_type.select', 'Select entity type'));

        // Orders (for current tenant)
        try {
            const res = await apiCall(API.orders + '?format=json&tenant_id=' + state.tenantId + '&limit=500');
            if (res.success) {
                const data = Array.isArray(res.data) ? res.data
                    : (res.data && res.data.items ? res.data.items : (res.data && res.data.data ? res.data.data : []));
                state.orders = data;
            }
        } catch (err) {
            console.warn('[Escrow] Failed to load orders:', err);
        }
        populateDropdown(el.orderId, state.orders, 'id', 'order_number',
            t('form.fields.order_id.select', 'Select order (optional)'));

        console.log('[Escrow] Dropdown data loaded');
    }

    // ─── Load entities by type ────────────────────────────────
    async function loadEntitiesByType(entityType, targetSelectEl) {
        if (!targetSelectEl) return;
        targetSelectEl.innerHTML = '<option value="">' + t('common.loading', 'Loading…') + '</option>';
        targetSelectEl.disabled = true;
        if (!entityType) {
            targetSelectEl.innerHTML = '<option value="">' + t('form.fields.buyer_entity_id.select', 'Select entity') + '</option>';
            targetSelectEl.disabled = false;
            return;
        }
        try {
            const res = await apiCall(
                API.entities + '?format=json&tenant_id=' + state.tenantId + '&limit=500'
            );
            if (res.success) {
                const data = Array.isArray(res.data) ? res.data
                    : (res.data && res.data.items ? res.data.items : (res.data && res.data.data ? res.data.data : []));
                // Use store_name field (from entities table); fall back to name if not present
                const labelKey = (data.length && data[0].store_name !== undefined) ? 'store_name' : 'name';
                populateDropdown(targetSelectEl, data, 'id', labelKey,
                    t('form.fields.buyer_entity_id.select', 'Select entity'));
            }
        } catch (err) {
            console.warn('[Escrow] Failed to load entities:', err);
            targetSelectEl.innerHTML = '<option value="">' + t('common.load_error', 'Load failed') + '</option>';
        } finally {
            targetSelectEl.disabled = false;
        }
    }

    // ─── Load and set entity (helper for showForm) ────────────
    async function loadAndSetEntity(entityType, entityId, typeEl, idEl) {
        if (!typeEl) return;
        typeEl.value = entityType || '';
        await loadEntitiesByType(entityType, idEl);
        if (idEl) idEl.value = entityId || '';
    }

    // ─── Status badge ─────────────────────────────────────────
    function statusBadge(status) {
        return '<span class="badge badge-' + esc(status) + '">' +
               esc(t('status.' + status, status)) + '</span>';
    }

    // ─── Format amount with currency ──────────────────────────
    function formatAmount(amount, currencyCode, row) {
        if (amount == null) return '-';
        const decimalPlaces = (row && row.currency_decimal_places != null)
            ? parseInt(row.currency_decimal_places, 10) : 2;
        const formatted = parseFloat(amount).toFixed(decimalPlaces);
        if (row && row.currency_symbol) {
            const pos = row.currency_symbol_position || 'before';
            return pos === 'after'
                ? esc(formatted) + '\u00a0' + esc(row.currency_symbol)
                : esc(row.currency_symbol) + '\u00a0' + esc(formatted);
        }
        return esc(formatted) + '\u00a0' + esc(currencyCode || 'USD');
    }

    // ─── Get order number by id ───────────────────────────────
    function getOrderNumber(orderId, row) {
        if (!orderId) return '-';
        // Prefer enriched field returned by API
        if (row && row.order_number) return esc(row.order_number);
        const order = state.orders.find(function (o) { return String(o.id) === String(orderId); });
        return order ? esc(order.order_number) : ('#' + orderId);
    }

    // ─── Get entity name by id ────────────────────────────────
    function getEntityLabel(entityId, entityType, storeName) {
        if (!entityId) return '-';
        // Prefer enriched store_name returned by API
        if (storeName) return esc(storeName);
        return esc(entityType || '') + ' #' + esc(entityId);
    }

    // ─── Load list ────────────────────────────────────────────
    async function loadEscrow(page) {
        try {
            showLoading();
            state.page = page || 1;
            const params = new URLSearchParams({
                page:      state.page,
                limit:     state.perPage,
                tenant_id: state.tenantId
            });
            Object.keys(state.filters).forEach(function (k) {
                if (state.filters[k]) params.set(k, state.filters[k]);
            });
            const result = await apiCall(API.escrow + '?' + params);
            if (result.success) {
                state.transactions = result.data && result.data.items ? result.data.items
                    : (Array.isArray(result.data) ? result.data : []);
                state.total = (result.data && result.data.meta && result.data.meta.total)
                    ? result.data.meta.total : state.transactions.length;
                renderTable(state.transactions);
                updatePagination(state.total);
                showTable();
            } else {
                throw new Error(result.message || t('messages.error.load_failed', 'Failed to load'));
            }
        } catch (err) {
            showError(err.message);
        }
    }

    // ─── Render table ─────────────────────────────────────────
    function renderTable(items) {
        if (!el.tbody) return;
        if (!items.length) { showEmpty(); return; }
        el.tbody.innerHTML = items.map(function (r) {
            return '<tr data-id="' + r.id + '">' +
                '<td>#' + esc(r.id) + '</td>' +
                '<td><strong>' + esc(r.escrow_number || '-') + '</strong></td>' +
                '<td>' + getOrderNumber(r.order_id, r) + '</td>' +
                '<td>' + getEntityLabel(r.buyer_entity_id, r.buyer_entity_type, r.buyer_store_name) + '</td>' +
                '<td>' + getEntityLabel(r.seller_entity_id, r.seller_entity_type, r.seller_store_name) + '</td>' +
                '<td>' + formatAmount(r.amount, r.currency_code, r) + '</td>' +
                '<td>' + statusBadge(r.status) + '</td>' +
                '<td>' + (r.created_at ? new Date(r.created_at).toLocaleDateString() : '-') + '</td>' +
                '<td>' +
                    '<div class="table-actions">' +
                        (state.permissions.canEdit !== false
                            ? '<button class="btn btn-sm btn-secondary" onclick="Escrow.edit(' + r.id + ')" ' +
                              'title="' + esc(t('form.edit_title', 'Edit')) + '"><i class="fas fa-edit"></i></button>'
                            : '') +
                        (state.permissions.canDelete
                            ? '<button class="btn btn-sm btn-danger" onclick="Escrow.remove(' + r.id + ')" ' +
                              'title="' + esc(t('form.buttons.delete', 'Delete')) + '"><i class="fas fa-trash"></i></button>'
                            : '') +
                    '</div>' +
                '</td>' +
            '</tr>';
        }).join('');
    }

    // ─── Show form ────────────────────────────────────────────
    async function showForm(data) {
        state.currentTransaction = data || null;
        state.disputesList = [];
        state.historyList  = [];
        state.ledgerList   = [];

        if (el.form) el.form.reset();
        if (el.formContainer) {
            el.formContainer.style.display = 'block';
            el.formContainer.scrollIntoView({ behavior: 'smooth' });
        }

        // Reset tabs
        if (el.formContainer) {
            el.formContainer.querySelectorAll('.tab-btn').forEach(function (b) { b.classList.remove('active'); });
            el.formContainer.querySelectorAll('.tab-content').forEach(function (c) { c.style.display = 'none'; });
        }
        const firstTabBtn  = el.formContainer && el.formContainer.querySelector('.tab-btn[data-tab="details"]');
        const detailsPane  = document.getElementById('esc-tab-details');
        if (firstTabBtn) firstTabBtn.classList.add('active');
        if (detailsPane) detailsPane.style.display = 'block';

        // Re-populate dropdowns (in case data wasn't loaded yet)
        populateDropdown(el.currencyCode, state.currencies, 'code', 'name',
            t('form.fields.currency_code.select', 'Select currency'));
        populateDropdown(el.buyerEntityType, state.entityTypes, 'code', 'name',
            t('form.fields.buyer_entity_type.select', 'Select entity type'));
        populateDropdown(el.sellerEntityType, state.entityTypes, 'code', 'name',
            t('form.fields.seller_entity_type.select', 'Select entity type'));
        populateDropdown(el.orderId, state.orders, 'id', 'order_number',
            t('form.fields.order_id.select', 'Select order (optional)'));

        if (data) {
            if (el.formTitle)     el.formTitle.textContent   = t('form.edit_title', 'Edit Escrow') + ' #' + data.id;
            if (el.formId)        el.formId.value            = data.id;
            if (el.escrowNumber)  el.escrowNumber.value      = data.escrow_number || '';
            if (el.status)        el.status.value            = data.status || 'pending';
            if (el.orderId)       el.orderId.value           = data.order_id || '';
            if (el.amount)        el.amount.value            = data.amount || '';
            if (el.escrowFee)     el.escrowFee.value         = data.escrow_fee || '0';
            if (el.currencyCode)  el.currencyCode.value      = data.currency_code || 'USD';
            if (el.autoRelease)   el.autoRelease.value       = data.auto_release_days || '7';
            if (el.notes)         el.notes.value             = data.notes || '';

            // Buyer / Seller: load entities for the given type, then set the selected value
            await loadAndSetEntity(data.buyer_entity_type, data.buyer_entity_id,
                el.buyerEntityType, el.buyerEntityId);
            await loadAndSetEntity(data.seller_entity_type, data.seller_entity_id,
                el.sellerEntityType, el.sellerEntityId);

            if (el.btnDelete) el.btnDelete.style.display = 'inline-flex';
            await loadTransactionDetails(data.id);
        } else {
            if (el.formTitle)  el.formTitle.textContent   = t('form.add_title', 'New Escrow Transaction');
            if (el.formId)     el.formId.value            = '';
            if (el.btnDelete)  el.btnDelete.style.display = 'none';
            // Reset entity dropdowns
            if (el.buyerEntityId) {
                el.buyerEntityId.innerHTML = '<option value="">' + t('form.fields.buyer_entity_id.select', 'Select buyer') + '</option>';
            }
            if (el.sellerEntityId) {
                el.sellerEntityId.innerHTML = '<option value="">' + t('form.fields.seller_entity_id.select', 'Select seller') + '</option>';
            }
        }
    }

    function hideForm() {
        if (el.formContainer) el.formContainer.style.display = 'none';
        state.currentTransaction = null;
    }

    // ─── Load related details ─────────────────────────────────
    async function loadTransactionDetails(escrowId) {
        // Status history
        try {
            const res = await apiCall(
                API.history + '?escrow_id=' + escrowId +
                '&tenant_id=' + state.tenantId + '&order_by=id&order_dir=ASC'
            );
            if (res.success) {
                state.historyList = res.data && res.data.items ? res.data.items
                    : (Array.isArray(res.data) ? res.data : []);
                renderHistory();
            }
        } catch (e) { /* silent */ }

        // Disputes
        try {
            const res = await apiCall(
                API.disputes + '?escrow_id=' + escrowId + '&tenant_id=' + state.tenantId
            );
            if (res.success) {
                state.disputesList = res.data && res.data.items ? res.data.items
                    : (Array.isArray(res.data) ? res.data : []);
                renderDisputes();
            }
        } catch (e) { /* silent */ }

        // Ledger
        try {
            const res = await apiCall(
                API.ledger + '?escrow_id=' + escrowId + '&tenant_id=' + state.tenantId
            );
            if (res.success) {
                state.ledgerList = res.data && res.data.items ? res.data.items
                    : (Array.isArray(res.data) ? res.data : []);
                renderLedger();
            }
        } catch (e) { /* silent */ }
    }

    // ─── Render history ───────────────────────────────────────
    function renderHistory() {
        if (!el.historyList) return;
        if (!state.historyList.length) {
            el.historyList.innerHTML =
                '<p style="color:var(--text-secondary);text-align:center;padding:20px">' +
                t('history.empty', 'No status history yet') + '</p>';
            return;
        }
        el.historyList.innerHTML = '<div class="history-list">' +
            state.historyList.map(function (h) {
                return '<div class="history-item">' +
                    '<div class="history-item-content">' +
                    '<div>' + statusBadge(h.status) +
                    (h.changed_by_entity_id
                        ? ' &nbsp;<small style="color:var(--text-secondary)">by #' + esc(h.changed_by_entity_id) + '</small>'
                        : '') +
                    '</div>' +
                    (h.notes
                        ? '<div style="margin-top:4px;font-size:0.85rem;color:var(--text-secondary)">' +
                          esc(h.notes) + '</div>'
                        : '') +
                    '</div>' +
                    '<div class="history-item-date">' +
                    (h.created_at ? new Date(h.created_at).toLocaleString() : '') +
                    '</div>' +
                    '</div>';
            }).join('') + '</div>';
    }

    // ─── Render disputes ──────────────────────────────────────
    function renderDisputes() {
        if (!el.disputesList) return;
        if (!state.disputesList.length) {
            el.disputesList.innerHTML =
                '<p style="color:var(--text-secondary);text-align:center;padding:20px">' +
                t('disputes.empty', 'No disputes for this escrow') + '</p>';
            return;
        }
        el.disputesList.innerHTML = '<div class="items-table-wrapper"><table class="items-table">' +
            '<thead><tr>' +
            '<th>' + t('disputes.headers.number',      'Dispute #')    + '</th>' +
            '<th>' + t('disputes.headers.type',        'Type')         + '</th>' +
            '<th>' + t('disputes.headers.status',      'Status')       + '</th>' +
            '<th>' + t('disputes.headers.description', 'Description')  + '</th>' +
            '<th>' + t('disputes.headers.created_at',  'Created')      + '</th>' +
            '</tr></thead><tbody>' +
            state.disputesList.map(function (d) {
                return '<tr>' +
                    '<td>' + esc(d.dispute_number || '#' + d.id) + '</td>' +
                    '<td>' + esc(d.dispute_type || '-') + '</td>' +
                    '<td>' + statusBadge(d.status) + '</td>' +
                    '<td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">' +
                    esc(d.description || '-') + '</td>' +
                    '<td>' + (d.created_at ? new Date(d.created_at).toLocaleDateString() : '-') + '</td>' +
                    '</tr>';
            }).join('') +
            '</tbody></table></div>';
    }

    // ─── Render ledger ────────────────────────────────────────
    function renderLedger() {
        if (!el.ledgerList) return;
        if (!state.ledgerList.length) {
            el.ledgerList.innerHTML =
                '<p style="color:var(--text-secondary);text-align:center;padding:20px">' +
                t('ledger.empty', 'No ledger entries yet') + '</p>';
            return;
        }
        el.ledgerList.innerHTML = '<div class="items-table-wrapper"><table class="items-table">' +
            '<thead><tr>' +
            '<th>' + t('ledger.headers.type',       'Type')     + '</th>' +
            '<th>' + t('ledger.headers.amount',     'Amount')   + '</th>' +
            '<th>' + t('ledger.headers.currency',   'Currency') + '</th>' +
            '<th>' + t('ledger.headers.entity',     'Entity')   + '</th>' +
            '<th>' + t('ledger.headers.notes',      'Notes')    + '</th>' +
            '<th>' + t('ledger.headers.created_at', 'Date')     + '</th>' +
            '</tr></thead><tbody>' +
            state.ledgerList.map(function (entry) {
                return '<tr>' +
                    '<td>' + esc(entry.transaction_type || '-') + '</td>' +
                    '<td><strong>' + formatAmount(entry.amount, entry.currency_code) + '</strong></td>' +
                    '<td>' + esc(entry.currency_code || 'USD') + '</td>' +
                    '<td>' + getEntityLabel(entry.entity_id, entry.entity_type) + '</td>' +
                    '<td>' + esc(entry.notes || '-') + '</td>' +
                    '<td>' + (entry.created_at ? new Date(entry.created_at).toLocaleDateString() : '-') + '</td>' +
                    '</tr>';
            }).join('') +
            '</tbody></table></div>';
    }

    // ─── Save ─────────────────────────────────────────────────
    async function saveEscrow(e) {
        e.preventDefault();
        const formData = new FormData(el.form);
        const id = formData.get('id');

        const data = {
            tenant_id:          state.tenantId,
            status:             formData.get('status') || 'pending',
            order_id:           formData.get('order_id') ? parseInt(formData.get('order_id'), 10) : null,
            amount:             formData.get('amount') ? parseFloat(formData.get('amount')) : null,
            escrow_fee:         formData.get('escrow_fee') ? parseFloat(formData.get('escrow_fee')) : 0,
            currency_code:      formData.get('currency_code') || 'USD',
            auto_release_days:  formData.get('auto_release_days') ? parseInt(formData.get('auto_release_days'), 10) : 7,
            buyer_entity_id:    formData.get('buyer_entity_id') ? parseInt(formData.get('buyer_entity_id'), 10) : null,
            buyer_entity_type:  formData.get('buyer_entity_type') || null,
            seller_entity_id:   formData.get('seller_entity_id') ? parseInt(formData.get('seller_entity_id'), 10) : null,
            seller_entity_type: formData.get('seller_entity_type') || null,
            notes:              formData.get('notes') || null
        };
        if (id) data.id = parseInt(id, 10);

        // Basic validation
        if (!data.amount) {
            showNotification(t('form.fields.amount.required', 'Amount is required'), 'error');
            return;
        }

        try {
            const method = id ? 'PUT' : 'POST';
            const res = await apiCall(API.escrow, {
                method: method,
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });
            if (res.success) {
                showNotification(
                    id ? t('messages.updated', 'Escrow updated successfully')
                       : t('messages.created', 'Escrow created successfully'),
                    'success'
                );
                hideForm();
                loadEscrow(state.page);
            } else {
                throw new Error(res.message || t('messages.error.save_failed', 'Save failed'));
            }
        } catch (err) {
            showNotification(err.message, 'error');
        }
    }

    // ─── Delete ───────────────────────────────────────────────
    async function deleteEscrow(id) {
        if (!confirm(t('messages.confirm_delete', 'Are you sure you want to delete this escrow transaction?'))) return;
        try {
            const res = await apiCall(API.escrow, {
                method: 'DELETE',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: id, tenant_id: state.tenantId })
            });
            if (res.success) {
                showNotification(t('messages.deleted', 'Escrow deleted successfully'), 'success');
                hideForm();
                loadEscrow(state.page);
            }
        } catch (err) {
            showNotification(err.message, 'error');
        }
    }

    // ─── UI helpers ───────────────────────────────────────────
    function showLoading() {
        if (el.loading)   el.loading.style.display   = 'flex';
        if (el.container) el.container.style.display = 'none';
        if (el.empty)     el.empty.style.display     = 'none';
    }
    function showTable() {
        if (el.loading)   el.loading.style.display   = 'none';
        if (el.container) el.container.style.display = 'block';
        if (el.empty)     el.empty.style.display     = 'none';
    }
    function showEmpty() {
        if (el.loading)   el.loading.style.display   = 'none';
        if (el.container) el.container.style.display = 'none';
        if (el.empty)     el.empty.style.display     = 'flex';
    }
    function showError(msg) {
        if (el.loading)   el.loading.style.display   = 'none';
        if (el.container) el.container.style.display = 'none';
        if (el.empty)     el.empty.style.display     = 'none';
        console.error('[Escrow]', msg);
    }
    function showNotification(msg, type) {
        if (window._admin && typeof window._admin.notify === 'function') {
            window._admin.notify(msg, type);
        } else {
            alert(msg);
        }
    }

    // ─── Pagination ───────────────────────────────────────────
    function updatePagination(total) {
        if (!el.pagination) return;
        const pages = Math.ceil(total / state.perPage);
        let html = '';
        for (let i = 1; i <= pages; i++) {
            html += '<button class="pagination-btn ' + (i === state.page ? 'active' : '') +
                    '" onclick="Escrow.load(' + i + ')">' + i + '</button>';
        }
        el.pagination.innerHTML = html;
        if (el.paginationInfo) {
            const start = ((state.page - 1) * state.perPage) + 1;
            const end   = Math.min(state.page * state.perPage, total);
            el.paginationInfo.textContent = start + '-' + end + ' / ' + total;
        }
    }

    // ─── Init ─────────────────────────────────────────────────
    async function init() {
        el = {
            container:        document.getElementById('esc-tableContainer'),
            loading:          document.getElementById('esc-tableLoading'),
            empty:            document.getElementById('esc-emptyState'),
            tbody:            document.getElementById('esc-tableBody'),
            pagination:       document.getElementById('esc-pagination'),
            paginationInfo:   document.getElementById('esc-paginationInfo'),
            formContainer:    document.getElementById('esc-formContainer'),
            form:             document.getElementById('esc-form'),
            formTitle:        document.getElementById('esc-formTitle'),
            formId:           document.getElementById('esc-formId'),
            escrowNumber:     document.getElementById('esc-escrowNumber'),
            status:           document.getElementById('esc-status'),
            orderId:          document.getElementById('esc-orderId'),
            amount:           document.getElementById('esc-amount'),
            escrowFee:        document.getElementById('esc-escrowFee'),
            currencyCode:     document.getElementById('esc-currencyCode'),
            autoRelease:      document.getElementById('esc-autoReleaseDays'),
            buyerEntityType:  document.getElementById('esc-buyerEntityType'),
            buyerEntityId:    document.getElementById('esc-buyerEntityId'),
            sellerEntityType: document.getElementById('esc-sellerEntityType'),
            sellerEntityId:   document.getElementById('esc-sellerEntityId'),
            notes:            document.getElementById('esc-notes'),
            btnDelete:        document.getElementById('esc-btnDelete'),
            historyList:      document.getElementById('esc-historyList'),
            disputesList:     document.getElementById('esc-disputesList'),
            ledgerList:       document.getElementById('esc-ledgerList')
        };

        // Load all reference data (currencies, entity types, orders)
        await loadDropdownData();

        // Bind entity type change → reload entity list
        if (el.buyerEntityType) {
            el.buyerEntityType.addEventListener('change', function () {
                loadEntitiesByType(this.value, el.buyerEntityId);
            });
        }
        if (el.sellerEntityType) {
            el.sellerEntityType.addEventListener('change', function () {
                loadEntitiesByType(this.value, el.sellerEntityId);
            });
        }

        // Bind Add / Close / Cancel
        const btnAdd = document.getElementById('esc-btnAdd');
        if (btnAdd) btnAdd.addEventListener('click', function () { showForm(); });

        const btnClose = document.getElementById('esc-btnCloseForm');
        if (btnClose) btnClose.addEventListener('click', hideForm);

        const btnCancel = document.getElementById('esc-btnCancelForm');
        if (btnCancel) btnCancel.addEventListener('click', hideForm);

        if (el.form) el.form.addEventListener('submit', saveEscrow);

        if (el.btnDelete) {
            el.btnDelete.addEventListener('click', function () {
                const id = el.formId && el.formId.value ? parseInt(el.formId.value, 10) : null;
                if (id) deleteEscrow(id);
            });
        }

        // Filter events
        const btnApply = document.getElementById('esc-btnApplyFilters');
        if (btnApply) {
            btnApply.addEventListener('click', function () {
                state.filters = {
                    search:        (document.getElementById('esc-searchInput') || {}).value || '',
                    status:        (document.getElementById('esc-statusFilter') || {}).value || '',
                    currency_code: (document.getElementById('esc-currencyFilter') || {}).value || ''
                };
                loadEscrow(1);
            });
        }

        const btnReset = document.getElementById('esc-btnResetFilters');
        if (btnReset) {
            btnReset.addEventListener('click', function () {
                state.filters = {};
                ['esc-searchInput', 'esc-statusFilter', 'esc-currencyFilter'].forEach(function (id) {
                    const el2 = document.getElementById(id);
                    if (el2) el2.value = '';
                });
                loadEscrow(1);
            });
        }

        // Tab switching
        if (el.formContainer) {
            el.formContainer.querySelectorAll('.tab-btn').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    el.formContainer.querySelectorAll('.tab-btn').forEach(function (b) {
                        b.classList.remove('active');
                    });
                    el.formContainer.querySelectorAll('.tab-content').forEach(function (c) {
                        c.style.display = 'none';
                    });
                    btn.classList.add('active');
                    const pane = document.getElementById('esc-tab-' + btn.dataset.tab);
                    if (pane) pane.style.display = 'block';
                });
            });
        }

        // Load initial data
        await loadEscrow(1);
    }

    // ─── Public API ───────────────────────────────────────────
    window.Escrow = {
        init:   init,
        load:   loadEscrow,
        edit:   async function (id) {
            try {
                const res = await apiCall(API.escrow + '?id=' + id + '&tenant_id=' + state.tenantId);
                if (res.success) await showForm(res.data);
            } catch (e) { console.error('[Escrow] edit failed:', e); }
        },
        remove: deleteEscrow
    };

    // Initialization is driven by the fragment's inline script.
    // Do NOT self-invoke init() here.
})();