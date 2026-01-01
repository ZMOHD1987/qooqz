<?php
// api/helpers/countries.php
// Returns countries for form selects or filter lists.
// Usage:
//  - /api/helpers/countries.php?scope=all&lang=ar       -> all countries (for form selects)
//  - /api/helpers/countries.php?scope=referenced&lang=ar -> only countries referenced by delivery_companies (for filters)
// Default: scope=all (so form calls without query will get all countries)

declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

$DEBUG_LOG = __DIR__ . '/../error_debug.log';
function log_debug_countries($m){ @file_put_contents(__DIR__ . '/../error_debug.log', "[".date('c')."] " . trim($m) . PHP_EOL, FILE_APPEND); }

if (php_sapi_name() !== 'cli' && session_status() === PHP_SESSION_NONE) @session_start();

// Acquire DB (robust)
$db = null;
if (function_exists('container')) { try { $tmp = container('db'); if ($tmp instanceof mysqli) $db = $tmp; } catch (Throwable $e) { log_debug_countries("container db error: ".$e->getMessage()); } }
if (!$db && function_exists('connectDB')) { try { $tmp = connectDB(); if ($tmp instanceof mysqli) $db = $tmp; } catch (Throwable $e) { log_debug_countries("connectDB error: ".$e->getMessage()); } }
if (!$db) {
    foreach (['conn','db','mysqli'] as $n) if (!empty($GLOBALS[$n]) && $GLOBALS[$n] instanceof mysqli) { $db = $GLOBALS[$n]; break; }
}
if (!$db) {
    $cfg = __DIR__ . '/../config/db.php';
    if (is_readable($cfg)) { try { require_once $cfg; } catch (Throwable $e) { log_debug_countries("include db.php failed: ".$e->getMessage()); }
        if (function_exists('connectDB')) { try { $tmp = connectDB(); if ($tmp instanceof mysqli) $db = $tmp; } catch (Throwable $e) {} }
        if (isset($conn) && $conn instanceof mysqli) $db = $conn;
    }
}
if (!($db instanceof mysqli)) { http_response_code(500); echo json_encode(['success'=>false,'message'=>'Database connection error']); exit; }

// params
$lang = isset($_GET['lang']) && $_GET['lang'] !== '' ? trim((string)$_GET['lang']) :
        (!empty($_SESSION['preferred_language']) ? (string)$_SESSION['preferred_language'] :
         (!empty($_SESSION['user']['preferred_language']) ? (string)$_SESSION['user']['preferred_language'] : 'en'));

// default scope = all for form convenience
$scope = isset($_GET['scope']) && in_array($_GET['scope'], ['all','referenced'], true) ? $_GET['scope'] : 'all';

// check translations tables
$hasCountryTrans = false;
try { $r = $db->query("SHOW TABLES LIKE 'country_translations'"); if ($r && $r->num_rows > 0) $hasCountryTrans = true; if ($r) $r->free(); } catch (Throwable $e) {}

// build result
try {
    if ($scope === 'all') {
        if ($hasCountryTrans && $lang) {
            $sql = "SELECT c.id, COALESCE(ct.name, c.name) AS name, c.iso2 FROM countries c LEFT JOIN country_translations ct ON ct.country_id = c.id AND ct.language_code = ? ORDER BY name ASC";
            $stmt = $db->prepare($sql);
            if (!$stmt) { log_debug_countries("prepare failed: " . $db->error); echo json_encode(['success'=>false,'message'=>'Server error']); exit; }
            $stmt->bind_param('s', $lang);
            $stmt->execute();
            $res = $stmt->get_result();
            $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
            if ($res) $res->free();
            $stmt->close();
        } else {
            $res = $db->query("SELECT id, name, iso2 FROM countries ORDER BY name ASC");
            $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
            if ($res) $res->free();
        }
    } else { // referenced
        if ($hasCountryTrans && $lang) {
            $sql = "SELECT DISTINCT c.id, COALESCE(ct.name, c.name) AS name, c.iso2 FROM delivery_companies dc JOIN countries c ON c.id = dc.country_id LEFT JOIN country_translations ct ON ct.country_id = c.id AND ct.language_code = ? WHERE dc.country_id IS NOT NULL ORDER BY name ASC";
            $stmt = $db->prepare($sql);
            if (!$stmt) { log_debug_countries("prepare failed: " .$db->error); echo json_encode(['success'=>false,'message'=>'Server error']); exit; }
            $stmt->bind_param('s', $lang);
            $stmt->execute();
            $res = $stmt->get_result();
            $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
            if ($res) $res->free();
            $stmt->close();
        } else {
            $sql = "SELECT DISTINCT c.id, c.name, c.iso2 FROM delivery_companies dc JOIN countries c ON c.id = dc.country_id WHERE dc.country_id IS NOT NULL ORDER BY c.name ASC";
            $res = $db->query($sql);
            $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
            if ($res) $res->free();
        }
    }

    echo json_encode(['success'=>true,'data'=>$rows], JSON_UNESCAPED_UNICODE);
    exit;
} catch (Throwable $e) {
    log_debug_countries("countries helper error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>'Server error']);
    exit;
}