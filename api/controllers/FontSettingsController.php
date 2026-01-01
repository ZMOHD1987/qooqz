<?php
// api/controllers/FontSettingsController.php
require_once __DIR__ . '/../controllers/GenericSettingsController.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/utils.php';
require_once __DIR__ . '/../middleware/auth.php';

$GLOBALS['FONT_ALLOWED'] = [
    'theme_id','setting_key','setting_name','font_family','font_size','font_weight','line_height','category','other','is_active','sort_order'
];

function font_controller(array $container): GenericSettingsController {
    return new GenericSettingsController($container['db'] ?? null, 'font_settings', $GLOBALS['FONT_ALLOWED']);
}

function FontSettings_index($container, $theme_id): void {
    font_controller($container)->index($container, (int)$theme_id);
}

function FontSettings_store($container, $theme_id): void {
    require_auth($container);
    $d = get_json_input(); $d['theme_id'] = (int)$theme_id;
    font_controller($container)->store($container, (int)$theme_id, $d);
}

function FontSettings_update($container, $id): void {
    require_auth($container);
    font_controller($container)->update($container, (int)$id, get_json_input());
}

function FontSettings_delete($container, $id): void {
    require_auth($container);
    font_controller($container)->delete($container, (int)$id);
}

function FontSettings_bulk($container, $theme_id): void {
    require_auth($container);
    $payload = get_json_input();
    $items = $payload['items'] ?? ($payload ?? []);
    if (!is_array($items)) json_error('items array required', 400);
    foreach ($items as $i => $it) $items[$i]['theme_id'] = (int)$theme_id;
    font_controller($container)->bulkUpdate($container, (int)$theme_id, $items);
}
function FontSettings_export($container, $theme_id): void { font_controller($container)->export($container, (int)$theme_id); }
function FontSettings_import($container, $theme_id): void {
    require_auth($container);
    $payload = get_json_input(); $items = $payload['items'] ?? null; $clear = !empty($payload['clear_existing']);
    if (!is_array($items)) json_error('items array required', 400);
    foreach ($items as $i => $it) $items[$i]['theme_id'] = (int)$theme_id;
    font_controller($container)->import($container, (int)$theme_id, $items, $clear);
}