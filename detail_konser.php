<?php
session_start();
require_once 'koneksi.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: " . (isset($_SESSION['id_user']) ? 'user_dashboard.php' : 'index.php'));
    exit;
}
$isLoggedIn = isset($_SESSION['id_user']);

$konserId = $_GET['id'];

// Get concert details
$stmt = $conn->prepare("SELECT * FROM event WHERE id_event = ?");
$stmt->execute([$konserId]);
$konser = $stmt->fetch();

if (!$konser || !in_array($konser['status'], ['published', 'ongoing', 'completed'])) {
    header("Location: user_dashboard");
    exit;
}

// Hitung apakah event expired / ongoing / upcoming
$nowDt = new DateTime();
$startDt = new DateTime($konser['tanggal']);

$stmtEndTime = $conn->prepare("SELECT MAX(jam_selesai) FROM event_schedule WHERE id_event = ?");
$stmtEndTime->execute([$konserId]);
$maxJamSelesai = $stmtEndTime->fetchColumn();
if ($maxJamSelesai) {
    $endDt = new DateTime(date('Y-m-d', strtotime($konser['tanggal'])) . ' ' . $maxJamSelesai);
} else {
    $endDt = clone $startDt;
    $endDt->modify('+4 hours');
}

$stmtMinStart = $conn->prepare("SELECT MIN(jam_mulai) FROM event_schedule WHERE id_event = ?");
$stmtMinStart->execute([$konserId]);
$minJamMulai = $stmtMinStart->fetchColumn();
$displayJamMulai = $minJamMulai ? date('H:i', strtotime($minJamMulai)) : date('H:i', strtotime($konser['tanggal']));
$displayJamSelesai = $maxJamSelesai ? date('H:i', strtotime($maxJamSelesai)) : date('H:i', strtotime($konser['tanggal']) + 4*3600);

if ($nowDt < $startDt) {
    $eventComputedStatus = 'upcoming';
} elseif ($nowDt >= $startDt && $nowDt <= $endDt) {
    $eventComputedStatus = 'ongoing';
} else {
    $eventComputedStatus = 'expired';
}
$isExpired = ($eventComputedStatus === 'expired');

// Get ticket categories
$stmtCat = $conn->prepare("SELECT * FROM tiket WHERE id_event = ? ORDER BY harga ASC");
$stmtCat->execute([$konserId]);
$kategori = $stmtCat->fetchAll();

// Get lineup
$stmtL = $conn->prepare("SELECT * FROM event_lineup WHERE id_event = ? ORDER BY id ASC");
$stmtL->execute([$konserId]);
$lineup = $stmtL->fetchAll();

// Get setlist
$stmtS = $conn->prepare("SELECT * FROM event_setlist WHERE id_event = ? ORDER BY urutan ASC, id ASC");
$stmtS->execute([$konserId]);
$setlist = $stmtS->fetchAll();

// Get schedule timeline
$stmtT = $conn->prepare("SELECT * FROM event_schedule WHERE id_event = ? ORDER BY jam_mulai ASC");
$stmtT->execute([$konserId]);
$schedule = $stmtT->fetchAll();

$scheduleMap = [];
foreach ($schedule as $row) {
    $scheduleMap[$row['id']] = $row['nama_band'];
}

$setlistByBand = [];
foreach ($setlist as $s) {
    $bandKey = !empty($s['id_schedule']) ? $s['id_schedule'] : '__general__';
    $setlistByBand[$bandKey][] = $s;
}

$posterUrl = !empty($konser['poster_url']) ? htmlspecialchars($konser['poster_url']) : 'https://images.unsplash.com/photo-1540039155732-68b2dbceaebd?q=80&w=2000&auto=format&fit=crop';
$bannerUrl = !empty($konser['banner_url']) ? htmlspecialchars($konser['banner_url']) : $posterUrl;
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($konser['nama_event']); ?> — TixNow</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link
        href="https://fonts.googleapis.com/css2?family=Syne:wght@400;500;600;700;800&family=Inter:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <script>
        // IMMEDATE THEME LOAD
        (function () {
            const savedTheme = localStorage.getItem('theme') || 'dark';
            document.documentElement.setAttribute('data-theme', savedTheme);
        })();
    </script>
</head>

<body>
    <style>
        *,
        *::before,
        *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        /* LIGHT THEME VARIABLES (PREMIUM REDESIGN) */
        [data-theme="light"] {
            --bg: #f3f4f6;
            --surface: #ffffff;
            --card: #ffffff;
            --card2: #f8fafc;
            --border: rgba(0, 0, 0, 0.06);
            --border-alpha: rgba(0, 0, 0, 0.08);
            --surface-alpha: rgba(0, 0, 0, 0.03);
            --accent: #6d28d9;
            --accent-glow: rgba(109, 40, 217, 0.12);
            --accent-dim: rgba(109, 40, 217, 0.08);
            --text: #374151;
            --muted: #6b7280;
            --glass: rgba(255, 255, 255, 0.7);
            --nav-bg: linear-gradient(to bottom, rgba(255, 255, 255, 0.8) 0%, transparent 100%);
            --header-text: #111827;
        }

        :root {
            /* DARK THEME (DEFAULT) */
            --bg: #07090f;
            --surface: #0d1117;
            --card: #0f1521;
            --card2: #111827;
            --border: rgba(255, 255, 255, 0.06);
            --border-alpha: rgba(255, 255, 255, 0.1);
            --surface-alpha: rgba(255, 255, 255, 0.04);
            --accent: #a78bfa;
            --accent-glow: rgba(167, 139, 250, 0.15);
            --accent-dim: rgba(167, 139, 250, 0.1);
            --text: #e2e8f0;
            --muted: #4b5a72;
            --glass: rgba(7, 9, 15, 0.4);
            --nav-bg: linear-gradient(to bottom, rgba(7, 9, 15, 0.8) 0%, transparent 100%);
            --header-text: #ffffff;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            transition: background 0.3s, color 0.3s;
            overflow-x: hidden;
            display: flex;
            flex-direction: column;
        }

        /* HEADER NAV (Transparent) */
        nav {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 100;
            padding: 0 40px;
            height: 70px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: var(--nav-bg);
            transition: all 0.4s ease;
        }

        nav.scrolled {
            background: var(--glass);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-bottom: 1px solid var(--border);
            height: 60px;
        }

        .nav-back {
            display: flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            color: var(--header-text);
            font-weight: 600;
            font-size: 0.85rem;
            transition: all 0.2s;
            padding: 6px 16px 6px 6px;
            border-radius: 999px;
            background: var(--glass);
            border: 1px solid var(--border);
            backdrop-filter: blur(8px);
        }

        .nav-logo {
            font-family: 'Syne', sans-serif;
            font-weight: 800;
            font-size: 1.3rem;
            color: var(--header-text);
            letter-spacing: -0.5px;
        }

        .nav-logo span {
            color: var(--accent);
        }

        /* HERO HEADER (Cinematic) */
        .header-hero {
            position: relative;
            width: 100%;
            height: 60vh;
            min-height: 400px;
            max-height: 550px;
            display: flex;
            align-items: flex-end;
            padding-bottom: 60px;
        }

        .hero-bg {
            position: absolute;
            inset: 0;
            z-index: 0;
            overflow: hidden;
        }

        .hero-bg img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            filter: brightness(0.6) saturate(1.2);
            /* animation: zoomBg 20s infinite alternate; */
        }

        .hero-bg::after {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(to top, var(--bg) 0%, rgba(0, 0, 0, 0.2) 50%, transparent 100%);
            transition: background 0.5s ease;
        }

        [data-theme="light"] .hero-bg::after {
            background: linear-gradient(to top, var(--bg) 0%, rgba(255, 255, 255, 0.4) 50%, transparent 100%);
        }

        .hero-content {
            position: relative;
            z-index: 10;
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 40px;
            display: flex;
            align-items: flex-end;
            gap: 40px;
        }

        /* Detail Poster Float */
        .poster-card {
            width: 240px;
            height: 320px;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.5);
            border: 1px solid rgba(255, 255, 255, 0.1);
            flex-shrink: 0;
            transform: translateY(40px);
            /* Bikin melayang turun dikit melewati hero */
            background: var(--surface);
        }

        .poster-card img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .header-info {
            flex: 1;
            padding-bottom: 20px;
        }

        .badge-status {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 0.65rem;
            font-weight: 700;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            color: var(--accent);
            border: 1px solid var(--accent-dim);
            padding: 5px 14px;
            border-radius: 4px;
            margin-bottom: 12px;
            background: rgba(200, 181, 255, 0.06);
            transform: skewX(-8deg);
        }

        .badge-status span {
            transform: skewX(8deg);
        }

        .badge-status::before {
            content: '';
            width: 5px;
            height: 5px;
            border-radius: 50%;
            background: var(--accent);
        }

        .title {
            font-family: 'Syne', sans-serif;
            font-size: clamp(2rem, 4vw, 3.2rem);
            font-weight: 800;
            line-height: 1.1;
            letter-spacing: -1px;
            color: var(--header-text);
            margin-bottom: 12px;
        }

        .artist {
            color: var(--muted);
            font-size: 1.1rem;
            margin-bottom: 24px;
            font-weight: 400;
        }

        .meta-strip {
            display: flex;
            gap: 24px;
            flex-wrap: wrap;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--muted);
            font-size: 0.85rem;
        }

        .meta-item svg {
            color: var(--accent);
        }

        /* MAIN CONTENT ROW */
        .content-wrap {
            max-width: 1200px;
            margin: 0 auto;
            padding: 60px 40px 100px;
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 50px;
            position: relative;
            z-index: 20;
        }

        .desc-section h3 {
            font-family: 'Syne', sans-serif;
            font-size: 1.3rem;
            color: var(--header-text);
            margin-bottom: 16px;
        }

        .desc-text {
            color: var(--muted);
            line-height: 1.7;
            font-size: 0.95rem;
            margin-bottom: 30px;
            white-space: pre-wrap;
        }

        .detail-box {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 30px;
        }

        .detail-row {
            display: flex;
            align-items: flex-start;
            gap: 16px;
            margin-bottom: 20px;
        }

        .detail-row:last-child {
            margin-bottom: 0;
        }

        .detail-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.03);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--accent);
            flex-shrink: 0;
        }

        .detail-data p.label {
            font-size: 0.75rem;
            color: var(--muted);
            margin-bottom: 4px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .detail-data p.value {
            font-size: 0.95rem;
            color: var(--header-text);
            font-weight: 500;
        }

        /* LINEUP SECTION (Spotify Style) */
        .lineup-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(110px, 1fr));
            gap: 24px;
            margin-top: 24px;
            margin-bottom: 40px;
        }

        .lineup-card {
            text-align: center;
        }

        .lineup-img-wrap {
            width: 110px;
            height: 110px;
            border-radius: 50%;
            overflow: hidden;
            margin: 0 auto 12px;
            background: rgba(255, 255, 255, 0.05);
            border: 2px solid transparent;
            transition: all 0.3s;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
        }

        .lineup-card:hover .lineup-img-wrap {
            border-color: var(--accent);
            transform: scale(1.05);
        }

        .lineup-img-wrap img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .lineup-name {
            font-family: 'Syne', sans-serif;
            font-size: 0.9rem;
            font-weight: 700;
            color: var(--header-text);
            margin-bottom: 2px;
        }

        .lineup-role {
            font-size: 0.7rem;
            color: var(--muted);
            letter-spacing: 0.05em;
            text-transform: uppercase;
        }

        /* SETLIST SECTION (Tracklist Style) */
        .setlist-wrap {
            margin-top: 24px;
            margin-bottom: 40px;
            border-top: 1px solid var(--border);
            padding-top: 16px;
        }

        .track-row {
            display: flex;
            align-items: center;
            padding: 12px 16px;
            border-radius: 8px;
            transition: all 0.2s;
            gap: 16px;
        }

        .track-row:hover {
            background: rgba(255, 255, 255, 0.03);
        }

        .track-num {
            width: 24px;
            font-size: 1rem;
            color: var(--muted);
            font-family: 'Syne', sans-serif;
            font-weight: 600;
            text-align: right;
        }

        .track-row:hover .track-num {
            color: var(--accent);
        }

        .track-title {
            font-size: 0.95rem;
            font-weight: 500;
            color: var(--header-text);
        }

        /* TIKET SECTION */
        .ticket-section h3 {
            font-family: 'Syne', sans-serif;
            font-size: 1.4rem;
            color: var(--header-text);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .ticket-list {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .ticket-card {
            background: transparent;
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 20px;
            display: flex;
            flex-direction: column;
            gap: 16px;
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
        }

        .ticket-card::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 4px;
            background: var(--cat-color, var(--border));
        }

        .ticket-card:hover {
            border-color: rgba(255, 255, 255, 0.2);
            background: rgba(255, 255, 255, 0.02);
        }

        .ticket-card.sold-out {
            opacity: 0.6;
            pointer-events: none;
        }

        .ticket-card.sold-out::after {
            content: 'HABIS';
            position: absolute;
            right: -30px;
            top: 16px;
            background: #ef4444;
            color: white;
            font-size: 0.6rem;
            font-weight: 800;
            padding: 4px 30px;
            transform: rotate(45deg);
            letter-spacing: 0.1em;
        }

        .tc-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }

        .tc-name {
            font-family: 'Syne', sans-serif;
            font-size: 1.15rem;
            font-weight: 700;
            color: var(--header-text);
            margin-bottom: 6px;
        }

        .tc-price {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--accent);
        }

        .tc-desc {
            font-size: 0.8rem;
            color: var(--muted);
        }

        .tc-bottom {
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-top: 1px solid var(--border);
            padding-top: 16px;
        }

        .tc-stock {
            font-size: 0.75rem;
            color: rgba(255, 255, 255, 0.5);
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .tc-stock.low {
            color: #f59e0b;
        }

        .tc-stock::before {
            content: '';
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: currentColor;
        }

        .btn-buy {
            background: var(--header-text);
            color: var(--bg);
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 0.8rem;
            cursor: pointer;
            transition: all 0.2s;
            font-family: 'Inter', sans-serif;
            clip-path: polygon(4px 0%, 100% 0%, calc(100% - 4px) 100%, 0% 100%);
        }

        .btn-buy:hover {
            background: var(--accent);
        }

        @media (max-width: 900px) {
            .content-wrap {
                grid-template-columns: 1fr;
                padding-top: 40px;
            }

            .hero-content {
                flex-direction: column;
                align-items: flex-start;
                padding-bottom: 20px;
                gap: 20px;
            }

            .poster-card {
                width: 140px;
                height: 180px;
                transform: translateY(0);
                display: none;
            }

            /* Hide on mobile, use hero bg */
            .header-hero {
                min-height: 500px;
                padding-bottom: 20px;
            }
        }

        /* TIMELINE UI */
        .timeline-container {
            margin-top: 30px;
            margin-bottom: 50px;
            position: relative;
            padding-left: 20px;
        }

        .timeline-item {
            position: relative;
            padding-left: 45px;
            padding-bottom: 35px;
            border-left: 2px solid var(--border);
            transition: all 0.3s;
        }

        .timeline-item:last-child {
            border-left-color: transparent;
            padding-bottom: 0;
        }

        .timeline-item::after {
            content: '';
            position: absolute;
            left: -8px;
            top: 0;
            width: 14px;
            height: 14px;
            border-radius: 50%;
            background: var(--bg);
            border: 3px solid var(--border);
            transition: all 0.3s;
            z-index: 2;
        }

        .timeline-item:hover::after {
            border-color: var(--accent);
            box-shadow: 0 0 15px var(--accent-glow);
            transform: scale(1.2);
        }

        .timeline-time {
            display: inline-block;
            font-size: 0.7rem;
            font-weight: 800;
            color: var(--accent);
            background: var(--accent-dim);
            padding: 4px 10px;
            border-radius: 6px;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .timeline-content {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 20px;
            transition: all 0.3s;
        }

        .timeline-item:hover .timeline-content {
            border-color: var(--accent-dim);
            background: var(--card2);
            transform: translateX(5px);
        }

        .timeline-band {
            font-family: 'Syne', sans-serif;
            font-size: 1.1rem;
            font-weight: 800;
            color: var(--header-text);
            margin-bottom: 4px;
        }

        .timeline-duration {
            font-size: 0.8rem;
            color: var(--muted);
            display: flex;
            align-items: center;
            gap: 6px;
        }

        /* MODAL LOGIN PROTECT */
        .modal-protect {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.85);
            backdrop-filter: blur(10px);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 10000;
            animation: fadeIn 0.3s ease;
        }

        .modal-protect.active {
            display: flex;
        }

        .mp-content {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 24px;
            padding: 40px;
            width: 90%;
            max-width: 420px;
            text-align: center;
            box-shadow: 0 30px 60px rgba(0, 0, 0, 0.5);
        }

        .mp-icon {
            width: 64px;
            height: 64px;
            background: var(--accent-dim);
            color: var(--accent);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px;
        }

        .mp-title {
            font-family: 'Syne', sans-serif;
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--header-text);
            margin-bottom: 12px;
        }

        .mp-desc {
            color: var(--muted);
            font-size: 0.95rem;
            margin-bottom: 30px;
            line-height: 1.6;
        }

        .mp-actions {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .btn-mp {
            padding: 14px;
            border-radius: 12px;
            font-weight: 700;
            font-size: 0.95rem;
            text-decoration: none;
            transition: 0.3s;
        }

        .btn-mp-login {
            background: var(--header-text);
            color: var(--bg);
        }

        .btn-mp-login:hover {
            background: var(--accent);
            color: white;
        }

        .btn-mp-reg {
            color: var(--muted);
            font-size: 0.85rem;
        }

        .btn-mp-reg span {
            color: var(--accent);
            text-decoration: underline;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        /* SLIDE PANEL MODALS */
        .slide-modal {
            position: fixed;
            inset: 0;
            z-index: 9998;
            pointer-events: none;
        }
        .slide-modal.active { pointer-events: auto; }

        .slide-modal-overlay {
            position: absolute;
            inset: 0;
            background: rgba(0,0,0,0.7);
            backdrop-filter: blur(8px);
            opacity: 0;
            transition: opacity 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .slide-modal.active .slide-modal-overlay { opacity: 1; }

        .slide-modal-content {
            position: absolute;
            top: 0;
            right: 0;
            bottom: 0;
            width: min(480px, 95vw);
            background: var(--surface);
            border-left: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            transform: translateX(100%);
            transition: transform 0.45s cubic-bezier(0.16, 1, 0.3, 1);
            box-shadow: -20px 0 60px rgba(0,0,0,0.4);
        }
        .slide-modal.active .slide-modal-content { transform: translateX(0); }

        .slide-modal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 28px 28px 20px;
            border-bottom: 1px solid var(--border);
        }
        .slide-modal-header h3 {
            font-family: 'Syne', sans-serif;
            font-size: 1.3rem;
            font-weight: 800;
            color: var(--header-text);
        }
        .btn-close-slide {
            width: 36px; height: 36px;
            border-radius: 50%;
            background: var(--card);
            border: 1px solid var(--border);
            color: var(--muted);
            font-size: 1.2rem;
            cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            transition: 0.2s;
        }
        .btn-close-slide:hover { background: var(--border); color: var(--header-text); }

        .slide-modal-body {
            flex: 1;
            overflow-y: auto;
            padding: 24px 28px;
            scrollbar-width: thin;
            scrollbar-color: var(--border) transparent;
        }
        .slide-modal-body::-webkit-scrollbar { width: 4px; }
        .slide-modal-body::-webkit-scrollbar-thumb { background: var(--border); border-radius: 10px; }

        /* Setlist band group */
        .setlist-band-group { margin-bottom: 32px; }
        .setlist-band-header {
            display: flex;
            align-items: center;
            gap: 10px;
            font-family: 'Syne', sans-serif;
            font-size: 1rem;
            font-weight: 800;
            color: var(--accent);
            margin-bottom: 14px;
            padding-bottom: 10px;
            border-bottom: 1px solid var(--border);
        }
        .setlist-track-row {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 12px 0;
            border-bottom: 1px solid var(--border);
            transition: 0.15s;
        }
        .setlist-track-row:last-child { border-bottom: none; }
        .setlist-track-row:hover { padding-left: 6px; }
        .setlist-track-num {
            width: 28px; height: 28px;
            border-radius: 8px;
            background: var(--accent-glow);
            color: var(--accent);
            font-size: 0.75rem;
            font-weight: 700;
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
        }
        .setlist-track-title {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--text);
        }

        .header-hero {
            height: 520px;
            position: relative;
            overflow: hidden;
            display: flex;
            align-items: flex-end;
            padding: 60px 8%;
            background: #000;
        }

        .hero-bg {
            position: absolute;
            inset: 0;
            z-index: 1;
        }

        .hero-bg img,
        .hero-bg video {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: all 1.8s cubic-bezier(0.16, 1, 0.3, 1);
            backface-visibility: hidden;
            perspective: 1000;
            transform: translateZ(0);
            /* Hardware acceleration */
        }

        .hero-bg img {
            opacity: 0.7;
            z-index: 1;
            filter: blur(0px) scale(1) contrast(1.05);
        }

        .hero-bg video {
            position: absolute;
            inset: 0;
            opacity: 0;
            z-index: 2;
            transform: translate3d(0, 0, 0);
            /* Force 3D Mode for Sharpness */
            -webkit-transform: translate3d(0, 0, 0);
            backface-visibility: hidden;
            -webkit-backface-visibility: hidden;
            transform-style: preserve-3d;
            filter: none;
        }

        /* PREVENT PIXELATION ON 4K */
        .hero-bg.video-loaded video {
            opacity: 1;
            image-rendering: -webkit-optimize-contrast;
            image-rendering: crisp-edges;
            filter: brightness(1.02) contrast(1.05);
            /* Subtle pop */
        }

        /* Make the image fade faster so it doesn't overlap shadow-wise */
        .hero-bg.video-loaded img {
            opacity: 0;
            transition: opacity 0.5s ease;
        }

        .hero-bg::after {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(to top, #07090f 5%, transparent 60%),
                linear-gradient(to right, #07090f 0%, transparent 40%);
            z-index: 2;
        }
    </style>
    </head>

    <body>

        <nav id="navbar">
            <a href="user_dashboard" class="nav-back">
                <div class="icon-wrap">
                    <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7" />
                    </svg>
                </div>
                Kembali ke Dashboard
            </a>
            <div class="nav-logo">Tix<span>Now</span></div>
            <div style="width: 140px;"></div> <!-- Spacer -->
        </nav>

        <div class="header-hero">
            <div class="hero-bg">
                <img src="<?php echo $bannerUrl; ?>" alt="Poster Fallback">
                <?php if (!empty($konser['video_url'])): ?>
                    <video id="heroVideo" src="<?php echo htmlspecialchars($konser['video_url']); ?>" autoplay muted loop
                        playsinline data-start="<?php echo $konser['video_start'] ?? 0; ?>"
                        data-end="<?php echo $konser['video_end'] ?? 0; ?>"></video>
                <?php endif; ?>
            </div>

            <div class="hero-content">
                <div class="poster-card">
                    <img src="<?php echo $posterUrl; ?>" alt="Poster">
                </div>
                <div class="header-info">
                    <div class="badge-status">
                        <span><?php echo strtoupper(htmlspecialchars($konser['status'])); ?></span></div>
                    <h1 class="title"><?php echo htmlspecialchars($konser['nama_event']); ?></h1>
                    <p class="artist"><?php echo htmlspecialchars($konser['artis']); ?></p>
                    <div class="meta-strip">
                        <div class="meta-item">
                            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                            <?php echo date('d M Y', strtotime($konser['tanggal'])); ?>
                        </div>
                        <div class="meta-item">
                            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            <?php echo htmlspecialchars($konser['venue']); ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="content-wrap">
            <div class="col-main">
                <div class="desc-section">
                    <h3>Informasi Event</h3>

                    <div class="detail-box">
                        <div class="detail-row">
                            <div class="detail-icon"><svg width="20" height="20" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg></div>
                            <div class="detail-data">
                                <p class="label">Waktu Pelaksanaan</p>
                                <p class="value">
                                    <?php
                                    echo $displayJamMulai . ' – ' . $displayJamSelesai . ' WIB<br>';
                                    echo date('l, d F Y', strtotime($konser['tanggal']));
                                    ?>
                                </p>
                            </div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-icon"><svg width="20" height="20" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg></div>
                            <div class="detail-data">
                                <p class="label">Lokasi Lengkap</p>
                                <p class="value"><?php echo htmlspecialchars($konser['venue']); ?><br>
                                    <span
                                        style="font-size:0.85rem;color:var(--muted); font-weight:normal;"><?php echo htmlspecialchars($konser['alamat']); ?></span>
                                </p>
                            </div>
                        </div>
                    </div>

                    <h3>Tentang Konser Ini</h3>
                    <div class="desc-text"><?php echo nl2br(htmlspecialchars($konser['deskripsi'])); ?></div>

                    <!-- TIMELINE SCHEDULE -->
                    <?php if (!empty($schedule)): ?>
                        <div style="margin-top: 40px; margin-bottom: 20px;">
                            <button onclick="document.getElementById('scheduleModal').classList.add('active');" 
                                    style="width:100%; padding: 18px 24px; border-radius: 16px; background: var(--accent-glow); border: 1px solid var(--accent); color: var(--accent); font-weight: 700; font-family: 'Syne', sans-serif; font-size: 1.1rem; cursor: pointer; display: flex; align-items: center; justify-content: space-between; transition: 0.3s;">
                                <span>Lihat Jadwal Tampil</span>
                                <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                            </button>
                        </div>
                    <?php endif; ?>

                    <!-- LINEUP -->
                    <?php if (!empty($lineup)): ?>
                        <h3>Lineup / Anggota Band</h3>
                        <div class="lineup-grid">
                            <?php foreach ($lineup as $m): ?>
                                <div class="lineup-card">
                                    <div class="lineup-img-wrap">
                                        <?php if (!empty($m['foto_url'])): ?>
                                            <img src="<?php echo htmlspecialchars($m['foto_url']); ?>"
                                                alt="<?php echo htmlspecialchars($m['nama_member']); ?>">
                                        <?php else: ?>
                                            <div
                                                style="width:100%; height:100%; display:flex; align-items:center; justify-content:center; color:var(--muted); font-size:0.7rem;">
                                                NO PIC</div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="lineup-name"><?php echo htmlspecialchars($m['nama_member']); ?></div>
                                    <div class="lineup-role"><?php echo htmlspecialchars($m['peran']); ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <!-- SETLIST -->
                    <?php if (!empty($setlist)): ?>
                        <div style="margin-top: 20px; margin-bottom: 40px;">
                            <button onclick="document.getElementById('setlistModal').classList.add('active');" 
                                    style="width:100%; padding: 18px 24px; border-radius: 16px; background: var(--card); border: 1px solid var(--border); color: var(--text); font-weight: 700; font-family: 'Syne', sans-serif; font-size: 1.1rem; cursor: pointer; display: flex; align-items: center; justify-content: space-between; transition: 0.3s;">
                                <span>Lihat Setlist Lagu</span>
                                <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3"/></svg>
                            </button>
                        </div>
                    <?php endif; ?>

                </div>
            </div>

            <div class="col-side">
                <div class="ticket-section">
                    <h3>Pilih Tiket</h3>

                    <?php if (empty($kategori)): ?>
                        <div
                            style="padding:40px 20px; text-align:center; background:var(--surface); border:1px solid var(--border); border-radius:12px;">
                            <p style="color:var(--muted); font-size:0.9rem;">Tiket belum tersedia atau penjualan belum
                                dimulai.</p>
                        </div>
                    <?php else: ?>
                        <?php
                        $grouped = [];
                        foreach ($kategori as $cat) {
                            $sid = $cat['id_schedule'] ?: 'general';
                            $grouped[$sid][] = $cat;
                        }
                        ?>
                        <?php foreach ($grouped as $sid => $tickets): ?>
                            <div class="ticket-group" style="margin-bottom: 30px;">
                                <h4
                                    style="font-size:0.75rem; color:var(--muted); margin-bottom:15px; text-transform:uppercase; letter-spacing:1.5px; display:flex; align-items:center; gap:10px;">
                                    <span style="flex:1; height:1px; background:var(--border);"></span>
                                    <?php
                                    if ($sid === 'general')
                                        echo 'Tiket Umum / Terusan';
                                    else {
                                        $bName = 'Sesi';
                                        foreach ($schedule as $sc)
                                            if ($sc['id'] == $sid)
                                                $bName = $sc['nama_band'];
                                        echo "Khusus: " . htmlspecialchars($bName);
                                    }
                                    ?>
                                    <span style="flex:1; height:1px; background:var(--border);"></span>
                                </h4>
                                <div class="ticket-list">
                                    <?php foreach ($tickets as $cat):
                                        $isSoldOut = $cat['kuota'] <= 0;
                                        $isLow = $cat['kuota'] > 0 && $cat['kuota'] <= 20;
                                        $color = !empty($cat['warna_kategori']) ? $cat['warna_kategori'] : '#ffffff';
                                        ?>
                                        <div class="ticket-card <?php echo $isSoldOut ? 'sold-out' : ''; ?>"
                                            style="--cat-color: <?php echo $color; ?>; margin-bottom:12px;">
                                            <div class="tc-header">
                                                <div>
                                                    <div class="tc-name"><?php echo htmlspecialchars($cat['nama_tiket']); ?></div>
                                                    <div class="tc-desc"><?php echo htmlspecialchars($cat['deskripsi']); ?></div>
                                                </div>
                                            </div>
                                            <div class="tc-price">Rp <?php echo number_format($cat['harga'], 0, ',', '.'); ?></div>
                                            <div class="tc-bottom">
                                                <div class="tc-stock <?php echo $isLow ? 'low' : ''; ?>">
                                                    <?php
                                                    if ($isSoldOut)
                                                        echo "Stok Habis";
                                                    else if ($isLow)
                                                        echo "Sisa " . $cat['kuota'] . " tiket";
                                                    else
                                                        echo "Tersedia";
                                                    ?>
                                                </div>
                                                <?php if (!$isSoldOut && !$isExpired): ?>
                                                    <button class="btn-buy"
                                                        onclick="handleBuyClick(<?php echo $cat['id_tiket'] ?? 0; ?>)">Beli
                                                        Tiket</button>
                                                <?php elseif ($isExpired): ?>
                                                    <button class="btn-buy" disabled
                                                        style="opacity:0.5; background:rgba(248,113,113,0.1); color:#f87171; border:1px solid rgba(248,113,113,0.2);">Event Berakhir</button>
                                                <?php else: ?>
                                                    <button class="btn-buy" disabled
                                                        style="opacity:0.5; background:var(--border); color:var(--muted);">Habis</button>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <script>
            (function () {
                const savedTheme = localStorage.getItem('theme') || 'dark';
                document.documentElement.setAttribute('data-theme', savedTheme);
            })();

            window.addEventListener('scroll', () => {
                const nav = document.getElementById('navbar');
                if (window.scrollY > 50) {
                    nav.classList.add('scrolled');
                } else {
                    nav.classList.remove('scrolled');
                }
            });

            const isLoggedIn = <?php echo $isLoggedIn ? 'true' : 'false'; ?>;

            function handleBuyClick(catId) {
                if (!isLoggedIn) {
                    document.getElementById('modalProtect').classList.add('active');
                    return;
                }
                window.location.href = 'gatekeeper?tiket_id=' + catId;
            }

            function closeProtectModal() {
                document.getElementById('modalProtect').classList.remove('active');
            }

            // HERO SEQUENCE LOGIC (IMAGE -> VIDEO -> REPEAT)
            const heroVideo = document.getElementById('heroVideo');
            if (heroVideo) {
                const heroBg = heroVideo.closest('.hero-bg');
                const vStart = parseFloat(heroVideo.getAttribute('data-start')) || 0;
                const vEnd = parseFloat(heroVideo.getAttribute('data-end')) || 0;
                let isVideoTurn = false;
                let cycleTimer;

                const startVideoCycle = () => {
                    isVideoTurn = true;
                    heroVideo.currentTime = vStart;

                    heroVideo.play().then(() => {
                        heroBg.classList.add('video-loaded'); // Switch to video effect
                    }).catch(e => {
                        console.log('Autoplay blocked');
                        switchToImage(); // Fallback to image
                    });
                };

                const switchToImage = () => {
                    isVideoTurn = false;
                    heroBg.classList.remove('video-loaded'); // Switch to image effect
                    heroVideo.pause();

                    // Wait 3 seconds before playing video again
                    setTimeout(startVideoCycle, 3000);
                };

                // Initial state: Show image for 3s
                setTimeout(startVideoCycle, 3000);

                heroVideo.addEventListener('timeupdate', () => {
                    if (isVideoTurn && vEnd > 0 && heroVideo.currentTime >= (vEnd - 0.15)) {
                        switchToImage();
                    }
                });

                heroVideo.addEventListener('ended', () => {
                    if (isVideoTurn) switchToImage();
                });

                // Safety check if it gets stuck
                setInterval(() => {
                    if (isVideoTurn && vEnd > 0 && (heroVideo.currentTime >= vEnd || heroVideo.currentTime < vStart - 0.3)) {
                        switchToImage();
                    }
                }, 1000);
            }

            heroVideo.addEventListener('error', (e) => {
                console.error('Video Error:', heroVideo.error);
            });
        </script>

        <!-- MODAL PROTECT -->
        <?php if (!$isLoggedIn): ?>
            <div class="modal-protect" id="modalProtect" onclick="if(event.target == this) closeProtectModal()">
                <div class="mp-content">
                    <div class="mp-icon">
                        <svg width="32" height="32" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                                d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                        </svg>
                    </div>
                    <div class="mp-title">Aksi Diperlukan</div>
                    <p class="mp-desc">Anda harus login terlebih dahulu untuk melanjutkan aksi ini dan mengamankan tiket
                        konsermu.</p>
                    <div class="mp-actions">
                        <a href="login" class="btn-mp btn-mp-login">Buka Halaman Login</a>
                        <a href="register" class="btn-mp btn-mp-reg">Belum punya akun? <span>Daftar di sini</span></a>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- SCHEDULE SLIDE PANEL -->
        <?php if (!empty($schedule)): ?>
            <div class="slide-modal" id="scheduleModal">
                <div class="slide-modal-overlay" onclick="closeScheduleSlide()"></div>
                <div class="slide-modal-content">
                    <div class="slide-modal-header">
                        <h3>🗓 Jadwal Tampil</h3>
                        <button class="btn-close-slide" onclick="closeScheduleSlide()">&times;</button>
                    </div>
                    <div class="slide-modal-body">
                        <div class="timeline-container" style="margin-top: 0;">
                            <?php foreach ($schedule as $row): ?>
                                <div class="timeline-item">
                                    <span class="timeline-time"><?php echo date('H:i', strtotime($row['jam_mulai'])); ?> – <?php echo date('H:i', strtotime($row['jam_selesai'])); ?></span>
                                    <div class="timeline-content">
                                        <div class="timeline-band"><?php echo htmlspecialchars($row['nama_band']); ?></div>
                                        <div class="timeline-duration">
                                            <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                            Penampilan Sesi Utama
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- SETLIST SLIDE PANEL -->
        <?php if (!empty($setlist)): ?>
            <div class="slide-modal" id="setlistModal">
                <div class="slide-modal-overlay" onclick="closeSetlistSlide()"></div>
                <div class="slide-modal-content">
                    <div class="slide-modal-header">
                        <h3>🎵 Setlist Lagu</h3>
                        <button class="btn-close-slide" onclick="closeSetlistSlide()">&times;</button>
                    </div>
                    <div class="slide-modal-body">
                        <?php foreach ($setlistByBand as $bandKey => $songs): ?>
                            <div class="setlist-band-group">
                                <div class="setlist-band-header">
                                    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3" />
                                    </svg>
                                    <?php
                                    if ($bandKey === '__general__')
                                        echo 'Setlist Umum';
                                    else
                                        echo htmlspecialchars($scheduleMap[$bandKey] ?? 'Band');
                                    ?>
                                </div>
                                <?php foreach ($songs as $i => $s): ?>
                                    <div class="setlist-track-row">
                                        <div class="setlist-track-num"><?php echo $s['urutan']; ?></div>
                                        <div class="setlist-track-title"><?php echo htmlspecialchars($s['judul_lagu']); ?></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <script>
            function closeScheduleSlide() {
                document.getElementById('scheduleModal').classList.remove('active');
            }
            function closeSetlistSlide() {
                document.getElementById('setlistModal').classList.remove('active');
            }
            // Close on Escape key
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') {
                    closeScheduleSlide();
                    closeSetlistSlide();
                }
            });
        </script>

    </body>

</html>