<?php
declare(strict_types=1);

if (!function_exists('admin_user')) {
    require_once __DIR__ . '/../includes/admin_context.php';
}

$isSuperAdmin = is_super_admin();
$canManage = $isSuperAdmin || can('manage_themes');

if (!$canManage) {
    echo '<div style="padding:2rem;background:#1e293b;border-radius:8px;margin:2rem;">
        <h3 style="color:#ef4444;"><i class="fas fa-ban"></i> Access Denied</h3>
        <p style="color:#e2e8f0;">You don\'t have permission to manage themes.</p>
    </div>';
    return;
}

$tenantId = admin_tenant_id();
$csrf = admin_csrf();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= htmlspecialchars($csrf) ?>">
    <title>Theme Management</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/admin/assets/css/themes-system.css?v=<?= time() ?>">
</head>
<body>

<div class="alerts-container" id="alertsContainer"></div>

<div class="container">
    
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-palette"></i>
                Theme Management
            </h1>
            <p class="page-subtitle">Manage Themes, Design Settings, and Styling</p>
        </div>
        <button class="btn btn-secondary btn-sm" onclick="ThemesApp.refreshAll()">
            <i class="fas fa-sync"></i> Refresh
        </button>
    </div>

    <div class="main-tabs">
        <button class="main-tab active" data-tab="themes">
            <i class="fas fa-palette"></i>
            <span>Themes</span>
        </button>
        <button class="main-tab" data-tab="design">
            <i class="fas fa-cog"></i>
            <span>Design Settings</span>
        </button>
        <button class="main-tab" data-tab="colors">
            <i class="fas fa-paint-brush"></i>
            <span>Colors</span>
        </button>
        <button class="main-tab" data-tab="fonts">
            <i class="fas fa-font"></i>
            <span>Fonts</span>
        </button>
        <button class="main-tab" data-tab="buttons">
            <i class="fas fa-mouse-pointer"></i>
            <span>Buttons</span>
        </button>
        <button class="main-tab" data-tab="cards">
            <i class="fas fa-square"></i>
            <span>Cards</span>
        </button>
        <button class="main-tab" data-tab="homepage">
            <i class="fas fa-home"></i>
            <span>Homepage</span>
        </button>
    </div>

    <!-- THEMES TAB -->
    <div class="tab-content active" id="tab-themes">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-palette"></i> Themes List
                </h3>
                <button class="btn btn-primary" onclick="ThemesApp.openThemeModal()">
                    <i class="fas fa-plus"></i> Add Theme
                </button>
            </div>
            <div class="card-body">
                <div id="themesLoading" class="loading">
                    <div class="spinner"></div>
                    <p>Loading...</p>
                </div>
                <div id="themesContent" style="display:none;">
                    <div class="theme-grid" id="themesGrid"></div>
                </div>
                <div id="themesEmpty" class="empty-state" style="display:none;">
                    <i class="fas fa-palette"></i>
                    <h3>No Themes</h3>
                    <button class="btn btn-primary" onclick="ThemesApp.openThemeModal()">
                        <i class="fas fa-plus"></i> Add First Theme
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- DESIGN SETTINGS TAB -->
    <div class="tab-content" id="tab-design">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-palette"></i> Select Theme</h3>
            </div>
            <div class="card-body">
                <div class="theme-selector" id="designThemeSelector"></div>
            </div>
        </div>
        <div class="card" id="designCard" style="display:none;">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-cog"></i> Design Settings for <span id="designThemeName"></span>
                </h3>
                <button class="btn btn-success" onclick="ThemesApp.saveDesignSettings()">
                    <i class="fas fa-save"></i> Save
                </button>
            </div>
            <div class="card-body">
                <div id="designLoading" class="loading">
                    <div class="spinner"></div>
                    <p>Loading...</p>
                </div>
                <div id="designContent" style="display:none;">
                    <div class="settings-grid" id="designSettingsGrid"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- COLORS TAB -->
    <div class="tab-content" id="tab-colors">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-palette"></i> Select Theme</h3>
            </div>
            <div class="card-body">
                <div class="theme-selector" id="colorsThemeSelector"></div>
            </div>
        </div>
        <div class="card" id="colorsCard" style="display:none;">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-paint-brush"></i> Color Settings for <span id="colorsThemeName"></span>
                </h3>
                <button class="btn btn-success" onclick="ThemesApp.saveColorSettings()">
                    <i class="fas fa-save"></i> Save
                </button>
            </div>
            <div class="card-body">
                <div id="colorsLoading" class="loading">
                    <div class="spinner"></div>
                    <p>Loading...</p>
                </div>
                <div id="colorsContent" style="display:none;">
                    <div class="color-settings-grid" id="colorSettingsGrid"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- FONTS TAB -->
    <div class="tab-content" id="tab-fonts">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-palette"></i> Select Theme</h3>
            </div>
            <div class="card-body">
                <div class="theme-selector" id="fontsThemeSelector"></div>
            </div>
        </div>
        <div class="card" id="fontsCard" style="display:none;">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-font"></i> Font Settings for <span id="fontsThemeName"></span>
                </h3>
                <button class="btn btn-success" onclick="ThemesApp.saveFontSettings()">
                    <i class="fas fa-save"></i> Save
                </button>
            </div>
            <div class="card-body">
                <div id="fontsLoading" class="loading">
                    <div class="spinner"></div>
                    <p>Loading...</p>
                </div>
                <div id="fontsContent" style="display:none;">
                    <div class="font-settings-grid" id="fontSettingsGrid"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- BUTTONS TAB -->
    <div class="tab-content" id="tab-buttons">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-mouse-pointer"></i> Button Styles
                </h3>
                <button class="btn btn-primary" onclick="ThemesApp.openButtonModal()">
                    <i class="fas fa-plus"></i> Add Button Style
                </button>
            </div>
            <div class="card-body">
                <div id="buttonsLoading" class="loading">
                    <div class="spinner"></div>
                    <p>Loading...</p>
                </div>
                <div id="buttonsContent" style="display:none;">
                    <div class="button-styles-grid" id="buttonStylesGrid"></div>
                </div>
                <div id="buttonsEmpty" class="empty-state" style="display:none;">
                    <i class="fas fa-mouse-pointer"></i>
                    <h3>No Button Styles</h3>
                    <button class="btn btn-primary" onclick="ThemesApp.openButtonModal()">
                        <i class="fas fa-plus"></i> Add First Style
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- CARDS TAB -->
    <div class="tab-content" id="tab-cards">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-square"></i> Card Styles
                </h3>
                <button class="btn btn-primary" onclick="ThemesApp.openCardModal()">
                    <i class="fas fa-plus"></i> Add Card Style
                </button>
            </div>
            <div class="card-body">
                <div id="cardsLoading" class="loading">
                    <div class="spinner"></div>
                    <p>Loading...</p>
                </div>
                <div id="cardsContent" style="display:none;">
                    <div class="card-styles-grid" id="cardStylesGrid"></div>
                </div>
                <div id="cardsEmpty" class="empty-state" style="display:none;">
                    <i class="fas fa-square"></i>
                    <h3>No Card Styles</h3>
                    <button class="btn btn-primary" onclick="ThemesApp.openCardModal()">
                        <i class="fas fa-plus"></i> Add First Style
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- HOMEPAGE TAB -->
    <div class="tab-content" id="tab-homepage">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-home"></i> Homepage Sections
                </h3>
                <button class="btn btn-primary" onclick="ThemesApp.openSectionModal()">
                    <i class="fas fa-plus"></i> Add Section
                </button>
            </div>
            <div class="card-body">
                <div id="homepageLoading" class="loading">
                    <div class="spinner"></div>
                    <p>Loading...</p>
                </div>
                <div id="homepageContent" style="display:none;">
                    <div class="sections-list" id="sectionsList"></div>
                </div>
                <div id="homepageEmpty" class="empty-state" style="display:none;">
                    <i class="fas fa-home"></i>
                    <h3>No Homepage Sections</h3>
                    <button class="btn btn-primary" onclick="ThemesApp.openSectionModal()">
                        <i class="fas fa-plus"></i> Add First Section
                    </button>
                </div>
            </div>
        </div>
    </div>

</div>

<!-- THEME MODAL -->
<div class="modal" id="themeModal">
    <div class="modal-dialog modal-lg">
        <div class="modal-header">
            <h3 class="modal-title" id="themeModalTitle">Add Theme</h3>
            <button class="modal-close" onclick="ThemesApp.closeThemeModal()">&times;</button>
        </div>
        <div class="modal-body">
            <form id="themeForm" onsubmit="return false;">
                <input type="hidden" id="themeId">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Name *</label>
                        <input type="text" class="form-control" id="themeName" required>
                    </div>
                    <div class="form-group">
                        <label>Slug *</label>
                        <input type="text" class="form-control" id="themeSlug" required>
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <textarea class="form-control" id="themeDescription" rows="3"></textarea>
                    </div>
                    <div class="form-group">
                        <label>Version</label>
                        <input type="text" class="form-control" id="themeVersion" value="1.0.0">
                    </div>
                    <div class="form-group">
                        <label>Author</label>
                        <input type="text" class="form-control" id="themeAuthor">
                    </div>
                    <div class="form-group">
                        <label>Thumbnail URL</label>
                        <input type="url" class="form-control" id="themeThumbnailUrl">
                    </div>
                    <div class="form-group">
                        <label>Preview URL</label>
                        <input type="url" class="form-control" id="themePreviewUrl">
                    </div>
                    <div class="form-group">
                        <label class="checkbox-wrapper">
                            <input type="checkbox" id="themeIsActive"> Active
                        </label>
                    </div>
                    <div class="form-group">
                        <label class="checkbox-wrapper">
                            <input type="checkbox" id="themeIsDefault"> Default Theme
                        </label>
                    </div>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="ThemesApp.closeThemeModal()">Cancel</button>
            <button class="btn btn-primary" onclick="ThemesApp.saveTheme()">
                <i class="fas fa-save"></i> Save
            </button>
        </div>
    </div>
</div>

<!-- BUTTON MODAL -->
<div class="modal" id="buttonModal">
    <div class="modal-dialog modal-lg">
        <div class="modal-header">
            <h3 class="modal-title" id="buttonModalTitle">Add Button Style</h3>
            <button class="modal-close" onclick="ThemesApp.closeButtonModal()">&times;</button>
        </div>
        <div class="modal-body">
            <form id="buttonForm" onsubmit="return false;">
                <input type="hidden" id="buttonId">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Name *</label>
                        <input type="text" class="form-control" id="buttonName" required>
                    </div>
                    <div class="form-group">
                        <label>Slug *</label>
                        <input type="text" class="form-control" id="buttonSlug" required>
                    </div>
                    <div class="form-group">
                        <label>Type</label>
                        <select class="form-control" id="buttonType">
                            <option value="primary">Primary</option>
                            <option value="secondary">Secondary</option>
                            <option value="success">Success</option>
                            <option value="danger">Danger</option>
                            <option value="warning">Warning</option>
                            <option value="info">Info</option>
                            <option value="outline">Outline</option>
                            <option value="link">Link</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Background Color</label>
                        <input type="color" class="form-control" id="buttonBgColor" value="#007bff">
                    </div>
                    <div class="form-group">
                        <label>Text Color</label>
                        <input type="color" class="form-control" id="buttonTextColor" value="#ffffff">
                    </div>
                    <div class="form-group">
                        <label>Border Color</label>
                        <input type="color" class="form-control" id="buttonBorderColor" value="#007bff">
                    </div>
                    <div class="form-group">
                        <label>Border Width (px)</label>
                        <input type="number" class="form-control" id="buttonBorderWidth" min="0" value="0">
                    </div>
                    <div class="form-group">
                        <label>Border Radius (px)</label>
                        <input type="number" class="form-control" id="buttonBorderRadius" min="0" value="4">
                    </div>
                    <div class="form-group">
                        <label>Padding</label>
                        <input type="text" class="form-control" id="buttonPadding" value="10px 20px">
                    </div>
                    <div class="form-group">
                        <label>Font Size</label>
                        <input type="text" class="form-control" id="buttonFontSize" value="14px">
                    </div>
                    <div class="form-group">
                        <label>Font Weight</label>
                        <input type="text" class="form-control" id="buttonFontWeight" value="normal">
                    </div>
                    <div class="form-group">
                        <label>Hover Background</label>
                        <input type="color" class="form-control" id="buttonHoverBgColor">
                    </div>
                    <div class="form-group">
                        <label>Hover Text Color</label>
                        <input type="color" class="form-control" id="buttonHoverTextColor">
                    </div>
                    <div class="form-group">
                        <label>Hover Border Color</label>
                        <input type="color" class="form-control" id="buttonHoverBorderColor">
                    </div>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="ThemesApp.closeButtonModal()">Cancel</button>
            <button class="btn btn-primary" onclick="ThemesApp.saveButton()">
                <i class="fas fa-save"></i> Save
            </button>
        </div>
    </div>
</div>

<!-- CARD MODAL -->
<div class="modal" id="cardModal">
    <div class="modal-dialog modal-lg">
        <div class="modal-header">
            <h3 class="modal-title" id="cardModalTitle">Add Card Style</h3>
            <button class="modal-close" onclick="ThemesApp.closeCardModal()">&times;</button>
        </div>
        <div class="modal-body">
            <form id="cardForm" onsubmit="return false;">
                <input type="hidden" id="cardId">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Name *</label>
                        <input type="text" class="form-control" id="cardName" required>
                    </div>
                    <div class="form-group">
                        <label>Slug *</label>
                        <input type="text" class="form-control" id="cardSlug" required>
                    </div>
                    <div class="form-group">
                        <label>Type</label>
                        <select class="form-control" id="cardType">
                            <option value="product">Product</option>
                            <option value="category">Category</option>
                            <option value="vendor">Vendor</option>
                            <option value="blog">Blog</option>
                            <option value="feature">Feature</option>
                            <option value="testimonial">Testimonial</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Background Color</label>
                        <input type="color" class="form-control" id="cardBgColor" value="#ffffff">
                    </div>
                    <div class="form-group">
                        <label>Border Color</label>
                        <input type="color" class="form-control" id="cardBorderColor" value="#e0e0e0">
                    </div>
                    <div class="form-group">
                        <label>Border Width (px)</label>
                        <input type="number" class="form-control" id="cardBorderWidth" min="0" value="1">
                    </div>
                    <div class="form-group">
                        <label>Border Radius (px)</label>
                        <input type="number" class="form-control" id="cardBorderRadius" min="0" value="8">
                    </div>
                    <div class="form-group">
                        <label>Shadow Style</label>
                        <select class="form-control" id="cardShadowStyle">
                            <option value="none">None</option>
                            <option value="small">Small</option>
                            <option value="medium">Medium</option>
                            <option value="large">Large</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Padding</label>
                        <input type="text" class="form-control" id="cardPadding" value="16px">
                    </div>
                    <div class="form-group">
                        <label>Hover Effect</label>
                        <select class="form-control" id="cardHoverEffect">
                            <option value="none">None</option>
                            <option value="lift">Lift</option>
                            <option value="zoom">Zoom</option>
                            <option value="shadow">Shadow</option>
                            <option value="border">Border</option>
                            <option value="brightness">Brightness</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Text Align</label>
                        <select class="form-control" id="cardTextAlign">
                            <option value="left">Left</option>
                            <option value="center">Center</option>
                            <option value="right">Right</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Image Aspect Ratio</label>
                        <input type="text" class="form-control" id="cardAspectRatio" value="1:1">
                    </div>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="ThemesApp.closeCardModal()">Cancel</button>
            <button class="btn btn-primary" onclick="ThemesApp.saveCard()">
                <i class="fas fa-save"></i> Save
            </button>
        </div>
    </div>
</div>

<!-- SECTION MODAL -->
<div class="modal" id="sectionModal">
    <div class="modal-dialog modal-xl">
        <div class="modal-header">
            <h3 class="modal-title" id="sectionModalTitle">Add Homepage Section</h3>
            <button class="modal-close" onclick="ThemesApp.closeSectionModal()">&times;</button>
        </div>
        <div class="modal-body">
            <form id="sectionForm" onsubmit="return false;">
                <input type="hidden" id="sectionId">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Type *</label>
                        <select class="form-control" id="sectionType" required>
                            <option value="slider">Slider</option>
                            <option value="categories">Categories</option>
                            <option value="featured_products">Featured Products</option>
                            <option value="new_products">New Products</option>
                            <option value="deals">Deals</option>
                            <option value="brands">Brands</option>
                            <option value="vendors">Vendors</option>
                            <option value="banners">Banners</option>
                            <option value="testimonials">Testimonials</option>
                            <option value="custom_html">Custom HTML</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Title</label>
                        <input type="text" class="form-control" id="sectionTitle">
                    </div>
                    <div class="form-group">
                        <label>Subtitle</label>
                        <input type="text" class="form-control" id="sectionSubtitle">
                    </div>
                    <div class="form-group">
                        <label>Layout Type</label>
                        <select class="form-control" id="sectionLayoutType">
                            <option value="grid">Grid</option>
                            <option value="slider">Slider</option>
                            <option value="list">List</option>
                            <option value="carousel">Carousel</option>
                            <option value="masonry">Masonry</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Items Per Row</label>
                        <input type="number" class="form-control" id="sectionItemsPerRow" min="1" max="12" value="4">
                    </div>
                    <div class="form-group">
                        <label>Background Color</label>
                        <input type="color" class="form-control" id="sectionBgColor" value="#ffffff">
                    </div>
                    <div class="form-group">
                        <label>Text Color</label>
                        <input type="color" class="form-control" id="sectionTextColor" value="#000000">
                    </div>
                    <div class="form-group">
                        <label>Padding</label>
                        <input type="text" class="form-control" id="sectionPadding" value="40px 0">
                    </div>
                    <div class="form-group">
                        <label>Custom CSS</label>
                        <textarea class="form-control" id="sectionCustomCss" rows="4" placeholder="Additional CSS styles"></textarea>
                    </div>
                    <div class="form-group">
                        <label>Custom HTML</label>
                        <textarea class="form-control" id="sectionCustomHtml" rows="4" placeholder="Custom HTML content"></textarea>
                    </div>
                    <div class="form-group">
                        <label>Data Source</label>
                        <input type="text" class="form-control" id="sectionDataSource" placeholder="API endpoint or query">
                    </div>
                    <div class="form-group">
                        <label>Sort Order</label>
                        <input type="number" class="form-control" id="sectionSortOrder" min="0" value="0">
                    </div>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="ThemesApp.closeSectionModal()">Cancel</button>
            <button class="btn btn-primary" onclick="ThemesApp.saveSection()">
                <i class="fas fa-save"></i> Save
            </button>
        </div>
    </div>
</div>

<script>
window.APP_CONFIG = {
    API_BASE: '/api',
    TENANT_ID: <?= $tenantId ?>,
    CSRF_TOKEN: '<?= htmlspecialchars($csrf) ?>'
};
</script>
<script src="/admin/assets/js/themes-system.js?v=<?= time() ?>"></script>

<script>
(function() {
    'use strict';
    
    function ready(callback) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', callback);
        } else {
            callback();
        }
    }
    
    ready(function() {
        setTimeout(function() {
            console.log('Themes Fragment ready');
            if (window.Admin && window.Admin.initPageFromFragment) {
                window.Admin.initPageFromFragment(document.querySelector('.container'));
            } else if (window.page && typeof window.page.run === 'function') {
                window.page.run();
            } else if (window.ThemesApp && typeof window.ThemesApp.init === 'function') {
                window.ThemesApp.init();
            }
        }, 200);
    });
})();
</script>

</body>
</html>