<?php
require_once 'koneksi.php';
try {
    // Kosongkan semua pesanan lama agar tidak nyangkut ke ID user baru
    $conn->exec("DELETE FROM orders");
    
    // Reset status VIP jason jika ingin mulai dari nol, atau biarkan jika memang ingin VIP
    // Saya reset saja dulu supaya bersih
    $conn->prepare("UPDATE users SET is_vip = 0 WHERE email = ?")->execute(['jason@gmail.com']);
    
    echo "Orders table cleared and Jason's VIP status reset.\n";
} catch(Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
