<?php
require_once 'koneksi.php';
$res = $conn->query("SHOW TABLES");
$tables = $res->fetchAll(PDO::FETCH_COLUMN);
print_r($tables);

foreach($tables as $tbl) {
    if (strpos($tbl, 'lineup') !== false || strpos($tbl, 'setlist') !== false || strpos($tbl, 'schedule') !== false) {
        echo "--- $tbl ---\n";
        $d = $conn->query("DESCRIBE $tbl");
        foreach($d->fetchAll() as $r) echo $r['Field'].' '.$r['Type']."\n";
    }
}
?>
