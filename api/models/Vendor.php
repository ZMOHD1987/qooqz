<?php
/**
 * api/models/Vendor.php
 * Unified VendorModel with compatibility alias Vendor
 * - Handles vendors table CRUD, translations, media uploads, and stats
 * - Separates allowed fields for normal users, admin-only fields and stat fields
 * - Uses transactions to ensure consistency
 *
 * Requirements:
 * - api/config/db.php -> connectDB()
 * - Optional: helpers/utils.php for logging utilities
 */

require_once __DIR__ . '/../config/db.php';
if (is_readable(__DIR__ . '/../helpers/utils.php')) require_once __DIR__ . '/../helpers/utils.php';

if (!function_exists('connectDB')) {
    throw new Exception('connectDB() not found in api/config/db.php');
}

class VendorModel
{
    public $conn;
    private $uploadDir;
    private $uploadUrlPrefix = '/uploads/vendors/';

    // Field groups
    private $allowedFields = [
        'parent_vendor_id','is_branch','branch_code','inherit_settings','inherit_products','inherit_commission',
        'user_id','store_name','vendor_type','slug','store_type','registration_number','tax_number',
        'phone','mobile','email','website_url','country_id','city_id','address','postal_code',
        'latitude','longitude','logo_url','cover_image_url','banner_url','commission_rate','service_radius',
        'accepts_online_booking','average_response_time'
    ];

    private $adminFields = [
        'status','suspension_reason','is_verified','is_featured','approved_at'
    ];

    private $statFields = [
        'rating_average','rating_count','total_sales','total_products','joined_at','created_at','updated_at'
    ];

    public function __construct(mysqli $conn = null)
    {
        if ($conn instanceof mysqli) $this->conn = $conn;
        else $this->conn = connectDB();

        if (!($this->conn instanceof mysqli)) {
            throw new Exception('Database connection failed in VendorModel');
        }

        $this->uploadDir = rtrim($_SERVER['DOCUMENT_ROOT'] ?? (__DIR__ . '/../../'), '/\\') . $this->uploadUrlPrefix;
        if (!is_dir($this->uploadDir)) @mkdir($this->uploadDir, 0755, true);
    }

    public function getConnection(): mysqli
    {
        return $this->conn;
    }

    private function log($msg)
    {
        if (class_exists('Utils') && method_exists('Utils', 'log')) return Utils::log($msg);
        error_log('[VendorModel] ' . $msg);
    }

    private function tableExists(string $name): bool
    {
        $r = $this->conn->query("SHOW TABLES LIKE '" . $this->conn->real_escape_string($name) . "'");
        return ($r && $r->num_rows > 0);
    }

    /**
     * Find vendor by ID
     * @param int $id
     * @param bool $withStats include stats summary
     * @return array|null
     */
    public function findById(int $id, bool $withStats = false)
    {
        $stmt = $this->conn->prepare("SELECT * FROM vendors WHERE id = ? LIMIT 1");
        if (!$stmt) return null;
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $v = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$v) return null;

        // Attach translations
        $v['translations'] = [];
        if ($this->tableExists('vendor_translations')) {
            $res = $this->conn->query("SELECT * FROM vendor_translations WHERE vendor_id = " . (int)$id);
            if ($res) {
                while ($r = $res->fetch_assoc()) {
                    $lang = $r['language_code'] ?? 'en';
                    unset($r['vendor_id'], $r['id']);
                    $v['translations'][$lang] = $r;
                }
            }
        }

        // Attach media
        $v['media'] = [];
        if ($this->tableExists('vendor_media')) {
            $res = $this->conn->query("SELECT * FROM vendor_media WHERE vendor_id = " . (int)$id . " ORDER BY is_primary DESC, sort_order ASC, id ASC");
            if ($res) $v['media'] = $res->fetch_all(MYSQLI_ASSOC);
        }

        if ($withStats) {
            $v['stats'] = $this->getStats($id);
        }

        return $v;
    }

    public function findBySlug(string $slug)
    {
        $slug = trim($slug);
        if ($slug === '') return null;
        $stmt = $this->conn->prepare("SELECT id FROM vendors WHERE slug = ? LIMIT 1");
        if (!$stmt) return null;
        $stmt->bind_param('s', $slug);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (empty($res['id'])) return null;
        return $this->findById((int)$res['id']);
    }

    public function findByEmail(string $email)
    {
        $email = trim($email);
        if ($email === '') return null;
        $stmt = $this->conn->prepare("SELECT * FROM vendors WHERE email = ? LIMIT 1");
        if (!$stmt) return null;
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $res ?: null;
    }

    public function findByUserId(int $userId)
    {
        $stmt = $this->conn->prepare("SELECT id FROM vendors WHERE user_id = ? LIMIT 1");
        if (!$stmt) return null;
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$res) return null;
        return $this->findById((int)$res['id']);
    }

    public function hasBranches(int $vendorId): bool
    {
        $stmt = $this->conn->prepare("SELECT COUNT(*) AS cnt FROM vendors WHERE parent_vendor_id = ?");
        if (!$stmt) return false;
        $stmt->bind_param('i', $vendorId);
        $stmt->execute();
        $cnt = (int)$stmt->get_result()->fetch_assoc()['cnt'];
        $stmt->close();
        return $cnt > 0;
    }

    /**
     * Get list with filters/pagination
     */
    public function getAll(array $filters = [], int $page = 1, int $per = 20)
    {
        $where = []; $params = []; $types = '';
        if (!empty($filters['status'])) { $where[] = "status = ?"; $params[] = $filters['status']; $types .= 's'; }
        if (!empty($filters['vendor_type'])) { $where[] = "vendor_type = ?"; $params[] = $filters['vendor_type']; $types .= 's'; }
        if (!empty($filters['is_branch'])) { $where[] = "is_branch = ?"; $params[] = (int)$filters['is_branch']; $types .= 'i'; }
        if (isset($filters['user_id'])) { $where[] = "user_id = ?"; $params[] = (int)$filters['user_id']; $types .= 'i'; }
        if (!empty($filters['search'])) {
            $where[] = "(store_name LIKE ? OR email LIKE ? OR slug LIKE ?)";
            $s = '%' . $filters['search'] . '%';
            $params[] = $s; $params[] = $s; $params[] = $s;
            $types .= 'sss';
        }

        $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

        // count
        $countSql = "SELECT COUNT(*) as total FROM vendors {$whereSql}";
        $stmt = $this->conn->prepare($countSql);
        if (!$stmt) return ['data'=>[], 'total'=>0, 'page'=>$page, 'per_page'=>$per];
        if (!empty($params)) $this->dynamicBind($stmt, $types, $params);
        $stmt->execute();
        $total = (int)$stmt->get_result()->fetch_assoc()['total'];
        $stmt->close();

        $offset = ($page - 1) * $per;
        $sql = "SELECT * FROM vendors {$whereSql} ORDER BY id DESC LIMIT ? OFFSET ?";
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) return ['data'=>[], 'total'=>$total, 'page'=>$page, 'per_page'=>$per];
        $typesWith = $types . 'ii';
        $paramsWith = $params; $paramsWith[] = (int)$per; $paramsWith[] = (int)$offset;
        $this->dynamicBind($stmt, $typesWith, $paramsWith);
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();

        return ['data'=>$rows, 'total'=>$total, 'page'=>$page, 'per_page'=>$per];
    }

    /**
     * Save vendor (create or update)
     * - Only allowedFields are persisted here.
     * - Admin-only and stat fields are intentionally excluded.
     * - Translations and file uploads handled here.
     */
    public function save(array $data, array $translations = [], array $files = [])
    {
        $id = !empty($data['id']) ? (int)$data['id'] : 0;

        // Build pairs from allowedFields only
        $pairs = [];
        foreach ($this->allowedFields as $f) {
            if (array_key_exists($f, $data)) $pairs[$f] = $data[$f];
        }

        if (empty($pairs['store_name'] ?? null)) { $this->log('store_name required'); return false; }

        $this->conn->begin_transaction();
        try {
            if ($id) {
                $sets = []; $params = []; $types = '';
                foreach ($pairs as $k=>$v) { $sets[] = "`$k` = ?"; $params[] = $v; $types .= $this->phpType($v); }
                $sql = "UPDATE vendors SET " . implode(', ', $sets) . ", updated_at = NOW() WHERE id = ?";
                $params[] = $id; $types .= 'i';
                $stmt = $this->conn->prepare($sql);
                if (!$stmt) { throw new RuntimeException("prepare update failed: " . $this->conn->error); }
                $this->dynamicBind($stmt, $types, $params);
                $ok = $stmt->execute();
                $stmt->close();
            } else {
                $cols = array_keys($pairs);
                $placeholders = implode(',', array_fill(0, count($cols), '?'));
                $sql = "INSERT INTO vendors (" . implode(',', $cols) . ", created_at, updated_at) VALUES ({$placeholders}, NOW(), NOW())";
                $stmt = $this->conn->prepare($sql);
                if (!$stmt) { throw new RuntimeException("prepare insert failed: " . $this->conn->error); }
                $types = ''; $params = [];
                foreach ($pairs as $v) { $params[] = $v; $types .= $this->phpType($v); }
                $this->dynamicBind($stmt, $types, $params);
                $ok = $stmt->execute();
                $id = (int)$this->conn->insert_id;
                $stmt->close();
            }

            if (!$ok) throw new RuntimeException('Save query failed: ' . $this->conn->error);

            // translations
            if (!empty($translations)) $this->saveTranslations($id, $translations);

            // handle file uploads (prefer $files param else $_FILES)
            $uploads = $files ?: ($_FILES ?? []);
            if (!empty($uploads)) {
                if (!empty($uploads['logo'])) $this->handleSingleUpload($id, $uploads['logo'], 'logo_url');
                if (!empty($uploads['cover'])) $this->handleSingleUpload($id, $uploads['cover'], 'cover_image_url');
                if (!empty($uploads['banner'])) $this->handleSingleUpload($id, $uploads['banner'], 'banner_url');
                if (!empty($uploads['images'])) $this->handleGalleryUpload($id, $uploads['images']);
            }

            $this->conn->commit();
            return $id;
        } catch (Throwable $e) {
            $this->conn->rollback();
            $this->log('save failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Update admin-only fields (must be called after permission check)
     */
    public function updateAdminFields(int $vendorId, array $fields)
    {
        $sets = []; $params = []; $types = '';
        foreach ($fields as $k => $v) {
            if (!in_array($k, $this->adminFields, true)) continue;
            $sets[] = "`$k` = ?";
            $params[] = $v;
            $types .= $this->phpType($v);
        }
        if (empty($sets)) return false;
        $sql = "UPDATE vendors SET " . implode(', ', $sets) . ", updated_at = NOW() WHERE id = ?";
        $params[] = $vendorId; $types .= 'i';
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) { $this->log("prepare updateAdminFields failed: " . $this->conn->error); return false; }
        $this->dynamicBind($stmt, $types, $params);
        $ok = $stmt->execute();
        $stmt->close();
        return (bool)$ok;
    }

    public function delete(int $id)
    {
        $stmt = $this->conn->prepare("DELETE FROM vendors WHERE id = ? LIMIT 1");
        if (!$stmt) return false;
        $stmt->bind_param('i', $id);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    public function setVerified(int $id, int $value = 1)
    {
        $stmt = $this->conn->prepare("UPDATE vendors SET is_verified = ?, approved_at = ".($value ? "NOW()" : "NULL")." WHERE id = ?");
        if (!$stmt) return false;
        $stmt->bind_param('ii', $value, $id);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    public function deleteImage(int $vendorId, string $imageUrl)
    {
        $vendor = $this->findById($vendorId);
        if (!$vendor) return false;
        $fields = ['logo_url','cover_image_url','banner_url'];
        foreach ($fields as $f) {
            if (!empty($vendor[$f]) && $vendor[$f] === $imageUrl) {
                $stmt = $this->conn->prepare("UPDATE vendors SET {$f} = NULL WHERE id = ?");
                if (!$stmt) return false;
                $stmt->bind_param('i', $vendorId);
                $stmt->execute();
                $stmt->close();
                $path = $_SERVER['DOCUMENT_ROOT'] . $imageUrl;
                if (is_file($path)) @unlink($path);
                return true;
            }
        }
        // vendor_media deletion
        if ($this->tableExists('vendor_media')) {
            $stmt = $this->conn->prepare("DELETE FROM vendor_media WHERE vendor_id = ? AND file_url = ? LIMIT 1");
            if ($stmt) {
                $stmt->bind_param('is', $vendorId, $imageUrl);
                $ok = $stmt->execute();
                $stmt->close();
                return (bool)$ok;
            }
        }
        return false;
    }

    private function saveTranslations(int $vendorId, array $translations)
    {
        if (!$this->tableExists('vendor_translations')) return true;
        $this->conn->query("DELETE FROM vendor_translations WHERE vendor_id = " . (int)$vendorId);
        $vals = [];
        foreach ($translations as $lang => $tr) {
            $langE = $this->conn->real_escape_string($lang);
            $desc = $this->conn->real_escape_string($tr['description'] ?? '');
            $rp = $this->conn->real_escape_string($tr['return_policy'] ?? '');
            $sp = $this->conn->real_escape_string($tr['shipping_policy'] ?? '');
            $mt = $this->conn->real_escape_string($tr['meta_title'] ?? '');
            $md = $this->conn->real_escape_string($tr['meta_description'] ?? '');
            $vals[] = "(" . (int)$vendorId . ",'{$langE}','{$desc}','{$rp}','{$sp}','{$mt}','{$md}')";
        }
        if ($vals) {
            $sql = "INSERT INTO vendor_translations (vendor_id, language_code, description, return_policy, shipping_policy, meta_title, meta_description) VALUES " . implode(',', $vals);
            if (!$this->conn->query($sql)) $this->log("insert translations failed: " . $this->conn->error);
        }
        return true;
    }

    private function handleSingleUpload(int $vendorId, array $file, string $fieldName)
    {
        if (empty($file) || ($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) return false;
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $fn = 'v' . $vendorId . '_' . $fieldName . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $dest = $this->uploadDir . $fn;
        if (!move_uploaded_file($file['tmp_name'], $dest)) return false;
        $this->compressImage($dest, $dest, 82);
        $url = $this->uploadUrlPrefix . $fn;
        $stmt = $this->conn->prepare("UPDATE vendors SET {$fieldName} = ? WHERE id = ?");
        if (!$stmt) return false;
        $stmt->bind_param('si', $url, $vendorId);
        $stmt->execute();
        $stmt->close();
        return true;
    }

    private function handleGalleryUpload(int $vendorId, array $files)
    {
        if (!$this->tableExists('vendor_media')) return false;
        $names = is_array($files['name']) ? $files['name'] : [$files['name']];
        $tmps = is_array($files['tmp_name']) ? $files['tmp_name'] : [$files['tmp_name']];
        $types = is_array($files['type']) ? $files['type'] : [$files['type']];
        $sizes = is_array($files['size']) ? $files['size'] : [$files['size']];

        for ($i = 0; $i < count($names); $i++) {
            if (empty($tmps[$i])) continue;
            $ext = strtolower(pathinfo($names[$i], PATHINFO_EXTENSION));
            $fn = 'v' . $vendorId . '_gallery_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            $dest = $this->uploadDir . $fn;
            if (!move_uploaded_file($tmps[$i], $dest)) continue;
            $this->compressImage($dest, $dest, 82);
            $url = $this->uploadUrlPrefix . $fn;
            $mime = $types[$i] ?? 'application/octet-stream';
            $size = (int)$sizes[$i];
            $is_primary = 0; $sort = 0;
            $stmt = $this->conn->prepare("INSERT INTO vendor_media (vendor_id, media_type, file_url, thumbnail_url, file_size, mime_type, is_primary, sort_order, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            if (!$stmt) continue;
            $thumb = $url;
            $stmt->bind_param('isssissi', $vendorId, $mime, $url, $thumb, $size, $mime, $is_primary, $sort);
            $stmt->execute();
            $stmt->close();
        }
        return true;
    }

    private function compressImage(string $src, string $dest, int $quality = 82)
    {
        $info = @getimagesize($src);
        if (!$info) return false;
        $mime = $info['mime'];
        if ($mime === 'image/jpeg') $img = @imagecreatefromjpeg($src);
        elseif ($mime === 'image/png') $img = @imagecreatefrompng($src);
        elseif ($mime === 'image/gif') $img = @imagecreatefromgif($src);
        else return false;

        $maxW = 2000; $maxH = 2000;
        $w = imagesx($img); $h = imagesy($img);
        $ratio = min(1, $maxW / $w, $maxH / $h);
        if ($ratio < 1) {
            $nw = (int)($w * $ratio); $nh = (int)($h * $ratio);
            $tmp = imagecreatetruecolor($nw, $nh);
            imagecopyresampled($tmp, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
            imagedestroy($img);
            $img = $tmp;
        }

        imagejpeg($img, $dest, $quality);
        imagedestroy($img);
        return true;
    }

    private function dynamicBind(mysqli_stmt $stmt, $types, array $params)
    {
        if ($types === '') return true;
        $refs = []; $refs[] = $types;
        for ($i = 0; $i < count($params); $i++) $refs[] = &$params[$i];
        return call_user_func_array([$stmt,'bind_param'],$refs);
    }

    private function phpType($v)
    {
        if (is_int($v)) return 'i';
        if (is_float($v) || is_double($v)) return 'd';
        return 's';
    }

    /**
     * Stats summary including balance
     */
    public function getStats(int $vendorId): array
    {
        $stmt = $this->conn->prepare("SELECT rating_average, rating_count, total_sales, total_products, joined_at FROM vendors WHERE id = ? LIMIT 1");
        $stmt->bind_param('i', $vendorId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc() ?: [];
        $stmt->close();

        $balance = $this->calculateBalance($vendorId);

        return array_merge($row, ['balance' => $balance]);
    }

    /**
     * Calculate vendor balance (simple)
     */
    public function calculateBalance(int $vendorId)
    {
        $vendorId = (int)$vendorId;
        $sql = "SELECT COALESCE(SUM(total_amount),0) AS total_revenue, COALESCE(SUM(commission_amount),0) AS total_commission FROM vendor_orders WHERE vendor_id = ?";
        $stmt = $this->conn->prepare($sql);
        $res = ['total_revenue'=>0,'total_commission'=>0];
        if ($stmt) {
            $stmt->bind_param('i', $vendorId);
            $stmt->execute();
            $res = $stmt->get_result()->fetch_assoc() ?: $res;
            $stmt->close();
        }
        $stmt = $this->conn->prepare("SELECT COALESCE(SUM(payout_amount),0) AS total_paid FROM vendor_payouts WHERE vendor_id = ? AND status = 'completed'");
        $paid = 0;
        if ($stmt) {
            $stmt->bind_param('i', $vendorId);
            $stmt->execute();
            $paid = (float)$stmt->get_result()->fetch_assoc()['total_paid'];
            $stmt->close();
        }
        $gross = (float)($res['total_revenue'] ?? 0);
        $commission = (float)($res['total_commission'] ?? 0);
        $available = max(0, $gross - $commission - $paid);
        return [
            'pending_amount' => 0,
            'total_revenue' => $gross,
            'total_commission' => $commission,
            'total_paid' => $paid,
            'available_for_payout' => $available
        ];
    }
}

// Compatibility alias
if (!class_exists('Vendor') && class_exists('VendorModel')) {
    class Vendor extends VendorModel {}
}