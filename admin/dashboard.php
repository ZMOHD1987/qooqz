<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/header.php';

// ════════════════════════════════════════════════════════════
// EXTRACT DATA
// ════════════════════════════════════════════════════════════
$payload = $GLOBALS['ADMIN_UI'] ?? [];
$user = $payload['user'] ?? [];
$lang = $payload['lang'] ?? 'en';

// Check permissions
$canViewDrivers = in_array('view_drivers', $user['permissions'] ?? [], true) 
               || in_array('super_admin', $user['roles'] ?? [], true);
$canViewSettings = in_array('view_settings', $user['permissions'] ?? [], true)
                || in_array('super_admin', $user['roles'] ?? [], true)
                || in_array('admin', $user['roles'] ?? [], true);
$canViewUsers = in_array('view_users', $user['permissions'] ?? [], true)
             || in_array('super_admin', $user['roles'] ?? [], true);
?>

<!-- Page meta for i18n -->
<meta data-page="dashboard" 
      data-i18n-files="/languages/admin/<?= rawurlencode($lang) ?>.json">

<style>
/* ================= Dashboard Styles ================= */
.dashboard-wrapper {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0;
}
.dash-card {
    background: var(--background-secondary, var(--background_secondary, #1e293b));
    border: 1px solid var(--border-color, var(--border_color, #334155));
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
}
.dash-title { font-size: 1.75rem; font-weight: 700; color: var(--text-primary, var(--text_primary, #f1f5f9)); margin-bottom:0.25rem; }
.dash-subtitle { color: var(--text-secondary, var(--text_secondary, #94a3b8)); font-size:0.9375rem; margin-bottom:0; }
.welcome-section { display:flex; align-items:center; gap:1rem; }
.welcome-icon { font-size:1.75rem; color: var(--primary-color, var(--primary_color, #3b82f6)); flex-shrink:0; }
.welcome-content h3 { margin:0 0 0.25rem 0; font-size:1.125rem; }
.welcome-content p { margin:0; color:var(--text-secondary, var(--text_secondary, #94a3b8)); }

.quick-actions-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 1rem;
    margin-top: 1rem;
}
.action-card {
    background: var(--background-secondary, var(--background_secondary, #1e293b));
    border: 1px solid var(--border-color, var(--border_color, #334155));
    border-radius: 10px;
    padding: 1.125rem 1.25rem;
    text-decoration: none;
    color: var(--text-primary, var(--text_primary, #f1f5f9));
    transition: background 0.2s, border-color 0.2s, transform 0.15s;
    display: flex;
    align-items: center;
    gap: 0.875rem;
    -webkit-tap-highlight-color: transparent;
    touch-action: manipulation;
}
.action-card:hover, .action-card:focus {
    background: var(--primary-color, var(--primary_color, #3b82f6));
    border-color: var(--primary-color, var(--primary_color, #3b82f6));
    color: #fff;
    transform: translateY(-2px);
    outline: none;
}
.action-icon {
    width: 44px;
    height: 44px;
    border-radius: 10px;
    background: rgba(59, 130, 246, 0.15);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    font-size: 1.25rem;
    color: var(--primary-color, var(--primary_color, #3b82f6));
    transition: background 0.2s, color 0.2s;
}
.action-card:hover .action-icon,
.action-card:focus .action-icon {
    background: rgba(255,255,255,0.2);
    color: #fff;
}
.action-content h3 { margin:0 0 0.2rem 0; font-size:1rem; font-weight:600; }
.action-content p { margin:0; font-size:0.8125rem; opacity:0.8; }

/* RTL: icon appears on the right side of the card */
[dir="rtl"] .action-card { flex-direction: row-reverse; }

@media (max-width:768px){
    .dash-card { padding:1.125rem; }
    .quick-actions-grid { grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap:0.75rem; }
    .action-card { padding:1rem; gap:0.75rem; }
    .action-icon { width:38px; height:38px; font-size:1.1rem; }
}
@media (max-width:480px){
    .dash-card { padding:0.875rem; border-radius:8px; }
    .quick-actions-grid { grid-template-columns: 1fr 1fr; gap:0.625rem; }
    .dash-title { font-size:1.375rem; }
    .action-content p { display:none; }
}
</style>

<div class="dashboard-wrapper">

    <!-- Welcome Banner -->
    <div class="dash-card">
        <div class="welcome-section">
            <div class="welcome-icon"><i class="fas fa-tachometer-alt"></i></div>
            <div class="welcome-content">
                <h1 class="dash-title" data-i18n="dashboard_title">Dashboard</h1>
                <p class="dash-subtitle"><?= htmlspecialchars($user['username'] ?? 'Admin') ?> &mdash; <span data-i18n="welcome_message">Manage your platform from here</span></p>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="dash-card">
        <h2 style="font-size:1.125rem; font-weight:600; margin-bottom:0.25rem; color:var(--text-primary);" data-i18n="quick_actions_title">Quick Actions</h2>
        <p class="dash-subtitle" style="margin-bottom:0;" data-i18n="quick_actions_subtitle">Jump to any section</p>
        <div class="quick-actions-grid">
            <a href="/admin/fragments/products.php" class="action-card">
                <div class="action-icon"><i class="fas fa-box"></i></div>
                <div class="action-content"><h3 data-i18n="nav.products">Products</h3><p data-i18n="manage_products">Manage products &amp; inventory</p></div>
            </a>
            <a href="/admin/fragments/categories.php" class="action-card">
                <div class="action-icon"><i class="fas fa-tags"></i></div>
                <div class="action-content"><h3 data-i18n="nav.categories">Categories</h3><p data-i18n="manage_categories">Organize product categories</p></div>
            </a>
            <?php if ($canViewUsers): ?>
            <a href="/admin/fragments/users.php" class="action-card">
                <div class="action-icon"><i class="fas fa-users"></i></div>
                <div class="action-content"><h3 data-i18n="nav.users">Users</h3><p data-i18n="manage_users">Manage user accounts</p></div>
            </a>
            <?php endif; ?>
            <a href="/admin/fragments/tenant_users.php" class="action-card">
                <div class="action-icon"><i class="fas fa-user-shield"></i></div>
                <div class="action-content"><h3 data-i18n="nav.tenant_users">Staff</h3><p data-i18n="manage_tenant_users">Manage staff accounts</p></div>
            </a>
            <a href="/admin/fragments/vendors.php" class="action-card">
                <div class="action-icon"><i class="fas fa-store-alt"></i></div>
                <div class="action-content"><h3 data-i18n="menu.vendors">Vendors</h3><p data-i18n="manage_vendors">Manage vendor accounts</p></div>
            </a>
            <?php if ($canViewDrivers): ?>
            <a href="/admin/fragments/IndependentDriver.php" class="action-card">
                <div class="action-icon"><i class="fas fa-truck"></i></div>
                <div class="action-content"><h3 data-i18n="nav.drivers">Drivers</h3><p data-i18n="manage_drivers">Manage delivery drivers</p></div>
            </a>
            <?php endif; ?>
            <a href="/admin/fragments/entities.php" class="action-card">
                <div class="action-icon"><i class="fas fa-store"></i></div>
                <div class="action-content"><h3 data-i18n="menu.entities">Branches</h3><p data-i18n="manage_entities">Manage branches &amp; outlets</p></div>
            </a>
            <a href="/admin/fragments/pos.php" class="action-card">
                <div class="action-icon"><i class="fas fa-cash-register"></i></div>
                <div class="action-content"><h3 data-i18n="nav.pos">POS</h3><p data-i18n="manage_pos">Point of sale system</p></div>
            </a>
            <a href="/admin/fragments/discounts.php" class="action-card">
                <div class="action-icon"><i class="fas fa-percent"></i></div>
                <div class="action-content"><h3 data-i18n="discounts">Discounts</h3><p data-i18n="manage_discounts">Manage offers &amp; discounts</p></div>
            </a>
            <a href="/admin/fragments/flash_sales.php" class="action-card">
                <div class="action-icon"><i class="fas fa-bolt"></i></div>
                <div class="action-content"><h3 data-i18n="menu.flash_sales">Flash Sales</h3><p data-i18n="manage_flash_sales">Manage limited time deals</p></div>
            </a>
            <a href="/admin/fragments/banners.php" class="action-card">
                <div class="action-icon"><i class="fas fa-images"></i></div>
                <div class="action-content"><h3 data-i18n="menu.banners">Banners</h3><p data-i18n="manage_banners">Manage promotional banners</p></div>
            </a>
            <?php if ($canViewSettings): ?>
            <a href="/admin/fragments/themes.php" class="action-card">
                <div class="action-icon"><i class="fas fa-paint-brush"></i></div>
                <div class="action-content"><h3 data-i18n="nav.settings">Settings &amp; Theme</h3><p data-i18n="manage_settings">Configure system &amp; appearance</p></div>
            </a>
            <?php endif; ?>
        </div>
    </div>

</div>

<script>
(function(){
'use strict';

// Apply translations
function applyTranslations(){
    try{
        if(window._admin && typeof window._admin.applyTranslations==='function'){
            window._admin.applyTranslations(document.body);
        }
    }catch(e){ console.warn(e); }
}
if(document.readyState==='loading'){
    document.addEventListener('DOMContentLoaded', applyTranslations);
}else{ setTimeout(applyTranslations,100); }

})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
