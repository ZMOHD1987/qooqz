// htdocs/admin/assets/js/image-studio.js
// Modal-based Image Studio integration.
// Usage:
//   ImageStudio.open({ ownerType: 'menu', ownerId: 123, onSelect: function(url){ ... } })
//
// - Loads /admin/images.php?owner_type=...&owner_id=... into an in-page modal (AJAX).
// - Binds thumbnail clicks and the upload form inside the fragment.
// - Expects image_upload.php to return JSON { success: true, url: "...", ... } on success.
// - Keeps session/CSRF intact because it runs in-page (same-origin).
//
// Save as UTF-8 without BOM.

var ImageStudio = (function () {
  var modalRoot = null;
  var boundEvents = [];

  function createModal() {
    // Root overlay
    var root = document.createElement('div');
    root.id = 'imageStudioModal';
    Object.assign(root.style, {
      position: 'fixed',
      inset: '0',
      background: 'rgba(0,0,0,0.45)',
      display: 'flex',
      alignItems: 'center',
      justifyContent: 'center',
      zIndex: 20000
    });

    // Inner panel
    var inner = document.createElement('div');
    inner.id = 'imageStudioInner';
    Object.assign(inner.style, {
      width: '92%',
      maxWidth: '1100px',
      maxHeight: '86vh',
      overflow: 'auto',
      background: '#fff',
      borderRadius: '8px',
      padding: '12px',
      boxSizing: 'border-box'
    });

    // Append
    root.appendChild(inner);
    document.body.appendChild(root);
    return root;
  }

  function remoteFetchHtml(url) {
    return fetch(url, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then(function (res) { return res.text(); });
  }

  function safeParseJson(text) {
    try { return JSON.parse(text); } catch (e) { return null; }
  }

  function bindFragment(inner, opts) {
    // close button — look for common id
    var closeBtn = inner.querySelector('#studioCloseBtn');
    if (closeBtn) {
      var cb = function () { ImageStudio.close(); };
      closeBtn.addEventListener('click', cb);
      boundEvents.push({ el: closeBtn, type: 'click', fn: cb });
    }

    // thumbnail selection
    var thumbs = inner.querySelectorAll('.thumb');
    thumbs.forEach(function (t) {
      var fn = function () {
        var url = t.getAttribute('data-url') || t.dataset.url;
        if (opts && typeof opts.onSelect === 'function') {
          try { opts.onSelect(url); } catch (e) { console.error(e); }
        }
        ImageStudio.close();
      };
      t.addEventListener('click', fn);
      boundEvents.push({ el: t, type: 'click', fn: fn });
    });

    // upload form (AJAX)
    var form = inner.querySelector('#uploadForm');
    if (form) {
      var submitHandler = function (e) {
        e.preventDefault();
        var submitBtn = form.querySelector('button[type="submit"]');
        if (submitBtn) submitBtn.disabled = true;
        var fd = new FormData(form);

        fetch(form.action, { method: 'POST', credentials: 'same-origin', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
          .then(function (res) { return res.text(); })
          .then(function (text) {
            var json = safeParseJson(text);
            if (!json) throw new Error('Invalid JSON response from server: ' + text);
            if (json.success) {
              var selectedUrl = json.url || json.file_url || json.fileUrl || json.file_url;
              if (opts && typeof opts.onSelect === 'function') {
                try { opts.onSelect(selectedUrl); } catch (e) { console.error(e); }
              }
              ImageStudio.close();
            } else {
              alert(json.message || 'فشل الرفع');
            }
          })
          .catch(function (err) {
            console.error('Image upload error:', err);
            alert('Upload error: ' + (err.message || err));
          })
          .finally(function () {
            if (submitBtn) submitBtn.disabled = false;
          });
      };
      form.addEventListener('submit', submitHandler);
      boundEvents.push({ el: form, type: 'submit', fn: submitHandler });
    }
  }

  function cleanupBoundEvents() {
    boundEvents.forEach(function (b) {
      try { b.el.removeEventListener(b.type, b.fn); } catch (e) { /* ignore */ }
    });
    boundEvents = [];
  }

  function open(opts) {
    opts = opts || {};
    var ownerType = opts.ownerType || 'user';
    var ownerId = typeof opts.ownerId !== 'undefined' ? opts.ownerId : 0;

    var url = '/admin/images.php?owner_type=' + encodeURIComponent(ownerType) + '&owner_id=' + encodeURIComponent(ownerId);

    // Create modal and fetch fragment
    modalRoot = createModal();
    var inner = document.getElementById('imageStudioInner');
    inner.innerHTML = '<div style="padding:18px">جارٍ تحميل الاستوديو…</div>';

    remoteFetchHtml(url).then(function (html) {
      // Insert HTML fragment
      inner.innerHTML = html;
      // Bind fragment controls
      try { bindFragment(inner, opts); } catch (e) { console.error('bindFragment error', e); }
    }).catch(function (err) {
      inner.innerHTML = '<div style="padding:18px;color:#900">خطأ بتحميل الاستوديو: ' + (err.message || err) + '</div>';
      console.error('Failed to load ImageStudio fragment:', err);
    });
  }

  function close() {
    cleanupBoundEvents();
    if (modalRoot && modalRoot.parentNode) modalRoot.parentNode.removeChild(modalRoot);
    modalRoot = null;
  }

  // Expose API
  return { open: open, close: close };
})();