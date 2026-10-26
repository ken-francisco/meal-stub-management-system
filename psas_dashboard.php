<?php
// psas_dashboard.php — PSAS attendance verification and stub batch generation
session_start();

if (!isset($_SESSION['user_id'], $_SESSION['role']) || $_SESSION['role'] !== 'psas') {
    header('Location: login.php');
    exit();
}

require_once __DIR__ . '/db.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function h($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

$message = '';
$messageType = 'success';

// Handle Approve / Reject actions.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';

    if (!is_string($csrfToken) || !hash_equals($_SESSION['csrf_token'], $csrfToken)) {
        $message = 'Security check failed. Refresh the page and try again.';
        $messageType = 'error';
    } else {
        $reportId = filter_input(INPUT_POST, 'report_id', FILTER_VALIDATE_INT);
        $action = $_POST['action'] ?? '';

        if (!$reportId || !in_array($action, ['approve', 'reject'], true)) {
            $message = 'Invalid request. Please try again.';
            $messageType = 'error';
        } else {
            try {
                $pdo->beginTransaction();

                // Locking is limited in SQLite; the transaction and conditional update
                // below ensure the report can only transition from pending once.
                $reportStmt = $pdo->prepare(
                    'SELECT id, present_count, status
                     FROM attendance_reports
                     WHERE id = :id'
                );
                $reportStmt->execute([':id' => $reportId]);
                $report = $reportStmt->fetch(PDO::FETCH_ASSOC);

                if (!$report) {
                    throw new RuntimeException('Attendance report not found.');
                }

                if ($report['status'] !== 'pending') {
                    throw new RuntimeException('This report has already been processed.');
                }

                if ($action === 'approve') {
                     $presentCount = filter_var(
                     $report['present_count'],
                     FILTER_VALIDATE_INT
                     );

                 if ($presentCount === false || $presentCount < 0) {
                     throw new RuntimeException('The attendance count is invalid.');
                     }

                    // Unique index on stub_batches.attendance_id prevents duplicate batches.
                    $batchStmt = $pdo->prepare(
                        'INSERT INTO stub_batches
                            (attendance_id, quantity_issued, issued_by, issued_at)
                         VALUES
                            (:attendance_id, :quantity_issued, :issued_by, :issued_at)'
                    );
                    $batchStmt->execute([
                        ':attendance_id' => $reportId,
                        ':quantity_issued' => $presentCount,
                        ':issued_by' => (int)$_SESSION['user_id'],
                        ':issued_at' => date('Y-m-d H:i:s'),
                    ]);

                    $newStatus = 'approved';
                    $successText = 'Attendance approved and stub batch generated.';
                } else {
                    $newStatus = 'rejected';
                    $successText = 'Attendance report rejected.';
                }

                $updateStmt = $pdo->prepare(
                    "UPDATE attendance_reports
                     SET status = :status
                     WHERE id = :id AND status = 'pending'"
                );
                $updateStmt->execute([
                    ':status' => $newStatus,
                    ':id' => $reportId,
                ]);

                if ($updateStmt->rowCount() !== 1) {
                    throw new RuntimeException('The report was processed by another request. Refresh and check its status.');
                }

                $pdo->commit();
                $message = $successText;
                $messageType = 'success';
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                error_log('PSAS dashboard action failed: ' . $e->getMessage());
                $message = $e instanceof RuntimeException
                    ? $e->getMessage()
                    : 'Unable to process this report. Check the PHP error log for details.';
                $messageType = 'error';
            }
        }
    }
}

// Dashboard summary counts.
$counts = ['pending' => 0, 'approved' => 0, 'rejected' => 0];
try {
    $countStmt = $pdo->query(
        "SELECT status, COUNT(*) AS total
         FROM attendance_reports
         GROUP BY status"
    );
    foreach ($countStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if (array_key_exists($row['status'], $counts)) {
            $counts[$row['status']] = (int)$row['total'];
        }
    }

    // Include section/course/year and secretary details where available.
    $reportsStmt = $pdo->query(
        "SELECT
            ar.id,
            ar.present_count,
            ar.date_recorded,
            ar.status,
            s.section_name,
            c.course_code,
            c.course_name,
            yl.year_level,
            u.name AS secretary_name,
            sb.id AS batch_id,
            sb.quantity_issued
         FROM attendance_reports ar
         LEFT JOIN sections s ON s.id = ar.section_id
         LEFT JOIN courses c ON c.id = s.course_id
         LEFT JOIN year_levels yl ON yl.id = s.year_id
         LEFT JOIN users u ON u.id = ar.reported_by
         LEFT JOIN stub_batches sb ON sb.attendance_id = ar.id
         ORDER BY
            CASE ar.status
                WHEN 'pending' THEN 0
                WHEN 'rejected' THEN 1
                WHEN 'approved' THEN 2
                ELSE 3
            END,
            ar.date_recorded DESC,
            ar.id DESC"
    );
    $reports = $reportsStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('PSAS dashboard query failed: ' . $e->getMessage());
    $reports = [];
    $message = 'Could not load attendance data. Confirm that setup.php completed successfully.';
    $messageType = 'error';
}

function statusClass(string $status): string
{
    return match ($status) {
        'approved' => 'approved',
        'rejected' => 'rejected',
        default => 'pending',
    };
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PSAS Staff Dashboard | Free Meal Stub System</title>
    <style>
        :root {
            --navy: #073763;
            --blue: #1769aa;
            --blue-light: #eaf3ff;
            --gold: #ffcc00;
            --bg: #f3f6fb;
            --text: #17243a;
            --muted: #66758b;
            --border: #e2e8f0;
            --green: #16794b;
            --green-bg: #e6f6ed;
            --red: #b42318;
            --red-bg: #feeceb;
            --amber: #8a5b00;
            --amber-bg: #fff4d6;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 24px;
            background: var(--bg);
            color: var(--text);
            font-family: Arial, Helvetica, sans-serif;
        }
        .topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            flex-wrap: wrap;
            padding: 22px 26px;
            color: white;
            background: var(--navy);
            border-radius: 16px;
            box-shadow: 0 8px 24px rgba(7, 55, 99, .12);
        }
        .brand { margin: 0; font-size: clamp(22px, 3vw, 30px); }
        .subtitle { margin: 7px 0 0; color: #d7e7f8; font-size: 14px; }
        .user-actions { display: flex; align-items: center; gap: 14px; flex-wrap: wrap; }
        .welcome { color: #fff1a8; }
        .logout {
            display: inline-block; padding: 10px 14px; border-radius: 8px;
            background: #c93737; color: white; text-decoration: none; font-weight: 700;
        }
        .logout:hover { background: #a92d2d; }
        .page-heading { margin: 28px 0 16px; }
        .page-heading h2 { margin: 0 0 7px; font-size: 24px; }
        .page-heading p { margin: 0; color: var(--muted); line-height: 1.5; }
        .summary {
            display: grid; grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 16px; margin-bottom: 22px;
        }
        .summary-card {
            background: white; padding: 20px; border-radius: 14px;
            border: 1px solid var(--border); box-shadow: 0 3px 12px rgba(20, 40, 70, .04);
        }
        .summary-label { color: var(--muted); font-size: 14px; font-weight: 700; }
        .summary-value { margin-top: 10px; font-size: 32px; font-weight: 800; }
        .summary-card.pending-card { border-top: 4px solid #e2a400; }
        .summary-card.approved-card { border-top: 4px solid #19945a; }
        .summary-card.rejected-card { border-top: 4px solid #d64545; }
        .panel {
            background: white; border: 1px solid var(--border); border-radius: 14px;
            overflow: hidden; box-shadow: 0 3px 12px rgba(20, 40, 70, .04);
        }
        .panel-heading {
            display: flex; justify-content: space-between; align-items: center;
            gap: 12px; flex-wrap: wrap; padding: 20px 22px; border-bottom: 1px solid var(--border);
        }
        .panel-heading h3 { margin: 0; font-size: 18px; }
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; min-width: 900px; }
        th, td { padding: 14px 16px; border-bottom: 1px solid var(--border); text-align: left; }
        th { background: #f8fafc; color: #506078; font-size: 12px; text-transform: uppercase; letter-spacing: .04em; }
        td { font-size: 14px; vertical-align: middle; }
        tbody tr:hover { background: #fbfdff; }
        .badge {
            display: inline-block; padding: 6px 9px; border-radius: 999px;
            font-size: 12px; font-weight: 700; text-transform: capitalize;
        }
        .badge.pending { color: var(--amber); background: var(--amber-bg); }
        .badge.approved { color: var(--green); background: var(--green-bg); }
        .badge.rejected { color: var(--red); background: var(--red-bg); }
        .actions { display: flex; gap: 7px; align-items: center; }
        .btn {
            border: 0; border-radius: 7px; padding: 8px 10px; font-weight: 700;
            font-size: 12px; cursor: pointer;
        }
        .btn-approve { background: var(--green-bg); color: var(--green); }
        .btn-reject { background: var(--red-bg); color: var(--red); }
        .btn:disabled { opacity: .45; cursor: not-allowed; }
        .notice { padding: 13px 16px; margin: 0 0 18px; border-radius: 9px; line-height: 1.45; }
        .notice.success { color: var(--green); background: var(--green-bg); }
        .notice.error { color: var(--red); background: var(--red-bg); }
        .empty { padding: 36px 20px; color: var(--muted); text-align: center; }
        .small-muted { color: var(--muted); font-size: 12px; margin-top: 4px; }
        .footer-note { margin: 14px 2px 0; color: var(--muted); font-size: 12px; line-height: 1.5; }
        @media (max-width: 720px) {
            body { padding: 12px; }
            .topbar { padding: 18px; }
            .summary { grid-template-columns: 1fr; gap: 10px; }
            .summary-card { padding: 16px; }
            .summary-value { font-size: 27px; }
        }
    </style>
</head>
<body>
<header class="topbar">
    <div>
        <h1 class="brand">PSAS Staff Portal</h1>
        <p class="subtitle">Free Meal Stub Management System · Attendance Verification</p>
    </div>
    <div class="user-actions">
        <span class="welcome">Welcome, <strong><?= h($_SESSION['name'] ?? 'PSAS Staff') ?></strong>!</span>
        <a class="logout" href="logout.php">Logout</a>
    </div>
</header>

<main>
    <section class="page-heading">
        <h2>Attendance &amp; Stub Issuance</h2>
        <p>Review attendance submitted by class secretaries. Approving a report creates one stub batch based on its present-student count.</p>
    </section>

    <?php if ($message !== ''): ?>
        <div class="notice <?= h($messageType) ?>" role="status"><?= h($message) ?></div>
    <?php endif; ?>

    <section class="summary" aria-label="Attendance report summary">
        <article class="summary-card pending-card">
            <div class="summary-label">Pending Reports</div>
            <div class="summary-value"><?= number_format($counts['pending']) ?></div>
        </article>
        <article class="summary-card approved-card">
            <div class="summary-label">Approved Reports</div>
            <div class="summary-value"><?= number_format($counts['approved']) ?></div>
        </article>
        <article class="summary-card rejected-card">
            <div class="summary-label">Rejected Reports</div>
            <div class="summary-value"><?= number_format($counts['rejected']) ?></div>
        </article>
    </section>

    <section class="panel">
        <div class="panel-heading">
            <h3>Attendance Reports</h3>
            <span class="small-muted">Newest reports are shown first within each status.</span>
        </div>

        <?php if (!$reports): ?>
            <div class="empty">
                <strong>No attendance reports found.</strong>
                <p>Reports submitted by the Secretary will appear here after they are saved in the database.</p>
            </div>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th>ID</th>
                        <th>Course / Section</th>
                        <th>Year Level</th>
                        <th>Date</th>
                        <th>Present</th>
                        <th>Submitted By</th>
                        <th>Status</th>
                        <th>Stub Batch</th>
                        <th>Action</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($reports as $report): ?>
                        <?php
                            $status = (string)($report['status'] ?? 'pending');
                            $courseLabel = trim((string)($report['course_code'] ?? ''));
                            if ($courseLabel === '') {
                                $courseLabel = (string)($report['course_name'] ?? 'Course not set');
                            }
                            $sectionLabel = (string)($report['section_name'] ?? 'Section not set');
                            $yearLabel = (string)($report['year_level'] ?? 'Year not set');
                        ?>
                        <tr>
                            <td>#<?= (int)$report['id'] ?></td>
                            <td>
                                <strong><?= h($courseLabel) ?></strong>
                                <div class="small-muted"><?= h($sectionLabel) ?></div>
                            </td>
                            <td><?= h($yearLabel) ?></td>
                            <td><?= h($report['date_recorded']) ?></td>
                            <td><strong><?= (int)$report['present_count'] ?></strong></td>
                            <td><?= h($report['secretary_name'] ?? 'Unknown') ?></td>
                            <td><span class="badge <?= h(statusClass($status)) ?>"><?= h($status) ?></span></td>
                            <td>
                                <?php if ($report['batch_id'] !== null): ?>
                                    <strong>#<?= (int)$report['batch_id'] ?></strong>
                                    <div class="small-muted"><?= (int)$report['quantity_issued'] ?> stubs issued</div>
                                <?php else: ?>
                                    <span class="small-muted">Not generated</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($status === 'pending' && $report['batch_id'] === null): ?>
                                    <div class="actions">
                                        <form method="post" onsubmit="return confirm('Approve this attendance report and generate <?= (int)$report['present_count'] ?> stubs?');">
                                            <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>">
                                            <input type="hidden" name="report_id" value="<?= (int)$report['id'] ?>">
                                            <input type="hidden" name="action" value="approve">
                                            <button class="btn btn-approve" type="submit">Approve</button>
                                        </form>
                                        <form method="post" onsubmit="return confirm('Reject this attendance report?');">
                                            <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>">
                                            <input type="hidden" name="report_id" value="<?= (int)$report['id'] ?>">
                                            <input type="hidden" name="action" value="reject">
                                            <button class="btn btn-reject" type="submit">Reject</button>
                                        </form>
                                    </div>
                                <?php else: ?>
                                    <span class="small-muted">Processed</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <p class="footer-note">
        Note: This module creates a batch record in <code>stub_batches</code>. Individual stub codes in
        <code>meal_stubs</code> are not generated by this page yet; coordinate that part with the Distribution module owner.
    </p>
</main>
</body>
</html>
