
<?php
session_start();
require_once __DIR__ . '/db.php';

// PSAS-only access
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'psas') {
    header('Location: login.php');
    exit;
}

function e($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

$message = '';
$error = '';

// CSRF protection
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/*
|--------------------------------------------------------------------------
| SAFE DATABASE CHECK
|--------------------------------------------------------------------------
| Adds section_id only if it does not exist.
| Existing user accounts and records are preserved.
*/
try {
    $columns = $pdo->query("PRAGMA table_info(users)")->fetchAll(PDO::FETCH_ASSOC);

    $hasSectionId = false;

    foreach ($columns as $column) {
        if ($column['name'] === 'section_id') {
            $hasSectionId = true;
            break;
        }
    }

    if (!$hasSectionId) {
        $pdo->exec("
            ALTER TABLE users
            ADD COLUMN section_id INTEGER REFERENCES sections(id) ON DELETE SET NULL
        ");
    }
} catch (PDOException $ex) {
    error_log($ex->getMessage());
    $error = 'Unable to check the user database structure.';
}

// Load available sections
$sections = [];

try {
    $sections = $pdo->query("
        SELECT
            s.id,
            s.section_name,
            c.course_code,
            y.year_level
        FROM sections s
        LEFT JOIN courses c ON c.id = s.course_id
        LEFT JOIN year_levels y ON y.id = s.year_id
        ORDER BY c.course_code, y.year_level
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $ex) {
    error_log($ex->getMessage());
    $error = 'Unable to load academic sections.';
}

// Handle account creation and updating
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (
            !isset($_POST['csrf_token']) ||
            !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
        ) {
            throw new Exception('Invalid request. Please refresh the page.');
        }

        $action = $_POST['action'] ?? '';
        $fullName = trim($_POST['full_name'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $role = $_POST['role'] ?? '';
        $sectionId = $_POST['section_id'] ?? '';

        if ($fullName === '' || $username === '') {
            throw new Exception('Please complete all required fields.');
        }

        if (strlen($fullName) > 150 || strlen($username) > 50) {
            throw new Exception('The name or username is too long.');
        }

        if (!in_array($role, ['psas', 'secretary'], true)) {
            throw new Exception('Invalid account role.');
        }

        // Validate section assignment
        if ($role === 'secretary') {
            if ($sectionId === '' || !ctype_digit((string)$sectionId)) {
                throw new Exception('Please select a section for the secretary.');
            }

            $checkSection = $pdo->prepare("
                SELECT id FROM sections WHERE id = ?
            ");
            $checkSection->execute([$sectionId]);

            if (!$checkSection->fetch()) {
                throw new Exception('Selected section does not exist.');
            }
        } else {
            $sectionId = null;
        }

        // CREATE ACCOUNT
        if ($action === 'create') {

            if (strlen($password) < 6) {
                throw new Exception('Password must contain at least 6 characters.');
            }

            // Check duplicate username
            $checkUsername = $pdo->prepare("
                SELECT id FROM users WHERE username = ?
            ");
            $checkUsername->execute([$username]);

            if ($checkUsername->fetch()) {
                throw new Exception('Username is already taken.');
            }

            $passwordHash = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $pdo->prepare("
                INSERT INTO users
                    (full_name, username, password_hash, role, section_id)
                VALUES
                    (?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $fullName,
                $username,
                $passwordHash,
                $role,
                $sectionId
            ]);

            $message = 'Account created successfully!';

        // UPDATE ACCOUNT
        } elseif ($action === 'update') {

            $userId = filter_var(
                $_POST['user_id'] ?? null,
                FILTER_VALIDATE_INT
            );

            if (!$userId || $userId <= 0) {
                throw new Exception('Invalid account ID.');
            }

            // Prevent accidental removal of own PSAS access
            if (
                $userId == $_SESSION['user_id'] &&
                $role !== 'psas'
            ) {
                throw new Exception('You cannot change your own PSAS role.');
            }

            // Check duplicate username
            $checkUsername = $pdo->prepare("
                SELECT id
                FROM users
                WHERE username = ? AND id != ?
            ");
            $checkUsername->execute([$username, $userId]);

            if ($checkUsername->fetch()) {
                throw new Exception('Username is already taken.');
            }

            if ($password !== '') {

                if (strlen($password) < 6) {
                    throw new Exception('Password must contain at least 6 characters.');
                }

                $passwordHash = password_hash($password, PASSWORD_DEFAULT);

                $stmt = $pdo->prepare("
                    UPDATE users
                    SET
                        full_name = ?,
                        username = ?,
                        password_hash = ?,
                        role = ?,
                        section_id = ?
                    WHERE id = ?
                ");

                $stmt->execute([
                    $fullName,
                    $username,
                    $passwordHash,
                    $role,
                    $sectionId,
                    $userId
                ]);

            } else {

                $stmt = $pdo->prepare("
                    UPDATE users
                    SET
                        full_name = ?,
                        username = ?,
                        role = ?,
                        section_id = ?
                    WHERE id = ?
                ");

                $stmt->execute([
                    $fullName,
                    $username,
                    $role,
                    $sectionId,
                    $userId
                ]);
            }

            $message = 'Account updated successfully!';

        } else {
            throw new Exception('Invalid action.');
        }

    } catch (PDOException $ex) {

        error_log($ex->getMessage());

        if (str_contains($ex->getMessage(), 'UNIQUE')) {
            $error = 'Username is already taken.';
        } else {
            $error = 'A database error occurred. Please try again.';
        }

    } catch (Throwable $ex) {
        $error = $ex->getMessage();
    }
}

// Load account for editing
$editUser = null;

if (isset($_GET['edit']) && ctype_digit((string)$_GET['edit'])) {

    $stmt = $pdo->prepare("
        SELECT id, full_name, username, role, section_id
        FROM users
        WHERE id = ?
    ");

    $stmt->execute([$_GET['edit']]);
    $editUser = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$editUser) {
        $error = 'Account not found.';
    }
}

// Load existing accounts
$users = $pdo->query("
    SELECT
        u.id,
        u.full_name,
        u.username,
        u.role,
        u.section_id,
        s.section_name,
        c.course_code,
        y.year_level
    FROM users u
    LEFT JOIN sections s ON s.id = u.section_id
    LEFT JOIN courses c ON c.id = s.course_id
    LEFT JOIN year_levels y ON y.id = s.year_id
    WHERE u.role IN ('psas', 'secretary')
    ORDER BY
        CASE WHEN u.role = 'psas' THEN 0 ELSE 1 END,
        u.full_name
")->fetchAll(PDO::FETCH_ASSOC);

$isEditing = $editUser !== null;

$formUser = $editUser ?: [
    'id' => '',
    'full_name' => '',
    'username' => '',
    'role' => 'secretary',
    'section_id' => ''
];

$totalUsers = count($users);
$totalPSAS = 0;
$totalSecretaries = 0;

foreach ($users as $user) {
    if ($user['role'] === 'psas') {
        $totalPSAS++;
    } elseif ($user['role'] === 'secretary') {
        $totalSecretaries++;
    }
}

$displayName = $_SESSION['full_name'] ?? $_SESSION['name'] ?? 'PSAS Administrator';
$initials = strtoupper(substr($displayName, 0, 1));
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>User Management | LVCC Meal Stub System</title>

<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<style>
:root {
    --navy: #00205b;
    --blue: #003087;
    --yellow: #f2a900;
    --bg: #f4f6f9;
    --text: #1d2b3a;
    --muted: #687788;
    --border: #dde3ec;
    --green: #17835b;
    --red: #c84646;
}

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    min-height: 100vh;
    font-family: 'DM Sans', sans-serif;
    color: var(--text);
    background: var(--bg);
}

/* SIDEBAR */
.sidebar {
    width: 264px;
    height: 100vh;
    position: fixed;
    top: 0;
    left: 0;
    background: var(--navy);
    color: white;
    display: flex;
    flex-direction: column;
    padding: 25px 17px;
    z-index: 10;
}

.brand {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 0 8px 27px;
    border-bottom: 1px solid rgba(255,255,255,.15);
}

.brand img {
    width: 48px;
    height: 48px;
    object-fit: contain;
    background: white;
    border-radius: 50%;
    padding: 3px;
}

.brand h2 {
    font-family: 'Manrope', sans-serif;
    font-size: 16px;
    margin: 0;
}

.brand span {
    font-size: 11px;
    color: #c4d1e6;
}

.nav-label {
    margin: 27px 10px 12px;
    color: #aabbd5;
    font-size: 10px;
    font-weight: 700;
    letter-spacing: 1.5px;
}

.nav-links {
    display: flex;
    flex-direction: column;
    gap: 7px;
}

.nav-links a {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 14px;
    border-radius: 9px;
    text-decoration: none;
    color: #d8e1ef;
    font-size: 13px;
    font-weight: 600;
    transition: .25s ease;
}

.nav-links a:hover {
    background: rgba(255,255,255,.12);
    color: white;
    transform: translateX(4px);
}

.nav-links a.active {
    background: var(--blue);
    color: white;
    box-shadow: 0 5px 15px rgba(0,0,0,.15);
}

.nav-icon {
    width: 20px;
    text-align: center;
    font-size: 16px;
}

.sidebar-bottom {
    margin-top: auto;
    border-top: 1px solid rgba(255,255,255,.15);
    padding-top: 18px;
}

.profile {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 5px 7px 15px;
}

.avatar {
    width: 38px;
    height: 38px;
    border-radius: 50%;
    background: var(--yellow);
    color: var(--navy);
    display: grid;
    place-items: center;
    font-weight: 800;
}

.profile strong {
    display: block;
    font-size: 12px;
}

.profile small {
    color: #bdcbe0;
    font-size: 11px;
}

.logout {
    display: block;
    text-decoration: none;
    color: #ffd1d1;
    font-size: 13px;
    padding: 10px;
    border-radius: 8px;
    transition: .2s;
}

.logout:hover {
    background: rgba(255,255,255,.1);
}

/* MAIN CONTENT */
.main {
    margin-left: 264px;
    min-height: 100vh;
    padding: 35px;
    background:
        linear-gradient(rgba(226,235,247,.70), rgba(226,235,247,.78)),
        url('assets/image/bg.jpg') center/cover fixed;
}

.content {
    max-width: 1400px;
    margin: auto;
}

.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
    margin-bottom: 25px;
}

.page-header h1 {
    font-family: 'Manrope', sans-serif;
    font-size: 27px;
    margin: 0;
    color: var(--navy);
}

.subtitle {
    margin-top: 6px;
    color: var(--muted);
    font-size: 13px;
}

.header-tag {
    background: rgba(255,255,255,.85);
    border: 1px solid var(--border);
    padding: 9px 14px;
    border-radius: 9px;
    color: var(--navy);
    font-size: 12px;
    font-weight: 700;
}

/* SUMMARY */
.summary-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 18px;
    margin-bottom: 23px;
}

.summary-card {
    background: rgba(255,255,255,.94);
    border: 1px solid var(--border);
    border-radius: 14px;
    padding: 20px 22px;
    box-shadow: 0 5px 18px rgba(20,45,80,.05);
    transition: .25s ease;
}

.summary-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 12px 25px rgba(20,45,80,.12);
}

.summary-card .label {
    color: var(--muted);
    font-size: 12px;
    font-weight: 600;
}

.summary-card .number {
    margin-top: 8px;
    font-family: 'Manrope', sans-serif;
    font-size: 28px;
    font-weight: 800;
    color: var(--navy);
}

.summary-card .description {
    color: #8490a0;
    font-size: 11px;
    margin-top: 3px;
}

/* PANELS */
.panel {
    background: rgba(255,255,255,.95);
    border: 1px solid var(--border);
    border-radius: 14px;
    padding: 25px;
    margin-bottom: 23px;
    box-shadow: 0 5px 18px rgba(20,45,80,.05);
    transition: .25s ease;
}

.panel:hover {
    box-shadow: 0 10px 25px rgba(20,45,80,.09);
}

.panel-heading {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    margin-bottom: 20px;
}

.panel-heading h2 {
    font-family: 'Manrope', sans-serif;
    font-size: 17px;
    margin: 0;
    color: var(--navy);
}

.panel-heading p {
    color: var(--muted);
    font-size: 12px;
    margin: 5px 0 0;
}

/* FORM */
.form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 18px;
}

.field {
    display: flex;
    flex-direction: column;
    gap: 7px;
}

.field.full {
    grid-column: 1 / -1;
}

label {
    font-size: 13px;
    font-weight: 700;
    color: #344054;
}

input, select {
    width: 100%;
    padding: 12px 13px;
    border: 1px solid #dce2eb;
    border-radius: 8px;
    font: inherit;
    font-size: 13px;
    background: white;
    color: var(--text);
    transition: .2s;
}

input:focus, select:focus {
    outline: 2px solid #b9d3ff;
    border-color: #3978d7;
}

.help-text {
    color: #8791a2;
    font-size: 11px;
    line-height: 1.5;
}

/* BUTTONS */
.actions {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-top: 22px;
    flex-wrap: wrap;
}

.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    border: 0;
    border-radius: 8px;
    padding: 11px 17px;
    background: var(--blue);
    color: white;
    font: inherit;
    font-size: 13px;
    font-weight: 700;
    text-decoration: none;
    cursor: pointer;
    transition: .2s ease;
}

.btn:hover {
    background: #0044a8;
    transform: translateY(-2px);
}

.btn-secondary {
    background: #e9edf4;
    color: #344054;
}

.btn-secondary:hover {
    background: #dce3ed;
}

.btn-small {
    padding: 7px 12px;
    font-size: 12px;
}

/* NOTICES */
.notice {
    padding: 13px 16px;
    border-radius: 9px;
    margin-bottom: 18px;
    font-size: 13px;
    font-weight: 600;
}

.success {
    background: #e9f8ef;
    color: #176b3a;
    border: 1px solid #c7ebd4;
}

.error {
    background: #fff0ef;
    color: #b42318;
    border: 1px solid #f5d0cc;
}

/* TABLE */
.table-wrap {
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 700px;
}

th, td {
    padding: 14px 12px;
    text-align: left;
    border-bottom: 1px solid #edf0f5;
    font-size: 13px;
}

th {
    color: #758096;
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: .05em;
    background: #f8fafd;
}

tbody tr {
    transition: .2s ease;
}

tbody tr:hover {
    background: #f5f8fd;
}

.role {
    display: inline-block;
    padding: 5px 10px;
    border-radius: 20px;
    background: #eaf1ff;
    color: #245fbd;
    font-size: 11px;
    font-weight: 800;
}

.role.secretary {
    background: #e8f7ef;
    color: #167344;
}

.muted {
    color: #8791a2;
}

.account-name {
    font-weight: 700;
    color: #25364c;
}

.empty-state {
    text-align: center;
    padding: 35px 15px;
    color: var(--muted);
}

.empty-state strong {
    display: block;
    color: var(--navy);
    margin-bottom: 5px;
}

/* RESPONSIVE */
@media(max-width: 1000px) {
    .sidebar {
        width: 230px;
    }

    .main {
        margin-left: 230px;
        padding: 25px;
    }
}

@media(max-width: 760px) {
    .sidebar {
        position: relative;
        width: 100%;
        height: auto;
    }

    .nav-links {
        display: grid;
        grid-template-columns: 1fr 1fr;
    }

    .sidebar-bottom {
        margin-top: 20px;
    }

    .main {
        margin-left: 0;
        padding: 20px 15px;
    }

    .summary-grid {
        grid-template-columns: 1fr;
    }

    .page-header {
        align-items: flex-start;
        flex-direction: column;
    }
}

@media(max-width: 520px) {
    .form-grid {
        grid-template-columns: 1fr;
    }

    .field.full {
        grid-column: auto;
    }

    .panel {
        padding: 18px;
    }

    .nav-links {
        grid-template-columns: 1fr;
    }
}
</style>
</head>

<body>

<!-- SIDEBAR -->
<aside class="sidebar">

    <div class="brand">
        <img src="assets/image/LVlogo.jpg" alt="LVCC Logo">
        <div>
            <h2>LVCC Meal Stub</h2>
            <span>Management System</span>
        </div>
    </div>

    <div class="nav-label">MAIN MENU</div>

    <nav class="nav-links">
        <a href="psas_dashboard.php">
            <span class="nav-icon">▦</span>
            Dashboard
        </a>

        <a href="attendance_verification.php">
            <span class="nav-icon">✓</span>
            Attendance Verification
        </a>

        <a href="stub_issuance.php">
            <span class="nav-icon">＋</span>
            Stub Issuance
        </a>

        <a href="stub_monitoring.php">
            <span class="nav-icon">▤</span>
            Stub Monitoring
        </a>

        <a href="reports_audit.php">
            <span class="nav-icon">▥</span>
            Reports & Audit
        </a>

        <a href="user_management.php" class="active">
            <span class="nav-icon">♙</span>
            User Management
        </a>
    </nav>

    <div class="sidebar-bottom">
        <div class="profile">
            <div class="avatar"><?= e($initials) ?></div>
            <div>
                <strong><?= e($displayName) ?></strong>
                <small>PSAS Administrator</small>
            </div>
        </div>

        <a href="logout.php" class="logout">↪ Logout</a>
    </div>

</aside>

<!-- MAIN CONTENT -->
<main class="main">
<div class="content">

    <header class="page-header">
        <div>
            <h1>User Management</h1>
            <div class="subtitle">
                Create, update, and manage PSAS Staff and Class Secretary accounts.
            </div>
        </div>

        <div class="header-tag">PSAS ADMINISTRATION</div>
    </header>

    <?php if ($message): ?>
        <div class="notice success">✓ <?= e($message) ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="notice error">! <?= e($error) ?></div>
    <?php endif; ?>

    <!-- SUMMARY CARDS -->
    <section class="summary-grid">

        <div class="summary-card">
            <div class="label">TOTAL ACCOUNTS</div>
            <div class="number"><?= $totalUsers ?></div>
            <div class="description">All managed system accounts</div>
        </div>

        <div class="summary-card">
            <div class="label">PSAS STAFF</div>
            <div class="number"><?= $totalPSAS ?></div>
            <div class="description">Administrative accounts</div>
        </div>

        <div class="summary-card">
            <div class="label">CLASS SECRETARIES</div>
            <div class="number"><?= $totalSecretaries ?></div>
            <div class="description">Accounts with section assignments</div>
        </div>

    </section>

    <!-- ACCOUNT FORM -->
    <section class="panel">

        <div class="panel-heading">
            <div>
                <h2><?= $isEditing ? 'Edit Account' : 'Create New Account' ?></h2>
                <p>
                    <?= $isEditing
                        ? 'Update the selected account information.'
                        : 'Register a new PSAS Staff or Class Secretary account.' ?>
                </p>
            </div>
        </div>

        <form method="POST">

            <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">

            <input
                type="hidden"
                name="action"
                value="<?= $isEditing ? 'update' : 'create' ?>"
            >

            <?php if ($isEditing): ?>
                <input type="hidden" name="user_id" value="<?= e($formUser['id']) ?>">
            <?php endif; ?>

            <div class="form-grid">

                <div class="field">
                    <label for="full_name">Full Name *</label>
                    <input
                        type="text"
                        id="full_name"
                        name="full_name"
                        maxlength="150"
                        value="<?= e($formUser['full_name']) ?>"
                        placeholder="Enter complete name"
                        required
                    >
                </div>

                <div class="field">
                    <label for="username">Username *</label>
                    <input
                        type="text"
                        id="username"
                        name="username"
                        maxlength="50"
                        value="<?= e($formUser['username']) ?>"
                        placeholder="Enter username"
                        required
                    >
                </div>

                <div class="field">
                    <label for="password">
                        Password <?= $isEditing ? '(Optional)' : '*' ?>
                    </label>

                    <input
                        type="password"
                        id="password"
                        name="password"
                        <?= $isEditing ? '' : 'required' ?>
                        minlength="6"
                        autocomplete="new-password"
                        placeholder="<?= $isEditing ? 'Leave blank to keep current password' : 'Minimum 6 characters' ?>"
                    >

                    <small class="help-text">
                        <?= $isEditing
                            ? 'Leave this field blank if you do not want to change the password.'
                            : 'Use at least 6 characters.' ?>
                    </small>
                </div>

                <div class="field">
                    <label for="role">Account Role *</label>

                    <select id="role" name="role" required>
                        <option
                            value="secretary"
                            <?= $formUser['role'] === 'secretary' ? 'selected' : '' ?>
                        >
                            Class Secretary
                        </option>

                        <option
                            value="psas"
                            <?= $formUser['role'] === 'psas' ? 'selected' : '' ?>
                        >
                            PSAS Staff
                        </option>
                    </select>
                </div>

                <div class="field full" id="sectionField">
                    <label for="section_id">Assigned Section *</label>

                    <select id="section_id" name="section_id">
                        <option value="">Select Course / Year</option>

                        <?php foreach ($sections as $section): ?>
                            <option
                                value="<?= e($section['id']) ?>"
                                <?= (string)$formUser['section_id'] === (string)$section['id'] ? 'selected' : '' ?>
                            >
                                <?= e($section['course_code'] ?? 'No Course') ?>
                                -
                                <?= e($section['year_level'] ?? 'No Year') ?>
                                <?php
                                    $sectionLabel = trim((string)($section['section_name'] ?? ''));
                                    $yearLabel = trim((string)($section['year_level'] ?? ''));
                                    $showSectionLabel = $sectionLabel !== ''
                                        && strtolower($sectionLabel) !== strtolower($yearLabel);
                                ?>
                                <?= $showSectionLabel ? ' (Section ' . e($sectionLabel) . ')' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <small class="help-text">
                        Each Class Secretary must be assigned to a specific academic section.
                    </small>

                    <?php if (count($sections) === 0): ?>
                        <small class="help-text">
                            No academic sections found. Add academic records first.
                        </small>
                    <?php endif; ?>
                </div>

            </div>

            <div class="actions">

                <button type="submit" class="btn">
                    <?= $isEditing ? '✓ Save Changes' : '+ Create Account' ?>
                </button>

                <?php if ($isEditing): ?>
                    <a href="user_management.php" class="btn btn-secondary">
                        Cancel
                    </a>
                <?php endif; ?>

            </div>

        </form>

    </section>

    <!-- EXISTING ACCOUNTS -->
    <section class="panel">

        <div class="panel-heading">
            <div>
                <h2>Existing Accounts</h2>
                <p>View and update registered system users.</p>
            </div>

            <span class="header-tag"><?= $totalUsers ?> ACCOUNTS</span>
        </div>

        <div class="table-wrap">
            <table>

                <thead>
                    <tr>
                        <th>Full Name</th>
                        <th>Username</th>
                        <th>Role</th>
                        <th>Assigned Section</th>
                        <th>Action</th>
                    </tr>
                </thead>

                <tbody>

                <?php if (count($users) > 0): ?>

                    <?php foreach ($users as $user): ?>
                        <tr>

                            <td class="account-name">
                                <?= e($user['full_name']) ?>
                            </td>

                            <td><?= e($user['username']) ?></td>

                            <td>
                                <span class="role <?= $user['role'] === 'secretary' ? 'secretary' : '' ?>">
                                    <?= $user['role'] === 'psas' ? 'PSAS Staff' : 'Class Secretary' ?>
                                </span>
                            </td>

                            <td>
                                <?php if ($user['role'] === 'secretary' && $user['section_id']): ?>

                                    <?= e($user['course_code'] ?? '') ?>
                                    -
                                    <?= e($user['year_level'] ?? '') ?>

                                <?php elseif ($user['role'] === 'secretary'): ?>

                                    <span class="muted">Not assigned</span>

                                <?php else: ?>

                                    <span class="muted">Not applicable</span>

                                <?php endif; ?>
                            </td>

                            <td>
                                <a
                                    href="user_management.php?edit=<?= e($user['id']) ?>"
                                    class="btn btn-small"
                                >
                                    Edit
                                </a>
                            </td>

                        </tr>
                    <?php endforeach; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="5">
                            <div class="empty-state">
                                <strong>No accounts found</strong>
                                Create an account using the form above.
                            </div>
                        </td>
                    </tr>

                <?php endif; ?>

                </tbody>

            </table>
        </div>

    </section>

</div>
</main>

<script>
const roleSelect = document.getElementById('role');
const sectionField = document.getElementById('sectionField');
const sectionSelect = document.getElementById('section_id');

function updateSectionField() {
    const isSecretary = roleSelect.value === 'secretary';

    sectionField.style.display = isSecretary ? 'flex' : 'none';
    sectionSelect.required = isSecretary;
    sectionSelect.disabled = !isSecretary;

    if (!isSecretary) {
        sectionSelect.value = '';
    }
}

roleSelect.addEventListener('change', updateSectionField);
updateSectionField();
</script>

</body>
</html>
