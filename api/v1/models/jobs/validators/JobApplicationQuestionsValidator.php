<?php
declare(strict_types=1);

namespace App\Models\Jobs\Validators;

use InvalidArgumentException;

final class JobApplicationQuestionsValidator
{
    private const QUESTION_TYPES = [
        'text', 'textarea', 'select', 'multiselect',
        'radio', 'checkbox', 'file', 'date', 'number'
    ];

    public function validate(array $data, bool $isUpdate = false): void
    {
        // التحقق من الحقول المطلوبة
        if (!$isUpdate) {
            $required = ['job_id', 'question_text'];
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

        // التحقق من question_text
        if (isset($data['question_text'])) {
            if (trim($data['question_text']) === '') {
                throw new InvalidArgumentException("question_text cannot be empty.");
            }
        }

        // التحقق من question_type
        if (isset($data['question_type'])) {
            if (!in_array($data['question_type'], self::QUESTION_TYPES, true)) {
                throw new InvalidArgumentException(
                    "Invalid question_type. Allowed: " . implode(', ', self::QUESTION_TYPES)
                );
            }
        }

        // التحقق من options للأسئلة التي تحتاج خيارات
        if (isset($data['question_type'])) {
            $typesRequiringOptions = ['select', 'multiselect', 'radio', 'checkbox'];
            
            if (in_array($data['question_type'], $typesRequiringOptions, true)) {
                if (empty($data['options'])) {
                    throw new InvalidArgumentException(
                        "options are required for question type: {$data['question_type']}"
                    );
                }

                // التحقق من صحة options
                $this->validateOptions($data['options']);
            }
        }

        // التحقق من is_required
        if (isset($data['is_required'])) {
            if (!in_array((int)$data['is_required'], [0, 1], true)) {
                throw new InvalidArgumentException("is_required must be 0 or 1.");
            }
        }

        // التحقق من sort_order
        if (isset($data['sort_order']) && $data['sort_order'] !== null) {
            if (!is_numeric($data['sort_order']) || $data['sort_order'] < 0) {
                throw new InvalidArgumentException("sort_order must be a non-negative integer.");
            }
        }
    }

    /**
     * التحقق من صحة الخيارات
     */
    private function validateOptions($options): void
    {
        // إذا كانت JSON string
        if (is_string($options)) {
            $decoded = json_decode($options, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new InvalidArgumentException("options must be valid JSON.");
            }
            $options = $decoded;
        }

        // يجب أن تكون array
        if (!is_array($options)) {
            throw new InvalidArgumentException("options must be an array.");
        }

        // يجب أن تحتوي على خيار واحد على الأقل
        if (empty($options)) {
            throw new InvalidArgumentException("options cannot be empty.");
        }

        // التحقق من كل خيار
        foreach ($options as $option) {
            if (is_array($option)) {
                // إذا كان كل خيار object مع value و label
                if (!isset($option['value']) || !isset($option['label'])) {
                    throw new InvalidArgumentException(
                        "Each option must have 'value' and 'label' keys."
                    );
                }
            } elseif (!is_string($option) && !is_numeric($option)) {
                throw new InvalidArgumentException(
                    "Options must be strings, numbers, or objects with value and label."
                );
            }
        }
    }

    /**
     * التحقق من صحة بيانات إعادة الترتيب
     */
    public function validateReorder(array $orderData): void
    {
        if (empty($orderData)) {
            throw new InvalidArgumentException("Order data cannot be empty.");
        }

        foreach ($orderData as $item) {
            if (!isset($item['id']) || !isset($item['sort_order'])) {
                throw new InvalidArgumentException(
                    "Each item must have 'id' and 'sort_order' fields."
                );
            }

            if (!is_numeric($item['id']) || $item['id'] <= 0) {
                throw new InvalidArgumentException("id must be a positive integer.");
            }

            if (!is_numeric($item['sort_order']) || $item['sort_order'] < 0) {
                throw new InvalidArgumentException("sort_order must be a non-negative integer.");
            }
        }
    }
}
