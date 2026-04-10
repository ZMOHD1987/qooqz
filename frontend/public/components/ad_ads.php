<?php
declare(strict_types=1);
/**
 * Component: ad_ads [v2.5.1 — Production Final]
 * سلايدر إعلانات متحرك تلقائي + أزرار + نقاط تنقل
 */

if (empty($sectionData) || !is_array($sectionData)) {
    return;
}

if (!function_exists('_ad_link')) {
    function _ad_link(string $type, string $value): string {
        if (empty($value)) return '#';
        return match ($type) {
            'url' => (static function (string $v): string {
                $scheme = parse_url($v, PHP_URL_SCHEME);
                return ($scheme !== null && in_array(strtolower($scheme), ['http', 'https'], true)) ? $v : '#';
            })($value),
            default => '/frontend/public/' . $type . '.php?id=' . urlencode($value),
        };
    }
}

if (!function_exists('_ad_is_external')) {
    function _ad_is_external(string $type, string $href): bool {
        return $type === 'url' && $href !== '#';
    }
}
?>

<div class="ads-hero-slider" id="mainAdsSlider">
    <?php foreach ($sectionData as $i => $ad):
        $adId   = (int)($ad['id'] ?? 0);
        $title  = trim($ad['title'] ?? 'إعلان ممول');
        $desc   = trim($ad['description'] ?? '');
        $image  = $ad['image_url'] ?? $ad['thumb_url'] ?? '';
        $type   = $ad['target_type'] ?? '';
        $value  = $ad['target_value'] ?? '';

        $href    = _ad_link($type, $value);
        $external = _ad_is_external($type, $href);

        if ($adId === 0) continue;
    ?>
    <div class="ads-slide <?= $i === 0 ? 'active' : '' ?>" data-index="<?= $i ?>">
        <a href="<?= e($href) ?>" 
           class="ads-slide-link"
           <?= $external ? 'target="_blank" rel="noopener noreferrer"' : '' ?>
           data-ad-id="<?= $adId ?>"
           onclick="__qzAdClick(<?= $adId ?>)">

            <?php if ($image): ?>
            <img src="<?= e(pub_img($image)) ?>" 
                 alt="<?= e($title) ?>" 
                 class="ads-slide-img"
                 loading="<?= $i === 0 ? 'eager' : 'lazy' ?>">
            <?php endif; ?>

            <div class="ads-slide-content">
                <h2 class="ads-slide-title"><?= e($title) ?></h2>
                <?php if ($desc): ?>
                <p class="ads-slide-desc"><?= e($desc) ?></p>
                <?php endif; ?>
                <span class="ads-badge"><?= e(t('ads.sponsored')) ?></span>
            </div>
        </a>
    </div>
    <?php endforeach; ?>
</div>

<!-- Controls -->
<div class="ads-slider-controls">
    <button class="ads-prev" aria-label="السابق">‹</button>
    <div class="ads-dots" id="mainAdsDots"></div>
    <button class="ads-next" aria-label="التالي">›</button>
</div>

<style>
.ads-hero-slider { position: relative; z-index: 10 !important; height: 480px; overflow: hidden; border-radius: 16px; box-shadow: 0 10px 40px rgba(0,0,0,0.15); margin: 25px 0; }
.ads-slide { position: absolute; inset: 0; opacity: 0; transition: opacity 1s ease; display: none; }
.ads-slide.active { opacity: 1; display: block; }
.ads-slide-link { display: block; height: 100%; position: relative; }
.ads-slide-img { width: 100%; height: 100%; object-fit: cover; }
.ads-slide-content { position: absolute; bottom: 0; left: 0; right: 0; background: linear-gradient(transparent, rgba(0,0,0,0.85)); color: white; padding: 60px 40px 40px; }
.ads-slide-title { font-size: 2.3rem; font-weight: 700; margin: 0 0 12px 0; line-height: 1.2; }
.ads-slide-desc { font-size: 1.15rem; margin: 0 0 20px 0; }
.ads-badge { background: #ff3d00; color: white; padding: 8px 20px; border-radius: 50px; font-size: 0.95rem; }

/* Controls */
.ads-slider-controls { display: flex; align-items: center; justify-content: center; gap: 20px; margin-top: 15px; }
.ads-prev, .ads-next { background: rgba(0,0,0,0.7); color: white; border: none; width: 48px; height: 48px; border-radius: 50%; font-size: 26px; cursor: pointer; }
.ads-prev:hover, .ads-next:hover { background: #ff3d00; }
.ads-dots { display: flex; gap: 10px; }
.ads-dot { width: 14px; height: 14px; background: #ccc; border-radius: 50%; cursor: pointer; transition: all 0.3s; }
.ads-dot.active { background: #ff3d00; transform: scale(1.4); }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const slider = document.getElementById('mainAdsSlider');
    if (!slider) return;
    const slides = slider.querySelectorAll('.ads-slide');
    if (slides.length <= 1) return;

    let current = 0;
    let autoTimer;

    const dotsContainer = document.getElementById('mainAdsDots');

    slides.forEach((_, i) => {
        const dot = document.createElement('div');
        dot.classList.add('ads-dot');
        if (i === 0) dot.classList.add('active');
        dot.addEventListener('click', () => { goToSlide(i); resetTimer(); });
        dotsContainer.appendChild(dot);
    });

    const dots = dotsContainer.querySelectorAll('.ads-dot');

    function goToSlide(n) {
        slides.forEach(s => s.classList.remove('active'));
        dots.forEach(d => d.classList.remove('active'));
        slides[n].classList.add('active');
        dots[n].classList.add('active');
        current = n;
    }

    function nextSlide() {
        current = (current + 1) % slides.length;
        goToSlide(current);
    }

    function prevSlide() {
        current = (current - 1 + slides.length) % slides.length;
        goToSlide(current);
    }

    const prevBtn = document.querySelector('.ads-prev');
    const nextBtn = document.querySelector('.ads-next');

    if (prevBtn) prevBtn.addEventListener('click', () => { prevSlide(); resetTimer(); });
    if (nextBtn) nextBtn.addEventListener('click', () => { nextSlide(); resetTimer(); });

    function startAuto() {
        autoTimer = setInterval(nextSlide, 4500);
    }

    function resetTimer() {
        clearInterval(autoTimer);
        startAuto();
    }

    slider.addEventListener('mouseenter', () => clearInterval(autoTimer));
    slider.addEventListener('mouseleave', startAuto);

    startAuto();
    goToSlide(0);
});
</script>