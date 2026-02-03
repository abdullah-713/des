<?php
require_once 'config.php';

try {
    // 1. Add weekly_schedule column to employees if not exists
    $sql = "SHOW COLUMNS FROM employees LIKE 'weekly_schedule'";
    $stmt = $pdo->query($sql);
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE employees ADD COLUMN weekly_schedule JSON DEFAULT NULL COMMENT 'Stores custom daily schedule e.g. {\"sun\":{\"start\":\"08:00\",\"end\":\"16:00\"}}'");
        echo "✅ Added 'weekly_schedule' column to employees table.\n";
    } else {
        echo "ℹ️ 'weekly_schedule' column already exists.\n";
    }

    echo "🎉 Database schema updated successfully for V2 features.\n";

} catch (PDOException $e) {
    die("❌ Error updating database: " . $e->getMessage());
}
?>