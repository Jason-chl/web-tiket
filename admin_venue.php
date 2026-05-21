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

// ── HANDLE ACTIONS ──────────────────────────────────────────────────
$msg = '';
$msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $nama    = trim($_POST['nama_venue']);
        $alamat  = trim($_POST['alamat']);
        $kapasitas = (int)$_POST['kapasitas'];

        if ($nama) {
            $stmt = $conn->prepare("INSERT INTO venue (nama_venue, alamat, kapasitas) VALUES (?, ?, ?)");
            $stmt->execute([$nama, $alamat, $kapasitas]);
            $msg = "Venue <strong>$nama</strong> berhasil ditambahkan.";
            $msgType = 'success';
        } else {
            $msg = 'Nama venue wajib diisi.';
            $msgType = 'error';
        }
    }

    if ($action === 'edit') {
        $id      = (int)$_POST['id_venue'];
        $nama    = trim($_POST['nama_venue']);
        $alamat  = trim($_POST['alamat']);
        $kapasitas = (int)$_POST['kapasitas'];

        if ($id && $nama) {
            $stmt = $conn->prepare("UPDATE venue SET nama_venue=?, alamat=?, kapasitas=? WHERE id_venue=?");
            $stmt->execute([$nama, $alamat, $kapasitas, $id]);
            $msg = "Venue <strong>$nama</strong> berhasil diperbarui.";
            $msgType = 'success';
        }
    }

    if ($action === 'delete') {
        $id = (int)$_POST['id_venue'];
        // Check if venue is used by any event
        $check = $conn->prepare("SELECT COUNT(*) FROM event WHERE id_venue = ?");
        $check->execute([$id]);
        $used = $check->fetchColumn();

        if ($used > 0) {
            $msg = "Venue tidak bisa dihapus karena masih digunakan oleh <strong>$used event</strong>.";
            $msgType = 'error';
        } else {
            $conn->prepare("DELETE FROM venue WHERE id_venue=?")->execute([$id]);
            $msg = "Venue berhasil dihapus.";
            $msgType = 'success';
        }
    }
}

// ── FETCH DATA ───────────────────────────────────────────────────────
$search = trim($_GET['search'] ?? '');
$sql    = "SELECT v.*, (SELECT COUNT(*) FROM event e WHERE e.id_venue = v.id_venue) as total_event FROM venue v";
$params = [];
if ($search) {
    $sql .= " WHERE v.nama_venue LIKE ? OR v.alamat LIKE ?";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
$sql .= " ORDER BY v.nama_venue ASC";
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$venues = $stmt->fetchAll();

$totalVenues   = count($venues);
$totalKapasitas = array_sum(array_column($venues, 'kapasitas'));
$totalEvents   = array_sum(array_column($venues, 'total_event'));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TixNow Admin — Manajemen Venue</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;500;600;700;800&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
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
            --green-glow: rgba(52,211,153,0.12);
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
            --green-glow: rgba(16, 185, 129, 0.08);
            --text: #374151; --muted: #6b7280; --header-text: #111827;
        }

        body { font-family: 'Inter', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; display: flex; transition: var(--transition); }

        /* ── SIDEBAR ── */
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

        /* ── MAIN ── */
        .main { flex: 1; margin-left: 230px; padding: 40px; transition: var(--transition); }
        .page-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 30px; }
        .page-title { font-family: 'Syne', sans-serif; font-size: 1.6rem; font-weight: 700; color: var(--header-text); letter-spacing: -0.5px; }
        .page-title span { color: var(--accent); }
        .page-sub { font-size: 0.82rem; color: var(--muted); margin-top: 2px; }
        .btn-add { display: flex; align-items: center; gap: 8px; padding: 10px 24px; background: var(--accent); color: white; border: none; border-radius: 99px; font-size: 0.85rem; font-weight: 700; cursor: pointer; transition: var(--transition); font-family: 'Inter', sans-serif; box-shadow: 0 8px 20px var(--accent-glow); }
        .btn-add:hover { opacity: 0.9; transform: translateY(-2px); box-shadow: 0 12px 25px var(--accent-glow); }

        /* ── STATS ── */
        .stats-strip { display: flex; gap: 20px; margin-bottom: 30px; }
        .mini-stat { background: var(--card); border: 1px solid var(--border); border-radius: 14px; padding: 18px 22px; flex: 1; display: flex; align-items: center; gap: 16px; transition: var(--transition); }
        .mini-stat-icon { width: 44px; height: 44px; border-radius: 12px; background: var(--accent-glow); color: var(--accent); display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .mini-stat-icon.green { background: var(--green-glow); color: var(--green); }
        .mini-stat-icon.red { background: var(--red-glow); color: var(--red); }
        .mini-stat-info h4 { font-size: 0.68rem; color: var(--muted); text-transform: uppercase; letter-spacing: 0.1em; }
        .mini-stat-info p { font-family: 'Syne', sans-serif; font-size: 1.3rem; font-weight: 700; color: var(--header-text); }

        /* ── ALERT ── */
        .alert { padding: 14px 18px; border-radius: 12px; margin-bottom: 24px; font-size: 0.85rem; font-weight: 500; display: flex; align-items: center; gap: 10px; }
        .alert-success { background: var(--green-glow); border: 1px solid rgba(52,211,153,0.2); color: var(--green); }
        .alert-error { background: var(--red-glow); border: 1px solid rgba(248,113,113,0.2); color: var(--red); }
        [data-theme="light"] .alert-success { color: #059669; }
        [data-theme="light"] .alert-error { color: #e11d48; }

        /* ── TABLE ── */
        .table-container { background: var(--card); border: 1px solid var(--border); border-radius: 20px; overflow: hidden; }
        .table-top { padding: 20px 24px; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; gap: 16px; }
        .search-box { position: relative; flex: 1; max-width: 380px; }
        .search-box input { width: 100%; padding: 10px 14px 10px 40px; border-radius: 10px; background: var(--bg); border: 1px solid var(--border); color: var(--text); font-size: 0.85rem; outline: none; transition: 0.3s; font-family: 'Inter', sans-serif; }
        .search-box input:focus { border-color: var(--accent); box-shadow: 0 0 0 3px var(--accent-glow); }
        .search-box svg { position: absolute; left: 13px; top: 50%; transform: translateY(-50%); color: var(--muted); pointer-events: none; }
        .table-count { font-size: 0.8rem; color: var(--muted); }

        table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 0.85rem; table-layout: fixed; }
        th { text-align: left; padding: 14px 24px; background: rgba(255,255,255,0.02); color: var(--muted); font-weight: 600; font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.08em; border-bottom: 1px solid var(--border); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        td { padding: 16px 24px; border-bottom: 1px solid var(--border); color: var(--text); vertical-align: middle; overflow: hidden; }
        tr:last-child td:first-child { border-bottom-left-radius: 20px; }
        tr:last-child td:last-child { border-bottom-right-radius: 20px; }
        tr:last-child td { border-bottom: none; }
        tbody tr:hover { background: rgba(167,139,250,0.02); }

        .venue-name { font-weight: 700; color: var(--header-text); font-size: 0.9rem; }
        .venue-alamat { font-size: 0.78rem; color: var(--muted); margin-top: 2px; max-width: 280px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

        .badge-event { display: inline-flex; align-items: center; gap: 5px; padding: 4px 10px; border-radius: 20px; font-size: 0.72rem; font-weight: 600; }
        .badge-event.used { background: var(--accent-glow); color: var(--accent); }
        .badge-event.unused { background: rgba(255,255,255,0.05); color: var(--muted); }

        .kapasitas-val { font-family: 'Syne', sans-serif; font-weight: 700; }

        .action-btns { display: flex; gap: 8px; justify-content: flex-end; }
        .btn-icon { width: 34px; height: 34px; border-radius: 8px; border: 1px solid var(--border); background: transparent; color: var(--muted); display: flex; align-items: center; justify-content: center; cursor: pointer; transition: 0.2s; }
        .btn-icon:hover { color: var(--accent); border-color: var(--accent); background: var(--accent-glow); }
        .btn-icon.del:hover { color: var(--red); border-color: var(--red); background: var(--red-glow); }

        .empty-state { text-align: center; padding: 60px 20px; color: var(--muted); }
        .empty-state svg { opacity: 0.15; margin-bottom: 16px; }
        .empty-state h3 { font-family: 'Syne', sans-serif; font-size: 1rem; margin-bottom: 6px; color: var(--text); }

        /* ── MODAL ── */
        .modal-backdrop { position: fixed; inset: 0; background: rgba(0,0,0,0.6); backdrop-filter: blur(6px); z-index: 200; display: none; align-items: center; justify-content: center; padding: 20px; }
        .modal-backdrop.open { display: flex; }
        .modal-box { background: var(--surface); border: 1px solid var(--border); border-radius: 20px; padding: 32px; width: 100%; max-width: 500px; position: relative; animation: modalIn 0.25s ease; transition: var(--transition); }
        @keyframes modalIn { from { opacity:0; transform: translateY(16px) scale(0.97); } to { opacity:1; transform: none; } }
        .modal-close { position: absolute; top: 16px; right: 16px; width: 32px; height: 32px; border-radius: 8px; border: 1px solid var(--border); background: transparent; color: var(--muted); cursor: pointer; display: flex; align-items: center; justify-content: center; transition: 0.2s; }
        .modal-close:hover { border-color: var(--accent); color: var(--accent); }
        .modal-title { font-family: 'Syne', sans-serif; font-size: 1.2rem; font-weight: 700; color: var(--header-text); margin-bottom: 6px; }
        .modal-sub { font-size: 0.8rem; color: var(--muted); margin-bottom: 28px; }

        .form-group { margin-bottom: 18px; }
        .form-label { display: block; font-size: 0.8rem; font-weight: 600; color: var(--muted); margin-bottom: 8px; letter-spacing: 0.04em; text-transform: uppercase; }
        .form-input { width: 100%; padding: 11px 14px; border-radius: 10px; background: var(--bg); border: 1px solid var(--border); color: var(--text); font-size: 0.88rem; outline: none; transition: 0.3s; font-family: 'Inter', sans-serif; }
        .form-input:focus { border-color: var(--accent); box-shadow: 0 0 0 3px var(--accent-glow); }
        textarea.form-input { resize: vertical; min-height: 80px; }

        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }

        .modal-actions { display: flex; gap: 12px; margin-top: 28px; }
        .btn-submit { flex: 1; padding: 12px; background: var(--accent); color: white; border: none; border-radius: 10px; font-size: 0.88rem; font-weight: 700; cursor: pointer; transition: 0.2s; font-family: 'Inter', sans-serif; }
        .btn-submit:hover { opacity: 0.9; }
        .btn-cancel { padding: 12px 20px; background: transparent; color: var(--muted); border: 1px solid var(--border); border-radius: 10px; font-size: 0.88rem; font-weight: 600; cursor: pointer; transition: 0.2s; font-family: 'Inter', sans-serif; }
        .btn-cancel:hover { border-color: var(--accent); color: var(--accent); }

        /* Delete Confirm Modal */
        .modal-icon { width: 56px; height: 56px; border-radius: 16px; background: var(--red-glow); color: var(--red); display: flex; align-items: center; justify-content: center; margin-bottom: 20px; }
        .btn-delete-confirm { flex: 1; padding: 12px; background: var(--red); color: white; border: none; border-radius: 10px; font-size: 0.88rem; font-weight: 700; cursor: pointer; transition: 0.2s; font-family: 'Inter', sans-serif; }
        .btn-delete-confirm:hover { opacity: 0.85; }
    </style>
</head>
<body>

<!-- SIDEBAR -->
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
    <a href="admin_event" class="sidebar-item">
        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3"/></svg>
        Event
    </a>
    <a href="admin_venue" class="sidebar-item active">
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
            <h1 class="page-title">Manajemen <span>Venue</span></h1>
            <p class="page-sub">Kelola seluruh lokasi dan tempat penyelenggaraan event</p>
        </div>
        <button class="btn-add" onclick="openModal('modalAdd')">
            <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M12 4v16m8-8H4"/></svg>
            Tambah Venue
        </button>
    </div>

    <?php if ($msg): ?>
    <div class="alert alert-<?php echo $msgType; ?>">
        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <?php if ($msgType === 'success'): ?>
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
            <?php else: ?>
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            <?php endif; ?>
        </svg>
        <?php echo $msg; ?>
    </div>
    <?php endif; ?>

    <!-- STATS -->
    <div class="stats-strip">
        <div class="mini-stat">
            <div class="mini-stat-icon">
                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            </div>
            <div class="mini-stat-info">
                <h4>Total Venue</h4>
                <p><?php echo $totalVenues; ?></p>
            </div>
        </div>
        <div class="mini-stat">
            <div class="mini-stat-icon green">
                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            </div>
            <div class="mini-stat-info">
                <h4>Total Kapasitas</h4>
                <p><?php echo number_format($totalKapasitas, 0, ',', '.'); ?></p>
            </div>
        </div>
        <div class="mini-stat">
            <div class="mini-stat-icon red">
                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            </div>
            <div class="mini-stat-info">
                <h4>Dipakai Event</h4>
                <p><?php echo $totalEvents; ?></p>
            </div>
        </div>
    </div>

    <!-- TABLE -->
    <div class="table-container">
        <div class="table-top">
            <form method="GET" style="flex:1; display:flex; gap:12px; align-items:center;">
                <div class="search-box">
                    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    <input type="text" name="search" placeholder="Cari nama venue atau kota..." value="<?php echo htmlspecialchars($search); ?>" onchange="this.form.submit()">
                </div>
            </form>
            <span class="table-count"><?php echo $totalVenues; ?> venue ditemukan</span>
        </div>

        <?php if (!empty($venues)): ?>
        <table>
            <thead>
                <tr>
                    <th style="width: 60px;">#</th>
                    <th style="width: auto;">Nama Venue & Lokasi</th>
                    <th style="width: 160px;">Kapasitas</th>
                    <th style="width: 160px;">Digunakan Event</th>
                    <th style="width: 120px; text-align: right;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($venues as $i => $v): ?>
                <tr class="venue-row">
                    <td style="color:var(--muted); font-size:0.75rem;"><?php echo str_pad($i+1, 2, '0', STR_PAD_LEFT); ?></td>
                    <td>
                        <div class="venue-name"><?php echo htmlspecialchars($v['nama_venue']); ?></div>
                        <div class="venue-alamat"><?php echo htmlspecialchars($v['alamat'] ?: '—'); ?></div>
                    </td>
                    <td>
                        <span class="kapasitas-val"><?php echo number_format($v['kapasitas'], 0, ',', '.'); ?></span>
                        <span style="font-size:0.75rem; color:var(--muted);"> orang</span>
                    </td>
                    <td>
                        <?php if ($v['total_event'] > 0): ?>
                        <span class="badge-event used">
                            <svg width="10" height="10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            <?php echo $v['total_event']; ?> Event
                        </span>
                        <?php else: ?>
                        <span class="badge-event unused">Belum dipakai</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="action-btns">
                            <button class="btn-icon" title="Edit Venue"
                                onclick="openEditModal(<?php echo $v['id_venue']; ?>, '<?php echo addslashes($v['nama_venue']); ?>', '<?php echo addslashes($v['alamat']); ?>', <?php echo $v['kapasitas']; ?>)">
                                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                            </button>
                            <button class="btn-icon del" title="Hapus Venue"
                                onclick="openDeleteModal(<?php echo $v['id_venue']; ?>, '<?php echo addslashes($v['nama_venue']); ?>', <?php echo $v['total_event']; ?>)">
                                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="empty-state">
            <svg width="48" height="48" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            <h3>Belum Ada Venue</h3>
            <p>Klik "Tambah Venue" untuk mulai menambahkan tempat.</p>
        </div>
        <?php endif; ?>
    </div>
</main>

<!-- MODAL TAMBAH -->
<div class="modal-backdrop" id="modalAdd">
    <div class="modal-box">
        <button class="modal-close" onclick="closeModal('modalAdd')">
            <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
        <div class="modal-title">Tambah Venue Baru</div>
        <div class="modal-sub">Isi data lengkap venue yang akan ditambahkan ke sistem</div>
        <form method="POST">
            <input type="hidden" name="action" value="add">
            <div class="form-group">
                <label class="form-label">Nama Venue *</label>
                <input type="text" name="nama_venue" class="form-input" placeholder="Contoh: Gelora Bung Karno, ICE BSD..." required>
            </div>
            <div class="form-group">
                <label class="form-label">Alamat Lengkap</label>
                <textarea name="alamat" class="form-input" placeholder="Jl. Pintu Satu Senayan, Jakarta Pusat, DKI Jakarta"></textarea>
            </div>
            <div class="form-group">
                <label class="form-label">Kapasitas (orang)</label>
                <input type="number" name="kapasitas" class="form-input" placeholder="Contoh: 50000" min="0">
            </div>
            <div class="modal-actions">
                <button type="button" class="btn-cancel" onclick="closeModal('modalAdd')">Batal</button>
                <button type="submit" class="btn-submit">Simpan Venue</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL EDIT -->
<div class="modal-backdrop" id="modalEdit">
    <div class="modal-box">
        <button class="modal-close" onclick="closeModal('modalEdit')">
            <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
        <div class="modal-title">Edit Venue</div>
        <div class="modal-sub">Perbarui informasi venue yang sudah ada</div>
        <form method="POST">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id_venue" id="editId">
            <div class="form-group">
                <label class="form-label">Nama Venue *</label>
                <input type="text" name="nama_venue" id="editNama" class="form-input" required>
            </div>
            <div class="form-group">
                <label class="form-label">Alamat Lengkap</label>
                <textarea name="alamat" id="editAlamat" class="form-input"></textarea>
            </div>
            <div class="form-group">
                <label class="form-label">Kapasitas (orang)</label>
                <input type="number" name="kapasitas" id="editKapasitas" class="form-input" min="0">
            </div>
            <div class="modal-actions">
                <button type="button" class="btn-cancel" onclick="closeModal('modalEdit')">Batal</button>
                <button type="submit" class="btn-submit">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL DELETE -->
<div class="modal-backdrop" id="modalDelete">
    <div class="modal-box" style="max-width:420px;">
        <button class="modal-close" onclick="closeModal('modalDelete')">
            <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
        <div class="modal-icon">
            <svg width="26" height="26" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
        </div>
        <div class="modal-title">Hapus Venue?</div>
        <div class="modal-sub" id="deleteMsg">Venue ini akan dihapus permanen dari sistem.</div>
        <form method="POST">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id_venue" id="deleteId">
            <div class="modal-actions">
                <button type="button" class="btn-cancel" onclick="closeModal('modalDelete')">Batal</button>
                <button type="submit" class="btn-delete-confirm">Ya, Hapus</button>
            </div>
        </form>
    </div>
</div>

<script>
    // ── THEME TOGGLE ──
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
    document.addEventListener('DOMContentLoaded', () => {
        updateAdminIcons(document.documentElement.getAttribute('data-theme'));
    });

    // ── MODAL ──
    function openModal(id) {
        document.getElementById(id).classList.add('open');
    }
    function closeModal(id) {
        document.getElementById(id).classList.remove('open');
    }

    // Close on backdrop click
    document.querySelectorAll('.modal-backdrop').forEach(el => {
        el.addEventListener('click', e => {
            if (e.target === el) el.classList.remove('open');
        });
    });

    function openEditModal(id, nama, alamat, kapasitas) {
        document.getElementById('editId').value       = id;
        document.getElementById('editNama').value     = nama;
        document.getElementById('editAlamat').value   = alamat;
        document.getElementById('editKapasitas').value = kapasitas;
        openModal('modalEdit');
    }

    function openDeleteModal(id, nama, usedCount) {
        document.getElementById('deleteId').value = id;
        document.getElementById('deleteMsg').innerHTML =
            usedCount > 0
            ? `<strong style="color:var(--red);">${nama}</strong> masih digunakan oleh <strong>${usedCount} event</strong>. Anda tetap bisa mencobanya, namun sistem akan menolak penghapusan.`
            : `<strong>${nama}</strong> akan dihapus permanen. Tindakan ini tidak bisa dibatalkan.`;
        openModal('modalDelete');
    }

    // Auto-dismiss alert after 4s
    const alert = document.querySelector('.alert');
    if (alert) setTimeout(() => alert.style.opacity = '0', 3500);

    // ── SMART BUTTON DISABLE ──────────────────────────────────────────────

    // Tambah Venue: disabled until nama_venue is filled
    const addNameInput  = document.querySelector('#modalAdd input[name="nama_venue"]');
    const addSubmitBtn  = document.querySelector('#modalAdd button[type="submit"]');
    function syncAddBtn() {
        const ok = addNameInput.value.trim().length > 0;
        addSubmitBtn.disabled = !ok;
        addSubmitBtn.style.opacity = ok ? '1' : '0.4';
        addSubmitBtn.style.cursor  = ok ? 'pointer' : 'not-allowed';
    }
    addNameInput.addEventListener('input', syncAddBtn);
    syncAddBtn();

    // Edit Venue: disabled until user changes at least one field
    let editSnapshot = {};
    const editSubmitBtn = document.querySelector('#modalEdit button[type="submit"]');
    const editInputs    = document.querySelectorAll('#modalEdit input:not([type="hidden"]), #modalEdit textarea');

    function syncEditBtn() {
        const changed = Array.from(editInputs).some(el => String(el.value) !== String(editSnapshot[el.name] ?? ''));
        editSubmitBtn.disabled = !changed;
        editSubmitBtn.style.opacity = changed ? '1' : '0.4';
        editSubmitBtn.style.cursor  = changed ? 'pointer' : 'not-allowed';
    }
    editInputs.forEach(el => el.addEventListener('input', syncEditBtn));

    // Override openEditModal to capture snapshot
    const _origOpenEdit = openEditModal;
    window.openEditModal = function(id, nama, alamat, kapasitas) {
        _origOpenEdit(id, nama, alamat, kapasitas);
        editSnapshot = { nama_venue: nama, alamat: alamat, kapasitas: String(kapasitas) };
        syncEditBtn();
    };
    syncEditBtn();

</script>

</body>
</html>
