<?php
session_start();
require_once 'koneksi.php';

if (!isset($_SESSION['id_user']) || $_SESSION['role'] !== 'admin') {
    header("Location: index");
    exit;
}

$id_user = $_SESSION['id_user'];
$stmt = $conn->prepare("SELECT * FROM users WHERE id_user = ?");
$stmt->execute([$id_user]);
$admin = $stmt->fetch();

if (!$admin) {
    session_destroy();
    header("Location: login");
    exit;
}

$error = '';
$success = '';

// Handle Update Profile
if (isset($_POST['update_profile'])) {
    $nama = trim($_POST['nama']);
    $email = trim($_POST['email']);
    $username = trim($_POST['username']);
    $no_telepon = trim($_POST['no_telepon']);

    if (empty($nama) || empty($email) || empty($username)) {
        $error = "Nama, Email, dan Username wajib diisi.";
    } else {
        try {
            $conn->prepare("UPDATE users SET nama = ?, email = ?, username = ?, no_telepon = ? WHERE id_user = ?")
                 ->execute([$nama, $email, $username, $no_telepon, $id_user]);
            $_SESSION['nama'] = $nama;
            $_SESSION['username'] = $username;
            $success = "Profil berhasil diperbarui.";
            $stmt = $conn->prepare("SELECT * FROM users WHERE id_user = ?");
            $stmt->execute([$id_user]);
            $admin = $stmt->fetch();
        } catch (Exception $e) {
            $error = "Gagal memperbarui profil: " . $e->getMessage();
        }
    }
}

// Handle Update Password
if (isset($_POST['update_password'])) {
    $current = $_POST['current_password'];
    $new     = $_POST['new_password'];
    $confirm = $_POST['confirm_password'];

    if (!password_verify($current, $admin['password'])) {
        $error = "Password saat ini salah.";
    } elseif (strlen($new) < 8) {
        $error = "Password baru minimal 8 karakter.";
    } elseif ($new !== $confirm) {
        $error = "Konfirmasi password baru tidak cocok.";
    } else {
        $conn->prepare("UPDATE users SET password = ? WHERE id_user = ?")
             ->execute([password_hash($new, PASSWORD_DEFAULT), $id_user]);
        $success = "Password berhasil diperbarui.";
        // Refresh data to get the new hash for subsequent checks
        $stmt = $conn->prepare("SELECT * FROM users WHERE id_user = ?");
        $stmt->execute([$id_user]);
        $admin = $stmt->fetch();
    }
}

// Handle Update Profile Picture
if (isset($_POST['upload_foto'])) {
    $base64 = $_POST['cropped_image'] ?? '';
    if (!empty($base64)) {
        if (strpos($base64, ',') !== false) {
            @list($type, $imgData) = explode(';', $base64);
            @list(, $imgData)      = explode(',', $imgData);
            $imgData = base64_decode($imgData);
            
            $dir = 'uploads/profiles/';
            if (!is_dir($dir)) mkdir($dir, 0777, true);
            
            $fileName = 'admin_' . $id_user . '_' . time() . '.jpg';
            $fullPath = $dir . $fileName;
            
            if (file_put_contents($fullPath, $imgData)) {
                // Delete old photo
                $oldPath = $admin['foto_profil'];
                if ($oldPath && file_exists($oldPath)) @unlink($oldPath);
                
                $conn->prepare("UPDATE users SET foto_profil = ? WHERE id_user = ?")->execute([$fullPath, $id_user]);
                $success = "Foto profil berhasil diperbarui.";
                // Refresh data
                $stmt = $conn->prepare("SELECT * FROM users WHERE id_user = ?");
                $stmt->execute([$id_user]);
                $admin = $stmt->fetch();
            }
        }
    }
}

$initials = strtoupper(substr($admin['nama'], 0, 1));
$joinDate  = date('d M Y', strtotime($admin['created_at'] ?? 'now'));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil Admin — TixNow</title>
    <!-- Cropper.js -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.js"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;500;600;700;800&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <script>
        (function() {
            const t = localStorage.getItem('theme') || 'dark';
            document.documentElement.setAttribute('data-theme', t);
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
            --red: #f87171;
            --red-glow: rgba(248,113,113,0.12);
            --green: #34d399;
            --text: #e2e8f0;
            --muted: #4b5a72;
            --header-text: #ffffff;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

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
            --red-glow: rgba(239, 68, 68, 0.08);
            --green: #10b981;
            --text: #374151;
            --muted: #6b7280;
            --header-text: #111827;
        }

        body { font-family: 'Inter', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; display: flex; transition: var(--transition); }

        /* SIDEBAR (Copy Literal from admin_dashboard.php) */
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

        /* MAIN CONTENT */
        .main { margin-left: 230px; flex: 1; padding: 40px; transition: var(--transition); }
        .page-header { margin-bottom: 40px; }
        .page-title { font-family: 'Syne', sans-serif; font-size: 2rem; font-weight: 800; margin-bottom: 8px; color: var(--header-text); }
        .page-sub { color: var(--muted); font-size: 0.95rem; }

        /* ALERT */
        .alert { padding: 14px 20px; border-radius: 12px; font-size: 0.85rem; margin-bottom: 28px; display: flex; align-items: center; gap: 12px; transition: opacity 0.5s ease, transform 0.5s ease; }
        .alert-error   { background: rgba(248,113,113,0.1); border: 1px solid rgba(248,113,113,0.25); color: var(--red); }
        .alert-success { background: rgba(52,211,153,0.1); border: 1px solid rgba(52,211,153,0.25); color: var(--green); }

        /* IDENTITY CARD */
        .identity-card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 28px 32px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 24px;
            position: relative;
        }
        .identity-card::before {
            content: '';
            position: absolute; inset: 0;
            background: linear-gradient(135deg, rgba(167,139,250,0.06) 0%, transparent 60%);
            pointer-events: none;
        }
        .identity-glow {
            position: absolute; top: -60px; left: -60px;
            width: 200px; height: 200px;
            background: radial-gradient(circle, rgba(167,139,250,0.12) 0%, transparent 70%);
            pointer-events: none;
        }
        .admin-avatar {
            width: 72px; height: 72px; border-radius: 18px;
            background: linear-gradient(135deg, var(--accent), #7c3aed);
            display: flex; align-items: center; justify-content: center;
            font-family: 'Syne', sans-serif; font-size: 1.8rem; font-weight: 800;
            color: #fff; flex-shrink: 0;
            box-shadow: 0 8px 24px rgba(167,139,250,0.3);
            position: relative; z-index: 1;
        }
        .identity-info { flex: 1; position: relative; z-index: 1; }
        .identity-name { font-family: 'Syne', sans-serif; font-size: 1.3rem; font-weight: 800; color: var(--header-text); margin-bottom: 5px; }
        .identity-role-badge {
            display: inline-flex; align-items: center; gap: 6px;
            background: rgba(167,139,250,0.12); border: 1px solid rgba(167,139,250,0.2);
            color: var(--accent); font-size: 0.7rem; font-weight: 700;
            padding: 4px 10px; border-radius: 6px; text-transform: uppercase; letter-spacing: 0.1em;
            margin-bottom: 10px;
        }
        .identity-meta { display: flex; flex-wrap: wrap; gap: 16px; }
        .identity-meta-item { font-size: 0.78rem; color: var(--muted); display: flex; align-items: center; gap: 6px; }
        .identity-meta-item svg { color: var(--accent); opacity: 0.7; }
        .identity-stats { display: flex; gap: 12px; position: relative; z-index: 1; }
        .id-stat { text-align: center; background: var(--surface-alpha); border: 1px solid var(--border); border-radius: 12px; padding: 14px 20px; }
        .id-stat-val { font-family: 'Syne', sans-serif; font-size: 1.2rem; font-weight: 800; color: var(--accent); }
        .id-stat-label { font-size: 0.65rem; color: var(--muted); margin-top: 3px; text-transform: uppercase; letter-spacing: 0.08em; }

        /* PHOTO UPLOAD UI */
        .avatar-container { position: relative; cursor: pointer; group: hover; }
        .avatar-overlay { position: absolute; inset: 0; background: rgba(0,0,0,0.4); border-radius: 18px; display: flex; align-items: center; justify-content: center; opacity: 0; transition: 0.2s; z-index: 2; }
        .avatar-container:hover .avatar-overlay { opacity: 1; }
        .avatar-overlay svg { color: white; width: 24px; height: 24px; }
        
        .admin-avatar-img { width: 72px; height: 72px; border-radius: 18px; object-fit: cover; box-shadow: 0 8px 24px rgba(167,139,250,0.3); position: relative; z-index: 1; }
        
        /* AVATAR ACTION MENU */
        .avatar-action-wrapper { position: relative; display: inline-block; }
        .avatar-menu { position: absolute; top: calc(100% + 10px); left: 50%; transform: translateX(-50%); background: var(--card2); border: 1px solid var(--border); border-radius: 12px; padding: 6px; display: none; z-index: 100; min-width: 150px; box-shadow: 0 10px 30px rgba(0,0,0,0.5); backdrop-filter: blur(10px); }
        .avatar-menu.active { display: block; animation: menuFade 0.2s ease; }
        .avatar-menu-item { display: flex; align-items: center; gap: 10px; padding: 10px 12px; border-radius: 8px; color: var(--text); font-size: 0.75rem; cursor: pointer; transition: 0.2s; white-space: nowrap; }
        .avatar-menu-item:hover { background: var(--accent-glow); color: var(--accent); }
        .avatar-menu-item svg { opacity: 0.7; }
        
        @keyframes menuFade { from { opacity: 0; transform: translateX(-50%) translateY(-10px); } to { opacity: 1; transform: translateX(-50%) translateY(0); } }

        /* LIGHTBOX */
        .lightbox { position: fixed; inset: 0; background: rgba(0,0,0,0.9); backdrop-filter: blur(5px); display: none; align-items: center; justify-content: center; z-index: 3000; padding: 20px; cursor: zoom-out; }
        .lightbox.active { display: flex; }
        .lightbox-img { max-width: 90%; max-height: 90%; border-radius: 16px; box-shadow: 0 0 50px rgba(0,0,0,0.5); cursor: default; }

        /* CROP MODAL */
        .crop-modal { position: fixed; inset: 0; background: rgba(0,0,0,0.85); backdrop-filter: blur(8px); display: none; align-items: center; justify-content: center; z-index: 2000; padding: 20px; }
        .crop-modal.active { display: flex; }
        .crop-content { background: var(--card); border: 1px solid var(--border); border-radius: 24px; width: 100%; max-width: 500px; padding: 30px; position: relative; }
        .crop-area { width: 100%; aspect-ratio: 1; background: #000; border-radius: 12px; overflow: hidden; margin-bottom: 24px; }
        .crop-area img { max-width: 100%; }

        /* CARD GRID */
        .card-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }

        .panel {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 16px;
            overflow: hidden;
        }
        .panel-header {
            padding: 20px 24px 0;
            display: flex; align-items: center; gap: 12px;
            margin-bottom: 20px;
        }
        .panel-icon {
            width: 36px; height: 36px; border-radius: 10px;
            background: rgba(167,139,250,0.1); border: 1px solid rgba(167,139,250,0.15);
            display: flex; align-items: center; justify-content: center; flex-shrink: 0;
        }
        .panel-icon svg { color: var(--accent); }
        .panel-title-text { font-family: 'Syne', sans-serif; font-size: 0.95rem; font-weight: 700; color: var(--header-text); }
        .panel-sub-text { font-size: 0.72rem; color: var(--muted); margin-top: 1px; }
        .panel-body { padding: 0 24px 24px; }
        .panel-divider { height: 1px; background: var(--border); margin: 0 24px 20px; }

        /* FORM */
        .form-group { margin-bottom: 16px; }
        .form-label { display: block; font-size: 0.72rem; font-weight: 700; color: var(--muted); margin-bottom: 7px; text-transform: uppercase; letter-spacing: 0.1em; }
        .form-input {
            width: 100%;
            background: var(--surface-alpha);
            border: 1px solid var(--border-alpha);
            border-radius: 10px;
            padding: 11px 16px;
            color: var(--text);
            font-size: 0.88rem;
            font-family: 'Inter', sans-serif;
            transition: 0.2s; outline: none;
        }
        .form-input:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(167,139,250,0.15); }
        .form-input::placeholder { color: var(--muted); opacity: 0.6; }

        .input-with-icon { position: relative; }
        .input-with-icon svg { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: var(--muted); pointer-events: none; }
        .input-with-icon .form-input { padding-left: 40px; }

        .btn-primary {
            width: 100%; padding: 11px; border-radius: 10px; border: none;
            background: var(--accent); color: var(--bg);
            font-weight: 700; font-size: 0.85rem; font-family: 'Inter', sans-serif;
            cursor: pointer; transition: 0.2s;
            display: flex; align-items: center; justify-content: center; gap: 8px;
            margin-top: 8px;
        }
        .btn-primary:hover:not(:disabled) { opacity: 0.9; transform: translateY(-1px); }
        .btn-primary:disabled { opacity: 0.4; cursor: not-allowed; transform: none; box-shadow: none; }

        .btn-secondary {
            width: 100%; padding: 11px; border-radius: 10px;
            background: var(--surface-alpha); border: 1px solid var(--border-alpha);
            color: var(--text); font-weight: 600; font-size: 0.85rem; font-family: 'Inter', sans-serif;
            cursor: pointer; transition: 0.2s;
            display: flex; align-items: center; justify-content: center; gap: 8px;
            margin-top: 8px;
        }
        .btn-secondary:hover { border-color: var(--accent); color: var(--accent); }

        /* Password Strength */
        .strength-bar { height: 4px; border-radius: 4px; background: var(--border); margin-top: 8px; overflow: hidden; }
        .strength-fill { height: 100%; border-radius: 4px; transition: 0.4s; width: 0; }
        .strength-label { font-size: 0.68rem; margin-top: 5px; color: var(--muted); }

        /* DANGER ZONE */
        .danger-zone {
            margin-top: 20px;
            background: var(--card);
            border: 1px solid rgba(248,113,113,0.2);
            border-radius: 16px;
            overflow: hidden;
        }
        .danger-header {
            padding: 16px 24px;
            background: var(--red-glow);
            display: flex; align-items: center; gap: 10px;
            border-bottom: 1px solid rgba(248,113,113,0.15);
        }
        .danger-title { font-size: 0.82rem; font-weight: 700; color: var(--red); }
        .danger-body { padding: 18px 24px; }
        .danger-desc { font-size: 0.78rem; color: var(--muted); margin-bottom: 14px; line-height: 1.6; }

        @media (max-width: 900px) {
            .card-grid { grid-template-columns: 1fr; }
            .identity-card { flex-direction: column; text-align: center; }
            .identity-meta { justify-content: center; }
            .identity-stats { justify-content: center; }
        }

        /* ALERTS */
        .alert {
            padding: 14px 20px;
            border-radius: 12px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 0.85rem;
            font-weight: 600;
            animation: slideDown 0.4s ease;
        }
        .alert-success {
            background: rgba(52,211,153,0.1);
            color: var(--green);
            border: 1px solid rgba(52,211,153,0.2);
        }
        .alert-error {
            background: rgba(248,113,113,0.1);
            color: var(--red);
            border: 1px solid rgba(248,113,113,0.2);
        }
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>

<!-- SIDEBAR -->
<aside class="sidebar">
    <div class="sidebar-logo">Tix<span>Now</span></div>
    <div class="sidebar-label">Menu</div>
    <a href="admin_dashboard" class="sidebar-item">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
        Dashboard
    </a>
    <a href="admin_profile" class="sidebar-item active">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
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

<!-- MAIN -->
<main class="main">
    <div class="page-header">
        <h1 class="page-title">Profil Administrator</h1>
        <p class="page-sub">Kelola informasi akun dan keamanan panel kontrol.</p>
    </div>

    <!-- ALERTS -->
    <?php if ($error): ?>
        <div class="alert alert-error">
            <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success">
            <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            <?php echo htmlspecialchars($success); ?>
        </div>
    <?php endif; ?>

    <!-- IDENTITY HERO CARD -->
    <div class="identity-card">
        <div class="identity-glow"></div>
        <div class="avatar-action-wrapper">
            <div class="avatar-container" onclick="toggleAvatarMenu(event)">
                <div class="avatar-overlay">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                </div>
                <?php if (!empty($admin['foto_profil'])): ?>
                    <img src="<?php echo htmlspecialchars($admin['foto_profil']); ?>" class="admin-avatar-img" id="currentAvatar">
                <?php else: ?>
                    <div class="admin-avatar" id="currentAvatarPlaceholder"><?php echo $initials; ?></div>
                <?php endif; ?>
            </div>
            <div class="avatar-menu" id="avatarMenu">
                <div class="avatar-menu-item" onclick="openLightbox()">
                    <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                    Lihat Foto
                </div>
                <div class="avatar-menu-item" onclick="document.getElementById('profileInput').click(); hideAvatarMenu();">
                    <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    Ganti Foto
                </div>
            </div>
        </div>
        <input type="file" id="profileInput" hidden accept="image/*" onchange="handleFile(this)">
        <div class="identity-info">
            <div class="identity-name"><?php echo htmlspecialchars($admin['nama']); ?></div>
            <div class="identity-role-badge">
                <svg width="10" height="10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                Super Administrator
            </div>
            <div class="identity-meta">
                <div class="identity-meta-item">
                    <svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                    <?php echo htmlspecialchars($admin['email']); ?>
                </div>
                <?php if (!empty($admin['no_telepon'])): ?>
                <div class="identity-meta-item">
                    <svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                    <?php echo htmlspecialchars($admin['no_telepon']); ?>
                </div>
                <?php endif; ?>
                <div class="identity-meta-item">
                    <svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    Bergabung: <?php echo $joinDate; ?>
                </div>
            </div>
        </div>
        <div class="identity-stats">
            <div class="id-stat">
                <div class="id-stat-val">#<?php echo $admin['id_user']; ?></div>
                <div class="id-stat-label">User ID</div>
            </div>
            <div class="id-stat">
                <div class="id-stat-val" style="color: var(--green);">●</div>
                <div class="id-stat-label">Online</div>
            </div>
        </div>
    </div>

    <!-- FORM CARDS -->
    <div class="card-grid">

        <!-- Informasi Akun -->
        <div class="panel">
            <div class="panel-header">
                <div class="panel-icon">
                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                </div>
                <div>
                    <div class="panel-title-text">Informasi Akun</div>
                    <div class="panel-sub-text">Perbarui data pribadi administrator</div>
                </div>
            </div>
            <div class="panel-divider"></div>
            <div class="panel-body">
                <form method="POST" id="profileForm">
                    <div class="form-group">
                        <label class="form-label">Nama Lengkap</label>
                        <div class="input-with-icon">
                            <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.121 17.804A13.937 13.937 0 0112 16c2.5 0 4.847.655 6.879 1.804M15 10a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            <input type="text" name="nama" value="<?php echo htmlspecialchars($admin['nama']); ?>" class="form-input" placeholder="Nama Lengkap" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Username</label>
                        <div class="input-with-icon">
                            <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207"/></svg>
                            <input type="text" name="username" value="<?php echo htmlspecialchars($admin['username']); ?>" class="form-input" placeholder="Username" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email</label>
                        <div class="input-with-icon">
                            <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                            <input type="email" name="email" value="<?php echo htmlspecialchars($admin['email']); ?>" class="form-input" placeholder="Email" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Nomor Telepon</label>
                        <div class="input-with-icon">
                            <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                            <input type="text" name="no_telepon" value="<?php echo htmlspecialchars($admin['no_telepon'] ?? ''); ?>" class="form-input" placeholder="08xxxxxxxxxx">
                        </div>
                    </div>
                    <button type="submit" name="update_profile" id="btnSimpan" class="btn-primary" disabled>
                        <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                        Simpan Perubahan
                    </button>
                </form>
            </div>
        </div>

        <!-- Keamanan -->
        <div class="panel">
            <div class="panel-header">
                <div class="panel-icon">
                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                </div>
                <div>
                    <div class="panel-title-text">Keamanan Akun</div>
                    <div class="panel-sub-text">Ganti password untuk melindungi panel kontrol</div>
                </div>
            </div>
            <div class="panel-divider"></div>
            <div class="panel-body">
                <form method="POST">
                    <div class="form-group">
                        <label class="form-label">Password Saat Ini</label>
                        <div class="input-with-icon">
                            <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>
                            <input type="password" name="current_password" class="form-input" placeholder="••••••••" required id="curPwd">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Password Baru</label>
                        <div class="input-with-icon">
                            <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                            <input type="password" name="new_password" class="form-input" placeholder="Min. 8 karakter" required id="newPwd" oninput="checkStrength(this.value)">
                        </div>
                        <div class="strength-bar"><div class="strength-fill" id="strengthFill"></div></div>
                        <div class="strength-label" id="strengthLabel">Masukkan password baru</div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Konfirmasi Password Baru</label>
                        <div class="input-with-icon">
                            <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <input type="password" name="confirm_password" class="form-input" placeholder="Sesuai password baru" required id="confPwd">
                        </div>
                    </div>
                    <button type="submit" name="update_password" class="btn-secondary">
                        <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                        Ubah Password Sekarang
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- DANGER ZONE -->
    <div class="danger-zone">
        <div class="danger-header">
            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
            <span class="danger-title">Zona Bahaya</span>
        </div>
        <div class="danger-body">
            <p class="danger-desc">Jika Anda log out, sesi admin akan berakhir dan Anda perlu memasukkan kredensial kembali untuk mengakses panel kontrol. Pastikan semua perubahan sudah tersimpan sebelum melanjutkan.</p>
            <a href="logout" style="display:inline-flex; align-items:center; gap:8px; padding:9px 20px; border-radius:9px; background: var(--red-glow); border: 1px solid rgba(248,113,113,0.25); color:var(--red); font-size:0.82rem; font-weight:700; text-decoration:none; transition:0.2s;" onmouseover="this.style.background='rgba(248,113,113,0.15)'" onmouseout="this.style.background='var(--red-glow)'">
                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                Keluar dari Panel Kontrol
            </a>
        </div>
    </div>

    <!-- CROP MODAL -->
    <div class="crop-modal" id="cropModal">
        <div class="crop-content">
            <h3 style="font-family:'Syne',sans-serif; margin-bottom:20px;">Sesuaikan Foto Profil</h3>
            <div class="crop-area">
                <img id="cropImage">
            </div>
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px;">
                <button class="btn-secondary" style="margin-top:0;" onclick="closeCrop()">Batal</button>
                <button class="btn-primary" style="margin-top:0;" onclick="saveCrop()">Terapkan & Simpan</button>
            </div>
        </div>
    </div>

    <!-- HIDDEN FORM FOR UPLOAD -->
    <form id="uploadForm" method="POST" style="display:none;">
        <input type="hidden" name="upload_foto" value="1">
        <input type="hidden" name="cropped_image" id="croppedInput">
    </form>

    <!-- LIGHTBOX -->
    <div class="lightbox" id="lightbox" onclick="closeLightbox()">
        <img src="" class="lightbox-img" id="lightboxImg">
    </div>

</main>

<script>
    // ── PHOTO UPLOAD & CROP ──
    let cropper = null;
    const cropModal = document.getElementById('cropModal');
    const cropImage = document.getElementById('cropImage');
    const profileInput = document.getElementById('profileInput');

    function handleFile(input) {
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                cropImage.src = e.target.result;
                cropModal.classList.add('active');
                if (cropper) cropper.destroy();
                cropper = new Cropper(cropImage, {
                    aspectRatio: 1,
                    viewMode: 2,
                    dragMode: 'move',
                    background: false,
                    autoCropArea: 1,
                    ready: function() { updateLivePreview(); },
                    crop: function() { updateLivePreview(); }
                });
            };
            reader.readAsDataURL(input.files[0]);
        }
    }

    function updateLivePreview() {
        if (!cropper) return;
        const canvas = cropper.getCroppedCanvas({ width: 200, height: 200 });
        const dataUrl = canvas.toDataURL('image/jpeg');
        
        const avatarContainer = document.querySelector('.avatar-container');
        let previewImg = document.getElementById('adminLivePreview');
        
        if (!previewImg) {
            previewImg = document.createElement('img');
            previewImg.id = 'adminLivePreview';
            previewImg.className = 'admin-avatar-img';
            previewImg.style.position = 'absolute';
            previewImg.style.top = '0';
            previewImg.style.left = '0';
            previewImg.style.zIndex = '5';
            avatarContainer.appendChild(previewImg);
        }
        
        previewImg.src = dataUrl;
        previewImg.style.display = 'block';
    }

    function closeCrop() {
        cropModal.classList.remove('active');
        const previewImg = document.getElementById('adminLivePreview');
        if (previewImg) previewImg.style.display = 'none';
        profileInput.value = '';
    }

    function saveCrop() {
        if (!cropper) return;
        const canvas = cropper.getCroppedCanvas({ width: 400, height: 400 });
        const base64 = canvas.toDataURL('image/jpeg', 0.9);
        
        document.getElementById('croppedInput').value = base64;
        document.getElementById('uploadForm').submit();
    }

    // ── AVATAR MENU & LIGHTBOX ──
    const avatarMenu = document.getElementById('avatarMenu');
    function toggleAvatarMenu(e) {
        e.stopPropagation();
        // Cek apakah sudah ada foto (id currentAvatar)
        const hasPhoto = document.getElementById('currentAvatar');
        if (!hasPhoto) {
            document.getElementById('profileInput').click();
            return;
        }
        avatarMenu.classList.toggle('active');
    }
    function hideAvatarMenu() {
        avatarMenu.classList.remove('active');
    }
    document.addEventListener('click', hideAvatarMenu);

    function openLightbox() {
        const lightbox = document.getElementById('lightbox');
        const lightboxImg = document.getElementById('lightboxImg');
        const currentImg = document.getElementById('currentAvatar');
        
        if (currentImg && currentImg.tagName === 'IMG') {
            lightboxImg.src = currentImg.src;
            lightbox.classList.add('active');
            document.body.style.overflow = 'hidden';
        } else {
            alert('Belum ada foto profil untuk ditampilkan.');
        }
    }
    function closeLightbox() {
        document.getElementById('lightbox').classList.remove('active');
        document.body.style.overflow = '';
    }

    function toggleAdminTheme() {
        const cur = document.documentElement.getAttribute('data-theme');
        const next = cur === 'dark' ? 'light' : 'dark';
        document.documentElement.setAttribute('data-theme', next);
        localStorage.setItem('theme', next);
        refreshAdminIcons(next);
    }
    function refreshAdminIcons(t) {
        const sun = document.getElementById('adminSunIcon');
        const moon = document.getElementById('adminMoonIcon');
        if (sun) sun.style.display  = t === 'light' ? 'block' : 'none';
        if (moon) moon.style.display = t === 'dark'  ? 'block' : 'none';
    }
    refreshAdminIcons(document.documentElement.getAttribute('data-theme'));

    function checkStrength(val) {
        const fill  = document.getElementById('strengthFill');
        const label = document.getElementById('strengthLabel');
        if (!fill || !label) return;
        let score = 0;
        if (val.length >= 8)        score++;
        if (/[A-Z]/.test(val))      score++;
        if (/[0-9]/.test(val))      score++;
        if (/[^A-Za-z0-9]/.test(val)) score++;

        const map = [
            { w:'0%',   color:'transparent', text: 'Masukkan password baru' },
            { w:'25%',  color:'#f87171',     text: 'Lemah' },
            { w:'55%',  color:'#fbbf24',     text: 'Cukup' },
            { w:'80%',  color:'#34d399',     text: 'Kuat' },
            { w:'100%', color:'#a78bfa',     text: 'Sangat Kuat 💪' },
        ];
        fill.style.width      = map[score].w;
        fill.style.background = map[score].color;
        label.textContent     = map[score].text;
        label.style.color     = map[score].color || 'var(--muted)';
    }

    // ── SAVE BUTTON GUARD ──
    (function() {
        const btn     = document.getElementById('btnSimpan');
        const inputs  = document.querySelectorAll('#profileForm .form-input');
        if (!btn) return;

        // Store original values on load
        const originals = {};
        inputs.forEach(inp => { originals[inp.name] = inp.value.trim(); });

        function checkDirty() {
            const dirty = [...inputs].some(inp => inp.value.trim() !== originals[inp.name]);
            btn.disabled = !dirty;
        }

        // Listen for any change in the profile form inputs
        inputs.forEach(inp => inp.addEventListener('input', checkDirty));
    })();

    // ── AUTO-DISMISS ALERTS ──
    document.querySelectorAll('.alert').forEach(function(alert) {
        setTimeout(function() {
            alert.style.opacity = '0';
            alert.style.transform = 'translateY(-8px)';
            setTimeout(function() { alert.remove(); }, 500);
        }, 3000);
    });
</script>

</body>
</html>
