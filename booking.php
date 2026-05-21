<?php
session_start();
require_once 'koneksi.php';

if (!isset($_SESSION['id_user'])) {
    header("Location: index");
    exit;
}

if (!isset($_GET['tiket_id']) || !is_numeric($_GET['tiket_id'])) {
    header("Location: user_dashboard");
    exit;
}

$tiket_id = $_GET['tiket_id'];

// Check VIP status
$stmtVip = $conn->prepare("SELECT is_vip FROM users WHERE id_user = ?");
$stmtVip->execute([$_SESSION['id_user']]);
$isVipUser = (int)($stmtVip->fetchColumn() ?? 0);

// Get fast track fee (only applies if VIP)
$fastTrackFee = 0;
if ($isVipUser) {
    try {
        $conn->exec("INSERT IGNORE INTO settings (setting_key, setting_value) VALUES ('fast_track_fee', '250000')");
    } catch(Exception $e) {}
    $fastTrackFee = (int)($conn->query("SELECT setting_value FROM settings WHERE setting_key = 'fast_track_fee'")->fetchColumn() ?? 0);
}

// Get Kategori & Konser Detail
$stmt = $conn->prepare("
    SELECT k.*, c.nama_event, c.artis, c.venue, c.tanggal, c.poster_url 
    FROM tiket k
    JOIN event c ON k.id_event = c.id_event
    WHERE k.id_tiket = ?
");
$stmt->execute([$tiket_id]);
$data = $stmt->fetch();
$id_event = $data['id_event'] ?? 0;

// Security: Check if user has passed the Waiting Queue
$stmtQ = $conn->prepare("SELECT id FROM waiting_queue WHERE id_user = ? AND id_event = ? AND status = 'active' AND expires_at > NOW()");
$stmtQ->execute([$_SESSION['id_user'], $data['id_event']]);
$qRow = $stmtQ->fetch();
if (!$qRow) {
    header("Location: waiting_room?tiket_id=" . $tiket_id);
    exit;
}

// ANTI-REFRESH KICK: If non-VIP refreshes, they lose their spot!
if (!$isVipUser) {
    if (isset($_SESSION['booking_seen_' . $id_event])) {
        // Double load/refresh detected -> Expire queue and send back
        $conn->prepare("UPDATE waiting_queue SET status = 'expired' WHERE id = ?")->execute([$qRow['id']]);
        unset($_SESSION['booking_seen_' . $id_event]);
        header("Location: waiting_room?tiket_id=" . $tiket_id . "&msg=refreshed");
        exit;
    }
    $_SESSION['booking_seen_' . $id_event] = true;
}

// Store the order permission in session temporarily for order_action.php
$_SESSION['can_book_' . $data['id_event']] = time();

if (!$data || $data['kuota'] <= 0) {
    echo "<script>alert('Tiket tidak tersedia atau habis.'); window.location.href='user_dashboard';</script>";
    exit;
}

$posterUrl = !empty($data['poster_url']) ? htmlspecialchars($data['poster_url']) : 'https://images.unsplash.com/photo-1540039155732-68b2dbceaebd?q=80&w=600&auto=format&fit=crop';
$maxQty = $data['kuota'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pesan Tiket - TixNow</title>
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root {
            --bg: #03050a; --surface: #0f1520; --card: #131c2e;
            --border: rgba(255,255,255,0.08); --accent: #c8b5ff;
            --text: #e8e8f0; --muted: #5e6a82;
            --cat-color: <?php echo !empty($data['warna_kategori']) ? $data['warna_kategori'] : 'var(--border)'; ?>;
        }

        body { font-family: 'Inter', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 40px 20px; }
        
        .booking-container {
            width: 100%; max-width: 900px;
            background: var(--surface); border: 1px solid var(--border); border-radius: 24px; padding: 40px;
            display: grid; grid-template-columns: 1fr 1.2fr; gap: 40px;
            box-shadow: 0 40px 80px rgba(0,0,0,0.5);
            position: relative; overflow: hidden;
        }

        .booking-container::before {
            content: ''; position: absolute; top: -100px; left: -100px; width: 300px; height: 300px;
            background: var(--cat-color); filter: blur(120px); opacity: 0.15; z-index: 0; pointer-events: none;
        }

        .b-left { z-index: 1; display:flex; flex-direction:column; }
        .b-right { z-index: 1; display:flex; flex-direction:column; justify-content:center; }

        .poster { width: 100%; max-width: 250px; border-radius: 16px; border: 1px solid rgba(255,255,255,0.1); box-shadow: 0 20px 40px rgba(0,0,0,0.4); margin-bottom: 24px; }
        .k-title { font-family: 'Syne', sans-serif; font-size: 1.8rem; font-weight: 700; line-height: 1.2; margin-bottom: 8px; }
        .k-artist { color: var(--muted); font-size: 0.95rem; margin-bottom: 16px; }
        .k-meta { display: flex; flex-direction: column; gap: 6px; font-size: 0.85rem; color: var(--text); opacity: 0.8; }
        .k-meta span { display: flex; align-items: center; gap: 8px; }

        .cat-box { border: 1px solid var(--cat-color); background: linear-gradient(135deg, rgba(255,255,255,0.03), transparent); border-radius: 16px; padding: 24px; margin-bottom: 30px; position: relative; overflow: hidden; }
        .cat-box::after { content: ''; position: absolute; right: 0; top: 0; bottom: 0; width: 6px; background: var(--cat-color); }
        .cat-name { font-family: 'Syne', sans-serif; font-size: 1.3rem; font-weight: 700; margin-bottom: 4px; }
        .cat-desc { font-size: 0.85rem; color: var(--muted); margin-bottom: 16px; }
        .cat-price { font-size: 1.6rem; font-weight: 700; color: var(--accent); }

        .qty-control { display: flex; align-items: center; justify-content: space-between; background: rgba(0,0,0,0.2); border: 1px solid var(--border); padding: 12px 20px; border-radius: 12px; margin-bottom: 30px; }
        .qty-label { font-weight: 500; }
        .qty-actions { display: flex; align-items: center; gap: 16px; }
        .btn-qty { width: 36px; height: 36px; border-radius: 50%; background: var(--card); border: 1px solid var(--border); color: white; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: 0.2s; font-size: 1.1rem; }
        .btn-qty:hover { background: rgba(255,255,255,0.1); border-color: rgba(255,255,255,0.2); }
        .qty-val { font-family: 'Syne', sans-serif; font-size: 1.2rem; font-weight: 700; width: 30px; text-align: center; }

        .tot-box { margin-bottom: 30px; padding-top: 20px; border-top: 1px dashed var(--border); }
        .tot-row { display: flex; justify-content: space-between; align-items: center; font-size: 0.85rem; margin-bottom: 8px; }
        .tot-row .lbl { color: var(--muted); }
        .tot-row .val { font-weight: 600; }
        .tot-row.vip-row .lbl { color: #ffd700; }
        .tot-row.vip-row .val { color: #ffd700; }
        .tot-divider { height: 1px; background: var(--border); margin: 10px 0; }
        .tot-final { display: flex; justify-content: space-between; align-items: center; margin-top: 4px; }
        .tot-final .tot-label { color: var(--muted); font-size: 0.9rem; }
        .tot-final .tot-amount { font-family: 'Syne', sans-serif; font-size: 1.8rem; font-weight: 700; color: white; }

        .btn-checkout { width: 100%; background: white; color: black; border: none; padding: 16px; border-radius: 12px; font-weight: 700; font-size: 1rem; cursor: pointer; transition: 0.3s; font-family: 'Inter', sans-serif; display: flex; justify-content: center; align-items: center; gap: 10px; }
        .btn-checkout:hover { background: var(--accent); transform: translateY(-2px); }
        
        .btn-back { display: inline-flex; align-items: center; gap: 8px; color: var(--muted); text-decoration: none; font-size: 0.85rem; margin-top: 20px; transition: 0.2s; }
        .btn-back:hover { color: white; }

        @media (max-width: 768px) {
            .booking-container { grid-template-columns: 1fr; padding: 24px; }
            .poster { max-width: 150px; }
        }
    </style>
</head>
<body>

<div class="booking-container">
    <div class="b-left">
        <img src="<?php echo $posterUrl; ?>" class="poster" alt="">
        <h1 class="k-title"><?php echo htmlspecialchars($data['nama_event']); ?></h1>
        <p class="k-artist"><?php echo htmlspecialchars($data['artis']); ?></p>
        <div class="k-meta">
            <span>
                <svg width="16" height="16" fill="none" stroke="var(--accent)" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                <?php echo date('d M Y, H:i', strtotime($data['tanggal'])); ?> WIB
            </span>
            <span>
                <svg width="16" height="16" fill="none" stroke="var(--accent)" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                <?php echo htmlspecialchars($data['venue']); ?>
            </span>
        </div>
        <a href="detail_konser?id=<?php echo $data['id_event']; ?>" class="btn-back">
            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            Kembali ke Detail
        </a>
    </div>

    <div class="b-right">
        <div class="cat-box">
            <div class="cat-name"><?php echo htmlspecialchars($data['nama_tiket']); ?></div>
            <div class="cat-desc"><?php echo htmlspecialchars($data['deskripsi']); ?></div>
            <div class="cat-price">Rp <?php echo number_format($data['harga'], 0, ',', '.'); ?></div>
            <div style="margin-top: 15px; font-size: 0.8rem; display: flex; align-items: center; gap: 6px;">
                <span style="background: var(--accent); color: black; padding: 2px 8px; border-radius: 4px; font-weight: 700; font-size: 0.7rem;">SISA STOK</span>
                <span style="color: var(--text); font-weight: 600;"><?php echo number_format($data['kuota'], 0, ',', '.'); ?> Tiket</span>
            </div>
        </div>

        <form action="order_action" method="POST" id="bkForm">
            <input type="hidden" name="tiket_id" value="<?php echo $tiket_id; ?>">
            <input type="hidden" name="qty" id="qtyInput" value="1">
            <input type="hidden" name="fast_track_fee" value="<?php echo $fastTrackFee; ?>">
            

            <?php if ($isVipUser && $fastTrackFee > 0): ?>
            <div style="background: rgba(255,215,0,0.07); border: 1px solid rgba(255,215,0,0.25); border-radius: 12px; padding: 12px 16px; margin-bottom: 16px; display: flex; align-items: center; gap: 10px;">
                <svg width="16" height="16" viewBox="0 0 20 20" fill="#ffd700"><path d="M10 1l2.39 5.26L18 7.27l-4 3.89.94 5.5L10 14l-4.94 2.66.94-5.5L2 7.27l5.61-.01L10 1z"/></svg>
                <div style="font-size: 0.8rem; color: #ffd700; font-weight: 600;">VIP Fast Track aktif — Biaya tambahan flat <strong>Rp <?php echo number_format($fastTrackFee, 0, ',', '.'); ?></strong> per pembelian</div>
            </div>
            <?php endif; ?>
            
            <div class="qty-control">
                <div class="qty-label">Jumlah Tiket</div>
                <div class="qty-actions">
                    <button type="button" class="btn-qty" onclick="changeQty(-1)">-</button>
                    <div class="qty-val" id="qtyText">1</div>
                    <button type="button" class="btn-qty" onclick="changeQty(1)">+</button>
                </div>
            </div>

            <div class="tot-box">
                <?php if ($isVipUser && $fastTrackFee > 0): ?>
                <div class="tot-row">
                    <span class="lbl">Harga Tiket (<span id="qtyLabel">1</span>×)</span>
                    <span class="val" id="baseTotal">Rp <?php echo number_format($data['harga'], 0, ',', '.'); ?></span>
                </div>
                <div class="tot-row vip-row">
                    <span class="lbl">⭐ Fast Track Fee (Flat)</span>
                    <span class="val" id="feeTotal">Rp <?php echo number_format($fastTrackFee, 0, ',', '.'); ?></span>
                </div>
                <div class="tot-divider"></div>
                <?php endif; ?>
                <div class="tot-final">
                    <div class="tot-label">Total Pembayaran</div>
                    <div class="tot-amount" id="totAmount">Rp <?php echo number_format($data['harga'] + $fastTrackFee, 0, ',', '.'); ?></div>
                </div>
            </div>

            <button type="submit" class="btn-checkout">
                Lanjut Pembayaran
                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
            </button>
        </form>
    </div>
</div>

<script>
    const price        = <?php echo $data['harga']; ?>;
    const fastTrackFee = <?php echo $fastTrackFee; ?>;
    const max          = <?php echo $maxQty; ?>;
    let qty = 1;

    function changeQty(d) {
        let nq = qty + d;
        if (nq >= 1 && nq <= max) {
            qty = nq;
            document.getElementById('qtyText').innerText = qty;
            document.getElementById('qtyInput').value    = qty;

            const fmt = v => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(v).replace('IDR', 'Rp');

            const base  = qty * price;
            const fee   = fastTrackFee; // Flat fee per purchase
            const total = base + fee;

            document.getElementById('totAmount').innerText = fmt(total);

            const baseEl = document.getElementById('baseTotal');
            const feeEl  = document.getElementById('feeTotal');
            const qtyLbl = document.getElementById('qtyLabel');
            if (baseEl) baseEl.innerText = fmt(base);
            if (feeEl)  feeEl.innerText  = fmt(fee);
            if (qtyLbl) qtyLbl.innerText = qty;
        } else if (nq > max) {
            alert('Jumlah pembelian melebihi stok yang tersedia (' + max + ' tiket).');
        }
    }
</script>
</body>
</html>
