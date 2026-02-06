/*!
 * admin/assets/js/sidebar-toggle.js
 * Sidebar toggle - ONLY works on mobile
 */
(function () {
  'use strict';

  if (window.SidebarToggle && window.SidebarToggle.__installed) return;

  var SidebarToggle = { __installed: true };

  // ════════════════════════════════════════════════════════════
  // Helpers
  // ════════════════════════════════════════════════════════════
  
  function log() {
    if (console && console.log) console.log.apply(console, arguments);
  }

  function isMobile() {
    return window.innerWidth <= 768;
  }

  function findToggle() {
    return document.getElementById('sidebarToggle');
  }

  function findSidebar() {
    return document.getElementById('adminSidebar') ||
           document.querySelector('.admin-sidebar');
  }

  function findBackdrop() {
    return document.querySelector('.sidebar-backdrop');
  }

  // ════════════════════════════════════════════════════════════
  // State - ONLY for mobile
  // ════════════════════════════════════════════════════════════
  
  function isOpen() {
    return document.body.classList.contains('sidebar-open');
  }

  function openSidebar() {
    // CRITICAL: Only work on mobile
    if (!isMobile()) {
      log('⚠ Sidebar toggle ignored on desktop');
      return;
    }

    document.body.classList.add('sidebar-open');
    
    var toggle = findToggle();
    if (toggle) toggle.setAttribute('aria-expanded', 'true');
    
    log('✓ Sidebar opened (mobile)');
  }

  function closeSidebar() {
    // Works on mobile only, but safe to call anytime
    if (!isMobile() && !isOpen()) return;

    document.body.classList.remove('sidebar-open');
    
    var toggle = findToggle();
    if (toggle) toggle.setAttribute('aria-expanded', 'false');
    
    log('✓ Sidebar closed (mobile)');
  }

  function toggleSidebar() {
    if (!isMobile()) {
      log('⚠ Sidebar toggle disabled on desktop');
      return;
    }

    if (isOpen()) {
      closeSidebar();
    } else {
      openSidebar();
    }
  }

  // ════════════════════════════════════════════════════════════
  // Events
  // ════════════════════════════════════════════════════════════
  
  function bindEvents() {
    var toggle = findToggle();
    var sidebar = findSidebar();
    var backdrop = findBackdrop();

    if (!sidebar) {
      console.warn('⚠ Sidebar not found');
      return;
    }

    // Toggle button (only visible on mobile via CSS)
    if (toggle) {
      toggle.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        toggleSidebar();
      });
    }

    // Backdrop click (mobile only)
    if (backdrop) {
      backdrop.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        if (isMobile()) closeSidebar();
      });
    }

    // ESC key (mobile only)
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && isMobile() && isOpen()) {
        closeSidebar();
      }
    });

    // Auto-close on link click (mobile only)
    sidebar.addEventListener('click', function (e) {
      if (!isMobile()) return;

      var link = e.target.closest('a');
      if (!link) return;
      
      var href = link.getAttribute('href') || '';
      if (href === '#' || href.indexOf('javascript:') === 0) return;
      
      setTimeout(function() {
        closeSidebar();
        log('✓ Sidebar auto-closed after navigation');
      }, 150);
    });

    // Handle window resize
    var resizeTimer;
    window.addEventListener('resize', function() {
      clearTimeout(resizeTimer);
      resizeTimer = setTimeout(function() {
        // Close sidebar if switched from mobile to desktop
        if (!isMobile() && isOpen()) {
          document.body.classList.remove('sidebar-open');
          log('✓ Sidebar state cleared (switched to desktop)');
        }
      }, 200);
    });

    log('✓ SidebarToggle events bound');
  }

  // ════════════════════════════════════════════════════════════
  // Init
  // ════════════════════════════════════════════════════════════
  
  function init() {
    // Ensure sidebar is closed on mobile
    if (isMobile()) {
      closeSidebar();
    } else {
      // Ensure no sidebar-open class on desktop
      document.body.classList.remove('sidebar-open');
    }

    bindEvents();

    // Expose API
    SidebarToggle.open = openSidebar;
    SidebarToggle.close = closeSidebar;
    SidebarToggle.toggle = toggleSidebar;
    SidebarToggle.isOpen = isOpen;
    SidebarToggle.isMobile = isMobile;
    
    window.SidebarToggle = SidebarToggle;
    
    log('✓ SidebarToggle initialized (mobile-only mode)');
  }

  // Run
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

})();