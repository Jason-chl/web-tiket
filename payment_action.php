<?php
session_start();
require_once 'koneksi.php';

if (!isset($_SESSION['id_user']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index");
    exit;
}

$id_order = $_POST['id_order'] ?? '';

if (!$id_order) {
    header("Location: user_dashboard");
    exit;
}

try {
    $conn->beginTransaction();

    // GET ORDER
    $oStmt = $conn->prepare("SELECT * FROM orders WHERE id_order = ? AND id_user = ? FOR UPDATE");
    $oStmt->execute([$id_order, $_SESSION['id_user']]);
    $order = $oStmt->fetch();

    if (!$order || ($order['status'] !== 'pending' && $order['status'] !== 'dp_paid')) {
        throw new Exception("Order tidak valid atau sudah diproses.");
    }

    $id_order = $order['id_order'];

    // Jika status saat ini adalah dp_paid, berarti ini adalah proses PELUNASAN
    $isPelunasan = ($order['status'] === 'dp_paid');
    
    // Tentukan apakah pembayaran kali ini adalah DP (30%) atau Full
    // Jika dari pending, lihat pilihan user (dp atau full)
    // Jika dari dp_paid, pasti tujuannya adalah full (paid)
    $payDPNow = ($order['payment_type'] === 'dp' && !$isPelunasan);
    
    $status = $payDPNow ? 'dp_paid' : 'paid';
    
    if ($isPelunasan) {
        // Tagihan adalah sisa yang belum dibayar
        $amountToPay = $order['total'] - $order['amount_paid'];
    } else {
        // Tagihan baru (30% atau 100%)
        $amountToPay = $payDPNow ? ($order['total'] * 0.3) : $order['total'];
    }

    // UPDATE ORDER
    $upStmt = $conn->prepare("UPDATE orders SET status = ?, amount_paid = amount_paid + ?, tanggal_pembayaran = NOW() WHERE id_order = ?");
    $upStmt->execute([$status, $amountToPay, $id_order]);

    // INSERT PAYMENTS tracking
    $payStmt = $conn->prepare("
        INSERT INTO payments (id_order, jumlah, metode_pembayaran, status, external_transaction_id) 
        VALUES (?, ?, ?, 'success', ?)
    ");
    // dummy transaction ID
    $transId = "TRX-" . strtoupper(uniqid());
    $payStmt->execute([$id_order, $amountToPay, $order['metode_pembayaran'], $transId]);

    // GENERATE TIKET (Only if status is 'paid'/Full Payment)
    if (!$payDPNow) {
        // Get Order Items
    $iStmt = $conn->prepare("SELECT id_detail, qty FROM order_detail WHERE id_order = ?");
    $iStmt->execute([$id_order]);
    $items = $iStmt->fetchAll();

    // Get user details for ticket holder name default
    $uStmt = $conn->prepare("SELECT nama, email, no_telepon FROM users WHERE id_user = ?");
    $uStmt->execute([$_SESSION['id_user']]);
    $user = $uStmt->fetch();

    $tStmt = $conn->prepare("
        INSERT INTO attendee (id_detail, kode_tiket, qr_code_url, status_checkin, nama_pemegang, email_pemegang, no_telepon_pemegang) 
        VALUES (?, ?, ?, 'active', ?, ?, ?)
    ");

    foreach ($items as $item) {
        $qty = (int)$item['qty'];
        for ($i = 0; $i < $qty; $i++) {
            // Generate unique ticket code
            $kode_tiket = "TIX-" . date('ymd') . "-" . strtoupper(substr(md5(uniqid(rand(), true)), 0, 6));
            
            // Generate QR Code via goqr.me API (aktif dan gratis)
            $externalUrl = "https://api.qrserver.com/v1/create-qr-code/?size=400x400&margin=10&data=" . urlencode($kode_tiket);
            $localPath = "qrcodes/" . $kode_tiket . ".png";
            
            // Simpan konten gambar ke folder qrcodes/
            $opts = stream_context_create(['http' => ['timeout' => 5]]);
            $imgData = @file_get_contents($externalUrl, false, $opts);
            if ($imgData && strlen($imgData) > 1000) { // Pastikan data valid (bukan error page)
                file_put_contents($localPath, $imgData);
                $qrPathForDb = $localPath;
            } else {
                $qrPathForDb = $externalUrl; // Fallback jika gagal download
            }

            $tStmt->execute([
                $item['id_detail'], 
                $kode_tiket, 
                $qrPathForDb, 
                $user['nama'], 
                $user['email'], 
                $user['no_telepon']
            ]);
        }
    }
}

    $conn->commit();
    $msg = $payDPNow ? "dp_paid" : "paid";
    header("Location: user_orders?msg=" . $msg);
    exit;

} catch (Exception $e) {
    if($conn->inTransaction()) {
        $conn->rollBack();
    }
    header("Location: user_orders?msg=error&err=" . urlencode($e->getMessage()));
    exit;
}
?>
