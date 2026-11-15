<?php
// Retired legacy page. The supported distribution workflow is distribution_tracker.php.
session_start();

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'secretary') {
    header('Location: login.php', true, 303);
    exit;
}

header('Location: distribution_tracker.php', true, 303);
exit;
