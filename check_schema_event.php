<?php
require_once 'koneksi.php';
$d = $conn->query("DESCRIBE event");
foreach($d->fetchAll() as $r) echo $r['Field'].' '.$r['Type']."\n";
?>
