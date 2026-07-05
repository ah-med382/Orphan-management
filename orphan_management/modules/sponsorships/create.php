<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth_middleware.php';

// Restricted to Admin and Donor
checkRole(['admin', 'donor']);

$role = $_SESSION['role'];
$csrf_token = getCsrfToken();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken($_POST['csrf_token'] ?? '');

    $orphan_id = (int)$_POST['orphan_id'] ?? 0;
    $sponsorship_amount = (float)$_POST['sponsorship_amount'] ?? 0;
    $start_date = $_POST['start_date'] ?? date('Y-m-d');
    
    if ($role === 'donor') {
        $donor_id = $_SESSION['donor_id'];
    } else {
        $donor_id = isset($_POST['donor_id']) ? (int)$_POST['donor_id'] : 0;
    }

    if ($orphan_id <= 0 || $donor_id <= 0 || $sponsorship_amount <= 0) {
        $_SESSION['error_message'] = "Please select a valid orphan, donor, and sponsorship monthly amount.";
        header("Location: /orphan_management/modules/sponsorships/create.php");
        exit;
    }

    try {
        $pdo->beginTransaction();

        // Double check if orphan is still Active
        $stmtCheck = $pdo->prepare("SELECT status FROM orphans WHERE orphan_id = ? LIMIT 1");
        $stmtCheck->execute([$orphan_id]);
        $status = $stmtCheck->fetchColumn();

        if ($status !== 'Active') {
            $_SESSION['error_message'] = "The selected orphan is already sponsored or adopted.";
            $pdo->rollBack();
            header("Location: /orphan_management/modules/sponsorships/create.php");
            exit;
        }

        // Insert Sponsorship Record
        $stmtSpons = $pdo->prepare("
            INSERT INTO sponsorships (donor_id, orphan_id, sponsorship_amount, start_date, status) 
            VALUES (?, ?, ?, ?, 'Active')
        ");
        $stmtSpons->execute([$donor_id, $orphan_id, $sponsorship_amount, $start_date]);

        // Update Orphan Status
        $stmtOrphan = $pdo->prepare("UPDATE orphans SET status = 'Sponsored' WHERE orphan_id = ?");
        $stmtOrphan->execute([$orphan_id]);

        // Get Orphan's name for donation notes
        $stmtOrphanName = $pdo->prepare("SELECT full_name FROM orphans WHERE orphan_id = ? LIMIT 1");
        $stmtOrphanName->execute([$orphan_id]);
        $orphan_name = $stmtOrphanName->fetchColumn() ?? 'Orphan';

        // Automatically log an initial monthly payment donation in Central Finance under Orphan Sponsorship Fund (account_id = 2)
        $stmtDonation = $pdo->prepare("
            INSERT INTO donations (donor_id, amount, payment_method, donation_date, notes, account_id) 
            VALUES (?, ?, 'Sponsorship Payout', ?, ?, 2)
        ");
        $notes = "Initial monthly sponsorship payment for child: " . $orphan_name;
        $stmtDonation->execute([$donor_id, $sponsorship_amount, $start_date, $notes]);

        // Increment the Orphan Sponsorship Fund balance
        $stmtUpdateBalance = $pdo->prepare("
            UPDATE finance_accounts 
            SET balance = balance + ? 
            WHERE account_id = 2
        ");
        $stmtUpdateBalance->execute([$sponsorship_amount]);

        $pdo->commit();
        $_SESSION['success_message'] = "Thank you! Sponsorship allocation completed successfully.";
        header("Location: /orphan_management/modules/sponsorships/index.php");
        exit;
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $_SESSION['error_message'] = "Transaction failed: " . $e->getMessage();
        header("Location: /orphan_management/modules/sponsorships/create.php");
        exit;
    }
}

// Fetch list of active orphans who need sponsors (status = 'Active')
try {
    $active_orphans = $pdo->query("SELECT orphan_id, full_name, gender, date_of_birth FROM orphans WHERE status = 'Active' ORDER BY full_name ASC")->fetchAll();
    
    // Fetch donors if Admin
    $all_donors = [];
    if ($role === 'admin') {
        $all_donors = $pdo->query("SELECT donor_id, full_name, email FROM donors ORDER BY full_name ASC")->fetchAll();
    }
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
          <h1 class="m-0 font-weight-bold text-dark">Sponsor an Orphan</h1>
        </div>
        <div class="col-sm-6 text-right">
          <a href="/orphan_management/modules/sponsorships/index.php" class="btn btn-secondary">
            <i class="fas fa-list mr-1"></i> View Sponsorships
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

      <div class="row">
        <!-- Main Form -->
        <div class="col-md-7">
          <div class="card">
            <div class="card-body">
              <form action="/orphan_management/modules/sponsorships/create.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                
                <?php if ($role === 'admin'): ?>
                  <!-- Admin select donor -->
                  <div class="form-group">
                    <label class="font-weight-medium">Select Sponsor (Donor) <span class="text-danger">*</span></label>
                    <select name="donor_id" class="form-control" required>
                      <option value="">-- Choose Sponsor --</option>
                      <?php foreach ($all_donors as $dn): ?>
                        <option value="<?php echo $dn['donor_id']; ?>">
                          <?php echo escape($dn['full_name']); ?> (<?php echo escape($dn['email']); ?>)
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                <?php endif; ?>

                 <!-- Select Orphan from active list -->
                <div class="form-group">
                  <label class="font-weight-medium">Select Child in Need <span class="text-danger">*</span></label>
                  <select name="orphan_id" class="form-control" required>
                    <option value="">-- Choose Orphan --</option>
                    <?php foreach ($active_orphans as $orp): ?>
                      <?php 
                        $dob = new DateTime($orp['date_of_birth']);
                        $age = (new DateTime())->diff($dob)->y;
                        $selected = (isset($_GET['orphan_id']) && (int)$_GET['orphan_id'] === (int)$orp['orphan_id']) ? 'selected' : '';
                      ?>
                      <option value="<?php echo $orp['orphan_id']; ?>" <?php echo $selected; ?>>
                        <?php echo escape($orp['full_name']); ?> (<?php echo escape($orp['gender']); ?>, <?php echo $age; ?> yrs)
                      </option>
                    <?php endforeach; ?>
                  </select>
                  <?php if (count($active_orphans) === 0): ?>
                    <span class="text-xs text-danger mt-1 d-block"><i class="fas fa-exclamation-triangle"></i> All orphans are currently sponsored.</span>
                  <?php endif; ?>
                </div>

                <div class="form-group">
                  <label class="font-weight-medium">Monthly Sponsorship Amount ($) <span class="text-danger">*</span></label>
                  <div class="input-group">
                    <div class="input-group-prepend">
                      <span class="input-group-text">$</span>
                    </div>
                    <input type="number" name="sponsorship_amount" min="10" step="5" class="form-control" value="150" required>
                  </div>
                  <span class="text-xs text-muted mt-1 d-block">Recommended minimum: $150.00 per month for basic boarding and tuition support.</span>
                </div>

                <div class="form-group">
                  <label class="font-weight-medium">Start Date <span class="text-danger">*</span></label>
                  <input type="date" name="start_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                </div>

                <button type="submit" class="btn btn-primary btn-block px-4 py-2 mt-2" <?php echo (count($active_orphans) === 0) ? 'disabled' : ''; ?>>
                  <i class="fas fa-heart mr-1"></i> Register Sponsorship
                </button>
              </form>
            </div>
          </div>
        </div>

        <!-- Info Card -->
        <div class="col-md-5">
          <div class="card h-100 bg-gradient-light border-0 py-3">
            <div class="card-body d-flex flex-column justify-content-center text-center">
              <i class="fas fa-ribbon text-info mb-4" style="font-size: 4.5rem;"></i>
              <h4 class="font-weight-bold text-dark">Why Sponsor a Child?</h4>
              <p class="text-secondary text-sm px-3 mt-2" style="line-height: 1.6;">
                Sponsoring a child connects you with an orphan in need. Your monthly pledge provides nutritional care, specialized health checks, custom tuition materials, clothing, and primary school education options.
              </p>
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
