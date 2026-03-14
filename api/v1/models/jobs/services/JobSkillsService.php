<?php
declare(strict_types=1);

final class JobSkillsService
{
    private PdoJobSkillsRepository $repo;
    private $validator;

    public function __construct(PdoJobSkillsRepository $repo, $validator = null)
    {
        $this->repo = $repo;
        $this->validator = $validator;
    }

    /**
     * List skills with filters, ordering, and pagination
     */
    public function list(
        ?int $limit,
        ?int $offset,
        array $filters,
        string $orderBy,
        string $orderDir
    ): array {
        return $this->repo->all($limit, $offset, $filters, $orderBy, $orderDir);
    }

    /**
     * Count skills with filters
     */
    public function count(array $filters = []): int
    {
        return $this->repo->count($filters);
    }

    /**
     * Get single skill by ID
     */
    public function get(int $id): ?array
    {
        return $this->repo->find($id);
    }

    /**
     * Get skills by job ID
     */
    public function getByJob(int $jobId, bool $requiredOnly = false): array
    {
        return $this->repo->getByJob($jobId, $requiredOnly);
    }

    /**
     * Get available proficiency levels
     */
    public function getProficiencyLevels(): array
    {
        return $this->repo->getProficiencyLevels();
    }

    /**
     * Create new skill
     */
    public function create(array $data): int
    {
        // Validate
        if ($this->validator) {
            $this->validator->validate($data, false);
        }

        return $this->repo->save($data);
    }

    /**
     * Update existing skill
     */
    public function update(array $data): int
    {
        if (empty($data['id'])) {
            throw new InvalidArgumentException('ID is required for update');
        }

        // Validate
        if ($this->validator) {
            $this->validator->validate($data, true);
        }

        return $this->repo->save($data);
    }

    /**
     * Delete skill
     */
    public function delete(int $id): bool
    {
        return $this->repo->delete($id);
    }

    /**
     * Delete all skills for a job
     */
    public function deleteByJob(int $jobId): bool
    {
        return $this->repo->deleteByJob($jobId);
    }

    /**
     * Duplicate skills from another job
     */
    public function duplicateFromJob(int $sourceJobId, int $targetJobId): bool
    {
        if ($sourceJobId === $targetJobId) {
            throw new InvalidArgumentException('Source and target job IDs cannot be the same.');
        }

        return $this->repo->duplicateFromJob($sourceJobId, $targetJobId);
    }

    /**
     * Bulk create skills
     */
    public function bulkCreate(int $jobId, array $skills): array
    {
        $createdIds = [];
        
        foreach ($skills as $skill) {
            $skill['job_id'] = $jobId;
            $createdIds[] = $this->create($skill);
        }

        return $createdIds;
    }

    /**
     * Bulk update skills for a job
     */
    public function bulkUpdate(int $jobId, array $skills): bool
    {
        // Validate
        if ($this->validator) {
            $this->validator->validateBulkUpdate($skills);
        }

        // First, delete existing skills for the job
        $this->deleteByJob($jobId);

        // Then, create new ones
        $this->bulkCreate($jobId, $skills);

        return true;
    }
}