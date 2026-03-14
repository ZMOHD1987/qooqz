<?php
declare(strict_types=1);

final class PdoImagesRepository
{
    private PDO $pdo;
    private string $table = 'images';
    private string $imageTypesTable = 'image_types';

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /* ===================== IMAGE TYPES ===================== */
    public function getImageType(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM {$this->imageTypesTable} WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function getImageTypeByCode(string $code): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM {$this->imageTypesTable} WHERE code = :code LIMIT 1");
        $stmt->execute([':code' => $code]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function getAllImageTypes(): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM {$this->imageTypesTable} ORDER BY name ASC");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /* ===================== LIST / PAGINATION ===================== */
    public function all(
        int $tenantId,
        ?int $ownerId = null,
        ?int $imageTypeId = null,
        ?string $visibility = null,
        ?string $filename = null,
        ?int $userId = null,
        int $page = 1,
        int $limit = 25
    ): array {
        $params = [':tenant_id' => $tenantId];
        $countParams = [':tenant_id' => $tenantId];
        
        $sql = "SELECT i.*, it.name as image_type_name, it.code as image_type_code 
                FROM {$this->table} i 
                LEFT JOIN {$this->imageTypesTable} it ON i.image_type_id = it.id 
                WHERE i.tenant_id = :tenant_id";
                
        $countSql = "SELECT COUNT(*) FROM {$this->table} WHERE tenant_id = :tenant_id";

        if ($ownerId) { 
            $sql .= " AND i.owner_id = :owner_id"; 
            $countSql .= " AND owner_id = :owner_id"; 
            $params[':owner_id'] = $countParams[':owner_id'] = $ownerId; 
        }
        if ($imageTypeId) { 
            $sql .= " AND i.image_type_id = :image_type_id"; 
            $countSql .= " AND image_type_id = :image_type_id"; 
            $params[':image_type_id'] = $countParams[':image_type_id'] = $imageTypeId; 
        }
        if ($visibility) { 
            $sql .= " AND i.visibility = :visibility"; 
            $countSql .= " AND visibility = :visibility"; 
            $params[':visibility'] = $countParams[':visibility'] = $visibility; 
        }
        if ($filename) { 
            $sql .= " AND i.filename LIKE :filename"; 
            $countSql .= " AND filename LIKE :filename"; 
            $params[':filename'] = $countParams[':filename'] = "%$filename%"; 
        }
        if ($userId) { 
            $sql .= " AND i.user_id = :user_id"; 
            $countSql .= " AND user_id = :user_id"; 
            $params[':user_id'] = $countParams[':user_id'] = $userId; 
        }

        $offset = ($page - 1) * $limit;
        $sql .= " ORDER BY i.is_main DESC, i.sort_order ASC, i.id DESC LIMIT :offset, :limit";
        $params[':offset'] = $offset; 
        $params[':limit'] = $limit;

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) { 
            $stmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR); 
        }
        $stmt->execute();
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $countStmt = $this->pdo->prepare($countSql);
        foreach ($countParams as $k => $v) { 
            $countStmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR); 
        }
        $countStmt->execute();
        $total = (int)$countStmt->fetchColumn();

        return ['data' => $data, 'total' => $total];
    }

    /* ===================== FIND ===================== */
    public function find(int $tenantId, int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT i.*, it.name as image_type_name, it.code as image_type_code 
             FROM {$this->table} i 
             LEFT JOIN {$this->imageTypesTable} it ON i.image_type_id = it.id 
             WHERE i.id = :id AND i.tenant_id = :tenant_id LIMIT 1"
        );
        $stmt->execute([':id' => $id, ':tenant_id' => $tenantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findByUrl(int $tenantId, string $url): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM {$this->table} WHERE url = :url AND tenant_id = :tenant_id LIMIT 1"
        );
        $stmt->execute([':url' => $url, ':tenant_id' => $tenantId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /* ===================== SAVE / CREATE ===================== */
    public function save(int $tenantId, array $data, ?int $userId = null): int
    {
        // إزالة الحقول غير المرغوب فيها
        $unwanted = ['csrf_token', 'entity', '_method', 'image_type_display', 'image_type_name', 'image_type_code'];
        foreach ($unwanted as $f) { 
            unset($data[$f]); 
        }

        $isUpdate = !empty($data['id']);

        if ($isUpdate) {
            $id = (int)$data['id'];
            unset($data['id'], $data['tenant_id']);
            $data['updated_at'] = date('Y-m-d H:i:s');

            // التحقق إذا كان يتم تغيير المالك
            $existing = $this->find($tenantId, $id);
            if ($existing && isset($data['owner_id']) && $data['owner_id'] != $existing['owner_id']) {
                // إنشاء سجل جديد للمالك الجديد
                unset($data['id'], $data['updated_at']);
                $data['tenant_id'] = $tenantId;
                $data['created_at'] = date('Y-m-d H:i:s');
                $data['image_type_id'] = $existing['image_type_id']; // تأكيد نفس النوع
                
                // السجل الجديد يكون غير رئيسي دائمًا عند نقل الملكية
                $data['is_main'] = 0;
                
                // الحصول على اتصال PDO للإدراج المباشر
                $columns = implode(', ', array_keys($data));
                $placeholders = ':' . implode(', :', array_keys($data));
                $stmt = $this->pdo->prepare("INSERT INTO {$this->table} ($columns) VALUES ($placeholders)");
                
                // Bind values
                foreach ($data as $k => $v) {
                    $stmt->bindValue(':' . $k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
                }
                
                $stmt->execute();
                $newId = (int)$this->pdo->lastInsertId();
                
                // إذا لم يكن للمالك الجديد صورة رئيسية، جعل الصورة المنقولة هي الرئيسية
                if (!$this->getMainImage($tenantId, $data['owner_id'], $existing['image_type_id'])) {
                    $this->setMain($tenantId, $data['owner_id'], $existing['image_type_id'], $newId, $userId);
                }
                
                return $newId;
            }

            // إذا تم تعيين الصورة كرئيسية، إلغاء الرئيسية من الصور الأخرى لنفس المالك والنوع
            if (isset($data['is_main']) && $data['is_main'] == 1) {
                $this->unsetOtherMainImages(
                    $tenantId, 
                    $existing['owner_id'] ?? $data['owner_id'], 
                    $existing['image_type_id'] ?? $data['image_type_id'],
                    $id
                );
            }

            $fields = []; 
            $params = [];
            foreach ($data as $k => $v) { 
                if ($v !== null) { 
                    $fields[] = "$k = :$k"; 
                    $params[":$k"] = $v; 
                } 
            }
            if (empty($fields)) {
                throw new RuntimeException('No fields to update');
            }

            $params[':id'] = $id; 
            $params[':tenant_id'] = $tenantId;
            $stmt = $this->pdo->prepare("UPDATE {$this->table} SET " . implode(', ', $fields) . " WHERE id = :id AND tenant_id = :tenant_id");
            $stmt->execute($params);
            return $id;
        } else {
            // إضافة جديدة - غير رئيسي دائمًا
            $data['tenant_id'] = $tenantId;
            if ($userId) {
                $data['user_id'] = $userId;
            }
            
            $defaults = [
                'is_main' => 0, // دائمًا غير رئيسي عند الإضافة
                'sort_order' => 0,
                'visibility' => 'private',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            $data = array_merge($defaults, $data);
            
            // الحصول على اتصال PDO للإدراج المباشر
            $columns = implode(', ', array_keys($data));
            $placeholders = ':' . implode(', :', array_keys($data));
            $stmt = $this->pdo->prepare("INSERT INTO {$this->table} ($columns) VALUES ($placeholders)");
            
            // Bind values
            foreach ($data as $k => $v) {
                $stmt->bindValue(':' . $k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
            }
            
            $stmt->execute();
            $newId = (int)$this->pdo->lastInsertId();
            
            // إذا كان المالك ليس لديه أي صور، جعل هذه الصورة الرئيسية تلقائيًا
            if (!$this->getMainImage($tenantId, $data['owner_id'], $data['image_type_id'])) {
                $this->setMain($tenantId, $data['owner_id'], $data['image_type_id'], $newId, $userId);
            }
            
            return $newId;
        }
    }

    /* ===================== DELETE ===================== */
    public function delete(int $tenantId, int $id, ?int $userId = null): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM {$this->table} WHERE id = :id AND tenant_id = :tenant_id");
        return $stmt->execute([':id' => $id, ':tenant_id' => $tenantId]);
    }

    public function deleteMultiple(int $tenantId, array $ids): bool
    {
        if (empty($ids)) return false;
        
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $params = array_merge($ids, [$tenantId]);
        
        $stmt = $this->pdo->prepare("DELETE FROM {$this->table} WHERE id IN ($placeholders) AND tenant_id = ?");
        return $stmt->execute($params);
    }

    /* ===================== OWNER OPERATIONS ===================== */
    public function getByOwner(int $tenantId, int $ownerId, int $imageTypeId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT i.*, it.name as image_type_name 
             FROM {$this->table} i 
             LEFT JOIN {$this->imageTypesTable} it ON i.image_type_id = it.id 
             WHERE i.tenant_id = :tenant_id 
             AND i.owner_id = :owner_id 
             AND i.image_type_id = :image_type_id 
             ORDER BY i.is_main DESC, i.sort_order ASC, i.id DESC"
        );
        $stmt->execute([
            ':tenant_id' => $tenantId,
            ':owner_id' => $ownerId,
            ':image_type_id' => $imageTypeId
        ]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countByOwnerType(int $tenantId, int $ownerId, int $imageTypeId): int
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM {$this->table} 
             WHERE tenant_id = :tenant_id 
             AND owner_id = :owner_id 
             AND image_type_id = :image_type_id"
        );
        $stmt->execute([
            ':tenant_id' => $tenantId,
            ':owner_id' => $ownerId,
            ':image_type_id' => $imageTypeId
        ]);
        return (int)$stmt->fetchColumn();
    }

    public function getMainImage(int $tenantId, int $ownerId, int $imageTypeId): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM {$this->table} 
             WHERE tenant_id = :tenant_id 
             AND owner_id = :owner_id 
             AND image_type_id = :image_type_id 
             AND is_main = 1 
             LIMIT 1"
        );
        $stmt->execute([
            ':tenant_id' => $tenantId,
            ':owner_id' => $ownerId,
            ':image_type_id' => $imageTypeId
        ]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function deleteByOwner(int $tenantId, int $ownerId, int $imageTypeId, ?int $userId = null): int
    {
        $stmt = $this->pdo->prepare(
            "DELETE FROM {$this->table} 
             WHERE tenant_id = :tenant_id 
             AND owner_id = :owner_id 
             AND image_type_id = :image_type_id"
        );
        $stmt->execute([
            ':tenant_id' => $tenantId,
            ':owner_id' => $ownerId,
            ':image_type_id' => $imageTypeId
        ]);
        return $stmt->rowCount();
    }

    /* ===================== MAIN IMAGE OPERATIONS ===================== */
    public function setMain(int $tenantId, int $ownerId, int $imageTypeId, int $imageId, ?int $userId = null): bool
    {
        try {
            $this->pdo->beginTransaction();

            // إلغاء الصور الرئيسية الأخرى لنفس المالك والنوع
            $stmt = $this->pdo->prepare(
                "UPDATE {$this->table} SET is_main = 0 
                 WHERE tenant_id = :tenant_id 
                 AND owner_id = :owner_id 
                 AND image_type_id = :image_type_id 
                 AND id != :exclude_id"
            );
            $stmt->execute([
                ':tenant_id' => $tenantId,
                ':owner_id' => $ownerId,
                ':image_type_id' => $imageTypeId,
                ':exclude_id' => $imageId
            ]);

            // تعيين الصورة كرئيسية
            $stmt2 = $this->pdo->prepare(
                "UPDATE {$this->table} SET is_main = 1, updated_at = NOW() 
                 WHERE id = :id AND tenant_id = :tenant_id"
            );
            $res = $stmt2->execute([':id' => $imageId, ':tenant_id' => $tenantId]);

            $this->pdo->commit();
            return $res;
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    private function unsetOtherMainImages(int $tenantId, int $ownerId, int $imageTypeId, int $excludeId): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE {$this->table} SET is_main = 0 
             WHERE tenant_id = :tenant_id 
             AND owner_id = :owner_id 
             AND image_type_id = :image_type_id 
             AND id != :exclude_id"
        );
        $stmt->execute([
            ':tenant_id' => $tenantId,
            ':owner_id' => $ownerId,
            ':image_type_id' => $imageTypeId,
            ':exclude_id' => $excludeId
        ]);
    }

    /* ===================== DIRECT INSERT (FOR UPLOAD) ===================== */
    public function insertDirect(array $data): int
    {
        $columns = implode(', ', array_keys($data));
        $placeholders = ':' . implode(', :', array_keys($data));
        $stmt = $this->pdo->prepare("INSERT INTO {$this->table} ($columns) VALUES ($placeholders)");
        
        // Bind values with proper types
        foreach ($data as $k => $v) {
            $stmt->bindValue(':' . $k, $v, 
                is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR
            );
        }
        
        $stmt->execute();
        return (int)$this->pdo->lastInsertId();
    }

    /* ===================== IMAGE PROCESSING HELPERS ===================== */
    public function getImageProcessingSettings(int $imageTypeId): ?array
    {
        return $this->getImageType($imageTypeId);
    }
}
?>