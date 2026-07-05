<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth_middleware.php';

// Restricted to Donors
checkRole(['donor']);

$donor_id = $_SESSION['donor_id'];
$csrf_token = getCsrfToken();

// Fetch donor details for defaults
try {
    $stmtDonor = $pdo->prepare("SELECT * FROM donors WHERE donor_id = ? LIMIT 1");
    $stmtDonor->execute([$donor_id]);
    $donor = $stmtDonor->fetch();
} catch (PDOException $e) {
    die("Database query error: " . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken($_POST['csrf_token'] ?? '');

    $applicant_name = trim($_POST['applicant_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $orphan_id = (int)$_POST['orphan_id'] ?? 0;
    $request_date = $_POST['request_date'] ?? date('Y-m-d');
    
    if (empty($applicant_name) || empty($phone) || empty($email) || empty($address) || $orphan_id <= 0) {
        $_SESSION['error_message'] = "Please fill in all details and select an orphan.";
        header("Location: /orphan_management/modules/adoptions/create.php");
        exit;
    }

    try {
        // Insert adoption request
        $stmtAdd = $pdo->prepare("
            INSERT INTO adoption_requests (applicant_name, phone, email, address, orphan_id, request_date, status)
            VALUES (?, ?, ?, ?, ?, ?, 'Pending')
        ");
        $stmtAdd->execute([$applicant_name, $phone, $email, $address, $orphan_id, $request_date]);

        $_SESSION['success_message'] = "Your adoption application has been submitted successfully! An administrator will review your request.";
        header("Location: /orphan_management/donor/index.php");
        exit;
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Failed to submit request: " . $e->getMessage();
        header("Location: /orphan_management/modules/adoptions/create.php");
        exit;
    }
}

// Fetch list of orphans who are NOT Adopted or Inactive
try {
    $adoptable_orphans = $pdo->query("
        SELECT orphan_id, full_name, gender, date_of_birth, status 
        FROM orphans 
        WHERE status IN ('Active', 'Sponsored') 
        ORDER BY full_name ASC
    ")->fetchAll();
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
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
          <h1 class="m-0 font-weight-bold text-dark">Submit Adoption Request</h1>
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

      <div class="row">
        <div class="col-md-8">
          <div class="card">
            <div class="card-header bg-white border-0 pt-3">
              <h5 class="card-title font-weight-bold text-dark"><i class="fas fa-file-contract text-primary mr-2"></i>Adoption Application Form</h5>
            </div>
            <div class="card-body">
              <form action="/orphan_management/modules/adoptions/create.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                
                <div class="row">
                  <div class="col-md-6 form-group">
                    <label class="text-sm font-weight-medium">Applicant Full Name <span class="text-danger">*</span></label>
                    <input type="text" name="applicant_name" class="form-control" value="<?php echo escape($donor['full_name']); ?>" required>
                  </div>
                  <div class="col-md-6 form-group">
                    <label class="text-sm font-weight-medium">Contact Phone <span class="text-danger">*</span></label>
                    <input type="text" name="phone" class="form-control" value="<?php echo escape($donor['phone']); ?>" required>
                  </div>
                </div>

                <div class="row">
                  <div class="col-md-6 form-group">
                    <label class="text-sm font-weight-medium">Email Address <span class="text-danger">*</span></label>
                    <input type="email" name="email" class="form-control" value="<?php echo escape($donor['email']); ?>" required>
                  </div>
                  <div class="col-md-6 form-group">
                    <label class="text-sm font-weight-medium">Select Child <span class="text-danger">*</span></label>
                    <select name="orphan_id" class="form-control" required>
                      <option value="">-- Choose Orphan --</option>
                      <?php foreach ($adoptable_orphans as $orp): ?>
                        <?php 
                          $dob = new DateTime($orp['date_of_birth']);
                          $age = (new DateTime())->diff($dob)->y;
                        ?>
                        <option value="<?php echo $orp['orphan_id']; ?>">
                          <?php echo escape($orp['full_name']); ?> (<?php echo escape($orp['gender']); ?>, <?php echo $age; ?> yrs) - [<?php echo escape($orp['status']); ?>]
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                </div>

                <div class="form-group">
                  <label class="text-sm font-weight-medium">Physical Address <span class="text-danger">*</span></label>
                  <textarea name="address" class="form-control" rows="3" required><?php echo escape($donor['address']); ?></textarea>
                </div>

                <div class="form-group">
                  <label class="text-sm font-weight-medium">Application Date <span class="text-danger">*</span></label>
                  <input type="date" name="request_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                </div>

                <div class="form-group custom-control custom-checkbox mb-3">
                  <input type="checkbox" class="custom-control-input" id="termsCheck" required>
                  <label class="custom-control-label text-sm text-secondary" for="termsCheck">
                    I declare that all the information provided in this adoption application is truthful. I understand that the orphanage administration will conduct home checks and verification visits before final endorsement.
                  </label>
                </div>

                <button type="submit" class="btn btn-primary px-4 py-2">
                  <i class="fas fa-paper-plane mr-1"></i> Submit Adoption Request
                </button>
              </form>
            </div>
          </div>
        </div>

        <div class="col-md-4">
          <div class="card bg-light border-0 py-2">
            <div class="card-body">
              <h6 class="font-weight-bold text-dark"><i class="fas fa-info-circle text-primary mr-2"></i>Adoption Guidelines</h6>
              <ul class="text-xs text-secondary pl-3 mt-2" style="line-height: 1.7;">
                <li class="mb-2">Applicants must be registered donors of our organization with a clear support history.</li>
                <li class="mb-2">A background screening, character reference validation, and facility visit check are required.</li>
                <li class="mb-2">Once approved by orphanage administration, legal court procedures will be initiated.</li>
              </ul>
            </div>
          </div>
        </div>
      </div>

    </div>
  </section>
</div>

<?php
include __DIR__ . '/../../includes/footer.php';
?>
