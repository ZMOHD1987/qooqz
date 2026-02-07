<?php
/**
 * themes.php — Fragment matching categories.php pattern
 */

$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
$isEmbedded = isset($_GET['embedded']) || isset($_POST['embedded']);
$isFragment = $isAjax || $isEmbedded;

if ($isFragment) {
    $contextFile = __DIR__ . '/../admin_context.php';
    if (file_exists($contextFile)) require_once $contextFile;
} else {
    require_once __DIR__ . '/../header.php';
}

$canCreate = function_exists('can') && (can('manage_themes') || (function_exists('is_super_admin') && is_super_admin()));
$canEdit   = $canCreate;
$canDelete = $canCreate;
$tenantId  = $_SESSION['tenant_id'] ?? 1;
$userLang  = $_SESSION['user']['preferred_language'] ?? 'en';
$csrfToken = $_SESSION['csrf_token'] ?? '';

if ($isFragment) {
    echo '<link rel="stylesheet" href="/admin/assets/css/themes-system.css">';
}
?>

<meta data-page="themes" data-i18n-files="/admin/languages/AdminUiTheme/<?= htmlspecialchars($userLang) ?>.json">

<script>
window.THEMES_CONFIG = {
    TENANT_ID: <?= (int)$tenantId ?>,
    USER_LANG: '<?= htmlspecialchars($userLang) ?>',
    CSRF_TOKEN: '<?= htmlspecialchars($csrfToken) ?>',
    CAN_CREATE: <?= $canCreate ? 'true' : 'false' ?>,
    CAN_EDIT: <?= $canEdit ? 'true' : 'false' ?>,
    CAN_DELETE: <?= $canDelete ? 'true' : 'false' ?>
};
</script>

<div class="themes-page" id="themesPage" dir="<?= $userLang === 'ar' ? 'rtl' : 'ltr' ?>">
<div class="page-container">

    <!-- ═══ Page Header ═══ -->
    <div class="page-header">
        <div class="header-info">
            <h1 class="page-title" data-i18n="page.title">Themes Management</h1>
            <p class="page-subtitle" data-i18n="page.subtitle">Manage themes and their settings</p>
        </div>
        <div class="page-header-right">
            <?php if ($canCreate): ?>
            <button class="btn btn-primary" id="btnAddTheme"><i class="fas fa-plus"></i> <span data-i18n="actions.add_new">Add New</span></button>
            <?php endif; ?>
        </div>
    </div>

    <!-- ═══ List View ═══ -->
    <div class="card" id="themesList">
        <div class="card-header">
            <h3 data-i18n="list.title">Themes</h3>
            <div class="filters-bar">
                <input type="text" class="search-input" id="searchThemes" placeholder="Search..." data-i18n-placeholder="filters.search">
                <select class="filter-select" id="filterStatus">
                    <option value="" data-i18n="filters.all_status">All Status</option>
                    <option value="1" data-i18n="filters.active">Active</option>
                    <option value="0" data-i18n="filters.inactive">Inactive</option>
                </select>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="data-table">
                    <thead><tr>
                        <th data-i18n="table.id">ID</th>
                        <th data-i18n="table.name">Name</th>
                        <th data-i18n="table.slug">Slug</th>
                        <th data-i18n="table.version">Version</th>
                        <th data-i18n="table.status">Status</th>
                        <th data-i18n="table.default">Default</th>
                        <th data-i18n="table.actions">Actions</th>
                    </tr></thead>
                    <tbody id="themesTableBody">
                        <tr><td colspan="7" class="loading-state"><div class="spinner"></div></td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ═══ Form View ═══ -->
    <div class="card form-card" id="themeForm">
        <div class="card-header">
            <h3 id="formTitle" data-i18n="form.add_title">Add Theme</h3>
            <button class="btn btn-outline btn-sm" id="btnCancel"><i class="fas fa-arrow-left"></i> <span data-i18n="actions.back">Back</span></button>
        </div>
        <div class="card-body">
            <input type="hidden" id="themeId">

            <!-- Tabs -->
            <div class="tabs-nav">
                <button class="tab-btn active" data-tab="info" data-i18n="tabs.info">Theme Info</button>
                <button class="tab-btn" data-tab="design" data-i18n="tabs.design">Design Settings</button>
                <button class="tab-btn" data-tab="colors" data-i18n="tabs.colors">Color Settings</button>
                <button class="tab-btn" data-tab="fonts" data-i18n="tabs.fonts">Font Settings</button>
                <button class="tab-btn" data-tab="buttons" data-i18n="tabs.buttons">Button & Card Styles</button>
                <button class="tab-btn" data-tab="system" data-i18n="tabs.system">System Settings</button>
            </div>

            <!-- ─── Tab: Info ─── -->
            <div class="tab-panel active" id="tabInfo">
                <div class="form-row">
                    <div class="form-group"><label data-i18n="fields.name">Name</label><input type="text" id="fName" required></div>
                    <div class="form-group"><label data-i18n="fields.slug">Slug</label><input type="text" id="fSlug"></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label data-i18n="fields.description">Description</label><textarea id="fDescription" rows="2"></textarea></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label data-i18n="fields.version">Version</label><input type="text" id="fVersion" value="1.0.0"></div>
                    <div class="form-group"><label data-i18n="fields.author">Author</label><input type="text" id="fAuthor"></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label data-i18n="fields.thumbnail_url">Thumbnail URL</label><input type="url" id="fThumbnailUrl"></div>
                    <div class="form-group"><label data-i18n="fields.preview_url">Preview URL</label><input type="url" id="fPreviewUrl"></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label data-i18n="fields.is_active">Active</label><select id="fIsActive"><option value="1" data-i18n="common.yes">Yes</option><option value="0" data-i18n="common.no">No</option></select></div>
                    <div class="form-group"><label data-i18n="fields.is_default">Default</label><select id="fIsDefault"><option value="0" data-i18n="common.no">No</option><option value="1" data-i18n="common.yes">Yes</option></select></div>
                </div>
            </div>

            <!-- ─── Tab: Design ─── -->
            <div class="tab-panel" id="tabDesign">
                <div class="settings-list" id="designList"></div>
                <button class="btn btn-outline btn-sm" id="btnAddDesign"><i class="fas fa-plus"></i> <span data-i18n="actions.add_setting">Add Setting</span></button>
                <div class="inline-form" id="designForm">
                    <div class="inline-form-header"><h4 data-i18n="design.add_title">Add Design Setting</h4></div>
                    <div class="form-row">
                        <div class="form-group"><label data-i18n="design.key">Key</label><input type="text" id="dsKey"></div>
                        <div class="form-group"><label data-i18n="design.name">Name</label><input type="text" id="dsName"></div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label data-i18n="design.value">Value</label><input type="text" id="dsValue"></div>
                        <div class="form-group"><label data-i18n="design.type">Type</label>
                            <select id="dsType"><option value="text">text</option><option value="number">number</option><option value="color">color</option><option value="image">image</option><option value="boolean">boolean</option><option value="select">select</option><option value="json">json</option></select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label data-i18n="design.category">Category</label>
                            <select id="dsCategory"><option value="layout">layout</option><option value="header">header</option><option value="footer">footer</option><option value="sidebar">sidebar</option><option value="homepage">homepage</option><option value="product">product</option><option value="cart">cart</option><option value="checkout">checkout</option><option value="other">other</option></select>
                        </div>
                        <div class="form-group"><label data-i18n="design.sort_order">Sort</label><input type="number" id="dsSortOrder" value="0"></div>
                        <div class="form-group"><label data-i18n="common.is_active">Active</label><select id="dsIsActive"><option value="1" data-i18n="common.yes">Yes</option><option value="0" data-i18n="common.no">No</option></select></div>
                    </div>
                    <input type="hidden" id="dsId">
                    <div class="form-actions"><button class="btn btn-primary btn-sm" id="btnSaveDesign"><i class="fas fa-check"></i> <span data-i18n="actions.save">Save</span></button><button class="btn btn-outline btn-sm" id="btnCancelDesign" data-i18n="actions.cancel">Cancel</button></div>
                </div>
            </div>

            <!-- ─── Tab: Colors ─── -->
            <div class="tab-panel" id="tabColors">
                <div class="settings-list" id="colorsList"></div>
                <button class="btn btn-outline btn-sm" id="btnAddColor"><i class="fas fa-plus"></i> <span data-i18n="actions.add_setting">Add Setting</span></button>
                <div class="inline-form" id="colorsForm">
                    <div class="inline-form-header"><h4 data-i18n="colors.add_title">Add Color Setting</h4></div>
                    <div class="form-row">
                        <div class="form-group"><label data-i18n="colors.key">Key</label><input type="text" id="csKey"></div>
                        <div class="form-group"><label data-i18n="colors.name">Name</label><input type="text" id="csName"></div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label data-i18n="colors.value">Color</label><input type="color" id="csValue" value="#3b82f6"></div>
                        <div class="form-group"><label data-i18n="colors.category">Category</label>
                            <select id="csCategory"><option value="primary">primary</option><option value="secondary">secondary</option><option value="accent">accent</option><option value="background">background</option><option value="text">text</option><option value="border">border</option><option value="status">status</option><option value="other">other</option></select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label data-i18n="colors.sort_order">Sort</label><input type="number" id="csSortOrder" value="0"></div>
                        <div class="form-group"><label data-i18n="common.is_active">Active</label><select id="csIsActive"><option value="1" data-i18n="common.yes">Yes</option><option value="0" data-i18n="common.no">No</option></select></div>
                    </div>
                    <input type="hidden" id="csId">
                    <div class="form-actions"><button class="btn btn-primary btn-sm" id="btnSaveColor"><i class="fas fa-check"></i> <span data-i18n="actions.save">Save</span></button><button class="btn btn-outline btn-sm" id="btnCancelColor" data-i18n="actions.cancel">Cancel</button></div>
                </div>
            </div>

            <!-- ─── Tab: Fonts ─── -->
            <div class="tab-panel" id="tabFonts">
                <div class="settings-list" id="fontsList"></div>
                <button class="btn btn-outline btn-sm" id="btnAddFont"><i class="fas fa-plus"></i> <span data-i18n="actions.add_setting">Add Setting</span></button>
                <div class="inline-form" id="fontsForm">
                    <div class="inline-form-header"><h4 data-i18n="fonts.add_title">Add Font Setting</h4></div>
                    <div class="form-row">
                        <div class="form-group"><label data-i18n="fonts.key">Key</label><input type="text" id="fsKey"></div>
                        <div class="form-group"><label data-i18n="fonts.name">Name</label><input type="text" id="fsName"></div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label data-i18n="fonts.family">Family</label><input type="text" id="fsFamily"></div>
                        <div class="form-group"><label data-i18n="fonts.size">Size</label><input type="text" id="fsSize" placeholder="16px"></div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label data-i18n="fonts.weight">Weight</label><input type="text" id="fsWeight" placeholder="normal"></div>
                        <div class="form-group"><label data-i18n="fonts.line_height">Line Height</label><input type="text" id="fsLineHeight" placeholder="1.5"></div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label data-i18n="fonts.category">Category</label>
                            <select id="fsCategory"><option value="heading">heading</option><option value="body">body</option><option value="button">button</option><option value="navigation">navigation</option><option value="other">other</option></select>
                        </div>
                        <div class="form-group"><label data-i18n="fonts.sort_order">Sort</label><input type="number" id="fsSortOrder" value="0"></div>
                        <div class="form-group"><label data-i18n="common.is_active">Active</label><select id="fsIsActive"><option value="1" data-i18n="common.yes">Yes</option><option value="0" data-i18n="common.no">No</option></select></div>
                    </div>
                    <input type="hidden" id="fsId">
                    <div class="form-actions"><button class="btn btn-primary btn-sm" id="btnSaveFont"><i class="fas fa-check"></i> <span data-i18n="actions.save">Save</span></button><button class="btn btn-outline btn-sm" id="btnCancelFont" data-i18n="actions.cancel">Cancel</button></div>
                </div>
            </div>

            <!-- ─── Tab: Buttons & Cards ─── -->
            <div class="tab-panel" id="tabButtons">
                <h4 style="margin:0 0 8px; color:var(--text-primary,#fff);" data-i18n="buttons.title">Button Styles</h4>
                <div class="settings-list" id="buttonsList"></div>
                <button class="btn btn-outline btn-sm" id="btnAddButton"><i class="fas fa-plus"></i> <span data-i18n="actions.add_setting">Add</span></button>
                <div class="inline-form" id="buttonsForm">
                    <div class="inline-form-header"><h4 data-i18n="buttons.add_title">Add Button Style</h4></div>
                    <div class="form-row">
                        <div class="form-group"><label data-i18n="buttons.name">Name</label><input type="text" id="bsName"></div>
                        <div class="form-group"><label data-i18n="buttons.slug">Slug</label><input type="text" id="bsSlug"></div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label data-i18n="buttons.type">Type</label>
                            <select id="bsType"><option value="primary">primary</option><option value="secondary">secondary</option><option value="success">success</option><option value="danger">danger</option><option value="warning">warning</option><option value="info">info</option><option value="outline">outline</option><option value="link">link</option></select>
                        </div>
                        <div class="form-group"><label data-i18n="buttons.bg_color">BG Color</label><input type="color" id="bsBgColor" value="#3b82f6"></div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label data-i18n="buttons.text_color">Text</label><input type="color" id="bsTextColor" value="#ffffff"></div>
                        <div class="form-group"><label data-i18n="buttons.border_color">Border</label><input type="color" id="bsBorderColor" value="#3b82f6"></div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label data-i18n="buttons.border_width">Border W</label><input type="number" id="bsBorderWidth" value="0"></div>
                        <div class="form-group"><label data-i18n="buttons.border_radius">Radius</label><input type="number" id="bsBorderRadius" value="4"></div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label data-i18n="buttons.padding">Padding</label><input type="text" id="bsPadding" value="10px 20px"></div>
                        <div class="form-group"><label data-i18n="buttons.font_size">Font Size</label><input type="text" id="bsFontSize" value="14px"></div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label data-i18n="buttons.font_weight">Font Weight</label><input type="text" id="bsFontWeight" value="normal"></div>
                        <div class="form-group"><label data-i18n="common.is_active">Active</label><select id="bsIsActive"><option value="1" data-i18n="common.yes">Yes</option><option value="0" data-i18n="common.no">No</option></select></div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label data-i18n="buttons.hover_bg">Hover BG</label><input type="color" id="bsHoverBg"></div>
                        <div class="form-group"><label data-i18n="buttons.hover_text">Hover Text</label><input type="color" id="bsHoverText"></div>
                        <div class="form-group"><label data-i18n="buttons.hover_border">Hover Border</label><input type="color" id="bsHoverBorder"></div>
                    </div>
                    <input type="hidden" id="bsId">
                    <div class="form-actions"><button class="btn btn-primary btn-sm" id="btnSaveButton"><i class="fas fa-check"></i> <span data-i18n="actions.save">Save</span></button><button class="btn btn-outline btn-sm" id="btnCancelButton" data-i18n="actions.cancel">Cancel</button></div>
                </div>

                <hr style="border-color:var(--border-color,#263044); margin:20px 0;">

                <h4 style="margin:0 0 8px; color:var(--text-primary,#fff);" data-i18n="cards.title">Card Styles</h4>
                <div class="settings-list" id="cardsList"></div>
                <button class="btn btn-outline btn-sm" id="btnAddCard"><i class="fas fa-plus"></i> <span data-i18n="actions.add_setting">Add</span></button>
                <div class="inline-form" id="cardsForm">
                    <div class="inline-form-header"><h4 data-i18n="cards.add_title">Add Card Style</h4></div>
                    <div class="form-row">
                        <div class="form-group"><label data-i18n="cards.name">Name</label><input type="text" id="crdName"></div>
                        <div class="form-group"><label data-i18n="cards.slug">Slug</label><input type="text" id="crdSlug"></div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label data-i18n="cards.type">Type</label>
                            <select id="crdType"><option value="product">product</option><option value="category">category</option><option value="vendor">vendor</option><option value="blog">blog</option><option value="feature">feature</option><option value="testimonial">testimonial</option><option value="other">other</option></select>
                        </div>
                        <div class="form-group"><label data-i18n="cards.bg_color">BG Color</label><input type="color" id="crdBgColor" value="#FFFFFF"></div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label data-i18n="cards.border_color">Border</label><input type="color" id="crdBorderColor" value="#E0E0E0"></div>
                        <div class="form-group"><label data-i18n="cards.border_width">Border W</label><input type="number" id="crdBorderWidth" value="1"></div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label data-i18n="cards.border_radius">Radius</label><input type="number" id="crdBorderRadius" value="8"></div>
                        <div class="form-group"><label data-i18n="cards.padding">Padding</label><input type="text" id="crdPadding" value="16px"></div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label data-i18n="cards.shadow">Shadow</label><input type="text" id="crdShadow" value="none"></div>
                        <div class="form-group"><label data-i18n="cards.hover">Hover</label>
                            <select id="crdHover"><option value="none">none</option><option value="lift">lift</option><option value="zoom">zoom</option><option value="shadow">shadow</option><option value="border">border</option><option value="brightness">brightness</option></select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label data-i18n="cards.text_align">Text Align</label>
                            <select id="crdTextAlign"><option value="left">left</option><option value="center">center</option><option value="right">right</option></select>
                        </div>
                        <div class="form-group"><label data-i18n="cards.aspect_ratio">Aspect Ratio</label><input type="text" id="crdAspectRatio" value="1:1"></div>
                        <div class="form-group"><label data-i18n="common.is_active">Active</label><select id="crdIsActive"><option value="1" data-i18n="common.yes">Yes</option><option value="0" data-i18n="common.no">No</option></select></div>
                    </div>
                    <input type="hidden" id="crdId">
                    <div class="form-actions"><button class="btn btn-primary btn-sm" id="btnSaveCard"><i class="fas fa-check"></i> <span data-i18n="actions.save">Save</span></button><button class="btn btn-outline btn-sm" id="btnCancelCard" data-i18n="actions.cancel">Cancel</button></div>
                </div>
            </div>

            <!-- ─── Tab: System Settings ─── -->
            <div class="tab-panel" id="tabSystem">
                <div class="settings-list" id="systemList"></div>
                <button class="btn btn-outline btn-sm" id="btnAddSystem"><i class="fas fa-plus"></i> <span data-i18n="actions.add_setting">Add Setting</span></button>
                <div class="inline-form" id="systemForm">
                    <div class="inline-form-header"><h4 data-i18n="system.add_title">Add System Setting</h4></div>
                    <div class="form-row">
                        <div class="form-group"><label data-i18n="system.key">Key</label><input type="text" id="sysKey"></div>
                        <div class="form-group"><label data-i18n="system.category">Category</label><input type="text" id="sysCategory" placeholder="general"></div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label data-i18n="system.value">Value</label><textarea id="sysValue" rows="2"></textarea></div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label data-i18n="system.type">Type</label>
                            <select id="sysType"><option value="text">text</option><option value="number">number</option><option value="boolean">boolean</option><option value="json">json</option><option value="file">file</option><option value="email">email</option></select>
                        </div>
                        <div class="form-group"><label data-i18n="system.description">Description</label><input type="text" id="sysDescription"></div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label data-i18n="system.is_public">Public</label><select id="sysIsPublic"><option value="0" data-i18n="common.no">No</option><option value="1" data-i18n="common.yes">Yes</option></select></div>
                        <div class="form-group"><label data-i18n="system.is_editable">Editable</label><select id="sysIsEditable"><option value="1" data-i18n="common.yes">Yes</option><option value="0" data-i18n="common.no">No</option></select></div>
                    </div>
                    <input type="hidden" id="sysId">
                    <div class="form-actions"><button class="btn btn-primary btn-sm" id="btnSaveSystem"><i class="fas fa-check"></i> <span data-i18n="actions.save">Save</span></button><button class="btn btn-outline btn-sm" id="btnCancelSystem" data-i18n="actions.cancel">Cancel</button></div>
                </div>
            </div>

            <!-- Main form save -->
            <div class="form-actions" id="mainFormActions">
                <button class="btn btn-primary" id="btnSave"><i class="fas fa-save"></i> <span data-i18n="actions.save">Save Theme</span></button>
                <button class="btn btn-secondary" id="btnCancelMain"><i class="fas fa-times"></i> <span data-i18n="actions.cancel">Cancel</span></button>
            </div>
        </div>
    </div>

</div>
</div>

<?php if ($isFragment): ?>
<script>
(function poll() {
    var attempts = 0, maxAttempts = 50;
    var timer = setInterval(function() {
        attempts++;
        if (window.ThemesSystem && typeof window.ThemesSystem.init === 'function') {
            clearInterval(timer);
            window.ThemesSystem.init();
        } else if (attempts >= maxAttempts) {
            clearInterval(timer);
        }
    }, 100);
})();
</script>
<?php else: ?>
<script src="/admin/assets/js/themes-system.js"></script>
<script>document.addEventListener('DOMContentLoaded', function() { if (window.ThemesSystem) window.ThemesSystem.init(); });</script>
<?php
    if (!$isFragment) require_once __DIR__ . '/../footer.php';
endif;
?>
