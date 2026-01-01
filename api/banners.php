<?php
// htdocs/api/banners.php
// Banners API - production-ready
// - GET: list or ?_fetch_row=1&id=...
// - POST action=save/delete
// - CSRF check for POST
// - Auth check (auth_helper or session/rbac fallback)
// - Always returns valid JSON, logs server errors to /tmp/banners_debug.log

header('Content-Type: application/json; charset=utf-8');
if (session_status() === PHP_SESSION_NONE) session_start();

// helper: consistent JSON response + exit
function api_resp($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// server-side logging (safe): only use for debugging; file must be writable by PHP
function server_log($msg) {
    @file_put_contents('/tmp/banners_debug.log', date('c')." ".$msg."\n", FILE_APPEND);
}

// disable display errors to avoid breaking JSON output; log errors instead
@ini_set('display_errors', '0');
@ini_set('display_startup_errors', '0');
error_reporting(E_ALL);

// --- Auth check (API-friendly) ---
$has_permission = false;
if (is_readable(__DIR__ . '/helpers/auth_helper.php')) {
    require_once __DIR__ . '/helpers/auth_helper.php';
    if (function_exists('get_authenticated_user_with_permissions')) {
        $user = get_authenticated_user_with_permissions();
        if ($user && in_array('manage_banners', $user['permissions'] ?? [], true)) $has_permission = true;
    }
}

// fallback to legacy _auth.php / $rbac check (no redirects)
if (!$has_permission) {
    $authCandidates = [
        __DIR__ . '/../admin/_auth.php',
        __DIR__ . '/../_auth.php',
        __DIR__ . '/_auth.php'
    ];
    foreach ($authCandidates as $c) if (is_readable($c)) { require_once $c; break; }
    if (isset($rbac) && is_object($rbac)) {
        if (method_exists($rbac, 'hasPermission')) $has_permission = (bool)$rbac->hasPermission('manage_banners');
        elseif (method_exists($rbac, 'check')) $has_permission = (bool)$rbac->check('manage_banners');
    } else {
        // fallback: session permissions array
        $sessionPerms = $_SESSION['permissions'] ?? [];
        if (is_array($sessionPerms) && in_array('manage_banners', $sessionPerms, true)) $has_permission = true;
    }
}

if (!$has_permission) {
    api_resp(['success' => false, 'message' => 'ليس لديك صلاحية لهذه العملية'], 403);
}

// --- DB connect ---
$dbFile = __DIR__ . '/config/db.php';
if (!is_readable($dbFile)) api_resp(['success' => false, 'message' => 'Server configuration error (db)'], 500);
require_once $dbFile;
if (!function_exists('connectDB')) api_resp(['success' => false, 'message' => 'DB helper missing'], 500);
$conn = connectDB();
if (!($conn instanceof mysqli)) api_resp(['success' => false, 'message' => 'DB connection failed'], 500);

// helper: parse translations JSON input
function parse_translations_input($input) {
    if (is_array($input)) return $input;
    if (empty($input)) return [];
    $t = json_decode($input, true);
    return is_array($t) ? $t : [];
}

// helper: bind params by reference for mysqli_stmt
function bind_params_by_ref(mysqli_stmt $stmt, string $types, array $params) {
    $refs = [];
    $refs[] = & $types;
    for ($i = 0; $i < count($params); $i++) {
        $refs[] = & $params[$i];
    }
    return call_user_func_array([$stmt, 'bind_param'], $refs);
}

// Safe wrapper to execute prepared statement and return result or false
function safe_execute_stmt(mysqli_stmt $stmt) {
    if (!$stmt) return false;
    if (!$stmt->execute()) {
        server_log("Statement execute error: " . $stmt->error);
        return false;
    }
    return $stmt->get_result();
}

// --- Handle GET ---
try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // fetch single row
        if (!empty($_GET['_fetch_row']) && $_GET['_fetch_row'] == '1' && !empty($_GET['id'])) {
            $id = (int)$_GET['id'];
            $stmt = $conn->prepare("SELECT * FROM banners WHERE id = ? LIMIT 1");
            if (!$stmt) api_resp(['success' => false, 'message' => 'Query prepare failed'], 500);
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $banner = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!$banner) api_resp(['success' => false, 'message' => 'Not found'], 404);

            // load translations
            $tstmt = $conn->prepare("SELECT language_code, title, subtitle, link_text FROM banner_translations WHERE banner_id = ?");
            if ($tstmt) {
                $tstmt->bind_param('i', $id);
                $tstmt->execute();
                $res = $tstmt->get_result();
                $banner['translations'] = [];
                while ($r = $res->fetch_assoc()) {
                    $banner['translations'][$r['language_code']] = [
                        'title' => $r['title'],
                        'subtitle' => $r['subtitle'],
                        'link_text' => $r['link_text']
                    ];
                }
                $tstmt->close();
            } else {
                $banner['translations'] = [];
            }

            api_resp(['success' => true, 'data' => $banner]);
        }

        // list all banners
        $list = [];
        $q = "SELECT id, title, subtitle, image_url, mobile_image_url, position, theme_id, background_color, text_color, button_style, sort_order, is_active, start_date, end_date, created_at, updated_at FROM banners ORDER BY sort_order ASC, id DESC";
        $res = $conn->query($q);
        if (!$res) api_resp(['success' => false, 'message' => 'Query failed'], 500);
        while ($r = $res->fetch_assoc()) $list[] = $r;

        // attach translations in single query (if any)
        if (!empty($list)) {
            $ids = array_map(function($b){ return (int)$b['id']; }, $list);
            $in = implode(',', $ids);
            $tq = "SELECT banner_id, language_code, title, subtitle, link_text FROM banner_translations WHERE banner_id IN ($in)";
            $tres = $conn->query($tq);
            $translations = [];
            if ($tres) {
                while ($tr = $tres->fetch_assoc()) {
                    $translations[(int)$tr['banner_id']][] = $tr;
                }
            }
            foreach ($list as &$b) {
                $bid = (int)$b['id'];
                $b['translations'] = [];
                if (!empty($translations[$bid])) {
                    foreach ($translations[$bid] as $tr) {
                        $b['translations'][$tr['language_code']] = [
                            'title' => $tr['title'],
                            'subtitle' => $tr['subtitle'],
                            'link_text' => $tr['link_text']
                        ];
                    }
                }
            }
            unset($b);
        }

        api_resp(['success' => true, 'data' => $list]);
    }

    // --- Handle POST ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';

        // CSRF
        if (empty($_POST['csrf_token']) || empty($_SESSION['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            api_resp(['success' => false, 'message' => 'Invalid CSRF token'], 400);
        }

        if ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            if (!$id) api_resp(['success' => false, 'message' => 'Invalid id'], 400);
            $d1 = $conn->prepare("DELETE FROM banner_translations WHERE banner_id = ?");
            if ($d1) { $d1->bind_param('i', $id); $d1->execute(); $d1->close(); }
            $d = $conn->prepare("DELETE FROM banners WHERE id = ? LIMIT 1");
            if (!$d) api_resp(['success' => false, 'message' => 'Prepare failed'], 500);
            $d->bind_param('i', $id);
            if ($d->execute()) { $d->close(); api_resp(['success' => true, 'message' => 'Deleted']); }
            else { $err = $d->error; $d->close(); api_resp(['success' => false, 'message' => 'Delete failed: '.$err], 500); }
        }

        if ($action === 'save') {
            // normalize inputs
            $id = !empty($_POST['id']) ? (int)$_POST['id'] : 0;
            $title = trim($_POST['title'] ?? '');
            $subtitle = isset($_POST['subtitle']) ? trim($_POST['subtitle']) : null;
            $image_url = trim($_POST['image_url'] ?? '');
            $mobile_image_url = trim($_POST['mobile_image_url'] ?? '');
            $link_url = isset($_POST['link_url']) ? trim($_POST['link_url']) : null;
            $link_text = isset($_POST['link_text']) ? trim($_POST['link_text']) : null;
            $position = isset($_POST['position']) ? trim($_POST['position']) : null;
            $theme_id = isset($_POST['theme_id']) && $_POST['theme_id'] !== '' ? (int)$_POST['theme_id'] : null;
            $background_color = isset($_POST['background_color']) ? trim($_POST['background_color']) : '#FFFFFF';
            $text_color = isset($_POST['text_color']) ? trim($_POST['text_color']) : '#000000';
            $button_style = isset($_POST['button_style']) ? trim($_POST['button_style']) : null;
            $sort_order = isset($_POST['sort_order']) ? (int)$_POST['sort_order'] : 0;
            $is_active = isset($_POST['is_active']) ? (int)$_POST['is_active'] : 0;
            $start_date = !empty($_POST['start_date']) ? str_replace('T',' ',$_POST['start_date']) : null;
            $end_date = !empty($_POST['end_date']) ? str_replace('T',' ',$_POST['end_date']) : null;
            $translations = parse_translations_input($_POST['translations'] ?? '');

            if ($title === '') api_resp(['success' => false, 'message' => 'Title is required'], 400);

            if ($id) {
                // build dynamic UPDATE to allow NULL assignment for theme/start/end
                $sets = [];
                $types = '';
                $params = [];

                $sets[] = 'title = ?'; $types .= 's'; $params[] = $title;
                $sets[] = 'subtitle = ?'; $types .= 's'; $params[] = $subtitle;
                $sets[] = 'image_url = ?'; $types .= 's'; $params[] = $image_url;
                $sets[] = 'mobile_image_url = ?'; $types .= 's'; $params[] = $mobile_image_url;
                $sets[] = 'link_url = ?'; $types .= 's'; $params[] = $link_url;
                $sets[] = 'link_text = ?'; $types .= 's'; $params[] = $link_text;
                $sets[] = 'position = ?'; $types .= 's'; $params[] = $position;

                if ($theme_id === null) {
                    $sets[] = 'theme_id = NULL';
                } else {
                    $sets[] = 'theme_id = ?'; $types .= 'i'; $params[] = $theme_id;
                }

                $sets[] = 'background_color = ?'; $types .= 's'; $params[] = $background_color;
                $sets[] = 'text_color = ?'; $types .= 's'; $params[] = $text_color;
                $sets[] = 'button_style = ?'; $types .= 's'; $params[] = $button_style;
                $sets[] = 'sort_order = ?'; $types .= 'i'; $params[] = $sort_order;
                $sets[] = 'is_active = ?'; $types .= 'i'; $params[] = $is_active;

                if ($start_date === null) {
                    $sets[] = 'start_date = NULL';
                } else {
                    $sets[] = 'start_date = ?'; $types .= 's'; $params[] = $start_date;
                }

                if ($end_date === null) {
                    $sets[] = 'end_date = NULL';
                } else {
                    $sets[] = 'end_date = ?'; $types .= 's'; $params[] = $end_date;
                }

                $sets[] = 'updated_at = NOW()';

                $sql = "UPDATE banners SET " . implode(', ', $sets) . " WHERE id = ?";
                $types .= 'i'; $params[] = $id;

                $stmt = $conn->prepare($sql);
                if (!$stmt) api_resp(['success' => false, 'message' => 'Prepare failed: '.$conn->error], 500);
                // bind params by ref
                if (!empty($params)) bind_params_by_ref($stmt, $types, $params);
                if (!$stmt->execute()) { $err = $stmt->error; $stmt->close(); api_resp(['success' => false, 'message' => 'Update failed: '.$err], 500); }
                $stmt->close();
                $bannerId = $id;
            } else {
                // INSERT with dynamic columns (allow NULL theme/start/end)
                $cols = ['title','subtitle','image_url','mobile_image_url','link_url','link_text','position','theme_id','background_color','text_color','button_style','sort_order','is_active','start_date','end_date','created_at'];
                $placeholders = [];
                $types = '';
                $params = [];

                // title..position always placeholders
                $placeholders[] = '?'; $types .= 's'; $params[] = $title;
                $placeholders[] = '?'; $types .= 's'; $params[] = $subtitle;
                $placeholders[] = '?'; $types .= 's'; $params[] = $image_url;
                $placeholders[] = '?'; $types .= 's'; $params[] = $mobile_image_url;
                $placeholders[] = '?'; $types .= 's'; $params[] = $link_url;
                $placeholders[] = '?'; $types .= 's'; $params[] = $link_text;
                $placeholders[] = '?'; $types .= 's'; $params[] = $position;

                // theme_id: nullable
                if ($theme_id === null) {
                    $placeholders[] = 'NULL';
                } else {
                    $placeholders[] = '?'; $types .= 'i'; $params[] = $theme_id;
                }

                $placeholders[] = '?'; $types .= 's'; $params[] = $background_color;
                $placeholders[] = '?'; $types .= 's'; $params[] = $text_color;
                $placeholders[] = '?'; $types .= 's'; $params[] = $button_style;
                $placeholders[] = '?'; $types .= 'i'; $params[] = $sort_order;
                $placeholders[] = '?'; $types .= 'i'; $params[] = $is_active;

                if ($start_date === null) {
                    $placeholders[] = 'NULL';
                } else {
                    $placeholders[] = '?'; $types .= 's'; $params[] = $start_date;
                }

                if ($end_date === null) {
                    $placeholders[] = 'NULL';
                } else {
                    $placeholders[] = '?'; $types .= 's'; $params[] = $end_date;
                }

                $placeholders[] = 'NOW()'; // created_at

                $sql = "INSERT INTO banners (" . implode(',', $cols) . ") VALUES (" . implode(',', $placeholders) . ")";
                $stmt = $conn->prepare($sql);
                if (!$stmt) api_resp(['success' => false, 'message' => 'Prepare failed: '.$conn->error], 500);
                if (!empty($params)) bind_params_by_ref($stmt, $types, $params);
                if (!$stmt->execute()) { $err = $stmt->error; $stmt->close(); api_resp(['success' => false, 'message' => 'Insert failed: '.$err], 500); }
                $bannerId = $stmt->insert_id;
                $stmt->close();
            }

            // translations: delete existing, then insert provided
            $d = $conn->prepare("DELETE FROM banner_translations WHERE banner_id = ?");
            if ($d) { $d->bind_param('i', $bannerId); $d->execute(); $d->close(); }

            if (!empty($translations) && is_array($translations)) {
                $ins = $conn->prepare("INSERT INTO banner_translations (banner_id, language_code, title, subtitle, link_text) VALUES (?,?,?,?,?)");
                if ($ins) {
                    foreach ($translations as $lang => $t) {
                        $t_title = $t['title'] ?? '';
                        $t_sub = $t['subtitle'] ?? null;
                        $t_link = $t['link_text'] ?? null;
                        $ins->bind_param('issss', $bannerId, $lang, $t_title, $t_sub, $t_link);
                        $ins->execute();
                    }
                    $ins->close();
                } else {
                    server_log("Failed to prepare translation insert: " . $conn->error);
                }
            }

            // return banner record (fresh)
            $s = $conn->prepare("SELECT * FROM banners WHERE id = ? LIMIT 1");
            if ($s) { $s->bind_param('i', $bannerId); $s->execute(); $banner = $s->get_result()->fetch_assoc(); $s->close(); }
            else $banner = ['id' => $bannerId];

            api_resp(['success' => true, 'message' => $id ? 'Updated' : 'Created', 'data' => $banner]);
        }

        api_resp(['success' => false, 'message' => 'Unknown action'], 400);
    }

    // fallthrough: method not allowed
    api_resp(['success' => false, 'message' => 'Method not allowed'], 405);

} catch (Throwable $e) {
    // log and return safe JSON
    server_log("Unhandled exception: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
    api_resp(['success' => false, 'message' => 'Server error'], 500);
}