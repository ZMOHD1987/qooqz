/**
 * frontend/assets/js/homepage-engine.js
 * QOOQZ — Dynamic Homepage Section Renderer Engine
 *
 * Architecture:
 *   1. Fetches homepage_sections from DB via /api/public/homepage_sections
 *   2. Each section carries its own data_source (API endpoint)
 *   3. SectionRegistry maps section_type → renderer function
 *   4. Admin adds a new section in DB → it renders automatically (no code change needed)
 *
 * Usage (called by index.php after footer):
 *   PubHomepageEngine.init(tenantId, lang);
 */
(function (global) {
  'use strict';

  /* -------------------------------------------------------
   * XSS-safe HTML escape
   * ----------------------------------------------------- */
  function esc(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  /* -------------------------------------------------------
   * Resolve image URL (mirrors pub_img() PHP function)
   * ----------------------------------------------------- */
  function imgUrl(path) {
    if (!path) return '';
    if (/^https?:\/\/|^\/\//.test(path)) return path;
    if (path.charAt(0) === '/') return path;
    if (/^(uploads\/|admin\/uploads\/)/.test(path)) return '/' + path;
    return '/uploads/images/' + path;
  }

  /* -------------------------------------------------------
   * Build a complete <section class="pub-section"> wrapper
   * Returns { el, inner } where inner is the .pub-container div.
   * ----------------------------------------------------- */
  function createSection(sec) {
    var el = document.createElement('section');
    el.className = 'pub-section pub-section--js';
    el.dataset.sectionId   = sec.id   || '';
    el.dataset.sectionType = sec.section_type || '';

    var style = '';
    if (sec.background_color) style += 'background:' + esc(sec.background_color) + ';';
    if (sec.text_color)       style += 'color:'       + esc(sec.text_color)       + ';';
    if (sec.padding)          style += 'padding:'     + esc(sec.padding)          + ';';
    if (style) el.setAttribute('style', style);

    var inner = document.createElement('div');
    inner.className = 'pub-container';

    if (sec.title) {
      var head = document.createElement('div');
      head.className = 'pub-section-head';

      var h2 = document.createElement('h2');
      h2.className = 'pub-section-title';
      h2.textContent = sec.title;
      head.appendChild(h2);

      if (sec.subtitle) {
        var sub = document.createElement('p');
        sub.className = 'pub-section-sub';
        sub.textContent = sec.subtitle;
        head.appendChild(sub);
      }
      inner.appendChild(head);
    }

    el.appendChild(inner);
    return { el: el, inner: inner };
  }

  /* -------------------------------------------------------
   * Append rendered section element to the container
   * ----------------------------------------------------- */
  function appendSection(el) {
    var container = document.getElementById('pub-homepage-sections');
    if (container) container.appendChild(el);
  }

  /* -------------------------------------------------------
   * Section Renderers
   * Each receives (sec, items) and renders into the page.
   * ----------------------------------------------------- */

  function renderBanners(sec, items) {
    var wrap = createSection(sec);
    var slider = document.createElement('div');
    slider.className = 'pub-banner-slider';

    items.forEach(function (b) {
      var slide = document.createElement('div');
      slide.className = 'pub-banner-slide';
      if (b.background_color) slide.style.background = b.background_color;

      var html = '';
      if (b.image_url) {
        html += '<a href="' + esc(b.link_url || '#') + '">'
             +  '<img src="' + esc(imgUrl(b.image_url)) + '" alt="' + esc(b.title || '') + '" class="pub-banner-img" loading="lazy">'
             +  '</a>';
      }
      if (b.title) {
        html += '<div class="pub-banner-caption"><h3>' + esc(b.title) + '</h3>';
        if (b.subtitle) html += '<p>' + esc(b.subtitle) + '</p>';
        if (b.link_url && b.link_text) {
          html += '<a href="' + esc(b.link_url) + '" class="pub-btn pub-btn--primary pub-btn--sm">' + esc(b.link_text) + '</a>';
        }
        html += '</div>';
      }
      slide.innerHTML = html;
      slider.appendChild(slide);
    });

    wrap.inner.appendChild(slider);
    appendSection(wrap.el);
  }

  function renderCategories(sec, items) {
    var wrap = createSection(sec);
    var grid = document.createElement('div');
    grid.className = 'pub-grid-cat';

    items.forEach(function (cat) {
      var a = document.createElement('a');
      a.href = '/frontend/public/products.php?category_id=' + (parseInt(cat.id) || 0);
      a.className = 'pub-cat-card' + (cat.is_featured ? ' pub-cat-card--featured' : '');
      a.style.textDecoration = 'none';

      var imgHtml = cat.image_url
        ? '<img src="' + esc(imgUrl(cat.image_url)) + '" alt="' + esc(cat.name || '') + '" class="pub-cat-img" loading="lazy" onerror="this.style.display=\'none\'">'
        : '<span class="pub-img-placeholder" aria-hidden="true">📂</span>';

      a.innerHTML = '<div class="pub-cat-img-wrap">' + imgHtml + '</div>'
        + '<div class="pub-cat-body">'
        + '<h3 class="pub-cat-name">' + esc(cat.name || cat.slug || '') + '</h3>'
        + (cat.product_count ? '<span class="pub-cat-count">' + parseInt(cat.product_count) + '</span>' : '')
        + '</div>';
      grid.appendChild(a);
    });

    wrap.inner.appendChild(grid);
    appendSection(wrap.el);
  }

  function renderProducts(sec, items) {
    var wrap = createSection(sec);
    var grid = document.createElement('div');
    grid.className = 'pub-grid';

    items.forEach(function (p) {
      var a = document.createElement('a');
      a.href = '/frontend/public/product.php?id=' + (parseInt(p.id) || 0);
      a.className = 'pub-product-card';
      a.style.textDecoration = 'none';

      var imgHtml = p.image_url
        ? '<img src="' + esc(imgUrl(p.image_url)) + '" alt="' + esc(p.name || '') + '" class="pub-cat-img" loading="lazy" onerror="this.style.display=\'none\'">'
        : '<span class="pub-img-placeholder" aria-hidden="true">🖼️</span>';

      var price = p.price
        ? '<p class="pub-product-price">' + parseFloat(p.price).toFixed(2) + ' ' + esc(p.currency_code || '') + '</p>'
        : '';

      a.innerHTML = '<div class="pub-cat-img-wrap" style="aspect-ratio:1">' + imgHtml + '</div>'
        + '<div class="pub-product-card-body">'
        + (p.is_featured ? '<span class="pub-product-badge">★</span>' : '')
        + '<p class="pub-product-name">' + esc(p.name || p.slug || '') + '</p>'
        + price
        + '</div>';
      grid.appendChild(a);
    });

    wrap.inner.appendChild(grid);
    appendSection(wrap.el);
  }

  function renderDeals(sec, items) {
    var wrap = createSection(sec);
    var grid = document.createElement('div');
    grid.className = 'pub-grid-lg';

    items.forEach(function (deal) {
      var card = document.createElement('div');
      card.className = 'pub-deal-card';
      card.innerHTML = (deal.code ? '<span class="pub-deal-badge">' + esc(deal.code) + '</span>' : '')
        + '<p class="pub-deal-title">' + esc(deal.title || deal.code || '') + '</p>'
        + (deal.description ? '<p class="pub-deal-desc">' + esc(deal.description) + '</p>' : '')
        + (deal.ends_at ? '<p class="pub-deal-expires">⏰ ' + esc(String(deal.ends_at).substring(0, 10)) + '</p>' : '');
      grid.appendChild(card);
    });

    wrap.inner.appendChild(grid);
    appendSection(wrap.el);
  }

  function renderBrands(sec, items) {
    var wrap = createSection(sec);
    var grid = document.createElement('div');
    grid.className = 'pub-grid-md';

    items.forEach(function (b) {
      var a = document.createElement('a');
      if (b.website_url) {
        a.href   = b.website_url;
        a.target = '_blank';
        a.rel    = 'noopener noreferrer';
      } else {
        a.href = '/frontend/public/products.php?brand_id=' + (parseInt(b.id) || 0);
      }
      a.className = 'pub-brand-card';
      a.style.textDecoration = 'none';

      var imgHtml = b.logo_url
        ? '<img src="' + esc(imgUrl(b.logo_url)) + '" alt="' + esc(b.name || b.slug || '') + '" class="pub-brand-logo" loading="lazy" onerror="this.style.display=\'none\'">'
        : '<span class="pub-img-placeholder" aria-hidden="true">🏷️</span>';

      a.innerHTML = '<div class="pub-brand-logo-wrap">' + imgHtml + '</div>'
        + '<p class="pub-brand-name">' + esc(b.name || b.slug || '') + '</p>';
      grid.appendChild(a);
    });

    wrap.inner.appendChild(grid);
    appendSection(wrap.el);
  }

  function renderEntities(sec, items) {
    var wrap = createSection(sec);
    var grid = document.createElement('div');
    grid.className = 'pub-grid-md';

    items.forEach(function (ent) {
      var a = document.createElement('a');
      a.href = '/frontend/public/entity.php?id=' + (parseInt(ent.id) || 0);
      a.className = 'pub-entity-card';
      a.style.textDecoration = 'none';

      var logoHtml = ent.logo_url
        ? '<img src="' + esc(imgUrl(ent.logo_url)) + '" alt="' + esc(ent.store_name || ent.name || '') + '" loading="lazy" onerror="this.style.display=\'none\'">'
        : '🏢';

      a.innerHTML = '<div class="pub-entity-avatar">' + logoHtml + '</div>'
        + '<div class="pub-entity-info">'
        + '<p class="pub-entity-name">' + esc(ent.store_name || ent.name || '') + '</p>'
        + (ent.vendor_type ? '<p class="pub-entity-desc">' + esc(ent.vendor_type) + '</p>' : '')
        + (ent.is_verified ? '<span class="pub-entity-verified">✅</span>' : '')
        + '</div>';
      grid.appendChild(a);
    });

    wrap.inner.appendChild(grid);
    appendSection(wrap.el);
  }

  /* -------------------------------------------------------
   * Section Registry — maps section_type → renderer function
   * Add new section types here to extend automatically.
   * ----------------------------------------------------- */
  var SectionRegistry = {
    'slider':            renderBanners,
    'banners':           renderBanners,
    'categories':        renderCategories,
    'featured_products': renderProducts,
    'new_products':      renderProducts,
    'deals':             renderDeals,
    'brands':            renderBrands,
    'vendors':           renderEntities,
  };

  /* -------------------------------------------------------
   * Default data_source per section_type
   * Used when the DB section has no data_source set.
   * ----------------------------------------------------- */
  var DefaultSources = {
    'slider':            '/banners',
    'banners':           '/banners',
    'categories':        '/categories?featured=1',
    'featured_products': '/products?is_featured=1',
    'new_products':      '/products?is_new=1',
    'deals':             '/discounts',
    'brands':            '/brands?is_featured=1',
    'vendors':           '/entities',
  };

  /* -------------------------------------------------------
   * renderSection — fetch section data then call renderer
   * ----------------------------------------------------- */
  function renderSection(sec, tenantId, lang) {
    var type     = sec.section_type || '';
    var renderer = SectionRegistry[type];
    if (!renderer) return Promise.resolve();

    var src = (sec.data_source && sec.data_source !== 'null') ? sec.data_source : (DefaultSources[type] || '');
    if (!src) return Promise.resolve();

    var perRow = Math.max(1, parseInt(sec.items_per_row) || 4);
    var per    = perRow * 2;

    // data_source may start with / and may already have query params
    var sep = src.indexOf('?') !== -1 ? '&' : '?';
    var url = '/api/public/' + src.replace(/^\//, '') + sep
            + 'lang=' + encodeURIComponent(lang)
            + '&tenant_id=' + encodeURIComponent(tenantId)
            + '&per=' + per;

    return fetch(url)
      .then(function (r) { return r.json(); })
      .then(function (json) {
        // ResponseFormatter wraps payload: {success, message, data: {ok, data: [...], meta: {...}}}
        var data = (json.data && Array.isArray(json.data.data)) ? json.data.data
                 : (Array.isArray(json.data) ? json.data : []);
        if (data.length === 0) return;
        renderer(sec, data);
      })
      .catch(function () { /* silently fail — PHP fallback stays visible */ });
  }

  /* -------------------------------------------------------
   * loadSections — fetch all active sections and render
   * Only renders from DB when PHP has NOT already rendered sections
   * (PHP is the primary renderer; JS adds enhancement only when needed)
   * ----------------------------------------------------- */
  function loadSections(tenantId, lang) {
    var container = document.getElementById('pub-homepage-sections');
    if (!container) return;

    // If PHP already rendered section content, skip JS replacement entirely.
    // PHP sections have class "pub-section".
    if (container.querySelector('.pub-section')) {
      // PHP content is present — just enhance it (sliders etc.)
      return;
    }

    // No PHP content: fetch and render from API as fallback
    var url = '/api/public/homepage_sections'
            + '?tenant_id=' + encodeURIComponent(tenantId)
            + '&lang='      + encodeURIComponent(lang);

    fetch(url)
      .then(function (r) { return r.json(); })
      .then(function (json) {
        var sections = (json.data && Array.isArray(json.data.data)) ? json.data.data
                     : (Array.isArray(json.data) ? json.data : []);

        if (sections.length === 0) return; // keep PHP fallback

        // Only clear empty container — never clear PHP-rendered content
        if (container.querySelector('.pub-section')) return;
        container.innerHTML = '';

        // Render sections sequentially to preserve sort_order
        var chain = Promise.resolve();
        sections.forEach(function (sec) {
          chain = chain.then(function () {
            return renderSection(sec, tenantId, lang);
          });
        });
      })
      .catch(function () { /* silently fail — PHP fallback stays visible */ });
  }

  /* -------------------------------------------------------
   * Public API
   * ----------------------------------------------------- */
  var PubHomepageEngine = {
    /**
     * Initialize the engine.
     * Call this after the DOM is loaded (index.php does this inline after footer).
     *
     * @param {number} tenantId - Current tenant ID
     * @param {string} lang     - Active language code (e.g. 'en', 'ar')
     */
    init: function (tenantId, lang) {
      tenantId = parseInt(tenantId) || 1;
      lang     = String(lang || 'en');

      if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
          loadSections(tenantId, lang);
        });
      } else {
        loadSections(tenantId, lang);
      }
    },

    /* Expose registry so external code can register custom section types */
    SectionRegistry: SectionRegistry,
    DefaultSources:  DefaultSources,
  };

  global.PubHomepageEngine = PubHomepageEngine;

}(typeof window !== 'undefined' ? window : this));