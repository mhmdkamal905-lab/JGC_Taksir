<?php

declare(strict_types=1);

require_once __DIR__ . '/../auth/session.php';
requireLogin();

require_once __DIR__ . '/../config/db.php';

/*
|--------------------------------------------------------------------------
| CSRF TOKEN
|--------------------------------------------------------------------------
*/
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/*
|--------------------------------------------------------------------------
| HELPER
|--------------------------------------------------------------------------
*/
function e($value): string
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
}

function rupiah($number): string
{
    return 'Rp ' . number_format(
        (float) $number,
        0,
        ',',
        '.'
    );
}

/*
|--------------------------------------------------------------------------
| DEFAULT VARIABLE
|--------------------------------------------------------------------------
*/
$error = '';
$result = null;

/*
|--------------------------------------------------------------------------
| DEFAULT FORM
|--------------------------------------------------------------------------
*/
$form = [
    'category'           => '',
    'brand'              => '',
    'model'              => '',
    'new_price'          => '',
    'age_years'          => '',
    'ram_gb'             => 0,
    'storage_gb'         => 0,
    'physical_condition' => 'Sangat Baik',
    'functionality'      => 'Normal',
    'completeness'       => 'Lengkap'
];

/*
|--------------------------------------------------------------------------
| PROSES PREDIKSI
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /*
    |--------------------------------------------------------------------------
    | VALIDASI CSRF
    |--------------------------------------------------------------------------
    */
    if (
        !isset($_POST['csrf_token']) ||
        !hash_equals(
            $_SESSION['csrf_token'],
            (string) $_POST['csrf_token']
        )
    ) {
        $error = 'Permintaan tidak valid. Silakan muat ulang halaman.';
    } else {

        /*
        |--------------------------------------------------------------------------
        | AMBIL DATA FORM
        |--------------------------------------------------------------------------
        */
        $form = [
            'category'           => trim((string) ($_POST['category'] ?? '')),
            'brand'              => trim((string) ($_POST['brand'] ?? '')),
            'model'              => trim((string) ($_POST['model'] ?? '')),
            'new_price'          => (float) ($_POST['new_price'] ?? 0),
            'age_years'          => (float) ($_POST['age_years'] ?? 0),
            'ram_gb'             => (int) ($_POST['ram_gb'] ?? 0),
            'storage_gb'         => (int) ($_POST['storage_gb'] ?? 0),
            'physical_condition' => trim((string) ($_POST['physical_condition'] ?? '')),
            'functionality'      => trim((string) ($_POST['functionality'] ?? '')),
            'completeness'       => trim((string) ($_POST['completeness'] ?? ''))
        ];

        /*
        |--------------------------------------------------------------------------
        | VALIDASI INPUT
        |--------------------------------------------------------------------------
        */
        if (
            $form['category'] === '' ||
            $form['brand'] === '' ||
            $form['model'] === '' ||
            $form['new_price'] <= 0 ||
            $form['age_years'] < 0 ||
            $form['ram_gb'] < 0 ||
            $form['storage_gb'] < 0
        ) {
            $error = 'Periksa kembali data barang yang dimasukkan.';
        } else {

            /*
            |--------------------------------------------------------------------------
            | DATA UNTUK MACHINE LEARNING API
            |--------------------------------------------------------------------------
            */
            $payload = [
                'category'           => $form['category'],
                'brand'              => $form['brand'],
                'model'              => $form['model'],
                'new_price'          => $form['new_price'],
                'age_years'          => $form['age_years'],
                'ram_gb'             => $form['ram_gb'],
                'storage_gb'         => $form['storage_gb'],
                'physical_condition' => $form['physical_condition'],
                'functionality'      => $form['functionality'],
                'completeness'       => $form['completeness']
            ];

            /*
            |--------------------------------------------------------------------------
            | URL MACHINE LEARNING API
            |--------------------------------------------------------------------------
            |
            | Flask:
            | http://127.0.0.1:5000/predict
            |
            */
            $apiUrl = 'http://127.0.0.1:5000/predict';

            $ch = curl_init($apiUrl);

            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,

                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Accept: application/json'
                ],

                CURLOPT_POSTFIELDS => json_encode(
                    $payload,
                    JSON_UNESCAPED_UNICODE
                ),

                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_TIMEOUT        => 30
            ]);

            $response = curl_exec($ch);

            $curlError = curl_error($ch);

            $httpCode = (int) curl_getinfo(
                $ch,
                CURLINFO_HTTP_CODE
            );

            curl_close($ch);

            /*
            |--------------------------------------------------------------------------
            | CEK KONEKSI API
            |--------------------------------------------------------------------------
            */
            if ($response === false) {

                $error =
                    'API Machine Learning tidak dapat dihubungi. ' .
                    'Pastikan Flask sedang berjalan. ' .
                    'Detail: ' . $curlError;

            } else {

                /*
                |--------------------------------------------------------------------------
                | DECODE JSON
                |--------------------------------------------------------------------------
                */
                $prediction = json_decode(
                    $response,
                    true
                );

                if (
                    $httpCode !== 200 ||
                    !is_array($prediction)
                ) {

                    $error =
                        is_array($prediction) &&
                        isset($prediction['error'])
                            ? (string) $prediction['error']
                            : 'Gagal mendapatkan hasil prediksi dari API Machine Learning.';

                } elseif (
                    !isset($prediction['predicted_market_price'])
                ) {

                    $error =
                        'API berhasil dihubungi tetapi nilai prediksi tidak ditemukan.';

                } else {

                    /*
                    |--------------------------------------------------------------------------
                    | NILAI PREDIKSI
                    |--------------------------------------------------------------------------
                    */
                    $marketPrice = (float) $prediction[
                        'predicted_market_price'
                    ];

                    /*
                    |--------------------------------------------------------------------------
                    | VALIDASI HASIL ML
                    |--------------------------------------------------------------------------
                    */
                    if (
                        !is_finite($marketPrice) ||
                        $marketPrice <= 0
                    ) {

                        $error = 'Hasil prediksi Machine Learning tidak valid.';

                    } else {

                        /*
                        |--------------------------------------------------------------------------
                        | KEBIJAKAN SIMULASI JGC
                        |--------------------------------------------------------------------------
                        |
                        | appraisalRate:
                        | 80% dari harga pasar prediksi.
                        |
                        | LTV:
                        | maksimal 75% dari nilai taksiran.
                        |
                        | Sesuaikan dengan kebijakan resmi JGC.
                        |--------------------------------------------------------------------------
                        */
                        $appraisalRate = 0.80;
                        $ltv           = 0.75;

                        $appraisalValue =
                            $marketPrice * $appraisalRate;

                        $estimatedLoan =
                            $appraisalValue * $ltv;

                        /*
                        |--------------------------------------------------------------------------
                        | NAMA MODEL
                        |--------------------------------------------------------------------------
                        */
                        $algorithm =
                            isset($prediction['model'])
                                ? (string) $prediction['model']
                                : 'Machine Learning';

                        /*
                        |--------------------------------------------------------------------------
                        | SIMPAN KE DATABASE
                        |--------------------------------------------------------------------------
                        */
                        try {

                            $stmt = $pdo->prepare("
                                INSERT INTO appraisal_history (
                                    category,
                                    brand,
                                    model,
                                    new_price,
                                    age_years,
                                    ram_gb,
                                    storage_gb,
                                    physical_condition,
                                    functionality,
                                    completeness,
                                    predicted_market_price,
                                    appraisal_value,
                                    estimated_loan,
                                    algorithm
                                ) VALUES (
                                    :category,
                                    :brand,
                                    :model,
                                    :new_price,
                                    :age_years,
                                    :ram_gb,
                                    :storage_gb,
                                    :physical_condition,
                                    :functionality,
                                    :completeness,
                                    :market,
                                    :appraisal,
                                    :loan,
                                    :algorithm
                                )
                            ");

                            $stmt->execute([
                                ':category'           => $form['category'],
                                ':brand'              => $form['brand'],
                                ':model'              => $form['model'],
                                ':new_price'          => $form['new_price'],
                                ':age_years'          => $form['age_years'],
                                ':ram_gb'             => $form['ram_gb'],
                                ':storage_gb'         => $form['storage_gb'],
                                ':physical_condition' => $form['physical_condition'],
                                ':functionality'      => $form['functionality'],
                                ':completeness'       => $form['completeness'],
                                ':market'             => $marketPrice,
                                ':appraisal'          => $appraisalValue,
                                ':loan'               => $estimatedLoan,
                                ':algorithm'          => $algorithm
                            ]);

                            /*
                            |--------------------------------------------------------------------------
                            | HASIL UNTUK DITAMPILKAN
                            |--------------------------------------------------------------------------
                            */
                            $result = [
                                'market'         => $marketPrice,
                                'appraisal'      => $appraisalValue,
                                'loan'           => $estimatedLoan,
                                'algorithm'      => $algorithm,
                                'appraisal_rate' => $appraisalRate * 100,
                                'ltv'            => $ltv * 100
                            ];

                        } catch (PDOException $ex) {

                            $error =
                                'Hasil prediksi berhasil diperoleh, ' .
                                'tetapi gagal disimpan ke database.';
                        }
                    }
                }
            }
        }
    }
}

/*
|--------------------------------------------------------------------------
| DASHBOARD STATISTICS
|--------------------------------------------------------------------------
*/

$totalStmt = $pdo->query("
    SELECT COUNT(*) AS total
    FROM appraisal_history
");

$totalHistory = (int) (
    $totalStmt->fetch()['total'] ?? 0
);

$todayStmt = $pdo->query("
    SELECT COUNT(*) AS total
    FROM appraisal_history
    WHERE DATE(created_at) = CURDATE()
");

$totalToday = (int) (
    $todayStmt->fetch()['total'] ?? 0
);

$latestStmt = $pdo->query("
    SELECT *
    FROM appraisal_history
    ORDER BY id DESC
    LIMIT 5
");

$latestHistory = $latestStmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <title>
        Prediksi Taksiran | Joy Gadai Cemerlang
    </title>

    <!-- Bootstrap -->
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <!-- Bootstrap Icons -->
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
        rel="stylesheet"
    >

    <style>

        :root {
            --primary: #123c35;
            --primary-light: #e8f2ef;
            --background: #f5f7f9;
            --text: #24332f;
            --success: #16734d;
        }

        * {
            box-sizing: border-box;
        }

        body {
            background: var(--background);
            color: var(--text);
            font-family: "Segoe UI", sans-serif;
        }

        /* SIDEBAR */

        .sidebar {
            width: 250px;
            min-height: 100vh;

            position: fixed;

            top: 0;
            left: 0;

            background: var(--primary);

            color: white;

            padding: 25px 18px;

            z-index: 1000;
        }

        .brand {
            font-size: 19px;
            font-weight: 700;

            margin-bottom: 35px;
        }

        .sidebar a {
            display: flex;

            align-items: center;

            gap: 12px;

            color: #dce9e5;

            text-decoration: none;

            padding: 12px 14px;

            border-radius: 9px;

            margin-bottom: 7px;

            transition: .2s;
        }

        .sidebar a:hover,
        .sidebar a.active {
            background: rgba(255,255,255,.14);

            color: white;
        }

        /* MAIN */

        .main {
            margin-left: 250px;

            padding: 30px;
        }

        .topbar {
            background: white;

            padding: 17px 22px;

            border-radius: 13px;

            margin-bottom: 25px;

            display: flex;

            justify-content: space-between;

            align-items: center;

            gap: 15px;

            box-shadow:
                0 3px 15px rgba(20,40,35,.045);
        }

        /* CARD */

        .stat-card,
        .content-card {
            background: white;

            border: 0;

            border-radius: 14px;

            box-shadow:
                0 3px 15px rgba(20,40,35,.045);
        }

        .stat-card {
            padding: 22px;

            height: 100%;
        }

        .stat-icon {
            width: 45px;
            height: 45px;

            border-radius: 12px;

            background: var(--primary-light);

            color: var(--primary);

            display: grid;

            place-items: center;

            font-size: 21px;
        }

        .stat-value {
            font-size: 25px;

            font-weight: 700;

            margin-top: 12px;
        }

        /* FORM */

        .section-title {
            font-size: 17px;

            font-weight: 700;
        }

        .form-label {
            font-size: 13px;

            font-weight: 600;

            color: #46564f;
        }

        .form-control,
        .form-select {
            min-height: 43px;

            border-color: #e0e7e4;

            border-radius: 8px;

            transition: .2s;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: #4b8878;

            box-shadow:
                0 0 0 .2rem rgba(35,110,90,.10);
        }

        /* BUTTON */

        .btn-primary-custom {
            background: var(--primary);

            border: none;

            color: white;

            border-radius: 8px;

            padding: 11px 18px;

            transition: .2s;
        }

        .btn-primary-custom:hover {
            background: #1b574b;

            color: white;

            transform: translateY(-1px);
        }

        /* RESULT */

        .result-panel {
            background: #edf6f1;

            border: 1px solid #d9eade;

            border-radius: 12px;

            padding: 22px;
        }

        .result-price {
            color: #16734d;

            font-size: 27px;

            font-weight: 800;

            margin-top: 5px;
        }

        .loan-price {
            color: #126343;

            font-size: 30px;

            font-weight: 800;

            margin-top: 5px;
        }

        .result-box {
            background: white;

            border-radius: 10px;

            padding: 15px;

            height: 100%;
        }

        .result-box small {
            color: #65736e;
        }

        /* LOADING */

        #loadingBox {
            display: none;

            background: #f0f7f4;

            border: 1px solid #d7e9e2;

            border-radius: 10px;

            padding: 14px;

            margin-top: 20px;
        }

        /* TABLE */

        .table thead th {
            background: #f5f8f6;

            color: #53625b;

            font-size: 12px;

            text-transform: uppercase;

            white-space: nowrap;
        }

        .table td {
            vertical-align: middle;

            font-size: 13px;
        }

        /* BADGE */

        .badge-soft {
            background: #e8f2ef;

            color: #126343;

            border-radius: 20px;

            padding: 6px 10px;

            font-size: 11px;
        }

        /* MOBILE */

        @media (max-width: 991px) {

            .sidebar {
                position: static;

                width: 100%;

                min-height: auto;
            }

            .main {
                margin-left: 0;

                padding: 18px;
            }

            .sidebar nav {
                display: flex;

                flex-wrap: wrap;
            }

            .sidebar nav a {
                flex: 1;

                min-width: 180px;
            }

        }

        @media (max-width: 576px) {

            .topbar {
                align-items: flex-start;

                flex-direction: column;
            }

            .loan-price {
                font-size: 25px;
            }

            .result-price {
                font-size: 23px;
            }

        }

    </style>

</head>

<body>

<!-- =========================================================
     SIDEBAR
========================================================= -->

<aside class="sidebar">

    <div class="brand">

        <i class="bi bi-gem me-2"></i>

        JOY GADAI

        <div class="small fw-normal opacity-75 mt-1">
            Cemerlang • Sistem Taksiran
        </div>

    </div>

    <div class="small text-uppercase opacity-50 mb-2">
        Menu Utama
    </div>

    <nav>

        <a
            href="appraisal.php"
            class="active"
        >

            <i class="bi bi-calculator"></i>

            Prediksi Taksiran

        </a>

        <a href="appraisal_history.php">

            <i class="bi bi-clock-history"></i>

            Riwayat Taksiran

        </a>

    </nav>

    <div class="mt-5 pt-3 border-top border-light border-opacity-25">

        <a
            href="../auth/logout.php"
            onclick="return confirm('Apakah Anda yakin ingin logout?')"
        >

            <i class="bi bi-box-arrow-right"></i>

            Logout

        </a>

    </div>

</aside>


<!-- =========================================================
     MAIN
========================================================= -->

<main class="main">

    <!-- TOPBAR -->

    <div class="topbar">

        <div>

            <h5 class="fw-bold mb-1">
                Dashboard Taksiran
            </h5>

            <small class="text-secondary">
                Sistem Prediksi Nilai Barang Jaminan
            </small>

        </div>

        <div class="text-end">

            <div class="fw-semibold">
                <?= e(currentUserName()) ?>
            </div>

            <small class="text-secondary">
                <?= e(currentUserRole()) ?>
            </small>

        </div>

    </div>


    <!-- HEADER -->

    <div class="mb-4">

        <h4 class="fw-bold mb-1">
            Selamat Datang di Sistem Taksiran
        </h4>

        <p class="text-secondary mb-0">

            Lakukan estimasi nilai pasar barang jaminan
            menggunakan Machine Learning.

        </p>

    </div>


    <!-- ERROR -->

    <?php if ($error): ?>

        <div class="alert alert-danger">

            <i class="bi bi-exclamation-circle me-2"></i>

            <?= e($error) ?>

        </div>

    <?php endif; ?>


    <!-- STATISTICS -->

    <div class="row g-3 mb-4">

        <div class="col-md-6">

            <div class="stat-card">

                <div class="stat-icon">

                    <i class="bi bi-clipboard-data"></i>

                </div>

                <div class="text-secondary small mt-3">
                    Total Riwayat Taksiran
                </div>

                <div class="stat-value">

                    <?= number_format(
                        $totalHistory,
                        0,
                        ',',
                        '.'
                    ) ?>

                </div>

            </div>

        </div>


        <div class="col-md-6">

            <div class="stat-card">

                <div class="stat-icon">

                    <i class="bi bi-calendar-check"></i>

                </div>

                <div class="text-secondary small mt-3">
                    Taksiran Hari Ini
                </div>

                <div class="stat-value">

                    <?= number_format(
                        $totalToday,
                        0,
                        ',',
                        '.'
                    ) ?>

                </div>

            </div>

        </div>

    </div>


    <!-- =====================================================
         FORM PREDIKSI
    ====================================================== -->

    <div class="content-card p-4 mb-4">

        <div class="d-flex justify-content-between align-items-center mb-4">

            <div>

                <h5 class="section-title mb-1">

                    <i class="bi bi-cpu me-2"></i>

                    Form Prediksi Barang

                </h5>

                <small class="text-secondary">

                    Masukkan spesifikasi dan kondisi barang
                    jaminan.

                </small>

            </div>

            <span class="badge-soft">

                <i class="bi bi-robot me-1"></i>

                Machine Learning

            </span>

        </div>


        <form
            method="POST"
            id="predictionForm"
        >

            <input
                type="hidden"
                name="csrf_token"
                value="<?= e($_SESSION['csrf_token']) ?>"
            >


            <div class="row g-3">

                <!-- KATEGORI -->

                <div class="col-md-4">

                    <label class="form-label">
                        Kategori Barang
                    </label>

                    <select
                        name="category"
                        id="category"
                        class="form-select"
                        required
                    >

                        <option value="">
                            Pilih kategori
                        </option>

                        <option
                            value="HP"
                            <?= $form['category'] === 'HP'
                                ? 'selected'
                                : '' ?>
                        >
                            HP
                        </option>

                        <option
                            value="Laptop"
                            <?= $form['category'] === 'Laptop'
                                ? 'selected'
                                : '' ?>
                        >
                            Laptop
                        </option>

                        <option
                            value="Tablet"
                            <?= $form['category'] === 'Tablet'
                                ? 'selected'
                                : '' ?>
                        >
                            Tablet
                        </option>

                        <option
                            value="Kamera"
                            <?= $form['category'] === 'Kamera'
                                ? 'selected'
                                : '' ?>
                        >
                            Kamera
                        </option>

                    </select>

                </div>


                <!-- MEREK -->

                <div class="col-md-4">

                    <label class="form-label">
                        Merek
                    </label>

                    <input
                        type="text"
                        name="brand"
                        class="form-control"
                        placeholder="Contoh: Samsung"
                        value="<?= e($form['brand']) ?>"
                        required
                    >

                </div>


                <!-- MODEL -->

                <div class="col-md-4">

                    <label class="form-label">
                        Model Barang
                    </label>

                    <input
                        type="text"
                        name="model"
                        class="form-control"
                        placeholder="Contoh: Galaxy S23"
                        value="<?= e($form['model']) ?>"
                        required
                    >

                </div>


                <!-- HARGA BARU -->

                <div class="col-md-4">

                    <label class="form-label">
                        Harga Baru (Rp)
                    </label>

                    <input
                        type="number"
                        name="new_price"
                        class="form-control"
                        min="1"
                        step="1"
                        placeholder="Contoh: 12000000"
                        value="<?= e($form['new_price']) ?>"
                        required
                    >

                    <div class="form-text">
                        Masukkan harga baru barang saat ini.
                    </div>

                </div>


                <!-- USIA -->

                <div class="col-md-4">

                    <label class="form-label">
                        Usia Barang (Tahun)
                    </label>

                    <input
                        type="number"
                        name="age_years"
                        class="form-control"
                        min="0"
                        max="30"
                        step="0.1"
                        placeholder="Contoh: 2"
                        value="<?= e($form['age_years']) ?>"
                        required
                    >

                </div>


                <!-- RAM -->

                <div class="col-md-2">

                    <label class="form-label">
                        RAM (GB)
                    </label>

                    <input
                        type="number"
                        name="ram_gb"
                        class="form-control"
                        min="0"
                        value="<?= e($form['ram_gb']) ?>"
                    >

                </div>


                <!-- STORAGE -->

                <div class="col-md-2">

                    <label class="form-label">
                        Storage (GB)
                    </label>

                    <input
                        type="number"
                        name="storage_gb"
                        class="form-control"
                        min="0"
                        value="<?= e($form['storage_gb']) ?>"
                    >

                </div>


                <!-- KONDISI FISIK -->

                <div class="col-md-4">

                    <label class="form-label">
                        Kondisi Fisik
                    </label>

                    <select
                        name="physical_condition"
                        class="form-select"
                        required
                    >

                        <?php
                        $conditions = [
                            'Sangat Baik',
                            'Baik',
                            'Cukup',
                            'Rusak Ringan',
                            'Rusak Berat'
                        ];
                        ?>

                        <?php foreach ($conditions as $condition): ?>

                            <option
                                value="<?= e($condition) ?>"
                                <?= $form['physical_condition'] === $condition
                                    ? 'selected'
                                    : '' ?>
                            >
                                <?= e($condition) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <!-- FUNGSI -->

                <div class="col-md-4">

                    <label class="form-label">
                        Kondisi Fungsi
                    </label>

                    <select
                        name="functionality"
                        class="form-select"
                        required
                    >

                        <?php
                        $functions = [
                            'Normal',
                            'Bermasalah',
                            'Rusak'
                        ];
                        ?>

                        <?php foreach ($functions as $function): ?>

                            <option
                                value="<?= e($function) ?>"
                                <?= $form['functionality'] === $function
                                    ? 'selected'
                                    : '' ?>
                            >
                                <?= e($function) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <!-- KELENGKAPAN -->

                <div class="col-md-4">

                    <label class="form-label">
                        Kelengkapan
                    </label>

                    <select
                        name="completeness"
                        class="form-select"
                        required
                    >

                        <option
                            value="Lengkap"
                            <?= $form['completeness'] === 'Lengkap'
                                ? 'selected'
                                : '' ?>
                        >
                            Lengkap
                        </option>

                        <option
                            value="Tidak Lengkap"
                            <?= $form['completeness'] === 'Tidak Lengkap'
                                ? 'selected'
                                : '' ?>
                        >
                            Tidak Lengkap
                        </option>

                    </select>

                </div>

            </div>


            <!-- LOADING -->

            <div id="loadingBox">

                <div class="d-flex align-items-center gap-3">

                    <div
                        class="spinner-border spinner-border-sm text-success"
                        role="status"
                    ></div>

                    <div>

                        <div class="fw-semibold">
                            Machine Learning sedang memproses...
                        </div>

                        <small class="text-secondary">
                            Sistem sedang menghitung estimasi harga pasar.
                        </small>

                    </div>

                </div>

            </div>


            <!-- BUTTON -->

            <div class="d-flex flex-wrap gap-2 mt-4">

                <button
                    type="submit"
                    id="submitButton"
                    class="btn btn-primary-custom"
                >

                    <i class="bi bi-cpu me-2"></i>

                    Proses Prediksi

                </button>


                <button
                    type="reset"
                    class="btn btn-outline-secondary"
                    id="resetButton"
                >

                    <i class="bi bi-arrow-counterclockwise me-2"></i>

                    Reset

                </button>


                <a
                    href="appraisal_history.php"
                    class="btn btn-outline-success"
                >

                    <i class="bi bi-clock-history me-2"></i>

                    Lihat Riwayat

                </a>

            </div>

        </form>

    </div>


    <!-- =====================================================
         HASIL PREDIKSI
    ====================================================== -->

    <?php if ($result): ?>

        <div class="content-card p-4 mb-4">

            <div class="d-flex justify-content-between align-items-center mb-3">

                <div>

                    <h5 class="section-title mb-1">

                        <i class="bi bi-graph-up-arrow me-2"></i>

                        Hasil Prediksi Machine Learning

                    </h5>

                    <small class="text-secondary">
                        Estimasi berdasarkan data barang yang dimasukkan.
                    </small>

                </div>

                <span class="badge bg-success">

                    <i class="bi bi-check-circle me-1"></i>

                    Berhasil

                </span>

            </div>


            <div class="result-panel">

                <div class="row g-3">

                    <!-- MARKET -->

                    <div class="col-md-4">

                        <div class="result-box">

                            <small>
                                Prediksi Harga Pasar
                            </small>

                            <div class="result-price">

                                <?= rupiah($result['market']) ?>

                            </div>

                        </div>

                    </div>


                    <!-- APPRAISAL -->

                    <div class="col-md-4">

                        <div class="result-box">

                            <small>
                                Nilai Taksiran Barang
                            </small>

                            <h4 class="fw-bold mt-2 mb-0">

                                <?= rupiah($result['appraisal']) ?>

                            </h4>

                            <small>
                                <?= e($result['appraisal_rate']) ?>%
                                dari harga pasar
                            </small>

                        </div>

                    </div>


                    <!-- LOAN -->

                    <div class="col-md-4">

                        <div class="result-box">

                            <small>
                                Estimasi Maksimal Pinjaman
                            </small>

                            <div class="loan-price">

                                <?= rupiah($result['loan']) ?>

                            </div>

                            <small>
                                LTV <?= e($result['ltv']) ?>%
                            </small>

                        </div>

                    </div>

                </div>

            </div>


            <!-- MODEL -->

            <div class="d-flex justify-content-between flex-wrap mt-3">

                <small class="text-secondary">

                    <i class="bi bi-cpu me-1"></i>

                    Algoritma:

                    <strong>
                        <?= e($result['algorithm']) ?>
                    </strong>

                </small>

                <small class="text-success fw-semibold">

                    <i class="bi bi-database-check me-1"></i>

                    Hasil berhasil disimpan

                </small>

            </div>


            <!-- WARNING -->

            <div class="alert alert-warning mt-3 mb-0 small">

                <i class="bi bi-info-circle me-1"></i>

                Hasil prediksi merupakan estimasi sistem,
                bukan persetujuan pinjaman otomatis.
                Petugas JGC tetap wajib melakukan pemeriksaan
                kondisi fisik dan verifikasi harga pasar barang.

            </div>

        </div>

    <?php endif; ?>


    <!-- =====================================================
         RIWAYAT TERBARU
    ====================================================== -->

    <div class="content-card p-4">

        <div class="d-flex justify-content-between align-items-center mb-3">

            <div>

                <h5 class="section-title mb-1">
                    Riwayat Taksiran Terbaru
                </h5>

                <small class="text-secondary">

                    Lima transaksi taksiran terakhir.

                </small>

            </div>

            <a
                href="appraisal_history.php"
                class="btn btn-sm btn-outline-success"
            >

                Lihat Semua

                <i class="bi bi-arrow-right ms-1"></i>

            </a>

        </div>


        <div class="table-responsive">

            <table class="table table-hover align-middle">

                <thead>

                    <tr>

                        <th>Barang</th>

                        <th>Kategori</th>

                        <th>Harga Pasar</th>

                        <th>Nilai Taksiran</th>

                        <th>Estimasi Pinjaman</th>

                        <th>Tanggal</th>

                    </tr>

                </thead>


                <tbody>

                <?php if (!$latestHistory): ?>

                    <tr>

                        <td
                            colspan="6"
                            class="text-center text-secondary py-4"
                        >

                            <i class="bi bi-inbox fs-3 d-block mb-2"></i>

                            Belum ada riwayat taksiran.

                        </td>

                    </tr>

                <?php else: ?>

                    <?php foreach ($latestHistory as $item): ?>

                        <tr>

                            <td>

                                <div class="fw-semibold">

                                    <?= e(
                                        $item['brand'] .
                                        ' ' .
                                        $item['model']
                                    ) ?>

                                </div>

                            </td>


                            <td>

                                <span class="badge-soft">

                                    <?= e($item['category']) ?>

                                </span>

                            </td>


                            <td>

                                <?= rupiah(
                                    $item['predicted_market_price']
                                ) ?>

                            </td>


                            <td class="fw-semibold">

                                <?= rupiah(
                                    $item['appraisal_value']
                                ) ?>

                            </td>


                            <td class="fw-semibold text-success">

                                <?= rupiah(
                                    $item['estimated_loan']
                                ) ?>

                            </td>


                            <td>

                                <?php
                                $date = strtotime(
                                    (string) $item['created_at']
                                );
                                ?>

                                <?= $date
                                    ? e(date('d/m/Y H:i', $date))
                                    : '-' ?>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>


    <!-- FOOTER -->

    <div class="text-center text-secondary small mt-4">

        Joy Gadai Cemerlang &copy;

        <?= date('Y') ?>

        • Sistem Prediksi Taksiran

    </div>

</main>


<!-- =========================================================
     JAVASCRIPT
========================================================= -->

<script>

document.addEventListener('DOMContentLoaded', function () {

    const form =
        document.getElementById('predictionForm');

    const loadingBox =
        document.getElementById('loadingBox');

    const submitButton =
        document.getElementById('submitButton');

    const resetButton =
        document.getElementById('resetButton');


    /*
    |--------------------------------------------------------------------------
    | LOADING SAAT SUBMIT
    |--------------------------------------------------------------------------
    */

    if (form) {

        form.addEventListener('submit', function () {

            loadingBox.style.display = 'block';

            submitButton.disabled = true;

            submitButton.innerHTML =
                '<span class="spinner-border spinner-border-sm me-2"></span>' +
                'Memproses...';

        });

    }


    /*
    |--------------------------------------------------------------------------
    | RESET FORM
    |--------------------------------------------------------------------------
    */

    if (resetButton) {

        resetButton.addEventListener('click', function () {

            setTimeout(function () {

                document
                    .querySelectorAll('.form-select')
                    .forEach(function (select) {

                        if (select.name === 'physical_condition') {
                            select.value = 'Sangat Baik';
                        }

                        if (select.name === 'functionality') {
                            select.value = 'Normal';
                        }

                        if (select.name === 'completeness') {
                            select.value = 'Lengkap';
                        }

                    });

                document
                    .querySelector('input[name="ram_gb"]')
                    .value = 0;

                document
                    .querySelector('input[name="storage_gb"]')
                    .value = 0;

            }, 10);

        });

    }

});

</script>

</body>

</html>