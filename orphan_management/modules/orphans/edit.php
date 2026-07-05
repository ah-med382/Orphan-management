<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth_middleware.php';

// Restricted to Admin and Staff
checkRole(['admin', 'staff']);

$csrf_token = getCsrfToken();
$orphan_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Fetch Orphan Details
try {
    $stmt = $pdo->prepare("SELECT * FROM orphans WHERE orphan_id = ? LIMIT 1");
    $stmt->execute([$orphan_id]);
    $orphan = $stmt->fetch();

    if (!$orphan) {
        $_SESSION['error_message'] = "Orphan record not found.";
        header("Location: /orphan_management/modules/orphans/index.php");
        exit;
    }
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken($_POST['csrf_token'] ?? '');

    $full_name = trim($_POST['full_name'] ?? '');
    $gender = $_POST['gender'] ?? '';
    $date_of_birth = $_POST['date_of_birth'] ?? '';
    $admission_date = $_POST['admission_date'] ?? '';
    $health_status = trim($_POST['health_status'] ?? '');
    $education_level = trim($_POST['education_level'] ?? '');
    $guardian_information = trim($_POST['guardian_information'] ?? '');
    $status = $_POST['status'] ?? '';

    // Validation
    if (empty($full_name) || empty($gender) || empty($date_of_birth) || empty($admission_date) || empty($status)) {
        $_SESSION['error_message'] = "Full name, gender, DOB, admission date, and status are required.";
        header("Location: /orphan_management/modules/orphans/edit.php?id=" . $orphan_id);
        exit;
    }

    $photo_filename = $orphan['photo']; // Keep current photo by default
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $file_tmp = $_FILES['photo']['tmp_name'];
        $file_name = $_FILES['photo']['name'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        $allowed_exts = ['jpg', 'jpeg', 'png', 'gif'];

        if (in_array($file_ext, $allowed_exts)) {
            $upload_dir = __DIR__ . '/../../assets/images/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            // Remove old photo if exists
            if (!empty($orphan['photo']) && file_exists($upload_dir . $orphan['photo'])) {
                unlink($upload_dir . $orphan['photo']);
            }

            // Save new photo
            $photo_filename = uniqid('orphan_', true) . '.' . $file_ext;
            move_uploaded_file($file_tmp, $upload_dir . $photo_filename);
        } else {
            $_SESSION['error_message'] = "Invalid photo format. Only JPG, JPEG, PNG, and GIF allowed.";
            header("Location: /orphan_management/modules/orphans/edit.php?id=" . $orphan_id);
            exit;
        }
    }

    try {
        $pdo->beginTransaction();

        if ($status === 'Inactive' && $orphan['status'] !== 'Inactive') {
            // Cancel active sponsorships and set orphan_left_at date for notification
            $stmtCancel = $pdo->prepare("
                UPDATE sponsorships 
                SET status = 'Cancelled', end_date = CURDATE(), orphan_left_at = CURDATE(), notification_read = 0 
                WHERE orphan_id = ? AND status = 'Active'
            ");
            $stmtCancel->execute([$orphan_id]);
        }

        $stmtUpdate = $pdo->prepare("
            UPDATE orphans 
            SET full_name = ?, gender = ?, date_of_birth = ?, admission_date = ?, photo = ?, health_status = ?, education_level = ?, guardian_information = ?, status = ?
            WHERE orphan_id = ?
        ");
        $stmtUpdate->execute([
            $full_name,
            $gender,
            $date_of_birth,
            $admission_date,
            $photo_filename,
            $health_status,
            $education_level,
            $guardian_information,
            $status,
            $orphan_id
        ]);

        $pdo->commit();
        $_SESSION['success_message'] = "Orphan record updated successfully!";
        header("Location: /orphan_management/modules/orphans/index.php");
        exit;
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $_SESSION['error_message'] = "Database error: " . $e->getMessage();
        header("Location: /orphan_management/modules/orphans/edit.php?id=" . $orphan_id);
        exit;
    }
}

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<!-- Content Wrapper -->
<div class="content-wrapper">
  <!-- Content Header -->
  <div class="content-header">
    <div class="container-fluid">
      <div class="row mb-2">
        <div class="col-sm-6">
          <h1 class="m-0 font-weight-bold text-dark">Edit Orphan Profile</h1>
        </div>
        <div class="col-sm-6 text-right">
          <a href="/orphan_management/modules/orphans/index.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left mr-1"></i> Back to Directory
          </a>
        </div>
      </div>
    </div>
  </div>

  <!-- Main content -->
  <section class="content">
    <div class="container-fluid">

      <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
          <i class="icon fas fa-ban mr-2"></i> <?php echo escape($_SESSION['error_message']); unset($_SESSION['error_message']); ?>
          <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
      <?php endif; ?>

      <div class="card">
        <div class="card-body">
          <form action="/orphan_management/modules/orphans/edit.php?id=<?php echo $orphan_id; ?>" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            
            <div class="row">
              <div class="col-md-6 form-group">
                <label class="font-weight-medium">Full Name <span class="text-danger">*</span></label>
                <input type="text" name="full_name" class="form-control" value="<?php echo escape($orphan['full_name']); ?>" required>
              </div>
              <div class="col-md-6 form-group">
                <label class="font-weight-medium">Gender <span class="text-danger">*</span></label>
                <select name="gender" class="form-control" required>
                  <option value="Male" <?php echo ($orphan['gender'] === 'Male') ? 'selected' : ''; ?>>Male</option>
                  <option value="Female" <?php echo ($orphan['gender'] === 'Female') ? 'selected' : ''; ?>>Female</option>
                  <option value="Other" <?php echo ($orphan['gender'] === 'Other') ? 'selected' : ''; ?>>Other</option>
                </select>
              </div>
            </div>

            <div class="row">
              <div class="col-md-6 form-group">
                <label class="font-weight-medium">Date of Birth <span class="text-danger">*</span></label>
                <input type="date" name="date_of_birth" class="form-control" value="<?php echo $orphan['date_of_birth']; ?>" required>
              </div>
              <div class="col-md-6 form-group">
                <label class="font-weight-medium">Admission Date <span class="text-danger">*</span></label>
                <input type="date" name="admission_date" class="form-control" value="<?php echo $orphan['admission_date']; ?>" required>
              </div>
            </div>

            <div class="row">
              <div class="col-md-6 form-group">
                <label class="font-weight-medium">Health Status</label>
                <input type="text" name="health_status" class="form-control" value="<?php echo escape($orphan['health_status']); ?>">
              </div>
              <div class="col-md-6 form-group">
                <label class="font-weight-medium">Education Level</label>
                <input type="text" name="education_level" class="form-control" value="<?php echo escape($orphan['education_level']); ?>">
              </div>
            </div>

            <div class="row">
              <div class="col-md-6 form-group">
                <label class="font-weight-medium">System Status <span class="text-danger">*</span></label>
                <select name="status" class="form-control" required>
                  <option value="Active" <?php echo ($orphan['status'] === 'Active') ? 'selected' : ''; ?>>Active</option>
                  <option value="Sponsored" <?php echo ($orphan['status'] === 'Sponsored') ? 'selected' : ''; ?>>Sponsored</option>
                  <option value="Adopted" <?php echo ($orphan['status'] === 'Adopted') ? 'selected' : ''; ?>>Adopted</option>
                  <option value="Inactive" <?php echo ($orphan['status'] === 'Inactive') ? 'selected' : ''; ?>>Inactive</option>
                </select>
              </div>
              <div class="col-md-6 form-group">
                <label class="font-weight-medium">Update Profile Photo</label>
                <div class="custom-file">
                  <input type="file" name="photo" class="custom-file-input" id="customFile" accept="image/*">
                  <label class="custom-file-label" for="customFile">Choose new file if updating</label>
                </div>
                <?php if (!empty($orphan['photo'])): ?>
                  <span class="text-xs text-muted mt-1 d-block">Current: <code><?php echo escape($orphan['photo']); ?></code></span>
                <?php endif; ?>
              </div>
            </div>

            <div class="form-group">
              <label class="font-weight-medium">Guardian / Background Information</label>
              <textarea name="guardian_information" class="form-control" rows="3"><?php echo escape($orphan['guardian_information']); ?></textarea>
            </div>

            <button type="submit" class="btn btn-primary px-4 py-2 mt-2">
              <i class="fas fa-save mr-1"></i> Update Record
            </button>
          </form>
        </div>
      </div>

    </div>
  </section>
</div>

<script>
  window.addEventListener('DOMContentLoaded', () => {
    $('.custom-file-input').on('change', function() {
      let fileName = $(this).val().split('\\').pop();
      $(this).next('.custom-file-label').addClass("selected").html(fileName);
    });
  });
</script>

<?php
include __DIR__ . '/../../includes/footer.php';
?>
