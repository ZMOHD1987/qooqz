<?php
declare(strict_types=1);
/**
 * admin/search.php
 * Simple admin search page (DB-driven).
 *
 * - Expects header.php to have been included (it injects window.ADMIN_UI on client side).
 * - Uses GET parameter "q" for query and optional "page" for pagination.
 * - Attempts to detect a DB connection (PDO or mysqli) created by your bootstrap.
 * - Searches across a set of common tables if present (pages, products, users).
 * - Returns HTML page when normal request, or JSON when X-Requested-With: XMLHttpRequest.
 *
 * Save as UTF-8 without BOM.
 */

require_once __DIR__ . '/includes/header.php'; // injects session, ADMIN_UI, etc.

/* ----------------------
   Helpers: DB abstraction
   ---------------------- */
function detect_db_handle() {
    // common variable names in projects: $pdo, $DB, $db, $mysqli
    foreach (['pdo','PDO','DB','db','dbh','mysqli'] as $name) {
        if (isset($GLOBALS[$name])) return $GLOBALS[$name];
    }
    return null;
}

function execute_select($sql, $params = []) {
    $dbh = detect_db_handle();
    if (!$dbh) {
        throw new RuntimeException('No DB handle detected. Please ensure bootstrap provides $pdo or $DB or $mysqli.');
    }

    // PDO
    if ($dbh instanceof PDO) {
        $stmt = $dbh->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // mysqli object
    if ($dbh instanceof mysqli) {
        // emulate prepared behavior for simple cases
        if (!$params) {
            $res = $dbh->query($sql);
            $rows = [];
            if ($res) while ($r = $res->fetch_assoc()) $rows[] = $r;
            return $rows;
        } else {
            // prepare + bind generically as strings
            $stmt = $dbh->prepare($sql);
            if ($stmt === false) throw new RuntimeException('MySQLi prepare failed: ' . $dbh->error);
            // build types
            $types = str_repeat('s', count($params));
            $refs = [];
            foreach ($params as $k => $v) $refs[] = &$params[$k];
            array_unshift($refs, $types);
            call_user_func_array([$stmt, 'bind_param'], $refs);
            $stmt->execute();
            $res = $stmt->get_result();
            $rows = [];
            if ($res) while ($r = $res->fetch_assoc()) $rows[] = $r;
            $stmt->close();
            return $rows;
        }
    }

    // fallback: if $dbh is array-like with connection (rare)
    throw new RuntimeException('Unsupported DB handle type: ' . gettype($dbh));
}

/* ----------------------
   Request / Input
   ---------------------- */
$q = (string)($_GET['q'] ?? '');
$q = trim(mb_substr($q, 0, 300)); // limit length
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 12;
$ajax = (
    !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
);

/* If no query, render simple search form (header already included) */
if ($q === '') {
    if ($ajax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'message' => 'empty_query']);
        exit;
    }
    ?>
    <main id="adminMainContent" class="admin-main" role="main">
      <div class="card">
        <h2 class="card-title">Search</h2>
        <form method="get" action="/admin/search.php" class="search-wrap">
          <input id="adminSearch" name="q" class="search-input" type="search" placeholder="<?php echo htmlspecialchars($GLOBALS['ADMIN_UI']['strings']['search_placeholder'] ?? 'Search...', ENT_QUOTES, 'UTF-8'); ?>" value="">
          <button id="searchBtn" class="btn"><?php echo htmlspecialchars($GLOBALS['ADMIN_UI']['strings']['search_button'] ?? 'Search', ENT_QUOTES, 'UTF-8'); ?></button>
        </form>
        <p style="margin-top:12px;color:#6b7280;">Enter a search term to find pages, products or users.</p>
      </div>
    </main>
    <?php
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

/* ----------------------
   Searchable sources configuration
   - Each source: table name, columns to search, title column, excerpt column, admin/edit URL pattern
   - We will try each source only if the table exists (we will attempt a safe query; failures are ignored)
   ---------------------- */
$sources = [
    'pages' => [
        'table' => 'pages',
        'title' => 'title',
        'excerpt' => 'content',
        'columns' => ['title','content'],
        'url' => '/admin/pages/edit.php?id=%d'
    ],
    'products' => [
        'table' => 'products',
        'title' => 'name',
        'excerpt' => 'description',
        'columns' => ['name','description'],
        'url' => '/admin/products/edit.php?id=%d'
    ],
    'users' => [
        'table' => 'users',
        'title' => 'username',
        'excerpt' => 'email',
        'columns' => ['username','email'],
        'url' => '/admin/users/edit.php?id=%d'
    ],
];

/* ----------------------
   Build queries and collect results (best-effort)
   ---------------------- */
$allResults = [];
$totalCount = 0;
$limitPerSource = 50; // avoid huge responses per table

// parameter for LIKE
$like = '%' . str_replace(['%','_'], ['\\%','\\_'], $q) . '%';

foreach ($sources as $key => $cfg) {
    $table = $cfg['table'];
    // build WHERE like (title OR excerpt)
    $cols = array_map(function($c){ return "`$c`"; }, $cfg['columns']);
    $whereParts = [];
    foreach ($cfg['columns'] as $col) {
        $whereParts[] = "`$col` LIKE :q";
    }
    $where = implode(' OR ', $whereParts);
    $sql = "SELECT id, " . ($cfg['title']) . " AS title, " . ($cfg['excerpt']) . " AS excerpt FROM `{$table}` WHERE ({$where}) LIMIT {$limitPerSource}";
    try {
        $rows = execute_select($sql, ['q' => $like]);
        if (!empty($rows)) {
            foreach ($rows as $r) {
                $title = (string)($r['title'] ?? '');
                $excerpt = (string)($r['excerpt'] ?? '');
                // build excerpt snippet (first 160 chars around first match)
                $snippet = '';
                $lc = mb_stripos($excerpt, $q);
                if ($lc !== false) {
                    $start = max(0, $lc - 40);
                    $snippet = mb_substr($excerpt, $start, 160);
                    if ($start > 0) $snippet = '...' . $snippet;
                    if (mb_strlen($excerpt) > $start + 160) $snippet .= '...';
                } else {
                    $snippet = mb_substr($excerpt, 0, 160) . (mb_strlen($excerpt) > 160 ? '...' : '');
                }
                $allResults[] = [
                    'source' => $key,
                    'table' => $table,
                    'id' => $r['id'],
                    'title' => $title ?: ('#' . $r['id']),
                    'excerpt' => $snippet,
                    'admin_url' => isset($cfg['url']) ? sprintf($cfg['url'], intval($r['id'])) : null,
                ];
                $totalCount++;
            }
        }
    } catch (Throwable $e) {
        // ignore this table if it doesn't exist or query fails; do not stop entire search
        // optionally: log error to server logs
        error_log('search.php: skipping table ' . $table . ' error: ' . $e->getMessage());
        continue;
    }
}

/* ----------------------
   Simple ranking: prioritize results with query in title
   ---------------------- */
usort($allResults, function($a, $b) use ($q) {
    $aq = mb_stripos($a['title'], $q) !== false ? 1 : 0;
    $bq = mb_stripos($b['title'], $q) !== false ? 1 : 0;
    if ($aq !== $bq) return $bq - $aq; // items with title match first
    return 0;
});

/* Pagination across aggregated results */
$total = count($allResults);
$start = ($page - 1) * $perPage;
$paginated = array_slice($allResults, $start, $perPage);
$totalPages = (int)ceil($total / $perPage);

/* Output JSON for AJAX */
if ($ajax) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok' => true,
        'query' => $q,
        'total' => $total,
        'page' => $page,
        'per_page' => $perPage,
        'results' => $paginated
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/* ----------------------
   Render HTML results page
   ---------------------- */
?>
<main id="adminMainContent" class="admin-main" role="main">
  <div class="row">
    <div class="col" style="max-width:1100px;">
      <div class="card">
        <div style="display:flex;align-items:center;justify-content:space-between;">
          <h2 class="card-title">Search results for "<?php echo htmlspecialchars($q, ENT_QUOTES, 'UTF-8'); ?>"</h2>
          <form method="get" action="/admin/search.php" style="margin:0;">
            <input id="adminSearch" name="q" class="search-input" type="search" placeholder="<?php echo htmlspecialchars($GLOBALS['ADMIN_UI']['strings']['search_placeholder'] ?? 'Search...', ENT_QUOTES, 'UTF-8'); ?>" value="<?php echo htmlspecialchars($q, ENT_QUOTES, 'UTF-8'); ?>">
            <button id="searchBtn" class="btn">Search</button>
          </form>
        </div>

        <?php if ($total === 0): ?>
          <div style="padding:16px;color:#6b7280;">No results found.</div>
        <?php else: ?>
          <div style="margin-top:12px;">
            <div style="margin-bottom:8px;color:#6b7280;"><?php printf('%d result(s) found', $total); ?></div>

            <ul style="list-style:none;padding:0;margin:0;">
              <?php foreach ($paginated as $row): ?>
                <li style="margin-bottom:12px;padding-bottom:12px;border-bottom:1px solid rgba(0,0,0,0.04);">
                  <div style="display:flex;align-items:center;justify-content:space-between;">
                    <div>
                      <a href="<?php echo htmlspecialchars($row['admin_url'] ?? '#', ENT_QUOTES, 'UTF-8'); ?>" style="font-weight:600;color:var(--theme-primary, #3B82F6);text-decoration:none;"><?php echo htmlspecialchars($row['title'], ENT_QUOTES, 'UTF-8'); ?></a>
                      <div style="color:#6b7280;margin-top:6px;"><?php echo htmlspecialchars($row['excerpt'], ENT_QUOTES, 'UTF-8'); ?></div>
                      <div style="margin-top:6px;font-size:12px;color:#9ca3af;">Source: <?php echo htmlspecialchars($row['source'], ENT_QUOTES, 'UTF-8'); ?> | ID: <?php echo intval($row['id']); ?></div>
                    </div>
                    <div style="margin-left:12px;">
                      <?php if (!empty($row['admin_url'])): ?>
                        <a class="btn" href="<?php echo htmlspecialchars($row['admin_url'], ENT_QUOTES, 'UTF-8'); ?>">Open</a>
                      <?php endif; ?>
                    </div>
                  </div>
                </li>
              <?php endforeach; ?>
            </ul>

            <?php if ($totalPages > 1): ?>
              <div style="margin-top:14px;display:flex;gap:6px;flex-wrap:wrap;">
                <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                  <a class="btn" href="/admin/search.php?q=<?php echo urlencode($q); ?>&page=<?php echo $p; ?>" style="<?php if ($p == $page) echo 'background:var(--theme-primary);color:#fff;'; ?>"><?php echo $p; ?></a>
                <?php endfor; ?>
              </div>
            <?php endif; ?>

          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</main>

<?php
require_once __DIR__ . '/includes/footer.php';