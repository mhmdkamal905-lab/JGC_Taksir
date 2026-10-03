<?php

require_once __DIR__ . '/../config/db.php';

$username = 'admin';
$fullName = 'Administrator JGC';
$password = 'admin123';

$passwordHash = password_hash(
    $password,
    PASSWORD_DEFAULT
);

$stmt = $pdo->prepare("
    INSERT INTO users
    (
        username,
        full_name,
        password_hash,
        role,
        is_active
    )
    VALUES
    (?, ?, ?, 'admin', 1)
");

try {

    $stmt->execute([
        $username,
        $fullName,
        $passwordHash
    ]);

    echo '<h3>User admin berhasil dibuat.</h3>';

    echo '<p>Username: <b>admin</b></p>';
    echo '<p>Password: <b>admin123</b></p>';

    echo '<p>
        Setelah berhasil, hapus file
        <b>create_admin.php</b>
        demi keamanan.
    </p>';

} catch (PDOException $e) {

    echo 'Gagal membuat user: ' .
        htmlspecialchars($e->getMessage());
}