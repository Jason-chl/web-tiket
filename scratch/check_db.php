<?php
require 'koneksi.php';
$q = $conn->query("DESCRIBE users");
echo json_encode($q->fetchAll(PDO::FETCH_ASSOC), JSON_PRETTY_PRINT);
?>
