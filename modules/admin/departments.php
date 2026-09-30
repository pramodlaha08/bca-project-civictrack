<?php
//modules/admin/departments.php
session_start();
require_once '../../config/db.php';
require_once '../../includes/auth_check.php';
requireRole('municipality_admin');

$db = getDB();
$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add_dept') {
        $name = trim($_POST['name'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        if (empty($name)) { $error = 'Department name is required.'; }
        else {
            $db->prepare("INSERT INTO departments (name, description) VALUES (?,?)")->execute([$name, $desc]);
            $success = "Department '{$name}' added.";
        }
    } elseif ($action === 'toggle_dept') {
        $id  = (int)$_POST['dept_id'];
        $cur = (int)$_POST['is_active'];
        $db->prepare("UPDATE departments SET is_active = ? WHERE id = ?")->execute([$cur ? 0 : 1, $id]);
        $success = 'Department status updated.';
    }
}

$departments = $db->query("
    SELECT d.*, COUNT(u.id) AS officer_count
    FROM departments d
    LEFT JOIN users u ON u.department_id = d.id AND u.role = 'dept_officer'
    GROUP BY d.id ORDER BY d.name
")->fetchAll();

$pageTitle = 'Departments';
include '../../includes/header.php';
include '../../includes/sidebar.php';
?>
<main class="main-content">
  <div class="page-header">
    <div><h1>Manage Departments</h1><p class="text-muted"><?= count($departments) ?> departments configured</p></div>
  </div>
  <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
  <?php if ($error):   ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

  <div class="two-col">
    <div class="card">
      <div class="card-header"><h2>Add Department</h2></div>
      <div class="card-body">
        <form method="POST">
          <input type="hidden" name="action" value="add_dept">
          <div class="form-group">
            <label>Department Name</label>
            <input type="text" name="name" class="form-control" placeholder="e.g. Health Department" required>
          </div>
          <div class="form-group">
            <label>Description</label>
            <textarea name="description" class="form-control" rows="3" placeholder="What this department handles..."></textarea>
          </div>
          <button type="submit" class="btn btn-primary">Add Department</button>
        </form>
      </div>
    </div>

    <div class="card">
      <div class="card-header"><h2>All Departments</h2></div>
      <div class="dept-list">
        <?php foreach ($departments as $d): ?>
        <div class="dept-row <?= $d['is_active'] ? '' : 'dept-inactive' ?>">
          <div class="dept-info">
            <strong><?= htmlspecialchars($d['name']) ?></strong>
            <span class="text-muted text-sm"><?= htmlspecialchars($d['description'] ?: 'No description') ?></span>
            <span class="text-sm"><?= $d['officer_count'] ?> officer(s)</span>
          </div>
          <div class="dept-actions">
            <span class="badge <?= $d['is_active'] ? 'badge-resolved' : 'badge-rejected' ?>">
              <?= $d['is_active'] ? 'Active' : 'Inactive' ?>
            </span>
            <form method="POST" style="display:inline">
              <input type="hidden" name="action"    value="toggle_dept">
              <input type="hidden" name="dept_id"   value="<?= $d['id'] ?>">
              <input type="hidden" name="is_active" value="<?= $d['is_active'] ?>">
              <button class="btn btn-sm btn-outline"><?= $d['is_active'] ? 'Deactivate' : 'Activate' ?></button>
            </form>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</main>
<?php include '../../includes/footer.php'; ?>
