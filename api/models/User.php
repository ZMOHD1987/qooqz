<?php
// htdocs/api/models/User.php
declare(strict_types=1);

class UserModel
{
    protected ?mysqli $db = null;

    public function __construct($db = null) {
        $this->initConnection($db);
    }

    private function initConnection($db): void {
        if ($db instanceof mysqli) { $this->db = $db; return; }
        foreach (['ADMIN_DB', 'db', 'conn'] as $g) {
            if (!empty($GLOBALS[$g]) && $GLOBALS[$g] instanceof mysqli) {
                $this->db = $GLOBALS[$g]; return;
            }
        }
        $cfgFile = __DIR__ . '/../config/db.php';
        if (is_readable($cfgFile)) {
            require_once $cfgFile;
            if (isset($conn)) $this->db = $conn;
        }
    }

    /**
     * جلب المستخدمين مع دعم الفلاتر الكاملة والربط مع الجداول الأخرى
     */
    public function all(array $opts = []): array
    {
        if (!$this->db) return [];

        $limit = (int)($opts['limit'] ?? 100);
        $where = ["1=1"];

        // 1. فلتر البحث النصي (اسم، إيميل، هاتف)
        if (!empty($opts['q'])) {
            $s = $this->db->real_escape_string((string)$opts['q']);
            $where[] = "(u.username LIKE '%$s%' OR u.email LIKE '%$s%' OR u.phone LIKE '%$s%')";
        }

        // 2. فلتر الدور
        if (isset($opts['role_id']) && $opts['role_id'] !== '' && $opts['role_id'] !== null) {
            $where[] = "u.role_id = " . (int)$opts['role_id'];
        }

        // 3. فلتر الحالة
        if (isset($opts['is_active']) && $opts['is_active'] !== '' && $opts['is_active'] !== null) {
            $where[] = "u.is_active = " . (int)$opts['is_active'];
        }

        // 4. فلتر الدولة
        if (isset($opts['country_id']) && $opts['country_id'] !== '' && $opts['country_id'] !== null) {
            $where[] = "u.country_id = " . (int)$opts['country_id'];
        }

        // 5. فلتر المدينة
        if (isset($opts['city_id']) && $opts['city_id'] !== '' && $opts['city_id'] !== null) {
            $where[] = "u.city_id = " . (int)$opts['city_id'];
        }

        // 6. فلتر اللغة
        if (!empty($opts['preferred_language'])) {
            $lang = $this->db->real_escape_string((string)$opts['preferred_language']);
            $where[] = "u.preferred_language = '$lang'";
        }

        // 7. فلتر المنطقة الزمنية
        if (!empty($opts['timezone'])) {
            $tz = $this->db->real_escape_string((string)$opts['timezone']);
            $where[] = "u.timezone = '$tz'";
        }

        $whereClause = implode(" AND ", $where);

        $sql = "SELECT 
                    u.*, 
                    r.display_name as role_name,
                    co.name as country_name,
                    ci.name as city_name
                FROM users u
                LEFT JOIN roles r ON u.role_id = r.id
                LEFT JOIN countries co ON u.country_id = co.id
                LEFT JOIN cities ci ON u.city_id = ci.id
                WHERE $whereClause
                ORDER BY u.id DESC 
                LIMIT $limit";

        $res = @$this->db->query($sql);

        // نظام الحماية في حال فشل الربط (Join)
        if (!$res) {
            $res = $this->db->query("SELECT * FROM users WHERE $whereClause ORDER BY id DESC LIMIT $limit");
        }

        return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    }

    public function find(int $id): ?array {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc() ?: null;
    }

    /**
     * إنشاء مستخدم جديد - تم إصلاح خطأ bind_param
     */
    public function create(array $data): ?int {
        $hash = password_hash($data['password'] ?? '', PASSWORD_DEFAULT);
        
        // إسناد القيم لمتغيرات صريحة لحل مشكلة Argument passed by reference
        $username   = (string)$data['username'];
        $email      = (string)$data['email'];
        $role_id    = isset($data['role_id']) ? (int)$data['role_id'] : null;
        $country_id = isset($data['country_id']) ? (int)$data['country_id'] : null;
        $city_id    = isset($data['city_id']) ? (int)$data['city_id'] : null;
        $is_active  = isset($data['is_active']) ? (int)$data['is_active'] : 1;
        $phone      = isset($data['phone']) ? (string)$data['phone'] : null;
        $language   = (string)($data['preferred_language'] ?? 'ar');
        $timezone   = (string)($data['timezone'] ?? 'Asia/Dubai');

        $sql = "INSERT INTO users (username, email, password_hash, role_id, country_id, city_id, is_active, phone, preferred_language, timezone) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $this->db->prepare($sql);
        if (!$stmt) return null;

        $stmt->bind_param("sssiisssss", 
            $username, $email, $hash, $role_id, $country_id, $city_id, 
            $is_active, $phone, $language, $timezone
        );

        return $stmt->execute() ? (int)$this->db->insert_id : null;
    }

    /**
     * تحديث مستخدم - يدعم التحديث الجزئي (Partial Update)
     */
    public function update(int $id, array $data): bool {
        if ($id <= 0) return false;
        
        $fields = []; $params = []; $types = "";
        $updatable = ['username', 'email', 'role_id', 'country_id', 'city_id', 'is_active', 'phone', 'preferred_language', 'timezone'];
        
        foreach ($updatable as $f) {
            if (isset($data[$f])) { 
                $fields[] = "$f = ?"; 
                $params[] = $data[$f]; 
                $types .= (is_int($data[$f]) || is_numeric($data[$f])) ? "i" : "s"; 
            }
        }

        if (!empty($data['password'])) { 
            $fields[] = "password_hash = ?"; 
            $params[] = password_hash($data['password'], PASSWORD_DEFAULT); 
            $types .= "s"; 
        }

        if (empty($fields)) return false;

        $sql = "UPDATE users SET " . implode(', ', $fields) . " WHERE id = ?";
        $params[] = $id; 
        $types .= "i";
        
        $stmt = $this->db->prepare($sql);
        if (!$stmt) return false;

        return $stmt->execute($params); // استخدام execute مباشرة للمصفوفات في PHP الحديثة
    }

    public function delete(int $id): bool {
        $stmt = $this->db->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param("i", $id);
        return $stmt->execute();
    }
}

/**
 * Static Wrapper لتسهيل الاستخدام
 */
class User {
    private static ?UserModel $inst = null;
    private static function get() { if (!self::$inst) self::$inst = new UserModel(); return self::$inst; }
    public static function all($o = []) { return self::get()->all($o); }
    public static function find($id) { return self::get()->find((int)$id); }
    public static function create($d) { return self::get()->create($d); }
    public static function update($id, $d) { return self::get()->update((int)$id, $d); }
    public static function delete($id) { return self::get()->delete((int)$id); }
}

// إنشاء اسم مستعار للتوافق مع الأكواد القديمة
if (!class_exists('Users')) {
    class_alias('User', 'Users');
}
