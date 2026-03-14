<?php
declare(strict_types=1);

final class BadWordsController
{
    private BadWordsService $service;

    public function __construct(BadWordsService $service)
    {
        $this->service = $service;
    }

    public function list(
        ?int $limit = null,
        ?int $offset = null,
        array $filters = [],
        string $orderBy = 'id',
        string $orderDir = 'DESC'
    ): array {
        return $this->service->list($limit, $offset, $filters, $orderBy, $orderDir);
    }

    public function get(int $id): ?array
    {
        return $this->service->get($id);
    }

    public function create(array $data): int
    {
        return $this->service->create($data);
    }

    public function update(int $id, array $data): bool
    {
        return $this->service->update($id, $data);
    }

    public function delete(int $id): bool
    {
        return $this->service->delete($id);
    }

    // Translations
    public function getTranslations(int $badWordId): array
    {
        return $this->service->getTranslations($badWordId);
    }

    public function saveTranslation(array $data): int
    {
        return $this->service->saveTranslation($data);
    }

    public function deleteTranslation(int $id): bool
    {
        return $this->service->deleteTranslation($id);
    }

    // Text checking
    public function checkText(string $text, ?string $languageCode = null): array
    {
        return $this->service->checkText($text, $languageCode);
    }

    public function filterText(string $text, ?string $languageCode = null): string
    {
        return $this->service->filterText($text, $languageCode);
    }
}
