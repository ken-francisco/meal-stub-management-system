<?php
// setup.php
require_once __DIR__ . '/db.php';

try {
    // Patakbuhin ang paglikha ng mga tables na tugma sa lahat ng modules
    $pdo->exec("
        PRAGMA foreign_keys = ON;

        -- 1. USERS TABLE (Sinusuportahan ang full_name para sa API at authentication)
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            full_name TEXT NOT NULL,
            username TEXT UNIQUE NOT NULL,
            password_hash TEXT NOT NULL,
            role TEXT NOT NULL CHECK (role IN ('secretary', 'psas', 'treasurer')),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );

        -- 2. COURSES TABLE
        CREATE TABLE IF NOT EXISTS courses (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            course_code TEXT NOT NULL,
            course_name TEXT NOT NULL
        );

        -- 3. ATTENDANCE REPORTS TABLE (Tugma sa code at API ng Attendance module)
        CREATE TABLE IF NOT EXISTS attendance_reports (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            secretary_id INTEGER NOT NULL,
            course TEXT NOT NULL,
            year_level INTEGER NOT NULL CHECK (year_level BETWEEN 1 AND 6),
            section TEXT NOT NULL,
            report_date TEXT NOT NULL,
            present_count INTEGER NOT NULL CHECK (present_count >= 0),
            status TEXT NOT NULL DEFAULT 'pending' CHECK (status IN ('pending', 'approved', 'rejected')),
            remarks TEXT,
            reviewed_by INTEGER,
            reviewed_at TIMESTAMP,
            review_remarks TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP,
            FOREIGN KEY (secretary_id) REFERENCES users(id),
            FOREIGN KEY (reviewed_by) REFERENCES users(id)
        );

        -- 4. STUB BATCHES TABLE (Module para sa PSAS Stub Generator)
        CREATE TABLE IF NOT EXISTS stub_batches (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            attendance_report_id INTEGER NOT NULL UNIQUE,
            psas_staff_id INTEGER NOT NULL,
            stubs_issued INTEGER NOT NULL CHECK (stubs_issued >= 0),
            issue_date TEXT NOT NULL,
            status TEXT NOT NULL DEFAULT 'issued' CHECK (status IN ('issued', 'collected', 'completed')),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (attendance_report_id) REFERENCES attendance_reports(id),
            FOREIGN KEY (psas_staff_id) REFERENCES users(id)
        );

        -- 5. MEAL STUBS / DISTRIBUTION TABLE
        CREATE TABLE IF NOT EXISTS meal_stubs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            batch_id INTEGER NOT NULL,
            stub_code TEXT UNIQUE NOT NULL,
            status TEXT NOT NULL DEFAULT 'Unclaimed' CHECK (status IN ('Unclaimed', 'Distributed', 'Redeemed')),
            distributed_by INTEGER,
            distributed_at TEXT,
            FOREIGN KEY (batch_id) REFERENCES stub_batches(id),
            FOREIGN KEY (distributed_by) REFERENCES users(id)
        );
    ");

    // Lumikha ng default test accounts kung wala pang laman ang users table
    $userCount = (int) $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();

    if ($userCount === 0) {
        $passwordHash = password_hash('123456', PASSWORD_DEFAULT);

        $stmt = $pdo->prepare("
            INSERT INTO users (full_name, username, password_hash, role)
            VALUES (?, ?, ?, ?)
        ");

        $stmt->execute(['Class Secretary', 'secretary', $passwordHash, 'secretary']);
        $stmt->execute(['PSAS Staff Member', 'psas', $passwordHash, 'psas']);
    }

    echo "<h3>Database setup complete!</h3>";
    echo "<p>Lahat ng tables at columns ay matagumpay na naitayo.</p>";
    echo "<p>Test accounts — <b>secretary</b> / <b>psas</b> (Password: <b>123456</b>).</p>";

} catch (PDOException $e) {
    http_response_code(500);
    echo "<h3>Database setup failed.</h3>";
    echo "<p>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>