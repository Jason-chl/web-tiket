<?php
require_once 'koneksi.php';
$users = [
    ['jason', 'jason@gmail.com', 'Jason Utama'],
    ['bot_war1', 'bot1@gmail.com', 'TixBot Alpha'],
    ['bot_war2', 'bot2@gmail.com', 'TixBot Beta'],
    ['bot_war3', 'bot3@gmail.com', 'TixBot Gamma'],
    ['bot_war4', 'bot4@gmail.com', 'TixBot Delta'],
    ['bot_war5', 'bot5@gmail.com', 'TixBot Epsilon'],
    ['bot_war6', 'bot6@gmail.com', 'TixBot Zeta'],
    ['bot_war7', 'bot7@gmail.com', 'TixBot Eta'],
    ['bot_war8', 'bot8@gmail.com', 'TixBot Theta'],
    ['bot_war9', 'bot9@gmail.com', 'TixBot Iota'],
    ['bot_war10', 'bot10@gmail.com', 'TixBot Kappa'],
];

$pass = password_hash('password123', PASSWORD_DEFAULT);

foreach ($users as $u) {
    try {
        $stmt = $conn->prepare("INSERT IGNORE INTO users (username, email, password, nama, role) VALUES (?, ?, ?, ?, 'user')");
        $stmt->execute([$u[0], $u[1], $pass, $u[2]]);
    } catch(Exception $e) {
        echo "Error adding " . $u[0] . ": " . $e->getMessage() . "\n";
    }
}
echo "Akun Jason & 10 User Bot SIAP untuk War Tiket!\n";
?>
