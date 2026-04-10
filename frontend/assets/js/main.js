// assets/js/main.js
// خفيف للتعامل مع سلايدر البانر والتحكم البسيط بالقائمة على الشاشات الصغيرة

(function () {
  // Hero slider
  const slider = document.getElementById('heroSlider');
  if (!slider) return;

  const slides = Array.from(slider.querySelectorAll('.hero-slide'));
  let current = slides.findIndex(s => s.classList.contains('active'));
  if (current < 0) current = 0;

  let interval = null;
  const playPauseBtn = document.getElementById('playPause');
  const prevBtn = document.getElementById('prevHero');
  const nextBtn = document.getElementById('nextHero');

  function showSlide(idx) {
    slides.forEach((s,i) => s.classList.toggle('active', i === idx));
    current = idx;
  }
  function next() { showSlide((current + 1) % slides.length); }
  function prev() { showSlide((current - 1 + slides.length) % slides.length); }

  function startAuto() {
    if (interval) return;
    interval = setInterval(next, 4500);
    if (playPauseBtn) playPauseBtn.textContent = 'إيقاف';
  }
  function stopAuto() {
    if (!interval) return;
    clearInterval(interval); interval = null;
    if (playPauseBtn) playPauseBtn.textContent = 'تشغيل';
  }

  if (nextBtn) nextBtn.addEventListener('click', () => { next(); stopAuto(); });
  if (prevBtn) prevBtn.addEventListener('click', () => { prev(); stopAuto(); });
  if (playPauseBtn) {
    playPauseBtn.addEventListener('click', () => {
      if (interval) stopAuto(); else startAuto();
    });
  }

  // start auto if more than one
  if (slides.length > 1) startAuto();

  // Simple accessibility: keyboard arrows
  document.addEventListener('keydown', function (e) {
    if (e.key === 'ArrowLeft') prev();
    if (e.key === 'ArrowRight') next();
  });

})();