<?php
require_once 'koneksi.php';
try {
    $sql = "CREATE TABLE users (
        id_user INT PRIMARY KEY AUTO_INCREMENT,
        username VARCHAR(50) DEFAULT NULL,
        email VARCHAR(100) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        nama VARCHAR(100) NOT NULL,
        no_telepon VARCHAR(20) DEFAULT NULL,
        role ENUM('admin', 'user') DEFAULT 'user',
        is_active TINYINT(1) DEFAULT 1,
        foto_profil VARCHAR(255) DEFAULT NULL,
        last_login TIMESTAMP NULL DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    $conn->exec($sql);
    echo "Users table created successfully.\n";

    // Insert Default admin
    $adminPass = password_hash('admin123', PASSWORD_DEFAULT);
    $conn->prepare("INSERT INTO users (username, email, password, nama, role) VALUES (?, ?, ?, ?, ?)")
         ->execute(['admin', 'admin@tixnow.com', $adminPass, 'Administrator', 'admin']);
    
    echo "Default admin created: admin@tixnow.com / admin123\n";

} catch(Exception $e) {
    echo "Creation failed: " . $e->getMessage() . "\n";
}
?>
