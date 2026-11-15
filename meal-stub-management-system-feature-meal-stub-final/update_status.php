<?php
// Retired legacy API. Stub distribution is handled by distribution_tracker.php.
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');
http_response_code(410);

echo json_encode([
    'success' => false,
    'message' => 'This legacy endpoint has been retired. Please use the official distribution module.'
]);
exit;
