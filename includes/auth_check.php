<?php
// includes/auth_check.php
// Place at top of every protected page:
//   session_start();
//   require_once '../../includes/auth_check.php';
//   requireRole('municipality_admin');

if (!function_exists('requireLogin')) {

    function requireLogin(): void {
        if (!isset($_SESSION['user_id'])) {
            header('Location: ../../public/login.php');
            exit;
        }
        if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time']) > 7200) {
            session_unset();
            session_destroy();
            header('Location: ../../public/login.php?timeout=1');
            exit;
        }
    }

    function requireRole(string|array $roles): void {
        requireLogin();
        $allowed = is_array($roles) ? $roles : [$roles];
        if (!in_array($_SESSION['user_role'] ?? '', $allowed, true)) {
            http_response_code(403);
            die('<div style="font-family:sans-serif;text-align:center;padding:80px 20px">
              <h2 style="color:#dc2626">&#x26D4; Access Denied</h2>
              <p style="color:#6b7280;margin:12px 0">You do not have permission to view this page.</p>
              <a href="../../public/index.php"
                 style="background:#2563eb;color:white;padding:10px 20px;border-radius:8px;text-decoration:none">
                 Go to my dashboard
              </a></div>');
        }
    }
}
