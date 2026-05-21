<?php
require_once 'koneksi.php';
try {
    $conn->exec("ALTER TABLE tiket ADD COLUMN id_schedule INT NULL AFTER id_event");
    echo "Column id_schedule added to tiket table.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
