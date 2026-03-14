<?php
// htdocs/api/helpers/get_user_permissions.php
header('Content-Type: application/json; charset=utf-8');
session_start();

$dbPath = __DIR__ . '/../config/db.php';
if (!file_exists($dbPath)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'DB config not found']);
    exit;
}
require_once $dbPath;

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$currentUserId = (int)$_SESSION['user_id'];

try {
    // جلب بيانات المستخدم مع الدور
    $stmt = $conn->prepare("
        SELECT u.id, u.username, u.email, u.is_active, u.role_id,
               r.key_name AS role_key, r.display_name AS role_display_name
        FROM users u
        LEFT JOIN roles r ON r.id = u.role_id
        WHERE u.id = ?
        LIMIT 1
    ");
    $stmt->bind_param('i', $currentUserId);
    $stmt->execute();
    $userResult = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$userResult) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit;
    }

    $user = [
        'id' => $userResult['id'],
        'username' => $userResult['username'],
        'email' => $userResult['email'],
        'is_active' => $userResult['is_active']
    ];

    $roles = [];
    if ($userResult['role_id']) {
        $roles[] = [
            'id' => (int)$userResult['role_id'],
            'key_name' => $userResult['role_key'],
            'display_name' => $userResult['role_display_name']
        ];
    }

    // جلب الصلاحيات إذا كان هناك دور
    $permissions = [];
    $permissionMap = [];
    if ($userResult['role_id']) {
        $stmt = $conn->prepare("
            SELECT DISTINCT p.key_name, p.display_name, p.description
            FROM role_permissions rp
            JOIN permissions p ON p.id = rp.permission_id
            WHERE rp.role_id = ?
        ");
        $stmt->bind_param('i', $userResult['role_id']);
        $stmt->execute();
        $permRes = $stmt->get_result();
        $permissions = $permRes->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        foreach ($permissions as $p) {
            $permissionMap[$p['key_name']] = true;
        }
    }

    echo json_encode([
        'success' => true,
        'user' => $user,
        'roles' => $roles,
        'permissions' => $permissionMap,
        'permissions_full' => $permissions
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error',
        'debug' => $e->getMessage()  // احذف هذا في الإنتاج
    ]);
}