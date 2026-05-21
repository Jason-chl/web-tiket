<?php
session_start();
require_once 'koneksi.php';
if (!isset($_SESSION['id_user'])) { header("Location: login"); exit; }
$id_user = $_SESSION['id_user'];
try {
    $conn->exec("CREATE TABLE IF NOT EXISTS settings (setting_key VARCHAR(50) PRIMARY KEY, setting_value TEXT)");
    $conn->exec("INSERT IGNORE INTO settings (setting_key, setting_value) VALUES ('vip_price', '150000')");
} catch(Exception $e) {}
$stmt = $conn->prepare("SELECT is_vip, nama FROM users WHERE id_user = ?");
$stmt->execute([$id_user]);
$user  = $stmt->fetch();
$isVIP = $user['is_vip'] ?? 0;
if ($isVIP) { header("Location: user_dashboard"); exit; }
$price = $conn->query("SELECT setting_value FROM settings WHERE setting_key = 'vip_price'")->fetchColumn() ?: 150000;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VIP Fast Track — TixNow</title>
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800;900&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <script>(function(){ const t=localStorage.getItem('theme')||'dark'; document.documentElement.setAttribute('data-theme',t); })();</script>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root {
            --bg: #03050a; --surface: #0f1520; --card: #131c2e;
            --border: rgba(255,255,255,0.07); --text: #e8e8f0; --muted: #5e6a82;
            --gold: #ffd700; --gold-2: #ffae00;
            --gold-dim: rgba(255,215,0,0.08); --gold-glow: rgba(255,215,0,0.2);
        }
        [data-theme="light"] {
            --bg: #f1f5f9; --surface: #ffffff; --card: #f8fafc;
            --border: rgba(0,0,0,0.08); --text: #0f172a; --muted: #64748b;
            --gold-dim: rgba(255,174,0,0.08); --gold-glow: rgba(255,174,0,0.2);
        }
        body { font-family: 'Inter', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; }

        /* === NAV === */
        nav { position: fixed; top: 0; left: 0; right: 0; z-index: 100; padding: 14px 32px; display: flex; align-items: center; justify-content: space-between; background: rgba(3,5,10,0.85); backdrop-filter: blur(20px); border-bottom: 1px solid var(--border); }
        [data-theme="light"] nav { background: rgba(241,245,249,0.9); }
        .nav-logo { font-family: 'Syne', sans-serif; font-size: 1.2rem; font-weight: 800; color: var(--text); }
        .nav-logo span { color: var(--gold); }
        .nav-back { display: inline-flex; align-items: center; gap: 7px; padding: 7px 16px; border-radius: 8px; border: 1px solid var(--border); color: var(--muted); font-size: 0.8rem; font-weight: 600; text-decoration: none; transition: 0.2s; }
        .nav-back:hover { border-color: rgba(255,255,255,0.2); color: var(--text); }

        /* === HERO === */
        .hero {
            padding: 140px 24px 80px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        .hero::before {
            content: '';
            position: absolute; top: 0; left: 50%; transform: translateX(-50%);
            width: 800px; height: 600px;
            background: radial-gradient(ellipse, rgba(255,215,0,0.07) 0%, transparent 65%);
            pointer-events: none;
        }
        .hero-badge {
            display: inline-flex; align-items: center; gap: 8px;
            background: var(--gold-dim); border: 1px solid rgba(255,215,0,0.25);
            padding: 6px 16px; border-radius: 999px;
            font-size: 0.75rem; font-weight: 700; color: var(--gold);
            text-transform: uppercase; letter-spacing: 1.5px;
            margin-bottom: 28px;
        }
        .hero-crown {
            display: flex; align-items: center; justify-content: center;
            width: 88px; height: 88px; border-radius: 28px;
            background: linear-gradient(135deg, var(--gold), var(--gold-2));
            box-shadow: 0 0 60px var(--gold-glow), 0 20px 40px rgba(255,174,0,0.2);
            margin: 0 auto 28px;
            animation: float 3s ease-in-out infinite;
        }
        @keyframes float { 0%,100% { transform: translateY(0); } 50% { transform: translateY(-8px); } }
        .hero h1 {
            font-family: 'Syne', sans-serif; font-size: clamp(2.4rem, 5vw, 3.8rem);
            font-weight: 900; line-height: 1.05; max-width: 700px; margin: 0 auto 16px;
        }
        .hero h1 .gradient {
            background: linear-gradient(135deg, #fff 0%, var(--gold) 50%, var(--gold-2) 100%);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;
        }
        [data-theme="light"] .hero h1 .gradient {
            background: linear-gradient(135deg, #1e293b 0%, #b45309 100%);
            -webkit-background-clip: text; background-clip: text;
        }
        .hero-sub { color: var(--muted); font-size: 1.05rem; max-width: 480px; margin: 0 auto 40px; line-height: 1.65; }

        .price-pill {
            display: inline-flex; align-items: baseline; gap: 4px;
            background: linear-gradient(135deg, rgba(255,215,0,0.12), rgba(255,174,0,0.06));
            border: 1px solid rgba(255,215,0,0.3);
            padding: 10px 24px; border-radius: 999px; margin-bottom: 32px;
        }
        .price-pill .label { font-size: 0.8rem; color: var(--muted); margin-right: 6px; }
        .price-pill .amount { font-family: 'Syne', sans-serif; font-size: 1.6rem; font-weight: 900; color: var(--gold); }
        .price-pill .period { font-size: 0.8rem; color: var(--muted); margin-left: 4px; }

        .hero-cta {
            display: inline-flex; align-items: center; gap: 10px;
            padding: 16px 36px; border-radius: 14px; border: none;
            background: linear-gradient(135deg, var(--gold), var(--gold-2));
            color: #000; font-family: 'Syne', sans-serif; font-weight: 800; font-size: 1rem;
            cursor: pointer; text-decoration: none;
            box-shadow: 0 10px 30px rgba(255,174,0,0.25);
            transition: all 0.3s;
        }
        .hero-cta:hover { transform: translateY(-3px); box-shadow: 0 18px 40px rgba(255,174,0,0.4); }

        /* === BENEFITS === */
        .benefits-section { max-width: 1020px; margin: 0 auto; padding: 0 24px 100px; }
        .section-label { text-align: center; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 2px; color: var(--gold); margin-bottom: 16px; }
        .section-title { text-align: center; font-family: 'Syne', sans-serif; font-size: 1.8rem; font-weight: 800; margin-bottom: 48px; }

        .benefits-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px; margin-bottom: 60px; }
        @media (max-width: 640px) { .benefits-grid { grid-template-columns: 1fr; } }

        .benefit-card {
            background: var(--surface); border: 1px solid var(--border);
            border-radius: 20px; padding: 28px;
            position: relative; overflow: hidden;
            transition: border-color 0.3s, transform 0.3s;
        }
        .benefit-card:hover { border-color: rgba(255,215,0,0.25); transform: translateY(-3px); }
        .benefit-card::before {
            content: ''; position: absolute; top: 0; left: 0; right: 0; height: 1px;
            background: linear-gradient(90deg, transparent, rgba(255,215,0,0.3), transparent);
            opacity: 0; transition: opacity 0.3s;
        }
        .benefit-card:hover::before { opacity: 1; }
        .benefit-icon {
            width: 48px; height: 48px; border-radius: 14px;
            background: var(--gold-dim); color: var(--gold);
            display: flex; align-items: center; justify-content: center;
            margin-bottom: 18px;
        }
        .benefit-card h3 { font-family: 'Syne', sans-serif; font-size: 1rem; font-weight: 800; margin-bottom: 8px; }
        .benefit-card p { font-size: 0.82rem; color: var(--muted); line-height: 1.6; }

        /* === COMPARISON === */
        .compare-table { border: 1px solid var(--border); border-radius: 20px; overflow: hidden; margin-bottom: 60px; }
        .compare-row { display: grid; grid-template-columns: 1fr 140px 140px; border-bottom: 1px solid var(--border); }
        .compare-row:last-child { border-bottom: none; }
        .compare-row.header { background: var(--surface); }
        .compare-cell { padding: 14px 20px; font-size: 0.85rem; display: flex; align-items: center; }
        .compare-cell.center { justify-content: center; text-align: center; }
        .compare-cell.vip-col { background: rgba(255,215,0,0.04); font-weight: 600; color: var(--gold); }
        .compare-cell.header-cell { font-weight: 700; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 1px; }
        .compare-cell.header-cell.vip-col { color: var(--gold); }
        .check-yes { color: #34d399; }
        .check-no { color: var(--muted); opacity: 0.5; }

        /* === BOTTOM CTA === */
        .bottom-cta { background: var(--surface); border: 1px solid rgba(255,215,0,0.15); border-radius: 24px; padding: 48px; text-align: center; }
        .bottom-cta h2 { font-family: 'Syne', sans-serif; font-size: 1.8rem; font-weight: 800; margin-bottom: 12px; }
        .bottom-cta p { color: var(--muted); margin-bottom: 32px; }
    </style>
</head>
<body>

<!-- NAV -->
<nav>
    <div class="nav-logo">Tix<span>Now</span></div>
    <a href="user_dashboard" class="nav-back">
        <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"/></svg>
        Kembali ke Dashboard
    </a>
</nav>

<!-- HERO -->
<div class="hero">
    <div class="hero-badge">
        <svg width="12" height="12" viewBox="0 0 20 20" fill="currentColor"><path d="M10 1l2.39 5.26L18 7.27l-4 3.89.94 5.5L10 14l-4.94 2.66.94-5.5L2 7.27l5.61-.01L10 1z"/></svg>
        Eksklusif · Permanen · Tanpa Antrean
    </div>
    <div class="hero-crown">
        <svg width="44" height="44" viewBox="0 0 20 20" fill="#000"><path d="M10 1l2.39 5.26L18 7.27l-4 3.89.94 5.5L10 14l-4.94 2.66.94-5.5L2 7.27l5.61-.01L10 1z"/></svg>
    </div>
    <h1>Jadilah yang <span class="gradient">Pertama Masuk</span>,<br>Bukan yang Terakhir</h1>
    <p class="hero-sub">VIP Fast Track membuat kamu langsung melewati semua antrean dan masuk ke halaman tiket. Satu kali upgrade, berlaku selamanya.</p>
    <div class="price-pill">
        <span class="label">Mulai dari</span>
        <span class="amount">Rp <?php echo number_format($price, 0, ',', '.'); ?></span>
        <span class="period">/ permanen</span>
    </div>
    <br>
    <a href="vip_checkout" class="hero-cta">
        <svg width="18" height="18" viewBox="0 0 20 20" fill="currentColor"><path d="M10 1l2.39 5.26L18 7.27l-4 3.89.94 5.5L10 14l-4.94 2.66.94-5.5L2 7.27l5.61-.01L10 1z"/></svg>
        Beli VIP Sekarang
    </a>
</div>

<!-- BENEFITS -->
<div class="benefits-section">
    <div class="section-label">Keuntungan VIP</div>
    <div class="section-title">Semua yang Kamu Dapatkan</div>

    <div class="benefits-grid">
        <div class="benefit-card">
            <div class="benefit-icon">
                <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
            </div>
            <h3>Skip Waiting Room Selamanya</h3>
            <p>Begitu kamu klik tiket, langsung masuk ke halaman pemilihan — melewati semua antrean dan countdown yang menegangkan.</p>
        </div>
        <div class="benefit-card">
            <div class="benefit-icon">
                <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
            </div>
            <h3>Badge VIP Eksklusif</h3>
            <p>Namamu tampil dengan badge emas <strong style="color:var(--gold)">VIP</strong> di seluruh platform. Semua orang tahu kamu berbeda.</p>
        </div>
        <div class="benefit-card">
            <div class="benefit-icon">
                <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/></svg>
            </div>
            <h3>Prioritas War Tiket</h3>
            <p>Di momen paling ramai dan kompetitif, kamu punya keunggulan nyata untuk mendapatkan tiket sebelum kehabisan.</p>
        </div>
        <div class="benefit-card">
            <div class="benefit-icon">
                <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/></svg>
            </div>
            <h3>Berlaku Permanen</h3>
            <p>Cukup satu kali upgrade. Status VIP tidak akan pernah expired selama akun kamu aktif di TixNow.</p>
        </div>
    </div>

    <!-- COMPARISON TABLE -->
    <div class="section-label">Perbandingan</div>
    <div class="section-title">Reguler vs VIP</div>

    <div class="compare-table">
        <div class="compare-row header">
            <div class="compare-cell header-cell">Fitur</div>
            <div class="compare-cell center header-cell">Reguler</div>
            <div class="compare-cell center header-cell vip-col">⭐ VIP</div>
        </div>
        <div class="compare-row">
            <div class="compare-cell">Beli Tiket</div>
            <div class="compare-cell center check-yes"><svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg></div>
            <div class="compare-cell center vip-col check-yes"><svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg></div>
        </div>
        <div class="compare-row">
            <div class="compare-cell">Waiting Room / Antrean</div>
            <div class="compare-cell center" style="color:var(--muted);font-size:0.8rem;">Wajib Antre</div>
            <div class="compare-cell center vip-col" style="font-size:0.8rem;">⚡ Skip Otomatis</div>
        </div>
        <div class="compare-row">
            <div class="compare-cell">Badge di Profil</div>
            <div class="compare-cell center check-no"><svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></div>
            <div class="compare-cell center vip-col check-yes"><svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg></div>
        </div>
        <div class="compare-row">
            <div class="compare-cell">Prioritas War Tiket</div>
            <div class="compare-cell center check-no"><svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></div>
            <div class="compare-cell center vip-col check-yes"><svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg></div>
        </div>
        <div class="compare-row">
            <div class="compare-cell">Masa Berlaku</div>
            <div class="compare-cell center" style="color:var(--muted);font-size:0.8rem;">—</div>
            <div class="compare-cell center vip-col" style="font-size:0.8rem;">Permanen</div>
        </div>
    </div>

    <!-- BOTTOM CTA -->
    <div class="bottom-cta">
        <h2>Siap Upgrade ke VIP?</h2>
        <p>Hanya Rp <?php echo number_format($price, 0, ',', '.'); ?> — bayar sekali, nikmati selamanya.</p>
        <a href="vip_checkout" class="hero-cta">
            <svg width="18" height="18" viewBox="0 0 20 20" fill="currentColor"><path d="M10 1l2.39 5.26L18 7.27l-4 3.89.94 5.5L10 14l-4.94 2.66.94-5.5L2 7.27l5.61-.01L10 1z"/></svg>
            Beli VIP Fast Track
        </a>
    </div>
</div>

</body>
</html>
