
<?php
$dbDirectory = __DIR__ . '/storage';
$dbPath = $dbDirectory . '/database.sqlite';

// Create storage directory if it does not exist
if (!is_dir($dbDirectory)) {
    mkdir($dbDirectory, 0775, true);
}

try {
    $pdo = new PDO('sqlite:' . $dbPath);

    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // Enable foreign key constraints
    $pdo->exec('PRAGMA foreign_keys = ON;');

} catch (PDOException $e) {
    http_response_code(500);
    exit('Database connection failed. Please check your SQLite setup.');
}
?>
