<?php
declare(strict_types=1);

/**
 * admin/fragments/users.php
 * User Management Interface - Respects translations and theme from bootstrap_admin_ui.php
 */

// Load admin context and UI bootstrap
require_once __DIR__ . '/../../api/bootstrap_admin_context.php';
require_once __DIR__ . '/../../api/bootstrap_admin_ui.php';

$isInDashboard = false;
$standaloneMode = true;

// Check if running inside dashboard with ADMIN_UI_PAYLOAD
if (defined('ADMIN_HEADER_INCLUDED') || isset($ADMIN_UI_PAYLOAD)) {
    $isInDashboard = true;
    $standaloneMode = false;
}

// Initialize from ADMIN_UI_PAYLOAD or fallback to session
if (isset($ADMIN_UI_PAYLOAD)) {
    $userLang = $ADMIN_UI_PAYLOAD['lang'] ?? 'en';
    $direction = $ADMIN_UI_PAYLOAD['direction'] ?? 'ltr';
    $csrfToken = $_SESSION['csrf_token'] ?? '';
    $apiUrl = '/api/routes/users.php';
    $translations = $ADMIN_UI_PAYLOAD['strings'] ?? [];
    $theme = $ADMIN_UI_PAYLOAD['theme'] ?? [];
} else {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $userLang = $_SESSION['lang'] ?? $_SESSION['preferred_language'] ?? 'en';
    $direction = in_array(strtolower(substr($userLang, 0, 2)), ['ar', 'fa', 'he', 'ur']) ? 'rtl' : 'ltr';
    $csrfToken = $_SESSION['csrf_token'] ?? bin2hex(random_bytes(16));
    $apiUrl = '/api/routes/users.php';
    $translations = [];
    $theme = [];
}

// Helper function to get translation with fallback
function t($key, $default = '') {
    global $translations;
    if (isset($translations[$key])) {
        return $translations[$key];
    }
    // Try nested keys like 'table.username'
    $parts = explode('.', $key);
    $value = $translations;
    foreach ($parts as $part) {
        if (isset($value[$part])) {
            $value = $value[$part];
        } else {
            return $default ?: $key;
        }
    }
    return is_string($value) ? $value : $default ?: $key;
}

if ($standaloneMode): ?>
<!doctype html>
<html lang="<?= $userLang ?>" dir="<?= $direction ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= t('page_title', 'User Management') ?></title>
    <link rel="stylesheet" href="/admin/assets/css/admin-theme.css">
    <link rel="stylesheet" href="/admin/assets/css/pages/users.css">
</head>
<body>
<?php endif; ?>

<style>
    /* Component-specific styles using theme CSS variables */
    .vusers-card {
        background: var(--theme-background-secondary, #1e293b);
        border: 1px solid var(--theme-border, #334155);
        border-radius: var(--theme-card-radius, 12px);
        padding: 24px;
        box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1);
    }
    .vusers-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 25px;
        border-bottom: 1px solid var(--theme-border, #334155);
        padding-bottom: 15px;
    }
    .vusers-title {
        font-size: 1.5rem;
        margin: 0;
        color: var(--theme-primary, #3b82f6);
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .vusers-filters {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        margin-bottom: 20px;
        background: var(--theme-background, rgba(15, 23, 42, 0.5));
        padding: 15px;
        border-radius: 10px;
    }
    .vusers-filter-group { flex: 1; min-width: 180px; }
    .vusers-input {
        width: 100%;
        padding: 10px;
        background: var(--theme-background, #0f172a);
        border: 1px solid var(--theme-border, #334155);
        color: var(--theme-text-primary, white);
        border-radius: 8px;
        font-size: 0.85rem;
        transition: 0.3s;
    }
    .vusers-input:focus {
        border-color: var(--theme-primary, #3b82f6);
        outline: none;
        box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.2);
    }
    .vusers-table-wrap {
        overflow-x: auto;
        background: var(--theme-background, #0f172a);
        border-radius: 8px;
        border: 1px solid var(--theme-border, #334155);
    }
    .vusers-table { width: 100%; border-collapse: collapse; min-width: 900px; }
    .vusers-table th {
        background: var(--theme-background-secondary, #1e293b);
        padding: 14px;
        text-align: left;
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: var(--theme-text-secondary, #94a3b8);
        border-bottom: 2px solid var(--theme-border, #334155);
    }
    [dir="rtl"] .vusers-table th { text-align: right; }
    .vusers-table td {
        padding: 14px;
        border-bottom: 1px solid var(--theme-border, #334155);
        font-size: 0.9rem;
        vertical-align: middle;
    }
    .vusers-table tr:hover { background: rgba(51, 65, 85, 0.3); }
    .user-info { display: flex; flex-direction: column; }
    .user-info .main { font-weight: 600; color: var(--theme-text-primary, #fff); }
    .user-info .sub { font-size: 0.75rem; color: var(--theme-text-secondary, #64748b); }
    .badge { padding: 4px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: 600; }
    .badge-active { background: rgba(16, 185, 129, 0.2); color: var(--theme-success, #10b981); }
    .badge-inactive { background: rgba(239, 68, 68, 0.2); color: var(--theme-error, #ef4444); }
    #vusersFormWrap {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0,0,0,0.8);
        z-index: 9999;
        justify-content: center;
        align-items: center;
        backdrop-filter: blur(8px);
        padding: 20px;
    }
    .vusers-modal {
        background: var(--theme-background-secondary, #1e293b);
        width: 100%;
        max-width: 800px;
        max-height: 90vh;
        overflow-y: auto;
        padding: 30px;
        border-radius: 16px;
        border: 1px solid var(--theme-border, #334155);
        position: relative;
    }
    .form-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; margin-bottom: 20px; }
    @media (max-width: 600px) { .form-grid { grid-template-columns: 1fr; } }
    .vusers-label {
        display: block;
        font-size: 0.8rem;
        color: var(--theme-text-secondary, #94a3b8);
        margin-bottom: 6px;
        font-weight: 500;
    }
</style>
<?php if ($standaloneMode): ?>
<?php endif; ?>

<div class="vusers-card">
    <div class="vusers-header">
        <h2 class="vusers-title">
            <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 00-3-3.87m-4-12a4 4 0 010 7.75"></path></svg>
            <span data-i18n="page_title"><?= t('page_title', 'User Management') ?></span>
        </h2>
        <button id="vusersNew" class="admin-btn-primary" style="padding: 10px 20px; border-radius: 8px; cursor: pointer;">
            <span data-i18n="add_user"><?= t('add_user', 'Add New User') ?></span>
        </button>
    </div>

    <div class="vusers-filters">
        <div class="vusers-filter-group" style="flex: 2;">
            <input type="text" id="vusersSearch" class="vusers-input" placeholder="<?= t('search_placeholder', 'Search by name, email or phone...') ?>" data-i18n-placeholder="search_placeholder">
        </div>
        <div class="vusers-filter-group">
            <select id="vusersRoleFilter" class="vusers-input">
                <option value=""><?= t('filters.all_roles', 'All Roles') ?></option>
            </select>
        </div>
        <div class="vusers-filter-group">
            <select id="vusersCountryFilter" class="vusers-input">
                <option value=""><?= t('filters.all_countries', 'All Countries') ?></option>
            </select>
        </div>
        <div class="vusers-filter-group">
            <select id="vusersLangFilter" class="vusers-input">
                <option value=""><?= t('filters.all_languages', 'All Languages') ?></option>
            </select>
        </div>
        <div class="vusers-filter-group">
            <select id="vusersStatusFilter" class="vusers-input">
                <option value=""><?= t('filters.all_status', 'All Status') ?></option>
                <option value="1" data-i18n="status.active"><?= t('status.active', 'Active') ?></option>
                <option value="0" data-i18n="status.inactive"><?= t('status.inactive', 'Inactive') ?></option>
            </select>
        </div>
        <button id="vusersRefresh" class="vusers-input" style="width: auto; cursor: pointer; background: var(--theme-border);">
            <span data-i18n="refresh"><?= t('refresh', 'Refresh') ?></span>
        </button>
    </div>

    <div class="vusers-table-wrap">
        <table class="vusers-table">
            <thead>
                <tr>
                    <th data-i18n="table.username"><?= t('table.username', 'User Detail') ?></th>
                    <th data-i18n="table.role"><?= t('table.role', 'Role & Contact') ?></th>
                    <th data-i18n="table.location"><?= t('table.location', 'Location & Lang') ?></th>
                    <th data-i18n="table.status"><?= t('table.status', 'Status') ?></th>
                    <th style="text-align:center;" data-i18n="table.actions"><?= t('table.actions', 'Actions') ?></th>
                </tr>
            </thead>
            <tbody id="vusersTbody">
                <tr><td colspan="5" style="text-align:center; padding:60px;">
                    <div class="spinner"></div> 
                    <p style="color: var(--theme-text-secondary); margin-top:10px;" data-i18n="messages.loading"><?= t('messages.loading', 'Loading users data...') ?></p>
                </td></tr>
            </tbody>
        </table>
    </div>
</div>

<div id="vusersFormWrap">
    <div class="vusers-modal">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:25px;">
            <h3 id="vusersFormTitle" style="margin:0; color: var(--theme-primary);" data-i18n="form_title"></h3>
            <button type="button" id="vusersCloseX" style="background:none; border:none; color: var(--theme-text-secondary); cursor:pointer; font-size:1.5rem;">&times;</button>
        </div>

        <form id="vusersForm">
            <input type="hidden" name="id" id="vusersId">
            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">

            <div class="form-grid">
                <div>
                    <label class="vusers-label" data-i18n="form.username_label"><?= t('form.username_label', 'Username') ?></label>
                    <input type="text" name="username" id="vusersUsername" class="vusers-input" required>
                </div>
                <div>
                    <label class="vusers-label" data-i18n="form.display_name_label"><?= t('form.display_name_label', 'Display Name') ?></label>
                    <input type="text" name="display_name" id="vusersDisplayName" class="vusers-input">
                </div>
            </div>

            <div class="form-grid">
                <div>
                    <label class="vusers-label" data-i18n="form.email_label"><?= t('form.email_label', 'Email Address') ?></label>
                    <input type="email" name="email" id="vusersEmail" class="vusers-input" required>
                </div>
                <div>
                    <label class="vusers-label" data-i18n="form.phone_label"><?= t('form.phone_label', 'Phone Number') ?></label>
                    <input type="text" name="phone" id="vusersPhone" class="vusers-input">
                </div>
            </div>

            <div class="form-grid">
                <div>
                    <label class="vusers-label" data-i18n="form.password_label"><?= t('form.password_label', 'Password') ?></label>
                    <input type="password" name="password" id="vusersPassword" class="vusers-input">
                    <small style="color: var(--theme-text-secondary); font-size:0.7rem;" data-i18n="form.password_help"><?= t('form.password_help', 'Leave blank to keep current password') ?></small>
                </div>
                <div>
                    <label class="vusers-label" data-i18n="form.role_label"><?= t('form.role_label', 'System Role') ?></label>
                    <select name="role_id" id="vusersRole" class="vusers-input" required></select>
                </div>
            </div>

            <div class="form-grid">
                <div>
                    <label class="vusers-label" data-i18n="form.country_label"><?= t('form.country_label', 'Country') ?></label>
                    <select name="country_id" id="vusersCountry" class="vusers-input"></select>
                </div>
                <div>
                    <label class="vusers-label" data-i18n="form.city_label"><?= t('form.city_label', 'City') ?></label>
                    <select name="city_id" id="vusersCity" class="vusers-input">
                        <option value=""><?= t('form.select_country_first', 'Select Country First') ?></option>
                    </select>
                </div>
            </div>

            <div class="form-grid">
                <div>
                    <label class="vusers-label" data-i18n="form.language_label"><?= t('form.language_label', 'Preferred Language') ?></label>
                    <select name="preferred_language" id="vusersLang" class="vusers-input"></select>
                </div>
                <div>
                    <label class="vusers-label" data-i18n="form.timezone_label"><?= t('form.timezone_label', 'Timezone') ?></label>
                    <select name="timezone" id="vusersTimezone" class="vusers-input"></select>
                </div>
            </div>

            <div style="margin-bottom:25px; padding:15px; background:rgba(15,23,42,0.5); border-radius:10px;">
                <label class="vusers-label" data-i18n="form.status_label"><?= t('form.status_label', 'Account Status') ?></label>
                <div style="display:flex; align-items:center; gap:20px;">
                    <label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
                        <input type="radio" name="is_active" value="1" checked> 
                        <span data-i18n="status.active"><?= t('status.active', 'Active') ?></span>
                    </label>
                    <label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
                        <input type="radio" name="is_active" value="0"> 
                        <span data-i18n="status.inactive"><?= t('status.inactive', 'Inactive') ?></span>
                    </label>
                </div>
            </div>

            <div style="display:flex; justify-content:flex-end; gap:12px;">
                <button type="button" id="vusersCancel" class="vusers-input" style="width:auto; padding:10px 25px; cursor:pointer; background:transparent;" data-i18n="form.cancel_btn"><?= t('form.cancel_btn', 'Cancel') ?></button>
                <button type="submit" class="admin-btn-primary" style="padding:10px 30px; border-radius:8px; cursor:pointer; font-weight:600;" data-i18n="form.save_btn"><?= t('form.save_btn', 'Save Changes') ?></button>
            </div>
        </form>
    </div>
</div>

<script>
    /**
     * User management configuration
     * Uses translations from ADMIN_UI_PAYLOAD or fetches from language API
     */
    window.USERS_CONFIG = {
        apiUrl: "<?= $apiUrl ?>",
        csrfToken: "<?= $csrfToken ?>",
        lang: "<?= $userLang ?>",
        direction: "<?= $direction ?>",
        translations: <?= json_encode($translations, JSON_UNESCAPED_UNICODE) ?>,
        theme: <?= json_encode($theme, JSON_UNESCAPED_UNICODE) ?>,
        assets: {
            placeholder: "/admin/assets/img/user-placeholder.png"
        }
    };
</script>

<?php if ($standaloneMode): ?>
<script src="/admin/assets/js/admin_core.js"></script>
<?php endif; ?>
<script src="/admin/assets/js/pages/users.js"></script>

<?php if ($standaloneMode): ?>
</body>
</html>
<?php endif; ?>
