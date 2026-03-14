<?php
declare(strict_types=1);

final class EntitiesAttributesService
{
    private PdoEntitiesAttributesRepository $repo;

    public function __construct(PdoEntitiesAttributesRepository $repo)
    {
        $this->repo = $repo;
    }

    /**
     * الحصول على قائمة الخصائص مع التصفية والترحيل
     */
    public function list(
        ?int $limit = null,
        ?int $offset = null,
        array $filters = [],
        string $orderBy = 'sort_order',
        string $orderDir = 'ASC',
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
     * الحصول على خاصية محددة
     */
    public function get(int $id, string $lang = 'ar'): ?array
    {
        return $this->repo->find($id, $lang);
    }

    /**
     * الحصول على خاصية بواسطة slug
     */
    public function getBySlug(string $slug, string $lang = 'ar'): ?array
    {
        return $this->repo->findBySlug($slug, $lang);
    }

    /**
     * إنشاء خاصية جديدة
     */
    public function create(array $data): int
    {
        EntitiesAttributesValidator::validateCreate($data);
        
        // حفظ الخاصية الرئيسية
        $attributeId = $this->repo->save($data);
        
        // حفظ الترجمة الرئيسية
        $defaultLang = $data['language_code'] ?? 'ar';
        $this->repo->saveTranslation($attributeId, $defaultLang, [
            'name' => $data['name'],
            'description' => $data['description'] ?? null
        ]);
        
        // حفظ الترجمات الإضافية
        if (isset($data['translations']) && is_array($data['translations'])) {
            foreach ($data['translations'] as $langCode => $translation) {
                if ($langCode !== $defaultLang) {
                    EntitiesAttributesValidator::validateLanguageCode($langCode);
                    $this->repo->saveTranslation($attributeId, $langCode, $translation);
                }
            }
        }
        
        return $attributeId;
    }

    /**
     * تحديث خاصية موجودة
     */
    public function update(int $id, array $data): void
    {
        // التحقق من وجود الخاصية
        $existing = $this->repo->find($id);
        if (!$existing) {
            throw new RuntimeException("Attribute not found");
        }
        
        EntitiesAttributesValidator::validateUpdate($data);
        
        // تحديث الخاصية الرئيسية
        $this->repo->save(array_merge(['id' => $id], $data));
        
        // تحديث الترجمة الرئيسية
        if (isset($data['name']) || isset($data['description'])) {
            $defaultLang = $data['language_code'] ?? 'ar';
            $this->repo->saveTranslation($id, $defaultLang, [
                'name' => $data['name'] ?? $existing['name'],
                'description' => $data['description'] ?? $existing['description']
            ]);
        }
        
        // تحديث الترجمات الإضافية
        if (isset($data['translations']) && is_array($data['translations'])) {
            foreach ($data['translations'] as $langCode => $translation) {
                EntitiesAttributesValidator::validateLanguageCode($langCode);
                $this->repo->saveTranslation($id, $langCode, $translation);
            }
        }
    }

    /**
     * حذف خاصية
     */
    public function delete(int $id): void
    {
        // التحقق من وجود الخاصية
        if (!$this->repo->find($id)) {
            throw new RuntimeException("Attribute not found");
        }
        
        $this->repo->delete($id);
    }

    /**
     * الحصول على ترجمات الخاصية
     */
    public function getTranslations(int $id): array
    {
        if (!$this->repo->find($id)) {
            throw new RuntimeException("Attribute not found");
        }
        
        return $this->repo->getTranslations($id);
    }

    /**
     * الحصول على اللغات المتاحة
     */
    public function getLanguages(): array
    {
        return $this->repo->getLanguages();
    }
}