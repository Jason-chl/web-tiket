<?php
require_once 'koneksi.php';
try {
    // Check and increase other potentially small columns
    $conn->exec("ALTER TABLE payments MODIFY COLUMN external_transaction_id VARCHAR(100) NULL");
    $conn->exec("ALTER TABLE orders MODIFY COLUMN payment_type VARCHAR(20) DEFAULT 'full'");
    echo "Additional schema updates applied!";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
