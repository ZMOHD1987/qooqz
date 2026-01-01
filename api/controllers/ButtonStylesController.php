<?php
// api/controllers/ButtonStylesController.php
require_once __DIR__ . '/../controllers/GenericSettingsController.php';
require_once __DIR__ . '/../helpers/color_utils.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/utils.php';
require_once __DIR__ . '/../middleware/auth.php';

// allowed fields for button_styles table
$GLOBALS['BUTTON_ALLOWED'] = [
    'theme_id','name','slug','button_type','background_color','text_color','border_color',
    'border_width','border_radius','padding','font_size','font_weight',
    'hover_background_color','hover_text_color','hover_border_color','is_active','sort_order'
];

function button_controller(array $container): GenericSettingsController {
    $db = $container['db'] ?? null;
    return new GenericSettingsController($db, 'button_styles', $GLOBALS['BUTTON_ALLOWED']);
}

function ButtonStyles_index($container, $theme_id): void {
    $c = button_controller($container);
    $c->index($container, (int)$theme_id);
}

function ButtonStyles_store($container, $theme_id): void {
    require_auth($container);
    $data = get_json_input();
    // normalize color fields if present
    foreach (['background_color','text_color','border_color','hover_background_color','hover_text_color','hover_border_color'] as $f) {
        if (isset($data[$f])) {
            $nv = normalize_color($data[$f]);
            if ($nv === null) json_error("$f invalid", 400);
            $data[$f] = $nv;
        }
    }
    $c = button_controller($container);
    $c->store($container, (int)$theme_id, $data);
}

function ButtonStyles_update($container, $id): void {
    require_auth($container);
    $data = get_json_input();
    foreach (['background_color','text_color','border_color','hover_background_color','hover_text_color','hover_border_color'] as $f) {
        if (isset($data[$f])) {
            $nv = normalize_color($data[$f]);
            if ($nv === null) json_error("$f invalid", 400);
            $data[$f] = $nv;
        }
    }
    $c = button_controller($container);
    $c->update($container, (int)$id, $data);
}

function ButtonStyles_delete($container, $id): void {
    require_auth($container);
    $c = button_controller($container);
    $c->delete($container, (int)$id);
}

function ButtonStyles_bulk($container, $theme_id): void {
    require_auth($container);
    $payload = get_json_input();
    $items = $payload['items'] ?? ($payload ?? []);
    if (!is_array($items)) json_error('items array required', 400);
    foreach ($items as $i => $it) {
        foreach (['background_color','text_color','border_color','hover_background_color','hover_text_color','hover_border_color'] as $f) {
            if (isset($it[$f])) {
                $nv = normalize_color($it[$f]);
                if ($nv === null) json_error("$f invalid at index $i", 400);
                $items[$i][$f] = $nv;
            }
        }
        $items[$i]['theme_id'] = (int)$theme_id;
    }
    $c = button_controller($container);
    $c->bulkUpdate($container, (int)$theme_id, $items);
}

function ButtonStyles_export($container, $theme_id): void {
    $c = button_controller($container);
    $c->export($container, (int)$theme_id);
}

function ButtonStyles_import($container, $theme_id): void {
    require_auth($container);
    $payload = get_json_input();
    $items = $payload['items'] ?? null;
    $clear = !empty($payload['clear_existing']);
    if (!is_array($items)) json_error('items array required', 400);
    // normalize colors
    foreach ($items as $i => $it) {
        foreach (['background_color','text_color','border_color','hover_background_color','hover_text_color','hover_border_color'] as $f) {
            if (isset($it[$f])) {
                $nv = normalize_color($it[$f]);
                if ($nv === null) json_error("$f invalid at index $i", 400);
                $items[$i][$f] = $nv;
            }
        }
        $items[$i]['theme_id'] = (int)$theme_id;
    }
    $c = button_controller($container);
    $c->import($container, (int)$theme_id, $items, $clear);
}