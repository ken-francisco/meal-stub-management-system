<?php
// attendance.php
session_start();
require_once __DIR__ . '/db.php';

// Authentication guard: Only allow secretaries (or adjust role checking if needed)
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'secretary') {
    header("Location: login.php");
    exit;
}

$message = '';

// Handle form submission to record attendance
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_attendance'])) {
    $course = trim($_POST['course'] ?? '');
    $year_level = (int)($_POST['year_level'] ?? 0);
    $section = trim($_POST['section'] ?? '');
    $report_date = trim($_POST['report_date'] ?? '');
    $present_count = (int)($_POST['present_count'] ?? 0);
    $remarks = trim($_POST['remarks'] ?? '');
    $secretary_id = $_SESSION['user_id'];

    if (!empty($course) && !empty($section) && !empty($report_date)) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO attendance_reports (secretary_id, course, year_level, section, report_date, present_count, remarks)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$secretary_id, $course, $year_level, $section, $report_date, $present_count, $remarks]);
            $message = "Attendance report successfully submitted!";
        } catch (PDOException $e) {
            $message = "Error: " . $e->getMessage();
        }
    } else {
        $message = "Please fill in all required fields.";
    }
}

// Fetch submitted attendance reports from database
$reports = [];
try {
    $stmt = $pdo->query("
        SELECT ar.*, u.full_name as secretary_name 
        FROM attendance_reports ar
        LEFT JOIN users u ON ar.secretary_id = u.id
        ORDER BY ar.created_at DESC
    ");
    $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Table might not exist yet if setup hasn't run fully
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Attendance Reports - Meal Stub System</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f6f8; margin: 0; padding: 20px; }
        .container { max-width: 900px; margin: auto; background: #fff; padding: 25px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #ddd; padding-bottom: 15px; margin-bottom: 20px; }
        .form-group { margin-bottom: 12px; }
        .form-group label { display: block; font-weight: bold; margin-bottom: 5px; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        .btn { background: #007bff; color: white; padding: 10px 15px; border: none; border-radius: 4px; cursor: pointer; }
        .btn:hover { background: #0056b3; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: left; font-size: 14px; }
        th { background-color: #f8f9fa; }
        .msg { color: green; font-weight: bold; margin-bottom: 15px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>Attendance Reports Module</h2>
            <a href="secretary_dashboard.php" style="text-decoration: none; color: #007bff;">&larr; Back to Dashboard</a>
        </div>

        <?php if (!empty($message)): ?>
            <p class="msg"><?php echo htmlspecialchars($message); ?></p>
        <?php endif; ?>

        <h3>Submit New Attendance Report</h3>
        <form method="POST" action="">
            <div class="form-group">
                <label>Course / Program:</label>
                <input type="text" name="course" required placeholder="e.g., BSIS">
            </div>
            <div class="form-group">
                <label>Year Level:</label>
                <select name="year_level">
                    <option value="1">1st Year</option>
                    <option value="2" selected>2nd Year</option>
                    <option value="3">3rd Year</option>
                    <option value="4">4th Year</option>
                </select>
            </div>
            <div class="form-group">
                <label>Section:</label>
                <input type="text" name="section" required placeholder="e.g., A">
            </div>
            <div class="form-group">
                <label>Report Date:</label>
                <input type="date" name="report_date" required value="<?php echo date('Y-m-d'); ?>">
            </div>
            <div class="form-group">
                <label>Present Count:</label>
                <input type="number" name="present_count" min="0" required value="0">
            </div>
            <div class="form-group">
                <label>Remarks / Notes:</label>
                <textarea name="remarks" placeholder="Optional notes..."></textarea>
            </div>
            <button type="submit" name="submit_attendance" class="btn">Submit Report</button>
        </form>

        <h3 style="margin-top: 30px;">Submitted Reports History</h3>
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Course & Section</th>
                    <th>Present Count</th>
                    <th>Status</th>
                    <th>Notes</th>
                    <th>Submitted By</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($reports)): ?>
                    <tr><td colspan="6" style="text-align: center;">No attendance reports found.</td></tr>
                <?php else: ?>
                    <?php foreach ($reports as $rep): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($rep['report_date']); ?></td>
                            <td><?php echo htmlspecialchars($rep['course'] . ' ' . $rep['year_level'] . '-' . $rep['section']); ?></td>
                            <td><?php echo htmlspecialchars($rep['present_count']); ?></td>
                            <td><b><?php echo htmlspecialchars(ucfirst($rep['status'])); ?></b></td>
                            <td><?php echo htmlspecialchars($rep['remarks']); ?></td>
                            <td><?php echo htmlspecialchars($rep['secretary_name'] ?? 'Unknown'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</body>
</html>