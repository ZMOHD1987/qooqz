<?php
declare(strict_types=1);

final class EntitiesAttributeValuesService
{
    private PdoEntitiesAttributeValuesRepository $repo;

    public function __construct(PdoEntitiesAttributeValuesRepository $repo)
    {
        $this->repo = $repo;
    }

    /**
     * الحصول على قائمة قيم الخصائص مع التصفية والترحيل
     */
    public function list(
        ?int $limit = null,
        ?int $offset = null,
        array $filters = [],
        string $orderBy = 'id',
        string $orderDir = 'DESC',
        string $lang = 'ar'
    ): array {
        $items = $this->repo->all($limit, $offset, $filters, $orderBy, $orderDir, $lang);
        $total = $this->repo->count($filters, $lang);

        return [
            'items' => $items,
            'meta'  => [
                'total'       => $total,
                'limit'       => $limit,
                'offset'      => $offset,
                'total_pages' => $limit > 0 ? (int)ceil($total / $limit) : 0
            ]
        ];
    }

    /**
     * الحصول على قيمة محددة
     */
    public function get(int $id, string $lang = 'ar'): ?array
    {
        return $this->repo->find($id, $lang);
    }

    /**
     * الحصول على قيمة بواسطة الكيان والخاصية
     */
    public function getByEntityAndAttribute(int $entityId, int $attributeId, string $lang = 'ar'): ?array
    {
        return $this->repo->findByEntityAndAttribute($entityId, $attributeId, $lang);
    }

    /**
     * الحصول على جميع قيم كيان محدد
     */
    public function getEntityValues(int $entityId, string $lang = 'ar'): array
    {
        return $this->repo->getEntityValues($entityId, $lang);
    }

    /**
     * الحصول على جميع قيم خاصية محددة
     */
    public function getAttributeValues(int $attributeId, string $lang = 'ar'): array
    {
        return $this->repo->getAttributeValues($attributeId, $lang);
    }

    /**
     * إنشاء قيمة جديدة
     */
    public function create(array $data): int
    {
        EntitiesAttributeValuesValidator::validateCreate($data);
        return $this->repo->save($data);
    }

    /**
     * تحديث قيمة موجودة
     */
    public function update(int $id, array $data): void
    {
        // التحقق من وجود القيمة
        $existing = $this->repo->find($id);
        if (!$existing) {
            throw new RuntimeException("Attribute value not found");
        }
        
        EntitiesAttributeValuesValidator::validateUpdate($data);
        $this->repo->save(array_merge(['id' => $id], $data));
    }

    /**
     * حفظ جماعي لقيم كيان
     */
    public function saveEntityValues(int $entityId, array $values): array
    {
        EntitiesAttributeValuesValidator::validateBulkSave($entityId, $values);
        return $this->repo->saveEntityValues($entityId, $values);
    }

    /**
     * حذف قيمة
     */
    public function delete(int $id): void
    {
        // التحقق من وجود القيمة
        if (!$this->repo->find($id)) {
            throw new RuntimeException("Attribute value not found");
        }
        
        $this->repo->delete($id);
    }

    /**
     * حذف جميع قيم كيان
     */
    public function deleteEntityValues(int $entityId): void
    {
        $this->repo->deleteEntityValues($entityId);
    }

    /**
     * حذف جميع قيم خاصية
     */
    public function deleteAttributeValues(int $attributeId): void
    {
        $this->repo->deleteAttributeValues($attributeId);
    }

    /**
     * الحصول على إحصائيات قيم الخصائص
     */
    public function getStatistics(): array
    {
        return $this->repo->getStatistics();
    }
}