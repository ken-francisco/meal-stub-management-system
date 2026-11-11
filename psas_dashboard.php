<?php
// psas_dashboard.php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'psas') {
    header("Location: login.php");
    exit();
}

// Kunin ang mga isinumiteng attendance reports at ang pangalan ng secretary galing sa database
try {
    $stmt = $pdo->query("
        SELECT ar.*, u.full_name as secretary_name 
        FROM attendance_reports ar 
        JOIN users u ON ar.secretary_id = u.id 
        ORDER BY ar.report_date DESC
    ");
    $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $reports = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PSAS Admin Dashboard - Meal Stub System</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f0f2f5;
            margin: 0;
            padding: 20px;
        }
        header {
            background-color: #003366; /* Royal Blue */
            color: #FFCC00; /* Yellow */
            padding: 15px 20px;
            border-radius: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .logout-btn {
            background-color: #dc3545;
            color: white;
            padding: 8px 12px;
            text-decoration: none;
            border-radius: 4px;
            font-weight: bold;
        }
        main {
            background: white;
            padding: 20px;
            margin-top: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .table-container {
            width: 100%;
            overflow-x: auto;
            margin-top: 15px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
            font-size: 14px;
        }
        th, td {
            padding: 12px;
            border-bottom: 1px solid #e2e8f0;
        }
        th {
            background-color: #f8fafc;
            color: #475569;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 12px;
        }
        .badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .badge-pending { background-color: #fef3c7; color: #d97706; }
        .badge-approved { background-color: #def7ec; color: #03543f; }
        .badge-rejected { background-color: #fee2e2; color: #991b1b; }
        .action-btn {
            background-color: #003366;
            color: white;
            padding: 6px 10px;
            text-decoration: none;
            border-radius: 4px;
            font-size: 12px;
        }
        .action-btn:hover {
            background-color: #002244;
        }
    </style>
</head>
<body>

    <header>
        <h1>PSAS Admin Portal</h1>
        <div>
            <span>Welcome, <strong><?= htmlspecialchars(isset($_SESSION['full_name']) ? $_SESSION['full_name'] : 'Admin'); ?></strong>!</span> | 
            <a href="logout.php" class="logout-btn">Logout</a>
        </div>
    </header>

    <main>
        <section>
            <h2>Admin Management & Stub Issuance</h2>
            <p>This portal allows PSAS Admins to issue stub batches, verify attendance logs, and manage distributions.</p>
        </section>

        <hr style="border: 0; border-top: 1px solid #e2e8f0; margin: 20px 0;">

        <section>
            <h3>Submitted Class Attendance Reports</h3>
            
            <?php if (empty($reports)): ?>
                <p style="color: #64748B; font-style: italic;">No attendance reports submitted yet.</p>
            <?php else: ?>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Secretary</th>
                                <th>Course / Section</th>
                                <th>Present</th>
                                <th>Absent</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reports as $row): ?>
                                <tr>
                                    <td><?= htmlspecialchars($row['report_date']) ?></td>
                                    <td><?= htmlspecialchars($row['secretary_name']) ?></td>
                                    <td><?= htmlspecialchars($row['course']) ?> - <?= htmlspecialchars($row['year_level']) ?><?= htmlspecialchars($row['section']) ?></td>
                                    <td><strong style="color: #059669;"><?= $row['present_count'] ?></strong></td>
                                    <td><strong style="color: #dc2626;"><?= $row['absent_count'] ?></strong></td>
                                    <td>
                                        <span class="badge badge-<?= strtolower($row['status']) ?>">
                                            <?= htmlspecialchars($row['status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="process_batch.php?report_id=<?= $row['id'] ?>" class="action-btn">Manage Stubs</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </main>

</body>
</html>