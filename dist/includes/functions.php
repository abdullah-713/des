<?php
/**
 * وظائف مساعدة عامة للنظام
 */

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
        
        if (is_array($oldValue)) $oldValue = json_encode($oldValue, JSON_UNESCAPED_UNICODE);
        if (is_array($newValue)) $newValue = json_encode($newValue, JSON_UNESCAPED_UNICODE);

        $stmt->execute([$userId, $action, $table, $recordId, $oldValue, $newValue, $ip, $ua]);
    } catch (Exception $e) {
        logError("Audit Log Error: " . $e->getMessage());
    }
}

function hasPermission($permissionCode) {
    if (!isset($_SESSION['admin_user_id'])) return false;
    
    if (isset($_SESSION['admin_role']) && ($_SESSION['admin_role'] === 'admin' || $_SESSION['admin_role'] === 'Super Admin')) {
        return true;
    }

    try {
        $db = Database::getInstance()->getConnection();
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

function formatDate($date, $format = DATE_FORMAT) {
    return date($format, strtotime($date));
}

function formatTime($time, $format = TIME_FORMAT) {
    return date($format, strtotime($time));
}

function getCurrentDate() { return date(DATE_FORMAT); }
function getCurrentTime() { return date(TIME_FORMAT); }
function getCurrentDateTime() { return date(DATETIME_FORMAT); }

function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    $data = trim($data);
    $data = strip_tags($data);
    $data = htmlspecialchars($data, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    return $data;
}

function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function isValidPhone($phone) {
    return preg_match('/^[0-9+\-\s()]+$/', $phone);
}

function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

function generateRandomCode($length = 8) {
    $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $code = '';
    for ($i = 0; $i < $length; $i++) {
        $code .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $code;
}

function calculateEarlyMinutes($actualTime, $expectedTime = '08:00:00') {
    $actual = strtotime($actualTime);
    $expected = strtotime($expectedTime);
    if ($actual >= $expected) return 0;
    return round(($expected - $actual) / 60);
}

function calculateOvertimeMinutes($actualTime, $expectedEndTime = '17:00:00') {
    $actual = strtotime($actualTime);
    $expected = strtotime($expectedEndTime);
    if ($actual <= $expected) return 0;
    return round(($actual - $expected) / 60);
}

function calculateRewardPoints($minutes) {
    $engine = new RuleEngine();
    $points = $engine->evaluate('reward_points', ['minutes' => $minutes]);
    if ($points !== null) return (int)$points;
    if ($minutes <= 0) return 0;
    return floor($minutes / 10);
}

function calculateDelayMinutes($actualTime, $expectedTime = '08:00:00') {
    $actual = strtotime($actualTime);
    $expected = strtotime($expectedTime);
    if ($actual <= $expected) return 0;
    return round(($actual - $expected) / 60);
}

function calculateDeductionPoints($delayMinutes) {
    $enabled = SystemSettings::get('points_system_enabled', '1');
    if ($enabled !== '1') return 0;
    
    $engine = new RuleEngine();
    $gracePeriod = (int)SystemSettings::get('grace_period_minutes', '30');
    
    $points = $engine->evaluate('late_deduction', [
        'delay_minutes' => $delayMinutes, 
        'grace_period' => $gracePeriod
    ]);
    
    if ($points !== null) return (int)$points;
    
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

function getAttendanceStatus($delayMinutes, $earlyMinutes = 0) {
    if ($earlyMinutes >= 60) return 'مبكر جداً';
    if ($earlyMinutes >= 30) return 'نشيط جداً';
    if ($earlyMinutes > 0) return 'نشيط';
    if ($delayMinutes <= 0) return 'حضور';
    if ($delayMinutes <= 30) return 'تأخير بسيط';
    if ($delayMinutes <= 60) return 'تأخير';
    return 'تأخير شديد';
}

function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function isAdmin() {
    return isLoggedIn() && $_SESSION['user_role'] === 'admin';
}

function isManager() {
    return isLoggedIn() && in_array($_SESSION['user_role'], ['admin', 'manager']);
}

function redirect($url) {
    header("Location: $url");
    exit();
}

function setAlert($message, $type = 'info') {
    $_SESSION['alert'] = ['message' => $message, 'type' => $type];
}

function getAlert() {
    if (isset($_SESSION['alert'])) {
        $alert = $_SESSION['alert'];
        unset($_SESSION['alert']);
        return $alert;
    }
    return null;
}

function logError($message, $file = 'error.log') {
    $timestamp = date('[Y-m-d H:i:s]');
    $logMessage = "$timestamp $message" . PHP_EOL;
    file_put_contents($file, $logMessage, FILE_APPEND | LOCK_EX);
}

function jsonResponse($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    if (ob_get_length()) ob_clean();
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit();
}

function apiJsonError($message) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    if (ob_get_length()) ob_clean();
    echo json_encode(['success' => false, 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit();
}

function isAjaxRequest() {
    return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

function getRealIpAddr() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }
}

function __($key) {
    global $translations;
    $lang = $_SESSION['lang'] ?? DEFAULT_LANG;
    return $translations[$lang][$key] ?? $key;
}
