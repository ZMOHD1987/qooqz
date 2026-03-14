<?php
declare(strict_types=1);

final class AuctionTranslationsService
{
    private PdoAuctionTranslationsRepository $repo;

    public function __construct(PdoAuctionTranslationsRepository $repo)
    {
        $this->repo = $repo;
    }

    public function list(int $auctionId): array
    {
        return $this->repo->all($auctionId);
    }

    public function get(int $auctionId, string $languageCode): array
    {
        $data = $this->repo->find($auctionId, $languageCode);
        if (!$data) {
            throw new RuntimeException('Auction translation not found');
        }
        return $data;
    }

    public function save(array $data): int
    {
        $validator = new AuctionTranslationsValidator();
        if (!$validator->validate($data)) {
            throw new InvalidArgumentException(implode(', ', $validator->getErrors()));
        }
        return $this->repo->save($data);
    }

    public function delete(int $auctionId, string $languageCode): bool
    {
        return $this->repo->delete($auctionId, $languageCode);
    }
}
