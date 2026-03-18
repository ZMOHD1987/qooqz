<?php
declare(strict_types=1);

require_once __DIR__ . '/../repositories/PdoTenantDomainsRepository.php';
require_once __DIR__ . '/../validators/TenantDomainsValidator.php';

/**
 * TenantDomainsService
 *
 * Business logic for the tenant_domains feature.
 * This layer is the single source of truth for domain management rules.
 *
 * Rules enforced here (not in the repository):
 *  - Only one 'primary' domain per tenant is allowed.
 *  - A domain string must be globally unique.
 *  - Downgrading a primary domain is blocked; promote another first.
 *  - Bad words are checked before persisting.
 */
final class TenantDomainsService
{
    private PdoTenantDomainsRepository $repo;

    public function __construct(PdoTenantDomainsRepository $repo)
    {
        $this->repo = $repo;
    }

    // ─────────────────────────────────────────────────────────────
    // READ
    // ─────────────────────────────────────────────────────────────

    public function list(int $tenantId, array $filters = [], int $limit = 50, int $offset = 0): array
    {
        $items = $this->repo->allByTenant($tenantId, $filters, $limit, $offset);
        $total = $this->repo->countByTenant($tenantId, $filters);

        return [
            'items' => $items,
            'meta'  => [
                'total'    => $total,
                'limit'    => $limit,
                'offset'   => $offset,
                'has_more' => ($offset + count($items)) < $total,
            ],
        ];
    }

    public function get(int $id): array
    {
        $row = $this->repo->find($id);
        if (!$row) {
            throw new RuntimeException('Domain record not found');
        }
        return $row;
    }

    // ─────────────────────────────────────────────────────────────
    // WRITE
    // ─────────────────────────────────────────────────────────────

    public function create(array $data): array
    {
        $errors = TenantDomainsValidator::validateCreate($data);
        if (!empty($errors)) {
            throw new InvalidArgumentException(json_encode($errors, JSON_UNESCAPED_UNICODE));
        }

        // Normalise domain (strip protocol, trailing slash, lowercase)
        $data['domain'] = $this->normaliseDomain($data['domain']);

        // Uniqueness check
        if ($this->repo->domainExists($data['domain'])) {
            throw new InvalidArgumentException('This domain is already registered');
        }

        // Only one primary per tenant
        $type = $data['type'] ?? 'custom';
        if ($type === 'primary') {
            $existing = $this->repo->findPrimary((int)$data['tenant_id']);
            if ($existing) {
                throw new InvalidArgumentException(
                    'Tenant already has a primary domain (ID ' . $existing['id'] . '). ' .
                    'Promote the new domain instead of creating a second primary.'
                );
            }
        }

        $id  = $this->repo->create($data);
        $row = $this->repo->find($id);

        if (!$row) {
            throw new RuntimeException('Failed to retrieve created domain record');
        }

        // Keep tenants.domain in sync when a primary domain is created
        if (($data['type'] ?? 'custom') === 'primary') {
            $this->repo->syncTenantCanonicalDomain((int)$data['tenant_id'], $data['domain']);
        }

        return $row;
    }

    public function update(int $id, array $data): array
    {
        $existing = $this->repo->find($id);
        if (!$existing) {
            throw new RuntimeException('Domain record not found');
        }

        $errors = TenantDomainsValidator::validateUpdate($data);
        if (!empty($errors)) {
            throw new InvalidArgumentException(json_encode($errors, JSON_UNESCAPED_UNICODE));
        }

        // Normalise domain if being changed
        if (isset($data['domain'])) {
            $data['domain'] = $this->normaliseDomain($data['domain']);
            if ($this->repo->domainExists($data['domain'], $id)) {
                throw new InvalidArgumentException('This domain is already registered');
            }
        }

        // Guard: cannot downgrade a primary domain (must promote another first)
        if (isset($data['type']) && $data['type'] !== 'primary' && $existing['type'] === 'primary') {
            throw new InvalidArgumentException(
                'Cannot downgrade the primary domain type. Promote another domain to primary first.'
            );
        }

        // Guard: two primaries per tenant
        if (isset($data['type']) && $data['type'] === 'primary' && $existing['type'] !== 'primary') {
            $currentPrimary = $this->repo->findPrimary((int)$existing['tenant_id']);
            if ($currentPrimary && $currentPrimary['id'] !== $id) {
                throw new InvalidArgumentException(
                    'Tenant already has a primary domain (ID ' . $currentPrimary['id'] . ').'
                );
            }
        }

        $this->repo->update($id, $data);
        $row = $this->repo->find($id);

        if (!$row) {
            throw new RuntimeException('Failed to retrieve updated domain record');
        }

        // Sync tenants.domain when the primary domain's value or type changes
        $isPrimaryNow = $row['type'] === 'primary';
        $wasPrimary   = $existing['type'] === 'primary';
        if ($isPrimaryNow) {
            // Primary domain created or its domain string changed
            $this->repo->syncTenantCanonicalDomain((int)$row['tenant_id'], $row['domain']);
        } elseif ($wasPrimary && !$isPrimaryNow) {
            // Type was downgraded from primary – clear tenants.domain
            $this->repo->syncTenantCanonicalDomain((int)$row['tenant_id'], null);
        }

        return $row;
    }

    public function delete(int $id): void
    {
        $row = $this->repo->find($id);
        if (!$row) {
            throw new RuntimeException('Domain record not found');
        }
        if ($row['type'] === 'primary') {
            throw new InvalidArgumentException(
                'Cannot delete the primary domain. Promote another domain first.'
            );
        }
        if (!$this->repo->delete($id)) {
            throw new RuntimeException('Failed to delete domain record');
        }
    }

    public function markVerified(int $id): array
    {
        $row = $this->repo->find($id);
        if (!$row) {
            throw new RuntimeException('Domain record not found');
        }
        $this->repo->markVerified($id, date('Y-m-d H:i:s'));
        return $this->repo->find($id) ?? $row;
    }

    public function updateSslStatus(int $id, string $status, ?string $expiresAt = null): array
    {
        $row = $this->repo->find($id);
        if (!$row) {
            throw new RuntimeException('Domain record not found');
        }
        $this->repo->updateSslStatus($id, $status, $expiresAt);
        return $this->repo->find($id) ?? $row;
    }

    // ─────────────────────────────────────────────────────────────
    // HELPERS
    // ─────────────────────────────────────────────────────────────

    private function normaliseDomain(string $domain): string
    {
        // Strip protocol
        $domain = preg_replace('#^https?://#i', '', trim($domain)) ?? $domain;
        // Strip trailing slash and path
        $domain = strtolower(explode('/', rtrim($domain, '/'))[0]);
        // Strip port
        $domain = explode(':', $domain)[0];
        return $domain;
    }
}
