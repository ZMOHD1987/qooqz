<?php
// admin/includes/theme_injector.php
// Requires: $ADMIN_UI_PAYLOAD already built by bootstrap_admin_ui.php
// Place this file include AFTER require_once $adminBootstrap in header.php and BEFORE linking admin.css

if (!isset($ADMIN_UI_PAYLOAD) || !is_array($ADMIN_UI_PAYLOAD)) {
    return;
}

$theme = $ADMIN_UI_PAYLOAD['theme'] ?? [];
$colorsRaw = $theme['colors'] ?? [];
$designsRaw = $theme['designs'] ?? [];
$fontsRaw = $theme['fonts'] ?? [];

/**
 * Helpers to normalize different payload shapes:
 * - colors may be an associative map ['primary_color'=>'#fff'] OR array of rows with setting_key/color_value
 * - fonts may be an array of rows with font_family/font_url or an associative map
 */
$colors = [];
// normalize colors
if (is_array($colorsRaw)) {
    // detect list-of-rows (has numeric keys or items with 'setting_key')
    $isRows = false;
    foreach ($colorsRaw as $k => $v) {
        if (is_int($k)) { $isRows = true; break; }
        if (is_array($v) && isset($v['setting_key'])) { $isRows = true; break; }
    }
    if ($isRows) {
        foreach ($colorsRaw as $row) {
            if (!is_array($row)) continue;
            $key = $row['setting_key'] ?? ($row['key'] ?? null);
            $val = $row['color_value'] ?? ($row['value'] ?? null);
            if ($key && $val) $colors[(string)$key] = (string)$val;
        }
    } else {
        // assume associative
        foreach ($colorsRaw as $k => $v) {
            $colors[(string)$k] = (string)$v;
        }
    }
}

// normalize designs (should already be associative mapping by your bootstrap)
$designs = is_array($designsRaw) ? $designsRaw : [];

// normalize fonts -> array of ['font_family','font_url','category']
$fonts = [];
if (is_array($fontsRaw)) {
    foreach ($fontsRaw as $f) {
        if (is_array($f)) {
            // possible column names: font_family, font_url, setting_key etc.
            $font_family = $f['font_family'] ?? $f['value'] ?? $f['setting_key'] ?? null;
            $font_url = $f['font_url'] ?? $f['url'] ?? null;
            $category = $f['category'] ?? ($f['type'] ?? 'body');
            if ($font_family) $fonts[] = ['font_family' => (string)$font_family, 'font_url' => $font_url ? (string)$font_url : null, 'category' => $category];
        } elseif (is_string($f)) {
            $fonts[] = ['font_family' => $f, 'font_url' => null, 'category' => 'body'];
        }
    }
}

// Map known color keys to CSS variables matching the names used in CSS files
$map = [
    'primary_color'        => '--primary-color',
    'primary_hover'        => '--primary-hover',
    'secondary_color'      => '--secondary-color',
    'accent_color'         => '--accent-color',
    'background_main'      => '--background-main',
    'background_secondary' => '--background-secondary',
    'text_primary'         => '--text-primary',
    'text_secondary'       => '--text-secondary',
    'border_color'         => '--border-color',
    'success_color'        => '--success-color',
    'danger_color'         => '--danger-color',
    'error_color'          => '--danger-color',  // alias: some themes use error_color
    'warning_color'        => '--warning-color',
    'info_color'           => '--info-color',
    'card_bg'              => '--card-bg',
    'input_bg'             => '--input-bg',
    'thead_bg'             => '--thead-bg',
];

// Build cssVars
$cssVars = [];

// Apply mapped colors
foreach ($colors as $k => $v) {
    $key = strtolower((string)$k);
    if (isset($map[$key])) {
        $cssVars[$map[$key]] = $v;
    } else {
        $safe = preg_replace('/[^a-z0-9_-]/i', '-', $key);
        $cssVars['--theme-' . $safe] = $v;
    }
}

// Designs -> export as --theme-<key>, plus friendly aliases
foreach ($designs as $k => $v) {
    $safe = preg_replace('/[^a-z0-9_-]/i', '-', (string)$k);
    $val = $v;
    if (is_numeric($v) && strpos((string)$v, '.') === false) $val = (int)$v . 'px';
    $cssVars['--theme-' . $safe] = $val;
    if ($safe === 'header_height') $cssVars['--header-height'] = $val;
    if ($safe === 'container_width') $cssVars['--container-width'] = $val;
    if ($safe === 'logo_url') $cssVars['--logo-url'] = $val;
}

// Extract font families from fonts array (first match per category wins)
foreach ($fonts as $f) {
    $cat = strtolower($f['category'] ?? 'body');
    if (in_array($cat, ['body', 'main', 'primary', 'default']) && !isset($cssVars['--body-font-family'])) {
        $cssVars['--body-font-family'] = $f['font_family'];
    } elseif (in_array($cat, ['heading', 'title', 'header']) && !isset($cssVars['--heading-font-family'])) {
        $cssVars['--heading-font-family'] = $f['font_family'];
    }
}

// Ensure sensible defaults for critical variables
$defaults = [
    '--primary-color'        => $cssVars['--primary-color'] ?? '#3B82F6',
    '--primary-hover'        => $cssVars['--primary-hover'] ?? ($cssVars['--primary-color'] ?? '#2563EB'),
    '--secondary-color'      => $cssVars['--secondary-color'] ?? '#64748b',
    '--accent-color'         => $cssVars['--accent-color'] ?? '#F59E0B',
    '--background-main'      => $cssVars['--background-main'] ?? '#0a0f1e',
    '--background-secondary' => $cssVars['--background-secondary'] ?? '#0f1724',
    '--text-primary'         => $cssVars['--text-primary'] ?? '#ffffff',
    '--text-secondary'       => $cssVars['--text-secondary'] ?? '#94a3b8',
    '--border-color'         => $cssVars['--border-color'] ?? '#263044',
    '--danger-color'         => $cssVars['--danger-color'] ?? '#ef4444',
    '--success-color'        => $cssVars['--success-color'] ?? '#22c55e',
    '--warning-color'        => $cssVars['--warning-color'] ?? '#f59e0b',
    '--info-color'           => $cssVars['--info-color'] ?? '#3b82f6',
    '--card-bg'              => $cssVars['--card-bg'] ?? '#081127',
    '--input-bg'             => $cssVars['--input-bg'] ?? '#0b1220',
    '--thead-bg'             => $cssVars['--thead-bg'] ?? '#061021',
    '--body-font-family'     => $cssVars['--body-font-family'] ?? $fonts[0]['font_family'] ?? '"Inter", system-ui, -apple-system, "Segoe UI", Roboto, Arial',
    '--heading-font-family'  => $cssVars['--heading-font-family'] ?? $cssVars['--body-font-family'] ?? $fonts[0]['font_family'] ?? '"Inter", system-ui, -apple-system, "Segoe UI", Roboto, Arial',
    '--font-size'            => $cssVars['--font-size'] ?? '14px',
    '--header-height'        => $cssVars['--header-height'] ?? '64px'
];
foreach ($defaults as $k => $v) {
    if (!isset($cssVars[$k])) $cssVars[$k] = $v;
}

// Build font links list (dedup)
$fontLinks = [];
foreach ($fonts as $f) {
    if (!empty($f['font_url'])) $fontLinks[] = $f['font_url'];
    else {
        // attempt to auto-generate google fonts link from first family token
        if (!empty($f['font_family'])) {
            $family = trim(explode(',', $f['font_family'])[0], " \"'");
            if ($family && preg_match('/^[A-Za-z0-9 \-]+$/', $family)) {
                $fontLinks[] = 'https://fonts.googleapis.com/css2?family=' . str_replace(' ', '+', rawurlencode($family)) . '&display=swap';
            }
        }
    }
}
$fontLinks = array_values(array_unique($fontLinks));

// Output font links early
foreach ($fontLinks as $fl) {
    echo '<link rel="stylesheet" href="' . htmlspecialchars($fl, ENT_QUOTES | ENT_SUBSTITUTE) . '">' . PHP_EOL;
}

// Output :root variables block (id=theme-vars)
echo '<style id="theme-vars">:root' . PHP_EOL . '{' . PHP_EOL;
foreach ($cssVars as $vn => $vv) {
    $name = htmlspecialchars($vn, ENT_QUOTES | ENT_SUBSTITUTE);
    $value = htmlspecialchars($vv, ENT_QUOTES | ENT_SUBSTITUTE);
    echo "  {$name}: {$value};" . PHP_EOL;
}
echo '}' . PHP_EOL . '</style>' . PHP_EOL;

// Add theme slug as html class for extra hooks
if (!empty($theme['slug'])) {
    $slug = preg_replace('/[^a-z0-9_-]/i','-', (string)$theme['slug']);
    echo '<script>document.documentElement.classList.add("theme-' . addslashes($slug) . '");</script>' . PHP_EOL;
}