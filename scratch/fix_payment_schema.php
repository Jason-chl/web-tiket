<?php
require_once 'koneksi.php';
try {
    $conn->exec("ALTER TABLE orders MODIFY COLUMN metode_pembayaran VARCHAR(50) NULL");
    $conn->exec("ALTER TABLE payments MODIFY COLUMN metode_pembayaran VARCHAR(50) NULL");
    echo "Schema updated successfully!";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
