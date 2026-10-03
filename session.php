<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| SESSION JGC TAKSIR
|--------------------------------------------------------------------------
*/

if (session_status() !== PHP_SESSION_ACTIVE) {

    $isHttps = (
        isset($_SERVER['HTTPS']) &&
        $_SERVER['HTTPS'] !== 'off'
    );

    session_name('JGC_TAKSIR_SESSION');

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);

    session_start();
}


/*
|--------------------------------------------------------------------------
| SESSION TIMEOUT
|--------------------------------------------------------------------------
*/

const SESSION_TIMEOUT = 7200;


/*
|--------------------------------------------------------------------------
| CEK SESSION TIMEOUT
|--------------------------------------------------------------------------
*/

if (isset($_SESSION['user_id'])) {

    if (
        isset($_SESSION['last_activity']) &&
        (time() - $_SESSION['last_activity']) > SESSION_TIMEOUT
    ) {

        $_SESSION = [];

        if (ini_get('session.use_cookies')) {

            $params = session_get_cookie_params();

            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        session_destroy();

    } else {

        $_SESSION['last_activity'] = time();
    }
}


/*
|--------------------------------------------------------------------------
| CEK APAKAH USER SUDAH LOGIN
|--------------------------------------------------------------------------
*/

function isLoggedIn(): bool
{
    return isset($_SESSION['user_id']);
}


/*
|--------------------------------------------------------------------------
| WAJIB LOGIN
|--------------------------------------------------------------------------
*/

function requireLogin(): void
{
    if (!isLoggedIn()) {

        header('Location: ../auth/login.php');

        exit;
    }
}


/*
|--------------------------------------------------------------------------
| USER ID
|--------------------------------------------------------------------------
*/

function currentUserId(): ?int
{
    if (!isset($_SESSION['user_id'])) {
        return null;
    }

    return (int) $_SESSION['user_id'];
}


/*
|--------------------------------------------------------------------------
| USERNAME
|--------------------------------------------------------------------------
*/



/*
|--------------------------------------------------------------------------
| NAMA USER
|--------------------------------------------------------------------------
|
| HANYA ADA SATU currentUserName()
|
*/

function currentUserName(): string
{
    return $_SESSION['full_name'] ?? '';
}


/*
|--------------------------------------------------------------------------
| ROLE USER
|--------------------------------------------------------------------------
*/

function currentUserRole(): string
{
    return $_SESSION['role'] ?? '';
}


/*
|--------------------------------------------------------------------------
| CEK ROLE
|--------------------------------------------------------------------------
*/

function requireRole(array $allowedRoles): void
{
    requireLogin();

    $role = currentUserRole();

    if (!in_array($role, $allowedRoles, true)) {

        http_response_code(403);

        echo '
        <!DOCTYPE html>
        <html lang="id">

        <head>
            <meta charset="UTF-8">
            <meta name="viewport"
                  content="width=device-width, initial-scale=1">

            <title>Akses Ditolak</title>

            <link
                href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
                rel="stylesheet"
            >
        </head>

        <body class="bg-light">

        <div class="container py-5">

            <div class="card shadow-sm border-0">

                <div class="card-body text-center p-5">

                    <h3 class="text-danger">
                        Akses Ditolak
                    </h3>

                    <p class="text-secondary">
                        Anda tidak memiliki izin
                        untuk mengakses halaman ini.
                    </p>

                    <a
                        href="../admin/appraisal.php"
                        class="btn btn-primary"
                    >
                        Kembali
                    </a>

                </div>

            </div>

        </div>

        </body>
        </html>
        ';

        exit;
    }
}


/*
|--------------------------------------------------------------------------
| LOGOUT
|--------------------------------------------------------------------------
*/

function logoutUser(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {

        $params = session_get_cookie_params();

        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();
}