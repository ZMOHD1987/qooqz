<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

$user = $GLOBALS['ADMIN_USER'];

ResponseFormatter::success([
    'ok' => true,
    'db_connected' => $GLOBALS['ADMIN_DB'] !== null,
    'user_lang' => $user['preferred_language'] ?? 'ar',
    'user_direction' => in_array($user['preferred_language'] ?? 'ar', ['ar','fa','ur']) ? 'rtl' : 'ltr',
    'detected_module' => 'BootstrapAdminUi',
    'languages_dir' => LANGUAGES_PATH ?? null,
    'strings_count' => function_exists('i18n_count') ? i18n_count() : 0,

    'user_info' => [
        'id' => $user['id'],
        'username' => $user['username'],
        'email' => $user['email'],
        'preferred_language' => $user['preferred_language'] ?? 'ar',
        'role_id' => $user['role_id'] ?? null,
        'roles' => $_SESSION['roles'] ?? [],
        'permissions' => $_SESSION['permissions'] ?? [],
        'permissions_count' => count($_SESSION['permissions'] ?? []),
        'roles_count' => count($_SESSION['roles'] ?? []),
        'is_active' => true,
    ],

    'session_roles' => $_SESSION['roles'] ?? [],
    'session_permissions' => $_SESSION['permissions'] ?? [],
]);
