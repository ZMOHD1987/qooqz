<?php
declare(strict_types=1);

final class PdoProductVariantAttributesRepository
{
    private PDO $pdo;

    private const ALLOWED_ORDER_BY = ['id','variant_id','attribute_id','attribute_value_id','created_at'];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // ===========================
    // List with filters, pagination, ordering
    // ===========================
    public function all(
        int $tenantId, // optional if you have tenant relation
        ?int $limit = null,
        ?int $offset = null,
        array $filters = [],
        string $orderBy = 'id',
        string $orderDir = 'DESC'
    ): array {
        $sql = "SELECT pva.*
                FROM product_variant_attributes pva
                INNER JOIN product_variants pv ON pva.variant_id = pv.id
                INNER JOIN products p ON pv.product_id = p.id
                WHERE p.tenant_id = :tenant_id";

        $params = [':tenant_id'=>$tenantId];

        if(!empty($filters['variant_id'])){
            $sql .= " AND pva.variant_id = :variant_id";
            $params[':variant_id'] = (int)$filters['variant_id'];
        }
        if(!empty($filters['attribute_id'])){
            $sql .= " AND pva.attribute_id = :attribute_id";
            $params[':attribute_id'] = (int)$filters['attribute_id'];
        }
        if(!empty($filters['attribute_value_id'])){
            $sql .= " AND pva.attribute_value_id = :attribute_value_id";
            $params[':attribute_value_id'] = (int)$filters['attribute_value_id'];
        }

        $orderBy = in_array($orderBy,self::ALLOWED_ORDER_BY,true) ? $orderBy : 'id';
        $orderDir = strtoupper($orderDir) === 'ASC' ? 'ASC' : 'DESC';
        $sql .= " ORDER BY {$orderBy} {$orderDir}";

        if($limit!==null) $sql .= " LIMIT :limit";
        if($offset!==null) $sql .= " OFFSET :offset";

        $stmt = $this->pdo->prepare($sql);
        foreach($params as $k=>$v){
            $type = is_int($v)?PDO::PARAM_INT:PDO::PARAM_STR;
            $stmt->bindValue($k,$v,$type);
        }
        if($limit!==null) $stmt->bindValue(':limit',(int)$limit,PDO::PARAM_INT);
        if($offset!==null) $stmt->bindValue(':offset',(int)$offset,PDO::PARAM_INT);

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ===========================
    // Count for pagination
    // ===========================
    public function count(int $tenantId, array $filters = []): int
    {
        $sql = "SELECT COUNT(*) 
                FROM product_variant_attributes pva
                INNER JOIN product_variants pv ON pva.variant_id = pv.id
                INNER JOIN products p ON pv.product_id = p.id
                WHERE p.tenant_id = :tenant_id";

        $params = [':tenant_id'=>$tenantId];

        if(!empty($filters['variant_id'])) $params[':variant_id']=(int)$filters['variant_id'];
        if(!empty($filters['attribute_id'])) $params[':attribute_id']=(int)$filters['attribute_id'];
        if(!empty($filters['attribute_value_id'])) $params[':attribute_value_id']=(int)$filters['attribute_value_id'];

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    // ===========================
    // Find single
    // ===========================
    public function find(int $tenantId,int $id): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT pva.*
            FROM product_variant_attributes pva
            INNER JOIN product_variants pv ON pva.variant_id = pv.id
            INNER JOIN products p ON pv.product_id = p.id
            WHERE p.tenant_id = :tenant_id AND pva.id = :id
            LIMIT 1
        ");
        $stmt->execute([':tenant_id'=>$tenantId,':id'=>$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    // ===========================
    // Create / Update
    // ===========================
    public function save(int $tenantId, array $data): int
    {
        $isUpdate = !empty($data['id']);
        if($isUpdate){
            $stmt = $this->pdo->prepare("
                UPDATE product_variant_attributes SET
                    variant_id = :variant_id,
                    attribute_id = :attribute_id,
                    attribute_value_id = :attribute_value_id
                WHERE id = :id
            ");
            $stmt->execute(array_merge($data, [':id'=>$data['id']]));
            return (int)$data['id'];
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO product_variant_attributes (variant_id,attribute_id,attribute_value_id)
            VALUES (:variant_id,:attribute_id,:attribute_value_id)
        ");
        $stmt->execute($data);
        return (int)$this->pdo->lastInsertId();
    }

    // ===========================
    // Delete
    // ===========================
    public function delete(int $tenantId,int $id): bool
    {
        $stmt = $this->pdo->prepare("
            DELETE pva FROM product_variant_attributes pva
            INNER JOIN product_variants pv ON pva.variant_id = pv.id
            INNER JOIN products p ON pv.product_id = p.id
            WHERE p.tenant_id = :tenant_id AND pva.id = :id
        ");
        return $stmt->execute([':tenant_id'=>$tenantId,':id'=>$id]);
    }
}
