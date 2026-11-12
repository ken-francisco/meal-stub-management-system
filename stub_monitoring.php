<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'psas') {
    header("Location: login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Stub Monitoring | LVCC Meal Stub System</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f6f9; margin: 0; display: flex; }
        .sidebar { width: 264px; background: #00205b; color: white; height: 100vh; position: fixed; padding: 20px; box-sizing: border-box; }
        .sidebar a { display: block; color: #bfd0df; text-decoration: none; padding: 10px 15px; margin-bottom: 5px; border-radius: 6px; }
        .sidebar a.active, .sidebar a:hover { background: #003087; color: white; }
        .main-content { margin-left: 264px; padding: 40px; width: calc(100% - 264px); box-sizing: border-box; }
        .kpi-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 30px; }
        .kpi-card { background: white; padding: 20px; border-radius: 9px; border: 1px solid #dde3ec; }
        .kpi-card h3 { color: #687788; font-size: 11px; text-transform: uppercase; margin: 0 0 10px 0; }
        .kpi-card strong { font-size: 24px; color: #1d2b3a; }
    </style>
</head>
<body>
    <div class="sidebar">
        <h2>LVCC PSAS</h2>
        <a href="psas_dashboard.php">Overview</a>
        <a href="attendance_verification.php">Attendance Verification</a>
        <a href="stub_issuance.php">Stub Issuance</a>
        <a href="stub_monitoring.php" class="active">Stub Monitoring</a>
        <a href="reports_audit.php">Reports & Audit</a>
        <a href="logout.php" style="margin-top: 40px; background: #dc3545; text-align:center;">Logout</a>
    </div>
    <div class="main-content">
        <h1>Stub monitoring</h1>
        <p style="color: #687788;">Monitor issuance, distribution, and discrepancies across all sections.</p>

        <div class="kpi-grid">
            <div class="kpi-card">
                <h3>Stubs Collected</h3>
                <strong>1,284</strong>
            </div>
            <div class="kpi-card">
                <h3>Stubs Distributed</h3>
                <strong>1,142</strong>
            </div>
            <div class="kpi-card">
                <h3>Stubs Returned</h3>
                <strong>35</strong>
            </div>
        </div>
    </div>
</body>
</html>