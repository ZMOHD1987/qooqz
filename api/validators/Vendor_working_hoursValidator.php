<?php
// api/validators/Vendor_working_hoursValidator.php

if (!class_exists('Vendor_working_hoursValidator')) {

class Vendor_working_hoursValidator
{
    public static function validate(array $data)
    {
        $errors = [];

        // vendor_id
        if (empty($data['vendor_id']) || !is_numeric($data['vendor_id']) || (int)$data['vendor_id'] <= 0) {
            $errors['vendor_id'] = 'Invalid vendor';
        }

        // day_of_week (0 - 6)
        if (!isset($data['day_of_week']) || !is_numeric($data['day_of_week'])) {
            $errors['day_of_week'] = 'Invalid day';
        } else {
            $day = (int)$data['day_of_week'];
            if ($day < 0 || $day > 6) {
                $errors['day_of_week'] = 'Day out of range';
            }
        }

        // is_closed
        $isClosed = !empty($data['is_closed']) ? 1 : 0;

        // open_time / close_time
        // إذا كان مغلق → لا نتحقق من الوقت
        if (!$isClosed) {

            if (empty($data['open_time'])) {
                $errors['open_time'] = 'Open time is required';
            }

            if (empty($data['close_time'])) {
                $errors['close_time'] = 'Close time is required';
            }

            // تحقق بسيط من صيغة الوقت HH:MM[:SS]
            if (!empty($data['open_time']) &&
                !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', (string)$data['open_time'])) {
                $errors['open_time'] = 'Invalid open time format';
            }

            if (!empty($data['close_time']) &&
                !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', (string)$data['close_time'])) {
                $errors['close_time'] = 'Invalid close time format';
            }
        }

        // id (اختياري للتعديل)
        if (isset($data['id']) && $data['id'] !== '') {
            if (!is_numeric($data['id']) || (int)$data['id'] <= 0) {
                $errors['id'] = 'Invalid ID';
            }
        }

        return empty($errors) ? true : $errors;
    }
}

}
