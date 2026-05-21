<?php
session_start();
require_once 'koneksi.php';

if (!isset($_SESSION['id_user']) || $_SESSION['role'] !== 'admin') {
    header("Location: index");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id_event = $_POST['id_event'] ?? '';

    if (!$id_event) {
        header("Location: admin_dashboard");
        exit;
    }

    try {
        $isAjax = isset($_POST['ajax']) && $_POST['ajax'] == '1';

        if ($action === 'add_member') {
            $nama = trim($_POST['nama_member']);
            $peran = trim($_POST['peran']);
            $fotoUrl = '';

            if (!empty($_POST['foto_base64'])) {
                $base64Data = $_POST['foto_base64'];
                list($type, $data) = explode(';', $base64Data);
                list(, $data)      = explode(',', $data);
                $data = base64_decode($data);
                $uploadDir = 'uploads/lineup/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
                $fileName = 'member_' . time() . '_' . uniqid() . '.png';
                $targetPath = $uploadDir . $fileName;
                if (file_put_contents($targetPath, $data)) $fotoUrl = $targetPath;
            }

            $id_schedule = !empty($_POST['id_schedule']) ? (int)$_POST['id_schedule'] : null;
            $stmt = $conn->prepare("INSERT INTO event_lineup (id_event, id_schedule, nama_member, peran, foto_url) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$id_event, $id_schedule, $nama, $peran, $fotoUrl]);
            
            if ($isAjax) { echo json_encode(['success' => true]); exit; }
            header("Location: admin_manage_event?id=" . $id_event . "&msg=success");
            exit;
        }

        if ($action === 'del_member') {
            $id = $_POST['id'];
            $sel = $conn->prepare("SELECT foto_url FROM event_lineup WHERE id = ?");
            $sel->execute([$id]);
            $row = $sel->fetch();
            if ($row && !empty($row['foto_url']) && file_exists($row['foto_url'])) unlink($row['foto_url']);

            $stmt = $conn->prepare("DELETE FROM event_lineup WHERE id = ?");
            $stmt->execute([$id]);
            
            if ($isAjax) { echo json_encode(['success' => true]); exit; }
            header("Location: admin_manage_event?id=" . $id_event . "&msg=success");
            exit;
        }

        if ($action === 'add_song') {
            $id_schedule = !empty($_POST['id_schedule']) ? (int)$_POST['id_schedule'] : null;
            $urutan = $_POST['urutan'] ?? 1;
            $judul = trim($_POST['judul_lagu']);
            $stmt = $conn->prepare("INSERT INTO event_setlist (id_event, id_schedule, urutan, judul_lagu) VALUES (?, ?, ?, ?)");
            $stmt->execute([$id_event, $id_schedule, $urutan, $judul]);
            
            if ($isAjax) { echo json_encode(['success' => true]); exit; }
            header("Location: admin_manage_event?id=" . $id_event . "&msg=success");
            exit;
        }

        if ($action === 'del_song') {
            $id = $_POST['id'];
            $stmt = $conn->prepare("DELETE FROM event_setlist WHERE id = ?");
            $stmt->execute([$id]);
            
            if ($isAjax) { echo json_encode(['success' => true]); exit; }
            header("Location: admin_manage_event?id=" . $id_event . "&msg=success");
            exit;
        }

        if ($action === 'add_category') {
            $nama = trim($_POST['nama_tiket']);
            $harga = (int)$_POST['harga'];
            $stok = (int)$_POST['stok'];
            $warna = $_POST['warna'] ?? '#ffffff';
            $id_schedule = !empty($_POST['id_schedule']) ? (int)$_POST['id_schedule'] : null;
            
            // Check venue capacity
            $stmtV = $conn->prepare("SELECT v.kapasitas FROM event e LEFT JOIN venue v ON e.id_venue = v.id_venue WHERE e.id_event = ?");
            $stmtV->execute([$id_event]);
            $venue = $stmtV->fetch();
            $kapasitas = $venue['kapasitas'] ?? 0;
            
            // Get current total tickets
            $stmtC = $conn->prepare("SELECT SUM(kuota) as total_kuota FROM tiket WHERE id_event = ?");
            $stmtC->execute([$id_event]);
            $current_total = $stmtC->fetch()['total_kuota'] ?? 0;
            
            if ($current_total + $stok > $kapasitas) {
                if ($isAjax) { echo json_encode(['success' => false, 'message' => 'Melebihi kapasitas venue']); exit; }
                header("Location: admin_manage_event?id=" . $id_event . "&msg=over_capacity");
                exit;
            }
            
            $stmt = $conn->prepare("INSERT INTO tiket (id_event, id_schedule, nama_tiket, harga, kuota, warna_kategori) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$id_event, $id_schedule, $nama, $harga, $stok, $warna]);
            
            if ($isAjax) { echo json_encode(['success' => true]); exit; }
            header("Location: admin_manage_event?id=" . $id_event . "&msg=success");
            exit;
        }

        if ($action === 'del_category') {
            $id = $_POST['id'];
            $stmt = $conn->prepare("DELETE FROM tiket WHERE id_tiket = ?");
            $stmt->execute([$id]);
            
            if ($isAjax) { echo json_encode(['success' => true]); exit; }
            header("Location: admin_manage_event?id=" . $id_event . "&msg=success");
            exit;
        }

        if ($action === 'edit_category') {
            $id = $_POST['id'];
            $nama = trim($_POST['nama_tiket']);
            $harga = (int)$_POST['harga'];
            $stok = (int)$_POST['stok'];
            
            // Check venue capacity
            $stmtV = $conn->prepare("SELECT v.kapasitas FROM event e LEFT JOIN venue v ON e.id_venue = v.id_venue WHERE e.id_event = ?");
            $stmtV->execute([$id_event]);
            $venue = $stmtV->fetch();
            $kapasitas = $venue['kapasitas'] ?? 0;
            
            // Get current total tickets excluding this category
            $stmtC = $conn->prepare("SELECT SUM(kuota) as total_kuota FROM tiket WHERE id_event = ? AND id_tiket != ?");
            $stmtC->execute([$id_event, $id]);
            $current_total = $stmtC->fetch()['total_kuota'] ?? 0;
            
            if ($current_total + $stok > $kapasitas) {
                if ($isAjax) { echo json_encode(['success' => false, 'message' => 'Melebihi kapasitas venue']); exit; }
                header("Location: admin_manage_event?id=" . $id_event . "&msg=over_capacity");
                exit;
            }
            
            $stmt = $conn->prepare("UPDATE tiket SET nama_tiket = ?, harga = ?, kuota = ? WHERE id_tiket = ?");
            $stmt->execute([$nama, $harga, $stok, $id]);
            
            if ($isAjax) { echo json_encode(['success' => true]); exit; }
            header("Location: admin_manage_event?id=" . $id_event . "&msg=success");
            exit;
        }

        if ($action === 'finalize_event') {
            // User requested to auto-publish upon wizard completion
            $stmt = $conn->prepare("UPDATE event SET status = 'published' WHERE id_event = ?");
            $stmt->execute([$id_event]);
            
            if ($isAjax) { echo json_encode(['success' => true]); exit; }
            header("Location: admin_dashboard");
            exit;
        }

        if ($action === 'update_gatekeeper') {
            $is_queue = (int)$_POST['is_queue_active'];
            $max_concurrent = (int)$_POST['max_concurrent_checkout'];
            $threshold = (int)$_POST['queue_threshold'];
            
            $stmt = $conn->prepare("UPDATE event SET is_queue_active = ?, max_concurrent_checkout = ?, queue_threshold = ? WHERE id_event = ?");
            $stmt->execute([$is_queue, $max_concurrent, $threshold, $id_event]);
            
            if ($isAjax) { echo json_encode(['success' => true]); exit; }
            header("Location: admin_manage_event?id=" . $id_event . "&msg=success");
            exit;
        }

    } catch (PDOException $e) {
        if ($isAjax) { echo json_encode(['success' => false, 'message' => $e->getMessage()]); exit; }
        header("Location: admin_manage_event?id=" . $id_event . "&msg=error");
        exit;
    }
}

header("Location: admin_dashboard");
exit;
?>
