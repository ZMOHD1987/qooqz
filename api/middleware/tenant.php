<?php
// htdocs/api/middleware/tenant.php
// Multi-Tenancy Middleware

class TenantMiddleware {
    private static ?PDO $pdo = null;
    
    public static function setPDO(PDO $pdo) {
        self::$pdo = $pdo;
    }
    
    public static function handle($ctx, callable $next) {
        $host = $_SERVER['HTTP_HOST'] ?? '';
        $tenantId = self::resolveTenant($host);
        
        if (!$tenantId) {
            http_response_code(404);
            echo json_encode(['error' => 'Tenant not found']);
            exit;
        }
        
        $tenant = self::getTenant($tenantId);
        if (!$tenant || !$tenant['active']) {
            http_response_code(403);
            echo json_encode(['error' => 'Tenant inactive']);
            exit;
        }
        
        $ctx['tenant'] = $tenant;
        $ctx['tenant_id'] = $tenantId;
        
        return $next($ctx);
    }
    
    private static function resolveTenant($host) {
        $subdomain = explode('.', $host)[0] ?? '';
        
        if (self::$pdo) {
            $stmt = self::$pdo->prepare("SELECT id FROM tenants WHERE subdomain = ? AND active = 1");
            $stmt->execute([$subdomain]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row['id'] ?? null;
        }
        return null;
    }
    
    private static function getTenant($tenantId) {
        if (self::$pdo) {
            $stmt = self::$pdo->prepare("SELECT * FROM tenants WHERE id = ?");
            $stmt->execute([$tenantId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        }
        return null;
    }
}