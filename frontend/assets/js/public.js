/**
 * assets/js/public.js
 * QOOQZ — Global Public Interface JS
 * - Mobile hamburger menu
 * - Dynamic theme injection from API
 * - Lazy loading helpers
 * - Lightweight, no dependencies
 */

(function () {
  'use strict';

  /* -------------------------------------------------------
   * 1. Mobile nav toggle
   * ----------------------------------------------------- */
  function initMobileNav() {
    var toggle = document.getElementById('pubHamburger');
    var drawer = document.getElementById('pubMobileNav');
    var overlay = document.getElementById('pubMobileNav');

    if (!toggle || !drawer) return;

    toggle.addEventListener('click', function () {
      var isOpen = drawer.classList.toggle('open');
      toggle.setAttribute('aria-expanded', String(isOpen));
      document.body.style.overflow = isOpen ? 'hidden' : '';
    });

    // Close on overlay click
    drawer.addEventListener('click', function (e) {
      if (e.target === drawer) {
        drawer.classList.remove('open');
        toggle.setAttribute('aria-expanded', 'false');
        document.body.style.overflow = '';
      }
    });

    // Close on Escape key
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && drawer.classList.contains('open')) {
        drawer.classList.remove('open');
        toggle.setAttribute('aria-expanded', 'false');
        document.body.style.overflow = '';
      }
    });
  }

  /* -------------------------------------------------------
   * 2. Apply dynamic theme colors from data attribute
   * ----------------------------------------------------- */
  function applyTheme() {
    var root = document.documentElement;
    var themeEl = document.getElementById('pubThemeData');
    if (!themeEl) return;

    var raw = themeEl.textContent || themeEl.innerText || '';
    if (!raw.trim()) return;

    var theme;
    try { theme = JSON.parse(raw); } catch (e) { return; }

    var map = {
      primary:           '--pub-primary',
      secondary:         '--pub-secondary',
      accent:            '--pub-accent',
      background:        '--pub-bg',
      surface:           '--pub-surface',
      text:              '--pub-text',
      header_bg:         '--pub-header-bg',
      header_text_color: '--pub-header-text',
      footer_bg:         '--pub-footer-bg',
      footer_text_color: '--pub-footer-text',
    };

    Object.keys(map).forEach(function (key) {
      if (theme[key]) {
        root.style.setProperty(map[key], theme[key]);
      }
    });
  }

  /* -------------------------------------------------------
   * 3. Mark active nav link based on current path
   * ----------------------------------------------------- */
  function markActiveNav() {
    var path = window.location.pathname;
    var links = document.querySelectorAll('.pub-nav a, .pub-mobile-nav-inner a');
    links.forEach(function (a) {
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
      // Fallback: load all immediately
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
   * 5. Simple search form progressive enhancement
   * ----------------------------------------------------- */
  function initSearch() {
    var form = document.getElementById('pubSearchForm');
    if (!form) return;

    var input = form.querySelector('.pub-search-input');
    if (!input) return;

    // Auto-focus on desktop
    if (window.innerWidth >= 768) {
      input.focus();
    }
  }

  /* -------------------------------------------------------
   * 6a. Banner/Slider carousel auto-advance
   * Finds .pub-banner-slider elements rendered by PHP,
   * activates the first slide, and auto-advances.
   * ----------------------------------------------------- */
  function initSliders() {
    var sliders = document.querySelectorAll('.pub-banner-slider');
    sliders.forEach(function (slider) {
      var slides = slider.querySelectorAll('.pub-banner-slide');
      if (slides.length <= 1) return; // nothing to cycle

      var current = 0;
      var isRtl   = document.documentElement.dir === 'rtl';

      /* Activate first slide */
      slides.forEach(function (s) { s.classList.remove('active'); });
      slides[0].classList.add('active');

      /* Inject prev / next buttons */
      var prevBtn = document.createElement('button');
      prevBtn.className = 'pub-slider-btn pub-slider-btn--prev';
      prevBtn.setAttribute('aria-label', 'Previous');
      prevBtn.innerHTML = isRtl ? '&#8250;' : '&#8249;'; // › or ‹
      slider.appendChild(prevBtn);

      var nextBtn = document.createElement('button');
      nextBtn.className = 'pub-slider-btn pub-slider-btn--next';
      nextBtn.setAttribute('aria-label', 'Next');
      nextBtn.innerHTML = isRtl ? '&#8249;' : '&#8250;'; // ‹ or ›
      slider.appendChild(nextBtn);

      /* Inject dot indicators */
      var dotsWrap = document.createElement('div');
      dotsWrap.className = 'pub-slider-dots';
      slides.forEach(function (_, i) {
        var dot = document.createElement('button');
        dot.className = 'pub-slider-dot' + (i === 0 ? ' active' : '');
        dot.setAttribute('aria-label', 'Go to slide ' + (i + 1));
        dot.addEventListener('click', function () { goTo(i); });
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

      prevBtn.addEventListener('click', function () { goTo(current - 1); clearInterval(timer); timer = setInterval(advance, 5000); });
      nextBtn.addEventListener('click', function () { goTo(current + 1); clearInterval(timer); timer = setInterval(advance, 5000); });

      function advance() { goTo(current + 1); }
      var timer = setInterval(advance, 5000);

      /* Pause on hover */
      slider.addEventListener('mouseenter', function () { clearInterval(timer); });
      slider.addEventListener('mouseleave', function () { timer = setInterval(advance, 5000); });
    });
  }

  /* -------------------------------------------------------
   * 6. Animate counters (stats row)
   * ----------------------------------------------------- */
  function animateCounters() {
    var counters = document.querySelectorAll('.pub-stat-value[data-target]');
    if (!counters.length) return;

    var observer = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (!entry.isIntersecting) return;
        var el = entry.target;
        var target = parseInt(el.dataset.target, 10);
        var duration = 800;
        var start = performance.now();
        observer.unobserve(el);

        function step(now) {
          var elapsed = now - start;
          var progress = Math.min(elapsed / duration, 1);
          // easeOut
          var value = Math.floor(progress * target);
          el.textContent = value.toLocaleString();
          if (progress < 1) requestAnimationFrame(step);
          else el.textContent = target.toLocaleString() + '+';
        }
        requestAnimationFrame(step);
      });
    });

    counters.forEach(function (c) { observer.observe(c); });
  }

  /* -------------------------------------------------------
   * 7. Filter select: submit form on change
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
   * 8. Back-to-top button
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
   * 9. Cart badge — read localStorage and update #pubCartCount
   * ----------------------------------------------------- */
  function initCartBadge() {
    var badges = [
      document.getElementById('pubCartCount'),
      document.getElementById('pubCartCountMobile'),
    ];
    var cart = [];
    try { cart = JSON.parse(localStorage.getItem('pub_cart') || '[]'); } catch (e) {}
    if (!Array.isArray(cart)) cart = [];
    var total = cart.reduce(function (s, i) { return s + (Math.max(1, parseInt(i.qty, 10) || 1)); }, 0);
    badges.forEach(function (badge) {
      if (!badge) return;
      badge.textContent = total;
      badge.style.display = total ? 'inline-flex' : 'none';
    });
  }

  /* -------------------------------------------------------
   * 9b. User display: read localStorage.pubUser set by login.js
   *     Falls back to window.pubSessionUser injected by PHP (header.php)
   *     so users who logged in via the admin panel are also recognised.
   * ----------------------------------------------------- */
  function updateUserDisplay() {
    try {
      var u = null;
      var raw = localStorage.getItem('pubUser');
      if (raw) { try { u = JSON.parse(raw); } catch (e) {} }

      // Fallback: PHP session user injected in <head> by header.php
      if (!u || !u.id) {
        u = (typeof window.pubSessionUser !== 'undefined' && window.pubSessionUser) ? window.pubSessionUser : null;
        // Sync to localStorage so future checks (pubAddToCart, job.php) also see it
        if (u && u.id) {
          try { localStorage.setItem('pubUser', JSON.stringify(u)); } catch (e) {}
        }
      }

      if (!u || !u.id) return;

      var displayName = u.name || u.username || 'User';

      // Update header login link(s) that still point to login.php
      var loginLinks = document.querySelectorAll('a.pub-login-btn');
      loginLinks.forEach(function (el) {
        if (el.href && el.href.indexOf('login.php') !== -1) {
          el.textContent = displayName;
          el.href = '/frontend/profile.php';
        }
      });
    } catch (e) {}
  }

  /* -------------------------------------------------------
   * 9c. Notification Bell
   *     Reads from #pubNotifData JSON element (injected by header.php).
   *     Tracks "seen" IDs in localStorage to show unread badge.
   * ----------------------------------------------------- */
  function initNotifBell() {
    var btn      = document.getElementById('pubNotifBtn');
    var dropdown = document.getElementById('pubNotifDropdown');
    var badge    = document.getElementById('pubNotifBadge');
    var list     = document.getElementById('pubNotifList');
    var markAll  = document.getElementById('pubNotifMarkAll');

    if (!btn || !dropdown) return;

    // Parse notification data injected by PHP
    var notifications = [];
    var dataEl = document.getElementById('pubNotifData');
    if (dataEl) {
      try { notifications = JSON.parse(dataEl.textContent || '[]'); } catch (e) {}
    }
    if (!Array.isArray(notifications)) notifications = [];

    // Load seen IDs from localStorage
    var seenKey = 'pub_notif_seen';
    var seenIds = [];
    try { seenIds = JSON.parse(localStorage.getItem(seenKey) || '[]'); } catch (e) {}
    if (!Array.isArray(seenIds)) seenIds = [];

    // Count unread: a notification is unread if server says is_read===false AND not in local seenIds
    var unread = notifications.filter(function (n) {
      return !n.is_read && seenIds.indexOf(String(n.id)) === -1;
    }).length;

    // Update badge
    function updateBadge(count) {
      if (!badge) return;
      badge.textContent = count > 99 ? '99+' : String(count);
      if (count > 0) {
        badge.classList.add('visible');
      } else {
        badge.classList.remove('visible');
      }
    }
    updateBadge(unread);

    // Notification type code → emoji icon
    function typeIcon(code) {
      var icons = {
        order: '📦', payment: '💳', shipment: '🚚', 'return': '↩️',
        review: '⭐', promotion: '🎉', system: '⚙️', entities: '🏢',
        support: '🆘', wallet: '💰', loyalty: '🏅',
        audit_completed: '✅', audit_rejected: '❌',
      };
      return icons[code] || '🔔';
    }

    // Render notifications list
    function renderList() {
      if (!list) return;
      if (!notifications.length) {
        list.innerHTML = '<div class="pub-notif-empty">' +
          (document.documentElement.lang === 'ar' ? 'لا توجد إشعارات' : 'No notifications') +
          '</div>';
        return;
      }
      list.innerHTML = notifications.map(function (n) {
        var isSeen = n.is_read || seenIds.indexOf(String(n.id)) !== -1;
        var icon   = typeIcon(n.type_code || '');
        var time   = n.sent_at ? n.sent_at.replace('T', ' ').substring(0, 16) : '';
        var title  = (n.title || '').replace(/</g, '&lt;').replace(/>/g, '&gt;');
        var msg    = (n.message || '').replace(/</g, '&lt;').replace(/>/g, '&gt;');
        return '<div class="pub-notif-item' + (isSeen ? '' : ' unread') + '" data-id="' + n.id + '">' +
          '<span class="pub-notif-icon">' + icon + '</span>' +
          '<div class="pub-notif-body">' +
            '<p class="pub-notif-title">' + title + '</p>' +
            (msg ? '<p class="pub-notif-msg">' + msg + '</p>' : '') +
            (time ? '<div class="pub-notif-time">' + time + '</div>' : '') +
          '</div>' +
        '</div>';
      }).join('');

      // Click on item → mark as seen locally and persist to server
      list.querySelectorAll('.pub-notif-item').forEach(function (item) {
        item.addEventListener('click', function () {
          var id = String(item.dataset.id);
          if (seenIds.indexOf(id) === -1) {
            seenIds.push(id);
            try { localStorage.setItem(seenKey, JSON.stringify(seenIds)); } catch (e) {}
            item.classList.remove('unread');
            unread = Math.max(0, unread - 1);
            updateBadge(unread);
            // Persist to server (best-effort)
            fetch('/api/public/notifications/mark-read', {
              method: 'POST',
              credentials: 'include',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({ ids: [parseInt(id, 10)] })
            }).catch(function () {});
          }
        });
      });
    }
    renderList();

    // Mark all as seen — also persist to server via API
    if (markAll) {
      markAll.addEventListener('click', function () {
        // Optimistic local update
        seenIds = notifications.map(function (n) { return String(n.id); });
        try { localStorage.setItem(seenKey, JSON.stringify(seenIds)); } catch (e) {}
        unread = 0;
        updateBadge(0);
        renderList();
        // Persist to server (best-effort — do not block UI)
        fetch('/api/public/notifications/mark-all-read', {
          method: 'POST',
          credentials: 'include',
          headers: { 'Content-Type': 'application/json' }
        }).catch(function () {});
      });
    }

    // Toggle dropdown
    btn.addEventListener('click', function (e) {
      e.stopPropagation();
      var isOpen = dropdown.classList.toggle('open');
      btn.setAttribute('aria-expanded', String(isOpen));
    });

    // Close on outside click
    document.addEventListener('click', function (e) {
      if (!dropdown.contains(e.target) && !btn.contains(e.target)) {
        dropdown.classList.remove('open');
        btn.setAttribute('aria-expanded', 'false');
      }
    });

    // Close on Escape
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') {
        dropdown.classList.remove('open');
        btn.setAttribute('aria-expanded', 'false');
      }
    });
  }

  /* -------------------------------------------------------
   * 10. Init all on DOMContentLoaded
   * ----------------------------------------------------- */
  document.addEventListener('DOMContentLoaded', function () {
    applyTheme();
    initMobileNav();
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
  });

})();

/* -------------------------------------------------------
 * Global Add-to-Cart helpers (available on all pages)
 * Used by product detail page and products listing cards.
 * ----------------------------------------------------- */

/**
 * Change quantity in #pubQtyInput by delta (+1 / -1).
 */
function pubQtyChange(delta) {
  var inp = document.getElementById('pubQtyInput');
  if (!inp) return;
  var v = parseInt(inp.value, 10) || 1;
  v = Math.max(1, Math.min(parseInt(inp.max, 10) || 999, v + delta));
  inp.value = v;
}

/**
 * Add a product to the cart.
 * Saves to DB (via /api/public/cart/add) when logged in, always to localStorage as fallback.
 * Accepts a button/span element with data-product-* attributes.
 * Quantity is taken from #pubQtyInput (defaults to 1).
 */
function pubAddToCart(btn) {
  // Require login — check localStorage first, then window.pubSessionUser (PHP session)
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
  var qty      = qtyInput ? (parseInt(qtyInput.value, 10) || 1) : 1;
  var id       = parseInt(btn.dataset.productId, 10);
  var name     = btn.dataset.productName  || '';
  var price    = parseFloat(btn.dataset.productPrice) || 0;
  var img      = btn.dataset.productImage || '';
  var cur      = btn.dataset.currency     || '';
  var sku      = btn.dataset.productSku   || '';
  var eid      = parseInt(btn.dataset.entityId, 10) || 1;

  if (!id) return;

  // ── 1. Always update localStorage immediately (works offline) ────────────
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

  // ── 2. Sync to DB (fire-and-forget — user is logged in) ──────────────────
  var tenantId = (typeof window.PUB_TENANT_ID !== 'undefined') ? window.PUB_TENANT_ID : 1;
  if (typeof fetch !== 'undefined') {
    fetch('/api/public/cart/add?tenant_id=' + tenantId, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
      credentials: 'include',
      body: JSON.stringify({
        product_id: id,
        product_name: name,
        sku: sku,
        unit_price: price,
        qty: qty,
        entity_id: eid
      })
    }).catch(function () {}); // silent fail — localStorage is the fallback
  }

  // ── 3. Update header cart count badges ───────────────────────────────────
  var total = cart.reduce(function (s, i) { return s + (Math.max(1, parseInt(i.qty, 10) || 1)); }, 0);
  [document.getElementById('pubCartCount'), document.getElementById('pubCartCountMobile')]
    .forEach(function (badge) {
      if (!badge) return;
      badge.textContent = total;
      badge.style.display = total ? 'inline-flex' : 'none';
    });

  // ── 4. Visual feedback + navigate to cart ─────────────────────────────────
  var orig = btn.textContent;
  btn.textContent = btn.dataset.addedText || '✅';
  btn.disabled = true;
  setTimeout(function () {
    window.location.href = '/frontend/public/cart.php';
  }, 1200);
}

// ── Wishlist ─────────────────────────────────────────────────────────────────

/** Toggle wishlist state for a product. Called by heart buttons. */
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
  fd.append('entity_id', btn.dataset.entityId || '1');

  fetch('/api/public/wishlist/' + action, {
    method: 'POST',
    credentials: 'include',
    body: fd
  })
  .then(function (r) { return r.json(); })
  .then(function (data) {
    if (data.success || data.ok) {
      if (active) {
        btn.classList.remove('pub-wishlist-active');
        btn.title = 'Add to wishlist';
        btn.textContent = '♡';
      } else {
        btn.classList.add('pub-wishlist-active');
        btn.title = 'In wishlist';
        btn.textContent = '♥';
      }
      // Update badge count
      pubRefreshWishlistBadge();
    }
  })
  .catch(function () {})
  .finally(function () { btn.disabled = false; });
}

/** Refresh wishlist badge in header */
function pubRefreshWishlistBadge() {
  fetch('/api/public/wishlist/ids', { credentials: 'include' })
  .then(function (r) { return r.json(); })
  .then(function (data) {
    var ids = (data.data && data.data.ids) ? data.data.ids : [];
    var count = ids.length;
    // Update badge
    var badge = document.getElementById('pubWishlistCount');
    if (badge) { badge.textContent = count; badge.style.display = count ? 'inline-flex' : 'none'; }
    var badgeMob = document.getElementById('pubWishlistCountMobile');
    if (badgeMob) { badgeMob.textContent = count; badgeMob.style.display = count ? 'inline-flex' : 'none'; }
    // Update heart buttons on page
    document.querySelectorAll('.pub-wishlist-btn').forEach(function (btn) {
      var pid = String(btn.dataset.productId);
      if (ids.map(String).indexOf(pid) !== -1) {
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

// ── Init on page load ─────────────────────────────────────────────────────────
(function () {
  var u = window.pubSessionUser || JSON.parse(localStorage.getItem('pubUser') || 'null');
  if (u && u.id && document.querySelector('.pub-wishlist-btn')) {
    pubRefreshWishlistBadge();
  }
})();