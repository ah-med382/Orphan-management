<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_middleware.php';

// Restricted to Admin only
checkRole(['admin']);

$csrf_token = getCsrfToken();

// Handle Salary Payment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'pay_salary') {
    verifyCsrfToken($_POST['csrf_token'] ?? '');
    
    $staff_id = (int)($_POST['staff_id'] ?? 0);
    $amount = (float)($_POST['amount'] ?? 0);
    $source_account_id = (int)($_POST['source_account_id'] ?? 0);
    $payment_month = trim($_POST['payment_month'] ?? '');
    $payment_date = $_POST['payment_date'] ?? date('Y-m-d');
    $notes = trim($_POST['notes'] ?? '');
    
    if ($staff_id <= 0 || $amount <= 0 || $source_account_id <= 0 || empty($payment_month)) {
        $_SESSION['error_message'] = "Please fill in all required salary payment fields.";
    } else {
        try {
            $pdo->beginTransaction();
            
            // Check source account balance
            $stmtBal = $pdo->prepare("SELECT balance, account_name FROM finance_accounts WHERE account_id = ? LIMIT 1");
            $stmtBal->execute([$source_account_id]);
            $sourceAccount = $stmtBal->fetch();
            
            if (!$sourceAccount || $sourceAccount['balance'] < $amount) {
                $_SESSION['error_message'] = "Insufficient funds in " . ($sourceAccount['account_name'] ?? 'selected account') . ". Available: $" . number_format($sourceAccount['balance'] ?? 0, 2);
                $pdo->rollBack();
            } else {
                // Deduct from source account
                $pdo->prepare("UPDATE finance_accounts SET balance = balance - ? WHERE account_id = ?")->execute([$amount, $source_account_id]);
                
                // Record salary payment
                $pdo->prepare("INSERT INTO salary_payments (staff_id, account_id, amount, payment_date, payment_month, notes) VALUES (?, ?, ?, ?, ?, ?)")
                    ->execute([$staff_id, $source_account_id, $amount, $payment_date, $payment_month, $notes]);
                
                $pdo->commit();
                $_SESSION['success_message'] = "Salary of $" . number_format($amount, 2) . " paid successfully from " . escape($sourceAccount['account_name']) . "!";
            }
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $_SESSION['error_message'] = "Transaction failed: " . $e->getMessage();
        }
    }
    header("Location: /orphan_management/admin/finance.php");
    exit;
}

// Fetch summary statistics
$accounts = $pdo->query("SELECT * FROM finance_accounts ORDER BY account_type ASC, account_id ASC")->fetchAll();

$total_fund_balance = 0;
foreach ($accounts as $acc) {
    if ($acc['account_type'] === 'income') {
        $total_fund_balance += $acc['balance'];
    }
}

$total_income = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM donations")->fetchColumn();
$total_salary_paid = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM salary_payments")->fetchColumn();
$net_balance = $total_income - $total_salary_paid;

// Fetch recent donations with account name
$recent_donations = $pdo->query("
    SELECT d.amount, d.donation_date, d.payment_method, dn.full_name AS donor_name, fa.account_name
    FROM donations d
    JOIN donors dn ON d.donor_id = dn.donor_id
    LEFT JOIN finance_accounts fa ON d.account_id = fa.account_id
    ORDER BY d.donation_id DESC
    LIMIT 20
")->fetchAll();

// Fetch salary payments with staff name
$salary_records = $pdo->query("
    SELECT sp.amount, sp.payment_date, sp.payment_month, sp.notes, st.full_name AS staff_name, st.position, fa.account_name AS source_fund
    FROM salary_payments sp
    JOIN staff st ON sp.staff_id = st.staff_id
    LEFT JOIN finance_accounts fa ON sp.account_id = fa.account_id
    ORDER BY sp.payment_id DESC
")->fetchAll();

// Fetch staff for salary form
$all_staff = $pdo->query("SELECT staff_id, full_name, position, salary FROM staff ORDER BY full_name ASC")->fetchAll();

// Fetch income accounts for source fund dropdown
$income_accounts = $pdo->query("SELECT account_id, account_name, balance FROM finance_accounts WHERE account_type = 'income' ORDER BY account_id ASC")->fetchAll();

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
          <h1 class="m-0 font-weight-bold text-dark"><i class="fas fa-university text-primary mr-2"></i>Central Finance</h1>
        </div>
        <div class="col-sm-6 text-right">
          <span class="text-muted text-sm">Last updated: <?php echo date('M d, Y h:i A'); ?></span>
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

      <!-- Summary Metric Cards -->
      <div class="row">
        <div class="col-lg-3 col-md-6">
          <div class="small-box bg-success py-2">
            <div class="inner">
              <h3>$<?php echo number_format($total_fund_balance, 2); ?></h3>
              <p>Total Fund Balance</p>
            </div>
            <div class="icon"><i class="fas fa-wallet"></i></div>
            <a href="#accounts-section" class="small-box-footer">View Accounts <i class="fas fa-arrow-circle-right"></i></a>
          </div>
        </div>
        <div class="col-lg-3 col-md-6">
          <div class="small-box bg-primary py-2">
            <div class="inner">
              <h3>$<?php echo number_format($total_income, 2); ?></h3>
              <p>Total Income Received</p>
            </div>
            <div class="icon"><i class="fas fa-arrow-circle-down"></i></div>
            <a href="#ledger-section" class="small-box-footer">View Ledger <i class="fas fa-arrow-circle-right"></i></a>
          </div>
        </div>
        <div class="col-lg-3 col-md-6">
          <div class="small-box bg-danger py-2">
            <div class="inner">
              <h3>$<?php echo number_format($total_salary_paid, 2); ?></h3>
              <p>Total Salaries Paid</p>
            </div>
            <div class="icon"><i class="fas fa-arrow-circle-up"></i></div>
            <a href="#salary-section" class="small-box-footer">View Disbursements <i class="fas fa-arrow-circle-right"></i></a>
          </div>
        </div>
        <div class="col-lg-3 col-md-6">
          <div class="small-box bg-info py-2">
            <div class="inner">
              <h3>$<?php echo number_format($net_balance, 2); ?></h3>
              <p>Net Balance</p>
            </div>
            <div class="icon"><i class="fas fa-balance-scale"></i></div>
            <a href="#" class="small-box-footer">Income - Expenses</a>
          </div>
        </div>
      </div>

      <!-- Account Cards -->
      <div id="accounts-section" class="row mb-3">
        <?php foreach ($accounts as $acc): ?>
          <div class="col-lg-4 col-md-6 mb-3">
            <div class="card h-100">
              <div class="card-body">
                <div class="d-flex align-items-center mb-3">
                  <?php if ($acc['account_type'] === 'income'): ?>
                    <div class="rounded-circle d-flex align-items-center justify-content-center mr-3" style="width: 50px; height: 50px; background-color: rgba(40, 167, 69, 0.12);">
                      <i class="fas fa-piggy-bank text-success" style="font-size: 1.4rem;"></i>
                    </div>
                  <?php else: ?>
                    <div class="rounded-circle d-flex align-items-center justify-content-center mr-3" style="width: 50px; height: 50px; background-color: rgba(220, 53, 69, 0.12);">
                      <i class="fas fa-money-bill-wave text-danger" style="font-size: 1.4rem;"></i>
                    </div>
                  <?php endif; ?>
                  <div>
                    <h6 class="font-weight-bold mb-0"><?php echo escape($acc['account_name']); ?></h6>
                    <span class="badge <?php echo $acc['account_type'] === 'income' ? 'badge-success' : 'badge-danger'; ?> text-uppercase" style="font-size: 0.65rem;"><?php echo escape($acc['account_type']); ?></span>
                  </div>
                </div>
                <p class="text-muted text-xs mb-2"><?php echo escape($acc['description']); ?></p>
                <h4 class="font-weight-bold <?php echo $acc['account_type'] === 'income' ? 'text-success' : 'text-danger'; ?> mb-0">
                  $<?php echo number_format($acc['balance'], 2); ?>
                </h4>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <!-- Ledger & Salary Payment -->
      <div class="row" id="ledger-section">
        <!-- Left: Financial Ledger -->
        <div class="col-md-8">
          <div class="card card-primary card-tabs">
            <div class="card-header p-0 pt-1 bg-white border-bottom-0">
              <ul class="nav nav-tabs" id="financeTab" role="tablist">
                <li class="nav-item">
                  <a class="nav-link active font-weight-bold text-secondary" id="income-tab" data-toggle="pill" href="#income" role="tab">
                    <i class="fas fa-arrow-circle-down text-success mr-1"></i>Income Transactions
                  </a>
                </li>
                <li class="nav-item">
                  <a class="nav-link font-weight-bold text-secondary" id="salary-tab" data-toggle="pill" href="#salaries" role="tab">
                    <i class="fas fa-arrow-circle-up text-danger mr-1"></i>Salary Disbursements
                  </a>
                </li>
              </ul>
            </div>
            <div class="card-body">
              <div class="tab-content" id="financeTabContent">

                <!-- Income Tab -->
                <div class="tab-pane fade show active" id="income" role="tabpanel">
                  <div class="table-responsive">
                    <table class="table table-striped table-hover text-sm mb-0">
                      <thead>
                        <tr>
                          <th>Donor</th>
                          <th>Destination Fund</th>
                          <th>Amount</th>
                          <th>Method</th>
                          <th>Date</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php if (count($recent_donations) > 0): ?>
                          <?php foreach ($recent_donations as $d): ?>
                            <tr>
                              <td><strong><?php echo escape($d['donor_name']); ?></strong></td>
                              <td><span class="badge badge-light border text-dark"><?php echo escape($d['account_name'] ?? 'General Fund'); ?></span></td>
                              <td class="font-weight-bold text-success">$<?php echo number_format($d['amount'], 2); ?></td>
                              <td><span class="badge badge-secondary"><?php echo escape($d['payment_method']); ?></span></td>
                              <td class="text-muted"><?php echo date('M d, Y', strtotime($d['donation_date'])); ?></td>
                            </tr>
                          <?php endforeach; ?>
                        <?php else: ?>
                          <tr><td colspan="5" class="text-center text-muted py-4">No income transactions recorded yet.</td></tr>
                        <?php endif; ?>
                      </tbody>
                    </table>
                  </div>
                </div>

                <!-- Salary Tab -->
                <div class="tab-pane fade" id="salaries" role="tabpanel">
                  <div class="table-responsive">
                    <table class="table table-striped table-hover text-sm mb-0">
                      <thead>
                        <tr>
                          <th>Staff Member</th>
                          <th>Position</th>
                          <th>Amount</th>
                          <th>Source Fund</th>
                          <th>Month</th>
                          <th>Payment Date</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php if (count($salary_records) > 0): ?>
                          <?php foreach ($salary_records as $sp): ?>
                            <tr>
                              <td><strong><?php echo escape($sp['staff_name']); ?></strong></td>
                              <td class="text-muted"><?php echo escape($sp['position']); ?></td>
                              <td class="font-weight-bold text-danger">-$<?php echo number_format($sp['amount'], 2); ?></td>
                              <td><span class="badge badge-light border text-dark"><?php echo escape($sp['source_fund'] ?? 'N/A'); ?></span></td>
                              <td><?php echo escape($sp['payment_month']); ?></td>
                              <td class="text-muted"><?php echo date('M d, Y', strtotime($sp['payment_date'])); ?></td>
                            </tr>
                          <?php endforeach; ?>
                        <?php else: ?>
                          <tr><td colspan="6" class="text-center text-muted py-4">No salary disbursements recorded yet.</td></tr>
                        <?php endif; ?>
                      </tbody>
                    </table>
                  </div>
                </div>

              </div>
            </div>
          </div>
        </div>

        <!-- Right: Salary Payment Form -->
        <div class="col-md-4" id="salary-section">
          <div class="card">
            <div class="card-header bg-white border-0 py-3">
              <h3 class="card-title font-weight-bold"><i class="fas fa-money-check-alt text-danger mr-2"></i>Process Salary Payment</h3>
            </div>
            <div class="card-body">
              <form action="" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="action" value="pay_salary">

                <div class="form-group">
                  <label class="text-sm font-weight-medium">Staff Member <span class="text-danger">*</span></label>
                  <select name="staff_id" id="staffSelect" class="form-control" required>
                    <option value="">-- Select Staff --</option>
                    <?php foreach ($all_staff as $st): ?>
                      <option value="<?php echo $st['staff_id']; ?>" data-salary="<?php echo $st['salary']; ?>">
                        <?php echo escape($st['full_name']); ?> (<?php echo escape($st['position']); ?>)
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>

                <div class="form-group">
                  <label class="text-sm font-weight-medium">Salary Amount ($) <span class="text-danger">*</span></label>
                  <div class="input-group">
                    <div class="input-group-prepend">
                      <span class="input-group-text">$</span>
                    </div>
                    <input type="number" name="amount" id="salaryAmount" step="0.01" min="1" class="form-control" placeholder="0.00" required>
                  </div>
                </div>

                <div class="form-group">
                  <label class="text-sm font-weight-medium">Source Fund <span class="text-danger">*</span></label>
                  <select name="source_account_id" class="form-control" required>
                    <option value="">-- Select Fund --</option>
                    <?php foreach ($income_accounts as $ia): ?>
                      <option value="<?php echo $ia['account_id']; ?>">
                        <?php echo escape($ia['account_name']); ?> ($<?php echo number_format($ia['balance'], 2); ?>)
                      </option>
                    <?php endforeach; ?>
                  </select>
                  <span class="text-xs text-muted d-block mt-1">Amount will be deducted from this fund.</span>
                </div>

                <div class="form-group">
                  <label class="text-sm font-weight-medium">Payment Month <span class="text-danger">*</span></label>
                  <input type="text" name="payment_month" class="form-control" placeholder="e.g. July 2026" value="<?php echo date('F Y'); ?>" required>
                </div>

                <div class="form-group">
                  <label class="text-sm font-weight-medium">Payment Date <span class="text-danger">*</span></label>
                  <input type="date" name="payment_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                </div>

                <div class="form-group">
                  <label class="text-sm font-weight-medium">Notes <span class="text-danger">*</span></label>
                  <input type="text" name="notes" class="form-control" placeholder="e.g. Monthly salary for July" required>
                </div>

                <button type="submit" class="btn btn-danger btn-block">
                  <i class="fas fa-paper-plane mr-1"></i> Process Salary Payment
                </button>
              </form>
            </div>
          </div>
        </div>
      </div>

    </div>
  </section>
</div>

<!-- JS: Auto-fill salary from staff selection -->
<script>
  window.addEventListener('DOMContentLoaded', () => {
    const staffSelect = document.getElementById('staffSelect');
    const salaryInput = document.getElementById('salaryAmount');
    if (staffSelect && salaryInput) {
      staffSelect.addEventListener('change', function() {
        const selected = this.options[this.selectedIndex];
        const salary = selected.getAttribute('data-salary');
        if (salary) {
          salaryInput.value = parseFloat(salary).toFixed(2);
        } else {
          salaryInput.value = '';
        }
      });
    }
  });
</script>

<?php
include __DIR__ . '/../includes/footer.php';
?>
