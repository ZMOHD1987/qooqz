<?php
// api/models/User.php
// User model — cleaned & compatible with older PHP

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/security.php';
require_once __DIR__ . '/../helpers/utils.php';

class User {

    private $mysqli;

    // public properties (no typed properties)
    public $id;
    public $username;
    public $email;
    public $phone;
    public $password;
    public $user_type;
    public $status;
    public $is_verified;
    public $email_verified_at;
    public $phone_verified_at;
    public $avatar;
    public $first_name;
    public $last_name;
    public $birth_date;
    public $gender;
    public $language;
    public $currency;
    public $timezone;
    public $last_activity;
    public $created_at;
    public $updated_at;

    public function __construct() {
        $this->mysqli = connectDB();
    }

    // portable bind helper (same pattern)
    private function bindParamsRef($stmt, $types, $params) {
        if (empty($types)) return;
        $refs = array();
        $refs[] = $types;
        for ($i = 0; $i < count($params); $i++) $refs[] = &$params[$i];
        call_user_func_array(array($stmt, 'bind_param'), $refs);
    }

    private function fetchOneAssoc($stmt) {
        if (method_exists($stmt, 'get_result')) {
            $res = $stmt->get_result();
            if ($res) {
                $row = $res->fetch_assoc();
                if (method_exists($res, 'free')) $res->free();
                return $row ?: null;
            }
            return null;
        }
        $meta = $stmt->result_metadata();
        if (!$meta) return null;
        $fields = array(); $row = array();
        while ($f = $meta->fetch_field()) { $row[$f->name] = null; $fields[] = &$row[$f->name]; }
        $meta->free();
        call_user_func_array(array($stmt, 'bind_result'), $fields);
        if ($stmt->fetch()) return $row;
        return null;
    }

    private function fetchAllAssoc($stmt) {
        $out = array();
        if (method_exists($stmt, 'get_result')) {
            $res = $stmt->get_result();
            if ($res) { while ($r = $res->fetch_assoc()) $out[] = $r; if (method_exists($res, 'free')) $res->free(); }
            return $out;
        }
        $meta = $stmt->result_metadata();
        if (!$meta) return $out;
        $fields = array(); $row = array();
        while ($f = $meta->fetch_field()) { $row[$f->name] = null; $fields[] = &$row[$f->name]; }
        $meta->free();
        call_user_func_array(array($stmt, 'bind_result'), $fields);
        while ($stmt->fetch()) { $r = array(); foreach ($row as $k => $v) $r[$k] = $v; $out[] = $r; }
        return $out;
    }

    // --- Create -----------------------------------------------------------

    public function create($data) {
        $hashedPassword = Security::hashPassword($data['password']);
        $sql = "INSERT INTO users (
                    username, email, phone, password, user_type, status, is_verified,
                    first_name, last_name, language, currency, timezone, created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
        $stmt = $this->mysqli->prepare($sql);
        if (!$stmt) {
            Utils::log("User create prepare failed: " . $this->mysqli->error, 'ERROR');
            return false;
        }

        $userType = isset($data['user_type']) ? $data['user_type'] : (defined('USER_TYPE_CUSTOMER') ? USER_TYPE_CUSTOMER : 'customer');
        $status = isset($data['status']) ? $data['status'] : (defined('USER_STATUS_PENDING') ? USER_STATUS_PENDING : 'pending');
        $isVerified = isset($data['is_verified']) ? (int)$data['is_verified'] : 0;
        $firstName = isset($data['first_name']) ? $data['first_name'] : null;
        $lastName = isset($data['last_name']) ? $data['last_name'] : null;
        $language = isset($data['language']) ? $data['language'] : (defined('DEFAULT_LANGUAGE') ? DEFAULT_LANGUAGE : 'en');
        $currency = isset($data['currency']) ? $data['currency'] : (defined('DEFAULT_CURRENCY') ? DEFAULT_CURRENCY : 'USD');
        $timezone = isset($data['timezone']) ? $data['timezone'] : (defined('DEFAULT_TIMEZONE') ? DEFAULT_TIMEZONE : 'UTC');

        $params = array(
            $data['username'],
            $data['email'],
            isset($data['phone']) ? $data['phone'] : null,
            $hashedPassword,
            $userType,
            $status,
            $isVerified,
            $firstName,
            $lastName,
            $language,
            $currency,
            $timezone
        );
        $types = 'ssssssis s s s'; // messy, rebuild properly:
        $types = '';
        $types .= 's'; // username
        $types .= 's'; // email
        $types .= 's'; // phone
        $types .= 's'; // password
        $types .= 's'; // user_type
        $types .= 's'; // status
        $types .= 'i'; // is_verified
        $types .= 's'; // first_name
        $types .= 's'; // last_name
        $types .= 's'; // language
        $types .= 's'; // currency
        $types .= 's'; // timezone

        $this->bindParamsRef($stmt, $types, $params);

        if ($stmt->execute()) {
            $userId = $stmt->insert_id;
            $stmt->close();
            Utils::log("User created: ID {$userId}, Email: " . (isset($data['email']) ? $data['email'] : ''), 'INFO');
            return $this->findById($userId);
        }
        $err = $stmt->error;
        $stmt->close();
        Utils::log("User create failed: {$err}", 'ERROR');
        return false;
    }

    // --- Read -------------------------------------------------------------

    public function findById($id) {
        $sql = "SELECT id, username, email, phone, user_type, status, is_verified, email_verified_at, phone_verified_at, avatar, first_name, last_name, birth_date, gender, language, currency, timezone, last_activity, created_at, updated_at FROM users WHERE id = ? LIMIT 1";
        $stmt = $this->mysqli->prepare($sql);
        if (!$stmt) return null;
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $this->fetchOneAssoc($stmt);
        $stmt->close();
        return $row ?: null;
    }

    public function findByEmail($email) {
        $sql = "SELECT * FROM users WHERE email = ? LIMIT 1";
        $stmt = $this->mysqli->prepare($sql);
        if (!$stmt) return null;
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $row = $this->fetchOneAssoc($stmt);
        $stmt->close();
        return $row ?: null;
    }

    public function findByPhone($phone) {
        $sql = "SELECT * FROM users WHERE phone = ? LIMIT 1";
        $stmt = $this->mysqli->prepare($sql);
        if (!$stmt) return null;
        $stmt->bind_param('s', $phone);
        $stmt->execute();
        $row = $this->fetchOneAssoc($stmt);
        $stmt->close();
        return $row ?: null;
    }

    public function findByUsername($username) {
        $sql = "SELECT * FROM users WHERE username = ? LIMIT 1";
        $stmt = $this->mysqli->prepare($sql);
        if (!$stmt) return null;
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $row = $this->fetchOneAssoc($stmt);
        $stmt->close();
        return $row ?: null;
    }

    public function findByIdentifier($identifier) {
        $sql = "SELECT * FROM users WHERE email = ? OR phone = ? OR username = ? LIMIT 1";
        $stmt = $this->mysqli->prepare($sql);
        if (!$stmt) return null;
        $stmt->bind_param('sss', $identifier, $identifier, $identifier);
        $stmt->execute();
        $row = $this->fetchOneAssoc($stmt);
        $stmt->close();
        return $row ?: null;
    }

    // getAll with portable binding
    public function getAll($filters = array(), $page = 1, $perPage = 20) {
        $where = array();
        $params = array();
        $types = '';

        if (isset($filters['user_type'])) {
            $where[] = "user_type = ?";
            $params[] = $filters['user_type'];
            $types .= 's';
        }
        if (isset($filters['status'])) {
            $where[] = "status = ?";
            $params[] = $filters['status'];
            $types .= 's';
        }
        if (isset($filters['is_verified'])) {
            $where[] = "is_verified = ?";
            $params[] = (int)$filters['is_verified'];
            $types .= 'i';
        }
        if (isset($filters['search'])) {
            $where[] = "(username LIKE ? OR email LIKE ? OR phone LIKE ? OR CONCAT(first_name, ' ', last_name) LIKE ?)";
            $st = '%' . $filters['search'] . '%';
            $params[] = $st; $params[] = $st; $params[] = $st; $params[] = $st;
            $types .= 'ssss';
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        // count
        $countSql = "SELECT COUNT(*) as total FROM users {$whereClause}";
        $stmt = $this->mysqli->prepare($countSql);
        if ($stmt) {
            if (!empty($params)) $this->bindParamsRef($stmt, $types, $params);
            $stmt->execute();
            $res = $stmt->get_result();
            $total = (int)($res->fetch_assoc()['total'] ?? 0);
            $stmt->close();
        } else {
            $total = 0;
        }

        // fetch rows
        $offset = ($page - 1) * $perPage;
        $sql = "SELECT id, username, email, phone, user_type, status, is_verified, avatar, first_name, last_name, created_at, updated_at, last_activity FROM users {$whereClause} ORDER BY created_at DESC LIMIT ? OFFSET ?";

        $paramsForQuery = $params;
        $typesForQuery = $types;
        $paramsForQuery[] = (int)$perPage;
        $paramsForQuery[] = (int)$offset;
        $typesForQuery .= 'ii';

        $stmt = $this->mysqli->prepare($sql);
        if ($stmt) {
            if (!empty($paramsForQuery)) $this->bindParamsRef($stmt, $typesForQuery, $paramsForQuery);
            $stmt->execute();
            $rows = $this->fetchAllAssoc($stmt);
            $stmt->close();
        } else {
            $rows = array();
        }

        return array(
            'data' => $rows,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => ($perPage > 0 ? ceil($total / $perPage) : 0)
        );
    }

    // --- Update ------------------------------------------------------------

    public function update($id, $data) {
        $allowedFields = array(
            'username' => 's', 'email' => 's', 'phone' => 's', 'first_name' => 's', 'last_name' => 's',
            'birth_date' => 's', 'gender' => 's', 'avatar' => 's', 'status' => 's', 'is_verified' => 'i',
            'language' => 's', 'currency' => 's', 'timezone' => 's'
        );

        $fields = array();
        $params = array();
        $types = '';

        foreach ($allowedFields as $field => $type) {
            if (isset($data[$field])) {
                $fields[] = "{$field} = ?";
                $params[] = $data[$field];
                $types .= $type;
            }
        }

        if (empty($fields)) return false;

        $fields[] = "updated_at = NOW()";
        $sql = "UPDATE users SET " . implode(', ', $fields) . " WHERE id = ?";
        $params[] = (int)$id;
        $types .= 'i';

        $stmt = $this->mysqli->prepare($sql);
        if (!$stmt) {
            Utils::log("User update prepare failed: " . $this->mysqli->error, 'ERROR');
            return false;
        }
        $this->bindParamsRef($stmt, $types, $params);
        $success = $stmt->execute();
        $stmt->close();
        if ($success) Utils::log("User updated: ID {$id}", 'INFO');
        return $success;
    }

    public function updatePassword($id, $newPassword) {
        $hashed = Security::hashPassword($newPassword);
        $sql = "UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?";
        $stmt = $this->mysqli->prepare($sql);
        if (!$stmt) return false;
        $stmt->bind_param('si', $hashed, $id);
        $ok = $stmt->execute();
        $stmt->close();
        if ($ok) {
            Utils::log("Password updated for user ID: {$id}", 'INFO');
            Security::logSecurityEvent('password_changed', "User ID: {$id}");
        }
        return $ok;
    }

    public function updateAvatar($id, $avatarUrl) {
        return $this->update($id, array('avatar' => $avatarUrl));
    }

    public function updateLastActivity($id) {
        $sql = "UPDATE users SET last_activity = NOW() WHERE id = ?";
        $stmt = $this->mysqli->prepare($sql);
        if (!$stmt) return false;
        $stmt->bind_param('i', $id);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    // --- Delete / verification / auth -------------------------------------

    public function softDelete($id) {
        return $this->update($id, array('status' => defined('USER_STATUS_DELETED') ? USER_STATUS_DELETED : 'deleted'));
    }

    public function delete($id) {
        $sql = "DELETE FROM users WHERE id = ?";
        $stmt = $this->mysqli->prepare($sql);
        if (!$stmt) return false;
        $stmt->bind_param('i', $id);
        $ok = $stmt->execute();
        $stmt->close();
        if ($ok) {
            Utils::log("User permanently deleted: ID {$id}", 'WARNING');
            Security::logSecurityEvent('user_deleted', "User ID: {$id}");
        }
        return $ok;
    }

    public function restore($id) {
        return $this->update($id, array('status' => defined('USER_STATUS_ACTIVE') ? USER_STATUS_ACTIVE : 'active'));
    }

    public function verifyEmail($id) {
        $sql = "UPDATE users SET is_verified = 1, email_verified_at = NOW(), status = ?, updated_at = NOW() WHERE id = ?";
        $stmt = $this->mysqli->prepare($sql);
        if (!$stmt) return false;
        $active = defined('USER_STATUS_ACTIVE') ? USER_STATUS_ACTIVE : 'active';
        $stmt->bind_param('si', $active, $id);
        $ok = $stmt->execute();
        $stmt->close();
        if ($ok) Utils::log("Email verified for user ID: {$id}", 'INFO');
        return $ok;
    }

    public function verifyPhone($id) {
        $sql = "UPDATE users SET phone_verified_at = NOW(), updated_at = NOW() WHERE id = ?";
        $stmt = $this->mysqli->prepare($sql);
        if (!$stmt) return false;
        $stmt->bind_param('i', $id);
        $ok = $stmt->execute();
        $stmt->close();
        if ($ok) Utils::log("Phone verified for user ID: {$id}", 'INFO');
        return $ok;
    }

    public function authenticate($identifier, $password) {
        $user = $this->findByIdentifier($identifier);
        if (!$user) return false;
        // Password column name may be 'password' or 'password_hash' — adapt:
        $hash = isset($user['password']) ? $user['password'] : (isset($user['password_hash']) ? $user['password_hash'] : null);
        if (!$hash) return false;
        if (!Security::verifyPassword($password, $hash)) return false;
        unset($user['password']); unset($user['password_hash']);
        return $user;
    }

    public function isEmailVerified($id) {
        $user = $this->findById($id);
        return ($user && !empty($user['is_verified']) && $user['email_verified_at'] !== null);
    }

    public function isPhoneVerified($id) {
        $user = $this->findById($id);
        return ($user && !empty($user['phone_verified_at']));
    }

    // --- Statistics / uniqueness helpers ----------------------------------

    public function countByType($type) {
        $sql = "SELECT COUNT(*) as count FROM users WHERE user_type = ?";
        $stmt = $this->mysqli->prepare($sql);
        if (!$stmt) return 0;
        $stmt->bind_param('s', $type);
        $stmt->execute();
        $row = $this->fetchOneAssoc($stmt);
        $stmt->close();
        return isset($row['count']) ? (int)$row['count'] : 0;
    }

    public function countByStatus($status) {
        $sql = "SELECT COUNT(*) as count FROM users WHERE status = ?";
        $stmt = $this->mysqli->prepare($sql);
        if (!$stmt) return 0;
        $stmt->bind_param('s', $status);
        $stmt->execute();
        $row = $this->fetchOneAssoc($stmt);
        $stmt->close();
        return isset($row['count']) ? (int)$row['count'] : 0;
    }

    public function countNewToday() {
        $sql = "SELECT COUNT(*) as count FROM users WHERE DATE(created_at) = CURDATE()";
        $res = $this->mysqli->query($sql);
        $row = $res ? $res->fetch_assoc() : ['count' => 0];
        return (int)$row['count'];
    }

    public function countActiveUsers() {
        $sql = "SELECT COUNT(*) as count FROM users WHERE last_activity >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
        $res = $this->mysqli->query($sql);
        $row = $res ? $res->fetch_assoc() : ['count' => 0];
        return (int)$row['count'];
    }

    public function getStatistics() {
        return array(
            'total_users' => $this->countByStatus(defined('USER_STATUS_ACTIVE') ? USER_STATUS_ACTIVE : 'active'),
            'customers' => $this->countByType(defined('USER_TYPE_CUSTOMER') ? USER_TYPE_CUSTOMER : 'customer'),
            'vendors' => $this->countByType(defined('USER_TYPE_VENDOR') ? USER_TYPE_VENDOR : 'vendor'),
            'admins' => $this->countByType(defined('USER_TYPE_ADMIN') ? USER_TYPE_ADMIN : 'admin'),
            'new_today' => $this->countNewToday(),
            'active_users' => $this->countActiveUsers(),
            'pending_verification' => $this->countByStatus(defined('USER_STATUS_PENDING') ? USER_STATUS_PENDING : 'pending'),
            'suspended' => $this->countByStatus(defined('USER_STATUS_SUSPENDED') ? USER_STATUS_SUSPENDED : 'suspended')
        );
    }

    public function isEmailUnique($email, $exceptId = null) {
        $sql = "SELECT COUNT(*) as count FROM users WHERE email = ?";
        if ($exceptId) $sql .= " AND id != ?";
        $stmt = $this->mysqli->prepare($sql);
        if (!$stmt) return false;
        if ($exceptId) {
            $stmt->bind_param('si', $email, $exceptId);
        } else {
            $stmt->bind_param('s', $email);
        }
        $stmt->execute();
        $row = $this->fetchOneAssoc($stmt);
        $stmt->close();
        return isset($row['count']) ? ($row['count'] == 0) : false;
    }

    public function isPhoneUnique($phone, $exceptId = null) {
        $sql = "SELECT COUNT(*) as count FROM users WHERE phone = ?";
        if ($exceptId) $sql .= " AND id != ?";
        $stmt = $this->mysqli->prepare($sql);
        if (!$stmt) return false;
        if ($exceptId) $stmt->bind_param('si', $phone, $exceptId); else $stmt->bind_param('s', $phone);
        $stmt->execute();
        $row = $this->fetchOneAssoc($stmt);
        $stmt->close();
        return isset($row['count']) ? ($row['count'] == 0) : false;
    }

    public function isUsernameUnique($username, $exceptId = null) {
        $sql = "SELECT COUNT(*) as count FROM users WHERE username = ?";
        if ($exceptId) $sql .= " AND id != ?";
        $stmt = $this->mysqli->prepare($sql);
        if (!$stmt) return false;
        if ($exceptId) $stmt->bind_param('si', $username, $exceptId); else $stmt->bind_param('s', $username);
        $stmt->execute();
        $row = $this->fetchOneAssoc($stmt);
        $stmt->close();
        return isset($row['count']) ? ($row['count'] == 0) : false;
    }

} // class User

?>