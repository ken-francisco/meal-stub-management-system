<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'psas') {
    header("Location: login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reports & Audit | LVCC Meal Stub System</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f6f9; margin: 0; display: flex; }
        .sidebar { width: 264px; background: #00205b; color: white; height: 100vh; position: fixed; padding: 20px; box-sizing: border-box; }
        .sidebar a { display: block; color: #bfd0df; text-decoration: none; padding: 10px 15px; margin-bottom: 5px; border-radius: 6px; }
        .sidebar a.active, .sidebar a:hover { background: #003087; color: white; }
        .main-content { margin-left: 264px; padding: 40px; width: calc(100% - 264px); box-sizing: border-box; }
        .btn-export { background: #00205b; color: white; border: none; padding: 10px 16px; border-radius: 6px; font-weight: bold; cursor: pointer; margin-right: 10px; }
    </style>
</head>
<body>
    <div class="sidebar">
        <h2>LVCC PSAS</h2>
        <a href="psas_dashboard.php">Overview</a>
        <a href="attendance_verification.php">Attendance Verification</a>
        <a href="stub_issuance.php">Stub Issuance</a>
        <a href="stub_monitoring.php">Stub Monitoring</a>
        <a href="reports_audit.php" class="active">Reports & Audit</a>
        <a href="logout.php" style="margin-top: 40px; background: #dc3545; text-align:center;">Logout</a>
    </div>
    <div class="main-content">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <div>
                <h1>Reports & audit</h1>
                <p style="color: #687788;">Analyze attendance and stub utilization across the institution.</p>
            </div>
            <div>
                <button class="btn-export" onclick="alert('Exporting CSV...')">Export CSV</button>
                <button class="btn-export" onclick="alert('Exporting PDF...')">Export PDF</button>
            </div>
        </div>
    </div>
</body>
</html>