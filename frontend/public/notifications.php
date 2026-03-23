<?php
/**
 * frontend/public/notifications.php
 * QOOQZ — My Notifications Page
 *
 * Displays notifications addressed to the logged-in user via
 * notification_recipients. Supports read/unread state, mark-all-read,
 * type and priority filters, and pagination.
 *
 * Requires: authenticated session.
 */
require_once dirname(__DIR__) . '/includes/public_context.php';

// Login required — notifications are user-specific
if (!$_isLoggedIn) {
    header('Location: /frontend/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI'] ?? '/frontend/public/notifications.php'));
    exit;
}

$ctx = $GLOBALS['PUB_CONTEXT'];
$GLOBALS['PUB_PAGE_TITLE'] = e(t('notifications.page_title', ['default' => 'My Notifications'])) . ' — QOOQZ';

// Resolve notification card style from DB card_styles (card_type='notification')
$_notifCardStyle = pub_card_inline_style('notification');
$_notifCardClass = pub_card_css_class('notification');

include dirname(__DIR__) . '/partials/header.php';

$userId = (int)($_SESSION['user_id'] ?? $_SESSION['user']['id'] ?? 0);
$pdo    = pub_get_pdo();

// Filters
$currentPage    = max(1, (int)($_GET['page'] ?? 1));
$perPage        = 20;
$typeCodeFilter = trim($_GET['type_code'] ?? '');
$priorityFilter = in_array($_GET['priority'] ?? '', ['low','normal','high','urgent']) ? $_GET['priority'] : '';
$unreadOnly     = !empty($_GET['unread']);

$notifItems = [];
$notifTotal = 0;
$notifTypes = [];
$unreadCount = 0;
$notifError  = false;

if ($pdo && $userId) {
    try {
        // Active notification types for the filter dropdown
        $stTypes = $pdo->prepare(
            'SELECT id, code, name FROM notification_types WHERE is_active = 1 ORDER BY id ASC'
        );
        $stTypes->execute();
        $notifTypes = $stTypes->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // Build WHERE clause joining notification_recipients → notifications
        $where  = [
            "nr.recipient_type = 'user'",
            'nr.recipient_id   = ?',
            'n.tenant_id       = ?',
            '(n.expires_at IS NULL OR n.expires_at > NOW())',
        ];
        $params = [$userId, $tenantId];

        if ($typeCodeFilter !== '') {
            $where[]  = 'nt.code = ?';
            $params[] = $typeCodeFilter;
        }
        if ($priorityFilter !== '') {
            $where[]  = 'n.priority = ?';
            $params[] = $priorityFilter;
        }
        if ($unreadOnly) {
            $where[] = 'nr.is_read = 0';
        }

        $whereClause = implode(' AND ', $where);
        $offset      = ($currentPage - 1) * $perPage;

        // Total count (for pagination)
        $cSt = $pdo->prepare(
            "SELECT COUNT(*)
               FROM notification_recipients nr
               JOIN notifications n          ON n.id  = nr.notification_id
          LEFT JOIN notification_types nt    ON nt.id = n.notification_type_id
              WHERE $whereClause"
        );
        $cSt->execute($params);
        $notifTotal = (int)$cSt->fetchColumn();

        // Items — embed LIMIT/OFFSET as cast integers to avoid PDO type-casting issues
        $iSt = $pdo->prepare(
            "SELECT n.id, n.title, n.message, n.sent_at, n.priority,
                    nr.is_read, nr.read_at,
                    nt.code AS type_code, nt.name AS type_name
               FROM notification_recipients nr
               JOIN notifications n          ON n.id  = nr.notification_id
          LEFT JOIN notification_types nt    ON nt.id = n.notification_type_id
              WHERE $whereClause
           ORDER BY n.sent_at DESC
              LIMIT " . (int)$perPage . " OFFSET " . (int)$offset
        );
        $iSt->execute($params);
        $notifItems = $iSt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    } catch (Throwable $e) {
        $notifError = true;
        error_log('[notifications.php] ' . $e->getMessage());
    }

    // Unread count — separate try-catch so a failure here does not hide the notifications list
    if (!$notifError) {
        try {
            $ucSt = $pdo->prepare(
                "SELECT COUNT(*)
                   FROM notification_recipients nr2
                   JOIN notifications n2 ON n2.id = nr2.notification_id
                  WHERE nr2.recipient_type = 'user'
                    AND nr2.recipient_id   = ?
                    AND nr2.is_read        = 0
                    AND n2.tenant_id       = ?
                    AND (n2.expires_at IS NULL OR n2.expires_at > NOW())"
            );
            $ucSt->execute([$userId, $tenantId]);
            $unreadCount = (int)$ucSt->fetchColumn();
        } catch (Throwable $e) {
            $unreadCount = 0;
            error_log('[notifications.php] unread-count: ' . $e->getMessage());
        }
    }
}

$totalPages = $notifTotal > 0 ? (int)ceil($notifTotal / $perPage) : 0;

// Type → emoji icon
function notif_icon(string $code): string {
    $icons = [
        'order' => '📦', 'payment' => '💳', 'shipment' => '🚚', 'return' => '↩️',
        'review' => '⭐', 'promotion' => '🎉', 'system' => '⚙️', 'entities' => '🏢',
        'support' => '🆘', 'wallet' => '💰', 'loyalty' => '🏅',
        'audit_completed' => '✅', 'audit_rejected' => '❌',
    ];
    return $icons[$code] ?? '🔔';
}

// Priority dot
function notif_priority_label(string $p): string {
    return ['low' => '🔵', 'normal' => '⚪', 'high' => '🟠', 'urgent' => '🔴'][$p] ?? '';
}

// Build filter query string preserving current filters
function notif_filter_qs(array $extra = []): string {
    $base = [];
    if (!empty($_GET['type_code'])) $base['type_code'] = $_GET['type_code'];
    if (!empty($_GET['priority']))  $base['priority']  = $_GET['priority'];
    if (!empty($_GET['unread']))    $base['unread']    = '1';
    if (!empty($_GET['tenant_id'])) $base['tenant_id'] = (int)$_GET['tenant_id'];
    // Remove keys explicitly set to null (used to clear individual filters)
    $merged = array_filter(array_merge($base, $extra), fn($v) => $v !== null && $v !== '');
    return '?' . http_build_query($merged);
}
?>

<main class="pub-container" style="padding-top:24px;padding-bottom:48px;">

    <!-- Page header -->
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;margin-bottom:20px;">
        <div>
            <h1 style="margin:0;font-size:1.4rem;">🔔 <?= e(t('notifications.page_title', ['default' => 'My Notifications'])) ?></h1>
            <?php if ($unreadCount > 0): ?>
            <p style="color:var(--pub-muted);font-size:0.85rem;margin:4px 0 0;">
                <?= $unreadCount ?> <?= e(t('notifications.unread_count', ['default' => 'unread'])) ?>
            </p>
            <?php endif; ?>
        </div>
        <?php if ($unreadCount > 0): ?>
        <button type="button" class="pub-btn pub-btn--primary pub-btn--sm" id="pubMarkAllReadBtn">
            ✓ <?= e(t('notifications.mark_all_read', ['default' => 'Mark all as read'])) ?>
        </button>
        <?php endif; ?>
    </div>

    <!-- Filters bar -->
    <form method="get" action="<?= htmlspecialchars($_SERVER['PHP_SELF'] ?? '', ENT_QUOTES) ?>"
          style="margin-bottom:24px;display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;">
        <?php if (!empty($_GET['tenant_id'])): ?>
            <input type="hidden" name="tenant_id" value="<?= (int)$_GET['tenant_id'] ?>">
        <?php endif; ?>
        <?php if (!empty($_GET['lang'])): ?>
            <input type="hidden" name="lang" value="<?= e($_GET['lang']) ?>">
        <?php endif; ?>

        <!-- Unread-only toggle -->
        <div>
            <label class="pub-label">&nbsp;</label>
            <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:0.88rem;">
                <input type="checkbox" name="unread" value="1" onchange="this.form.submit()" <?= $unreadOnly ? 'checked' : '' ?>>
                <?= e(t('notifications.unread_only', ['default' => 'Unread only'])) ?>
            </label>
        </div>

        <!-- Type filter -->
        <?php if (!empty($notifTypes)): ?>
        <div>
            <label class="pub-label"><?= e(t('notifications.filter_type', ['default' => 'Type'])) ?></label>
            <select name="type_code" class="pub-select" style="min-width:140px;" onchange="this.form.submit()">
                <option value=""><?= e(t('notifications.all_types', ['default' => 'All types'])) ?></option>
                <?php foreach ($notifTypes as $nt): ?>
                <option value="<?= e($nt['code']) ?>" <?= $typeCodeFilter === $nt['code'] ? 'selected' : '' ?>>
                    <?= notif_icon($nt['code']) ?> <?= e(t('notifications.type_' . $nt['code'], ['default' => $nt['name']])) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>

        <!-- Priority filter -->
        <div>
            <label class="pub-label"><?= e(t('notifications.filter_priority', ['default' => 'Priority'])) ?></label>
            <select name="priority" class="pub-select" style="min-width:120px;" onchange="this.form.submit()">
                <option value=""><?= e(t('notifications.all_priorities', ['default' => 'All'])) ?></option>
                <option value="urgent" <?= $priorityFilter === 'urgent' ? 'selected' : '' ?>>🔴 <?= e(t('notifications.priority_urgent', ['default' => 'Urgent'])) ?></option>
                <option value="high"   <?= $priorityFilter === 'high'   ? 'selected' : '' ?>>🟠 <?= e(t('notifications.priority_high',   ['default' => 'High'])) ?></option>
                <option value="normal" <?= $priorityFilter === 'normal' ? 'selected' : '' ?>>⚪ <?= e(t('notifications.priority_normal', ['default' => 'Normal'])) ?></option>
                <option value="low"    <?= $priorityFilter === 'low'    ? 'selected' : '' ?>>🔵 <?= e(t('notifications.priority_low',    ['default' => 'Low'])) ?></option>
            </select>
        </div>

        <!-- Explicit submit button so filters work without JavaScript -->
        <button type="submit" class="pub-btn pub-btn--primary pub-btn--sm" style="align-self:flex-end;">
            🔍 <?= e(t('notifications.apply_filters', ['default' => 'Apply'])) ?>
        </button>

        <?php if ($typeCodeFilter || $priorityFilter || $unreadOnly): ?>
        <a href="<?= notif_filter_qs(['type_code' => null, 'priority' => null, 'unread' => null, 'page' => null]) ?>"
           class="pub-btn pub-btn--ghost pub-btn--sm" style="align-self:flex-end;">
            ✕ <?= e(t('notifications.reset_filters', ['default' => 'Reset'])) ?>
        </a>
        <?php endif; ?>
    </form>

    <?php if ($notifError): ?>
    <div class="pub-alert pub-alert--error" style="margin-bottom:20px;">
        ⚠️ <?= e(t('notifications.load_error', ['default' => 'Could not load notifications. Please try again.'])) ?>
    </div>
    <?php elseif (empty($notifItems)): ?>
    <div style="text-align:center;padding:60px 20px;">
        <div style="font-size:3rem;margin-bottom:16px;">🔔</div>
        <p style="color:var(--pub-muted);font-size:1rem;">
            <?= e(t('notifications.empty', ['default' => 'No notifications yet'])) ?>
        </p>
    </div>
    <?php else: ?>

    <!-- Notification cards grid -->
    <div class="pub-notif-page-list" id="pubNotifPageList">
        <?php foreach ($notifItems as $n):
            $nId    = (int)$n['id'];
            $nTitle = $n['title'] ?? '';
            $nMsg   = $n['message'] ?? '';
            $nTime  = isset($n['sent_at']) ? substr((string)$n['sent_at'], 0, 16) : '';
            $nCode  = $n['type_code'] ?? '';
            $nPrio  = $n['priority'] ?? 'normal';
            $nRead  = (bool)$n['is_read'];
            $nIcon  = notif_icon($nCode);
            $nPLbl  = notif_priority_label($nPrio);
            // Use DB card style if present; otherwise fall back to CSS-only classes
            $cardBaseStyle = $_notifCardStyle;
        ?>
        <div class="pub-notif-card<?= $_notifCardClass ? ' ' . $_notifCardClass : '' ?><?= $nRead ? ' pub-notif-read' : ' pub-notif-unread' ?>"
             data-id="<?= $nId ?>"
             data-priority="<?= e($nPrio) ?>"
             <?= $cardBaseStyle ? 'style="' . e($cardBaseStyle) . '"' : '' ?>>

            <!-- Card top row: icon + title + badges -->
            <div class="pub-notif-card-header">
                <span class="pub-notif-card-icon" aria-hidden="true"><?= $nIcon ?></span>
                <div class="pub-notif-card-title-wrap">
                    <p class="pub-notif-card-title"><?= e($nTitle) ?></p>
                    <?php if ($nPLbl): ?>
                    <span class="pub-notif-card-prio" title="<?= e($nPrio) ?>"><?= $nPLbl ?></span>
                    <?php endif; ?>
                    <?php if (!$nRead): ?>
                    <span class="pub-notif-badge-unread" title="<?= e(t('notifications.unread', ['default' => 'Unread'])) ?>"></span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Message body -->
            <?php if ($nMsg): ?>
            <p class="pub-notif-card-msg"><?= e($nMsg) ?></p>
            <?php endif; ?>

            <!-- Card footer: timestamp + per-item mark-as-read button -->
            <div class="pub-notif-card-footer">
                <?php if ($nTime): ?>
                <span class="pub-notif-card-time">🕐 <?= e($nTime) ?></span>
                <?php endif; ?>
                <?php if (!$nRead): ?>
                <button type="button"
                        class="pub-btn pub-btn--ghost pub-btn--xs pub-notif-mark-read-btn"
                        data-id="<?= $nId ?>"
                        aria-label="<?= e(t('notifications.mark_read', ['default' => 'Mark as read'])) ?>">
                    ✓ <?= e(t('notifications.mark_read', ['default' => 'Mark as read'])) ?>
                </button>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <nav class="pub-pagination" style="margin-top:32px;" aria-label="Pagination">
        <?php if ($currentPage > 1): ?>
        <a class="pub-page-btn" href="<?= notif_filter_qs(['page' => $currentPage - 1]) ?>">‹</a>
        <?php endif; ?>

        <?php for ($p = max(1, $currentPage - 2); $p <= min($totalPages, $currentPage + 2); $p++): ?>
        <a class="pub-page-btn<?= $p === $currentPage ? ' active' : '' ?>"
           href="<?= notif_filter_qs(['page' => $p]) ?>"><?= $p ?></a>
        <?php endfor; ?>

        <?php if ($currentPage < $totalPages): ?>
        <a class="pub-page-btn" href="<?= notif_filter_qs(['page' => $currentPage + 1]) ?>">›</a>
        <?php endif; ?>
    </nav>
    <?php endif; ?>

    <?php endif; ?>
</main>

<style>
/* Notification card grid */
.pub-notif-page-list {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 16px;
}
@media (max-width: 480px) {
    .pub-notif-page-list { grid-template-columns: 1fr; }
}

/* Individual notification card */
.pub-notif-card {
    display: flex;
    flex-direction: column;
    gap: 8px;
    /* CSS fallback for when DB card_styles has no 'notification' entry */
    background-color: #fffbe6;
    border: 1px solid #f59e0b;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(245,158,11,0.2);
    padding: 14px;
    transition: transform 0.15s, box-shadow 0.15s;
}
.pub-notif-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(0,0,0,0.12) !important;
}
.pub-notif-card.pub-notif-unread { border-inline-start-width: 3px !important; }
.pub-notif-card[data-priority="urgent"] { border-inline-start-color: #ef4444 !important; }
.pub-notif-card[data-priority="high"]   { border-inline-start-color: #f97316 !important; }
.pub-notif-card[data-priority="normal"] { border-inline-start-color: #f59e0b !important; }
.pub-notif-card[data-priority="low"]    { border-inline-start-color: #6b7280 !important; }

/* Card inner elements */
.pub-notif-card-header {
    display: flex;
    align-items: flex-start;
    gap: 10px;
}
.pub-notif-card-icon { font-size: 1.5rem; flex-shrink: 0; }
.pub-notif-card-title-wrap {
    flex: 1;
    display: flex;
    align-items: center;
    gap: 6px;
    flex-wrap: wrap;
}
.pub-notif-card-title {
    font-size: 0.93rem;
    font-weight: 600;
    color: var(--pub-text, #111);
    margin: 0;
    flex: 1;
    min-width: 0;
    line-height: 1.4;
}
.pub-notif-read .pub-notif-card-title {
    font-weight: 400;
    color: var(--pub-muted, #6b7280);
}
.pub-notif-card-prio  { font-size: 0.85rem; }
.pub-notif-badge-unread {
    width: 8px; height: 8px;
    border-radius: 50%;
    background: #f59e0b;
    flex-shrink: 0;
}
.pub-notif-card-msg {
    font-size: 0.84rem;
    color: var(--pub-muted, #6b7280);
    margin: 0;
    line-height: 1.5;
}
.pub-notif-card-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    margin-top: 4px;
    flex-wrap: wrap;
}
.pub-notif-card-time {
    font-size: 0.73rem;
    color: var(--pub-muted, #9ca3af);
}
/* Extra-small button variant */
.pub-btn--xs {
    padding: 3px 10px;
    font-size: 0.75rem;
    border-radius: 6px;
}
</style>

<script>
(function () {
    // Helper: visually mark a single notification card as read
    function markItemRead(el) {
        el.classList.remove('pub-notif-unread');
        el.classList.add('pub-notif-read');
        var dot = el.querySelector('.pub-notif-badge-unread');
        if (dot) dot.remove();
        var btn = el.querySelector('.pub-notif-mark-read-btn');
        if (btn) btn.remove();
    }

    // API call to mark specific IDs as read
    function apiMarkRead(ids, onSuccess) {
        fetch('/api/public/notifications/mark-read', {
            method: 'POST',
            credentials: 'include',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ ids: ids })
        })
        .then(function(r) { return r.json(); })
        .then(function(d) { if (d && d.success) onSuccess(); })
        .catch(function () {});
    }

    // Mark-all-read button
    var markAllBtn = document.getElementById('pubMarkAllReadBtn');
    if (markAllBtn) {
        markAllBtn.addEventListener('click', function () {
            markAllBtn.disabled = true;
            fetch('/api/public/notifications/mark-all-read', {
                method: 'POST',
                credentials: 'include',
                headers: { 'Content-Type': 'application/json' }
            })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (d && d.success) {
                    document.querySelectorAll('#pubNotifPageList .pub-notif-unread').forEach(markItemRead);
                    markAllBtn.style.display = 'none';
                }
            })
            .catch(function () {})
            .finally(function () { markAllBtn.disabled = false; });
        });
    }

    // Per-card "Mark as read" buttons
    document.querySelectorAll('.pub-notif-mark-read-btn').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.stopPropagation();
            var card = btn.closest('[data-id]');
            var id   = card ? parseInt(card.dataset.id, 10) : 0;
            if (!id) return;
            btn.disabled = true;
            apiMarkRead([id], function () { markItemRead(card); });
        });
    });
})();
</script>

<?php include dirname(__DIR__) . '/partials/footer.php'; ?>