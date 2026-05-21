<?php
require_once 'koneksi.php';
echo "--- Tables in " . $db . " ---\n";
try {
    $stmt = $conn->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    foreach($tables as $t) {
        echo $t;
        try {
            $conn->query("SELECT 1 FROM $t LIMIT 1");
            echo " [OK]\n";
        } catch(Exception $e) {
            echo " [CORRUPT: " . $e->getMessage() . "]\n";
        }
    }
} catch(Exception $e) { echo "ERROR: " . $e->getMessage() . "\n"; }
?>
