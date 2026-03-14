<?php
declare(strict_types=1);
/**
 * frontend/profile.php
 * QOOQZ — Public User Profile Page
 * Shows logged-in user's info (name, email, roles).
 * Requires authentication; redirects to login if not logged in.
 */

require_once __DIR__ . '/includes/public_context.php';

$ctx  = $GLOBALS['PUB_CONTEXT'];
$user = $ctx['user'] ?? null;

// Require login
if (empty($user['id'])) {
    header('Location: /frontend/login.php?redirect=' . urlencode('/frontend/profile.php'));
    exit;
}

$GLOBALS['PUB_APP_NAME']   = 'QOOQZ';
$GLOBALS['PUB_BASE_PATH']  = '/frontend/public';
$GLOBALS['PUB_PAGE_TITLE'] = e(t('nav.account')) . ' — QOOQZ';

include __DIR__ . '/partials/header.php';

$uName  = $user['name']     ?? $user['username'] ?? '';
$uEmail = $user['email']    ?? '';
$uLang  = $user['preferred_language'] ?? $ctx['lang'] ?? 'ar';
$uRoles = $user['roles']    ?? [];
$dir    = $ctx['dir'] ?? 'rtl';
?>

<main class="pub-container" style="padding:40px 0 60px;">

    <!-- Breadcrumb -->
    <nav style="font-size:0.84rem;color:var(--pub-muted);margin-bottom:24px;" aria-label="breadcrumb">
        <a href="/frontend/public/index.php"><?= e(t('nav.home')) ?></a>
        <span style="margin:0 6px;">›</span>
        <span><?= e(t('nav.account')) ?></span>
    </nav>

    <div style="max-width:680px;margin:0 auto;">

        <!-- Avatar + Name -->
        <div style="display:flex;align-items:center;gap:20px;margin-bottom:32px;
                    padding:28px;background:var(--pub-surface);border-radius:16px;
                    border:1px solid var(--pub-border);">
            <div style="width:72px;height:72px;border-radius:50%;
                        background:var(--pub-primary);display:flex;align-items:center;
                        justify-content:center;font-size:1.8rem;color:#fff;
                        font-weight:700;flex-shrink:0;">
                <?= e(mb_substr($uName, 0, 1, 'UTF-8')) ?>
            </div>
            <div>
                <h1 style="font-size:1.3rem;font-weight:700;margin:0 0 4px;color:var(--pub-text);">
                    <?= e($uName) ?>
                </h1>
                <?php if ($uEmail): ?>
                <p style="color:var(--pub-muted);margin:0;font-size:0.9rem;">
                    <?= e($uEmail) ?>
                </p>
                <?php endif; ?>
                <?php if (!empty($uRoles)): ?>
                <div style="margin-top:8px;display:flex;flex-wrap:wrap;gap:6px;">
                    <?php foreach ($uRoles as $role): ?>
                    <span style="padding:2px 10px;border-radius:20px;font-size:0.75rem;font-weight:600;
                                 background:var(--pub-primary);color:#fff;">
                        <?= e($role) ?>
                    </span>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Account info -->
        <div style="background:var(--pub-surface);border-radius:16px;
                    border:1px solid var(--pub-border);overflow:hidden;margin-bottom:24px;">
            <div style="padding:16px 24px;border-bottom:1px solid var(--pub-border);">
                <h2 style="font-size:1rem;font-weight:600;margin:0;color:var(--pub-text);">
                    👤 <?= e(t('profile.account_info', ['default' => 'Account Information'])) ?>
                </h2>
            </div>
            <div style="padding:20px 24px;">
                <?php
                $rows = [
                    [t('profile.full_name', ['default' => 'Full Name']), $uName],
                    [t('profile.email',     ['default' => 'Email']),     $uEmail],
                    [t('profile.language',  ['default' => 'Language']),  strtoupper($uLang)],
                ];
                ?>
                <?php foreach ($rows as [$label, $value]): if (!$value) continue; ?>
                <div style="display:flex;justify-content:space-between;padding:10px 0;
                            border-bottom:1px solid var(--pub-border);font-size:0.9rem;">
                    <span style="color:var(--pub-muted);"><?= e($label) ?></span>
                    <span style="color:var(--pub-text);font-weight:500;"><?= e($value) ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Quick links -->
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:24px;">
            <a href="/frontend/public/index.php"
               style="display:flex;align-items:center;gap:10px;padding:16px 20px;
                      background:var(--pub-surface);border-radius:12px;
                      border:1px solid var(--pub-border);text-decoration:none;color:var(--pub-text);">
                🏠 <?= e(t('nav.home')) ?>
            </a>
            <a href="/frontend/public/products.php"
               style="display:flex;align-items:center;gap:10px;padding:16px 20px;
                      background:var(--pub-surface);border-radius:12px;
                      border:1px solid var(--pub-border);text-decoration:none;color:var(--pub-text);">
                🛍️ <?= e(t('nav.products')) ?>
            </a>
            <a href="/frontend/public/cart.php"
               style="display:flex;align-items:center;gap:10px;padding:16px 20px;
                      background:var(--pub-surface);border-radius:12px;
                      border:1px solid var(--pub-border);text-decoration:none;color:var(--pub-text);">
                🛒 <?= e(t('nav.cart')) ?>
            </a>
            <a href="/frontend/public/jobs.php"
               style="display:flex;align-items:center;gap:10px;padding:16px 20px;
                      background:var(--pub-surface);border-radius:12px;
                      border:1px solid var(--pub-border);text-decoration:none;color:var(--pub-text);">
                💼 <?= e(t('nav.jobs')) ?>
            </a>
        </div>

        <!-- Logout -->
        <div style="text-align:center;">
            <a href="/frontend/logout.php"
               class="pub-btn pub-btn--ghost"
               style="display:inline-flex;align-items:center;gap:8px;padding:12px 32px;">
                🚪 <?= e(t('nav.logout')) ?>
            </a>
        </div>

    </div>
</main>

<script>
// Clear localStorage on profile page to keep it in sync with server session
try {
    var sessUser = window.pubSessionUser;
    if (sessUser && sessUser.id) {
        localStorage.setItem('pubUser', JSON.stringify(sessUser));
    }
} catch (e) {}
</script>

<?php include __DIR__ . '/partials/footer.php'; ?>