<?php
require_once 'config.php';

// التحقق من إدخال رقم الموظف
$error = '';
$companyName = SystemSettings::get('company_name', 'صرح انضباط');
$allowLogout = SystemSettings::get('allow_employee_logout', '0');

if (isset($_SESSION['employee_code'])) {
    header('Location: employee.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['employee_code'])) {
    $employeeCode = trim($_POST['employee_code']);
    $password = $_POST['password'] ?? '';
    
    if (empty($employeeCode)) {
        $error = 'يرجى إدخال رقم الموظف';
    } else {
        try {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare("SELECT id, name, branch_id, password FROM employees WHERE employee_code = ? AND is_active = 1");
            $stmt->execute([$employeeCode]);
            $employee = $stmt->fetch();
            
            if ($employee) {
                // التحقق من كلمة المرور إذا كانت معينة
                if (!empty($employee['password'])) {
                    if (empty($password)) {
                        $error = 'هذا الحساب محمي. يرجى إدخال كلمة المرور.';
                    } elseif (!password_verify($password, $employee['password'])) {
                        $error = 'كلمة المرور غير صحيحة';
                    } else {
                        // كلمة المرور صحيحة
                        $_SESSION['employee_code'] = $employeeCode;
                        $_SESSION['employee_name'] = $employee['name'];
                        $_SESSION['employee_id'] = $employee['id'];
                        header('Location: employee.php');
                        exit;
                    }
                } else {
                    // لا توجد كلمة مرور، تسجيل دخول مباشر
                    $_SESSION['employee_code'] = $employeeCode;
                    $_SESSION['employee_name'] = $employee['name'];
                    $_SESSION['employee_id'] = $employee['id'];
                    header('Location: employee.php');
                    exit;
                }
            } else {
                $error = 'رقم الموظف غير صحيح أو غير مفعل';
            }
        } catch (Exception $e) {
            $error = 'حدث خطأ في النظام. يرجى المحاولة لاحقاً.';
        }
    }
}

// الحصول على شعار الشركة
$logoPath = 'uploads/logo.png';
if (!file_exists($logoPath)) {
    $logoPath = 'logo.png';
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#f97316">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="صرح انضباط">
    <link rel="manifest" href="manifest.json">
    <link rel="apple-touch-icon" href="<?php echo htmlspecialchars($logoPath ?: 'logo.png'); ?>">
    <title><?php echo htmlspecialchars($companyName); ?> - <?php echo __('app_name'); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style-login.css">
</head>
<body>
    <div class="lang-switcher">
        <a href="?lang=<?php echo $_SESSION['lang'] === 'ar' ? 'en' : 'ar'; ?>" class="lang-btn">
            <?php echo $_SESSION['lang'] === 'ar' ? 'English' : 'العربية'; ?>
        </a>
    </div>
    
    <div class="login-container">
        <?php if (file_exists($logoPath)): ?>
        <img src="<?php echo $logoPath; ?>" alt="Logo" class="logo">
        <?php endif; ?>
        
        <h1 class="app-title"><?php echo htmlspecialchars($companyName); ?></h1>
        <p class="app-subtitle"><?php echo __('app_name'); ?></p>
        
        <div class="clock" id="clock">--:--:--</div>
        <div class="date-display" id="dateDisplay"><?php echo $_SESSION['lang'] === 'ar' ? 'جاري التحميل...' : 'Loading...'; ?></div>
        
        <p class="welcome-text">👋 <?php echo __('welcome'); ?></p>
        
        <?php if ($error): ?>
        <div class="error-message">
            ⚠️ <?php echo htmlspecialchars($error); ?>
        </div>
        <?php endif; ?>
        
        <form method="POST" autocomplete="off">
            <div class="form-group">
                <label>🔢 <?php echo __('employee_code'); ?></label>
                <input type="text" name="employee_code" placeholder="<?php echo $_SESSION['lang'] === 'ar' ? 'مثال: أحمد 123' : 'e.g. Ahmed 123'; ?>" autofocus required value="<?php echo htmlspecialchars($_POST['employee_code'] ?? ''); ?>">
            </div>
            
            <div class="form-group">
                <label>🔑 كلمة المرور</label>
                <input type="password" name="password" placeholder="<?php echo $_SESSION['lang'] === 'ar' ? 'اتركها فارغة إذا لم تحدد كلمة مرور' : 'Leave empty if no password set'; ?>">
            </div>
            
            <button type="submit" class="btn-login">
                🚀 <?php echo __('login'); ?>
            </button>
        </form>
        
        <p class="footer-note">
            📱 <?php echo __('app_name'); ?>
        </p>
    </div>
    
    <script>
        // تحديث الساعة
        function updateClock() {
            const now = new Date();
            const hours = String(now.getHours()).padStart(2, '0');
            const minutes = String(now.getMinutes()).padStart(2, '0');
            const seconds = String(now.getSeconds()).padStart(2, '0');
            document.getElementById('clock').textContent = `${hours}:${minutes}:${seconds}`;
            
            // تحديث التاريخ
            const lang = '<?php echo $_SESSION['lang'] === 'ar' ? 'ar-SA' : 'en-US'; ?>';
            const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
            document.getElementById('dateDisplay').textContent = now.toLocaleDateString(lang, options);
        }
        
        updateClock();
        setInterval(updateClock, 1000);
        
        // تركيز على حقل الإدخال
        document.querySelector('input[name="employee_code"]').focus();
        
        // تسجيل Service Worker للـ PWA
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('/sw.js')
                    .then(reg => console.log('Service Worker registered:', reg.scope))
                    .catch(err => console.log('Service Worker registration failed:', err));
            });
        }
    </script>
</body>
</html>
