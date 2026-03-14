<?php
declare(strict_types=1);

/**
 * Tenants Management Page
 */

require_once __DIR__ . '/../includes/PermissionsFramework.php';

$payload = $GLOBALS['ADMIN_UI'] ?? [];
$user = $payload['user'] ?? [];
$permissions = $user['permissions'] ?? [];
$roles = $user['roles'] ?? [];
$csrf = $payload['csrf_token'] ?? '';

// Check permissions
$isSuperAdmin = in_array('super_admin', $roles, true);
$canView = $isSuperAdmin || in_array('manage_tenants', $permissions) || in_array('view_tenants', $permissions);
$canCreate = $isSuperAdmin || in_array('manage_tenants', $permissions);
$canEdit = $isSuperAdmin || in_array('manage_tenants', $permissions);
$canDelete = $isSuperAdmin || in_array('manage_tenants', $permissions);

if (!$canView) {
    header('Location: /admin/dashboard.php');
    exit;
}
?>

<!-- Page Container -->
<div class="page-container">
    
    <!-- Page Header -->
    <div class="page-header">
        <div class="page-header-content">
            <h1 class="page-title">
                <i class="fas fa-building"></i>
                Tenants Management
            </h1>
            <p class="page-subtitle">Manage multi-tenant organizations and their configurations</p>
        </div>
        <div class="page-header-actions">
            <?php if ($canCreate): ?>
            <button id="btnAddTenant" class="btn btn-primary">
                <i class="fas fa-plus"></i>
                Add Tenant
            </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Filters Card -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-filter"></i>
                Filters
            </h3>
        </div>
        <div class="card-body">
            <div class="filters-grid">
                <div class="form-group">
                    <label for="searchInput">
                        <i class="fas fa-search"></i>
                        Search
                    </label>
                    <input type="text" 
                           id="searchInput" 
                           class="form-control" 
                           placeholder="Search by name or domain...">
                </div>
                
                <div class="form-group">
                    <label for="statusFilter">
                        <i class="fas fa-toggle-on"></i>
                        Status
                    </label>
                    <select id="statusFilter" class="form-control">
                        <option value="">All Statuses</option>
                        <option value="active">Active</option>
                        <option value="suspended">Suspended</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>&nbsp;</label>
                    <div class="filter-actions">
                        <button id="btnApplyFilters" class="btn btn-secondary">
                            <i class="fas fa-check"></i>
                            Apply
                        </button>
                        <button id="btnResetFilters" class="btn btn-outline">
                            <i class="fas fa-undo"></i>
                            Reset
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Table Card -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-table"></i>
                Tenants List
            </h3>
            <div class="card-actions">
                <button id="btnRefresh" class="btn btn-sm btn-outline">
                    <i class="fas fa-sync-alt"></i>
                    Refresh
                </button>
            </div>
        </div>
        <div class="card-body">
            
            <!-- Loading State -->
            <div id="tableLoading" class="loading-state">
                <div class="spinner"></div>
                <p>Loading tenants...</p>
            </div>

            <!-- Table Container -->
            <div id="tableContainer" style="display:none">
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Domain</th>
                                <th>Owner</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="tableBody"></tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="table-footer">
                    <div id="paginationInfo" class="pagination-info"></div>
                    <div id="pagination" class="pagination"></div>
                </div>
            </div>

            <!-- Empty State -->
            <div id="emptyState" class="empty-state" style="display:none">
                <div class="empty-icon">
                    <i class="fas fa-building"></i>
                </div>
                <h3>No Tenants Found</h3>
                <p>No tenants match your current filters</p>
                <?php if ($canCreate): ?>
                <button class="btn btn-primary" onclick="Tenants.add()">
                    <i class="fas fa-plus"></i>
                    Create First Tenant
                </button>
                <?php endif; ?>
            </div>

            <!-- Error State -->
            <div id="errorState" class="error-state" style="display:none">
                <div class="error-icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <h3>Error Loading Tenants</h3>
                <p id="errorMessage"></p>
                <button id="btnRetry" class="btn btn-secondary">
                    <i class="fas fa-redo"></i>
                    Retry
                </button>
            </div>

        </div>
    </div>

    <!-- Form Card (Hidden by default) -->
    <div id="tenantFormContainer" class="card form-card" style="display:none">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-building"></i>
                <span id="formTitle">Add Tenant</span>
            </h3>
            <button id="btnCloseForm" class="btn-close" type="button">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="card-body">
            <form id="tenantForm" novalidate>
                <input type="hidden" id="formId" name="id">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">

                <div class="form-grid">
                    
                    <!-- Name -->
                    <div class="form-group">
                        <label for="formName" class="required">
                            <i class="fas fa-signature"></i>
                            Tenant Name
                        </label>
                        <input type="text" 
                               id="formName" 
                               name="name" 
                               class="form-control" 
                               placeholder="Enter tenant name..."
                               required
                               minlength="3"
                               maxlength="150">
                        <small class="form-text">Minimum 3 characters, maximum 150</small>
                        <div class="invalid-feedback">Please enter a valid tenant name</div>
                    </div>

                    <!-- Domain -->
                    <div class="form-group">
                        <label for="formDomain">
                            <i class="fas fa-globe"></i>
                            Domain
                        </label>
                        <input type="text" 
                               id="formDomain" 
                               name="domain" 
                               class="form-control" 
                               placeholder="e.g., acme-corp"
                               pattern="^[a-z0-9.-]+$"
                               maxlength="255">
                        <small class="form-text">Lowercase letters, numbers, dots, and hyphens only</small>
                        <div class="invalid-feedback">Please enter a valid domain</div>
                    </div>

                    <!-- Owner User ID -->
                    <div class="form-group">
                        <label for="formOwnerUserId" class="required">
                            <i class="fas fa-user-shield"></i>
                            Owner User ID
                        </label>
                        <input type="number" 
                               id="formOwnerUserId" 
                               name="owner_user_id" 
                               class="form-control" 
                               placeholder="Enter owner user ID..."
                               required
                               min="1">
                        <small class="form-text">The user who owns this tenant</small>
                        <div class="invalid-feedback">Please enter a valid user ID</div>
                    </div>

                    <!-- Status -->
                    <div class="form-group">
                        <label for="formStatus" class="required">
                            <i class="fas fa-toggle-on"></i>
                            Status
                        </label>
                        <select id="formStatus" name="status" class="form-control" required>
                            <option value="active" selected>Active</option>
                            <option value="suspended">Suspended</option>
                        </select>
                        <div class="invalid-feedback">Please select a status</div>
                    </div>

                </div>

                <!-- Form Actions -->
                <div class="form-actions">
                    <button type="submit" id="btnSubmitForm" class="btn btn-primary">
                        <i class="fas fa-save"></i>
                        Save Tenant
                    </button>
                    <button type="button" id="btnCancelForm" class="btn btn-outline">
                        <i class="fas fa-times"></i>
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>

<!-- Page Permissions Data -->
<script type="application/json" id="pagePermissions">
<?= json_encode([
    'canView' => $canView,
    'canCreate' => $canCreate,
    'canEdit' => $canEdit,
    'canDelete' => $canDelete,
    'isSuperAdmin' => $isSuperAdmin
], JSON_PRETTY_PRINT) ?>
</script>

<!-- Load JS -->
<link rel="stylesheet" href="/admin/assets/css/tenant.css?v=<?= time() ?>">
<script src="/admin/assets/js/pages/tenant.js?v=<?= time() ?>"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    if (typeof Tenants !== 'undefined') {
        Tenants.init();
    }
});
</script>