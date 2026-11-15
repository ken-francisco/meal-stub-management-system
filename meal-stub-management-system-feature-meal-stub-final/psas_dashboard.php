
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

$staffName = (string)(
    $_SESSION['name'] ??
    $_SESSION['full_name'] ??
    'PSAS Staff'
);

$firstName = explode(' ', trim($staffName))[0] ?: 'Staff';
$initials = strtoupper(substr($staffName, 0, 1));

$hour = (int)date('H');

$greeting = $hour < 12
    ? 'Good morning'
    : ($hour < 18 ? 'Good afternoon' : 'Good evening');

$countPending = 0;
$countApproved = 0;
$countRejected = 0;
$totalStubs = 0;
$approvalRate = 0;
$reports = [];
$dashboardError = '';

try {
    $countPending = (int)$pdo->query("
        SELECT COUNT(*)
        FROM attendance_reports
        WHERE status = 'pending'
    ")->fetchColumn();

    $countApproved = (int)$pdo->query("
        SELECT COUNT(*)
        FROM attendance_reports
        WHERE status = 'approved'
    ")->fetchColumn();

    $countRejected = (int)$pdo->query("
        SELECT COUNT(*)
        FROM attendance_reports
        WHERE status = 'rejected'
    ")->fetchColumn();

    $totalStubs = (int)$pdo->query("
        SELECT COALESCE(SUM(stubs_issued), 0)
        FROM stub_batches
    ")->fetchColumn();

    $reviewed = $countApproved + $countRejected;

    if ($reviewed > 0) {
        $approvalRate = round(
            ($countApproved / $reviewed) * 100
        );
    }

    $stmt = $pdo->query("
        SELECT
            ar.*,
            u.full_name AS secretary_name
        FROM attendance_reports ar
        LEFT JOIN users u
            ON ar.secretary_id = u.id
        ORDER BY ar.report_date DESC, ar.id DESC
        LIMIT 5
    ");

    $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log('PSAS dashboard error: ' . $e->getMessage());
    $dashboardError = 'Unable to load dashboard information.';
}

function e($value) {
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}

function statusBadgeClass($status) {
    switch (strtolower((string)$status)) {
        case 'approved':
            return 'badge-approved';

        case 'rejected':
            return 'badge-rejected';

        default:
            return 'badge-pending';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>PSAS Dashboard | LVCC Meal Stub System</title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@500;600;700;800&display=swap" rel="stylesheet">

<style>
:root {
    --navy: #00205b;
    --blue: #003087;
    --yellow: #f2a900;
    --bg: #f4f6fa;
    --text: #1d2b3a;
    --muted: #718096;
    --border: #e5eaf1;
    --green: #17835b;
    --red: #c84646;
    --orange: #d97706;
}

* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}

body {
    min-height: 100vh;
    display: flex;
    background: var(--bg);
    color: var(--text);
    font-family: 'DM Sans', sans-serif;
}

/* SIDEBAR */

.sidebar {
    width: 264px;
    height: 100vh;
    position: fixed;
    top: 0;
    left: 0;
    z-index: 10;
    display: flex;
    flex-direction: column;
    padding: 22px 20px;
    overflow-y: auto;
    background: var(--navy);
    color: white;
}

.brand {
    display: flex;
    align-items: center;
    gap: 12px;
    padding-bottom: 21px;
    border-bottom: 1px solid #ffffff20;
}

.brand img {
    width: 56px;
    height: 56px;
    object-fit: contain;
    background: white;
    border-radius: 50%;
    padding: 3px;
}

.brand b {
    display: block;
    font-family: 'Manrope', sans-serif;
    font-size: 18px;
    font-weight: 800;
}

.brand small {
    color: #b8cadd;
    font-size: 11px;
}

.nav-label {
    display: block;
    margin: 27px 0 11px;
    color: #8da9c5;
    font-size: 10px;
    font-weight: 800;
    letter-spacing: 1.6px;
    text-transform: uppercase;
}

nav {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

nav a {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    padding: 12px 14px;
    border-radius: 8px;
    color: #c4d3e3;
    text-decoration: none;
    font-size: 13px;
    font-weight: 600;
    transition: .2s;
}

nav a:hover,
nav a.active {
    background: #073b91;
    color: white;
}

.nav-left {
    display: flex;
    align-items: center;
    gap: 11px;
}

.nav-icon {
    width: 19px;
    display: inline-grid;
    place-items: center;
    font-size: 15px;
}

.nav-badge {
    min-width: 21px;
    height: 21px;
    display: grid;
    place-items: center;
    padding: 0 6px;
    border-radius: 20px;
    background: var(--yellow);
    color: var(--navy);
    font-size: 10px;
    font-weight: 800;
}

.sidebar-account {
    margin-top: auto;
    padding-top: 18px;
    border-top: 1px solid #ffffff20;
}

.account-info {
    display: flex;
    align-items: center;
    gap: 10px;
}

.account-avatar {
    width: 37px;
    height: 37px;
    display: grid;
    place-items: center;
    border-radius: 50%;
    background: var(--yellow);
    color: var(--navy);
    font-size: 13px;
    font-weight: 800;
}

.account-name {
    font-size: 12px;
    font-weight: 700;
    color: white;
}

.account-role {
    font-size: 10px;
    color: #a9bfd5;
    margin-top: 3px;
}

.logout-link {
    display: block;
    margin-top: 16px;
    padding: 9px 11px;
    border-radius: 7px;
    color: #ff9999;
    text-decoration: none;
    font-size: 12px;
    font-weight: 600;
}

.logout-link:hover {
    background: #ffffff15;
    color: white;
}

/* MAIN CONTENT */

.main-content {
    position: relative;
    isolation: isolate;
    width: calc(100% - 264px);
    min-height: 100vh;
    margin-left: 264px;
    padding: 32px 38px 45px;
}

/* MORE VISIBLE LVCC BACKGROUND */

.main-content::before {
    content: "";
    position: fixed;
    z-index: -1;
    top: 0;
    right: 0;
    bottom: 0;
    left: 264px;

    background-image:
        linear-gradient(
            rgba(235, 241, 250, 0.48),
            rgba(235, 241, 250, 0.60)
        ),
        url('assets/image/bg.jpg');

    background-size: cover;
    background-position: center;
    background-repeat: no-repeat;
}

/* TOPBAR */

.topbar {
    display: flex;
    justify-content: flex-end;
    align-items: center;
    margin-bottom: 28px;
}

.topbar-user {
    display: flex;
    align-items: center;
    gap: 11px;
    padding-left: 18px;
    border-left: 1px solid var(--border);
}

.topbar-avatar {
    width: 39px;
    height: 39px;
    display: grid;
    place-items: center;
    border-radius: 50%;
    background: #eaf0fa;
    color: var(--blue);
    font-size: 13px;
    font-weight: 800;
}

.topbar-name {
    font-size: 12px;
    font-weight: 700;
}

.topbar-role {
    display: block;
    margin-top: 3px;
    color: var(--muted);
    font-size: 10px;
}

/* PAGE HEADING */

.page-heading {
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
    gap: 20px;
    margin-bottom: 27px;
}

.page-eyebrow {
    color: var(--blue);
    font-size: 10px;
    font-weight: 800;
    letter-spacing: 1.5px;
    text-transform: uppercase;
}

.page-heading h1 {
    margin-top: 7px;
    font-family: 'Manrope', sans-serif;
    font-size: 27px;
    font-weight: 800;
    color: var(--navy);
}

.page-heading p {
    margin-top: 6px;
    color: var(--muted);
    font-size: 13px;
}

.date-label {
    padding: 10px 14px;
    background: white;
    border: 1px solid var(--border);
    border-radius: 8px;
    color: var(--muted);
    font-size: 11px;
    font-weight: 600;
    white-space: nowrap;
}

/* ERROR */

.alert-error {
    margin-bottom: 22px;
    padding: 14px 16px;
    border: 1px solid #f3c4c4;
    border-radius: 9px;
    background: #fee2e2;
    color: #b91c1c;
    font-size: 13px;
}

/* KPI CARDS */

.kpi-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 17px;
    margin-bottom: 27px;
}

.kpi-card {
    padding: 21px;
    background: rgba(255,255,255,.96);
    border: 1px solid var(--border);
    border-radius: 12px;
    box-shadow: 0 4px 14px rgba(15,35,70,.06);
    transition: .2s;
}

.kpi-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 9px 22px rgba(15,35,70,.10);
}

.kpi-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    margin-bottom: 16px;
}

.kpi-label {
    color: var(--muted);
    font-size: 10px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: .6px;
}

.kpi-icon {
    width: 38px;
    height: 38px;
    display: grid;
    place-items: center;
    border-radius: 10px;
    font-size: 17px;
}

.icon-pending {
    background: #fef3c7;
    color: var(--orange);
}

.icon-approved {
    background: #def7ec;
    color: var(--green);
}

.icon-stubs {
    background: #eaf0fa;
    color: var(--blue);
}

.icon-rejected {
    background: #fee2e2;
    color: var(--red);
}

.kpi-value {
    display: block;
    font-family: 'Manrope', sans-serif;
    font-size: 29px;
    font-weight: 800;
    color: var(--navy);
}

.kpi-description {
    display: block;
    margin-top: 7px;
    color: var(--muted);
    font-size: 11px;
}

/* SUMMARY */

.summary-strip {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 15px;
    margin-bottom: 27px;
    padding: 19px 21px;
    background: linear-gradient(110deg, #00205b, #073b91);
    border-radius: 11px;
    color: white;
    box-shadow: 0 5px 16px rgba(0,32,91,.12);
}

.summary-copy strong {
    display: block;
    font-family: 'Manrope', sans-serif;
    font-size: 14px;
}

.summary-copy small {
    display: block;
    margin-top: 4px;
    color: #c6d5e8;
    font-size: 11px;
}

.summary-rate {
    font-family: 'Manrope', sans-serif;
    font-size: 25px;
    font-weight: 800;
    color: var(--yellow);
}

/* RECENT REPORTS */

.panel {
    background: rgba(255,255,255,.97);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 23px;
    box-shadow: 0 4px 14px rgba(15,35,70,.06);
}

.panel-heading {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    margin-bottom: 19px;
}

.panel-heading h2 {
    font-family: 'Manrope', sans-serif;
    color: var(--navy);
    font-size: 16px;
    font-weight: 800;
}

.panel-heading p {
    margin-top: 5px;
    color: var(--muted);
    font-size: 11px;
}

.view-all {
    color: var(--blue);
    font-size: 11px;
    font-weight: 700;
    text-decoration: none;
    white-space: nowrap;
}

.view-all:hover {
    text-decoration: underline;
}

/* TABLE */

.table-wrapper {
    width: 100%;
    overflow-x: auto;
}

table {
    width: 100%;
    min-width: 690px;
    border-collapse: collapse;
    font-size: 12px;
}

thead th {
    padding: 13px 14px;
    background: #f5f7fb;
    color: #718096;
    font-size: 10px;
    font-weight: 800;
    text-align: left;
    text-transform: uppercase;
    letter-spacing: .5px;
    border-bottom: 1px solid var(--border);
}

tbody td {
    padding: 15px 14px;
    border-bottom: 1px solid #edf0f5;
    color: #35485c;
    vertical-align: middle;
}

tbody tr:last-child td {
    border-bottom: none;
}

tbody tr:hover {
    background: #fafbfd;
}

.date-cell {
    color: var(--navy);
    font-weight: 700;
}

.secretary-cell {
    font-weight: 600;
}

.course-cell strong {
    color: var(--navy);
    font-weight: 700;
}

.count-present {
    color: var(--green);
    font-weight: 800;
}

.count-absent {
    color: var(--red);
    font-weight: 800;
}

/* STATUS */

.badge {
    display: inline-flex;
    align-items: center;
    padding: 5px 10px;
    border-radius: 20px;
    font-size: 9px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: .4px;
}

.badge-pending {
    background: #fef3c7;
    color: var(--orange);
}

.badge-approved {
    background: #def7ec;
    color: var(--green);
}

.badge-rejected {
    background: #fee2e2;
    color: var(--red);
}

/* EMPTY STATE */

.empty-state {
    padding: 42px 15px;
    text-align: center;
}

.empty-icon {
    width: 54px;
    height: 54px;
    display: grid;
    place-items: center;
    margin: 0 auto 13px;
    border-radius: 50%;
    background: #eaf0fa;
    color: var(--blue);
    font-size: 23px;
}

.empty-state h3 {
    color: var(--navy);
    font-family: 'Manrope', sans-serif;
    font-size: 14px;
}

.empty-state p {
    margin-top: 6px;
    color: var(--muted);
    font-size: 12px;
}

/* FOOTER */

.page-footer {
    margin-top: 24px;
    padding: 15px 2px;
    display: flex;
    justify-content: space-between;
    gap: 10px;
    color: #718096;
    font-size: 10px;
}

/* RESPONSIVE */

@media (max-width: 1150px) {
    .main-content {
        padding: 28px 25px;
    }

    .kpi-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 750px) {
    body {
        display: block;
    }

    .sidebar {
        position: relative;
        width: 100%;
        height: auto;
        min-height: auto;
        padding: 17px;
    }

    .brand {
        padding-bottom: 15px;
    }

    .nav-label {
        margin-top: 18px;
    }

    nav {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    nav a {
        font-size: 11px;
        padding: 11px 9px;
    }

    .sidebar-account {
        margin-top: 18px;
    }

    .main-content {
        width: 100%;
        margin-left: 0;
        padding: 23px 17px;
    }

    .main-content::before {
        left: 0;
    }

    .topbar {
        margin-bottom: 22px;
    }

    .page-heading {
        align-items: flex-start;
        flex-direction: column;
    }

    .page-heading h1 {
        font-size: 23px;
    }

    .kpi-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 11px;
    }

    .kpi-card {
        padding: 16px;
    }

    .kpi-value {
        font-size: 24px;
    }

    .kpi-label {
        font-size: 9px;
    }

    .summary-strip {
        padding: 15px;
    }

    .panel {
        padding: 17px;
    }
}

@media (max-width: 430px) {
    nav {
        grid-template-columns: 1fr;
    }

    .kpi-grid {
        grid-template-columns: 1fr 1fr;
    }

    .kpi-icon {
        width: 32px;
        height: 32px;
    }

    .summary-copy strong {
        font-size: 12px;
    }

    .summary-rate {
        font-size: 21px;
    }

    .page-footer {
        flex-direction: column;
    }
}

/* PRINT */

@media print {
    body {
        display: block;
        background: white;
    }

    .sidebar,
    .topbar,
    .date-label,
    .page-footer,
    .view-all {
        display: none !important;
    }

    .main-content {
        width: 100%;
        margin: 0;
        padding: 0;
    }

    .main-content::before {
        display: none;
    }

    .kpi-card,
    .panel {
        box-shadow: none;
        break-inside: avoid;
    }

    .summary-strip {
        background: #00205b !important;
        print-color-adjust: exact;
        -webkit-print-color-adjust: exact;
    }

    table {
        min-width: 100%;
    }
}
</style>
</head>

<body>

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

        <a href="psas_dashboard.php" class="active">
            <span class="nav-left">
                <span class="nav-icon">▦</span>
                Overview
            </span>
        </a>

        <a href="attendance_verification.php">
            <span class="nav-left">
                <span class="nav-icon">✓</span>
                Attendance Verification
            </span>

            <span class="nav-badge">
                <?= number_format($countPending) ?>
            </span>
        </a>

        <a href="stub_issuance.php">
            <span class="nav-left">
                <span class="nav-icon">▤</span>
                Stub Issuance
            </span>
        </a>

        <a href="stub_monitoring.php">
            <span class="nav-left">
                <span class="nav-icon">◷</span>
                Stub Monitoring
            </span>
        </a>

        <a href="reports_audit.php">
            <span class="nav-left">
                <span class="nav-icon">▥</span>
                Reports & Audit
            </span>
        </a>

        <a href="user_management.php">
            <span class="nav-left">
                <span class="nav-icon">♙</span>
                User Management
            </span>
        </a>

    </nav>

    <div class="sidebar-account">

        <div class="account-info">

            <div class="account-avatar">
                <?= e($initials) ?>
            </div>

            <div>
                <div class="account-name">
                    <?= e($staffName) ?>
                </div>

                <div class="account-role">
                    PSAS Administrator
                </div>
            </div>

        </div>

        <a href="logout.php" class="logout-link">
            ↪ &nbsp; Logout
        </a>

    </div>

</aside>


<main class="main-content">

    <div class="topbar">

        <div class="topbar-user">

            <div class="topbar-avatar">
                <?= e($initials) ?>
            </div>

            <div>
                <div class="topbar-name">
                    <?= e($staffName) ?>
                </div>

                <span class="topbar-role">
                    PSAS Staff
                </span>
            </div>

        </div>

    </div>


    <section class="page-heading">

        <div>

            <span class="page-eyebrow">
                PSAS Staff Portal
            </span>

            <h1>
                <?= e($greeting) ?>, <?= e($firstName) ?>!
            </h1>

            <p>
                Here's what's happening with the meal program today.
            </p>

        </div>

        <div class="date-label">
            ◷ &nbsp; <?= date('F d, Y') ?>
        </div>

    </section>


    <?php if ($dashboardError !== ''): ?>

        <div class="alert-error">
            <?= e($dashboardError) ?>
        </div>

    <?php endif; ?>


    <section class="kpi-grid">

        <div class="kpi-card">

            <div class="kpi-top">
                <span class="kpi-label">Pending Attendance</span>
                <div class="kpi-icon icon-pending">◷</div>
            </div>

            <strong class="kpi-value">
                <?= number_format($countPending) ?>
            </strong>

            <span class="kpi-description">
                Waiting for verification
            </span>

        </div>


        <div class="kpi-card">

            <div class="kpi-top">
                <span class="kpi-label">Approved Reports</span>
                <div class="kpi-icon icon-approved">✓</div>
            </div>

            <strong class="kpi-value">
                <?= number_format($countApproved) ?>
            </strong>

            <span class="kpi-description">
                <?= number_format($approvalRate) ?>% approval rate
            </span>

        </div>


        <div class="kpi-card">

            <div class="kpi-top">
                <span class="kpi-label">Total Stubs Issued</span>
                <div class="kpi-icon icon-stubs">▤</div>
            </div>

            <strong class="kpi-value">
                <?= number_format($totalStubs) ?>
            </strong>

            <span class="kpi-description">
                Across all issued batches
            </span>

        </div>


        <div class="kpi-card">

            <div class="kpi-top">
                <span class="kpi-label">Rejected Reports</span>
                <div class="kpi-icon icon-rejected">!</div>
            </div>

            <strong class="kpi-value">
                <?= number_format($countRejected) ?>
            </strong>

            <span class="kpi-description">
                Reports requiring attention
            </span>

        </div>

    </section>


    <section class="summary-strip">

        <div class="summary-copy">

            <strong>Attendance Review Summary</strong>

            <small>
                Based on all reviewed attendance reports.
            </small>

        </div>

        <div class="summary-rate">
            <?= number_format($approvalRate) ?>%
        </div>

    </section>


    <section class="panel">

        <div class="panel-heading">

            <div>
                <h2>Recent Submissions</h2>

                <p>
                    Latest attendance reports from class secretaries
                </p>
            </div>

            <a href="attendance_verification.php" class="view-all">
                View all reports →
            </a>

        </div>


        <?php if (empty($reports)): ?>

            <div class="empty-state">

                <div class="empty-icon">▤</div>

                <h3>No attendance reports yet</h3>

                <p>
                    Submitted attendance reports will appear here.
                </p>

            </div>

        <?php else: ?>

            <div class="table-wrapper">

                <table>

                    <thead>
                        <tr>
                            <th>Date Submitted</th>
                            <th>Secretary</th>
                            <th>Course & Section</th>
                            <th>Present</th>
                            <th>Absent</th>
                            <th>Status</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php foreach ($reports as $r): ?>

                            <tr>

                                <td class="date-cell">
                                    <?= e($r['report_date']) ?>
                                </td>

                                <td class="secretary-cell">
                                    <?= e($r['secretary_name'] ?? 'Unknown') ?>
                                </td>

                                <td class="course-cell">
                                    <strong><?= e($r['course']) ?></strong>
                                    - <?= e($r['section']) ?>
                                </td>

                                <td>
                                    <span class="count-present">
                                        <?= (int)$r['present_count'] ?>
                                    </span>
                                </td>

                                <td>
                                    <span class="count-absent">
                                        <?= (int)($r['absent_count'] ?? 0) ?>
                                    </span>
                                </td>

                                <td>
                                    <span class="badge <?= statusBadgeClass($r['status'] ?? 'pending') ?>">
                                        <?= e(ucfirst($r['status'] ?? 'pending')) ?>
                                    </span>
                                </td>

                            </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        <?php endif; ?>

    </section>


    <footer class="page-footer">

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
