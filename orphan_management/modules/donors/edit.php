<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth_middleware.php';

// Restricted to Admin only
checkRole(['admin']);

$csrf_token = getCsrfToken();
$donor_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

try {
    $stmt = $pdo->prepare("SELECT * FROM donors WHERE donor_id = ? LIMIT 1");
    $stmt->execute([$donor_id]);
    $donor = $stmt->fetch();

    if (!$donor) {
        $_SESSION['error_message'] = "Donor not found.";
        header("Location: /orphan_management/modules/donors/index.php");
        exit;
    }
} catch (PDOException $e) {
    die("Database query error: " . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken($_POST['csrf_token'] ?? '');
    
    $full_name = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $password = $_POST['password'] ?? ''; // Optional password update

    if (empty($full_name) || empty($phone)) {
        $_SESSION['error_message'] = "Name and Phone number are required.";
        header("Location: /orphan_management/modules/donors/edit.php?id=" . $donor_id);
        exit;
    }

    try {
        $pdo->beginTransaction();
        
        // Update Donors table
        $stmtUpdateDonor = $pdo->prepare("UPDATE donors SET full_name = ?, phone = ?, address = ? WHERE donor_id = ?");
        $stmtUpdateDonor->execute([$full_name, $phone, $address, $donor_id]);

        // Update Users table name
        if ($donor['user_id']) {
            if (!empty($password)) {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmtUpdateUser = $pdo->prepare("UPDATE users SET full_name = ?, password = ? WHERE user_id = ?");
                $stmtUpdateUser->execute([$full_name, $hashed_password, $donor['user_id']]);
            } else {
                $stmtUpdateUser = $pdo->prepare("UPDATE users SET full_name = ? WHERE user_id = ?");
                $stmtUpdateUser->execute([$full_name, $donor['user_id']]);
            }
        }

        $pdo->commit();
        $_SESSION['success_message'] = "Donor updated successfully!";
        header("Location: /orphan_management/modules/donors/index.php");
        exit;
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $_SESSION['error_message'] = "Database write error: " . $e->getMessage();
        header("Location: /orphan_management/modules/donors/edit.php?id=" . $donor_id);
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
          <h1 class="m-0 font-weight-bold text-dark">Edit Donor Info</h1>
        </div>
        <div class="col-sm-6 text-right">
          <a href="/orphan_management/modules/donors/index.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left mr-1"></i> Back
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

      <div class="card col-md-8 px-0">
        <div class="card-body">
          <form action="/orphan_management/modules/donors/edit.php?id=<?php echo $donor_id; ?>" method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            
            <div class="form-group">
              <label class="font-weight-medium">Full Name <span class="text-danger">*</span></label>
              <input type="text" name="full_name" class="form-control" value="<?php echo escape($donor['full_name']); ?>" required>
            </div>
            
            <div class="form-group">
              <label class="font-weight-medium">Email Address (Cannot change login ID)</label>
              <input type="email" class="form-control text-muted" value="<?php echo escape($donor['email']); ?>" disabled>
            </div>
            
            <div class="form-group">
              <label class="font-weight-medium">Phone Number <span class="text-danger">*</span></label>
              <input type="text" name="phone" class="form-control" value="<?php echo escape($donor['phone']); ?>" required>
            </div>
            
            <div class="form-group">
              <label class="font-weight-medium">Physical Address</label>
              <textarea name="address" class="form-control" rows="3"><?php echo escape($donor['address']); ?></textarea>
            </div>

            <div class="form-group">
              <label class="font-weight-medium">Update Login Password (Leave blank to keep current)</label>
              <input type="password" name="password" class="form-control" placeholder="Enter new password if updating">
            </div>

            <button type="submit" class="btn btn-primary px-4 py-2 mt-2">
              <i class="fas fa-save mr-1"></i> Update Donor
            </button>
          </form>
        </div>
      </div>

    </div>
  </section>
</div>

<?php
include __DIR__ . '/../../includes/footer.php';
?>
