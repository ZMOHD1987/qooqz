(function () {
    'use strict';

    /**
     * /admin/assets/js/pages/carts.js
     * Cart Management Module
     * Handles carts, cart items, and cart events with CRUD, filtering, pagination
     */

    var CFG = window.CARTS_CONFIG || {};
    var API_CARTS      = CFG.apiCarts || '/api/carts';
    var API_CART_ITEMS  = CFG.apiCartItems || '/api/cart_items';
    var API_CART_EVENTS = CFG.apiCartEvents || '/api/cart_events';
    var TENANT_ID      = CFG.tenantId || 1;
    var PER_PAGE       = CFG.itemsPerPage || 25;
    var CAN_EDIT       = CFG.canEdit !== false;
    var CAN_DELETE     = CFG.canDelete !== false;

    // State
    var cartsPage   = 1;
    var itemsPage   = 1;
    var eventsPage  = 1;
    var cartsFilters = {};
    var itemsFilters = {};
    var eventsFilters = {};
    var activeTab = 'carts';

    // ════════════════════════════════════════════
    // UTILITIES
    // ════════════════════════════════════════════
    function esc(s) {
        if (s === null || s === undefined) return '';
        var d = document.createElement('div');
        d.textContent = String(s);
        return d.innerHTML;
    }

    function showNotification(msg, type) {
        var n = document.createElement('div');
        n.className = 'notification notification-' + (type || 'info');
        n.textContent = msg;
        document.body.appendChild(n);
        setTimeout(function () { n.style.opacity = '0'; setTimeout(function () { n.remove(); }, 300); }, 3000);
    }

    function formatDate(d) {
        if (!d) return '-';
        try { return new Date(d).toLocaleString(); } catch (e) { return d; }
    }

    function formatMoney(v, cur) {
        if (v === null || v === undefined) return '-';
        var num = parseFloat(v);
        return isNaN(num) ? v : num.toFixed(2) + (cur ? ' ' + cur : '');
    }

    function statusBadge(status) {
        var cls = 'badge-default';
        if (status === 'active') cls = 'badge-success';
        else if (status === 'abandoned') cls = 'badge-warning';
        else if (status === 'converted') cls = 'badge-info';
        else if (status === 'expired') cls = 'badge-danger';
        return '<span class="badge ' + cls + '">' + esc(status) + '</span>';
    }

    function truncateJson(val) {
        if (!val) return '-';
        var s = typeof val === 'string' ? val : JSON.stringify(val);
        return s.length > 60 ? esc(s.substring(0, 57)) + '...' : esc(s);
    }

    function apiUrl(base, params) {
        var qs = ['tenant_id=' + TENANT_ID];
        if (params) {
            for (var k in params) {
                if (params[k] !== null && params[k] !== undefined && params[k] !== '') {
                    qs.push(encodeURIComponent(k) + '=' + encodeURIComponent(params[k]));
                }
            }
        }
        return base + '?' + qs.join('&');
    }

    // ════════════════════════════════════════════
    // TABS
    // ════════════════════════════════════════════
    function initTabs() {
        var btns = document.querySelectorAll('.tabs-nav .tab-btn');
        btns.forEach(function (btn) {
            btn.addEventListener('click', function () {
                var tab = btn.getAttribute('data-tab');
                btns.forEach(function (b) { b.classList.remove('active'); });
                btn.classList.add('active');

                document.querySelectorAll('.tab-content').forEach(function (tc) { tc.style.display = 'none'; });
                var target = document.getElementById('tab-' + tab);
                if (target) target.style.display = '';

                activeTab = tab;
                if (tab === 'carts') loadCarts();
                else if (tab === 'items') loadItems();
                else if (tab === 'events') loadEvents();
            });
        });
    }

    // ════════════════════════════════════════════
    // CARTS
    // ════════════════════════════════════════════
    function loadCarts() {
        var tbody = document.getElementById('cartsBody');
        if (!tbody) return;
        tbody.innerHTML = '<tr><td colspan="10" class="text-center">Loading...</td></tr>';

        var params = {
            page: cartsPage,
            limit: PER_PAGE,
            order_by: 'id',
            order_dir: 'DESC'
        };
        if (cartsFilters.status) params.status = cartsFilters.status;
        if (cartsFilters.entity_id) params.entity_id = cartsFilters.entity_id;
        if (cartsFilters.user_id) params.user_id = cartsFilters.user_id;

        fetch(apiUrl(API_CARTS, params))
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (!d.success) throw new Error(d.message || 'Failed to load carts');
                var data = d.data || {};
                var items = data.items || [];
                var meta = data.meta || {};

                renderCartsTable(items);
                renderPagination('cartsPagination', 'cartsPaginationInfo', meta, function (p) { cartsPage = p; loadCarts(); });
                updateStats(items, meta.total || items.length);
            })
            .catch(function (e) {
                tbody.innerHTML = '<tr><td colspan="10" class="text-center text-danger">' + esc(e.message) + '</td></tr>';
            });
    }

    function renderCartsTable(items) {
        var tbody = document.getElementById('cartsBody');
        if (!items.length) {
            tbody.innerHTML = '<tr><td colspan="10" class="text-center">No carts found</td></tr>';
            return;
        }
        var html = '';
        items.forEach(function (c) {
            html += '<tr>';
            html += '<td>' + esc(c.id) + '</td>';
            html += '<td>' + esc(c.entity_id || c.entity_name || '-') + '</td>';
            html += '<td>' + esc(c.user_id || c.session_id || '-') + '</td>';
            html += '<td>' + esc(c.total_items) + '</td>';
            html += '<td>' + formatMoney(c.total_amount, c.currency_code) + '</td>';
            html += '<td>' + esc(c.currency_code || 'SAR') + '</td>';
            html += '<td>' + statusBadge(c.status) + '</td>';
            html += '<td>' + esc(c.coupon_code || '-') + '</td>';
            html += '<td>' + formatDate(c.last_activity_at) + '</td>';
            html += '<td class="actions-cell">';
            html += '<button class="btn btn-sm btn-info btn-view-cart" data-id="' + c.id + '" title="View"><i class="fas fa-eye"></i></button>';
            if (CAN_EDIT) html += ' <button class="btn btn-sm btn-warning btn-edit-cart" data-id="' + c.id + '" title="Edit"><i class="fas fa-edit"></i></button>';
            if (CAN_DELETE) html += ' <button class="btn btn-sm btn-danger btn-delete-cart" data-id="' + c.id + '" title="Delete"><i class="fas fa-trash"></i></button>';
            html += '</td>';
            html += '</tr>';
        });
        tbody.innerHTML = html;
        bindCartActions();
    }

    function bindCartActions() {
        document.querySelectorAll('.btn-view-cart').forEach(function (btn) {
            btn.addEventListener('click', function () { viewCart(parseInt(btn.getAttribute('data-id'))); });
        });
        document.querySelectorAll('.btn-edit-cart').forEach(function (btn) {
            btn.addEventListener('click', function () { editCart(parseInt(btn.getAttribute('data-id'))); });
        });
        document.querySelectorAll('.btn-delete-cart').forEach(function (btn) {
            btn.addEventListener('click', function () { deleteCart(parseInt(btn.getAttribute('data-id'))); });
        });
    }

    function updateStats(items, total) {
        var active = 0, abandoned = 0, converted = 0;
        // Stats are approximate from visible page; for real stats we'd need separate API
        var el = document.getElementById('statTotal');
        if (el) el.textContent = total || 0;
    }

    function viewCart(id) {
        var modal = document.getElementById('cartDetailModal');
        if (!modal) return;
        modal.style.display = 'block';
        document.getElementById('cartDetailTitle').textContent = 'Cart #' + id;
        document.getElementById('cartInfoFields').innerHTML = 'Loading...';
        document.getElementById('cartAmountFields').innerHTML = '';
        document.getElementById('cartDetailItemsBody').innerHTML = '<tr><td colspan="5" class="text-center">Loading...</td></tr>';
        document.getElementById('cartDetailEventsBody').innerHTML = '<tr><td colspan="4" class="text-center">Loading...</td></tr>';

        // Load cart details
        fetch(apiUrl(API_CARTS, { id: id }))
            .then(function (r) { return r.json(); })
            .then(function (d) {
                var c = (d.data && typeof d.data === 'object' && !Array.isArray(d.data)) ? d.data : {};
                var info = '';
                info += '<div class="detail-row"><span>ID:</span><span>' + esc(c.id) + '</span></div>';
                info += '<div class="detail-row"><span>Entity:</span><span>' + esc(c.entity_id) + '</span></div>';
                info += '<div class="detail-row"><span>User ID:</span><span>' + esc(c.user_id || '-') + '</span></div>';
                info += '<div class="detail-row"><span>Session:</span><span>' + esc(c.session_id || '-') + '</span></div>';
                info += '<div class="detail-row"><span>Device:</span><span>' + esc(c.device_id || '-') + '</span></div>';
                info += '<div class="detail-row"><span>IP:</span><span>' + esc(c.ip_address || '-') + '</span></div>';
                info += '<div class="detail-row"><span>Status:</span><span>' + statusBadge(c.status) + '</span></div>';
                info += '<div class="detail-row"><span>Coupon:</span><span>' + esc(c.coupon_code || '-') + '</span></div>';
                info += '<div class="detail-row"><span>Created:</span><span>' + formatDate(c.created_at) + '</span></div>';
                info += '<div class="detail-row"><span>Last Activity:</span><span>' + formatDate(c.last_activity_at) + '</span></div>';
                info += '<div class="detail-row"><span>Expires:</span><span>' + formatDate(c.expires_at) + '</span></div>';
                document.getElementById('cartInfoFields').innerHTML = info;

                var amounts = '';
                amounts += '<div class="detail-row"><span>Items:</span><span>' + esc(c.total_items) + '</span></div>';
                amounts += '<div class="detail-row"><span>Subtotal:</span><span>' + formatMoney(c.subtotal, c.currency_code) + '</span></div>';
                amounts += '<div class="detail-row"><span>Tax:</span><span>' + formatMoney(c.tax_amount, c.currency_code) + '</span></div>';
                amounts += '<div class="detail-row"><span>Shipping:</span><span>' + formatMoney(c.shipping_cost, c.currency_code) + '</span></div>';
                amounts += '<div class="detail-row"><span>Discount:</span><span>' + formatMoney(c.discount_amount, c.currency_code) + '</span></div>';
                amounts += '<div class="detail-row total"><span>Total:</span><span>' + formatMoney(c.total_amount, c.currency_code) + '</span></div>';
                amounts += '<div class="detail-row"><span>Loyalty Points:</span><span>' + esc(c.loyalty_points_used || 0) + '</span></div>';
                document.getElementById('cartAmountFields').innerHTML = amounts;
            })
            .catch(function () {
                document.getElementById('cartInfoFields').innerHTML = '<p class="text-danger">Failed to load cart details</p>';
            });

        // Load items for this cart
        fetch(apiUrl(API_CART_ITEMS, { cart_id: id }))
            .then(function (r) { return r.json(); })
            .then(function (d) {
                var items = (d.data && d.data.items) ? d.data.items : [];
                var tbody = document.getElementById('cartDetailItemsBody');
                if (!items.length) {
                    tbody.innerHTML = '<tr><td colspan="5" class="text-center">No items</td></tr>';
                    return;
                }
                var html = '';
                items.forEach(function (it) {
                    html += '<tr>';
                    html += '<td>' + esc(it.product_name) + '</td>';
                    html += '<td>' + esc(it.sku) + '</td>';
                    html += '<td>' + esc(it.quantity) + '</td>';
                    html += '<td>' + formatMoney(it.unit_price, it.currency_code) + '</td>';
                    html += '<td>' + formatMoney(it.total, it.currency_code) + '</td>';
                    html += '</tr>';
                });
                tbody.innerHTML = html;
            })
            .catch(function () {
                document.getElementById('cartDetailItemsBody').innerHTML = '<tr><td colspan="5" class="text-center text-danger">Error loading items</td></tr>';
            });

        // Load events for this cart
        fetch(apiUrl(API_CART_EVENTS, { cart_id: id, limit: 20 }))
            .then(function (r) { return r.json(); })
            .then(function (d) {
                var items = (d.data && d.data.items) ? d.data.items : [];
                var tbody = document.getElementById('cartDetailEventsBody');
                if (!items.length) {
                    tbody.innerHTML = '<tr><td colspan="4" class="text-center">No events</td></tr>';
                    return;
                }
                var html = '';
                items.forEach(function (ev) {
                    html += '<tr>';
                    html += '<td><span class="event-type-badge">' + esc(ev.event_type) + '</span></td>';
                    html += '<td>' + esc(ev.actor_type) + (ev.actor_id ? ' #' + ev.actor_id : '') + '</td>';
                    html += '<td>' + esc(ev.note || '-') + '</td>';
                    html += '<td>' + formatDate(ev.created_at) + '</td>';
                    html += '</tr>';
                });
                tbody.innerHTML = html;
            })
            .catch(function () {
                document.getElementById('cartDetailEventsBody').innerHTML = '<tr><td colspan="4" class="text-center text-danger">Error loading events</td></tr>';
            });
    }

    function editCart(id) {
        fetch(apiUrl(API_CARTS, { id: id }))
            .then(function (r) { return r.json(); })
            .then(function (d) {
                var c = (d.data && typeof d.data === 'object' && !Array.isArray(d.data)) ? d.data : {};
                document.getElementById('editCartId').value = c.id || id;
                document.getElementById('editCartStatus').value = c.status || 'active';
                document.getElementById('editCartCoupon').value = c.coupon_code || '';
                var exp = '';
                if (c.expires_at) {
                    try {
                        var dt = new Date(c.expires_at);
                        if (!isNaN(dt.getTime())) {
                            exp = dt.toISOString().substring(0, 16);
                        }
                    } catch (ex) { exp = ''; }
                }
                document.getElementById('editCartExpires').value = exp;
                document.getElementById('editCartTitle').textContent = 'Edit Cart #' + id;
                document.getElementById('editCartModal').style.display = 'block';
            })
            .catch(function (e) { showNotification('Error: ' + e.message, 'error'); });
    }

    function saveCart(e) {
        e.preventDefault();
        var id = document.getElementById('editCartId').value;
        if (!id) return;

        var body = {
            id: parseInt(id),
            status: document.getElementById('editCartStatus').value,
            coupon_code: document.getElementById('editCartCoupon').value || null,
            expires_at: document.getElementById('editCartExpires').value || null
        };

        fetch(apiUrl(API_CARTS, {}), {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body)
        })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (d.success) {
                    showNotification('Cart updated successfully', 'success');
                    document.getElementById('editCartModal').style.display = 'none';
                    loadCarts();
                } else {
                    showNotification(d.message || 'Update failed', 'error');
                }
            })
            .catch(function (e) { showNotification('Error: ' + e.message, 'error'); });
    }

    function deleteCart(id) {
        if (!confirm('Are you sure you want to delete cart #' + id + '?')) return;

        fetch(apiUrl(API_CARTS, {}), {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: id })
        })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (d.success) {
                    showNotification('Cart deleted', 'success');
                    loadCarts();
                } else {
                    showNotification(d.message || 'Delete failed', 'error');
                }
            })
            .catch(function (e) { showNotification('Error: ' + e.message, 'error'); });
    }

    // ════════════════════════════════════════════
    // CART ITEMS
    // ════════════════════════════════════════════
    function loadItems() {
        var tbody = document.getElementById('itemsBody');
        if (!tbody) return;
        tbody.innerHTML = '<tr><td colspan="12" class="text-center">Loading...</td></tr>';

        var params = {
            page: itemsPage,
            limit: PER_PAGE,
            order_by: 'id',
            order_dir: 'DESC'
        };
        if (itemsFilters.cart_id) params.cart_id = itemsFilters.cart_id;
        if (itemsFilters.sku) params.sku = itemsFilters.sku;
        if (itemsFilters.product_id) params.product_id = itemsFilters.product_id;
        if (itemsFilters.entity_id) params.entity_id = itemsFilters.entity_id;

        fetch(apiUrl(API_CART_ITEMS, params))
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (!d.success) throw new Error(d.message || 'Failed to load items');
                var data = d.data || {};
                var items = data.items || [];
                var meta = data.meta || {};
                renderItemsTable(items);
                renderPagination('itemsPagination', 'itemsPaginationInfo', meta, function (p) { itemsPage = p; loadItems(); });
            })
            .catch(function (e) {
                tbody.innerHTML = '<tr><td colspan="12" class="text-center text-danger">' + esc(e.message) + '</td></tr>';
            });
    }

    function renderItemsTable(items) {
        var tbody = document.getElementById('itemsBody');
        if (!items.length) {
            tbody.innerHTML = '<tr><td colspan="12" class="text-center">No items found</td></tr>';
            return;
        }
        var html = '';
        items.forEach(function (it) {
            html += '<tr>';
            html += '<td>' + esc(it.id) + '</td>';
            html += '<td><a href="#" class="link-cart-id" data-cart-id="' + it.cart_id + '">' + esc(it.cart_id) + '</a></td>';
            html += '<td>' + esc(it.product_name) + '<br><small class="text-muted">ID: ' + esc(it.product_id) + '</small></td>';
            html += '<td>' + esc(it.sku) + '</td>';
            html += '<td>' + esc(it.quantity) + '</td>';
            html += '<td>' + formatMoney(it.unit_price) + '</td>';
            html += '<td>' + formatMoney(it.discount_amount) + '</td>';
            html += '<td>' + formatMoney(it.tax_amount) + '</td>';
            html += '<td><strong>' + formatMoney(it.total, it.currency_code) + '</strong></td>';
            html += '<td>' + (parseInt(it.is_gift) ? '<i class="fas fa-gift text-success"></i>' : '-') + '</td>';
            html += '<td>' + formatDate(it.added_at) + '</td>';
            html += '<td class="actions-cell">';
            if (CAN_EDIT) html += '<button class="btn btn-sm btn-warning btn-edit-item" data-id="' + it.id + '" title="Edit"><i class="fas fa-edit"></i></button>';
            if (CAN_DELETE) html += ' <button class="btn btn-sm btn-danger btn-delete-item" data-id="' + it.id + '" title="Delete"><i class="fas fa-trash"></i></button>';
            html += '</td>';
            html += '</tr>';
        });
        tbody.innerHTML = html;
        bindItemActions();
    }

    function bindItemActions() {
        document.querySelectorAll('.btn-edit-item').forEach(function (btn) {
            btn.addEventListener('click', function () { editItem(parseInt(btn.getAttribute('data-id'))); });
        });
        document.querySelectorAll('.btn-delete-item').forEach(function (btn) {
            btn.addEventListener('click', function () { deleteItem(parseInt(btn.getAttribute('data-id'))); });
        });
        document.querySelectorAll('.link-cart-id').forEach(function (link) {
            link.addEventListener('click', function (e) {
                e.preventDefault();
                viewCart(parseInt(link.getAttribute('data-cart-id')));
            });
        });
    }

    function editItem(id) {
        fetch(apiUrl(API_CART_ITEMS, { id: id }))
            .then(function (r) { return r.json(); })
            .then(function (d) {
                var it = (d.data && typeof d.data === 'object' && !Array.isArray(d.data)) ? d.data : {};
                document.getElementById('editItemId').value = it.id || id;
                document.getElementById('editItemQty').value = it.quantity || 1;
                document.getElementById('editItemPrice').value = it.unit_price || 0;
                document.getElementById('editItemDiscount').value = it.discount_amount || 0;
                document.getElementById('editItemGift').value = it.is_gift || 0;
                document.getElementById('editItemInstructions').value = it.special_instructions || '';
                document.getElementById('editItemModal').style.display = 'block';
            })
            .catch(function (e) { showNotification('Error: ' + e.message, 'error'); });
    }

    function saveItem(e) {
        e.preventDefault();
        var id = document.getElementById('editItemId').value;
        if (!id) return;

        var body = {
            id: parseInt(id),
            quantity: parseInt(document.getElementById('editItemQty').value) || 1,
            unit_price: document.getElementById('editItemPrice').value,
            discount_amount: document.getElementById('editItemDiscount').value || '0',
            is_gift: parseInt(document.getElementById('editItemGift').value) || 0,
            special_instructions: document.getElementById('editItemInstructions').value || null
        };

        fetch(apiUrl(API_CART_ITEMS, {}), {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body)
        })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (d.success) {
                    showNotification('Item updated successfully', 'success');
                    document.getElementById('editItemModal').style.display = 'none';
                    loadItems();
                } else {
                    showNotification(d.message || 'Update failed', 'error');
                }
            })
            .catch(function (e) { showNotification('Error: ' + e.message, 'error'); });
    }

    function deleteItem(id) {
        if (!confirm('Are you sure you want to delete item #' + id + '?')) return;

        fetch(apiUrl(API_CART_ITEMS, {}), {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: id })
        })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (d.success) {
                    showNotification('Item deleted', 'success');
                    loadItems();
                } else {
                    showNotification(d.message || 'Delete failed', 'error');
                }
            })
            .catch(function (e) { showNotification('Error: ' + e.message, 'error'); });
    }

    // ════════════════════════════════════════════
    // CART EVENTS
    // ════════════════════════════════════════════
    function loadEvents() {
        var tbody = document.getElementById('eventsBody');
        if (!tbody) return;
        tbody.innerHTML = '<tr><td colspan="10" class="text-center">Loading...</td></tr>';

        var params = {
            page: eventsPage,
            limit: PER_PAGE,
            order_by: 'id',
            order_dir: 'DESC'
        };
        if (eventsFilters.cart_id) params.cart_id = eventsFilters.cart_id;
        if (eventsFilters.event_type) params.event_type = eventsFilters.event_type;
        if (eventsFilters.actor_type) params.actor_type = eventsFilters.actor_type;

        fetch(apiUrl(API_CART_EVENTS, params))
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (!d.success) throw new Error(d.message || 'Failed to load events');
                var data = d.data || {};
                var items = data.items || [];
                var meta = data.meta || {};
                renderEventsTable(items);
                renderPagination('eventsPagination', 'eventsPaginationInfo', meta, function (p) { eventsPage = p; loadEvents(); });
            })
            .catch(function (e) {
                tbody.innerHTML = '<tr><td colspan="10" class="text-center text-danger">' + esc(e.message) + '</td></tr>';
            });
    }

    function renderEventsTable(items) {
        var tbody = document.getElementById('eventsBody');
        if (!items.length) {
            tbody.innerHTML = '<tr><td colspan="10" class="text-center">No events found</td></tr>';
            return;
        }
        var html = '';
        items.forEach(function (ev) {
            html += '<tr>';
            html += '<td>' + esc(ev.id) + '</td>';
            html += '<td><a href="#" class="link-event-cart" data-cart-id="' + ev.cart_id + '">' + esc(ev.cart_id) + '</a></td>';
            html += '<td><span class="event-type-badge">' + esc(ev.event_type) + '</span></td>';
            html += '<td>' + esc(ev.actor_type) + '</td>';
            html += '<td>' + esc(ev.actor_id || '-') + '</td>';
            html += '<td>' + esc(ev.related_item_id || '-') + '</td>';
            html += '<td>' + truncateJson(ev.old_value) + '</td>';
            html += '<td>' + truncateJson(ev.new_value) + '</td>';
            html += '<td>' + esc(ev.note || '-') + '</td>';
            html += '<td>' + formatDate(ev.created_at) + '</td>';
            html += '</tr>';
        });
        tbody.innerHTML = html;
        document.querySelectorAll('.link-event-cart').forEach(function (link) {
            link.addEventListener('click', function (e) {
                e.preventDefault();
                viewCart(parseInt(link.getAttribute('data-cart-id')));
            });
        });
    }

    // ════════════════════════════════════════════
    // PAGINATION
    // ════════════════════════════════════════════
    function renderPagination(paginationId, infoId, meta, onPageChange) {
        var container = document.getElementById(paginationId);
        var info = document.getElementById(infoId);
        if (!meta || !meta.total) {
            if (container) container.innerHTML = '';
            if (info) info.textContent = '';
            return;
        }

        var totalPages = meta.total_pages || 1;
        var page = meta.page || 1;

        if (info) {
            info.textContent = 'Showing ' + (meta.from || 0) + '-' + (meta.to || 0) + ' of ' + meta.total;
        }

        if (!container) return;
        if (totalPages <= 1) { container.innerHTML = ''; return; }

        var html = '';
        if (page > 1) html += '<button class="page-btn" data-p="' + (page - 1) + '">&laquo;</button>';
        var start = Math.max(1, page - 2);
        var end = Math.min(totalPages, page + 2);
        for (var i = start; i <= end; i++) {
            html += '<button class="page-btn' + (i === page ? ' active' : '') + '" data-p="' + i + '">' + i + '</button>';
        }
        if (page < totalPages) html += '<button class="page-btn" data-p="' + (page + 1) + '">&raquo;</button>';
        container.innerHTML = html;

        container.querySelectorAll('.page-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                onPageChange(parseInt(btn.getAttribute('data-p')));
            });
        });
    }

    // ════════════════════════════════════════════
    // MODAL HELPERS
    // ════════════════════════════════════════════
    function closeModal(id) {
        var modal = document.getElementById(id);
        if (modal) modal.style.display = 'none';
    }

    // ════════════════════════════════════════════
    // FILTERS
    // ════════════════════════════════════════════
    function initCartFilters() {
        var btn = document.getElementById('btnCartFilter');
        if (btn) btn.addEventListener('click', function () {
            var search = document.getElementById('cartSearchInput').value.trim();
            cartsFilters = {
                status: document.getElementById('cartStatusFilter').value || null,
                entity_id: document.getElementById('cartEntityFilter').value || null
            };
            if (search && /^\d+$/.test(search)) cartsFilters.user_id = search;
            cartsPage = 1;
            loadCarts();
        });
        var clr = document.getElementById('btnCartClearFilter');
        if (clr) clr.addEventListener('click', function () {
            document.getElementById('cartSearchInput').value = '';
            document.getElementById('cartStatusFilter').value = '';
            document.getElementById('cartEntityFilter').value = '';
            cartsFilters = {};
            cartsPage = 1;
            loadCarts();
        });
    }

    function initItemFilters() {
        var btn = document.getElementById('btnItemFilter');
        if (btn) btn.addEventListener('click', function () {
            itemsFilters = {
                cart_id: document.getElementById('itemCartIdFilter').value || null,
                sku: document.getElementById('itemSkuFilter').value || null,
                product_id: document.getElementById('itemProductFilter').value || null
            };
            itemsPage = 1;
            loadItems();
        });
        var clr = document.getElementById('btnItemClearFilter');
        if (clr) clr.addEventListener('click', function () {
            document.getElementById('itemCartIdFilter').value = '';
            document.getElementById('itemSkuFilter').value = '';
            document.getElementById('itemProductFilter').value = '';
            itemsFilters = {};
            itemsPage = 1;
            loadItems();
        });
    }

    function initEventFilters() {
        var btn = document.getElementById('btnEventFilter');
        if (btn) btn.addEventListener('click', function () {
            eventsFilters = {
                cart_id: document.getElementById('eventCartIdFilter').value || null,
                event_type: document.getElementById('eventTypeFilter').value || null,
                actor_type: document.getElementById('eventActorFilter').value || null
            };
            eventsPage = 1;
            loadEvents();
        });
        var clr = document.getElementById('btnEventClearFilter');
        if (clr) clr.addEventListener('click', function () {
            document.getElementById('eventCartIdFilter').value = '';
            document.getElementById('eventTypeFilter').value = '';
            document.getElementById('eventActorFilter').value = '';
            eventsFilters = {};
            eventsPage = 1;
            loadEvents();
        });
    }

    // ════════════════════════════════════════════
    // INIT
    // ════════════════════════════════════════════
    function init() {
        initTabs();
        initCartFilters();
        initItemFilters();
        initEventFilters();

        // Refresh button
        var refresh = document.getElementById('btnRefreshCarts');
        if (refresh) refresh.addEventListener('click', function () {
            if (activeTab === 'carts') loadCarts();
            else if (activeTab === 'items') loadItems();
            else if (activeTab === 'events') loadEvents();
        });

        // Modal close buttons
        var closeDetail = document.getElementById('btnCloseCartDetail');
        if (closeDetail) closeDetail.addEventListener('click', function () { closeModal('cartDetailModal'); });

        var closeEdit = document.getElementById('btnCloseEditCart');
        if (closeEdit) closeEdit.addEventListener('click', function () { closeModal('editCartModal'); });
        var cancelEdit = document.getElementById('btnCancelEditCart');
        if (cancelEdit) cancelEdit.addEventListener('click', function () { closeModal('editCartModal'); });

        var closeEditItem = document.getElementById('btnCloseEditItem');
        if (closeEditItem) closeEditItem.addEventListener('click', function () { closeModal('editItemModal'); });
        var cancelEditItem = document.getElementById('btnCancelEditItem');
        if (cancelEditItem) cancelEditItem.addEventListener('click', function () { closeModal('editItemModal'); });

        // Close modals on backdrop click
        document.querySelectorAll('.modal').forEach(function (modal) {
            modal.addEventListener('click', function (e) {
                if (e.target === modal) modal.style.display = 'none';
            });
        });

        // Form submissions
        var editCartForm = document.getElementById('editCartForm');
        if (editCartForm) editCartForm.addEventListener('submit', saveCart);

        var editItemForm = document.getElementById('editItemForm');
        if (editItemForm) editItemForm.addEventListener('submit', saveItem);

        // Initial load
        loadCarts();
    }

    // Wait for DOM
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
