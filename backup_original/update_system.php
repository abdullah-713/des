<?php
/**
 * تحديث النظام الموجود بالتغييرات الجديدة
 * Update existing system with new changes
 */

require_once 'config.php';

$message = '';
$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db = Database::getInstance()->getConnection();
        
        $adminPassword = password_hash('password', PASSWORD_DEFAULT);
        $stmt = $db->prepare("SELECT id FROM users WHERE username = 'admin' LIMIT 1");
        $stmt->execute();
        $adminUser = $stmt->fetch();
        
        if ($adminUser) {
            $stmt = $db->prepare("UPDATE users SET password = ?, role = 'admin', is_active = 1 WHERE id = ?");
            $stmt->execute([$adminPassword, $adminUser['id']]);
        } else {
            $stmt = $db->prepare("INSERT INTO users (username, password, role, is_active) VALUES ('admin', ?, 'admin', 1)");
            $stmt->execute([$adminPassword]);
        }
        
        $success = true;
        $message = "تم إعادة ضبط بيانات الدخول بنجاح!<br>";
        $message .= "- اسم المستخدم: admin<br>";
        $message .= "- كلمة المرور: password";
        
    } catch (Exception $e) {
        $error = "خطأ في التحديث: " . $e->getMessage();
    }
}

?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تحديث النظام - صرح انضباط</title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Cairo', sans-serif;
            background: linear-gradient(135deg, #f97316, #c2410c);
            min-height: 100vh;
            margin: 0;
            padding: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .container {
            background: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 600px;
        }
        h1 {
            text-align: center;
            color: #c2410c;
            margin-bottom: 30px;
            font-size: 2.5rem;
            font-weight: 800;
        }
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 600;
        }
        .alert-success {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #bbf7d0;
        }
        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }
        .btn {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #f97316, #c2410c);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 18px;
            font-weight: 700;
            cursor: pointer;
            transition: transform 0.3s;
            font-family: inherit;
        }
        .btn:hover {
            transform: translateY(-2px);
        }
        .changes-list {
            background: #f8fafc;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            border: 1px solid #e2e8f0;
        }
        .changes-list h3 {
            color: #c2410c;
            margin-bottom: 15px;
        }
        .changes-list ul {
            margin: 0;
            padding-right: 20px;
        }
        .changes-list li {
            margin: 8px 0;
            color: #64748b;
        }
        .success-page {
            text-align: center;
        }
        .success-icon {
            font-size: 80px;
            color: #10b981;
            margin-bottom: 20px;
        }
        .btn-link {
            display: inline-block;
            padding: 12px 25px;
            background: #64748b;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            margin: 10px;
            font-weight: 700;
            transition: background 0.3s;
        }
        .btn-link:hover {
            background: #475569;
        }
        .warning {
            background: #fef3c7;
            color: #92400e;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
            border: 1px solid #fde68a;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php if ($success): ?>
        <!-- صفحة النجاح -->
        <div class="success-page">
            <div class="success-icon">✅</div>
            <h1>تم التحديث بنجاح!</h1>
            
            <div class="alert alert-success">
                <?php echo $message; ?>
            </div>
            
            <div style="background: #f8fafc; padding: 20px; border-radius: 10px; margin: 20px 0; text-align: right;">
                <h3 style="color: #c2410c; margin-bottom: 15px;">ملاحظات مهمة:</h3>
                <p>• صرح الإتقان المحدودة أصبح يمثل المجموع العام في الإحصائيات</p>
                <p>• أرقام الموظفين الجديدة مبنية على أول 3 أحرف من الاسم + 3 أرقام</p>
                <p>• تم تسجيل حضور جميع الموظفين اليوم في الساعة 8:00 صباحاً</p>
                <p>• جميع أزرار التعديل تعمل الآن بشكل صحيح</p>
                <p>• الشريط العلوي يعرض المتميزين (أخضر) والمتأخرين (أحمر) كل 3 ثواني</p>
                <p>• نسب الحضور تحسب الآن بناءً على الموظفين بدون تأخير</p>
                <p>• يمكن للمدير التحكم في نظام النقاط من تبويب "نظام النقاط"</p>
            </div>
            
            <a href="employee.php" class="btn-link">👥 واجهة الموظفين</a>
            <a href="admin.php" class="btn-link">🔧 لوحة الإدارة</a>
        </div>
        
        <?php else: ?>
        
        <h1>🔄 تحديث النظام</h1>
        
        <?php if ($error): ?>
        <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <div class="warning">
            <strong>⚠️ تحذير:</strong> هذا التحديث سيقوم بتغيير البيانات الموجودة. تأكد من أخذ نسخة احتياطية قبل المتابعة.
        </div>
        
        <div class="changes-list">
            <h3>🔧 التغييرات التي سيتم تطبيقها:</h3>
            <ul>
                <li>نقل موظفي صرح الإتقان المحدودة إلى الفرع الرئيسي</li>
                <li>حذف فرع صرح الإتقان المحدودة من قاعدة البيانات</li>
                <li>تحديث أرقام الموظفين لتكون من أول 3 أحرف من الاسم + 3 أرقام عشوائية</li>
                <li>إضافة سجلات حضور لجميع الموظفين اليوم في الساعة 8:00</li>
                <li>جعل صرح الإتقان المحدودة يمثل المجموع العام في الإحصائيات</li>
                <li>إصلاح جميع أزرار التعديل في لوحة الإدارة</li>
                <li>تصغير العناصر لإظهار محتوى أكبر في الشاشة</li>
                <li>إصلاح حساب نسب الحضور (الموظفين بدون تأخير)</li>
                <li>تفعيل نظام النقاط والتحكم الكامل بها من المدير</li>
                <li>إضافة شريط علوي دوار يعرض المتميزين والمتأخرين</li>
                <li>تحسين إضافة الموظفين وربطهم بالمراكز</li>
            </ul>
        </div>
        
        <form method="POST">
            <button type="submit" class="btn" onclick="return confirm('هل أنت متأكد من تطبيق التحديث؟')">
                🚀 تطبيق التحديث
            </button>
        </form>
        
        <div style="text-align: center; margin-top: 20px;">
            <a href="index.php" style="color: #64748b; text-decoration: none;">← العودة للصفحة الرئيسية</a>
        </div>
        
        <?php endif; ?>
    </div>
</body>
</html>
