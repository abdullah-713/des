<?php
require_once 'config.php';

echo "Starting migration...\n";

try {
    $db = Database::getInstance()->getConnection();

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
    echo "Users table checked/created.\n";

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
            echo "Adding missing column: $col\n";
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
    echo "Migrated $migrated employees to Users table.\n";

    // 3. Ensure Admin
    $checkAdmin = $db->prepare("SELECT id FROM users WHERE username = 'admin'");
    $checkAdmin->execute();
    if (!$checkAdmin->fetch()) {
        $adminPass = password_hash('admin123', PASSWORD_DEFAULT);
        $db->prepare("INSERT INTO users (username, password, full_name, role, is_active) VALUES ('admin', ?, 'Administrator', 'admin', 1)")
            ->execute([$adminPass]);
        echo "Created default admin user (admin / admin123).\n";
    } else {
            echo "Admin user already exists.\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>