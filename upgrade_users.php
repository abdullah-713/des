<?php
require_once 'config.php';

$message = '';
$logs = [];

function logMsg($msg) {
    global $logs;
    $logs[] = date('H:i:s') . ' - ' . $msg;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db = Database::getInstance()->getConnection();
        logMsg("Connected to database.");

        // 1. Create Users Table
        $sql = "CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            full_name VARCHAR(100) NOT NULL,
            email VARCHAR(100),
            role ENUM('admin', 'manager', 'employee') DEFAULT 'employee',
            is_active TINYINT(1) DEFAULT 1,
            profile_image VARCHAR(255),
            phone VARCHAR(20),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            last_login DATETIME
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        
        $db->exec($sql);
        logMsg("Users table checked/created.");

        // Ensure columns exist (if table existed before)
        $columns = [
            'username' => "VARCHAR(50) NOT NULL UNIQUE",
            'full_name' => "VARCHAR(100) NOT NULL",
            'role' => "ENUM('admin', 'manager', 'employee') DEFAULT 'employee'",
            'is_active' => "TINYINT(1) DEFAULT 1",
            'profile_image' => "VARCHAR(255)",
            'phone' => "VARCHAR(20)",
            'email' => "VARCHAR(100)"
        ];
        
        foreach ($columns as $col => $def) {
            try {
                $check = $db->query("SELECT $col FROM users LIMIT 1");
            } catch (Exception $e) {
                logMsg("Adding missing column: $col");
                $db->exec("ALTER TABLE users ADD COLUMN $col $def");
            }
        }

        // 2. Migrate Employees
        $stmt = $db->query("SELECT * FROM employees");
        $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $migrated = 0;

        foreach ($employees as $emp) {
            $username = $emp['employee_code'];
            // Fallback for username
            if (empty($username)) {
                $username = 'user_' . $emp['id'];
            }
            
            $fullName = $emp['name'];
            $existingPass = $emp['password'] ?? '';
            
            // Check if user exists
            $check = $db->prepare("SELECT id FROM users WHERE username = ?");
            $check->execute([$username]);
            
            if (!$check->fetch()) {
                // Create new user
                // If existing password looks like a hash, use it. Else hash username.
                if (!empty($existingPass) && strlen($existingPass) > 50) {
                     $finalPass = $existingPass;
                } else {
                     $finalPass = password_hash($username, PASSWORD_DEFAULT);
                }

                $insert = $db->prepare("INSERT INTO users (username, password, full_name, role, is_active, profile_image) VALUES (?, ?, ?, 'employee', 1, ?)");
                $insert->execute([
                    $username, 
                    $finalPass, 
                    $fullName,
                    $emp['profile_image'] ?? null
                ]);
                $migrated++;
            }
        }
        logMsg("Migrated $migrated employees to Users table.");

        // 3. Ensure Admin
        $checkAdmin = $db->prepare("SELECT id FROM users WHERE username = 'admin'");
        $checkAdmin->execute();
        if (!$checkAdmin->fetch()) {
            $adminPass = password_hash('admin123', PASSWORD_DEFAULT);
            $db->prepare("INSERT INTO users (username, password, full_name, role, is_active) VALUES ('admin', ?, 'Administrator', 'admin', 1)")
               ->execute([$adminPass]);
            logMsg("Created default admin user (admin / admin123).");
        } else {
             logMsg("Admin user already exists.");
        }

        // 4. Update Admin Table/Config if necessary
        // (Assuming admin.php uses users table, which we verified)

        $message = "تم تحديث النظام بنجاح!";

    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
        logMsg("EXCEPTION: " . $e->getMessage());
    }
}

?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>تحديث نظام المستخدمين</title>
    <style>
        body { font-family: sans-serif; padding: 40px; background: #f0f2f5; }
        .card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); max-width: 600px; margin: 0 auto; }
        h1 { margin-top: 0; color: #1a1a1a; }
        .btn { background: #6366f1; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; font-size: 16px; width: 100%; }
        .btn:hover { background: #4f46e5; }
        .log { background: #1e1e1e; color: #4ade80; padding: 15px; border-radius: 4px; margin-top: 20px; font-family: monospace; white-space: pre-wrap; }
        .success { color: green; font-weight: bold; text-align: center; }
        .error { color: red; font-weight: bold; text-align: center; }
    </style>
</head>
<body>
    <div class="card">
        <h1>ترقية الموظفين إلى مستخدمين</h1>
        <p>سيقوم هذا الإجراء بإنشاء جدول المستخدمين، ونقل جميع الموظفين الحاليين إليه، وتعيين كلمات مرور مشفرة لهم (كلمة المرور الافتراضية هي نفس اسم المستخدم/رقم الموظف).</p>
        
        <?php if ($message): ?>
            <div class="<?php echo strpos($message, 'Error') !== false ? 'error' : 'success'; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <button type="submit" class="btn">بدء الترقية الآن</button>
        </form>
        
        <?php if (!empty($logs)): ?>
            <div class="log">
                <?php echo implode("\n", $logs); ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
