<?php
require_once 'koneksi.php';
try {
    $conn->exec("ALTER TABLE event_lineup ADD COLUMN id_schedule INT NULL AFTER id_event");
    echo "Added id_schedule to event_lineup\n";
} catch(Exception $e) { echo $e->getMessage() . "\n"; }

try {
    $conn->exec("ALTER TABLE event_setlist ADD COLUMN id_schedule INT NULL AFTER id_event");
    echo "Added id_schedule to event_setlist\n";
} catch(Exception $e) { echo $e->getMessage() . "\n"; }
?>
