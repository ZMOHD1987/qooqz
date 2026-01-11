<?php
declare(strict_types=1);

/**
 * LanguagesValidator
 * يقوم بالتحقق من بيانات اللغات بناءً على هيكل جدول اللغات (code, name, direction)
 */
class LanguagesValidator
{
    public static function validate(array $data, string $mode = 'save'): array
    {
        $errors = [];

        // 1. تحديد المود: بما أن code هو المفتاح الأساسي، نعتمد على وجوده للتمييز بين التحديث والإنشاء
        // إذا كان المود 'save'، نعتبره 'create' إلا إذا تم تمرير مؤشر للتعديل
        if ($mode === 'save' || empty($mode)) {
            // في اللغات، غالباً ما نستخدم "save" كدالة شاملة (Upsert) 
            // لكن للتدقيق سنفترض الإنشاء كحالة افتراضية
            $mode = ($data['is_edit'] ?? false) ? 'update' : 'create';
        }

        /* ================== Code (Primary Key) ================== */
        if ($mode === 'create' || array_key_exists('code', $data)) {
            $code = trim((string)($data['code'] ?? ''));

            if ($code === '') {
                $errors['code'] = 'Language code is required';
            } elseif (strlen($code) < 2) {
                $errors['code'] = 'Code must be at least 2 characters (e.g., en)';
            } elseif (strlen($code) > 8) {
                $errors['code'] = 'Code is too long (max 8 characters)';
            } elseif (!preg_match('/^[a-z0-9-]+$/i', $code)) {
                // الكود يجب أن يحتوي فقط على أحرف، أرقام، أو شرطة
                $errors['code'] = 'Code contains invalid characters';
            }
        }

        /* ================== Name ================== */
        if ($mode === 'create' || array_key_exists('name', $data)) {
            $name = trim((string)($data['name'] ?? ''));

            if ($name === '') {
                $errors['name'] = 'Language name is required';
            } elseif (mb_strlen($name) < 2) {
                $errors['name'] = 'Name is too short';
            } elseif (mb_strlen($name) > 100) {
                $errors['name'] = 'Name is too long';
            }
        }

        /* ================== Direction ================== */
        if ($mode === 'create' || array_key_exists('direction', $data)) {
            $direction = strtolower(trim((string)($data['direction'] ?? 'ltr')));

            if ($direction === '') {
                // نضع قيمة افتراضية بدلاً من الخطأ لأن الجدول لديه Default ltr
                $data['direction'] = 'ltr'; 
            } elseif (!in_array($direction, ['ltr', 'rtl'], true)) {
                $errors['direction'] = 'Direction must be either LTR or RTL';
            }
        }

        return $errors;
    }

    /**
     * دالة مساعدة للتحقق من وجود الكود فقط (تستخدم في الحذف مثلاً)
     */
    public static function isValidCode(string $code): bool
    {
        return !empty($code) && strlen($code) <= 8 && preg_match('/^[a-z0-9-]+$/i', $code);
    }
}