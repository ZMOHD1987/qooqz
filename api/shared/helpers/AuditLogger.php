<?php
declare(strict_types=1);

class AuditLogger {
    private static ?PDO $pdo = null;

    public static function init(PDO $pdo): void {
        self::$pdo = $pdo;
    }

    public static function log(string $action, string $entityType, ?int $entityId = null, ?array $payload = null): void {
        if (!self::$pdo) return;
        try {
            $tenantId = $_SESSION['tenant_id'] ?? null;
            $userId = $_SESSION['user_id'] ?? null;
            $ip = $_SERVER['REMOTE_ADDR'] ?? null;
            $ua = isset($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'], 0, 255) : null;
            $stmt = self::$pdo->prepare("INSERT INTO audit_logs (tenant_id, entity_type, entity_id, user_id, action, ip_address, user_agent, payload) VALUES (:tenant_id, :entity_type, :entity_id, :user_id, :action, :ip, :ua, :payload)");
            $stmt->execute([
                ':tenant_id' => $tenantId,
                ':entity_type' => $entityType,
                ':entity_id' => $entityId,
                ':user_id' => $userId,
                ':action' => $action,
                ':ip' => $ip,
                ':ua' => $ua,
                ':payload' => $payload ? json_encode($payload, JSON_UNESCAPED_UNICODE) : null
            ]);
        } catch (\Throwable $e) {
            // silently fail - audit should never break main operations
        }
    }
}