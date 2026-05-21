<?php
require_once 'koneksi.php';
header('Content-Type: text/plain');

try {
    $stmt = $conn->query("SELECT id_event, nama_event, video_url, video_start, video_end FROM event ORDER BY id_event DESC LIMIT 5");
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    print_r($data);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
