<?php
session_start();
require_once 'koneksi.php';

if (!isset($_SESSION['id_user']) || $_SESSION['role'] !== 'petugas') {
    header("Location: index");
    exit;
}

$id_user = $_SESSION['id_user'];
$stmt_staff = $conn->prepare("SELECT * FROM users WHERE id_user = ?");
$stmt_staff->execute([$id_user]);
$petugas = $stmt_staff->fetch();
$initials = strtoupper(substr($petugas['nama'] ?? 'P', 0, 1));

// Get stats for petugas
$id_petugas = $_SESSION['id_user'];

// 1. Total Scanned by this petugas today
$stmtScanned = $conn->prepare("SELECT COUNT(*) FROM attendee WHERE used_by_admin_id = ? AND DATE(waktu_checkin) = CURDATE()");
$stmtScanned->execute([$id_petugas]);
$totalScannedToday = $stmtScanned->fetchColumn();

// 2. Active Events (events with today's date or happening now)
$stmtEvents = $conn->query("SELECT COUNT(*) FROM event WHERE status = 'published' AND tanggal >= CURDATE()");
$activeEvents = $stmtEvents->fetchColumn();

// 3. Recent Check-ins handled by this petugas
$stmtRecent = $conn->prepare("
    SELECT t.nama_pemegang, t.waktu_checkin, e.nama_event, tk.nama_tiket
    FROM attendee t
    JOIN order_detail od ON t.id_detail = od.id_detail
    JOIN orders o ON od.id_order = o.id_order
    JOIN event e ON o.id_event = e.id_event
    JOIN tiket tk ON od.id_tiket = tk.id_tiket
    WHERE t.used_by_admin_id = ?
    ORDER BY t.waktu_checkin DESC
    LIMIT 5
");
$stmtRecent->execute([$id_petugas]);
$recentCheckins = $stmtRecent->fetchAll();

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Petugas — TixNow</title>
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
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
            --border: rgba(255,255,255,0.06); --border-alpha: rgba(255,255,255,0.1);
            --surface-alpha: rgba(255,255,255,0.04); --accent: #a78bfa;
            --accent-glow: rgba(167,139,250,0.15); --accent-dim: rgba(167,139,250,0.1);
            --red: #f87171; --red-glow: rgba(248,113,113,0.12);
            --green: #34d399; --text: #e2e8f0; --muted: #4b5a72;
            --header-text: #ffffff; --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        [data-theme="light"] {
            --bg: #f3f4f6; --surface: #ffffff; --card: #ffffff; --card2: #f8fafc;
            --border: rgba(0,0,0,0.06); --border-alpha: rgba(0,0,0,0.08);
            --surface-alpha: rgba(0,0,0,0.03); --accent: #6d28d9;
            --accent-glow: rgba(109, 40, 217, 0.12); --accent-dim: rgba(109, 40, 217, 0.08);
            --red: #ef4444; --red-glow: rgba(239, 68, 68, 0.08);
            --green: #10b981; --text: #374151; --muted: #6b7280; --header-text: #111827;
        }

        body { font-family: 'Inter', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; display: flex; transition: var(--transition); }

        /* SIDEBAR (Unified Premium) */
        .sidebar {
            width: 230px; min-height: 100vh; background: var(--surface); border-right: 1px solid var(--border);
            display: flex; flex-direction: column; padding: 28px 0; position: fixed; top: 0; left: 0; bottom: 0; z-index: 50;
            transition: var(--transition);
        }
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
        .main { margin-left: 230px; flex: 1; padding: 40px; transition: var(--transition); }
        .page-header { margin-bottom: 40px; }
        .page-title { font-family: 'Syne', sans-serif; font-size: 2rem; font-weight: 800; margin-bottom: 8px; color: var(--header-text); }
        .page-sub { color: var(--muted); font-size: 0.95rem; }

        /* STATS */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; margin-bottom: 40px; }
        .stat-card { background: var(--card); border: 1px solid var(--border); border-radius: 20px; padding: 24px; }
        .stat-label { font-size: 0.75rem; color: var(--muted); text-transform: uppercase; letter-spacing: 0.05em; font-weight: 600; margin-bottom: 10px; }
        .stat-value { font-family: 'Inter', sans-serif; font-size: 2.2rem; font-weight: 800; color: var(--header-text); }
        .stat-sub { font-size: 0.8rem; color: var(--muted); margin-top: 5px; }

        /* RECENT TABLE */
        .panel { background: var(--card); border: 1px solid var(--border); border-radius: 20px; overflow: hidden; }
        .panel-header { padding: 20px 24px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; }
        .panel-title { font-family: 'Syne', sans-serif; font-weight: 700; color: var(--header-text); }
        .panel-body { padding: 0; }
        
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 14px 24px; font-size: 0.75rem; color: var(--muted); font-weight: 600; text-transform: uppercase; border-bottom: 1px solid var(--border); }
        td { padding: 16px 24px; font-size: 0.85rem; border-bottom: 1px solid var(--border); }
        .badge-kategori { padding: 4px 10px; border-radius: 6px; background: var(--accent-glow); color: var(--accent); font-weight: 600; font-size: 0.75rem; }

        .btn-scan-hero { display: inline-flex; align-items: center; gap: 10px; background: var(--accent); color: #000; padding: 14px 28px; border-radius: 12px; font-weight: 700; text-decoration: none; margin-top: 20px; transition: 0.2s; box-shadow: 0 10px 20px var(--accent-glow); }
        .btn-scan-hero:hover { transform: translateY(-3px); opacity: 0.9; }

        .theme-toggle-btn { width: 36px; height: 36px; border-radius: 10px; background: var(--surface); border: 1px solid var(--border); display: flex; align-items: center; justify-content: center; cursor: pointer; color: var(--header-text); margin-bottom: 15px; }
    </style>
</head>
<body>

<aside class="sidebar">
    <div class="sidebar-logo">Tix<span>Now</span></div>
    <div class="sidebar-label">Menu Petugas</div>
    <a href="petugas_dashboard"
        class="sidebar-item<?php echo basename($_SERVER['PHP_SELF']) == 'petugas_dashboard.php' ? ' active' : ''; ?>">
        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
        </svg>
        Dashboard
    </a>
    <a href="petugas_scan"
        class="sidebar-item<?php echo basename($_SERVER['PHP_SELF']) == 'petugas_scan.php' ? ' active' : ''; ?>">
        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" />
        </svg>
        Scan Tiket
    </a>
    <a href="petugas_attendance"
        class="sidebar-item<?php echo basename($_SERVER['PHP_SELF']) == 'petugas_attendance.php' ? ' active' : ''; ?>">
        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
        </svg>
        Daftar Hadir
    </a>
    <a href="petugas_profile" class="sidebar-item">
        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
        Profil Saya
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
            <?php if (!empty($petugas['foto_profil'])): ?>
                <img src="<?php echo htmlspecialchars($petugas['foto_profil']); ?>" style="width: 38px; height: 38px; border-radius: 10px; object-fit: cover; border: 1px solid var(--border);">
            <?php else: ?>
                <div style="width: 38px; height: 38px; border-radius: 10px; background: var(--accent-dim); color: var(--accent); display: flex; align-items: center; justify-content: center; font-weight: 800; font-family: 'Syne', sans-serif; font-size: 0.9rem; border: 1px solid var(--accent-glow);"><?php echo $initials; ?></div>
            <?php endif; ?>
            <div style="overflow: hidden;">
                <strong style="display: block; color: var(--text); font-weight: 600; font-size: 0.82rem; white-space: nowrap; text-overflow: ellipsis; overflow: hidden;"><?php echo htmlspecialchars($petugas['nama']); ?></strong>
                <span style="display: block; font-size: 0.68rem; color: var(--muted); text-transform: uppercase; letter-spacing: 0.05em; margin-top: 1px;">Staff Operasional</span>
            </div>
        </div>
        <a href="logout" class="btn-logout">Keluar</a>
    </div>
</aside>

<main class="main">
    <div class="header">
        <h1>Dashboard Petugas</h1>
        <p>Selamat datang, <?php echo explode(' ', $_SESSION['nama'])[0]; ?>. Kelola check-in event hari ini.</p>
        
        <a href="petugas_scan" class="btn-scan-hero">
            <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01"/></svg>
            Mulai Scan Tiket
        </a>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-label">Check-in Oleh Anda (Hari Ini)</div>
            <div class="stat-value"><?php echo $totalScannedToday; ?></div>
            <div class="stat-sub">Tiket terverifikasi</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Event Aktif</div>
            <div class="stat-value"><?php echo $activeEvents; ?></div>
            <div class="stat-sub">Mendatang & Sedang Berlangsung</div>
        </div>
    </div>

    <div class="panel">
        <div class="panel-header">
            <div class="panel-title">Aktivitas Scan Terakhir</div>
            <a href="petugas_attendance" style="color:var(--accent); font-size:0.8rem; text-decoration:none; font-weight:600;">Lihat Semua →</a>
        </div>
        <div class="panel-body">
            <table>
                <thead>
                    <tr>
                        <th>Waktu</th>
                        <th>Penonton</th>
                        <th>Event</th>
                        <th>Kategori</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($recentCheckins) > 0): ?>
                        <?php foreach ($recentCheckins as $row): ?>
                        <tr>
                            <td style="color:var(--muted)"><?php echo date('H:i', strtotime($row['waktu_checkin'])); ?></td>
                            <td style="font-weight:600;"><?php echo htmlspecialchars($row['nama_pemegang']); ?></td>
                            <td><?php echo htmlspecialchars($row['nama_event']); ?></td>
                            <td><span class="badge-kategori"><?php echo htmlspecialchars($row['nama_tiket']); ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" style="text-align:center; padding: 40px; color:var(--muted)">Belum ada aktivitas scan hari ini.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<script>
    function toggleTheme() {
        const cur = document.documentElement.getAttribute('data-theme') || 'dark';
        const next = cur === 'light' ? 'dark' : 'light';
        document.documentElement.setAttribute('data-theme', next);
        localStorage.setItem('theme', next);
        updateIcons(next);
    }
    function updateIcons(theme) {
        document.getElementById('sunIcon').style.display = theme === 'light' ? 'block' : 'none';
        document.getElementById('moonIcon').style.display = theme === 'light' ? 'none' : 'block';
    }
    document.addEventListener('DOMContentLoaded', () => {
        updateIcons(document.documentElement.getAttribute('data-theme'));
    });
</script>

</body>
</html>
