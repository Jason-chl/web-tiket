<?php
require_once 'koneksi.php';
try {
    $conn->exec("ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'user', 'petugas') DEFAULT 'user'");
    echo "Role 'petugas' added successfully!";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
