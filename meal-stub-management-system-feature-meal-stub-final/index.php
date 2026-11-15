
<?php
session_start();

// Redirect logged-in users to their respective dashboards
if (isset($_SESSION['user_id'], $_SESSION['role'])) {
    if ($_SESSION['role'] === 'psas') {
        header('Location: psas_dashboard.php');
        exit;
    }

    if ($_SESSION['role'] === 'secretary') {
        header('Location: secretary_dashboard.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>LVCC | Meal Stub Management System</title>

<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@500;600;700;800&display=swap" rel="stylesheet">

<style>

:root {
    --navy: #00205b;
    --blue: #003087;
    --yellow: #f2a900;
    --white: #ffffff;
    --muted: #d4dfed;
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

html {
    scroll-behavior: smooth;
}

body {
    font-family: 'DM Sans', sans-serif;
    min-height: 100vh;
    color: var(--white);
    background: var(--navy);
}

/* HERO BACKGROUND */

.hero {
    min-height: 100vh;
    position: relative;
    display: flex;
    flex-direction: column;

    background:
        linear-gradient(
            90deg,
            rgba(0, 20, 55, .90) 0%,
            rgba(0, 32, 91, .72) 55%,
            rgba(0, 20, 55, .45) 100%
        ),
        linear-gradient(
            0deg,
            rgba(0, 20, 55, .85),
            transparent 65%
        ),
        url('assets/image/bg.jpg');

    background-size: cover;
    background-position: center;
    background-repeat: no-repeat;
    background-attachment: fixed;
}

/* HEADER */

.header {
    width: 100%;
    padding: 22px 7%;
    display: flex;
    justify-content: flex-start;
    align-items: center;
    border-bottom: 1px solid rgba(255,255,255,.15);
    position: relative;
    z-index: 2;
}

.brand {
    display: flex;
    align-items: center;
    gap: 13px;
}

.brand img {
    width: 62px;
    height: 62px;
    object-fit: contain;
    background: white;
    border-radius: 50%;
    padding: 3px;
}

.brand h2 {
    font-family: 'Manrope', sans-serif;
    font-size: 17px;
    font-weight: 800;
}

.brand p {
    color: #d4dfed;
    font-size: 11px;
    letter-spacing: 1.3px;
    margin-top: 3px;
}

/* HERO CONTENT */

.hero-content {
    width: 100%;
    max-width: 1200px;
    margin: auto;
    padding: 90px 7% 110px;
    position: relative;
    z-index: 1;
}

.eyebrow {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    color: var(--yellow);
    font-size: 11px;
    font-weight: 800;
    letter-spacing: 2.5px;
    text-transform: uppercase;
    margin-bottom: 22px;
}

.eyebrow::before {
    content: '';
    width: 32px;
    height: 2px;
    background: var(--yellow);
}

.hero-content h1 {
    max-width: 720px;
    font-family: 'Manrope', sans-serif;
    font-size: clamp(38px, 6vw, 68px);
    line-height: 1.13;
    font-weight: 800;
    margin-bottom: 22px;
}

.hero-content h1 span {
    color: var(--yellow);
}

.description {
    max-width: 590px;
    color: var(--muted);
    font-size: 16px;
    line-height: 1.8;
    margin-bottom: 34px;
}

/* BUTTONS */

.hero-buttons {
    display: flex;
    flex-wrap: wrap;
    gap: 13px;
}

.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 9px;
    padding: 14px 25px;
    border-radius: 7px;
    text-decoration: none;
    font-size: 13px;
    font-weight: 700;
    font-family: inherit;
    transition: .25s;
    cursor: pointer;
}

.btn-primary {
    background: var(--yellow);
    color: var(--navy);
    border: 1px solid var(--yellow);
}

.btn-primary:hover {
    background: #ffc52c;
    border-color: #ffc52c;
    transform: translateY(-2px);
}

.btn-outline {
    background: #ffffff;
    border: 1px solid #ffffff;
    color: #00205b;
}

.btn-outline:hover {
    background: #f2a900;
    border-color: #f2a900;
    color: #00205b;
    transform: translateY(-2px);
}

/* FEATURES */

.features {
    background: #ffffff;
    color: var(--navy);
    padding: 65px 7%;
}

.section-heading {
    text-align: center;
    margin-bottom: 40px;
}

.section-heading small {
    color: var(--blue);
    font-size: 11px;
    font-weight: 800;
    letter-spacing: 2px;
}

.section-heading h2 {
    font-family: 'Manrope', sans-serif;
    font-size: 30px;
    margin: 10px 0;
}

.section-heading p {
    color: #718096;
    font-size: 14px;
}

.feature-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 22px;
    max-width: 1100px;
    margin: auto;
}

.feature-card {
    border: 1px solid #e5eaf1;
    padding: 27px;
    border-radius: 12px;
    transition: .2s;
}

.feature-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 12px 30px rgba(0,32,91,.08);
}

.feature-icon {
    width: 48px;
    height: 48px;
    display: grid;
    place-items: center;
    border-radius: 10px;
    background: #eaf0fa;
    color: var(--blue);
    font-size: 22px;
    margin-bottom: 18px;
}

.feature-card h3 {
    font-family: 'Manrope', sans-serif;
    font-size: 16px;
    margin-bottom: 9px;
}

.feature-card p {
    color: #718096;
    font-size: 13px;
    line-height: 1.7;
}

/* FOOTER */

.footer {
    background: #00183f;
    color: #b8cadd;
    padding: 25px 7%;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
    font-size: 11px;
}

/* REQUEST ACCOUNT MODAL */

.modal {
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,.65);
    display: none;
    align-items: center;
    justify-content: center;
    padding: 20px;
    z-index: 10;
}

.modal.show {
    display: flex;
}

.modal-content {
    background: white;
    color: var(--navy);
    width: 100%;
    max-width: 430px;
    padding: 30px;
    border-radius: 13px;
    position: relative;
    box-shadow: 0 15px 50px rgba(0,0,0,.2);
}

.modal-content h2 {
    font-family: 'Manrope', sans-serif;
    margin-bottom: 12px;
}

.modal-content p {
    color: #64748b;
    font-size: 14px;
    line-height: 1.7;
    margin-bottom: 15px;
}

.modal-content .close {
    position: absolute;
    right: 20px;
    top: 15px;
    border: none;
    background: none;
    color: #64748b;
    font-size: 25px;
    cursor: pointer;
}

.modal-content .btn {
    width: 100%;
    margin-top: 10px;
}

/* RESPONSIVE */

@media(max-width: 750px) {

    .header {
        padding: 16px 5%;
    }

    .brand img {
        width: 48px;
        height: 48px;
    }

    .brand h2 {
        font-size: 13px;
    }

    .brand p {
        font-size: 9px;
    }

    .hero-content {
        padding: 85px 7%;
    }

    .hero-content h1 {
        font-size: 40px;
    }

    .description {
        font-size: 14px;
    }

    .hero-buttons {
        flex-direction: column;
        align-items: flex-start;
    }

    .hero-buttons .btn {
        min-width: 190px;
    }

    .feature-grid {
        grid-template-columns: 1fr;
    }

    .features {
        padding: 50px 7%;
    }

    .footer {
        flex-direction: column;
        text-align: center;
    }

    .hero {
        background-attachment: scroll;
        background-position: 60% center;
    }
}

</style>

</head>

<body>

<!-- HERO SECTION -->

<section class="hero">

    <!-- HEADER -->

    <header class="header">

        <div class="brand">

            <img
                src="assets/image/LVlogo.jpg"
                alt="La Verdad Christian College Logo"
            >

            <div>
                <h2>LA VERDAD CHRISTIAN COLLEGE</h2>
                <p>APALIT, PAMPANGA</p>
            </div>

        </div>

    </header>

    <!-- HERO CONTENT -->

    <div class="hero-content">

        <div class="eyebrow">
            Student Meal Program
        </div>

        <h1>
            Meal Stub <span>Management</span> System
        </h1>

        <p class="description">
            A centralized platform designed to simplify student
            attendance reporting, meal stub issuance, and distribution
            monitoring at La Verdad Christian College.
        </p>

        <!-- LOGIN AND REQUEST ACCOUNT BUTTONS -->

        <div class="hero-buttons">

            <a href="login.php" class="btn btn-primary">
                Log In →
            </a>

            <button
                type="button"
                class="btn btn-outline"
                onclick="openRequestModal()"
            >
                Request an Account
            </button>

        </div>

    </div>

</section>

<!-- FEATURES SECTION -->

<section class="features">

    <div class="section-heading">

        <small>SYSTEM FEATURES</small>

        <h2>Simple. Organized. Efficient.</h2>

        <p>
            Supporting a more organized meal stub management process.
        </p>

    </div>

    <div class="feature-grid">

        <div class="feature-card">

            <div class="feature-icon">✓</div>

            <h3>Attendance Reporting</h3>

            <p>
                Record and submit daily class attendance
                through an organized reporting process.
            </p>

        </div>

        <div class="feature-card">

            <div class="feature-icon">▤</div>

            <h3>Meal Stub Issuance</h3>

            <p>
                Manage approved attendance reports and
                corresponding meal stub batches.
            </p>

        </div>

        <div class="feature-card">

            <div class="feature-icon">◷</div>

            <h3>Distribution Monitoring</h3>

            <p>
                Monitor meal stub distribution and
                maintain accessible system records.
            </p>

        </div>

    </div>

</section>

<!-- FOOTER -->

<footer class="footer">

    <span>
        © <?= date('Y') ?> La Verdad Christian College
    </span>

    <span>
        Meal Stub Management System
    </span>

</footer>

<!-- REQUEST ACCOUNT MODAL -->

<div
    class="modal"
    id="requestModal"
    onclick="closeRequestModal(event)"
>

    <div class="modal-content">

        <button
            type="button"
            class="close"
            onclick="closeRequestModal()"
            aria-label="Close"
        >
            &times;
        </button>

        <h2>Request an Account</h2>

        <p>
            Need access to the LVCC Meal Stub Management System?
            Please coordinate with the authorized PSAS Staff.
        </p>

        <p>
            Your account will be created and assigned to the
            appropriate role and section by PSAS.
        </p>

        <a href="login.php" class="btn btn-primary">
            Back to Log In
        </a>

    </div>

</div>

<!-- JAVASCRIPT -->

<script>

function openRequestModal() {
    document.getElementById('requestModal').classList.add('show');
}

function closeRequestModal(event) {

    if (
        !event ||
        event.target.id === 'requestModal' ||
        event.target.classList.contains('close')
    ) {
        document.getElementById('requestModal').classList.remove('show');
    }

}

document.addEventListener('keydown', function(event) {

    if (event.key === 'Escape') {
        document.getElementById('requestModal').classList.remove('show');
    }

});

</script>

</body>
</html>
