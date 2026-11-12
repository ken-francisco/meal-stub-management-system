<?php
// login.php
session_start();
require_once 'db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    if (!empty($username) && !empty($password)) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['name'] = $user['name'];
            $_SESSION['role'] = $user['role'];

            if ($user['role'] === 'secretary') {
                header("Location: secretary_dashboard.php");
            } else if ($user['role'] === 'psas') {
                header("Location: psas_dashboard.php");
            } else {
                header("Location: dashboard.php");
            }
            exit();
        } else {
            $error = "Invalid username or password.";
        }
    } else {
        $error = "Please fill in all fields.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In | LVCC Meal Stub System</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --navy: #00205b;
            --blue: #003087;
            --yellow: #f2a900;
            --bg: #f4f6f9;
            --text: #1d2b3a;
            --muted: #687788;
            --border: #dde3ec;
        }
        * { box-sizing: border-box; }
        body {
            font-family: "DM Sans", sans-serif;
            background-color: var(--bg);
            color: var(--text);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            padding: 16px;
        }
        .login-card {
            background: #ffffff;
            border: 1px solid var(--border);
            border-top: 4px solid var(--navy);
            padding: 32px 28px;
            border-radius: 10px;
            box-shadow: 0 8px 25px #072d5014;
            width: 100%;
            max-width: 360px;
        }
        .brand-logo {
            width: 54px;
            height: 54px;
            margin: 0 auto 14px;
            display: grid;
            place-items: center;
        }
        .brand-logo img {
            width: 54px;
            height: 54px;
            border-radius: 50%;
            object-fit: cover;
            box-shadow: 0 3px 10px #00133130;
        }
        .brand-fallback {
            width: 54px;
            height: 54px;
            border-radius: 50%;
            background: var(--yellow);
            color: var(--navy);
            font: 800 18px "Manrope", sans-serif;
            display: grid;
            place-items: center;
        }
        .login-card h2 {
            font-family: "Manrope", sans-serif;
            margin: 0 0 4px;
            color: var(--navy);
            font-size: 20px;
            font-weight: 800;
            text-align: center;
        }
        .subtitle {
            text-align: center;
            color: var(--muted);
            font-size: 12px;
            margin: 0 0 22px;
        }
        .form-group { margin-bottom: 15px; }
        .form-group label {
            display: block;
            margin-bottom: 6px;
            color: #3a4d5f;
            font-size: 11px;
            font-weight: 700;
        }
        .form-group input {
            width: 100%;
            height: 40px;
            padding: 0 12px;
            border: 1px solid #d9e1e8;
            border-radius: 6px;
            font: inherit;
            font-size: 13px;
            outline: none;
        }
        .form-group input:focus {
            border-color: var(--blue);
            box-shadow: 0 0 0 3px #0b5a9d12;
        }
        .button {
            width: 100%;
            height: 41px;
            border-radius: 9px;
            border: 1px solid var(--blue);
            background: var(--blue);
            color: white;
            font: 700 13px "DM Sans", sans-serif;
            cursor: pointer;
            box-shadow: 0 4px 0 #001b4d, 0 7px 14px #00205b22;
            transition: transform .16s ease, box-shadow .16s ease, background .16s ease;
        }
        .button:hover {
            background: #0741a5;
            transform: translateY(-2px);
            box-shadow: 0 6px 0 #001b4d, 0 10px 18px #00205b2d;
        }
        .button:active {
            transform: translateY(2px);
            box-shadow: 0 1px 0 #001b4d, 0 3px 7px #00205b20;
        }
        .error-message {
            background-color: #fbeaea;
            color: #a53d3d;
            border: 1px solid #f3c4c4;
            padding: 9px 12px;
            border-radius: 6px;
            font-size: 12px;
            margin-bottom: 14px;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="brand-logo">
            <img src="assets/lvcc-logo.png" alt="LVCC Logo" onerror="this.style.display='none'; this.nextElementSibling.style.display='grid';">
            <div class="brand-fallback" style="display:none;">LV</div>
        </div>
        <h2>LVCC Meal Stub System</h2>
        <p class="subtitle">Sign in to continue</p>

        <?php if (!empty($error)): ?>
            <div class="error-message"><?= htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" action="login.php">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" required autocomplete="off">
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit" class="button">Sign In</button>
        </form>
    </div>
</body>
</html>
