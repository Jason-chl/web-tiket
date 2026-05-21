<?php
session_start();
require_once 'koneksi.php';

if (!isset($_SESSION['id_user']) || $_SESSION['role'] !== 'admin') {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$search = $_GET['search'] ?? '';
$statusFilter = $_GET['status'] ?? '';

$sql = "
    SELECT o.order_code, u.nama, u.email, c.nama_event, o.total, o.amount_paid, o.status, o.tanggal_order
    FROM orders o
    JOIN users u ON o.id_user = u.id_user
    JOIN event c ON o.id_event = c.id_event
    WHERE 1=1
";

$params = [];
if ($search) {
    $sql .= " AND (u.nama LIKE ? OR o.order_code LIKE ? OR u.email LIKE ? OR u.username LIKE ? OR c.nama_event LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($statusFilter) {
    if ($statusFilter === 'bought') {
        $sql .= " AND o.status IN ('paid', 'dp_paid')";
    } else {
        $sql .= " AND o.status = ?";
        $params[] = $statusFilter;
    }
}

$sql .= " ORDER BY o.tanggal_order DESC";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: application/json');
echo json_encode($data);
