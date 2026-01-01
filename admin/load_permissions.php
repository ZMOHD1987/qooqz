<?php
// htdocs/admin/load_permissions.php
// وظائف لمَنهَج تحميل صلاحيات المستخدم إلى الجلسة والتحقق منها.
// include هذا الملف في authenticate.php و في الصفحات التي تحتاج تحققاً من صلاحية.

if (session_status() === PHP_SESSION_NONE) @session_start();

/**
 * load_user_permissions_into_session(mysqli $conn, int $user_id)
 * يعيد ملء $_SESSION['permissions'] كمصفوفة مفاتيح صلاحية (key_name).
 */
function load_user_permissions_into_session(mysqli $conn, int $user_id) {
    // افرغ أي صلاحيات سابقة
    $_SESSION['permissions'] = [];
    $_SESSION['permissions_map'] = [];

    // اجلب role_id من users
    $sql = "SELECT role_id FROM users WHERE id = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    if (!$stmt) return false;
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    $stmt->close();

    $role_id = $row['role_id'] ?? null;
    $_SESSION['role_id'] = $role_id ? (int)$role_id : null;

    // من role_permissions
    if ($role_id) {
        $sql = "SELECT p.key_name FROM role_permissions rp JOIN permissions p ON p.id = rp.permission_id WHERE rp.role_id = ? ORDER BY p.id";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param('i', $role_id);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($r = $res->fetch_assoc()) {
                $k = $r['key_name'];
                if ($k) {
                    $_SESSION['permissions'][] = $k;
                    $_SESSION['permissions_map'][$k] = true;
                }
            }
            $stmt->close();
        }
    }

    // أيضاً افحص جدول user_roles لو موجود (دعم للأدوار المتعددة)
    $has = $conn->query("SHOW TABLES LIKE 'user_roles'");
    if ($has && $has->num_rows > 0) {
        $sql = "SELECT ur.role_id FROM user_roles ur WHERE ur.user_id = ?";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param('i', $user_id);
            $stmt->execute();
            $res = $stmt->get_result();
            $roleIds = [];
            while ($r = $res->fetch_assoc()) $roleIds[] = (int)$r['role_id'];
            $stmt->close();
            if (!empty($roleIds)) {
                $placeholders = implode(',', array_fill(0, count($roleIds), '?'));
                $types = str_repeat('i', count($roleIds));
                // Prepared statement with dynamic params
                $sql = "SELECT p.key_name FROM role_permissions rp JOIN permissions p ON p.id = rp.permission_id WHERE rp.role_id IN ($placeholders)";
                $stmt = $conn->prepare($sql);
                if ($stmt) {
                    // bind params dynamically
                    $refArr = [];
                    $refArr[] = & $types;
                    foreach ($roleIds as $i => $val) {
                        $refArr[] = & $roleIds[$i];
                    }
                    // Note: in some PHP versions, bind_param with dynamic params requires call_user_func_array
                    call_user_func_array([$stmt, 'bind_param'], $refArr);
                    $stmt->execute();
                    $res = $stmt->get_result();
                    while ($r = $res->fetch_assoc()) {
                        $k = $r['key_name'];
                        if ($k && empty($_SESSION['permissions_map'][$k])) {
                            $_SESSION['permissions'][] = $k;
                            $_SESSION['permissions_map'][$k] = true;
                        }
                    }
                    $stmt->close();
                }
            }
        }
    }

    // إزالة التكرارات (أمان)
    $_SESSION['permissions'] = array_values(array_unique($_SESSION['permissions']));
    return true;
}

/**
 * user_has_permission(string $perm_key): bool
 */
function user_has_permission(string $perm_key): bool {
    if (session_status() === PHP_SESSION_NONE) @session_start();
    if (!isset($_SESSION['permissions_map'])) return false;
    return !empty($_SESSION['permissions_map'][$perm_key]);
}