<?php
// edit_stub.php - Handles Update (U) operation
require_once 'db.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$error = '';

// Retrieve record to edit
try {
    $stmt = $pdo->prepare("SELECT * FROM meal_stubs WHERE id = ?");
    $stmt->execute([$id]);
    $stub = $stmt->fetch();

    if (!$stub) {
        die("Record not found.");
    }
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stubCode = trim($_POST['stub_code']);
    $status = trim($_POST['status']);

    if (empty($stubCode)) {
        $error = "Stub code is required.";
    } else {
        try {
            $updateStmt = $pdo->prepare("UPDATE meal_stubs SET stub_code = ?, status = ? WHERE id = ?");
            $updateStmt->execute([$stubCode, $status, $id]);
            header("Location: manage_stubs.php");
            exit();
        } catch (PDOException $e) {
            $error = "Database update error: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Meal Stub - CRUD System</title>
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
            <span class="role-tag">UPDATE RECORD</span>
            <h1 class="greeting" style="font-size: 22px;">Edit Stub ID #<?= (int)$stub['id'] ?></h1>
        </header>

        <?php if (!empty($error)): ?>
            <div class="alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="card">
            <form action="edit_stub.php?id=<?= (int)$stub['id'] ?>" method="POST">
                <div class="form-group">
                    <label class="form-label">Stub Code</label>
                    <input type="text" name="stub_code" class="form-control" value="<?= htmlspecialchars($stub['stub_code']) ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-control">
                        <option value="Unclaimed" <?= $stub['status'] === 'Unclaimed' ? 'selected' : '' ?>>Unclaimed</option>
                        <option value="Distributed" <?= $stub['status'] === 'Distributed' ? 'selected' : '' ?>>Distributed</option>
                    </select>
                </div>

                <button type="submit" class="btn-primary" style="margin-top: 10px;">Update Record</button>
            </form>
        </div>
    </main>
</body>
</html>