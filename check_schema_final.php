<?php
require_once 'koneksi.php';
$t = ['event_lineup', 'event_setlist', 'event_schedule'];
foreach($t as $tbl) {
    echo "--- $tbl ---\n";
    try {
        $d = $conn->query("DESCRIBE $tbl");
        foreach($d->fetchAll() as $r) echo $r['Field'].' '.$r['Type']."\n";
    } catch(Exception $e) { echo "ERROR: " . $e->getMessage() . "\n"; }
}
?>
