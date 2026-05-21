<?php
require_once 'koneksi.php';
$stmt = $conn->query("DESCRIBE attendee");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
?>
