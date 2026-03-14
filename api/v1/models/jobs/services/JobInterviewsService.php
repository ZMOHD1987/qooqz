<?php
declare(strict_types=1);

final class JobInterviewsService
{
    private PdoJobInterviewsRepository $repo;
    private $validator;

    public function __construct(PdoJobInterviewsRepository $repo, $validator = null)
    {
        $this->repo = $repo;
        $this->validator = $validator;
    }

    /**
     * List interviews with filters, ordering, and pagination
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
     * Count interviews with filters
     */
    public function count(array $filters = []): int
    {
        return $this->repo->count($filters);
    }

    /**
     * Get single interview by ID
     */
    public function get(int $id): ?array
    {
        return $this->repo->find($id);
    }

    /**
     * Get interviews by application
     */
    public function getByApplication(int $applicationId): array
    {
        return $this->repo->getByApplication($applicationId);
    }

    /**
     * Get statistics
     */
    public function getStatistics(array $filters = []): array
    {
        return $this->repo->getStatistics($filters);
    }

    /**
     * Get available interview types
     */
    public function getInterviewTypes(): array
    {
        return $this->repo->getInterviewTypes();
    }

    /**
     * Get available statuses
     */
    public function getStatuses(): array
    {
        return $this->repo->getStatuses();
    }

    /**
     * Create new interview
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
     * Update existing interview
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
     * Delete interview
     */
    public function delete(int $id): bool
    {
        return $this->repo->delete($id);
    }

    /**
     * Update interview status
     */
    public function updateStatus(int $id, string $status): bool
    {
        // Validate status
        if ($this->validator) {
            $this->validator->validateStatusUpdate($status);
        }

        return $this->repo->updateStatus($id, $status);
    }

    /**
     * Add feedback
     */
    public function addFeedback(int $id, string $feedback, ?int $rating = null): bool
    {
        if (trim($feedback) === '') {
            throw new InvalidArgumentException('Feedback cannot be empty.');
        }

        if ($rating !== null && $this->validator) {
            $this->validator->validateRating($rating);
        }

        return $this->repo->addFeedback($id, $feedback, $rating);
    }

    /**
     * Reschedule interview
     */
    public function reschedule(int $id, string $newDate, ?int $newDuration = null): bool
    {
        // Validate
        if ($this->validator) {
            $this->validator->validateReschedule([
                'new_date' => $newDate,
                'new_duration' => $newDuration
            ]);
        }

        return $this->repo->reschedule($id, $newDate, $newDuration);
    }

    /**
     * Confirm interview
     */
    public function confirm(int $id): bool
    {
        return $this->updateStatus($id, 'confirmed');
    }

    /**
     * Complete interview
     */
    public function complete(int $id): bool
    {
        return $this->updateStatus($id, 'completed');
    }

    /**
     * Cancel interview
     */
    public function cancel(int $id): bool
    {
        return $this->updateStatus($id, 'cancelled');
    }

    /**
     * Mark as no show
     */
    public function markNoShow(int $id): bool
    {
        return $this->updateStatus($id, 'no_show');
    }

    /**
     * Schedule interview (helper method)
     */
    public function schedule(array $data): int
    {
        $data['status'] = 'scheduled';
        return $this->create($data);
    }
}
