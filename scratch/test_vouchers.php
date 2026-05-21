<?php
require_once 'koneksi.php';
try {
    $stmt = $conn->query("SELECT * FROM vouchers LIMIT 1");
    echo "Vouchers table exists.\n";
} catch(Exception $e) {
    echo "No vouchers table: " . $e->getMessage() . "\n";
}
?>
