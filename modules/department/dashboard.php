<?php
//modules/department/dashboard.php
session_start();
require_once '../../config/db.php';
require_once '../../config/constants.php';
require_once '../../includes/auth_check.php';
requireRole('dept_officer');

$db     = getDB();
$uid    = $_SESSION['user_id'];
$deptId = $_SESSION['dept_id'];
$success = $error = '';

// Handle accept task
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action   = $_POST['action']   ?? '';
    $issue_id = (int)($_POST['issue_id'] ?? 0);
    $remarks  = trim($_POST['remarks']   ?? '');

    if ($action === 'accept') {
        // Set officer_id in assignments
        $db->prepare("UPDATE assignments SET officer_id = ? WHERE issue_id = ? AND department_id = ?")
           ->execute([$uid, $issue_id, $deptId]);
        $db->prepare("UPDATE issues SET status='in_progress', updated_at=NOW() WHERE id=?")
           ->execute([$issue_id]);
        $db->prepare("INSERT INTO issue_history (issue_id, changed_by, old_status, new_status, remarks) VALUES (?,?,'assigned','in_progress',?)")
           ->execute([$issue_id, $uid, 'Accepted by officer: ' . $_SESSION['user_name']]);
        $success = "Task #$issue_id accepted. Status updated to In Progress.";

    } elseif ($action === 'update') {
        if (empty($remarks)) { $error = 'Progress remarks are required.'; }
        else {
            $db->prepare("INSERT INTO progress_updates (issue_id, officer_id, remarks) VALUES (?,?,?)")
               ->execute([$issue_id, $uid, $remarks]);
            $success = "Progress update added for Issue #$issue_id.";
        }

    } elseif ($action === 'resolve') {
        $db->prepare("UPDATE issues SET status='resolved', updated_at=NOW() WHERE id=?")->execute([$issue_id]);
        $db->prepare("INSERT INTO issue_history (issue_id, changed_by, old_status, new_status, remarks) VALUES (?,?,'in_progress','resolved',?)")
           ->execute([$issue_id, $uid, $remarks ?: 'Work completed by ' . $_SESSION['user_name']]);
        $success = "Issue #$issue_id marked as Resolved. Ward admin will confirm closure.";
    }
}

// Get dept info
$dept = $db->prepare("SELECT * FROM departments WHERE id=?");
$dept->execute([$deptId]);
$deptInfo = $dept->fetch();

// Assigned tasks (not yet accepted — no officer_id)
$assigned = $db->prepare("
    SELECT i.*, a.id AS assign_id, a.remarks AS assign_remarks, u.name AS citizen_name, w.ward_number
    FROM issues i
    JOIN assignments a ON i.id = a.issue_id
    JOIN users u ON i.citizen_id = u.id
    JOIN wards w ON i.ward_id = w.id
    WHERE a.department_id = ? AND a.officer_id IS NULL AND i.status = 'assigned'
    ORDER BY i.priority_score DESC
");
$assigned->execute([$deptId]);
$assignedTasks = $assigned->fetchAll();

// My in-progress tasks
$myTasks = $db->prepare("
    SELECT i.*, u.name AS citizen_name, w.ward_number
    FROM issues i
    JOIN assignments a ON i.id = a.issue_id
    JOIN users u ON i.citizen_id = u.id
    JOIN wards w ON i.ward_id = w.id
    WHERE a.department_id = ? AND a.officer_id = ? AND i.status = 'in_progress'
    ORDER BY i.updated_at DESC
");
$myTasks->execute([$deptId, $uid]);
$inProgressTasks = $myTasks->fetchAll();

$pageTitle = 'My Tasks';
include '../../includes/header.php';
include '../../includes/sidebar.php';
?>
<main class="main-content">
  <div class="page-header">
    <div>
      <h1><?= htmlspecialchars($deptInfo['name'] ?? 'Department') ?></h1>
      <p class="text-muted">Officer: <?= htmlspecialchars($_SESSION['user_name']) ?></p>
    </div>
  </div>

  <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
  <?php if ($error):   ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

  <!-- Unaccepted tasks -->
  <h2 style="font-size:16px;margin-bottom:12px">&#128204; Assigned to Department <span style="color:#6b7280;font-weight:400;font-size:14px">(<?= count($assignedTasks) ?> tasks waiting)</span></h2>
  <?php if (empty($assignedTasks)): ?>
    <div class="card" style="margin-bottom:24px"><div class="empty-state"><div>&#127881;</div><p>No unassigned tasks.</p></div></div>
  <?php else: ?>
  <?php foreach ($assignedTasks as $t): ?>
  <div class="card" style="margin-bottom:14px">
    <div class="card-header">
      <div>
        <span class="text-muted text-sm">#<?= $t['id'] ?> &middot; <?= htmlspecialchars($t['category']) ?> &middot; Ward <?= $t['ward_number'] ?></span>
        <h3 style="margin-top:4px"><?= htmlspecialchars($t['title']) ?></h3>
        <span class="text-muted text-sm"><?= htmlspecialchars($t['location_text']) ?></span>
      </div>
      <div style="text-align:right">
        <span class="badge badge-assigned">Assigned</span>
        <div class="text-sm text-muted" style="margin-top:4px">Priority: <?= $t['priority_score'] ?></div>
      </div>
    </div>
    <div class="card-body" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px">
      <p style="color:#475569;flex:1"><?= htmlspecialchars($t['description']) ?></p>
      <form method="POST">
        <input type="hidden" name="action"   value="accept">
        <input type="hidden" name="issue_id" value="<?= $t['id'] ?>">
        <button class="btn btn-primary">Accept &amp; Start Work</button>
      </form>
    </div>
  </div>
  <?php endforeach; ?>
  <?php endif; ?>

  <!-- My in-progress tasks -->
  <h2 style="font-size:16px;margin:24px 0 12px">&#128295; My In-Progress Tasks <span style="color:#6b7280;font-weight:400;font-size:14px">(<?= count($inProgressTasks) ?>)</span></h2>
  <?php foreach ($inProgressTasks as $t): ?>
  <div class="card" style="margin-bottom:14px">
    <div class="card-header">
      <div>
        <span class="text-muted text-sm">#<?= $t['id'] ?> &middot; <?= htmlspecialchars($t['category']) ?> &middot; Ward <?= $t['ward_number'] ?></span>
        <h3 style="margin-top:4px"><?= htmlspecialchars($t['title']) ?></h3>
      </div>
      <span class="badge badge-inprogress">In Progress</span>
    </div>
    <div class="card-body">
      <div style="display:flex;gap:14px;flex-wrap:wrap">
        <!-- Update progress -->
        <form method="POST" style="flex:1;min-width:220px;background:#fffbeb;padding:14px;border-radius:8px;border:1px solid #fde68a">
          <input type="hidden" name="action"   value="update">
          <input type="hidden" name="issue_id" value="<?= $t['id'] ?>">
          <strong style="color:#92400e;display:block;margin-bottom:8px">&#128221; Add Progress Update</strong>
          <textarea name="remarks" class="form-control" rows="3" placeholder="What work was done today..." required></textarea>
          <button class="btn btn-sm" style="margin-top:8px;background:#d97706;color:white">Add Update</button>
        </form>

        <!-- Mark resolved -->
        <form method="POST" style="flex:1;min-width:220px;background:#f0fdf4;padding:14px;border-radius:8px;border:1px solid #bbf7d0">
          <input type="hidden" name="action"   value="resolve">
          <input type="hidden" name="issue_id" value="<?= $t['id'] ?>">
          <strong style="color:#15803d;display:block;margin-bottom:8px">&#9989; Mark as Resolved</strong>
          <textarea name="remarks" class="form-control" rows="3" placeholder="Describe completed work..."></textarea>
          <button class="btn btn-sm" style="margin-top:8px;background:#16a34a;color:white">Mark Resolved</button>
        </form>
      </div>
    </div>
  </div>
  <?php endforeach; ?>

</main>
<?php include '../../includes/footer.php'; ?>
