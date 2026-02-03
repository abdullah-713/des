<?php
require_once 'config.php';

// التحقق من إعداد السماح بالخروج
$allowLogout = SystemSettings::get('allow_employee_logout', '0');

$userRole = $_SESSION['role'] ?? 'employee';

// إذا كان المستخدم موظف وليس أدمن/مدير، فحص الإعداد
if ($userRole === 'employee' && $allowLogout === '0') {
    // منع الخروج للموظفين
    header('Location: profile.php'); // Changed to profile.php
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
