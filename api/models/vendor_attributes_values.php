<?php
// api/models/vendor_attributes_values.php
// Model for vendor_attribute_values table
// Provides static Vendor_attributes_values proxy used by routes/controller.

declare(strict_types=1);

if (!class_exists('Vendor_attributes_values')) {

class Vendor_attributes_values
{
    private static function getDB()
    {
        // Try common DB providers
        if (function_exists('container')) {
            try { $db = container('db'); if ($db instanceof mysqli) return $db; } catch (Throwable $e) {}
        }
        if (!empty($GLOBALS['CONTAINER']['db']) && $GLOBALS['CONTAINER']['db'] instanceof mysqli) return $GLOBALS['CONTAINER']['db'];
        foreach (['ADMIN_DB', 'db', 'mysqli', 'conn'] as $k) {
            if (!empty($GLOBALS[$k]) && $GLOBALS[$k] instanceof mysqli) return $GLOBALS[$k];
        }
        if (function_exists('connectDB')) {
            try { $maybe = @connectDB(); if ($maybe instanceof mysqli) return $maybe; } catch (Throwable $e) {}
        }
        if (function_exists('get_db')) {
            try { $maybe = @get_db(); if ($maybe instanceof mysqli) return $maybe; } catch (Throwable $e) {}
        }
        throw new Exception('Database connection not available');
    }

    private static function refValues(array &$arr)
    {
        $refs = [];
        foreach ($arr as $k => $v) $refs[$k] = &$arr[$k];
        return $refs;
    }

    public static function all(array $opts = []): array
    {
        $db = self::getDB();
        $where = [];
        $params = [];
        $types = '';

        // فلترة حسب التاجر
        if (!empty($opts['vendor_id'])) {
            $where[] = 'vav.vendor_id = ?';
            $params[] = (int)$opts['vendor_id'];
            $types .= 'i';
        }
        
        // فلترة حسب الخاصية
        if (!empty($opts['attribute_id'])) {
            $where[] = 'vav.attribute_id = ?';
            $params[] = (int)$opts['attribute_id'];
            $types .= 'i';
        }

        // إضافة دعم البحث النصي (هذا ما كان ينقص الكود)
        if (!empty($opts['search'])) {
            $where[] = 'vav.value LIKE ?';
            $params[] = '%' . $opts['search'] . '%';
            $types .= 's';
        }

        $sql = "SELECT vav.id, vav.vendor_id, v.store_name AS vendor_name, vav.attribute_id, va.slug AS attribute_slug, vav.value
                FROM vendor_attribute_values vav
                LEFT JOIN vendors v ON v.id = vav.vendor_id
                LEFT JOIN vendor_attributes va ON va.id = vav.attribute_id";

        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        
        $sql .= ' ORDER BY vav.id DESC';

        // إضافة الـ Limit والـ Offset
        if (!empty($opts['limit'])) {
            $limit = (int)$opts['limit'];
            $offset = !empty($opts['offset']) ? (int)$opts['offset'] : 0;
            $sql .= " LIMIT $limit OFFSET $offset";
        }

        $stmt = $db->prepare($sql);
        if (!$stmt) throw new Exception('DB prepare failed: ' . $db->error);
        
        if ($params) {
            $bindParams = array_merge([$types], $params);
            $stmt->bind_param(...$bindParams);
        }
        
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
        if ($res) $res->free();
        $stmt->close();
        return $rows;
    }

    public static function find(int $id): ?array
    {
        $db = self::getDB();
        $stmt = $db->prepare("SELECT vav.id, vav.vendor_id, v.store_name AS vendor_name, vav.attribute_id, va.slug AS attribute_slug, vav.value
                              FROM vendor_attribute_values vav
                              LEFT JOIN vendors v ON v.id = vav.vendor_id
                              LEFT JOIN vendor_attributes va ON va.id = vav.attribute_id
                              WHERE vav.id = ? LIMIT 1");
        if (!$stmt) throw new Exception('DB prepare failed: ' . $db->error);
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        if ($res) $res->free();
        $stmt->close();
        return $row ?: null;
    }

    public static function save(array $data)
    {
        $db = self::getDB();
        $id = !empty($data['id']) ? (int)$data['id'] : 0;
        $vendor_id = (int)($data['vendor_id'] ?? 0);
        $attribute_id = (int)($data['attribute_id'] ?? 0);
        $value = $data['value'] ?? '';

        if ($id > 0) {
            $stmt = $db->prepare("UPDATE vendor_attribute_values SET vendor_id = ?, attribute_id = ?, value = ? WHERE id = ? LIMIT 1");
            if (!$stmt) throw new Exception('DB prepare failed (update): ' . $db->error);
            $stmt->bind_param('iisi', $vendor_id, $attribute_id, $value, $id);
            $stmt->execute();
            $stmt->close();
            return $id;
        } else {
            $stmt = $db->prepare("INSERT INTO vendor_attribute_values (vendor_id, attribute_id, value) VALUES (?, ?, ?)");
            if (!$stmt) throw new Exception('DB prepare failed (insert): ' . $db->error);
            $stmt->bind_param('iis', $vendor_id, $attribute_id, $value);
            $stmt->execute();
            $newId = $stmt->insert_id;
            $stmt->close();
            return $newId;
        }
    }

    public static function delete(int $id): bool
    {
        $db = self::getDB();
        $stmt = $db->prepare("DELETE FROM vendor_attribute_values WHERE id = ? LIMIT 1");
        if (!$stmt) throw new Exception('DB prepare failed (delete): ' . $db->error);
        $stmt->bind_param('i', $id);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }
}

} // end class_exists
