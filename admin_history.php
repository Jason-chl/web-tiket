<?php
session_start();
require_once 'koneksi.php';
require_once 'cleanup_orders.php';

if (!isset($_SESSION['id_user']) || $_SESSION['role'] !== 'admin') {
    header("Location: index");
    exit;
}

// Stats for Header
// Total Tiket Terjual: Menghitung total qty dari order_detail untuk order yang Berhasil (Paid) atau DP (DP Paid)
$totalTickets = $conn->query("
    SELECT SUM(od.qty) 
    FROM order_detail od 
    JOIN orders o ON od.id_order = o.id_order 
    WHERE o.status IN ('paid', 'dp_paid')
")->fetchColumn() ?: 0;

// Tiket Terpakai: Menghitung tiket yang statusnya sudah 'used' di tabel attendee
$usedTickets = $conn->query("SELECT COUNT(*) FROM attendee WHERE status_checkin = 'used'")->fetchColumn();

// Tiket Aktif: Total Terjual dikurangi yang sudah Terpakai (termasuk tiket DP yang slotnya sudah amannn)
$unusedTickets = $totalTickets - $usedTickets;

// Tiket Dicancel: Menghitung qty tiket dari order yang dibatalkan atau dikembalikan
$cancelledTickets = $conn->query("
    SELECT SUM(od.qty) 
    FROM order_detail od 
    JOIN orders o ON od.id_order = o.id_order 
    WHERE o.status IN ('cancelled', 'refunded')
")->fetchColumn() ?: 0;
$totalIncome = $conn->query("SELECT SUM(amount_paid) FROM orders WHERE status IN ('paid', 'dp_paid')")->fetchColumn() ?: 0;

// Search & Filter
$id_user = $_SESSION['id_user'];
$stmt_admin = $conn->prepare("SELECT * FROM users WHERE id_user = ?");
$stmt_admin->execute([$id_user]);
$admin = $stmt_admin->fetch();
$initials = strtoupper(substr($admin['nama'] ?? 'A', 0, 1));

$search = isset($_GET['search']) ? $_GET['search'] : '';
$statusFilter = $_GET['status'] ?? '';

// Pagination Config
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

$sql = "
    FROM orders o
    JOIN users u ON o.id_user = u.id_user
    JOIN event c ON o.id_event = c.id_event
    WHERE 1=1
";

$params = [];
if ($search) {
    $sql .= " AND (u.nama LIKE ? OR o.order_code LIKE ? OR u.email LIKE ? OR u.username LIKE ? OR c.nama_event LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($statusFilter) {
    if ($statusFilter === 'bought') {
        $sql .= " AND o.status IN ('paid', 'dp_paid')";
    } else {
        $sql .= " AND o.status = ?";
        $params[] = $statusFilter;
    }
}

// Get Total for Pagination
$countStmt = $conn->prepare("SELECT COUNT(*) " . $sql);
$countStmt->execute($params);
$totalItems = $countStmt->fetchColumn();
$totalPages = ceil($totalItems / $limit);

// Final SQL with Limit
$sqlData = "SELECT o.*, u.nama, u.username, u.email, u.is_vip, c.nama_event " . $sql . " ORDER BY o.tanggal_order DESC LIMIT $limit OFFSET $offset";
$stmt = $conn->prepare($sqlData);
$stmt->execute($params);
$orders = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat User - TixNow Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;500;600;700;800&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <!-- Export Libraries -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.25/jspdf.plugin.autotable.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
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
        .page-title { font-family: 'Syne', sans-serif; font-size: 2rem; font-weight: 800; margin-bottom: 8px; }
        .page-sub { color: var(--muted); font-size: 0.95rem; }

        /* STATS */
        .income-banner {
            background: linear-gradient(135deg, #1e1b4b 0%, #312e81 100%);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 28px 36px;
            margin-bottom: 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: relative;
            overflow: hidden;
            box-shadow: 0 20px 40px rgba(0,0,0,0.2);
        }
        [data-theme="light"] .income-banner {
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
            border: none;
            box-shadow: 0 20px 40px rgba(79, 70, 229, 0.2);
        }
        .income-banner::before {
            content: ''; position: absolute; top: -50%; left: -10%; width: 40%; height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            pointer-events: none;
        }
        .income-info h4 { font-size: 0.75rem; color: rgba(255,255,255,0.6); text-transform: uppercase; letter-spacing: 0.12em; margin-bottom: 8px; }
        .income-value-wrapper { display: flex; align-items: center; gap: 15px; }
        .income-value { font-family: 'Syne', sans-serif; font-size: 2.2rem; font-weight: 800; color: #fbbf24; text-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        [data-theme="light"] .income-value { color: #ffffff; }
        
        .btn-hide { background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); color: rgba(255,255,255,0.8); width: 40px; height: 40px; border-radius: 12px; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: 0.3s; }
        .btn-hide:hover { background: rgba(255,255,255,0.2); color: #fff; transform: scale(1.05); }

        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 36px; }

        .stat-card { background: var(--card); border: 1px solid var(--border); border-radius: 14px; padding: 20px 22px; transition: var(--transition); }
        .stat-card:hover { transform: translateY(-5px); box-shadow: 0 10px 25px rgba(0,0,0,0.1); }
        .stat-label { font-size: 0.72rem; font-weight: 600; color: var(--muted); text-transform: uppercase; letter-spacing: 0.08em; margin-bottom: 10px; }
        .stat-value { font-family: 'Syne', sans-serif; font-size: 1.8rem; font-weight: 800; color: var(--text); line-height: 1; }
        [data-theme="light"] .stat-value { color: var(--text); }
        .stat-value.accent { color: var(--accent); }
        .stat-value.green { color: var(--green); }
        .stat-value.red { color: var(--red); }

        /* TABLE AREA */
        .table-container { background: var(--card); border: 1px solid var(--border); border-radius: 16px; position: relative; }
        .filters { padding: 20px; border-bottom: 1px solid var(--border); display: flex; gap: 15px; align-items: center; border-radius: 16px 16px 0 0; position: relative; z-index: 100; }
        .search-box { flex: 1; position: relative; }
        .search-box input { width: 100%; padding: 12px 12px 12px 40px; border-radius: 12px; background: var(--bg); border: 1px solid var(--border); color: var(--text); font-size: 0.85rem; outline: none; transition: 0.3s; }
        .search-box input:focus { border-color: var(--accent); box-shadow: 0 0 0 4px var(--accent-glow); }
        .search-box svg { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: var(--muted); }
        /* CUSTOM SELECT */
        .custom-select-wrapper { position: relative; width: 220px; z-index: 60; }
        .custom-select-trigger {
            padding: 12px 16px; border-radius: 12px; background: var(--bg); border: 1px solid var(--border);
            color: var(--text); font-size: 0.85rem; cursor: pointer; display: flex; align-items: center; justify-content: space-between;
            transition: 0.3s;
        }
        .custom-select-trigger:hover { border-color: var(--accent); }
        .custom-select-trigger.active { border-color: var(--accent); box-shadow: 0 0 0 4px var(--accent-glow); }
        .custom-select-trigger svg { transition: transform 0.3s; opacity: 0.7; }
        .custom-select-trigger.active svg { transform: rotate(180deg); opacity: 1; color: var(--accent); }

        .custom-options {
            position: absolute; top: calc(100% + 8px); left: 0; right: 0;
            background: var(--surface); border: 1px solid var(--border); border-radius: 14px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.4); padding: 8px; display: none;
            backdrop-filter: blur(20px); z-index: 200;
        }
        [data-theme="light"] .custom-options { box-shadow: 0 20px 40px rgba(79, 70, 229, 0.1); }
        .custom-options.show { display: block; animation: selectFadeIn 0.2s ease-out; }

        @keyframes selectFadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .custom-option {
            padding: 10px 12px; border-radius: 8px; font-size: 0.85rem; color: var(--text);
            cursor: pointer; transition: 0.2s; display: flex; items-center; justify-content: space-between;
        }
        .custom-option:hover { background: var(--accent-glow); color: var(--accent); }
        .custom-option.selected { background: var(--accent); color: white; }
        .custom-option.selected:hover { color: white; }

        table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 0.9rem; }
        th { 
            text-align: left; padding: 18px 24px; background: var(--surface-alpha); 
            color: var(--muted); font-weight: 700; font-size: 0.72rem; 
            text-transform: uppercase; letter-spacing: 0.1em; border-bottom: 1px solid var(--border);
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        th:first-child { border-top-left-radius: 0; }
        th:last-child { border-top-right-radius: 0; }
        
        td { padding: 20px 24px; border-bottom: 1px solid var(--border); vertical-align: middle; }
        tr:last-child td:first-child { border-bottom-left-radius: 16px; }
        tr:last-child td:last-child { border-bottom-right-radius: 16px; }
        tr:last-child td { border-bottom: none; }
        tr:hover td { background: rgba(255,255,255,0.015); }
        [data-theme="light"] tr:hover td { background: rgba(0,0,0,0.015); }

        .user-cell { display: flex; align-items: center; gap: 14px; }
        .user-avatar { 
            width: 36px; height: 36px; border-radius: 10px; 
            background: var(--accent-glow); color: var(--accent); 
            display: flex; align-items: center; justify-content: center; 
            font-weight: 700; font-size: 0.85rem; border: 1px solid var(--border);
        }
        .user-name { font-weight: 600; color: var(--header-text); font-size: 0.9rem; }
        .user-email { font-size: 0.78rem; color: var(--muted); margin-top: 2px; }

        .order-code { 
            font-family: 'Space Mono', monospace; font-weight: 700; color: var(--accent); font-size: 0.9rem; letter-spacing: -0.5px; 
            max-width: 140px; display: inline-block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; vertical-align: middle;
        }
        
        .event-name-cell { font-weight: 500; color: var(--text); }
        
        .badge { padding: 6px 14px; border-radius: 8px; font-size: 0.68rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.08em; display: inline-block; }
        .badge.paid { background: rgba(16,185,129,0.1); color: var(--green); border: 1px solid rgba(16,185,129,0.2); }
        .badge.dp_paid { background: rgba(167,139,250,0.1); color: var(--accent); border: 1px solid rgba(167,139,250,0.2); }
        .badge.pending { background: rgba(245,158,11,0.1); color: #f59e0b; border: 1px solid rgba(245,158,11,0.2); }
        .badge.cancelled { background: rgba(239,68,68,0.1); color: var(--red); border: 1px solid rgba(239,68,68,0.2); }
        .badge.completed { background: rgba(16,185,129,0.1); color: var(--green); border: 1px solid rgba(16,185,129,0.2); }
        .badge.expired { background: rgba(255,255,255,0.05); color: var(--muted); border: 1px solid var(--border); }

        .price-text { font-family: 'Space Mono', monospace; font-weight: 700; color: var(--header-text); font-size: 0.95rem; white-space: nowrap; }

        .btn-detail { 
            background: var(--surface-alpha); border: 1px solid var(--border); color: var(--text); 
            width: 38px; height: 38px; border-radius: 10px; font-size: 0.78rem; font-weight: 600; 
            cursor: pointer; transition: 0.2s; text-decoration: none; display: inline-flex; align-items: center; justify-content: center;
        }
        .btn-detail:hover { background: var(--accent); color: #fff; border-color: var(--accent); transform: scale(1.05); }
        .btn-detail svg { width: 18px; height: 18px; }
        .btn-detail.btn-cancel:hover { background: var(--red); border-color: var(--red); }
        .btn-detail.btn-vip.is-vip { border-color: #ffd700; color: #ffd700; }
        .btn-detail.btn-vip.is-vip:hover { background: #ffd700; color: #000; }

        /* EXPORT BUTTONS */
        .btn-export {
            display: flex; align-items: center; gap: 8px; padding: 10px 18px; border-radius: 12px;
            font-size: 0.8rem; font-weight: 700; cursor: pointer; transition: 0.3s; border: 1px solid var(--border);
            color: var(--text); background: var(--bg);
        }
        .btn-export svg { opacity: 0.7; }
        .btn-export:hover { border-color: var(--accent); color: var(--accent); transform: translateY(-2px); box-shadow: 0 4px 12px var(--accent-glow); }
        .btn-export:active { transform: translateY(0); }
        .btn-export.loading { opacity: 0.6; pointer-events: none; }
        .btn-export.pdf:hover { border-color: #f87171; color: #f87171; box-shadow: 0 4px 12px rgba(248,113,113,0.15); }
        .btn-export.excel:hover { border-color: #34d399; color: #34d399; box-shadow: 0 4px 12px rgba(52,211,153,0.15); }


        /* PAGINATION */
        .pagination { display: flex; align-items: center; justify-content: center; gap: 8px; padding: 24px; border-top: 1px solid var(--border); }
        .page-link { 
            width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; 
            border-radius: 10px; background: var(--surface-alpha); border: 1px solid var(--border);
            color: var(--muted); text-decoration: none; font-weight: 600; font-size: 0.85rem; transition: 0.2s;
        }
        .page-link:hover:not(.active) { background: var(--accent-glow); color: var(--accent); border-color: var(--accent); }
        .page-link.active { background: var(--accent); color: #fff; border-color: var(--accent); }
        .page-link.nav { width: auto; padding: 0 16px; gap: 8px; }
        .page-info { font-size: 0.8rem; color: var(--muted); margin-right: auto; }

        /* MODAL */
        .modal { 
            display: none; position: fixed; inset: 0; z-index: 1000; 
            background: rgba(0,0,0,0.85); backdrop-filter: blur(8px); 
            align-items: center; justify-content: center; padding: 20px; 
        }
        .modal.active { display: flex; }
        .modal-content { 
            background: var(--card); border: 1px solid var(--border); border-radius: 24px; 
            width: 100%; max-width: 800px; max-height: 90vh; overflow-y: auto; position: relative;
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5);
        }
        .modal-header { padding: 24px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; }
        .modal-title { font-family: 'Syne', sans-serif; font-size: 1.4rem; font-weight: 700; color: var(--text); }
        .close-modal { background: none; border: none; color: var(--muted); cursor: pointer; padding: 5px; }
        .modal-body { padding: 24px; }

        .ticket-list { display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 20px; }
        .ticket-card { background: var(--bg); border: 1px solid var(--border); border-radius: 16px; padding: 20px; display: flex; flex-direction: column; align-items: stretch; gap: 8px; text-align: left; position: relative; overflow: hidden; }
        .qr-placeholder { width: 100px; height: 100px; background: white; padding: 6px; border-radius: 8px; align-self: center; margin-bottom: 10px; }
        .qr-placeholder img { width: 100%; height: 100%; object-fit: contain; }
        .ticket-code { font-family: 'Space Mono', monospace; font-size: 0.72rem; font-weight: 700; color: var(--muted); background: var(--surface-alpha); padding: 4px 8px; border-radius: 6px; text-align: center; }
        .ticket-status { position: absolute; top: 12px; right: 12px; font-size: 0.6rem; font-weight: 800; text-transform: uppercase; padding: 4px 10px; border-radius: 99px; }
        .status-used { background: rgba(16,185,129,0.1); color: var(--green); }
        .status-active { background: rgba(167,139,250,0.1); color: var(--accent); }

        /* QR ZOOM MODAL */
        .qr-modal { position: fixed; inset: 0; background: rgba(0,0,0,0.92); backdrop-filter: blur(10px); display: none; align-items: center; justify-content: center; z-index: 3000; padding: 40px; cursor: zoom-out; opacity: 0; transition: opacity 0.3s ease; }
        .qr-modal.active { display: flex; opacity: 1; }
        .qr-modal-content { background: white; padding: 24px; border-radius: 24px; box-shadow: 0 0 100px rgba(167,139,250,0.3); transform: scale(0.9); transition: transform 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275); width: 420px; height: 420px; max-width: 90vmin; max-height: 90vmin; display: flex; align-items: center; justify-content: center; }
        .qr-modal.active .qr-modal-content { transform: scale(1); }
        .qr-modal-content img { width: 100%; height: 100%; object-fit: contain; image-rendering: pixelated; }
        .qr-hint { position: absolute; bottom: 40px; color: white; font-size: 0.9rem; font-family: 'Syne', sans-serif; opacity: 0.7; }
        
        @media (max-width: 1024px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
        }
    </style>
</head>
<body>

<aside class="sidebar">
    <div class="sidebar-logo">Tix<span>Now</span></div>
    <div class="sidebar-label">Menu</div>
    <a href="admin_dashboard" class="sidebar-item">
        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
        Dashboard
    </a>
    <a href="admin_profile" class="sidebar-item">
        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
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
    <a href="admin_history" class="sidebar-item active">
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

<main class="main">
    <div class="page-header">
        <h1 class="page-title">Riwayat & Laporan User</h1>
        <p class="page-sub">Pantau aktivitas pembelian, pembatalan, dan penggunaan tiket user.</p>
    </div>

    <div class="income-banner">
        <div class="income-info">
            <h4>Penghasilan Total</h4>
            <div class="income-value-wrapper">
                <div class="income-value" id="incomeValue">Rp <?php echo number_format($totalIncome, 0, ',', '.'); ?></div>
                <button class="btn-hide" id="toggleIncome" title="Sembunyikan/Tampilkan Nominal">
                    <svg id="eyeIcon" width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                    <svg id="eyeOffIcon" style="display:none;" width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.06m.772-1.72A10.014 10.014 0 0112 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-1.447 0-2.811-.384-3.99-1.058M15 12a3 3 0 11-6 0 3 3 0 016 0zM3 14h18"/></svg>
                </button>
            </div>
        </div>
        <div style="text-align:right;">
            <div style="font-size: 0.72rem; color: rgba(255,255,255,0.7); margin-bottom: 4px;">Update Terakhir</div>
            <div style="font-size: 0.8rem; color: #fff; font-weight: 500;"><?php echo date('d M Y, H:i'); ?></div>
        </div>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-label">Total Tiket Terjual</div>
            <div class="stat-value accent"><?php echo number_format($totalTickets, 0, ',', '.'); ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Tiket Terpakai (Used)</div>
            <div class="stat-value green"><?php echo number_format($usedTickets, 0, ',', '.'); ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Tiket Aktif</div>
            <div class="stat-value"><?php echo number_format($unusedTickets, 0, ',', '.'); ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Tiket Dicancel</div>
            <div class="stat-value red"><?php echo number_format($cancelledTickets, 0, ',', '.'); ?></div>
        </div>
    </div>

    <div class="table-container">
        <div class="filters">
            <form action="" method="GET" style="display:contents;">
                <div class="search-box">
                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    <input type="text" name="search" placeholder="Cari nama user, email, atau order code..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="custom-select-wrapper" id="statusFilterWrapper">
                    <input type="hidden" name="status" id="statusInput" value="<?php echo htmlspecialchars($statusFilter); ?>">
                    <div class="custom-select-trigger" id="selectTrigger">
                        <span id="triggerLabel">
                            <?php 
                                switch($statusFilter){
                                    case 'bought': echo 'Berhasil Dibayar'; break;
                                    case 'dp_paid': echo 'Dibayar (DP)'; break;
                                    case 'cancelled': echo 'Dibatalkan/Refund'; break;
                                    case 'pending': echo 'Pending'; break;
                                    case 'expired': echo 'Expired'; break;
                                    default: echo 'Semua Status';
                                }
                            ?>
                        </span>
                        <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </div>
                    <div class="custom-options" id="optionsList">
                        <div class="custom-option <?php echo $statusFilter === '' ? 'selected' : ''; ?>" data-value="">Semua Status</div>
                        <div class="custom-option <?php echo $statusFilter === 'bought' ? 'selected' : ''; ?>" data-value="bought">Berhasil Dibayar</div>
                        <div class="custom-option <?php echo $statusFilter === 'dp_paid' ? 'selected' : ''; ?>" data-value="dp_paid">Dibayar (DP)</div>
                        <div class="custom-option <?php echo $statusFilter === 'cancelled' ? 'selected' : ''; ?>" data-value="cancelled">Dibatalkan/Refund</div>
                        <div class="custom-option <?php echo $statusFilter === 'pending' ? 'selected' : ''; ?>" data-value="pending">Pending</div>
                        <div class="custom-option <?php echo $statusFilter === 'expired' ? 'selected' : ''; ?>" data-value="expired">Expired</div>
                    </div>
                </div>
                <button type="submit" style="display:none;"></button>
            </form>
            <div style="display:flex; gap:10px; margin-left:auto;">
                <button class="btn-export pdf" onclick="exportData('pdf')" id="btnExportPDF">
                    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                    PDF
                </button>
                <button class="btn-export excel" onclick="exportData('excel')" id="btnExportExcel">
                    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    Excel
                </button>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th style="width: 20%;">User</th>
                    <th style="width: 15%;">Order Code</th>
                    <th style="width: 25%;">Konser</th>
                    <th style="width: 15%; text-align: right;">Total / Price</th>
                    <th style="width: 10%; text-align: center;">Status</th>
                    <th style="width: 15%; min-width: 180px; text-align: right;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($orders as $o): ?>
                <tr>
                    <td>
                        <div class="user-cell">
                            <div class="user-avatar"><?php echo strtoupper(substr($o['nama'],0,1)); ?></div>
                            <div>
                                <div class="user-name">
                                    @<?php echo htmlspecialchars($o['username'] ?: $o['nama']); ?>
                                    <?php if($o['is_vip']): ?>
                                        <span style="font-size:0.55rem; background:linear-gradient(45deg, #ffd700, #ffa500); color:#000; padding:2px 6px; border-radius:4px; margin-left:5px; font-weight:800; vertical-align:middle;">VIP</span>
                                    <?php endif; ?>
                                </div>
                                <div class="user-email"><?php echo htmlspecialchars($o['email']); ?></div>
                            </div>
                        </div>
                    </td>
                    <td><span class="order-code" title="<?php echo htmlspecialchars($o['id_order']); ?>"><?php echo htmlspecialchars($o['id_order']); ?></span></td>
                    <td><div class="event-name-cell" title="<?php echo htmlspecialchars($o['nama_event']); ?>"><?php echo htmlspecialchars($o['nama_event']); ?></div></td>
                    <td style="text-align: right;">
                        <div class="price-text" style="display: block; width: 100%; text-align: right;">Rp <?php echo number_format($o['total'],0,',','.'); ?></div>
                    </td>
                    <td style="text-align: center;">
                        <span class="badge <?php echo $o['status']; ?>">
                            <?php echo strtoupper($o['status'] === 'dp_paid' ? 'DP PAID' : $o['status']); ?>
                        </span>
                        <?php if ($o['status'] === 'cancelled' && $o['refund_status'] !== 'none'): ?>
                            <div class="badge <?php echo $o['refund_status']; ?>" style="display:block; margin-top:4px; font-size:0.55rem; padding: 2px 6px;">
                                REFUND: <?php echo strtoupper($o['refund_status']); ?>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div style="display: flex; justify-content: flex-end; gap: 8px; white-space: nowrap;">
                        <button class="btn-detail" onclick="showTickets('<?php echo $o['id_order']; ?>', '<?php echo $o['id_order']; ?>')" title="Lihat Tiket">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                        </button>

                        <?php if (in_array($o['status'], ['paid', 'dp_paid', 'pending'])): ?>
                            <form action="admin_order_action" method="POST" style="display:inline;" onsubmit="return confirm('Apakah Anda yakin ingin membatalkan pesanan ini? Stok akan otomatis bertambah kembali.')">
                                <input type="hidden" name="id_order" value="<?php echo $o['id_order']; ?>">
                                <input type="hidden" name="action" value="cancel">
                                <button type="submit" class="btn-detail btn-cancel" title="Batalkan Pesanan" style="color: var(--red); border-color: var(--red-glow);">
                                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                </button>
                            </form>
                        <?php endif; ?>
                        
                        <form action="admin_vip_action" method="POST" style="display:inline;">
                            <input type="hidden" name="id_user" value="<?php echo $o['id_user']; ?>">
                            <input type="hidden" name="is_vip" value="<?php echo $o['is_vip'] ? '0' : '1'; ?>">
                            <button type="submit" class="btn-detail btn-vip <?php echo $o['is_vip'] ? 'is-vip' : ''; ?>" title="<?php echo $o['is_vip'] ? 'Remove VIP' : 'Make VIP'; ?>">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/></svg>
                            </button>
                        </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($orders)): ?>
                <tr>
                    <td colspan="6" style="text-align: center; padding: 60px 0; color: var(--muted);">
                        <div style="display: flex; flex-direction: column; align-items: center; gap: 12px;">
                            <svg width="48" height="48" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="opacity: 0.2;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <p style="font-size: 0.95rem; font-weight: 500;">Tidak ada riwayat transaksi ditemukan</p>
                            <p style="font-size: 0.8rem; opacity: 0.7;">Coba gunakan kata kunci pencarian atau filter lain</p>
                        </div>
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
        
        <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <div class="page-info">
                Menampilkan <?php echo count($orders); ?> dari <?php echo $totalItems; ?> transaksi
            </div>
            
            <?php if ($page > 1): ?>
                <a href="?page=<?php echo $page-1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($statusFilter); ?>" class="page-link nav">
                    <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                    Previous
                </a>
            <?php endif; ?>

            <?php
                $startPage = max(1, $page - 2);
                $endPage = min($totalPages, $page + 2);
                for($i = $startPage; $i <= $endPage; $i++):
            ?>
                <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($statusFilter); ?>" 
                   class="page-link <?php echo $i === $page ? 'active' : ''; ?>">
                    <?php echo $i; ?>
                </a>
            <?php endfor; ?>

            <?php if ($page < $totalPages): ?>
                <a href="?page=<?php echo $page+1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($statusFilter); ?>" class="page-link nav">
                    Next
                    <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</main>

<!-- Modal Tiket -->
<div class="modal" id="ticketModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title">Tiket untuk Order #<span id="modalOrderCode"></span></h2>
            <button class="close-modal" onclick="closeModal()">
                <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <div class="modal-body">
            <div id="ticketContainer" class="ticket-list">
                <!-- Ajax content here -->
            </div>
        </div>
    </div>
</div>

<!-- QR ZOOM MODAL -->
<div class="qr-modal" id="qrZoomModal" onclick="closeQR()">
    <div class="qr-modal-content" onclick="event.stopPropagation()">
        <img id="zoomedImg" src="" alt="Zoomed QR">
    </div>
    <div class="qr-hint">Klik di mana saja untuk menutup</div>
</div>

<script>
    // ── THEME TOGGLE (GLOBAL) ──
    function updateIcons(theme) {
        const moonIcon = document.getElementById('adminMoonIcon');
        const sunIcon = document.getElementById('adminSunIcon');
        if(!moonIcon || !sunIcon) return;
        moonIcon.style.display = theme === 'light' ? 'none' : 'block';
        sunIcon.style.display = theme === 'light' ? 'block' : 'none';
    }
    function toggleAdminTheme() {
        let current = document.documentElement.getAttribute('data-theme') || 'dark';
        let target = current === 'dark' ? 'light' : 'dark';
        document.documentElement.setAttribute('data-theme', target);
        localStorage.setItem('theme', target);
        updateIcons(target);
    }
    document.addEventListener('DOMContentLoaded', () => {
        updateIcons(document.documentElement.getAttribute('data-theme'));
    });

    // Income Privacy Toggle
    const incomeValue = document.getElementById('incomeValue');
    const toggleIncome = document.getElementById('toggleIncome');
    const eyeIcon = document.getElementById('eyeIcon');
    const eyeOffIcon = document.getElementById('eyeOffIcon');
    const actualValue = "Rp <?php echo number_format($totalIncome, 0, ',', '.'); ?>";
    const maskedValue = "Rp •••••••••";
    let isHidden = false;

    toggleIncome.addEventListener('click', () => {
        isHidden = !isHidden;
        if (isHidden) {
            incomeValue.innerText = maskedValue;
            eyeIcon.style.display = 'none';
            eyeOffIcon.style.display = 'block';
        } else {
            incomeValue.innerText = actualValue;
            eyeIcon.style.display = 'block';
            eyeOffIcon.style.display = 'none';
        }
    });

    function showTickets(orderId, orderCode) {
        document.getElementById('modalOrderCode').innerText = orderCode;
        document.getElementById('ticketContainer').innerHTML = '<p style="text-align:center; grid-column: 1/-1; color: var(--muted);">Loading tiket...</p>';
        document.getElementById('ticketModal').classList.add('active');

        fetch(`admin_get_order_tickets?id_order=${orderId}`)
            .then(res => res.json())
            .then(data => {
                let html = '';
                if (data.length === 0) {
                    html = '<p style="text-align:center; grid-column: 1/-1;">Tidak ada tiket ditemukan.</p>';
                } else {
                    data.forEach(t => {
                        const statusClass = t.status_checkin === 'used' ? 'status-used' : 'status-active';
                        const checkinTime = t.waktu_checkin ? `<div style="font-size: 0.6rem; color: var(--muted);">Used: ${t.waktu_checkin}</div>` : '';
                        const qrUrl = `https://api.qrserver.com/v1/create-qr-code/?size=400x400&data=${t.kode_tiket}`;
                        html += `
                            <div class="ticket-card">
                                <div class="qr-placeholder" onclick="zoomQR('${qrUrl}')" style="cursor:zoom-in;">
                                    <img src="${qrUrl}" alt="QR" style="image-rendering:pixelated;">
                                </div>
                                <div class="ticket-status ${statusClass}">${t.status_checkin.toUpperCase()}</div>
                                <div class="ticket-code">${t.kode_tiket}</div>
                                <div style="margin: 10px 0;">
                                    <div style="font-weight: 700; font-size: 0.85rem; color: var(--header-text);">${t.nama_pemegang}</div>
                                    <div style="font-size: 0.75rem; color: var(--muted);">${t.email_pemegang}</div>
                                    <div style="font-size: 0.75rem; color: var(--muted);">${t.no_telepon_pemegang}</div>
                                </div>
                                <div style="font-size: 0.72rem; color: var(--accent); font-weight: 600; margin-top: auto;">${t.nama_tiket}</div>
                                ${checkinTime}
                            </div>
                        `;
                    });
                }
                document.getElementById('ticketContainer').innerHTML = html;
            });
    }

    const qrZoomModal = document.getElementById('qrZoomModal');
    const zoomedImg = document.getElementById('zoomedImg');

    function zoomQR(url) {
        zoomedImg.src = url;
        qrZoomModal.classList.add('active');
        // Jangan lock body jika modal tiket juga aktif?
        // Sebenarnya lock body bagus agar tidak scroll-chasing.
        document.body.style.overflow = 'hidden';
    }

    function closeQR() {
        qrZoomModal.classList.remove('active');
        // Jika modal tiket masih ada, biarkan tetap ter-lock atau buka kembali jika perlu.
        // Tapi di admin_history, modal tiket tidak mengunci body via overflow secara eksplisit.
    }

    function closeModal() {
        document.getElementById('ticketModal').classList.remove('active');
    }

    // Custom Select Logic
    const selectTrigger = document.getElementById('selectTrigger');
    const optionsList = document.getElementById('optionsList');
    const statusInput = document.getElementById('statusInput');
    const customOptions = document.querySelectorAll('.custom-option');
    const triggerLabel = document.getElementById('triggerLabel');

    selectTrigger.addEventListener('click', (e) => {
        e.stopPropagation();
        selectTrigger.classList.toggle('active');
        optionsList.classList.toggle('show');
    });

    customOptions.forEach(option => {
        option.addEventListener('click', function() {
            const value = this.getAttribute('data-value');
            const label = this.innerText;
            
            statusInput.value = value;
            triggerLabel.innerText = label;
            
            // Re-select UI
            customOptions.forEach(opt => opt.classList.remove('selected'));
            this.classList.add('selected');
            
            optionsList.classList.remove('show');
            selectTrigger.classList.remove('active');
            
            // Auto submit form
            selectTrigger.closest('form').submit();
        });
    });

    // THEME TOGGLE
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
    document.addEventListener('DOMContentLoaded', () => {
        updateAdminIcons(document.documentElement.getAttribute('data-theme'));
        // Auto-dismiss alert
        const alert = document.querySelector('.alert');
        if (alert) setTimeout(() => alert.style.opacity = '0', 3500);
    });

    // ── EXPORT LOGIC ──
    async function exportData(type) {
        const btn = type === 'pdf' ? document.getElementById('btnExportPDF') : document.getElementById('btnExportExcel');
        const originalHTML = btn.innerHTML;
        btn.classList.add('loading');
        btn.innerHTML = `<span class="spinner" style="width:14px; height:14px; border:2px solid currentColor; border-top-color:transparent; border-radius:50%; display:inline-block; animation:spin 0.8s linear infinite;"></span> Exporting...`;

        try {
            // Fetch all filtered data from backend
            const search = "<?php echo urlencode($search); ?>";
            const status = "<?php echo urlencode($statusFilter); ?>";
            const response = await fetch(`admin_export_data?search=${search}&status=${status}`);
            const data = await response.json();

            if (type === 'excel') {
                const ws = XLSX.utils.json_to_sheet(data.map(item => ({
                    'Order ID': item.order_code,
                    'Nama': item.nama,
                    'Email': item.email,
                    'Event': item.nama_event,
                    'Total': item.total,
                    'Dibayar': item.amount_paid,
                    'Status': item.status.toUpperCase(),
                    'Tanggal': item.tanggal_order
                })));
                const wb = XLSX.utils.book_new();
                XLSX.utils.book_append_sheet(wb, ws, "History");
                XLSX.writeFile(wb, `TixNow_History_${new Date().getTime()}.xlsx`);
            } else {
                const { jsPDF } = window.jspdf;
                const doc = new jsPDF('l', 'pt');
                
                doc.setFontSize(18);
                doc.text('Riwayat Transaksi TixNow', 40, 40);
                doc.setFontSize(10);
                doc.setTextColor(100);
                doc.text(`Generated on: ${new Date().toLocaleString()}`, 40, 60);

                const tableData = data.map(item => [
                    item.order_code,
                    item.nama,
                    item.nama_event,
                    `Rp ${parseInt(item.total).toLocaleString('id-ID')}`,
                    item.status.toUpperCase(),
                    item.tanggal_order
                ]);

                doc.autoTable({
                    head: [['Order ID', 'Nama', 'Event', 'Total', 'Status', 'Tanggal']],
                    body: tableData,
                    startY: 80,
                    theme: 'grid',
                    styles: { fontSize: 8, cellPadding: 8 },
                    headStyles: { fillStyle: '#1e1b4b', textColor: '#ffffff' },
                    alternateRowStyles: { fillColor: '#f8fafc' }
                });

                doc.save(`TixNow_History_${new Date().getTime()}.pdf`);
            }
        } catch (err) {
            console.error(err);
            alert('Gagal mengekspor data.');
        } finally {
            btn.classList.remove('loading');
            btn.innerHTML = originalHTML;
        }
    }
</script>

</body>
</html>
