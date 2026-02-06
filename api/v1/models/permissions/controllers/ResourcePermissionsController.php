<?php
declare(strict_types=1);

namespace App\Models\Permissions\Controllers;

use App\Models\Permissions\Services\ResourcePermissionsService;
use InvalidArgumentException;

/**
 * Controller for resource_permissions
 * returns arrays / does not throw on partial bulk errors
 */
class ResourcePermissionsController
{
    private ResourcePermissionsService $service;

    public function __construct(ResourcePermissionsService $service)
    {
        $this->service = $service;
    }

    public function list(?int $tenantId, ?int $roleId): array
    {
        return $this->service->list($tenantId, $roleId);
    }

    public function get(int $id): array
    {
        return $this->service->get($id);
    }

    public function store(array $data): array
    {
        $id = $this->service->create($data);
        return ['inserted_id' => $id];
    }

    public function update(array $data): array
    {
        // دعم التحديث الفردي أو bulk (items/updates)
        $items = $data['items'] ?? ($data['updates'] ?? null);

        if (is_array($items)) {
            $summary = [
                'inserted' => 0,
                'updated'  => 0,
                'skipped'  => 0,
                'errors'   => [],
            ];

            foreach ($items as $idx => $item) {
                try {
                    if (!empty($item['id'])) {
                        $ok = $this->service->updateSingle($item);
                        $summary['updated'] += $ok ? 1 : 0;
                        if (!$ok) $summary['skipped']++;
                    } else {
                        $res = $this->service->bulkUpsert([$item]);
                        $summary['inserted'] += $res['inserted'] ?? 0;
                        $summary['updated']  += $res['updated'] ?? 0;
                        $summary['skipped']  += $res['skipped'] ?? 0;
                        if (!empty($res['errors'])) {
                            foreach ($res['errors'] as $err) {
                                $summary['errors'][] = "Index {$idx}: {$err}";
                            }
                        }
                    }
                } catch (\Throwable $e) {
                    $summary['errors'][] = "Index {$idx}: {$e->getMessage()}";
                }
            }

            return $summary;
        }

        // تحديث فردي
        if (!empty($data['id'])) {
            $ok = $this->service->updateSingle($data);
            return ['updated' => $ok ? 1 : 0];
        }

        throw new InvalidArgumentException('Invalid update payload; expected id or items/updates array');
    }

    public function delete(array $data): array
    {
        if (empty($data['id'])) throw new InvalidArgumentException('id is required for delete');
        $ok = $this->service->delete((int)$data['id']);
        return ['deleted' => $ok];
    }
}
