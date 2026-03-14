<?php
declare(strict_types=1);

final class JobApplicationsController
{
    private JobApplicationsService $service;

    public function __construct(JobApplicationsService $service)
    {
        $this->service = $service;
    }

    /**
     * List applications with filters, ordering, and pagination
     */
    public function list(
        ?int $limit = null,
        ?int $offset = null,
        array $filters = [],
        string $orderBy = 'id',
        string $orderDir = 'DESC',
        string $lang = 'ar'
    ): array {
        $items = $this->service->list($limit, $offset, $filters, $orderBy, $orderDir, $lang);
        $total = $this->service->count($filters, $lang);

        return [
            'items' => $items,
            'total' => $total
        ];
    }

    /**
     * Get a single application by ID
     */
    public function get(int $id, string $lang = 'ar'): ?array
    {
        return $this->service->get($id, $lang);
    }

    /**
     * Get applications by job ID
     */
    public function getByJob(int $jobId, string $lang = 'ar'): array
    {
        return $this->service->getByJob($jobId, $lang);
    }

    /**
     * Get applications by user ID
     */
    public function getByUser(int $userId, string $lang = 'ar'): array
    {
        return $this->service->getByUser($userId, $lang);
    }

    /**
     * Check if user already applied
     */
    public function hasApplied(int $jobId, int $userId): bool
    {
        return $this->service->hasApplied($jobId, $userId);
    }

    /**
     * Get statistics for a job
     */
    public function getStatistics(int $jobId): array
    {
        return $this->service->getStatistics($jobId);
    }

    /**
     * Create a new application
     */
    public function create(array $data): int
    {
        return $this->service->create($data);
    }

    /**
     * Update an existing application
     */
    public function update(array $data): int
    {
        return $this->service->update($data);
    }

    /**
     * Delete an application
     */
    public function delete(int $id): bool
    {
        return $this->service->delete($id);
    }

    /**
     * Update application status
     */
    public function updateStatus(int $id, string $status): bool
    {
        return $this->service->updateStatus($id, $status);
    }

    /**
     * Update rating
     */
    public function updateRating(int $id, int $rating, ?int $reviewedBy = null): bool
    {
        return $this->service->updateRating($id, $rating, $reviewedBy);
    }

    /**
     * Add review/notes
     */
    public function addReview(int $id, string $notes, int $reviewedBy): bool
    {
        return $this->service->addReview($id, $notes, $reviewedBy);
    }

    /**
     * Shortlist application
     */
    public function shortlist(int $id): bool
    {
        return $this->service->shortlist($id);
    }

    /**
     * Reject application
     */
    public function reject(int $id): bool
    {
        return $this->service->reject($id);
    }

    /**
     * Schedule interview
     */
    public function scheduleInterview(int $id): bool
    {
        return $this->service->scheduleInterview($id);
    }

    /**
     * Mark as interviewed
     */
    public function markInterviewed(int $id): bool
    {
        return $this->service->markInterviewed($id);
    }

    /**
     * Make offer
     */
    public function makeOffer(int $id): bool
    {
        return $this->service->makeOffer($id);
    }

    /**
     * Accept offer
     */
    public function acceptOffer(int $id): bool
    {
        return $this->service->acceptOffer($id);
    }

    /**
     * Withdraw application
     */
    public function withdraw(int $id): bool
    {
        return $this->service->withdraw($id);
    }

    /**
     * Move to under review
     */
    public function moveToUnderReview(int $id): bool
    {
        return $this->service->moveToUnderReview($id);
    }
}
