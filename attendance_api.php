<?php
session_start();
require_once __DIR__ . '/../db.php'; 
header('Content-Type: application/json');

const VIEW_ROLES = ['secretary', 'psas', 'treasurer'];

function deny(int $code, string $msg): void {
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $msg]);
    exit;
}

// ---- Authentication and role checks ----
if (empty($_SESSION['user_id'])) deny(401, 'Not logged in.');
$role = $_SESSION['role'] ?? '';
if (!in_array($role, VIEW_ROLES, true)) deny(403, 'Forbidden.');

$uid    = (int) $_SESSION['user_id'];
$action = $_GET['action'] ?? '';
$in     = json_decode(file_get_contents('php://input'), true) ?? [];

if (in_array($action, ['create', 'update', 'delete'], true)) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') deny(405, 'POST required.');
    if ($role !== 'secretary') deny(403, 'Only the Secretary can modify reports.');
}

// ---- Validation ----
function validate(array $d): array {
    $errors  = [];
    $course  = trim($d['course'] ?? '');
    $year    = filter_var($d['year_level'] ?? null, FILTER_VALIDATE_INT);
    $section = trim($d['section'] ?? '');
    $date    = $d['report_date'] ?? '';
    $present = filter_var($d['present_count'] ?? null, FILTER_VALIDATE_INT);

    if ($course === '') $errors[] = 'Course is required.';
    if ($year === false || $year < 1 || $year > 6) $errors[] = 'Year level must be 1 to 6.';
    if ($section === '') $errors[] = 'Section is required.';
    $dt = DateTime::createFromFormat('Y-m-d', $date);
    if (!$dt || $dt->format('Y-m-d') !== $date) $errors[] = 'Invalid date.';
    elseif ($date > date('Y-m-d')) $errors[] = 'Date cannot be in the future.';
    if ($present === false || $present < 0) $errors[] = 'Present count must be 0 or more.';

    return [$errors, [
        'course' => $course, 'year_level' => $year, 'section' => $section,
        'report_date' => $date, 'present_count' => $present,
        'remarks' => trim($d['remarks'] ?? ''),
    ]];
}

// It returns the report row if it belongs to this secretary and is still editable
function editableReport(PDO $pdo, int $id, int $uid): array {
    $st = $pdo->prepare('SELECT id, status FROM attendance_reports WHERE id = ? AND secretary_id = ?');
    $st->execute([$id, $uid]);
    $row = $st->fetch();
    if (!$row) deny(404, 'Report not found.');
    if ($row['status'] === 'approved') deny(409, 'Approved reports are locked because stubs were already issued.');
    return $row;
}

try {
    switch ($action) {

        case 'list': // READ
            $sql = 'SELECT ar.*, u.full_name AS secretary_name
                    FROM attendance_reports ar
                    JOIN users u ON u.id = ar.secretary_id';
            $params = [];
            if ($role === 'secretary') {
                $sql .= ' WHERE ar.secretary_id = ?';
                $params[] = $uid;
            }
            $sql .= ' ORDER BY ar.report_date DESC, ar.course, ar.year_level, ar.section';
            $st = $pdo->prepare($sql);
            $st->execute($params);
            echo json_encode(['ok' => true, 'data' => $st->fetchAll()]);
            break;

        case 'create': // CREATE
            [$errors, $v] = validate($in);
            if ($errors) { http_response_code(422); echo json_encode(['ok' => false, 'errors' => $errors]); break; }
            $st = $pdo->prepare(
                'INSERT INTO attendance_reports
                 (secretary_id, course, year_level, section, report_date, present_count, remarks)
                 VALUES (?, ?, ?, ?, ?, ?, ?)');
            $st->execute([$uid, $v['course'], $v['year_level'], $v['section'],
                          $v['report_date'], $v['present_count'], $v['remarks']]);
            echo json_encode(['ok' => true, 'id' => (int) $pdo->lastInsertId()]);
            break;

        case 'update': // UPDATE (pending or rejected only then resubmit it)
            $id = (int) ($in['id'] ?? 0);
            editableReport($pdo, $id, $uid);
            [$errors, $v] = validate($in);
            if ($errors) { http_response_code(422); echo json_encode(['ok' => false, 'errors' => $errors]); break; }
            $st = $pdo->prepare(
                "UPDATE attendance_reports
                 SET course = ?, year_level = ?, section = ?, report_date = ?,
                     present_count = ?, remarks = ?,
                     status = 'pending', reviewed_by = NULL, reviewed_at = NULL, review_remarks = NULL,
                     updated_at = datetime('now')
                 WHERE id = ? AND secretary_id = ?");
            $st->execute([$v['course'], $v['year_level'], $v['section'], $v['report_date'],
                          $v['present_count'], $v['remarks'], $id, $uid]);
            echo json_encode(['ok' => true]);
            break;

        case 'delete': // DELETE (pending or rejected only)
            $id = (int) ($in['id'] ?? 0);
            editableReport($pdo, $id, $uid);
            $st = $pdo->prepare('DELETE FROM attendance_reports WHERE id = ? AND secretary_id = ?');
            $st->execute([$id, $uid]);
            echo json_encode(['ok' => true]);
            break;

        default:
            deny(400, 'Unknown action.');
    }
} catch (PDOException $e) {
    if (str_contains($e->getMessage(), 'UNIQUE')) {
        http_response_code(409);
        echo json_encode(['ok' => false, 'errors' => ['A report for this course, year, section and date already exists.']]);
    } else {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Server error.']);
    }
}