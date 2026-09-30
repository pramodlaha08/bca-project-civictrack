<?php
// public/register.php
session_start();
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/config/constants.php';

if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$error = $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name    = trim($_POST['name']     ?? '');
    $email   = trim($_POST['email']    ?? '');
    $phone   = trim($_POST['phone']    ?? '');
    $pass    = trim($_POST['password'] ?? '');
    $confirm = trim($_POST['confirm']  ?? '');

    if (empty($name) || empty($email) || empty($pass)) {
        $error = 'Name, email and password are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email.';
    } elseif (strlen($pass) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($pass !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $db  = getDB();
        $chk = $db->prepare("SELECT id FROM users WHERE email = ?");
        $chk->execute([$email]);
        if ($chk->fetch()) {
            $error = 'This email is already registered. Try logging in.';
        } else {
            $hash = password_hash($pass, PASSWORD_DEFAULT);
            $db->prepare("INSERT INTO users (name,email,password_hash,phone,role) VALUES (?,?,?,?,'citizen')")
                ->execute([$name, $email, $hash, $phone]);
            $success = 'Account created! You can now sign in.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Register — CivicTrack</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>

<body class="login-page">
    <div class="login-wrapper">
        <div class="login-brand">
            <div class="brand-icon">&#127963;</div>
            <h1>CivicTrack</h1>
            <p>Create your citizen account</p>
        </div>
        <div class="login-card">
            <h2>Register</h2>

            <?php if ($success): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success) ?>
                    <a href="login.php">Sign in now</a>
                </div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" name="name" class="form-control" required
                        value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" class="form-control" required
                        value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Phone <span class="text-muted">(optional)</span></label>
                    <input type="text" name="phone" class="form-control"
                        value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" class="form-control" required minlength="6">
                </div>
                <div class="form-group">
                    <label>Confirm Password</label>
                    <input type="password" name="confirm" class="form-control" required minlength="6">
                </div>
                <button type="submit" class="btn btn-primary btn-block">Create Account</button>
            </form>

            <div class="login-hints">
                <p>Already have an account? <a href="login.php">Sign in</a></p>
            </div>
        </div>
    </div>
</body>

</html>