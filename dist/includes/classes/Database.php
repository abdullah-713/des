<?php
/**
 * فئة الاتصال بقاعدة البيانات (Singleton)
 */
class Database {
    private static $instance = null;
    private $connection;
    
    private function __construct() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
                PDO::ATTR_PERSISTENT => true,
                PDO::ATTR_TIMEOUT => 5
            ];
            $this->connection = new PDO($dsn, DB_USER, DB_PASS, $options);
            
            // تحديث قاعدة البيانات تلقائياً
            $this->runMigrations();
        } catch(PDOException $e) {
            logError('خطأ في الاتصال بقاعدة البيانات: ' . $e->getMessage());
            
            if (defined('API_REQUEST') && API_REQUEST) {
                apiJsonError('تعذر الاتصال بقاعدة البيانات');
            }
            
            if (ENABLE_ERROR_DISPLAY) {
                $errorMsg = $e->getMessage();
            } else {
                $errorMsg = "عذراً، حدث خطأ في الاتصال بقاعدة البيانات. يرجى المحاولة لاحقاً.";
            }
            
            include_once __DIR__ . '/../error_page.php';
            exit();
        }
    }
    
    private function runMigrations() {
        try {
            // إضافة وقت الانصراف للموظفين
            $this->connection->exec("ALTER TABLE employees ADD COLUMN IF NOT EXISTS end_time TIME DEFAULT '17:00:00' AFTER start_time");
            
            // إضافة أعمدة الملف الشخصي
            $this->connection->exec("ALTER TABLE employees ADD COLUMN IF NOT EXISTS password VARCHAR(255) NULL AFTER phone");
            $this->connection->exec("ALTER TABLE employees ADD COLUMN IF NOT EXISTS profile_image VARCHAR(255) NULL AFTER password");
            
            // إضافة أعمدة المكافآت لسجلات الحضور
            $this->connection->exec("ALTER TABLE attendance_records ADD COLUMN IF NOT EXISTS reward_points INT DEFAULT 0 AFTER deduction_points");
            $this->connection->exec("ALTER TABLE attendance_records ADD COLUMN IF NOT EXISTS early_minutes INT DEFAULT 0 AFTER delay_minutes");
            $this->connection->exec("ALTER TABLE attendance_records ADD COLUMN IF NOT EXISTS overtime_minutes INT DEFAULT 0 AFTER early_minutes");

            // إضافة أعمدة الجدولة المخصصة للموظفين
            $this->connection->exec("ALTER TABLE employees ADD COLUMN IF NOT EXISTS custom_check_in_start TIME NULL AFTER end_time");
            $this->connection->exec("ALTER TABLE employees ADD COLUMN IF NOT EXISTS custom_check_in_end TIME NULL AFTER custom_check_in_start");
            $this->connection->exec("ALTER TABLE employees ADD COLUMN IF NOT EXISTS custom_check_out_start TIME NULL AFTER custom_check_in_end");

            // تحسين الأداء: إضافة فهارس
            try {
                $this->connection->exec("CREATE INDEX IF NOT EXISTS idx_attendance_date ON attendance_records(date)");
                $this->connection->exec("CREATE INDEX IF NOT EXISTS idx_attendance_emp_date ON attendance_records(employee_id, date)");
                $this->connection->exec("CREATE INDEX IF NOT EXISTS idx_employees_code ON employees(employee_code)");
                $this->connection->exec("CREATE INDEX IF NOT EXISTS idx_employees_branch ON employees(branch_id)");
            } catch (Exception $e) {}
        } catch (Exception $e) {
            logError("Migration Notice: " . $e->getMessage());
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->connection;
    }
    
    private function __clone() {}
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
}
