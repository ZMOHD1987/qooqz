<?php
declare(strict_types=1);

final class ImagesController
{
    private ImagesService $service;

    public function __construct(ImagesService $service)
    {
        $this->service = $service;
    }

    /* ===================== LIST / GET ===================== */
    public function list(int $tenantId): array
    {
        // إذا كان هناك id في GET، قم بإرجاع صورة واحدة
        $id = isset($_GET['id']) ? (int)$_GET['id'] : null;
        if ($id > 0) {
            return $this->get($tenantId, $id);
        }

        // استخراج معاملات التصفية والترتيب
        $filename     = $_GET['q'] ?? null;
        $ownerId      = isset($_GET['owner_id']) ? (int)$_GET['owner_id'] : null;
        $imageTypeId  = isset($_GET['image_type_id']) ? (int)$_GET['image_type_id'] : null;
        $visibility   = $_GET['visibility'] ?? null;
        $userId       = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;
        $page         = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        $limit        = isset($_GET['limit']) ? max(1, (int)$_GET['limit']) : 25;

        // استدعاء service للحصول على البيانات
        $result = $this->service->list(
            $tenantId,
            $filename,
            $ownerId,
            $imageTypeId,
            $visibility,
            $userId,
            $page,
            $limit
        );

        // إرجاع البيانات مع metadata للتقسيم
        return [
            'data' => $result['data'],
            'meta' => [
                'total'     => $result['total'],
                'page'      => $page,
                'per_page'  => $limit,
                'last_page' => (int)ceil($result['total'] / $limit)
            ]
        ];
    }

    public function get(int $tenantId, int $id): array
    {
        return $this->service->get($tenantId, $id);
    }

    public function getByOwner(int $tenantId, int $ownerId, int $imageTypeId): array
    {
        return $this->service->getByOwner($tenantId, $ownerId, $imageTypeId);
    }

    public function getMain(int $tenantId, int $ownerId, int $imageTypeId): array
    {
        $image = $this->service->getMainImage($tenantId, $ownerId, $imageTypeId);
        if (!$image) {
            return ['message' => 'No main image found'];
        }
        return $image;
    }

    public function getImageTypes(): array
    {
        return $this->service->getImageTypes();
    }

    /* ===================== CREATE ===================== */
    public function create(int $tenantId, array $data): array
    {
        // إزالة الحقول غير ضرورية
        unset($data['entity']);

        $id = $this->service->save($tenantId, $data, $data['user_id'] ?? null);
        return $this->service->get($tenantId, $id);
    }

    /* ===================== UPLOAD ===================== */
    public function upload(int $tenantId, array $data, array $files, ?int $userId = null): array
    {
        $entity = $data['entity'] ?? 'general';
        $data['entity'] = $entity;
        $data['tenant_id'] = $tenantId;
        if (!isset($data['user_id']) && $userId) {
            $data['user_id'] = $userId;
        }

        $uploaded = $this->service->upload($tenantId, $data, $files, $userId);

        return [
            'success' => true,
            'message' => count($uploaded) . ' image(s) uploaded successfully',
            'data' => $uploaded
        ];
    }

    /* ===================== UPDATE ===================== */
    public function update(int $tenantId, array $data): array
    {
        $id = $data['id'] ?? ($_GET['id'] ?? null);
        if (!$id || (int)$id <= 0) {
            throw new InvalidArgumentException('ID is required');
        }

        unset($data['entity']);

        $id = $this->service->save($tenantId, $data, $data['user_id'] ?? null);
        return $this->service->get($tenantId, $id);
    }

    /* ===================== DELETE ===================== */
    public function delete(int $tenantId, array $data): array
    {
        $id = $data['id'] ?? ($_GET['id'] ?? null);
        if (!$id || (int)$id <= 0) {
            throw new InvalidArgumentException('ID is required');
        }

        $this->service->delete($tenantId, (int)$id, $data['user_id'] ?? null);

        return ['success' => true, 'message' => 'Image deleted successfully'];
    }

    public function deleteMultiple(int $tenantId, array $data): array
    {
        $ids = $data['ids'] ?? explode(',', ($_GET['ids'] ?? ''));
        if (empty($ids)) {
            throw new InvalidArgumentException('No IDs provided');
        }

        $ids = array_map('intval', $ids);

        $this->service->deleteMultiple($tenantId, $ids, $data['user_id'] ?? null);

        return ['success' => true, 'message' => count($ids) . ' image(s) deleted successfully'];
    }

    public function deleteByOwner(int $tenantId, int $ownerId, int $imageTypeId, ?int $userId = null): array
    {
        $this->service->deleteByOwner($tenantId, $ownerId, $imageTypeId, $userId);
        return ['success' => true, 'message' => 'All images deleted successfully'];
    }

    /* ===================== MAIN IMAGE ===================== */
    public function setMain(int $tenantId, array $data): array
    {
        $ownerId     = $data['owner_id'] ?? ($_GET['owner_id'] ?? null);
        $imageTypeId = $data['image_type_id'] ?? ($_GET['image_type_id'] ?? null);
        $imageId     = $data['image_id'] ?? ($_GET['image_id'] ?? null);

        if (!$ownerId || !$imageTypeId || !$imageId) {
            throw new InvalidArgumentException('Owner ID, image type ID, and image ID are required');
        }

        $this->service->setMain($tenantId, (int)$ownerId, (int)$imageTypeId, (int)$imageId, $data['user_id'] ?? null);

        return ['success' => true, 'message' => 'Main image set successfully'];
    }

    /* ===================== BULK OPERATIONS ===================== */
    public function updateSortOrder(int $tenantId, array $data): array
    {
        if (empty($data['images'])) {
            throw new InvalidArgumentException('Images array is required');
        }

        $updated = 0;
        foreach ($data['images'] as $imageData) {
            if (empty($imageData['id'])) continue;
            
            $updateData = [
                'id' => (int)$imageData['id'],
                'sort_order' => (int)($imageData['sort_order'] ?? 0)
            ];
            
            $this->service->save($tenantId, $updateData);
            $updated++;
        }

        return ['success' => true, 'message' => "{$updated} images updated successfully"];
    }

    public function updateVisibility(int $tenantId, array $data): array
    {
        $ids = $data['ids'] ?? [];
        $visibility = $data['visibility'] ?? 'private';

        if (empty($ids)) {
            throw new InvalidArgumentException('IDs array is required');
        }

        if (!in_array($visibility, ['private', 'public'])) {
            throw new InvalidArgumentException('Visibility must be private or public');
        }

        $updated = 0;
        foreach ($ids as $id) {
            $updateData = [
                'id' => (int)$id,
                'visibility' => $visibility
            ];
            
            $this->service->save($tenantId, $updateData);
            $updated++;
        }

        return ['success' => true, 'message' => "{$updated} images updated to {$visibility}"];
    }
}
?>