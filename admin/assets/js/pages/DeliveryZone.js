// admin/assets/js/pages/DeliveryZone.js
// Delivery Zones admin UI — aligned to delivery_zones DB schema.
// Self-loads Leaflet JS and CSS so the map works whether this script is
// included statically or injected dynamically by admin_core.js runScripts.

(function () {
    'use strict';

    var API        = (window.DZ && window.DZ.API_BASE)   || '/api/delivery_zones';
    var TENANT_ID  = (window.DZ && window.DZ.TENANT_ID)  || 1;
    var CSRF_TOKEN = (window.DZ && window.DZ.CSRF_TOKEN) || '';

    var LEAFLET_JS   = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
    var DRAW_JS      = 'https://cdnjs.cloudflare.com/ajax/libs/leaflet.draw/1.0.4/leaflet.draw.js';
    var LEAFLET_CSS  = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
    var DRAW_CSS     = 'https://cdnjs.cloudflare.com/ajax/libs/leaflet.draw/1.0.4/leaflet.draw.css';

    // ─── Helpers ─────────────────────────────────────────────────────────────
    function qs(sel) { return document.querySelector(sel); }
    function qsa(sel) { return Array.from(document.querySelectorAll(sel)); }
    function fld(id) { return qs('#dz_' + id); }
    function safeSet(id, val) { var el = fld(id); if (el) el.value = (val == null ? '' : val); }
    function esc(s) {
        return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    // ─── Serial script loader ─────────────────────────────────────────────────
    // Appends a <script> to <head> and fires cb() when it finishes (or errors).
    // Injects <script src> into <head> if not already present, then polls
    // checkFn() every POLL_INTERVAL_MS until it returns true.
    // Max wait: POLL_MAX_TICKS * POLL_INTERVAL_MS = 10 s.
    // This correctly handles the race where admin_core.js runScripts has
    // already added the <script> tag but the browser hasn't executed it yet.
    var POLL_INTERVAL_MS = 50;
    var POLL_MAX_TICKS   = 200; // 200 × 50 ms = 10 s
    function loadScript(src, checkFn, cb) {
        if (checkFn()) { cb(); return; }
        // Add tag only if not already in DOM
        var found = false;
        var allScripts = document.querySelectorAll('script');
        for (var i = 0; i < allScripts.length; i++) {
            if (allScripts[i].src === src) { found = true; break; }
        }
        if (!found) {
            var s = document.createElement('script');
            s.src = src;
            s.onerror = function () { console.error('[DZ] Failed to load: ' + src); };
            document.head.appendChild(s);
        }
        var ticks = 0;
        var timer = setInterval(function () {
            ticks++;
            if (checkFn()) { clearInterval(timer); cb(); return; }
            if (ticks >= POLL_MAX_TICKS) { clearInterval(timer); console.error('[DZ] Timed out loading: ' + src); cb(); }
        }, POLL_INTERVAL_MS);
    }

    // Load leaflet.js then leaflet.draw.js in a guaranteed serial chain,
    // verified via library globals — not just <script> tag presence.
    function ensureLeafletJs(cb) {
        loadScript(LEAFLET_JS, function () { return !!window.L; }, function () {
            loadScript(DRAW_JS, function () { return !!(window.L && window.L.Draw); }, cb);
        });
    }

    // ─── CSS loader ─────────────────────────────────────────────────────────
    function ensureCss(href, cb) {
        var found = false;
        qsa('link[rel="stylesheet"]').forEach(function (el) {
            if (el.href === href) {
                // Move to head if it was injected inside body by runScripts
                if (!el.closest('head')) {
                    document.head.appendChild(el);
                }
                found = true;
            }
        });
        if (found) { cb(); return; }
        var l = document.createElement('link');
        l.rel  = 'stylesheet';
        l.href = href;
        l.onload = l.onerror = cb;
        document.head.appendChild(l);
    }

    function ensureLeafletCss(cb) {
        var pending = 2, done = false;
        function finish() { if (!done && --pending === 0) { done = true; cb(); } }
        ensureCss(LEAFLET_CSS, finish);
        ensureCss(DRAW_CSS, finish);
    }

    // ─── Map state ───────────────────────────────────────────────────────────
    var map = null, drawnItems = null, zoneLayerMap = new Map();

    function initMap() {
        var mapEl = qs('#dzMap');
        if (!mapEl) return;
        // If map container already has a Leaflet instance remove it first
        if (map) {
            try { map.remove(); } catch(e) {}
            map = null;
            drawnItems = null;
        }
        if (typeof L === 'undefined') { console.error('[DZ] Leaflet (L) still undefined — map skipped'); return; }

        map = L.map('dzMap').setView([24.7136, 46.6753], 6);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/">OpenStreetMap</a> contributors',
            maxZoom: 19
        }).addTo(map);

        drawnItems = L.featureGroup().addTo(map);

        if (typeof L.Control !== 'undefined' && L.Control.Draw) {
            new L.Control.Draw({
                position: 'topright',
                draw: {
                    polygon:      { allowIntersection: false, showArea: true, shapeOptions: { color: '#2563eb', fillOpacity: 0.15 } },
                    circle:       { shapeOptions: { color: '#2563eb', fillOpacity: 0.15 } },
                    rectangle:    false,
                    marker:       false,
                    polyline:     false,
                    circlemarker: false
                },
                edit: { featureGroup: drawnItems, remove: true }
            }).addTo(map);

            map.on(L.Draw.Event.CREATED, function (e) {
                drawnItems.clearLayers();
                drawnItems.addLayer(e.layer);
                populateFromLayer(e.layer);
            });
            map.on(L.Draw.Event.EDITED, function (e) {
                e.layers.eachLayer(function (l) { populateFromLayer(l); });
            });
            map.on(L.Draw.Event.DELETED, function () {
                if (drawnItems.getLayers().length === 0) {
                    safeSet('zone_value', '');
                    safeSet('center_lat', '');
                    safeSet('center_lng', '');
                    safeSet('radius_km', '');
                }
            });
        }

        // Trigger size recalculation after layout settles
        [50, 300, 800].forEach(function (ms) {
            setTimeout(function () { if (map) map.invalidateSize(); }, ms);
        });
    }

    // ─── Populate form fields from drawn layer ────────────────────────────────
    function populateFromLayer(layer) {
        if (!layer) return;
        if (layer instanceof L.Circle) {
            var c = layer.getLatLng();
            safeSet('center_lat', c.lat.toFixed(7));
            safeSet('center_lng', c.lng.toFixed(7));
            safeSet('radius_km',  (layer.getRadius() / 1000).toFixed(2));
            safeSet('zone_type', 'radius');
            safeSet('zone_value', '');
            showHideConditionalFields('radius');
        } else if (layer.getLatLngs) {
            var coords = layer.getLatLngs()[0].map(function (p) { return [p.lng, p.lat]; });
            var geo = { type: 'Polygon', coordinates: [coords] };
            safeSet('zone_value', JSON.stringify(geo));
            safeSet('center_lat', '');
            safeSet('center_lng', '');
            safeSet('radius_km', '');
        }
    }

    // ─── Draw a geometry on the map ───────────────────────────────────────────
    function drawGeometry(zone) {
        if (!drawnItems || typeof L === 'undefined') return;
        drawnItems.clearLayers();
        var layer = null;
        var t = (zone.zone_type || '').toLowerCase();

        if (t === 'radius' && zone.center_lat && zone.center_lng && zone.radius_km) {
            var lat = parseFloat(zone.center_lat), lng = parseFloat(zone.center_lng), rkm = parseFloat(zone.radius_km);
            if (isFinite(lat) && isFinite(lng) && isFinite(rkm) && rkm > 0) {
                layer = L.circle([lat, lng], { radius: rkm * 1000, color: '#ff7800', fillOpacity: 0.15 });
            }
        } else if (zone.zone_value) {
            try {
                var geo = JSON.parse(zone.zone_value);
                if ((geo.type || '').toLowerCase() === 'polygon') {
                    var lls = (geo.coordinates && geo.coordinates[0] || []).map(function (p) { return [p[1], p[0]]; });
                    layer = L.polygon(lls, { color: '#ff7800', fillOpacity: 0.15 });
                }
            } catch(e) {}
        }

        if (layer) {
            drawnItems.addLayer(layer);
            try { map.fitBounds(layer.getBounds()); } catch(e) {}
        }
    }

    // ─── Conditional field visibility ────────────────────────────────────────
    function showHideConditionalFields(type) {
        var cityRow   = qs('#dz_city_row');
        var radiusRow = qs('#dz_radius_row');
        var geoRow    = qs('#dz_geojson_row');
        if (cityRow)   cityRow.style.display   = (type === 'city')                     ? '' : 'none';
        if (radiusRow) radiusRow.style.display  = (type === 'radius')                   ? '' : 'none';
        if (geoRow)    geoRow.style.display     = (type === 'polygon' || type === 'district') ? '' : 'none';
    }

    // ─── API wrapper ─────────────────────────────────────────────────────────
    function apiCall(url, opts) {
        opts = opts || {};
        var headers = { 'X-Requested-With': 'XMLHttpRequest' };
        if (CSRF_TOKEN) headers['X-CSRF-Token'] = CSRF_TOKEN;
        if (opts.json) {
            headers['Content-Type'] = 'application/json';
            opts.body = JSON.stringify(opts.json);
            delete opts.json;
        }
        return fetch(url, Object.assign({}, opts, { headers: headers, credentials: 'same-origin' }))
            .then(function (res) {
                if (!res.ok) throw new Error('HTTP ' + res.status);
                return res.json().catch(function () { throw new Error('Invalid JSON response'); });
            })
            .then(function (j) {
                if (j && j.success === false) throw new Error(j.message || 'API error');
                return j;
            });
    }

    // ─── Load and render list ─────────────────────────────────────────────────
    function loadList() {
        var listEl = qs('#dzList');
        if (listEl) listEl.innerHTML = '<div class="loading-state"><div class="spinner"></div></div>';

        var isActive = ((qs('#dzStatusFilter') || {}).value) || '';
        var q        = (((qs('#dzSearch') || {}).value) || '').trim();
        var params   = 'tenant_id=' + encodeURIComponent(TENANT_ID) + '&limit=100';
        if (isActive !== '') params += '&is_active=' + encodeURIComponent(isActive);
        if (q) params += '&q=' + encodeURIComponent(q);

        apiCall(API + '?' + params)
            .then(function (r) {
                var items = (r.data && r.data.items) || r.items || [];
                renderList(items);
                renderZonesOnMap(items);
            })
            .catch(function (e) {
                if (listEl) listEl.innerHTML = '<div class="table-error-row">' + esc(e.message) + '</div>';
            });
    }

    function renderList(rows) {
        var listEl = qs('#dzList');
        if (!listEl) return;
        if (!rows || rows.length === 0) {
            listEl.innerHTML = '<div class="empty-state"><div class="empty-icon"><i class="fas fa-map-marked-alt"></i></div><p data-i18n="delivery_zone.no_zones">No zones found</p></div>';
            return;
        }
        listEl.innerHTML = '';
        rows.forEach(function (r) {
            var activeHtml = r.is_active
                ? '<span class="badge badge-success" data-i18n="statuses.active">Active</span>'
                : '<span class="badge badge-danger" data-i18n="statuses.inactive">Inactive</span>';
            var item = document.createElement('div');
            item.className = 'dz-list-item';
            item.innerHTML =
                '<div class="dz-item-main">' +
                  '<strong>' + esc(r.zone_name || '(unnamed)') + '</strong>' +
                  '<div class="dz-meta">' + esc(r.zone_type) + ' &middot; ' + esc(r.delivery_fee || 0) + ' &middot; ' + activeHtml + '</div>' +
                '</div>' +
                '<div class="dz-item-actions">' +
                  '<button class="btn btn-sm btn-outline edit-btn" data-id="' + esc(r.id) + '" data-i18n-title="actions.edit" title="Edit"><i class="fas fa-edit"></i></button>' +
                  '<button class="btn btn-sm btn-danger del-btn"  data-id="' + esc(r.id) + '" data-i18n-title="actions.delete" title="Delete"><i class="fas fa-trash"></i></button>' +
                '</div>';
            listEl.appendChild(item);
        });
        qsa('#dzList .edit-btn').forEach(function (b) {
            b.addEventListener('click', function () { openEdit(b.dataset.id); });
        });
        qsa('#dzList .del-btn').forEach(function (b) {
            b.addEventListener('click', function () { deleteZone(b.dataset.id); });
        });
    }

    function renderZonesOnMap(rows) {
        if (!drawnItems || typeof L === 'undefined') return;
        drawnItems.clearLayers();
        zoneLayerMap.clear();

        (rows || []).forEach(function (r) {
            var layer = null;
            var t = (r.zone_type || '').toLowerCase();

            if (t === 'radius' && r.center_lat && r.center_lng && r.radius_km) {
                var lat = parseFloat(r.center_lat), lng = parseFloat(r.center_lng), rkm = parseFloat(r.radius_km);
                if (isFinite(lat) && isFinite(lng) && isFinite(rkm) && rkm > 0) {
                    layer = L.circle([lat, lng], { radius: rkm * 1000, color: '#3388ff', fillOpacity: 0.15 });
                }
            } else if (r.zone_value) {
                try {
                    var geo = JSON.parse(r.zone_value);
                    if ((geo.type || '').toLowerCase() === 'polygon') {
                        var lls = (geo.coordinates && geo.coordinates[0] || []).map(function (p) { return [p[1], p[0]]; });
                        layer = L.polygon(lls, { color: '#3388ff', fillOpacity: 0.15 });
                    }
                } catch(e) {}
            }

            if (layer) {
                drawnItems.addLayer(layer);
                layer.bindPopup('<strong>' + esc(r.zone_name) + '</strong><br>' + esc(t) + ' &middot; ' + esc(r.delivery_fee || 0));
                zoneLayerMap.set(String(r.id), layer);
                layer.on('click', (function (id) { return function () { openEdit(id); }; })(r.id));
            }
        });

        if (drawnItems.getLayers().length > 0) {
            try { map.fitBounds(drawnItems.getBounds(), { padding: [20, 20] }); } catch(e) {}
        }
    }

    // ─── Open edit ───────────────────────────────────────────────────────────
    function openEdit(id) {
        if (!id) return;
        apiCall(API + '/' + id + '?tenant_id=' + TENANT_ID)
            .then(function (r) {
                var z = (r && r.data) ? r.data : r;
                safeSet('id', z.id || 0);
                safeSet('zone_name', z.zone_name || '');
                safeSet('zone_type', z.zone_type || 'city');
                safeSet('provider_id', z.provider_id || '');
                safeSet('city_id', z.city_id || '');
                safeSet('center_lat', z.center_lat || '');
                safeSet('center_lng', z.center_lng || '');
                safeSet('radius_km', z.radius_km || '');
                safeSet('delivery_fee', z.delivery_fee || '0.00');
                safeSet('free_delivery_over', z.free_delivery_over || '');
                safeSet('min_order_value', z.min_order_value || '');
                safeSet('estimated_minutes', z.estimated_minutes || 45);
                safeSet('is_active', z.is_active ? '1' : '0');
                safeSet('zone_value', z.zone_value || '');
                showHideConditionalFields(z.zone_type || 'city');
                showForm('Edit Zone');
                drawGeometry(z);
            })
            .catch(function (e) { alert(e.message || 'Failed to load zone'); });
    }

    // ─── Save ────────────────────────────────────────────────────────────────
    function saveZone() {
        var idVal = ((fld('id') || {}).value) || '';
        var isCreate = !idVal || idVal === '0';

        var body = {
            zone_name:          (((fld('zone_name') || {}).value) || '').trim(),
            zone_type:          ((fld('zone_type') || {}).value) || 'city',
            provider_id:        parseInt(((fld('provider_id') || {}).value), 10) || null,
            city_id:            parseInt(((fld('city_id') || {}).value), 10) || null,
            center_lat:         (((fld('center_lat') || {}).value) || '').trim() || null,
            center_lng:         (((fld('center_lng') || {}).value) || '').trim() || null,
            radius_km:          (((fld('radius_km') || {}).value) || '').trim() || null,
            delivery_fee:       ((fld('delivery_fee') || {}).value) || '0.00',
            free_delivery_over: (((fld('free_delivery_over') || {}).value) || '').trim() || null,
            min_order_value:    (((fld('min_order_value') || {}).value) || '').trim() || null,
            estimated_minutes:  parseInt(((fld('estimated_minutes') || {}).value), 10) || 45,
            is_active:          ((fld('is_active') || {}).value) === '1' ? 1 : 0,
            zone_value:         (((fld('zone_value') || {}).value) || '').trim() || null,
            tenant_id:          TENANT_ID
        };
        if (!isCreate) body.id = parseInt(idVal, 10);

        apiCall(isCreate ? API : API + '/' + idVal, { method: isCreate ? 'POST' : 'PUT', json: body })
            .then(function (j) {
                if (isCreate) {
                    var newId = j.data && j.data.id;
                    if (newId) { safeSet('id', newId); } else { console.warn('[DZ] create response missing data.id', j); }
                }
                showSuccess('Saved successfully');
                loadList();
            })
            .catch(function (e) { alert(e.message || 'Save failed'); });
    }

    // ─── Delete ──────────────────────────────────────────────────────────────
    function deleteZone(id) {
        if (!id || !confirm('Delete this delivery zone?')) return;
        apiCall(API + '/' + id + '?tenant_id=' + TENANT_ID, { method: 'DELETE' })
            .then(function () {
                showSuccess('Deleted successfully');
                clearForm();
                loadList();
            })
            .catch(function (e) { alert(e.message || 'Delete failed'); });
    }

    // ─── Form helpers ────────────────────────────────────────────────────────
    function showForm(title) {
        var card = qs('#dzFormCard');
        if (card) card.style.display = '';
        var t = qs('#dzFormTitle');
        if (t) t.textContent = title || 'Zone Details';
    }

    function clearForm() {
        var form = qs('#dzForm');
        if (form) form.reset();
        safeSet('id', '');
        safeSet('zone_value', '');
        if (drawnItems) drawnItems.clearLayers();
        if (map) map.setView([24.7136, 46.6753], 6);
        var card = qs('#dzFormCard');
        if (card) card.style.display = 'none';
        showHideConditionalFields('city');
    }

    function showSuccess(msg) {
        if (window.AdminFramework && typeof window.AdminFramework.success === 'function') {
            window.AdminFramework.success(msg);
        } else if (window.AF && typeof window.AF.success === 'function') {
            window.AF.success(msg);
        } else {
            console.log('[DZ] ' + msg);
        }
    }

    // ─── Bind UI events ──────────────────────────────────────────────────────
    function bindEvents() {
        var saveBtn    = qs('#dzSaveBtn');
        var resetBtn   = qs('#dzResetBtn');
        var newBtn     = qs('#dzNewBtn');
        var refreshBtn = qs('#dzRefresh');
        var closeBtn   = qs('#dzCloseForm');
        var search     = qs('#dzSearch');
        var statusSel  = qs('#dzStatusFilter');
        var zoneType   = qs('#dz_zone_type');
        var geoField   = qs('#dz_zone_value');

        if (saveBtn)    saveBtn.addEventListener('click',  saveZone);
        if (resetBtn)   resetBtn.addEventListener('click', clearForm);
        if (closeBtn)   closeBtn.addEventListener('click', clearForm);
        if (newBtn)     newBtn.addEventListener('click', function () { clearForm(); showForm('New Zone'); });
        if (refreshBtn) refreshBtn.addEventListener('click', loadList);
        if (search)     search.addEventListener('input', function () { setTimeout(loadList, 300); });
        if (statusSel)  statusSel.addEventListener('change', loadList);
        if (zoneType)   zoneType.addEventListener('change', function () { showHideConditionalFields(this.value); });

        if (geoField) {
            geoField.addEventListener('change', function () {
                if (!drawnItems || typeof L === 'undefined') return;
                var txt = this.value.trim();
                if (!txt) return;
                try {
                    var geo = JSON.parse(txt);
                    if ((geo.type || '').toLowerCase() === 'polygon') {
                        drawnItems.clearLayers();
                        var lls = (geo.coordinates && geo.coordinates[0] || []).map(function (p) { return [p[1], p[0]]; });
                        var l = L.polygon(lls, { color: '#ff7800', fillOpacity: 0.15 });
                        drawnItems.addLayer(l);
                        try { map.fitBounds(l.getBounds()); } catch(e) {}
                    }
                } catch(e) {}
            });
        }
    }

    // ─── Entry point ─────────────────────────────────────────────────────────
    function boot() {
        // Guard: don't init twice if script gets re-injected without a page reload
        var mapEl = qs('#dzMap');
        if (!mapEl) return;
        if (mapEl.getAttribute('data-dz-init')) return;
        mapEl.setAttribute('data-dz-init', '1');

        ensureLeafletJs(function () {
            ensureLeafletCss(function () {
                initMap();
                bindEvents();
                loadList();
            });
        });
    }

    // Expose boot so the fragment's inline script can re-trigger initialisation
    // on subsequent AJAX loads (runScripts skips already-loaded external .js tags).
    window.DZ = window.DZ || {};
    window.DZ.boot = boot;

    // Works in both direct page load and AJAX fragment injection modes.
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }

})();