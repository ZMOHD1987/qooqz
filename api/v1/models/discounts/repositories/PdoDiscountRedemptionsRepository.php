<?php
declare(strict_types=1);

/**
 * PDO repository for the discount_redemptions table.
 */
final class PdoDiscountRedemptionsRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // ================================
    // List redemptions by discount
    // ================================
    public function listByDiscount(int $discountId, ?int $limit = null, ?int $offset = null): array
    {
        $sql = "SELECT * FROM discount_redemptions WHERE discount_id = :discount_id ORDER BY redeemed_at DESC";
        $params = [':discount_id' => $discountId];

        if ($limit !== null) $sql .= " LIMIT :limit";
        if ($offset !== null) $sql .= " OFFSET :offset";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':discount_id', $discountId, PDO::PARAM_INT);
        if ($limit  !== null) $stmt->bindValue(':limit',  (int)$limit,  PDO::PARAM_INT);
        if ($offset !== null) $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ================================
    // Create
    // ================================
    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO discount_redemptions
                (discount_id, user_id, order_id, amount_discounted, currency_code, redeemed_at)
            VALUES
                (:discount_id, :user_id, :order_id, :amount_discounted, :currency_code, :redeemed_at)
        ");
        $stmt->execute([
            ':discount_id'      => $data['discount_id'],
            ':user_id'          => $data['user_id'] ?? null,
            ':order_id'         => $data['order_id'] ?? null,
            ':amount_discounted' => $data['amount_discounted'] ?? 0,
            ':currency_code'    => $data['currency_code'] ?? null,
            ':redeemed_at'      => $data['redeemed_at'] ?? date('Y-m-d H:i:s'),
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    // ================================
    // Stats for a discount
    // ================================
    /**
     * @return array{total_redeemed: int, total_amount: float, unique_users: int}
     */
    public function stats(int $discountId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                COUNT(*) AS total_redeemed,
                COALESCE(SUM(amount_discounted), 0) AS total_amount,
                COUNT(DISTINCT user_id) AS unique_users
            FROM discount_redemptions
            WHERE discount_id = :discount_id
        ");
        $stmt->execute([':discount_id' => $discountId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'total_redeemed' => (int)($row['total_redeemed'] ?? 0),
            'total_amount'   => (float)($row['total_amount'] ?? 0),
            'unique_users'   => (int)($row['unique_users'] ?? 0),
        ];
    }
}
