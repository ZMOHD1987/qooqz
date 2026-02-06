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

// Map known color keys to CSS variables (extend as needed)
$map = [
    'primary_color' => '--color-primary',
    'primary_hover' => '--color-primary-dark',
    'secondary_color' => '--color-secondary',
    'accent_color' => '--color-accent',
    'background_main' => '--color-bg',
    'background_secondary' => '--color-surface',
    'text_primary' => '--color-text',
    'text_secondary' => '--color-muted',
    'border_color' => '--color-border',
    'success_color' => '--color-success',
    'error_color' => '--color-error',
    'warning_color' => '--color-warning',
    'info_color' => '--color-info',
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

// Ensure sensible defaults for critical variables
$defaults = [
    '--color-primary' => $cssVars['--color-primary'] ?? '#3B82F6',
    '--color-primary-dark' => $cssVars['--color-primary-dark'] ?? ($cssVars['--color-primary'] ?? '#2563EB'),
    '--color-accent' => $cssVars['--color-accent'] ?? '#F59E0B',
    '--color-bg' => $cssVars['--color-bg'] ?? '#FFFFFF',
    '--color-surface' => $cssVars['--color-surface'] ?? ($cssVars['--color-bg'] ?? '#FFFFFF'),
    '--color-text' => $cssVars['--color-text'] ?? '#111827',
    '--color-muted' => $cssVars['--color-muted'] ?? '#6B7280',
    '--color-border' => $cssVars['--color-border'] ?? '#E5E7EB',
    '--font-family' => $cssVars['--font-family'] ?? $fonts[0]['font_family'] ?? '"Inter", system-ui, -apple-system, "Segoe UI", Roboto, Arial',
    '--font-size' => $cssVars['--font-size'] ?? '14px',
    '--header-height' => $cssVars['--header-height'] ?? '64px'
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