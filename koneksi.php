<?php
date_default_timezone_set('Asia/Jakarta');
$host = "localhost";
$user = "root";
$pass = "";
$db = "event_tiket";

try {
    $conn = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Connection failed");
}

// Database connected

// === AUTOMATIC CLEANUP EXPIRED ORDERS ===
// This returns ticket stock to the pool when payment time is up
try {
    // 1. Cari pesanan 'pending' yang sudah melewati tanggal_kadaluarsa
    $stmtExp = $conn->query("SELECT id_order FROM orders WHERE status = 'pending' AND tanggal_kadaluarsa < NOW()");
    $expiredOrders = $stmtExp->fetchAll(PDO::FETCH_ASSOC);

    if ($expiredOrders) {
        foreach ($expiredOrders as $order) {
            $id_order = $order['id_order'];
            
            // Ambil detail item untuk mengembalikan stok
            $stmtDet = $conn->prepare("SELECT id_tiket, qty FROM order_detail WHERE id_order = ?");
            $stmtDet->execute([$id_order]);
            $items = $stmtDet->fetchAll(PDO::FETCH_ASSOC);

            foreach ($items as $item) {
                $conn->prepare("UPDATE tiket SET kuota = kuota + ? WHERE id_tiket = ?")
                     ->execute([$item['qty'], $item['id_tiket']]);
            }

            // Update status pesanan jadi dibatalkan
            $conn->prepare("UPDATE orders SET status = 'cancelled' WHERE id_order = ?")
                 ->execute([$id_order]);
        }
    }
} catch (Exception $e) {
    // Silently fail to not break the site if something is wrong with the cleanup
}
?>
