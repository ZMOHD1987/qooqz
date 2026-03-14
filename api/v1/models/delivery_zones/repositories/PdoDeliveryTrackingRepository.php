<?php
declare(strict_types=1);

final class PdoDeliveryTrackingRepository implements DeliveryTrackingRepositoryInterface
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function all(
        int $tenantId,
        ?int $limit = null,
        ?int $offset = null,
        array $filters = [],
        string $orderBy = 'dt.id',
        string $orderDir = 'DESC',
        string $lang = 'ar'
    ): array {
        // Join delivery_orders to ensure tenant scope
        $sql = "
            SELECT dt.*
            FROM delivery_tracking dt
            INNER JOIN delivery_orders dord ON dt.delivery_order_id = dord.id
            WHERE dord.tenant_id = :tenant_id
        ";

        $params = [':tenant_id' => $tenantId];

        if (!empty($filters['delivery_order_id'])) {
            $sql .= " AND dt.delivery_order_id = :delivery_order_id";
            $params[':delivery_order_id'] = $filters['delivery_order_id'];
        }
        if (!empty($filters['provider_id'])) {
            $sql .= " AND dt.provider_id = :provider_id";
            $params[':provider_id'] = $filters['provider_id'];
        }

        $orderDir = strtoupper($orderDir) === 'ASC' ? 'ASC' : 'DESC';
        $sql .= " ORDER BY {$orderBy} {$orderDir}";

        if ($limit !== null) {
            $sql .= " LIMIT :limit";
            $params[':limit'] = $limit;
        }
        if ($offset !== null) {
            $sql .= " OFFSET :offset";
            $params[':offset'] = $offset;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function count(int $tenantId, array $filters = []): int
    {
        $sql = "
            SELECT COUNT(*)
            FROM delivery_tracking dt
            INNER JOIN delivery_orders dord ON dt.delivery_order_id = dord.id
            WHERE dord.tenant_id = :tenant_id
        ";
        $params = [':tenant_id' => $tenantId];

        if (!empty($filters['delivery_order_id'])) {
            $sql .= " AND dt.delivery_order_id = :delivery_order_id";
            $params[':delivery_order_id'] = $filters['delivery_order_id'];
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public function find(int $tenantId, int $id, string $lang = 'ar'): ?array
    {
        $sql = "
            SELECT dt.*
            FROM delivery_tracking dt
            INNER JOIN delivery_orders dord ON dt.delivery_order_id = dord.id
            WHERE dord.tenant_id = :tenant_id AND dt.id = :id
            LIMIT 1
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':tenant_id' => $tenantId, ':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function create(int $tenantId, array $data): int
    {
        // Note: We assume data validation ensures the delivery_order_id belongs to this tenant
        // before calling this method, or we rely on DB foreign keys.
        
        $stmt = $this->pdo->prepare("
            INSERT INTO delivery_tracking (delivery_order_id, provider_id, latitude, longitude, status_note)
            VALUES (:delivery_order_id, :provider_id, :latitude, :longitude, :status_note)
        ");

        $stmt->execute([
            ':delivery_order_id' => $data['delivery_order_id'],
            ':provider_id'       => $data['provider_id'] ?? null,
            ':latitude'          => $data['latitude'],
            ':longitude'         => $data['longitude'],
            ':status_note'       => $data['status_note'] ?? null,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function delete(int $tenantId, int $id): bool
    {
        $existing = $this->find($tenantId, $id);
        if (!$existing) return false;

        $stmt = $this->pdo->prepare("DELETE FROM delivery_tracking WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }
}