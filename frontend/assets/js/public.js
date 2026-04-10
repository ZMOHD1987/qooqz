/**
 * assets/js/public.js — Production v3.1
 * QOOQZ — Global Public Interface JS
 *
 * ─ Fixes vs v3.0 ──────────────────────────────────────────────
 *   FIX-3  Sidebar double-binding eliminated:
 *          cloneNode() replaces inline-script listener when
 *          button carries data-bound="1" (set by header.php).
 *          public.js then sets data-bound="js" as its own mark.
 *   FIX-4  Desktop collapse state correctly restored on load.
 *   FIX-5  Resize handler prevents stale mobile-open state.
 * ──────────────────────────────────────────────────────────────
 *
 * No external dependencies.
 */

(function () {
  'use strict';

  /* -------------------------------------------------------
   * 1. Sidebar toggle
   *    Desktop : 3-state cycle:
   *              Full (0) → Collapsed/icons-only (1) → Hidden (2)
   *              state persisted in localStorage (pub_sidebar_state)
   *    Mobile  : slide-out overlay (sidebar.open + backdrop.open)
   * ----------------------------------------------------- */
  function initSidebar() {
    var toggle   = document.getElementById('pubHamburger');
    var sidebar  = document.getElementById('pubSidebar');
    var backdrop = document.getElementById('pubSidebarOverlay');
    var closeBtn = document.getElementById('pubSidebarClose');

    if (!toggle || !sidebar) return;

    // ── FIX-3: Remove the inline fallback listener from header.php ──
    // header.php marks the button with data-bound="1".
    // We replace the node with a clean clone so the old addEventListener
    // (captured in the header.php inline <script>) is discarded entirely.
    // Then we add our own listener and mark the button as ours.
    if (toggle.dataset.bound) {
      var clean = toggle.cloneNode(true);
      toggle.parentNode.replaceChild(clean, toggle);
      toggle = clean;
    }
    toggle.dataset.bound = 'js'; // mark as handled by this file

    var STORAGE_KEY = 'pub_sidebar_state'; // 0=full, 1=collapsed, 2=hidden
    var MOBILE_BP   = 768; // must match CSS @media breakpoint

    function isMobile() {
      return window.innerWidth <= MOBILE_BP;
    }

    // ── Desktop: persist 3-state sidebar ──────────────────
    function restoreDesktopState() {
      if (isMobile()) return;
      try {
        var state = localStorage.getItem(STORAGE_KEY);
        // Default to state 2 (hidden) if not set
        if (!state) state = '2';
        
        if (state === '1') {
          document.body.classList.add('pub-sidebar-collapsed');
          document.body.classList.remove('pub-sidebar-hidden');
        } else if (state === '2') {
          document.body.classList.add('pub-sidebar-hidden');
          document.body.classList.remove('pub-sidebar-collapsed');
        } else {
          document.body.classList.remove('pub-sidebar-collapsed');
          document.body.classList.remove('pub-sidebar-hidden');
        }
      } catch (e) {}
    }

    function toggleDesktop() {
      var body = document.body;
      if (body.classList.contains('pub-sidebar-hidden')) {
        // Hidden → Full
        body.classList.remove('pub-sidebar-hidden');
        body.classList.remove('pub-sidebar-collapsed');
        try { localStorage.setItem(STORAGE_KEY, '0'); } catch (e) {}
      } else if (body.classList.contains('pub-sidebar-collapsed')) {
        // Collapsed → Hidden
        body.classList.remove('pub-sidebar-collapsed');
        body.classList.add('pub-sidebar-hidden');
        try { localStorage.setItem(STORAGE_KEY, '2'); } catch (e) {}
      } else {
        // Full → Collapsed
        body.classList.add('pub-sidebar-collapsed');
        try { localStorage.setItem(STORAGE_KEY, '1'); } catch (e) {}
      }
    }

    // ── Mobile: slide-out overlay ──────────────────────────
    function openMobile() {
      sidebar.classList.add('open');
      if (backdrop) backdrop.classList.add('open');
      toggle.setAttribute('aria-expanded', 'true');
      document.body.style.overflow = 'hidden';
    }

    function closeMobile() {
      sidebar.classList.remove('open');
      if (backdrop) backdrop.classList.remove('open');
      toggle.setAttribute('aria-expanded', 'false');
      document.body.style.overflow = '';
    }

    // ── Main toggle click ──────────────────────────────────
    toggle.addEventListener('click', function () {
      if (isMobile()) {
        sidebar.classList.contains('open') ? closeMobile() : openMobile();
      } else {
        toggleDesktop();
      }
    });

    // Close on backdrop click (mobile)
    if (backdrop) {
      backdrop.addEventListener('click', closeMobile);
    }

    // Close button inside sidebar (mobile)
    if (closeBtn) {
      closeBtn.addEventListener('click', closeMobile);
    }

    // Escape key closes mobile sidebar
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && sidebar.classList.contains('open')) {
        closeMobile();
      }
    });

    // ── FIX-5: Resize — clean up stale mobile-open state ──
    window.addEventListener('resize', function () {
      if (!isMobile() && sidebar.classList.contains('open')) {
        closeMobile();
      }
    }, { passive: true });

    // Restore desktop collapsed state
    restoreDesktopState();

    // Highlight active sidebar link by current URL path
    var currentPath = window.location.pathname;
    var links = sidebar.querySelectorAll('.pub-sidebar-link');
    for (var i = 0; i < links.length; i++) {
      if (links[i].getAttribute('href') === currentPath) {
        links[i].classList.add('active');
        break;
      }
    }
  }

  /* -------------------------------------------------------
   * 2. Apply dynamic theme colors from #pubThemeData element
   * ----------------------------------------------------- */
  function applyTheme() {
    var themeEl = document.getElementById('pubThemeData');
    if (!themeEl) return;

    var raw = themeEl.textContent || themeEl.innerText || '';
    if (!raw.trim()) return;

    var theme;
    try { theme = JSON.parse(raw); } catch (e) { return; }

    var root = document.documentElement;
    var map = {
      primary:            '--pub-primary',
      secondary:          '--pub-secondary',
      accent:             '--pub-accent',
      background:         '--pub-bg',
      surface:            '--pub-surface',
      text:               '--pub-text',
      header_bg:          '--pub-header-bg',
      header_text_color:  '--pub-header-text',
      footer_bg:          '--pub-footer-bg',
      footer_text_color:  '--pub-footer-text',
    };

    Object.keys(map).forEach(function (key) {
      if (theme[key]) root.style.setProperty(map[key], theme[key]);
    });
  }

  /* -------------------------------------------------------
   * 3. Mark active nav link based on current path
   * ----------------------------------------------------- */
  function markActiveNav() {
    var path = window.location.pathname;
    document.querySelectorAll('.pub-sidebar-link').forEach(function (a) {
      if (a.getAttribute('href') && path.indexOf(a.getAttribute('href')) !== -1) {
        a.classList.add('active');
      }
    });
  }

  /* -------------------------------------------------------
   * 4. Lazy-load images with data-src attribute
   * ----------------------------------------------------- */
  function lazyLoadImages() {
    if (!('IntersectionObserver' in window)) {
      document.querySelectorAll('img[data-src]').forEach(function (img) {
        img.src = img.dataset.src;
      });
      return;
    }

    var observer = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          var img = entry.target;
          img.src = img.dataset.src;
          img.removeAttribute('data-src');
          observer.unobserve(img);
        }
      });
    }, { rootMargin: '200px' });

    document.querySelectorAll('img[data-src]').forEach(function (img) {
      observer.observe(img);
    });
  }

  /* -------------------------------------------------------
   * 5. Search form — auto-focus on desktop
   * ----------------------------------------------------- */
  function initSearch() {
    var form = document.getElementById('pubSearchForm');
    if (!form) return;
    var input = form.querySelector('.pub-search-input');
    if (!input) return;
    if (window.innerWidth >= 768) input.focus();
  }

  /* -------------------------------------------------------
   * 6. Banner / Slider carousel auto-advance
   * ----------------------------------------------------- */
  function initSliders() {
    var sliders = document.querySelectorAll('.pub-banner-slider');
    sliders.forEach(function (slider) {
      var slides = slider.querySelectorAll('.pub-banner-slide');
      if (slides.length <= 1) return;

      var current = 0;
      var isRtl   = document.documentElement.dir === 'rtl';

      // Activate first slide
      slides.forEach(function (s) { s.classList.remove('active'); });
      slides[0].classList.add('active');

      // Prev button
      var prevBtn = document.createElement('button');
      prevBtn.className = 'pub-slider-btn pub-slider-btn--prev';
      prevBtn.setAttribute('aria-label', 'Previous');
      prevBtn.innerHTML = isRtl ? '&#8250;' : '&#8249;';
      slider.appendChild(prevBtn);

      // Next button
      var nextBtn = document.createElement('button');
      nextBtn.className = 'pub-slider-btn pub-slider-btn--next';
      nextBtn.setAttribute('aria-label', 'Next');
      nextBtn.innerHTML = isRtl ? '&#8249;' : '&#8250;';
      slider.appendChild(nextBtn);

      // Dot indicators
      var dotsWrap = document.createElement('div');
      dotsWrap.className = 'pub-slider-dots';
      slides.forEach(function (_, i) {
        var dot = document.createElement('button');
        dot.className = 'pub-slider-dot' + (i === 0 ? ' active' : '');
        dot.setAttribute('aria-label', 'Go to slide ' + (i + 1));
        dot.addEventListener('click', function () { goTo(i); resetTimer(); });
        dotsWrap.appendChild(dot);
      });
      slider.appendChild(dotsWrap);

      function goTo(idx) {
        slides[current].classList.remove('active');
        dotsWrap.children[current].classList.remove('active');
        current = (idx + slides.length) % slides.length;
        slides[current].classList.add('active');
        dotsWrap.children[current].classList.add('active');
      }

      var timer;
      function resetTimer() {
        clearInterval(timer);
        timer = setInterval(function () { goTo(current + 1); }, 5000);
      }

      prevBtn.addEventListener('click', function () { goTo(current - 1); resetTimer(); });
      nextBtn.addEventListener('click', function () { goTo(current + 1); resetTimer(); });

      resetTimer();

      slider.addEventListener('mouseenter', function () { clearInterval(timer); });
      slider.addEventListener('mouseleave', resetTimer);
    });
  }

  /* -------------------------------------------------------
   * 7. Animate stat counters (.pub-stat-value[data-target])
   * ----------------------------------------------------- */
  function animateCounters() {
    var counters = document.querySelectorAll('.pub-stat-value[data-target]');
    if (!counters.length || !('IntersectionObserver' in window)) return;

    var observer = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (!entry.isIntersecting) return;
        var el     = entry.target;
        var target = parseInt(el.dataset.target, 10);
        var start  = performance.now();
        observer.unobserve(el);

        function step(now) {
          var progress = Math.min((now - start) / 800, 1);
          el.textContent = Math.floor(progress * target).toLocaleString();
          if (progress < 1) {
            requestAnimationFrame(step);
          } else {
            el.textContent = target.toLocaleString() + '+';
          }
        }
        requestAnimationFrame(step);
      });
    });

    counters.forEach(function (c) { observer.observe(c); });
  }

  /* -------------------------------------------------------
   * 8. Filter selects — auto-submit on change
   * ----------------------------------------------------- */
  function initFilterSelects() {
    document.querySelectorAll('.pub-filter-select[data-auto-submit]').forEach(function (sel) {
      sel.addEventListener('change', function () {
        var form = sel.closest('form');
        if (form) form.submit();
      });
    });
  }

  /* -------------------------------------------------------
   * 9. Back-to-top button
   * ----------------------------------------------------- */
  function initBackToTop() {
    var btn = document.getElementById('pubBackToTop');
    if (!btn) return;

    window.addEventListener('scroll', function () {
      btn.style.display = window.scrollY > 400 ? 'flex' : 'none';
    }, { passive: true });

    btn.addEventListener('click', function () {
      window.scrollTo({ top: 0, behavior: 'smooth' });
    });
  }

  /* -------------------------------------------------------
   * 10. Cart badge — update sidebar badge from localStorage
   * ----------------------------------------------------- */
  function initCartBadge() {
    var cart = [];
    try { cart = JSON.parse(localStorage.getItem('pub_cart') || '[]'); } catch (e) {}
    if (!Array.isArray(cart)) cart = [];
    var total = cart.reduce(function (s, i) {
      return s + (Math.max(1, parseInt(i.qty, 10) || 1));
    }, 0);
    
    var badge1 = document.getElementById('pubCartCountSidebar');
    if (badge1) { badge1.textContent = total; badge1.style.display = total ? 'inline-flex' : 'none'; }
    
    var badge2 = document.getElementById('pubCartCountFooter');
    if (badge2) { badge2.textContent = total; badge2.style.display = total ? 'inline-flex' : 'none'; }
  }

  /* -------------------------------------------------------
   * 11. User display — sync localStorage / PHP session user
   * ----------------------------------------------------- */
  function updateUserDisplay() {
    try {
      var u = null;
      var raw = localStorage.getItem('pubUser');
      if (raw) { try { u = JSON.parse(raw); } catch (e) {} }

      // Fallback to PHP session user injected in <head>
      if (!u || !u.id) {
        u = (typeof window.pubSessionUser !== 'undefined' && window.pubSessionUser) ? window.pubSessionUser : null;
        if (u && u.id) {
          try { localStorage.setItem('pubUser', JSON.stringify(u)); } catch (e) {}
        }
      }

      if (!u || !u.id) return;

      var displayName = u.name || u.username || 'User';

      // Update header login links that still point to login.php
      document.querySelectorAll('a.pub-login-btn').forEach(function (el) {
        if (el.href && el.href.indexOf('login.php') !== -1) {
          el.textContent = displayName;
          el.href = '/frontend/profile.php';
        }
      });
    } catch (e) {}
  }

  /* -------------------------------------------------------
   * 12. Notification bell
   * ----------------------------------------------------- */
  function initNotifBell() {
    var btn      = document.getElementById('pubNotifBtn');
    var dropdown = document.getElementById('pubNotifDropdown');
    var badge    = document.getElementById('pubNotifBadge');
    var list     = document.getElementById('pubNotifList');
    var markAll  = document.getElementById('pubNotifMarkAll');

    if (!btn || !dropdown) return;

    var notifications = [];
    var dataEl = document.getElementById('pubNotifData');
    if (dataEl) {
      try { notifications = JSON.parse(dataEl.textContent || '[]'); } catch (e) {}
    }
    if (!Array.isArray(notifications)) notifications = [];

    var seenKey = 'pub_notif_seen';
    var seenIds = [];
    try { seenIds = JSON.parse(localStorage.getItem(seenKey) || '[]'); } catch (e) {}
    if (!Array.isArray(seenIds)) seenIds = [];

    var unread = notifications.filter(function (n) {
      return !n.is_read && seenIds.indexOf(String(n.id)) === -1;
    }).length;

    function updateBadge(count) {
      if (!badge) return;
      badge.textContent = count > 99 ? '99+' : String(count);
      badge.classList.toggle('visible', count > 0);
    }
    updateBadge(unread);

    function typeIcon(code) {
      var icons = {
        order: '📦', payment: '💳', shipment: '🚚', 'return': '↩️',
        review: '⭐', promotion: '🎉', system: '⚙️', entities: '🏢',
        support: '🆘', wallet: '💰', loyalty: '🏅',
        audit_completed: '✅', audit_rejected: '❌',
      };
      return icons[code] || '🔔';
    }

    function renderList() {
      if (!list) return;
      if (!notifications.length) {
        list.innerHTML = '<div class="pub-notif-empty">'
          + (document.documentElement.lang === 'ar' ? 'لا توجد إشعارات' : 'No notifications')
          + '</div>';
        return;
      }
      list.innerHTML = notifications.map(function (n) {
        var isSeen = n.is_read || seenIds.indexOf(String(n.id)) !== -1;
        var icon   = typeIcon(n.type_code || '');
        var time   = n.sent_at ? n.sent_at.replace('T', ' ').substring(0, 16) : '';
        var title  = (n.title   || '').replace(/</g, '&lt;').replace(/>/g, '&gt;');
        var msg    = (n.message || '').replace(/</g, '&lt;').replace(/>/g, '&gt;');
        return '<div class="pub-notif-item' + (isSeen ? '' : ' unread') + '" data-id="' + n.id + '">'
          + '<span class="pub-notif-icon">' + icon + '</span>'
          + '<div class="pub-notif-body">'
          + '<p class="pub-notif-title">' + title + '</p>'
          + (msg  ? '<p class="pub-notif-msg">'  + msg  + '</p>' : '')
          + (time ? '<div class="pub-notif-time">' + time + '</div>' : '')
          + '</div></div>';
      }).join('');

      list.querySelectorAll('.pub-notif-item').forEach(function (item) {
        item.addEventListener('click', function () {
          var id = String(item.dataset.id);
          if (seenIds.indexOf(id) === -1) {
            seenIds.push(id);
            try { localStorage.setItem(seenKey, JSON.stringify(seenIds)); } catch (e) {}
            item.classList.remove('unread');
            unread = Math.max(0, unread - 1);
            updateBadge(unread);
            fetch('/api/public/notifications/mark-read', {
              method: 'POST', credentials: 'include',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({ ids: [parseInt(id, 10)] })
            }).catch(function () {});
          }
        });
      });
    }
    renderList();

    if (markAll) {
      markAll.addEventListener('click', function () {
        seenIds = notifications.map(function (n) { return String(n.id); });
        try { localStorage.setItem(seenKey, JSON.stringify(seenIds)); } catch (e) {}
        unread = 0;
        updateBadge(0);
        renderList();
        fetch('/api/public/notifications/mark-all-read', {
          method: 'POST', credentials: 'include',
          headers: { 'Content-Type': 'application/json' }
        }).catch(function () {});
      });
    }

    btn.addEventListener('click', function (e) {
      e.stopPropagation();
      var isOpen = dropdown.classList.toggle('open');
      btn.setAttribute('aria-expanded', String(isOpen));
    });

    document.addEventListener('click', function (e) {
      if (!dropdown.contains(e.target) && !btn.contains(e.target)) {
        dropdown.classList.remove('open');
        btn.setAttribute('aria-expanded', 'false');
      }
    });

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') {
        dropdown.classList.remove('open');
        btn.setAttribute('aria-expanded', 'false');
      }
    });
  }

  /* -------------------------------------------------------
   * 13. Init all on DOMContentLoaded
   * ----------------------------------------------------- */
  document.addEventListener('DOMContentLoaded', function () {
    applyTheme();
    initSidebar();
    markActiveNav();
    lazyLoadImages();
    initSearch();
    initSliders();
    animateCounters();
    initFilterSelects();
    initBackToTop();
    initCartBadge();
    updateUserDisplay();
    initNotifBell();

    // PWA service worker
    if ('serviceWorker' in navigator) {
      navigator.serviceWorker.register('/frontend/sw.js').catch(function () {});
    }
  });

})();


/* ═══════════════════════════════════════════════════════════════
 * Global cart helpers (available on all pages, no module wrap)
 * ════════════════════════════════════════════════════════════ */

/**
 * Increment / decrement quantity in #pubQtyInput by delta.
 */
function pubQtyChange(delta) {
  var inp = document.getElementById('pubQtyInput');
  if (!inp) return;
  var v = parseInt(inp.value, 10) || 1;
  v = Math.max(1, Math.min(parseInt(inp.max, 10) || 999, v + delta));
  inp.value = v;
}

/**
 * Add a product to cart.
 * Saves to DB when logged in; always writes to localStorage as fallback.
 */
function pubAddToCart(btn) {
  // Require login
  var pubU = null;
  try {
    pubU = JSON.parse(localStorage.getItem('pubUser') || 'null');
    if (!pubU || !pubU.id) {
      if (typeof window.pubSessionUser !== 'undefined' && window.pubSessionUser && window.pubSessionUser.id) {
        pubU = window.pubSessionUser;
        try { localStorage.setItem('pubUser', JSON.stringify(pubU)); } catch (e) {}
      }
    }
    if (!pubU || !pubU.id) {
      window.location.href = '/frontend/login.php?redirect=' + encodeURIComponent(window.location.href);
      return;
    }
  } catch (e) {
    window.location.href = '/frontend/login.php';
    return;
  }

  var qtyInput = document.getElementById('pubQtyInput');
  var qty   = qtyInput ? (parseInt(qtyInput.value, 10) || 1) : 1;
  var id    = parseInt(btn.dataset.productId, 10);
  var name  = btn.dataset.productName  || '';
  var price = parseFloat(btn.dataset.productPrice) || 0;
  var img   = btn.dataset.productImage || '';
  var cur   = btn.dataset.currency     || '';
  var sku   = btn.dataset.productSku   || '';
  var eid   = parseInt(btn.dataset.entityId, 10) || 1;

  if (!id) return;

  // 1. Update localStorage immediately
  var cart = [];
  try { cart = JSON.parse(localStorage.getItem('pub_cart') || '[]'); } catch (e) {}
  if (!Array.isArray(cart)) cart = [];

  var found = false;
  cart.forEach(function (item) {
    if (item.id === id) { item.qty = (item.qty || 1) + qty; found = true; }
  });
  if (!found) {
    cart.push({ id: id, name: name, price: price, qty: qty, image: img, currency: cur, sku: sku });
  }
  try { localStorage.setItem('pub_cart', JSON.stringify(cart)); } catch (e) {}

  // Track event
  if (typeof window.pubTrackEvent === 'function') {
    window.pubTrackEvent('product', id, 'add_to_cart', price || null);
  }

  // 2. Sync to DB (fire-and-forget)
  var tenantId = (typeof window.PUB_TENANT_ID !== 'undefined') ? window.PUB_TENANT_ID : 1;
  if (typeof fetch !== 'undefined') {
    fetch('/api/public/cart/add?tenant_id=' + tenantId, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
      credentials: 'include',
      body: JSON.stringify({ product_id: id, product_name: name, sku: sku, unit_price: price, qty: qty, entity_id: eid })
    }).catch(function () {});
  }

  // 3. Update all cart badges
  var total = cart.reduce(function (s, i) { return s + (Math.max(1, parseInt(i.qty, 10) || 1)); }, 0);
  var badge1 = document.getElementById('pubCartCountSidebar');
  if (badge1) { badge1.textContent = total; badge1.style.display = total ? 'inline-flex' : 'none'; }
  var badge2 = document.getElementById('pubCartCountFooter');
  if (badge2) { badge2.textContent = total; badge2.style.display = total ? 'inline-flex' : 'none'; }

  // 4. Visual feedback then navigate
  var orig = btn.textContent;
  btn.textContent = btn.dataset.addedText || '✅';
  btn.disabled = true;
  setTimeout(function () {
    window.location.href = '/frontend/public/cart.php';
  }, 1200);
}


/* ═══════════════════════════════════════════════════════════════
 * Wishlist helpers
 * ════════════════════════════════════════════════════════════ */

function pubToggleWishlist(btn) {
  var u = window.pubSessionUser || JSON.parse(localStorage.getItem('pubUser') || 'null');
  if (!u || !u.id) {
    window.location.href = '/frontend/login.php?redirect=' + encodeURIComponent(window.location.href);
    return;
  }
  var productId = btn.dataset.productId;
  if (!productId) return;

  var active = btn.classList.contains('pub-wishlist-active');
  var action = active ? 'remove' : 'add';
  btn.disabled = true;

  var fd = new FormData();
  fd.append('product_id', productId);
  fd.append('entity_id',  btn.dataset.entityId || '1');

  fetch('/api/public/wishlist/' + action, { method: 'POST', credentials: 'include', body: fd })
    .then(function (r) { return r.json(); })
    .then(function (data) {
      if (data.success || data.ok) {
        if (active) {
          btn.classList.remove('pub-wishlist-active');
          btn.title       = 'Add to wishlist';
          btn.textContent = '♡';
        } else {
          btn.classList.add('pub-wishlist-active');
          btn.title       = 'In wishlist';
          btn.textContent = '♥';
          if (typeof window.pubTrackEvent === 'function') {
            window.pubTrackEvent('product', parseInt(productId, 10), 'favorite');
          }
        }
        pubRefreshWishlistBadge();
      }
    })
    .catch(function () {})
    .finally(function () { btn.disabled = false; });
}

function pubRefreshWishlistBadge() {
  fetch('/api/public/wishlist/ids', { credentials: 'include' })
    .then(function (r) { return r.json(); })
    .then(function (data) {
      var ids   = (data.data && data.data.ids) ? data.data.ids : [];
      var count = ids.length;

      var b1 = document.getElementById('pubWishlistCount');
      var b2 = document.getElementById('pubWishlistCountSidebar');
      if (b1) { b1.textContent = count; b1.style.display = count ? 'inline-flex' : 'none'; }
      if (b2) { b2.textContent = count; b2.style.display = count ? 'inline-flex' : 'none'; }

      document.querySelectorAll('.pub-wishlist-btn').forEach(function (btn) {
        if (ids.map(String).indexOf(String(btn.dataset.productId)) !== -1) {
          btn.classList.add('pub-wishlist-active');
          btn.textContent = '♥';
          btn.title = 'In wishlist';
        } else {
          btn.classList.remove('pub-wishlist-active');
          btn.textContent = '♡';
          btn.title = 'Add to wishlist';
        }
      });
    })
    .catch(function () {});
}

// Auto-refresh wishlist badge on page load when user is logged in
(function () {
  var u = window.pubSessionUser || JSON.parse(localStorage.getItem('pubUser') || 'null');
  if (u && u.id && document.querySelector('.pub-wishlist-btn')) {
    pubRefreshWishlistBadge();
  }
}());