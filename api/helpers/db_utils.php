<?php
// helpers/db_utils.php
// وظائف مساعدة لربط معاملات mysqli الديناميكية

if (!function_exists('mysqli_bind_params')) {
    /**
     * Bind params dynamically to a mysqli_stmt
     * @param mysqli_stmt $stmt
     * @param array $params
     * @return void
     * @throws RuntimeException
     */
    function mysqli_bind_params(mysqli_stmt $stmt, array $params): void {
        if (empty($params)) return;
        $types = '';
        $refs = [];
        foreach ($params as $p) {
            if (is_int($p)) $types .= 'i';
            elseif (is_float($p)) $types .= 'd';
            elseif (is_null($p)) $types .= 's';
            elseif (is_bool($p)) { $types .= 'i'; $p = $p ? 1 : 0; }
            else $types .= 's';
            $refs[] = $p;
        }

        // bind_param requires references
        $bind_names = [];
        $bind_names[] = &$types;
        for ($i = 0; $i < count($refs); $i++) {
            $bind_names[] = &$refs[$i];
        }

        if (!@call_user_func_array([$stmt, 'bind_param'], $bind_names)) {
            throw new RuntimeException('bind_param failed: ' . $stmt->error);
        }
    }
}