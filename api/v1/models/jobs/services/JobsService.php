<?php
declare(strict_types=1);

final class JobsService
{
    private PdoJobsRepository $repo;
    private $validator;

    public function __construct(PdoJobsRepository $repo, $validator = null)
    {
        $this->repo = $repo;
        $this->validator = $validator;
    }

    /**
     * List jobs with filters, ordering, and pagination
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
     * Count jobs with filters
     */
    public function count(array $filters = [], string $lang = 'ar'): int
    {
        return $this->repo->count($filters, $lang);
    }

    /**
     * Get single job by ID
     */
    public function get(int $id, string $lang = 'ar'): ?array
    {
        return $this->repo->find($id, $lang);
    }

    /**
     * Get single job by slug
     */
    public function getBySlug(string $slug, string $lang = 'ar'): ?array
    {
        return $this->repo->findBySlug($slug, $lang);
    }

    /**
     * Get all translations for a job
     */
    public function getTranslations(int $jobId): array
    {
        return $this->repo->getTranslations($jobId);
    }

    /**
     * Create new job
     */
    public function create(array $data): int
    {
        // Validate
        if ($this->validator) {
            $this->validator->validate($data, false);
        }

        // Save job
        $jobId = $this->repo->save($data);

        // Save translation if provided
        if ($jobId && !empty($data['job_title'])) {
            $lang = $data['language_code'] ?? 'ar';
            $this->saveTranslation($jobId, $lang, $data);
        }

        return $jobId;
    }

    /**
     * Update existing job
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

        // Update job
        $jobId = $this->repo->save($data);

        // Update translation if provided
        if (!empty($data['job_title'])) {
            $lang = $data['language_code'] ?? 'ar';
            $this->saveTranslation($jobId, $lang, $data);
        }

        return $jobId;
    }

    /**
     * Save or update translation
     */
    public function saveTranslation(int $jobId, string $languageCode, array $data): bool
    {
        // Validate translation data
        if ($this->validator) {
            $translationData = array_merge($data, ['language_code' => $languageCode]);
            $this->validator->validateTranslation($translationData);
        }

        return $this->repo->saveTranslation($jobId, $languageCode, $data);
    }

    /**
     * Delete translation
     */
    public function deleteTranslation(int $jobId, string $languageCode): bool
    {
        return $this->repo->deleteTranslation($jobId, $languageCode);
    }

    /**
     * Delete job
     */
    public function delete(int $id): bool
    {
        return $this->repo->delete($id);
    }

    /**
     * Increment view count
     */
    public function incrementViews(int $id): bool
    {
        return $this->repo->incrementViews($id);
    }

    /**
     * Increment applications count
     */
    public function incrementApplications(int $id): bool
    {
        return $this->repo->incrementApplications($id);
    }

    /**
     * Update job status
     */
    public function updateStatus(int $id, string $status): bool
    {
        return $this->repo->updateStatus($id, $status);
    }

    /**
     * Publish job
     */
    public function publish(int $id): bool
    {
        return $this->updateStatus($id, 'published');
    }

    /**
     * Close job
     */
    public function close(int $id): bool
    {
        return $this->updateStatus($id, 'closed');
    }

    /**
     * Mark job as filled
     */
    public function markAsFilled(int $id): bool
    {
        return $this->updateStatus($id, 'filled');
    }

    /**
     * Cancel job
     */
    public function cancel(int $id): bool
    {
        return $this->updateStatus($id, 'cancelled');
    }

    /**
     * Get featured jobs
     */
    public function getFeatured(int $limit = 10, string $lang = 'ar'): array
    {
        return $this->list(
            $limit,
            0,
            ['is_featured' => 1, 'status' => 'published'],
            'created_at',
            'DESC',
            $lang
        );
    }

    /**
     * Get urgent jobs
     */
    public function getUrgent(int $limit = 10, string $lang = 'ar'): array
    {
        return $this->list(
            $limit,
            0,
            ['is_urgent' => 1, 'status' => 'published'],
            'created_at',
            'DESC',
            $lang
        );
    }

    /**
     * Get remote jobs
     */
    public function getRemote(int $limit = 10, string $lang = 'ar'): array
    {
        return $this->list(
            $limit,
            0,
            ['is_remote' => 1, 'status' => 'published'],
            'created_at',
            'DESC',
            $lang
        );
    }

    /**
     * Search jobs by keyword
     */
    public function search(
        string $keyword,
        ?int $limit = null,
        ?int $offset = null,
        string $lang = 'ar'
    ): array {
        return $this->list(
            $limit,
            $offset,
            ['search' => $keyword, 'status' => 'published'],
            'created_at',
            'DESC',
            $lang
        );
    }
}
