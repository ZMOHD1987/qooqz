<?php
declare(strict_types=1);
/**
 * frontend/public/slider.php
 * QOOQZ — Banners / Promotions Slider Page
 * Shows all active banners for the tenant in a full-page slider.
 */

require_once dirname(__DIR__) . '/includes/public_context.php';

$ctx      = $GLOBALS['PUB_CONTEXT'];
$lang     = $ctx['lang'];
$dir      = $ctx['dir'];
$tenantId = $ctx['tenant_id'];
$apiBase  = pub_api_url('');

$resp    = pub_fetch($apiBase . 'public/banners?tenant_id=' . $tenantId);
$banners = $resp['data']['data'] ?? $resp['data'] ?? [];

$GLOBALS['PUB_APP_NAME']   = 'QOOQZ';
$GLOBALS['PUB_BASE_PATH']  = '/frontend/public';
$GLOBALS['PUB_PAGE_TITLE'] = t('slider.title') . ' — QOOQZ';

include dirname(__DIR__) . '/partials/header.php';
?>

<main class="pub-container" style="padding-top:28px;padding-bottom:48px;">

    <div class="pub-section-head" style="margin-bottom:20px;">
        <h1 class="pub-section-title"><?= e(t('slider.title')) ?></h1>
        <a href="/frontend/public/index.php" class="pub-section-link">← <?= e(t('nav.home')) ?></a>
    </div>

    <?php if (empty($banners)): ?>
    <div class="pub-empty">
        <div class="pub-empty-icon" aria-hidden="true">🖼️</div>
        <p class="pub-empty-msg"><?= e(t('slider.empty')) ?></p>
    </div>
    <?php else: ?>

    <!-- Full-width Slider -->
    <div class="pub-slider" id="pubSlider">
        <div class="pub-slider-track" id="pubSliderTrack">
            <?php foreach ($banners as $i => $b): ?>
            <div class="pub-slider-slide<?= $i === 0 ? ' active' : '' ?>"
                 id="pubSlide<?= $i ?>"
                 style="<?= !empty($b['background_color']) ? 'background:' . e($b['background_color']) . ';' : '' ?>">
                <?php if (!empty($b['image_url'])): ?>
                <a href="<?= e($b['link_url'] ?? '#') ?>" tabindex="<?= $i === 0 ? '0' : '-1' ?>">
                    <picture>
                        <?php if (!empty($b['mobile_image_url'])): ?>
                        <source media="(max-width:600px)"
                                srcset="<?= e(pub_img($b['mobile_image_url'])) ?>">
                        <?php endif; ?>
                        <img src="<?= e(pub_img($b['image_url'])) ?>"
                             alt="<?= e($b['title'] ?? '') ?>"
                             class="pub-slider-img"
                             loading="<?= $i === 0 ? 'eager' : 'lazy' ?>">
                    </picture>
                </a>
                <?php endif; ?>
                <?php if (!empty($b['title']) || !empty($b['subtitle'])): ?>
                <div class="pub-slider-caption">
                    <?php if (!empty($b['title'])): ?>
                    <h2 class="pub-slider-caption-title"><?= e($b['title']) ?></h2>
                    <?php endif; ?>
                    <?php if (!empty($b['subtitle'])): ?>
                    <p class="pub-slider-caption-sub"><?= e($b['subtitle']) ?></p>
                    <?php endif; ?>
                    <?php if (!empty($b['link_url']) && !empty($b['link_text'])): ?>
                    <a href="<?= e($b['link_url']) ?>" class="pub-btn pub-btn--primary">
                        <?= e($b['link_text']) ?> →
                    </a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>

        <?php if (count($banners) > 1): ?>
        <!-- Navigation arrows -->
        <button class="pub-slider-arrow pub-slider-prev" id="pubSliderPrev"
                aria-label="<?= e(t('slider.prev')) ?>" onclick="pubSliderMove(-1)">‹</button>
        <button class="pub-slider-arrow pub-slider-next" id="pubSliderNext"
                aria-label="<?= e(t('slider.next')) ?>" onclick="pubSliderMove(1)">›</button>

        <!-- Dots -->
        <div class="pub-slider-dots" id="pubSliderDots">
            <?php foreach ($banners as $i => $b): ?>
            <button class="pub-slider-dot<?= $i === 0 ? ' active' : '' ?>"
                    aria-label="<?= e(t('slider.goto', ['n' => ($i + 1)])) ?>"
                    onclick="pubSliderGoto(<?= $i ?>)"></button>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Banner grid below slider -->
    <?php if (count($banners) > 1): ?>
    <section class="pub-section" style="padding-top:32px;">
        <div class="pub-section-head">
            <h2 class="pub-section-title"><?= e(t('slider.all_promotions')) ?></h2>
        </div>
        <div class="pub-grid-md">
            <?php foreach ($banners as $b): ?>
            <?php if (empty($b['image_url'])) continue; ?>
            <a href="<?= e($b['link_url'] ?? '#') ?>" class="pub-banner-grid-card" style="text-decoration:none;">
                <img src="<?= e(pub_img($b['image_url'])) ?>"
                     alt="<?= e($b['title'] ?? '') ?>"
                     class="pub-banner-grid-img" loading="lazy">
                <?php if (!empty($b['title'])): ?>
                <p class="pub-banner-grid-title"><?= e($b['title']) ?></p>
                <?php endif; ?>
            </a>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <?php endif; ?>
</main>

<style>
/* Slider styles */
.pub-slider {
    position: relative;
    border-radius: var(--pub-radius);
    overflow: hidden;
    background: var(--pub-surface);
    max-width: 100%;
    aspect-ratio: 16/6;
    min-height: 200px;
}
.pub-slider-slide {
    display: none;
    position: absolute;
    top: 0;
    right: 0;
    bottom: 0;
    left: 0;
    background: var(--pub-surface);
}
.pub-slider-slide.active { display: block; }
.pub-slider-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}
.pub-slider-caption {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    padding: 24px;
    background: linear-gradient(to top, rgba(0,0,0,0.65) 0%, transparent 100%);
    color: #fff;
}
.pub-slider-caption-title { margin: 0 0 6px; font-size: clamp(1rem,3vw,1.6rem); font-weight: 800; }
.pub-slider-caption-sub   { margin: 0 0 12px; font-size: clamp(0.85rem,2vw,1rem); opacity: 0.9; }
.pub-slider-arrow {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    background: rgba(0,0,0,0.4);
    color: #fff;
    border: none;
    width: 42px;
    height: 42px;
    font-size: 1.6rem;
    cursor: pointer;
    border-radius: 50%;
    z-index: 5;
    transition: background 0.2s;
    display: flex; align-items: center; justify-content: center;
    line-height: 1;
}
.pub-slider-arrow:hover { background: rgba(0,0,0,0.65); }
.pub-slider-prev { left: 12px; }
.pub-slider-next { right: 12px; }
[dir="rtl"] .pub-slider-prev { left: auto; right: 12px; }
[dir="rtl"] .pub-slider-next { right: auto; left: 12px; }
.pub-slider-dots {
    position: absolute;
    bottom: 10px;
    left: 0;
    right: 0;
    display: flex;
    justify-content: center;
    gap: 6px;
    z-index: 5;
}
.pub-slider-dot {
    width: 10px; height: 10px;
    border-radius: 50%;
    border: none;
    background: rgba(255,255,255,0.5);
    cursor: pointer;
    transition: background 0.2s;
    padding: 0;
}
.pub-slider-dot.active { background: #fff; }
/* Banner grid */
.pub-banner-grid-card {
    display: flex;
    flex-direction: column;
    gap: 8px;
    border-radius: var(--pub-radius);
    overflow: hidden;
    background: var(--pub-bg);
    border: 1px solid var(--pub-border);
    transition: box-shadow 0.2s;
}
.pub-banner-grid-card:hover { box-shadow: var(--pub-shadow-hover); }
.pub-banner-grid-img { width: 100%; height: 180px; object-fit: cover; display: block; }
.pub-banner-grid-title { padding: 8px 12px; font-size: 0.9rem; font-weight: 600; color: var(--pub-text); margin: 0; }
@media (max-width: 480px) {
    .pub-slider { aspect-ratio: 16/9; }
    .pub-slider-arrow { width: 34px; height: 34px; font-size: 1.3rem; }
}
</style>

<script>
(function() {
    var current = 0;
    var slides = document.querySelectorAll('.pub-slider-slide');
    var dots   = document.querySelectorAll('.pub-slider-dot');
    var total  = slides.length;
    var timer;

    function show(n) {
        if (total === 0) return;
        n = ((n % total) + total) % total;
        slides.forEach(function(s, i) { s.classList.toggle('active', i === n); });
        dots.forEach(function(d, i)   { d.classList.toggle('active', i === n); });
        current = n;
    }

    window.pubSliderMove = function(delta) { clearInterval(timer); show(current + delta); startAuto(); };
    window.pubSliderGoto = function(n)     { clearInterval(timer); show(n); startAuto(); };

    function startAuto() {
        if (total <= 1) return;
        timer = setInterval(function() { show(current + 1); }, 5000);
    }

    document.addEventListener('DOMContentLoaded', function() {
        startAuto();
        /* Touch/swipe support */
        var slider = document.getElementById('pubSlider');
        if (!slider) return;
        var startX = 0;
        slider.addEventListener('touchstart', function(e) { startX = e.touches[0].clientX; }, { passive: true });
        slider.addEventListener('touchend', function(e) {
            var diff = startX - e.changedTouches[0].clientX;
            if (Math.abs(diff) > 40) {
                clearInterval(timer);
                show(current + (diff > 0 ? 1 : -1));
                startAuto();
            }
        });
    });
}());
</script>

<?php include dirname(__DIR__) . '/partials/footer.php'; ?>