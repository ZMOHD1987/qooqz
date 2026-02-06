<?php
// helpers/db_utils.php
// وظائف مساعدة لـ PDO: ربط معاملات ديناميكية وتنفيذ استعلامات آمنة

/**
 * ربط معاملات ديناميكية إلى PDOStatement
 * @param PDOStatement $stmt
 * @param array $params
 * @return void
 * @throws RuntimeException
 */
function pdo_bind_params(PDOStatement $stmt, array $params): void {
    if (empty($params)) return;
    
    foreach ($params as $key => $value) {
        // تحديد النوع تلقائيًا
        if (is_int($value)) {
            $type = PDO::PARAM_INT;
        } elseif (is_bool($value)) {
            $type = PDO::PARAM_BOOL;
            $value = $value ? 1 : 0; // تحويل bool إلى int للتوافق
        } elseif (is_null($value)) {
            $type = PDO::PARAM_NULL;
        } elseif (is_float($value)) {
            $type = PDO::PARAM_STR; // PDO لا يدعم PARAM_FLOAT مباشرة، استخدم STR
        } else {
            $type = PDO::PARAM_STR;
        }
        
        // ربط المعامل (دعم المفاتيح الرقمية أو النصية)
        $paramName = is_int($key) ? $key + 1 : $key; // للمفاتيح الرقمية، ابدأ من 1
        if (!$stmt->bindValue($paramName, $value, $type)) {
            throw new RuntimeException('bindValue failed for parameter: ' . $paramName);
        }
    }
}

/**
 * تنفيذ استعلام PDO آمن مع معاملات
 * @param PDO $pdo
 * @param string $query
 * @param array $params
 * @param int $fetchMode (PDO::FETCH_ASSOC افتراضيًا)
 * @return PDOStatement|false
 * @throws RuntimeException
 */
function pdo_execute_query(PDO $pdo, string $query, array $params = [], int $fetchMode = PDO::FETCH_ASSOC) {
    try {
        $stmt = $pdo->prepare($query);
        if (!$stmt) {
            throw new RuntimeException('Prepare failed: ' . implode(' ', $pdo->errorInfo()));
        }
        
        pdo_bind_params($stmt, $params);
        
        if (!$stmt->execute()) {
            throw new RuntimeException('Execute failed: ' . implode(' ', $stmt->errorInfo()));
        }
        
        $stmt->setFetchMode($fetchMode);
        return $stmt;
    } catch (PDOException $e) {
        throw new RuntimeException('PDO Query Error: ' . $e->getMessage());
    }
}

/**
 * جلب صف واحد من النتائج
 * @param PDO $pdo
 * @param string $query
 * @param array $params
 * @param int $fetchMode
 * @return mixed|null
 */
function pdo_fetch_one(PDO $pdo, string $query, array $params = [], int $fetchMode = PDO::FETCH_ASSOC) {
    $stmt = pdo_execute_query($pdo, $query, $params, $fetchMode);
    return $stmt->fetch() ?: null;
}

/**
 * جلب جميع النتائج
 * @param PDO $pdo
 * @param string $query
 * @param array $params
 * @param int $fetchMode
 * @return array
 */
function pdo_fetch_all(PDO $pdo, string $query, array $params = [], int $fetchMode = PDO::FETCH_ASSOC): array {
    $stmt = pdo_execute_query($pdo, $query, $params, $fetchMode);
    return $stmt->fetchAll();
}

/**
 * تنفيذ استعلام تحديث/إدراج/حذف
 * @param PDO $pdo
 * @param string $query
 * @param array $params
 * @return int عدد الصفوف المتأثرة
 */
function pdo_execute_update(PDO $pdo, string $query, array $params = []): int {
    $stmt = pdo_execute_query($pdo, $query, $params);
    return $stmt->rowCount();
}