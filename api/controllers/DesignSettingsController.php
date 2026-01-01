<?php
// api/controllers/DesignSettingsController.php
require_once __DIR__ . '/../controllers/GenericSettingsController.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/utils.php';
require_once __DIR__ . '/../middleware/auth.php';

$GLOBALS['DESIGN_ALLOWED'] = [
    'theme_id','setting_key','setting_name','setting_value','setting_type','category','other','is_active','sort_order'
];

function design_controller(array $container): GenericSettingsController {
    return new GenericSettingsController($container['db'] ?? null, 'design_settings', $GLOBALS['DESIGN_ALLOWED']);
}

function DesignSettings_index($container, $theme_id): void {
    design_controller($container)->index($container, (int)$theme_id);
}

function DesignSettings_store($container, $theme_id): void {
    require_auth($container);
    $d = get_json_input(); $d['theme_id'] = (int)$theme_id;
    design_controller($container)->store($container, (int)$theme_id, $d);
}

function DesignSettings_update($container, $id): void {
    require_auth($container);
    design_controller($container)->update($container, (int)$id, get_json_input());
}

function DesignSettings_delete($container, $id): void {
    require_auth($container);
    design_controller($container)->delete($container, (int)$id);
}

function DesignSettings_bulk($container, $theme_id): void {
    require_auth($container);
    $payload = get_json_input();
    $items = $payload['items'] ?? ($payload ?? []);
    if (!is_array($items)) json_error('items array required', 400);
    foreach ($items as $i => $it) $items[$i]['theme_id'] = (int)$theme_id;
    design_controller($container)->bulkUpdate($container, (int)$theme_id, $items);
}
function DesignSettings_export($container, $theme_id): void { design_controller($container)->export($container, (int)$theme_id); }
function DesignSettings_import($container, $theme_id): void {
    require_auth($container);
    $payload = get_json_input(); $items = $payload['items'] ?? null; $clear = !empty($payload['clear_existing']);
    if (!is_array($items)) json_error('items array required', 400);
    foreach ($items as $i => $it) $items[$i]['theme_id'] = (int)$theme_id;
    design_controller($container)->import($container, (int)$theme_id, $items, $clear);
}