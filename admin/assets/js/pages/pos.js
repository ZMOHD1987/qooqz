(function () {
    'use strict';

    /**
     * admin/assets/js/pages/pos.js
     * POS Cashier System – Main Logic
     * Enhanced: hierarchical categories, stock badges, barcode scanner
     * (hardware + camera), sales history, admin reports, price edit.
     */

    // ─────────────────────────────────────────────
    // Config & State
    // ─────────────────────────────────────────────
    const CFG = window.POS_CONFIG || {};

    const API = {
        pos:              '/api/pos_sessions',
        products:         '/api/products',
        entities:         '/api/entities',
        categories:       '/api/categories',
        productCategories:'/api/product_categories',
        publicDiscounts:  '/api/public/discounts',
        discountActions:  '/api/discount_actions',
    };

    const state = {
        tenantId:      CFG.TENANT_ID || 1,
        entityId:      CFG.ENTITY_ID || null,
        lang:          CFG.LANG || 'ar',
        dir:           CFG.DIR || 'ltr',
        csrf:          CFG.CSRF || '',
        currency:      CFG.CURRENCY || 'SAR',
        currencySymbol: CFG.CURRENCY_SYMBOL || '',
        session:       null,
        products:      [],
        categoryTree:  [],     // [{id, name, parent_id, children:[...]}]
        parentCatId:   null,   // selected parent category id (null = All)
        subCatId:      null,   // selected sub-category id (null = all in parent)
        activeTab:     'pos',  // 'pos' | 'history' | 'reports'
        cart:          [],
        paymentMethod: 'cash',
        searchQuery:   '',
        loading:       false,
        // Category→Product mapping (loaded on-demand per category)
        categoryProductIds: {},  // catId → [productId, ...]
        // Discounts
        discounts:     [],       // active discounts from API
        activeCoupon:  null,     // applied coupon discount object
        couponDiscount:0,        // computed discount amount from coupon
        // Barcode scanner
        barcodeMode:   false,  // hardware scanner input mode active
        barcodeBuffer: '',     // accumulate typed chars
        barcodeLastKey: 0,     // last keydown timestamp for speed detection
        // Camera scanner
        cameraActive:  false,
        cameraStream:  null,
        cameraScanInterval: null,
        // History & Reports
        salesHistory:  [],
        reportsOrders: [],
        // Active filters for history/reports
        historyFilters: { dateFrom: '', dateTo: '', paymentMethod: '' },
        reportsFilters: { dateFrom: '', dateTo: '', paymentMethod: '' },
        // Entity → tenantId map (populated from entities API, for super-admin support)
        entityTenantMap: {},
    };

    // Barcode hardware scanner: gap in ms above which typed chars are considered stale (user typing vs scanner)
    const BARCODE_STALE_TIMEOUT_MS = 500;

    let translations = {};

    // ─────────────────────────────────────────────
    // Translations
    // ─────────────────────────────────────────────
    async function loadTranslations() {
        try {
            const url = `/languages/POS/${encodeURIComponent(state.lang)}.json`;
            const res = await fetch(url, { credentials: 'same-origin' });
            if (!res.ok) throw new Error('not found');
            translations = await res.json();
        } catch {
            translations = {};
        }
    }

    function t(key, fallback) {
        const parts = key.split('.');
        let cur = translations;
        for (const p of parts) {
            if (cur == null || typeof cur !== 'object') return fallback ?? key;
            cur = cur[p];
        }
        return (typeof cur === 'string') ? cur : (fallback ?? key);
    }

    // ─────────────────────────────────────────────
    // API Helpers
    // ─────────────────────────────────────────────
    async function apiGet(url, params = {}) {
        const u = new URL(url, location.origin);
        u.searchParams.set('tenant_id', state.tenantId);
        for (const [k, v] of Object.entries(params)) {
            if (v !== null && v !== undefined) u.searchParams.set(k, v);
        }
        const res = await fetch(u.toString(), { credentials: 'same-origin' });
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        return res.json();
    }

    async function apiPost(url, body = {}) {
        body.tenant_id = state.tenantId;
        const res = await fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': state.csrf,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify(body),
        });
        const data = await res.json().catch(() => ({}));
        if (!res.ok) throw new Error(data.message || `HTTP ${res.status}`);
        return data;
    }

    // ─────────────────────────────────────────────
    // DOM References
    // ─────────────────────────────────────────────
    let container, alertsEl, openSessionView, mainLayout;
    let searchInput, parentCatTabs, subCatTabs, productsGrid;
    let cartItemsList, cartCount, cartEmpty;
    let subtotalEl, taxEl, discountInput, couponInput, couponStatusEl, applyCouponBtn, clearCouponBtn, couponRow, totalEl, grandTotalEl;
    let amountPaidInput, changeDisplay, checkoutBtn, clearBtn;
    let payMethodBtns;
    let modalBackdrop;
    let tabNav, historyPanel, reportsPanel;
    let barcodeBtn, barcodeBanner, barcodeCameraBtn, barcodeClose;
    let cameraOverlay, cameraVideo, cameraStatus, cameraStopBtn, cameraCloseBtn;
    let contextNav, entityNavTabs;

    function cacheElements() {
        container        = document.getElementById('posPageContainer');
        alertsEl         = document.getElementById('posAlerts');
        openSessionView  = document.getElementById('posOpenSession');
        mainLayout       = document.getElementById('posMainLayout');
        searchInput      = document.getElementById('posSearch');
        parentCatTabs    = document.getElementById('posParentCats');
        subCatTabs       = document.getElementById('posSubCats');
        productsGrid     = document.getElementById('posProductsGrid');
        cartItemsList    = document.getElementById('posCartItems');
        cartCount        = document.getElementById('posCartCount');
        cartEmpty        = document.getElementById('posCartEmpty');
        subtotalEl       = document.getElementById('posSubtotal');
        taxEl            = document.getElementById('posTax');
        discountInput    = document.getElementById('posDiscount');
        couponInput      = document.getElementById('posCouponInput');
        couponStatusEl   = document.getElementById('posCouponStatus');
        applyCouponBtn   = document.getElementById('posApplyCoupon');
        clearCouponBtn   = document.getElementById('posClearCoupon');
        couponRow        = document.getElementById('posCouponRow');
        totalEl          = document.getElementById('posTotal');
        grandTotalEl     = document.getElementById('posGrandTotal');
        amountPaidInput  = document.getElementById('posAmountPaid');
        changeDisplay    = document.getElementById('posChange');
        checkoutBtn      = document.getElementById('posCheckoutBtn');
        clearBtn         = document.getElementById('posClearBtn');
        payMethodBtns    = document.querySelectorAll('.pos-pay-method-btn');
        modalBackdrop    = document.getElementById('posModalBackdrop');
        tabNav           = document.getElementById('posTabNav');
        historyPanel     = document.getElementById('posHistoryPanel');
        reportsPanel     = document.getElementById('posReportsPanel');
        barcodeBtn       = document.getElementById('posBarcodeBtn');
        barcodeBanner    = document.getElementById('posBarcodeBanner');
        barcodeCameraBtn = document.getElementById('posBarcodeCameraBtn');
        barcodeClose     = document.getElementById('posBarcodeClose');
        cameraOverlay    = document.getElementById('posCameraOverlay');
        cameraVideo      = document.getElementById('posCameraVideo');
        cameraStatus     = document.getElementById('posCameraStatus');
        cameraStopBtn    = document.getElementById('posCameraStop');
        cameraCloseBtn   = document.getElementById('posCameraClose');
        contextNav       = document.getElementById('posContextNav');
        entityNavTabs    = document.getElementById('posEntityNavTabs');
    }

    // ─────────────────────────────────────────────
    // Alerts
    // ─────────────────────────────────────────────
    function showAlert(msg, type = 'success', duration = 4000) {
        if (!alertsEl) return;
        const div = document.createElement('div');
        div.className = `pos-alert pos-alert-${type}`;
        div.innerHTML = `<span>${type === 'success' ? '✓' : '✕'}</span> ${escHtml(msg)}`;
        alertsEl.prepend(div);
        setTimeout(() => div.remove(), duration);
    }

    function escHtml(str) {
        return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    // ─────────────────────────────────────────────
    // Tab Navigation
    // ─────────────────────────────────────────────
    function switchTab(tabName) {
        state.activeTab = tabName;
        document.querySelectorAll('.pos-tab-btn').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.tab === tabName);
        });
        if (mainLayout)    mainLayout.style.display    = tabName === 'pos'     ? 'grid' : 'none';
        if (historyPanel)  historyPanel.style.display  = tabName === 'history' ? 'block' : 'none';
        if (reportsPanel)  reportsPanel.style.display  = tabName === 'reports' ? 'block' : 'none';

        if (tabName === 'history') loadSalesHistory();
        if (tabName === 'reports') loadReports();
    }

    // ─────────────────────────────────────────────
    // Session Management
    // ─────────────────────────────────────────────
    /**
     * Resolve the correct tenant_id for a given entity+session combination.
     * Prefers entityTenantMap (populated from entities API), then session's entity_tenant_id,
     * then session's tenant_id as last resort.
     */
    function resolveEntityTenantId(entityId, session) {
        return state.entityTenantMap[entityId]
            || (session?.entity_tenant_id ? parseInt(session.entity_tenant_id, 10) : null)
            || (session?.tenant_id ? parseInt(session.tenant_id, 10) : null)
            || null;
    }

    async function loadCurrentSession() {
        try {
            const params = {};
            if (state.entityId) params.entity_id = state.entityId;
            const res = await apiGet(API.pos, { ...params, action: 'current' });
            state.session = res.session || null;
        } catch {
            state.session = null;
        }
    }

    function updateSessionBar() {
        const bar = document.getElementById('posSessionBar');
        if (!bar) return;
        const s = state.session;
        if (s) {
            bar.innerHTML = `
                <div class="session-info">
                    <span class="session-badge open">● ${t('pos.session.open','Open')}</span>
                    <span>🏪 ${escHtml(s.store_name || '')}</span>
                    <span>👤 ${escHtml(s.cashier_name || '')}</span>
                    <span>🕐 ${new Date(s.opened_at).toLocaleTimeString(state.lang === 'ar' ? 'ar-SA' : 'en')}</span>
                    <span>💵 ${t('pos.session.cash','Cash')}: ${formatCurrency(s.total_cash)}</span>
                    <span>💳 ${t('pos.session.card','Card')}: ${formatCurrency(s.total_card)}</span>
                </div>
                <div>
                    <button class="btn btn-danger btn-sm" id="btnCloseSession">
                        🔒 ${t('pos.session.close','Close Session')}
                    </button>
                </div>`;
            document.getElementById('btnCloseSession')?.addEventListener('click', showCloseSessionModal);
        } else {
            bar.innerHTML = `<span class="session-badge closed">● ${t('pos.session.closed','No Active Session')}</span>`;
        }
    }

    function showOpenSessionView() {
        openSessionView && (openSessionView.style.display = 'flex');
        tabNav && (tabNav.style.display = 'none');
        mainLayout && (mainLayout.style.display = 'none');
        historyPanel && (historyPanel.style.display = 'none');
        reportsPanel && (reportsPanel.style.display = 'none');
    }

    function showMainLayout() {
        openSessionView && (openSessionView.style.display = 'none');
        tabNav && (tabNav.style.display = 'flex');
        switchTab('pos');
        // Render entity/tenant context navigation after session is active
        renderEntityContextNav();
    }

    async function openSession(entityId, openingBalance, cashierUserId) {
        try {
            const res = await apiPost(API.pos, {
                action: 'open',
                entity_id: entityId,
                opening_balance: openingBalance,
                cashier_user_id: cashierUserId,
            });
            state.session = res.session;
            state.entityId = entityId;
            const resolvedTenant = resolveEntityTenantId(entityId, state.session);
            if (resolvedTenant) state.tenantId = resolvedTenant;
            updateSessionBar();
            showMainLayout();
            await Promise.all([loadCategories(), loadDiscounts()]);
            await loadProducts();
            showAlert(t('pos.session.opened_msg', 'Session opened successfully'), 'success');
        } catch (err) {
            showAlert(err.message, 'error');
        }
    }

    function showCloseSessionModal() {
        if (!state.session) return;
        showModal(`
            <h3>🔒 ${t('pos.session.close','Close Session')}</h3>
            <p style="color:var(--text-secondary,#94a3b8);font-size:.88rem;margin:0 0 16px">
                ${t('pos.session.close_confirm','Are you sure you want to close this session?')}
            </p>
            <div class="pos-close-session-form">
                <div class="form-group">
                    <label>${t('pos.session.closing_balance','Closing Cash Balance')}</label>
                    <input type="number" id="closingBalance" class="form-control" step="0.01" min="0"
                           value="${state.session.total_cash || 0}">
                </div>
                <div class="btn-group">
                    <button class="btn btn-outline" onclick="posCloseModal()">
                        ${t('common.cancel','Cancel')}
                    </button>
                    <button class="btn btn-danger" onclick="posConfirmCloseSession()">
                        🔒 ${t('pos.session.close','Close Session')}
                    </button>
                </div>
            </div>
        `);
    }

    window.posConfirmCloseSession = async function () {
        const balance = parseFloat(document.getElementById('closingBalance')?.value ?? 0);
        try {
            await apiPost(API.pos, {
                action: 'close',
                session_id: state.session.id,
                closing_balance: balance,
            });
            state.session = null;
            closeModal();
            updateSessionBar();
            showOpenSessionView();
            state.cart = [];
            renderCart();
            showAlert(t('pos.session.closed_msg', 'Session closed'), 'success');
        } catch (err) {
            showAlert(err.message, 'error');
        }
    };

    // ─────────────────────────────────────────────
    // Categories (hierarchical tree)
    // ─────────────────────────────────────────────
    async function loadCategories() {
        try {
            const params = { limit: 500, is_active: 1, lang: state.lang };
            if (state.entityId) params.entity_id = state.entityId;
            const res = await apiGet(API.categories, params);
            const items = res.data?.items ?? res.items ?? (Array.isArray(res) ? res : []);
            state.categoryTree = buildCategoryTree(items);
        } catch {
            state.categoryTree = [];
        }
        renderParentCats();
    }

    // ─────────────────────────────────────────────
    // Discounts
    // ─────────────────────────────────────────────
    async function loadDiscounts() {
        try {
            const res = await apiGet(API.publicDiscounts, { lang: state.lang });
            // Public route returns ResponseFormatter::success(['ok'=>true,'data'=>[...]])
            // ResponseFormatter wraps payload in { success, data: payload }
            // so actual list is at res.data.data (nested)
            let items;
            if (Array.isArray(res.data)) {
                items = res.data;
            } else if (Array.isArray(res.data?.data)) {
                items = res.data.data;
            } else if (Array.isArray(res.items)) {
                items = res.items;
            } else {
                items = [];
            }
            state.discounts = items;
        } catch {
            state.discounts = [];
        }
        renderActiveDiscountsBanner();
    }

    /** Render active offers/discounts banner above the products grid */
    function renderActiveDiscountsBanner() {
        const banner = document.getElementById('posDiscountsBanner');
        if (!banner) return;

        const now = Date.now();
        const activeDiscounts = state.discounts.filter(d => {
            if (d.status && d.status !== 'active') return false;
            if (d.ends_at && new Date(d.ends_at).getTime() < now) return false;
            if (d.starts_at && new Date(d.starts_at).getTime() > now) return false;
            if (d.max_redemptions > 0 && d.current_redemptions >= d.max_redemptions) return false;
            return true;
        });

        if (!activeDiscounts.length) {
            banner.style.display = 'none';
            return;
        }

        const badges = activeDiscounts.map(d => {
            const label = d.marketing_badge || d.title || '';
            if (!label) return '';
            const typeIcon = d.type === 'buy_x_get_y' ? '🎁' :
                             (d.code ? '🏷' : '⚡');
            return `<span class="pos-offer-badge">${typeIcon} ${escHtml(label)}</span>`;
        }).filter(Boolean);

        if (!badges.length) {
            banner.style.display = 'none';
            return;
        }

        banner.innerHTML = `
            <span class="pos-offers-label">🎉 ${t('pos.offers.active', 'Active Offers')}:</span>
            ${badges.join('')}
        `;
        banner.style.display = 'flex';
    }

    /** Return active auto-apply discounts (for showing badges on all products) */
    function getAutoApplyDiscounts() {
        const now = Date.now();
        return state.discounts.filter(d => {
            if (!d.auto_apply) return false;
            if (d.status && d.status !== 'active') return false;
            if (d.ends_at && new Date(d.ends_at).getTime() < now) return false;
            if (d.starts_at && new Date(d.starts_at).getTime() > now) return false;
            if (d.max_redemptions > 0 && d.current_redemptions >= d.max_redemptions) return false;
            return true;
        });
    }

    /** Find a coupon discount by code (case-insensitive) */
    function findCouponByCode(code) {
        if (!code) return null;
        const now = Date.now();
        return state.discounts.find(d => {
            if (!d.code) return false;
            if (d.code.toLowerCase() !== code.toLowerCase()) return false;
            if (d.status && d.status !== 'active') return false;
            if (d.ends_at && new Date(d.ends_at).getTime() < now) return false;
            if (d.starts_at && new Date(d.starts_at).getTime() > now) return false;
            if (d.max_redemptions > 0 && d.current_redemptions >= d.max_redemptions) return false;
            return true;
        }) ?? null;
    }

    async function applyCoupon() {
        const code = (couponInput?.value ?? '').trim();
        if (!code) {
            showAlert(t('pos.coupon.enter_code', 'Please enter a coupon code'), 'error');
            return;
        }
        const discount = findCouponByCode(code);
        if (!discount) {
            showAlert(t('pos.coupon.invalid', 'Invalid or expired coupon code'), 'error');
            if (couponStatusEl) couponStatusEl.textContent = '';
            return;
        }
        // Fetch discount actions to determine the discount value
        try {
            const res = await apiGet(API.discountActions, { discount_id: discount.id });
            const actions = res.data?.items ?? res.items ?? (Array.isArray(res.data) ? res.data : []);
            state.activeCoupon = { discount, actions };
            computeCouponDiscount();
            renderCouponStatus();
            updateTotals();
            showAlert(`${t('pos.coupon.applied', 'Coupon applied')}: ${escHtml(discount.title || discount.code)}`, 'success');
        } catch {
            // Fallback: use discount without action details
            state.activeCoupon = { discount, actions: [] };
            computeCouponDiscount();
            renderCouponStatus();
            updateTotals();
        }
    }

    function computeCouponDiscount() {
        if (!state.activeCoupon) { state.couponDiscount = 0; return; }
        const { discount, actions } = state.activeCoupon;
        const { sub, tax } = cartSubtotals();
        const orderTotal = sub + tax;
        let amount = 0;
        actions.forEach(a => {
            const val = parseFloat(a.action_value) || 0;
            if (a.action_type === 'percentage_discount' || a.action_type === 'percent') {
                amount += orderTotal * (val / 100);
            } else if (a.action_type === 'fixed_discount' || a.action_type === 'fixed') {
                amount += val;
            }
        });
        // If no actions fetched, don't guess – just leave discount at 0
        if (actions.length === 0) {
            state.couponDiscount = 0;
            return;
        }
        state.couponDiscount = Math.max(0, Math.min(amount, orderTotal));
    }

    function clearCoupon() {
        state.activeCoupon = null;
        state.couponDiscount = 0;
        if (couponInput) couponInput.value = '';
        renderCouponStatus();
        updateTotals();
    }

    function renderCouponStatus() {
        if (!couponStatusEl) return;
        if (state.activeCoupon) {
            const { discount } = state.activeCoupon;
            couponStatusEl.innerHTML = `<span class="pos-coupon-applied">✓ ${escHtml(discount.title || discount.code)}: -${formatCurrency(state.couponDiscount)}</span>`;
            if (clearCouponBtn) clearCouponBtn.style.display = 'inline-flex';
        } else {
            couponStatusEl.textContent = '';
            if (clearCouponBtn) clearCouponBtn.style.display = 'none';
        }
    }

    // ─────────────────────────────────────────────
    // Category → Product ID Mapping (on-demand)
    // ─────────────────────────────────────────────
    async function loadCategoryProductIds(catId) {
        if (state.categoryProductIds[catId] !== undefined) return; // already cached
        state.categoryProductIds[catId] = []; // mark as loading
        try {
            const res = await apiGet(API.productCategories, { category_id: catId, limit: 2000 });
            const items = res.data?.items ?? res.items ?? [];
            state.categoryProductIds[catId] = items.map(i => parseInt(i.product_id, 10)).filter(Boolean);
        } catch {
            state.categoryProductIds[catId] = [];
        }
    }

    async function loadSubtreeCategoryProducts(catId) {
        if (!catId) return;
        const subtreeIds = getSubtreeIds(catId);
        await Promise.all(subtreeIds.map(id => loadCategoryProductIds(id)));
    }

    function buildCategoryTree(flatList) {
        const map = {};
        flatList.forEach(c => { map[c.id] = { ...c, children: [] }; });
        const roots = [];
        flatList.forEach(c => {
            if (c.parent_id && map[c.parent_id]) {
                map[c.parent_id].children.push(map[c.id]);
            } else {
                roots.push(map[c.id]);
            }
        });
        return roots;
    }

    function findCategoryById(id) {
        function search(list) {
            for (const c of list) {
                if (c.id == id) return c;
                const found = search(c.children);
                if (found) return found;
            }
            return null;
        }
        return search(state.categoryTree);
    }

    /** Get all category IDs in a subtree (parent + all descendants) */
    function getSubtreeIds(catId) {
        const cat = findCategoryById(catId);
        if (!cat) return [catId];
        const ids = [catId];
        cat.children.forEach(child => ids.push(...getSubtreeIds(child.id)));
        return ids;
    }

    function renderParentCats() {
        if (!parentCatTabs) return;
        const allLabel = t('pos.products.all', 'All');
        let html = `<button class="pos-cat-tab ${state.parentCatId === null ? 'active' : ''}" data-parent-id="0">${allLabel}</button>`;
        state.categoryTree.forEach(cat => {
            const active = state.parentCatId == cat.id;
            const catName = cat.name || cat.slug || '';
            const icon = cat.image_url ? `<img src="${escHtml(cat.image_url)}" alt="" style="width:16px;height:16px;border-radius:3px;object-fit:cover;vertical-align:middle;margin-inline-end:4px">` : '';
            html += `<button class="pos-cat-tab ${active ? 'active' : ''}" data-parent-id="${cat.id}">${icon}${escHtml(catName)}</button>`;
        });
        parentCatTabs.innerHTML = html;
        parentCatTabs.querySelectorAll('.pos-cat-tab').forEach(btn => {
            btn.addEventListener('click', async () => {
                const pid = parseInt(btn.dataset.parentId, 10) || null;
                state.parentCatId = pid || null;
                state.subCatId = null;
                renderParentCats();
                renderSubCats();
                if (state.parentCatId) {
                    productsGrid && (productsGrid.innerHTML = `<div class="pos-loading" style="grid-column:1/-1"><div class="pos-spinner"></div></div>`);
                    await loadSubtreeCategoryProducts(state.parentCatId);
                }
                renderProducts();
            });
        });
    }

    function renderSubCats() {
        if (!subCatTabs) return;
        if (!state.parentCatId) {
            subCatTabs.style.display = 'none';
            subCatTabs.innerHTML = '';
            return;
        }
        const parent = findCategoryById(state.parentCatId);
        if (!parent || !parent.children.length) {
            subCatTabs.style.display = 'none';
            subCatTabs.innerHTML = '';
            return;
        }
        let html = `<button class="pos-sub-cat-tab ${state.subCatId === null ? 'active' : ''}" data-sub-id="0">${t('pos.products.all','All')}</button>`;
        parent.children.forEach(sub => {
            html += `<button class="pos-sub-cat-tab ${state.subCatId == sub.id ? 'active' : ''}" data-sub-id="${sub.id}">${escHtml(sub.name || sub.slug || '')}</button>`;
        });
        subCatTabs.innerHTML = html;
        subCatTabs.style.display = 'flex';
        subCatTabs.querySelectorAll('.pos-sub-cat-tab').forEach(btn => {
            btn.addEventListener('click', async () => {
                state.subCatId = parseInt(btn.dataset.subId, 10) || null;
                renderSubCats();
                if (state.subCatId) {
                    productsGrid && (productsGrid.innerHTML = `<div class="pos-loading" style="grid-column:1/-1"><div class="pos-spinner"></div></div>`);
                    await loadSubtreeCategoryProducts(state.subCatId);
                }
                renderProducts();
            });
        });
    }

    // ─────────────────────────────────────────────
    // Products
    // ─────────────────────────────────────────────
    async function loadProducts() {
        if (!productsGrid) return;
        productsGrid.innerHTML = `<div class="pos-loading" style="grid-column:1/-1"><div class="pos-spinner"></div></div>`;
        try {
            const params = { limit: 500, page: 1, is_active: 1, lang: state.lang };
            if (state.entityId) params.entity_id = state.entityId;
            const res = await apiGet(API.products, params);
            const rawItems = res.data?.items ?? res.items ?? [];
            state.products = Array.isArray(rawItems) ? rawItems : [];
            // If no category tree loaded yet, load it now
            if (!state.categoryTree.length) {
                await loadCategories();
            } else {
                renderParentCats();
            }
            renderProducts();
        } catch (err) {
            productsGrid.innerHTML = `<p style="grid-column:1/-1;color:var(--danger-color,#ef4444);padding:20px">${escHtml(err.message)}</p>`;
        }
    }

    function filteredProducts() {
        let prods = state.products;

        // Category filter using on-demand loaded category→product mapping
        const catId = state.subCatId || state.parentCatId;
        if (catId) {
            const subtreeIds = getSubtreeIds(catId);
            const allowedPids = new Set();
            let anyCatLoaded = false;
            subtreeIds.forEach(id => {
                const ids = state.categoryProductIds[id];
                if (Array.isArray(ids)) {
                    anyCatLoaded = true;
                    ids.forEach(pid => allowedPids.add(pid));
                }
            });
            // Only filter when at least one category in the subtree has been loaded.
            // This prevents showing all products when loading is still in progress,
            // and correctly shows 0 products when a category has no products.
            if (anyCatLoaded) {
                prods = prods.filter(p => allowedPids.has(parseInt(p.id, 10)));
            }
        }

        // Search / barcode filter
        const q = state.searchQuery.toLowerCase().trim();
        if (q) {
            prods = prods.filter(p =>
                (p.name || '').toLowerCase().includes(q) ||
                (p.sku || '').toLowerCase().includes(q) ||
                (p.barcode || '').toLowerCase().includes(q)
            );
        }
        return prods;
    }

    function stockInfo(p) {
        const qty     = parseInt(p.stock_quantity ?? 0, 10);
        const status  = (p.stock_status || '').toLowerCase();
        const managed = p.manage_stock == 1 || p.manage_stock === true;

        if (!managed) return { cls: 'in-stock', label: t('pos.stock.available','Available') };
        if (status === 'out_of_stock' || qty <= 0) return { cls: 'out-of-stock', label: t('pos.stock.out','Out of stock'), disabled: true };
        const threshold = parseInt(p.low_stock_threshold ?? 5, 10);
        if (status === 'low_stock' || qty <= threshold) return { cls: 'low-stock', label: `${t('pos.stock.low','Low')}: ${qty}` };
        return { cls: 'in-stock', label: `${t('pos.stock.qty','Qty')}: ${qty}` };
    }

    function renderProducts() {
        if (!productsGrid) return;
        const prods = filteredProducts();
        const autoBadges = getAutoApplyDiscounts();

        if (!prods.length) {
            productsGrid.innerHTML = `<p style="grid-column:1/-1;color:var(--text-muted,#64748b);padding:30px;text-align:center">${t('pos.products.empty','No products found')}</p>`;
            return;
        }
        productsGrid.innerHTML = prods.map(p => {
            const price         = parseFloat(p.price || p.sale_price || 0);
            const comparePrice  = parseFloat(p.compare_at_price || 0);
            const currency      = p.currency_code || state.currency || CFG.CURRENCY || 'SAR';
            const img           = p.image_url || p.image_thumb_url || '';
            const stock         = stockInfo(p);
            const disabledCls   = stock.disabled ? ' out-of-stock' : '';
            const hasDiscount   = comparePrice > 0 && comparePrice > price;
            const discountPct   = hasDiscount ? Math.round((1 - price / comparePrice) * 100) : 0;

            const warehouseHtml = p.warehouse_name
                ? `<div class="pos-product-warehouse">🏭 ${escHtml(p.warehouse_name)}</div>` : '';

            const priceHtml = hasDiscount ? `
                <div class="pos-product-original-price">${formatCurrency(comparePrice, currency)}</div>
                <div class="pos-product-price sale">${formatCurrency(price, currency)}</div>
            ` : `
                <div class="pos-product-price">${formatCurrency(price, currency)}</div>
            `;

            // Determine best discount badge to show
            let discountBadgeHtml = '';
            if (hasDiscount) {
                discountBadgeHtml = `<div class="pos-discount-badge">-${discountPct}%</div>`;
            } else if (autoBadges.length > 0) {
                // Find BOGO (buy_x_get_y) offer first, then any auto-apply offer
                const bogoBadge = autoBadges.find(d => d.type === 'buy_x_get_y');
                const bestBadge = bogoBadge || autoBadges[0];
                const badgeLabel = bestBadge.marketing_badge || bestBadge.title || '';
                const badgeIcon  = bestBadge.type === 'buy_x_get_y' ? '🎁' : '⚡';
                if (badgeLabel) {
                    discountBadgeHtml = `<div class="pos-discount-badge promo">${badgeIcon} ${escHtml(badgeLabel)}</div>`;
                }
            }

            return `
            <div class="pos-product-card${disabledCls}" data-id="${p.id}" data-barcode="${escHtml(p.barcode || '')}" title="${escHtml(p.name || '')}">
                <div class="pos-card-img-wrap">
                    ${img
                        ? `<img src="${escHtml(img)}" alt="${escHtml(p.name || '')}" loading="lazy">`
                        : `<div class="pos-product-img-placeholder">📦</div>`
                    }
                    ${discountBadgeHtml}
                </div>
                <div class="pos-product-name">${escHtml(p.name || p.slug || '')}</div>
                ${priceHtml}
                ${p.sku ? `<div class="pos-product-sku">${escHtml(p.sku)}</div>` : ''}
                ${warehouseHtml}
                <div class="pos-stock-badge ${stock.cls}">${escHtml(stock.label)}</div>
            </div>`;
        }).join('');

        productsGrid.querySelectorAll('.pos-product-card:not(.out-of-stock)').forEach(card => {
            card.addEventListener('click', () => {
                const prod = state.products.find(p => p.id == card.dataset.id);
                if (prod) addToCart(prod);
            });
        });
    }

    // ─────────────────────────────────────────────
    // Barcode Scanner – Hardware Mode
    // ─────────────────────────────────────────────
    function toggleBarcodeMode() {
        state.barcodeMode = !state.barcodeMode;
        barcodeBtn?.classList.toggle('active', state.barcodeMode);
        if (barcodeBanner) barcodeBanner.style.display = state.barcodeMode ? 'flex' : 'none';
        if (state.barcodeMode) {
            searchInput?.focus();
        }
    }

    /** Hardware barcode readers type chars very fast (< 50 ms apart) and end with Enter.
     *  We detect this pattern and route to product lookup instead of text search. */
    function initBarcodeHardware() {
        if (!searchInput) return;
        searchInput.addEventListener('keydown', (e) => {
            if (!state.barcodeMode) return;
            const now = Date.now();
            if (e.key === 'Enter') {
                e.preventDefault();
                const code = state.barcodeBuffer.trim();
                state.barcodeBuffer = '';
                if (code.length >= 3) lookupBarcode(code);
                return;
            }
            // Accumulate characters quickly typed (< 100 ms gap = likely scanner)
            if (e.key.length === 1) {
                const gap = now - state.barcodeLastKey;
                if (gap > BARCODE_STALE_TIMEOUT_MS) state.barcodeBuffer = ''; // stale, reset
                state.barcodeBuffer += e.key;
                state.barcodeLastKey = now;
                // Prevent going to search input (silent mode)
                e.preventDefault();
                // Show in search box for user feedback
                searchInput.value = state.barcodeBuffer;
            }
        });
    }

    async function lookupBarcode(code) {
        if (cameraStatus) cameraStatus.textContent = `${t('pos.barcode.found','Found')}: ${code}`;
        // First check in already loaded products
        const existing = state.products.find(p =>
            (p.barcode || '').toLowerCase() === code.toLowerCase() ||
            (p.sku || '').toLowerCase() === code.toLowerCase()
        );
        if (existing) {
            addToCart(existing);
            if (searchInput) searchInput.value = '';
            state.barcodeBuffer = '';
            showAlert(`${t('pos.barcode.added','Added')}: ${existing.name || existing.sku}`, 'success', 2000);
            return;
        }
        // Fallback: API lookup by barcode
        try {
            const res = await apiGet(API.products, { barcode: code, lang: state.lang });
            const items = res.data?.items ?? res.items ?? [];
            if (items.length > 0) {
                const prod = items[0];
                // Merge into state.products if not already there
                if (!state.products.find(p => p.id === prod.id)) {
                    state.products.push(prod);
                }
                addToCart(prod);
                if (searchInput) searchInput.value = '';
                state.barcodeBuffer = '';
                showAlert(`${t('pos.barcode.added','Added')}: ${prod.name || prod.sku}`, 'success', 2000);
            } else {
                showAlert(`${t('pos.barcode.not_found','Product not found')}: ${code}`, 'error');
            }
        } catch (err) {
            showAlert(err.message, 'error');
        }
    }

    // ─────────────────────────────────────────────
    // Barcode Scanner – Camera Mode
    // ─────────────────────────────────────────────
    async function startCameraScanner() {
        if (!cameraOverlay || !cameraVideo) {
            showAlert(t('pos.barcode.camera_unavailable','Camera not available'), 'error');
            return;
        }
        try {
            state.cameraStream = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: { ideal: 'environment' }, width: { ideal: 1280 } }
            });
            cameraVideo.srcObject = state.cameraStream;
            await cameraVideo.play();
            cameraOverlay.style.display = 'flex';
            state.cameraActive = true;

            // BarcodeDetector is natively supported in Chromium-based browsers (Chrome 83+, Edge 83+).
            // Firefox and Safari users will see the manual-entry fallback message below.
            if ('BarcodeDetector' in window) {
                const detector = new window.BarcodeDetector({
                    formats: ['ean_13','ean_8','code_128','code_39','qr_code','upc_a','upc_e','itf','codabar']
                });
                const scan = async () => {
                    if (!state.cameraActive) return;
                    try {
                        const barcodes = await detector.detect(cameraVideo);
                        if (barcodes.length > 0) {
                            const code = barcodes[0].rawValue;
                            stopCameraScanner();
                            await lookupBarcode(code);
                            return;
                        }
                    } catch { /* continue */ }
                    if (state.cameraActive) requestAnimationFrame(scan);
                };
                requestAnimationFrame(scan);
            } else {
                if (cameraStatus) cameraStatus.textContent = t('pos.barcode.manual_scan','BarcodeDetector not supported – use hardware scanner or type manually');
            }
        } catch (err) {
            showAlert(`${t('pos.barcode.camera_error','Camera error')}: ${err.message}`, 'error');
        }
    }

    function stopCameraScanner() {
        state.cameraActive = false;
        if (state.cameraStream) {
            state.cameraStream.getTracks().forEach(t => t.stop());
            state.cameraStream = null;
        }
        if (cameraVideo) cameraVideo.srcObject = null;
        if (cameraOverlay) cameraOverlay.style.display = 'none';
    }

    // ─────────────────────────────────────────────
    // Cart
    // ─────────────────────────────────────────────
    function addToCart(prod) {
        const existing = state.cart.find(i => i.product_id === prod.id);
        if (existing) {
            existing.qty++;
        } else {
            const price        = parseFloat(prod.price || prod.sale_price || 0);
            const comparePrice = parseFloat(prod.compare_at_price || 0);
            state.cart.push({
                product_id:      prod.id,
                product_name:    prod.name || prod.slug || '',
                sku:             prod.sku || '',
                unit_price:      price,
                sale_price:      price,
                original_price:  comparePrice > price ? comparePrice : 0,
                tax_rate:        parseFloat(prod.tax_rate || 0),
                qty:             1,
                image:           prod.image_thumb_url || prod.image_url || '',
            });
        }
        // Recompute coupon discount when cart changes
        if (state.activeCoupon) computeCouponDiscount();
        renderCart();
    }

    function removeFromCart(productId) {
        state.cart = state.cart.filter(i => i.product_id !== productId);
        if (state.activeCoupon) computeCouponDiscount();
        renderCart();
    }

    function updateQty(productId, delta) {
        const item = state.cart.find(i => i.product_id === productId);
        if (!item) return;
        item.qty = Math.max(1, item.qty + delta);
        if (state.activeCoupon) computeCouponDiscount();
        renderCart();
    }

    function clearCart() {
        state.cart = [];
        state.couponDiscount = 0;
        state.activeCoupon = null;
        renderCouponStatus();
        renderCart();
    }

    /** Compute sub + tax without discounts (used for coupon calculation) */
    function cartSubtotals() {
        let sub = 0, tax = 0;
        state.cart.forEach(i => {
            const lineTotal = i.sale_price * i.qty;
            const lineTax   = lineTotal * (i.tax_rate / 100);
            sub += lineTotal;
            tax += lineTax;
        });
        return { sub, tax };
    }

    function cartTotals() {
        const { sub, tax } = cartSubtotals();
        const manualDiscount = parseFloat(discountInput?.value ?? 0) || 0;
        const couponDiscount = state.couponDiscount || 0;
        const total          = sub + tax;
        const grandTotal     = Math.max(0, total - manualDiscount - couponDiscount);
        return { sub, tax, discount: manualDiscount, couponDiscount, total, grandTotal };
    }

    function renderCart() {
        const items = state.cart;
        const canEdit = CFG.IS_SUPER_ADMIN || CFG.CAN_EDIT_ORDERS;

        // Count badge
        const totalQty = items.reduce((s, i) => s + i.qty, 0);
        if (cartCount) cartCount.textContent = totalQty;

        // Empty state
        if (cartEmpty) cartEmpty.style.display = items.length ? 'none' : 'block';

        // Items
        if (cartItemsList) {
            cartItemsList.innerHTML = items.map(item => `
                <div class="pos-cart-item">
                    <div style="flex:1;min-width:0">
                        <div class="pos-item-name">${escHtml(item.product_name)}
                            ${canEdit ? `<button class="pos-item-edit-btn" data-action="editprice" data-id="${item.product_id}" title="Edit price">✎</button>` : ''}
                        </div>
                        <div class="pos-item-price">${formatCurrency(item.sale_price)} × ${item.qty}</div>
                        <div class="pos-qty-controls">
                            <button class="pos-qty-btn remove" data-action="dec" data-id="${item.product_id}">−</button>
                            <span class="pos-qty-display">${item.qty}</span>
                            <button class="pos-qty-btn" data-action="inc" data-id="${item.product_id}">+</button>
                        </div>
                    </div>
                    <div style="display:flex;flex-direction:column;align-items:flex-end;gap:4px">
                        <div class="pos-item-total">${formatCurrency(item.sale_price * item.qty)}</div>
                        <button class="pos-qty-btn remove" style="width:22px;height:22px;font-size:.75rem" data-action="remove" data-id="${item.product_id}">✕</button>
                    </div>
                </div>
            `).join('');

            cartItemsList.querySelectorAll('[data-action]').forEach(btn => {
                btn.addEventListener('click', () => {
                    const id  = parseInt(btn.dataset.id, 10);
                    const act = btn.dataset.action;
                    if (act === 'inc') updateQty(id, 1);
                    else if (act === 'dec') updateQty(id, -1);
                    else if (act === 'remove') removeFromCart(id);
                    else if (act === 'editprice') showEditPriceModal(id);
                });
            });
        }

        updateTotals();
    }

    // Admin: edit cart item price
    function showEditPriceModal(productId) {
        const item = state.cart.find(i => i.product_id === productId);
        if (!item) return;
        showModal(`
            <h3>✎ ${t('pos.cart.edit_price','Edit Price')}</h3>
            <p style="color:var(--text-secondary,#94a3b8);font-size:.85rem;margin:0 0 14px">${escHtml(item.product_name)}</p>
            <div class="form-group" style="margin-bottom:14px">
                <label style="font-size:.82rem;color:var(--text-secondary,#94a3b8)">${t('pos.cart.unit_price','Unit Price')}</label>
                <input type="number" id="editPriceInput" class="form-control" step="0.01" min="0"
                       value="${item.sale_price.toFixed(2)}" style="margin-top:6px">
            </div>
            <div class="btn-group">
                <button class="btn btn-outline" onclick="posCloseModal()">${t('common.cancel','Cancel')}</button>
                <button class="btn btn-primary" onclick="posApplyPriceEdit(${productId})">
                    ✓ ${t('common.apply','Apply')}
                </button>
            </div>
        `);
    }

    window.posApplyPriceEdit = function (productId) {
        const input    = document.getElementById('editPriceInput');
        const newPrice = input ? parseFloat(input.value) : NaN;
        if (!input || isNaN(newPrice) || newPrice < 0) {
            showAlert(t('pos.error.invalid_price','Invalid price'), 'error');
            return;
        }
        const item = state.cart.find(i => i.product_id === productId);
        if (item) {
            item.sale_price = newPrice;
            renderCart();
        }
        closeModal();
    };

    function updateTotals() {
        const { sub, tax, discount, couponDiscount, total, grandTotal } = cartTotals();
        if (subtotalEl)  subtotalEl.textContent  = formatCurrency(sub);
        if (taxEl)       taxEl.textContent       = formatCurrency(tax);
        if (totalEl)     totalEl.textContent     = formatCurrency(total);
        if (grandTotalEl) grandTotalEl.textContent = formatCurrency(grandTotal);

        // Show/hide coupon discount row
        if (couponRow) couponRow.style.display = couponDiscount > 0 ? 'flex' : 'none';
        const couponDiscountEl = document.getElementById('posCouponDiscountAmt');
        if (couponDiscountEl) couponDiscountEl.textContent = `-${formatCurrency(couponDiscount)}`;

        updateChange();
        const hasItems = state.cart.length > 0 && !!state.session;
        if (checkoutBtn) checkoutBtn.disabled = !hasItems;
    }

    function updateChange() {
        const { grandTotal } = cartTotals();
        const paid = parseFloat(amountPaidInput?.value ?? 0) || 0;
        const change = paid - grandTotal;
        if (changeDisplay) {
            if (paid > 0) {
                changeDisplay.textContent = change >= 0
                    ? `${t('pos.change','Change')}: ${formatCurrency(change)}`
                    : `${t('pos.remaining','Remaining')}: ${formatCurrency(Math.abs(change))}`;
                changeDisplay.style.color = change >= 0
                    ? 'var(--success-color, #10b981)'
                    : 'var(--danger-color, #ef4444)';
            } else {
                changeDisplay.textContent = '';
            }
        }
    }

    // ─────────────────────────────────────────────
    // Checkout
    // ─────────────────────────────────────────────
    async function checkout() {
        if (!state.session || !state.cart.length) return;
        const { grandTotal, couponDiscount } = cartTotals();
        const paid = parseFloat(amountPaidInput?.value ?? 0) || 0;
        const discount = parseFloat(discountInput?.value ?? 0) || 0;

        if (state.paymentMethod === 'cash' && paid < grandTotal) {
            showAlert(t('pos.error.insufficient_payment', 'Amount paid is less than the total'), 'error');
            return;
        }

        checkoutBtn && (checkoutBtn.disabled = true);
        try {
            const res = await apiPost(API.pos, {
                action:            'create_order',
                session_id:        state.session.id,
                entity_id:         state.session.entity_id,
                payment_method:    state.paymentMethod,
                amount_paid:       paid,
                discount_amount:   discount + couponDiscount,
                coupon_code:       state.activeCoupon?.discount?.code || null,
                coupon_discount:   couponDiscount,
                cashier_user_id:   state.session.cashier_user_id,
                items: state.cart.map(i => ({
                    product_id:   i.product_id,
                    product_name: i.product_name,
                    sku:          i.sku,
                    quantity:     i.qty,
                    unit_price:   i.unit_price,
                    sale_price:   i.sale_price,
                    tax_rate:     i.tax_rate,
                })),
            });

            showReceiptModal(res, paid);
            // Refresh session totals silently
            loadCurrentSession().then(updateSessionBar);

        } catch (err) {
            showAlert(err.message, 'error');
            if (checkoutBtn) checkoutBtn.disabled = false;
        }
    }

    // ─────────────────────────────────────────────
    // Receipt Modal
    // ─────────────────────────────────────────────
    function showReceiptModal(orderRes, paid) {
        const { grandTotal, couponDiscount } = cartTotals();
        const change = Math.max(0, paid - grandTotal);
        const now = new Date().toLocaleString(state.lang === 'ar' ? 'ar-SA' : 'en');

        let itemsHtml = state.cart.map(i => `
            <div class="receipt-row">
                <span>${escHtml(i.product_name)} ×${i.qty}</span>
                <span>${formatCurrency(i.sale_price * i.qty)}</span>
            </div>`).join('');

        const totals = cartTotals();
        showModal(`
            <h3>🧾 ${t('pos.receipt.title','Receipt')}</h3>
            <div class="pos-receipt">
                <div class="receipt-center"><strong>${escHtml(state.session?.store_name || '')}</strong></div>
                <div class="receipt-center" style="font-size:.75rem;color:var(--text-muted,#64748b)">${escHtml(now)}</div>
                <div class="receipt-center" style="font-size:.75rem">Order: ${escHtml(orderRes.order_number || '')}</div>
                <hr class="receipt-divider">
                ${itemsHtml}
                <hr class="receipt-divider">
                <div class="receipt-row"><span>${t('pos.subtotal','Subtotal')}</span><span>${formatCurrency(totals.sub)}</span></div>
                ${totals.tax > 0 ? `<div class="receipt-row"><span>${t('pos.tax','Tax')}</span><span>${formatCurrency(totals.tax)}</span></div>` : ''}
                ${totals.discount > 0 ? `<div class="receipt-row"><span>${t('pos.discount','Discount')}</span><span>-${formatCurrency(totals.discount)}</span></div>` : ''}
                ${couponDiscount > 0 ? `<div class="receipt-row"><span>${t('pos.coupon.label','Coupon')} (${escHtml(state.activeCoupon?.discount?.code||'')})</span><span>-${formatCurrency(couponDiscount)}</span></div>` : ''}
                <hr class="receipt-divider">
                <div class="receipt-row total-row"><span>${t('pos.total','Total')}</span><span>${formatCurrency(grandTotal)}</span></div>
                <div class="receipt-row"><span>${t('pos.paid','Paid')}</span><span>${formatCurrency(paid)}</span></div>
                ${change > 0 ? `<div class="receipt-row"><span>${t('pos.change','Change')}</span><span>${formatCurrency(change)}</span></div>` : ''}
                <hr class="receipt-divider">
                <div class="receipt-center" style="color:var(--success-color,#10b981)">✓ ${t('pos.receipt.thank_you','Thank you!')}</div>
            </div>
            <div class="btn-group">
                <button class="btn btn-outline" onclick="window.print()">🖨 ${t('pos.receipt.print','Print')}</button>
                <button class="btn btn-primary" onclick="posNewSale()">+ ${t('pos.new_sale','New Sale')}</button>
            </div>
        `);
    }

    window.posNewSale = function () {
        clearCart();
        closeModal();
        if (discountInput) discountInput.value = '';
        if (amountPaidInput) amountPaidInput.value = '';
        if (changeDisplay) changeDisplay.textContent = '';
        if (checkoutBtn) checkoutBtn.disabled = true;
        clearCoupon();
    };

    // ─────────────────────────────────────────────
    // Modal
    // ─────────────────────────────────────────────
    function showModal(html) {
        if (!modalBackdrop) return;
        const box = modalBackdrop.querySelector('.pos-modal');
        if (box) box.innerHTML = html;
        modalBackdrop.style.display = 'flex';
    }

    function closeModal() {
        if (!modalBackdrop) return;
        modalBackdrop.style.display = 'none';
    }

    window.posCloseModal = closeModal;

    // ─────────────────────────────────────────────
    // Sales History
    // ─────────────────────────────────────────────
    async function loadSalesHistory() {
        if (!state.session) return;
        const content = document.getElementById('posHistoryContent');
        if (content) content.innerHTML = '<div class="pos-loading"><div class="pos-spinner"></div></div>';
        try {
            const params = { action: 'session_orders', session_id: state.session.id };
            const f = state.historyFilters;
            if (f.dateFrom) params.date_from = f.dateFrom;
            if (f.dateTo)   params.date_to   = f.dateTo;
            if (f.paymentMethod) params.payment_method = f.paymentMethod;
            const res = await apiGet(API.pos, params);
            state.salesHistory = res.orders ?? [];
            renderSalesHistory();
        } catch (err) {
            if (content) content.innerHTML = `<p style="color:var(--danger-color,#ef4444)">${escHtml(err.message)}</p>`;
        }
    }

    /** Apply client-side filters on top of (already-fetched) salesHistory */
    function applyHistoryFilters(orders) {
        const f = state.historyFilters;
        let result = orders;
        if (f.dateFrom) {
            const from = new Date(f.dateFrom + 'T00:00:00');
            result = result.filter(o => new Date(o.created_at) >= from);
        }
        if (f.dateTo) {
            const to = new Date(f.dateTo + 'T23:59:59');
            result = result.filter(o => new Date(o.created_at) <= to);
        }
        if (f.paymentMethod) {
            result = result.filter(o =>
                (o.payment_method || '').toLowerCase().includes(f.paymentMethod.toLowerCase())
            );
        }
        return result;
    }

    function renderSalesHistory() {
        const content = document.getElementById('posHistoryContent');
        if (!content) return;
        const orders = applyHistoryFilters(state.salesHistory);
        if (!orders.length) {
            content.innerHTML = `<p style="color:var(--text-muted,#64748b);padding:20px;text-align:center">${t('pos.history.empty','No sales in this session yet')}</p>`;
            return;
        }
        const totalSales = orders.reduce((s, o) => s + parseFloat(o.grand_total || 0), 0);
        content.innerHTML = `
            <div style="margin-bottom:16px;padding:12px 16px;background:var(--background-secondary,#1e293b);border-radius:10px;display:flex;gap:24px;flex-wrap:wrap">
                <div><span style="color:var(--text-secondary,#94a3b8);font-size:.82rem">${t('pos.history.total_orders','Total Orders')}</span>
                     <strong style="display:block;font-size:1.3rem">${orders.length}</strong></div>
                <div><span style="color:var(--text-secondary,#94a3b8);font-size:.82rem">${t('pos.history.total_sales','Total Sales')}</span>
                     <strong style="display:block;font-size:1.3rem;color:#10b981">${formatCurrency(totalSales)}</strong></div>
            </div>
            <div style="overflow-x:auto">
            <table class="pos-history-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>${t('pos.history.order_number','Order #')}</th>
                        <th>${t('pos.history.amount','Amount')}</th>
                        <th>${t('pos.reports.payment_method','Payment')}</th>
                        <th>${t('pos.history.status','Status')}</th>
                        <th>${t('pos.history.time','Time')}</th>
                        <th>${t('pos.history.customer','Customer')}</th>
                    </tr>
                </thead>
                <tbody>
                    ${orders.map((o, i) => {
                        const t_ = new Date(o.created_at).toLocaleString(state.lang === 'ar' ? 'ar-SA' : 'en');
                        const badgeCls = o.payment_status === 'paid' ? 'badge-paid' : 'badge-pending';
                        return `<tr>
                            <td>${i+1}</td>
                            <td style="font-family:monospace">${escHtml(o.order_number || String(o.id))}</td>
                            <td><strong>${formatCurrency(o.grand_total)}</strong></td>
                            <td>${escHtml(o.payment_method || '—')}</td>
                            <td><span class="${badgeCls}">${escHtml(o.payment_status || 'paid')}</span></td>
                            <td>${escHtml(t_)}</td>
                            <td>${escHtml(o.customer_name || '—')}</td>
                        </tr>`;
                    }).join('')}
                </tbody>
            </table>
            </div>
        `;
    }

    // ─────────────────────────────────────────────
    // Reports (admin / manage_pos)
    // ─────────────────────────────────────────────
    async function loadReports() {
        if (!state.session) return;
        const content = document.getElementById('posReportsContent');
        if (content) content.innerHTML = '<div class="pos-loading"><div class="pos-spinner"></div></div>';
        try {
            const params = { action: 'session_orders', session_id: state.session.id };
            const f = state.reportsFilters;
            if (f.dateFrom) params.date_from = f.dateFrom;
            if (f.dateTo)   params.date_to   = f.dateTo;
            if (f.paymentMethod) params.payment_method = f.paymentMethod;
            const res = await apiGet(API.pos, params);
            state.reportsOrders = res.orders ?? [];
        } catch { /* ignore */ }
        renderReports();
    }

    /** Apply client-side filters on top of (already-fetched) salesHistory for reports */
    function applyReportsFilters(orders) {
        const f = state.reportsFilters;
        let result = orders;
        if (f.dateFrom) {
            const from = new Date(f.dateFrom + 'T00:00:00');
            result = result.filter(o => new Date(o.created_at) >= from);
        }
        if (f.dateTo) {
            const to = new Date(f.dateTo + 'T23:59:59');
            result = result.filter(o => new Date(o.created_at) <= to);
        }
        if (f.paymentMethod) {
            result = result.filter(o =>
                (o.payment_method || '').toLowerCase().includes(f.paymentMethod.toLowerCase())
            );
        }
        return result;
    }

    function renderReports() {
        const content = document.getElementById('posReportsContent');
        if (!content) return;
        const orders  = applyReportsFilters(state.reportsOrders);
        const s       = state.session;

        const totalOrders = orders.length;
        // Single-pass totals calculation
        let totalSales = 0, cashSales = 0, cardSales = 0;
        orders.forEach(o => {
            const amt = parseFloat(o.grand_total || 0);
            totalSales += amt;
            const pm = (o.payment_method || '').toLowerCase();
            if (pm.includes('cash'))  cashSales += amt;
            if (pm.includes('card'))  cardSales += amt;
        });
        const avgOrder = totalOrders > 0 ? (totalSales / totalOrders) : 0;

        content.innerHTML = `
            <div class="pos-report-cards">
                <div class="pos-report-card">
                    <div class="rc-label">${t('pos.reports.orders','Orders')}</div>
                    <div class="rc-value blue">${totalOrders}</div>
                </div>
                <div class="pos-report-card">
                    <div class="rc-label">${t('pos.reports.total_sales','Total Sales')}</div>
                    <div class="rc-value green">${formatCurrency(totalSales)}</div>
                </div>
                <div class="pos-report-card">
                    <div class="rc-label">${t('pos.reports.avg_order','Avg. Order')}</div>
                    <div class="rc-value">${formatCurrency(avgOrder)}</div>
                </div>
                <div class="pos-report-card">
                    <div class="rc-label">${t('pos.reports.cash','Cash Total')}</div>
                    <div class="rc-value">${formatCurrency(cashSales || s?.total_cash || 0)}</div>
                </div>
                <div class="pos-report-card">
                    <div class="rc-label">${t('pos.reports.card','Card Total')}</div>
                    <div class="rc-value">${formatCurrency(cardSales || s?.total_card || 0)}</div>
                </div>
                <div class="pos-report-card">
                    <div class="rc-label">${t('pos.reports.opening_balance','Opening Balance')}</div>
                    <div class="rc-value">${formatCurrency(s?.opening_balance || 0)}</div>
                </div>
            </div>
            ${totalOrders > 0 ? `
            <h3 style="color:var(--text-primary,#e2e8f0);margin:20px 0 12px;font-size:1rem">${t('pos.reports.orders_breakdown','Orders Breakdown')}</h3>
            <div style="overflow-x:auto">
            <table class="pos-history-table" id="posReportsTable">
                <thead><tr>
                    <th>${t('pos.history.order_number','Order #')}</th>
                    <th>${t('pos.history.amount','Amount')}</th>
                    <th>${t('pos.reports.payment_method','Payment')}</th>
                    <th>${t('pos.history.time','Time')}</th>
                    <th>${t('pos.history.customer','Customer')}</th>
                </tr></thead>
                <tbody>
                    ${orders.map(o => {
                        const tm = new Date(o.created_at).toLocaleString(state.lang === 'ar' ? 'ar-SA' : 'en');
                        return `<tr>
                            <td style="font-family:monospace">${escHtml(o.order_number || String(o.id))}</td>
                            <td><strong>${formatCurrency(o.grand_total)}</strong></td>
                            <td>${escHtml(o.payment_method || '—')}</td>
                            <td>${escHtml(tm)}</td>
                            <td>${escHtml(o.customer_name || '—')}</td>
                        </tr>`;
                    }).join('')}
                </tbody>
            </table>
            </div>` : `<p style="color:var(--text-muted,#64748b);padding:20px;text-align:center">${t('pos.reports.no_data','No data for selected filters')}</p>`}
        `;
    }

    // ─────────────────────────────────────────────
    // Open Session Form
    // ─────────────────────────────────────────────
    function bindOpenSessionForm() {
        const form = document.getElementById('posOpenSessionForm');
        if (!form) return;
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const entityId      = parseInt(form.querySelector('[name=entity_id]')?.value ?? 0, 10);
            const balance       = parseFloat(form.querySelector('[name=opening_balance]')?.value ?? 0) || 0;
            const cashierUserId = parseInt(form.querySelector('[name=cashier_user_id]')?.value ?? 0, 10) || null;
            if (!entityId) {
                showAlert(t('pos.error.entity_required', 'Please select an entity/branch'), 'error');
                return;
            }
            await openSession(entityId, balance, cashierUserId);
        });
    }

    // ─────────────────────────────────────────────
    // Entities select (load in open session form)
    // ─────────────────────────────────────────────
    async function loadEntitiesSelect() {
        const wrapper = document.getElementById('posEntitySelectWrapper');
        const sel     = document.getElementById('posEntitySelect');

        // Non-super-admin with a preset entity_id: skip the entity selector entirely
        if (!CFG.IS_SUPER_ADMIN && CFG.ENTITY_ID) {
            state.entityId = CFG.ENTITY_ID;
            if (wrapper) wrapper.style.display = 'none';
            if (sel) {
                sel.innerHTML = `<option value="${CFG.ENTITY_ID}" selected></option>`;
                sel.removeAttribute('required');
            }
            return;
        }

        if (!sel) return;

        try {
            const res = await apiGet(API.entities, { limit: 200 });
            let entities = [];
            if (res.data && Array.isArray(res.data.items)) {
                entities = res.data.items;
            } else if (Array.isArray(res.items)) {
                entities = res.items;
            }

            sel.innerHTML = `<option value="">${t('pos.select_entity','Select Entity/Branch')}</option>` +
                entities.map(e => `<option value="${e.id}">${escHtml(e.store_name || '')}</option>`).join('');

            // Build entity→tenant map so openSession can use correct tenant
            entities.forEach(e => {
                if (e.id && e.tenant_id) state.entityTenantMap[e.id] = parseInt(e.tenant_id, 10);
            });

            if (entities.length === 1) {
                sel.value = entities[0].id;
                state.entityId = entities[0].id;
            } else if (CFG.ENTITY_ID) {
                sel.value = CFG.ENTITY_ID;
            }

            if (CFG.IS_SUPER_ADMIN) {
                addEntitySearch(sel, entities);
            }
        } catch {
            // leave empty
        }
    }

    function addEntitySearch(sel, entities) {
        const wrapper = sel.parentElement;
        if (!wrapper || wrapper.querySelector('.pos-entity-search')) return;

        const searchBox = document.createElement('input');
        searchBox.type = 'text';
        searchBox.className = 'form-control pos-entity-search';
        searchBox.placeholder = t('pos.search_entity', 'Search entity…');
        searchBox.style.cssText = 'margin-bottom:6px;';
        wrapper.insertBefore(searchBox, sel);

        searchBox.addEventListener('input', () => {
            const q = searchBox.value.toLowerCase();
            Array.from(sel.options).forEach(opt => {
                if (!opt.value) return;
                opt.hidden = !opt.text.toLowerCase().includes(q);
            });
        });
    }

    /**
     * Render entity/tenant navigation tabs in the context nav bar.
     * Called after session is opened so the cashier can quickly switch
     * entity context (reloads categories and products for the selected entity).
     */
    async function renderEntityContextNav() {
        if (!contextNav || !entityNavTabs) return;

        // Only show context nav when there is an active session
        if (!state.session) { contextNav.style.display = 'none'; return; }

        try {
            const res = await apiGet(API.entities, { limit: 200 });
            let entities = [];
            if (res.data && Array.isArray(res.data.items)) entities = res.data.items;
            else if (Array.isArray(res.items)) entities = res.items;

            // Populate entity→tenant map (may already be set from open-session form)
            entities.forEach(e => {
                if (e.id && e.tenant_id) state.entityTenantMap[e.id] = parseInt(e.tenant_id, 10);
            });

            // Build nav tabs — always show at least the current entity
            if (!entities.length && state.session) {
                entities = [{
                    id: state.session.entity_id,
                    store_name: state.session.store_name || String(state.session.entity_id),
                    tenant_id: state.session.tenant_id,
                }];
            }

            if (!entities.length) { contextNav.style.display = 'none'; return; }

            entityNavTabs.innerHTML = entities.map(e => {
                const active = parseInt(e.id, 10) === parseInt(state.entityId, 10);
                return `<button class="pos-entity-nav-tab${active ? ' active' : ''}" data-entity-id="${e.id}" data-tenant-id="${e.tenant_id || ''}">${escHtml(e.store_name || String(e.id))}</button>`;
            }).join('');

            entityNavTabs.querySelectorAll('.pos-entity-nav-tab').forEach(btn => {
                btn.addEventListener('click', async () => {
                    const newEntityId = parseInt(btn.dataset.entityId, 10);
                    if (newEntityId === parseInt(state.entityId, 10)) return; // already active
                    state.entityId = newEntityId;
                    const tid = parseInt(btn.dataset.tenantId, 10) || state.entityTenantMap[newEntityId] || state.tenantId;
                    if (tid) state.tenantId = tid;
                    // Mark active
                    entityNavTabs.querySelectorAll('.pos-entity-nav-tab').forEach(b => b.classList.remove('active'));
                    btn.classList.add('active');
                    // Reset category/product state
                    state.categoryTree = [];
                    state.categoryProductIds = {};
                    state.parentCatId = null;
                    state.subCatId = null;
                    // Reload categories and products for the new entity
                    await loadCategories();
                    await loadProducts();
                });
            });

            contextNav.style.display = 'flex';
        } catch (err) {
            error_log('[POS] renderEntityContextNav: ' + (err && err.message ? err.message : String(err)));
            contextNav.style.display = 'none';
        }
    }

    // ─────────────────────────────────────────────
    // Utilities
    // ─────────────────────────────────────────────
    function formatCurrency(val, currencyCode) {
        const n = parseFloat(val) || 0;
        return n.toFixed(2) + ' ' + (currencyCode || state.currency || CFG.CURRENCY || 'SAR');
    }

    // ─────────────────────────────────────────────
    // CSV / Excel Export
    // ─────────────────────────────────────────────

    /** Build the rows array for export (shared by CSV and Excel) */
    function buildExportData(orders) {
        const headers = [
            t('pos.history.order_number', 'Order #'),
            t('pos.history.amount', 'Amount'),
            t('pos.reports.payment_method', 'Payment Method'),
            t('pos.history.status', 'Status'),
            t('pos.reports.session_date', 'Date'),
            t('pos.history.customer', 'Customer'),
            t('pos.subtotal', 'Subtotal'),
            t('pos.tax', 'Tax'),
            t('pos.discount', 'Discount'),
        ];

        const rows = orders.map(o => [
            o.order_number || String(o.id),
            parseFloat(o.grand_total || 0).toFixed(2),
            o.payment_method || '',
            o.payment_status || 'paid',
            new Date(o.created_at).toLocaleString(state.lang === 'ar' ? 'ar-SA' : 'en'),
            o.customer_name || '',
            parseFloat(o.subtotal || o.grand_total || 0).toFixed(2),
            parseFloat(o.tax_amount || 0).toFixed(2),
            parseFloat(o.discount_amount || 0).toFixed(2),
        ]);
        return { headers, rows };
    }

    function exportSalesCSV(ordersOverride) {
        const orders = ordersOverride || applyHistoryFilters(state.salesHistory);
        if (!orders.length) {
            showAlert(t('pos.reports.no_data', 'No data to export'), 'error');
            return;
        }
        const { headers, rows } = buildExportData(orders);
        const csvContent = [headers, ...rows]
            .map(row => row.map(cell => `"${String(cell).replace(/"/g, '""')}"`).join(','))
            .join('\n');

        const bom = '\uFEFF'; // UTF-8 BOM for Excel Arabic support
        const blob = new Blob([bom + csvContent], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        const dateSource = state.session ? new Date(state.session.opened_at) : new Date();
        const sessionDate = dateSource.toISOString().split('T')[0];
        a.download = `pos-sales-${sessionDate}.csv`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
        showAlert(t('pos.reports.export_done', 'File exported successfully'), 'success', 3000);
    }

    /** Export as .xls (HTML table with Excel MIME type – opens natively in Excel) */
    function exportSalesExcel(ordersOverride) {
        const orders = ordersOverride || applyHistoryFilters(state.salesHistory);
        if (!orders.length) {
            showAlert(t('pos.reports.no_data', 'No data to export'), 'error');
            return;
        }
        const { headers, rows } = buildExportData(orders);
        const sessionTitle = t('pos.reports.orders_breakdown', 'Orders Breakdown');
        const sessionDate  = state.session
            ? new Date(state.session.opened_at).toLocaleDateString(state.lang === 'ar' ? 'ar-SA' : 'en')
            : new Date().toLocaleDateString();

        const th = headers.map(h => `<th style="background:#1e293b;color:#e2e8f0;border:1px solid #334155;padding:6px 10px">${escHtml(String(h))}</th>`).join('');
        const tbody = rows.map(row =>
            '<tr>' + row.map(cell => `<td style="border:1px solid #334155;padding:5px 10px">${escHtml(String(cell))}</td>`).join('') + '</tr>'
        ).join('');

        const html = `<html xmlns:o="urn:schemas-microsoft-com:office:office"
                           xmlns:x="urn:schemas-microsoft-com:office:excel"
                           xmlns="http://www.w3.org/TR/REC-html40">
<head><meta charset="utf-8">
<style>
  table { border-collapse: collapse; font-family: Arial, sans-serif; direction: ${state.dir}; }
  th { background: #1e293b; color: #e2e8f0; }
  td, th { border: 1px solid #334155; padding: 5px 10px; white-space: nowrap; }
  h2 { font-family: Arial, sans-serif; color: #1e293b; }
</style>
</head><body>
<h2>${escHtml(sessionTitle)} – ${escHtml(sessionDate)}</h2>
<table><thead><tr>${th}</tr></thead><tbody>${tbody}</tbody></table>
</body></html>`;

        const bom  = '\uFEFF';
        const blob = new Blob([bom + html], { type: 'application/vnd.ms-excel;charset=utf-8;' });
        const url  = URL.createObjectURL(blob);
        const a    = document.createElement('a');
        a.href     = url;
        const fileDate = (state.session ? new Date(state.session.opened_at) : new Date()).toISOString().split('T')[0];
        a.download = `pos-sales-${fileDate}.xls`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
        showAlert(t('pos.reports.export_done', 'File exported successfully'), 'success', 3000);
    }

    // ─────────────────────────────────────────────
    // Event Bindings
    // ─────────────────────────────────────────────
    function bindEvents() {
        // Search (also used as barcode buffer display)
        searchInput?.addEventListener('input', () => {
            if (state.barcodeMode) return; // handled by keydown
            state.searchQuery = searchInput.value;
            renderProducts();
        });

        // Payment methods
        payMethodBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                state.paymentMethod = btn.dataset.method;
                payMethodBtns.forEach(b => b.classList.remove('selected'));
                btn.classList.add('selected');
                updateChange();
            });
        });

        // Amount paid → update change
        amountPaidInput?.addEventListener('input', updateChange);

        // Discount → update totals (coupon binding is added below)

        // Checkout
        checkoutBtn?.addEventListener('click', checkout);

        // Clear cart
        clearBtn?.addEventListener('click', () => {
            if (state.cart.length && confirm(t('pos.clear_confirm', 'Clear the cart?'))) {
                clearCart();
            }
        });

        // Close modal on backdrop click
        modalBackdrop?.addEventListener('click', (e) => {
            if (e.target === modalBackdrop) closeModal();
        });

        // Tab navigation
        tabNav?.querySelectorAll('.pos-tab-btn').forEach(btn => {
            btn.addEventListener('click', () => switchTab(btn.dataset.tab));
        });

        // Barcode toggle button
        barcodeBtn?.addEventListener('click', toggleBarcodeMode);

        // Barcode banner: switch to camera
        barcodeCameraBtn?.addEventListener('click', () => {
            startCameraScanner();
        });

        // Barcode close banner
        barcodeClose?.addEventListener('click', () => {
            state.barcodeMode = false;
            barcodeBtn?.classList.remove('active');
            if (barcodeBanner) barcodeBanner.style.display = 'none';
            if (searchInput) { searchInput.value = ''; state.searchQuery = ''; }
            state.barcodeBuffer = '';
            renderProducts();
        });

        // Camera scanner stop / close
        cameraStopBtn?.addEventListener('click', stopCameraScanner);
        cameraCloseBtn?.addEventListener('click', stopCameraScanner);

        // History refresh
        document.getElementById('posRefreshHistory')?.addEventListener('click', loadSalesHistory);
        // History export CSV
        document.getElementById('posExportHistoryCSV')?.addEventListener('click', () =>
            exportSalesCSV(applyHistoryFilters(state.salesHistory))
        );
        // History export Excel
        document.getElementById('posExportHistoryExcel')?.addEventListener('click', () =>
            exportSalesExcel(applyHistoryFilters(state.salesHistory))
        );
        // History filters
        document.getElementById('posApplyHistoryFilter')?.addEventListener('click', () => {
            state.historyFilters.dateFrom      = document.getElementById('posHistoryFrom')?.value || '';
            state.historyFilters.dateTo        = document.getElementById('posHistoryTo')?.value || '';
            state.historyFilters.paymentMethod = document.getElementById('posHistoryPayMethod')?.value || '';
            loadSalesHistory();
        });
        document.getElementById('posResetHistoryFilter')?.addEventListener('click', () => {
            state.historyFilters = { dateFrom: '', dateTo: '', paymentMethod: '' };
            const fromEl = document.getElementById('posHistoryFrom');
            const toEl   = document.getElementById('posHistoryTo');
            const pmEl   = document.getElementById('posHistoryPayMethod');
            if (fromEl) fromEl.value = '';
            if (toEl)   toEl.value   = '';
            if (pmEl)   pmEl.value   = '';
            loadSalesHistory();
        });

        // Reports refresh
        document.getElementById('posRefreshReports')?.addEventListener('click', () => {
            state.reportsOrders = [];
            loadReports();
        });
        // Reports export CSV
        document.getElementById('posExportCSV')?.addEventListener('click', () =>
            exportSalesCSV(applyReportsFilters(state.reportsOrders))
        );
        // Reports export Excel
        document.getElementById('posExportExcel')?.addEventListener('click', () =>
            exportSalesExcel(applyReportsFilters(state.reportsOrders))
        );
        // Reports filters
        document.getElementById('posApplyReportsFilter')?.addEventListener('click', () => {
            state.reportsFilters.dateFrom      = document.getElementById('posReportsFrom')?.value || '';
            state.reportsFilters.dateTo        = document.getElementById('posReportsTo')?.value || '';
            state.reportsFilters.paymentMethod = document.getElementById('posReportsPayMethod')?.value || '';
            loadReports();
        });
        document.getElementById('posResetReportsFilter')?.addEventListener('click', () => {
            state.reportsFilters = { dateFrom: '', dateTo: '', paymentMethod: '' };
            const fromEl = document.getElementById('posReportsFrom');
            const toEl   = document.getElementById('posReportsTo');
            const pmEl   = document.getElementById('posReportsPayMethod');
            if (fromEl) fromEl.value = '';
            if (toEl)   toEl.value   = '';
            if (pmEl)   pmEl.value   = '';
            loadReports();
        });

        // Coupon
        applyCouponBtn?.addEventListener('click', applyCoupon);
        clearCouponBtn?.addEventListener('click', clearCoupon);
        couponInput?.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') { e.preventDefault(); applyCoupon(); }
        });
        discountInput?.addEventListener('input', () => {
            updateTotals();
        });
    }

    // ─────────────────────────────────────────────
    // Init
    // ─────────────────────────────────────────────
    async function init() {
        cacheElements();
        await loadTranslations();
        bindEvents();
        bindOpenSessionForm();
        initBarcodeHardware();

        // Load entities for the open session form
        await loadEntitiesSelect();

        // Check if a session is already open
        await loadCurrentSession();
        updateSessionBar();

        if (state.session) {
            state.entityId = state.session.entity_id;
            const resolvedTenant = resolveEntityTenantId(state.session.entity_id, state.session);
            if (resolvedTenant) state.tenantId = resolvedTenant;
            await Promise.all([loadCategories(), loadDiscounts()]);
            showMainLayout();
            await loadProducts();
        } else {
            showOpenSessionView();
        }

        // Set default payment method
        const defaultBtn = document.querySelector('.pos-pay-method-btn[data-method="cash"]');
        defaultBtn?.classList.add('selected');
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();