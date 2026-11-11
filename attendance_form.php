<?php
// attendance_form.php
session_start();
require_once 'db.php';

$message = '';
$error = '';

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Kunin ang secretary ID mula sa session, kung wala ay default sa 1 para sa testing
    $secretaryId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 1;
    
    $course      = trim($_POST['course']);
    $yearLevel   = intval($_POST['year_level']);
    $section     = trim($_POST['section']);
    $reportDate  = trim($_POST['report_date']);
    $presentCount = intval($_POST['present_count']);
    $absentCount = isset($_POST['absent_count']) ? intval($_POST['absent_count']) : 0;
    $remarks     = trim($_POST['remarks']);

    if (empty($course) || empty($section) || empty($reportDate)) {
        $error = "Please fill in all required fields.";
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO attendance_reports 
                (secretary_id, course, year_level, section, report_date, present_count, absent_count, status, remarks) 
                VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', ?)");
            
            $stmt->execute([
                $secretaryId, 
                $course, 
                $yearLevel, 
                $section, 
                $reportDate, 
                $presentCount, 
                $absentCount, 
                $remarks
            ]);

            $message = "Attendance report successfully submitted!";
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submit Attendance - LVCC Meal Stub System</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .form-group { margin-bottom: 16px; }
        .form-label { display: block; font-size: 13px; font-weight: 700; color: var(--primary-blue); margin-bottom: 6px; text-transform: uppercase; }
        .form-control { width: 100%; padding: 12px; border: 1px solid var(--border-color); border-radius: 8px; font-size: 14px; outline: none; }
        .alert-success { padding: 12px; border-radius: 8px; margin-bottom: 16px; font-size: 14px; background: #DEF7EC; color: #03543F; }
        .alert-error { padding: 12px; border-radius: 8px; margin-bottom: 16px; font-size: 14px; background: #FEE2E2; color: #991B1B; }
    </style>
</head>
<body>

    <nav class="navbar">
        <div class="brand"><span>LVCC <small style="font-weight: normal; color: #64748B;">Meal Stub System</small></span></div>
        <a href="secretary_dashboard.php" class="btn-link" style="margin-top:0;">&lsaquo; Back to Dashboard</a>
    </nav>

    <main class="app-container" style="max-width: 600px;">
        <header class="page-header" style="margin-bottom: 20px;">
            <span class="role-tag">DAILY ENCODING</span>
            <h1 class="greeting" style="font-size: 22px;">Submit Attendance</h1>
            <p style="color: var(--text-muted); font-size: 14px;">Record today's class attendance for PSAS verification.</p>
        </header>

        <?php if (!empty($message)): ?>
            <div class="alert-success"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="card">
            <form action="attendance_form.php" method="POST">
                <div class="form-group">
                    <label class="form-label">Date</label>
                    <input type="date" name="report_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Assigned Course</label>
                    <input type="text" name="course" class="form-control" value="BS Information Systems" required>
                </div>

                <div style="display: flex; gap: 12px;">
                    <div class="form-group" style="flex: 1;">
                        <label class="form-label">Year Level</label>
                        <input type="number" name="year_level" class="form-control" value="2" min="1" max="6" required>
                    </div>
                    <div class="form-group" style="flex: 1;">
                        <label class="form-label">Section</label>
                        <input type="text" name="section" class="form-control" value="2A" required>
                    </div>
                </div>

                <div style="display: flex; gap: 12px;">
                    <div class="form-group" style="flex: 1;">
                        <label class="form-label">Number of Present Students</label>
                        <input type="number" name="present_count" class="form-control" min="0" value="35" required>
                    </div>
                    <div class="form-group" style="flex: 1;">
                        <label class="form-label">Number of Absent Students</label>
                        <input type="number" name="absent_count" class="form-control" min="0" value="1" required>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Remarks (Optional)</label>
                    <textarea name="remarks" class="form-control" placeholder="e.g., Complete attendance for morning session" rows="3"></textarea>
                </div>

                <button type="submit" class="btn-primary" style="margin-top: 10px; width: 100%;">Submit Attendance Report</button>
            </form>
        </div>
    </main>
</body>
</html>