<?php

// Allow execution only through the command line.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit('404 - Not Found');
}

require_once __DIR__ . '/db.php';


try {
    $pdo->beginTransaction();

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            full_name TEXT NOT NULL,
            username TEXT UNIQUE NOT NULL,
            password_hash TEXT NOT NULL,
            role TEXT NOT NULL CHECK (role IN ('secretary', 'psas', 'treasurer')),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS courses (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            course_code TEXT NOT NULL,
            course_name TEXT NOT NULL
        );

        CREATE TABLE IF NOT EXISTS year_levels (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            year_level TEXT NOT NULL
        );

        CREATE TABLE IF NOT EXISTS sections (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            section_name TEXT NOT NULL,
            course_id INTEGER,
            year_id INTEGER,
            FOREIGN KEY (course_id) REFERENCES courses(id),
            FOREIGN KEY (year_id) REFERENCES year_levels(id)
        );

        CREATE TABLE IF NOT EXISTS attendance_reports (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            secretary_id INTEGER NOT NULL,
            course TEXT NOT NULL,
            year_level INTEGER NOT NULL CHECK (year_level BETWEEN 1 AND 6),
            section TEXT NOT NULL,
            report_date TEXT NOT NULL,
            present_count INTEGER NOT NULL CHECK (present_count >= 0),
            absent_count INTEGER NOT NULL DEFAULT 0 CHECK (absent_count >= 0),
            status TEXT NOT NULL DEFAULT 'pending'
                CHECK (status IN ('pending', 'approved', 'rejected')),
            remarks TEXT,
            reviewed_by INTEGER,
            reviewed_at TIMESTAMP,
            review_remarks TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP,
            FOREIGN KEY (secretary_id) REFERENCES users(id),
            FOREIGN KEY (reviewed_by) REFERENCES users(id)
        );

        CREATE TABLE IF NOT EXISTS stub_batches (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            attendance_report_id INTEGER NOT NULL UNIQUE,
            psas_staff_id INTEGER NOT NULL,
            stubs_issued INTEGER NOT NULL CHECK (stubs_issued >= 0),
            issue_date TEXT NOT NULL,
            status TEXT NOT NULL DEFAULT 'issued'
                CHECK (status IN ('issued', 'collected', 'completed')),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (attendance_report_id) REFERENCES attendance_reports(id),
            FOREIGN KEY (psas_staff_id) REFERENCES users(id)
        );

        CREATE TABLE IF NOT EXISTS meal_stubs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            batch_id INTEGER NOT NULL,
            stub_code TEXT UNIQUE NOT NULL,
            status TEXT NOT NULL DEFAULT 'Unclaimed'
                CHECK (status IN ('Unclaimed', 'Distributed', 'Redeemed')),
            distributed_by INTEGER,
            distributed_at TEXT,
            FOREIGN KEY (batch_id) REFERENCES stub_batches(id),
            FOREIGN KEY (distributed_by) REFERENCES users(id)
        );
    ");

    // Check whether absent_count exists in older databases.
    $columns = $pdo->query(
        "PRAGMA table_info(attendance_reports)"
    )->fetchAll(PDO::FETCH_ASSOC);

    $hasAbsentCount = false;

    foreach ($columns as $column) {
        if ($column['name'] === 'absent_count') {
            $hasAbsentCount = true;
            break;
        }
    }

    if (!$hasAbsentCount) {
        $pdo->exec("
            ALTER TABLE attendance_reports
            ADD COLUMN absent_count INTEGER NOT NULL DEFAULT 0
        ");
    }

    // Add indexes for frequently used queries.
    $pdo->exec("
        CREATE INDEX IF NOT EXISTS idx_attendance_secretary
        ON attendance_reports(secretary_id);

        CREATE INDEX IF NOT EXISTS idx_attendance_status
        ON attendance_reports(status);

        CREATE INDEX IF NOT EXISTS idx_meal_stubs_batch
        ON meal_stubs(batch_id);
    ");

    // Create test accounts only if users table is empty.
    $userCount = (int) $pdo->query(
        "SELECT COUNT(*) FROM users"
    )->fetchColumn();

    if ($userCount === 0) {
        $passwordHash = password_hash('123456', PASSWORD_DEFAULT);

        $stmt = $pdo->prepare("
            INSERT INTO users (full_name, username, password_hash, role)
            VALUES (?, ?, ?, ?)
        ");

        $stmt->execute([
            'Class Secretary',
            'secretary',
            $passwordHash,
            'secretary'
        ]);

        $stmt->execute([
            'PSAS Staff Member',
            'psas',
            $passwordHash,
            'psas'
        ]);
    }

    $pdo->commit();

    echo "
        <h2>Database setup successful!</h2>
        <p>Tables, indexes, and migration checks completed.</p>
        <p>No sample attendance reports or meal stubs were inserted.</p>
        <p>Default test password, if accounts were newly created: 123456</p>
    ";

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    http_response_code(500);

    echo "<h2>Database setup failed.</h2>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
}
?>
