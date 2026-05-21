<?php
session_start();
require_once 'koneksi.php';

if (!isset($_SESSION['id_user']) || $_SESSION['role'] !== 'user') {
    header("Location: index");
    exit;
}

$id_user = $_SESSION['id_user'];

// Get user info with VIP status
$stmt = $conn->prepare("SELECT is_vip, nama, username, email FROM users WHERE id_user = ?");
$stmt->execute([$id_user]);
$userRow  = $stmt->fetch();

if (!$userRow) {
    session_destroy();
    header("Location: login?msg=Sesi berakhir, silakan login kembali.");
    exit;
}

$isVIP    = ($userRow['is_vip'] ?? 0) == 1;
$userName = $userRow['username'] ?? $userRow['nama'] ?? '';

// Filter Logic
$statusFilter = $_GET['status'] ?? 'all';
$whereClause = "WHERE o.id_user = ?";
$params = [$id_user];

if ($statusFilter === 'pending') {
    $whereClause .= " AND o.status = 'pending'";
} elseif ($statusFilter === 'paid') {
    $whereClause .= " AND o.status IN ('paid', 'dp_paid')";
} elseif ($statusFilter === 'cancelled') {
    $whereClause .= " AND o.status = 'cancelled'";
}

$stmt = $conn->prepare("
    SELECT o.*, c.nama_event, c.poster_url, c.tanggal, c.venue
    FROM orders o
    JOIN event c ON o.id_event = c.id_event
    $whereClause
    ORDER BY o.id_order DESC
");
$stmt->execute($params);
$orders = $stmt->fetchAll();

// Schema handled by system


// Get complete user info
$stmtUser = $conn->prepare("SELECT nama, username, foto_profil, is_vip FROM users WHERE id_user = ?");
$stmtUser->execute([$id_user]);
$appUser = $stmtUser->fetch();
$fotoProfil = $appUser['foto_profil'] ?? null;
$isVIP = ($appUser['is_vip'] ?? 0) == 1;

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Pesanan - TixNow</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;500;600;700;800&family=Inter:wght@300;400;500&display=swap" rel="stylesheet">
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
            --btn-text: #07090f;
            --red: #f87171;
            --green: #34d399;
            --pending: #f59e0b;
            --paid: #10b981;
            --cancelled: #ef4444;
            --expired: #64748b;
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
            --border: rgba(0,0,0,0.06);
            --border-alpha: rgba(0,0,0,0.08);
            --surface-alpha: rgba(0,0,0,0.03);
            --accent: #6d28d9;
            --accent-glow: rgba(109, 40, 217, 0.12);
            --btn-text: #ffffff;
            --red: #ef4444;
            --red-glow: rgba(239, 68, 68, 0.08);
            --green: #10b981;
            --pending: #d97706;
            --paid: #059669;
            --cancelled: #dc2626;
            --expired: #475569;
            --text: #374151;
            --muted: #6b7280;
            --header-text: #111827;
            --nav-bg: rgba(255, 255, 255, 0.75);
        }

        html { scroll-behavior: smooth; }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            overflow-x: hidden;
            transition: var(--transition);
        }

        /* ── NAV SYNC ── */
        nav {
            position: fixed;
            top: 0; left: 0; right: 0;
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
        .nav-logo span { color: var(--accent); }

        .nav-links { display: flex; gap: 24px; margin-left: 40px; }
        .nav-links a { color: var(--muted); text-decoration: none; font-size: 0.9rem; font-weight: 500; transition: 0.2s; }
        .nav-links a:hover, .nav-links a.active { color: var(--header-text); }

        .nav-right {
            display: flex;
            align-items: center;
            gap: 20px;
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
        .btn-logout:hover { color: var(--header-text); border-color: var(--accent); background: var(--accent-glow); }
        
        .nav-profile-link {
            display: flex; align-items: center; gap: 10px; text-decoration: none; 
            padding: 5px 14px 5px 5px; border-radius: 999px; 
            border: 1px solid var(--border); transition: all 0.2s;
        }
        .nav-profile-link:hover { border-color: var(--accent); background: var(--accent-glow); }
        .nav-profile-link span { font-size: 0.8rem; color: var(--muted); transition: 0.2s; }
        .nav-profile-link:hover span { color: var(--header-text); }
        
        /* THEME TOGGLE */
        .theme-toggle {
            width: 36px; height: 36px; border-radius: 50%; background: rgba(0,0,0,0.03);
            border: 1px solid var(--border); display: flex; align-items: center; justify-content: center;
            cursor: pointer; color: var(--header-text); transition: 0.3s; position: relative;
        }
        [data-theme="dark"] .theme-toggle { background: rgba(255,255,255,0.05); }
        .theme-toggle:hover { background: var(--accent); color: white; border-color: var(--accent); }

        .vip-badge {
            background: linear-gradient(135deg, #ffd700, #ffae00);
            color: #000; font-size: 0.65rem; font-weight: 800; padding: 2px 7px;
            border-radius: 4px; margin-left: 6px; text-transform: uppercase;
            box-shadow: 0 0 10px rgba(255, 215, 0, 0.3);
            display: inline-flex; align-items: center; justify-content: center;
        }

        .container { max-width: 1200px; margin: 0 auto; padding: 124px 40px 60px; min-height: calc(100vh - 64px); }
        .page-title { font-family: 'Syne', sans-serif; font-size: clamp(2.2rem, 5vw, 3.5rem); font-weight: 800; letter-spacing: -1.5px; margin-bottom: 12px; }
        .page-sub { font-size: 1rem; color: var(--muted); margin-bottom: 50px; }

        .order-card { background: var(--surface); border: 1px solid var(--border); border-radius: 16px; padding: 24px; margin-bottom: 24px; display: flex; flex-direction: column; gap: 20px; }
        
        .oc-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border); padding-bottom: 16px; }
        .oc-code { font-family: 'Space Mono', monospace; font-size: 1.1rem; font-weight: 700; color: var(--header-text); }
        .oc-date { font-size: 0.8rem; color: var(--muted); }
        
        .badge { padding: 6px 12px; border-radius: 99px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; }
        .badge.pending { background: rgba(245,158,11,0.1); color: var(--pending); border: 1px solid rgba(245,158,11,0.2); }
        .badge.paid { background: rgba(16,185,129,0.1); color: var(--paid); border: 1px solid rgba(16,185,129,0.2); }
        .badge.dp_paid { background: rgba(199,210,254,0.1); color: #818cf8; border: 1px solid rgba(129,140,248,0.2); }
        .badge.cancelled { background: rgba(239,68,68,0.1); color: var(--cancelled); border: 1px solid rgba(239,68,68,0.2); }
        .badge.completed { background: rgba(16,185,129,0.1); color: var(--paid); border: 1px solid rgba(16,185,129,0.2); }
        .badge.expired { background: rgba(100,116,139,0.1); color: var(--expired); border: 1px solid rgba(100,116,139,0.2); }

        .oc-body { display: flex; gap: 20px; }
        
        .oc-img { width: 100px; height: 100px; border-radius: 12px; object-fit: cover; }
        .oc-info { flex: 1; }
        .oc-info h3 { font-family: 'Syne', sans-serif; margin-bottom: 8px; font-size: 1.2rem; }
        .oc-meta { font-size: 0.85rem; color: var(--muted); margin-bottom: 4px; display: flex; align-items: center; gap: 6px; }
        .oc-price { font-size: 1.2rem; font-weight: 700; color: var(--accent); margin-top: 12px; }

        .oc-actions { display: flex; justify-content: flex-end; gap: 12px; border-top: 1px solid var(--border); padding-top: 16px; }
        
        .btn { padding: 10px 20px; border-radius: 8px; font-size: 0.85rem; font-weight: 600; cursor: pointer; border: none; text-decoration: none; transition: 0.2s; display: inline-flex; justify-content: center; align-items: center; }
        .btn-primary { background: var(--header-text); color: var(--bg); }
        .btn-primary:hover { background: var(--accent); color: #000; }
        .btn-sec { background: var(--surface-alpha); color: var(--text); border: 1px solid var(--border-alpha); }
        .btn-sec:hover { background: var(--accent-glow); color: var(--accent); }
        .btn-acc { background: var(--accent); color: var(--btn-text); }
        .btn-acc:hover { opacity: 0.8; }
        .btn-danger { background: transparent; color: var(--red); }
        .btn-danger:hover { background: var(--red-glow); }

        /* FILTER TABS */
        .filter-tabs { display: flex; gap: 8px; margin-bottom: 32px; overflow-x: auto; padding-bottom: 4px; scrollbar-width: none; }
        .filter-tabs::-webkit-scrollbar { display: none; }
        
        .filter-tab {
            padding: 10px 20px; border-radius: 99px; background: var(--surface-alpha);
            border: 1px solid var(--border-alpha); color: var(--muted); font-size: 0.85rem; font-weight: 600;
            text-decoration: none; transition: 0.2s; white-space: nowrap;
        }
        .filter-tab:hover { background: var(--accent-glow); color: var(--accent); border-color: var(--accent); }
        .filter-tab.active { background: var(--accent); color: #000; border-color: var(--accent); }

        /* MODAL SUCCESS STYLE */
        .modal-overlay {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.8); backdrop-filter: blur(12px);
            display: none; align-items: center; justify-content: center; z-index: 2000;
            opacity: 0; transition: opacity 0.4s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .modal-overlay.active { display: flex; opacity: 1; }
        .modal-content {
            background: var(--card); border: 1px solid var(--border);
            padding: 48px 32px; border-radius: 28px; text-align: center; max-width: 420px; width: 90%;
            transform: scale(0.9) translateY(30px); transition: all 0.5s cubic-bezier(0.16, 1, 0.3, 1);
            box-shadow: 0 30px 60px -12px rgba(0, 0, 0, 0.6);
            position: relative; overflow: hidden;
        }
        .modal-overlay.active .modal-content { transform: scale(1) translateY(0); }
        
        .modal-content::before {
            content: ''; position: absolute; top: -50%; left: -50%; width: 200%; height: 200%;
            background: radial-gradient(circle, rgba(200,181,255,0.05) 0%, transparent 70%);
            z-index: 0; pointer-events: none;
        }

        .modal-icon-wrapper {
            width: 90px; height: 90px; background: rgba(16,185,129,0.1); color: #10b981;
            border-radius: 24px; display: flex; align-items: center; justify-content: center; 
            margin: 0 auto 28px; transform: rotate(-10deg);
            box-shadow: 0 10px 20px rgba(16, 185, 129, 0.1);
            position: relative; z-index: 1;
        }
        .modal-icon-wrapper.dp { background: rgba(129,140,248,0.1); color: #818cf8; box-shadow: 0 10px 20px rgba(129, 140, 248, 0.1); }
        
        .modal-content h2 { font-family: 'Syne', sans-serif; margin-bottom: 12px; font-size: 1.7rem; font-weight: 800; position: relative; z-index: 1; }
        .modal-content p { color: var(--muted); margin-bottom: 32px; line-height: 1.6; font-size: 1rem; position: relative; z-index: 1; }
        .modal-btn { width: 100%; padding: 16px; border-radius: 14px; border: none; font-family: 'Inter', sans-serif; font-weight: 700; cursor: pointer; transition: 0.3s; position: relative; z-index: 1; }
        .modal-btn-primary { background: var(--accent); color: #000; box-shadow: 0 8px 16px rgba(200, 181, 255, 0.2); }
        .modal-btn-primary:hover { transform: translateY(-2px); box-shadow: 0 12px 24px rgba(200, 181, 255, 0.3); }

        @keyframes confetti {
            0% { transform: translateY(0) rotate(0deg); opacity: 1; }
            100% { transform: translateY(100vh) rotate(720deg); opacity: 0; }
        }
        .confetti {
            position: absolute; width: 10px; height: 10px; background: var(--accent);
            top: -10px; z-index: 0; animation: confetti 3s linear forwards;
        }

        @media (max-width: 600px) {
            .oc-body { flex-direction: column; }
            .oc-img { width: 100%; height: 160px; }
            .oc-actions { flex-direction: column; }
            .btn { width: 100%; }
        }
        /* VIP Style Sync */
        .vip-badge {
            background: linear-gradient(135deg, #ffd700, #ffae00);
            color: #000; font-size: 0.65rem; font-weight: 800; padding: 2px 7px;
            border-radius: 4px; margin-left: 6px; text-transform: uppercase;
            box-shadow: 0 0 10px rgba(255, 215, 0, 0.3);
            display: inline-flex; align-items: center; justify-content: center;
        }
        .nav-vip-btn {
            display: inline-flex; align-items: center; gap: 7px;
            padding: 7px 16px; border-radius: 999px;
            background: linear-gradient(135deg, rgba(255,215,0,0.12), rgba(255,174,0,0.06));
            border: 1px solid rgba(255,215,0,0.4);
            color: #ffd700; font-weight: 700; font-size: 0.78rem;
            text-decoration: none; transition: all 0.25s; white-space: nowrap;
            box-shadow: 0 0 14px rgba(255,215,0,0.12), inset 0 1px 0 rgba(255,255,255,0.05);
            letter-spacing: 0.3px;
            animation: vip-pulse 2.5s ease-in-out infinite;
        }
        @keyframes vip-pulse {
            0%, 100% { box-shadow: 0 0 14px rgba(255,215,0,0.12), inset 0 1px 0 rgba(255,255,255,0.05); }
            50% { box-shadow: 0 0 22px rgba(255,215,0,0.28), inset 0 1px 0 rgba(255,255,255,0.05); }
        }
        .nav-vip-btn:hover {
            background: linear-gradient(135deg, rgba(255,215,0,0.22), rgba(255,174,0,0.14));
            border-color: rgba(255,215,0,0.7);
            box-shadow: 0 0 28px rgba(255,215,0,0.3);
            transform: translateY(-1px);
            animation: none;
        }
    </style>
</head>
<body>

<nav>
    <div style="display:flex; align-items:center; gap: 24px;">
        <div class="nav-logo">Tix<span>Now</span></div>
        <div class="nav-links">
            <a href="user_dashboard">Konser</a>
            <a href="user_orders" class="active">Pesanan Saya</a>
        </div>
        <?php if(!$isVIP): ?>
        <a href="buy_vip" class="nav-vip-btn">
            <svg width="14" height="14" viewBox="0 0 20 20" fill="currentColor">
                <path d="M10 1l2.39 5.26L18 7.27l-4 3.89.94 5.5L10 14l-4.94 2.66.94-5.5L2 7.27l5.61-.01L10 1z"/>
            </svg>
            Upgrade VIP
        </a>
        <?php endif; ?>
    </div>
    <div class="nav-right">
        <div class="theme-toggle" id="themeToggle" title="Pindah Tema">
            <svg id="moonIcon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path></svg>
            <svg id="sunIcon" style="display:none;" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"></circle><line x1="12" y1="1" x2="12" y2="3"></line><line x1="12" y1="21" x2="12" y2="23"></line><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line><line x1="1" y1="12" x2="3" y2="12"></line><line x1="21" y1="12" x2="23" y2="12"></line><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line></svg>
        </div>
        <a href="profile" class="nav-profile-link">
            <?php if ($fotoProfil && file_exists($fotoProfil)): ?>
            <img src="<?php echo htmlspecialchars($fotoProfil); ?>?v=<?php echo time(); ?>" style="width:30px;height:30px;border-radius:50%;object-fit:cover;border:2px solid var(--accent-glow);" alt="">
            <?php else: ?>
            <div style="width:30px;height:30px;border-radius:50%;background:var(--accent-glow);border:2px solid var(--border);display:flex;align-items:center;justify-content:center;font-family:'Syne',sans-serif;font-size:0.75rem;font-weight:700;color:var(--accent);"><?php echo strtoupper(substr($appUser['username'] ?? 'U', 0, 1)); ?></div>
            <?php endif; ?>
            <span>@<?php echo htmlspecialchars($appUser['username'] ?? 'User'); ?></span>
            <?php if($isVIP): ?>
                <span class="vip-badge">VIP</span>
            <?php endif; ?>
        </a>
        <a href="logout" class="btn-logout">Keluar</a>
    </div>
</nav>

<div class="container">
    <h1 class="page-title">Pesanan Saya</h1>
    <p class="page-sub">Lacak status pesanan dan e-tiket kamu di sini.</p>

    <div class="filter-tabs">
        <a href="user_orders?status=all" class="filter-tab <?php echo $statusFilter === 'all' ? 'active' : ''; ?>">Semua</a>
        <a href="user_orders?status=pending" class="filter-tab <?php echo $statusFilter === 'pending' ? 'active' : ''; ?>">Menunggu Pembayaran</a>
        <a href="user_orders?status=paid" class="filter-tab <?php echo $statusFilter === 'paid' ? 'active' : ''; ?>">Sudah Dibayar</a>
        <a href="user_orders?status=cancelled" class="filter-tab <?php echo $statusFilter === 'cancelled' ? 'active' : ''; ?>">Dibatalkan</a>
    </div>

    <!-- Modal Notification -->
    <div class="modal-overlay" id="paymentSuccessModal">
        <div class="modal-content">
            <div id="confettiContainer"></div>
            <div class="modal-icon-wrapper" id="modalIcon">
                <svg width="40" height="40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path>
                </svg>
            </div>
            <h2 id="modalTitle">Pembayaran Berhasil</h2>
            <p id="modalMsg">Pesanan Anda telah kami terima dan akan segera diproses.</p>
            <button class="modal-btn modal-btn-primary" onclick="closePaymentModal()">Siap, Terima Kasih!</button>
        </div>
    </div>

    <?php if (empty($orders)): ?>
        <div style="text-align:center; padding: 60px 20px; background:var(--surface); border:1px dashed var(--border); border-radius:20px;">
            <svg width="48" height="48" fill="none" stroke="var(--border)" viewBox="0 0 24 24" style="margin-bottom:16px;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
            <h3 style="margin-bottom:8px;">
                <?php 
                if($statusFilter === 'pending') echo "Tidak Ada Pembayaran Tertunda";
                elseif($statusFilter === 'paid') echo "Belum Ada Tiket Terbayar";
                elseif($statusFilter === 'cancelled') echo "Tidak Ada Pesanan Dibatalkan";
                else echo "Belum Ada Pesanan";
                ?>
            </h3>
            <p style="color:var(--muted); font-size:0.9rem; margin-bottom:20px;">Riwayat pesanan Anda akan muncul di sini.</p>
            <a href="user_dashboard" class="btn btn-primary">Cari Konser</a>
        </div>
    <?php else: ?>
        <?php foreach($orders as $o): 
            $poster = !empty($o['poster_url']) ? htmlspecialchars($o['poster_url']) : 'https://via.placeholder.com/300';
            $isPending = $o['status'] === 'pending';
            $isPaid = $o['status'] === 'paid';
            $isDP = $o['status'] === 'dp_paid';
        ?>
        <div class="order-card">
            <div class="oc-header">
                <div>
                    <div class="oc-code"><?php echo htmlspecialchars($o['order_code']); ?></div>
                    <div class="oc-date"><?php 
                        $rawDate = $o['tanggal_order'] ?? '';
                        $safeDate = (!empty($rawDate) && strpos($rawDate, '0000') === false) ? $rawDate : 'now';
                        echo date('d M Y, H:i', strtotime($safeDate)); 
                    ?></div>
                </div>
                <div class="oc-header-right" style="text-align:right;">
                    <div class="badge <?php echo $o['status']; ?>"><?php echo strtoupper($o['status'] === 'dp_paid' ? 'Paid (DP)' : $o['status']); ?></div>
                    <?php if ($o['status'] === 'cancelled' && $o['refund_status'] !== 'none'): ?>
                        <div class="badge <?php echo $o['refund_status']; ?>" style="margin-top:4px; font-size:0.6rem; padding: 3px 8px;">
                            REFUND: <?php echo strtoupper($o['refund_status']); ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="oc-body">
                <img src="<?php echo $poster; ?>" class="oc-img">
                <div class="oc-info">
                    <h3><?php echo htmlspecialchars($o['nama_event']); ?></h3>
                    <div class="oc-meta">
                        <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        <?php echo date('d M Y', strtotime($o['tanggal'])); ?>
                    </div>
                    <div class="oc-meta">
                        <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        <?php echo htmlspecialchars($o['venue']); ?>
                    </div>
                    <div class="oc-price">
                        Rp <?php echo number_format($o['total'], 0, ',', '.'); ?>
                        <?php if($isDP): ?>
                            <span style="font-size:0.75rem; color:var(--muted); font-weight:normal; display:block; margin-top:4px;">Terbayar DP: Rp <?php echo number_format($o['amount_paid'], 0, ',', '.'); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="oc-actions">
                <?php if ($isPending): ?>
                    <form action="cancel_order" method="POST" onsubmit="return showConfirmModal(event, this, 'Yakin ingin membatalkan pesanan ini?');">
                        <input type="hidden" name="id_order" value="<?php echo $o['id_order']; ?>">
                        <button type="submit" class="btn btn-danger">Batalkan</button>
                    </form>
                    <a href="checkout?order=<?php echo urlencode($o['order_code']); ?>" class="btn btn-primary">Lanjut Bayar</a>
                <?php endif; ?>

                <?php if ($isDP): ?>
                    <form action="cancel_order" method="POST" onsubmit="return showConfirmModal(event, this, 'Membatalkan pesanan yang sudah DP akan menghapus slot Anda. Dana DP akan diproses untuk Refund. Lanjutkan?');">
                        <input type="hidden" name="id_order" value="<?php echo $o['id_order']; ?>">
                        <button type="submit" class="btn btn-danger">Batalkan & Refund</button>
                    </form>
                    <a href="checkout?order=<?php echo urlencode($o['order_code']); ?>" class="btn btn-primary" style="background: #818cf8; color: white;">Lunasi Pembayaran</a>
                <?php endif; ?>

                <?php if ($isPaid): ?>
                    <form action="cancel_order" method="POST" onsubmit="return showConfirmModal(event, this, 'MEMBATALKAN pesanan yang sudah Lunas akan menghapus E-Tiket Anda secara permanen. Dana akan diproses untuk Refund. Lanjutkan?');" style="margin-right:8px;">
                        <input type="hidden" name="id_order" value="<?php echo $o['id_order']; ?>">
                        <button type="submit" class="btn btn-danger">Batalkan & Refund</button>
                    </form>
                    <a href="e_ticket?order=<?php echo urlencode($o['order_code']); ?>" class="btn btn-acc">Lihat E-Tiket</a>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- CONFIRMATION MODAL -->
<div class="modal-overlay" id="confirmModal">
    <div class="modal-content" style="max-width: 450px;">
        <div class="modal-icon-wrapper" style="background: rgba(248,113,113,0.1); color: #f87171; box-shadow: 0 10px 20px rgba(248,113,113,0.1);">
            <svg width="40" height="40" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
        </div>
        <h2>Konfirmasi Pembatalan</h2>
        <p id="confirmMsg" style="margin-bottom: 24px;"></p>
        <div style="display:flex; gap: 12px;">
            <button class="modal-btn" style="background: rgba(255,255,255,0.05); color: var(--text); border: 1px solid var(--border);" onclick="closeConfirmModal()">Tutup</button>
            <button class="modal-btn" style="background: #ef4444; color: white; box-shadow: 0 8px 16px rgba(239,68,68,0.2); border: none;" id="confirmBtnAction">Lanjutkan</button>
        </div>
    </div>
</div>

<script>
    // THEME TOGGLE LOGIC
    const themeToggle = document.getElementById('themeToggle');
    const moonIcon = document.getElementById('moonIcon');
    const sunIcon = document.getElementById('sunIcon');

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

    // MODAL LOGIC
    const modal = document.getElementById('paymentSuccessModal');
    const modalTitle = document.getElementById('modalTitle');
    const modalMsg = document.getElementById('modalMsg');
    const modalIcon = document.getElementById('modalIcon');
    const confettiContainer = document.getElementById('confettiContainer');

    function createConfetti() {
        for(let i=0; i<30; i++) {
            const c = document.createElement('div');
            c.className = 'confetti';
            c.style.left = Math.random() * 100 + '%';
            c.style.animationDelay = Math.random() * 2 + 's';
            c.style.backgroundColor = ['#c8b5ff', '#10b981', '#818cf8', '#f59e0b'][Math.floor(Math.random()*4)];
            confettiContainer.appendChild(c);
        }
    }

    function showPaymentModal(type) {
        if (type === 'paid') {
            modalTitle.innerText = "Pembayaran Lunas!";
            modalMsg.innerText = "Yesss! Pembayaran kamu berhasil. E-Tiket sudah siap dan bisa kamu akses sekarang.";
            modalIcon.classList.remove('dp');
        } else if (type === 'dp_paid') {
            modalTitle.innerText = "Booking Berhasil!";
            modalMsg.innerText = "Pembayaran DP 30% telah kami terima. Slot amannn! Pastikan lakukan pelunasan sebelum hari H untuk mendapatkan tiket fisik/E-Tiket ya.";
            modalIcon.classList.add('dp');
        } else if (type === 'refund_pending') {
            modalTitle.innerText = "Pembatalan Berhasil";
            modalMsg.innerText = "Pesanan Anda telah dibatalkan. Pengajuan refund dana sedang diproses oleh admin (Estimasi 1x24 jam).";
            modalIcon.innerHTML = `<svg width="40" height="40" fill="none" stroke="#f87171" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>`;
        } else if (type === 'cancelled') {
        } else if (type === 'error') {
            const params = new URLSearchParams(window.location.search);
            const errMsg = params.get('err') || "Terjadi kesalahan saat memproses permintaan Anda.";
            modalTitle.innerText = "Gagal Memproses";
            modalMsg.innerText = errMsg;
            modalIcon.innerHTML = `<svg width="40" height="40" fill="none" stroke="#f87171" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>`;
        } else {
            // Unrecognized type, don't show modal
            return;
        }
        
        modal.style.display = 'flex';
        setTimeout(() => {
            modal.classList.add('active');
            if(type !== 'error') createConfetti();
        }, 10);
    }

    function closePaymentModal() {
        modal.classList.remove('active');
        setTimeout(() => {
            modal.style.display = 'none';
            // Clear URL param without reload
            const url = new URL(window.location);
            url.searchParams.delete('msg');
            url.searchParams.delete('err');
            window.history.replaceState({}, '', url);
        }, 400);
    }

    // CHECK FOR MSG ON LOAD
    window.addEventListener('DOMContentLoaded', () => {
        const params = new URLSearchParams(window.location.search);
        const msg = params.get('msg');
        if (msg) {
            showPaymentModal(msg);
        }
    });

    // CONFIRM MODAL LOGIC
    let formToSubmit = null;
    const confirmModal = document.getElementById('confirmModal');
    const confirmMsg = document.getElementById('confirmMsg');
    const confirmBtnAction = document.getElementById('confirmBtnAction');

    function showConfirmModal(e, formElement, message) {
        e.preventDefault(); // Prevent native submit
        formToSubmit = formElement;
        confirmMsg.innerText = message;
        confirmModal.style.display = 'flex';
        setTimeout(() => { confirmModal.classList.add('active'); }, 10);
        return false;
    }

    function closeConfirmModal() {
        confirmModal.classList.remove('active');
        setTimeout(() => { confirmModal.style.display = 'none'; }, 400);
    }

    confirmBtnAction.addEventListener('click', () => {
        if (formToSubmit) {
            formToSubmit.submit();
        }
        closeConfirmModal();
    });
</script>
</body>
</html>
