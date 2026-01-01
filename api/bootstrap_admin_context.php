<?php
/**
 * Lightweight Admin Context Bootstrap
 * Compatible with shared/free hosting
 */

// ===== SESSION =====
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// ===== DB CONFIG =====
$db = null;
$dbFile = __DIR__ . '/config/db.php';

if (is_file($dbFile)) {
    require_once $dbFile;
}

if (function_exists('connectDB')) {
    $tmp = connectDB();
    if ($tmp instanceof mysqli) {
        $db = $tmp;
        @$db->set_charset('utf8mb4');
    }
}

// ===== CURRENT USER =====
$currentUser = null;

// 1) from session user array
if (!empty($_SESSION['user']) && is_array($_SESSION['user'])) {
    $currentUser = $_SESSION['user'];
}

// 2) from session user_id (NO get_result)
elseif (!empty($_SESSION['user_id']) && $db) {
    $uid = (int)$_SESSION['user_id'];

    if ($stmt = $db->prepare("SELECT id, username, email, role_id, preferred_language FROM users WHERE id = ? LIMIT 1")) {
        $stmt->bind_param('i', $uid);
        if ($stmt->execute()) {
            $stmt->bind_result($id, $username, $email, $role_id, $preferred_language);
            if ($stmt->fetch()) {
                $currentUser = [
                    'id' => $id,
                    'username' => $username,
                    'email' => $email,
                    'role_id' => $role_id,
                    'preferred_language' => $preferred_language
                ];
            }
        }
        $stmt->close();
    }
}

// ===== GLOBAL EXPORT =====
$GLOBALS['ADMIN_DB']   = $db;
$GLOBALS['ADMIN_USER'] = $currentUser;
