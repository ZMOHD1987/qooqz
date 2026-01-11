<?php
// htdocs/api/controllers/LanguagesController.php
declare(strict_types=1);

require_once __DIR__ . '/../models/Languages.php';
require_once __DIR__ . '/../validators/LanguagesValidator.php';

class LanguagesController
{
    // أزلنا كلمة array وأضفنا = null لضمان عدم حدوث Error 500
    public static function list($c = null): void
    {
        try {
            // تحويل $c لمصفوفة إذا كانت فارغة لتجنب أخطاء الاستخدام لاحقاً
            $container = is_array($c) ? $c : [];
            $input = $container['input'] ?? $_GET;

            $rows = Languages::all(); 
            jsonResponse(['success' => true, 'data' => $rows]);
        } catch (Throwable $e) {
            errorResponse('Database error: ' . $e->getMessage(), 500);
        }
    }

    public static function save($c = null): void
    {
        try {
            $container = is_array($c) ? $c : [];
            $input = $container['input'] ?? array_merge($_POST, (array)json_decode(file_get_contents('php://input'), true));
            
            $isEdit = isset($input['is_edit']) && ($input['is_edit'] == '1' || $input['is_edit'] === true);
            $mode = $isEdit ? 'update' : 'create';

            $errors = LanguagesValidator::validate($input, $mode);
            if (!empty($errors)) {
                jsonResponse(['success' => false, 'errors' => $errors, 'message' => 'Validation Failed'], 422);
                return;
            }

            $data = [
                'code'      => strtolower(trim((string)($input['code'] ?? ''))),
                'name'      => trim((string)($input['name'] ?? '')),
                'direction' => in_array(($input['direction'] ?? ''), ['ltr', 'rtl']) ? $input['direction'] : 'ltr'
            ];

            $ok = Languages::save($data);
            jsonResponse(['success' => $ok, 'message' => $ok ? 'Saved' : 'Error']);

        } catch (Throwable $e) {
            errorResponse('Server Error: ' . $e->getMessage(), 500);
        }
    }

    public static function delete($c = null): void
    {
        try {
            $container = is_array($c) ? $c : [];
            $input = $container['input'] ?? $_GET;
            $code = trim((string)($input['code'] ?? ''));

            $ok = Languages::delete($code);
            jsonResponse(['success' => $ok, 'message' => $ok ? 'Deleted' : 'Error']);
        } catch (Throwable $e) {
            errorResponse('Database error: ' . $e->getMessage(), 500);
        }
    }
}
