<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) @session_start();

$pdo = $GLOBALS['ADMIN_DB'] ?? null;
$user = $_SESSION['user'] ?? null;

if (!$pdo instanceof PDO || !$user) {
    ResponseFormatter::error('User or database not initialized', 500);
    exit;
}

// جلب جميع tenants التي ينتمي إليها المستخدم
$stmt = $pdo->prepare("
    SELECT tu.tenant_id, tu.role_id, tu.is_active AS tenant_user_active,
           t.name AS tenant_name, t.domain AS tenant_domain,
           r.key_name AS role_key, r.display_name AS role_name
    FROM tenant_users tu
    JOIN tenants t ON tu.tenant_id = t.id
    LEFT JOIN roles r ON tu.role_id = r.id
    WHERE tu.user_id = :uid
");
$stmt->execute([':uid' => $user['id']]);
$tenantsData = $stmt->fetchAll(PDO::FETCH_ASSOC);

// جلب الصلاحيات لكل دور ضمن الـ tenants
$rolesPermissions = [];
foreach ($tenantsData as $td) {
    $roleId = $td['role_id'];
    $tenantId = $td['tenant_id'];
    if ($roleId) {
        $permStmt = $pdo->prepare("
            SELECT p.key_name
            FROM role_permissions rp
            JOIN permissions p ON rp.permission_id = p.id
            WHERE rp.role_id = :role_id AND rp.tenant_id = :tenant_id
        ");
        $permStmt->execute([':role_id' => $roleId, ':tenant_id' => $tenantId]);
        $perms = $permStmt->fetchAll(PDO::FETCH_COLUMN);
        $rolesPermissions[$tenantId] = $perms;
    }
}

// إعداد الرد النهائي
$response = [
    'ok' => true,
    'module' => 'admin',
    'db_connected' => true,
    'user' => [
        'id' => $user['id'],
        'username' => $user['username'],
        'email' => $user['email'],
        'preferred_language' => $user['preferred_language'] ?? 'ar',
        'is_active' => $user['is_active'] ?? true,
        'tenants' => []
    ]
];

foreach ($tenantsData as $td) {
    $tid = $td['tenant_id'];
    $response['user']['tenants'][] = [
        'tenant_id' => $tid,
        'tenant_name' => $td['tenant_name'],
        'tenant_domain' => $td['tenant_domain'],
        'role_id' => $td['role_id'],
        'role_key' => $td['role_key'],
        'role_name' => $td['role_name'],
        'is_active' => (bool)$td['tenant_user_active'],
        'permissions' => $rolesPermissions[$tid] ?? []
    ];
}

ResponseFormatter::success($response);