<?php
session_start();
require_once 'koneksi.php';

if (!isset($_SESSION['id_user']) || $_SESSION['role'] !== 'admin') {
    die("Access Denied");
}

$action = $_POST['action'] ?? '';
$id_order = $_POST['id_order'] ?? 0;

if (!$id_order) {
    header("Location: admin_history");
    exit;
}

try {
    $conn->beginTransaction();

    // 1. Get current order info
    $stmt = $conn->prepare("SELECT status FROM orders WHERE id_order = ? FOR UPDATE");
    $stmt->execute([$id_order]);
    $order = $stmt->fetch();

    if ($action === 'cancel') {
        if (!in_array($order['status'], ['pending', 'paid', 'dp_paid'])) {
            throw new Exception("Status order tidak valid untuk dibatalkan.");
        }

        // 2. Restore Stock
        $iStmt = $conn->prepare("SELECT id_tiket, qty FROM order_detail WHERE id_order = ?");
        $iStmt->execute([$id_order]);
        $items = $iStmt->fetchAll();

        foreach ($items as $item) {
            $upStock = $conn->prepare("UPDATE tiket SET kuota = kuota + ? WHERE id_tiket = ?");
            $upStock->execute([$item['qty'], $item['id_tiket']]);
        }

        // 3. Mark Tickets as Cancelled (Don't delete, for history consistency)
        $upTix = $conn->prepare("UPDATE attendee SET status_checkin = 'cancelled' WHERE id_detail IN (SELECT id_detail FROM order_detail WHERE id_order = ?)");
        $upTix->execute([$id_order]);

        // 4. Update status to cancelled
        $newStatus = 'cancelled';
        $upOrder = $conn->prepare("UPDATE orders SET status = ?, cancelled_at = NOW(), refund_status = 'completed' WHERE id_order = ?");
        $upOrder->execute([$newStatus, $id_order]);
    }

    $conn->commit();
    header("Location: admin_history?msg=success");
    exit;

} catch (Exception $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    die("Error: " . $e->getMessage());
}
?>
