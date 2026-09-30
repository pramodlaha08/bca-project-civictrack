<?php
// modules/citizen/my_issues.php
session_start();
require_once '../../config/db.php';
require_once '../../config/constants.php';
require_once '../../includes/auth_check.php';
requireRole('citizen');

$db  = getDB();
$uid = $_SESSION['user_id'];

// ─── Filter ──────────────────────────────────────────────────
$filter = $_GET['status'] ?? 'all';
$validFilters = ['all', 'pending', 'verified', 'assigned', 'in_progress', 'resolved', 'closed', 'rejected'];
if (!in_array($filter, $validFilters, true)) $filter = 'all';

$where  = "WHERE i.citizen_id = ?";
$params = [$uid];

if ($filter !== 'all') {
    $where   .= " AND i.status = ?";
    $params[] = $filter;
}

$stmt = $db->prepare("
    SELECT i.*, w.ward_number, w.name AS ward_name,
           (SELECT COUNT(*) FROM issue_images ii WHERE ii.issue_id = i.id) AS image_count,
           (SELECT COUNT(*) FROM progress_updates pu WHERE pu.issue_id = i.id) AS progress_count
    FROM issues i
    JOIN wards w ON i.ward_id = w.id
    {$where}
    ORDER BY i.created_at DESC
");
$stmt->execute($params);
$issues = $stmt->fetchAll();

// ─── Stats (all issues, regardless of filter) ────────────────
$statsStmt = $db->prepare("
    SELECT
        COUNT(*) AS total,
        SUM(status = 'pending')                       AS pending,
        SUM(status IN ('verified','assigned'))        AS in_queue,
        SUM(status = 'in_progress')                   AS in_progress,
        SUM(status IN ('resolved','closed'))          AS resolved,
        SUM(status = 'rejected')                      AS rejected,
        COALESCE(SUM(support_count), 0)               AS total_supports
    FROM issues
    WHERE citizen_id = ?
");
$statsStmt->execute([$uid]);
$stats = $statsStmt->fetch();

$pageTitle = 'My Reports';
include '../../includes/header.php';
include '../../includes/sidebar.php';
?>

<main class="main-content">
    <div class="page-header">
        <div>
            <h1>My Reports</h1>
            <p class="text-muted">All issues you have reported, with their current status</p>
        </div>
        <div class="header-actions">
            <a href="report.php" class="btn btn-primary">+ Report New Issue</a>
        </div>
    </div>

    <!-- STATS -->
    <div class="stats-grid">
        <div class="stat-card stat-blue">
            <div class="stat-icon">&#128203;</div>
            <div class="stat-value"><?= (int)$stats['total'] ?></div>
            <div class="stat-label">Total Reports</div>
        </div>
        <div class="stat-card stat-amber">
            <div class="stat-icon">&#9203;</div>
            <div class="stat-value"><?= (int)$stats['pending'] + (int)$stats['in_queue'] ?></div>
            <div class="stat-label">Awaiting Action</div>
        </div>
        <div class="stat-card stat-teal">
            <div class="stat-icon">&#128295;</div>
            <div class="stat-value"><?= (int)$stats['in_progress'] ?></div>
            <div class="stat-label">In Progress</div>
        </div>
        <div class="stat-card stat-green">
            <div class="stat-icon">&#9989;</div>
            <div class="stat-value"><?= (int)$stats['resolved'] ?></div>
            <div class="stat-label">Resolved</div>
        </div>
        <div class="stat-card stat-purple">
            <div class="stat-icon">&#128077;</div>
            <div class="stat-value"><?= (int)$stats['total_supports'] ?></div>
            <div class="stat-label">Total Supports Received</div>
        </div>
        <div class="stat-card stat-coral">
            <div class="stat-icon">&#10060;</div>
            <div class="stat-value"><?= (int)$stats['rejected'] ?></div>
            <div class="stat-label">Rejected</div>
        </div>
    </div>

    <!-- FILTER TABS -->
    <div class="filter-tabs" style="margin-bottom:16px">
        <a href="my_issues.php" class="filter-tab <?= $filter === 'all' ? 'active' : '' ?>">
            All (<?= (int)$stats['total'] ?>)
        </a>
        <a href="my_issues.php?status=pending" class="filter-tab <?= $filter === 'pending' ? 'active' : '' ?>">
            Pending (<?= (int)$stats['pending'] ?>)
        </a>
        <a href="my_issues.php?status=verified" class="filter-tab <?= $filter === 'verified' ? 'active' : '' ?>">
            Verified
        </a>
        <a href="my_issues.php?status=assigned" class="filter-tab <?= $filter === 'assigned' ? 'active' : '' ?>">
            Assigned
        </a>
        <a href="my_issues.php?status=in_progress" class="filter-tab <?= $filter === 'in_progress' ? 'active' : '' ?>">
            In Progress
        </a>
        <a href="my_issues.php?status=resolved" class="filter-tab <?= $filter === 'resolved' ? 'active' : '' ?>">
            Resolved
        </a>
        <a href="my_issues.php?status=closed" class="filter-tab <?= $filter === 'closed' ? 'active' : '' ?>">
            Closed
        </a>
        <a href="my_issues.php?status=rejected" class="filter-tab <?= $filter === 'rejected' ? 'active' : '' ?>">
            Rejected (<?= (int)$stats['rejected'] ?>)
        </a>
    </div>

    <!-- ISSUES LIST -->
    <?php if (empty($issues)): ?>
        <div class="card">
            <div class="empty-state">
                <div>&#128221;</div>
                <p>
                    <?php if ($filter === 'all'): ?>
                        You haven't reported any issues yet.
                    <?php else: ?>
                        No reports in this category.
                    <?php endif; ?>
                </p>
                <a href="report.php" class="btn btn-primary btn-sm">Report your first issue</a>
            </div>
        </div>
    <?php else: ?>

        <?php foreach ($issues as $i): ?>
            <div class="card" style="margin-bottom:14px">
                <div class="card-header" style="align-items:flex-start">
                    <div style="flex:1">
                        <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px;flex-wrap:wrap">
                            <span class="text-muted text-sm">#<?= (int)$i['id'] ?></span>
                            <span class="issue-category"><?= htmlspecialchars($i['category']) ?></span>
                            <span class="text-muted text-sm">&middot;</span>
                            <span class="text-muted text-sm">Ward <?= (int)$i['ward_number'] ?></span>
                            <span class="text-muted text-sm">&middot;</span>
                            <span class="text-muted text-sm"><?= date('d M Y', strtotime($i['created_at'])) ?></span>
                        </div>
                        <h3 style="font-size:15px;font-weight:600;color:var(--gray-800);margin-bottom:4px">
                            <?= htmlspecialchars($i['title']) ?>
                        </h3>
                        <p class="text-sm text-muted" style="margin:0">
                            &#128205; <?= htmlspecialchars($i['location_text'] ?: 'Location not specified') ?>
                        </p>
                    </div>
                    <div style="text-align:right;flex-shrink:0">
                        <span class="badge <?= STATUS_LABELS[$i['status']]['class'] ?>">
                            <?= STATUS_LABELS[$i['status']]['label'] ?>
                        </span>
                    </div>
                </div>

                <div class="card-body" style="padding:14px 18px">
                    <p style="font-size:13px;color:var(--gray-600);line-height:1.6;margin-bottom:14px">
                        <?= htmlspecialchars(mb_strimwidth($i['description'], 0, 200, '…')) ?>
                    </p>

                    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px">
                        <div style="display:flex;gap:16px;font-size:12px;color:var(--text-m);flex-wrap:wrap">
                            <span title="Citizens supporting this issue">
                                &#128077; <strong><?= (int)$i['support_count'] ?></strong> supports
                            </span>
                            <span title="Priority score">
                                &#9889; Priority: <strong><?= (int)$i['priority_score'] ?></strong>
                            </span>
                            <?php if ($i['image_count'] > 0): ?>
                                <span>&#128247; <?= (int)$i['image_count'] ?> photo<?= $i['image_count'] > 1 ? 's' : '' ?></span>
                            <?php endif; ?>
                            <?php if ($i['progress_count'] > 0): ?>
                                <span>&#128221; <?= (int)$i['progress_count'] ?> progress update<?= $i['progress_count'] > 1 ? 's' : '' ?></span>
                            <?php endif; ?>
                        </div>

                        <div style="display:flex;gap:8px">
                            <a href="../../public/issue.php?id=<?= (int)$i['id'] ?>"
                                class="btn btn-sm btn-outline">View Details</a>
                            <?php if ($i['status'] === 'pending'): ?>
                                <span class="text-muted text-sm" style="align-self:center">Under review</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if ($i['status'] === 'rejected'): ?>
                        <div class="alert alert-danger" style="margin-top:12px;margin-bottom:0;font-size:13px">
                            <strong>Rejected:</strong> This report was rejected by the ward office.
                            <a href="../../public/issue.php?id=<?= (int)$i['id'] ?>">View reason</a>
                        </div>
                    <?php endif; ?>

                    <?php if ($i['status'] === 'resolved'): ?>
                        <div class="alert alert-success" style="margin-top:12px;margin-bottom:0;font-size:13px">
                            <strong>Resolved!</strong> The department has completed work. Awaiting ward office closure.
                            <a href="../../public/issue.php?id=<?= (int)$i['id'] ?>">View progress</a>
                        </div>
                    <?php endif; ?>

                    <?php if ($i['status'] === 'closed'): ?>
                        <div class="alert alert-success" style="margin-top:12px;margin-bottom:0;font-size:13px;background:var(--teal-light);color:#0f766e;border-color:#99f6e4">
                            <strong>&#10004; Closed.</strong> Thank you for helping improve your ward!
                            <a href="../../public/issue.php?id=<?= (int)$i['id'] ?>">View final status</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>

    <?php endif; ?>
</main>

<?php include '../../includes/footer.php'; ?>