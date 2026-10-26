<?php
// setup.php
require_once 'db.php';

try {
    // Paglikha ng mga kinakailangang talahanayan (tables)
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
            role TEXT NOT NULL, -- 'secretary' o 'psas'
            course_id INTEGER,
            year_id INTEGER,
            FOREIGN KEY (course_id) REFERENCES courses(id),
            FOREIGN KEY (year_id) REFERENCES year_levels(id)
        );

        CREATE TABLE IF NOT EXISTS attendance_reports (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            section_id INTEGER,
            present_count INTEGER NOT NULL,
            reported_by INTEGER,
            date_recorded TEXT NOT NULL,
            FOREIGN KEY (section_id) REFERENCES sections(id),
            FOREIGN KEY (reported_by) REFERENCES users(id)
        );

        CREATE TABLE IF NOT EXISTS stub_batches (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            attendance_id INTEGER,
            quantity_issued INTEGER NOT NULL,
            issued_by INTEGER,
            issued_at TEXT NOT NULL,
            FOREIGN KEY (attendance_id) REFERENCES attendance_reports(id),
            FOREIGN KEY (issued_by) REFERENCES users(id)
        );

        CREATE TABLE IF NOT EXISTS meal_stubs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            batch_id INTEGER,
            stub_code TEXT UNIQUE NOT NULL,
            status TEXT DEFAULT 'Unclaimed', -- 'Unclaimed', 'Distributed', 'Redeemed'
            distributed_by INTEGER,
            distributed_at TEXT,
            FOREIGN KEY (batch_id) REFERENCES stub_batches(id),
            FOREIGN KEY (distributed_by) REFERENCES users(id)
        );
    ");

    // Default password para sa test accounts: 123456
    $password_hash = password_hash('123456', PASSWORD_DEFAULT);

    $checkUser = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    if ($checkUser == 0) {
        $stmt = $pdo->prepare("INSERT INTO users (name, username, password_hash, role) VALUES (?, ?, ?, ?)");
        $stmt->execute(['Class Secretary', 'secretary', $password_hash, 'secretary']);
        $stmt->execute(['PSAS Staff Member', 'psas', $password_hash, 'psas']);
    }

    echo "<h3>Database setup complete!</h3>";
    echo "<p>Nabuo na ang mga tables at test accounts ('secretary' at 'psas') gamit ang password na <b>123456</b>.</p>";

} catch (PDOException $e) {
    echo "Setup Error: " . $e->getMessage();
}
?>
