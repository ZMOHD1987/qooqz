<?php
declare(strict_types=1);

final class JobsController
{
    private JobsService $service;

    public function __construct(JobsService $service)
    {
        $this->service = $service;
    }

    /**
     * List jobs with filters, ordering, and pagination
     *
     * @param int|null $limit
     * @param int|null $offset
     * @param array $filters
     * @param string $orderBy
     * @param string $orderDir
     * @param string $lang
     * @return array
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
     * Get a single job by ID
     *
     * @param int $id
     * @param string $lang
     * @return array|null
     */
    public function get(int $id, string $lang = 'ar'): ?array
    {
        return $this->service->get($id, $lang);
    }

    /**
     * Get a single job by slug
     *
     * @param string $slug
     * @param string $lang
     * @return array|null
     */
    public function getBySlug(string $slug, string $lang = 'ar'): ?array
    {
        return $this->service->getBySlug($slug, $lang);
    }

    /**
     * Get all translations for a job
     *
     * @param int $jobId
     * @return array
     */
    public function getTranslations(int $jobId): array
    {
        return $this->service->getTranslations($jobId);
    }

    /**
     * Create a new job
     *
     * @param array $data
     * @return int New job ID
     */
    public function create(array $data): int
    {
        return $this->service->create($data);
    }

    /**
     * Update an existing job
     *
     * @param array $data Must include 'id'
     * @return int Updated job ID
     */
    public function update(array $data): int
    {
        return $this->service->update($data);
    }

    /**
     * Delete a job by ID
     *
     * @param int $id
     * @return bool
     */
    public function delete(int $id): bool
    {
        return $this->service->delete($id);
    }

    /**
     * Save or update translation for a job
     *
     * @param int $jobId
     * @param string $languageCode
     * @param array $data
     * @return bool
     */
    public function saveTranslation(int $jobId, string $languageCode, array $data): bool
    {
        return $this->service->saveTranslation($jobId, $languageCode, $data);
    }

    /**
     * Delete translation
     *
     * @param int $jobId
     * @param string $languageCode
     * @return bool
     */
    public function deleteTranslation(int $jobId, string $languageCode): bool
    {
        return $this->service->deleteTranslation($jobId, $languageCode);
    }

    /**
     * Increment view count
     *
     * @param int $id
     * @return bool
     */
    public function incrementViews(int $id): bool
    {
        return $this->service->incrementViews($id);
    }

    /**
     * Increment applications count
     *
     * @param int $id
     * @return bool
     */
    public function incrementApplications(int $id): bool
    {
        return $this->service->incrementApplications($id);
    }

    /**
     * Update job status
     *
     * @param int $id
     * @param string $status
     * @return bool
     */
    public function updateStatus(int $id, string $status): bool
    {
        return $this->service->updateStatus($id, $status);
    }

    /**
     * Publish job
     *
     * @param int $id
     * @return bool
     */
    public function publish(int $id): bool
    {
        return $this->service->publish($id);
    }

    /**
     * Close job
     *
     * @param int $id
     * @return bool
     */
    public function close(int $id): bool
    {
        return $this->service->close($id);
    }

    /**
     * Mark job as filled
     *
     * @param int $id
     * @return bool
     */
    public function markAsFilled(int $id): bool
    {
        return $this->service->markAsFilled($id);
    }

    /**
     * Cancel job
     *
     * @param int $id
     * @return bool
     */
    public function cancel(int $id): bool
    {
        return $this->service->cancel($id);
    }

    /**
     * Get featured jobs
     *
     * @param int $limit
     * @param string $lang
     * @return array
     */
    public function getFeatured(int $limit = 10, string $lang = 'ar'): array
    {
        return $this->service->getFeatured($limit, $lang);
    }

    /**
     * Get urgent jobs
     *
     * @param int $limit
     * @param string $lang
     * @return array
     */
    public function getUrgent(int $limit = 10, string $lang = 'ar'): array
    {
        return $this->service->getUrgent($limit, $lang);
    }

    /**
     * Get remote jobs
     *
     * @param int $limit
     * @param string $lang
     * @return array
     */
    public function getRemote(int $limit = 10, string $lang = 'ar'): array
    {
        return $this->service->getRemote($limit, $lang);
    }

    /**
     * Search jobs by keyword
     *
     * @param string $keyword
     * @param int|null $limit
     * @param int|null $offset
     * @param string $lang
     * @return array
     */
    public function search(
        string $keyword,
        ?int $limit = null,
        ?int $offset = null,
        string $lang = 'ar'
    ): array {
        $items = $this->service->search($keyword, $limit, $offset, $lang);
        $total = $this->service->count(['search' => $keyword, 'status' => 'published'], $lang);

        return [
            'items' => $items,
            'total' => $total
        ];
    }
}
