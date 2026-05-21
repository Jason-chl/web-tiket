<?php
session_start();
require_once 'koneksi.php';

if (!isset($_SESSION['id_user']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    exit;
}

$id = $_GET['id'] ?? '';
if (!$id) {
    echo json_encode(['error' => 'No ID provided']);
    exit;
}

try {
    $stmtL = $conn->prepare("SELECT * FROM event_lineup WHERE id_event = ? ORDER BY id ASC");
    $stmtL->execute([$id]);
    $lineup = $stmtL->fetchAll(PDO::FETCH_ASSOC);

    $stmtS = $conn->prepare("SELECT * FROM event_setlist WHERE id_event = ? ORDER BY urutan ASC, id ASC");
    $stmtS->execute([$id]);
    $setlist = $stmtS->fetchAll(PDO::FETCH_ASSOC);

    $stmtC = $conn->prepare("SELECT * FROM tiket WHERE id_event = ? ORDER BY harga ASC");
    $stmtC->execute([$id]);
    $categories = $stmtC->fetchAll(PDO::FETCH_ASSOC);

    $stmtSched = $conn->prepare("SELECT * FROM event_schedule WHERE id_event = ? ORDER BY urutan ASC");
    $stmtSched->execute([$id]);
    $schedule = $stmtSched->fetchAll(PDO::FETCH_ASSOC);

    $stmtVenue = $conn->prepare("
        SELECT v.kapasitas 
        FROM event e
        LEFT JOIN venue v ON e.id_venue = v.id_venue
        WHERE e.id_event = ?
    ");
    $stmtVenue->execute([$id]);
    $venueCapacity = $stmtVenue->fetchColumn();

    // Fetch basic event info to restore Step 1 form
    $stmtE = $conn->prepare("
        SELECT e.*, v.nama_venue 
        FROM event e 
        LEFT JOIN venue v ON e.id_venue = v.id_venue 
        WHERE e.id_event = ?
    ");
    $stmtE->execute([$id]);
    $event = $stmtE->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'event' => $event,
        'lineup' => $lineup,
        'setlist' => $setlist,
        'categories' => $categories,
        'schedule' => $schedule,
        'venue_cap' => $venueCapacity ?: 0
    ]);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>
