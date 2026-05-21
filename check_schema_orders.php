<?php
require_once 'koneksi.php';
$t = ['tiket', 'order_detail', 'orders'];
foreach($t as $tbl) {
    echo "--- $tbl ---\n";
    $d = $conn->query("DESCRIBE $tbl");
    foreach($d->fetchAll() as $r) echo $r['Field'].' '.$r['Type']."\n";
}
?>
