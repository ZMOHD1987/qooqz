// htdocs/admin/assets/js/pages/menu_form.js
// Page initializer for menu_form fragment.
// - Binds dynamic translations panels (add/remove).
// - Binds image-studio opener (listens to ImageStudio:selected).
// - Submits the form via AJAX to /admin/fragments/menus_list.php (action=save).
// - On success dispatches menus:saved and menus:refresh and closes panel.
//
// Save as UTF-8 without BOM.

(function(){
  window.PageInitializers = window.PageInitializers || {};
  window.PageInitializers['menu_form'] = {
    init: function(root) {
      root = root || document;
      // apply translations if available
      if (window._admin && typeof window._admin.applyTranslations === 'function') {
        try { window._admin.applyTranslations(root); } catch(e){ console.warn(e); }
      }

      // panel close handlers
      Array.prototype.forEach.call(root.querySelectorAll('.panel-close'), function(btn){
        if (btn.__panelCloseBound) return;
        btn.__panelCloseBound = true;
        btn.addEventListener('click', function(e){
          e.preventDefault();
          if (window.AdminUI && typeof window.AdminUI.closePanel === 'function') window.AdminUI.closePanel();
        });
      });

      // Translations: add / remove panels
      var langSelect = root.querySelector('#lang_select');
      var addLangBtn = root.querySelector('#addLangBtn');
      var translationsContainer = root.querySelector('#translations_container');

      function createLangPanel(code) {
        code = String(code || '').trim();
        if (!code) return null;
        // prevent duplicates
        if (translationsContainer.querySelector('.lang-panel[data-lang="'+code+'"]')) {
          alert((window._admin && window._admin.t) ? window._admin.t('categories.form.lang_exists','Translation exists') : 'Translation exists');
          return null;
        }
        var div = document.createElement('div');
        div.className = 'lang-panel';
        div.setAttribute('data-lang', code);
        div.innerHTML = '<div style="display:flex;justify-content:space-between;align-items:center;"><strong>'+code+'</strong><button type="button" class="btn small removeLangBtn">Remove</button></div>'
          + '<input type="hidden" name="translations['+code+'][language_code]" value="'+code+'">'
          + '<label>Name<input name="translations['+code+'][name]" type="text"></label>'
          + '<label>Slug<input name="translations['+code+'][slug]" type="text"></label>'
          + '<label>Description<textarea name="translations['+code+'][description]" rows="2"></textarea></label>'
          + '<label>Meta title<input name="translations['+code+'][meta_title]" type="text"></label>'
          + '<label>Meta description<textarea name="translations['+code+'][meta_description]" rows="2"></textarea></label>';
        return div;
      }

      if (addLangBtn && langSelect) {
        addLangBtn.addEventListener('click', function(){
          var code = langSelect.value;
          var panel = createLangPanel(code);
          if (panel) translationsContainer.appendChild(panel);
        });
      }

      translationsContainer.addEventListener('click', function(e){
        if (e.target && e.target.classList.contains('removeLangBtn')) {
          var panel = e.target.closest('.lang-panel');
          if (!panel) return;
          if (!confirm((window._admin && window._admin.t) ? window._admin.t('categories.form.confirm_remove','Remove translation?') : 'Remove translation?')) return;
          panel.remove();
        }
      });

      // Image studio opener: listens to buttons with data-image-studio
      Array.prototype.forEach.call(root.querySelectorAll('[data-image-studio]'), function(btn){
        if (btn.__imageBound) return;
        btn.__imageBound = true;
        btn.addEventListener('click', function(){
          var ownerType = btn.getAttribute('data-owner-type') || 'category';
          var ownerId = parseInt(btn.getAttribute('data-owner-id') || '0', 10) || 0;
          var targetSelector = btn.getAttribute('data-image-target') || '#image_url';
          // open ImageStudio if available
          if (window.ImageStudio && typeof window.ImageStudio.open === 'function') {
            window.ImageStudio.open({
              ownerType: ownerType,
              ownerId: ownerId,
              onSelect: function(url){
                var input = root.querySelector(targetSelector);
                if (input) input.value = url;
                var preview = root.querySelector('#image_preview');
                var placeholder = root.querySelector('#image_placeholder');
                if (preview) { preview.src = url; preview.style.display = ''; }
                if (placeholder) placeholder.style.display = 'none';
              }
            });
          } else {
            // fallback: prompt for url
            var url = prompt('Image URL');
            if (url) {
              var input = root.querySelector(targetSelector);
              if (input) input.value = url;
              var preview = root.querySelector('#image_preview');
              var placeholder = root.querySelector('#image_placeholder');
              if (preview) { preview.src = url; preview.style.display = ''; }
              if (placeholder) placeholder.style.display = 'none';
            }
          }
        });
      });

      // Listen to ImageStudio selection event (if the studio dispatches global event)
      window.addEventListener('ImageStudio:selected', function(ev){
        try {
          var url = ev && ev.detail && ev.detail.url;
          if (!url) return;
          var input = root.querySelector('#image_url');
          if (input) input.value = url;
          var preview = root.querySelector('#image_preview');
          var placeholder = root.querySelector('#image_placeholder');
          if (preview) { preview.src = url; preview.style.display = ''; }
          if (placeholder) placeholder.style.display = 'none';
        } catch (e) { console.warn(e); }
      });

      // Form AJAX submit
      var form = root.querySelector('#categoryForm');
      if (!form) return;
      form.addEventListener('submit', function(e){
        e.preventDefault();
        var fd = new FormData(form);
        fd.set('action','save');
        // include translations panel data: panels already have inputs with correct names
        // ensure csrf present
        if (window.CSRF_TOKEN && !fd.get('csrf_token')) fd.set('csrf_token', window.CSRF_TOKEN);

        var btn = form.querySelector('#saveBtn');
        if (btn) btn.disabled = true;

        fetch(form.action, {
          method: 'POST',
          credentials: 'same-origin',
          body: fd,
          headers: { 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function(r){ return r.json(); })
        .then(function(json){
          if (btn) btn.disabled = false;
          if (json && json.success) {
            // dispatch saved events
            var evt = new CustomEvent('menus:saved', { detail: { message: json.message || 'Saved', data: json.data || {} } });
            window.dispatchEvent(evt);
            window.dispatchEvent(new CustomEvent('menus:refresh', { detail: { id: json.id, data: json.data } }));
            if (window.AdminUI && typeof window.AdminUI.closePanel === 'function') window.AdminUI.closePanel();
            if (window.AdminUI && typeof window.AdminUI.showToast === 'function') window.AdminUI.showToast(json.message || 'Saved');
          } else {
            alert((json && json.message) ? json.message : 'Error');
          }
        }).catch(function(err){
          if (btn) btn.disabled = false;
          console.error(err);
          alert('Error: ' + err.message);
        });
      });

      console.info('menu_form initialized');
    }
  };
})();