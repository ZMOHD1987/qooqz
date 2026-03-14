<?php
declare(strict_types=1);

final class JobInterviewsController
{
    private JobInterviewsService $service;

    public function __construct(JobInterviewsService $service)
    {
        $this->service = $service;
    }

    /**
     * List interviews with filters, ordering, and pagination
     */
    public function list(
        ?int $limit = null,
        ?int $offset = null,
        array $filters = [],
        string $orderBy = 'interview_date',
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
     * Get a single interview by ID
     */
    public function get(int $id): ?array
    {
        return $this->service->get($id);
    }

    /**
     * Get interviews by application
     */
    public function getByApplication(int $applicationId): array
    {
        return $this->service->getByApplication($applicationId);
    }

    /**
     * Get statistics
     */
    public function getStatistics(array $filters = []): array
    {
        return $this->service->getStatistics($filters);
    }

    /**
     * Get available interview types
     */
    public function getInterviewTypes(): array
    {
        return $this->service->getInterviewTypes();
    }

    /**
     * Get available statuses
     */
    public function getStatuses(): array
    {
        return $this->service->getStatuses();
    }

    /**
     * Create a new interview
     */
    public function create(array $data): int
    {
        return $this->service->create($data);
    }

    /**
     * Update an existing interview
     */
    public function update(array $data): int
    {
        return $this->service->update($data);
    }

    /**
     * Delete an interview
     */
    public function delete(int $id): bool
    {
        return $this->service->delete($id);
    }

    /**
     * Update status
     */
    public function updateStatus(int $id, string $status): bool
    {
        return $this->service->updateStatus($id, $status);
    }

    /**
     * Add feedback
     */
    public function addFeedback(int $id, string $feedback, ?int $rating = null): bool
    {
        return $this->service->addFeedback($id, $feedback, $rating);
    }

    /**
     * Reschedule interview
     */
    public function reschedule(int $id, string $newDate, ?int $newDuration = null): bool
    {
        return $this->service->reschedule($id, $newDate, $newDuration);
    }

    /**
     * Confirm interview
     */
    public function confirm(int $id): bool
    {
        return $this->service->confirm($id);
    }

    /**
     * Complete interview
     */
    public function complete(int $id): bool
    {
        return $this->service->complete($id);
    }

    /**
     * Cancel interview
     */
    public function cancel(int $id): bool
    {
        return $this->service->cancel($id);
    }

    /**
     * Mark as no show
     */
    public function markNoShow(int $id): bool
    {
        return $this->service->markNoShow($id);
    }

    /**
     * Schedule interview
     */
    public function schedule(array $data): int
    {
        return $this->service->schedule($data);
    }
}
