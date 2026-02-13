<?php
declare(strict_types=1);

class SubscriptionPlanTranslationsController {
    private SubscriptionPlanTranslationsService $service;

    public function __construct(SubscriptionPlanTranslationsService $service) {
        $this->service = $service;
    }

    public function listByPlan(int $planId): array { return $this->service->listByPlan($planId); }
    public function find(int $id): ?array { return $this->service->find($id); }
    public function upsert(int $planId, string $langCode, array $data): int { return $this->service->upsert($planId, $langCode, $data); }
    public function delete(int $id): bool { return $this->service->delete($id); }
    public function deleteByPlan(int $planId): bool { return $this->service->deleteByPlan($planId); }
}
