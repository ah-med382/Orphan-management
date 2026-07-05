<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth_middleware.php';

// Restricted to Admin only
checkRole(['admin']);

$csrf_token = getCsrfToken();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken($_POST['csrf_token'] ?? '');

    $full_name = trim($_POST['full_name'] ?? '');
    $gender = $_POST['gender'] ?? '';
    $date_of_birth = $_POST['date_of_birth'] ?? '';
    $admission_date = $_POST['admission_date'] ?? '';
    $health_status = trim($_POST['health_status'] ?? 'Healthy');
    $education_level = trim($_POST['education_level'] ?? 'None');
    $guardian_information = trim($_POST['guardian_information'] ?? '');
    $status = $_POST['status'] ?? 'Active';

    // Validation
    if (empty($full_name) || empty($gender) || empty($date_of_birth) || empty($admission_date)) {
        $_SESSION['error_message'] = "Full name, gender, date of birth, and admission date are required.";
        header("Location: /orphan_management/modules/orphans/create.php");
        exit;
    }

    $photo_filename = null;
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $file_tmp = $_FILES['photo']['tmp_name'];
        $file_name = $_FILES['photo']['name'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        $allowed_exts = ['jpg', 'jpeg', 'png', 'gif'];

        if (in_array($file_ext, $allowed_exts)) {
            // Ensure directory exists
            $upload_dir = __DIR__ . '/../../assets/images/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            // Create a unique name
            $photo_filename = uniqid('orphan_', true) . '.' . $file_ext;
            $dest_path = $upload_dir . $photo_filename;

            if (!move_uploaded_file($file_tmp, $dest_path)) {
                $photo_filename = null;
                $_SESSION['error_message'] = "Failed to upload photo. File transfer error.";
            }
        } else {
            $_SESSION['error_message'] = "Invalid photo format. Only JPG, JPEG, PNG, and GIF allowed.";
            header("Location: /orphan_management/modules/orphans/create.php");
            exit;
        }
    }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO orphans (full_name, gender, date_of_birth, admission_date, photo, health_status, education_level, guardian_information, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $full_name,
            $gender,
            $date_of_birth,
            $admission_date,
            $photo_filename,
            $health_status,
            $education_level,
            $guardian_information,
            $status
        ]);

        $_SESSION['success_message'] = "Orphan registered successfully!";
        header("Location: /orphan_management/modules/orphans/index.php");
        exit;
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Database error: " . $e->getMessage();
        header("Location: /orphan_management/modules/orphans/create.php");
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
          <h1 class="m-0 font-weight-bold text-dark">Register Orphan</h1>
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
          <form action="/orphan_management/modules/orphans/create.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            
            <div class="row">
              <!-- Name & Gender -->
              <div class="col-md-6 form-group">
                <label class="font-weight-medium">Full Name <span class="text-danger">*</span></label>
                <input type="text" name="full_name" class="form-control" placeholder="Enter full name" required>
              </div>
              <div class="col-md-6 form-group">
                <label class="font-weight-medium">Gender <span class="text-danger">*</span></label>
                <select name="gender" class="form-control" required>
                  <option value="">Select Gender</option>
                  <option value="Male">Male</option>
                  <option value="Female">Female</option>
                  <option value="Other">Other</option>
                </select>
              </div>
            </div>

            <div class="row">
              <!-- DOB & Admission -->
              <div class="col-md-6 form-group">
                <label class="font-weight-medium">Date of Birth <span class="text-danger">*</span></label>
                <input type="date" name="date_of_birth" class="form-control" required>
              </div>
              <div class="col-md-6 form-group">
                <label class="font-weight-medium">Admission Date <span class="text-danger">*</span></label>
                <input type="date" name="admission_date" class="form-control" value="<?php echo date('Y-day'); ?>" required>
              </div>
            </div>

            <div class="row">
              <!-- Health & Education -->
              <div class="col-md-6 form-group">
                <label class="font-weight-medium">Initial Health Status <span class="text-danger">*</span></label>
                <input type="text" name="health_status" class="form-control" placeholder="e.g. Healthy, Asthmatic" value="Healthy" required>
              </div>
              <div class="col-md-6 form-group">
                <label class="font-weight-medium">Initial Education Level <span class="text-danger">*</span></label>
                <input type="text" name="education_level" class="form-control" placeholder="e.g. Kindergarten, 3rd Grade" value="None" required>
              </div>
            </div>

            <div class="row">
              <!-- Status & Photo -->
              <div class="col-md-6 form-group">
                <label class="font-weight-medium">System Status <span class="text-danger">*</span></label>
                <select name="status" class="form-control" required>
                  <option value="Active">Active</option>
                  <option value="Sponsored">Sponsored</option>
                  <option value="Adopted">Adopted</option>
                  <option value="Inactive">Inactive</option>
                </select>
              </div>
              <div class="col-md-6 form-group">
                <label class="font-weight-medium">Profile Photo <span class="text-danger">*</span></label>
                <div class="custom-file">
                  <input type="file" name="photo" class="custom-file-input" id="customFile" accept="image/*" required>
                  <label class="custom-file-label" for="customFile">Choose image file</label>
                </div>
              </div>
            </div>

            <div class="form-group">
              <label class="font-weight-medium">Guardian / Background Information <span class="text-danger">*</span></label>
              <textarea name="guardian_information" class="form-control" rows="3" placeholder="Enter background history, known relatives, case reports..." required></textarea>
            </div>

            <button type="submit" class="btn btn-primary px-4 py-2 mt-2">
              <i class="fas fa-save mr-1"></i> Register Orphan
            </button>
          </form>
        </div>
      </div>

    </div>
  </section>
</div>

<!-- Custom File Input Script -->
<script>
  window.addEventListener('DOMContentLoaded', () => {
    // Show chosen file name in custom file inputs
    $('.custom-file-input').on('change', function() {
      let fileName = $(this).val().split('\\').pop();
      $(this).next('.custom-file-label').addClass("selected").html(fileName);
    });
  });
</script>

<?php
include __DIR__ . '/../../includes/footer.php';
?>
