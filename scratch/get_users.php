<?php
require 'koneksi.php';
$u = $conn->query('SELECT email, password, role FROM users LIMIT 10')->fetchAll();
print_r($u);
