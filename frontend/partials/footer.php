<?php
/**
 * Frontend Footer Partial — QOOQZ Global Public Interface
 */
$_year     = date('Y');
$_ctx      = $GLOBALS['PUB_CONTEXT'] ?? [];
$_appName  = $GLOBALS['PUB_APP_NAME'] ?? 'QOOQZ';
$_basePath = rtrim($GLOBALS['PUB_BASE_PATH'] ?? '/frontend/public', '/');
if (!function_exists('e')) {
    function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }
}
if (!function_exists('t')) {
    function t(string $key, array $r = []): string { return $key; }
}
?>

<!-- =============================================
     FOOTER
============================================= -->
<footer class="pub-footer" role="contentinfo"
        style="background:var(--pub-footer-bg,#1e2a38);color:var(--pub-footer-text,rgba(255,255,255,0.8));border-top:4px solid var(--pub-primary,#2d8cf0);">
    <div class="pub-container">
        <div class="pub-footer-grid">

            <!-- Brand column -->
            <div class="pub-footer-col">
                <p class="pub-footer-brand-name">🌐 <?= e($_appName) ?></p>
                <p class="pub-footer-brand-desc"><?= e(t('footer.tagline')) ?></p>
            </div>

            <!-- Quick links -->
            <div class="pub-footer-col">
                <p class="pub-footer-col-title"><?= e(t('footer.quick_links')) ?></p>
                <a href="<?= e($_basePath . '/index.php') ?>"><?= e(t('nav.home')) ?></a>
                <a href="<?= e($_basePath . '/products.php') ?>"><?= e(t('nav.products')) ?></a>
                <a href="<?= e($_basePath . '/categories.php') ?>"><?= e(t('nav.categories')) ?></a>
                <a href="<?= e($_basePath . '/jobs.php') ?>"><?= e(t('nav.jobs')) ?></a>
                <a href="<?= e($_basePath . '/entities.php') ?>"><?= e(t('nav.entities')) ?></a>
                <a href="<?= e($_basePath . '/tenants.php') ?>"><?= e(t('nav.tenants')) ?></a>
            </div>

            <!-- Support -->
            <div class="pub-footer-col">
                <p class="pub-footer-col-title"><?= e(t('footer.support')) ?></p>
                <a href="#"><?= e(t('footer.about')) ?></a>
                <a href="#"><?= e(t('footer.contact')) ?></a>
                <a href="#"><?= e(t('footer.privacy')) ?></a>
                <a href="#"><?= e(t('footer.terms')) ?></a>
            </div>

            <!-- Auth -->
            <div class="pub-footer-col">
                <p class="pub-footer-col-title"><?= e(t('footer.account')) ?></p>
                <a href="/frontend/login.php"><?= e(t('nav.login')) ?></a>
                <a href="/frontend/login.php?tab=register"><?= e(t('nav.register')) ?></a>
            </div>

        </div>
    </div>

    <div class="pub-footer-bottom">
        © <?= $_year ?> <?= e($_appName) ?> — <?= e(t('footer.rights')) ?>
    </div>
</footer>

<!-- Back-to-top button -->
<?php $_btt_side = ($_ctx['dir'] ?? 'rtl') === 'rtl' ? 'left' : 'right'; ?>
<button id="pubBackToTop" title="<?= e(t('footer.back_to_top')) ?>"
        style="display:none;position:fixed;bottom:20px;<?= e($_btt_side) ?>:20px;
               z-index:200;width:40px;height:40px;background:var(--pub-primary);color:#fff;
               border:none;border-radius:50%;font-size:1.2rem;cursor:pointer;align-items:center;
               justify-content:center;box-shadow:0 4px 12px rgba(0,0,0,0.2);">↑</button>

<!-- Public JS — ?v= cache-busting -->
<?php $_pubJsV = @filemtime(FRONTEND_BASE . '/assets/js/public.js') ?: '1'; ?>
<script src="/frontend/assets/js/public.js?v=<?= $_pubJsV ?>"></script>
</body>
</html>