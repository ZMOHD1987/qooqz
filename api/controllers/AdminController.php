<?php
// api/controllers/AdminController.php
// Minimal AdminController placeholder (safe stub).
// Provides basic static methods used by /api/routes/admin.php to avoid fatal errors.
// Replace with full implementation when you need real admin features.

declare(strict_types=1);

class AdminController
{
    // helper responders (use project's json_ok/json_error if present)
    private static function ok($data = [], int $code = 200) {
        if (function_exists('json_ok')) {
            json_ok($data, $code);
            return;
        }
        if (!headers_sent()) header('Content-Type: application/json; charset=utf-8', true, $code);
        echo json_encode(array_merge(['success' => true], is_array($data) ? $data : ['data' => $data]), JSON_UNESCAPED_UNICODE);
        exit;
    }

    private static function err(string $msg = 'Not implemented', int $code = 501, array $extra = []) {
        if (function_exists('json_error')) {
            json_error($msg, $code, $extra);
            return;
        }
        if (!headers_sent()) header('Content-Type: application/json; charset=utf-8', true, $code);
        echo json_encode(array_merge(['success' => false, 'message' => $msg], $extra), JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Dashboard (basic placeholder)
    public static function dashboard() {
        // try to provide minimal useful info if DB available
        $out = ['message' => 'Admin dashboard placeholder', 'ok' => true];
        try {
            if (function_exists('acquire_db')) $db = acquire_db();
            else $db = $GLOBALS['db'] ?? $GLOBALS['conn'] ?? null;
            if ($db instanceof mysqli) {
                $counts = [];
                $tables = ['users','products','orders'];
                foreach ($tables as $t) {
                    $q = @$db->query("SELECT COUNT(*) AS c FROM `{$t}` LIMIT 1");
                    if ($q) { $r = $q->fetch_assoc(); $counts[$t] = (int)($r['c'] ?? 0); $q->free(); }
                    else $counts[$t] = null;
                }
                $out['counts'] = $counts;
            }
        } catch (Throwable $e) {
            // ignore DB errors in stub
        }
        self::ok($out);
    }

    // Stats placeholder
    public static function stats() {
        self::ok(['message' => 'Stats placeholder', 'timestamp' => date('c')]);
    }

    // API keys management placeholders
    public static function listApiKeys() {
        // return empty list (or attempt DB read)
        $keys = [];
        try {
            $db = $GLOBALS['db'] ?? $GLOBALS['conn'] ?? null;
            if ($db instanceof mysqli) {
                $res = $db->query("SELECT id, name, created_at, is_active FROM api_keys ORDER BY id DESC LIMIT 100");
                if ($res) {
                    while ($r = $res->fetch_assoc()) $keys[] = $r;
                    $res->free();
                }
            }
        } catch (Throwable $e) { /* ignore */ }
        self::ok(['api_keys' => $keys]);
    }

    public static function createApiKey() {
        // Not implemented: return 501
        self::err('createApiKey not implemented on this installation', 501);
    }

    public static function revokeApiKey($id = null) {
        self::err('revokeApiKey not implemented on this installation', 501);
    }

    // Feature flags placeholders
    public static function listFeatures() {
        self::ok(['features' => []]);
    }

    public static function toggleFeature() {
        self::err('toggleFeature not implemented', 501);
    }

    // Other admin helpers (can be expanded)
    public static function listUsers() { self::err('Admin user listing not implemented', 501); }
    public static function createUser() { self::err('Admin create user not implemented', 501); }
}