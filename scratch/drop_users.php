<?php
require_once 'koneksi.php';
try {
    $conn->exec("DROP TABLE IF EXISTS users");
    echo "Users table dropped (if existed).\n";
} catch(Exception $e) {
    echo "Drop failed: " . $e->getMessage() . "\n";
}
?>
