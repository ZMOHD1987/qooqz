<?php
declare(strict_types=1);

/**
 * htdocs/admin/fragments/users.php
 * واجهة إدارة المستخدمين الشاملة - تدعم كافة الحقول والفلاتر
 */

// 1. تضمين ملفات السياق والنظام
require_once __DIR__ . '/../../api/bootstrap_admin_context.php';

$isInDashboard = false;
$standaloneMode = true;

// التحقق من وجود Payload الواجهة الإدارية
if (defined('ADMIN_HEADER_INCLUDED') || isset($ADMIN_UI_PAYLOAD)) {
    $isInDashboard = true;
    $standaloneMode = false;
    if (isset($ADMIN_UI_PAYLOAD)) {
        $userLang = $ADMIN_UI_PAYLOAD['lang'] ?? 'en';
        $direction = $ADMIN_UI_PAYLOAD['direction'] ?? 'ltr';
        $csrfToken = $ADMIN_UI_PAYLOAD['csrf_token'] ?? '';
        $apiUrl = $ADMIN_UI_PAYLOAD['apiUrls']['users'] ?? '/api/routes/users.php';
    }
}

if ($standaloneMode) {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $userLang = $_SESSION['user_lang'] ?? 'ar';
    $direction = ($userLang === 'ar') ? 'rtl' : 'ltr';
    $csrfToken = $_SESSION['csrf_token'] ?? '';
    $apiUrl = '/api/routes/users.php';
}

if ($standaloneMode): ?>
<!doctype html>
<html lang="<?= $userLang ?>" dir="<?= $direction ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>User Management System</title>
    <link rel="stylesheet" href="/admin/assets/css/admin-theme.css">
    <style>
        :root { --primary: #3b82f6; --success: #10b981; --danger: #ef4444; --bg: #0b1120; --card: #1e293b; --text: #f1f5f9; --border: #334155; }
        body.vusers-scope { background: var(--bg); color: var(--text); font-family: 'Inter', sans-serif; margin: 0; padding: 20px; }
<?php endif; ?>

        /* التنسيقات المتقدمة */
        .vusers-card { background: var(--card); border: 1px solid var(--border); border-radius: 12px; padding: 24px; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); }
        .vusers-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; border-bottom: 1px solid var(--border); padding-bottom: 15px; }
        .vusers-title { font-size: 1.5rem; margin: 0; color: var(--primary); display: flex; align-items: center; gap: 10px; }
        
        /* الفلاتر المارنة */
        .vusers-filters { display: flex; flex-wrap: wrap; gap: 12px; margin-bottom: 20px; background: rgba(15, 23, 42, 0.5); padding: 15px; border-radius: 10px; }
        .vusers-filter-group { flex: 1; min-width: 180px; }
        .vusers-input { width: 100%; padding: 10px; background: #0f172a; border: 1px solid var(--border); color: white; border-radius: 8px; font-size: 0.85rem; transition: 0.3s; }
        .vusers-input:focus { border-color: var(--primary); outline: none; box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.2); }

        /* الجدول المطور */
        .vusers-table-wrap { overflow-x: auto; background: #0f172a; border-radius: 8px; border: 1px solid var(--border); }
        .vusers-table { width: 100%; border-collapse: collapse; min-width: 900px; }
        .vusers-table th { background: #1e293b; padding: 14px; text-align: left; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: #94a3b8; border-bottom: 2px solid var(--border); }
        [dir="rtl"] .vusers-table th { text-align: right; }
        .vusers-table td { padding: 14px; border-bottom: 1px solid var(--border); font-size: 0.9rem; vertical-align: middle; }
        .vusers-table tr:hover { background: rgba(51, 65, 85, 0.3); }
        
        .user-info { display: flex; flex-direction: column; }
        .user-info .main { font-weight: 600; color: #fff; }
        .user-info .sub { font-size: 0.75rem; color: #64748b; }

        .badge { padding: 4px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: 600; }
        .badge-active { background: rgba(16, 185, 129, 0.2); color: #10b981; }
        .badge-inactive { background: rgba(239, 68, 68, 0.2); color: #ef4444; }

        /* Modal & Form Grid */
        #vusersFormWrap { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.8); z-index: 9999; justify-content: center; align-items: center; backdrop-filter: blur(8px); padding: 20px; }
        .vusers-modal { background: var(--card); width: 100%; max-width: 800px; max-height: 90vh; overflow-y: auto; padding: 30px; border-radius: 16px; border: 1px solid var(--border); position: relative; }
        .form-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; margin-bottom: 20px; }
        @media (max-width: 600px) { .form-grid { grid-template-columns: 1fr; } }
        
        .vusers-label { display: block; font-size: 0.8rem; color: #94a3b8; margin-bottom: 6px; font-weight: 500; }

<?php if ($standaloneMode): ?>
    </style>
</head>
<body class="vusers-scope">
<?php endif; ?>

<div class="vusers-card">
    <div class="vusers-header">
        <h2 class="vusers-title">
            <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 00-3-3.87m-4-12a4 4 0 010 7.75"></path></svg>
            User Directory
        </h2>
        <button id="vusersNew" class="admin-btn-primary" style="padding: 10px 20px; border-radius: 8px; cursor: pointer;">+ Add New User</button>
    </div>

    <div class="vusers-filters">
        <div class="vusers-filter-group" style="flex: 2;">
            <input type="text" id="vusersSearch" class="vusers-input" placeholder="Search by name, email or phone...">
        </div>
        <div class="vusers-filter-group">
            <select id="vusersRoleFilter" class="vusers-input">
                <option value="">All Roles</option>
            </select>
        </div>
        <div class="vusers-filter-group">
            <select id="vusersCountryFilter" class="vusers-input">
                <option value="">All Countries</option>
            </select>
        </div>
        <div class="vusers-filter-group">
            <select id="vusersLangFilter" class="vusers-input">
                <option value="">All Languages</option>
            </select>
        </div>
        <div class="vusers-filter-group">
            <select id="vusersStatusFilter" class="vusers-input">
                <option value="">All Status</option>
                <option value="1">Active</option>
                <option value="0">Inactive</option>
            </select>
        </div>
        <button id="vusersRefresh" class="vusers-input" style="width: auto; cursor: pointer; background: var(--border);">Reset</button>
    </div>

    <div class="vusers-table-wrap">
        <table class="vusers-table">
            <thead>
                <tr>
                    <th>User Detail</th>
                    <th>Role & Contact</th>
                    <th>Location & Lang</th>
                    <th>Status</th>
                    <th style="text-align:center;">Actions</th>
                </tr>
            </thead>
            <tbody id="vusersTbody">
                <tr><td colspan="5" style="text-align:center; padding:60px;">
                    <div class="spinner"></div> 
                    <p style="color:#64748b; margin-top:10px;">Loading users data...</p>
                </td></tr>
            </tbody>
        </table>
    </div>
</div>

<div id="vusersFormWrap">
    <div class="vusers-modal">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:25px;">
            <h3 id="vusersFormTitle" style="margin:0; color:var(--primary);">User Profile</h3>
            <button type="button" id="vusersCloseX" style="background:none; border:none; color:#94a3b8; cursor:pointer; font-size:1.5rem;">&times;</button>
        </div>

        <form id="vusersForm">
            <input type="hidden" name="id" id="vusersId">
            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">

            <div class="form-grid">
                <div>
                    <label class="vusers-label">Username</label>
                    <input type="text" name="username" id="vusersUsername" class="vusers-input" placeholder="e.g. jsmith" required>
                </div>
                <div>
                    <label class="vusers-label">Full Name / Display Name</label>
                    <input type="text" name="display_name" id="vusersDisplayName" class="vusers-input">
                </div>
            </div>

            <div class="form-grid">
                <div>
                    <label class="vusers-label">Email Address</label>
                    <input type="email" name="email" id="vusersEmail" class="vusers-input" placeholder="name@domain.com" required>
                </div>
                <div>
                    <label class="vusers-label">Phone Number</label>
                    <input type="text" name="phone" id="vusersPhone" class="vusers-input" placeholder="+1234567890">
                </div>
            </div>

            <div class="form-grid">
                <div>
                    <label class="vusers-label">Password</label>
                    <input type="password" name="password" id="vusersPassword" class="vusers-input" placeholder="Min 8 characters">
                    <small style="color:#64748b; font-size:0.7rem;">Leave blank if you don't want to change password</small>
                </div>
                <div>
                    <label class="vusers-label">System Role</label>
                    <select name="role_id" id="vusersRole" class="vusers-input" required></select>
                </div>
            </div>

            <div class="form-grid">
                <div>
                    <label class="vusers-label">Country</label>
                    <select name="country_id" id="vusersCountry" class="vusers-input"></select>
                </div>
                <div>
                    <label class="vusers-label">City</label>
                    <select name="city_id" id="vusersCity" class="vusers-input">
                        <option value="">Select Country First</option>
                    </select>
                </div>
            </div>

            <div class="form-grid">
                <div>
                    <label class="vusers-label">Preferred Language</label>
                    <select name="preferred_language" id="vusersLang" class="vusers-input"></select>
                </div>
                <div>
                    <label class="vusers-label">Timezone</label>
                    <select name="timezone" id="vusersTimezone" class="vusers-input"></select>
                </div>
            </div>

            <div style="margin-bottom:25px; padding:15px; background:rgba(15,23,42,0.5); border-radius:10px;">
                <label class="vusers-label">Account Status</label>
                <div style="display:flex; align-items:center; gap:20px;">
                    <label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
                        <input type="radio" name="is_active" value="1" checked> Active
                    </label>
                    <label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
                        <input type="radio" name="is_active" value="0"> Inactive / Suspended
                    </label>
                </div>
            </div>

            <div style="display:flex; justify-content:flex-end; gap:12px;">
                <button type="button" id="vusersCancel" class="vusers-input" style="width:auto; padding:10px 25px; cursor:pointer; background:transparent;">Cancel</button>
                <button type="submit" class="admin-btn-primary" style="padding:10px 30px; border-radius:8px; cursor:pointer; font-weight:600;">Save User Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
    /**
     * إعدادات النظام الممررة من PHP للـ JS
     */
    window.USERS_CONFIG = {
        apiUrl: "<?= $apiUrl ?>",
        csrfToken: "<?= $csrfToken ?>",
        lang: "<?= $userLang ?>",
        direction: "<?= $direction ?>",
        assets: {
            placeholder: "/admin/assets/img/user-placeholder.png"
        }
    };
</script>

<?php if ($standaloneMode): ?>
<script src="/admin/assets/js/admin_core.js"></script>
<script src="/admin/assets/js/pages/users.js"></script>
</body>
</html>
<?php endif; ?>
