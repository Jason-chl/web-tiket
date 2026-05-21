<?php
session_start();
require_once 'koneksi.php';

if (!isset($_SESSION['id_user']) || $_SESSION['role'] !== 'admin') {
    header("Location: index");
    exit;
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: admin_dashboard");
    exit;
}

$id_event = $_GET['id'];
$stmt = $conn->prepare("SELECT e.*, v.kapasitas FROM event e LEFT JOIN venue v ON e.id_venue = v.id_venue WHERE e.id_event = ?");
$stmt->execute([$id_event]);
$konser = $stmt->fetch();

if (!$konser) {
    header("Location: admin_dashboard");
    exit;
}

// Fetch lineup
$stmtL = $conn->prepare("SELECT * FROM event_lineup WHERE id_event = ? ORDER BY id ASC");
$stmtL->execute([$id_event]);
$lineup = $stmtL->fetchAll();

// Get setlist
$stmtS = $conn->prepare("SELECT * FROM event_setlist WHERE id_event = ? ORDER BY urutan ASC, id ASC");
$stmtS->execute([$id_event]);
$setlist = $stmtS->fetchAll();

// Get schedule
$stmtT = $conn->prepare("SELECT * FROM event_schedule WHERE id_event = ? ORDER BY jam_mulai ASC");
$stmtT->execute([$id_event]);
$schedule = $stmtT->fetchAll();

// Fetch categories
$stmtC = $conn->prepare("SELECT * FROM tiket WHERE id_event = ? ORDER BY harga ASC");
$stmtC->execute([$id_event]);
$categories = $stmtC->fetchAll();

$total_kuota = 0;
foreach ($categories as $c) {
    $total_kuota += $c['kuota'];
}
$sisa_kuota = max(0, ($konser['kapasitas'] ?? 0) - $total_kuota);

$msg = $_GET['msg'] ?? '';

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
    <title>Kelola Konten: <?php echo htmlspecialchars($konser['nama_event']); ?></title>
    <!-- CSS & JS Cropper -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;500;600;700;800&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <script>
    // 1. THEME INITIALIZATION
    (function() {
        const savedTheme = localStorage.getItem('theme') || 'dark';
        document.documentElement.setAttribute('data-theme', savedTheme);
    })();
    </script>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root {
            color-scheme: dark;
            --bg: #07090f; --surface: #0d1117; --card: #0f1521; --card2: #141b2d;
            --border: rgba(255,255,255,0.06); 
            --border-alpha: rgba(255,255,255,0.1);
            --surface-alpha: rgba(255,255,255,0.04);
            --accent: #a78bfa; --accent-glow: rgba(167,139,250,0.15);
            --red: #f87171; --red-glow: rgba(248,113,113,0.12); --green: #34d399;
            --text: #e2e8f0; --muted: #4b5a72; --header-text: #ffffff;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        [data-theme="light"] {
            color-scheme: light;
            --bg: #f3f4f6; --surface: #ffffff; --card: #ffffff; --card2: #f8fafc;
            --border: rgba(0,0,0,0.06); 
            --border-alpha: rgba(0,0,0,0.08);
            --surface-alpha: rgba(0,0,0,0.03);
            --accent: #6d28d9; --accent-glow: rgba(109, 40, 217, 0.12);
            --red: #ef4444; --red-glow: rgba(239, 68, 68, 0.08); --green: #10b981;
            --text: #374151; --muted: #6b7280; --header-text: #111827;
        }

        body { font-family: 'Inter', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; display: flex; transition: var(--transition); }
        
        /* SIDEBAR SYNC */
        .sidebar { width: 230px; min-height: 100vh; background: var(--surface); border-right: 1px solid var(--border); display: flex; flex-direction: column; padding: 28px 0; position: fixed; top: 0; left: 0; bottom: 0; z-index: 50; transition: var(--transition); }
        .sidebar-logo { padding: 0 24px 32px; font-family: 'Syne', sans-serif; font-size: 1.3rem; font-weight: 800; color: var(--header-text); letter-spacing: -0.5px; }
        .sidebar-logo span { color: var(--accent); }
        .sidebar-label { font-size: 0.65rem; font-weight: 600; letter-spacing: 0.12em; text-transform: uppercase; color: var(--muted); padding: 0 24px 10px; }
        .sidebar-item { display: flex; align-items: center; gap: 10px; padding: 10px 24px; color: var(--muted); font-size: 0.85rem; cursor: pointer; transition: all 0.2s; border-left: 2px solid transparent; text-decoration: none; }
        .sidebar-item:hover, .sidebar-item.active { color: var(--text); background: var(--accent-glow); border-left-color: var(--accent); }
        .sidebar-item svg { width: 16px; height: 16px; opacity: 0.6; }
        .sidebar-item:hover svg, .sidebar-item.active svg { opacity: 1; color: var(--accent); }
        .sidebar-bottom { margin-top: auto; padding: 24px; border-top: 1px solid var(--border); }
        .theme-toggle-container { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; padding: 10px 14px; background: rgba(255,255,255,0.03); border: 1px solid var(--border); border-radius: 12px; }
        .theme-label { font-size: 0.75rem; color: var(--muted); font-weight: 500; }
        .theme-toggle { width: 32px; height: 32px; border-radius: 50%; background: var(--surface); border: 1px solid var(--border); display: flex; align-items: center; justify-content: center; cursor: pointer; color: var(--header-text); transition: 0.3s; }
        .theme-toggle:hover { border-color: var(--accent); transform: scale(1.05); }

        .admin-info { font-size: 0.78rem; color: var(--muted); margin-bottom: 12px; line-height: 1.5; }
        .admin-info strong { display: block; color: var(--text); font-weight: 500; font-size: 0.85rem; }
        .btn-logout { display: block; text-align: center; padding: 9px; border-radius: 8px; background: var(--red-glow); border: 1px solid rgba(248,113,113,0.2); color: var(--red); font-size: 0.8rem; font-weight: 500; text-decoration: none; transition: all 0.2s; }
        .btn-logout:hover { background: rgba(248,113,113,0.2); }
        
        main { flex: 1; margin-left: 230px; padding: 40px 60px; transition: var(--transition); }
        
        .header { margin-bottom: 40px; }
        .title { font-family: 'Syne', sans-serif; font-size: 2.2rem; font-weight: 800; color: var(--header-text); margin-bottom: 8px; letter-spacing: -1px; }
        .subtitle { color: var(--muted); font-size: 0.9rem; }
        .badge { display: inline-block; padding: 3px 10px; border-radius: 4px; font-size: 0.7rem; font-weight: 600; background: rgba(167,139,250,0.15); color: var(--accent); margin-bottom: 12px; }

        .grid-layout { display: grid; grid-template-columns: repeat(auto-fit, minmax(380px, 1fr)); gap: 30px; align-items: start; }
        
        .panel { background: var(--card); border: 1px solid var(--border); border-radius: 20px; padding: 28px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); }
        .panel-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; border-bottom: 1px solid var(--border); padding-bottom: 16px; }
        .panel-title { font-family: 'Syne', sans-serif; font-size: 1.1rem; font-weight: 700; color: var(--header-text); }
        
        /* FORMS */
        .form-grid { display: grid; grid-template-columns: 1fr; gap: 16px; margin-bottom: 16px; }
        @media(min-width: 600px) { .form-grid { grid-template-columns: 1fr 1fr; } }
        
        .form-group { margin-bottom: 16px; }
        .form-label { display: block; font-size: 0.75rem; color: var(--muted); margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.05em; font-weight: 500; }
        
        .form-input { width: 100%; background: var(--surface-alpha); border: 1px solid var(--border); border-radius: 8px; padding: 10px 14px; color: var(--text); font-family: 'Inter', sans-serif; font-size: 0.85rem; outline: none; transition: 0.2s; appearance: none; -webkit-appearance: none; }
        .form-input:focus { border-color: var(--accent); background: var(--accent-glow); }
        
        /* CUSTOM PREMIUM SELECT */
        .custom-select-wrapper { position: relative; user-select: none; width: 100%; }
        .custom-select {
            display: flex; align-items: center; justify-content: space-between;
            background: var(--surface-alpha); border: 1px solid var(--border);
            border-radius: 8px; padding: 0 14px; color: var(--text);
            font-family: 'Inter', sans-serif; font-size: 0.85rem; cursor: pointer; transition: 0.2s;
            height: 44px; width: 100%;
        }
        .custom-select:hover { border-color: var(--accent); background: var(--accent-glow); }
        .custom-select.open { border-color: var(--accent); box-shadow: 0 0 0 4px var(--accent-glow); }
        .custom-select-arrow { transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1); color: var(--accent); display: flex; }
        .custom-select.open .custom-select-arrow { transform: rotate(180deg); }
        .custom-options {
            position: absolute; top: calc(100% + 8px); left: 0; right: 0;
            background: var(--card); border: 1px solid var(--border); border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.5); overflow: hidden;
            opacity: 0; pointer-events: none; transform: translateY(-10px);
            transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1); z-index: 100;
        }
        .custom-select.open + .custom-options { opacity: 1; pointer-events: auto; transform: translateY(0); }
        .custom-option {
            padding: 12px 14px; font-size: 0.85rem; color: var(--text);
            cursor: pointer; transition: 0.2s; font-family: 'Inter', sans-serif;
            border-bottom: 1px solid var(--border-alpha);
        }
        .custom-option:last-child { border-bottom: none; }
        .custom-option:hover { background: var(--surface-alpha); color: var(--header-text); padding-left: 18px; }
        .custom-option.selected { background: var(--accent-glow); color: var(--accent); font-weight: 600; border-left: 3px solid var(--accent); padding-left: 15px; }

        
        .form-file { font-size: 0.8rem; color: var(--muted); }
        .form-file::file-selector-button { background: var(--surface-alpha); border: 1px solid var(--border); color: var(--text); padding: 6px 12px; border-radius: 6px; cursor: pointer; margin-right: 12px; font-family: 'Inter', sans-serif; }
        
        .btn { background: var(--accent); color: #000; border: none; padding: 10px 20px; border-radius: 8px; font-weight: 600; font-size: 0.8rem; cursor: pointer; transition: 0.2s; font-family: 'Inter', sans-serif; width: 100%; }
        .btn:hover { opacity: 0.9; }

        /* LISTS */
        .list-wrap { margin-top: 30px; display: flex; flex-direction: column; gap: 12px; }
        .list-item { display: flex; align-items: center; justify-content: space-between; background: var(--surface-alpha); border: 1px solid var(--border); padding: 12px 16px; border-radius: 10px; }
        .list-info { display: flex; align-items: center; gap: 14px; }
        .list-img { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; background: var(--surface-alpha); display: flex; align-items: center; justify-content: center; font-size: 10px; color: var(--muted); }
        .list-text strong { display: block; font-size: 0.9rem; color: var(--header-text); margin-bottom: 2px; }
        .list-text span { font-size: 0.75rem; color: var(--muted); }
        
        .btn-sm-del { background: rgba(239,68,68,0.1); color: #ef4444; border: 1px solid rgba(239,68,68,0.2); width: 30px; height: 30px; border-radius: 8px; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: 0.2s; }
        .btn-sm-del:hover { background: #ef4444; color: white; }

        /* CUSTOM STEPPER DESIGN */
        .num-stepper { display: flex; align-items: center; gap: 8px; position: relative; }
        .num-stepper .input-wrap { position: relative; flex: 1; }
        .num-stepper input[type="number"] { -moz-appearance: textfield; }
        .num-stepper input[type="number"]::-webkit-outer-spin-button,
        .num-stepper input[type="number"]::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
        .stepper-btns { display: flex; flex-direction: column; gap: 3px; flex-shrink: 0; }
        .step-btn {
            width: 32px; height: 22px; border-radius: 6px; border: 1px solid rgba(255,255,255,0.1);
            background: rgba(255,255,255,0.03); color: var(--text); cursor: pointer;
            display: flex; align-items: center; justify-content: center; transition: 0.2s;
            font-size: 0.7rem;
        }
        .step-btn:hover { border-color: var(--accent); color: var(--accent); background: var(--accent-glow); }

        .alert { padding: 12px 16px; border-radius: 8px; background: rgba(74,222,128,0.1); color: #4ade80; border: 1px solid rgba(74,222,128,0.2); font-size: 0.85rem; margin-bottom: 20px; transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1); max-height: 100px; overflow: hidden; transform-origin: top; opacity: 1; }
        .alert.error { background: rgba(239,68,68,0.1); color: #ef4444; border-color: rgba(239,68,68,0.2); }
        .alert.hide { opacity: 0; max-height: 0; padding-top: 0; padding-bottom: 0; margin-bottom: 0; border-width: 0; transform: scaleY(0.8) translateY(-10px); }

        /* Modal Crop */
        .modal-crop { position: fixed; inset: 0; background: rgba(0,0,0,0.8); backdrop-filter: blur(5px); display: none; align-items: center; justify-content: center; z-index: 1000; }
        .modal-crop.show { display: flex; }
        .crop-container { background: #131c2e; padding: 20px; width: 90%; max-width: 500px; border-radius: 12px; border: 1px solid var(--border); }
        .img-container { width: 100%; max-height: 400px; margin-bottom: 20px; }
        .img-container img { max-width: 100%; }
        .crop-actions { display: flex; gap: 10px; justify-content: flex-end; }
        .btn-cancel { background: var(--surface-alpha); color: var(--text); border: 1px solid var(--border); padding: 8px 16px; border-radius: 6px; cursor: pointer; }

        /* ── DELETE CONFIRM MODAL ─────────────── */
        .del-modal-overlay {
            position: fixed; inset: 0; z-index: 2000;
            background: rgba(0,0,0,0.65); backdrop-filter: blur(6px);
            display: flex; align-items: center; justify-content: center;
            opacity: 0; pointer-events: none;
            transition: opacity 0.25s ease;
        }
        .del-modal-overlay.show { opacity: 1; pointer-events: auto; }
        .del-modal {
            background: var(--card); border: 1px solid var(--border);
            border-radius: 20px; padding: 32px 28px;
            max-width: 380px; width: 90%;
            transform: translateY(20px) scale(0.97);
            transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
            text-align: center;
            box-shadow: 0 20px 60px rgba(0,0,0,0.4);
        }
        .del-modal-overlay.show .del-modal { transform: translateY(0) scale(1); }
        .del-modal-icon {
            width: 56px; height: 56px; border-radius: 16px;
            background: rgba(239,68,68,0.12); border: 1px solid rgba(239,68,68,0.25);
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 20px; color: #ef4444;
        }
        .del-modal h4 {
            font-family: 'Syne', sans-serif; font-size: 1.15rem; font-weight: 800;
            color: var(--header-text); margin-bottom: 10px;
        }
        .del-modal p { font-size: 0.85rem; color: var(--muted); line-height: 1.6; margin-bottom: 6px; }
        .del-modal-target {
            font-size: 0.88rem; font-weight: 700; color: var(--text);
            background: var(--surface-alpha); border: 1px solid var(--border);
            border-radius: 8px; padding: 8px 14px; margin: 12px 0 24px;
            font-family: 'Syne', sans-serif;
        }
        .del-modal-actions { display: flex; gap: 10px; }
        .btn-del-cancel {
            flex: 1; padding: 12px; border-radius: 10px; border: 1px solid var(--border);
            background: var(--surface-alpha); color: var(--text); font-weight: 600;
            font-size: 0.85rem; cursor: pointer; transition: 0.2s; font-family: 'Inter', sans-serif;
        }
        .btn-del-cancel:hover { border-color: var(--accent); color: var(--accent); }
        .btn-del-confirm {
            flex: 1; padding: 12px; border-radius: 10px; border: none;
            background: #ef4444; color: white; font-weight: 700;
            font-size: 0.85rem; cursor: pointer; transition: 0.2s; font-family: 'Inter', sans-serif;
        }
        .btn-del-confirm:hover { background: #dc2626; transform: translateY(-1px); box-shadow: 0 6px 20px rgba(239,68,68,0.35); }
    </style>
</head>
<body>

<aside class="sidebar">
    <div class="sidebar-logo">Tix<span>Now</span></div>
    <div class="sidebar-label">Menu</div>
    <a href="admin_dashboard" class="sidebar-item">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
        Dashboard
    </a>
    <a href="admin_profile" class="sidebar-item">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
        Profil Saya
    </a>
    <a href="admin_scan" class="sidebar-item">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/></svg>
        Scan Tiket
    </a>

    <a href="admin_vouchers" class="sidebar-item<?php echo basename($_SERVER['PHP_SELF']) == 'admin_vouchers.php' ? ' active' : ''; ?>">
        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/></svg>
        Voucher
    </a>
    <a href="admin_history" class="sidebar-item">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        Riwayat User
    </a>
    <a href="admin_event" class="sidebar-item active">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3"/></svg>
        Event
    </a>
    <a href="admin_venue" class="sidebar-item">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
        Venue
    </a>
    <div class="sidebar-bottom">
        <div class="theme-toggle-container">
            <span class="theme-label">Tampilan</span>
            <div class="theme-toggle" id="themeToggle" onclick="toggleAdminTheme()">
                <svg id="moonIcon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path></svg>
                <svg id="sunIcon" style="display:none;" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"></circle><line x1="12" y1="1" x2="12" y2="3"></line><line x1="12" y1="21" x2="12" y2="23"></line><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line><line x1="1" y1="12" x2="3" y2="12"></line><line x1="21" y1="12" x2="23" y2="12"></line><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line></svg>
            </div>
        </div>
        <div class="admin-info" style="display: flex; align-items: center; gap: 12px; margin-bottom: 15px;">
            <?php if (!empty($admin_profile['foto_profil'])): ?>
                <img src="<?php echo htmlspecialchars($admin_profile['foto_profil']); ?>" style="width: 38px; height: 38px; border-radius: 10px; object-fit: cover; border: 1px solid var(--border);">
            <?php else: ?>
                <div style="width: 38px; height: 38px; border-radius: 10px; background: var(--accent-dim); color: var(--accent); display: flex; align-items: center; justify-content: center; font-weight: 800; font-family: 'Syne', sans-serif; font-size: 0.9rem; border: 1px solid var(--accent-glow);"><?php echo $admin_initials; ?></div>
            <?php endif; ?>
            <div style="overflow: hidden;">
                <strong style="display: block; color: var(--text); font-weight: 600; font-size: 0.82rem; white-space: nowrap; text-overflow: ellipsis; overflow: hidden;"><?php echo htmlspecialchars($admin_profile['nama']); ?></strong>
                <span style="display: block; font-size: 0.68rem; color: var(--muted); text-transform: uppercase; letter-spacing: 0.05em; margin-top: 1px;">Administrator</span>
            </div>
        </div>
        <a href="logout" class="btn-logout">Keluar</a>
    </div>
</aside>

<main>
    <div class="header">
        <div class="badge"><?php echo htmlspecialchars($konser['status']); ?></div>
        <h1 class="title"><?php echo htmlspecialchars($konser['nama_event']); ?></h1>
        <p class="subtitle">Atur detail anggota band dan daftar lagu untuk event ini</p>
    </div>

    <?php if ($msg === 'success'): ?>
        <div class="alert">Data berhasil diperbarui.</div>
    <?php elseif ($msg === 'error'): ?>
        <div class="alert error">Terjadi kesalahan. Pastikan file gambar valid jika mengupload foto.</div>
    <?php elseif ($msg === 'over_capacity'): ?>
        <div class="alert error">Gagal: Total stok tiket melebihi kapasitas maksimal venue (<?php echo number_format($konser['kapasitas'] ?? 0, 0, ',', '.'); ?>).</div>
    <?php endif; ?>

    <div class="grid-layout" style="grid-template-columns: 1fr; margin-bottom: 24px;">
        <!-- PANEL GATEKEEPER SETTINGS -->
        <div class="panel">
            <div class="panel-header">
                <div class="panel-title">Gatekeeper & Queue Settings</div>
            </div>
            <form action="admin_manage_action" method="POST" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; align-items: flex-end;">
                <input type="hidden" name="action" value="update_gatekeeper">
                <input type="hidden" name="id_event" value="<?php echo $id_event; ?>">
                
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label">Status Antrean</label>
                    <select name="is_queue_active" class="form-input">
                        <option value="1" <?php echo ($konser['is_queue_active'] ?? 0) == 1 ? 'selected' : ''; ?>>AKTIF (Wajib Antre)</option>
                        <option value="0" <?php echo ($konser['is_queue_active'] ?? 0) == 0 ? 'selected' : ''; ?>>NON-AKTIF (Langsung Beli)</option>
                    </select>
                </div>
                
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label">Limit Checkout (Concurrent)</label>
                    <div class="num-stepper">
                        <div class="input-wrap">
                            <input type="number" name="max_concurrent_checkout" id="max_con" class="form-input" value="<?php echo $konser['max_concurrent_checkout'] ?? 100; ?>" placeholder="Cth: 100">
                        </div>
                        <div class="stepper-btns">
                            <button type="button" class="step-btn" onclick="stepInput('max_con', 10)"><svg width="10" height="10" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7"/></svg></button>
                            <button type="button" class="step-btn" onclick="stepInput('max_con', -10)"><svg width="10" height="10" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg></button>
                        </div>
                    </div>
                </div>
                
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label">Threshold Antrean (Traffic)</label>
                    <div class="num-stepper">
                        <div class="input-wrap">
                            <input type="number" name="queue_threshold" id="q_thresh" class="form-input" value="<?php echo $konser['queue_threshold'] ?? 10; ?>" placeholder="Cth: 10">
                        </div>
                        <div class="stepper-btns">
                            <button type="button" class="step-btn" onclick="stepInput('q_thresh', 1)"><svg width="10" height="10" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7"/></svg></button>
                            <button type="button" class="step-btn" onclick="stepInput('q_thresh', -1)"><svg width="10" height="10" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg></button>
                        </div>
                    </div>
                </div>
                
                <button type="submit" class="btn">Simpan Pengaturan</button>
            </form>
        </div>
    </div>

    <div class="grid-layout" style="align-items: start;">
        <!-- COLUMN 1: LINEUP -->
        <div style="display: flex; flex-direction: column; gap: 30px;">
            <!-- PANEL LINEUP -->
            <div class="panel">
            <div class="panel-header">
                <div class="panel-title">Lineup Member</div>
            </div>
            
            <form id="formAddMember" action="admin_manage_action" method="POST">
                <input type="hidden" name="action" value="add_member">
                <input type="hidden" name="id_event" value="<?php echo $id_event; ?>">
                <input type="hidden" name="foto_base64" id="fotoBase64" required>
                
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px;">
                    <div class="form-group">
                        <label class="form-label">Nama Member</label>
                        <input type="text" name="nama_member" required class="form-input" placeholder="Cth: John Doe">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Posisi / Peran</label>
                        <input type="text" name="peran" required class="form-input" placeholder="Cth: Vokalis">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Pilih Foto (Wajib Upload)</label>
                    <input type="file" id="fotoInput" accept="image/*" class="form-file" required>
                </div>
                
                <div id="previewWrap" style="display:none; margin-bottom: 16px; align-items:center; gap: 14px;">
                    <img id="fotoPreview" style="width: 50px; height: 50px; border-radius: 50%; object-fit: cover; border: 2px solid var(--accent);">
                    <span style="font-size: 0.8rem; color: #4ade80;">Foto berhasil dicrop ✓</span>
                </div>

                <button type="submit" class="btn">Tambah Member</button>
            </form>

            <div class="list-wrap">
                <?php if(empty($lineup)): ?>
                    <div style="color:var(--muted); font-size:0.8rem; text-align:center; padding: 20px;">Belum ada member ditambahkan.</div>
                <?php else: ?>
                    <?php foreach($lineup as $m): ?>
                    <div class="list-item">
                        <div class="list-info">
                            <?php if(!empty($m['foto_url'])): ?>
                                <img src="<?php echo htmlspecialchars($m['foto_url']); ?>" class="list-img" alt="">
                            <?php else: ?>
                                <div class="list-img">No Img</div>
                            <?php endif; ?>
                            <div class="list-text">
                                <strong><?php echo htmlspecialchars($m['nama_member']); ?></strong>
                                <span><?php echo htmlspecialchars($m['peran']); ?></span>
                            </div>
                        </div>
                        <form id="del_member_<?php echo $m['id']; ?>" action="admin_manage_action" method="POST">
                            <input type="hidden" name="action" value="del_member">
                            <input type="hidden" name="id" value="<?php echo $m['id']; ?>">
                            <input type="hidden" name="id_event" value="<?php echo $id_event; ?>">
                            <button type="button" class="btn-sm-del" onclick="openDeleteModal('del_member_<?php echo $m['id']; ?>', '<?php echo htmlspecialchars(addslashes($m['nama_member'])); ?>')">
                                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            </button>
                        </form>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- PANEL TIKET -->
        <div class="panel">
            <div class="panel-header" style="margin-bottom: 15px;">
                <div class="panel-title">Kategori & Harga Tiket</div>
            </div>
            
            <div style="background: var(--surface-alpha); padding: 12px 16px; border-radius: 10px; margin-bottom: 20px; font-size: 0.85rem; border: 1px solid var(--border); display: flex; justify-content: space-between;">
                <span style="color: var(--muted);">Kapasitas Venue: <strong style="color: var(--text);"><?php echo number_format($konser['kapasitas'] ?? 0, 0, ',', '.'); ?></strong></span>
                <span style="color: var(--muted);">Sisa Kuota: <strong style="color: var(--accent);"><?php echo number_format($sisa_kuota, 0, ',', '.'); ?></strong></span>
            </div>
            
            <form action="admin_manage_action" method="POST">
                <input type="hidden" name="action" value="add_category">
                <input type="hidden" name="id_event" value="<?php echo $id_event; ?>">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Nama Kategori</label>
                        <input type="text" name="nama_tiket" required class="form-input" placeholder="Cth: VIP, Festival, Tribune">
                    </div>
                    <?php if (count($schedule) > 1): ?>
                    <div class="form-group">
                        <label class="form-label">Tiket Untuk Band</label>
                        <select name="id_schedule" class="form-input" style="height:44px; padding:0 14px;">
                            <option value="">Semua Band (Umum)</option>
                            <?php foreach ($schedule as $s): ?>
                                <option value="<?php echo $s['id']; ?>"><?php echo htmlspecialchars($s['nama_band']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                </div>
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px; margin-bottom:16px;">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label">Harga (Rp)</label>
                        <div class="num-stepper">
                            <div class="input-wrap">
                                <input type="number" name="harga" id="cat_price" required class="form-input" placeholder="Cth: 500000">
                            </div>
                            <div class="stepper-btns">
                                <button type="button" class="step-btn" onclick="stepInput('cat_price', 10000)"><svg width="10" height="10" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7"/></svg></button>
                                <button type="button" class="step-btn" onclick="stepInput('cat_price', -10000)"><svg width="10" height="10" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg></button>
                            </div>
                        </div>
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label">Stok (Sisa: <?php echo $sisa_kuota; ?>)</label>
                        <div class="num-stepper">
                            <div class="input-wrap">
                                <input type="number" name="stok" id="cat_stock" required max="<?php echo $sisa_kuota; ?>" class="form-input" placeholder="Cth: 100">
                            </div>
                            <div class="stepper-btns">
                                <button type="button" class="step-btn" onclick="stepInput('cat_stock', 5)"><svg width="10" height="10" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7"/></svg></button>
                                <button type="button" class="step-btn" onclick="stepInput('cat_stock', -5)"><svg width="10" height="10" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg></button>
                            </div>
                        </div>
                    </div>
                </div>
                <button type="submit" class="btn">Tambah Kategori</button>
            </form>

            <div class="list-wrap">
                <?php if(empty($categories)): ?>
                    <div style="color:var(--muted); font-size:0.8rem; text-align:center; padding: 20px;">Belum ada kategori tiket.</div>
                <?php else: ?>
                    <?php foreach($categories as $c): ?>
                    <div class="list-item">
                        <div class="list-info">
                            <div class="list-text">
                                <div style="display:flex; align-items:center; gap:8px;">
                                    <strong><?php echo htmlspecialchars($c['nama_tiket']); ?></strong>
                                    <?php if ($c['id_schedule']): ?>
                                        <?php 
                                            $bName = '';
                                            foreach($schedule as $sc) if($sc['id'] == $c['id_schedule']) $bName = $sc['nama_band'];
                                        ?>
                                        <span style="font-size:0.6rem; background:var(--accent-dim); color:var(--accent); padding:2px 6px; border-radius:4px;"><?php echo htmlspecialchars($bName); ?></span>
                                    <?php endif; ?>
                                </div>
                                <span style="font-size: 0.8rem; opacity:0.7;">Rp <?php echo number_format($c['harga'], 0, ',', '.'); ?> • Stok: <?php echo $c['kuota']; ?></span>
                            </div>
                        </div>
                        <div style="display:flex; gap:8px;">
                            <button class="btn-sm-del" style="background:rgba(167,139,250,0.1); color:var(--accent); border-color:rgba(167,139,250,0.2);" 
                                onclick="openEditCat(<?php echo $c['id_tiket']; ?>, '<?php echo addslashes($c['nama_tiket']); ?>', <?php echo $c['harga']; ?>, <?php echo $c['kuota']; ?>, <?php echo $sisa_kuota; ?>)">
                                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                            </button>
                            <form id="del_cat_<?php echo $c['id_tiket']; ?>" action="admin_manage_action" method="POST">
                                <input type="hidden" name="action" value="del_category">
                                <input type="hidden" name="id" value="<?php echo $c['id_tiket']; ?>">
                                <input type="hidden" name="id_event" value="<?php echo $id_event; ?>">
                                <button type="button" class="btn-sm-del" onclick="openDeleteModal('del_cat_<?php echo $c['id_tiket']; ?>', 'Kategori: <?php echo htmlspecialchars(addslashes($c['nama_tiket'])); ?>')">
                                    <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </form>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        </div> <!-- END COLUMN 1 -->

        <!-- COLUMN 2: SETLIST & TIKET -->
        <div style="display: flex; flex-direction: column; gap: 30px;">
            <!-- PANEL SETLIST -->
            <div class="panel">
            <div class="panel-header">
                <div class="panel-title">Setlist Lagu</div>
            </div>

            <form action="admin_manage_action" method="POST">
                <input type="hidden" name="action" value="add_song">
                <input type="hidden" name="id_event" value="<?php echo $id_event; ?>">

                <div class="form-grid" style="margin-bottom: 12px;">
                    <?php if (!empty($schedule)): ?>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label">Band / Penampil</label>
                        <select name="id_schedule" class="form-input" style="height:44px; padding:0 14px;" id="setlist_band_select">
                            <option value="">— Umum (tanpa band tertentu)</option>
                            <?php foreach ($schedule as $sc): ?>
                                <option value="<?php echo $sc['id']; ?>"><?php echo htmlspecialchars($sc['nama_band']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label">Judul Lagu</label>
                        <input type="text" name="judul_lagu" required class="form-input" placeholder="Masukkan judul lagu...">
                    </div>
                </div>
                <div style="display: flex; gap: 10px; align-items: center; margin-bottom: 16px;">
                    <div class="form-group" style="width: 120px; margin-bottom: 0;">
                        <label class="form-label">No. Urut</label>
                        <div class="num-stepper">
                            <div class="input-wrap">
                                <input type="number" name="urutan" id="song_order" required class="form-input" value="<?php echo count($setlist) + 1; ?>">
                            </div>
                            <div class="stepper-btns">
                                <button type="button" class="step-btn" onclick="stepInput('song_order', 1)"><svg width="10" height="10" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7"/></svg></button>
                                <button type="button" class="step-btn" onclick="stepInput('song_order', -1)"><svg width="10" height="10" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg></button>
                            </div>
                        </div>
                    </div>
                    <button type="submit" class="btn" style="align-self: flex-end; margin-bottom: 0;">
                        <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
                        Tambah Lagu
                    </button>
                </div>
            </form>

            <?php if(empty($setlist)): ?>
                <div style="color:var(--muted); font-size:0.8rem; text-align:center; padding: 20px;">Belum ada lagu ditambahkan.</div>
            <?php else: ?>
                <?php
                    // Build schedule lookup and count songs for dynamic numbering
                    $schedLookup = [];
                    $songCounts = ['' => 0];
                    foreach ($schedule as $sc) {
                        $schedLookup[$sc['id']] = $sc['nama_band'];
                        $songCounts[$sc['id']] = 0;
                    }

                    // Group setlist by id_schedule
                    $setlistGrouped = [];
                    foreach ($setlist as $s) {
                        $key = !empty($s['id_schedule']) ? (string)$s['id_schedule'] : '__general__';
                        $countKey = !empty($s['id_schedule']) ? (string)$s['id_schedule'] : '';
                        
                        $setlistGrouped[$key][] = $s;
                        if(isset($songCounts[$countKey])) $songCounts[$countKey]++;
                        else $songCounts[$countKey] = 1;
                    }
                ?>
                <script>
                    const songCountsMap = <?php echo json_encode($songCounts); ?>;
                </script>
                <?php foreach ($setlistGrouped as $bandKey => $songs): ?>
                <div style="margin-bottom: 20px;">
                    <!-- Band Group Header -->
                    <div style="display:flex; align-items:center; gap:8px; padding: 8px 12px; background: var(--surface-alpha); border-radius:8px; margin-bottom:8px; border-left: 3px solid var(--accent);">
                        <svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="color:var(--accent); flex-shrink:0;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3"/></svg>
                        <span style="font-family:'Syne',sans-serif; font-weight:700; font-size:0.82rem; color:var(--text);">
                            <?php echo $bandKey === '__general__' ? 'Setlist Umum' : htmlspecialchars($schedLookup[$bandKey] ?? 'Band'); ?>
                        </span>
                        <span style="margin-left:auto; font-size:0.72rem; color:var(--muted);"><?php echo count($songs); ?> lagu</span>
                    </div>
                    <!-- Songs in this band -->
                    <div class="list-wrap" style="margin-bottom:0;">
                        <?php foreach ($songs as $s): ?>
                        <div class="list-item">
                            <div class="list-info">
                                <div class="list-text" style="display:flex; align-items:center; gap:14px;">
                                    <span style="color:var(--accent); font-weight:700; width:24px; flex-shrink:0;"><?php echo str_pad($s['urutan'], 2, '0', STR_PAD_LEFT); ?>.</span>
                                    <strong style="margin-bottom:0; font-size:0.92rem; font-weight:500; font-family:'Syne',sans-serif;"><?php echo htmlspecialchars($s['judul_lagu']); ?></strong>
                                </div>
                            </div>
                            <form id="del_song_<?php echo $s['id']; ?>" action="admin_manage_action" method="POST">
                                <input type="hidden" name="action" value="del_song">
                                <input type="hidden" name="id" value="<?php echo $s['id']; ?>">
                                <input type="hidden" name="id_event" value="<?php echo $id_event; ?>">
                                <button type="button" class="btn-sm-del" onclick="openDeleteModal('del_song_<?php echo $s['id']; ?>', '<?php echo htmlspecialchars(addslashes($s['judul_lagu'])); ?>')">
                                    <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </form>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        </div> <!-- END COLUMN 2 -->
    </div>
</main>

<!-- Modal Cropper -->
<div class="modal-crop" id="modalCrop">
    <div class="crop-container">
        <h3 style="font-family:'Syne',sans-serif; margin-bottom:15px;">Sesuaikan Foto Member</h3>
        <div class="img-container">
            <img id="imageToCrop" src="">
        </div>
        <div class="crop-actions">
            <button class="btn-cancel" onclick="cancelCrop()">Batal</button>
            <button class="btn" style="width:auto;" onclick="doCrop()">Tetapkan Foto</button>
        </div>
    </div>
</div>

<!-- Modal Edit Kategori -->
<div class="modal-crop" id="modalEditCat">
    <div class="crop-container" style="max-width: 400px;">
        <h3 style="font-family:'Syne',sans-serif; margin-bottom:20px; color:white;">Edit Kategori Tiket</h3>
        <form action="admin_manage_action" method="POST">
            <input type="hidden" name="action" value="edit_category">
            <input type="hidden" name="id_event" value="<?php echo $id_event; ?>">
            <input type="hidden" name="id" id="ec_id">
            
            <div class="form-group">
                <label class="form-label">Nama Kategori</label>
                <input type="text" name="nama_tiket" id="ec_nama" required class="form-input">
            </div>
            <div class="form-group">
                <label class="form-label">Harga (Rp)</label>
                <div class="num-stepper">
                    <div class="input-wrap">
                        <input type="number" name="harga" id="ec_harga" required class="form-input">
                    </div>
                    <div class="stepper-btns">
                        <button type="button" class="step-btn" onclick="stepInput('ec_harga', 10000)"><svg width="10" height="10" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7"/></svg></button>
                        <button type="button" class="step-btn" onclick="stepInput('ec_harga', -10000)"><svg width="10" height="10" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg></button>
                    </div>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label" id="edit_stok_label">Stok</label>
                <div class="num-stepper">
                    <div class="input-wrap">
                        <input type="number" name="stok" id="ec_stok" required class="form-input">
                    </div>
                    <div class="stepper-btns">
                        <button type="button" class="step-btn" onclick="stepInput('ec_stok', 5)"><svg width="10" height="10" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7"/></svg></button>
                        <button type="button" class="step-btn" onclick="stepInput('ec_stok', -5)"><svg width="10" height="10" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg></button>
                    </div>
                </div>
            </div>
            
            <div class="crop-actions" style="margin-top: 24px;">
                <button type="button" class="btn-cancel" onclick="closeEditCat()">Batal</button>
                <button type="submit" class="btn" style="width:auto;">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>

<script>
    const fotoInput = document.getElementById('fotoInput');
    const modalCrop = document.getElementById('modalCrop');
    const imageToCrop = document.getElementById('imageToCrop');
    let cropper;

    fotoInput.addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(event) {
                imageToCrop.src = event.target.result;
                modalCrop.classList.add('show');
                
                if (cropper) cropper.destroy();
                
                cropper = new Cropper(imageToCrop, {
                    aspectRatio: 1, // Foto wajib rasiokotak 1:1 / lingkaran
                    viewMode: 1,
                    dragMode: 'move',
                    autoCropArea: 1,
                    restore: false,
                    guides: true,
                    center: true,
                    highlight: false,
                    cropBoxMovable: true,
                    cropBoxResizable: true,
                    toggleDragModeOnDblclick: false,
                });
            };
            reader.readAsDataURL(file);
        }
    });

    function cancelCrop() {
        modalCrop.classList.remove('show');
        if (cropper) cropper.destroy();
        fotoInput.value = '';
    }

    function stepInput(id, delta) {
        const el = document.getElementById(id);
        if (!el) return;
        const cur = parseInt(el.value) || 0;
        el.value = Math.max(0, cur + delta);
    }

    function doCrop() {
        if (!cropper) return;
        const canvas = cropper.getCroppedCanvas({
            width: 300,
            height: 300
        });
        
        const base64Str = canvas.toDataURL('image/png');
        document.getElementById('fotoBase64').value = base64Str;
        
        document.getElementById('fotoPreview').src = base64Str;
        document.getElementById('previewWrap').style.display = 'flex';
        
        modalCrop.classList.remove('show');
    }

    function openEditCat(id, nama, harga, stok, sisaKuotaBase) {
        document.getElementById('ec_id').value = id;
        document.getElementById('ec_nama').value = nama;
        document.getElementById('ec_harga').value = harga;
        
        const elStok = document.getElementById('ec_stok');
        elStok.value = stok;
        
        // For editing, max stock is the current base sisa kuota + this category's current stock
        const maxStok = sisaKuotaBase + stok;
        elStok.max = maxStok;
        document.getElementById('edit_stok_label').textContent = 'Stok (Sisa Maksimal: ' + maxStok + ')';
        
        document.getElementById('modalEditCat').classList.add('show');
    }
    function closeEditCat() {
        document.getElementById('modalEditCat').classList.remove('show');
    }
</script>

<!-- HTML Delete Modal -->
<div class="del-modal-overlay" id="deleteConfirmModal" onclick="if(event.target === this) closeDeleteModal()">
    <div class="del-modal">
        <div class="del-modal-icon">
            <svg width="28" height="28" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
        </div>
        <h4>Konfirmasi Hapus</h4>
        <p>Apakah Anda yakin ingin menghapus data ini? Tindakan ini tidak dapat dibatalkan.</p>
        <div class="del-modal-target" id="delModalTarget">-</div>
        <div class="del-modal-actions">
            <button type="button" class="btn-del-cancel" onclick="closeDeleteModal()">Batal</button>
            <button type="button" class="btn-del-confirm" onclick="executeDelete()">Ya, Hapus Data</button>
        </div>
    </div>
</div>

<script>
    let currentDeleteFormId = null;

    function openDeleteModal(formId, targetName) {
        currentDeleteFormId = formId;
        document.getElementById('delModalTarget').textContent = targetName;
        document.getElementById('deleteConfirmModal').classList.add('show');
    }

    function closeDeleteModal() {
        currentDeleteFormId = null;
        document.getElementById('deleteConfirmModal').classList.remove('show');
    }

    function executeDelete() {
        if (currentDeleteFormId) {
            document.getElementById(currentDeleteFormId).submit();
        }
    }

    // THEME TOGGLE LOGIC
    const moonIcon = document.getElementById('moonIcon');
    const sunIcon = document.getElementById('sunIcon');

    function updateIcons(theme) {
        if(!moonIcon || !sunIcon) return;
        moonIcon.style.display = theme === 'light' ? 'none' : 'block';
        sunIcon.style.display = theme === 'light' ? 'block' : 'none';
    }

    function toggleAdminTheme() {
        let current = document.documentElement.getAttribute('data-theme') || 'dark';
        let target = current === 'dark' ? 'light' : 'dark';
        document.documentElement.setAttribute('data-theme', target);
        localStorage.setItem('theme', target);
        updateIcons(target);
    }

    document.addEventListener('DOMContentLoaded', () => {
        updateIcons(document.documentElement.getAttribute('data-theme'));
        // Auto-dismiss alert
        const alert = document.querySelector('.alert');
        if (alert) setTimeout(() => alert.classList.add('hide'), 3500);

        // Dynamic Song Order based on selected band
        const bandSelect = document.getElementById('setlist_band_select');
        const orderInput = document.getElementById('song_order');
        if(bandSelect && orderInput && typeof songCountsMap !== 'undefined') {
            function updateSongOrder() {
                const count = songCountsMap[bandSelect.value] || 0;
                orderInput.value = count + 1;
            }
            bandSelect.addEventListener('change', updateSongOrder);
            updateSongOrder(); // Run on load
        }

        // Initialize Custom Premium Selects
        document.querySelectorAll('select.form-input').forEach(select => {
            select.style.display = 'none';

            const wrapper = document.createElement('div');
            wrapper.className = 'custom-select-wrapper';
            select.parentNode.insertBefore(wrapper, select);
            wrapper.appendChild(select);

            const customSelect = document.createElement('div');
            customSelect.className = 'custom-select';
            
            const selectedText = document.createElement('span');
            selectedText.textContent = select.options[select.selectedIndex]?.text || '';
            customSelect.appendChild(selectedText);
            
            const arrow = document.createElement('div');
            arrow.className = 'custom-select-arrow';
            arrow.innerHTML = `<svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>`;
            customSelect.appendChild(arrow);
            
            wrapper.appendChild(customSelect);

            const optionsList = document.createElement('div');
            optionsList.className = 'custom-options';
            
            Array.from(select.options).forEach(option => {
                const customOption = document.createElement('div');
                customOption.className = 'custom-option' + (option.selected ? ' selected' : '');
                customOption.textContent = option.text;
                
                customOption.addEventListener('click', () => {
                    select.value = option.value;
                    select.dispatchEvent(new Event('change'));
                    
                    selectedText.textContent = option.text;
                    
                    optionsList.querySelectorAll('.custom-option').forEach(opt => {
                        opt.classList.remove('selected');
                    });
                    
                    customOption.classList.add('selected');
                    customSelect.classList.remove('open');
                });
                
                optionsList.appendChild(customOption);
            });
            
            wrapper.appendChild(optionsList);

            customSelect.addEventListener('click', (e) => {
                e.stopPropagation();
                const isOpen = customSelect.classList.contains('open');
                document.querySelectorAll('.custom-select').forEach(sel => sel.classList.remove('open'));
                if (!isOpen) customSelect.classList.add('open');
            });
            
            // Sync with external changes if needed
            select.addEventListener('change', () => {
                selectedText.textContent = select.options[select.selectedIndex]?.text || '';
                Array.from(optionsList.children).forEach((opt, idx) => {
                    if (idx === select.selectedIndex) {
                        opt.classList.add('selected');
                    } else {
                        opt.classList.remove('selected');
                    }
                });
            });
        });

        document.addEventListener('click', () => {
            document.querySelectorAll('.custom-select').forEach(sel => sel.classList.remove('open'));
        });
    });
</script>

</body>
</html>
