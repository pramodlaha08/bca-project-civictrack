<?php
// public/home.php — Public home, no login required
session_start();
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/config/constants.php';

$db = getDB();

$isLoggedIn = isset($_SESSION['user_id']);
$role       = $_SESSION['user_role'] ?? '';

// ─── Stats ────────────────────────────────────────────────────
$totalIssues  = (int)$db->query("SELECT COUNT(*) FROM issues")->fetchColumn();
$resolved     = (int)$db->query("SELECT COUNT(*) FROM issues WHERE status IN ('resolved','closed')")->fetchColumn();
$inProgress   = (int)$db->query("SELECT COUNT(*) FROM issues WHERE status IN ('assigned','in_progress','verified')")->fetchColumn();
$pending      = (int)$db->query("SELECT COUNT(*) FROM issues WHERE status='pending'")->fetchColumn();
$totalSupports = (int)$db->query("SELECT COALESCE(SUM(support_count),0) FROM issues")->fetchColumn();
$totalWards   = (int)$db->query("SELECT COUNT(*) FROM wards")->fetchColumn();
$resolutionRate = $totalIssues > 0 ? round(($resolved / $totalIssues) * 100) : 0;

// ─── Filters ──────────────────────────────────────────────────
$filterCategory = $_GET['category'] ?? '';
$filterWard     = (int)($_GET['ward'] ?? 0);
$filterStatus   = $_GET['status']   ?? '';
$search         = trim($_GET['q']    ?? '');

$where  = ["i.status != 'rejected'"];
$params = [];

// Citizens only see their own pending; public sees verified+ (privacy)
if (!$isLoggedIn) {
    $where[] = "i.status NOT IN ('pending','rejected')";
} elseif ($role === 'citizen') {
    // logged-in citizen can see their own pending + all public issues
    $where[] = "(i.status NOT IN ('pending','rejected') OR i.citizen_id = ?)";
    $params[] = $_SESSION['user_id'];
}

if ($filterCategory) {
    $where[] = "i.category = ?";
    $params[] = $filterCategory;
}
if ($filterWard) {
    $where[] = "i.ward_id = ?";
    $params[] = $filterWard;
}
if ($filterStatus) {
    $where[] = "i.status = ?";
    $params[] = $filterStatus;
}
if ($search) {
    $where[] = "(i.title LIKE ? OR i.description LIKE ? OR i.location_text LIKE ?)";
    $like = "%$search%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$sql = "SELECT i.*, w.ward_number, w.name AS ward_name,
               u.name AS citizen_name,
               (SELECT COUNT(*) FROM issue_images ii WHERE ii.issue_id = i.id) AS image_count
        FROM issues i
        JOIN wards w ON i.ward_id = w.id
        JOIN users u ON i.citizen_id = u.id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY i.support_count DESC, i.priority_score DESC, i.created_at DESC
        LIMIT 30";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$issues = $stmt->fetchAll();

// ─── Category counts (for filter pills) ──────────────────────
$catCounts = $db->query("
    SELECT category, COUNT(*) AS cnt
    FROM issues
    WHERE status NOT IN ('pending','rejected')
    GROUP BY category
")->fetchAll(PDO::FETCH_KEY_PAIR);

$wards = $db->query("SELECT * FROM wards ORDER BY ward_number")->fetchAll();

// ─── Which issues has the logged-in citizen already supported? ──
$supportedIds = [];
if ($isLoggedIn && $role === 'citizen') {
    $s = $db->prepare("SELECT issue_id FROM supports WHERE citizen_id = ?");
    $s->execute([$_SESSION['user_id']]);
    $supportedIds = array_column($s->fetchAll(), 'issue_id');
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>CivicTrack — Report & Track Local Issues</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/public.css">
</head>

<body class="public-body">

    <!-- PUBLIC TOPNAV -->
    <header class="public-nav">
        <div class="public-nav-inner">
            <a href="home.php" class="public-brand">
                <span class="brand-logo">&#127963;</span>
                <span class="brand-name">CivicTrack</span>
            </a>
            <nav class="public-menu">
                <a href="home.php" class="active">Home</a>
                <a href="#issues">Issues</a>
                <a href="#how">How it works</a>
            </nav>
            <div class="public-auth">
                <?php if ($isLoggedIn): ?>
                    <span class="nav-user">
                        &#128100; <?= htmlspecialchars($_SESSION['user_name']) ?>
                    </span>
                    <a href="index.php" class="btn btn-sm btn-primary">My Dashboard</a>
                    <a href="logout.php" class="btn btn-sm btn-outline">Sign out</a>
                <?php else: ?>
                    <a href="login.php" class="btn btn-sm btn-outline">Sign in</a>
                    <a href="register.php" class="btn btn-sm btn-primary">Register</a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <!-- HERO -->
    <section class="hero">
        <div class="hero-inner">
            <h1>Your city. Your voice. <span>Real change.</span></h1>
            <p>Report local problems — potholes, garbage, broken lights — and track them until they're fixed. Support issues that matter to your neighborhood.</p>
            <div class="hero-actions">
                <?php if ($isLoggedIn && $role === 'citizen'): ?>
                    <a href="../modules/citizen/report.php" class="btn btn-primary btn-lg">+ Report an Issue</a>
                    <a href="#issues" class="btn btn-outline btn-lg">Browse Issues</a>
                <?php elseif ($isLoggedIn): ?>
                    <a href="index.php" class="btn btn-primary btn-lg">Go to Dashboard</a>
                <?php else: ?>
                    <a href="register.php" class="btn btn-primary btn-lg">Get Started — It's Free</a>
                    <a href="#issues" class="btn btn-outline btn-lg">Browse Issues</a>
                <?php endif; ?>
            </div>

            <div class="hero-stats">
                <div class="hero-stat">
                    <strong><?= number_format($totalIssues) ?></strong>
                    <span>Total Reports</span>
                </div>
                <div class="hero-stat">
                    <strong><?= number_format($resolved) ?></strong>
                    <span>Resolved</span>
                </div>
                <div class="hero-stat">
                    <strong><?= $resolutionRate ?>%</strong>
                    <span>Resolution Rate</span>
                </div>
                <div class="hero-stat">
                    <strong><?= number_format($totalSupports) ?></strong>
                    <span>Citizen Supports</span>
                </div>
            </div>
        </div>
    </section>

    <!-- HOW IT WORKS -->
    <section id="how" class="how-section">
        <h2>How CivicTrack Works</h2>
        <p class="section-sub">Four steps from report to resolution</p>
        <div class="how-grid">
            <div class="how-card">
                <div class="how-num">1</div>
                <h3>Report</h3>
                <p>Citizens submit issues with a photo, location, and description. Takes less than a minute.</p>
            </div>
            <div class="how-card">
                <div class="how-num">2</div>
                <h3>Verify</h3>
                <p>The local ward office reviews each report to confirm it's genuine and not a duplicate.</p>
            </div>
            <div class="how-card">
                <div class="how-num">3</div>
                <h3>Assign</h3>
                <p>Verified issues are routed to the correct department — Roads, Sanitation, Water, etc.</p>
            </div>
            <div class="how-card">
                <div class="how-num">4</div>
                <h3>Resolve</h3>
                <p>Departments complete the work, upload proof, and the ward office confirms closure.</p>
            </div>
        </div>
    </section>

    <!-- ISSUES FEED -->
    <section id="issues" class="issues-section">
        <div class="issues-header">
            <div>
                <h2>Community Issues</h2>
                <p class="section-sub">Support issues that matter — the more support, the higher the priority</p>
            </div>
        </div>

        <!-- FILTERS -->
        <form method="GET" class="filters-bar">
            <input type="text" name="q" class="form-control" placeholder="Search issues..." value="<?= htmlspecialchars($search) ?>">

            <select name="category" class="form-control">
                <option value="">All Categories</option>
                <?php foreach (CATEGORIES as $c): ?>
                    <option value="<?= $c ?>" <?= $filterCategory === $c ? 'selected' : '' ?>><?= $c ?></option>
                <?php endforeach; ?>
            </select>

            <select name="ward" class="form-control">
                <option value="">All Wards</option>
                <?php foreach ($wards as $w): ?>
                    <option value="<?= $w['id'] ?>" <?= $filterWard == $w['id'] ? 'selected' : '' ?>>
                        Ward <?= $w['ward_number'] ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <select name="status" class="form-control">
                <option value="">All Status</option>
                <?php foreach (STATUS_LABELS as $k => $v): if (in_array($k, ['pending', 'rejected'])) continue; ?>
                    <option value="<?= $k ?>" <?= $filterStatus === $k ? 'selected' : '' ?>><?= $v['label'] ?></option>
                <?php endforeach; ?>
            </select>

            <button class="btn btn-primary">Filter</button>
            <?php if ($filterCategory || $filterWard || $filterStatus || $search): ?>
                <a href="home.php" class="btn btn-outline">Clear</a>
            <?php endif; ?>
        </form>

        <!-- CATEGORY PILLS -->
        <div class="cat-pills">
            <a href="home.php#issues" class="cat-pill <?= !$filterCategory ? 'active' : '' ?>">All</a>
            <?php foreach (CATEGORIES as $c):
                $cnt = $catCounts[$c] ?? 0;
                if ($cnt === 0 && $filterCategory !== $c) continue; ?>
                <a href="home.php?category=<?= urlencode($c) ?>#issues"
                    class="cat-pill <?= $filterCategory === $c ? 'active' : '' ?>">
                    <?= $c ?> <span class="pill-count"><?= $cnt ?></span>
                </a>
            <?php endforeach; ?>
        </div>

        <!-- ISSUE GRID -->
        <?php if (empty($issues)): ?>
            <div class="empty-state" style="background:#fff;border-radius:12px;padding:60px 20px">
                <div style="font-size:48px">&#128269;</div>
                <p>No issues match your filters.</p>
            </div>
        <?php else: ?>
            <div class="issue-grid">
                <?php foreach ($issues as $i):
                    $hasSupported = in_array($i['id'], $supportedIds);
                    $isOwn = $isLoggedIn && ($i['citizen_id'] == ($_SESSION['user_id'] ?? 0));
                ?>
                    <article class="issue-card" data-issue-id="<?= $i['id'] ?>">
                        <div class="issue-card-top">
                            <span class="badge <?= STATUS_LABELS[$i['status']]['class'] ?>">
                                <?= STATUS_LABELS[$i['status']]['label'] ?>
                            </span>
                            <span class="issue-category"><?= htmlspecialchars($i['category']) ?></span>
                        </div>

                        <h3 class="issue-card-title">
                            <a href="issue.php?id=<?= $i['id'] ?>">
                                <?= htmlspecialchars($i['title']) ?>
                            </a>
                        </h3>

                        <p class="issue-card-desc">
                            <?= htmlspecialchars(mb_strimwidth($i['description'], 0, 110, '…')) ?>
                        </p>

                        <div class="issue-card-meta">
                            <span>&#128205; Ward <?= $i['ward_number'] ?></span>
                            <span>&#128197; <?= date('d M Y', strtotime($i['created_at'])) ?></span>
                        </div>

                        <div class="issue-card-footer">
                            <button class="support-btn <?= $hasSupported ? 'supported' : '' ?>"
                                data-issue-id="<?= $i['id'] ?>"
                                <?= $isOwn ? 'disabled title="You cannot support your own issue"' : '' ?>>
                                <span class="heart">&#128077;</span>
                                <span class="support-count"><?= (int)$i['support_count'] ?></span>
                                <span class="support-label"><?= $hasSupported ? 'Supported' : 'Support' ?></span>
                            </button>
                            <a href="issue.php?id=<?= $i['id'] ?>" class="btn btn-sm btn-outline">View</a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <footer class="public-footer">
        <p><strong>CivicTrack</strong> &mdash; Local Problem Reporting System</p>
        <p class="text-sm">BCA Project I &middot; Tribhuvan University &middot; &copy; <?= date('Y') ?></p>
    </footer>

    <?php if (!$isLoggedIn): ?>
        <!-- Login prompt modal -->
        <div id="loginModal" class="modal-backdrop" style="display:none">
            <div class="modal">
                <h3>Sign in to support this issue</h3>
                <p>Your support helps prioritize issues in your community.</p>
                <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:16px">
                    <button class="btn btn-outline" onclick="closeModal()">Cancel</button>
                    <a href="login.php" class="btn btn-primary">Sign in</a>
                    <a href="register.php" class="btn btn-primary">Register</a>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <script>
        const IS_LOGGED_IN = <?= $isLoggedIn ? 'true' : 'false' ?>;

        document.querySelectorAll('.support-btn').forEach(btn => {
            btn.addEventListener('click', async () => {
                if (btn.disabled) return;

                if (!IS_LOGGED_IN) {
                    document.getElementById('loginModal').style.display = 'flex';
                    return;
                }

                const issueId = btn.dataset.issueId;
                btn.disabled = true;

                try {
                    const res = await fetch('../api/support.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded'
                        },
                        body: 'issue_id=' + encodeURIComponent(issueId)
                    });
                    const data = await res.json();

                    if (data.success) {
                        btn.classList.add('supported');
                        btn.querySelector('.support-count').textContent = data.count;
                        btn.querySelector('.support-label').textContent = 'Supported';
                    } else {
                        alert(data.message || 'Could not support this issue.');
                        btn.disabled = false;
                    }
                } catch (err) {
                    alert('Network error. Please try again.');
                    btn.disabled = false;
                }
            });
        });

        function closeModal() {
            document.getElementById('loginModal').style.display = 'none';
        }
    </script>

</body>

</html>