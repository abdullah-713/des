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
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="صرح انضباط">
    <meta name="format-detection" content="telephone=no">
    <link rel="manifest" href="manifest.json">
    <link rel="apple-touch-icon" href="<?php echo htmlspecialchars($logoPath ?: 'logo.png'); ?>">
    <link rel="icon" type="image/png" href="<?php echo htmlspecialchars($logoPath ?: 'logo.png'); ?>">
    <title><?php echo htmlspecialchars($companyName); ?> - <?php echo __('app_name'); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Cairo', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: radial-gradient(circle at top, rgba(249, 115, 22, 0.12), transparent 55%), linear-gradient(135deg, #fff8f1, #ffffff);
            padding: 20px;
            direction: <?php echo ($_SESSION['lang'] ?? 'ar') === 'ar' ? 'rtl' : 'ltr'; ?>;
        }
        
        /* Language Switcher */
        .lang-switcher {
            position: absolute;
            top: 20px;
            right: 20px;
            z-index: 10;
        }
        
        .lang-btn {
            background: white;
            color: #c2410c;
            border: 1px solid #f2d7bf;
            padding: 6px 16px;
            border-radius: 999px;
            cursor: pointer;
            text-decoration: none;
            font-size: 14px;
            font-weight: 700;
            box-shadow: 0 10px 20px -16px rgba(15, 23, 42, 0.45);
        }
        
        .login-container {
            background: white;
            padding: 40px 30px;
            border-radius: 26px;
            box-shadow: 0 24px 50px -35px rgba(15, 23, 42, 0.7);
            width: 100%;
            max-width: 420px;
            text-align: center;
            border: 1px solid #f2d7bf;
        }
        
        @media (max-width: 768px) {
            body {
                align-items: flex-end;
            }
            
            .login-container {
                padding: 35px 25px;
                max-width: 95%;
                border-radius: 20px;
                margin-bottom: 12px;
            }
        }
        
        @media (max-width: 480px) {
            .login-container {
                padding: 30px 20px;
                max-width: 100%;
                border-radius: 16px;
            }
        }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .logo {
            width: 90px;
            height: 90px;
            margin: 0 auto 20px;
            border-radius: 18px;
            object-fit: contain;
            background: white;
            padding: 10px;
            box-shadow: 0 14px 28px -18px rgba(194, 65, 12, 0.6);
        }
        
        @media (max-width: 768px) {
            .logo {
                width: 80px;
                height: 80px;
                margin-bottom: 15px;
            }
        }
        
        @media (max-width: 480px) {
            .logo {
                width: 70px;
                height: 70px;
                border-radius: 14px;
            }
        }
        
        .app-title {
            font-size: 2rem;
            font-weight: 900;
            background: linear-gradient(135deg, #f97316, #c2410c);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 8px;
            line-height: 1.2;
        }
        
        @media (max-width: 768px) {
            .app-title {
                font-size: 1.8rem;
            }
        }
        
        @media (max-width: 480px) {
            .app-title {
                font-size: 1.5rem;
            }
        }
        
        .app-subtitle {
            font-size: 1rem;
            color: #6b7280;
            margin-bottom: 30px;
            font-weight: 600;
        }
        
        @media (max-width: 480px) {
            .app-subtitle {
                font-size: 0.9rem;
                margin-bottom: 25px;
            }
        }
        
        .welcome-text {
            font-size: 1.2rem;
            color: #1f2937;
            margin-bottom: 25px;
            font-weight: 700;
        }
        
        @media (max-width: 480px) {
            .welcome-text {
                font-size: 1.1rem;
                margin-bottom: 20px;
            }
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        .form-group label {
            display: block;
            font-size: 1rem;
            font-weight: 700;
            color: #374151;
            margin-bottom: 10px;
            text-align: right;
        }
        
        .form-group input {
            width: 100%;
            padding: 15px 20px;
            font-size: 1.05rem;
            font-weight: 700;
            text-align: center;
            border: 2px solid #f2d7bf;
            border-radius: 16px;
            background: #fff7ed;
            transition: all 0.3s ease;
            font-family: 'Cairo', sans-serif;
            letter-spacing: 1px;
        }
        
        @media (max-width: 768px) {
            .form-group input {
                padding: 14px 18px;
                font-size: 1rem;
            }
        }
        
        @media (max-width: 480px) {
            .form-group input {
                padding: 12px 15px;
                font-size: 0.95rem;
                border-radius: 12px;
            }
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #f97316;
            background: white;
            box-shadow: 0 0 0 4px rgba(249, 115, 22, 0.12);
        }
        
        .form-group input::placeholder {
            color: #9ca3af;
            letter-spacing: normal;
        }
        
        .btn-login {
            width: 100%;
            padding: 15px;
            font-size: 1.1rem;
            font-weight: 800;
            color: white;
            background: linear-gradient(135deg, #f97316, #c2410c);
            border: none;
            border-radius: 16px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-family: 'Cairo', sans-serif;
            box-shadow: 0 16px 30px -18px rgba(194, 65, 12, 0.7);
        }
        
        @media (max-width: 768px) {
            .btn-login {
                padding: 14px;
                font-size: 1.05rem;
            }
        }
        
        @media (max-width: 480px) {
            .btn-login {
                padding: 13px;
                font-size: 1rem;
                border-radius: 12px;
            }
        }
        
        .btn-login:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 35px rgba(249, 115, 22, 0.4);
        }
        
        .btn-login:active {
            transform: translateY(-1px);
        }
        
        .error-message {
            background: linear-gradient(135deg, #fff1f2, #ffe4e6);
            color: #dc2626;
            padding: 12px 18px;
            border-radius: 14px;
            margin-bottom: 20px;
            font-weight: 700;
            border: 1px solid #fecdd3;
            animation: shake 0.5s ease-in-out;
            font-size: 0.95rem;
        }
        
        @media (max-width: 480px) {
            .error-message {
                padding: 10px 15px;
                font-size: 0.9rem;
                margin-bottom: 15px;
            }
        }
        
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }
        
        .clock {
            font-size: 2.2rem;
            font-weight: 900;
            color: #f97316;
            margin-bottom: 15px;
            font-feature-settings: "tnum";
            font-variant-numeric: tabular-nums;
        }
        
        @media (max-width: 768px) {
            .clock {
                font-size: 2rem;
            }
        }
        
        @media (max-width: 480px) {
            .clock {
                font-size: 1.8rem;
                margin-bottom: 12px;
            }
        }
        
        .date-display {
            font-size: 0.95rem;
            color: #6b7280;
            margin-bottom: 25px;
            font-weight: 600;
        }
        
        @media (max-width: 480px) {
            .date-display {
                font-size: 0.85rem;
                margin-bottom: 20px;
            }
        }
        
        .footer-note {
            margin-top: 25px;
            font-size: 0.85rem;
            color: #9ca3af;
        }
        
        @media (max-width: 480px) {
            .footer-note {
                margin-top: 20px;
                font-size: 0.8rem;
            }
        }
        
        /* تأثير الجسيمات */
        .particles {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            overflow: hidden;
            z-index: 0;
        }
        
        .particle {
            position: absolute;
            width: 6px;
            height: 6px;
            background: rgba(249, 115, 22, 0.3);
            border-radius: 50%;
            animation: float 15s infinite;
        }
        
        @keyframes float {
            0%, 100% {
                transform: translateY(100vh) rotate(0deg);
                opacity: 0;
            }
            10% {
                opacity: 1;
            }
            90% {
                opacity: 1;
            }
            100% {
                transform: translateY(-100vh) rotate(720deg);
                opacity: 0;
            }
        }
    </style>
</head>
<body>
    <div class="lang-switcher">
        <a href="?lang=<?php echo ($_SESSION['lang'] ?? 'ar') === 'ar' ? 'en' : 'ar'; ?>" class="lang-btn">
            <?php echo ($_SESSION['lang'] ?? 'ar') === 'ar' ? 'English' : 'العربية'; ?>
        </a>
    </div>
    
    <div class="login-container">
        <?php if (file_exists($logoPath)): ?>
        <img src="<?php echo $logoPath; ?>" alt="Logo" class="logo">
        <?php endif; ?>
        
        <h1 class="app-title"><?php echo htmlspecialchars($companyName); ?></h1>
        <p class="app-subtitle"><?php echo __('app_name'); ?></p>
        
        <div class="clock" id="clock">--:--:--</div>
        <div class="date-display" id="dateDisplay"><?php echo ($_SESSION['lang'] ?? 'ar') === 'ar' ? 'جاري التحميل...' : 'Loading...'; ?></div>
        
        <p class="welcome-text">👋 <?php echo __('welcome'); ?></p>
        
        <?php if ($error): ?>
        <div class="error-message">
            ⚠️ <?php echo htmlspecialchars($error); ?>
        </div>
        <?php endif; ?>
        
        <form method="POST" autocomplete="off">
            <div class="form-group">
                <label>🔢 <?php echo __('employee_code'); ?></label>
                <input type="text" name="employee_code" placeholder="<?php echo ($_SESSION['lang'] ?? 'ar') === 'ar' ? 'مثال: أحمد 123' : 'e.g. Ahmed 123'; ?>" autofocus required value="<?php echo htmlspecialchars($_POST['employee_code'] ?? ''); ?>">
            </div>
            
            <div class="form-group">
                <label>🔑 كلمة المرور</label>
                <input type="password" name="password" placeholder="<?php echo ($_SESSION['lang'] ?? 'ar') === 'ar' ? 'اتركها فارغة إذا لم تحدد كلمة مرور' : 'Leave empty if no password set'; ?>">
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
            const lang = '<?php echo ($_SESSION['lang'] ?? 'ar') === 'ar' ? 'ar-SA' : 'en-US'; ?>';
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
