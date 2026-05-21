<?php
session_start();
require_once 'koneksi.php';

$error = '';
$success = '';
$showModalError = false;
$modalErrorMessage = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $nama = trim($_POST['nama'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email']);
        $no_telepon = trim($_POST['no_telepon']);
        $password = $_POST['password'];

        if (empty($nama) || empty($email) || empty($password) || empty($username)) {
            $error = 'Nama Lengkap, Username, Email, dan Password wajib diisi.';
        } elseif (strlen($password) < 8) {
            $error = 'Password minimal harus memiliki 8 karakter.';
        } else {
            // Check for duplicates: Email or Phone (Username can be duplicate)
            $queryCheck = "SELECT id_user FROM users WHERE email = ?";
            $paramsCheck = [$email];
            
            if (!empty($no_telepon)) {
                $queryCheck .= " OR no_telepon = ?";
                $paramsCheck[] = $no_telepon;
            }
        
        $stmt = $conn->prepare($queryCheck);
        $stmt->execute($paramsCheck);
        
        if ($stmt->rowCount() > 0) {
            $showModalError = true;
            $modalErrorMessage = 'Email atau Nomor Telepon ini sudah terdaftar. Silakan gunakan email/nomor lain atau masuk ke akun Anda.';
        } else {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $stmtInsert = $conn->prepare("INSERT INTO users (email, username, password, nama, no_telepon, role) VALUES (?, ?, ?, ?, ?, 'user')");
            
            if ($stmtInsert->execute([$email, $username, $hashedPassword, $nama, $no_telepon])) {
                // Auto login setelah register
                $newUserId = $conn->lastInsertId();
                $_SESSION['id_user'] = $newUserId;
                $_SESSION['role'] = 'user';
                $_SESSION['nama'] = $nama;
                $_SESSION['username'] = $username;
                
                header("Location: user_dashboard");
                exit;
            } else {
                $error = 'Terjadi kesalahan sistem.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Tiket Konser</title>
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

        .theme-toggle-float:hover { transform: scale(1.1); border-color: #a78bfa; }

        /* ALERT COLORS THEME AWARE */
        .alert-error { background: rgba(239, 68, 68, 0.15); border: 1px solid rgba(239, 68, 68, 0.4); color: #fecaca; }
        [data-theme="light"] .alert-error { background: rgba(239, 68, 68, 0.1); border-color: rgba(239, 68, 68, 0.3); color: #b91c1c; }
        
        .alert-success { background: rgba(16, 185, 129, 0.15); border: 1px solid rgba(16, 185, 129, 0.4); color: #a7f3d0; }
        [data-theme="light"] .alert-success { background: rgba(16, 185, 129, 0.1); border-color: rgba(16, 185, 129, 0.3); color: #047857; }
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
        <div class="text-center mb-8">
            <h1 class="text-4xl font-bold text-white mb-2 tracking-tight">Tix<span class="text-brand-400">Now</span></h1>
            <p class="text-slate-400 font-light">Bergabunglah dan amankan tiket konsermu</p>
        </div>

        <?php if ($error): ?>
            <div class="alert-msg alert-error p-4 rounded-xl mb-6 text-sm flex items-center gap-3 transition-all duration-500">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span><?php echo $error; ?></span>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert-msg alert-success p-4 rounded-xl mb-6 text-sm flex items-center gap-3 transition-all duration-500">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span><?php echo $success; ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" action="" class="space-y-5" novalidate>
            <div>
                <label class="block text-sm font-medium text-slate-300 mb-1.5" for="nama">Nama Lengkap</label>
                <input type="text" name="nama" id="nama" required class="input-glass w-full px-4 py-3 rounded-xl" placeholder="John Doe" value="<?php echo htmlspecialchars($_POST['nama'] ?? ''); ?>">
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-300 mb-1.5" for="username">Username</label>
                <input type="text" name="username" id="username" required class="input-glass w-full px-4 py-3 rounded-xl" placeholder="johndoe123" value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-300 mb-1.5" for="email">Alamat Email</label>
                <input type="email" name="email" id="email" required class="input-glass w-full px-4 py-3 rounded-xl" placeholder="john@example.com" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-300 mb-1.5" for="no_telepon">Nomor Telepon</label>
                <input type="tel" name="no_telepon" id="no_telepon" class="input-glass w-full px-4 py-3 rounded-xl" placeholder="081234567890" value="<?php echo htmlspecialchars($_POST['no_telepon'] ?? ''); ?>">
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-300 mb-1.5" for="password">Password</label>
                <input type="password" name="password" id="password" required class="input-glass w-full px-4 py-3 rounded-xl" placeholder="Minimal 8 karakter">
                <div id="passwordHint" class="text-[11px] text-red-400 mt-1.5 hidden animate-pulse">
                    Password minimal harus 8 karakter.
                </div>
            </div>

            <button type="submit" id="submitBtn" disabled class="w-full bg-brand-500 hover:bg-brand-400 disabled:bg-slate-700 disabled:opacity-50 disabled:cursor-not-allowed text-white font-medium py-3 px-4 rounded-xl transition-all duration-300 transform active:scale-95 shadow-lg shadow-brand-500/30 mt-4">
                Daftar Sekarang
            </button>
        </form>

        <p class="mt-8 text-center text-slate-400 text-sm">
            Sudah punya akun? 
            <a href="login" class="text-brand-400 hover:text-brand-300 font-medium transition-colors">Masuk di sini</a>
        </p>
    </div>

    <?php if ($showModalError): ?>
    <div id="errorModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/80 backdrop-blur-sm">
        <div class="glass-panel w-full max-w-sm rounded-3xl p-6 relative text-center">
            <div class="w-16 h-16 bg-red-500/20 rounded-full flex items-center justify-center mx-auto mb-4 border border-red-500/30">
                <svg class="w-8 h-8 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
            </div>
            <h3 class="text-xl font-bold text-white mb-2">Pendaftaran Gagal</h3>
            <p class="text-slate-400 text-sm mb-6"><?php echo $modalErrorMessage; ?></p>
            <button onclick="document.getElementById('errorModal').remove()" class="w-full bg-slate-700 hover:bg-slate-600 text-white font-medium py-3 px-4 rounded-xl transition-colors">
                Mengerti
            </button>
        </div>
    </div>
    <?php endif; ?>

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

        // FORM VALIDATION LOGIC
        const form = document.querySelector('form');
        const submitBtn = document.getElementById('submitBtn');
        const passInput = document.getElementById('password');
        const passHint = document.getElementById('passwordHint');
        const inputs = form.querySelectorAll('input[required]');

        function validateForm() {
            let allFilled = true;
            inputs.forEach(input => {
                if (!input.value.trim()) allFilled = false;
            });

            const isPassValid = passInput.value.length >= 8;
            
            // Show/Hide Password Hint
            if (passInput.value.length > 0 && !isPassValid) {
                passHint.classList.remove('hidden');
            } else {
                passHint.classList.add('hidden');
            }

            submitBtn.disabled = !(allFilled && isPassValid);
        }

        form.addEventListener('input', validateForm);
        inputs.forEach(i => i.addEventListener('input', validateForm));
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
