<?php
//modules/citizen/report.php
session_start();
require_once '../../config/db.php';
require_once '../../config/constants.php';
require_once '../../includes/auth_check.php';
requireRole('citizen');

$db      = getDB();
$uid     = $_SESSION['user_id'];
$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title    = trim($_POST['title']    ?? '');
    $category = trim($_POST['category'] ?? '');
    $ward_id  = (int)($_POST['ward_id'] ?? 0);
    $location = trim($_POST['location'] ?? '');
    $desc     = trim($_POST['description'] ?? '');

    if (empty($title) || empty($category) || !$ward_id || empty($desc)) {
        $error = 'Please fill in all required fields.';
    } else {
        // Calculate simple priority score
        $catWeights = ['Water Leakage'=>50,'Road Damage'=>40,'Street Light'=>35,
                       'Drainage'=>35,'Garbage'=>30,'Public Safety'=>30,
                       'Footpath'=>20,'Public Park'=>20,'Other'=>10];
        $priority = $catWeights[$category] ?? 10;

        $stmt = $db->prepare("
            INSERT INTO issues (citizen_id, ward_id, category, title, description, location_text, status, priority_score)
            VALUES (?,?,?,?,?,?,'pending',?)
        ");
        $stmt->execute([$uid, $ward_id, $category, $title, $desc, $location, $priority]);
        $newId = $db->lastInsertId();

        // Log to history
        $db->prepare("INSERT INTO issue_history (issue_id, changed_by, old_status, new_status, remarks) VALUES (?,?,NULL,'pending','Issue submitted by citizen')")
           ->execute([$newId, $uid]);

        $success = "Issue reported successfully! Your issue ID is #$newId. Ward office will review it shortly.";
    }
}

$wards = $db->query("SELECT * FROM wards ORDER BY ward_number")->fetchAll();

$pageTitle = 'Report Issue';
include '../../includes/header.php';
include '../../includes/sidebar.php';
?>
<main class="main-content">
  <div class="page-header">
    <div><h1>Report a New Issue</h1><p class="text-muted">Fill in the details — ward office will review your report</p></div>
  </div>

  <?php if ($success): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?> <a href="dashboard.php">View my reports</a></div>
  <?php endif; ?>
  <?php if ($error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <div class="card" style="max-width:640px">
    <div class="card-header"><h2>Issue Details</h2></div>
    <div class="card-body">
      <form method="POST">
        <div class="form-group">
          <label>Issue Title <span style="color:#dc2626">*</span></label>
          <input type="text" name="title" class="form-control"
                 placeholder="Brief title, e.g. Large pothole near school" required
                 value="<?= htmlspecialchars($_POST['title'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label>Category <span style="color:#dc2626">*</span></label>
          <select name="category" class="form-control" required>
            <option value="">— Select category —</option>
            <?php foreach (CATEGORIES as $cat): ?>
            <option value="<?= $cat ?>" <?= ($_POST['category'] ?? '') === $cat ? 'selected' : '' ?>>
              <?= $cat ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Ward <span style="color:#dc2626">*</span></label>
          <select name="ward_id" class="form-control" required>
            <option value="">— Select ward —</option>
            <?php foreach ($wards as $w): ?>
            <option value="<?= $w['id'] ?>" <?= ($_POST['ward_id'] ?? '') == $w['id'] ? 'selected' : '' ?>>
              Ward <?= $w['ward_number'] ?> — <?= htmlspecialchars($w['name']) ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Location / Landmark <span style="color:#dc2626">*</span></label>
          <input type="text" name="location" class="form-control"
                 placeholder="e.g. Near Janapath School, Marga 4"
                 value="<?= htmlspecialchars($_POST['location'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label>Description <span style="color:#dc2626">*</span></label>
          <textarea name="description" class="form-control" rows="5"
                    placeholder="Describe the problem in detail — how long it has been there, impact on public, etc." required><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
        </div>
        <div style="display:flex;gap:12px">
          <button type="submit" class="btn btn-primary">Submit Report</button>
          <a href="dashboard.php" class="btn btn-outline">Cancel</a>
        </div>
      </form>
    </div>
  </div>
</main>
<?php include '../../includes/footer.php'; ?>
