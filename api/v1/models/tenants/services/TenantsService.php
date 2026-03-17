<?php
declare(strict_types=1);

require_once __DIR__ . '/../repositories/PdoTenantsRepository.php';
require_once __DIR__ . '/../validators/TenantsValidator.php';

/**
 * TenantsService
 *
 * Business logic layer for tenant management.
 *
 * Integrations
 * ────────────
 * • audit_logs : every mutation (create / update / delete / bulk-status) is
 *   written to the audit_logs table via PdoAuditLogsRepository (optional –
 *   gracefully no-ops when the repository is not injected).
 * • bad_words   : tenant name and domain are checked against the bad-word list
 *   before any create or update is persisted.
 */
final class TenantsService
{
    private PdoTenantsRepository $repo;
    private TenantsValidator     $validator;

    /** @var object|null PdoAuditLogsRepository (duck-typed to avoid hard dep) */
    private ?object $auditRepo;

    /** @var object|null BadWordsService (duck-typed to avoid hard dep) */
    private ?object $badWordsService;

    public function __construct(
        PdoTenantsRepository $repo,
        TenantsValidator     $validator,
        ?object $auditRepo       = null,
        ?object $badWordsService = null
    ) {
        $this->repo            = $repo;
        $this->validator       = $validator;
        $this->auditRepo       = $auditRepo;
        $this->badWordsService = $badWordsService;
    }

    // ─────────────────────────────────────────────────────────────
    // READ
    // ─────────────────────────────────────────────────────────────

    public function list(int $perPage = 10, int $offset = 0, array $filters = []): array
    {
        $filterErrors = TenantsValidator::validateFilters($filters);
        if (!empty($filterErrors)) {
            throw new InvalidArgumentException(
                'Invalid filters: ' . json_encode($filterErrors, JSON_UNESCAPED_UNICODE)
            );
        }
        return $this->repo->all($perPage, $offset, $filters);
    }

    public function count(array $filters = []): int
    {
        $filterErrors = TenantsValidator::validateFilters($filters);
        if (!empty($filterErrors)) {
            throw new InvalidArgumentException(
                'Invalid filters: ' . json_encode($filterErrors, JSON_UNESCAPED_UNICODE)
            );
        }
        return $this->repo->count($filters);
    }

    public function get(int $id): array
    {
        $row = $this->repo->find($id);
        if (!$row) {
            throw new RuntimeException('Tenant not found');
        }
        return $row;
    }

    public function getByDomain(string $domain): array
    {
        $row = $this->repo->findByDomain($domain);
        if (!$row) {
            throw new RuntimeException('Tenant not found');
        }
        return $row;
    }

    public function getActive(): array
    {
        return $this->repo->findActive();
    }

    public function getStats(): array
    {
        return $this->repo->getStats();
    }

    // ─────────────────────────────────────────────────────────────
    // WRITE
    // ─────────────────────────────────────────────────────────────

    public function create(array $data, ?int $userId = null): array
    {
        // Structural validation
        $errors = TenantsValidator::validate($data, false);
        if (!empty($errors)) {
            throw new InvalidArgumentException(json_encode($errors, JSON_UNESCAPED_UNICODE));
        }

        // Bad-words check
        $this->assertNoBadWords('name', $data['name'] ?? '');
        if (!empty($data['domain'])) {
            $this->assertNoBadWords('domain', $data['domain']);
        }

        // Owner existence
        if (!$this->repo->userExists((int)$data['owner_user_id'])) {
            throw new InvalidArgumentException('Owner user does not exist');
        }

        // Domain uniqueness
        if (!empty($data['domain']) && $this->repo->domainExists($data['domain'])) {
            throw new InvalidArgumentException('Domain is already in use');
        }

        $id  = $this->repo->save($data, $userId);
        $row = $this->repo->find($id);

        if (!$row) {
            throw new RuntimeException('Failed to retrieve created tenant');
        }

        // Audit log
        $this->audit([
            'tenant_id'   => $id,
            'entity_type' => 'tenant',
            'entity_id'   => $id,
            'user_id'     => $userId,
            'action'      => 'tenant.create',
            'new_values'  => $this->sanitiseForAudit($row),
            'http_method' => $_SERVER['REQUEST_METHOD'] ?? 'POST',
            'http_url'    => $_SERVER['REQUEST_URI']    ?? '',
            'ip_address'  => $_SERVER['REMOTE_ADDR']    ?? null,
            'user_agent'  => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'session_id'  => session_id() ?: null,
        ]);

        return $row;
    }

    public function update(array $data, int $id, ?int $userId = null): array
    {
        $existing = $this->repo->find($id);
        if (!$existing) {
            throw new RuntimeException('Tenant not found');
        }

        $data       = array_merge($existing, $data);
        $data['id'] = $id;

        // Structural validation
        $errors = TenantsValidator::validate($data, true);
        if (!empty($errors)) {
            throw new InvalidArgumentException(json_encode($errors, JSON_UNESCAPED_UNICODE));
        }

        // Bad-words check (only when name/domain changed)
        if (($data['name'] ?? '') !== ($existing['name'] ?? '')) {
            $this->assertNoBadWords('name', $data['name']);
        }
        if (!empty($data['domain']) && ($data['domain'] ?? '') !== ($existing['domain'] ?? '')) {
            $this->assertNoBadWords('domain', $data['domain']);
        }

        // Owner existence
        if (!$this->repo->userExists((int)$data['owner_user_id'])) {
            throw new InvalidArgumentException('Owner user does not exist');
        }

        // Domain uniqueness
        if (!empty($data['domain']) && $this->repo->domainExists($data['domain'], $id)) {
            throw new InvalidArgumentException('Domain is already in use by another tenant');
        }

        $savedId = $this->repo->save($data, $userId);
        $row     = $this->repo->find($savedId);

        if (!$row) {
            throw new RuntimeException('Failed to retrieve updated tenant');
        }

        // Audit log (with diff)
        $this->audit([
            'tenant_id'   => $id,
            'entity_type' => 'tenant',
            'entity_id'   => $id,
            'user_id'     => $userId,
            'action'      => 'tenant.update',
            'old_values'  => $this->sanitiseForAudit($existing),
            'new_values'  => $this->sanitiseForAudit($row),
            'http_method' => $_SERVER['REQUEST_METHOD'] ?? 'PUT',
            'http_url'    => $_SERVER['REQUEST_URI']    ?? '',
            'ip_address'  => $_SERVER['REMOTE_ADDR']    ?? null,
            'user_agent'  => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'session_id'  => session_id() ?: null,
        ]);

        return $row;
    }

    public function delete(int $id, ?int $userId = null): void
    {
        $existing = $this->repo->find($id);
        if (!$existing) {
            throw new RuntimeException('Tenant not found');
        }

        if (!$this->repo->delete($id, $userId)) {
            throw new RuntimeException('Failed to delete tenant');
        }

        // Audit log
        $this->audit([
            'tenant_id'   => $id,
            'entity_type' => 'tenant',
            'entity_id'   => $id,
            'user_id'     => $userId,
            'action'      => 'tenant.delete',
            'old_values'  => $this->sanitiseForAudit($existing),
            'http_method' => $_SERVER['REQUEST_METHOD'] ?? 'DELETE',
            'http_url'    => $_SERVER['REQUEST_URI']    ?? '',
            'ip_address'  => $_SERVER['REMOTE_ADDR']    ?? null,
            'user_agent'  => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'session_id'  => session_id() ?: null,
        ]);
    }

    public function bulkUpdateStatus(array $ids, string $status, ?int $userId = null): array
    {
        $errors = TenantsValidator::validateBulk(['ids' => $ids, 'status' => $status]);
        if (!empty($errors)) {
            throw new InvalidArgumentException(json_encode($errors, JSON_UNESCAPED_UNICODE));
        }

        $affected = $this->repo->bulkUpdateStatus($ids, $status, $userId);

        // Audit log (single entry for the bulk operation)
        $this->audit([
            'tenant_id'   => null,
            'entity_type' => 'tenant',
            'entity_id'   => 0,
            'user_id'     => $userId,
            'action'      => 'tenant.bulk_status',
            'metadata'    => ['ids' => $ids, 'status' => $status, 'affected' => $affected],
            'http_method' => $_SERVER['REQUEST_METHOD'] ?? 'POST',
            'http_url'    => $_SERVER['REQUEST_URI']    ?? '',
            'ip_address'  => $_SERVER['REMOTE_ADDR']    ?? null,
            'user_agent'  => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'session_id'  => session_id() ?: null,
        ]);

        return [
            'affected_count' => $affected,
            'ids'            => $ids,
            'status'         => $status,
        ];
    }

    // ─────────────────────────────────────────────────────────────
    // PRIVATE HELPERS
    // ─────────────────────────────────────────────────────────────

    /**
     * Check a text field against the bad-words list.
     * Throws InvalidArgumentException when offensive content is detected.
     */
    private function assertNoBadWords(string $field, string $text): void
    {
        if ($this->badWordsService === null || trim($text) === '') {
            return;
        }
        try {
            $result = $this->badWordsService->checkText($text);
            if (empty($result['clean'])) {
                $words = implode(', ', array_column($result['found'] ?? [], 'word'));
                throw new InvalidArgumentException(
                    "The {$field} contains prohibited content" .
                    ($words ? " ({$words})" : '') . '.'
                );
            }
        } catch (InvalidArgumentException $e) {
            throw $e;
        } catch (\Throwable $e) {
            // Bad-words service failure must not block the primary operation
            error_log('[TenantsService] Bad-words check failed: ' . $e->getMessage());
        }
    }

    /**
     * Write an audit log entry.
     * Silently no-ops if no audit repository is configured.
     */
    private function audit(array $data): void
    {
        if ($this->auditRepo === null) {
            return;
        }
        try {
            $this->auditRepo->save($data);
        } catch (\Throwable $e) {
            // Audit failure must never break the primary operation
            error_log('[TenantsService] Audit log write failed: ' . $e->getMessage());
        }
    }

    /**
     * Strip columns that should not appear in audit snapshots
     * (e.g. password hashes inherited from a JOIN).
     */
    private function sanitiseForAudit(array $row): array
    {
        $sensitive = ['password', 'password_hash', 'token', 'secret', 'remember_token'];
        foreach ($sensitive as $k) {
            unset($row[$k]);
        }
        return $row;
    }
}
