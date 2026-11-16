
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

$message = '';
$messageType = '';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/* PROCESS STUB ISSUANCE */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $token = $_POST['csrf_token'] ?? '';
    $reportId = filter_var(
        $_POST['attendance_report_id'] ?? null,
        FILTER_VALIDATE_INT
    );

    if (
        !is_string($token) ||
        !hash_equals($_SESSION['csrf_token'], $token)
    ) {
        $message = 'Invalid request. Please refresh the page.';
        $messageType = 'error';

    } elseif ($reportId === false || $reportId < 1) {
        $message = 'Please select a valid attendance report.';
        $messageType = 'error';

    } else {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                SELECT *
                FROM attendance_reports
                WHERE id = ?
                  AND status = 'approved'
                LIMIT 1
            ");
            $stmt->execute([$reportId]);
            $report = $stmt->fetch();

            if (!$report) {
                throw new Exception(
                    'Attendance report not found or not yet approved.'
                );
            }

            $quantity = (int)$report['present_count'];

            if ($quantity < 1) {
                throw new Exception(
                    'Cannot issue meal stubs because the present count is zero.'
                );
            }

            // Prevent duplicate issuance
            $check = $pdo->prepare("
                SELECT id
                FROM stub_batches
                WHERE attendance_report_id = ?
                LIMIT 1
            ");
            $check->execute([$reportId]);

            if ($check->fetch()) {
                throw new Exception(
                    'Meal stubs have already been issued for this report.'
                );
            }

            // Create batch
            $batchStmt = $pdo->prepare("
                INSERT INTO stub_batches (
                    attendance_report_id,
                    psas_staff_id,
                    stubs_issued,
                    issue_date,
                    status
                )
                VALUES (?, ?, ?, ?, 'issued')
            ");

            $batchStmt->execute([
                $reportId,
                $_SESSION['user_id'],
                $quantity,
                date('Y-m-d')
            ]);

            $batchId = (int)$pdo->lastInsertId();

            // Generate individual stub records
            $stubStmt = $pdo->prepare("
                INSERT INTO meal_stubs (
                    batch_id,
                    stub_code,
                    status
                )
                VALUES (?, ?, 'Unclaimed')
            ");

            for ($i = 1; $i <= $quantity; $i++) {
                $stubCode = sprintf(
                    'LVCC-%s-AR%04d-%03d',
                    date('Ymd'),
                    $reportId,
                    $i
                );

                $stubStmt->execute([
                    $batchId,
                    $stubCode
                ]);
            }

            $pdo->commit();

            $message = "Successfully issued {$quantity} meal stubs for {$report['course']} {$report['year_level']} - {$report['section']}!";
            $messageType = 'success';

            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

        } catch (Throwable $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log('Stub issuance error: ' . $e->getMessage());

            $message = $e instanceof PDOException
                ? 'Unable to issue meal stubs. Please check the database.'
                : $e->getMessage();

            $messageType = 'error';
        }
    }
}

/* LOAD APPROVED REPORTS WITHOUT ISSUED BATCH */
$approvedReports = [];
$issuedBatches = [];
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

    $stmt = $pdo->query("
        SELECT
            ar.id,
            ar.course,
            ar.year_level,
            ar.section,
            ar.report_date,
            ar.present_count,
            ar.absent_count,
            u.full_name AS secretary_name
        FROM attendance_reports ar
        LEFT JOIN users u ON ar.secretary_id = u.id
        LEFT JOIN stub_batches sb
            ON sb.attendance_report_id = ar.id
        WHERE ar.status = 'approved'
          AND sb.id IS NULL
        ORDER BY ar.report_date DESC, ar.id DESC
    ");

    $approvedReports = $stmt->fetchAll();

    /* RECENT ISSUED BATCHES */
    $stmt = $pdo->query("
        SELECT
            sb.id,
            sb.stubs_issued,
            sb.issue_date,
            sb.status,
            ar.course,
            ar.section,
            ar.year_level,
            ar.report_date,
            u.full_name AS staff_name
        FROM stub_batches sb
        JOIN attendance_reports ar
            ON sb.attendance_report_id = ar.id
        LEFT JOIN users u
            ON sb.psas_staff_id = u.id
        ORDER BY sb.id DESC
        LIMIT 10
    ");

    $issuedBatches = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log('Stub issuance page error: ' . $e->getMessage());
    $pageError = 'Unable to load stub issuance records.';
}

$staffName = $_SESSION['name'] ?? $_SESSION['full_name'] ?? 'PSAS Staff';
$initial = strtoupper(substr($staffName, 0, 1));
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Stub Issuance | LVCC Meal Stub System</title>

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

/* MAIN BACKGROUND */
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

/* PANELS */
.panel {
    background: rgba(255,255,255,.94);
    border: 1px solid rgba(221,227,236,.9);
    border-radius: 12px;
    padding: 25px;
    margin-bottom: 25px;
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
    margin: 0 0 22px;
}

/* FORM */
.form-group {
    margin-bottom: 20px;
    display: flex;
    flex-direction: column;
}

.form-group label {
    font-size: 11px;
    font-weight: 800;
    margin-bottom: 8px;
    text-transform: uppercase;
    color: var(--navy);
}

.form-control {
    width: 100%;
    padding: 13px 14px;
    border: 1px solid var(--border);
    border-radius: 8px;
    font-family: inherit;
    font-size: 13px;
    color: var(--text);
    background: white;
    outline: none;
    transition: .2s ease;
}

.form-control:focus {
    border-color: var(--blue);
    box-shadow: 0 0 0 3px rgba(0,48,135,.10);
}

.form-control:read-only {
    background: #f1f5fb;
    color: var(--navy);
    font-weight: 700;
}

.form-hint {
    color: var(--muted);
    font-size: 11px;
    margin-top: 7px;
}

/* REPORT DETAILS */
.report-info {
    background: #f3f7fc;
    border: 1px solid var(--border);
    padding: 18px;
    border-radius: 9px;
    margin-bottom: 20px;
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 15px;
}

.report-info small {
    color: var(--muted);
    display: block;
    font-size: 10px;
    text-transform: uppercase;
    margin-bottom: 6px;
}

.report-info strong {
    font-size: 15px;
    color: var(--navy);
}

/* BUTTON */
.btn-primary {
    background: var(--blue);
    color: white;
    border: none;
    padding: 13px 22px;
    border-radius: 8px;
    font-family: inherit;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
    transition: .2s ease;
    box-shadow: 0 4px 0 #001b4d;
}

.btn-primary:hover:not(:disabled) {
    background: #0741a5;
    transform: translateY(-3px);
    box-shadow: 0 7px 14px rgba(0,32,91,.20);
}

.btn-primary:disabled {
    opacity: .5;
    cursor: not-allowed;
    box-shadow: none;
}

/* ALERTS */
.alert {
    padding: 15px 18px;
    border-radius: 9px;
    margin-bottom: 22px;
    font-size: 13px;
    font-weight: 600;
}

.alert-success {
    background: #def7ec;
    color: #03543f;
    border: 1px solid #b7ebd2;
}

.alert-error {
    background: #fee2e2;
    color: #991b1b;
    border: 1px solid #fecaca;
}

/* TABLE */
.table-wrap {
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    font-size: 12px;
    margin-top: 15px;
    white-space: nowrap;
}

th, td {
    padding: 15px 13px;
    border-bottom: 1px solid var(--border);
    text-align: left;
}

th {
    background: #f1f4f9;
    color: #718198;
    font-size: 10px;
    text-transform: uppercase;
    letter-spacing: .04em;
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
    color: var(--green);
}

.empty-state {
    color: var(--muted);
    padding: 25px 5px;
    font-size: 13px;
    font-style: italic;
}

.footer {
    color: #8291a5;
    font-size: 10px;
    display: flex;
    justify-content: space-between;
    margin-top: 35px;
}

/* RESPONSIVE */
@media (max-width: 900px) {
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

    .report-info {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 650px) {
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
        <a href="stub_issuance.php" class="active">▤ &nbsp; Stub Issuance</a>
        <a href="stub_monitoring.php">◷ &nbsp; Stub Monitoring</a>
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
            <h1 class="page-title">Stub Issuance</h1>
            <p class="page-description">
                Generate meal stub batches from approved attendance reports.
            </p>
        </div>

        <div class="date-pill">
            ◷ &nbsp; <?= date('F d, Y') ?>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert <?= $messageType === 'success' ? 'alert-success' : 'alert-error' ?>">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <?php if ($pageError): ?>
        <div class="alert alert-error">
            <?= htmlspecialchars($pageError) ?>
        </div>
    <?php endif; ?>

    <!-- NEW ISSUANCE -->
    <section class="panel">

        <h3>New Stub Issuance</h3>
        <p class="panel-subtitle">
            Select an approved attendance report to generate meal stubs.
        </p>

        <?php if (empty($approvedReports)): ?>

            <div class="empty-state">
                <p><strong>No reports available for issuance.</strong></p>
                All approved attendance reports have already been issued or no approved reports are available.
            </div>

        <?php else: ?>

            <form method="POST" id="issuanceForm">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>"
                >

                <div class="form-group">
                    <label for="attendance_report_id">
                        Approved Attendance Report
                    </label>

                    <select
                        name="attendance_report_id"
                        id="attendance_report_id"
                        class="form-control"
                        required
                    >
                        <option value="">Select an approved report</option>

                        <?php foreach ($approvedReports as $report): ?>
                            <option
                                value="<?= (int)$report['id'] ?>"
                                data-course="<?= htmlspecialchars($report['course'], ENT_QUOTES) ?>"
                                data-section="<?= htmlspecialchars($report['section'], ENT_QUOTES) ?>"
                                data-year="<?= (int)$report['year_level'] ?>"
                                data-present="<?= (int)$report['present_count'] ?>"
                                data-absent="<?= (int)$report['absent_count'] ?>"
                                data-date="<?= htmlspecialchars($report['report_date'], ENT_QUOTES) ?>"
                                data-secretary="<?= htmlspecialchars($report['secretary_name'] ?? 'Unknown', ENT_QUOTES) ?>"
                            >
                                <?= htmlspecialchars(
                                    $report['course'] . ' - Year ' .
                                    $report['year_level'] . ' | ' .
                                    $report['report_date'] . ' | ' .
                                    $report['present_count'] . ' present'
                                ) ?>
                            </option>
                        <?php endforeach; ?>

                    </select>
                </div>

                <div id="reportDetails" class="report-info" style="display:none;">

                    <div>
                        <small>Course and Year</small>
                        <strong id="detailCourse">—</strong>
                    </div>

                    <div>
                        <small>Present Students</small>
                        <strong id="detailPresent">—</strong>
                    </div>

                    <div>
                        <small>Absent Students</small>
                        <strong id="detailAbsent">—</strong>
                    </div>

                    <div>
                        <small>Section</small>
                        <strong id="detailSection">—</strong>
                    </div>

                    <div>
                        <small>Attendance Date</small>
                        <strong id="detailDate">—</strong>
                    </div>

                    <div>
                        <small>Class Secretary</small>
                        <strong id="detailSecretary">—</strong>
                    </div>

                </div>

                <div class="form-group">
                    <label for="quantity">Total Stub Quantity</label>

                    <input
                        type="number"
                        id="quantity"
                        class="form-control"
                        readonly
                        placeholder="Automatically based on present count"
                    >

                    <small class="form-hint">
                        Stub quantity is automatically based on the approved present count.
                    </small>
                </div>

                <button
                    type="submit"
                    class="btn-primary"
                    id="issueButton"
                    disabled
                >
                    Issue Meal Stubs →
                </button>

            </form>

        <?php endif; ?>

    </section>

    <!-- RECENT BATCHES -->
    <section class="panel">

        <h3>Recent Issued Batches</h3>
        <p class="panel-subtitle">
            Latest meal stub batches generated by PSAS staff.
        </p>

        <?php if (empty($issuedBatches)): ?>

            <div class="empty-state">
                No stub batches have been issued yet.
            </div>

        <?php else: ?>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Batch ID</th>
                            <th>Course & Section</th>
                            <th>Attendance Date</th>
                            <th>Quantity Issued</th>
                            <th>Issue Date</th>
                            <th>Status</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php foreach ($issuedBatches as $batch): ?>
                            <tr>
                                <td>
                                    <strong>#<?= (int)$batch['id'] ?></strong>
                                </td>

                                <td>
                                    <strong><?= htmlspecialchars($batch['course']) ?></strong>
                                    - Year <?= (int)$batch['year_level'] ?>
                                    - <?= htmlspecialchars($batch['section']) ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($batch['report_date']) ?>
                                </td>

                                <td>
                                    <strong><?= (int)$batch['stubs_issued'] ?></strong>
                                </td>

                                <td>
                                    <?= htmlspecialchars($batch['issue_date']) ?>
                                </td>

                                <td>
                                    <span class="badge <?= strtolower($batch['status'] ?? '') === 'completed' ? 'badge-completed' : 'badge-issued' ?>">
                                        <?= htmlspecialchars(strtoupper($batch['status'] ?? 'issued')) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        <?php endif; ?>

    </section>

    <div class="footer">
        <span>© <?= date('Y') ?> La Verdad Christian College</span>
        <span>Meal Stub Management System · PSAS Portal</span>
    </div>

</main>

<script>
const reportSelect = document.getElementById('attendance_report_id');

if (reportSelect) {

    const details = document.getElementById('reportDetails');
    const quantity = document.getElementById('quantity');
    const issueButton = document.getElementById('issueButton');

    reportSelect.addEventListener('change', function () {

        const selected = this.options[this.selectedIndex];

        if (!selected.value) {
            details.style.display = 'none';
            quantity.value = '';
            issueButton.disabled = true;
            return;
        }

        const present = Number(selected.dataset.present || 0);

        document.getElementById('detailCourse').textContent =
            selected.dataset.course + ' - Year ' + selected.dataset.year;

        document.getElementById('detailSection').textContent =
            selected.dataset.section;

        document.getElementById('detailPresent').textContent =
            present + ' students';

        document.getElementById('detailAbsent').textContent =
            selected.dataset.absent + ' students';

        document.getElementById('detailDate').textContent =
            selected.dataset.date;

        document.getElementById('detailSecretary').textContent =
            selected.dataset.secretary;

        quantity.value = present;

        details.style.display = 'grid';
        issueButton.disabled = present < 1;
    });

    document.getElementById('issuanceForm').addEventListener('submit', function (event) {

        const selected = reportSelect.options[reportSelect.selectedIndex];

        if (!selected.value) {
            event.preventDefault();
            alert('Please select an approved attendance report.');
            return;
        }

        const present = Number(selected.dataset.present || 0);

        if (!confirm(
            'Issue ' + present +
            ' meal stubs for ' +
            selected.dataset.course + ' Year ' +
            selected.dataset.year + ' - ' +
            selected.dataset.section + '?'
        )) {
            event.preventDefault();
        }
    });
}
</script>

</body>
</html>
