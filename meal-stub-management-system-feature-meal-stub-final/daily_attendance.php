
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

$userId = (int)$_SESSION['user_id'];
$today = date('Y-m-d');

// Total enrollment is entered by the Class Secretary for each attendance report.
// The value is validated on the server and used to calculate absent students.
$totalEnrollees = 0;

$success = '';
$error = '';

$secretaryName = $_SESSION['full_name'] ?? $_SESSION['name'] ?? 'Class Secretary';
$firstName = explode(' ', trim($secretaryName))[0] ?: 'Secretary';
$initials = strtoupper(substr($firstName, 0, 1));

$courseCode = '';
$courseName = '';
$yearLevel = '';
$sectionName = '';
$sectionId = null;
$sectionDisplay = 'No section assigned';

$todayReport = null;
$present = 0;
$absent = 0;
$remarks = '';

$hasAssignment = false;
$alreadySubmitted = false;

// CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function e($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

try {
    // GET LOGGED-IN SECRETARY AND ASSIGNED SECTION
    $stmt = $pdo->prepare("
        SELECT
            u.full_name,
            u.section_id,
            s.section_name,
            c.course_code,
            c.course_name,
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
        $secretaryName = $secretary['full_name'] ?: $secretaryName;
        $firstName = explode(' ', trim($secretaryName))[0] ?: 'Secretary';
        $initials = strtoupper(substr($firstName, 0, 1));

        $sectionId = $secretary['section_id'];
        $courseCode = $secretary['course_code'] ?? '';
        $courseName = $secretary['course_name'] ?? '';
        $yearLevel = $secretary['year_level'] ?? '';
        $sectionName = $secretary['section_name'] ?? '';

        $hasAssignment = (
            !empty($sectionId) &&
            $courseCode !== '' &&
            $yearLevel !== ''
        );

        if ($hasAssignment) {
            $sectionDisplay = $courseCode . ' - ' . $yearLevel;

            // Avoid repeating the year when the section label duplicates it.
            $sameAsYear = strtolower(trim((string)$sectionName))
                === strtolower(trim((string)$yearLevel));

            if ($sectionName !== '' && !$sameAsYear) {
                $sectionDisplay .= ' - ' . $sectionName;
            }
        }
    }

    // CHECK TODAY'S LATEST REPORT
    $stmt = $pdo->prepare("
        SELECT *
        FROM attendance_reports
        WHERE secretary_id = ?
          AND report_date = ?
        ORDER BY id DESC
        LIMIT 1
    ");

    $stmt->execute([$userId, $today]);
    $todayReport = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($todayReport) {
        $present = (int)$todayReport['present_count'];
        $absent = (int)($todayReport['absent_count'] ?? 0);
        // Existing reports retain their total through present + absent.
        $totalEnrollees = $present + $absent;
        $remarks = $todayReport['remarks'] ?? '';

        $status = strtolower($todayReport['status'] ?? 'pending');

        $alreadySubmitted = in_array(
            $status,
            ['pending', 'approved'],
            true
        );
    }

} catch (PDOException $ex) {
    error_log($ex->getMessage());
    $error = 'Unable to load attendance information. Please try again.';
}

// PROCESS ATTENDANCE SUBMISSION
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $totalInput = $_POST['total_enrollees'] ?? '';
    $presentInput = $_POST['present_count'] ?? '';
    $remarksInput = trim($_POST['remarks'] ?? '');
    $submittedToken = $_POST['csrf_token'] ?? '';

    try {
        if (
            !is_string($submittedToken) ||
            !hash_equals($_SESSION['csrf_token'], $submittedToken)
        ) {
            throw new Exception('Invalid form request. Please refresh the page.');
        }

        if (!$hasAssignment) {
            throw new Exception(
                'Your account has no assigned section. Please contact PSAS.'
            );
        }

        if ($alreadySubmitted) {
            throw new Exception(
                'You have already submitted attendance for today.'
            );
        }

        if (
            !is_scalar($totalInput) ||
            filter_var($totalInput, FILTER_VALIDATE_INT) === false
        ) {
            throw new Exception('Please enter a valid total enrollment.');
        }

        $totalEnrollees = (int)$totalInput;

        if ($totalEnrollees < 1 || $totalEnrollees > 500) {
            throw new Exception('Total enrollment must be between 1 and 500.');
        }

        if (
            !is_scalar($presentInput) ||
            filter_var($presentInput, FILTER_VALIDATE_INT) === false
        ) {
            throw new Exception('Please enter a valid present student count.');
        }

        $present = (int)$presentInput;
        $remarks = $remarksInput;

        if ($present < 0 || $present > $totalEnrollees) {
            throw new Exception(
                "Present count must be between 0 and {$totalEnrollees}."
            );
        }

        if (strlen($remarks) > 1000) {
            throw new Exception('Remarks must not exceed 1,000 characters.');
        }

        $absent = $totalEnrollees - $present;

        // Use a transaction to protect against duplicate submissions.
        $pdo->beginTransaction();

        // Recheck duplicate submission inside the transaction.
        $check = $pdo->prepare("
            SELECT COUNT(*)
            FROM attendance_reports
            WHERE secretary_id = ?
              AND report_date = ?
              AND status IN ('pending', 'approved')
        ");

        $check->execute([$userId, $today]);

        if ((int)$check->fetchColumn() > 0) {
            throw new Exception(
                'You have already submitted attendance for today.'
            );
        }

        // Convert the assigned label (for example, "2nd Year") to the
        // integer required by attendance_reports.year_level.
        $yearLevelNumber = (int) preg_replace('/\D+/', '', (string)$yearLevel);

        if ($yearLevelNumber < 1 || $yearLevelNumber > 6) {
            throw new Exception('Invalid assigned year level. Please contact PSAS.');
        }

        // Insert attendance using the secretary's actual assignment.
        $stmt = $pdo->prepare("
            INSERT INTO attendance_reports (
                secretary_id,
                course,
                year_level,
                section,
                report_date,
                present_count,
                absent_count,
                remarks,
                status
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')
        ");

        $stmt->execute([
            $userId,
            $courseCode,
            $yearLevelNumber,
            $sectionName,
            $today,
            $present,
            $absent,
            $remarks
        ]);

        $pdo->commit();

        $success = 'Attendance submitted successfully! Your report is now pending PSAS verification.';

        // Update page state
        $todayReport = [
            'id' => $pdo->lastInsertId(),
            'report_date' => $today,
            'present_count' => $present,
            'absent_count' => $absent,
            'remarks' => $remarks,
            'status' => 'pending'
        ];

        $alreadySubmitted = true;

        // Generate a new token
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

    } catch (Throwable $ex) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        if ($ex instanceof PDOException) {
            error_log($ex->getMessage());
            $error = 'Unable to submit attendance. Please try again.';
        } else {
            $error = $ex->getMessage();
        }
    }
}

$todayFormatted = date('F d, Y');

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

<title>Daily Attendance | LVCC Meal Stub System</title>

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
    max-width: 1200px;
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

/* PAGE HEADER */
.page-heading {
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

/* PANEL */
.panel {
    background: rgba(255,255,255,.96);
    border: 1px solid var(--border);
    border-radius: 14px;
    padding: 26px;
    box-shadow: 0 5px 18px rgba(20,45,80,.05);
    transition: .25s ease;
    margin-bottom: 22px;
}

.panel:hover {
    box-shadow: 0 10px 25px rgba(20,45,80,.09);
}

.panel h2 {
    font-family: 'Manrope', sans-serif;
    font-size: 17px;
    color: var(--navy);
    margin: 0;
}

.panel-description {
    font-size: 12px;
    color: var(--muted);
    margin: 6px 0 22px;
}

/* SECTION INFO */
.info-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 15px;
    background: #f7f9fc;
    border: 1px solid #edf0f5;
    border-radius: 11px;
    padding: 18px;
    margin-bottom: 25px;
}

.info-item small {
    display: block;
    color: var(--muted);
    font-size: 10px;
    font-weight: 800;
    letter-spacing: .06em;
    margin-bottom: 7px;
}

.info-item strong {
    color: var(--navy);
    font-size: 13px;
}

/* FORM */
.form-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 20px;
    margin-bottom: 20px;
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 7px;
}

.form-group label {
    font-weight: 700;
    font-size: 13px;
    color: #344054;
}

.form-control {
    width: 100%;
    padding: 12px 13px;
    border: 1px solid var(--border);
    border-radius: 8px;
    font: inherit;
    font-size: 13px;
    background: white;
    color: var(--text);
    transition: .2s;
}

.form-control:focus {
    outline: 2px solid #b9d3ff;
    border-color: #3978d7;
}

.form-control[readonly] {
    background: #f1f5f9;
    color: var(--muted);
    cursor: not-allowed;
}

textarea.form-control {
    resize: vertical;
    min-height: 100px;
}

.help-text {
    color: var(--muted);
    font-size: 11px;
    line-height: 1.5;
}

/* SUMMARY */
.attendance-summary {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
    margin-bottom: 22px;
}

.count-card {
    padding: 17px;
    border-radius: 10px;
    background: #f7f9fc;
    border: 1px solid var(--border);
}

.count-card small {
    color: var(--muted);
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
}

.count-card strong {
    display: block;
    font-family: 'Manrope', sans-serif;
    font-size: 26px;
    color: var(--navy);
    margin-top: 6px;
}

.count-card.present {
    border-left: 4px solid var(--green);
}

.count-card.absent {
    border-left: 4px solid var(--red);
}

/* BUTTON */
.btn-submit {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    background: var(--blue);
    color: white;
    border: 0;
    padding: 13px 21px;
    border-radius: 9px;
    font: inherit;
    font-size: 13px;
    font-weight: 800;
    cursor: pointer;
    transition: .25s ease;
}

.btn-submit:hover {
    background: var(--navy);
    transform: translateY(-3px);
    box-shadow: 0 7px 16px rgba(0,48,135,.18);
}

.btn-submit:disabled {
    background: #aab5c4;
    cursor: not-allowed;
    transform: none;
    box-shadow: none;
}

.form-actions {
    display: flex;
    justify-content: flex-end;
    margin-top: 22px;
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

.alert-warning {
    background: #fff8e5;
    color: #805b00;
    border: 1px solid #f3dfa5;
}

.status-badge {
    display: inline-block;
    padding: 5px 11px;
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

/* RESPONSIVE */
@media(max-width: 900px) {
    .main-content {
        padding: 25px;
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

    .info-grid {
        grid-template-columns: 1fr;
    }
}

@media(max-width: 520px) {
    .nav-links {
        grid-template-columns: 1fr;
    }

    .form-grid,
    .attendance-summary {
        grid-template-columns: 1fr;
    }

    .panel {
        padding: 18px;
    }

    .page-heading h1 {
        font-size: 23px;
    }

    .form-actions {
        justify-content: stretch;
    }

    .btn-submit {
        width: 100%;
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

        <a href="daily_attendance.php" class="active">
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
        <span class="eyebrow">DAILY ENCODING</span>

        <h1>Daily Attendance</h1>

        <p class="subtitle">
            Record and submit your section's daily attendance for PSAS verification.
        </p>
    </section>

    <?php if ($success): ?>
        <div class="alert alert-success">
            ✓ <?= e($success) ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-error">
            ! <?= e($error) ?>
        </div>
    <?php endif; ?>

    <?php if (!$hasAssignment): ?>

        <div class="alert alert-warning">
            <strong>No section assigned.</strong>
            Your account does not have an academic section yet.
            Please contact the PSAS administrator and ask them to assign your section
            through User Management before submitting attendance.
        </div>

    <?php else: ?>

        <!-- SECTION INFORMATION -->
        <section class="panel">

            <h2>Attendance Information</h2>

            <p class="panel-description">
                Review your assigned class details before submitting.
            </p>

            <div class="info-grid">

                <div class="info-item">
                    <small>DATE</small>
                    <strong><?= e($todayFormatted) ?></strong>
                </div>

                <div class="info-item">
                    <small>ASSIGNED COURSE</small>
                    <strong><?= e($courseName ?: $courseCode) ?></strong>
                </div>

                <div class="info-item">
                    <small>ASSIGNED SECTION</small>
                    <strong><?= e($sectionDisplay) ?></strong>
                </div>

            </div>

            <?php if ($todayReport && $alreadySubmitted): ?>

                <div class="alert alert-success">
                    Today's attendance has already been submitted.
                    You cannot submit another attendance report for this date.
                </div>

                <div class="attendance-summary">

                    <div class="count-card present">
                        <small>Present Students</small>
                        <strong><?= number_format($present) ?></strong>
                    </div>

                    <div class="count-card absent">
                        <small>Absent Students</small>
                        <strong><?= number_format($absent) ?></strong>
                    </div>

                </div>

                <p class="help-text">
                    Submission status:
                    <span class="status-badge <?= e(statusBadgeClass($todayReport['status'])) ?>">
                        <?= e(ucfirst($todayReport['status'])) ?>
                    </span>
                </p>

            <?php else: ?>

                <?php if ($todayReport && strtolower($todayReport['status']) === 'rejected'): ?>
                    <div class="alert alert-warning">
                        Your previous attendance submission was rejected.
                        You may correct the information and submit it again.
                    </div>
                <?php endif; ?>

                <form method="POST" id="attendanceForm">

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= e($_SESSION['csrf_token']) ?>"
                    >

                    <div class="form-grid">

                        <div class="form-group">
                            <label for="total_enrollees">
                                Total Enrolled Students *
                            </label>

                            <input
                                type="number"
                                id="total_enrollees"
                                name="total_enrollees"
                                class="form-control"
                                value="<?= e($totalEnrollees ?: '') ?>"
                                min="1"
                                max="500"
                                required
                            >

                            <small class="help-text">
                                Enter the total number of enrolled students in your section.
                            </small>
                        </div>

                        <div class="form-group">

                            <label for="present_count">
                                Number of Present Students *
                            </label>

                            <input
                                type="number"
                                id="present_count"
                                name="present_count"
                                class="form-control"
                                value="<?= e($present) ?>"
                                min="0"
                                max="<?= (int)$totalEnrollees ?>"
                                required
                            >

                            <small class="help-text">
                                Present students cannot exceed the total enrollment.
                            </small>

                        </div>

                        <div class="form-group">

                            <label for="absent_count">
                                Number of Absent Students
                            </label>

                            <input
                                type="number"
                                id="absent_count"
                                class="form-control"
                                value="<?= e($absent) ?>"
                                readonly
                            >

                            <small class="help-text">
                                Automatically calculated from the total class size.
                            </small>

                        </div>

                    </div>

                    <div class="attendance-summary">

                        <div class="count-card present">
                            <small>Present</small>
                            <strong id="presentPreview"><?= number_format($present) ?></strong>
                        </div>

                        <div class="count-card absent">
                            <small>Absent</small>
                            <strong id="absentPreview"><?= number_format($absent) ?></strong>
                        </div>

                    </div>

                    <div class="form-group">

                        <label for="remarks">
                            Remarks / Notes (Optional)
                        </label>

                        <textarea
                            name="remarks"
                            id="remarks"
                            class="form-control"
                            rows="4"
                            maxlength="1000"
                            placeholder="Add notes that may help PSAS verify this attendance..."
                        ><?= e($remarks) ?></textarea>

                        <small class="help-text">
                            Maximum of 1,000 characters.
                        </small>

                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn-submit" id="submitButton">
                            Submit Attendance →
                        </button>
                    </div>

                </form>

            <?php endif; ?>

        </section>

    <?php endif; ?>

</div>

</main>

<script>
const totalInput = document.getElementById('total_enrollees');
const presentInput = document.getElementById('present_count');
const absentInput = document.getElementById('absent_count');

const presentPreview = document.getElementById('presentPreview');
const absentPreview = document.getElementById('absentPreview');

const submitButton = document.getElementById('submitButton');

function calculateAbsent() {
    if (!totalInput || !presentInput || !absentInput) return;

    const total = parseInt(totalInput.value, 10) || 0;
    let present = parseInt(presentInput.value, 10) || 0;

    // Keep the preview within the current total without changing the submitted value.
    present = Math.max(0, Math.min(present, total));
    const absent = Math.max(0, total - present);

    presentInput.max = total > 0 ? total : 500;
    absentInput.value = absent;

    if (presentPreview) {
        presentPreview.textContent = present;
    }

    if (absentPreview) {
        absentPreview.textContent = absent;
    }
}

if (totalInput) {
    totalInput.addEventListener('input', calculateAbsent);
}

if (presentInput) {
    presentInput.addEventListener('input', calculateAbsent);
}

calculateAbsent();

const attendanceForm = document.getElementById('attendanceForm');

if (attendanceForm) {
    attendanceForm.addEventListener('submit', function(event) {
        const total = parseInt(totalInput.value, 10);
        const present = parseInt(presentInput.value, 10);

        if (
            !Number.isInteger(total) ||
            total < 1 ||
            total > 500 ||
            !Number.isInteger(present) ||
            present < 0 ||
            present > total
        ) {
            event.preventDefault();
            alert('Please enter valid total enrollment and present student counts.');
            return;
        }

        if (!confirm('Are you sure you want to submit today\'s attendance?')) {
            event.preventDefault();
            return;
        }

        submitButton.disabled = true;
        submitButton.textContent = 'Submitting...';
    });
}
</script>

</body>
</html>
