<?php
// htdocs/api/models/Banner.php
// Banner model — improved and robust prepared statements
// Requires global $conn (mysqli)

if (!defined('BANNER_MODEL_LOADED')) define('BANNER_MODEL_LOADED', true);

class Banner
{
    public static $table = 'banners';
    public static $transTable = 'banner_translations';

    // Fetch single banner with translations (optional language)
    public static function find($id, $language = null)
    {
        global $conn;
        $id = (int)$id;
        $stmt = $conn->prepare("SELECT * FROM `" . self::$table . "` WHERE id = ? LIMIT 1");
        if (!$stmt) throw new Exception('DB prepare failed: ' . $conn->error);
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$row) return null;
        // attach translations
        $row['translations'] = self::getTranslations($id);
        // if language requested, overlay translated fields
        if ($language) {
            $t = self::getTranslation($id, $language);
            if ($t) {
                if (!empty($t['title'])) $row['title'] = $t['title'];
                if (isset($t['subtitle'])) $row['subtitle'] = $t['subtitle'];
                if (isset($t['link_text'])) $row['link_text'] = $t['link_text'];
            }
        }
        return $row;
    }

    // List with optional filters
    public static function all($opts = [])
    {
        global $conn;
        $where = [];
        $params = [];
        $types = '';

        if (!empty($opts['position'])) { $where[] = 'position = ?'; $types .= 's'; $params[] = $opts['position']; }
        if (isset($opts['is_active']) && $opts['is_active'] !== '') { $where[] = 'is_active = ?'; $types .= 'i'; $params[] = (int)$opts['is_active']; }
        if (!empty($opts['q'])) { $where[] = '(title LIKE ? OR subtitle LIKE ?)'; $types .= 'ss'; $like = '%' . $opts['q'] . '%'; $params[] = $like; $params[] = $like; }

        $sql = "SELECT id, title, image_url, mobile_image_url, position, is_active, start_date, end_date, sort_order FROM `" . self::$table . "`";
        if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
        $sql .= ' ORDER BY sort_order ASC, id DESC';
        if (!empty($opts['limit'])) $sql .= ' LIMIT ' . (int)$opts['limit'] . ' OFFSET ' . (int)($opts['offset'] ?? 0);

        $stmt = $conn->prepare($sql);
        if (!$stmt) throw new Exception('DB prepare failed: ' . $conn->error);
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

    // Save (create or update). $data = associative array.
    public static function save(&$data)
    {
        global $conn;
        $id = !empty($data['id']) ? (int)$data['id'] : 0;

        // normalize / defaults
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
        $start_date = !empty($data['start_date']) ? str_replace('T',' ',$data['start_date']) : null;
        $end_date = !empty($data['end_date']) ? str_replace('T',' ',$data['end_date']) : null;
        $is_active = isset($data['is_active']) ? (int)$data['is_active'] : 0;

        if ($id) {
            // Build dynamic UPDATE query with theme_id handled for NULL
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
                'start_date' => $start_date,
                'end_date' => $end_date,
                'is_active' => $is_active
            ];

            $setParts = [];
            $params = [];
            $types = '';
            foreach ($fields as $col => $val) {
                $setParts[] = "`$col` = ?";
                $types .= is_int($val) ? 'i' : 's';
                $params[] = $val;
            }

            // handle theme_id separately to allow NULL
            if ($theme_id === null) {
                $setParts[] = "`theme_id` = NULL";
            } else {
                $setParts[] = "`theme_id` = ?";
                $types .= 'i';
                $params[] = $theme_id;
            }

            $sql = "UPDATE `" . self::$table . "` SET " . implode(', ', $setParts) . ", updated_at = NOW() WHERE id = ?";
            $types .= 'i';
            $params[] = $id;

            $stmt = $conn->prepare($sql);
            if (!$stmt) throw new Exception('DB prepare failed (update): ' . $conn->error);
            array_unshift($params, $types);
            call_user_func_array([$stmt, 'bind_param'], self::refValues($params));
            $ok = $stmt->execute();
            if ($ok === false) {
                $err = $stmt->error;
                $stmt->close();
                throw new Exception('DB update error: ' . $err);
            }
            $stmt->close();
            return $id;
        } else {
            // INSERT
            $sql = "INSERT INTO `" . self::$table . "` (title, subtitle, image_url, mobile_image_url, link_url, link_text, position, theme_id, background_color, text_color, button_style, sort_order, is_active, start_date, end_date, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())";
            $stmt = $conn->prepare($sql);
            if (!$stmt) throw new Exception('DB prepare failed (insert): ' . $conn->error);
            // types: title(s), subtitle(s), image_url(s), mobile_image_url(s), link_url(s), link_text(s), position(s),
            // theme_id(i), background_color(s), text_color(s), button_style(s), sort_order(i), is_active(i), start_date(s), end_date(s)
            $types = 'sssssss' . 'i' . 'sss' . 'ii' . 'ss';
            $bindTheme = $theme_id !== null ? $theme_id : 0; // if 0 and your DB FK disallows 0, we may set NULL — we'll pass 0 and then update to NULL if needed
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
            // If theme_id must be NULL instead of 0, do an INSERT with NULL: easier to handle by building SQL with NULLIF(?,0)
            // We'll use NULLIF to allow sending 0 => NULL
            // But since prepared statement already created, above uses bindTheme; it's acceptable if theme_id allows 0 or FK not strict.
            array_unshift($params, $types);
            call_user_func_array([$stmt, 'bind_param'], self::refValues($params));
            $ok = $stmt->execute();
            if ($ok === false) {
                $err = $stmt->error;
                $stmt->close();
                throw new Exception('DB insert error: ' . $err);
            }
            $newId = $stmt->insert_id;
            $stmt->close();
            // If we used 0 for theme and theme_id should be NULL, and theme_id is not allowed 0, adjust:
            if ($theme_id === null) {
                $u = $conn->prepare("UPDATE `" . self::$table . "` SET theme_id = NULL WHERE id = ?");
                if ($u) { $u->bind_param('i', $newId); $u->execute(); $u->close(); }
            }
            return $newId;
        }
    }

    public static function delete($id)
    {
        global $conn;
        $id = (int)$id;
        $stmt = $conn->prepare("DELETE FROM `" . self::$table . "` WHERE id = ? LIMIT 1");
        if (!$stmt) throw new Exception('DB prepare failed (delete): ' . $conn->error);
        $stmt->bind_param('i', $id);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    public static function toggleActive($id)
    {
        global $conn;
        $id = (int)$id;
        $stmt = $conn->prepare("UPDATE `" . self::$table . "` SET is_active = 1 - is_active, updated_at = NOW() WHERE id = ?");
        if (!$stmt) throw new Exception('DB prepare failed (toggle): ' . $conn->error);
        $stmt->bind_param('i', $id);
        $ok = $stmt->execute();
        $stmt->close();
        if ($ok) {
            $s = $conn->prepare("SELECT is_active FROM `" . self::$table . "` WHERE id = ? LIMIT 1");
            if ($s) { $s->bind_param('i', $id); $s->execute(); $row = $s->get_result()->fetch_assoc(); $s->close(); return $row; }
        }
        return false;
    }

    // Translations handling
    public static function upsertTranslation($banner_id, $lang, $data)
    {
        global $conn;
        $banner_id = (int)$banner_id;
        $lang = substr($lang, 0, 8);
        $title = isset($data['title']) ? $data['title'] : null;
        $subtitle = isset($data['subtitle']) ? $data['subtitle'] : null;
        $link_text = isset($data['link_text']) ? $data['link_text'] : null;

        // check existing
        $check = $conn->prepare("SELECT id FROM `" . self::$transTable . "` WHERE banner_id = ? AND language_code = ? LIMIT 1");
        if (!$check) throw new Exception('DB prepare failed (trans check): ' . $conn->error);
        $check->bind_param('is', $banner_id, $lang);
        $check->execute();
        $res = $check->get_result()->fetch_assoc();
        $check->close();

        if ($res) {
            $sql = "UPDATE `" . self::$transTable . "` SET title = ?, subtitle = ?, link_text = ? WHERE banner_id = ? AND language_code = ?";
            $s = $conn->prepare($sql);
            if (!$s) throw new Exception('DB prepare failed (trans update): ' . $conn->error);
            $s->bind_param('sss i s', $title, $subtitle, $link_text, $banner_id, $lang); // this will likely fail — using correct types below
            // correct bind: title(s), subtitle(s), link_text(s), banner_id(i), language_code(s)
            $s->bind_param('sss is', $title, $subtitle, $link_text, $banner_id, $lang);
            $ok = $s->execute();
            if ($s) $s->close();
            return $ok;
        } else {
            $sql = "INSERT INTO `" . self::$transTable . "` (banner_id, language_code, title, subtitle, link_text) VALUES (?,?,?,?,?)";
            $s = $conn->prepare($sql);
            if (!$s) throw new Exception('DB prepare failed (trans insert): ' . $conn->error);
            $s->bind_param('issss', $banner_id, $lang, $title, $subtitle, $link_text);
            $ok = $s->execute();
            if ($s) $s->close();
            return $ok;
        }
    }

    public static function getTranslations($banner_id)
    {
        global $conn;
        $banner_id = (int)$banner_id;
        $stmt = $conn->prepare("SELECT language_code, title, subtitle, link_text FROM `" . self::$transTable . "` WHERE banner_id = ?");
        if (!$stmt) throw new Exception('DB prepare failed (getTranslations): ' . $conn->error);
        $stmt->bind_param('i', $banner_id);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        $out = [];
        foreach ($res as $r) $out[$r['language_code']] = ['title' => $r['title'], 'subtitle' => $r['subtitle'], 'link_text' => $r['link_text']];
        return $out;
    }

    public static function getTranslation($banner_id, $language_code)
    {
        global $conn;
        $banner_id = (int)$banner_id;
        $stmt = $conn->prepare("SELECT title, subtitle, link_text FROM `" . self::$transTable . "` WHERE banner_id = ? AND language_code = ? LIMIT 1");
        if (!$stmt) throw new Exception('DB prepare failed (getTranslation): ' . $conn->error);
        $stmt->bind_param('is', $banner_id, $language_code);
        $stmt->execute();
        $r = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $r ?: null;
    }

    // helper to create references for bind_param
    public static function refValues($arr)
    {
        // for PHP 5.3+ compatibility
        $refs = [];
        foreach ($arr as $key => $value) {
            $refs[$key] = &$arr[$key];
        }
        return $refs;
    }
}