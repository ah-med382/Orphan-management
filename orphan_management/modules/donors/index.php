<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth_middleware.php';

// Restricted to Admin and Staff
checkRole(['admin', 'staff']);

$csrf_token = getCsrfToken();

// Handle additions & deletions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken($_POST['csrf_token'] ?? '');
    
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add_donor') {
        $full_name = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($full_name) || empty($email) || empty($phone) || empty($password)) {
            $_SESSION['error_message'] = "Full name, email, phone, and password are required.";
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
                        $upload_dir = __DIR__ . '/../../assets/images/';
                        if (!is_dir($upload_dir)) {
                            mkdir($upload_dir, 0777, true);
                        }
                        $profile_image_filename = uniqid('donor_', true) . '.' . $file_ext;
                        if (!move_uploaded_file($file_tmp, $upload_dir . $profile_image_filename)) {
                            $profile_image_filename = null;
                        }
                    }
                }

                $pdo->beginTransaction();
                
                // Check email
                $stmtCheck = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
                $stmtCheck->execute([$email]);
                if ($stmtCheck->fetch()) {
                    $_SESSION['error_message'] = "Email is already registered.";
                    $pdo->rollBack();
                } else {
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $stmtUser = $pdo->prepare("INSERT INTO users (full_name, email, password, role, status) VALUES (?, ?, ?, 'donor', 'active')");
                    $stmtUser->execute([$full_name, $email, $hashed_password]);
                    $user_id = $pdo->lastInsertId();

                    $stmtDonor = $pdo->prepare("INSERT INTO donors (user_id, full_name, phone, email, address, profile_image) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmtDonor->execute([$user_id, $full_name, $phone, $email, $address, $profile_image_filename]);
                    $donor_id = $pdo->lastInsertId();

                    // Optional sponsorship 1
                    $orphan_id_1 = isset($_POST['orphan_id_1']) ? (int)$_POST['orphan_id_1'] : 0;
                    if ($orphan_id_1 > 0) {
                        $stmtO1 = $pdo->prepare("SELECT full_name, status FROM orphans WHERE orphan_id = ?");
                        $stmtO1->execute([$orphan_id_1]);
                        $orp1 = $stmtO1->fetch();

                        if ($orp1 && $orp1['status'] === 'Active') {
                            $stmtS1 = $pdo->prepare("INSERT INTO sponsorships (donor_id, orphan_id, sponsorship_amount, start_date, status) VALUES (?, ?, 150.00, CURDATE(), 'Active')");
                            $stmtS1->execute([$donor_id, $orphan_id_1]);

                            $pdo->prepare("UPDATE orphans SET status = 'Sponsored' WHERE orphan_id = ?")->execute([$orphan_id_1]);

                            $stmtD1 = $pdo->prepare("INSERT INTO donations (donor_id, amount, payment_method, donation_date, notes, account_id) VALUES (?, 150.00, 'Sponsorship Payout', CURDATE(), ?, 2)");
                            $dNotes = "Initial monthly sponsorship payment for child: " . $orp1['full_name'];
                            $stmtD1->execute([$donor_id, $dNotes]);

                            $pdo->prepare("UPDATE finance_accounts SET balance = balance + 150.00 WHERE account_id = 2")->execute();
                        }
                    }

                    // Optional sponsorship 2
                    $orphan_id_2 = isset($_POST['orphan_id_2']) ? (int)$_POST['orphan_id_2'] : 0;
                    if ($orphan_id_2 > 0) {
                        $stmtO2 = $pdo->prepare("SELECT full_name, status FROM orphans WHERE orphan_id = ?");
                        $stmtO2->execute([$orphan_id_2]);
                        $orp2 = $stmtO2->fetch();

                        if ($orp2 && $orp2['status'] === 'Active') {
                            $stmtS2 = $pdo->prepare("INSERT INTO sponsorships (donor_id, orphan_id, sponsorship_amount, start_date, status) VALUES (?, ?, 150.00, CURDATE(), 'Active')");
                            $stmtS2->execute([$donor_id, $orphan_id_2]);

                            $pdo->prepare("UPDATE orphans SET status = 'Sponsored' WHERE orphan_id = ?")->execute([$orphan_id_2]);

                            $stmtD2 = $pdo->prepare("INSERT INTO donations (donor_id, amount, payment_method, donation_date, notes, account_id) VALUES (?, 150.00, 'Sponsorship Payout', CURDATE(), ?, 2)");
                            $dNotes = "Initial monthly sponsorship payment for child: " . $orp2['full_name'];
                            $stmtD2->execute([$donor_id, $dNotes]);

                            $pdo->prepare("UPDATE finance_accounts SET balance = balance + 150.00 WHERE account_id = 2")->execute();
                        }
                    }
                    
                    $pdo->commit();
                    $_SESSION['success_message'] = "Donor added successfully!";
                }
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $_SESSION['error_message'] = "Database error: " . $e->getMessage();
            }
        }
    }
    
    header("Location: /orphan_management/modules/donors/index.php");
    exit;
}

// Handle deletions
if (isset($_GET['delete'])) {
    $donor_id = (int)$_GET['delete'];
    try {
        $pdo->beginTransaction();
        
        // Find user_id associated with donor
        $stmtUserFind = $pdo->prepare("SELECT user_id FROM donors WHERE donor_id = ?");
        $stmtUserFind->execute([$donor_id]);
        $user_id = $stmtUserFind->fetchColumn();

        // Delete donor details
        $stmtDelDonor = $pdo->prepare("DELETE FROM donors WHERE donor_id = ?");
        $stmtDelDonor->execute([$donor_id]);

        // Delete user auth account
        if ($user_id) {
            $stmtDelUser = $pdo->prepare("DELETE FROM users WHERE user_id = ?");
            $stmtDelUser->execute([$user_id]);
        }

        $pdo->commit();
        $_SESSION['success_message'] = "Donor and login account deleted successfully.";
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $_SESSION['error_message'] = "Error deleting donor: " . $e->getMessage();
    }
    header("Location: /orphan_management/modules/donors/index.php");
    exit;
}

// Fetch all donors & active orphans
try {
    $donors = $pdo->query("SELECT * FROM donors ORDER BY full_name ASC")->fetchAll();
    $active_orphans = $pdo->query("SELECT orphan_id, full_name FROM orphans WHERE status = 'Active' ORDER BY full_name ASC")->fetchAll();
} catch (PDOException $e) {
    die("Database query error: " . $e->getMessage());
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
          <h1 class="m-0 font-weight-bold text-dark">Donor Management</h1>
        </div>
        <div class="col-sm-6 text-right">
          <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addDonorModal">
            <i class="fas fa-plus mr-1"></i> Add New Donor
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

      <!-- Donors list table -->
      <div class="card">
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-striped table-hover mb-0">
              <thead>
                <tr>
                  <th>Full Name</th>
                  <th>Email</th>
                  <th>Phone</th>
                  <th>Address</th>
                  <th class="text-right">Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php if (count($donors) > 0): ?>
                  <?php foreach ($donors as $donor): ?>
                    <tr>
                      <td>
                        <div class="d-flex align-items-center">
                          <div class="mr-2">
                            <?php if (!empty($donor['profile_image']) && file_exists(__DIR__ . '/../../assets/images/' . $donor['profile_image'])): ?>
                              <img src="/orphan_management/assets/images/<?php echo escape($donor['profile_image']); ?>" class="img-circle" style="width: 32px; height: 32px; object-fit: cover; border: 1.5px solid #cbd5e1;">
                            <?php else: ?>
                              <div class="bg-indigo text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 32px; height: 32px; font-weight: bold; font-size: 0.8rem; border: 1.5px solid #cbd5e1;">
                                <?php echo strtoupper(substr($donor['full_name'], 0, 1)); ?>
                              </div>
                            <?php endif; ?>
                          </div>
                          <strong><?php echo escape($donor['full_name']); ?></strong>
                        </div>
                      </td>
                      <td><a href="mailto:<?php echo escape($donor['email']); ?>"><?php echo escape($donor['email']); ?></a></td>
                      <td><?php echo escape($donor['phone']); ?></td>
                      <td class="text-muted text-sm"><?php echo escape($donor['address']); ?></td>
                      <td class="text-right">
                        <a href="/orphan_management/modules/donors/edit.php?id=<?php echo $donor['donor_id']; ?>" class="btn btn-sm btn-outline-primary mr-1">
                          <i class="fas fa-edit"></i> Edit
                        </a>
                        <a href="/orphan_management/modules/donors/index.php?delete=<?php echo $donor['donor_id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure you want to delete this donor? This deletes their account and associated profiles.');">
                          <i class="fas fa-trash"></i> Delete
                        </a>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php else: ?>
                  <tr>
                    <td colspan="5" class="text-center py-5 text-muted">No donor records found.</td>
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

<!-- Add Donor Modal -->
<div class="modal fade" id="addDonorModal" tabindex="-1" role="dialog" aria-labelledby="addDonorModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content" style="border-radius: 12px;">
      <div class="modal-header">
        <h5 class="modal-title font-weight-bold" id="addDonorModalLabel">Register New Donor</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <form action="/orphan_management/modules/donors/index.php" method="POST" enctype="multipart/form-data">
        <div class="modal-body">
          <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
          <input type="hidden" name="action" value="add_donor">
          
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
            <label class="text-sm">Physical Address <span class="text-danger">*</span></label>
            <textarea name="address" class="form-control" rows="2" placeholder="Donor address details..." required></textarea>
          </div>
          <div class="form-group">
            <label class="text-sm">Login Password <span class="text-danger">*</span></label>
            <input type="password" name="password" class="form-control" placeholder="Assign system password" required>
          </div>
          <div class="form-group">
            <label class="text-sm">Profile Image <span class="text-danger">*</span></label>
            <div class="custom-file">
              <input type="file" name="profile_image" class="custom-file-input" id="donorProfileImage" accept="image/*" required>
              <label class="custom-file-label text-xs" for="donorProfileImage">Choose image...</label>
            </div>
          </div>
          <div class="row">
            <div class="col-6 form-group">
              <label class="text-sm">Sponsor Child 1 (Optional)</label>
              <select name="orphan_id_1" class="form-control text-xs">
                <option value="">-- None --</option>
                <?php foreach ($active_orphans as $orp): ?>
                  <option value="<?php echo $orp['orphan_id']; ?>"><?php echo escape($orp['full_name']); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-6 form-group">
              <label class="text-sm">Sponsor Child 2 (Optional)</label>
              <select name="orphan_id_2" class="form-control text-xs">
                <option value="">-- None --</option>
                <?php foreach ($active_orphans as $orp): ?>
                  <option value="<?php echo $orp['orphan_id']; ?>"><?php echo escape($orp['full_name']); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
          <button type="submit" class="btn btn-primary">Create Donor Account</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php
include __DIR__ . '/../../includes/footer.php';
?>
