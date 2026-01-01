<?php
// htdocs/api/index.php
// Front controller for API — loads bootstrap then routes dispatcher.
// This version normalizes the REQUEST_URI so routes like /api/themes will match
// even when the request is /api/index.php/themes

declare(strict_types=1);

// prevent accidental CLI execution without context
if (php_sapi_name() === 'cli') {
    echo "API front controller (CLI)\n";
    exit;
}

// work from api directory
chdir(__DIR__);

// normalize REQUEST_URI: remove the script name (e.g. /api/index.php) so routes match
if (!empty($_SERVER['REQUEST_URI']) && !empty($_SERVER['SCRIPT_NAME'])) {
    // remove the first occurrence of /index.php (or script basename) from REQUEST_URI
    $scriptBasename = '/' . basename($_SERVER['SCRIPT_NAME']);
    // if REQUEST_URI contains the script basename, strip it once
    if (strpos($_SERVER['REQUEST_URI'], $scriptBasename) !== false) {
        $_SERVER['REQUEST_URI'] = preg_replace('#' . preg_quote($scriptBasename, '#') . '#', '', $_SERVER['REQUEST_URI'], 1);
        if ($_SERVER['REQUEST_URI'] === '') $_SERVER['REQUEST_URI'] = '/';
    } else {
        // also handle cases where script is in subpath (e.g. /subdir/index.php)
        $scriptDir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
        if ($scriptDir !== '' && strpos($_SERVER['REQUEST_URI'], $scriptDir . '/') === 0) {
            // keep the leading slash then remove scriptDir
            $_SERVER['REQUEST_URI'] = substr($_SERVER['REQUEST_URI'], strlen($scriptDir));
            if ($_SERVER['REQUEST_URI'] === '') $_SERVER['REQUEST_URI'] = '/';
        }
    }
}

// also set PATH_INFO for scripts that rely on it
if (!isset($_SERVER['PATH_INFO'])) {
    $req = $_SERVER['REQUEST_URI'] ?? '/';
    // remove query string
    $reqPath = parse_url($req, PHP_URL_PATH) ?: '/';
    $_SERVER['PATH_INFO'] = $reqPath;
}

// load bootstrap (initializes $container etc.)
require_once __DIR__ . '/bootstrap.php';

// routes.php is the dispatcher; require it if present
$routesFile = __DIR__ . '/routes.php';
if (is_readable($routesFile)) {
    require_once $routesFile;
    // routes.php should exit after dispatching, but in case it returns:
    exit;
} else {
    // fallback: simple health JSON
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(200);
    echo json_encode(['success' => true, 'message' => 'API bootstrap loaded, but routes.php missing.']);
    exit;
}