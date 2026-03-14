<?php
/**
 * Standalone Theme Loader - Simplified Version for Testing
 */

// Simple configuration
$apiBase = '/api';
$lang = 'en';
$dir = 'ltr';
$csrf = bin2hex(random_bytes(16));
$tenantId = 1;
$userId = 1;

// Permissions (set all to true for testing)
$canCreate = true;
$canEdit = true;
$canDelete = true;
$canExport = true;
$canActivate = true;
$isSuperAdmin = true;
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>" dir="<?= $dir ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Theme Manager - Standalone Test</title>
    
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Toastr -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
    
    <!-- Color Picker -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-colorpicker/3.4.0/css/bootstrap-colorpicker.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="/admin/assets/css/pages/AdminUiThemeLoader.css">
</head>
<body>
    <div class="container-fluid py-4">
        <h1 class="mb-4">Theme Manager - Standalone Test</h1>
        
        <!-- Debug Info -->
        <div class="alert alert-info">
            <h5>Debug Information:</h5>
            <ul>
                <li>API Base: <code><?= $apiBase ?></code></li>
                <li>Tenant ID: <code><?= $tenantId ?></code></li>
                <li>User ID: <code><?= $userId ?></code></li>
                <li>Permissions: All enabled for testing</li>
            </ul>
            <p class="mb-0"><strong>Check browser console (F12) for detailed logs</strong></p>
        </div>
        
        <!-- Theme Manager App Container -->
        <div id="themeManagerApp">
            <!-- Loading State -->
            <div id="loadingState" class="text-center py-5">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-3">Loading themes...</p>
            </div>
            
            <!-- Empty State -->
            <div id="emptyState" class="text-center py-5" style="display:none;">
                <i class="fas fa-palette fa-3x text-muted mb-3"></i>
                <h3>No themes found</h3>
                <p class="text-muted">Create your first theme to get started</p>
            </div>
            
            <!-- Error State -->
            <div id="errorState" class="alert alert-danger" style="display:none;">
                <h4>Error Loading Themes</h4>
                <p id="errorMessage"></p>
            </div>
            
            <!-- Table Container -->
            <div id="tableContainer" style="display:none;">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Author</th>
                            <th>Version</th>
                            <th>Status</th>
                            <th>Colors</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="tableBody"></tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <div id="tableFooter" class="d-flex justify-content-between align-items-center mt-3" style="display:none;">
                <div id="paginationInfo"></div>
                <div id="pagination"></div>
            </div>
        </div>
    </div>

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    
    <!-- Bootstrap -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Toastr -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
    
    <!-- Color Picker -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-colorpicker/3.4.0/js/bootstrap-colorpicker.min.js"></script>
    
    <!-- APP_CONFIG -->
    <script>
    window.APP_CONFIG = {
        API_BASE: '<?= $apiBase ?>',
        TENANT_ID: <?= $tenantId ?>,
        CSRF_TOKEN: '<?= $csrf ?>',
        USER_LANGUAGE: '<?= $lang ?>',
        USER_DIRECTION: '<?= $dir ?>',
        USER_ID: <?= $userId ?>,
        PERMISSIONS: {
            canCreate: <?= $canCreate ? 'true' : 'false' ?>,
            canEdit: <?= $canEdit ? 'true' : 'false' ?>,
            canDelete: <?= $canDelete ? 'true' : 'false' ?>,
            canExport: <?= $canExport ? 'true' : 'false' ?>,
            canActivate: <?= $canActivate ? 'true' : 'false' ?>,
            isSuperAdmin: <?= $isSuperAdmin ? 'true' : 'false' ?>
        },
        TRANSLATIONS: {
            processing: 'Processing...',
            saving: 'Saving...',
            deleting: 'Deleting...',
            loading: 'Loading...',
            confirm_delete_multiple: 'Are you sure you want to delete {count} themes?',
            no_themes_selected: 'No themes selected',
            select_at_least_one: 'Please select at least one theme',
            themes_activated: '{count} themes activated',
            themes_deactivated: '{count} themes deactivated',
            themes_deleted: '{count} themes deleted'
        },
        ENDPOINTS: {
            themes: '<?= $apiBase ?>/themes',
            design_settings: '<?= $apiBase ?>/design_settings',
            color_settings: '<?= $apiBase ?>/color_settings',
            font_settings: '<?= $apiBase ?>/font_settings',
            button_styles: '<?= $apiBase ?>/button_styles',
            card_styles: '<?= $apiBase ?>/card_styles',
            system_settings: '<?= $apiBase ?>/system_settings',
            tenants: '<?= $apiBase ?>/tenants',
            roles: '<?= $apiBase ?>/roles'
        }
    };

    // Toastr config
    toastr.options = {
        closeButton: true,
        progressBar: true,
        positionClass: "toast-top-right",
        preventDuplicates: true,
        timeOut: 5000
    };

    console.log('=== STANDALONE TEST PAGE ===');
    console.log('APP_CONFIG loaded:', window.APP_CONFIG);
    </script>

    <!-- Theme Manager Script -->
    <script src="/admin/assets/js/pages/AdminUiThemeLoader.js"></script>
</body>
</html>
