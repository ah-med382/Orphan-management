<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_middleware.php';

// Restricted to Admin only
checkRole(['admin']);

$csrf_token = getCsrfToken();

// Add Staff
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_staff') {
    verifyCsrfToken($_POST['csrf_token'] ?? '');
    
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $position = trim($_POST['position'] ?? '');
    $salary = (float)$_POST['salary'] ?? 0;
    $joining_date = $_POST['joining_date'] ?? date('Y-m-d');
    $password = $_POST['password'] ?? '';

    if (empty($full_name) || empty($email) || empty($phone) || empty($position) || empty($password)) {
        $_SESSION['error_message'] = "All fields are required to register staff.";
    } else {
        try {
            // Handle Profile Image Upload
            $profile_image_filename = null;
            if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
                $file_tmp = $_FILES['profile_image']['tmp_name'];
                $file_name = $_FILES['profile_image']['name'];
                $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                $allowed_exts = ['jpg', 'jpeg', 'png', 'gif'];

                if (in_array($file_ext, $allowed_exts)) {
                    $upload_dir = __DIR__ . '/../assets/images/';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0777, true);
                    }
                    $profile_image_filename = uniqid('staff_', true) . '.' . $file_ext;
                    if (!move_uploaded_file($file_tmp, $upload_dir . $profile_image_filename)) {
                        $profile_image_filename = null;
                    }
                }
            }

            $pdo->beginTransaction();

            // Check email uniqueness
            $stmtCheck = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
            $stmtCheck->execute([$email]);
            if ($stmtCheck->fetch()) {
                $_SESSION['error_message'] = "Email is already in use.";
                $pdo->rollBack();
            } else {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmtUser = $pdo->prepare("INSERT INTO users (full_name, email, password, role, status) VALUES (?, ?, ?, 'staff', 'active')");
                $stmtUser->execute([$full_name, $email, $hashed_password]);
                $user_id = $pdo->lastInsertId();

                $stmtStaff = $pdo->prepare("INSERT INTO staff (user_id, full_name, phone, email, position, salary, joining_date, profile_image) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmtStaff->execute([$user_id, $full_name, $phone, $email, $position, $salary, $joining_date, $profile_image_filename]);

                $pdo->commit();
                $_SESSION['success_message'] = "Staff registered successfully!";
            }
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $_SESSION['error_message'] = "Database error: " . $e->getMessage();
        }
    }
    header("Location: /orphan_management/admin/staff.php");
    exit;
}

// Edit Staff
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_staff') {
    verifyCsrfToken($_POST['csrf_token'] ?? '');
    
    $staff_id = (int)$_POST['staff_id'];
    $full_name = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $position = trim($_POST['position'] ?? '');
    $salary = (float)$_POST['salary'] ?? 0;
    $joining_date = $_POST['joining_date'] ?? '';
    $password = $_POST['password'] ?? '';

    if (empty($full_name) || empty($phone) || empty($position)) {
        $_SESSION['error_message'] = "Name, phone and position are required.";
    } else {
        try {
            $pdo->beginTransaction();

            // Fetch staff details to get user_id
            $stmtGet = $pdo->prepare("SELECT user_id FROM staff WHERE staff_id = ?");
            $stmtGet->execute([$staff_id]);
            $user_id = $stmtGet->fetchColumn();

            // Update Staff table
            $stmtUpdateStaff = $pdo->prepare("UPDATE staff SET full_name = ?, phone = ?, position = ?, salary = ?, joining_date = ? WHERE staff_id = ?");
            $stmtUpdateStaff->execute([$full_name, $phone, $position, $salary, $joining_date, $staff_id]);

            // Update User details
            if ($user_id) {
                if (!empty($password)) {
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $stmtUpdateUser = $pdo->prepare("UPDATE users SET full_name = ?, password = ? WHERE user_id = ?");
                    $stmtUpdateUser->execute([$full_name, $hashed_password, $user_id]);
                } else {
                    $stmtUpdateUser = $pdo->prepare("UPDATE users SET full_name = ? WHERE user_id = ?");
                    $stmtUpdateUser->execute([$full_name, $user_id]);
                }
            }

            $pdo->commit();
            $_SESSION['success_message'] = "Staff updated successfully!";
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $_SESSION['error_message'] = "Database error: " . $e->getMessage();
        }
    }
    header("Location: /orphan_management/admin/staff.php");
    exit;
}

// Delete Staff
if (isset($_GET['delete'])) {
    $staff_id = (int)$_GET['delete'];
    try {
        $pdo->beginTransaction();

        $stmtGet = $pdo->prepare("SELECT user_id FROM staff WHERE staff_id = ?");
        $stmtGet->execute([$staff_id]);
        $user_id = $stmtGet->fetchColumn();

        // Delete from staff
        $stmtDelStaff = $pdo->prepare("DELETE FROM staff WHERE staff_id = ?");
        $stmtDelStaff->execute([$staff_id]);

        // Delete from users
        if ($user_id) {
            $stmtDelUser = $pdo->prepare("DELETE FROM users WHERE user_id = ?");
            $stmtDelUser->execute([$user_id]);
        }

        $pdo->commit();
        $_SESSION['success_message'] = "Staff deleted successfully.";
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $_SESSION['error_message'] = "Database error: " . $e->getMessage();
    }
    header("Location: /orphan_management/admin/staff.php");
    exit;
}

// Fetch all staff
$staff_list = $pdo->query("SELECT * FROM staff ORDER BY full_name ASC")->fetchAll();

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
          <h1 class="m-0 font-weight-bold text-dark">Staff Directory Management</h1>
        </div>
        <div class="col-sm-6 text-right">
          <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addStaffModal">
            <i class="fas fa-user-plus mr-1"></i> Register New Staff
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

      <!-- Staff Table -->
      <div class="card">
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-striped table-hover mb-0">
              <thead>
                <tr>
                  <th>Full Name</th>
                  <th>Position</th>
                  <th>Phone</th>
                  <th>Email</th>
                  <th>Monthly Salary</th>
                  <th>Joining Date</th>
                  <th class="text-right">Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php if (count($staff_list) > 0): ?>
                  <?php foreach ($staff_list as $stf): ?>
                    <tr>
                      <td>
                        <div class="d-flex align-items-center">
                          <div class="mr-2">
                            <?php if (!empty($stf['profile_image']) && file_exists(__DIR__ . '/../assets/images/' . $stf['profile_image'])): ?>
                              <img src="/orphan_management/assets/images/<?php echo escape($stf['profile_image']); ?>" class="img-circle" style="width: 32px; height: 32px; object-fit: cover; border: 1.5px solid #cbd5e1;">
                            <?php else: ?>
                              <div class="bg-indigo text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 32px; height: 32px; font-weight: bold; font-size: 0.8rem; border: 1.5px solid #cbd5e1;">
                                <?php echo strtoupper(substr($stf['full_name'], 0, 1)); ?>
                              </div>
                            <?php endif; ?>
                          </div>
                          <strong><?php echo escape($stf['full_name']); ?></strong>
                        </div>
                      </td>
                      <td><span class="badge badge-info"><?php echo escape($stf['position']); ?></span></td>
                      <td><?php echo escape($stf['phone']); ?></td>
                      <td><a href="mailto:<?php echo escape($stf['email']); ?>"><?php echo escape($stf['email']); ?></a></td>
                      <td class="font-weight-medium text-dark">$<?php echo number_format($stf['salary'], 2); ?></td>
                      <td><?php echo date('M d, Y', strtotime($stf['joining_date'])); ?></td>
                      <td class="text-right">
                        <button type="button" class="btn btn-sm btn-outline-primary mr-1 edit-staff-btn" 
                                data-id="<?php echo $stf['staff_id']; ?>"
                                data-name="<?php echo escape($stf['full_name']); ?>"
                                data-phone="<?php echo escape($stf['phone']); ?>"
                                data-pos="<?php echo escape($stf['position']); ?>"
                                data-sal="<?php echo $stf['salary']; ?>"
                                data-date="<?php echo $stf['joining_date']; ?>"
                                data-toggle="modal" data-target="#editStaffModal">
                          <i class="fas fa-edit"></i> Edit
                        </button>
                        <a href="?delete=<?php echo $stf['staff_id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure you want to delete this staff record? This deletes their account and associated profiles.');">
                          <i class="fas fa-trash"></i> Delete
                        </a>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php else: ?>
                  <tr>
                    <td colspan="7" class="text-center py-5 text-muted">No staff records found.</td>
                  </tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

    </div>
  </section>
</div>

<!-- Add Staff Modal -->
<div class="modal fade" id="addStaffModal" tabindex="-1" role="dialog" aria-labelledby="addStaffModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content" style="border-radius: 12px;">
      <div class="modal-header">
        <h5 class="modal-title font-weight-bold" id="addStaffModalLabel">Register New Staff</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <form action="" method="POST" enctype="multipart/form-data">
        <div class="modal-body">
          <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
          <input type="hidden" name="action" value="add_staff">
          
          <div class="form-group">
            <label class="text-sm">Full Name <span class="text-danger">*</span></label>
            <input type="text" name="full_name" class="form-control" placeholder="Enter full name" required>
          </div>
          <div class="form-group">
            <label class="text-sm">Email Address <span class="text-danger">*</span></label>
            <input type="email" name="email" class="form-control" placeholder="Enter email" required>
          </div>
          <div class="form-group">
            <label class="text-sm">Phone Number <span class="text-danger">*</span></label>
            <input type="text" name="phone" class="form-control" placeholder="e.g. +15550199" required>
          </div>
          <div class="form-group">
            <label class="text-sm">Position / Role <span class="text-danger">*</span></label>
            <input type="text" name="position" class="form-control" placeholder="e.g. Caretaker, Nurse, Cook" required>
          </div>
          <div class="form-group">
            <label class="text-sm">Monthly Salary ($) <span class="text-danger">*</span></label>
            <input type="number" name="salary" step="0.01" class="form-control" placeholder="0.00" required>
          </div>
          <div class="form-group">
            <label class="text-sm">Joining Date <span class="text-danger">*</span></label>
            <input type="date" name="joining_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
          </div>
          <div class="form-group">
            <label class="text-sm">Profile Image <span class="text-danger">*</span></label>
            <div class="custom-file">
              <input type="file" name="profile_image" class="custom-file-input" id="staffImage" accept="image/*" required>
              <label class="custom-file-label text-xs" for="staffImage">Choose image...</label>
            </div>
          </div>
          <div class="form-group">
            <label class="text-sm">Login Password <span class="text-danger">*</span></label>
            <input type="password" name="password" class="form-control" placeholder="Assign system password" required>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
          <button type="submit" class="btn btn-primary">Register Staff</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Edit Staff Modal -->
<div class="modal fade" id="editStaffModal" tabindex="-1" role="dialog" aria-labelledby="editStaffModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content" style="border-radius: 12px;">
      <div class="modal-header">
        <h5 class="modal-title font-weight-bold" id="editStaffModalLabel">Edit Staff Info</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <form action="" method="POST">
        <div class="modal-body">
          <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
          <input type="hidden" name="action" value="edit_staff">
          <input type="hidden" name="staff_id" id="edit_staff_id">
          
          <div class="form-group">
            <label class="text-sm">Full Name <span class="text-danger">*</span></label>
            <input type="text" name="full_name" id="edit_full_name" class="form-control" required>
          </div>
          <div class="form-group">
            <label class="text-sm">Phone Number <span class="text-danger">*</span></label>
            <input type="text" name="phone" id="edit_phone" class="form-control" required>
          </div>
          <div class="form-group">
            <label class="text-sm">Position / Role <span class="text-danger">*</span></label>
            <input type="text" name="position" id="edit_position" class="form-control" required>
          </div>
          <div class="form-group">
            <label class="text-sm">Monthly Salary ($) <span class="text-danger">*</span></label>
            <input type="number" name="salary" id="edit_salary" step="0.01" class="form-control" required>
          </div>
          <div class="form-group">
            <label class="text-sm">Joining Date <span class="text-danger">*</span></label>
            <input type="date" name="joining_date" id="edit_joining_date" class="form-control" required>
          </div>
          <div class="form-group">
            <label class="text-sm">Update Login Password (Leave blank to keep current)</label>
            <input type="password" name="password" class="form-control" placeholder="Enter new password if updating">
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
          <button type="submit" class="btn btn-primary">Save Changes</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
  window.addEventListener('DOMContentLoaded', () => {
    // Populate Edit Modal on button click
    $('.edit-staff-btn').on('click', function() {
      $('#edit_staff_id').val($(this).data('id'));
      $('#edit_full_name').val($(this).data('name'));
      $('#edit_phone').val($(this).data('phone'));
      $('#edit_position').val($(this).data('pos'));
      $('#edit_salary').val($(this).data('sal'));
      $('#edit_joining_date').val($(this).data('date'));
    });
  });
</script>

<?php
include __DIR__ . '/../includes/footer.php';
?>
