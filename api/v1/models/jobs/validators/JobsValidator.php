<?php
declare(strict_types=1);

namespace App\Models\Jobs\Validators;

use InvalidArgumentException;

final class JobsValidator
{
    // الحقول المطلوبة
    private const REQUIRED_FIELDS = ['entity_id', 'job_type', 'experience_level', 'country_id'];

    // القيم المسموحة للـ ENUM fields
    private const JOB_TYPES = ['full_time', 'part_time', 'contract', 'temporary', 'internship', 'freelance', 'remote'];
    private const EMPLOYMENT_TYPES = ['permanent', 'temporary', 'seasonal'];
    private const APPLICATION_FORM_TYPES = ['simple', 'custom', 'external'];
    private const EXPERIENCE_LEVELS = ['entry', 'junior', 'mid', 'senior', 'executive', 'director'];
    private const SALARY_PERIODS = ['hourly', 'daily', 'weekly', 'monthly', 'yearly'];
    private const STATUSES = ['draft', 'published', 'closed', 'filled', 'cancelled'];

    public function validate(array $data, bool $isUpdate = false): void
    {
        // التحقق من الحقول المطلوبة (فقط عند الإنشاء)
        if (!$isUpdate) {
            foreach (self::REQUIRED_FIELDS as $field) {
                if (!isset($data[$field]) || $data[$field] === '') {
                    throw new InvalidArgumentException("Field '{$field}' is required.");
                }
            }

            // job_title مطلوب في الترجمة
            if (empty($data['job_title'])) {
                throw new InvalidArgumentException("Field 'job_title' is required.");
            }

            // description مطلوب في الترجمة
            if (empty($data['description'])) {
                throw new InvalidArgumentException("Field 'description' is required.");
            }
        }

        // التحقق من entity_id
        if (isset($data['entity_id']) && (!is_numeric($data['entity_id']) || $data['entity_id'] <= 0)) {
            throw new InvalidArgumentException("entity_id must be a positive integer.");
        }

        // التحقق من slug
        if (isset($data['slug'])) {
            if (strlen($data['slug']) > 255) {
                throw new InvalidArgumentException("Slug must be at most 255 characters.");
            }
            if (!preg_match('/^[a-z0-9\-]+$/i', $data['slug'])) {
                throw new InvalidArgumentException("Slug must contain only letters, numbers, and hyphens.");
            }
        }

        // التحقق من job_type
        if (isset($data['job_type']) && !in_array($data['job_type'], self::JOB_TYPES, true)) {
            throw new InvalidArgumentException("Invalid job_type. Allowed: " . implode(', ', self::JOB_TYPES));
        }

        // التحقق من employment_type
        if (isset($data['employment_type']) && !in_array($data['employment_type'], self::EMPLOYMENT_TYPES, true)) {
            throw new InvalidArgumentException("Invalid employment_type. Allowed: " . implode(', ', self::EMPLOYMENT_TYPES));
        }

        // التحقق من application_form_type
        if (isset($data['application_form_type']) && !in_array($data['application_form_type'], self::APPLICATION_FORM_TYPES, true)) {
            throw new InvalidArgumentException("Invalid application_form_type. Allowed: " . implode(', ', self::APPLICATION_FORM_TYPES));
        }

        // التحقق من external_application_url
        if (isset($data['application_form_type']) && $data['application_form_type'] === 'external') {
            if (empty($data['external_application_url'])) {
                throw new InvalidArgumentException("external_application_url is required when application_form_type is 'external'.");
            }
            if (!filter_var($data['external_application_url'], FILTER_VALIDATE_URL)) {
                throw new InvalidArgumentException("external_application_url must be a valid URL.");
            }
        }

        // التحقق من experience_level
        if (isset($data['experience_level']) && !in_array($data['experience_level'], self::EXPERIENCE_LEVELS, true)) {
            throw new InvalidArgumentException("Invalid experience_level. Allowed: " . implode(', ', self::EXPERIENCE_LEVELS));
        }

        // التحقق من category
        if (isset($data['category']) && strlen($data['category']) > 100) {
            throw new InvalidArgumentException("Category must be at most 100 characters.");
        }

        // التحقق من department
        if (isset($data['department']) && strlen($data['department']) > 100) {
            throw new InvalidArgumentException("Department must be at most 100 characters.");
        }

        // التحقق من positions_available
        if (isset($data['positions_available'])) {
            if (!is_numeric($data['positions_available']) || $data['positions_available'] < 1) {
                throw new InvalidArgumentException("positions_available must be at least 1.");
            }
        }

        // التحقق من الراتب
        if (isset($data['salary_min']) && isset($data['salary_max'])) {
            if (!is_numeric($data['salary_min']) || !is_numeric($data['salary_max'])) {
                throw new InvalidArgumentException("Salary values must be numeric.");
            }
            if ($data['salary_min'] < 0 || $data['salary_max'] < 0) {
                throw new InvalidArgumentException("Salary values cannot be negative.");
            }
            if ($data['salary_min'] > $data['salary_max']) {
                throw new InvalidArgumentException("salary_min cannot be greater than salary_max.");
            }
        }

        // التحقق من salary_currency
        if (isset($data['salary_currency']) && strlen($data['salary_currency']) > 8) {
            throw new InvalidArgumentException("salary_currency must be at most 8 characters.");
        }

        // التحقق من salary_period
        if (isset($data['salary_period']) && !in_array($data['salary_period'], self::SALARY_PERIODS, true)) {
            throw new InvalidArgumentException("Invalid salary_period. Allowed: " . implode(', ', self::SALARY_PERIODS));
        }

        // التحقق من salary_negotiable
        if (isset($data['salary_negotiable']) && !in_array((int)$data['salary_negotiable'], [0, 1], true)) {
            throw new InvalidArgumentException("salary_negotiable must be 0 or 1.");
        }

        // التحقق من country_id
        if (isset($data['country_id']) && (!is_numeric($data['country_id']) || $data['country_id'] <= 0)) {
            throw new InvalidArgumentException("country_id must be a positive integer.");
        }

        // التحقق من city_id
        if (isset($data['city_id']) && $data['city_id'] !== null && (!is_numeric($data['city_id']) || $data['city_id'] <= 0)) {
            throw new InvalidArgumentException("city_id must be a positive integer.");
        }

        // التحقق من work_location
        if (isset($data['work_location']) && strlen($data['work_location']) > 255) {
            throw new InvalidArgumentException("work_location must be at most 255 characters.");
        }

        // التحقق من is_remote
        if (isset($data['is_remote']) && !in_array((int)$data['is_remote'], [0, 1], true)) {
            throw new InvalidArgumentException("is_remote must be 0 or 1.");
        }

        // التحقق من status
        if (isset($data['status']) && !in_array($data['status'], self::STATUSES, true)) {
            throw new InvalidArgumentException("Invalid status. Allowed: " . implode(', ', self::STATUSES));
        }

        // التحقق من application_deadline
        if (isset($data['application_deadline']) && !empty($data['application_deadline'])) {
            if (!$this->isValidDateTime($data['application_deadline'])) {
                throw new InvalidArgumentException("application_deadline must be a valid datetime (Y-m-d H:i:s).");
            }
            // التحقق من أن تاريخ الانتهاء في المستقبل
            if (strtotime($data['application_deadline']) < time()) {
                throw new InvalidArgumentException("application_deadline must be in the future.");
            }
        }

        // التحقق من start_date
        if (isset($data['start_date']) && !empty($data['start_date'])) {
            if (!$this->isValidDate($data['start_date'])) {
                throw new InvalidArgumentException("start_date must be a valid date (Y-m-d).");
            }
        }

        // التحقق من الأعداد الصحيحة
        foreach (['views_count', 'applications_count'] as $field) {
            if (isset($data[$field]) && (!is_numeric($data[$field]) || $data[$field] < 0)) {
                throw new InvalidArgumentException("{$field} must be a non-negative integer.");
            }
        }

        // التحقق من الحقول المنطقية
        foreach (['is_featured', 'is_urgent'] as $field) {
            if (isset($data[$field]) && !in_array((int)$data[$field], [0, 1], true)) {
                throw new InvalidArgumentException("{$field} must be 0 or 1.");
            }
        }

        // التحقق من job_title في الترجمة
        if (isset($data['job_title'])) {
            if (strlen($data['job_title']) > 255) {
                throw new InvalidArgumentException("job_title must be at most 255 characters.");
            }
            if (trim($data['job_title']) === '') {
                throw new InvalidArgumentException("job_title cannot be empty.");
            }
        }

        // التحقق من description في الترجمة
        if (isset($data['description']) && trim($data['description']) === '') {
            throw new InvalidArgumentException("description cannot be empty.");
        }
    }

    /**
     * التحقق من صحة التاريخ والوقت
     */
    private function isValidDateTime(string $datetime): bool
    {
        $d = \DateTime::createFromFormat('Y-m-d H:i:s', $datetime);
        return $d && $d->format('Y-m-d H:i:s') === $datetime;
    }

    /**
     * التحقق من صحة التاريخ
     */
    private function isValidDate(string $date): bool
    {
        $d = \DateTime::createFromFormat('Y-m-d', $date);
        return $d && $d->format('Y-m-d') === $date;
    }

    /**
     * التحقق من صحة بيانات الترجمة
     */
    public function validateTranslation(array $data): void
    {
        if (empty($data['language_code'])) {
            throw new InvalidArgumentException("language_code is required.");
        }

        if (strlen($data['language_code']) > 8) {
            throw new InvalidArgumentException("language_code must be at most 8 characters.");
        }

        if (empty($data['job_title'])) {
            throw new InvalidArgumentException("job_title is required in translation.");
        }

        if (strlen($data['job_title']) > 255) {
            throw new InvalidArgumentException("job_title must be at most 255 characters.");
        }

        if (empty($data['description'])) {
            throw new InvalidArgumentException("description is required in translation.");
        }
    }
}
