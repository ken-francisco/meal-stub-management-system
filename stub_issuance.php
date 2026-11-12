<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'psas') {
    header("Location: login.php");
    exit();
}

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $course = $_POST['course'] ?? '';
    $section = $_POST['section'] ?? '';
    $quantity = (int)($_POST['quantity'] ?? 0);
    $issued_by = $_SESSION['user_id'];

    try {
        $stmt = $pdo->prepare("INSERT INTO stub_batches (quantity_issued, issued_by, issued_at) VALUES (?, ?, datetime('now'))");
        $stmt->execute([$quantity, $issued_by]);
        $message = "Stub batch successfully issued!";
    } catch (PDOException $e) {
        $message = "Error: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Stub Issuance | LVCC Meal Stub System</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f6f9; margin: 0; display: flex; }
        .sidebar { width: 264px; background: #00205b; color: white; height: 100vh; position: fixed; padding: 20px; box-sizing: border-box; }
        .sidebar a { display: block; color: #bfd0df; text-decoration: none; padding: 10px 15px; margin-bottom: 5px; border-radius: 6px; }
        .sidebar a.active, .sidebar a:hover { background: #003087; color: white; }
        .main-content { margin-left: 264px; padding: 40px; width: calc(100% - 264px); box-sizing: border-box; }
        .panel { background: white; border: 1px solid #dde3ec; border-radius: 9px; padding: 24px; margin-bottom: 20px; }
        .form-group { margin-bottom: 15px; display: flex; flex-direction: column; }
        .form-group label { font-weight: bold; font-size: 11px; margin-bottom: 5px; text-transform: uppercase; color: #00205b; }
        .form-control { padding: 10px; border: 1px solid #dde3ec; border-radius: 6px; font-size: 13px; }
        .btn-primary { background: #003087; color: white; border: none; padding: 10px 20px; border-radius: 6px; font-weight: bold; cursor: pointer; }
    </style>
</head>
<body>
    <div class="sidebar">
        <h2>LVCC PSAS</h2>
        <a href="psas_dashboard.php">Overview</a>
        <a href="attendance_verification.php">Attendance Verification</a>
        <a href="stub_issuance.php" class="active">Stub Issuance</a>
        <a href="stub_monitoring.php">Stub Monitoring</a>
        <a href="reports_audit.php">Reports & Audit</a>
        <a href="logout.php" style="margin-top: 40px; background: #dc3545; text-align:center;">Logout</a>
    </div>
    <div class="main-content">
        <h1>Issue meal stubs</h1>
        <p style="color: #687788;">Create and track meal stub batches for each class section.</p>

        <?php if($message): ?><div style="background:#e7f6ef; color:#167453; padding:10px; border-radius:6px; margin-bottom:15px;"><?= $message ?></div><?php endif; ?>

        <div class="panel">
            <h3>New Issuance</h3>
            <form method="POST">
                <div class="form-group">
                    <label>Course</label>
                    <select name="course" class="form-control">
                        <option value="BSIS">BSIS</option>
                        <option value="BSAIS">BSAIS</option>
                        <option value="BSA">BSA</option>
                        <option value="BAB">BAB</option>
                        <option value="ACT">ACT</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Section</label>
                    <input type="text" name="section" class="form-control" placeholder="e.g., 3A" required>
                </div>
                <div class="form-group">
                    <label>Total Stub Quantity</label>
                    <input type="number" name="quantity" class="form-control" required>
                </div>
                <button type="submit" class="btn-primary">Issue Stubs</button>
            </form>
        </div>
    </div>
</body>
</html>