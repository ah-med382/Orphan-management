<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth_middleware.php';

// Restricted to Admin, Staff, and Donor
checkRole(['admin', 'staff', 'donor']);

$role = $_SESSION['role'];
$donor_id = $_SESSION['donor_id'] ?? null;

// Query filters
$payment_method = trim($_GET['payment_method'] ?? '');
$donor_filter = isset($_GET['donor_filter']) ? (int)$_GET['donor_filter'] : 0;

$query = "
    SELECT d.*, dn.full_name AS donor_name, dn.email AS donor_email, fa.account_name 
    FROM donations d 
    JOIN donors dn ON d.donor_id = dn.donor_id 
    LEFT JOIN finance_accounts fa ON d.account_id = fa.account_id
    WHERE 1=1
";
$params = [];

if ($role === 'donor') {
    $query .= " AND d.donor_id = ?";
    $params[] = $donor_id;
} else {
    // Admin filters
    if ($donor_filter > 0) {
        $query .= " AND d.donor_id = ?";
        $params[] = $donor_filter;
    }
}

if ($payment_method !== '') {
    $query .= " AND d.payment_method = ?";
    $params[] = $payment_method;
}

$query .= " ORDER BY d.donation_id DESC";

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $donations = $stmt->fetchAll();
    
    // Fetch donors list for dropdown filter (Admin only)
    $all_donors = [];
    if ($role === 'admin') {
        $all_donors = $pdo->query("SELECT donor_id, full_name FROM donors ORDER BY full_name ASC")->fetchAll();
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
          <h1 class="m-0 font-weight-bold text-dark">Donation Transactions</h1>
        </div>
        <div class="col-sm-6 text-right">
          <a href="/orphan_management/modules/donations/create.php" class="btn btn-primary">
            <i class="fas fa-plus-circle mr-1"></i> <?php echo ($role === 'admin') ? 'Record Offline Donation' : 'Process New Donation'; ?>
          </a>
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

      <!-- Filter bar -->
      <div class="card mb-4">
        <div class="card-body">
          <form method="GET" action="">
            <div class="row">
              <?php if ($role === 'admin'): ?>
                <div class="col-md-5 mb-2 mb-md-0">
                  <select name="donor_filter" class="form-control">
                    <option value="">All Donors</option>
                    <?php foreach ($all_donors as $dn): ?>
                      <option value="<?php echo $dn['donor_id']; ?>" <?php echo ($donor_filter == $dn['donor_id']) ? 'selected' : ''; ?>>
                        <?php echo escape($dn['full_name']); ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
              <?php endif; ?>
              <div class="<?php echo ($role === 'admin') ? 'col-md-5' : 'col-md-9'; ?> mb-2 mb-md-0">
                <select name="payment_method" class="form-control">
                  <option value="">All Payment Methods</option>
                  <option value="Credit Card" <?php echo ($payment_method === 'Credit Card') ? 'selected' : ''; ?>>Credit Card</option>
                  <option value="PayPal" <?php echo ($payment_method === 'PayPal') ? 'selected' : ''; ?>>PayPal</option>
                  <option value="Bank Transfer" <?php echo ($payment_method === 'Bank Transfer') ? 'selected' : ''; ?>>Bank Transfer</option>
                </select>
              </div>
              <div class="col-md-2">
                <button type="submit" class="btn btn-primary btn-block">Search</button>
              </div>
            </div>
          </form>
        </div>
      </div>

      <!-- Donations list -->
      <div class="card">
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-striped table-hover mb-0">
              <thead>
                <tr>
                  <th>Transaction ID</th>
                  <th>Donor</th>
                  <th>Fund Allocation</th>
                  <th>Amount</th>
                  <th>Payment Method</th>
                  <th>Date</th>
                  <th>Notes</th>
                  <th class="text-right">Receipt</th>
                </tr>
              </thead>
              <tbody>
                <?php if (count($donations) > 0): ?>
                  <?php foreach ($donations as $donation): ?>
                    <tr>
                      <td><code>#TXN-<?php echo str_pad($donation['donation_id'], 5, '0', STR_PAD_LEFT); ?></code></td>
                      <td>
                        <strong><?php echo escape($donation['donor_name']); ?></strong>
                        <span class="text-xs text-muted d-block"><?php echo escape($donation['donor_email']); ?></span>
                      </td>
                      <td><span class="badge badge-light border text-dark"><?php echo escape($donation['account_name'] ?? 'General Fund'); ?></span></td>
                      <td class="font-weight-bold text-success">$<?php echo number_format($donation['amount'], 2); ?></td>
                      <td><span class="badge badge-secondary"><?php echo escape($donation['payment_method']); ?></span></td>
                      <td class="text-muted"><?php echo date('M d, Y', strtotime($donation['donation_date'])); ?></td>
                      <td class="text-xs text-muted" style="max-width: 180px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                        <?php echo escape($donation['notes']); ?>
                      </td>
                      <td class="text-right">
                        <a href="/orphan_management/modules/donations/print_receipt.php?id=<?php echo $donation['donation_id']; ?>" target="_blank" class="btn btn-xs btn-outline-info">
                          <i class="fas fa-print mr-1"></i> Print Receipt
                        </a>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php else: ?>
                  <tr>
                    <td colspan="8" class="text-center py-5 text-muted">No donation transactions found.</td>
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

<?php
include __DIR__ . '/../../includes/footer.php';
?>
