<?php
// database.php (or db.php)

// Define the path to the SQLite database file inside the storage directory
$dbPath = __DIR__ . '/storage/database.sqlite';

try {
    // Establish a secure PDO connection to SQLite
    $pdo = new PDO('sqlite:' . $dbPath);
    
    // Set PDO error mode to exception to catch errors smoothly
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Enable foreign key constraints for SQLite data integrity
    $pdo->exec('PRAGMA foreign_keys = ON;');

} catch (PDOException $e) {
    http_response_code(500);
    exit("Database connection failed: " . htmlspecialchars($e->getMessage()));
}
?>