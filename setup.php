<?php
// setup.php
require_once __DIR__ . '/db.php';

try {
    // Execute table creation compatible with all modules
    $pdo->exec("
        PRAGMA foreign_keys = ON;

        -- 1. USERS TABLE
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

        -- 3. YEAR LEVELS TABLE
        CREATE TABLE IF NOT EXISTS year_levels (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            year_level TEXT NOT NULL
        );

        -- 4. SECTIONS TABLE
        CREATE TABLE IF NOT EXISTS sections (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            section_name TEXT NOT NULL,
            course_id INTEGER,
            year_id INTEGER,
            FOREIGN KEY (course_id) REFERENCES courses(id),
            FOREIGN KEY (year_id) REFERENCES year_levels(id)
        );

        -- 5. ATTENDANCE REPORTS TABLE (Includes absent_count)
        CREATE TABLE IF NOT EXISTS attendance_reports (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            secretary_id INTEGER NOT NULL,
            course TEXT NOT NULL,
            year_level INTEGER NOT NULL CHECK (year_level BETWEEN 1 AND 6),
            section TEXT NOT NULL,
            report_date TEXT NOT NULL,
            present_count INTEGER NOT NULL CHECK (present_count >= 0),
            absent_count INTEGER NOT NULL CHECK (absent_count >= 0),
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

        -- 6. STUB BATCHES TABLE
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

        -- 7. MEAL STUBS / DISTRIBUTION TABLE
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

    // Safe migration: Check if absent_count column exists in existing attendance_reports table, if not, add it
    $columns = $pdo->query("PRAGMA table_info(attendance_reports)")->fetchAll(PDO::FETCH_ASSOC);
    $hasAbsentCount = false;
    foreach ($columns as $col) {
        if ($col['name'] === 'absent_count') {
            $hasAbsentCount = true;
            break;
        }
    }
    if (!$hasAbsentCount) {
        $pdo->exec("ALTER TABLE attendance_reports ADD COLUMN absent_count INTEGER NOT NULL DEFAULT 0;");
    }

    // Create default test accounts if users table is empty
    $userCount = (int) $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    if ($userCount === 0) {
        $passwordHash = password_hash('123456', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (full_name, username, password_hash, role) VALUES (?, ?, ?, ?)");
        $stmt->execute(['Class Secretary', 'secretary', $passwordHash, 'secretary']);
        $stmt->execute(['PSAS Staff Member', 'psas', $passwordHash, 'psas']);
    }

    // Insert sample test data for distribution if meal_stubs is empty
    $stubCount = (int) $pdo->query("SELECT COUNT(*) FROM meal_stubs")->fetchColumn();
    if ($stubCount === 0) {
        $pdo->exec("INSERT INTO attendance_reports (secretary_id, course, year_level, section, report_date, present_count, absent_count, status) 
                    VALUES (1, 'BSIS', 2, '2A', '2026-11-11', 35, 1, 'approved')");
        
        $pdo->exec("INSERT INTO stub_batches (attendance_report_id, psas_staff_id, stubs_issued, issue_date, status) 
                    VALUES (1, 2, 3, '2026-11-11', 'issued')");

        $pdo->exec("INSERT INTO meal_stubs (batch_id, stub_code, status) VALUES 
                    (1, 'STUB-BSIS-2A-001', 'Unclaimed'),
                    (1, 'STUB-BSIS-2A-002', 'Unclaimed'),
                    (1, 'STUB-BSIS-2A-003', 'Distributed')");
    }

    echo "<h3>Database setup & sample seeding complete!</h3>";
    echo "<p>Test stubs and absent count column are fully updated and ready.</p>";

} catch (PDOException $e) {
    http_response_code(500);
    echo "<h3>Database setup failed.</h3>";
    echo "<p>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>