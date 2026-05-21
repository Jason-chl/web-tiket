<?php
session_start();
require_once 'koneksi.php';

if (!isset($_SESSION['id_user']) || $_SESSION['role'] !== 'admin') {
    header("Location: index");
    exit;
}

// Filter and Search
$search = isset($_GET['search']) ? $_GET['search'] : '';
$statusFilter = isset($_GET['status']) ? $_GET['status'] : '';

$id_user = $_SESSION['id_user'];
$stmt_admin = $conn->prepare("SELECT * FROM users WHERE id_user = ?");
$stmt_admin->execute([$id_user]);
$admin = $stmt_admin->fetch();
$initials = strtoupper(substr($admin['nama'] ?? 'A', 0, 1));

$query = "SELECT k.*, 
          (SELECT SUM(kuota) FROM tiket WHERE id_event = k.id_event) as total_stok,
          (SELECT SUM(qty) FROM order_detail oi JOIN orders o ON oi.id_order = o.id_order WHERE o.id_event = k.id_event AND o.status IN ('paid', 'dp_paid')) as terjual,
          (SELECT SUM(total) FROM orders WHERE id_event = k.id_event AND status IN ('paid', 'dp_paid')) as revenue
          FROM event k WHERE 1=1";

$params = [];
if($search) {
    $query .= " AND (nama_event LIKE ? OR artis LIKE ? OR venue LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if($statusFilter) {
    $query .= " AND status = ?";
    $params[] = $statusFilter;
}

$query .= " ORDER BY created_at DESC";
$stmt = $conn->prepare($query);
$stmt->execute($params);
$konser_list = $stmt->fetchAll();

// Global Stats for this page
$totalEvents = count($konser_list);
$qTotalRevenue = $conn->query("SELECT SUM(total) FROM orders WHERE status IN ('paid', 'dp_paid')")->fetchColumn() ?: 0;

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
    <title>TixNow Admin — Manajemen Event</title>
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
        .page-title span { color: var(--accent); }
        .page-sub { font-size: 0.82rem; color: var(--muted); margin-top: 2px; }
        .btn-add { display: flex; align-items: center; gap: 8px; padding: 10px 24px; background: var(--accent); color: white; border: none; border-radius: 99px; font-size: 0.85rem; font-weight: 700; cursor: pointer; transition: var(--transition); font-family: 'Inter', sans-serif; box-shadow: 0 8px 20px var(--accent-glow); text-decoration: none; }
        .btn-add:hover { opacity: 0.9; transform: translateY(-2px); box-shadow: 0 12px 25px var(--accent-glow); color: white; }

        .stats-strip { display: flex; gap: 20px; margin-bottom: 30px; }
        .mini-stat { background: var(--card); border: 1px solid var(--border); border-radius: 12px; padding: 15px 20px; flex: 1; display: flex; align-items: center; gap: 15px; transition: var(--transition); }
        .mini-stat-icon { width: 40px; height: 40px; border-radius: 10px; background: var(--accent-glow); color: var(--accent); display: flex; align-items: center; justify-content: center; }
        .mini-stat-info h4 { font-size: 0.68rem; color: var(--muted); text-transform: uppercase; letter-spacing: 0.1em; transform: translateY(2px); }
        .mini-stat-info p { font-family: 'Syne', sans-serif; font-size: 1.2rem; font-weight: 700; color: var(--header-text); }

        /* TABLE */
        .table-container { background: var(--card); border: 1px solid var(--border); border-radius: 20px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.1); }
        .filters { padding: 20px; border-bottom: 1px solid var(--border); display: flex; gap: 15px; align-items: center; background: rgba(255,255,255,0.01); }
        .search-box { flex: 1; position: relative; }
        .search-box input { width: 100%; padding: 12px 12px 12px 42px; border-radius: 12px; background: var(--bg); border: 1px solid var(--border); color: var(--text); font-size: 0.85rem; outline: none; transition: 0.3s; }
        .search-box input:focus { border-color: var(--accent); box-shadow: 0 0 0 4px var(--accent-glow); }
        .search-box svg { position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: var(--muted); }

        /* CUSTOM SELECT SYNC */
        .custom-select-wrapper { position: relative; width: 180px; }
        .custom-select-trigger { padding: 12px 16px; border-radius: 12px; background: var(--bg); border: 1px solid var(--border); color: var(--text); font-size: 0.85rem; cursor: pointer; display: flex; align-items: center; justify-content: space-between; transition: 0.3s; }
        .custom-select-trigger svg { transition: transform 0.3s; opacity: 0.7; }
        .custom-select-trigger.active { border-color: var(--accent); box-shadow: 0 0 0 4px var(--accent-glow); }
        .custom-select-trigger.active svg { transform: rotate(180deg); color: var(--accent); opacity: 1; }
        .custom-options { position: absolute; top: calc(100% + 8px); left: 0; right: 0; background: var(--surface); border: 1px solid var(--border); border-radius: 14px; box-shadow: 0 20px 40px rgba(0,0,0,0.3); padding: 8px; display: none; z-index: 100; backdrop-filter: blur(12px); }
        .custom-options.show { display: block; animation: selectFadeIn 0.2s ease-out; }
        .custom-option { padding: 10px 12px; border-radius: 8px; font-size: 0.85rem; color: var(--text); cursor: pointer; transition: 0.2s; }
        .custom-option:hover { background: var(--accent-glow); color: var(--accent); }
        .custom-option.selected { background: var(--accent); color: white; }

        table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
        th { text-align: left; padding: 16px 24px; background: rgba(255,255,255,0.02); color: var(--muted); font-weight: 600; font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.08em; border-bottom: 1px solid var(--border); }
        td { padding: 16px 24px; border-bottom: 1px solid var(--border); color: var(--text); }
        tr:hover { background: rgba(167,139,250,0.02); }

        .event-info { display: flex; align-items: center; gap: 14px; }
        .event-poster { width: 44px; height: 44px; border-radius: 10px; object-fit: cover; border: 1px solid var(--border); }
        .event-name { font-weight: 700; color: var(--header-text); }
        .event-artis { font-size: 0.75rem; color: var(--muted); }

        .status-badge { padding: 4px 10px; border-radius: 6px; font-size: 0.65rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; }
        .status-draft { background: rgba(100,116,139,0.1); color: #94a3b8; }
        .status-published { background: rgba(52,211,153,0.1); color: var(--green); }
        .status-ongoing { background: rgba(167,139,250,0.1); color: var(--accent); }
        .status-completed { background: rgba(130,207,255,0.1); color: #82cfff; }

        .inventory-wrap { width: 120px; }
        .inventory-label { display: flex; justify-content: space-between; font-size: 0.65rem; color: var(--muted); margin-bottom: 4px; }
        .inventory-bar { height: 5px; background: rgba(255,255,255,0.05); border-radius: 10px; overflow: hidden; }
        .inventory-fill { height: 100%; background: var(--accent); border-radius: 10px; }

        .revenue-val { font-family: 'Space Mono', monospace; font-weight: 700; color: var(--green); }
        
        .action-btns { display: flex; gap: 8px; }
        .btn-icon { width: 32px; height: 32px; border-radius: 8px; border: 1px solid var(--border); background: var(--bg); color: var(--muted); display: flex; align-items: center; justify-content: center; cursor: pointer; transition: 0.2s; text-decoration: none; }
        .btn-icon:hover { color: var(--accent); border-color: var(--accent); background: var(--accent-glow); }
        .btn-icon.del:hover { color: var(--red); border-color: var(--red); background: var(--red-glow); }

        @keyframes selectFadeIn { from { opacity: 0; transform: translateY(-8px); } to { opacity: 1; transform: translateY(0); } }
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

    <a href="admin_vouchers" class="sidebar-item<?php echo basename($_SERVER['PHP_SELF']) == 'admin_vouchers.php' ? ' active' : ''; ?>">
        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/></svg>
        Voucher
    </a>
    <a href="admin_history" class="sidebar-item">
        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        Riwayat User
    </a>
    <a href="admin_event" class="sidebar-item active">
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

<main class="main">
    <div class="page-header">
        <div>
            <h1 class="page-title">Manajemen <span>Event</span></h1>
            <p class="page-sub">Database master seluruh konser dan event TixNow</p>
        </div>
        <a href="admin_dashboard" class="btn-add">
            <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
            Tambah Event
        </a>
    </div>

    <div class="stats-strip">
        <div class="mini-stat">
            <div class="mini-stat-icon">
                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
            </div>
            <div class="mini-stat-info">
                <h4>Total Event</h4>
                <p><?php echo $totalEvents; ?></p>
            </div>
        </div>
        <div class="mini-stat">
            <div class="mini-stat-icon" style="background:rgba(52,211,153,0.1); color:var(--green);">
                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <div class="mini-stat-info">
                <h4>Total Revenue</h4>
                <p>Rp <?php echo number_format($qTotalRevenue, 0, ',', '.'); ?></p>
            </div>
        </div>
    </div>

    <div class="table-container">
        <form action="" method="GET" class="filters" id="filterForm">
            <div class="search-box">
                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input type="text" name="search" placeholder="Cari event, artis, atau lokasi..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            
            <div class="custom-select-wrapper" id="statusWrapper">
                <input type="hidden" name="status" id="statusInput" value="<?php echo htmlspecialchars($statusFilter); ?>">
                <div class="custom-select-trigger" id="selectTrigger">
                    <span id="triggerLabel">
                        <?php 
                            switch($statusFilter){
                                case 'draft': echo 'Draft'; break;
                                case 'published': echo 'Published'; break;
                                case 'ongoing': echo 'Ongoing'; break;
                                case 'completed': echo 'Completed'; break;
                                default: echo 'Semua Status';
                            }
                        ?>
                    </span>
                    <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </div>
                <div class="custom-options" id="optionsList">
                    <div class="custom-option" data-value="">Semua Status</div>
                    <div class="custom-option" data-value="draft">Draft</div>
                    <div class="custom-option" data-value="published">Published</div>
                    <div class="custom-option" data-value="ongoing">Ongoing</div>
                    <div class="custom-option" data-value="completed">Completed</div>
                </div>
            </div>
        </form>

        <table>
            <thead>
                <tr>
                    <th>Event & Artis</th>
                    <th>Jadwal & Lokasi</th>
                    <th>Status</th>
                    <th>Inventory</th>
                    <th>Revenue</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($konser_list as $row): 
                    $pSold = $row['total_stok'] > 0 ? ($row['terjual'] / $row['total_stok']) * 100 : 0;
                    $poster = !empty($row['poster_url']) ? $row['poster_url'] : 'https://via.placeholder.com/100x100?text=No+Poster';
                ?>
                <tr>
                    <td>
                        <div class="event-info">
                            <img src="<?php echo $poster; ?>" class="event-poster">
                            <div>
                                <div class="event-name"><?php echo htmlspecialchars($row['nama_event']); ?></div>
                                <div class="event-artis"><?php echo htmlspecialchars($row['artis']); ?></div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <div style="font-weight:600; font-size:0.8rem;"><?php echo date('d M Y', strtotime($row['tanggal'])); ?></div>
                        <div style="font-size:0.75rem; color:var(--muted);"><?php echo htmlspecialchars($row['venue']); ?></div>
                    </td>
                    <td><span class="status-badge status-<?php echo $row['status']; ?>"><?php echo $row['status']; ?></span></td>
                    <td>
                        <div class="inventory-wrap">
                            <div class="inventory-label">
                                <span><?php echo $row['terjual']; ?>/<?php echo $row['total_stok']; ?></span>
                                <span><?php echo round($pSold); ?>%</span>
                            </div>
                            <div class="inventory-bar">
                                <div class="inventory-fill" style="width: <?php echo $pSold; ?>%"></div>
                            </div>
                        </div>
                    </td>
                    <td><div class="revenue-val">Rp <?php echo number_format($row['revenue'] ?: 0, 0, ',', '.'); ?></div></td>
                    <td>
                        <div class="action-btns">
                            <a href="admin_manage_event?id=<?php echo $row['id_event']; ?>" class="btn-icon" title="Atur Konten">
                                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            </a>
                            <a href="admin_dashboard" class="btn-icon" title="Edit Info">
                                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                            </a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if(empty($konser_list)): ?>
                <tr><td colspan="6" style="text-align:center; padding:40px; color:var(--muted);">Tidak ada event ditemukan.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</main>

<script>
    // ── THEME TOGGLE (GLOBAL) ──
    function updateIcons(theme) {
        const moonIcon = document.getElementById('moonIcon');
        const sunIcon = document.getElementById('sunIcon');
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
    });

    // CUSTOM SELECT LOGIC
    const selectTrigger = document.getElementById('selectTrigger');
    const optionsList = document.getElementById('optionsList');
    const statusInput = document.getElementById('statusInput');
    const customOptions = document.querySelectorAll('.custom-option');
    const triggerLabel = document.getElementById('triggerLabel');
    const filterForm = document.getElementById('filterForm');

    selectTrigger.addEventListener('click', (e) => {
        e.stopPropagation();
        selectTrigger.classList.toggle('active');
        optionsList.classList.toggle('show');
    });

    customOptions.forEach(opt => {
        opt.addEventListener('click', function() {
            statusInput.value = this.dataset.value;
            triggerLabel.innerText = this.innerText;
            filterForm.submit();
        });
    });

    document.addEventListener('click', (e) => {
        if (!document.getElementById('statusWrapper').contains(e.target)) {
            optionsList.classList.remove('show');
            selectTrigger.classList.remove('active');
        }
    });
    // Auto-dismiss alerts after 3.5 seconds
    const alert = document.querySelector('.alert');
    if (alert) setTimeout(() => alert.style.opacity = '0', 3500);
</script>

</body>
</html>
