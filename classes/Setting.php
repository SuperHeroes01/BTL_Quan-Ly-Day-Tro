<?php
// classes/Setting.php - Cấu hình hệ thống (tên dãy trọ, địa chỉ, hạn thanh toán...)
class Setting {
    private static $cache = null;

    public static function all() {
        if (self::$cache === null) {
            self::$cache = [];
            foreach (Database::get()->query("SELECT setting_key, setting_value FROM setting") as $r) {
                self::$cache[$r['setting_key']] = $r['setting_value'];
            }
        }
        return self::$cache;
    }
    public static function get($key, $default = '') { $a = self::all(); return $a[$key] ?? $default; }
    public static function set($key, $value) {
        Database::get()->exec("INSERT INTO setting (setting_key, setting_value) VALUES (?, ?)
                               ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)", [$key, (string) $value]);
        self::$cache = null;
    }
}
