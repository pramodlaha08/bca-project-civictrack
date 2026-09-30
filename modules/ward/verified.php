<?php
// modules/ward/verified.php
session_start();
require_once '../../config/db.php';
require_once '../../config/constants.php';
require_once '../../includes/auth_check.php';
requireRole('ward_admin');

$db     = getDB();
$wardId = $_SESSION['ward_id'];
$uid    = $_SESSION['user_id'];
$success = $error = '';

// ─── Handle assignment action (from this page too) ─────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action   = $_POST['action']   ?? '';
    $issue_id = (int)($_POST['issue_id'] ?? 0);
    $remarks  = trim($_POST['remarks']   ?? '');

    // Scope check
    $chk = $db->prepare("SELECT * FROM issues WHERE id = ? AND ward_id = ?");
    $chk->execute([$issue_id, $wardId]);
    $issue = $chk->fetch();

    if (!$issue) {
        $error = 'Issue not found or does not belong to your ward.';
    } elseif ($issue['status'] !== 'verified') {
        $error = 'Only verified issues can be assigned from this page.';
    } elseif ($action === 'assign') {
        $dept_id = (int)($_POST['dept_id'] ?? 0);
        if (!$dept_id) {
            $error = 'Please select a department.';
        } else {
            try {
                $db->beginTransaction();

                $db->prepare("UPDATE issues SET status='assigned', updated_at=NOW() WHERE id=?")
                    ->execute([$issue_id]);

                $db->prepare("INSERT INTO assignments (issue_id, assigned_by, department_id, remarks) VALUES (?,?,?,?)")
                    ->execute([$issue_id, $uid, $dept_id, $remarks ?: 'Assigned by ward admin']);

                $db->prepare("INSERT INTO issue_history (issue_id, changed_by, old_status, new_status, remarks) VALUES (?,?,?,?,?)")
                    ->execute([$issue_id, $uid, 'verified', 'assigned', $remarks ?: 'Assigned to department']);

                $db->commit();
                $success = "Issue #$issue_id assigned to department successfully.";
            } catch (Exception $e) {
                $db->rollBack();
                $error = 'Could not assign issue. Please try again.';
            }
        }
    } elseif ($action === 'reject') {
        if (empty($remarks)) {
            $error = 'Rejection reason is required.';
        } else {
            try {
                $db->beginTransaction();

                $db->prepare("UPDATE issues SET status='rejected', updated_at=NOW() WHERE id=?")
                    ->execute([$issue_id]);

                $db->prepare("INSERT INTO issue_history (issue_id, changed_by, old_status, new_status, remarks) VALUES (?,?,?,?,?)")
                    ->execute([$issue_id, $uid, 'verified', 'rejected', $remarks]);

                $db->commit();
                $success = "Issue #$issue_id rejected.";
            } catch (Exception $e) {
                $db->rollBack();
                $error = 'Could not reject issue.';
            }
        }
    }
}

// ─── Fetch verified issues for this ward ───────────────────
$stmt = $db->prepare("
    SELECT i.*, u.name AS citizen_name, u.phone AS citizen_phone,
           w.ward_number,
           (SELECT COUNT(*) FROM issue_images ii WHERE ii.issue_id = i.id) AS image_count
    FROM issues i
    JOIN users u ON i.citizen_id = u.id
    JOIN wards w ON i.ward_id = w.id
    WHERE i.ward_id = ? AND i.status = 'verified'
    ORDER BY i.priority_score DESC, i.support_count DESC, i.created_at ASC
");
$stmt->execute([$wardId]);
$verifiedIssues = $stmt->fetchAll();

// ─── Stats ──────────────────────────────────────────────────
$statStmt = $db->prepare("
    SELECT
        COUNT(*) AS total,
        SUM(priority_score >= 40) AS high_priority,
        SUM(DATEDIFF(NOW(), created_at) > 7) AS aging
    FROM issues
    WHERE ward_id = ? AND status = 'verified'
");
$statStmt->execute([$wardId]);
$stats = $statStmt->fetch();

$depts = $db->query("SELECT * FROM departments WHERE is_active=1 ORDER BY name")->fetchAll();

$pageTitle = 'Verified Issues';
include '../../includes/header.php';
include '../../includes/sidebar.php';
?>
<main class="main-content">
    <div class="page-header">
        <div>
            <h1>Verified Issues</h1>
            <p class="text-muted">Issues you've verified — assign them to the correct department</p>
        </div>
        <div class="header-actions">
            <a href="pending.php" class="btn btn-outline">Pending Review</a>
            <a href="assigned.php" class="btn btn-outline">Assigned</a>
        </div>
    </div>

    <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
    <?php if ($error):   ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <!-- STATS -->
    <div class="stats-grid">
        <div class="stat-card stat-blue">
            <div class="stat-icon">&#9989;</div>
            <div class="stat-value"><?= (int)$stats['total'] ?></div>
            <div class="stat-label">Verified — Awaiting Assignment</div>
        </div>
        <div class="stat-card stat-coral">
            <div class="stat-icon">&#128293;</div>
            <div class="stat-value"><?= (int)$stats['high_priority'] ?></div>
            <div class="stat-label">High Priority</div>
        </div>
        <div class="stat-card stat-amber">
            <div class="stat-icon">&#8987;</div>
            <div class="stat-value"><?= (int)$stats['aging'] ?></div>
            <div class="stat-label">Pending &gt; 7 Days</div>
        </div>
    </div>

    <!-- ISSUE LIST -->
    <?php if (empty($verifiedIssues)): ?>
        <div class="card">
            <div class="empty-state">
                <div>&#127881;</div>
                <p>No verified issues waiting for assignment. All caught up!</p>
                <a href="pending.php" class="btn btn-primary btn-sm">Review Pending Issues</a>
            </div>
        </div>
    <?php else: ?>

        <?php foreach ($verifiedIssues as $i):
            $daysOld = (int)floor((time() - strtotime($i['created_at'])) / 86400);
            $isHigh  = $i['priority_score'] >= 40;
            $isAging = $daysOld > 7;
        ?>
            <div class="card" style="margin-bottom:16px;<?= $isHigh ? 'border-left:4px solid #dc2626' : ($isAging ? 'border-left:4px solid #d97706' : 'border-left:4px solid #2563eb') ?>">
                <div class="card-header" style="align-items:flex-start">
                    <div style="flex:1">
                        <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:6px">
                            <span class="text-muted text-sm">#<?= (int)$i['id'] ?></span>
                            <span class="issue-category"><?= htmlspecialchars($i['category']) ?></span>
                            <span class="text-muted text-sm">&middot;</span>
                            <span class="text-muted text-sm">Ward <?= (int)$i['ward_number'] ?></span>
                            <span class="text-muted text-sm">&middot;</span>
                            <span class="text-muted text-sm"><?= date('d M Y', strtotime($i['created_at'])) ?>
                                <?php if ($daysOld > 0): ?>
                                    (<?= $daysOld ?> day<?= $daysOld > 1 ? 's' : '' ?> ago)
                                <?php endif; ?>
                            </span>

                            <?php if ($isHigh): ?>
                                <span class="badge badge-rejected" title="High priority">&#128293; High Priority</span>
                            <?php endif; ?>
                            <?php if ($isAging): ?>
                                <span class="badge badge-inprogress" title="Pending over a week">&#8987; Aging</span>
                            <?php endif; ?>
                        </div>

                        <h3 style="font-size:16px;font-weight:600;color:var(--gray-800);margin-bottom:4px">
                            <?= htmlspecialchars($i['title']) ?>
                        </h3>

                        <p class="text-sm text-muted" style="margin:0">
                            &#128205; <?= htmlspecialchars($i['location_text'] ?: 'Location not specified') ?>
                            &nbsp;&middot;&nbsp;
                            &#128100; <?= htmlspecialchars($i['citizen_name']) ?>
                            <?php if ($i['citizen_phone']): ?>
                                (<?= htmlspecialchars($i['citizen_phone']) ?>)
                            <?php endif; ?>
                        </p>
                    </div>
                    <span class="badge badge-verified">Verified</span>
                </div>

                <div class="card-body" style="padding:16px 18px">
                    <p style="font-size:13px;color:var(--gray-600);line-height:1.6;margin-bottom:16px">
                        <?= htmlspecialchars($i['description']) ?>
                    </p>

                    <!-- Meta row -->
                    <div style="display:flex;gap:18px;font-size:12px;color:var(--text-m);flex-wrap:wrap;margin-bottom:16px">
                        <span>&#128077; <strong><?= (int)$i['support_count'] ?></strong> supports</span>
                        <span>&#9889; Priority: <strong><?= (int)$i['priority_score'] ?></strong></span>
                        <?php if ($i['image_count'] > 0): ?>
                            <span>&#128247; <?= (int)$i['image_count'] ?> photo<?= $i['image_count'] > 1 ? 's' : '' ?></span>
                        <?php endif; ?>
                    </div>

                    <!-- ACTION FORMS -->
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
                        <!-- ASSIGN -->
                        <form method="POST" style="background:#eff6ff;padding:14px;border-radius:8px;border:1px solid #bfdbfe">
                            <input type="hidden" name="action" value="assign">
                            <input type="hidden" name="issue_id" value="<?= $i['id'] ?>">
                            <strong style="color:#1d4ed8;display:block;margin-bottom:10px">&#128204; Assign to Department</strong>
                            <div class="form-group" style="margin-bottom:10px">
                                <select name="dept_id" class="form-control" required>
                                    <option value="">— Select department —</option>
                                    <?php foreach ($depts as $d): ?>
                                        <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group" style="margin-bottom:10px">
                                <textarea name="remarks" class="form-control" rows="2"
                                    placeholder="Assignment remarks (optional)..."></textarea>
                            </div>
                            <button class="btn btn-sm btn-primary" style="width:100%">Assign</button>
                        </form>

                        <!-- REJECT -->
                        <form method="POST" style="background:#fef2f2;padding:14px;border-radius:8px;border:1px solid #fecaca"
                            onsubmit="return confirm('Reject this issue? This cannot be undone.')">
                            <input type="hidden" name="action" value="reject">
                            <input type="hidden" name="issue_id" value="<?= $i['id'] ?>">
                            <strong style="color:#b91c1c;display:block;margin-bottom:10px">&#10060; Reject Issue</strong>
                            <div class="form-group" style="margin-bottom:10px">
                                <textarea name="remarks" class="form-control" rows="3"
                                    placeholder="Reason for rejection (required)..." required></textarea>
                            </div>
                            <button class="btn btn-sm btn-danger" style="width:100%">Reject</button>
                        </form>
                    </div>

                    <div style="margin-top:14px;text-align:right">
                        <a href="../../public/issue.php?id=<?= (int)$i['id'] ?>"
                            class="btn btn-sm btn-outline">View Full Details</a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>

    <?php endif; ?>
</main>

<?php include '../../includes/footer.php'; ?>
