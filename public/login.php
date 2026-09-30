<?php
// public/login.php
session_start();
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/config/constants.php';

if (isset($_SESSION['user_id'])) {
  header('Location: index.php');
  exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $email    = trim($_POST['email']    ?? '');
  $password = trim($_POST['password'] ?? '');

  if (empty($email) || empty($password)) {
    $error = 'Please enter both email and password.';
  } else {
    $db   = getDB();
    $stmt = $db->prepare("SELECT * FROM users WHERE email = ? AND is_active = 1 LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
      $_SESSION['user_id']    = $user['id'];
      $_SESSION['user_name']  = $user['name'];
      $_SESSION['user_role']  = $user['role'];
      $_SESSION['ward_id']    = $user['ward_id'];
      $_SESSION['dept_id']    = $user['department_id'];
      $_SESSION['login_time'] = time();
      header('Location: index.php');
      exit;
    } else {
      $error = 'Invalid email or password. Please try again.';
    }
  }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Login — CivicTrack</title>
  <link rel="stylesheet" href="../assets/css/style.css">
</head>

<body class="login-page">

  <div class="login-wrapper">
    <div class="login-brand">
      <div class="brand-icon">&#127963;</div>
      <h1>CivicTrack</h1>
      <p>Local Problem Reporting System</p>
      <p class="brand-sub">Municipality Management Portal</p>
    </div>

    <div class="login-card">
      <h2>Sign in to your account</h2>

      <?php if (isset($_GET['timeout'])): ?>
        <div class="alert alert-danger">Session expired. Please sign in again.</div>
      <?php endif; ?>

      <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>
      <p style="text-align:center;margin-top:16px;font-size:13px">
        <a href="home.php">&larr; Back to home</a> &nbsp;|&nbsp;
        Don't have an account? <a href="register.php">Register</a>
      </p>

      <form method="POST" action="">
        <div class="form-group">
          <label for="email">Email address</label>
          <input type="email" id="email" name="email" class="form-control"
            placeholder="your@email.com"
            value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
            required autofocus>
        </div>
        <div class="form-group">
          <label for="password">Password</label>
          <input type="password" id="password" name="password" class="form-control"
            placeholder="Enter your password" required>
        </div>
        <button type="submit" class="btn btn-primary btn-block">Sign in</button>
      </form>

      <div class="login-hints">
        <p><strong>Demo accounts</strong> &mdash; password for all: <code>password</code></p>
        <div class="hint-grid">
          <span class="hint-role admin">Municipality Admin</span>
          <span class="hint-email">admin@civictrack.gov.np</span>
          <span class="hint-role ward">Ward Admin</span>
          <span class="hint-email">ward1@civictrack.gov.np</span>
          <span class="hint-role officer">Dept. Officer</span>
          <span class="hint-email">road1@civictrack.gov.np</span>
          <span class="hint-role citizen">Citizen</span>
          <span class="hint-email">citizen1@gmail.com</span>
        </div>
      </div>
    </div>
  </div>

</body>

</html>