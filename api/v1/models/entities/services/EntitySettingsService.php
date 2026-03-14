<?php
declare(strict_types=1);

final class EntitySettingsService
{
    private PdoEntitySettingsRepository $repo;

    public function __construct(PdoEntitySettingsRepository $repo)
    {
        $this->repo = $repo;
    }

    public function list(
        ?int $limit = null,
        ?int $offset = null,
        array $filters = [],
        string $orderBy = 'entity_id',
        string $orderDir = 'DESC'
    ): array {
        $items = $this->repo->all($limit, $offset, $filters, $orderBy, $orderDir);
        $total = $this->repo->count($filters);

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

    public function count(array $filters = []): int
    {
        return $this->repo->count($filters);
    }

    public function get(int $entityId): ?array
    {
        return $this->repo->find($entityId);
    }

    public function create(int $entityId, array $data): bool
    {
        // التحقق من عدم وجود إعدادات مسبقة لنفس الكيان
        if ($this->get($entityId)) {
            throw new RuntimeException("Entity settings already exist for entity ID: $entityId");
        }
        
        // Remove entity_id from data since save() handles it as a separate parameter
        unset($data['entity_id']);
        return $this->repo->save($entityId, $data);
    }

    /**
     * Create or update entity settings (upsert via repository save)
     */
    public function update(int $entityId, array $data): bool
    {
        // Remove entity_id from data since save() handles it as a separate parameter
        unset($data['entity_id']);
        // save() handles both insert and update (upsert)
        return $this->repo->save($entityId, $data);
    }

    public function delete(int $entityId): bool
    {
        // التحقق من وجود الإعدادات قبل الحذف
        if (!$this->get($entityId)) {
            throw new RuntimeException("Entity settings not found for entity ID: $entityId");
        }
        
        return $this->repo->delete($entityId);
    }

    /**
     * تبديل حالة القيمة المنطقية (toggle)
     */
    public function toggle(int $entityId, string $field): bool
    {
        $allowedToggleFields = [
            'auto_accept_orders', 'allow_cod', 'allow_online_booking',
            'booking_cancellation_allowed', 'allow_preorders', 'is_visible',
            'maintenance_mode', 'show_reviews', 'show_contact_info',
            'featured_in_app', 'allow_multiple_payment_methods'
        ];

        if (!in_array($field, $allowedToggleFields, true)) {
            throw new InvalidArgumentException("Field '$field' cannot be toggled");
        }

        $currentSettings = $this->get($entityId);
        if (!$currentSettings) {
            throw new RuntimeException("Entity settings not found for entity ID: $entityId");
        }
        
        $newValue = $currentSettings[$field] ? 0 : 1;
        return $this->repo->save($entityId, [$field => $newValue]);
    }
}