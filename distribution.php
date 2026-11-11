
<?php
require_once 'db.php';

$sql = "SELECT
            ms.id,
            ms.stub_code,
            ms.status,
            c.course_code,
            yl.year_level,
            s.section_name
        FROM meal_stubs ms
        LEFT JOIN stub_batches sb ON ms.batch_id = sb.id
        LEFT JOIN attendance_reports ar ON sb.attendance_id = ar.id
        LEFT JOIN sections s ON ar.section_id = s.id
        LEFT JOIN courses c ON s.course_id = c.id
        LEFT JOIN year_levels yl ON s.year_id = yl.id
        ORDER BY ms.id ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute();

$stubs = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Meal Stub Distribution</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f4f6f9;
            padding: 30px;
        }

        .container {
            max-width: 1100px;
            margin: auto;
            background: white;
            padding: 25px;
            border-radius: 10px;
        }

        h2 {
            color: #263b64;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        th, td {
            padding: 12px;
            border-bottom: 1px solid #ddd;
            text-align: left;
        }

        th {
            background: #263b64;
            color: white;
        }

        .unclaimed {
            color: #d97706;
            font-weight: bold;
        }

        .distributed {
            color: #16a34a;
            font-weight: bold;
        }

        button {
            background: #263b64;
            color: white;
            border: none;
            padding: 9px 12px;
            border-radius: 5px;
            cursor: pointer;
        }

        button:disabled {
            background: #999;
            cursor: not-allowed;
        }

        .message {
            margin-top: 15px;
            font-weight: bold;
        }

        .empty {
            text-align: center;
            padding: 20px;
        }
    </style>
</head>

<body>

<div class="container">

    <h2>Meal Stub Distribution Tracker</h2>

    <p>Monitor and manage the distribution of meal stubs to students.</p>

    <table>
        <thead>
            <tr>
                <th>Stub ID</th>
                <th>Stub Code</th>
                <th>Course</th>
                <th>Year Level</th>
                <th>Section</th>
                <th>Status</th>
                <th>Action</th>
            </tr>
        </thead>

        <tbody>

        <?php if (count($stubs) > 0): ?>

            <?php foreach ($stubs as $stub): ?>

                <tr data-stub-row="<?= (int)$stub['id'] ?>">

                    <td><?= (int)$stub['id'] ?></td>

                    <td><?= htmlspecialchars($stub['stub_code']) ?></td>

                    <td><?= htmlspecialchars($stub['course_code'] ?? 'N/A') ?></td>

                    <td><?= htmlspecialchars($stub['year_level'] ?? 'N/A') ?></td>

                    <td><?= htmlspecialchars($stub['section_name'] ?? 'N/A') ?></td>

                    <td class="status">
                        <span class="<?= $stub['status'] === 'Distributed' ? 'distributed' : 'unclaimed' ?>">
                            <?= htmlspecialchars($stub['status']) ?>
                        </span>
                    </td>

                    <td class="action">

                        <?php if ($stub['status'] === 'Unclaimed'): ?>

                            <button
                                class="distribute-btn"
                                data-stub-id="<?= (int)$stub['id'] ?>">
                                Mark as Distributed
                            </button>

                        <?php elseif ($stub['status'] === 'Distributed'): ?>

                            <button disabled>
                                Already Distributed
                            </button>

                        <?php else: ?>

                            <button disabled>
                                <?= htmlspecialchars($stub['status']) ?>
                            </button>

                        <?php endif; ?>

                    </td>

                </tr>

            <?php endforeach; ?>

        <?php else: ?>

            <tr>
                <td colspan="7" class="empty">
                    No meal stubs available.
                </td>
            </tr>

        <?php endif; ?>

        </tbody>
    </table>

    <p id="message" class="message"></p>

</div>

<script src="distribution.js"></script>

</body>
</html>
