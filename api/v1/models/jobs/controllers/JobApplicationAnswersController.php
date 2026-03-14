<?php
declare(strict_types=1);

final class JobApplicationAnswersController
{
    private JobApplicationAnswersService $service;

    public function __construct(JobApplicationAnswersService $service)
    {
        $this->service = $service;
    }

    /**
     * List answers with filters, ordering, and pagination
     */
    public function list(
        ?int $limit = null,
        ?int $offset = null,
        array $filters = [],
        string $orderBy = 'id',
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
     * Get a single answer by ID
     */
    public function get(int $id): ?array
    {
        return $this->service->get($id);
    }

    /**
     * Get answers by application ID
     */
    public function getByApplication(int $applicationId): array
    {
        return $this->service->getByApplication($applicationId);
    }

    /**
     * Get answer by application and question
     */
    public function getByApplicationAndQuestion(int $applicationId, int $questionId): ?array
    {
        return $this->service->getByApplicationAndQuestion($applicationId, $questionId);
    }

    /**
     * Get answers by question (all applications)
     */
    public function getByQuestion(int $questionId): array
    {
        return $this->service->getByQuestion($questionId);
    }

    /**
     * Get statistics for a question
     */
    public function getQuestionStatistics(int $questionId): array
    {
        return $this->service->getQuestionStatistics($questionId);
    }

    /**
     * Create a new answer
     */
    public function create(array $data): int
    {
        return $this->service->create($data);
    }

    /**
     * Update an existing answer
     */
    public function update(array $data): int
    {
        return $this->service->update($data);
    }

    /**
     * Delete an answer
     */
    public function delete(int $id): bool
    {
        return $this->service->delete($id);
    }

    /**
     * Delete by application
     */
    public function deleteByApplication(int $applicationId): bool
    {
        return $this->service->deleteByApplication($applicationId);
    }

    /**
     * Delete by question
     */
    public function deleteByQuestion(int $questionId): bool
    {
        return $this->service->deleteByQuestion($questionId);
    }

    /**
     * Bulk save answers
     */
    public function bulkSave(int $applicationId, array $answers): bool
    {
        return $this->service->bulkSave($applicationId, $answers);
    }

    /**
     * Check required answers
     */
    public function checkRequiredAnswers(int $applicationId): array
    {
        return $this->service->checkRequiredAnswers($applicationId);
    }

    /**
     * Submit application answers
     */
    public function submitApplicationAnswers(int $applicationId, array $answers, array $questions = []): bool
    {
        return $this->service->submitApplicationAnswers($applicationId, $answers, $questions);
    }
}
