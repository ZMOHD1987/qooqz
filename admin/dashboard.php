<?php
declare(strict_types=1);

$validPages = [
    'dashboard' => 'fragments/dashboard.php',
    'users' => 'fragments/users.php',
    'tenant' => 'fragments/tenant.php',
    'permissions' => 'fragments/permissions.php',
    // ... other pages
];

require_once __DIR__ . '/includes/header.php';

// ════════════════════════════════════════════════════════════
// EXTRACT DATA
// ════════════════════════════════════════════════════════════
$payload = $GLOBALS['ADMIN_UI'] ?? [];
$theme = $payload['theme'] ?? [];
$user = $payload['user'] ?? [];
$lang = $payload['lang'] ?? 'en';

// Extract colors from DB/theme
$colors = [];
foreach ($theme['color_settings'] ?? [] as $c) {
    if (!empty($c['color_value'])) {
        $colors[] = [
            'setting_name' => $c['setting_name'] ?? $c['setting_key'] ?? 'Color',
            'color_value' => $c['color_value']
        ];
    }
}

// Check permissions
$canViewDrivers = in_array('view_drivers', $user['permissions'] ?? [], true) 
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
    background: var(--background-secondary, #1e293b);
    border: 1px solid var(--border-color, #334155);
    border-radius: 12px;
    padding: 2rem;
    margin-bottom: 2rem;
}
.dash-title { font-size: 2rem; font-weight: 700; color: var(--text-primary,#fff); margin-bottom:0.5rem; }
.dash-subtitle { color: var(--text-secondary,#94a3b8); font-size:0.9375rem; margin-bottom:0; }
.welcome-section { display:flex; align-items:center; gap:1rem; }
.welcome-icon { font-size:2.5rem; }
.welcome-content h3 { margin:0 0 0.25rem 0; font-size:1.25rem; }
.welcome-content p { margin:0; color:var(--text-secondary,#94a3b8); }
.quick-actions-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(240px,1fr)); gap:1.25rem; margin-top:1.5rem; }
.action-card {
    background: var(--background-secondary,#1e293b);
    border:1px solid var(--border-color,#334155);
    border-radius:10px;
    padding:1.25rem;
    text-decoration:none;
    color:inherit;
    transition:all 0.2s ease;
    display:flex;
    align-items:flex-start;
    gap:1rem;
}
.action-card:hover {
    background: var(--primary-color,#3B82F6);
    color:#fff;
    transform:translateY(-2px);
    box-shadow:0 8px 16px rgba(0,0,0,0.2);
}
.action-icon { font-size:2rem; flex-shrink:0; }
.action-content h3 { margin:0 0 0.25rem 0; font-size:1.125rem; font-weight:600; }
.action-content p { margin:0; font-size:0.875rem; opacity:0.85; }

.colors-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(180px,1fr)); gap:1.5rem; margin-top:1.5rem; }
.color-card {
    background: var(--background-secondary,#1e293b);
    border:2px solid var(--border-color,#334155);
    border-radius:10px;
    padding:1rem;
    cursor:pointer;
    transition:all 0.2s ease;
}
.color-card:hover { border-color: var(--primary-color,#6366f1); transform:translateY(-4px); box-shadow:0 8px 20px rgba(0,0,0,0.3); }
.color-card.copied { border-color:#10b981; animation: copyPulse 0.5s ease; }
@keyframes copyPulse { 50% { transform: scale(1.05); } }

.color-preview { width:100%; height:80px; border-radius:8px; margin-bottom:0.75rem; border:1px solid rgba(0,0,0,0.2); }
.color-name { font-weight:600; color:var(--text-primary,#fff); margin-bottom:0.5rem; font-size:0.95rem; }
.color-code { font-family:'Courier New', monospace; font-size:0.85rem; color:var(--text-secondary,#94a3b8); background:rgba(0,0,0,0.2); padding:0.25rem 0.5rem; border-radius:4px; }

.copy-feedback {
    position:fixed;
    bottom:2rem; right:2rem;
    background:linear-gradient(135deg,#10b981 0%,#059669 100%);
    color:white; padding:1rem 1.5rem; border-radius:8px;
    box-shadow:0 4px 20px rgba(16,185,129,0.4);
    opacity:0; transform:translateY(20px); transition:all 0.3s ease; pointer-events:none; z-index:10000; font-weight:500;
}
.copy-feedback.show { opacity:1; transform:translateY(0); }

.empty-state { text-align:center; padding:3rem; color:var(--text-secondary,#94a3b8); }
.empty-state p { margin:0; }

@media (max-width:768px){
    .dash-card { padding:1.5rem; }
    .colors-grid { grid-template-columns:repeat(auto-fill,minmax(140px,1fr)); gap:1rem; }
    .quick-actions-grid { grid-template-columns:1fr; }
    .copy-feedback { bottom:1rem; right:1rem; left:1rem; }
}
</style>

<div class="dashboard-wrapper">

    <!-- Welcome Card -->
    <div class="dash-card">
        <h1 class="dash-title" data-i18n="dashboard_title">Dashboard</h1>
        <p class="dash-subtitle" data-i18n="dashboard_subtitle">Welcome back, <?= htmlspecialchars($user['username'] ?? 'Guest') ?></p>
    </div>

    <!-- Welcome Section -->
    <div class="dash-card">
        <div class="welcome-section">
            <div class="welcome-icon">👋</div>
            <div class="welcome-content">
                <h3 data-i18n="welcome_title">Welcome Back</h3>
                <p data-i18n="welcome_message">Manage your platform from here</p>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="dash-card">
        <h2 style="font-size:1.5rem; margin-bottom:0.5rem; color:var(--text-primary);" data-i18n="quick_actions_title">Quick Actions</h2>
        <div class="quick-actions-grid">
            <a href="/admin/products.php" class="action-card"><div class="action-icon">📦</div><div class="action-content"><h3 data-i18n="nav.products">Products</h3><p data-i18n="manage_products">Manage products & inventory</p></div></a>
            <a href="/admin/categories.php" class="action-card"><div class="action-icon">🏷️</div><div class="action-content"><h3 data-i18n="nav.categories">Categories</h3><p data-i18n="manage_categories">Organize product categories</p></div></a>
            <a href="/admin/users.php" class="action-card"><div class="action-icon">👥</div><div class="action-content"><h3 data-i18n="nav.users">Users</h3><p data-i18n="manage_users">Manage user accounts</p></div></a>
            <?php if ($canViewDrivers): ?>
            <a href="/admin/drivers.php" class="action-card"><div class="action-icon">🚚</div><div class="action-content"><h3 data-i18n="nav.drivers">Drivers</h3><p data-i18n="manage_drivers">Manage delivery drivers</p></div></a>
            <?php endif; ?>
            <a href="/admin/orders.php" class="action-card"><div class="action-icon">🛒</div><div class="action-content"><h3 data-i18n="nav.orders">Orders</h3><p data-i18n="manage_orders">Track and process orders</p></div></a>
            <a href="/admin/settings.php" class="action-card"><div class="action-icon">⚙️</div><div class="action-content"><h3 data-i18n="nav.settings">Settings</h3><p>Configure system settings</p></div></a>
        </div>
    </div>

    <!-- Theme Colors -->
    <div class="dash-card">
        <h2 style="font-size:1.5rem; margin-bottom:0.5rem; color:var(--text-primary);" data-i18n="theme_preview_title">Active Theme Colors</h2>
        <p class="dash-subtitle" data-i18n="theme_preview_subtitle">Click any color to copy to clipboard</p>

        <?php if (empty($colors)): ?>
            <div class="empty-state"><p>No colors available</p><small>Configure theme colors in Settings</small></div>
        <?php else: ?>
            <div class="colors-grid">
                <?php foreach ($colors as $color): ?>
                    <div class="color-card" onclick="copyColor('<?= htmlspecialchars($color['color_value'], ENT_QUOTES) ?>', this)">
                        <div class="color-preview" style="background: <?= htmlspecialchars($color['color_value']) ?>;"></div>
                        <div class="color-name"><?= htmlspecialchars($color['setting_name']) ?></div>
                        <div class="color-code"><?= htmlspecialchars($color['color_value']) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

</div>

<div class="copy-feedback" id="copyFeedback">Copied!</div>

<script>
(function(){
'use strict';

console.log('Dashboard Loaded');
console.log('Language: <?= $lang ?>');
console.log('User: <?= $user['username'] ?? 'Guest' ?>');

// 🔹 مزامنة ألوان PHP إلى JS
window.ADMIN_UI = window.ADMIN_UI || {};
window.ADMIN_UI.theme = window.ADMIN_UI.theme || {};
window.ADMIN_UI.theme.colors = <?= json_encode($colors, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

// Copy color function
window.copyColor = async function(value, el){
    try {
        await navigator.clipboard.writeText(value);
        el.classList.add('copied');
        setTimeout(()=>el.classList.remove('copied'),1000);

        var fb = document.getElementById('copyFeedback');
        fb.textContent = 'Copied: ' + value;
        fb.classList.add('show');
        setTimeout(()=>fb.classList.remove('show'),2500);

        // تحديث اللون مباشرة على Dashboard
        const card = el.closest('.color-card');
        if(card){
            card.querySelector('.color-preview').style.backgroundColor = value;
            card.querySelector('.color-code').textContent = value;
        }

        // تحديث ADMIN_UI.theme.colors
        const colorName = el.querySelector('.color-name')?.textContent;
        if(colorName && window.ADMIN_UI?.theme?.colors){
            const themeColor = window.ADMIN_UI.theme.colors.find(c=>c.setting_name===colorName);
            if(themeColor) themeColor.color_value = value;
        }

    }catch(e){
        console.error(e);
        var textarea=document.createElement('textarea');
        textarea.value=value; textarea.style.position='fixed'; textarea.style.opacity='0';
        document.body.appendChild(textarea); textarea.select();
        try{ document.execCommand('copy'); alert('Copied: '+value);}catch(err){console.error(err);}
        document.body.removeChild(textarea);
    }
};

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

// مزامنة الألوان عند تحميل الصفحة
document.addEventListener('DOMContentLoaded', function(){
    if(window.ADMIN_UI?.theme?.colors){
        document.querySelectorAll('.color-card').forEach(card=>{
            const name = card.querySelector('.color-name')?.textContent;
            const color = window.ADMIN_UI.theme.colors.find(c=>c.setting_name===name);
            if(color){
                const val = color.color_value;
                card.querySelector('.color-preview').style.backgroundColor = val;
                card.querySelector('.color-code').textContent = val;
            }
        });
    }
});

console.log('Dashboard initialized');
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
