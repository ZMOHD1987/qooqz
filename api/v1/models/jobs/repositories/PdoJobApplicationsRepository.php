<?php
declare(strict_types=1);

final class PdoJobApplicationsRepository
{
    private PDO $pdo;

    // الأعمدة المسموح بها للفرز
    private const ALLOWED_ORDER_BY = [
        'id', 'job_id', 'user_id', 'full_name', 'email', 'years_of_experience',
        'expected_salary', 'status', 'rating', 'created_at', 'updated_at', 'reviewed_at'
    ];

    // الأعمدة القابلة للفلاتر
    private const FILTERABLE_COLUMNS = [
        'job_id', 'user_id', 'status', 'rating'
    ];

    // حالات الطلب المتاحة
    private const STATUSES = [
        'submitted', 'under_review', 'shortlisted', 'interview_scheduled',
        'interviewed', 'offered', 'accepted', 'rejected', 'withdrawn'
    ];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // ================================
    // List with dynamic filters, search, ordering, pagination
    // ================================
    public function all(
        ?int $limit = null,
        ?int $offset = null,
        array $filters = [],
        string $orderBy = 'id',
        string $orderDir = 'DESC',
        string $lang = 'ar'
    ): array {
        $sql = "
            SELECT ja.*,
                   j.slug AS job_slug,
                   COALESCE(jt.job_title, '') AS job_title,
                   u.username,
                   u.email AS user_email,
                   reviewer.username AS reviewer_name
            FROM job_applications ja
            LEFT JOIN jobs j ON ja.job_id = j.id
            LEFT JOIN job_translations jt ON j.id = jt.job_id AND jt.language_code = :lang
            LEFT JOIN users u ON ja.user_id = u.id
            LEFT JOIN users reviewer ON ja.reviewed_by = reviewer.id
            WHERE 1=1
        ";
        $params = [':lang' => $lang];

        // تطبيق الفلاتر
        foreach (self::FILTERABLE_COLUMNS as $col) {
            if (isset($filters[$col]) && $filters[$col] !== '') {
                $sql .= " AND ja.{$col} = :{$col}";
                $params[":{$col}"] = $filters[$col];
            }
        }

        // فلتر البحث
        if (!empty($filters['search'])) {
            $sql .= " AND (ja.full_name LIKE :search OR ja.email LIKE :search OR ja.phone LIKE :search)";
            $params[':search'] = '%' . $filters['search'] . '%';
        }

        // فلتر نطاق الراتب المتوقع
        if (isset($filters['salary_min']) && is_numeric($filters['salary_min'])) {
            $sql .= " AND ja.expected_salary >= :salary_min";
            $params[':salary_min'] = $filters['salary_min'];
        }

        if (isset($filters['salary_max']) && is_numeric($filters['salary_max'])) {
            $sql .= " AND ja.expected_salary <= :salary_max";
            $params[':salary_max'] = $filters['salary_max'];
        }

        // فلتر سنوات الخبرة
        if (isset($filters['experience_min']) && is_numeric($filters['experience_min'])) {
            $sql .= " AND ja.years_of_experience >= :experience_min";
            $params[':experience_min'] = $filters['experience_min'];
        }

        if (isset($filters['experience_max']) && is_numeric($filters['experience_max'])) {
            $sql .= " AND ja.years_of_experience <= :experience_max";
            $params[':experience_max'] = $filters['experience_max'];
        }

        // فلتر التقييم
        if (isset($filters['rating_min']) && is_numeric($filters['rating_min'])) {
            $sql .= " AND ja.rating >= :rating_min";
            $params[':rating_min'] = $filters['rating_min'];
        }

        // فلتر التاريخ
        if (!empty($filters['date_from'])) {
            $sql .= " AND DATE(ja.created_at) >= :date_from";
            $params[':date_from'] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $sql .= " AND DATE(ja.created_at) <= :date_to";
            $params[':date_to'] = $filters['date_to'];
        }

        // فلتر المراجعة
        if (isset($filters['reviewed']) && $filters['reviewed'] === '1') {
            $sql .= " AND ja.reviewed_at IS NOT NULL";
        } elseif (isset($filters['reviewed']) && $filters['reviewed'] === '0') {
            $sql .= " AND ja.reviewed_at IS NULL";
        }

        // الفرز
        $orderBy = in_array($orderBy, self::ALLOWED_ORDER_BY, true) ? $orderBy : 'id';
        $orderDir = strtoupper($orderDir) === 'ASC' ? 'ASC' : 'DESC';
        $sql .= " ORDER BY ja.{$orderBy} {$orderDir}";

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
    public function count(array $filters = [], string $lang = 'ar'): int
    {
        $sql = "SELECT COUNT(DISTINCT ja.id) FROM job_applications ja";
        
        if (!empty($filters['search'])) {
            $sql .= " WHERE 1=1";
        } else {
            $sql .= " WHERE 1=1";
        }
        
        $params = [];

        foreach (self::FILTERABLE_COLUMNS as $col) {
            if (isset($filters[$col]) && $filters[$col] !== '') {
                $sql .= " AND ja.{$col} = :{$col}";
                $params[":{$col}"] = $filters[$col];
            }
        }

        if (!empty($filters['search'])) {
            $sql .= " AND (ja.full_name LIKE :search OR ja.email LIKE :search OR ja.phone LIKE :search)";
            $params[':search'] = '%' . $filters['search'] . '%';
        }

        if (isset($filters['salary_min']) && is_numeric($filters['salary_min'])) {
            $sql .= " AND ja.expected_salary >= :salary_min";
            $params[':salary_min'] = $filters['salary_min'];
        }

        if (isset($filters['salary_max']) && is_numeric($filters['salary_max'])) {
            $sql .= " AND ja.expected_salary <= :salary_max";
            $params[':salary_max'] = $filters['salary_max'];
        }

        if (isset($filters['experience_min']) && is_numeric($filters['experience_min'])) {
            $sql .= " AND ja.years_of_experience >= :experience_min";
            $params[':experience_min'] = $filters['experience_min'];
        }

        if (isset($filters['experience_max']) && is_numeric($filters['experience_max'])) {
            $sql .= " AND ja.years_of_experience <= :experience_max";
            $params[':experience_max'] = $filters['experience_max'];
        }

        if (isset($filters['rating_min']) && is_numeric($filters['rating_min'])) {
            $sql .= " AND ja.rating >= :rating_min";
            $params[':rating_min'] = $filters['rating_min'];
        }

        if (!empty($filters['date_from'])) {
            $sql .= " AND DATE(ja.created_at) >= :date_from";
            $params[':date_from'] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $sql .= " AND DATE(ja.created_at) <= :date_to";
            $params[':date_to'] = $filters['date_to'];
        }

        if (isset($filters['reviewed']) && $filters['reviewed'] === '1') {
            $sql .= " AND ja.reviewed_at IS NOT NULL";
        } elseif (isset($filters['reviewed']) && $filters['reviewed'] === '0') {
            $sql .= " AND ja.reviewed_at IS NULL";
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    // ================================
    // Find by ID
    // ================================
    public function find(int $id, string $lang = 'ar'): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT ja.*,
                   j.slug AS job_slug,
                   COALESCE(jt.job_title, '') AS job_title,
                   u.username,
                   u.email AS user_email,
                   reviewer.username AS reviewer_name
            FROM job_applications ja
            LEFT JOIN jobs j ON ja.job_id = j.id
            LEFT JOIN job_translations jt ON j.id = jt.job_id AND jt.language_code = :lang
            LEFT JOIN users u ON ja.user_id = u.id
            LEFT JOIN users reviewer ON ja.reviewed_by = reviewer.id
            WHERE ja.id = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $id, ':lang' => $lang]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    // ================================
    // Get applications by job ID
    // ================================
    public function getByJob(int $jobId, string $lang = 'ar'): array
    {
        $stmt = $this->pdo->prepare("
            SELECT ja.*,
                   u.username,
                   u.email AS user_email
            FROM job_applications ja
            LEFT JOIN users u ON ja.user_id = u.id
            WHERE ja.job_id = :job_id
            ORDER BY ja.created_at DESC
        ");
        $stmt->execute([':job_id' => $jobId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ================================
    // Get applications by user ID
    // ================================
    public function getByUser(int $userId, string $lang = 'ar'): array
    {
        $stmt = $this->pdo->prepare("
            SELECT ja.*,
                   j.slug AS job_slug,
                   COALESCE(jt.job_title, '') AS job_title
            FROM job_applications ja
            LEFT JOIN jobs j ON ja.job_id = j.id
            LEFT JOIN job_translations jt ON j.id = jt.job_id AND jt.language_code = :lang
            WHERE ja.user_id = :user_id
            ORDER BY ja.created_at DESC
        ");
        $stmt->execute([':user_id' => $userId, ':lang' => $lang]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ================================
    // Check if user already applied
    // ================================
    public function hasApplied(int $jobId, int $userId): bool
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM job_applications 
            WHERE job_id = :job_id AND user_id = :user_id
        ");
        $stmt->execute([':job_id' => $jobId, ':user_id' => $userId]);
        return (int)$stmt->fetchColumn() > 0;
    }

    // ================================
    // Get statistics
    // ================================
    public function getStatistics(int $jobId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'submitted' THEN 1 ELSE 0 END) as submitted,
                SUM(CASE WHEN status = 'under_review' THEN 1 ELSE 0 END) as under_review,
                SUM(CASE WHEN status = 'shortlisted' THEN 1 ELSE 0 END) as shortlisted,
                SUM(CASE WHEN status = 'interview_scheduled' THEN 1 ELSE 0 END) as interview_scheduled,
                SUM(CASE WHEN status = 'interviewed' THEN 1 ELSE 0 END) as interviewed,
                SUM(CASE WHEN status = 'offered' THEN 1 ELSE 0 END) as offered,
                SUM(CASE WHEN status = 'accepted' THEN 1 ELSE 0 END) as accepted,
                SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
                SUM(CASE WHEN status = 'withdrawn' THEN 1 ELSE 0 END) as withdrawn,
                AVG(rating) as average_rating,
                AVG(years_of_experience) as average_experience,
                AVG(expected_salary) as average_salary
            FROM job_applications
            WHERE job_id = :job_id
        ");
        $stmt->execute([':job_id' => $jobId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    // ================================
    // Create / Update
    // ================================
    private const APPLICATION_COLUMNS = [
        'job_id', 'user_id', 'full_name', 'email', 'phone',
        'current_position', 'current_company', 'years_of_experience',
        'expected_salary', 'currency_code', 'notice_period',
        'cv_file_url', 'cover_letter', 'portfolio_url', 'linkedin_url',
        'status', 'rating', 'notes', 'reviewed_by', 'reviewed_at', 'ip_address'
    ];

    public function save(array $data): int
    {
        $isUpdate = !empty($data['id']);

        $params = [];
        foreach (self::APPLICATION_COLUMNS as $col) {
            if (array_key_exists($col, $data)) {
                $val = $data[$col];
                $params[':' . $col] = ($val === '' || $val === null) ? null : $val;
            } else {
                $params[':' . $col] = null;
            }
        }

        // القيم الافتراضية
        if (!isset($params[':status']) || $params[':status'] === null) {
            $params[':status'] = 'submitted';
        }
        if (!isset($params[':currency_code']) || $params[':currency_code'] === null) {
            $params[':currency_code'] = 'SAR';
        }

        if ($isUpdate) {
            $params[':id'] = (int)$data['id'];

            $stmt = $this->pdo->prepare("
                UPDATE job_applications SET
                    job_id = :job_id,
                    user_id = :user_id,
                    full_name = :full_name,
                    email = :email,
                    phone = :phone,
                    current_position = :current_position,
                    current_company = :current_company,
                    years_of_experience = :years_of_experience,
                    expected_salary = :expected_salary,
                    currency_code = :currency_code,
                    notice_period = :notice_period,
                    cv_file_url = :cv_file_url,
                    cover_letter = :cover_letter,
                    portfolio_url = :portfolio_url,
                    linkedin_url = :linkedin_url,
                    status = :status,
                    rating = :rating,
                    notes = :notes,
                    reviewed_by = :reviewed_by,
                    reviewed_at = :reviewed_at,
                    ip_address = :ip_address,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :id
            ");
            $stmt->execute($params);
            return (int)$data['id'];
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO job_applications (
                job_id, user_id, full_name, email, phone,
                current_position, current_company, years_of_experience,
                expected_salary, currency_code, notice_period,
                cv_file_url, cover_letter, portfolio_url, linkedin_url,
                status, rating, notes, reviewed_by, reviewed_at, ip_address
            ) VALUES (
                :job_id, :user_id, :full_name, :email, :phone,
                :current_position, :current_company, :years_of_experience,
                :expected_salary, :currency_code, :notice_period,
                :cv_file_url, :cover_letter, :portfolio_url, :linkedin_url,
                :status, :rating, :notes, :reviewed_by, :reviewed_at, :ip_address
            )
        ");
        $stmt->execute($params);
        return (int)$this->pdo->lastInsertId();
    }

    // ================================
    // Update status
    // ================================
    public function updateStatus(int $id, string $status): bool
    {
        if (!in_array($status, self::STATUSES, true)) {
            throw new InvalidArgumentException("Invalid status: {$status}");
        }

        $stmt = $this->pdo->prepare("
            UPDATE job_applications 
            SET status = :status, updated_at = CURRENT_TIMESTAMP
            WHERE id = :id
        ");
        return $stmt->execute([':id' => $id, ':status' => $status]);
    }

    // ================================
    // Update rating
    // ================================
    public function updateRating(int $id, int $rating, ?int $reviewedBy = null): bool
    {
        $sql = "UPDATE job_applications SET rating = :rating, updated_at = CURRENT_TIMESTAMP";
        $params = [':id' => $id, ':rating' => $rating];

        if ($reviewedBy !== null) {
            $sql .= ", reviewed_by = :reviewed_by, reviewed_at = CURRENT_TIMESTAMP";
            $params[':reviewed_by'] = $reviewedBy;
        }

        $sql .= " WHERE id = :id";
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }

    // ================================
    // Add notes/review
    // ================================
    public function addReview(int $id, string $notes, int $reviewedBy): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE job_applications 
            SET notes = :notes, 
                reviewed_by = :reviewed_by, 
                reviewed_at = CURRENT_TIMESTAMP,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :id
        ");
        return $stmt->execute([
            ':id' => $id,
            ':notes' => $notes,
            ':reviewed_by' => $reviewedBy
        ]);
    }

    // ================================
    // Delete
    // ================================
    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM job_applications WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }
}
