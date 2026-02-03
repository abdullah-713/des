<?php
require_once 'config.php';

// التحقق من إعداد السماح بالخروج
$allowLogout = SystemSettings::get('allow_employee_logout', '0');

// إذا كان المستخدم موظف وليس أدمن، فحص الإعداد
if (!isset($_SESSION['admin_logged_in']) && $allowLogout === '0') {
    // منع الخروج للموظفين
    header('Location: employee.php');
    exit;
}

// السماح بالخروج (للأدمن أو إذا كان الإعداد مفعل)
$_SESSION = array();
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
session_destroy();
header('Location: index.php');
exit;
