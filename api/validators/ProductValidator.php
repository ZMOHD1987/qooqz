<?php
// api/validators/ProductValidator.php - Created from scratch
class ProductValidator {
    public static function validateCreate($data) {
        $errors = [];
        if (empty($data["sku"])) $errors["sku"] = "SKU is required";
        if (empty($data["slug"])) $errors["slug"] = "Slug is required";
        return ["valid" => empty($errors), "errors" => $errors];
    }
    public static function validateUpdate($data) {
        return ["valid" => true, "errors" => []];
    }
}

