<?php
// modules/admin/users.php
session_start();
require_once '../../config/db.php';
require_once '../../includes/auth_check.php';
requireRole('municipality_admin');

$db = getDB();
$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';

  // ─── CREATE USER ────────────────────────────────────────
  if ($action === 'create_user') {
    $name    = trim($_POST['name']     ?? '');
    $email   = trim($_POST['email']    ?? '');
    $pass    = trim($_POST['password'] ?? '');
    $role    = trim($_POST['role']     ?? '');
    $ward_id = !empty($_POST['ward_id']) ? (int)$_POST['ward_id'] : null;
    $dept_id = !empty($_POST['dept_id']) ? (int)$_POST['dept_id'] : null;
    $phone   = trim($_POST['phone']    ?? '');

    if (empty($name) || empty($email) || empty($pass) || empty($role)) {
      $error = 'Name, email, password, and role are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
      $error = 'Invalid email address.';
    } elseif (strlen($pass) < 6) {
      $error = 'Password must be at least 6 characters.';
    } elseif (!in_array($role, ['ward_admin', 'dept_officer', 'citizen'], true)) {
      $error = 'Invalid role selected.';
    } elseif ($role === 'ward_admin' && !$ward_id) {
      $error = 'Please select a ward for the ward admin.';
    } elseif ($role === 'dept_officer' && !$dept_id) {
      $error = 'Please select a department for the officer.';
    } else {
      $chk = $db->prepare("SELECT id FROM users WHERE email = ?");
      $chk->execute([$email]);
      if ($chk->fetch()) {
        $error = 'This email is already registered.';
      } else {
        $hash = password_hash($pass, PASSWORD_DEFAULT);
        $db->prepare("INSERT INTO users (name,email,password_hash,phone,role,ward_id,department_id) VALUES (?,?,?,?,?,?,?)")
          ->execute([$name, $email, $hash, $phone, $role, $ward_id, $dept_id]);
        $success = "User '{$name}' created as " . ucwords(str_replace('_', ' ', $role)) . '.';
      }
    }
  }

  // ─── UPDATE USER ────────────────────────────────────────
  elseif ($action === 'update_user') {
    $id      = (int)($_POST['user_id'] ?? 0);
    $name    = trim($_POST['name']  ?? '');
    $email   = trim($_POST['email'] ?? '');
    $phone   = trim($_POST['phone'] ?? '');
    $ward_id = !empty($_POST['ward_id']) ? (int)$_POST['ward_id'] : null;
    $dept_id = !empty($_POST['dept_id']) ? (int)$_POST['dept_id'] : null;
    $newPass = trim($_POST['new_password'] ?? '');

    if ($id <= 0) {
      $error = 'Invalid user.';
    } else {
      // Load current user
      $cur = $db->prepare("SELECT * FROM users WHERE id = ? AND role != 'municipality_admin'");
      $cur->execute([$id]);
      $target = $cur->fetch();

      if (!$target) {
        $error = 'User not found.';
      } elseif (empty($name) || empty($email)) {
        $error = 'Name and email are required.';
      } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email address.';
      } else {
        // Email uniqueness (excluding self)
        $chk = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $chk->execute([$email, $id]);
        if ($chk->fetch()) {
          $error = 'This email is already used by another user.';
        } elseif ($target['role'] === 'ward_admin' && !$ward_id) {
          $error = 'Ward admins must be assigned to a ward.';
        } elseif ($target['role'] === 'dept_officer' && !$dept_id) {
          $error = 'Department officers must be assigned to a department.';
        } elseif ($newPass !== '' && strlen($newPass) < 6) {
          $error = 'New password must be at least 6 characters.';
        } else {
          // Force ward/dept based on role (ignore stray values from JS)
          if ($target['role'] === 'citizen') {
            $ward_id = null;
            $dept_id = null;
          } elseif ($target['role'] === 'ward_admin') {
            $dept_id = null;
          } elseif ($target['role'] === 'dept_officer') {
            $ward_id = null;
          }

          if ($newPass !== '') {
            $hash = password_hash($newPass, PASSWORD_DEFAULT);
            $db->prepare("UPDATE users SET name=?, email=?, phone=?, ward_id=?, department_id=?, password_hash=? WHERE id=?")
              ->execute([$name, $email, $phone, $ward_id, $dept_id, $hash, $id]);
          } else {
            $db->prepare("UPDATE users SET name=?, email=?, phone=?, ward_id=?, department_id=? WHERE id=?")
              ->execute([$name, $email, $phone, $ward_id, $dept_id, $id]);
          }

          $success = "User '{$name}' updated successfully." .
            ($newPass !== '' ? ' Password has been reset.' : '');
        }
      }
    }
  }

  // ─── TOGGLE ACTIVE ──────────────────────────────────────
  elseif ($action === 'toggle_user') {
    $id  = (int)$_POST['user_id'];
    $cur = (int)$_POST['is_active'];
    $db->prepare("UPDATE users SET is_active = ? WHERE id = ? AND role != 'municipality_admin'")
      ->execute([$cur ? 0 : 1, $id]);
    $success = 'User status updated.';
  }
}

$wards = $db->query("SELECT * FROM wards ORDER BY ward_number")->fetchAll();
$depts = $db->query("SELECT * FROM departments WHERE is_active=1 ORDER BY name")->fetchAll();
$users = $db->query("
    SELECT u.*, w.ward_number, d.name AS dept_name
    FROM users u
    LEFT JOIN wards w ON u.ward_id = w.id
    LEFT JOIN departments d ON u.department_id = d.id
    WHERE u.role != 'municipality_admin'
    ORDER BY u.created_at DESC
")->fetchAll();

// Optional: auto-open the modal if ?edit=ID is present (e.g. after a validation error)
$autoOpenId = (int)($_GET['edit'] ?? 0);

$pageTitle = 'Manage Users';
include '../../includes/header.php';
include '../../includes/sidebar.php';
?>
<main class="main-content">
  <div class="page-header">
    <div>
      <h1>Manage Users</h1>
      <p class="text-muted">Create, edit, and manage ward admins, department officers, and citizens</p>
    </div>
  </div>
  <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
  <?php if ($error):   ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

  <div class="two-col">
    <!-- ═══ CREATE NEW USER ═══ -->
    <div class="card">
      <div class="card-header">
        <h2>Create New User</h2>
      </div>
      <div class="card-body">
        <form method="POST">
          <input type="hidden" name="action" value="create_user">
          <div class="form-group">
            <label>Full Name</label>
            <input type="text" name="name" class="form-control" placeholder="Full name" required>
          </div>
          <div class="form-group">
            <label>Email</label>
            <input type="email" name="email" class="form-control" placeholder="email@example.com" required>
          </div>
          <div class="form-group">
            <label>Password</label>
            <input type="password" name="password" class="form-control" placeholder="Min 6 characters" required>
          </div>
          <div class="form-group">
            <label>Phone</label>
            <input type="text" name="phone" class="form-control" placeholder="98XXXXXXXX">
          </div>
          <div class="form-group">
            <label>Role</label>
            <select name="role" class="form-control" onchange="toggleCreateFields(this.value)" required>
              <option value="">— Select role —</option>
              <option value="ward_admin">Ward Admin</option>
              <option value="dept_officer">Department Officer</option>
              <option value="citizen">Citizen</option>
            </select>
          </div>
          <div class="form-group" id="wardGroup" style="display:none">
            <label>Assign Ward</label>
            <select name="ward_id" class="form-control">
              <option value="">— Select ward —</option>
              <?php foreach ($wards as $w): ?>
                <option value="<?= $w['id'] ?>">Ward <?= $w['ward_number'] ?> — <?= htmlspecialchars($w['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group" id="deptGroup" style="display:none">
            <label>Assign Department</label>
            <select name="dept_id" class="form-control">
              <option value="">— Select department —</option>
              <?php foreach ($depts as $d): ?>
                <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <button type="submit" class="btn btn-primary">Create User</button>
        </form>
      </div>
    </div>

    <!-- ═══ ALL USERS ═══ -->
    <div class="card">
      <div class="card-header">
        <h2>All Users (<?= count($users) ?>)</h2>
      </div>
      <div class="table-wrapper">
        <table class="table">
          <thead>
            <tr>
              <th>Name</th>
              <th>Role</th>
              <th>Assigned</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($users as $u): ?>
              <tr>
                <td>
                  <strong><?= htmlspecialchars($u['name']) ?></strong>
                  <span class="text-muted text-sm d-block"><?= htmlspecialchars($u['email']) ?></span>
                  <?php if ($u['phone']): ?>
                    <span class="text-muted text-sm">&#128222; <?= htmlspecialchars($u['phone']) ?></span>
                  <?php endif; ?>
                </td>
                <td>
                  <span class="role-badge role-<?= $u['role'] ?>">
                    <?= ucwords(str_replace('_', ' ', $u['role'])) ?>
                  </span>
                </td>
                <td class="text-sm text-muted">
                  <?php if ($u['ward_number']): ?>
                    Ward <?= $u['ward_number'] ?>
                  <?php elseif ($u['dept_name']): ?>
                    <?= htmlspecialchars($u['dept_name']) ?>
                    <?php else: ?>—<?php endif; ?>
                </td>
                <td>
                  <span class="badge <?= $u['is_active'] ? 'badge-resolved' : 'badge-rejected' ?>">
                    <?= $u['is_active'] ? 'Active' : 'Inactive' ?>
                  </span>
                </td>
                <td>
                  <div style="display:flex;gap:6px;flex-wrap:wrap">
                    <!-- EDIT BUTTON -->
                    <button type="button" class="btn btn-sm btn-outline"
                      onclick='openEditModal(<?= json_encode([
                                                "id"        => $u["id"],
                                                "name"      => $u["name"],
                                                "email"     => $u["email"],
                                                "phone"     => $u["phone"] ?? "",
                                                "role"      => $u["role"],
                                                "ward_id"   => $u["ward_id"],
                                                "dept_id"   => $u["department_id"],
                                              ], JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>
                      Edit
                    </button>

                    <!-- TOGGLE ACTIVE -->
                    <form method="POST" style="display:inline">
                      <input type="hidden" name="action" value="toggle_user">
                      <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                      <input type="hidden" name="is_active" value="<?= $u['is_active'] ?>">
                      <button class="btn btn-sm btn-outline">
                        <?= $u['is_active'] ? 'Deactivate' : 'Activate' ?>
                      </button>
                    </form>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</main>

<!-- ═══ EDIT USER MODAL ═══ -->
<div id="editUserModal" class="modal-backdrop" style="display:none">
  <div class="modal" style="max-width:520px">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px">
      <h3 style="font-size:17px;color:var(--gray-800)">Edit User</h3>
      <button type="button" onclick="closeEditModal()"
        style="background:none;border:none;font-size:22px;cursor:pointer;color:var(--text-m);line-height:1">&times;</button>
    </div>

    <form method="POST" id="editUserForm">
      <input type="hidden" name="action" value="update_user">
      <input type="hidden" name="user_id" id="eu_id">

      <div class="form-group">
        <label>Role</label>
        <input type="text" id="eu_role" class="form-control" disabled
          style="background:var(--gray-50);cursor:not-allowed">
        <small class="text-muted text-sm">Role cannot be changed after creation.</small>
      </div>

      <div class="form-group">
        <label>Full Name <span style="color:#dc2626">*</span></label>
        <input type="text" name="name" id="eu_name" class="form-control" required>
      </div>

      <div class="form-group">
        <label>Email <span style="color:#dc2626">*</span></label>
        <input type="email" name="email" id="eu_email" class="form-control" required>
      </div>

      <div class="form-group">
        <label>Phone</label>
        <input type="text" name="phone" id="eu_phone" class="form-control">
      </div>

      <!-- Ward (only for ward_admin) -->
      <div class="form-group" id="eu_wardGroup" style="display:none">
        <label>Assigned Ward <span style="color:#dc2626">*</span></label>
        <select name="ward_id" id="eu_ward_id" class="form-control">
          <option value="">— Select ward —</option>
          <?php foreach ($wards as $w): ?>
            <option value="<?= $w['id'] ?>">Ward <?= $w['ward_number'] ?> — <?= htmlspecialchars($w['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- Department (only for dept_officer) -->
      <div class="form-group" id="eu_deptGroup" style="display:none">
        <label>Assigned Department <span style="color:#dc2626">*</span></label>
        <select name="dept_id" id="eu_dept_id" class="form-control">
          <option value="">— Select department —</option>
          <?php foreach ($depts as $d): ?>
            <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <hr style="margin:16px 0;border:none;border-top:1px solid var(--border)">

      <div class="form-group">
        <label>Reset Password <span class="text-muted">(optional)</span></label>
        <input type="password" name="new_password" id="eu_password" class="form-control"
          placeholder="Leave blank to keep current password" minlength="6">
        <small class="text-muted text-sm">Only fill this if you want to change the user's password.</small>
      </div>

      <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:20px">
        <button type="button" class="btn btn-outline" onclick="closeEditModal()">Cancel</button>
        <button type="submit" class="btn btn-primary">Save Changes</button>
      </div>
    </form>
  </div>
</div>

<script>
  // ─── Create form: show ward/dept fields based on role ─────
  function toggleCreateFields(role) {
    document.getElementById('wardGroup').style.display = role === 'ward_admin' ? 'block' : 'none';
    document.getElementById('deptGroup').style.display = role === 'dept_officer' ? 'block' : 'none';
  }

  // ─── Edit modal: open + populate ──────────────────────────
  function openEditModal(user) {
    document.getElementById('eu_id').value = user.id;
    document.getElementById('eu_name').value = user.name;
    document.getElementById('eu_email').value = user.email;
    document.getElementById('eu_phone').value = user.phone || '';
    document.getElementById('eu_password').value = '';

    // Role label
    const roleLabel = {
      'ward_admin': 'Ward Admin',
      'dept_officer': 'Department Officer',
      'citizen': 'Citizen'
    } [user.role] || user.role;
    document.getElementById('eu_role').value = roleLabel;

    // Show/hide ward or dept fields
    const wardGroup = document.getElementById('eu_wardGroup');
    const deptGroup = document.getElementById('eu_deptGroup');

    wardGroup.style.display = user.role === 'ward_admin' ? 'block' : 'none';
    deptGroup.style.display = user.role === 'dept_officer' ? 'block' : 'none';

    // Pre-select ward / dept
    const wardSel = document.getElementById('eu_ward_id');
    const deptSel = document.getElementById('eu_dept_id');
    wardSel.value = user.ward_id || '';
    deptSel.value = user.dept_id || '';

    // Required attributes based on role
    wardSel.required = (user.role === 'ward_admin');
    deptSel.required = (user.role === 'dept_officer');

    document.getElementById('editUserModal').style.display = 'flex';
  }

  function closeEditModal() {
    document.getElementById('editUserModal').style.display = 'none';
  }

  // Close modal on Escape key
  document.addEventListener('keydown', e => {
    if (e.key === 'Escape') closeEditModal();
  });

  // Click backdrop to close
  document.getElementById('editUserModal').addEventListener('click', e => {
    if (e.target.id === 'editUserModal') closeEditModal();
  });

  // Auto-open if ?edit=ID was passed (e.g. validation error)
  <?php if ($autoOpenId): ?>
    document.addEventListener('DOMContentLoaded', () => {
      const users = <?= json_encode(array_map(fn($u) => [
                      'id'      => (int)$u['id'],
                      'name'    => $u['name'],
                      'email'   => $u['email'],
                      'phone'   => $u['phone'] ?? '',
                      'role'    => $u['role'],
                      'ward_id' => $u['ward_id'],
                      'dept_id' => $u['department_id'],
                    ], $users), JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
      const match = users.find(u => u.id === <?= (int)$autoOpenId ?>);
      if (match) openEditModal(match);
    });
  <?php endif; ?>
</script>

<?php include '../../includes/footer.php'; ?>