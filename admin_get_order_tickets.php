<?php
session_start();
require_once 'koneksi.php';

header('Content-Type: application/json');

if (!isset($_SESSION['id_user']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$id_order = $_GET['id_order'] ?? 0;

if (!$id_order) {
    echo json_encode([]);
    exit;
}

$stmt = $conn->prepare("
    SELECT t.*, k.nama_tiket
    FROM attendee t
    JOIN order_detail i ON t.id_detail = i.id_detail
    JOIN tiket k ON i.id_tiket = k.id_tiket
    WHERE i.id_order = ?
");
$stmt->execute([$id_order]);
$tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($tickets);
?>
