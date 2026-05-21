<?php
session_start();
require_once 'koneksi.php';

if (!isset($_SESSION['id_user']) || $_SESSION['role'] !== 'admin') {
    header("Location: index");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_user = $_POST['id_user'];
    $is_vip = $_POST['is_vip'];

    try {
        $stmt = $conn->prepare("UPDATE users SET is_vip = ? WHERE id_user = ?");
        $stmt->execute([$is_vip, $id_user]);
        
        header("Location: admin_history?msg=vip_updated");
        exit;
    } catch (PDOException $e) {
        header("Location: admin_history?msg=error");
        exit;
    }
}

header("Location: admin_history");
exit;
?>
