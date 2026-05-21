<?php
require_once 'koneksi.php';
$stmt = $conn->query("DESCRIBE attendee");
echo "<pre>";
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    print_r($row);
}
echo "</pre>";
?>
