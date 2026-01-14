<?php
declare(strict_types=1);
/**
 * api/services/HomeService.php
 * Service layer for home page data
 * Coordinates between repositories to build home page response
 */

require_once __DIR__ . '/../repositories/HomeRepository.php';

class HomeService
{
    private $conn;
    private $repository;

    public function __construct($conn, $lang = 'en')
    {
        $this->conn = $conn;
        $this->repository = new HomeRepository($conn, $lang);
    }

    /**
     * Get all data needed for home page
     * @return array
     */
    public function getHomeData()
    {
        try {
            return [
                'theme' => $this->repository->getActiveTheme(),
                'sections' => $this->repository->getSections(),
                'banners' => $this->repository->getBanners(),
                'colors' => $this->repository->getColors(),
                'featured_products' => $this->repository->getFeaturedProducts(),
                'new_products' => $this->repository->getNewProducts(),
                'hot_products' => $this->repository->getHotProducts(),
                'featured_vendors' => $this->repository->getFeaturedVendors(),
                'categories' => $this->repository->getCategories()
            ];
        } catch (Throwable $e) {
            error_log('HomeService::getHomeData error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get featured products
     * @param int $limit
     * @return array
     */
    public function getFeaturedProducts($limit = 12)
    {
        return $this->repository->getFeaturedProducts($limit);
    }

    /**
     * Get new products
     * @param int $limit
     * @return array
     */
    public function getNewProducts($limit = 12)
    {
        return $this->repository->getNewProducts($limit);
    }

    /**
     * Get hot/bestseller products
     * @param int $limit
     * @return array
     */
    public function getHotProducts($limit = 12)
    {
        return $this->repository->getHotProducts($limit);
    }

    /**
     * Get featured vendors
     * @param int $limit
     * @return array
     */
    public function getFeaturedVendors($limit = 8)
    {
        return $this->repository->getFeaturedVendors($limit);
    }

    /**
     * Get categories
     * @return array
     */
    public function getCategories()
    {
        return $this->repository->getCategories();
    }
}
