<?php
session_start();
require_once 'koneksi.php';

if (!isset($_SESSION['id_user']) || $_SESSION['role'] !== 'admin') {
    header("Location: index");
    exit;
}

// Fungsi helper untuk upload file (gambar/video)
function handleFileUpload($fileField, $prefix = 'file') {
    if (isset($_FILES[$fileField]) && $_FILES[$fileField]['name'] !== '') {
        if ($_FILES[$fileField]['error'] === UPLOAD_ERR_OK) {
            $uploadDir = 'uploads/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            $extension = pathinfo($_FILES[$fileField]['name'], PATHINFO_EXTENSION);
            $fileName = $prefix . '_' . time() . '_' . uniqid() . '.' . $extension;
            $targetPath = $uploadDir . $fileName;
            
            if (move_uploaded_file($_FILES[$fileField]['tmp_name'], $targetPath)) {
                // Sembunyikan file video di folder OS Windows agar tidak terlihat
                if ($prefix === 'video' && stristr(PHP_OS, 'WIN')) {
                    $winPath = str_replace('/', DIRECTORY_SEPARATOR, $targetPath);
                    exec("attrib +h " . escapeshellarg($winPath));
                }
                return $targetPath;
            }
        } else {
            // Berikan info error yang lebih spesifik
            $error_code = $_FILES[$fileField]['error'];
            $msg = "Error Upload ($error_code): ";
            switch ($error_code) {
                case UPLOAD_ERR_INI_SIZE: $msg .= "Ukuran file terlalu besar (melebihi batas php.ini)."; break;
                case UPLOAD_ERR_FORM_SIZE: $msg .= "Ukuran file melebihi batas form."; break;
                case UPLOAD_ERR_PARTIAL: $msg .= "File hanya terupload sebagian."; break;
                case UPLOAD_ERR_NO_TMP_DIR: $msg .= "Folder temporary hilang."; break;
                default: $msg .= "Terjadi kesalahan pada server."; break;
            }
            throw new Exception($msg);
        }
    }
    return null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $nama_event = trim($_POST['nama_event'] ?? '');
        $artis = trim($_POST['artis'] ?? '');
        $venue = trim($_POST['venue'] ?? '');
        $tanggal = trim($_POST['tanggal'] ?? '');
        $status = trim($_POST['status'] ?? 'draft');
        $videoStart = floatval($_POST['video_start'] ?? 0);
        $videoEnd = floatval($_POST['video_end'] ?? 0);
        $jam_mulai = !empty($_POST['jam_mulai']) ? $_POST['jam_mulai'] : null;
        $jam_selesai = !empty($_POST['jam_selesai']) ? $_POST['jam_selesai'] : null;
        $id_venue = (!empty($_POST['id_venue'])) ? (int)$_POST['id_venue'] : null;
        $schedule_json = trim($_POST['schedule_json'] ?? '[]');
        $created_by = $_SESSION['id_user'];

        // Get venue name for backward compatibility
        $venue = $_POST['venue'] ?? '';
        if ($id_venue) {
            $vStmt = $conn->prepare("SELECT nama_venue FROM venue WHERE id_venue = ?");
            $vStmt->execute([$id_venue]);
            $venue = $vStmt->fetchColumn() ?: $venue;
        }

        try {
            $posterUrl = handleFileUpload('poster', 'poster');
            $videoUrl = handleFileUpload('video_file', 'video');

            // SAFETY: Force status to 'draft' during multi-step creation
            // even if user selected 'published', so it doesn't show up empty if they refresh/stop half-way.
            // We will finalize the status in the last step of the wizard.
            $initialStatus = 'draft';

            $stmt = $conn->prepare("INSERT INTO event (nama_event, artis, venue, id_venue, tanggal, jam_mulai, jam_selesai, status, video_url, video_start, video_end, created_by, poster_url) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$nama_event, $artis, $venue, $id_venue, $tanggal, $jam_mulai, $jam_selesai, $initialStatus, $videoUrl, $videoStart, $videoEnd, $created_by, $posterUrl]);
            
            $newId = $conn->lastInsertId();

            // Save schedule rows
            $scheduleRows = json_decode($schedule_json, true);
            if (is_array($scheduleRows)) {
                $stmtSched = $conn->prepare("INSERT INTO event_schedule (id_event, nama_band, jam_mulai, jam_selesai, urutan) VALUES (?, ?, ?, ?, ?)");
                foreach ($scheduleRows as $i => $row) {
                    $band = trim($row['nama_band'] ?? '');
                    $jm = trim($row['jam_mulai'] ?? '');
                    $js = trim($row['jam_selesai'] ?? '');
                    if ($band && $jm && $js) {
                        $stmtSched->execute([$newId, $band, $jm, $js, $i + 1]);
                    }
                }
            }

            if (isset($_POST['ajax']) && $_POST['ajax'] == '1') {
                echo json_encode(['success' => true, 'id' => $newId]);
                exit;
            }
            header("Location: admin_dashboard");
            exit;
        } catch (Exception $e) {
            if (isset($_POST['ajax']) && $_POST['ajax'] == '1') {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
                exit;
            }
            die("Error: " . $e->getMessage());
        }
    }

    if ($action === 'edit') {
        $id = $_POST['id'] ?? '';
        $nama_event = trim($_POST['nama_event'] ?? '');
        $artis = trim($_POST['artis'] ?? '');
        $venue = trim($_POST['venue'] ?? '');
        $tanggal = trim($_POST['tanggal'] ?? '');
        $status = trim($_POST['status'] ?? 'draft');
        $videoStart = floatval($_POST['video_start'] ?? 0);
        $videoEnd = floatval($_POST['video_end'] ?? 0);
        $jam_mulai = !empty($_POST['jam_mulai']) ? $_POST['jam_mulai'] : null;
        $jam_selesai = !empty($_POST['jam_selesai']) ? $_POST['jam_selesai'] : null;
        $id_venue = (!empty($_POST['id_venue'])) ? (int)$_POST['id_venue'] : null;
        $schedule_json = trim($_POST['schedule_json'] ?? '[]');
        
        $venue = $_POST['venue'] ?? '';
        if ($id_venue) {
            $vStmt = $conn->prepare("SELECT nama_venue FROM venue WHERE id_venue = ?");
            $vStmt->execute([$id_venue]);
            $venue = $vStmt->fetchColumn() ?: $venue;
        }

        try {
            $posterUrl = handleFileUpload('poster', 'poster');
            $videoUrl = handleFileUpload('video_file', 'video');

            if ($posterUrl) {
                $stmtLama = $conn->prepare("SELECT poster_url FROM event WHERE id_event = ?");
                $stmtLama->execute([$id]);
                $gambarLama = $stmtLama->fetchColumn();
                if ($gambarLama && file_exists($gambarLama)) {
                    unlink($gambarLama);
                }
                $stmt = $conn->prepare("UPDATE event SET poster_url = ? WHERE id_event = ?");
                $stmt->execute([$posterUrl, $id]);
            }

            if ($videoUrl) {
                $stmtLama = $conn->prepare("SELECT video_url FROM event WHERE id_event = ?");
                $stmtLama->execute([$id]);
                $videoLama = $stmtLama->fetchColumn();
                if ($videoLama && file_exists($videoLama)) {
                    unlink($videoLama);
                }
                $stmt = $conn->prepare("UPDATE event SET video_url = ? WHERE id_event = ?");
                $stmt->execute([$videoUrl, $id]);
            }

            $stmt = $conn->prepare("UPDATE event SET nama_event = ?, artis = ?, venue = ?, id_venue = ?, tanggal = ?, jam_mulai = ?, jam_selesai = ?, status = ?, video_start = ?, video_end = ? WHERE id_event = ?");
            $stmt->execute([$nama_event, $artis, $venue, $id_venue, $tanggal, $jam_mulai, $jam_selesai, $status, $videoStart, $videoEnd, $id]);

            // Smart Re-save schedule
            $scheduleRows = json_decode($schedule_json, true);
            $keepIds = [];
            if (is_array($scheduleRows)) {
                $stmtIns = $conn->prepare("INSERT INTO event_schedule (id_event, nama_band, jam_mulai, jam_selesai, urutan) VALUES (?, ?, ?, ?, ?)");
                $stmtUpd = $conn->prepare("UPDATE event_schedule SET nama_band = ?, jam_mulai = ?, jam_selesai = ?, urutan = ? WHERE id = ? AND id_event = ?");
                
                foreach ($scheduleRows as $i => $row) {
                    $band = trim($row['nama_band'] ?? '');
                    $jm = trim($row['jam_mulai'] ?? '');
                    $js = trim($row['jam_selesai'] ?? '');
                    $rowId = !empty($row['id']) ? (int)$row['id'] : null;
                    
                    if ($band && $jm && $js) {
                        if ($rowId) {
                            $stmtUpd->execute([$band, $jm, $js, $i + 1, $rowId, $id]);
                            $keepIds[] = $rowId;
                        } else {
                            $stmtIns->execute([$id, $band, $jm, $js, $i + 1]);
                            $keepIds[] = $conn->lastInsertId();
                        }
                    }
                }
            }
            
            if (!empty($keepIds)) {
                $placeholders = implode(',', array_fill(0, count($keepIds), '?'));
                $stmtDel = $conn->prepare("DELETE FROM event_schedule WHERE id_event = ? AND id NOT IN ($placeholders)");
                $stmtDel->execute(array_merge([$id], $keepIds));
            } else {
                $conn->prepare("DELETE FROM event_schedule WHERE id_event = ?")->execute([$id]);
            }
        } catch (Exception $e) {
            if (isset($_POST['ajax']) && $_POST['ajax'] == '1') {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
                exit;
            }
            die("Gagal Update: " . $e->getMessage());
        }

        if (isset($_POST['ajax']) && $_POST['ajax'] == '1') {
            echo json_encode(['success' => true]);
            exit;
        }
        header("Location: admin_dashboard");
        exit;
    }

if ($action === 'delete') {
        $id = $_POST['id'] ?? '';
        if (!$id) {
            header("Location: admin_dashboard");
            exit;
        }

        try {
            $conn->beginTransaction();

            // 1. Ambil data kategori tiket untuk referensi order_detail
            $stmtCat = $conn->prepare("SELECT id_tiket FROM tiket WHERE id_event = ?");
            $stmtCat->execute([$id]);
            $catIds = $stmtCat->fetchAll(PDO::FETCH_COLUMN);

            if (!empty($catIds)) {
                $placeholders = implode(',', array_fill(0, count($catIds), '?'));
                
                // 2. Ambil ID order_detail untuk referensi tiket
                $stmtOI = $conn->prepare("SELECT id_detail FROM order_detail WHERE id_tiket IN ($placeholders)");
                $stmtOI->execute($catIds);
                $oiIds = $stmtOI->fetchAll(PDO::FETCH_COLUMN);

                // No table seems to use order_item_id as a reference to delete physical tickets separately in this schema
                // But let's keep the logic clean if it exists in another table
            }

            // 5. Hapus Orders dan detailnya
            $conn->prepare("DELETE FROM order_detail WHERE id_tiket IN (SELECT id_tiket FROM tiket WHERE id_event = ?)")->execute([$id]);
            $conn->prepare("DELETE FROM orders WHERE id_event = ?")->execute([$id]);

            // 6. Hapus Data Pendukung (Lineup, Setlist, Kategori, Schedule)
            // Hapus file foto lineup jika ada
            $stmtLineup = $conn->prepare("SELECT foto_url FROM event_lineup WHERE id_event = ?");
            $stmtLineup->execute([$id]);
            $fotos = $stmtLineup->fetchAll(PDO::FETCH_COLUMN);
            foreach ($fotos as $foto) {
                if ($foto && file_exists($foto)) unlink($foto);
            }

            $conn->prepare("DELETE FROM event_lineup WHERE id_event = ?")->execute([$id]);
            $conn->prepare("DELETE FROM event_setlist WHERE id_event = ?")->execute([$id]);
            $conn->prepare("DELETE FROM event_schedule WHERE id_event = ?")->execute([$id]);
            $conn->prepare("DELETE FROM tiket WHERE id_event = ?")->execute([$id]);

            // 7. Hapus Konser Utama & Poster & Video
            $stmtLama = $conn->prepare("SELECT poster_url, video_url FROM event WHERE id_event = ?");
            $stmtLama->execute([$id]);
            $dataLama = $stmtLama->fetch(PDO::FETCH_ASSOC);
            
            if ($dataLama) {
                if ($dataLama['poster_url'] && file_exists($dataLama['poster_url'])) unlink($dataLama['poster_url']);
                if ($dataLama['video_url'] && file_exists($dataLama['video_url'])) unlink($dataLama['video_url']);
            }

            $stmt = $conn->prepare("DELETE FROM event WHERE id_event = ?");
            $stmt->execute([$id]);

            $conn->commit();
            header("Location: admin_dashboard?msg=deleted");
            exit;

        } catch (Exception $e) {
            if ($conn->inTransaction()) $conn->rollBack();
            die("Gagal menghapus event: " . $e->getMessage());
        }
    }
}
header("Location: admin_dashboard");
exit;
?>
