<?php
// db.php - Database Connection using __DIR__ for absolute pathing
$db_file = __DIR__ . '/database.sqlite';

try {
    $pdo = new PDO("sqlite:" . $db_file);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // Siguraduhing naka-enable ang foreign key constraints sa SQLite
    $pdo->exec('PRAGMA foreign_keys = ON;');
} catch (PDOException $e) {
    http_response_code(500);
    die("Database Connection Failed: " . htmlspecialchars($e->getMessage()));
}
?>