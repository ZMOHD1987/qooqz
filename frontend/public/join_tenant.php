<?php
declare(strict_types=1);
/**
 * frontend/public/join_tenant.php
 * QOOQZ — Create a new Tenant workspace
 */

require_once dirname(__DIR__) . '/includes/public_context.php';

$ctx  = $GLOBALS['PUB_CONTEXT'];
$lang = $ctx['lang'];

$GLOBALS['PUB_APP_NAME']   = 'QOOQZ';
$GLOBALS['PUB_BASE_PATH']  = '/frontend/public';
$GLOBALS['PUB_PAGE_TITLE'] = t('join_tenant.page_title') . ' — QOOQZ';

$user       = $ctx['user'] ?? [];
$isLoggedIn = !empty($user['id']);

include dirname(__DIR__) . '/partials/header.php';
?>

<div class="pub-join-wrap">

    <h1>🌐 <?= e(t('join_tenant.page_title')) ?></h1>
    <p class="pub-join-sub"><?= e(t('join_tenant.subtitle')) ?></p>

    <?php if (!$isLoggedIn): ?>
    <div class="pub-empty" style="text-align:center;padding:32px 0;">
        <div class="pub-empty-icon">🔒</div>
        <p><?= e(t('auth.login_required')) ?></p>
        <a href="/frontend/login.php?redirect=<?= urlencode('/frontend/public/join_tenant.php') ?>"
           class="pub-btn pub-btn--primary" style="margin-top:12px;display:inline-block;">
            <?= e(t('auth.login')) ?>
        </a>
    </div>
    <?php else: ?>

    <form id="joinTenantForm">
        <div class="pub-join-fieldset">

            <div class="pub-join-group full">
                <label for="jtName"><?= e(t('join_tenant.name')) ?> *</label>
                <input id="jtName" name="name" type="text" required
                       placeholder="<?= e(t('join_tenant.name_ph')) ?>">
            </div>

            <div class="pub-join-group full">
                <label for="jtDomain"><?= e(t('join_tenant.domain')) ?></label>
                <input id="jtDomain" name="domain" type="text"
                       placeholder="<?= e(t('join_tenant.domain_ph')) ?>">
            </div>

        </div>

        <button type="submit" class="pub-btn pub-btn--primary" style="width:100%;padding:12px;">
            <?= e(t('join_tenant.submit')) ?>
        </button>

        <div id="joinTenantResult" class="pub-join-result" style="display:none;"></div>
    </form>

    <script>
    document.getElementById('joinTenantForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        const btn = this.querySelector('[type="submit"]');
        const res = document.getElementById('joinTenantResult');
        btn.disabled = true;
        btn.textContent = <?= json_encode(t('join_tenant.submitting')) ?>;
        res.style.display = 'none';

        try {
            const fd = new FormData(this);
            const r  = await fetch('/api/public/register/tenant', {
                method: 'POST', body: fd, credentials: 'include'
            });
            const data = await r.json();
            if (r.ok && data.data && data.data.ok) {
                res.className = 'pub-join-result success';
                res.textContent = <?= json_encode(t('join_tenant.success')) ?>;
                res.style.display = 'block';
                this.reset();
            } else {
                res.className = 'pub-join-result error';
                res.textContent = data.message || <?= json_encode(t('join_tenant.error')) ?>;
                res.style.display = 'block';
            }
        } catch (_) {
            res.className = 'pub-join-result error';
            res.textContent = <?= json_encode(t('join_tenant.error')) ?>;
            res.style.display = 'block';
        }
        btn.disabled = false;
        btn.textContent = <?= json_encode(t('join_tenant.submit')) ?>;
    });
    </script>

    <?php endif; ?>

</div>

<?php include dirname(__DIR__) . '/partials/footer.php'; ?>
