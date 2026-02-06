<?php
declare(strict_types=1);

/**
 * Route: /products or /products/{id}
 * يدعم GET, POST, PUT/PATCH, DELETE
 */

$db = $c['db'] ?? null;
$input = $c['input'] ?? [];
$id = $_SERVER['ROUTE_ID'] ?? null;

if (!$db) {
    errorResponse("Database connection missing");
}

// ===== GET =====
if ($c['method'] === 'GET') {
    try {
        if ($id !== null) {
            // عرض منتج واحد
            $stmt = $db->prepare("SELECT * FROM products WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            if (!$result) {
                errorResponse("Product not found", 404);
            }
            jsonResponse($result);
        } else {
            // عرض جميع المنتجات مع دعم فلترة بسيطة
            $sql = "SELECT * FROM products";
            $where = [];
            $params = [];
            $types = '';

            if (!empty($_GET['category_id'])) {
                $where[] = "category_id = ?";
                $params[] = (int)$_GET['category_id'];
                $types .= 'i';
            }

            if (!empty($_GET['status'])) {
                $where[] = "status = ?";
                $params[] = $_GET['status'];
                $types .= 's';
            }

            if ($where) {
                $sql .= " WHERE " . implode(" AND ", $where);
            }

            $stmt = $db->prepare($sql);
            if ($params) $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            jsonResponse($result);
        }
    } catch (Throwable $e) {
        errorResponse("DB Error: " . $e->getMessage());
    }
}

// ===== POST =====
if ($c['method'] === 'POST') {
    if (empty($input['name']) || empty($input['price'])) {
        errorResponse("Product name and price are required", 400);
    }
    try {
        $stmt = $db->prepare("INSERT INTO products (name, price, category_id, status, created_at) VALUES (?, ?, ?, ?, NOW())");
        $category_id = $input['category_id'] ?? null;
        $status = $input['status'] ?? 'active';
        $stmt->bind_param("sdss", $input['name'], $input['price'], $category_id, $status);
        $stmt->execute();
        jsonResponse(['id' => $stmt->insert_id, 'message' => 'Product created']);
    } catch (Throwable $e) {
        errorResponse("Insert failed: " . $e->getMessage());
    }
}

// ===== PUT / PATCH =====
if (in_array($c['method'], ['PUT', 'PATCH'], true)) {
    if (!$id) errorResponse("Product ID required", 400);
    if (empty($input)) errorResponse("No data to update", 400);

    try {
        $fields = [];
        $types = '';
        $values = [];

        foreach (['name', 'price', 'category_id', 'status'] as $col) {
            if (isset($input[$col])) {
                $fields[] = "$col = ?";
                $values[] = $input[$col];
                $types .= is_numeric($input[$col]) ? 'd' : 's';
            }
        }

        if (!$fields) errorResponse("No valid fields to update", 400);

        $sql = "UPDATE products SET " . implode(", ", $fields) . " WHERE id = ?";
        $stmt = $db->prepare($sql);
        $types .= 'i';
        $values[] = $id;
        $stmt->bind_param($types, ...$values);
        $stmt->execute();
        jsonResponse(['message' => 'Product updated']);
    } catch (Throwable $e) {
        errorResponse("Update failed: " . $e->getMessage());
    }
}

// ===== DELETE =====
if ($c['method'] === 'DELETE') {
    if (!$id) errorResponse("Product ID required", 400);
    try {
        $stmt = $db->prepare("DELETE FROM products WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        jsonResponse(['message' => 'Product deleted']);
    } catch (Throwable $e) {
        errorResponse("Delete failed: " . $e->getMessage());
    }
}

// ===== Fallback =====
errorResponse("Method not allowed", 405);
