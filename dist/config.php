<?php
/**
 * ملف إعدادات نظام الحضور
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
define('ENABLE_ERROR_DISPLAY', false); 
define('SESSION_LIFETIME', 3600); 
define('MAX_LOGIN_ATTEMPTS', 5); 
define('LOGIN_TIMEOUT', 900); 

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

// بدء الجلسة
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

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

// وظيفة Autoload لتحميل الكلاسات تلقائياً
spl_autoload_register(function ($class_name) {
    $file = __DIR__ . '/includes/classes/' . $class_name . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

// تحميل الوظائف الأساسية
require_once __DIR__ . '/includes/functions.php';

// حماية الجلسة من الاختطاف
if (!isset($_SESSION['initiated'])) {
    session_regenerate_id(true);
    $_SESSION['initiated'] = true;
    $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $_SESSION['ip_address'] = getRealIpAddr();
}

if (isset($_SESSION['user_agent'], $_SESSION['ip_address'])) {
    if ($_SESSION['user_agent'] !== ($_SERVER['HTTP_USER_AGENT'] ?? '') ||
        $_SESSION['ip_address'] !== getRealIpAddr()) {
        session_unset();
        session_destroy();
        session_start();
    }
}
