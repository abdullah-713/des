<?php
/**
 * فحص حالة الخادم ومتطلبات النظام
 * Server Status and Requirements Check
 */

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>فحص الخادم - صرح انضباط</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f0f2f5;
            margin: 0;
            padding: 20px;
            direction: rtl;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            text-align: center;
            margin-bottom: 30px;
        }
        .check-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            margin: 10px 0;
            border-radius: 8px;
            border-right: 4px solid #ddd;
        }
        .check-item.success {
            background: #f0f9ff;
            border-right-color: #10b981;
        }
        .check-item.error {
            background: #fef2f2;
            border-right-color: #ef4444;
        }
        .check-item.warning {
            background: #fffbeb;
            border-right-color: #f59e0b;
        }
        .status {
            font-weight: bold;
            padding: 5px 15px;
            border-radius: 20px;
            color: white;
        }
        .status.ok { background: #10b981; }
        .status.error { background: #ef4444; }
        .status.warning { background: #f59e0b; }
        .info {
            background: #f8fafc;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            border: 1px solid #e2e8f0;
        }
        .btn {
            display: inline-block;
            padding: 12px 25px;
            background: #f97316;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: bold;
            margin: 10px 5px;
        }
        .btn:hover {
            background: #c2410c;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 فحص حالة الخادم</h1>
        
        <div class="info">
            <h3>معلومات الخادم:</h3>
            <p><strong>التاريخ والوقت:</strong> <?php echo date('Y-m-d H:i:s'); ?></p>
            <p><strong>إصدار PHP:</strong> <?php echo PHP_VERSION; ?></p>
            <p><strong>نظام التشغيل:</strong> <?php echo PHP_OS; ?></p>
            <p><strong>الخادم:</strong> <?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'غير محدد'; ?></p>
        </div>

        <h3>فحص المتطلبات:</h3>

        <?php
        $checks = [
            'PHP 7.4+' => version_compare(PHP_VERSION, '7.4.0', '>='),
            'PDO Extension' => extension_loaded('pdo'),
            'PDO MySQL' => extension_loaded('pdo_mysql'),
            'GD Extension' => extension_loaded('gd'),
            'MBString Extension' => extension_loaded('mbstring'),
            'JSON Extension' => extension_loaded('json'),
            'Session Support' => function_exists('session_start'),
            'File Write Permission' => is_writable('.'),
            'Upload Directory' => is_dir('uploads') || mkdir('uploads', 0755, true)
        ];

        foreach ($checks as $name => $status) {
            $class = $status ? 'success' : 'error';
            $statusText = $status ? 'متوفر' : 'غير متوفر';
            $statusClass = $status ? 'ok' : 'error';
            
            echo "<div class='check-item $class'>";
            echo "<span>$name</span>";
            echo "<span class='status $statusClass'>$statusText</span>";
            echo "</div>";
        }
        ?>

        <h3>فحص الملفات المطلوبة:</h3>

        <?php
        $files = [
            'config.php' => 'ملف الإعدادات',
            'database_production.sql' => 'ملف قاعدة البيانات',
            'employee.php' => 'واجهة الموظفين',
            'admin.php' => 'لوحة الإدارة',
            'attendance_api.php' => 'API الحضور',
            'admin_api.php' => 'API الإدارة'
        ];

        foreach ($files as $file => $description) {
            $exists = file_exists($file);
            $class = $exists ? 'success' : 'warning';
            $statusText = $exists ? 'موجود' : 'غير موجود';
            $statusClass = $exists ? 'ok' : 'warning';
            
            echo "<div class='check-item $class'>";
            echo "<span>$description ($file)</span>";
            echo "<span class='status $statusClass'>$statusText</span>";
            echo "</div>";
        }
        ?>

        <h3>اختبار قاعدة البيانات:</h3>
        
        <?php
        try {
            if (file_exists('config.php')) {
                require_once 'config.php';
                if (defined('DB_HOST')) {
                    $pdo = new PDO("mysql:host=" . DB_HOST, DB_USER, DB_PASS);
                    echo "<div class='check-item success'>";
                    echo "<span>الاتصال بقاعدة البيانات</span>";
                    echo "<span class='status ok'>نجح</span>";
                    echo "</div>";
                    
                    // فحص قاعدة البيانات
                    $stmt = $pdo->prepare("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?");
                    $stmt->execute([DB_NAME]);
                    $dbExists = $stmt->fetch() !== false;
                    
                    $class = $dbExists ? 'success' : 'warning';
                    $statusText = $dbExists ? 'موجودة' : 'غير موجودة';
                    $statusClass = $dbExists ? 'ok' : 'warning';
                    
                    echo "<div class='check-item $class'>";
                    echo "<span>قاعدة البيانات (" . DB_NAME . ")</span>";
                    echo "<span class='status $statusClass'>$statusText</span>";
                    echo "</div>";
                } else {
                    echo "<div class='check-item warning'>";
                    echo "<span>ملف الإعدادات غير مكتمل</span>";
                    echo "<span class='status warning'>يحتاج إعداد</span>";
                    echo "</div>";
                }
            } else {
                echo "<div class='check-item warning'>";
                echo "<span>ملف الإعدادات غير موجود</span>";
                echo "<span class='status warning'>يحتاج تثبيت</span>";
                echo "</div>";
            }
        } catch (Exception $e) {
            echo "<div class='check-item error'>";
            echo "<span>خطأ في قاعدة البيانات: " . htmlspecialchars($e->getMessage()) . "</span>";
            echo "<span class='status error'>فشل</span>";
            echo "</div>";
        }
        ?>

        <div style="text-align: center; margin-top: 30px;">
            <h3>الخطوات التالية:</h3>
            
            <?php if (!file_exists('config.php') || !defined('DB_HOST')): ?>
                <?php if (file_exists('setup_simple.php')): ?>
                    <a href="setup_simple.php" class="btn">🚀 بدء التثبيت المبسط</a>
                <?php endif; ?>
                <?php if (file_exists('install.php')): ?>
                    <a href="install.php" class="btn">⚙️ معالج التثبيت الكامل</a>
                <?php endif; ?>
                <?php if (file_exists('update_system.php')): ?>
                    <a href="update_system.php" class="btn">🔄 تحديث النظام</a>
                <?php endif; ?>
            <?php else: ?>
            <a href="index.php" class="btn">🏠 الصفحة الرئيسية</a>
            <a href="employee.php" class="btn">👥 واجهة الموظفين</a>
            <a href="admin.php" class="btn">🔧 لوحة الإدارة</a>
            <?php endif; ?>
        </div>

        <div class="info" style="margin-top: 30px;">
            <h4>معلومات إضافية:</h4>
            <p><strong>مسار الملفات:</strong> <?php echo __DIR__; ?></p>
            <p><strong>رابط النظام:</strong> <a href="<?php echo 'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']); ?>"><?php echo 'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']); ?></a></p>
            <p><strong>وقت آخر تحديث:</strong> <?php echo date('Y-m-d H:i:s', filemtime(__FILE__)); ?></p>
        </div>
    </div>
</body>
</html>
