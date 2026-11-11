<?php
// manage_stubs.php - Handles Read (R) and Delete (D) operations
require_once 'db.php';

$message = '';
$error = '';

// Handle Secure Delete Operation (D)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $deleteId = intval($_POST['delete_id']);
    try {
        $stmt = $pdo->prepare("DELETE FROM meal_stubs WHERE id = ?");
        $stmt->execute([$deleteId]);
        header("Location: manage_stubs.php?msg=deleted");
        exit();
    } catch (PDOException $e) {
        $error = "Error deleting record: " . $e->getMessage();
    }
}

if (isset($_GET['msg']) && $_GET['msg'] === 'deleted') {
    $message = "Record successfully deleted!";
}

// Fetch All Records (R)
try {
    $stmt = $pdo->query("SELECT * FROM meal_stubs ORDER BY id DESC");
    $stubs = $stmt->fetchAll();
} catch (PDOException $e) {
    $stubs = [];
    $error = "Error fetching records: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Meal Stubs - CRUD System</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .table-container {
            background: white;
            border-radius: 12px;
            border: 1px solid var(--border-color);
            overflow: hidden;
            margin-top: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
            font-size: 14px;
        }
        th, td {
            padding: 14px 18px;
            border-bottom: 1px solid var(--border-color);
        }
        th {
            background: #F8FAFC;
            color: var(--primary-blue);
            font-weight: 700;
            text-transform: uppercase;
            font-size: 12px;
            letter-spacing: 0.5px;
        }
        .action-btns {
            display: flex;
            gap: 8px;
            align-items: center;
        }
        .btn-sm {
            padding: 6px 12px;
            font-size: 12px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            display: inline-block;
        }
        .btn-edit {
            background: var(--accent-yellow);
            color: #000;
        }
        .btn-delete {
            background: #FEE2E2;
            color: #991B1B;
            border: none;
            cursor: pointer;
        }
        .alert-success {
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 16px;
            font-size: 14px;
            background: #DEF7EC;
            color: #03543F;
        }
        .alert-error {
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 16px;
            font-size: 14px;
            background: #FEE2E2;
            color: #991B1B;
        }
    </style>
</head>
<body>

    <!-- Navbar -->
    <nav class="navbar">
        <div class="brand">
            <span>LVCC <small style="font-weight: normal; color: #64748B;">Meal Stub System</small></span>
        </div>
        <a href="secretary_dashboard.php" class="btn-link" style="margin-top:0;">&lsaquo; Back to Dashboard</a>
    </nav>

    <main class="app-container" style="max-width: 900px;">
        <header class="page-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <div>
                <span class="role-tag">DATABASE MANAGEMENT</span>
                <h1 class="greeting" style="font-size: 22px;">Manage Meal Stubs</h1>
            </div>
            <a href="create_stub.php" class="btn-primary" style="width: auto; padding: 10px 18px; text-decoration: none;">+ Add New Stub</a>
        </header>

        <?php if (!empty($message)): ?>
            <div class="alert-success"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Stub Code</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($stubs) > 0): ?>
                        <?php foreach ($stubs as $stub): ?>
                            <tr>
                                <td><?= (int)$stub['id'] ?></td>
                                <td><strong><?= htmlspecialchars($stub['stub_code']) ?></strong></td>
                                <td>
                                    <span class="status-badge <?= strtolower($stub['status']) ?>">
                                        <?= htmlspecialchars($stub['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-btns">
                                        <!-- Edit Action (Update) -->
                                        <a href="edit_stub.php?id=<?= (int)$stub['id'] ?>" class="btn-sm btn-edit">Edit</a>
                                        
                                        <!-- Delete Action (POST Method for Security) -->
                                        <form action="manage_stubs.php" method="POST" style="margin:0;" onsubmit="return confirm('Are you sure you want to delete stub <?= htmlspecialchars($stub['stub_code']) ?>?');">
                                            <input type="hidden" name="delete_id" value="<?= (int)$stub['id'] ?>">
                                            <button type="submit" class="btn-sm btn-delete">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" style="text-align: center; color: var(--text-muted); padding: 24px;">No meal stub records found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>
</body>
</html>