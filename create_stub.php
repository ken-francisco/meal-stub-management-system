<?php
// create_stub.php - Handles Create (C) operation
require_once 'db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stubCode = trim($_POST['stub_code']);
    $status = trim($_POST['status']);

    if (empty($stubCode)) {
        $error = "Stub code is required.";
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO meal_stubs (stub_code, status) VALUES (?, ?)");
            $stmt->execute([$stubCode, $status]);
            header("Location: manage_stubs.php");
            exit();
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Meal Stub - CRUD System</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .form-group {
            margin-bottom: 16px;
        }
        .form-label {
            display: block;
            font-size: 13px;
            font-weight: 700;
            color: var(--primary-blue);
            margin-bottom: 6px;
            text-transform: uppercase;
        }
        .form-control {
            width: 100%;
            padding: 12px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 14px;
            outline: none;
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

    <nav class="navbar">
        <div class="brand"><span>LVCC <small style="font-weight: normal; color: #64748B;">Meal Stub System</small></span></div>
        <a href="manage_stubs.php" class="btn-link" style="margin-top:0;">&lsaquo; Back to List</a>
    </nav>

    <main class="app-container" style="max-width: 500px;">
        <header class="page-header" style="margin-bottom: 20px;">
            <span class="role-tag">CREATE RECORD</span>
            <h1 class="greeting" style="font-size: 22px;">Add New Meal Stub</h1>
        </header>

        <?php if (!empty($error)): ?>
            <div class="alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="card">
            <form action="create_stub.php" method="POST">
                <div class="form-group">
                    <label class="form-label">Stub Code</label>
                    <input type="text" name="stub_code" class="form-control" placeholder="e.g., STUB-BSIS-2A-010" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-control">
                        <option value="Unclaimed">Unclaimed</option>
                        <option value="Distributed">Distributed</option>
                    </select>
                </div>

                <button type="submit" class="btn-primary" style="margin-top: 10px;">Save Record</button>
            </form>
        </div>
    </main>
</body>
</html>