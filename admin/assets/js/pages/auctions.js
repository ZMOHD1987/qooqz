(function(){
    'use strict';

    /**
     * /admin/assets/js/pages/auctions.js
     * Auctions Management Module - Production Ready
     * Based on Products pattern adapted for Auctions
     */

    // ════════════════════════════════════════════════════════════
    // CONFIGURATION & STATE
    // ════════════════════════════════════════════════════════════
    const CONFIG = window.AUCTIONS_CONFIG || {};
    const AF     = window.AdminFramework  || {};
    const PERMS  = window.PAGE_PERMISSIONS || {};

    const API = {
        auctions:     CONFIG.apiUrl          || '/api/auctions',
        bids:         CONFIG.bidsApi         || '/api/auction_bids',
        translations: CONFIG.translationsApi || '/api/auction_translations',
        products:     CONFIG.productsApi     || '/api/products',
        currencies:   CONFIG.currenciesApi   || '/api/currencies',
        languages:    CONFIG.languagesApi    || '/api/languages',
        entities:     CONFIG.entitiesApi     || '/api/entities'
    };

    const state = {
        page:           1,
        perPage:        CONFIG.itemsPerPage || 25,
        total:          0,
        auctions:       [],
        languages:      [],
        currencies:     [],
        currencyMap:    {}, // keyed by code for fast lookup
        products:       [],
        entities:       [],
        filters:        {},
        currentAuction: null,
        permissions:    PERMS,
        language:       window.USER_LANGUAGE  || CONFIG.lang || 'en',
        direction:      window.USER_DIRECTION || 'ltr',
        csrfToken:      window.CSRF_TOKEN     || CONFIG.csrfToken || '',
        tenantId:       window.APP_CONFIG?.TENANT_ID || 1
    };

    let el = {};           // DOM element cache
    let translations = {}; // i18n map

    // ════════════════════════════════════════════════════════════
    // TRANSLATIONS
    // ════════════════════════════════════════════════════════════
    function t(key, fallback = '') {
        const parts = key.split('.');
        let val = translations;
        for (const k of parts) {
            if (val && typeof val === 'object' && k in val) { val = val[k]; }
            else { return fallback || key; }
        }
        return (val !== undefined && val !== null) ? String(val) : (fallback || key);
    }

    async function loadTranslations(lang) {
        try {
            const url = `/languages/Auctions/${encodeURIComponent(lang || state.language)}.json`;
            const res = await fetch(url, { credentials: 'same-origin' });
            if (!res.ok) throw new Error('Translation fetch failed: ' + res.status);
            const raw = await res.json();
            translations = raw.strings || raw;
            applyTranslations();
        } catch (err) {
            console.warn('[Auctions] Translation load failed:', err);
            translations = {};
        }
    }

    function applyTranslations() {
        const container = document.getElementById('auctionsPageContainer');
        if (!container) return;
        container.querySelectorAll('[data-i18n]').forEach(elem => {
            const key = elem.getAttribute('data-i18n');
            const txt = t(key);
            if (txt !== key) {
                if (elem.tagName === 'INPUT' && elem.type !== 'submit' && elem.type !== 'button') {
                    if (elem.hasAttribute('placeholder')) elem.placeholder = txt;
                } else {
                    elem.textContent = txt;
                }
            }
        });
        container.querySelectorAll('[data-i18n-placeholder]').forEach(elem => {
            const key = elem.getAttribute('data-i18n-placeholder');
            const txt = t(key);
            if (txt !== key) elem.placeholder = txt;
        });
    }

    // ════════════════════════════════════════════════════════════
    // API HELPERS
    // ════════════════════════════════════════════════════════════
    async function apiCall(url, options = {}) {
        const defaults = {
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        };
        if (options.method && options.method !== 'GET') {
            defaults.headers['X-CSRF-Token'] = state.csrfToken;
        }
        const config = { ...defaults, ...options };
        if (options.headers) config.headers = { ...defaults.headers, ...options.headers };

        try {
            const res = await fetch(url, config);
            const ct  = res.headers.get('content-type') || '';
            if (ct.includes('application/json')) {
                const data = await res.json();
                if (!res.ok) throw new Error(data.error || data.message || `HTTP ${res.status}`);
                return data;
            }
            const text = await res.text();
            if (!res.ok) throw new Error(text || `HTTP ${res.status}`);
            try { return JSON.parse(text); } catch { return { success: true, data: text }; }
        } catch (err) {
            console.error('[Auctions] API error:', url, err);
            throw err;
        }
    }

    // ════════════════════════════════════════════════════════════
    // DATA LOADING
    // ════════════════════════════════════════════════════════════
    async function loadAuctions(page = 1) {
        try {
            showLoading();
            state.page = page;

            const params = new URLSearchParams({
                page:      page,
                limit:     state.perPage,
                tenant_id: state.tenantId,
                lang:      state.language,
                format:    'json'
            });

            Object.entries(state.filters).forEach(([k, v]) => {
                if (v !== undefined && v !== null && v !== '') params.set(k, v);
            });

            const result = await apiCall(`${API.auctions}?${params}`);

            if (result.success && result.data) {
                const items = result.data.items || result.data;
                const meta  = result.data.meta  || result.meta || {};
                state.auctions = Array.isArray(items) ? items : [];
                state.total    = meta.total || state.auctions.length;
                renderTable(state.auctions);
                updatePagination(meta.total !== undefined ? meta : { page, per_page: state.perPage, total: state.total });
                updateResultsCount(state.total);
                showTable();
            } else {
                throw new Error(result.error || result.message || 'Invalid response');
            }
        } catch (err) {
            console.error('[Auctions] Load failed:', err);
            showError(err.message || 'Failed to load auctions');
        }
    }

    async function loadDropdownData() {
        // Currencies – uses code, name, symbol, symbol_position, decimal_places
        try {
            const res = await apiCall(`${API.currencies}?format=json`);
            if (res.success) {
                const data = Array.isArray(res.data) ? res.data : (res.data?.items || res.data?.data || []);
                applyCurrencies(data);
            }
        } catch (e) {
            console.warn('[Auctions] Failed to load currencies:', e);
            // Minimal fallback keeps the page usable without the API
            applyCurrencies([
                { id: 1, code: 'SAR', name: 'Saudi Riyal',  symbol: '﷼',  symbol_position: 'after',  decimal_places: 2 },
                { id: 2, code: 'USD', name: 'US Dollar',     symbol: '$',   symbol_position: 'before', decimal_places: 2 },
                { id: 3, code: 'EUR', name: 'Euro',           symbol: '€',   symbol_position: 'before', decimal_places: 2 },
                { id: 4, code: 'AED', name: 'UAE Dirham',    symbol: 'د.إ', symbol_position: 'after',  decimal_places: 2 }
            ]);
        }

        // Languages
        try {
            const res = await apiCall(`${API.languages}?format=json`);
            if (res.success) {
                const data = res.data?.items || res.data || [];
                state.languages = Array.isArray(data) ? data : [];
                populateDropdown(el.auctionLangSelect, state.languages, 'code', 'name', t('translations.choose', 'Choose language'));
            }
        } catch (e) {
            console.warn('[Auctions] Failed to load languages:', e);
        }

        // Products (for product selector)
        try {
            const res = await apiCall(`${API.products}?format=json&tenant_id=${state.tenantId}&lang=${state.language}&limit=500`);
            if (res.success) {
                const data = res.data?.items || (Array.isArray(res.data) ? res.data : []);
                state.products = data;
                if (el.auctionProduct) {
                    populateDropdown(el.auctionProduct, data, 'id', 'name', t('form.fields.product_id.select', 'Select product (optional)'));
                }
            }
        } catch (e) {
            console.warn('[Auctions] Failed to load products:', e);
        }

        // Entities (for entity selector)
        try {
            const res = await apiCall(`${API.entities}?format=json&tenant_id=${state.tenantId}&lang=${state.language}&limit=500`);
            if (res.success) {
                const data = res.data?.items || (Array.isArray(res.data) ? res.data : []);
                state.entities = Array.isArray(data) ? data : [];
                if (el.auctionEntity) {
                    populateDropdown(el.auctionEntity, state.entities, 'id', 'store_name', t('form.fields.entity_id.select', 'Select entity'));
                }
            }
        } catch (e) {
            console.warn('[Auctions] Failed to load entities:', e);
        }
    }

    /** Store currencies in state and refresh the currency dropdown. */
    function applyCurrencies(data) {
        state.currencies = data;
        state.currencyMap = Object.fromEntries(data.map(c => [c.code, c]));
        populateCurrencyDropdown(el.auctionCurrency, data, t('form.fields.currency_id.select', 'Select currency'));
    }

    function populateDropdown(selectEl, data, valueKey, textKey, placeholder = '') {
        if (!selectEl) return;
        selectEl.innerHTML = '';
        if (placeholder) {
            const opt = document.createElement('option');
            opt.value = '';
            opt.textContent = placeholder;
            selectEl.appendChild(opt);
        }
        data.forEach(item => {
            const opt = document.createElement('option');
            opt.value = item[valueKey];
            opt.textContent = item[textKey];
            selectEl.appendChild(opt);
        });
    }

    /**
     * Populate the currency <select> showing code + name + symbol,
     * e.g. "SAR – Saudi Riyal (﷼)" so admins can identify the currency at a glance.
     */
    function populateCurrencyDropdown(selectEl, currencies, placeholder = '') {
        if (!selectEl) return;
        selectEl.innerHTML = '';
        if (placeholder) {
            const opt = document.createElement('option');
            opt.value = '';
            opt.textContent = placeholder;
            selectEl.appendChild(opt);
        }
        currencies.forEach(cur => {
            const opt = document.createElement('option');
            opt.value = cur.id;
            const symPart = cur.symbol ? ` (${cur.symbol})` : '';
            opt.textContent = `${cur.code} – ${cur.name}${symPart}`;
            selectEl.appendChild(opt);
        });
    }

    // ════════════════════════════════════════════════════════════
    // RENDERING
    // ════════════════════════════════════════════════════════════
    function renderTable(items) {
        if (!el.tbody) return;
        if (!items || !items.length) { showEmpty(); return; }

        const isSuperAdmin = state.permissions.isSuperAdmin;

        el.tbody.innerHTML = items.map(a => {
            const statusBadge    = `<span class="badge badge-${esc(a.status)}">${esc(a.status)}</span>`;
            const typeBadge      = `<span class="badge badge-${esc(a.auction_type)}">${esc(a.auction_type)}</span>`;
            const currentPrice   = a.current_price  ? formatPrice(a.current_price, a.currency_code) : '—';
            const totalBids      = a.total_bids      || 0;
            const endDate        = a.end_date ? new Date(a.end_date).toLocaleString() : '—';
            const title          = a.translated_title || a.title || `Auction #${a.id}`;
            const featured       = a.is_featured == 1 ? ' <i class="fas fa-star" style="color:#f59e0b;font-size:0.75rem;" title="Featured"></i>' : '';
            const entityName     = a.entity_name  || `#${a.entity_id || ''}`;
            const tenantDisplay  = a.tenant_name  || `#${a.tenant_id || ''}`;

            const canEdit   = state.permissions.canEdit   || state.permissions.canEditAll   ||
                              (state.permissions.canEditOwn && a.created_by == window.APP_CONFIG?.USER_ID);
            const canDelete = state.permissions.canDelete || state.permissions.canDeleteAll ||
                              (state.permissions.canDeleteOwn && a.created_by == window.APP_CONFIG?.USER_ID);

            return `
                <tr data-id="${esc(a.id)}">
                    <td>${esc(a.id)}</td>
                    ${isSuperAdmin ? `<td>${esc(tenantDisplay)}</td>` : ''}
                    <td>${esc(entityName)}</td>
                    <td>
                        <strong>${esc(title)}${featured}</strong>
                        <br><small style="color:var(--text-secondary,#94a3b8);">${esc(a.slug||'')}</small>
                    </td>
                    <td>${typeBadge}</td>
                    <td>${statusBadge}</td>
                    <td class="price-current" style="font-weight:700;">${currentPrice}</td>
                    <td>${esc(totalBids)}</td>
                    <td><small>${esc(endDate)}</small></td>
                    <td>
                        <div class="table-actions">
                            ${canEdit ? `<button class="btn btn-sm btn-secondary" onclick="Auctions.edit(${a.id})" title="Edit">
                                <i class="fas fa-edit"></i>
                            </button>` : ''}
                            ${canDelete ? `<button class="btn btn-sm btn-danger" onclick="Auctions.remove(${a.id})" title="Delete">
                                <i class="fas fa-trash"></i>
                            </button>` : ''}
                        </div>
                    </td>
                </tr>
            `;
        }).join('');
    }

    // ════════════════════════════════════════════════════════════
    // TABS
    // ════════════════════════════════════════════════════════════
    function initTabs() {
        document.querySelectorAll('#auctionForm .tab-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const target = btn.dataset.tab;
                document.querySelectorAll('#auctionForm .tab-btn').forEach(b => b.classList.remove('active'));
                document.querySelectorAll('#auctionForm .tab-content').forEach(c => c.style.display = 'none');
                btn.classList.add('active');
                const content = document.getElementById('tab-' + target);
                if (content) content.style.display = 'block';

                // Lazy-load bids when switching to bids tab on edit
                if (target === 'bids' && state.currentAuction?.id) {
                    loadBids(state.currentAuction.id);
                }
            });
        });
    }

    // ════════════════════════════════════════════════════════════
    // FORM MANAGEMENT
    // ════════════════════════════════════════════════════════════
    function showForm(auction = null) {
        if (!el.formContainer || !el.form) return;

        state.currentAuction = auction;
        el.form.reset();

        // Reset tabs – show General
        document.querySelectorAll('#auctionForm .tab-btn').forEach(b => b.classList.remove('active'));
        document.querySelectorAll('#auctionForm .tab-content').forEach(c => c.style.display = 'none');
        const genBtn  = document.querySelector('#auctionForm .tab-btn[data-tab="general"]');
        const genContent = document.getElementById('tab-general');
        if (genBtn)     genBtn.classList.add('active');
        if (genContent) genContent.style.display = 'block';

        // Clear bids / translations
        if (el.bidsTableBody)     el.bidsTableBody.innerHTML = '';
        if (el.auctionTranslations) el.auctionTranslations.innerHTML = '';
        if (el.bidsLoading)       el.bidsLoading.style.display   = 'none';
        if (el.bidsEmpty)         el.bidsEmpty.style.display     = 'none';
        if (el.bidsTableWrapper)  el.bidsTableWrapper.style.display = 'none';

        if (auction) {
            if (el.auctionFormTitle) el.auctionFormTitle.textContent = t('form.edit_title', 'Edit Auction');
            if (el.auctionFormId)  el.auctionFormId.value  = auction.id || '';
            // General
            if (el.auctionTitle)   el.auctionTitle.value   = auction.title || '';
            if (el.auctionSlug)    el.auctionSlug.value    = auction.slug  || '';
            if (el.auctionProduct) el.auctionProduct.value = auction.product_id || '';
            if (el.auctionEntity)  el.auctionEntity.value  = auction.entity_id  || '';
            if (el.auctionType)    el.auctionType.value    = auction.auction_type  || 'normal';
            if (el.auctionStatus)  el.auctionStatus.value  = auction.status        || 'draft';
            if (el.auctionCondition) el.auctionCondition.value = auction.condition_type || 'new';
            if (el.auctionQuantity)  el.auctionQuantity.value  = auction.quantity        || 1;
            if (el.auctionIsFeatured) el.auctionIsFeatured.value = auction.is_featured   || '0';
            if (el.auctionAutoBid)    el.auctionAutoBid.value    = auction.auto_bid_enabled ?? '1';
            if (el.auctionNotes)   el.auctionNotes.value   = auction.notes || '';
            // Pricing
            if (el.auctionStartingPrice) el.auctionStartingPrice.value  = auction.starting_price  || '';
            if (el.auctionReservePrice)  el.auctionReservePrice.value   = auction.reserve_price   || '';
            if (el.auctionBuyNowPrice)   el.auctionBuyNowPrice.value    = auction.buy_now_price   || '';
            if (el.auctionBidIncrement)  el.auctionBidIncrement.value   = auction.bid_increment   || '5.00';
            if (el.auctionCurrency)      el.auctionCurrency.value       = auction.currency_id    || '';
            if (el.auctionShipping)      el.auctionShipping.value       = auction.shipping_cost   || '0.00';
            if (el.auctionPaymentDeadline) el.auctionPaymentDeadline.value = auction.payment_deadline_hours || '48';
            // Schedule
            if (el.auctionStartDate && auction.start_date) {
                el.auctionStartDate.value = toDatetimeLocal(auction.start_date);
            }
            if (el.auctionEndDate && auction.end_date) {
                el.auctionEndDate.value = toDatetimeLocal(auction.end_date);
            }
            if (el.auctionAutoExtend)       el.auctionAutoExtend.value       = auction.auto_extend        ?? '1';
            if (el.auctionExtendMinutes)    el.auctionExtendMinutes.value    = auction.extend_minutes      || '5';
            if (el.auctionMinExtendBidTime) el.auctionMinExtendBidTime.value = auction.min_extend_bid_time || '5';
            // Stats
            updateBidStats(auction);
            // Load translations
            if (auction.id) loadAuctionTranslations(auction.id);

            if (el.btnDeleteAuction) el.btnDeleteAuction.style.display = state.permissions.canDelete ? 'inline-flex' : 'none';
        } else {
            if (el.auctionFormTitle) el.auctionFormTitle.textContent = t('form.add_title', 'Add Auction');
            if (el.auctionFormId)    el.auctionFormId.value           = '';
            if (el.auctionTenantId)  el.auctionTenantId.value         = state.tenantId;
            if (el.btnDeleteAuction) el.btnDeleteAuction.style.display = 'none';
        }

        el.formContainer.style.display = 'block';
        el.formContainer.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    function hideForm() {
        if (el.formContainer) el.formContainer.style.display = 'none';
        state.currentAuction = null;
        if (el.form) el.form.reset();
    }

    function toDatetimeLocal(dateStr) {
        if (!dateStr) return '';
        try {
            const d = new Date(dateStr);
            const pad = n => String(n).padStart(2, '0');
            return `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
        } catch { return ''; }
    }

    // ════════════════════════════════════════════════════════════
    // FORM SUBMISSION
    // ════════════════════════════════════════════════════════════
    async function saveAuction(e) {
        e.preventDefault();

        if (!validateForm()) {
            showNotification(t('messages.validation_failed', 'Please fill all required fields'), 'error');
            return;
        }

        const btn = el.btnSubmit;
        if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...'; }

        try {
            const formData = new FormData(el.form);
            const auctionId = el.auctionFormId?.value;
            const isEdit    = !!auctionId;

            const data = {
                tenant_id:             state.tenantId,
                entity_id:             parseInt(formData.get('entity_id'), 10) || null,
                title:                 formData.get('title') || '',
                slug:                  formData.get('slug')  || generateSlug(formData.get('title') || ''),
                product_id:            formData.get('product_id')   || null,
                auction_type:          formData.get('auction_type') || 'normal',
                status:                formData.get('status')       || 'draft',
                starting_price:        formData.get('starting_price') || '0',
                reserve_price:         formData.get('reserve_price')  || null,
                current_price:         formData.get('starting_price') || '0',
                buy_now_price:         formData.get('buy_now_price')  || null,
                bid_increment:         formData.get('bid_increment')  || '5.00',
                currency_id:           (v => v > 0 ? v : null)(parseInt(formData.get('currency_id'), 10)),
                auto_bid_enabled:      formData.get('auto_bid_enabled') ?? '1',
                start_date:            formData.get('start_date')     || '',
                end_date:              formData.get('end_date')       || '',
                auto_extend:           formData.get('auto_extend')    ?? '1',
                extend_minutes:        formData.get('extend_minutes') || '5',
                min_extend_bid_time:   formData.get('min_extend_bid_time') || '5',
                is_featured:           formData.get('is_featured')    || '0',
                condition_type:        formData.get('condition_type') || 'new',
                quantity:              formData.get('quantity')       || '1',
                shipping_cost:         formData.get('shipping_cost')  || '0.00',
                payment_deadline_hours:formData.get('payment_deadline_hours') || '48',
                notes:                 formData.get('notes') || null,
                created_by:            window.APP_CONFIG?.USER_ID || null
            };

            if (isEdit) data.id = auctionId;

            const result = await apiCall(API.auctions, {
                method:  isEdit ? 'PUT' : 'POST',
                headers: { 'Content-Type': 'application/json' },
                body:    JSON.stringify(data)
            });

            if (result.success) {
                const savedId = isEdit ? auctionId : (result.data?.id || result.data?.items?.[0]?.id);

                // Save translations if any panels exist
                const transData = collectTranslations();
                if (Object.keys(transData).length > 0) {
                    await saveAuctionTranslations(savedId, transData);
                }

                showNotification(
                    isEdit ? t('messages.updated', 'Auction updated successfully') : t('messages.created', 'Auction created successfully'),
                    'success'
                );
                hideForm();
                loadAuctions(state.page);
            } else {
                throw new Error(result.error || result.message || 'Save failed');
            }
        } catch (err) {
            console.error('[Auctions] Save failed:', err);
            showNotification(err.message || 'Failed to save auction', 'error');
        } finally {
            if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-save"></i> Save'; }
        }
    }

    function validateForm() {
        let valid = true;
        const required = [el.auctionTitle, el.auctionStartingPrice, el.auctionCurrency];
        required.forEach(field => {
            if (!field || !field.value.trim()) {
                valid = false;
                if (field) {
                    field.classList.add('is-invalid');
                    field.addEventListener('input', () => field.classList.remove('is-invalid'), { once: true });
                }
            }
        });
        return valid;
    }

    function generateSlug(name) {
        const suffix = '-' + Date.now().toString(36);
        return name.toLowerCase()
            .replace(/[^\w\s-]/g, '')
            .replace(/[\s_]+/g, '-')
            .replace(/^-+|-+$/g, '')
            .substring(0, 255 - suffix.length) + suffix;
    }

    // ════════════════════════════════════════════════════════════
    // BIDS
    // ════════════════════════════════════════════════════════════
    async function loadBids(auctionId) {
        if (!el.bidsLoading || !el.bidsEmpty || !el.bidsTableWrapper || !el.bidsTableBody) return;

        el.bidsLoading.style.display    = 'flex';
        el.bidsEmpty.style.display      = 'none';
        el.bidsTableWrapper.style.display = 'none';

        try {
            const res = await apiCall(`${API.bids}?auction_id=${auctionId}&limit=100&order_by=id&order_dir=DESC&format=json`);
            if (res.success) {
                const items = res.data?.items || (Array.isArray(res.data) ? res.data : []);

                el.bidsLoading.style.display = 'none';

                if (!items.length) {
                    el.bidsEmpty.style.display = 'flex';
                    return;
                }

                el.bidsTableWrapper.style.display = 'block';
                el.bidsTableBody.innerHTML = items.map(bid => {
                    const isWinning = bid.is_winning == 1;
                    const typeBadge = `<span class="badge badge-${esc(bid.bid_type || 'manual')}">${esc(bid.bid_type || 'manual')}</span>`;
                    const statusLabel = isWinning
                        ? '<span class="winner-badge"><i class="fas fa-trophy"></i> Winning</span>'
                        : (bid.is_auto_outbid == 1 ? '<span class="badge badge-ended">Outbid</span>' : '—');
                    const amount   = `<strong>${formatPrice(bid.bid_amount, state.currentAuction?.currency_code)}</strong>`;
                    const maxAuto  = bid.max_auto_bid ? ` / max: ${formatPrice(bid.max_auto_bid, state.currentAuction?.currency_code)}` : '';
                    const created  = bid.created_at ? new Date(bid.created_at).toLocaleString() : '—';
                    return `
                        <tr class="${isWinning ? 'winner-row' : ''}">
                            <td>${esc(bid.id)}</td>
                            <td>${esc(bid.user_id)}</td>
                            <td>${amount}${esc(maxAuto)}</td>
                            <td>${typeBadge}</td>
                            <td>${statusLabel}</td>
                            <td><small>${esc(created)}</small></td>
                        </tr>
                    `;
                }).join('');
            }
        } catch (err) {
            console.warn('[Auctions] Bids load failed:', err);
            el.bidsLoading.style.display = 'none';
            el.bidsEmpty.style.display   = 'flex';
        }
    }

    function updateBidStats(auction) {
        if (el.statTotalBids)     el.statTotalBids.textContent    = auction.total_bids    || 0;
        if (el.statTotalBidders)  el.statTotalBidders.textContent  = auction.total_bidders || 0;
        if (el.statCurrentPrice)  el.statCurrentPrice.textContent  = auction.current_price  ? formatPrice(auction.current_price,  auction.currency_code) : '—';
        if (el.statWinningAmount) el.statWinningAmount.textContent = auction.winning_amount ? formatPrice(auction.winning_amount, auction.currency_code) : '—';
    }

    // ════════════════════════════════════════════════════════════
    // TRANSLATIONS
    // ════════════════════════════════════════════════════════════
    function addTranslation() {
        if (!el.auctionLangSelect?.value) return;
        const langCode = el.auctionLangSelect.value;
        const langName = el.auctionLangSelect.options[el.auctionLangSelect.selectedIndex].textContent;

        if (document.querySelector(`#auctionTranslations [data-lang="${langCode}"]`)) {
            showNotification(t('messages.translation_exists', 'Translation already added'), 'warning');
            return;
        }

        const panel = createTranslationPanel(langCode, langName, {});
        if (el.auctionTranslations) el.auctionTranslations.appendChild(panel);
        el.auctionLangSelect.value = '';
    }

    function createTranslationPanel(langCode, langName, data) {
        const div = document.createElement('div');
        div.className = 'translation-panel';
        div.dataset.lang = langCode;
        div.innerHTML = `
            <div class="translation-panel-header">
                <h5><i class="fas fa-language"></i> ${esc(langName)} (${esc(langCode)})</h5>
                <button type="button" class="btn btn-sm btn-danger" onclick="this.closest('.translation-panel').remove()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="translation-panel-body">
                <div class="form-group">
                    <label>Title</label>
                    <input type="text" class="form-control trans-title" data-lang="${esc(langCode)}" value="${esc(data.title||'')}">
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea class="form-control trans-description" rows="4" data-lang="${esc(langCode)}">${esc(data.description||'')}</textarea>
                </div>
                <div class="form-group">
                    <label>Terms &amp; Conditions</label>
                    <textarea class="form-control trans-terms" rows="3" data-lang="${esc(langCode)}">${esc(data.terms_conditions||'')}</textarea>
                </div>
            </div>
        `;
        return div;
    }

    function collectTranslations() {
        const result = {};
        document.querySelectorAll('#auctionTranslations .translation-panel').forEach(panel => {
            const lang  = panel.dataset.lang;
            const title = panel.querySelector('.trans-title')?.value       || '';
            const desc  = panel.querySelector('.trans-description')?.value  || '';
            const terms = panel.querySelector('.trans-terms')?.value        || '';
            if (title || desc || terms) {
                result[lang] = { title, description: desc, terms_conditions: terms };
            }
        });
        return result;
    }

    async function saveAuctionTranslations(auctionId, translations) {
        for (const [langCode, data] of Object.entries(translations)) {
            try {
                await apiCall(API.translations, {
                    method:  'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        auction_id:       parseInt(auctionId),
                        language_code:    langCode,
                        title:            data.title            || '',
                        description:      data.description      || null,
                        terms_conditions: data.terms_conditions || null
                    })
                });
            } catch (err) {
                console.warn('[Auctions] Translation save failed for', langCode, err);
            }
        }
    }

    async function loadAuctionTranslations(auctionId) {
        try {
            const res = await apiCall(`${API.translations}?auction_id=${auctionId}&format=json`);
            if (res.success) {
                const items = Array.isArray(res.data) ? res.data : (res.data?.items || []);
                if (el.auctionTranslations) el.auctionTranslations.innerHTML = '';
                items.forEach(trans => {
                    const langName = state.languages.find(l => l.code === trans.language_code)?.name || trans.language_code;
                    const panel = createTranslationPanel(trans.language_code, langName, {
                        title:            trans.title            || '',
                        description:      trans.description      || '',
                        terms_conditions: trans.terms_conditions || ''
                    });
                    if (el.auctionTranslations) el.auctionTranslations.appendChild(panel);
                });
            }
        } catch (err) {
            console.warn('[Auctions] Load translations failed:', err);
        }
    }

    // ════════════════════════════════════════════════════════════
    // DELETE
    // ════════════════════════════════════════════════════════════
    async function deleteAuction(id) {
        if (!confirm(t('messages.confirm_delete', 'Are you sure you want to delete this auction?'))) return;
        try {
            const result = await apiCall(API.auctions, {
                method:  'DELETE',
                headers: { 'Content-Type': 'application/json' },
                body:    JSON.stringify({ id })
            });
            if (result.success) {
                showNotification(t('messages.deleted', 'Auction deleted successfully'), 'success');
                hideForm();
                loadAuctions(state.page);
            } else {
                throw new Error(result.error || 'Delete failed');
            }
        } catch (err) {
            console.error('[Auctions] Delete failed:', err);
            showNotification(err.message || 'Failed to delete auction', 'error');
        }
    }

    // ════════════════════════════════════════════════════════════
    // FILTERS & PAGINATION
    // ════════════════════════════════════════════════════════════
    function applyFilters() {
        state.filters = {};
        if (el.auctionSearch?.value)        state.filters.search       = el.auctionSearch.value;
        if (el.auctionTenantFilter?.value)  state.filters.tenant_id    = el.auctionTenantFilter.value;
        if (el.auctionStatusFilter?.value)  state.filters.status       = el.auctionStatusFilter.value;
        if (el.auctionTypeFilter?.value)    state.filters.auction_type = el.auctionTypeFilter.value;
        if (el.auctionFeaturedFilter?.value !== '') state.filters.is_featured = el.auctionFeaturedFilter.value;
        loadAuctions(1);
    }

    function resetFilters() {
        state.filters = {};
        if (el.auctionSearch)        el.auctionSearch.value        = '';
        if (el.auctionTenantFilter)  el.auctionTenantFilter.value  = state.tenantId;
        if (el.auctionStatusFilter)  el.auctionStatusFilter.value  = '';
        if (el.auctionTypeFilter)    el.auctionTypeFilter.value    = '';
        if (el.auctionFeaturedFilter) el.auctionFeaturedFilter.value = '';
        loadAuctions(1);
    }

    function updatePagination(meta) {
        if (!el.pagination || !el.paginationInfo) return;
        const { page = 1, per_page = 25, total = 0 } = meta;
        const totalPages = Math.ceil(total / per_page);
        const start = total > 0 ? (page - 1) * per_page + 1 : 0;
        const end   = Math.min(page * per_page, total);
        el.paginationInfo.textContent = `${start}-${end} of ${total}`;

        if (totalPages <= 1) { el.pagination.innerHTML = ''; return; }

        let html = `<button class="pagination-btn" ${page <= 1 ? 'disabled' : ''} onclick="Auctions.load(${page-1})"><i class="fas fa-chevron-left"></i></button>`;
        for (let i = 1; i <= totalPages; i++) {
            if (i === 1 || i === totalPages || (i >= page - 2 && i <= page + 2)) {
                html += `<button class="pagination-btn ${i === page ? 'active' : ''}" onclick="Auctions.load(${i})">${i}</button>`;
            } else if (i === page - 3 || i === page + 3) {
                html += `<span class="pagination-ellipsis">...</span>`;
            }
        }
        html += `<button class="pagination-btn" ${page >= totalPages ? 'disabled' : ''} onclick="Auctions.load(${page+1})"><i class="fas fa-chevron-right"></i></button>`;
        el.pagination.innerHTML = html;
    }

    function updateResultsCount(total) {
        if (el.resultsCount && el.resultsCountText) {
            el.resultsCountText.textContent = `${total} ${t('auctions.found', 'auctions found')}`;
            el.resultsCount.style.display   = total > 0 ? 'block' : 'none';
        }
    }

    // ════════════════════════════════════════════════════════════
    // UI STATE
    // ════════════════════════════════════════════════════════════
    function showLoading() {
        if (el.loading)   { el.loading.style.display   = 'flex'; }
        if (el.container) { el.container.style.display = 'none'; }
        if (el.empty)     { el.empty.style.display     = 'none'; }
        if (el.error)     { el.error.style.display     = 'none'; }
    }

    function showTable() {
        if (el.loading)   { el.loading.style.display   = 'none'; }
        if (el.container) { el.container.style.display = 'block'; }
        if (el.empty)     { el.empty.style.display     = 'none'; }
        if (el.error)     { el.error.style.display     = 'none'; }
    }

    function showEmpty() {
        if (el.loading)   { el.loading.style.display   = 'none'; }
        if (el.container) { el.container.style.display = 'none'; }
        if (el.error)     { el.error.style.display     = 'none'; }
        if (el.empty) {
            el.empty.innerHTML = `
                <div class="empty-icon">🔨</div>
                <h3>${t('table.empty.title', 'No Auctions Found')}</h3>
                <p>${t('table.empty.message', 'Start by adding your first auction')}</p>
                ${state.permissions.canCreate ? `<button class="btn btn-primary" onclick="Auctions.add()">
                    <i class="fas fa-plus"></i> ${t('table.empty.add_first', 'Add First Auction')}
                </button>` : ''}
            `;
            el.empty.style.display = 'flex';
        }
        if (el.tbody) el.tbody.innerHTML = '';
    }

    function showError(message) {
        if (el.loading)   { el.loading.style.display   = 'none'; }
        if (el.container) { el.container.style.display = 'none'; }
        if (el.empty)     { el.empty.style.display     = 'none'; }
        if (el.error) {
            if (el.errorMessage) el.errorMessage.textContent = message;
            el.error.style.display = 'flex';
        }
    }

    function showNotification(message, type = 'info') {
        if (AF.notify) { AF.notify(message, type); }
        else { alert(message); }
    }

    // ════════════════════════════════════════════════════════════
    // UTILITIES
    // ════════════════════════════════════════════════════════════
    function esc(text) {
        if (text === null || text === undefined) return '';
        const div = document.createElement('div');
        div.textContent = String(text);
        return div.innerHTML;
    }

    /**
     * Format a monetary amount using the currency's symbol, position, and
     * decimal_places as returned by the /api/currencies endpoint.
     * Falls back to plain "{amount} {code}" if the currency is not in the map.
     */
    function formatPrice(amount, currencyCode) {
        if (amount === null || amount === undefined || amount === '') return '—';
        const cur = state.currencyMap[currencyCode] || null;
        const decimals = cur ? (parseInt(cur.decimal_places, 10) || 2) : 2;
        const num = Number(amount).toFixed(decimals);
        if (!cur) return `${num} ${currencyCode || ''}`.trim();
        const sym = cur.symbol || currencyCode;
        // symbol_position 'before' → no space (e.g. "$100"), 'after' → space (e.g. "100 ﷼")
        return cur.symbol_position === 'after'
            ? `${num} ${sym}`
            : `${sym}${num}`;
    }

    // ════════════════════════════════════════════════════════════
    // INITIALIZATION
    // ════════════════════════════════════════════════════════════
    async function init() {
        console.log('[Auctions] Initializing...');
        const $id = id => document.getElementById(id);

        el = {
            // Page states
            container:    $id('auctionTableContainer'),
            loading:      $id('auctionTableLoading'),
            empty:        $id('auctionEmptyState'),
            error:        $id('auctionErrorState'),
            errorMessage: $id('auctionErrorMessage'),
            // Form
            formContainer:   $id('auctionFormContainer'),
            form:            $id('auctionForm'),
            auctionFormTitle:$id('auctionFormTitle'),
            auctionFormId:   $id('auctionFormId'),
            auctionTenantId: $id('auctionTenantId'),
            // General
            auctionTitle:     $id('auctionTitle'),
            auctionSlug:      $id('auctionSlug'),
            auctionProduct:   $id('auctionProduct'),
            auctionEntity:    $id('auctionEntity'),
            auctionType:      $id('auctionType'),
            auctionStatus:    $id('auctionStatus'),
            auctionCondition: $id('auctionCondition'),
            auctionQuantity:  $id('auctionQuantity'),
            auctionIsFeatured:$id('auctionIsFeatured'),
            auctionAutoBid:   $id('auctionAutoBid'),
            auctionNotes:     $id('auctionNotes'),
            // Pricing
            auctionStartingPrice:    $id('auctionStartingPrice'),
            auctionReservePrice:     $id('auctionReservePrice'),
            auctionBuyNowPrice:      $id('auctionBuyNowPrice'),
            auctionBidIncrement:     $id('auctionBidIncrement'),
            auctionCurrency:         $id('auctionCurrency'),
            auctionShipping:         $id('auctionShipping'),
            auctionPaymentDeadline:  $id('auctionPaymentDeadline'),
            // Schedule
            auctionStartDate:        $id('auctionStartDate'),
            auctionEndDate:          $id('auctionEndDate'),
            auctionAutoExtend:       $id('auctionAutoExtend'),
            auctionExtendMinutes:    $id('auctionExtendMinutes'),
            auctionMinExtendBidTime: $id('auctionMinExtendBidTime'),
            // Bids
            bidsLoading:       $id('bidsLoading'),
            bidsEmpty:         $id('bidsEmpty'),
            bidsTableWrapper:  $id('bidsTableWrapper'),
            bidsTableBody:     $id('bidsTableBody'),
            btnRefreshBids:    $id('btnRefreshBids'),
            statTotalBids:     $id('statTotalBids'),
            statTotalBidders:  $id('statTotalBidders'),
            statCurrentPrice:  $id('statCurrentPrice'),
            statWinningAmount: $id('statWinningAmount'),
            // Translations
            auctionTranslations: $id('auctionTranslations'),
            auctionLangSelect:   $id('auctionLangSelect'),
            auctionAddLangBtn:   $id('auctionAddLangBtn'),
            // Table
            tbody:               $id('auctionTableBody'),
            // Filters
            auctionSearch:         $id('auctionSearch'),
            auctionTenantFilter:   $id('auctionTenantFilter'),
            auctionStatusFilter:   $id('auctionStatusFilter'),
            auctionTypeFilter:     $id('auctionTypeFilter'),
            auctionFeaturedFilter: $id('auctionFeaturedFilter'),
            // Buttons
            btnAdd:     $id('btnAddAuction'),
            btnClose:   $id('btnCloseAuctionForm'),
            btnCancel:  $id('btnCancelAuctionForm'),
            btnSubmit:  $id('btnSubmitAuctionForm'),
            btnDeleteAuction: $id('btnDeleteAuction'),
            btnApply:   $id('btnApplyAuctionFilters'),
            btnReset:   $id('btnResetAuctionFilters'),
            btnRetry:   $id('btnAuctionRetry'),
            // Pagination
            pagination:        $id('auctionPagination'),
            paginationInfo:    $id('auctionPaginationInfo'),
            resultsCount:      $id('auctionResultsCount'),
            resultsCountText:  $id('auctionResultsCountText')
        };

        // Try translations (non-fatal)
        await loadTranslations(state.language);

        // Wire up event handlers (onXxx prevents duplicate listeners on re-init)
        if (el.form)          el.form.onsubmit          = saveAuction;
        if (el.btnAdd)        el.btnAdd.onclick          = () => showForm();
        if (el.btnClose)      el.btnClose.onclick        = hideForm;
        if (el.btnCancel)     el.btnCancel.onclick       = hideForm;
        if (el.btnApply)      el.btnApply.onclick        = applyFilters;
        if (el.btnReset)      el.btnReset.onclick        = resetFilters;
        if (el.btnRetry)      el.btnRetry.onclick        = () => loadAuctions(state.page);
        if (el.btnDeleteAuction) el.btnDeleteAuction.onclick = () => {
            if (state.currentAuction) deleteAuction(state.currentAuction.id);
        };
        if (el.btnRefreshBids) el.btnRefreshBids.onclick = () => {
            if (state.currentAuction?.id) loadBids(state.currentAuction.id);
        };
        if (el.auctionAddLangBtn) el.auctionAddLangBtn.onclick = addTranslation;

        // Search on Enter
        if (el.auctionSearch) {
            el.auctionSearch.addEventListener('keydown', e => { if (e.key === 'Enter') applyFilters(); });
        }

        // Initialize tabs
        initTabs();

        // Load dropdown data
        await loadDropdownData();

        // Initial data load
        await loadAuctions(1);

        console.log('[Auctions] ✓ Initialized');
    }

    // ════════════════════════════════════════════════════════════
    // PUBLIC API
    // ════════════════════════════════════════════════════════════
    window.Auctions = {
        init,
        load: loadAuctions,
        add:  () => showForm(),
        edit: async (id) => {
            try {
                const safeId = encodeURIComponent(id);
                const result = await apiCall(`${API.auctions}?id=${safeId}&lang=${state.language}&tenant_id=${state.tenantId}&format=json`);
                if (result.success && result.data) {
                    showForm(result.data);
                } else {
                    throw new Error('Auction not found');
                }
            } catch (err) {
                console.error('[Auctions] Edit failed:', err);
                showNotification(err.message || 'Failed to load auction', 'error');
            }
        },
        remove: deleteAuction,
        setLanguage: async (lang) => {
            state.language = lang;
            await loadTranslations(lang);
            loadAuctions(state.page);
        }
    };

    window.page = { run: init };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            if (window.AdminFramework && !window.page.__fragment_init) {
                init().catch(e => console.error('[Auctions] Auto-init failed:', e));
            }
        });
    } else {
        if (window.AdminFramework && !window.page.__fragment_init) {
            init().catch(e => console.error('[Auctions] Auto-init failed:', e));
        }
    }
    window.page.__fragment_init = false;

    console.log('[Auctions] Module loaded');

})();