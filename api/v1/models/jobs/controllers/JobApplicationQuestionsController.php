<?php
declare(strict_types=1);

final class JobApplicationQuestionsController
{
    private JobApplicationQuestionsService $service;

    public function __construct(JobApplicationQuestionsService $service)
    {
        $this->service = $service;
    }

    /**
     * List questions with filters, ordering, and pagination
     */
    public function list(
        ?int $limit = null,
        ?int $offset = null,
        array $filters = [],
        string $orderBy = 'sort_order',
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
     * Get a single question by ID
     */
    public function get(int $id): ?array
    {
        return $this->service->get($id);
    }

    /**
     * Get questions by job ID
     */
    public function getByJob(int $jobId, bool $requiredOnly = false): array
    {
        return $this->service->getByJob($jobId, $requiredOnly);
    }

    /**
     * Get available question types
     */
    public function getQuestionTypes(): array
    {
        return $this->service->getQuestionTypes();
    }

    /**
     * Create a new question
     */
    public function create(array $data): int
    {
        return $this->service->create($data);
    }

    /**
     * Update an existing question
     */
    public function update(array $data): int
    {
        return $this->service->update($data);
    }

    /**
     * Delete a question
     */
    public function delete(int $id): bool
    {
        return $this->service->delete($id);
    }

    /**
     * Delete all questions for a job
     */
    public function deleteByJob(int $jobId): bool
    {
        return $this->service->deleteByJob($jobId);
    }

    /**
     * Update sort order
     */
    public function updateSortOrder(int $id, int $sortOrder): bool
    {
        return $this->service->updateSortOrder($id, $sortOrder);
    }

    /**
     * Reorder questions (batch update)
     */
    public function reorder(array $orderData): bool
    {
        return $this->service->reorder($orderData);
    }

    /**
     * Duplicate questions from another job
     */
    public function duplicateFromJob(int $sourceJobId, int $targetJobId): bool
    {
        return $this->service->duplicateFromJob($sourceJobId, $targetJobId);
    }

    /**
     * Bulk create questions
     */
    public function bulkCreate(int $jobId, array $questions): array
    {
        return $this->service->bulkCreate($jobId, $questions);
    }
}
