<?php
// api/models/vendor_working_hours.php
// Model for vendor_working_hours table
// Provides static Vendor_working_hours proxy used by routes/controller.

declare(strict_types=1);

if (!class_exists('Vendor_working_hours')) {

class Vendor_working_hours
{
    private static function getDB()
    {
        // Try common DB providers
        if (function_exists('container')) {
            try {
                $db = container('db');
                if ($db instanceof mysqli) return $db;
            } catch (Throwable $e) {}
        }

        if (!empty($GLOBALS['CONTAINER']['db']) && $GLOBALS['CONTAINER']['db'] instanceof mysqli) {
            return $GLOBALS['CONTAINER']['db'];
        }

        foreach (['ADMIN_DB', 'db', 'mysqli', 'conn'] as $k) {
            if (!empty($GLOBALS[$k]) && $GLOBALS[$k] instanceof mysqli) {
                return $GLOBALS[$k];
            }
        }

        if (function_exists('connectDB')) {
            try {
                $maybe = @connectDB();
                if ($maybe instanceof mysqli) return $maybe;
            } catch (Throwable $e) {}
        }

        if (function_exists('get_db')) {
            try {
                $maybe = @get_db();
                if ($maybe instanceof mysqli) return $maybe;
            } catch (Throwable $e) {}
        }

        throw new Exception('Database connection not available');
    }

    /* -------------------------------------------------------
     | Get all working hours (with filters)
     * ----------------------------------------------------- */
    public static function all(array $opts = []): array
    {
        $db = self::getDB();
        $where = [];
        $params = [];
        $types  = '';

        // Filter by vendor
        if (!empty($opts['vendor_id'])) {
            $where[] = 'vwh.vendor_id = ?';
            $params[] = (int)$opts['vendor_id'];
            $types .= 'i';
        }

        // Filter by day of week
        if (isset($opts['day_of_week']) && $opts['day_of_week'] !== '') {
            $where[] = 'vwh.day_of_week = ?';
            $params[] = (int)$opts['day_of_week'];
            $types .= 'i';
        }

        $sql = "
            SELECT
                vwh.id,
                vwh.vendor_id,
                v.store_name AS vendor_name,
                vwh.day_of_week,
                vwh.open_time,
                vwh.close_time,
                vwh.is_closed
            FROM vendor_working_hours vwh
            LEFT JOIN vendors v ON v.id = vwh.vendor_id
        ";

        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' ORDER BY vwh.vendor_id ASC, vwh.day_of_week ASC';

        // Pagination
        if (!empty($opts['limit'])) {
            $limit  = (int)$opts['limit'];
            $offset = !empty($opts['offset']) ? (int)$opts['offset'] : 0;
            $sql .= " LIMIT $limit OFFSET $offset";
        }

        $stmt = $db->prepare($sql);
        if (!$stmt) {
            throw new Exception('DB prepare failed: ' . $db->error);
        }

        if ($params) {
            $bind = array_merge([$types], $params);
            $stmt->bind_param(...$bind);
        }

        $stmt->execute();
        $res  = $stmt->get_result();
        $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];

        if ($res) $res->free();
        $stmt->close();

        return $rows;
    }

    /* -------------------------------------------------------
     | Find by ID
     * ----------------------------------------------------- */
    public static function find(int $id): ?array
    {
        $db = self::getDB();

        $stmt = $db->prepare("
            SELECT
                vwh.id,
                vwh.vendor_id,
                v.store_name AS vendor_name,
                vwh.day_of_week,
                vwh.open_time,
                vwh.close_time,
                vwh.is_closed
            FROM vendor_working_hours vwh
            LEFT JOIN vendors v ON v.id = vwh.vendor_id
            WHERE vwh.id = ?
            LIMIT 1
        ");

        if (!$stmt) {
            throw new Exception('DB prepare failed: ' . $db->error);
        }

        $stmt->bind_param('i', $id);
        $stmt->execute();

        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;

        if ($res) $res->free();
        $stmt->close();

        return $row ?: null;
    }

    /* -------------------------------------------------------
     | Insert / Update
     * ----------------------------------------------------- */
    public static function save(array $data): int
    {
        $db = self::getDB();

        $id          = !empty($data['id']) ? (int)$data['id'] : 0;
        $vendor_id   = (int)($data['vendor_id'] ?? 0);
        $day         = (int)($data['day_of_week'] ?? 0);
        $open_time   = $data['open_time'] ?? null;
        $close_time  = $data['close_time'] ?? null;
        $is_closed   = !empty($data['is_closed']) ? 1 : 0;

        if ($id > 0) {
            $stmt = $db->prepare("
                UPDATE vendor_working_hours
                SET vendor_id = ?, day_of_week = ?, open_time = ?, close_time = ?, is_closed = ?
                WHERE id = ?
                LIMIT 1
            ");

            if (!$stmt) {
                throw new Exception('DB prepare failed (update): ' . $db->error);
            }

            $stmt->bind_param(
                'iissii',
                $vendor_id,
                $day,
                $open_time,
                $close_time,
                $is_closed,
                $id
            );

            $stmt->execute();
            $stmt->close();

            return $id;
        }

        $stmt = $db->prepare("
            INSERT INTO vendor_working_hours
            (vendor_id, day_of_week, open_time, close_time, is_closed)
            VALUES (?, ?, ?, ?, ?)
        ");

        if (!$stmt) {
            throw new Exception('DB prepare failed (insert): ' . $db->error);
        }

        $stmt->bind_param(
            'iissi',
            $vendor_id,
            $day,
            $open_time,
            $close_time,
            $is_closed
        );

        $stmt->execute();
        $newId = $stmt->insert_id;
        $stmt->close();

        return $newId;
    }

    /* -------------------------------------------------------
     | Delete
     * ----------------------------------------------------- */
    public static function delete(int $id): bool
    {
        $db = self::getDB();

        $stmt = $db->prepare("
            DELETE FROM vendor_working_hours
            WHERE id = ?
            LIMIT 1
        ");

        if (!$stmt) {
            throw new Exception('DB prepare failed (delete): ' . $db->error);
        }

        $stmt->bind_param('i', $id);
        $ok = $stmt->execute();
        $stmt->close();

        return $ok;
    }
}

} // end class_exists
