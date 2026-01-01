<?php
// htdocs/api/models/Service.php
// Model للخدمات (Service Model)
// يدعم: CRUD، تسعير زمني، الحجوزات/المواعيد، التوفر، الربط مع التصنيفات والمزودين، التقييمات والإحصاءات

// تحميل الملفات المطلوبة
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/utils.php';
require_once __DIR__ . '/../helpers/security.php';

class Service
{
    private $mysqli;

    public function __construct()
    {
        $this->mysqli = connectDB();
    }

    /**
     * إنشاء خدمة جديدة
     *
     * @param array $data (provider_id, title, slug, description, duration_minutes, price, currency, is_active, capacity, buffer_time)
     * @return array|false
     */
    public function create($data)
    {
        $sql = "INSERT INTO services (
                    provider_id, title, slug, short_description, description,
                    duration_minutes, price, currency, is_active, capacity,
                    buffer_time_minutes, created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";

        $stmt = $this->mysqli->prepare($sql);
        if (!$stmt) {
            Utils::log("Service create prepare failed: " . $this->mysqli->error, 'ERROR');
            return false;
        }

        $providerId = $data['provider_id'];
        $title = $data['title'];
        $slug = $data['slug'] ?? Utils::createSlug($title);
        $shortDesc = $data['short_description'] ?? null;
        $description = $data['description'] ?? null;
        $duration = $data['duration_minutes'] ?? 60;
        $price = $data['price'] ?? 0.0;
        $currency = $data['currency'] ?? DEFAULT_CURRENCY;
        $isActive = isset($data['is_active']) ? (int)$data['is_active'] : 1;
        $capacity = $data['capacity'] ?? 1; // how many simultaneous bookings
        $buffer = $data['buffer_time_minutes'] ?? 0;

        $stmt->bind_param(
            'issssiidiii',
            $providerId, $title, $slug, $shortDesc, $description,
            $duration, $price, $currency, $isActive, $capacity, $buffer
        );

        if ($stmt->execute()) {
            $id = $stmt->insert_id;
            $stmt->close();
            Utils::log("Service created: ID {$id}, Title: {$title}", 'INFO');
            return $this->findById($id);
        }

        $error = $stmt->error;
        $stmt->close();
        Utils::log("Service create failed: " . $error, 'ERROR');
        return false;
    }

    /**
     * جلب خدمة حسب ID
     *
     * @param int $id
     * @return array|null
     */
    public function findById($id)
    {
        $sql = "SELECT s.*, u.username as provider_username, u.email as provider_email
                FROM services s
                LEFT JOIN users u ON s.provider_id = u.id
                WHERE s.id = ? LIMIT 1";
        $stmt = $this->mysqli->prepare($sql);
        if (!$stmt) return null;
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res->num_rows === 0) { $stmt->close(); return null; }
        $service = $res->fetch_assoc();
        $stmt->close();

        // روابط مفيدة
        $service['categories'] = $this->getCategories($id);
        $service['pricing'] = $this->getPricing($id);
        $service['reviews'] = $this->getReviews($id, 1, 10);
        return $service;
    }

    /**
     * جلب خدمة حسب Slug
     *
     * @param string $slug
     * @return array|null
     */
    public function findBySlug($slug)
    {
        $sql = "SELECT id FROM services WHERE slug = ? LIMIT 1";
        $stmt = $this->mysqli->prepare($sql);
        if (!$stmt) return null;
        $stmt->bind_param('s', $slug);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res->num_rows === 0) { $stmt->close(); return null; }
        $row = $res->fetch_assoc();
        $stmt->close();
        return $this->findById($row['id']);
    }

    /**
     * قائمة الخدمات مع فلترة وتمييز
     *
     * @param array $filters (provider_id, category_id, is_active, search, min_price, max_price)
     * @param int $page
     * @param int $perPage
     * @return array
     */
    public function getAll($filters = [], $page = 1, $perPage = 20)
    {
        $where = [];
        $params = [];
        $types = '';
        $joins = [];

        if (isset($filters['provider_id'])) {
            $where[] = "s.provider_id = ?";
            $params[] = $filters['provider_id'];
            $types .= 'i';
        }

        if (isset($filters['is_active'])) {
            $where[] = "s.is_active = ?";
            $params[] = (int)$filters['is_active'];
            $types .= 'i';
        }

        if (isset($filters['category_id'])) {
            $joins[] = "INNER JOIN service_categories sc ON s.id = sc.service_id";
            $where[] = "sc.category_id = ?";
            $params[] = $filters['category_id'];
            $types .= 'i';
        }

        if (isset($filters['min_price'])) {
            $joins[] = "LEFT JOIN service_pricing sp ON s.id = sp.service_id";
            $where[] = "sp.price >= ?";
            $params[] = $filters['min_price'];
            $types .= 'd';
        }

        if (isset($filters['max_price'])) {
            if (!in_array("LEFT JOIN service_pricing sp ON s.id = sp.service_id", $joins)) {
                $joins[] = "LEFT JOIN service_pricing sp ON s.id = sp.service_id";
            }
            $where[] = "sp.price <= ?";
            $params[] = $filters['max_price'];
            $types .= 'd';
        }

        if (isset($filters['search'])) {
            $where[] = "(s.title LIKE ? OR s.short_description LIKE ?)";
            $term = '%' . $filters['search'] . '%';
            $params[] = $term;
            $params[] = $term;
            $types .= 'ss';
        }

        $joinClause = !empty($joins) ? implode(' ', array_unique($joins)) : '';
        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        // count
        $countSql = "SELECT COUNT(DISTINCT s.id) as total FROM services s {$joinClause} {$whereClause}";
        $stmt = $this->mysqli->prepare($countSql);
        if ($stmt && !empty($params)) $stmt->bind_param($types, ...$params);
        if ($stmt) {
            $stmt->execute();
            $res = $stmt->get_result();
            $total = $res->fetch_assoc()['total'];
            $stmt->close();
        } else {
            $total = 0;
        }

        $offset = ($page - 1) * $perPage;
        $sql = "SELECT DISTINCT s.* FROM services s {$joinClause} {$whereClause} ORDER BY s.created_at DESC LIMIT ? OFFSET ?";
        $params[] = $perPage;
        $params[] = $offset;
        $types .= 'ii';

        $stmt = $this->mysqli->prepare($sql);
        if ($stmt && !empty($params)) $stmt->bind_param($types, ...$params);
        if (!$stmt) return ['data' => [], 'total' => 0, 'page' => $page, 'per_page' => $perPage, 'total_pages' => 0];

        $stmt->execute();
        $res = $stmt->get_result();
        $data = [];
        while ($r = $res->fetch_assoc()) {
            $r['pricing'] = $this->getPricing($r['id']);
            $r['primary_category'] = $this->getPrimaryCategory($r['id']);
            $data[] = $r;
        }
        $stmt->close();

        return ['data' => $data, 'total' => (int)$total, 'page' => $page, 'per_page' => $perPage, 'total_pages' => ceil($total / $perPage)];
    }

    /**
     * تحديث خدمة
     *
     * @param int $id
     * @param array $data
     * @return bool
     */
    public function update($id, $data)
    {
        $allowed = [
            'title' => 's',
            'slug' => 's',
            'short_description' => 's',
            'description' => 's',
            'duration_minutes' => 'i',
            'price' => 'd',
            'currency' => 's',
            'is_active' => 'i',
            'capacity' => 'i',
            'buffer_time_minutes' => 'i'
        ];

        $fields = [];
        $params = [];
        $types = '';

        foreach ($allowed as $f => $t) {
            if (array_key_exists($f, $data)) {
                $fields[] = "{$f} = ?";
                $params[] = $data[$f];
                $types .= $t;
            }
        }

        if (empty($fields)) return false;
        $fields[] = "updated_at = NOW()";
        $sql = "UPDATE services SET " . implode(', ', $fields) . " WHERE id = ?";
        $params[] = $id;
        $types .= 'i';

        $stmt = $this->mysqli->prepare($sql);
        if (!$stmt) {
            Utils::log("Service update prepare failed: " . $this->mysqli->error, 'ERROR');
            return false;
        }

        $stmt->bind_param($types, ...$params);
        $ok = $stmt->execute();
        $stmt->close();

        if ($ok) Utils::log("Service updated: ID {$id}", 'INFO');
        return $ok;
    }

    /**
     * حذف خدمة (soft/hard)
     *
     * @param int $id
     * @param bool $hard
     * @return bool
     */
    public function delete($id, $hard = false)
    {
        if ($hard) {
            // حذف بيانات مرتبطة
            $this->mysqli->query("DELETE FROM service_pricing WHERE service_id = " . (int)$id);
            $this->mysqli->query("DELETE FROM service_categories WHERE service_id = " . (int)$id);
            $this->mysqli->query("DELETE FROM service_reviews WHERE service_id = " . (int)$id);
            $sql = "DELETE FROM services WHERE id = ?";
            $stmt = $this->mysqli->prepare($sql);
            if (!$stmt) return false;
            $stmt->bind_param('i', $id);
            $ok = $stmt->execute();
            $stmt->close();
            if ($ok) Utils::log("Service hard deleted: ID {$id}", 'WARNING');
            return $ok;
        } else {
            return $this->update($id, ['is_active' => 0]);
        }
    }

    /**
     * حفظ/تحديث تسعير الخدمة
     *
     * @param int $serviceId
     * @param array $data (price, currency, duration_minutes_optional)
     * @return bool
     */
    public function savePricing($serviceId, $data)
    {
        $sql = "INSERT INTO service_pricing (service_id, price, currency, duration_minutes, created_at)
                VALUES (?, ?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE price = VALUES(price), currency = VALUES(currency), duration_minutes = VALUES(duration_minutes)";
        $stmt = $this->mysqli->prepare($sql);
        if (!$stmt) return false;

        $duration = $data['duration_minutes'] ?? null;
        $stmt->bind_param('iddi', $serviceId, $data['price'], $data['currency'], $duration);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    /**
     * جلب تسعير الخدمة
     *
     * @param int $serviceId
     * @return array|null
     */
    public function getPricing($serviceId)
    {
        $sql = "SELECT * FROM service_pricing WHERE service_id = ? LIMIT 1";
        $stmt = $this->mysqli->prepare($sql);
        if (!$stmt) return null;
        $stmt->bind_param('i', $serviceId);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res->num_rows === 0) { $stmt->close(); return null; }
        $pricing = $res->fetch_assoc();
        $stmt->close();
        return $pricing;
    }

    /**
     * إرفاق فئة بالخدمة
     *
     * @param int $serviceId
     * @param int $categoryId
     * @param bool $isPrimary
     * @return bool
     */
    public function attachCategory($serviceId, $categoryId, $isPrimary = false)
    {
        if ($isPrimary) {
            $this->mysqli->query("UPDATE service_categories SET is_primary = 0 WHERE service_id = " . (int)$serviceId);
        }

        $sql = "INSERT INTO service_categories (service_id, category_id, is_primary, created_at)
                VALUES (?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE is_primary = VALUES(is_primary)";
        $stmt = $this->mysqli->prepare($sql);
        if (!$stmt) return false;
        $isPrimaryInt = $isPrimary ? 1 : 0;
        $stmt->bind_param('iii', $serviceId, $categoryId, $isPrimaryInt);
        $ok = $stmt->execute();
        $stmt->close();
        if ($ok) Utils::log("Service {$serviceId} attached to Category {$categoryId}", 'INFO');
        return $ok;
    }

    /**
     * الحصول على تصنيفات الخدمة
     *
     * @param int $serviceId
     * @return array
     */
    public function getCategories($serviceId)
    {
        $sql = "SELECT sc.*, c.name, c.slug FROM service_categories sc
                INNER JOIN categories c ON sc.category_id = c.id
                WHERE sc.service_id = ?
                ORDER BY sc.is_primary DESC, sc.created_at ASC";
        $stmt = $this->mysqli->prepare($sql);
        if (!$stmt) return [];
        $stmt->bind_param('i', $serviceId);
        $stmt->execute();
        $res = $stmt->get_result();
        $cats = [];
        while ($r = $res->fetch_assoc()) $cats[] = $r;
        $stmt->close();
        return $cats;
    }

    /**
     * الحصول على الفئة الأساسية (إن وُجدت)
     *
     * @param int $serviceId
     * @return array|null
     */
    public function getPrimaryCategory($serviceId)
    {
        $sql = "SELECT c.* FROM service_categories sc
                INNER JOIN categories c ON sc.category_id = c.id
                WHERE sc.service_id = ? AND sc.is_primary = 1 LIMIT 1";
        $stmt = $this->mysqli->prepare($sql);
        if (!$stmt) return null;
        $stmt->bind_param('i', $serviceId);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res->num_rows === 0) { $stmt->close(); return null; }
        $row = $res->fetch_assoc();
        $stmt->close();
        return $row;
    }

    /**
     * التحقق من توفر وقت للحجز
     *
     * @param int $serviceId
     * @param string $startDatetime (Y-m-d H:i:s)
     * @return bool
     */
    public function isAvailable($serviceId, $startDatetime)
    {
        // جلب الخدمة
        $service = $this->findById($serviceId);
        if (!$service || !$service['is_active']) return false;

        $duration = (int)($service['duration_minutes'] ?? 60);
        $buffer = (int)($service['buffer_time_minutes'] ?? 0);

        $startTs = strtotime($startDatetime);
        $endTs = $startTs + ($duration * 60) + ($buffer * 60);

        // حساب الحجوزات المتداخلة
        $sql = "SELECT COUNT(*) as cnt FROM service_bookings
                WHERE service_id = ?
                AND ((UNIX_TIMESTAMP(start_at) < ? AND UNIX_TIMESTAMP(end_at) > ?)
                     OR (UNIX_TIMESTAMP(start_at) >= ? AND UNIX_TIMESTAMP(start_at) < ?))";
        $stmt = $this->mysqli->prepare($sql);
        if (!$stmt) return false;

        $stmt->bind_param('iiiii', $serviceId, $endTs, $startTs, $startTs, $endTs);
        $stmt->execute();
        $res = $stmt->get_result();
        $count = (int)($res->fetch_assoc()['cnt'] ?? 0);
        $stmt->close();

        // مقارنة بالسعة
        $capacity = (int)($service['capacity'] ?? 1);
        return $count < $capacity;
    }

    /**
     * حجز خدمة (إضافة booking)
     *
     * @param array $data (service_id, user_id, start_at, end_at, attendees, notes)
     * @return array ['success' => bool, 'message' => string, 'booking_id' => int|null]
     */
    public function book($data)
    {
        $serviceId = $data['service_id'];
        $userId = $data['user_id'] ?? null;
        $startAt = $data['start_at']; // Y-m-d H:i:s
        $endAt = $data['end_at'] ?? date('Y-m-d H:i:s', strtotime($startAt) + (($this->findById($serviceId)['duration_minutes'] ?? 60) * 60));
        $attendees = $data['attendees'] ?? 1;

        if (!$this->isAvailable($serviceId, $startAt)) {
            return ['success' => false, 'message' => 'Requested slot not available', 'booking_id' => null];
        }

        // السعر والحساب
        $pricing = $this->getPricing($serviceId);
        $price = $pricing['price'] ?? 0.0;
        $total = $price * $attendees;

        $sql = "INSERT INTO service_bookings (service_id, user_id, start_at, end_at, attendees, price, total, status, notes, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        $stmt = $this->mysqli->prepare($sql);
        if (!$stmt) return ['success' => false, 'message' => 'DB error', 'booking_id' => null];

        $status = BOOKING_STATUS_PENDING;
        $notes = $data['notes'] ?? null;

        $stmt->bind_param('iissiddss', $serviceId, $userId, $startAt, $endAt, $attendees, $price, $total, $status, $notes);
        if ($stmt->execute()) {
            $bookingId = $stmt->insert_id;
            $stmt->close();
            Utils::log("Service booked: service {$serviceId}, booking {$bookingId}, user {$userId}", 'INFO');
            return ['success' => true, 'message' => 'Booking created', 'booking_id' => $bookingId];
        }

        $err = $stmt->error;
        $stmt->close();
        Utils::log("Service booking failed: " . $err, 'ERROR');
        return ['success' => false, 'message' => 'Failed to create booking', 'booking_id' => null];
    }

    /**
     * جلب حجوزات خدمة
     *
     * @param int $serviceId
     * @param int $page
     * @param int $perPage
     * @return array
     */
    public function getBookings($serviceId, $page = 1, $perPage = 20)
    {
        $offset = ($page - 1) * $perPage;
        $sql = "SELECT sb.*, u.username, u.email FROM service_bookings sb
                LEFT JOIN users u ON sb.user_id = u.id
                WHERE sb.service_id = ?
                ORDER BY sb.start_at DESC
                LIMIT ? OFFSET ?";
        $stmt = $this->mysqli->prepare($sql);
        if (!$stmt) return ['data' => [], 'total' => 0];
        $stmt->bind_param('iii', $serviceId, $perPage, $offset);
        $stmt->execute();
        $res = $stmt->get_result();
        $bookings = [];
        while ($r = $res->fetch_assoc()) $bookings[] = $r;
        $stmt->close();

        $countSql = "SELECT COUNT(*) as total FROM service_bookings WHERE service_id = ?";
        $stmt = $this->mysqli->prepare($countSql);
        $stmt->bind_param('i', $serviceId);
        $stmt->execute();
        $cr = $stmt->get_result();
        $total = $cr->fetch_assoc()['total'] ?? 0;
        $stmt->close();

        return ['data' => $bookings, 'total' => (int)$total, 'page' => $page, 'per_page' => $perPage, 'total_pages' => ceil($total / $perPage)];
    }

    /**
     * إلغاء حجز
     *
     * @param int $bookingId
     * @param string|null $reason
     * @return bool
     */
    public function cancelBooking($bookingId, $reason = null)
    {
        $sql = "UPDATE service_bookings SET status = ?, cancellation_reason = ?, updated_at = NOW() WHERE id = ?";
        $stmt = $this->mysqli->prepare($sql);
        if (!$stmt) return false;
        $status = BOOKING_STATUS_CANCELLED;
        $stmt->bind_param('ssi', $status, $reason, $bookingId);
        $ok = $stmt->execute();
        $stmt->close();
        if ($ok) Utils::log("Booking cancelled: ID {$bookingId}", 'INFO');
        return $ok;
    }

    /**
     * التعليقات والتقييمات للخدمة
     *
     * @param int $serviceId
     * @param int $page
     * @param int $perPage
     * @return array
     */
    public function getReviews($serviceId, $page = 1, $perPage = 10)
    {
        $offset = ($page - 1) * $perPage;
        $sql = "SELECT sr.*, u.username, u.avatar FROM service_reviews sr
                LEFT JOIN users u ON sr.user_id = u.id
                WHERE sr.service_id = ?
                ORDER BY sr.created_at DESC
                LIMIT ? OFFSET ?";
        $stmt = $this->mysqli->prepare($sql);
        if (!$stmt) return ['data' => [], 'total' => 0];
        $stmt->bind_param('iii', $serviceId, $perPage, $offset);
        $stmt->execute();
        $res = $stmt->get_result();
        $reviews = [];
        while ($r = $res->fetch_assoc()) $reviews[] = $r;
        $stmt->close();

        $countSql = "SELECT COUNT(*) as total FROM service_reviews WHERE service_id = ?";
        $stmt = $this->mysqli->prepare($countSql);
        $stmt->bind_param('i', $serviceId);
        $stmt->execute();
        $cr = $stmt->get_result();
        $total = $cr->fetch_assoc()['total'] ?? 0;
        $stmt->close();

        return ['data' => $reviews, 'total' => (int)$total, 'page' => $page, 'per_page' => $perPage, 'total_pages' => ceil($total / $perPage)];
    }

    /**
     * إضافة تقييم/مراجعة
     *
     * @param int $serviceId
     * @param int $userId
     * @param int $rating
     * @param string $comment
     * @return bool
     */
    public function addReview($serviceId, $userId, $rating, $comment = null)
    {
        $sql = "INSERT INTO service_reviews (service_id, user_id, rating, comment, created_at)
                VALUES (?, ?, ?, ?, NOW())";
        $stmt = $this->mysqli->prepare($sql);
        if (!$stmt) return false;
        $stmt->bind_param('iiis', $serviceId, $userId, $rating, $comment);
        $ok = $stmt->execute();
        $stmt->close();

        if ($ok) {
            $this->updateRating($serviceId);
            Utils::log("Service review added: service {$serviceId}, user {$userId}", 'INFO');
        }
        return $ok;
    }

    /**
     * تحديث متوسط التقييم وعدد التقييمات
     *
     * @param int $serviceId
     * @return bool
     */
    public function updateRating($serviceId)
    {
        $sql = "UPDATE services SET
                rating_average = (SELECT AVG(rating) FROM service_reviews WHERE service_id = ?),
                rating_count = (SELECT COUNT(*) FROM service_reviews WHERE service_id = ?)
                WHERE id = ?";
        $stmt = $this->mysqli->prepare($sql);
        if (!$stmt) return false;
        $stmt->bind_param('iii', $serviceId, $serviceId, $serviceId);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    /**
     * إحصائيات للخدمات
     *
     * @param int|null $providerId
     * @return array
     */
    public function getStatistics($providerId = null)
    {
        $where = $providerId ? "WHERE provider_id = " . (int)$providerId : "";
        $sql = "SELECT
                    COUNT(*) as total_services,
                    SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_services,
                    AVG(rating_average) as avg_rating,
                    SUM(COALESCE((SELECT COUNT(*) FROM service_bookings sb WHERE sb.service_id = s.id),0)) as total_bookings
                FROM services s
                {$where}";
        $res = $this->mysqli->query($sql);
        return $res ? $res->fetch_assoc() : [];
    }

    /**
     * تحقق من تفرد slug
     *
     * @param string $slug
     * @param int|null $exceptId
     * @return bool
     */
    public function isSlugUnique($slug, $exceptId = null)
    {
        $sql = "SELECT COUNT(*) as cnt FROM services WHERE slug = ?";
        if ($exceptId) {
            $sql .= " AND id != ?";
            $stmt = $this->mysqli->prepare($sql);
            $stmt->bind_param('si', $slug, $exceptId);
        } else {
            $stmt = $this->mysqli->prepare($sql);
            $stmt->bind_param('s', $slug);
        }
        if (!$stmt) return false;
        $stmt->execute();
        $res = $stmt->get_result();
        $count = $res->fetch_assoc()['cnt'] ?? 0;
        $stmt->close();
        return $count == 0;
    }
}

// تم تحميل Service Model بنجاح
?>