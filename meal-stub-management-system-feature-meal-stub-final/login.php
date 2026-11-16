
<?php
session_start();
require_once __DIR__ . '/db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Please fill in all fields.';
    } else {

        try {
            $stmt = $pdo->prepare("
                SELECT id, full_name, username, password_hash, role
                FROM users
                WHERE username = ?
                LIMIT 1
            ");

            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (
                $user &&
                password_verify($password, $user['password_hash']) &&
                in_array($user['role'], ['secretary', 'psas'], true)
            ) {

                // Prevent session fixation
                session_regenerate_id(true);

                $_SESSION['user_id'] = (int)$user['id'];
                $_SESSION['name'] = $user['full_name'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];

                if ($user['role'] === 'secretary') {
                    header('Location: secretary_dashboard.php');
                } else {
                    header('Location: psas_dashboard.php');
                }

                exit();

            } else {
                $error = 'Invalid username or password.';
            }

        } catch (PDOException $e) {
            error_log('Login error: ' . $e->getMessage());
            $error = 'Unable to process login. Please try again.';
        }
    }
}

// Escape username value for safe display
$enteredUsername = htmlspecialchars(
    $_POST['username'] ?? '',
    ENT_QUOTES,
    'UTF-8'
);
?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Sign In | LVCC Meal Stub System</title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@500;600;700;800&display=swap" rel="stylesheet">

<style>

:root {
    --navy: #00205b;
    --blue: #003087;
    --yellow: #f2a900;
    --white: #ffffff;
    --text: #1d2b3a;
    --muted: #687788;
    --border: #dde3ec;
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: "DM Sans", sans-serif;
    min-height: 100vh;
    color: var(--text);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 25px;

    /* LVCC BUILDING BACKGROUND */

    background:
        linear-gradient(
            135deg,
            rgba(0, 20, 55, .88),
            rgba(0, 32, 91, .75),
            rgba(0, 15, 43, .86)
        ),
        url('assets/image/bg.jpg');

    background-size: cover;
    background-position: center;
    background-repeat: no-repeat;
    background-attachment: fixed;

    position: relative;
}

/* BACKGROUND DECORATION */

body::before {
    content: "";
    position: fixed;
    inset: 0;
    background: radial-gradient(
        circle at 75% 25%,
        rgba(242, 169, 0, .12),
        transparent 35%
    );
    pointer-events: none;
}

/* MAIN CONTAINER */

.login-wrapper {
    width: 100%;
    max-width: 440px;
    position: relative;
    z-index: 1;
    animation: fadeIn .6s ease;
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(15px);
    }

    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* SCHOOL BRAND */

.school-brand {
    text-align: center;
    margin-bottom: 22px;
    color: white;
}

.school-logo {
    width: 78px;
    height: 78px;
    background: white;
    padding: 4px;
    border-radius: 50%;
    object-fit: contain;
    box-shadow: 0 5px 20px rgba(0,0,0,.25);
    margin-bottom: 12px;
}

.school-brand h2 {
    font-family: "Manrope", sans-serif;
    font-size: 17px;
    font-weight: 800;
    letter-spacing: .5px;
}

.school-brand p {
    font-size: 11px;
    letter-spacing: 2px;
    color: #d4dfed;
    margin-top: 4px;
}

/* LOGIN CARD */

.login-card {
    background: rgba(255, 255, 255, .97);
    border: 1px solid rgba(255,255,255,.7);
    border-top: 5px solid var(--yellow);
    border-radius: 14px;
    padding: 35px;
    box-shadow: 0 20px 60px rgba(0,0,0,.25);
}

/* CARD HEADER */

.card-heading {
    text-align: center;
    margin-bottom: 28px;
}

.card-heading h1 {
    font-family: "Manrope", sans-serif;
    color: var(--navy);
    font-size: 24px;
    font-weight: 800;
    margin-bottom: 7px;
}

.card-heading p {
    font-size: 13px;
    color: var(--muted);
    line-height: 1.6;
}

/* FORM */

.form-group {
    margin-bottom: 19px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    color: #35485c;
    font-size: 12px;
    font-weight: 700;
}

.input-wrapper {
    position: relative;
}

.form-group input {
    width: 100%;
    height: 48px;
    padding: 0 14px;
    border: 1px solid var(--border);
    border-radius: 8px;
    background: #f8fafc;
    font-family: inherit;
    font-size: 14px;
    color: var(--text);
    outline: none;
    transition: .2s;
}

.form-group input:focus {
    border-color: var(--blue);
    background: white;
    box-shadow: 0 0 0 4px rgba(0,48,135,.08);
}

.password-input {
    padding-right: 65px !important;
}

.toggle-password {
    position: absolute;
    right: 13px;
    top: 50%;
    transform: translateY(-50%);
    border: none;
    background: none;
    color: var(--blue);
    font-family: inherit;
    font-size: 11px;
    font-weight: 700;
    cursor: pointer;
}

.toggle-password:hover {
    color: var(--yellow);
}

/* SIGN IN BUTTON */

.signin-button {
    width: 100%;
    height: 49px;
    margin-top: 7px;
    border: none;
    border-radius: 8px;
    background: var(--blue);
    color: white;
    font-family: "DM Sans", sans-serif;
    font-size: 14px;
    font-weight: 700;
    cursor: pointer;
    transition: .2s;
    box-shadow: 0 5px 0 #001b4d;
}

.signin-button:hover {
    background: #0741a5;
    transform: translateY(-2px);
    box-shadow: 0 7px 0 #001b4d;
}

.signin-button:active {
    transform: translateY(2px);
    box-shadow: 0 2px 0 #001b4d;
}

/* ERROR MESSAGE */

.error-message {
    background: #fbeaea;
    color: #a53d3d;
    border: 1px solid #f3c4c4;
    padding: 12px 14px;
    border-radius: 7px;
    font-size: 12px;
    margin-bottom: 18px;
    text-align: center;
}

/* BACK TO HOME */

.back-home {
    display: block;
    text-align: center;
    margin-top: 23px;
    color: #64748b;
    text-decoration: none;
    font-size: 12px;
    font-weight: 600;
    transition: .2s;
}

.back-home:hover {
    color: var(--blue);
}

/* FOOTER */

.footer-note {
    color: rgba(255,255,255,.7);
    text-align: center;
    font-size: 10px;
    margin-top: 22px;
    letter-spacing: .4px;
}

/* RESPONSIVE */

@media(max-width: 500px) {

    body {
        padding: 18px;
        background-attachment: scroll;
    }

    .school-logo {
        width: 68px;
        height: 68px;
    }

    .school-brand h2 {
        font-size: 14px;
    }

    .login-card {
        padding: 27px 22px;
    }

    .card-heading h1 {
        font-size: 21px;
    }

}

</style>

</head>

<body>

<div class="login-wrapper">

    <!-- SCHOOL BRAND -->

    <div class="school-brand">

        <img
            src="assets/image/LVlogo.jpg"
            alt="La Verdad Christian College Logo"
            class="school-logo"
        >

        <h2>LA VERDAD CHRISTIAN COLLEGE</h2>

        <p>APALIT, PAMPANGA</p>

    </div>

    <!-- LOGIN CARD -->

    <div class="login-card">

        <div class="card-heading">

            <h1>Welcome Back!</h1>

            <p>
                Sign in to access the
                <strong>LVCC Meal Stub Management System.</strong>
            </p>

        </div>

        <!-- ERROR MESSAGE -->

        <?php if ($error !== ''): ?>

            <div class="error-message">
                <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
            </div>

        <?php endif; ?>

        <!-- LOGIN FORM -->

        <form method="POST" action="login.php">

            <div class="form-group">

                <label for="username">
                    Username
                </label>

                <input
                    type="text"
                    id="username"
                    name="username"
                    placeholder="Enter your username"
                    required
                    autocomplete="username"
                    value="<?= $enteredUsername ?>"
                    autofocus
                >

            </div>

            <div class="form-group">

                <label for="password">
                    Password
                </label>

                <div class="input-wrapper">

                    <input
                        type="password"
                        id="password"
                        name="password"
                        class="password-input"
                        placeholder="Enter your password"
                        required
                        autocomplete="current-password"
                    >

                    <button
                        type="button"
                        class="toggle-password"
                        id="togglePassword"
                    >
                        Show
                    </button>

                </div>

            </div>

            <button type="submit" class="signin-button">
                Sign In →
            </button>

        </form>

        <!-- BACK TO LANDING PAGE -->

        <a href="index.php" class="back-home">
            ← Back to Home
        </a>

    </div>

    <div class="footer-note">
        © <?= date('Y') ?> La Verdad Christian College
    </div>

</div>

<!-- SHOW / HIDE PASSWORD -->

<script>

const passwordInput = document.getElementById('password');
const togglePassword = document.getElementById('togglePassword');

togglePassword.addEventListener('click', function () {

    const isPassword = passwordInput.type === 'password';

    passwordInput.type = isPassword ? 'text' : 'password';

    togglePassword.textContent = isPassword ? 'Hide' : 'Show';

});

</script>

</body>
</html>
