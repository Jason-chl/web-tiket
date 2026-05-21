<?php
session_start();
require_once 'koneksi.php';
require_once 'cleanup_orders.php';

if (!isset($_SESSION['id_user']) || $_SESSION['role'] !== 'user') {
    header("Location: index");
    exit;
}

// Ambil data lengkap user termasuk foto profil
$stmtUser = $conn->prepare("SELECT foto_profil, nama, username, is_vip FROM users WHERE id_user = ?");
$stmtUser->execute([$_SESSION['id_user']]);
$appUser = $stmtUser->fetch();

if (!$appUser) {
    session_destroy();
    header("Location: login?msg=Sesi berakhir, silakan login kembali.");
    exit;
}

$currentUser = $appUser;
$fotoProfil = $appUser['foto_profil'] ?? null;
$isVIP = ($appUser['is_vip'] ?? 0) == 1;
// Data retrieved above


$stmt = $conn->query("SELECT * FROM event WHERE status IN ('published', 'ongoing', 'completed') ORDER BY tanggal ASC");
$konser_list_raw = $stmt->fetchAll();

// Hitung status dinamis (UPCOMING / ONGOING / EXPIRED) per event
$now = new DateTime();
$konser_list = [];
foreach ($konser_list_raw as $k) {
    $tgl = $k['tanggal'];
    // Cari jam selesai dari schedule atau default +4 jam
    $stmtEnd = $conn->prepare("SELECT MAX(jam_selesai) FROM event_schedule WHERE id_event = ?");
    $stmtEnd->execute([$k['id_event']]);
    $jamSelesai = $stmtEnd->fetchColumn();
    
    $startDt = new DateTime($tgl);
    if ($jamSelesai) {
        $endDt = new DateTime(date('Y-m-d', strtotime($tgl)) . ' ' . $jamSelesai);
    } else {
        $endDt = clone $startDt;
        $endDt->modify('+4 hours');
    }
    
    if ($now < $startDt) {
        $k['computed_status'] = 'upcoming';
    } elseif ($now >= $startDt && $now <= $endDt) {
        $k['computed_status'] = 'ongoing';
    } else {
        $k['computed_status'] = 'expired';
    }
    $k['end_datetime'] = $endDt->format('Y-m-d H:i:s');
    $konser_list[] = $k;
}

$featured = array_filter($konser_list, fn($k) => $k['computed_status'] !== 'expired');
$featured = array_slice(array_values($featured), 0, 5);
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TixNow — Temukan Konsermu</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link
        href="https://fonts.googleapis.com/css2?family=Syne:wght@400;500;600;700;800&family=Inter:wght@300;400;500&display=swap"
        rel="stylesheet">
    <script>
        // IMMEDATE THEME LOAD
        (function () {
            const savedTheme = localStorage.getItem('theme') || 'dark';
            document.documentElement.setAttribute('data-theme', savedTheme);
        })();
    </script>
    <style>
        *,
        *::before,
        *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        :root {
            --bg: #07090f;
            --surface: #0d1117;
            --card: #0f1521;
            --card2: #111827;
            --border: rgba(255, 255, 255, 0.06);
            --border-alpha: rgba(255, 255, 255, 0.1);
            --surface-alpha: rgba(255, 255, 255, 0.04);
            --accent: #a78bfa;
            --accent-glow: rgba(167, 139, 250, 0.15);
            --red: #f87171;
            --green: #34d399;
            --text: #e2e8f0;
            --muted: #4b5a72;
            --header-text: #ffffff;
            --nav-bg: rgba(7, 9, 15, 0.85);
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
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
            --red: #ef4444;
            --red-glow: rgba(239, 68, 68, 0.08);
            --green: #10b981;
            --text: #374151;
            --muted: #6b7280;
            --header-text: #111827;
            --nav-bg: rgba(255, 255, 255, 0.75);
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            overflow-x: hidden;
            transition: var(--transition);
        }

        /* NAV */
        nav {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 100;
            padding: 0 40px;
            height: 64px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: var(--nav-bg);
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
            border-bottom: 1px solid var(--border);
            transition: all 0.3s ease;
        }

        .nav-logo {
            font-family: 'Syne', sans-serif;
            font-weight: 800;
            font-size: 1.4rem;
            letter-spacing: -0.5px;
            color: var(--header-text);
        }

        .nav-logo span {
            color: var(--accent);
        }

        .nav-links {
            display: flex;
            gap: 24px;
            margin-left: 40px;
        }

        .nav-links a {
            color: var(--muted);
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 500;
            transition: 0.2s;
        }

        .nav-links a:hover,
        .nav-links a.active {
            color: var(--header-text);
        }

        .nav-right {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .nav-user {
            font-size: 0.8rem;
            color: var(--muted);
        }

        .nav-user strong {
            color: var(--text);
            font-weight: 500;
        }

        .btn-logout {
            font-size: 0.8rem;
            padding: 8px 18px;
            border-radius: 999px;
            border: 1px solid var(--border);
            background: transparent;
            color: var(--muted);
            text-decoration: none;
            transition: all 0.2s;
            cursor: pointer;
        }

        .btn-logout:hover {
            color: var(--header-text);
            border-color: var(--accent);
            background: var(--accent-glow);
        }

        .nav-profile-link {
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            padding: 5px 14px 5px 5px;
            border-radius: 999px;
            border: 1px solid var(--border);
            transition: all 0.2s;
        }

        .nav-profile-link:hover {
            border-color: var(--accent);
            background: var(--accent-glow);
        }

        .nav-profile-link span {
            font-size: 0.8rem;
            color: var(--muted);
            transition: 0.2s;
        }

        .nav-profile-link:hover span {
            color: var(--header-text);
        }

        /* THEME TOGGLE */
        .theme-toggle {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: rgba(0, 0, 0, 0.03);
            border: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            color: var(--header-text);
            transition: 0.3s;
            position: relative;
        }

        [data-theme="dark"] .theme-toggle {
            background: rgba(255, 255, 255, 0.05);
        }

        .theme-toggle:hover {
            background: var(--accent);
            color: white;
            border-color: var(--accent);
        }

        /* ── HERO CAROUSEL ───────────────── */
        .hero {
            margin-top: 64px;
            position: relative;
            height: 88vh;
            min-height: 540px;
            overflow: hidden;
            background: var(--bg);
            transition: background 0.5s ease;
        }

        /* Slides stack di atas satu sama lain, fade in/out */
        .carousel-slide {
            position: absolute;
            inset: 0;
            opacity: 0;
            transition: opacity 0.9s ease;
            pointer-events: none;
        }

        .carousel-slide.active {
            opacity: 1;
            pointer-events: auto;
        }

        /* BG Gambar full */
        .slide-bg {
            position: absolute;
            inset: 0;
        }

        .slide-bg img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            filter: brightness(0.5) saturate(1.1);
            transform: scale(1.06);
            transition: transform 7s ease, filter 0.5s ease;
        }

        [data-theme="light"] .slide-bg img {
            filter: brightness(0.65) saturate(1.1);
        }

        .carousel-slide.active .slide-bg img {
            transform: scale(1);
        }

        /* Multi-layer gradient - THEME AWARE */
        .slide-bg::after {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(to top, var(--bg) 0%, transparent 60%),
                linear-gradient(to right, var(--bg) 0%, transparent 50%);
            transition: background 0.5s ease;
        }

        [data-theme="light"] .slide-bg::after {
            background: linear-gradient(to top, rgba(226, 232, 240, 0.6) 0%, rgba(226, 232, 240, 0.2) 80%, transparent 100%),
                linear-gradient(to right, rgba(226, 232, 240, 0.4) 5%, transparent 60%) !important;
        }

        /* INFO PANEL — miring ke kiri (skew) */
        .slide-info {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            padding: 60px 72px 70px;
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: 40px;
        }

        .slide-info-left {
            max-width: 600px;
            transform: translateY(10px);
            opacity: 0;
            transition: transform 0.7s ease 0.2s, opacity 0.7s ease 0.2s;
        }

        .carousel-slide.active .slide-info-left {
            transform: translateY(0);
            opacity: 1;
        }

        .slide-info-right {
            flex-shrink: 0;
            transform: translateY(10px);
            opacity: 0;
            transition: transform 0.7s ease 0.35s, opacity 0.7s ease 0.35s;
            text-align: right;
        }

        .carousel-slide.active .slide-info-right {
            transform: translateY(0);
            opacity: 1;
        }

        /* Badge status */
        .slide-badge {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            font-size: 0.62rem;
            font-weight: 700;
            letter-spacing: 0.15em;
            text-transform: uppercase;
            color: var(--accent);
            border: 1px solid rgba(200, 181, 255, 0.3);
            padding: 5px 14px;
            border-radius: 4px;
            margin-bottom: 16px;
            background: rgba(200, 181, 255, 0.06);
            width: fit-content;
            /* MIRING ke kanan */
            transform: skewX(-8deg);
        }

        .slide-badge span {
            transform: skewX(8deg);
            display: inline-block;
        }

        .slide-badge::before {
            content: '';
            width: 5px;
            height: 5px;
            border-radius: 50%;
            background: var(--accent);
            flex-shrink: 0;
        }

        .slide-title {
            font-family: 'Syne', sans-serif;
            font-size: clamp(2.2rem, 5.5vw, 4rem);
            font-weight: 800;
            line-height: 1.02;
            letter-spacing: -2px;
            color: var(--header-text);
            margin-bottom: 12px;
        }

        .slide-artist {
            font-size: 0.95rem;
            color: var(--muted);
            margin-bottom: 22px;
        }

        .slide-meta {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            margin-bottom: 28px;
        }

        .slide-meta-item {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.77rem;
            color: var(--muted);
        }

        /* Tombol CTA pakai pseudo-element miring */
        .btn-detail {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: linear-gradient(to right, var(--accent) 50%, var(--header-text) 50%);
            background-size: 200% 100%;
            background-position: right bottom;
            color: var(--bg);
            padding: 13px 28px;
            font-weight: 700;
            font-size: 0.83rem;
            text-decoration: none;
            transition: all 0.4s cubic-bezier(0.23, 1, 0.32, 1);
            clip-path: polygon(4px 0%, 100% 0%, calc(100% - 4px) 100%, 0% 100%);
        }

        .btn-detail:hover {
            background-position: left bottom;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 10px 20px -10px var(--accent);
        }

        .btn-detail span {
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-detail:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 20px -5px var(--accent);
        }

        position: relative;
        overflow: hidden;
        z-index: 1;
        }

        .btn-detail::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            bottom: 0;
            right: 0;
            background: var(--accent);
            transform: translateX(-101%);
            transition: transform 0.4s cubic-bezier(0.77, 0, 0.18, 1);
            z-index: -1;
        }

        .btn-detail:hover {
            color: white;
            padding-right: 36px;
        }

        .btn-detail:hover::before {
            transform: translateX(0);
        }

        .btn-detail svg {
            transition: transform 0.4s cubic-bezier(0.77, 0, 0.18, 1);
        }

        .btn-detail:hover svg {
            transform: translateX(6px);
        }

        /* Progress bar tipis di bawah */
        .slide-progress {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: var(--border);
            z-index: 10;
        }

        .slide-progress-bar {
            height: 100%;
            background: var(--accent);
            width: 0%;
            transition: width linear;
        }

        /* Thumbnail strip kanan bawah */
        .slide-thumbs {
            display: flex;
            gap: 10px;
            flex-direction: column;
        }

        .slide-thumb {
            width: 6px;
            height: 40px;
            border-radius: 3px;
            background: rgba(255, 255, 255, 0.12);
            cursor: pointer;
            transition: all 0.35s;
            border: none;
            flex-shrink: 0;
        }

        [data-theme="light"] .slide-thumb {
            background: rgba(0, 0, 0, 0.1);
        }

        .slide-thumb.active {
            background: var(--accent);
            height: 60px;
        }

        /* Nomor slide kiri bawah */
        .slide-num {
            font-family: 'Syne', sans-serif;
            font-size: 0.7rem;
            letter-spacing: 0.1em;
            color: var(--muted);
            margin-bottom: 12px;
        }

        .slide-num strong {
            color: var(--header-text);
            font-size: 1rem;
        }

        /* Nav arrows pojok atas kanan */
        .carousel-nav {
            position: absolute;
            top: 36px;
            right: 48px;
            z-index: 20;
            display: flex;
            gap: 8px;
        }

        .carousel-btn {
            width: 40px;
            height: 40px;
            border-radius: 2px;
            background: var(--surface-alpha);
            border: 1px solid var(--border-alpha);
            color: var(--muted);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
            clip-path: polygon(4px 0%, 100% 0%, calc(100% - 4px) 100%, 0% 100%);
        }

        .carousel-btn:hover {
            background: var(--accent);
            color: white;
            border-color: var(--accent);
        }

        .hero-empty {
            margin-top: 64px;
            height: 260px;
            background: var(--surface);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        @media (max-width: 768px) {
            .slide-info {
                padding: 40px 24px 56px;
                flex-direction: column;
                align-items: flex-start;
                gap: 20px;
            }

            .slide-info-right {
                display: none;
            }

            .carousel-nav {
                top: 20px;
                right: 20px;
            }

            nav {
                padding: 0 20px;
            }

            section {
                padding: 60px 20px;
            }
        }

        /* ── SECTION & CARDS ─────────────────────────────── */
        section {
            padding: 80px 40px;
        }

        .section-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 40px;
            flex-wrap: wrap;
            gap: 20px;
        }

        .section-title {
            font-family: 'Syne', sans-serif;
            font-size: clamp(1.5rem, 3vw, 2rem);
            font-weight: 700;
            letter-spacing: -0.5px;
            color: var(--header-text);
        }

        .section-title span {
            color: var(--accent);
        }

        .section-count {
            font-size: 0.8rem;
            color: var(--muted);
        }

        .grid-events {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }

        .event-card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 16px;
            overflow: hidden;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            cursor: pointer;
            display: flex;
            flex-direction: column;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        }

        .event-card:hover {
            transform: translateY(-8px);
            border-color: var(--accent);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
        }

        .card-image {
            position: relative;
            height: 200px;
            overflow: hidden;
            background: var(--surface);
        }

        .card-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.6s ease;
        }

        .event-card:hover .card-image img {
            transform: scale(1.06);
        }

        .card-status {
            position: absolute;
            top: 14px;
            right: 14px;
            font-size: 0.72rem;
            font-weight: 800;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            padding: 6px 14px;
            border-radius: 10px;
            backdrop-filter: blur(20px);
            z-index: 10;
            box-shadow: 0 8px 16px rgba(0,0,0,0.25);
            text-shadow: 0 1px 2px rgba(0,0,0,0.3);
        }

        .status-upcoming {
            background: rgba(7, 24, 46, 0.75);
            color: #82cfff;
            border: 1px solid rgba(130, 207, 255, 0.4);
        }

        .status-ongoing {
            background: rgba(10, 41, 26, 0.75);
            color: #4ade80;
            border: 1px solid rgba(74, 222, 128, 0.4);
        }

        .status-expired {
            background: rgba(45, 12, 12, 0.75);
            color: #f87171;
            border: 1px solid rgba(248, 113, 113, 0.4);
        }

        /* FILTER TABS — Premium Segmented Bar */
        .filter-bar {
            display: flex;
            align-items: center;
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 5px;
            gap: 2px;
        }

        .filter-tab {
            position: relative;
            display: flex;
            align-items: center;
            gap: 7px;
            padding: 8px 16px;
            border-radius: 10px;
            border: none;
            background: transparent;
            color: var(--muted);
            font-size: 0.78rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            font-family: 'Inter', sans-serif;
            letter-spacing: 0.01em;
            white-space: nowrap;
        }

        .filter-tab:hover:not(.active) {
            background: var(--surface-alpha);
            color: var(--text);
        }

        .filter-tab.active {
            color: var(--header-text);
            background: var(--surface);
            box-shadow: 0 2px 8px rgba(0,0,0,0.12), 0 0 0 1px var(--border);
        }

        /* Dot indicators */
        .filter-dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            flex-shrink: 0;
            transition: transform 0.25s;
        }
        .filter-tab.active .filter-dot { transform: scale(1.3); }
        .dot-all    { background: var(--accent); }
        .dot-upcoming { background: #7dd3fc; }
        .dot-ongoing  { background: #4ade80; }
        .dot-expired  { background: #f87171; }

        /* Count badge */
        .filter-count {
            font-size: 0.65rem;
            font-weight: 700;
            padding: 1px 6px;
            border-radius: 999px;
            background: var(--surface-alpha);
            color: var(--muted);
            transition: all 0.25s;
            min-width: 20px;
            text-align: center;
        }
        .filter-tab.active .filter-count {
            background: var(--accent-glow);
            color: var(--accent);
        }
        .filter-tab.active[data-filter="upcoming"] .filter-count { background: rgba(125,211,252,0.15); color: #7dd3fc; }
        .filter-tab.active[data-filter="ongoing"]  .filter-count { background: rgba(74,222,128,0.15);  color: #4ade80; }
        .filter-tab.active[data-filter="expired"]  .filter-count { background: rgba(248,113,113,0.15); color: #f87171; }

        .event-card.hidden { display: none; }

        /* Empty state saat filter tidak ada hasilnya */
        .filter-empty {
            display: none;
            grid-column: 1 / -1;
            text-align: center;
            padding: 60px 20px;
            color: var(--muted);
        }
        .filter-empty.show { display: block; }
        .filter-empty-icon {
            width: 64px; height: 64px;
            border-radius: 20px;
            background: var(--surface);
            border: 1px solid var(--border);
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 20px;
            font-size: 1.6rem;
        }
        .filter-empty h4 {
            font-family: 'Syne', sans-serif;
            font-size: 1.15rem;
            font-weight: 700;
            color: var(--header-text);
            margin-bottom: 6px;
        }
        .filter-empty p {
            font-size: 0.83rem;
            color: var(--muted);
        }

        .card-body {
            padding: 24px;
            flex: 1;
            display: flex;
            flex-direction: column;
            background: linear-gradient(to bottom, var(--card), var(--surface-alpha));
        }

        .card-date {
            font-size: 0.75rem;
            font-weight: 800;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            color: var(--accent);
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .card-title {
            font-family: 'Syne', sans-serif;
            font-size: 1.25rem;
            font-weight: 800;
            color: var(--header-text);
            margin-bottom: 6px;
            line-height: 1.2;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            letter-spacing: -0.02em;
        }

        .card-artist {
            font-size: 0.85rem;
            font-weight: 500;
            color: var(--text);
            opacity: 0.7;
            margin-bottom: 16px;
        }

        .card-venue {
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--text);
            opacity: 0.8;
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: auto;
            padding-top: 16px;
            border-top: 1px solid var(--border);
        }

        .empty-state {
            text-align: center;
            padding: 80px 20px;
            color: var(--muted);
        }

        .empty-state h3 {
            font-family: 'Syne', sans-serif;
            font-size: 1.4rem;
            color: var(--header-text);
            margin-bottom: 8px;
            margin-top: 20px;
        }

        .placeholder-bg {
            width: 100%;
            height: 100%;
            background: var(--surface);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .vip-badge {
            background: linear-gradient(135deg, #ffd700, #ffae00);
            color: #000;
            font-size: 0.65rem;
            font-weight: 800;
            padding: 2px 7px;
            border-radius: 4px;
            margin-left: 6px;
            text-transform: uppercase;
            box-shadow: 0 0 10px rgba(255, 215, 0, 0.3);
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .nav-vip-btn {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 7px 16px;
            border-radius: 999px;
            background: linear-gradient(135deg, rgba(255, 215, 0, 0.12), rgba(255, 174, 0, 0.06));
            border: 1px solid rgba(255, 215, 0, 0.4);
            color: #ffd700;
            font-weight: 700;
            font-size: 0.78rem;
            text-decoration: none;
            transition: all 0.25s;
            white-space: nowrap;
            box-shadow: 0 0 14px rgba(255, 215, 0, 0.12), inset 0 1px 0 rgba(255, 255, 255, 0.05);
            letter-spacing: 0.3px;
            animation: vip-pulse 2.5s ease-in-out infinite;
        }

        @keyframes vip-pulse {

            0%,
            100% {
                box-shadow: 0 0 14px rgba(255, 215, 0, 0.12), inset 0 1px 0 rgba(255, 255, 255, 0.05);
            }

            50% {
                box-shadow: 0 0 22px rgba(255, 215, 0, 0.28), inset 0 1px 0 rgba(255, 255, 255, 0.05);
            }
        }

        .nav-vip-btn:hover {
            background: linear-gradient(135deg, rgba(255, 215, 0, 0.22), rgba(255, 174, 0, 0.14));
            border-color: rgba(255, 215, 0, 0.7);
            box-shadow: 0 0 28px rgba(255, 215, 0, 0.3);
            transform: translateY(-1px);
            animation: none;
        }

        /* === VIP SUCCESS MODAL === */
        .vip-modal-overlay {
            position: fixed;
            inset: 0;
            z-index: 9999;
            background: rgba(0, 0, 0, 0.75);
            backdrop-filter: blur(10px);
            display: none;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .vip-modal-overlay.active {
            display: flex;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }

            to {
                opacity: 1;
            }
        }

        .vip-modal {
            background: var(--surface);
            border: 1px solid rgba(255, 215, 0, 0.25);
            border-radius: 28px;
            padding: 48px 40px;
            max-width: 420px;
            width: 100%;
            text-align: center;
            position: relative;
            overflow: hidden;
            animation: slideUp 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(40px) scale(0.95);
            }

            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .vip-modal::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 1px;
            background: linear-gradient(90deg, transparent, #ffd700, transparent);
        }

        .vip-modal-glow {
            position: absolute;
            top: -60px;
            left: 50%;
            transform: translateX(-50%);
            width: 240px;
            height: 240px;
            background: radial-gradient(ellipse, rgba(255, 215, 0, 0.12) 0%, transparent 70%);
            pointer-events: none;
        }

        .vip-modal-icon {
            width: 88px;
            height: 88px;
            border-radius: 28px;
            background: linear-gradient(135deg, #ffd700, #ffae00);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px;
            box-shadow: 0 0 40px rgba(255, 215, 0, 0.4), 0 16px 32px rgba(255, 174, 0, 0.2);
            animation: iconBounce 0.6s cubic-bezier(0.34, 1.56, 0.64, 1) 0.2s both;
        }

        @keyframes iconBounce {
            from {
                transform: scale(0);
            }

            to {
                transform: scale(1);
            }
        }

        .vip-modal h2 {
            font-family: 'Syne', sans-serif;
            font-size: 1.7rem;
            font-weight: 900;
            margin-bottom: 10px;
            background: linear-gradient(135deg, #fff 0%, #ffd700 60%, #ffae00 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        [data-theme='light'] .vip-modal h2 {
            background: linear-gradient(135deg, #1e293b, #b45309);
            -webkit-background-clip: text;
            background-clip: text;
        }

        .vip-modal p {
            color: var(--muted);
            font-size: 0.9rem;
            line-height: 1.65;
            margin-bottom: 28px;
        }

        .vip-modal-perks {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-bottom: 28px;
            text-align: left;
        }

        .vip-modal-perk {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.84rem;
            color: var(--text);
        }

        .vip-modal-perk svg {
            color: #34d399;
            flex-shrink: 0;
        }

        .vip-modal-btn {
            width: 100%;
            padding: 15px;
            border-radius: 14px;
            border: none;
            background: linear-gradient(135deg, #ffd700, #ffae00);
            color: #000;
            font-family: 'Syne', sans-serif;
            font-weight: 800;
            font-size: 0.95rem;
            cursor: pointer;
            transition: all 0.3s;
            box-shadow: 0 10px 25px rgba(255, 174, 0, 0.25);
        }

        .vip-modal-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 35px rgba(255, 174, 0, 0.35);
        }

        /* Confetti */
        .confetti-piece {
            position: absolute;
            width: 8px;
            height: 8px;
            border-radius: 2px;
            animation: confettiFall linear forwards;
            opacity: 1;
        }

        @keyframes confettiFall {
            0% {
                transform: translateY(-20px) rotate(0deg);
                opacity: 1;
            }

            100% {
                transform: translateY(500px) rotate(720deg);
                opacity: 0;
            }
        }
    </style>
</head>

<body>

    <!-- VIP SUCCESS MODAL -->
    <div class="vip-modal-overlay" id="vipSuccessModal">
        <div class="vip-modal" id="vipModalBox">
            <div class="vip-modal-glow"></div>
            <div class="vip-modal-icon">
                <svg width="44" height="44" viewBox="0 0 20 20" fill="#000">
                    <path d="M10 1l2.39 5.26L18 7.27l-4 3.89.94 5.5L10 14l-4.94 2.66.94-5.5L2 7.27l5.61-.01L10 1z" />
                </svg>
            </div>
            <h2>Selamat, Kamu Kini VIP! 🎉</h2>
            <p>Status VIP Fast Track aktif di akun kamu. Mulai sekarang, kamu bisa melewati semua antrean dan langsung
                memilih tiket.</p>
            <div class="vip-modal-perks">
                <div class="vip-modal-perk">
                    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                    </svg>
                    Skip Waiting Room di semua event
                </div>
                <div class="vip-modal-perk">
                    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                    </svg>
                    Badge VIP emas aktif di profilmu
                </div>
                <div class="vip-modal-perk">
                    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                    </svg>
                    Prioritas akses tiket saat War berlangsung
                </div>
            </div>
            <button class="vip-modal-btn" onclick="closeVipModal()">Mulai Jelajahi Konser ✨</button>
        </div>
    </div>

    <nav>
        <div style="display:flex; align-items:center; gap: 24px;">
            <div class="nav-logo">Tix<span>Now</span></div>
            <div class="nav-links">
                <a href="user_dashboard" class="active">Konser</a>
                <a href="user_orders">Pesanan Saya</a>
            </div>
            <?php if (!$isVIP): ?>
                <a href="buy_vip" class="nav-vip-btn">
                    <svg width="14" height="14" viewBox="0 0 20 20" fill="currentColor">
                        <path d="M10 1l2.39 5.26L18 7.27l-4 3.89.94 5.5L10 14l-4.94 2.66.94-5.5L2 7.27l5.61-.01L10 1z" />
                    </svg>
                    Upgrade VIP
                </a>
            <?php endif; ?>
        </div>
        <div class="nav-right">
            <div class="theme-toggle" id="themeToggle" title="Pindah Tema">
                <svg id="moonIcon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                    stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path>
                </svg>
                <svg id="sunIcon" style="display:none;" width="18" height="18" viewBox="0 0 24 24" fill="none"
                    stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="5"></circle>
                    <line x1="12" y1="1" x2="12" y2="3"></line>
                    <line x1="12" y1="21" x2="12" y2="23"></line>
                    <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line>
                    <line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line>
                    <line x1="1" y1="12" x2="3" y2="12"></line>
                    <line x1="21" y1="12" x2="23" y2="12"></line>
                    <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line>
                    <line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line>
                </svg>
            </div>
            <a href="profile" class="nav-profile-link">
                <?php if ($fotoProfil && file_exists($fotoProfil)): ?>
                    <img src="<?php echo htmlspecialchars($fotoProfil); ?>?v=<?php echo time(); ?>"
                        style="width:30px;height:30px;border-radius:50%;object-fit:cover;border:2px solid var(--accent-glow);"
                        alt="">
                <?php else: ?>
                    <div
                        style="width:30px;height:30px;border-radius:50%;background:var(--accent-glow);border:2px solid var(--border);display:flex;align-items:center;justify-content:center;font-family:'Syne',sans-serif;font-size:0.75rem;font-weight:700;color:var(--accent);">
                        <?php echo strtoupper(substr($appUser['username'] ?? $_SESSION['username'] ?? 'U', 0, 1)); ?></div>
                <?php endif; ?>
                <span>@<?php echo htmlspecialchars($appUser['username'] ?? $_SESSION['username'] ?? 'User'); ?></span>
                <?php if ($isVIP): ?>
                    <span class="vip-badge">VIP</span>
                <?php endif; ?>
            </a>
            <a href="logout" class="btn-logout">Keluar</a>
        </div>
    </nav>

    <?php if (!empty($featured)): ?>
        <div class="hero">

            <!-- Nav arrows pojok atas kanan -->
            <div class="carousel-nav">
                <button class="carousel-btn" onclick="moveCarousel(-1)" title="Sebelumnya">
                    <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7" />
                    </svg>
                </button>
                <button class="carousel-btn" onclick="moveCarousel(1)" title="Berikutnya">
                    <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7" />
                    </svg>
                </button>
            </div>

            <?php foreach ($featured as $idx => $f):
                $img = !empty($f['poster_url']) ? htmlspecialchars($f['poster_url']) : 'https://images.unsplash.com/photo-1540039155732-68b2dbceaebd?q=80&w=2000&auto=format&fit=crop';
                ?>
                <div class="carousel-slide <?php echo $idx === 0 ? 'active' : ''; ?>">
                    <!-- BG Gambar -->
                    <div class="slide-bg">
                        <img src="<?php echo $img; ?>" alt="<?php echo htmlspecialchars($f['nama_event']); ?>">
                    </div>

                    <!-- Info panel bawah -->
                    <div class="slide-info">
                        <div class="slide-info-left">
                            <div class="slide-num">
                                <strong><?php echo str_pad($idx + 1, 2, '0', STR_PAD_LEFT); ?></strong> /
                                <?php echo str_pad(count($featured), 2, '0', STR_PAD_LEFT); ?>
                            </div>
                            <div class="slide-badge"><span><?php echo strtoupper(htmlspecialchars($f['status'])); ?></span>
                            </div>
                            <h2 class="slide-title"><?php echo htmlspecialchars($f['nama_event']); ?></h2>
                            <p class="slide-artist"><?php echo htmlspecialchars($f['artis']); ?></p>
                            <div class="slide-meta">
                                <div class="slide-meta-item">
                                    <svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                    <?php echo date('d M Y', strtotime($f['tanggal'])); ?>
                                </div>
                                <div class="slide-meta-item">
                                    <svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                    </svg>
                                    <?php echo htmlspecialchars($f['venue']); ?>
                                </div>
                            </div>
                            <a href="detail_konser?id=<?php echo $f['id_event']; ?>" class="btn-detail">
                                Lihat Detail
                                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7" />
                                </svg>
                            </a>
                        </div>
                        <div class="slide-info-right">
                            <div class="slide-thumbs" id="thumbs">
                                <?php foreach ($featured as $ti => $tf): ?>
                                    <button class="slide-thumb <?php echo $ti === $idx ? ($idx === 0 ? 'active' : '') : ''; ?>"
                                        onclick="goToSlide(<?php echo $ti; ?>)"></button>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>

            <!-- Progress bar bawah (hanya jika lebih dari 1 event) -->
            <?php if (count($featured) > 1): ?>
                <div class="slide-progress">
                    <div class="slide-progress-bar" id="progressBar"></div>
                </div>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="hero-empty">
            <p style="color:var(--muted);font-size:0.9rem;">Belum ada event yang dipublikasikan.</p>
        </div>
    <?php endif; ?>

    <section id="all-events">
        <div class="section-header">
            <div>
                <h2 class="section-title">Semua <span>Event</span></h2>
            </div>
            <div class="filter-bar">
                <button class="filter-tab active" data-filter="all" onclick="filterEvents('all', this)">
                    <span class="filter-dot dot-all"></span>
                    Semua
                    <span class="filter-count" id="cnt-all">0</span>
                </button>
                <button class="filter-tab" data-filter="upcoming" onclick="filterEvents('upcoming', this)">
                    <span class="filter-dot dot-upcoming"></span>
                    Upcoming
                    <span class="filter-count" id="cnt-upcoming">0</span>
                </button>
                <button class="filter-tab" data-filter="ongoing" onclick="filterEvents('ongoing', this)">
                    <span class="filter-dot dot-ongoing"></span>
                    Ongoing
                    <span class="filter-count" id="cnt-ongoing">0</span>
                </button>
                <button class="filter-tab" data-filter="expired" onclick="filterEvents('expired', this)">
                    <span class="filter-dot dot-expired"></span>
                    Expired
                    <span class="filter-count" id="cnt-expired">0</span>
                </button>
            </div>
        </div>

        <?php if (!empty($konser_list)): ?>
            <div class="grid-events" id="eventsGrid">
                <?php foreach ($konser_list as $row):
                    $posterUrl = !empty($row['poster_url']) ? htmlspecialchars($row['poster_url']) : null;
                    $cs = $row['computed_status'];
                    $statusLabel = strtoupper($cs);
                    $statusClass = 'status-' . $cs;
                    ?>
                    <div class="event-card" data-status="<?php echo $cs; ?>" onclick="window.location.href='detail_konser?id=<?php echo $row['id_event']; ?>'">
                        <div class="card-image">
                            <?php if ($posterUrl): ?>
                                <img src="<?php echo $posterUrl; ?>" alt="<?php echo htmlspecialchars($row['nama_event']); ?>"
                                    loading="lazy">
                            <?php else: ?>
                                <div class="placeholder-bg">
                                    <svg width="40" height="40" fill="none" stroke="rgba(255,255,255,0.1)" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                            d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3" />
                                    </svg>
                                </div>
                            <?php endif; ?>
                            <div class="card-status <?php echo $statusClass; ?>"><?php echo $statusLabel; ?></div>
                        </div>
                        <div class="card-body">
                            <div class="card-date"><?php echo date('d M Y', strtotime($row['tanggal'])); ?></div>
                            <h3 class="card-title"><?php echo htmlspecialchars($row['nama_event']); ?></h3>
                            <p class="card-artist"><?php echo htmlspecialchars($row['artis']); ?></p>
                            <div class="card-venue">
                                <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                                <?php echo htmlspecialchars($row['venue']); ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
                <!-- Empty state saat filter aktif -->
                <div class="filter-empty" id="filterEmpty">
                    <div class="filter-empty-icon">🎫</div>
                    <h4>Tidak ada event di kategori ini</h4>
                    <p>Coba pilih filter lain atau tunggu event baru dirilis.</p>
                </div>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <svg width="48" height="48" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                    style="opacity:0.2; margin: 0 auto;">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                        d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3" />
                </svg>
                <h3>Belum Ada Event</h3>
                <p style="font-size:0.85rem;">Event baru akan muncul di sini saat sudah dipublish.</p>
            </div>
        <?php endif; ?>
    </section>

    <script>
        const slides = document.querySelectorAll('.carousel-slide');
        const progressBar = document.getElementById('progressBar');
        const DURATION = 5500;
        let current = 0;
        let autoTimer, progressTimer;

        function goToSlide(n) {
            slides[current].classList.remove('active');
            current = (n + slides.length) % slides.length;
            slides[current].classList.add('active');

            // Update status thumbnail garis di setiap container frame
            const thumbsContainers = document.querySelectorAll('.slide-thumbs');
            thumbsContainers.forEach(container => {
                const thumbs = container.querySelectorAll('.slide-thumb');
                thumbs.forEach((thumb, index) => {
                    thumb.classList.toggle('active', index === current);
                });
            });

            resetProgress();
            resetTimer();
        }

        function moveCarousel(dir) { goToSlide(current + dir); }

        function resetProgress() {
            if (!progressBar) return;
            progressBar.style.transition = 'none';
            progressBar.style.width = '0%';
            setTimeout(() => {
                progressBar.style.transition = `width ${DURATION}ms linear`;
                progressBar.style.width = '100%';
            }, 30);
        }

        function resetTimer() {
            clearInterval(autoTimer);
            autoTimer = setInterval(() => moveCarousel(1), DURATION);
        }

        // Filter events
        function filterEvents(filter, btn) {
            document.querySelectorAll('.filter-tab').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');

            let visibleCount = 0;
            document.querySelectorAll('.event-card').forEach(card => {
                const status = card.getAttribute('data-status');
                const hide = filter !== 'all' && status !== filter;
                card.classList.toggle('hidden', hide);
                if (!hide) visibleCount++;
            });

            // Tampilkan/sembunyikan empty state
            const emptyEl = document.getElementById('filterEmpty');
            if (emptyEl) emptyEl.classList.toggle('show', visibleCount === 0);
        }

        // Init count badges
        function initFilterCounts() {
            const cards = document.querySelectorAll('.event-card');
            const counts = { all: cards.length, upcoming: 0, ongoing: 0, expired: 0 };
            cards.forEach(c => {
                const s = c.getAttribute('data-status');
                if (counts[s] !== undefined) counts[s]++;
            });
            Object.entries(counts).forEach(([k, v]) => {
                const el = document.getElementById('cnt-' + k);
                if (el) el.textContent = v;
            });
        }
        initFilterCounts();

        // Init — hanya jalankan progress & autoplay jika lebih dari 1 slide
        if (slides.length > 1) {
            resetProgress();
            resetTimer();
        }

        // THEME TOGGLE LOGIC
        const themeToggle = document.getElementById('themeToggle');
        const sunIcon = document.getElementById('sunIcon');
        const moonIcon = document.getElementById('moonIcon');

        function updateIcons(theme) {
            if (theme === 'light') {
                sunIcon.style.display = 'block';
                moonIcon.style.display = 'none';
            } else {
                sunIcon.style.display = 'none';
                moonIcon.style.display = 'block';
            }
        }

        updateIcons(document.documentElement.getAttribute('data-theme'));

        themeToggle.addEventListener('click', () => {
            const currentTheme = document.documentElement.getAttribute('data-theme');
            const newTheme = currentTheme === 'light' ? 'dark' : 'light';

            document.documentElement.setAttribute('data-theme', newTheme);
            localStorage.setItem('theme', newTheme);
            updateIcons(newTheme);
        });

        // === VIP SUCCESS MODAL ===
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('vip_activated') === '1') {
            const modal = document.getElementById('vipSuccessModal');
            modal.classList.add('active');
            spawnConfetti();
            // Clean URL without reload
            window.history.replaceState({}, '', window.location.pathname);
        }

        function closeVipModal() {
            const modal = document.getElementById('vipSuccessModal');
            modal.style.animation = 'none';
            modal.style.opacity = '0';
            modal.style.transition = 'opacity 0.3s';
            setTimeout(() => { modal.classList.remove('active'); modal.style.opacity = ''; }, 300);
        }

        function spawnConfetti() {
            const colors = ['#ffd700', '#ffae00', '#c8b5ff', '#34d399', '#f87171', '#fff'];
            const box = document.getElementById('vipModalBox');
            for (let i = 0; i < 40; i++) {
                const piece = document.createElement('div');
                piece.className = 'confetti-piece';
                piece.style.left = Math.random() * 100 + '%';
                piece.style.top = '0';
                piece.style.background = colors[Math.floor(Math.random() * colors.length)];
                piece.style.animationDuration = (Math.random() * 2 + 1.5) + 's';
                piece.style.animationDelay = (Math.random() * 0.8) + 's';
                piece.style.width = (Math.random() * 8 + 5) + 'px';
                piece.style.height = (Math.random() * 8 + 5) + 'px';
                box.appendChild(piece);
                setTimeout(() => piece.remove(), 4000);
            }
        }
    </script>

</body>

</html>