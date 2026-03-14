<?php
declare(strict_types=1);

final class PdoJobSkillsRepository
{
    private PDO $pdo;

    // الأعمدة المسموح بها للفرز
    private const ALLOWED_ORDER_BY = [
        'id', 'job_id', 'skill_name', 'proficiency_level', 'is_required'
    ];

    // مستويات الإتقان المتاحة
    private const PROFICIENCY_LEVELS = [
        'basic', 'intermediate', 'advanced', 'expert'
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
        string $orderBy = 'skill_name',
        string $orderDir = 'ASC'
    ): array {
        $sql = "
            SELECT js.*,
                   j.slug AS job_slug
            FROM job_skills js
            LEFT JOIN jobs j ON js.job_id = j.id
            WHERE 1=1
        ";
        $params = [];

        // فلتر job_id
        if (isset($filters['job_id']) && $filters['job_id'] !== '') {
            $sql .= " AND js.job_id = :job_id";
            $params[':job_id'] = $filters['job_id'];
        }

        // فلتر proficiency_level
        if (isset($filters['proficiency_level']) && $filters['proficiency_level'] !== '') {
            $sql .= " AND js.proficiency_level = :proficiency_level";
            $params[':proficiency_level'] = $filters['proficiency_level'];
        }

        // فلتر is_required
        if (isset($filters['is_required']) && $filters['is_required'] !== '') {
            $sql .= " AND js.is_required = :is_required";
            $params[':is_required'] = $filters['is_required'];
        }

        // البحث في اسم المهارة
        if (!empty($filters['search'])) {
            $sql .= " AND js.skill_name LIKE :search";
            $params[':search'] = '%' . $filters['search'] . '%';
        }

        // الفرز
        $orderBy = in_array($orderBy, self::ALLOWED_ORDER_BY, true) ? $orderBy : 'skill_name';
        $orderDir = strtoupper($orderDir) === 'DESC' ? 'DESC' : 'ASC';
        $sql .= " ORDER BY js.{$orderBy} {$orderDir}";

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
        $sql = "SELECT COUNT(*) FROM job_skills WHERE 1=1";
        $params = [];

        if (isset($filters['job_id']) && $filters['job_id'] !== '') {
            $sql .= " AND job_id = :job_id";
            $params[':job_id'] = $filters['job_id'];
        }

        if (isset($filters['proficiency_level']) && $filters['proficiency_level'] !== '') {
            $sql .= " AND proficiency_level = :proficiency_level";
            $params[':proficiency_level'] = $filters['proficiency_level'];
        }

        if (isset($filters['is_required']) && $filters['is_required'] !== '') {
            $sql .= " AND is_required = :is_required";
            $params[':is_required'] = $filters['is_required'];
        }

        if (!empty($filters['search'])) {
            $sql .= " AND skill_name LIKE :search";
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
            SELECT js.*,
                   j.slug AS job_slug
            FROM job_skills js
            LEFT JOIN jobs j ON js.job_id = j.id
            WHERE js.id = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    // ================================
    // Get skills by job ID
    // ================================
    public function getByJob(int $jobId, bool $requiredOnly = false): array
    {
        $sql = "
            SELECT * 
            FROM job_skills 
            WHERE job_id = :job_id
        ";
        
        if ($requiredOnly) {
            $sql .= " AND is_required = 1";
        }
        
        $sql .= " ORDER BY skill_name ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':job_id' => $jobId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ================================
    // Create / Update
    // ================================
    private const SKILL_COLUMNS = [
        'job_id', 'skill_name', 'proficiency_level', 'is_required'
    ];

    public function save(array $data): int
    {
        $isUpdate = !empty($data['id']);

        $params = [];
        foreach (self::SKILL_COLUMNS as $col) {
            if (array_key_exists($col, $data)) {
                $val = $data[$col];
                $params[':' . $col] = ($val === '' || $val === null) ? null : $val;
            } else {
                $params[':' . $col] = null;
            }
        }

        // القيم الافتراضية
        if (!isset($params[':proficiency_level']) || $params[':proficiency_level'] === null) {
            $params[':proficiency_level'] = 'intermediate';
        }
        if (!isset($params[':is_required'])) {
            $params[':is_required'] = 1;
        }

        if ($isUpdate) {
            $params[':id'] = (int)$data['id'];

            $stmt = $this->pdo->prepare("
                UPDATE job_skills SET
                    job_id = :job_id,
                    skill_name = :skill_name,
                    proficiency_level = :proficiency_level,
                    is_required = :is_required
                WHERE id = :id
            ");
            $stmt->execute($params);
            return (int)$data['id'];
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO job_skills (
                job_id, skill_name, proficiency_level, is_required
            ) VALUES (
                :job_id, :skill_name, :proficiency_level, :is_required
            )
        ");
        $stmt->execute($params);
        return (int)$this->pdo->lastInsertId();
    }

    // ================================
    // Delete
    // ================================
    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM job_skills WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }

    // ================================
    // Delete all skills for a job
    // ================================
    public function deleteByJob(int $jobId): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM job_skills WHERE job_id = :job_id");
        return $stmt->execute([':job_id' => $jobId]);
    }

    // ================================
    // Duplicate skills from another job
    // ================================
    public function duplicateFromJob(int $sourceJobId, int $targetJobId): bool
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO job_skills (
                job_id, skill_name, proficiency_level, is_required
            )
            SELECT 
                :target_job_id, skill_name, proficiency_level, is_required
            FROM job_skills
            WHERE job_id = :source_job_id
            ORDER BY skill_name ASC
        ");
        
        return $stmt->execute([
            ':source_job_id' => $sourceJobId,
            ':target_job_id' => $targetJobId
        ]);
    }

    // ================================
    // Get proficiency levels
    // ================================
    public function getProficiencyLevels(): array
    {
        return self::PROFICIENCY_LEVELS;
    }
}