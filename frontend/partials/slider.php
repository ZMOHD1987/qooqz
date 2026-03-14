<?php
/**///htdocs/frontend/partials/slider.php
 * Homepage Slider
 */
$banners = $banners ?? [];
if (empty($banners)) return;
?>

<section class="hero-slider">
    <div class="slider-wrapper">
        <?php foreach ($banners as $i => $b): 
            $img = $b['image_url'] ?: $b['mobile_image_url'];
            if (!$img) continue;
        ?>
            <div class="slide <?= $i === 0 ? 'active' : '' ?>">
                <img src="<?= htmlspecialchars($img) ?>" alt="<?= htmlspecialchars($b['title'] ?? '') ?>">
                <?php if (!empty($b['title'])): ?>
                    <div class="slide-caption">
                        <h2><?= htmlspecialchars($b['title']) ?></h2>
                        <?php if (!empty($b['subtitle'])): ?>
                            <p><?= htmlspecialchars($b['subtitle']) ?></p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<script>
(function(){
    const slides=document.querySelectorAll('.hero-slider .slide');
    if(!slides.length) return;
    let i=0;
    setInterval(()=>{
        slides.forEach(s=>s.classList.remove('active'));
        slides[++i % slides.length].classList.add('active');
    },5000);
})();
</script>
