<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth_middleware.php';

// Restricted to Admin and Donor
checkRole(['admin', 'donor']);

$role = $_SESSION['role'];
$csrf_token = getCsrfToken();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken($_POST['csrf_token'] ?? '');

    $amount = (float)$_POST['amount'] ?? 0;
    $payment_method = $_POST['payment_method'] ?? '';
    $donation_date = $_POST['donation_date'] ?? date('Y-m-d');
    $notes = trim($_POST['notes'] ?? '');
    $account_id = isset($_POST['account_id']) ? (int)$_POST['account_id'] : 1;
    
    // Determine donor ID
    if ($role === 'donor') {
        $donor_id = $_SESSION['donor_id'];
    } else {
        $donor_id = isset($_POST['donor_id']) ? (int)$_POST['donor_id'] : 0;
    }

    if ($amount <= 0 || empty($payment_method) || $donor_id <= 0 || $account_id <= 0) {
        $_SESSION['error_message'] = "Please provide a valid amount, select a donor, pick a payment method, and select a destination fund.";
        header("Location: /orphan_management/modules/donations/create.php");
        exit;
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            INSERT INTO donations (donor_id, amount, payment_method, donation_date, notes, account_id) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$donor_id, $amount, $payment_method, $donation_date, $notes, $account_id]);

        // Increment account balance
        $stmtUpdate = $pdo->prepare("
            UPDATE finance_accounts 
            SET balance = balance + ? 
            WHERE account_id = ?
        ");
        $stmtUpdate->execute([$amount, $account_id]);

        $pdo->commit();

        $_SESSION['success_message'] = "Donation has been successfully processed, recorded, and credited to the fund!";
        header("Location: /orphan_management/modules/donations/index.php");
        exit;
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $_SESSION['error_message'] = "Failed to record transaction: " . $e->getMessage();
        header("Location: /orphan_management/modules/donations/create.php");
        exit;
    }
}

// Fetch donors for Admin selector and income accounts
$donors = [];
$funds = [];
try {
    if ($role === 'admin') {
        $donors = $pdo->query("SELECT donor_id, full_name, email FROM donors ORDER BY full_name ASC")->fetchAll();
    }
    $funds = $pdo->query("SELECT account_id, account_name FROM finance_accounts WHERE account_type = 'income' ORDER BY account_id ASC")->fetchAll();
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
          <h1 class="m-0 font-weight-bold text-dark">Make a Donation</h1>
        </div>
        <div class="col-sm-6 text-right">
          <a href="/orphan_management/modules/donations/index.php" class="btn btn-secondary">
            <i class="fas fa-history mr-1"></i> Transaction History
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
              <form action="/orphan_management/modules/donations/create.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                
                <?php if ($role === 'admin'): ?>
                  <!-- Admin donor selector -->
                  <div class="form-group">
                    <label class="font-weight-medium">Select Registered Donor <span class="text-danger">*</span></label>
                    <select name="donor_id" class="form-control" required>
                      <option value="">-- Choose Donor --</option>
                      <?php foreach ($donors as $dn): ?>
                        <option value="<?php echo $dn['donor_id']; ?>">
                          <?php echo escape($dn['full_name']); ?> (<?php echo escape($dn['email']); ?>)
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  
                  <div class="form-group">
                    <label class="font-weight-medium">Donation Date <span class="text-danger">*</span></label>
                    <input type="date" name="donation_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                  </div>
                <?php else: ?>
                  <!-- Donor display of their name -->
                  <div class="form-group">
                    <label class="font-weight-medium">Donor Name</label>
                    <input type="text" class="form-control text-muted" value="<?php echo escape($_SESSION['full_name']); ?>" disabled>
                  </div>
                <?php endif; ?>

                <div class="form-group">
                  <label class="font-weight-medium">Donation Amount ($) <span class="text-danger">*</span></label>
                  <div class="input-group">
                    <div class="input-group-prepend">
                      <span class="input-group-text">$</span>
                    </div>
                    <input type="number" name="amount" step="0.01" min="1.00" class="form-control" placeholder="0.00" required>
                  </div>
                </div>

                <div class="form-group">
                  <label class="font-weight-medium">Destination Fund / Account <span class="text-danger">*</span></label>
                  <select name="account_id" class="form-control" required>
                    <option value="">-- Select Destination Account --</option>
                    <?php foreach ($funds as $f): ?>
                      <option value="<?php echo $f['account_id']; ?>">
                        <?php echo escape($f['account_name']); ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                  <span class="text-xs text-muted d-block mt-1">Specify which central account this donation should fund.</span>
                </div>

                <div class="form-group">
                  <label class="font-weight-medium">Payment Method <span class="text-danger">*</span></label>
                  <select name="payment_method" class="form-control" id="payMethod" required>
                    <option value="">-- Choose Payment Method --</option>
                    <option value="Credit Card">Credit Card</option>
                    <option value="PayPal">PayPal</option>
                    <option value="Bank Transfer">Bank Transfer</option>
                  </select>
                </div>

                <div class="form-group">
                  <label class="font-weight-medium">Notes / Special Instructions <span class="text-danger">*</span></label>
                  <textarea name="notes" class="form-control" rows="3" placeholder="Enter notes here (e.g. In memory of..., Clothing budget...)" required></textarea>
                </div>

                <?php if ($role === 'donor'): ?>
                  <!-- Simulation card credentials box to look highly premium -->
                  <div id="paymentSimulation" class="border rounded p-3 mb-3 bg-light d-none">
                    <h6 class="font-weight-bold text-dark mb-2"><i class="far fa-credit-card mr-2 text-primary"></i>Simulated Checkout Card Details</h6>
                    <div class="form-group mb-2">
                      <label class="text-xs text-muted mb-1">Card Holder Name</label>
                      <input type="text" class="form-control form-control-sm" value="<?php echo escape($_SESSION['full_name']); ?>" disabled>
                    </div>
                    <div class="row">
                      <div class="col-8 form-group mb-2">
                        <label class="text-xs text-muted mb-1">Card Number</label>
                        <input type="text" class="form-control form-control-sm" placeholder="4000 1234 5678 9010">
                      </div>
                      <div class="col-4 form-group mb-2">
                        <label class="text-xs text-muted mb-1">CVV</label>
                        <input type="text" class="form-control form-control-sm" placeholder="123">
                      </div>
                    </div>
                  </div>
                <?php endif; ?>

                <button type="submit" class="btn btn-success px-4 py-2 mt-2 btn-block">
                  <i class="fas fa-check-circle mr-1"></i> Process Transaction
                </button>
              </form>
            </div>
          </div>
        </div>

        <!-- Info sidepanel -->
        <div class="col-md-5">
          <div class="card h-100 bg-gradient-light border-0 py-3">
            <div class="card-body d-flex flex-column justify-content-center text-center">
              <i class="fas fa-hand-holding-heart text-danger mb-4" style="font-size: 4.5rem;"></i>
              <h4 class="font-weight-bold text-dark">Your Support Changes Lives</h4>
              <p class="text-secondary text-sm px-3 mt-2" style="line-height: 1.6;">
                100% of all public contributions are funneled directly into orphans health care, food supply chains, primary & secondary educational assets, and orphanage facility operations.
              </p>
              <div class="border-top pt-3 mt-3 w-75 mx-auto">
                <span class="text-xs text-muted">A project for online social advocacy and child services administration.</span>
              </div>
            </div>
          </div>
        </div>
      </div>

    </div>
  </section>
</div>

<!-- Script to handle simulation display on dropdown changes -->
<script>
  window.addEventListener('DOMContentLoaded', () => {
    $('#payMethod').on('change', function() {
      let method = $(this).val();
      let simBox = $('#paymentSimulation');
      if (method === 'Credit Card') {
        simBox.removeClass('d-none');
      } else {
        simBox.addClass('d-none');
      }
    });
  });
</script>

<?php
include __DIR__ . '/../../includes/footer.php';
?>
