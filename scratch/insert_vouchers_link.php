<?php
$files = glob('admin_*.php');
$inject = '
    <a href="admin_vouchers" class="sidebar-item<?php echo basename($_SERVER[\'PHP_SELF\']) == \'admin_vouchers.php\' ? \' active\' : \'\'; ?>">
        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/></svg>
        Voucher
    </a>
    <a href="admin_history"';

foreach ($files as $f) {
    if ($f == 'admin_vouchers.php') continue; // We manually set admin_vouchers active earlier
    
    $content = file_get_contents($f);
    
    // Check if it already has admin_vouchers to avoid duplicates
    if (strpos($content, 'href="admin_vouchers"') !== false) {
        continue;
    }

    $content = str_replace('    <a href="admin_history"', $inject, $content);
    file_put_contents($f, $content);
    echo "Updated $f\n";
}
