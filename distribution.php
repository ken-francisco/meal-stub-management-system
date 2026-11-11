<?php
require_once 'db.php';

// Kunin ang lahat ng stubs kasama ang student/section details
$sql = "SELECT 
            ms.id,
            ms.stub_code,
            ms.status,
            ar.course,
            ar.year_level,
            ar.section AS section_name
        FROM meal_stubs ms
        LEFT JOIN stub_batches sb ON ms.batch_id = sb.id
        LEFT JOIN attendance_reports ar ON sb.attendance_report_id = ar.id
        ORDER BY ms.id ASC";

$stmt =$pdo->prepare($sql);$stmt->execute();
$stubs =$stmt->fetchAll(PDO::FETCH_ASSOC);

// Kalkulahin ang stats para sa Progress Header
$totalStubs = count($stubs);$distributedCount = 0;
foreach ($stubs as$s) {
    if ($s['status'] === 'Distributed') {$distributedCount++;
    }
}
$remainingCount = $totalStubs -$distributedCount;
$progressPercent =$totalStubs > 0 ? ($distributedCount / $totalStubs) * 100 : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meal Stub Distribution - LVCC System</title>
    <link rel="stylesheet" href="style.css">
    <style>
        /* Karagdagang styling para sa Figma-matching distribution layout */
        .progress-card {
            background: var(--primary-blue);
            color: white;
            border-radius: 14px;
            padding: 24px;
            margin-bottom: 20px;
            box-shadow: 0 4px 12px rgba(10, 37, 64, 0.15);
        }
        .progress-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }
        .progress-bar-bg {
            background: rgba(255, 255, 255, 0.2);
            height: 8px;
            border-radius: 4px;
            overflow: hidden;
            margin-top: 10px;
        }
        .progress-bar-fill {
            background: var(--accent-yellow);
            height: 100%;
            transition: width 0.4s ease;
        }
        .search-filter-container {
            margin-bottom: 20px;
        }
        .search-input {
            width: 100%;
            padding: 12px 16px 12px 40px;
            border: 1px solid var(--border-color);
            border-radius: 10px;
            font-size: 14px;
            outline: none;
            background: white url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="%2364748B" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>') no-repeat 14px center;
            margin-bottom: 12px;
        }
        .filter-pills {
            display: flex;
            gap: 8px;
        }
        .pill {
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            border: 1px solid var(--border-color);
            background: white;
            color: var(--text-muted);
            transition: all 0.2s;
        }
        .pill.active {
            background: var(--primary-blue);
            color: white;
            border-color: var(--primary-blue);
        }
        .stub-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .stub-item-card {
            background: white;
            border: 1px solid var(--border-color);
            border-radius: 10px;
            padding: 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 1px 3px rgba(0,0,0,0.01);
        }
        .stub-info h4 {
            font-size: 15px;
            color: var(--primary-blue);
            margin-bottom: 2px;
        }
        .stub-info p {
            font-size: 12px;
            color: var(--text-muted);
        }
        .status-badge {
            font-size: 12px;
            font-weight: 700;
            padding: 4px 10px;
            border-radius: 12px;
        }
        .status-badge.distributed {
            background: #DEF7EC;
            color: #03543F;
        }
        .status-badge.unclaimed {
            background: #FEF08A;
            color: #854D0E;
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

    <main class="app-container" style="max-width: 800px;">
        
        <!-- Page Title -->
        <header class="page-header" style="margin-bottom: 16px;">
            <span class="role-tag">DISTRIBUTION MODULE</span>
            <h1 class="greeting" style="font-size: 22px;">Meal Stub Distribution Tracker</h1>
        </header>

        <!-- Figma Progress Card -->
        <div class="progress-card">
            <div class="progress-header">
                <div>
                    <span style="font-size: 12px; opacity: 0.8; text-transform: uppercase; letter-spacing: 0.5px;">Distribution progress</span>
                    <div style="font-size: 20px; font-weight: 800; margin-top: 2px;">
                        <?= $distributedCount ?> of <?= $totalStubs ?> distributed
                    </div>
                </div>
                <div style="text-align: right;">
                    <span style="font-size: 24px; font-weight: 800; color: var(--accent-yellow);"><?= $remainingCount ?></span>
                    <div style="font-size: 11px; opacity: 0.8;">Remaining</div>
                </div>
            </div>
            <div class="progress-bar-bg">
                <div class="progress-bar-fill" style="width: <?= $progressPercent ?>%;"></div>
            </div>
        </div>

        <!-- Search & Filter Controls (Logic & Interactivity) -->
        <div class="search-filter-container">
            <input type="text" id="searchInput" class="search-input" placeholder="Search stub code, course, or section...">
            <div class="filter-pills">
                <button class="pill active" onclick="filterStubs('all', this)">All stubs</button>
                <button class="pill" onclick="filterStubs('Unclaimed', this)">Unclaimed</button>
                <button class="pill" onclick="filterStubs('Distributed', this)">Distributed</button>
            </div>
        </div>

        <!-- Dynamic Stub List (Responsive & Interactive Cards) -->
        <div class="stub-list" id="stubListContainer">
            <?php if (count($stubs) > 0): ?>
                <?php foreach ($stubs as$stub): ?>
                    <div class="stub-item-card" data-status="<?= $stub['status'] ?>" data-search="<?= strtolower($stub['stub_code'] . ' ' . $stub['course'] . ' ' .$stub['section_name']) ?>">
                        <div class="stub-info">
                            <h4><?= htmlspecialchars($stub['stub_code']) ?></h4>
                            <p><?= htmlspecialchars($stub['course'] ?? 'N/A') ?> · Year <?= htmlspecialchars($stub['year_level'] ?? 'N/A') ?> · <?= htmlspecialchars($stub['section_name'] ?? 'N/A') ?></p>
                        </div>
                        <span class="status-badge <?= strtolower($stub['status']) === 'distributed' ? 'distributed' : 'unclaimed' ?>"><?= htmlspecialchars($stub['status']) ?></span>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="stub-item-card" style="justify-content: center; color: var(--text-muted);">
                    No stubs found.
                </div>
            <?php endif; ?>
        </div>
    </main>

    <script>
        const searchInput = document.getElementById('searchInput');
        const stubListContainer = document.getElementById('stubListContainer');
        let currentFilter = 'all';

        function filterStubs(status, button) {
            currentFilter = status;
            document.querySelectorAll('.pill').forEach((pill) => pill.classList.remove('active'));
            button.classList.add('active');
            const cards = stubListContainer.querySelectorAll('.stub-item-card');
            cards.forEach((card) => {
                const matches = (status === 'all' || card.dataset.status === status);
                card.style.display = matches ? 'flex' : 'none';
            });
            applySearch();
        }

        function applySearch() {
            const query = searchInput.value.trim().toLowerCase();
            const cards = stubListContainer.querySelectorAll('.stub-item-card');
            cards.forEach((card) => {
                const matchesQuery = !query || card.dataset.search.includes(query);
                const matchesStatus = currentFilter === 'all' || card.dataset.status === currentFilter;
                card.style.display = matchesQuery && matchesStatus ? 'flex' : 'none';
            });
        }

        searchInput.addEventListener('input', applySearch);
    </script>
</body>
</html>
