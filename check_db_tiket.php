<?php
require_once 'koneksi.php';
$stmt = $conn->query("DESCRIBE tiket");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
?>
