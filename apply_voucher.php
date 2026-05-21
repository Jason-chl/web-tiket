<?php
session_start();
require_once 'koneksi.php';

header('Content-Type: application/json');

if (!isset($_SESSION['id_user'])) {
    echo json_encode(['success' => false, 'message' => 'Silakan login terlebih dahulu.']);
    exit;
}

$code = strtoupper(trim($_POST['code'] ?? ''));
$orderCode = $_POST['order_code'] ?? '';

if (empty($code)) {
    echo json_encode(['success' => false, 'message' => 'Masukkan kode voucher.']);
    exit;
}

// Get Order Details
$stmtO = $conn->prepare("SELECT id_event, total FROM orders WHERE order_code = ? AND id_user = ?");
$stmtO->execute([$orderCode, $_SESSION['id_user']]);
$order = $stmtO->fetch();

if (!$order) {
    echo json_encode(['success' => false, 'message' => 'Order tidak ditemukan.']);
    exit;
}

// Get Voucher Details
$stmtV = $conn->prepare("SELECT * FROM vouchers WHERE code = ? AND status = 'active'");
$stmtV->execute([$code]);
$voucher = $stmtV->fetch();

if (!$voucher) {
    echo json_encode(['success' => false, 'message' => 'Kode voucher tidak valid atau sudah tidak aktif.']);
    exit;
}

// Check Event Matching
if ($voucher['id_event'] != 0 && $voucher['id_event'] != $order['id_event']) {
    echo json_encode(['success' => false, 'message' => 'Voucher ini tidak berlaku untuk konser ini.']);
    exit;
}

// Check Usage
if ($voucher['current_usage'] >= $voucher['max_usage']) {
    $conn->prepare("UPDATE vouchers SET status = 'expired' WHERE id_voucher = ?")->execute([$voucher['id_voucher']]);
    echo json_encode(['success' => false, 'message' => 'Kuota voucher sudah habis.']);
    exit;
}

// All good!
$discountAmount = (int)$voucher['discount_amount'];
$newTotal = max(0, $order['total'] - $discountAmount);

echo json_encode([
    'success' => true,
    'message' => 'Voucher berhasil diterapkan!',
    'discount' => $discountAmount,
    'newTotal' => $newTotal,
    'code' => $code
]);
?>
