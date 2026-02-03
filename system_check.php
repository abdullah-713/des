<?php
/**
 * أداة فحص صحة النظام
 * System Health Check Tool
 * 
 * يفحص هذا الملف جميع مكونات النظام ويعطي تقريراً شاملاً
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>فحص صحة النظام</title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Cairo', sans-serif;
            background: #f8fafc;
            padding: 20px;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        h1 {
            color: #0f172a;
            font-size: 2rem;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .subtitle {
            color: #64748b;
            margin-bottom: 30px;
            font-size: 1rem;
        }
        .check-section {
            margin-bottom: 30px;
        }
        .check-section h2 {
            color: #1e293b;
            font-size: 1.3rem;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e2e8f0;
        }
        .check-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px;
            margin-bottom: 8px;
            background: #f8fafc;
            border-radius: 8px;
            border-right: 4px solid transparent;
        }
        .check-item.success {
            border-right-color: #10b981;
            background: #f0fdf4;
        }
        .check-item.warning {
            border-right-color: #f59e0b;
            background: #fffbeb;
        }
        .check-item.error {
            border-right-color: #ef4444;
            background: #fef2f2;
        }
        .check-label {
            font-weight: 600;
            color: #374151;
        }
        .check-value {
            font-weight: 700;
            padding: 4px 12px;
            border-radius: 6px;
        }
        .check-value.success {
            background: #10b981;
            color: white;
        }
        .check-value.warning {
            background: #f59e0b;
            color: white;
        }
        .check-value.error {
            background: #ef4444;
            color: white;
        }
        .summary {
            background: linear-gradient(135deg, #f97316, #c2410c);
            color: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 30px;
            text-align: center;
        }
        .summary h3 {
            font-size: 1.5rem;
            margin-bottom: 10px;
        }
        .stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-top: 15px;
        }
        .stat-item {
            background: rgba(255,255,255,0.2);
            padding: 15px;
            border-radius: 8px;
        }
        .stat-value {
            font-size: 2rem;
            font-weight: 800;
        }
        .stat-label {
            font-size: 0.9rem;
            opacity: 0.9;
        }
        .code-block {
            background: #1e293b;
            color: #e2e8f0;
            padding: 15px;
            border-radius: 8px;
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
            overflow-x: auto;
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>
            <span>🔍</span>
            فحص صحة النظام
        </h1>
        <p class="subtitle">فحص شامل لجميع مكونات نظام الحضور</p>

        <?php
        $totalChecks = 0;
        $passedChecks = 0;
        $warnings = 0;
        $errors = 0;

        // فحص PHP
        echo '<div class="check-section">';
        echo '<h2>📌 فحص PHP</h2>';
        
        $phpVersion = PHP_VERSION;
        $phpOk = version_compare($phpVersion, '7.4.0', '>=');
        $totalChecks++;
        if ($phpOk) $passedChecks++;
        displayCheck('إصدار PHP', $phpVersion, $phpOk);
        
        $extensions = ['pdo', 'pdo_mysql', 'mbstring', 'json', 'session'];
        foreach ($extensions as $ext) {
            $totalChecks++;
            $loaded = extension_loaded($ext);
            if ($loaded) $passedChecks++; else $errors++;
            displayCheck("امتداد $ext", $loaded ? 'مثبت' : 'غير مثبت', $loaded);
        }
        
        echo '</div>';

        // فحص الملفات
        echo '<div class="check-section">';
        echo '<h2>📁 فحص الملفات</h2>';
        
        $requiredFiles = [
            'config.php' => 'ملف الإعدادات',
            'database_production.sql' => 'قاعدة البيانات',
            'admin.php' => 'لوحة الإدارة',
            'employee.php' => 'واجهة الموظف',
            'index.php' => 'الصفحة الرئيسية'
        ];
        
        foreach ($requiredFiles as $file => $desc) {
            $totalChecks++;
            $exists = file_exists($file);
            if ($exists) $passedChecks++; else $errors++;
            displayCheck($desc, $exists ? 'موجود' : 'مفقود', $exists);
        }
        
        echo '</div>';

        // فحص الصلاحيات
        echo '<div class="check-section">';
        echo '<h2>🔐 فحص الصلاحيات</h2>';
        
        $totalChecks++;
        $writable = is_writable('.');
        if ($writable) $passedChecks++; else $errors++;
        displayCheck('صلاحيات الكتابة في المجلد الحالي', $writable ? 'متاحة' : 'غير متاحة', $writable);
        
        $uploadDir = 'uploads';
        if (!is_dir($uploadDir)) {
            @mkdir($uploadDir, 0755, true);
        }
        $totalChecks++;
        $uploadWritable = is_writable($uploadDir);
        if ($uploadWritable) $passedChecks++; else $warnings++;
        displayCheck('صلاحيات مجلد uploads', $uploadWritable ? 'متاحة' : 'غير متاحة', $uploadWritable, 'warning');
        
        echo '</div>';

        // فحص قاعدة البيانات
        echo '<div class="check-section">';
        echo '<h2>🗄️ فحص قاعدة البيانات</h2>';
        
        if (file_exists('config.php')) {
            try {
                require_once 'config.php';
                $db = Database::getInstance()->getConnection();
                
                $totalChecks++;
                $passedChecks++;
                displayCheck('الاتصال بقاعدة البيانات', 'ناجح', true);
                
                // فحص الجداول
                $requiredTables = ['employees', 'branches', 'attendance_records', 'system_settings', 'users'];
                foreach ($requiredTables as $table) {
                    $totalChecks++;
                    try {
                        $stmt = $db->query("SHOW TABLES LIKE '$table'");
                        $exists = $stmt->rowCount() > 0;
                        if ($exists) $passedChecks++; else $errors++;
                        displayCheck("جدول $table", $exists ? 'موجود' : 'مفقود', $exists);
                    } catch (Exception $e) {
                        $errors++;
                        displayCheck("جدول $table", 'خطأ', false);
                    }
                }
                
                // إحصائيات البيانات
                try {
                    $stmt = $db->query("SELECT COUNT(*) as count FROM employees");
                    $empCount = $stmt->fetch()['count'];
                    displayCheck('عدد الموظفين', $empCount, true, 'info');
                    
                    $stmt = $db->query("SELECT COUNT(*) as count FROM branches");
                    $branchCount = $stmt->fetch()['count'];
                    displayCheck('عدد الفروع', $branchCount, true, 'info');
                } catch (Exception $e) {
                    // تجاهل
                }
                
            } catch (Exception $e) {
                $totalChecks++;
                $errors++;
                displayCheck('الاتصال بقاعدة البيانات', 'فشل: ' . $e->getMessage(), false);
            }
        } else {
            displayCheck('ملف config.php', 'غير موجود - لم يتم التثبيت بعد', false);
        }
        
        echo '</div>';

        // فحص الأمان
        echo '<div class="check-section">';
        echo '<h2>🛡️ فحص الأمان</h2>';
        
        $totalChecks++;
        $htaccess = file_exists('.htaccess');
        if ($htaccess) $passedChecks++; else $warnings++;
        displayCheck('ملف .htaccess', $htaccess ? 'موجود' : 'مفقود', $htaccess, 'warning');
        
        $totalChecks++;
        $sessionSecure = ini_get('session.cookie_httponly') == 1;
        if ($sessionSecure) $passedChecks++; else $warnings++;
        displayCheck('HttpOnly Cookies', $sessionSecure ? 'مفعل' : 'غير مفعل', $sessionSecure, 'warning');
        
        echo '</div>';

        // الملخص
        $percentage = $totalChecks > 0 ? round(($passedChecks / $totalChecks) * 100) : 0;
        
        echo '<div class="summary">';
        echo '<h3>📊 ملخص الفحص</h3>';
        echo '<div class="stats">';
        echo "<div class='stat-item'><div class='stat-value'>$percentage%</div><div class='stat-label'>نسبة النجاح</div></div>";
        echo "<div class='stat-item'><div class='stat-value'>$passedChecks/$totalChecks</div><div class='stat-label'>اختبارات ناجحة</div></div>";
        echo "<div class='stat-item'><div class='stat-value'>$errors</div><div class='stat-label'>أخطاء</div></div>";
        echo '</div>';
        echo '</div>';

        // التوصيات
        if ($errors > 0 || $warnings > 0) {
            echo '<div class="check-section">';
            echo '<h2>💡 التوصيات</h2>';
            if ($errors > 0) {
                echo '<div class="check-item error">';
                echo '<div class="check-label">❌ يوجد ' . $errors . ' خطأ يجب إصلاحه</div>';
                echo '</div>';
            }
            if ($warnings > 0) {
                echo '<div class="check-item warning">';
                echo '<div class="check-label">⚠️ يوجد ' . $warnings . ' تحذير ينصح بمعالجته</div>';
                echo '</div>';
            }
            echo '</div>';
        }

        function displayCheck($label, $value, $status, $type = null) {
            if ($type === null) {
                $type = $status ? 'success' : 'error';
            }
            if ($type === 'info') {
                $type = 'success';
            }
            
            $icon = $status ? '✅' : ($type === 'warning' ? '⚠️' : '❌');
            $statusClass = is_bool($status) ? ($status ? 'success' : 'error') : $type;
            
            echo "<div class='check-item $statusClass'>";
            echo "<div class='check-label'>$icon $label</div>";
            echo "<div class='check-value $statusClass'>$value</div>";
            echo "</div>";
        }
        ?>

        <div style="margin-top: 30px; padding: 20px; background: #f1f5f9; border-radius: 8px; text-align: center;">
            <p style="color: #64748b; font-size: 0.9rem;">
                💻 تم إنشاء هذا التقرير بواسطة نظام فحص الصحة التلقائي<br>
                <?php echo date('Y-m-d H:i:s'); ?>
            </p>
        </div>
    </div>
</body>
</html>
