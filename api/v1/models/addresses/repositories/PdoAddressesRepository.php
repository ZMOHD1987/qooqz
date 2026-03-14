<?php
declare(strict_types=1);

final class PdoAddressesRepository
{
    private PDO $pdo;

    private const ALLOWED_ORDER_BY = [
        'id','owner_type','owner_id','city_id','country_id',
        'is_primary','created_at','updated_at'
    ];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // ================================
    // LIST
    // ================================
    public function list(
        int $limit,
        int $offset,
        array $filters,
        string $orderBy,
        string $orderDir
    ): array {
        if (!in_array($orderBy, self::ALLOWED_ORDER_BY, true)) {
            $orderBy = 'id';
        }

        $orderDir = strtoupper($orderDir) === 'ASC' ? 'ASC' : 'DESC';

        $where  = [];
        $params = [];
        $language = $filters['language'] ?? 'ar';

        foreach ([
            'id','owner_type','owner_id','city_id','country_id','is_primary'
        ] as $field) {
            if (array_key_exists($field, $filters) && $filters[$field] !== null && $filters[$field] !== '') {
                $where[] = "a.$field = :filter_$field";
                $params["filter_$field"] = $filters[$field];
            }
        }

        $whereSql = $where ? 'WHERE '.implode(' AND ', $where) : '';

        // ================================
        // DATA WITH TRANSLATIONS
        // ================================
        $sql = "
            SELECT 
                a.*,
                COALESCE(ct.name, c.name) AS country_name,
                COALESCE(cit.name, ci.name) AS city_name
            FROM addresses a
            LEFT JOIN countries c ON a.country_id = c.id
            LEFT JOIN country_translations ct ON c.id = ct.country_id AND ct.language_code = :lang_country
            LEFT JOIN cities ci ON a.city_id = ci.id
            LEFT JOIN city_translations cit ON ci.id = cit.city_id AND cit.language_code = :lang_city
            $whereSql
            ORDER BY a.$orderBy $orderDir
            LIMIT :limit OFFSET :offset
        ";

        $stmt = $this->pdo->prepare($sql);

        // Fix: Bind language parameters separately for both joins
        $stmt->bindValue(':lang_country', $language, PDO::PARAM_STR);
        $stmt->bindValue(':lang_city', $language, PDO::PARAM_STR);
        
        foreach ($params as $k => $v) {
            if (is_int($v)) {
                $stmt->bindValue(":$k", $v, PDO::PARAM_INT);
            } else {
                $stmt->bindValue(":$k", $v, PDO::PARAM_STR);
            }
        }

        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // ================================
        // COUNT
        // ================================
        $countSql = "
            SELECT COUNT(*)
            FROM addresses a
            $whereSql
        ";

        $countStmt = $this->pdo->prepare($countSql);
        foreach ($params as $k => $v) {
            if (is_int($v)) {
                $countStmt->bindValue(":$k", $v, PDO::PARAM_INT);
            } else {
                $countStmt->bindValue(":$k", $v, PDO::PARAM_STR);
            }
        }
        $countStmt->execute();

        $total = (int)$countStmt->fetchColumn();

        return [
            'items' => $items,
            'total' => $total
        ];
    }

    // ================================
    // GET
    // ================================
    public function find(int $id, string $language = 'ar'): ?array
    {
        $sql = "
            SELECT 
                a.*,
                COALESCE(ct.name, c.name) AS country_name,
                COALESCE(cit.name, ci.name) AS city_name
            FROM addresses a
            LEFT JOIN countries c ON a.country_id = c.id
            LEFT JOIN country_translations ct ON c.id = ct.country_id AND ct.language_code = :lang_country
            LEFT JOIN cities ci ON a.city_id = ci.id
            LEFT JOIN city_translations cit ON ci.id = cit.city_id AND ct.language_code = :lang_city
            WHERE a.id = :id
            LIMIT 1
        ";

        $stmt = $this->pdo->prepare($sql);
        // Fix: Bind language parameters separately here too for consistency
        $stmt->bindValue(':lang_country', $language, PDO::PARAM_STR);
        $stmt->bindValue(':lang_city', $language, PDO::PARAM_STR);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    // ================================
    // CREATE
    // ================================
    public function create(array $data): int
    {
        // If setting as primary, unset other primary addresses for this owner
        if (isset($data['is_primary']) && (int)$data['is_primary'] === 1) {
            $this->unsetPrimaryAddresses($data['owner_type'], (int)$data['owner_id']);
        }

        $sql = "
            INSERT INTO addresses (
                owner_type, owner_id, address_line1, address_line2,
                city_id, country_id, postal_code,
                latitude, longitude, is_primary
            ) VALUES (
                :owner_type, :owner_id, :address_line1, :address_line2,
                :city_id, :country_id, :postal_code,
                :latitude, :longitude, :is_primary
            )
        ";

        $params = [
            'owner_type'    => $data['owner_type'] ?? null,
            'owner_id'      => $data['owner_id'] ?? null,
            'address_line1' => $data['address_line1'] ?? null,
            'address_line2' => $data['address_line2'] ?? null,
            'city_id'       => isset($data['city_id']) && $data['city_id'] !== '' ? (int)$data['city_id'] : null,
            'country_id'    => isset($data['country_id']) && $data['country_id'] !== '' ? (int)$data['country_id'] : null,
            'postal_code'   => $data['postal_code'] ?? null,
            'latitude'      => isset($data['latitude']) && $data['latitude'] !== '' ? (float)$data['latitude'] : null,
            'longitude'     => isset($data['longitude']) && $data['longitude'] !== '' ? (float)$data['longitude'] : null,
            'is_primary'    => isset($data['is_primary']) ? (int)$data['is_primary'] : 0,
        ];

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return (int)$this->pdo->lastInsertId();
        } catch (\PDOException $e) {
            // If it's a duplicate entry error on entity_address key
            if ($e->getCode() == 23000 && strpos($e->getMessage(), 'entity_address') !== false) {
                // This means there's already a non-primary address, which shouldn't be unique
                // We need to handle this by modifying our approach
                throw new \RuntimeException('An address already exists. If you have a UNIQUE constraint on entity_address, please modify it to only apply when is_primary = 1', 400);
            }
            throw $e;
        }
    }

    // ================================
    // UPDATE
    // ================================
    public function update(int $id, array $data): bool
    {
        unset($data['id']);
        unset($data['csrf_token']);
        unset($data['tenant_id']);

        if (!$data) {
            return false;
        }

        // If setting as primary, unset other primary addresses for this owner
        if (isset($data['is_primary']) && (int)$data['is_primary'] === 1) {
            // Get current address to find owner
            $current = $this->find($id);
            if ($current) {
                $this->unsetPrimaryAddresses($current['owner_type'], (int)$current['owner_id'], $id);
            }
        }

        $sets = [];
        foreach ($data as $key => $_) {
            $sets[] = "$key = :$key";
        }

        $sql = "
            UPDATE addresses
            SET ".implode(', ', $sets)."
            WHERE id = :id
        ";

        $data['id'] = $id;

        return $this->pdo->prepare($sql)->execute($data);
    }

    // ================================
    // UNSET PRIMARY ADDRESSES
    // ================================
    private function unsetPrimaryAddresses(string $ownerType, int $ownerId, ?int $excludeId = null): void
    {
        $sql = "
            UPDATE addresses
            SET is_primary = 0
            WHERE owner_type = :owner_type
            AND owner_id = :owner_id
        ";

        $params = [
            'owner_type' => $ownerType,
            'owner_id'   => $ownerId,
        ];

        if ($excludeId !== null) {
            $sql .= " AND id != :exclude_id";
            $params['exclude_id'] = $excludeId;
        }

        $this->pdo->prepare($sql)->execute($params);
    }

    // ================================
    // DELETE
    // ================================
    public function delete(int $id): bool
    {
        return $this->pdo
            ->prepare("DELETE FROM addresses WHERE id = :id")
            ->execute(['id' => $id]);
    }
}