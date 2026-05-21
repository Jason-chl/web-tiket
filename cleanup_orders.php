<?php
// cleanup_orders.php - Automatic cleanup for expired pending orders

function cleanupExpiredOrders($conn) {
    try {
        // 1. Find expired pending orders
        $stmt = $conn->query("
            SELECT id_order 
            FROM orders 
            WHERE status = 'pending' AND tanggal_kadaluarsa < NOW()
        ");
        $expiredIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (empty($expiredIds)) return;

        foreach ($expiredIds as $id_order) {
            $conn->beginTransaction();

            // Double check status with lock
            $check = $conn->prepare("SELECT status FROM orders WHERE id_order = ? FOR UPDATE");
            $check->execute([$id_order]);
            if ($check->fetchColumn() !== 'pending') {
                $conn->rollBack();
                continue;
            }

            // Update Status
            $up = $conn->prepare("UPDATE orders SET status = 'cancelled', cancelled_at = NOW() WHERE id_order = ?");
            $up->execute([$id_order]);

            // Restore Stock
            $itemsStmt = $conn->prepare("SELECT id_tiket, qty FROM order_detail WHERE id_order = ?");
            $itemsStmt->execute([$id_order]);
            $items = $itemsStmt->fetchAll();

            $restoreStmt = $conn->prepare("UPDATE tiket SET kuota = kuota + ? WHERE id_tiket = ?");
            foreach ($items as $it) {
                $restoreStmt->execute([$it['qty'], $it['id_tiket']]);
            }

            $conn->commit();
        }
    } catch (Exception $e) {
        if ($conn->inTransaction()) $conn->rollBack();
        // Silently fail or log error
    }
}

// Global invocation (optional: throttled by session or file time)
if (!isset($_SESSION['last_cleanup']) || (time() - $_SESSION['last_cleanup'] > 300)) {
    cleanupExpiredOrders($conn);
    $_SESSION['last_cleanup'] = time();
}
