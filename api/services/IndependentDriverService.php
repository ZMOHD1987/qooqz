<?php
// api/services/IndependentDriverService.php
// Business logic for IndependentDriver - thin service layer
declare(strict_types=1);

require_once __DIR__ . '/../models/IndependentDriver.php';

class IndependentDriverService
{
    private IndependentDriverModel $model;
    private mysqli $db;

    public function __construct(mysqli $db)
    {
        $this->db = $db;
        $this->model = new IndependentDriverModel($db);
    }

    // Example higher-level operation: assign driver to an order (placeholder)
    public function assignToOrder(int $driverId, int $orderId): bool
    {
        // Placeholder: you likely have an orders table and logic to validate
        // Minimal implementation: record assignment in a join table if exists
        try {
            $stmt = $this->db->prepare("INSERT INTO driver_order_assignments (driver_id, order_id, assigned_at) VALUES (?, ?, NOW())");
            if (!$stmt) return false;
            $stmt->bind_param('ii', $driverId, $orderId);
            $ok = $stmt->execute();
            $stmt->close();
            return (bool)$ok;
        } catch (Throwable $e) {
            return false;
        }
    }

    // expose model operations (convenience)
    public function search(array $filters = [], int $page = 1, int $per = 20): array { return $this->model->search($filters, $page, $per); }
    public function findById(int $id): ?array { return $this->model->findById($id); }
    public function create(array $data): int { return $this->model->create($data); }
    public function update(int $id, array $data): bool { return $this->model->update($id, $data); }
    public function delete(int $id): bool { return $this->model->delete($id); }
}