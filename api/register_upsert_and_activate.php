<?php
// htdocs/api/register_upsert_and_activate.php
// Robust wrapper: buffer all output, log any unexpected output, then return a clean JSON response.
// Replace the original endpoint with this file (it calls the internal logic below).
// NOTE: Keep this file at the top-level of api/ (adjust paths if your layout differs).

header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);
error_reporting(E_ALL);

// start output buffering to capture any stray output from includes
ob_start();

$logDir = __DIR__ . '/../logs';
if (!is_dir($logDir)) @mkdir($logDir, 0755, true);
$unexpectedLog = $logDir . '/api_unexpected_output.log';

// helper to write log line
function apilog($rec) {
    global $unexpectedLog;
    @file_put_contents($unexpectedLog, json_encode($rec, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) . PHP_EOL, FILE_APPEND | LOCK_EX);
}

// central responder that cleans buffer and emits JSON
function respond_json($payload, $http_status = 200) {
    // capture and clear any buffer
    $buf = '';
    if (ob_get_length() !== false) {
        $buf = ob_get_clean();
    }
    // if buffer contains non-whitespace, log it for debugging
    if (is_string($buf) && trim($buf) !== '') {
        apilog([
            'ts' => date('c'),
            'event' => 'unexpected_output_captured',
            'remote' => $_SERVER['REMOTE_ADDR'] ?? null,
            'uri' => $_SERVER['REQUEST_URI'] ?? null,
            'buffer_snippet' => mb_substr($buf, 0, 200),
        ]);
    }
    http_response_code($http_status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
    exit;
}

// ---- Now include the actual implementation, but still protected by buffering ----
try {
    require_once __DIR__ . '/config/db.php';
    require_once __DIR__ . '/lib/whatsapp_service.php';
    // If you have token_service or other includes used by the original script, include them here as needed:
    // require_once __DIR__ . '/lib/token_service.php';

    // read input
    $raw = file_get_contents('php://input');
    $input = $_POST;
    if ($raw && empty($input)) {
        $ct = $_SERVER['CONTENT_TYPE'] ?? '';
        if (stripos($ct, 'application/json') !== false) {
            $j = json_decode($raw, true);
            if (is_array($j)) $input = $j;
        } else {
            parse_str($raw, $p);
            if (is_array($p)) $input = array_merge($input, $p);
        }
    }

    // --- Minimal example logic (adapt or replace with your full logic) ---
    $action = isset($input['action']) ? strtolower(trim((string)$input['action'])) : 'register';
    $phone = isset($input['phone']) ? trim((string)$input['phone']) : null;
    if (!$phone) {
        respond_json(['ok'=>false,'error'=>'phone_required_or_invalid'], 400);
    }

    // Example: return success for testing (replace by your actual register/verify code)
    // You can move your existing register/verify logic here — keep respond_json for output.
    if ($action === 'register') {
        // example result (in real code you'd create user, token row, send whatsapp)
        $result = ['ok'=>true,'action'=>'register','phone'=>$phone,'debug'=>'example'];
        respond_json($result, 200);
    } elseif ($action === 'verify') {
        $code = $input['code'] ?? null;
        if (!$code) respond_json(['success'=>false,'message'=>'code required'], 400);
        respond_json(['success'=>true,'message'=>'verified','phone'=>$phone], 200);
    } else {
        respond_json(['ok'=>false,'error'=>'unknown_action'], 400);
    }

} catch (Throwable $e) {
    // capture any buffer and log
    $buf = '';
    if (ob_get_length() !== false) {
        $buf = ob_get_clean();
    }
    apilog([
        'ts' => date('c'),
        'event' => 'exception_in_endpoint',
        'error' => $e->getMessage(),
        'trace' => mb_substr($e->getTraceAsString(), 0, 1500),
        'buffer_snippet' => mb_substr($buf, 0, 400),
        'uri' => $_SERVER['REQUEST_URI'] ?? null,
        'remote' => $_SERVER['REMOTE_ADDR'] ?? null,
    ]);
    respond_json(['ok'=>false,'error'=>'exception','details'=>$e->getMessage()], 500);
}
?>