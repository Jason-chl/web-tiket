<?php
require_once 'koneksi.php';
try {
    $conn->exec("ALTER TABLE users ADD COLUMN is_vip TINYINT(1) DEFAULT 0 AFTER role");
    echo "Column 'is_vip' added successfully.\n";
} catch(Exception $e) {
    echo "Alter failed: " . $e->getMessage() . "\n";
}
?>
