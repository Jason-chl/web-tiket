<?php
session_start();
require_once 'koneksi.php';

if (!isset($_SESSION['id_user'])) { header("Location: login"); exit; }

$id_user        = $_SESSION['id_user'];
$payment_method = $_POST['payment_method'] ?? '';

if (!$payment_method) {
    header("Location: vip_checkout");
    exit;
}

// Validate user is still not VIP
$stmt = $conn->prepare("SELECT is_vip, nama, username FROM users WHERE id_user = ?");
$stmt->execute([$id_user]);
$user  = $stmt->fetch();
if ($user['is_vip']) { header("Location: user_dashboard"); exit; }
$userName = $user['username'] ?? $user['nama'];

// Get VIP price
try {
    $conn->exec("CREATE TABLE IF NOT EXISTS settings (setting_key VARCHAR(50) PRIMARY KEY, setting_value TEXT)");
    $conn->exec("INSERT IGNORE INTO settings (setting_key, setting_value) VALUES ('vip_price', '150000')");
} catch(Exception $e) {}
$price    = $conn->query("SELECT setting_value FROM settings WHERE setting_key = 'vip_price'")->fetchColumn() ?: 150000;
$userName = $user['nama'];

// Method info map
$methodMap = [
    'bca'     => ['name' => 'BCA Virtual Account',     'short' => 'BCA',     'color' => '#005aaa', 'type' => 'va'],
    'mandiri' => ['name' => 'Mandiri Virtual Account', 'short' => 'Mandiri', 'color' => '#ffb700', 'type' => 'va'],
    'bni'     => ['name' => 'BNI Virtual Account',     'short' => 'BNI',     'color' => '#f06400', 'type' => 'va'],
    'bri'     => ['name' => 'BRI Virtual Account',     'short' => 'BRI',     'color' => '#005baa', 'type' => 'va'],
    'gopay'   => ['name' => 'GoPay',                   'short' => 'GoPay',   'color' => '#00a5cf', 'type' => 'ewallet'],
    'ovo'     => ['name' => 'OVO',                     'short' => 'OVO',     'color' => '#4c3494', 'type' => 'ewallet'],
    'dana'    => ['name' => 'DANA',                    'short' => 'DANA',    'color' => '#118eea', 'type' => 'ewallet'],
    'qris'    => ['name' => 'QRIS',                    'short' => 'QRIS',    'color' => '#e02020', 'type' => 'qris'],
];
$mInfo  = $methodMap[$payment_method] ?? ['name' => 'Transfer Bank', 'short' => 'Bank', 'color' => '#333', 'type' => 'va'];
$isVA   = ($mInfo['type'] === 'va');
$isQRIS = ($mInfo['type'] === 'qris');

// Generate dummy VA / account number
if ($isVA) {
    $refNumber = "8800" . rand(10000000, 99999999);
    $refLabel  = "Nomor Virtual Account";
} elseif ($isQRIS) {
    $refNumber = strtoupper(substr(md5($id_user . time()), 0, 16));
    $refLabel  = "Kode QRIS";
} else {
    $refNumber = "08" . rand(100000000, 999999999);
    $refLabel  = "Nomor Akun Terdaftar";
}

// Expiry: 15 minutes from now
$expiry    = date('c', time() + 15 * 60);
$expiryFmt = date('H:i, d M Y', time() + 15 * 60);

// Handle confirm payment
if (isset($_POST['confirm_pay'])) {
    $conn->prepare("UPDATE users SET is_vip = 1 WHERE id_user = ?")->execute([$id_user]);
    header("Location: user_dashboard?vip_activated=1");
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gateway Pembayaran VIP — TixNow</title>
    <link href="https://fonts.googleapis.com/css2?family=Space+Mono:wght@400;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Inter', sans-serif;
            background: #e2e8f0;
            color: #1e293b;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .gateway-card {
            background: white;
            width: 100%;
            max-width: 460px;
            border-radius: 24px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.12);
            overflow: hidden;
        }

        /* HEADER */
        .gw-header {
            background: <?php echo $mInfo['color']; ?>;
            color: white;
            padding: 28px 24px;
            text-align: center;
            position: relative;
        }
        .gw-header::after {
            content: '';
            position: absolute;
            bottom: -1px; left: 0; right: 0;
            height: 20px;
            background: white;
            border-radius: 20px 20px 0 0;
        }
        .gw-logo { font-size: 1.6rem; font-weight: 800; letter-spacing: -1px; margin-bottom: 4px; }
        .gw-subtitle { font-size: 0.82rem; opacity: 0.85; }

        /* BODY */
        .gw-body { padding: 28px 24px; }

        .timer-area { text-align: center; margin-bottom: 22px; }
        .timer-label { font-size: 0.78rem; color: #64748b; margin-bottom: 4px; }
        .timer-box {
            font-family: 'Space Mono', monospace;
            font-size: 2rem; font-weight: 700;
            color: #ef4444;
            background: #fef2f2;
            border: 1px solid #fecaca;
            border-radius: 12px;
            display: inline-block;
            padding: 8px 24px;
        }

        .divider { height: 1px; background: #e2e8f0; margin: 20px 0; }

        .info-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; font-size: 0.88rem; }
        .info-row .lbl { color: #64748b; }
        .info-row .val { font-weight: 600; text-align: right; max-width: 60%; word-break: break-word; }
        .info-row.total { border-top: 2px dashed #e2e8f0; padding-top: 14px; margin-top: 6px; font-size: 1.05rem; }
        .info-row.total .val { color: <?php echo $mInfo['color']; ?>; font-size: 1.2rem; }

        /* VA / Akun Box */
        .va-box {
            background: #f8fafc;
            border: 1.5px solid #cbd5e1;
            border-radius: 14px;
            padding: 22px;
            text-align: center;
            margin: 20px 0;
            position: relative;
        }
        .va-label {
            font-size: 0.72rem; color: #64748b;
            text-transform: uppercase; letter-spacing: 1.5px;
            font-weight: 700; margin-bottom: 10px;
        }
        .va-number {
            font-family: 'Space Mono', monospace;
            font-size: 1.7rem; font-weight: 700;
            color: #0f172a;
            letter-spacing: 3px;
            margin-bottom: 12px;
        }
        .copy-btn {
            display: inline-flex; align-items: center; gap: 6px;
            background: #f1f5f9; border: 1px solid #cbd5e1;
            color: #475569; font-size: 0.78rem; font-weight: 600;
            padding: 6px 14px; border-radius: 6px; cursor: pointer;
            transition: all 0.2s; border: none;
        }
        .copy-btn:hover { background: <?php echo $mInfo['color']; ?>; color: white; }
        .copy-btn.copied { background: #10b981; color: white; }

        /* Instructions */
        .instructions {
            background: #f8fafc;
            border-radius: 12px;
            padding: 16px 18px;
            margin-bottom: 22px;
        }
        .instructions-title { font-size: 0.8rem; font-weight: 700; color: #475569; margin-bottom: 10px; display: flex; align-items: center; gap: 6px; }
        .instructions ol { padding-left: 18px; }
        .instructions li { font-size: 0.8rem; color: #64748b; margin-bottom: 6px; line-height: 1.5; }

        /* Expiry note */
        .expiry-note {
            text-align: center; font-size: 0.75rem; color: #94a3b8;
            margin-bottom: 20px;
        }

        /* Buttons */
        .btn {
            width: 100%; padding: 15px; border-radius: 12px;
            font-weight: 700; font-family: 'Inter', sans-serif;
            cursor: pointer; transition: 0.2s; border: none;
            font-size: 0.95rem; color: white;
            display: flex; align-items: center; justify-content: center; gap: 8px;
        }
        .btn-success { background: #10b981; box-shadow: 0 4px 15px rgba(16,185,129,0.3); margin-bottom: 12px; }
        .btn-success:hover { background: #059669; transform: translateY(-2px); }
        .btn-back { background: transparent; color: #94a3b8; font-size: 0.85rem; box-shadow: none; }
        .btn-back:hover { color: #ef4444; }
    </style>
</head>
<body>

<div class="gateway-card">
    <div class="gw-header">
        <div class="gw-logo"><?php echo htmlspecialchars($mInfo['name']); ?></div>
        <div class="gw-subtitle">TixNow · VIP Fast Track · Secure Payment</div>
    </div>

    <div class="gw-body">
        <!-- TIMER -->
        <div class="timer-area">
            <div class="timer-label">Selesaikan pembayaran dalam</div>
            <div class="timer-box" id="timer">15:00</div>
        </div>

        <!-- ORDER INFO -->
        <div class="info-row">
            <span class="lbl">Merchant</span>
            <span class="val">TixNow Official</span>
        </div>
        <div class="info-row">
            <span class="lbl">Item</span>
            <span class="val">VIP Fast Track</span>
        </div>
        <div class="info-row">
            <span class="lbl">Akun</span>
            <span class="val"><?php echo htmlspecialchars($userName); ?></span>
        </div>
        <div class="info-row">
            <span class="lbl">Berlaku</span>
            <span class="val">Permanen</span>
        </div>
        <div class="info-row total">
            <span class="lbl">Total Pembayaran</span>
            <span class="val">Rp <?php echo number_format($price, 0, ',', '.'); ?></span>
        </div>

        <div class="divider"></div>

        <!-- VA / ACCOUNT NUMBER BOX -->
        <div class="va-box">
            <div class="va-label"><?php echo $refLabel; ?></div>
            <div class="va-number" id="vaNumber"><?php echo $refNumber; ?></div>
            <button class="copy-btn" id="copyBtn" onclick="copyVA()">
                <svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                Salin Nomor
            </button>
        </div>

        <!-- EXPIRY NOTE -->
        <div class="expiry-note">
            Berlaku hingga <?php echo $expiryFmt; ?>
        </div>

        <!-- INSTRUCTIONS -->
        <div class="instructions">
            <div class="instructions-title">
                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                Instruksi Pembayaran
            </div>
            <ol>
                <?php if ($isVA): ?>
                <li>Buka aplikasi mobile banking atau ATM <?php echo $mInfo['short']; ?> kamu.</li>
                <li>Pilih menu <strong>Transfer / Virtual Account</strong>.</li>
                <li>Masukkan nomor VA di atas dan pastikan nama tujuan <strong>TixNow Official</strong>.</li>
                <li>Masukkan nominal <strong>Rp <?php echo number_format($price, 0, ',', '.'); ?></strong> tepat.</li>
                <?php elseif ($isQRIS): ?>
                <li>Buka aplikasi e-wallet atau banking kamu yang mendukung QRIS.</li>
                <li>Pilih menu <strong>Scan QR / QRIS</strong>.</li>
                <li>Masukkan kode di atas atau scan QR yang tersedia.</li>
                <li>Konfirmasi nominal <strong>Rp <?php echo number_format($price, 0, ',', '.'); ?></strong>.</li>
                <?php else: ?>
                <li>Buka aplikasi <?php echo $mInfo['short']; ?> kamu.</li>
                <li>Pilih menu <strong>Bayar / Transfer</strong>.</li>
                <li>Masukkan nomor akun di atas sebagai tujuan pembayaran.</li>
                <li>Masukkan nominal tepat <strong>Rp <?php echo number_format($price, 0, ',', '.'); ?></strong>.</li>
                <?php endif; ?>
                <li>Simpan bukti transaksi setelah pembayaran berhasil.</li>
                <li>Tekan tombol <strong>"Konfirmasi Pembayaran"</strong> di bawah ini untuk mengaktifkan VIP.</li>
            </ol>
        </div>

        <!-- ACTION BUTTONS -->
        <form method="POST">
            <input type="hidden" name="payment_method" value="<?php echo htmlspecialchars($payment_method); ?>">
            <button type="submit" name="confirm_pay" class="btn btn-success">
                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                Konfirmasi Pembayaran
            </button>
        </form>

        <a href="vip_checkout">
            <button type="button" class="btn btn-back">← Ganti Metode Pembayaran</button>
        </a>
    </div>
</div>

<script>
    // Countdown 15 minutes
    let total = 15 * 60;
    const timerEl = document.getElementById('timer');
    const interval = setInterval(() => {
        total--;
        if (total <= 0) {
            clearInterval(interval);
            timerEl.textContent = '00:00';
            timerEl.style.color = '#94a3b8';
            alert('Waktu pembayaran telah habis! Silakan ulangi proses.');
            window.location.href = 'vip_checkout';
            return;
        }
        const m = String(Math.floor(total / 60)).padStart(2, '0');
        const s = String(total % 60).padStart(2, '0');
        timerEl.textContent = `${m}:${s}`;
        if (total < 60) timerEl.style.color = '#ef4444';
    }, 1000);

    // Copy VA number
    function copyVA() {
        const va  = document.getElementById('vaNumber').innerText;
        const btn = document.getElementById('copyBtn');
        navigator.clipboard.writeText(va).then(() => {
            btn.classList.add('copied');
            btn.innerHTML = '<svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg> Tersalin!';
            setTimeout(() => {
                btn.classList.remove('copied');
                btn.innerHTML = '<svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg> Salin Nomor';
            }, 2500);
        });
    }
</script>
</body>
</html>
