<?php
// DEBUG SNIPPET - Temporary request inspector for resource_permissions routes.
// Paste this snippet right after reading the raw input and decoding $data:
//   $rawInput = file_get_contents('php://input');
//   $data = $rawInput ? json_decode($rawInput, true) : [];
//
// Writes detailed request diagnostics to /tmp/resource_permissions_debug.log
// Remove this snippet when debugging is finished.

$__rp_debug_log_file = '/tmp/resource_permissions_debug.log';

/**
 * Append structured debug entry to file
 */
function __rp_debug_log(array $entry) {
    global $__rp_debug_log_file;
    $line = date('c') . ' ' . json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    @file_put_contents($__rp_debug_log_file, $line, FILE_APPEND | LOCK_EX);
}

// gather incoming headers (best-effort)
$hdrs = [];
foreach ($_SERVER as $k => $v) {
    if (strpos($k, 'HTTP_') === 0) {
        $name = strtolower(str_replace('_', '-', substr($k, 5)));
        $hdrs[$name] = $v;
    }
}
if (!empty($_SERVER['CONTENT_TYPE'])) $hdrs['content-type'] = $_SERVER['CONTENT_TYPE'];
if (!empty($_SERVER['CONTENT_LENGTH'])) $hdrs['content-length'] = $_SERVER['CONTENT_LENGTH'];

// path detection helpers
$reqUriRaw = $_SERVER['REQUEST_URI'] ?? '';
$reqUri = explode('?', $reqUriRaw, 2)[0];
$pathInfo = $_SERVER['PATH_INFO'] ?? null;
$scriptName = $_SERVER['SCRIPT_NAME'] ?? null;
$phpSelf = $_SERVER['PHP_SELF'] ?? null;

// check common legacy endpoints that frontends may call
$calledBatchPath = preg_match('#/resource_permissions/batch$#', $reqUri) === 1;
$calledResourcePermissionsPath = preg_match('#/resource_permissions(?:/|\z)#', $reqUri) === 1;

// prepare debug payload
$debugEntry = [
    'phase' => 'request-inspect',
    'method' => $_SERVER['REQUEST_METHOD'] ?? null,
    'REQUEST_URI' => $reqUriRaw,
    'REQUEST_PATH' => $reqUri,
    'PATH_INFO' => $pathInfo,
    'SCRIPT_NAME' => $scriptName,
    'PHP_SELF' => $phpSelf,
    'headers' => $hdrs,
    'GET' => $_GET ?? [],
    'decoded_body' => is_array($data) ? $data : null,
    'raw_body_first_2048' => substr((string)($rawInput ?? ''), 0, 2048),
    'detected_calls' => [
        'is_batch_path' => $calledBatchPath,
        'is_resource_permissions_path' => $calledResourcePermissionsPath
    ],
    'server_argv' => $_SERVER['argv'] ?? null,
    'ts' => time()
];

// write debug entry
__rp_debug_log($debugEntry);

// Optional: if a debugging query param present, return the diagnostics immediately (be careful: remove in production)
if (isset($_GET['rp_debug']) && ($_GET['rp_debug'] === '1' || $_GET['rp_debug'] === 'true')) {
    // return the debug info directly (developer use only)
    header('Content-Type: application/json');
    echo json_encode(['debug' => $debugEntry], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}