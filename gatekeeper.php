<?php
session_start();
require_once 'koneksi.php';
// SILENT SCHEMA UPDATE
try { $conn->exec("ALTER TABLE users ADD COLUMN is_vip TINYINT(1) DEFAULT 0"); } catch(Exception $e) {}

if (!isset($_SESSION['id_user'])) {
    header("Location: login");
    exit;
}

$id_user  = $_SESSION['id_user'];
$tiket_id = $_GET['tiket_id'] ?? 0;
$test_war = isset($_GET['test_war']); // DEBUG MODE

if (!$tiket_id) {
    header("Location: user_dashboard");
    exit;
}

// 1. VIP FAST TRACK CHECK — use already-fetched $appUser
$stmtV = $conn->prepare("SELECT is_vip FROM users WHERE id_user = ?");
$stmtV->execute([$id_user]);
$appUser = $stmtV->fetch();
$isVip = ($appUser['is_vip'] ?? 0);

// 1. Get Ticket Category Info
$stmtT = $conn->prepare("SELECT * FROM tiket WHERE id_tiket = ?");
$stmtT->execute([$tiket_id]);
$cat = $stmtT->fetch();

if (!$cat) {
    header("Location: user_dashboard");
    exit;
}

$id_event = $cat['id_event'];

// 2. Get Event Detail
$stmtE = $conn->prepare("SELECT * FROM event WHERE id_event = ?");
$stmtE->execute([$id_event]);
$konser = $stmtE->fetch();

if (!$konser) {
    header("Location: user_dashboard");
    exit;
}

// DEBUG: Clear existing session if test_war is active
if ($test_war) {
    $conn->prepare("UPDATE waiting_queue SET status = 'expired' WHERE id_user = ? AND id_event = ? AND status = 'active'")->execute([$id_user, $id_event]);
}

// NOTE: Anti-refresh is intentionally removed for non-VIP.
// Non-VIP always goes through waiting room every time they click buy.

// 2.5 VIP Whitelisting Bypass — skip queue entirely
if ($isVip) {
    // VIP: clear any old queue & insert fresh active session, then direct to booking
    $conn->prepare("DELETE FROM waiting_queue WHERE id_user = ? AND id_event = ?")
         ->execute([$id_user, $id_event]);
    $conn->prepare("INSERT INTO waiting_queue (id_user, id_event, queue_number, status, activated_at, expires_at) VALUES (?, ?, 0, 'active', NOW(), DATE_ADD(NOW(), INTERVAL 15 MINUTE))")
         ->execute([$id_user, $id_event]);
    header("Location: booking?tiket_id=" . $tiket_id);
    exit;
}

// 3. Check Manual Override (admin-forced queue)
if ($konser['is_queue_active']) {
    header("Location: waiting_room?tiket_id=" . $tiket_id);
    exit;
}

// 4. Check Concurrency Limit
$stmtActive = $conn->prepare("SELECT COUNT(*) FROM waiting_queue WHERE id_event = ? AND status = 'active' AND expires_at > NOW()");
$stmtActive->execute([$id_event]);
$activeCount = $stmtActive->fetchColumn();

$maxAccess = (!empty($konser['max_concurrent_checkout']) && $konser['max_concurrent_checkout'] > 0)
    ? $konser['max_concurrent_checkout'] : 100;

if ($activeCount >= $maxAccess) {
    header("Location: waiting_room?tiket_id=" . $tiket_id);
    exit;
}

// 5. ALWAYS send non-VIP to waiting room (as per system design)
// The waiting room handles its own queue countdown and auto-advances users
header("Location: waiting_room?tiket_id=" . $tiket_id);
exit;
?>
