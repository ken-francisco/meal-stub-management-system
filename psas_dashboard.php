<?php
session_start();
require_once __DIR__ . '/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'psas') {
    header("Location: login.php");
    exit();
}

$staffName = (string)($_SESSION['name'] ?? $_SESSION['full_name'] ?? 'Ricardo Salazar');
$initials = 'RS';

// Kunin ang mga tunay na datos mula sa database
try {
    $countPending = $pdo->query("SELECT COUNT(*) FROM attendance_reports WHERE status = 'Pending'")->fetchColumn();
    $countApproved = $pdo->query("SELECT COUNT(*) FROM attendance_reports WHERE status = 'Approved'")->fetchColumn();
    $totalStubs = $pdo->query("SELECT COALESCE(SUM(quantity_issued), 0) FROM stub_batches")->fetchColumn();
    $countDiscrepancies = $pdo->query("SELECT COUNT(*) FROM attendance_reports WHERE status = 'Returned'")->fetchColumn();

    $stmt = $pdo->query("
        SELECT ar.*, u.full_name as secretary_name 
        FROM attendance_reports ar 
        JOIN users u ON ar.secretary_id = u.id 
        ORDER BY ar.report_date DESC LIMIT 5
    ");
    $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $reports = [];
    $countPending = 0; $countApproved = 0; $totalStubs = 0; $countDiscrepancies = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>PSAS Staff Dashboard | LVCC Meal Stub System</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@600;700;800&display=swap" rel="stylesheet">
    <style>
        :root { --navy: #00205b; --blue: #003087; --yellow: #f2a900; --bg: #f4f6f9; --text: #1d2b3a; --muted: #687788; --border: #dde3ec; --green: #17835b; --red: #c84646; }
        * { box-sizing: border-box; }
        body { margin: 0; background: var(--bg); color: var(--text); font-family: "DM Sans", sans-serif; display: flex; }
        .sidebar { width: 264px; background: var(--navy); color: white; position: fixed; height: 100vh; padding: 20px; box-sizing: border-box; display: flex; flex-direction: column; }
        .brand { display: flex; align-items: center; gap: 12px; padding-bottom: 20px; border-bottom: 1px solid #ffffff17; }
        .brand b { font-family: "Manrope"; font-size: 18px; display: block; }
        .brand small { color: #b8cadd; font-size: 11px; }
        .nav-label { color: #88a4bd; font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: .14em; margin: 25px 0 10px; }
        nav a { display: flex; align-items: center; justify-content: space-between; color: #bfd0df; text-decoration: none; padding: 12px 15px; border-radius: 6px; font-weight: 500; font-size: 13px; margin-bottom: 5px; }
        nav a.active, nav a:hover { background: var(--blue); color: white; }
        .nav-badge { background: var(--yellow); color: var(--navy); font-size: 10px; font-weight: 800; padding: 2px 8px; border-radius: 10px; }
        .main-content { margin-left: 264px; padding: 40px; width: calc(100% - 264px); box-sizing: border-box; }
        .topbar { display: flex; justify-content: flex-end; align-items: center; margin-bottom: 30px; gap: 15px; }
        .kpi-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin-bottom: 30px; }
        .kpi-card { background: white; padding: 20px; border-radius: 9px; border: 1px solid var(--border); box-shadow: 0 3px 12px rgba(0,0,0,0.03); }
        .kpi-card p { font-size: 11px; color: var(--muted); font-weight: 700; text-transform: uppercase; margin: 0 0 8px 0; }
        .kpi-card strong { font-size: 24px; font-family: "Manrope"; color: #14283b; }
        .panel { background: white; border: 1px solid var(--border); border-radius: 9px; padding: 24px; box-shadow: 0 3px 12px rgba(0,0,0,0.03); }
        table { width: 100%; border-collapse: collapse; font-size: 13px; margin-top: 15px; }
        th, td { padding: 14px 16px; border-bottom: 1px solid var(--border); text-align: left; }
        th { background: #00205b; color: white; font-size: 11px; text-transform: uppercase; letter-spacing: .05em; }
        .badge { padding: 4px 10px; border-radius: 12px; font-size: 10px; font-weight: 700; text-transform: uppercase; }
        .badge-pending { background: #fef3c7; color: #d97706; }
        .badge-approved { background: #def7ec; color: var(--green); }
        .badge-returned { background: #fee2e2; color: var(--red); }
        .btn-export { background: white; border: 1px solid var(--border); padding: 8px 16px; border-radius: 8px; font-weight: bold; cursor: pointer; }
    </style>
</head>
<body>
    <aside class="sidebar">
        <div class="brand">
            <div>
                <b>LVCC</b>
                <small>Meal Stub System</small>
            </div>
        </div>
        <span class="nav-label">Workspace</span>
        <nav>
            <a href="psas_dashboard.php" class="active">Overview <span></span></a>
            <a href="attendance_verification.php">Attendance Verification <span class="nav-badge"><?= $countPending ?></span></a>
            <a href="stub_issuance.php">Stub Issuance</a>
            <a href="stub_monitoring.php">Stub Monitoring</a>
            <a href="reports_audit.php">Reports & Audit</a>
        </nav>
        <div style="margin-top: auto; border-top: 1px solid #ffffff19; padding-top: 15px;">
            <b style="font-size: 12px; display: block;"><?= htmlspecialchars($staffName) ?></b>
            <small style="color: #9db4c8;">PSAS Administrator</small>
            <a href="logout.php" style="color: #ff6b6b; font-size: 12px; margin-top: 8px; display: block; text-decoration: none;">Logout</a>
        </div>
    </aside>

    <div class="main-content">
        <div class="topbar">
            <div style="display: flex; align-items: center; gap: 10px; border-left: 1px solid var(--border); padding-left: 20px;">
                <div style="width: 35px; height: 35px; background: #ffe264; border-radius: 50%; display: grid; place-items: center; font-weight: bold; font-size: 12px; color: var(--navy);"><?= $initials ?></div>
                <div>
                    <b style="font-size: 12px; display: block;"><?= htmlspecialchars($staffName) ?></b>
                    <small style="color: var(--muted); font-size: 10px;">PSAS Staff</small>
                </div>
            </div>
        </div>

        <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 25px;">
            <div>
                <span style="color: var(--blue); font-size: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: .1em;">PSAS Staff Portal</span>
                <h1 style="font-size: 28px; font-family: 'Manrope'; margin: 5px 0 0;">Good morning, <?= explode(' ', $staffName)[0] ?></h1>
                <p style="color: var(--muted); font-size: 13px; margin: 4px 0 0;">Here's what's happening with the meal program today.</p>
            </div>
            <button class="btn-export" onclick="window.print()">↓ Export</button>
        </div>

        <div class="kpi-grid">
            <div class="kpi-card">
                <p>Pending attendance</p>
                <strong><?= $countPending ?></strong>
                <span style="font-size: 11px; color: var(--muted); display: block; margin-top: 5px;">Waiting for approval</span>
            </div>
            <div class="kpi-card">
                <p>Approved attendance</p>
                <strong><?= $countApproved ?></strong>
                <span style="font-size: 11px; color: var(--muted); display: block; margin-top: 5px;">80% approval rate</span>
            </div>
            <div class="kpi-card">
                <p>Total stubs issued</p>
                <strong><?= number_format($totalStubs) ?></strong>
                <span style="font-size: 11px; color: var(--muted); display: block; margin-top: 5px;">Across approved batches</span>
            </div>
            <div class="kpi-card">
                <p>Discrepancies</p>
                <strong><?= $countDiscrepancies ?></strong>
                <span style="font-size: 11px; color: var(--red); display: block; margin-top: 5px; font-weight: bold;">Requires attention</span>
            </div>
        </div>

        <div class="panel">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                <div>
                    <h3 style="font-family: 'Manrope'; font-size: 16px; margin: 0;">Recent submissions</h3>
                    <p style="font-size: 12px; color: var(--muted); margin: 3px 0 0;">Latest attendance reports from class secretaries</p>
                </div>
            </div>

            <?php if (empty($reports)): ?>
                <p style="color: var(--muted); font-style: italic; padding: 20px 0;">No attendance reports found.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Date Submitted</th>
                            <th>Course & Section</th>
                            <th>Present</th>
                            <th>Absent</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reports as $r): ?>
                            <tr>
                                <td><b><?= htmlspecialchars($r['report_date']) ?></b></td>
                                <td><b><?= htmlspecialchars($r['course']) ?></b> - Section <?= htmlspecialchars($r['section']) ?></td>
                                <td><strong style="color: var(--green);"><?= (int)$r['present_count'] ?></strong></td>
                                <td><strong style="color: var(--red);"><?= (int)($r['absent_count'] ?? 0) ?></strong></td>
                                <td><span class="badge badge-<?= strtolower($r['status'] ?? 'pending') ?>"><?= htmlspecialchars($r['status'] ?? 'Pending') ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>