<?php
declare(strict_types=1);

/**
 * /admin/fragments/AdminUiThemeLoader.php
 * Production-Ready Theme Management System
 * Version: 2.0.0
 */

// ════════════════════════════════════════════════════════════
// SECURITY CONFIGURATION
// ════════════════════════════════════════════════════════════
error_reporting(0);
ini_set('display_errors', '0');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// ════════════════════════════════════════════════════════════
// REQUEST TYPE DETECTION
// ════════════════════════════════════════════════════════════
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
          strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
$isEmbedded = isset($_GET['embedded']) || isset($_POST['embedded']);
$isFragment = $isAjax || $isEmbedded;

// ════════════════════════════════════════════════════════════
// LOAD CONTEXT / HEADER
// ════════════════════════════════════════════════════════════
if ($isFragment) {
    require_once __DIR__ . '/../includes/admin_context.php';
} else {
    require_once __DIR__ . '/../includes/header.php';
}


// ════════════════════════════════════════════════════════════
// VERIFY USER IS LOGGED IN
// ════════════════════════════════════════════════════════════
if (!function_exists('is_admin_logged_in') || !is_admin_logged_in()) {
    if ($isFragment) {
        http_response_code(401);
        echo json_encode(['error' => 'Not authenticated']);
        exit;
    } else {
        header('Location: /admin/login.php');
        exit;
    }
}

// ════════════════════════════════════════════════════════════
// CONTEXT / PAYLOAD EXTRACTION
// ════════════════════════════════════════════════════════════
$payload = $GLOBALS['ADMIN_UI'] ?? [];
$user = function_exists('admin_user') ? admin_user() : [];
$permissions = $user['permissions'] ?? [];
$roles = $user['roles'] ?? [];
$lang = function_exists('admin_lang') ? admin_lang() : ($user['preferred_language'] ?? 'en');
$dir = function_exists('admin_dir') ? admin_dir() : ($lang === 'ar' ? 'rtl' : 'ltr');
$csrf = function_exists('admin_csrf') ? admin_csrf() : bin2hex(random_bytes(32));
$apiBase = $payload['api_base'] ?? '/api';
$tenantId = function_exists('admin_tenant_id') ? admin_tenant_id() : 1;
$userId = function_exists('admin_user_id') ? admin_user_id() : 0;

// ════════════════════════════════════════════════════════════
// PERMISSIONS CHECK
// ════════════════════════════════════════════════════════════
$isSuperAdmin = function_exists('is_super_admin') && is_super_admin();

$canCreate = $isSuperAdmin || in_array('manage_themes', $permissions, true);
$canEdit   = $isSuperAdmin || in_array('edit_themes', $permissions, true) || $canCreate;
$canDelete = $isSuperAdmin || in_array('delete_themes', $permissions, true);
$canExport = $isSuperAdmin || in_array('export_themes', $permissions, true);
$canActivate = $isSuperAdmin || in_array('activate_themes', $permissions, true) || $canEdit;

// ════════════════════════════════════════════════════════════
// TRANSLATION HELPER
// ════════════════════════════════════════════════════════════
function __t(string $key, string $fallback = ''): string {
    static $translations = [];
    
    if (empty($translations) && function_exists('i18n_get_all')) {
        $translations = i18n_get_all();
    }
    
    return $translations[$key] ?? $fallback ?: $key;
}

// ════════════════════════════════════════════════════════════
// HTML SANITIZATION HELPER
// ════════════════════════════════════════════════════════════
function e(string $text): string {
    return htmlspecialchars($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}
?>

<!DOCTYPE html>
<html lang="<?= e($lang) ?>" dir="<?= e($dir) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <meta name="description" content="<?= __t('theme_manager.meta_description', 'Manage admin interface themes') ?>">
    <title><?= __t('theme_manager.title', 'Theme Management') ?> | <?= e($payload['site_name'] ?? 'Admin') ?></title>
    
    <!-- Favicon -->
    <link rel="icon" href="/admin/assets/favicon.ico" type="image/x-icon">
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Color Picker -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-colorpicker/3.4.0/css/bootstrap-colorpicker.min.css">
    
    <!-- Toast Notifications -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
    
    <!-- Custom Styles -->
    <link rel="stylesheet" href="/admin/assets/css/pages/AdminUiThemeLoader.css?v=<?= filemtime(__DIR__ . '/../../assets/css/pages/AdminUiThemeLoader.css') ?>">
    
    <!-- Preload Critical Resources -->
    <link rel="preload" href="/admin/assets/js/pages/AdminUiThemeLoader.js" as="script">
    
    <!-- Inline Critical CSS -->
    <style>
        .theme-manager-loading {
            min-height: 400px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .color-preview-circle {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: inline-block;
            vertical-align: middle;
            border: 2px solid #fff;
            box-shadow: 0 0 0 1px #dee2e6;
        }
        
        [dir="rtl"] {
            text-align: right;
        }
        
        [dir="rtl"] .dropdown-menu {
            text-align: right;
        }
        
        .cursor-pointer {
            cursor: pointer;
        }
        
        .text-truncate-2 {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
    </style>
</head>
<body class="bg-light">
    <div id="themeManagerApp" class="container-fluid px-3 px-lg-4 py-3 py-lg-4" dir="<?= e($dir) ?>">
        
        <!-- Breadcrumb -->
        <nav aria-label="breadcrumb" class="mb-4">
            <ol class="breadcrumb">
                <li class="breadcrumb-item">
                    <a href="/admin/dashboard">
                        <i class="fas fa-home"></i> <?= __t('breadcrumb.home', 'Home') ?>
                    </a>
                </li>
                <li class="breadcrumb-item">
                    <a href="/admin/appearance"><?= __t('breadcrumb.appearance', 'Appearance') ?></a>
                </li>
                <li class="breadcrumb-item active" aria-current="page">
                    <?= __t('theme_manager.title', 'Theme Management') ?>
                </li>
            </ol>
        </nav>

        <!-- Main Header -->
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-3">
            <div>
                <h1 class="h2 mb-1">
                    <i class="fas fa-palette text-primary me-2"></i>
                    <?= __t('theme_manager.title', 'Theme Management') ?>
                </h1>
                <p class="text-muted mb-0">
                    <?= __t('theme_manager.subtitle', 'Create, edit, and manage admin interface themes') ?>
                </p>
            </div>
            
            <!-- Action Buttons -->
            <div class="d-flex flex-wrap gap-2">
                <!-- Bulk Actions Dropdown -->
                <div class="dropdown">
                    <button class="btn btn-outline-secondary dropdown-toggle" type="button" 
                            data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-layer-group me-1"></i>
                        <?= __t('theme_manager.bulk_actions', 'Bulk Actions') ?>
                    </button>
                    <ul class="dropdown-menu">
                        <li>
                            <button class="dropdown-item" type="button" id="btnBulkActivate">
                                <i class="fas fa-check-circle text-success me-2"></i>
                                <?= __t('theme_manager.activate_selected', 'Activate Selected') ?>
                            </button>
                        </li>
                        <li>
                            <button class="dropdown-item" type="button" id="btnBulkDeactivate">
                                <i class="fas fa-times-circle text-secondary me-2"></i>
                                <?= __t('theme_manager.deactivate_selected', 'Deactivate Selected') ?>
                            </button>
                        </li>
                        <?php if ($canDelete): ?>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <button class="dropdown-item text-danger" type="button" id="btnBulkDelete">
                                <i class="fas fa-trash-alt me-2"></i>
                                <?= __t('theme_manager.delete_selected', 'Delete Selected') ?>
                            </button>
                        </li>
                        <?php endif; ?>
                    </ul>
                </div>
                
                <?php if ($canExport): ?>
                <button type="button" class="btn btn-outline-primary" id="btnExportAll">
                    <i class="fas fa-file-export me-1"></i>
                    <?= __t('theme_manager.export_all', 'Export All') ?>
                </button>
                <?php endif; ?>
                
                <?php if ($canCreate): ?>
                <button type="button" class="btn btn-primary" id="btnAddTheme">
                    <i class="fas fa-plus me-1"></i>
                    <?= __t('theme_manager.add_new', 'Add New Theme') ?>
                </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- Bulk Selection Info -->
        <div id="bulkSelectionInfo" class="alert alert-info alert-dismissible fade show mb-3" style="display: none;">
            <div class="d-flex justify-content-between align-items-center">
                <span>
                    <i class="fas fa-check-circle me-2"></i>
                    <span id="selectedCount">0</span> <?= __t('theme_manager.themes_selected', 'themes selected') ?>
                </span>
                <button type="button" class="btn btn-sm btn-outline-info" id="btnClearSelection">
                    <?= __t('theme_manager.clear_selection', 'Clear Selection') ?>
                </button>
            </div>
        </div>

        <!-- Filter Card -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body p-3">
                <div class="row g-2 align-items-end">
                    <!-- Search -->
                    <div class="col-md-3">
                        <label class="form-label small fw-bold mb-1">
                            <i class="fas fa-search me-1"></i>
                            <?= __t('theme_manager.search', 'Search') ?>
                        </label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-white">
                                <i class="fas fa-magnifying-glass"></i>
                            </span>
                            <input type="search" class="form-control" id="searchInput" 
                                   placeholder="<?= __t('theme_manager.search_placeholder', 'Search themes...') ?>"
                                   aria-label="Search themes">
                            <button class="btn btn-outline-secondary" type="button" id="btnClearSearch" title="Clear search">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Status Filter -->
                    <div class="col-md-2">
                        <label class="form-label small fw-bold mb-1">
                            <i class="fas fa-toggle-on me-1"></i>
                            <?= __t('theme_manager.status', 'Status') ?>
                        </label>
                        <select class="form-select form-select-sm" id="statusFilter">
                            <option value=""><?= __t('theme_manager.all_statuses', 'All Statuses') ?></option>
                            <option value="1"><?= __t('theme_manager.active', 'Active') ?></option>
                            <option value="0"><?= __t('theme_manager.inactive', 'Inactive') ?></option>
                        </select>
                    </div>
                    
                    <!-- Type Filter -->
                    <div class="col-md-2">
                        <label class="form-label small fw-bold mb-1">
                            <i class="fas fa-tag me-1"></i>
                            <?= __t('theme_manager.type', 'Type') ?>
                        </label>
                        <select class="form-select form-select-sm" id="typeFilter">
                            <option value=""><?= __t('theme_manager.all_types', 'All Types') ?></option>
                            <option value="default"><?= __t('theme_manager.default', 'Default') ?></option>
                            <option value="custom"><?= __t('theme_manager.custom', 'Custom') ?></option>
                        </select>
                    </div>
                    
                    <!-- Sort By -->
                    <div class="col-md-2">
                        <label class="form-label small fw-bold mb-1">
                            <i class="fas fa-sort me-1"></i>
                            <?= __t('theme_manager.sort_by', 'Sort By') ?>
                        </label>
                        <select class="form-select form-select-sm" id="sortFilter">
                            <option value="created_at:desc"><?= __t('theme_manager.newest_first', 'Newest First') ?></option>
                            <option value="created_at:asc"><?= __t('theme_manager.oldest_first', 'Oldest First') ?></option>
                            <option value="name:asc"><?= __t('theme_manager.name_az', 'Name (A-Z)') ?></option>
                            <option value="name:desc"><?= __t('theme_manager.name_za', 'Name (Z-A)') ?></option>
                            <option value="version:desc"><?= __t('theme_manager.version_desc', 'Version (High to Low)') ?></option>
                        </select>
                    </div>
                    
                    <!-- Action Buttons -->
                    <div class="col-md-3 d-flex gap-2">
                        <button type="button" class="btn btn-sm btn-primary flex-fill" id="btnApplyFilters">
                            <i class="fas fa-filter me-1"></i>
                            <?= __t('theme_manager.apply_filters', 'Apply') ?>
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="btnResetFilters" 
                                title="<?= __t('theme_manager.reset_filters', 'Reset all filters') ?>">
                            <i class="fas fa-redo"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Loading State -->
        <div id="loadingState" class="card shadow-sm border-0">
            <div class="card-body text-center py-5">
                <div class="spinner-border text-primary mb-3" role="status">
                    <span class="visually-hidden"><?= __t('theme_manager.loading', 'Loading...') ?></span>
                </div>
                <h5 class="mb-2"><?= __t('theme_manager.loading_themes', 'Loading themes...') ?></h5>
                <p class="text-muted mb-0"><?= __t('theme_manager.please_wait', 'Please wait while we load your themes.') ?></p>
            </div>
        </div>

        <!-- Empty State -->
        <div id="emptyState" class="card shadow-sm border-0" style="display: none;">
            <div class="card-body text-center py-5">
                <div class="mb-4">
                    <i class="fas fa-palette fa-4x text-light bg-primary rounded-circle p-4"></i>
                </div>
                <h4 class="mb-3"><?= __t('theme_manager.no_themes_found', 'No Themes Found') ?></h4>
                <p class="text-muted mb-4">
                    <?= __t('theme_manager.no_themes_description', 'You haven\'t created any themes yet. Start by creating your first theme.') ?>
                </p>
                <?php if ($canCreate): ?>
                <button type="button" class="btn btn-primary" id="btnAddFirstTheme">
                    <i class="fas fa-plus me-2"></i>
                    <?= __t('theme_manager.create_first_theme', 'Create Your First Theme') ?>
                </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- Error State -->
        <div id="errorState" class="card shadow-sm border-0" style="display: none;">
            <div class="card-body text-center py-5">
                <div class="mb-4">
                    <i class="fas fa-exclamation-triangle fa-4x text-danger"></i>
                </div>
                <h4 class="mb-3"><?= __t('theme_manager.error_loading', 'Error Loading Themes') ?></h4>
                <p class="text-muted mb-3" id="errorMessage"></p>
                <div class="d-flex justify-content-center gap-2">
                    <button type="button" class="btn btn-outline-primary" id="btnRetry">
                        <i class="fas fa-redo me-1"></i>
                        <?= __t('theme_manager.retry', 'Try Again') ?>
                    </button>
                    <button type="button" class="btn btn-outline-secondary" id="btnReportError">
                        <i class="fas fa-bug me-1"></i>
                        <?= __t('theme_manager.report_error', 'Report Issue') ?>
                    </button>
                </div>
            </div>
        </div>

        <!-- Themes Table Container -->
        <div class="card shadow-sm border-0" id="tableContainer" style="display: none;">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-striped mb-0" id="themesTable">
                        <thead class="table-light">
                            <tr>
                                <th width="50" class="ps-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="selectAllThemes">
                                    </div>
                                </th>
                                <th width="70">ID</th>
                                <th><?= __t('theme_manager.theme', 'Theme') ?></th>
                                <th><?= __t('theme_manager.author', 'Author') ?></th>
                                <th><?= __t('theme_manager.version', 'Version') ?></th>
                                <th><?= __t('theme_manager.status', 'Status') ?></th>
                                <th><?= __t('theme_manager.colors', 'Colors') ?></th>
                                <th><?= __t('theme_manager.created', 'Created') ?></th>
                                <th class="text-end pe-3"><?= __t('theme_manager.actions', 'Actions') ?></th>
                            </tr>
                        </thead>
                        <tbody id="tableBody"></tbody>
                    </table>
                </div>
            </div>
            
            <!-- Table Footer -->
            <div class="card-footer bg-white border-top-0" id="tableFooter" style="display: none;">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-center gap-3">
                    <div class="text-muted small" id="paginationInfo"></div>
                    <nav aria-label="Page navigation">
                        <ul class="pagination pagination-sm mb-0" id="pagination"></ul>
                    </nav>
                </div>
            </div>
        </div>

        <!-- Theme Form Modal -->
        <div class="modal fade" id="themeModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="modalTitle">
                            <i class="fas fa-palette me-2"></i>
                            <?= __t('theme_manager.add_new', 'Add New Theme') ?>
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form id="themeForm" novalidate>
                        <div class="modal-body">
                            <!-- Hidden Fields -->
                            <input type="hidden" id="themeId" name="id">
                            <input type="hidden" name="tenant_id" value="<?= e($tenantId) ?>">
                            <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                            
                            <!-- Tabs -->
                            <ul class="nav nav-tabs mb-4" id="themeFormTabs" role="tablist">
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link active" id="basic-tab" data-bs-toggle="tab" 
                                            data-bs-target="#basic-tab-pane" type="button" role="tab">
                                        <i class="fas fa-info-circle me-1"></i>
                                        <?= __t('theme_manager.basic_info', 'Basic Info') ?>
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="colors-tab" data-bs-toggle="tab" 
                                            data-bs-target="#colors-tab-pane" type="button" role="tab">
                                        <i class="fas fa-fill-drip me-1"></i>
                                        <?= __t('theme_manager.colors', 'Colors') ?>
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="advanced-tab" data-bs-toggle="tab" 
                                            data-bs-target="#advanced-tab-pane" type="button" role="tab">
                                        <i class="fas fa-cogs me-1"></i>
                                        <?= __t('theme_manager.advanced', 'Advanced') ?>
                                    </button>
                                </li>
                            </ul>
                            
                            <!-- Tab Content -->
                            <div class="tab-content" id="themeFormTabsContent">
                                <!-- Basic Info Tab -->
                                <div class="tab-pane fade show active" id="basic-tab-pane" role="tabpanel">
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="themeName" class="form-label required">
                                                <?= __t('theme_manager.name', 'Theme Name') ?>
                                            </label>
                                            <input type="text" class="form-control" id="themeName" name="name" 
                                                   required maxlength="100"
                                                   placeholder="<?= __t('theme_manager.name_placeholder', 'e.g., Modern Dark Theme') ?>">
                                            <div class="form-text">
                                                <?= __t('theme_manager.name_help', 'A descriptive name for your theme') ?>
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-6 mb-3">
                                            <label for="themeSlug" class="form-label required">
                                                <?= __t('theme_manager.slug', 'Theme Slug') ?>
                                            </label>
                                            <div class="input-group">
                                                <input type="text" class="form-control" id="themeSlug" name="slug" 
                                                       required pattern="^[a-z0-9]+(?:-[a-z0-9]+)*$"
                                                       placeholder="<?= __t('theme_manager.slug_placeholder', 'modern-dark-theme') ?>">
                                                <button class="btn btn-outline-secondary" type="button" id="btnGenerateSlug">
                                                    <i class="fas fa-magic"></i>
                                                </button>
                                            </div>
                                            <div class="form-text">
                                                <?= __t('theme_manager.slug_help', 'URL-friendly version of the name (lowercase, hyphens)') ?>
                                            </div>
                                        </div>
                                        
                                        <div class="col-12 mb-3">
                                            <label for="themeDescription" class="form-label">
                                                <?= __t('theme_manager.description', 'Description') ?>
                                            </label>
                                            <textarea class="form-control" id="themeDescription" name="description" 
                                                      rows="3" maxlength="500"
                                                      placeholder="<?= __t('theme_manager.description_placeholder', 'Brief description of your theme...') ?>"></textarea>
                                        </div>
                                        
                                        <div class="col-md-6 mb-3">
                                            <label for="themeVersion" class="form-label required">
                                                <?= __t('theme_manager.version', 'Version') ?>
                                            </label>
                                            <input type="text" class="form-control" id="themeVersion" name="version" 
                                                   required pattern="^\d+\.\d+\.\d+$"
                                                   value="1.0.0"
                                                   placeholder="1.0.0">
                                        </div>
                                        
                                        <div class="col-md-6 mb-3">
                                            <label for="themeAuthor" class="form-label">
                                                <?= __t('theme_manager.author', 'Author') ?>
                                            </label>
                                            <input type="text" class="form-control" id="themeAuthor" name="author" 
                                                   maxlength="100"
                                                   placeholder="<?= __t('theme_manager.author_placeholder', 'Your name or company') ?>">
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Colors Tab -->
                                <div class="tab-pane fade" id="colors-tab-pane" role="tabpanel">
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="themePrimaryColor" class="form-label">
                                                <?= __t('theme_manager.primary_color', 'Primary Color') ?>
                                            </label>
                                            <div class="input-group">
                                                <input type="text" class="form-control colorpicker" 
                                                       id="themePrimaryColor" name="primary_color" value="#3b82f6">
                                                <span class="input-group-text">
                                                    <i class="fas fa-eye-dropper"></i>
                                                </span>
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-6 mb-3">
                                            <label for="themeSecondaryColor" class="form-label">
                                                <?= __t('theme_manager.secondary_color', 'Secondary Color') ?>
                                            </label>
                                            <div class="input-group">
                                                <input type="text" class="form-control colorpicker" 
                                                       id="themeSecondaryColor" name="secondary_color" value="#64748b">
                                                <span class="input-group-text">
                                                    <i class="fas fa-eye-dropper"></i>
                                                </span>
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-6 mb-3">
                                            <label for="themeSuccessColor" class="form-label">
                                                <?= __t('theme_manager.success_color', 'Success Color') ?>
                                            </label>
                                            <div class="input-group">
                                                <input type="text" class="form-control colorpicker" 
                                                       id="themeSuccessColor" name="success_color" value="#10b981">
                                                <span class="input-group-text">
                                                    <i class="fas fa-eye-dropper"></i>
                                                </span>
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-6 mb-3">
                                            <label for="themeDangerColor" class="form-label">
                                                <?= __t('theme_manager.danger_color', 'Danger Color') ?>
                                            </label>
                                            <div class="input-group">
                                                <input type="text" class="form-control colorpicker" 
                                                       id="themeDangerColor" name="danger_color" value="#ef4444">
                                                <span class="input-group-text">
                                                    <i class="fas fa-eye-dropper"></i>
                                                </span>
                                            </div>
                                        </div>
                                        
                                        <div class="col-12">
                                            <div class="card bg-light">
                                                <div class="card-body">
                                                    <h6 class="card-title">
                                                        <i class="fas fa-palette me-2"></i>
                                                        <?= __t('theme_manager.color_preview', 'Color Preview') ?>
                                                    </h6>
                                                    <div class="d-flex gap-3 flex-wrap" id="colorPreview">
                                                        <!-- Preview will be generated here -->
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Advanced Tab -->
                                <div class="tab-pane fade" id="advanced-tab-pane" role="tabpanel">
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="themeStatus" class="form-label">
                                                <?= __t('theme_manager.status', 'Status') ?>
                                            </label>
                                            <select class="form-select" id="themeStatus" name="is_active">
                                                <option value="1"><?= __t('theme_manager.active', 'Active') ?></option>
                                                <option value="0"><?= __t('theme_manager.inactive', 'Inactive') ?></option>
                                            </select>
                                        </div>
                                        
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">
                                                <?= __t('theme_manager.options', 'Options') ?>
                                            </label>
                                            <div class="form-check mb-2">
                                                <input class="form-check-input" type="checkbox" 
                                                       id="themeIsDefault" name="is_default">
                                                <label class="form-check-label" for="themeIsDefault">
                                                    <?= __t('theme_manager.set_as_default', 'Set as default theme') ?>
                                                </label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" 
                                                       id="themeIsPublic" name="is_public" checked>
                                                <label class="form-check-label" for="themeIsPublic">
                                                    <?= __t('theme_manager.make_public', 'Make theme public') ?>
                                                </label>
                                            </div>
                                        </div>
                                        
                                        <div class="col-12 mb-3">
                                            <label for="themeCustomCSS" class="form-label">
                                                <?= __t('theme_manager.custom_css', 'Custom CSS') ?>
                                            </label>
                                            <textarea class="form-control font-monospace" id="themeCustomCSS" 
                                                      name="custom_css" rows="6" 
                                                      placeholder="/* Add your custom CSS here */"></textarea>
                                            <div class="form-text">
                                                <?= __t('theme_manager.css_help', 'Optional custom CSS for advanced styling') ?>
                                            </div>
                                        </div>
                                        
                                        <div class="col-12">
                                            <div class="alert alert-info">
                                                <i class="fas fa-info-circle me-2"></i>
                                                <strong><?= __t('theme_manager.note', 'Note') ?>:</strong>
                                                <?= __t('theme_manager.advanced_note', 'Changes in advanced settings may affect theme behavior.') ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                <i class="fas fa-times me-1"></i>
                                <?= __t('theme_manager.cancel', 'Cancel') ?>
                            </button>
                            <button type="submit" class="btn btn-primary" id="btnSaveTheme">
                                <i class="fas fa-save me-1"></i>
                                <span id="saveButtonText"><?= __t('theme_manager.save', 'Save Theme') ?></span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Delete Confirmation Modal -->
        <div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header border-0">
                        <h5 class="modal-title text-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <?= __t('theme_manager.confirm_delete', 'Confirm Delete') ?>
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p><?= __t('theme_manager.delete_warning', 'Are you sure you want to delete this theme? This action cannot be undone.') ?></p>
                        <p class="mb-0 fw-bold" id="deleteThemeName"></p>
                    </div>
                    <div class="modal-footer border-0">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <?= __t('theme_manager.cancel', 'Cancel') ?>
                        </button>
                        <button type="button" class="btn btn-danger" id="btnConfirmDelete">
                            <i class="fas fa-trash me-1"></i>
                            <?= __t('theme_manager.delete', 'Delete') ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Preview Modal -->
        <div class="modal fade" id="previewModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-xl">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="fas fa-eye me-2"></i>
                            <?= __t('theme_manager.theme_preview', 'Theme Preview') ?>
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-0">
                        <div class="ratio ratio-16x9 bg-light">
                            <iframe id="previewFrame" class="border-0" title="Theme Preview"></iframe>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <?= __t('theme_manager.close', 'Close') ?>
                        </button>
                        <button type="button" class="btn btn-primary" id="btnActivatePreview">
                            <i class="fas fa-check me-1"></i>
                            <?= __t('theme_manager.activate_this_theme', 'Activate This Theme') ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Loading Overlay -->
        <div class="modal fade" id="loadingOverlay" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 bg-transparent shadow-none">
                    <div class="modal-body text-center">
                        <div class="spinner-border text-primary" style="width: 3rem; height: 3rem;" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-3 mb-0 text-white" id="loadingMessage"><?= __t('theme_manager.processing', 'Processing...') ?></p>
                    </div>
                </div>
            </div>
        </div>

    </div> <!-- End #themeManagerApp -->

    <!-- JavaScript Libraries -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-colorpicker/3.4.0/js/bootstrap-colorpicker.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
    
    <!-- Global Configuration -->
    <script>
    window.APP_CONFIG = {
        API_BASE: '<?= e($apiBase) ?>',
        TENANT_ID: <?= (int)$tenantId ?>,
        CSRF_TOKEN: '<?= e($csrf) ?>',
        USER_LANGUAGE: '<?= e($lang) ?>',
        USER_DIRECTION: '<?= e($dir) ?>',
        USER_ID: <?= (int)$userId ?>,
        PERMISSIONS: {
            canCreate: <?= $canCreate ? 'true' : 'false' ?>,
            canEdit: <?= $canEdit ? 'true' : 'false' ?>,
            canDelete: <?= $canDelete ? 'true' : 'false' ?>,
            canExport: <?= $canExport ? 'true' : 'false' ?>,
            canActivate: <?= $canActivate ? 'true' : 'false' ?>,
            isSuperAdmin: <?= $isSuperAdmin ? 'true' : 'false' ?>
        },
        TRANSLATIONS: {
            processing: '<?= __t('theme_manager.processing', 'Processing...') ?>',
            saving: '<?= __t('theme_manager.saving', 'Saving...') ?>',
            deleting: '<?= __t('theme_manager.deleting', 'Deleting...') ?>',
            loading: '<?= __t('theme_manager.loading', 'Loading...') ?>',
            confirm_delete_multiple: '<?= __t('theme_manager.confirm_delete_multiple', 'Are you sure you want to delete {count} themes?') ?>',
            no_themes_selected: '<?= __t('theme_manager.no_themes_selected', 'No themes selected') ?>',
            select_at_least_one: '<?= __t('theme_manager.select_at_least_one', 'Please select at least one theme') ?>',
            themes_activated: '<?= __t('theme_manager.themes_activated', '{count} themes activated') ?>',
            themes_deactivated: '<?= __t('theme_manager.themes_deactivated', '{count} themes deactivated') ?>',
            themes_deleted: '<?= __t('theme_manager.themes_deleted', '{count} themes deleted') ?>'
        },
        // API Endpoints for all theme-related resources
        ENDPOINTS: {
            themes: '<?= e($apiBase) ?>/themes',
            design_settings: '<?= e($apiBase) ?>/design_settings',
            color_settings: '<?= e($apiBase) ?>/color_settings',
            font_settings: '<?= e($apiBase) ?>/font_settings',
            button_styles: '<?= e($apiBase) ?>/button_styles',
            card_styles: '<?= e($apiBase) ?>/card_styles',
            system_settings: '<?= e($apiBase) ?>/system_settings',
            tenants: '<?= e($apiBase) ?>/tenants',
            roles: '<?= e($apiBase) ?>/roles'
        }
    };
    
    // Toastr Configuration
    toastr.options = {
        "closeButton": true,
        "progressBar": true,
        "positionClass": "toast-top-right",
        "preventDuplicates": true,
        "showDuration": "300",
        "hideDuration": "1000",
        "timeOut": "5000",
        "extendedTimeOut": "1000"
    };
    </script>
    
    <!-- Main Application Script -->
    <script src="/admin/assets/js/pages/AdminUiThemeLoader.js?v=<?= filemtime(__DIR__ . '/../../assets/js/pages/AdminUiThemeLoader.js') ?>"></script>
    
    <?php
    // Only include footer if not a fragment
    if (!$isFragment) {
        require_once __DIR__ . '/../includes/footer.php';
    }
    ?>
</body>
</html>