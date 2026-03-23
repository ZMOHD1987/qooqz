/**
 * assets/js/slider.js
 * QOOQZ — Standalone Slider Engine
 *
 * Handles:
 *   1. Hero slider (hero-slider) from slider.php
 *   2. Banner slider (pub-banner-slider) from public/index.php
 *   Both use auto-advance, dot navigation, arrow buttons, and touch/swipe.
 */

(function () {
  'use strict';

  /* -------------------------------------------------------
   * 1. Hero Slider (slider.php — .hero-slider)
   * ----------------------------------------------------- */
  function initHeroSlider() {
    var slider = document.getElementById('heroSlider');
    if (!slider) return;

    var slides  = slider.querySelectorAll('.hero-slider__slide');
    var dots    = slider.querySelectorAll('.hero-slider__dot');
    var prevBtn = slider.querySelector('.hero-slider__arrow--prev');
    var nextBtn = slider.querySelector('.hero-slider__arrow--next');
    var current = 0;
    var total   = slides.length;
    var timer   = null;
    var INTERVAL = 5000;

    if (total <= 1) return;

    function show(idx) {
      idx = ((idx % total) + total) % total;
      for (var i = 0; i < total; i++) {
        slides[i].classList.toggle('active', i === idx);
        slides[i].setAttribute('aria-hidden', i === idx ? 'false' : 'true');
        if (dots[i]) dots[i].classList.toggle('active', i === idx);
      }
      current = idx;
    }

    function next() { show(current + 1); }
    function prev() { show(current - 1); }

    function startAutoplay() {
      stopAutoplay();
      timer = setInterval(next, INTERVAL);
    }
    function stopAutoplay() {
      if (timer) { clearInterval(timer); timer = null; }
    }

    // Arrow buttons
    if (nextBtn) nextBtn.addEventListener('click', function () { stopAutoplay(); next(); startAutoplay(); });
    if (prevBtn) prevBtn.addEventListener('click', function () { stopAutoplay(); prev(); startAutoplay(); });

    // Dot buttons
    dots.forEach(function (dot, idx) {
      dot.addEventListener('click', function () { stopAutoplay(); show(idx); startAutoplay(); });
    });

    // Touch/swipe support
    var touchStartX = 0;
    slider.addEventListener('touchstart', function (e) {
      touchStartX = e.touches[0].clientX;
      stopAutoplay();
    }, { passive: true });

    slider.addEventListener('touchend', function (e) {
      var diff = touchStartX - e.changedTouches[0].clientX;
      var isRtl = document.documentElement.dir === 'rtl';
      if (Math.abs(diff) > 40) {
        if (isRtl) {
          diff > 0 ? prev() : next();
        } else {
          diff > 0 ? next() : prev();
        }
      }
      startAutoplay();
    }, { passive: true });

    // Keyboard navigation
    slider.addEventListener('keydown', function (e) {
      if (e.key === 'ArrowLeft' || e.key === 'ArrowRight') {
        stopAutoplay();
        var isRtl = document.documentElement.dir === 'rtl';
        if (e.key === 'ArrowRight') { isRtl ? prev() : next(); }
        if (e.key === 'ArrowLeft')  { isRtl ? next() : prev(); }
        startAutoplay();
      }
    });

    // Pause on hover
    slider.addEventListener('mouseenter', stopAutoplay);
    slider.addEventListener('mouseleave', startAutoplay);

    startAutoplay();
  }

  /* -------------------------------------------------------
   * 2. Banner Slider (public/index.php — .pub-banner-slider)
   * ----------------------------------------------------- */
  function initBannerSliders() {
    var sliders = document.querySelectorAll('.pub-banner-slider');
    sliders.forEach(function (wrap) {
      var slides = wrap.querySelectorAll('.pub-banner-slide');
      if (slides.length <= 1) return;

      var total   = slides.length;
      var current = 0;
      var timer   = null;

      function show(idx) {
        idx = ((idx % total) + total) % total;
        slides.forEach(function (s, i) {
          s.classList.toggle('active', i === idx);
        });
        current = idx;
      }

      function next() { show(current + 1); }
      function prev() { show(current - 1); }

      function startAuto() { stopAuto(); timer = setInterval(next, 4500); }
      function stopAuto()  { if (timer) { clearInterval(timer); timer = null; } }

      // Auto-create navigation if not present
      if (!wrap.querySelector('.pub-slider-btn')) {
        var prevBtn = document.createElement('button');
        prevBtn.className = 'pub-slider-btn pub-slider-prev';
        prevBtn.innerHTML = '‹';
        prevBtn.setAttribute('aria-label', 'Previous');
        prevBtn.addEventListener('click', function () { stopAuto(); prev(); startAuto(); });

        var nextBtn = document.createElement('button');
        nextBtn.className = 'pub-slider-btn pub-slider-next';
        nextBtn.innerHTML = '›';
        nextBtn.setAttribute('aria-label', 'Next');
        nextBtn.addEventListener('click', function () { stopAuto(); next(); startAuto(); });

        wrap.appendChild(prevBtn);
        wrap.appendChild(nextBtn);
      }

      // Touch/swipe
      var sx = 0;
      wrap.addEventListener('touchstart', function (e) { sx = e.touches[0].clientX; stopAuto(); }, { passive: true });
      wrap.addEventListener('touchend', function (e) {
        var d = sx - e.changedTouches[0].clientX;
        var rtl = document.documentElement.dir === 'rtl';
        if (Math.abs(d) > 40) { rtl ? (d > 0 ? prev() : next()) : (d > 0 ? next() : prev()); }
        startAuto();
      }, { passive: true });

      wrap.addEventListener('mouseenter', stopAuto);
      wrap.addEventListener('mouseleave', startAuto);

      startAuto();
    });
  }

  /* -------------------------------------------------------
   * 3. Initialize all sliders on DOM ready
   * ----------------------------------------------------- */
  function init() {
    initHeroSlider();
    initBannerSliders();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

  // Export for external use
  window.QSlider = {
    initHeroSlider: initHeroSlider,
    initBannerSliders: initBannerSliders,
    init: init
  };
})();
