<?php
declare(strict_types=1);

final class JobApplicationAnswersService
{
    private PdoJobApplicationAnswersRepository $repo;
    private $validator;

    public function __construct(PdoJobApplicationAnswersRepository $repo, $validator = null)
    {
        $this->repo = $repo;
        $this->validator = $validator;
    }

    /**
     * List answers with filters, ordering, and pagination
     */
    public function list(
        ?int $limit,
        ?int $offset,
        array $filters,
        string $orderBy,
        string $orderDir
    ): array {
        return $this->repo->all($limit, $offset, $filters, $orderBy, $orderDir);
    }

    /**
     * Count answers with filters
     */
    public function count(array $filters = []): int
    {
        return $this->repo->count($filters);
    }

    /**
     * Get single answer by ID
     */
    public function get(int $id): ?array
    {
        return $this->repo->find($id);
    }

    /**
     * Get answers by application ID
     */
    public function getByApplication(int $applicationId): array
    {
        return $this->repo->getByApplication($applicationId);
    }

    /**
     * Get answer by application and question
     */
    public function getByApplicationAndQuestion(int $applicationId, int $questionId): ?array
    {
        return $this->repo->findByApplicationAndQuestion($applicationId, $questionId);
    }

    /**
     * Get answers by question (all applications)
     */
    public function getByQuestion(int $questionId): array
    {
        return $this->repo->getByQuestion($questionId);
    }

    /**
     * Get statistics for a question
     */
    public function getQuestionStatistics(int $questionId): array
    {
        return $this->repo->getQuestionStatistics($questionId);
    }

    /**
     * Create new answer
     */
    public function create(array $data): int
    {
        // Validate
        if ($this->validator) {
            $this->validator->validate($data, false);
        }

        return $this->repo->save($data);
    }

    /**
     * Update existing answer
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
     * Delete answer
     */
    public function delete(int $id): bool
    {
        return $this->repo->delete($id);
    }

    /**
     * Delete by application
     */
    public function deleteByApplication(int $applicationId): bool
    {
        return $this->repo->deleteByApplication($applicationId);
    }

    /**
     * Delete by question
     */
    public function deleteByQuestion(int $questionId): bool
    {
        return $this->repo->deleteByQuestion($questionId);
    }

    /**
     * Bulk save answers for an application
     */
    public function bulkSave(int $applicationId, array $answers): bool
    {
        // Validate
        if ($this->validator) {
            $this->validator->validateBulkAnswers($answers);
        }

        return $this->repo->bulkSave($applicationId, $answers);
    }

    /**
     * Check if all required questions are answered
     */
    public function checkRequiredAnswers(int $applicationId): array
    {
        $check = $this->repo->checkRequiredAnswers($applicationId);
        
        $result = [
            'all_answered' => true,
            'missing' => [],
            'answered' => []
        ];

        foreach ($check as $item) {
            if ($item['is_answered'] == 0) {
                $result['all_answered'] = false;
                $result['missing'][] = [
                    'question_id' => $item['id'],
                    'question_text' => $item['question_text']
                ];
            } else {
                $result['answered'][] = [
                    'question_id' => $item['id'],
                    'question_text' => $item['question_text']
                ];
            }
        }

        return $result;
    }

    /**
     * Submit application with answers
     */
    public function submitApplicationAnswers(int $applicationId, array $answers, array $questions = []): bool
    {
        // إذا تم توفير الأسئلة، نتحقق من الإجابات
        if (!empty($questions)) {
            foreach ($answers as $answer) {
                $questionId = $answer['question_id'];
                $question = array_filter($questions, fn($q) => $q['id'] == $questionId);
                $question = reset($question);

                if ($question && $this->validator) {
                    $this->validator->validateAnswerByQuestionType(
                        $question['question_type'],
                        $answer['answer_text'] ?? null,
                        (bool)$question['is_required']
                    );
                }
            }
        }

        return $this->bulkSave($applicationId, $answers);
    }
}
