<?php
// helpers/SettingsHelper.php

class SettingsHelper {
    private static $cache = [];
    
    public static function get($key, $pdo) {
        if (isset(self::$cache[$key])) {
            return self::$cache[$key];
        }
        
        $stmt = $pdo->prepare("SELECT value FROM system_settings WHERE `key` = ?");
        $stmt->execute([$key]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $value = $row ? json_decode($row['value'], true) : null;
        self::$cache[$key] = $value;
        
        return $value;
    }
    
    public static function set($key, $value, $pdo) {
        $jsonValue = json_encode($value);
        $stmt = $pdo->prepare("INSERT INTO system_settings (`key`, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = ?");
        $stmt->execute([$key, $jsonValue, $jsonValue]);
        
        self::$cache[$key] = $value;
    }
}