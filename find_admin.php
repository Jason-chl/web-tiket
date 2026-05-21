<?php
require_once 'koneksi.php';
$stmt = $conn->query("SELECT username, role FROM users WHERE role='admin' LIMIT 1");
print_r($stmt->fetch());
?>
