<?php
// api/models/Banner.php
// Banner model compatible with PHP 7.2+ (no typed properties)
// Supports multiple DB connection methods: $GLOBALS['conn'], $GLOBALS['mysqli'], container('db'), connectDB(), get_db()

if (!class_exists('Banner')) {

class Banner
{
    // Helper: get DB connection using multiple fallback methods
    private static function getDB()
    {
        // Try container() helper
        if (function_exists('container')) {
            $db = container('db');
            if ($db instanceof mysqli) return $db;
        }

        // Try $GLOBALS['CONTAINER']['db']
        if (isset($GLOBALS['CONTAINER']['db']) && $GLOBALS['CONTAINER']['db'] instanceof mysqli) {
            return $GLOBALS['CONTAINER']['db'];
        }

        // Try common global variables
        foreach (['conn', 'mysqli', 'db'] as $var) {
            if (isset($GLOBALS[$var]) && $GLOBALS[$var] instanceof mysqli) {
                return $GLOBALS[$var];
            }
        }

        // Try connectDB() function
        if (function_exists('connectDB')) {
            $db = connectDB();
            if ($db instanceof mysqli) return $db;
        }

        // Try get_db() function
        if (function_exists('get_db')) {
            $db = get_db();
            if ($db instanceof mysqli) return $db;
        }

        throw new Exception('Database connection not available');
    }

    // Helper for bind_param by reference (PHP 5.3+ compatibility)
    private static function refValues($arr)
    {
        $refs = [];
        foreach ($arr as $key => $value) {
            $refs[$key] = &$arr[$key];
        }
        return $refs;
    }

    /**
     * Get all banners with optional filters
     * @param array $opts Options: position, is_active, q (search), limit, offset
     * @return array
     */
    public static function all($opts = [])
    {
        $conn = self::getDB();
        $where = [];
        $params = [];
        $types = '';

        if (!empty($opts['position'])) {
            $where[] = 'position = ?';
            $types .= 's';
            $params[] = $opts['position'];
        }

        if (isset($opts['is_active']) && $opts['is_active'] !== '') {
            $where[] = 'is_active = ?';
            $types .= 'i';
            $params[] = (int)$opts['is_active'];
        }

        if (!empty($opts['q'])) {
            $where[] = '(title LIKE ? OR subtitle LIKE ?)';
            $types .= 'ss';
            $like = '%' . $opts['q'] . '%';
            $params[] = $like;
            $params[] = $like;
        }

        $sql = "SELECT id, title, subtitle, image_url, mobile_image_url, link_url, link_text, position, theme_id, background_color, text_color, button_style, sort_order, is_active, start_date, end_date, created_at, updated_at FROM banners";
        
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        
        $sql .= ' ORDER BY sort_order ASC, id DESC';
        
        if (!empty($opts['limit'])) {
            $sql .= ' LIMIT ' . (int)$opts['limit'];
            if (!empty($opts['offset'])) {
                $sql .= ' OFFSET ' . (int)$opts['offset'];
            }
        }

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception('DB prepare failed: ' . $conn->error);
        }

        if ($params) {
            array_unshift($params, $types);
            call_user_func_array([$stmt, 'bind_param'], self::refValues($params));
        }

        $stmt->execute();
        $res = $stmt->get_result();
        $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();

        return $rows;
    }

    /**
     * Find banner by ID with optional language overlay
     * @param int $id
     * @param string|null $lang Language code for translation overlay
     * @return array|null
     */
    public static function find($id, $lang = null)
    {
        $conn = self::getDB();
        $id = (int)$id;

        $stmt = $conn->prepare("SELECT * FROM banners WHERE id = ? LIMIT 1");
        if (!$stmt) {
            throw new Exception('DB prepare failed: ' . $conn->error);
        }

        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row) {
            return null;
        }

        // Attach translations
        $row['translations'] = self::getTranslations($id);

        // If language requested, overlay translated fields
        if ($lang && isset($row['translations'][$lang])) {
            $t = $row['translations'][$lang];
            if (!empty($t['title'])) $row['title'] = $t['title'];
            if (isset($t['subtitle'])) $row['subtitle'] = $t['subtitle'];
            if (isset($t['link_text'])) $row['link_text'] = $t['link_text'];
        }

        return $row;
    }

    /**
     * Save (create or update) banner
     * @param array $data Banner data with 'id' for update, without for insert
     * @return int Banner ID
     */
    public static function save($data)
    {
        $conn = self::getDB();
        $id = !empty($data['id']) ? (int)$data['id'] : 0;

        // Normalize data
        $title = isset($data['title']) ? $data['title'] : '';
        $subtitle = isset($data['subtitle']) ? $data['subtitle'] : null;
        $image_url = isset($data['image_url']) ? $data['image_url'] : '';
        $mobile_image_url = isset($data['mobile_image_url']) ? $data['mobile_image_url'] : null;
        $link_url = isset($data['link_url']) ? $data['link_url'] : null;
        $link_text = isset($data['link_text']) ? $data['link_text'] : null;
        $position = isset($data['position']) ? $data['position'] : null;
        $theme_id = isset($data['theme_id']) && $data['theme_id'] !== '' ? (int)$data['theme_id'] : null;
        $background_color = isset($data['background_color']) ? $data['background_color'] : '#FFFFFF';
        $text_color = isset($data['text_color']) ? $data['text_color'] : '#000000';
        $button_style = isset($data['button_style']) ? $data['button_style'] : null;
        $sort_order = isset($data['sort_order']) ? (int)$data['sort_order'] : 0;
        $is_active = isset($data['is_active']) ? (int)$data['is_active'] : 0;
        $start_date = !empty($data['start_date']) ? str_replace('T', ' ', $data['start_date']) : null;
        $end_date = !empty($data['end_date']) ? str_replace('T', ' ', $data['end_date']) : null;

        if ($id) {
            // UPDATE
            $fields = [
                'title' => $title,
                'subtitle' => $subtitle,
                'image_url' => $image_url,
                'mobile_image_url' => $mobile_image_url,
                'link_url' => $link_url,
                'link_text' => $link_text,
                'position' => $position,
                'background_color' => $background_color,
                'text_color' => $text_color,
                'button_style' => $button_style,
                'sort_order' => $sort_order,
                'is_active' => $is_active,
                'start_date' => $start_date,
                'end_date' => $end_date
            ];

            $setParts = [];
            $params = [];
            $types = '';

            foreach ($fields as $col => $val) {
                $setParts[] = "`$col` = ?";
                if (is_int($val)) {
                    $types .= 'i';
                } else {
                    $types .= 's';
                }
                $params[] = $val;
            }

            // Handle theme_id separately for NULL
            if ($theme_id === null) {
                $setParts[] = "`theme_id` = NULL";
            } else {
                $setParts[] = "`theme_id` = ?";
                $types .= 'i';
                $params[] = $theme_id;
            }

            $setParts[] = "`updated_at` = NOW()";

            $sql = "UPDATE banners SET " . implode(', ', $setParts) . " WHERE id = ?";
            $types .= 'i';
            $params[] = $id;

            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                throw new Exception('DB prepare failed (update): ' . $conn->error);
            }

            array_unshift($params, $types);
            call_user_func_array([$stmt, 'bind_param'], self::refValues($params));
            
            if (!$stmt->execute()) {
                $err = $stmt->error;
                $stmt->close();
                throw new Exception('DB update error: ' . $err);
            }
            $stmt->close();
            
            return $id;
        } else {
            // INSERT
            $sql = "INSERT INTO banners (title, subtitle, image_url, mobile_image_url, link_url, link_text, position, theme_id, background_color, text_color, button_style, sort_order, is_active, start_date, end_date, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())";
            
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                throw new Exception('DB prepare failed (insert): ' . $conn->error);
            }

            $types = 'sssssss'; // title, subtitle, image_url, mobile_image_url, link_url, link_text, position
            $types .= 'i';      // theme_id
            $types .= 'sss';    // background_color, text_color, button_style
            $types .= 'ii';     // sort_order, is_active
            $types .= 'ss';     // start_date, end_date

            $bindTheme = $theme_id !== null ? $theme_id : 0;
            
            $params = [
                $title,
                $subtitle,
                $image_url,
                $mobile_image_url,
                $link_url,
                $link_text,
                $position,
                $bindTheme,
                $background_color,
                $text_color,
                $button_style,
                $sort_order,
                $is_active,
                $start_date,
                $end_date
            ];

            array_unshift($params, $types);
            call_user_func_array([$stmt, 'bind_param'], self::refValues($params));
            
            if (!$stmt->execute()) {
                $err = $stmt->error;
                $stmt->close();
                throw new Exception('DB insert error: ' . $err);
            }

            $newId = $stmt->insert_id;
            $stmt->close();

            // If theme_id should be NULL, update it
            if ($theme_id === null) {
                $u = $conn->prepare("UPDATE banners SET theme_id = NULL WHERE id = ?");
                if ($u) {
                    $u->bind_param('i', $newId);
                    $u->execute();
                    $u->close();
                }
            }

            return $newId;
        }
    }

    /**
     * Delete banner by ID
     * @param int $id
     * @return bool
     */
    public static function delete($id)
    {
        $conn = self::getDB();
        $id = (int)$id;

        // Delete translations first
        $dt = $conn->prepare("DELETE FROM banner_translations WHERE banner_id = ?");
        if ($dt) {
            $dt->bind_param('i', $id);
            $dt->execute();
            $dt->close();
        }

        // Delete banner
        $stmt = $conn->prepare("DELETE FROM banners WHERE id = ? LIMIT 1");
        if (!$stmt) {
            throw new Exception('DB prepare failed (delete): ' . $conn->error);
        }

        $stmt->bind_param('i', $id);
        $ok = $stmt->execute();
        $stmt->close();

        return $ok;
    }

    /**
     * Toggle active status of banner
     * @param int $id
     * @return array|bool Updated banner row or false
     */
    public static function toggleActive($id)
    {
        $conn = self::getDB();
        $id = (int)$id;

        $stmt = $conn->prepare("UPDATE banners SET is_active = 1 - is_active, updated_at = NOW() WHERE id = ?");
        if (!$stmt) {
            throw new Exception('DB prepare failed (toggle): ' . $conn->error);
        }

        $stmt->bind_param('i', $id);
        $ok = $stmt->execute();
        $stmt->close();

        if ($ok) {
            $s = $conn->prepare("SELECT is_active FROM banners WHERE id = ? LIMIT 1");
            if ($s) {
                $s->bind_param('i', $id);
                $s->execute();
                $row = $s->get_result()->fetch_assoc();
                $s->close();
                return $row;
            }
        }

        return false;
    }

    /**
     * Upsert translation for banner
     * @param int $banner_id
     * @param string $language_code
     * @param array $data Translation data (title, subtitle, link_text)
     * @return bool
     */
    public static function upsertTranslation($banner_id, $language_code, $data)
    {
        $conn = self::getDB();
        $banner_id = (int)$banner_id;
        $language_code = substr($language_code, 0, 8);
        
        $title = isset($data['title']) ? $data['title'] : null;
        $subtitle = isset($data['subtitle']) ? $data['subtitle'] : null;
        $link_text = isset($data['link_text']) ? $data['link_text'] : null;

        // Check if exists
        $check = $conn->prepare("SELECT id FROM banner_translations WHERE banner_id = ? AND language_code = ? LIMIT 1");
        if (!$check) {
            throw new Exception('DB prepare failed (trans check): ' . $conn->error);
        }

        $check->bind_param('is', $banner_id, $language_code);
        $check->execute();
        $res = $check->get_result()->fetch_assoc();
        $check->close();

        if ($res) {
            // UPDATE
            $sql = "UPDATE banner_translations SET title = ?, subtitle = ?, link_text = ? WHERE banner_id = ? AND language_code = ?";
            $s = $conn->prepare($sql);
            if (!$s) {
                throw new Exception('DB prepare failed (trans update): ' . $conn->error);
            }
            $s->bind_param('sssis', $title, $subtitle, $link_text, $banner_id, $language_code);
            $ok = $s->execute();
            $s->close();
            return $ok;
        } else {
            // INSERT
            $sql = "INSERT INTO banner_translations (banner_id, language_code, title, subtitle, link_text) VALUES (?,?,?,?,?)";
            $s = $conn->prepare($sql);
            if (!$s) {
                throw new Exception('DB prepare failed (trans insert): ' . $conn->error);
            }
            $s->bind_param('issss', $banner_id, $language_code, $title, $subtitle, $link_text);
            $ok = $s->execute();
            $s->close();
            return $ok;
        }
    }

    /**
     * Get all translations for a banner
     * @param int $banner_id
     * @return array Associative array keyed by language_code
     */
    public static function getTranslations($banner_id)
    {
        $conn = self::getDB();
        $banner_id = (int)$banner_id;

        $stmt = $conn->prepare("SELECT language_code, title, subtitle, link_text FROM banner_translations WHERE banner_id = ?");
        if (!$stmt) {
            throw new Exception('DB prepare failed (getTranslations): ' . $conn->error);
        }

        $stmt->bind_param('i', $banner_id);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $out = [];
        foreach ($res as $r) {
            $out[$r['language_code']] = [
                'title' => $r['title'],
                'subtitle' => $r['subtitle'],
                'link_text' => $r['link_text']
            ];
        }

        return $out;
    }
}

} // end if !class_exists
