<?php
session_start();
require_once 'koneksi.php';

if (!isset($_SESSION['id_user'])) {
    header("Location: index");
    exit;
}

$id_order = $_GET['order'] ?? '';
if (!$id_order) {
    header("Location: user_orders");
    exit;
}

$id_user = $_SESSION['id_user'];

// Get Order and tickets
$stmt = $conn->prepare("
    SELECT o.*, c.nama_event, c.tanggal, c.venue, c.poster_url 
    FROM orders o
    JOIN event c ON o.id_event = c.id_event
    WHERE o.order_code = ? AND o.id_user = ? AND o.status = 'paid'
");
$stmt->execute([$id_order, $id_user]);
$order = $stmt->fetch();

if (!$order) {
    echo "<script>alert('E-Tiket tidak ditemukan atau belum dibayar.'); window.location.href='user_orders';</script>";
    exit;
}

// Get Tickets joined with Order Items and Kategori
$tStmt = $conn->prepare("
    SELECT t.*, k.nama_tiket
    FROM attendee t
    JOIN order_detail i ON t.id_detail = i.id_detail
    JOIN tiket k ON i.id_tiket = k.id_tiket
    WHERE i.id_order = ?
");
$tStmt->execute([$order['id_order']]);
$tickets = $tStmt->fetchAll();

// Get complete user info
$stmtUser = $conn->prepare("SELECT nama, username, foto_profil, is_vip FROM users WHERE id_user = ?");
$stmtUser->execute([$id_user]);
$appUser = $stmtUser->fetch();
$fotoProfil = $appUser['foto_profil'] ?? null;
$isVIP = ($appUser['is_vip'] ?? 0) == 1;

$posterUrl = !empty($order['poster_url']) ? htmlspecialchars($order['poster_url']) : 'https://images.unsplash.com/photo-1540039155732-68b2dbceaebd?q=80&w=600&auto=format&fit=crop';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>E-Tiket - <?php echo htmlspecialchars($order['order_code']); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;500;600;700;800&family=Inter:wght@300;400;500&display=swap" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
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
            --accent: #a78bfa;
            --accent-glow: rgba(167,139,250,0.15);
            --red: #f87171;
            --red-glow: rgba(248,113,113,0.12);
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
            --border: rgba(0,0,0,0.06);
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

        html { scroll-behavior: smooth; }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            overflow-x: hidden;
            padding-top: 104px;
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

        .container { max-width: 1200px; margin: 0 auto; padding: 60px 40px; }
        .header-top { width: 100%; margin: 0 auto 30px; display: flex; justify-content: space-between; align-items: center; }
        .btn-back { display: inline-flex; align-items: center; gap: 8px; color: var(--muted); text-decoration: none; font-size: 0.9rem; transition: 0.2s; }
        .btn-back:hover { color: var(--text); }

        .ticket-wrapper { max-width: 800px; margin: 0 auto; display: flex; flex-direction: column; gap: 30px; }
        
        /* Premium Ticket Design - Refined to match image */
        .ticket {
            display: flex; 
            width: 800px;
            height: 320px;
            background: #111; 
            border-radius: 24px; 
            overflow: hidden; 
            position: relative;
            box-shadow: 0 40px 80px rgba(0,0,0,0.6);
            margin: 0 auto;
        }

        .t-left { 
            flex: 1.8; 
            padding: 35px 40px; 
            position: relative; 
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        
        .t-right { 
            flex: 1;
            background: #ffffff; 
            display: flex; 
            flex-direction: column; 
            align-items: center; 
            justify-content: center; 
            padding: 30px; 
            position: relative;
        }

        /* Dashed Divider with Circles */
        .ticket-divider {
            position: absolute;
            left: 64.3%; /* Align with the stub start */
            top: 0; bottom: 0;
            width: 2px;
            border-left: 2px dashed rgba(255,255,255,0.3);
            z-index: 10;
        }
        
        .ticket::before {
            content: ''; 
            position: absolute; 
            width: 44px; height: 44px; 
            background: var(--bg); 
            border-radius: 50%; 
            z-index: 15; 
            top: 50%; 
            right: 32.7%; /* Center on the dashed line */
            transform: translate(50%, -50%);
            box-shadow: inset -5px 0 10px rgba(0,0,0,0.1);
        }

        .poster-bg { position: absolute; inset: 0; z-index: 0; }
        .poster-bg img { width: 100%; height: 100%; object-fit: cover; opacity: 0.5; }
        
        .t-overlay {
            position: absolute; inset: 0; z-index: 1;
            background: linear-gradient(
                to right,
                rgba(0, 0, 0, 0.9) 0%,
                rgba(0, 0, 0, 0.7) 50%,
                rgba(0, 0, 0, 0.4) 100%
            );
        }

        .t-left-content { position: relative; z-index: 5; height: 100%; display: flex; flex-direction: column; }
        
        .t-logo { 
            font-family: 'Syne', sans-serif; 
            font-size: 1.4rem; 
            font-weight: 800; 
            color: white; 
            margin-bottom: 25px;
            letter-spacing: -0.5px;
        }
        .t-logo span { color: var(--accent); }
        
        .t-title { 
            font-family: 'Syne', sans-serif; 
            font-size: 2.4rem; 
            font-weight: 800; 
            margin-bottom: 5px; 
            line-height: 1.1; 
            color: #ffffff; 
            letter-spacing: -1px;
        }
        
        .t-vip-label {
            font-size: 0.9rem;
            font-weight: 800;
            color: var(--accent);
            text-transform: uppercase;
            letter-spacing: 2px;
            margin-bottom: 30px;
            font-family: 'Space Mono', monospace;
        }

        .t-details-grid {
            display: grid;
            grid-template-columns: 1.2fr 1fr;
            gap: 20px;
            margin-top: auto;
        }

        .info-group { display: flex; flex-direction: column; gap: 4px; }
        .info-label { 
            font-size: 0.65rem; 
            font-weight: 800; 
            color: rgba(255,255,255,0.5); 
            letter-spacing: 0.08em; 
            text-transform: uppercase;
        }
        .info-val { 
            font-size: 1.05rem; 
            font-weight: 700; 
            color: #fff; 
        }

        .qr-wrapper {
            background: white;
            padding: 12px;
            border-radius: 18px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.05);
            margin-bottom: 15px;
        }

        .qr-code { width: 150px; height: 150px; }
        .qr-code img { width: 100%; height: 100%; }

        .t-id-label {
            font-family: 'Space Mono', monospace;
            font-size: 0.95rem;
            font-weight: 800;
            color: #000;
            letter-spacing: 1px;
            margin-bottom: 4px;
        }
        
        .scan-msg {
            font-size: 0.75rem;
            color: #888;
            font-weight: 500;
        }

        @media (max-width: 850px) {
            .ticket { 
                width: 100%; 
                height: auto; 
                flex-direction: column; 
            }
            .ticket-divider { display: none; }
            .ticket::before { display: none; }
            .t-right { padding: 40px 20px; }
            .t-left { padding: 35px 25px; min-height: 300px; }
            .t-title { font-size: 1.8rem; }
        }

        /* TICKET SLIDE CSS */
        .slider-container {
            max-width: 800px;
            margin: 0 auto;
            overflow: hidden; /* Kunci agar tidak meleber keluar */
            position: relative;
            border-radius: 20px;
        }
        .slider-track {
            display: flex;
            transition: transform 0.5s cubic-bezier(0.4, 0, 0.2, 1);
            width: 100%;
        }
        .ticket { 
            flex: 0 0 100%; /* Setiap tiket memakan 100% area viewport */
            display: flex; /* Kembalikan ke flex untuk layout internal tiket */
        }

        .slider-controls {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 24px;
            margin-top: 30px;
        }
        /* ... styles nav-btn ... */
        /* ... styles nav-btn tetap sama ... */
        .nav-btn {
            width: 48px; height: 48px; border-radius: 50%;
            background: var(--surface); border: 1px solid var(--border);
            color: var(--text); display: flex; align-items: center; justify-content: center;
            cursor: pointer; transition: 0.3s;
        }
        .nav-btn:hover:not(:disabled) { border-color: var(--accent); color: var(--accent); transform: scale(1.1); }
        .nav-btn:disabled { opacity: 0.3; cursor: not-allowed; }
        .slider-indicator { font-family: 'Syne', sans-serif; font-size: 1rem; font-weight: 600; color: var(--muted); }
        .slider-indicator span { color: var(--accent); }

        /* QR MODAL */
        .qr-modal { position: fixed; inset: 0; background: rgba(0,0,0,0.92); backdrop-filter: blur(10px); display: none; align-items: center; justify-content: center; z-index: 2000; padding: 40px; cursor: zoom-out; opacity: 0; transition: opacity 0.3s ease; }
        .qr-modal.active { display: flex; opacity: 1; }
        .qr-modal-content { background: white; padding: 40px; border-radius: 30px; box-shadow: 0 0 100px rgba(0,0,0,0.5); transform: scale(0.9); transition: transform 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275); width: 450px; height: 450px; max-width: 90vmin; max-height: 90vmin; display: flex; align-items: center; justify-content: center; }
        .qr-modal.active .qr-modal-content { transform: scale(1); }
        .qr-modal-content img { width: 100%; height: 100%; object-fit: contain; }
        .qr-hint { position: absolute; bottom: 40px; color: white; font-size: 0.9rem; font-family: 'Syne', sans-serif; opacity: 0.7; }

        @media (max-width: 768px) {
            .ticket { flex-direction: column; max-width: 100%; }
            .t-right { width: 100%; border-left: none; border-top: 2px dashed #ccc; padding: 40px 20px; }
            .ticket::before { display: none; }
            .slider-track { gap: 20px; }
        }

        /* VIP Style Sync */
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
            <a href="user_orders">Pesanan Saya</a>
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

<div class="container" style="padding: 20px;">

<div class="header-top">
    <a href="user_orders" class="btn-back">
        <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
        Kembali ke Pesanan
    </a>
    <div style="font-family:'Space Mono',monospace; font-size:0.9rem; color:var(--muted);">ORDER: <?php echo htmlspecialchars($order['order_code']); ?></div>
</div>

<div class="slider-container">
    <div class="slider-track" id="sliderTrack">
        <?php foreach($tickets as $idx => $t): ?>
        <div class="ticket">
            <div class="poster-bg"><img src="<?php echo $posterUrl; ?>"></div>
            <div class="t-overlay"></div>
            <div class="ticket-divider"></div>
            
            <!-- Left Side: Main Info -->
            <div class="t-left">
                <div class="t-left-content">
                    <div class="t-logo">Tix<span>Now</span></div>
                    
                    <div>
                        <h1 class="t-title"><?php echo htmlspecialchars($order['nama_event']); ?></h1>
                        <div class="t-vip-label"><?php echo htmlspecialchars($t['nama_tiket']); ?></div>
                    </div>
                    
                    <div class="t-details-grid">
                        <div class="info-group">
                            <span class="info-label">DATE & TIME</span>
                            <span class="info-val"><?php echo date('d M Y, H:i', strtotime($order['tanggal'])); ?></span>
                        </div>
                        <div class="info-group">
                            <span class="info-label">LOCATION</span>
                            <span class="info-val"><?php echo htmlspecialchars($order['venue']); ?></span>
                        </div>
                        <div class="info-group" style="grid-column: span 2; margin-top: 10px;">
                            <span class="info-label">TICKET HOLDER</span>
                            <span class="info-val"><?php echo htmlspecialchars($t['nama_pemegang']); ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Side: The QR Stub -->
            <div class="t-right">
                <div class="qr-wrapper" id="qr-wrap-<?php echo $idx; ?>" onclick="zoomQR('<?php echo htmlspecialchars($t['kode_tiket']); ?>', this)">
                    <img src="<?php echo htmlspecialchars($t['qr_code_url']); ?>" alt="QR Code" class="qr-code"
                        onerror="this.style.display='none'; renderQR('<?php echo htmlspecialchars($t['kode_tiket']); ?>', 'qr-wrap-<?php echo $idx; ?>');">
                </div>
                <div class="t-id-label"><?php echo htmlspecialchars($t['kode_tiket']); ?></div>
                <div class="scan-msg">Scan at the entrance</div>
                
                <button class="btn-download-premium" style="margin-top: 20px; width: 140px;" onclick="downloadTicket(this.closest('.ticket'), '<?php echo $t['kode_tiket']; ?>')">
                    <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="margin-right: 5px;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    DOWNLOAD
                </button>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<?php if (count($tickets) > 1): ?>
<div class="slider-controls">
    <button class="nav-btn" onclick="moveSlide(-1)" id="prevBtn" disabled>
        <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"/></svg>
    </button>
    <div class="slider-indicator"><span id="currIdx">1</span> dari <?php echo count($tickets); ?></div>
    <button class="nav-btn" onclick="moveSlide(1)" id="nextBtn">
        <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/></svg>
    </button>
</div>
<?php endif; ?>

<!-- QR ZOOM MODAL -->
<div class="qr-modal" id="qrModal" onclick="closeQR()">
    <div class="qr-modal-content" onclick="event.stopPropagation()">
        <img id="zoomedImg" src="" alt="Zoomed QR">
    </div>
    <div class="qr-hint">Klik di mana saja untuk menutup</div>
</div>

<script>
    // THEME TOGGLE
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

    // TICKET SLIDE LOGIC
    let currentSlide = 0;
    const totalSlides = <?php echo count($tickets); ?>;
    const track = document.getElementById('sliderTrack');
    const currIdxText = document.getElementById('currIdx');
    const prevBtn = document.getElementById('prevBtn');
    const nextBtn = document.getElementById('nextBtn');

    function moveSlide(dir) {
        currentSlide += dir;
        if (currentSlide < 0) currentSlide = 0;
        if (currentSlide >= totalSlides) currentSlide = totalSlides - 1;

        // Geser track ke kiri berdasarkan persentase (100% per tiket)
        track.style.transform = `translateX(-${currentSlide * 100}%)`;
        
        if(currIdxText) currIdxText.innerText = currentSlide + 1;
        
        if(prevBtn) prevBtn.disabled = (currentSlide === 0);
        if(nextBtn) nextBtn.disabled = (currentSlide === totalSlides - 1);
    }

    function downloadTicket(ticketElement, ticketCode) {
        // Sembunyikan tombol download sementara saat rendering
        const btn = ticketElement.querySelector('.btn-download');
        btn.style.display = 'none';

        html2canvas(ticketElement, {
            scale: 3, // Ultra-high resolution for scanning
            useCORS: true,
            backgroundColor: null // Transparent background to preserve internal styles
        }).then(canvas => {
            const link = document.createElement('a');
            link.download = `Tiket-${ticketCode}.png`;
            link.href = canvas.toDataURL("image/png");
            link.click();
            
            // Tampilkan kembali tombol download
            btn.style.display = 'inline-flex';
        });
    }

    function renderQR(text, containerId) {
        const container = document.getElementById(containerId);
        if (!container) return;
        
        // Clear container first
        container.innerHTML = '';
        
        // Generate QR via library
        new QRCode(container, {
            text: text,
            width: 220,
            height: 220,
            colorDark: "#000000",
            colorLight: "#ffffff",
            correctLevel: QRCode.CorrectLevel.H
        });

        // Add zoom ability to the new canvas
        container.onclick = () => zoomQR(text, container);
    }

    // QR ZOOM LOGIC
    const qrModal = document.getElementById('qrModal');
    const zoomedImg = document.getElementById('zoomedImg');
    const modalContent = document.querySelector('.qr-modal-content');

    function zoomQR(source, element) {
        // Clear previous zoomed content
        modalContent.innerHTML = '';
        
        if (element.querySelector('canvas')) {
            // If it's a fallback canvas, clone it
            const canvas = element.querySelector('canvas');
            const newCanvas = document.createElement('canvas');
            newCanvas.width = 1000;
            newCanvas.height = 1000;
            newCanvas.style.width = '100%';
            newCanvas.style.height = '100%';
            newCanvas.style.objectFit = 'contain';
            
            const ctx = newCanvas.getContext('2d');
            // Re-render for high quality zoom
            new QRCode(newCanvas, {
                text: source,
                width: 1000,
                height: 1000,
                colorDark: "#000000",
                colorLight: "#ffffff",
                correctLevel: QRCode.CorrectLevel.H
            });
            modalContent.appendChild(newCanvas);
        } else {
            // If it's a regular image
            const img = document.createElement('img');
            img.src = element.querySelector('img').src;
            img.style.width = '100%';
            img.style.height = '100%';
            img.style.objectFit = 'contain';
            modalContent.appendChild(img);
        }

        qrModal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closeQR() {
        qrModal.classList.remove('active');
        document.body.style.overflow = '';
    }
</script>
</body>
</html>
