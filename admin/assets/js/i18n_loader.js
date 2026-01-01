/**
 * admin/assets/js/i18n_loader.js
 * Safe i18n loader that respects window.ADMIN_UI.__skipGlobalLoad.
 * - loadLangAndApply(basePath, root, cb) : loads global lang only if NOT skipped.
 * - loadPageFile(pageUrl, root, cb) : loads and merges page file.
 * - translateFragment(root) : applies translations.
 *
 * Save as UTF-8 without BOM.
 */
(function (global) {
  'use strict';
  var I18nLoader = global.I18nLoader || {};
  global.I18nLoader = I18nLoader;

  // helpers
  function isElement(o){ return !!(o && o.nodeType === 1); }
  function safeMerge(dest, src){
    if (!src || typeof src !== 'object') return dest;
    dest = dest || {};
    Object.keys(src).forEach(function(k){
      var v = src[k];
      if (v && typeof v === 'object' && !Array.isArray(v)){
        dest[k] = dest[k] || {};
        safeMerge(dest[k], v);
      } else dest[k] = v;
    });
    return dest;
  }
  function getNested(obj, path){
    if (!obj || !path) return undefined;
    var parts = String(path).split('.');
    var cur = obj;
    for (var i=0;i<parts.length;i++){
      if (!cur || typeof cur !== 'object' || !(parts[i] in cur)) return undefined;
      cur = cur[parts[i]];
    }
    return cur;
  }

  I18nLoader._store = I18nLoader._store || { strings: {} };

  function translateLocal(root){
    root = root || document;
    try {
      var store = I18nLoader._store.strings || (global.ADMIN_UI && global.ADMIN_UI.strings) || {};
      Array.prototype.forEach.call(root.querySelectorAll('[data-i18n]'), function(el){
        var k = el.getAttribute('data-i18n');
        var v = getNested(store, k);
        if (typeof v === 'string') el.textContent = v;
      });
      Array.prototype.forEach.call(root.querySelectorAll('[data-i18n-placeholder]'), function(el){
        var k = el.getAttribute('data-i18n-placeholder');
        var v = getNested(store, k);
        if (typeof v === 'string') el.placeholder = v;
      });
      Array.prototype.forEach.call(root.querySelectorAll('[data-i18n-html]'), function(el){
        var k = el.getAttribute('data-i18n-html');
        var v = getNested(store, k);
        if (typeof v === 'string') el.innerHTML = v;
      });
    } catch (e) { if (global.console) console.warn('translateLocal error', e); }
  }

  I18nLoader.translateFragment = I18nLoader.translateFragment || function(root){
    // prefer external translator if present
    if (global.AdminI18n && typeof global.AdminI18n.translateFragment === 'function') {
      try { return global.AdminI18n.translateFragment(root); } catch(e) {}
    }
    translateLocal(root);
  };

  function loadJSON(url){
    return fetch(url, { credentials: 'same-origin', cache: 'no-cache' }).then(function(res){
      if (!res.ok) throw new Error('Failed to load ' + url + ' status ' + res.status);
      return res.json();
    });
  }

  // loadLangAndApply: loads global language ONLY if __skipGlobalLoad is not true
  I18nLoader.loadLangAndApply = I18nLoader.loadLangAndApply || function(basePath, root, cb){
    cb = typeof cb === 'function' ? cb : function(){};
    root = root || document;
    try {
      if (global.ADMIN_UI && global.ADMIN_UI.__skipGlobalLoad) {
        // skip global load by design
        if (global.console && console.info) console.info('I18nLoader: skipping global language load due to __skipGlobalLoad');
        // merge any existing ADMIN_UI.strings into store for consistency
        if (global.ADMIN_UI && global.ADMIN_UI.strings) safeMerge(I18nLoader._store.strings, global.ADMIN_UI.strings);
        try { I18nLoader.translateFragment(root); } catch(e){ translateLocal(root); }
        global.DC_LANG_LOADED = true;
        return cb(null, null);
      }
    } catch (e) { /* proceed normally if check fails */ }

    var lang = root.getAttribute && root.getAttribute('data-lang') || global.ADMIN_LANG || document.documentElement.lang || 'en';
    var url = String(basePath).replace(/\/$/, '') + '/' + encodeURIComponent(lang) + '.json';
    loadJSON(url).then(function(json){
      if (json) {
        I18nLoader._store.strings = I18nLoader._store.strings || {};
        safeMerge(I18nLoader._store.strings, json.strings || json);
        global.ADMIN_UI = global.ADMIN_UI || {};
        global.ADMIN_UI.strings = global.ADMIN_UI.strings || {};
        safeMerge(global.ADMIN_UI.strings, json.strings || json);
        if (json.direction) global.ADMIN_UI.direction = json.direction;
      }
      try { I18nLoader.translateFragment(root); } catch(e){ translateLocal(root); }
      try { global.dispatchEvent(new CustomEvent('dc:i18n:lang-applied', { detail:{ lang: lang, source: url } })); } catch(e){}
      global.DC_LANG_LOADED = true;
      cb(null, json);
    }).catch(function(err){
      try { I18nLoader.translateFragment(root); } catch(e){ translateLocal(root); }
      try { global.dispatchEvent(new CustomEvent('dc:i18n:lang-applied', { detail:{ lang: lang, source: url, error: String(err) } })); } catch(e){}
      global.DC_LANG_LOADED = true;
      cb(err);
    });
  };

  // loadPageFile: always allowed (page-only)
  I18nLoader.loadPageFile = I18nLoader.loadPageFile || function(pageUrl, root, cb){
    cb = typeof cb === 'function' ? cb : function(){};
    root = root || document;
    loadJSON(pageUrl).then(function(json){
      if (json) {
        I18nLoader._store.strings = I18nLoader._store.strings || {};
        safeMerge(I18nLoader._store.strings, json.strings || json);
        global.ADMIN_UI = global.ADMIN_UI || {};
        global.ADMIN_UI.strings = global.ADMIN_UI.strings || {};
        safeMerge(global.ADMIN_UI.strings, json.strings || json);
        if (json.direction) global.ADMIN_UI.direction = json.direction;
      }
      try { I18nLoader.translateFragment(root); } catch(e){ translateLocal(root); }
      try { global.dispatchEvent(new CustomEvent('dc:i18n:page-applied', { detail:{ url: pageUrl } })); } catch(e){}
      cb(null, json);
    }).catch(function(err){
      try { I18nLoader.translateFragment(root); } catch(e){ translateLocal(root); }
      try { global.dispatchEvent(new CustomEvent('dc:i18n:page-applied', { detail:{ url: pageUrl, error: String(err) } })); } catch(e){}
      cb(err);
    });
  };

})(window);