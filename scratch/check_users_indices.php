<?php
require_once 'koneksi.php';
echo "--- users indexes ---\n";
try {
    $stmt = $conn->query("SHOW INDEX FROM users");
    foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        echo $row['Column_name'] . " (Unique: " . ($row['Non_unique'] == 0 ? 'Yes' : 'No') . ")\n";
    }
} catch(Exception $e) { echo "ERROR: " . $e->getMessage() . "\n"; }
?>
