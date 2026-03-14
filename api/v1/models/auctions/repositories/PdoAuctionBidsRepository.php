<?php
declare(strict_types=1);

final class PdoAuctionBidsRepository implements AuctionBidsRepositoryInterface
{
    private PDO $pdo;
    private const TABLE = 'auction_bids';
    private const ALLOWED_ORDER_BY = ['id', 'bid_amount', 'bid_type', 'is_winning', 'created_at'];

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

        if (isset($filters['bid_type']) && $filters['bid_type'] !== '') {
            $sql .= " AND bid_type = :bid_type";
            $params[':bid_type'] = $filters['bid_type'];
        }

        if (isset($filters['is_winning'])) {
            $sql .= " AND is_winning = :is_winning";
            $params[':is_winning'] = (int)$filters['is_winning'];
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

        if (isset($filters['user_id']) && $filters['user_id'] !== '') {
            $sql .= " AND user_id = :user_id";
            $params[':user_id'] = (int)$filters['user_id'];
        }

        if (isset($filters['bid_type']) && $filters['bid_type'] !== '') {
            $sql .= " AND bid_type = :bid_type";
            $params[':bid_type'] = $filters['bid_type'];
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
            INSERT INTO " . self::TABLE . " (auction_id, user_id, bid_amount, max_auto_bid, bid_type, is_winning, is_auto_outbid, ip_address, user_agent)
            VALUES (:auction_id, :user_id, :bid_amount, :max_auto_bid, :bid_type, :is_winning, :is_auto_outbid, :ip_address, :user_agent)
        ");
        $stmt->execute([
            ':auction_id'    => (int)$data['auction_id'],
            ':user_id'       => (int)$data['user_id'],
            ':bid_amount'    => $data['bid_amount'],
            ':max_auto_bid'  => $data['max_auto_bid'] ?? null,
            ':bid_type'      => $data['bid_type'] ?? 'manual',
            ':is_winning'    => isset($data['is_winning']) ? (int)$data['is_winning'] : 0,
            ':is_auto_outbid'=> isset($data['is_auto_outbid']) ? (int)$data['is_auto_outbid'] : 0,
            ':ip_address'    => $data['ip_address'] ?? null,
            ':user_agent'    => $data['user_agent'] ?? null,
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM " . self::TABLE . " WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }
}
