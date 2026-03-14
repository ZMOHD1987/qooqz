<?php
declare(strict_types=1);

namespace App\Models\Jobs\Validators {
    use InvalidArgumentException;

    final class JobAlertsValidator
    {
        // القيم المسموحة
        private const VALID_FREQUENCIES = ['instant', 'daily', 'weekly'];
        private const VALID_JOB_TYPES = ['full-time', 'part-time', 'contract', 'freelance', 'internship', 'remote'];
        private const VALID_EXPERIENCE_LEVELS = ['entry', 'junior', 'mid', 'senior', 'lead', 'executive'];

        /**
         * Validate job alert data
         */
        public function validate(array $data, bool $isUpdate = false): void
        {
            // التحقق من الحقول المطلوبة عند الإنشاء
            if (!$isUpdate) {
                if (!isset($data['user_id']) || empty($data['user_id'])) {
                    throw new InvalidArgumentException("Field 'user_id' is required.");
                }
                if (!isset($data['alert_name']) || empty(trim($data['alert_name']))) {
                    throw new InvalidArgumentException("Field 'alert_name' is required.");
                }
            }

            // التحقق من alert_name
            if (isset($data['alert_name'])) {
                if (empty(trim($data['alert_name']))) {
                    throw new InvalidArgumentException("Alert name cannot be empty.");
                }
                if (strlen($data['alert_name']) > 255) {
                    throw new InvalidArgumentException("Alert name must be at most 255 characters.");
                }
            }

            // التحقق من keywords
            if (isset($data['keywords']) && $data['keywords'] !== null) {
                if (strlen($data['keywords']) > 500) {
                    throw new InvalidArgumentException("Keywords must be at most 500 characters.");
                }
            }

            // التحقق من job_type
            if (isset($data['job_type']) && $data['job_type'] !== null) {
                if (strlen($data['job_type']) > 100) {
                    throw new InvalidArgumentException("Job type must be at most 100 characters.");
                }
            }

            // التحقق من experience_level
            if (isset($data['experience_level']) && $data['experience_level'] !== null) {
                if (strlen($data['experience_level']) > 100) {
                    throw new InvalidArgumentException("Experience level must be at most 100 characters.");
                }
            }

            // التحقق من country_id
            if (isset($data['country_id']) && $data['country_id'] !== null) {
                if (!is_numeric($data['country_id']) || (int)$data['country_id'] < 1) {
                    throw new InvalidArgumentException("country_id must be a valid positive integer.");
                }
            }

            // التحقق من city_id
            if (isset($data['city_id']) && $data['city_id'] !== null) {
                if (!is_numeric($data['city_id']) || (int)$data['city_id'] < 1) {
                    throw new InvalidArgumentException("city_id must be a valid positive integer.");
                }
            }

            // التحقق من salary_min
            if (isset($data['salary_min']) && $data['salary_min'] !== null) {
                if (!is_numeric($data['salary_min'])) {
                    throw new InvalidArgumentException("salary_min must be numeric.");
                }
                if ((float)$data['salary_min'] < 0) {
                    throw new InvalidArgumentException("salary_min cannot be negative.");
                }
            }

            // التحقق من is_active
            if (isset($data['is_active']) && !in_array((int)$data['is_active'], [0, 1], true)) {
                throw new InvalidArgumentException("is_active must be 0 or 1.");
            }

            // التحقق من frequency
            if (isset($data['frequency']) && $data['frequency'] !== null) {
                if (!in_array($data['frequency'], self::VALID_FREQUENCIES, true)) {
                    throw new InvalidArgumentException(
                        "frequency must be one of: " . implode(', ', self::VALID_FREQUENCIES)
                    );
                }
            }

            // التحقق من user_id
            if (isset($data['user_id'])) {
                if (!is_numeric($data['user_id']) || (int)$data['user_id'] < 1) {
                    throw new InvalidArgumentException("user_id must be a valid positive integer.");
                }
            }
        }

        /**
         * Validate filter parameters
         */
        public function validateFilters(array $filters): void
        {
            // التحقق من id
            if (isset($filters['id'])) {
                if (!is_numeric($filters['id']) || (int)$filters['id'] < 1) {
                    throw new InvalidArgumentException("Filter 'id' must be a positive integer.");
                }
            }

            // التحقق من user_id
            if (isset($filters['user_id'])) {
                if (!is_numeric($filters['user_id']) || (int)$filters['user_id'] < 1) {
                    throw new InvalidArgumentException("Filter 'user_id' must be a positive integer.");
                }
            }

            // التحقق من country_id
            if (isset($filters['country_id'])) {
                if (!is_numeric($filters['country_id']) || (int)$filters['country_id'] < 1) {
                    throw new InvalidArgumentException("Filter 'country_id' must be a positive integer.");
                }
            }

            // التحقق من city_id
            if (isset($filters['city_id'])) {
                if (!is_numeric($filters['city_id']) || (int)$filters['city_id'] < 1) {
                    throw new InvalidArgumentException("Filter 'city_id' must be a positive integer.");
                }
            }

            // التحقق من is_active
            if (isset($filters['is_active'])) {
                if (!in_array((int)$filters['is_active'], [0, 1], true)) {
                    throw new InvalidArgumentException("Filter 'is_active' must be 0 or 1.");
                }
            }

            // التحقق من frequency
            if (isset($filters['frequency']) && !in_array($filters['frequency'], self::VALID_FREQUENCIES, true)) {
                throw new InvalidArgumentException(
                    "Filter 'frequency' must be one of: " . implode(', ', self::VALID_FREQUENCIES)
                );
            }

            // التحقق من salary range
            if (isset($filters['salary_min']) && !is_numeric($filters['salary_min'])) {
                throw new InvalidArgumentException("Filter 'salary_min' must be numeric.");
            }

            if (isset($filters['salary_max']) && !is_numeric($filters['salary_max'])) {
                throw new InvalidArgumentException("Filter 'salary_max' must be numeric.");
            }

            // التحقق من search
            if (isset($filters['search']) && strlen($filters['search']) > 500) {
                throw new InvalidArgumentException("Search term must be at most 500 characters.");
            }
        }

        /**
         * Get valid frequencies
         */
        public function getValidFrequencies(): array
        {
            return self::VALID_FREQUENCIES;
        }

        /**
         * Get valid job types
         */
        public function getValidJobTypes(): array
        {
            return self::VALID_JOB_TYPES;
        }

        /**
         * Get valid experience levels
         */
        public function getValidExperienceLevels(): array
        {
            return self::VALID_EXPERIENCE_LEVELS;
        }
    }
}
