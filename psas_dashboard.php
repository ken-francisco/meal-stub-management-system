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
                    $successText = 'Attendance report returned.';
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
$totalStubsIssued = 0;

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

    $stubSumStmt = $pdo->query("SELECT COALESCE(SUM(quantity_issued), 0) FROM stub_batches");
    $totalStubsIssued = (int)$stubSumStmt->fetchColumn();

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
        'rejected' => 'returned',
        default => 'pending',
    };
}

function statusLabel(string $status): string
{
    return match ($status) {
        'approved' => 'Approved',
        'rejected' => 'Returned',
        default => 'Pending',
    };
}

function courseTagClass(string $code): string
{
    $upper = strtoupper(trim($code));
    if (str_starts_with($upper, 'BSAIS')) return 'course-bsais';
    if (str_starts_with($upper, 'BSIS'))  return 'course-bsis';
    if (str_starts_with($upper, 'BSA'))   return 'course-bsa';
    if (str_starts_with($upper, 'BAB'))   return 'course-bab';
    if (str_starts_with($upper, 'ACT'))   return 'course-act';
    return 'course-bab';
}

$staffName = (string)($_SESSION['name'] ?? 'PSAS Staff');
$initials = '';
foreach (preg_split('/\s+/', trim($staffName)) as $part) {
    if ($part !== '') {
        $initials .= strtoupper($part[0]);
    }
}
$initials = substr($initials ?: 'PS', 0, 2);

$totalReports = $counts['pending'] + $counts['approved'] + $counts['rejected'];
$approvalRate = $totalReports > 0 ? (int)round(($counts['approved'] / $totalReports) * 100) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PSAS Staff Dashboard | LVCC Meal Stub System</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --navy: #00205b;
            --navy-deep: #00205b;
            --blue: #003087;
            --yellow: #f2a900;
            --soft-yellow: #ffe264;
            --bg: #f4f6f9;
            --text: #1d2b3a;
            --muted: #687788;
            --border: #dde3ec;
            --green: #17835b;
            --red: #c84646;
        }
        * { box-sizing: border-box; }
        html { background: var(--bg); }
        body { margin: 0; color: var(--text); font-family: "DM Sans", sans-serif; -webkit-font-smoothing: antialiased; }
        button, input, select, textarea { font: inherit; }
        button { cursor: pointer; }
        h1, h2, h3, p { margin: 0; }
        h1, h2, h3, .brand { font-family: "Manrope", sans-serif; }

        .app-shell { min-height: 100vh; background: var(--bg); }
        .sidebar { position: fixed; inset: 0 auto 0 0; width: 264px; background: var(--navy); color: white; display: flex; flex-direction: column; z-index: 30; box-shadow: 8px 0 25px #072d5020; }
        .brand { height: 90px; padding: 0 25px; display: flex; align-items: center; gap: 12px; border-bottom: 1px solid #ffffff17; }
        .brand-mark { width: 43px; height: 48px; display: grid; place-items: center; flex: none; }
        .brand-mark img { width: 43px; height: 43px; display: block; border-radius: 50%; object-fit: cover; background: white; box-shadow: 0 0 0 2px #ffffff, 0 3px 9px #00133180; }
        .brand-fallback { width: 43px; height: 43px; border-radius: 50%; background: var(--yellow); color: var(--navy); font: 800 14px "Manrope", sans-serif; display: grid; place-items: center; box-shadow: 0 0 0 2px #ffffff; }
        .brand b { font-size: 19px; letter-spacing: .06em; display: block; }
        .brand small { color: #b8cadd; font: 400 11px "DM Sans"; display: block; margin-top: 2px; }

        .role-switch { padding: 21px 17px 13px; }
        .role-switch > span, .nav-label { display: block; color: #88a4bd; text-transform: uppercase; font-size: 9px; letter-spacing: .14em; font-weight: 700; margin: 0 8px 9px; }
        .role-box { width: 100%; padding: 10px; border: 1px solid #ffffff1a; border-radius: 8px; display: flex; color: white; text-align: left; align-items: center; background: #ffffff0b; gap: 9px; }
        .role-box > div:nth-child(2) { flex: 1; }
        .role-box b { display: block; font-size: 12px; }
        .role-box small { display: block; color: #99b2c8; font-size: 9px; margin-top: 2px; }

        nav { padding: 8px 12px; }
        nav a { color: #bfd0df; text-decoration: none; width: 100%; height: 45px; border-radius: 6px; display: flex; align-items: center; gap: 12px; padding: 0 13px; font-weight: 500; font-size: 13px; position: relative; margin-bottom: 4px; transition: background .15s ease, color .15s ease; }
        nav a:hover { background: #ffffff0d; color: white; }
        nav a.active { background: var(--blue); color: white; box-shadow: inset 0 -3px 0 #001a49; }
        nav a.active::before { content: ""; position: absolute; left: -12px; height: 27px; width: 3px; border-radius: 0 3px 3px 0; background: var(--yellow); }
        .nav-count { margin-left: auto; background: var(--yellow); color: var(--navy); font-style: normal; font-size: 10px; font-weight: 800; min-width: 22px; height: 20px; border-radius: 10px; display: grid; place-items: center; padding: 0 6px; }

        .sidebar-bottom { padding: 13px 14px 18px; margin-top: auto; }
        .profile-card { display: flex; align-items: center; gap: 9px; padding: 12px 10px; border-top: 1px solid #ffffff19; border-bottom: 1px solid #ffffff19; }
        .avatar { width: 38px; height: 38px; flex: none; border-radius: 50%; display: grid; place-items: center; color: var(--navy); background: #f4d978; font-size: 11px; font-weight: 800; }
        .avatar.small { width: 32px; height: 32px; font-size: 9px; }
        .profile-card > div:nth-child(2) { min-width: 0; flex: 1; }
        .profile-card b { display: block; font-size: 11px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .profile-card small { display: block; color: #9db4c8; font-size: 9px; white-space: nowrap; margin-top: 2px; }
        .logout-icon-btn { width: 32px; height: 30px; border: 1px solid #dc4949; border-radius: 8px; background: #c93737; color: white; padding: 0; display: grid; place-items: center; text-decoration: none; box-shadow: 0 3px 0 #8f2424, 0 5px 10px #00122c55; transition: transform .16s ease, box-shadow .16s ease, background .16s ease; }
        .logout-icon-btn:hover { background: #dc4141; transform: translateY(-2px); box-shadow: 0 5px 0 #8f2424, 0 8px 13px #00122c60; }
        .logout-icon-btn:active { transform: translateY(2px); box-shadow: 0 1px 0 #8f2424, 0 2px 5px #00122c45; }
        .sidebar-bottom > p { text-align: center; color: #6686a1; font-size: 8px; margin-top: 15px; letter-spacing: .08em; text-transform: uppercase; }

        .main-shell { margin-left: 264px; min-height: 100vh; }
        .topbar { height: 68px; background: #fff; border-bottom: 1px solid var(--border); display: flex; justify-content: flex-end; align-items: center; padding: 0 37px; position: sticky; top: 0; z-index: 20; }
        .topbar-right, .header-profile { display: flex; align-items: center; }
        .topbar-right { gap: 20px; }
        .header-profile { border-left: 1px solid var(--border); padding-left: 20px; gap: 9px; min-width: 165px; }
        .header-profile b, .header-profile small { display: block; }
        .header-profile b { font-size: 11px; }
        .header-profile small { font-size: 9px; color: var(--muted); margin-top: 1px; }
        .menu-button, .mobile-brand { display: none; }

        main { padding: 34px 38px 50px; max-width: 1550px; margin: 0 auto; }
        .page-header { display: flex; justify-content: space-between; gap: 24px; align-items: flex-end; margin-bottom: 25px; }
        .eyebrow { color: var(--blue); font-size: 9px; font-weight: 800; letter-spacing: .14em; text-transform: uppercase; display: block; margin-bottom: 7px; }
        .page-header h1 { font-size: 27px; letter-spacing: -.025em; line-height: 1.2; color: #14283b; }
        .page-header p { color: var(--muted); font-size: 12px; margin-top: 6px; }
        .header-actions { display: flex; gap: 8px; }

        .button { height: 39px; padding: 0 17px; border-radius: 9px; border: 1px solid var(--blue); display: inline-flex; align-items: center; justify-content: center; gap: 8px; font-size: 11px; font-weight: 700; transition: transform .16s ease, box-shadow .16s ease, background .16s ease; white-space: nowrap; box-shadow: 0 4px 0 #001b4d, 0 7px 14px #00205b22; text-decoration: none; }
        .button:hover:not(:disabled) { transform: translateY(-2px); box-shadow: 0 6px 0 #001b4d, 0 10px 18px #00205b2d; }
        .button:active:not(:disabled) { transform: translateY(2px); box-shadow: 0 1px 0 #001b4d, 0 3px 7px #00205b20; }
        .button.primary { color: white; background: var(--blue); }
        .button.primary:hover:not(:disabled) { background: #0741a5; }
        .button.outline { color: var(--navy); background: white; border-color: #dde3ec; box-shadow: 0 4px 0 #c9d1dc, 0 7px 14px #00205b12; }
        .button.outline:hover:not(:disabled) { border-color: #c5cfdb; background: #fff; box-shadow: 0 6px 0 #c9d1dc, 0 10px 18px #00205b18; }
        .button.outline:active:not(:disabled) { box-shadow: 0 1px 0 #c9d1dc, 0 3px 7px #00205b12; }
        .button.danger { color: white; border-color: #c93737; background: #c93737; box-shadow: 0 4px 0 #8f2424, 0 7px 14px #711e1e24; }
        .button.danger:hover:not(:disabled) { background: #da4141; box-shadow: 0 6px 0 #8f2424, 0 10px 18px #711e1e2d; }
        .button.danger:active:not(:disabled) { box-shadow: 0 1px 0 #8f2424, 0 3px 7px #711e1e20; }
        .button.sm { height: 30px; padding: 0 11px; font-size: 10px; border-radius: 7px; box-shadow: 0 3px 0 #001b4d, 0 4px 8px #00205b18; }
        .button.danger.sm { box-shadow: 0 3px 0 #8f2424, 0 4px 8px #711e1e1a; }

        .notice { padding: 13px 16px; margin-bottom: 18px; border-radius: 8px; font-size: 12px; font-weight: 600; border: 1px solid transparent; }
        .notice.success { color: #167453; background: #e7f6ef; border-color: #bfe7d4; }
        .notice.error { color: #a53d3d; background: #fbeaea; border-color: #f3c4c4; }

        .kpi-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin-bottom: 19px; }
        .kpi-card { min-height: 128px; padding: 20px; background: #fff; border: 1px solid var(--border); border-radius: 9px; display: flex; align-items: flex-start; gap: 14px; position: relative; overflow: hidden; box-shadow: 0 3px 12px #193a5c08; }
        .kpi-icon { width: 39px; height: 39px; border-radius: 7px; display: grid; place-items: center; flex: none; }
        .kpi-icon.amber { background: #fff5ce; color: #a87500; }
        .kpi-icon.green { background: #e7f5ee; color: var(--green); }
        .kpi-icon.blue { background: #e7f1fa; color: var(--blue); }
        .kpi-icon.red { background: #fbeaea; color: var(--red); }
        .kpi-copy p { font-size: 10px; color: var(--muted); font-weight: 600; margin-bottom: 5px; }
        .kpi-copy strong { font: 800 24px "Manrope"; display: block; letter-spacing: -.03em; color: #172c3f; }
        .kpi-copy span { font-size: 9px; color: #8090a0; display: block; margin-top: 6px; }
        .mini-progress { position: absolute; height: 3px; left: 0; right: 0; bottom: 0; background: #edf1f4; }
        .mini-progress i { display: block; height: 100%; background: var(--green); }

        .panel { background: white; border: 1px solid var(--border); border-radius: 9px; box-shadow: 0 3px 12px #193a5c08; overflow: hidden; }
        .panel-head { padding: 19px 21px 15px; display: flex; align-items: center; justify-content: space-between; gap: 15px; flex-wrap: wrap; }
        .panel-head h2 { font-size: 14px; letter-spacing: -.01em; }
        .panel-head p { font-size: 9px; color: var(--muted); margin-top: 4px; }

        .toolbar { padding: 12px 21px 16px; display: flex; justify-content: space-between; gap: 14px; align-items: center; flex-wrap: wrap; border-top: 1px solid #edf1f4; }
        .search-box { height: 36px; border: 1px solid #d9e1e8; border-radius: 5px; display: flex; align-items: center; padding: 0 11px; gap: 8px; color: #82909e; width: 280px; }
        .search-box input { border: 0; outline: 0; width: 100%; color: var(--text); font-size: 11px; background: transparent; }

        .course-filters { display: flex; gap: 6px; flex-wrap: wrap; align-items: center; }
        .course-pill-btn { border: 1px solid transparent; border-radius: 999px; padding: 5px 11px; font-size: 9px; font-weight: 800; line-height: 1; transition: transform .15s ease, box-shadow .15s ease; }
        .course-pill-btn:hover { transform: translateY(-1.5px); }
        .course-pill-btn.active { box-shadow: 0 0 0 2px var(--navy); }

        .course-tag { display: inline-flex; align-items: center; width: fit-content; padding: 4px 9px; border-radius: 999px; font-size: 9px; font-weight: 700; line-height: 1; }
        .course-all   { background: #eef2f6; color: #334155; }
        .course-bsa   { background: #ffe264; color: #4a3800; }
        .course-bsis  { background: #fee2e2; color: #b91c1c; }
        .course-bab   { background: #dbeafe; color: #003087; }
        .course-bsais { background: #f2a900; color: #2b1d00; }
        .course-act   { background: #ffedd5; color: #c2410c; }

        .table-wrap { width: 100%; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; font-size: 11px; }
        th { background: var(--navy); color: white; font-size: 8.5px; text-transform: uppercase; letter-spacing: .075em; text-align: left; padding: 11px 16px; border-top: 1px solid var(--navy); border-bottom: 1px solid var(--navy); white-space: nowrap; }
        td { padding: 12px 16px; border-bottom: 1px solid #edf1f4; white-space: nowrap; color: #4f6070; vertical-align: middle; }
        tr:last-child td { border-bottom: 0; }
        tbody tr:hover { background: #f8fafc; }
        td b { color: #25384a; font-weight: 700; }
        td small { display: block; color: #8996a3; font-size: 9px; margin-top: 3px; }

        .status { display: inline-flex; align-items: center; gap: 5px; padding: 4px 9px; border-radius: 12px; font-size: 9px; font-weight: 700; }
        .status i { width: 5px; height: 5px; border-radius: 50%; }
        .status.pending { color: #9a6a00; background: #fff5d8; }
        .status.pending i { background: #d99b00; }
        .status.approved { color: #167453; background: #e7f6ef; }
        .status.approved i { background: #20a675; }
        .status.returned { color: #a53d3d; background: #fbeaea; }
        .status.returned i { background: #d75c5c; }

        .actions { display: flex; gap: 6px; align-items: center; }
        .empty { padding: 38px 20px; text-align: center; color: var(--muted); font-size: 12px; }
        .footer-note { margin-top: 14px; font-size: 11px; color: var(--muted); }
        .mobile-scrim { display: none; }

        @media (max-width: 1100px) {
            .kpi-grid { grid-template-columns: 1fr 1fr; }
        }
        @media (max-width: 780px) {
            .sidebar { transform: translateX(-100%); transition: transform .25s ease; }
            .sidebar.open { transform: translateX(0); }
            .mobile-scrim.open { display: block; position: fixed; inset: 0; background: #071b2c99; z-index: 25; }
            .main-shell { margin-left: 0; }
            .topbar { justify-content: space-between; padding: 0 18px; height: 62px; }
            .menu-button { display: grid; place-items: center; border: 0; background: none; color: var(--navy); padding: 6px; }
            .mobile-brand { display: block; font: 800 13px "Manrope"; color: var(--navy); }
            .header-profile { min-width: 0; padding-left: 12px; }
            .header-profile > div:nth-child(2) { display: none; }
            main { padding: 25px 18px 40px; }
            .page-header { align-items: flex-start; flex-direction: column; }
            .search-box { width: 100%; }
        }
        @media (max-width: 520px) {
            .kpi-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<div class="app-shell">
    <div class="mobile-scrim" id="scrim" onclick="toggleSidebar()"></div>

    <aside class="sidebar" id="sidebar">
        <div class="brand">
            <div class="brand-mark">
                <img src="assets/lvcc-logo.png" alt="LVCC Logo" onerror="this.style.display='none'; this.nextElementSibling.style.display='grid';">
                <div class="brand-fallback" style="display:none;">LV</div>
            </div>
            <div>
                <b>LVCC</b>
                <small>Meal Stub System</small>
            </div>
        </div>

        <div class="role-switch">
            <span>Viewing As</span>
            <div class="role-box">
                <div class="avatar small"><?= h($initials) ?></div>
                <div>
                    <b>PSAS Staff</b>
                    <small>Stub Verification</small>
                </div>
            </div>
        </div>

        <span class="nav-label">Workspace</span>
        <nav>
            <a href="psas_dashboard.php" class="active">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
                <span>Overview</span>
                <?php if ($counts['pending'] > 0): ?>
                    <i class="nav-count"><?= number_format($counts['pending']) ?></i>
                <?php endif; ?>
            </a>
            <a href="#submissions-panel">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                <span>Attendance Check</span>
                <i class="nav-count"><?= number_format($counts['pending']) ?></i>
            </a>
        </nav>

        <div class="sidebar-bottom">
            <div class="profile-card">
                <div class="avatar"><?= h($initials) ?></div>
                <div>
                    <b><?= h($staffName) ?></b>
                    <small>PSAS Administrator</small>
                </div>
                <a href="logout.php" class="logout-icon-btn" title="Logout" aria-label="Logout">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 17l5-5-5-5M15 12H3"/><path d="M14 3h5a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-5"/></svg>
                </a>
            </div>
            <p>LVCC · Meal Assistance Program</p>
        </div>
    </aside>

    <div class="main-shell">
        <header class="topbar">
            <button type="button" class="menu-button" onclick="toggleSidebar()" aria-label="Toggle menu">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
            </button>
            <div class="mobile-brand">LVCC <span>Meal Stub System</span></div>

            <div class="topbar-right">
                <div class="header-profile">
                    <div class="avatar small"><?= h($initials) ?></div>
                    <div>
                        <b><?= h($staffName) ?></b>
                        <small>PSAS Staff</small>
                    </div>
                </div>
            </div>
        </header>

        <main>
            <div class="page-header">
                <div>
                    <span class="eyebrow">PSAS Staff Portal</span>
                    <h1>Good morning, <?= h($staffName) ?></h1>
                    <p>Review class attendance and create meal stub batches.</p>
                </div>
                <div class="header-actions">
                    <button type="button" class="button outline" onclick="window.print()">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v12M7 10l5 5 5-5M4 21h16"/></svg>
                        Export
                    </button>
                </div>
            </div>

            <?php if ($message !== ''): ?>
                <div class="notice <?= h($messageType) ?>" role="status"><?= h($message) ?></div>
            <?php endif; ?>

            <section class="kpi-grid">
                <article class="kpi-card">
                    <div class="kpi-icon amber">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                    </div>
                    <div class="kpi-copy">
                        <p>Pending attendance</p>
                        <strong><?= number_format($counts['pending']) ?></strong>
                        <span>Waiting for approval</span>
                    </div>
                </article>

                <article class="kpi-card">
                    <div class="kpi-icon green">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M20 6 9 17l-5-5"/></svg>
                    </div>
                    <div class="kpi-copy">
                        <p>Approved attendance</p>
                        <strong><?= number_format($counts['approved']) ?></strong>
                        <span><?= $approvalRate ?>% approval rate</span>
                    </div>
                    <div class="mini-progress"><i style="width: <?= $approvalRate ?>%"></i></div>
                </article>

                <article class="kpi-card">
                    <div class="kpi-icon blue">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 7.5A1.5 1.5 0 0 1 4.5 6h15A1.5 1.5 0 0 1 21 7.5V10a2 2 0 0 0 0 4v2.5a1.5 1.5 0 0 1-1.5 1.5h-15A1.5 1.5 0 0 1 3 16.5V14a2 2 0 0 0 0-4Z"/></svg>
                    </div>
                    <div class="kpi-copy">
                        <p>Total stubs issued</p>
                        <strong><?= number_format($totalStubsIssued) ?></strong>
                        <span>Across approved batches</span>
                    </div>
                </article>

                <article class="kpi-card">
                    <div class="kpi-icon red">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M10.3 3.4 2.5 17a2 2 0 0 0 1.7 3h15.6a2 2 0 0 0 1.7-3L13.7 3.4a2 2 0 0 0-3.4 0Z"/><path d="M12 9v4M12 17h.01"/></svg>
                    </div>
                    <div class="kpi-copy">
                        <p>Returned reports</p>
                        <strong><?= number_format($counts['rejected']) ?></strong>
                        <span>Requires revision</span>
                    </div>
                </article>
            </section>

            <section class="panel" id="submissions-panel">
                <div class="panel-head">
                    <div>
                        <h2>Recent submissions</h2>
                        <p>Latest attendance reports from class secretaries</p>
                    </div>
                    <div class="course-filters" aria-label="Filter by course">
                        <button type="button" class="course-pill-btn course-all active" onclick="filterCourse('ALL', this)">All</button>
                        <button type="button" class="course-pill-btn course-bsa" onclick="filterCourse('BSA', this)">BSA</button>
                        <button type="button" class="course-pill-btn course-bsis" onclick="filterCourse('BSIS', this)">BSIS</button>
                        <button type="button" class="course-pill-btn course-bab" onclick="filterCourse('BAB', this)">BAB</button>
                        <button type="button" class="course-pill-btn course-bsais" onclick="filterCourse('BSAIS', this)">BSAIS</button>
                        <button type="button" class="course-pill-btn course-act" onclick="filterCourse('ACT', this)">ACT</button>
                    </div>
                </div>

                <div class="toolbar">
                    <div class="search-box">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg>
                        <input type="text" id="searchInput" onkeyup="filterTable()" placeholder="Search course, section, or secretary...">
                    </div>
                </div>

                <?php if (!$reports): ?>
                    <div class="empty">
                        <strong>No attendance reports found.</strong>
                        <p style="margin-top:4px;">Reports submitted by class secretaries will appear here.</p>
                    </div>
                <?php else: ?>
                    <div class="table-wrap">
                        <table id="reportsTable">
                            <thead>
                                <tr>
                                    <th>Date submitted</th>
                                    <th>Course &amp; section</th>
                                    <th>Year</th>
                                    <th>Present</th>
                                    <th>Submitted by</th>
                                    <th>Status</th>
                                    <th>Stub batch</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($reports as $report): ?>
                                <?php
                                    $status = (string)($report['status'] ?? 'pending');
                                    $courseLabel = trim((string)($report['course_code'] ?? ''));
                                    if ($courseLabel === '') {
                                        $courseLabel = (string)($report['course_name'] ?? 'N/A');
                                    }
                                    $sectionLabel = (string)($report['section_name'] ?? 'No section');
                                    $yearLabel = (string)($report['year_level'] ?? '—');
                                ?>
                                <tr data-course="<?= h(strtoupper($courseLabel)) ?>">
                                    <td>
                                        <b><?= h($report['date_recorded']) ?></b>
                                        <small>#<?= (int)$report['id'] ?></small>
                                    </td>
                                    <td>
                                        <span class="course-tag <?= h(courseTagClass($courseLabel)) ?>">
                                            <?= h($courseLabel) ?> <?= h($sectionLabel) ?>
                                        </span>
                                    </td>
                                    <td><?= h($yearLabel) ?></td>
                                    <td><b><?= (int)$report['present_count'] ?></b></td>
                                    <td><?= h($report['secretary_name'] ?? 'Unknown') ?></td>
                                    <td>
                                        <span class="status <?= h(statusClass($status)) ?>">
                                            <i></i><?= h(statusLabel($status)) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($report['batch_id'] !== null): ?>
                                            <b>#<?= (int)$report['batch_id'] ?></b>
                                            <small><?= (int)$report['quantity_issued'] ?> stubs issued</small>
                                        <?php else: ?>
                                            <small>Not generated</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($status === 'pending' && $report['batch_id'] === null): ?>
                                            <div class="actions">
                                                <form method="post" onsubmit="return confirm('Approve and issue <?= (int)$report['present_count'] ?> stubs?');">
                                                    <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>">
                                                    <input type="hidden" name="report_id" value="<?= (int)$report['id'] ?>">
                                                    <input type="hidden" name="action" value="approve">
                                                    <button class="button primary sm" type="submit">Approve</button>
                                                </form>
                                                <form method="post" onsubmit="return confirm('Return this attendance report?');">
                                                    <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>">
                                                    <input type="hidden" name="report_id" value="<?= (int)$report['id'] ?>">
                                                    <input type="hidden" name="action" value="reject">
                                                    <button class="button danger sm" type="submit">Return</button>
                                                </form>
                                            </div>
                                        <?php else: ?>
                                            <small>Processed</small>
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
                Approving a report creates one batch record in <code>stub_batches</code> matching the present-student count.
            </p>
        </main>
    </div>
</div>

<script>
    let activeCourse = 'ALL';

    function toggleSidebar() {
        document.getElementById('sidebar').classList.toggle('open');
        document.getElementById('scrim').classList.toggle('open');
    }

    function filterCourse(course, btn) {
        activeCourse = course;
        document.querySelectorAll('.course-pill-btn').forEach(el => el.classList.remove('active'));
        btn.classList.add('active');
        filterTable();
    }

    function filterTable() {
        const query = (document.getElementById('searchInput')?.value || '').toLowerCase();
        const rows = document.querySelectorAll('#reportsTable tbody tr');
        rows.forEach(row => {
            const rowCourse = row.getAttribute('data-course') || '';
            const text = row.textContent.toLowerCase();
            const matchCourse = activeCourse === 'ALL' || rowCourse.startsWith(activeCourse);
            const matchQuery = text.includes(query);
            row.style.display = (matchCourse && matchQuery) ? '' : 'none';
        });
    }
</script>
</body>
</html>
