<?php
require_once 'config.php';

header('Content-Type: text/plain; charset=utf-8');

try {
    $db = Database::getInstance()->getConnection();
    echo "Starting database migration for Advanced Control Panel...\n";

    // 1. Audit Logs Table
    echo "Creating audit_logs table...\n";
    $db->exec("CREATE TABLE IF NOT EXISTS audit_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NULL,
        action VARCHAR(50) NOT NULL,
        table_name VARCHAR(50) NULL,
        record_id INT NULL,
        old_value TEXT NULL,
        new_value TEXT NULL,
        ip_address VARCHAR(45) NULL,
        user_agent VARCHAR(255) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

    // 2. Dynamic Rules/Equations Table
    echo "Creating dynamic_rules table...\n";
    $db->exec("CREATE TABLE IF NOT EXISTS dynamic_rules (
        id INT AUTO_INCREMENT PRIMARY KEY,
        rule_key VARCHAR(50) NOT NULL UNIQUE,
        name VARCHAR(100) NOT NULL,
        description TEXT NULL,
        equation TEXT NOT NULL,
        variables TEXT NULL COMMENT 'JSON definition of available variables',
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

    // Insert default rules if not exist
    $stmt = $db->prepare("SELECT COUNT(*) FROM dynamic_rules WHERE rule_key = 'late_deduction'");
    $stmt->execute();
    if ($stmt->fetchColumn() == 0) {
        $defaultEquation = 'delay_minutes <= grace_period ? 0 : (delay_minutes <= 30 ? 10 : (delay_minutes <= 60 ? 20 : 50))';
        $vars = json_encode([
            'delay_minutes' => 'Minutes late',
            'grace_period' => 'Grace period in minutes'
        ]);
        $stmt = $db->prepare("INSERT INTO dynamic_rules (rule_key, name, description, equation, variables) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute(['late_deduction', 'Late Deduction Rule', 'Calculates points to deduct based on delay minutes', $defaultEquation, $vars]);
    }

    // 3. Roles and Permissions Tables (RBAC)
    echo "Creating roles and permissions tables...\n";
    $db->exec("CREATE TABLE IF NOT EXISTS roles (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(50) NOT NULL UNIQUE,
        description VARCHAR(255) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

    $db->exec("CREATE TABLE IF NOT EXISTS permissions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        code VARCHAR(50) NOT NULL UNIQUE,
        description VARCHAR(255) NULL,
        group_name VARCHAR(50) NULL
    ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

    $db->exec("CREATE TABLE IF NOT EXISTS role_permissions (
        role_id INT NOT NULL,
        permission_id INT NOT NULL,
        PRIMARY KEY (role_id, permission_id),
        FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
        FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
    ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

    // Add role_id to users table if not exists
    try {
        $db->exec("ALTER TABLE users ADD COLUMN role_id INT NULL AFTER role");
        $db->exec("ALTER TABLE users ADD CONSTRAINT fk_users_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE SET NULL");
    } catch (Exception $e) {
        // Column likely exists
    }

    // Seed default roles and permissions
    $defaultRoles = ['Super Admin', 'Manager', 'HR', 'Auditor'];
    foreach ($defaultRoles as $roleName) {
        $db->exec("INSERT IGNORE INTO roles (name) VALUES ('$roleName')");
    }

    $permissions = [
        'user_view' => 'View Users', 'user_create' => 'Create Users', 'user_edit' => 'Edit Users', 'user_delete' => 'Delete Users',
        'attendance_view' => 'View Attendance', 'attendance_edit' => 'Edit Attendance',
        'rules_manage' => 'Manage Dynamic Rules',
        'backup_manage' => 'Manage Backups',
        'logs_view' => 'View Audit Logs'
    ];

    foreach ($permissions as $code => $desc) {
        $db->exec("INSERT IGNORE INTO permissions (code, description) VALUES ('$code', '$desc')");
    }

    // Assign all permissions to Super Admin
    $stmt = $db->prepare("SELECT id FROM roles WHERE name = 'Super Admin'");
    $stmt->execute();
    $adminId = $stmt->fetchColumn();

    if ($adminId) {
        $db->exec("INSERT IGNORE INTO role_permissions (role_id, permission_id) SELECT $adminId, id FROM permissions");
    }

    // 4. Backups Table
    echo "Creating backups table...\n";
    $db->exec("CREATE TABLE IF NOT EXISTS backups (
        id INT AUTO_INCREMENT PRIMARY KEY,
        filename VARCHAR(255) NOT NULL,
        size_bytes BIGINT NOT NULL,
        created_by INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        note VARCHAR(255) NULL
    ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

    echo "Migration completed successfully!\n";

} catch (Exception $e) {
    echo "Error during migration: " . $e->getMessage() . "\n";
    http_response_code(500);
}
?>
