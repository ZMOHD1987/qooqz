<?php
declare(strict_types=1);

final class PdoJobInterviewsRepository
{
    private PDO $pdo;

    // الأعمدة المسموح بها للفرز
    private const ALLOWED_ORDER_BY = [
        'id', 'application_id', 'interview_type', 'interview_date', 
        'interview_duration', 'status', 'rating', 'created_at', 'updated_at'
    ];

    // أنواع المقابلات
    private const INTERVIEW_TYPES = [
        'phone', 'video', 'in_person', 'technical', 'hr', 'final'
    ];

    // حالات المقابلة
    private const STATUSES = [
        'scheduled', 'confirmed', 'completed', 'cancelled', 'rescheduled', 'no_show'
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
        string $orderBy = 'interview_date',
        string $orderDir = 'ASC'
    ): array {
        $sql = "
            SELECT ji.*,
                   ja.full_name AS applicant_name,
                   ja.email AS applicant_email,
                   ja.phone AS applicant_phone,
                   ja.job_id,
                   j.slug AS job_slug,
                   u.username AS creator_name
            FROM job_interviews ji
            LEFT JOIN job_applications ja ON ji.application_id = ja.id
            LEFT JOIN jobs j ON ja.job_id = j.id
            LEFT JOIN users u ON ji.created_by = u.id
            WHERE 1=1
        ";
        $params = [];

        // فلتر application_id
        if (isset($filters['application_id']) && $filters['application_id'] !== '') {
            $sql .= " AND ji.application_id = :application_id";
            $params[':application_id'] = $filters['application_id'];
        }

        // فلتر job_id
        if (isset($filters['job_id']) && $filters['job_id'] !== '') {
            $sql .= " AND ja.job_id = :job_id";
            $params[':job_id'] = $filters['job_id'];
        }

        // فلتر interview_type
        if (isset($filters['interview_type']) && $filters['interview_type'] !== '') {
            $sql .= " AND ji.interview_type = :interview_type";
            $params[':interview_type'] = $filters['interview_type'];
        }

        // فلتر status
        if (isset($filters['status']) && $filters['status'] !== '') {
            $sql .= " AND ji.status = :status";
            $params[':status'] = $filters['status'];
        }

        // فلتر rating
        if (isset($filters['rating']) && $filters['rating'] !== '') {
            $sql .= " AND ji.rating = :rating";
            $params[':rating'] = $filters['rating'];
        }

        // فلتر التاريخ
        if (!empty($filters['date_from'])) {
            $sql .= " AND DATE(ji.interview_date) >= :date_from";
            $params[':date_from'] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $sql .= " AND DATE(ji.interview_date) <= :date_to";
            $params[':date_to'] = $filters['date_to'];
        }

        // فلتر اليوم (today)
        if (isset($filters['today']) && $filters['today'] === '1') {
            $sql .= " AND DATE(ji.interview_date) = CURDATE()";
        }

        // فلتر الأسبوع القادم
        if (isset($filters['upcoming']) && $filters['upcoming'] === '1') {
            $sql .= " AND ji.interview_date >= NOW() AND ji.interview_date <= DATE_ADD(NOW(), INTERVAL 7 DAY)";
        }

        // البحث
        if (!empty($filters['search'])) {
            $sql .= " AND (ja.full_name LIKE :search OR ja.email LIKE :search OR ji.interviewer_name LIKE :search)";
            $params[':search'] = '%' . $filters['search'] . '%';
        }

        // الفرز
        $orderBy = in_array($orderBy, self::ALLOWED_ORDER_BY, true) ? $orderBy : 'interview_date';
        $orderDir = strtoupper($orderDir) === 'DESC' ? 'DESC' : 'ASC';
        $sql .= " ORDER BY ji.{$orderBy} {$orderDir}";

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
            FROM job_interviews ji
            LEFT JOIN job_applications ja ON ji.application_id = ja.id
            WHERE 1=1
        ";
        $params = [];

        if (isset($filters['application_id']) && $filters['application_id'] !== '') {
            $sql .= " AND ji.application_id = :application_id";
            $params[':application_id'] = $filters['application_id'];
        }

        if (isset($filters['job_id']) && $filters['job_id'] !== '') {
            $sql .= " AND ja.job_id = :job_id";
            $params[':job_id'] = $filters['job_id'];
        }

        if (isset($filters['interview_type']) && $filters['interview_type'] !== '') {
            $sql .= " AND ji.interview_type = :interview_type";
            $params[':interview_type'] = $filters['interview_type'];
        }

        if (isset($filters['status']) && $filters['status'] !== '') {
            $sql .= " AND ji.status = :status";
            $params[':status'] = $filters['status'];
        }

        if (isset($filters['rating']) && $filters['rating'] !== '') {
            $sql .= " AND ji.rating = :rating";
            $params[':rating'] = $filters['rating'];
        }

        if (!empty($filters['date_from'])) {
            $sql .= " AND DATE(ji.interview_date) >= :date_from";
            $params[':date_from'] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $sql .= " AND DATE(ji.interview_date) <= :date_to";
            $params[':date_to'] = $filters['date_to'];
        }

        if (isset($filters['today']) && $filters['today'] === '1') {
            $sql .= " AND DATE(ji.interview_date) = CURDATE()";
        }

        if (isset($filters['upcoming']) && $filters['upcoming'] === '1') {
            $sql .= " AND ji.interview_date >= NOW() AND ji.interview_date <= DATE_ADD(NOW(), INTERVAL 7 DAY)";
        }

        if (!empty($filters['search'])) {
            $sql .= " AND (ja.full_name LIKE :search OR ja.email LIKE :search OR ji.interviewer_name LIKE :search)";
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
            SELECT ji.*,
                   ja.full_name AS applicant_name,
                   ja.email AS applicant_email,
                   ja.phone AS applicant_phone,
                   ja.job_id,
                   j.slug AS job_slug,
                   u.username AS creator_name
            FROM job_interviews ji
            LEFT JOIN job_applications ja ON ji.application_id = ja.id
            LEFT JOIN jobs j ON ja.job_id = j.id
            LEFT JOIN users u ON ji.created_by = u.id
            WHERE ji.id = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    // ================================
    // Get interviews by application
    // ================================
    public function getByApplication(int $applicationId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT ji.*,
                   u.username AS creator_name
            FROM job_interviews ji
            LEFT JOIN users u ON ji.created_by = u.id
            WHERE ji.application_id = :application_id
            ORDER BY ji.interview_date ASC
        ");
        $stmt->execute([':application_id' => $applicationId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ================================
    // Get statistics
    // ================================
    public function getStatistics(array $filters = []): array
    {
        $sql = "
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'scheduled' THEN 1 ELSE 0 END) as scheduled,
                SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
                SUM(CASE WHEN status = 'rescheduled' THEN 1 ELSE 0 END) as rescheduled,
                SUM(CASE WHEN status = 'no_show' THEN 1 ELSE 0 END) as no_show,
                AVG(rating) as average_rating,
                AVG(interview_duration) as average_duration
            FROM job_interviews ji
            LEFT JOIN job_applications ja ON ji.application_id = ja.id
            WHERE 1=1
        ";
        $params = [];

        if (isset($filters['job_id']) && $filters['job_id'] !== '') {
            $sql .= " AND ja.job_id = :job_id";
            $params[':job_id'] = $filters['job_id'];
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    // ================================
    // Create / Update
    // ================================
    private const INTERVIEW_COLUMNS = [
        'application_id', 'interview_type', 'interview_date', 'interview_duration',
        'location', 'meeting_link', 'interviewer_name', 'interviewer_email',
        'status', 'feedback', 'rating', 'notes', 'created_by'
    ];

    public function save(array $data): int
    {
        $isUpdate = !empty($data['id']);

        $params = [];
        foreach (self::INTERVIEW_COLUMNS as $col) {
            if (array_key_exists($col, $data)) {
                $val = $data[$col];
                $params[':' . $col] = ($val === '' || $val === null) ? null : $val;
            } else {
                $params[':' . $col] = null;
            }
        }

        // القيم الافتراضية
        if (!isset($params[':status']) || $params[':status'] === null) {
            $params[':status'] = 'scheduled';
        }
        if (!isset($params[':interview_duration']) || $params[':interview_duration'] === null) {
            $params[':interview_duration'] = 60;
        }

        if ($isUpdate) {
            $params[':id'] = (int)$data['id'];

            $stmt = $this->pdo->prepare("
                UPDATE job_interviews SET
                    application_id = :application_id,
                    interview_type = :interview_type,
                    interview_date = :interview_date,
                    interview_duration = :interview_duration,
                    location = :location,
                    meeting_link = :meeting_link,
                    interviewer_name = :interviewer_name,
                    interviewer_email = :interviewer_email,
                    status = :status,
                    feedback = :feedback,
                    rating = :rating,
                    notes = :notes,
                    created_by = :created_by,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :id
            ");
            $stmt->execute($params);
            return (int)$data['id'];
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO job_interviews (
                application_id, interview_type, interview_date, interview_duration,
                location, meeting_link, interviewer_name, interviewer_email,
                status, feedback, rating, notes, created_by
            ) VALUES (
                :application_id, :interview_type, :interview_date, :interview_duration,
                :location, :meeting_link, :interviewer_name, :interviewer_email,
                :status, :feedback, :rating, :notes, :created_by
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
            UPDATE job_interviews 
            SET status = :status, updated_at = CURRENT_TIMESTAMP
            WHERE id = :id
        ");
        return $stmt->execute([':id' => $id, ':status' => $status]);
    }

    // ================================
    // Add feedback
    // ================================
    public function addFeedback(int $id, string $feedback, ?int $rating = null): bool
    {
        $sql = "UPDATE job_interviews SET feedback = :feedback";
        $params = [':id' => $id, ':feedback' => $feedback];

        if ($rating !== null) {
            $sql .= ", rating = :rating";
            $params[':rating'] = $rating;
        }

        $sql .= ", updated_at = CURRENT_TIMESTAMP WHERE id = :id";
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }

    // ================================
    // Reschedule interview
    // ================================
    public function reschedule(int $id, string $newDate, ?int $newDuration = null): bool
    {
        $sql = "UPDATE job_interviews SET interview_date = :new_date, status = 'rescheduled'";
        $params = [':id' => $id, ':new_date' => $newDate];

        if ($newDuration !== null) {
            $sql .= ", interview_duration = :new_duration";
            $params[':new_duration'] = $newDuration;
        }

        $sql .= ", updated_at = CURRENT_TIMESTAMP WHERE id = :id";
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }

    // ================================
    // Delete
    // ================================
    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM job_interviews WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }

    // ================================
    // Get interview types
    // ================================
    public function getInterviewTypes(): array
    {
        return self::INTERVIEW_TYPES;
    }

    // ================================
    // Get statuses
    // ================================
    public function getStatuses(): array
    {
        return self::STATUSES;
    }
}
