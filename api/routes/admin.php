<?php
// api/routes/admin.php
// Minimal safe admin endpoint — returns JSON health/status and logs requests.
// Use this temporary file to eliminate HTTP 500 and allow progressive restoration.
declare(strict_types=1);

$LOG = __DIR__ . '/../logs/admin-health.log';
$FALLBACK = __DIR__ . '/../error_debug.log';
if (!is_dir(dirname($LOG))) @mkdir(dirname($LOG), 0755, true);

function adm_log($m) {
    global $LOG, $FALLBACK;
    $line = '['.date('c').'] '.trim((string)$m).PHP_EOL;
    @file_put_contents($LOG, $line, FILE_APPEND|LOCK_EX) ?: @file_put_contents($FALLBACK, $line, FILE_APPEND|LOCK_EX);
}

try {
    // Start session if not started (non-fatal)
    if (php_sapi_name() !== 'cli' && session_status() === PHP_SESSION_NONE) @session_start();

    // Simple health response
    $resp = [
        'success' => true,
        'message' => 'Admin routes placeholder is running',
        'time' => date('c'),
        'php_version' => PHP_VERSION,
        'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? null
    ];

    // log a short request summary
    adm_log('ADMIN-HEALTH REQUEST: ' . ($_SERVER['REQUEST_METHOD'] ?? 'N/A') . ' ' . ($_SERVER['REQUEST_URI'] ?? '') .
        ' REMOTE=' . ($_SERVER['REMOTE_ADDR'] ?? ''));

    header('Content-Type: application/json; charset=utf-8', true, 200);
    echo json_encode($resp, JSON_UNESCAPED_UNICODE);
    exit;
} catch (Throwable $e) {
    adm_log('ADMIN-HEALTH EXCEPTION: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    if (!headers_sent()) header('Content-Type: application/json; charset=utf-8', true, 500);
    echo json_encode(['success'=>false,'message'=>'Server error','error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit;
}