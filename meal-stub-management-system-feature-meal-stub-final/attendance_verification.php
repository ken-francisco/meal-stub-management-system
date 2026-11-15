
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

$success = '';
$error = '';

$staffName = (string)(
    $_SESSION['name'] ??
    $_SESSION['full_name'] ??
    'PSAS Staff'
);

$initials = strtoupper(substr(trim($staffName), 0, 1));

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// PROCESS APPROVAL OR REJECTION
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $token = $_POST['csrf_token'] ?? '';
    $reportId = filter_var(
        $_POST['report_id'] ?? null,
        FILTER_VALIDATE_INT
    );

    $action = $_POST['action'] ?? '';
    $reviewRemarks = trim($_POST['review_remarks'] ?? '');

    if (
        !is_string($token) ||
        !hash_equals($_SESSION['csrf_token'], $token)
    ) {
        $error = 'Invalid request. Please refresh the page.';

    } elseif ($reportId === false || $reportId < 1) {
        $error = 'Invalid attendance report.';

    } elseif (!in_array($action, ['approve', 'reject'], true)) {
        $error = 'Invalid review action.';

    } elseif ($action === 'reject' && $reviewRemarks === '') {
        $error = 'Please provide a reason before rejecting the report.';

    } elseif (strlen($reviewRemarks) > 1000) {
        $error = 'Review remarks must not exceed 1,000 characters.';

    } else {

        try {
            $newStatus = $action === 'approve'
                ? 'approved'
                : 'rejected';

            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                UPDATE attendance_reports
                SET
                    status = ?,
                    reviewed_by = ?,
                    reviewed_at = CURRENT_TIMESTAMP,
                    review_remarks = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
                  AND status = 'pending'
            ");

            $stmt->execute([
                $newStatus,
                $_SESSION['user_id'],
                $reviewRemarks !== '' ? $reviewRemarks : null,
                $reportId
            ]);

            if ($stmt->rowCount() !== 1) {
                $pdo->rollBack();
                $error = 'This report was already reviewed or no longer exists.';

            } else {
                $pdo->commit();

                $success = $action === 'approve'
                    ? 'Attendance report approved successfully!'
                    : 'Attendance report rejected successfully!';

                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            }

        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log('Attendance review error: ' . $e->getMessage());
            $error = 'Unable to process the attendance review. Please try again.';
        }
    }
}

// GET ATTENDANCE REPORTS
$reports = [];
$pendingCount = 0;
$approvedCount = 0;
$rejectedCount = 0;

try {
    $stmt = $pdo->query("
        SELECT
            ar.*,
            u.full_name AS secretary_name,
            reviewer.full_name AS reviewer_name
        FROM attendance_reports ar
        LEFT JOIN users u
            ON ar.secretary_id = u.id
        LEFT JOIN users reviewer
            ON ar.reviewed_by = reviewer.id
        ORDER BY ar.report_date DESC, ar.id DESC
    ");

    $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($reports as $report) {
        switch (strtolower((string)$report['status'])) {
            case 'approved':
                $approvedCount++;
                break;

            case 'rejected':
                $rejectedCount++;
                break;

            default:
                $pendingCount++;
                break;
        }
    }

} catch (PDOException $e) {
    error_log('Attendance report loading error: ' . $e->getMessage());
    $error = 'Unable to load attendance reports.';
}

function e($value) {
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}

function getStatusClass($status) {
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

<title>Attendance Verification | LVCC Meal Stub System</title>

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

/* PAGE HEADER */

.page-heading {
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
    gap: 20px;
    margin-bottom: 26px;
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

/* ALERTS */

.alert {
    padding: 14px 16px;
    border-radius: 9px;
    margin-bottom: 20px;
    font-size: 13px;
    font-weight: 600;
}

.alert-success {
    background: #def7ec;
    color: #03543f;
    border: 1px solid #b7ead2;
}

.alert-error {
    background: #fee2e2;
    color: #991b1b;
    border: 1px solid #f3c4c4;
}

/* SUMMARY CARDS */

.summary-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 17px;
    margin-bottom: 25px;
}

.summary-card {
    padding: 20px;
    background: rgba(255,255,255,.96);
    border: 1px solid var(--border);
    border-radius: 12px;
    box-shadow: 0 4px 14px rgba(15,35,70,.06);
}

.summary-card-top {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 13px;
}

.summary-label {
    color: var(--muted);
    font-size: 10px;
    font-weight: 800;
    letter-spacing: .6px;
    text-transform: uppercase;
}

.summary-icon {
    width: 35px;
    height: 35px;
    display: grid;
    place-items: center;
    border-radius: 9px;
    font-size: 16px;
}

.icon-pending {
    background: #fef3c7;
    color: var(--orange);
}

.icon-approved {
    background: #def7ec;
    color: var(--green);
}

.icon-rejected {
    background: #fee2e2;
    color: var(--red);
}

.summary-number {
    display: block;
    font-family: 'Manrope', sans-serif;
    font-size: 28px;
    font-weight: 800;
    color: var(--navy);
}


.summary-card {
    transition: transform .25s ease, box-shadow .25s ease;
    cursor: pointer;
}

.summary-card:hover {
    transform: translateY(-6px);
    box-shadow: 0 12px 25px rgba(0, 32, 91, 0.13);
}


.summary-description {
    display: block;
    margin-top: 5px;
    color: var(--muted);
    font-size: 11px;
}

/* PANEL */

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
    margin-bottom: 18px;
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

.report-count {
    padding: 7px 11px;
    border-radius: 20px;
    background: #eaf0fa;
    color: var(--blue);
    font-size: 10px;
    font-weight: 800;
    white-space: nowrap;
}

/* TABLE */

.table-wrapper {
    width: 100%;
    overflow-x: auto;
}

table {
    width: 100%;
    min-width: 950px;
    border-collapse: collapse;
    font-size: 12px;
}

thead th {
    padding: 13px 12px;
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
    padding: 15px 12px;
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
    white-space: nowrap;
}

.course-cell strong {
    color: var(--navy);
    font-weight: 800;
}

.secretary-cell {
    font-weight: 600;
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
    white-space: nowrap;
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

/* REVIEW DETAILS */

.review-details {
    max-width: 190px;
    color: var(--muted);
    font-size: 10px;
    line-height: 1.6;
}

.review-details strong {
    color: var(--text);
}

.review-remarks {
    display: block;
    margin-top: 5px;
    color: #64748b;
    font-style: italic;
    overflow-wrap: anywhere;
}

/* ACTION BUTTONS */

.action-buttons {
    display: flex;
    align-items: center;
    gap: 6px;
    white-space: nowrap;
}

.btn {
    border: none;
    padding: 8px 11px;
    border-radius: 7px;
    font-family: 'DM Sans', sans-serif;
    font-size: 10px;
    font-weight: 800;
    cursor: pointer;
    transition: .2s;
}

.btn-approve {
    background: #17835b;
    color: white;
}

.btn-approve:hover {
    background: #116644;
    transform: translateY(-1px);
}

.btn-reject {
    background: #c84646;
    color: white;
}

.btn-reject:hover {
    background: #a83232;
    transform: translateY(-1px);
}

.reviewed-label {
    color: var(--muted);
    font-size: 10px;
    font-style: italic;
}

/* EMPTY STATE */

.empty-state {
    padding: 45px 15px;
    text-align: center;
}

.empty-icon {
    width: 55px;
    height: 55px;
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

/* REJECTION MODAL */

.modal {
    display: none;
    position: fixed;
    inset: 0;
    z-index: 1000;
    align-items: center;
    justify-content: center;
    padding: 20px;
    background: rgba(0, 20, 55, .58);
    backdrop-filter: blur(4px);
}

.modal-content {
    width: 100%;
    max-width: 460px;
    padding: 27px;
    border-radius: 14px;
    background: white;
    box-shadow: 0 15px 45px rgba(0,0,0,.18);
    animation: modalIn .2s ease;
}

@keyframes modalIn {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.modal-content h2 {
    color: var(--navy);
    font-family: 'Manrope', sans-serif;
    font-size: 18px;
}

.modal-content p {
    margin-top: 7px;
    color: var(--muted);
    font-size: 12px;
    line-height: 1.6;
}

.modal-content label {
    display: block;
    margin: 20px 0 7px;
    color: var(--text);
    font-size: 11px;
    font-weight: 700;
}

.modal-content textarea {
    width: 100%;
    min-height: 120px;
    resize: vertical;
    padding: 12px;
    border: 1px solid var(--border);
    border-radius: 8px;
    outline: none;
    font: 13px 'DM Sans', sans-serif;
}

.modal-content textarea:focus {
    border-color: var(--blue);
    box-shadow: 0 0 0 3px #00308712;
}

.modal-actions {
    display: flex;
    justify-content: flex-end;
    gap: 9px;
    margin-top: 18px;
}

.btn-cancel {
    background: #edf1f5;
    color: #475569;
    padding: 10px 15px;
}

.btn-confirm {
    background: var(--red);
    color: white;
    padding: 10px 15px;
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

@media (max-width: 1100px) {
    .main-content {
        padding: 28px 24px;
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

    .summary-grid {
        grid-template-columns: 1fr;
        gap: 11px;
    }

    .panel {
        padding: 17px;
    }

    .page-footer {
        flex-direction: column;
    }
}

@media print {
    body {
        display: block;
        background: white;
    }

    .sidebar,
    .topbar,
    .date-label,
    .page-footer,
    .action-buttons,
    .modal {
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

    .panel,
    .summary-card {
        box-shadow: none;
    }

    table {
        min-width: 100%;
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

        <a href="psas_dashboard.php">
            <span class="nav-left">
                <span class="nav-icon">▦</span>
                Overview
            </span>
        </a>

        <a href="attendance_verification.php" class="active">
            <span class="nav-left">
                <span class="nav-icon">✓</span>
                Attendance Verification
            </span>

            <span class="nav-badge">
                <?= number_format($pendingCount) ?>
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


<!-- MAIN CONTENT -->

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


    <!-- PAGE HEADING -->

    <section class="page-heading">

        <div>

            <span class="page-eyebrow">
                PSAS Staff Portal
            </span>

            <h1>Attendance Verification</h1>

            <p>
                Review and verify daily attendance submitted by class secretaries.
            </p>

        </div>

        <div class="date-label">
            ◷ &nbsp; <?= date('F d, Y') ?>
        </div>

    </section>


    <!-- ALERT MESSAGES -->

    <?php if ($success !== ''): ?>
        <div class="alert alert-success">
            ✓ &nbsp; <?= e($success) ?>
        </div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <div class="alert alert-error">
            ! &nbsp; <?= e($error) ?>
        </div>
    <?php endif; ?>


    <!-- SUMMARY CARDS -->

    <section class="summary-grid">

        <div class="summary-card">

            <div class="summary-card-top">
                <span class="summary-label">Pending Reports</span>
                <div class="summary-icon icon-pending">◷</div>
            </div>

            <strong class="summary-number">
                <?= number_format($pendingCount) ?>
            </strong>

            <span class="summary-description">
                Waiting for verification
            </span>

        </div>


        <div class="summary-card">

            <div class="summary-card-top">
                <span class="summary-label">Approved Reports</span>
                <div class="summary-icon icon-approved">✓</div>
            </div>

            <strong class="summary-number">
                <?= number_format($approvedCount) ?>
            </strong>

            <span class="summary-description">
                Successfully verified
            </span>

        </div>


        <div class="summary-card">

            <div class="summary-card-top">
                <span class="summary-label">Rejected Reports</span>
                <div class="summary-icon icon-rejected">!</div>
            </div>

            <strong class="summary-number">
                <?= number_format($rejectedCount) ?>
            </strong>

            <span class="summary-description">
                Returned for correction
            </span>

        </div>

    </section>


    <!-- ATTENDANCE TABLE -->

    <section class="panel">

        <div class="panel-heading">

            <div>
                <h2>All Attendance Submissions</h2>

                <p>
                    Review reports and manage their approval status.
                </p>
            </div>

            <span class="report-count">
                <?= number_format(count($reports)) ?> Reports
            </span>

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
                            <th>Course & Section</th>
                            <th>Submitted By</th>
                            <th>Present</th>
                            <th>Absent</th>
                            <th>Status</th>
                            <th>Review Details</th>
                            <th>Actions</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php foreach ($reports as $r): ?>

                            <tr>

                                <td class="date-cell">
                                    <?= e($r['report_date']) ?>
                                </td>

                                <td class="course-cell">
                                    <strong><?= e($r['course']) ?></strong>
                                    - <?= e($r['section']) ?>
                                </td>

                                <td class="secretary-cell">
                                    <?= e($r['secretary_name'] ?? 'Unknown') ?>
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
                                    <span class="badge <?= getStatusClass($r['status']) ?>">
                                        <?= e(ucfirst($r['status'])) ?>
                                    </span>
                                </td>

                                <td>

                                    <?php if (!empty($r['reviewed_at'])): ?>

                                        <div class="review-details">

                                            <strong>Reviewed by:</strong><br>

                                            <?= e($r['reviewer_name'] ?? 'PSAS') ?>

                                            <br>

                                            <?= e($r['reviewed_at']) ?>

                                            <?php if (!empty($r['review_remarks'])): ?>

                                                <span class="review-remarks">
                                                    <?= e($r['review_remarks']) ?>
                                                </span>

                                            <?php endif; ?>

                                        </div>

                                    <?php else: ?>

                                        <span class="reviewed-label">
                                            Not yet reviewed
                                        </span>

                                    <?php endif; ?>

                                </td>

                                <td>

                                    <?php if (strtolower($r['status']) === 'pending'): ?>

                                        <div class="action-buttons">

                                            <form
                                                method="POST"
                                                onsubmit="return confirm('Are you sure you want to approve this attendance report?');"
                                            >

                                                <input
                                                    type="hidden"
                                                    name="csrf_token"
                                                    value="<?= e($_SESSION['csrf_token']) ?>"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="report_id"
                                                    value="<?= (int)$r['id'] ?>"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="action"
                                                    value="approve"
                                                >

                                                <button
                                                    type="submit"
                                                    class="btn btn-approve"
                                                >
                                                    Approve
                                                </button>

                                            </form>


                                            <button
                                                type="button"
                                                class="btn btn-reject"
                                                onclick="openRejectModal(<?= (int)$r['id'] ?>)"
                                            >
                                                Reject
                                            </button>

                                        </div>

                                    <?php else: ?>

                                        <span class="reviewed-label">
                                            No actions available
                                        </span>

                                    <?php endif; ?>

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


<!-- REJECTION MODAL -->

<div id="rejectModal" class="modal">

    <div class="modal-content">

        <h2>Reject Attendance Report</h2>

        <p>
            Please provide a reason for rejecting this attendance report.
            The class secretary should be able to understand what needs correction.
        </p>

        <form
            method="POST"
            onsubmit="return confirm('Confirm rejection of this attendance report?');"
        >

            <input
                type="hidden"
                name="csrf_token"
                value="<?= e($_SESSION['csrf_token']) ?>"
            >

            <input
                type="hidden"
                name="report_id"
                id="rejectReportId"
            >

            <input
                type="hidden"
                name="action"
                value="reject"
            >

            <label for="reviewRemarks">
                Reason for Rejection *
            </label>

            <textarea
                id="reviewRemarks"
                name="review_remarks"
                required
                maxlength="1000"
                placeholder="Enter reason for rejection..."
            ></textarea>

            <div class="modal-actions">

                <button
                    type="button"
                    class="btn btn-cancel"
                    onclick="closeRejectModal()"
                >
                    Cancel
                </button>

                <button
                    type="submit"
                    class="btn btn-confirm"
                >
                    Confirm Reject
                </button>

            </div>

        </form>

    </div>

</div>


<script>
function openRejectModal(reportId) {

    document.getElementById('rejectReportId').value = reportId;

    const modal = document.getElementById('rejectModal');

    modal.style.display = 'flex';

    document.getElementById('reviewRemarks').focus();
}

function closeRejectModal() {

    document.getElementById('rejectModal').style.display = 'none';
}

document.getElementById('rejectModal').addEventListener('click', function(event) {

    if (event.target === this) {
        closeRejectModal();
    }

});

document.addEventListener('keydown', function(event) {

    if (event.key === 'Escape') {
        closeRejectModal();
    }

});
</script>

</body>
</html>
