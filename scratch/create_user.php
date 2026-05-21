<?php
require 'koneksi.php';
$pass = password_hash('123456', PASSWORD_DEFAULT);
$conn->query("INSERT IGNORE INTO users (email, password, role, nama, username) VALUES ('testuser@gmail.com', '$pass', 'user', 'Test User', 'testuser')");
echo "Test user created (testuser@gmail.com / 123456)";
