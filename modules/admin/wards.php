<?php
//modules/admin/users.php
session_start();
require_once '../../config/db.php';
require_once '../../includes/auth_check.php';
requireRole('municipality_admin');

$db = getDB();
$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_ward') {
        $name        = trim($_POST['name'] ?? '');
        $ward_number = (int)($_POST['ward_number'] ?? 0);
        $description = trim($_POST['description'] ?? '');
        if (empty($name) || $ward_number < 1) {
            $error = 'Ward name and number are required.';
        } else {
            $check = $db->prepare("SELECT id FROM wards WHERE ward_number = ?");
            $check->execute([$ward_number]);
            if ($check->fetch()) {
                $error = "Ward number {$ward_number} already exists.";
            } else {
                $db->prepare("INSERT INTO wards (name, ward_number, description) VALUES (?,?,?)")
                   ->execute([$name, $ward_number, $description]);
                $success = "Ward {$ward_number} added successfully.";
            }
        }
    } elseif ($action === 'delete_ward') {
        $id = (int)$_POST['ward_id'];
        $cnt = $db->prepare("SELECT COUNT(*) FROM issues WHERE ward_id = ?");
        $cnt->execute([$id]);
        if ($cnt->fetchColumn() > 0) {
            $error = 'Cannot delete — this ward has existing issues.';
        } else {
            $db->prepare("DELETE FROM wards WHERE id = ?")->execute([$id]);
            $success = 'Ward deleted.';
        }
    }
}

$wards = $db->query("
    SELECT w.*, COUNT(i.id) AS issue_count
    FROM wards w LEFT JOIN issues i ON w.id = i.ward_id
    GROUP BY w.id ORDER BY w.ward_number
")->fetchAll();

$pageTitle = 'Manage Wards';
include '../../includes/header.php';
include '../../includes/sidebar.php';
?>
<main class="main-content">
  <div class="page-header">
    <div><h1>Manage Wards</h1><p class="text-muted"><?= count($wards) ?> wards in the system</p></div>
  </div>
  <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
  <?php if ($error):   ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

  <div class="two-col">
    <div class="card">
      <div class="card-header"><h2>Add New Ward</h2></div>
      <div class="card-body">
        <form method="POST">
          <input type="hidden" name="action" value="add_ward">
          <div class="form-group">
            <label>Ward Number</label>
            <input type="number" name="ward_number" class="form-control" placeholder="e.g. 6" min="1" max="99" required>
          </div>
          <div class="form-group">
            <label>Ward Name</label>
            <input type="text" name="name" class="form-control" placeholder="e.g. Ward 6 Office" required>
          </div>
          <div class="form-group">
            <label>Description <span class="text-muted">(optional)</span></label>
            <textarea name="description" class="form-control" rows="3" placeholder="Area description..."></textarea>
          </div>
          <button type="submit" class="btn btn-primary">Add Ward</button>
        </form>
      </div>
    </div>

    <div class="card">
      <div class="card-header"><h2>All Wards</h2></div>
      <div class="table-wrapper">
        <table class="table">
          <thead><tr><th>Ward #</th><th>Name</th><th>Issues</th><th>Action</th></tr></thead>
          <tbody>
          <?php foreach ($wards as $w): ?>
            <tr>
              <td><strong>Ward <?= $w['ward_number'] ?></strong></td>
              <td><?= htmlspecialchars($w['name']) ?></td>
              <td><?= $w['issue_count'] ?></td>
              <td>
                <?php if ($w['issue_count'] == 0): ?>
                <form method="POST" onsubmit="return confirm('Delete Ward <?= $w['ward_number'] ?>?')">
                  <input type="hidden" name="action" value="delete_ward">
                  <input type="hidden" name="ward_id" value="<?= $w['id'] ?>">
                  <button class="btn btn-sm btn-danger">Delete</button>
                </form>
                <?php else: ?><span class="text-muted text-sm">Has issues</span><?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</main>
<?php include '../../includes/footer.php'; ?>
