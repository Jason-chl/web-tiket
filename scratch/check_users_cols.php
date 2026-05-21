<?php
require_once 'koneksi.php';
try {
    $stmt = $conn->query("DESCRIBE users");
    foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        echo $row['Field'] . " " . $row['Type'] . "\n";
    }
} catch(Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
