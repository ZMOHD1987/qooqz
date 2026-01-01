<?php
// htdocs/api/users/role_helper.php
// Helper functions to resolve and validate role_id from the roles table.
//
// Usage:
//   require_once __DIR__ . '/role_helper.php';
//   $role_id = resolve_role_id($mysqli, $requested_role); // $requested_role can be int or key_name string or null
//   $roles = get_roles($mysqli); // returns array of roles
//
// Notes:
// - Functions expect an active mysqli connection in $mysqli.
// - resolve_role_id() will try (in order): numeric id, key_name, default key_name, first available role.

function resolve_role_id($mysqli, $requested = null, $default_key = 'customer') {
    if (!($mysqli instanceof mysqli)) return null;

    // 1) If numeric id provided, validate it exists
    if ($requested !== null) {
        if (is_numeric($requested) && (int)$requested > 0) {
            $rid = (int)$requested;
            $st = $mysqli->prepare("SELECT id FROM roles WHERE id = ? LIMIT 1");
            if ($st) {
                $st->bind_param('i', $rid);
                $st->execute();
                $res = $st->get_result();
                $row = $res ? $res->fetch_assoc() : null;
                $st->close();
                if ($row) return (int)$row['id'];
            }
        } else {
            // treat requested as key_name
            $rkey = trim((string)$requested);
            if ($rkey !== '') {
                $st = $mysqli->prepare("SELECT id FROM roles WHERE key_name = ? LIMIT 1");
                if ($st) {
                    $st->bind_param('s', $rkey);
                    $st->execute();
                    $res = $st->get_result();
                    $row = $res ? $res->fetch_assoc() : null;
                    $st->close();
                    if ($row) return (int)$row['id'];
                }
            }
        }
    }

    // 2) Fallback to default role by key_name
    if ($default_key) {
        $st2 = $mysqli->prepare("SELECT id FROM roles WHERE key_name = ? LIMIT 1");
        if ($st2) {
            $st2->bind_param('s', $default_key);
            $st2->execute();
            $res2 = $st2->get_result();
            $row2 = $res2 ? $res2->fetch_assoc() : null;
            $st2->close();
            if ($row2) return (int)$row2['id'];
        }
    }

    // 3) Last resort: return first available role id
    $res3 = $mysqli->query("SELECT id FROM roles ORDER BY id ASC LIMIT 1");
    if ($res3) {
        $r3 = $res3->fetch_assoc();
        if ($r3) return (int)$r3['id'];
    }

    return null;
}

/**
 * get_roles
 * Returns an array of roles:
 *  [ ['id'=>1,'key_name'=>'admin','display_name'=>'Admin'], ... ]
 */
function get_roles($mysqli) {
    $out = [];
    if (!($mysqli instanceof mysqli)) return $out;
    $res = $mysqli->query("SELECT id, key_name, display_name FROM roles ORDER BY id ASC");
    if ($res) {
        while ($r = $res->fetch_assoc()) $out[] = $r;
    }
    return $out;
}

/**
 * role_exists
 * Check if a role exists by numeric id or by key_name.
 * Returns boolean.
 */
function role_exists($mysqli, $id_or_key) {
    if (!($mysqli instanceof mysqli)) return false;
    if (is_numeric($id_or_key) && (int)$id_or_key > 0) {
        $st = $mysqli->prepare("SELECT 1 FROM roles WHERE id = ? LIMIT 1");
        if ($st) {
            $rid = (int)$id_or_key;
            $st->bind_param('i', $rid);
            $st->execute();
            $res = $st->get_result();
            $found = $res && $res->fetch_assoc();
            $st->close();
            return (bool)$found;
        }
        return false;
    } else {
        $st = $mysqli->prepare("SELECT 1 FROM roles WHERE key_name = ? LIMIT 1");
        if ($st) {
            $key = trim((string)$id_or_key);
            $st->bind_param('s', $key);
            $st->execute();
            $res = $st->get_result();
            $found = $res && $res->fetch_assoc();
            $st->close();
            return (bool)$found;
        }
        return false;
    }
}
?>