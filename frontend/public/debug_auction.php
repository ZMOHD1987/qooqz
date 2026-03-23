<?php
/**
 * QOOQZ Auction Debug — REMOVE AFTER USE
 * Token: ?token=qooqz_debug_2026
 */
if (($_GET['token'] ?? '') !== 'qooqz_debug_2026') {
    http_response_code(403);
    echo '<!DOCTYPE html><html><head><title>Auction Debug — Token Required</title>'
        . '<style>body{font:14px monospace;padding:32px;background:#111;color:#eee}'
        . 'form{margin-top:16px}input{padding:8px;width:320px;background:#222;color:#eee;border:1px solid #444}'
        . 'button{padding:8px 16px;background:#2563eb;color:#fff;border:0;cursor:pointer;margin-left:8px}'
        . '</style></head><body>'
        . '<h2>🔐 QOOQZ Auction Debug</h2>'
        . '<p>This diagnostic tool requires a token.</p>'
        . '<form method="get"><input name="token" placeholder="Enter debug token" required>'
        . '<button type="submit">Run Diagnostics</button></form>'
        . '<p style="color:#888;font-size:12px;margin-top:24px">Token is: <code>qooqz_debug_2026</code><br>'
        . 'Or visit: <code>' . htmlspecialchars($_SERVER['PHP_SELF'] ?? '/debug_auction.php') . '?token=qooqz_debug_2026</code></p>'
        . '</body></html>';
    exit;
}
?><!DOCTYPE html><html><head><title>Auction Debug</title>
<style>body{font:14px monospace;padding:16px;background:#111;color:#eee}
pre{background:#1a1a1a;padding:8px;border-radius:4px;white-space:pre-wrap;word-break:break-all}
.ok{color:#4f4}  .warn{color:#ff4}  .err{color:#f44}
h3{color:#8bf;margin-top:1.5em}
</style></head><body>
<h2>🔍 QOOQZ Auction Debug</h2>
<p><em>⚠️ DELETE this file after use: /frontend/public/debug_auction.php</em></p>

<?php
// ── Step 1: Load db.php directly ─────────────────────────────────────────────
echo "<h3>Step 1: DB Config File</h3>";
$dbConf = null;
$docRoot = $_SERVER['DOCUMENT_ROOT'] ?? '';
foreach ([
    $docRoot . '/api/shared/config/db.php',
    dirname(__DIR__) . '/../api/shared/config/db.php',
    realpath(dirname(__DIR__) . '/../api/shared/config/db.php'),
] as $c) {
    if ($c && is_readable($c)) {
        $tmp = require $c;
        if (is_array($tmp)) { $dbConf = $tmp; echo "<p class='ok'>✅ Found: $c</p>"; break; }
    }
}
if (!$dbConf) { echo "<p class='err'>❌ db.php not found</p>"; exit; }
$safe = $dbConf; $safe['pass'] = '(hidden)';
echo "<pre>" . print_r($safe, true) . "</pre>";

// ── Step 2: Direct PDO ───────────────────────────────────────────────────────
echo "<h3>Step 2: PDO Connection</h3>";
try {
    $pdo2 = new PDO(
        sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $dbConf['host'], (int)($dbConf['port'] ?? 3306), $dbConf['name'], $dbConf['charset'] ?? 'utf8mb4'),
        $dbConf['user'], $dbConf['pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
    echo "<p class='ok'>✅ PDO connected</p>";
} catch (Throwable $e) {
    echo "<p class='err'>❌ PDO failed: " . htmlspecialchars($e->getMessage()) . "</p>"; exit;
}

// ── Step 3: Basic queries ────────────────────────────────────────────────────
echo "<h3>Step 3: Basic Queries</h3>";

// 3a: Total auctions
$cnt = $pdo2->query('SELECT COUNT(*) as n FROM auctions')->fetch()['n'] ?? 0;
echo "<p class='ok'>✅ Total auctions: $cnt</p>";

// 3b: Show all auctions raw
$rows = $pdo2->query("SELECT id, status, auction_type, title, slug, tenant_id, start_date, end_date FROM auctions LIMIT 10")->fetchAll();
echo "<p>Raw auctions (first 10):</p><pre>" . htmlspecialchars(print_r($rows, true)) . "</pre>";

// 3c: Check auction_translations
$trans = $pdo2->query("SELECT auction_id, language_code, title FROM auction_translations LIMIT 10")->fetchAll();
echo "<p>Translations (first 10):</p><pre>" . htmlspecialchars(print_r($trans, true)) . "</pre>";

// ── Step 4: Replicate auctions.php query ────────────────────────────────────
echo "<h3>Step 4: Replicate auctions.php Query</h3>";
$lang     = 'ar'; // from debug output: Lang: ar
$status   = 'active';
$tenantId = 1;
$per      = 24;
$offset   = 0;

$aWhere  = '1=1';
$aParams = [$lang];
if ($status !== 'all')    { $aWhere .= ' AND a.status = ?';       $aParams[] = $status; }
if ($tenantId)            { $aWhere .= ' AND a.tenant_id = ?';    $aParams[] = $tenantId; }
$aParams[] = $per;
$aParams[] = $offset;

$sql = "SELECT a.id, a.slug, a.auction_type, a.status, a.starting_price, a.current_price,
                a.buy_now_price, a.bid_increment, a.total_bids, a.total_bidders,
                a.start_date, a.end_date, a.is_featured, a.condition_type, a.quantity,
                a.entity_id,
                (SELECT i.url FROM images i WHERE i.owner_id = a.product_id ORDER BY i.id ASC LIMIT 1) AS image_url,
                (SELECT at2.title FROM auction_translations at2
                 WHERE at2.auction_id = a.id AND at2.language_code = ? LIMIT 1) AS title
         FROM auctions a
         WHERE $aWhere
         ORDER BY a.is_featured DESC, a.end_date ASC
         LIMIT ? OFFSET ?";

echo "<p>SQL:</p><pre>" . htmlspecialchars($sql) . "</pre>";
echo "<p>Params: <pre>" . htmlspecialchars(json_encode($aParams)) . "</pre></p>";

try {
    $st = $pdo2->prepare($sql);
    echo "<p class='ok'>✅ prepare() succeeded</p>";
    $st->execute($aParams);
    echo "<p class='ok'>✅ execute() succeeded</p>";
    $result = $st->fetchAll();
    echo "<p class='" . (count($result) > 0 ? 'ok' : 'warn') . "'>" . (count($result) > 0 ? '✅' : '⚠️') . " Result rows: " . count($result) . "</p>";
    if ($result) echo "<pre>" . htmlspecialchars(print_r($result, true)) . "</pre>";
    else         echo "<p class='warn'>⚠️ Zero rows — checking why…</p>";
} catch (Throwable $e) {
    echo "<p class='err'>❌ Query failed: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// ── Step 5: Narrow down — remove each part to find the failing column ────────
echo "<h3>Step 5: Column Existence Tests</h3>";

$checks = [
    'a.is_featured'    => "SELECT a.is_featured FROM auctions a LIMIT 1",
    'a.condition_type' => "SELECT a.condition_type FROM auctions a LIMIT 1",
    'a.entity_id'      => "SELECT a.entity_id FROM auctions a LIMIT 1",
    'a.product_id'     => "SELECT a.product_id FROM auctions a LIMIT 1",
    'i.owner_id'       => "SELECT i.url FROM images i WHERE i.owner_id = 1 LIMIT 1",
    'at2 subquery'     => "SELECT (SELECT at2.title FROM auction_translations at2 WHERE at2.auction_id = 1 AND at2.language_code = 'ar' LIMIT 1) AS title FROM auctions a LIMIT 1",
    'tenant filter'    => "SELECT a.id FROM auctions a WHERE a.tenant_id = 1 LIMIT 1",
    'status filter'    => "SELECT a.id FROM auctions a WHERE a.status = 'active' LIMIT 1",
    'LIMIT ? OFFSET ?' => "SELECT a.id FROM auctions a LIMIT ? OFFSET ?",
];

foreach ($checks as $label => $query) {
    try {
        $params = strpos($query, '?') !== false ? [24, 0] : [];
        $st2 = $pdo2->prepare($query);
        $st2->execute($params);
        $r = $st2->fetchAll();
        echo "<p class='ok'>✅ $label → " . count($r) . " row(s)</p>";
    } catch (Throwable $e) {
        echo "<p class='err'>❌ $label FAILED: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
}

// ── Step 6: pub_get_pdo() test ───────────────────────────────────────────────
echo "<h3>Step 6: pub_get_pdo() via public_context.php</h3>";
try {
    ob_start();
    require_once dirname(__DIR__) . '/includes/public_context.php';
    ob_end_clean();
    $pdo3 = pub_get_pdo();
    if ($pdo3 instanceof PDO) {
        echo "<p class='ok'>✅ pub_get_pdo() returned PDO</p>";
        $r3 = $pdo3->query("SELECT COUNT(*) as n FROM auctions")->fetch();
        echo "<p class='ok'>✅ Auction count via pub_get_pdo(): " . $r3['n'] . "</p>";
    } else {
        echo "<p class='err'>❌ pub_get_pdo() returned null — auctions page will use HTTP fallback</p>";
    }
} catch (Throwable $e) {
    echo "<p class='err'>❌ public_context.php error: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>
<h3>✅ Done</h3>
<p class='err'>⚠️ DELETE this file: /frontend/public/debug_auction.php</p>
</body></html>