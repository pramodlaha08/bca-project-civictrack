<?php
// public/index.php — Role-based router
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: home.php');
    exit;
}

if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time']) > 7200) {
    session_unset();
    session_destroy();
    header('Location: login.php?timeout=1');
    exit;
}

switch ($_SESSION['user_role'] ?? '') {
    case 'municipality_admin':
        header('Location: ../modules/admin/dashboard.php');
        break;
    case 'ward_admin':
        header('Location: ../modules/ward/dashboard.php');
        break;
    case 'dept_officer':
        header('Location: ../modules/department/dashboard.php');
        break;
    case 'citizen':
        header('Location: ../modules/citizen/dashboard.php');
        break;
    default:
        session_unset();
        session_destroy();
        header('Location: home.php');
}
exit;
