<?php
// api/controllers/GenericSettingsController.php
// General controller class (stateless) — works with GenericRepository and field definitions

require_once __DIR__ . '/../repositories/GenericRepository.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/utils.php';

class GenericSettingsController {
    private $repo;
    private $allowedFields;
    private $table;

    /**
     * @param mysqli $db
     * @param string $table
     * @param array $allowedFields
     */
    public function __construct($db, string $table, array $allowedFields) {
        $this->repo = new GenericRepository($db, $table);
        $this->allowedFields = $allowedFields;
        $this->table = $table;
    }

    public function index($container, int $themeId) {
        $rows = $this->repo->listByTheme($themeId);
        json_response($rows);
    }

    public function store($container, int $themeId, array $data) {
        $data['theme_id'] = $themeId;
        $created = $this->repo->create($data, $this->allowedFields);
        json_response($created, 201);
    }

    public function update($container, int $id, array $data) {
        $updated = $this->repo->update($id, $data, $this->allowedFields);
        json_response($updated);
    }

    public function delete($container, int $id) {
        $this->repo->delete($id);
        json_response(null, 204);
    }

    public function bulkUpdate($container, int $themeId, array $items) {
        $result = $this->repo->bulkUpsertByTheme($themeId, $items, $this->allowedFields);
        json_response($result);
    }

    public function export($container, int $themeId) {
        $data = $this->repo->exportByTheme($themeId);
        json_response($data);
    }

    public function import($container, int $themeId, array $items, bool $clearExisting = false) {
        $created = $this->repo->importForTheme($themeId, $items, $this->allowedFields, $clearExisting);
        json_response($created);
    }
}