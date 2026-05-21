<?php
session_start();
require_once 'koneksi.php';

header('Content-Type: application/json');

if (!isset($_SESSION['id_user'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$id_user = $_SESSION['id_user'];
$id_event = $_GET['id_event'] ?? 0;
$tiket_id = $_GET['tiket_id'] ?? 0;

if (!$id_event) {
    echo json_encode(['error' => 'Invalid Request']);
    exit;
}

// 1. Cleanup expired active sessions
$conn->prepare("UPDATE waiting_queue SET status = 'expired' WHERE status = 'active' AND expires_at < NOW()")->execute();

// 2. Refresh current user's status
$stmt = $conn->prepare("SELECT * FROM waiting_queue WHERE id_user = ? AND id_event = ? AND status != 'expired' ORDER BY id DESC LIMIT 1");
$stmt->execute([$id_user, $id_event]);
$queue = $stmt->fetch();

if (!$queue) {
    echo json_encode(['status' => 'not_queued']);
    exit;
}

if ($queue['status'] === 'active') {
    echo json_encode(['status' => 'active', 'expires_at' => $queue['expires_at']]);
    exit;
}

// 3. Check position ahead
$stmt = $conn->prepare("SELECT COUNT(*) FROM waiting_queue WHERE id_event = ? AND status = 'waiting' AND queue_number < ?");
$stmt->execute([$id_event, $queue['queue_number']]);
$positionAhead = $stmt->fetchColumn();

// 4. SWARM BOT SIMULATION (Responsive to Market Sentiment)
// Fetch current simulation state
$stmtM = $conn->prepare("SELECT simulated_demand FROM event WHERE id_event = ?");
$stmtM->execute([$id_event]);
$simDemand = $stmtM->fetchColumn() ?: 0;

// Cache available vouchers for this event
$vStmt = $conn->prepare("SELECT * FROM vouchers WHERE id_event = ? AND status = 'active' AND current_usage < max_usage");
$vStmt->execute([$id_event]);
$availableVouchers = $vStmt->fetchAll();
$paymentMethods = ['bca', 'mandiri', 'bni', 'gopay', 'ovo'];

// Swarm size is dynamic: Higher demand = More bots attacking simultaneously
$swarmSize = mt_rand(1, 3) + floor($simDemand / 12); 
for ($s = 0; $s < $swarmSize; $s++) {
    try {
        $botStmt = $conn->prepare("SELECT id_user, nama, email FROM users WHERE is_bot = 1 ORDER BY RAND() LIMIT 1");
        $botStmt->execute();
        $bot = $botStmt->fetch();
        
        if ($bot) {
            $catStmt = $conn->prepare("SELECT id_tiket, harga, kuota FROM tiket WHERE id_event = ? AND kuota > 0 ORDER BY RAND() LIMIT 1");
            $catStmt->execute([$id_event]);
            $cat = $catStmt->fetch();
            
            if ($cat) {
                // BRUTAL BOT: Randomly grab up to 4 tickets (reduced for realism)
                $qty = mt_rand(1, 4);
                
                if ($qty > $cat['kuota']) $qty = $cat['kuota'];
                if ($qty <= 0) continue;
                
                $originalTotal = $cat['harga'] * $qty;
                $discount = 0;

                // 25% bot uses voucher
                if (!empty($availableVouchers) && mt_rand(1, 100) <= 25) {
                    $v = $availableVouchers[array_rand($availableVouchers)];
                    $discount = (int)$v['discount_amount'];
                    $conn->prepare("UPDATE vouchers SET current_usage = current_usage + 1 WHERE id_voucher = ?")->execute([$v['id_voucher']]);
                }
                
                $finalTotal = max(0, $originalTotal - $discount);
                $pMethod = $paymentMethods[array_rand($paymentMethods)];
                
                // Status randomization
                $randS = mt_rand(1, 100);
                if ($randS <= 70) { $status = 'paid'; $pType = 'full'; $amtP = $finalTotal; }
                elseif ($randS <= 92) { $status = 'dp_paid'; $pType = 'dp'; $amtP = $finalTotal * 0.3; }
                else { $status = 'cancelled'; $pType = 'full'; $amtP = 0; }

                $orderCodeStr = 'BOT-WAR-' . strtoupper(substr(uniqid(), -6));
                
                // Create Order with required non-null fields
                $conn->prepare("
                    INSERT INTO orders (
                        order_code, id_user, id_event, total, discount, amount_paid, 
                        status, payment_type, metode_pembayaran, bukti_pembayaran_url, 
                        tanggal_pembayaran, tanggal_order, tanggal_kadaluarsa
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), DATE_ADD(NOW(), INTERVAL 1 DAY))
                ")->execute([
                    $orderCodeStr, $bot['id_user'], $id_event, $finalTotal, $discount, $amtP,
                    $status, $pType, $pMethod, 'uploads/payments/bot_swarm_proof.png'
                ]);
                
                $orderId = $conn->lastInsertId();
                $conn->prepare("INSERT INTO order_detail (id_order, id_tiket, qty, harga_satuan, subtotal) VALUES (?, ?, ?, ?, ?)")
                     ->execute([$orderId, $cat['id_tiket'], $qty, $cat['harga'], $originalTotal]);
                
                $orderItemId = $conn->lastInsertId();

                if ($status !== 'cancelled') {
                    // Generate Tickets
                    for ($i = 0; $i < $qty; $i++) {
                        $kode_tiket = "TIX-BOT-" . date('ymd') . "-" . strtoupper(substr(md5(uniqid(rand(), true)), 0, 4));
                        $qrPathForDb = "https://api.qrserver.com/v1/create-qr-code/?size=400x400&data=" . urlencode($kode_tiket);

                        $conn->prepare("INSERT INTO attendee (id_detail, kode_tiket, qr_code_url, status_checkin, nama_pemegang, email_pemegang) VALUES (?, ?, ?, 'active', ?, ?)")
                             ->execute([$orderItemId, $kode_tiket, $qrPathForDb, $bot['nama'], $bot['email']]);
                    }

                    // Update real stock
                    $conn->prepare("UPDATE tiket SET kuota = kuota - ? WHERE id_tiket = ?")
                         ->execute([$qty, $cat['id_tiket']]);
                }

                // BOT OCCUPANCY: Make the bot occupy a checkout slot in the Gatekeeper
                $conn->prepare("INSERT INTO waiting_queue (id_user, id_event, queue_number, status, joined_at, activated_at, expires_at) VALUES (?, ?, 0, 'active', NOW(), NOW(), DATE_ADD(NOW(), INTERVAL 1 MINUTE))")
                     ->execute([$bot['id_user'], $id_event]);
            }
        }
    } catch (Exception $e) { /* silent bot error */ }
}

// 5. Activation Logic & Concurrency Check
$joinedAt = $queue['joined_at'] ?? date('Y-m-d H:i:s');
$secondsSinceJoined = time() - strtotime($joinedAt);
$minWaitTime = 10;

// Fetch Gatekeeper Config
$stmtK = $conn->prepare("SELECT max_concurrent_checkout FROM event WHERE id_event = ?");
$stmtK->execute([$id_event]);
$maxConcurrent = $stmtK->fetchColumn() ?: 200;

// Count how many people are currently ACTIVE (in checkout)
$stmtActive = $conn->prepare("SELECT COUNT(*) FROM waiting_queue WHERE id_event = ? AND status = 'active' AND expires_at > NOW()");
$stmtActive->execute([$id_event]);
$activeCount = $stmtActive->fetchColumn();

// SLOW PROGRESS VELOCITY: The "Hardcore Experience"
// Each poll (3s) should drop ~13-14 people.
// velocity = 4.5 people/second
// Stable unique velocity per user (~4-8 drop per 3s poll)
mt_srand((int)$id_user + (int)$id_event);
$personalVelocity = mt_rand(13, 23) / 10; // Drop 4-7 people per 3 seconds poll
mt_srand(); // Reset seed

$currentPos = floor($queue['queue_number'] - ($secondsSinceJoined * $personalVelocity));

if ($currentPos < 0) $currentPos = 0;

// Dynamic minWaitTime based on velocity
$minWaitTime = 5; // Minimal wait time 5 seconds

// ONLY Activate if: Position is 0 AND we have slot in Gatekeeper
if ($currentPos <= 0 && $secondsSinceJoined >= $minWaitTime && $activeCount < $maxConcurrent) {
    if ($queue['status'] !== 'active') {
        $up = $conn->prepare("UPDATE waiting_queue SET status = 'active', activated_at = NOW(), expires_at = DATE_ADD(NOW(), INTERVAL 15 MINUTE) WHERE id = ?");
        $up->execute([$queue['id']]);
    }
    echo json_encode(['status' => 'active']);
    exit;
}

echo json_encode([
    'status' => 'waiting',
    'position_ahead' => (int)$currentPos,
    'queue_number' => (int)$queue['queue_number'],
    'seconds_joined' => $secondsSinceJoined
]);
?>
