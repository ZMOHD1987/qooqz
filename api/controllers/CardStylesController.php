<?php
// api/controllers/CardStylesController.php
require_once __DIR__ . '/../controllers/GenericSettingsController.php';
require_once __DIR__ . '/../helpers/color_utils.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/utils.php';
require_once __DIR__ . '/../middleware/auth.php';

$GLOBALS['CARD_ALLOWED'] = [
    'theme_id','name','slug','card_type','background_color','border_color','border_width','border_radius',
    'shadow_style','padding','hover_effect','text_align','image_aspect_ratio','is_active','sort_order'
];

function card_controller(array $container): GenericSettingsController {
    return new GenericSettingsController($container['db'] ?? null, 'card_styles', $GLOBALS['CARD_ALLOWED']);
}

function CardStyles_index($container, $theme_id): void {
    card_controller($container)->index($container, (int)$theme_id);
}

function CardStyles_store($container, $theme_id): void {
    require_auth($container);
    $d = get_json_input();
    if (isset($d['background_color'])) {
        $nv = normalize_color($d['background_color']);
        if ($nv === null) json_error('background_color invalid', 400);
        $d['background_color'] = $nv;
    }
    if (isset($d['border_color'])) {
        $nv = normalize_color($d['border_color']);
        if ($nv === null) json_error('border_color invalid', 400);
        $d['border_color'] = $nv;
    }
    $d['theme_id'] = (int)$theme_id;
    card_controller($container)->store($container, (int)$theme_id, $d);
}

function CardStyles_update($container, $id): void {
    require_auth($container);
    $d = get_json_input();
    foreach (['background_color','border_color'] as $f) {
        if (isset($d[$f])) {
            $nv = normalize_color($d[$f]);
            if ($nv === null) json_error("$f invalid", 400);
            $d[$f] = $nv;
        }
    }
    card_controller($container)->update($container, (int)$id, $d);
}

function CardStyles_delete($container, $id): void {
    require_auth($container);
    card_controller($container)->delete($container, (int)$id);
}

function CardStyles_bulk($container, $theme_id): void {
    require_auth($container);
    $payload = get_json_input();
    $items = $payload['items'] ?? ($payload ?? []);
    if (!is_array($items)) json_error('items array required', 400);
    foreach ($items as $i => $it) {
        foreach (['background_color','border_color'] as $f) {
            if (isset($it[$f])) {
                $nv = normalize_color($it[$f]);
                if ($nv === null) json_error("$f invalid at index $i", 400);
                $items[$i][$f] = $nv;
            }
        }
        $items[$i]['theme_id'] = (int)$theme_id;
    }
    card_controller($container)->bulkUpdate($container, (int)$theme_id, $items);
}

function CardStyles_export($container, $theme_id): void {
    card_controller($container)->export($container, (int)$theme_id);
}

function CardStyles_import($container, $theme_id): void {
    require_auth($container);
    $payload = get_json_input();
    $items = $payload['items'] ?? null;
    $clear = !empty($payload['clear_existing']);
    if (!is_array($items)) json_error('items array required', 400);
    foreach ($items as $i => $it) {
        foreach (['background_color','border_color'] as $f) {
            if (isset($it[$f])) {
                $nv = normalize_color($it[$f]);
                if ($nv === null) json_error("$f invalid at index $i", 400);
                $items[$i][$f] = $nv;
            }
        }
        $items[$i]['theme_id'] = (int)$theme_id;
    }
    card_controller($container)->import($container, (int)$theme_id, $items, $clear);
}