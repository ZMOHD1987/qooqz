<?php
declare(strict_types=1);

final class AuditLogsService
{
    private PdoAuditLogsRepository $repo;

    public function __construct(PdoAuditLogsRepository $repo)
    {
        $this->repo = $repo;
    }

    // ─────────────────────────────────────────────────────────────
    // Helper Method: استدعاء ثابت للتسجيل السريع من أي مكان
    // ─────────────────────────────────────────────────────────────
    public static function log(
        string $action,
        ?string $entityType = null,
        ?int $entityId = null,
        ?array $payload = null,
        ?int $tenantId = null,
        ?int $userId = null
    ): void {
        // التحقق من وجود اتصال قاعدة البيانات
        if (!isset($GLOBALS['ADMIN_DB']) || !$GLOBALS['ADMIN_DB'] instanceof PDO) {
            error_log("AuditLog Error: Database connection not found in GLOBALS.");
            return;
        }

        try {
            $pdo = $GLOBALS['ADMIN_DB'];
            $repo = new PdoAuditLogsRepository($pdo);

            // جمع البيانات من الـ Global State إذا لم تمرر
            $data = [
                'tenant_id'    => $tenantId ?? ($GLOBALS['TENANT_ID'] ?? ($_SESSION['tenant_id'] ?? null)),
                'user_id'      => $userId ?? ($_SESSION['user_id'] ?? null),
                'action'       => $action,
                'entity_type'  => $entityType,
                'entity_id'    => $entityId,
                'payload'      => $payload,
                'ip_address'   => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent'   => $_SERVER['HTTP_USER_AGENT'] ?? null,
            ];

            $repo->save($data);
        } catch (\Throwable $e) {
            // لا نريد أن يتوقف النظام إذا فشل التسجيل، نكتفي بتسجيل الخطأ
            error_log("AuditLog Exception: " . $e->getMessage());
        }
    }

    // ─────────────────────────────────────────────────────────────
    // Standard API Methods
    // ─────────────────────────────────────────────────────────────

    public function list(
        int $tenantId,
        ?int $limit = null,
        ?int $offset = null,
        array $filters = [],
        string $orderBy = 'id',
        string $orderDir = 'DESC'
    ): array {
        return $this->repo->all($tenantId, $limit, $offset, $filters, $orderBy, $orderDir);
    }

    public function count(int $tenantId, array $filters = []): int
    {
        return $this->repo->count($tenantId, $filters);
    }

    public function get(int $tenantId, int $id): array
    {
        $data = $this->repo->find($tenantId, $id);
        if (!$data) {
            throw new RuntimeException('Log not found');
        }
        return $data;
    }
}