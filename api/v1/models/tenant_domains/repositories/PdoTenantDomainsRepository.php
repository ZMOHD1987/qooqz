<?php
declare(strict_types=1);

require_once __DIR__ . '/../Contracts/TenantDomainsRepositoryInterface.php';

/**
 * PdoTenantDomainsRepository
 *
 * PDO-backed persistence for the tenant_domains table.
 * Implements TenantDomainsRepositoryInterface.
 */
final class PdoTenantDomainsRepository implements TenantDomainsRepositoryInterface
{
    private PDO $pdo;
    private const TABLE = 'tenant_domains';

    private const ALLOWED_TYPES       = ['primary', 'custom', 'subdomain', 'alias'];
    private const ALLOWED_SSL_STATUSES = ['none', 'pending', 'active', 'failed'];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // ─────────────────────────────────────────────────────────────
    // READ
    // ─────────────────────────────────────────────────────────────

    public function allByTenant(int $tenantId, array $filters = [], int $limit = 50, int $offset = 0): array
    {
        $where  = 'WHERE td.tenant_id = :tenant_id';
        $params = [':tenant_id' => $tenantId];

        if (!empty($filters['type']) && in_array($filters['type'], self::ALLOWED_TYPES, true)) {
            $where .= ' AND td.type = :type';
            $params[':type'] = $filters['type'];
        }
        if (!empty($filters['ssl_status']) && in_array($filters['ssl_status'], self::ALLOWED_SSL_STATUSES, true)) {
            $where .= ' AND td.ssl_status = :ssl_status';
            $params[':ssl_status'] = $filters['ssl_status'];
        }
        if (isset($filters['is_verified']) && $filters['is_verified'] !== '') {
            $where .= ' AND td.is_verified = :is_verified';
            $params[':is_verified'] = (int)(bool)$filters['is_verified'];
        }
        if (!empty($filters['search'])) {
            $where .= ' AND td.domain LIKE :search';
            $params[':search'] = '%' . $filters['search'] . '%';
        }

        $stmt = $this->pdo->prepare("
            SELECT td.*,
                   t.name AS tenant_name
            FROM " . self::TABLE . " td
            JOIN tenants t ON td.tenant_id = t.id
            {$where}
            ORDER BY td.type = 'primary' DESC, td.created_at ASC
            LIMIT :limit OFFSET :offset
        ");

        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit',  $limit,  PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countByTenant(int $tenantId, array $filters = []): int
    {
        $where  = 'WHERE td.tenant_id = :tenant_id';
        $params = [':tenant_id' => $tenantId];

        if (!empty($filters['type']) && in_array($filters['type'], self::ALLOWED_TYPES, true)) {
            $where .= ' AND td.type = :type';
            $params[':type'] = $filters['type'];
        }
        if (!empty($filters['ssl_status']) && in_array($filters['ssl_status'], self::ALLOWED_SSL_STATUSES, true)) {
            $where .= ' AND td.ssl_status = :ssl_status';
            $params[':ssl_status'] = $filters['ssl_status'];
        }
        if (isset($filters['is_verified']) && $filters['is_verified'] !== '') {
            $where .= ' AND td.is_verified = :is_verified';
            $params[':is_verified'] = (int)(bool)$filters['is_verified'];
        }
        if (!empty($filters['search'])) {
            $where .= ' AND td.domain LIKE :search';
            $params[':search'] = '%' . $filters['search'] . '%';
        }

        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) AS total
            FROM " . self::TABLE . " td
            {$where}
        ");
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();
        return (int)($stmt->fetchColumn() ?? 0);
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT td.*, t.name AS tenant_name
            FROM " . self::TABLE . " td
            JOIN tenants t ON td.tenant_id = t.id
            WHERE td.id = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findByDomain(string $domain): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT td.*, t.name AS tenant_name
            FROM " . self::TABLE . " td
            JOIN tenants t ON td.tenant_id = t.id
            WHERE td.domain = :domain
            LIMIT 1
        ");
        $stmt->execute([':domain' => $domain]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function domainExists(string $domain, ?int $excludeId = null): bool
    {
        $sql    = "SELECT COUNT(*) FROM " . self::TABLE . " WHERE domain = :domain";
        $params = [':domain' => $domain];
        if ($excludeId !== null) {
            $sql .= ' AND id != :exclude_id';
            $params[':exclude_id'] = $excludeId;
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn() > 0;
    }

    public function findPrimary(int $tenantId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT td.*, t.name AS tenant_name
            FROM " . self::TABLE . " td
            JOIN tenants t ON td.tenant_id = t.id
            WHERE td.tenant_id = :tenant_id AND td.type = 'primary'
            LIMIT 1
        ");
        $stmt->execute([':tenant_id' => $tenantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    // ─────────────────────────────────────────────────────────────
    // WRITE
    // ─────────────────────────────────────────────────────────────

    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO " . self::TABLE . "
                (tenant_id, domain, type, is_verified, verification_token,
                 verified_at, ssl_status, ssl_expires_at, redirect_to_primary, meta)
            VALUES
                (:tenant_id, :domain, :type, :is_verified, :verification_token,
                 :verified_at, :ssl_status, :ssl_expires_at, :redirect_to_primary, :meta)
        ");

        $stmt->execute([
            ':tenant_id'           => (int)$data['tenant_id'],
            ':domain'              => $data['domain'],
            ':type'                => $data['type'] ?? 'custom',
            ':is_verified'         => (int)(bool)($data['is_verified'] ?? false),
            ':verification_token'  => $data['verification_token'] ?? null,
            ':verified_at'         => $data['verified_at'] ?? null,
            ':ssl_status'          => $data['ssl_status'] ?? 'none',
            ':ssl_expires_at'      => $data['ssl_expires_at'] ?? null,
            ':redirect_to_primary' => (int)(bool)($data['redirect_to_primary'] ?? false),
            ':meta'                => isset($data['meta']) ? json_encode($data['meta'], JSON_UNESCAPED_UNICODE) : null,
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $allowed = [
            'domain', 'type', 'is_verified', 'verification_token',
            'verified_at', 'ssl_status', 'ssl_expires_at', 'redirect_to_primary', 'meta',
        ];

        $sets   = [];
        $params = [':id' => $id];

        foreach ($allowed as $col) {
            if (!array_key_exists($col, $data)) continue;
            $sets[] = "{$col} = :{$col}";
            $val = $data[$col];
            if ($col === 'meta' && is_array($val)) {
                $val = json_encode($val, JSON_UNESCAPED_UNICODE);
            }
            if (in_array($col, ['is_verified', 'redirect_to_primary'], true)) {
                $val = (int)(bool)$val;
            }
            $params[":{$col}"] = $val;
        }

        if (empty($sets)) return false;

        $stmt = $this->pdo->prepare(
            'UPDATE ' . self::TABLE . ' SET ' . implode(', ', $sets) . ' WHERE id = :id'
        );
        $stmt->execute($params);
        return $stmt->rowCount() > 0;
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM ' . self::TABLE . ' WHERE id = :id');
        $stmt->execute([':id' => $id]);
        return $stmt->rowCount() > 0;
    }

    public function markVerified(int $id, string $verifiedAt): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE " . self::TABLE . "
            SET is_verified = 1, verified_at = :verified_at, verification_token = NULL
            WHERE id = :id
        ");
        $stmt->execute([':verified_at' => $verifiedAt, ':id' => $id]);
        return $stmt->rowCount() > 0;
    }

    public function updateSslStatus(int $id, string $status, ?string $expiresAt = null): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE " . self::TABLE . "
            SET ssl_status = :ssl_status, ssl_expires_at = :ssl_expires_at
            WHERE id = :id
        ");
        $stmt->execute([
            ':ssl_status'    => $status,
            ':ssl_expires_at'=> $expiresAt,
            ':id'            => $id,
        ]);
        return $stmt->rowCount() > 0;
    }
}
