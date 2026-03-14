<?php
// htdocs/frontend/partials/header.php
// Small server-side partial to render site header using active theme settings.
// It fetches a small subset of design settings (colors/fonts/button styles) and applies inline styles.
// Include this partial in your frontend templates where appropriate.

function api_request_local($path) {
    $url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/api' . $path;
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 6);
    $res = curl_exec($ch);
    curl_close($ch);
    $json = json_decode($res, true);
    return $json;
}

// Try to locate active theme
$themes = api_request_local('/themes');
$activeTheme = null;
if (!empty($themes['themes'])) {
    foreach ($themes['themes'] as $t) if (!empty($t['is_default'])) { $activeTheme = $t; break; }
    if (!$activeTheme) $activeTheme = $themes['themes'][0] ?? null;
}

$primary = '#2d8cf0';
$background = '#ffffff';
$headingFont = 'inherit';
$bodyFont = 'inherit';

if ($activeTheme) {
    $settings = api_request_local('/themes/' . $activeTheme['id'] . '/design-settings');
    if (!empty($settings['colors'])) {
        foreach ($settings['colors'] as $c) {
            if (!empty($c['category']) && $c['category'] === 'primary') $primary = $c['color_value'];
            if (!empty($c['category']) && $c['category'] === 'background') $background = $c['color_value'];
        }
    }
    if (!empty($settings['fonts'])) {
        foreach ($settings['fonts'] as $f) {
            if (!empty($f['category']) && $f['category'] === 'heading') $headingFont = $f['font_family'];
            if (!empty($f['category']) && $f['category'] === 'body') $bodyFont = $f['font_family'];
        }
    }
}

?>
<header style="background: <?php echo htmlspecialchars($primary); ?>; color: #fff; padding: 14px 20px; font-family: <?php echo htmlspecialchars($headingFont); ?>;">
    <div style="max-width:1100px;margin:auto;display:flex;align-items:center;justify-content:space-between">
        <div>
            <a href="/" style="color:#fff;text-decoration:none;font-weight:700;font-size:1.2em;">موقعي</a>
        </div>
        <nav style="font-family: <?php echo htmlspecialchars($bodyFont); ?>;">
            <a href="/frontend/products.php" style="color:#fff;margin-left:14px;text-decoration:none">المنتجات</a>
            <a href="/frontend/categories.php" style="color:#fff;margin-left:14px;text-decoration:none">التصنيفات</a>
            <a href="/frontend/cart.php" style="color:#fff;margin-left:14px;text-decoration:none">سلة</a>
        </nav>
    </div>
</header>