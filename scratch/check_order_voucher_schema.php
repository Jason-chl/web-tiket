<?php
require_once 'koneksi.php';
$tbls = ['orders', 'vouchers', 'order_detail'];
foreach($tbls as $tbl) {
    echo "--- $tbl ---\n";
    $d = $conn->query("DESCRIBE $tbl");
    foreach($d->fetchAll() as $r) echo $r['Field'].' '.$r['Type']."\n";
    echo "\n";
}
