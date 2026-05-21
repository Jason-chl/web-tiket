<?php
session_start();
require_once 'koneksi.php';
require_once 'cleanup_orders.php';

// Cek apakah user sudah login
$isLoggedIn = isset($_SESSION['id_user']);
$userRole = $_SESSION['role'] ?? '';

// Ambil 3 event terbaru yang dipublish
$stmt = $conn->query("SELECT * FROM event WHERE status = 'published' ORDER BY id_event DESC LIMIT 3");
$featured_events = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TixNow — Experience Real Music Vibrations</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;500;600;700;800&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
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
            --border: rgba(0,0,0,0.06);
            --border-alpha: rgba(0,0,0,0.08);
            --surface-alpha: rgba(0,0,0,0.03);
            --accent: #6d28d9;
            --accent-glow: rgba(109, 40, 217, 0.12);
            --red: #ef4444;
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
            line-height: 1.6;
            overflow-x: hidden;
        }

        /* DYNAMIC BACKGROUND BLOBS */
        .blobs {
            position: fixed; inset: 0;
            z-index: -1; pointer-events: none;
            filter: blur(80px);
        }
        .blob {
            position: absolute; width: 400px; height: 400px; border-radius: 50%; opacity: 0.12;
            animation: move 20s infinite alternate cubic-bezier(0.45, 0, 0.55, 1);
            transition: background 0.5s;
        }
        [data-theme="light"] .blob { opacity: 0.08; }
        .blob-1 { background: var(--accent); top: -10%; left: -5%; }
        .blob-2 { background: var(--accent); bottom: -10%; right: -5%; animation-delay: -5s; filter: hue-rotate(30deg); }
        .blob-3 { background: var(--accent); top: 40%; right: 10%; animation-delay: -10s; width: 300px; height: 300px; filter: hue-rotate(-30deg); }

        @keyframes move {
            from { transform: translate(0,0) rotate(0deg) scale(1); }
            to { transform: translate(100px, 100px) rotate(45deg) scale(1.1); }
        }

        /* NAVIGATION */
        nav {
            position: fixed; top: 20px; left: 50%; transform: translateX(-50%);
            width: 90%; max-width: 1200px; height: 64px;
            display: flex; align-items: center; justify-content: space-between;
            padding: 0 24px;
            background: var(--nav-bg);
            backdrop-filter: blur(20px);
            border: 1px solid var(--border);
            border-radius: 99px;
            z-index: 1000;
            transition: 0.4s;
        }
        nav.scrolled { top: 0; width: 100%; border-radius: 0; background: var(--nav-bg); border-left: none; border-right: none; }

        .nav-logo { font-family: 'Syne', sans-serif; font-weight: 800; font-size: 1.3rem; color: var(--header-text); text-decoration: none; letter-spacing: -1px; }
        .nav-logo span { color: var(--accent); }

        .nav-links { display: flex; gap: 28px; align-items: center; }
        .nav-links a { text-decoration: none; color: var(--muted); font-size: 0.85rem; font-weight: 500; transition: 0.3s; }
        .nav-links a:not(.btn-auth-nav):hover { color: var(--header-text); }

        .btn-auth-nav { padding: 8px 20px; border-radius: 99px; font-size: 0.8rem; font-weight: 600; text-decoration: none; transition: 0.3s; }
        .btn-login { color: #fff; }
        [data-theme="light"] .btn-login { color: var(--header-text); }
        .btn-register { background: var(--header-text); color: var(--bg); }
        .btn-register:hover { background: var(--accent) !important; color: #fff !important; transform: scale(1.05); }

        /* THEME TOGGLE */
        .theme-toggle {
            width: 40px; height: 40px; border-radius: 50%; background: rgba(255,255,255,0.05);
            border: 1px solid var(--border); display: flex; align-items: center; justify-content: center;
            cursor: pointer; color: var(--text); transition: 0.3s;
        }
        .theme-toggle:hover { background: rgba(255,255,255,0.1); border-color: var(--accent); }
        [data-theme="light"] .theme-toggle { background: #f1f5f9; }

        /* HERO */
        .hero {
            min-height: 100vh; display: flex; flex-direction: column; justify-content: center; align-items: center;
            text-align: center; padding: 120px 20px 60px;
        }
        .social-proof {
            display: inline-flex; align-items: center; gap: 10px;
            padding: 8px 16px; background: rgba(255,255,255,0.03); border: 1px solid var(--border);
            border-radius: 99px; margin-bottom: 32px; font-size: 0.8rem; color: var(--muted);
        }
        .avatars { display: flex; margin-right: 4px; }
        .avatar { width: 24px; height: 24px; border-radius: 50%; border: 2px solid var(--bg); margin-left: -8px; object-fit: cover; }
        .avatar:first-child { margin-left: 0; }

        .hero-title {
            font-family: 'Syne', sans-serif; font-size: clamp(2.8rem, 10vw, 5.5rem);
            font-weight: 800; line-height: 1; color: var(--header-text); max-width: 1000px;
            margin-bottom: 32px; letter-spacing: -3px;
            margin-left: auto; margin-right: auto;
        }
        .hero-desc { font-size: 1.15rem; color: var(--muted); max-width: 650px; margin-bottom: 48px; }
        .hero-actions { display: flex; gap: 16px; }
        .btn-hero { padding: 16px 40px; border-radius: 14px; font-weight: 700; font-size: 1rem; text-decoration: none; transition: 0.3s; }
        .btn-primary { background: var(--accent); color: #fff; box-shadow: 0 10px 40px var(--accent-glow); }
        .btn-primary:hover { transform: translateY(-4px); filter: brightness(1.1); }
        .btn-secondary { background: rgba(255,255,255,0.05); color: white; border: 1px solid var(--border); }
        .btn-secondary:hover { background: rgba(255,255,255,0.1); }
        
        [data-theme="light"] .btn-secondary { background: rgba(0,0,0,0.04); color: #000; border-color: rgba(0,0,0,0.1); }
        [data-theme="light"] .btn-secondary:hover { background: rgba(0,0,0,0.08); }

        /* STATS */
        .stats-row { 
            display: flex; justify-content: center; gap: 60px; margin-top: 80px; 
            padding: 40px; border-top: 1px solid var(--border); width: 80%;
        }
        .stat-item { text-align: center; }
        .stat-val { font-family: 'Syne', sans-serif; font-size: 2rem; font-weight: 800; color: var(--header-text); display: block; }
        .stat-lab { font-size: 0.75rem; color: var(--muted); text-transform: uppercase; letter-spacing: 2px; }

        /* SECTIONS */
        section { padding: 120px 8%; position: relative; }
        .section-header { margin-bottom: 60px; max-width: 800px; }
        .pre-title { color: var(--accent); font-weight: 700; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 4px; margin-bottom: 16px; display: block; }
        .section-title { font-family: 'Syne', sans-serif; font-size: 3rem; font-weight: 800; color: var(--header-text); letter-spacing: -1px; line-height: 1.1; }

        /* BENTO GRID */
        .bento-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 24px; }
        .bento-card { 
            background: var(--surface); border: 1px solid var(--border); border-radius: 32px; 
            padding: 48px; position: relative; overflow: hidden; transition: 0.4s;
        }
        .bento-card:hover { border-color: var(--accent); }
        .bento-card.wide { grid-column: span 2; }
        .bento-card.tall { grid-row: span 2; }
        .bento-icon { width: 56px; height: 56px; background: var(--accent-dim); border-radius: 16px; display: flex; align-items: center; justify-content: center; color: var(--accent); margin-bottom: 32px; }
        .bento-card h3 { font-family: 'Syne', sans-serif; font-size: 1.5rem; color: var(--header-text); margin-bottom: 16px; }
        .bento-card p { color: var(--muted); font-size: 0.95rem; }

        /* EVENT CARDS */
        .events-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 32px; }
        .event-card {
            background: var(--card); border: 1px solid var(--border); border-radius: 24px;
            overflow: hidden; transition: 0.4s cubic-bezier(0.23, 1, 0.32, 1);
        }
        .event-card:hover { transform: translateY(-12px); border-color: rgba(255,255,255,0.2); box-shadow: 0 20px 40px rgba(0,0,0,0.4); }
        .event-img { width: 100%; height: 280px; object-fit: cover; transition: 0.6s; }
        .event-card:hover .event-img { scale: 1.1; }
        .event-content { padding: 24px; }
        .event-meta { font-size: 0.7rem; color: var(--accent); font-weight: 700; text-transform: uppercase; margin-bottom: 12px; display: block; }
        .event-name { font-family: 'Syne', sans-serif; font-size: 1.35rem; font-weight: 800; color: var(--header-text); margin-bottom: 16px; line-height: 1.2; }
        .btn-view {
            width: 100%; padding: 14px; background: rgba(255,255,255,0.05); border: 1px solid var(--border); 
            border-radius: 14px; color: white; text-align: center; text-decoration: none; font-weight: 600; font-size: 0.9rem; transition: 0.3s; display: block;
        }
        .btn-view:hover { background: white; color: black; }

        /* TESTIMONIALS */
        .testi-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 24px; }
        .testi-card { background: rgba(255,255,255,0.02); border: 1px solid var(--border); padding: 32px; border-radius: 24px; }
        .testi-quote { font-size: 1rem; color: var(--text); margin-bottom: 24px; font-style: italic; }
        .testi-user { display: flex; align-items: center; gap: 12px; }
        .testi-avatar { width: 44px; height: 44px; border-radius: 50%; object-fit: cover; }
        .testi-info span { display: block; }
        .testi-name { font-weight: 700; color: #fff; font-size: 0.9rem; }
        [data-theme="light"] .testi-name { color: var(--header-text); }
        .testi-role { font-size: 0.75rem; color: var(--muted); }

        /* CTA */
        .cta-box {
            background: radial-gradient(circle at top right, #312e81, #03050a);
            border: 1px solid rgba(255,255,255,0.1); border-radius: 48px;
            padding: 100px 40px; text-align: center; margin-bottom: 100px;
        }
        .cta-box .hero-title, .cta-box .hero-desc { color: #fff !important; }

        /* FOOTER */
        footer { padding: 80px 8% 40px; border-top: 1px solid var(--border); }
        .footer-grid { display: grid; grid-template-columns: 2fr 1fr 1fr 1.5fr; gap: 60px; margin-bottom: 60px; }
        .footer-desc { color: var(--muted); font-size: 0.9rem; margin-top: 20px; max-width: 300px; }
        .footer-head { font-family: 'Syne', sans-serif; font-weight: 700; color: #fff; margin-bottom: 24px; display: block; }
        [data-theme="light"] .footer-head { color: var(--header-text); }
        .footer-links { list-style: none; }
        .footer-links li { margin-bottom: 12px; }
        .footer-links a { color: var(--muted); text-decoration: none; font-size: 0.9rem; transition: 0.3s; }
        .footer-links a:hover { color: white; padding-left: 4px; }

        .footer-links a:hover { color: white; padding-left: 4px; }

        /* SCROLL REVEAL ANIMATION */
        .reveal {
            opacity: 0;
            transform: translateY(30px);
            transition: all 0.9s cubic-bezier(0.16, 1, 0.3, 1);
            will-change: transform, opacity;
        }
        .reveal.active {
            opacity: 1;
            transform: translateY(0);
        }

        /* STAGGERED DELAYS */
        .delay-1 { transition-delay: 0.1s; }
        .delay-2 { transition-delay: 0.2s; }
        .delay-3 { transition-delay: 0.3s; }

        @media (max-width: 1100px) {
            .bento-grid, .events-grid, .testi-grid, .footer-grid { grid-template-columns: 1fr; }
            .bento-card.wide { grid-column: span 1; }
            .hero-title { font-size: 3.5rem; }
        }
    </style>
    <script>
        // IMMEDATE THEME LOAD
        (function() {
            const savedTheme = localStorage.getItem('theme') || 'dark';
            document.documentElement.setAttribute('data-theme', savedTheme);
        })();
    </script>
</head>
<body>

    <div class="blobs">
        <div class="blob blob-1"></div>
        <div class="blob blob-2"></div>
        <div class="blob blob-3"></div>
    </div>

    <nav id="navbar">
        <div style="display: flex; align-items: center; gap: 20px;">
            <a href="index" class="nav-logo">Tix<span>Now</span></a>
            <div class="theme-toggle" id="themeToggle" title="Pindah Tema">
                <svg id="moonIcon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path></svg>
                <svg id="sunIcon" style="display:none;" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"></circle><line x1="12" y1="1" x2="12" y2="3"></line><line x1="12" y1="21" x2="12" y2="23"></line><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line><line x1="1" y1="12" x2="3" y2="12"></line><line x1="21" y1="12" x2="23" y2="12"></line><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line></svg>
            </div>
        </div>
        <div class="nav-links">
            <a href="#events">Explore</a>
            <a href="#about">Features</a>
            <a href="#testi">Testimonials</a>
            <?php if ($isLoggedIn): ?>
                <a href="<?php echo ($userRole === 'admin') ? 'admin_dashboard' : 'user_dashboard'; ?>" class="btn-auth-nav btn-register">Dashboard</a>
            <?php else: ?>
                <a href="login" class="btn-auth-nav btn-login">Sign In</a>
                <a href="register" class="btn-auth-nav btn-register">Get Started</a>
            <?php endif; ?>
        </div>
    </nav>

    <main>
        <!-- HERO -->
        <section class="hero">
            <div class="social-proof">
                <div class="avatars">
                    <img src="https://i.pravatar.cc/100?u=1" class="avatar">
                    <img src="https://i.pravatar.cc/100?u=2" class="avatar">
                    <img src="https://i.pravatar.cc/100?u=3" class="avatar">
                </div>
                <span>Bergabunglah dengan 50.000+ penikmat musik</span>
            </div>
            <h1 class="hero-title">Your Gateway to<br>Perfect Music.</h1>
            <p class="hero-desc">Amankan tiket konser artis favoritmu dengan sistem tercanggih, tercepat, dan termudah di ekosistem hiburan digital Nusantara.</p>
            <div class="hero-actions">
                <a href="#events" class="btn-hero btn-primary">Lihat Konser</a>
                <a href="#about" class="btn-hero btn-secondary">Cara Kerja?</a>
            </div>
            
            <div class="stats-row">
                <div class="stat-item">
                    <span class="stat-val">120+</span>
                    <span class="stat-lab">Total Event</span>
                </div>
                <div class="stat-item">
                    <span class="stat-val">45+</span>
                    <span class="stat-lab">Venue Partner</span>
                </div>
                <div class="stat-item">
                    <span class="stat-val">99%</span>
                    <span class="stat-lab">Trust Rate</span>
                </div>
            </div>
        </section>

        <!-- FEATURES BENTO -->
        <section id="about">
            <div class="section-header reveal">
                <span class="pre-title">Revolutionary Ticketing</span>
                <h2 class="section-title">Teknologi yang Menghubungkanmu dengan Panggung.</h2>
            </div>
            <div class="bento-grid">
                <div class="bento-card wide reveal delay-1">
                    <div class="bento-icon">
                        <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                    </div>
                    <h3>Anti-War System</h3>
                    <p>Infrastruktur kami siap menangani ribuan transaksi per detik tanpa lag. Amankan tiket favoritmu dalam hitungan detik setelah penjualan dibuka.</p>
                </div>
                <div class="bento-card tall reveal delay-2">
                    <div class="bento-icon">
                        <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                    </div>
                    <h3>Cyber Security First</h3>
                    <p>Pembayaran dienskripsi dengan standar perbankan. Data pribadi dan rincian transaksi Anda sepenuhnya aman bersama sistem proteksi TixNow.</p>
                </div>
                <div class="bento-card reveal delay-1">
                    <div class="bento-icon">
                        <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/></svg>
                    </div>
                    <h3>Instant QR Ticketing</h3>
                    <p>Terima e-tiket instan setelah konfirmasi pembayaran. Langsung siap scan di lokasi tanpa ribet cetak fisik.</p>
                </div>
                <div class="bento-card reveal delay-3">
                    <div class="bento-icon">
                        <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                    </div>
                    <h3>Flexible Payment</h3>
                    <p>Pilih metode bayar favoritmu: Bank Transfer, E-Wallet, hingga Paylater untuk fleksibilitas maksimal.</p>
                </div>
            </div>
        </section>

        <!-- FEATURED EVENTS -->
        <section id="events">
            <div class="section-header reveal">
                <span class="pre-title">Available Events</span>
                <h2 class="section-title">Segera Hadir di Kotamu.</h2>
            </div>
            <div class="events-grid">
                <?php $i=1; foreach($featured_events as $event): ?>
                    <div class="event-card reveal delay-<?php echo $i; ?>">
                        <img src="<?php echo !empty($event['poster_url']) ? htmlspecialchars($event['poster_url']) : 'https://images.unsplash.com/photo-1540039155732-68b2dbceaebd?q=80&w=2674&auto=format&fit=crop'; ?>" class="event-img" alt="">
                        <div class="event-content">
                            <span class="event-meta"><?php echo date('d M Y', strtotime($event['tanggal'])); ?> • <?php echo htmlspecialchars($event['venue']); ?></span>
                            <h3 class="event-name"><?php echo htmlspecialchars($event['nama_event']); ?></h3>
                            <a href="detail_konser?id=<?php echo $event['id']; ?>" class="btn-view">Lihat Detail Event</a>
                        </div>
                    </div>
                <?php $i++; endforeach; ?>
                <?php if(empty($featured_events)): ?>
                    <div style="grid-column: span 3; text-align: center; color: var(--muted); padding: 80px; border: 1px dashed var(--border); border-radius: 20px;">
                        <p>Belum ada konser yang tersedia saat ini. Silakan kembali lagi nanti.</p>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <!-- TESTIMONIALS -->
        <section id="testi">
            <div class="section-header reveal">
                <span class="pre-title">User Stories</span>
                <h2 class="section-title">Apa Kata Mereka?</h2>
            </div>
            <div class="testi-grid">
                <div class="testi-card reveal delay-1">
                    <p class="testi-quote">"Sumpah cepat banget! Baru klik bayar, detik berikutnya tiket langsung masuk email. Rekomen buat war tiket."</p>
                    <div class="testi-user">
                        <img src="https://i.pravatar.cc/100?u=11" class="testi-avatar">
                        <div class="testi-info">
                            <span class="testi-name">Adit Pratama</span>
                            <span class="testi-role">Fans Taylor Swift</span>
                        </div>
                    </div>
                </div>
                <div class="testi-card reveal delay-2">
                    <p class="testi-quote">"Desain webnya keren, modern banget. Proses login dan regis simpel banget, UI-nya gampang dimengerti."</p>
                    <div class="testi-user">
                        <img src="https://i.pravatar.cc/100?u=12" class="testi-avatar">
                        <div class="testi-info">
                            <span class="testi-name">Sasha Amara</span>
                            <span class="testi-role">K-Pop Enthusiast</span>
                        </div>
                    </div>
                </div>
                <div class="testi-card reveal delay-3">
                    <p class="testi-quote">"Akhirnya ada platform ticketing yang nggak lag pas jam war. TixNow bener-bener ngerubah cara cari tiket."</p>
                    <div class="testi-user">
                        <img src="https://i.pravatar.cc/100?u=13" class="testi-avatar">
                        <div class="testi-info">
                            <span class="testi-name">Fajar Alamsyah</span>
                            <span class="testi-role">Rock Concert Hunter</span>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- CTA -->
        <section>
            <div class="cta-box reveal">
                <h2 class="hero-title" style="font-size: clamp(2rem, 6vw, 4rem); margin-bottom: 24px;">Jangan Sampai Ketinggalan.<br>Panggung Sudah Siap.</h2>
                <p class="hero-desc" style="margin: 0 auto 48px;">Daftar akun TixNow hari ini dan dapatkan akses eksklusif ke berbagai event musik spektakular di kotamu.</p>
                <?php if (!$isLoggedIn): ?>
                    <a href="register" class="btn-hero btn-primary" style="padding: 20px 60px;">Daftar Sekarang — Gratis</a>
                <?php else: ?>
                    <a href="user_dashboard" class="btn-hero btn-primary" style="padding: 20px 60px;">Kembali Ke Dashboard</a>
                <?php endif; ?>
            </div>
        </section>
    </main>

    <footer>
        <div class="footer-grid">
            <div>
                <a href="#" class="nav-logo">Tix<span>Now</span></a>
                <p class="footer-desc">Platform ticketing konser paling inovatif dengan fokus pada keamanan transaksi dan kenyamanan pengguna.</p>
            </div>
            <div>
                <span class="footer-head">Company</span>
                <ul class="footer-links">
                    <li><a href="#">About Us</a></li>
                    <li><a href="#">Careers</a></li>
                    <li><a href="#">Press Kit</a></li>
                </ul>
            </div>
            <div>
                <span class="footer-head">Support</span>
                <ul class="footer-links">
                    <li><a href="#">Help Center</a></li>
                    <li><a href="#">Terms of Service</a></li>
                    <li><a href="#">Privacy Policy</a></li>
                </ul>
            </div>
            <div>
                <span class="footer-head">Join Newsletter</span>
                <p class="footer-desc" style="margin-bottom: 20px;">Dapatkan info konser terbaru langsung di emailmu.</p>
                <div style="display: flex; gap: 8px;">
                    <input type="email" placeholder="email@kamu.com" style="background: rgba(255,255,255,0.05); border: 1px solid var(--border); border-radius: 12px; padding: 12px; color: white; flex: 1; outline: none;">
                    <button style="background: white; border: none; padding: 12px 20px; border-radius: 12px; font-weight: 700; cursor: pointer;">Join</button>
                </div>
            </div>
        </div>
        <div style="text-align: center; border-top: 1px solid var(--border); padding-top: 40px; color: var(--muted); font-size: 0.8rem;">
            &copy; 2026 TixNow Ticketing System. Developed by Antigravity for Music Fans.
        </div>
    </footer>

    <script>
        window.addEventListener('scroll', () => {
            const nav = document.getElementById('navbar');
            if (window.scrollY > 80) {
                nav.classList.add('scrolled');
            } else {
                nav.classList.remove('scrolled');
            }
        });

        /* SCROLL REVEAL OBSERVER */
        const revealObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('active');
                    // Stop observing once revealed if you want it only once
                    // revealObserver.unobserve(entry.target);
                }
            });
        }, { threshold: 0.15 });

        document.querySelectorAll('.reveal').forEach(el => {
            revealObserver.observe(el);
        });

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

        // Initialize icons
        updateIcons(document.documentElement.getAttribute('data-theme'));

        themeToggle.addEventListener('click', () => {
            const currentTheme = document.documentElement.getAttribute('data-theme');
            const newTheme = currentTheme === 'light' ? 'dark' : 'light';
            
            document.documentElement.setAttribute('data-theme', newTheme);
            localStorage.setItem('theme', newTheme);
            updateIcons(newTheme);
        });
    </script>
</body>
</html>
