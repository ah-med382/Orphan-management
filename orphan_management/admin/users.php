<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_middleware.php';

// Restricted to Admin only
checkRole(['admin']);

$csrf_token = getCsrfToken();

// Add System User
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_user') {
    verifyCsrfToken($_POST['csrf_token'] ?? '');
    
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $role = $_POST['role'] ?? 'staff';
    $password = $_POST['password'] ?? '';

    if (empty($full_name) || empty($email) || empty($password)) {
        $_SESSION['error_message'] = "Full name, email and password are required.";
    } else {
        try {
            $stmtCheck = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
            $stmtCheck->execute([$email]);
            if ($stmtCheck->fetch()) {
                $_SESSION['error_message'] = "Email is already registered.";
            } else {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmtInsert = $pdo->prepare("INSERT INTO users (full_name, email, password, role, status) VALUES (?, ?, ?, ?, 'active')");
                $stmtInsert->execute([$full_name, $email, $hashed_password, $role]);
                
                $_SESSION['success_message'] = "User account created successfully.";
            }
        } catch (PDOException $e) {
            $_SESSION['error_message'] = "Database error: " . $e->getMessage();
        }
    }
    header("Location: /orphan_management/admin/users.php");
    exit;
}

// Toggle status (Active / Inactive)
if (isset($_GET['toggle_status']) && isset($_GET['id'])) {
    $user_id = (int)$_GET['id'];
    $new_status = $_GET['toggle_status'] === 'active' ? 'active' : 'inactive';
    
    // Prevent locking out yourself
    if ($user_id === (int)$_SESSION['user_id']) {
        $_SESSION['error_message'] = "You cannot deactivate your own admin session.";
    } else {
        try {
            $stmtToggle = $pdo->prepare("UPDATE users SET status = ? WHERE user_id = ?");
            $stmtToggle->execute([$new_status, $user_id]);
            $_SESSION['success_message'] = "User account status updated.";
        } catch (PDOException $e) {
            $_SESSION['error_message'] = "Failed to update: " . $e->getMessage();
        }
    }
    header("Location: /orphan_management/admin/users.php");
    exit;
}

// Fetch all users
$users = $pdo->query("SELECT * FROM users ORDER BY role ASC, full_name ASC")->fetchAll();

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<!-- Content Wrapper -->
<div class="content-wrapper">
  <!-- Content Header -->
  <div class="content-header">
    <div class="container-fluid">
      <div class="row mb-2">
        <div class="col-sm-6">
          <h1 class="m-0 font-weight-bold text-dark">System Users Registry</h1>
        </div>
        <div class="col-sm-6 text-right">
          <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addUserModal">
            <i class="fas fa-plus mr-1"></i> Add System User
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- Main content -->
  <section class="content">
    <div class="container-fluid">

      <!-- Alerts -->
      <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
          <i class="icon fas fa-check mr-2"></i> <?php echo escape($_SESSION['success_message']); unset($_SESSION['success_message']); ?>
          <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
      <?php endif; ?>
      <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
          <i class="icon fas fa-ban mr-2"></i> <?php echo escape($_SESSION['error_message']); unset($_SESSION['error_message']); ?>
          <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
      <?php endif; ?>

      <!-- Users Table -->
      <div class="card">
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-striped table-hover mb-0 text-sm">
              <thead>
                <tr>
                  <th>User ID</th>
                  <th>Full Name</th>
                  <th>Email</th>
                  <th>System Role</th>
                  <th>Account Status</th>
                  <th>Registered Date</th>
                  <th class="text-right">Action</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($users as $usr): ?>
                  <tr>
                    <td><code>#USR-<?php echo str_pad($usr['user_id'], 4, '0', STR_PAD_LEFT); ?></code></td>
                    <td><strong><?php echo escape($usr['full_name']); ?></strong></td>
                    <td><?php echo escape($usr['email']); ?></td>
                    <td>
                      <?php 
                      $role_badge = 'badge-secondary';
                      if ($usr['role'] === 'admin') $role_badge = 'badge-danger';
                      elseif ($usr['role'] === 'staff') $role_badge = 'badge-info';
                      elseif ($usr['role'] === 'donor') $role_badge = 'badge-success';
                      ?>
                      <span class="badge <?php echo $role_badge; ?> role-badge px-2 py-1"><?php echo escape(strtoupper($usr['role'])); ?></span>
                    </td>
                    <td>
                      <?php if ($usr['status'] === 'active'): ?>
                        <span class="badge badge-success px-2 py-1">ACTIVE</span>
                      <?php else: ?>
                        <span class="badge badge-danger px-2 py-1">INACTIVE</span>
                      <?php endif; ?>
                    </td>
                    <td class="text-muted"><?php echo date('M d, Y', strtotime($usr['created_at'])); ?></td>
                    <td class="text-right">
                      <?php if ($usr['user_id'] !== (int)$_SESSION['user_id']): ?>
                        <?php if ($usr['status'] === 'active'): ?>
                          <a href="?toggle_status=inactive&id=<?php echo $usr['user_id']; ?>" class="btn btn-xs btn-outline-danger">Deactivate</a>
                        <?php else: ?>
                          <a href="?toggle_status=active&id=<?php echo $usr['user_id']; ?>" class="btn btn-xs btn-outline-success">Activate</a>
                        <?php endif; ?>
                      <?php else: ?>
                        <span class="text-xs text-muted">Self Session</span>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

    </div>
  </section>
</div>

<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1" role="dialog" aria-labelledby="addUserModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content" style="border-radius: 12px;">
      <div class="modal-header">
        <h5 class="modal-title font-weight-bold" id="addUserModalLabel">Create User Account</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <form action="" method="POST">
        <div class="modal-body">
          <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
          <input type="hidden" name="action" value="add_user">
          
          <div class="form-group">
            <label class="text-sm">Full Name <span class="text-danger">*</span></label>
            <input type="text" name="full_name" class="form-control" placeholder="Enter full name" required>
          </div>
          <div class="form-group">
            <label class="text-sm">Email Address <span class="text-danger">*</span></label>
            <input type="email" name="email" class="form-control" placeholder="Enter email" required>
          </div>
          <div class="form-group">
            <label class="text-sm">Role <span class="text-danger">*</span></label>
            <select name="role" class="form-control" required>
              <option value="admin">Admin (Full Access)</option>
              <option value="staff">Staff (Operational Access)</option>
              <option value="donor">Donor (Support Dashboard Access)</option>
            </select>
          </div>
          <div class="form-group">
            <label class="text-sm">Password <span class="text-danger">*</span></label>
            <input type="password" name="password" class="form-control" placeholder="Minimum 6 characters" required>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
          <button type="submit" class="btn btn-primary">Create Account</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php
include __DIR__ . '/../includes/footer.php';
?>
