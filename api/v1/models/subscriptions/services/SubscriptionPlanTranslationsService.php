<?php
declare(strict_types=1);

class SubscriptionPlanTranslationsService {
    private PdoSubscriptionPlanTranslationsRepository $repo;

    public function __construct(PdoSubscriptionPlanTranslationsRepository $repo) {
        $this->repo = $repo;
    }

    public function listByPlan(int $planId): array { return $this->repo->listByPlan($planId); }
    public function find(int $id): ?array { return $this->repo->find($id); }
    public function upsert(int $planId, string $langCode, array $data): int { return $this->repo->upsert($planId, $langCode, $data); }
    public function delete(int $id): bool { return $this->repo->delete($id); }
    public function deleteByPlan(int $planId): bool { return $this->repo->deleteByPlan($planId); }
}
