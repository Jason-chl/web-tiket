<?php
session_start();
require_once 'koneksi.php';

if (!isset($_SESSION['id_user'])) {
    header("Location: index");
    exit;
}

$id_order_code = $_POST['order_code'] ?? '';
$action_type = $_POST['action_type'] ?? 'pay_now';
$payment_method = $_POST['payment_method'] ?? 'bca';
$voucher_code = strtoupper(trim($_POST['voucher_code'] ?? ''));

if (!$id_order_code) {
    header("Location: user_dashboard");
    exit;
}

// Get Order
$stmt = $conn->prepare("
    SELECT o.*, c.nama_event
    FROM orders o
    JOIN event c ON o.id_event = c.id_event
    WHERE o.order_code = ? AND o.id_user = ?
");
$stmt->execute([$id_order_code, $_SESSION['id_user']]);
$order = $stmt->fetch();

if (!$order || ($order['status'] !== 'pending' && $order['status'] !== 'dp_paid')) {
    echo "<script>alert('Order tidak valid atau sudah dibayar.'); window.location.href='user_orders';</script>";
    exit;
}

$id_order = $order['id_order']; // internal ID

// UPDATE Order with Payment Method and Action Type (DP goal)
if ($order['status'] === 'pending') {
    $payType = ($action_type === 'pay_dp') ? 'dp' : 'full';
    $conn->prepare("UPDATE orders SET metode_pembayaran = ?, payment_type = ? WHERE id_order = ?")
         ->execute([$payment_method, $payType, $id_order]);
    // Refresh order data
    $order['metode_pembayaran'] = $payment_method;
    $order['payment_type'] = $payType;
}

// APPLY VOUCHER LOGIC (Only if not already applied or handled)
$discount = 0;
if ($voucher_code && $order['discount'] == 0 && $order['status'] === 'pending') {
    $stmtV = $conn->prepare("SELECT * FROM vouchers WHERE code = ? AND status = 'active' AND id_event = ?");
    $stmtV->execute([$voucher_code, $order['id_event']]);
    $v = $stmtV->fetch();

    if ($v && $v['current_usage'] < $v['max_usage']) {
        $discount = (int)$v['discount_amount'];
        $newTotal = max(0, $order['total'] - $discount);
        
        // Update database
        $conn->prepare("UPDATE orders SET total = ?, discount = ? WHERE id_order = ?")
             ->execute([$newTotal, $discount, $id_order]);
        
        // Update usage count
        $conn->prepare("UPDATE vouchers SET current_usage = current_usage + 1 WHERE id_voucher = ?")
             ->execute([$v['id_voucher']]);
             
        $order['total'] = $newTotal;
        $order['discount'] = $discount;
    }
}

$isCurrentlyPaidDP = ($order['status'] === 'dp_paid');
$shouldPayDP = ($order['payment_type'] === 'dp' && !$isCurrentlyPaidDP);

if ($isCurrentlyPaidDP) {
    $tagihan = $order['total'] - $order['amount_paid'];
    $labelTagihan = "Pelunasan Pembayaran";
} else {
    $tagihan = $shouldPayDP ? ($order['total'] * 0.3) : $order['total'];
    $labelTagihan = $shouldPayDP ? "Pembayaran DP (30%)" : "Pembayaran Lunas";
}

$methodMap = [
    'bca' => ['name' => 'BCA Virtual Account', 'color' => '#005aaa'],
    'mandiri' => ['name' => 'Mandiri Virtual Account', 'color' => '#ffb700'],
    'bni' => ['name' => 'BNI Virtual Account', 'color' => '#f06400'],
    'gopay' => ['name' => 'GoPay', 'color' => '#00a5cf'],
    'ovo' => ['name' => 'OVO', 'color' => '#4c3494']
];

$mKey = $order['metode_pembayaran'] ?: 'bca';
$methodInfo = $methodMap[$mKey] ?? ['name' => 'Transfer Bank', 'color' => '#333'];

// Generate a dummy VA or Ref Number
$refNumber = strtoupper(substr(md5($id_order), 0, 12));
if(in_array($mKey, ['bca', 'mandiri', 'bni'])) {
    $refNumber = "8800" . rand(10000000, 99999999);
} else {
    $refNumber = "08" . rand(100000000, 999999999);
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gateway Pembayaran</title>
    <link href="https://fonts.googleapis.com/css2?family=Space+Mono:wght@400;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Inter', sans-serif; background: #e2e8f0; color: #1e293b; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
        
        .gateway-card {
            background: white; width: 100%; max-width: 450px;
            border-radius: 24px; box-shadow: 0 20px 40px rgba(0,0,0,0.08); overflow: hidden;
            position: relative;
        }

        .gw-header {
            background: <?php echo $methodInfo['color']; ?>;
            color: white; padding: 24px 24px 28px; text-align: center; position: relative;
        }
        .gw-back {
            position: absolute; top: 14px; left: 16px;
            display: inline-flex; align-items: center; gap: 5px;
            color: rgba(255,255,255,0.7); font-size: 0.78rem; text-decoration: none;
            transition: color 0.2s;
        }
        .gw-back:hover { color: white; }
        .gw-logo { font-size: 1.4rem; font-weight: 800; letter-spacing: -1px; margin-bottom: 6px; }
        .gw-title { font-size: 0.85rem; opacity: 0.85; }

        .gw-body { padding: 30px 24px; }
        
        .timer-box { font-family: 'Space Mono', monospace; font-size: 1.5rem; font-weight: 700; text-align: center; color: #ef4444; margin-bottom: 24px; }

        .info-row { display: flex; justify-content: space-between; margin-bottom: 16px; font-size: 0.9rem; }
        .info-row.total { border-top: 1px dashed #cbd5e1; padding-top: 16px; margin-top: 8px; font-weight: 700; font-size: 1.1rem; }
        
        .va-box { background: #f1f5f9; border: 1px solid #cbd5e1; border-radius: 12px; padding: 20px; text-align: center; margin: 24px 0; }
        .va-label { font-size: 0.8rem; color: #64748b; text-transform: uppercase; font-weight: 600; margin-bottom: 8px; }
        .va-number { font-family: 'Space Mono', monospace; font-size: 1.6rem; font-weight: 700; color: #0f172a; letter-spacing: 2px; }

        .instructions { font-size: 0.8rem; color: #64748b; margin-bottom: 30px; line-height: 1.6; }
        .instructions ul { padding-left: 20px; }
        
        .btn { width: 100%; padding: 16px; border-radius: 12px; font-weight: 600; font-family: 'Inter', sans-serif; cursor: pointer; transition: 0.2s; border: none; font-size: 1rem; color: white; display: flex; align-items: center; justify-content: center; gap: 8px; }
        .btn-success { background: #10b981; box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3); }
        .btn-success:hover { background: #059669; transform: translateY(-2px); }
        .btn-cancel { background: transparent; color: #94a3b8; font-size: 0.9rem; margin-top: 16px; box-shadow: none; }
        .btn-cancel:hover { color: #ef4444; }

    </style>
</head>
<body>

<div class="gateway-card">
    <div class="gw-header">
        <a href="checkout?order=<?php echo $order['order_code']; ?>" class="gw-back">
            <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            Ganti Metode
        </a>
        <div class="gw-logo"><?php echo htmlspecialchars($methodInfo['name']); ?></div>
        <div class="gw-title">Secure Payment Gateway</div>
    </div>
    
    <div class="gw-body">
        <?php if (!$isCurrentlyPaidDP): ?>
            <div style="text-align:center; font-size:0.85rem; color:#64748b; margin-bottom:4px;">Selesaikan pembayaran dalam</div>
            <div class="timer-box" id="timer">-- : --</div>
        <?php endif; ?>

        <div class="info-row">
            <span style="color:#64748b;">Merchant</span>
            <span style="font-weight:600;">TixNow Official</span>
        </div>
        <div class="info-row">
            <span style="color:#64748b;">Item</span>
            <span style="text-align:right; max-width:200px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"><?php echo htmlspecialchars($order['nama_event']); ?></span>
        </div>
        <?php if ($isCurrentlyPaidDP): ?>
        <div class="info-row">
            <span style="color:#64748b;">Total Harga</span>
            <span>Rp <?php echo number_format($order['total'], 0, ',', '.'); ?></span>
        </div>
        <div class="info-row" style="color:#10b981; font-weight:600;">
            <span>DP Terbayar (30%)</span>
            <span>- Rp <?php echo number_format($order['amount_paid'], 0, ',', '.'); ?></span>
        </div>
        <?php endif; ?>
        <?php if ($order['discount'] > 0): ?>
        <div class="info-row" style="color:#ef4444;">
            <span>Potongan Voucher</span>
            <span>- Rp <?php echo number_format($order['discount'], 0, ',', '.'); ?></span>
        </div>
        <?php endif; ?>
        <div class="info-row total">
            <span><?php echo $labelTagihan; ?></span>
            <span style="color:<?php echo $methodInfo['color']; ?>">Rp <?php echo number_format($tagihan, 0, ',', '.'); ?></span>
        </div>
        
        <?php if($shouldPayDP): ?>
        <div style="font-size: 0.75rem; color: #64748b; margin-top: -10px; margin-bottom: 15px; text-align: right;">
            Sisa pelunasan: Rp <?php echo number_format($order['total'] - $tagihan, 0, ',', '.'); ?>
        </div>
        <?php endif; ?>

        <div class="va-box">
            <div class="va-label"><?php echo in_array($mKey, ['gopay','ovo']) ? 'Nomor Akun Terdaftar' : 'Nomor Virtual Account'; ?></div>
            <div class="va-number"><?php echo $refNumber; ?></div>
        </div>

        <div class="instructions">
            <strong>Instruksi Pembayaran:</strong>
            <ul>
                <li>Pastikan nominal yang Anda masukkan sesuai dengan total tagihan.</li>
                <li>Simpan bukti transaksi setelah pembayaran berhasil dilakukan.</li>
                <li>Setelah menekan tombol 'Bayar Sekarang', sistem akan memverifikasi transaksi Anda secara otomatis.</li>
            </ul>
        </div>

        <form action="payment_action" method="POST">
            <input type="hidden" name="id_order" value="<?php echo htmlspecialchars($id_order); ?>">
            <button type="submit" class="btn btn-success">
                Bayar Sekarang
                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
            </button>
        </form>
        
        <a href="checkout?order=<?php echo $order['order_code']; ?>" style="display:block; text-align:center; margin-top:18px; color:#94a3b8; font-size:0.78rem; text-decoration:none;">Batal pembayaran</a>
    </div>
</div>

<script>
    <?php if (!$isCurrentlyPaidDP): ?>
    const expDate = new Date("<?php echo date('c', strtotime($order['tanggal_kadaluarsa'])); ?>").getTime();
    
    const x = setInterval(function() {
        const now = new Date().getTime();
        const distance = expDate - now;

        if (distance < 0) {
            clearInterval(x);
            document.getElementById("timer").innerHTML = "00 : 00";
            alert("Waktu pembayaran telah habis!");
            window.location.href = 'user_orders';
        } else {
            const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
            const seconds = Math.floor((distance % (1000 * 60)) / 1000);
            document.getElementById("timer").innerHTML = `${String(minutes).padStart(2,'0')} : ${String(seconds).padStart(2,'0')}`;
        }
    }, 1000);
    <?php endif; ?>
</script>
</body>
</html>
