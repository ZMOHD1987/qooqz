<?php
declare(strict_types=1);

final class JobApplicationsService
{
    private PdoJobApplicationsRepository $repo;
    private $validator;

    public function __construct(PdoJobApplicationsRepository $repo, $validator = null)
    {
        $this->repo = $repo;
        $this->validator = $validator;
    }

    /**
     * List applications with filters, ordering, and pagination
     */
    public function list(
        ?int $limit,
        ?int $offset,
        array $filters,
        string $orderBy,
        string $orderDir,
        string $lang = 'ar'
    ): array {
        return $this->repo->all($limit, $offset, $filters, $orderBy, $orderDir, $lang);
    }

    /**
     * Count applications with filters
     */
    public function count(array $filters = [], string $lang = 'ar'): int
    {
        return $this->repo->count($filters, $lang);
    }

    /**
     * Get single application by ID
     */
    public function get(int $id, string $lang = 'ar'): ?array
    {
        return $this->repo->find($id, $lang);
    }

    /**
     * Get applications by job ID
     */
    public function getByJob(int $jobId, string $lang = 'ar'): array
    {
        return $this->repo->getByJob($jobId, $lang);
    }

    /**
     * Get applications by user ID
     */
    public function getByUser(int $userId, string $lang = 'ar'): array
    {
        return $this->repo->getByUser($userId, $lang);
    }

    /**
     * Check if user already applied
     */
    public function hasApplied(int $jobId, int $userId): bool
    {
        return $this->repo->hasApplied($jobId, $userId);
    }

    /**
     * Get statistics for a job
     */
    public function getStatistics(int $jobId): array
    {
        return $this->repo->getStatistics($jobId);
    }

    /**
     * Create new application
     */
    public function create(array $data): int
    {
        // Validate
        if ($this->validator) {
            $this->validator->validate($data, false);
        }

        // Check if user already applied
        if (isset($data['job_id']) && isset($data['user_id'])) {
            if ($this->hasApplied((int)$data['job_id'], (int)$data['user_id'])) {
                throw new RuntimeException('User has already applied for this job.');
            }
        }

        // Save application
        return $this->repo->save($data);
    }

    /**
     * Update existing application
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
     * Delete application
     */
    public function delete(int $id): bool
    {
        return $this->repo->delete($id);
    }

    /**
     * Update application status
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
     * Update rating
     */
    public function updateRating(int $id, int $rating, ?int $reviewedBy = null): bool
    {
        // Validate rating
        if ($this->validator) {
            $this->validator->validateRating($rating);
        }

        return $this->repo->updateRating($id, $rating, $reviewedBy);
    }

    /**
     * Add review/notes
     */
    public function addReview(int $id, string $notes, int $reviewedBy): bool
    {
        if (trim($notes) === '') {
            throw new InvalidArgumentException('Review notes cannot be empty.');
        }

        return $this->repo->addReview($id, $notes, $reviewedBy);
    }

    /**
     * Shortlist application
     */
    public function shortlist(int $id): bool
    {
        return $this->updateStatus($id, 'shortlisted');
    }

    /**
     * Reject application
     */
    public function reject(int $id): bool
    {
        return $this->updateStatus($id, 'rejected');
    }

    /**
     * Schedule interview
     */
    public function scheduleInterview(int $id): bool
    {
        return $this->updateStatus($id, 'interview_scheduled');
    }

    /**
     * Mark as interviewed
     */
    public function markInterviewed(int $id): bool
    {
        return $this->updateStatus($id, 'interviewed');
    }

    /**
     * Make offer
     */
    public function makeOffer(int $id): bool
    {
        return $this->updateStatus($id, 'offered');
    }

    /**
     * Accept offer
     */
    public function acceptOffer(int $id): bool
    {
        return $this->updateStatus($id, 'accepted');
    }

    /**
     * Withdraw application
     */
    public function withdraw(int $id): bool
    {
        return $this->updateStatus($id, 'withdrawn');
    }

    /**
     * Move to under review
     */
    public function moveToUnderReview(int $id): bool
    {
        return $this->updateStatus($id, 'under_review');
    }
}
