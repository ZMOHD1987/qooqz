<?php
declare(strict_types=1);

final class PdoAutoBidSettingsRepository implements AutoBidSettingsRepositoryInterface
{
    private PDO $pdo;
    private const TABLE = 'auto_bid_settings';
    private const ALLOWED_ORDER_BY = ['id', 'max_bid_amount', 'is_active', 'total_auto_bids', 'created_at', 'updated_at'];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function all(
        int $auctionId,
        ?int $limit = null,
        ?int $offset = null,
        array $filters = [],
        string $orderBy = 'id',
        string $orderDir = 'DESC'
    ): array {
        $sql    = "SELECT * FROM " . self::TABLE . " WHERE auction_id = :auction_id";
        $params = [':auction_id' => $auctionId];

        if (isset($filters['user_id']) && $filters['user_id'] !== '') {
            $sql .= " AND user_id = :user_id";
            $params[':user_id'] = (int)$filters['user_id'];
        }

        if (isset($filters['is_active'])) {
            $sql .= " AND is_active = :is_active";
            $params[':is_active'] = (int)$filters['is_active'];
        }

        $orderBy  = in_array($orderBy, self::ALLOWED_ORDER_BY, true) ? $orderBy : 'id';
        $orderDir = strtoupper($orderDir) === 'ASC' ? 'ASC' : 'DESC';
        $sql .= " ORDER BY {$orderBy} {$orderDir}";

        if ($limit !== null) {
            $sql .= " LIMIT :limit OFFSET :offset";
        }

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        if ($limit !== null) {
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset ?? 0, PDO::PARAM_INT);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function count(int $auctionId, array $filters = []): int
    {
        $sql    = "SELECT COUNT(*) FROM " . self::TABLE . " WHERE auction_id = :auction_id";
        $params = [':auction_id' => $auctionId];

        if (isset($filters['is_active'])) {
            $sql .= " AND is_active = :is_active";
            $params[':is_active'] = (int)$filters['is_active'];
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM " . self::TABLE . " WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findByUser(int $auctionId, int $userId): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM " . self::TABLE . " WHERE auction_id = :auction_id AND user_id = :user_id LIMIT 1"
        );
        $stmt->execute([':auction_id' => $auctionId, ':user_id' => $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function save(array $data): int
    {
        $isUpdate = !empty($data['id']);

        if ($isUpdate) {
            $stmt = $this->pdo->prepare("
                UPDATE " . self::TABLE . " SET
                    max_bid_amount  = :max_bid_amount,
                    is_active       = :is_active
                WHERE id = :id
            ");
            $stmt->execute([
                ':max_bid_amount' => $data['max_bid_amount'],
                ':is_active'      => isset($data['is_active']) ? (int)$data['is_active'] : 1,
                ':id'             => (int)$data['id'],
            ]);
            return (int)$data['id'];
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO " . self::TABLE . " (auction_id, user_id, max_bid_amount, is_active)
            VALUES (:auction_id, :user_id, :max_bid_amount, :is_active)
            ON DUPLICATE KEY UPDATE
                max_bid_amount = VALUES(max_bid_amount),
                is_active      = VALUES(is_active)
        ");
        $stmt->execute([
            ':auction_id'     => (int)$data['auction_id'],
            ':user_id'        => (int)$data['user_id'],
            ':max_bid_amount' => $data['max_bid_amount'],
            ':is_active'      => isset($data['is_active']) ? (int)$data['is_active'] : 1,
        ]);
        return (int)$this->pdo->lastInsertId() ?: (int)$data['auction_id'];
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM " . self::TABLE . " WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }
}
