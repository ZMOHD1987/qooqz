<?php
declare(strict_types=1);

final class PdoAuctionWatchersRepository implements AuctionWatchersRepositoryInterface
{
    private PDO $pdo;
    private const TABLE = 'auction_watchers';

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function all(int $auctionId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM " . self::TABLE . " WHERE auction_id = :auction_id ORDER BY created_at DESC"
        );
        $stmt->execute([':auction_id' => $auctionId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function find(int $auctionId, int $userId): ?array
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
        $stmt = $this->pdo->prepare("
            INSERT INTO " . self::TABLE . " (auction_id, user_id, notify_before_end, notify_on_outbid, notify_on_winner)
            VALUES (:auction_id, :user_id, :notify_before_end, :notify_on_outbid, :notify_on_winner)
            ON DUPLICATE KEY UPDATE
                notify_before_end = VALUES(notify_before_end),
                notify_on_outbid  = VALUES(notify_on_outbid),
                notify_on_winner  = VALUES(notify_on_winner)
        ");
        $stmt->execute([
            ':auction_id'       => (int)$data['auction_id'],
            ':user_id'          => (int)$data['user_id'],
            ':notify_before_end'=> isset($data['notify_before_end']) ? (int)$data['notify_before_end'] : 1,
            ':notify_on_outbid' => isset($data['notify_on_outbid'])  ? (int)$data['notify_on_outbid']  : 1,
            ':notify_on_winner' => isset($data['notify_on_winner'])  ? (int)$data['notify_on_winner']  : 1,
        ]);
        return (int)$this->pdo->lastInsertId() ?: (int)$data['auction_id'];
    }

    public function delete(int $auctionId, int $userId): bool
    {
        $stmt = $this->pdo->prepare(
            "DELETE FROM " . self::TABLE . " WHERE auction_id = :auction_id AND user_id = :user_id"
        );
        return $stmt->execute([':auction_id' => $auctionId, ':user_id' => $userId]);
    }
}
