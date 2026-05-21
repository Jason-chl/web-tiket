<?php
session_start();
require_once 'koneksi.php';

if (!isset($_SESSION['id_user'])) {
    header("Location: index");
    exit;
}

if ($_SESSION['role'] === 'admin') {
    header("Location: admin_profile");
    exit;
}

// Schema already handled by koneksi.php and system rebuild


$userId = $_SESSION['id_user'];

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_info') {
        $nama = trim($_POST['nama'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $no_telepon = trim($_POST['no_telepon'] ?? '');

        if (empty($nama) || empty($username)) {
            $error = 'Nama Lengkap dan Username tidak boleh kosong.';
        } else {
            // Cek no_telepon duplikat (kecuali milik sendiri)
            if (!empty($no_telepon)) {
                $chkT = $conn->prepare("SELECT id_user FROM users WHERE no_telepon = ? AND id_user != ?");
                $chkT->execute([$no_telepon, $userId]);
                if ($chkT->rowCount() > 0) {
                    $error = 'Nomor telepon sudah digunakan akun lain.';
                }
            }

            if (empty($error)) {
                $stmt = $conn->prepare("UPDATE users SET nama = ?, username = ?, no_telepon = ? WHERE id_user = ?");
                $stmt->execute([$nama, $username, $no_telepon, $userId]);
                $_SESSION['nama'] = $nama;
                $_SESSION['username'] = $username;
                $success = 'Informasi akun berhasil diperbarui.';
            }
        }
    }

    if ($action === 'update_password') {
        $cur = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $cfm = $_POST['confirm_password'] ?? '';

        $stmtU = $conn->prepare("SELECT password FROM users WHERE id_user = ?");
        $stmtU->execute([$userId]);
        $row = $stmtU->fetch();

        if (!password_verify($cur, $row['password'])) {
            $error = 'Password saat ini tidak sesuai.';
        } elseif (strlen($new) < 8) {
            $error = 'Password baru minimal 8 karakter.';
        } elseif ($new !== $cfm) {
            $error = 'Konfirmasi password tidak cocok.';
        } else {
            $hash = password_hash($new, PASSWORD_DEFAULT);
            $conn->prepare("UPDATE users SET password = ? WHERE id_user = ?")->execute([$hash, $userId]);
            $success = 'Password berhasil diubah.';
        }
    }

    if ($action === 'upload_foto') {
        $base64 = $_POST['cropped_image'] ?? '';

        if (empty($base64)) {
            $error = 'Tidak ada gambar yang dipilih.';
        } else {
            // Saring data base64 dengan lebih aman
            if (strpos($base64, ',') !== false) {
                @list($type, $imgData) = explode(';', $base64);
                @list(, $imgData)      = explode(',', $imgData);
                $imgData = base64_decode($imgData);

                $dir = 'uploads/profiles/';
                if (!is_dir($dir)) {
                    mkdir($dir, 0777, true);
                    // Buat file index.php kosong untuk keamanan (optional but good practice)
                    file_put_contents($dir . 'index.php', '');
                }

                // Tentukan ekstensi (default jpg dari toDataURL di JS)
                $ext = 'jpg';
                
                // Hapus foto lama agar folder tidak penuh
                $old = $conn->prepare("SELECT foto_profil FROM users WHERE id_user = ?");
                $old->execute([$userId]);
                $oldPath = $old->fetchColumn();
                if ($oldPath && file_exists($oldPath) && strpos($oldPath, 'uploads/profiles/') === 0) {
                    @unlink($oldPath);
                }

                $fname = 'profile_' . $userId . '_' . time() . '.' . $ext;
                $target = $dir . $fname;

                if (file_put_contents($target, $imgData)) {
                    $conn->prepare("UPDATE users SET foto_profil = ? WHERE id_user = ?")->execute([$target, $userId]);
                    $success = 'Foto profil berhasil diperbarui.';
                } else {
                    $error = 'Gagal menyimpan file ke server. Cek izin folder uploads.';
                }
            } else {
                $error = 'Format data gambar tidak valid.';
            }
        }
    }

    if ($action === 'remove_foto') {
        $old = $conn->prepare("SELECT foto_profil FROM users WHERE id_user = ?");
        $old->execute([$userId]);
        $oldPath = $old->fetchColumn();
        if ($oldPath && file_exists($oldPath)) unlink($oldPath);
        $conn->prepare("UPDATE users SET foto_profil = NULL WHERE id_user = ?")->execute([$userId]);
        $success = 'Foto profil dihapus.';
    }
}

// Ambil data user terbaru
$stmt = $conn->prepare("SELECT * FROM users WHERE id_user = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    session_destroy();
    header("Location: login?msg=Sesi berakhir, silakan login kembali.");
    exit;
}

$initials = strtoupper(substr($user['username'] ?? $user['nama'] ?? 'U', 0, 1));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil Saya — TixNow</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;500;600;700;800&family=Inter:wght@300;400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.css">
    <script>
        // IMMEDIATE THEME LOAD
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
            --accent-dim: rgba(167,139,250,0.1);
            --btn-text: #07090f;
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
            --accent-dim: rgba(109, 40, 217, 0.08);
            --btn-text: #ffffff;
            --red: #ef4444;
            --red-glow: rgba(239, 68, 68, 0.08);
            --green: #10b981;
            --text: #374151;
            --muted: #6b7280;
            --header-text: #111827;
            --nav-bg: rgba(255, 255, 255, 0.75);
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        /* CUSTOM LIGHT THEME FIXES FOR PROFILE FORMS */
        [data-theme="light"] .form-input {
            background: rgba(0,0,0,0.03);
            border: 1px solid rgba(0,0,0,0.08);
        }
        [data-theme="light"] .form-input:focus {
            background: rgba(109, 40, 217, 0.04);
            border-color: rgba(109, 40, 217, 0.3);
        }
        [data-theme="light"] .btn-upload-foto {
            background: rgba(0,0,0,0.03);
            border: 1px dashed rgba(0,0,0,0.15);
        }
        [data-theme="light"] .crop-tool-btn {
            background: rgba(0,0,0,0.04);
            border: 1px solid rgba(0,0,0,0.08);
        }
        [data-theme="light"] .crop-tool-btn:hover {
            color: var(--header-text);
            background: rgba(0,0,0,0.08);
        }
        [data-theme="light"] .btn-cancel-upload:hover {
            background: rgba(0,0,0,0.04);
        }
        [data-theme="light"] .modal-close {
            background: rgba(0,0,0,0.05);
        }
        [data-theme="light"] .modal-close:hover {
            color: var(--text);
            background: rgba(0,0,0,0.1);
        }

        /* VIP CARD LIGHT MODE - PREMIUM DARK CARD STYLE */
        [data-theme="light"] .vip-status-card {
            background: #0f1521; /* Deep dark background even in light mode */
            border-color: rgba(255, 215, 0, 0.3);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.15);
            position: relative;
        }
        [data-theme="light"] .vip-card-label { color: rgba(255, 215, 0, 0.8); opacity: 1; }
        [data-theme="light"] .vip-card-title { color: #ffd700; }
        [data-theme="light"] .vip-card-perk { color: rgba(255, 215, 0, 0.7); opacity: 1; }
        [data-theme="light"] .vip-card-perk svg { color: #34d399; }
        [data-theme="light"] .not-vip-info strong { color: #b45309 !important; }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            overflow-x: hidden;
            transition: var(--transition);
        }

        /* NAV */
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
            text-decoration: none;
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

        .nav-vip-btn {
            display: flex; align-items: center; gap: 6px;
            padding: 6px 14px; border-radius: 999px;
            background: linear-gradient(135deg, rgba(255,215,0,0.1), rgba(255,174,0,0.05));
            border: 1px solid rgba(255,215,0,0.3);
            color: #ffd700; font-size: 0.75rem; font-weight: 700; text-decoration: none; transition: 0.2s;
        }
        .nav-vip-btn:hover { background: linear-gradient(135deg, rgba(255,215,0,0.2), rgba(255,174,0,0.1)); border-color: rgba(255,215,0,0.5); transform: translateY(-1px); }

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
            border: 1px solid var(--border); transition: all 0.2s; background: transparent;
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

        /* LAYOUT */
        .container {
            max-width: 900px;
            margin: 0 auto;
            padding: 110px 24px 80px;
        }

        .page-head { margin-bottom: 36px; }
        .page-head h1 {
            font-family: 'Syne', sans-serif;
            font-size: 1.8rem;
            font-weight: 800;
            color: var(--header-text);
            letter-spacing: -0.5px;
        }
        .page-head p { font-size: 0.85rem; color: var(--muted); margin-top: 4px; }

        /* ALERT */
        .alert {
            padding: 12px 18px;
            border-radius: 10px;
            font-size: 0.83rem;
            margin-bottom: 24px;
            display: flex;
            align-items: flex-start;
            gap: 10px;
        }
        .alert-success { background: rgba(52,211,153,0.08); border: 1px solid rgba(52,211,153,0.2); color: var(--green); }
        .alert-error { background: rgba(248,113,113,0.08); border: 1px solid rgba(248,113,113,0.2); color: var(--red); }

        /* GRID */
        .grid-profile {
            display: grid;
            grid-template-columns: 280px 1fr;
            gap: 20px;
            align-items: start;
        }

        /* SIDEBAR CARD */
        .profile-sidebar {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .avatar-card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 28px 20px;
            text-align: center;
        }

        .avatar-wrap {
            position: relative;
            display: inline-block;
            margin-bottom: 16px;
        }

        .avatar {
            width: 96px; height: 96px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid rgba(200,181,255,0.3);
            display: block;
        }

        .avatar-initials {
            width: 96px; height: 96px;
            border-radius: 50%;
            background: linear-gradient(135deg, rgba(200,181,255,0.2), rgba(200,181,255,0.06));
            border: 3px solid rgba(200,181,255,0.25);
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Syne', sans-serif;
            font-size: 1.6rem;
            font-weight: 800;
            color: var(--accent);
        }

        .avatar-edit-btn {
            position: absolute;
            bottom: 2px; right: 2px;
            width: 28px; height: 28px;
            border-radius: 50%;
            background: var(--accent);
            border: 2px solid var(--card);
            display: flex; align-items: center; justify-content: center;
            cursor: pointer;
            transition: all 0.2s;
        }
        .avatar-edit-btn:hover { transform: scale(1.1); }
        .avatar-edit-btn svg { color: var(--btn-text); }

        .avatar-name {
            font-family: 'Syne', sans-serif;
            font-size: 1rem;
            font-weight: 700;
            color: var(--header-text);
            margin-bottom: 3px;
        }
        .avatar-email {
            font-size: 0.75rem;
            color: var(--muted);
            margin-bottom: 16px;
            word-break: break-all;
        }
        .avatar-role {
            display: inline-block;
            font-size: 0.65rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            padding: 4px 14px;
            border-radius: 999px;
            background: var(--accent-dim);
            color: var(--accent);
            border: 1px solid rgba(200,181,255,0.2);
        }

        /* upload modal trigger */
        .upload-form-inline { margin-top: 14px; }
        .btn-upload-foto {
            width: 100%;
            padding: 9px;
            border-radius: 9px;
            background: rgba(255,255,255,0.04);
            border: 1px dashed rgba(255,255,255,0.1);
            color: var(--muted);
            font-size: 0.77rem;
            cursor: pointer;
            transition: all 0.2s;
            font-family: 'Inter', sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
        }
        .btn-upload-foto:hover { color: var(--text); border-color: var(--border-alpha); background: var(--accent-dim); }

        .btn-remove-foto {
            width: 100%;
            margin-top: 8px;
            padding: 7px;
            border-radius: 9px;
            background: transparent;
            border: 1px solid var(--border-alpha);
            color: var(--red);
            font-size: 0.73rem;
            cursor: pointer;
            transition: all 0.2s;
            font-family: 'Inter', sans-serif;
        }
        .btn-remove-foto:hover { background: var(--accent-dim); }

        /* AVATAR OVERLAY */
        .avatar-wrap { position: relative; display: inline-block; margin-bottom: 16px; }
        .avatar-overlay {
            position: absolute;
            inset: 0;
            border-radius: 50%;
            background: rgba(0,0,0,0.4);
            display: flex; align-items: center; justify-content: center;
            opacity: 0;
            transition: opacity 0.25s;
        }
        .avatar-wrap:hover .avatar-overlay { opacity: 1; }

        /* AVATAR ACTION MENU */
        .avatar-action-wrapper { position: relative; display: inline-block; }
        .avatar-menu { position: absolute; top: 100%; left: 50%; transform: translateX(-50%) translateY(10px); background: var(--card2); border: 1px solid var(--border); border-radius: 12px; padding: 6px; display: none; z-index: 100; min-width: 140px; box-shadow: 0 10px 30px rgba(0,0,0,0.5); backdrop-filter: blur(10px); }
        .avatar-menu.active { display: block; animation: menuFade 0.2s ease; }
        .avatar-menu-item { display: flex; align-items: center; gap: 10px; padding: 10px 12px; border-radius: 8px; color: var(--text); font-size: 0.75rem; cursor: pointer; transition: 0.2s; white-space: nowrap; }
        .avatar-menu-item:hover { background: var(--accent-glow); color: var(--accent); }
        .avatar-menu-item svg { opacity: 0.7; }
        
        @keyframes menuFade { from { opacity: 0; transform: translateX(-50%) translateY(0); } to { opacity: 1; transform: translateX(-50%) translateY(10px); } }

        /* LIGHTBOX */
        .lightbox { position: fixed; inset: 0; background: rgba(0,0,0,0.9); backdrop-filter: blur(5px); display: none; align-items: center; justify-content: center; z-index: 3000; padding: 20px; cursor: zoom-out; }
        .lightbox.active { display: flex; }
        .lightbox-img { max-width: 90%; max-height: 90%; border-radius: 12px; box-shadow: 0 0 50px rgba(0,0,0,0.5); cursor: default; }

        /* UPLOAD ACTIONS */
        .btn-upload-submit {
            width: 100%;
            padding: 10px;
            border-radius: 9px;
            background: var(--accent);
            color: var(--btn-text);
            font-weight: 700;
            font-size: 0.82rem;
            border: none;
            cursor: pointer;
            font-family: 'Inter', sans-serif;
            transition: opacity 0.2s;
            margin-bottom: 7px;
        }
        .btn-upload-submit:hover { opacity: 0.88; }

        .btn-cancel-upload {
            width: 100%;
            padding: 8px;
            border-radius: 9px;
            background: transparent;
            border: 1px solid var(--border);
            color: var(--muted);
            font-size: 0.78rem;
            cursor: pointer;
            font-family: 'Inter', sans-serif;
            transition: all 0.2s;
        }
        .btn-cancel-upload:hover { color: var(--text); background: rgba(255,255,255,0.05); }

        /* INFO CARD */
        .info-card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 16px;
        }
        .info-label { font-size: 0.68rem; color: var(--muted); margin-bottom: 3px; text-transform: uppercase; letter-spacing: 0.06em; }
        .info-value { font-size: 0.85rem; color: var(--text); font-weight: 500; }

        .info-item { padding: 10px 0; border-bottom: 1px solid var(--border); }
        .info-item:last-child { border-bottom: none; padding-bottom: 0; }
        .info-item:first-child { padding-top: 0; }

        /* PANELS */
        .panel {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 16px;
            overflow: hidden;
        }

        .panel-header {
            padding: 18px 22px;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .panel-header-icon {
            width: 34px; height: 34px;
            border-radius: 9px;
            background: var(--accent-dim);
            border: 1px solid rgba(200,181,255,0.15);
            display: flex; align-items: center; justify-content: center;
        }
        .panel-header-icon svg { color: var(--accent); }
        .panel-title { font-family: 'Syne', sans-serif; font-size: 0.95rem; font-weight: 700; color: var(--header-text); }
        .panel-sub { font-size: 0.75rem; color: var(--muted); }

        .panel-body { padding: 22px; }

        .panels-stack { display: flex; flex-direction: column; gap: 20px; }

        /* FORM */
        .form-group { margin-bottom: 16px; }
        .form-group:last-of-type { margin-bottom: 0; }
        .form-label {
            display: block;
            font-size: 0.75rem;
            font-weight: 500;
            color: var(--muted);
            margin-bottom: 7px;
            letter-spacing: 0.03em;
        }
        .form-input {
            width: 100%;
            background: var(--surface-alpha);
            border: 1px solid var(--border-alpha);
            border-radius: 10px;
            padding: 10px 14px;
            color: var(--text);
            font-size: 0.85rem;
            font-family: 'Inter', sans-serif;
            transition: border-color 0.2s;
            outline: none;
        }
        .form-input:focus { border-color: var(--accent); background: var(--accent-glow); }
        .form-input::placeholder { color: var(--muted); }
        .form-input:disabled { opacity: 0.4; cursor: not-allowed; }

        .form-hint { font-size: 0.72rem; color: var(--muted); margin-top: 5px; }

        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }

        .btn-save {
            margin-top: 18px;
            padding: 10px 24px;
            border-radius: 9px;
            background: var(--accent);
            color: var(--btn-text);
            font-weight: 700;
            font-size: 0.82rem;
            border: none;
            cursor: pointer;
            font-family: 'Inter', sans-serif;
            transition: all 0.2s;
        }
        .btn-save:hover:not(:disabled) { opacity: 0.88; transform: translateY(-1px); }
        .btn-save:disabled { opacity: 0.3; cursor: not-allowed; filter: grayscale(1); }

        /* MODAL FOTO UPLOAD */
        .modal-backdrop {
            position: fixed; inset: 0; z-index: 300;
            background: rgba(0,0,0,0.85); /* Darker backdrop for focus */
            backdrop-filter: blur(12px);
            display: none; align-items: center; justify-content: center;
        }
        .modal-backdrop.open { display: flex; }
        .modal-box {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 18px;
            width: 100%; max-width: 420px;
            padding: 28px;
            position: relative;
            animation: mi 0.22s ease;
        }
        @keyframes mi { from { opacity:0; transform:scale(0.96); } to { opacity:1; transform:scale(1); } }
        .modal-close {
            position: absolute; top: 16px; right: 16px;
            width: 28px; height: 28px;
            border-radius: 50%;
            background: rgba(0,0,0,0.05);
            border: 1px solid var(--border);
            color: var(--muted); cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            transition: all 0.2s;
        }
        [data-theme="dark"] .modal-close { background: rgba(255,255,255,0.06); }
        .modal-close:hover { color: var(--text); background: rgba(255,255,255,0.12); }
        .modal-title { font-family: 'Syne', sans-serif; font-size: 1rem; font-weight: 700; color: var(--header-text); margin-bottom: 20px; }

        /* DROPZONE */
        .dropzone {
            border: 2px dashed rgba(200,181,255,0.2);
            border-radius: 12px;
            padding: 32px 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s;
            margin-bottom: 16px;
        }
        .dropzone:hover, .dropzone.dragover {
            border-color: rgba(200,181,255,0.5);
            background: var(--accent-dim);
        }
        .dropzone-icon { margin: 0 auto 12px; opacity: 0.4; color: var(--accent); }
        .dropzone-text { font-size: 0.82rem; color: var(--muted); }
        .dropzone-text strong { color: var(--accent); }
        .dropzone input[type=file] { display: none; }
        .preview-img { width: 80px; height: 80px; border-radius: 50%; object-fit: cover; margin: 0 auto 12px; display: block; border: 3px solid rgba(200,181,255,0.3); }

        .btn-upload-submit {
            width: 100%;
            padding: 11px;
            border-radius: 9px;
            background: var(--accent);
            color: #07090f;
            font-weight: 700;
            font-size: 0.83rem;
            border: none;
            cursor: pointer;
            font-family: 'Inter', sans-serif;
            transition: opacity 0.2s;
        }
        .btn-upload-submit:hover { opacity: 0.88; }

        @media (max-width: 720px) {
            .grid-profile { grid-template-columns: 1fr; }
            .container { padding: 90px 16px 60px; }
            .form-row { grid-template-columns: 1fr; }
        }

        /* VIP BADGE */
        .vip-badge {
            background: linear-gradient(135deg, #ffd700, #ffae00);
            color: #000; font-size: 0.65rem; font-weight: 800; padding: 2px 7px;
            border-radius: 4px; margin-left: 6px; text-transform: uppercase;
            box-shadow: 0 0 10px rgba(255, 215, 0, 0.3);
            display: inline-flex; align-items: center; justify-content: center;
        }

        /* VIP STATUS CARD */
        .vip-status-card {
            background: linear-gradient(135deg, rgba(255,215,0,0.08), rgba(255,174,0,0.04));
            border: 1px solid rgba(255,215,0,0.2);
            border-radius: 14px;
            padding: 16px;
        }
        .vip-status-card .vip-card-top {
            display: flex; align-items: center; gap: 10px; margin-bottom: 12px;
        }
        .vip-card-icon {
            width: 36px; height: 36px; border-radius: 10px;
            background: linear-gradient(135deg, #ffd700, #ffae00);
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
        }
        .vip-card-label { font-size: 0.68rem; color: rgba(255,215,0,0.7); margin-bottom: 2px; text-transform: uppercase; letter-spacing: 0.08em; }
        .vip-card-title { font-family: 'Syne', sans-serif; font-weight: 800; font-size: 0.9rem; color: #ffd700; }
        .vip-card-perk { display: flex; align-items: center; gap: 8px; font-size: 0.75rem; color: rgba(255,215,0,0.75); padding: 4px 0; }
        .vip-card-perk svg { color: #34d399; flex-shrink: 0; }

        .not-vip-card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 14px 16px;
            display: flex; flex-direction: column; gap: 10px;
        }
        .not-vip-info { font-size: 0.78rem; color: var(--muted); line-height: 1.5; }
        .btn-get-vip {
            display: flex; align-items: center; justify-content: center; gap: 7px;
            padding: 9px 16px; border-radius: 9px;
            background: linear-gradient(135deg, rgba(255,215,0,0.12), rgba(255,174,0,0.06));
            border: 1px solid rgba(255,215,0,0.3);
            color: #ffd700; font-weight: 700; font-size: 0.8rem;
            text-decoration: none; transition: all 0.25s;
        }
        .btn-get-vip:hover { background: linear-gradient(135deg, rgba(255,215,0,0.2), rgba(255,174,0,0.12)); border-color: rgba(255,215,0,0.55); }
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
        <?php if(empty($user['is_vip'])): ?>
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
            <?php if (!empty($user['foto_profil']) && file_exists($user['foto_profil'])): ?>
            <img src="<?php echo htmlspecialchars($user['foto_profil']); ?>?v=<?php echo time(); ?>" style="width:30px;height:30px;border-radius:50%;object-fit:cover;border:2px solid var(--accent-glow);" alt="">
            <?php else: ?>
            <div style="width:30px;height:30px;border-radius:50%;background:var(--accent-glow);border:2px solid var(--border);display:flex;align-items:center;justify-content:center;font-family:'Syne',sans-serif;font-size:0.75rem;font-weight:700;color:var(--accent);"><?php echo strtoupper(substr($user['username'] ?? 'U', 0, 1)); ?></div>
            <?php endif; ?>
            <span>@<?php echo htmlspecialchars($user['username'] ?? 'User'); ?></span>
            <?php if(!empty($user['is_vip'])): ?>
                <span class="vip-badge">VIP</span>
            <?php endif; ?>
        </a>
        <a href="logout" class="btn-logout">Keluar</a>
    </div>
</nav>

<div class="container">
    <div class="page-head">
        <h1>Profil Saya</h1>
        <p>Kelola informasi akun dan keamanan kamu</p>
    </div>

    <!-- ALERT NOTIFICATIONS -->
    <?php if ($error): ?>
    <div class="alert-msg" style="max-width: 1000px; margin: 0 auto 20px; background: rgba(248,113,113,0.1); border: 1px solid rgba(248,113,113,0.2); color: #f87171; padding: 12px 20px; border-radius: 12px; font-size: 0.85rem; display: flex; align-items: center; gap: 12px; animation: slideDown 0.3s ease; transition: opacity 0.5s ease-out, transform 0.5s ease-out;">
        <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
        <span><?php echo $error; ?></span>
    </div>
    <?php endif; ?>

    <?php if ($success): ?>
    <div class="alert-msg" style="max-width: 1000px; margin: 0 auto 20px; background: rgba(16,185,129,0.1); border: 1px solid rgba(16,185,129,0.2); color: #10b981; padding: 12px 20px; border-radius: 12px; font-size: 0.85rem; display: flex; align-items: center; gap: 12px; animation: slideDown 0.3s ease; transition: opacity 0.5s ease-out, transform 0.5s ease-out;">
        <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <span><?php echo $success; ?></span>
    </div>
    <?php endif; ?>

    <style>
        @keyframes slideDown { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
    </style>

    <div class="grid-profile">

        <!-- SIDEBAR KIRI -->
        <div class="profile-sidebar">
            <div class="avatar-card">
                <form method="POST" id="fotoForm">
                    <input type="hidden" name="action" value="upload_foto">
                    <input type="hidden" name="cropped_image" id="croppedImageInput">
                    <input type="file" id="fotoInput" accept="image/jpeg,image/png,image/webp" style="display:none">
                
                    <div class="avatar-action-wrapper" id="avatarActionWrapper">
                        <div class="avatar-wrap" onclick="toggleAvatarMenu(event)" title="Opsi Foto Profil" style="cursor:pointer">
                            <div id="avatarDisplay">
                                <?php if (!empty($user['foto_profil']) && file_exists($user['foto_profil'])): ?>
                                <img src="<?php echo htmlspecialchars($user['foto_profil']); ?>?v=<?php echo time(); ?>" class="avatar" id="avatarImg" alt="Foto Profil">
                                <?php else: ?>
                                <div class="avatar-initials" id="avatarImg"><?php echo $initials; ?></div>
                                <?php endif; ?>
                            </div>
                            <img id="avatarPreview" class="avatar" style="display:none" alt="Preview">
                            <div class="avatar-overlay">
                                <svg width="18" height="18" fill="none" stroke="white" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            </div>
                        </div>
                        <div class="avatar-menu" id="avatarMenu">
                            <div class="avatar-menu-item" onclick="openLightbox()">
                                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                Lihat Foto
                            </div>
                            <div class="avatar-menu-item" onclick="document.getElementById('fotoInput').click(); hideAvatarMenu();">
                                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                Ganti Foto
                            </div>
                        </div>
                    </div>

                </form>

                <div class="avatar-name">
                    @<?php echo htmlspecialchars($user['username']); ?>
                    <?php if (!empty($user['is_vip']) && $user['is_vip'] == 1): ?>
                    <span class="vip-badge">
                        <svg width="9" height="9" viewBox="0 0 20 20" fill="currentColor"><path d="M10 1l2.39 5.26L18 7.27l-4 3.89.94 5.5L10 14l-4.94 2.66.94-5.5L2 7.27l5.61-.01L10 1z"/></svg>
                        VIP
                    </span>
                    <?php endif; ?>
                </div>
                <div class="avatar-email"><?php echo htmlspecialchars($user['email']); ?></div>
                <div class="avatar-role">Pengguna</div>

                <?php if (!empty($user['foto_profil']) && file_exists($user['foto_profil'])): ?>
                <form method="POST" class="upload-form-inline">
                    <input type="hidden" name="action" value="remove_foto">
                    <button type="submit" class="btn-remove-foto">Hapus Foto Profil</button>
                </form>
                <?php endif; ?>
            </div>

            <!-- INFO SINGKAT -->
            <div class="info-card">
                <div class="info-item">
                    <div class="info-label">Bergabung sejak</div>
                    <div class="info-value"><?php echo date('d M Y', strtotime($user['created_at'])); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Login terakhir</div>
                    <div class="info-value">
                        <?php echo !empty($user['last_login']) ? date('d M Y, H:i', strtotime($user['last_login'])) : 'Sesi ini'; ?>
                    </div>
                </div>
                <div class="info-item">
                    <div class="info-label">Status akun</div>
                    <div class="info-value" style="color:var(--green)">● Aktif</div>
                </div>
            </div>

            <!-- VIP STATUS CARD -->
            <?php if (!empty($user['is_vip']) && $user['is_vip'] == 1): ?>
            <div class="vip-status-card">
                <div class="vip-card-top">
                    <div class="vip-card-icon">
                        <svg width="18" height="18" viewBox="0 0 20 20" fill="#000"><path d="M10 1l2.39 5.26L18 7.27l-4 3.89.94 5.5L10 14l-4.94 2.66.94-5.5L2 7.27l5.61-.01L10 1z"/></svg>
                    </div>
                    <div>
                        <div class="vip-card-label">Status Member</div>
                        <div class="vip-card-title">VIP Fast Track</div>
                    </div>
                </div>
                <div class="vip-card-perk">
                    <svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                    Skip Waiting Room aktif
                </div>
                <div class="vip-card-perk">
                    <svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                    Badge VIP aktif di profil
                </div>
                <div class="vip-card-perk">
                    <svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                    Prioritas War Tiket
                </div>
            </div>
            <?php else: ?>
            <div class="not-vip-card">
                <div class="not-vip-info">Upgrade ke <strong style="color:#ffd700">VIP Fast Track</strong> untuk skip antrean di semua event dan dapatkan badge eksklusif.</div>
                <a href="buy_vip" class="btn-get-vip">
                    <svg width="13" height="13" viewBox="0 0 20 20" fill="currentColor"><path d="M10 1l2.39 5.26L18 7.27l-4 3.89.94 5.5L10 14l-4.94 2.66.94-5.5L2 7.27l5.61-.01L10 1z"/></svg>
                    Upgrade ke VIP
                </a>
            </div>
            <?php endif; ?>
        </div>

        <!-- PANEL KANAN -->
        <div class="panels-stack">

            <!-- INFO AKUN -->
            <div class="panel">
                <div class="panel-header">
                    <div class="panel-header-icon">
                        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                    </div>
                    <div>
                        <div class="panel-title">Informasi Akun</div>
                        <div class="panel-sub">Nama dan nomor telepon yang ditampilkan</div>
                    </div>
                </div>
                <div class="panel-body">
                    <form method="POST" id="formInfo" novalidate>
                        <input type="hidden" name="action" value="update_info">
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Nama Lengkap</label>
                                <input type="text" name="nama" class="form-input" value="<?php echo htmlspecialchars($user['nama']); ?>" required placeholder="Nama lengkap kamu">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Username</label>
                                <input type="text" name="username" class="form-input" value="<?php echo htmlspecialchars($user['username'] ?? ''); ?>" required placeholder="Username unik">
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Nomor Telepon</label>
                            <input type="tel" name="no_telepon" class="form-input" value="<?php echo htmlspecialchars($user['no_telepon'] ?? ''); ?>" placeholder="081234567890">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Alamat Email</label>
                            <input type="email" class="form-input" value="<?php echo htmlspecialchars($user['email']); ?>" disabled>
                            <div class="form-hint">Email tidak bisa diubah.</div>
                        </div>
                        <button type="submit" id="btnSaveInfo" class="btn-save">Simpan Perubahan</button>
                    </form>
                </div>
            </div>

            <!-- UBAH PASSWORD -->
            <div class="panel">
                <div class="panel-header">
                    <div class="panel-header-icon">
                        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                    </div>
                    <div>
                        <div class="panel-title">Ubah Password</div>
                        <div class="panel-sub">Pastikan minimal 8 karakter</div>
                    </div>
                </div>
                <div class="panel-body">
                    <form method="POST" id="formPass" novalidate>
                        <input type="hidden" name="action" value="update_password">
                        <div class="form-group">
                            <label class="form-label">Password Saat Ini</label>
                            <input type="password" name="current_password" class="form-input" required placeholder="Masukkan password lama">
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Password Baru</label>
                                <input type="password" name="new_password" id="newPass" class="form-input" required placeholder="Min. 8 karakter">
                                <div id="passHint" style="color:#f87171; font-size:0.7rem; margin-top:5px; display:none; animation: pulse 2s infinite;">Password minimal 8 karakter.</div>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Konfirmasi Password</label>
                                <input type="password" name="confirm_password" id="confirmPass" class="form-input" required placeholder="Ulangi password baru">
                            </div>
                        </div>
                        <button type="submit" id="btnSavePass" class="btn-save">Update Password</button>
                    </form>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- MODAL CROP -->
<div class="modal-backdrop" id="cropModal">
    <div class="modal-box" style="max-width:490px">
        <button class="modal-close" onclick="closeCropModal()">
            <svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
        <div class="modal-title">Sesuaikan Foto Profil</div>
        <p style="font-size:0.78rem;color:var(--muted);margin-bottom:16px;margin-top:-10px;">Geser dan zoom untuk menyesuaikan posisi foto yang akan ditampilkan.</p>

        <div style="position:relative;width:100%;height:310px;background:#07090f;border-radius:12px;overflow:hidden;margin-bottom:14px;">
            <img id="cropImage" style="display:block;max-width:100%;">
        </div>

        <div style="display:flex;gap:8px;margin-bottom:14px;flex-wrap:wrap;">
            <button type="button" onclick="cropper.zoom(0.1)" class="crop-tool-btn" title="Zoom In">
                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v3m0 0v3m0-3h3m-3 0H7"/></svg>
                Perbesar
            </button>
            <button type="button" onclick="cropper.zoom(-0.1)" class="crop-tool-btn" title="Zoom Out">
                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM7 10h6"/></svg>
                Perkecil
            </button>
            <button type="button" onclick="cropper.rotate(-90)" class="crop-tool-btn" title="Putar Kiri">
                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                Putar
            </button>
            <button type="button" onclick="cropper.reset()" class="crop-tool-btn" style="margin-left:auto">
                Reset
            </button>
        </div>

        <button type="button" onclick="saveCrop(this)" class="btn-upload-submit" style="margin-bottom:0">
            Terapkan &amp; Simpan Foto
        </button>
    </div>
</div>

<!-- LIGHTBOX -->
<div class="lightbox" id="lightbox" onclick="closeLightbox()">
    <img src="" class="lightbox-img" id="lightboxImg">
</div>

<style>
    .crop-tool-btn {
        display: flex; align-items: center; gap: 5px;
        padding: 7px 12px;
        border-radius: 8px;
        background: var(--surface-alpha);
        border: 1px solid var(--border-alpha);
        color: var(--muted);
        cursor: pointer;
        font-size: 0.73rem;
        font-family: 'Inter', sans-serif;
        transition: all 0.2s;
    }
    .crop-tool-btn:hover { color: var(--accent); background: var(--accent-dim); }
    /* Cropper lingkaran */
    .cropper-view-box, .cropper-face { border-radius: 50%; }
    .cropper-view-box { outline: 2px solid var(--accent); }
    .cropper-point { background-color: var(--accent); opacity: 0; }
    .cropper-line { background-color: var(--accent-dim); }
    .cropper-dashed { border-color: var(--accent-dim); }
    #cropModal .modal-box { background: var(--surface); }
    #cropModal .modal-backdrop { background: rgba(0,0,0,0.85); }
</style>

<script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.js"></script>

<script>
    const fotoInput = document.getElementById('fotoInput');
    const avatarPreview = document.getElementById('avatarPreview');
    const avatarDisplay = document.getElementById('avatarDisplay');
    const uploadActions = document.getElementById('uploadActions');
    const cropImage = document.getElementById('cropImage');
    let cropper = null;

    fotoInput.addEventListener('change', function() {
        if (this.files && this.files[0]) {
            const reader = new FileReader();
            reader.onload = e => {
                cropImage.src = e.target.result;
                openCropModal();
            };
            reader.readAsDataURL(this.files[0]);
        }
    });

    function openCropModal() {
        document.getElementById('cropModal').classList.add('open');
        document.body.style.overflow = 'hidden';

        // Destroy cropper lama dulu jika ada
        if (cropper) { cropper.destroy(); cropper = null; }

        setTimeout(() => {
            cropper = new Cropper(cropImage, {
                aspectRatio: 1,
                viewMode: 1,
                dragMode: 'move',
                autoCropArea: 0.85,
                restore: false,
                guides: false,
                center: false,
                highlight: false,
                cropBoxMovable: false,
                cropBoxResizable: false,
                toggleDragModeOnDblclick: false,
                ready: function() {
                    // Update preview immediately when ready
                    updateLivePreview();
                },
                crop: function() {
                    // Update preview as user move/zoom
                    updateLivePreview();
                }
            });
        }, 100);
    }

    function updateLivePreview() {
        if (!cropper) return;
        const canvas = cropper.getCroppedCanvas({ width: 200, height: 200 });
        const dataUrl = canvas.toDataURL('image/jpeg');
        
        // Find the current avatar image/initials and replace content
        const avatarWrap = document.querySelector('.avatar-wrap');
        let previewImg = document.getElementById('livePreviewImg');
        
        if (!previewImg) {
            previewImg = document.createElement('img');
            previewImg.id = 'livePreviewImg';
            previewImg.className = 'avatar';
            previewImg.style.position = 'absolute';
            previewImg.style.top = '0';
            previewImg.style.left = '0';
            previewImg.style.zIndex = '5';
            avatarWrap.appendChild(previewImg);
        }
        
        previewImg.src = dataUrl;
        previewImg.style.display = 'block';
    }

    function closeCropModal() {
        document.getElementById('cropModal').classList.remove('open');
        document.body.style.overflow = '';
        const previewImg = document.getElementById('livePreviewImg');
        if (previewImg) previewImg.style.display = 'none';
        if (cropper) { cropper.destroy(); cropper = null; }
    }

    // ── AVATAR MENU & LIGHTBOX ──
    const avatarMenu = document.getElementById('avatarMenu');
    function toggleAvatarMenu(e) {
        e.stopPropagation();
        // Cek apakah sudah ada foto (tag IMG di dalam avatarDisplay)
        const hasPhoto = document.querySelector('#avatarDisplay img');
        if (!hasPhoto) {
            document.getElementById('fotoInput').click();
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
        const currentImg = document.getElementById('avatarImg');
        
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

    function saveCrop(btn) {
        if (!cropper) return;

        const originalText = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span style="display:flex;align-items:center;justify-content:center;gap:8px;"><svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round" style="animation:spin 1s linear infinite"><line x1="12" y1="2" x2="12" y2="6"></line><line x1="12" y1="18" x2="12" y2="22"></line><line x1="4.93" y1="4.93" x2="7.76" y2="7.76"></line><line x1="16.24" y1="16.24" x2="19.07" y2="19.07"></line><line x1="2" y1="12" x2="6" y2="12"></line><line x1="18" y1="12" x2="22" y2="12"></line><line x1="4.93" y1="19.07" x2="7.76" y2="16.24"></line><line x1="16.24" y1="7.76" x2="19.07" y2="4.93"></line></svg> Menyimpan...</span>';

        const canvas = cropper.getCroppedCanvas({ width: 400, height: 400, imageSmoothingQuality: 'high' });
        const base64 = canvas.toDataURL('image/jpeg', 0.88);

        // Simpan base64 ke hidden input
        const input = document.getElementById('croppedImageInput');
        if (input) {
            input.value = base64;
            // Submit form
            document.getElementById('fotoForm').submit();
        } else {
            console.error('Input croppedImageInput tidak ditemukan!');
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    }

    // Tambahkan animasi spin di style
    const style = document.createElement('style');
    style.innerHTML = '@keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }';
    document.head.appendChild(style);

    document.getElementById('cropModal').addEventListener('click', e => {
        if (e.target === document.getElementById('cropModal')) closeCropModal();
    });

    // --- FORM VALIDATION ---
    // 1. Info Form
    const formInfo = document.getElementById('formInfo');
    const btnInfo = document.getElementById('btnSaveInfo');
    const infoInputs = formInfo ? formInfo.querySelectorAll('input:not([type="hidden"]):not([disabled])') : [];

    function valInfo() {
        if(!btnInfo) return;
        let isFilled = true;
        let isChanged = false;

        infoInputs.forEach(i => { 
            if(i.required && !i.value.trim()) isFilled = false;
            // Dirty check: bandingkan dengan nilai asli saat load
            if(i.value !== i.defaultValue) isChanged = true;
        });

        btnInfo.disabled = !(isFilled && isChanged);
    }
    if(formInfo) formInfo.addEventListener('input', valInfo);
    valInfo();

    // 2. Pass Form
    const formPass = document.getElementById('formPass');
    const btnPass = document.getElementById('btnSavePass');
    const newPass = document.getElementById('newPass');
    const confirmPass = document.getElementById('confirmPass');
    const passHint = document.getElementById('passHint');
    const passRequired = formPass ? formPass.querySelectorAll('input[required]') : [];

    function valPass() {
        if(!btnPass) return;
        let ok = true;
        passRequired.forEach(i => { if(!i.value.trim()) ok = false; });
        
        let isLongEnough = true;
        let isMatch = true;
        if(newPass) {
            isLongEnough = newPass.value.length >= 8;
            isMatch = newPass.value === confirmPass.value;
            
            if (newPass.value.length > 0 && !isLongEnough) {
                if(passHint) passHint.style.display = 'block';
            } else {
                if(passHint) passHint.style.display = 'none';
            }
        }
        btnPass.disabled = !(ok && isLongEnough && isMatch);
    }
    if(formPass) formPass.addEventListener('input', valPass);
    valPass();

    // THEME TOGGLE SCRIPT (EXACT MERGE WITH DASHBOARD)
    const themeToggle = document.getElementById('themeToggle');
    const sunIcon = document.getElementById('sunIcon');
    const moonIcon = document.getElementById('moonIcon');

    function updateIcons(theme) {
        if (!themeToggle || !sunIcon || !moonIcon) return;
        if (theme === 'light') {
            sunIcon.style.display = 'block';
            moonIcon.style.display = 'none';
        } else {
            sunIcon.style.display = 'none';
            moonIcon.style.display = 'block';
        }
    }

    if (themeToggle) {
        updateIcons(document.documentElement.getAttribute('data-theme') || 'dark');
        themeToggle.addEventListener('click', () => {
            const currentTheme = document.documentElement.getAttribute('data-theme') || 'dark';
            const newTheme = currentTheme === 'light' ? 'dark' : 'light';
            
            document.documentElement.setAttribute('data-theme', newTheme);
            localStorage.setItem('theme', newTheme);
            updateIcons(newTheme);
        });
    }

    // AUTO HIDE ALERTS
    const alerts = document.querySelectorAll('.alert-msg');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.opacity = '0';
            alert.style.transform = 'translateY(-10px)';
            setTimeout(() => {
                alert.style.display = 'none';
            }, 500);
        }, 3000);
    });
</script>

</body>
</html>
