<?php
require_once __DIR__ . '/../includes/header.php';

$payload = $GLOBALS['ADMIN_UI'] ?? [];
$user = $payload['user'] ?? [];
$canCreate = in_array('manage_tenant_users', $_SESSION['permissions'] ?? [], true) || ($user['roles'][0] ?? '') === 'super_admin';
$canEdit = $canCreate;
$canDelete = $canCreate;
?>
<meta data-page="tenant_users"
      data-i18n-files="/languages/admin/<?= $payload['lang'] ?? 'en' ?>.json"
      data-assets-css="/admin/assets/css/pages/tenant_users.css"
      data-assets-js="/admin/assets/js/pages/tenant_users.js">

<div class="page-header">
    <h1 data-i18n="tenant_users.title">Tenant Users</h1>
    <?php if ($canCreate): ?>
    <button id="btnAddTenantUser" class="btn btn-primary" data-i18n="add">Add</button>
    <?php endif; ?>
</div>

<div class="card filter-card">
    <input type="text" id="searchInput" placeholder="Search users..." data-i18n-placeholder="search">
    <select id="roleFilter">
        <option value="">All Roles</option>
        <!-- Load roles via JS -->
    </select>
    <button id="btnApplyFilters" data-i18n="apply_filters">Apply Filters</button>
</div>

<div class="card table-card">
    <table id="tenantUsersTable">
        <thead>
            <tr>
                <th data-i18n="id">ID</th>
                <th data-i18n="username">Username</th>
                <th data-i18n="email">Email</th>
                <th data-i18n="role">Role</th>
                <th data-i18n="joined_at">Joined At</th>
                <th data-i18n="status">Status</th>
                <th data-i18n="actions">Actions</th>
            </tr>
        </thead>
        <tbody id="tableBody">
            <!-- Loading -->
            <tr><td colspan="7" data-i18n="loading">Loading...</td></tr>
        </tbody>
    </table>
    <div id="pagination"></div>
</div>

<script id="pagePermissions" type="application/json">
<?= json_encode(['canCreate' => $canCreate, 'canEdit' => $canEdit, 'canDelete' => $canDelete]) ?>
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>