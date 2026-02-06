<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/admin_context.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Permissions Test - Admin Panel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Monaco', 'Courier New', monospace;
            background: #0f172a;
            color: #e2e8f0;
            padding: 2rem;
            line-height: 1.6;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
        }

        h1 {
            color: #3b82f6;
            margin-bottom: 0.5rem;
            font-size: 2rem;
        }

        .subtitle {
            color: #94a3b8;
            margin-bottom: 2rem;
        }

        .section {
            background: #1e293b;
            border: 1px solid #334155;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .section h2 {
            color: #60a5fa;
            margin-bottom: 1rem;
            font-size: 1.25rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .code-block {
            background: #0f172a;
            border: 1px solid #334155;
            border-radius: 6px;
            padding: 1rem;
            margin-top: 1rem;
            overflow-x: auto;
            font-size: 0.8125rem;
        }

        .code-block pre {
            margin: 0;
            white-space: pre-wrap;
            word-wrap: break-word;
        }

        .success {
            color: #10b981;
        }

        .error {
            color: #ef4444;
        }

        .warning {
            color: #f59e0b;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
            font-size: 0.875rem;
        }

        th, td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid #334155;
        }

        th {
            background: #0f172a;
            color: #60a5fa;
            font-weight: 600;
        }

        tr:hover {
            background: rgba(59, 130, 246, 0.05);
        }

        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .status-success {
            background: #10b981;
            color: white;
        }

        .status-error {
            background: #ef4444;
            color: white;
        }

        .permission-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 0.5rem;
            margin-top: 1rem;
        }

        .permission-item {
            background: #0f172a;
            padding: 0.75rem;
            border-radius: 4px;
            font-size: 0.8125rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .permission-item i {
            color: #10b981;
        }

        .check-result {
            display: inline-block;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            font-weight: 600;
            margin-left: 0.5rem;
        }

        .check-true {
            background: #10b981;
            color: white;
        }

        .check-false {
            background: #ef4444;
            color: white;
        }

        .btn {
            display: inline-block;
            padding: 0.75rem 1.5rem;
            background: #3b82f6;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 500;
            transition: all 0.2s;
            border: none;
            cursor: pointer;
            font-size: 0.875rem;
            margin-right: 0.5rem;
        }

        .btn:hover {
            background: #2563eb;
        }
    </style>
</head>
<body>
    <div class="container">
        
        <h1><i class="fas fa-shield-check"></i> Permissions System Test</h1>
        <p class="subtitle">Complete test for resource permissions and role-based access control</p>

        <div style="margin-bottom:2rem;">
            <a href="/admin/dashboard.php" class="btn">
                <i class="fas fa-home"></i> Dashboard
            </a>
            <a href="?" class="btn" style="background:#10b981;">
                <i class="fas fa-sync"></i> Refresh
            </a>
        </div>

        <!-- Session Info -->
        <div class="section">
            <h2><i class="fas fa-fingerprint"></i> Session Information</h2>
            
            <p><strong>Session ID:</strong> <?= session_id() ?></p>
            
            <h3 style="margin-top:1.5rem;color:#60a5fa;">Raw $_SESSION:</h3>
            <div class="code-block">
                <pre><?= htmlspecialchars(print_r($_SESSION, true)) ?></pre>
            </div>
        </div>

        <!-- Admin Context -->
        <div class="section">
            <h2><i class="fas fa-code"></i> ADMIN_UI Context</h2>
            <div class="code-block">
                <pre><?= htmlspecialchars(print_r($GLOBALS['ADMIN_UI'] ?? [], true)) ?></pre>
            </div>
        </div>

        <!-- Current User -->
        <div class="section">
            <h2><i class="fas fa-user"></i> Current User</h2>
            
            <?php $user = admin_user(); ?>
            
            <table>
                <thead>
                    <tr>
                        <th>Property</th>
                        <th>Value</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>ID</strong></td>
                        <td><?= $user['id'] ?? 'N/A' ?></td>
                    </tr>
                    <tr>
                        <td><strong>Username</strong></td>
                        <td><?= htmlspecialchars($user['username'] ?? 'N/A') ?></td>
                    </tr>
                    <tr>
                        <td><strong>Email</strong></td>
                        <td><?= htmlspecialchars($user['email'] ?? 'N/A') ?></td>
                    </tr>
                    <tr>
                        <td><strong>Role ID</strong></td>
                        <td><?= $user['role_id'] ?? 'N/A' ?></td>
                    </tr>
                    <tr>
                        <td><strong>Tenant ID</strong></td>
                        <td><?= $user['tenant_id'] ?? 'N/A' ?></td>
                    </tr>
                    <tr>
                        <td><strong>Is Super Admin</strong></td>
                        <td>
                            <?php if (is_super_admin()): ?>
                                <span class="status-badge status-success">YES</span>
                            <?php else: ?>
                                <span class="status-badge status-error">NO</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- User Roles -->
        <div class="section">
            <h2><i class="fas fa-shield-alt"></i> User Roles</h2>
            
            <?php 
            $roles = admin_roles();
            if (!empty($roles)): 
            ?>
                <p><strong>Total Roles: <?= count($roles) ?></strong></p>
                <div class="permission-grid">
                    <?php foreach ($roles as $role): ?>
                        <div class="permission-item">
                            <i class="fas fa-user-shield"></i>
                            <span><?= htmlspecialchars($role) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="warning">⚠ No roles assigned</p>
            <?php endif; ?>

            <h3 style="margin-top:1.5rem;color:#60a5fa;">Raw Data:</h3>
            <div class="code-block">
                <pre><?= htmlspecialchars(print_r($roles, true)) ?></pre>
            </div>
        </div>

        <!-- User Permissions -->
        <div class="section">
            <h2><i class="fas fa-key"></i> User Permissions</h2>
            
            <?php 
            $permissions = admin_permissions();
            if (!empty($permissions)): 
            ?>
                <p><strong>Total Permissions: <?= count($permissions) ?></strong></p>
                <div class="permission-grid">
                    <?php foreach ($permissions as $perm): ?>
                        <div class="permission-item">
                            <i class="fas fa-check-circle"></i>
                            <span><?= htmlspecialchars($perm) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="warning">⚠ No permissions assigned</p>
            <?php endif; ?>

            <h3 style="margin-top:1.5rem;color:#60a5fa;">Raw Data:</h3>
            <div class="code-block">
                <pre><?= htmlspecialchars(print_r($permissions, true)) ?></pre>
            </div>
        </div>

        <!-- Permission Checks -->
        <div class="section">
            <h2><i class="fas fa-check-double"></i> Permission Checks</h2>
            
            <?php
            $checksToTest = [
                'manage_users',
                'manage_permissions',
                'view_users',
                'create_users',
                'edit_users',
                'delete_users',
                'manage_roles',
                'view_reports',
                'super_admin',
            ];
            ?>

            <table>
                <thead>
                    <tr>
                        <th>Permission</th>
                        <th>Result</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($checksToTest as $perm): ?>
                        <tr>
                            <td><code><?= htmlspecialchars($perm) ?></code></td>
                            <td>
                                <?php if (can($perm)): ?>
                                    <span class="check-result check-true">
                                        <i class="fas fa-check"></i> GRANTED
                                    </span>
                                <?php else: ?>
                                    <span class="check-result check-false">
                                        <i class="fas fa-times"></i> DENIED
                                    </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Resource Permissions from Database -->
        <div class="section">
            <h2><i class="fas fa-database"></i> Resource Permissions (from DB)</h2>
            
            <?php
            $pdo = admin_db();
            if ($pdo instanceof PDO && admin_user_id() > 0):
                try {
                    $userId = admin_user_id();
                    
                    // Get user's role_id
                    $stmt = $pdo->prepare("SELECT role_id FROM users WHERE id = ?");
                    $stmt->execute([$userId]);
                    $userRoleId = $stmt->fetchColumn();
                    
                    if ($userRoleId) {
                        // Get resource permissions
                        $stmt = $pdo->prepare("
                            SELECT 
                                p.key_name,
                                p.display_name,
                                rp.resource_type,
                                rp.can_view_all,
                                rp.can_view_own,
                                rp.can_view_tenant,
                                rp.can_create,
                                rp.can_edit_all,
                                rp.can_edit_own,
                                rp.can_delete_all,
                                rp.can_delete_own
                            FROM resource_permissions rp
                            INNER JOIN permissions p ON p.id = rp.permission_id
                            INNER JOIN role_permissions role_perm ON role_perm.permission_id = p.id
                            WHERE role_perm.role_id = ?
                            ORDER BY rp.resource_type, p.key_name
                        ");
                        $stmt->execute([$userRoleId]);
                        $resourcePerms = $stmt->fetchAll();
                        
                        if ($resourcePerms):
            ?>
                            <p><strong>Total: <?= count($resourcePerms) ?> resource permissions</strong></p>
                            
                            <div style="overflow-x:auto;">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Permission</th>
                                            <th>Resource</th>
                                            <th>View All</th>
                                            <th>View Own</th>
                                            <th>View Tenant</th>
                                            <th>Create</th>
                                            <th>Edit All</th>
                                            <th>Edit Own</th>
                                            <th>Delete All</th>
                                            <th>Delete Own</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($resourcePerms as $rp): ?>
                                        <tr>
                                            <td><strong><?= htmlspecialchars($rp['key_name']) ?></strong></td>
                                            <td><?= htmlspecialchars($rp['resource_type']) ?></td>
                                            <td><?= $rp['can_view_all'] ? '<span class="success">✓</span>' : '<span class="error">✗</span>' ?></td>
                                            <td><?= $rp['can_view_own'] ? '<span class="success">✓</span>' : '<span class="error">✗</span>' ?></td>
                                            <td><?= $rp['can_view_tenant'] ? '<span class="success">✓</span>' : '<span class="error">✗</span>' ?></td>
                                            <td><?= $rp['can_create'] ? '<span class="success">✓</span>' : '<span class="error">✗</span>' ?></td>
                                            <td><?= $rp['can_edit_all'] ? '<span class="success">✓</span>' : '<span class="error">✗</span>' ?></td>
                                            <td><?= $rp['can_edit_own'] ? '<span class="success">✓</span>' : '<span class="error">✗</span>' ?></td>
                                            <td><?= $rp['can_delete_all'] ? '<span class="success">✓</span>' : '<span class="error">✗</span>' ?></td>
                                            <td><?= $rp['can_delete_own'] ? '<span class="success">✓</span>' : '<span class="error">✗</span>' ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
            <?php
                        else:
                            echo '<p class="warning">⚠ No resource permissions found</p>';
                        endif;
                    } else {
                        echo '<p class="error">✗ User has no role assigned</p>';
                    }
                    
                } catch (Exception $e) {
                    echo '<p class="error">✗ Error: ' . htmlspecialchars($e->getMessage()) . '</p>';
                }
            else:
                echo '<p class="warning">⚠ Database not available or user not logged in</p>';
            endif;
            ?>
        </div>

        <!-- Helper Functions Test -->
        <div class="section">
            <h2><i class="fas fa-function"></i> Helper Functions Test</h2>
            
            <table>
                <thead>
                    <tr>
                        <th>Function</th>
                        <th>Result</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><code>admin_user_id()</code></td>
                        <td><?= admin_user_id() ?></td>
                    </tr>
                    <tr>
                        <td><code>admin_username()</code></td>
                        <td><?= htmlspecialchars(admin_username()) ?></td>
                    </tr>
                    <tr>
                        <td><code>admin_tenant_id()</code></td>
                        <td><?= admin_tenant_id() ?></td>
                    </tr>
                    <tr>
                        <td><code>is_admin_logged_in()</code></td>
                        <td><?= is_admin_logged_in() ? '<span class="success">TRUE</span>' : '<span class="error">FALSE</span>' ?></td>
                    </tr>
                    <tr>
                        <td><code>is_super_admin()</code></td>
                        <td><?= is_super_admin() ? '<span class="success">TRUE</span>' : '<span class="error">FALSE</span>' ?></td>
                    </tr>
                    <tr>
                        <td><code>admin_lang()</code></td>
                        <td><?= htmlspecialchars(admin_lang()) ?></td>
                    </tr>
                    <tr>
                        <td><code>admin_dir()</code></td>
                        <td><?= htmlspecialchars(admin_dir()) ?></td>
                    </tr>
                    <tr>
                        <td><code>admin_csrf()</code></td>
                        <td><code style="font-size:0.7rem;"><?= htmlspecialchars(substr(admin_csrf(), 0, 20)) ?>...</code></td>
                    </tr>
                </tbody>
            </table>
        </div>

    </div>

    <script>
        console.log('%c════════════════════════════════════', 'color:#3b82f6');
        console.log('%cPermissions Test Page', 'color:#10b981;font-weight:bold;font-size:16px');
        console.log('%c════════════════════════════════════', 'color:#3b82f6');
        console.log('User:', <?= json_encode(admin_user()) ?>);
        console.log('Roles:', <?= json_encode(admin_roles()) ?>);
        console.log('Permissions:', <?= json_encode(admin_permissions()) ?>);
        console.log('Is Super Admin:', <?= is_super_admin() ? 'true' : 'false' ?>);
        console.log('%c════════════════════════════════════', 'color:#3b82f6');
    </script>
</body>
</html>