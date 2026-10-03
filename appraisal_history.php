<?php

declare(strict_types=1);

require_once __DIR__ . '/../auth/session.php';
requireLogin();

require_once __DIR__ . '/../config/db.php';

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

function formatTanggal($date): string
{
    if (empty($date)) {
        return '-';
    }

    $timestamp = strtotime((string) $date);

    if ($timestamp === false) {
        return e($date);
    }

    return date('d/m/Y H:i', $timestamp);
}

/*
|--------------------------------------------------------------------------
| AMBIL DATA
|--------------------------------------------------------------------------
*/

$histories = [];
$error = '';

try {

    $stmt = $pdo->query("
        SELECT
            id,
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
            algorithm,
            created_at
        FROM appraisal_history
        ORDER BY created_at DESC
        LIMIT 200
    ");

    $histories = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $ex) {

    $error =
        'Data riwayat penaksiran tidak dapat dimuat. ' .
        'Periksa koneksi database.';

}

/*
|--------------------------------------------------------------------------
| STATISTIK
|--------------------------------------------------------------------------
*/

$totalHistory = count($histories);

$totalLoan = 0;

$totalAppraisal = 0;

foreach ($histories as $history) {

    $totalLoan += (float) (
        $history['estimated_loan'] ?? 0
    );

    $totalAppraisal += (float) (
        $history['appraisal_value'] ?? 0
    );
}

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
        Riwayat Penaksiran | Joy Gadai Cemerlang
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


    <!-- DataTables -->

    <link
        rel="stylesheet"
        href="https://cdn.datatables.net/2.1.8/css/dataTables.bootstrap5.min.css"
    >


    <style>

        :root {

            --primary: #123c35;

            --primary-hover: #1b574b;

            --primary-light: #e8f2ef;

            --background: #f5f7f9;

            --text: #24332f;

        }


        * {
            box-sizing: border-box;
        }


        body {

            background: var(--background);

            color: var(--text);

            font-family:
                "Segoe UI",
                Arial,
                sans-serif;

        }


        /* =====================================================
           SIDEBAR
        ===================================================== */

        .sidebar {

            width: 250px;

            min-height: 100vh;

            position: fixed;

            left: 0;

            top: 0;

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


        .brand-subtitle {

            font-size: 12px;

            font-weight: 400;

            opacity: .75;

            margin-top: 4px;

        }


        .menu-title {

            font-size: 11px;

            text-transform: uppercase;

            letter-spacing: .7px;

            opacity: .5;

            margin-bottom: 8px;

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

            background:
                rgba(255,255,255,.14);

            color: white;

        }


        .sidebar-divider {

            border-color:
                rgba(255,255,255,.25);

        }


        /* =====================================================
           MAIN
        ===================================================== */

        .main {

            margin-left: 250px;

            padding: 30px;

        }


        /* =====================================================
           TOPBAR
        ===================================================== */

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
                0 3px 15px
                rgba(20,40,35,.045);

        }


        /* =====================================================
           CARDS
        ===================================================== */

        .stat-card {

            background: white;

            border: 0;

            border-radius: 14px;

            padding: 20px;

            height: 100%;

            box-shadow:
                0 3px 15px
                rgba(20,40,35,.045);

        }


        .content-card {

            background: white;

            border-radius: 14px;

            border: 0;

            box-shadow:
                0 3px 15px
                rgba(20,40,35,.045);

        }


        .stat-icon {

            width: 45px;

            height: 45px;

            border-radius: 12px;

            background: var(--primary-light);

            color: var(--primary);

            display: grid;

            place-items: center;

            font-size: 20px;

        }


        .stat-title {

            color: #6b7873;

            font-size: 13px;

            margin-top: 12px;

        }


        .stat-value {

            font-size: 23px;

            font-weight: 700;

            margin-top: 4px;

        }


        /* =====================================================
           TABLE
        ===================================================== */

        .table {

            margin-bottom: 0;

        }


        .table thead th {

            background: #f5f8f6;

            color: #53625b;

            font-size: 11px;

            text-transform: uppercase;

            letter-spacing: .3px;

            white-space: nowrap;

            border-bottom:
                1px solid #e4ebe8;

        }


        .table tbody td {

            vertical-align: middle;

            font-size: 13px;

            white-space: nowrap;

        }


        .item-name {

            font-weight: 600;

            color: #263b35;

        }


        .item-category {

            font-size: 11px;

            color: #74827d;

        }


        .badge-category {

            background: var(--primary-light);

            color: #126343;

            border-radius: 20px;

            padding: 6px 10px;

            font-size: 11px;

            font-weight: 600;

        }


        .price-market {

            color: #16734d;

            font-weight: 600;

        }


        .price-appraisal {

            font-weight: 700;

        }


        .price-loan {

            color: #126343;

            font-weight: 700;

        }


        .algorithm {

            background: #f1f3f4;

            color: #56635e;

            border-radius: 6px;

            padding: 5px 8px;

            font-size: 11px;

        }


        /* =====================================================
           BUTTON
        ===================================================== */

        .btn-primary-custom {

            background: var(--primary);

            border: none;

            color: white;

            border-radius: 8px;

            padding: 10px 16px;

        }


        .btn-primary-custom:hover {

            background:
                var(--primary-hover);

            color: white;

        }


        /* =====================================================
           EMPTY STATE
        ===================================================== */

        .empty-state {

            padding: 55px 20px;

            text-align: center;

            color: #75817c;

        }


        .empty-state i {

            font-size: 42px;

            opacity: .5;

        }


        /* =====================================================
           DATATABLE
        ===================================================== */

        .dt-search input {

            border-radius: 8px !important;

            border: 1px solid #dce5e1 !important;

            padding: 7px 12px !important;

        }


        .dt-length select {

            border-radius: 7px;

            border: 1px solid #dce5e1;

            padding: 5px 8px;

        }


        .dt-info {

            color: #6d7974 !important;

            font-size: 13px;

        }


        .dt-paging .pagination {

            margin-bottom: 0;

        }


        /* =====================================================
           MOBILE
        ===================================================== */

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

                gap: 5px;

            }


            .sidebar nav a {

                flex: 1;

                min-width: 180px;

            }

        }


        @media (max-width: 576px) {

            .topbar {

                flex-direction: column;

                align-items: flex-start;

            }


            .topbar .text-end {

                text-align: left !important;

            }


            .main {

                padding: 12px;

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

        <div class="brand-subtitle">

            Cemerlang • Sistem Taksiran

        </div>

    </div>


    <div class="menu-title">

        Menu Utama

    </div>


    <nav>

        <a href="appraisal.php">

            <i class="bi bi-calculator"></i>

            Prediksi Taksiran

        </a>


        <a
            href="appraisal_history.php"
            class="active"
        >

            <i class="bi bi-clock-history"></i>

            Riwayat Taksiran

        </a>

    </nav>


    <div class="mt-5 pt-3">

        <hr class="sidebar-divider">


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

                Riwayat Penaksiran

            </h5>

            <small class="text-secondary">

                Data hasil prediksi Machine Learning JGC

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

            Riwayat Taksiran Barang

        </h4>

        <p class="text-secondary mb-0">

            Daftar hasil estimasi nilai pasar,
            nilai taksiran, dan estimasi pinjaman.

        </p>

    </div>


    <!-- ERROR -->

    <?php if ($error): ?>

        <div class="alert alert-danger">

            <i class="bi bi-exclamation-circle me-2"></i>

            <?= e($error) ?>

        </div>

    <?php endif; ?>


    <!-- =====================================================
         STATISTIK
    ====================================================== -->

    <div class="row g-3 mb-4">


        <!-- TOTAL -->

        <div class="col-md-4">

            <div class="stat-card">

                <div class="stat-icon">

                    <i class="bi bi-clipboard-data"></i>

                </div>


                <div class="stat-title">

                    Total Penaksiran

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


        <!-- APPRAISAL -->

        <div class="col-md-4">

            <div class="stat-card">

                <div class="stat-icon">

                    <i class="bi bi-cash-stack"></i>

                </div>


                <div class="stat-title">

                    Total Nilai Taksiran

                </div>


                <div class="stat-value">

                    <?= rupiah($totalAppraisal) ?>

                </div>

            </div>

        </div>


        <!-- LOAN -->

        <div class="col-md-4">

            <div class="stat-card">

                <div class="stat-icon">

                    <i class="bi bi-wallet2"></i>

                </div>


                <div class="stat-title">

                    Total Estimasi Pinjaman

                </div>


                <div class="stat-value">

                    <?= rupiah($totalLoan) ?>

                </div>

            </div>

        </div>

    </div>


    <!-- =====================================================
         TABLE CARD
    ====================================================== -->

    <div class="content-card">


        <!-- CARD HEADER -->

        <div class="p-4 pb-3">

            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">

                <div>

                    <h5 class="fw-bold mb-1">

                        <i class="bi bi-table me-2"></i>

                        Data Riwayat Penaksiran

                    </h5>

                    <small class="text-secondary">

                        Maksimal 200 data penaksiran terakhir.

                    </small>

                </div>


                <div class="d-flex gap-2">

                    <a
                        href="appraisal.php"
                        class="btn btn-primary-custom"
                    >

                        <i class="bi bi-plus-circle me-1"></i>

                        Penaksiran Baru

                    </a>


                    <button
                        type="button"
                        class="btn btn-outline-secondary"
                        onclick="window.print()"
                    >

                        <i class="bi bi-printer me-1"></i>

                        Cetak

                    </button>

                </div>

            </div>

        </div>


        <!-- TABLE -->

        <div class="table-responsive">

            <?php if (!$histories): ?>

                <div class="empty-state">

                    <i class="bi bi-inbox d-block mb-3"></i>

                    <h6 class="fw-bold">

                        Belum Ada Data Penaksiran

                    </h6>

                    <p class="small mb-3">

                        Belum terdapat hasil prediksi
                        Machine Learning yang tersimpan.

                    </p>


                    <a
                        href="appraisal.php"
                        class="btn btn-primary-custom"
                    >

                        <i class="bi bi-calculator me-1"></i>

                        Mulai Penaksiran

                    </a>

                </div>

            <?php else: ?>

                <table
                    id="historyTable"
                    class="table table-hover align-middle"
                    style="width:100%"
                >

                    <thead>

                        <tr>

                            <th>No</th>

                            <th>Tanggal</th>

                            <th>Barang</th>

                            <th>Kategori</th>

                            <th>Harga Baru</th>

                            <th>Harga Pasar</th>

                            <th>Nilai Taksiran</th>

                            <th>Estimasi Pinjaman</th>

                            <th>Algoritma</th>

                        </tr>

                    </thead>


                    <tbody>

                    <?php foreach ($histories as $i => $row): ?>

                        <tr>


                            <!-- NOMOR -->

                            <td>

                                <?= $i + 1 ?>

                            </td>


                            <!-- TANGGAL -->

                            <td>

                                <span class="text-secondary">

                                    <?= formatTanggal(
                                        $row['created_at']
                                    ) ?>

                                </span>

                            </td>


                            <!-- BARANG -->

                            <td>

                                <div class="item-name">

                                    <?= e(
                                        trim(
                                            ($row['brand'] ?? '') .
                                            ' ' .
                                            ($row['model'] ?? '')
                                        )
                                    ) ?>

                                </div>


                                <?php if (!empty($row['physical_condition'])): ?>

                                    <div class="item-category">

                                        Kondisi:

                                        <?= e(
                                            $row['physical_condition']
                                        ) ?>

                                    </div>

                                <?php endif; ?>

                            </td>


                            <!-- KATEGORI -->

                            <td>

                                <span class="badge-category">

                                    <?= e(
                                        $row['category'] ?? '-'
                                    ) ?>

                                </span>

                            </td>


                            <!-- HARGA BARU -->

                            <td>

                                <?= rupiah(
                                    $row['new_price'] ?? 0
                                ) ?>

                            </td>


                            <!-- HARGA PASAR -->

                            <td class="price-market">

                                <?= rupiah(
                                    $row['predicted_market_price'] ?? 0
                                ) ?>

                            </td>


                            <!-- TAKSIRAN -->

                            <td class="price-appraisal">

                                <?= rupiah(
                                    $row['appraisal_value'] ?? 0
                                ) ?>

                            </td>


                            <!-- PINJAMAN -->

                            <td class="price-loan">

                                <?= rupiah(
                                    $row['estimated_loan'] ?? 0
                                ) ?>

                            </td>


                            <!-- ALGORITMA -->

                            <td>

                                <span class="algorithm">

                                    <i class="bi bi-cpu me-1"></i>

                                    <?= e(
                                        $row['algorithm'] ??
                                        'Machine Learning'
                                    ) ?>

                                </span>

                            </td>


                        </tr>

                    <?php endforeach; ?>

                    </tbody>

                </table>

            <?php endif; ?>

        </div>


        <?php if ($histories): ?>

            <div class="p-3 border-top">

                <div class="small text-secondary">

                    <i class="bi bi-info-circle me-1"></i>

                    Data ditampilkan berdasarkan
                    waktu penaksiran terbaru.

                    Hasil Machine Learning tetap harus
                    diverifikasi oleh petugas JGC.

                </div>

            </div>

        <?php endif; ?>


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

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script src="https://cdn.datatables.net/2.1.8/js/dataTables.min.js"></script>

<script src="https://cdn.datatables.net/2.1.8/js/dataTables.bootstrap5.min.js"></script>


<script>

document.addEventListener(
    'DOMContentLoaded',
    function () {

        const table =
            document.getElementById('historyTable');


        if (table) {

            new DataTable(
                '#historyTable',
                {

                    pageLength: 10,

                    lengthMenu: [
                        [10, 25, 50, 100],
                        [10, 25, 50, 100]
                    ],

                    order: [
                        [1, 'desc']
                    ],

                    language: {

                        search: 'Cari:',

                        lengthMenu:
                            'Tampilkan _MENU_ data',

                        info:
                            'Menampilkan _START_ sampai _END_ dari _TOTAL_ data',

                        infoEmpty:
                            'Tidak ada data',

                        zeroRecords:
                            'Data tidak ditemukan',

                        emptyTable:
                            'Belum ada data penaksiran',

                        paginate: {

                            first: 'Awal',

                            last: 'Akhir',

                            next: '›',

                            previous: '‹'

                        }

                    },

                    columnDefs: [

                        {
                            targets: 0,
                            searchable: false,
                            orderable: false
                        }

                    ]

                }
            );

        }

    }
);

</script>


<!-- =========================================================
     PRINT CSS
========================================================= -->

<style media="print">

    @page {

        size: landscape;

        margin: 10mm;

    }


    .sidebar,
    .topbar,
    .btn,
    .dataTables_wrapper > div:first-child,
    .dataTables_wrapper > div:last-child {

        display: none !important;

    }


    .main {

        margin-left: 0 !important;

        padding: 0 !important;

    }


    .content-card {

        box-shadow: none !important;

    }


    body {

        background: white !important;

    }

</style>


</body>

</html>