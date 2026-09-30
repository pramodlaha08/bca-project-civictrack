<?php
//modules/citizen/dashboard.php
session_start();
require_once '../../config/db.php';
require_once '../../config/constants.php';
require_once '../../includes/auth_check.php';
requireRole('citizen');

$db  = getDB();
$uid = $_SESSION['user_id'];

$stmt = $db->prepare("
    SELECT i.*, w.ward_number
    FROM issues i JOIN wards w ON i.ward_id = w.id
    WHERE i.citizen_id = ?
    ORDER BY i.created_at DESC
");
$stmt->execute([$uid]);
$issues = $stmt->fetchAll();

$total    = count($issues);
$pending  = count(array_filter($issues, fn($i) => $i['status'] === 'pending'));
$active   = count(array_filter($issues, fn($i) => in_array($i['status'], ['verified','assigned','in_progress'])));
$resolved = count(array_filter($issues, fn($i) => in_array($i['status'], ['resolved','closed'])));

$pageTitle = 'My Dashboard';
include '../../includes/header.php';
include '../../includes/sidebar.php';
?>
<main class="main-content">
  <div class="page-header">
    <div>
      <h1>Welcome, <?= htmlspecialchars($_SESSION['user_name']) ?></h1>
      <p class="text-muted">Track all your submitted issues here</p>
    </div>
    <a href="report.php" class="btn btn-primary">+ Report Issue</a>
  </div>

  <div class="stats-grid stats-grid-4">
    <div class="stat-card stat-blue"><div class="stat-icon">&#128203;</div><div class="stat-value"><?= $total ?></div><div class="stat-label">Total Reported</div></div>
    <div class="stat-card stat-amber"><div class="stat-icon">&#9203;</div><div class="stat-value"><?= $pending ?></div><div class="stat-label">Awaiting Review</div></div>
    <div class="stat-card stat-teal"><div class="stat-icon">&#128295;</div><div class="stat-value"><?= $active ?></div><div class="stat-label">Being Worked On</div></div>
    <div class="stat-card stat-green"><div class="stat-icon">&#9989;</div><div class="stat-value"><?= $resolved ?></div><div class="stat-label">Resolved</div></div>
  </div>

  <div class="card">
    <div class="card-header"><h2>My Reports</h2></div>
    <div class="table-wrapper">
      <table class="table">
        <thead><tr><th>#</th><th>Issue</th><th>Ward</th><th>Category</th><th>Status</th><th>Support</th><th>Date</th></tr></thead>
        <tbody>
        <?php if (empty($issues)): ?>
          <tr><td colspan="7" class="empty-state">
            <div>&#128221;</div>
            <p>You haven't reported any issues yet.</p>
            <a href="report.php" class="btn btn-primary btn-sm">Report your first issue</a>
          </td></tr>
        <?php else: ?>
        <?php foreach ($issues as $i): ?>
          <tr>
            <td>#<?= $i['id'] ?></td>
            <td>
              <span class="issue-title"><?= htmlspecialchars($i['title']) ?></span>
              <span class="text-muted text-sm"><?= htmlspecialchars($i['location_text']) ?></span>
            </td>
            <td>Ward <?= $i['ward_number'] ?></td>
            <td class="text-sm"><?= htmlspecialchars($i['category']) ?></td>
            <td><span class="badge <?= STATUS_LABELS[$i['status']]['class'] ?>"><?= STATUS_LABELS[$i['status']]['label'] ?></span></td>
            <td>&#128077; <?= $i['support_count'] ?></td>
            <td class="text-sm text-muted"><?= date('d M Y', strtotime($i['created_at'])) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</main>
<?php include '../../includes/footer.php'; ?>
