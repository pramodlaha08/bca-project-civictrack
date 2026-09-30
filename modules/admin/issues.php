<?php
//modules/admin/issues.php
session_start();
require_once '../../config/db.php';
require_once '../../config/constants.php';
require_once '../../includes/auth_check.php';
requireRole('municipality_admin');

$db = getDB();

// Filter by status
$filterStatus = $_GET['status'] ?? '';
$where = $filterStatus ? "WHERE i.status = " . $db->quote($filterStatus) : '';

$issues = $db->query("
    SELECT i.*, u.name AS citizen_name, w.ward_number, w.name AS ward_name
    FROM issues i
    JOIN users u ON i.citizen_id = u.id
    JOIN wards w ON i.ward_id = w.id
    {$where}
    ORDER BY i.created_at DESC
")->fetchAll();

$pageTitle = 'All Issues';
include '../../includes/header.php';
include '../../includes/sidebar.php';
?>
<main class="main-content">
  <div class="page-header">
    <div><h1>All Issues</h1><p class="text-muted"><?= count($issues) ?> issues found</p></div>
  </div>

  <!-- Status filter tabs -->
  <div class="filter-tabs">
    <a href="issues.php" class="filter-tab <?= !$filterStatus ? 'active' : '' ?>">All</a>
    <?php foreach (STATUS_LABELS as $key => $val): ?>
    <a href="issues.php?status=<?= $key ?>"
       class="filter-tab <?= $filterStatus === $key ? 'active' : '' ?>">
       <?= $val['label'] ?>
    </a>
    <?php endforeach; ?>
  </div>

  <div class="card" style="margin-top:16px">
    <div class="table-wrapper">
      <table class="table">
        <thead><tr><th>#</th><th>Title</th><th>Citizen</th><th>Ward</th><th>Category</th><th>Priority</th><th>Status</th><th>Date</th></tr></thead>
        <tbody>
        <?php if (empty($issues)): ?>
          <tr><td colspan="8" class="empty-state"><div>&#128203;</div><p>No issues found.</p></td></tr>
        <?php else: ?>
        <?php foreach ($issues as $i): ?>
          <tr>
            <td>#<?= $i['id'] ?></td>
            <td>
              <span class="issue-title"><?= htmlspecialchars($i['title']) ?></span>
              <span class="text-muted text-sm"><?= htmlspecialchars(substr($i['location_text'],0,35)) ?>...</span>
            </td>
            <td class="text-sm"><?= htmlspecialchars($i['citizen_name']) ?></td>
            <td>Ward <?= $i['ward_number'] ?></td>
            <td class="text-sm"><?= htmlspecialchars($i['category']) ?></td>
            <td><strong><?= $i['priority_score'] ?></strong> <span class="text-muted text-sm">(&#128077; <?= $i['support_count'] ?>)</span></td>
            <td><span class="badge <?= STATUS_LABELS[$i['status']]['class'] ?>"><?= STATUS_LABELS[$i['status']]['label'] ?></span></td>
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
