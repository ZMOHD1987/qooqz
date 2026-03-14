<?php
declare(strict_types=1);

final class PdoJobApplicationQuestionsRepository
{
    private PDO $pdo;

    // الأعمدة المسموح بها للفرز
    private const ALLOWED_ORDER_BY = [
        'id', 'job_id', 'question_type', 'is_required', 'sort_order'
    ];

    // أنواع الأسئلة المتاحة
    private const QUESTION_TYPES = [
        'text', 'textarea', 'select', 'multiselect', 
        'radio', 'checkbox', 'file', 'date', 'number'
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
        string $orderBy = 'sort_order',
        string $orderDir = 'ASC'
    ): array {
        $sql = "
            SELECT jaq.*,
                   j.slug AS job_slug
            FROM job_application_questions jaq
            LEFT JOIN jobs j ON jaq.job_id = j.id
            WHERE 1=1
        ";
        $params = [];

        // فلتر job_id
        if (isset($filters['job_id']) && $filters['job_id'] !== '') {
            $sql .= " AND jaq.job_id = :job_id";
            $params[':job_id'] = $filters['job_id'];
        }

        // فلتر question_type
        if (isset($filters['question_type']) && $filters['question_type'] !== '') {
            $sql .= " AND jaq.question_type = :question_type";
            $params[':question_type'] = $filters['question_type'];
        }

        // فلتر is_required
        if (isset($filters['is_required']) && $filters['is_required'] !== '') {
            $sql .= " AND jaq.is_required = :is_required";
            $params[':is_required'] = $filters['is_required'];
        }

        // البحث في نص السؤال
        if (!empty($filters['search'])) {
            $sql .= " AND jaq.question_text LIKE :search";
            $params[':search'] = '%' . $filters['search'] . '%';
        }

        // الفرز
        $orderBy = in_array($orderBy, self::ALLOWED_ORDER_BY, true) ? $orderBy : 'sort_order';
        $orderDir = strtoupper($orderDir) === 'DESC' ? 'DESC' : 'ASC';
        $sql .= " ORDER BY jaq.{$orderBy} {$orderDir}";

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
        $sql = "SELECT COUNT(*) FROM job_application_questions WHERE 1=1";
        $params = [];

        if (isset($filters['job_id']) && $filters['job_id'] !== '') {
            $sql .= " AND job_id = :job_id";
            $params[':job_id'] = $filters['job_id'];
        }

        if (isset($filters['question_type']) && $filters['question_type'] !== '') {
            $sql .= " AND question_type = :question_type";
            $params[':question_type'] = $filters['question_type'];
        }

        if (isset($filters['is_required']) && $filters['is_required'] !== '') {
            $sql .= " AND is_required = :is_required";
            $params[':is_required'] = $filters['is_required'];
        }

        if (!empty($filters['search'])) {
            $sql .= " AND question_text LIKE :search";
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
            SELECT jaq.*,
                   j.slug AS job_slug
            FROM job_application_questions jaq
            LEFT JOIN jobs j ON jaq.job_id = j.id
            WHERE jaq.id = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    // ================================
    // Get questions by job ID
    // ================================
    public function getByJob(int $jobId, bool $requiredOnly = false): array
    {
        $sql = "
            SELECT * 
            FROM job_application_questions 
            WHERE job_id = :job_id
        ";
        
        if ($requiredOnly) {
            $sql .= " AND is_required = 1";
        }
        
        $sql .= " ORDER BY sort_order ASC, id ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':job_id' => $jobId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ================================
    // Create / Update
    // ================================
    private const QUESTION_COLUMNS = [
        'job_id', 'question_text', 'question_type', 
        'options', 'is_required', 'sort_order'
    ];

    public function save(array $data): int
    {
        $isUpdate = !empty($data['id']);

        $params = [];
        foreach (self::QUESTION_COLUMNS as $col) {
            if (array_key_exists($col, $data)) {
                $val = $data[$col];
                $params[':' . $col] = ($val === '' || $val === null) ? null : $val;
            } else {
                $params[':' . $col] = null;
            }
        }

        // القيم الافتراضية
        if (!isset($params[':question_type']) || $params[':question_type'] === null) {
            $params[':question_type'] = 'text';
        }
        if (!isset($params[':is_required'])) {
            $params[':is_required'] = 0;
        }
        if (!isset($params[':sort_order']) || $params[':sort_order'] === null) {
            $params[':sort_order'] = 0;
        }

        // تحويل options array إلى JSON إذا كان array
        if (isset($data['options']) && is_array($data['options'])) {
            $params[':options'] = json_encode($data['options'], JSON_UNESCAPED_UNICODE);
        }

        if ($isUpdate) {
            $params[':id'] = (int)$data['id'];

            $stmt = $this->pdo->prepare("
                UPDATE job_application_questions SET
                    job_id = :job_id,
                    question_text = :question_text,
                    question_type = :question_type,
                    options = :options,
                    is_required = :is_required,
                    sort_order = :sort_order
                WHERE id = :id
            ");
            $stmt->execute($params);
            return (int)$data['id'];
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO job_application_questions (
                job_id, question_text, question_type,
                options, is_required, sort_order
            ) VALUES (
                :job_id, :question_text, :question_type,
                :options, :is_required, :sort_order
            )
        ");
        $stmt->execute($params);
        return (int)$this->pdo->lastInsertId();
    }

    // ================================
    // Update sort order
    // ================================
    public function updateSortOrder(int $id, int $sortOrder): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE job_application_questions 
            SET sort_order = :sort_order 
            WHERE id = :id
        ");
        return $stmt->execute([':id' => $id, ':sort_order' => $sortOrder]);
    }

    // ================================
    // Reorder questions (batch update)
    // ================================
    public function reorder(array $orderData): bool
    {
        $this->pdo->beginTransaction();
        
        try {
            foreach ($orderData as $item) {
                if (isset($item['id']) && isset($item['sort_order'])) {
                    $this->updateSortOrder((int)$item['id'], (int)$item['sort_order']);
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
        $stmt = $this->pdo->prepare("DELETE FROM job_application_questions WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }

    // ================================
    // Delete all questions for a job
    // ================================
    public function deleteByJob(int $jobId): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM job_application_questions WHERE job_id = :job_id");
        return $stmt->execute([':job_id' => $jobId]);
    }

    // ================================
    // Duplicate questions from another job
    // ================================
    public function duplicateFromJob(int $sourceJobId, int $targetJobId): bool
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO job_application_questions (
                job_id, question_text, question_type, 
                options, is_required, sort_order
            )
            SELECT 
                :target_job_id, question_text, question_type,
                options, is_required, sort_order
            FROM job_application_questions
            WHERE job_id = :source_job_id
            ORDER BY sort_order ASC
        ");
        
        return $stmt->execute([
            ':source_job_id' => $sourceJobId,
            ':target_job_id' => $targetJobId
        ]);
    }

    // ================================
    // Get question types
    // ================================
    public function getQuestionTypes(): array
    {
        return self::QUESTION_TYPES;
    }
}
