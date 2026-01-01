// admin/assets/js/pages/banners.js
// COMPLETE client script for banners admin UI
// - Uses JSON translations provided in window.I18N (nested) and window.I18N_FLAT (flat map).
// - Full features: list, search, filter, create/edit/save/delete, translations inline inputs, image upload, active toggle.
// - Safe DOM operations and fallbacks.

(function(){
  'use strict';

  // Translations accessors
  var I18N = window.I18N || {};
  var I18N_FLAT = window.I18N_FLAT || {};
  var FALLBACK = {
    loading: 'Loading...', no_banners: 'No banners', btn_edit: 'Edit', btn_delete: 'Delete',
    btn_save: 'Save', btn_new: 'Create Banner', btn_refresh: 'Refresh', btn_translations: 'Translations',
    btn_cancel: 'Cancel', yes: 'Yes', no: 'No', confirm_delete: 'Are you sure?', processing: 'Processing...'
  };

  function t(key){
    if (!key) return '';
    if (I18N_FLAT && typeof I18N_FLAT[key] !== 'undefined' && I18N_FLAT[key] !== '') return I18N_FLAT[key];
    var containers = ['banners','strings','buttons','labels','actions','status','forms','table','menu'];
    for (var i=0;i<containers.length;i++){
      var c = containers[i];
      if (I18N[c] && typeof I18N[c][key] !== 'undefined' && I18N[c][key] !== '') return I18N[c][key];
    }
    return FALLBACK[key] || key.replace(/_/g,' ');
  }

  // DOM helpers
  function getEl(id){ return document.getElementById(id); }
  function qs(sel, ctx){ ctx = ctx || document; return ctx.querySelector(sel); }
  function qsa(sel, ctx){ ctx = ctx || document; return Array.prototype.slice.call(ctx.querySelectorAll(sel || '')); }
  function escapeHtml(s){ return String(s==null?'':s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
  function escapeAttr(s){ return escapeHtml(s).replace(/'/g, '&#39;'); }

  var API = '/api/banners.php';

  var tbody = getEl('bannersTbody');
  var countEl = getEl('bannersCount');
  var statusEl = getEl('bannersStatus');
  var searchEl = getEl('bannerSearch');
  var formWrap = getEl('bannerFormWrap');
  var translationsArea = getEl('translationsArea');

  var bannersCache = [];

  function setStatus(msg, isError){
    if (!statusEl) return;
    statusEl.textContent = msg || '';
    statusEl.style.color = isError ? '#b91c1c' : '#064e3b';
  }

  // network helpers
  function fetchText(url, opts){ opts = opts || {}; opts.credentials = 'include'; return fetch(url, opts).then(function(r){ return r.text().then(function(t){ return { ok: r.ok, status: r.status, text: t }; }); }); }
  function fetchJson(url, opts){ return fetchText(url, opts).then(function(res){ if (!res.ok) { var msg = res.text || ('HTTP ' + res.status); try { var j = JSON.parse(res.text); msg = j.message || msg; } catch(e){} throw new Error(msg); } try { return JSON.parse(res.text); } catch(e){ throw new Error('Invalid JSON response'); } }); }

  // load banners
  function loadBanners(){
    setStatus(t('loading'));
    fetchJson(API + '?format=json').then(function(json){
      if (!json.success) { setStatus(json.message || t('error_fetch'), true); return; }
      bannersCache = json.data || [];
      renderTable(bannersCache);
      setStatus('');
    }).catch(function(err){ console.error(err); setStatus(err.message || t('error_fetch'), true); });
  }

  // client-side search filter
  function filterBanners(q){
    if (!q) return bannersCache;
    q = q.trim().toLowerCase();
    return bannersCache.filter(function(b){
      if (String(b.id).indexOf(q) !== -1) return true;
      if ((b.title || '').toLowerCase().indexOf(q) !== -1) return true;
      if ((b.subtitle || '').toLowerCase().indexOf(q) !== -1) return true;
      if ((b.position || '').toLowerCase().indexOf(q) !== -1) return true;
      if ((b.link_text || '').toLowerCase().indexOf(q) !== -1) return true;
      if (b.translations && typeof b.translations === 'object') {
        for (var code in b.translations) {
          var it = b.translations[code];
          if ((it.title || '').toLowerCase().indexOf(q) !== -1) return true;
          if ((it.subtitle || '').toLowerCase().indexOf(q) !== -1) return true;
        }
      }
      return false;
    });
  }

  // render table rows
  function renderTable(rows){
    if (!tbody) return;
    tbody.innerHTML = '';
    if (!rows || rows.length === 0) {
      tbody.innerHTML = '<tr><td colspan="6" style="padding:12px;text-align:center;color:#666;">' + t('no_banners') + '</td></tr>';
      if (countEl) countEl.textContent = '0';
      return;
    }
    if (countEl) countEl.textContent = rows.length;
    rows.forEach(function(b){
      var id = escapeHtml(b.id);
      var title = escapeHtml(b.title || '');
      var img = b.image_url ? '<img src="'+escapeAttr(b.image_url)+'" style="max-height:50px;border-radius:4px">' : '';
      var pos = escapeHtml(b.position || '');
      var activeLabel = b.is_active ? t('yes') : t('no');
      var tr = document.createElement('tr');
      tr.innerHTML = '<td style="padding:8px;border-bottom:1px solid #eee;">'+id+'</td>'
        + '<td style="padding:8px;border-bottom:1px solid #eee;">'+title+'</td>'
        + '<td style="padding:8px;border-bottom:1px solid #eee;">'+img+'</td>'
        + '<td style="padding:8px;border-bottom:1px solid #eee;">'+pos+'</td>'
        + '<td style="padding:8px;border-bottom:1px solid #eee;text-align:center;"><button class="btn toggleActiveBtn" data-id="'+escapeAttr(b.id)+'" data-active="'+(b.is_active?1:0)+'">'+activeLabel+'</button></td>'
        + '<td style="padding:8px;border-bottom:1px solid #eee;"><button class="btn editBtn" data-id="'+escapeAttr(b.id)+'">'+t('btn_edit')+'</button> <button class="btn danger delBtn" data-id="'+escapeAttr(b.id)+'" style="background:#ef4444;color:#fff">'+t('btn_delete')+'</button></td>';
      tbody.appendChild(tr);
    });

    qsa('.editBtn', tbody).forEach(function(btn){ btn.addEventListener('click', function(){ openEdit(this.getAttribute('data-id')); }); });
    qsa('.delBtn', tbody).forEach(function(btn){ btn.addEventListener('click', function(){ var id=this.getAttribute('data-id'); if (!window.CAN_MANAGE_BANNERS){ alert(t('no_permission_notice')); return; } if (confirm(t('confirm_delete'))) deleteBanner(id); }); });
    qsa('.toggleActiveBtn', tbody).forEach(function(btn){ btn.addEventListener('click', function(){ var id=this.getAttribute('data-id'); var cur = parseInt(this.getAttribute('data-active')||0,10); toggleActive(id, cur?0:1); }); });
  }

  // toggleActive: try dedicated API action, else fallback to minimal save
  function toggleActive(id, newState){
    if (!window.CAN_MANAGE_BANNERS) { alert(t('no_permission_notice')); return; }
    setStatus(t('processing'));
    var fd = new FormData();
    fd.append('action','toggle_active');
    fd.append('id', id);
    fd.append('is_active', newState ? 1 : 0);
    fd.append('csrf_token', window.CSRF_TOKEN || '');
    fetchText(API, { method: 'POST', body: fd }).then(function(r){
      if (r.status >=200 && r.status < 300) {
        try {
          var j = JSON.parse(r.text || '{}');
          if (j.success) {
            setStatus(j.message || t('saved'));
            bannersCache = bannersCache.map(function(b){ if (String(b.id) === String(id)) b.is_active = newState?1:0; return b; });
            renderTable(filterBanners(searchEl && searchEl.value ? searchEl.value : ''));
            return;
          } else {
            fallbackToggleSave(id, newState);
            return;
          }
        } catch(e){
          fallbackToggleSave(id, newState);
          return;
        }
      } else fallbackToggleSave(id, newState);
    }).catch(function(err){ console.warn('toggle active failed, fallback', err); fallbackToggleSave(id, newState); });
  }

  function fallbackToggleSave(id, newState){
    var b = bannersCache.find(function(x){ return String(x.id) === String(id); }) || { id: id, title: '' };
    var fd = new FormData();
    fd.append('action','save');
    fd.append('id', id);
    fd.append('title', b.title || ''); // some APIs require title
    fd.append('is_active', newState ? 1 : 0);
    fd.append('csrf_token', window.CSRF_TOKEN || '');
    fetchText(API, { method:'POST', body: fd }).then(function(r){
      if (r.status >=200 && r.status < 300) {
        try {
          var j = JSON.parse(r.text || '{}');
          if (j.success) {
            setStatus(j.message || t('saved'));
            bannersCache = bannersCache.map(function(x){ if (String(x.id) === String(id)) x.is_active = newState?1:0; return x; });
            renderTable(filterBanners(searchEl && searchEl.value ? searchEl.value : ''));
            return;
          } else setStatus(j.message || t('error_save'), true);
        } catch(e){ setStatus('Invalid JSON response', true); console.error(r.text); }
      } else setStatus('HTTP '+r.status, true);
    }).catch(function(err){ console.error(err); setStatus(t('error_save'), true); });
  }

  // delete
  function deleteBanner(id){
    if (!window.CAN_MANAGE_BANNERS) { alert(t('no_permission_notice')); return; }
    var fd = new FormData(); fd.append('action','delete'); fd.append('id', id); fd.append('csrf_token', window.CSRF_TOKEN || '');
    setStatus(t('processing'));
    fetchText(API, { method:'POST', body: fd }).then(function(r){
      if (r.status >=200 && r.status < 300) {
        try { var j = JSON.parse(r.text || '{}'); if (j.success){ setStatus(j.message || t('deleted')); bannersCache = bannersCache.filter(function(b){ return String(b.id) !== String(id); }); renderTable(filterBanners(searchEl && searchEl.value ? searchEl.value : '')); } else setStatus(j.message || t('error_delete'), true); }
        catch(e){ setStatus('Invalid JSON', true); console.error(r.text); }
      } else setStatus('HTTP '+r.status, true);
    }).catch(function(err){ console.error(err); setStatus(t('error_delete'), true); });
  }

  // open edit: fetch single
  function openEdit(id){
    setStatus(t('loading'));
    fetchJson(API + '?_fetch_row=1&id=' + encodeURIComponent(id)).then(function(json){
      if (!json.success) { setStatus(json.message || t('error_fetch'), true); return; }
      populateForm(json.data || {});
      setStatus('');
    }).catch(function(err){ console.error(err); setStatus(err.message || t('error_fetch'), true); });
  }

  // populate form
  function populateForm(b){
    if (!formWrap) return;
    formWrap.style.display = 'block';
    var titleEl = getEl('bannerFormTitle'); if (titleEl) titleEl.textContent = (t('banners.form_title_edit') || t('form_title_edit')) + ' #' + (b.id || '');
    safeSet('banner_id', b.id || 0);
    safeSet('banner_title', b.title || '');
    safeSet('banner_subtitle', b.subtitle || '');
    safeSet('banner_link_url', b.link_url || '');
    safeSet('banner_link_text', b.link_text || '');
    safeSet('banner_position', b.position || '');
    safeSet('banner_theme_id', (b.theme_id === null ? '' : b.theme_id));
    safeSet('banner_background_color', b.background_color || '#ffffff');
    safeSet('banner_text_color', b.text_color || '#000000');
    safeSet('banner_button_style', b.button_style || '');
    safeSet('banner_sort_order', (typeof b.sort_order !== 'undefined') ? b.sort_order : 0);
    safeSet('banner_is_active', b.is_active ? 1 : 0);
    safeSet('banner_start_date', formatForDatetimeLocal(b.start_date));
    safeSet('banner_end_date', formatForDatetimeLocal(b.end_date));
    safeSet('banner_image_url', b.image_url || '');
    safeSet('banner_mobile_image_url', b.mobile_image_url || '');
    var prev = getEl('banner_image_preview'); if (prev) prev.innerHTML = b.image_url ? '<img src="'+escapeAttr(b.image_url)+'" style="max-height:80px;border-radius:4px;border:1px solid #e5e7eb">' : '';
    var mprev = getEl('banner_mobile_image_preview'); if (mprev) mprev.innerHTML = b.mobile_image_url ? '<img src="'+escapeAttr(b.mobile_image_url)+'" style="max-height:80px;border-radius:4px;border:1px solid #e5e7eb">' : '';
    var hidden = getEl('banner_translations'); if (hidden) hidden.value = JSON.stringify(b.translations || {});
    fillTranslationInputs(b.translations || {});
    var del = getEl('bannerDeleteBtn'); if (del) { del.style.display = window.CAN_MANAGE_BANNERS ? 'inline-block' : 'none'; if (b.id) del.onclick = function(){ if (confirm(t('confirm_delete'))) deleteBanner(b.id); }; }
    var form = getEl('bannerForm'); if (form && form.scrollIntoView) form.scrollIntoView({behavior:'smooth'});
  }

  function formatForDatetimeLocal(dt){ if (!dt) return ''; var d = new Date(dt.replace(' ', 'T')); if (isNaN(d.getTime())) return dt.replace(' ', 'T').substring(0,16); function p(n){ return ('0'+n).slice(-2); } return d.getFullYear()+'-'+p(d.getMonth()+1)+'-'+p(d.getDate())+'T'+p(d.getHours())+':'+p(d.getMinutes()); }
  function safeSet(id,v){ var el=getEl(id); if(!el) return; try{ el.value = (v===null||typeof v==='undefined')?'':v; }catch(e){} }
  function getEl(id){ return document.getElementById(id); }

  // translations: populate and collect from server-rendered inputs
  function fillTranslationInputs(translations){
    qsa('#translationsInlineTable tbody tr').forEach(function(row){
      var code = row.getAttribute('data-lang'); if (!code) return;
      var item = translations && translations[code] ? translations[code] : {};
      var titleEl = qs('.tr-title[data-lang="'+code+'"]', row) || qs('.tr-title', row);
      var subEl = qs('.tr-subtitle[data-lang="'+code+'"]', row) || qs('.tr-subtitle', row);
      var linkEl = qs('.tr-linktext[data-lang="'+code+'"]', row) || qs('.tr-linktext', row);
      if (titleEl) try { titleEl.value = item.title || ''; } catch(e) {}
      if (subEl) try { subEl.value = item.subtitle || ''; } catch(e) {}
      if (linkEl) try { linkEl.value = item.link_text || ''; } catch(e) {}
    });
  }

  function collectTranslationInputs(){
    var out = {};
    qsa('#translationsInlineTable tbody tr').forEach(function(row){
      var code = row.getAttribute('data-lang'); if (!code) return;
      var title = (qs('.tr-title[data-lang="'+code+'"]', row) || qs('.tr-title', row) || { value:'' }).value || '';
      var subtitle = (qs('.tr-subtitle[data-lang="'+code+'"]', row) || qs('.tr-subtitle', row) || { value:'' }).value || '';
      var link_text = (qs('.tr-linktext[data-lang="'+code+'"]', row) || qs('.tr-linktext', row) || { value:'' }).value || '';
      if (title !== '' || subtitle !== '' || link_text !== '') out[code] = { title: title, subtitle: subtitle, link_text: link_text };
    });
    var hidden = getEl('banner_translations'); if (hidden) hidden.value = JSON.stringify(out);
  }

  // save (create/update)
  var saveBtn = getEl('bannerSaveBtn');
  if (saveBtn) saveBtn.addEventListener('click', function(e){
    e.preventDefault();
    if (!window.CAN_MANAGE_BANNERS) { alert(t('no_permission_notice')); return; }
    collectTranslationInputs();
    var form = getEl('bannerForm'); if (!form) { setStatus('Form not found', true); return; }
    var fd = new FormData(form); fd.set('action','save'); fd.set('csrf_token', window.CSRF_TOKEN || '');
    setStatus(t('saving'));
    fetchText(API, { method:'POST', body: fd }).then(function(r){
      if (r.status >=200 && r.status < 300) {
        try { var j = JSON.parse(r.text || '{}'); if (j.success){ setStatus(j.message || t('saved')); setTimeout(function(){ loadBanners(); if (formWrap) formWrap.style.display = 'none'; }, 700); } else setStatus(j.message || t('error_save'), true); }
        catch(e){ setStatus('Invalid JSON', true); console.error(r.text); }
      } else setStatus('HTTP '+r.status, true);
    }).catch(function(err){ console.error(err); setStatus(t('error_save'), true); });
  });

  // new
  var newBtn = getEl('bannerNewBtn');
  if (newBtn) newBtn.addEventListener('click', function(){
    if (!window.CAN_MANAGE_BANNERS) { alert(t('no_permission_notice')); return; }
    if (formWrap) formWrap.style.display = 'block';
    var titleEl = getEl('bannerFormTitle'); if (titleEl) titleEl.textContent = t('banners.form_title_create') || 'Create Banner';
    var form = getEl('bannerForm'); if (form) form.reset();
    safeSet('banner_id', 0);
    var hidden = getEl('banner_translations'); if (hidden) hidden.value = '{}';
    qsa('#translationsInlineTable tbody tr').forEach(function(tr){ (qs('.tr-title', tr) || {}).value=''; (qs('.tr-subtitle', tr) || {}).value=''; (qs('.tr-linktext', tr) || {}).value=''; });
    var prev = getEl('banner_image_preview'); if (prev) prev.innerHTML = ''; var mprev = getEl('banner_mobile_image_preview'); if (mprev) mprev.innerHTML = '';
    var del = getEl('bannerDeleteBtn'); if (del) del.style.display = 'none';
  });

  var cancelBtn = getEl('bannerCancelBtn'); if (cancelBtn) cancelBtn.addEventListener('click', function(){ if (formWrap) formWrap.style.display = 'none'; });

  // image upload helper
  function uploadFileXHR(file, cb, progressCb){
    var fd = new FormData(); fd.append('file', file); fd.append('csrf_token', window.CSRF_TOKEN || '');
    var xhr = new XMLHttpRequest(); xhr.open('POST', '/api/upload_image.php', true); xhr.withCredentials = true;
    xhr.upload.onprogress = function(e){ if (e.lengthComputable && progressCb) progressCb(Math.round(e.loaded/e.total*100)); };
    xhr.onload = function(){ if (xhr.status>=200 && xhr.status<300){ try{ var j=JSON.parse(xhr.responseText); if (j && j.success) return cb(null, j.url, j.thumb||null); cb(j||{message:'Upload failed'}); } catch(e){ cb({message:'Invalid server response'}); } } else cb({message:'HTTP '+xhr.status}); };
    xhr.onerror = function(){ cb({message:'Network error'}); };
    xhr.send(fd);
  }

  var imgInput = getEl('banner_image_file'), mobInput = getEl('banner_mobile_image_file'), imgStatus = getEl('imageUploadStatus'), mobStatus = getEl('mobileImageUploadStatus');
  if (imgInput) imgInput.addEventListener('change', function(){ var f=this.files && this.files[0]; if(!f) return; if (imgStatus) imgStatus.textContent = t('uploading'); uploadFileXHR(f,function(err,url){ if (err){ if (imgStatus) imgStatus.textContent = err.message || t('error_upload'); return; } safeSet('banner_image_url', url); var prev = getEl('banner_image_preview'); if (prev) prev.innerHTML = '<img src="'+escapeAttr(url)+'" style="max-height:80px;border-radius:4px;border:1px solid #e5e7eb">'; if (imgStatus) imgStatus.textContent = t('uploaded'); }, function(p){ if (imgStatus) imgStatus.textContent = t('uploading')+' '+p+'%'; }); });
  if (mobInput) mobInput.addEventListener('change', function(){ var f=this.files && this.files[0]; if(!f) return; if (mobStatus) mobStatus.textContent = t('uploading'); uploadFileXHR(f,function(err,url){ if (err){ if (mobStatus) mobStatus.textContent = err.message || t('error_upload'); return; } safeSet('banner_mobile_image_url', url); var prev = getEl('banner_mobile_image_preview'); if (prev) prev.innerHTML = '<img src="'+escapeAttr(url)+'" style="max-height:80px;border-radius:4px;border:1px solid #e5e7eb">'; if (mobStatus) mobStatus.textContent = t('uploaded'); }, function(p){ if (mobStatus) mobStatus.textContent = t('uploading')+' '+p+'%'; }); });

  // search debounce
  var searchTimer = null;
  if (searchEl) {
    searchEl.addEventListener('input', function(){
      clearTimeout(searchTimer);
      var q = String(this.value || '');
      searchTimer = setTimeout(function(){ renderTable(filterBanners(q)); }, 200);
    });
  }

  // toggle translations area
  var toggleBtn = getEl('toggleTranslationsBtn'); if (toggleBtn && translationsArea) toggleBtn.addEventListener('click', function(){ translationsArea.style.display = (translationsArea.style.display === 'block') ? 'none' : 'block'; });

  // refresh
  var refreshBtn = getEl('bannerRefresh'); if (refreshBtn) refreshBtn.addEventListener('click', loadBanners);

  // init
  document.addEventListener('DOMContentLoaded', function(){
    loadBanners();
    if (!window.CAN_MANAGE_BANNERS) {
      qsa('#bannerForm input, #bannerForm select, #bannerForm button, #banner_image_file, #banner_mobile_image_file, #bannerSaveBtn, #bannerDeleteBtn, #bannerNewBtn').forEach(function(el){ el.disabled = true; });
      var nb = getEl('bannerNewBtn'); if (nb) nb.style.display = 'none';
    }
    if (window.DIRECTION && window.DIRECTION === 'rtl') {
      var root = getEl('adminBanners'); if (root) root.setAttribute('dir','rtl');
    }
  });

})();