<?php
require_once 'config.php';
header('Content-Type: text/plain; charset=utf-8');

try {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->query("SHOW COLUMNS FROM employees LIKE 'weekly_schedule'");
    if ($stmt && $stmt->rowCount() == 0) {
        $db->exec("ALTER TABLE employees ADD COLUMN weekly_schedule JSON DEFAULT NULL COMMENT 'Stores custom daily schedule e.g. {\"sun\":{\"start\":\"08:00\",\"end\":\"16:00\"}}'");
        echo "✅ Added 'weekly_schedule' column to employees table.\n";
    } else {
        echo "ℹ️ 'weekly_schedule' column already exists.\n";
    }

    echo "🎉 Database schema updated successfully for V2 features.\n";
} catch (Exception $e) {
    http_response_code(500);
    echo "❌ Error updating database: " . $e->getMessage();
}
?>
