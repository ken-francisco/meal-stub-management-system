
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

$totalStubs = 0;
$unclaimedStubs = 0;
$distributedStubs = 0;
$batches = [];
$pageError = '';

try {
    /*
 * Keep batch status synchronized with its individual meal stubs.
 * A batch is completed only when it has at least one stub and every
 * stub in that batch has status Distributed.
 */
$pdo->exec("
    UPDATE stub_batches
    SET status = CASE
        WHEN EXISTS (
            SELECT 1
            FROM meal_stubs ms
            WHERE ms.batch_id = stub_batches.id
        )
        AND NOT EXISTS (
            SELECT 1
            FROM meal_stubs ms
            WHERE ms.batch_id = stub_batches.id
              AND LOWER(TRIM(ms.status)) <> 'distributed'
        )
        THEN 'completed'
        ELSE 'issued'
    END
");

    // Overall stub statistics
    $statsQuery = $pdo->query("
        SELECT
            COUNT(*) AS total_stubs,
            SUM(
                CASE WHEN LOWER(status) = 'unclaimed'
                THEN 1 ELSE 0 END
            ) AS unclaimed_stubs,
            SUM(
                CASE WHEN LOWER(status) = 'distributed'
                THEN 1 ELSE 0 END
            ) AS distributed_stubs
        FROM meal_stubs
    ");

    $stats = $statsQuery->fetch(PDO::FETCH_ASSOC);

    $totalStubs = (int)($stats['total_stubs'] ?? 0);
    $unclaimedStubs = (int)($stats['unclaimed_stubs'] ?? 0);
    $distributedStubs = (int)($stats['distributed_stubs'] ?? 0);

    // Recent issued batches
    $batchQuery = $pdo->query("
        SELECT
            sb.id,
            sb.stubs_issued,
            sb.issue_date,
            sb.status AS batch_status,
            ar.course,
            ar.year_level,
            ar.section,
            ar.report_date,
            COUNT(ms.id) AS actual_stubs,
            SUM(
                CASE WHEN LOWER(ms.status) = 'unclaimed'
                THEN 1 ELSE 0 END
            ) AS unclaimed,
            SUM(
                CASE WHEN LOWER(ms.status) = 'distributed'
                THEN 1 ELSE 0 END
            ) AS distributed
        FROM stub_batches sb
        LEFT JOIN attendance_reports ar
            ON sb.attendance_report_id = ar.id
        LEFT JOIN meal_stubs ms
            ON ms.batch_id = sb.id
        GROUP BY
            sb.id,
            sb.stubs_issued,
            sb.issue_date,
            sb.status,
            ar.course,
            ar.year_level,
            ar.section,
            ar.report_date
        ORDER BY sb.id DESC
    ");

    $batches = $batchQuery->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log('Stub Monitoring Error: ' . $e->getMessage());
    $pageError = 'Unable to load stub monitoring records.';
}

$distributionRate = $totalStubs > 0
    ? round(($distributedStubs / $totalStubs) * 100)
    : 0;

$staffName = $_SESSION['name'] ?? $_SESSION['full_name'] ?? 'PSAS Staff';
$initial = strtoupper(substr($staffName, 0, 1));
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Stub Monitoring | LVCC Meal Stub System</title>

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
    font-family: "DM Sans", sans-serif;
    color: var(--text);
    background: var(--bg);
    display: flex;
    min-height: 100vh;
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
    padding: 20px;
    display: flex;
    flex-direction: column;
    z-index: 10;
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
    margin-left: 264px;
    width: calc(100% - 264px);
    min-height: 100vh;
    padding: 40px;
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
    margin-bottom: 28px;
}

.eyebrow {
    color: var(--blue);
    font-size: 10px;
    font-weight: 800;
    letter-spacing: .14em;
    text-transform: uppercase;
}

.page-title {
    font-family: "Manrope", sans-serif;
    font-size: 28px;
    margin: 6px 0;
    color: var(--navy);
}

.page-description {
    color: var(--muted);
    font-size: 13px;
    margin: 0;
}

.date-pill {
    background: white;
    border: 1px solid var(--border);
    border-radius: 9px;
    padding: 12px 17px;
    font-size: 12px;
    color: var(--muted);
    white-space: nowrap;
}

/* SUMMARY CARDS */
.kpi-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 18px;
    margin-bottom: 28px;
}

.kpi-card {
    background: rgba(255,255,255,.94);
    padding: 24px;
    border: 1px solid rgba(221,227,236,.9);
    border-radius: 12px;
    box-shadow: 0 5px 18px rgba(0,32,91,.06);
    transition: transform .25s ease, box-shadow .25s ease;
    position: relative;
    overflow: hidden;
}

.kpi-card:hover {
    transform: translateY(-6px);
    box-shadow: 0 14px 28px rgba(0,32,91,.13);
}

.kpi-card::before {
    content: "";
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 4px;
}

.kpi-card.total::before {
    background: var(--blue);
}

.kpi-card.unclaimed::before {
    background: var(--orange);
}

.kpi-card.distributed::before {
    background: var(--green);
}

.kpi-top {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 16px;
}

.kpi-card h3 {
    font-size: 10px;
    color: var(--muted);
    text-transform: uppercase;
    letter-spacing: .06em;
    margin: 0;
}

.kpi-icon {
    width: 39px;
    height: 39px;
    border-radius: 10px;
    display: grid;
    place-items: center;
    font-size: 18px;
    font-weight: 700;
}

.total .kpi-icon {
    background: #e8efff;
    color: var(--blue);
}

.unclaimed .kpi-icon {
    background: #fff1cf;
    color: var(--orange);
}

.distributed .kpi-icon {
    background: #def7ec;
    color: var(--green);
}

.kpi-card strong {
    font-family: "Manrope", sans-serif;
    font-size: 30px;
    display: block;
    color: var(--navy);
}

.kpi-card p {
    color: var(--muted);
    font-size: 11px;
    margin: 6px 0 0;
}

/* DISTRIBUTION SUMMARY */
.summary-banner {
    background: linear-gradient(110deg, #00205b, #073f9c);
    color: white;
    border-radius: 12px;
    padding: 20px 24px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 20px;
    margin-bottom: 28px;
    box-shadow: 0 7px 18px rgba(0,32,91,.15);
}

.summary-banner b {
    font-family: "Manrope", sans-serif;
    font-size: 14px;
}

.summary-banner p {
    color: #d1e0f3;
    font-size: 11px;
    margin: 5px 0 0;
}

.summary-rate {
    font-family: "Manrope", sans-serif;
    font-size: 28px;
    font-weight: 800;
    color: var(--yellow);
}

/* TABLE PANEL */
.panel {
    background: rgba(255,255,255,.95);
    border: 1px solid rgba(221,227,236,.9);
    border-radius: 12px;
    padding: 25px;
    box-shadow: 0 5px 18px rgba(0,32,91,.06);
    transition: transform .25s ease, box-shadow .25s ease;
}

.panel:hover {
    transform: translateY(-5px);
    box-shadow: 0 14px 28px rgba(0,32,91,.12);
}

.panel h3 {
    font-family: "Manrope", sans-serif;
    color: var(--navy);
    font-size: 16px;
    margin: 0 0 6px;
}

.panel-subtitle {
    color: var(--muted);
    font-size: 12px;
    margin: 0 0 20px;
}

.table-wrapper {
    width: 100%;
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    font-size: 12px;
    min-width: 850px;
}

th {
    background: #f1f4f9;
    color: #718198;
    padding: 15px 12px;
    text-align: left;
    font-size: 10px;
    text-transform: uppercase;
    letter-spacing: .04em;
}

td {
    padding: 15px 12px;
    border-bottom: 1px solid #edf1f4;
    color: var(--text);
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

.badge {
    display: inline-block;
    padding: 5px 10px;
    border-radius: 20px;
    font-size: 10px;
    font-weight: 800;
    text-transform: uppercase;
}

.badge-issued {
    background: #fff1cf;
    color: #9a6700;
}

.badge-completed {
    background: #def7ec;
    color: #03543f;
}

.empty {
    text-align: center;
    color: var(--muted);
    padding: 35px;
    font-style: italic;
}

.alert-error {
    background: #fee2e2;
    color: #991b1b;
    border: 1px solid #fecaca;
    padding: 15px 18px;
    border-radius: 9px;
    margin-bottom: 22px;
    font-size: 13px;
    font-weight: 600;
}

.footer {
    color: #8291a5;
    font-size: 10px;
    display: flex;
    justify-content: space-between;
    margin-top: 35px;
}

/* RESPONSIVE */
@media (max-width: 1000px) {
    .kpi-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 750px) {
    .sidebar {
        width: 220px;
    }

    .main-content {
        margin-left: 220px;
        width: calc(100% - 220px);
        padding: 25px;
    }

    .main-content::before,
    .main-content::after {
        left: 220px;
    }

    .kpi-grid {
        grid-template-columns: 1fr;
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
        margin-left: 0;
        width: 100%;
        padding: 20px;
    }

    .main-content::before,
    .main-content::after {
        inset: 0;
    }

    .page-header {
        align-items: flex-start;
        flex-direction: column;
    }

    .summary-banner {
        align-items: flex-start;
        flex-direction: column;
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
        <a href="psas_dashboard.php">▦ &nbsp; Overview</a>
        <a href="attendance_verification.php">✓ &nbsp; Attendance Verification</a>
        <a href="stub_issuance.php">▤ &nbsp; Stub Issuance</a>
        <a href="stub_monitoring.php" class="active">◷ &nbsp; Stub Monitoring</a>
        <a href="reports_audit.php">▥ &nbsp; Reports & Audit</a>
        <a href="user_management.php">♟ &nbsp; User Management</a>
    </nav>

    <div class="sidebar-bottom">
        <div class="profile">
            <div class="profile-icon">
                <?= htmlspecialchars($initial) ?>
            </div>

            <div class="profile-info">
                <b><?= htmlspecialchars($staffName) ?></b>
                <small>PSAS Administrator</small>
            </div>
        </div>

        <a href="logout.php" class="logout">↪ Logout</a>
    </div>

</aside>

<main class="main-content">

    <div class="page-header">
        <div>
            <span class="eyebrow">PSAS Staff Portal</span>
            <h1 class="page-title">Stub Monitoring</h1>
            <p class="page-description">
                Monitor meal stub issuance and distribution across all sections.
            </p>
        </div>

        <div class="date-pill">
            ◷ &nbsp; <?= date('F d, Y') ?>
        </div>
    </div>

    <?php if ($pageError): ?>
        <div class="alert-error">
            <?= htmlspecialchars($pageError) ?>
        </div>
    <?php endif; ?>

    <!-- SUMMARY CARDS -->
    <section class="kpi-grid">

        <div class="kpi-card total">
            <div class="kpi-top">
                <h3>Total Stubs Issued</h3>
                <div class="kpi-icon">▤</div>
            </div>

            <strong><?= number_format($totalStubs) ?></strong>
            <p>All generated meal stub records</p>
        </div>

        <div class="kpi-card unclaimed">
            <div class="kpi-top">
                <h3>Unclaimed Stubs</h3>
                <div class="kpi-icon">◷</div>
            </div>

            <strong><?= number_format($unclaimedStubs) ?></strong>
            <p>Stubs not yet distributed</p>
        </div>

        <div class="kpi-card distributed">
            <div class="kpi-top">
                <h3>Distributed Stubs</h3>
                <div class="kpi-icon">✓</div>
            </div>

            <strong><?= number_format($distributedStubs) ?></strong>
            <p>Stubs already distributed to students</p>
        </div>

    </section>

    <!-- DISTRIBUTION SUMMARY -->
    <section class="summary-banner">
        <div>
            <b>Stub Distribution Summary</b>
            <p>
                Percentage of issued stubs that have already been distributed.
            </p>
        </div>

        <div class="summary-rate">
            <?= $distributionRate ?>%
        </div>
    </section>

    <!-- BATCH MONITORING TABLE -->
    <section class="panel">

        <h3>Issued Batch Records</h3>

        <p class="panel-subtitle">
            Monitor the actual number of generated, unclaimed, and distributed stubs per section.
        </p>

        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Batch ID</th>
                        <th>Course & Section</th>
                        <th>Attendance Date</th>
                        <th>Issued</th>
                        <th>Actual Stubs</th>
                        <th>Unclaimed</th>
                        <th>Distributed</th>
                        <th>Batch Status</th>
                    </tr>
                </thead>

                <tbody>

                <?php if (count($batches) > 0): ?>

                    <?php foreach ($batches as $batch): ?>

                        <tr>
                            <td>
                                <strong>#<?= (int)$batch['id'] ?></strong>
                            </td>

                            <td>
                                <strong>
                                    <?= htmlspecialchars($batch['course'] ?? 'N/A') ?>
                                </strong>
                                <?= htmlspecialchars(
                                    ($batch['year_level'] ?? '') . ' - ' .
                                    ($batch['section'] ?? '')
                                ) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($batch['report_date'] ?? 'N/A') ?>
                            </td>

                            <td>
                                <?= number_format((int)$batch['stubs_issued']) ?>
                            </td>

                            <td>
                                <strong>
                                    <?= number_format((int)$batch['actual_stubs']) ?>
                                </strong>
                            </td>

                            <td>
                                <?= number_format((int)($batch['unclaimed'] ?? 0)) ?>
                            </td>

                            <td>
                                <strong style="color:var(--green);">
                                    <?= number_format((int)($batch['distributed'] ?? 0)) ?>
                                </strong>
                            </td>

                            <td>
                                <span class="badge <?= strtolower($batch['batch_status'] ?? '') === 'completed' ? 'badge-completed' : 'badge-issued' ?>">
                                    <?= htmlspecialchars(strtoupper($batch['batch_status'] ?? 'ISSUED')) ?>
                                </span>
                            </td>
                        </tr>

                    <?php endforeach; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="8" class="empty">
                            No meal stub batches have been issued yet.
                        </td>
                    </tr>

                <?php endif; ?>

                </tbody>
            </table>
        </div>

    </section>

    <div class="footer">
        <span>© <?= date('Y') ?> La Verdad Christian College</span>
        <span>Meal Stub Management System · PSAS Portal</span>
    </div>

</main>

</body>
</html>
