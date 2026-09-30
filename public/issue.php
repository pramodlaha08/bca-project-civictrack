<?php
// public/issue.php — Public issue detail with timeline
session_start();
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/config/constants.php';

$db = getDB();
$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    header('Location: home.php');
    exit;
}

$stmt = $db->prepare("
    SELECT i.*, w.ward_number, w.name AS ward_name,
           u.name AS citizen_name,
           d.name AS dept_name
    FROM issues i
    JOIN wards w ON i.ward_id = w.id
    JOIN users u ON i.citizen_id = u.id
    LEFT JOIN assignments a ON a.issue_id = i.id
    LEFT JOIN departments d ON a.department_id = d.id
    WHERE i.id = ?
");
$stmt->execute([$id]);
$issue = $stmt->fetch();

if (!$issue) {
    header('Location: home.php');
    exit;
}

$isLoggedIn = isset($_SESSION['user_id']);
$role       = $_SESSION['user_role'] ?? '';

// Hide pending/rejected from non-owner
if (!$isLoggedIn && in_array($issue['status'], ['pending', 'rejected'])) {
    header('Location: home.php');
    exit;
}
if (
    $isLoggedIn && $role === 'citizen'
    && in_array($issue['status'], ['pending', 'rejected'])
    && $issue['citizen_id'] != $_SESSION['user_id']
) {
    header('Location: home.php');
    exit;
}

// Timeline
$hist = $db->prepare("
    SELECT h.*, u.name AS actor_name, u.role AS actor_role
    FROM issue_history h
    JOIN users u ON h.changed_by = u.id
    WHERE h.issue_id = ?
    ORDER BY h.created_at ASC
");
$hist->execute([$id]);
$history = $hist->fetchAll();

// Images
$imgs = $db->prepare("SELECT * FROM issue_images WHERE issue_id = ? ORDER BY created_at ASC");
$imgs->execute([$id]);
$images = $imgs->fetchAll();

// Progress updates
$prog = $db->prepare("
    SELECT p.*, u.name AS officer_name
    FROM progress_updates p
    JOIN users u ON p.officer_id = u.id
    WHERE p.issue_id = ?
    ORDER BY p.created_at ASC
");
$prog->execute([$id]);
$progress = $prog->fetchAll();

// Supporters count + has current user supported
$hasSupported = false;
if ($isLoggedIn && $role === 'citizen') {
    $s = $db->prepare("SELECT id FROM supports WHERE issue_id = ? AND citizen_id = ?");
    $s->execute([$id, $_SESSION['user_id']]);
    $hasSupported = (bool)$s->fetch();
}

$isOwn = $isLoggedIn && ($issue['citizen_id'] == ($_SESSION['user_id'] ?? 0));
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= htmlspecialchars($issue['title']) ?> — CivicTrack</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/public.css">
</head>

<body class="public-body">

    <header class="public-nav">
        <div class="public-nav-inner">
            <a href="home.php" class="public-brand">
                <span class="brand-logo">&#127963;</span>
                <span class="brand-name">CivicTrack</span>
            </a>
            <div class="public-auth">
                <?php if ($isLoggedIn): ?>
                    <a href="index.php" class="btn btn-sm btn-primary">Dashboard</a>
                    <a href="logout.php" class="btn btn-sm btn-outline">Sign out</a>
                <?php else: ?>
                    <a href="login.php" class="btn btn-sm btn-outline">Sign in</a>
                    <a href="register.php" class="btn btn-sm btn-primary">Register</a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <main class="issue-detail-wrap">
        <a href="home.php" class="back-link">&larr; Back to all issues</a>

        <div class="issue-detail-grid">
            <!-- MAIN COLUMN -->
            <div class="issue-detail-main">
                <div class="issue-detail-head">
                    <span class="badge <?= STATUS_LABELS[$issue['status']]['class'] ?>">
                        <?= STATUS_LABELS[$issue['status']]['label'] ?>
                    </span>
                    <span class="issue-category"><?= htmlspecialchars($issue['category']) ?></span>
                </div>

                <h1 class="issue-detail-title"><?= htmlspecialchars($issue['title']) ?></h1>

                <div class="issue-detail-meta">
                    <span>&#128205; <?= htmlspecialchars($issue['location_text'] ?: 'Location not specified') ?></span>
                    <span>&#127968; Ward <?= $issue['ward_number'] ?></span>
                    <span>&#128100; <?= htmlspecialchars($issue['citizen_name']) ?></span>
                    <span>&#128197; <?= date('d M Y, h:i A', strtotime($issue['created_at'])) ?></span>
                </div>

                <div class="issue-detail-body">
                    <h3>Description</h3>
                    <p><?= nl2br(htmlspecialchars($issue['description'])) ?></p>
                </div>

                <?php if (!empty($images)): ?>
                    <div class="issue-detail-body">
                        <h3>Photos</h3>
                        <div class="photo-grid">
                            <?php foreach ($images as $img): ?>
                                <a href="<?= UPLOADS_URL . 'issues/' . htmlspecialchars($img['filename']) ?>" target="_blank">
                                    <img src="<?= UPLOADS_URL . 'issues/' . htmlspecialchars($img['filename']) ?>" alt="Issue photo">
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($progress)): ?>
                    <div class="issue-detail-body">
                        <h3>Work Progress</h3>
                        <?php foreach ($progress as $p): ?>
                            <div class="progress-item">
                                <div class="progress-head">
                                    <strong><?= htmlspecialchars($p['officer_name']) ?></strong>
                                    <span class="text-muted text-sm"><?= date('d M Y, h:i A', strtotime($p['created_at'])) ?></span>
                                </div>
                                <p><?= nl2br(htmlspecialchars($p['remarks'])) ?></p>
                                <?php if ($p['progress_image']): ?>
                                    <img src="<?= UPLOADS_URL . 'progress/' . htmlspecialchars($p['progress_image']) ?>"
                                        class="progress-img" alt="Progress photo">
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- SIDEBAR -->
            <aside class="issue-detail-side">

                <!-- SUPPORT BOX -->
                <div class="side-card support-card">
                    <div class="support-big">&#128077;</div>
                    <div class="support-big-count"><?= (int)$issue['support_count'] ?></div>
                    <p class="support-big-label">citizens support this issue</p>

                    <button class="btn btn-primary btn-block support-btn-lg <?= $hasSupported ? 'supported' : '' ?>"
                        data-issue-id="<?= $issue['id'] ?>"
                        <?= $isOwn ? 'disabled' : '' ?>
                        <?= in_array($issue['status'], ['closed', 'rejected']) ? 'disabled' : '' ?>>
                        <?php if ($isOwn): ?>
                            Your own report
                        <?php elseif (in_array($issue['status'], ['closed', 'rejected'])): ?>
                            Issue closed
                        <?php elseif ($hasSupported): ?>
                            &#10003; You supported this
                        <?php else: ?>
                            Support This Issue
                        <?php endif; ?>
                    </button>

                    <div class="support-meta">
                        <div><span>Priority</span><strong><?= (int)$issue['priority_score'] ?></strong></div>
                        <div><span>Department</span><strong><?= htmlspecialchars($issue['dept_name'] ?? 'Not assigned') ?></strong></div>
                    </div>
                </div>

                <!-- TIMELINE -->
                <div class="side-card">
                    <h3 class="side-title">Activity Timeline</h3>
                    <ul class="timeline">
                        <?php foreach ($history as $h): ?>
                            <li>
                                <div class="timeline-dot"></div>
                                <div class="timeline-body">
                                    <strong>
                                        <?= $h['old_status']
                                            ? ucfirst(str_replace('_', ' ', $h['old_status'])) . ' &rarr; '
                                            : '' ?>
                                        <?= ucfirst(str_replace('_', ' ', $h['new_status'])) ?>
                                    </strong>
                                    <div class="text-sm text-muted">
                                        by <?= htmlspecialchars($h['actor_name']) ?>
                                        &middot; <?= date('d M Y, h:i A', strtotime($h['created_at'])) ?>
                                    </div>
                                    <?php if ($h['remarks']): ?>
                                        <p class="timeline-remarks"><?= htmlspecialchars($h['remarks']) ?></p>
                                    <?php endif; ?>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>

            </aside>
        </div>
    </main>

    <footer class="public-footer">
        <p><strong>CivicTrack</strong> &mdash; Local Problem Reporting System</p>
    </footer>

    <?php if (!$isLoggedIn): ?>
        <div id="loginModal" class="modal-backdrop" style="display:none">
            <div class="modal">
                <h3>Sign in to support this issue</h3>
                <p>Your support helps prioritize issues in your community.</p>
                <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:16px">
                    <button class="btn btn-outline" onclick="document.getElementById('loginModal').style.display='none'">Cancel</button>
                    <a href="login.php" class="btn btn-primary">Sign in</a>
                    <a href="register.php" class="btn btn-primary">Register</a>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <script>
        const IS_LOGGED_IN = <?= $isLoggedIn ? 'true' : 'false' ?>;

        document.querySelectorAll('.support-btn-lg').forEach(btn => {
            btn.addEventListener('click', async () => {
                if (btn.disabled) return;
                if (!IS_LOGGED_IN) {
                    document.getElementById('loginModal').style.display = 'flex';
                    return;
                }
                btn.disabled = true;

                try {
                    const res = await fetch('../api/support.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded'
                        },
                        body: 'issue_id=' + encodeURIComponent(btn.dataset.issueId)
                    });
                    const data = await res.json();

                    if (data.success) {
                        document.querySelector('.support-big-count').textContent = data.count;
                        btn.classList.add('supported');
                        btn.innerHTML = '&#10003; You supported this';
                    } else {
                        alert(data.message || 'Could not support.');
                        btn.disabled = false;
                    }
                } catch (e) {
                    alert('Network error.');
                    btn.disabled = false;
                }
            });
        });
    </script>

</body>

</html>