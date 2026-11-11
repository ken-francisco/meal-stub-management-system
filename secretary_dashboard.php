<?php
// secretary_dashboard.php
require_once __DIR__ . '/db.php';

// Kunin ang latest total enrollees at stats mula sa SQLite DB
$totalEnrollees = 36; // Default demo value
$todayPresent = null; // Set to null if not submitted yet

// Check if attendance report exists for today
$today = date('Y-m-d');
$stmt = $pdo->prepare("SELECT present_count, status FROM attendance_reports WHERE report_date = ? LIMIT 1");
$stmt->execute([$today]);
$report = $stmt->fetch(PDO::FETCH_ASSOC);

if ($report) {
    $todayPresent = $report['present_count'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Secretary Dashboard - LVCC Meal Stub System</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

    <!-- Header Navigation Bar -->
    <nav class="navbar">
        <div class="brand">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path></svg>
            <span>LVCC <small style="font-weight: normal; color: #64748B;">Meal Stub System</small></span>
        </div>
        <div class="avatar-chip">MS</div>
    </nav>

    <main class="app-container">
        
        <!-- User Welcome Section -->
        <header class="page-header">
            <span class="role-tag">BSIS · SECTION 3A</span>
            <h1 class="greeting">Good morning, Maria</h1>
            <p class="subtext">Here's your section's meal program status for today.</p>
        </header>

        <!-- Main Action CTA -->
        <div style="margin-bottom: 20px;">
            <a href="attendance_form.php" class="btn-primary">
                <span>+</span> Record attendance
            </a>
        </div>

        <!-- Alert Notification Card -->
        <?php if ($todayPresent === null): ?>
        <div class="card alert-card-warning">
            <div style="display: flex; gap: 12px; align-items: flex-start;">
                <span style="font-size: 20px;">⏱️</span>
                <div>
                    <strong style="color: #92400E; font-size: 15px;">Today's attendance is awaiting submission</strong>
                    <p style="font-size: 13px; color: #B45309; margin-top: 4px;">Submit your class attendance before 9:30 AM to receive meal stubs on time.</p>
                    <a href="attendance_form.php" class="btn-link">Submit now &rsaquo;</a>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Stat Card 1: Total Enrollees -->
        <div class="card metric-card">
            <div class="metric-icon">👥</div>
            <div>
                <span class="subtext">Total class enrollees</span>
                <div class="metric-value"><?= $totalEnrollees ?></div>
                <span style="font-size: 12px; color: #94A3B8;">BSIS 3A · AY 2025–26</span>
            </div>
        </div>

        <!-- Stat Card 2: Today's Present Count -->
        <div class="card metric-card">
            <div class="metric-icon" style="background: #FEF3C7; color: #D97706;">📋</div>
            <div>
                <span class="subtext">Today's present count</span>
                <div class="metric-value">
                    <?= $todayPresent !== null ? htmlspecialchars($todayPresent) : '—' ?>
                </div>
                <span style="font-size: 12px; color: #94A3B8;">
                    <?= $todayPresent !== null ? 'Submitted for verification' : 'Not submitted yet' ?>
                </span>
            </div>
        </div>

        <!-- Quick Access to Distribution Module -->
        <div class="card" style="margin-top: 24px;">
            <h3 style="font-size: 16px; color: var(--primary-blue); margin-bottom: 6px;">Stub Distribution</h3>
            <p class="subtext" style="margin-bottom: 14px;">Monitor and toggle student meal stub distribution status.</p>
            <a href="distribution.php" class="btn-primary" style="background: #3B82F6;">View Distribution List</a>
        </div>

    </main>

</body>
</html>