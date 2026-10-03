<?php

declare(strict_types=1);

require_once __DIR__ . '/session.php';
require_once __DIR__ . '/../config/db.php';


/*
|--------------------------------------------------------------------------
| JIKA SUDAH LOGIN
|--------------------------------------------------------------------------
*/

if (isLoggedIn()) {

    header(
        'Location: ../admin/appraisal.php'
    );

    exit;
}


$error = '';

$usernameInput = '';


/*
|--------------------------------------------------------------------------
| PROSES LOGIN
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $usernameInput = trim(
        $_POST['username'] ?? ''
    );

    $passwordInput =
        $_POST['password'] ?? '';


    /*
    |--------------------------------------------------------------------------
    | VALIDASI
    |--------------------------------------------------------------------------
    */

    if (
        $usernameInput === '' ||
        $passwordInput === ''
    ) {

        $error =
            'Username dan password wajib diisi.';

    } else {

        try {

            $stmt = $pdo->prepare("
                SELECT
                    id,
                    username,
                    full_name,
                    password_hash,
                    role,
                    is_active
                FROM users
                WHERE username = ?
                LIMIT 1
            ");

            $stmt->execute([
                $usernameInput
            ]);

            $user = $stmt->fetch();


            /*
            |--------------------------------------------------------------------------
            | VERIFIKASI USER
            |--------------------------------------------------------------------------
            */

            if (
                !$user ||
                !(bool) $user['is_active'] ||
                !password_verify(
                    $passwordInput,
                    $user['password_hash']
                )
            ) {

                $error =
                    'Username atau password salah.';

            } else {

                /*
                |--------------------------------------------------------------------------
                | REGENERATE SESSION
                |--------------------------------------------------------------------------
                */

                session_regenerate_id(true);


                /*
                |--------------------------------------------------------------------------
                | SIMPAN DATA USER
                |--------------------------------------------------------------------------
                */

                $_SESSION['user_id'] =
                    (int) $user['id'];

                $_SESSION['username'] =
                    $user['username'];

                $_SESSION['full_name'] =
                    $user['full_name'];

                $_SESSION['role'] =
                    $user['role'];

                $_SESSION['last_activity'] =
                    time();


                /*
                |--------------------------------------------------------------------------
                | REDIRECT
                |--------------------------------------------------------------------------
                */

                header(
                    'Location: ../admin/appraisal.php'
                );

                exit;
            }

        } catch (PDOException $e) {

            $error =
                'Terjadi kesalahan pada database.';
        }
    }
}

?>
<!DOCTYPE html>

<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Login - JGC Taksir
    </title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <style>

        body {
            min-height: 100vh;
            background:
                linear-gradient(
                    135deg,
                    #0d6efd,
                    #198754
                );

            display: flex;
            align-items: center;
            justify-content: center;
        }

        .login-card {
            width: 100%;
            max-width: 420px;
            border: none;
            border-radius: 18px;
        }

        .logo {
            width: 75px;
            height: 75px;
            border-radius: 50%;
            background: #198754;
            color: white;

            display: flex;
            align-items: center;
            justify-content: center;

            font-size: 26px;
            font-weight: bold;

            margin: auto;
        }

    </style>

</head>

<body>

<div class="container">

    <div class="card login-card shadow-lg mx-auto">

        <div class="card-body p-4 p-md-5">

            <div class="logo mb-3">
                JGC
            </div>

            <div class="text-center mb-4">

                <h3 class="fw-bold">
                    Joy Gadai Cemerlang
                </h3>

                <p class="text-secondary mb-0">
                    Sistem Prediksi Nilai Taksiran
                </p>

            </div>


            <?php if ($error !== ''): ?>

                <div class="alert alert-danger">
                    <?= htmlspecialchars(
                        $error,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </div>

            <?php endif; ?>


            <form
                method="POST"
                action=""
                autocomplete="off"
            >

                <div class="mb-3">

                    <label
                        class="form-label fw-semibold"
                        for="username"
                    >
                        Username
                    </label>

                    <input
                        type="text"
                        class="form-control form-control-lg"
                        id="username"
                        name="username"
                        value="<?= htmlspecialchars(
                            $usernameInput,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                        placeholder="Masukkan username"
                        required
                        autofocus
                    >

                </div>


                <div class="mb-4">

                    <label
                        class="form-label fw-semibold"
                        for="password"
                    >
                        Password
                    </label>

                    <input
                        type="password"
                        class="form-control form-control-lg"
                        id="password"
                        name="password"
                        placeholder="Masukkan password"
                        required
                    >

                </div>


                <button
                    type="submit"
                    class="btn btn-success btn-lg w-100"
                >
                    Login
                </button>

            </form>


            <div class="text-center mt-4">

                <small class="text-secondary">
                    Joy Gadai Cemerlang
                    &copy; <?= date('Y') ?>
                </small>

            </div>

        </div>

    </div>

</div>

</body>

</html>