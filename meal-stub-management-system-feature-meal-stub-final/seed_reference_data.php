<?php

// Allow execution only through the command line.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit('404 - Not Found');
}

require_once __DIR__ . '/db.php';


try {
    $pdo->beginTransaction();

    // Academic programs
    $programs = [
        'BSIS'  => 'Bachelor of Science in Information Systems',
        'ACT'   => 'ACT',
        'BSAIS' => 'Bachelor of Science in Accounting Information System',
        'BSA'   => 'Bachelor of Science in Accountancy',
        'BAB'   => 'BAB',
        'BSSW'  => 'Bachelor of Science in Social Work'
    ];

    // Year levels
    $yearLevels = [
        '1st Year',
        '2nd Year',
        '3rd Year',
        '4th Year'
    ];

    // Insert courses if they do not exist.
    $courseIds = [];

    foreach ($programs as $code => $name) {
        $stmt = $pdo->prepare("
            SELECT id FROM courses
            WHERE course_code = ?
            LIMIT 1
        ");
        $stmt->execute([$code]);

        $courseId = $stmt->fetchColumn();

        if (!$courseId) {
            $stmt = $pdo->prepare("
                INSERT INTO courses (course_code, course_name)
                VALUES (?, ?)
            ");
            $stmt->execute([$code, $name]);

            $courseId = $pdo->lastInsertId();
        }

        $courseIds[$code] = (int)$courseId;
    }

    // Insert year levels if they do not exist.
    $yearIds = [];

    foreach ($yearLevels as $year) {
        $stmt = $pdo->prepare("
            SELECT id FROM year_levels
            WHERE year_level = ?
            LIMIT 1
        ");
        $stmt->execute([$year]);

        $yearId = $stmt->fetchColumn();

        if (!$yearId) {
            $stmt = $pdo->prepare("
                INSERT INTO year_levels (year_level)
                VALUES (?)
            ");
            $stmt->execute([$year]);

            $yearId = $pdo->lastInsertId();
        }

        $yearIds[$year] = (int)$yearId;
    }

    // ACT has only 2 years. Other programs have 4.
    foreach ($programs as $code => $name) {
        $allowedYears = $code === 'ACT'
            ? ['1st Year', '2nd Year']
            : $yearLevels;

        foreach ($allowedYears as $year) {
            // One section per program and year level.
            $stmt = $pdo->prepare("
                SELECT id FROM sections
                WHERE course_id = ?
                  AND year_id = ?
                  AND section_name = ?
                LIMIT 1
            ");

            $stmt->execute([
                $courseIds[$code],
                $yearIds[$year],
                $year
            ]);

            if (!$stmt->fetchColumn()) {
                $stmt = $pdo->prepare("
                    INSERT INTO sections
                        (section_name, course_id, year_id)
                    VALUES (?, ?, ?)
                ");

                $stmt->execute([
                    $year,
                    $courseIds[$code],
                    $yearIds[$year]
                ]);
            }
        }
    }

    $pdo->commit();

    echo "<h2>Academic data successfully added!</h2>";
    echo "<p>Programs, year levels, and sections are ready.</p>";
    echo "<p>Existing records were preserved.</p>";

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    http_response_code(500);

    echo "<h2>Seeding failed.</h2>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
}
?>
