<?php
require_once 'config.php';

try {
    $db = Database::getInstance()->getConnection();
    
    // Add profile_image column if not exists
    try {
        $db->exec("ALTER TABLE employees ADD COLUMN profile_image VARCHAR(255) NULL");
        echo "Added profile_image column.\n";
    } catch (PDOException $e) {
        // Column likely exists
        echo "profile_image column might already exist.\n";
    }
    
    // Add password column if not exists
    try {
        $db->exec("ALTER TABLE employees ADD COLUMN password VARCHAR(255) NULL");
        echo "Added password column.\n";
    } catch (PDOException $e) {
        // Column likely exists
        echo "password column might already exist.\n";
    }

    echo "Database schema updated successfully.\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
