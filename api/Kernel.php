<?php

declare(strict_types=1);

/**
 * Kernel – Auto Route Loader (FINAL PRODUCTION v2)
 * (Updated: Removed special handling for resource_permissions, now handled as a regular route file)
 */

final class Kernel
{
    public static function dispatch(): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri    = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

        // Remove /api prefix
        $uri = preg_replace('#^/api#', '', $uri);
        $uri = '/' . trim($uri, '/');

        // Health check
        if (preg_match('#^/(admin|mobile)?/?health$#', $uri)) {
            http_response_code(200);
            echo json_encode([
                'status' => 'ok',
                'time'   => date('c'),
            ]);
            exit;
        }

        // Standard route resolution (resource_permissions now handled like others)
        $routeFile = self::resolveRouteFile($uri);
        if (!$routeFile) {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'error'   => 'Route not found',
                'path'    => $uri,
                'method'  => $method,
            ]);
            exit;
        }

        require $routeFile;
        exit;
    }

    /**
     * Resolve route file from URI (unchanged)
     */
    private static function resolveRouteFile(string $uri): ?string
    {
        $base = __DIR__ . '/routes';
        if (str_starts_with($uri, '/admin/')) {
            $parts = explode('/', trim($uri, '/'));
            $name = $parts[1] ?? '';
        } elseif (str_starts_with($uri, '/mobile/')) {
            $parts = explode('/', trim($uri, '/'));
            $name = $parts[1] ?? '';
        } else {
            $parts = explode('/', trim($uri, '/'));
            $name = $parts[0] ?? '';
        }

        if ($name === '') return null;
        $file = $base . '/' . $name . '.php';
        return is_file($file) ? $file : null;
    }
}