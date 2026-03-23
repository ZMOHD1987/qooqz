<?php
/**
 * frontend/partials/slider.php
 * Homepage Slider — DB-driven colors, flexible, RTL-ready
 *
 * Expected variables:
 *   $banners  – array of banner rows (from API / DB)
 *   $lang     – current language code (optional, defaults to 'ar')
 *
 * Each banner may contain:
 *   image_url, mobile_image_url, title, subtitle,
 *   link_url, link_text, background_color, text_color, button_style
 */
$banners = $banners ?? [];
if (empty($banners)) return;
$_dir = $GLOBALS['PUB_CONTEXT']['dir'] ?? (isset($direction) ? $direction : 'rtl');
if (!function_exists('_se')) {
    function _se($v) { return htmlspecialchars((string)$v, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }
}
if (!function_exists('_safe_color')) {
    /** Validate and sanitize a CSS color value. Returns empty string if invalid. */
    function _safe_color($v) {
        $v = trim((string)$v);
        if ($v === '') return '';
        // Allow hex (#fff, #ffffff, #ffffffff), rgb/rgba, hsl/hsla, named colors
        if (preg_match('/^#([0-9a-fA-F]{3,8})$/', $v)) return $v;
        if (preg_match('/^(rgb|rgba|hsl|hsla)\(\s*[\d\s%,.\/ ]+\)$/i', $v)) return $v;
        if (preg_match('/^[a-zA-Z]{2,30}$/', $v)) return $v; // named colors
        if (preg_match('/^var\(--[a-zA-Z0-9_-]+\)$/', $v)) return $v; // CSS variables
        return '';
    }
}
$sliderCount = count($banners);
?>

<section class="hero-slider" id="heroSlider" dir="<?= _se($_dir) ?>">
    <div class="hero-slider__track">
        <?php foreach ($banners as $i => $b):
            $img    = $b['image_url'] ?? ($b['mobile_image_url'] ?? '');
            $mobImg = $b['mobile_image_url'] ?? '';
            if (!$img && !$mobImg) continue;
            $bgColor   = _safe_color($b['background_color'] ?? '');
            $txtColor  = _safe_color($b['text_color'] ?? '');
            $slideStyle = '';
            if ($bgColor) $slideStyle .= 'background-color:' . _se($bgColor) . ';';
            if ($txtColor) $slideStyle .= 'color:' . _se($txtColor) . ';';
        ?>
        <div class="hero-slider__slide<?= $i === 0 ? ' active' : '' ?>"
             data-index="<?= $i ?>"
             <?= $slideStyle ? 'style="' . $slideStyle . '"' : '' ?>>
            <?php if ($img || $mobImg): ?>
            <a href="<?= _se($b['link_url'] ?? '#') ?>" class="hero-slider__link"
               tabindex="<?= $i === 0 ? '0' : '-1' ?>">
                <picture>
                    <?php if ($mobImg): ?>
                    <source media="(max-width:600px)" srcset="<?= _se($mobImg) ?>">
                    <?php endif; ?>
                    <img src="<?= _se($img ?: $mobImg) ?>"
                         alt="<?= _se($b['title'] ?? '') ?>"
                         class="hero-slider__img"
                         loading="<?= $i === 0 ? 'eager' : 'lazy' ?>">
                </picture>
            </a>
            <?php endif; ?>
            <?php if (!empty($b['title']) || !empty($b['subtitle'])): ?>
            <div class="hero-slider__caption"
                 <?php $captionColor = _safe_color($b['text_color'] ?? ''); if ($captionColor): ?>style="color:<?= _se($captionColor) ?>;"<?php endif; ?>>
                <?php if (!empty($b['title'])): ?>
                <h2 class="hero-slider__title"><?= _se($b['title']) ?></h2>
                <?php endif; ?>
                <?php if (!empty($b['subtitle'])): ?>
                <p class="hero-slider__subtitle"><?= _se($b['subtitle']) ?></p>
                <?php endif; ?>
                <?php if (!empty($b['link_url']) && !empty($b['link_text'])):
                    $btnStyle = _safe_color($b['button_style'] ?? '');
                ?>
                <a href="<?= _se($b['link_url']) ?>"
                   class="hero-slider__cta btn pub-btn--primary"
                   <?= $btnStyle ? 'style="background-color:' . _se($btnStyle) . ';"' : '' ?>>
                    <?= _se($b['link_text']) ?>
                </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>

    <?php if ($sliderCount > 1): ?>
    <!-- Navigation arrows -->
    <button class="hero-slider__arrow hero-slider__arrow--prev"
            id="heroSliderPrev" type="button"
            aria-label="<?= $_dir === 'rtl' ? 'التالي' : 'Previous' ?>">‹</button>
    <button class="hero-slider__arrow hero-slider__arrow--next"
            id="heroSliderNext" type="button"
            aria-label="<?= $_dir === 'rtl' ? 'السابق' : 'Next' ?>">›</button>

    <!-- Dots -->
    <div class="hero-slider__dots" id="heroSliderDots">
        <?php for ($d = 0; $d < $sliderCount; $d++): ?>
        <button class="hero-slider__dot<?= $d === 0 ? ' active' : '' ?>"
                type="button"
                data-slide="<?= $d ?>"
                aria-label="<?= ($d + 1) ?>"></button>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</section>

<style>
/* ── Hero Slider — DB-driven, flexible, RTL-ready ── */
.hero-slider {
    position: relative;
    width: 100%;
    border-radius: var(--radius-md, 8px);
    overflow: hidden;
    background: var(--background-main, var(--background_main, var(--pub-bg, #242323)));
    aspect-ratio: 16 / 6;
    min-height: 200px;
    margin-bottom: var(--space-6, 32px);
}
.hero-slider__track { position: relative; width: 100%; height: 100%; }
.hero-slider__slide {
    position: absolute; inset: 0;
    display: none;
    background: var(--background-secondary, var(--background_secondary, var(--pub-surface, #383f42)));
    animation: sliderFadeIn 0.5s ease;
}
.hero-slider__slide.active { display: block; }
@keyframes sliderFadeIn { from { opacity: 0; } to { opacity: 1; } }
.hero-slider__link { display: block; width: 100%; height: 100%; }
.hero-slider__img {
    width: 100%; height: 100%;
    object-fit: cover; display: block;
}
.hero-slider__caption {
    position: absolute; bottom: 0; left: 0; right: 0;
    padding: 24px 28px;
    background: var(--pub-slider-overlay, linear-gradient(to top, rgba(0,0,0,0.6) 0%, transparent 100%));
    color: var(--pub-slider-text, #fff);
}
.hero-slider__title {
    margin: 0 0 6px; font-size: clamp(1rem, 3vw, 1.6rem);
    font-weight: 800; color: inherit;
}
.hero-slider__subtitle {
    margin: 0 0 12px; font-size: clamp(0.85rem, 2vw, 1rem);
    opacity: 0.9; color: inherit;
}
.hero-slider__cta {
    display: inline-block; padding: var(--btn-primary-padding, 8px 20px);
    border-radius: var(--btn-primary-radius, var(--radius-sm, 4px));
    background: var(--btn-primary-bg, var(--pub-primary, var(--primary-color, var(--primary_color, #03874e))));
    color: var(--btn-primary-color, var(--pub-slider-text, #fff)); text-decoration: none;
    font-weight: var(--btn-primary-font-weight, 600); font-size: var(--btn-primary-font-size, 0.95rem);
    border: var(--btn-primary-border-width, 1px) solid var(--btn-primary-border, transparent);
    transition: background-color 0.2s ease, border-color 0.2s ease, color 0.2s ease;
}
.hero-slider__cta:hover {
    background: var(--btn-primary-hover-bg, var(--primary-hover, var(--primary_hover, #00ff00)));
    color: var(--btn-primary-hover-color, var(--pub-slider-text, #fff));
    border-color: var(--btn-primary-hover-border, transparent);
    text-decoration: none;
}
/* Arrows */
.hero-slider__arrow {
    position: absolute; top: 50%; transform: translateY(-50%);
    background: var(--pub-slider-arrow-bg, rgba(0,0,0,0.4));
    color: var(--pub-slider-text, #fff); border: none;
    width: 42px; height: 42px; font-size: 1.6rem;
    cursor: pointer; border-radius: 50%; z-index: 5;
    transition: background 0.2s;
    display: flex; align-items: center; justify-content: center;
    line-height: 1;
}
.hero-slider__arrow:hover { background: var(--pub-slider-arrow-hover, rgba(0,0,0,0.65)); }
.hero-slider__arrow--prev { left: 12px; }
.hero-slider__arrow--next { right: 12px; }
[dir="rtl"] .hero-slider__arrow--prev { left: auto; right: 12px; }
[dir="rtl"] .hero-slider__arrow--next { right: auto; left: 12px; }
/* Dots */
.hero-slider__dots {
    position: absolute; bottom: 12px; left: 0; right: 0;
    display: flex; justify-content: center; gap: 6px; z-index: 5;
}
.hero-slider__dot {
    width: 10px; height: 10px; border-radius: 50%; border: none;
    background: var(--pub-slider-dot-bg, rgba(255,255,255,0.45)); cursor: pointer;
    transition: background 0.2s, transform 0.2s; padding: 0;
}
.hero-slider__dot.active {
    background: var(--pub-slider-dot-active, #fff); transform: scale(1.25);
}
@media (max-width: 600px) {
    .hero-slider { aspect-ratio: 16 / 9; }
    .hero-slider__arrow { width: 34px; height: 34px; font-size: 1.3rem; }
    .hero-slider__caption { padding: 16px; }
}
</style>

<script>
(function() {
    var current = 0;
    var slides = document.querySelectorAll('#heroSlider .hero-slider__slide');
    var dots   = document.querySelectorAll('#heroSlider .hero-slider__dot');
    var total  = slides.length;
    var timer;

    function show(n) {
        if (total === 0) return;
        n = ((n % total) + total) % total;
        slides.forEach(function(s, i) { s.classList.toggle('active', i === n); });
        dots.forEach(function(d, i)   { d.classList.toggle('active', i === n); });
        current = n;
    }

    function move(delta) { clearInterval(timer); show(current + delta); startAuto(); }
    function goTo(n)     { clearInterval(timer); show(n); startAuto(); }

    function startAuto() {
        if (total <= 1) return;
        timer = setInterval(function() { show(current + 1); }, 5000);
    }

    document.addEventListener('DOMContentLoaded', function() {
        var prevBtn = document.getElementById('heroSliderPrev');
        var nextBtn = document.getElementById('heroSliderNext');
        if (prevBtn) prevBtn.addEventListener('click', function() { move(-1); });
        if (nextBtn) nextBtn.addEventListener('click', function() { move(1); });

        dots.forEach(function(d) {
            d.addEventListener('click', function() {
                goTo(parseInt(this.getAttribute('data-slide'), 10));
            });
        });

        startAuto();

        /* Touch/swipe support */
        var slider = document.getElementById('heroSlider');
        if (!slider) return;
        var startX = 0;
        slider.addEventListener('touchstart', function(e) {
            startX = e.touches[0].clientX;
        }, { passive: true });
        slider.addEventListener('touchend', function(e) {
            var diff = startX - e.changedTouches[0].clientX;
            if (Math.abs(diff) > 40) {
                move(diff > 0 ? 1 : -1);
            }
        });
    });
}());
</script>
