<?php
declare(strict_types=1);
/**
 * api/routes/public_notifications.php
 * QOOQZ — Public Notifications API
 *
 * Serves /api/public/notifications/* requests.
 * Included by public.php when $first === 'notifications'.
 *
 * All list/count endpoints require an authenticated user session because
 * notifications are addressed to specific recipients via notification_recipients.
 *
 * Endpoints:
 *  GET  /api/public/notifications               — list notifications addressed to the logged-in user
 *  GET  /api/public/notifications/unread-count  — unread count for the logged-in user
 *  GET  /api/public/notifications/types         — active notification types (for icons/labels)
 *  POST /api/public/notifications/mark-read     — mark notification IDs as read in notification_recipients
 *  POST /api/public/notifications/mark-all-read — mark all unread notifications as read for the user
 *
 * Variables provided by the parent (public.php):
 *  $pdo        PDO|null
 *  $segments   array
 *  $notifTenantId   int|null
 *  $lang       string
 *  $page       int
 *  $per        int
 *  $offset     int
 */

$notifMethod = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$notifSub    = strtolower($segments[1] ?? '');

// Handle CORS preflight so POST mark-read / mark-all-read calls succeed
if ($notifMethod === 'OPTIONS') {
    if (!headers_sent()) {
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-Token, X-Requested-With');
        http_response_code(204);
    }
    exit;
}

// Resolve authenticated user from session (same pattern as rest of public.php)
$notifUserId = (int)($_SESSION['user_id'] ?? ($_SESSION['user']['id'] ?? 0));

// $tenantId from public.php is null when no ?tenant_id= param is present.
// Fall back to the session-cached value (set by public_context.php) or default to 1.
$notifTenantId = (int)($tenantId ?? $_SESSION['pub_tenant_id'] ?? 1) ?: 1;

/* ------------------------------------------------------------------
 * GET /api/public/notifications/types
 * Returns all active notification types (id, code, name, description).
 * Public — no login required.
 * ------------------------------------------------------------------ */
if ($notifMethod === 'GET' && $notifSub === 'types') {
    if (!$pdo instanceof PDO) { ResponseFormatter::error('DB unavailable', 503); exit; }
    try {
        $st = $pdo->prepare(
            'SELECT id, code, name, description FROM notification_types WHERE is_active = 1 ORDER BY id ASC'
        );
        $st->execute();
        $types = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        ResponseFormatter::success(['types' => $types, 'total' => count($types)]);
    } catch (Throwable $e) {
        error_log('[public_notifications] types error: ' . $e->getMessage());
        ResponseFormatter::error('Failed to load notification types', 500);
    }
    exit;
}

/* ------------------------------------------------------------------
 * GET /api/public/notifications/unread-count
 * Returns the unread count for the logged-in user.
 * Reads from notification_counters when available, falls back to counting
 * notification_recipients directly.
 *
 * Requires: login
 * ------------------------------------------------------------------ */
if ($notifMethod === 'GET' && $notifSub === 'unread-count') {
    if (!$notifUserId) { ResponseFormatter::error('Login required', 401); exit; }
    if (!$pdo instanceof PDO) { ResponseFormatter::error('DB unavailable', 503); exit; }
    try {
        // Prefer the pre-aggregated counter row
        $cntSt = $pdo->prepare(
            "SELECT unread_count FROM notification_counters
              WHERE tenant_id = ? AND recipient_type = 'user' AND recipient_id = ?
              LIMIT 1"
        );
        $cntSt->execute([$notifTenantId, $notifUserId]);
        $row = $cntSt->fetch(PDO::FETCH_ASSOC);

        if ($row !== false) {
            $count = (int)$row['unread_count'];
        } else {
            // Fallback: count directly from notification_recipients
            $fbSt = $pdo->prepare(
                "SELECT COUNT(*) FROM notification_recipients nr
                   JOIN notifications n ON n.id = nr.notification_id
                  WHERE nr.recipient_type = 'user'
                    AND nr.recipient_id   = ?
                    AND nr.is_read        = 0
                    AND n.tenant_id       = ?
                    AND (n.expires_at IS NULL OR n.expires_at > NOW())"
            );
            $fbSt->execute([$notifUserId, $notifTenantId]);
            $count = (int)$fbSt->fetchColumn();
        }
        ResponseFormatter::success(['unread_count' => $count]);
    } catch (Throwable $e) {
        error_log('[public_notifications] unread-count error: ' . $e->getMessage());
        ResponseFormatter::error('Failed to load unread count', 500);
    }
    exit;
}

/* ------------------------------------------------------------------
 * GET /api/public/notifications
 * Returns notifications addressed to the logged-in user via
 * notification_recipients (recipient_type='user', recipient_id=user_id).
 *
 * Query params:
 *   limit     int    default 20, max 100
 *   page      int    default 1
 *   type_code string filter by notification_type code
 *   priority  string filter by priority (low|normal|high|urgent)
 *   unread    1      show only unread notifications
 *
 * Requires: login
 * ------------------------------------------------------------------ */
if ($notifMethod === 'GET' && $notifSub === '') {
    if (!$notifUserId) { ResponseFormatter::error('Login required', 401); exit; }
    if (!$pdo instanceof PDO) { ResponseFormatter::error('DB unavailable', 503); exit; }

    $nLimit  = min(100, max(1, (int)($_GET['limit'] ?? $per)));
    $nPage   = max(1, (int)($_GET['page'] ?? $page));
    $nOffset = ($nPage - 1) * $nLimit;

    $where  = [
        "nr.recipient_type = 'user'",
        'nr.recipient_id   = ?',
        'n.tenant_id       = ?',
        '(n.expires_at IS NULL OR n.expires_at > NOW())',
    ];
    $params = [$notifUserId, $notifTenantId];

    if (!empty($_GET['type_code'])) {
        $where[]  = 'nt.code = ?';
        $params[] = (string)$_GET['type_code'];
    }
    $allowedPriorities = ['low', 'normal', 'high', 'urgent'];
    if (!empty($_GET['priority']) && in_array($_GET['priority'], $allowedPriorities, true)) {
        $where[]  = 'n.priority = ?';
        $params[] = $_GET['priority'];
    }
    if (!empty($_GET['unread'])) {
        $where[] = 'nr.is_read = 0';
    }

    $whereClause = implode(' AND ', $where);

    try {
        // Total count
        $cSt = $pdo->prepare(
            "SELECT COUNT(*)
               FROM notification_recipients nr
               JOIN notifications n          ON n.id  = nr.notification_id
          LEFT JOIN notification_types nt    ON nt.id = n.notification_type_id
              WHERE $whereClause"
        );
        $cSt->execute($params);
        $total = (int)$cSt->fetchColumn();

        // Items — use only positional ? placeholders to avoid SQLSTATE HY093 when
        // mixing positional and named parameters in MySQL PDO.
        $itemParams = $params;
        $itemParams[] = $nLimit;
        $itemParams[] = $nOffset;

        $qSt = $pdo->prepare(
            "SELECT n.id, n.title, n.message, n.sent_at, n.priority, n.data,
                    n.entity_id, n.sender_entity_id,
                    nr.is_read, nr.read_at,
                    nt.id   AS type_id,
                    nt.code AS type_code,
                    nt.name AS type_name
               FROM notification_recipients nr
               JOIN notifications n          ON n.id  = nr.notification_id
          LEFT JOIN notification_types nt    ON nt.id = n.notification_type_id
              WHERE $whereClause
           ORDER BY n.sent_at DESC
              LIMIT ? OFFSET ?"
        );
        $qSt->execute($itemParams);
        $items = $qSt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // Cast is_read to bool for clean JSON
        foreach ($items as &$item) {
            $item['is_read'] = (bool)$item['is_read'];
        }
        unset($item);

        ResponseFormatter::success([
            'items' => $items,
            'meta'  => [
                'total'       => $total,
                'page'        => $nPage,
                'per_page'    => $nLimit,
                'total_pages' => $total > 0 ? (int)ceil($total / $nLimit) : 0,
            ],
        ]);
    } catch (Throwable $e) {
        error_log('[public_notifications] list error: ' . $e->getMessage());
        ResponseFormatter::error('Failed to load notifications', 500);
    }
    exit;
}

/* ------------------------------------------------------------------
 * POST /api/public/notifications/mark-read
 * Marks specific notification IDs as read for the logged-in user.
 * Updates notification_recipients.is_read = 1 and notification_counters.
 *
 * Body: { "ids": [1, 2, 3] }
 * Requires: login
 * ------------------------------------------------------------------ */
if ($notifMethod === 'POST' && $notifSub === 'mark-read') {
    if (!$notifUserId) { ResponseFormatter::error('Login required', 401); exit; }
    if (!$pdo instanceof PDO) { ResponseFormatter::error('DB unavailable', 503); exit; }

    $raw  = file_get_contents('php://input');
    $body = $raw !== false && $raw !== '' ? (json_decode($raw, true) ?? []) : [];
    $ids  = array_values(array_filter(array_map('intval', (array)($body['ids'] ?? []))));

    if (empty($ids)) { ResponseFormatter::error('ids array is required', 422); exit; }

    try {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $upSt = $pdo->prepare(
            "UPDATE notification_recipients
                SET is_read = 1, read_at = NOW()
              WHERE notification_id IN ($placeholders)
                AND recipient_type = 'user'
                AND recipient_id   = ?
                AND is_read        = 0"
        );
        $upSt->execute(array_merge($ids, [$notifUserId]));
        $affected = $upSt->rowCount();

        // Recalculate unread_count from source to ensure consistency (non-fatal)
        if ($affected > 0) {
            try {
                $pdo->prepare(
                    "INSERT INTO notification_counters (tenant_id, recipient_type, recipient_id, unread_count)
                     VALUES (?, 'user', ?,
                         (SELECT COUNT(*) FROM notification_recipients nr2
                            JOIN notifications n2 ON n2.id = nr2.notification_id
                           WHERE nr2.recipient_type = 'user'
                             AND nr2.recipient_id   = ?
                             AND nr2.is_read        = 0
                             AND n2.tenant_id       = ?
                             AND (n2.expires_at IS NULL OR n2.expires_at > NOW()))
                     )
                     ON DUPLICATE KEY UPDATE
                         unread_count = VALUES(unread_count)"
                )->execute([$notifTenantId, $notifUserId, $notifUserId, $notifTenantId]);
            } catch (Throwable) {}
        }

        ResponseFormatter::success(['marked' => $ids, 'affected' => $affected]);
    } catch (Throwable $e) {
        error_log('[public_notifications] mark-read error: ' . $e->getMessage());
        ResponseFormatter::error('Failed to mark notifications as read', 500);
    }
    exit;
}

/* ------------------------------------------------------------------
 * POST /api/public/notifications/mark-all-read
 * Marks ALL unread notifications as read for the logged-in user and
 * resets notification_counters.unread_count to 0.
 *
 * Requires: login
 * ------------------------------------------------------------------ */
if ($notifMethod === 'POST' && $notifSub === 'mark-all-read') {
    if (!$notifUserId) { ResponseFormatter::error('Login required', 401); exit; }
    if (!$pdo instanceof PDO) { ResponseFormatter::error('DB unavailable', 503); exit; }

    try {
        $upSt = $pdo->prepare(
            "UPDATE notification_recipients nr
               JOIN notifications n ON n.id = nr.notification_id
                SET nr.is_read = 1, nr.read_at = NOW()
              WHERE nr.recipient_type = 'user'
                AND nr.recipient_id   = ?
                AND nr.is_read        = 0
                AND n.tenant_id       = ?
                AND (n.expires_at IS NULL OR n.expires_at > NOW())"
        );
        $upSt->execute([$notifUserId, $notifTenantId]);
        $affected = $upSt->rowCount();

        // Recalculate counter from source to ensure consistency (non-fatal)
        try {
            $pdo->prepare(
                "INSERT INTO notification_counters (tenant_id, recipient_type, recipient_id, unread_count)
                 VALUES (?, 'user', ?, 0)
                 ON DUPLICATE KEY UPDATE unread_count = 0"
            )->execute([$notifTenantId, $notifUserId]);
        } catch (Throwable) {}

        ResponseFormatter::success(['affected' => $affected]);
    } catch (Throwable $e) {
        error_log('[public_notifications] mark-all-read error: ' . $e->getMessage());
        ResponseFormatter::error('Failed to mark all notifications as read', 500);
    }
    exit;
}

ResponseFormatter::notFound('Notifications route not found: /' . implode('/', $segments));