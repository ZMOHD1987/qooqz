<?php
declare(strict_types=1);

final class PdoEntitiesRepository
{
    private PDO $pdo;

    private const ALLOWED_ORDER_BY = [
        'id','store_name','status','is_verified','joined_at','created_at'
    ];

    private const FILTERABLE_COLUMNS = [
        'tenant_id','user_id','status','vendor_type','store_type','is_verified'
    ];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // =========================
    // List
    // =========================
    public function all(
        int $tenantId,
        ?int $limit,
        ?int $offset,
        array $filters,
        string $orderBy,
        string $orderDir,
        string $lang
    ): array {
        $sql = "
            SELECT e.*,
                   e.store_name AS original_store_name,
                   COALESCE(et.store_name, e.store_name) AS store_name,
                   et.description,
                   et.meta_title,
                   et.meta_description,
                   tz.timezone AS timezone_name,
                   tz.label    AS timezone_label
            FROM entities e
            LEFT JOIN entity_translations et
              ON e.id = et.entity_id AND et.language_code = :lang
            LEFT JOIN timezones tz ON tz.id = e.timezone_id
            WHERE e.tenant_id = :tenant_id
        ";

        $params = [
            ':tenant_id' => $tenantId,
            ':lang' => $lang
        ];

        foreach (self::FILTERABLE_COLUMNS as $col) {
            if (isset($filters[$col]) && $filters[$col] !== null) {
                $sql .= " AND e.{$col} = :{$col}";
                $params[":{$col}"] = $filters[$col];
            }
        }

        $orderBy = in_array($orderBy, self::ALLOWED_ORDER_BY, true) ? $orderBy : 'id';
        $orderDir = strtoupper($orderDir) === 'ASC' ? 'ASC' : 'DESC';
        $sql .= " ORDER BY e.{$orderBy} {$orderDir}";

        if ($limit !== null)  $sql .= " LIMIT :limit";
        if ($offset !== null) $sql .= " OFFSET :offset";

        $stmt = $this->pdo->prepare($sql);

        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        if ($limit !== null)  $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        if ($offset !== null) $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function count(int $tenantId, array $filters): int
    {
        $sql = "SELECT COUNT(*) FROM entities WHERE tenant_id = :tenant_id";
        $params = [':tenant_id'=>$tenantId];

        foreach (self::FILTERABLE_COLUMNS as $col) {
            if (isset($filters[$col]) && $filters[$col] !== null) {
                $sql .= " AND {$col} = :{$col}";
                $params[":{$col}"] = $filters[$col];
            }
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    // =========================
    // Find
    // =========================
    public function find(int $tenantId, int $id, string $lang): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT e.*,
                   e.store_name AS original_store_name,
                   COALESCE(et.store_name, e.store_name) AS store_name,
                   et.description,
                   et.meta_title,
                   et.meta_description,
                   tz.timezone AS timezone_name,
                   tz.label    AS timezone_label
            FROM entities e
            LEFT JOIN entity_translations et
              ON e.id = et.entity_id AND et.language_code = :lang
            LEFT JOIN timezones tz ON tz.id = e.timezone_id
            WHERE e.tenant_id = :tenant_id AND e.id = :id
            LIMIT 1
        ");
        $stmt->execute([
            ':tenant_id'=>$tenantId,
            ':id'=>$id,
            ':lang'=>$lang
        ]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    // =========================
    // Save
    // =========================
    public function save(int $tenantId, array $data): int
    {
        if (!empty($data['id'])) {
            $stmt = $this->pdo->prepare("
                UPDATE entities SET
                    store_name = :store_name,
                    slug = :slug,
                    parent_id = :parent_id,
                    branch_code = :branch_code,
                    status = :status,
                    vendor_type = :vendor_type,
                    store_type = :store_type,
                    registration_number = :registration_number,
                    tax_number = :tax_number,
                    phone = :phone,
                    mobile = :mobile,
                    email = :email,
                    website_url = :website_url,
                    suspension_reason = :suspension_reason,
                    is_verified = :is_verified,
                    timezone_id = :timezone_id,
                    updated_at = CURRENT_TIMESTAMP
                WHERE tenant_id = :tenant_id AND id = :id
            ");
            $stmt->execute([
                ':id'=>$data['id'],
                ':tenant_id'=>$tenantId,
                ':store_name'=>$data['store_name'] ?? null,
                ':slug'=>$data['slug'] ?? null,
                ':parent_id'=>!empty($data['parent_id']) ? (int)$data['parent_id'] : null,
                ':branch_code'=>$data['branch_code'] ?? null,
                ':status'=>$data['status'] ?? 'pending',
                ':vendor_type'=>$data['vendor_type'] ?? 'product_seller',
                ':store_type'=>$data['store_type'] ?? 'individual',
                ':registration_number'=>$data['registration_number'] ?? null,
                ':tax_number'=>$data['tax_number'] ?? null,
                ':phone'=>$data['phone'],
                ':mobile'=>$data['mobile'] ?? null,
                ':email'=>$data['email'],
                ':website_url'=>$data['website_url'] ?? null,
                ':suspension_reason'=>$data['suspension_reason'] ?? null,
                ':is_verified'=>$data['is_verified'] ?? 0,
                ':timezone_id'=>!empty($data['timezone_id']) ? (int)$data['timezone_id'] : null
            ]);
            return (int)$data['id'];
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO entities (
                tenant_id, user_id, store_name, slug,
                parent_id, branch_code,
                vendor_type, store_type,
                registration_number, tax_number,
                phone, mobile, email, website_url,
                status, is_verified, timezone_id
            ) VALUES (
                :tenant_id, :user_id, :store_name, :slug,
                :parent_id, :branch_code,
                :vendor_type, :store_type,
                :registration_number, :tax_number,
                :phone, :mobile, :email, :website_url,
                :status, :is_verified, :timezone_id
            )
        ");
        $stmt->execute([
            ':tenant_id'=>$tenantId,
            ':user_id'=>$data['user_id'],
            ':store_name'=>$data['store_name'],
            ':slug'=>$data['slug'],
            ':parent_id'=>!empty($data['parent_id']) ? (int)$data['parent_id'] : null,
            ':branch_code'=>$data['branch_code'] ?? null,
            ':vendor_type'=>$data['vendor_type'] ?? 'product_seller',
            ':store_type'=>$data['store_type'] ?? 'individual',
            ':registration_number'=>$data['registration_number'] ?? null,
            ':tax_number'=>$data['tax_number'] ?? null,
            ':phone'=>$data['phone'],
            ':mobile'=>$data['mobile'] ?? null,
            ':email'=>$data['email'],
            ':website_url'=>$data['website_url'] ?? null,
            ':status'=>$data['status'] ?? 'pending',
            ':is_verified'=>$data['is_verified'] ?? 0,
            ':timezone_id'=>!empty($data['timezone_id']) ? (int)$data['timezone_id'] : null
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    // =========================
    // Save Translation
    // =========================
    public function saveTranslation(int $entityId, string $lang, array $data): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO entity_translations
            (entity_id, language_code, store_name, description, meta_title, meta_description)
            VALUES (:entity_id,:lang,:store_name,:description,:meta_title,:meta_description)
            ON DUPLICATE KEY UPDATE
                store_name = VALUES(store_name),
                description = VALUES(description),
                meta_title = VALUES(meta_title),
                meta_description = VALUES(meta_description)
        ");
        $stmt->execute([
            ':entity_id'=>$entityId,
            ':lang'=>$lang,
            ':store_name'=>$data['store_name'],
            ':description'=>$data['description'] ?? null,
            ':meta_title'=>$data['meta_title'] ?? null,
            ':meta_description'=>$data['meta_description'] ?? null
        ]);
    }

    public function delete(int $tenantId, int $id): bool
    {
        return $this->pdo
            ->prepare("DELETE FROM entities WHERE tenant_id = :t AND id = :i")
            ->execute([':t'=>$tenantId,':i'=>$id]);
    }
}