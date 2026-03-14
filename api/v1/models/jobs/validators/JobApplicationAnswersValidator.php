<?php
declare(strict_types=1);

namespace App\Models\Jobs\Validators;

use InvalidArgumentException;

final class JobApplicationAnswersValidator
{
    public function validate(array $data, bool $isUpdate = false): void
    {
        // التحقق من الحقول المطلوبة
        if (!$isUpdate) {
            $required = ['application_id', 'question_id'];
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

        // التحقق من question_id
        if (isset($data['question_id'])) {
            if (!is_numeric($data['question_id']) || $data['question_id'] <= 0) {
                throw new InvalidArgumentException("question_id must be a positive integer.");
            }
        }

        // answer_text يمكن أن يكون null أو فارغ في بعض الحالات
        // لكن إذا كان السؤال مطلوباً، يجب التحقق من ذلك في منطق الأعمال
    }

    /**
     * التحقق من صحة الإجابات الجماعية
     */
    public function validateBulkAnswers(array $answers): void
    {
        if (empty($answers)) {
            throw new InvalidArgumentException("Answers array cannot be empty.");
        }

        foreach ($answers as $index => $answer) {
            if (!isset($answer['question_id'])) {
                throw new InvalidArgumentException("question_id is required for answer at index {$index}.");
            }

            if (!is_numeric($answer['question_id']) || $answer['question_id'] <= 0) {
                throw new InvalidArgumentException("Invalid question_id at index {$index}.");
            }
        }
    }

    /**
     * التحقق من إجابة سؤال معين بناءً على نوعه
     */
    public function validateAnswerByQuestionType(string $questionType, $answerValue, bool $isRequired): void
    {
        // إذا كان السؤال مطلوباً ولا توجد إجابة
        if ($isRequired && ($answerValue === null || $answerValue === '')) {
            throw new InvalidArgumentException("Answer is required for this question.");
        }

        // إذا لم يكن السؤال مطلوباً والإجابة فارغة، نتجاهل التحقق
        if (!$isRequired && ($answerValue === null || $answerValue === '')) {
            return;
        }

        // التحقق حسب نوع السؤال
        switch ($questionType) {
            case 'number':
                if (!is_numeric($answerValue)) {
                    throw new InvalidArgumentException("Answer must be a number.");
                }
                break;

            case 'date':
                if (!$this->isValidDate($answerValue)) {
                    throw new InvalidArgumentException("Answer must be a valid date (Y-m-d).");
                }
                break;

            case 'file':
                // التحقق من أن الإجابة هي URL أو path صحيح
                if (!is_string($answerValue) || trim($answerValue) === '') {
                    throw new InvalidArgumentException("Answer must be a valid file path or URL.");
                }
                break;

            case 'multiselect':
            case 'checkbox':
                // يجب أن تكون array أو JSON string
                if (is_string($answerValue)) {
                    $decoded = json_decode($answerValue, true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        throw new InvalidArgumentException("Answer must be a valid JSON array for multiple choice questions.");
                    }
                } elseif (!is_array($answerValue)) {
                    throw new InvalidArgumentException("Answer must be an array for multiple choice questions.");
                }
                break;

            case 'text':
            case 'textarea':
            case 'select':
            case 'radio':
                // يجب أن تكون string
                if (!is_string($answerValue) && !is_numeric($answerValue)) {
                    throw new InvalidArgumentException("Answer must be a string or number.");
                }
                break;
        }
    }

    /**
     * التحقق من صحة التاريخ
     */
    private function isValidDate(string $date): bool
    {
        $d = \DateTime::createFromFormat('Y-m-d', $date);
        return $d && $d->format('Y-m-d') === $date;
    }
}
