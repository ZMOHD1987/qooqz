<?php
/**
 * Frontend Footer Partial — QOOQZ Global Public Interface
 * معدل لاستخدام أيقونات SVG بدلاً من الرموز التعبيرية
 */
$_year     = date('Y');
$_ctx      = $GLOBALS['PUB_CONTEXT'] ?? [];
$_appName  = $GLOBALS['PUB_APP_NAME'] ?? 'QOOQZ';
$_basePath = rtrim($GLOBALS['PUB_BASE_PATH'] ?? '/frontend/public', '/');
if (!function_exists('e')) {
    function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }
}
if (!function_exists('t')) {
    function t(string $key, string|array $r = []): string { return is_string($r) ? $r : $key; }
}
?>

    </main><!-- .pub-main-content -->
</div><!-- .pub-layout -->

<!-- =============================================
     FOOTER
============================================= -->
<footer class="pub-footer" role="contentinfo">
    <div class="pub-container">
        <div class="pub-footer-grid">

            <!-- Brand column مع أيقونة SVG -->
            <div class="pub-footer-col">
                <p class="pub-footer-brand-name">
                    <!-- SVG Globe icon بدلاً من 🌐 -->
                    <span class="pub-footer-brand-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </span>
                    <?= e($_appName) ?>
                </p>
                <p class="pub-footer-brand-desc"><?= e(t('footer.tagline')) ?></p>
            </div>

            <!-- Quick links (بدون أيقونات، تبقى نصوصاً فقط) -->
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
                <a href="<?= e($_basePath . '/about.php') ?>"><?= e(t('footer.about')) ?></a>
                <a href="<?= e($_basePath . '/contact.php') ?>"><?= e(t('footer.contact')) ?></a>
                <a href="<?= e($_basePath . '/privacy.php') ?>"><?= e(t('footer.privacy')) ?></a>
                <a href="<?= e($_basePath . '/terms.php') ?>"><?= e(t('footer.terms')) ?></a>
                <a href="<?= e($_basePath . '/support.php') ?>"><?= e(t('footer.support_center')) ?></a>
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

<!-- Mobile bottom navigation bar (أيقونات SVG بدلاً من Emoji) -->
<?php
$_isLoggedIn = !empty(($GLOBALS['PUB_CONTEXT']['user'] ?? [])['id']);
$_authPath   = '/frontend';
?>
<nav class="pub-bottom-nav" aria-label="<?= e(t('nav.actions', 'Navigation')) ?>">
    <!-- Home -->
    <a href="<?= e($_basePath . '/index.php') ?>" class="pub-bottom-nav__item">
        <span class="pub-bottom-nav__icon" aria-hidden="true">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="24" height="24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
            </svg>
        </span>
        <span class="pub-bottom-nav__label"><?= e(t('nav.home', 'Home')) ?></span>
    </a>
    <!-- Categories -->
    <a href="<?= e($_basePath . '/categories.php') ?>" class="pub-bottom-nav__item">
        <span class="pub-bottom-nav__icon" aria-hidden="true">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="24" height="24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
            </svg>
        </span>
        <span class="pub-bottom-nav__label"><?= e(t('nav.categories', 'Categories')) ?></span>
    </a>
    <!-- Cart -->
    <a href="<?= e($_basePath . '/cart.php') ?>" class="pub-bottom-nav__item" style="position:relative;">
        <span class="pub-bottom-nav__icon" aria-hidden="true" style="position:relative;">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="24" height="24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
            </svg>
            <span id="pubCartCountFooter" style="position:absolute; top:-4px; right:-8px; background:var(--pub-danger, #ef4444); color:white; font-size:10px; font-weight:bold; padding:2px 5px; border-radius:10px; display:none; line-height:1;"></span>
        </span>
        <span class="pub-bottom-nav__label"><?= e(t('nav.cart', 'Cart')) ?></span>
    </a>
    <!-- Profile (أيقونة المستخدم) -->
    <a href="<?= e($_isLoggedIn ? $_authPath . '/profile.php' : $_authPath . '/login.php') ?>" class="pub-bottom-nav__item">
        <span class="pub-bottom-nav__icon" aria-hidden="true">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="24" height="24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
            </svg>
        </span>
        <span class="pub-bottom-nav__label"><?= e(t('nav.profile', 'Profile')) ?></span>
    </a>
</nav>

<!-- Back-to-top button (يمكن استبدال السهم بأيقونة SVG أيضاً إذا رغبت) -->
<?php $_btt_side = ($_ctx['dir'] ?? 'ltr') === 'rtl' ? 'left' : 'right'; ?>
<button id="pubBackToTop" title="<?= e(t('footer.back_to_top')) ?>"
        style="display:none;position:fixed;bottom:20px;<?= e($_btt_side) ?>:20px;
               z-index:200;width:40px;height:40px;background:var(--pub-primary);color:var(--pub-btn-primary-text,#fff);
               border:none;border-radius:50%;font-size:1.2rem;cursor:pointer;align-items:center;
               justify-content:center;box-shadow:0 4px 12px rgba(0,0,0,0.2);">↑</button>

<!-- Public JS — ?v= cache-busting -->
<?php $_pubJsV = @filemtime(FRONTEND_BASE . '/assets/js/public.js') ?: '1'; ?>
<script src="/frontend/assets/js/public.js?v=<?= $_pubJsV ?>"></script>

<?php
// ════════════════════════════════════════════════════════════
// Device Registration Fallback (بدون FCM)
// ════════════════════════════════════════════════════════════
$_devRegUserId = 0;
if (isset($_user) && !empty($_user['id'])) {
    $_devRegUserId = (int)$_user['id'];
} elseif (!empty($_SESSION['user_id'])) {
    $_devRegUserId = (int)$_SESSION['user_id'];
} elseif (!empty($_SESSION['user']['id'])) {
    $_devRegUserId = (int)$_SESSION['user']['id'];
}

if ($_devRegUserId > 0):
?>
<script>
(function(){
    var STORAGE_KEY='qz_dev_reg', DAY_MS=86400000;
    var lastReg=localStorage.getItem(STORAGE_KEY);
    if(lastReg && (Date.now()-parseInt(lastReg,10))<DAY_MS) return;
    fetch('/api/public/user_devices',{
        method:'POST',
        credentials:'same-origin',
        headers:{'Content-Type':'application/json'},
        body:JSON.stringify({})
    }).then(function(r){
        if(r.ok) localStorage.setItem(STORAGE_KEY,''+Date.now());
    }).catch(function(){});
})();
</script>
<?php endif; // $_devRegUserId check ?>

<?php
// ════════════════════════════════════════════════════════════
// Firebase Push Notifications — Device Registration
// ════════════════════════════════════════════════════════════
$_fcmEnabled = defined('FCM_ENABLED') ? FCM_ENABLED : false;
if (!$_fcmEnabled) {
    $_envPath = dirname(__DIR__, 2) . '/api/.env';
    if (!file_exists($_envPath)) {
        $_envPath = dirname(__DIR__, 2) . '/api/shared/config/.env';
    }
    if (file_exists($_envPath) && is_readable($_envPath)) {
        foreach (file($_envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $_eLine) {
            $_eLine = trim($_eLine);
            if ($_eLine === '' || str_starts_with($_eLine, '#') || !str_contains($_eLine, '=')) continue;
            [$_eK, $_eV] = array_map('trim', explode('=', $_eLine, 2));
            if ($_eK !== '' && getenv($_eK) === false) {
                putenv("{$_eK}={$_eV}");
            }
        }
    }

    $_constFile = dirname(__DIR__, 2) . '/api/shared/config/constants.php';
    if (file_exists($_constFile)) {
        $_consts = @include $_constFile;
        if (is_array($_consts)) {
            $_fcmEnabled = !empty($_consts['FCM_ENABLED']);
            if ($_fcmEnabled) {
                foreach ($_consts as $_ck => $_cv) {
                    if (str_starts_with($_ck, 'FCM_') && !defined($_ck)) {
                        define($_ck, $_cv);
                    }
                }
            }
        }
    }
}

$_fcmUserId = 0;
if (isset($_user) && !empty($_user['id'])) {
    $_fcmUserId = (int)$_user['id'];
} elseif (!empty($_SESSION['user_id'])) {
    $_fcmUserId = (int)$_SESSION['user_id'];
} elseif (!empty($_SESSION['user']['id'])) {
    $_fcmUserId = (int)$_SESSION['user']['id'];
}

if ($_fcmEnabled && $_fcmUserId > 0):
    $_fcmCfg = [
        'apiKey'            => defined('FCM_API_KEY')             ? FCM_API_KEY             : '',
        'authDomain'        => defined('FCM_AUTH_DOMAIN')         ? FCM_AUTH_DOMAIN         : '',
        'projectId'         => defined('FCM_PROJECT_ID')          ? FCM_PROJECT_ID          : '',
        'messagingSenderId' => defined('FCM_MESSAGING_SENDER_ID') ? FCM_MESSAGING_SENDER_ID : '',
        'appId'             => defined('FCM_APP_ID')              ? FCM_APP_ID              : '',
        'vapidKey'          => defined('FCM_VAPID_KEY')           ? FCM_VAPID_KEY           : '',
    ];
    if (!empty($_fcmCfg['projectId'])):
?>
<script>
window.APP_CONFIG = window.APP_CONFIG || {};
Object.assign(window.APP_CONFIG, {
    FCM_API_KEY:             <?= json_encode($_fcmCfg['apiKey']) ?>,
    FCM_AUTH_DOMAIN:         <?= json_encode($_fcmCfg['authDomain']) ?>,
    FCM_PROJECT_ID:          <?= json_encode($_fcmCfg['projectId']) ?>,
    FCM_MESSAGING_SENDER_ID: <?= json_encode($_fcmCfg['messagingSenderId']) ?>,
    FCM_APP_ID:              <?= json_encode($_fcmCfg['appId']) ?>,
    FCM_VAPID_KEY:           <?= json_encode($_fcmCfg['vapidKey']) ?>,
    API_BASE:                '/api',
    USER_ID:                 <?= $_fcmUserId ?>
});
</script>
<script src="https://www.gstatic.com/firebasejs/10.7.0/firebase-app-compat.js" defer></script>
<script src="https://www.gstatic.com/firebasejs/10.7.0/firebase-messaging-compat.js" defer></script>
<?php $_fcmJsV = @filemtime(FRONTEND_BASE . '/assets/js/firebase.js') ?: '1'; ?>
<script src="/frontend/assets/js/firebase.js?v=<?= $_fcmJsV ?>" defer></script>
<?php endif; // projectId check ?>
<?php endif; // fcmEnabled + userId check ?>
</body>
</html>