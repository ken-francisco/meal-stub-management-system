<?php
// attendance_api.php
header('Content-Type: application/json');
session_start();
require_once __DIR__ . '/db.php';

// Check if user is authenticated
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access. Please log in.']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // Fetch all attendance records as JSON
    try {
        $stmt = $pdo->query("SELECT * FROM attendance_reports ORDER BY created_at DESC");
        $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['status' => 'success', 'data' => $reports]);
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
} 
elseif ($method === 'POST') {
    // Handle incoming JSON or POST request data
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

    $course = trim($input['course'] ?? '');
    $year_level = (int)($input['year_level'] ?? 0);
    $section = trim($input['section'] ?? '');
    $report_date = trim($input['report_date'] ?? '');
    $present_count = (int)($input['present_count'] ?? 0);
    $remarks = trim($input['remarks'] ?? '');
    $secretary_id = $_SESSION['user_id'];

    if (empty($course) || empty($section) || empty($report_date)) {
        echo json_encode(['status' => 'error', 'message' => 'Required fields are missing.']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO attendance_reports (secretary_id, course, year_level, section, report_date, present_count, remarks)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$secretary_id, $course, $year_level, $section, $report_date, $present_count, $remarks]);
        
        echo json_encode(['status' => 'success', 'message' => 'Attendance report saved successfully!']);
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}
else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
}