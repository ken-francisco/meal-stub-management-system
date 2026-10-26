<?php
// setup.php
require_once 'db.php';

try {
    // Create required database tables.
    $pdo->exec("
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
            course_id INTEGER,
            year_id INTEGER,
            section_name TEXT NOT NULL,
            FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
            FOREIGN KEY (year_id) REFERENCES year_levels(id) ON DELETE CASCADE
        );

        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            username TEXT UNIQUE NOT NULL,
            password_hash TEXT NOT NULL,
            role TEXT NOT NULL,
            course_id INTEGER,
            year_id INTEGER,
            FOREIGN KEY (course_id) REFERENCES courses(id),
            FOREIGN KEY (year_id) REFERENCES year_levels(id)
        );

        CREATE TABLE IF NOT EXISTS attendance_reports (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            section_id INTEGER,
            present_count INTEGER NOT NULL CHECK (present_count >= 0),
            reported_by INTEGER,
            date_recorded TEXT NOT NULL,
            status TEXT NOT NULL DEFAULT 'pending'
                CHECK (status IN ('pending', 'approved', 'rejected')),
            FOREIGN KEY (section_id) REFERENCES sections(id),
            FOREIGN KEY (reported_by) REFERENCES users(id)
        );

        CREATE TABLE IF NOT EXISTS stub_batches (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            attendance_id INTEGER NOT NULL UNIQUE,
            quantity_issued INTEGER NOT NULL CHECK (quantity_issued >= 0),
            issued_by INTEGER,
            issued_at TEXT NOT NULL,
            FOREIGN KEY (attendance_id) REFERENCES attendance_reports(id),
            FOREIGN KEY (issued_by) REFERENCES users(id)
        );

        CREATE TABLE IF NOT EXISTS meal_stubs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            batch_id INTEGER,
            stub_code TEXT UNIQUE NOT NULL,
            status TEXT NOT NULL DEFAULT 'Unclaimed'
                CHECK (status IN ('Unclaimed', 'Distributed', 'Redeemed')),
            distributed_by INTEGER,
            distributed_at TEXT,
            FOREIGN KEY (batch_id) REFERENCES stub_batches(id),
            FOREIGN KEY (distributed_by) REFERENCES users(id)
        );
    ");

    // Migrate databases created before attendance_reports.status was added.
    $attendanceColumns = $pdo
        ->query("PRAGMA table_info(attendance_reports)")
        ->fetchAll(PDO::FETCH_COLUMN, 1);

    if (!in_array('status', $attendanceColumns, true)) {
        $pdo->exec("
            ALTER TABLE attendance_reports
            ADD COLUMN status TEXT NOT NULL DEFAULT 'pending'
            CHECK (status IN ('pending', 'approved', 'rejected'))
        ");
    }

    // Prevent duplicate stub batches for the same attendance report.
    // Existing duplicate attendance_id values must be resolved before this succeeds.
    $pdo->exec("
        CREATE UNIQUE INDEX IF NOT EXISTS
        idx_stub_batches_attendance_id
        ON stub_batches(attendance_id)
    ");

    // Default password for test accounts: 123456.
    // Create defaults only when the users table is empty.
    $userCount = (int) $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();

    if ($userCount === 0) {
        $passwordHash = password_hash('123456', PASSWORD_DEFAULT);

        $stmt = $pdo->prepare("
            INSERT INTO users (name, username, password_hash, role)
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

    echo "<h3>Database setup complete!</h3>";
    echo "<p>Tables are ready, including attendance approval status and duplicate-batch protection.</p>";
    echo "<p>Test accounts (created only if the users table was empty): "
       . "<b>secretary</b> and <b>psas</b>; password: <b>123456</b>.</p>";
    echo "<p>Change the default test passwords before using real data.</p>";

} catch (PDOException $e) {
    http_response_code(500);
    error_log("Database setup error: " . $e->getMessage());
    echo "<h3>Database setup failed.</h3>";
    echo "<p>Check the PHP error log for details. Back up the database before retrying setup.</p>";
}
?>
