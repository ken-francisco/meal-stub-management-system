<?php
session_start();
$role = $_SESSION['role'] ?? '';
if (empty($_SESSION['user_id']) || !in_array($role, ['secretary', 'psas', 'treasurer'], true)) {
    header('Location: ../login.php');
    exit;
}
$canEdit = ($role === 'secretary');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Attendance</title>
  <link rel="stylesheet" href="../css/style.css">
</head>
<body data-can-edit="<?= $canEdit ? '1' : '0' ?>">
  <h1><?= $canEdit ? 'Attendance Encoding Form' : 'Attendance Reports' ?></h1>
  <div id="messages" role="alert"></div>

  <?php if ($canEdit): ?>
  <form id="attendanceForm" novalidate>
    <input type="hidden" id="reportId">

    <label>Course
      <input type="text" id="course" required placeholder="e.g. BSIS">
    </label>

    <label>Year Level
      <select id="year_level" required>
        <option value="">Select</option>
        <option>1</option><option>2</option><option>3</option><option>4</option>
      </select>
    </label>

    <label>Section
      <input type="text" id="section" required placeholder="e.g. A">
    </label>

    <label>Date
      <input type="date" id="report_date" required>
    </label>

    <label>Present Students
      <input type="number" id="present_count" min="0" required>
    </label>

    <label>Remarks (optional)
      <textarea id="remarks" rows="2"></textarea>
    </label>

    <button type="submit" id="saveBtn">Submit Report</button>
    <button type="button" id="cancelBtn" hidden>Cancel Edit</button>
  </form>
  <?php endif; ?>

  <h2>Submitted Reports</h2>
  <table id="reportsTable">
    <thead>
      <tr>
        <th>Date</th><th>Course</th><th>Year</th><th>Section</th>
        <th>Present</th><th>Status</th><th>Notes</th>
        <th><?= $canEdit ? 'Actions' : 'Submitted by' ?></th>
      </tr>
    </thead>
    <tbody></tbody>
  </table>

  <script src="attendance.js"></script>
</body>
</html>