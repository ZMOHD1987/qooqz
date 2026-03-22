/**
 * /admin/assets/js/pages/delivery.js
 * Delivery Management — Full Workspace Logic
 * Includes: Leaflet map, zone drawing, provider ID lookup, country/city cascade, coordinate picker
 */
(function () {
    'use strict';

    const AF  = window.AdminFramework;
    const CFG = window.DELIVERY_CONFIG || {};

    // ─── Constants ───────────────────────────────────────────────────
    const MIN_VISIBLE_MAP_HEIGHT    = 260;
    const FALLBACK_MIN_MAP_HEIGHT   = 320;
    // Progressive delays cover immediate layout + late-settle phases on mobile/tab switches
    const MAP_INVALIDATE_DELAYS_MS  = [80, 180, 350, 700, 1200, 2000];
    const MAP_INIT_RETRY_DELAYS_MS  = [100, 300, 700, 1200, 2500];
    const GEOLOCATION_MIN_ZOOM      = 13;
    const GEOLOCATION_TIMEOUT_MS    = 10000;
    const PROVIDER_LOOKUP_DEBOUNCE  = 400;
    const LEAFLET_POLL_MS           = 50;
    const LEAFLET_MAX_TICKS         = 200; // 200 × 50ms = 10s

    // ─── State ───────────────────────────────────────────────────────
    const state = {
        lang:   CFG.lang || 'ar',
        tenant: CFG.tenantId || 1,
        csrf:   CFG.csrfToken || '',
        perms:  { canCreate: !!CFG.canCreate, canEdit: !!CFG.canEdit, canDelete: !!CFG.canDelete },
        initialized: false,
        zones:          { page: 1, items: [], filters: {}, loaded: false, total: 0, saving: false },
        providers:      { page: 1, items: [], filters: {}, loaded: false, total: 0, saving: false },
        orders:         { page: 1, items: [], filters: {}, loaded: false, total: 0, saving: false },
        locations:      { page: 1, items: [], filters: {}, loaded: false, total: 0, saving: false },
        tracking:       { page: 1, items: [], filters: {}, loaded: false, total: 0, saving: false },
        provider_zones: { page: 1, items: [], filters: {}, loaded: false, total: 0, saving: false }
    };
    const LIMIT = 20;

    // ─── Leaflet CDN URLs ─────────────────────────────────────────────
    var LEAFLET_JS  = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
    var DRAW_JS     = 'https://cdnjs.cloudflare.com/ajax/libs/leaflet.draw/1.0.4/leaflet.draw.js';
    var LEAFLET_CSS = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
    var DRAW_CSS    = 'https://cdnjs.cloudflare.com/ajax/libs/leaflet.draw/1.0.4/leaflet.draw.css';

    // ─── Leaflet Loader ───────────────────────────────────────────────
    /** Inject <script src> then poll until checkFn() is truthy. */
    function loadScript(src, checkFn, cb) {
        if (checkFn()) { cb(); return; }
        if (!document.querySelector('script[src="' + src + '"]')) {
            var s = document.createElement('script');
            s.src = src;
            s.crossOrigin = 'anonymous';
            s.onerror = function () { console.error('[Delivery] Failed to load: ' + src); };
            document.head.appendChild(s);
        }
        var ticks = 0;
        var iv = setInterval(function () {
            if (checkFn()) { clearInterval(iv); cb(); return; }
            if (++ticks >= LEAFLET_MAX_TICKS) {
                clearInterval(iv);
                console.error('[Delivery] Timed out loading: ' + src);
                cb();
            }
        }, LEAFLET_POLL_MS);
    }

    /** Serial chain: Leaflet JS → Leaflet.Draw JS → cb */
    function ensureLeafletJs(cb) {
        loadScript(LEAFLET_JS, function () { return !!window.L; }, function () {
            loadScript(DRAW_JS, function () { return !!(window.L && window.L.Draw); }, cb);
        });
    }

    /** Ensure Leaflet CSS + Draw CSS are injected into <head> (not a fragment div). */
    function ensureLeafletCss(cb) {
        var urls  = [LEAFLET_CSS, DRAW_CSS];
        var pending = 0;
        var done    = false;
        function finish() { if (!done) { done = true; cb(); } }
        var allLinks  = Array.from(document.querySelectorAll('link[rel="stylesheet"]'));
        var headLinks = Array.from(document.querySelectorAll('head link[rel="stylesheet"]'));
        urls.forEach(function (href) {
            // Remove stale links that ended up outside <head> after AJAX injection
            allLinks.forEach(function (el) {
                if (el.href === href && el.closest('head') === null) {
                    el.parentNode.removeChild(el);
                }
            });
            var inHead = headLinks.some(function (el) { return el.href === href; });
            if (!inHead) {
                pending++;
                var l = document.createElement('link');
                l.rel  = 'stylesheet';
                l.href = href;
                l.onload = l.onerror = function () { if (--pending === 0) finish(); };
                document.head.appendChild(l);
            }
        });
        if (pending === 0) finish();
    }

    // ─── Leaflet Map State ────────────────────────────────────────────
    let zonesMap          = null;
    let drawnItems        = null;
    let zoneLayerMap      = new Map();
    let coordPickerMap    = null;
    let coordPickerMarker = null;
    let coordPickerTarget = null;   // { latId, lngId }
    let zonesLocateMarker = null;
    let mapResizeObserver = null;

    // ─── DOM Helpers ──────────────────────────────────────────────────
    function $(id) { return document.getElementById(id); }
    function esc(v) {
        if (v == null) return '';
        return String(v).replace(/[&<>"']/g, function (m) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m];
        });
    }

    function notify(msg, type) {
        type = type || 'info';
        if (AF && typeof AF.error === 'function') {
            type === 'error' ? AF.error(msg) : AF.success(msg);
            return;
        }
        var t = $('deliveryToast');
        if (!t) { console.log('[Delivery]', type, msg); return; }
        t.textContent = msg;
        t.className   = 'dl-toast dl-toast--' + type;
        t.style.display = '';
        clearTimeout(t._tmr);
        t._tmr = setTimeout(function () { t.style.display = 'none'; }, 4000);
    }

    function scheduleMapInvalidate() {
        if (!zonesMap) return;
        MAP_INVALIDATE_DELAYS_MS.forEach(function (ms) {
            setTimeout(function () { if (zonesMap) zonesMap.invalidateSize(); }, ms);
        });
    }

    function ensureMapElementHeight() {
        var mapEl = $('zonesMap');
        if (!mapEl) return;
        var current = mapEl.getBoundingClientRect().height;
        if (current < MIN_VISIBLE_MAP_HEIGHT) {
            mapEl.style.minHeight = FALLBACK_MIN_MAP_HEIGHT + 'px';
        }
    }

    function isElementVisible(el) {
        if (!el) return false;
        return !!(el.offsetParent || el.getClientRects().length);
    }

    function showTableError(tbodyId, msg) {
        var el = $(tbodyId);
        if (el) {
            el.innerHTML = '<tr><td colspan="20" class="table-error-row">' +
                '<i class="fas fa-exclamation-triangle"></i> ' + esc(msg) + '</td></tr>';
        }
    }

    // ─── Fetch Wrapper ────────────────────────────────────────────────
    async function api(url, opts) {
        opts = opts || {};
        var headers = {
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-Token': state.csrf
        };
        if (opts.json) {
            headers['Content-Type'] = 'application/json';
            opts.body = JSON.stringify(opts.json);
            delete opts.json;
        }
        var res = await fetch(url, Object.assign({}, opts, { headers: headers, credentials: 'same-origin' }));
        if (!res.ok) {
            var errMsg = 'HTTP ' + res.status;
            try { var j2 = await res.json(); errMsg = j2.message || j2.error || errMsg; } catch (_) {}
            throw new Error(errMsg);
        }
        var j = await res.json();
        if (j && ('success' in j) && j.success === false) throw new Error(j.message || j.error || 'API error');
        return j;
    }

    function badge(text, map) {
        map = map || {};
        var cls = map[text] || 'secondary';
        return '<span class="badge badge-' + cls + '">' + esc(text) + '</span>';
    }

    function pagination(container, info, total, page, cb) {
        var pages = Math.ceil(total / LIMIT) || 1;
        if (info) info.textContent = total
            ? ((page - 1) * LIMIT + 1) + '–' + Math.min(page * LIMIT, total) + ' / ' + total
            : '0';
        if (!container) return;
        var h = '<ul>';
        if (page > 1) h += '<li><a href="#" data-p="' + (page - 1) + '">&laquo;</a></li>';
        for (var i = Math.max(1, page - 2); i <= Math.min(pages, page + 2); i++) {
            h += i === page
                ? '<li class="active"><span>' + i + '</span></li>'
                : '<li><a href="#" data-p="' + i + '">' + i + '</a></li>';
        }
        if (page < pages) h += '<li><a href="#" data-p="' + (page + 1) + '">&raquo;</a></li>';
        container.innerHTML = h + '</ul>';
        container.querySelectorAll('a[data-p]').forEach(function (a) {
            a.onclick = function (e) { e.preventDefault(); cb(parseInt(a.dataset.p)); };
        });
    }

    // ─── API Response Extractors ──────────────────────────────────────
    // Handles: { success, data: { items, meta } }  (providers, tenant_users)
    //          { success, data: { data, meta } }   (countries, cities, entities)
    //          { success, data: [] }               (direct array)
    //          { items: [] }  /  []                (legacy)
    function extractItems(r) {
        var d = r && r.data;
        if (d) {
            if (Array.isArray(d))                       return d;
            if (d.items && Array.isArray(d.items))      return d.items;
            if (d.data  && Array.isArray(d.data))       return d.data;
        }
        if (r && Array.isArray(r.items)) return r.items;
        if (Array.isArray(r))            return r;
        return [];
    }
    function extractMeta(r) {
        var d = r && r.data;
        if (d && d.meta) return d.meta;
        if (r && r.meta) return r.meta;
        return null;
    }
    function extractItem(r) {
        if (r && r.data && typeof r.data === 'object' && !Array.isArray(r.data)) return r.data;
        return r;
    }

    // ─── Generic ID → Label Lookup ────────────────────────────────────
    function bindIdLookup(inputId, nameSpanId, fetchUrl, labelFn) {
        var input = $(inputId), span = $(nameSpanId);
        if (!input || !span) return;
        var timer;
        input.addEventListener('input', function () {
            clearTimeout(timer);
            var val = this.value.trim();
            if (!val || isNaN(val) || +val < 1) {
                span.textContent = '';
                span.className   = 'provider-name-badge';
                return;
            }
            span.textContent = '...';
            span.className   = 'provider-name-badge loading';
            timer = setTimeout(async function () {
                try {
                    var r    = await api(fetchUrl(val));
                    var item = extractItem(r);
                    if (item && item.id) {
                        span.textContent = labelFn(item);
                        span.className   = 'provider-name-badge found';
                    } else {
                        span.textContent = 'Not found';
                        span.className   = 'provider-name-badge not-found';
                    }
                } catch (_) {
                    span.textContent = 'Not found';
                    span.className   = 'provider-name-badge not-found';
                }
            }, PROVIDER_LOOKUP_DEBOUNCE);
        });
    }

    function bindProviderLookup(inputId, nameSpanId) {
        bindIdLookup(inputId, nameSpanId,
            function (id) { return CFG.urls.providers + '/' + id + '?tenant_id=' + state.tenant; },
            function (p)  { return '#' + p.id + ' – ' + (p.provider_type || '') + (p.vehicle_type ? ' / ' + p.vehicle_type : ''); }
        );
    }
    function bindEntityLookup(inputId, nameSpanId) {
        bindIdLookup(inputId, nameSpanId,
            function (id) { return CFG.urls.entities + '/' + id + '?tenant_id=' + state.tenant; },
            function (e)  { return '#' + e.id + ' – ' + (e.store_name || e.name || ''); }
        );
    }
    function bindTenantUserLookup(inputId, nameSpanId) {
        bindIdLookup(inputId, nameSpanId,
            function (id) { return CFG.urls.tenant_users + '/' + id + '?tenant_id=' + state.tenant; },
            function (u)  { return '#' + u.id + ' – ' + (u.username || u.email || ''); }
        );
    }

    // ─── Country → City Cascade ───────────────────────────────────────
    async function loadCountries() {
        try {
            var r     = await api(CFG.urls.countries + '?limit=300&lang=' + state.lang);
            var items = extractItems(r);
            var html  = '<option value="">–</option>' +
                items.map(function (c) {
                    return '<option value="' + esc(c.id) + '">' + esc(c.name || c.iso2) + '</option>';
                }).join('');
            var el = $('zoneCountryId');
            if (el) el.innerHTML = html;
        } catch (e) { console.warn('[Delivery] loadCountries:', e.message); }
    }

    async function loadCitiesForCountry(countryId) {
        var el = $('zoneCityId');
        if (!el) return;
        el.innerHTML = '<option value="">Loading...</option>';
        try {
            var url = countryId
                ? CFG.urls.cities + '?country_id=' + encodeURIComponent(countryId) + '&limit=500&language=' + state.lang
                : CFG.urls.cities + '?limit=500&language=' + state.lang;
            var r     = await api(url);
            var items = extractItems(r);
            el.innerHTML = '<option value="">–</option>' +
                items.map(function (c) {
                    return '<option value="' + esc(c.id) + '">' + esc(c.name) + '</option>';
                }).join('');
        } catch (e) {
            el.innerHTML = '<option value="">Error loading cities</option>';
            console.warn('[Delivery] loadCitiesForCountry:', e.message);
        }
    }

    // ─── Coordinate Picker Modal ──────────────────────────────────────
    function initCoordPicker() {
        document.querySelectorAll('.btn-pick-map').forEach(function (btn) {
            btn.addEventListener('click', function () { openCoordPicker(this.dataset.lat, this.dataset.lng); });
        });
        document.querySelectorAll('.btn-use-gps').forEach(function (btn) {
            btn.addEventListener('click', function () { useGpsLocation(this.dataset.lat, this.dataset.lng); });
        });
        var closeBtn = $('coordModalClose');
        if (closeBtn) closeBtn.addEventListener('click', closeCoordPicker);
        var modal = $('coordPickerModal');
        if (modal) modal.addEventListener('click', function (e) { if (e.target === this) closeCoordPicker(); });
        var confirmBtn = $('coordConfirmBtn');
        if (confirmBtn) confirmBtn.addEventListener('click', function () {
            if (!coordPickerMarker || !coordPickerTarget) return;
            var ll    = coordPickerMarker.getLatLng();
            var latEl = $(coordPickerTarget.latId);
            var lngEl = $(coordPickerTarget.lngId);
            if (latEl) latEl.value = ll.lat.toFixed(7);
            if (lngEl) lngEl.value = ll.lng.toFixed(7);
            closeCoordPicker();
        });
        var searchBtn = $('coordSearchBtn');
        if (searchBtn) searchBtn.addEventListener('click', searchCoordPlace);
        var searchInput = $('coordSearchInput');
        if (searchInput) searchInput.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') { e.preventDefault(); searchCoordPlace(); }
        });
    }

    function useGpsLocation(latId, lngId) {
        if (!navigator.geolocation) { notify('Geolocation is not supported by your browser', 'error'); return; }
        notify('Getting your location…', 'info');
        navigator.geolocation.getCurrentPosition(
            function (pos) {
                var latEl = $(latId), lngEl = $(lngId);
                if (latEl) latEl.value = pos.coords.latitude.toFixed(7);
                if (lngEl) lngEl.value = pos.coords.longitude.toFixed(7);
                notify('Location acquired', 'success');
            },
            function (err) {
                var msg = err.code === 1 ? 'Location access denied'
                        : err.code === 2 ? 'Location unavailable'
                        : 'Location request timed out';
                notify(msg, 'error');
            },
            { enableHighAccuracy: true, timeout: GEOLOCATION_TIMEOUT_MS }
        );
    }

    function openCoordPicker(latId, lngId) {
        coordPickerTarget = { latId: latId, lngId: lngId };
        var modal = $('coordPickerModal');
        if (!modal) return;
        modal.style.display = '';
        if (!coordPickerMap) {
            coordPickerMap = L.map('coordPickerMap')
                .setView(CFG.mapCenter || [24.7136, 46.6753], CFG.mapZoom || 7);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© <a href="https://www.openstreetmap.org/">OpenStreetMap</a> contributors',
                maxZoom: 19
            }).addTo(coordPickerMap);
            coordPickerMap.on('click', function (e) { placePickerMarker(e.latlng.lat, e.latlng.lng); });
        }
        setTimeout(function () { coordPickerMap.invalidateSize(); }, 150);
        var latVal = parseFloat($(latId) && $(latId).value || '');
        var lngVal = parseFloat($(lngId) && $(lngId).value || '');
        if (!isNaN(latVal) && !isNaN(lngVal)) {
            placePickerMarker(latVal, lngVal);
            coordPickerMap.setView([latVal, lngVal], 12);
        } else {
            coordPickerMap.setView(CFG.mapCenter || [24.7136, 46.6753], 7);
            var cb = $('coordConfirmBtn'); if (cb) cb.disabled = true;
        }
        var si = $('coordSearchInput'); if (si) si.value = '';
    }

    function placePickerMarker(lat, lng) {
        if (!coordPickerMap) return;
        if (coordPickerMarker) {
            coordPickerMarker.setLatLng([lat, lng]);
        } else {
            coordPickerMarker = L.marker([lat, lng], { draggable: true }).addTo(coordPickerMap);
            coordPickerMarker.on('dragend', function () {
                var p = coordPickerMarker.getLatLng();
                updateCoordDisplay(p.lat, p.lng);
            });
        }
        updateCoordDisplay(lat, lng);
        var cb = $('coordConfirmBtn'); if (cb) cb.disabled = false;
    }

    function updateCoordDisplay(lat, lng) {
        var disp = $('coordDisplay');
        if (disp) disp.textContent = lat.toFixed(6) + ', ' + lng.toFixed(6);
    }

    function closeCoordPicker() {
        var modal = $('coordPickerModal');
        if (modal) modal.style.display = 'none';
        coordPickerTarget = null;
    }

    async function searchCoordPlace() {
        var input = $('coordSearchInput');
        var q = input && input.value.trim();
        if (!q) return;
        try {
            var url  = 'https://nominatim.openstreetmap.org/search?q=' + encodeURIComponent(q) + '&format=json&limit=1';
            var res  = await fetch(url, { headers: { 'Accept-Language': state.lang } });
            var data = await res.json();
            if (data && data.length > 0) {
                var lat = parseFloat(data[0].lat), lng = parseFloat(data[0].lon);
                placePickerMarker(lat, lng);
                coordPickerMap.setView([lat, lng], 13);
            } else { notify('Place not found', 'error'); }
        } catch (e) { notify('Search failed: ' + e.message, 'error'); }
    }

    // ─── Generic Module Factory ───────────────────────────────────────
    function createModule(name, url, cfg) {
        var s = state[name];
        var mod = {
            cfg: cfg,
            load: async function (page) {
                page = page || 1;
                s.page = page; s.loaded = true;
                if (cfg.loading)   { var lel = $(cfg.loading);   if (lel) lel.style.display = ''; }
                if (cfg.container) { var cel = $(cfg.container);  if (cel) cel.style.display = 'none'; }
                if (cfg.empty)     { var eel = $(cfg.empty);      if (eel) eel.style.display = 'none'; }
                try {
                    var p = new URLSearchParams(Object.assign(
                        { page: page, limit: LIMIT, tenant_id: state.tenant, lang: state.lang },
                        s.filters
                    ));
                    var r     = await api(url + '?' + p.toString());
                    var items = extractItems(r);
                    var meta  = extractMeta(r);
                    var total = (meta && meta.total !== undefined ? meta.total : null) || r.total || items.length;
                    s.items = items; s.total = total;
                    if (cfg.loading) { var lel2 = $(cfg.loading); if (lel2) lel2.style.display = 'none'; }
                    if (!items.length) {
                        if (cfg.empty) { var eel2 = $(cfg.empty); if (eel2) eel2.style.display = ''; }
                        return;
                    }
                    if (cfg.container) { var cel2 = $(cfg.container); if (cel2) cel2.style.display = ''; }
                    if (cfg.tbody)     { var tb   = $(cfg.tbody);     if (tb)   tb.innerHTML = items.map(cfg.row).join(''); }
                    pagination($(cfg.pagination), $(cfg.info), total, page, function (n) { mod.load(n); });
                    if (cfg.afterLoad) cfg.afterLoad(items);
                } catch (e) {
                    if (cfg.loading)   { var lel3 = $(cfg.loading);   if (lel3)  lel3.style.display = 'none'; }
                    if (cfg.container) { var cel3 = $(cfg.container);  if (cel3)  cel3.style.display = ''; }
                    if (cfg.tbody)     showTableError(cfg.tbody, e.message);
                    notify(e.message, 'error');
                }
            },
            applyFilters: function () { s.filters = cfg.getFilters(); mod.load(1); },
            resetFilters:  function () { if (cfg.reset) cfg.reset(); s.filters = {}; mod.load(1); },
            showForm: function (item) {
                item = item || {};
                var fc = $(cfg.formContainer); if (!fc) return;
                fc.style.display = '';
                if (cfg.setForm) cfg.setForm(item);
            },
            hideForm: function () { var fc = $(cfg.formContainer); if (fc) fc.style.display = 'none'; },
            save: async function (e) {
                e.preventDefault();
                if (s.saving) return;
                s.saving = true;
                var body = cfg.getFormData ? cfg.getFormData() : null;
                if (!body) { s.saving = false; return; }
                var id = cfg.getId ? cfg.getId() : null;
                try {
                    await api(id ? url + '/' + id : url, { method: id ? 'PUT' : 'POST', json: body });
                    notify('Saved successfully', 'success');
                    mod.hideForm();
                    mod.load(s.page);
                } catch (err) { notify(err.message, 'error'); }
                finally { s.saving = false; }
            },
            del: async function (id) {
                if (!confirm('Delete this item?')) return;
                try {
                    var delUrl = cfg.delUrl ? cfg.delUrl(id) : url + '/' + id;
                    await api(delUrl, { method: 'DELETE' });
                    notify('Deleted successfully', 'success');
                    mod.load(s.page);
                } catch (e) { notify(e.message, 'error'); }
            },
            bindEvents: function () {
                if ($(cfg.addBtn))    $(cfg.addBtn).addEventListener('click',    function ()  { mod.showForm(); });
                if ($(cfg.closeBtn))  $(cfg.closeBtn).addEventListener('click',  function ()  { mod.hideForm(); });
                if ($(cfg.cancelBtn)) $(cfg.cancelBtn).addEventListener('click', function ()  { mod.hideForm(); });
                if ($(cfg.form))      $(cfg.form).addEventListener('submit',     function (e) { mod.save(e); });
                if ($(cfg.applyBtn))  $(cfg.applyBtn).addEventListener('click',  function ()  { mod.applyFilters(); });
                if ($(cfg.resetBtn))  $(cfg.resetBtn).addEventListener('click',  function ()  { mod.resetFilters(); });
            }
        };
        return mod;
    }

    // ─── Zones Map ────────────────────────────────────────────────────
    function initZonesMap() {
        var mapEl = $('zonesMap');
        if (!mapEl || typeof L === 'undefined' || zonesMap) return;

        ensureMapElementHeight();

        // On mobile the element may still be hidden during first init — retry silently
        if (!isElementVisible(mapEl)) {
            setTimeout(initZonesMap, 300);
            return;
        }

        zonesMap = L.map('zonesMap', { tap: true, tapTolerance: 20 })
            .setView(CFG.mapCenter || [24.7136, 46.6753], CFG.mapZoom || 5);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© <a href="https://www.openstreetmap.org/">OpenStreetMap</a> contributors',
            maxZoom: 19
        }).addTo(zonesMap);

        drawnItems = L.featureGroup().addTo(zonesMap);

        if (L.Control && L.Control.Draw) {
            new L.Control.Draw({
                position: 'topright',
                draw: {
                    polygon:    { allowIntersection: false, showArea: true, shapeOptions: { color: '#2563eb', fillOpacity: 0.15 } },
                    rectangle:  { shapeOptions: { color: '#2563eb', fillOpacity: 0.15 } },
                    circle:     { shapeOptions: { color: '#2563eb', fillOpacity: 0.15 } },
                    marker:       false,
                    polyline:     false,
                    circlemarker: false
                },
                edit: { featureGroup: drawnItems, remove: true }
            }).addTo(zonesMap);
        }

        zonesMap.on(L.Draw.Event.CREATED, function (e) {
            drawnItems.clearLayers();
            drawnItems.addLayer(e.layer);
            populateZoneGeoFromLayer(e.layer);
        });
        zonesMap.on(L.Draw.Event.EDITED, function (e) {
            e.layers.eachLayer(function (layer) { populateZoneGeoFromLayer(layer); });
        });
        zonesMap.on(L.Draw.Event.DELETED, function () {
            if (drawnItems.getLayers().length === 0) { var gf = $('zoneGeoJson'); if (gf) gf.value = ''; }
        });

        var geoInput = $('zoneGeoJson');
        if (geoInput) geoInput.addEventListener('input', function () {
            var txt = this.value.trim();
            drawnItems.clearLayers();
            if (!txt) return;
            try { drawGeoOnMap(JSON.parse(txt)); } catch (_) {}
        });

        bindZonesMapControls();

        // Extended delays for mobile layout-settle
        MAP_INVALIDATE_DELAYS_MS.forEach(function (ms) {
            setTimeout(function () { if (zonesMap) zonesMap.invalidateSize(); }, ms);
        });

        bindMapAutoResize();
    }

    function populateZoneGeoFromLayer(layer) {
        var gf = $('zoneGeoJson'), latEl = $('zoneLat'), lngEl = $('zoneLng'), radEl = $('zoneRadius');
        var geo = null, type = null;
        if (layer instanceof L.Circle) {
            var c = layer.getLatLng(), r = layer.getRadius();
            geo  = { type: 'Circle', center: [c.lat, c.lng], radius: Math.round(r) };
            type = 'radius';
            if (latEl) latEl.value = c.lat.toFixed(7);
            if (lngEl) lngEl.value = c.lng.toFixed(7);
            if (radEl) radEl.value = (r / 1000).toFixed(2);
        } else if (layer instanceof L.Rectangle) {
            var b = layer.getBounds();
            geo  = { type: 'Rectangle', bounds: [[b.getSouth(), b.getWest()], [b.getNorth(), b.getEast()]] };
            type = 'polygon';
        } else if (layer instanceof L.Polygon) {
            var latlngs = layer.getLatLngs()[0].map(function (ll) { return [ll.lng, ll.lat]; });
            latlngs.push(latlngs[0]);
            geo  = { type: 'Polygon', coordinates: [latlngs] };
            type = 'polygon';
        }
        if (gf && geo) gf.value = JSON.stringify(geo, null, 2);
        if (type) { var zt = $('zoneType'); if (zt) zt.value = type; }
        toggleRadiusFields();
    }

    function drawGeoOnMap(parsed) {
        if (!parsed || !parsed.type || !zonesMap) return;
        var t = (parsed.type || '').toLowerCase(), layer = null;
        if (t === 'polygon') {
            var ll = ((parsed.coordinates && parsed.coordinates[0]) || []).map(function (pt) { return [pt[1], pt[0]]; });
            layer = L.polygon(ll, { color: '#2563eb', fillOpacity: 0.15 });
        } else if (t === 'rectangle') {
            var b = parsed.bounds;
            if (b && b.length >= 2) layer = L.rectangle([[b[0][0], b[0][1]], [b[1][0], b[1][1]]], { color: '#2563eb', fillOpacity: 0.15 });
        } else if (t === 'circle' || t === 'radius') {
            if (parsed.center && parsed.radius) layer = L.circle([parsed.center[0], parsed.center[1]], { radius: parsed.radius, color: '#2563eb', fillOpacity: 0.15 });
        }
        if (layer) {
            drawnItems.addLayer(layer);
            try { zonesMap.fitBounds(layer.getBounds(), { padding: [20, 20] }); } catch (_) {}
        }
    }

    function renderZonesOnMap(zones) {
        if (!zonesMap || !drawnItems) return;
        zoneLayerMap.forEach(function (l) { drawnItems.removeLayer(l); });
        zoneLayerMap.clear();
        zones.forEach(function (z) {
            if (!z.zone_value) return;
            var parsed; try { parsed = JSON.parse(z.zone_value); } catch (_) { return; }
            var layer = null, t = (parsed.type || '').toLowerCase();
            if (t === 'polygon') {
                var ll = ((parsed.coordinates && parsed.coordinates[0]) || []).map(function (pt) { return [pt[1], pt[0]]; });
                layer = L.polygon(ll, { color: '#10b981', fillOpacity: 0.1 });
            } else if (t === 'rectangle') {
                var b = parsed.bounds;
                if (b) layer = L.rectangle([[b[0][0], b[0][1]], [b[1][0], b[1][1]]], { color: '#10b981', fillOpacity: 0.1 });
            } else if (t === 'circle' || t === 'radius') {
                if (parsed.center && parsed.radius) layer = L.circle([parsed.center[0], parsed.center[1]], { radius: parsed.radius, color: '#10b981', fillOpacity: 0.1 });
            }
            if (layer) {
                drawnItems.addLayer(layer);
                layer.bindPopup('<strong>' + esc(z.zone_name) + '</strong><br>Type: ' + esc(z.zone_type) + '<br>Fee: ' + esc(z.delivery_fee));
                layer.on('click', (function (zid) { return function () { zonesMod.edit(zid); }; })(z.id));
                zoneLayerMap.set(String(z.id), layer);
            }
        });
        if (drawnItems.getLayers().length > 0) {
            try { zonesMap.fitBounds(drawnItems.getBounds(), { padding: [30, 30], maxZoom: 14 }); } catch (_) {}
        }
        // Extra invalidate after data render
        setTimeout(function () { if (zonesMap) zonesMap.invalidateSize(); }, 200);
    }

    function toggleRadiusFields() {
        var zt = $('zoneType'), rf = $('radiusFields');
        if (!zt || !rf) return;
        rf.style.display = zt.value === 'radius' ? '' : 'none';
    }

    function bindMapAutoResize() {
        var mapEl = $('zonesMap');
        if (!mapEl || !window.ResizeObserver) return;
        if (mapResizeObserver) { try { mapResizeObserver.disconnect(); } catch (_) {} }
        mapResizeObserver = new ResizeObserver(function () { scheduleMapInvalidate(); });
        mapResizeObserver.observe(mapEl);
    }

    function cfgText(key, fallback) {
        // Check CFG.strings first (server-injected translations), then CFG.i18n for backwards compat
        if (CFG.strings) {
            var parts = key.split('.'), val = CFG.strings;
            for (var i = 0; i < parts.length; i++) {
                if (val && typeof val === 'object' && parts[i] in val) val = val[parts[i]];
                else { val = null; break; }
            }
            if (typeof val === 'string') return val;
        }
        return (CFG.i18n && CFG.i18n[key]) ? CFG.i18n[key] : fallback;
    }

    function bindZonesMapControls() {
        var fullscreenBtn = $('zonesFullscreenBtn');
        var locateBtn     = $('zonesLocateBtn');
        var panel         = document.querySelector('.zones-map-panel');

        if (fullscreenBtn && !fullscreenBtn.dataset.bound) {
            fullscreenBtn.dataset.bound = '1';
            fullscreenBtn.addEventListener('click', function () {
                if (!panel) return;
                var on = panel.classList.toggle('is-fullscreen');
                fullscreenBtn.innerHTML = on
                    ? '<i class="fas fa-compress-arrows-alt" aria-hidden="true"></i><span>' + esc(cfgText('mapExitFullscreen', 'Exit fullscreen')) + '</span>'
                    : '<i class="fas fa-expand-arrows-alt" aria-hidden="true"></i><span>' + esc(cfgText('mapFullscreen', 'Fullscreen map')) + '</span>';
                fullscreenBtn.setAttribute('aria-pressed', on ? 'true' : 'false');
                scheduleMapInvalidate();
            });
        }

        if (locateBtn && !locateBtn.dataset.bound) {
            locateBtn.dataset.bound = '1';
            locateBtn.addEventListener('click', function () {
                if (!navigator.geolocation || !zonesMap) {
                    notify(cfgText('mapGeolocationUnsupported', 'Geolocation is not supported by your browser'), 'error');
                    return;
                }
                locateBtn.disabled = true;
                notify(cfgText('mapGettingLocation', 'Getting your location…'), 'info');
                navigator.geolocation.getCurrentPosition(
                    function (pos) {
                        var lat = pos.coords.latitude;
                        var lng = pos.coords.longitude;
                        zonesMap.setView([lat, lng], Math.max(zonesMap.getZoom(), GEOLOCATION_MIN_ZOOM));
                        if (zonesLocateMarker && zonesMap) zonesMap.removeLayer(zonesLocateMarker);
                        zonesLocateMarker = L.marker([lat, lng]).addTo(zonesMap)
                            .bindPopup(esc(cfgText('mapYourLocation', 'Your location')));
                        zonesLocateMarker.openPopup();
                        locateBtn.disabled = false;
                        notify(cfgText('mapLocationAcquired', 'Location acquired'), 'success');
                    },
                    function () {
                        locateBtn.disabled = false;
                        notify(cfgText('mapLocationUnavailable', 'Unable to get your location'), 'error');
                    },
                    { enableHighAccuracy: true, timeout: GEOLOCATION_TIMEOUT_MS }
                );
            });
        }
    }

    // ─── Zones Module ─────────────────────────────────────────────────
    var zonesMod = Object.assign(
        createModule('zones', CFG.urls.zones, {
            loading: null, container: null, empty: null,
            pagination: 'zonesPagination', info: 'zonesPaginationInfo',
            formContainer: 'zoneFormContainer', form: 'zoneForm',
            addBtn: 'zonesAddBtn', closeBtn: 'zoneCloseForm', cancelBtn: 'zoneCancelBtn',
            applyBtn: 'zonesApplyFilter', resetBtn: 'zonesResetFilter',
            getId: function () { return $('zoneId') && $('zoneId').value || null; },
            getFilters: function () {
                return {
                    search:    $('zonesSearch')      && $('zonesSearch').value      || '',
                    zone_type: $('zonesTypeFilter')   && $('zonesTypeFilter').value   || '',
                    is_active: $('zonesActiveFilter') ? $('zonesActiveFilter').value  : ''
                };
            },
            reset: function () {
                ['zonesSearch', 'zonesTypeFilter', 'zonesActiveFilter']
                    .forEach(function (id) { var el = $(id); if (el) el.value = ''; });
            },
            setForm: function (z) {
                if ($('zoneId'))           $('zoneId').value           = z.id                || '';
                if ($('zoneName'))         $('zoneName').value         = z.zone_name         || '';
                if ($('zoneType'))         $('zoneType').value         = z.zone_type         || 'city';
                if ($('zoneFee'))          $('zoneFee').value          = z.delivery_fee      || '0.00';
                if ($('zoneTime'))         $('zoneTime').value         = z.estimated_minutes != null ? z.estimated_minutes : 45;
                if ($('zoneActive'))       $('zoneActive').checked     = !!+z.is_active;
                if ($('zoneLat'))          $('zoneLat').value          = z.center_lat        || '';
                if ($('zoneLng'))          $('zoneLng').value          = z.center_lng        || '';
                if ($('zoneRadius'))       $('zoneRadius').value       = z.radius_km         || '';
                if ($('zoneMinOrder'))     $('zoneMinOrder').value     = z.min_order_value   || '';
                if ($('zoneFreeDelivery')) $('zoneFreeDelivery').value = z.free_delivery_over|| '';
                if ($('zoneGeoJson'))      $('zoneGeoJson').value      = z.zone_value        || '';
                if ($('zoneCityId') && z.city_id) $('zoneCityId').value = z.city_id;
                if ($('zoneProviderId'))   $('zoneProviderId').value   = z.provider_id       || '';
                if ($('zoneProviderName') && z.provider_id) {
                    $('zoneProviderName').textContent = '#' + z.provider_id;
                    $('zoneProviderName').className   = 'provider-name-badge found';
                } else if ($('zoneProviderName')) {
                    $('zoneProviderName').textContent = '';
                    $('zoneProviderName').className   = 'provider-name-badge';
                }
                toggleRadiusFields();
                if (drawnItems) drawnItems.clearLayers();
                if (z.zone_value) { try { drawGeoOnMap(JSON.parse(z.zone_value)); } catch (_) {} }
            },
            getFormData: function () {
                return {
                    id:                 $('zoneId')          && $('zoneId').value          || null,
                    zone_name:          $('zoneName')        && $('zoneName').value        || '',
                    zone_type:          $('zoneType')        && $('zoneType').value        || 'city',
                    city_id:            $('zoneCityId')      && $('zoneCityId').value      || null,
                    provider_id:        $('zoneProviderId')  && $('zoneProviderId').value  || null,
                    delivery_fee:       $('zoneFee')         && $('zoneFee').value         || '0.00',
                    estimated_minutes:  $('zoneTime')        && $('zoneTime').value        || 45,
                    center_lat:         $('zoneLat')         && $('zoneLat').value         || null,
                    center_lng:         $('zoneLng')         && $('zoneLng').value         || null,
                    radius_km:          $('zoneRadius')      && $('zoneRadius').value      || null,
                    min_order_value:    $('zoneMinOrder')    && $('zoneMinOrder').value    || null,
                    free_delivery_over: $('zoneFreeDelivery')&& $('zoneFreeDelivery').value|| null,
                    zone_value:         $('zoneGeoJson')     && $('zoneGeoJson').value     || null,
                    is_active:          $('zoneActive') ? ($('zoneActive').checked ? 1 : 0) : 1,
                    tenant_id:          state.tenant
                };
            },
            afterLoad: function (items) { renderZonesListSidebar(items); },
            row: function () { return ''; }
        }),
        {
            edit: async function (id) {
                try {
                    var r = await api(CFG.urls.zones + '/' + id + '?tenant_id=' + state.tenant);
                    zonesMod.showForm(extractItem(r));
                } catch (e) { notify(e.message, 'error'); }
            }
        }
    );

    // Override load to render sidebar + map
    zonesMod.load = async function (page) {
        page = page || 1;
        var s = state.zones;
        s.page = page; s.loaded = true;
        var loadingEl = $('zonesListLoading');
        var itemsEl   = $('zonesListItems');
        var emptyEl   = $('zonesListEmpty');
        if (loadingEl) loadingEl.style.display = '';
        if (itemsEl)   itemsEl.innerHTML       = '';
        if (emptyEl)   emptyEl.style.display   = 'none';
        try {
            var p = new URLSearchParams(Object.assign(
                { page: page, limit: LIMIT, tenant_id: state.tenant, lang: state.lang },
                s.filters
            ));
            var r     = await api(CFG.urls.zones + '?' + p.toString());
            var items = extractItems(r);
            var meta  = extractMeta(r);
            var total = (meta && meta.total !== undefined ? meta.total : null) || r.total || items.length;
            s.items = items; s.total = total;
            if (loadingEl) loadingEl.style.display = 'none';
            if (!items.length) { if (emptyEl) emptyEl.style.display = ''; return; }
            renderZonesListSidebar(items);
            renderZonesOnMap(items);
            pagination($('zonesPagination'), $('zonesPaginationInfo'), total, page, function (n) { zonesMod.load(n); });
        } catch (e) {
            if (loadingEl) loadingEl.style.display = 'none';
            if (itemsEl)   itemsEl.innerHTML = '<p class="table-error-row"><i class="fas fa-exclamation-triangle"></i> ' + esc(e.message) + '</p>';
            notify(e.message, 'error');
        }
    };

    function renderZonesListSidebar(items) {
        var el = $('zonesListItems');
        if (!el) return;
        el.innerHTML = items.map(function (z) {
            return '<div class="zone-list-item' + (+z.is_active ? '' : ' inactive') + '" data-id="' + esc(z.id) + '">' +
                '<div class="zone-item-info">' +
                    '<strong>' + esc(z.zone_name) + '</strong>' +
                    '<span class="zone-item-meta">' + esc(z.zone_type) + ' · ' + esc(z.delivery_fee) + ' · ' + esc(z.estimated_minutes) + ' min</span>' +
                '</div>' +
                '<div class="zone-item-actions">' +
                (CFG.canEdit   ? '<button class="btn btn-sm btn-icon btn-primary" onclick="Delivery.editZone(' + z.id + ')" title="Edit"><i class="fas fa-edit"></i></button>'   : '') +
                (CFG.canDelete ? '<button class="btn btn-sm btn-icon btn-danger" onclick="Delivery.delZone(' + z.id + ')" title="Delete"><i class="fas fa-trash"></i></button>' : '') +
                '</div></div>';
        }).join('');
        el.querySelectorAll('.zone-list-item').forEach(function (item) {
            item.addEventListener('click', function (e) {
                if (e.target.closest('button')) return;
                var id    = item.dataset.id;
                var layer = zoneLayerMap.get(String(id));
                if (layer && zonesMap) {
                    try { zonesMap.fitBounds(layer.getBounds(), { padding: [30, 30], maxZoom: 14 }); } catch (_) {}
                    layer.openPopup();
                }
            });
        });
    }

    // ─── Providers Module ─────────────────────────────────────────────
    var providersMod = Object.assign(
        createModule('providers', CFG.urls.providers, {
            loading: 'providersTableLoading', container: 'providersTableContainer', empty: 'providersEmptyState',
            tbody: 'providersTableBody', pagination: 'providersPagination', info: 'providersPaginationInfo',
            formContainer: 'providerFormContainer', form: 'providerForm',
            addBtn: 'providersAddBtn', closeBtn: 'providerCloseForm', cancelBtn: 'providerCancelBtn',
            applyBtn: 'providersApplyFilter', resetBtn: 'providersResetFilter',
            getId: function () { return $('providerId') && $('providerId').value || null; },
            getFilters: function () {
                return {
                    search:        $('providersSearch')       && $('providersSearch').value       || '',
                    provider_type: $('providersTypeFilter')   && $('providersTypeFilter').value   || '',
                    vehicle_type:  $('providersVehicleFilter')&& $('providersVehicleFilter').value|| '',
                    is_active:     $('providersActiveFilter') ? $('providersActiveFilter').value  : ''
                };
            },
            reset: function () {
                ['providersSearch', 'providersTypeFilter', 'providersVehicleFilter', 'providersActiveFilter']
                    .forEach(function (id) { var el = $(id); if (el) el.value = ''; });
            },
            setForm: function (p) {
                if ($('providerId'))          $('providerId').value          = p.id             || '';
                if ($('providerType'))        $('providerType').value        = p.provider_type  || 'company';
                if ($('providerVehicle'))     $('providerVehicle').value     = p.vehicle_type   || 'bike';
                if ($('providerLicense'))     $('providerLicense').value     = p.license_number || '';
                if ($('providerOnline'))      $('providerOnline').checked    = !!+p.is_online;
                if ($('providerActive'))      $('providerActive').checked    = !!+p.is_active;
                if ($('providerEntityId'))    $('providerEntityId').value    = p.entity_id      || '';
                if ($('providerEntityName') && p.entity_id) {
                    $('providerEntityName').textContent = '#' + p.entity_id;
                    $('providerEntityName').className   = 'provider-name-badge found';
                } else if ($('providerEntityName')) {
                    $('providerEntityName').textContent = '';
                    $('providerEntityName').className   = 'provider-name-badge';
                }
                if ($('providerTenantUserId')) $('providerTenantUserId').value = p.tenant_user_id || '';
                if ($('providerTenantUserName') && p.tenant_user_id) {
                    $('providerTenantUserName').textContent = '#' + p.tenant_user_id;
                    $('providerTenantUserName').className   = 'provider-name-badge found';
                } else if ($('providerTenantUserName')) {
                    $('providerTenantUserName').textContent = '';
                    $('providerTenantUserName').className   = 'provider-name-badge';
                }
            },
            getFormData: function () {
                return {
                    id:             $('providerId')         && $('providerId').value         || null,
                    provider_type:  $('providerType')       && $('providerType').value       || 'company',
                    vehicle_type:   $('providerVehicle')    && $('providerVehicle').value    || 'bike',
                    license_number: $('providerLicense')    && $('providerLicense').value    || null,
                    is_online:      $('providerOnline')     ? ($('providerOnline').checked  ? 1 : 0) : 0,
                    is_active:      $('providerActive')     ? ($('providerActive').checked  ? 1 : 0) : 1,
                    entity_id:      $('providerEntityId')   && $('providerEntityId').value   || null,
                    tenant_user_id: $('providerTenantUserId')&& $('providerTenantUserId').value|| null,
                    tenant_id:      state.tenant
                };
            },
            row: function (p) {
                return '<tr>' +
                    '<td>' + esc(p.id) + '</td>' +
                    '<td>' + esc(p.provider_type) + '</td>' +
                    '<td>' + esc(p.vehicle_type) + '</td>' +
                    '<td>' + badge(p.is_online ? 'Online' : 'Offline', { Online: 'success', Offline: 'secondary' }) + '</td>' +
                    '<td>' + esc(p.rating != null ? p.rating : '–') + '</td>' +
                    '<td>' + esc(p.total_deliveries || 0) + '</td>' +
                    '<td class="actions">' +
                    (CFG.canEdit   ? '<button class="btn btn-sm btn-primary" onclick="Delivery.editProvider(' + p.id + ')"><i class="fas fa-edit"></i></button>'   : '') +
                    (CFG.canDelete ? '<button class="btn btn-sm btn-danger"  onclick="Delivery.delProvider(' + p.id + ')"><i class="fas fa-trash"></i></button>'   : '') +
                    '</td></tr>';
            }
        }),
        {
            edit: async function (id) {
                var r = await api(CFG.urls.providers + '/' + id + '?tenant_id=' + state.tenant);
                providersMod.showForm(extractItem(r));
            }
        }
    );

    // ─── Orders Module ────────────────────────────────────────────────
    var ordersMod = Object.assign(
        createModule('orders', CFG.urls.orders, {
            loading: 'ordersTableLoading', container: 'ordersTableContainer', empty: 'ordersEmptyState',
            tbody: 'ordersTableBody', pagination: 'ordersPagination', info: 'ordersPaginationInfo',
            formContainer: 'orderFormContainer', form: 'orderForm',
            addBtn: 'ordersAddBtn', closeBtn: 'orderCloseForm', cancelBtn: 'orderCancelBtn',
            applyBtn: 'ordersApplyFilter', resetBtn: 'ordersResetFilter',
            getId: function () { return $('orderId') && $('orderId').value || null; },
            getFilters: function () {
                return {
                    delivery_status:  $('ordersStatusFilter')   && $('ordersStatusFilter').value   || '',
                    provider_id:      $('ordersProviderFilter')  && $('ordersProviderFilter').value  || '',
                    delivery_zone_id: $('ordersZoneFilter')      && $('ordersZoneFilter').value      || ''
                };
            },
            reset: function () {
                ['ordersStatusFilter', 'ordersProviderFilter', 'ordersZoneFilter']
                    .forEach(function (id) { var el = $(id); if (el) el.value = ''; });
            },
            setForm: function (o) {
                if ($('orderId'))           $('orderId').value           = o.id                  || '';
                if ($('orderOrderId'))      $('orderOrderId').value      = o.order_id            || '';
                if ($('orderProviderId'))   $('orderProviderId').value   = o.provider_id         || '';
                if ($('orderProviderName') && o.provider_id) {
                    $('orderProviderName').textContent = '#' + o.provider_id;
                    $('orderProviderName').className   = 'provider-name-badge found';
                }
                if ($('orderStatus'))       $('orderStatus').value       = o.delivery_status     || 'pending';
                if ($('orderPickup'))       $('orderPickup').value       = o.pickup_address_id   || '';
                if ($('orderDropoff'))      $('orderDropoff').value      = o.dropoff_address_id  || '';
                if ($('orderFee'))          $('orderFee').value          = o.delivery_fee        || '0.00';
                if ($('orderCalcFee'))      $('orderCalcFee').value      = o.calculated_fee      || '0.00';
                if ($('orderPayout'))       $('orderPayout').value       = o.provider_payout     || '0.00';
                if ($('orderZoneId'))       $('orderZoneId').value       = o.delivery_zone_id    || '';
                if ($('orderCancelledBy'))  $('orderCancelledBy').value  = o.cancelled_by        || '';
                if ($('orderCancelReason')) $('orderCancelReason').value = o.cancellation_reason || '';
                var cf = $('cancelFields');
                if (cf) cf.style.display = o.delivery_status === 'cancelled' ? '' : 'none';
            },
            getFormData: function () {
                return {
                    id:                  $('orderId')          && $('orderId').value          || null,
                    order_id:            $('orderOrderId')     && $('orderOrderId').value     || '',
                    provider_id:         $('orderProviderId')  && $('orderProviderId').value  || null,
                    delivery_status:     $('orderStatus')      && $('orderStatus').value      || 'pending',
                    pickup_address_id:   $('orderPickup')      && $('orderPickup').value      || '',
                    dropoff_address_id:  $('orderDropoff')     && $('orderDropoff').value     || '',
                    delivery_fee:        $('orderFee')         && $('orderFee').value         || '0.00',
                    calculated_fee:      $('orderCalcFee')     && $('orderCalcFee').value     || '0.00',
                    provider_payout:     $('orderPayout')      && $('orderPayout').value      || '0.00',
                    delivery_zone_id:    $('orderZoneId')      && $('orderZoneId').value      || null,
                    cancelled_by:        $('orderCancelledBy') && $('orderCancelledBy').value || null,
                    cancellation_reason: $('orderCancelReason')&& $('orderCancelReason').value|| null,
                    tenant_id:           state.tenant
                };
            },
            row: function (o) {
                return '<tr>' +
                    '<td>' + esc(o.id) + '</td>' +
                    '<td>' + esc(o.order_id) + '</td>' +
                    '<td>' + esc(o.provider_id || '–') + '</td>' +
                    '<td>' + badge(o.delivery_status, { pending: 'secondary', assigned: 'primary', accepted: 'primary', picked_up: 'warning', on_the_way: 'warning', delivered: 'success', cancelled: 'danger' }) + '</td>' +
                    '<td>' + esc(o.delivery_fee) + '</td>' +
                    '<td>' + esc(o.delivery_zone_id || '–') + '</td>' +
                    '<td>' + esc(o.created_at || '') + '</td>' +
                    '<td class="actions">' +
                    (CFG.canEdit ? '<button class="btn btn-sm btn-primary" onclick="Delivery.editOrder(' + o.id + ')"><i class="fas fa-edit"></i></button>' : '') +
                    '</td></tr>';
            }
        }),
        {
            edit: async function (id) {
                var r = await api(CFG.urls.orders + '/' + id + '?tenant_id=' + state.tenant);
                ordersMod.showForm(extractItem(r));
            }
        }
    );

    var orderStatusEl = $('orderStatus');
    if (orderStatusEl) orderStatusEl.addEventListener('change', function () {
        var cf = $('cancelFields');
        if (cf) cf.style.display = this.value === 'cancelled' ? '' : 'none';
    });

    // ─── Locations Module ─────────────────────────────────────────────
    var locationsMod = Object.assign(
        createModule('locations', CFG.urls.locations, {
            loading: 'locationsTableLoading', container: 'locationsTableContainer', empty: 'locationsEmptyState',
            tbody: 'locationsTableBody', pagination: 'locationsPagination', info: 'locationsPaginationInfo',
            formContainer: 'locationFormContainer', form: 'locationForm',
            addBtn: 'locationsAddBtn', closeBtn: 'locationCloseForm', cancelBtn: 'locationCancelBtn',
            applyBtn: 'locationsApplyFilter', resetBtn: 'locationsResetFilter',
            getId: function () { return $('locationId') && $('locationId').value || null; },
            getFilters: function () {
                return { provider_id: $('locationsProviderFilter') && $('locationsProviderFilter').value || '' };
            },
            reset: function () { var el = $('locationsProviderFilter'); if (el) el.value = ''; },
            setForm: function (l) {
                if ($('locationId'))         $('locationId').value         = l.id          || '';
                if ($('locationProviderId')) $('locationProviderId').value = l.provider_id || '';
                if ($('locationProviderName') && l.provider_id) {
                    $('locationProviderName').textContent = '#' + l.provider_id;
                    $('locationProviderName').className   = 'provider-name-badge found';
                }
                if ($('locationLat'))        $('locationLat').value        = l.latitude    || '';
                if ($('locationLng'))        $('locationLng').value        = l.longitude   || '';
            },
            getFormData: function () {
                return {
                    id:          $('locationId')         && $('locationId').value         || null,
                    provider_id: $('locationProviderId') && $('locationProviderId').value || '',
                    latitude:    $('locationLat')        && $('locationLat').value        || '',
                    longitude:   $('locationLng')        && $('locationLng').value        || ''
                };
            },
            row: function (l) {
                return '<tr>' +
                    '<td>' + esc(l.id) + '</td>' +
                    '<td>' + esc(l.provider_id) + '</td>' +
                    '<td>' + esc(l.latitude) + '</td>' +
                    '<td>' + esc(l.longitude) + '</td>' +
                    '<td>' + esc(l.updated_at || '') + '</td>' +
                    '<td class="actions">' +
                    (CFG.canEdit   ? '<button class="btn btn-sm btn-primary" onclick="Delivery.editLocation(' + l.id + ')"><i class="fas fa-edit"></i></button>'   : '') +
                    (CFG.canDelete ? '<button class="btn btn-sm btn-danger"  onclick="Delivery.delLocation(' + l.id + ')"><i class="fas fa-trash"></i></button>'   : '') +
                    '</td></tr>';
            }
        }),
        {
            edit: async function (id) {
                var r = await api(CFG.urls.locations + '/' + id + '?tenant_id=' + state.tenant);
                locationsMod.showForm(extractItem(r));
            }
        }
    );

    // ─── Tracking Module ──────────────────────────────────────────────
    var trackingMod = createModule('tracking', CFG.urls.tracking, {
        loading: 'trackingTableLoading', container: 'trackingTableContainer', empty: 'trackingEmptyState',
        tbody: 'trackingTableBody', pagination: 'trackingPagination', info: 'trackingPaginationInfo',
        formContainer: 'trackingFormContainer', form: 'trackingForm',
        addBtn: 'trackingAddBtn', closeBtn: 'trackingCloseForm', cancelBtn: 'trackingCancelBtn',
        applyBtn: 'trackingApplyFilter', resetBtn: 'trackingResetFilter',
        getId: function () { return $('trackingId') && $('trackingId').value || null; },
        getFilters: function () {
            return {
                delivery_order_id: $('trackingOrderFilter')    && $('trackingOrderFilter').value    || '',
                provider_id:       $('trackingProviderFilter') && $('trackingProviderFilter').value || ''
            };
        },
        reset: function () {
            ['trackingOrderFilter', 'trackingProviderFilter']
                .forEach(function (id) { var el = $(id); if (el) el.value = ''; });
        },
        setForm: function (t) {
            if ($('trackingId'))          $('trackingId').value          = t.id                || '';
            if ($('trackingOrderId'))     $('trackingOrderId').value     = t.delivery_order_id || '';
            if ($('trackingProviderId'))  $('trackingProviderId').value  = t.provider_id       || '';
            if ($('trackingProviderName') && t.provider_id) {
                $('trackingProviderName').textContent = '#' + t.provider_id;
                $('trackingProviderName').className   = 'provider-name-badge found';
            }
            if ($('trackingLat'))         $('trackingLat').value         = t.latitude          || '';
            if ($('trackingLng'))         $('trackingLng').value         = t.longitude         || '';
            if ($('trackingNote'))        $('trackingNote').value        = t.status_note       || '';
        },
        getFormData: function () {
            return {
                id:                $('trackingId')        && $('trackingId').value        || null,
                delivery_order_id: $('trackingOrderId')   && $('trackingOrderId').value   || '',
                provider_id:       $('trackingProviderId')&& $('trackingProviderId').value || null,
                latitude:          $('trackingLat')       && $('trackingLat').value       || '',
                longitude:         $('trackingLng')       && $('trackingLng').value       || '',
                status_note:       $('trackingNote')      && $('trackingNote').value      || null,
                tenant_id:         state.tenant
            };
        },
        row: function (t) {
            return '<tr>' +
                '<td>' + esc(t.id) + '</td>' +
                '<td>' + esc(t.delivery_order_id) + '</td>' +
                '<td>' + esc(t.provider_id || '–') + '</td>' +
                '<td>' + esc(t.latitude) + '</td>' +
                '<td>' + esc(t.longitude) + '</td>' +
                '<td>' + esc(t.status_note || '–') + '</td>' +
                '<td>' + esc(t.created_at || '') + '</td>' +
                '<td class="actions">' +
                (CFG.canDelete ? '<button class="btn btn-sm btn-danger" onclick="Delivery.delTracking(' + t.id + ')"><i class="fas fa-trash"></i></button>' : '') +
                '</td></tr>';
        }
    });

    // ─── Provider Zones Module ────────────────────────────────────────
    var pzonesMod = Object.assign(
        createModule('provider_zones', CFG.urls.provider_zones, {
            loading: 'pzonesTableLoading', container: 'pzonesTableContainer', empty: 'pzonesEmptyState',
            tbody: 'pzonesTableBody', pagination: 'pzonesPagination', info: 'pzonesPaginationInfo',
            formContainer: 'pzoneFormContainer', form: 'pzoneForm',
            addBtn: 'pzonesAddBtn', closeBtn: 'pzoneCloseForm', cancelBtn: 'pzoneCancelBtn',
            applyBtn: 'pzonesApplyFilter', resetBtn: 'pzonesResetFilter',
            getId: function () { return null; },
            getFilters: function () {
                return {
                    provider_id: $('pzonesProviderFilter') && $('pzonesProviderFilter').value || '',
                    zone_id:     $('pzonesZoneFilter')     && $('pzonesZoneFilter').value     || '',
                    is_active:   $('pzonesActiveFilter')   ? $('pzonesActiveFilter').value    : ''
                };
            },
            reset: function () {
                ['pzonesProviderFilter', 'pzonesZoneFilter', 'pzonesActiveFilter']
                    .forEach(function (id) { var el = $(id); if (el) el.value = ''; });
            },
            setForm: function (pz) {
                if ($('pzoneProviderId')) $('pzoneProviderId').value = pz.provider_id || '';
                if ($('pzoneProviderName') && pz.provider_id) {
                    $('pzoneProviderName').textContent = '#' + pz.provider_id;
                    $('pzoneProviderName').className   = 'provider-name-badge found';
                } else if ($('pzoneProviderName')) {
                    $('pzoneProviderName').textContent = '';
                    $('pzoneProviderName').className   = 'provider-name-badge';
                }
                if ($('pzoneZoneId')) $('pzoneZoneId').value   = pz.zone_id  || '';
                if ($('pzoneActive')) $('pzoneActive').checked = !!+pz.is_active;
            },
            getFormData: function () {
                return {
                    provider_id: $('pzoneProviderId') && $('pzoneProviderId').value || '',
                    zone_id:     $('pzoneZoneId')     && $('pzoneZoneId').value     || '',
                    is_active:   $('pzoneActive')     ? ($('pzoneActive').checked ? 1 : 0) : 1
                };
            },
            delUrl: function (id) {
                var sep = id.indexOf('_'), pid = id.substring(0, sep), zid = id.substring(sep + 1);
                return CFG.urls.provider_zones + '?provider_id=' + encodeURIComponent(pid) + '&zone_id=' + encodeURIComponent(zid);
            },
            row: function (pz) {
                return '<tr data-pzid="' + esc(pz.provider_id) + '_' + esc(pz.zone_id) + '">' +
                    '<td>' + esc(pz.provider_id) + '</td>' +
                    '<td>' + esc(pz.zone_id) + '</td>' +
                    '<td>' + badge(pz.is_active ? 'Active' : 'Inactive', { Active: 'success', Inactive: 'secondary' }) + '</td>' +
                    '<td>' + esc(pz.assigned_at || '') + '</td>' +
                    '<td class="actions">' +
                    (CFG.canDelete ? '<button class="btn btn-sm btn-danger" onclick="Delivery.delPZone(\'' + esc(pz.provider_id) + '_' + esc(pz.zone_id) + '\')"><i class="fas fa-trash"></i></button>' : '') +
                    '</td></tr>';
            }
        }),
        {
            save: async function (e) {
                e.preventDefault();
                var ps = state.provider_zones;
                if (ps.saving) return;
                ps.saving = true;
                var body = pzonesMod.cfg.getFormData();
                try {
                    await api(CFG.urls.provider_zones, { method: 'POST', json: body });
                    notify('Saved successfully', 'success');
                    pzonesMod.hideForm();
                    pzonesMod.load(ps.page);
                } catch (err) { notify(err.message, 'error'); }
                finally { ps.saving = false; }
            }
        }
    );

    // ─── Dropdown Loaders ─────────────────────────────────────────────
    async function loadDrops() {
        // Zones dropdown
        try {
            var rz     = await api(CFG.urls.zones + '?limit=500&tenant_id=' + state.tenant);
            var zitems = extractItems(rz);
            var zhtml  = '<option value="">–</option>' +
                zitems.map(function (z) { return '<option value="' + esc(z.id) + '">' + esc(z.zone_name) + '</option>'; }).join('');
            ['pzoneZoneId', 'pzonesZoneFilter', 'orderZoneId', 'ordersZoneFilter']
                .forEach(function (id) { var el = $(id); if (el) el.innerHTML = zhtml; });
        } catch (e) { console.warn('[Delivery] zones dropdown:', e.message); }

        // Countries + Cities cascade
        await loadCountries();
        await loadCitiesForCountry('');

        // Delivery orders for tracking dropdown
        try {
            var ro     = await api(CFG.urls.orders + '?limit=500&tenant_id=' + state.tenant);
            var oitems = extractItems(ro);
            var ohtml  = '<option value="">–</option>' +
                oitems.map(function (o) {
                    return '<option value="' + esc(o.id) + '">#' + esc(o.id) + ' (order:' + esc(o.order_id) + ')</option>';
                }).join('');
            ['trackingOrderId', 'trackingOrderFilter']
                .forEach(function (id) { var el = $(id); if (el) el.innerHTML = ohtml; });
        } catch (e) { console.warn('[Delivery] orders dropdown:', e.message); }

        // Provider filter dropdowns (filter bars only — form fields use ID lookup)
        try {
            var rp     = await api(CFG.urls.providers + '?limit=500&tenant_id=' + state.tenant);
            var pitems = extractItems(rp);
            var phtml  = '<option value="">–</option>' +
                pitems.map(function (p) {
                    return '<option value="' + esc(p.id) + '">#' + esc(p.id) + ' ' + esc(p.provider_type) + '</option>';
                }).join('');
            ['ordersProviderFilter', 'locationsProviderFilter', 'pzonesProviderFilter', 'trackingProviderFilter']
                .forEach(function (id) { var el = $(id); if (el) el.innerHTML = phtml; });
        } catch (e) { console.warn('[Delivery] providers filter:', e.message); }
    }

    // ─── Tabs ─────────────────────────────────────────────────────────
    function initTabs() {
        document.querySelectorAll('#workspaceTabs .tab-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                document.querySelectorAll('#workspaceTabs .tab-btn').forEach(function (b) { b.classList.remove('active'); });
                btn.classList.add('active');
                document.querySelectorAll('.ws-panel').forEach(function (p) { p.style.display = 'none'; });
                var tab   = btn.dataset.tab;
                var panel = document.getElementById(tab + 'Tab');
                if (panel) panel.style.display = '';
                if (tab === 'zones' && zonesMap) {
                    scheduleMapInvalidate();
                }
                var modMap = {
                    zones:          zonesMod,
                    providers:      providersMod,
                    orders:         ordersMod,
                    locations:      locationsMod,
                    tracking:       trackingMod,
                    provider_zones: pzonesMod
                };
                var mod = modMap[tab];
                if (mod && state[tab] && !state[tab].loaded) mod.load(1);
            });
        });
    }

    function bindCascade() {
        var countryEl = $('zoneCountryId');
        if (countryEl) countryEl.addEventListener('change', function () { loadCitiesForCountry(this.value); });
    }

    function bindZoneTypeChange() {
        var zt = $('zoneType');
        if (zt) zt.addEventListener('change', toggleRadiusFields);
    }

    // ─── Init ─────────────────────────────────────────────────────────
    async function init() {
        if (state.initialized) return;
        state.initialized = true;

        // Re-read config in case of fragment re-navigation
        var cfg = window.DELIVERY_CONFIG || {};
        state.lang   = cfg.lang || 'ar';
        state.tenant = cfg.tenantId || 1;
        state.csrf   = cfg.csrfToken || '';
        state.perms  = { canCreate: !!cfg.canCreate, canEdit: !!cfg.canEdit, canDelete: !!cfg.canDelete };

        initTabs();
        bindZoneTypeChange();
        bindCascade();
        initCoordPicker();

        // ESC key closes modal
        document.addEventListener('keydown', function (e) {
            if (e.key !== 'Escape') return;
            var modal = $('coordPickerModal');
            if (modal && modal.style.display !== 'none' && modal.style.display !== '') {
                closeCoordPicker();
            }
        });

        bindProviderLookup('orderProviderId',       'orderProviderName');
        bindProviderLookup('locationProviderId',    'locationProviderName');
        bindProviderLookup('trackingProviderId',    'trackingProviderName');
        bindProviderLookup('zoneProviderId',        'zoneProviderName');
        bindProviderLookup('pzoneProviderId',       'pzoneProviderName');
        bindEntityLookup('providerEntityId',        'providerEntityName');
        bindTenantUserLookup('providerTenantUserId','providerTenantUserName');

        [zonesMod, providersMod, ordersMod, locationsMod, trackingMod, pzonesMod]
            .forEach(function (m) { m.bindEvents(); });

        // Serial-chain: Leaflet JS → Draw JS → CSS → initZonesMap
        ensureLeafletJs(function () {
            ensureLeafletCss(function () {
                if (typeof L === 'undefined') {
                    console.error('[Delivery] Leaflet not loaded — map skipped');
                    return;
                }
                initZonesMap();
                if (!zonesMap) {
                    // Retry for slow mobile networks / late CSS-paint
                    MAP_INIT_RETRY_DELAYS_MS.forEach(function (ms) { setTimeout(initZonesMap, ms); });
                }
                scheduleMapInvalidate();
            });
        });

        await loadDrops();
        await zonesMod.load(1);
    }

    // ─── Reinit after AJAX re-navigation ─────────────────────────────
    function reinit() {
        if (zonesMap) {
            try { zonesMap.off(); zonesMap.remove(); } catch (e) {}
            zonesMap         = null;
            drawnItems       = null;
            zonesLocateMarker= null;
            zoneLayerMap.clear();
        }
        if (mapResizeObserver) {
            try { mapResizeObserver.disconnect(); } catch (e) {}
            mapResizeObserver = null;
        }
        if (coordPickerMap) {
            try { coordPickerMap.off(); coordPickerMap.remove(); } catch (e) {}
            coordPickerMap    = null;
            coordPickerMarker = null;
        }
        state.initialized = false;
        init();
    }

    // ─── Public API ───────────────────────────────────────────────────
    window.Delivery = {
        init:         init,
        reinit:       reinit,
        editZone:     function (id) { zonesMod.edit(id); },
        delZone:      function (id) { zonesMod.del(id); },
        editProvider: function (id) { providersMod.edit(id); },
        delProvider:  function (id) { providersMod.del(id); },
        editOrder:    function (id) { ordersMod.edit(id); },
        editLocation: function (id) { locationsMod.edit(id); },
        delLocation:  function (id) { locationsMod.del(id); },
        delTracking:  function (id) { trackingMod.del(id); },
        delPZone:     function (id) { pzonesMod.del(id); }
    };

    // ─── Page Registration ───────────────────────────────────────────
    window.page = { run: init };

    if (window.Admin && window.Admin.page && window.Admin.page.register) {
        window.Admin.page.register('delivery', init);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

}());