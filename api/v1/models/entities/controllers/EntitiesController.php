<?php
declare(strict_types=1);

final class EntitiesController
{
    public function __construct(
        private EntitiesService $service
    ) {}

    public function list(...$args): array
    {
        return $this->service->list(...$args);
    }

    public function get(int $t,int $i,string $l): ?array
    {
        return $this->service->get($t,$i,$l);
    }

    public function save(int $t,array $d,string $l): int
    {
        return $this->service->save($t,$d,$l);
    }

    public function delete(int $t,int $i): bool
    {
        return $this->service->delete($t,$i);
    }
}
