<?php
require_once 'config.php';

// إعادة توليد معرف الجلسة بشكل دوري للحماية من Session Fixation
if (!isset($_SESSION['created'])) {
    $_SESSION['created'] = time();
} else if (time() - $_SESSION['created'] > 18000) { // 30 دقيقة
    session_regenerate_id(true);
    $_SESSION['created'] = time();
}

// معالجة تسجيل الخروج
if (isset($_GET['logout'])) {
    // تدمير الجلسة بشكل كامل
    $_SESSION = array();
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
    header('Location: admin.php');
    exit;
}

// دالة للتحقق من كلمة المرور
function verifyAdminLogin($username, $password) {
    try {
        $db = Database::getInstance()->getConnection();
        
        // البحث عن المستخدم
        $stmt = $db->prepare("SELECT id, password, role, is_active FROM users WHERE username = ? LIMIT 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        if ($user && $user['is_active'] && in_array($user['role'], ['admin', 'manager'])) {
            if (password_verify($password, $user['password'])) {
                $stmt = $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                $stmt->execute([$user['id']]);
                return $user;
            }
            return false;
        }

        $stmt = $db->query("SELECT COUNT(*) as total FROM users");
        $row = $stmt->fetch();
        $totalUsers = (int)($row['total'] ?? 0);

        if ($totalUsers === 0 && $username === 'admin' && $password === 'password') {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $db->prepare("INSERT INTO users (username, password, role, is_active) VALUES (?, ?, 'admin', 1)");
            $stmt->execute([$username, $hash]);
            $userId = $db->lastInsertId();
            $stmt = $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
            $stmt->execute([$userId]);
            return [
                'id' => $userId,
                'password' => $hash,
                'role' => 'admin',
                'is_active' => 1
            ];
        }

        return false;
    } catch (Exception $e) {
        logError('Login error: ' . $e->getMessage());
        return false;
    }
}

// حماية من هجمات Brute Force
function checkLoginAttempts() {
    $maxAttempts = 5;
    $lockoutTime = 900; // 15 دقيقة
    
    if (!isset($_SESSION['login_attempts'])) {
        $_SESSION['login_attempts'] = 0;
        $_SESSION['first_attempt_time'] = time();
    }
    
    // إعادة تعيين المحاولات بعد انتهاء فترة الحظر
    if (isset($_SESSION['first_attempt_time']) && 
        (time() - $_SESSION['first_attempt_time']) > $lockoutTime) {
        $_SESSION['login_attempts'] = 0;
        $_SESSION['first_attempt_time'] = time();
    }
    
    return $_SESSION['login_attempts'] < $maxAttempts;
}

function recordLoginAttempt($success) {
    if ($success) {
        $_SESSION['login_attempts'] = 0;
    } else {
        $_SESSION['login_attempts'] = ($_SESSION['login_attempts'] ?? 0) + 1;
    }
}

$loginError = '';
$loginLocked = false;

// التحقق من تسجيل الدخول
if (!isset($_SESSION['admin_logged_in'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['username'], $_POST['password'])) {
        // التحقق من CSRF token
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
            $loginError = 'خطأ في التحقق من الأمان. يرجى إعادة المحاولة.';
        } else if (!checkLoginAttempts()) {
            $loginLocked = true;
            $loginError = 'تم تجاوز عدد المحاولات المسموح بها. يرجى الانتظار 15 دقيقة.';
        } else {
            $username = trim($_POST['username']);
            $password = $_POST['password'];
            
            $user = verifyAdminLogin($username, $password);
            if ($user) {
                recordLoginAttempt(true);
                session_regenerate_id(true); // حماية من Session Fixation
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_user_id'] = $user['id'];
                $_SESSION['admin_role'] = $user['role'];
                $_SESSION['created'] = time();
                
                // إعادة التوجيه لمنع إعادة الإرسال
                header('Location: admin.php');
                exit;
            } else {
                recordLoginAttempt(false);
                $remainingAttempts = 5 - ($_SESSION['login_attempts'] ?? 0);
                $loginError = "اسم المستخدم أو كلمة المرور غير صحيحة. (محاولات متبقية: $remainingAttempts)";
            }
        }
    }
}

// توليد CSRF token جديد
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if (!isset($_SESSION['admin_logged_in'])) {
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes, viewport-fit=cover">
    <meta name="theme-color" content="#6366f1">
    <meta name="color-scheme" content="light dark">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title>تسجيل الدخول - لوحة الإدارة</title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800;900&display=swap" rel="stylesheet">
    <link href="assets/css/login.css?v=<?php echo time(); ?>" rel="stylesheet">
    <script src="assets/js/pwa.js" defer></script>
</head>
<body lang="<?php echo $_SESSION['lang'] ?? 'ar'; ?>" dir="<?php echo ($_SESSION['lang'] ?? 'ar') === 'ar' ? 'rtl' : 'ltr'; ?>">
    <!-- Floating Particles -->
    <div class="particles">
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
    </div>
    
    <div class="lang-switcher">
        <a href="?lang=<?php echo ($_SESSION['lang'] ?? 'ar') === 'ar' ? 'en' : 'ar'; ?>" class="lang-btn">
            🌐 <?php echo ($_SESSION['lang'] ?? 'ar') === 'ar' ? 'English' : 'العربية'; ?>
        </a>
    </div>
    <div class="login-container">
        <h1 class="app-title">🔐 لوحة الإدارة</h1>
        
        <div class="demo-info">
            <strong>بيانات الدخول الافتراضية:</strong><br>
            اسم المستخدم: admin<br>
            كلمة المرور: password
        </div>
        
        <?php if (!empty($loginError)): ?>
            <div class="error-message">⚠️ <?php echo htmlspecialchars($loginError); ?></div>
        <?php endif; ?>
        
        <form method="POST" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            
            <div class="form-group">
                <label>👤 اسم المستخدم</label>
                <input type="text" name="username" required autocomplete="username" <?php echo $loginLocked ? 'disabled' : ''; ?>>
            </div>
            <div class="form-group">
                <label>🔑 كلمة المرور</label>
                <input type="password" name="password" required autocomplete="current-password" <?php echo $loginLocked ? 'disabled' : ''; ?>>
            </div>
            <button type="submit" class="btn-login" <?php echo $loginLocked ? 'disabled' : ''; ?>>
                <?php echo $loginLocked ? '⏳ يرجى الانتظار...' : '🚀 دخول'; ?>
            </button>
        </form>
        
        <p class="footer-note">
            🔒 الجلسة محمية بتشفير آمن
        </p>
        
        <div class="admin-link">
            <a href="index.php">← العودة لواجهة الموظف</a>
        </div>
    </div>
    
    <script>
        // Dark theme detection
        if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
            document.body.dataset.theme = 'dark';
        }
    </script>
</body>
</html>
<?php
exit;
}

// رؤوس أمان HTTP
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

// الحصول على معلومات المستخدم المسجل
$adminUsername = 'مدير';
try {
    $db = Database::getInstance()->getConnection();
    if (isset($_SESSION['admin_user_id'])) {
        $stmt = $db->prepare("SELECT username FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['admin_user_id']]);
        $adminUser = $stmt->fetch();
        if ($adminUser) {
            $adminUsername = $adminUser['username'];
        }
    }
} catch (Exception $e) {
    // تجاهل الخطأ
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes, viewport-fit=cover">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#6366f1">
    <meta name="color-scheme" content="light dark">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="format-detection" content="telephone=no">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <link rel="manifest" href="manifest.json">
    <link rel="apple-touch-icon" href="logo.png">
    <title>لوحة الإدارة - نظام الحضور</title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link href="assets/css/admin.css?v=<?php echo time(); ?>" rel="stylesheet">
    <script src="assets/js/pwa.js" defer></script>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <div class="header-content">
            <div style="display: flex; align-items: center; gap: 15px;">
                <?php
                $logoPath = SystemSettings::get('company_logo', '');
                if ($logoPath && file_exists($logoPath)):
                ?>
                <img src="<?php echo $logoPath; ?>" alt="شعار الشركة" style="max-height: 50px; max-width: 80px; background: white; padding: 5px; border-radius: 8px;">
                <?php endif; ?>
                <div>
                    <h1>🔧 صرح انضباط - لوحة الإدارة</h1>
                    <small style="opacity: 0.8;"><?php echo SystemSettings::get('company_name', 'صرح الإتقان'); ?></small>
                </div>
            </div>
            <div class="header-actions">
                <span style="background: rgba(255,255,255,0.2); padding: 8px 15px; border-radius: 20px; font-size: 14px;">
                    👤 <?php echo htmlspecialchars($adminUsername); ?>
                </span>
                <a href="employee.php" class="btn btn-light">👥 واجهة الموظف</a>
                <button onclick="logout()" class="btn btn-danger" style="background: #dc2626;">🚪 تسجيل الخروج</button>
            </div>
            <nav class="sidebar-nav">
                <button class="nav-link active" onclick="showTab('dashboard')" data-tab="dashboard">
                    <span class="material-icons">dashboard</span>
                    <span class="nav-text">لوحة التحكم</span>
                </button>
                <button class="nav-link" onclick="showTab('attendance')" data-tab="attendance">
                    <span class="material-icons">schedule</span>
                    <span class="nav-text">سجلات الحضور</span>
                </button>
                <div class="nav-group open" id="navGroupPeople">
                    <button class="nav-group-toggle" onclick="toggleNavGroup('navGroupPeople')">
                        <span class="material-icons">groups</span>
                        <span class="nav-text">إدارة الموارد</span>
                    </button>
                    <div class="nav-group-list">
                        <button class="nav-link" onclick="showTab('employees')" data-tab="employees">
                            <span class="material-icons">badge</span>
                            <span class="nav-text">الموظفون</span>
                        </button>
                        <button class="nav-link" onclick="showTab('branches')" data-tab="branches">
                            <span class="material-icons">apartment</span>
                            <span class="nav-text">الفروع</span>
                        </button>
                    </div>
                </div>
                <div class="nav-group open" id="navGroupSystem">
                    <button class="nav-group-toggle" onclick="toggleNavGroup('navGroupSystem')">
                        <span class="material-icons">settings</span>
                        <span class="nav-text">إعدادات النظام</span>
                    </button>
                    <div class="nav-group-list">
                        <button class="nav-link" onclick="showTab('points')" data-tab="points">
                            <span class="material-icons">stars</span>
                            <span class="nav-text">نظام النقاط</span>
                        </button>
                        <button class="nav-link" onclick="showTab('settings')" data-tab="settings">
                            <span class="material-icons">tune</span>
                            <span class="nav-text">الإعدادات</span>
                        </button>
                        <button class="nav-link" onclick="showTab('advanced')" data-tab="advanced">
                            <span class="material-icons">shield</span>
                            <span class="nav-text">المتقدمة</span>
                        </button>
                    </div>
                </div>
            </nav>
            <div class="sidebar-footer">
                <button class="btn btn-light theme-toggle" id="themeToggle">
                    <span class="material-icons">dark_mode</span>
                    <span>الوضع الداكن</span>
                </button>
            </div>
        </aside>
        <div class="sidebar-overlay" onclick="document.getElementById('sidebar').classList.remove('mobile-open')"></div>
        <div class="app-main">
            <div class="topbar">
                <div class="title-block">
                    <span class="material-icons">auto_graph</span>
                    <div>
                        <h1>صرح انضباط - لوحة الإدارة</h1>
                        <small>لوحة تحكم متقدمة للموظفين والفروع</small>
                    </div>
                </div>
                <div class="header-actions">
                    <a href="employee.php" class="btn btn-light">واجهة الموظف</a>
                    <button onclick="logout()" class="btn btn-danger">تسجيل الخروج</button>
                </div>
            </div>
            <div class="main-content">
        <!-- Alert Container -->
        <div id="alertContainer"></div>

        <!-- Dashboard Tab -->
        <div id="dashboard" class="tab-content active">
            <!-- Control Panel -->
            <div class="control-panel">
                <div class="control-card">
                    <h4>🎛️ التحكم في النظام</h4>
                    <div class="control-buttons">
                        <button id="toggleSystemBtn" class="btn btn-success">تفعيل النظام</button>
                        <button id="toggleModeBtn" class="btn btn-warning">تبديل الوضع</button>
                    </div>
                    <div id="systemStatus" style="margin-top: 15px; padding: 10px; border-radius: 5px; font-weight: 700;"></div>
                </div>

                <div class="control-card">
                    <h4>📈 الإحصائيات السريعة</h4>
                    <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; font-size: 14px;">
                        <div>إجمالي المستخدمين: <strong id="dashTotalEmployees">0</strong></div>
                        <div>الحضور اليوم: <strong id="dashPresentCount" style="color: var(--success);">0</strong></div>
                        <div>حالات التأخير: <strong id="dashLateCount" style="color: var(--danger);">0</strong></div>
                        <div>نسبة الالتزام: <strong id="dashAttendanceRate" style="color: var(--primary);">0%</strong></div>
                    </div>
                </div>

                <div class="control-card">
                    <h4>🛠️ أدوات سريعة</h4>
                    <div class="control-buttons">
                        <button onclick="exportData()" class="btn btn-primary">📊 تصدير البيانات</button>
                        <button onclick="resetTodayRecords()" class="btn btn-danger">🔄 تصفير سجلات اليوم</button>
                        <button onclick="printReport()" class="btn btn-secondary">🖨️ طباعة التقرير</button>
                        <button onclick="toggleRegistrationClosed()" id="registrationClosedBtn" class="btn btn-warning">🔒 قفل التسجيل</button>
                    </div>
                </div>

                <div class="control-card">
                    <h4>📢 مربع الإعلانات</h4>
                    <div class="form-group">
                        <label>
                            <input type="checkbox" id="announcementVisible"> إظهار الإعلان
                        </label>
                        <textarea id="announcementText" rows="2" placeholder="نص الإعلان المهم..." style="margin-top: 10px;"></textarea>
                        <button onclick="saveAnnouncement()" class="btn btn-primary" style="margin-top: 10px;">حفظ</button>
                    </div>
                </div>
            </div>

            <!-- Today's Summary -->
            <div class="card">
                <div class="card-header">
                    <h3>📅 ملخص اليوم</h3>
                    <span id="currentDate"></span>
                </div>
                <div class="card-body">
                    <div class="table-container">
                        <table id="todaySummaryTable">
                            <thead>
                                <tr>
                                    <th>الفرع</th>
                                    <th>إجمالي المستخدمين</th>
                                    <th>الحضور</th>
                                    <th>التأخير</th>
                                    <th>نسبة الحضور</th>
                                    <th>إجمالي النقاط</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Attendance Records Tab -->
        <div id="attendance" class="tab-content">
            <div class="card">
                <div class="card-header">
                    <h3>📋 سجلات الحضور التفصيلية</h3>
                    <div>
                        <input type="date" id="attendanceDate" class="btn btn-light" style="padding: 8px;">
                        <button onclick="loadAttendanceRecords()" class="btn btn-primary">🔍 بحث</button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-container">
                        <table id="attendanceTable">
                            <thead>
                                <tr>
                                    <th>الموئف</th>
                                    <th>رقم الموظف</th>
                                    <th>الفرع</th>
                                    <th>وقت الحضور</th>
                                    <th>وقت الانصراف</th>
                                    <th>التأخير (دقيقة)</th>
                                    <th>النقاط</th>
                                    <th>الإجراءات</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Employees Management Tab -->
        <div id="employees" class="tab-content">
            <div class="card">
                <div class="card-header">
                    <h3>👥 إدارة المستخدمين</h3>
                    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                        <button onclick="showAddEmployeeModal()" class="btn btn-success">➕ إضافة مستخدم</button>
                        <button onclick="showAddDefaultEmployeesModal()" class="btn btn-info">📋 إضافة البيانات الأساسية</button>
                        <button onclick="showBulkAttendanceModal()" class="btn btn-primary">📝 تسجيل حضور جماعي</button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-container">
                        <table id="employeesTable">
                            <thead>
                                <tr>
                                    <th>رقم الموظف</th>
                                    <th>الاسم</th>
                                    <th>الفرع</th>
                                    <th>المنصب</th>
                                    <th>الهاتف</th>
                                    <th>الدوام</th>
                                    <th>النقاط</th>
                                    <th>الإجراءات</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Branches Management Tab -->
        <div id="branches" class="tab-content">
            <div class="card">
                <div class="card-header">
                    <h3>🏢 إدارة الفروع</h3>
                    <button onclick="showAddBranchModal()" class="btn btn-success">➕ إضافة فرع</button>
                </div>
                <div class="card-body">
                    <div class="table-container">
                        <table id="branchesTable">
                            <thead>
                                <tr>
                                    <th>اسم الفرع</th>
                                    <th>العنوان</th>
                                    <th>الهاتف</th>
                                    <th>عدد المستخدمين</th>
                                    <th>الإجراءات</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Points System Tab -->
        <div id="points" class="tab-content">
            <div class="card">
                <div class="card-header">
                    <h3>🎯 إدارة نظام النقاط</h3>
                    <button onclick="savePointsSystem()" class="btn btn-success">💾 حفظ الإعدادات</button>
                </div>
                <div class="card-body">
                    <div class="form-group">
                        <label>
                            <input type="checkbox" id="pointsEnabled" checked> 
                            تفعيل نظام النقاط
                        </label>
                        <div class="help-text">عند الإلغاء، لن يتم خصم أي نقاط على التأخير</div>
                    </div>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label>فترة السماح (بالدقائق)</label>
                            <input type="number" id="gracePeriod" value="30" min="0" max="60">
                            <div class="help-text">عدد الدقائق المسموحة بدون نقاط</div>
                        </div>
                    </div>
                    
                    <h4 style="margin: 30px 0 15px; color: var(--primary-dark);">نقاط التأخير</h4>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>المستوى الأول (10 دقائق بعد السماح)</label>
                            <input type="number" id="penalty1" value="10" min="0">
                        </div>
                        <div class="form-group">
                            <label>المستوى الثاني (20 دقيقة بعد السماح)</label>
                            <input type="number" id="penalty2" value="15" min="0">
                        </div>
                        <div class="form-group">
                            <label>المستوى الثالث (30 دقيقة بعد السماح)</label>
                            <input type="number" id="penalty3" value="25" min="0">
                        </div>
                        <div class="form-group">
                            <label>المستوى الرابع (أكثر من 30 دقيقة)</label>
                            <input type="number" id="penalty4" value="45" min="0">
                        </div>
                    </div>
                    
                    <div style="background: #f8fafc; padding: 20px; border-radius: 8px; margin: 20px 0;">
                        <h4 style="color: var(--primary-dark); margin-bottom: 10px;">مثال على النقاط:</h4>
                        <p><strong>وقت بداية العمل:</strong> 8:00 صباحاً</p>
                        <p><strong>فترة السماح:</strong> <span id="exampleGrace">30</span> دقيقة (حتى <span id="exampleGraceTime">8:30</span>)</p>
                        <ul style="margin: 10px 0; padding-right: 20px;">
                            <li>8:00 - <span id="exampleTime1">8:30</span>: <strong>0 نقاط</strong></li>
                            <li><span id="exampleTime2">8:31 - 8:40</span>: <strong><span id="examplePenalty1">10</span> نقاط</strong></li>
                            <li><span id="exampleTime3">8:41 - 8:50</span>: <strong><span id="examplePenalty2">15</span> نقطة</strong></li>
                            <li><span id="exampleTime4">8:51 - 9:00</span>: <strong><span id="examplePenalty3">25</span> نقطة</strong></li>
                            <li>بعد 9:00: <strong><span id="examplePenalty4">45</span> نقطة</strong></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- Settings Tab -->
        <div id="settings" class="tab-content">
            <div class="card">
                <div class="card-header">
                    <h3>⚙️ إعدادات النظام</h3>
                    <button onclick="saveSettings()" class="btn btn-success">💾 حفظ الإعدادات</button>
                </div>
                <div class="card-body">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>اسم الشركة</label>
                            <input type="text" id="companyName" placeholder="صرح الإتقان">
                        </div>
                        <div class="form-group">
                            <label>اسم المشرف</label>
                            <input type="text" id="adminName" placeholder="عبدالله أحمد الكردي">
                        </div>
                        <div class="form-group">
                            <label>وقت بداية العمل</label>
                            <input type="time" id="workStartTime" value="08:00">
                        </div>
                        <div class="form-group">
                            <label>فترة السماح (بالدقائق)</label>
                            <input type="number" id="gracePeriod" value="30" min="0" max="60">
                        </div>
                    </div>
                    
                    <h4 style="margin: 30px 0 15px; color: var(--primary-dark);">نقاط التأخير</h4>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>8:31 - 8:40 (نقاط)</label>
                            <input type="number" id="latePenalty1" value="10" min="0">
                        </div>
                        <div class="form-group">
                            <label>8:41 - 8:50 (نقاط)</label>
                            <input type="number" id="latePenalty2" value="15" min="0">
                        </div>
                        <div class="form-group">
                            <label>8:51 - 9:00 (نقاط)</label>
                            <input type="number" id="latePenalty3" value="25" min="0">
                        </div>
                        <div class="form-group">
                            <label>بعد 9:00 (نقاط)</label>
                            <input type="number" id="latePenalty4" value="45" min="0">
                        </div>
                    </div>
                    
                    <h4 style="margin: 30px 0 15px; color: var(--primary-dark);">⏰ الجدولة التلقائية</h4>
                    <div class="form-grid">
                        <div class="form-group" style="grid-column: 1 / -1;">
                            <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                                <input type="checkbox" id="autoModeEnabled" style="width: 20px; height: 20px; cursor: pointer;">
                                <span style="font-size: 16px;">تفعيل الوضع التلقائي (تبديل زر الحضور/الانصراف تلقائياً)</span>
                            </label>
                        </div>
                        <div class="form-group">
                            <label>بداية وقت الحضور</label>
                            <input type="time" id="autoCheckInStart" value="06:00">
                        </div>
                        <div class="form-group">
                            <label>نهاية وقت الحضور</label>
                            <input type="time" id="autoCheckInEnd" value="12:00">
                        </div>
                        <div class="form-group">
                            <label>بداية وقت الانصراف</label>
                            <input type="time" id="autoCheckOutStart" value="12:01">
                        </div>
                        <div class="form-group">
                            <label>&nbsp;</label>
                            <div class="help-text" style="margin-top: 10px;">سيتم تفعيل زر الانصراف تلقائياً بعد نهاية وقت الحضور</div>
                        </div>
                    </div>

                    <h4 style="margin: 30px 0 15px; color: var(--primary-dark);">🔐 إعدادات الأمان</h4>
                    <div class="form-grid">
                        <div class="form-group" style="grid-column: 1 / -1;">
                            <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                                <input type="checkbox" id="allowEmployeeLogout" style="width: 20px; height: 20px; cursor: pointer;">
                                <span style="font-size: 16px;">السماح للموظفين بالخروج من حساباتهم</span>
                            </label>
                            <small style="display: block; margin-top: 8px; color: #666; font-size: 14px;">
                                عند التحديد: يظهر زر «خروج» في صفحة الموظف. عند إلغاء التحديد: يُخفى زر الخروج ويمنع فتح صفحة الخروج لمنع تسجيل الحضور بدلاً من الزملاء. لا يؤثر على إمكانية الدخول لصفحة الموظف أو عمل الحضور والانصراف.
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Advanced Control Panel Tab -->
        <div id="advanced" class="tab-content">
            <div class="card">
                <div class="card-header">
                    <h3>🛡️ لوحة التحكم المتقدمة</h3>
                </div>
                <div class="card-body">
                    <!-- Sub Tabs -->
                    <div class="sub-tabs" style="display: flex; gap: 10px; margin-bottom: 20px; border-bottom: 2px solid #eee; padding-bottom: 10px;">
                        <button class="btn btn-light active" onclick="showSubTab('rules')" id="tab-rules">📐 المعادلات الديناميكية</button>
                        <button class="btn btn-light" onclick="showSubTab('roles')" id="tab-roles">👥 الصلاحيات والأدوار</button>
                        <button class="btn btn-light" onclick="showSubTab('logs')" id="tab-logs">📜 سجل التدقيق</button>
                        <button class="btn btn-light" onclick="showSubTab('backups')" id="tab-backups">💾 النسخ الاحتياطي</button>
                    </div>

                    <!-- 1. Dynamic Rules Section -->
                    <div id="section-rules" class="sub-section">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                            <h4>إدارة معادلات النظام</h4>
                            <button onclick="showRuleModal()" class="btn btn-primary">➕ إضافة قاعدة جديدة</button>
                        </div>
                        <div class="table-container">
                            <table id="rulesTable">
                                <thead>
                                    <tr>
                                        <th>المفتاح (Key)</th>
                                        <th>الاسم</th>
                                        <th>الوصف</th>
                                        <th>المعادلة</th>
                                        <th>الحالة</th>
                                        <th>الإجراءات</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>

                    <!-- 2. Roles & Permissions Section -->
                    <div id="section-roles" class="sub-section" style="display: none;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                            <h4>إدارة الأدوار والصلاحيات</h4>
                            <button onclick="showRoleModal()" class="btn btn-primary">➕ إضافة دور جديد</button>
                        </div>
                        <div class="table-container">
                            <table id="rolesTable">
                                <thead>
                                    <tr>
                                        <th>اسم الدور</th>
                                        <th>الوصف</th>
                                        <th>عدد المستخدمين</th>
                                        <th>الإجراءات</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>

                    <!-- 3. Audit Logs Section -->
                    <div id="section-logs" class="sub-section" style="display: none;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                            <h4>سجل الأحداث والتدقيق</h4>
                            <button onclick="loadAuditLogs()" class="btn btn-secondary">🔄 تحديث</button>
                        </div>
                        <div class="table-container">
                            <table id="auditLogsTable">
                                <thead>
                                    <tr>
                                        <th>الوقت</th>
                                        <th>المستخدم</th>
                                        <th>الحدث</th>
                                        <th>الجدول</th>
                                        <th>التفاصيل</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>

                    <!-- 4. Backups Section -->
                    <div id="section-backups" class="sub-section" style="display: none;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                            <h4>إدارة النسخ الاحتياطي</h4>
                            <button onclick="createBackup()" class="btn btn-success">📦 إنشاء نسخة احتياطية الآن</button>
                        </div>
                        <div class="table-container">
                            <table id="backupsTable">
                                <thead>
                                    <tr>
                                        <th>اسم الملف</th>
                                        <th>الحجم</th>
                                        <th>تاريخ الإنشاء</th>
                                        <th>ملاحظات</th>
                                        <th>الإجراءات</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Rule Modal -->
        <div id="ruleModal" class="modal">
            <div class="modal-content" style="max-width: 700px;">
                <div class="modal-header">
                    <h3 id="ruleModalTitle">إضافة/تعديل قاعدة</h3>
                    <span class="close" onclick="closeModal('ruleModal')">&times;</span>
                </div>
                <div class="modal-body">
                    <form id="ruleForm">
                        <input type="hidden" id="ruleId">
                        <div class="form-grid">
                            <div class="form-group">
                                <label>مفتاح القاعدة (Key) *</label>
                                <input type="text" id="ruleKey" placeholder="مثال: late_deduction" required pattern="[a-z0-9_]+">
                                <small style="color: #666;">أحرف إنجليزية صغيرة وأرقام و _ فقط</small>
                            </div>
                            <div class="form-group">
                                <label>اسم القاعدة *</label>
                                <input type="text" id="ruleName" required>
                            </div>
                            <div class="form-group" style="grid-column: 1 / -1;">
                                <label>الوصف</label>
                                <input type="text" id="ruleDescription">
                            </div>
                            <div class="form-group" style="grid-column: 1 / -1;">
                                <label>المعادلة الرياضية *</label>
                                <!-- V2: Simplified Rule Builder Toggle -->
                                <div style="margin-bottom: 10px;">
                                    <label class="switch-label">
                                        <input type="checkbox" id="simpleRuleMode" onchange="toggleRuleMode()" checked>
                                        <span>وضع البناء البسيط (Easy Mode)</span>
                                    </label>
                                </div>
                                
                                <!-- Simple Builder UI -->
                                <div id="simpleRuleBuilder" style="background: #f8fafc; padding: 15px; border-radius: 8px; border: 1px solid #e2e8f0;">
                                    <div style="margin-bottom: 10px;">
                                        <strong>إذا كان:</strong> 
                                        <select id="simpleConditionVar" style="padding: 5px; border-radius: 4px;">
                                            <option value="delay_minutes">دقائق التأخير</option>
                                            <option value="early_minutes">دقائق التبكير</option>
                                        </select>
                                        أكبر من
                                        <input type="number" id="simpleThreshold" value="30" style="width: 60px; padding: 5px;">
                                    </div>
                                    <div style="margin-bottom: 10px;">
                                        <strong>النتيجة:</strong> خصم/إضافة
                                        <input type="number" id="simplePoints" value="50" style="width: 60px; padding: 5px;">
                                        نقطة
                                    </div>
                                    <div>
                                        <strong>وإلا:</strong> النتيجة تكون 0
                                    </div>
                                </div>

                                <!-- Advanced Editor (Hidden by default) -->
                                <div id="advancedRuleEditor" style="display: none;">
                                    <textarea id="ruleEquation" rows="3" style="direction: ltr; font-family: monospace;" required></textarea>
                                    <small style="display: block; margin-top: 5px; color: #666;">
                                        العمليات المتاحة: +, -, *, /, %, (), ? : (شرطي)<br>
                                        مثال: <code>delay_minutes > 30 ? 50 : 0</code>
                                    </small>
                                </div>
                            </div>
                            <div class="form-group" style="grid-column: 1 / -1;">
                                <label>المتغيرات (JSON) *</label>
                                <textarea id="ruleVariables" rows="3" style="direction: ltr; font-family: monospace;" required></textarea>
                                <small style="color: #666;">مثال: {"delay_minutes": "Minutes Late", "factor": "Multiplier"}</small>
                            </div>
                        </div>
                        <div style="text-align: center; margin-top: 20px;">
                            <button type="submit" class="btn btn-success">💾 حفظ القاعدة</button>
                            <button type="button" onclick="closeModal('ruleModal')" class="btn btn-secondary">إلغاء</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Role Modal -->
        <div id="roleModal" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3 id="roleModalTitle">إدارة الدور</h3>
                    <span class="close" onclick="closeModal('roleModal')">&times;</span>
                </div>
                <div class="modal-body">
                    <form id="roleForm">
                        <input type="hidden" id="roleId">
                        <div class="form-group">
                            <label>اسم الدور *</label>
                            <input type="text" id="roleName" required>
                        </div>
                        <div class="form-group">
                            <label>الوصف</label>
                            <input type="text" id="roleDescription">
                        </div>
                        
                        <h4 style="margin: 15px 0 10px; border-top: 1px solid #eee; padding-top: 10px;">الصلاحيات</h4>
                        <div id="permissionsList" style="max-height: 300px; overflow-y: auto; display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px;">
                            <!-- Permissions will be loaded here -->
                        </div>

                        <div style="text-align: center; margin-top: 20px;">
                            <button type="submit" class="btn btn-success">💾 حفظ الدور</button>
                            <button type="button" onclick="closeModal('roleModal')" class="btn btn-secondary">إلغاء</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Employee Modal -->
    <div id="employeeModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="employeeModalTitle">إضافة موظف جديد</h3>
                <span class="close" onclick="closeModal('employeeModal')">&times;</span>
            </div>
            <div class="modal-body">
                <form id="employeeForm">
                    <input type="hidden" id="employeeId">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>رقم الموظف *</label>
                            <div style="display: flex; gap: 10px;">
                                <input type="text" id="employeeCode" required style="flex: 1;">
                                <button type="button" onclick="generateEmployeeCode()" class="btn btn-secondary" style="width: auto; padding: 10px 15px;">توليد</button>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>اسم الموظف *</label>
                            <input type="text" id="employeeName" required>
                        </div>
                        <div class="form-group">
                            <label>الفرع *</label>
                            <select id="employeeBranch" required></select>
                        </div>
                        <div class="form-group">
                            <label>المنصب</label>
                            <input type="text" id="employeePosition">
                        </div>
                        <div class="form-group">
                            <label>رقم الهاتف</label>
                            <input type="tel" id="employeePhone">
                        </div>
                        <div class="form-group">
                            <label>البريد الإلكتروني</label>
                            <input type="email" id="employeeEmail">
                        </div>
                        <div class="form-group">
                            <label>وقت بداية العمل</label>
                            <input type="time" id="employeeStartTime" value="08:00">
                        </div>
                        <div class="form-group">
                            <label>وقت نهاية العمل</label>
                            <input type="time" id="employeeEndTime" value="17:00">
                        </div>
                    </div>
                    
                    <h4 style="margin: 15px 0 10px; color: var(--primary-dark); font-size: 1rem; border-top: 1px solid #eee; padding-top: 15px;">⏰ جدولة مخصصة (تجاوز الإعدادات العامة)</h4>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>بداية الحضور</label>
                            <input type="time" id="customCheckInStart">
                            <small style="color: #666; font-size: 11px;">اتركه فارغاً لاستخدام العام</small>
                        </div>
                        <div class="form-group">
                            <label>نهاية الحضور</label>
                            <input type="time" id="customCheckInEnd">
                        </div>
                        <div class="form-group">
                            <label>بداية الانصراف</label>
                            <input type="time" id="customCheckOutStart">
                        </div>
                    </div>

                    <div style="text-align: center; margin-top: 20px;">
                        <button type="submit" class="btn btn-success">💾 حفظ</button>
                        <button type="button" onclick="closeModal('employeeModal')" class="btn btn-secondary">إلغاء</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Branch Modal -->
    <div id="branchModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="branchModalTitle">إضافة فرع جديد</h3>
                <span class="close" onclick="closeModal('branchModal')">&times;</span>
            </div>
            <div class="modal-body">
                <form id="branchForm">
                    <input type="hidden" id="branchId">
                    <div class="form-group">
                        <label>اسم الفرع *</label>
                        <input type="text" id="branchName" required>
                    </div>
                    <div class="form-group">
                        <label>العنوان</label>
                        <textarea id="branchAddress" rows="3"></textarea>
                    </div>
                    <div class="form-group">
                        <label>رقم الهاتف</label>
                        <input type="tel" id="branchPhone">
                    </div>
                    <div style="text-align: center; margin-top: 20px;">
                        <button type="submit" class="btn btn-success">💾 حفظ</button>
                        <button type="button" onclick="closeModal('branchModal')" class="btn btn-secondary">إلغاء</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add Default Employees Modal -->
    <div id="defaultEmployeesModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>إضافة البيانات الأساسية</h3>
                <span class="close" onclick="closeModal('defaultEmployeesModal')">&times;</span>
            </div>
            <div class="modal-body">
                <p style="color: #666; margin-bottom: 15px;">هل تريد إضافة قائمة المستخدمين الأساسية؟ سيتم إضافة 17 مستخدم.</p>
                <div class="table-container" style="max-height: 400px; overflow-y: auto; margin-bottom: 15px;">
                    <table id="defaultEmployeesTable" style="width: 100%; font-size: 13px;">
                        <thead>
                            <tr>
                                <th>✓</th>
                                <th>رقم الموظف</th>
                                <th>الاسم</th>
                                <th>الفرع</th>
                                <th>المنصب</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
                <div style="text-align: center; margin-top: 20px;">
                    <button onclick="addAllDefaultEmployees()" class="btn btn-success">✅ إضافة الجميع</button>
                    <button type="button" onclick="closeModal('defaultEmployeesModal')" class="btn btn-secondary">إلغاء</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bulk Attendance Modal -->
    <div id="bulkAttendanceModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>تسجيل حضور جماعي</h3>
                <span class="close" onclick="closeModal('bulkAttendanceModal')">&times;</span>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>التاريخ *</label>
                    <input type="date" id="bulkAttendanceDate" required>
                </div>
                <div class="form-group">
                    <label>وقت الحضور *</label>
                    <input type="time" id="bulkCheckInTime" value="08:00" required>
                </div>
                <div class="form-group">
                    <label>وقت الانصراف (اختياري)</label>
                    <input type="time" id="bulkCheckOutTime">
                </div>
                <div class="form-group">
                    <label>الفرع</label>
                    <select id="bulkBranchFilter">
                        <option value="">الكل</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>تحديد المستخدمين</label>
                    <div style="border: 1px solid #ddd; border-radius: 8px; max-height: 250px; overflow-y: auto; padding: 10px; background: #f9f9f9;">
                        <div style="margin-bottom: 10px; padding: 8px; background: white; border-radius: 5px;">
                            <input type="checkbox" id="bulkSelectAll" onchange="toggleAllBulkEmployees(this.checked)">
                            <label for="bulkSelectAll" style="cursor: pointer; font-weight: 600;"> اختيار الكل</label>
                        </div>
                        <div id="bulkEmployeesList" style="min-height: 50px;"></div>
                        <div id="bulkEmployeesCount" style="margin-top: 10px; padding: 5px; font-size: 12px; color: #666; text-align: center;"></div>
                    </div>
                </div>
                <div style="text-align: center; margin-top: 20px;">
                    <button onclick="submitBulkAttendance()" class="btn btn-success">✅ تسجيل الحضور</button>
                    <button type="button" onclick="closeModal('bulkAttendanceModal')" class="btn btn-secondary">إلغاء</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // متغيرات عامة
        let currentTab = 'dashboard';
        let systemSettings = {};
        
        // دالة مساعدة للتعامل مع طلبات API
        async function apiRequest(action, data = {}) {
            try {
                const response = await fetch('admin_api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'same-origin',
                    body: JSON.stringify({ action, ...data })
                });
                
                const result = await response.json();
                
                // التحقق من انتهاء الجلسة
                if (result.session_expired || response.status === 401) {
                    alert('انتهت صلاحية الجلسة. سيتم تحويلك لصفحة تسجيل الدخول.');
                    window.location.href = 'admin.php?logout=1';
                    return null;
                }
                
                // التحقق من رفض الوصول
                if (response.status === 403) {
                    alert('غير مصرح لك بالوصول. يرجى تسجيل الدخول.');
                    window.location.href = 'admin.php?logout=1';
                    return null;
                }
                
                return result;
            } catch (error) {
                console.error('خطأ في الاتصال:', error);
                throw error;
            }
        }
        
        // تحديث التاريخ الحالي
        function updateCurrentDate() {
            const now = new Date();
            const dateString = now.toLocaleDateString('ar-SA', {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
            const dateElement = document.getElementById('currentDate');
            if (dateElement) {
                dateElement.textContent = dateString;
            }
            
            // تعيين التاريخ الحالي في حقل البحث
            const attendanceDateInput = document.getElementById('attendanceDate');
            if (attendanceDateInput && !attendanceDateInput.value) {
                attendanceDateInput.value = now.toISOString().split('T')[0];
            }
        }

        // عرض رسالة تنبيه
        function showAlert(message, type = 'success') {
            const alertContainer = document.getElementById('alertContainer');
            const alertClass = type === 'success' ? 'alert-success' : 
                              type === 'error' ? 'alert-error' : 'alert-warning';
            
            alertContainer.innerHTML = `
                <div class="alert ${alertClass}">
                    ${message}
                </div>
            `;
            
            setTimeout(() => {
                alertContainer.innerHTML = '';
            }, 5000);
        }

        // تبديل التبويبات
        function showTab(tabName) {
            // إخفاء جميع التبويبات
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // إزالة الفئة النشطة من جميع الأزرار
            document.querySelectorAll('.nav-tab').forEach(btn => {
                btn.classList.remove('active');
            });
            
            // إظهار التبويب المحدد
            document.getElementById(tabName).classList.add('active');
            
            // تفعيل الزر المحدد
            event.target.classList.add('active');
            
            currentTab = tabName;
            
            // تحميل البيانات حسب التبويب
            switch(tabName) {
                case 'dashboard':
                    loadDashboardData();
                    break;
                case 'attendance':
                    loadAttendanceRecords();
                    break;
                case 'employees':
                    loadEmployees();
                    break;
                case 'branches':
                    loadBranches();
                    break;
                case 'points':
                    loadPointsSystem();
                    break;
                case 'settings':
                    loadSettings();
                    break;
                case 'advanced':
                    showSubTab('rules'); // Default sub-tab
                    break;
            }
        }

        // تحميل بيانات لوحة التحكم
        async function loadDashboardData() {
            try {
                const response = await fetch('admin_api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'get_dashboard_data' })
                });
                
                const result = await response.json();
                if (result.success) {
                    updateDashboardStats(result.data.stats);
                    updateTodaySummary(result.data.branches);
                    updateSystemStatus();
                }
            } catch (error) {
                console.error('خطأ في تحميل بيانات لوحة التحكم:', error);
            }
        }

        // تحديث إحصائيات لوحة التحكم
        function updateDashboardStats(stats) {
            document.getElementById('dashTotalEmployees').textContent = stats.total_employees || 0;
            document.getElementById('dashPresentCount').textContent = stats.present_count || 0;
            document.getElementById('dashLateCount').textContent = stats.late_count || 0;
            document.getElementById('dashAttendanceRate').textContent = (stats.attendance_rate || 0) + '%';
        }

        // تحديث ملخص اليوم
        function updateTodaySummary(branches) {
            const tbody = document.querySelector('#todaySummaryTable tbody');
            tbody.innerHTML = '';
            
            branches.forEach(branch => {
                const attendanceRate = branch.total_employees > 0 ? 
                    Math.round(((branch.present_count || 0) / branch.total_employees) * 100) : 0;
                
                const row = tbody.insertRow();
                row.innerHTML = `
                    <td>${branch.name}</td>
                    <td>${branch.total_employees || 0}</td>
                    <td><span class="badge badge-success">${branch.present_count || 0}</span></td>
                    <td><span class="badge badge-danger">${branch.late_count || 0}</span></td>
                    <td><span class="badge badge-secondary">${attendanceRate}%</span></td>
                    <td><span class="badge badge-warning">${branch.total_deductions || 0}</span></td>
                `;
            });
        }

        // تحديث حالة النظام
        async function updateSystemStatus() {
            try {
                const response = await fetch('attendance_api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'get_system_status' })
                });
                
                const result = await response.json();
                if (result.success) {
                    systemSettings = result.data;
                    
                    const statusDiv = document.getElementById('systemStatus');
                    const toggleBtn = document.getElementById('toggleSystemBtn');
                    const modeBtn = document.getElementById('toggleModeBtn');
                    const regClosedBtn = document.getElementById('registrationClosedBtn');
                    
                    if (systemSettings.attendance_enabled === '1') {
                        statusDiv.innerHTML = '✅ النظام مفعل';
                        statusDiv.style.background = '#dcfce7';
                        statusDiv.style.color = '#166534';
                        toggleBtn.textContent = 'إيقاف النظام';
                        toggleBtn.className = 'btn btn-danger';
                    } else {
                        statusDiv.innerHTML = '⚠️ النظام متوقف';
                        statusDiv.style.background = '#fee2e2';
                        statusDiv.style.color = '#991b1b';
                        toggleBtn.textContent = 'تفعيل النظام';
                        toggleBtn.className = 'btn btn-success';
                    }
                    
                    const mode = systemSettings.attendance_mode === 'check_out' ? 'انصراف' : 'حضور';
                    modeBtn.textContent = `الوضع: ${mode}`;
                    
                    if (regClosedBtn) {
                        if (systemSettings.registration_closed === '1') {
                            regClosedBtn.textContent = '🔓 فتح التسجيل';
                            regClosedBtn.className = 'btn btn-success';
                        } else {
                            regClosedBtn.textContent = '🔒 قفل التسجيل';
                            regClosedBtn.className = 'btn btn-warning';
                        }
                    }
                    
                    const annVisible = document.getElementById('announcementVisible');
                    const annText = document.getElementById('announcementText');
                    if (annVisible && annText) {
                        annVisible.checked = systemSettings.announcement_visible === '1';
                        annText.value = systemSettings.announcement_text || '';
                    }
                }
            } catch (error) {
                console.error('خطأ في تحديث حالة النظام:', error);
            }
        }

        // قفل/فتح التسجيل
        async function toggleRegistrationClosed() {
            const newVal = systemSettings.registration_closed === '1' ? '0' : '1';
            try {
                const response = await fetch('admin_api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'update_setting', key: 'registration_closed', value: newVal })
                });
                const result = await response.json();
                if (result.success) {
                    showAlert(newVal === '1' ? 'تم قفل التسجيل - سيظهر السلاحف' : 'تم فتح التسجيل');
                    updateSystemStatus();
                }
            } catch (error) {
                showAlert('حدث خطأ', 'error');
            }
        }

        // حفظ الإعلان
        async function saveAnnouncement() {
            const visible = document.getElementById('announcementVisible').checked ? '1' : '0';
            const text = document.getElementById('announcementText').value;
            try {
                await fetch('admin_api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'update_setting', key: 'announcement_visible', value: visible })
                });
                await fetch('admin_api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'update_setting', key: 'announcement_text', value: text })
                });
                showAlert('تم حفظ الإعلان');
                updateSystemStatus();
            } catch (error) {
                showAlert('حدث خطأ', 'error');
            }
        }

        // تبديل حالة النظام
        async function toggleSystem() {
            const newStatus = systemSettings.attendance_enabled === '1' ? '0' : '1';
            
            try {
                const response = await fetch('admin_api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ 
                        action: 'update_setting',
                        key: 'attendance_enabled',
                        value: newStatus
                    })
                });
                
                const result = await response.json();
                if (result.success) {
                    showAlert(result.message);
                    updateSystemStatus();
                } else {
                    showAlert(result.message, 'error');
                }
            } catch (error) {
                showAlert('حدث خطأ في النظام', 'error');
            }
        }

        // تبديل وضع النظام
        async function toggleMode() {
            const newMode = systemSettings.attendance_mode === 'check_in' ? 'check_out' : 'check_in';
            
            try {
                const response = await fetch('admin_api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ 
                        action: 'update_setting',
                        key: 'attendance_mode',
                        value: newMode
                    })
                });
                
                const result = await response.json();
                if (result.success) {
                    showAlert(result.message);
                    updateSystemStatus();
                } else {
                    showAlert(result.message, 'error');
                }
            } catch (error) {
                showAlert('حدث خطأ في النظام', 'error');
            }
        }

        // تحميل سجلات الحضور
        async function loadAttendanceRecords() {
            const date = document.getElementById('attendanceDate').value;
            
            try {
                const response = await fetch('admin_api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ 
                        action: 'get_attendance_records',
                        date: date
                    })
                });
                
                const result = await response.json();
                if (result.success) {
                    updateAttendanceTable(result.data);
                }
            } catch (error) {
                console.error('خطأ في تحميل سجلات الحضور:', error);
            }
        }

        // تحديث جدول الحضور
        function updateAttendanceTable(records) {
            const tbody = document.querySelector('#attendanceTable tbody');
            tbody.innerHTML = '';
            
            records.forEach(record => {
                const row = tbody.insertRow();
                const statusClass = record.deduction_points > 0 ? 'badge-danger' : 'badge-success';
                const branchColor = getBranchColor(record.branch_name);
                
                row.innerHTML = `
                    <td>${record.employee_name}</td>
                    <td>${record.employee_code}</td>
                    <td><span class="badge" style="background: ${branchColor}; color: white; padding: 5px 12px; border-radius: 12px; font-weight: 600;">${record.branch_name}</span></td>
                    <td>${record.check_in_time || '-'}</td>
                    <td>${record.check_out_time || '-'}</td>
                    <td>${record.delay_minutes || 0}</td>
                    <td>${record.deduction_points || 0}</td>
                    <td><span class="badge ${statusClass}">${record.status || 'غائب'}</span></td>
                    <td>
                        <button onclick="editAttendance(${record.id})" class="btn btn-warning" style="padding: 5px 10px; font-size: 12px;">تعديل</button>
                    </td>
                `;
            });
        }

        // تحميل الموظفين
        async function loadEmployees() {
            try {
                const response = await fetch('admin_api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'get_employees' })
                });
                
                const result = await response.json();
                if (result.success) {
                    updateEmployeesTable(result.data);
                }
            } catch (error) {
                console.error('خطأ في تحميل الموظفين:', error);
            }
        }

        // تحميل بيانات موظف للتعديل
        async function editEmployee(employeeId) {
            try {
                // تحميل الفروع أولاً
                await loadBranches();
                
                const response = await fetch('admin_api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'get_employee', employee_id: employeeId })
                });
                
                const result = await response.json();
                if (result.success) {
                    const employee = result.data;
                    
                    // ملء النموذج
                    document.getElementById('employeeModalTitle').textContent = 'تعديل الموظف';
                    document.getElementById('employeeId').value = employee.id;
                    document.getElementById('employeeCode').value = employee.employee_code;
                    document.getElementById('employeeName').value = employee.name;
                    document.getElementById('employeePosition').value = employee.position || '';
                    document.getElementById('employeePhone').value = employee.phone || '';
                    document.getElementById('employeeEmail').value = employee.email || '';
                    document.getElementById('employeeStartTime').value = employee.start_time;
                    document.getElementById('employeeEndTime').value = employee.end_time || '17:00:00';
                    
                    // تعيين الفرع بعد التأكد من تحميل القائمة
                    setTimeout(() => {
                        document.getElementById('employeeBranch').value = employee.branch_id;
                    }, 100);
                    
                    document.getElementById('customCheckInStart').value = employee.custom_check_in_start || '';
                    document.getElementById('customCheckInEnd').value = employee.custom_check_in_end || '';
                    document.getElementById('customCheckOutStart').value = employee.custom_check_out_start || '';

                    // إظهار النافذة
                    document.getElementById('employeeModal').style.display = 'block';
                } else {
                    showAlert(result.message, 'error');
                }
            } catch (error) {
                showAlert('حدث خطأ في تحميل بيانات الموظف', 'error');
            }
        }

        // حذف موظف
        async function deleteEmployee(employeeId) {
            if (!confirm('هل أنت متأكد من حذف هذا الموظف؟ سيتم حذف جميع سجلات الحضور الخاصة به.')) {
                return;
            }
            
            try {
                const response = await fetch('admin_api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'delete_employee', employee_id: employeeId })
                });
                
                const result = await response.json();
                if (result.success) {
                    showAlert(result.message, 'success');
                    loadEmployees();
                } else {
                    showAlert(result.message, 'error');
                }
            } catch (error) {
                showAlert('حدث خطأ في حذف الموظف', 'error');
            }
        }

        // ألوان الفروع
        const branchColors = {
            'صرح الإتقان الرئيسي': '#3b82f6',
            'صرح الإتقان الأمريكي': '#8b5cf6',
            'صرح الإتقان الأوروبي': '#ec4899',
            'شركة فضاء المحركات': '#f59e0b',
            'مركز فضاء المحركات': '#10b981'
        };

        function getBranchColor(branchName) {
            return branchColors[branchName] || '#64748b';
        }

        // تحديث جدول الموظفين
        function updateEmployeesTable(employees) {
            const tbody = document.querySelector('#employeesTable tbody');
            tbody.innerHTML = '';
            
            employees.forEach(employee => {
                const row = tbody.insertRow();
                const branchColor = getBranchColor(employee.branch_name);
                
                row.innerHTML = `
                    <td>${employee.employee_code}</td>
                    <td>${employee.name}</td>
                    <td><span class="badge" style="background: ${branchColor}; color: white; padding: 5px 12px; border-radius: 12px; font-weight: 600;">${employee.branch_name}</span></td>
                    <td>${employee.position || '-'}</td>
                    <td>${employee.phone || '-'}</td>
                    <td>${employee.start_time} - ${employee.end_time || '17:00'}</td>
                    <td style="font-weight: 700; color: ${employee.points_balance < 50 ? '#dc2626' : employee.points_balance < 80 ? '#f59e0b' : '#10b981'};">${employee.points_balance}</td>
                    <td>
                        <button onclick="adjustPoints(${employee.id}, '${employee.name}')" class="btn" style="background: #10b981; color: white; padding: 5px 10px; font-size: 12px; margin: 2px;">⚡ نقاط</button>
                        <button onclick="editEmployee(${employee.id})" class="btn btn-info" style="padding: 5px 10px; font-size: 12px; margin: 2px;">✏️ تعديل</button>
                        <button onclick="deleteEmployee(${employee.id})" class="btn btn-danger" style="padding: 5px 10px; font-size: 12px; margin: 2px;">🗑️ حذف</button>
                    </td>
                `;
            });
        }

        // تحميل الفروع
        async function loadBranches() {
            try {
                const response = await fetch('admin_api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'get_branches' })
                });
                
                const result = await response.json();
                console.log('نتيجة تحميل الفروع:', result);
                
                if (result.success) {
                    updateBranchesTable(result.data);
                    updateBranchSelects(result.data);
                    return result.data;
                } else {
                    console.error('فشل تحميل الفروع:', result.message);
                    return [];
                }
            } catch (error) {
                console.error('خطأ في تحميل الفروع:', error);
                return [];
            }
        }

        // تحميل بيانات فرع للتعديل
        async function editBranch(branchId) {
            try {
                const response = await fetch('admin_api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'get_branch', branch_id: branchId })
                });
                
                const result = await response.json();
                if (result.success) {
                    const branch = result.data;
                    
                    // ملء النموذج
                    document.getElementById('branchModalTitle').textContent = 'تعديل الفرع';
                    document.getElementById('branchId').value = branch.id;
                    document.getElementById('branchName').value = branch.name;
                    document.getElementById('branchAddress').value = branch.address || '';
                    document.getElementById('branchPhone').value = branch.phone || '';
                    
                    // إظهار النافذة
                    document.getElementById('branchModal').style.display = 'block';
                } else {
                    showAlert(result.message, 'error');
                }
            } catch (error) {
                showAlert('حدث خطأ في تحميل بيانات الفرع', 'error');
            }
        }

        // حذف فرع
        async function deleteBranch(branchId) {
            if (!confirm('هل أنت متأكد من حذف هذا الفرع؟ يجب نقل المستخدمين إلى فرع آخر أولاً.')) {
                return;
            }
            
            try {
                const response = await fetch('admin_api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'delete_branch', branch_id: branchId })
                });
                
                const result = await response.json();
                if (result.success) {
                    showAlert(result.message, 'success');
                    loadBranches();
                } else {
                    showAlert(result.message, 'error');
                }
            } catch (error) {
                showAlert('حدث خطأ في حذف الفرع', 'error');
            }
        }

        // تحديث جدول الفروع
        function updateBranchesTable(branches) {
            const tbody = document.querySelector('#branchesTable tbody');
            if (!tbody) return;
            tbody.innerHTML = '';
            
            if (!branches || !Array.isArray(branches)) return;
            
            branches.forEach(branch => {
                const row = tbody.insertRow();
                const branchColor = getBranchColor(branch.name);
                
                row.innerHTML = `
                    <td><span class="badge" style="background: ${branchColor}; color: white; padding: 8px 16px; border-radius: 12px; font-weight: 700; font-size: 14px;">${branch.name}</span></td>
                    <td>${branch.address || '-'}</td>
                    <td>${branch.phone || '-'}</td>
                    <td><span class="badge badge-info" style="padding: 6px 12px; font-size: 14px;">${branch.employee_count || 0} موظف</span></td>
                    <td>
                        <button onclick="editBranch(${branch.id})" class="btn btn-warning" style="padding: 5px 10px; font-size: 12px;">✏️ تعديل</button>
                        <button onclick="deleteBranch(${branch.id})" class="btn btn-danger" style="padding: 5px 10px; font-size: 12px;">🗑️ حذف</button>
                    </td>
                `;
            });
        }

        // تحديث قوائم الفروع المنسدلة
        function updateBranchSelects(branches) {
            const select = document.getElementById('employeeBranch');
            if (!select) {
                console.error('لم يتم العثور على عنصر employeeBranch');
                return;
            }
            select.innerHTML = '<option value="">اختر الفرع</option>';
            
            if (!branches || !Array.isArray(branches)) {
                console.error('بيانات الفروع غير صحيحة:', branches);
                return;
            }
            
            branches.forEach(branch => {
                // قبول القيمة كنص أو رقم
                if (branch.is_active == 1 || branch.is_active === '1' || branch.is_active === true) {
                    select.innerHTML += `<option value="${branch.id}">${branch.name}</option>`;
                }
            });
            
            console.log('تم تحميل الفروع:', branches.length);
        }

        // إظهار نافذة إضافة موظف
        async function showAddEmployeeModal() {
            document.getElementById('employeeModalTitle').textContent = 'إضافة موظف جديد';
            document.getElementById('employeeForm').reset();
            document.getElementById('employeeId').value = '';
            
            // تحميل الفروع أولاً ثم إظهار النافذة
            await loadBranches();
            document.getElementById('employeeModal').style.display = 'block';
        }

        // توليد رقم موظف من الاسم
        async function generateEmployeeCode() {
            const name = document.getElementById('employeeName').value.trim();
            if (!name) {
                showAlert('يرجى إدخال اسم الموظف أولاً', 'error');
                return;
            }
            
            try {
                const response = await fetch('admin_api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'generate_employee_code', name: name })
                });
                
                const result = await response.json();
                if (result.success) {
                    document.getElementById('employeeCode').value = result.employee_code;
                    showAlert('تم توليد رقم الموظف بنجاح', 'success');
                } else {
                    showAlert(result.message, 'error');
                }
            } catch (error) {
                showAlert('حدث خطأ في توليد رقم الموظف', 'error');
            }
        }

        // تعديل سجل حضور
        async function editAttendance(recordId) {
            const checkIn = prompt('وقت الحضور (HH:MM):');
            if (checkIn === null) return;
            
            const checkOut = prompt('وقت الانصراف (HH:MM) - اتركه فارغاً إذا لم ينصرف:') || null;
            const notes = prompt('ملاحظات (اختياري):') || '';
            
            try {
                const response = await fetch('admin_api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ 
                        action: 'edit_attendance',
                        record_id: recordId,
                        check_in_time: checkIn,
                        check_out_time: checkOut,
                        notes: notes
                    })
                });
                
                const result = await response.json();
                if (result.success) {
                    showAlert(result.message, 'success');
                    loadAttendanceRecords();
                } else {
                    showAlert(result.message, 'error');
                }
            } catch (error) {
                showAlert('حدث خطأ في تعديل سجل الحضور', 'error');
            }
        }

        // إظهار نافذة إضافة فرع
        function showAddBranchModal() {
            document.getElementById('branchModalTitle').textContent = 'إضافة فرع جديد';
            document.getElementById('branchForm').reset();
            document.getElementById('branchId').value = '';
            document.getElementById('branchModal').style.display = 'block';
        }

        // إغلاق النافذة المنبثقة
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        // تسجيل الخروج
        function logout() {
            if (confirm('هل أنت متأكد من تسجيل الخروج؟')) {
                // مسح البيانات المحلية
                sessionStorage.clear();
                localStorage.removeItem('adminToken');
                // الانتقال لصفحة الخروج
                window.location.href = 'admin.php?logout=1';
            }
        }
        
        // التحقق من انتهاء الجلسة
        let sessionTimeout;
        function resetSessionTimer() {
            clearTimeout(sessionTimeout);
            sessionTimeout = setTimeout(function() {
                alert('انتهت صلاحية الجلسة. سيتم تسجيل خروجك تلقائياً.');
                window.location.href = 'admin.php?logout=1';
            }, 30 * 60 * 1000); // 30 دقيقة
        }
        
        // إعادة تعيين المؤقت عند أي نشاط
        document.addEventListener('mousemove', resetSessionTimer);
        document.addEventListener('keypress', resetSessionTimer);
        document.addEventListener('click', resetSessionTimer);
        resetSessionTimer();

        // تصدير البيانات
        function exportData() {
            showAlert('جاري تصدير البيانات...', 'warning');
            // هنا يمكن إضافة كود تصدير البيانات
        }

        // طباعة التقرير
        function printReport() {
            window.open('print_report.php', '_blank');
        }

        // تحميل إعدادات نظام النقاط
        async function loadPointsSystem() {
            try {
                const response = await fetch('admin_api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'get_points_system' })
                });
                
                const result = await response.json();
                if (result.success) {
                    const settings = result.data;
                    
                    document.getElementById('pointsEnabled').checked = settings.enabled === '1';
                    document.getElementById('gracePeriod').value = settings.grace_period;
                    document.getElementById('penalty1').value = settings.penalty_1;
                    document.getElementById('penalty2').value = settings.penalty_2;
                    document.getElementById('penalty3').value = settings.penalty_3;
                    document.getElementById('penalty4').value = settings.penalty_4;
                    
                    updatePointsExample();
                }
            } catch (error) {
                console.error('خطأ في تحميل إعدادات النقاط:', error);
            }
        }

        // حفظ إعدادات نظام النقاط
        async function savePointsSystem() {
            const settings = {
                action: 'update_points_system',
                enabled: document.getElementById('pointsEnabled').checked ? 1 : 0,
                grace_period: document.getElementById('gracePeriod').value,
                penalty_1: document.getElementById('penalty1').value,
                penalty_2: document.getElementById('penalty2').value,
                penalty_3: document.getElementById('penalty3').value,
                penalty_4: document.getElementById('penalty4').value
            };
            
            try {
                const response = await fetch('admin_api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(settings)
                });
                
                const result = await response.json();
                if (result.success) {
                    showAlert(result.message, 'success');
                    updatePointsExample();
                } else {
                    showAlert(result.message, 'error');
                }
            } catch (error) {
                showAlert('حدث خطأ في حفظ الإعدادات', 'error');
            }
        }

        // تحديث مثال النقاط
        function updatePointsExample() {
            const gracePeriod = parseInt(document.getElementById('gracePeriod').value) || 30;
            const penalty1 = document.getElementById('penalty1').value;
            const penalty2 = document.getElementById('penalty2').value;
            const penalty3 = document.getElementById('penalty3').value;
            const penalty4 = document.getElementById('penalty4').value;
            
            // حساب الأوقات
            const graceEndHour = 8;
            const graceEndMinute = gracePeriod;
            const graceEndTime = `${graceEndHour}:${graceEndMinute.toString().padStart(2, '0')}`;
            
            const level1End = `${graceEndHour}:${(graceEndMinute + 10).toString().padStart(2, '0')}`;
            const level2End = `${graceEndHour}:${(graceEndMinute + 20).toString().padStart(2, '0')}`;
            const level3End = `${graceEndHour}:${(graceEndMinute + 30).toString().padStart(2, '0')}`;
            
            // تحديث النص
            document.getElementById('exampleGrace').textContent = gracePeriod;
            document.getElementById('exampleGraceTime').textContent = graceEndTime;
            document.getElementById('exampleTime1').textContent = graceEndTime;
            document.getElementById('exampleTime2').textContent = `${graceEndHour}:${(graceEndMinute + 1).toString().padStart(2, '0')} - ${level1End}`;
            document.getElementById('exampleTime3').textContent = `${graceEndHour}:${(graceEndMinute + 11).toString().padStart(2, '0')} - ${level2End}`;
            document.getElementById('exampleTime4').textContent = `${graceEndHour}:${(graceEndMinute + 21).toString().padStart(2, '0')} - ${level3End}`;
            document.getElementById('examplePenalty1').textContent = penalty1;
            document.getElementById('examplePenalty2').textContent = penalty2;
            document.getElementById('examplePenalty3').textContent = penalty3;
            document.getElementById('examplePenalty4').textContent = penalty4;
        }

        // ربط الأحداث
        document.getElementById('toggleSystemBtn').addEventListener('click', toggleSystem);
        document.getElementById('toggleModeBtn').addEventListener('click', toggleMode);

        // معالجة نموذج الموظف
        document.getElementById('employeeForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = {
                action: document.getElementById('employeeId').value ? 'update_employee' : 'add_employee',
                employee_id: document.getElementById('employeeId').value,
                employee_code: document.getElementById('employeeCode').value,
                name: document.getElementById('employeeName').value,
                branch_id: document.getElementById('employeeBranch').value,
                position: document.getElementById('employeePosition').value,
                phone: document.getElementById('employeePhone').value,
                email: document.getElementById('employeeEmail').value,
                start_time: document.getElementById('employeeStartTime').value,
                end_time: document.getElementById('employeeEndTime').value,
                custom_check_in_start: document.getElementById('customCheckInStart').value,
                custom_check_in_end: document.getElementById('customCheckInEnd').value,
                custom_check_out_start: document.getElementById('customCheckOutStart').value
            };
            
            try {
                const response = await fetch('admin_api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(formData)
                });
                
                const result = await response.json();
                if (result.success) {
                    showAlert(result.message, 'success');
                    closeModal('employeeModal');
                    loadEmployees();
                } else {
                    showAlert(result.message, 'error');
                }
            } catch (error) {
                showAlert('حدث خطأ في حفظ بيانات الموظف', 'error');
            }
        });

        // معالجة نموذج الفرع
        document.getElementById('branchForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = {
                action: document.getElementById('branchId').value ? 'update_branch' : 'add_branch',
                branch_id: document.getElementById('branchId').value,
                name: document.getElementById('branchName').value,
                address: document.getElementById('branchAddress').value,
                phone: document.getElementById('branchPhone').value
            };
            
            try {
                const response = await fetch('admin_api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(formData)
                });
                
                const result = await response.json();
                if (result.success) {
                    showAlert(result.message, 'success');
                    closeModal('branchModal');
                    loadBranches();
                } else {
                    showAlert(result.message, 'error');
                }
            } catch (error) {
                showAlert('حدث خطأ في حفظ بيانات الفرع', 'error');
            }
        });

        // إغلاق النوافذ المنبثقة عند النقر خارجها
        window.onclick = function(event) {
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
                if (event.target === modal) {
                    modal.style.display = 'none';
                }
            });
        }

        // التشغيل الأولي
        document.addEventListener('DOMContentLoaded', function() {
            updateCurrentDate();
            loadDashboardData();
            loadBranches(); // تحميل الفروع عند بدء الصفحة
            
            // مراقبة تغييرات حقول النقاط
            ['gracePeriod', 'penalty1', 'penalty2', 'penalty3', 'penalty4'].forEach(id => {
                const element = document.getElementById(id);
                if (element) {
                    element.addEventListener('input', updatePointsExample);
                }
            });
        });

        // ============ الدوال الجديدة ============
        
        // تصفير سجلات اليوم
        async function resetTodayRecords() {
            if (!confirm('هل أنت متأكد من تصفير سجلات اليوم للبداية من جديد؟ سيتم حذف جميع سجلات الحضور لهذا اليوم.')) {
                return;
            }
            
            try {
                const response = await fetch('admin_api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'reset_today_records' })
                });
                
                const result = await response.json();
                if (result.success) {
                    showAlert(result.message, 'success');
                    loadDashboardData();
                    if (currentTab === 'attendance') {
                        loadAttendanceRecords();
                    }
                } else {
                    showAlert(result.message, 'error');
                }
            } catch (error) {
                showAlert('حدث خطأ في النظام', 'error');
            }
        }

        // قائمة المستخدمين الأساسية
        const rawDefaultEmployees = [
            {name: "Arnous", branch: "صرح الإتقان الأمريكي", position: "كهربائي", phone: "966500089178"},
            {name: "Bilal", branch: "صرح الإتقان الأمريكي", position: "ميكانيكي", phone: "966594154009"},
            {name: "Inay_us", branch: "صرح الإتقان الأمريكي", position: "ميكانيكي", phone: "966571761401"},
            {name: "Wakas", branch: "صرح الإتقان الأمريكي", position: "كهربائي", phone: "966598997295"},
            {name: "Shaaban", branch: "صرح الإتقان الأمريكي", position: "ميكانيكي", phone: "966595153544"},
            {name: "Musab", branch: "صرح الإتقان الأمريكي", position: "المدير", phone: "966555792273"},
            {name: "Abu Suleiman (Lebanese)", branch: "صرح الإتقان الأوروبي", position: "موظف", phone: "966500651865"},
            {name: "Islam", branch: "صرح الإتقان الأوروبي", position: "موظف", phone: "966549820672"},
            {name: "Bukhari", branch: "صرح الإتقان الأوروبي", position: "موظف", phone: "923095734018"},
            {name: "Saber", branch: "صرح الإتقان الأوروبي", position: "موظف", phone: "966570899595"},
            {name: "Mohsen", branch: "صرح الإتقان الأوروبي", position: "موظف", phone: "966537491699"},
            {name: "Amjad", branch: "صرح الإتقان الرئيسي", position: "موظف", phone: "966555106370"},
            {name: "Ayman", branch: "صرح الإتقان الرئيسي", position: "موظف", phone: "966555090870"},
            {name: "Zaher", branch: "صرح الإتقان الرئيسي", position: "موظف", phone: "966546481759"},
            {name: "Najeeb (n forever)", branch: "صرح الإتقان الرئيسي", position: "موظف", phone: "923475914157"},
            {name: "Abd_y", branch: "مركز فضاء المحركات", position: "ميزان", phone: "966536765655"},
            {name: "Afzal", branch: "مركز فضاء المحركات", position: "ميكانيكي", phone: "966599258117"},
            {name: "Habib", branch: "مركز فضاء المحركات", position: "ميكانيكي", phone: "966573263203"},
            {name: "Imti", branch: "مركز فضاء المحركات", position: "ميكانيكي", phone: "966595806604"},
            {name: "Inay", branch: "مركز فضاء المحركات", position: "ميكانيكي", phone: "966582329361"},
            {name: "Irfan", branch: "مركز فضاء المحركات", position: "ميكانيكي", phone: "966597255093"},
            {name: "Wassim", branch: "مركز فضاء المحركات", position: "مهندس نظافة", phone: "966531806242"},
            {name: "Jihad", branch: "مركز فضاء المحركات", position: "المدير", phone: "966508512355"},
            {name: "Risha", branch: "مركز فضاء المحركات", position: "كهربائي", phone: "966536781886"},
            {name: "Shehata", branch: "مركز فضاء المحركات", position: "محاسب", phone: "966545677065"},
            {name: "Qutaiba", branch: "مركز فضاء المحركات", position: "مدير الميكانيكا", phone: "966597024453"},
            {name: "Mustafa Awad Saad", branch: "شركة فضاء المحركات", position: "موظف", phone: "966555106370"},
            {name: "Munther Mohammed", branch: "شركة فضاء المحركات", position: "موظف", phone: "966556593723"},
            {name: "Abdulhadi Younis", branch: "شركة فضاء المحركات", position: "موظف", phone: "966596261969"},
            {name: "Mohammed Jalal", branch: "شركة فضاء المحركات", position: "موظف", phone: "966573603727"},
            {name: "Mohammed Bilal", branch: "شركة فضاء المحركات", position: "موظف", phone: "966503863694"},
            {name: "Abbas Ali Ramadan", branch: "شركة فضاء المحركات", position: "موظف", phone: "966594119151"},
            {name: "Mohammed Afridi", branch: "شركة فضاء المحركات", position: "موظف", phone: "966565722089"},
            {name: "Salvador Dela", branch: "شركة فضاء المحركات", position: "موظف", phone: "966541756875"},
            {name: "Mohammed Khan", branch: "شركة فضاء المحركات", position: "موظف", phone: "966594163035"},
            {name: "Andres Portes", branch: "شركة فضاء المحركات", position: "موظف", phone: "966590087140"},
            {name: "Hassan Shabbir (Asif)", branch: "شركة فضاء المحركات", position: "موظف", phone: "966582670736"},
            {name: "Owais Ali Ramadan", branch: "شركة فضاء المحركات", position: "موظف", phone: "966531096640"},
            {name: "Jarashot Thonglor", branch: "شركة فضاء المحركات", position: "موظف", phone: "966570481129"},
            {name: "Nokon Jakrong", branch: "شركة فضاء المحركات", position: "موظف", phone: "966570643312"},
            {name: "Kittiphong Kaewphio", branch: "شركة فضاء المحركات", position: "موظف", phone: "966570893607"},
            {name: "Sakda Bindula", branch: "شركة فضاء المحركات", position: "موظف", phone: "966572746930"},
            {name: "Mohammed Khamis", branch: "شركة فضاء المحركات", position: "موظف", phone: "966532543900"}
        ];

        // توليد الأكواد
        const defaultEmployees = rawDefaultEmployees.map(emp => {
            const first3 = emp.name.trim().substring(0, 3);
            const random3 = Math.floor(Math.random() * 900) + 100;
            return {
                ...emp,
                code: `${first3}+${random3}`
            };
        });

        // عرض نافذة إضافة المستخدمين الافتراضيين
        async function showAddDefaultEmployeesModal() {
            // تحميل الجدول
            const tbody = document.querySelector('#defaultEmployeesTable tbody');
            tbody.innerHTML = '';
            
            defaultEmployees.forEach((emp, index) => {
                const row = tbody.insertRow();
                row.innerHTML = `
                    <td><input type="checkbox" class="default-emp-checkbox" data-index="${index}" checked></td>
                    <td>${emp.code}</td>
                    <td>${emp.name}</td>
                    <td>${emp.branch}</td>
                    <td>${emp.position}</td>
                `;
            });
            
            document.getElementById('defaultEmployeesModal').style.display = 'block';
        }

        // إضافة جميع الموظفين الافتراضيين
        async function addAllDefaultEmployees(event) {
            const selected = document.querySelectorAll('.default-emp-checkbox:checked');
            if (selected.length === 0) {
                showAlert('يرجى تحديد موظفين للإضافة', 'error');
                return;
            }
            
            if (!confirm('تحذير: سيتم مسح جميع الموظفين وسجلات الحضور الحالية واستبدالهم بالقائمة الجديدة. هل أنت متأكد؟')) {
                return;
            }
            
            const employeesToAdd = [];
            selected.forEach(checkbox => {
                const index = parseInt(checkbox.dataset.index);
                employeesToAdd.push(defaultEmployees[index]);
            });
            
            try {
                // إظهار رسالة التحميل
                const btn = event ? event.target : document.querySelector('#defaultEmployeesModal .btn-success');
                btn.disabled = true;
                btn.textContent = '⏳ جاري الإضافة...';
                
                const response = await fetch('admin_api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ 
                        action: 'add_default_employees',
                        employees: employeesToAdd,
                        wipe_all: true
                    })
                });
                
                const result = await response.json();
                if (result.success) {
                    showAlert(result.message, 'success');
                    closeModal('defaultEmployeesModal');
                    loadEmployees();
                } else {
                    showAlert(result.message, 'error');
                }
                
                btn.disabled = false;
                btn.textContent = '✅ إضافة الجميع';
            } catch (error) {
                showAlert('حدث خطأ في إضافة المستخدمين', 'error');
                const btn = event ? event.target : document.querySelector('#defaultEmployeesModal .btn-success');
                if (btn) {
                    btn.disabled = false;
                    btn.textContent = '✅ إضافة الجميع';
                }
            }
        }

        // عرض نافذة تسجيل الحضور الجماعي
        async function showBulkAttendanceModal() {
            // تحديد التاريخ الحالي
            const today = new Date().toISOString().split('T')[0];
            document.getElementById('bulkAttendanceDate').value = today;
            
            // تحميل الفروع والموظفين
            await loadBranchesForBulkAttendance();
            await loadEmployeesForBulkAttendance();
            
            // إضافة مراقب لتغيير الفرع
            const branchFilter = document.getElementById('bulkBranchFilter');
            branchFilter.onchange = loadEmployeesForBulkAttendance;
            
            document.getElementById('bulkAttendanceModal').style.display = 'block';
        }

        // تحميل الفروع في اختيار تسجيل الحضور الجماعي
        async function loadBranchesForBulkAttendance() {
            try {
                const response = await fetch('admin_api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'get_branches' })
                });
                
                const result = await response.json();
                if (result.success) {
                    const select = document.getElementById('bulkBranchFilter');
                    select.innerHTML = '<option value="">الكل</option>';
                    
                    result.data.forEach(branch => {
                        const option = document.createElement('option');
                        option.value = branch.id;
                        option.textContent = branch.name;
                        select.appendChild(option);
                    });
                }
            } catch (error) {
                console.error('خطأ في تحميل الفروع:', error);
            }
        }

        // تحميل الموظفين في اختيار تسجيل الحضور الجماعي
        async function loadEmployeesForBulkAttendance() {
            try {
                const response = await fetch('admin_api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'get_employees' })
                });
                
                const result = await response.json();
                if (result.success) {
                    const branchFilter = document.getElementById('bulkBranchFilter').value;
                    
                    const filtered = result.data.filter(emp => {
                        return emp.is_active === '1' && (!branchFilter || emp.branch_id == branchFilter);
                    });
                    
                    const list = document.getElementById('bulkEmployeesList');
                    list.innerHTML = '';
                    
                    if (filtered.length === 0) {
                        list.innerHTML = '<div style="text-align: center; padding: 20px; color: #999;">لا توجد موظفين نشطين</div>';
                        document.getElementById('bulkEmployeesCount').textContent = '';
                        return;
                    }
                    
                    filtered.forEach(emp => {
                        const div = document.createElement('div');
                        div.style.marginBottom = '5px';
                        div.style.padding = '5px';
                        div.style.background = 'white';
                        div.style.borderRadius = '3px';
                        div.innerHTML = `
                            <input type="checkbox" class="bulk-employee-checkbox" data-id="${emp.id}" id="bulk-emp-${emp.id}" checked onchange="updateBulkEmployeesCount()">
                            <label for="bulk-emp-${emp.id}" style="cursor: pointer;"> ${emp.name} (${emp.employee_code}) - ${emp.branch_name}</label>
                        `;
                        list.appendChild(div);
                    });
                    
                    updateBulkEmployeesCount();
                }
            } catch (error) {
                console.error('خطأ في تحميل الموظفين:', error);
                document.getElementById('bulkEmployeesList').innerHTML = 
                    '<div style="text-align: center; padding: 20px; color: #ef4444;">حدث خطأ في تحميل الموظفين</div>';
            }
        }
        
        // تحديث عداد الموظفين المحددين
        function updateBulkEmployeesCount() {
            const total = document.querySelectorAll('.bulk-employee-checkbox').length;
            const selected = document.querySelectorAll('.bulk-employee-checkbox:checked').length;
            document.getElementById('bulkEmployeesCount').textContent = `محدد: ${selected} من ${total} موظف`;
        }

        // اختيار/إلغاء اختيار جميع الموظفين
        function toggleAllBulkEmployees(checked) {
            document.querySelectorAll('.bulk-employee-checkbox').forEach(checkbox => {
                checkbox.checked = checked;
            });
            updateBulkEmployeesCount();
        }

        // تسجيل الحضور الجماعي
        async function submitBulkAttendance() {
            const date = document.getElementById('bulkAttendanceDate').value;
            const checkInTime = document.getElementById('bulkCheckInTime').value;
            const checkOutTime = document.getElementById('bulkCheckOutTime').value;
            
            if (!date) {
                showAlert('يرجى تحديد التاريخ', 'error');
                return;
            }
            
            if (!checkInTime) {
                showAlert('يرجى تحديد وقت الحضور', 'error');
                return;
            }
            
            const selected = document.querySelectorAll('.bulk-employee-checkbox:checked');
            if (selected.length === 0) {
                showAlert('يرجى تحديد موظف واحد على الأقل', 'error');
                return;
            }
            
            const employeeIds = Array.from(selected).map(cb => cb.dataset.id);
            
            if (!confirm(`هل تريد تسجيل حضور ${employeeIds.length} موظف للتاريخ ${date}؟`)) {
                return;
            }
            
            try {
                const response = await fetch('admin_api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'bulk_attendance_record',
                        date: date,
                        check_in_time: checkInTime,
                        check_out_time: checkOutTime || null,
                        employee_ids: employeeIds
                    })
                });
                
                const result = await response.json();
                if (result.success) {
                    showAlert(`تم تسجيل حضور ${employeeIds.length} موظف بنجاح`, 'success');
                    closeModal('bulkAttendanceModal');
                    if (currentTab === 'attendance') {
                        loadAttendanceRecords();
                    }
                } else {
                    showAlert(result.message, 'error');
                }
            } catch (error) {
                showAlert('حدث خطأ في تسجيل الحضور', 'error');
                console.error('خطأ في تسجيل الحضور الجماعي:', error);
            }
        }

        // تحميل الإعدادات
        async function loadSettings() {
            try {
                const response = await fetch('admin_api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'get_settings' })
                });
                
                const result = await response.json();
                if (result.success) {
                    const settings = result.data;
                    document.getElementById('companyName').value = settings.company_name || '';
                    document.getElementById('adminName').value = settings.admin_name || '';
                    document.getElementById('workStartTime').value = settings.work_start_time || '08:00';
                    document.getElementById('gracePeriod').value = settings.grace_period || '30';
                    document.getElementById('latePenalty1').value = settings.late_penalty_1 || '10';
                    document.getElementById('latePenalty2').value = settings.late_penalty_2 || '15';
                    document.getElementById('latePenalty3').value = settings.late_penalty_3 || '25';
                    document.getElementById('latePenalty4').value = settings.late_penalty_4 || '45';
                    document.getElementById('allowEmployeeLogout').checked = settings.allow_employee_logout === '1';
                    
                    // إعدادات الجدولة التلقائية
                    document.getElementById('autoModeEnabled').checked = settings.auto_mode_enabled === '1';
                    document.getElementById('autoCheckInStart').value = settings.auto_check_in_start || '06:00';
                    document.getElementById('autoCheckInEnd').value = settings.auto_check_in_end || '12:00';
                    document.getElementById('autoCheckOutStart').value = settings.auto_check_out_start || '12:01';
                }
            } catch (error) {
                console.error('خطأ في تحميل الإعدادات:', error);
            }
        }

        // حفظ الإعدادات
        async function saveSettings() {
            const settings = {
                action: 'save_settings',
                company_name: document.getElementById('companyName').value,
                admin_name: document.getElementById('adminName').value,
                work_start_time: document.getElementById('workStartTime').value,
                grace_period_minutes: document.getElementById('gracePeriod').value,
                late_penalty_1: document.getElementById('latePenalty1').value,
                late_penalty_2: document.getElementById('latePenalty2').value,
                late_penalty_3: document.getElementById('latePenalty3').value,
                late_penalty_4: document.getElementById('latePenalty4').value,
                allow_employee_logout: document.getElementById('allowEmployeeLogout').checked ? '1' : '0',
                auto_mode_enabled: document.getElementById('autoModeEnabled').checked ? '1' : '0',
                auto_check_in_start: document.getElementById('autoCheckInStart').value,
                auto_check_in_end: document.getElementById('autoCheckInEnd').value,
                auto_check_out_start: document.getElementById('autoCheckOutStart').value
            };
            
            try {
                const response = await fetch('admin_api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(settings)
                });
                
                const result = await response.json();
                if (result.success) {
                    showAlert('✅ تم حفظ الإعدادات بنجاح', 'success');
                } else {
                    showAlert(result.message, 'error');
                }
            } catch (error) {
                showAlert('حدث خطأ في حفظ الإعدادات', 'error');
                console.error('خطأ في حفظ الإعدادات:', error);
            }
        }

        // تعديل نقاط الموظف
        async function adjustPoints(employeeId, employeeName) {
            const points = prompt(`تعديل نقاط الموظف: ${employeeName}\n\nأدخل عدد النقاط:\n• رقم موجب (+) للإضافة\n• رقم سالب (-) للخصم\n• 0 لإعادة التعيين إلى 100`);
            
            if (points === null) return; // ألغى المستخدم
            
            const pointsNum = parseInt(points);
            if (isNaN(pointsNum)) {
                showAlert('يرجى إدخال رقم صحيح', 'error');
                return;
            }
            
            // إعادة تعيين إلى القيمة الافتراضية
            if (pointsNum === 0) {
                if (!confirm(`هل تريد إعادة تعيين نقاط ${employeeName} إلى 100؟`)) {
                    return;
                }
                
                try {
                    const response = await fetch('admin_api.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            action: 'reset_employee_points',
                            employee_id: employeeId
                        })
                    });
                    
                    const result = await response.json();
                    if (result.success) {
                        showAlert(result.message, 'success');
                        loadEmployees();
                    } else {
                        showAlert(result.message, 'error');
                    }
                } catch (error) {
                    showAlert('حدث خطأ في إعادة تعيين النقاط', 'error');
                }
                return;
            }
            
            // إضافة أو خصم النقاط
            const action = pointsNum > 0 ? 'إضافة' : 'خصم';
            const absPoints = Math.abs(pointsNum);
            
            if (!confirm(`هل تريد ${action} ${absPoints} نقطة ${pointsNum > 0 ? 'إلى' : 'من'} ${employeeName}؟`)) {
                return;
            }
            
            try {
                const response = await fetch('admin_api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'adjust_employee_points',
                        employee_id: employeeId,
                        points: pointsNum,
                        reason: `${action} ${absPoints} نقطة من قبل المدير`
                    })
                });
                
                const result = await response.json();
                if (result.success) {
                    showAlert(result.message, 'success');
                    loadEmployees();
                } else {
                    showAlert(result.message, 'error');
                }
            } catch (error) {
                showAlert('حدث خطأ في تعديل النقاط', 'error');
                console.error('خطأ:', error);
            }
        }

        // تسجيل Service Worker للـ PWA
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('/sw.js')
                    .then(reg => console.log('Service Worker registered:', reg.scope))
                    .catch(err => console.log('Service Worker registration failed:', err));
            });
        }

        // ============ Advanced Control Panel Functions ============

        function showSubTab(tabName) {
            // Hide all sections
            document.querySelectorAll('.sub-section').forEach(el => el.style.display = 'none');
            // Remove active class from buttons
            document.querySelectorAll('.sub-tabs .btn').forEach(el => el.classList.remove('active'));
            
            // Show selected section
            document.getElementById('section-' + tabName).style.display = 'block';
            document.getElementById('tab-' + tabName).classList.add('active');
            
            // Load data
            if (tabName === 'rules') loadRules();
            else if (tabName === 'roles') loadRoles();
            else if (tabName === 'logs') loadAuditLogs();
            else if (tabName === 'backups') loadBackups();
        }

        // --- Rules Management ---
        function toggleRuleMode() {
            const isSimple = document.getElementById('simpleRuleMode').checked;
            document.getElementById('simpleRuleBuilder').style.display = isSimple ? 'block' : 'none';
            document.getElementById('advancedRuleEditor').style.display = isSimple ? 'none' : 'block';
            
            // If switching to advanced, sync the value
            if (!isSimple) {
                const variable = document.getElementById('simpleConditionVar').value;
                const threshold = document.getElementById('simpleThreshold').value;
                const points = document.getElementById('simplePoints').value;
                // Generate equation: variable > threshold ? points : 0
                document.getElementById('ruleEquation').value = `${variable} > ${threshold} ? ${points} : 0`;
            }
        }
        
        async function loadRules() {
            try {
                const response = await fetch('admin_api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'get_rules' })
                });
                const result = await response.json();
                if (result.success) {
                    const tbody = document.querySelector('#rulesTable tbody');
                    tbody.innerHTML = '';
                    result.data.forEach(rule => {
                        const row = tbody.insertRow();
                        row.innerHTML = `
                            <td><code>${rule.rule_key}</code></td>
                            <td>${rule.name}</td>
                            <td>${rule.description || '-'}</td>
                            <td><code style="font-size: 11px;">${rule.equation}</code></td>
                            <td>${rule.is_active == 1 ? '<span class="badge badge-success">Active</span>' : '<span class="badge badge-danger">Inactive</span>'}</td>
                            <td>
                                <button onclick='editRule(${JSON.stringify(rule)})' class="btn btn-warning" style="padding: 2px 8px; font-size: 11px;">Edit</button>
                                <button onclick="deleteRule(${rule.id})" class="btn btn-danger" style="padding: 2px 8px; font-size: 11px;">Delete</button>
                            </td>
                        `;
                    });
                }
            } catch (error) { console.error(error); }
        }

        function showRuleModal() {
            document.getElementById('ruleModalTitle').textContent = 'إضافة قاعدة جديدة';
            document.getElementById('ruleForm').reset();
            document.getElementById('ruleId').value = '';
            document.getElementById('ruleKey').disabled = false;
            document.getElementById('ruleModal').style.display = 'block';
        }

        function editRule(rule) {
            document.getElementById('ruleModalTitle').textContent = 'تعديل القاعدة';
            document.getElementById('ruleId').value = rule.id;
            document.getElementById('ruleKey').value = rule.rule_key;
            document.getElementById('ruleKey').disabled = true; // Key cannot be changed
            document.getElementById('ruleName').value = rule.name;
            document.getElementById('ruleDescription').value = rule.description;
            document.getElementById('ruleEquation').value = rule.equation;
            document.getElementById('ruleVariables').value = rule.variables;
            document.getElementById('ruleModal').style.display = 'block';
        }

        document.getElementById('ruleForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            // Logic to handle Simple vs Advanced Mode
            let finalEquation = document.getElementById('ruleEquation').value;
            if (document.getElementById('simpleRuleMode').checked) {
                const variable = document.getElementById('simpleConditionVar').value;
                const threshold = document.getElementById('simpleThreshold').value;
                const points = document.getElementById('simplePoints').value;
                finalEquation = `${variable} > ${threshold} ? ${points} : 0`;
            }

            const data = {
                action: 'save_rule',
                id: document.getElementById('ruleId').value,
                rule_key: document.getElementById('ruleKey').value,
                name: document.getElementById('ruleName').value,
                description: document.getElementById('ruleDescription').value,
                equation: finalEquation,
                variables: document.getElementById('ruleVariables').value
            };
            
            try {
                const response = await fetch('admin_api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });
                const result = await response.json();
                if (result.success) {
                    showAlert('تم حفظ القاعدة بنجاح');
                    closeModal('ruleModal');
                    loadRules();
                } else {
                    showAlert(result.message, 'error');
                }
            } catch (error) { showAlert('حدث خطأ', 'error'); }
        });

        async function deleteRule(id) {
            if (!confirm('هل أنت متأكد من حذف هذه القاعدة؟')) return;
            try {
                const response = await fetch('admin_api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'delete_rule', id: id })
                });
                const result = await response.json();
                if (result.success) {
                    showAlert('تم الحذف بنجاح');
                    loadRules();
                } else {
                    showAlert(result.message, 'error');
                }
            } catch (error) { showAlert('حدث خطأ', 'error'); }
        }

        // --- Roles Management ---
        async function loadRoles() {
            try {
                const response = await fetch('admin_api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'get_roles' })
                });
                const result = await response.json();
                if (result.success) {
                    const tbody = document.querySelector('#rolesTable tbody');
                    tbody.innerHTML = '';
                    result.data.forEach(role => {
                        const row = tbody.insertRow();
                        row.innerHTML = `
                            <td>${role.name}</td>
                            <td>${role.description || '-'}</td>
                            <td>${role.user_count || 0}</td>
                            <td>
                                <button onclick="editRole(${role.id})" class="btn btn-warning" style="padding: 2px 8px; font-size: 11px;">Edit</button>
                                ${role.id > 4 ? `<button onclick="deleteRole(${role.id})" class="btn btn-danger" style="padding: 2px 8px; font-size: 11px;">Delete</button>` : ''}
                            </td>
                        `;
                    });
                }
            } catch (error) { console.error(error); }
        }

        async function showRoleModal() {
            document.getElementById('roleModalTitle').textContent = 'إضافة دور جديد';
            document.getElementById('roleForm').reset();
            document.getElementById('roleId').value = '';
            
            // Load permissions
            await loadPermissionsList();
            
            document.getElementById('roleModal').style.display = 'block';
        }

        async function editRole(id) {
            try {
                // Get Role Details
                const response = await fetch('admin_api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'get_role', id: id })
                });
                const result = await response.json();
                
                if (result.success) {
                    const role = result.data.role;
                    const rolePerms = result.data.permissions;
                    
                    document.getElementById('roleModalTitle').textContent = 'تعديل الدور';
                    document.getElementById('roleId').value = role.id;
                    document.getElementById('roleName').value = role.name;
                    document.getElementById('roleDescription').value = role.description;
                    
                    // Load permissions list first
                    await loadPermissionsList();
                    
                    // Check assigned permissions
                    rolePerms.forEach(p => {
                        const cb = document.getElementById('perm_' + p);
                        if (cb) cb.checked = true;
                    });
                    
                    document.getElementById('roleModal').style.display = 'block';
                }
            } catch (error) { console.error(error); }
        }

        async function loadPermissionsList() {
            try {
                const response = await fetch('admin_api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'get_permissions' })
                });
                const result = await response.json();
                if (result.success) {
                    const container = document.getElementById('permissionsList');
                    container.innerHTML = '';
                    result.data.forEach(perm => {
                        const div = document.createElement('div');
                        div.innerHTML = `
                            <label style="display: flex; align-items: center; gap: 8px; font-size: 13px;">
                                <input type="checkbox" name="permissions[]" value="${perm.id}" id="perm_${perm.id}">
                                ${perm.description || perm.code}
                            </label>
                        `;
                        container.appendChild(div);
                    });
                }
            } catch (error) { console.error(error); }
        }

        document.getElementById('roleForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            const perms = [];
            document.querySelectorAll('input[name="permissions[]"]:checked').forEach(cb => perms.push(cb.value));
            
            const data = {
                action: 'save_role',
                id: document.getElementById('roleId').value,
                name: document.getElementById('roleName').value,
                description: document.getElementById('roleDescription').value,
                permissions: perms
            };
            
            try {
                const response = await fetch('admin_api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });
                const result = await response.json();
                if (result.success) {
                    showAlert('تم حفظ الدور بنجاح');
                    closeModal('roleModal');
                    loadRoles();
                } else {
                    showAlert(result.message, 'error');
                }
            } catch (error) { showAlert('حدث خطأ', 'error'); }
        });

        async function deleteRole(id) {
            if (!confirm('هل أنت متأكد من حذف هذا الدور؟')) return;
            try {
                const response = await fetch('admin_api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'delete_role', id: id })
                });
                const result = await response.json();
                if (result.success) {
                    showAlert('تم حذف الدور بنجاح');
                    loadRoles();
                } else {
                    showAlert(result.message, 'error');
                }
            } catch (error) { showAlert('حدث خطأ', 'error'); }
        }

        // --- Audit Logs ---
        async function loadAuditLogs() {
            try {
                const response = await fetch('admin_api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'get_audit_logs' })
                });
                const result = await response.json();
                if (result.success) {
                    const tbody = document.querySelector('#auditLogsTable tbody');
                    tbody.innerHTML = '';
                    result.data.forEach(log => {
                        const row = tbody.insertRow();
                        row.innerHTML = `
                            <td style="font-size: 12px;">${log.created_at}</td>
                            <td>${log.username || 'System'}</td>
                            <td><span class="badge badge-info">${log.action}</span></td>
                            <td>${log.table_name || '-'}</td>
                            <td style="font-size: 11px; max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                ${log.old_value ? 'Old: ' + log.old_value : ''} 
                                ${log.new_value ? 'New: ' + log.new_value : ''}
                            </td>
                        `;
                    });
                }
            } catch (error) { console.error(error); }
        }

        // --- Backups ---
        async function loadBackups() {
            try {
                const response = await fetch('admin_api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'get_backups' })
                });
                const result = await response.json();
                if (result.success) {
                    const tbody = document.querySelector('#backupsTable tbody');
                    tbody.innerHTML = '';
                    result.data.forEach(backup => {
                        const row = tbody.insertRow();
                        row.innerHTML = `
                            <td>${backup.filename}</td>
                            <td>${(backup.size_bytes / 1024).toFixed(2)} KB</td>
                            <td>${backup.created_at}</td>
                            <td>${backup.note || '-'}</td>
                            <td>
                                <a href="backups/${backup.filename}" download class="btn btn-success" style="padding: 2px 8px; font-size: 11px;">Download</a>
                                <button onclick="deleteBackup(${backup.id})" class="btn btn-danger" style="padding: 2px 8px; font-size: 11px;">Delete</button>
                                <button onclick="restoreBackup(${backup.id})" class="btn btn-warning" style="padding: 2px 8px; font-size: 11px;">Restore</button>
                            </td>
                        `;
                    });
                }
            } catch (error) { console.error(error); }
        }

        async function createBackup() {
            if (!confirm('هل أنت متأكد من إنشاء نسخة احتياطية جديدة؟')) return;
            try {
                const response = await fetch('admin_api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'create_backup' })
                });
                const result = await response.json();
                if (result.success) {
                    showAlert('تم إنشاء النسخة الاحتياطية بنجاح');
                    loadBackups();
                } else {
                    showAlert(result.message, 'error');
                }
            } catch (error) { showAlert('حدث خطأ', 'error'); }
        }

        async function deleteBackup(id) {
            if (!confirm('هل أنت متأكد من حذف هذه النسخة الاحتياطية؟')) return;
            try {
                const response = await fetch('admin_api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'delete_backup', id: id })
                });
                const result = await response.json();
                if (result.success) {
                    showAlert('تم الحذف بنجاح');
                    loadBackups();
                } else {
                    showAlert(result.message, 'error');
                }
            } catch (error) { showAlert('حدث خطأ', 'error'); }
        }

        async function restoreBackup(id) {
            if (!confirm('تحذير: استعادة النسخة الاحتياطية ستقوم بمسح البيانات الحالية واستبدالها بالنسخة. هل أنت متأكد تماماً؟')) return;
            try {
                // This would typically need a separate handler as it might involve complex DB operations
                // For now we just call the API endpoint if it exists
                showAlert('ميزة الاستعادة تتطلب صلاحيات خاصة (تجريبية)', 'warning');
            } catch (error) { showAlert('حدث خطأ', 'error'); }
        }
    </script>
</body>
</html>