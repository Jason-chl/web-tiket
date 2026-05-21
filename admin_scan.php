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
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR Scanner Admin - TixNow</title>
    <script>
        (function () {
            const savedTheme = localStorage.getItem('theme') || 'dark';
            document.documentElement.setAttribute('data-theme', savedTheme);
        })();
    </script>
    <link
        href="https://fonts.googleapis.com/css2?family=Syne:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap"
        rel="stylesheet">
    <script src="https://unpkg.com/html5-qrcode"></script>
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
            --red-glow: rgba(248, 113, 113, 0.12);
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
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            display: flex;
            transition: var(--transition);
        }

        /* SIDEBAR (Unified) */
        .sidebar {
            width: 230px;
            min-height: 100vh;
            background: var(--surface);
            border-right: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            padding: 28px 0;
            position: fixed;
            top: 0;
            left: 0;
            bottom: 0;
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

        .sidebar-logo span {
            color: var(--accent);
        }

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
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.2s;
            border-left: 2px solid transparent;
            text-decoration: none;
        }

        .sidebar-item:hover,
        .sidebar-item.active {
            color: var(--text);
            background: var(--accent-glow);
            border-left-color: var(--accent);
        }

        .sidebar-item svg {
            width: 16px;
            height: 16px;
            opacity: 0.6;
            flex-shrink: 0;
        }

        .sidebar-item:hover svg,
        .sidebar-item.active svg {
            opacity: 1;
            color: var(--accent);
        }

        .sidebar-bottom {
            margin-top: auto;
            padding: 24px;
            border-top: 1px solid var(--border);
        }

        .theme-toggle-container {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
            padding: 10px 14px;
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid var(--border);
            border-radius: 12px;
            position: relative;
            z-index: 1000;
            transition: var(--transition);
        }

        .theme-label {
            font-size: 0.75rem;
            color: var(--muted);
            font-weight: 500;
        }

        .theme-toggle {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: var(--surface);
            border: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            color: var(--header-text);
            transition: var(--transition);
            position: relative;
            z-index: 999;
            pointer-events: auto;
        }

        .theme-toggle:hover {
            border-color: var(--accent);
            transform: scale(1.1);
        }

        .admin-info {
            font-size: 0.78rem;
            color: var(--muted);
            margin-bottom: 12px;
            line-height: 1.5;
        }

        .admin-info strong {
            display: block;
            color: var(--text);
            font-weight: 500;
            font-size: 0.85rem;
        }

        .btn-logout {
            display: block;
            text-align: center;
            padding: 9px;
            border-radius: 8px;
            background: var(--red-glow);
            border: 1px solid rgba(248, 113, 113, 0.2);
            color: var(--red);
            font-size: 0.8rem;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.2s;
        }

        .btn-logout:hover {
            background: rgba(248, 113, 113, 0.2);
        }

        /* MAIN CONTENT */
        .main {
            margin-left: 230px;
            flex: 1;
            padding: 40px;
            transition: var(--transition);
        }

        .page-header {
            margin-bottom: 40px;
        }

        .page-title {
            font-family: 'Syne', sans-serif;
            font-size: 2rem;
            font-weight: 800;
            margin-bottom: 8px;
        }

        .page-sub {
            color: var(--muted);
            font-size: 0.95rem;
        }

        .scanner-layout {
            max-width: 800px;
            margin: 0 auto;
        }

        .scanner-container {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 30px;
            padding: 25px;
            position: relative;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.2);
        }

        #reader__dashboard {
            border: none !important;
            padding: 10px !important;
        }

        /* Mode Switcher Tabs */
        .mode-tabs {
            display: flex;
            background: var(--surface-alpha);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 6px;
            margin-bottom: 24px;
            gap: 6px;
        }

        .mode-tab {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px;
            border-radius: 12px;
            border: none;
            background: transparent;
            color: var(--muted);
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            transition: 0.2s;
        }

        .mode-tab.active {
            background: var(--card);
            color: var(--accent);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .mode-tab:not(.active):hover {
            color: var(--text);
            background: rgba(255, 255, 255, 0.03);
        }

        /* Result Box */
        .result-box {
            margin-top: 24px;
            display: none;
            transform: translateY(20px);
            opacity: 0;
            transition: 0.3s;
        }

        .result-box.active {
            display: block;
            transform: translateY(0);
            opacity: 1;
        }

        .res-card {
            padding: 24px;
            border-radius: 18px;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
        }

        .result-success .res-card {
            background: rgba(52, 211, 153, 0.08);
            border: 1px solid rgba(52, 211, 153, 0.2);
        }

        .result-error .res-card {
            background: rgba(248, 113, 113, 0.08);
            border: 1px solid rgba(248, 113, 113, 0.2);
        }

        .res-icon {
            width: 56px;
            height: 56px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 16px;
        }

        .result-success .res-icon {
            background: var(--green);
            color: #000;
        }

        .result-error .res-icon {
            background: var(--red);
            color: #fff;
        }

        .res-title {
            font-family: 'Syne', sans-serif;
            font-size: 1.25rem;
            font-weight: 800;
            margin-bottom: 6px;
        }

        .res-msg {
            font-size: 0.9rem;
            opacity: 0.8;
            margin-bottom: 20px;
            line-height: 1.5;
        }

        .ticket-info {
            width: 100%;
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 20px;
            display: grid;
            gap: 12px;
            text-align: left;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            font-size: 0.82rem;
        }

        .info-label {
            color: var(--muted);
        }

        .info-value {
            font-weight: 700;
            color: var(--header-text);
        }

        .btn-reset {
            margin-top: 24px;
            width: 100%;
            padding: 16px;
            border-radius: 14px;
            border: none;
            background: var(--header-text);
            color: var(--bg);
            font-weight: 800;
            font-size: 0.9rem;
            cursor: pointer;
            transition: 0.2s;
        }

        .btn-reset:hover {
            background: var(--accent);
            transform: translateY(-2px);
        }

        .manual-input-box {
            margin-top: 24px;
            display: flex;
            gap: 10px;
            width: 100%;
        }

        .m-input {
            flex: 1;
            background: var(--card);
            border: 1px solid var(--border);
            padding: 14px 20px;
            border-radius: 14px;
            color: var(--text);
            outline: none;
            transition: 0.3s;
            font-size: 0.9rem;
        }

        .m-input:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 4px var(--accent-glow);
        }

        .m-btn-submit {
            padding: 0 24px;
            border-radius: 14px;
            border: 1px solid var(--border);
            background: var(--card);
            color: var(--muted);
            font-weight: 700;
            cursor: pointer;
            transition: 0.3s;
            font-size: 0.85rem;
        }

        .m-btn-submit.active {
            background: var(--accent);
            color: #000;
            border-color: var(--accent);
        }

        .scan-line {
            position: absolute;
            width: 100%;
            height: 2px;
            background: var(--accent);
            z-index: 10;
            boxShadow: 0 0 15px var(--accent);
            animation: scan 3s infinite linear;
            opacity: 0.5;
        }

        @keyframes scan {
            0% {
                top: 0%;
            }

            50% {
                top: 100%;
            }

            100% {
                top: 0%;
            }
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        #reader {
            width: 100%;
            min-height: 400px;
            background: #000;
            border-radius: 40px !important;
            overflow: hidden !important;
            margin-bottom: 24px;
            position: relative;
            border: none !important;
        }

        #reader video {
            width: 100% !important;
            height: 100% !important;
            object-fit: contain;
            border-radius: 40px !important;
            transform: scaleX(-1) !important;
            -webkit-transform: scaleX(-1) !important;
        }

        #reader__scan_region {
            border-radius: 40px !important;
            overflow: hidden !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
        }

        #reader__scan_region video {
            border-radius: 40px !important;
        }

        /* Hide library UI elements */
        #reader__dashboard,
        #reader__status_span,
        #reader img {
            display: none !important;
        }

        #reader button {
            display: none !important;
        }

        #reader canvas {
            display: none;
        }
    </style>
</head>

<body>

    <aside class="sidebar">
        <div class="sidebar-logo">Tix<span>Now</span></div>
        <div class="sidebar-label">Menu</div>
        <a href="admin_dashboard" class="sidebar-item">
            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
            </svg>
            Dashboard
        </a>
        <a href="admin_profile" class="sidebar-item">
            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
            </svg>
            Profil Saya
        </a>
        <a href="admin_scan" class="sidebar-item active">
            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" />
            </svg>
            Scan Tiket
        </a>

        <a href="admin_vouchers"
            class="sidebar-item<?php echo basename($_SERVER['PHP_SELF']) == 'admin_vouchers.php' ? ' active' : ''; ?>">
            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z" />
            </svg>
            Voucher
        </a>
        <a href="admin_history" class="sidebar-item">
            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            Riwayat User
        </a>
        <a href="admin_event" class="sidebar-item">
            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3" />
            </svg>
            Event
        </a>
        <a href="admin_venue" class="sidebar-item">
            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
            </svg>
            Venue
        </a>
        <div class="sidebar-bottom">
            <div class="theme-toggle-container">
                <span class="theme-label">Tampilan</span>
                <div class="theme-toggle" id="themeToggleAdmin" title="Pindah Tema" onclick="toggleAdminTheme()"
                    style="pointer-events: auto !important;">
                    <svg id="adminMoonIcon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path>
                    </svg>
                    <svg id="adminSunIcon" style="display:none;" width="16" height="16" viewBox="0 0 24 24" fill="none"
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
            <h1 class="page-title">Scan E-Tiket</h1>
            <p class="page-sub">Verifikasi tiket penonton secara real-time untuk akses masuk.</p>
        </div>

        <div class="scanner-layout">

            <div class="scanner-container">
                <div id="fileScanner" style="text-align:center; padding: 20px 0;">
                    <div style="border: 2px dashed var(--border); border-radius: 20px; padding: 50px 20px; background: rgba(255,255,255,0.015); transition: 0.3s;"
                        id="dropzone">
                        <div
                            style="width: 64px; height: 64px; background: var(--accent-glow); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; color: var(--accent);">
                            <svg width="32" height="32" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2-2v12a2 2 0 002 2z" />
                            </svg>
                        </div>
                        <div style="margin-bottom: 8px; color: var(--text); font-weight: 700;">Upload Foto QR Tiket
                        </div>
                        <div style="margin-bottom: 25px; color: var(--muted); font-size: 0.8rem;">Gunakan tangkapan
                            layar E-Tiket</div>
                        <input type="file" id="qr-input-file" accept="image/*" style="display:none;">
                        <label for="qr-input-file" class="btn-reset"
                            style="display:inline-block; width: auto; margin-top: 0; padding: 12px 24px;">Pilih
                            Gambar</label>
                    </div>
                </div>

                <div id="resultContainer" class="result-box">
                    <div id="resultContent">
                        <!-- Dynamic Content -->
                    </div>
                    <button class="btn-reset" onclick="resetScanner()">Scan Tiket Lain</button>
                </div>
            </div>

            <div class="manual-input-box">
                <input type="text" id="manualCode" class="m-input" placeholder="Masukkan kode tiket manual...">
                <button id="manualSubmitBtn" class="m-btn-submit" onclick="validateManual()" disabled>Submit</button>
            </div>
            
            <!-- Hidden elements for QR library -->
            <div id="__qr_temp__" style="display:none;"></div>
            <div id="__qr_dummy__" style="display:none;"></div>
        </div>
    </main>

    <script>
        let scannerInstance = null;
        const resultContainer = document.getElementById('resultContainer');
        const resultContent = document.getElementById('resultContent');
        const fileInput = document.getElementById('qr-input-file');
        const manualInput = document.getElementById('manualCode');
        const manualSubmitBtn = document.getElementById('manualSubmitBtn');

        // ── SYNTHETIC AUDIO ──
        const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
        const beepSuccess = {
            play() {
                const osc = audioCtx.createOscillator();
                const gain = audioCtx.createGain();
                osc.connect(gain); gain.connect(audioCtx.destination);
                osc.type = 'sine';
                osc.frequency.setValueAtTime(1050, audioCtx.currentTime);
                gain.gain.setValueAtTime(0, audioCtx.currentTime);
                gain.gain.linearRampToValueAtTime(0.3, audioCtx.currentTime + 0.01);
                gain.gain.exponentialRampToValueAtTime(0.01, audioCtx.currentTime + 0.15);
                osc.start(); osc.stop(audioCtx.currentTime + 0.16);
            }
        };
        const beepError = {
            play() {
                [320, 200].forEach((freq, i) => {
                    const osc = audioCtx.createOscillator();
                    const gain = audioCtx.createGain();
                    osc.connect(gain); gain.connect(audioCtx.destination);
                    osc.type = 'sawtooth';
                    osc.frequency.setValueAtTime(freq, audioCtx.currentTime + i * 0.15);
                    gain.gain.setValueAtTime(0, audioCtx.currentTime + i * 0.15);
                    gain.gain.linearRampToValueAtTime(0.35, audioCtx.currentTime + i * 0.15 + 0.01);
                    gain.gain.linearRampToValueAtTime(0, audioCtx.currentTime + i * 0.15 + 0.2);
                    osc.start(audioCtx.currentTime + i * 0.15);
                    osc.stop(audioCtx.currentTime + i * 0.15 + 0.22);
                });
            }
        };

        window.onload = () => {
            try {
                scannerInstance = new Html5Qrcode("__qr_temp__");
            } catch (e) {
                console.error("Scanner init error", e);
            }
            updateAdminIcons(document.documentElement.getAttribute('data-theme'));
        };

        fileInput.addEventListener('change', async e => {
            if (e.target.files.length == 0) return;
            
            resultContent.innerHTML = `
                <div style="text-align:center; padding:40px;">
                    <div class="spinner" style="width:32px; height:32px; border:3px solid var(--accent); border-top-color:transparent; border-radius:50%; margin:0 auto 16px; animation: spin 0.8s linear infinite;"></div>
                    <div style="color:var(--text); font-weight:600;">Menganalisa Gambar...</div>
                </div>
            `;
            document.getElementById('fileScanner').style.display = 'none';
            resultContainer.style.display = 'block';
            resultContainer.classList.add('active');

            const imageFile = e.target.files[0];
            try {
                if (!scannerInstance) scannerInstance = new Html5Qrcode("__qr_temp__");
                const decodedText = await scannerInstance.scanFile(imageFile, true);
                processTicket(decodedText);
            } catch (err) {
                renderResult({ success: false, message: "QR tidak terdeteksi. Pastikan gambar jelas." });
                beepError.play();
            }
        });

        async function processTicket(code) {
            try {
                const response = await fetch('admin_validate_ticket', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ kode_tiket: code })
                });
                const result = await response.json();
                renderResult(result);
                if (result.success) beepSuccess.play();
                else beepError.play();
            } catch (err) {
                renderResult({ success: false, message: "Kesalahan koneksi ke server." });
                beepError.play();
            }
        }

        function renderResult(res) {
            let html = `
            <div class="res-card">
                <div class="res-icon" style="background:${res.success ? 'var(--green)' : 'var(--red)'}; color:${res.success ? '#000' : '#fff'}; width:56px; height:56px; border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 16px;">
                    ${res.success
                        ? '<svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>'
                        : '<svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>'}
                </div>
                <div class="res-title" style="font-family:'Syne',sans-serif; font-size:1.25rem; font-weight:800; margin-bottom:8px;">${res.success ? 'Check-in Berhasil' : 'Check-in Gagal'}</div>
                <div class="res-msg" style="font-size:0.9rem; color:var(--muted); margin-bottom:20px; line-height:1.5;">${res.message}</div>
            `;
            if (res.success && res.detail) {
                html += `<div class="ticket-info" style="width:100%; background:rgba(255,255,255,0.03); border:1px solid var(--border); border-radius:14px; padding:18px; text-align:left; display:grid; gap:10px;">
                    <div class="info-row" style="display:flex; justify-content:space-between; font-size:0.8rem;"><span class="info-label" style="color:var(--muted)">Penonton</span><span class="info-value" style="font-weight:700; color:var(--header-text)">${res.detail.nama_pemegang}</span></div>
                    <div class="info-row" style="display:flex; justify-content:space-between; font-size:0.8rem;"><span class="info-label" style="color:var(--muted)">Konser</span><span class="info-value" style="font-weight:700; color:var(--header-text)">${res.detail.nama_event}</span></div>
                    <div class="info-row" style="display:flex; justify-content:space-between; font-size:0.8rem;"><span class="info-label" style="color:var(--muted)">Kategori</span><span class="info-value" style="font-weight:700; color:var(--accent)">${res.detail.kategori}</span></div>
                    <div class="info-row" style="display:flex; justify-content:space-between; font-size:0.8rem;"><span class="info-label" style="color:var(--muted)">Waktu</span><span class="info-value" style="font-weight:700; color:var(--header-text)">${res.detail.waktu}</span></div>
                </div>`;
            }
            html += '</div>';

            document.getElementById('fileScanner').style.display = 'none';
            resultContent.innerHTML = html;
            resultContainer.style.display = 'block';
            resultContainer.classList.add('active');
        }

        function resetScanner() {
            resultContainer.classList.remove('active');
            resultContainer.style.display = 'none';
            document.getElementById('fileScanner').style.display = 'block';
            fileInput.value = '';
            manualInput.value = '';
            manualSubmitBtn.disabled = true;
            manualSubmitBtn.classList.remove('active');
            manualSubmitBtn.style.opacity = '0.4';
            manualSubmitBtn.style.cursor = 'not-allowed';
        }

        function validateManual() {
            const code = manualInput.value.trim();
            if (code) processTicket(code);
        }

        manualInput.addEventListener('input', () => {
            const ok = manualInput.value.trim().length > 0;
            manualSubmitBtn.disabled = !ok;
            manualSubmitBtn.classList.toggle('active', ok);
            manualSubmitBtn.style.opacity = ok ? '1' : '0.4';
            manualSubmitBtn.style.cursor = ok ? 'pointer' : 'not-allowed';
        });
        manualInput.addEventListener('keydown', (e) => { if (e.key === 'Enter' && !manualSubmitBtn.disabled) validateManual(); });

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
    </script>
</body>
</html>