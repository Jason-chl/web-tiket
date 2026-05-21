<?php
require_once 'koneksi.php';
$stmt = $conn->query("DESCRIBE orders");
echo "<pre>";
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    print_r($row);
}
echo "</pre>";
?>
