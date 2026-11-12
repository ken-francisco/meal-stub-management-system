<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'psas') {
    header("Location: login.php");
    exit();
}

try {
    $stmt = $pdo->query("
        SELECT ar.*, u.full_name as secretary_name 
        FROM attendance_reports ar 
        JOIN users u ON ar.secretary_id = u.id 
        ORDER BY ar.report_date DESC
    ");
    $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $reports = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Verification | LVCC Meal Stub System</title>
    <link rel="stylesheet" href="style.css">
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f6f9; margin: 0; padding: 0; display: flex; }
        .sidebar { width: 264px; background: #00205b; color: white; height: 100vh; position: fixed; padding: 20px; box-sizing: border-box; }
        .sidebar a { display: block; color: #bfd0df; text-decoration: none; padding: 10px 15px; margin-bottom: 5px; border-radius: 6px; font-weight: 500; }
        .sidebar a.active, .sidebar a:hover { background: #003087; color: white; }
        .main-content { margin-left: 264px; padding: 40px; width: calc(100% - 264px); box-sizing: border-box; }
        .panel { background: white; border: 1px solid #dde3ec; border-radius: 9px; padding: 24px; box-shadow: 0 3px 12px rgba(0,0,0,0.03); }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 13px; }
        th, td { padding: 12px 16px; border-bottom: 1px solid #edf1f4; text-align: left; }
        th { background: #00205b; color: white; font-size: 11px; text-transform: uppercase; }
        .badge { padding: 4px 8px; border-radius: 12px; font-size: 10px; font-weight: bold; text-transform: uppercase; }
        .badge-pending { background: #fef3c7; color: #d97706; }
        .badge-approved { background: #def7ec; color: #03543f; }
        .badge-rejected { background: #fee2e2; color: #991b1b; }
    </style>
</head>
<body>
    <div class="sidebar">
        <h2>LVCC PSAS</h2>
        <a href="psas_dashboard.php">Overview</a>
        <a href="attendance_verification.php" class="active">Attendance Verification</a>
        <a href="stub_issuance.php">Stub Issuance</a>
        <a href="stub_monitoring.php">Stub Monitoring</a>
        <a href="reports_audit.php">Reports & Audit</a>
        <a href="logout.php" style="margin-top: 40px; background: #dc3545; text-align:center;">Logout</a>
    </div>
    <div class="main-content">
        <h1>Attendance Verification</h1>
        <p style="color: #687788;">Review and verify daily attendance submitted by class secretaries.</p>
        
        <div class="panel">
            <h3>All Submissions</h3>
            <table>
                <thead>
                    <tr>
                        <th>Date Submitted</th>
                        <th>Course & Section</th>
                        <th>Submitted By</th>
                        <th>Present</th>
                        <th>Absent</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reports as $r): ?>
                        <tr>
                            <td><?= htmlspecialchars($r['report_date']) ?></td>
                            <td><b><?= htmlspecialchars($r['course']) ?></b> - Section <?= htmlspecialchars($r['section']) ?></td>
                            <td><?= htmlspecialchars($r['secretary_name']) ?></td>
                            <td><strong style="color: #17835b;"><?= (int)$r['present_count'] ?></strong></td>
                            <td><strong style="color: #c84646;"><?= (int)($r['absent_count'] ?? 0) ?></strong></td>
                            <td><span class="badge badge-<?= strtolower($r['status'] ?? 'pending') ?>"><?= htmlspecialchars($r['status'] ?? 'Pending') ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>