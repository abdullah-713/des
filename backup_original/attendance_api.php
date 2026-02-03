<?php
define('API_REQUEST', true);
/**
 * واجهة برمجة التطبيقات لنظام الحضور
 * API للتعامل مع عمليات الحضور والانصراف
 */

require_once 'config.php';

header('X-Content-Type-Options: nosniff');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

ob_start();
set_exception_handler(function ($e) {
    logError("خطأ غير معالج في API الحضور: " . $e->getMessage());
    apiJsonError('حدث خطأ في النظام');
});
set_error_handler(function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});
register_shutdown_function(function () {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) {
        logError("خطأ قاتل في API الحضور: " . $error['message']);
        apiJsonError('حدث خطأ في النظام');
    }
});

// التأكد من أن الطلب POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'طريقة الطلب غير صحيحة'], 405);
}

// الحصول على البيانات المرسلة
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}
$action = $input['action'] ?? '';

try {
    $db = Database::getInstance()->getConnection();
    
    switch ($action) {
        case 'check_in':
            handleCheckIn($db, $input);
            break;
            
        case 'check_out':
            handleCheckOut($db, $input);
            break;
            
        case 'get_stats':
            getAttendanceStats($db);
            break;
            
        case 'get_employee_status':
            getEmployeeStatus($db, $input);
            break;

        case 'get_profile_data':
            getProfileData($db, $input);
            break;

        case 'update_password':
            updatePassword($db, $input);
            break;

        case 'upload_profile_image':
            uploadProfileImage($db, $input);
            break;
            
        case 'get_system_status':
            getSystemStatus($db);
            break;
            
        case 'get_banner_employees':
            getBannerEmployees($db);
            break;
            
        default:
            jsonResponse(['success' => false, 'message' => 'عملية غير معروفة'], 400);
    }
    
} catch (Exception $e) {
    logError("خطأ في API الحضور: " . $e->getMessage());
    jsonResponse(['success' => false, 'message' => 'حدث خطأ في النظام'], 500);
}

/**
 * تسجيل الحضور
 */
function handleCheckIn($db, $input) {
    $employeeCode = sanitizeInput($input['employee_code'] ?? '');
    
    if (empty($employeeCode)) {
        jsonResponse(['success' => false, 'message' => 'رقم الموظف مطلوب']);
    }
    
    // التحقق من تفعيل نظام الحضور
    if (!SystemSettings::get('attendance_enabled', '1')) {
        jsonResponse(['success' => false, 'message' => 'نظام الحضور غير مفعل حالياً']);
    }
    
    // التحقق من وضع النظام
    $attendanceMode = SystemSettings::get('attendance_mode', 'check_in');
    if ($attendanceMode !== 'check_in') {
        jsonResponse(['success' => false, 'message' => 'النظام في وضع تسجيل الانصراف']);
    }
    
    // البحث عن الموظف
    $stmt = $db->prepare("
        SELECT e.*, b.name as branch_name 
        FROM employees e 
        JOIN branches b ON e.branch_id = b.id 
        WHERE e.employee_code = ? AND e.is_active = 1
    ");
    $stmt->execute([$employeeCode]);
    $employee = $stmt->fetch();
    
    if (!$employee) {
        jsonResponse(['success' => false, 'message' => 'رقم الموظف غير صحيح أو غير مفعل']);
    }
    
    $currentDate = getCurrentDate();
    $currentTime = getCurrentTime();

    // === V2: Granular Scheduling Logic ===
    // Check if there is a custom weekly schedule for today
    $startTime = $employee['start_time']; // Default global start time
    
    if (!empty($employee['weekly_schedule'])) {
        $schedule = json_decode($employee['weekly_schedule'], true);
        $todayName = strtolower(date('D')); // mon, tue, wed...
        
        // If today exists in schedule and has a start time
        if (isset($schedule[$todayName]) && !empty($schedule[$todayName]['start'])) {
            $startTime = $schedule[$todayName]['start'];
            
            // Optional: If 'off' is true, block check-in (Day Off)
            if (isset($schedule[$todayName]['is_off']) && $schedule[$todayName]['is_off']) {
                jsonResponse(['success' => false, 'message' => 'عذراً، هذا اليوم إجازة حسب جدولك المخصص']);
            }
        }
    }
    // === End V2 Logic ===
    
    // التحقق من وجود سجل حضور لليوم
    $stmt = $db->prepare("
        SELECT * FROM attendance_records 
        WHERE employee_id = ? AND date = ?
    ");
    $stmt->execute([$employee['id'], $currentDate]);
    $existingRecord = $stmt->fetch();
    
    $isAutoPlaceholder = false;
    if ($existingRecord && $existingRecord['check_in_time']) {
        $isAutoPlaceholder = $existingRecord['check_in_time'] === '08:00:00'
            && (int)$existingRecord['delay_minutes'] === 0
            && (int)$existingRecord['deduction_points'] === 0
            && empty($existingRecord['check_out_time'])
            && $existingRecord['status'] === 'حضور';
    }

    if ($existingRecord && $existingRecord['check_in_time'] && !$isAutoPlaceholder) {
        jsonResponse([
            'success' => false, 
            'message' => 'تم تسجيل الحضور مسبقاً اليوم في ' . formatTime($existingRecord['check_in_time'], 'H:i')
        ]);
    }
    
    // حساب التأخير والنقاط
    // $startTime is already set from V2 Logic above
    $delayMinutes = calculateDelayMinutes($currentTime, $startTime);
    $earlyMinutes = calculateEarlyMinutes($currentTime, $startTime);
    
    $deductionPoints = calculateDeductionPoints($delayMinutes);
    $rewardPoints = calculateRewardPoints($earlyMinutes);
    
    $status = getAttendanceStatus($delayMinutes, $earlyMinutes);
    
    // حساب فرق النقاط لتحديث الرصيد (إصلاح خطأ تكرار النقاط)
    $oldNetPoints = 0;
    if ($existingRecord) {
        $oldNetPoints = ($existingRecord['reward_points'] ?? 0) - ($existingRecord['deduction_points'] ?? 0);
    }
    
    $newNetPoints = $rewardPoints - $deductionPoints;
    $pointsDiff = $newNetPoints - $oldNetPoints;
    
    // إدراج أو تحديث سجل الحضور
    if ($existingRecord) {
        $stmt = $db->prepare("
            UPDATE attendance_records 
            SET check_in_time = ?, delay_minutes = ?, early_minutes = ?, deduction_points = ?, reward_points = ?, status = ?
            WHERE id = ?
        ");
        $stmt->execute([$currentTime, $delayMinutes, $earlyMinutes, $deductionPoints, $rewardPoints, $status, $existingRecord['id']]);
    } else {
        $stmt = $db->prepare("
            INSERT INTO attendance_records 
            (employee_id, date, check_in_time, delay_minutes, early_minutes, deduction_points, reward_points, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$employee['id'], $currentDate, $currentTime, $delayMinutes, $earlyMinutes, $deductionPoints, $rewardPoints, $status]);
    }
    
    // تحديث رصيد نقاط الموظف بالفارق فقط
    if ($pointsDiff != 0) {
        $stmt = $db->prepare("UPDATE employees SET points_balance = points_balance + ? WHERE id = ?");
        $stmt->execute([$pointsDiff, $employee['id']]);
    }
    
    jsonResponse([
        'success' => true,
        'message' => 'تم تسجيل الحضور بنجاح',
        'data' => [
            'employee_name' => $employee['name'],
            'branch_name' => $employee['branch_name'],
            'check_in_time' => formatTime($currentTime, 'H:i'),
            'delay_minutes' => $delayMinutes,
            'early_minutes' => $earlyMinutes,
            'deduction_points' => $deductionPoints,
            'reward_points' => $rewardPoints,
            'status' => $status
        ]
    ]);
}

/**
 * تسجيل الانصراف
 */
function handleCheckOut($db, $input) {
    $employeeCode = sanitizeInput($input['employee_code'] ?? '');
    
    if (empty($employeeCode)) {
        jsonResponse(['success' => false, 'message' => 'رقم الموظف مطلوب']);
    }
    
    // التحقق من تفعيل نظام الحضور
    if (!SystemSettings::get('attendance_enabled', '1')) {
        jsonResponse(['success' => false, 'message' => 'نظام الحضور غير مفعل حالياً']);
    }
    
    // التحقق من وضع النظام
    $attendanceMode = SystemSettings::get('attendance_mode', 'check_in');
    if ($attendanceMode !== 'check_out') {
        jsonResponse(['success' => false, 'message' => 'النظام في وضع تسجيل الحضور']);
    }
    
    // البحث عن الموظف
    $stmt = $db->prepare("
        SELECT e.*, b.name as branch_name 
        FROM employees e 
        JOIN branches b ON e.branch_id = b.id 
        WHERE e.employee_code = ? AND e.is_active = 1
    ");
    $stmt->execute([$employeeCode]);
    $employee = $stmt->fetch();
    
    if (!$employee) {
        jsonResponse(['success' => false, 'message' => 'رقم الموظف غير صحيح أو غير مفعل']);
    }
    
    $currentDate = getCurrentDate();
    $currentTime = getCurrentTime();
    
    // البحث عن سجل الحضور لليوم
    $stmt = $db->prepare("
        SELECT * FROM attendance_records 
        WHERE employee_id = ? AND date = ?
    ");
    $stmt->execute([$employee['id'], $currentDate]);
    $record = $stmt->fetch();
    
    if (!$record || !$record['check_in_time']) {
        jsonResponse(['success' => false, 'message' => 'لم يتم تسجيل الحضور اليوم']);
    }
    
    if ($record['check_out_time']) {
        jsonResponse([
            'success' => false, 
            'message' => 'تم تسجيل الانصراف مسبقاً في ' . formatTime($record['check_out_time'], 'H:i')
        ]);
    }
    
    // حساب العمل الإضافي والمكافآت
    $endTime = $employee['end_time'] ?? '17:00:00';
    $overtimeMinutes = calculateOvertimeMinutes($currentTime, $endTime);
    $rewardPoints = calculateRewardPoints($overtimeMinutes);
    
    // حساب فرق النقاط (إصلاح خطأ التكرار)
    $oldRewardPoints = $record['reward_points'] ?? 0;
    $pointsDiff = $rewardPoints - $oldRewardPoints; // هنا نضيف فقط نقاط المكافأة الجديدة لأن الانصراف يضيف مكافآت فقط
    
    // تحديث سجل الانصراف
    $stmt = $db->prepare("
        UPDATE attendance_records 
        SET check_out_time = ?, overtime_minutes = ?, reward_points = reward_points + ? 
        WHERE id = ?
    ");
    // ملاحظة: reward_points في الجدول هو المجموع، لذا في الاستعلام نجمع الفرق، أو نعيد تعيين القيمة
    // الأفضل إعادة تعيين القيمة الإجمالية للمكافآت (حضور مبكر + انصراف متأخر)
    // لكن هنا نحن نتعامل مع منطق معقد قليلاً لأن المكافآت تأتي من الحضور ومن الانصراف
    // سنقوم بتحديث القيمة بدلاً من الجمع لتجنب المشاكل
    
    // الحل الأفضل: قراءة القيمة الحالية للمكافآت من الحضور المبكر
    $earlyRewardPoints = 0;
    // نحتاج معرفة كم كانت نقاط التبكير للحفاظ عليها
    // في handleCheckIn حسبنا reward_points بناء على التبكير.
    // لذا reward_points في قاعدة البيانات حالياً هي نقاط التبكير.
    // المجموع الجديد = نقاط التبكير (الموجودة أصلاً) + نقاط العمل الإضافي (الجديدة)
    // ولكن مهلاً، إذا كان المستخدم سجل انصراف سابقاً، فإن reward_points تشمل التبكير + الإضافي القديم
    
    // إعادة حساب كل شيء لضمان الدقة
    $startTime = $employee['start_time'];
    $earlyMinutes = calculateEarlyMinutes($record['check_in_time'], $startTime);
    $earlyRewardPoints = calculateRewardPoints($earlyMinutes);
    
    $totalRewardPoints = $earlyRewardPoints + $rewardPoints;
    
    // الفرق في النقاط الكلية للموظف
    $currentDbReward = $record['reward_points'] ?? 0;
    $employeePointsDiff = $totalRewardPoints - $currentDbReward;
    
    $stmt = $db->prepare("
        UPDATE attendance_records 
        SET check_out_time = ?, overtime_minutes = ?, reward_points = ?
        WHERE id = ?
    ");
    $stmt->execute([$currentTime, $overtimeMinutes, $totalRewardPoints, $record['id']]);
    
    // تحديث رصيد نقاط الموظف
    if ($employeePointsDiff != 0) {
        $stmt = $db->prepare("UPDATE employees SET points_balance = points_balance + ? WHERE id = ?");
        $stmt->execute([$employeePointsDiff, $employee['id']]);
    }
    
    jsonResponse([
        'success' => true,
        'message' => 'تم تسجيل الانصراف بنجاح',
        'data' => [
            'employee_name' => $employee['name'],
            'branch_name' => $employee['branch_name'],
            'check_out_time' => formatTime($currentTime, 'H:i'),
            'overtime_minutes' => $overtimeMinutes,
            'reward_points' => $rewardPoints,
            'work_hours' => calculateWorkHours($record['check_in_time'], $currentTime)
        ]
    ]);
}

/**
 * الحصول على إحصائيات الحضور
 */
function getAttendanceStats($db) {
    $currentDate = getCurrentDate();
    
    // إحصائيات عامة
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total_employees,
            SUM(CASE WHEN ar.check_in_time IS NOT NULL THEN 1 ELSE 0 END) as present_count,
            SUM(CASE WHEN ar.delay_minutes > 30 THEN 1 ELSE 0 END) as late_count,
            SUM(CASE WHEN ar.delay_minutes > 0 THEN ar.delay_minutes ELSE 0 END) as total_delay_minutes,
            SUM(CASE WHEN ar.deduction_points > 0 THEN ar.deduction_points ELSE 0 END) as total_deductions,
            SUM(ar.reward_points) as total_reward_points,
            (SELECT SUM(points_balance) FROM employees WHERE is_active = 1) as points_balance
        FROM employees e
        LEFT JOIN attendance_records ar ON e.id = ar.employee_id AND ar.date = ?
        WHERE e.is_active = 1
    ");
    $stmt->execute([$currentDate]);
    $generalStats = $stmt->fetch();
    
    // إحصائيات الفروع (5 فروع فقط)
    $stmt = $db->prepare("
        SELECT 
            b.id,
            b.name,
            COUNT(e.id) as total_employees,
            SUM(CASE WHEN ar.check_in_time IS NOT NULL THEN 1 ELSE 0 END) as present_count,
            SUM(CASE WHEN ar.delay_minutes > 30 THEN 1 ELSE 0 END) as late_count,
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
    
    // حساب نسبة الحضور (الموظفين الذين لم يتأخروا)
    $onTimeCount = $generalStats['present_count'] - $generalStats['late_count'];
    $attendanceRate = $generalStats['total_employees'] > 0 ? 
        round(($onTimeCount / $generalStats['total_employees']) * 100) : 0;
    
    jsonResponse([
        'success' => true,
        'data' => [
            'general' => array_merge($generalStats, ['attendance_rate' => $attendanceRate]),
            'branches' => $branchStats,
            'date' => formatDate($currentDate, 'd/m/Y')
        ]
    ]);
}

/**
 * الحصول على حالة موظف معين
 */
function getEmployeeStatus($db, $input) {
    $employeeCode = sanitizeInput($input['employee_code'] ?? '');
    
    if (empty($employeeCode)) {
        jsonResponse(['success' => false, 'message' => 'رقم الموظف مطلوب']);
    }
    
    $currentDate = getCurrentDate();
    
    $stmt = $db->prepare("
        SELECT 
            e.name, e.employee_code, e.position,
            b.name as branch_name,
            ar.check_in_time, ar.check_out_time, ar.delay_minutes, 
            ar.deduction_points, ar.status
        FROM employees e
        JOIN branches b ON e.branch_id = b.id
        LEFT JOIN attendance_records ar ON e.id = ar.employee_id AND ar.date = ?
        WHERE e.employee_code = ? AND e.is_active = 1
    ");
    $stmt->execute([$currentDate, $employeeCode]);
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
 * الحصول على حالة النظام
 */
function getSystemStatus($db) {
    // الحصول على البيانات المرسلة
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) $input = $_POST;
    
    $employeeCode = $input['employee_code'] ?? null;
    
    // التحقق من الوضع التلقائي
    $autoMode = SystemSettings::get('auto_mode_enabled', '0');
    $currentMode = SystemSettings::get('attendance_mode', 'check_in');
    
    // إعدادات افتراضية
    $checkInStart = SystemSettings::get('auto_check_in_start', '06:00');
    $checkInEnd = SystemSettings::get('auto_check_in_end', '12:00');
    $checkOutStart = SystemSettings::get('auto_check_out_start', '12:01');
    
    // إذا تم تمرير كود موظف، تحقق من الإعدادات المخصصة
    if ($employeeCode) {
        $stmt = $db->prepare("SELECT custom_check_in_start, custom_check_in_end, custom_check_out_start FROM employees WHERE employee_code = ?");
        $stmt->execute([$employeeCode]);
        $empSettings = $stmt->fetch();
        
        if ($empSettings) {
            if (!empty($empSettings['custom_check_in_start'])) $checkInStart = $empSettings['custom_check_in_start'];
            if (!empty($empSettings['custom_check_in_end'])) $checkInEnd = $empSettings['custom_check_in_end'];
            if (!empty($empSettings['custom_check_out_start'])) $checkOutStart = $empSettings['custom_check_out_start'];
        }
    }
    
    if ($autoMode === '1') {
        $now = date('H:i');
        
        // منطق بسيط: إذا كان الوقت ضمن فترة الحضور، فهو حضور. وإلا إذا كان بعد بداية الانصراف، فهو انصراف.
        $newMode = $currentMode;
        
        if ($now >= $checkInStart && $now <= $checkInEnd) {
            $newMode = 'check_in';
        } elseif ($now >= $checkOutStart) {
            $newMode = 'check_out';
        }
        
        // إذا كان الطلب لموظف محدد، نعيد الوضع المحسوب له فقط دون تحديث النظام العام
        if ($employeeCode) {
            $currentMode = $newMode;
        } else {
            // تحديث الوضع العام إذا تغير (فقط للطلبات العامة)
            if ($newMode !== $currentMode) {
                SystemSettings::set('attendance_mode', $newMode);
                $currentMode = $newMode;
            }
        }
    }

    $settings = [
        'attendance_enabled' => SystemSettings::get('attendance_enabled', '1'),
        'attendance_mode' => $currentMode,
        'auto_mode_enabled' => $autoMode,
        'company_name' => SystemSettings::get('company_name', 'صرح الإتقان'),
        'work_start_time' => SystemSettings::get('work_start_time', '08:00'),
        'registration_closed' => SystemSettings::get('registration_closed', '0'),
        'announcement_visible' => SystemSettings::get('announcement_visible', '0'),
        'announcement_text' => SystemSettings::get('announcement_text', '')
    ];
    
    jsonResponse([
        'success' => true,
        'data' => $settings
    ]);
}

/**
 * حساب ساعات العمل
 */
function calculateWorkHours($checkIn, $checkOut) {
    $start = strtotime($checkIn);
    $end = strtotime($checkOut);
    
    if ($end <= $start) {
        return '0:00';
    }
    
    $diff = $end - $start;
    $hours = floor($diff / 3600);
    $minutes = floor(($diff % 3600) / 60);
    
    return sprintf('%d:%02d', $hours, $minutes);
}

/**
 * الحصول على الموظفين للشريط العلوي
 * المميزين: أول مسجل من كل فرع (يظهر فور تسجيل أول شخص)
 * السلاحف: آخر مسجل من كل فرع (يظهر فقط عند قفل التسجيل من المدير)
 * إهمال الفرع إذا تساوى وقتان
 */
function getBannerEmployees($db) {
    $currentDate = getCurrentDate();
    $registrationClosed = SystemSettings::get('registration_closed', '0') === '1';
    $allEmployees = [];
    
    $branchesStmt = $db->prepare("
        SELECT id, name FROM branches 
        WHERE is_active = 1
        ORDER BY name
    ");
    $branchesStmt->execute();
    $branches = $branchesStmt->fetchAll();
    
    foreach ($branches as $branch) {
        $branchId = $branch['id'];
        $branchName = $branch['name'];
        
        if ($registrationClosed) {
            // السلاحف: آخر مسجل من كل فرع (أعلى وقت حضور = آخر من وصل)
            $stmt = $db->prepare("
                SELECT e.name, ar.check_in_time, ar.deduction_points
                FROM attendance_records ar
                JOIN employees e ON ar.employee_id = e.id
                WHERE ar.date = ? AND e.branch_id = ? 
                AND ar.check_in_time IS NOT NULL
                ORDER BY ar.check_in_time DESC
                LIMIT 2
            ");
            $stmt->execute([$currentDate, $branchId]);
            $lastTwo = $stmt->fetchAll();
            
            if (count($lastTwo) === 1) {
                $allEmployees[] = [
                    'name' => $lastTwo[0]['name'],
                    'branch_name' => $branchName,
                    'deduction_points' => $lastTwo[0]['deduction_points'],
                    'type' => 'worst'
                ];
            } elseif (count($lastTwo) === 2 && $lastTwo[0]['check_in_time'] !== $lastTwo[1]['check_in_time']) {
                $allEmployees[] = [
                    'name' => $lastTwo[0]['name'],
                    'branch_name' => $branchName,
                    'deduction_points' => $lastTwo[0]['deduction_points'],
                    'type' => 'worst'
                ];
            }
        } else {
            // المميزين: أول مسجل من كل فرع أو من بكر جداً
            $stmt = $db->prepare("
                SELECT e.name, ar.check_in_time, ar.reward_points, ar.status
                FROM attendance_records ar
                JOIN employees e ON ar.employee_id = e.id
                WHERE ar.date = ? AND e.branch_id = ? 
                AND ar.check_in_time IS NOT NULL
                ORDER BY ar.early_minutes DESC, ar.check_in_time ASC
                LIMIT 1
            ");
            $stmt->execute([$currentDate, $branchId]);
            $best = $stmt->fetch();
            
            if ($best) {
                $allEmployees[] = [
                    'name' => $best['name'],
                    'branch_name' => $branchName,
                    'reward_points' => $best['reward_points'],
                    'status' => $best['status'],
                    'type' => 'best'
                ];
            }
        }
    }
    
    jsonResponse([
        'success' => true,
        'data' => $allEmployees
    ]);
}

/**
 * الحصول على بيانات الملف الشخصي
 */
function getProfileData($db, $input) {
    $employeeCode = sanitizeInput($input['employee_code'] ?? '');
    
    if (empty($employeeCode)) {
        jsonResponse(['success' => false, 'message' => 'رقم الموظف مطلوب']);
    }
    
    // بيانات الموظف الأساسية
    $stmt = $db->prepare("
        SELECT e.*, b.name as branch_name 
        FROM employees e 
        JOIN branches b ON e.branch_id = b.id 
        WHERE e.employee_code = ? AND e.is_active = 1
    ");
    $stmt->execute([$employeeCode]);
    $employee = $stmt->fetch();
    
    if (!$employee) {
        jsonResponse(['success' => false, 'message' => 'الموظف غير موجود']);
    }
    
    // إحصائيات الحضور
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total_days,
            SUM(CASE WHEN delay_minutes > 0 THEN 1 ELSE 0 END) as late_days,
            SUM(delay_minutes) as total_delay_minutes,
            SUM(deduction_points) as total_deductions,
            SUM(reward_points) as total_rewards
        FROM attendance_records 
        WHERE employee_id = ?
    ");
    $stmt->execute([$employee['id']]);
    $stats = $stmt->fetch();
    
    // الترتيب على مستوى الفرع
    $stmt = $db->prepare("
        SELECT COUNT(*) + 1 as rank
        FROM employees 
        WHERE branch_id = ? AND points_balance > ? AND is_active = 1
    ");
    $stmt->execute([$employee['branch_id'], $employee['points_balance']]);
    $rank = $stmt->fetch()['rank'];
    
    // التقييم (النجوم)
    $maxPoints = 100; // افتراضي
    $rating = min(5, max(1, round(($employee['points_balance'] / $maxPoints) * 5)));
    
    // إخفاء كلمة المرور
    unset($employee['password']);
    
    jsonResponse([
        'success' => true,
        'data' => [
            'employee' => $employee,
            'stats' => $stats,
            'rank' => $rank,
            'rating' => $rating
        ]
    ]);
}

/**
 * تحديث كلمة المرور
 */
function updatePassword($db, $input) {
    $employeeCode = sanitizeInput($input['employee_code'] ?? '');
    $password = $input['password'] ?? '';
    
    if (empty($employeeCode) || empty($password)) {
        jsonResponse(['success' => false, 'message' => 'البيانات غير مكتملة']);
    }
    
    $hash = password_hash($password, PASSWORD_DEFAULT);
    
    $stmt = $db->prepare("UPDATE employees SET password = ? WHERE employee_code = ?");
    if ($stmt->execute([$hash, $employeeCode])) {
        jsonResponse(['success' => true, 'message' => 'تم تحديث كلمة المرور بنجاح']);
    } else {
        jsonResponse(['success' => false, 'message' => 'فشل تحديث كلمة المرور']);
    }
}

/**
 * رفع صورة الملف الشخصي
 */
function uploadProfileImage($db, $input) {
    $employeeCode = sanitizeInput($input['employee_code'] ?? '');
    
    if (empty($employeeCode)) {
        jsonResponse(['success' => false, 'message' => 'رقم الموظف مطلوب']);
    }
    
    if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        jsonResponse(['success' => false, 'message' => 'فشل رفع الصورة']);
    }
    
    $file = $_FILES['image'];
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    
    if (!in_array($file['type'], $allowedTypes)) {
        jsonResponse(['success' => false, 'message' => 'نوع الملف غير مدعوم']);
    }
    
    if ($file['size'] > 5 * 1024 * 1024) { // 5MB
        jsonResponse(['success' => false, 'message' => 'حجم الصورة كبير جداً']);
    }
    
    $uploadDir = 'uploads/profiles/';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = $employeeCode . '_' . time() . '.' . $extension;
    $targetPath = $uploadDir . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        $stmt = $db->prepare("UPDATE employees SET profile_image = ? WHERE employee_code = ?");
        $stmt->execute([$targetPath, $employeeCode]);
        
        jsonResponse([
            'success' => true, 
            'message' => 'تم تحديث الصورة الشخصية',
            'image_url' => $targetPath
        ]);
    } else {
        jsonResponse(['success' => false, 'message' => 'حدث خطأ أثناء حفظ الصورة']);
    }
}

?>
