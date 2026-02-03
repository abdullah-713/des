<?php
/**
 * فئة إدارة إعدادات النظام
 */
class SystemSettings {
    private static $settings = null;
    
    public static function get($key, $default = null) {
        if (self::$settings === null) {
            self::loadSettings();
        }
        return isset(self::$settings[$key]) ? self::$settings[$key] : $default;
    }
    
    public static function set($key, $value) {
        $db = Database::getInstance()->getConnection();
        
        $stmt = $db->prepare("
            INSERT INTO system_settings (setting_key, setting_value) 
            VALUES (?, ?) 
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
        ");
        
        $stmt->execute([$key, $value]);
        
        if (self::$settings !== null) {
            self::$settings[$key] = $value;
        }
    }
    
    private static function loadSettings() {
        try {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->query("SELECT setting_key, setting_value FROM system_settings");
            
            self::$settings = [];
            while ($row = $stmt->fetch()) {
                self::$settings[$row['setting_key']] = $row['setting_value'];
            }
        } catch (Exception $e) {
            logError('خطأ في تحميل إعدادات النظام: ' . $e->getMessage());
            self::$settings = [];
        }
    }
    
    public static function reload() {
        self::$settings = null;
        self::loadSettings();
    }
}
