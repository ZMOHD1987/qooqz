<?php
declare(strict_types=1);

// Load bootstrap for consistent DB and context
if (!isset($GLOBALS['CONTAINER']) || empty($GLOBALS['CONTAINER'])) {
    require_once __DIR__ . '/../bootstrap.php';
}

require_once __DIR__ . '/../services/HomeService.php';

// Response helper should be loaded by bootstrap
if (!function_exists('jsonResponse')) {
    require_once __DIR__ . '/../helpers/response.php';
}

class HomeController
{
    private $service;

    public function __construct()
    {
        // Get DB connection from bootstrap context
        $conn = $GLOBALS['CONTAINER']['db'] ?? null;
        
        // Fallback to connectDB if needed
        if (!$conn && function_exists('connectDB')) {
            $conn = connectDB();
        }

        if (!$conn instanceof mysqli) {
            throw new Exception('Database not available');
        }

        $this->service = new HomeService($conn);
    }

    public function index()
    {
        try {
            jsonResponse([
                'status' => 'ok',
                'data'   => $this->service->getHomeData()
            ]);
        } catch (Throwable $e) {
            jsonResponse([
                'status'  => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
