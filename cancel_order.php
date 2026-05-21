<?php
session_start();
require_once 'koneksi.php';

if (!isset($_SESSION['id_user']) || !isset($_POST['id_order'])) {
    header("Location: user_dashboard");
    exit;
}

$id_order = $_POST['id_order'];
$id_user = $_SESSION['id_user'];

try {
    $conn->beginTransaction();

    // Check if order belongs to user
    $stmt = $conn->prepare("SELECT id_order, status FROM orders WHERE id_order = ? AND id_user = ? FOR UPDATE");
    $stmt->execute([$id_order, $id_user]);
    $order = $stmt->fetch();

    $validStatuses = ['pending', 'paid', 'dp_paid'];
    if (!$order || !in_array($order['status'], $validStatuses)) {
        throw new Exception("Order tidak dapat dibatalkan (sudah dibatalkan atau status tidak valid).");
    }

    $oldStatus = $order['status'];
    $refundQuery = "";
    if ($oldStatus === 'paid' || $oldStatus === 'dp_paid') {
        $refundQuery = ", refund_status = 'pending'";
        
        // Hapus E-Tiket jika ada (biasanya di status paid)
        // Kita cari order_detail dulu
        $itemStmt = $conn->prepare("SELECT id_detail FROM order_detail WHERE id_order = ?");
        $itemStmt->execute([$id_order]);
        $oiIds = $itemStmt->fetchAll(PDO::FETCH_COLUMN);

        if (!empty($oiIds)) {
            $placeholders = implode(',', array_fill(0, count($oiIds), '?'));
            $delTix = $conn->prepare("DELETE FROM attendee WHERE id_detail IN ($placeholders)");
            $delTix->execute($oiIds);
        }
    }

    // Update order status to cancelled
    $upStmt = $conn->prepare("UPDATE orders SET status = 'cancelled', cancelled_at = NOW() $refundQuery WHERE id_order = ?");
    $upStmt->execute([$id_order]);

    // Restore stock
    $iStmt = $conn->prepare("SELECT id_tiket, qty FROM order_detail WHERE id_order = ?");
    $iStmt->execute([$id_order]);
    $items = $iStmt->fetchAll();

    $rStmt = $conn->prepare("UPDATE tiket SET kuota = kuota + ? WHERE id_tiket = ?");
    foreach($items as $i) {
        $rStmt->execute([$i['qty'], $i['id_tiket']]);
    }

    $conn->commit();
    
    $msg = ($oldStatus === 'pending') ? 'cancelled' : 'refund_pending';
    header("Location: user_orders?msg=$msg");

} catch (Exception $e) {
    if($conn->inTransaction()) $conn->rollBack();
    echo "<script>alert('Gagal membatalkan: " . addslashes($e->getMessage()) . "'); window.location.href='user_orders';</script>";
}
?>
