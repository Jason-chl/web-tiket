<?php
session_start();
require_once 'koneksi.php';

if (!isset($_SESSION['id_user'])) { header("Location: login"); exit; }

$id_user = $_SESSION['id_user'];
try {
    $conn->exec("CREATE TABLE IF NOT EXISTS settings (setting_key VARCHAR(50) PRIMARY KEY, setting_value TEXT)");
    $conn->exec("INSERT IGNORE INTO settings (setting_key, setting_value) VALUES ('vip_price', '150000')");
} catch(Exception $e) {}

$stmt = $conn->prepare("SELECT is_vip, nama, username, email FROM users WHERE id_user = ?");
$stmt->execute([$id_user]);
$userRow  = $stmt->fetch();
$isVIP    = $userRow['is_vip'] ?? 0;
$userName = $userRow['username'] ?? $userRow['nama'] ?? '';

if ($isVIP) { header("Location: user_dashboard"); exit; }

$price = $conn->query("SELECT setting_value FROM settings WHERE setting_key = 'vip_price'")->fetchColumn() ?: 150000;

// Validate method selection error (passed back via GET)
$errorMsg = '';
if (isset($_GET['error']) && $_GET['error'] === 'no_method') {
    $errorMsg = "Pilih metode pembayaran terlebih dahulu!";
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout VIP Fast Track — TixNow</title>
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <script>(function() { const t = localStorage.getItem('theme') || 'dark'; document.documentElement.setAttribute('data-theme', t); })();</script>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root {
            --bg: #03050a; --surface: #0f1520; --card: #131c2e;
            --border: rgba(255,255,255,0.08); --accent: #c8b5ff;
            --accent-glow: rgba(200, 181, 255, 0.15);
            --text: #e8e8f0; --muted: #5e6a82;
            --gold: #ffd700; --gold-2: #ffae00; --gold-dim: rgba(255,215,0,0.1);
        }
        [data-theme="light"] {
            --bg: #f1f5f9; --surface: #ffffff; --card: #f8fafc;
            --border: rgba(0,0,0,0.08); --accent: #7c3aed;
            --accent-glow: rgba(124,58,237,0.1); --text: #0f172a; --muted: #64748b;
        }
        body { font-family: 'Inter', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; padding: 40px 20px; }
        .wrapper { max-width: 1000px; margin: 0 auto; display: grid; grid-template-columns: 1fr 340px; gap: 30px; }
        .header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 30px; grid-column: 1 / -1; border-bottom: 1px solid var(--border); padding-bottom: 20px; }
        .h-left { display: flex; align-items: center; gap: 16px; }
        .back-btn { display: inline-flex; align-items: center; gap: 6px; padding: 8px 14px; border-radius: 8px; border: 1px solid var(--border); color: var(--muted); font-size: 0.8rem; font-weight: 600; text-decoration: none; transition: 0.2s; cursor: pointer; }
        .back-btn:hover { border-color: var(--gold); color: var(--gold); }
        .h-title { font-family: 'Syne', sans-serif; font-size: 1.5rem; font-weight: 700; display: flex; align-items: center; gap: 10px; }
        .h-title-badge { background: linear-gradient(135deg, var(--gold), var(--gold-2)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; }
        .h-secure { background: rgba(52,211,153,0.1); color: #34d399; padding: 6px 12px; border-radius: 8px; font-weight: 600; font-size: 0.8rem; border: 1px solid rgba(52,211,153,0.2); display: flex; align-items: center; gap: 6px; }
        .pane { background: var(--surface); border: 1px solid var(--border); border-radius: 20px; padding: 24px; }
        .pane-title { font-family: 'Syne', sans-serif; font-size: 1.1rem; font-weight: 700; margin-bottom: 20px; color: var(--text); display: flex; align-items: center; gap: 8px; }
        .vip-banner { display: flex; align-items: center; gap: 16px; background: var(--gold-dim); border: 1px solid rgba(255,215,0,0.2); border-radius: 14px; padding: 16px 20px; margin-bottom: 24px; }
        .vip-banner-icon { width: 48px; height: 48px; border-radius: 12px; background: linear-gradient(135deg, var(--gold), var(--gold-2)); display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .vip-banner-info h4 { font-family: 'Syne', sans-serif; font-weight: 700; font-size: 1rem; margin-bottom: 2px; }
        .vip-banner-info p { font-size: 0.78rem; color: var(--muted); }
        .pm-group { margin-bottom: 24px; }
        .pm-label { font-size: 0.8rem; color: var(--muted); margin-bottom: 10px; text-transform: uppercase; letter-spacing: 0.07em; font-weight: 600; }
        .pm-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
        .pm-opt { position: relative; cursor: pointer; }
        .pm-opt input { position: absolute; opacity: 0; cursor: pointer; }
        .pm-box { display: flex; align-items: center; gap: 10px; padding: 14px; background: rgba(255,255,255,0.03); border: 1px solid var(--border); border-radius: 12px; transition: all 0.2s; }
        .pm-box:hover { background: rgba(255,255,255,0.06); }
        .pm-opt input:checked ~ .pm-box { background: var(--accent-glow); border-color: var(--accent); }
        .pm-opt input:checked ~ .pm-box .pm-indicator { background: var(--accent); border-color: var(--accent); }
        .pm-name { font-weight: 500; font-size: 0.85rem; flex: 1; }
        .pm-indicator { width: 18px; height: 18px; border-radius: 50%; border: 2px solid var(--muted); transition: 0.2s; flex-shrink: 0; }
        .pm-logo { font-weight: 800; font-size: 0.9rem; min-width: 30px; text-align: center; }
        .error-alert { background: rgba(248,113,113,0.1); border: 1px solid rgba(248,113,113,0.3); color: #f87171; padding: 12px 16px; border-radius: 10px; font-size: 0.85rem; margin-bottom: 20px; display: flex; align-items: center; gap: 8px; }
        .btn { width: 100%; border: none; padding: 14px; border-radius: 10px; font-weight: 700; font-family: 'Syne', sans-serif; cursor: pointer; transition: 0.2s; display: flex; justify-content: center; align-items: center; gap: 8px; margin-bottom: 12px; font-size: 0.95rem; text-decoration: none; }
        .btn-gold { background: linear-gradient(135deg, var(--gold), var(--gold-2)); color: #000; box-shadow: 0 10px 25px rgba(255,174,0,0.2); }
        .btn-gold:hover { transform: translateY(-2px); box-shadow: 0 15px 30px rgba(255,174,0,0.3); }
        .btn-sec { background: rgba(255,255,255,0.04); color: var(--text); border: 1px solid var(--border); }
        .btn-sec:hover { background: rgba(255,255,255,0.08); }
        .sum-header { display: flex; align-items: center; gap: 8px; margin-bottom: 20px; }
        .sum-header-icon { width: 38px; height: 38px; border-radius: 10px; background: linear-gradient(135deg, var(--gold), var(--gold-2)); display: flex; align-items: center; justify-content: center; }
        .sum-detail-row { display: flex; justify-content: space-between; align-items: center; padding: 12px 0; border-bottom: 1px solid var(--border); font-size: 0.85rem; }
        .sum-detail-row:last-of-type { border-bottom: none; }
        .sum-detail-row .lbl { color: var(--muted); }
        .sum-detail-row .val { font-weight: 600; color: var(--text); }
        .sum-detail-row .val.gold { color: var(--gold); }
        .sum-total { display: flex; justify-content: space-between; align-items: center; margin-top: 16px; padding-top: 16px; border-top: 1px solid var(--border); }
        .sum-total .lbl { font-weight: 700; font-size: 1rem; }
        .sum-total .val { font-family: 'Syne', sans-serif; font-weight: 800; font-size: 1.3rem; color: var(--gold); }
        .divider { height: 1px; background: var(--border); margin: 20px 0; }
        .perk-mini { display: flex; flex-direction: column; gap: 10px; }
        .perk-mini-item { display: flex; align-items: center; gap: 10px; font-size: 0.82rem; color: var(--muted); }
        .perk-mini-item svg { color: #34d399; flex-shrink: 0; }
        .secure-note { text-align: center; font-size: 0.72rem; color: var(--muted); margin-top: 16px; display: flex; align-items: center; justify-content: center; gap: 5px; }
        @media (max-width: 768px) { .wrapper { grid-template-columns: 1fr; } .pm-grid { grid-template-columns: 1fr; } }
        @keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
    </style>
</head>
<body>
<div class="wrapper">
    <div class="header">
        <div class="h-left">
            <a href="buy_vip" class="back-btn">
                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"/></svg>
                Kembali
            </a>
            <div class="h-title">
                Checkout <span class="h-title-badge">VIP Fast Track</span>
            </div>
        </div>
        <div class="h-secure">
            <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
            Transaksi Aman
        </div>
    </div>

    <div class="pane">
        <div class="vip-banner">
            <div class="vip-banner-icon">
                <svg width="24" height="24" viewBox="0 0 20 20" fill="#000"><path d="M10 1l2.39 5.26L18 7.27l-4 3.89.94 5.5L10 14l-4.94 2.66.94-5.5L2 7.27l5.61-.01L10 1z"/></svg>
            </div>
            <div class="vip-banner-info">
                <h4>VIP Fast Track — Akses Seumur Hidup</h4>
                <p>Skip antrean di semua event. Bayar sekali, berlaku permanen.</p>
            </div>
        </div>

        <?php if (!empty($errorMsg)): ?>
        <div class="error-alert">
            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
            <?php echo $errorMsg; ?>
        </div>
        <?php endif; ?>

        <div class="pane-title">Pilih Metode Pembayaran</div>

        <form method="POST" action="vip_payment_gateway" id="paymentForm">
            <input type="hidden" name="payment_method" id="selectedMethod">
            <div class="pm-group">
                <div class="pm-label">Virtual Account Bank</div>
                <div class="pm-grid">
                    <label class="pm-opt"><input type="radio" name="pm" value="bca" onchange="selectMethod('bca')"><div class="pm-box"><div class="pm-logo" style="color:#005aaa;">BCA</div><div class="pm-name">BCA Virtual Account</div><div class="pm-indicator"></div></div></label>
                    <label class="pm-opt"><input type="radio" name="pm" value="mandiri" onchange="selectMethod('mandiri')"><div class="pm-box"><div class="pm-logo" style="color:#ffb700;">M</div><div class="pm-name">Mandiri VA</div><div class="pm-indicator"></div></div></label>
                    <label class="pm-opt"><input type="radio" name="pm" value="bni" onchange="selectMethod('bni')"><div class="pm-box"><div class="pm-logo" style="color:#f06400;">BNI</div><div class="pm-name">BNI VA</div><div class="pm-indicator"></div></div></label>
                    <label class="pm-opt"><input type="radio" name="pm" value="bri" onchange="selectMethod('bri')"><div class="pm-box"><div class="pm-logo" style="color:#005baa;">BRI</div><div class="pm-name">BRI VA</div><div class="pm-indicator"></div></div></label>
                </div>
            </div>
            <div class="pm-group">
                <div class="pm-label">E-Wallet</div>
                <div class="pm-grid">
                    <label class="pm-opt"><input type="radio" name="pm" value="gopay" onchange="selectMethod('gopay')"><div class="pm-box"><div class="pm-logo" style="color:#00a5cf;">G</div><div class="pm-name">GoPay</div><div class="pm-indicator"></div></div></label>
                    <label class="pm-opt"><input type="radio" name="pm" value="ovo" onchange="selectMethod('ovo')"><div class="pm-box"><div class="pm-logo" style="color:#4c3494;">OVO</div><div class="pm-name">OVO</div><div class="pm-indicator"></div></div></label>
                    <label class="pm-opt"><input type="radio" name="pm" value="dana" onchange="selectMethod('dana')"><div class="pm-box"><div class="pm-logo" style="color:#118eea;">D</div><div class="pm-name">DANA</div><div class="pm-indicator"></div></div></label>
                    <label class="pm-opt"><input type="radio" name="pm" value="qris" onchange="selectMethod('qris')"><div class="pm-box"><div class="pm-logo" style="color:#e02020;">⊞</div><div class="pm-name">QRIS</div><div class="pm-indicator"></div></div></label>
                </div>
            </div>
            <div style="margin-top: 32px; display: flex; flex-direction: column; gap: 12px;">
                <button type="button" class="btn btn-gold" onclick="submitPay()">
                    <svg width="18" height="18" viewBox="0 0 20 20" fill="currentColor"><path d="M10 1l2.39 5.26L18 7.27l-4 3.89.94 5.5L10 14l-4.94 2.66.94-5.5L2 7.27l5.61-.01L10 1z"/></svg>
                    Lanjut ke Pembayaran — Rp <?php echo number_format($price, 0, ',', '.'); ?>
                </button>
                <a href="buy_vip" class="btn btn-sec">Batal, kembali ke preview</a>
            </div>
        </form>
    </div>

    <div>
        <div class="pane">
            <div class="sum-header">
                <div class="sum-header-icon"><svg width="20" height="20" viewBox="0 0 20 20" fill="#000"><path d="M10 1l2.39 5.26L18 7.27l-4 3.89.94 5.5L10 14l-4.94 2.66.94-5.5L2 7.27l5.61-.01L10 1z"/></svg></div>
                <div class="pane-title" style="margin-bottom:0;">Ringkasan Pesanan</div>
            </div>
            <div class="sum-detail-row"><span class="lbl">Akun</span><span class="val"><?php echo htmlspecialchars($userName); ?></span></div>
            <div class="sum-detail-row"><span class="lbl">Paket</span><span class="val gold">VIP Fast Track</span></div>
            <div class="sum-detail-row"><span class="lbl">Berlaku</span><span class="val">Permanen</span></div>
            <div class="sum-detail-row"><span class="lbl">Harga</span><span class="val">Rp <?php echo number_format($price, 0, ',', '.'); ?></span></div>
            <div class="sum-total"><span class="lbl">Total Tagihan</span><span class="val">Rp <?php echo number_format($price, 0, ',', '.'); ?></span></div>
            <div class="divider"></div>
            <div style="font-size:0.78rem;color:var(--muted);margin-bottom:12px;font-weight:600;text-transform:uppercase;letter-spacing:0.07em;">Yang Kamu Dapatkan</div>
            <div class="perk-mini">
                <div class="perk-mini-item"><svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>Skip Waiting Room di semua event</div>
                <div class="perk-mini-item"><svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>Badge VIP emas di samping namamu</div>
                <div class="perk-mini-item"><svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>Prioritas saat War Tiket</div>
                <div class="perk-mini-item"><svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>Berlaku permanen, tidak expired</div>
            </div>
            <div class="secure-note">
                <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                Transaksi aman &amp; terenkripsi SSL
            </div>
        </div>
    </div>
</div>

<script>
    function selectMethod(m) { document.getElementById('selectedMethod').value = m; }
    function submitPay() {
        const method = document.getElementById('selectedMethod').value;
        if (!method) { alert('Silakan pilih metode pembayaran terlebih dahulu!'); return; }
        const btn = document.querySelector('.btn-gold');
        btn.disabled = true;
        btn.innerHTML = '<svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="animation:spin 1s linear infinite"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg> Memproses...';
        document.getElementById('paymentForm').submit();
    }
</script>
</body>
</html>
