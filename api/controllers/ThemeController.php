<?php
// htdocs/api/controllers/ThemeController.php
// Procedural handlers referenced by routes.php:
// ThemeController_index, ThemeController_store, ThemeController_show, ThemeController_update,
// ThemeController_delete, ThemeController_activate, ThemeController_duplicate

require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/utils.php';
require_once __DIR__ . '/../repositories/ThemeRepository.php';
require_once __DIR__ . '/../middleware/auth.php';

/**
 * GET /api/themes
 * Query params: page, per_page, q
 */
function ThemeController_index($container): void {
    $page = max(1, (int)($_GET['page'] ?? 1));
    $per = min(100, max(1, (int)($_GET['per_page'] ?? 20)));
    $q = isset($_GET['q']) ? trim((string)$_GET['q']) : null;

    $repo = new ThemeRepository($container['db'] ?? null);
    $result = $repo->paginate($page, $per, $q);
    json_response($result);
}

/**
 * POST /api/themes
 */
function ThemeController_store($container): void {
    require_auth($container);
    $data = get_json_input();
    if (empty($data['name']) || empty($data['slug'])) json_error('name and slug are required', 400);

    $repo = new ThemeRepository($container['db'] ?? null);
    try {
        $created = $repo->create($data);
        json_response($created, 201);
    } catch (Throwable $e) {
        json_error($e->getMessage(), 500);
    }
}

/**
 * GET /api/themes/{id}
 */
function ThemeController_show($container, $id): void {
    $repo = new ThemeRepository($container['db'] ?? null);
    $t = $repo->find((int)$id);
    if (!$t) json_error('Not found', 404);
    json_response($t);
}

/**
 * PUT /api/themes/{id}
 */
function ThemeController_update($container, $id): void {
    require_auth($container);
    $data = get_json_input();
    $repo = new ThemeRepository($container['db'] ?? null);
    try {
        $updated = $repo->update((int)$id, $data);
        json_response($updated);
    } catch (Throwable $e) {
        json_error($e->getMessage(), 500);
    }
}

/**
 * DELETE /api/themes/{id}
 */
function ThemeController_delete($container, $id): void {
    require_auth($container);
    $repo = new ThemeRepository($container['db'] ?? null);
    try {
        $repo->delete((int)$id);
        json_response(null, 204);
    } catch (Throwable $e) {
        json_error($e->getMessage(), 500);
    }
}

/**
 * POST /api/themes/{id}/activate
 */
function ThemeController_activate($container, $id): void {
    require_auth($container);
    $repo = new ThemeRepository($container['db'] ?? null);
    try {
        $repo->activate((int)$id);
        json_response(['success' => true]);
    } catch (Throwable $e) {
        json_error($e->getMessage(), 500);
    }
}

/**
 * POST /api/themes/{id}/duplicate
 * Payload: { "name": "Optional new name" }
 */
function ThemeController_duplicate($container, $id): void {
    require_auth($container);
    $payload = get_json_input();
    $newName = isset($payload['name']) ? trim((string)$payload['name']) : null;
    $repo = new ThemeRepository($container['db'] ?? null);
    try {
        $new = $repo->duplicate((int)$id, $newName);
        json_response($new, 201);
    } catch (Throwable $e) {
        json_error($e->getMessage(), 500);
    }
}