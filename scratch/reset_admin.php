<?php
require 'koneksi.php';
$pass = password_hash('123456', PASSWORD_DEFAULT);
$conn->query("UPDATE users SET password = '$pass' WHERE email = 'admin@gmail.com'");
echo "Admin password reset to 123456";
