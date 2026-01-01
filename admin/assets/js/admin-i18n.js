// Simple admin-i18n loader — supports:
//  - /languages/admin/{lang}.json
//  - /languages/DeliveryCompany/{lang}.json
// Respects window.ADMIN_UI.__skipGlobalLoad
(function(global){
  'use strict';
  if (global.AdminI18n && global.AdminI18n.__inited) return;
  global.AdminI18n = global.AdminI18n || {};
  AdminI18n.__inited = true;

  function deepMerge(dest, src){
    if (!src || typeof src !== 'object') return dest || {};
    dest = dest || {};
    Object.keys(src).forEach(function(k){
      var v = src[k];
      if (v && typeof v === 'object' && !Array.isArray(v)){
        dest[k] = dest[k] || {};
        deepMerge(dest[k], v);
      } else dest[k] = v;
    });
    return dest;
  }

  function loadJson(url){
    return fetch(url, { credentials:'same-origin', cache:'no-cache' }).then(function(res){
      if (!res.ok) return Promise.reject(new Error('Failed '+url+' status '+res.status));
      return res.json();
    });
  }

  function getLang(){
    return (global.ADMIN_LANG || (global.ADMIN_UI && global.ADMIN_UI.lang) || document.documentElement.lang || 'en').toString();
  }

  // Build default candidates for a page name (pageName = 'DeliveryCompany')
  AdminI18n.buildCandidates = function(pageName){
    var lang = getLang();
    var baseAdmin = '/languages/admin';
    var candidates = [];
    // global
    candidates.push(baseAdmin + '/' + encodeURIComponent(lang) + '.json');
    // page-specific path (your chosen pattern)
    if (pageName) candidates.push('/languages/' + encodeURIComponent(pageName) + '/' + encodeURIComponent(lang) + '.json');
    return candidates;
  };

  // Load array of paths sequentially, merge into window.ADMIN_UI.strings
  AdminI18n.loadPaths = function(paths, root){
    root = root || document;
    if (!paths) return Promise.resolve();
    var arr = Array.isArray(paths) ? paths.slice() : [paths];
    // unique
    arr = arr.filter(function(v,i,a){ return v && a.indexOf(v) === i; });

    // skip global if fragment set the flag
    var skipGlobal = !!(global.ADMIN_UI && global.ADMIN_UI.__skipGlobalLoad);

    return arr.reduce(function(p, url){
      return p.then(function(){
        // if skipping global and url is global pattern, skip it
        try {
          var lang = getLang();
          var globalPattern = '/languages/admin/' + encodeURIComponent(lang) + '.json';
          if (skipGlobal && String(url).indexOf(globalPattern) !== -1) {
            console.info('AdminI18n: skipping global', url);
            return Promise.resolve();
          }
        } catch(e){}
        return loadJson(url).then(function(json){
          if (json) {
            global.ADMIN_UI = global.ADMIN_UI || {};
            global.ADMIN_UI.strings = global.ADMIN_UI.strings || {};
            deepMerge(global.ADMIN_UI.strings, json.strings || json || {});
            if (json.direction) global.ADMIN_UI.direction = json.direction;
          }
        }).catch(function(err){
          console.info('AdminI18n: candidate failed', url, err && err.message);
        });
      });
    }, Promise.resolve()).then(function(){
      // apply translations after merge
      try { if (global._admin && typeof global._admin.applyTranslations === 'function') global._admin.applyTranslations(root); }
      catch(e){ /* ignore */ }
      try { global.dispatchEvent(new CustomEvent('dc:lang:loaded', { detail: { lang: getLang() } })); } catch(e){}
    });
  };

  // convenience: translate a fragment (tries other translators first)
  AdminI18n.translateFragment = function(root){
    root = root || document;
    if (global.I18nLoader && typeof global.I18nLoader.translateFragment === 'function'){
      try { return global.I18nLoader.translateFragment(root); } catch(e){}
    }
    if (global._admin && typeof global._admin.applyTranslations === 'function'){
      try { return global._admin.applyTranslations(root); } catch(e){}
    }
    // fallback local
    var S = (global.ADMIN_UI && global.ADMIN_UI.strings) ? global.ADMIN_UI.strings : {};
    Array.prototype.forEach.call(root.querySelectorAll('[data-i18n]'), function(el){
      var key = el.getAttribute('data-i18n');
      if (!key) return;
      var parts = key.split('.');
      var cur = S;
      for (var i=0;i<parts.length;i++){ if (!cur) { cur = null; break; } cur = cur[parts[i]]; }
      if (typeof cur === 'string') el.textContent = cur;
    });
  };

})(window);