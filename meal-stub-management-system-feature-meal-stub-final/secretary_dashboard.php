
<?php
session_start();
require_once __DIR__ . '/db.php';

date_default_timezone_set('Asia/Manila');

// Secretary-only access
if (
    !isset($_SESSION['user_id']) ||
    ($_SESSION['role'] ?? '') !== 'secretary'
) {
    header('Location: login.php');
    exit;
}

$userId = (int) $_SESSION['user_id'];

$secName = (string)(
    $_SESSION['full_name'] ??
    $_SESSION['name'] ??
    'Class Secretary'
);

$firstName = explode(' ', trim($secName))[0] ?: 'Secretary';
$initials = strtoupper(substr($firstName, 0, 1));

$courseCode = '';
$yearLevel = '';
$assignedSection = '';
$sectionDisplay = 'No section assigned';

$reports = [];
$todayReport = null;

$todayPresent = 0;
$todayAbsent = 0;
$totalReports = 0;
$totalStubs = 0;

$dashboardError = '';

try {
    // Get secretary information and assigned section
    $stmt = $pdo->prepare("
        SELECT
            u.full_name,
            u.section_id,
            s.section_name,
            c.course_code,
            y.year_level
        FROM users u
        LEFT JOIN sections s ON u.section_id = s.id
        LEFT JOIN courses c ON s.course_id = c.id
        LEFT JOIN year_levels y ON s.year_id = y.id
        WHERE u.id = ?
          AND u.role = 'secretary'
        LIMIT 1
    ");

    $stmt->execute([$userId]);
    $secretary = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($secretary) {
        $secName = $secretary['full_name'] ?: $secName;
        $firstName = explode(' ', trim($secName))[0] ?: 'Secretary';
        $initials = strtoupper(substr($firstName, 0, 1));

        $courseCode = $secretary['course_code'] ?? '';
        $yearLevel = $secretary['year_level'] ?? '';
        $assignedSection = $secretary['section_name'] ?? '';

        if ($courseCode && $yearLevel) {
            $sectionDisplay = $courseCode . ' - ' . $yearLevel;

            // Avoid repeating the year when the section label duplicates it.
            $sameAsYear = strtolower(trim((string)$assignedSection))
                === strtolower(trim((string)$yearLevel));

            if ($assignedSection !== '' && !$sameAsYear) {
                $sectionDisplay .= ' - ' . $assignedSection;
            }
        }
    } else {
        throw new Exception('Secretary account information could not be found.');
    }

    // Count all attendance reports submitted by this secretary
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM attendance_reports
        WHERE secretary_id = ?
    ");

    $stmt->execute([$userId]);
    $totalReports = (int)$stmt->fetchColumn();

    // Get today's attendance report
    $stmt = $pdo->prepare("
        SELECT *
        FROM attendance_reports
        WHERE secretary_id = ?
          AND report_date = ?
        ORDER BY id DESC
        LIMIT 1
    ");

    $stmt->execute([
        $userId,
        date('Y-m-d')
    ]);

    $todayReport = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($todayReport) {
        $todayPresent = (int)$todayReport['present_count'];
        $todayAbsent = (int)($todayReport['absent_count'] ?? 0);
    }

    // Count total stubs issued from this secretary's reports
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(sb.stubs_issued), 0)
        FROM stub_batches sb
        INNER JOIN attendance_reports ar
            ON sb.attendance_report_id = ar.id
        WHERE ar.secretary_id = ?
    ");

    $stmt->execute([$userId]);
    $totalStubs = (int)$stmt->fetchColumn();

    // Get latest five attendance reports
    $stmt = $pdo->prepare("
        SELECT
            id,
            report_date,
            present_count,
            absent_count,
            status
        FROM attendance_reports
        WHERE secretary_id = ?
        ORDER BY report_date DESC, id DESC
        LIMIT 5
    ");

    $stmt->execute([$userId]);
    $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {
    error_log($e->getMessage());
    $dashboardError = 'Unable to load dashboard information. Please try again.';
}

// Status badge
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

function e($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

$todayFormatted = date('F d, Y');
?>

<!DOCTYPE html>
<html lang="en">
<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Secretary Dashboard | LVCC Meal Stub System</title>

<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@500;600;700;800&display=swap" rel="stylesheet">

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
    --red: #c84646;
}

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    min-height: 100vh;
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
    background: var(--navy);
    color: white;
    display: flex;
    flex-direction: column;
    padding: 25px 17px;
    z-index: 10;
    overflow-y: auto;
}

.brand {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 0 8px 25px;
    border-bottom: 1px solid rgba(255,255,255,.15);
}

.brand img {
    width: 48px;
    height: 48px;
    object-fit: contain;
    background: white;
    border-radius: 50%;
    padding: 3px;
    flex-shrink: 0;
}

.brand h2 {
    font-family: 'Manrope', sans-serif;
    font-size: 16px;
    margin: 0;
}

.brand span {
    font-size: 11px;
    color: #c4d1e6;
}

.nav-label {
    margin: 27px 10px 12px;
    color: #aabbd5;
    font-size: 10px;
    font-weight: 700;
    letter-spacing: 1.5px;
}

.nav-links {
    display: flex;
    flex-direction: column;
    gap: 7px;
}

.nav-links a {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 14px;
    border-radius: 9px;
    text-decoration: none;
    color: #d8e1ef;
    font-size: 13px;
    font-weight: 600;
    transition: .25s ease;
}

.nav-links a:hover {
    background: rgba(255,255,255,.12);
    color: white;
    transform: translateX(4px);
}

.nav-links a.active {
    background: var(--blue);
    color: white;
    box-shadow: 0 5px 15px rgba(0,0,0,.15);
}

.nav-icon {
    width: 20px;
    text-align: center;
    font-size: 16px;
}

.sidebar-bottom {
    margin-top: auto;
    border-top: 1px solid rgba(255,255,255,.15);
    padding-top: 18px;
}

.sidebar-profile {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 5px 7px 15px;
}

.sidebar-avatar {
    width: 38px;
    height: 38px;
    border-radius: 50%;
    background: var(--yellow);
    color: var(--navy);
    display: grid;
    place-items: center;
    font-weight: 800;
}

.sidebar-profile strong {
    display: block;
    font-size: 12px;
}

.sidebar-profile small {
    color: #bdcbe0;
    font-size: 11px;
}

.logout {
    display: block;
    text-decoration: none;
    color: #ffd1d1;
    font-size: 13px;
    padding: 10px;
    border-radius: 8px;
    transition: .2s;
}

.logout:hover {
    background: rgba(255,255,255,.1);
}

/* MAIN CONTENT */
.main-content {
    margin-left: 264px;
    min-height: 100vh;
    padding: 35px;
    background:
        linear-gradient(rgba(226,235,247,.70), rgba(226,235,247,.78)),
        url('assets/image/bg.jpg') center/cover fixed;
}

.content {
    max-width: 1400px;
    margin: auto;
}

/* TOPBAR */
.topbar {
    display: flex;
    justify-content: flex-end;
    align-items: center;
    margin-bottom: 25px;
}

.profile {
    display: flex;
    align-items: center;
    gap: 10px;
    border-left: 1px solid var(--border);
    padding-left: 18px;
}

.avatar {
    width: 38px;
    height: 38px;
    background: var(--yellow);
    border-radius: 50%;
    display: grid;
    place-items: center;
    font-weight: 800;
    font-size: 13px;
    color: var(--navy);
}

.profile strong {
    display: block;
    font-size: 12px;
}

.profile small {
    color: var(--muted);
    font-size: 11px;
}

/* PAGE HEADER */
.page-heading {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 18px;
    margin-bottom: 24px;
}

.eyebrow {
    color: var(--blue);
    font-size: 11px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: .08em;
}

.page-heading h1 {
    font-family: 'Manrope', sans-serif;
    font-size: 28px;
    color: var(--navy);
    margin: 6px 0;
}

.subtitle {
    color: var(--muted);
    font-size: 13px;
    margin: 4px 0 0;
}

.date-label {
    color: var(--muted);
    font-size: 12px;
    font-weight: 600;
    white-space: nowrap;
}

/* BUTTON */
.btn-primary {
    background: var(--blue);
    color: white;
    padding: 12px 18px;
    border-radius: 9px;
    text-decoration: none;
    font-weight: 700;
    font-size: 13px;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: .25s ease;
    white-space: nowrap;
    box-shadow: 0 4px 12px rgba(0,48,135,.13);
}

.btn-primary:hover {
    background: var(--navy);
    transform: translateY(-3px);
    box-shadow: 0 8px 18px rgba(0,48,135,.2);
}

/* ATTENDANCE BANNER */
.top-banner {
    background: rgba(255,255,255,.95);
    border: 1px solid var(--border);
    border-left: 5px solid var(--yellow);
    padding: 19px 22px;
    border-radius: 12px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
    margin-bottom: 24px;
    box-shadow: 0 5px 18px rgba(20,45,80,.04);
    transition: .25s ease;
}

.top-banner:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 22px rgba(20,45,80,.09);
}

.banner-title {
    color: var(--navy);
    font-size: 14px;
    font-weight: 800;
}

.banner-description {
    color: var(--muted);
    font-size: 12px;
    margin: 5px 0 0;
}

.banner-link {
    color: var(--blue);
    font-weight: 800;
    font-size: 12px;
    text-decoration: none;
    white-space: nowrap;
}

.banner-link:hover {
    text-decoration: underline;
}

/* KPI CARDS */
.kpi-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 18px;
    margin-bottom: 24px;
}

.kpi-card {
    background: rgba(255,255,255,.95);
    padding: 23px;
    border-radius: 13px;
    border: 1px solid var(--border);
    box-shadow: 0 5px 18px rgba(20,45,80,.05);
    transition: .25s ease;
}

.kpi-card:hover {
    transform: translateY(-6px);
    box-shadow: 0 13px 25px rgba(20,45,80,.12);
}

.kpi-card p {
    font-size: 11px;
    color: var(--muted);
    font-weight: 700;
    text-transform: uppercase;
    margin: 0 0 10px;
    letter-spacing: .04em;
}

.kpi-card strong {
    font-size: 27px;
    font-family: 'Manrope', sans-serif;
    color: var(--navy);
    display: block;
}

.kpi-card span {
    font-size: 11px;
    color: var(--muted);
    display: block;
    margin-top: 6px;
}

/* PANEL */
.panel {
    background: rgba(255,255,255,.96);
    border: 1px solid var(--border);
    border-radius: 13px;
    padding: 24px;
    box-shadow: 0 5px 18px rgba(20,45,80,.05);
    transition: .25s ease;
}

.panel:hover {
    box-shadow: 0 10px 25px rgba(20,45,80,.09);
}

.panel-heading {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 10px;
    margin-bottom: 5px;
}

.panel h3 {
    font-family: 'Manrope', sans-serif;
    font-size: 16px;
    color: var(--navy);
    margin: 0;
}

.panel-description {
    font-size: 12px;
    color: var(--muted);
    margin: 5px 0 20px;
}

/* TABLE */
.table-wrap {
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 420px;
}

th {
    padding: 12px;
    text-align: left;
    background: #f7f9fc;
    color: #758096;
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: .04em;
}

td {
    padding: 15px 12px;
    border-bottom: 1px solid #edf0f5;
    font-size: 13px;
}

tbody tr {
    transition: .2s ease;
}

tbody tr:hover {
    background: #f5f8fd;
}

.report-date {
    color: var(--text);
    font-weight: 700;
}

.report-count {
    color: var(--muted);
    font-size: 11px;
    margin-top: 5px;
}

/* STATUS BADGES */
.badge {
    display: inline-block;
    padding: 5px 10px;
    border-radius: 20px;
    font-size: 10px;
    font-weight: 800;
    text-transform: uppercase;
}

.badge-pending {
    background: #fff4d6;
    color: #a86b00;
}

.badge-approved {
    background: #def7ec;
    color: var(--green);
}

.badge-rejected {
    background: #fee2e2;
    color: var(--red);
}

.empty-state {
    text-align: center;
    color: var(--muted);
    padding: 35px 15px;
    font-size: 13px;
}

.empty-state strong {
    display: block;
    color: var(--navy);
    font-size: 14px;
    margin-bottom: 5px;
}

.alert-error {
    background: #fff0ef;
    color: #b42318;
    border: 1px solid #f5d0cc;
    padding: 14px 16px;
    border-radius: 9px;
    margin-bottom: 20px;
    font-size: 13px;
}

.section-warning {
    background: #fff8e5;
    border: 1px solid #f3dfa5;
    color: #805b00;
    padding: 13px 16px;
    border-radius: 9px;
    margin-bottom: 20px;
    font-size: 12px;
}

/* RESPONSIVE */
@media(max-width: 1000px) {
    .main-content {
        padding: 25px;
    }

    .kpi-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media(max-width: 760px) {
    .sidebar {
        position: relative;
        width: 100%;
        height: auto;
    }

    .nav-links {
        display: grid;
        grid-template-columns: 1fr 1fr;
    }

    .sidebar-bottom {
        margin-top: 20px;
    }

    .main-content {
        margin-left: 0;
        padding: 20px 15px;
    }

    .kpi-grid {
        grid-template-columns: 1fr;
    }

    .page-heading {
        align-items: flex-start;
        flex-direction: column;
    }

    .top-banner {
        align-items: flex-start;
        flex-direction: column;
    }

    .date-label {
        white-space: normal;
    }
}

@media(max-width: 480px) {
    .nav-links {
        grid-template-columns: 1fr;
    }

    .panel {
        padding: 17px;
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
            <h2>LVCC</h2>
            <span>Meal Stub System</span>
        </div>
    </div>

    <div class="nav-label">WORKSPACE</div>

    <nav class="nav-links">

        <a href="secretary_dashboard.php" class="active">
            <span class="nav-icon">▦</span>
            Section Overview
        </a>

        <a href="daily_attendance.php">
            <span class="nav-icon">✓</span>
            Daily Attendance
        </a>

        <a href="distribution_tracker.php">
            <span class="nav-icon">▤</span>
            Distribution Tracker
        </a>

    </nav>

    <div class="sidebar-bottom">

        <div class="sidebar-profile">
            <div class="sidebar-avatar"><?= e($initials) ?></div>

            <div>
                <strong><?= e($secName) ?></strong>
                <small>Class Secretary</small>
            </div>
        </div>

        <a href="logout.php" class="logout">↪ Logout</a>

    </div>

</aside>

<!-- MAIN CONTENT -->
<main class="main-content">

<div class="content">

    <!-- TOPBAR -->
    <div class="topbar">

        <div class="profile">

            <div class="avatar"><?= e($initials) ?></div>

            <div>
                <strong><?= e($secName) ?></strong>
                <small>Class Secretary</small>
            </div>

        </div>

    </div>

    <!-- PAGE HEADING -->
    <section class="page-heading">

        <div>
            <span class="eyebrow"><?= e($sectionDisplay) ?></span>

            <h1>Good day, <?= e($firstName) ?>!</h1>

            <p class="subtitle">
                Here's your section's meal program status for today.
            </p>
        </div>

        <div>
            <a href="daily_attendance.php" class="btn-primary">
                + Record Attendance
            </a>
        </div>

    </section>

    <div class="date-label" style="margin-bottom:20px;">
        <span>📅</span> <?= e($todayFormatted) ?>
    </div>

    <?php if ($dashboardError): ?>
        <div class="alert-error">
            <?= e($dashboardError) ?>
        </div>
    <?php endif; ?>

    <?php if (!$courseCode || !$yearLevel): ?>
        <div class="section-warning">
            <strong>Section not assigned.</strong>
            Please contact the PSAS administrator to assign your academic section.
        </div>
    <?php endif; ?>

    <!-- ATTENDANCE NOTICE -->
    <section class="top-banner">

        <div>

            <?php if ($todayReport): ?>

                <div class="banner-title">
                    ✓ Today's attendance has been submitted.
                </div>

                <p class="banner-description">
                    Current status:
                    <strong><?= e(ucfirst($todayReport['status'] ?? 'pending')) ?></strong>
                    — <?= number_format($todayPresent) ?> present and
                    <?= number_format($todayAbsent) ?> absent.
                </p>

            <?php else: ?>

                <div class="banner-title">
                    Today's attendance is awaiting submission.
                </div>

                <p class="banner-description">
                    Submit your class attendance to begin the meal stub process.
                </p>

            <?php endif; ?>

        </div>

        <a href="daily_attendance.php" class="banner-link">
            <?= $todayReport ? 'View attendance →' : 'Submit now →' ?>
        </a>

    </section>

    <!-- KPI CARDS -->
    <section class="kpi-grid">

        <div class="kpi-card">
            <p>Assigned Section</p>

            <strong style="font-size:21px;">
                <?= e($courseCode ?: 'N/A') ?>
            </strong>

            <span>
                <?= e($yearLevel ?: 'No year assigned') ?>
                <?php
                    $showSectionLabel = $assignedSection !== ''
                        && strtolower(trim((string)$assignedSection))
                            !== strtolower(trim((string)$yearLevel));
                ?>
                <?= $showSectionLabel ? ' · Section ' . e($assignedSection) : '' ?>
            </span>
        </div>

        <div class="kpi-card">
            <p>Today's Present Count</p>

            <strong>
                <?= $todayReport ? number_format($todayPresent) : '—' ?>
            </strong>

            <span>
                <?= $todayReport
                    ? 'Based on today’s submitted report'
                    : 'Not submitted yet' ?>
            </span>
        </div>

        <div class="kpi-card">
            <p>Total Reports Submitted</p>

            <strong><?= number_format($totalReports) ?></strong>

            <span>
                <?= number_format($totalStubs) ?>
                total stubs issued from your reports
            </span>
        </div>

    </section>

    <!-- RECENT ATTENDANCE -->
    <section class="panel">

        <div class="panel-heading">
            <h3>Recent Attendance</h3>
        </div>

        <p class="panel-description">
            Your last five attendance submissions
        </p>

        <?php if (empty($reports)): ?>

            <div class="empty-state">
                <strong>No attendance reports yet.</strong>
                Your submitted attendance records will appear here.
            </div>

        <?php else: ?>

            <div class="table-wrap">

                <table>

                    <thead>
                        <tr>
                            <th>Report Date</th>
                            <th>Attendance</th>
                            <th style="text-align:right;">Status</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php foreach ($reports as $r): ?>

                            <tr>

                                <td>
                                    <div class="report-date">
                                        <?= e(date('M d, Y', strtotime($r['report_date']))) ?>
                                    </div>
                                </td>

                                <td>
                                    <div>
                                        <?= number_format((int)$r['present_count']) ?> present
                                        ·
                                        <?= number_format((int)($r['absent_count'] ?? 0)) ?> absent
                                    </div>
                                </td>

                                <td style="text-align:right;">

                                    <span class="badge <?= e(statusBadgeClass($r['status'] ?? 'pending')) ?>">
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

</div>

</main>

</body>
</html>
