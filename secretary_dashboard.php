<?php
// secretary_dashboard.php
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Secretary Dashboard - Meal Stub System</title>
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
    </style>
</head>
<body>

    <header>
        <h1>Secretary Portal</h1>
        <div>
            <span>Welcome, <strong><?= htmlspecialchars($_SESSION['name']); ?></strong>!</span> | 
            <a href="logout.php" class="logout-btn">Logout</a>
        </div>
    </header>

    <main>
        <section>
            <h2>Attendance & Stub Request Dashboard</h2>
            <p>Dito mo ilalagay Kyle ang UI para sa pag-encode ng daily attendance at pag-request ng meal stubs.</p>
        </section>
    </main>

</body>
</html>