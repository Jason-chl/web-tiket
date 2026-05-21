<?php
require_once 'koneksi.php';
try {
    // 1. Buat tabel vouchers
    $conn->exec("CREATE TABLE IF NOT EXISTS vouchers (
        id_voucher INT AUTO_INCREMENT PRIMARY KEY,
        code VARCHAR(50) UNIQUE NOT NULL,
        discount_amount INT NOT NULL,
        id_event INT NOT NULL,
        max_usage INT DEFAULT 100,
        current_usage INT DEFAULT 0,
        status ENUM('active', 'expired') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (id_event) REFERENCES event(id_event) ON DELETE CASCADE
    )");

    // 2. Tambahkan kolom discount di tabel orders untuk mencatat pemotongan harga
    try {
        $conn->exec("ALTER TABLE orders ADD COLUMN discount INT DEFAULT 0");
    } catch (PDOException $e) {
        // Kolom mungkin sudah ada
    }

    echo "Migration successful: Table 'vouchers' created and 'orders' updated.";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
