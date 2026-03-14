<?php
declare(strict_types=1);

final class PdoProductVariantsRepository
{
    private PDO $pdo;

    private const ALLOWED_ORDER_BY = [
        'id','product_id','sku','barcode','stock_quantity','low_stock_threshold',
        'is_active','is_default','created_at','updated_at'
    ];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // ===========================
    // List variants with optional translations
    // ===========================
    public function allWithTranslations(
        int $tenantId,
        ?string $languageCode = null,
        ?int $limit = null,
        ?int $offset = null,
        array $filters = [],
        string $orderBy = 'id',
        string $orderDir = 'DESC'
    ): array {
        $sql = "SELECT pv.*, pvt.name AS translation_name, pvt.language_code
                FROM product_variants pv
                INNER JOIN products p ON pv.product_id = p.id
                LEFT JOIN product_variant_translations pvt 
                       ON pv.id = pvt.variant_id" . ($languageCode ? " AND pvt.language_code = :lang" : "") . "
                WHERE p.tenant_id = :tenant_id";
        $params = [':tenant_id'=>$tenantId];
        if($languageCode) $params[':lang']=$languageCode;

        if (!empty($filters['product_id'])) {
            $sql .= " AND pv.product_id = :product_id";
            $params[':product_id'] = (int)$filters['product_id'];
        }
        if (!empty($filters['sku'])) {
            $sql .= " AND pv.sku LIKE :sku";
            $params[':sku'] = '%'.$filters['sku'].'%';
        }

        $orderBy = in_array($orderBy, self::ALLOWED_ORDER_BY, true) ? $orderBy : 'id';
        $orderDir = strtoupper($orderDir) === 'ASC' ? 'ASC' : 'DESC';
        $sql .= " ORDER BY {$orderBy} {$orderDir}";

        if($limit !== null) $sql .= " LIMIT :limit";
        if($offset !== null) $sql .= " OFFSET :offset";

        $stmt = $this->pdo->prepare($sql);
        foreach($params as $k=>$v){
            $type = is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR;
            $stmt->bindValue($k,$v,$type);
        }
        if($limit!==null) $stmt->bindValue(':limit',(int)$limit,PDO::PARAM_INT);
        if($offset!==null) $stmt->bindValue(':offset',(int)$offset,PDO::PARAM_INT);

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // الأعمدة المسموحة في جدول product_variants
    private const VARIANT_COLUMNS = [
        'product_id', 'sku', 'barcode', 'stock_quantity',
        'low_stock_threshold', 'is_active', 'is_default'
    ];

    // ===========================
    // Save / Update variant
    // ===========================
    public function save(int $tenantId, array $data): int
    {
        $isUpdate = !empty($data['id']);

        // استخراج الأعمدة المسموح بها فقط
        $params = [];
        foreach (self::VARIANT_COLUMNS as $col) {
            if (array_key_exists($col, $data)) {
                $val = $data[$col];
                $params[':' . $col] = ($val === '' || $val === null) ? null : $val;
            } else {
                $params[':' . $col] = null;
            }
        }

        // product_id مطلوب
        if (empty($params[':product_id'])) {
            throw new \InvalidArgumentException('product_id is required for variant');
        }

        // توليد SKU تلقائياً إذا فارغ
        if (empty($params[':sku'])) {
            $params[':sku'] = 'VAR-' . strtoupper(bin2hex(random_bytes(4))) . '-' . time();
        }

        if ($isUpdate) {
            $params[':id'] = (int)$data['id'];

            $stmt = $this->pdo->prepare("
                UPDATE product_variants SET
                    product_id = :product_id,
                    sku = :sku,
                    barcode = :barcode,
                    stock_quantity = :stock_quantity,
                    low_stock_threshold = :low_stock_threshold,
                    is_active = :is_active,
                    is_default = :is_default,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :id
            ");
            $stmt->execute($params);
            return (int)$data['id'];
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO product_variants (
                product_id, sku, barcode, stock_quantity, low_stock_threshold,
                is_active, is_default
            ) VALUES (
                :product_id, :sku, :barcode, :stock_quantity, :low_stock_threshold,
                :is_active, :is_default
            )
        ");
        $stmt->execute($params);
        return (int)$this->pdo->lastInsertId();
    }

    // ===========================
    // Delete variant
    // ===========================
    public function delete(int $tenantId, int $id): bool
    {
        $stmt = $this->pdo->prepare("
            DELETE pv FROM product_variants pv
            INNER JOIN products p ON pv.product_id = p.id
            WHERE pv.id = :id AND p.tenant_id = :tenant_id
        ");
        return $stmt->execute([':id'=>$id, ':tenant_id'=>$tenantId]);
    }

    // ===========================
    // Save or update translation
    // ===========================
    public function saveTranslation(int $variantId, string $languageCode, string $name): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO product_variant_translations (variant_id, language_code, name)
            VALUES (:variant_id, :lang, :name)
            ON DUPLICATE KEY UPDATE name = :name
        ");
        $stmt->execute([
            ':variant_id' => $variantId,
            ':lang' => $languageCode,
            ':name' => $name
        ]);
    }

    // ===========================
    // Get translations for a variant
    // ===========================
    public function getTranslations(int $variantId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT pvt.*, l.name AS language_name, l.direction
            FROM product_variant_translations pvt
            INNER JOIN languages l ON pvt.language_code = l.code
            WHERE pvt.variant_id = :variant_id
        ");
        $stmt->execute([':variant_id'=>$variantId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}