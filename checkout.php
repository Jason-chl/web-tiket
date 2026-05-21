<?php
session_start();
require_once 'koneksi.php';

if (!isset($_SESSION['id_user'])) {
    header("Location: index");
    exit;
}

$id_order = $_GET['order'] ?? '';
if (!$id_order) {
    header("Location: user_dashboard");
    exit;
}

$stmt = $conn->prepare("
    SELECT o.*, c.nama_event, c.tanggal, c.poster_url, c.venue
    FROM orders o
    JOIN event c ON o.id_event = c.id_event
    WHERE o.order_code = ? AND o.id_user = ?
");
$stmt->execute([$id_order, $_SESSION['id_user']]);
$order = $stmt->fetch();

if (!$order || ($order['status'] !== 'pending' && $order['status'] !== 'dp_paid')) {
    echo "<script>alert('Order tidak valid atau sudah dibayar.'); window.location.href='user_orders';</script>";
    exit;
}

$isPelunasan = ($order['status'] === 'dp_paid');

// Get Items
$istmt = $conn->prepare("
    SELECT i.*, k.nama_tiket 
    FROM order_detail i
    JOIN tiket k ON i.id_tiket = k.id_tiket
    WHERE i.id_order = ?
");
$istmt->execute([$order['id_order']]);
$items = $istmt->fetchAll();

$posterUrl = !empty($order['poster_url']) ? htmlspecialchars($order['poster_url']) : 'https://images.unsplash.com/photo-1540039155732-68b2dbceaebd?q=80&w=600&auto=format&fit=crop';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - TixNow</title>
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root {
            --bg: #03050a; --surface: #0f1520; --card: #131c2e;
            --border: rgba(255,255,255,0.08); --accent: #c8b5ff;
            --accent-glow: rgba(200, 181, 255, 0.15);
            --text: #e8e8f0; --muted: #5e6a82; --success: #34d399;
        }

        body { font-family: 'Inter', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; padding: 40px 20px; }
        .wrapper { max-width: 1000px; margin: 0 auto; display: grid; grid-template-columns: 1fr 340px; gap: 30px; }
        .top-nav { max-width: 1000px; margin: 0 auto 20px; }
        .btn-back-nav {
            display: inline-flex; align-items: center; gap: 8px;
            color: var(--muted); text-decoration: none; font-size: 0.85rem;
            padding: 8px 14px; border-radius: 8px;
            border: 1px solid var(--border); transition: all 0.2s;
        }
        .btn-back-nav:hover { color: white; border-color: rgba(255,255,255,0.2); background: rgba(255,255,255,0.05); }

        .header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 30px; grid-column: 1 / -1; border-bottom: 1px solid var(--border); padding-bottom: 20px; }
        .h-title { font-family: 'Syne', sans-serif; font-size: 1.5rem; font-weight: 700; }
        .h-timer { background: rgba(255,50,50,0.1); color: #ff6b6b; padding: 6px 12px; border-radius: 8px; font-weight: 600; font-size: 0.85rem; border: 1px solid rgba(255,50,50,0.2); }

        .pane { background: var(--surface); border: 1px solid var(--border); border-radius: 20px; padding: 24px; position: relative; overflow: hidden; }
        .pane-title { font-family: 'Syne', sans-serif; font-size: 1.1rem; font-weight: 700; margin-bottom: 20px; color: white; display:flex; align-items:center; gap:8px; }
        
        /* Order Info */
        .order-info { display: flex; gap: 16px; margin-bottom: 24px; padding-bottom: 24px; border-bottom: 1px solid var(--border); }
        .order-img { width: 100px; height: 100px; border-radius: 12px; object-fit: cover; }
        .order-details h3 { font-family: 'Syne', sans-serif; margin-bottom: 4px; }
        .order-meta { font-size: 0.8rem; color: var(--muted); margin-bottom: 2px; }

        .ticket-list { display: flex; flex-direction: column; gap: 12px; }
        .ticket-item { display: flex; justify-content: space-between; background: rgba(255,255,255,0.02); padding: 12px 16px; border-radius: 10px; border: 1px solid var(--border); }
        .t-name { font-weight: 600; font-size: 0.9rem; }
        .t-qty { color: var(--muted); font-size: 0.8rem; }
        .t-price { font-weight: 600; color: var(--accent); }

        /* Payment Methods */
        .pm-group { margin-bottom: 24px; }
        .pm-label { font-size: 0.85rem; color: var(--muted); margin-bottom: 10px; text-transform: uppercase; letter-spacing: 0.05em; font-weight: 600; }
        .pm-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        
        .pm-opt { position: relative; cursor: pointer; }
        .pm-opt input { position: absolute; opacity: 0; cursor: pointer; }
        .pm-box {
            display: flex; align-items: center; gap: 12px; padding: 16px;
            background: rgba(255,255,255,0.03); border: 1px solid var(--border);
            border-radius: 12px; transition: all 0.2s;
        }
        .pm-box:hover { background: rgba(255,255,255,0.06); }
        .pm-opt input:checked ~ .pm-box { background: var(--accent-glow); border-color: var(--accent); }
        .pm-opt input:checked ~ .pm-box .pm-indicator { background: var(--accent); border-color: var(--accent); }
        
        .pm-icon { width: 32px; height: 32px; object-fit: contain; }
        .pm-name { font-weight: 500; font-size: 0.9rem; flex: 1; }
        .pm-indicator { width: 18px; height: 18px; border-radius: 50%; border: 2px solid var(--muted); transition: 0.2s; }

        /* Summary */
        .sum-row { display: flex; justify-content: space-between; margin-bottom: 12px; font-size: 0.85rem; color: var(--muted); }
        .sum-row.discount { color: #f87171; font-weight: 600; }
        .sum-row.total { border-top: 1px solid var(--border); padding-top: 20px; margin-top: 20px; font-weight: 800; color: white; font-size: 1.1rem; align-items: flex-start; }
        .total-price-col { display: flex; flex-direction: column; align-items: flex-end; gap: 4px; }
        .old-price { text-decoration: line-through; font-size: 0.8rem; color: var(--muted); font-weight: 400; opacity: 0.7; }
        .new-price { color: var(--accent); font-size: 1.3rem; font-family: 'Syne', sans-serif; font-weight: 800; line-height: 1; }
        
        /* Voucher Input */
        .voucher-box { margin-top: 24px; padding-top: 24px; border-top: 1px dashed var(--border); }
        .v-input-group { display: flex; gap: 8px; margin-top: 8px; }
        .v-input { flex: 1; background: rgba(255,255,255,0.03); border: 1px solid var(--border); padding: 10px 14px; border-radius: 8px; color: white; font-size: 0.85rem; font-family: inherit; }
        .v-input:focus { border-color: var(--accent); outline: none; }
        .v-btn { background: var(--border); color: var(--text); padding: 0 16px; border-radius: 8px; border: none; font-size: 0.8rem; font-weight: 600; cursor: pointer; transition: 0.2s; }
        .v-btn:hover { background: rgba(255,255,255,0.1); }
        .v-applied { background: var(--accent-glow); border: 1px solid var(--accent); padding: 12px 16px; border-radius: 12px; display: none; align-items: center; justify-content: space-between; gap: 12px; margin-top: 14px; position: relative; overflow: hidden; }
        .v-applied::before { content: ''; position: absolute; left: 0; top: 0; bottom: 0; width: 4px; background: var(--accent); }
        .v-applied span { font-size: 0.82rem; color: var(--accent); font-weight: 600; line-height: 1.4; }
        .v-remove { cursor: pointer; color: var(--muted); padding: 4px; border-radius: 6px; transition: 0.2s; display: flex; }
        .v-remove:hover { color: #f87171; background: rgba(248,113,113,0.1); }

        .btn { width: 100%; border: none; padding: 14px; border-radius: 10px; font-weight: 600; font-family: 'Inter', sans-serif; cursor: pointer; transition: 0.2s; display: flex; justify-content: center; align-items: center; gap: 8px; margin-bottom: 12px; }
        .btn-primary { background: white; color: black; }
        .btn-primary:hover { background: var(--accent); }
        .btn-sec { background: rgba(255,255,255,0.05); color: white; border: 1px solid var(--border); }
        .btn-sec:hover { background: rgba(255,255,255,0.1); }

        @media (max-width: 768px) {
            .wrapper { grid-template-columns: 1fr; }
            .pm-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<div class="top-nav">
    <a href="user_dashboard" class="btn-back-nav">
        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
        Kembali ke Dashboard
    </a>
</div>

<div class="wrapper">
    <div class="header">
        <div class="h-title"><?php echo $isPelunasan ? 'Pelunasan Tiket' : 'Checkout Tiket'; ?></div>
        <?php if (!$isPelunasan): ?>
            <div class="h-timer" id="timer">Kadaluarsa dlm --:--</div>
        <?php endif; ?>
    </div>

    <!-- LEFT PANE: Payment Method -->
    <div class="pane">
        <div class="pane-title">Pilih Metode Pembayaran</div>
        
        <div class="pm-group">
            <div class="pm-label">Virtual Account Bank</div>
            <div class="pm-grid">
                <label class="pm-opt">
                    <input type="radio" name="payment_method" value="bca" onclick="selectMethod('bca')">
                    <div class="pm-box">
                        <div style="font-weight:800; color:#005aaa; font-style:italic;">BCA</div>
                        <div class="pm-name">BCA Virtual Account</div>
                        <div class="pm-indicator"></div>
                    </div>
                </label>
                <label class="pm-opt">
                    <input type="radio" name="payment_method" value="mandiri" onclick="selectMethod('mandiri')">
                    <div class="pm-box">
                        <div style="font-weight:800; color:#ffb700;">M</div>
                        <div class="pm-name">Mandiri VA</div>
                        <div class="pm-indicator"></div>
                    </div>
                </label>
                <label class="pm-opt">
                    <input type="radio" name="payment_method" value="bni" onclick="selectMethod('bni')">
                    <div class="pm-box">
                        <div style="font-weight:800; color:#f06400;">BNI</div>
                        <div class="pm-name">BNI VA</div>
                        <div class="pm-indicator"></div>
                    </div>
                </label>
            </div>
        </div>

        <div class="pm-group">
            <div class="pm-label">E-Wallet</div>
            <div class="pm-grid">
                <label class="pm-opt">
                    <input type="radio" name="payment_method" value="gopay" onclick="selectMethod('gopay')">
                    <div class="pm-box">
                        <div style="font-weight:800; color:#00a5cf;">G</div>
                        <div class="pm-name">GoPay</div>
                        <div class="pm-indicator"></div>
                    </div>
                </label>
                <label class="pm-opt">
                    <input type="radio" name="payment_method" value="ovo" onclick="selectMethod('ovo')">
                    <div class="pm-box">
                        <div style="font-weight:800; color:#4c3494;">OVO</div>
                        <div class="pm-name">OVO</div>
                        <div class="pm-indicator"></div>
                    </div>
                </label>
            </div>
        </div>
        
        <div style="display:flex; flex-direction:column; gap:12px; margin-top:40px;">
            <?php if ($isPelunasan): ?>
                <button type="button" class="btn btn-primary" style="padding: 18px; font-size: 1.1rem; background: #818cf8; color: white; box-shadow: 0 10px 20px rgba(129,140,248,0.2);" onclick="submitForm('pay_now')">Lunasi Pembayaran Sekarang</button>
            <?php else: ?>
                <button type="button" class="btn btn-primary" style="padding: 18px; font-size: 1.1rem; box-shadow: 0 10px 20px rgba(255,255,255,0.1);" onclick="submitForm('pay_now')">Lanjut Pembayaran (Lunas)</button>
                <button type="button" class="btn btn-sec" onclick="submitForm('pay_dp')">Bayar DP 30% Terlebih Dahulu</button>
            <?php endif; ?>
        </div>
    </div>

    <!-- RIGHT PANE: Order Summary -->
    <div>
        <div class="pane">
            <div class="pane-title">Ringkasan Pesanan</div>
            <div class="order-info">
                <img src="<?php echo $posterUrl; ?>" class="order-img">
                <div class="order-details">
                    <h3><?php echo htmlspecialchars($order['nama_event']); ?></h3>
                    <div class="order-meta"><?php echo date('d M Y, H:i', strtotime($order['tanggal'])); ?></div>
                    <div class="order-meta"><?php echo htmlspecialchars($order['venue']); ?></div>
                </div>
            </div>

            <div class="ticket-list">
                <?php foreach ($items as $it): ?>
                <div class="ticket-item">
                    <div>
                        <div class="t-name"><?php echo htmlspecialchars($it['nama_tiket']); ?></div>
                        <div class="t-qty"><?php echo $it['qty']; ?> Tiket</div>
                    </div>
                    <div class="t-price">Rp <?php echo number_format($it['subtotal'], 0, ',', '.'); ?></div>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="voucher-box">
                <div class="pm-label">Mempunyai Voucher?</div>
                <div class="v-input-group" id="voucherInputGroup">
                    <input type="text" id="voucherCode" class="v-input" placeholder="Masukkan kode promo">
                    <button type="button" class="v-btn" onclick="applyVoucher()">Terapkan</button>
                </div>
                <div class="v-applied" id="voucherApplied">
                    <span><svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="vertical-align:middle;margin-right:4px;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg> Voucher <strong id="appliedCodeText"></strong> Berhasil Dipasang</span>
                    <div class="v-remove" onclick="removeVoucher()">
                        <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </div>
                </div>
            </div>

            <div style="margin-top:24px;">
                <div class="sum-row">
                    <span>Subtotal Harga</span>
                    <span>Rp <?php echo number_format($order['total'], 0, ',', '.'); ?></span>
                </div>
                <?php if ($isPelunasan): ?>
                <div class="sum-row" style="color:#10b981; font-weight:600;">
                    <span>DP Terbayar (30%)</span>
                    <span>- Rp <?php echo number_format($order['amount_paid'], 0, ',', '.'); ?></span>
                </div>
                <?php endif; ?>
                <div id="discountRow" class="sum-row discount" style="display:none">
                    <span>Diskon Voucher</span>
                    <span id="discountVal">- Rp 0</span>
                </div>
                <div class="sum-row total">
                    <span style="margin-top: 4px;">Total Tagihan <?php echo $isPelunasan ? '(Sisa)' : ''; ?></span>
                    <div class="total-price-col">
                        <span id="oldTotal" class="old-price" style="display:none">Rp <?php echo number_format($order['total'], 0, ',', '.'); ?></span>
                        <span id="finalTotal" class="new-price">Rp <?php echo number_format($isPelunasan ? ($order['total'] - $order['amount_paid']) : $order['total'], 0, ',', '.'); ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<form id="paymentForm" method="POST" action="payment_gateway">
    <input type="hidden" name="order_code" value="<?php echo htmlspecialchars($id_order); ?>">
    <input type="hidden" name="payment_method" id="selectedMethod">
    <input type="hidden" name="action_type" id="hiddenActionType">
    <input type="hidden" name="voucher_code" id="appliedVoucherInput">
</form>

<script>
    const baseTotal = <?php echo $order['total']; ?>;

    function selectMethod(method) {
        document.getElementById('selectedMethod').value = method;
    }

    function applyVoucher() {
        const code = document.getElementById('voucherCode').value;
        if(!code) return alert('Masukkan kode voucher!');

        fetch('apply_voucher', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `code=${encodeURIComponent(code)}&order_code=<?php echo $id_order; ?>`
        })
        .then(res => res.json())
        .then(data => {
            if(data.success) {
                document.getElementById('voucherInputGroup').style.display = 'none';
                document.getElementById('voucherApplied').style.display = 'flex';
                document.getElementById('appliedCodeText').innerText = data.code;
                document.getElementById('appliedVoucherInput').value = data.code;
                
                document.getElementById('discountRow').style.display = 'flex';
                document.getElementById('discountVal').innerText = '- Rp ' + data.discount.toLocaleString('id-ID');
                
                document.getElementById('oldTotal').style.display = 'inline';
                document.getElementById('finalTotal').innerText = 'Rp ' + data.newTotal.toLocaleString('id-ID');
            } else {
                alert(data.message);
            }
        });
    }

    function removeVoucher() {
        document.getElementById('voucherInputGroup').style.display = 'flex';
        document.getElementById('voucherApplied').style.display = 'none';
        document.getElementById('appliedVoucherInput').value = '';
        document.getElementById('voucherCode').value = '';
        
        document.getElementById('discountRow').style.display = 'none';
        document.getElementById('oldTotal').style.display = 'none';
        document.getElementById('finalTotal').innerText = 'Rp ' + baseTotal.toLocaleString('id-ID');
    }

    function submitForm(type) {
        const method = document.getElementById('selectedMethod').value;
        if(!method) return alert('Pilih metode pembayaran!');
        
        document.getElementById('hiddenActionType').value = type;
        document.getElementById('paymentForm').submit();
    }

    <?php if (!$isPelunasan): ?>
    const expDate = new Date("<?php echo date('c', strtotime($order['tanggal_kadaluarsa'])); ?>").getTime();
    const x = setInterval(function() {
        const now = new Date().getTime();
        const distance = expDate - now;
        if (distance < 0) {
            clearInterval(x);
            document.getElementById("timer").innerHTML = "KADALUARSA";
        } else {
            const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
            const seconds = Math.floor((distance % (1000 * 60)) / 1000);
            document.getElementById("timer").innerHTML = `Kadaluarsa dlm ${minutes}m ${seconds}s`;
        }
    }, 1000);
    <?php endif; ?>
</script>
</body>
</html>
