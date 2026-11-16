<?php
// Retired legacy API. Attendance submissions are handled by daily_attendance.php.
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');
http_response_code(410);

echo json_encode([
    'status' => 'error',
    'message' => 'This legacy endpoint has been retired. Please use the official attendance module.'
]);
exit;
