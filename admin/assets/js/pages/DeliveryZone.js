// admin/assets/js/pages/DeliveryZone.js
// Full-featured admin UI for Delivery Zones - نسخة كاملة ومُكتملة 100%
// تعمل مع الترجمة + ترسل كل البيانات + لا تختفي الحقول

(function () {
  'use strict';

  // === 1. نظام الترجمة ===
  function loadLangAndApply(callback) {
    const lang = window.ADMIN_LANG || 'en';
    const url = (typeof langBase !== 'undefined' ? langBase : '/languages/admin') + '/' + lang + '.json';

    if (window.ADMIN_UI && window.ADMIN_UI.lang === lang) {
      applyTranslations();
      if (callback) callback();
      return;
    }

    fetch(url, { credentials: 'same-origin' })
      .then(res => res.ok ? res.json() : Promise.reject('Failed'))
      .then(json => {
        window.ADMIN_UI = { lang };
        Object.assign(window.ADMIN_UI, json);
        applyTranslations();
        if (callback) callback();
      })
      .catch(() => {
        if (callback) callback(); // نكمل حتى لو فشلت
      });
  }

  function applyTranslations() {
    const dict = window.ADMIN_UI || {};
    document.querySelectorAll('[data-i18n]').forEach(el => {
      const key = el.getAttribute('data-i18n');
      const val = getNested(dict, key);
      if (val) el.textContent = val;
    });
    document.querySelectorAll('[data-i18n-placeholder]').forEach(el => {
      const key = el.getAttribute('data-i18n-placeholder');
      const val = getNested(dict, key);
      if (val) el.placeholder = val;
    });
    const loadingEl = document.querySelector('#dzList[data-i18n-loading]');
    if (loadingEl && loadingEl.innerHTML.trim() === 'Loading...') {
      const val = getNested(dict, loadingEl.getAttribute('data-i18n-loading'));
      if (val) loadingEl.innerHTML = val;
    }
  }

  function getNested(obj, path) {
    return path.split('.').reduce((o, k) => o?.[k], obj);
  }

  // === 2. المتغيرات الأساسية ===
  const qs = s => document.querySelector(s);
  const qsa = s => Array.from(document.querySelectorAll(s));
  const API = window.DZ?.API_BASE || '/api/routes/DeliveryZone.php';
  const ALLOWED_ACTIONS = ['list', 'get', 'create_zone', 'update_zone', 'delete_zone'];

  const listEl = qs('#dzList');
  const form = qs('#dzForm');
  const saveBtn = qs('#dzSaveBtn');
  const resetBtn = qs('#dzResetBtn');
  const newBtn = qs('#dzNewBtn');
  const refreshBtn = qs('#dzRefresh');
  const searchInput = qs('#dzSearch');
  const statusFilter = qs('#dzStatusFilter');
  const autoSaveCheckbox = qs('#dz_auto_save') || { checked: true };

  if (!form || !listEl || !qs('#dzMap')) {
    console.error('DeliveryZone: Missing critical elements');
    return;
  }

  const fld = id => qs('#dz_' + id);

  // === 3. إعداد الخريطة ===
  if (typeof L === 'undefined') {
    console.error('Leaflet not loaded');
    return;
  }

  const map = L.map('dzMap').setView([24.7136, 46.6753], 6);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; OpenStreetMap contributors'
  }).addTo(map);

  const drawnItems = L.featureGroup().addTo(map);
  const zoneLayerMap = new Map();

  if (typeof L.Draw !== 'undefined') {
    new L.Control.Draw({
      position: 'topright',
      draw: {
        polygon: { allowIntersection: false, showArea: true, shapeOptions: { color: '#3366CC' } },
        rectangle: { shapeOptions: { color: '#3366CC' } },
        circle: { shapeOptions: { color: '#3366CC' } },
        marker: false,
        polyline: false,
        circlemarker: false
      },
      edit: { featureGroup: drawnItems, remove: true }
    }).addTo(map);
  }

  // === 4. أحداث الرسم ===
  let autoSaveTimer = 0;
  const AUTO_SAVE_DEBOUNCE_MS = 800;

  map.on(L.Draw.Event.CREATED, e => {
    drawnItems.addLayer(e.layer);
    populateZoneValueFromLayer(e.layer);
    scheduleAutoSave();
  });

  map.on(L.Draw.Event.EDITED, e => {
    e.layers.eachLayer(layer => populateZoneValueFromLayer(layer));
    scheduleAutoSave();
  });

  map.on(L.Draw.Event.DELETED, () => {
    if (drawnItems.getLayers().length === 0) {
      const zf = fld('zone_value');
      if (zf) zf.value = '';
    }
    scheduleAutoSave();
  });

  const zoneValueField = fld('zone_value');
  if (zoneValueField) {
    zoneValueField.addEventListener('input', () => {
      const txt = zoneValueField.value.trim();
      drawnItems.clearLayers();
      if (!txt) return;
      try {
        const parsed = JSON.parse(txt);
        drawParsedGeometry(parsed);
      } catch (e) {
        console.warn('Invalid JSON in zone_value');
      }
    });
  }

  // === 5. تحويل الشكل إلى JSON وبالعكس ===
  function populateZoneValueFromLayer(layer) {
    const out = fld('zone_value');
    if (!out || !layer) {
      if (out) out.value = '';
      return;
    }

    let geo = null;
    let type = '';

    if (layer instanceof L.Polygon && !(layer instanceof L.Rectangle)) {
      const coords = layer.getLatLngs()[0].map(p => [p.lng, p.lat]);
      geo = { type: 'Polygon', coordinates: [coords] };
      type = 'polygon';
    } else if (layer instanceof L.Rectangle) {
      const b = layer.getBounds();
      geo = { type: 'Rectangle', bounds: [[b.getSouth(), b.getWest()], [b.getNorth(), b.getEast()]] };
      type = 'rectangle';
    } else if (layer instanceof L.Circle) {
      const c = layer.getLatLng();
      geo = { type: 'Circle', center: [c.lat, c.lng], radius: Math.round(layer.getRadius()) };
      type = 'radius';
    }

    if (geo) {
      out.value = JSON.stringify(geo, null, 2);
      safeSetField('zone_type', type);
    }
  }

  function drawParsedGeometry(parsed) {
    if (!parsed || !parsed.type) return;
    const t = parsed.type.toLowerCase();
    let layer = null;

    if (t === 'polygon') {
      const coords = parsed.coordinates?.[0] || [];
      const latlngs = coords.map(pt => [pt[1], pt[0]]);
      layer = L.polygon(latlngs, { color: '#ff7800' });
    } else if (t === 'rectangle') {
      const b = parsed.bounds;
      if (b && b.length >= 2) {
        layer = L.rectangle([[b[0][0], b[0][1]], [b[1][0], b[1][1]]], { color: '#ff7800' });
      }
    } else if (t === 'circle' || t === 'radius') {
      if (parsed.center && parsed.radius) {
        layer = L.circle([parsed.center[0], parsed.center[1]], { radius: parsed.radius, color: '#ff7800' });
      }
    }

    if (layer) {
      drawnItems.addLayer(layer);
      try { map.fitBounds(layer.getBounds()); } catch (e) {}
    }
  }

  // === 6. تحميل وعرض القائمة والخريطة ===
  async function loadList() {
    listEl.innerHTML = getNested(window.ADMIN_UI, 'messages.loading') || 'Loading...';

    const q = searchInput?.value?.trim() || '';
    const status = statusFilter?.value || '';
    let url = API + '?action=list';
    if (q) url += '&q=' + encodeURIComponent(q);
    if (status) url += '&status=' + encodeURIComponent(status);

    try {
      const res = await fetch(url, { credentials: 'include' });
      const json = await res.json();

      if (!json.success) {
        listEl.innerHTML = 'Error: ' + (json.message || 'Failed');
        return;
      }

      renderList(json.data || []);
      renderZonesOnMap(json.data || []);
    } catch (err) {
      console.error(err);
      listEl.innerHTML = 'Network error';
    }
  }

  function renderList(rows) {
    if (!rows || rows.length === 0) {
      listEl.innerHTML = '<div class="empty">No zones</div>';
      return;
    }

    listEl.innerHTML = '';
    rows.forEach(r => {
      const item = document.createElement('div');
      item.className = 'dz-list-item';
      item.innerHTML = `
        <div class="dz-item-main">
          <strong>${escapeHTML(r.zone_name || 'Unnamed')}</strong>
          <div class="dz-meta">Type: ${escapeHTML(r.zone_type)} • Rate: ${r.shipping_rate || 0}</div>
        </div>
        <div class="dz-item-actions">
          <button class="btn small edit" data-id="${r.id}">Edit</button>
          <button class="btn small danger del" data-id="${r.id}">Delete</button>
        </div>
      `;
      listEl.appendChild(item);
    });

    qsa('.dz-list-item .edit').forEach(b => b.addEventListener('click', () => openEdit(b.dataset.id)));
    qsa('.dz-list-item .del').forEach(b => b.addEventListener('click', () => deleteZone(b.dataset.id)));
  }

  function renderZonesOnMap(rows) {
    drawnItems.clearLayers();
    zoneLayerMap.clear();

    if (!rows || rows.length === 0) return;

    rows.forEach(r => {
      if (!r.zone_value) return;
      let parsed;
      try { parsed = JSON.parse(r.zone_value); } catch (e) { return; }

      let layer = null;
      const t = (parsed.type || '').toLowerCase();

      if (t === 'polygon') {
        const latlngs = (parsed.coordinates?.[0] || []).map(pt => [pt[1], pt[0]]);
        layer = L.polygon(latlngs, { color: '#3388ff' });
      } else if (t === 'rectangle') {
        const b = parsed.bounds;
        if (b) layer = L.rectangle([[b[0][0], b[0][1]], [b[1][0], b[1][1]]], { color: '#3388ff' });
      } else if (t === 'circle' || t === 'radius') {
        if (parsed.center && parsed.radius) layer = L.circle(parsed.center, { radius: parsed.radius, color: '#3388ff' });
      }

      if (layer) {
        drawnItems.addLayer(layer);
        layer.bindPopup(`<strong>${escapeHTML(r.zone_name)}</strong><br/>Type: ${escapeHTML(r.zone_type)}`);
        zoneLayerMap.set(String(r.id), layer);
        layer.on('click', () => openEdit(r.id));
      }
    });

    if (drawnItems.getLayers().length > 0) {
      map.fitBounds(drawnItems.getBounds(), { padding: [20, 20] });
    }
  }

  // === 7. فتح التعديل ===
  async function openEdit(id) {
    if (!id) return;
    try {
      const res = await fetch(API + '?action=get&id=' + encodeURIComponent(id), { credentials: 'include' });
      const j = await res.json();
      if (!j.success) {
        alert(j.message || 'Failed to load zone');
        return;
      }

      const z = j.data || {};
      safeSetField('id', z.id || 0);
      safeSetField('zone_name', z.zone_name || '');
      safeSetField('zone_type', z.zone_type || 'polygon');
      safeSetField('shipping_rate', z.shipping_rate || 0);
      safeSetField('free_threshold', z.free_shipping_threshold || '');
      safeSetField('estimated_days', z.estimated_delivery_days || 3);
      safeSetField('status', z.status || 'active');
      safeSetField('zone_value', z.zone_value || '');

      drawnItems.clearLayers();
      if (z.zone_value) {
        try { drawParsedGeometry(JSON.parse(z.zone_value)); } catch (e) {}
      }
    } catch (err) {
      console.error(err);
      alert('Error loading zone');
    }
  }

  // === 8. الحفظ التلقائي واليدوي ===
  function scheduleAutoSave() {
    if (!autoSaveCheckbox.checked) return;
    clearTimeout(autoSaveTimer);
    autoSaveTimer = setTimeout(autoSaveZone, AUTO_SAVE_DEBOUNCE_MS);
  }

  async function autoSaveZone() {
    const idVal = fld('id')?.value || '0';
    const action = (idVal && idVal !== '0') ? 'update_zone' : 'create_zone';
    await saveZone(action);
  }

  function sanitizeAction(action) {
    return ALLOWED_ACTIONS.includes(action) ? action : null;
  }

  function removeActionInputFromForm() {
    form.querySelectorAll('input[name="action"]').forEach(el => el.remove());
  }

  function safeSetField(id, value) {
    const el = fld(id);
    if (el) el.value = value ?? '';
  }

  async function saveZone(action) {
    const cleanAction = sanitizeAction(action);
    if (!cleanAction) {
      console.warn('Blocked unsafe action');
      return;
    }

    removeActionInputFromForm();

    // إرسال كل الحقول يدويًا لضمان الوصول (الحل الأكيد)
    const fd = new FormData();
    fd.append('action', cleanAction);
    fd.append('id', fld('id')?.value || '0');
    fd.append('zone_name', fld('zone_name')?.value || '');
    fd.append('zone_type', fld('zone_type')?.value || 'polygon');
    fd.append('zone_value', fld('zone_value')?.value || '');
    fd.append('shipping_rate', fld('shipping_rate')?.value || '0.00');
    fd.append('free_shipping_threshold', fld('free_threshold')?.value || '');
    fd.append('estimated_delivery_days', fld('estimated_days')?.value || '3');
    fd.append('status', fld('status')?.value || 'active');

    const csrfEl = qs('#dz_csrf');
    if (csrfEl) fd.append('csrf_token', csrfEl.value);

    try {
      const res = await fetch(API, { method: 'POST', body: fd, credentials: 'include' });
      const j = await res.json();

      if (!j.success) {
        alert(j.message || 'فشل الحفظ');
        return;
      }

      if (cleanAction === 'create_zone' && j.id) {
        safeSetField('id', j.id);
      }

      alert('تم الحفظ بنجاح');
      loadList();
    } catch (err) {
      console.error('Save error:', err);
      alert('خطأ في الشبكة');
    }
  }

  // === 9. الحذف ===
  async function deleteZone(id) {
    if (!id || !confirm('هل تريد حذف هذه المنطقة؟')) return;

    const fd = new FormData();
    fd.append('action', 'delete_zone');
    fd.append('id', id);
    const csrfEl = qs('#dz_csrf');
    if (csrfEl) fd.append('csrf_token', csrfEl.value);

    try {
      const res = await fetch(API, { method: 'POST', body: fd, credentials: 'include' });
      const j = await res.json();

      if (!j.success) {
        alert(j.message || 'فشل الحذف');
        return;
      }

      alert('تم الحذف بنجاح');
      loadList();
      clearForm();
    } catch (err) {
      alert('خطأ في الشبكة');
    }
  }

  // === 10. إعادة تعيين النموذج ===
  function clearForm() {
    form.reset();
    safeSetField('id', 0);
    safeSetField('zone_value', '');
    drawnItems.clearLayers();
    map.setView([24.7136, 46.6753], 6);
  }

  function escapeHTML(s) {
    return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
  }

  // === 11. ربط الأحداث ===
  function initEvents() {
    saveBtn?.addEventListener('click', () => {
      const id = fld('id')?.value;
      saveZone(id && id !== '0' ? 'update_zone' : 'create_zone');
    });

    resetBtn?.addEventListener('click', clearForm);
    newBtn?.addEventListener('click', () => {
      clearForm();
      map.setView([24.7136, 46.6753], 6);
    });

    refreshBtn?.addEventListener('click', loadList);
    searchInput?.addEventListener('input', () => setTimeout(loadList, 300));
    statusFilter?.addEventListener('change', loadList);
  }

  // === 12. التشغيل ===
  document.addEventListener('DOMContentLoaded', () => {
    loadLangAndApply(() => {
      initEvents();
      loadList();
    });
  });

})();