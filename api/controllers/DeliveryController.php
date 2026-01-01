<?php
declare(strict_types=1);

require_once __DIR__ . '/../models/DeliveryZone.php';

class DeliveryZoneController {
    private DeliveryZoneModel $model;

    public function __construct(mysqli $db) {
        $this->model = new DeliveryZoneModel($db);
    }

    private function user(): array {
        $u = auth_user();
        if (!$u) json_error('Unauthorized', 401);
        return $u;
    }

    public function listAction(): void {
        $u = $this->user();

        $filters = [
            'user_id' => $u['id'],
            'status' => $_GET['status'] ?? null
        ];

        $page = max(1, (int)($_GET['page'] ?? 1));
        $per  = min(100, max(5, (int)($_GET['per_page'] ?? 20)));

        $res = $this->model->search($filters, $page, $per);
        json_ok($res);
    }

    public function getAction(): void {
        $u = $this->user();
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) json_error('Invalid id');

        $row = $this->model->findById($id);
        if (!$row || $row['user_id'] != $u['id']) json_error('Not found', 404);

        $row['zone_value_json'] = json_decode($row['zone_value'], true);
        json_ok($row);
    }

    public function createAction(): void {
        $u = $this->user();

        $payload = [
            'user_id' => $u['id'],
            'zone_name' => $_POST['zone_name'] ?? '',
            'zone_type' => $_POST['zone_type'] ?? '',
            'zone_value' => json_encode($_POST['zone_value'] ?? []),
            'shipping_rate' => (float)($_POST['shipping_rate'] ?? 0),
            'free_shipping_threshold' => (float)($_POST['free_shipping_threshold'] ?? 0),
            'estimated_delivery_days' => (int)($_POST['estimated_delivery_days'] ?? 1),
            'status' => $_POST['status'] ?? 'active'
        ];

        $id = $this->model->create($payload);
        json_ok(['id' => $id], 201);
    }

    public function updateAction(): void {
        $u = $this->user();
        $id = (int)($_POST['id'] ?? 0);
        if (!$id) json_error('Invalid id');

        $row = $this->model->findById($id);
        if (!$row || $row['user_id'] != $u['id']) json_error('Forbidden', 403);

        $payload = array_merge($row, $_POST);
        $payload['zone_value'] = json_encode($payload['zone_value']);

        $this->model->update($id, $payload);
        json_ok(['updated' => true]);
    }

    public function deleteAction(): void {
        $u = $this->user();
        $id = (int)($_POST['id'] ?? 0);

        $row = $this->model->findById($id);
        if (!$row || $row['user_id'] != $u['id']) json_error('Forbidden', 403);

        $this->model->delete($id);
        json_ok(['deleted' => true]);
    }
}
