<?php
// api/routes/product_meta.php
// Enhanced, defensive product metadata API.
// GET params: lang=ar

if (session_status() === PHP_SESSION_NONE) session_start();

// simple logger
function pm_log($msg) {
    @file_put_contents('/tmp/product_meta.log', date('c') . ' ' . $msg . PHP_EOL, FILE_APPEND);
}

try {
    $dbFile = __DIR__ . '/../config/db.php';
    if (!is_readable($dbFile)) {
        pm_log("DB config not found at {$dbFile}");
        http_response_code(500);
        echo json_encode(['success'=>false,'message'=>'DB config missing']);
        exit;
    }
    require_once $dbFile;
    if (!function_exists('connectDB')) {
        pm_log("connectDB() not found in db.php");
        http_response_code(500);
        echo json_encode(['success'=>false,'message'=>'DB helper missing']);
        exit;
    }
    $conn = connectDB();
    if (!($conn instanceof mysqli)) {
        pm_log("connectDB() did not return mysqli");
        http_response_code(500);
        echo json_encode(['success'=>false,'message'=>'DB connection failed']);
        exit;
    }

    // helpers (same as before)
    function table_exists(mysqli $conn, $table) {
        $t = $conn->real_escape_string($table);
        $res = $conn->query("SHOW TABLES LIKE '{$t}'");
        return ($res && $res->num_rows > 0);
    }
    function get_columns(mysqli $conn, $table) {
        $cols = [];
        $t = $conn->real_escape_string($table);
        $res = $conn->query("SHOW COLUMNS FROM `{$t}`");
        if ($res) while ($r = $res->fetch_assoc()) $cols[] = $r['Field'];
        return $cols;
    }
    function get_translations_map(mysqli $conn, $table, $foreign_field, $lang) {
        $out = [];
        if (!table_exists($conn,$table)) return $out;
        $stmt = $conn->prepare("SELECT * FROM {$table} WHERE language_code = ?");
        if (!$stmt) return $out;
        $stmt->bind_param('s',$lang);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            if (!isset($row[$foreign_field])) continue;
            $fid = $row[$foreign_field];
            $payload = $row;
            unset($payload['id']); unset($payload[$foreign_field]); unset($payload['language_code']);
            $out[$fid] = $payload;
        }
        $stmt->close();
        return $out;
    }

    // requested language
    $lang = isset($_GET['lang']) ? preg_replace('/[^a-z0-9_-]/i','',$_GET['lang']) : ($_SESSION['preferred_language'] ?? 'en');
    $lang = $lang ?: 'en';

    // 1) languages list
    $languages = [];
    if (table_exists($conn, 'languages')) {
        $stmt = $conn->prepare("SELECT code, name, direction FROM languages ORDER BY code");
        if ($stmt) { $stmt->execute(); $res = $stmt->get_result(); while ($r = $res->fetch_assoc()) $languages[] = $r; $stmt->close(); }
    }
    if (empty($languages)) {
        $langBase = __DIR__ . '/../../languages/admin';
        if (is_dir($langBase)) {
            foreach (glob($langBase . '/*.json') as $f) {
                $code = pathinfo($f, PATHINFO_FILENAME);
                $json = @json_decode(@file_get_contents($f), true) ?: [];
                $languages[] = ['code'=>$code,'name'=>$json['name'] ?? strtoupper($code),'direction'=>$json['direction'] ?? 'ltr'];
            }
        }
    }
    if (empty($languages)) $languages[] = ['code'=>'en','name'=>'English','direction'=>'ltr'];

    // 2) product types
    $product_types = [
        ['key'=>'simple','label'=>'Simple'],
        ['key'=>'variable','label'=>'Variable'],
        ['key'=>'digital','label'=>'Digital'],
        ['key'=>'bundle','label'=>'Bundle'],
    ];

    // 3) categories tree with translations
    $categories = [];
    if (table_exists($conn, 'categories')) {
        $cols = get_columns($conn, 'categories');
        $selectCols = [];
        foreach (['id','parent_id','slug','name','image_url','is_active','sort_order'] as $c) if (in_array($c,$cols)) $selectCols[] = "`{$c}`";
        if (!empty($selectCols)) {
            $sql = "SELECT " . implode(',', $selectCols) . " FROM categories ORDER BY sort_order ASC, name ASC";
            $res = $conn->query($sql);
            $flat = [];
            while ($r = $res->fetch_assoc()) {
                $r['children'] = [];
                $flat[$r['id']] = $r;
            }
            $catTrans = get_translations_map($conn, 'category_translations', 'category_id', $lang);
            foreach ($flat as $id => &$node) {
                if (isset($catTrans[$id])) {
                    $t = $catTrans[$id];
                    if (!empty($t['name'])) $node['name_translated'] = $t['name'];
                    if (!empty($t['slug'])) $node['slug_translated'] = $t['slug'];
                    if (!empty($t['description'])) $node['description_translated'] = $t['description'];
                } else {
                    $node['name_translated'] = $node['name'] ?? null;
                }
            }
            unset($node);
            foreach ($flat as $id => $node) {
                $pid = $node['parent_id'] ?? null;
                if ($pid && isset($flat[$pid])) {
                    $flat[$pid]['children'][] = &$flat[$id];
                }
            }
            foreach ($flat as $id => $node) if (empty($node['parent_id'])) $categories[] = $node;
        }
    }

    // 4) brands
    $brands = [];
    if (table_exists($conn, 'brands')) {
        $cols = get_columns($conn, 'brands');
        $selectCols = [];
        foreach (['id','slug','logo_url','banner_url','website_url','is_active','is_featured','sort_order'] as $c) if (in_array($c,$cols)) $selectCols[] = "`{$c}`";
        $sql = "SELECT " . implode(',', $selectCols) . " FROM brands ORDER BY sort_order ASC, id ASC";
        $res = $conn->query($sql);
        $flat = [];
        while ($r = $res->fetch_assoc()) $flat[$r['id']] = $r;
        $brandTrans = get_translations_map($conn, 'brand_translations', 'brand_id', $lang);
        foreach ($flat as $id => $b) {
            $b['name_translated'] = $brandTrans[$id]['name'] ?? ($b['slug'] ?? ('brand-'.$id));
            $brands[] = $b;
        }
    }

    // 5) attributes + values + translations
    $attributes = [];
    if (table_exists($conn, 'product_attributes')) {
        $attrCols = get_columns($conn, 'product_attributes');
        $selAttrCols = [];
        foreach (['id','slug','attribute_type','is_filterable','is_visible','is_required','is_variation','sort_order','is_global'] as $c) if (in_array($c,$attrCols)) $selAttrCols[] = "`{$c}`";
        $sql = "SELECT " . implode(',', $selAttrCols) . " FROM product_attributes ORDER BY sort_order ASC, id ASC";
        $res = $conn->query($sql);
        $attrs = [];
        while ($r = $res->fetch_assoc()) { $r['values'] = []; $attrs[$r['id']] = $r; }

        if (!empty($attrs) && table_exists($conn, 'product_attribute_values')) {
            $ids = implode(',', array_keys($attrs));
            $valCols = get_columns($conn, 'product_attribute_values');
            $selectValCols = [];
            foreach (['id','attribute_id','value','slug','color_code','image_url','sort_order','is_active'] as $c) if (in_array($c,$valCols)) $selectValCols[] = "`{$c}`";
            $rv = $conn->query("SELECT " . implode(',', $selectValCols) . " FROM product_attribute_values WHERE attribute_id IN ({$ids}) ORDER BY sort_order ASC, id ASC");
            while ($v = $rv->fetch_assoc()) {
                if (isset($attrs[$v['attribute_id']])) $attrs[$v['attribute_id']]['values'][] = $v;
            }
        }

        $attrTrans = get_translations_map($conn, 'product_attribute_translations', 'attribute_id', $lang);
        $valTrans = get_translations_map($conn, 'product_attribute_value_translations', 'attribute_value_id', $lang);

        foreach ($attrs as $aid => $a) {
            $aOut = [
                'id' => (int)$a['id'],
                'slug' => $a['slug'] ?? null,
                'attribute_type' => $a['attribute_type'] ?? 'text',
                'is_filterable' => (int)($a['is_filterable'] ?? 0),
                'is_visible' => (int)($a['is_visible'] ?? 1),
                'is_required' => (int)($a['is_required'] ?? 0),
                'is_variation' => (int)($a['is_variation'] ?? 0),
                'sort_order' => (int)($a['sort_order'] ?? 0),
                'is_global' => (int)($a['is_global'] ?? 1),
                'name' => $a['slug'] ?? ('attr-'.$a['id']),
                'name_translated' => $attrTrans[$aid]['name'] ?? ($a['slug'] ?? ('attr-'.$a['id'])),
                'values' => []
            ];
            foreach ($a['values'] as $v) {
                $vid = $v['id'];
                $vOut = [
                    'id' => (int)$v['id'],
                    'value' => $v['value'] ?? null,
                    'slug' => $v['slug'] ?? null,
                    'color_code' => $v['color_code'] ?? null,
                    'image_url' => $v['image_url'] ?? null,
                    'sort_order' => (int)($v['sort_order'] ?? 0),
                    'is_active' => (int)($v['is_active'] ?? 1),
                    'label_translated' => $valTrans[$vid]['label'] ?? ($v['value'] ?? null)
                ];
                $aOut['values'][] = $vOut;
            }
            $attributes[] = $aOut;
        }
    }

    $data = [
        'languages' => $languages,
        'product_types' => $product_types,
        'categories' => $categories,
        'brands' => $brands,
        'attributes' => $attributes
    ];

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success'=>true,'data'=>$data], JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);

} catch (Throwable $e) {
    pm_log("Unhandled exception in product_meta: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>'Internal server error']);
}