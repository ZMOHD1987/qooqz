<?php
declare(strict_types=1);

namespace App\Models\Permissions\Validators;

use PDO;
use InvalidArgumentException;

/**
 * Validator for resource_permissions payloads
 */
class ResourcePermissionsValidator
{
    private PDO $pdo;
    private array $flagCols = [
        'can_view_all','can_view_own','can_view_tenant',
        'can_create','can_edit_all','can_edit_own',
        'can_delete_all','can_delete_own'
    ];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Validate one upsert item and normalize
     */
    public function validateUpsertItem(array $item): array
    {
        $out = [];

        if (isset($item['id'])) {
            if (!is_numeric($item['id']) || (int)$item['id'] <= 0) {
                throw new InvalidArgumentException('Invalid id');
            }
            $out['id'] = (int)$item['id'];
        }

        // For new items require resource_type or permission_id
        if (!isset($out['id']) && empty($item['resource_type']) && empty($item['permission_id'])) {
            throw new InvalidArgumentException('resource_type or permission_id required for new item');
        }

        if (isset($item['permission_id'])) {
            if (!is_numeric($item['permission_id']) || (int)$item['permission_id'] <= 0) {
                throw new InvalidArgumentException('Invalid permission_id');
            }
            $out['permission_id'] = (int)$item['permission_id'];
        }

        if (array_key_exists('resource_type', $item)) {
            $out['resource_type'] = (string)$item['resource_type'];
        }

        if (array_key_exists('role_id', $item)) {
            if ($item['role_id'] === null || $item['role_id'] === '') {
                $out['role_id'] = null;
            } elseif (!is_numeric($item['role_id']) || (int)$item['role_id'] <= 0) {
                throw new InvalidArgumentException('Invalid role_id');
            } else {
                $out['role_id'] = (int)$item['role_id'];
            }
        }

        if (array_key_exists('tenant_id', $item)) {
            // FIXED: Accept 0 as valid (for global tenant)
            if ($item['tenant_id'] === null || $item['tenant_id'] === '' || $item['tenant_id'] === 0 || $item['tenant_id'] === '0') {
                $out['tenant_id'] = null;  // 0 means global (no tenant)
            } elseif (!is_numeric($item['tenant_id']) || (int)$item['tenant_id'] < 0) {
                throw new InvalidArgumentException('Invalid tenant_id');
            } else {
                $out['tenant_id'] = (int)$item['tenant_id'];
            }
        }

        foreach ($this->flagCols as $f) {
            if (isset($item[$f])) {
                $out[$f] = (int)$item[$f] ? 1 : 0;
            }
        }

        return $out;
    }

    public function validateBulkItems(array $items): array
    {
        if (empty($items)) throw new InvalidArgumentException('items must be a non-empty array');
        $normalized = [];
        foreach ($items as $i => $it) {
            if (!is_array($it)) throw new InvalidArgumentException("Each item must be an array at index {$i}");
            $normalized[] = $this->validateUpsertItem($it);
        }
        return $normalized;
    }

    public function validateDeleteId($id): int
    {
        if (!is_numeric($id) || (int)$id <= 0) throw new InvalidArgumentException('Invalid id for deletion');
        return (int)$id;
    }
}