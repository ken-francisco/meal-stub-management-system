<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'secretary') {
    header("Location: login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Distribution Tracker | LVCC Meal Stub System</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@600;700;800&display=swap" rel="stylesheet">
    <style>
        :root { --navy: #00205b; --blue: #003087; --yellow: #f2a900; --bg: #f4f6f9; --text: #1d2b3a; --muted: #687788; --border: #dde3ec; --green: #17835b; }
        body { margin: 0; background: var(--bg); color: var(--text); font-family: "DM Sans", sans-serif; display: flex; }
        .sidebar { width: 264px; background: var(--navy); color: white; position: fixed; height: 100vh; padding: 20px; box-sizing: border-box; }
        .sidebar a { display: block; color: #bfd0df; text-decoration: none; padding: 12px 15px; border-radius: 6px; font-weight: 500; font-size: 13px; margin-bottom: 5px; }
        .sidebar a.active { background: var(--blue); color: white; }
        .main-content { margin-left: 264px; padding: 40px; width: calc(100% - 264px); box-sizing: border-box; }
        .panel { background: white; border: 1px solid var(--border); border-radius: 9px; padding: 24px; box-shadow: 0 3px 12px rgba(0,0,0,0.03); }
        .student-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px; margin-top: 20px; }
        .student-card { border: 1px solid var(--border); padding: 15px; border-radius: 8px; display: flex; justify-content: space-between; align-items: center; }
        .avatar { width: 36px; height: 36px; background: #e2e8f0; border-radius: 50%; display: grid; place-items: center; font-weight: bold; font-size: 12px; color: var(--navy); }
    </style>
</head>
<body>
    <aside class="sidebar">
        <h2 style="font-family: 'Manrope'; font-size: 18px; margin-bottom: 30px;">LVCC Meal Stubs</h2>
        <a href="secretary_dashboard.php">Section Overview</a>
        <a href="daily_attendance.php">Daily Attendance</a>
        <a href="distribution_tracker.php" class="active">Distribution Tracker</a>
    </aside>

    <div class="main-content">
        <span style="color: var(--blue); font-size: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: .1em;">Meal Stubs</span>
        <h1 style="font-family: 'Manrope'; font-size: 28px; margin: 5px 0 30px;">Distribution tracker</h1>

        <div class="panel" style="background: var(--navy); color: white; margin-bottom: 25px;">
            <p style="text-transform: uppercase; font-size: 10px; color: #b8cadd; margin: 0 0 5px;">Distribution progress</p>
            <h2 style="font-family: 'Manrope'; font-size: 24px; margin: 0 0 15px;">9 of 12 distributed</h2>
            <div style="background: #ffffff22; height: 6px; border-radius: 3px; overflow: hidden;">
                <div style="background: var(--yellow); width: 75%; height: 100%;"></div>
            </div>
        </div>

        <div class="panel">
            <input type="text" placeholder="Search student name..." style="width: 100%; padding: 12px; border: 1px solid var(--border); border-radius: 6px; font-size: 13px; box-sizing: border-box;">
            
            <div class="student-grid">
                <div class="student-card">
                    <div style="display: flex; gap: 12px; align-items: center;">
                        <div class="avatar">AJ</div>
                        <div><b>Abad, Janelle M.</b><br><small style="color:var(--muted)">Student ID · 2025-1031</small></div>
                    </div>
                    <span style="color: var(--green); font-size: 12px; font-weight: bold;">● Distributed</span>
                </div>
                <div class="student-card">
                    <div style="display: flex; gap: 12px; align-items: center;">
                        <div class="avatar">AK</div>
                        <div><b>Aguilar, Kent R.</b><br><small style="color:var(--muted)">Student ID · 2025-1032</small></div>
                    </div>
                    <span style="color: var(--green); font-size: 12px; font-weight: bold;">● Distributed</span>
                </div>
                <div class="student-card">
                    <div style="display: flex; gap: 12px; align-items: center;">
                        <div class="avatar">DL</div>
                        <div><b>De Leon, Rafael J.</b><br><small style="color:var(--muted)">Student ID · 2025-1036</small></div>
                    </div>
                    <span style="color: var(--muted); font-size: 12px; font-weight: bold;">○ Unclaimed</span>
                </div>
                <div class="student-card">
                    <div style="display: flex; gap: 12px; align-items: center;">
                        <div class="avatar">HP</div>
                        <div><b>Hernandez, Paulo D.</b><br><small style="color:var(--muted)">Student ID · 2025-1040</small></div>
                    </div>
                    <span style="color: var(--muted); font-size: 12px; font-weight: bold;">○ Unclaimed</span>
                </div>
            </div>
        </div>
    </div>
</body>
</html>