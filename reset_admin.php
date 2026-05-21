<?php
require_once 'koneksi.php';
$email = 'admin@tixnow.com';
$password = password_hash('admin123', PASSWORD_DEFAULT);
$stmt = $conn->prepare("UPDATE users SET email = ?, password = ? WHERE role = 'admin' LIMIT 1");
if ($stmt->execute([$email, $password])) {
    echo "Admin credentials updated. Email: $email, Password: admin123\n";
} else {
    echo "Failed to update admin credentials.\n";
}
?>
