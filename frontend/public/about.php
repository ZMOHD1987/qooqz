<?php
declare(strict_types=1);
/**
 * frontend/public/about.php
 * QOOQZ — About Us Page
 */
require_once dirname(__DIR__) . '/includes/public_context.php';

$GLOBALS['PUB_APP_NAME']   = 'QOOQZ';
$GLOBALS['PUB_BASE_PATH']  = '/frontend/public';
$GLOBALS['PUB_PAGE_TITLE'] = t('about.page_title', 'About Us') . ' — QOOQZ';
$GLOBALS['PUB_PAGE_TYPE']  = 'about';

include dirname(__DIR__) . '/partials/header.php';
?>

<style>
.about-hero {
    background: linear-gradient(135deg, var(--pub-primary, #2563eb) 0%, #1e40af 100%);
    color: #fff;
    padding: 4rem 1.5rem;
    text-align: center;
}
.about-hero h1 { font-size: 2.2rem; margin-bottom: .75rem; }
.about-hero p  { font-size: 1.1rem; opacity: .9; max-width: 600px; margin: 0 auto; }

.about-section {
    padding: 3rem 1.5rem;
    max-width: 900px;
    margin: 0 auto;
}
.about-section h2 {
    font-size: 1.5rem;
    color: var(--pub-primary, #2563eb);
    margin-bottom: 1rem;
    border-bottom: 2px solid var(--pub-primary, #2563eb);
    padding-bottom: .4rem;
}
.about-section p {
    line-height: 1.8;
    color: var(--pub-text, #374151);
    font-size: 1rem;
}

.about-values-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1.5rem;
    margin-top: 1.5rem;
}
.about-value-card {
    background: var(--pub-surface, #f9fafb);
    border: 1px solid var(--pub-border, #e5e7eb);
    border-radius: 12px;
    padding: 1.5rem;
    text-align: center;
}
.about-value-card .icon { font-size: 2rem; margin-bottom: .75rem; }
.about-value-card h3 { font-size: 1rem; font-weight: 600; margin-bottom: .5rem; }
.about-value-card p { font-size: .9rem; color: var(--pub-text-muted, #6b7280); }

.about-cta {
    background: var(--pub-surface, #f9fafb);
    padding: 3rem 1.5rem;
    text-align: center;
    border-top: 1px solid var(--pub-border, #e5e7eb);
}
.about-cta h2 { font-size: 1.6rem; margin-bottom: .75rem; }
.about-cta p  { color: var(--pub-text-muted, #6b7280); margin-bottom: 1.5rem; }
.about-cta a  {
    display: inline-block;
    background: var(--pub-primary, #2563eb);
    color: #fff;
    padding: .75rem 2rem;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 600;
}
.about-cta a:hover { opacity: .9; }
</style>

<!-- Hero -->
<section class="about-hero">
    <h1><?= e(t('about.hero_title', 'About QOOQZ')) ?></h1>
    <p><?= e(t('about.hero_subtitle', 'A global multi-vendor marketplace connecting customers, vendors, and service providers.')) ?></p>
</section>

<!-- Mission -->
<section class="about-section">
    <h2><?= e(t('about.mission_title', 'Our Mission')) ?></h2>
    <p><?= e(t('about.mission_text')) ?></p>
</section>

<!-- Vision -->
<section class="about-section" style="padding-top:0">
    <h2><?= e(t('about.vision_title', 'Our Vision')) ?></h2>
    <p><?= e(t('about.vision_text')) ?></p>
</section>

<!-- Values -->
<section class="about-section" style="padding-top:0">
    <h2><?= e(t('about.values_title', 'Our Values')) ?></h2>
    <div class="about-values-grid">
        <div class="about-value-card">
            <div class="icon">🤝</div>
            <h3><?= e(t('about.value_trust', 'Trust & Transparency')) ?></h3>
            <p><?= e(t('about.value_trust_text')) ?></p>
        </div>
        <div class="about-value-card">
            <div class="icon">💡</div>
            <h3><?= e(t('about.value_innovation', 'Innovation')) ?></h3>
            <p><?= e(t('about.value_innovation_text')) ?></p>
        </div>
        <div class="about-value-card">
            <div class="icon">🌍</div>
            <h3><?= e(t('about.value_community', 'Community')) ?></h3>
            <p><?= e(t('about.value_community_text')) ?></p>
        </div>
        <div class="about-value-card">
            <div class="icon">🔒</div>
            <h3><?= e(t('about.value_security', 'Security')) ?></h3>
            <p><?= e(t('about.value_security_text')) ?></p>
        </div>
    </div>
</section>

<!-- Team -->
<section class="about-section" style="padding-top:0">
    <h2><?= e(t('about.team_title', 'Our Team')) ?></h2>
    <p><?= e(t('about.team_text')) ?></p>
</section>

<!-- CTA -->
<section class="about-cta">
    <h2><?= e(t('about.join_title', 'Join the Platform')) ?></h2>
    <p><?= e(t('about.join_text')) ?></p>
    <a href="/frontend/login.php?tab=register"><?= e(t('about.join_cta', 'Get Started')) ?></a>
</section>

<?php include dirname(__DIR__) . '/partials/footer.php'; ?>
