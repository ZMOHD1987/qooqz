<?php
// htdocs/api/validators/ProductValidator.php
// Validation helpers for product create/update endpoints.
// Returns ['valid'=>bool,'errors'=>[],'data'=>sanitized_data]

class ProductValidator
{
    protected static function sanitizeString($v)
    {
        return trim(filter_var($v ?? '', FILTER_SANITIZE_STRING));
    }

    protected static function sanitizeFloat($v)
    {
        return is_numeric($v) ? (float)$v : null;
    }

    protected static function sanitizeInt($v)
    {
        return is_numeric($v) ? (int)$v : null;
    }

    public static function validateCreate(array $input)
    {
        $errors = [];
        $data = [];

        $title = self::sanitizeString($input['title'] ?? $input['name'] ?? '');
        if ($title === '') $errors['title'] = 'Product title is required';
        $data['title'] = $title;

        $price = $input['price'] ?? null;
        if ($price === null || !is_numeric($price) || (float)$price < 0) {
            $errors['price'] = 'Valid price is required';
        } else {
            $data['price'] = (float)$price;
        }

        $data['sku'] = self::sanitizeString($input['sku'] ?? '');
        $data['description'] = trim($input['description'] ?? '');
        $data['short_description'] = trim($input['short_description'] ?? '');

        $manageStock = isset($input['manage_stock']) ? (bool)$input['manage_stock'] : false;
        $data['manage_stock'] = $manageStock;
        $stockQty = isset($input['stock_quantity']) ? self::sanitizeInt($input['stock_quantity']) : 0;
        if ($manageStock && ($stockQty === null || $stockQty < 0)) {
            $errors['stock_quantity'] = 'Stock quantity must be a non-negative integer when manage_stock is enabled';
        } else {
            $data['stock_quantity'] = $stockQty ?? 0;
        }

        $data['categories'] = [];
        if (!empty($input['categories']) && is_array($input['categories'])) {
            foreach ($input['categories'] as $c) {
                if (is_numeric($c)) $data['categories'][] = (int)$c;
            }
        }

        $data['vendor_id'] = isset($input['vendor_id']) ? self::sanitizeInt($input['vendor_id']) : null;
        $data['status'] = $input['status'] ?? 'draft';
        $data['weight'] = isset($input['weight']) ? self::sanitizeFloat($input['weight']) : 0.0;
        $data['dimensions'] = $input['dimensions'] ?? null; // array or string, pass-through

        // images/files may be handled separately by upload endpoint; accept image ids or urls
        if (!empty($input['images']) && is_array($input['images'])) $data['images'] = $input['images'];

        return ['valid' => empty($errors), 'errors' => $errors, 'data' => $data];
    }

    public static function validateUpdate(array $input)
    {
        // For update allow partial data; reuse create validation but do not require required fields
        $errors = [];
        $data = [];

        if (isset($input['title']) || isset($input['name'])) {
            $data['title'] = self::sanitizeString($input['title'] ?? $input['name']);
            if ($data['title'] === '') $errors['title'] = 'Title cannot be empty';
        }

        if (isset($input['price'])) {
            if (!is_numeric($input['price']) || (float)$input['price'] < 0) $errors['price'] = 'Invalid price';
            else $data['price'] = (float)$input['price'];
        }

        if (isset($input['sku'])) $data['sku'] = self::sanitizeString($input['sku']);
        if (isset($input['description'])) $data['description'] = trim($input['description']);
        if (isset($input['manage_stock'])) $data['manage_stock'] = (bool)$input['manage_stock'];
        if (isset($input['stock_quantity'])) {
            $sq = self::sanitizeInt($input['stock_quantity']);
            if ($sq === null || $sq < 0) $errors['stock_quantity'] = 'Invalid stock quantity';
            else $data['stock_quantity'] = $sq;
        }

        if (isset($input['categories']) && is_array($input['categories'])) {
            $cats = [];
            foreach ($input['categories'] as $c) if (is_numeric($c)) $cats[] = (int)$c;
            $data['categories'] = $cats;
        }

        if (isset($input['vendor_id'])) $data['vendor_id'] = self::sanitizeInt($input['vendor_id']);
        if (isset($input['status'])) $data['status'] = $input['status'];

        return ['valid' => empty($errors), 'errors' => $errors, 'data' => $data];
    }

    public static function validateStockChange(array $input)
    {
        $errors = [];
        $data = [];

        $productId = $input['product_id'] ?? null;
        if (!is_numeric($productId)) $errors['product_id'] = 'Product id is required';
        else $data['product_id'] = (int)$productId;

        $quantity = $input['quantity'] ?? null;
        if (!is_numeric($quantity) || (int)$quantity < 0) $errors['quantity'] = 'Quantity must be a non-negative integer';
        else $data['quantity'] = (int)$quantity;

        return ['valid' => empty($errors), 'errors' => $errors, 'data' => $data];
    }
}
?>