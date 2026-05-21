<?php
// Just copy and adjust from admin_scan.php logic
$adminScanContent = file_get_contents('c:\xampp\htdocs\UK\tiketkonser\admin_scan.php');

// Replace unauthorized check
$adminScanContent = str_replace(
    "if (!isset(\$_SESSION['id_user']) || \$_SESSION['role'] !== 'admin') {",
    "if (!isset(\$_SESSION['id_user']) || \$_SESSION['role'] !== 'petugas') {",
    $adminScanContent
);

// Replace title
$adminScanContent = str_replace(
    "<title>QR Scanner Admin - TixNow</title>",
    "<title>QR Scanner Petugas - TixNow</title>",
    $adminScanContent
);

// Replace Sidebar links and labels
$adminScanContent = str_replace(
    '<div class="admin-info">',
    '<div class="admin-info"><strong>' . 'Staf' . '</strong>',
    $adminScanContent
);

// We need to properly replace the sidebar for petugas
$petugasSidebar = '
<aside class="sidebar">
    <div class="sidebar-logo">Tix<span>Now</span></div>
    <div class="sidebar-label">Menu</div>
    <a href="petugas_dashboard.php" class="sidebar-item">
        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
        Dashboard
    </a>
    <a href="petugas_scan.php" class="sidebar-item active">
        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/></svg>
        Scan Tiket
    </a>
    <a href="petugas_attendance.php" class="sidebar-item">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
        Daftar Hadir
    </a>
    <div class="sidebar-bottom">
        <div class="theme-toggle-container">
            <span class="theme-label">Tampilan</span>
            <div class="theme-toggle" id="themeToggleAdmin" title="Pindah Tema" onclick="toggleAdminTheme()">
                <svg id="adminMoonIcon" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path></svg>
                <svg id="adminSunIcon" style="display:none;" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="5"></circle><path d="M12 1v2m0 18v2M4.22 4.22l1.42 1.42m12.72 12.72l1.42 1.42M1 12h2m18 0h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"></path></svg>
            </div>
        </div>
        <div class="admin-info">
             <strong><?php echo htmlspecialchars($_SESSION[\'nama\']); ?></strong>
             Staf Lapangan
        </div>
        <a href="logout.php" class="btn-logout">Keluar</a>
    </div>
</aside>';

// We'll just write full content to be safe
$fullContent = '<?php
session_start();
require_once "koneksi.php";
if (!isset($_SESSION["id_user"]) || $_SESSION["role"] !== "petugas") {
    header("Location: index.php");
    exit;
}
?>'. substr($adminScanContent, strpos($adminScanContent, '<!DOCTYPE html>'));

// Fix the sidebar replacement in $fullContent manually or just rewrite the file
// I will rewrite to be cleaner based on the dashboard structure
?>
