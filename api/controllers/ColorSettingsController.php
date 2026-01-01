<?php
//api/controllers/ColorSettingsController.php
// Procedural wrappers used by routes.php — these reuse GenericSettingsController
// Functions: ColorSettings_index, ColorSettings_store, ColorSettings_update, ColorSettings_delete, ColorSettings_bulk, ColorSettings_export, ColorSettings_import

require_once __DIR__ . '/../controllers/GenericSettingsController.php';
require_once __DIR__ . '/../helpers/color_utils.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/utils.php';
require_once __DIR__ . '/../middleware/auth.php';

// Fields allowed for color_settings table (match your DB columns)
$GLOBALS['COLOR_ALLOWED'] = ['theme_id','setting_key','setting_name','color_value','category','other','is_active','sort_order'];

/**
 * Get a controller instance for colors
 * @param array $container
 * @return GenericSettingsController
 */
function color_controller(array $container): GenericSettingsController {
    $db = $container['db'] ?? null;
    return new GenericSettingsController($db, 'color_settings', $GLOBALS['COLOR_ALLOWED']);
}

function ColorSettings_index($container, $theme_id): void {
    $c = color_controller($container);
    $c->index($container, (int)$theme_id);
}

function ColorSettings_store($container, $theme_id): void {
    require_auth($container);
    $data = get_json_input();
    if (!isset($data['color_value'])) json_error('color_value required', 400);
    $nv = normalize_color($data['color_value']);
    if ($nv === null) json_error('Invalid color_value', 400);
    $data['color_value'] = $nv;
    $c = color_controller($container);
    $c->store($container, (int)$theme_id, $data);
}

function ColorSettings_update($container, $id): void {
    require_auth($container);
    $data = get_json_input();
    if (isset($data['color_value'])) {
        $nv = normalize_color($data['color_value']);
        if ($nv === null) json_error('Invalid color_value', 400);
        $data['color_value'] = $nv;
    }
    $c = color_controller($container);
    $c->update($container, (int)$id, $data);
}

function ColorSettings_delete($container, $id): void {
    require_auth($container);
    $c = color_controller($container);
    $c->delete($container, (int)$id);
}

/**
 * Bulk update colors for theme via POST /api/themes/{id}/colors/bulk
 * Payload: { "items": [ { "id": 1, "setting_key":"...", "color_value":"#FFF", ... }, ... ] }
 */
function ColorSettings_bulk($container, $theme_id): void {
    require_auth($container);
    $payload = get_json_input();
    $items = [];
    if (isset($payload['items']) && is_array($payload['items'])) {
        $items = $payload['items'];
    } elseif (is_array($payload) && !empty($payload)) {
        // accept raw array as items
        $items = $payload;
    } else {
        json_error('No items provided', 400);
    }

    // normalize colors and validate minimal fields
    foreach ($items as $i => $it) {
        if (isset($it['color_value'])) {
            $nv = normalize_color($it['color_value']);
            if ($nv === null) json_error("Invalid color_value at index $i", 400);
            $items[$i]['color_value'] = $nv;
        } else {
            json_error("color_value required for item at index $i", 400);
        }
        // enforce theme_id
        $items[$i]['theme_id'] = (int)$theme_id;
    }

    $c = color_controller($container);
    $c->bulkUpdate($container, (int)$theme_id, $items);
}

/** Export colors for a theme: GET /api/themes/{id}/colors/export */
function ColorSettings_export($container, $theme_id): void {
    $c = color_controller($container);
    $c->export($container, (int)$theme_id);
}

/**
 * Import colors for theme: POST /api/themes/{id}/colors/import
 * Payload: { "items": [...], "clear_existing": true|false }
 */
function ColorSettings_import($container, $theme_id): void {
    require_auth($container);
    $payload = get_json_input();
    $items = $payload['items'] ?? null;
    $clear = !empty($payload['clear_existing']);
    if (!is_array($items)) json_error('items array required', 400);

    // normalize each color_value
    foreach ($items as $i => $it) {
        if (isset($it['color_value'])) {
            $nv = normalize_color($it['color_value']);
            if ($nv === null) json_error("Invalid color_value at index $i", 400);
            $items[$i]['color_value'] = $nv;
        } else {
            json_error("color_value required for item at index $i", 400);
        }
        $items[$i]['theme_id'] = (int)$theme_id;
    }

    $c = color_controller($container);
    $c->import($container, (int)$theme_id, $items, $clear);
}