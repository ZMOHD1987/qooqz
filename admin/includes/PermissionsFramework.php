<?php
declare(strict_types=1);

/**
 * Permissions Framework - Matrix Management Page
 */

// Load admin context
require_once __DIR__ . '/admin_context.php';

$payload = $GLOBALS['ADMIN_UI'] ?? [];
$user = $payload['user'] ?? [];
$permissions = $user['permissions'] ?? [];
$roles = $user['roles'] ?? [];
$csrf = $payload['csrf_token'] ?? '';

// Check permissions
$isSuperAdmin = in_array('super_admin', $roles, true);
$canManage = $isSuperAdmin || in_array('manage_permissions', $permissions);

if (!$canManage) {
    header('Location: /admin/dashboard.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= htmlspecialchars($csrf) ?>">
    <title>Permissions Management - Admin Panel</title>
    
    <!-- Styles -->
    <link rel="stylesheet" href="/admin/assets/css/admin_framework.css?v=<?= time() ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        .permissions-matrix {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.875rem;
        }

        .permissions-matrix th,
        .permissions-matrix td {
            padding: 0.75rem;
            border: 1px solid var(--border-color, #334155);
            text-align: center;
        }

        .permissions-matrix th {
            background: var(--background-secondary, #1e293b);
            color: var(--text-primary, #e2e8f0);
            font-weight: 600;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .permissions-matrix th.sticky-col {
            left: 0;
            z-index: 20;
        }

        .permissions-matrix td.sticky-col {
            position: sticky;
            left: 0;
            background: var(--background-primary, #0f172a);
            font-weight: 500;
            text-align: left;
            min-width: 200px;
        }

        .permission-row-header {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .permission-name {
            font-weight: 600;
            color: var(--primary-color, #3b82f6);
        }

        .resource-type {
            font-size: 0.75rem;
            color: var(--text-secondary, #94a3b8);
        }

        .permission-checkbox {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }

        .table-responsive {
            overflow-x: auto;
            max-height: 70vh;
        }

        .legend {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-top: 1.5rem;
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem;
            background: var(--background-secondary, #1e293b);
            border-radius: 6px;
            font-size: 0.875rem;
        }

        .legend-icon {
            color: var(--primary-color, #3b82f6);
        }

        .loading-state,
        .empty-state,
        .error-state {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 3rem;
            text-align: center;
        }

        .spinner {
            width: 48px;
            height: 48px;
            border: 4px solid rgba(59, 130, 246, 0.1);
            border-top-color: #3b82f6;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .empty-icon,
        .error-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
        }

        .empty-icon {
            color: var(--text-tertiary, #64748b);
        }

        .error-icon {
            color: var(--danger-color, #ef4444);
        }
    </style>
</head>
<body>
    
    <!-- Page Container -->
    <div class="page-container">
        
        <!-- Page Header -->
        <div class="page-header">
            <div class="page-header-content">
                <h1 class="page-title">
                    <i class="fas fa-shield-alt"></i>
                    Permissions Management
                </h1>
                <p class="page-subtitle">Manage resource-level permissions for all roles</p>
            </div>
            <div class="page-header-actions">
                <button id="btnSavePermissions" class="btn btn-primary" disabled>
                    <i class="fas fa-save"></i>
                    Save Changes
                </button>
            </div>
        </div>

        <!-- Matrix Card -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-table"></i>
                    Permissions Matrix
                </h3>
            </div>
            <div class="card-body">
                
                <!-- Loading State -->
                <div id="matrixLoading" class="loading-state">
                    <div class="spinner"></div>
                    <p style="margin-top: 1rem;">Loading permissions...</p>
                </div>

                <!-- Matrix Container -->
                <div id="matrixContainer" class="table-responsive" style="display:none">
                    <table class="permissions-matrix">
                        <thead>
                            <tr>
                                <th class="sticky-col">Permission / Resource</th>
                                <th><i class="fas fa-eye"></i><br>View All</th>
                                <th><i class="fas fa-user"></i><br>View Own</th>
                                <th><i class="fas fa-building"></i><br>View Tenant</th>
                                <th><i class="fas fa-plus"></i><br>Create</th>
                                <th><i class="fas fa-edit"></i><br>Edit All</th>
                                <th><i class="fas fa-user-edit"></i><br>Edit Own</th>
                                <th><i class="fas fa-trash"></i><br>Delete All</th>
                                <th><i class="fas fa-user-times"></i><br>Delete Own</th>
                            </tr>
                        </thead>
                        <tbody id="matrixBody"></tbody>
                    </table>
                </div>

                <!-- Empty State -->
                <div id="emptyState" class="empty-state" style="display:none">
                    <div class="empty-icon">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <h3>No Permissions Found</h3>
                    <p>No resource permissions configured yet</p>
                </div>

                <!-- Error State -->
                <div id="errorState" class="error-state" style="display:none">
                    <div class="error-icon">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <h3>Error Loading Permissions</h3>
                    <p id="errorMessage"></p>
                    <button id="btnRetry" class="btn btn-secondary">
                        <i class="fas fa-redo"></i>
                        Retry
                    </button>
                </div>

            </div>
        </div>

        <!-- Legend Card -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-info-circle"></i>
                    Legend
                </h3>
            </div>
            <div class="card-body">
                <div class="legend">
                    <div class="legend-item">
                        <i class="fas fa-eye legend-icon"></i>
                        <div>
                            <strong>View All:</strong><br>
                            Can view all records
                        </div>
                    </div>
                    <div class="legend-item">
                        <i class="fas fa-user legend-icon"></i>
                        <div>
                            <strong>View Own:</strong><br>
                            Can view only own records
                        </div>
                    </div>
                    <div class="legend-item">
                        <i class="fas fa-building legend-icon"></i>
                        <div>
                            <strong>View Tenant:</strong><br>
                            Can view tenant records
                        </div>
                    </div>
                    <div class="legend-item">
                        <i class="fas fa-plus legend-icon"></i>
                        <div>
                            <strong>Create:</strong><br>
                            Can create new records
                        </div>
                    </div>
                    <div class="legend-item">
                        <i class="fas fa-edit legend-icon"></i>
                        <div>
                            <strong>Edit All:</strong><br>
                            Can edit all records
                        </div>
                    </div>
                    <div class="legend-item">
                        <i class="fas fa-user-edit legend-icon"></i>
                        <div>
                            <strong>Edit Own:</strong><br>
                            Can edit only own records
                        </div>
                    </div>
                    <div class="legend-item">
                        <i class="fas fa-trash legend-icon"></i>
                        <div>
                            <strong>Delete All:</strong><br>
                            Can delete all records
                        </div>
                    </div>
                    <div class="legend-item">
                        <i class="fas fa-user-times legend-icon"></i>
                        <div>
                            <strong>Delete Own:</strong><br>
                            Can delete only own records
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- Load Scripts -->
    <script src="/admin/assets/js/admin_framework.js?v=<?= time() ?>"></script>
    <script src="/admin/assets/js/pages/PermissionsFramework.js?v=<?= time() ?>"></script>

    <script>
    // Manual initialization with debugging
    document.addEventListener('DOMContentLoaded', function() {
        console.log('%c════════════════════════════════════', 'color:#8b5cf6');
        console.log('%c[Permissions Page] Initializing...', 'color:#8b5cf6;font-weight:bold');
        console.log('%c════════════════════════════════════', 'color:#8b5cf6');
        
        // Check dependencies
        console.log('[Permissions Page] AdminFramework:', typeof AdminFramework);
        console.log('[Permissions Page] AF:', typeof AF);
        console.log('[Permissions Page] PermissionsFramework:', typeof PermissionsFramework);
        
        // Check elements
        const elements = {
            matrixLoading: document.getElementById('matrixLoading'),
            matrixContainer: document.getElementById('matrixContainer'),
            emptyState: document.getElementById('emptyState'),
            errorState: document.getElementById('errorState'),
            matrixBody: document.getElementById('matrixBody'),
            btnSave: document.getElementById('btnSavePermissions'),
            btnRetry: document.getElementById('btnRetry')
        };
        
        console.log('[Permissions Page] Elements check:', {
            matrixLoading: !!elements.matrixLoading,
            matrixContainer: !!elements.matrixContainer,
            emptyState: !!elements.emptyState,
            errorState: !!elements.errorState,
            matrixBody: !!elements.matrixBody,
            btnSave: !!elements.btnSave,
            btnRetry: !!elements.btnRetry
        });
        
        // Initialize
        if (typeof PermissionsFramework !== 'undefined') {
            console.log('[Permissions Page] Starting initialization...');
            try {
                PermissionsFramework.init();
                console.log('%c[Permissions Page] ✅ Initialized successfully!', 'color:#10b981;font-weight:bold');
            } catch (e) {
                console.error('[Permissions Page] ❌ Initialization error:', e);
            }
        } else {
            console.error('[Permissions Page] ❌ PermissionsFramework not found!');
            
            // Show error
            if (elements.matrixLoading) elements.matrixLoading.style.display = 'none';
            if (elements.errorState) {
                elements.errorState.style.display = 'flex';
                const msg = elements.errorState.querySelector('#errorMessage');
                if (msg) msg.textContent = 'PermissionsFramework.js failed to load';
            }
        }
        
        console.log('%c════════════════════════════════════', 'color:#8b5cf6');
    });
    </script>

</body>
</html>