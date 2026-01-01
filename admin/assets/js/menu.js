// htdocs/admin/assets/js/menu.js
// Handles expand/collapse of sidebar submenus, keyboard support, and remembers open items in localStorage.

(function(){
  'use strict';
  var storageKey = 'admin.sidebar.openIds';

  function getOpenIds(){
    try { return JSON.parse(localStorage.getItem(storageKey) || '[]'); } catch(e){ return []; }
  }
  function saveOpenIds(arr){ try { localStorage.setItem(storageKey, JSON.stringify(arr || [])); } catch(e){} }

  function toggleLi(li, forceOpen){
    if (!li) return;
    var isOpen = li.classList.contains('open');
    var shouldOpen = (typeof forceOpen === 'boolean') ? forceOpen : !isOpen;
    if (shouldOpen) {
      li.classList.add('open');
      li.querySelectorAll('.sidebar-toggle[aria-expanded]').forEach(function(b){ b.setAttribute('aria-expanded','true'); });
    } else {
      li.classList.remove('open');
      li.querySelectorAll('.sidebar-toggle[aria-expanded]').forEach(function(b){ b.setAttribute('aria-expanded','false'); });
    }
    // persist id if present
    var id = li.getAttribute('data-menu-id');
    if (id) {
      var ids = getOpenIds();
      ids = ids.filter(Boolean);
      var idx = ids.indexOf(id);
      if (shouldOpen && idx === -1) { ids.push(id); }
      if (!shouldOpen && idx !== -1) { ids.splice(idx,1); }
      saveOpenIds(ids);
    }
  }

  function init() {
    var sidebar = document.getElementById('adminSidebar');
    if (!sidebar) return;

    // restore open state
    var openIds = getOpenIds();
    openIds.forEach(function(id){
      var li = sidebar.querySelector('li[data-menu-id="'+ id +'"]');
      if (li) li.classList.add('open');
    });

    // attach handlers for toggle buttons/caret links
    sidebar.querySelectorAll('.sidebar-link').forEach(function(link){
      // if link is a toggle-button or has submenu (has-children)
      var li = link.closest('li');
      if (!li) return;
      if (li.classList.contains('has-children')) {
        link.addEventListener('click', function(e){
          // if the link has href '#' or no meaningful href, toggle instead of navigating
          var href = link.getAttribute('href') || '';
          // if element is a button (no href) or href is '#' or link has class sidebar-toggle -> toggle
          var isToggleBtn = link.classList.contains('sidebar-toggle') || href === '#' || href.trim() === '';
          if (isToggleBtn) {
            e.preventDefault();
            toggleLi(li);
            return;
          }
          // else for anchor with a real href, we still should toggle on small screens AFTER navigation occurs via admin.js
          // (no-op here)
        }, { passive: true });
      }
    });

    // keyboard accessibility: Enter or Space on toggle
    sidebar.querySelectorAll('.sidebar-toggle').forEach(function(btn){
      btn.addEventListener('keydown', function(e){
        if (e.key === 'Enter' || e.key === ' ') {
          e.preventDefault();
          var li = btn.closest('li');
          toggleLi(li);
        }
      });
    });
  }

  // init on DOM ready
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();

  // expose for debug
  window.AdminMenu = { toggleLi: toggleLi, getOpenIds: getOpenIds, saveOpenIds: saveOpenIds };
})();