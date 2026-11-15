
<?php
session_start();
require_once __DIR__ . '/db.php';

date_default_timezone_set('Asia/Manila');

if (
    !isset($_SESSION['user_id']) ||
    ($_SESSION['role'] ?? '') !== 'psas'
) {
    header('Location: login.php');
    exit();
}

$startDate = trim($_GET['start_date'] ?? '');
$endDate = trim($_GET['end_date'] ?? '');
$statusFilter = strtolower(trim($_GET['status'] ?? ''));

$allowedStatuses = ['pending', 'approved', 'rejected'];

if (!in_array($statusFilter, $allowedStatuses, true)) {
    $statusFilter = '';
}

// Validate date format
if (
    $startDate !== '' &&
    !DateTime::createFromFormat('Y-m-d', $startDate)
) {
    $startDate = '';
}

if (
    $endDate !== '' &&
    !DateTime::createFromFormat('Y-m-d', $endDate)
) {
    $endDate = '';
}

$where = [];
$params = [];

if ($startDate !== '') {
    $where[] = 'ar.report_date >= ?';
    $params[] = $startDate;
}

if ($endDate !== '') {
    $where[] = 'ar.report_date <= ?';
    $params[] = $endDate;
}

if ($statusFilter !== '') {
    $where[] = 'LOWER(ar.status) = ?';
    $params[] = $statusFilter;
}

$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$reports = [];
$errorMessage = '';

$totalReports = 0;
$pendingReports = 0;
$approvedReports = 0;
$rejectedReports = 0;

$totalPresent = 0;
$totalAbsent = 0;
$totalStubsIssued = 0;
$totalActualStubs = 0;
$totalUnclaimed = 0;
$totalDistributed = 0;

try {
    /*
     * Aggregate batches and meal stubs separately.
     * This prevents duplicate totals when one batch
     * contains multiple individual meal stub records.
     */
    $sql = "
        SELECT
            ar.id AS report_id,
            ar.report_date,
            ar.course,
            ar.year_level,
            ar.section,
            ar.present_count,
            ar.absent_count,
            ar.status,
            ar.remarks,
            ar.review_remarks,
            ar.reviewed_at,
            u.full_name AS secretary_name,

            COALESCE(sb.stubs_issued, 0) AS stubs_issued,

            COALESCE(ms.actual_stubs, 0) AS actual_stubs,
            COALESCE(ms.unclaimed, 0) AS unclaimed,
            COALESCE(ms.distributed, 0) AS distributed

        FROM attendance_reports ar

        LEFT JOIN users u
            ON ar.secretary_id = u.id

        LEFT JOIN (
            SELECT
                attendance_report_id,
                SUM(stubs_issued) AS stubs_issued
            FROM stub_batches
            GROUP BY attendance_report_id
        ) sb
            ON sb.attendance_report_id = ar.id

        LEFT JOIN (
            SELECT
                b.attendance_report_id,
                COUNT(m.id) AS actual_stubs,

                SUM(
                    CASE
                        WHEN LOWER(m.status) = 'unclaimed'
                        THEN 1 ELSE 0
                    END
                ) AS unclaimed,

                SUM(
                    CASE
                        WHEN LOWER(m.status) = 'distributed'
                        THEN 1 ELSE 0
                    END
                ) AS distributed

            FROM stub_batches b

            LEFT JOIN meal_stubs m
                ON m.batch_id = b.id

            GROUP BY b.attendance_report_id
        ) ms
            ON ms.attendance_report_id = ar.id

        $whereSQL

        ORDER BY ar.report_date DESC, ar.id DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate summary values from filtered records.
    $totalReports = count($reports);

    foreach ($reports as $r) {

        $status = strtolower($r['status'] ?? 'pending');

        if ($status === 'pending') {
            $pendingReports++;
        } elseif ($status === 'approved') {
            $approvedReports++;
        } elseif ($status === 'rejected') {
            $rejectedReports++;
        }

        $totalPresent += (int)$r['present_count'];
        $totalAbsent += (int)$r['absent_count'];
        $totalStubsIssued += (int)$r['stubs_issued'];
        $totalActualStubs += (int)$r['actual_stubs'];
        $totalUnclaimed += (int)$r['unclaimed'];
        $totalDistributed += (int)$r['distributed'];
    }

} catch (PDOException $e) {
    error_log('Reports Audit Error: ' . $e->getMessage());
    $errorMessage = 'Unable to load report records. Please check the database.';
}

// CSV EXPORT
if (isset($_GET['export']) && $_GET['export'] === 'csv') {

    if ($errorMessage !== '') {
        http_response_code(500);
        exit('Unable to export records.');
    }

    // Prevent PHP notices/warnings from corrupting the downloadable CSV.
    if (ob_get_length()) {
        ob_end_clean();
    }

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="lvcc_meal_stub_audit.csv"');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');

    $output = fopen('php://output', 'w');

    if ($output === false) {
        http_response_code(500);
        exit('Unable to create CSV export.');
    }

    // UTF-8 BOM helps Microsoft Excel recognize UTF-8 characters.
    fwrite($output, "\xEF\xBB\xBF");

    $csvHeaders = [
        'Report ID',
        'Date',
        'Course',
        'Year Level',
        'Section',
        'Secretary',
        'Present',
        'Absent',
        'Attendance Status',
        'Stubs Issued',
        'Actual Stubs',
        'Unclaimed',
        'Distributed'
    ];

    fputcsv($output, $csvHeaders, ',', '"', '');

    foreach ($reports as $r) {
        $csvRow = [
            $r['report_id'],
            $r['report_date'],
            $r['course'],
            $r['year_level'],
            $r['section'],
            $r['secretary_name'] ?? 'Unknown',
            $r['present_count'],
            $r['absent_count'],
            strtoupper($r['status'] ?? ''),
            $r['stubs_issued'],
            $r['actual_stubs'],
            $r['unclaimed'],
            $r['distributed']
        ];

        fputcsv($output, $csvRow, ',', '"', '');
    }

    fclose($output);
    exit();
}

function e($value) {
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}

$queryFilters = http_build_query([
    'start_date' => $startDate,
    'end_date' => $endDate,
    'status' => $statusFilter
]);

$staffName = $_SESSION['name'] ?? $_SESSION['full_name'] ?? 'PSAS Staff';
$initial = strtoupper(substr(trim($staffName), 0, 1));
?>

<!DOCTYPE html>
<html lang="en">
<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Reports & Audit | LVCC Meal Stub System</title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@600;700;800&display=swap" rel="stylesheet">

<style>
:root {
    --navy: #00205b;
    --blue: #003087;
    --yellow: #f2a900;
    --bg: #f4f6f9;
    --text: #1d2b3a;
    --muted: #687788;
    --border: #dde3ec;
    --green: #17835b;
    --orange: #d97706;
    --red: #c84646;
}

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    min-height: 100vh;
    display: flex;
    background: var(--bg);
    color: var(--text);
    font-family: "DM Sans", sans-serif;
}

/* SIDEBAR */

.sidebar {
    width: 264px;
    height: 100vh;
    position: fixed;
    top: 0;
    left: 0;
    padding: 20px;
    background: var(--navy);
    color: white;
    display: flex;
    flex-direction: column;
    z-index: 10;
    overflow-y: auto;
}

.brand {
    display: flex;
    align-items: center;
    gap: 12px;
    padding-bottom: 20px;
    border-bottom: 1px solid #ffffff20;
}

.brand img {
    width: 55px;
    height: 55px;
    border-radius: 50%;
    object-fit: cover;
    background: white;
}

.brand b {
    display: block;
    font-family: "Manrope", sans-serif;
    font-size: 18px;
}

.brand small {
    color: #b8cadd;
    font-size: 11px;
}

.nav-label {
    color: #88a4bd;
    font-size: 9px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .14em;
    margin: 28px 0 10px;
}

.sidebar nav a {
    display: block;
    color: #bfd0df;
    text-decoration: none;
    padding: 12px 15px;
    border-radius: 8px;
    font-weight: 500;
    font-size: 13px;
    margin-bottom: 5px;
    transition: .2s ease;
}

.sidebar nav a:hover,
.sidebar nav a.active {
    background: var(--blue);
    color: white;
    transform: translateX(3px);
}

.sidebar-bottom {
    margin-top: auto;
    border-top: 1px solid #ffffff20;
    padding-top: 16px;
}

.profile {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 15px;
}

.profile-icon {
    width: 38px;
    height: 38px;
    background: var(--yellow);
    color: var(--navy);
    border-radius: 50%;
    display: grid;
    place-items: center;
    font-weight: 800;
}

.profile-info b {
    display: block;
    font-size: 12px;
}

.profile-info small {
    font-size: 10px;
    color: #9db4c8;
}

.logout {
    color: #ff7777;
    text-decoration: none;
    font-size: 12px;
    font-weight: 700;
}

/* MAIN CONTENT AND BACKGROUND */

.main-content {
    width: calc(100% - 264px);
    min-height: 100vh;
    margin-left: 264px;
    padding: 35px 38px;
    position: relative;
    isolation: isolate;
}

.main-content::before {
    content: "";
    position: fixed;
    inset: 0 0 0 264px;
    background:
        linear-gradient(
            rgba(226, 235, 247, .65),
            rgba(226, 235, 247, .65)
        ),
        url("assets/image/bg.jpg") center center / cover no-repeat;
    z-index: -2;
    pointer-events: none;
}

.main-content::after {
    content: "";
    position: fixed;
    inset: 0 0 0 264px;
    background: linear-gradient(
        135deg,
        rgba(255,255,255,.10),
        rgba(255,255,255,.18)
    );
    z-index: -1;
    pointer-events: none;
}

/* HEADER */

.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 20px;
    margin-bottom: 25px;
}

.eyebrow {
    color: var(--blue);
    font-size: 10px;
    font-weight: 800;
    letter-spacing: .14em;
    text-transform: uppercase;
}

h1 {
    margin: 6px 0;
    font-family: "Manrope", sans-serif;
    font-size: 28px;
    color: var(--navy);
}

.subtitle {
    margin: 0;
    color: var(--muted);
    font-size: 13px;
}

.header-actions {
    display: flex;
    gap: 9px;
    flex-wrap: wrap;
}

.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 11px 16px;
    border: 1px solid var(--blue);
    border-radius: 8px;
    background: var(--blue);
    color: white;
    font-family: inherit;
    font-size: 12px;
    font-weight: 700;
    text-decoration: none;
    cursor: pointer;
    transition: .2s ease;
}

.btn:hover {
    background: #0741a5;
    transform: translateY(-2px);
    box-shadow: 0 6px 14px rgba(0,32,91,.15);
}

.btn-light {
    background: white;
    color: var(--navy);
    border-color: var(--border);
}

.btn-light:hover {
    background: #f1f5fb;
}

/* ALERT */

.error-message {
    padding: 14px 17px;
    margin-bottom: 20px;
    background: #fee2e2;
    color: #991b1b;
    border: 1px solid #fecaca;
    border-radius: 8px;
    font-size: 12px;
    font-weight: 600;
}

/* PANELS */

.panel {
    padding: 23px;
    margin-bottom: 23px;
    background: rgba(255,255,255,.95);
    border: 1px solid rgba(221,227,236,.9);
    border-radius: 12px;
    box-shadow: 0 5px 18px rgba(0,32,91,.06);
    transition: transform .25s ease, box-shadow .25s ease;
}

.panel:hover {
    transform: translateY(-5px);
    box-shadow: 0 14px 28px rgba(0,32,91,.12);
}

.panel h3 {
    margin: 0 0 6px;
    color: var(--navy);
    font-family: "Manrope", sans-serif;
    font-size: 16px;
}

.panel-subtitle {
    color: var(--muted);
    font-size: 11px;
    margin: 0 0 20px;
}

/* FILTERS */

.filter-grid {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr auto auto;
    gap: 13px;
    align-items: end;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    color: var(--navy);
    font-size: 10px;
    font-weight: 800;
    text-transform: uppercase;
}

.form-control {
    width: 100%;
    min-height: 41px;
    padding: 10px 12px;
    border: 1px solid var(--border);
    border-radius: 7px;
    background: white;
    color: var(--text);
    font-family: inherit;
    font-size: 12px;
    outline: none;
    transition: .2s ease;
}

.form-control:focus {
    border-color: var(--blue);
    box-shadow: 0 0 0 3px rgba(0,48,135,.10);
}

/* SUMMARY CARDS */

.summary-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 15px;
    margin-bottom: 24px;
}

.summary-card {
    min-height: 112px;
    padding: 19px;
    background: rgba(255,255,255,.96);
    border: 1px solid rgba(221,227,236,.9);
    border-radius: 11px;
    box-shadow: 0 4px 13px rgba(0,32,91,.05);
    transition: transform .25s ease, box-shadow .25s ease;
    position: relative;
    overflow: hidden;
}

.summary-card:hover {
    transform: translateY(-6px);
    box-shadow: 0 13px 24px rgba(0,32,91,.12);
}

.summary-card::before {
    content: "";
    position: absolute;
    top: 0;
    left: 0;
    height: 4px;
    width: 100%;
    background: var(--blue);
}

.summary-card.orange-card::before {
    background: var(--orange);
}

.summary-card.green-card::before {
    background: var(--green);
}

.summary-card.red-card::before {
    background: var(--red);
}

.summary-card p {
    margin: 0 0 10px;
    color: var(--muted);
    font-size: 10px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: .04em;
}

.summary-card strong {
    font-family: "Manrope", sans-serif;
    font-size: 27px;
    color: var(--navy);
}

.summary-card .orange {
    color: var(--orange);
}

.summary-card .green {
    color: var(--green);
}

.summary-card .red {
    color: var(--red);
}

/* TABLE */

.table-wrap {
    width: 100%;
    overflow-x: auto;
}

table {
    width: 100%;
    min-width: 1050px;
    border-collapse: collapse;
    font-size: 11px;
}

thead th {
    padding: 14px 11px;
    background: #f1f4f9;
    color: #65758b;
    text-align: left;
    font-size: 10px;
    font-weight: 800;
    text-transform: uppercase;
    white-space: nowrap;
    border-bottom: 1px solid var(--border);
}

tbody td {
    padding: 14px 11px;
    border-bottom: 1px solid #edf1f4;
    white-space: nowrap;
    color: #35485c;
}

tbody tr {
    transition: background .2s ease;
}

tbody tr:hover {
    background: #f5f8fd;
}

td strong {
    color: var(--navy);
}

.present {
    color: var(--green);
    font-weight: 800;
}

.absent {
    color: var(--red);
    font-weight: 800;
}

/* STATUS BADGES */

.badge {
    display: inline-block;
    padding: 5px 9px;
    border-radius: 20px;
    font-size: 9px;
    font-weight: 800;
    text-transform: uppercase;
}

.badge-pending {
    background: #fef3c7;
    color: #b45309;
}

.badge-approved {
    background: #def7ec;
    color: #03543f;
}

.badge-rejected {
    background: #fee2e2;
    color: #991b1b;
}

/* EMPTY STATE */

.empty {
    padding: 40px 20px;
    color: var(--muted);
    text-align: center;
    font-size: 12px;
}

/* FOOTER */

.footer {
    color: #8291a5;
    font-size: 10px;
    display: flex;
    justify-content: space-between;
    gap: 12px;
    margin-top: 30px;
}

/* RESPONSIVE */

@media (max-width: 1150px) {
    .summary-grid {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }

    .filter-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 850px) {
    .sidebar {
        width: 220px;
    }

    .main-content {
        width: calc(100% - 220px);
        margin-left: 220px;
        padding: 25px;
    }

    .main-content::before,
    .main-content::after {
        left: 220px;
    }

    .summary-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .page-header {
        align-items: flex-start;
        flex-direction: column;
    }
}

@media (max-width: 600px) {
    body {
        display: block;
    }

    .sidebar {
        position: relative;
        width: 100%;
        height: auto;
    }

    .sidebar-bottom {
        margin-top: 20px;
    }

    .main-content {
        width: 100%;
        margin-left: 0;
        padding: 20px;
    }

    .main-content::before,
    .main-content::after {
        inset: 0;
    }

    .summary-grid,
    .filter-grid {
        grid-template-columns: 1fr;
    }

    .header-actions {
        width: 100%;
    }

    .header-actions .btn {
        flex: 1;
    }

    .footer {
        flex-direction: column;
    }
}

/* PRINT / SAVE AS PDF */
@page {
    size: A4 landscape;
    margin: 10mm;
}

@media print {
    html,
    body {
        width: 100%;
        min-height: 0;
        margin: 0;
        padding: 0;
        display: block;
        background: #fff !important;
        color: #1d2b3a;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }

    .sidebar,
    .header-actions,
    .filter-panel,
    .footer {
        display: none !important;
    }

    .main-content {
        width: 100% !important;
        min-height: 0 !important;
        margin: 0 !important;
        padding: 0 !important;
        overflow: visible !important;
    }

    .main-content::before,
    .main-content::after {
        display: none !important;
    }

    .page-header {
        margin: 0 0 12px !important;
    }

    .page-header h1 {
        font-size: 20px !important;
    }

    .subtitle {
        font-size: 9px !important;
    }

    .summary-grid {
        grid-template-columns: repeat(5, minmax(0, 1fr)) !important;
        gap: 6px !important;
        margin-bottom: 10px !important;
    }

    .summary-card {
        min-height: 0 !important;
        padding: 8px !important;
        box-shadow: none !important;
        transform: none !important;
        break-inside: avoid;
    }

    .summary-card p {
        margin-bottom: 4px !important;
        font-size: 7px !important;
    }

    .summary-card strong {
        font-size: 16px !important;
    }

    .panel {
        padding: 10px !important;
        margin: 0 !important;
        box-shadow: none !important;
        transform: none !important;
        border: 1px solid #dce3ec !important;
        break-inside: auto !important;
    }

    .panel h3 {
        font-size: 12px !important;
    }

    .panel-subtitle {
        margin-bottom: 8px !important;
        font-size: 8px !important;
    }

    .table-wrap {
        width: 100% !important;
        overflow: visible !important;
    }

    table {
        width: 100% !important;
        min-width: 0 !important;
        table-layout: fixed !important;
        border-collapse: collapse !important;
        font-size: 7px !important;
    }

    thead {
        display: table-header-group;
    }

    tr {
        break-inside: avoid;
        page-break-inside: avoid;
    }

    thead th,
    tbody td {
        padding: 6px 4px !important;
        white-space: normal !important;
        overflow-wrap: anywhere !important;
        word-break: normal !important;
        font-size: 7px !important;
        line-height: 1.25 !important;
    }

    thead th {
        background: #00205b !important;
        color: #fff !important;
        border: 1px solid #00205b !important;
    }

    tbody td {
        border: 1px solid #dde3ec !important;
    }

    .badge {
        padding: 3px 5px !important;
        font-size: 6px !important;
    }

    .panel:hover,
    .summary-card:hover {
        transform: none !important;
        box-shadow: none !important;
    }
}

</style>
</head>

<body>

<!-- SIDEBAR -->

<aside class="sidebar">

    <div class="brand">
        <img src="assets/image/LVlogo.jpg" alt="LVCC Logo">

        <div>
            <b>LVCC</b>
            <small>Meal Stub System</small>
        </div>
    </div>

    <span class="nav-label">Workspace</span>

    <nav>
        <a href="psas_dashboard.php">▦ &nbsp; Overview</a>
        <a href="attendance_verification.php">✓ &nbsp; Attendance Verification</a>
        <a href="stub_issuance.php">▤ &nbsp; Stub Issuance</a>
        <a href="stub_monitoring.php">◷ &nbsp; Stub Monitoring</a>
        <a href="reports_audit.php" class="active">▥ &nbsp; Reports & Audit</a>
        <a href="user_management.php">♟ &nbsp; User Management</a>
    </nav>

    <div class="sidebar-bottom">

        <div class="profile">
            <div class="profile-icon">
                <?= e($initial) ?>
            </div>

            <div class="profile-info">
                <b><?= e($staffName) ?></b>
                <small>PSAS Administrator</small>
            </div>
        </div>

        <a href="logout.php" class="logout">↪ &nbsp; Logout</a>

    </div>

</aside>


<!-- MAIN CONTENT -->

<main class="main-content">

    <!-- HEADER -->

    <div class="page-header">

        <div>
            <span class="eyebrow">PSAS Staff Portal</span>

            <h1>Reports & Audit</h1>

            <p class="subtitle">
                Analyze attendance and meal stub distribution across the institution.
            </p>
        </div>

        <div class="header-actions">

            <a
                class="btn"
                href="reports_audit.php?<?= e($queryFilters) ?>&export=csv"
            >
                ↓ &nbsp; Export CSV
            </a>

            <button
                type="button"
                class="btn btn-light"
                onclick="window.print()"
            >
                ▤ &nbsp; Print / Save PDF
            </button>

        </div>

    </div>


    <?php if ($errorMessage !== ''): ?>

        <div class="error-message">
            ! &nbsp; <?= e($errorMessage) ?>
        </div>

    <?php endif; ?>


    <!-- FILTERS -->

    <section class="panel filter-panel">

        <h3>Filter Audit Records</h3>

        <p class="panel-subtitle">
            Narrow down attendance and stub records by date and approval status.
        </p>

        <form method="GET" class="filter-grid">

            <div class="form-group">

                <label for="start_date">Start Date</label>

                <input
                    type="date"
                    id="start_date"
                    name="start_date"
                    class="form-control"
                    value="<?= e($startDate) ?>"
                >

            </div>


            <div class="form-group">

                <label for="end_date">End Date</label>

                <input
                    type="date"
                    id="end_date"
                    name="end_date"
                    class="form-control"
                    value="<?= e($endDate) ?>"
                >

            </div>


            <div class="form-group">

                <label for="status">Attendance Status</label>

                <select
                    name="status"
                    id="status"
                    class="form-control"
                >

                    <option value="">All Statuses</option>

                    <option
                        value="pending"
                        <?= $statusFilter === 'pending' ? 'selected' : '' ?>
                    >
                        Pending
                    </option>

                    <option
                        value="approved"
                        <?= $statusFilter === 'approved' ? 'selected' : '' ?>
                    >
                        Approved
                    </option>

                    <option
                        value="rejected"
                        <?= $statusFilter === 'rejected' ? 'selected' : '' ?>
                    >
                        Rejected
                    </option>

                </select>

            </div>


            <button type="submit" class="btn">
                Apply Filters
            </button>

            <a href="reports_audit.php" class="btn btn-light">
                Reset
            </a>

        </form>

    </section>


    <!-- SUMMARY CARDS -->

    <div class="summary-grid">

        <div class="summary-card">
            <p>Total Attendance Reports</p>
            <strong><?= number_format($totalReports) ?></strong>
        </div>

        <div class="summary-card orange-card">
            <p>Pending Reports</p>
            <strong class="orange">
                <?= number_format($pendingReports) ?>
            </strong>
        </div>

        <div class="summary-card green-card">
            <p>Approved Reports</p>
            <strong class="green">
                <?= number_format($approvedReports) ?>
            </strong>
        </div>

        <div class="summary-card red-card">
            <p>Rejected Reports</p>
            <strong class="red">
                <?= number_format($rejectedReports) ?>
            </strong>
        </div>

        <div class="summary-card green-card">
            <p>Total Present</p>
            <strong class="green">
                <?= number_format($totalPresent) ?>
            </strong>
        </div>

        <div class="summary-card red-card">
            <p>Total Absent</p>
            <strong class="red">
                <?= number_format($totalAbsent) ?>
            </strong>
        </div>

        <div class="summary-card">
            <p>Total Stubs Issued</p>
            <strong>
                <?= number_format($totalStubsIssued) ?>
            </strong>
        </div>

        <div class="summary-card green-card">
            <p>Distributed Stubs</p>
            <strong class="green">
                <?= number_format($totalDistributed) ?>
            </strong>
        </div>

        <div class="summary-card">
            <p>Actual Stubs</p>
            <strong>
                <?= number_format($totalActualStubs) ?>
            </strong>
        </div>

        <div class="summary-card orange-card">
            <p>Unclaimed Stubs</p>
            <strong class="orange">
                <?= number_format($totalUnclaimed) ?>
            </strong>
        </div>

    </div>


    <!-- AUDIT TABLE -->

    <section class="panel">

        <h3>Attendance & Stub Records</h3>

        <p class="panel-subtitle">
            Detailed attendance submissions and corresponding meal stub records.
        </p>

        <div class="table-wrap">

            <table>

                <thead>

                    <tr>
                        <th>Report ID</th>
                        <th>Date</th>
                        <th>Course & Section</th>
                        <th>Submitted By</th>
                        <th>Present</th>
                        <th>Absent</th>
                        <th>Status</th>
                        <th>Stubs Issued</th>
                        <th>Actual Stubs</th>
                        <th>Unclaimed</th>
                        <th>Distributed</th>
                    </tr>

                </thead>

                <tbody>

                    <?php if (empty($reports)): ?>

                        <tr>
                            <td colspan="11" class="empty">
                                No attendance records found for the selected filters.
                            </td>
                        </tr>

                    <?php else: ?>

                        <?php foreach ($reports as $r): ?>

                            <?php
                            $status = strtolower($r['status'] ?? 'pending');

                            $badgeClass = in_array(
                                $status,
                                ['pending', 'approved', 'rejected'],
                                true
                            ) ? 'badge-' . $status : 'badge-pending';
                            ?>

                            <tr>

                                <td>
                                    <strong>#<?= (int)$r['report_id'] ?></strong>
                                </td>

                                <td>
                                    <?= e($r['report_date']) ?>
                                </td>

                                <td>
                                    <strong><?= e($r['course']) ?></strong>
                                    <?= e($r['year_level']) ?> -
                                    <?= e($r['section']) ?>
                                </td>

                                <td>
                                    <?= e($r['secretary_name'] ?? 'Unknown') ?>
                                </td>

                                <td class="present">
                                    <?= number_format((int)$r['present_count']) ?>
                                </td>

                                <td class="absent">
                                    <?= number_format((int)$r['absent_count']) ?>
                                </td>

                                <td>
                                    <span class="badge <?= e($badgeClass) ?>">
                                        <?= e(strtoupper($status)) ?>
                                    </span>
                                </td>

                                <td>
                                    <?= number_format((int)$r['stubs_issued']) ?>
                                </td>

                                <td>
                                    <strong>
                                        <?= number_format((int)$r['actual_stubs']) ?>
                                    </strong>
                                </td>

                                <td>
                                    <?= number_format((int)$r['unclaimed']) ?>
                                </td>

                                <td class="present">
                                    <?= number_format((int)$r['distributed']) ?>
                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php endif; ?>

                </tbody>

            </table>

        </div>

    </section>


    <footer class="footer">

        <span>
            © <?= date('Y') ?> La Verdad Christian College
        </span>

        <span>
            Meal Stub Management System · PSAS Portal
        </span>

    </footer>

</main>

</body>
</html>
