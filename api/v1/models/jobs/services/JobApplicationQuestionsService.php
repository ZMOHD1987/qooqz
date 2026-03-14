<?php
declare(strict_types=1);

final class JobApplicationQuestionsService
{
    private PdoJobApplicationQuestionsRepository $repo;
    private $validator;

    public function __construct(PdoJobApplicationQuestionsRepository $repo, $validator = null)
    {
        $this->repo = $repo;
        $this->validator = $validator;
    }

    /**
     * List questions with filters, ordering, and pagination
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
     * Count questions with filters
     */
    public function count(array $filters = []): int
    {
        return $this->repo->count($filters);
    }

    /**
     * Get single question by ID
     */
    public function get(int $id): ?array
    {
        return $this->repo->find($id);
    }

    /**
     * Get questions by job ID
     */
    public function getByJob(int $jobId, bool $requiredOnly = false): array
    {
        return $this->repo->getByJob($jobId, $requiredOnly);
    }

    /**
     * Get available question types
     */
    public function getQuestionTypes(): array
    {
        return $this->repo->getQuestionTypes();
    }

    /**
     * Create new question
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
     * Update existing question
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
     * Delete question
     */
    public function delete(int $id): bool
    {
        return $this->repo->delete($id);
    }

    /**
     * Delete all questions for a job
     */
    public function deleteByJob(int $jobId): bool
    {
        return $this->repo->deleteByJob($jobId);
    }

    /**
     * Update sort order
     */
    public function updateSortOrder(int $id, int $sortOrder): bool
    {
        return $this->repo->updateSortOrder($id, $sortOrder);
    }

    /**
     * Reorder questions (batch update)
     */
    public function reorder(array $orderData): bool
    {
        // Validate
        if ($this->validator) {
            $this->validator->validateReorder($orderData);
        }

        return $this->repo->reorder($orderData);
    }

    /**
     * Duplicate questions from another job
     */
    public function duplicateFromJob(int $sourceJobId, int $targetJobId): bool
    {
        if ($sourceJobId === $targetJobId) {
            throw new InvalidArgumentException('Source and target job IDs cannot be the same.');
        }

        return $this->repo->duplicateFromJob($sourceJobId, $targetJobId);
    }

    /**
     * Bulk create questions
     */
    public function bulkCreate(int $jobId, array $questions): array
    {
        $createdIds = [];
        
        foreach ($questions as $question) {
            $question['job_id'] = $jobId;
            $createdIds[] = $this->create($question);
        }

        return $createdIds;
    }
}
