
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

$secretaryId = (int)$_SESSION['user_id'];

function e($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

// Build a clean academic label without repeating a section name
// when the section name is identical to the year-level label.
function formatSectionLabel($course, $year, $section = '') {
    $parts = [];

    $course = trim((string)($course ?? ''));
    $year = trim((string)($year ?? ''));
    $section = trim((string)($section ?? ''));

    if ($course !== '') {
        $parts[] = $course;
    }

    if ($year !== '') {
        $parts[] = $year;
    }

    if (
        $section !== '' &&
        ($year === '' || strtolower($section) !== strtolower($year))
    ) {
        $parts[] = $section;
    }

    return $parts ? implode(' - ', $parts) : 'No section assigned';
}

// CSRF protection
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['csrf_token'];

$secretaryName = $_SESSION['full_name'] ?? $_SESSION['name'] ?? 'Class Secretary';
$firstName = explode(' ', trim($secretaryName))[0] ?: 'Secretary';
$initials = strtoupper(substr($firstName, 0, 1));

$courseCode = '';
$yearLevel = '';
$sectionName = '';
$sectionDisplay = 'No section assigned';

$search = trim($_GET['search'] ?? '');

$flash = $_SESSION['distribution_flash'] ?? null;
unset($_SESSION['distribution_flash']);

$error = '';

try {
    // Get secretary's assigned section
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

    $stmt->execute([$secretaryId]);
    $secretary = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($secretary) {
        $secretaryName = $secretary['full_name'] ?: $secretaryName;
        $firstName = explode(' ', trim($secretaryName))[0] ?: 'Secretary';
        $initials = strtoupper(substr($firstName, 0, 1));

        $courseCode = $secretary['course_code'] ?? '';
        $yearLevel = $secretary['year_level'] ?? '';
        $sectionName = $secretary['section_name'] ?? '';

        $sectionDisplay = formatSectionLabel(
            $courseCode,
            $yearLevel,
            $sectionName
        );
    }

} catch (PDOException $ex) {
    error_log($ex->getMessage());
    $error = 'Unable to load your account information.';
}

// Keep each batch status aligned with its actual meal-stub records.
// A batch is "issued" while any stub is still Unclaimed; otherwise it is
// "completed" once it contains at least one stub.
function syncSecretaryBatchStatuses(PDO $pdo, int $secretaryId): void {
    $stmt = $pdo->prepare("
        UPDATE stub_batches
        SET status = CASE
            WHEN EXISTS (
                SELECT 1
                FROM meal_stubs ms
                WHERE ms.batch_id = stub_batches.id
                  AND ms.status = 'Unclaimed'
            ) THEN 'issued'
            ELSE 'completed'
        END
        WHERE attendance_report_id IN (
            SELECT ar.id
            FROM attendance_reports ar
            WHERE ar.secretary_id = ?
        )
        AND EXISTS (
            SELECT 1
            FROM meal_stubs ms
            WHERE ms.batch_id = stub_batches.id
        )
    ");
    $stmt->execute([$secretaryId]);
}

// HANDLE DISTRIBUTION ACTIONS
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $submittedToken = $_POST['csrf_token'] ?? '';
    $action = $_POST['action'] ?? '';

    try {
        if (
            !is_string($submittedToken) ||
            !hash_equals($csrfToken, $submittedToken)
        ) {
            throw new RuntimeException(
                'Invalid request. Please refresh and try again.'
            );
        }

        $pdo->beginTransaction();

        if ($action === 'distribute_all') {

            // Distribute all unclaimed stubs belonging to this secretary.
            $stmt = $pdo->prepare("
                UPDATE meal_stubs
                SET
                    status = 'Distributed',
                    distributed_by = ?,
                    distributed_at = datetime('now')
                WHERE status = 'Unclaimed'
                  AND EXISTS (
                      SELECT 1
                      FROM stub_batches sb
                      INNER JOIN attendance_reports ar
                          ON ar.id = sb.attendance_report_id
                      WHERE sb.id = meal_stubs.batch_id
                        AND ar.secretary_id = ?
                  )
            ");

            $stmt->execute([
                $secretaryId,
                $secretaryId
            ]);

            $count = $stmt->rowCount();

            // Synchronize all of this secretary's batches in the same transaction.
            syncSecretaryBatchStatuses($pdo, $secretaryId);

            $pdo->commit();

            $_SESSION['distribution_flash'] = [
                'type' => 'success',
                'message' => $count > 0
                    ? "Successfully distributed {$count} meal stubs!"
                    : 'There are no unclaimed meal stubs to distribute.'
            ];

        } elseif ($action === 'distribute_one') {

            $stubId = filter_var(
                $_POST['stub_id'] ?? null,
                FILTER_VALIDATE_INT
            );

            if (!$stubId || $stubId < 1) {
                throw new RuntimeException('Invalid meal stub.');
            }

            // Update only if the stub belongs to this secretary.
            $stmt = $pdo->prepare("
                UPDATE meal_stubs
                SET
                    status = 'Distributed',
                    distributed_by = ?,
                    distributed_at = datetime('now')
                WHERE id = ?
                  AND status = 'Unclaimed'
                  AND EXISTS (
                      SELECT 1
                      FROM stub_batches sb
                      INNER JOIN attendance_reports ar
                          ON ar.id = sb.attendance_report_id
                      WHERE sb.id = meal_stubs.batch_id
                        AND ar.secretary_id = ?
                  )
            ");

            $stmt->execute([
                $secretaryId,
                $stubId,
                $secretaryId
            ]);

            $updated = $stmt->rowCount();

            // Sync after the individual update, even if this was the final stub.
            syncSecretaryBatchStatuses($pdo, $secretaryId);

            $pdo->commit();

            $_SESSION['distribution_flash'] = [
                'type' => $updated === 1 ? 'success' : 'error',
                'message' => $updated === 1
                    ? 'Meal stub successfully marked as distributed!'
                    : 'This stub is already processed or does not belong to your section.'
            ];

        } else {
            throw new RuntimeException('Invalid distribution action.');
        }

    } catch (PDOException $ex) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log('Distribution error: ' . $ex->getMessage());

        $_SESSION['distribution_flash'] = [
            'type' => 'error',
            'message' => 'Unable to process distribution. Please try again.'
        ];

    } catch (Throwable $ex) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        $_SESSION['distribution_flash'] = [
            'type' => 'error',
            'message' => $ex->getMessage()
        ];
    }

    header('Location: distribution_tracker.php');
    exit;
}

// Repair any older batch-status mismatch before displaying the tracker.
// This does not change meal-stub records; it only recalculates the batch summary.
try {
    syncSecretaryBatchStatuses($pdo, $secretaryId);
} catch (PDOException $ex) {
    error_log('Batch status synchronization error: ' . $ex->getMessage());
    $error = 'Unable to synchronize batch status. Please refresh and try again.';
}

// FETCH STATISTICS
$stats = [
    'total' => 0,
    'unclaimed' => 0,
    'distributed' => 0
];

$records = [];

try {
    $statsQuery = $pdo->prepare("
        SELECT
            COUNT(ms.id) AS total,

            COALESCE(SUM(
                CASE WHEN ms.status = 'Unclaimed'
                THEN 1 ELSE 0 END
            ), 0) AS unclaimed,

            COALESCE(SUM(
                CASE WHEN ms.status IN ('Distributed', 'Redeemed')
                THEN 1 ELSE 0 END
            ), 0) AS distributed

        FROM meal_stubs ms

        INNER JOIN stub_batches sb
            ON sb.id = ms.batch_id

        INNER JOIN attendance_reports ar
            ON ar.id = sb.attendance_report_id

        WHERE ar.secretary_id = ?
    ");

    $statsQuery->execute([$secretaryId]);
    $stats = $statsQuery->fetch(PDO::FETCH_ASSOC);

    // Fetch meal stub records
    $sql = "
        SELECT
            ms.id,
            ms.stub_code,
            ms.status,
            ms.distributed_at,

            sb.id AS batch_id,
            sb.issue_date,

            ar.course,
            ar.year_level,
            ar.section,
            ar.report_date

        FROM meal_stubs ms

        INNER JOIN stub_batches sb
            ON sb.id = ms.batch_id

        INNER JOIN attendance_reports ar
            ON ar.id = sb.attendance_report_id

        WHERE ar.secretary_id = ?
    ";

    $params = [$secretaryId];

    if ($search !== '') {
        $sql .= "
            AND (
                ms.stub_code LIKE ?
                OR ar.course LIKE ?
                OR ar.year_level LIKE ?
                OR ar.section LIKE ?
                OR CAST(sb.id AS TEXT) LIKE ?
            )
        ";

        $term = '%' . $search . '%';

        $params = array_merge(
            $params,
            [$term, $term, $term, $term, $term]
        );
    }

    $sql .= "
        ORDER BY sb.id DESC, ms.id ASC
    ";

    $recordsQuery = $pdo->prepare($sql);
    $recordsQuery->execute($params);
    $records = $recordsQuery->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $ex) {
    error_log('Tracker query error: ' . $ex->getMessage());
    $error = 'Unable to load meal stub records. Please try again.';
}

$total = (int)($stats['total'] ?? 0);
$unclaimed = (int)($stats['unclaimed'] ?? 0);
// Legacy Redeemed records are counted under Distributed.
// The system will no longer track redemption as a separate status.
$distributed = (int)($stats['distributed'] ?? 0);
$processed = $distributed;

$progress = $total > 0
    ? round(($processed / $total) * 100)
    : 0;

$progress = min(100, $progress);
?>

<!DOCTYPE html>
<html lang="en">
<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Distribution Tracker | LVCC Meal Stub System</title>

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
    --orange: #d97706;
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

/* MAIN */
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

/* HEADER */
.page-heading {
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
    gap: 15px;
    margin-bottom: 25px;
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

/* SECTION LABEL */
.section-tag {
    display: inline-flex;
    align-items: center;
    background: rgba(255,255,255,.85);
    border: 1px solid var(--border);
    color: var(--navy);
    border-radius: 8px;
    padding: 9px 13px;
    font-size: 11px;
    font-weight: 700;
    white-space: nowrap;
}

/* PROGRESS */
.progress-panel {
    background: var(--navy);
    color: white;
    padding: 25px;
    border-radius: 13px;
    margin-bottom: 23px;
    box-shadow: 0 5px 18px rgba(0,32,91,.13);
    transition: .25s ease;
}

.progress-panel:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 24px rgba(0,32,91,.2);
}

.progress-label {
    color: #b8cadd;
    font-size: 11px;
    text-transform: uppercase;
    font-weight: 700;
    letter-spacing: .06em;
}

.progress-number {
    font-family: 'Manrope', sans-serif;
    font-size: 27px;
    font-weight: 800;
    margin: 8px 0 16px;
}

.progress-track {
    height: 9px;
    background: #ffffff30;
    border-radius: 10px;
    overflow: hidden;
}

.progress-fill {
    height: 100%;
    background: var(--yellow);
    border-radius: 10px;
    transition: width .5s ease;
}

.progress-caption {
    color: #b8cadd;
    font-size: 11px;
    margin-top: 10px;
}

/* KPI */
.kpi-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 17px;
    margin-bottom: 23px;
}

.kpi-card {
    background: rgba(255,255,255,.95);
    border: 1px solid var(--border);
    border-radius: 13px;
    padding: 21px;
    box-shadow: 0 5px 18px rgba(20,45,80,.05);
    transition: .25s ease;
}

.kpi-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 12px 24px rgba(20,45,80,.11);
}

.kpi-card h3 {
    color: var(--muted);
    font-size: 10px;
    text-transform: uppercase;
    letter-spacing: .05em;
    margin: 0 0 10px;
}

.kpi-card strong {
    font-family: 'Manrope', sans-serif;
    font-size: 27px;
    color: var(--navy);
}

.kpi-card:nth-child(2) strong {
    color: var(--orange);
}

.kpi-card:nth-child(3) strong {
    color: var(--green);
}

.kpi-card:nth-child(4) strong {
    color: var(--blue);
}

.kpi-card small {
    display: block;
    margin-top: 7px;
    font-size: 11px;
    color: var(--muted);
}

/* PANEL */
.panel {
    background: rgba(255,255,255,.96);
    border: 1px solid var(--border);
    border-radius: 13px;
    padding: 25px;
    box-shadow: 0 5px 18px rgba(20,45,80,.05);
    margin-bottom: 23px;
    transition: .25s ease;
}

.panel:hover {
    box-shadow: 0 10px 25px rgba(20,45,80,.09);
}

.panel-heading {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
    flex-wrap: wrap;
    margin-bottom: 8px;
}

.panel-heading h2 {
    font-family: 'Manrope', sans-serif;
    font-size: 17px;
    color: var(--navy);
    margin: 0;
}

.panel-description {
    color: var(--muted);
    font-size: 12px;
    margin: 5px 0 18px;
}

/* BUTTONS */
.btn {
    border: none;
    border-radius: 8px;
    padding: 11px 16px;
    font-family: inherit;
    font-weight: 700;
    font-size: 12px;
    cursor: pointer;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    transition: .2s ease;
}

.btn:hover {
    transform: translateY(-2px);
}

.btn-all {
    background: var(--green);
    color: white;
}

.btn-all:hover {
    background: #116b49;
    box-shadow: 0 5px 12px rgba(23,131,91,.2);
}

.btn-single {
    background: var(--blue);
    color: white;
    padding: 8px 12px;
    font-size: 11px;
}

.btn-single:hover {
    background: var(--navy);
}

/* SEARCH */
.search-form {
    display: flex;
    gap: 9px;
    margin-bottom: 20px;
}

.search-input {
    flex: 1;
    min-width: 0;
    padding: 12px 14px;
    border: 1px solid var(--border);
    border-radius: 8px;
    font: inherit;
    font-size: 13px;
    background: white;
}

.search-input:focus {
    outline: 2px solid #b9d3ff;
    border-color: #3978d7;
}

.btn-search {
    background: #e9edf4;
    color: #344054;
}

.btn-clear {
    background: #f1f3f6;
    color: var(--muted);
}

/* STUB CARDS */
.stub-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 15px;
}

.stub-card {
    background: #fff;
    border: 1px solid var(--border);
    border-radius: 11px;
    padding: 17px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    min-width: 0;
    transition: .25s ease;
}

.stub-card:hover {
    border-color: #b7c9e2;
    transform: translateY(-3px);
    box-shadow: 0 8px 17px rgba(20,45,80,.08);
}

.stub-info {
    min-width: 0;
}

.stub-code {
    font-weight: 800;
    font-size: 13px;
    color: var(--navy);
    overflow-wrap: anywhere;
}

.stub-meta {
    color: var(--muted);
    font-size: 11px;
    margin-top: 7px;
    line-height: 1.7;
}

.stub-actions {
    display: flex;
    align-items: flex-end;
    flex-direction: column;
    gap: 9px;
    flex-shrink: 0;
}

/* STATUS */
.badge {
    display: inline-block;
    padding: 5px 10px;
    border-radius: 20px;
    font-size: 10px;
    font-weight: 800;
    text-transform: uppercase;
    white-space: nowrap;
}

.badge-unclaimed {
    background: #fff4d6;
    color: #a86b00;
}

.badge-distributed {
    background: #def7ec;
    color: #167344;
}

.badge-default {
    background: #edf0f5;
    color: #526173;
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
    background: #e9f8ef;
    color: #176b3a;
    border: 1px solid #c7ebd4;
}

.alert-error {
    background: #fff0ef;
    color: #b42318;
    border: 1px solid #f5d0cc;
}

/* EMPTY STATE */
.empty {
    color: var(--muted);
    text-align: center;
    padding: 35px 15px;
    font-size: 13px;
}

.empty strong {
    display: block;
    color: var(--navy);
    margin-bottom: 5px;
}

/* RESPONSIVE */
@media(max-width: 1100px) {
    .kpi-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media(max-width: 850px) {
    .main-content {
        padding: 25px;
    }

    .stub-grid {
        grid-template-columns: 1fr;
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

    .page-heading {
        align-items: flex-start;
        flex-direction: column;
    }
}

@media(max-width: 520px) {
    .nav-links {
        grid-template-columns: 1fr;
    }

    .kpi-grid {
        grid-template-columns: 1fr;
    }

    .panel {
        padding: 18px;
    }

    .stub-card {
        align-items: flex-start;
        flex-direction: column;
    }

    .stub-actions {
        align-items: flex-start;
        flex-direction: row;
        flex-wrap: wrap;
    }

    .search-form {
        flex-wrap: wrap;
    }

    .search-input {
        flex-basis: 100%;
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

        <a href="secretary_dashboard.php">
            <span class="nav-icon">▦</span>
            Section Overview
        </a>

        <a href="daily_attendance.php">
            <span class="nav-icon">✓</span>
            Daily Attendance
        </a>

        <a href="distribution_tracker.php" class="active">
            <span class="nav-icon">▤</span>
            Distribution Tracker
        </a>

    </nav>

    <div class="sidebar-bottom">

        <div class="sidebar-profile">
            <div class="sidebar-avatar"><?= e($initials) ?></div>

            <div>
                <strong><?= e($secretaryName) ?></strong>
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
                <strong><?= e($secretaryName) ?></strong>
                <small>Class Secretary</small>
            </div>
        </div>

    </div>

    <!-- PAGE HEADER -->
    <section class="page-heading">

        <div>
            <span class="eyebrow">MEAL STUB MANAGEMENT</span>

            <h1>Distribution Tracker</h1>

            <p class="subtitle">
                Track and update the distribution of your section's issued meal stubs.
            </p>
        </div>

        <div class="section-tag">
            <?= e($sectionDisplay) ?>
        </div>

    </section>

    <?php if ($flash): ?>
        <div class="alert <?= ($flash['type'] ?? '') === 'success' ? 'alert-success' : 'alert-error' ?>">
            <?= e($flash['message'] ?? '') ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-error">
            <?= e($error) ?>
        </div>
    <?php endif; ?>

    <!-- DISTRIBUTION PROGRESS -->
    <section class="progress-panel">

        <div class="progress-label">Distribution Progress</div>

        <div class="progress-number">
            <?= number_format($processed) ?> of <?= number_format($total) ?> processed
        </div>

        <div class="progress-track">
            <div
                class="progress-fill"
                style="width: <?= $progress ?>%;"
            ></div>
        </div>

        <div class="progress-caption">
            <?= $progress ?>% completed
        </div>

    </section>

    <!-- STATISTICS -->
    <section class="kpi-grid">

        <div class="kpi-card">
            <h3>Total Stubs</h3>
            <strong><?= number_format($total) ?></strong>
            <small>All issued meal stubs</small>
        </div>

        <div class="kpi-card">
            <h3>Unclaimed</h3>
            <strong><?= number_format($unclaimed) ?></strong>
            <small>Waiting for distribution</small>
        </div>

        <div class="kpi-card">
            <h3>Distributed</h3>
            <strong><?= number_format($distributed) ?></strong>
            <small>Already handed out</small>
        </div>

    </section>

    <!-- MEAL STUB RECORDS -->
    <section class="panel">

        <div class="panel-heading">

            <h2>Meal Stub Records</h2>

            <?php if ($unclaimed > 0): ?>

                <form
                    method="POST"
                    onsubmit="return confirm('Are you sure you want to distribute ALL <?= (int)$unclaimed ?> unclaimed meal stubs belonging to your reports?');"
                >

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= e($csrfToken) ?>"
                    >

                    <input
                        type="hidden"
                        name="action"
                        value="distribute_all"
                    >

                    <button type="submit" class="btn btn-all">
                        ✓ Distribute All (<?= number_format($unclaimed) ?>)
                    </button>

                </form>

            <?php endif; ?>

        </div>

        <p class="panel-description">
            Search by stub code, course, year level, section, or batch number.
        </p>

        <!-- SEARCH -->
        <form method="GET" class="search-form">

            <input
                type="text"
                name="search"
                class="search-input"
                placeholder="Search meal stub..."
                value="<?= e($search) ?>"
            >

            <button type="submit" class="btn btn-search">
                Search
            </button>

            <?php if ($search !== ''): ?>
                <a href="distribution_tracker.php" class="btn btn-clear">
                    Clear
                </a>
            <?php endif; ?>

        </form>

        <!-- STUB LIST -->
        <?php if (count($records) > 0): ?>

            <div class="stub-grid">

                <?php foreach ($records as $record): ?>

                    <?php
                    $storedStatus = $record['status'] ?? 'Unclaimed';

                    // Redemption is no longer tracked separately.
                    // Older Redeemed records are displayed as Distributed.
                    $status = $storedStatus === 'Redeemed'
                        ? 'Distributed'
                        : $storedStatus;

                    $badgeClass = match ($status) {
                        'Unclaimed' => 'badge-unclaimed',
                        'Distributed' => 'badge-distributed',
                        default => 'badge-default'
                    };
                    ?>

                    <article class="stub-card">

                        <div class="stub-info">

                            <div class="stub-code">
                                <?= e($record['stub_code']) ?>
                            </div>

                            <div class="stub-meta">

                                <?= e(formatSectionLabel(
                                    $record['course'],
                                    $record['year_level'],
                                    $record['section']
                                )) ?>

                                <br>

                                Batch #<?= (int)$record['batch_id'] ?>

                                · Issued <?= e($record['issue_date']) ?>

                                <?php if (!empty($record['distributed_at'])): ?>
                                    <br>
                                    Distributed:
                                    <?= e($record['distributed_at']) ?>
                                <?php endif; ?>

                            </div>

                        </div>

                        <div class="stub-actions">

                            <span class="badge <?= e($badgeClass) ?>">
                                <?= e($status) ?>
                            </span>

                            <?php if ($status === 'Unclaimed'): ?>

                                <form
                                    method="POST"
                                    onsubmit="return confirm('Mark this meal stub as distributed?');"
                                >

                                    <input
                                        type="hidden"
                                        name="csrf_token"
                                        value="<?= e($csrfToken) ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="action"
                                        value="distribute_one"
                                    >

                                    <input
                                        type="hidden"
                                        name="stub_id"
                                        value="<?= (int)$record['id'] ?>"
                                    >

                                    <button type="submit" class="btn btn-single">
                                        Mark as Distributed
                                    </button>

                                </form>

                            <?php endif; ?>

                        </div>

                    </article>

                <?php endforeach; ?>

            </div>

        <?php else: ?>

            <div class="empty">
                <?php if ($search !== ''): ?>

                    <strong>No matching meal stubs found.</strong>
                    Try another stub code, course, section, or batch number.

                <?php else: ?>

                    <strong>No meal stub records yet.</strong>
                    Meal stubs issued from your approved attendance reports will appear here.

                <?php endif; ?>
            </div>

        <?php endif; ?>

    </section>

</div>

</main>

</body>
</html>
