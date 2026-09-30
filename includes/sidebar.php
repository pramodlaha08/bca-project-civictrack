<?php
// includes/sidebar.php
$role        = $_SESSION['user_role'] ?? '';
$currentFile = basename($_SERVER['PHP_SELF']);
?>
<aside class="sidebar">
  <nav class="sidebar-nav">

    <?php if ($role === 'municipality_admin'): ?>
      <div class="nav-section">Municipality Admin</div>
      <a href="dashboard.php" class="nav-item <?= $currentFile === 'dashboard.php'  ? 'active' : '' ?>">&#128202; Dashboard</a>
      <a href="wards.php" class="nav-item <?= $currentFile === 'wards.php'      ? 'active' : '' ?>">&#127968; Manage Wards</a>
      <a href="departments.php" class="nav-item <?= $currentFile === 'departments.php' ? 'active' : '' ?>">&#127970; Departments</a>
      <a href="users.php" class="nav-item <?= $currentFile === 'users.php'      ? 'active' : '' ?>">&#128101; Manage Users</a>
      <a href="issues.php" class="nav-item <?= $currentFile === 'issues.php'     ? 'active' : '' ?>">&#128203; All Issues</a>

    <?php elseif ($role === 'ward_admin'): ?>
      <div class="nav-section">Ward Admin</div>
      <a href="dashboard.php" class="nav-item <?= $currentFile === 'dashboard.php' ? 'active' : '' ?>">&#128202; Dashboard</a>
      <a href="pending.php" class="nav-item <?= $currentFile === 'pending.php'   ? 'active' : '' ?>">&#9203; Pending Review</a>
      <a href="verified.php" class="nav-item <?= $currentFile === 'verified.php'  ? 'active' : '' ?>">&#9989; Verified Issues</a>
      <a href="assigned.php" class="nav-item <?= $currentFile === 'assigned.php'  ? 'active' : '' ?>">&#128204; Assigned</a>

    <?php elseif ($role === 'dept_officer'): ?>
      <div class="nav-section">Department Officer</div>
      <a href="dashboard.php" class="nav-item <?= $currentFile === 'dashboard.php' ? 'active' : '' ?>">&#128202; My Tasks</a>
      <a href="completed.php" class="nav-item <?= $currentFile === 'completed.php' ? 'active' : '' ?>">&#9989; Completed</a>

    <?php elseif ($role === 'citizen'): ?>
      <div class="nav-section">Citizen Portal</div>
      <a href="dashboard.php" class="nav-item <?= $currentFile === 'dashboard.php' ? 'active' : '' ?>">&#127968; Dashboard</a>
      <a href="report.php" class="nav-item <?= $currentFile === 'report.php'    ? 'active' : '' ?>">&#128221; Report Issue</a>
      <a href="my_issues.php" class="nav-item <?= $currentFile === 'my_issues.php' ? 'active' : '' ?>">&#128203; My Reports</a>
      <a href="../../public/home.php" class="nav-item">&#127760; Browse All Issues</a>
    <?php endif; ?>

  </nav>
</aside>