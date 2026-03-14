<?php
declare(strict_types=1);

if (!defined('API_ENTRY')) {
    http_response_code(403);
    exit('Direct access not allowed');
}

require_once __DIR__ . '/shared/helpers/jwt.php';
require_once __DIR__ . '/shared/core/DatabaseConnection.php';
require_once __DIR__ . '/shared/helpers/RBAC.php';

$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

if (!preg_match('/Bearer\s+(.+)/i', $authHeader, $m)) {
    Response::unauthorized('Missing token');
}

$token = $m[1];
$payload = jwt_decode($token);

if (!$payload || empty($payload['uid'])) {
    Response::unauthorized('Invalid token');
}

$pdo = DatabaseConnection::get();

$stmt = $pdo->prepare("SELECT * FROM users WHERE id=? AND is_active=1");
$stmt->execute([(int)$payload['uid']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    Response::unauthorized('User not found');
}

$rbac = new RBAC($pdo);
$rbacData = $rbac->loadPermissionsForUser((int)$user['id']);

$GLOBALS['MOBILE_USER']  = $user;
$GLOBALS['MOBILE_DB']    = $pdo;
$GLOBALS['MOBILE_ROLES'] = $rbacData['roles'];
$GLOBALS['MOBILE_PERMS'] = $rbacData['permissions'];

$lang = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? 'en';
$GLOBALS['MOBILE_LANG'] = substr($lang, 0, 2);
