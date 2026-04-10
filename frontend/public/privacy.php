<?php
declare(strict_types=1);
/**
 * frontend/public/privacy.php
 * QOOQZ — Privacy Policy Page
 */
require_once dirname(__DIR__) . '/includes/public_context.php';

$GLOBALS['PUB_APP_NAME']   = 'QOOQZ';
$GLOBALS['PUB_BASE_PATH']  = '/frontend/public';
$GLOBALS['PUB_PAGE_TITLE'] = t('privacy.page_title', 'Privacy Policy') . ' — QOOQZ';
$GLOBALS['PUB_PAGE_TYPE']  = 'privacy';

include dirname(__DIR__) . '/partials/header.php';
?>

<style>
.legal-hero {
    background: var(--pub-surface, #f9fafb);
    border-bottom: 1px solid var(--pub-border, #e5e7eb);
    padding: 3rem 1.5rem;
    text-align: center;
}
.legal-hero h1 { font-size: 2rem; margin-bottom: .4rem; }
.legal-hero .meta { font-size: .875rem; color: var(--pub-text-muted, #6b7280); }

.legal-body {
    max-width: 820px;
    margin: 2.5rem auto;
    padding: 0 1.5rem 4rem;
}
.legal-body h2 {
    font-size: 1.2rem;
    font-weight: 700;
    color: var(--pub-primary, #2563eb);
    margin: 2rem 0 .75rem;
}
.legal-body p {
    font-size: .97rem;
    line-height: 1.85;
    color: var(--pub-text, #374151);
    margin-bottom: .75rem;
}
.legal-body ul {
    margin: .5rem 0 1rem 1.5rem;
    padding: 0;
    color: var(--pub-text, #374151);
    font-size: .97rem;
    line-height: 1.8;
}
</style>

<div class="legal-hero">
    <h1><?= e(t('privacy.page_title', 'Privacy Policy')) ?></h1>
    <p class="meta"><?= e(t('privacy.last_updated', 'Last Updated')) ?>: <?= date('F d, Y') ?></p>
</div>

<div class="legal-body">
    <p><?= e(t('privacy.intro')) ?></p>

    <h2><?= e(t('privacy.s1_title')) ?></h2>
    <ul>
        <li><?= e(t('privacy.s1_account')) ?></li>
        <li><?= e(t('privacy.s1_transaction')) ?></li>
        <li><?= e(t('privacy.s1_usage')) ?></li>
        <li><?= e(t('privacy.s1_communications')) ?></li>
        <li><?= e(t('privacy.s1_cookies')) ?></li>
    </ul>

    <h2><?= e(t('privacy.s2_title')) ?></h2>
    <p><?= e(t('privacy.s2_text')) ?></p>

    <h2><?= e(t('privacy.s3_title')) ?></h2>
    <p><?= e(t('privacy.s3_text')) ?></p>

    <h2><?= e(t('privacy.s4_title')) ?></h2>
    <p><?= e(t('privacy.s4_text')) ?></p>

    <h2><?= e(t('privacy.s5_title')) ?></h2>
    <p><?= e(t('privacy.s5_text')) ?></p>

    <h2><?= e(t('privacy.s6_title')) ?></h2>
    <p><?= e(t('privacy.s6_text')) ?></p>

    <h2><?= e(t('privacy.s7_title')) ?></h2>
    <p><?= e(t('privacy.s7_text')) ?></p>

    <h2><?= e(t('privacy.s8_title')) ?></h2>
    <p><?= e(t('privacy.s8_text')) ?></p>

    <h2><?= e(t('privacy.s9_title')) ?></h2>
    <p><?= e(t('privacy.s9_text')) ?></p>

    <h2><?= e(t('privacy.s10_title')) ?></h2>
    <p><?= e(t('privacy.s10_text')) ?></p>

    <h2><?= e(t('privacy.s11_title')) ?></h2>
    <p><?= e(t('privacy.s11_text')) ?></p>
</div>

<?php include dirname(__DIR__) . '/partials/footer.php'; ?>
