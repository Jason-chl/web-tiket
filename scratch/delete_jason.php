<?php
require_once 'koneksi.php';
try {
    $stmt = $conn->prepare("DELETE FROM users WHERE email = ?");
    $stmt->execute(['jason@gmail.com']);
    echo "Account jason@gmail.com deleted.\n";
} catch(Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
