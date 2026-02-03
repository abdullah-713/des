<?php
/**
 * ملف إعدادات نظام الحضور
 * تكوين قاعدة البيانات والإعدادات العامة
 */

// إعدادات قاعدة البيانات
define('DB_HOST', 'localhost');
define('DB_NAME', 'u307296675_app');
define('DB_USER', 'u307296675_app');
define('DB_PASS', 'Goolbx512!!');
define('DB_CHARSET', 'utf8mb4');

// إعدادات النظام
define('TIMEZONE', 'Asia/Riyadh');
define('DATE_FORMAT', 'Y-m-d');
define('TIME_FORMAT', 'H:i:s');
define('DATETIME_FORMAT', 'Y-m-d H:i:s');

// إعدادات الأمان
define('ENABLE_ERROR_DISPLAY', false); // عرض الأخطاء في بيئة الإنتاج
define('SESSION_LIFETIME', 3600); // مدة الجلسة بالثواني (ساعة واحدة)
define('MAX_LOGIN_ATTEMPTS', 5); // الحد الأقصى لمحاولات تسجيل الدخول
define('LOGIN_TIMEOUT', 900); // وقت الحظر بعد تجاوز المحاولات (15 دقيقة)

// تعيين المنطقة الزمنية
date_default_timezone_set(TIMEZONE);

// إعدادات الأمان والأخطاء
if (ENABLE_ERROR_DISPLAY) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', __DIR__ . '/error.log');
}

// إعدادات الجلسة الآمنة
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_samesite', 'Strict');
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    ini_set('session.cookie_secure', 1);
}
ini_set('session.gc_maxlifetime', SESSION_LIFETIME);

// إعدادات اللغة
define('DEFAULT_LANG', 'ar');
if (isset($_GET['lang']) && in_array($_GET['lang'], ['ar', 'en'])) {
    $_SESSION['lang'] = $_GET['lang'];
}
if (!isset($_SESSION['lang'])) {
    $browserLang = substr($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? 'ar', 0, 2);
    $_SESSION['lang'] = in_array($browserLang, ['ar', 'en']) ? $browserLang : DEFAULT_LANG;
}

$translations = [
    'ar' => [
        'app_name' => 'صرح انضباط',
        'welcome' => 'مرحباً بك',
        'employee_code' => 'رقم الموظف',
        'login' => 'دخول',
        'logout' => 'خروج',
        'check_in' => 'تسجيل الحضور',
        'check_out' => 'تسجيل الانصراف',
        'status_present' => 'حاضر',
        'status_late' => 'تأخير',
        'status_very_late' => 'تأخير شديد',
        'status_early' => 'مبكر',
        'status_very_early' => 'مبكر جداً',
        'status_active' => 'نشيط',
        'status_super_active' => 'نشيط جداً',
        'status_overtime' => 'عمل إضافي',
        'today_status' => 'حالة اليوم',
        'not_checked_in' => 'لم تسجل بعد',
        'checked_in_at' => 'تم الحضور في',
        'checked_out_at' => 'تم الانصراف في',
        'delay' => 'تأخير',
        'early' => 'تبكير',
        'overtime' => 'إضافي',
        'points' => 'نقاط',
        'rewards' => 'مكافآت',
        'branch' => 'الفرع',
        'dashboard' => 'لوحة التحكم',
        'employees' => 'الموظفين',
        'branches' => 'الفروع',
        'settings' => 'الإعدادات',
        'system_enabled' => 'النظام مفعل',
        'system_disabled' => 'النظام متوقف',
    ],
    'en' => [
        'app_name' => 'Sarh Attendance',
        'welcome' => 'Welcome',
        'employee_code' => 'Employee Code',
        'login' => 'Login',
        'logout' => 'Logout',
        'check_in' => 'Check In',
        'check_out' => 'Check Out',
        'status_present' => 'Present',
        'status_late' => 'Late',
        'status_very_late' => 'Very Late',
        'status_early' => 'Early',
        'status_very_early' => 'Very Early',
        'status_active' => 'Active',
        'status_super_active' => 'Super Active',
        'status_overtime' => 'Overtime',
        'today_status' => 'Today\'s Status',
        'not_checked_in' => 'Not checked in yet',
        'checked_in_at' => 'Checked in at',
        'checked_out_at' => 'Checked out at',
        'delay' => 'Delay',
        'early' => 'Early',
        'overtime' => 'Overtime',
        'points' => 'Points',
        'rewards' => 'Rewards',
        'branch' => 'Branch',
        'dashboard' => 'Dashboard',
        'employees' => 'Employees',
        'branches' => 'Branches',
        'settings' => 'Settings',
        'system_enabled' => 'System Enabled',
        'system_disabled' => 'System Disabled',
    ]
];

function __($key) {
    global $translations;
    $lang = $_SESSION['lang'] ?? DEFAULT_LANG;
    return $translations[$lang][$key] ?? $key;
}

// بدء الجلسة
if (session_status() == PHP_SESSION_NONE) {
    session_start();
    
    // حماية من session hijacking
    if (!isset($_SESSION['initiated'])) {
        session_regenerate_id(true);
        $_SESSION['initiated'] = true;
        $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $_SESSION['ip_address'] = getRealIpAddr();
    }
    
    // التحقق من صحة الجلسة
    if (isset($_SESSION['user_agent'], $_SESSION['ip_address'])) {
        if ($_SESSION['user_agent'] !== ($_SERVER['HTTP_USER_AGENT'] ?? '') ||
            $_SESSION['ip_address'] !== getRealIpAddr()) {
            session_unset();
            session_destroy();
            session_start();
        }
    }
}

function apiJsonError($message) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    if (ob_get_length()) {
        ob_clean();
    }
    echo json_encode(['success' => false, 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit();
}

/**
 * فئة الاتصال بقاعدة البيانات
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
                PDO::ATTR_PERSISTENT => true, // اتصال مستمر لتحسين الأداء
                PDO::ATTR_TIMEOUT => 5 // مهلة الاتصال
            ];
            $this->connection = new PDO($dsn, DB_USER, DB_PASS, $options);
            
            // تحديث قاعدة البيانات تلقائياً (إضافة الأعمدة الجديدة إذا لم تكن موجودة)
            $this->runMigrations();
        } catch(PDOException $e) {
            logError('خطأ في الاتصال بقاعدة البيانات: ' . $e->getMessage());
            if (defined('API_REQUEST') && API_REQUEST) {
                apiJsonError('تعذر الاتصال بقاعدة البيانات');
            }
            if (ENABLE_ERROR_DISPLAY) {
                die("خطأ في الاتصال بقاعدة البيانات: " . $e->getMessage());
            } else {
                die("عذراً، حدث خطأ في النظام. يرجى المحاولة لاحقاً.");
            }
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

            // تحسين الأداء: إضافة فهارس (Indexes)
            try {
                $this->connection->exec("CREATE INDEX IF NOT EXISTS idx_attendance_date ON attendance_records(date)");
                $this->connection->exec("CREATE INDEX IF NOT EXISTS idx_attendance_emp_date ON attendance_records(employee_id, date)");
                $this->connection->exec("CREATE INDEX IF NOT EXISTS idx_employees_code ON employees(employee_code)");
                $this->connection->exec("CREATE INDEX IF NOT EXISTS idx_employees_branch ON employees(branch_id)");
            } catch (Exception $e) {
                // قد تفشل إذا كانت الفهارس موجودة في نسخ MySQL قديمة لا تدعم IF NOT EXISTS مع INDEX
                // يمكن تجاهل الخطأ هنا لأن الهدف هو التحسين فقط
            }
        } catch (Exception $e) {
            // تجاهل الأخطاء إذا كانت الأعمدة موجودة بالفعل في بعض إصدارات MySQL التي لا تدعم IF NOT EXISTS في ALTER
            logError("Migration Notice: " . $e->getMessage());
        }
    }
    
    /**
     * الحصول على مثيل قاعدة البيانات (Singleton)
     * @return Database
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * الحصول على كائن اتصال PDO
     * @return PDO
     */
    public function getConnection() {
        return $this->connection;
    }
    
    // منع النسخ والاستنساخ
    private function __clone() {}
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
}

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
        
        // تحديث الكاش
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

/**
 * فئة محرك القواعد الديناميكية (Dynamic Rule Engine)
 */
class RuleEngine {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * تقييم قاعدة بناءً على اسمها والمتغيرات
     */
    public function evaluate($ruleKey, $variables = []) {
        $stmt = $this->db->prepare("SELECT equation, is_active FROM dynamic_rules WHERE rule_key = ?");
        $stmt->execute([$ruleKey]);
        $rule = $stmt->fetch();

        if (!$rule || !$rule['is_active']) {
            return null; // القاعدة غير موجودة أو غير نشطة
        }

        $equation = $rule['equation'];
        return $this->evaluateExpression($equation, $variables);
    }

    /**
     * تنفيذ التعبير الرياضي بأمان
     */
    private function evaluateExpression($expression, $variables) {
        // استبدال المتغيرات بقيمها
        foreach ($variables as $key => $value) {
            // تأكد من أن القيمة رقمية للأمان
            $numericValue = is_numeric($value) ? $value : 0;
            $expression = str_replace($key, $numericValue, $expression);
        }

        // السماح فقط بالأرقام، العمليات الحسابية، الأقواس، والمقارنات الثلاثية
        // إزالة أي أحرف غير مسموح بها لمنع حقن الكود
        $sanitized = preg_replace('/[^0-9\.\+\-\*\/\(\)\?\:\<\>\=\s]/', '', $expression);

        if (empty($sanitized)) return 0;

        try {
            // استخدام eval بحذر شديد بعد التنظيف الصارم
            // بدلاً من مكتبات خارجية لعدم تغيير التقنيات
            $result = @eval("return $sanitized;");
            if ($result === false && ($sanitized != "return ;")) {
                logError("Rule Evaluation Error: Invalid expression $sanitized");
                return 0;
            }
            return $result;
        } catch (Throwable $e) {
            logError("Rule Evaluation Exception: " . $e->getMessage());
            return 0;
        }
    }
}

/**
 * دوال مساعدة عامة
 */

// تسجيل عملية في سجل التدقيق
function logAudit($action, $table = null, $recordId = null, $oldValue = null, $newValue = null) {
    try {
        $db = Database::getInstance()->getConnection();
        $userId = $_SESSION['admin_user_id'] ?? null;
        $ip = getRealIpAddr();
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';

        $stmt = $db->prepare("
            INSERT INTO audit_logs (user_id, action, table_name, record_id, old_value, new_value, ip_address, user_agent)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        // تحويل المصفوفات إلى JSON إذا لزم الأمر
        if (is_array($oldValue)) $oldValue = json_encode($oldValue, JSON_UNESCAPED_UNICODE);
        if (is_array($newValue)) $newValue = json_encode($newValue, JSON_UNESCAPED_UNICODE);

        $stmt->execute([$userId, $action, $table, $recordId, $oldValue, $newValue, $ip, $ua]);
    } catch (Exception $e) {
        logError("Audit Log Error: " . $e->getMessage());
    }
}

// التحقق من الصلاحيات (RBAC)
function hasPermission($permissionCode) {
    if (!isset($_SESSION['admin_user_id'])) return false;
    
    // المدير العام (Super Admin) لديه كل الصلاحيات
    // نفترض أن الدور رقم 1 أو الاسم هو Super Admin
    if (isset($_SESSION['admin_role']) && ($_SESSION['admin_role'] === 'admin' || $_SESSION['admin_role'] === 'Super Admin')) {
        return true;
    }

    try {
        $db = Database::getInstance()->getConnection();
        // الحصول على دور المستخدم والتحقق من الصلاحية
        $stmt = $db->prepare("
            SELECT COUNT(*) 
            FROM users u
            JOIN role_permissions rp ON u.role_id = rp.role_id
            JOIN permissions p ON rp.permission_id = p.id
            WHERE u.id = ? AND p.code = ?
        ");
        $stmt->execute([$_SESSION['admin_user_id'], $permissionCode]);
        return $stmt->fetchColumn() > 0;
    } catch (Exception $e) {
        return false;
    }
}

// تنسيق التاريخ والوقت
function formatDate($date, $format = DATE_FORMAT) {
    return date($format, strtotime($date));
}

function formatTime($time, $format = TIME_FORMAT) {
    return date($format, strtotime($time));
}

function getCurrentDate() {
    return date(DATE_FORMAT);
}

function getCurrentTime() {
    return date(TIME_FORMAT);
}

function getCurrentDateTime() {
    return date(DATETIME_FORMAT);
}

// تنظيف البيانات
function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    $data = trim($data);
    $data = strip_tags($data);
    $data = htmlspecialchars($data, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    return $data;
}

// التحقق من صحة البريد الإلكتروني
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

// التحقق من صحة رقم الهاتف
function isValidPhone($phone) {
    return preg_match('/^[0-9+\-\s()]+$/', $phone);
}

// تشفير كلمة المرور
function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

// التحقق من كلمة المرور
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

// إنشاء رمز عشوائي
function generateRandomCode($length = 8) {
    $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $code = '';
    for ($i = 0; $i < $length; $i++) {
        $code .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $code;
}

// حساب دقائق التبكير
function calculateEarlyMinutes($actualTime, $expectedTime = '08:00:00') {
    $actual = strtotime($actualTime);
    $expected = strtotime($expectedTime);
    
    if ($actual >= $expected) {
        return 0;
    }
    
    return round(($expected - $actual) / 60);
}

// حساب دقائق العمل الإضافي (بعد الانصراف)
function calculateOvertimeMinutes($actualTime, $expectedEndTime = '17:00:00') {
    $actual = strtotime($actualTime);
    $expected = strtotime($expectedEndTime);
    
    if ($actual <= $expected) {
        return 0;
    }
    
    return round(($actual - $expected) / 60);
}

// حساب نقاط المكافأة (للتبكير أو العمل الإضافي)
function calculateRewardPoints($minutes) {
    // استخدام محرك القواعد إذا كان مفعلاً
    $engine = new RuleEngine();
    $points = $engine->evaluate('reward_points', ['minutes' => $minutes]);
    
    if ($points !== null) {
        return (int)$points;
    }

    // القاعدة الافتراضية
    if ($minutes <= 0) return 0;
    return floor($minutes / 10);
}

// حساب دقائق التأخير
function calculateDelayMinutes($actualTime, $expectedTime = '08:00:00') {
    $actual = strtotime($actualTime);
    $expected = strtotime($expectedTime);
    
    if ($actual <= $expected) {
        return 0;
    }
    
    return round(($actual - $expected) / 60);
}

// حساب نقاط التأخير
function calculateDeductionPoints($delayMinutes) {
    // التحقق من تفعيل نظام النقاط
    $enabled = SystemSettings::get('points_system_enabled', '1');
    if ($enabled !== '1') return 0;
    
    // استخدام محرك القواعد
    $engine = new RuleEngine();
    $gracePeriod = (int)SystemSettings::get('grace_period_minutes', '30');
    
    $points = $engine->evaluate('late_deduction', [
        'delay_minutes' => $delayMinutes, 
        'grace_period' => $gracePeriod
    ]);
    
    if ($points !== null) {
        return (int)$points;
    }
    
    // المنطق الافتراضي القديم (Fallback)
    $penalty1 = (int)SystemSettings::get('late_penalty_1', '10');
    $penalty2 = (int)SystemSettings::get('late_penalty_2', '15');
    $penalty3 = (int)SystemSettings::get('late_penalty_3', '25');
    $penalty4 = (int)SystemSettings::get('late_penalty_4', '45');
    
    if ($delayMinutes <= $gracePeriod) return 0;
    if ($delayMinutes <= $gracePeriod + 10) return $penalty1;
    if ($delayMinutes <= $gracePeriod + 20) return $penalty2;
    if ($delayMinutes <= $gracePeriod + 30) return $penalty3;
    return $penalty4;
}

// تحديد حالة الحضور
function getAttendanceStatus($delayMinutes, $earlyMinutes = 0) {
    if ($earlyMinutes >= 60) return 'مبكر جداً';
    if ($earlyMinutes >= 30) return 'نشيط جداً';
    if ($earlyMinutes > 0) return 'نشيط';
    
    if ($delayMinutes <= 0) return 'حضور';
    if ($delayMinutes <= 30) return 'تأخير بسيط';
    if ($delayMinutes <= 60) return 'تأخير';
    return 'تأخير شديد';
}

// التحقق من تسجيل الدخول
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

// التحقق من صلاحيات المدير
function isAdmin() {
    return isLoggedIn() && $_SESSION['user_role'] === 'admin';
}

// التحقق من صلاحيات المدير أو المشرف
function isManager() {
    return isLoggedIn() && in_array($_SESSION['user_role'], ['admin', 'manager']);
}

// إعادة توجيه
function redirect($url) {
    header("Location: $url");
    exit();
}

// عرض رسالة تنبيه
function setAlert($message, $type = 'info') {
    $_SESSION['alert'] = [
        'message' => $message,
        'type' => $type
    ];
}

// الحصول على رسالة التنبيه
function getAlert() {
    if (isset($_SESSION['alert'])) {
        $alert = $_SESSION['alert'];
        unset($_SESSION['alert']);
        return $alert;
    }
    return null;
}

// تسجيل الأخطاء
function logError($message, $file = 'error.log') {
    $timestamp = date('[Y-m-d H:i:s]');
    $logMessage = "$timestamp $message" . PHP_EOL;
    file_put_contents($file, $logMessage, FILE_APPEND | LOCK_EX);
}

// إرجاع استجابة JSON
function jsonResponse($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    if (ob_get_length()) {
        ob_clean();
    }
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit();
}

// التحقق من طلب AJAX
function isAjaxRequest() {
    return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

// الحصول على عنوان IP الحقيقي
function getRealIpAddr() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        return $_SERVER['REMOTE_ADDR'];
    }
}

?>
