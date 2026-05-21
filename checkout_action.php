<?php
session_start();
require_once 'koneksi.php';

if (!isset($_SESSION['id_user']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index");
    exit;
}

$id_order = $_POST['id_order'] ?? '';
$payment_method = $_POST['payment_method'] ?? '';
$action_type = $_POST['action_type'] ?? '';

if (!$id_order || !$payment_method) {
    header("Location: user_dashboard");
    exit;
}

try {
    $ptype = ($action_type === 'pay_dp') ? 'dp' : 'full';
    
    $stmt = $conn->prepare("UPDATE orders SET metode_pembayaran = ?, payment_type = ? WHERE id_order = ? AND id_user = ? AND (status = 'pending' OR status = 'dp_paid')");
    $stmt->execute([$payment_method, $ptype, $id_order, $_SESSION['id_user']]);

    header("Location: payment_gateway?order=" . urlencode($id_order));
    exit;

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
