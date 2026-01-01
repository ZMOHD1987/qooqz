<?php
// api/helpers/cities.php (debug-capable, robust)
// Use ?scope=all|referenced, ?country_id=..., ?lang=..., optionally ?debug=1 to return detailed error
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

$DEBUG_LOG = __DIR__ . '/../error_debug.log';
function log_debug_cities($m){ @file_put_contents(__DIR__ . '/../error_debug.log', "[".date('c')."] " . trim($m) . PHP_EOL, FILE_APPEND); }

if (php_sapi_name() !== 'cli' && session_status() === PHP_SESSION_NONE) @session_start();

$debugMode = !empty($_GET['debug']) && $_GET['debug'] == '1';

// Acquire DB
$db = null;
if (function_exists('container')) { try { $tmp = container('db'); if ($tmp instanceof mysqli) $db = $tmp; } catch (Throwable $e) { log_debug_cities("container db error: ".$e->getMessage()); } }
if (!$db && function_exists('connectDB')) { try { $tmp = connectDB(); if ($tmp instanceof mysqli) $db = $tmp; } catch (Throwable $e) { log_debug_cities("connectDB error: ".$e->getMessage()); } }
if (!$db) {
    foreach (['conn','db','mysqli'] as $n) if (!empty($GLOBALS[$n]) && $GLOBALS[$n] instanceof mysqli) { $db = $GLOBALS[$n]; break; }
}
if (!$db) {
    $cfg = __DIR__ . '/../config/db.php';
    if (is_readable($cfg)) { try { require_once $cfg; } catch (Throwable $e) { log_debug_cities("include db.php failed: ".$e->getMessage()); } 
        if (function_exists('connectDB')) { try { $tmp = connectDB(); if ($tmp instanceof mysqli) $db = $tmp; } catch (Throwable $e) { } }
        if (isset($conn) && $conn instanceof mysqli) $db = $conn;
    }
}
if (!($db instanceof mysqli)) {
    $msg = 'Database connection error';
    if ($debugMode) echo json_encode(['success'=>false,'message'=>$msg]); else echo json_encode(['success'=>false,'message'=>'Server error']);
    http_response_code(500); exit;
}

$lang = isset($_GET['lang']) && $_GET['lang'] !== '' ? trim((string)$_GET['lang']) : (!empty($_SESSION['preferred_language']) ? $_SESSION['preferred_language'] : (!empty($_SESSION['user']['preferred_language']) ? $_SESSION['user']['preferred_language'] : 'en'));
$scope = isset($_GET['scope']) && in_array($_GET['scope'], ['all','referenced'], true) ? $_GET['scope'] : 'all';
$country_id = isset($_GET['country_id']) && $_GET['country_id'] !== '' ? (int)$_GET['country_id'] : null;

try {
    $hasCityTrans = false;
    $r = $db->query("SHOW TABLES LIKE 'city_translations'");
    if ($r) { if ($r->num_rows > 0) $hasCityTrans = true; $r->free(); }

    $rows = [];

    if ($scope === 'all') {
        if ($hasCityTrans && $lang) {
            if ($country_id) {
                $sql = "SELECT ci.id, COALESCE(cit.name, ci.name) AS name FROM cities ci LEFT JOIN city_translations cit ON cit.city_id = ci.id AND cit.language_code = ? WHERE ci.country_id = ? ORDER BY name ASC";
                $stmt = $db->prepare($sql);
                if (!$stmt) throw new RuntimeException("prepare failed: " . $db->error);
                $stmt->bind_param('si', $lang, $country_id);
                $stmt->execute();
                $res = $stmt->get_result();
                $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
                if ($res) $res->free();
                $stmt->close();
            } else {
                $sql = "SELECT ci.id, COALESCE(cit.name, ci.name) AS name FROM cities ci LEFT JOIN city_translations cit ON cit.city_id = ci.id AND cit.language_code = ? ORDER BY name ASC";
                $stmt = $db->prepare($sql);
                if (!$stmt) throw new RuntimeException("prepare failed: " . $db->error);
                $stmt->bind_param('s', $lang);
                $stmt->execute();
                $res = $stmt->get_result();
                $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
                if ($res) $res->free();
                $stmt->close();
            }
        } else {
            if ($country_id) {
                $stmt = $db->prepare("SELECT id, name FROM cities WHERE country_id = ? ORDER BY name ASC");
                if (!$stmt) throw new RuntimeException("prepare failed: " . $db->error);
                $stmt->bind_param('i', $country_id);
                $stmt->execute();
                $res = $stmt->get_result();
                $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
                if ($res) $res->free();
                $stmt->close();
            } else {
                $res = $db->query("SELECT id, name FROM cities ORDER BY name ASC");
                $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
                if ($res) $res->free();
            }
        }
    } else { // referenced
        if ($hasCityTrans && $lang) {
            if ($country_id) {
                $sql = "SELECT DISTINCT ci.id, COALESCE(cit.name, ci.name) AS name FROM delivery_companies dc JOIN cities ci ON ci.id = dc.city_id LEFT JOIN city_translations cit ON cit.city_id = ci.id AND cit.language_code = ? WHERE dc.city_id IS NOT NULL AND ci.country_id = ? ORDER BY name ASC";
                $stmt = $db->prepare($sql);
                if (!$stmt) throw new RuntimeException("prepare failed: " . $db->error);
                $stmt->bind_param('si', $lang, $country_id);
                $stmt->execute();
                $res = $stmt->get_result();
                $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
                if ($res) $res->free();
                $stmt->close();
            } else {
                $sql = "SELECT DISTINCT ci.id, COALESCE(cit.name, ci.name) AS name FROM delivery_companies dc JOIN cities ci ON ci.id = dc.city_id LEFT JOIN city_translations cit ON cit.city_id = ci.id AND cit.language_code = ? WHERE dc.city_id IS NOT NULL ORDER BY name ASC";
                $stmt = $db->prepare($sql);
                if (!$stmt) throw new RuntimeException("prepare failed: " . $db->error);
                $stmt->bind_param('s', $lang);
                $stmt->execute();
                $res = $stmt->get_result();
                $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
                if ($res) $res->free();
                $stmt->close();
            }
        } else {
            if ($country_id) {
                $sql = "SELECT DISTINCT ci.id, ci.name FROM delivery_companies dc JOIN cities ci ON ci.id = dc.city_id WHERE dc.city_id IS NOT NULL AND ci.country_id = ? ORDER BY ci.name ASC";
                $stmt = $db->prepare($sql);
                if (!$stmt) throw new RuntimeException("prepare failed: " . $db->error);
                $stmt->bind_param('i', $country_id);
                $stmt->execute();
                $res = $stmt->get_result();
                $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
                if ($res) $res->free();
                $stmt->close();
            } else {
                $res = $db->query("SELECT DISTINCT ci.id, ci.name FROM delivery_companies dc JOIN cities ci ON ci.id = dc.city_id WHERE dc.city_id IS NOT NULL ORDER BY ci.name ASC");
                $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
                if ($res) $res->free();
            }
        }
    }

    echo json_encode(['success' => true, 'data' => $rows], JSON_UNESCAPED_UNICODE);
    exit;
} catch (Throwable $e) {
    log_debug_cities("cities helper exception: " . $e->getMessage() . " trace:" . $e->getTraceAsString());
    if ($debugMode) {
        echo json_encode(['success' => false, 'message' => 'Server error', 'error' => $e->getMessage()]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Server error']);
    }
    http_response_code(500);
    exit;
}