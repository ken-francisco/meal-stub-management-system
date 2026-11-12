
<?php

header('Content-Type: application/json');

require_once 'db.php';

// Accept POST requests only
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);

    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method.'
    ]);

    exit;
}

// Receive the Stub ID
$stub_id = filter_input(INPUT_POST, 'stub_id', FILTER_VALIDATE_INT);

// Check if the Stub ID is valid
if (!$stub_id || $stub_id < 1) {
    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => 'Invalid Stub ID.'
    ]);

    exit;
}

try {

    $sql = "UPDATE meal_stubs
            SET status = 'Distributed',
                distributed_at = CURRENT_TIMESTAMP
            WHERE id = :stub_id
            AND status = 'Unclaimed'";

    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        ':stub_id' => $stub_id
    ]);

    if ($stmt->rowCount() > 0) {

        echo json_encode([
            'success' => true,
            'message' => 'Meal stub successfully distributed.'
        ]);

    } else {

        http_response_code(409);

        echo json_encode([
            'success' => false,
            'message' => 'Stub is already distributed or does not exist.'
        ]);

    }

} catch (PDOException $e) {

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Database update failed.'
    ]);

}

?>
