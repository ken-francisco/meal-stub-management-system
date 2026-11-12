<?php
session_start();
require_once __DIR__ . '/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'secretary') {
    header("Location: login.php");
    exit();
}

$secName = (string)($_SESSION['name'] ?? $_SESSION['full_name'] ?? 'Maria Santos');
$initials = 'MS';

try {
    $stmt = $pdo->prepare("SELECT * FROM attendance_reports WHERE secretary_id = ? ORDER BY id DESC LIMIT 5");
    $stmt->execute([$_SESSION['user_id']]);
    $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $reports = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Class Secretary Dashboard | LVCC Meal Stub System</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@600;700;800&display=swap" rel="stylesheet">
    <style>
        :root { --navy: #00205b; --blue: #003087; --yellow: #f2a900; --bg: #f4f6f9; --text: #1d2b3a; --muted: #687788; --border: #dde3ec; --green: #17835b; --red: #c84646; }
        * { box-sizing: border-box; }
        body { margin: 0; background: var(--bg); color: var(--text); font-family: "DM Sans", sans-serif; display: flex; }
        .sidebar { width: 264px; background: var(--navy); color: white; position: fixed; height: 100vh; padding: 20px; box-sizing: border-box; display: flex; flex-direction: column; }
        .brand { display: flex; align-items: center; gap: 12px; padding-bottom: 20px; border-bottom: 1px solid #ffffff17; }
        .brand b { font-family: "Manrope"; font-size: 18px; display: block; }
        .brand small { color: #b8cadd; font-size: 11px; }
        .nav-label { color: #88a4bd; font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: .14em; margin: 25px 0 10px; }
        nav a { display: block; color: #bfd0df; text-decoration: none; padding: 12px 15px; border-radius: 6px; font-weight: 500; font-size: 13px; margin-bottom: 5px; }
        nav a.active, nav a:hover { background: var(--blue); color: white; }
        .main-content { margin-left: 264px; padding: 40px; width: calc(100% - 264px); box-sizing: border-box; }
        .top-banner { background: #fffdf0; border: 1px solid #fce8a6; padding: 18px 24px; border-radius: 8px; display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
        .kpi-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 30px; }
        .kpi-card { background: white; padding: 24px; border-radius: 9px; border: 1px solid var(--border); box-shadow: 0 3px 12px rgba(0,0,0,0.03); }
        .kpi-card p { font-size: 11px; color: var(--muted); font-weight: 700; text-transform: uppercase; margin: 0 0 8px 0; }
        .kpi-card strong { font-size: 28px; font-family: "Manrope"; color: #14283b; }
        .panel { background: white; border: 1px solid var(--border); border-radius: 9px; padding: 24px; box-shadow: 0 3px 12px rgba(0,0,0,0.03); }
        .badge { padding: 4px 10px; border-radius: 12px; font-size: 10px; font-weight: 700; text-transform: uppercase; }
        .badge-approved { background: #def7ec; color: var(--green); }
        .badge-returned { background: #fee2e2; color: var(--red); }
        .btn-primary { background: var(--blue); color: white; padding: 12px 20px; border-radius: 8px; text-decoration: none; font-weight: 700; font-size: 13px; display: inline-flex; align-items: center; gap: 8px; }
    </style>
</head>
<body>
    <aside class="sidebar">
        <div class="brand">
            <div>
                <b>LVCC</b>
                <small>Meal Stub System</small>
            </div>
        </div>
        <span class="nav-label">Workspace</span>
        <nav>
            <a href="secretary_dashboard.php" class="active">Section Overview</a>
            <a href="daily_attendance.php">Daily Attendance</a>
            <a href="distribution_tracker.php">Distribution Tracker</a>
        </nav>
        <div style="margin-top: auto; border-top: 1px solid #ffffff19; padding-top: 15px;">
            <b style="font-size: 12px; display: block;"><?= htmlspecialchars($secName) ?></b>
            <small style="color: #9db4c8;">Secretary · BSIS 3A</small>
            <a href="logout.php" style="color: #ff6b6b; font-size: 12px; margin-top: 8px; display: block; text-decoration: none;">Logout</a>
        </div>
    </aside>

    <div class="main-content">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 25px;">
            <div>
                <span style="color: var(--blue); font-size: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: .1em;">BSIS · SECTION 3A</span>
                <h1 style="font-size: 28px; font-family: 'Manrope'; margin: 5px 0;">Good morning, Maria</h1>
                <p style="color: var(--muted); font-size: 13px; margin: 0;">Here's your section's meal program status for today.</p>
            </div>
            <a href="daily_attendance.php" class="btn-primary">+ Record attendance</a>
        </div>

        <div class="top-banner">
            <div>
                <b>Today's attendance is awaiting submission</b>
                <p style="font-size: 12px; color: var(--muted); margin: 3px 0 0;">Submit your class attendance before 9:30 AM to receive meal stubs on time.</p>
            </div>
            <a href="daily_attendance.php" style="color: var(--blue); font-weight: bold; font-size: 13px; text-decoration: none;">Submit now &rarr;</a>
        </div>

        <div class="kpi-grid">
            <div class="kpi-card">
                <p>Total class enrollees</p>
                <strong>36</strong>
                <span style="font-size: 11px; color: var(--muted); display: block; margin-top: 5px;">BSIS 3A · AY 2025–26</span>
            </div>
            <div class="kpi-card">
                <p>Today's present count</p>
                <strong>—</strong>
                <span style="font-size: 11px; color: var(--muted); display: block; margin-top: 5px;">Not submitted yet</span>
            </div>
            <div class="kpi-card">
                <p>Stubs on-hand</p>
                <strong>2</strong>
                <span style="font-size: 11px; color: var(--muted); display: block; margin-top: 5px;">34 distributed yesterday</span>
            </div>
        </div>

        <div class="panel">
            <h3 style="font-family: 'Manrope'; font-size: 15px; margin-bottom: 4px;">Recent attendance</h3>
            <p style="font-size: 12px; color: var(--muted); margin-bottom: 20px;">Your last five submissions</p>
            
            <?php if (empty($reports)): ?>
                <p style="color: var(--muted); font-style: italic;">No attendance reports submitted yet.</p>
            <?php else: ?>
                <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
                    <?php foreach ($reports as $r): ?>
                        <tr style="border-bottom: 1px solid var(--border);">
                            <td style="padding: 12px 0;"><b><?= htmlspecialchars($r['report_date']) ?></b><br><small style="color:var(--muted)"><?= (int)$r['present_count'] ?> present · <?= (int)($r['absent_count'] ?? 0) ?> absent</small></td>
                            <td style="text-align: right;"><span class="badge badge-<?= strtolower($r['status'] === 'Approved' ? 'approved' : 'returned') ?>"><?= htmlspecialchars($r['status'] ?? 'Pending') ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>