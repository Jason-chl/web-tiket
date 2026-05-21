<?php
require_once 'koneksi.php';
try {
    $conn->exec("ALTER TABLE users DROP INDEX username");
    echo "Index 'username' dropped.\n";
} catch(Exception $e) { 
    echo "ERROR: " . $e->getMessage() . "\n"; 
}
?>
