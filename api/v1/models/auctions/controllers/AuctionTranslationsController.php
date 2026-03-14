<?php
declare(strict_types=1);

final class AuctionTranslationsController
{
    private AuctionTranslationsService $service;

    public function __construct(AuctionTranslationsService $service)
    {
        $this->service = $service;
    }

    public function list(int $auctionId): array
    {
        return $this->service->list($auctionId);
    }

    public function get(int $auctionId, string $languageCode): array
    {
        return $this->service->get($auctionId, $languageCode);
    }

    public function save(array $data): int
    {
        return $this->service->save($data);
    }

    public function delete(int $auctionId, string $languageCode): bool
    {
        return $this->service->delete($auctionId, $languageCode);
    }
}
