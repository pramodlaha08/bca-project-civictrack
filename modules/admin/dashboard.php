<?php
//modules/admin/dashboard.php
session_start();
require_once '../../config/db.php';
require_once '../../config/constants.php';
require_once '../../includes/auth_check.php';
requireRole('municipality_admin');

$db = getDB();

$totalIssues    = $db->query("SELECT COUNT(*) FROM issues")->fetchColumn();
$pendingIssues  = $db->query("SELECT COUNT(*) FROM issues WHERE status='pending'")->fetchColumn();
$resolvedIssues = $db->query("SELECT COUNT(*) FROM issues WHERE status IN ('resolved','closed')")->fetchColumn();
$inProgress     = $db->query("SELECT COUNT(*) FROM issues WHERE status='in_progress'")->fetchColumn();
$totalUsers     = $db->query("SELECT COUNT(*) FROM users WHERE role != 'municipality_admin'")->fetchColumn();
$totalWards     = $db->query("SELECT COUNT(*) FROM wards")->fetchColumn();
$totalDepts     = $db->query("SELECT COUNT(*) FROM departments WHERE is_active=1")->fetchColumn();
$resolutionRate = $totalIssues > 0 ? round(($resolvedIssues / $totalIssues) * 100) : 0;

$recentIssues = $db->query("
    SELECT i.*, u.name AS citizen_name, w.ward_number
    FROM issues i
    JOIN users u ON i.citizen_id = u.id
    JOIN wards w ON i.ward_id = w.id
    ORDER BY i.created_at DESC LIMIT 10
")->fetchAll();

$wardStats = $db->query("
    SELECT w.ward_number, w.name,
           COUNT(i.id)                            AS total,
           SUM(i.status IN ('resolved','closed')) AS resolved,
           SUM(i.status = 'pending')              AS pending
    FROM wards w
    LEFT JOIN issues i ON w.id = i.ward_id
    GROUP BY w.id ORDER BY total DESC
")->fetchAll();

$pageTitle = 'Dashboard';
include '../../includes/header.php';
include '../../includes/sidebar.php';
?>

<main class="main-content">
  <div class="page-header">
    <div>
      <h1>Municipality Dashboard</h1>
      <p class="text-muted">System-wide overview — all wards and departments</p>
    </div>
    <div class="header-actions">
      <a href="users.php" class="btn btn-primary">+ Add User</a>
      <a href="wards.php" class="btn btn-outline">Manage Wards</a>
    </div>
  </div>

  <div class="stats-grid">
    <div class="stat-card stat-blue">
      <div class="stat-icon">&#128203;</div>
      <div class="stat-value"><?= $totalIssues ?></div>
      <div class="stat-label">Total Issues</div>
    </div>
    <div class="stat-card stat-amber">
      <div class="stat-icon">&#9203;</div>
      <div class="stat-value"><?= $pendingIssues ?></div>
      <div class="stat-label">Pending Review</div>
    </div>
    <div class="stat-card stat-teal">
      <div class="stat-icon">&#128295;</div>
      <div class="stat-value"><?= $inProgress ?></div>
      <div class="stat-label">In Progress</div>
    </div>
    <div class="stat-card stat-green">
      <div class="stat-icon">&#9989;</div>
      <div class="stat-value"><?= $resolvedIssues ?></div>
      <div class="stat-label">Resolved / Closed</div>
    </div>
    <div class="stat-card stat-purple">
      <div class="stat-icon">&#128200;</div>
      <div class="stat-value"><?= $resolutionRate ?>%</div>
      <div class="stat-label">Resolution Rate</div>
    </div>
    <div class="stat-card stat-coral">
      <div class="stat-icon">&#127963;</div>
      <div class="stat-value"><?= $totalWards ?> / <?= $totalDepts ?></div>
      <div class="stat-label">Wards / Departments</div>
    </div>
  </div>

  <div class="two-col">
    <div class="card">
      <div class="card-header">
        <h2>Recent Issues</h2>
        <a href="issues.php" class="btn btn-sm btn-outline">View All</a>
      </div>
      <div class="table-wrapper">
        <table class="table">
          <thead>
            <tr><th>#</th><th>Title</th><th>Ward</th><th>Category</th><th>Status</th><th>Date</th></tr>
          </thead>
          <tbody>
          <?php foreach ($recentIssues as $issue): ?>
            <tr>
              <td>#<?= $issue['id'] ?></td>
              <td>
                <span class="issue-title"><?= htmlspecialchars($issue['title']) ?></span>
                <span class="text-muted text-sm"><?= htmlspecialchars($issue['citizen_name']) ?></span>
              </td>
              <td>Ward <?= $issue['ward_number'] ?></td>
              <td class="text-sm"><?= htmlspecialchars($issue['category']) ?></td>
              <td><span class="badge <?= STATUS_LABELS[$issue['status']]['class'] ?>"><?= STATUS_LABELS[$issue['status']]['label'] ?></span></td>
              <td class="text-sm text-muted"><?= date('d M Y', strtotime($issue['created_at'])) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="card">
      <div class="card-header"><h2>Ward Performance</h2></div>
      <div class="ward-list">
        <?php foreach ($wardStats as $w): ?>
        <?php $rate = $w['total'] > 0 ? round(($w['resolved'] / $w['total']) * 100) : 0; ?>
        <div class="ward-row">
          <div class="ward-info">
            <strong>Ward <?= $w['ward_number'] ?></strong>
            <span class="text-muted text-sm"><?= $w['total'] ?> issues &middot; <?= $w['pending'] ?> pending</span>
          </div>
          <div class="ward-bar-wrap">
            <div class="ward-bar"><div class="ward-bar-fill" style="width:<?= $rate ?>%"></div></div>
            <span class="ward-pct"><?= $rate ?>%</span>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</main>

<?php include '../../includes/footer.php'; ?>
