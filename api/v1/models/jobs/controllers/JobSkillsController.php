<?php
declare(strict_types=1);

final class JobSkillsController
{
    private JobSkillsService $service;

    public function __construct(JobSkillsService $service)
    {
        $this->service = $service;
    }

    /**
     * List skills with filters, ordering, and pagination
     */
    public function list(
        ?int $limit = null,
        ?int $offset = null,
        array $filters = [],
        string $orderBy = 'skill_name',
        string $orderDir = 'ASC'
    ): array {
        $items = $this->service->list($limit, $offset, $filters, $orderBy, $orderDir);
        $total = $this->service->count($filters);

        return [
            'items' => $items,
            'total' => $total
        ];
    }

    /**
     * Get a single skill by ID
     */
    public function get(int $id): ?array
    {
        return $this->service->get($id);
    }

    /**
     * Get skills by job ID
     */
    public function getByJob(int $jobId, bool $requiredOnly = false): array
    {
        return $this->service->getByJob($jobId, $requiredOnly);
    }

    /**
     * Get available proficiency levels
     */
    public function getProficiencyLevels(): array
    {
        return $this->service->getProficiencyLevels();
    }

    /**
     * Create a new skill
     */
    public function create(array $data): int
    {
        return $this->service->create($data);
    }

    /**
     * Update an existing skill
     */
    public function update(array $data): int
    {
        return $this->service->update($data);
    }

    /**
     * Delete a skill
     */
    public function delete(int $id): bool
    {
        return $this->service->delete($id);
    }

    /**
     * Delete all skills for a job
     */
    public function deleteByJob(int $jobId): bool
    {
        return $this->service->deleteByJob($jobId);
    }

    /**
     * Duplicate skills from another job
     */
    public function duplicateFromJob(int $sourceJobId, int $targetJobId): bool
    {
        return $this->service->duplicateFromJob($sourceJobId, $targetJobId);
    }

    /**
     * Bulk create skills
     */
    public function bulkCreate(int $jobId, array $skills): array
    {
        return $this->service->bulkCreate($jobId, $skills);
    }

    /**
     * Bulk update skills for a job
     */
    public function bulkUpdate(int $jobId, array $skills): bool
    {
        return $this->service->bulkUpdate($jobId, $skills);
    }
}