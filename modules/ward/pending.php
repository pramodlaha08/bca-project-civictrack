<?php
//modules/ward/dashboard.php
session_start();
require_once '../../config/db.php';
require_once '../../config/constants.php';
require_once '../../includes/auth_check.php';
requireRole('ward_admin');

$db     = getDB();
$wardId = $_SESSION['ward_id'];
$uid    = $_SESSION['user_id'];
$success = $error = '';

// Handle verify / reject / assign actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action   = $_POST['action']   ?? '';
    $issue_id = (int)($_POST['issue_id'] ?? 0);
    $remarks  = trim($_POST['remarks']   ?? '');

    // Make sure issue belongs to this ward
    $chk = $db->prepare("SELECT * FROM issues WHERE id = ? AND ward_id = ?");
    $chk->execute([$issue_id, $wardId]);
    $issue = $chk->fetch();

    if (!$issue) {
        $error = 'Issue not found or does not belong to your ward.';
    } elseif ($action === 'verify') {
        $db->prepare("UPDATE issues SET status='verified', updated_at=NOW() WHERE id=?")->execute([$issue_id]);
        $db->prepare("INSERT INTO issue_history (issue_id, changed_by, old_status, new_status, remarks) VALUES (?,?,?,?,?)")
           ->execute([$issue_id, $uid, $issue['status'], 'verified', $remarks ?: 'Verified by ward admin']);
        $success = "Issue #$issue_id verified successfully.";
    } elseif ($action === 'reject') {
        if (empty($remarks)) { $error = 'Rejection reason is required.'; }
        else {
            $db->prepare("UPDATE issues SET status='rejected', updated_at=NOW() WHERE id=?")->execute([$issue_id]);
            $db->prepare("INSERT INTO issue_history (issue_id, changed_by, old_status, new_status, remarks) VALUES (?,?,?,?,?)")
               ->execute([$issue_id, $uid, $issue['status'], 'rejected', $remarks]);
            $success = "Issue #$issue_id rejected.";
        }
    } elseif ($action === 'assign') {
        $dept_id = (int)($_POST['dept_id'] ?? 0);
        if (!$dept_id) { $error = 'Please select a department.'; }
        else {
            $db->prepare("UPDATE issues SET status='assigned', updated_at=NOW() WHERE id=?")->execute([$issue_id]);
            $db->prepare("INSERT INTO assignments (issue_id, assigned_by, department_id, remarks) VALUES (?,?,?,?)")
               ->execute([$issue_id, $uid, $dept_id, $remarks ?: 'Assigned by ward admin']);
            $db->prepare("INSERT INTO issue_history (issue_id, changed_by, old_status, new_status, remarks) VALUES (?,?,?,?,?)")
               ->execute([$issue_id, $uid, $issue['status'], 'assigned', 'Assigned to department']);
            $success = "Issue #$issue_id assigned to department.";
        }
    }
}

$pendingIssues = $db->prepare("
    SELECT i.*, u.name AS citizen_name
    FROM issues i JOIN users u ON i.citizen_id = u.id
    WHERE i.ward_id = ? AND i.status = 'pending'
    ORDER BY i.priority_score DESC, i.created_at ASC
");
$pendingIssues->execute([$wardId]);
$issues = $pendingIssues->fetchAll();

$depts = $db->query("SELECT * FROM departments WHERE is_active=1 ORDER BY name")->fetchAll();

$pageTitle = 'Pending Review';
include '../../includes/header.php';
include '../../includes/sidebar.php';
?>
<main class="main-content">
  <div class="page-header">
    <div><h1>Pending Review</h1><p class="text-muted"><?= count($issues) ?> issues waiting for your decision</p></div>
  </div>
  <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
  <?php if ($error):   ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

  <?php if (empty($issues)): ?>
    <div class="card"><div class="empty-state"><div>&#127881;</div><p>No pending issues. All caught up!</p></div></div>
  <?php else: ?>
  <?php foreach ($issues as $i): ?>
  <div class="card" style="margin-bottom:16px">
    <div class="card-header">
      <div>
        <span style="font-size:13px;color:#6b7280">#<?= $i['id'] ?> &middot; <?= htmlspecialchars($i['category']) ?> &middot; Reported by <?= htmlspecialchars($i['citizen_name']) ?> &middot; <?= date('d M Y', strtotime($i['created_at'])) ?></span>
        <h3 style="margin-top:4px"><?= htmlspecialchars($i['title']) ?></h3>
      </div>
      <span class="badge badge-pending">Pending</span>
    </div>
    <div class="card-body">
      <p style="margin-bottom:8px"><strong>Location:</strong> <?= htmlspecialchars($i['location_text']) ?></p>
      <p style="margin-bottom:16px;color:#475569"><?= htmlspecialchars($i['description']) ?></p>

      <div style="display:flex;gap:16px;flex-wrap:wrap">
        <!-- Verify form -->
        <form method="POST" style="flex:1;min-width:200px;background:#f0fdf4;padding:14px;border-radius:8px;border:1px solid #bbf7d0">
          <input type="hidden" name="action"   value="verify">
          <input type="hidden" name="issue_id" value="<?= $i['id'] ?>">
          <strong style="color:#15803d;display:block;margin-bottom:8px">&#9989; Mark as Verified</strong>
          <div class="form-group">
            <textarea name="remarks" class="form-control" rows="2" placeholder="Optional remarks..."></textarea>
          </div>
          <button class="btn btn-sm" style="background:#16a34a;color:white">Verify Issue</button>
        </form>

        <!-- Reject form -->
        <form method="POST" style="flex:1;min-width:200px;background:#fef2f2;padding:14px;border-radius:8px;border:1px solid #fecaca">
          <input type="hidden" name="action"   value="reject">
          <input type="hidden" name="issue_id" value="<?= $i['id'] ?>">
          <strong style="color:#b91c1c;display:block;margin-bottom:8px">&#10060; Reject Issue</strong>
          <div class="form-group">
            <textarea name="remarks" class="form-control" rows="2" placeholder="Reason for rejection (required)..." required></textarea>
          </div>
          <button class="btn btn-sm btn-danger">Reject Issue</button>
        </form>

        <!-- Assign form -->
        <form method="POST" style="flex:1;min-width:200px;background:#eff6ff;padding:14px;border-radius:8px;border:1px solid #bfdbfe">
          <input type="hidden" name="action"   value="assign">
          <input type="hidden" name="issue_id" value="<?= $i['id'] ?>">
          <strong style="color:#1d4ed8;display:block;margin-bottom:8px">&#128204; Verify &amp; Assign</strong>
          <div class="form-group">
            <select name="dept_id" class="form-control" required>
              <option value="">— Select department —</option>
              <?php foreach ($depts as $d): ?>
              <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <textarea name="remarks" class="form-control" rows="2" placeholder="Assignment remarks..."></textarea>
          </div>
          <button class="btn btn-sm btn-primary">Assign to Dept.</button>
        </form>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
  <?php endif; ?>
</main>
<?php include '../../includes/footer.php'; ?>
