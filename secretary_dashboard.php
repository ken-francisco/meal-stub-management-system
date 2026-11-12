<?php
// secretary_dashboard.php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'secretary') {
    header("Location: login.php");
    exit();
}

$secName = (string)($_SESSION['name'] ?? 'Class Secretary');
$initials = '';
foreach (preg_split('/\s+/', trim($secName)) as $part) {
    if ($part !== '') {
        $initials .= strtoupper($part[0]);
    }
}
$initials = substr($initials ?: 'CS', 0, 2);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Secretary Portal | LVCC Meal Stub System</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --navy: #00205b;
            --blue: #003087;
            --yellow: #f2a900;
            --soft-yellow: #ffe264;
            --bg: #f4f6f9;
            --text: #1d2b3a;
            --muted: #687788;
            --border: #dde3ec;
            --green: #17835b;
        }
        * { box-sizing: border-box; }
        html { background: var(--bg); }
        body { margin: 0; color: var(--text); font-family: "DM Sans", sans-serif; -webkit-font-smoothing: antialiased; }
        h1, h2, .brand { font-family: "Manrope", sans-serif; margin: 0; }

        .app-shell { min-height: 100vh; background: var(--bg); }
        .sidebar { position: fixed; inset: 0 auto 0 0; width: 264px; background: var(--navy); color: white; display: flex; flex-direction: column; z-index: 30; box-shadow: 8px 0 25px #072d5020; }
        .brand { height: 90px; padding: 0 25px; display: flex; align-items: center; gap: 12px; border-bottom: 1px solid #ffffff17; }
        .brand-mark { width: 43px; height: 48px; display: grid; place-items: center; flex: none; }
        .brand-mark img { width: 43px; height: 43px; display: block; border-radius: 50%; object-fit: cover; background: white; box-shadow: 0 0 0 2px #ffffff, 0 3px 9px #00133180; }
        .brand-fallback { width: 43px; height: 43px; border-radius: 50%; background: var(--yellow); color: var(--navy); font: 800 14px "Manrope", sans-serif; display: grid; place-items: center; box-shadow: 0 0 0 2px #ffffff; }
        .brand b { font-size: 19px; letter-spacing: .06em; display: block; }
        .brand small { color: #b8cadd; font: 400 11px "DM Sans"; display: block; margin-top: 2px; }

        .role-switch { padding: 21px 17px 13px; }
        .role-switch > span, .nav-label { display: block; color: #88a4bd; text-transform: uppercase; font-size: 9px; letter-spacing: .14em; font-weight: 700; margin: 0 8px 9px; }
        .role-box { width: 100%; padding: 10px; border: 1px solid #ffffff1a; border-radius: 8px; display: flex; color: white; text-align: left; align-items: center; background: #ffffff0b; gap: 9px; }
        .role-box b { display: block; font-size: 12px; }
        .role-box small { display: block; color: #99b2c8; font-size: 9px; margin-top: 2px; }

        nav { padding: 8px 12px; }
        nav a { color: #bfd0df; text-decoration: none; width: 100%; height: 45px; border-radius: 6px; display: flex; align-items: center; gap: 12px; padding: 0 13px; font-weight: 500; font-size: 13px; position: relative; margin-bottom: 4px; }
        nav a.active { background: var(--blue); color: white; box-shadow: inset 0 -3px 0 #001a49; }
        nav a.active::before { content: ""; position: absolute; left: -12px; height: 27px; width: 3px; border-radius: 0 3px 3px 0; background: var(--yellow); }

        .sidebar-bottom { padding: 13px 14px 18px; margin-top: auto; }
        .profile-card { display: flex; align-items: center; gap: 9px; padding: 12px 10px; border-top: 1px solid #ffffff19; border-bottom: 1px solid #ffffff19; }
        .avatar { width: 38px; height: 38px; flex: none; border-radius: 50%; display: grid; place-items: center; color: var(--navy); background: #f4d978; font-size: 11px; font-weight: 800; }
        .avatar.small { width: 32px; height: 32px; font-size: 9px; }
        .profile-card > div:nth-child(2) { min-width: 0; flex: 1; }
        .profile-card b { display: block; font-size: 11px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .profile-card small { display: block; color: #9db4c8; font-size: 9px; white-space: nowrap; margin-top: 2px; }
        .logout-icon-btn { width: 32px; height: 30px; border: 1px solid #dc4949; border-radius: 8px; background: #c93737; color: white; display: grid; place-items: center; text-decoration: none; box-shadow: 0 3px 0 #8f2424, 0 5px 10px #00122c55; transition: transform .16s ease, box-shadow .16s ease, background .16s ease; }
        .logout-icon-btn:hover { background: #dc4141; transform: translateY(-2px); box-shadow: 0 5px 0 #8f2424, 0 8px 13px #00122c60; }
        .logout-icon-btn:active { transform: translateY(2px); box-shadow: 0 1px 0 #8f2424, 0 2px 5px #00122c45; }
        .sidebar-bottom > p { text-align: center; color: #6686a1; font-size: 8px; margin-top: 15px; letter-spacing: .08em; text-transform: uppercase; }

        .main-shell { margin-left: 264px; min-height: 100vh; }
        .topbar { height: 68px; background: #fff; border-bottom: 1px solid var(--border); display: flex; justify-content: flex-end; align-items: center; padding: 0 37px; position: sticky; top: 0; z-index: 20; }
        .header-profile { border-left: 1px solid var(--border); padding-left: 20px; gap: 9px; display: flex; align-items: center; }
        .header-profile b { display: block; font-size: 11px; }
        .header-profile small { display: block; font-size: 9px; color: var(--muted); margin-top: 1px; }

        main { padding: 34px 38px 50px; max-width: 1100px; margin: 0 auto; }
        .page-header { margin-bottom: 24px; }
        .eyebrow { color: var(--blue); font-size: 9px; font-weight: 800; letter-spacing: .14em; text-transform: uppercase; display: block; margin-bottom: 7px; }
        .page-header h1 { font-size: 27px; letter-spacing: -.025em; color: #14283b; }
        .page-header p { color: var(--muted); font-size: 12px; margin-top: 6px; }

        .panel { background: white; border: 1px solid var(--border); border-radius: 9px; box-shadow: 0 3px 12px #193a5c08; padding: 24px; }
        .panel h2 { font-size: 16px; color: #14283b; margin-bottom: 6px; }
        .panel p { font-size: 12px; color: var(--muted); margin-bottom: 18px; line-height: 1.5; }
        .course-tags { display: flex; gap: 8px; flex-wrap: wrap; }
        .course-tag { display: inline-flex; align-items: center; padding: 5px 11px; border-radius: 999px; font-size: 10px; font-weight: 800; line-height: 1; }
        .course-bsa   { background: #ffe264; color: #4a3800; }
        .course-bsis  { background: #fee2e2; color: #b91c1c; }
        .course-bab   { background: #dbeafe; color: #003087; }
        .course-bsais { background: #f2a900; color: #2b1d00; }
        .course-act   { background: #ffedd5; color: #c2410c; }

        @media (max-width: 780px) {
            .sidebar { position: static; width: 100%; }
            .main-shell { margin-left: 0; }
            main { padding: 24px 18px; }
        }
    </style>
</head>
<body>
<div class="app-shell">
    <aside class="sidebar">
        <div class="brand">
            <div class="brand-mark">
                <img src="assets/lvcc-logo.png" alt="LVCC Logo" onerror="this.style.display='none'; this.nextElementSibling.style.display='grid';">
                <div class="brand-fallback" style="display:none;">LV</div>
            </div>
            <div>
                <b>LVCC</b>
                <small>Meal Stub System</small>
            </div>
        </div>

        <div class="role-switch">
            <span>Viewing As</span>
            <div class="role-box">
                <div class="avatar small"><?= htmlspecialchars($initials) ?></div>
                <div>
                    <b>Class Secretary</b>
                    <small>Daily Attendance</small>
                </div>
            </div>
        </div>

        <span class="nav-label">Workspace</span>
        <nav>
            <a href="secretary_dashboard.php" class="active">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
                <span>Section Overview</span>
            </a>
        </nav>

        <div class="sidebar-bottom">
            <div class="profile-card">
                <div class="avatar"><?= htmlspecialchars($initials) ?></div>
                <div>
                    <b><?= htmlspecialchars($secName) ?></b>
                    <small>Class Secretary</small>
                </div>
                <a href="logout.php" class="logout-icon-btn" title="Logout" aria-label="Logout">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 17l5-5-5-5M15 12H3"/><path d="M14 3h5a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-5"/></svg>
                </a>
            </div>
            <p>LVCC · Meal Assistance Program</p>
        </div>
    </aside>

    <div class="main-shell">
        <header class="topbar">
            <div class="header-profile">
                <div class="avatar small"><?= htmlspecialchars($initials) ?></div>
                <div>
                    <b><?= htmlspecialchars($secName) ?></b>
                    <small>Class Secretary</small>
                </div>
            </div>
        </header>

        <main>
            <div class="page-header">
                <span class="eyebrow">Secretary Portal</span>
                <h1>Good morning, <?= htmlspecialchars($secName) ?></h1>
                <p>Submit daily class attendance to request meal stubs from PSAS.</p>
            </div>

            <section class="panel">
                <h2>Attendance &amp; Stub Request Dashboard</h2>
                <p>This is where you will build (kyle) the UI for daily attendance encoding and stub requests.</p>
                <div class="course-tags">
                    <span class="course-tag course-bsa">BSA</span>
                    <span class="course-tag course-bsis">BSIS</span>
                    <span class="course-tag course-bab">BAB</span>
                    <span class="course-tag course-bsais">BSAIS</span>
                    <span class="course-tag course-act">ACT</span>
                </div>
            </section>
        </main>
    </div>
</div>
</body>
</html>
