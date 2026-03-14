<?php
declare(strict_types=1);

use InvalidArgumentException;

final class EntitySettingsValidator
{
    /**
     * التحقق من صحة البيانات لإنشاء إعدادات جديدة
     */
    public static function validateCreate(array $data): void
    {
        if (empty($data['entity_id']) || !is_numeric($data['entity_id'])) {
            throw new InvalidArgumentException("Field 'entity_id' is required and must be numeric");
        }

        self::validateCommonFields($data);
    }

    /**
     * التحقق من صحة البيانات لتحديث إعدادات موجودة
     */
    public static function validateUpdate(array $data): void
    {
        if (empty($data)) {
            throw new InvalidArgumentException("No data provided for update");
        }

        self::validateCommonFields($data);
    }

    /**
     * التحقق من الحقول المشتركة بين الإنشاء والتحديث
     */
    private static function validateCommonFields(array $data): void
    {
        // التحقق من الحقول المنطقية (Boolean)
        $boolFields = [
            'auto_accept_orders', 'allow_cod', 'allow_online_booking',
            'booking_cancellation_allowed', 'allow_preorders', 'is_visible',
            'maintenance_mode', 'show_reviews', 'show_contact_info',
            'featured_in_app', 'allow_multiple_payment_methods'
        ];

        foreach ($boolFields as $field) {
            if (isset($data[$field]) && !in_array((int)$data[$field], [0, 1], true)) {
                throw new InvalidArgumentException("$field must be 0 or 1");
            }
        }

        // التحقق من الحقول الرقمية
        $numericFields = [
            'min_order_amount', 'preparation_time_minutes',
            'booking_window_days', 'max_bookings_per_slot',
            'max_daily_orders', 'delivery_radius_km', 'free_delivery_min_order'
        ];
        
        foreach ($numericFields as $field) {
            if (isset($data[$field]) && !is_numeric($data[$field])) {
                throw new InvalidArgumentException("$field must be numeric");
            }
            
            // التحقق من القيم الموجبة فقط
            if (isset($data[$field]) && (float)$data[$field] < 0) {
                throw new InvalidArgumentException("$field must be a positive number");
            }
        }

        // التحقق من الحقول النصية الخاصة
        if (isset($data['default_payment_method'])) {
            $allowedPaymentMethods = ['cash', 'card', 'bank_transfer', 'digital_wallet', null];
            if (!in_array($data['default_payment_method'], $allowedPaymentMethods, true)) {
                throw new InvalidArgumentException("Invalid default_payment_method value");
            }
        }

        // التحقق من حقول JSON (longtext)
        $jsonFields = ['notification_preferences', 'additional_settings'];
        foreach ($jsonFields as $field) {
            if (isset($data[$field]) && is_string($data[$field])) {
                json_decode($data[$field]);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new InvalidArgumentException("$field must be a valid JSON string");
                }
            }
        }
    }
}