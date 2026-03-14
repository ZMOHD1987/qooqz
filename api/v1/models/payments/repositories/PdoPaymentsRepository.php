<?php
declare(strict_types=1);

final class PdoPaymentsRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo) { $this->pdo = $pdo; }

    public function all(int $tenantId, ?int $limit, ?int $offset, array $filters, string $orderBy, string $orderDir, string $lang): array
    {
        $sql = "SELECT p.* FROM payments p INNER JOIN entities e ON p.entity_id = e.id WHERE e.tenant_id = :tenant_id";
        $params = [':tenant_id' => $tenantId];
        if (!empty($filters['order_id'])) { $sql .= " AND p.order_id = :order_id"; $params[':order_id'] = $filters['order_id']; }
        if (!empty($filters['status'])) { $sql .= " AND p.status = :status"; $params[':status'] = $filters['status']; }
        $sql .= " ORDER BY p.id DESC";
        if ($limit) $sql .= " LIMIT :limit";
        if ($offset) $sql .= " OFFSET :offset";
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) $stmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
        if ($limit) $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        if ($offset) $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function count(int $tenantId, array $filters): int
    {
        $sql = "SELECT COUNT(*) FROM payments p INNER JOIN entities e ON p.entity_id = e.id WHERE e.tenant_id = :tenant_id";
        $params = [':tenant_id' => $tenantId];
        if (!empty($filters['order_id'])) { $sql .= " AND p.order_id = :order_id"; $params[':order_id'] = $filters['order_id']; }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    public function find(int $tenantId, int $id, string $lang): ?array
    {
        $stmt = $this->pdo->prepare("SELECT p.* FROM payments p INNER JOIN entities e ON p.entity_id = e.id WHERE e.tenant_id = :tenant_id AND p.id = :id LIMIT 1");
        $stmt->execute([':tenant_id' => $tenantId, ':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function save(int $tenantId, array $data): int
    {
        $isUpdate = !empty($data['id']);
        $fields = ['payment_number', 'entity_id', 'payment_method_id', 'order_id', 'user_id', 'payment_method', 'payment_gateway', 'transaction_id', 'amount', 'currency_code', 'status', 'payment_type'];
        $params = [];
        foreach ($fields as $f) $params[':' . $f] = $data[$f] ?? null;
        
        if (empty($params[':payment_number'])) $params[':payment_number'] = 'PAY-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
        if (empty($params[':currency_code'])) $params[':currency_code'] = 'SAR';
        if (empty($params[':status'])) $params[':status'] = 'pending';

        if ($isUpdate) {
            $params[':id'] = $data['id'];
            $stmt = $this->pdo->prepare("UPDATE payments SET payment_number=:payment_number, entity_id=:entity_id, payment_method_id=:payment_method_id, order_id=:order_id, user_id=:user_id, payment_method=:payment_method, payment_gateway=:payment_gateway, transaction_id=:transaction_id, amount=:amount, currency_code=:currency_code, status=:status, payment_type=:payment_type, updated_at=CURRENT_TIMESTAMP WHERE id=:id");
            $stmt->execute($params);
            return (int)$data['id'];
        }

        $stmt = $this->pdo->prepare("INSERT INTO payments (payment_number, entity_id, payment_method_id, order_id, user_id, payment_method, payment_gateway, transaction_id, amount, currency_code, status, payment_type) VALUES (:payment_number, :entity_id, :payment_method_id, :order_id, :user_id, :payment_method, :payment_gateway, :transaction_id, :amount, :currency_code, :status, :payment_type)");
        $stmt->execute($params);
        return (int)$this->pdo->lastInsertId();
    }

    public function delete(int $tenantId, int $id): bool
    {
        $stmt = $this->pdo->prepare("UPDATE payments SET status = 'cancelled', updated_at = CURRENT_TIMESTAMP WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }
}
