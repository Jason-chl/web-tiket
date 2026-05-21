<?php
session_start();
require_once 'koneksi.php';
require_once 'cleanup_orders.php';

if (!isset($_SESSION['id_user']) || $_SESSION['role'] !== 'admin') {
    header("Location: index");
    exit;
}

$id_user = $_SESSION['id_user'];
$stmt_admin = $conn->prepare("SELECT * FROM users WHERE id_user = ?");
$stmt_admin->execute([$id_user]);
$admin = $stmt_admin->fetch();
$initials = strtoupper(substr($admin['nama'] ?? 'A', 0, 1));

$stmt = $conn->query("SELECT e.*, v.nama_venue, v.alamat as alamat_venue 
                      FROM event e 
                      LEFT JOIN venue v ON e.id_venue = v.id_venue 
                      ORDER BY e.id_event DESC");
$konser_list = $stmt->fetchAll();

$venue_list = $conn->query("SELECT * FROM venue ORDER BY nama_venue ASC")->fetchAll();

$total = count($konser_list);
$published = $total; 
$ongoing = 0;
$draft = 0;

// Handle VIP Price Update
if (isset($_POST['update_vip_price'])) {
    $newPrice = (int)$_POST['vip_price'];
    $newFee   = (int)($_POST['fast_track_fee'] ?? 0);
    $conn->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('vip_price', ?) ON DUPLICATE KEY UPDATE setting_value = ?")
         ->execute([$newPrice, $newPrice]);
    $conn->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('fast_track_fee', ?) ON DUPLICATE KEY UPDATE setting_value = ?")
         ->execute([$newFee, $newFee]);
    echo "<script>alert('Pengaturan VIP diperbarui!'); window.location.href='admin_dashboard';</script>";
    exit;
}

// Get VIP Price & Fast Track Fee
try { $conn->exec("INSERT IGNORE INTO settings (setting_key, setting_value) VALUES ('fast_track_fee', '250000')"); } catch(Exception $e) {}
$vipPriceValue    = $conn->query("SELECT setting_value FROM settings WHERE setting_key = 'vip_price'")->fetchColumn() ?: 150000;
$fastTrackFeeValue = $conn->query("SELECT setting_value FROM settings WHERE setting_key = 'fast_track_fee'")->fetchColumn() ?: 250000;

// Fetch Admin Profile
$id_user_login = $_SESSION['id_user'];
$stmtA = $conn->prepare("SELECT nama, foto_profil FROM users WHERE id_user = ?");
$stmtA->execute([$id_user_login]);
$admin_profile = $stmtA->fetch();
$admin_initials = strtoupper(substr($admin_profile['nama'] ?? 'A', 0, 1));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TixNow Admin — Panel Kontrol</title>
    <!-- Cropper.js -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;500;600;700;800&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <script>
        // IMMEDATE THEME LOAD
        (function() {
            const savedTheme = localStorage.getItem('theme') || 'dark';
            document.documentElement.setAttribute('data-theme', savedTheme);
        })();
    </script>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --bg: #07090f;
            --surface: #0d1117;
            --card: #0f1521;
            --card2: #111827;
            --border: rgba(255,255,255,0.06);
            --border-alpha: rgba(255,255,255,0.1);
            --surface-alpha: rgba(255,255,255,0.04);
            --accent: #a78bfa;
            --accent-glow: rgba(167,139,250,0.15);
            --accent-dim: rgba(167,139,250,0.1);
            --btn-text: #07090f;
            --red: #f87171;
            --green: #34d399;
            --text: #e2e8f0;
            --muted: #4b5a72;
            --header-text: #ffffff;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        /* LIGHT THEME VARIABLES (PREMIUM REDESIGN) */
        [data-theme="light"] {
            --bg: #f3f4f6;
            --surface: #ffffff;
            --card: #ffffff;
            --card2: #f8fafc;
            --border: rgba(0,0,0,0.06);
            --border-alpha: rgba(0,0,0,0.08);
            --surface-alpha: rgba(0,0,0,0.03);
            --accent: #6d28d9;
            --accent-glow: rgba(109, 40, 217, 0.12);
            --accent-dim: rgba(109, 40, 217, 0.08);
            --btn-text: #ffffff;
            --red: #ef4444;
            --red-glow: rgba(239, 68, 68, 0.08);
            --green: #10b981;
            --text: #374151;
            --muted: #6b7280;
            --header-text: #111827;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            display: flex;
            transition: var(--transition);
        }

        /* SIDEBAR */
        .sidebar {
            width: 230px;
            min-height: 100vh;
            background: var(--surface);
            border-right: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            padding: 28px 0;
            position: fixed;
            top: 0; left: 0; bottom: 0;
            z-index: 50;
            transition: var(--transition);
        }

        .sidebar-logo {
            padding: 0 24px 32px;
            font-family: 'Syne', sans-serif;
            font-size: 1.3rem;
            font-weight: 800;
            color: var(--header-text);
            letter-spacing: -0.5px;
        }
        .sidebar-logo span { color: var(--accent); }
        
        .nav-logo-text { display: flex; align-items: center; color: var(--header-text); }

        /* THEME TOGGLE ADMIN */
        .theme-toggle-container {
            display: flex; align-items: center; justify-content: space-between;
            margin-bottom: 20px; padding: 10px 14px; background: rgba(255,255,255,0.03);
            border: 1px solid var(--border); border-radius: 12px;
            position: relative; z-index: 1000;
        }
        .theme-label { font-size: 0.75rem; color: var(--muted); font-weight: 500; }
        .theme-toggle {
            width: 32px; height: 32px; border-radius: 50%;
            background: var(--surface);
            border: 1px solid var(--border);
            display: flex; align-items: center; justify-content: center;
            cursor: pointer; color: var(--header-text); 
            transition: var(--transition);
            position: relative; z-index: 999;
            pointer-events: auto;
        }
        .theme-toggle:hover { border-color: var(--accent); transform: scale(1.1); }

        .sidebar-label {
            font-size: 0.65rem;
            font-weight: 600;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: var(--muted);
            padding: 0 24px 10px;
        }

        .sidebar-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 24px;
            color: var(--muted);
            text-decoration: none;
            font-size: 0.85rem;
            transition: all 0.2s;
            border-left: 2px solid transparent;
        }
        .sidebar-item:hover, .sidebar-item.active {
            color: var(--text);
            background: var(--accent-glow);
            border-left-color: var(--accent);
        }
        .sidebar-item svg { width: 16px; height: 16px; opacity: 0.6; flex-shrink: 0; }
        .sidebar-item:hover svg, .sidebar-item.active svg { opacity: 1; color: var(--accent); }

        .sidebar-bottom {
            margin-top: auto;
            padding: 24px;
            border-top: 1px solid var(--border);
        }
        
        .admin-info { font-size: 0.78rem; color: var(--muted); margin-bottom: 12px; line-height: 1.5; }
        .admin-info strong { display: block; color: var(--text); font-weight: 500; font-size: 0.85rem; }
        .btn-logout {
            display: block;
            text-align: center;
            padding: 9px;
            border-radius: 8px;
            background: var(--red-glow);
            border: 1px solid rgba(248,113,113,0.2);
            color: var(--red);
            font-size: 0.8rem;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.2s;
        }
        .btn-logout:hover { background: rgba(248,113,113,0.2); }

        /* MAIN */
        .main {
            flex: 1;
            margin-left: 230px;
            padding: 40px;
            background: var(--bg);
            transition: var(--transition);
            min-height: 100vh;
        }

        .page-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 36px;
        }

        .page-title {
            font-family: 'Syne', sans-serif;
            font-size: 1.6rem;
            font-weight: 700;
            color: var(--header-text);
            letter-spacing: -0.5px;
        }
        .page-title span { color: var(--accent); }
        .page-sub { font-size: 0.82rem; color: var(--muted); margin-top: 2px; }

        /* BTN ADD */
        .btn-add {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: var(--accent);
            color: #fff;
            font-weight: 700;
            font-size: 0.82rem;
            padding: 10px 24px;
            border-radius: 99px;
            border: none;
            cursor: pointer;
            transition: var(--transition);
            font-family: 'Inter', sans-serif;
            box-shadow: 0 8px 20px var(--accent-glow);
            text-decoration: none;
        }
        .btn-add:hover { opacity: 0.9; transform: translateY(-2px); box-shadow: 0 12px 25px var(--accent-glow); color: #fff; }

        /* STATS */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 36px;
        }

        .stat-card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 24px;
            transition: var(--transition);
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
        }
        [data-theme="dark"] .stat-card { box-shadow: none; }
        .stat-card:hover { transform: translateY(-4px); border-color: var(--accent); }

        .stat-label {
            font-size: 0.72rem;
            font-weight: 500;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.08em;
            margin-bottom: 10px;
        }

        .stat-value {
            font-family: 'Syne', sans-serif;
            font-size: 1.8rem;
            font-weight: 800;
            color: var(--header-text);
            line-height: 1;
        }
        .stat-value.accent { color: var(--accent); }
        .stat-value.green { color: var(--green); }
        .stat-value.red { color: var(--red); }

        /* ── CAROUSEL CINEMATIC ────────────────── */
        .carousel-strip {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 16px;
            overflow: hidden;
            position: relative;
            height: 380px;
            margin-bottom: 40px;
            box-shadow: 0 10px 30px -10px rgba(0,0,0,0.1);
        }
        [data-theme="dark"] .carousel-strip { box-shadow: none; }

        .strip-slide {
            position: absolute;
            inset: 0;
            opacity: 0;
            transition: opacity 0.9s ease;
            pointer-events: none;
        }
        .strip-slide.active { opacity: 1; pointer-events: auto; }

        .slide-bg { position: absolute; inset: 0; }
        .slide-bg img {
            width: 100%; height: 100%; object-fit: cover;
            filter: brightness(0.4) saturate(1.15);
            transform: scale(1.06); transition: transform 7s ease;
        }
        .strip-slide.active .slide-bg img { transform: scale(1); }
        .slide-bg::after {
            content: ''; position: absolute; inset: 0;
            background: linear-gradient(to top, var(--bg) 0%, transparent 80%),
                        linear-gradient(to right, var(--bg) 5%, transparent 60%);
        }
        [data-theme="light"] .slide-bg::after {
            background: linear-gradient(to top, rgba(226, 232, 240, 0.6) 0%, rgba(226, 232, 240, 0.2) 80%, transparent 100%),
                        linear-gradient(to right, rgba(226, 232, 240, 0.4) 5%, transparent 60%) !important;
        }

        .strip-info {
            position: absolute;
            bottom: 0; left: 0; right: 0;
            padding: 40px 50px 50px;
            display: flex; align-items: flex-end; justify-content: space-between; gap: 30px;
        }
        .strip-info-left {
            max-width: 500px;
            transform: translateY(10px); opacity: 0;
            transition: transform 0.7s ease 0.2s, opacity 0.7s ease 0.2s;
        }
        .strip-slide.active .strip-info-left { transform: translateY(0); opacity: 1; }

        .strip-badge {
            display: inline-flex; align-items: center; gap: 7px;
            font-size: 0.6rem; font-weight: 700; letter-spacing: 0.15em; text-transform: uppercase;
            color: var(--accent); border: 1px solid var(--accent);
            padding: 5px 12px; border-radius: 4px; margin-bottom: 14px;
            background: var(--accent-glow); width: fit-content;
            transform: skewX(-8deg);
        }
        .strip-badge span { transform: skewX(8deg); display: inline-block; }
        .strip-badge::before { content: ''; width: 5px; height: 5px; border-radius: 50%; background: var(--accent); flex-shrink: 0; }

        .strip-title {
            font-family: 'Syne', sans-serif; font-size: clamp(1.8rem, 3.5vw, 2.6rem); font-weight: 800;
            line-height: 1.05; letter-spacing: -1px; color: var(--header-text); margin-bottom: 10px;
            display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
        }

        .strip-meta { display: flex; gap: 16px; flex-wrap: wrap; margin-bottom: 0; }
        .strip-meta-item { display: flex; align-items: center; gap: 6px; font-size: 0.77rem; color: var(--muted); }

        .strip-num { font-family: 'Syne', sans-serif; font-size: 0.65rem; letter-spacing: 0.1em; color: var(--muted); margin-bottom: 10px; }
        .strip-num strong { color: var(--text); font-size: 0.9rem; }

        /* Progress & Controls */
        .strip-progress { position: absolute; bottom: 0; left: 0; right: 0; height: 3px; background: var(--border); z-index: 10; }
        .strip-progress-bar { height: 100%; background: var(--accent); width: 0%; transition: width linear; }

        .carousel-nav { position: absolute; top: 24px; right: 28px; z-index: 20; display: flex; gap: 8px; }
        .carousel-btn {
            width: 36px; height: 36px; border-radius: 2px;
            background: var(--card); border: 1px solid var(--border);
            color: var(--muted); cursor: pointer; display: flex; align-items: center; justify-content: center;
            transition: all 0.2s; clip-path: polygon(4px 0%, 100% 0%, calc(100% - 4px) 100%, 0% 100%);
        }
        .carousel-btn:hover { background: var(--accent-glow); color: var(--header-text); }

        .strip-info-right {
            flex-shrink: 0; transform: translateY(10px); opacity: 0;
            transition: transform 0.7s ease 0.35s, opacity 0.7s ease 0.35s; text-align: right; margin-right: 20px;
        }
        .strip-slide.active .strip-info-right { transform: translateY(0); opacity: 1; }
        .slide-thumbs { display: flex; gap: 8px; flex-direction: column; }
        .slide-thumb {
            width: 5px; height: 32px; border-radius: 3px; background: var(--border);
            cursor: pointer; transition: all 0.35s; border: none; flex-shrink: 0;
        }
        .slide-thumb.active { background: var(--accent); height: 50px; }

        .strip-empty {
            height: 200px; border-radius: 16px; background: transparent; border: 1px dashed var(--border);
            display: flex; align-items: center; justify-content: center; color: var(--muted); font-size: 0.85rem; margin-bottom: 36px;
        }

        /* CARDS GRID */
        .section-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
        }
        .section-title {
            font-family: 'Syne', sans-serif;
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--header-text);
        }
        .section-title span { color: var(--accent); }
        .event-count { font-size: 0.75rem; color: var(--muted); }

        .cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 16px;
        }

        .event-card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 16px;
            overflow: hidden;
            transition: all 0.25s;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
        }
        .event-card:hover {
            border-color: var(--accent);
            transform: translateY(-4px);
            box-shadow: 0 16px 40px rgba(0,0,0,0.1);
        }

        .card-img {
            height: 170px;
            overflow: hidden;
            position: relative;
            background: var(--card);
        }
        .card-img img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.5s; }
        .event-card:hover .card-img img { transform: scale(1.05); }

        .card-status-badge {
            position: absolute;
            top: 10px; right: 10px;
            font-size: 0.6rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            padding: 3px 10px;
            border-radius: 999px;
            backdrop-filter: blur(10px);
        }
        .s-draft { background: rgba(100,116,139,0.3); color: #94a3b8; border: 1px solid rgba(100,116,139,0.3); }
        .s-published { background: rgba(130,207,255,0.15); color: #7dd3fc; border: 1px solid rgba(130,207,255,0.25); }
        .s-ongoing { background: rgba(52,211,153,0.15); color: var(--green); border: 1px solid rgba(52,211,153,0.25); }
        .s-completed { background: rgba(167,139,250,0.15); color: var(--accent); border: 1px solid rgba(167,139,250,0.25); }
        .s-cancelled { background: rgba(248,113,113,0.15); color: var(--red); border: 1px solid rgba(248,113,113,0.25); }

        .card-body {
            padding: 16px;
        }

        .card-date {
            font-size: 0.68rem;
            font-weight: 600;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            color: var(--accent);
            margin-bottom: 6px;
        }

        .card-title {
            font-family: 'Syne', sans-serif;
            font-size: 0.95rem;
            font-weight: 700;
            color: var(--header-text);
            margin-bottom: 3px;
            overflow: hidden;
            white-space: nowrap;
            text-overflow: ellipsis;
            transition: color 0.3s;
        }

        .card-artist {
            font-size: 0.75rem;
            color: var(--muted);
            margin-bottom: 12px;
            overflow: hidden;
            white-space: nowrap;
            text-overflow: ellipsis;
        }

        .card-venue {
            font-size: 0.72rem;
            color: var(--muted);
            display: flex;
            align-items: center;
            gap: 4px;
            margin-bottom: 14px;
        }

        .card-actions {
            display: flex;
            gap: 8px;
            padding-top: 12px;
            border-top: 1px solid var(--border);
        }

        .btn-edit {
            flex: 1;
            padding: 8px;
            border-radius: 8px;
            font-size: 0.75rem;
            font-weight: 600;
            cursor: pointer;
            border: 1px solid var(--accent-glow);
            background: var(--accent-glow);
            color: var(--accent);
            transition: all 0.2s;
            font-family: 'Inter', sans-serif;
        }
        .btn-edit:hover { background: var(--accent); color: white; }

        .btn-del {
            flex: 1;
            padding: 8px;
            border-radius: 8px;
            font-size: 0.75rem;
            font-weight: 600;
            cursor: pointer;
            border: 1px solid var(--red-glow);
            background: var(--red-glow);
            color: var(--red);
            transition: all 0.2s;
            font-family: 'Inter', sans-serif;
        }
        .btn-del:hover { background: var(--red); color: white; }

        /* MONITORING BAR */
        .monitoring-wrap { margin: 15px 0; }
        .monitor-label { display: flex; justify-content: space-between; font-size: 0.65rem; color: var(--muted); margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.5px; }
        .monitor-bar { display: flex; height: 6px; background: rgba(255,255,255,0.05); border-radius: 10px; overflow: hidden; }
        .m-wait { background: #6366f1; height: 100%; transition: width 0.5s ease; }
        .m-active { background: var(--accent); height: 100%; transition: width 0.5s ease; }
        .m-sold { background: var(--green); height: 100%; transition: width 0.5s ease; }
        .monitor-stats { display: flex; gap: 12px; margin-top: 8px; }
        .ms-item { display: flex; align-items: center; gap: 4px; font-size: 0.65rem; font-weight: 600; }
        .ms-dot { width: 6px; height: 6px; border-radius: 50%; }

        /* EMPTY */
        .empty {
            grid-column: 1 / -1;
            text-align: center;
            padding: 60px;
            border: 1px dashed var(--border);
            border-radius: 14px;
        }
        .empty h3 { font-family: 'Syne', sans-serif; color: var(--header-text); font-size: 1rem; margin: 16px 0 6px; }
        .empty p { font-size: 0.8rem; color: var(--muted); }

        /* MODAL */
        .modal-backdrop {
            position: fixed;
            inset: 0;
            z-index: 200;
            background: rgba(0,0,0,0.7);
            backdrop-filter: blur(8px);
            display: none;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .modal-backdrop.open { display: flex; }

        .modal-box {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 20px;
            width: 100%;
            max-width: 500px;
            padding: 30px;
            position: relative;
            animation: modalIn 0.3s ease;
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);
            max-height: 92vh;
            overflow-y: auto;
            scrollbar-width: thin;
            scrollbar-color: var(--border) transparent;
        }
        .modal-box::-webkit-scrollbar { width: 6px; }
        .modal-box::-webkit-scrollbar-thumb { background: var(--border); border-radius: 10px; }

        .modal-delete {
            max-width: 400px;
            text-align: center;
        }

        @keyframes modalIn {
            from { opacity: 0; transform: scale(0.96) translateY(8px); }
            to { opacity: 1; transform: scale(1) translateY(0); }
        }

        .modal-close {
            position: absolute;
            top: 18px; right: 18px;
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 50%;
            width: 30px; height: 30px;
            display: flex; align-items: center; justify-content: center;
            color: var(--muted);
            cursor: pointer;
            transition: all 0.2s;
        }
        .modal-close:hover { color: var(--header-text); background: var(--border); }

        .modal-title {
            font-family: 'Syne', sans-serif;
            font-size: 1.15rem;
            font-weight: 700;
            color: var(--header-text);
            margin-bottom: 20px;
        }

        .form-group { margin-bottom: 24px; position: relative; }
        .form-label {
            display: block;
            font-size: 0.72rem;
            font-weight: 700;
            color: var(--muted);
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            opacity: 0.8;
        }
        .form-input, .form-select {
            width: 100%;
            background: var(--surface-alpha);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 14px 18px;
            color: var(--text);
            font-size: 0.9rem;
            font-family: 'Inter', sans-serif;
            outline: none;
            transition: all 0.3s;
            box-shadow: inset 0 2px 4px rgba(0,0,0,0.05);
        }
        .form-input:focus, .form-select:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 4px var(--accent-glow), inset 0 2px 4px rgba(0,0,0,0.05);
        }
        .form-input::placeholder { color: var(--muted); }
        
        /* ULTRA PREMIUM FLATPICKR CSS */
        .date-input-wrapper { width: 100% !important; display: block !important; position: relative; }
        .date-input-wrapper > svg {
            position: absolute !important; left: 14px !important; top: 50% !important; transform: translateY(-50%) !important;
            width: 17px !important; height: 17px !important; color: var(--accent) !important; pointer-events: none !important; opacity: 0.8 !important;
            z-index: 10 !important;
        }
        #dateAdd, #edit_tanggal { 
            width: 100% !important; 
            min-width: 100% !important;
            display: block !important;
            padding: 14px 18px 14px 44px !important; 
            font-size: 0.9rem !important;
            border-radius: 14px !important;
        }
        .form-input.flatpickr-input:hover { border-color: var(--accent); background: var(--surface-alpha) !important; box-shadow: 0 0 15px var(--accent-glow); }
        .form-input.flatpickr-input:focus { border-color: var(--accent); background: var(--card) !important; }

        .flatpickr-calendar {
            background: var(--surface) !important;
            border: 1px solid var(--border) !important;
            box-shadow: 0 15px 45px rgba(0,0,0,0.1), 0 0 0 1px var(--border) !important;
            border-radius: 20px !important;
            font-family: 'Inter', sans-serif !important;
            padding: 15px 15px 25px 15px !important;
            backdrop-filter: blur(25px) !important;
            width: 350px !important; 
            box-sizing: border-box !important;
        }
        .flatpickr-calendar::before, .flatpickr-calendar::after { display: none !important; }
        
        .flatpickr-day {
            border-radius: 12px !important;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1) !important;
            color: var(--text) !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            margin: 0 !important;
            height: 44px !important; /* LARGER CELL */
            max-width: 44px !important;
            flex-basis: 44px !important;
            font-size: 0.9rem !important;
        }
        .flatpickr-days { width: 308px !important; margin: 0 auto !important; } /* 7 * 44px */
        .dayContainer { width: 308px !important; min-width: 308px !important; max-width: 308px !important; }
        .flatpickr-day.today { border-color: var(--accent) !important; color: var(--accent) !important; background: var(--accent-glow) !important; }
        .flatpickr-day:hover { background: var(--accent) !important; color: #fff !important; }
        .flatpickr-day.selected {
            background: var(--accent) !important;
            color: #fff !important;
            font-weight: 800 !important;
            box-shadow: 0 0 20px var(--accent-glow) !important;
            border: none !important;
            transform: scale(1.05);
        }
        .flatpickr-day.nextMonthDay, .flatpickr-day.prevMonthDay { opacity: 0.2 !important; }
        .flatpickr-weekday { color: var(--muted) !important; font-weight: 700 !important; }
        
        /* HEADER: MONTH & YEAR SELECTORS REVAMP */
        .flatpickr-months { 
            padding: 0 !important; 
            position: relative !important; 
            display: flex !important; 
            align-items: center !important; 
            height: 65px !important; 
            justify-content: center !important;
        }
        .flatpickr-months .flatpickr-month { 
            background: transparent !important; 
            color: var(--text) !important; 
            flex: 1 !important; 
            height: 65px !important; 
            position: relative !important;
        }
        .flatpickr-current-month { 
            display: flex !important; 
            align-items: center !important;
            justify-content: center !important;
            padding: 0 !important;
            position: absolute !important;
            left: 50% !important;
            top: 50% !important;
            transform: translate(-50%, -50%) !important;
            width: auto !important;
            height: 100% !important;
            margin: 0 !important;
        }
        
        .flatpickr-monthDropdown-months, .numInputWrapper {
            background: rgba(167, 139, 250, 0.08) !important;
            color: var(--text) !important;
            border: 1px solid rgba(167, 139, 250, 0.2) !important;
            border-radius: 10px !important;
            height: 38px !important; 
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            transition: all 0.2s;
            margin: 0 4px !important;
        }
        
        .flatpickr-monthDropdown-months {
            font-weight: 700 !important;
            font-family: 'Syne', sans-serif !important;
            font-size: 0.8rem !important;
            cursor: pointer !important;
            padding: 0 12px !important;
            -webkit-appearance: none;
            -moz-appearance: none;
            appearance: none;
        }
        .flatpickr-monthDropdown-month {
            background: var(--surface) !important;
            color: var(--text) !important;
            padding: 10px !important;
        }
        
        .numInputWrapper { 
            width: 75px !important; 
            padding: 0 8px !important;
        }
        .cur-year {
            font-weight: 800 !important;
            color: var(--header-text) !important;
            font-family: 'Inter', sans-serif !important;
            background: transparent !important;
            font-size: 0.9rem !important;
            width: auto !important;
            min-width: 50px !important;
            text-align: center !important;
            border: none !important;
            padding: 0 !important;
            margin: 0 !important;
            opacity: 1 !important;
        }
        .numInputWrapper span { display: flex !important; } 
        .numInputWrapper span:hover { background: rgba(255,255,255,0.1); }
        
        .flatpickr-monthDropdown-months:hover, .numInputWrapper:hover { 
            background: var(--accent) !important; 
            color: #fff !important; 
            border-color: var(--accent) !important;
        }        
        /* YEAR PICKER GRID OVERLAY */
        .year-grid-overlay {
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background: var(--bg);
            opacity: 0.98;
            backdrop-filter: blur(12px);
            z-index: 1000;
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 8px;
            padding: 20px;
            overflow-y: auto;
            border-radius: 20px;
            opacity: 0; pointer-events: none;
            transition: all 0.3s ease;
            scrollbar-width: thin;
            scrollbar-color: var(--accent) var(--bg);
        }
        .year-grid-overlay::-webkit-scrollbar { width: 5px; }
        .year-grid-overlay::-webkit-scrollbar-track { background: var(--bg); border-radius: 10px; }
        .year-grid-overlay::-webkit-scrollbar-thumb { background: var(--accent); border-radius: 10px; }

        .year-grid-overlay.show { opacity: 1; pointer-events: auto; }
        .year-grid-item {
            padding: 10px 6px; text-align: center; cursor: pointer;
            border-radius: 10px; color: var(--text) !important; font-weight: 700;
            font-family: 'Space Mono', monospace; font-size: 0.85rem;
            background: var(--surface-alpha);
            border: 1px solid var(--border);
            transition: all 0.2s;
        }
        .year-grid-item:hover { 
            background: var(--accent); color: #fff; 
            border-color: var(--accent);
            box-shadow: 0 0 12px var(--accent-glow);
            transform: scale(1.06);
        }
        .year-grid-item.active { 
            background: var(--accent); color: #fff;
            border-color: var(--accent);
            box-shadow: 0 0 16px var(--accent-glow);
        }

        .flatpickr-innerContainer { width: 308px !important; margin: 0 auto !important; }
        .flatpickr-weekdaycontainer { display: flex !important; width: 308px !important; }
        .flatpickr-weekday { 
            color: var(--accent) !important; 
            font-weight: 700 !important; 
            font-size: 0.75rem !important; 
            text-transform: uppercase; 
            width: 44px !important; 
            flex: 0 0 44px !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
        }
        
        .flatpickr-prev-month, .flatpickr-next-month {
            padding: 0 !important;
            color: var(--accent) !important;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
            top: 50% !important;
            transform: translateY(-50%) !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            width: 34px !important;
            height: 34px !important;
            z-index: 10 !important;
            background: rgba(255,255,255,0.03) !important;
            border: 1px solid rgba(255,255,255,0.05) !important;
            border-radius: 10px !important;
        }
        .flatpickr-prev-month { left: 10px !important; }
        .flatpickr-next-month { right: 10px !important; }
        
        .flatpickr-prev-month:hover, .flatpickr-next-month:hover {
            background: var(--accent) !important;
            color: #fff !important;
            border-color: var(--accent) !important;
            transform: translateY(-50%) scale(1.1) !important;
        }
        .flatpickr-prev-month svg, .flatpickr-next-month svg { fill: currentColor !important; width: 12px !important; height: 12px !important; }
        
        .flatpickr-time {
            border-top: 1px solid var(--border) !important;
            margin-top: 10px !important;
            border-radius: 0 0 20px 20px !important;
        }
        .flatpickr-time input { color: var(--accent) !important; font-weight: 700 !important; }
        .flatpickr-time .flatpickr-time-separator { color: var(--muted) !important; }
        .flatpickr-time .flatpickr-am-pm { color: var(--accent) !important; }
        .flatpickr-time input:hover, .flatpickr-time .flatpickr-am-pm:hover { background: rgba(255,255,255,0.05) !important; }

        /* PREMIUM STATUS TOGGLE (Segmented) */
        .status-toggle {
            display: flex; background: var(--bg); border: 1px solid var(--border);
            border-radius: 12px; padding: 4px; position: relative; overflow: hidden;
            height: 42px;
        }
        .status-toggle input { display: none; }
        .status-toggle label {
            flex: 1; display: flex; align-items: center; justify-content: center;
            font-size: 0.75rem; font-weight: 700; color: var(--muted);
            cursor: pointer; z-index: 2; transition: color 0.3s; text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .status-toggle .status-slider {
            position: absolute; top: 4px; left: 4px; height: calc(100% - 8px);
            width: calc(50% - 4px); background: var(--accent); border-radius: 9px;
            z-index: 1; transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 4px 12px var(--accent-glow);
        }
        
        .status-toggle input#st_pub:checked ~ .status-slider,
        .status-toggle input#edit_st_pub:checked ~ .status-slider { transform: translateX(100%); }
        .status-toggle input#st_pub:checked ~ label[for="st_pub"],
        .status-toggle input#edit_st_pub:checked ~ label[for="edit_st_pub"],
        .status-toggle input#st_draft:checked ~ label[for="st_draft"],
        .status-toggle input#edit_st_draft:checked ~ label[for="edit_st_draft"] { color: #fff; }
        
        #cropModalAdmin .modal-box { background: var(--surface); }

        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; }

        /* TIME INPUT WRAPPER */
        .time-input-wrapper { position: relative; display: flex; align-items: center; width: 100%; }
        .time-input-wrapper svg {
            position: absolute; left: 14px; top: 50%; transform: translateY(-50%);
            width: 17px; height: 17px; color: var(--accent); pointer-events: none; opacity: 0.8; z-index: 5;
        }
        input[type="time"].time-field {
            padding-left: 42px !important;
            color-scheme: dark;
            height: 42px !important;
            border: 1px solid var(--border);
            background: var(--bg);
            color: var(--text);
            border-radius: 12px;
            font-size: 0.85rem;
            font-family: 'Inter', sans-serif;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            cursor: pointer;
        }
        [data-theme="light"] input[type="time"].time-field { color-scheme: light; }
        input[type="time"].time-field:hover { border-color: var(--accent); box-shadow: 0 0 15px var(--accent-glow); }
        input[type="time"].time-field:focus { border-color: var(--accent); outline: none; }

        /* BAND SCHEDULE SECTION */
        .schedule-section {
            background: rgba(167,139,250,0.04);
            border: 1px solid rgba(167,139,250,0.12);
            border-radius: 20px;
            padding: 30px;
            margin: 30px 0;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
        }
        .schedule-header {
            display: flex; align-items: center; justify-content: space-between;
            margin-bottom: 24px;
        }
        .schedule-title {
            display: flex; align-items: center; gap: 12px;
            font-size: 0.9rem; font-weight: 800; color: var(--accent);
            text-transform: uppercase; letter-spacing: 0.1em;
        }
        .btn-add-sched {
            display: flex; align-items: center; gap: 6px;
            padding: 6px 14px; border-radius: 8px;
            background: var(--accent-glow); border: 1px solid rgba(167,139,250,0.25);
            color: var(--accent); font-size: 0.75rem; font-weight: 700;
            cursor: pointer; transition: all 0.2s; font-family: 'Inter', sans-serif;
        }
        .btn-add-sched:hover { background: var(--accent); color: #fff; border-color: var(--accent); }
        .sched-list { display: flex; flex-direction: column; gap: 16px; margin-top: 20px; }
        .sched-row {
            display: grid; grid-template-columns: 1fr 130px 130px 45px; gap: 16px;
            align-items: center; background: rgba(0,0,0,0.2);
            border: 1px solid var(--border); border-radius: 14px; padding: 18px 24px;
            transition: all 0.3s;
            animation: fadeIn 0.3s ease;
        }
        .sched-row:hover { border-color: rgba(167,139,250,0.3); background: rgba(0,0,0,0.3); }
        .sched-row input {
            background: transparent; border: none; outline: none;
            color: var(--text); font-family: 'Inter', sans-serif; font-size: 0.82rem;
            padding: 4px 0; width: 100%;
        }
        .sched-row input[type="time"] { color-scheme: dark; padding-left: 0; height: auto; }
        [data-theme="light"] .sched-row input[type="time"] { color-scheme: light; }
        .sched-row-label {
            font-size: 0.7rem; color: var(--muted); text-transform: uppercase;
            letter-spacing: 0.04em; font-weight: 600; margin-bottom: 1px;
        }
        .sched-col { display: flex; flex-direction: column; }
        .btn-del-sched {
            width: 36px; height: 36px; border-radius: 8px; border: none;
            background: rgba(248,113,113,0.08); color: var(--red);
            cursor: pointer; display: flex; align-items: center; justify-content: center;
            transition: all 0.2s; flex-shrink: 0;
        }
        .btn-del-sched:hover { background: var(--red); color: #fff; }
        /* CUSTOM STEPPER DESIGN */
        .num-stepper { display: flex; align-items: center; gap: 8px; position: relative; }
        .num-stepper .input-wrap { position: relative; flex: 1; }
        .num-stepper input[type="number"] { -moz-appearance: textfield; }
        .num-stepper input[type="number"]::-webkit-outer-spin-button,
        .num-stepper input[type="number"]::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
        .stepper-btns { display: flex; flex-direction: column; gap: 3px; flex-shrink: 0; }
        .step-btn {
            width: 32px; height: 22px; border-radius: 6px; border: 1px solid var(--border);
            background: var(--surface); color: var(--text); cursor: pointer;
            display: flex; align-items: center; justify-content: center; transition: 0.2s;
            font-size: 0.7rem;
        }
        .step-btn:hover { border-color: var(--accent); color: var(--accent); background: var(--accent-dim); }

        .sched-empty {
            text-align: center; padding: 12px;
            color: var(--muted); font-size: 0.78rem; font-style: italic;
        }

        /* PREMIUM SELECT DESIGN */
        .premium-select-wrapper {
            position: relative;
            width: 100%;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .premium-select-wrapper::after {
            content: "";
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            width: 14px;
            height: 14px;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%23a78bfa' stroke-width='3'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' d='M19.5 8.25l-7.5 7.5-7.5-7.5'/%3E%3C/svg%3E");
            background-size: contain;
            background-repeat: no-repeat;
            pointer-events: none;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            opacity: 0.7;
        }
        .premium-select-wrapper:hover::after {
            opacity: 1;
        }
        .premium-select-wrapper:focus-within::after {
            transform: translateY(-50%) rotate(180deg);
            color: var(--accent);
        }

        select.premium-select {
            appearance: none;
            -webkit-appearance: none;
            width: 100%;
            background: var(--bg) !important;
            border: 1px solid var(--border) !important;
            color: var(--text) !important;
            padding: 10px 42px 10px 16px !important;
            border-radius: 12px !important;
            font-size: 0.85rem !important;
            font-family: 'Inter', sans-serif !important;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            outline: none !important;
        }

        select.premium-select option {
            background-color: var(--surface);
            color: var(--text);
            padding: 10px;
        }

        select.premium-select:hover {
            border-color: rgba(167, 139, 250, 0.3) !important;
            background: rgba(255, 255, 255, 0.02) !important;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }

        select.premium-select:focus {
            border-color: var(--accent) !important;
            box-shadow: 0 0 0 3px var(--accent-glow), 0 8px 20px rgba(0, 0, 0, 0.3) !important;
        }

        [data-theme="light"] select.premium-select {
            background: #fff !important;
        }

        /* CUSTOM SEARCHABLE DROPDOWN */
        .venue-select-custom {
            position: relative;
            width: 100%;
        }
        .v-select-trigger {
            width: 100%;
            padding: 10px 16px;
            background: var(--bg);
            border: 1px solid var(--border);
            border-radius: 12px;
            color: var(--text);
            font-size: 0.85rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: all 0.3s;
        }
        .v-select-trigger:hover {
            border-color: rgba(167, 139, 250, 0.4);
            background: rgba(255,255,255,0.02);
        }
        .v-select-trigger.active {
            border-color: var(--accent);
            box-shadow: 0 0 0 3px var(--accent-glow);
        }
        .v-select-trigger svg {
            width: 14px;
            height: 14px;
            color: var(--accent);
            transition: transform 0.3s;
        }
        .v-select-trigger.active svg {
            transform: rotate(180deg);
        }

        .v-select-dropdown {
            position: absolute;
            top: calc(100% + 8px);
            left: 0;
            width: 100%;
            background: #1a1a1a;
            border: 1px solid var(--border);
            border-radius: 14px;
            z-index: 1000;
            display: none;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
            backdrop-filter: blur(20px);
            animation: dropdownFade 0.2s ease-out;
        }
        [data-theme="light"] .v-select-dropdown { background: #fff; }
        
        .v-select-dropdown.show { display: block; }
        
        @keyframes dropdownFade {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .v-select-search {
            padding: 12px;
            border-bottom: 1px solid var(--border);
        }
        .v-select-search input {
            width: 100%;
            padding: 8px 12px;
            background: rgba(255,255,255,0.03);
            border: 1px solid var(--border);
            border-radius: 8px;
            color: var(--text);
            font-size: 0.8rem;
            outline: none;
        }
        [data-theme="light"] .v-select-search input { background: #f9f9f9; }

        .v-select-options {
            max-height: 200px;
            overflow-y: auto;
        }
        .v-select-options::-webkit-scrollbar { width: 5px; }
        .v-select-options::-webkit-scrollbar-thumb { background: var(--border); border-radius: 10px; }

        .v-option {
            padding: 10px 16px;
            font-size: 0.82rem;
            color: var(--muted);
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .v-option .v-cap {
            font-size: 0.65rem;
            opacity: 0.5;
            background: rgba(255,255,255,0.05);
            padding: 2px 6px;
            border-radius: 4px;
        }
        .v-option:hover {
            background: var(--accent-glow);
            color: var(--accent);
        }
        .v-option.selected {
            background: var(--accent-glow);
            color: var(--accent);
            font-weight: 600;
        }

        /* BAND TABS STYLE */
        .btn-tab-band {
            padding: 8px 18px;
            border-radius: 10px;
            border: 1px solid var(--border);
            background: rgba(255, 255, 255, 0.05);
            color: var(--muted);
            cursor: pointer;
            font-size: 0.8rem;
            font-weight: 600;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            font-family: 'Inter', sans-serif;
        }
        .btn-tab-band:hover {
            border-color: rgba(167, 139, 250, 0.4);
            color: var(--text);
            background: rgba(255, 255, 255, 0.08);
        }
        .btn-tab-band.active {
            background: var(--accent) !important;
            color: #fff !important;
            border-color: var(--accent) !important;
            box-shadow: 0 4px 15px var(--accent-glow);
        }

        .form-file {
            width: 100%;
            background: var(--bg);
            border: 1px dashed var(--border);
            border-radius: 10px;
            padding: 12px;
            color: var(--muted);
            font-size: 0.8rem;
            cursor: pointer;
            font-family: 'Inter', sans-serif;
        }
        .form-file::file-selector-button {
            background: var(--accent-glow);
            border: none;
            color: var(--accent);
            padding: 6px 14px;
            border-radius: 6px;
            margin-right: 12px;
            font-size: 0.78rem;
            font-family: 'Inter', sans-serif;
            cursor: pointer;
            font-weight: 600;
        }

        .btn-submit {
            width: 100%;
            margin-top: 8px;
            padding: 12px;
            border-radius: 10px;
            background: var(--accent);
            color: #fff;
            font-size: 0.85rem;
            border: none;
            cursor: pointer;
            font-family: 'Inter', sans-serif;
            transition: all 0.2s;
        }
        .btn-submit:hover { opacity: 0.9; }

        /* ── WIZARD STYLES ────────────────────── */
        .modal-box.wizard-box { max-width: 850px; width: 95%; padding: 45px; }
        .wizard-header { margin-bottom: 45px; text-align: center; }
        .wizard-title { font-family: 'Syne', sans-serif; font-size: 1.6rem; font-weight: 800; color: var(--header-text); margin-bottom: 16px; letter-spacing: -0.5px; }
        
        .stepper { display: flex; justify-content: center; gap: 60px; margin-bottom: 45px; position: relative; }
        .stepper::before { content: ''; position: absolute; top: 18px; left: 50%; transform: translateX(-50%); width: 280px; height: 3px; background: var(--border); z-index: 0; }
        .step { position: relative; z-index: 1; display: flex; flex-direction: column; align-items: center; gap: 12px; cursor: default; }
        .step-circle { width: 38px; height: 38px; border-radius: 50%; background: var(--surface); border: 2px solid var(--border); display: flex; align-items: center; justify-content: center; font-size: 0.9rem; font-weight: 800; color: var(--muted); transition: all 0.3s; }
        .step.active .step-circle { border-color: var(--accent); color: var(--accent); box-shadow: 0 0 20px var(--accent-glow); transform: scale(1.1); }
        .step.done .step-circle { background: var(--accent); border-color: var(--accent); color: #fff; }
        .step-label { font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; color: var(--muted); }
        .step.active .step-label { color: var(--header-text); }

        .wizard-step { display: none; }
        .wizard-step.active { display: block; animation: modalIn 0.3s ease; }

        .manage-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 40px; margin-top: 15px; }
        .manage-col-title { font-size: 0.85rem; font-weight: 800; color: var(--header-text); margin-bottom: 20px; display: flex; align-items: center; gap: 10px; text-transform: uppercase; letter-spacing: 0.08em; opacity: 0.8; }
        
        .mini-list { display: flex; flex-direction: column; gap: 12px; max-height: 280px; overflow-y: auto; padding-right: 12px; margin-top: 20px; }
        .mini-list::-webkit-scrollbar { width: 4px; }
        .mini-list::-webkit-scrollbar-thumb { background: var(--border); border-radius: 10px; }
        
        .mini-item { display: flex; align-items: center; justify-content: space-between; background: var(--card2); padding: 8px 12px; border-radius: 8px; border: 1px solid var(--border); }
        .mini-item-info { display: flex; align-items: center; gap: 10px; font-size: 0.8rem; color: var(--header-text); }
        .mini-item-img { width: 24px; height: 24px; border-radius: 50%; object-fit: cover; }
        .btn-mini-del { color: var(--red); background: none; border: none; cursor: pointer; opacity: 0.6; transition: 0.2s; display: flex; align-items: center; }
        .btn-mini-del:hover { opacity: 1; }

        .btn-add-mini { width: 100%; padding: 8px; border-radius: 8px; border: 1px dashed var(--border); background: rgba(255,255,255,0.02); color: var(--muted); font-size: 0.75rem; font-weight: 600; cursor: pointer; transition: all 0.2s; margin-top: 5px; }
        .btn-add-mini:hover { background: rgba(167,139,250,0.05); color: var(--accent); border-color: var(--accent); }

        /* Modal Add Member (Wizard Overlay) */
        .modal-crop-wiz {
            position: fixed; inset: 0;
            background: rgba(0,0,0,0.85);
            backdrop-filter: blur(8px);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 1200;
        }
        .modal-crop-wiz.show { display: flex; }
        .crop-win-wiz {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 28px;
            width: 90%;
            max-width: 420px;
        }
        .crop-img-area-wiz {
            width: 100%; height: 300px;
            background: #000;
            margin-bottom: 20px;
            border-radius: 10px;
            overflow: hidden;
        }

        /* Modal Delete Styles Re-fix */
        .del-icon {
            width: 60px; height: 60px;
            background: var(--red-glow);
            border: 1px solid rgba(248,113,113,0.25);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 16px;
        }

        .del-title { font-family: 'Syne', sans-serif; font-size: 1.1rem; font-weight: 700; color: var(--header-text); margin-bottom: 8px; }
        .del-desc { font-size: 0.82rem; color: var(--muted); line-height: 1.6; margin-bottom: 24px; }
        .del-desc strong { color: var(--text); font-weight: 500; }

        .del-actions { display: flex; gap: 10px; }
        .btn-cancel {
            flex: 1; padding: 10px; border-radius: 9px;
            background: rgba(255,255,255,0.05); border: 1px solid var(--border);
            color: var(--muted); font-size: 0.82rem; font-weight: 600;
            cursor: pointer; font-family: 'Inter', sans-serif; transition: all 0.2s;
        }
        .btn-cancel:hover { color: var(--header-text); background: rgba(255,255,255,0.1); }
        .btn-confirm-del {
            flex: 1; padding: 10px; border-radius: 9px;
            background: var(--red); border: none;
            color: white; font-size: 0.82rem; font-weight: 700;
            cursor: pointer; font-family: 'Inter', sans-serif; transition: all 0.2s;
        }
        .btn-confirm-del:hover { opacity: 0.9; }

        /* PREMIUM VIDEO TRIMMER REVAMP */
        .video-trimmer {
            background: rgba(15, 23, 42, 0.4);
            backdrop-filter: blur(8px);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 20px;
            padding: 24px;
            margin-top: 20px;
            display: none;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            animation: fadeIn 0.4s ease;
        }
        .trim-preview-box {
            width: 100%; max-height: 200px; aspect-ratio: 16/9;
            background: #000; border-radius: 12px; overflow: hidden;
            position: relative; margin-bottom: 15px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 4px 20px rgba(200, 181, 255, 0.1);
        }
        .trim-preview-box video { width: 100%; height: 100%; object-fit: contain; }
        
        .trim-controls-container {
            padding: 0 10px;
        }

        .trim-controls {
            position: relative;
            height: 60px;
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 12px;
            margin: 15px 0 35px;
            display: flex;
            align-items: center;
            overflow: visible;
        }
        
        /* Fake Waveform Simulation */
        .trim-controls::before {
            content: ''; position: absolute; inset: 10px;
            background-image: linear-gradient(90deg, transparent 45%, rgba(255,255,255,0.05) 50%, transparent 55%);
            background-size: 8px 100%;
            opacity: 0.5; pointer-events: none;
        }

        .trim-track {
            position: absolute; height: 44px; /* Slightly taller for thumbs */
            left: 5px; right: 5px; border-radius: 8px;
            background: rgba(0,0,0,0.5);
            border: 1px solid rgba(255,255,255,0.1);
            overflow: hidden;
            display: flex; /* For thumbs layout */
        }
        .trim-thumb-img {
            height: 100%; flex: 1;
            object-fit: cover;
            opacity: 0.4;
            filter: grayscale(0.5);
            pointer-events: none;
        }
        .trim-range {
            position: absolute; height: 100%;
            background: rgba(200, 181, 255, 0.05); /* Lighter middle */
            border-left: 3px solid var(--accent);
            border-right: 3px solid var(--accent);
            box-shadow: inset 0 0 30px rgba(200,181,255,0.2);
            z-index: 5;
            pointer-events: none;
        }
        .trim-track-overlay {
            position: absolute; inset: 0;
            background: linear-gradient(90deg, #000 0%, transparent 5%, transparent 95%, #000 100%);
            z-index: 2; pointer-events: none; opacity: 0.5;
        }
        
        .trim-handle {
            position: absolute;
            top: 50%;
            width: 24px; height: 44px;
            background: var(--accent);
            border: none;
            border-radius: 6px;
            transform: translate(-50%, -50%);
            cursor: w-resize;
            z-index: 10;
            display: flex; align-items: center; justify-content: center;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            transition: transform 0.2s, background 0.2s;
        }
        [data-theme="light"] .trim-handle { background: #fff; border: 1px solid #ddd; }
        .trim-handle:hover { transform: translate(-50%, -50%) scale(1.05); background: white; }
        .trim-handle::before {
            content: ''; width: 2px; height: 12px; background: rgba(0,0,0,0.5);
            margin: 0 1px; border-radius: 1px;
        }
        .trim-handle::after {
            content: attr(data-time);
            position: absolute; top: -30px; left: 50%; transform: translateX(-50%);
            font-size: 0.65rem; color: #fff; font-weight: 700;
            background: var(--accent); padding: 2px 8px; border-radius: 4px;
            white-space: nowrap; pointer-events: none;
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }

        .trim-info-stats {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 5px;
        }
        .trim-stat-label { font-size: 0.75rem; color: var(--muted); text-transform: uppercase; letter-spacing: 1px; }
        .trim-stat-value { font-family: 'Syne', sans-serif; font-weight: 800; font-size: 0.9rem; color: var(--accent); }
        .stat-max { font-size: 0.65rem; color: var(--muted); opacity: 0.6; }
    </style>
</head>
<body>

<!-- SIDEBAR -->
<aside class="sidebar">
    <div class="sidebar-logo">Tix<span>Now</span></div>
    <div class="sidebar-label">Menu</div>
    <a href="admin_dashboard" class="sidebar-item active">
        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
        Dashboard
    </a>
    <a href="admin_profile" class="sidebar-item">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
        Profil Saya
    </a>
    <a href="admin_scan" class="sidebar-item">
        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/></svg>
        Scan Tiket
    </a>

    <a href="admin_vouchers" class="sidebar-item<?php echo basename($_SERVER['PHP_SELF']) == 'admin_vouchers.php' ? ' active' : ''; ?>">
        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/></svg>
        Voucher
    </a>
    <a href="admin_history" class="sidebar-item">
        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        Riwayat User
    </a>
    <a href="admin_event" class="sidebar-item">
        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3"/></svg>
        Event
    </a>
    <a href="admin_venue" class="sidebar-item">
        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
        Venue
    </a>
    <div class="sidebar-bottom">
        <div class="theme-toggle-container">
            <span class="theme-label">Tampilan</span>
            <div class="theme-toggle" id="themeToggleAdmin" title="Pindah Tema" onclick="toggleAdminTheme()" style="pointer-events: auto !important;">
                <svg id="adminMoonIcon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path></svg>
                <svg id="adminSunIcon" style="display:none;" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"></circle><line x1="12" y1="1" x2="12" y2="3"></line><line x1="12" y1="21" x2="12" y2="23"></line><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line><line x1="1" y1="12" x2="3" y2="12"></line><line x1="21" y1="12" x2="23" y2="12"></line><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line></svg>
            </div>
        </div>
        <div class="admin-info" style="display: flex; align-items: center; gap: 12px; margin-bottom: 15px;">
            <?php if (!empty($admin['foto_profil'])): ?>
                <img src="<?php echo htmlspecialchars($admin['foto_profil']); ?>" style="width: 38px; height: 38px; border-radius: 10px; object-fit: cover; border: 1px solid var(--border);">
            <?php else: ?>
                <div style="width: 38px; height: 38px; border-radius: 10px; background: var(--accent-dim); color: var(--accent); display: flex; align-items: center; justify-content: center; font-weight: 800; font-family: 'Syne', sans-serif; font-size: 0.9rem; border: 1px solid var(--accent-glow);"><?php echo $initials; ?></div>
            <?php endif; ?>
            <div style="overflow: hidden;">
                <strong style="display: block; color: var(--text); font-weight: 600; font-size: 0.82rem; white-space: nowrap; text-overflow: ellipsis; overflow: hidden;"><?php echo htmlspecialchars($admin['nama']); ?></strong>
                <span style="display: block; font-size: 0.68rem; color: var(--muted); text-transform: uppercase; letter-spacing: 0.05em; margin-top: 1px;">Administrator</span>
            </div>
        </div>
        <a href="logout" class="btn-logout">Keluar</a>
    </div>
</aside>

<!-- MAIN -->
<main class="main">
    <?php 
    $msg = $_GET['msg'] ?? '';
    if ($msg): 
        $txt = ''; $icon = ''; $color = '';
        if ($msg === 'deleted') {
            $txt = 'Event berhasil dihapus beserta seluruh data terkait.';
            $color = 'rgba(239, 68, 68, 0.1)'; $border = 'rgba(239, 68, 68, 0.3)'; $textCol = '#f87171';
            $icon = '<svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>';
        } else if ($msg === 'updated' || $msg === 'success') {
            $txt = $msg === 'updated' ? 'Data event berhasil diperbarui.' : 'Operasi berhasil diselesaikan.';
            $color = 'rgba(52, 211, 153, 0.1)'; $border = 'rgba(52, 211, 153, 0.3)'; $textCol = '#34d399';
            $icon = '<svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>';
        }
        
        if ($txt):
    ?>
        <div id="alertMsg" style="background: <?php echo $color; ?>; border: 1px solid <?php echo $border; ?>; color: <?php echo $textCol; ?>; padding: 12px 20px; border-radius: 12px; margin-bottom: 24px; font-size: 0.9rem; display: flex; align-items: center; gap: 10px; transition: all 0.5s ease; overflow: hidden;">
            <?php echo $icon; ?>
            <span><?php echo $txt; ?></span>
        </div>
    <?php endif; endif; ?>

    <div class="page-header">
        <div>
            <h1 class="page-title">Panel <span>Admin</span></h1>
            <p class="page-sub">Kelola seluruh event dan konser di sini</p>
        </div>
        <button class="btn-add" onclick="openAddWizard()">
            <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M12 4v16m8-8H4"/></svg>
            Tambah Event
        </button>
    </div>

    <!-- STATS -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-label">Total Event</div>
            <div class="stat-value"><?php echo $total; ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Published</div>
            <div class="stat-value accent"><?php echo $published; ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Ongoing</div>
            <div class="stat-value green"><?php echo $ongoing; ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Draft</div>
            <div class="stat-value" style="color:var(--muted)"><?php echo $draft; ?></div>
        </div>
    </div>
    
    <!-- VIP CONFIG CARD -->
    <div style="margin-bottom: 30px; position: relative; border-radius: 20px; overflow: hidden; border: 1px solid rgba(255,215,0,0.2); box-shadow: 0 20px 60px rgba(255,180,0,0.08);">
        <!-- Golden gradient header band -->
        <div style="background: linear-gradient(110deg, #1a1200 0%, #2d1f00 40%, #1a1200 100%); padding: 24px 28px; display: flex; align-items: center; gap: 18px; position: relative; overflow: hidden;">
            <!-- Shimmer overlay -->
            <div style="position: absolute; inset: 0; background: linear-gradient(105deg, transparent 35%, rgba(255,215,0,0.07) 50%, transparent 65%); pointer-events: none;"></div>
            <!-- Crown icon -->
            <div style="width: 52px; height: 52px; border-radius: 14px; background: linear-gradient(135deg, #ffd700, #ffae00); display: flex; align-items: center; justify-content: center; flex-shrink: 0; box-shadow: 0 8px 24px rgba(255,180,0,0.35);">
                <svg width="26" height="26" fill="none" stroke="#000" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 19l2-7 5 4 5-4 2 7H5z"/><path stroke-linecap="round" stroke-linejoin="round" d="M3 9l4 3 5-7 5 7 4-3"/></svg>
            </div>
            <div style="flex: 1;">
                <div style="font-family: 'Syne', sans-serif; font-size: 1.05rem; font-weight: 800; color: #ffd700; letter-spacing: -0.3px; margin-bottom: 3px;">VIP Fast Track Config</div>
                <div style="font-size: 0.78rem; color: rgba(255,215,0,0.5); line-height: 1.4;">Atur harga membership VIP dan biaya Fast Track tambahan saat VIP membeli tiket.</div>
            </div>
            <!-- VIP badge -->
            <div style="padding: 5px 14px; border-radius: 20px; background: rgba(255,215,0,0.1); border: 1px solid rgba(255,215,0,0.3); font-size: 0.68rem; font-weight: 800; color: #ffd700; letter-spacing: 0.12em; text-transform: uppercase; flex-shrink: 0;">PREMIUM</div>
        </div>

        <!-- Form body -->
        <div style="background: var(--card); padding: 28px;">
            <form method="POST">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 24px;">
                    <!-- VIP Price Field -->
                    <div style="background: var(--bg); border: 1px solid var(--border); border-radius: 14px; padding: 18px 20px; transition: 0.2s;" onmouseover="this.style.borderColor='rgba(255,215,0,0.35)'" onmouseout="this.style.borderColor='var(--border)'">
                        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 12px;">
                            <div style="width: 28px; height: 28px; border-radius: 8px; background: rgba(255,215,0,0.1); display: flex; align-items: center; justify-content: center;">
                                <svg width="14" height="14" fill="none" stroke="#ffd700" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0"/></svg>
                            </div>
                            <label style="font-size: 0.72rem; font-weight: 700; color: var(--muted); text-transform: uppercase; letter-spacing: 0.08em;">Harga Membership VIP</label>
                        </div>
                        <div style="position: relative; display: flex; align-items: center; gap: 8px;">
                            <div style="position: relative; flex: 1;">
                                <span style="position: absolute; left: 14px; top: 50%; transform: translateY(-50%); font-size: 0.82rem; color: var(--muted); font-weight: 700;">Rp</span>
                                <input type="text" inputmode="numeric" id="vip_price_input" name="vip_price" value="<?php echo $vipPriceValue; ?>"
                                    style="width: 100%; padding: 11px 14px 11px 42px; border-radius: 10px; background: var(--surface); border: 1px solid var(--border); color: var(--text); font-weight: 700; font-size: 1rem; outline: none; transition: 0.2s; font-family: 'Syne', sans-serif;"
                                    onfocus="this.style.borderColor='rgba(255,215,0,0.5)'; this.style.boxShadow='0 0 0 3px rgba(255,215,0,0.08)'"
                                    onblur="this.style.borderColor='var(--border)'; this.style.boxShadow='none'">
                            </div>
                            <div style="display: flex; flex-direction: column; gap: 4px; flex-shrink: 0;">
                                <button type="button" onclick="stepInput('vip_price_input', 10000)" style="width: 30px; height: 22px; border-radius: 6px; border: 1px solid var(--border); background: var(--surface); color: var(--text); cursor: pointer; display: flex; align-items: center; justify-content: center; transition: 0.15s; font-size: 0.7rem;" onmouseover="this.style.borderColor='#ffd700'; this.style.color='#ffd700'" onmouseout="this.style.borderColor='var(--border)'; this.style.color='var(--text)'">
                                    <svg width="10" height="10" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7"/></svg>
                                </button>
                                <button type="button" onclick="stepInput('vip_price_input', -10000)" style="width: 30px; height: 22px; border-radius: 6px; border: 1px solid var(--border); background: var(--surface); color: var(--text); cursor: pointer; display: flex; align-items: center; justify-content: center; transition: 0.15s; font-size: 0.7rem;" onmouseover="this.style.borderColor='#ffd700'; this.style.color='#ffd700'" onmouseout="this.style.borderColor='var(--border)'; this.style.color='var(--text)'">
                                    <svg width="10" height="10" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                                </button>
                            </div>
                        </div>
                        <div style="font-size: 0.7rem; color: var(--muted); margin-top: 8px;">Dibayar sekali untuk aktivasi status VIP permanen</div>
                    </div>

                    <!-- Fast Track Fee Field -->
                    <div style="background: var(--bg); border: 1px solid var(--border); border-radius: 14px; padding: 18px 20px; transition: 0.2s;" onmouseover="this.style.borderColor='rgba(255,215,0,0.35)'" onmouseout="this.style.borderColor='var(--border)'">
                        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 12px;">
                            <div style="width: 28px; height: 28px; border-radius: 8px; background: rgba(255,215,0,0.1); display: flex; align-items: center; justify-content: center;">
                                <svg width="14" height="14" fill="none" stroke="#ffd700" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                            </div>
                            <label style="font-size: 0.72rem; font-weight: 700; color: var(--muted); text-transform: uppercase; letter-spacing: 0.08em;">Biaya Fast Track per Pembelian</label>
                        </div>
                        <div style="position: relative; display: flex; align-items: center; gap: 8px;">
                            <div style="position: relative; flex: 1;">
                                <span style="position: absolute; left: 14px; top: 50%; transform: translateY(-50%); font-size: 0.82rem; color: var(--muted); font-weight: 700;">Rp</span>
                                <input type="text" inputmode="numeric" id="fast_track_input" name="fast_track_fee" value="<?php echo $fastTrackFeeValue; ?>"
                                    style="width: 100%; padding: 11px 14px 11px 42px; border-radius: 10px; background: var(--surface); border: 1px solid var(--border); color: var(--text); font-weight: 700; font-size: 1rem; outline: none; transition: 0.2s; font-family: 'Syne', sans-serif;"
                                    onfocus="this.style.borderColor='rgba(255,215,0,0.5)'; this.style.boxShadow='0 0 0 3px rgba(255,215,0,0.08)'"
                                    onblur="this.style.borderColor='var(--border)'; this.style.boxShadow='none'">
                            </div>
                            <div style="display: flex; flex-direction: column; gap: 4px; flex-shrink: 0;">
                                <button type="button" onclick="stepInput('fast_track_input', 10000)" style="width: 30px; height: 22px; border-radius: 6px; border: 1px solid var(--border); background: var(--surface); color: var(--text); cursor: pointer; display: flex; align-items: center; justify-content: center; transition: 0.15s; font-size: 0.7rem;" onmouseover="this.style.borderColor='#ffd700'; this.style.color='#ffd700'" onmouseout="this.style.borderColor='var(--border)'; this.style.color='var(--text)'">
                                    <svg width="10" height="10" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7"/></svg>
                                </button>
                                <button type="button" onclick="stepInput('fast_track_input', -10000)" style="width: 30px; height: 22px; border-radius: 6px; border: 1px solid var(--border); background: var(--surface); color: var(--text); cursor: pointer; display: flex; align-items: center; justify-content: center; transition: 0.15s; font-size: 0.7rem;" onmouseover="this.style.borderColor='#ffd700'; this.style.color='#ffd700'" onmouseout="this.style.borderColor='var(--border)'; this.style.color='var(--text)'">
                                    <svg width="10" height="10" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                                </button>
                            </div>
                        </div>
                        <div style="font-size: 0.7rem; color: var(--muted); margin-top: 8px;">Biaya tambahan per pembelian saat VIP skip antrean masuk</div>
                    </div>
                </div>

                <!-- Save button row -->
                <div style="display: flex; align-items: center; justify-content: space-between; padding-top: 4px;">
                    <div style="font-size: 0.75rem; color: var(--muted); display: flex; align-items: center; gap: 6px;">
                        <svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        Perubahan berlaku instan untuk semua pengguna baru
                    </div>
                    <button type="submit" name="update_vip_price" id="vipSaveBtn"
                        style="padding: 12px 32px; border-radius: 12px; background: linear-gradient(135deg, #ffd700, #ffae00); color: #000; border: none; font-weight: 800; font-size: 0.88rem; cursor: pointer; transition: 0.2s; white-space: nowrap; display: flex; align-items: center; gap: 8px; box-shadow: 0 6px 20px rgba(255,180,0,0.25); font-family: 'Syne', sans-serif; letter-spacing: -0.2px;"
                        onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 10px 30px rgba(255,180,0,0.35)'"
                        onmouseout="this.style.transform=''; this.style.boxShadow='0 6px 20px rgba(255,180,0,0.25)'">
                        <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                        Simpan Pengaturan
                    </button>
                </div>
            </form>
        </div>
    </div>


    <!-- CAROUSEL STRIP -->
    <?php if (!empty($konser_list)): ?>
    <div class="carousel-strip">
        <div class="carousel-nav">
            <button class="carousel-btn" onclick="moveStrip(-1)"><svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"/></svg></button>
            <button class="carousel-btn" onclick="moveStrip(1)"><svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/></svg></button>
        </div>

        <?php foreach ($konser_list as $idx => $row):
            $img = !empty($row['poster_url']) ? htmlspecialchars($row['poster_url']) : 'https://images.unsplash.com/photo-1540039155732-68b2dbceaebd?q=80&w=1600&auto=format&fit=crop';
        ?>
        <div class="strip-slide <?php echo $idx === 0 ? 'active' : ''; ?>">
            <div class="slide-bg"><img src="<?php echo $img; ?>" alt=""></div>
            <div class="strip-info">
                <div class="strip-info-left">
                    <div class="strip-num"><strong><?php echo str_pad($idx+1,2,'0',STR_PAD_LEFT); ?></strong> / <?php echo str_pad(count($konser_list),2,'0',STR_PAD_LEFT); ?></div>
                    <div class="strip-badge"><span><?php echo strtoupper(htmlspecialchars($row['status'])); ?></span></div>
                    <h2 class="strip-title"><?php echo htmlspecialchars($row['nama_event']); ?></h2>
                    <div class="strip-meta">
                        <div class="strip-meta-item"><?php echo htmlspecialchars($row['artis']); ?></div>
                        <div class="strip-meta-item"><?php echo date('d M Y', strtotime($row['tanggal'])); ?></div>
                        <div class="strip-meta-item"><?php echo htmlspecialchars($row['venue']); ?></div>
                    </div>
                </div>
                <div class="strip-info-right">
                    <div class="slide-thumbs">
                        <?php foreach ($konser_list as $ti => $tf): ?>
                        <button class="slide-thumb <?php echo $ti === $idx ? 'active' : ''; ?>" onclick="goStrip(<?php echo $ti; ?>)"></button>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        <div class="strip-progress"><div class="strip-progress-bar" id="stripProgress"></div></div>
    </div>
    <?php else: ?>
    <div class="strip-empty">Belum ada event. Tambahkan event pertama kamu!</div>
    <?php endif; ?>

    <!-- CARDS -->
    <div class="section-header">
        <div class="section-title">Semua <span>Event</span></div>
        <span class="event-count"><?php echo $total; ?> event</span>
    </div>

    <div class="cards-grid">
        <?php if (!empty($konser_list)): ?>
        <?php foreach ($konser_list as $row):
            $statusClass = 's-' . $row['status'];
            $posterUrl = !empty($row['poster_url']) ? htmlspecialchars($row['poster_url']) : null;
        ?>
        <div class="event-card">
            <div class="card-img">
                <?php if ($posterUrl): ?>
                <img src="<?php echo $posterUrl; ?>" alt="" loading="lazy">
                <?php else: ?>
                <div class="img-placeholder">
                    <svg width="30" height="30" fill="none" stroke="rgba(255,255,255,0.07)" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                </div>
                <?php endif; ?>
                <div class="card-status-badge <?php echo $statusClass; ?>"><?php echo strtoupper($row['status']); ?></div>
            </div>
            <div class="card-body">
                <div class="card-date"><?php echo date('d M Y', strtotime($row['tanggal'])); ?></div>
                <div class="card-title"><?php echo htmlspecialchars($row['nama_event']); ?></div>
                <div class="card-artist"><?php echo htmlspecialchars($row['artis']); ?></div>
                <div class="card-venue">
                    <svg width="11" height="11" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    <?php echo htmlspecialchars($row['venue']); ?>
                </div>

                <?php
                    $kid = $row['id_event'];
                    $qWaiting = $conn->prepare("SELECT COUNT(*) FROM waiting_queue WHERE id_event = ? AND status = 'waiting'");
                    $qWaiting->execute([$kid]);
                    $waiting = $qWaiting->fetchColumn();

                    $qActive = $conn->prepare("SELECT COUNT(*) FROM waiting_queue WHERE id_event = ? AND status = 'active' AND expires_at > NOW()");
                    $qActive->execute([$kid]);
                    $activeSess = $qActive->fetchColumn();

                    $qSold = $conn->prepare("SELECT SUM(qty) FROM order_detail oi JOIN orders o ON oi.id_order = o.id_order WHERE o.id_event = ? AND o.status = 'paid'");
                    $qSold->execute([$kid]);
                    $sold = $qSold->fetchColumn() ?: 0;
                    
                    $sum = ($waiting + $activeSess + $sold) ?: 1;
                    $pWait = ($waiting / $sum) * 100;
                    $pActive = ($activeSess / $sum) * 100;
                    $pSold = ($sold / $sum) * 100;
                ?>

                <div class="monitoring-wrap">
                    <div class="monitor-label">
                        <span>Traffic Monitor</span>
                        <span><?php echo $waiting + $activeSess + $sold; ?> Total</span>
                    </div>
                    <div class="monitor-bar">
                        <div class="m-wait" style="width: <?php echo $pWait; ?>%" title="Antre: <?php echo $waiting; ?>"></div>
                        <div class="m-active" style="width: <?php echo $pActive; ?>%" title="Booking: <?php echo $activeSess; ?>"></div>
                        <div class="m-sold" style="width: <?php echo $pSold; ?>%" title="Terjual: <?php echo $sold; ?>"></div>
                    </div>
                    <div class="monitor-stats">
                        <div class="ms-item"><div class="ms-dot" style="background:#6366f1"></div> <?php echo $waiting; ?> Waiting</div>
                        <div class="ms-item"><div class="ms-dot" style="background:var(--accent)"></div> <?php echo $activeSess; ?> Booking</div>
                        <div class="ms-item"><div class="ms-dot" style="background:var(--green)"></div> <?php echo $sold; ?> Sold</div>
                    </div>
                </div>

                <div class="card-actions">
                    <?php if($row['status'] === 'draft'): ?>
                        <button class="btn-manage" style="background: var(--accent-glow); color: var(--accent); border: 1px solid var(--accent); border-radius: 8px; padding: 6px 14px; font-size: 0.75rem; font-weight: 700; cursor: pointer; transition: all 0.2s;" onclick="resumeWiz(<?php echo $row['id_event']; ?>)">Lanjutkan Draft</button>
                    <?php else: ?>
                        <button class="btn-manage" style="background: rgba(130,207,255,0.1); color: #82cfff; border: 1px solid rgba(130,207,255,0.2); border-radius: 8px; padding: 6px 12px; font-size: 0.75rem; font-weight: 600; cursor: pointer; transition: all 0.2s;" onmouseover="this.style.background='rgba(130,207,255,0.2)'" onmouseout="this.style.background='rgba(130,207,255,0.1)'" onclick="window.location.href='admin_manage_event?id=<?php echo $row['id_event']; ?>'">Atur Konten</button>
                    <?php endif; ?>
                    <button class="btn-edit" onclick="openEditModal(<?php echo $row['id_event']; ?>, '<?php echo addslashes($row['nama_event']); ?>', '<?php echo addslashes($row['artis']); ?>', '<?php echo addslashes($row['venue'] ?? ''); ?>', '<?php echo str_replace(' ', 'T', $row['tanggal']); ?>', '<?php echo $row['status']; ?>', '<?php echo $row['video_start']; ?>', '<?php echo $row['video_end']; ?>', '<?php echo $row['id_venue']; ?>')">Edit</button>
                    <button class="btn-del" onclick="openDeleteModal(<?php echo $row['id_event']; ?>, '<?php echo addslashes($row['nama_event']); ?>')">Hapus</button>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        <?php else: ?>
        <div class="empty">
            <svg width="40" height="40" fill="none" stroke="rgba(255,255,255,0.1)" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3"/></svg>
            <h3>Belum Ada Event</h3>
            <p>Klik "Tambah Event" untuk menambahkan konser pertama.</p>
        </div>
        <?php endif; ?>
    </div>
</main>

<!-- MODAL ADD (WIZARD) -->
<div class="modal-backdrop" id="modalAdd">
    <div class="modal-box wizard-box">
        <button class="modal-close" onclick="closeModal('modalAdd')">
            <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
        
        <div class="wizard-header">
            <div class="wizard-title">Buat Event Baru</div>
            <div class="stepper">
                <div class="step active" id="st1">
                    <div class="step-circle">1</div>
                    <div class="step-label">Informasi</div>
                </div>
                <div class="step" id="st2">
                    <div class="step-circle">2</div>
                    <div class="step-label">Konten</div>
                </div>
                <div class="step" id="st3">
                    <div class="step-circle">3</div>
                    <div class="step-label">Harga Tiket</div>
                </div>
            </div>
        </div>

        <!-- STEP 1: INFORMASI UTAMA -->
        <div class="wizard-step active" id="wstep1">
            <form id="formEventStep1">
                <input type="hidden" name="action" value="add">
                <input type="hidden" name="ajax" value="1">
                <div class="form-group">
                    <label class="form-label">Nama Event</label>
                    <input type="text" name="nama_event" required class="form-input" placeholder="Nama konser / event">
                </div>
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Artis / Band</label>
                        <input type="text" name="artis" required class="form-input" placeholder="Nama artis">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Venue</label>
                        <div class="venue-select-custom" id="vSelectAddContainer">
                            <input type="hidden" name="id_venue" id="id_venue_add">
                            <div class="v-select-trigger" onclick="toggleVSelect('vSelectAddDropdown', this)">
                                <span class="v-selected-text">Pilih Venue...</span>
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M19.5 8.25l-7.5 7.5-7.5-7.5"/></svg>
                            </div>
                            <div class="v-select-dropdown" id="vSelectAddDropdown">
                                <div class="v-select-search">
                                    <input type="text" placeholder="Cari venue..." oninput="filterVOptions(this)">
                                </div>
                                <div class="v-select-options">
                                    <?php foreach($venue_list as $vn): ?>
                                    <div class="v-option" data-id="<?php echo $vn['id_venue']; ?>" data-cap="<?php echo $vn['kapasitas']; ?>" data-name="<?php echo htmlspecialchars($vn['nama_venue']); ?>" onclick="selectVOption(this, 'id_venue_add', 'vSelectAddContainer')">
                                        <span><?php echo htmlspecialchars($vn['nama_venue']); ?></span>
                                        <span class="v-cap">Cap: <?php echo $vn['kapasitas']; ?></span>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Tanggal Event</label>
                        <div class="date-input-wrapper">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 00-2 2z"/></svg>
                            <input type="text" name="tanggal" id="dateAdd" required class="form-input" placeholder="Pilih tanggal">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Status Publikasi</label>
                        <div class="status-toggle">
                            <input type="radio" name="status" value="draft" id="st_draft" checked>
                            <label for="st_draft">Draft</label>
                            
                            <input type="radio" name="status" value="published" id="st_pub">
                            <label for="st_pub">Published</label>
                            
                            <div class="status-slider"></div>
                        </div>
                    </div>
                </div>
                <!-- TIME INPUTS -->
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Jam Mulai</label>
                        <div class="time-input-wrapper">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <input type="time" name="jam_mulai" id="jam_mulai_add" class="form-input time-field" placeholder="--:--">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Jam Selesai</label>
                        <div class="time-input-wrapper">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <input type="time" name="jam_selesai" id="jam_selesai_add" class="form-input time-field" placeholder="--:--">
                        </div>
                    </div>
                </div>
                <!-- BAND SCHEDULE SECTION -->
                <div class="schedule-section">
                    <div class="schedule-header">
                        <div class="schedule-title">
                            <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg>
                            Jadwal Band / Lineup
                        </div>
                        <button type="button" class="btn-add-sched" onclick="addScheduleRow('schedListAdd')">
                            <svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
                            Tambah Band
                        </button>
                    </div>
                    <div class="sched-list" id="schedListAdd">
                        <!-- rows added dynamically -->
                    </div>
                    <input type="hidden" name="schedule_json" id="scheduleJsonAdd" value="[]">
                </div>

                <div class="form-group">
                    <label class="form-label">Poster Event <span style="opacity:0.5">(opsional)</span></label>
                    <input type="file" name="poster" id="posterInput" accept="image/jpeg,image/png,image/webp" class="form-file">
                </div>
                <div class="form-group">
                    <label class="form-label">Video Preview <span style="opacity:0.5">(Dapat di-crop 30s)</span></label>
                    <input type="file" name="video_file" id="videoInputAdd" accept="video/mp4,video/webm" class="form-file">
                    
                    <div class="video-trimmer" id="trimmerAdd">
                        <div class="trim-info-stats">
                            <div>
                                <span class="trim-stat-label">Durasi:</span>
                                <span class="trim-stat-value" id="trimDurAdd">0s</span>
                            </div>
                            <span class="stat-max">BATAS 30 DETIK</span>
                        </div>
                        <div class="trim-preview-box">
                            <video id="videoPreviewAdd" muted></video>
                        </div>
                        <div class="trim-controls-container">
                            <div class="trim-controls" id="trimControlsAdd">
                                <div class="trim-track" id="trimTrackAdd">
                                    <div class="trim-track-overlay"></div>
                                </div>
                                <div class="trim-range" id="trimRangeAdd"></div>
                                <div class="trim-handle" id="handleStartAdd" data-time="0:00"></div>
                                <div class="trim-handle" id="handleEndAdd" data-time="0:00"></div>
                            </div>
                        </div>
                        <input type="hidden" name="video_start" id="vStartAdd" value="0">
                        <input type="hidden" name="video_end" id="vEndAdd" value="0">
                    </div>
                </div>
                <button type="submit" class="btn-submit" id="btnNext1">Simpan & Lanjut ke Konten</button>
            </form>
        </div>

        <!-- STEP 2: LINEUP & SETLIST -->
        <div class="wizard-step" id="wstep2">
            <input type="hidden" id="wiz_id_event">
            
            <!-- BAND SELECTOR (Dynamic) -->
            <div id="wizBandSelector" style="background:rgba(255,255,255,0.03); border:1px solid var(--border); border-radius:12px; padding:12px; margin-bottom:20px; display:none;">
                <label class="form-label" style="font-size:0.7rem; opacity:0.6; text-transform:uppercase; letter-spacing:0.05em; margin-bottom:8px; display:block;">Pilih Band / Penampil:</label>
                <div id="wizBandTabs" style="display:flex; gap:8px; flex-wrap:wrap;">
                    <!-- Tabs rendered here -->
                </div>
                <input type="hidden" id="wizSelectedScheduleId">
            </div>

            <div class="manage-grid">
                <!-- Lineup Col -->
                <div class="manage-col">
                    <div class="manage-col-title">
                        <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                        Lineup Member
                    </div>
                    
                    <button class="btn-add-mini" onclick="openWizMemberAdd()">+ Tambah Member</button>
                    
                    <div class="mini-list" id="wizMemberList">
                        <!-- Ajax items -->
                    </div>
                </div>

                <!-- Setlist Col -->
                <div class="manage-col">
                    <div class="manage-col-title">
                        <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3"/></svg>
                        Setlist Lagu
                    </div>
                    
                    <div style="display:flex; gap:6px; margin-bottom:12px;">
                        <input type="text" id="wizSongInput" class="form-input" style="padding:6px 10px; font-size:0.75rem;" placeholder="Judul lagu...">
                        <button class="btn-submit" style="width: auto; margin:0; padding:0 12px; font-size:0.7rem;" onclick="addWizSong()">Tambah</button>
                    </div>

                    <div class="mini-list" id="wizSongList">
                        <!-- Ajax items -->
                    </div>
                </div>
            </div>
            
            <div style="margin-top: 30px; border-top: 1px solid var(--border); padding-top: 20px; display: flex; justify-content: space-between; align-items: center; gap: 15px;">
                <button type="button" class="btn-cancel" style="width:auto; padding: 10px 20px; font-size: 0.85rem; flex: none !important;" onclick="goWizBack(1)">&larr; Kembali ke Info Utama</button>
                <button class="btn-submit" style="width:auto; padding: 10px 30px; background:var(--accent); color:black; flex: none !important;" id="btnNext2">Lanjut ke Harga Tiket &rarr;</button>
            </div>
        </div>

        <!-- STEP 3: TICKET CATEGORIES -->
        <div class="wizard-step" id="wstep3">
            <div class="manage-grid" style="grid-template-columns: 1fr;">
                <!-- Ticket Col (Centered/Single) -->
                <div class="manage-col" style="max-width: 600px; margin: 0 auto; width: 100%;">
                    <div class="manage-col-title" style="display:flex; justify-content:space-between; align-items:center;">
                        <span style="display:flex; align-items:center; gap:10px;">
                            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 012-2h10a2 2 0 012 2v14a2 2 0 01-2 2H7a2 2 0 01-2-2V5z"/></svg>
                            KATEGORI & HARGA TIKET
                        </span>
                        <div id="capacityInfo" style="font-size:0.75rem; color:var(--muted); font-weight:700; letter-spacing: 1px;">
                            KAPASITAS: <span id="currentUsedCap" style="color:var(--accent)">0</span> / <span id="maxVenueCap">0</span>
                        </div>
                    </div>

                    <div style="background:rgba(255,255,255,0.03); border:1px solid var(--border); border-radius:16px; padding:28px; margin-bottom:20px; box-shadow: 0 10px 30px rgba(0,0,0,0.2);">
                        <div class="form-group" id="wizTixBandSelector" style="display:none; margin-bottom:18px;">
                            <label class="form-label" style="font-size:0.65rem; color:var(--muted); text-transform:uppercase; letter-spacing:0.08em; font-weight:700; margin-bottom:8px; display:block;">Pilih Presensi (Khusus Band)</label>
                            <div class="venue-select-custom" id="wizTixBandCustomContainer">
                                <input type="hidden" id="wc_schedule_id">
                                <div class="v-select-trigger" onclick="toggleVSelect('wizTixBandDropdown', this)" style="border-radius:12px; background:rgba(0,0,0,0.2); border:1px solid rgba(255,255,255,0.08);">
                                    <span class="v-selected-text">Pilih Presensi...</span>
                                    <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width:14px; height:14px;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M19.5 8.25l-7.5 7.5-7.5-7.5"/></svg>
                                </div>
                                <div class="v-select-dropdown" id="wizTixBandDropdown" style="border-radius:16px;">
                                    <div class="v-select-search">
                                        <input type="text" placeholder="Cari band..." oninput="filterVOptions(this)">
                                    </div>
                                    <div class="v-select-options" id="wizTixBandOptionsList">
                                        <!-- Injected via JS -->
                                    </div>
                                </div>
                            </div>
                        </div>
                        <input type="text" id="wc_name" class="form-input" style="padding:14px 18px; font-size:0.9rem; margin-bottom:15px; border-radius:12px; border-color:rgba(255,255,255,0.1);" placeholder="Nama Kategori (VIP / Fest)">
                        <div style="display:flex; gap:12px;">
                            <div class="num-stepper" style="flex:2;">
                                <div class="input-wrap">
                                    <input type="number" id="wc_price" class="form-input" style="padding:14px 18px; font-size:0.9rem; border-radius:12px; border-color:rgba(255,255,255,0.1);" placeholder="Harga (Rp)">
                                </div>
                                <div class="stepper-btns">
                                    <button type="button" class="step-btn" onclick="stepInput('wc_price', 10000)">
                                        <svg width="10" height="10" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7"/></svg>
                                    </button>
                                    <button type="button" class="step-btn" onclick="stepInput('wc_price', -10000)">
                                        <svg width="10" height="10" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                                    </button>
                                </div>
                            </div>
                            <div class="num-stepper" style="flex:1;">
                                <div class="input-wrap">
                                    <input type="number" id="wc_stock" class="form-input" style="padding:14px 18px; font-size:0.9rem; border-radius:12px; border-color:rgba(255,255,255,0.1);" placeholder="Stok">
                                </div>
                                <div class="stepper-btns">
                                    <button type="button" class="step-btn" onclick="stepInput('wc_stock', 5)">
                                        <svg width="10" height="10" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7"/></svg>
                                    </button>
                                    <button type="button" class="step-btn" onclick="stepInput('wc_stock', -5)">
                                        <svg width="10" height="10" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <button class="btn-submit" style="width: 100%; margin:20px 0 0; padding:15px; font-size:0.9rem; font-weight: 700; border-radius:12px; background: var(--accent); color:#000;" onclick="addWizCategory()">Tambah Kategori</button>
                    </div>

                    <div class="mini-list" id="wizCatList">
                        <!-- Ajax items -->
                    </div>
                </div>
            </div>
            
            <div style="margin-top: 30px; border-top: 1px solid var(--border); padding-top: 20px; display: flex; justify-content: space-between; align-items: center; gap: 15px;">
                <button type="button" class="btn-cancel" style="width:auto; padding: 10px 20px; font-size: 0.85rem; flex: none !important;" onclick="goWizBack(2)">&larr; Kembali ke Konten</button>
                <button class="btn-submit" id="btnFinalizeWiz" style="width:auto; padding: 10px 30px; flex: none !important; background: linear-gradient(135deg, var(--accent), #a78bfa); color: #000; font-weight: 800;" onclick="finalizeWiz()">Publikasikan Event & Selesai</button>
            </div>
        </div>
    </div>
</div>

<!-- MINI MODAL ADD MEMBER (WIZARD) -->
<div class="modal-crop-wiz" id="modalWizMemberAdd">
    <div class="crop-win-wiz">
        <div class="wizard-title" style="font-size:1rem; text-align:left; margin-bottom:20px;">Tambah Member Baru</div>
        <div class="form-group">
            <label class="form-label">Nama Member</label>
            <input type="text" id="wm_name" class="form-input" placeholder="Nama asli/panggung">
        </div>
        <div class="form-group">
            <label class="form-label">Posisi / Peran</label>
            <input type="text" id="wm_role" class="form-input" placeholder="Cth: Vocalist, Guitarist">
        </div>
        <div class="form-group">
            <label class="form-label">Foto Member (Pilih Dahulu)</label>
            <input type="file" id="wm_file" class="form-file" accept="image/*">
        </div>
        
        <div id="cropAreaWiz" style="display:none;">
            <div class="crop-img-area-wiz">
                <img id="wm_image_to_crop" src="">
            </div>
        </div>

        <div class="del-actions" style="margin-top:20px;">
            <button class="btn-cancel" onclick="closeWizMemberAdd()">Batal</button>
            <button class="btn-submit" id="btnSaveWizMember" style="margin:0; width:auto; flex:1;" onclick="saveWizMember()">Simpan Member</button>
        </div>
    </div>
</div>

<!-- MODAL EDIT -->
<div class="modal-backdrop" id="modalEdit">
    <div class="modal-box">
        <button class="modal-close" onclick="closeModal('modalEdit')">
            <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
        <div class="modal-title">Edit Event</div>
        <form method="POST" action="admin_konser_action" enctype="multipart/form-data">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="edit_id">
            <div class="form-group">
                <label class="form-label">Nama Event</label>
                <input type="text" name="nama_event" id="edit_nama" required class="form-input">
            </div>
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">Artis / Band</label>
                    <input type="text" name="artis" id="edit_artis" required class="form-input">
                </div>
                <div class="form-group">
                    <label class="form-label">Venue</label>
                    <div class="venue-select-custom" id="vSelectEditContainer">
                        <input type="hidden" name="id_venue" id="id_venue_edit">
                        <div class="v-select-trigger" onclick="toggleVSelect('vSelectEditDropdown', this)">
                            <span class="v-selected-text">Pilih Venue...</span>
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M19.5 8.25l-7.5 7.5-7.5-7.5"/></svg>
                        </div>
                        <div class="v-select-dropdown" id="vSelectEditDropdown">
                            <div class="v-select-search">
                                <input type="text" placeholder="Cari venue..." oninput="filterVOptions(this)">
                            </div>
                            <div class="v-select-options">
                                <?php foreach($venue_list as $vn): ?>
                                <div class="v-option" data-id="<?php echo $vn['id_venue']; ?>" data-cap="<?php echo $vn['kapasitas']; ?>" data-name="<?php echo htmlspecialchars($vn['nama_venue']); ?>" onclick="selectVOption(this, 'id_venue_edit', 'vSelectEditContainer')">
                                    <span><?php echo htmlspecialchars($vn['nama_venue']); ?></span>
                                    <span class="v-cap">Cap: <?php echo $vn['kapasitas']; ?></span>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">Tanggal Mulai</label>
                    <div class="date-input-wrapper">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 00-2 2z"/></svg>
                        <input type="text" name="tanggal" id="edit_tanggal" required class="form-input" placeholder="Pilih tanggal & waktu">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Status Publikasi</label>
                    <div class="status-toggle">
                        <input type="radio" name="status" value="draft" id="edit_st_draft">
                        <label for="edit_st_draft">Draft</label>
                        
                        <input type="radio" name="status" value="published" id="edit_st_pub">
                        <label for="edit_st_pub">Published</label>
                        
                        <div class="status-slider"></div>
                    </div>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Ganti Poster <span style="opacity:0.5">(opsional)</span></label>
                <input type="file" name="poster" accept="image/jpeg,image/png,image/webp" class="form-file">
            </div>
            <div class="form-group">
                <label class="form-label">Ganti Video <span style="opacity:0.5">(Crop maksimal 30s)</span></label>
                <input type="file" name="video_file" id="videoInputEdit" accept="video/mp4,video/webm" class="form-file">
                
                <div class="video-trimmer" id="trimmerEdit">
                    <div class="trim-info-stats">
                        <div>
                            <span class="trim-stat-label">Durasi:</span>
                            <span class="trim-stat-value" id="trimDurEdit">0s</span>
                        </div>
                        <span class="stat-max">BATAS 30 DETIK</span>
                    </div>
                    <div class="trim-preview-box">
                        <video id="videoPreviewEdit" muted></video>
                    </div>
                    <div class="trim-controls-container">
                        <div class="trim-controls" id="trimControlsEdit">
                            <div class="trim-track" id="trimTrackEdit">
                                <div class="trim-track-overlay"></div>
                            </div>
                            <div class="trim-range" id="trimRangeEdit"></div>
                            <div class="trim-handle" id="handleStartEdit" data-time="0:00"></div>
                            <div class="trim-handle" id="handleEndEdit" data-time="0:00"></div>
                        </div>
                    </div>
                    <input type="hidden" name="video_start" id="vStartEdit" value="0">
                    <input type="hidden" name="video_end" id="vEndEdit" value="0">
                </div>
            </div>
            <button type="submit" class="btn-submit" id="btnUpdateEvent">Update Event</button>
        </form>
    </div>
</div>

<!-- MODAL DELETE -->
<div class="modal-backdrop" id="modalDelete">
    <div class="modal-box modal-delete">
        <button class="modal-close" onclick="closeModal('modalDelete')">
            <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
        <div class="del-icon">
            <svg width="40" height="40" fill="none" stroke="#f87171" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
        </div>
        <div class="del-title">Hapus Event Permanen?</div>
        <div class="del-desc" style="margin-bottom: 10px;">Event <strong id="delete_nama"></strong> akan dihapus selamanya.</div>
        
        <div style="background: rgba(248,113,113,0.1); border: 1px solid rgba(248,113,113,0.2); border-radius: 12px; padding: 15px; margin-bottom: 25px;">
            <div style="color: #f87171; font-weight: 700; font-size: 0.75rem; text-transform: uppercase; margin-bottom: 5px; display: flex; align-items: center; gap: 6px;">
                <svg width="14" height="14" fill="currentColor" viewBox="0 0 24 24"><path d="M13 14H11V9H13V14ZM13 18H11V16H13V18ZM1 21H23L12 2L1 21Z"/></svg>
                Bahaya: Aksi Destruktif
            </div>
            <div style="font-size: 0.8rem; color: rgba(248,113,113,0.8); line-height: 1.5;">
                Menghapus event ini akan menghapus <strong>seluruh riwayat pesanan, kategori tiket, dan e-tiket</strong> yang sudah diterbitkan. Tindakan ini tidak bisa dibatalkan!
            </div>
        </div>
        <div class="del-actions">
            <button class="btn-cancel" onclick="closeModal('modalDelete')">Batal</button>
            <form method="POST" action="admin_konser_action" style="flex:1">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" id="delete_id">
                <button type="submit" class="btn-confirm-del" style="width:100%">Hapus</button>
            </form>
        </div>
    </div>
</div>

<!-- MODAL ALERT (Custom Alerts) -->
<div class="modal-backdrop" id="modalAlert">
    <div class="modal-box modal-delete" style="max-width: 360px; padding: 35px 30px;">
        <button class="modal-close" onclick="closeModal('modalAlert')">
            <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
        <div class="del-icon" id="alertIconBox" style="background:var(--red-glow); border-color:rgba(248,113,113,0.25); border-radius:50%; width:60px; height:60px; margin:0 auto 20px; display:flex; align-items:center; justify-content:center;">
            <svg id="alertIconError" width="30" height="30" fill="none" stroke="#f87171" viewBox="0 0 24 24" style="display:block;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
            <svg id="alertIconWarning" width="30" height="30" fill="none" stroke="#fbbf24" viewBox="0 0 24 24" style="display:none;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        </div>
        <div class="del-title" id="alertTitle" style="font-size:1.1rem; margin-bottom:10px; font-weight:800; font-family:'Syne', sans-serif;">Peringatan</div>
        <div class="del-desc" id="alertMsg" style="font-size:0.85rem; color:var(--muted); margin-bottom:25px; line-height:1.6;"></div>
        <button class="btn-cancel" style="width:100%; padding:12px; border-radius:12px; background:var(--surface); border:1px solid var(--border); font-weight:700;" onclick="closeModal('modalAlert')">Sip, Mengerti</button>
    </div>
</div>

<!-- MODAL WIZARD CONFIRM DELETE -->
<div class="modal-backdrop" id="modalConfirmDeleteWiz">
    <div class="modal-box modal-delete" style="max-width: 360px; padding: 35px 30px;">
        <button class="modal-close" onclick="closeModal('modalConfirmDeleteWiz')">
            <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
        <div class="del-icon">
            <svg width="40" height="40" fill="none" stroke="#f87171" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
        </div>
        <div class="del-title" style="font-size:1.1rem; margin-bottom:10px; font-weight:800; font-family:'Syne', sans-serif;">Hapus Item?</div>
        <div class="del-desc" style="font-size:0.85rem; color:var(--muted); margin-bottom:25px; line-height:1.6;">Tindakan ini tidak bisa dibatalkan dan item akan langsung dihapus.</div>
        <div class="del-actions">
            <button class="btn-cancel" onclick="closeModal('modalConfirmDeleteWiz')">Batal</button>
            <button class="btn-confirm-del" id="btnConfirmDeleteWizAction" style="flex:1">Ya, Hapus</button>
        </div>
    </div>
</div>

<script>
    // ── THEME TOGGLE (GLOBAL FUNCTION) ──
    function refreshAdminIcons(theme) {
        const sun = document.getElementById('adminSunIcon');
        const moon = document.getElementById('adminMoonIcon');
        if (!sun || !moon) return;
        sun.style.display = theme === 'light' ? 'block' : 'none';
        moon.style.display = theme === 'light' ? 'none' : 'block';
    }

    function showAdminAlert(msg, type = 'error') {
        const titleEl = document.getElementById('alertTitle');
        const msgEl = document.getElementById('alertMsg');
        const box = document.getElementById('alertIconBox');
        const iErr = document.getElementById('alertIconError');
        const iWarn = document.getElementById('alertIconWarning');
        
        msgEl.innerHTML = msg;
        if (type === 'error') {
            titleEl.textContent = 'Terjadi Kesalahan';
            box.style.background = 'var(--red-glow)';
            box.style.borderColor = 'rgba(248,113,113,0.25)';
            iErr.style.display = 'block';
            iWarn.style.display = 'none';
        } else {
            titleEl.textContent = 'Perhatian';
            box.style.background = 'rgba(251, 191, 36, 0.1)';
            box.style.borderColor = 'rgba(251, 191, 36, 0.25)';
            iErr.style.display = 'none';
            iWarn.style.display = 'block';
        }
        openModal('modalAlert');
    }

    function stepInput(id, delta) {
        const el = document.getElementById(id);
        const cur = parseInt(el.value) || 0;
        el.value = Math.max(0, cur + delta);
        if (id === 'vip_price_input' || id === 'fast_track_input') syncVipBtn();
    }

    // ── VIP CONFIG SMART DISABLE ──────────────────────────────────────────
    const vipSaveBtn   = document.getElementById('vipSaveBtn');
    const vipPriceInp  = document.getElementById('vip_price_input');
    const fastTrackInp = document.getElementById('fast_track_input');
    const vipInitPrice = vipPriceInp  ? String(vipPriceInp.value)  : '';
    const vipInitFast  = fastTrackInp ? String(fastTrackInp.value) : '';

    function syncVipBtn() {
        if (!vipSaveBtn) return;
        const changed = (vipPriceInp  && String(vipPriceInp.value)  !== vipInitPrice) ||
                        (fastTrackInp && String(fastTrackInp.value) !== vipInitFast);
        vipSaveBtn.disabled      = !changed;
        vipSaveBtn.style.opacity = changed ? '1' : '0.4';
        vipSaveBtn.style.cursor  = changed ? 'pointer' : 'not-allowed';
    }
    if (vipPriceInp)  vipPriceInp.addEventListener('input',  syncVipBtn);
    if (fastTrackInp) fastTrackInp.addEventListener('input', syncVipBtn);
    syncVipBtn();

    function toggleAdminTheme(e) {
        if(e) e.stopPropagation();
        const cur = document.documentElement.getAttribute('data-theme') || 'dark';
        const next = cur === 'light' ? 'dark' : 'light';
        document.documentElement.setAttribute('data-theme', next);
        localStorage.setItem('theme', next);
        refreshAdminIcons(next);
        console.log("Theme changed to:", next);
    }

    // Initialize icons
    document.addEventListener('DOMContentLoaded', () => {
        refreshAdminIcons(document.documentElement.getAttribute('data-theme'));

        // ── EVENT CREATE WIZARD: disable "Simpan & Lanjut" until required fields filled ──
        const btnNext1 = document.getElementById('btnNext1');
        if (btnNext1) {
            const reqInputs = document.querySelectorAll('#formEventStep1 input[required]');
            function syncNext1() {
                const ok = Array.from(reqInputs).every(el => el.value.trim().length > 0);
                btnNext1.disabled = !ok;
                btnNext1.style.opacity = ok ? '1' : '0.5';
                btnNext1.style.cursor  = ok ? 'pointer' : 'not-allowed';
            }
            reqInputs.forEach(el => el.addEventListener('input', syncNext1));
            // Also run when flatpickr fires change (date/time pickers)
            document.addEventListener('change', e => {
                if (e.target.closest('#formEventStep1')) syncNext1();
            });
            btnNext1.disabled = true;
            btnNext1.style.opacity = '0.5';
            btnNext1.style.cursor = 'not-allowed';
            syncNext1();
        }

        // ── EVENT UPDATE MODAL: disable "Update Event" until user changes at least one field ──
        const btnUpdateEvent = document.getElementById('btnUpdateEvent');
        if (btnUpdateEvent) {
            btnUpdateEvent.disabled = false;
            btnUpdateEvent.style.opacity = '1';
            btnUpdateEvent.style.cursor = 'pointer';

            const editForm   = document.getElementById('formEditEvent');
            function syncUpdateBtn() { } // Disabled auto-disable logic per user request
            if (editForm) {
                editForm.querySelectorAll('input:not([type="hidden"]), textarea, select')
                    .forEach(el => el.addEventListener('input', syncUpdateBtn));
                document.addEventListener('change', e => {
                    if (e.target.closest('#formEditEvent')) syncUpdateBtn();
                });
            }

            // Capture snapshot when edit modal opens
            const _origOpen = window.openEditModal || (() => {});
            window.openEditModalAndSnapshot = function() {
                updateSnapshot = {};
                if (editForm) {
                    editForm.querySelectorAll('input:not([type="hidden"]), textarea, select').forEach(el => {
                        updateSnapshot[el.name] = el.value;
                    });
                }
                syncUpdateBtn();
            };
        }
    });

    // CAROUSEL
    const stripSlides = document.querySelectorAll('.strip-slide');
    const stripThumbs = document.querySelectorAll('.slide-thumb');
    const stripProgress = document.getElementById('stripProgress');
    const STRIP_DUR = 5500;
    let currStrip = 0;
    let timerStrip;

    function goStrip(n) {
        if (stripSlides.length === 0) return;
        stripSlides[currStrip].classList.remove('active');
        currStrip = (n + stripSlides.length) % stripSlides.length;
        stripSlides[currStrip].classList.add('active');

        // Update status thumbnail garis di setiap container frame
        const thumbsContainers = document.querySelectorAll('.slide-thumbs');
        thumbsContainers.forEach(container => {
            const thumbs = container.querySelectorAll('.slide-thumb');
            thumbs.forEach((thumb, index) => {
                thumb.classList.toggle('active', index === currStrip);
            });
        });

        resetStripProg();
        resetTimerStrip();
    }

    function moveStrip(dir) { goStrip(currStrip + dir); }

    function resetStripProg() {
        if (!stripProgress) return;
        stripProgress.style.transition = 'none';
        stripProgress.style.width = '0%';
        setTimeout(() => {
            stripProgress.style.transition = `width ${STRIP_DUR}ms linear`;
            stripProgress.style.width = '100%';
        }, 30);
    }

    function resetTimerStrip() {
        clearInterval(timerStrip);
        timerStrip = setInterval(() => moveStrip(1), STRIP_DUR);
    }

    if (stripSlides.length > 0) {
        if (stripThumbs[0]) stripThumbs[0].classList.add('active');
        if (stripSlides.length > 1) {
            resetStripProg();
            resetTimerStrip();
        }
    }

    // MODALS
    function openModal(id) {
        document.getElementById(id).classList.add('open');
        document.body.style.overflow = 'hidden';
    }
    function closeModal(id) {
        document.getElementById(id).classList.remove('open');
        document.body.style.overflow = '';
    }
    document.querySelectorAll('.modal-backdrop').forEach(m => {
        m.addEventListener('click', e => { if(e.target === m) closeModal(m.id); });
    });

    function openEditModal(id, nama, artis, venue_val, tanggal, status, vStart = 0, vEnd = 30, id_venue = null) {
        document.getElementById('edit_id').value = id;
        document.getElementById('edit_nama').value = nama;
        document.getElementById('edit_artis').value = artis;
        
        // Sync Venue Custom Dropdown
        const venueCont = document.getElementById('vSelectEditContainer');
        const optToSelect = venueCont.querySelector(`.v-option[data-id="${id_venue}"]`);
        if (optToSelect) {
            selectVOption(optToSelect, 'id_venue_edit', 'vSelectEditContainer', true);
        } else {
            venueCont.querySelector('.v-selected-text').textContent = 'Pilih Venue...';
            document.getElementById('edit_id').value = '';
        }
        // Sync Flatpickr
        if (typeof editPicker !== 'undefined') {
            editPicker.setDate(tanggal);
        } else {
            document.getElementById('edit_tanggal').value = tanggal;
        }
        
        // Handle Premium Radio Toggle
        if (status === 'published') {
            document.getElementById('edit_st_pub').checked = true;
        } else {
            document.getElementById('edit_st_draft').checked = true;
        }
        document.getElementById('vStartEdit').value = vStart;
        document.getElementById('vEndEdit').value = vEnd;
        // Hide trimmer preview on edit if no new file selected yet
        document.getElementById('trimmerEdit').style.display = 'none';
        openModal('modalEdit');
        if (window.openEditModalAndSnapshot) window.openEditModalAndSnapshot();
    }

    function openDeleteModal(id, nama) {
        document.getElementById('delete_id').value = id;
        document.getElementById('delete_nama').textContent = nama;
        openModal('modalDelete');
    }

    // ── WIZARD LOGIC ─────────────────────────
    const formStep1 = document.getElementById('formEventStep1');
    const wizKonserId = document.getElementById('wiz_id_event');
    window.intendedWizStatus = 'draft';
    const wStep1 = document.getElementById('wstep1');
    const wStep2 = document.getElementById('wstep2');
    const wStep3 = document.getElementById('wstep3');
    const st1 = document.getElementById('st1');
    const st2 = document.getElementById('st2');
    const st3 = document.getElementById('st3');
    const btnNext2 = document.getElementById('btnNext2');

    btnNext2.onclick = async () => {
        wStep2.classList.remove('active');
        wStep3.classList.add('active');
        st2.classList.add('done');
        st2.classList.remove('active');
        st3.classList.add('active');
        
        // Refresh data to populate band selector in STEP 3
        if (wizKonserId.value) {
            await loadWizContent(wizKonserId.value);
            populateTixBandSelector();
        }
    };

    function populateTixBandSelector() {
        const hid = document.getElementById('wc_schedule_id');
        const list = document.getElementById('wizTixBandOptionsList');
        const cont = document.getElementById('wizTixBandSelector');
        const trigger = document.querySelector('#wizTixBandCustomContainer .v-selected-text');
        
        if (!list || !window.wizCurrentData || !window.wizCurrentData.schedule) return;
        
        const bands = window.wizCurrentData.schedule;
        if (bands.length > 1) {
            cont.style.display = 'block';
            list.innerHTML = bands.map(b => `
                <div class="v-option" data-id="${b.id}" data-name="${b.nama_band}" onclick="selectVOption(this, 'wc_schedule_id', 'wizTixBandCustomContainer')">
                    <div style="display:flex; align-items:center; gap:10px;">
                        <div style="width:24px; height:24px; background:var(--accent-dim); color:var(--accent); border-radius:6px; display:flex; align-items:center; justify-content:center; font-size:0.65rem; font-weight:800;">${b.nama_band.charAt(0)}</div>
                        <span>${b.nama_band}</span>
                    </div>
                </div>
            `).join('');
            
            // Reset trigger text if not selected
            if (!hid.value) trigger.textContent = 'Pilih Presensi...';
        } else if (bands.length === 1) {
            console.log("Single band found.");
            cont.style.display = 'none';
            hid.value = bands[0].id;
        } else {
            console.log("No schedule found.");
            cont.style.display = 'none';
            hid.value = '';
        }
    }

    function goWizBack(toStep) {
        if (toStep === 1) {
            wStep2.classList.remove('active');
            wStep1.classList.add('active');
            st1.classList.remove('done');
            st1.classList.add('active');
            st2.classList.remove('active');
            st2.classList.remove('done');
        } else if (toStep === 2) {
            wStep3.classList.remove('active');
            wStep2.classList.add('active');
            st2.classList.remove('done');
            st2.classList.add('active');
            st3.classList.remove('active');
            st3.classList.remove('done');
        }
    }

    function resetWizardUI() {
        [wStep1, wStep2, wStep3].forEach(s => s.classList.remove('active'));
        [st1, st2, st3].forEach(s => {
            s.classList.remove('active');
            s.classList.remove('done');
        });
        formStep1.reset();
        wizKonserId.value = '';
        const vText = document.querySelector('#vSelectAddContainer .v-selected-text');
        if (vText) vText.textContent = 'Pilih Venue...';
        document.getElementById('id_venue_add').value = '';

        const bText = document.querySelector('#wizTixBandCustomContainer .v-selected-text');
        if (bText) bText.textContent = 'Pilih Presensi...';
        const bHid = document.getElementById('wc_schedule_id');
        if (bHid) bHid.value = '';
        
        // Remove existing previews
        const pPrev = document.getElementById('posterPreviewExisting');
        if (pPrev) pPrev.remove();
        const vPrev = document.getElementById('videoPreviewExisting');
        if (vPrev) vPrev.remove();

        // Reset Schedule List
        document.getElementById('schedListAdd').innerHTML = '<div class="sched-empty">Belum ada jadwal. Klik Tambah Band.</div>';
    }

    function openAddWizard() {
        resetWizardUI();
        wStep1.classList.add('active');
        st1.classList.add('active');
        openModal('modalAdd');
    }

    function resumeWiz(id) {
        resetWizardUI();
        wizKonserId.value = id;
        openModal('modalAdd');
        
        // Transisi ke STEP 2
        wStep2.classList.add('active');
        st1.classList.add('done');
        st2.classList.add('active');
        
        loadWizContent(id);
    }

    async function finalizeWiz() {
        const btn = document.getElementById('btnFinalizeWiz');
        btn.disabled = true;
        btn.textContent = 'Mempublikasikan...';

        const formData = new FormData();
        formData.append('action', 'finalize_event');
        formData.append('id_event', wizKonserId.value);
        formData.append('status', window.intendedWizStatus);
        formData.append('ajax', '1');

        try {
            await fetch('admin_manage_action', { method: 'POST', body: formData });
            location.reload();
        } catch (err) {
            console.error(err);
            location.reload(); 
        }
    }

    formStep1.onsubmit = async (e) => {
        e.preventDefault();
        
        // Ensure schedule is collected before capturing FormData
        if (typeof collectScheduleJson === 'function') {
            collectScheduleJson('schedListAdd', 'scheduleJsonAdd');
        }

        const btn = document.getElementById('btnNext1');
        btn.disabled = true;
        btn.textContent = 'Menyimpan...';

        const formData = new FormData(formStep1);
        
        // Detect Edit Mode
        if (wizKonserId.value) {
            formData.set('action', 'edit');
            formData.append('id', wizKonserId.value);
        }

        // Capacity tracking for Step 3
        const idVenueVal = document.getElementById('id_venue_add').value;
        const statusVal  = document.querySelector('input[name="status"]:checked')?.value || 'draft';
        window.intendedWizStatus = statusVal;
        
        if (idVenueVal) {
            // Find option in either container (for Add version)
            const opt = document.querySelector(`#vSelectAddContainer .v-option[data-id="${idVenueVal}"]`);
            if (opt) {
                window.selectedVenueCap = parseInt(opt.dataset.cap) || 0;
                const maxCapEl = document.getElementById('maxVenueCap');
                if (maxCapEl) maxCapEl.textContent = window.selectedVenueCap;
            }
        } else {
            showAdminAlert('Mohon pilih Venue terlebih dahulu untuk melanjutkan.', 'warning');
            btn.disabled = false;
            btn.textContent = 'Simpan & Lanjut ke Konten';
            return;
        }

        // File size validation (Increased to 512MB)
        const videoInp = document.getElementById('videoInputAdd');
        if (videoInp && videoInp.files[0] && videoInp.files[0].size > 512 * 1024 * 1024) {
            showAdminAlert('Ukuran video poster terlalu besar!<br>Maksimal berukuran <b>512MB</b>. Silakan kompres dahulu.', 'warning');
            btn.disabled = false;
            btn.textContent = 'Simpan & Lanjut ke Konten';
            return;
        }

        try {
            console.log("Sending Step 1 Data:", Object.fromEntries(formData));
            const resp = await fetch('admin_konser_action', {
                method: 'POST',
                body: formData
            });
            
            const text = await resp.text();
            let res;
            try {
                res = JSON.parse(text);
            } catch(e) {
                console.error("Server returned non-JSON:", text);
                throw new Error("Server memberikan respon yang tidak valid (Bukan JSON).");
            }

            if (res.success) {
                const eventId = wizKonserId.value || res.id;
                wizKonserId.value = eventId;
                
                wStep1.classList.remove('active');
                wStep2.classList.add('active');
                st1.classList.add('done');
                st1.classList.remove('active');
                st2.classList.add('active');
                loadWizContent(eventId);
            } else {
                showAdminAlert('Gagal menyimpan event:<br><br>' + (res.message || 'Error tidak diketahui'), 'error');
                btn.disabled = false;
                btn.textContent = 'Simpan & Lanjut ke Konten';
            }
        } catch (err) {
            console.error("Wizard Submit Error:", err);
            showAdminAlert('Terjadi kesalahan saat menyimpan data:<br><br>' + err.message, 'error');
            btn.disabled = false;
            btn.textContent = 'Simpan & Lanjut ke Konten';
        }
    };

    async function loadWizContent(id) {
        console.log("Loading Wiz Content for Event ID:", id);
        const selectedSchedInp = document.getElementById('wizSelectedScheduleId');
        const prevSelectedId = selectedSchedInp.value; 

        try {
            const resp = await fetch(`admin_get_konser_content?id=${id}`);
            const data = await resp.json();
            console.log("Wiz Content Data:", data);
            
            window.wizCurrentData = data; 
            
            // POPULATE STEP 1 (If resuming a draft)
            if (data.event) {
                const ev = data.event;
                formStep1.querySelector('[name="nama_event"]').value = ev.nama_event || '';
                formStep1.querySelector('[name="artis"]').value = ev.artis || '';
                
                // Jam Mulai & Selesai
                const jM = document.getElementById('jam_mulai_add');
                const jS = document.getElementById('jam_selesai_add');
                if (jM) jM.value = ev.jam_mulai || '';
                if (jS) jS.value = ev.jam_selesai || '';

                // Re-build Schedule List
                const sList = document.getElementById('schedListAdd');
                sList.innerHTML = ''; // Fresh start
                if (data.schedule && data.schedule.length > 0) {
                    data.schedule.forEach(s => {
                        addScheduleRow('schedListAdd', {
                            id: s.id,
                            nama_band: s.nama_band,
                            jam_mulai: s.jam_mulai,
                            jam_selesai: s.jam_selesai
                        });
                    });
                } else {
                    sList.innerHTML = '<div class="sched-empty">Belum ada jadwal. Klik Tambah Band.</div>';
                }

                // Poster Preview (Visual only)
                if (ev.poster_url) {
                    let pPrev = document.getElementById('posterPreviewExisting');
                    if (!pPrev) {
                        pPrev = document.createElement('div');
                        pPrev.id = 'posterPreviewExisting';
                        pPrev.className = 'existing-file-preview';
                        document.getElementById('posterInput').parentNode.appendChild(pPrev);
                    }
                    pPrev.innerHTML = `
                        <div style="display:flex; align-items:center; gap:10px; margin-top:8px; padding:10px; background:rgba(255,255,255,0.03); border-radius:10px; border:1px solid var(--border);">
                            <img src="${ev.poster_url}" style="width:50px; height:70px; object-fit:cover; border-radius:6px;">
                            <div style="font-size:0.75rem;">
                                <div style="color:var(--accent); font-weight:700;">Poster Terpasang</div>
                                <div style="color:var(--muted); opacity:0.7;">Klik upload untuk mengganti</div>
                            </div>
                        </div>
                    `;
                }

                // Video Preview (Visual only)
                if (ev.video_url) {
                    let vPrev = document.getElementById('videoPreviewExisting');
                    if (!vPrev) {
                        vPrev = document.createElement('div');
                        vPrev.id = 'videoPreviewExisting';
                        vPrev.className = 'existing-file-preview';
                        document.getElementById('videoInputAdd').parentNode.appendChild(vPrev);
                    }
                    vPrev.innerHTML = `
                        <div style="display:flex; align-items:center; gap:10px; margin-top:8px; padding:10px; background:rgba(255,255,255,0.03); border-radius:10px; border:1px solid var(--border);">
                            <div style="width:50px; height:50px; background:#000; border-radius:6px; display:flex; align-items:center; justify-content:center;">
                                <svg width="20" height="20" fill="var(--accent)" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                            </div>
                            <div style="font-size:0.75rem;">
                                <div style="color:var(--accent); font-weight:700;">Video Terpasang</div>
                                <div style="color:var(--muted); opacity:0.7;">Klik upload untuk mengganti</div>
                            </div>
                        </div>
                    `;
                }

                // Sync Venue Dropdown
                const vCont = document.getElementById('vSelectAddContainer');
                const vText = vCont.querySelector('.v-selected-text');
                const vInp = document.getElementById('id_venue_add');
                if (ev.id_venue) {
                    vInp.value = ev.id_venue;
                    vText.textContent = ev.nama_venue || 'Venue Terpilih';
                }

                // Sync Date (Flatpickr)
                if (ev.tanggal && typeof addPicker !== 'undefined') {
                    addPicker.setDate(ev.tanggal.replace(' ', 'T'));
                }
            }

            if (data.venue_cap) {
                window.selectedVenueCap = parseInt(data.venue_cap) || 0;
                const maxCapEl = document.getElementById('maxVenueCap');
                if (maxCapEl) maxCapEl.textContent = window.selectedVenueCap;
            }
            
            const bandSelector = document.getElementById('wizBandSelector');
            const bandTabs = document.getElementById('wizBandTabs');

            if (data.schedule && data.schedule.length > 1) {
                // Temukan index band yang sebelumnya dipilih (jika ada)
                let activeIdx = 0;
                if (prevSelectedId) {
                    const foundIdx = data.schedule.findIndex(s => s.id == prevSelectedId);
                    if (foundIdx !== -1) activeIdx = foundIdx;
                }

                bandSelector.style.display = 'block';
                bandTabs.innerHTML = data.schedule.map((s, idx) => `
                    <button class="btn-tab-band ${idx === activeIdx ? 'active' : ''}" 
                            data-id="${s.id}"
                            onclick="selectWizBand(${s.id})">
                        ${s.nama_band}
                    </button>
                `).join('');
                
                selectedSchedInp.value = data.schedule[activeIdx].id;
                // Sinkronkan styling tab aktif
                setTimeout(() => selectWizBand(selectedSchedInp.value), 0);
            } else if (data.schedule && data.schedule.length === 1) {
                console.log("Single band found.");
                bandSelector.style.display = 'none';
                selectedSchedInp.value = data.schedule[0].id;
            } else {
                console.log("No schedule found.");
                bandSelector.style.display = 'none';
                selectedSchedInp.value = '';
            }

            filterWizContent();
        } catch (err) { console.error("Error loading wiz content:", err); }
    }

    function selectWizBand(schedId) {
        document.getElementById('wizSelectedScheduleId').value = schedId;
        // Update tab active state based on data-id
        document.querySelectorAll('.btn-tab-band').forEach(btn => {
            const isActive = btn.dataset.id == schedId;
            btn.classList.toggle('active', isActive);
            if (isActive) {
                btn.style.background = 'var(--accent)';
                btn.style.color = '#000';
                btn.style.borderColor = 'var(--accent)';
            } else {
                btn.style.background = 'rgba(255,255,255,0.05)';
                btn.style.color = 'var(--text)';
                btn.style.borderColor = 'var(--border)';
            }
        });
        filterWizContent();
    }

    function filterWizContent() {
        const schedId = document.getElementById('wizSelectedScheduleId').value;
        const data = window.wizCurrentData;
        
        const filteredLineup = data.lineup.filter(m => !schedId || m.id_schedule == schedId);
        const filteredSetlist = data.setlist.filter(s => !schedId || s.id_schedule == schedId);
        
        renderWizLineup(filteredLineup);
        renderWizSongs(filteredSetlist);
        renderWizCategories(data.categories);
    }

    function renderWizLineup(list) {
        const container = document.getElementById('wizMemberList');
        if (!list || list.length === 0) {
            container.innerHTML = '<div style="color:var(--muted); font-size:0.7rem; padding:10px; text-align:center;">Belum ada member.</div>';
            return;
        }
        container.innerHTML = list.map(m => `
            <div class="mini-item">
                <div class="mini-item-info">
                    <img src="${m.foto_url || 'https://via.placeholder.com/50'}" class="mini-item-img">
                    <span>${m.nama_member} <small style="display:block; opacity:0.6; font-size:0.65rem;">${m.peran}</small></span>
                </div>
                <button class="btn-mini-del" onclick="deleteWizItem('del_member', ${m.id})">
                    <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                </button>
            </div>
        `).join('');
    }

    function renderWizSongs(list) {
        const container = document.getElementById('wizSongList');
        if (!list || list.length === 0) {
            container.innerHTML = '<div style="color:var(--muted); font-size:0.7rem; padding:10px; text-align:center;">Belum ada lagu.</div>';
            return;
        }
        container.innerHTML = list.map(s => `
            <div class="mini-item">
                <div class="mini-item-info">
                    <span style="color:var(--accent); font-weight:700;">${String(s.urutan).padStart(2,'0')}.</span>
                    <span>${s.judul_lagu}</span>
                </div>
                <button class="btn-mini-del" onclick="deleteWizItem('del_song', ${s.id})">
                    <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                </button>
            </div>
        `).join('');
    }

    function renderWizCategories(list) {
        const container = document.getElementById('wizCatList');
        const usedCapEl = document.getElementById('currentUsedCap');
        
        let totalUsed = 0;
        if (!list || list.length === 0) {
            container.innerHTML = '<div style="color:var(--muted); font-size:0.7rem; padding:10px; text-align:center;">Belum ada kategori.</div>';
            if (usedCapEl) {
                usedCapEl.textContent = '0';
                usedCapEl.style.color = 'var(--accent)';
            }
            window.currentTotalUsed = 0;
            return;
        }
        
        container.innerHTML = list.map(c => {
            totalUsed += parseInt(c.kuota) || 0;
            const bandName = c.id_schedule ? (window.wizCurrentData.schedule.find(s => s.id == c.id_schedule)?.nama_band || '') : '';
            const bandBadge = bandName ? `<div style="font-size:0.65rem; font-weight:700; color:var(--accent); text-transform:uppercase; letter-spacing:0.05em; margin-top:4px;">Khusus: ${bandName}</div>` : '';
            
            return `
            <div style="background:var(--surface); border:1px solid rgba(255,255,255,0.08); border-radius:12px; padding:16px; display:flex; align-items:center; justify-content:space-between; margin-bottom:10px; position:relative; overflow:hidden;">
                <!-- Decorative Left Accent -->
                <div style="position:absolute; left:0; top:0; bottom:0; width:4px; background:${c.warna_kategori || 'var(--accent)'};"></div>
                
                <div style="display:flex; flex-direction:column; gap:6px; flex:1; padding-left:10px;">
                    <div style="display:flex; align-items:flex-start; justify-content:space-between;">
                        <div>
                            <div style="font-size:0.95rem; font-weight:800; color:#fff; font-family:'Syne', sans-serif;">${c.nama_tiket}</div>
                            ${bandBadge}
                        </div>
                        <div style="text-align:right; margin-right:15px;">
                            <div style="font-size:0.7rem; color:var(--muted); text-transform:uppercase; letter-spacing:0.05em; font-weight:600;">Stok</div>
                            <div style="font-size:0.95rem; font-weight:800; color:var(--accent); font-family:'Space Mono', monospace;">${c.kuota}</div>
                        </div>
                    </div>
                    <div style="font-size:0.8rem; font-weight:700; color:var(--green); font-family:'Space Mono', monospace; margin-top:2px;">
                        Rp ${new Intl.NumberFormat('id-ID').format(c.harga)}
                    </div>
                </div>
                
                <button class="btn-mini-del" style="background:rgba(239,68,68,0.1); width:36px; height:36px; border-radius:10px; display:flex; align-items:center; justify-content:center; border:1px solid rgba(239,68,68,0.2); transition:0.2s; flex-shrink:0;" onclick="deleteWizItem('del_category', ${c.id_tiket})" onmouseover="this.style.background='#ef4444'; this.style.color='#fff'" onmouseout="this.style.background='rgba(239,68,68,0.1)'; this.style.color='var(--red)'">
                    <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                </button>
            </div>
        `}).join('');

        if (usedCapEl) {
            usedCapEl.textContent = totalUsed;
            if (window.selectedVenueCap && totalUsed > window.selectedVenueCap) {
                usedCapEl.style.color = 'var(--red)';
            } else {
                usedCapEl.style.color = 'var(--accent)';
            }
        }
        window.currentTotalUsed = totalUsed;
    }

    async function addWizSong() {
        const input = document.getElementById('wizSongInput');
        const judul = input.value.trim();
        if (!judul) return;

        const formData = new FormData();
        formData.append('action', 'add_song');
        formData.append('id_event', wizKonserId.value);
        formData.append('id_schedule', document.getElementById('wizSelectedScheduleId').value);
        formData.append('judul_lagu', judul);
        formData.append('ajax', '1');

        await fetch('admin_manage_action', { method: 'POST', body: formData });
        input.value = '';
        loadWizContent(wizKonserId.value);
    }

    async function addWizCategory() {
        const name = document.getElementById('wc_name').value.trim();
        const price = document.getElementById('wc_price').value;
        const stockInp = document.getElementById('wc_stock');
        const stock = parseInt(stockInp.value) || 0;
        const schedId = document.getElementById('wc_schedule_id').value;
        
        if (!name || !price || stock <= 0) {
            showAdminAlert('Mohon lengkapi <b>Nama</b>, <b>Harga</b>, dan <b>Stok</b> kategori (min. 1) sebelum menyimpan.', 'warning');
            return;
        }

        // Capacity check
        const currentUsed = window.currentTotalUsed || 0;
        if (window.selectedVenueCap && (currentUsed + stock) > window.selectedVenueCap) {
            showAdminAlert(`Stok kategori yang Anda tambahkan melebihi kapasitas maksimum venue!<br><br>Kapasitas tersisa yang bisa dialokasikan: <strong style="color:var(--accent); font-size:1.1rem;">${window.selectedVenueCap - currentUsed}</strong>`, 'error');
            return;
        }

        const formData = new FormData();
        formData.append('action', 'add_category');
        formData.append('id_event', wizKonserId.value);
        formData.append('id_schedule', schedId); 
        formData.append('nama_tiket', name);
        formData.append('harga', price);
        formData.append('stok', stock);
        formData.append('ajax', '1');

        try {
            const resp = await fetch('admin_manage_action', { method: 'POST', body: formData });
            const res = await resp.json();
            
            if (res.success) {
                document.getElementById('wc_name').value = '';
                document.getElementById('wc_price').value = '';
                document.getElementById('wc_stock').value = '';
                loadWizContent(wizKonserId.value);
            } else {
                showAdminAlert('Gagal menambah kategori:<br><br>' + (res.message || 'Error tidak diketahui'), 'error');
            }
        } catch (err) {
            console.error(err);
            showAdminAlert('Terjadi kesalahan koneksi saat menyimpan kategori.', 'error');
        }
    }

    let confirmWizDeleteAction = null;
    let confirmWizDeleteId = null;

    function deleteWizItem(action, id) {
        confirmWizDeleteAction = action;
        confirmWizDeleteId = id;
        openModal('modalConfirmDeleteWiz');
    }

    document.getElementById('btnConfirmDeleteWizAction')?.addEventListener('click', async () => {
        closeModal('modalConfirmDeleteWiz');
        if (!confirmWizDeleteAction || !confirmWizDeleteId) return;

        const formData = new FormData();
        formData.append('action', confirmWizDeleteAction);
        formData.append('id', confirmWizDeleteId);
        formData.append('id_event', wizKonserId.value);
        formData.append('ajax', '1');

        await fetch('admin_manage_action', { method: 'POST', body: formData });
        loadWizContent(wizKonserId.value);
    });

    // Member Mini Modal & Cropper
    const modWizM = document.getElementById('modalWizMemberAdd');
    const wmFile = document.getElementById('wm_file');
    const wmImg = document.getElementById('wm_image_to_crop');
    const cropArea = document.getElementById('cropAreaWiz');
    let wizCropper;

    function openWizMemberAdd() { modWizM.classList.add('show'); }
    function closeWizMemberAdd() { 
        modWizM.classList.remove('show');
        if (wizCropper) wizCropper.destroy();
        cropArea.style.display = 'none';
        wmFile.value = '';
    }

    wmFile.onchange = (e) => {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = (ev) => {
                wmImg.src = ev.target.result;
                cropArea.style.display = 'block';
                if (wizCropper) wizCropper.destroy();
                wizCropper = new Cropper(wmImg, { aspectRatio: 1, viewMode: 1 });
            };
            reader.readAsDataURL(file);
        }
    };

    async function saveWizMember() {
        const name = document.getElementById('wm_name').value.trim();
        const role = document.getElementById('wm_role').value.trim();
        if (!name || !role || !wizCropper) {
            showAdminAlert('Mohon isi nama, posisi/peran, dan potong foto member dari file gambar Anda.', 'warning');
            return;
        }

        const btn = document.getElementById('btnSaveWizMember');
        btn.disabled = true;
        btn.textContent = 'Menyimpan...';

        const canvas = wizCropper.getCroppedCanvas({ width: 300, height: 300 });
        const base64 = canvas.toDataURL('image/png');

        const formData = new FormData();
        formData.append('action', 'add_member');
        formData.append('id_event', wizKonserId.value);
        formData.append('id_schedule', document.getElementById('wizSelectedScheduleId').value);
        formData.append('nama_member', name);
        formData.append('peran', role);
        formData.append('foto_base64', base64);
        formData.append('ajax', '1');

        try {
            await fetch('admin_manage_action', { method: 'POST', body: formData });
            closeWizMemberAdd();
            // Clear inputs
            document.getElementById('wm_name').value = '';
            document.getElementById('wm_role').value = '';
            loadWizContent(wizKonserId.value);
        } catch (err) { alert('Gagal menyimpan member.'); }
        btn.disabled = false;
        btn.textContent = 'Simpan Member';
    }

    // VIDEO TRIMMER & DURATION VALIDATION
    function createTrimmer(inputId, trimmerId, videoId, trackId, rangeId, hStartId, hEndId, vStartId, vEndId, durId) {
        const input = document.getElementById(inputId);
        const trimmer = document.getElementById(trimmerId);
        const video = document.getElementById(videoId);
        const track = document.getElementById(trackId);
        const range = document.getElementById(rangeId);
        const hStart = document.getElementById(hStartId);
        const hEnd = document.getElementById(hEndId);
        const inputStart = document.getElementById(vStartId);
        const inputEnd = document.getElementById(vEndId);
        const durText = document.getElementById(durId);

        let totalDuration = 0;
        let startVal = 0; // percentage
        let endVal = 100; // percentage

        input.addEventListener('change', function() {
            const file = this.files[0];
            if (!file) { trimmer.style.display = 'none'; return; }
            
            video.src = URL.createObjectURL(file);
            video.onloadedmetadata = () => {
                totalDuration = video.duration;
                trimmer.style.display = 'block';
                
                startVal = 0;
                if (totalDuration > 30) {
                    endVal = (30 / totalDuration) * 100;
                } else {
                    endVal = 100;
                }
                updateTrimmerUI();
                generateThumbs(video.src);
            };
        });

        async function generateThumbs(url) {
            // Remove old thumbs
            track.querySelectorAll('.trim-thumb-img').forEach(el => el.remove());
            
            const tempVideo = document.createElement('video');
            tempVideo.src = url;
            tempVideo.muted = true;
            await new Promise(r => tempVideo.onloadedmetadata = r);

            const canvas = document.createElement('canvas');
            const ctx = canvas.getContext('2d');
            canvas.width = 160; canvas.height = 90;

            const thumbCount = 8;
            for (let i = 0; i < thumbCount; i++) {
                const time = (i / thumbCount) * totalDuration;
                tempVideo.currentTime = time;
                await new Promise(r => tempVideo.onseeked = r);
                
                ctx.drawImage(tempVideo, 0, 0, canvas.width, canvas.height);
                const img = document.createElement('img');
                img.src = canvas.toDataURL('image/jpeg', 0.5);
                img.className = 'trim-thumb-img';
                track.appendChild(img);
            }
        }

        function updateTrimmerUI() {
            hStart.style.left = startVal + '%';
            hEnd.style.left = endVal + '%';
            range.style.left = startVal + '%';
            range.style.width = (endVal - startVal) + '%';
            
            const startSec = (startVal / 100) * totalDuration;
            const endSec = (endVal / 100) * totalDuration;
            const selectionDur = endSec - startSec;

            inputStart.value = startSec.toFixed(2);
            inputEnd.value = endSec.toFixed(2);
            durText.textContent = selectionDur.toFixed(1) + 's';

            hStart.setAttribute('data-time', formatTime(startSec));
            hEnd.setAttribute('data-time', formatTime(endSec));

            video.currentTime = startSec;
            
            if (selectionDur > 30.2) { // Allow slight buffer
                durText.style.color = 'var(--red)';
            } else {
                durText.style.color = 'var(--accent)';
            }
        }

        function formatTime(sec) {
            const m = Math.floor(sec / 60);
            const s = Math.floor(sec % 60);
            return m + ':' + s.toString().padStart(2, '0');
        }

        // Enhanced Draggable logic
        function setupDraggable(handle, isStart) {
            const onPointerMove = (e) => {
                const clientX = e.clientX || (e.touches ? e.touches[0].clientX : 0);
                const containerWidth = track.clientWidth;
                const containerLeft = track.getBoundingClientRect().left;
                
                let pos = ((clientX - containerLeft) / containerWidth) * 100;
                pos = Math.max(0, Math.min(100, pos));

                if (isStart) {
                    if (pos < endVal) {
                        const newStartSec = (pos / 100) * totalDuration;
                        const currentEndSec = (endVal / 100) * totalDuration;
                        if (currentEndSec - newStartSec <= 30.2) {
                            startVal = pos;
                        } else {
                            startVal = ((currentEndSec - 30) / totalDuration) * 100;
                        }
                    }
                } else {
                    if (pos > startVal) {
                        const newEndSec = (pos / 100) * totalDuration;
                        const currentStartSec = (startVal / 100) * totalDuration;
                        if (newEndSec - currentStartSec <= 30.2) {
                            endVal = pos;
                        } else {
                            endVal = ((currentStartSec + 30) / totalDuration) * 100;
                        }
                    }
                }
                updateTrimmerUI();
            };

            const onPointerUp = () => {
                document.removeEventListener('mousemove', onPointerMove);
                document.removeEventListener('mouseup', onPointerUp);
                document.removeEventListener('touchmove', onPointerMove);
                document.removeEventListener('touchend', onPointerUp);
                document.body.style.cursor = 'default';
                video.play();
            };

            const onPointerDown = (e) => {
                e.preventDefault();
                video.pause();
                document.addEventListener('mousemove', onPointerMove);
                document.addEventListener('mouseup', onPointerUp);
                document.addEventListener('touchmove', onPointerMove);
                document.addEventListener('touchend', onPointerUp);
                document.body.style.cursor = 'grabbing';
            };

            handle.addEventListener('mousedown', onPointerDown);
            handle.addEventListener('touchstart', onPointerDown);
        }

        // Logic to drag the whole range (blue area)
        range.style.cursor = 'grab';
        range.style.pointerEvents = 'auto'; // Ensure it can be clicked

        range.addEventListener('mousedown', function(e) {
            e.preventDefault();
            video.pause();
            const startX = e.clientX;
            const startS = startVal;
            const startE = endVal;
            const width = endVal - startVal;

            const onMove = (me) => {
                const containerWidth = track.clientWidth;
                const deltaPct = ((me.clientX - startX) / containerWidth) * 100;
                
                let newS = startS + deltaPct;
                let newE = startE + deltaPct;

                if (newS >= 0 && newE <= 100) {
                    startVal = newS;
                    endVal = newE;
                    updateTrimmerUI();
                }
            };

            const onUp = () => {
                document.removeEventListener('mousemove', onMove);
                document.removeEventListener('mouseup', onUp);
                document.body.style.cursor = 'default';
                video.play();
            };

            document.addEventListener('mousemove', onMove);
            document.addEventListener('mouseup', onUp);
            document.body.style.cursor = 'grabbing';
        });

    setupDraggable(hStart, true);
    setupDraggable(hEnd, false);
    }

    // ── DATE-ONLY FLATPICKR CONFIG ──
    const fpConfig = {
        enableTime: false,
        dateFormat: "Y-m-d",
        minDate: "today",
        disableMobile: true,
        static: true, 
        animate: true,
        onReady: function(selectedDates, dateStr, instance) {
            const yearInput = instance.currentYearElement;
            const yearWrapper = yearInput.parentElement;
            
            // Create Year Grid Overlay
            const overlay = document.createElement("div");
            overlay.className = "year-grid-overlay";
            instance.calendarContainer.appendChild(overlay);
            
            const renderYearGrid = () => {
                overlay.innerHTML = '';
                const startYear = instance.currentYear - 12;
                const endYear = instance.currentYear + 12;
                
                for (let i = startYear; i <= endYear; i++) {
                    const item = document.createElement("div");
                    item.className = "year-grid-item";
                    if (i === instance.currentYear) item.classList.add('active');
                    item.textContent = i;
                    item.onclick = (e) => {
                        e.stopPropagation();
                        instance.changeYear(i);
                        overlay.classList.remove('show');
                    };
                    overlay.appendChild(item);
                }
            };
            
            renderYearGrid();
            
            yearWrapper.style.cursor = "pointer";
            yearWrapper.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                renderYearGrid();
                overlay.classList.toggle('show');
            });
            
            instance.calendarContainer.addEventListener('mousedown', (e) => {
                if (!overlay.contains(e.target) && !yearWrapper.contains(e.target)) {
                    overlay.classList.remove('show');
                }
            });

            // Re-render grid if year changes through arrows
            instance.set('onYearChange', () => {
                renderYearGrid();
            });
        }
    };
    window.addPicker = flatpickr("#dateAdd", fpConfig);
    var editPicker = flatpickr("#edit_tanggal", fpConfig);

    createTrimmer('videoInputAdd', 'trimmerAdd', 'videoPreviewAdd', 'trimTrackAdd', 'trimRangeAdd', 'handleStartAdd', 'handleEndAdd', 'vStartAdd', 'vEndAdd', 'trimDurAdd');
    createTrimmer('videoInputEdit', 'trimmerEdit', 'videoPreviewEdit', 'trimTrackEdit', 'trimRangeEdit', 'handleStartEdit', 'handleEndEdit', 'vStartEdit', 'vEndEdit', 'trimDurEdit');

    // ── BAND SCHEDULE BUILDER ──
    let schedRowCount = 0;

    function addScheduleRow(listId, data = {}) {
        schedRowCount++;
        const list = document.getElementById(listId);
        const empty = list.querySelector('.sched-empty');
        if (empty) empty.remove();

        const row = document.createElement('div');
        row.className = 'sched-row';
        row.dataset.rowId = schedRowCount;
        row.dataset.dbId = data.id || '';
        row.innerHTML = `
            <div class="sched-col">
                <div class="sched-row-label">Nama Band</div>
                <input type="text" class="sched-band" placeholder="Nama artis / band" value="${data.nama_band || ''}">
            </div>
            <div class="sched-col">
                <div class="sched-row-label">Jam Mulai</div>
                <input type="time" class="sched-time-start" value="${data.jam_mulai || ''}">
            </div>
            <div class="sched-col">
                <div class="sched-row-label">Jam Selesai</div>
                <input type="time" class="sched-time-end" value="${data.jam_selesai || ''}">
            </div>
            <button type="button" class="btn-del-sched" onclick="removeScheduleRow(this, '${listId}')">
                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        `;
        list.appendChild(row);
    }

    function removeScheduleRow(btn, listId) {
        btn.closest('.sched-row').remove();
        const list = document.getElementById(listId);
        if (list.querySelectorAll('.sched-row').length === 0) {
            list.innerHTML = '<div class="sched-empty">Belum ada jadwal. Klik Tambah Band.</div>';
        }
    }

    function collectScheduleJson(listId, hiddenId) {
        const rows = document.querySelectorAll('#' + listId + ' .sched-row');
        console.log(`Collecting schedule from #${listId}, found ${rows.length} rows`);
        const data = [];
        rows.forEach(row => {
            const band = row.querySelector('.sched-band').value.trim();
            const jm = row.querySelector('.sched-time-start').value;
            const js = row.querySelector('.sched-time-end').value;
            const dbId = row.dataset.dbId || '';
            if (band) data.push({ id: dbId, nama_band: band, jam_mulai: jm, jam_selesai: js });
        });
        console.log("Collected Data:", data);
        document.getElementById(hiddenId).value = JSON.stringify(data);
    }

    // Show empty state on init
    var schedListAdd = document.getElementById('schedListAdd');
    if (schedListAdd && !schedListAdd.querySelector('.sched-row')) {
        schedListAdd.innerHTML = '<div class="sched-empty">Belum ada jadwal. Klik Tambah Band.</div>';
    }

    // ── CUSTOM SEARCHABLE DROPDOWN LOGIC ──
    function toggleVSelect(dropdownId, trigger) {
        event.stopPropagation();
        const dropdown = document.getElementById(dropdownId);
        const isOpen = dropdown.classList.contains('show');
        
        // Close all other dropdowns
        document.querySelectorAll('.v-select-dropdown').forEach(d => d.classList.remove('show'));
        document.querySelectorAll('.v-select-trigger').forEach(t => t.classList.remove('active'));

        if (!isOpen) {
            dropdown.classList.add('show');
            trigger.classList.add('active');
            dropdown.querySelector('input').focus();
        }
    }

    function selectVOption(el, hiddenId, containerId, silent = false) {
        const id = el.dataset.id;
        const name = el.dataset.name;
        const cap = el.dataset.cap;
        const container = document.getElementById(containerId);
        
        container.querySelector('.v-selected-text').textContent = name;
        document.getElementById(hiddenId).value = id;
        
        // Highlight logic
        container.querySelectorAll('.v-option').forEach(opt => opt.classList.remove('selected'));
        el.classList.add('selected');
        
        if (!silent) {
            container.querySelector('.v-select-dropdown').classList.remove('show');
            container.querySelector('.v-select-trigger').classList.remove('active');
        }
    }

    function filterVOptions(input) {
        const filter = input.value.toLowerCase();
        const options = input.closest('.v-select-dropdown').querySelectorAll('.v-option');
        options.forEach(opt => {
            const txt = opt.dataset.name.toLowerCase();
            opt.style.display = txt.includes(filter) ? 'flex' : 'none';
        });
    }

    window.addEventListener('click', () => {
        document.querySelectorAll('.v-select-dropdown').forEach(d => d.classList.remove('show'));
        document.querySelectorAll('.v-select-trigger').forEach(t => t.classList.remove('active'));
    });

    // Auto-hide alert message
    const alertMsg = document.getElementById('alertMsg');
    if (alertMsg) {
        setTimeout(() => {
            alertMsg.style.opacity = '0';
            alertMsg.style.transform = 'translateY(-10px)';
            setTimeout(() => {
                alertMsg.style.display = 'none';
                // Clean URL
                const url = new URL(window.location);
                url.searchParams.delete('msg');
                window.history.replaceState({}, '', url);
            }, 500);
        }, 3000);
    }

    // Init on load
    document.addEventListener('DOMContentLoaded', () => {
        refreshAdminIcons(document.documentElement.getAttribute('data-theme'));
    });
</script>

</body>
</html>
