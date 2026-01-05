<?php
// api/models/Roles.php
// Model for roles table
declare(strict_types=1);

if (!class_exists('Roles')) {

class Roles
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

    public static function all(): array
    {
        $db = self::getDB();
        $stmt = $db->prepare("SELECT id, key_name, display_name FROM roles ORDER BY id ASC");
        if (!$stmt) throw new Exception('DB prepare failed: ' . $db->error);
        $stmt->execute();
        
        if (method_exists($stmt, 'get_result')) {
            $res = $stmt->get_result();
            $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
            if ($res) $res->free();
        } else {
            // Fallback for older PHP/MySQL
            $meta = $stmt->result_metadata();
            $rows = [];
            if ($meta) {
                $fields = [];
                $refs = [];
                $row = [];
                while ($f = $meta->fetch_field()) {
                    $fields[] = $f->name;
                    $row[$f->name] = null;
                    $refs[] = &$row[$f->name];
                }
                $meta->free();
                call_user_func_array([$stmt, 'bind_result'], $refs);
                while ($stmt->fetch()) {
                    $r = [];
                    foreach ($fields as $fn) $r[$fn] = $row[$fn];
                    $rows[] = $r;
                }
            }
        }
        
        $stmt->close();
        return $rows;
    }

    public static function find(int $id): ?array
    {
        $db = self::getDB();
        $stmt = $db->prepare("SELECT id, key_name, display_name FROM roles WHERE id = ? LIMIT 1");
        if (!$stmt) throw new Exception('DB prepare failed: ' . $db->error);
        $stmt->bind_param('i', $id);
        $stmt->execute();
        
        if (method_exists($stmt, 'get_result')) {
            $res = $stmt->get_result();
            $row = $res ? $res->fetch_assoc() : null;
            if ($res) $res->free();
        } else {
            $meta = $stmt->result_metadata();
            $row = null;
            if ($meta) {
                $fields = [];
                $refs = [];
                $r = [];
                while ($f = $meta->fetch_field()) {
                    $fields[] = $f->name;
                    $r[$f->name] = null;
                    $refs[] = &$r[$f->name];
                }
                $meta->free();
                call_user_func_array([$stmt, 'bind_result'], $refs);
                if ($stmt->fetch()) {
                    $row = [];
                    foreach ($fields as $fn) $row[$fn] = $r[$fn];
                }
            }
        }
        
        $stmt->close();
        return $row ?: null;
    }
}

} // end class_exists
