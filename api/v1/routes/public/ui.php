<?php
declare(strict_types=1);
/**
 * Public API sub-route: ui
 * Loaded by api/v1/routes/public.php dispatcher.
 * Variables available: $pdo, $pdoList, $pdoOne, $pdoCount,
 *   $first, $segments, $lang, $page, $per, $offset, $tenantId
 */

if ($first === 'ui') {
    $tid = $tenantId ?? 1;

    // Look up active theme_id for this tenant (mirrors AdminUiThemeLoader::getActiveThemeId)
    $uiThemeRow = $pdoOne('SELECT id FROM themes WHERE tenant_id = ? AND is_active = 1 LIMIT 1', [$tid]);
    if (!$uiThemeRow) {
        $uiThemeRow = $pdoOne('SELECT id FROM themes WHERE tenant_id = ? AND is_default = 1 LIMIT 1', [$tid]);
    }
    $uiThemeId  = $uiThemeRow ? (int)$uiThemeRow['id'] : null;
    $uiTidCond  = $uiThemeId ? ' AND (theme_id = ? OR theme_id IS NULL)' : '';
    $uiP = static function(array $base) use ($uiThemeId): array {
        return $uiThemeId ? array_merge($base, [$uiThemeId]) : $base;
    };

    $colors  = $pdoList(
        'SELECT setting_key AS `key`, color_value AS value, category FROM color_settings WHERE tenant_id = ? AND is_active = 1' . $uiTidCond . ' ORDER BY sort_order, id',
        $uiP([$tid])
    );
    $fonts   = $pdoList(
        'SELECT setting_key, font_family, font_size, font_weight, line_height, category FROM font_settings WHERE tenant_id = ? AND is_active = 1' . $uiTidCond . ' ORDER BY sort_order',
        $uiP([$tid])
    );
    $designs = $pdoList(
        'SELECT setting_key, setting_value, setting_type, category FROM design_settings WHERE tenant_id = ? AND is_active = 1' . $uiTidCond . ' ORDER BY sort_order',
        $uiP([$tid])
    );
    $buttons = $pdoList(
        'SELECT slug, button_type, background_color, text_color, border_color, border_width, border_radius, padding, font_size, font_weight, hover_background_color, hover_text_color FROM button_styles WHERE tenant_id = ? AND is_active = 1' . $uiTidCond . ' ORDER BY button_type',
        $uiP([$tid])
    );
    $cards = $pdoList(
        'SELECT slug, card_type, background_color, border_color, border_width, border_radius, shadow_style, padding FROM card_styles WHERE tenant_id = ? AND is_active = 1' . $uiTidCond . ' ORDER BY card_type',
        $uiP([$tid])
    );

    // Generate CSS string from all settings (mirrors AdminUiThemeLoader::generateCss)
    // Values are escaped to prevent CSS injection (</style> breakout protection)
    $esc = function(string $v): string { return str_replace('</style', '<\\/style', htmlspecialchars($v, ENT_QUOTES, 'UTF-8')); };
    $css = ":root {\n";
    foreach ($colors as $c) {
        if (!empty($c['key']) && !empty($c['value'])) {
            $css .= '  --' . preg_replace('/[^a-z0-9_\-]/', '-', strtolower((string)$c['key'])) . ': ' . $esc((string)$c['value']) . ";\n";
        }
    }
    // CSS variable aliases: map DB setting_key names (underscore) to --pub-* / --color-*
    // so public.css and variables.css receive the correct DB values directly.
    $uiAliases = [
        'primary_color'        => ['color-primary',  'pub-primary'],
        'secondary_color'      => ['color-secondary', 'pub-secondary'],
        'accent_color'         => ['color-accent',    'pub-accent'],
        'background_main'      => ['pub-bg'],
        'background_secondary' => ['pub-surface'],
        'text_primary'         => ['pub-text'],
        'text_secondary'       => ['pub-muted'],
        'border_color'         => ['pub-border'],
    ];
    $colorKeyVal = [];
    foreach ($colors as $c) {
        if (!empty($c['key'])) {
            $colorKeyVal[$c['key']] = $c['value'] ?? '';
        }
    }
    foreach ($uiAliases as $srcKey => $aliases) {
        if (empty($colorKeyVal[$srcKey])) continue;
        $val = $esc($colorKeyVal[$srcKey]);
        foreach ($aliases as $alias) {
            $css .= '  --' . $alias . ': ' . $val . ";\n";
        }
    }
    foreach ($fonts as $f) {
        if (!empty($f['setting_key'])) {
            $sk = preg_replace('/[^a-z0-9_\-]/', '-', strtolower((string)$f['setting_key']));
            if (!empty($f['font_family'])) $css .= '  --' . $sk . '-family: ' . $esc((string)$f['font_family']) . ";\n";
            if (!empty($f['font_size']))   $css .= '  --' . $sk . '-size: '   . $esc((string)$f['font_size'])   . ";\n";
            if (!empty($f['font_weight'])) $css .= '  --' . $sk . '-weight: ' . $esc((string)$f['font_weight']) . ";\n";
        }
    }
    foreach ($designs as $d) {
        if (!empty($d['setting_key']) && !empty($d['setting_value'])) {
            $css .= '  --' . preg_replace('/[^a-z0-9_\-]/', '-', strtolower((string)$d['setting_key'])) . ': ' . $esc((string)$d['setting_value']) . ";\n";
        }
    }
    $css .= "}\n";
    foreach ($buttons as $b) {
        if (empty($b['slug'])) continue;
        $css .= '.btn-' . preg_replace('/[^a-z0-9_\-]/', '-', (string)$b['slug']) . " {\n";
        if (!empty($b['background_color'])) $css .= '  background-color: ' . $esc((string)$b['background_color']) . ";\n";
        if (!empty($b['text_color']))       $css .= '  color: '            . $esc((string)$b['text_color'])       . ";\n";
        if (!empty($b['border_color']))     $css .= '  border: '           . (int)$b['border_width'] . 'px solid ' . $esc((string)$b['border_color']) . ";\n";
        if (isset($b['border_radius']))     $css .= '  border-radius: '    . (int)$b['border_radius'] . "px;\n";
        if (!empty($b['padding']))          $css .= '  padding: '          . $esc((string)$b['padding'])          . ";\n";
        if (!empty($b['font_size']))        $css .= '  font-size: '        . $esc((string)$b['font_size'])        . ";\n";
        if (!empty($b['font_weight']))      $css .= '  font-weight: '      . $esc((string)$b['font_weight'])      . ";\n";
        $css .= "}\n";
    }
    foreach ($cards as $c) {
        if (empty($c['slug'])) continue;
        $css .= '.card-' . preg_replace('/[^a-z0-9_\-]/', '-', (string)$c['slug']) . " {\n";
        if (!empty($c['background_color'])) $css .= '  background-color: ' . $esc((string)$c['background_color']) . ";\n";
        if (!empty($c['border_color']))     $css .= '  border: '           . (int)$c['border_width'] . 'px solid ' . $esc((string)$c['border_color']) . ";\n";
        if (isset($c['border_radius']))     $css .= '  border-radius: '    . (int)$c['border_radius'] . "px;\n";
        if (!empty($c['shadow_style']))     $css .= '  box-shadow: '       . $esc((string)$c['shadow_style'])      . ";\n";
        if (!empty($c['padding']))          $css .= '  padding: '          . $esc((string)$c['padding'])           . ";\n";
        $css .= "}\n";
    }

    ResponseFormatter::success([
        'ok'           => true,
        'ui'           => $GLOBALS['PUBLIC_UI'] ?? [],
        'colors'       => $colors,
        'fonts'        => $fonts,
        'design'       => $designs,
        'buttons'      => $buttons,
        'cards'        => $cards,
        'generated_css'=> $css,
    ]);
    exit;
}

/* -------------------------------------------------------
 * Route: Products
 * GET /api/public/products[/{id}]
 * ----------------------------------------------------- */
