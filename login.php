<?php
session_start();
require_once 'koneksi.php';

$error = '';
$info = $_GET['msg'] ?? '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    if (empty($email) || empty($password)) {
        $error = 'Email dan Password wajib diisi.';
    } else {
        $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);

        if ($stmt->rowCount() == 1) {
            $user = $stmt->fetch();
            
            if (password_verify($password, $user['password'])) {
                if ($user['is_active']) {
                    $_SESSION['id_user'] = $user['id_user'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['nama'] = $user['nama'];
                    $_SESSION['username'] = $user['username'];
                    
                    $stmtUpdate = $conn->prepare("UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE id_user = ?");
                    $stmtUpdate->execute([$user['id_user']]);
                    
                    // Redirect berdasarkan role
                    $target = 'user_dashboard.php';
                    if ($user['role'] === 'admin') $target = 'admin_dashboard.php';
                    elseif ($user['role'] === 'petugas') $target = 'petugas_dashboard.php';
                    
                    header("Location: " . $target);
                    exit;
                } else {
                    $error = 'Akun Anda tidak aktif. Silakan hubungi admin.';
                }
            } else {
                $error = 'Email atau Password salah.';
            }
        } else {
            $error = 'Email atau Password salah.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Tiket Konser</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Outfit', 'sans-serif'],
                    },
                    colors: {
                        brand: {
                            400: '#a78bfa',
                            500: '#6d28d9',
                            600: '#5b21b6',
                        }
                    }
                }
            }
        }
    </script>
    <script>
        // IMMEDATE THEME LOAD
        (function() {
            const savedTheme = localStorage.getItem('theme') || 'dark';
            document.documentElement.setAttribute('data-theme', savedTheme);
        })();
    </script>
    <style>
        :root {
            --bg: #07090f;
            --surface: rgba(13, 17, 23, 0.85);
            --text-heading: #ffffff;
            --text-body: #e2e8f0;
            --text-label: #94a3b8;
            --input-bg: rgba(255, 255, 255, 0.04);
            --input-border: rgba(255, 255, 255, 0.08);
            --accent: #a78bfa;
        }

        [data-theme="light"] {
            --bg: #f3f4f6;
            --surface: rgba(255, 255, 255, 0.95);
            --text-heading: #111827;
            --text-body: #374151;
            --text-label: #6b7280;
            --input-bg: #ffffff;
            --input-border: #e5e7eb;
            --accent: #6d28d9;
        }

        body { 
            background-color: var(--bg); 
            background-image: url('https://images.unsplash.com/photo-1540039155732-68b2dbceaebd?q=80&w=2674&auto=format&fit=crop');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            transition: background-color 0.3s; 
        }
        .glass-panel { 
            background-color: var(--surface); 
            border: 1px solid var(--input-border); 
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
        }
        .input-glass { 
            background-color: var(--input-bg); 
            border: 1px solid var(--input-border); 
            color: var(--text-heading); 
            transition: all 0.3s ease;
        }
        .input-glass:focus {
            background: rgba(255, 255, 255, 0.1);
            border-color: rgba(155, 135, 245, 0.5);
            outline: none;
            box-shadow: 0 0 0 2px rgba(155, 135, 245, 0.25);
        }
        h1, .text-white { color: var(--text-heading) !important; }
        p, .text-slate-400 { color: var(--text-body) !important; }
        label, .text-slate-300 { color: var(--text-label) !important; }

        /* THEME TOGGLE FLOATING */
        .theme-toggle-float {
            position: fixed; top: 20px; right: 20px; z-index: 100;
            width: 44px; height: 44px; border-radius: 50%; background: var(--surface);
            border: 1px solid var(--input-border); display: flex; align-items: center; justify-content: center;
            cursor: pointer; color: var(--text-heading); transition: 0.3s; box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .theme-toggle-float:hover { transform: scale(1.1); border-color: #a78bfa; }

        /* ALERT COLORS THEME AWARE */
        .alert-error { background: rgba(239, 68, 68, 0.15); border: 1px solid rgba(239, 68, 68, 0.4); color: #fecaca; }
        [data-theme="light"] .alert-error { background: rgba(239, 68, 68, 0.1); border-color: rgba(239, 68, 68, 0.3); color: #b91c1c; }
        
        .alert-info { background: rgba(126, 105, 171, 0.15); border: 1px solid rgba(126, 105, 171, 0.4); color: #d8b4fe; }
        [data-theme="light"] .alert-info { background: rgba(126, 105, 171, 0.1); border-color: rgba(126, 105, 171, 0.3); color: #6d28d9; }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-4 antialiased text-slate-200">
    <div class="absolute inset-0 pointer-events-none theme-overlay"></div>
    <style>
        .theme-overlay {
            background: 
                repeating-linear-gradient(45deg, rgba(255, 255, 255, 0.03) 0px, rgba(255, 255, 255, 0.03) 1px, transparent 1px, transparent 15px),
                linear-gradient(to bottom right, rgba(15, 23, 42, 0.9), rgba(88, 28, 135, 0.5));
        }
        [data-theme="light"] .theme-overlay { 
            background: 
                repeating-linear-gradient(45deg, rgba(0, 0, 0, 0.07) 0px, rgba(0, 0, 0, 0.07) 1px, transparent 1px, transparent 15px),
                linear-gradient(135deg, #ffffff 0%, rgba(155, 135, 245, 0.15) 100%) !important; 
        }
        
        /* Premium Button Style for Light Mode - Ultra Smooth */
        [data-theme="light"] button[type="submit"] {
            position: relative;
            overflow: hidden;
            background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%) !important;
            box-shadow: 0 15px 35px -12px rgba(99, 102, 241, 0.5) !important;
            border: none !important;
            color: #ffffff !important;
            font-weight: 600 !important;
            z-index: 1;
            transition: all 0.4s ease !important;
        }
        
        [data-theme="light"] button[type="submit"]::before {
            content: "";
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background: linear-gradient(135deg, #7c3aed 0%, #6366f1 100%);
            opacity: 0;
            z-index: -1;
            transition: opacity 0.5s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        [data-theme="light"] button[type="submit"]:hover::before {
            opacity: 1;
        }

        [data-theme="light"] button[type="submit"]:hover {
            box-shadow: 0 15px 35px -12px rgba(99, 102, 241, 0.5) !important;
        }
    </style>

    <div class="glass-panel w-full max-w-md p-8 rounded-3xl shadow-2xl relative z-10 transform transition-all hover:scale-[1.01] duration-300">
        <div class="text-center mb-10">
            <h1 class="text-4xl font-bold text-white mb-2 tracking-tight">Tix<span class="text-brand-400">Now</span></h1>
            <p class="text-slate-400 font-light">Selamat datang kembali!</p>
        </div>

        <?php if ($error): ?>
        <div class="alert-msg bg-red-500/10 border border-red-500/20 text-red-400 px-4 py-3 rounded-xl text-sm mb-6 flex items-center gap-3 animate-shake transition-all duration-500">
            <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
            <span><?php echo $error; ?></span>
        </div>
        <?php endif; ?>

        <?php if (isset($_GET['status']) && $_GET['status'] === 'success'): ?>
        <div class="alert-msg bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 px-4 py-3 rounded-xl text-sm mb-6 flex items-center gap-3 transition-all duration-500">
            <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <span>Registrasi Berhasil! Silakan Login.</span>
        </div>
        <?php endif; ?>

        <form method="POST" action="" class="space-y-6" novalidate>
            <div>
                <label class="block text-sm font-medium text-slate-300 mb-1.5" for="email">Alamat Email</label>
                <input type="email" name="email" id="email" required class="input-glass w-full px-4 py-3.5 rounded-xl" placeholder="Masukkan email Anda">
            </div>

            <div>
                <div class="flex justify-between items-center mb-1.5">
                    <label class="block text-sm font-medium text-slate-300" for="password">Password</label>
                    <a href="#" class="text-xs text-brand-400 hover:text-brand-300 transition-colors">Lupa password?</a>
                </div>
                <input type="password" name="password" id="password" required class="input-glass w-full px-4 py-3.5 rounded-xl" placeholder="••••••••">
            </div>

            <button type="submit" id="submitBtn" disabled class="w-full bg-brand-500 hover:bg-brand-400 disabled:bg-slate-700 disabled:opacity-50 disabled:cursor-not-allowed text-white font-medium py-3.5 px-4 rounded-xl transition-all duration-300 transform active:scale-95 shadow-lg shadow-brand-500/30 mt-2">
                Masuk
            </button>
        </form>

        <p class="mt-8 text-center text-slate-400 text-sm">
            Belum punya akun? 
            <a href="register" class="text-brand-400 hover:text-brand-300 font-medium transition-colors">Daftar di sini</a>
        </p>
    </div>

    <!-- THEME TOGGLE -->
    <div class="theme-toggle-float" id="themeToggle" title="Pindah Tema">
        <svg id="moonIcon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path></svg>
        <svg id="sunIcon" style="display:none;" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"></circle><line x1="12" y1="1" x2="12" y2="3"></line><line x1="12" y1="21" x2="12" y2="23"></line><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line><line x1="1" y1="12" x2="3" y2="12"></line><line x1="21" y1="12" x2="23" y2="12"></line><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line></svg>
    </div>

    <script>
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

        updateIcons(document.documentElement.getAttribute('data-theme'));

        themeToggle.addEventListener('click', () => {
            const currentTheme = document.documentElement.getAttribute('data-theme');
            const newTheme = currentTheme === 'light' ? 'dark' : 'light';
            
            document.documentElement.setAttribute('data-theme', newTheme);
            localStorage.setItem('theme', newTheme);
            updateIcons(newTheme);
        });

        // FORM VALIDATION
        const form = document.querySelector('form');
        const submitBtn = document.getElementById('submitBtn');
        const inputs = form.querySelectorAll('input[required]');

        function validateForm() {
            let ok = true;
            inputs.forEach(i => { if(!i.value.trim()) ok = false; });
            submitBtn.disabled = !ok;
        }

        form.addEventListener('input', validateForm);
        validateForm();

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
    <script>
        // Auto-dismiss alert after 4 seconds
        document.addEventListener('DOMContentLoaded', () => {
            const alert = document.querySelector('.bg-red-500\\/10, .bg-blue-500\\/10');
            if (alert) {
                setTimeout(() => {
                    alert.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
                    alert.style.opacity = '0';
                    alert.style.transform = 'translateY(-10px)';
                    setTimeout(() => alert.remove(), 500);
                }, 4000);
            }
        });
    </script>
</body>
</html>
