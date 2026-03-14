<?php
declare(strict_types=1);

final class PdoJobApplicationAnswersRepository
{
    private PDO $pdo;

    // الأعمدة المسموح بها للفرز
    private const ALLOWED_ORDER_BY = [
        'id', 'application_id', 'question_id'
    ];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // ================================
    // List with filters, ordering, pagination
    // ================================
    public function all(
        ?int $limit = null,
        ?int $offset = null,
        array $filters = [],
        string $orderBy = 'id',
        string $orderDir = 'ASC'
    ): array {
        $sql = "
            SELECT jaa.*,
                   jaq.question_text,
                   jaq.question_type,
                   ja.full_name AS applicant_name,
                   ja.job_id
            FROM job_application_answers jaa
            LEFT JOIN job_application_questions jaq ON jaa.question_id = jaq.id
            LEFT JOIN job_applications ja ON jaa.application_id = ja.id
            WHERE 1=1
        ";
        $params = [];

        // فلتر application_id
        if (isset($filters['application_id']) && $filters['application_id'] !== '') {
            $sql .= " AND jaa.application_id = :application_id";
            $params[':application_id'] = $filters['application_id'];
        }

        // فلتر question_id
        if (isset($filters['question_id']) && $filters['question_id'] !== '') {
            $sql .= " AND jaa.question_id = :question_id";
            $params[':question_id'] = $filters['question_id'];
        }

        // فلتر job_id
        if (isset($filters['job_id']) && $filters['job_id'] !== '') {
            $sql .= " AND ja.job_id = :job_id";
            $params[':job_id'] = $filters['job_id'];
        }

        // البحث في الإجابة
        if (!empty($filters['search'])) {
            $sql .= " AND jaa.answer_text LIKE :search";
            $params[':search'] = '%' . $filters['search'] . '%';
        }

        // الفرز
        $orderBy = in_array($orderBy, self::ALLOWED_ORDER_BY, true) ? $orderBy : 'id';
        $orderDir = strtoupper($orderDir) === 'DESC' ? 'DESC' : 'ASC';
        $sql .= " ORDER BY jaa.{$orderBy} {$orderDir}";

        // Pagination
        if ($limit !== null) $sql .= " LIMIT :limit";
        if ($offset !== null) $sql .= " OFFSET :offset";

        $stmt = $this->pdo->prepare($sql);

        foreach ($params as $key => $value) {
            $type = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
            $stmt->bindValue($key, $value, $type);
        }
        if ($limit !== null) $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        if ($offset !== null) $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ================================
    // Count for pagination
    // ================================
    public function count(array $filters = []): int
    {
        $sql = "
            SELECT COUNT(*) 
            FROM job_application_answers jaa
            LEFT JOIN job_applications ja ON jaa.application_id = ja.id
            WHERE 1=1
        ";
        $params = [];

        if (isset($filters['application_id']) && $filters['application_id'] !== '') {
            $sql .= " AND jaa.application_id = :application_id";
            $params[':application_id'] = $filters['application_id'];
        }

        if (isset($filters['question_id']) && $filters['question_id'] !== '') {
            $sql .= " AND jaa.question_id = :question_id";
            $params[':question_id'] = $filters['question_id'];
        }

        if (isset($filters['job_id']) && $filters['job_id'] !== '') {
            $sql .= " AND ja.job_id = :job_id";
            $params[':job_id'] = $filters['job_id'];
        }

        if (!empty($filters['search'])) {
            $sql .= " AND jaa.answer_text LIKE :search";
            $params[':search'] = '%' . $filters['search'] . '%';
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    // ================================
    // Find by ID
    // ================================
    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT jaa.*,
                   jaq.question_text,
                   jaq.question_type,
                   jaq.options,
                   ja.full_name AS applicant_name,
                   ja.job_id
            FROM job_application_answers jaa
            LEFT JOIN job_application_questions jaq ON jaa.question_id = jaq.id
            LEFT JOIN job_applications ja ON jaa.application_id = ja.id
            WHERE jaa.id = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    // ================================
    // Get answers by application ID
    // ================================
    public function getByApplication(int $applicationId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT jaa.*,
                   jaq.question_text,
                   jaq.question_type,
                   jaq.options,
                   jaq.is_required,
                   jaq.sort_order
            FROM job_application_answers jaa
            LEFT JOIN job_application_questions jaq ON jaa.question_id = jaq.id
            WHERE jaa.application_id = :application_id
            ORDER BY jaq.sort_order ASC, jaq.id ASC
        ");
        $stmt->execute([':application_id' => $applicationId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ================================
    // Get answer by application and question
    // ================================
    public function findByApplicationAndQuestion(int $applicationId, int $questionId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT jaa.*,
                   jaq.question_text,
                   jaq.question_type,
                   jaq.options
            FROM job_application_answers jaa
            LEFT JOIN job_application_questions jaq ON jaa.question_id = jaq.id
            WHERE jaa.application_id = :application_id 
            AND jaa.question_id = :question_id
            LIMIT 1
        ");
        $stmt->execute([
            ':application_id' => $applicationId,
            ':question_id' => $questionId
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    // ================================
    // Get answers by question (all applications)
    // ================================
    public function getByQuestion(int $questionId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT jaa.*,
                   ja.full_name AS applicant_name,
                   ja.email AS applicant_email,
                   ja.status AS application_status,
                   ja.created_at AS application_date
            FROM job_application_answers jaa
            LEFT JOIN job_applications ja ON jaa.application_id = ja.id
            WHERE jaa.question_id = :question_id
            ORDER BY ja.created_at DESC
        ");
        $stmt->execute([':question_id' => $questionId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ================================
    // Get statistics for a question
    // ================================
    public function getQuestionStatistics(int $questionId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                COUNT(*) as total_answers,
                COUNT(DISTINCT application_id) as unique_applications,
                jaq.question_type,
                jaq.options
            FROM job_application_answers jaa
            LEFT JOIN job_application_questions jaq ON jaa.question_id = jaq.id
            WHERE jaa.question_id = :question_id
            GROUP BY jaq.question_type, jaq.options
        ");
        $stmt->execute([':question_id' => $questionId]);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);

        // إحصائيات إضافية للأسئلة ذات الخيارات
        if ($stats && in_array($stats['question_type'], ['select', 'radio', 'checkbox', 'multiselect'])) {
            $stats['value_distribution'] = $this->getAnswerDistribution($questionId);
        }

        return $stats ?: [];
    }

    // ================================
    // Get answer distribution for choice questions
    // ================================
    private function getAnswerDistribution(int $questionId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                answer_text,
                COUNT(*) as count,
                COUNT(*) * 100.0 / SUM(COUNT(*)) OVER() as percentage
            FROM job_application_answers
            WHERE question_id = :question_id
            AND answer_text IS NOT NULL
            GROUP BY answer_text
            ORDER BY count DESC
        ");
        $stmt->execute([':question_id' => $questionId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ================================
    // Create / Update
    // ================================
    public function save(array $data): int
    {
        $isUpdate = !empty($data['id']);

        // تحويل array إلى JSON إذا لزم الأمر
        if (isset($data['answer_text']) && is_array($data['answer_text'])) {
            $data['answer_text'] = json_encode($data['answer_text'], JSON_UNESCAPED_UNICODE);
        }

        if ($isUpdate) {
            $stmt = $this->pdo->prepare("
                UPDATE job_application_answers SET
                    application_id = :application_id,
                    question_id = :question_id,
                    answer_text = :answer_text
                WHERE id = :id
            ");
            $stmt->execute([
                ':id' => (int)$data['id'],
                ':application_id' => (int)$data['application_id'],
                ':question_id' => (int)$data['question_id'],
                ':answer_text' => $data['answer_text'] ?? null
            ]);
            return (int)$data['id'];
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO job_application_answers (
                application_id, question_id, answer_text
            ) VALUES (
                :application_id, :question_id, :answer_text
            )
        ");
        $stmt->execute([
            ':application_id' => (int)$data['application_id'],
            ':question_id' => (int)$data['question_id'],
            ':answer_text' => $data['answer_text'] ?? null
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    // ================================
    // Bulk save answers (for an application)
    // ================================
    public function bulkSave(int $applicationId, array $answers): bool
    {
        $this->pdo->beginTransaction();
        
        try {
            foreach ($answers as $answer) {
                // التحقق من وجود الإجابة
                $existing = $this->findByApplicationAndQuestion(
                    $applicationId,
                    (int)$answer['question_id']
                );

                if ($existing) {
                    // تحديث
                    $answer['id'] = $existing['id'];
                    $answer['application_id'] = $applicationId;
                    $this->save($answer);
                } else {
                    // إنشاء جديد
                    $answer['application_id'] = $applicationId;
                    $this->save($answer);
                }
            }
            
            $this->pdo->commit();
            return true;
        } catch (\Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    // ================================
    // Delete
    // ================================
    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM job_application_answers WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }

    // ================================
    // Delete by application
    // ================================
    public function deleteByApplication(int $applicationId): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM job_application_answers WHERE application_id = :application_id");
        return $stmt->execute([':application_id' => $applicationId]);
    }

    // ================================
    // Delete by question
    // ================================
    public function deleteByQuestion(int $questionId): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM job_application_answers WHERE question_id = :question_id");
        return $stmt->execute([':question_id' => $questionId]);
    }

    // ================================
    // Check if all required questions are answered
    // ================================
    public function checkRequiredAnswers(int $applicationId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                jaq.id,
                jaq.question_text,
                CASE WHEN jaa.id IS NOT NULL THEN 1 ELSE 0 END as is_answered
            FROM job_applications ja
            JOIN job_application_questions jaq ON ja.job_id = jaq.job_id
            LEFT JOIN job_application_answers jaa 
                ON jaq.id = jaa.question_id 
                AND jaa.application_id = :application_id
            WHERE ja.id = :application_id
            AND jaq.is_required = 1
            ORDER BY jaq.sort_order ASC
        ");
        $stmt->execute([':application_id' => $applicationId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
