<?php
declare(strict_types=1);

namespace App\Models\Jobs\Validators;

use InvalidArgumentException;

final class JobSkillsValidator
{
    private const PROFICIENCY_LEVELS = [
        'basic', 'intermediate', 'advanced', 'expert'
    ];

    public function validate(array $data, bool $isUpdate = false): void
    {
        // التحقق من الحقول المطلوبة
        if (!$isUpdate) {
            $required = ['job_id', 'skill_name'];
            foreach ($required as $field) {
                if (!isset($data[$field]) || $data[$field] === '') {
                    throw new InvalidArgumentException("Field '{$field}' is required.");
                }
            }
        }

        // التحقق من job_id
        if (isset($data['job_id'])) {
            if (!is_numeric($data['job_id']) || $data['job_id'] <= 0) {
                throw new InvalidArgumentException("job_id must be a positive integer.");
            }
        }

        // التحقق من skill_name
        if (isset($data['skill_name'])) {
            if (trim($data['skill_name']) === '') {
                throw new InvalidArgumentException("skill_name cannot be empty.");
            }
            if (strlen($data['skill_name']) > 100) {
                throw new InvalidArgumentException("skill_name cannot exceed 100 characters.");
            }
        }

        // التحقق من proficiency_level
        if (isset($data['proficiency_level'])) {
            if (!in_array($data['proficiency_level'], self::PROFICIENCY_LEVELS, true)) {
                throw new InvalidArgumentException(
                    "Invalid proficiency_level. Allowed: " . implode(', ', self::PROFICIENCY_LEVELS)
                );
            }
        }

        // التحقق من is_required
        if (isset($data['is_required'])) {
            if (!in_array((int)$data['is_required'], [0, 1], true)) {
                throw new InvalidArgumentException("is_required must be 0 or 1.");
            }
        }
    }

    /**
     * التحقق من صحة بيانات التحديث الجماعي
     */
    public function validateBulkUpdate(array $skills): void
    {
        if (empty($skills)) {
            throw new InvalidArgumentException("Skills array cannot be empty.");
        }

        foreach ($skills as $skill) {
            if (!isset($skill['skill_name']) || trim($skill['skill_name']) === '') {
                throw new InvalidArgumentException("Each skill must have a non-empty skill_name.");
            }
            if (isset($skill['proficiency_level']) && !in_array($skill['proficiency_level'], self::PROFICIENCY_LEVELS, true)) {
                throw new InvalidArgumentException("Invalid proficiency_level in bulk update.");
            }
            if (isset($skill['is_required']) && !in_array((int)$skill['is_required'], [0, 1], true)) {
                throw new InvalidArgumentException("is_required must be 0 or 1 in bulk update.");
            }
        }
    }

    /**
     * التحقق من فلاتر القائمة
     */
    public function validateFilters(array $filters): void
    {
        if (isset($filters['job_id']) && (!is_numeric($filters['job_id']) || $filters['job_id'] <= 0)) {
            throw new InvalidArgumentException("job_id filter must be a positive integer.");
        }

        if (isset($filters['proficiency_level']) && !in_array($filters['proficiency_level'], self::PROFICIENCY_LEVELS, true)) {
            throw new InvalidArgumentException("Invalid proficiency_level filter.");
        }

        if (isset($filters['is_required']) && !in_array((int)$filters['is_required'], [0, 1], true)) {
            throw new InvalidArgumentException("is_required filter must be 0 or 1.");
        }
    }

    /**
     * الحصول على مستويات الإتقان الصالحة
     */
    public function getValidProficiencyLevels(): array
    {
        return self::PROFICIENCY_LEVELS;
    }
}