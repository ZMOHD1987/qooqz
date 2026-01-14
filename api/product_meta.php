<?php
// api/product_meta.php - Created from scratch
header("Content-Type: application/json; charset=utf-8");
require_once __DIR__ . "/config/db.php";
$conn = connectDB();
$data = ["categories" => [], "brands" => [], "product_types" => ["simple", "variable", "digital", "bundle"]];
echo json_encode(["success" => true, "data" => $data]);

