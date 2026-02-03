<?php
// Force error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';

try {
    $db = Database::getInstance()->getConnection();
    echo "Connected to database.\n";

    // 1. Ensure users table structure is correct
    // We expect: id, username, password, role, is_active, full_name, etc.
    // If it exists, we might need to alter it.
    
    // Check if table exists
    $tableExists = false;
    try {
        $result = $db->query("SELECT 1 FROM users LIMIT 1");
        $tableExists = true;
        echo "Users table exists.\n";
    } catch (Exception $e) {
        echo "Users table does not exist. Creating...\n";
    }

    if (!$tableExists) {
        $sql = "CREATE TABLE users (
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
        echo "Users table created.\n";
    } else {
        // Table exists, ensure columns exist
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
                $db->query("SELECT $col FROM users LIMIT 1");
            } catch (Exception $e) {
                echo "Adding column $col...\n";
                $db->exec("ALTER TABLE users ADD COLUMN $col $def");
            }
        }
    }

    // 2. Migrate Employees to Users
    echo "Migrating employees...\n";
    $stmt = $db->query("SELECT * FROM employees");
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $count = 0;
    foreach ($employees as $emp) {
        $username = $emp['employee_code'];
        $fullName = $emp['name'];
        // Use existing password if hashed, else hash the employee_code or default
        $password = $emp['password']; // Assuming it might be there
        
        // If password is empty or not hashed (simple check), generate new
        if (empty($password) || strlen($password) < 30) { 
            // Default password is the employee_code
            $password = password_hash($username, PASSWORD_DEFAULT);
        } else {
            // Assume existing is hash, but if logic in index.php was using it, keep it.
            // If it's plain text in DB currently (unlikely if verified by password_verify), we should rehash?
            // index.php says: password_verify($password, $employee['password']) so it IS hashed.
        }

        // Check if user exists
        $check = $db->prepare("SELECT id FROM users WHERE username = ?");
        $check->execute([$username]);
        if (!$check->fetch()) {
            $insert = $db->prepare("INSERT INTO users (username, password, full_name, role, is_active, profile_image) VALUES (?, ?, ?, 'employee', 1, ?)");
            $insert->execute([
                $username, 
                $password, 
                $fullName,
                $emp['profile_image'] ?? null
            ]);
            $count++;
        }
    }
    echo "Migrated $count employees to users table.\n";

    // 3. Ensure Default Admin Exists
    $adminUser = 'admin';
    $checkAdmin = $db->prepare("SELECT id FROM users WHERE username = ?");
    $checkAdmin->execute([$adminUser]);
    if (!$checkAdmin->fetch()) {
        $adminPass = password_hash('admin123', PASSWORD_DEFAULT); // Temporary default
        $insertAdmin = $db->prepare("INSERT INTO users (username, password, full_name, role, is_active) VALUES (?, ?, ?, 'admin', 1)");
        $insertAdmin->execute([$adminUser, $adminPass, 'Administrator']);
        echo "Default admin created (user: admin, pass: admin123)\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}
?>