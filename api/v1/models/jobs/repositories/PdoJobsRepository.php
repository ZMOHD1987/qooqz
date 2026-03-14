<?php
declare(strict_types=1);

final class PdoJobsRepository
{
    private PDO $pdo;

    // الأعمدة المسموح بها للفرز
    private const ALLOWED_ORDER_BY = [
        'id', 'entity_id', 'job_title', 'slug', 'job_type', 'employment_type',
        'experience_level', 'category', 'department', 'positions_available',
        'salary_min', 'salary_max', 'country_id', 'city_id', 'is_remote',
        'status', 'application_deadline', 'start_date', 'views_count',
        'applications_count', 'is_featured', 'is_urgent', 'created_at',
        'updated_at', 'published_at', 'closed_at'
    ];

    // الأعمدة القابلة للفلاتر
    private const FILTERABLE_COLUMNS = [
        'entity_id', 'job_type', 'employment_type', 'experience_level',
        'category', 'department', 'country_id', 'city_id', 'is_remote',
        'status', 'is_featured', 'is_urgent', 'salary_negotiable'
    ];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // ================================
    // List with dynamic filters, search, ordering, pagination
    // ================================
    public function all(
        int $limit = null,
        int $offset = null,
        array $filters = [],
        string $orderBy = 'id',
        string $orderDir = 'DESC',
        string $lang = 'ar'
    ): array {
        $sql = "
            SELECT j.*,
                   COALESCE(jt.job_title, '') AS job_title,
                   COALESCE(jt.description, '') AS description,
                   COALESCE(jt.requirements, '') AS requirements,
                   COALESCE(jt.responsibilities, '') AS responsibilities,
                   COALESCE(jt.benefits, '') AS benefits,
                   l.name AS language_name,
                   l.direction AS language_direction
            FROM jobs j
            LEFT JOIN job_translations jt
                ON j.id = jt.job_id AND jt.language_code = :lang
            LEFT JOIN languages l
                ON jt.language_code = l.code
            WHERE 1=1
        ";
        $params = [':lang' => $lang];

        // تطبيق كل الفلاتر بشكل ديناميكي
        foreach (self::FILTERABLE_COLUMNS as $col) {
            if (isset($filters[$col]) && $filters[$col] !== '') {
                $sql .= " AND j.{$col} = :{$col}";
                $params[":{$col}"] = $filters[$col];
            }
        }

        // فلتر البحث في العنوان أو الوصف
        if (!empty($filters['search'])) {
            $sql .= " AND (jt.job_title LIKE :search OR jt.description LIKE :search)";
            $params[':search'] = '%' . $filters['search'] . '%';
        }

        // فلتر نطاق الراتب
        if (isset($filters['salary_min']) && is_numeric($filters['salary_min'])) {
            $sql .= " AND j.salary_min >= :filter_salary_min";
            $params[':filter_salary_min'] = $filters['salary_min'];
        }

        if (isset($filters['salary_max']) && is_numeric($filters['salary_max'])) {
            $sql .= " AND j.salary_max <= :filter_salary_max";
            $params[':filter_salary_max'] = $filters['salary_max'];
        }

        // فلتر تاريخ انتهاء التقديم
        if (!empty($filters['deadline_after'])) {
            $sql .= " AND j.application_deadline >= :deadline_after";
            $params[':deadline_after'] = $filters['deadline_after'];
        }

        // الفرز
        $orderBy = in_array($orderBy, self::ALLOWED_ORDER_BY, true) ? $orderBy : 'id';
        $orderDir = strtoupper($orderDir) === 'ASC' ? 'ASC' : 'DESC';
        $sql .= " ORDER BY j.{$orderBy} {$orderDir}";

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
        $sql = "SELECT COUNT(DISTINCT j.id) FROM jobs j";
        
        // Join للترجمات فقط إذا كان هناك بحث
        if (!empty($filters['search'])) {
            $sql .= " LEFT JOIN job_translations jt ON j.id = jt.job_id AND jt.language_code = :lang";
        }
        
        $sql .= " WHERE 1=1";
        $params = [];

        if (!empty($filters['search'])) {
            $params[':lang'] = $lang;
        }

        foreach (self::FILTERABLE_COLUMNS as $col) {
            if (isset($filters[$col]) && $filters[$col] !== '') {
                $sql .= " AND j.{$col} = :{$col}";
                $params[":{$col}"] = $filters[$col];
            }
        }

        if (!empty($filters['search'])) {
            $sql .= " AND (jt.job_title LIKE :search OR jt.description LIKE :search)";
            $params[':search'] = '%' . $filters['search'] . '%';
        }

        if (isset($filters['salary_min']) && is_numeric($filters['salary_min'])) {
            $sql .= " AND j.salary_min >= :filter_salary_min";
            $params[':filter_salary_min'] = $filters['salary_min'];
        }

        if (isset($filters['salary_max']) && is_numeric($filters['salary_max'])) {
            $sql .= " AND j.salary_max <= :filter_salary_max";
            $params[':filter_salary_max'] = $filters['salary_max'];
        }

        if (!empty($filters['deadline_after'])) {
            $sql .= " AND j.application_deadline >= :deadline_after";
            $params[':deadline_after'] = $filters['deadline_after'];
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    // ================================
    // Find by ID with translations
    // ================================
    public function find(int $id, string $lang = 'ar'): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT j.*,
                   COALESCE(jt.job_title, '') AS job_title,
                   COALESCE(jt.description, '') AS description,
                   COALESCE(jt.requirements, '') AS requirements,
                   COALESCE(jt.responsibilities, '') AS responsibilities,
                   COALESCE(jt.benefits, '') AS benefits,
                   l.name AS language_name,
                   l.direction AS language_direction
            FROM jobs j
            LEFT JOIN job_translations jt
                ON j.id = jt.job_id AND jt.language_code = :lang
            LEFT JOIN languages l
                ON jt.language_code = l.code
            WHERE j.id = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $id, ':lang' => $lang]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    // ================================
    // Find by slug with translations
    // ================================
    public function findBySlug(string $slug, string $lang = 'ar'): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT j.*,
                   COALESCE(jt.job_title, '') AS job_title,
                   COALESCE(jt.description, '') AS description,
                   COALESCE(jt.requirements, '') AS requirements,
                   COALESCE(jt.responsibilities, '') AS responsibilities,
                   COALESCE(jt.benefits, '') AS benefits,
                   l.name AS language_name,
                   l.direction AS language_direction
            FROM jobs j
            LEFT JOIN job_translations jt
                ON j.id = jt.job_id AND jt.language_code = :lang
            LEFT JOIN languages l
                ON jt.language_code = l.code
            WHERE j.slug = :slug
            LIMIT 1
        ");
        $stmt->execute([':slug' => $slug, ':lang' => $lang]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    // ================================
    // Get all translations for a job
    // ================================
    public function getTranslations(int $jobId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT jt.*,
                   l.name AS language_name,
                   l.direction AS language_direction
            FROM job_translations jt
            JOIN languages l ON jt.language_code = l.code
            WHERE jt.job_id = :job_id
            ORDER BY jt.language_code
        ");
        $stmt->execute([':job_id' => $jobId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ================================
    // Create / Update
    // ================================

    // الأعمدة المسموحة في جدول jobs فقط
    private const JOB_COLUMNS = [
        'entity_id', 'slug', 'job_type', 'employment_type',
        'application_form_type', 'external_application_url', 'experience_level',
        'category', 'department', 'positions_available',
        'salary_min', 'salary_max', 'salary_currency', 'salary_period', 'salary_negotiable',
        'country_id', 'city_id', 'work_location', 'is_remote',
        'status', 'application_deadline', 'start_date',
        'views_count', 'applications_count', 'is_featured', 'is_urgent',
        'created_by', 'published_at', 'closed_at'
    ];

    public function save(array $data): int
    {
        $isUpdate = !empty($data['id']);

        // استخراج الأعمدة المسموح بها فقط
        $params = [];
        foreach (self::JOB_COLUMNS as $col) {
            if (array_key_exists($col, $data)) {
                $val = $data[$col];
                $params[':' . $col] = ($val === '' || $val === null) ? null : $val;
            } else {
                $params[':' . $col] = null;
            }
        }

        // توليد slug تلقائياً إذا كان فارغاً
        if (empty($params[':slug']) || $params[':slug'] === null) {
            $title = $data['job_title'] ?? 'job';
            $params[':slug'] = $this->generateSlug($title);
        }

        // القيم الافتراضية
        if (empty($params[':positions_available'])) {
            $params[':positions_available'] = 1;
        }
        if (empty($params[':salary_currency'])) {
            $params[':salary_currency'] = 'SAR';
        }
        if (empty($params[':salary_period'])) {
            $params[':salary_period'] = 'monthly';
        }
        if (empty($params[':application_form_type'])) {
            $params[':application_form_type'] = 'simple';
        }
        if (!isset($params[':salary_negotiable'])) {
            $params[':salary_negotiable'] = 0;
        }
        if (!isset($params[':is_remote'])) {
            $params[':is_remote'] = 0;
        }
        if (!isset($params[':is_featured'])) {
            $params[':is_featured'] = 0;
        }
        if (!isset($params[':is_urgent'])) {
            $params[':is_urgent'] = 0;
        }
        if (empty($params[':status'])) {
            $params[':status'] = 'draft';
        }

        if ($isUpdate) {
            $params[':id'] = (int)$data['id'];

            $stmt = $this->pdo->prepare("
                UPDATE jobs SET
                    entity_id = :entity_id,
                    slug = :slug,
                    job_type = :job_type,
                    employment_type = :employment_type,
                    application_form_type = :application_form_type,
                    external_application_url = :external_application_url,
                    experience_level = :experience_level,
                    category = :category,
                    department = :department,
                    positions_available = :positions_available,
                    salary_min = :salary_min,
                    salary_max = :salary_max,
                    salary_currency = :salary_currency,
                    salary_period = :salary_period,
                    salary_negotiable = :salary_negotiable,
                    country_id = :country_id,
                    city_id = :city_id,
                    work_location = :work_location,
                    is_remote = :is_remote,
                    status = :status,
                    application_deadline = :application_deadline,
                    start_date = :start_date,
                    views_count = :views_count,
                    applications_count = :applications_count,
                    is_featured = :is_featured,
                    is_urgent = :is_urgent,
                    created_by = :created_by,
                    published_at = :published_at,
                    closed_at = :closed_at,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :id
            ");
            $stmt->execute($params);
            return (int)$data['id'];
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO jobs (
                entity_id, slug, job_type, employment_type,
                application_form_type, external_application_url, experience_level,
                category, department, positions_available,
                salary_min, salary_max, salary_currency, salary_period, salary_negotiable,
                country_id, city_id, work_location, is_remote,
                status, application_deadline, start_date,
                views_count, applications_count, is_featured, is_urgent,
                created_by, published_at, closed_at
            ) VALUES (
                :entity_id, :slug, :job_type, :employment_type,
                :application_form_type, :external_application_url, :experience_level,
                :category, :department, :positions_available,
                :salary_min, :salary_max, :salary_currency, :salary_period, :salary_negotiable,
                :country_id, :city_id, :work_location, :is_remote,
                :status, :application_deadline, :start_date,
                :views_count, :applications_count, :is_featured, :is_urgent,
                :created_by, :published_at, :closed_at
            )
        ");
        $stmt->execute($params);
        return (int)$this->pdo->lastInsertId();
    }

    // ================================
    // Save translation
    // ================================
    public function saveTranslation(int $jobId, string $languageCode, array $data): bool
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO job_translations (
                job_id, language_code, job_title, description,
                requirements, responsibilities, benefits
            ) VALUES (
                :job_id, :language_code, :job_title, :description,
                :requirements, :responsibilities, :benefits
            )
            ON DUPLICATE KEY UPDATE
                job_title = VALUES(job_title),
                description = VALUES(description),
                requirements = VALUES(requirements),
                responsibilities = VALUES(responsibilities),
                benefits = VALUES(benefits)
        ");

        return $stmt->execute([
            ':job_id' => $jobId,
            ':language_code' => $languageCode,
            ':job_title' => $data['job_title'] ?? '',
            ':description' => $data['description'] ?? '',
            ':requirements' => $data['requirements'] ?? null,
            ':responsibilities' => $data['responsibilities'] ?? null,
            ':benefits' => $data['benefits'] ?? null
        ]);
    }

    // ================================
    // Delete translation
    // ================================
    public function deleteTranslation(int $jobId, string $languageCode): bool
    {
        $stmt = $this->pdo->prepare(
            "DELETE FROM job_translations WHERE job_id = :job_id AND language_code = :language_code"
        );
        return $stmt->execute([':job_id' => $jobId, ':language_code' => $languageCode]);
    }

    // ================================
    // Delete job
    // ================================
    public function delete(int $id): bool
    {
        // Translations will be deleted automatically due to CASCADE
        $stmt = $this->pdo->prepare("DELETE FROM jobs WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }

    // ================================
    // Increment views count
    // ================================
    public function incrementViews(int $id): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE jobs SET views_count = views_count + 1 WHERE id = :id
        ");
        return $stmt->execute([':id' => $id]);
    }

    // ================================
    // Increment applications count
    // ================================
    public function incrementApplications(int $id): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE jobs SET applications_count = applications_count + 1 WHERE id = :id
        ");
        return $stmt->execute([':id' => $id]);
    }

    // ================================
    // Update status
    // ================================
    public function updateStatus(int $id, string $status): bool
    {
        $allowedStatuses = ['draft', 'published', 'closed', 'filled', 'cancelled'];
        if (!in_array($status, $allowedStatuses, true)) {
            throw new InvalidArgumentException("Invalid status: {$status}");
        }

        $updates = ['status = :status'];
        $params = [':id' => $id, ':status' => $status];

        // تحديث التواريخ حسب الحالة
        if ($status === 'published' && !$this->isPublished($id)) {
            $updates[] = 'published_at = CURRENT_TIMESTAMP';
        } elseif (in_array($status, ['closed', 'filled', 'cancelled'], true)) {
            $updates[] = 'closed_at = CURRENT_TIMESTAMP';
        }

        $sql = "UPDATE jobs SET " . implode(', ', $updates) . " WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }

    // ================================
    // Check if job is published
    // ================================
    private function isPublished(int $id): bool
    {
        $stmt = $this->pdo->prepare("SELECT published_at FROM jobs WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $result = $stmt->fetchColumn();
        return $result !== null && $result !== false;
    }

    // ================================
    // Generate unique slug
    // ================================
    private function generateSlug(string $title): string
    {
        // تنظيف العنوان وتحويله لـ slug
        $slug = preg_replace('/[^a-z0-9\p{Arabic}\-]+/u', '-', mb_strtolower(trim($title)));
        $slug = trim($slug, '-');
        
        if (empty($slug)) {
            $slug = 'job';
        }

        // إضافة رقم عشوائي لضمان عدم التكرار
        $slug .= '-' . time() . '-' . mt_rand(100, 999);

        return $slug;
    }
}
