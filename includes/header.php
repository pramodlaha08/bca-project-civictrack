<?php
// includes/header.php
$pageTitle = $pageTitle ?? 'CivicTrack';
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= htmlspecialchars($pageTitle) ?> — CivicTrack</title>
  <link rel="stylesheet" href="../../assets/css/style.css">
</head>

<body>

  <nav class="topnav">
    <div class="topnav-brand">
      <span class="brand-logo">&#127963;</span>
      <span class="brand-name">CivicTrack</span>
    </div>
    <div class="topnav-right">
      <span class="nav-user">
        &#128100; <?= htmlspecialchars($_SESSION['user_name'] ?? '') ?>
        <span class="role-badge role-<?= $_SESSION['user_role'] ?? '' ?>">
          <?= ucwords(str_replace('_', ' ', $_SESSION['user_role'] ?? '')) ?>
        </span>
      </span>
      <a href="../../public/logout.php" class="btn btn-sm btn-outline">Sign out</a>
    </div>
  </nav>

  <div class="layout">