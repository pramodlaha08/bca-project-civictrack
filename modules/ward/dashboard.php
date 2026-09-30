<?php
//modules/ward/dashboard.php
session_start();
require_once '../../config/db.php';
require_once '../../config/constants.php';
require_once '../../includes/auth_check.php';
requireRole('ward_admin');

$db     = getDB();
$wardId = $_SESSION['ward_id'];

$pending  = $db->prepare("SELECT COUNT(*) FROM issues WHERE ward_id=? AND status='pending'");
$pending->execute([$wardId]); $pendingCount = $pending->fetchColumn();

$verified = $db->prepare("SELECT COUNT(*) FROM issues WHERE ward_id=? AND status='verified'");
$verified->execute([$wardId]); $verifiedCount = $verified->fetchColumn();

$inprog = $db->prepare("SELECT COUNT(*) FROM issues WHERE ward_id=? AND status='in_progress'");
$inprog->execute([$wardId]); $inProgCount = $inprog->fetchColumn();

$resolved = $db->prepare("SELECT COUNT(*) FROM issues WHERE ward_id=? AND status IN ('resolved','closed')");
$resolved->execute([$wardId]); $resolvedCount = $resolved->fetchColumn();

// Recent pending issues for this ward
$recentPending = $db->prepare("
    SELECT i.*, u.name AS citizen_name
    FROM issues i JOIN users u ON i.citizen_id = u.id
    WHERE i.ward_id = ? AND i.status = 'pending'
    ORDER BY i.priority_score DESC, i.created_at ASC
    LIMIT 8
");
$recentPending->execute([$wardId]);
$pendingIssues = $recentPending->fetchAll();

// Ward info
$ward = $db->prepare("SELECT * FROM wards WHERE id = ?");
$ward->execute([$wardId]);
$wardInfo = $ward->fetch();

$pageTitle = 'Ward Dashboard';
include '../../includes/header.php';
include '../../includes/sidebar.php';
?>
<main class="main-content">
  <div class="page-header">
    <div>
      <h1>Ward <?= $wardInfo['ward_number'] ?> Dashboard</h1>
      <p class="text-muted"><?= htmlspecialchars($wardInfo['name']) ?></p>
    </div>
    <a href="pending.php" class="btn btn-primary">Review Pending (<?= $pendingCount ?>)</a>
  </div>

  <div class="stats-grid stats-grid-4">
    <div class="stat-card stat-amber"><div class="stat-icon">&#9203;</div><div class="stat-value"><?= $pendingCount ?></div><div class="stat-label">Pending Review</div></div>
    <div class="stat-card stat-blue"><div class="stat-icon">&#9989;</div><div class="stat-value"><?= $verifiedCount ?></div><div class="stat-label">Verified</div></div>
    <div class="stat-card stat-teal"><div class="stat-icon">&#128295;</div><div class="stat-value"><?= $inProgCount ?></div><div class="stat-label">In Progress</div></div>
    <div class="stat-card stat-green"><div class="stat-icon">&#127881;</div><div class="stat-value"><?= $resolvedCount ?></div><div class="stat-label">Resolved</div></div>
  </div>

  <div class="card">
    <div class="card-header">
      <h2>Pending Issues — Needs Your Review</h2>
      <a href="pending.php" class="btn btn-sm btn-outline">View All</a>
    </div>
    <div class="table-wrapper">
      <table class="table">
        <thead><tr><th>#</th><th>Title</th><th>Category</th><th>Reported By</th><th>Priority</th><th>Date</th><th>Action</th></tr></thead>
        <tbody>
        <?php if (empty($pendingIssues)): ?>
          <tr><td colspan="7" class="empty-state"><div>&#127881;</div><p>No pending issues. All caught up!</p></td></tr>
        <?php else: ?>
        <?php foreach ($pendingIssues as $i): ?>
          <tr>
            <td>#<?= $i['id'] ?></td>
            <td>
              <span class="issue-title"><?= htmlspecialchars($i['title']) ?></span>
              <span class="text-muted text-sm"><?= htmlspecialchars($i['location_text']) ?></span>
            </td>
            <td class="text-sm"><?= htmlspecialchars($i['category']) ?></td>
            <td class="text-sm"><?= htmlspecialchars($i['citizen_name']) ?></td>
            <td><strong><?= $i['priority_score'] ?></strong></td>
            <td class="text-sm text-muted"><?= date('d M', strtotime($i['created_at'])) ?></td>
            <td><a href="pending.php?review=<?= $i['id'] ?>" class="btn btn-sm btn-primary">Review</a></td>
          </tr>
        <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</main>
<?php include '../../includes/footer.php'; ?>
