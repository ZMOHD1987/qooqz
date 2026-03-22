/*!
 * admin/assets/js/sidebar-toggle.js
 * Sidebar toggle — mobile drawer + desktop collapse (icon ↔ icon+text)
 * v4 — single source of truth, blocks admin_core interference
 */
(function () {
  'use strict';

  /* ── prevent double init ── */
  if (window.SidebarToggle && window.SidebarToggle.__installed) return;

  var STORAGE_KEY = 'admin_sidebar_collapsed';

  /* ════════════════════════════════════════════════════════
     HELPERS
  ════════════════════════════════════════════════════════ */
  function isMobile()    { return window.innerWidth <= 768; }
  function findSidebar() { return document.getElementById('adminSidebar') || document.querySelector('.admin-sidebar'); }
  function findBackdrop(){ return document.querySelector('.sidebar-backdrop'); }

  /* Clone the toggle button to wipe ALL previous listeners
     (from admin_core.js initSidebar, old sidebar-toggle, etc.)
     Then mark it so admin_core won't re-bind to it. */
  function getFreshToggle() {
    var old = document.getElementById('sidebarToggle');
    if (!old) return null;
    var btn = old.cloneNode(true);
    btn._adminSidebarBound = true;   /* stop admin_core.js from binding */
    btn.__stBound          = true;
    old.parentNode.replaceChild(btn, old);
    return btn;
  }

  /* ════════════════════════════════════════════════════════
     STATE — desktop collapse
  ════════════════════════════════════════════════════════ */
  function savedCollapsed() {
    try { return localStorage.getItem(STORAGE_KEY) === '1'; } catch(e) { return false; }
  }
  function persistCollapsed(v) {
    try { localStorage.setItem(STORAGE_KEY, v ? '1' : '0'); } catch(e) {}
  }

  function isCollapsed() { return document.body.classList.contains('sidebar-collapsed'); }
  function isMobileOpen(){ return document.body.classList.contains('sidebar-open');    }

  /* ── desktop: collapse (icon-only) ── */
  function collapse() {
    document.body.classList.add('sidebar-collapsed');
    document.body.classList.remove('sidebar-open');
    persistCollapsed(true);
    updateToggleBtn();
  }

  /* ── desktop: expand (icon + text) ── */
  function expand() {
    document.body.classList.remove('sidebar-collapsed');
    persistCollapsed(false);
    updateToggleBtn();
  }

  /* ── mobile: open drawer ── */
  function openMobile() {
    document.body.classList.add('sidebar-open');
    updateToggleBtn();
  }

  /* ── mobile: close drawer ── */
  function closeMobile() {
    document.body.classList.remove('sidebar-open');
    updateToggleBtn();
  }

  /* Sync aria-expanded on the button */
  function updateToggleBtn() {
    var btn = document.getElementById('sidebarToggle');
    if (!btn) return;
    var expanded = isMobile() ? isMobileOpen() : !isCollapsed();
    btn.setAttribute('aria-expanded', expanded ? 'true' : 'false');
  }

  /* ════════════════════════════════════════════════════════
     MAIN CLICK HANDLER
  ════════════════════════════════════════════════════════ */
  function onToggleClick(e) {
    e.preventDefault();
    e.stopImmediatePropagation();   /* stop any other listener on this element */
    if (isMobile()) {
      isMobileOpen() ? closeMobile() : openMobile();
    } else {
      isCollapsed() ? expand() : collapse();
    }
  }

  /* ════════════════════════════════════════════════════════
     TOOLTIPS (shown on collapsed icon hover)
  ════════════════════════════════════════════════════════ */
  function injectTooltips() {
    var sb = findSidebar();
    if (!sb) return;
    sb.querySelectorAll('.sidebar-link').forEach(function(link) {
      if (link.getAttribute('data-tooltip')) return;
      var t = link.querySelector('.sidebar-title');
      if (t && t.textContent.trim())
        link.setAttribute('data-tooltip', t.textContent.trim());
    });
  }

  /* ════════════════════════════════════════════════════════
     BIND EVENTS
  ════════════════════════════════════════════════════════ */
  function bindEvents() {
    var btn      = getFreshToggle();   /* clone = no old listeners */
    var sidebar  = findSidebar();
    var backdrop = findBackdrop();

    if (btn)      btn.addEventListener('click', onToggleClick, true); /* capture */
    if (backdrop) backdrop.addEventListener('click', function(){ if (isMobile()) closeMobile(); });

    document.addEventListener('keydown', function(e){
      if (e.key === 'Escape' && isMobile() && isMobileOpen()) closeMobile();
    });

    if (sidebar) {
      sidebar.addEventListener('click', function(e){
        if (!isMobile()) return;
        var a = e.target.closest('a');
        if (!a) return;
        var href = a.getAttribute('href') || '';
        if (href === '#' || /^(javascript:|data:|vbscript:)/i.test(href)) return;
        setTimeout(closeMobile, 150);
      });
    }

    /* resize: clean stale state when crossing breakpoint */
    var resizeTimer;
    window.addEventListener('resize', function(){
      clearTimeout(resizeTimer);
      resizeTimer = setTimeout(function(){
        if (!isMobile()) {
          document.body.classList.remove('sidebar-open');
        } else {
          /* on mobile the collapse class is meaningless — remove it */
          document.body.classList.remove('sidebar-collapsed');
        }
        updateToggleBtn();
      }, 200);
    });
  }

  /* ════════════════════════════════════════════════════════
     BLOCK admin_core.js initSidebar BEFORE it runs
     (admin_core.js checks window.SidebarToggle.__installed
      but still calls its own initSidebar — we nullify it here)
  ════════════════════════════════════════════════════════ */
  function neutraliseAdminCore() {
    /* If Admin object already exists, overwrite initSidebar */
    if (window.Admin && typeof window.Admin === 'object') {
      window.Admin._initSidebarDisabled = true;
    }
    /* Patch it again after DOMContentLoaded in case admin_core runs later */
    document.addEventListener('DOMContentLoaded', function(){
      if (window.Admin) window.Admin._initSidebarDisabled = true;
    });
    /* Listen for admin_core init event and re-clone the toggle
       if admin_core somehow bound to it again */
    window.addEventListener('admin:content:loaded', function(){
      var btn = document.getElementById('sidebarToggle');
      if (btn && !btn.__stBound) {
        /* admin_core re-bound to it — clone and re-bind */
        bindToggleOnly();
      }
    });
  }

  function bindToggleOnly() {
    var btn = getFreshToggle();
    if (btn) btn.addEventListener('click', onToggleClick, true);
  }

  /* ════════════════════════════════════════════════════════
     INIT
  ════════════════════════════════════════════════════════ */
  function init() {
    if (!findSidebar()) { setTimeout(init, 250); return; }

    /* Set initial state */
    if (isMobile()) {
      document.body.classList.remove('sidebar-open');
      document.body.classList.remove('sidebar-collapsed');
    } else {
      document.body.classList.remove('sidebar-open');
      /* Restore last known state from localStorage */
      if (savedCollapsed()) {
        collapse();
      } else {
        expand();
      }
    }

    injectTooltips();
    neutraliseAdminCore();
    bindEvents();

    /* Public API */
    window.SidebarToggle = {
      __installed : true,
      toggle      : function(){ isMobile() ? (isMobileOpen() ? closeMobile() : openMobile()) : (isCollapsed() ? expand() : collapse()); },
      expand      : expand,
      collapse    : collapse,
      isCollapsed : isCollapsed,
      isMobile    : isMobile
    };

    console.log('[SidebarToggle v4] ready | mobile:', isMobile(), '| collapsed:', isCollapsed());
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

})();