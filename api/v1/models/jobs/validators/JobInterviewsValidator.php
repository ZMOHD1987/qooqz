<?php
declare(strict_types=1);

namespace App\Models\Jobs\Validators;

use InvalidArgumentException;

final class JobInterviewsValidator
{
    private const INTERVIEW_TYPES = [
        'phone', 'video', 'in_person', 'technical', 'hr', 'final'
    ];

    private const STATUSES = [
        'scheduled', 'confirmed', 'completed', 'cancelled', 'rescheduled', 'no_show'
    ];

    public function validate(array $data, bool $isUpdate = false): void
    {
        // التحقق من الحقول المطلوبة
        if (!$isUpdate) {
            $required = ['application_id', 'interview_type', 'interview_date'];
            foreach ($required as $field) {
                if (!isset($data[$field]) || $data[$field] === '') {
                    throw new InvalidArgumentException("Field '{$field}' is required.");
                }
            }
        }

        // التحقق من application_id
        if (isset($data['application_id'])) {
            if (!is_numeric($data['application_id']) || $data['application_id'] <= 0) {
                throw new InvalidArgumentException("application_id must be a positive integer.");
            }
        }

        // التحقق من interview_type
        if (isset($data['interview_type'])) {
            if (!in_array($data['interview_type'], self::INTERVIEW_TYPES, true)) {
                throw new InvalidArgumentException(
                    "Invalid interview_type. Allowed: " . implode(', ', self::INTERVIEW_TYPES)
                );
            }
        }

        // التحقق من interview_date
        if (isset($data['interview_date']) && !empty($data['interview_date'])) {
            if (!$this->isValidDateTime($data['interview_date'])) {
                throw new InvalidArgumentException("interview_date must be a valid datetime (Y-m-d H:i:s).");
            }

            // التحقق من أن التاريخ في المستقبل (للمقابلات الجديدة)
            if (!$isUpdate && strtotime($data['interview_date']) < time()) {
                throw new InvalidArgumentException("interview_date must be in the future.");
            }
        }

        // التحقق من interview_duration
        if (isset($data['interview_duration']) && $data['interview_duration'] !== null) {
            if (!is_numeric($data['interview_duration']) || $data['interview_duration'] <= 0) {
                throw new InvalidArgumentException("interview_duration must be a positive integer (in minutes).");
            }
            if ($data['interview_duration'] > 480) { // 8 ساعات كحد أقصى
                throw new InvalidArgumentException("interview_duration cannot exceed 480 minutes (8 hours).");
            }
        }

        // التحقق من location
        if (isset($data['location']) && $data['location'] !== null) {
            if (strlen($data['location']) > 500) {
                throw new InvalidArgumentException("location must be at most 500 characters.");
            }
        }

        // التحقق من meeting_link
        if (isset($data['meeting_link']) && $data['meeting_link'] !== null && $data['meeting_link'] !== '') {
            if (strlen($data['meeting_link']) > 500) {
                throw new InvalidArgumentException("meeting_link must be at most 500 characters.");
            }
            if (!filter_var($data['meeting_link'], FILTER_VALIDATE_URL)) {
                throw new InvalidArgumentException("meeting_link must be a valid URL.");
            }
        }

        // التحقق من interviewer_name
        if (isset($data['interviewer_name']) && $data['interviewer_name'] !== null) {
            if (strlen($data['interviewer_name']) > 255) {
                throw new InvalidArgumentException("interviewer_name must be at most 255 characters.");
            }
        }

        // التحقق من interviewer_email
        if (isset($data['interviewer_email']) && $data['interviewer_email'] !== null && $data['interviewer_email'] !== '') {
            if (strlen($data['interviewer_email']) > 191) {
                throw new InvalidArgumentException("interviewer_email must be at most 191 characters.");
            }
            if (!filter_var($data['interviewer_email'], FILTER_VALIDATE_EMAIL)) {
                throw new InvalidArgumentException("Invalid interviewer_email format.");
            }
        }

        // التحقق من status
        if (isset($data['status'])) {
            if (!in_array($data['status'], self::STATUSES, true)) {
                throw new InvalidArgumentException(
                    "Invalid status. Allowed: " . implode(', ', self::STATUSES)
                );
            }
        }

        // التحقق من rating
        if (isset($data['rating']) && $data['rating'] !== null) {
            if (!is_numeric($data['rating']) || $data['rating'] < 1 || $data['rating'] > 5) {
                throw new InvalidArgumentException("rating must be between 1 and 5.");
            }
        }

        // التحقق من created_by
        if (isset($data['created_by']) && $data['created_by'] !== null) {
            if (!is_numeric($data['created_by']) || $data['created_by'] <= 0) {
                throw new InvalidArgumentException("created_by must be a positive integer.");
            }
        }

        // التحقق من متطلبات المقابلة حسب النوع
        if (isset($data['interview_type'])) {
            $this->validateByType($data['interview_type'], $data);
        }
    }

    /**
     * التحقق من المتطلبات حسب نوع المقابلة
     */
    private function validateByType(string $type, array $data): void
    {
        switch ($type) {
            case 'video':
                if (empty($data['meeting_link'])) {
                    throw new InvalidArgumentException("meeting_link is required for video interviews.");
                }
                break;

            case 'in_person':
                if (empty($data['location'])) {
                    throw new InvalidArgumentException("location is required for in-person interviews.");
                }
                break;
        }
    }

    /**
     * التحقق من صحة تحديث الحالة
     */
    public function validateStatusUpdate(string $status): void
    {
        if (!in_array($status, self::STATUSES, true)) {
            throw new InvalidArgumentException(
                "Invalid status. Allowed: " . implode(', ', self::STATUSES)
            );
        }
    }

    /**
     * التحقق من صحة إعادة الجدولة
     */
    public function validateReschedule(array $data): void
    {
        if (empty($data['new_date'])) {
            throw new InvalidArgumentException("new_date is required for rescheduling.");
        }

        if (!$this->isValidDateTime($data['new_date'])) {
            throw new InvalidArgumentException("new_date must be a valid datetime (Y-m-d H:i:s).");
        }

        if (strtotime($data['new_date']) < time()) {
            throw new InvalidArgumentException("new_date must be in the future.");
        }

        if (isset($data['new_duration'])) {
            if (!is_numeric($data['new_duration']) || $data['new_duration'] <= 0) {
                throw new InvalidArgumentException("new_duration must be a positive integer.");
            }
        }
    }

    /**
     * التحقق من صحة التقييم
     */
    public function validateRating(int $rating): void
    {
        if ($rating < 1 || $rating > 5) {
            throw new InvalidArgumentException("rating must be between 1 and 5.");
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
}
