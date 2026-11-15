<?php

// Allow execution only through the command line.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit('404 - Not Found');
}

require_once __DIR__ . '/db.php';

try {
    $pdo->beginTransaction();

    // Check if section_id already exists.
    $columns = $pdo->query("PRAGMA table_info(users)")
                   ->fetchAll(PDO::FETCH_ASSOC);

    $hasSectionId = false;

    foreach ($columns as $column) {
        if ($column['name'] === 'section_id') {
            $hasSectionId = true;
            break;
        }
    }

    // Add section assignment to users.
    if (!$hasSectionId) {
        $pdo->exec("
            ALTER TABLE users
            ADD COLUMN section_id INTEGER
            REFERENCES sections(id)
        ");
    }

    // Index for faster section-based user lookup.
    $pdo->exec("
        CREATE INDEX IF NOT EXISTS idx_users_section
        ON users(section_id)
    ");

    $pdo->commit();

    echo "<h2>Migration successful!</h2>";
    echo "<p>Users table is ready for section assignments.</p>";
    echo "<p>Existing accounts and records were preserved.</p>";

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    http_response_code(500);

    echo "<h2>Migration failed.</h2>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
}
?>
