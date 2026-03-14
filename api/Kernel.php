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
        if (preg_match('#^/(v\d+/)?(admin|mobile)?/?health$#', $uri)) {
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
     * Resolve route file from URI
     */
    private static function resolveRouteFile(string $uri): ?string
    {
        $version = 'v1'; // Default version
        $parts = explode('/', trim($uri, '/'));

        if (isset($parts[0]) && preg_match('/^v\d+$/', $parts[0])) {
            $version = array_shift($parts);
        }

        $base = __DIR__ . '/' . $version . '/routes';

        $prefix = $parts[0] ?? '';
        if ($prefix === 'admin' || $prefix === 'mobile') {
            $name = $parts[1] ?? '';
        } else {
            $name = $prefix;
        }

        if ($name === '') return null;
        $file = $base . '/' . $name . '.php';
        return is_file($file) ? $file : null;
    }
}