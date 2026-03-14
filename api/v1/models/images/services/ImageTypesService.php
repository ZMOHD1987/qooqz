<?php
declare(strict_types=1);

require_once __DIR__ . '/../repositories/PdoImageTypesRepository.php';
require_once __DIR__ . '/../validators/ImageTypesValidator.php';

final class ImageTypesService
{
    private PdoImageTypesRepository $repo;

    public function __construct(PdoImageTypesRepository $repo)
    {
        $this->repo = $repo;
    }

    /**
     * List all image types
     */
    public function list(): array
    {
        return $this->repo->all();
    }

    /**
     * Get image type by ID
     */
    public function get(int $id): array
    {
        $row = $this->repo->find($id);
        if (!$row) {
            throw new RuntimeException('Image type not found');
        }

        return $row;
    }

    /**
     * Resolve image type by CODE (الأساس في النظام)
     */
    public function resolve(string $code): array
    {
        $row = $this->repo->findByCode($code);
        if (!$row) {
            throw new RuntimeException('Invalid image type');
        }

        return $row;
    }

    /**
     * Create new image type
     */
    public function create(array $data): array
    {
        $errors = ImageTypesValidator::validate($data);
        if (!empty($errors)) {
            throw new InvalidArgumentException(
                json_encode($errors, JSON_UNESCAPED_UNICODE)
            );
        }

        // منع تكرار الـ code (هو المفتاح الحقيقي)
        if (!empty($data['code']) && $this->repo->findByCode($data['code'])) {
            throw new InvalidArgumentException('Image type code already exists');
        }

        $id = $this->repo->create($data);
        return $this->get($id);
    }

    /**
     * Update image type
     */
    public function update(int $id, array $data): array
    {
        $errors = ImageTypesValidator::validate($data, $id);
        if (!empty($errors)) {
            throw new InvalidArgumentException(
                json_encode($errors, JSON_UNESCAPED_UNICODE)
            );
        }

        if (!$this->repo->update($id, $data)) {
            throw new RuntimeException('Failed to update image type');
        }

        return $this->get($id);
    }

    /**
     * Delete image type
     */
    public function delete(int $id): void
    {
        if (!$this->repo->delete($id)) {
            throw new RuntimeException('Failed to delete image type');
        }
    }
}
