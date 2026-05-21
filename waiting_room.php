<?php
session_start();
require_once 'koneksi.php';

if (!isset($_SESSION['id_user'])) {
    header("Location: login");
    exit;
}

$id_user = $_SESSION['id_user'];
$tiket_id = $_GET['tiket_id'] ?? 0;

if (!$tiket_id) {
    header("Location: user_dashboard");
    exit;
}

// Clear booking session seen flag if entering waiting room (fresh start or kicked back)
$id_event_temp = 0;
$stmtE = $conn->prepare("SELECT id_event FROM tiket WHERE id_tiket = ?");
$stmtE->execute([$tiket_id]);
$ev = $stmtE->fetch();
if ($ev) {
    unset($_SESSION['booking_seen_' . $ev['id_event']]);
}

// Get Ticket Category and Concert Info
$stmt = $conn->prepare("
    SELECT k.*, c.nama_event, c.poster_url 
    FROM tiket k
    JOIN event c ON k.id_event = c.id_event
    WHERE k.id_tiket = ?
");
$stmt->execute([$tiket_id]);
$category = $stmt->fetch();

if (!$category) {
    header("Location: user_dashboard");
    exit;
}

$id_event = $category['id_event'];

// Check if user is already active for this ticket
$stmt = $conn->prepare("SELECT * FROM waiting_queue WHERE id_user = ? AND id_event = ? AND status = 'active' AND expires_at > NOW()");
$stmt->execute([$id_user, $id_event]);
$active = $stmt->fetch();

if ($active) {
    header("Location: booking?tiket_id=" . $tiket_id);
    exit;
}

// Ensure schema is updated
try { $conn->exec("ALTER TABLE waiting_queue ADD COLUMN joined_at DATETIME DEFAULT CURRENT_TIMESTAMP"); } catch(Exception $e) {}

// Check if user is already in queue
$stmt = $conn->prepare("SELECT * FROM waiting_queue WHERE id_user = ? AND id_event = ? AND status = 'waiting' ORDER BY id DESC LIMIT 1");
$stmt->execute([$id_user, $id_event]);
$queue = $stmt->fetch();

if ($queue) {
    // REFRESH PENALTY: User refreshed the page! Increase queue number by 3-4 spots as requested.
    $penalty = mt_rand(3, 4);
    $up = $conn->prepare("UPDATE waiting_queue SET queue_number = queue_number + ? WHERE id = ?");
    $up->execute([$penalty, $queue['id']]);
    
    // Fetch updated
    $stmt->execute([$id_user, $id_event]);
    $queue = $stmt->fetch();
} else {
    // Join queue for the first time
    $queue_number = mt_rand(30, 80);
    $stmt = $conn->prepare("INSERT INTO waiting_queue (id_user, id_event, queue_number, status, joined_at) VALUES (?, ?, ?, 'waiting', NOW())");
    $stmt->execute([$id_user, $id_event, $queue_number]);

    // BOT SIMULATION: 10 bots buy 3-10 tickets each
    // Check if user is VIP (VIP might trigger less or no bots, or just skip)
    $isVip = $conn->query("SELECT is_vip FROM users WHERE id_user = $id_user")->fetchColumn();
    
    if (!$isVip) {
        $botStmt = $conn->query("SELECT id_user, nama, email FROM users WHERE is_bot = 1 AND id_user != 999999 ORDER BY RAND() LIMIT 10");
        $botsToUse = $botStmt->fetchAll();

        // Ambil voucher yang mungkin tersedia untuk event ini
        $vStmt = $conn->prepare("SELECT * FROM vouchers WHERE id_event = ? AND status = 'active' AND current_usage < max_usage");
        $vStmt->execute([$id_event]);
        $availableVouchers = $vStmt->fetchAll();

        $paymentMethods = ['bca', 'mandiri', 'bni', 'gopay', 'ovo'];

        foreach ($botsToUse as $bot) {
            $bName = $bot['nama'];
            $bEmail = $bot['email'];
            $bId = $bot['id_user'];
            $botQty = mt_rand(1, 4); // Bot beli 1-4 tiket
            
            // Check stock again
            $sCheck = $conn->prepare("SELECT kuota, harga FROM tiket WHERE id_tiket = ? FOR UPDATE");
            $sCheck->execute([$tiket_id]);
            $tData = $sCheck->fetch();
            
            if ($tData && $tData['kuota'] >= $botQty) {
                $orderCode = 'BOT-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid()), 0, 6));
                $originalTotal = $tData['harga'] * $botQty;
                $discount = 0;
                
                // 30% kemungkinan bot pakai voucher jika ada
                if (!empty($availableVouchers) && mt_rand(1, 100) <= 30) {
                    $v = $availableVouchers[array_rand($availableVouchers)];
                    $discount = (int)$v['discount_amount'];
                    // Update voucher usage
                    $conn->prepare("UPDATE vouchers SET current_usage = current_usage + 1 WHERE id_voucher = ?")->execute([$v['id_voucher']]);
                }

                $finalTotal = max(0, $originalTotal - $discount);
                $pMethod = $paymentMethods[array_rand($paymentMethods)];
                
                // Random Status: 70% Paid, 20% DP, 10% Cancelled
                $randStatus = mt_rand(1, 100);
                if ($randStatus <= 70) {
                    $status = 'paid';
                    $pType = 'full';
                    $amtPaid = $finalTotal;
                } elseif ($randStatus <= 90) {
                    $status = 'dp_paid';
                    $pType = 'dp';
                    $amtPaid = $finalTotal * 0.3;
                } else {
                    $status = 'cancelled';
                    $pType = 'full';
                    $amtPaid = 0;
                }

                // Insert Bot Order with complete payment data
                $conn->prepare("
                    INSERT INTO orders (
                        order_code, id_user, id_event, total, discount, amount_paid, 
                        status, payment_type, metode_pembayaran, bukti_pembayaran_url, 
                        tanggal_pembayaran, tanggal_order, tanggal_kadaluarsa
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), DATE_ADD(NOW(), INTERVAL 1 DAY))
                ")->execute([
                    $orderCode, $bId, $id_event, $finalTotal, $discount, $amtPaid,
                    $status, $pType, $pMethod, 'uploads/payments/bot_proof_dummy.jpg'
                ]);
                
                $orderId = $conn->lastInsertId();
                $conn->prepare("INSERT INTO order_detail (id_order, id_tiket, qty, harga_satuan, subtotal) VALUES (?, ?, ?, ?, ?)")
                     ->execute([$orderId, $tiket_id, $botQty, $tData['harga'], $originalTotal]);
                $detailId = $conn->lastInsertId();
                
                // Only create attendee/tickets if the bot actually paid (full or DP)
                if ($status !== 'cancelled') {
                    // Create Attendee Records for Bot
                    $tStmt = $conn->prepare("INSERT INTO attendee (id_detail, kode_tiket, status_checkin, nama_pemegang, email_pemegang, no_telepon_pemegang) VALUES (?, ?, 'active', ?, ?, ?)");
                    for ($i = 0; $i < $botQty; $i++) {
                        $kode = "TIX-BOT-" . strtoupper(substr(md5(uniqid()), 0, 6));
                        $tStmt->execute([$detailId, $kode, $bName, $bEmail, "0812" . mt_rand(10000000, 99999999)]);
                    }
                    
                    // Update Stock (only if not cancelled)
                    $conn->prepare("UPDATE tiket SET kuota = kuota - ? WHERE id_tiket = ?")->execute([$botQty, $tiket_id]);
                }
            }
        }
    }
    
    $stmt = $conn->prepare("SELECT * FROM waiting_queue WHERE id_user = ? AND id_event = ? AND status = 'waiting' ORDER BY id DESC LIMIT 1");
    $stmt->execute([$id_user, $id_event]);
    $queue = $stmt->fetch();
}

// Simulated total waiting (Real count * random multiplier + base bots)
$realWaitingCount = $conn->prepare("SELECT COUNT(*) FROM waiting_queue WHERE id_event = ? AND status = 'waiting'");
$realWaitingCount->execute([$id_event]);
$totalReal = $realWaitingCount->fetchColumn();

// We'll use the user ID and concert ID as a seed for stable simulation per user
mt_srand($id_user + $id_event); 
$baseBots = mt_rand(500, 1500);
$simulatedTotal = ($totalReal * mt_rand(10, 20)) + $baseBots;

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Antrean Tiket - <?php echo htmlspecialchars($category['nama_event']); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script>
        (function() {
            const savedTheme = localStorage.getItem('theme') || 'dark';
            document.documentElement.setAttribute('data-theme', savedTheme);
        })();
    </script>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root {
            --bg: #07090f; --surface: #0d1117; --card: #0f1521;
            --accent: #a78bfa; --accent-glow: rgba(167,139,250,0.15);
            --text: #e2e8f0; --muted: #4b5a72; --border: rgba(255,255,255,0.06);
        }

        [data-theme="light"] {
            --bg: #f8fafc; --surface: #ffffff; --card: #f1f5f9;
            --accent: #7c3aed; --accent-glow: rgba(124, 58, 237, 0.08);
            --text: #0f172a; --muted: #475569; --border: rgba(0,0,0,0.08);
        }

        body { font-family: 'Inter', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; display: flex; align-items: center; justify-content: center; overflow: hidden; }

        .queue-box {
            width: 100%; max-width: 550px; background: var(--surface); border: 1px solid var(--border);
            border-radius: 32px; padding: 50px 40px; text-align: center; position: relative;
            box-shadow: 0 40px 100px rgba(0,0,0,0.4);
        }

        .category-badge {
            display: inline-block; padding: 6px 16px; background: var(--accent-glow); color: var(--accent);
            border-radius: 99px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase;
            letter-spacing: 0.1em; margin-bottom: 24px;
        }

        h1 { font-family: 'Syne', sans-serif; font-size: 1.8rem; font-weight: 800; margin-bottom: 12px; }
        .concert-name { color: var(--muted); margin-bottom: 40px; font-size: 0.95rem; }

        /* PROGRESS AREA */
        .progress-container { margin-bottom: 40px; position: relative; padding: 20px 0; }
        
        .track { height: 6px; background: rgba(255,255,255,0.05); border-radius: 10px; width: 100%; position: relative; overflow: hidden; }
        .fill { position: absolute; left: 0; top: 0; bottom: 0; width: 0%; background: var(--accent); border-radius: 10px; transition: width 1s ease; }

        /* THE PERSON (AVATAR) */
        .walking-man {
            position: absolute; left: 0%; bottom: 20px; width: 32px; height: 32px;
            transform: translateX(-50%); transition: left 1s ease;
            z-index: 10;
        }
        .walking-man svg { width: 100%; height: 100%; filter: drop-shadow(0 0 10px var(--accent)); color: var(--accent); }

        /* BOT SIMULATION LIST */
        .bots-wrap { 
            position: relative; height: 40px; margin-bottom: 20px; overflow: hidden;
            mask-image: linear-gradient(to right, transparent, black 20%, black 80%, transparent);
        }
        .bots-track { display: flex; gap: 20px; position: absolute; left: 0; animation: moveBots 2s linear infinite; }
        .bot { width: 24px; height: 24px; opacity: 0.2; color: var(--text); }

        @keyframes moveBots { from { transform: translateX(0); } to { transform: translateX(-44px); } }

        /* STATS */
        .stats-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 20px; }
        .stat-item { padding: 20px; background: rgba(255,255,255,0.02); border: 1px solid var(--border); border-radius: 20px; }
        .stat-label { font-size: 0.7rem; color: var(--muted); text-transform: uppercase; letter-spacing: 0.1em; margin-bottom: 8px; }
        .stat-val { font-family: 'Syne', sans-serif; font-size: 1.5rem; font-weight: 700; }

        .warning { font-size: 0.8rem; color: var(--muted); margin-top: 30px; line-height: 1.6; }
        .warning strong { color: var(--text); }
        
        /* BEHIND BACKGROUND */
        .bg-glow { position: absolute; width: 400px; height: 400px; background: var(--accent); filter: blur(150px); opacity: 0.05; border-radius: 50%; z-index: -1; top: 10%; left: 50%; transform: translateX(-50%); }

    </style>
</head>
<body>

<div class="bg-glow"></div>

<div class="queue-box">
    <div class="category-badge">Ticket War Active</div>
    <h1>Ruang Tunggu Antrean</h1>
    <p class="concert-name"><?php echo htmlspecialchars($category['nama_event']); ?> - <?php echo htmlspecialchars($category['nama_tiket']); ?></p>

    <?php if (isset($_GET['msg']) && $_GET['msg'] === 'refreshed'): ?>
    <div style="background: rgba(248,113,113,0.1); border: 1px solid rgba(248,113,113,0.2); color: #f87171; padding: 10px 15px; border-radius: 12px; font-size: 0.8rem; margin: 15px 0; line-height: 1.4; display: flex; align-items: center; gap: 10px; text-align: left;">
        <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
        <span><strong>Deteksi Refresh!</strong> Anda dikembalikan ke antrean karena melakukan refresh di halaman pemesanan.</span>
    </div>
    <?php endif; ?>

    <!-- Visual Simulation of others -->
    <div class="bots-wrap">
        <div class="bots-track">
            <?php for($i=0; $i<20; $i++): ?>
            <div class="bot">
                <svg fill="currentColor" viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
            </div>
            <?php endfor; ?>
        </div>
    </div>

    <div class="progress-container">
        <div class="walking-man" id="walkingMan">
            <svg fill="currentColor" viewBox="0 0 24 24"><path d="M13.5 1.5c1.1 0 2 .9 2 2s-.9 2-2 2-2-.9-2-2 .9-2 2-2zM9.8 8.4l.2.3c.1.2.3.4.5.5l1.1.8c.6.4.8 1.2.4 1.8l-1.5 2.5c-.3.5-.1 1.2.4 1.5.5.3 1.2.1 1.5-.4l1.4-2.3c.4-.7 1.3-1 2-1h.2c.8 0 1.5.7 1.5 1.5v3.1c0 .5.4.9.9.9s.9-.4.9-.9v-3.4c0-2.3-1.8-4.1-4.1-4.1h-1.4l-1.3-.9c-.7-.5-1.7-.5-2.4 0l-1.5 1.1c-.4.3-.4.8-.1 1.1.2.4.6.4 1 .2l1.5-1.1s.1-.1.2-.1zM11 22.5c0 .6-.5 1-1 1s-1-.5-1-1v-4.6c0-1.1.6-2.1 1.5-2.5l.4-.2c.5-.2 1.1 0 1.2.5s0 1.1-.5 1.2l-.4.2c-.1.1-.2.4-.2.9v4.5zm4.8.4c.1.5.6.8 1.1.7.5-.1.8-.6.7-1.1l-.8-4.5c-.1-.7-.6-1.3-1.3-1.5l-.2-.1c-.5-.1-.8.2-.9.7s.2.8.7.9l.2.1c.1 0 .2.2.2.4l.3 4.4z"/></svg>
        </div>
        <div class="track">
            <div class="fill" id="progressFill"></div>
        </div>
    </div>

    <div class="stats-grid">
        <div class="stat-item">
            <div class="stat-label">Antrean Anda</div>
            <div class="stat-val" id="peopleAhead">...</div>
        </div>
        <div class="stat-item">
            <div class="stat-label">Estimasi Waktu Tunggu</div>
            <div class="stat-val" id="waitTime">...</div>
        </div>
    </div>

    <p class="warning">Mohon <strong>jangan tutup atau refresh</strong> halaman ini. Anda akan otomatis dialihkan ke halaman pemesanan setelah giliran Anda tiba.</p>
</div>

<script>
    function updateQueue() {
        const tiketId = "<?php echo $tiket_id; ?>";
        const konserId = "<?php echo $id_event; ?>";

        fetch(`queue_api?id_event=${konserId}&tiket_id=${tiketId}`)
            .then(res => res.json())
            .then(data => {
                if (data.status === 'active') {
                    window.location.href = `booking?tiket_id=${tiketId}`;
                    return;
                }

                const currentSimulatedPosition = data.position_ahead;
                const initialQueueNum = data.queue_number;
                
                // Update UI
                const aheadEl = document.getElementById('peopleAhead');
                aheadEl.innerText = currentSimulatedPosition.toLocaleString();

                const waitEl = document.getElementById('waitTime');
                
                // Detailed Time Estimation (Minutes & Seconds)
                // Assuming 1.2 person per second processing rate
                const totalSeconds = Math.ceil(currentSimulatedPosition / 1.2);
                const m = Math.floor(totalSeconds / 60);
                const s = totalSeconds % 60;
                
                let timeStr = "";
                if (currentSimulatedPosition > 0) {
                    if (currentSimulatedPosition < 10) {
                        timeStr = "Hampir Tiba!";
                    } else {
                        timeStr = (m > 0 ? `${m}m ` : "") + `${s}d`;
                    }
                } else {
                    timeStr = "Menuju Halaman...";
                }
                waitEl.innerText = timeStr;

                // Update Progress
                let progress = 100 - (currentSimulatedPosition / initialQueueNum * 100);
                if (progress < 5) progress = 5;
                if (progress > 98) progress = 98;
                
                document.getElementById('progressFill').style.width = progress + '%';
                document.getElementById('walkingMan').style.left = progress + '%';
            });
    }

    // Polling setiap 3 detik
    updateQueue();
    setInterval(updateQueue, 3000);
</script>

</body>
</html>
