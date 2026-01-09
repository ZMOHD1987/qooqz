<?php
declare(strict_types=1);

require_once __DIR__ . '/../services/HomeService.php';
require_once __DIR__ . '/../helpers/response.php';

class HomeController
{
    private HomeService $service;

    public function __construct()
    {
        if (!function_exists('connectDB')) {
            throw new Exception('connectDB() not found');
        }

        $conn = connectDB();

        if (!$conn instanceof mysqli) {
            throw new Exception('Database not available');
        }

        $this->service = new HomeService($conn);
    }

    public function index(): void
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
