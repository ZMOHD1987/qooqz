<?php
declare(strict_types=1);

final class ImageTypesController
{
    private ImageTypesService $service;

    public function __construct(ImageTypesService $service)
    {
        $this->service = $service;
    }

    /**
     * List all image types
     */
    public function list(): array
    {
        return $this->service->list();
    }

    /**
     * Get image type by ID
     */
    public function get(int $id): array
    {
        return $this->service->get($id);
    }

    /**
     * Resolve image type by CODE
     */
    public function resolve(string $code): array
    {
        return $this->service->resolve($code);
    }

    /**
     * Create image type
     */
    public function create(array $data): array
    {
        return $this->service->create($data);
    }

    /**
     * Update image type
     */
    public function update(int $id, array $data): array
    {
        return $this->service->update($id, $data);
    }

    /**
     * Delete image type
     */
    public function delete(int $id): void
    {
        $this->service->delete($id);
    }
}
