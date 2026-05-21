<?php
session_start();
require_once 'koneksi.php';

if (!isset($_SESSION['id_user']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index");
    exit;
}

$id_user = $_SESSION['id_user'];
$tiket_id = $_POST['tiket_id'] ?? 0;
$qty = (int)($_POST['qty'] ?? 1);

try {
    $conn->beginTransaction();

    // Lock the row for update (prevent race conditions)
    $stmt = $conn->prepare("SELECT * FROM tiket WHERE id_tiket = ? FOR UPDATE");
    $stmt->execute([$tiket_id]);
    $cat = $stmt->fetch();

    if (!$cat || $cat['kuota'] < $qty) {
        throw new Exception("Stok tiket tidak mencukupi.");
    }

    // Verify War Queue Permission from Session
    $id_event   = $cat['id_event'];
    $sessionKey = 'can_book_' . $id_event;
    if (!isset($_SESSION[$sessionKey]) || (time() - $_SESSION[$sessionKey] > 900)) { // 15 min limit
        throw new Exception("Sesi antrean Anda telah berakhir. Silakan mengantre ulang.");
    }
    // Remove the session permission so it can't be reused
    unset($_SESSION[$sessionKey]);

    $harga = $cat['harga'];

    // Validate fast track fee server-side (don't trust POST directly)
    $stmtVipCheck = $conn->prepare("SELECT is_vip FROM users WHERE id_user = ?");
    $stmtVipCheck->execute([$id_user]);
    $isVipBuyer = (int)($stmtVipCheck->fetchColumn() ?? 0);

    $fastTrackFeePerTicket = 0;
    if ($isVipBuyer) {
        $fastTrackFeePerTicket = (int)($conn->query("SELECT setting_value FROM settings WHERE setting_key = 'fast_track_fee'")->fetchColumn() ?? 0);
    }

    $baseSubtotal   = $harga * $qty;
    $fastTrackTotal = $fastTrackFeePerTicket; // Flat fee per purchase (not per ticket)
    $subtotal       = $baseSubtotal + $fastTrackTotal;

    // Generate Order Code (e.g. ORD-TIMESTAMP-RANDOM)
    $orderCode = 'ORD-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid()), 0, 6));

    // Expiry: 1 hour from now
    $expTime = date('Y-m-d H:i:s', strtotime('+1 hour'));

    // Insert Order
    $oStmt = $conn->prepare("
        INSERT INTO orders (order_code, id_user, id_event, total, status, tanggal_kadaluarsa, tanggal_order)
        VALUES (?, ?, ?, ?, 'pending', ?, NOW())
    ");
    $oStmt->execute([$orderCode, $id_user, $id_event, $subtotal, $expTime]);
    $order_id = $conn->lastInsertId();

    // Insert Order Item
    $iStmt = $conn->prepare("
        INSERT INTO order_detail (id_order, id_tiket, qty, harga_satuan, subtotal)
        VALUES (?, ?, ?, ?, ?)
    ");
    $iStmt->execute([$order_id, $tiket_id, $qty, $harga, $subtotal]);

    // Decrease Stock
    $newStock = $cat['kuota'] - $qty;
    $uStmt = $conn->prepare("UPDATE tiket SET kuota = ? WHERE id_tiket = ?");
    $uStmt->execute([$newStock, $tiket_id]);

    $conn->commit();

    // Redirect to Checkout page
    header("Location: checkout?order=" . $orderCode);
    exit;

} catch (Exception $e) {
    $conn->rollBack();
    echo "<script>alert('Gagal membuat pesanan: " . addslashes($e->getMessage()) . "'); window.location.href='user_dashboard';</script>";
}
?>
