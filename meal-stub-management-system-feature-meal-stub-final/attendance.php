<?php
// Retired legacy page. The supported attendance workflow is daily_attendance.php.
session_start();

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'secretary') {
    header('Location: login.php', true, 303);
    exit;
}

header('Location: daily_attendance.php', true, 303);
exit;
