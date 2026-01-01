/*!
 * Robust sidebar toggle
 * - Finds toggle (#sidebarToggle or [data-sidebar-toggle])
 * - Finds sidebar (#adminSidebar, .admin-sidebar, [role="navigation"])
 * - Manages body.sidebar-open and sidebar.open classes
 * - Creates/uses backdrop .sidebar-backdrop
 * - Persists state in localStorage (admin.sidebar.open)
 * - Exposes SidebarToggle.open/close/toggle
 */
(function () {
  'use strict';

  if (window.SidebarToggle && window.SidebarToggle.__installed) return;
  var SidebarToggle = { __installed: true };

  function log() { if (window.Admin && Admin.log) Admin.log.apply(Admin, arguments); else if (console && console.log) console.log.apply(console, arguments); }
  function warn() { if (window.Admin && Admin.warn) Admin.warn.apply(Admin, arguments); else if (console && console.warn) console.warn.apply(console, arguments); }

  function $(sel, ctx) { try { return (ctx || document).querySelector(sel); } catch (e) { return null; } }
  function $all(sel, ctx) { try { return Array.prototype.slice.call((ctx || document).querySelectorAll(sel)); } catch (e) { return []; } }

  function findToggle() {
    return $('#sidebarToggle') || $('[data-sidebar-toggle]') || $('[data-toggle="sidebar"]') || null;
  }

  // helper to query by selector string (returns first) - resilient
  function q(selector) {
    try { return document.querySelector(selector); } catch (e) { return null; }
  }

  function findSidebar() {
    return $('#adminSidebar') || $('.admin-sidebar') || q('[role="navigation"]') || q('aside');
  }

  // create or return existing backdrop
  function ensureBackdrop() {
    var b = document.querySelector('.sidebar-backdrop');
    if (b) return b;
    b = document.createElement('div');
    b.className = 'sidebar-backdrop';
    // minimal inline style so it's visible; you should override in CSS file
    b.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,0.35);display:none;z-index:1200;';
    document.body.appendChild(b);
    return b;
  }

  function isOpen() {
    return document.body.classList.contains('sidebar-open') || (findSidebar() && findSidebar().classList.contains('open'));
  }

  function setOpenState(open, opts) {
    opts = opts || {};
    var sidebar = findSidebar();
    var toggle = findToggle();
    var backdrop = ensureBackdrop();

    if (open) {
      document.body.classList.add('sidebar-open');
      if (sidebar) sidebar.classList.add('open');
      if (toggle) toggle.setAttribute('aria-expanded', 'true');
      backdrop.style.display = 'block';
      // small tick to allow CSS transitions
      setTimeout(function(){ backdrop.classList.add('visible'); }, 10);
    } else {
      document.body.classList.remove('sidebar-open');
      if (sidebar) sidebar.classList.remove('open');
      if (toggle) toggle.setAttribute('aria-expanded', 'false');
      backdrop.classList.remove('visible');
      // match hide after a tiny delay to allow transition
      setTimeout(function(){ try { if (!document.body.classList.contains('sidebar-open')) backdrop.style.display = 'none'; } catch(e){} }, 220);
    }

    // persist if requested
    try {
      if (!opts.skipPersist) localStorage.setItem('admin.sidebar.open', open ? '1' : '0');
    } catch (e) {}

    // ensure toggle placement not obstructed
    try { if (toggle) { toggle.style.pointerEvents = 'auto'; } } catch(e){}
  }

  function toggleSidebar() {
    setOpenState(!isOpen());
  }

  function openSidebar() { setOpenState(true); }
  function closeSidebar() { setOpenState(false); }

  // Click handlers wiring
  function bindEvents() {
    var toggle = findToggle();
    var sidebar = findSidebar();
    var backdrop = ensureBackdrop();

    if (!toggle || !sidebar) {
      warn('SidebarToggle: required elements missing', { toggle: !!toggle, sidebar: !!sidebar });
      return;
    }

    // Ensure required ARIA attributes
    try {
      toggle.setAttribute('aria-controls', sidebar.id || (sidebar.getAttribute('id') || 'adminSidebar'));
      if (!toggle.hasAttribute('aria-expanded')) toggle.setAttribute('aria-expanded', isOpen() ? 'true' : 'false');
      if (!toggle.hasAttribute('aria-label')) toggle.setAttribute('aria-label', 'Toggle sidebar');
    } catch (e) {}

    // Click toggle
    toggle.addEventListener('click', function (ev) {
      ev.preventDefault();
      toggleSidebar();
    });

    // Click backdrop closes
    backdrop.addEventListener('click', function (ev) {
      ev.preventDefault();
      closeSidebar();
    });

    // Close when link clicked on small screens (delegation)
    sidebar.addEventListener('click', function (ev) {
      try {
        var a = ev.target && (ev.target.closest ? ev.target.closest('a') : null);
        if (!a) return;
        // ignore if link is a hash or javascript:void(0) but still close for typical links
        var href = a.getAttribute('href') || '';
        if (window.innerWidth <= 900 && href && href.indexOf('#') !== 0 && href.indexOf('javascript:') !== 0) {
          // small delay so navigation can start, but visually close quickly
          setTimeout(function () { closeSidebar(); }, 120);
        }
      } catch (e) { /* ignore */ }
    });

    // ESC key closes if open
    document.addEventListener('keydown', function (ev) {
      if (ev.key === 'Escape' && isOpen()) closeSidebar();
    });

    // observe dir changes to reposition toggle if other code changes dir
    if (window.MutationObserver) {
      var mo = new MutationObserver(function (mut) {
        mut.forEach(function (m) {
          if (m.type === 'attributes' && m.attributeName === 'dir') {
            // nothing fancy here — header-placement.js will handle visual changes.
            log('SidebarToggle: dir changed -> reapply placement');
            // ensure toggle remains interactive
            try { toggle.style.pointerEvents = 'auto'; } catch(e){}
          }
        });
      });
      mo.observe(document.documentElement, { attributes: true, attributeFilter: ['dir'] });
    }

    // restore persisted state
    try {
      var nv = localStorage.getItem('admin.sidebar.open');
      if (nv === '1') openSidebar(); else closeSidebar();
    } catch (e) {}
  }

  // Initialize safely once DOM ready
  function init() {
    bindEvents();
    // expose api
    SidebarToggle.open = openSidebar;
    SidebarToggle.close = closeSidebar;
    SidebarToggle.toggle = toggleSidebar;
    SidebarToggle.isOpen = isOpen;
    window.SidebarToggle = SidebarToggle;
    log('SidebarToggle initialized');
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();

})();