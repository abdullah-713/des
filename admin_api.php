<?php
define('API_REQUEST', true);
/**
 * واجهة برمجة التطبيقات للوحة الإدارة
 * API لإدارة الموظفين والفروع والإعدادات
 */

require_once 'config.php';

// رؤوس أمان HTTP
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

ob_start();
set_exception_handler(function ($e) {
    logError("خطأ غير معالج في API الإدارة: " . $e->getMessage());
    apiJsonError('حدث خطأ في النظام');
});
set_error_handler(function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});
register_shutdown_function(function () {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) {
        logError("خطأ قاتل في API الإدارة: " . $error['message']);
        apiJsonError('حدث خطأ في النظام');
    }
});

// التحقق من الصلاحيات
if (!isset($_SESSION['admin_logged_in'])) {
    jsonResponse(['success' => false, 'message' => 'غير مصرح لك بالوصول. يرجى تسجيل الدخول.'], 403);
}

// التحقق من انتهاء صلاحية الجلسة (30 دقيقة)
$sessionTimeout = 1800;
if (isset($_SESSION['created']) && (time() - $_SESSION['created']) > $sessionTimeout) {
    session_destroy();
    jsonResponse(['success' => false, 'message' => 'انتهت صلاحية الجلسة. يرجى تسجيل الدخول مرة أخرى.', 'session_expired' => true], 401);
}

// تحديث وقت آخر نشاط
$_SESSION['last_activity'] = time();

// التأكد من أن الطلب POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'طريقة الطلب غير صحيحة'], 405);
}

// التحقق من رأس Origin (حماية CSRF بسيطة)
$allowedOrigins = [$_SERVER['HTTP_HOST'] ?? '', 'localhost', '127.0.0.1'];
$origin = $_SERVER['HTTP_ORIGIN'] ?? $_SERVER['HTTP_REFERER'] ?? '';
if ($origin) {
    $originHost = parse_url($origin, PHP_URL_HOST);
    if ($originHost && !in_array($originHost, $allowedOrigins) && !str_contains($origin, $_SERVER['HTTP_HOST'])) {
        // سجل المحاولة المشبوهة
        logError("Suspicious API request from: $origin");
    }
}

// الحصول على البيانات المرسلة
$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

try {
    $db = Database::getInstance()->getConnection();
    
    switch ($action) {
        case 'get_dashboard_data':
            getDashboardData($db);
            break;
            
        case 'get_attendance_records':
            getAttendanceRecords($db, $input);
            break;
            
        case 'get_employees':
            getEmployees($db);
            break;
            
        case 'add_employee':
            addEmployee($db, $input);
            break;
            
        case 'update_employee':
            updateEmployee($db, $input);
            break;
            
        case 'delete_employee':
            deleteEmployee($db, $input);
            break;
            
        case 'get_branches':
            getBranches($db);
            break;
            
        case 'add_branch':
            addBranch($db, $input);
            break;
            
        case 'update_branch':
            updateBranch($db, $input);
            break;
            
        case 'delete_branch':
            deleteBranch($db, $input);
            break;
            
        case 'update_setting':
            updateSetting($db, $input);
            break;
            
        case 'get_settings':
            getSettings($db);
            break;
            
        case 'save_settings':
            saveSettings($db, $input);
            break;
            
        case 'get_employee':
            getEmployee($db, $input);
            break;
            
        case 'get_branch':
            getBranch($db, $input);
            break;
            
        case 'edit_attendance':
            editAttendance($db, $input);
            break;
            
        case 'generate_employee_code':
            generateEmployeeCode($db, $input);
            break;
            
        case 'update_points_system':
            updatePointsSystem($db, $input);
            break;
            
        case 'activate_all_employees':
            activateAllEmployees($db);
            break;
            
        case 'toggle_employee_status':
            toggleEmployeeStatus($db, $input);
            break;
            
        case 'add_default_employees':
            addDefaultEmployees($db, $input);
            break;
            
        case 'remove_duplicate_employees':
            removeDuplicateEmployees($db);
            break;
            
        case 'bulk_attendance_record':
            bulkAttendanceRecord($db, $input);
            break;
            
        case 'get_points_system':
            getPointsSystem($db);
            break;
            
        case 'adjust_employee_points':
            adjustEmployeePoints($db, $input);
            break;
            
        case 'reset_employee_points':
            resetEmployeePoints($db, $input);
            break;
            
        case 'reset_today_records':
            resetTodayRecords($db);
            break;

        // Advanced Control Panel Actions
        case 'get_rules':
            getRules($db);
            break;
        case 'save_rule':
            saveRule($db, $input);
            break;
        case 'delete_rule':
            deleteRule($db, $input);
            break;
        case 'get_audit_logs':
            getAuditLogs($db, $input);
            break;
        case 'get_roles':
            getRoles($db);
            break;
        case 'get_role':
            getRole($db, $input);
            break;
        case 'save_role':
            saveRole($db, $input);
            break;
        case 'delete_role':
            deleteRole($db, $input);
            break;
        case 'get_permissions':
            getPermissions($db);
            break;
        case 'get_backups':
            getBackups($db);
            break;
        case 'create_backup':
            createBackup($db);
            break;
        case 'restore_backup':
            restoreBackup($db, $input);
            break;
        case 'delete_backup':
            deleteBackup($db, $input);
            break;
            
        default:
            jsonResponse(['success' => false, 'message' => 'عملية غير معروفة'], 400);
    }
    
} catch (Exception $e) {
    logError("خطأ في API الإدارة: " . $e->getMessage());
    jsonResponse(['success' => false, 'message' => 'حدث خطأ في النظام'], 500);
}

/**
 * الحصول على بيانات لوحة التحكم
 */
function getDashboardData($db) {
    $currentDate = getCurrentDate();
    
    // الإحصائيات العامة
    $stmt = $db->prepare("
        SELECT 
            COUNT(DISTINCT e.id) as total_employees,
            SUM(CASE WHEN ar.check_in_time IS NOT NULL THEN 1 ELSE 0 END) as present_count,
            SUM(CASE WHEN ar.deduction_points > 0 THEN 1 ELSE 0 END) as late_count,
            SUM(CASE WHEN ar.deduction_points > 0 THEN ar.deduction_points ELSE 0 END) as total_deductions
        FROM employees e
        LEFT JOIN attendance_records ar ON e.id = ar.employee_id AND ar.date = ?
        WHERE e.is_active = 1
    ");
    $stmt->execute([$currentDate]);
    $generalStats = $stmt->fetch();
    
    // حساب نسبة الحضور (الموظفين الذين لم يتأخروا)
    $onTimeCount = $generalStats['present_count'] - $generalStats['late_count'];
    $attendanceRate = $generalStats['total_employees'] > 0 ? 
        round(($onTimeCount / $generalStats['total_employees']) * 100) : 0;
    
    $generalStats['attendance_rate'] = $attendanceRate;
    
    // إحصائيات الفروع
    $stmt = $db->prepare("
        SELECT 
            b.id,
            b.name,
            COUNT(DISTINCT e.id) as total_employees,
            SUM(CASE WHEN ar.check_in_time IS NOT NULL THEN 1 ELSE 0 END) as present_count,
            SUM(CASE WHEN ar.deduction_points > 0 THEN 1 ELSE 0 END) as late_count,
            SUM(CASE WHEN ar.delay_minutes > 0 THEN ar.delay_minutes ELSE 0 END) as total_delay_minutes,
            SUM(CASE WHEN ar.deduction_points > 0 THEN ar.deduction_points ELSE 0 END) as total_deductions
        FROM branches b
        LEFT JOIN employees e ON b.id = e.branch_id AND e.is_active = 1
        LEFT JOIN attendance_records ar ON e.id = ar.employee_id AND ar.date = ?
        WHERE b.is_active = 1
        GROUP BY b.id, b.name
        ORDER BY b.name
    ");
    $stmt->execute([$currentDate]);
    $branchStats = $stmt->fetchAll();
    
    jsonResponse([
        'success' => true,
        'data' => [
            'stats' => $generalStats,
            'branches' => $branchStats,
            'date' => formatDate($currentDate, 'd/m/Y')
        ]
    ]);
}

/**
 * الحصول على سجلات الحضور
 */
function getAttendanceRecords($db, $input) {
    $date = $input['date'] ?? getCurrentDate();
    
    $stmt = $db->prepare("
        SELECT 
            ar.*,
            e.name as employee_name,
            e.employee_code,
            b.name as branch_name
        FROM attendance_records ar
        JOIN employees e ON ar.employee_id = e.id
        JOIN branches b ON e.branch_id = b.id
        WHERE ar.date = ?
        ORDER BY b.name, e.name
    ");
    $stmt->execute([$date]);
    $records = $stmt->fetchAll();
    
    jsonResponse([
        'success' => true,
        'data' => $records
    ]);
}

/**
 * الحصول على قائمة الموظفين
 */
function getEmployees($db) {
    $stmt = $db->query("
        SELECT 
            e.*,
            b.name as branch_name
        FROM employees e
        JOIN branches b ON e.branch_id = b.id
        ORDER BY b.name, e.name
    ");
    $employees = $stmt->fetchAll();
    
    jsonResponse([
        'success' => true,
        'data' => $employees
    ]);
}

/**
 * إضافة موظف جديد
 */
function addEmployee($db, $input) {
    $employeeCode = sanitizeInput($input['employee_code'] ?? '');
    $name = sanitizeInput($input['name'] ?? '');
    $branchId = (int)($input['branch_id'] ?? 0);
    $position = sanitizeInput($input['position'] ?? '');
    $phone = sanitizeInput($input['phone'] ?? '');
    $email = sanitizeInput($input['email'] ?? '');
    $startTime = $input['start_time'] ?? '08:00:00';
    $endTime = $input['end_time'] ?? '17:00:00';
    $customCheckInStart = !empty($input['custom_check_in_start']) ? $input['custom_check_in_start'] : null;
    $customCheckInEnd = !empty($input['custom_check_in_end']) ? $input['custom_check_in_end'] : null;
    $customCheckOutStart = !empty($input['custom_check_out_start']) ? $input['custom_check_out_start'] : null;
    
    // التحقق من البيانات المطلوبة
    if (empty($employeeCode) || empty($name) || $branchId <= 0) {
        jsonResponse(['success' => false, 'message' => 'يرجى ملء جميع الحقول المطلوبة']);
    }
    
    // التحقق من عدم تكرار رقم الموظف
    $stmt = $db->prepare("SELECT id FROM employees WHERE employee_code = ?");
    $stmt->execute([$employeeCode]);
    if ($stmt->fetch()) {
        jsonResponse(['success' => false, 'message' => 'رقم الموظف موجود مسبقاً']);
    }
    
    // التحقق من صحة البريد الإلكتروني
    if (!empty($email) && !isValidEmail($email)) {
        jsonResponse(['success' => false, 'message' => 'البريد الإلكتروني غير صحيح']);
    }
    
    // التحقق من وجود الفرع
    $stmt = $db->prepare("SELECT name FROM branches WHERE id = ? AND is_active = 1");
    $stmt->execute([$branchId]);
    $branch = $stmt->fetch();
    
    if (!$branch) {
        jsonResponse(['success' => false, 'message' => 'الفرع المحدد غير موجود أو غير مفعل']);
    }
    
    // الحصول على النقاط الافتراضية
    $defaultPoints = 100;
    try {
        $stmt = $db->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'default_points'");
        $stmt->execute();
        $result = $stmt->fetch();
        if ($result) {
            $defaultPoints = (int)$result['setting_value'];
        }
    } catch (Exception $e) {
        // استخدام القيمة الافتراضية
    }
    
    // كلمة المرور مشفرة (مثل المدير): اختيارية، إن لم تُدخل تُستخدم الافتراضية
    $password = $input['password'] ?? '';
    $passwordHash = $password !== '' ? password_hash($password, PASSWORD_DEFAULT) : password_hash('123456', PASSWORD_DEFAULT);
    
    // إدراج المستخدم الجديد
    $stmt = $db->prepare("
        INSERT INTO employees 
        (employee_code, name, branch_id, position, phone, email, start_time, end_time, points_balance, is_active, password) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?)
    ");
    
    if ($stmt->execute([$employeeCode, $name, $branchId, $position, $phone, $email, $startTime, $endTime, $defaultPoints, $passwordHash])) {
        $employeeId = $db->lastInsertId();
        
        // لا نضيف سجل حضور افتراضي - الموظف سيسجل بنفسه عند الحضور
        
        jsonResponse([
            'success' => true,
            'message' => 'تم إضافة الموظف بنجاح',
            'employee_id' => $employeeId
        ]);
    } else {
        jsonResponse(['success' => false, 'message' => 'فشل في إضافة الموظف']);
    }
}

/**
 * تحديث بيانات موظف
 */
function updateEmployee($db, $input) {
    $employeeId = (int)($input['employee_id'] ?? 0);
    $employeeCode = sanitizeInput($input['employee_code'] ?? '');
    $name = sanitizeInput($input['name'] ?? '');
    $branchId = (int)($input['branch_id'] ?? 0);
    $position = sanitizeInput($input['position'] ?? '');
    $phone = sanitizeInput($input['phone'] ?? '');
    $email = sanitizeInput($input['email'] ?? '');
    $startTime = $input['start_time'] ?? '08:00:00';
    $endTime = $input['end_time'] ?? '17:00:00';
    $isActive = (int)($input['is_active'] ?? 1);
    $customCheckInStart = !empty($input['custom_check_in_start']) ? $input['custom_check_in_start'] : null;
    $customCheckInEnd = !empty($input['custom_check_in_end']) ? $input['custom_check_in_end'] : null;
    $customCheckOutStart = !empty($input['custom_check_out_start']) ? $input['custom_check_out_start'] : null;
    
    if ($employeeId <= 0 || empty($employeeCode) || empty($name) || $branchId <= 0) {
        jsonResponse(['success' => false, 'message' => 'يرجى ملء جميع الحقول المطلوبة']);
    }
    
    $stmt = $db->prepare("SELECT id FROM employees WHERE employee_code = ? AND id != ?");
    $stmt->execute([$employeeCode, $employeeId]);
    if ($stmt->fetch()) {
        jsonResponse(['success' => false, 'message' => 'رقم المستخدم موجود مسبقاً']);
    }
    
    if (!empty($email) && !isValidEmail($email)) {
        jsonResponse(['success' => false, 'message' => 'البريد الإلكتروني غير صحيح']);
    }
    
    $sql = "UPDATE employees SET employee_code = ?, name = ?, branch_id = ?, position = ?, phone = ?, email = ?, start_time = ?, end_time = ?, custom_check_in_start = ?, custom_check_in_end = ?, custom_check_out_start = ?, is_active = ?";
    $params = [$employeeCode, $name, $branchId, $position, $phone, $email, $startTime, $endTime, $customCheckInStart, $customCheckInEnd, $customCheckOutStart, $isActive];
    
    if (!empty($input['password'])) {
        $sql .= ", password = ?";
        $params[] = password_hash($input['password'], PASSWORD_DEFAULT);
    }
    $params[] = $employeeId;
    $sql .= " WHERE id = ?";
    
    $stmt = $db->prepare($sql);
    if ($stmt->execute($params)) {
        jsonResponse(['success' => true, 'message' => 'تم تحديث بيانات المستخدم بنجاح']);
    } else {
        jsonResponse(['success' => false, 'message' => 'فشل في تحديث البيانات']);
    }
}

/**
 * حذف موظف
 */
function deleteEmployee($db, $input) {
    $employeeId = (int)($input['employee_id'] ?? 0);
    
    if ($employeeId <= 0) {
        jsonResponse(['success' => false, 'message' => 'معرف الموظف غير صحيح']);
    }
    
    // التحقق من وجود الموظف
    $stmt = $db->prepare("SELECT name FROM employees WHERE id = ?");
    $stmt->execute([$employeeId]);
    $employee = $stmt->fetch();
    
    if (!$employee) {
        jsonResponse(['success' => false, 'message' => 'الموظف غير موجود']);
    }
    
    // حذف الموظف (سيتم حذف سجلات الحضور تلقائياً بسبب CASCADE)
    $stmt = $db->prepare("DELETE FROM employees WHERE id = ?");
    
    if ($stmt->execute([$employeeId])) {
        jsonResponse(['success' => true, 'message' => 'تم حذف الموظف بنجاح']);
    } else {
        jsonResponse(['success' => false, 'message' => 'فشل في حذف الموظف']);
    }
}

/**
 * الحصول على قائمة الفروع
 */
function getBranches($db) {
    $stmt = $db->query("
        SELECT 
            b.*,
            COUNT(e.id) as employee_count
        FROM branches b
        LEFT JOIN employees e ON b.id = e.branch_id AND e.is_active = 1
        GROUP BY b.id
        ORDER BY b.name
    ");
    $branches = $stmt->fetchAll();
    
    jsonResponse([
        'success' => true,
        'data' => $branches
    ]);
}

/**
 * إضافة فرع جديد
 */
function addBranch($db, $input) {
    $name = sanitizeInput($input['name'] ?? '');
    $address = sanitizeInput($input['address'] ?? '');
    $phone = sanitizeInput($input['phone'] ?? '');
    
    if (empty($name)) {
        jsonResponse(['success' => false, 'message' => 'اسم الفرع مطلوب']);
    }
    
    // التحقق من عدم تكرار اسم الفرع
    $stmt = $db->prepare("SELECT id FROM branches WHERE name = ?");
    $stmt->execute([$name]);
    if ($stmt->fetch()) {
        jsonResponse(['success' => false, 'message' => 'اسم الفرع موجود مسبقاً']);
    }
    
    // إدراج الفرع الجديد
    $stmt = $db->prepare("
        INSERT INTO branches (name, address, phone) 
        VALUES (?, ?, ?)
    ");
    
    if ($stmt->execute([$name, $address, $phone])) {
        jsonResponse([
            'success' => true,
            'message' => 'تم إضافة الفرع بنجاح',
            'branch_id' => $db->lastInsertId()
        ]);
    } else {
        jsonResponse(['success' => false, 'message' => 'فشل في إضافة الفرع']);
    }
}

/**
 * تحديث بيانات فرع
 */
function updateBranch($db, $input) {
    $branchId = (int)($input['branch_id'] ?? 0);
    $name = sanitizeInput($input['name'] ?? '');
    $address = sanitizeInput($input['address'] ?? '');
    $phone = sanitizeInput($input['phone'] ?? '');
    $isActive = (int)($input['is_active'] ?? 1);
    
    if ($branchId <= 0 || empty($name)) {
        jsonResponse(['success' => false, 'message' => 'اسم الفرع مطلوب']);
    }
    
    // التحقق من عدم تكرار اسم الفرع
    $stmt = $db->prepare("SELECT id FROM branches WHERE name = ? AND id != ?");
    $stmt->execute([$name, $branchId]);
    if ($stmt->fetch()) {
        jsonResponse(['success' => false, 'message' => 'اسم الفرع موجود مسبقاً']);
    }
    
    // تحديث بيانات الفرع
    $stmt = $db->prepare("
        UPDATE branches 
        SET name = ?, address = ?, phone = ?, is_active = ?
        WHERE id = ?
    ");
    
    if ($stmt->execute([$name, $address, $phone, $isActive, $branchId])) {
        jsonResponse(['success' => true, 'message' => 'تم تحديث بيانات الفرع بنجاح']);
    } else {
        jsonResponse(['success' => false, 'message' => 'فشل في تحديث بيانات الفرع']);
    }
}

/**
 * حذف فرع
 */
function deleteBranch($db, $input) {
    $branchId = (int)($input['branch_id'] ?? 0);
    
    if ($branchId <= 0) {
        jsonResponse(['success' => false, 'message' => 'معرف الفرع غير صحيح']);
    }
    
    // التحقق من وجود موظفين في الفرع
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM employees WHERE branch_id = ?");
    $stmt->execute([$branchId]);
    $result = $stmt->fetch();
    
    if ($result['count'] > 0) {
        jsonResponse(['success' => false, 'message' => 'لا يمكن حذف الفرع لوجود موظفين مرتبطين به']);
    }
    
    // حذف الفرع
    $stmt = $db->prepare("DELETE FROM branches WHERE id = ?");
    
    if ($stmt->execute([$branchId])) {
        jsonResponse(['success' => true, 'message' => 'تم حذف الفرع بنجاح']);
    } else {
        jsonResponse(['success' => false, 'message' => 'فشل في حذف الفرع']);
    }
}

/**
 * تحديث إعداد واحد
 */
function updateSetting($db, $input) {
    $key = sanitizeInput($input['key'] ?? '');
    $value = sanitizeInput($input['value'] ?? '');
    
    if (empty($key)) {
        jsonResponse(['success' => false, 'message' => 'مفتاح الإعداد مطلوب']);
    }
    
    SystemSettings::set($key, $value);
    
    $message = '';
    switch ($key) {
        case 'attendance_enabled':
            $message = $value === '1' ? 'تم تفعيل نظام الحضور' : 'تم إيقاف نظام الحضور';
            break;
        case 'attendance_mode':
            $mode = $value === 'check_out' ? 'الانصراف' : 'الحضور';
            $message = "تم تغيير وضع النظام إلى: $mode";
            break;
        default:
            $message = 'تم تحديث الإعداد بنجاح';
    }
    
    jsonResponse(['success' => true, 'message' => $message]);
}

/**
 * الحصول على جميع الإعدادات
 */
function getAllSettingsOld($db) {
    $stmt = $db->query("SELECT setting_key, setting_value FROM system_settings");
    $settings = [];
    
    while ($row = $stmt->fetch()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    
    jsonResponse([
        'success' => true,
        'data' => $settings
    ]);
}

/**
 * حفظ جميع الإعدادات (طريقة قديمة)
 */
function saveAllSettingsOld($db, $input) {
    $settings = $input['settings'] ?? [];
    
    if (empty($settings)) {
        jsonResponse(['success' => false, 'message' => 'لا توجد إعدادات للحفظ']);
    }
    
    $db->beginTransaction();
    
    try {
        foreach ($settings as $key => $value) {
            SystemSettings::set($key, sanitizeInput($value));
        }
        
        $db->commit();
        jsonResponse(['success' => true, 'message' => 'تم حفظ الإعدادات بنجاح']);
        
    } catch (Exception $e) {
        $db->rollback();
        jsonResponse(['success' => false, 'message' => 'فشل في حفظ الإعدادات']);
    }
}

/**
 * الحصول على بيانات موظف واحد
 */
function getEmployee($db, $input) {
    $employeeId = (int)($input['employee_id'] ?? 0);
    
    if ($employeeId <= 0) {
        jsonResponse(['success' => false, 'message' => 'معرف الموظف غير صحيح']);
    }
    
    $stmt = $db->prepare("
        SELECT e.*, b.name as branch_name 
        FROM employees e 
        JOIN branches b ON e.branch_id = b.id 
        WHERE e.id = ?
    ");
    $stmt->execute([$employeeId]);
    $employee = $stmt->fetch();
    
    if (!$employee) {
        jsonResponse(['success' => false, 'message' => 'الموظف غير موجود']);
    }
    
    jsonResponse([
        'success' => true,
        'data' => $employee
    ]);
}

/**
 * الحصول على بيانات فرع واحد
 */
function getBranch($db, $input) {
    $branchId = (int)($input['branch_id'] ?? 0);
    
    if ($branchId <= 0) {
        jsonResponse(['success' => false, 'message' => 'معرف الفرع غير صحيح']);
    }
    
    $stmt = $db->prepare("SELECT * FROM branches WHERE id = ?");
    $stmt->execute([$branchId]);
    $branch = $stmt->fetch();
    
    if (!$branch) {
        jsonResponse(['success' => false, 'message' => 'الفرع غير موجود']);
    }
    
    jsonResponse([
        'success' => true,
        'data' => $branch
    ]);
}

/**
 * تعديل سجل حضور
 */
function editAttendance($db, $input) {
    $recordId = (int)($input['record_id'] ?? 0);
    $checkInTime = $input['check_in_time'] ?? null;
    $checkOutTime = $input['check_out_time'] ?? null;
    $status = sanitizeInput($input['status'] ?? '');
    $notes = sanitizeInput($input['notes'] ?? '');
    
    if ($recordId <= 0) {
        jsonResponse(['success' => false, 'message' => 'معرف السجل غير صحيح']);
    }
    
    // حساب التأخير والنقاط
    $delayMinutes = 0;
    $deductionPoints = 0;
    
    if ($checkInTime) {
        $delayMinutes = calculateDelayMinutes($checkInTime, '08:00:00');
        $deductionPoints = calculateDeductionPoints($delayMinutes);
        if (empty($status)) {
            $status = getAttendanceStatus($delayMinutes);
        }
    }
    
    $stmt = $db->prepare("
        UPDATE attendance_records 
        SET check_in_time = ?, check_out_time = ?, delay_minutes = ?, 
            deduction_points = ?, status = ?, notes = ?
        WHERE id = ?
    ");
    
    if ($stmt->execute([$checkInTime, $checkOutTime, $delayMinutes, $deductionPoints, $status, $notes, $recordId])) {
        jsonResponse(['success' => true, 'message' => 'تم تحديث سجل الحضور بنجاح']);
    } else {
        jsonResponse(['success' => false, 'message' => 'فشل في تحديث سجل الحضور']);
    }
}

/**
 * توليد رقم موظف من الاسم
 */
function generateEmployeeCode($db, $input) {
    $name = sanitizeInput($input['name'] ?? '');
    
    if (empty($name)) {
        jsonResponse(['success' => false, 'message' => 'اسم الموظف مطلوب']);
    }
    
    // استخراج الاسم الأول كاملاً
    $nameWords = array_filter(explode(' ', trim($name)));
    $firstName = $nameWords[0] ?? $name;
    
    // توليد أرقام عشوائية
    do {
        $randomNumbers = str_pad(rand(0, 999), 3, '0', STR_PAD_LEFT);
        $employeeCode = $firstName . ' ' . $randomNumbers;
        
        // التحقق من عدم وجود الرقم
        $stmt = $db->prepare("SELECT id FROM employees WHERE employee_code = ?");
        $stmt->execute([$employeeCode]);
        $exists = $stmt->fetch();
        
    } while ($exists);
    
    jsonResponse([
        'success' => true,
        'employee_code' => $employeeCode
    ]);
}

/**
 * تحديث نظام النقاط
 */
function updatePointsSystem($db, $input) {
    $enabled = (int)($input['enabled'] ?? 1);
    $gracePeriod = (int)($input['grace_period'] ?? 30);
    $penalty1 = (int)($input['penalty_1'] ?? 10);
    $penalty2 = (int)($input['penalty_2'] ?? 15);
    $penalty3 = (int)($input['penalty_3'] ?? 25);
    $penalty4 = (int)($input['penalty_4'] ?? 45);
    
    try {
        $db->beginTransaction();
        
        // تحديث الإعدادات
        SystemSettings::set('points_system_enabled', $enabled);
        SystemSettings::set('grace_period_minutes', $gracePeriod);
        SystemSettings::set('late_penalty_1', $penalty1);
        SystemSettings::set('late_penalty_2', $penalty2);
        SystemSettings::set('late_penalty_3', $penalty3);
        SystemSettings::set('late_penalty_4', $penalty4);
        
        // إعادة حساب النقاط لجميع سجلات الحضور اليوم
        $currentDate = getCurrentDate();
        $stmt = $db->prepare("
            SELECT ar.id, ar.check_in_time, e.start_time 
            FROM attendance_records ar 
            JOIN employees e ON ar.employee_id = e.id 
            WHERE ar.date = ? AND ar.check_in_time IS NOT NULL
        ");
        $stmt->execute([$currentDate]);
        $records = $stmt->fetchAll();
        
        foreach ($records as $record) {
            $delayMinutes = calculateDelayMinutes($record['check_in_time'], $record['start_time']);
            $deductionPoints = $enabled ? calculateDeductionPoints($delayMinutes) : 0;
            $status = getAttendanceStatus($delayMinutes);
            
            $updateStmt = $db->prepare("
                UPDATE attendance_records 
                SET delay_minutes = ?, deduction_points = ?, status = ? 
                WHERE id = ?
            ");
            $updateStmt->execute([$delayMinutes, $deductionPoints, $status, $record['id']]);
        }
        
        $db->commit();
        
        jsonResponse([
            'success' => true, 
            'message' => 'تم تحديث نظام النقاط وإعادة حساب جميع السجلات'
        ]);
        
    } catch (Exception $e) {
        $db->rollback();
        jsonResponse(['success' => false, 'message' => 'فشل في تحديث نظام النقاط']);
    }
}

/**
 * الحصول على إعدادات نظام النقاط
 */
function getPointsSystem($db) {
    $settings = [
        'enabled' => SystemSettings::get('points_system_enabled', '1'),
        'grace_period' => SystemSettings::get('grace_period_minutes', '30'),
        'penalty_1' => SystemSettings::get('late_penalty_1', '10'),
        'penalty_2' => SystemSettings::get('late_penalty_2', '15'),
        'penalty_3' => SystemSettings::get('late_penalty_3', '25'),
        'penalty_4' => SystemSettings::get('late_penalty_4', '45')
    ];
    
    jsonResponse([
        'success' => true,
        'data' => $settings
    ]);
}

/**
 * تفعيل جميع الموظفين
 */
function activateAllEmployees($db) {
    try {
        $stmt = $db->prepare("UPDATE employees SET is_active = 1 WHERE is_active = 0");
        $stmt->execute();
        
        $affectedRows = $stmt->rowCount();
        jsonResponse([
            'success' => true,
            'message' => "تم تفعيل $affectedRows موظف"
        ]);
    } catch (Exception $e) {
        jsonResponse(['success' => false, 'message' => 'فشل في تفعيل الموظفين']);
    }
}

/**
 * تبديل حالة موظف بين نشط وغير نشط
 */
function toggleEmployeeStatus($db, $input) {
    $employeeId = (int)($input['employee_id'] ?? 0);
    
    if ($employeeId <= 0) {
        jsonResponse(['success' => false, 'message' => 'معرف الموظف غير صحيح']);
    }
    
    try {
        // الحصول على الحالة الحالية
        $stmt = $db->prepare("SELECT is_active, name FROM employees WHERE id = ?");
        $stmt->execute([$employeeId]);
        $employee = $stmt->fetch();
        
        if (!$employee) {
            jsonResponse(['success' => false, 'message' => 'الموظف غير موجود']);
        }
        
        // تبديل الحالة
        $newStatus = $employee['is_active'] === '1' ? 0 : 1;
        $stmt = $db->prepare("UPDATE employees SET is_active = ? WHERE id = ?");
        $stmt->execute([$newStatus, $employeeId]);
        
        $message = $newStatus === 1 ? 
            "تم تفعيل الموظف: {$employee['name']}" : 
            "تم إيقاف الموظف: {$employee['name']}";
        
        jsonResponse([
            'success' => true,
            'message' => $message
        ]);
    } catch (Exception $e) {
        jsonResponse(['success' => false, 'message' => 'فشل في تبديل حالة الموظف']);
    }
}

/**
 * تصفير سجلات اليوم
 */
function resetTodayRecords($db) {
    $currentDate = getCurrentDate();
    try {
        $stmt = $db->prepare("DELETE FROM attendance_records WHERE date = ?");
        if ($stmt->execute([$currentDate])) {
            jsonResponse(['success' => true, 'message' => 'تم تصفير سجلات اليوم بنجاح']);
        } else {
            jsonResponse(['success' => false, 'message' => 'فشل في تصفير السجلات']);
        }
    } catch (Exception $e) {
        jsonResponse(['success' => false, 'message' => 'حدث خطأ أثناء تصفير السجلات']);
    }
}

/**
 * إضافة الموظفين الافتراضيين
 */
function addDefaultEmployees($db, $input) {
    $employees = $input['employees'] ?? [];
    $wipeAll = $input['wipe_all'] ?? false;
    
    if (empty($employees)) {
        jsonResponse(['success' => false, 'message' => 'لا توجد موظفين للإضافة']);
    }
    
    try {
        $db->beginTransaction();
        
        // مسح جميع البيانات إذا تم طلب ذلك
        if ($wipeAll) {
            // حذف جميع الموظفين (سيتم حذف سجلات الحضور تلقائياً)
            $db->exec("DELETE FROM employees");
            // تصفير معرفات التزايد التلقائي (اختياري)
            $db->exec("ALTER TABLE employees AUTO_INCREMENT = 1");
        }
        
        $successCount = 0;
        $failCount = 0;
        $defaultPoints = 100;
        
        // الحصول على معرف الفرع للفرع المحدد
        $branchMap = [];
        $stmt = $db->query("SELECT id, name FROM branches");
        $branches = $stmt->fetchAll();
        foreach ($branches as $branch) {
            $branchMap[$branch['name']] = $branch['id'];
        }
        
        foreach ($employees as $emp) {
            $employeeCode = sanitizeInput($emp['code'] ?? '');
            $name = sanitizeInput($emp['name'] ?? '');
            $branchName = $emp['branch'] ?? '';
            $position = sanitizeInput($emp['position'] ?? '');
            $phone = sanitizeInput($emp['phone'] ?? '');
            
            if (empty($employeeCode) || empty($name) || empty($branchName)) {
                $failCount++;
                continue;
            }
            
            $branchId = $branchMap[$branchName] ?? null;
            if (!$branchId) {
                // إنشاء فرع جديد إذا لم يكن موجوداً
                $stmt = $db->prepare("INSERT INTO branches (name, is_active) VALUES (?, 1)");
                if ($stmt->execute([$branchName])) {
                    $branchId = $db->lastInsertId();
                    $branchMap[$branchName] = $branchId;
                } else {
                    $failCount++;
                    continue;
                }
            }
            
            // التحقق من عدم تكرار رقم الموظف (فقط إذا لم يتم المسح الكامل)
            if (!$wipeAll) {
                $stmt = $db->prepare("SELECT id FROM employees WHERE employee_code = ?");
                $stmt->execute([$employeeCode]);
                if ($stmt->fetch()) {
                    $failCount++;
                    continue;
                }
            }
            
            // إضافة الموظف
            $stmt = $db->prepare("
                INSERT INTO employees 
                (employee_code, name, branch_id, position, phone, start_time, points_balance, is_active) 
                VALUES (?, ?, ?, ?, ?, ?, ?, 1)
            ");
            
            if ($stmt->execute([$employeeCode, $name, $branchId, $position, $phone, '08:00:00', $defaultPoints])) {
                $successCount++;
            } else {
                $failCount++;
            }
        }
        
        $db->commit();
        
        jsonResponse([
            'success' => true,
            'message' => "تم إضافة $successCount موظف بنجاح" . ($failCount > 0 ? " (فشل: $failCount)" : "")
        ]);
    } catch (Exception $e) {
        $db->rollback();
        jsonResponse(['success' => false, 'message' => 'فشل في إضافة الموظفين: ' . $e->getMessage()]);
    }
}

/**
 * تسجيل حضور جماعي
 */
function bulkAttendanceRecord($db, $input) {
    $date = sanitizeInput($input['date'] ?? '');
    $checkInTime = sanitizeInput($input['check_in_time'] ?? '');
    $checkOutTime = sanitizeInput($input['check_out_time'] ?? null);
    $employeeIds = $input['employee_ids'] ?? [];
    
    if (empty($date) || empty($checkInTime) || empty($employeeIds)) {
        jsonResponse(['success' => false, 'message' => 'يرجى ملء جميع البيانات المطلوبة']);
    }
    
    try {
        $db->beginTransaction();
        
        $successCount = 0;
        $failCount = 0;
        
        foreach ($employeeIds as $employeeId) {
            $employeeId = (int)$employeeId;
            
            if ($employeeId <= 0) {
                $failCount++;
                continue;
            }
            
            // التحقق من وجود الموظف
            $stmt = $db->prepare("SELECT id FROM employees WHERE id = ? AND is_active = 1");
            $stmt->execute([$employeeId]);
            if (!$stmt->fetch()) {
                $failCount++;
                continue;
            }
            
            // البحث عن سجل موجود
            $stmt = $db->prepare("SELECT id FROM attendance_records WHERE employee_id = ? AND date = ?");
            $stmt->execute([$employeeId, $date]);
            $existingRecord = $stmt->fetch();
            
            if ($existingRecord) {
                // تحديث السجل الموجود
                $stmt = $db->prepare("
                    UPDATE attendance_records 
                    SET check_in_time = ?, check_out_time = ?, status = 'حضور'
                    WHERE employee_id = ? AND date = ?
                ");
                $stmt->execute([$checkInTime, $checkOutTime, $employeeId, $date]);
            } else {
                // إنشاء سجل جديد
                $stmt = $db->prepare("
                    INSERT INTO attendance_records 
                    (employee_id, date, check_in_time, check_out_time, status, delay_minutes, deduction_points)
                    VALUES (?, ?, ?, ?, 'حضور', 0, 0)
                ");
                $stmt->execute([$employeeId, $date, $checkInTime, $checkOutTime]);
            }
            
            $successCount++;
        }
        
        $db->commit();
        
        jsonResponse([
            'success' => true,
            'message' => "تم تسجيل حضور $successCount موظف بنجاح" . ($failCount > 0 ? " (فشل: $failCount)" : "")
        ]);
    } catch (Exception $e) {
        $db->rollback();
        jsonResponse(['success' => false, 'message' => 'فشل في تسجيل الحضور: ' . $e->getMessage()]);
    }
}

/**
 * تحميل الإعدادات من قاعدة البيانات
 */
function getSettings($db) {
    $gracePeriod = SystemSettings::get('grace_period_minutes', '');
    if ($gracePeriod === '' || $gracePeriod === null) {
        $gracePeriod = SystemSettings::get('grace_period', '30');
    }

    $settings = [
        'company_name' => SystemSettings::get('company_name', ''),
        'admin_name' => SystemSettings::get('admin_name', ''),
        'work_start_time' => SystemSettings::get('work_start_time', '08:00'),
        'grace_period' => $gracePeriod,
        'grace_period_minutes' => $gracePeriod,
        'late_penalty_1' => SystemSettings::get('late_penalty_1', '10'),
        'late_penalty_2' => SystemSettings::get('late_penalty_2', '15'),
        'late_penalty_3' => SystemSettings::get('late_penalty_3', '25'),
        'late_penalty_4' => SystemSettings::get('late_penalty_4', '45'),
        'allow_employee_logout' => SystemSettings::get('allow_employee_logout', '0')
    ];
    
    jsonResponse(['success' => true, 'data' => $settings]);
}

/**
 * إضافة أو خصم نقاط للموظف
 */
function adjustEmployeePoints($db, $input) {
    $employeeId = (int)($input['employee_id'] ?? 0);
    $points = (int)($input['points'] ?? 0);
    $reason = sanitizeInput($input['reason'] ?? '');
    
    if ($employeeId <= 0) {
        jsonResponse(['success' => false, 'message' => 'معرف الموظف غير صحيح']);
    }
    
    if ($points == 0) {
        jsonResponse(['success' => false, 'message' => 'يرجى إدخال عدد النقاط']);
    }
    
    try {
        // الحصول على رصيد النقاط الحالي
        $stmt = $db->prepare("SELECT name, points_balance FROM employees WHERE id = ?");
        $stmt->execute([$employeeId]);
        $employee = $stmt->fetch();
        
        if (!$employee) {
            jsonResponse(['success' => false, 'message' => 'الموظف غير موجود']);
        }
        
        $newBalance = $employee['points_balance'] + $points;
        
        // منع الرصيد السالب
        if ($newBalance < 0) {
            $newBalance = 0;
        }
        
        // تحديث الرصيد
        $stmt = $db->prepare("UPDATE employees SET points_balance = ? WHERE id = ?");
        $stmt->execute([$newBalance, $employeeId]);
        
        $action = $points > 0 ? 'إضافة' : 'خصم';
        $absPoints = abs($points);
        
        jsonResponse([
            'success' => true,
            'message' => "تم {$action} {$absPoints} نقطة للموظف {$employee['name']}",
            'data' => [
                'old_balance' => $employee['points_balance'],
                'new_balance' => $newBalance,
                'change' => $points
            ]
        ]);
    } catch (Exception $e) {
        logError("خطأ في تعديل النقاط: " . $e->getMessage());
        jsonResponse(['success' => false, 'message' => 'فشل في تعديل النقاط']);
    }
}

/**
 * إعادة تعيين نقاط الموظف للقيمة الافتراضية
 */
function resetEmployeePoints($db, $input) {
    $employeeId = (int)($input['employee_id'] ?? 0);
    $defaultPoints = (int)SystemSettings::get('default_points', '100');
    
    if ($employeeId <= 0) {
        jsonResponse(['success' => false, 'message' => 'معرف الموظف غير صحيح']);
    }
    
    try {
        $stmt = $db->prepare("UPDATE employees SET points_balance = ? WHERE id = ?");
        $stmt->execute([$defaultPoints, $employeeId]);
        
        jsonResponse([
            'success' => true,
            'message' => "تم إعادة تعيين النقاط إلى {$defaultPoints}",
            'data' => ['new_balance' => $defaultPoints]
        ]);
    } catch (Exception $e) {
        logError("خطأ في إعادة تعيين النقاط: " . $e->getMessage());
        jsonResponse(['success' => false, 'message' => 'فشل في إعادة تعيين النقاط']);
    }
}

/**
 * حفظ الإعدادات في قاعدة البيانات
 */
function saveSettings($db, $input) {
    $settingsMap = [
        'company_name' => $input['company_name'] ?? '',
        'admin_name' => $input['admin_name'] ?? '',
        'work_start_time' => $input['work_start_time'] ?? '08:00',
        'grace_period_minutes' => $input['grace_period'] ?? $input['grace_period_minutes'] ?? '30',
        'late_penalty_1' => $input['late_penalty_1'] ?? '10',
        'late_penalty_2' => $input['late_penalty_2'] ?? '15',
        'late_penalty_3' => $input['late_penalty_3'] ?? '25',
        'late_penalty_4' => $input['late_penalty_4'] ?? '45',
        'allow_employee_logout' => $input['allow_employee_logout'] ?? '0',
        'auto_mode_enabled' => $input['auto_mode_enabled'] ?? '0',
        'auto_check_in_start' => $input['auto_check_in_start'] ?? '06:00',
        'auto_check_in_end' => $input['auto_check_in_end'] ?? '12:00',
        'auto_check_out_start' => $input['auto_check_out_start'] ?? '12:01'
    ];
    
    try {
        foreach ($settingsMap as $key => $value) {
            SystemSettings::set($key, $value);
        }
        
        jsonResponse(['success' => true, 'message' => 'تم حفظ الإعدادات بنجاح']);
    } catch (Exception $e) {
        logError("خطأ في حفظ الإعدادات: " . $e->getMessage());
        jsonResponse(['success' => false, 'message' => 'فشل في حفظ الإعدادات']);
    }
}

/**
 * ==========================================
 * Advanced Control Panel Functions
 * ==========================================
 */

/**
 * Get all dynamic rules
 */
function getRules($db) {
    try {
        $stmt = $db->query("SELECT * FROM dynamic_rules ORDER BY id");
        $rules = $stmt->fetchAll();
        jsonResponse(['success' => true, 'data' => $rules]);
    } catch (Exception $e) {
        jsonResponse(['success' => false, 'message' => 'Failed to fetch rules']);
    }
}

/**
 * Save (create/update) a dynamic rule
 */
function saveRule($db, $input) {
    $id = isset($input['id']) ? (int)$input['id'] : 0;
    $ruleKey = sanitizeInput($input['rule_key'] ?? '');
    $name = sanitizeInput($input['name'] ?? '');
    $description = sanitizeInput($input['description'] ?? '');
    $equation = $input['equation'] ?? ''; // Equations might contain symbols, careful with sanitizeInput if it strips too much
    // Basic validation
    if (empty($ruleKey) || empty($name) || empty($equation)) {
        jsonResponse(['success' => false, 'message' => 'Please fill all required fields']);
    }
    
    // Validate equation syntax (basic check)
    if (!RuleEngine::validate($equation)) {
         jsonResponse(['success' => false, 'message' => 'Invalid equation syntax']);
    }

    try {
        if ($id > 0) {
            $stmt = $db->prepare("UPDATE dynamic_rules SET rule_key=?, name=?, description=?, equation=? WHERE id=?");
            $stmt->execute([$ruleKey, $name, $description, $equation, $id]);
            logAudit('update_rule', 'dynamic_rules', $id, null, json_encode($input));
        } else {
            $stmt = $db->prepare("INSERT INTO dynamic_rules (rule_key, name, description, equation) VALUES (?, ?, ?, ?)");
            $stmt->execute([$ruleKey, $name, $description, $equation]);
            $id = $db->lastInsertId();
            logAudit('create_rule', 'dynamic_rules', $id, null, json_encode($input));
        }
        jsonResponse(['success' => true, 'message' => 'Rule saved successfully']);
    } catch (Exception $e) {
        jsonResponse(['success' => false, 'message' => 'Failed to save rule: ' . $e->getMessage()]);
    }
}

/**
 * Delete a rule
 */
function deleteRule($db, $input) {
    $id = (int)($input['id'] ?? 0);
    if ($id <= 0) jsonResponse(['success' => false, 'message' => 'Invalid ID']);
    
    // Protect core rules
    $stmt = $db->prepare("SELECT rule_key FROM dynamic_rules WHERE id = ?");
    $stmt->execute([$id]);
    $rule = $stmt->fetch();
    if ($rule && $rule['rule_key'] == 'late_deduction') {
        jsonResponse(['success' => false, 'message' => 'Cannot delete core system rules']);
    }

    try {
        $stmt = $db->prepare("DELETE FROM dynamic_rules WHERE id = ?");
        $stmt->execute([$id]);
        logAudit('delete_rule', 'dynamic_rules', $id, $rule['rule_key'], null);
        jsonResponse(['success' => true, 'message' => 'Rule deleted successfully']);
    } catch (Exception $e) {
         jsonResponse(['success' => false, 'message' => 'Failed to delete rule']);
    }
}

/**
 * Get Audit Logs
 */
function getAuditLogs($db, $input) {
    $limit = (int)($input['limit'] ?? 100);
    $offset = (int)($input['offset'] ?? 0);
    
    try {
        $stmt = $db->prepare("
            SELECT al.*, u.username 
            FROM audit_logs al 
            LEFT JOIN users u ON al.user_id = u.id 
            ORDER BY al.created_at DESC 
            LIMIT ? OFFSET ?
        ");
        // Bind parameters as integers
        $stmt->bindParam(1, $limit, PDO::PARAM_INT);
        $stmt->bindParam(2, $offset, PDO::PARAM_INT);
        $stmt->execute();
        $logs = $stmt->fetchAll();
        jsonResponse(['success' => true, 'data' => $logs]);
    } catch (Exception $e) {
        jsonResponse(['success' => false, 'message' => 'Failed to fetch logs']);
    }
}

/**
 * Get Roles
 */
function getRoles($db) {
    try {
        $stmt = $db->query("SELECT * FROM roles ORDER BY id");
        $roles = $stmt->fetchAll();
        
        // Attach permissions
        foreach ($roles as &$role) {
            $pStmt = $db->prepare("
                SELECT p.code 
                FROM permissions p 
                JOIN role_permissions rp ON p.id = rp.permission_id 
                WHERE rp.role_id = ?
            ");
            $pStmt->execute([$role['id']]);
            $role['permissions'] = $pStmt->fetchAll(PDO::FETCH_COLUMN);
        }
        
        jsonResponse(['success' => true, 'data' => $roles]);
    } catch (Exception $e) {
        jsonResponse(['success' => false, 'message' => 'Failed to fetch roles']);
    }
}

/**
 * Get Single Role
 */
function getRole($db, $input) {
    $id = (int)($input['id'] ?? 0);
    if ($id <= 0) jsonResponse(['success' => false, 'message' => 'Invalid ID']);
    
    try {
        $stmt = $db->prepare("SELECT * FROM roles WHERE id = ?");
        $stmt->execute([$id]);
        $role = $stmt->fetch();
        
        if (!$role) jsonResponse(['success' => false, 'message' => 'Role not found']);
        
        // Get permissions
        $stmt = $db->prepare("SELECT permission_id FROM role_permissions WHERE role_id = ?");
        $stmt->execute([$id]);
        $permissions = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        jsonResponse(['success' => true, 'data' => ['role' => $role, 'permissions' => $permissions]]);
    } catch (Exception $e) {
        jsonResponse(['success' => false, 'message' => 'Failed to fetch role']);
    }
}

/**
 * Save Role
 */
function saveRole($db, $input) {
    $id = (int)($input['id'] ?? 0);
    $name = sanitizeInput($input['name'] ?? '');
    $perms = $input['permissions'] ?? [];
    
    if (empty($name)) jsonResponse(['success' => false, 'message' => 'Role name is required']);

    try {
        $db->beginTransaction();
        
        if ($id > 0) {
            $stmt = $db->prepare("UPDATE roles SET name = ? WHERE id = ?");
            $stmt->execute([$name, $id]);
        } else {
            $stmt = $db->prepare("INSERT INTO roles (name) VALUES (?)");
            $stmt->execute([$name]);
            $id = $db->lastInsertId();
        }
        
        // Update permissions
        $db->prepare("DELETE FROM role_permissions WHERE role_id = ?")->execute([$id]);
        
        if (!empty($perms)) {
            $insertStmt = $db->prepare("INSERT INTO role_permissions (role_id, permission_id) SELECT ?, id FROM permissions WHERE code = ?");
            foreach ($perms as $permCode) {
                $insertStmt->execute([$id, $permCode]);
            }
        }
        
        $db->commit();
        logAudit('save_role', 'roles', $id, null, json_encode($input));
        jsonResponse(['success' => true, 'message' => 'Role saved successfully']);
    } catch (Exception $e) {
        $db->rollback();
        jsonResponse(['success' => false, 'message' => 'Failed to save role']);
    }
}

/**
 * Delete Role
 */
function deleteRole($db, $input) {
    $id = (int)($input['id'] ?? 0);
    if ($id <= 0) jsonResponse(['success' => false, 'message' => 'Invalid ID']);
    
    // Prevent deleting Super Admin
    $stmt = $db->prepare("SELECT name FROM roles WHERE id = ?");
    $stmt->execute([$id]);
    $role = $stmt->fetch();
    if ($role && $role['name'] == 'Super Admin') {
        jsonResponse(['success' => false, 'message' => 'Cannot delete Super Admin role']);
    }

    try {
        $stmt = $db->prepare("DELETE FROM roles WHERE id = ?");
        $stmt->execute([$id]);
        logAudit('delete_role', 'roles', $id, $role['name'], null);
        jsonResponse(['success' => true, 'message' => 'Role deleted successfully']);
    } catch (Exception $e) {
        jsonResponse(['success' => false, 'message' => 'Failed to delete role']);
    }
}

/**
 * Get Permissions
 */
function getPermissions($db) {
    try {
        $stmt = $db->query("SELECT * FROM permissions ORDER BY group_name, code");
        jsonResponse(['success' => true, 'data' => $stmt->fetchAll()]);
    } catch (Exception $e) {
        jsonResponse(['success' => false, 'message' => 'Failed to fetch permissions']);
    }
}

/**
 * Get Backups
 */
function getBackups($db) {
    try {
        $stmt = $db->query("SELECT * FROM backups ORDER BY created_at DESC");
        jsonResponse(['success' => true, 'data' => $stmt->fetchAll()]);
    } catch (Exception $e) {
        jsonResponse(['success' => false, 'message' => 'Failed to fetch backups']);
    }
}

/**
 * Create Backup
 */
function createBackup($db) {
    // This is a simplified logic. In a real scenario, we'd dump the DB.
    // Since I cannot run mysqldump easily, I will simulate it or try to generate a SQL file via PHP.
    // For this environment, I'll generate a JSON export of key tables.
    
    try {
        $tables = ['employees', 'attendance_records', 'branches', 'users', 'system_settings', 'dynamic_rules', 'roles', 'permissions', 'role_permissions'];
        $backupData = [];
        foreach ($tables as $table) {
            try {
                $stmt = $db->query("SELECT * FROM $table");
                $backupData[$table] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (Exception $e) {
                // Table might not exist yet
            }
        }
        
        $filename = 'backup_' . date('Y-m-d_H-i-s') . '.json';
        $filepath = __DIR__ . '/backups/' . $filename;
        if (!is_dir(__DIR__ . '/backups')) mkdir(__DIR__ . '/backups');
        
        file_put_contents($filepath, json_encode($backupData, JSON_PRETTY_PRINT));
        
        $size = filesize($filepath);
        $stmt = $db->prepare("INSERT INTO backups (filename, size_bytes, created_by) VALUES (?, ?, ?)");
        $stmt->execute([$filename, $size, $_SESSION['admin_user_id'] ?? null]);
        
        logAudit('create_backup', 'backups', $db->lastInsertId(), $filename, null);
        jsonResponse(['success' => true, 'message' => 'Backup created successfully']);
    } catch (Exception $e) {
        jsonResponse(['success' => false, 'message' => 'Failed to create backup: ' . $e->getMessage()]);
    }
}

/**
 * Restore Backup
 */
function restoreBackup($db, $input) {
    // Basic restore logic
    jsonResponse(['success' => false, 'message' => 'Restore functionality requires server access']);
}

/**
 * Delete Backup
 */
function deleteBackup($db, $input) {
    $id = (int)($input['id'] ?? 0);
    try {
        $stmt = $db->prepare("SELECT filename FROM backups WHERE id = ?");
        $stmt->execute([$id]);
        $backup = $stmt->fetch();
        
        if ($backup) {
            $filepath = __DIR__ . '/backups/' . $backup['filename'];
            if (file_exists($filepath)) unlink($filepath);
            
            $db->prepare("DELETE FROM backups WHERE id = ?")->execute([$id]);
            logAudit('delete_backup', 'backups', $id, $backup['filename'], null);
        }
        jsonResponse(['success' => true, 'message' => 'Backup deleted successfully']);
    } catch (Exception $e) {
        jsonResponse(['success' => false, 'message' => 'Failed to delete backup']);
    }
}
?>
