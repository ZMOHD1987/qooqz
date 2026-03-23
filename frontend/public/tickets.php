<?php
/**
 * frontend/public/tickets.php
 * QOOQZ — Support Tickets Page
 * Allows logged-in users to view their tickets and submit new ones.
 */
require_once dirname(__DIR__) . '/includes/public_context.php';

// Require login
if (!$_isLoggedIn) {
    header('Location: /frontend/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI'] ?? '/frontend/public/tickets.php'));
    exit;
}

$GLOBALS['PUB_PAGE_TITLE'] = e(t('tickets.page_title')) . ' — QOOQZ';
include dirname(__DIR__) . '/partials/header.php';

$userId   = (int)($_SESSION['user_id'] ?? $_SESSION['user']['id'] ?? 0);
$tenantId = (int)($_SESSION['pub_tenant_id'] ?? 1);
$pdo      = pub_get_pdo();

// ── Load ticket categories ──────────────────────────────────────────────────
$categories = [];
if ($pdo) {
    try {
        $st = $pdo->prepare(
            "SELECT tc.id, COALESCE(tct.name, CAST(tc.id AS CHAR)) AS name
             FROM ticket_categories tc
             LEFT JOIN ticket_category_translations tct
                ON tct.category_id = tc.id AND tct.language_code = ?
             WHERE tc.tenant_id = ? AND tc.is_active = 1
             ORDER BY tc.id ASC"
        );
        $st->execute([$lang, $tenantId]);
        $categories = $st->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        // Fallback: ticket_category_translations table may not exist yet — use id as name.
        try {
            $st = $pdo->prepare(
                "SELECT id, CAST(id AS CHAR) AS name FROM ticket_categories
                 WHERE tenant_id = ? AND is_active = 1
                 ORDER BY id ASC"
            );
            $st->execute([$tenantId]);
            $categories = $st->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e2) { /* ignore – form will still show */ }
    }
}

// ── Load user's tickets ─────────────────────────────────────────────────────
$tickets   = [];
$filterStatus = in_array($_GET['status'] ?? '', ['open','pending','awaiting_customer','awaiting_vendor','in_progress','resolved','closed','cancelled'])
              ? ($_GET['status'] ?? '') : '';

if ($pdo && $userId) {
    try {
        $where  = 'WHERE st.tenant_id = ? AND st.user_id = ?';
        $params = [$tenantId, $userId];
        if ($filterStatus) {
            $where .= ' AND st.status = ?';
            $params[] = $filterStatus;
        }
        $st = $pdo->prepare(
            "SELECT st.id, st.subject, st.status, st.priority, st.created_at,
                    COALESCE(tct.name, CAST(tc.id AS CHAR)) AS category_name
             FROM support_tickets st
             LEFT JOIN ticket_categories tc  ON tc.id = st.category_id
             LEFT JOIN ticket_category_translations tct
                ON tct.category_id = tc.id AND tct.language_code = ?
             $where
             ORDER BY st.created_at DESC
             LIMIT 50"
        );
        $st->execute(array_merge([$lang], $params));
        $tickets = $st->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        // Fallback: ticket_category_translations table may not exist yet.
        try {
            $where  = 'WHERE st.tenant_id = ? AND st.user_id = ?';
            $params = [$tenantId, $userId];
            if ($filterStatus) {
                $where .= ' AND st.status = ?';
                $params[] = $filterStatus;
            }
            $st = $pdo->prepare(
                "SELECT st.id, st.subject, st.status, st.priority, st.created_at,
                        '' AS category_name
                 FROM support_tickets st
                 LEFT JOIN ticket_categories tc ON tc.id = st.category_id
                 $where
                 ORDER BY st.created_at DESC
                 LIMIT 50"
            );
            $st->execute($params);
            $tickets = $st->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e2) { /* show empty state */ }
    }
}

// ── Status badge helper ─────────────────────────────────────────────────────
function ticket_status_label(string $s): string {
    $map = [
        'open'              => 'status_open',
        'pending'           => 'status_pending',
        'awaiting_customer' => 'status_awaiting_customer',
        'awaiting_vendor'   => 'status_awaiting_vendor',
        'in_progress'       => 'status_in_progress',
        'resolved'          => 'status_resolved',
        'closed'            => 'status_closed',
        'cancelled'         => 'status_cancelled',
    ];
    return t('tickets.' . ($map[$s] ?? 'status_open'));
}
function ticket_status_color(string $s): string {
    return match($s) {
        'open', 'in_progress' => '#2563EB',
        'pending', 'awaiting_customer', 'awaiting_vendor' => '#D97706',
        'resolved', 'closed'  => '#16A34A',
        'cancelled'           => '#6B7280',
        default               => '#2563EB',
    };
}
function ticket_priority_color(string $p): string {
    return match($p) {
        'urgent' => '#DC2626',
        'high'   => '#EA580C',
        'normal' => '#2563EB',
        'low'    => '#6B7280',
        default  => '#6B7280',
    };
}
?>

<main class="pub-container" style="padding:40px 0 60px;">

    <!-- Breadcrumb -->
    <nav style="font-size:0.84rem;color:var(--pub-muted);margin-bottom:24px;" aria-label="breadcrumb">
        <a href="/frontend/public/index.php"><?= e(t('nav.home')) ?></a>
        <span style="margin:0 6px;">›</span>
        <span><?= e(t('tickets.page_title')) ?></span>
    </nav>

    <!-- Page header -->
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:28px;">
        <div>
            <h1 style="font-size:1.6rem;font-weight:700;margin:0;"><?= e(t('tickets.page_title')) ?></h1>
            <p style="font-size:0.92rem;color:var(--pub-muted);margin:4px 0 0;"><?= e(t('tickets.page_subtitle')) ?></p>
        </div>
        <button id="ticketNewBtn"
                onclick="document.getElementById('ticketFormWrap').style.display='block';this.style.display='none';window.scrollTo({top:0,behavior:'smooth'});"
                style="padding:10px 22px;background:var(--pub-primary);color:#fff;border:none;border-radius:8px;
                       font-size:0.95rem;font-weight:600;cursor:pointer;">
            + <?= e(t('tickets.new_ticket')) ?>
        </button>
    </div>

    <!-- New ticket form (hidden by default) -->
    <div id="ticketFormWrap" style="display:none;margin-bottom:32px;">
        <div style="background:var(--pub-surface);border:1px solid var(--pub-border);border-radius:12px;padding:28px;">
            <h2 style="font-size:1.1rem;font-weight:700;margin:0 0 20px;"><?= e(t('tickets.new_ticket')) ?></h2>
            <form id="ticketForm">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;">
                    <!-- Category -->
                    <div>
                        <label style="font-size:0.87rem;font-weight:600;display:block;margin-bottom:6px;">
                            <?= e(t('tickets.category')) ?> *
                        </label>
                        <select name="category_id" id="ticketCategory" required
                                style="width:100%;padding:10px 12px;border:1px solid var(--pub-border);
                                       border-radius:8px;font-size:0.93rem;background:var(--pub-bg);color:var(--pub-text);">
                            <option value=""><?= e(t('tickets.select_category')) ?></option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= (int)$cat['id'] ?>"><?= e($cat['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <!-- Priority -->
                    <div>
                        <label style="font-size:0.87rem;font-weight:600;display:block;margin-bottom:6px;">
                            <?= e(t('tickets.priority')) ?>
                        </label>
                        <select name="priority" id="ticketPriority"
                                style="width:100%;padding:10px 12px;border:1px solid var(--pub-border);
                                       border-radius:8px;font-size:0.93rem;background:var(--pub-bg);color:var(--pub-text);">
                            <option value="normal"><?= e(t('tickets.priority_normal')) ?></option>
                            <option value="low"><?= e(t('tickets.priority_low')) ?></option>
                            <option value="high"><?= e(t('tickets.priority_high')) ?></option>
                            <option value="urgent"><?= e(t('tickets.priority_urgent')) ?></option>
                        </select>
                    </div>
                </div>
                <!-- Subject -->
                <div style="margin-bottom:16px;">
                    <label style="font-size:0.87rem;font-weight:600;display:block;margin-bottom:6px;">
                        <?= e(t('tickets.subject')) ?> *
                    </label>
                    <input type="text" name="subject" id="ticketSubject" required maxlength="500"
                           placeholder="<?= e(t('tickets.subject_placeholder')) ?>"
                           style="width:100%;padding:10px 12px;border:1px solid var(--pub-border);
                                  border-radius:8px;font-size:0.93rem;box-sizing:border-box;
                                  background:var(--pub-bg);color:var(--pub-text);">
                </div>
                <!-- Description -->
                <div style="margin-bottom:20px;">
                    <label style="font-size:0.87rem;font-weight:600;display:block;margin-bottom:6px;">
                        <?= e(t('tickets.description')) ?> *
                    </label>
                    <textarea name="description" id="ticketDesc" required rows="5"
                              placeholder="<?= e(t('tickets.description_placeholder')) ?>"
                              style="width:100%;padding:10px 12px;border:1px solid var(--pub-border);
                                     border-radius:8px;font-size:0.93rem;resize:vertical;box-sizing:border-box;
                                     background:var(--pub-bg);color:var(--pub-text);"></textarea>
                </div>
                <!-- Buttons -->
                <div style="display:flex;gap:12px;justify-content:flex-end;">
                    <button type="button" onclick="document.getElementById('ticketFormWrap').style.display='none';
                                document.querySelector('button[onclick*=ticketFormWrap]').style.display='';"
                            style="padding:10px 22px;background:transparent;border:1px solid var(--pub-border);
                                   border-radius:8px;font-size:0.95rem;cursor:pointer;color:var(--pub-text);">
                        <?= e(t('tickets.cancel')) ?>
                    </button>
                    <button type="submit" id="ticketSubmitBtn"
                            style="padding:10px 22px;background:var(--pub-primary);color:#fff;border:none;
                                   border-radius:8px;font-size:0.95rem;font-weight:600;cursor:pointer;">
                        <?= e(t('tickets.submit')) ?>
                    </button>
                </div>
                <div id="ticketFormMsg" style="margin-top:12px;font-size:0.9rem;"></div>
            </form>
        </div>
    </div>

    <!-- Filter tabs -->
    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:20px;">
        <?php
        $tabs = [
            ''         => t('tickets.filter_all'),
            'open'     => t('tickets.filter_open'),
            'resolved' => t('tickets.filter_resolved'),
            'closed'   => t('tickets.filter_closed'),
        ];
        foreach ($tabs as $val => $label):
            $active = $filterStatus === $val;
        ?>
        <a href="?status=<?= urlencode($val) ?>"
           style="padding:7px 16px;border-radius:20px;font-size:0.87rem;font-weight:<?= $active?'700':'500'?>;
                  text-decoration:none;transition:all .2s;
                  background:<?= $active?'var(--pub-primary)':'var(--pub-surface)'?>;
                  color:<?= $active?'#fff':'var(--pub-text)'?>;
                  border:1px solid <?= $active?'transparent':'var(--pub-border)'?>;">
            <?= e($label) ?>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- Tickets list -->
    <?php if (empty($tickets)): ?>
    <div style="text-align:center;padding:60px 20px;color:var(--pub-muted);">
        <div style="font-size:3rem;margin-bottom:16px;">🎫</div>
        <p style="font-size:1rem;margin:0 0 16px;"><?= e(t('tickets.empty')) ?></p>
        <button onclick="document.getElementById('ticketFormWrap').style.display='block';
                         document.querySelector('button[onclick*=ticketFormWrap]').style.display='none';
                         window.scrollTo({top:0,behavior:'smooth'});"
                style="padding:10px 24px;background:var(--pub-primary);color:#fff;border:none;
                       border-radius:8px;font-size:0.95rem;font-weight:600;cursor:pointer;">
            + <?= e(t('tickets.create_first')) ?>
        </button>
    </div>
    <?php else: ?>
    <div style="display:flex;flex-direction:column;gap:12px;">
        <?php foreach ($tickets as $tk): ?>
        <div style="background:var(--pub-surface);border:1px solid var(--pub-border);border-radius:12px;
                    padding:18px 20px;display:flex;align-items:center;justify-content:space-between;
                    flex-wrap:wrap;gap:12px;">
            <div style="flex:1;min-width:0;">
                <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:6px;">
                    <span style="font-size:0.78rem;color:var(--pub-muted);">
                        <?= e(t('tickets.ticket_number')) ?><?= (int)$tk['id'] ?>
                    </span>
                    <?php if (!empty($tk['category_name'])): ?>
                    <span style="font-size:0.78rem;background:rgba(0,0,0,0.06);padding:2px 8px;border-radius:10px;">
                        <?= e($tk['category_name']) ?>
                    </span>
                    <?php endif; ?>
                </div>
                <div style="font-size:1rem;font-weight:600;margin-bottom:4px;
                            white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:500px;">
                    <?= e($tk['subject']) ?>
                </div>
                <div style="font-size:0.82rem;color:var(--pub-muted);">
                    <?= e(t('tickets.opened_on')) ?>: <?= e(date('Y-m-d', strtotime($tk['created_at']))) ?>
                </div>
            </div>
            <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                <!-- Priority -->
                <span style="font-size:0.78rem;font-weight:600;padding:3px 10px;border-radius:10px;
                             background:<?= e(ticket_priority_color($tk['priority'] ?? 'normal')) ?>22;
                             color:<?= e(ticket_priority_color($tk['priority'] ?? 'normal')) ?>;">
                    <?= e(t('tickets.priority_' . ($tk['priority'] ?? 'normal'))) ?>
                </span>
                <!-- Status -->
                <span style="font-size:0.82rem;font-weight:600;padding:4px 12px;border-radius:20px;
                             background:<?= e(ticket_status_color($tk['status'] ?? 'open')) ?>22;
                             color:<?= e(ticket_status_color($tk['status'] ?? 'open')) ?>;">
                    <?= e(ticket_status_label($tk['status'] ?? 'open')) ?>
                </span>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

</main>

<script>
(function () {
    var form = document.getElementById('ticketForm');
    if (!form) return;

    var tenantId = <?= (int)$tenantId ?>;
    var lang     = <?= json_encode($lang) ?>;

    /* ---- Load categories via API if the PHP-rendered list is empty ---- */
    function loadCategories() {
        var sel = document.getElementById('ticketCategory');
        if (!sel) return;
        // Already populated by PHP (more than just the placeholder option)
        if (sel.options.length > 1) return;
        fetch('/api/public/ticket_categories?tenant_id=' + tenantId + '&lang=' + encodeURIComponent(lang), {
            credentials: 'include'
        })
        .then(function (r) { return r.json(); })
        .then(function (res) {
            var cats = (res.data && Array.isArray(res.data.items)) ? res.data.items : [];
            cats.forEach(function (cat) {
                var opt = document.createElement('option');
                opt.value       = cat.id;
                opt.textContent = cat.name || cat.slug || String(cat.id);
                sel.appendChild(opt);
            });
        })
        .catch(function () { /* silently ignore; placeholder already shown */ });
    }

    /* Trigger category load when the new-ticket button shows the form */
    var showBtn = document.getElementById('ticketNewBtn');
    if (showBtn) {
        showBtn.addEventListener('click', loadCategories);
    }
    /* Also try immediately in case form is already visible */
    loadCategories();

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        var btn = document.getElementById('ticketSubmitBtn');
        var msg = document.getElementById('ticketFormMsg');
        btn.disabled = true;

        var data = {
            user_id:     <?= (int)$userId ?>,
            tenant_id:   tenantId,
            category_id: parseInt(document.getElementById('ticketCategory').value, 10) || null,
            priority:    document.getElementById('ticketPriority').value,
            subject:     document.getElementById('ticketSubject').value.trim(),
            description: document.getElementById('ticketDesc').value.trim()
        };

        fetch('/api/public/support_tickets?tenant_id=' + tenantId, {
            method: 'POST',
            credentials: 'include',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        })
        .then(function (r) { return r.json(); })
        .then(function (res) {
            if (res.success || res.id || (res.data && res.data.id)) {
                msg.style.color = '#16A34A';
                msg.textContent = <?= json_encode(t('tickets.success')) ?>;
                form.reset();
                setTimeout(function () { location.reload(); }, 1500);
            } else {
                throw new Error(res.message || res.error || 'error');
            }
        })
        .catch(function (err) {
            msg.style.color = '#DC2626';
            msg.textContent = <?= json_encode(t('tickets.error')) ?>;
            btn.disabled = false;
        });
    });
})();
</script>

<?php include dirname(__DIR__) . '/partials/footer.php'; ?>