<?php
declare(strict_types=1);

final class PdoEntitySettingsRepository
{
    private PDO $pdo;

    // الأعمدة المسموح بها للفرز
    private const ALLOWED_ORDER_BY = [
        'entity_id', 'min_order_amount', 'delivery_radius_km', 
        'created_at', 'updated_at', 'auto_accept_orders', 
        'allow_cod', 'is_visible', 'maintenance_mode'
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
        string $orderBy = 'entity_id',
        string $orderDir = 'DESC'
    ): array {
        $sql = "
            SELECT es.*,
                   e.store_name,
                   e.status,
                   e.email
            FROM entity_settings es
            LEFT JOIN entities e ON es.entity_id = e.id
            WHERE 1=1
        ";
        $params = [];

        // تطبيق الفلاتر
        if (isset($filters['entity_id']) && is_numeric($filters['entity_id'])) {
            $sql .= " AND es.entity_id = :entity_id";
            $params[':entity_id'] = (int)$filters['entity_id'];
        }

        if (isset($filters['auto_accept_orders']) && in_array((int)$filters['auto_accept_orders'], [0, 1], true)) {
            $sql .= " AND es.auto_accept_orders = :auto_accept_orders";
            $params[':auto_accept_orders'] = (int)$filters['auto_accept_orders'];
        }

        if (isset($filters['allow_cod']) && in_array((int)$filters['allow_cod'], [0, 1], true)) {
            $sql .= " AND es.allow_cod = :allow_cod";
            $params[':allow_cod'] = (int)$filters['allow_cod'];
        }

        if (isset($filters['allow_online_booking']) && in_array((int)$filters['allow_online_booking'], [0, 1], true)) {
            $sql .= " AND es.allow_online_booking = :allow_online_booking";
            $params[':allow_online_booking'] = (int)$filters['allow_online_booking'];
        }

        if (isset($filters['booking_cancellation_allowed']) && in_array((int)$filters['booking_cancellation_allowed'], [0, 1], true)) {
            $sql .= " AND es.booking_cancellation_allowed = :booking_cancellation_allowed";
            $params[':booking_cancellation_allowed'] = (int)$filters['booking_cancellation_allowed'];
        }

        if (isset($filters['allow_preorders']) && in_array((int)$filters['allow_preorders'], [0, 1], true)) {
            $sql .= " AND es.allow_preorders = :allow_preorders";
            $params[':allow_preorders'] = (int)$filters['allow_preorders'];
        }

        if (isset($filters['is_visible']) && in_array((int)$filters['is_visible'], [0, 1], true)) {
            $sql .= " AND es.is_visible = :is_visible";
            $params[':is_visible'] = (int)$filters['is_visible'];
        }

        if (isset($filters['maintenance_mode']) && in_array((int)$filters['maintenance_mode'], [0, 1], true)) {
            $sql .= " AND es.maintenance_mode = :maintenance_mode";
            $params[':maintenance_mode'] = (int)$filters['maintenance_mode'];
        }

        if (isset($filters['show_reviews']) && in_array((int)$filters['show_reviews'], [0, 1], true)) {
            $sql .= " AND es.show_reviews = :show_reviews";
            $params[':show_reviews'] = (int)$filters['show_reviews'];
        }

        if (isset($filters['show_contact_info']) && in_array((int)$filters['show_contact_info'], [0, 1], true)) {
            $sql .= " AND es.show_contact_info = :show_contact_info";
            $params[':show_contact_info'] = (int)$filters['show_contact_info'];
        }

        if (isset($filters['featured_in_app']) && in_array((int)$filters['featured_in_app'], [0, 1], true)) {
            $sql .= " AND es.featured_in_app = :featured_in_app";
            $params[':featured_in_app'] = (int)$filters['featured_in_app'];
        }

        if (isset($filters['allow_multiple_payment_methods']) && in_array((int)$filters['allow_multiple_payment_methods'], [0, 1], true)) {
            $sql .= " AND es.allow_multiple_payment_methods = :allow_multiple_payment_methods";
            $params[':allow_multiple_payment_methods'] = (int)$filters['allow_multiple_payment_methods'];
        }

        if (isset($filters['min_order_amount']) && is_numeric($filters['min_order_amount'])) {
            $sql .= " AND es.min_order_amount >= :min_order_amount";
            $params[':min_order_amount'] = (float)$filters['min_order_amount'];
        }

        if (isset($filters['preparation_time_minutes']) && is_numeric($filters['preparation_time_minutes'])) {
            $sql .= " AND es.preparation_time_minutes = :preparation_time_minutes";
            $params[':preparation_time_minutes'] = (int)$filters['preparation_time_minutes'];
        }

        if (isset($filters['booking_window_days']) && is_numeric($filters['booking_window_days'])) {
            $sql .= " AND es.booking_window_days = :booking_window_days";
            $params[':booking_window_days'] = (int)$filters['booking_window_days'];
        }

        if (isset($filters['max_bookings_per_slot']) && is_numeric($filters['max_bookings_per_slot'])) {
            $sql .= " AND es.max_bookings_per_slot = :max_bookings_per_slot";
            $params[':max_bookings_per_slot'] = (int)$filters['max_bookings_per_slot'];
        }

        if (isset($filters['max_daily_orders']) && is_numeric($filters['max_daily_orders'])) {
            $sql .= " AND es.max_daily_orders = :max_daily_orders";
            $params[':max_daily_orders'] = (int)$filters['max_daily_orders'];
        }

        if (isset($filters['delivery_radius_km']) && is_numeric($filters['delivery_radius_km'])) {
            $sql .= " AND es.delivery_radius_km = :delivery_radius_km";
            $params[':delivery_radius_km'] = (int)$filters['delivery_radius_km'];
        }

        if (isset($filters['free_delivery_min_order']) && is_numeric($filters['free_delivery_min_order'])) {
            $sql .= " AND es.free_delivery_min_order = :free_delivery_min_order";
            $params[':free_delivery_min_order'] = (float)$filters['free_delivery_min_order'];
        }

        if (isset($filters['default_payment_method']) && is_string($filters['default_payment_method'])) {
            $sql .= " AND es.default_payment_method = :default_payment_method";
            $params[':default_payment_method'] = $filters['default_payment_method'];
        }

        // فلتر إضافي لاسم المتجر
        if (isset($filters['store_name']) && !empty($filters['store_name'])) {
            $sql .= " AND e.store_name LIKE :store_name";
            $params[":store_name"] = '%' . trim($filters['store_name']) . '%';
        }

        // فلتر إضافي لحالة الكيان
        if (isset($filters['status']) && in_array($filters['status'], ['pending', 'approved', 'suspended', 'rejected'])) {
            $sql .= " AND e.status = :status";
            $params[":status"] = $filters['status'];
        }

        // فلتر إضافي للبحث في الإعدادات الإضافية (JSON)
        if (isset($filters['additional_settings_search']) && !empty($filters['additional_settings_search'])) {
            $sql .= " AND es.additional_settings LIKE :additional_settings_search";
            $params[":additional_settings_search"] = '%' . $filters['additional_settings_search'] . '%';
        }

        // الفرز
        $orderBy = in_array($orderBy, self::ALLOWED_ORDER_BY, true) ? $orderBy : 'entity_id';
        $orderDir = strtoupper($orderDir) === 'ASC' ? 'ASC' : 'DESC';
        $sql .= " ORDER BY es.{$orderBy} {$orderDir}";

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
            FROM entity_settings es
            LEFT JOIN entities e ON es.entity_id = e.id
            WHERE 1=1
        ";
        $params = [];

        // تطبيق نفس الفلاتر الموجودة في دالة all
        if (isset($filters['entity_id']) && is_numeric($filters['entity_id'])) {
            $sql .= " AND es.entity_id = :entity_id";
            $params[':entity_id'] = (int)$filters['entity_id'];
        }

        if (isset($filters['auto_accept_orders']) && in_array((int)$filters['auto_accept_orders'], [0, 1], true)) {
            $sql .= " AND es.auto_accept_orders = :auto_accept_orders";
            $params[':auto_accept_orders'] = (int)$filters['auto_accept_orders'];
        }

        if (isset($filters['allow_cod']) && in_array((int)$filters['allow_cod'], [0, 1], true)) {
            $sql .= " AND es.allow_cod = :allow_cod";
            $params[':allow_cod'] = (int)$filters['allow_cod'];
        }

        if (isset($filters['is_visible']) && in_array((int)$filters['is_visible'], [0, 1], true)) {
            $sql .= " AND es.is_visible = :is_visible";
            $params[':is_visible'] = (int)$filters['is_visible'];
        }

        if (isset($filters['maintenance_mode']) && in_array((int)$filters['maintenance_mode'], [0, 1], true)) {
            $sql .= " AND es.maintenance_mode = :maintenance_mode";
            $params[':maintenance_mode'] = (int)$filters['maintenance_mode'];
        }

        if (isset($filters['min_order_amount']) && is_numeric($filters['min_order_amount'])) {
            $sql .= " AND es.min_order_amount >= :min_order_amount";
            $params[':min_order_amount'] = (float)$filters['min_order_amount'];
        }

        if (isset($filters['store_name']) && !empty($filters['store_name'])) {
            $sql .= " AND e.store_name LIKE :store_name";
            $params[":store_name"] = '%' . trim($filters['store_name']) . '%';
        }

        if (isset($filters['status']) && in_array($filters['status'], ['pending', 'approved', 'suspended', 'rejected'])) {
            $sql .= " AND e.status = :status";
            $params[":status"] = $filters['status'];
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    // ================================
    // Find by ID
    // ================================
    public function find(int $entityId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT es.*,
                   e.store_name,
                   e.status,
                   e.email
            FROM entity_settings es
            LEFT JOIN entities e ON es.entity_id = e.id
            WHERE es.entity_id = :entity_id
            LIMIT 1
        ");
        $stmt->execute([':entity_id' => $entityId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    // ================================
    // Create / Update
    // ================================

    // الأعمدة المسموحة في جدول entity_settings فقط
    private const ENTITY_SETTINGS_COLUMNS = [
        'auto_accept_orders', 'allow_cod', 'min_order_amount',
        'preparation_time_minutes', 'allow_online_booking', 'booking_window_days',
        'max_bookings_per_slot', 'booking_cancellation_allowed', 'allow_preorders',
        'max_daily_orders', 'is_visible', 'maintenance_mode', 'show_reviews',
        'show_contact_info', 'featured_in_app', 'default_payment_method',
        'allow_multiple_payment_methods', 'delivery_radius_km', 'free_delivery_min_order',
        'notification_preferences', 'additional_settings'
    ];

    public function save(int $entityId, array $data): bool
    {
        $isUpdate = $this->find($entityId) !== null;

        // استخراج الأعمدة المسموح بها فقط من البيانات الواردة
        $params = [':entity_id' => $entityId];
        $setClauses = [];
        $filteredCols = [];

        foreach (self::ENTITY_SETTINGS_COLUMNS as $col) {
            if (array_key_exists($col, $data)) {
                $val = $data[$col];
                // تحويل القيم الفارغة إلى null للأعمدة الاختيارية
                $params[':' . $col] = ($val === '' || $val === null) ? null : $val;
                $setClauses[] = "{$col} = :{$col}";
                $filteredCols[] = $col;
            }
        }

        if (empty($setClauses)) {
            throw new InvalidArgumentException("No valid fields provided for update");
        }

        if ($isUpdate) {
            $sql = "
                UPDATE entity_settings SET 
                    " . implode(', ', $setClauses) . ",
                    updated_at = CURRENT_TIMESTAMP
                WHERE entity_id = :entity_id
            ";
        } else {
            $sql = "
                INSERT INTO entity_settings (
                    entity_id, " . implode(', ', $filteredCols) . "
                ) VALUES (
                    :entity_id, :" . implode(', :', $filteredCols) . "
                )
            ";
        }

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }

    // ================================
    // Delete
    // ================================
    public function delete(int $entityId): bool
    {
        $stmt = $this->pdo->prepare(
            "DELETE FROM entity_settings WHERE entity_id = :entity_id"
        );
        return $stmt->execute([':entity_id' => $entityId]);
    }
}