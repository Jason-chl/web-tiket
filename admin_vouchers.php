<?php
session_start();
require_once 'koneksi.php';

if (!isset($_SESSION['id_user']) || $_SESSION['role'] !== 'admin') {
    header("Location: index");
    exit;
}

$id_user = $_SESSION['id_user'];
$stmt_admin = $conn->prepare("SELECT * FROM users WHERE id_user = ?");
$stmt_admin->execute([$id_user]);
$admin = $stmt_admin->fetch();
$initials = strtoupper(substr($admin['nama'] ?? 'A', 0, 1));

// Handle Add Voucher
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    $code = strtoupper(trim($_POST['code']));
    $discount = (int)$_POST['discount_amount'];
    $id_event = (int)$_POST['id_event'];
    $max_usage = (int)$_POST['max_usage'];

    try {
        $stmt = $conn->prepare("INSERT INTO vouchers (code, discount_amount, id_event, max_usage) VALUES (?, ?, ?, ?)");
        $stmt->execute([$code, $discount, $id_event === 0 ? null : $id_event, $max_usage]);
        $success = "Voucher $code berhasil ditambahkan!";
    } catch (PDOException $e) {
        $error = "Gagal menambahkan voucher: " . $e->getMessage();
    }
}

// Handle Delete
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $conn->prepare("DELETE FROM vouchers WHERE id_voucher = ?")->execute([$id]);
    header("Location: admin_vouchers?msg=deleted");
    exit;
}

// Fetch Vouchers
$vouchers = $conn->query("
    SELECT v.*, e.nama_event 
    FROM vouchers v 
    LEFT JOIN event e ON v.id_event = e.id_event 
    ORDER BY v.created_at DESC
")->fetchAll();

// Fetch Events for Dropdown
$events = $conn->query("SELECT id_event, nama_event FROM event WHERE status != 'cancelled' ORDER BY nama_event ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Voucher — TixNow Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;500;600;700;800&family=Inter:wght@300;400;500;600&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
    <script>
        (function() {
            const savedTheme = localStorage.getItem('theme') || 'dark';
            document.documentElement.setAttribute('data-theme', savedTheme);
        })();
    </script>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root {
            --bg: #07090f; --surface: #0d1117; --card: #0f1521; --card2: #111827;
            --border: rgba(255,255,255,0.06); 
            --border-alpha: rgba(255,255,255,0.1);
            --surface-alpha: rgba(255,255,255,0.04);
            --accent: #a78bfa; --accent-glow: rgba(167,139,250,0.15);
            --red: #f87171; --red-glow: rgba(248,113,113,0.12); --green: #34d399;
            --text: #e2e8f0; --muted: #4b5a72; --header-text: #ffffff;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        [data-theme="light"] {
            --bg: #f3f4f6; --surface: #ffffff; --card: #ffffff; --card2: #f8fafc;
            --border: rgba(0,0,0,0.06); 
            --border-alpha: rgba(0,0,0,0.08);
            --surface-alpha: rgba(0,0,0,0.03);
            --accent: #6d28d9; --accent-glow: rgba(109, 40, 217, 0.12);
            --red: #ef4444; --red-glow: rgba(239, 68, 68, 0.08); --green: #10b981;
            --text: #374151; --muted: #6b7280; --header-text: #111827;
        }        body { font-family: 'Inter', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; display: flex; transition: var(--transition); }
        
        .sidebar { width: 230px; min-height: 100vh; background: var(--surface); border-right: 1px solid var(--border); display: flex; flex-direction: column; padding: 28px 0; position: fixed; top: 0; left: 0; bottom: 0; z-index: 50; transition: var(--transition); }
        .sidebar-logo { padding: 0 24px 32px; font-family: 'Syne', sans-serif; font-size: 1.3rem; font-weight: 800; color: var(--header-text); letter-spacing: -0.5px; }
        .sidebar-logo span { color: var(--accent); }
        .sidebar-label { font-size: 0.65rem; font-weight: 600; letter-spacing: 0.12em; text-transform: uppercase; color: var(--muted); padding: 0 24px 10px; }
        .sidebar-item { display: flex; align-items: center; gap: 10px; padding: 10px 24px; color: var(--muted); font-size: 0.85rem; cursor: pointer; transition: all 0.2s; border-left: 2px solid transparent; text-decoration: none; }
        .sidebar-item:hover, .sidebar-item.active { color: var(--text); background: var(--accent-glow); border-left-color: var(--accent); }
        .sidebar-item svg { width: 16px; height: 16px; opacity: 0.6; flex-shrink: 0; }
        .sidebar-item:hover svg, .sidebar-item.active svg { opacity: 1; color: var(--accent); }
        .sidebar-bottom { margin-top: auto; padding: 24px; border-top: 1px solid var(--border); }
        .theme-toggle-container { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; padding: 10px 14px; background: rgba(255,255,255,0.03); border: 1px solid var(--border); border-radius: 12px; position: relative; z-index: 1000; transition: var(--transition); }
        .theme-label { font-size: 0.75rem; color: var(--muted); font-weight: 500; }
        .theme-toggle { width: 32px; height: 32px; border-radius: 50%; background: var(--surface); border: 1px solid var(--border); display: flex; align-items: center; justify-content: center; cursor: pointer; color: var(--header-text); transition: var(--transition); position: relative; z-index: 999; pointer-events: auto; }
        .theme-toggle:hover { border-color: var(--accent); transform: scale(1.1); }
        .admin-info { font-size: 0.78rem; color: var(--muted); margin-bottom: 12px; line-height: 1.5; }
        .admin-info strong { display: block; color: var(--text); font-weight: 500; font-size: 0.85rem; }
        .btn-logout { display: block; text-align: center; padding: 9px; border-radius: 8px; background: var(--red-glow); border: 1px solid rgba(248,113,113,0.2); color: var(--red); font-size: 0.8rem; font-weight: 500; text-decoration: none; transition: all 0.2s; }
        .btn-logout:hover { background: rgba(248,113,113,0.2); }

        .main { flex: 1; margin-left: 230px; padding: 40px; transition: var(--transition); }
        .page-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 30px; }
        .page-title { font-family: 'Syne', sans-serif; font-size: 1.6rem; font-weight: 700; color: var(--header-text); letter-spacing: -0.5px; }
        .btn-add { background: var(--accent); color: #fff; padding: 10px 24px; border-radius: 99px; border: none; font-weight: 700; font-size: 0.85rem; cursor: pointer; display: flex; align-items: center; gap: 8px; transition: var(--transition); box-shadow: 0 8px 20px var(--accent-glow); }
        .btn-add:hover { transform: translateY(-2px); opacity: 0.9; box-shadow: 0 12px 25px var(--accent-glow); }

        .v-card { background: var(--card); border: 1px solid var(--border); border-radius: 20px; overflow: hidden; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 20px; font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.12em; color: var(--muted); background: var(--card2); font-weight: 600; }
        td { padding: 20px; font-size: 0.85rem; border-top: 1px solid var(--border); vertical-align: middle; }
        .v-code { font-family: 'Space Mono', monospace; background: var(--accent-glow); color: var(--accent); padding: 6px 12px; border-radius: 8px; font-weight: 700; border: 1px solid rgba(167,139,250,0.2); }
        .v-amount { font-weight: 700; color: var(--green); font-family: 'Space Mono', monospace; }
        .usage-bar { width: 100px; height: 6px; background: var(--surface-alpha); border-radius: 3px; overflow: hidden; margin-top: 6px; border: 1px solid var(--border); }
        .usage-fill { height: 100%; background: var(--accent); transition: 1s ease-out; }
        .btn-delete { color: var(--red); cursor: pointer; background: var(--red-glow); border: 1px solid rgba(248,113,113,0.1); padding: 8px; border-radius: 8px; transition: 0.2s; display: flex; align-items: center; justify-content: center; }
        .btn-delete:hover { background: var(--red); color: white; transform: rotate(8deg); }
        .btn-cancel { width: 100%; background: var(--surface-alpha); border: 1px solid var(--border); padding: 14px 18px; border-radius: 12px; color: var(--muted); font-family: 'Inter', sans-serif; font-size: 0.9rem; font-weight: 700; transition: 0.2s; cursor: pointer; }
        .btn-cancel:hover { background: var(--red-glow); color: var(--red); border-color: var(--red-glow); }

        /* MODAL REDESIGN */
        .modal { position: fixed; inset: 0; background: rgba(0,0,0,0.85); backdrop-filter: blur(8px); display: none; align-items: center; justify-content: center; z-index: 1000; padding: 20px; opacity: 0; transition: 0.3s ease; }
        .modal.open { display: flex; opacity: 1; }
        .modal-content { background: var(--card); border: 1px solid var(--border); padding: 40px; border-radius: 24px; width: 100%; max-width: 480px; transform: scale(0.9); transition: 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275); box-shadow: 0 40px 100px rgba(0,0,0,0.5); }
        .modal.open .modal-content { transform: scale(1); }
        .form-group { margin-bottom: 24px; }
        .form-label { display: block; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.1em; color: var(--muted); margin-bottom: 10px; }
        .form-input { width: 100%; background: var(--bg); border: 1px solid var(--border); padding: 14px 18px; border-radius: 12px; color: var(--text); font-family: 'Inter', sans-serif; font-size: 0.9rem; transition: 0.2s; }
        .form-input:focus { border-color: var(--accent); background: var(--surface); box-shadow: 0 0 0 4px var(--accent-glow); outline: none; }

        .num-stepper { display: flex; align-items: center; gap: 12px; background: var(--bg); padding: 6px; border-radius: 14px; border: 1px solid var(--border); }
        .num-stepper input { border: none !important; background: transparent !important; box-shadow: none !important; text-align: center; font-weight: 700; font-family: 'Space Mono', monospace; font-size: 1.1rem; -moz-appearance: textfield; }
        .num-stepper input::-webkit-outer-spin-button, .num-stepper input::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
        .step-btn { width: 38px; height: 38px; border-radius: 10px; border: 1px solid var(--border); background: var(--card2); color: var(--text); cursor: pointer; display: flex; align-items: center; justify-content: center; transition: 0.2s; }
        .step-btn:hover { background: var(--accent); color: #000; border-color: var(--accent); }

        /* CUSTOM SELECT MODAL */
        .c-select-wrapper { position: relative; }
        .c-select-trigger { width: 100%; background: var(--bg); border: 1px solid var(--border); padding: 14px 18px; border-radius: 12px; color: var(--text); font-family: 'Inter', sans-serif; font-size: 0.9rem; transition: 0.2s; cursor: pointer; display: flex; align-items: center; justify-content: space-between; }
        .c-select-trigger:hover { border-color: var(--border-alpha); }
        .c-select-trigger.active { border-color: var(--accent); background: var(--surface); box-shadow: 0 0 0 4px var(--accent-glow); }
        .c-select-trigger svg { transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1); opacity: 0.6; }
        .c-select-trigger.active svg { transform: rotate(180deg); opacity: 1; color: var(--accent); }
        
        .c-select-options { position: absolute; top: calc(100% + 8px); left: 0; right: 0; background: var(--card2); border: 1px solid var(--border); border-radius: 16px; box-shadow: 0 20px 40px rgba(0,0,0,0.4); padding: 8px; display: none; z-index: 110; backdrop-filter: blur(20px); max-height: 240px; overflow-y: auto; transform-origin: top; animation: selectPop 0.25s cubic-bezier(0.175, 0.885, 0.32, 1.1); }
        .c-select-options.show { display: block; }
        .c-select-option { padding: 12px 16px; border-radius: 10px; font-size: 0.88rem; color: var(--text); cursor: pointer; transition: 0.2s; display: flex; align-items: center; justify-content: space-between; margin-bottom: 2px; }
        .c-select-option:last-child { margin-bottom: 0; }
        .c-select-option:hover { background: var(--accent-glow); color: var(--accent); }
        .c-select-option.selected { background: var(--accent); color: #000; font-weight: 600; }
        
        @keyframes selectPop { from { opacity: 0; transform: translateY(-10px) scale(0.95); } to { opacity: 1; transform: translateY(0) scale(1); } }

        .status-badge { padding: 4px 10px; border-radius: 20px; font-size: 0.65rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; display: inline-flex; align-items: center; gap: 5px; }
        .status-active { background: rgba(52, 211, 153, 0.1); color: var(--green); border: 1px solid rgba(52, 211, 153, 0.2); }
        .status-expired { background: rgba(248, 113, 113, 0.1); color: var(--red); border: 1px solid rgba(248, 113, 113, 0.2); }
    </style>
</head>
<body>

<aside class="sidebar">
    <div class="sidebar-logo">Tix<span>Now</span></div>
    <div class="sidebar-label">Menu</div>
    <a href="admin_dashboard" class="sidebar-item">
        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
        Dashboard
    </a>
    <a href="admin_profile" class="sidebar-item">
        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
        Profil Saya
    </a>
    <a href="admin_scan" class="sidebar-item">
        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/></svg>
        Scan Tiket
    </a>
    <a href="admin_vouchers" class="sidebar-item active">
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

<div class="main">
    <div class="page-header">
        <div>
            <h1 class="page-title">Manajemen <span>Voucher</span></h1>
            <p style="color:var(--muted); font-size:0.85rem">Kelola kode promo dan diskon event secara dinamis</p>
        </div>
        <button class="btn-add" onclick="document.getElementById('addModal').classList.add('open')">
            <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
            Tambah Voucher
        </button>
    </div>

    <?php if(isset($success)): ?>
    <div style="background:var(--green); color:#000; padding:12px 20px; border-radius:12px; margin-bottom:24px; font-size:0.85rem; font-weight:700;">
        <?php echo $success; ?>
    </div>
    <?php endif; ?>

    <div class="v-card">
        <table>
            <thead>
                <tr>
                    <th>Kode Voucher</th>
                    <th>Potongan</th>
                    <th>Event Terkait</th>
                    <th>Pemakaian</th>
                    <th>Status</th>
                    <th style="text-align:right;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($vouchers as $v): ?>
                <tr>
                    <td><span class="v-code"><?php echo $v['code']; ?></span></td>
                    <td><span class="v-amount">Rp <?php echo number_format($v['discount_amount'], 0, ',', '.'); ?></span></td>
                    <td style="color:var(--header-text); font-weight:500;">
                        <?php if ($v['id_event'] == 0): ?>
                            <span style="color: var(--accent); font-weight: 700;">Semua Event</span>
                        <?php else: ?>
                            <?php echo htmlspecialchars($v['nama_event'] ?? 'Event Terhapus'); ?>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div style="display:flex; align-items:center; gap:12px;">
                            <span style="font-family:'Space Mono', monospace; font-weight:700; font-size:0.8rem;"><?php echo $v['current_usage']; ?> / <?php echo $v['max_usage']; ?></span>
                            <div class="usage-bar">
                                <div class="usage-fill" style="width: <?php echo ($v['current_usage'] / $v['max_usage']) * 100; ?>%"></div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <span class="status-badge <?php echo $v['status'] === 'active' ? 'status-active' : 'status-expired'; ?>">
                            <span style="width:6px; height:6px; border-radius:50%; background:currentColor;"></span>
                            <?php echo strtoupper($v['status']); ?>
                        </span>
                    </td>
                    <td style="text-align:right;">
                        <div style="display:flex; justify-content:flex-end; gap:10px;">
                            <button class="btn-delete" onclick="if(confirm('Hapus voucher ini?')) window.location.href='admin_vouchers?delete=<?php echo $v['id_voucher']; ?>'">
                                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if(empty($vouchers)): ?>
                <tr><td colspan="6" style="text-align:center; color:var(--muted); padding:80px; font-size:0.9rem;">Belum ada voucher aktif yang tersedia.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal" id="addModal" onclick="if(event.target === this) this.classList.remove('open')">
    <div class="modal-content">
        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:30px;">
            <h2 style="font-family:'Syne'; font-size:1.4rem; font-weight:800; color:var(--header-text);">Buat <span>Voucher</span></h2>
            <button onclick="document.getElementById('addModal').classList.remove('open')" style="background:none; border:none; color:var(--muted); cursor:pointer;"><svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg></button>
        </div>

        <form method="POST">
            <input type="hidden" name="action" value="add">
            
            <div class="form-group">
                <label class="form-label">Kode Voucher</label>
                <input type="text" name="code" class="form-input" placeholder="Misal: TIXNOW2024" required style="text-transform:uppercase;">
            </div>

            <div class="form-group">
                <label class="form-label">Potongan Harga (Rp)</label>
                <div class="num-stepper">
                    <button type="button" class="step-btn" onclick="stepInput('v_discount', -10000)"><svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M20 12H4"/></svg></button>
                    <input type="number" name="discount_amount" id="v_discount" class="form-input" value="10000" step="5000" min="0" required>
                    <button type="button" class="step-btn" onclick="stepInput('v_discount', 10000)"><svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg></button>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Berlaku untuk Event</label>
                <div class="c-select-wrapper" id="eventSelect">
                    <input type="hidden" name="id_event" id="id_event_input" required>
                    <div class="c-select-trigger" id="eventTrigger">
                        <span id="eventLabel">Pilih Event...</span>
                        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                    </div>
                    <div class="c-select-options" id="eventOptions">
                        <div class="c-select-option" data-value="0">
                            <strong>Semua Event</strong>
                        </div>
                        <?php foreach($events as $e): ?>
                        <div class="c-select-option" data-value="<?php echo $e['id_event']; ?>">
                            <?php echo htmlspecialchars($e['nama_event']); ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Batas Pemakaian (Kuota)</label>
                <div class="num-stepper">
                    <button type="button" class="step-btn" onclick="stepInput('v_usage', -10)"><svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M20 12H4"/></svg></button>
                    <input type="number" name="max_usage" id="v_usage" class="form-input" value="100" min="1" required>
                    <button type="button" class="step-btn" onclick="stepInput('v_usage', 10)"><svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg></button>
                </div>
            </div>

            <div style="display:grid; grid-template-columns: 1fr 2fr; gap:12px; margin-top:10px;">
                <button type="button" class="btn-cancel" onclick="document.getElementById('addModal').classList.remove('open')">Batal</button>
                <button type="submit" class="btn-add" style="justify-content:center; width:100%;">Terbitkan Voucher</button>
            </div>
        </form>
    </div>
</div>

<script>
    function updateAdminIcons(theme) {
        const sun = document.getElementById('adminSunIcon');
        const moon = document.getElementById('adminMoonIcon');
        if (sun && moon) {
            sun.style.display = theme === 'light' ? 'block' : 'none';
            moon.style.display = theme === 'light' ? 'none' : 'block';
        }
    }
    function toggleAdminTheme() {
        const cur = document.documentElement.getAttribute('data-theme') || 'dark';
        const next = cur === 'light' ? 'dark' : 'light';
        document.documentElement.setAttribute('data-theme', next);
        localStorage.setItem('theme', next);
        updateAdminIcons(next);
    }
    function stepInput(id, delta) {
        const el = document.getElementById(id);
        if (!el) return;
        const cur = parseInt(el.value) || 0;
        el.value = Math.max(0, cur + delta);
    }

    // Custom Select Logic
    const trigger = document.getElementById('eventTrigger');
    const optionsList = document.getElementById('eventOptions');
    const input = document.getElementById('id_event_input');
    const label = document.getElementById('eventLabel');
    const options = document.querySelectorAll('.c-select-option');

    trigger.addEventListener('click', (e) => {
        e.stopPropagation();
        const isOpen = optionsList.classList.contains('show');
        
        // Close other modals if any, then toggle
        trigger.classList.toggle('active', !isOpen);
        optionsList.classList.toggle('show', !isOpen);
    });

    options.forEach(opt => {
        opt.addEventListener('click', function() {
            const val = this.dataset.value;
            const text = this.innerText;
            
            input.value = val;
            label.innerText = text;
            
            options.forEach(o => o.classList.remove('selected'));
            this.classList.add('selected');
            
            trigger.classList.remove('active');
            optionsList.classList.remove('show');
        });
    });

    document.addEventListener('click', (e) => {
        if (!document.getElementById('eventSelect').contains(e.target)) {
            trigger.classList.remove('active');
            optionsList.classList.remove('show');
        }
    });

    document.addEventListener('DOMContentLoaded', () => {
        updateAdminIcons(document.documentElement.getAttribute('data-theme'));
    });
</script>
</body>
</html>
