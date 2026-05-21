<?php
require_once 'koneksi.php';
$stmt = $conn->query("SELECT id_user, email, role, password, is_active FROM users");
echo "FULL USER LIST:\n";
while($row = $stmt->fetch()) {
    echo "ID: " . $row['id_user'] . " | Email: " . $row['email'] . " | Role: " . $row['role'] . " | Active: " . $row['is_active'] . " | Pass Hash: " . substr($row['password'], 0, 10) . "...\n";
}
?>
