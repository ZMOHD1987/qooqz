<?php
declare(strict_types=1);

final class PdoJobAlertsRepository
{
    private PDO $pdo;

    // الأعمدة المسموح بها للفرز
    private const ALLOWED_ORDER_BY = [
        'id', 'user_id', 'alert_name', 'job_type', 'experience_level',
        'country_id', 'city_id', 'salary_min', 'is_active', 'frequency',
        'last_sent_at', 'created_at', 'updated_at'
    ];

    // الأعمدة القابلة للفلاتر
    private const FILTERABLE_COLUMNS = [
        'id', 'user_id', 'job_type', 'experience_level', 'country_id',
        'city_id', 'is_active', 'frequency'
    ];

    // الأعمدة المسموحة في جدول job_alerts
    private const ALERT_COLUMNS = [
        'alert_name', 'keywords', 'job_type', 'experience_level',
        'country_id', 'city_id', 'salary_min', 'is_active', 'frequency'
    ];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // ================================
    // List with dynamic filters, search, ordering, pagination
    // ================================
    public function all(
        int $userId,
        ?int $limit = null,
        ?int $offset = null,
        array $filters = [],
        string $orderBy = 'created_at',
        string $orderDir = 'DESC'
    ): array {
        // استعلام بسيط بدون joins اختيارية
        $sql = "
            SELECT ja.*
            FROM job_alerts ja
            WHERE ja.user_id = :user_id
        ";
        $params = [':user_id' => $userId];

        // تطبيق كل الفلاتر بشكل ديناميكي
        foreach (self::FILTERABLE_COLUMNS as $col) {
            if (isset($filters[$col]) && $filters[$col] !== '') {
                if ($col === 'id') {
                    $sql .= " AND ja.{$col} = :{$col}";
                    $params[":{$col}"] = $filters[$col];
                } else {
                    $sql .= " AND ja.{$col} = :{$col}";
                    $params[":{$col}"] = $filters[$col];
                }
            }
        }

        // البحث في الاسم والكلمات المفتاحية
        if (!empty($filters['search'])) {
            $sql .= " AND (ja.alert_name LIKE :search OR ja.keywords LIKE :search)";
            $params[':search'] = '%' . $filters['search'] . '%';
        }

        // فلتر نطاق الراتب
        if (isset($filters['salary_min']) && is_numeric($filters['salary_min'])) {
            $sql .= " AND ja.salary_min >= :salary_min";
            $params[':salary_min'] = $filters['salary_min'];
        }

        if (isset($filters['salary_max']) && is_numeric($filters['salary_max'])) {
            $sql .= " AND ja.salary_min <= :salary_max";
            $params[':salary_max'] = $filters['salary_max'];
        }

        // الفرز
        $orderBy = in_array($orderBy, self::ALLOWED_ORDER_BY, true) ? $orderBy : 'created_at';
        $orderDir = strtoupper($orderDir) === 'ASC' ? 'ASC' : 'DESC';
        $sql .= " ORDER BY ja.{$orderBy} {$orderDir}";

        // Pagination
        if ($limit !== null) {
            $sql .= " LIMIT " . (int)$limit;
        }
        if ($offset !== null) {
            $sql .= " OFFSET " . (int)$offset;
        }

        $stmt = $this->pdo->prepare($sql);
        
        foreach ($params as $key => $value) {
            $type = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
            $stmt->bindValue($key, $value, $type);
        }
        
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ================================
    // Count for pagination
    // ================================
    public function count(int $userId, array $filters = []): int
    {
        $sql = "SELECT COUNT(*) FROM job_alerts WHERE user_id = :user_id";
        $params = [':user_id' => $userId];

        foreach (self::FILTERABLE_COLUMNS as $col) {
            if (isset($filters[$col]) && $filters[$col] !== '') {
                if ($col === 'id') {
                    $sql .= " AND {$col} = :{$col}";
                    $params[":{$col}"] = $filters[$col];
                } else {
                    $sql .= " AND {$col} = :{$col}";
                    $params[":{$col}"] = $filters[$col];
                }
            }
        }

        if (!empty($filters['search'])) {
            $sql .= " AND (alert_name LIKE :search OR keywords LIKE :search)";
            $params[':search'] = '%' . $filters['search'] . '%';
        }

        if (isset($filters['salary_min']) && is_numeric($filters['salary_min'])) {
            $sql .= " AND salary_min >= :salary_min";
            $params[':salary_min'] = $filters['salary_min'];
        }

        if (isset($filters['salary_max']) && is_numeric($filters['salary_max'])) {
            $sql .= " AND salary_min <= :salary_max";
            $params[':salary_max'] = $filters['salary_max'];
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    // ================================
    // Find by ID
    // ================================
    public function find(int $userId, int $id): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT ja.*
            FROM job_alerts ja
            WHERE ja.user_id = :user_id AND ja.id = :id
            LIMIT 1
        ");
        $stmt->execute([':user_id' => $userId, ':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    // ================================
    // Create / Update
    // ================================
    public function save(int $userId, array $data): int
    {
        $isUpdate = !empty($data['id']);

        // استخراج الأعمدة المسموح بها فقط
        $params = [];
        foreach (self::ALERT_COLUMNS as $col) {
            if (array_key_exists($col, $data)) {
                $val = $data[$col];
                $params[':' . $col] = ($val === '' || $val === null) ? null : $val;
            } else {
                $params[':' . $col] = null;
            }
        }

        // التحقق من القيم المطلوبة
        if (empty($params[':alert_name'])) {
            throw new InvalidArgumentException('Alert name is required');
        }

        // تعيين قيم افتراضية
        if (!isset($params[':is_active']) || $params[':is_active'] === null) {
            $params[':is_active'] = 1;
        }
        if (!isset($params[':frequency']) || $params[':frequency'] === null) {
            $params[':frequency'] = 'daily';
        }

        if ($isUpdate) {
            $params[':user_id'] = $userId;
            $params[':id'] = (int)$data['id'];

            $stmt = $this->pdo->prepare("
                UPDATE job_alerts SET
                    alert_name = :alert_name,
                    keywords = :keywords,
                    job_type = :job_type,
                    experience_level = :experience_level,
                    country_id = :country_id,
                    city_id = :city_id,
                    salary_min = :salary_min,
                    is_active = :is_active,
                    frequency = :frequency,
                    updated_at = CURRENT_TIMESTAMP
                WHERE user_id = :user_id AND id = :id
            ");
            $stmt->execute($params);
            return (int)$data['id'];
        }

        $params[':user_id'] = $userId;

        $stmt = $this->pdo->prepare("
            INSERT INTO job_alerts (
                user_id, alert_name, keywords, job_type, experience_level,
                country_id, city_id, salary_min, is_active, frequency
            ) VALUES (
                :user_id, :alert_name, :keywords, :job_type, :experience_level,
                :country_id, :city_id, :salary_min, :is_active, :frequency
            )
        ");
        $stmt->execute($params);
        return (int)$this->pdo->lastInsertId();
    }

    // ================================
    // Delete
    // ================================
    public function delete(int $userId, int $id): bool
    {
        $stmt = $this->pdo->prepare(
            "DELETE FROM job_alerts WHERE user_id = :user_id AND id = :id"
        );
        return $stmt->execute([':user_id' => $userId, ':id' => $id]);
    }

    // ================================
    // Get alerts due for sending
    // ================================
    public function getDueAlerts(string $frequency = 'daily'): array
    {
        $sql = "
            SELECT ja.*
            FROM job_alerts ja
            WHERE ja.is_active = 1
              AND ja.frequency = :frequency
        ";

        $params = [':frequency' => $frequency];

        // حسب التردد
        if ($frequency === 'instant') {
            // لا حاجة لشرط إضافي للفوري
        } elseif ($frequency === 'daily') {
            $sql .= " AND (ja.last_sent_at IS NULL OR ja.last_sent_at < DATE_SUB(NOW(), INTERVAL 1 DAY))";
        } elseif ($frequency === 'weekly') {
            $sql .= " AND (ja.last_sent_at IS NULL OR ja.last_sent_at < DATE_SUB(NOW(), INTERVAL 1 WEEK))";
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ================================
    // Update last sent timestamp
    // ================================
    public function updateLastSent(int $alertId): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE job_alerts 
            SET last_sent_at = CURRENT_TIMESTAMP 
            WHERE id = :id
        ");
        return $stmt->execute([':id' => $alertId]);
    }

    // ================================
    // Toggle active status
    // ================================
    public function toggleActive(int $userId, int $id): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE job_alerts 
            SET is_active = NOT is_active,
                updated_at = CURRENT_TIMESTAMP
            WHERE user_id = :user_id AND id = :id
        ");
        return $stmt->execute([':user_id' => $userId, ':id' => $id]);
    }

    // ================================
    // Get statistics
    // ================================
    public function getStatistics(int $userId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                COUNT(*) as total_alerts,
                SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_alerts,
                SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) as inactive_alerts,
                SUM(CASE WHEN frequency = 'instant' THEN 1 ELSE 0 END) as instant_alerts,
                SUM(CASE WHEN frequency = 'daily' THEN 1 ELSE 0 END) as daily_alerts,
                SUM(CASE WHEN frequency = 'weekly' THEN 1 ELSE 0 END) as weekly_alerts,
                MAX(created_at) as latest_alert_date
            FROM job_alerts
            WHERE user_id = :user_id
        ");
        $stmt->execute([':user_id' => $userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }
}