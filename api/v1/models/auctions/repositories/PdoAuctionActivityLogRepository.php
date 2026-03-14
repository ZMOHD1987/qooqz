<?php
declare(strict_types=1);

final class PdoAuctionActivityLogRepository implements AuctionActivityLogRepositoryInterface
{
    private PDO $pdo;
    private const TABLE = 'auction_activity_log';
    private const ALLOWED_ORDER_BY = ['id', 'activity_type', 'amount', 'created_at'];
    private const VALID_ACTIVITY_TYPES = [
        'created', 'started', 'bid_placed', 'auto_bid_placed', 'outbid',
        'extended', 'paused', 'resumed', 'ended', 'cancelled',
        'winner_declared', 'payment_received', 'item_shipped'
    ];

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

        if (isset($filters['activity_type']) && $filters['activity_type'] !== '') {
            $sql .= " AND activity_type = :activity_type";
            $params[':activity_type'] = $filters['activity_type'];
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

        if (isset($filters['activity_type']) && $filters['activity_type'] !== '') {
            $sql .= " AND activity_type = :activity_type";
            $params[':activity_type'] = $filters['activity_type'];
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

    public function save(array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO " . self::TABLE . " (auction_id, user_id, activity_type, amount, notes, ip_address)
            VALUES (:auction_id, :user_id, :activity_type, :amount, :notes, :ip_address)
        ");
        $stmt->execute([
            ':auction_id'    => (int)$data['auction_id'],
            ':user_id'       => isset($data['user_id']) ? (int)$data['user_id'] : null,
            ':activity_type' => $data['activity_type'],
            ':amount'        => $data['amount'] ?? null,
            ':notes'         => $data['notes']  ?? null,
            ':ip_address'    => $data['ip_address'] ?? null,
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM " . self::TABLE . " WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }
}
