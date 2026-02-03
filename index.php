<?php
require_once 'config.php';

// التحقق من إدخال رقم الموظف
$error = '';
$companyName = SystemSettings::get('company_name', 'صرح انضباط');
$allowLogout = SystemSettings::get('allow_employee_logout', '0');

if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'admin') {
        header('Location: admin.php');
    } else {
        header('Location: profile.php');
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['username'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'] ?? '';
    
    if (empty($username)) {
        $error = 'يرجى إدخال اسم المستخدم / رقم الموظف';
    } else {
        try {
            $db = Database::getInstance()->getConnection();
            // Check Users Table
            $stmt = $db->prepare("SELECT id, username, password, role, full_name, is_active FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch();
            
            if ($user) {
                if (!$user['is_active']) {
                    $error = 'تم تعطيل هذا الحساب. يرجى مراجعة الإدارة.';
                } elseif (password_verify($password, $user['password'])) {
                    // Login Success
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['full_name'] = $user['full_name'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['lang'] = 'ar'; // Default

                    // Update last login
                    $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")->execute([$user['id']]);

                    // Legacy Support: Link to Employee table if exists
                    $empStmt = $db->prepare("SELECT id, employee_code, name FROM employees WHERE employee_code = ?");
                    $empStmt->execute([$user['username']]);
                    $employee = $empStmt->fetch();

                    if ($employee) {
                        $_SESSION['employee_id'] = $employee['id'];
                        $_SESSION['employee_code'] = $employee['employee_code'];
                        $_SESSION['employee_name'] = $employee['name'];
                    }

                    if ($user['role'] === 'admin') {
                        header('Location: admin.php');
                    } else {
                        header('Location: profile.php');
                    }
                    exit;
                } else {
                    $error = 'كلمة المرور غير صحيحة';
                }
            } else {
                // Check if migration hasn't happened yet? 
                // Fallback to old table just in case, or show error "User not found"
                // But better to enforce new system.
                 $error = 'اسم المستخدم غير صحيح';
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes, viewport-fit=cover">
    <meta name="theme-color" content="#6366f1">
    <meta name="color-scheme" content="light dark">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="صرح انضباط">
    <meta name="format-detection" content="telephone=no">
    <meta name="description" content="نظام الحضور الذكي - صرح انضباط">
    <link rel="manifest" href="manifest.json">
    <link rel="apple-touch-icon" href="<?php echo htmlspecialchars($logoPath ?: 'logo.png'); ?>">
    <link rel="icon" type="image/png" href="<?php echo htmlspecialchars($logoPath ?: 'logo.png'); ?>">
    <title><?php echo htmlspecialchars($companyName); ?> - <?php echo __('app_name'); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/login.css?v=<?php echo time(); ?>">
    <script src="assets/js/pwa.js" defer></script>
</head>
<body dir="<?php echo ($_SESSION['lang'] ?? 'ar') === 'ar' ? 'rtl' : 'ltr'; ?>">
    <!-- Floating Particles -->
    <div class="particles">
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
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
                <label>� <?php echo (__('username') !== 'username' ? __('username') : 'اسم المستخدم'); ?></label>
                <input type="text" name="username" inputmode="text" autocomplete="username" placeholder="<?php echo 'أدخل اسم المستخدم أو رقم الموظف'; ?>" autofocus required value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
            </div>
            
            <div class="form-group">
                <label>🔑 <?php echo (__('password') !== 'password' ? __('password') : 'كلمة المرور'); ?></label>
                <input type="password" name="password" autocomplete="current-password" placeholder="<?php echo 'أدخل كلمة المرور'; ?>" required>
            </div>
            
            <button type="submit" class="btn-login">
                🚀 <?php echo __('login'); ?>
            </button>
        </form>
        
        <p class="footer-note">
            📱 <?php echo __('app_name'); ?>
        </p>
        
        <div class="admin-link">
            <a href="admin.php">🔐 <?php echo ($_SESSION['lang'] ?? 'ar') === 'ar' ? 'لوحة الإدارة' : 'Admin Panel'; ?></a>
        </div>
    </div>
    
    <script>
        // Update Clock
        function updateClock() {
            const now = new Date();
            const hours = String(now.getHours()).padStart(2, '0');
            const minutes = String(now.getMinutes()).padStart(2, '0');
            const seconds = String(now.getSeconds()).padStart(2, '0');
            document.getElementById('clock').textContent = `${hours}:${minutes}:${seconds}`;
            
            // Update Date
            const lang = '<?php echo ($_SESSION['lang'] ?? 'ar') === 'ar' ? 'ar-SA' : 'en-US'; ?>';
            const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
            document.getElementById('dateDisplay').textContent = now.toLocaleDateString(lang, options);
        }
        
        updateClock();
        setInterval(updateClock, 1000);
        
        // Focus on input
        const employeeInput = document.querySelector('input[name="employee_code"]');
        if (employeeInput) employeeInput.focus();
        
        // Register Service Worker
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', async () => {
                try {
                    const registration = await navigator.serviceWorker.register('/sw.js', { scope: '/' });
                    console.log('✅ Service Worker registered:', registration.scope);
                    
                    // Check for updates
                    registration.addEventListener('updatefound', () => {
                        const newWorker = registration.installing;
                        newWorker.addEventListener('statechange', () => {
                            if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                                // New version available
                                if (confirm('يتوفر تحديث جديد. هل تريد التحديث الآن؟')) {
                                    newWorker.postMessage({ type: 'SKIP_WAITING' });
                                    window.location.reload();
                                }
                            }
                        });
                    });
                } catch (error) {
                    console.log('❌ Service Worker registration failed:', error);
                }
            });
        }
        
        // Handle install prompt
        let deferredPrompt;
        window.addEventListener('beforeinstallprompt', (e) => {
            e.preventDefault();
            deferredPrompt = e;
            console.log('📲 Install prompt available');
        });
        
        // Dark theme detection
        if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
            document.body.dataset.theme = 'dark';
        }
        
        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', e => {
            document.body.dataset.theme = e.matches ? 'dark' : 'light';
        });
    </script>
</body>
</html>
