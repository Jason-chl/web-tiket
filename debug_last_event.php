<?php
require_once 'koneksi.php';
$e = $conn->query("SELECT * FROM event ORDER BY id_event DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if ($e) {
    echo "Latest Event: " . $e['nama_event'] . " (ID: " . $e['id_event'] . ")\n";
    $s = $conn->prepare("SELECT * FROM event_schedule WHERE id_event = ?");
    $s->execute([$e['id_event']]);
    $rows = $s->fetchAll(PDO::FETCH_ASSOC);
    echo "Schedule Count: " . count($rows) . "\n";
    print_r($rows);
} else {
    echo "No event found.\n";
}
?>
