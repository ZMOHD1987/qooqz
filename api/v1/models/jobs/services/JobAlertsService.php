<?php
declare(strict_types=1);

final class JobAlertsService
{
    private PdoJobAlertsRepository $repo;

    public function __construct(PdoJobAlertsRepository $repo)
    {
        $this->repo = $repo;
    }

    // ================================
    // List alerts with filters
    // ================================
    public function list(
        int $userId,
        ?int $limit,
        ?int $offset,
        array $filters,
        string $orderBy,
        string $orderDir
    ): array {
        return $this->repo->all(
            $userId,
            $limit,
            $offset,
            $filters,
            $orderBy,
            $orderDir
        );
    }

    // ================================
    // Count alerts
    // ================================
    public function count(int $userId, array $filters = []): int
    {
        return $this->repo->count($userId, $filters);
    }

    // ================================
    // Get single alert by ID
    // ================================
    public function get(int $userId, int $id): ?array
    {
        return $this->repo->find($userId, $id);
    }

    // ================================
    // Create new alert
    // ================================
    public function create(int $userId, array $data): int
    {
        $data['user_id'] = $userId;
        return $this->repo->save($userId, $data);
    }

    // ================================
    // Update existing alert
    // ================================
    public function update(int $userId, array $data): int
    {
        if (empty($data['id'])) {
            throw new InvalidArgumentException('ID is required for update');
        }

        // التحقق من أن التنبيه ينتمي للمستخدم
        $existing = $this->repo->find($userId, (int)$data['id']);
        if (!$existing) {
            throw new RuntimeException('Alert not found or does not belong to this user');
        }

        return $this->repo->save($userId, $data);
    }

    // ================================
    // Delete alert
    // ================================
    public function delete(int $userId, int $id): bool
    {
        // التحقق من أن التنبيه ينتمي للمستخدم
        $existing = $this->repo->find($userId, $id);
        if (!$existing) {
            throw new RuntimeException('Alert not found or does not belong to this user');
        }

        return $this->repo->delete($userId, $id);
    }

    // ================================
    // Toggle active status
    // ================================
    public function toggleActive(int $userId, int $id): bool
    {
        // التحقق من أن التنبيه ينتمي للمستخدم
        $existing = $this->repo->find($userId, $id);
        if (!$existing) {
            throw new RuntimeException('Alert not found or does not belong to this user');
        }

        return $this->repo->toggleActive($userId, $id);
    }

    // ================================
    // Get alerts due for sending
    // ================================
    public function getDueAlerts(string $frequency = 'daily'): array
    {
        return $this->repo->getDueAlerts($frequency);
    }

    // ================================
    // Update last sent timestamp
    // ================================
    public function updateLastSent(int $alertId): bool
    {
        return $this->repo->updateLastSent($alertId);
    }

    // ================================
    // Get user statistics
    // ================================
    public function getStatistics(int $userId): array
    {
        return $this->repo->getStatistics($userId);
    }

    // ================================
    // Batch activate/deactivate
    // ================================
    public function batchUpdateStatus(int $userId, array $alertIds, bool $isActive): int
    {
        $updated = 0;
        foreach ($alertIds as $alertId) {
            try {
                $alert = $this->repo->find($userId, (int)$alertId);
                if ($alert && (int)$alert['is_active'] !== (int)$isActive) {
                    $this->repo->toggleActive($userId, (int)$alertId);
                    $updated++;
                }
            } catch (Exception $e) {
                // تسجيل الخطأ والمتابعة
                continue;
            }
        }
        return $updated;
    }

    // ================================
    // Check if user can create more alerts (quota check)
    // ================================
    public function canCreateAlert(int $userId, int $maxAlerts = 10): bool
    {
        $count = $this->repo->count($userId, []);
        return $count < $maxAlerts;
    }

    // ================================
    // Get active alerts count
    // ================================
    public function getActiveAlertsCount(int $userId): int
    {
        return $this->repo->count($userId, ['is_active' => 1]);
    }
}
