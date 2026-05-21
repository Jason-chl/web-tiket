<?php
session_start();
require_once 'koneksi.php';

header('Content-Type: application/json');

if (!isset($_SESSION['id_user']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'petugas')) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$kode_tiket = $data['kode_tiket'] ?? '';

if (!$kode_tiket) {
    echo json_encode(['success' => false, 'message' => 'Kode tiket tidak ditemukan']);
    exit;
}

try {
    $conn->beginTransaction();

    // Ambil info tiket, kategori, konser, dan status order
    $stmt = $conn->prepare("
         SELECT t.*, oi.id_order, o.status as order_status,
               k.nama_tiket, c.nama_event
         FROM attendee t
         JOIN order_detail oi ON t.id_detail = oi.id_detail
         JOIN orders o ON oi.id_order = o.id_order
         JOIN tiket k ON oi.id_tiket = k.id_tiket
         JOIN event c ON o.id_event = c.id_event
        WHERE t.kode_tiket = ?
        FOR UPDATE
    ");
    $stmt->execute([$kode_tiket]);
    $ticket = $stmt->fetch();

    if (!$ticket) {
        throw new Exception("Tiket tidak terdaftar dalam sistem.");
    }

    if ($ticket['order_status'] !== 'paid') {
        $statusText = $ticket['order_status'] === 'dp_paid' ? 'Sudah DP namun belum lunas' : strtoupper($ticket['order_status']);
        throw new Exception("Pesanan belum lunas. Status: " . $statusText);
    }

    if ($ticket['status_checkin'] === 'used') {
        throw new Exception("Tiket sudah pernah digunakan pada " . date('d M Y, H:i', strtotime($ticket['waktu_checkin'] ?? 'sebelumnya')));
    }

    if ($ticket['status_checkin'] !== 'active') {
        throw new Exception("Tiket tidak aktif (Status: " . $ticket['status_checkin'] . ")");
    }

    // Update status tiket menjadi used
    $upStmt = $conn->prepare("
        UPDATE attendee 
        SET status_checkin = 'used', waktu_checkin = NOW(), used_by_admin_id = ? 
        WHERE id_attendee = ?
    ");
    $upStmt->execute([$_SESSION['id_user'], $ticket['id_attendee']]);

    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Check-in Berhasil!',
        'detail' => [
            'nama_pemegang' => $ticket['nama_pemegang'],
            'nama_event' => $ticket['nama_event'],
            'kategori' => $ticket['nama_tiket'],
            'waktu' => date('H:i:s')
        ]
    ]);

} catch (Exception $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
