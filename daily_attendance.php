<?php
session_start();
require_once __DIR__ . '/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'secretary') {
    header("Location: login.php");
    exit();
}

$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $present = (int)($_POST['present_count'] ?? 0);
    $absent = (int)($_POST['absent_count'] ?? 0);
    $remarks = trim($_POST['remarks'] ?? '');
    
    try {
        $stmt = $pdo->prepare("INSERT INTO attendance_reports (secretary_id, course, year_level, section, report_date, present_count, absent_count, remarks, status) VALUES (?, 'BSIS', 3, '3A', date('now'), ?, ?, ?, 'Pending')");
        $stmt->execute([$_SESSION['user_id'], $present, $absent, $remarks]);
        $success = "Attendance submitted successfully!";
    } catch (Exception $e) {
        $success = "Error submitting report.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Submit Attendance | LVCC Meal Stub System</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@600;700;800&display=swap" rel="stylesheet">
    <style>
        :root { --navy: #00205b; --blue: #003087; --yellow: #f2a900; --bg: #f4f6f9; --text: #1d2b3a; --muted: #687788; --border: #dde3ec; --green: #17835b; }
        body { margin: 0; background: var(--bg); color: var(--text); font-family: "DM Sans", sans-serif; display: flex; }
        .sidebar { width: 264px; background: var(--navy); color: white; position: fixed; height: 100vh; padding: 20px; box-sizing: border-box; }
        .sidebar a { display: block; color: #bfd0df; text-decoration: none; padding: 12px 15px; border-radius: 6px; font-weight: 500; font-size: 13px; margin-bottom: 5px; }
        .sidebar a.active { background: var(--blue); color: white; }
        .main-content { margin-left: 264px; padding: 40px; width: calc(100% - 264px); box-sizing: border-box; }
        .panel { background: white; border: 1px solid var(--border); border-radius: 9px; padding: 30px; box-shadow: 0 3px 12px rgba(0,0,0,0.03); max-width: 900px; }
        .form-control { width: 100%; padding: 12px; border: 1px solid var(--border); border-radius: 6px; font-size: 14px; margin-top: 6px; box-sizing: border-box; }
        .btn-submit { background: var(--blue); color: white; border: none; padding: 12px 24px; border-radius: 8px; font-weight: bold; cursor: pointer; float: right; margin-top: 20px; }
    </style>
</head>
<body>
    <aside class="sidebar">
        <h2 style="font-family: 'Manrope'; font-size: 18px; margin-bottom: 30px;">LVCC Meal Stubs</h2>
        <a href="secretary_dashboard.php">Section Overview</a>
        <a href="daily_attendance.php" class="active">Daily Attendance</a>
        <a href="distribution_tracker.php">Distribution Tracker</a>
    </aside>

    <div class="main-content">
        <span style="color: var(--blue); font-size: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: .1em;">Daily Encoding</span>
        <h1 style="font-family: 'Manrope'; font-size: 28px; margin: 5px 0 30px;">Submit attendance</h1>

        <?php if($success): ?>
            <div style="background: #def7ec; color: var(--green); padding: 15px; border-radius: 6px; margin-bottom: 20px; font-weight: bold;"><?= $success ?></div>
        <?php endif; ?>

        <div class="panel">
            <h3 style="margin-top: 0; font-family: 'Manrope';">Daily attendance record</h3>
            <p style="color: var(--muted); font-size: 13px; margin-bottom: 25px;">All fields marked as required must be completed.</p>

            <form method="POST">
                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 20px; background: #f8fafc; padding: 15px; border-radius: 6px;">
                    <div><small style="color:var(--muted)">DATE</small><br><b>October 24, 2025</b></div>
                    <div><small style="color:var(--muted)">ASSIGNED COURSE</small><br><b>BS Information Systems</b></div>
                    <div><small style="color:var(--muted)">SECTION</small><br><b>BSIS 3A</b></div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                    <div>
                        <label style="font-weight: bold; font-size: 12px;">Number of present students</label>
                        <input type="number" name="present_count" class="form-control" value="34" required>
                        <small style="color: var(--muted);">Maximum of 36 students</small>
                    </div>
                    <div>
                        <label style="font-weight: bold; font-size: 12px;">Number of absent students</label>
                        <input type="number" name="absent_count" class="form-control" value="2" required>
                        <small style="color: var(--muted);">Calculated automatically</small>
                    </div>
                </div>

                <div style="margin-bottom: 20px;">
                    <label style="font-weight: bold; font-size: 12px;">Remarks / notes (Optional)</label>
                    <textarea name="remarks" class="form-control" rows="3" placeholder="Add any notes that may help PSAS verify this attendance..."></textarea>
                </div>

                <button type="submit" class="btn-submit">Preview submission &rarr;</button>
                <div style="clear: both;"></div>
            </form>
        </div>
    </div>
</body>
</html>