<?php
declare(strict_types=1);
/**
 * frontend/public/join_entity.php
 * QOOQZ — Register as a Vendor / Entity (public registration form)
 * Status starts as "pending"; admin approves from the admin panel.
 */

require_once dirname(__DIR__) . '/includes/public_context.php';

$ctx  = $GLOBALS['PUB_CONTEXT'];
$lang = $ctx['lang'];
$dir  = $ctx['dir'];

$GLOBALS['PUB_APP_NAME']   = 'QOOQZ';
$GLOBALS['PUB_BASE_PATH']  = '/frontend/public';
$GLOBALS['PUB_PAGE_TITLE'] = t('join_entity.page_title') . ' — QOOQZ';

$user = $ctx['user'] ?? [];
$isLoggedIn = !empty($user['id']);
$userId = (int)($user['id'] ?? 0);

/* Load tenants user belongs to (for the tenant selector) */
$userTenants = [];
if ($isLoggedIn) {
    $pdo = pub_get_pdo();
    if ($pdo) {
        try {
            $st = $pdo->prepare(
                'SELECT t.id, t.name FROM tenants t
                 INNER JOIN tenant_users tu ON tu.tenant_id = t.id AND tu.user_id = ? AND tu.is_active = 1
                 WHERE t.status = \'active\'
                 ORDER BY t.name ASC LIMIT 50'
            );
            $st->execute([$userId]);
            $userTenants = $st->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable) {}
    }
    /* Fallback: use session tenant_users data */
    if (empty($userTenants) && !empty($_SESSION['tenant_users'])) {
        $tu = $_SESSION['tenant_users'];
        if (isset($tu['tenant_id'])) {
            /* single tenant (flat array in session) */
            $userTenants = [['id' => $tu['tenant_id'], 'name' => $tu['tenant_name'] ?? 'Tenant #' . $tu['tenant_id']]];
        }
    }
}

/* Default tenant_id (pre-select) */
$defaultTenantId = (int)($_SESSION['pub_tenant_id'] ?? $_SESSION['tenant_id'] ?? (isset($userTenants[0]) ? $userTenants[0]['id'] : 1));

include dirname(__DIR__) . '/partials/header.php';
?>

<div class="pub-join-wrap">

    <h1>🏪 <?= e(t('join_entity.page_title')) ?></h1>
    <p class="pub-join-sub"><?= e(t('join_entity.subtitle')) ?></p>

    <!-- Pending-review notice -->
    <div class="pub-join-notice">
        ℹ️ <?= e(t('join_entity.notice')) ?>
    </div>

    <?php if (!$isLoggedIn): ?>
    <!-- Must be logged in -->
    <div class="pub-empty" style="text-align:center;padding:32px 0;">
        <div class="pub-empty-icon">🔒</div>
        <p><?= e(t('auth.login_required')) ?></p>
        <a href="/frontend/login.php?redirect=<?= urlencode('/frontend/public/join_entity.php') ?>"
           class="pub-btn pub-btn--primary" style="margin-top:12px;display:inline-block;">
            <?= e(t('auth.login')) ?>
        </a>
    </div>
    <?php else: ?>

    <form id="joinEntityForm">
        <div class="pub-join-fieldset">

            <?php if (count($userTenants) > 1): ?>
            <!-- Tenant selector — only shown when user belongs to multiple tenants -->
            <div class="pub-join-group full">
                <label for="jTenant"><?= e(t('join_entity.tenant')) ?> *</label>
                <select id="jTenant" name="tenant_id" required>
                    <?php foreach ($userTenants as $ut): ?>
                    <option value="<?= (int)$ut['id'] ?>"
                        <?= ((int)$ut['id'] === $defaultTenantId) ? 'selected' : '' ?>>
                        <?= e($ut['name']) ?> (#<?= (int)$ut['id'] ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php else: ?>
            <!-- Single tenant: hidden field -->
            <input type="hidden" name="tenant_id" value="<?= $defaultTenantId ?>">
            <?php endif; ?>

            <div class="pub-join-group full">
                <label for="jStore"><?= e(t('join_entity.store_name')) ?> *</label>
                <input id="jStore" name="store_name" type="text" required
                       placeholder="<?= e(t('join_entity.store_name_ph')) ?>">
            </div>

            <div class="pub-join-group">
                <label for="jSlug"><?= e(t('join_entity.slug')) ?></label>
                <input id="jSlug" name="slug" type="text"
                       placeholder="<?= e(t('join_entity.slug_ph')) ?>"
                       pattern="[a-z0-9\-]*">
            </div>

            <div class="pub-join-group">
                <label for="jVtype"><?= e(t('join_entity.vendor_type')) ?></label>
                <select id="jVtype" name="vendor_type">
                    <option value="product_seller"><?= e(t('join_entity.vtype_product_seller')) ?></option>
                    <option value="service_provider"><?= e(t('join_entity.vtype_service_provider')) ?></option>
                    <option value="both"><?= e(t('join_entity.vtype_both')) ?></option>
                </select>
            </div>

            <div class="pub-join-group">
                <label for="jStype"><?= e(t('join_entity.store_type')) ?></label>
                <select id="jStype" name="store_type">
                    <option value="individual"><?= e(t('join_entity.stype_individual')) ?></option>
                    <option value="company"><?= e(t('join_entity.stype_company')) ?></option>
                    <option value="brand"><?= e(t('join_entity.stype_brand')) ?></option>
                </select>
            </div>

            <div class="pub-join-group">
                <label for="jPhone"><?= e(t('join_entity.phone')) ?> *</label>
                <input id="jPhone" name="phone" type="tel" required
                       placeholder="<?= e(t('join_entity.phone_ph')) ?>">
            </div>

            <div class="pub-join-group">
                <label for="jEmail"><?= e(t('join_entity.email')) ?> *</label>
                <input id="jEmail" name="email" type="email" required
                       placeholder="<?= e(t('join_entity.email_ph')) ?>">
            </div>

            <div class="pub-join-group full">
                <label for="jWeb"><?= e(t('join_entity.website')) ?></label>
                <input id="jWeb" name="website_url" type="url"
                       placeholder="<?= e(t('join_entity.website_ph')) ?>">
            </div>

        </div>

        <button type="submit" class="pub-btn pub-btn--primary" style="width:100%;padding:12px;">
            <?= e(t('join_entity.submit')) ?>
        </button>

        <div id="joinEntityResult" class="pub-join-result" style="display:none;"></div>
    </form>

    <script>
    document.getElementById('joinEntityForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        const btn = this.querySelector('[type="submit"]');
        const res = document.getElementById('joinEntityResult');
        btn.disabled = true;
        btn.textContent = <?= json_encode(t('join_entity.submitting')) ?>;
        res.style.display = 'none';

        try {
            const fd = new FormData(this);
            const r  = await fetch('/api/public/register/entity', {
                method: 'POST', body: fd, credentials: 'include'
            });
            const data = await r.json();
            if (r.ok && data.data && data.data.ok) {
                res.className = 'pub-join-result success';
                res.textContent = <?= json_encode(t('join_entity.success')) ?>;
                res.style.display = 'block';
                this.reset();
            } else {
                res.className = 'pub-join-result error';
                res.textContent = data.message || <?= json_encode(t('join_entity.error')) ?>;
                res.style.display = 'block';
            }
        } catch (_) {
            res.className = 'pub-join-result error';
            res.textContent = <?= json_encode(t('join_entity.error')) ?>;
            res.style.display = 'block';
        }
        btn.disabled = false;
        btn.textContent = <?= json_encode(t('join_entity.submit')) ?>;
    });
    </script>

    <?php endif; ?>

</div>

<?php include dirname(__DIR__) . '/partials/footer.php'; ?>