// Robust Image Studio fragment script (replace existing).
// Works inside AdminModal, popup (window.open) or direct page.
// On select/close it will:
//  - try AdminModal.closeModal()
//  - try postMessage to opener (if available)
//  - try window.close()
//  - always dispatch ImageStudio:selected or ImageStudio:close local events
(function () {
  'use strict';

  // IDs/classes used in fragment
  var UPLOAD_FORM_ID = 'uploadForm';
  var UPLOAD_INPUT_ID = 'uploadInput';
  var UPLOAD_BTN_ID = 'uploadBtn';
  var UPLOAD_STATUS_ID = 'uploadStatus';
  var GALLERY_ID = 'studioGallery';
  var NO_IMAGES_ID = 'noImages';
  var CLOSE_BTN_ID = 'studioCloseBtn';

  function q(id) { return document.getElementById(id); }

  function log() {
    if (window.console && console.log) console.log.apply(console, arguments);
  }

  // Try close the modal/popup in several ways (AdminModal -> postMessage to opener -> window.close)
  function tryCloseContext() {
    var closed = false;
    // 1) try AdminModal
    try {
      if (window.AdminModal && typeof window.AdminModal.closeModal === 'function') {
        try { window.AdminModal.closeModal(); closed = true; log('ImageStudio: closed via AdminModal'); } catch (e) { log('AdminModal.close failed', e); }
      }
    } catch (e) { /* ignore */ }

    // 2) try postMessage to opener (if exists)
    try {
      if (!closed && window.opener && window.opener !== window) {
        try {
          // prefer to send only to same origin; if that throws fallback to '*'
          var targetOrigin = '*';
          try {
            // might throw if cross-origin access is forbidden
            var oOrigin = window.opener.location && window.opener.location.origin;
            if (oOrigin) targetOrigin = oOrigin;
          } catch (ee) { targetOrigin = '*'; }
          window.opener.postMessage({ type: 'ImageStudio:close' }, targetOrigin);
          closed = true;
          try { window.close(); } catch (e) {}
          log('ImageStudio: close posted to opener');
        } catch (e) {
          log('postMessage to opener failed', e);
        }
      }
    } catch (e) { /* ignore */ }

    // 3) try window.close() as last resort
    try {
      if (!closed) {
        window.close();
        closed = true;
        log('ImageStudio: window.close() attempted');
      }
    } catch (e) { /* ignore */ }

    return closed;
  }

  function dispatchSelected(url) {
    try {
      window.dispatchEvent(new CustomEvent('ImageStudio:selected', { detail: { url: url } }));
    } catch (e) { log('dispatchSelected failed', e); }

    // Always try to close context after dispatching
    // But also attempt to notify opener directly with the URL payload
    try {
      if (window.opener && window.opener !== window) {
        try {
          var targetOrigin = '*';
          try {
            var oOrigin = window.opener.location && window.opener.location.origin;
            if (oOrigin) targetOrigin = oOrigin;
          } catch (e) { targetOrigin = '*'; }
          window.opener.postMessage({ type: 'ImageStudio:selected', url: url }, targetOrigin);
          log('ImageStudio: posted selected to opener', url);
        } catch (e) {
          log('postMessage(selected) to opener failed', e);
        }
      }
    } catch (e) { /* ignore */ }

    tryCloseContext();
  }

  function dispatchClose() {
    try {
      window.dispatchEvent(new CustomEvent('ImageStudio:close'));
    } catch (e) { log('dispatchClose failed', e); }
    tryCloseContext();
  }

  function setStatus(msg, isError) {
    var el = q(UPLOAD_STATUS_ID);
    if (!el) return;
    el.textContent = msg || '';
    el.style.color = isError ? '#c0392b' : '#666';
    el.style.display = msg ? '' : 'none';
  }

  // Bind thumbs (delegated safe)
  function bindThumbs() {
    var gallery = q(GALLERY_ID);
    if (!gallery) return;
    var thumbs = gallery.querySelectorAll('.thumb');
    thumbs.forEach(function (t) {
      if (t.__bound) return;
      t.__bound = true;
      t.addEventListener('click', function () {
        var url = this.getAttribute('data-url') || this.dataset.url;
        if (!url) return;
        dispatchSelected(url);
      });
    });
  }

  // Upload handler
  function bindUpload() {
    var form = q(UPLOAD_FORM_ID);
    var input = q(UPLOAD_INPUT_ID);
    var btn = q(UPLOAD_BTN_ID);
    var gallery = q(GALLERY_ID);

    if (!form) return;
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      setStatus('');
      if (!input || !input.files || !input.files[0]) {
        setStatus('اختر ملفاً أولاً', true);
        return;
      }
      var fd = new FormData(form);
      if (btn) btn.disabled = true;
      setStatus('جارٍ الرفع...');
      fetch(form.action, { method: 'POST', credentials: 'same-origin', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function (res) {
          return res.text().then(function (txt) {
            if (!res.ok) throw new Error('HTTP ' + res.status + ' ' + txt);
            try { return JSON.parse(txt); } catch (e) { throw new Error('Invalid JSON: ' + txt); }
          });
        })
        .then(function (json) {
          if (btn) btn.disabled = false;
          if (json && json.success && json.url) {
            setStatus('تم الرفع', false);
            // add to gallery
            if (q(NO_IMAGES_ID)) { try { q(NO_IMAGES_ID).remove(); } catch (e) {} }
            if (gallery) {
              var div = document.createElement('div');
              div.className = 'thumb';
              div.setAttribute('data-url', json.url);
              var img = document.createElement('img');
              img.src = json.thumb_url || json.url;
              img.style.cssText = 'width:100%;height:100%;object-fit:cover';
              div.appendChild(img);
              div.addEventListener('click', function () { dispatchSelected(json.url); });
              gallery.insertBefore(div, gallery.firstChild);
            }
            // notify parent and close
            dispatchSelected(json.url);
            form.reset();
          } else {
            var msg = (json && json.message) ? json.message : 'Upload failed';
            setStatus(msg, true);
          }
        })
        .catch(function (err) {
          if (btn) btn.disabled = false;
          log('upload error', err);
          setStatus(err && err.message ? err.message : 'Upload error', true);
        });
    });
  }

  // close button bind
  function bindCloseBtn() {
    var btn = q(CLOSE_BTN_ID) || document.querySelector('.studio .studio-close') || document.querySelector('.studio header .btn');
    if (!btn) return;
    if (btn.__bound) return;
    btn.__bound = true;
    btn.addEventListener('click', function () {
      dispatchClose();
    });
  }

  // init
  function init() {
    bindThumbs();
    bindUpload();
    bindCloseBtn();
    setTimeout(bindThumbs, 200); // rebind if DOM inserted late
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();

  // Expose for debug
  window.ImageStudioFragment = window.ImageStudioFragment || {};
  window.ImageStudioFragment.dispatchSelected = dispatchSelected;
  window.ImageStudioFragment.dispatchClose = dispatchClose;

})();