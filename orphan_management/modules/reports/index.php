<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth_middleware.php';

// Restricted to Admin only
checkRole(['admin']);

$report_type = $_GET['type'] ?? '';
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-d');
$orphan_status = $_GET['orphan_status'] ?? '';

$report_data = [];
$report_title = "Select Report Details";

try {
    if ($report_type === 'donations') {
        $report_title = "Donation Receipts Report (" . date('M d, Y', strtotime($start_date)) . " to " . date('M d, Y', strtotime($end_date)) . ")";
        $stmt = $pdo->prepare("
            SELECT d.*, dn.full_name AS donor_name, dn.email AS donor_email 
            FROM donations d 
            JOIN donors dn ON d.donor_id = dn.donor_id 
            WHERE d.donation_date BETWEEN ? AND ?
            ORDER BY d.donation_date DESC
        ");
        $stmt->execute([$start_date, $end_date]);
        $report_data = $stmt->fetchAll();
    } elseif ($report_type === 'orphans') {
        $status_label = !empty($orphan_status) ? $orphan_status : "All";
        $report_title = "Orphans Registry Report (Status: " . $status_label . ")";
        
        $query = "SELECT * FROM orphans WHERE 1=1";
        $params = [];
        if (!empty($orphan_status)) {
            $query .= " AND status = ?";
            $params[] = $orphan_status;
        }
        $query .= " ORDER BY full_name ASC";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $report_data = $stmt->fetchAll();
    } elseif ($report_type === 'sponsorships') {
        $report_title = "Active Sponsorships Distribution Report";
        $report_data = $pdo->query("
            SELECT s.*, o.full_name AS orphan_name, dn.full_name AS donor_name 
            FROM sponsorships s 
            JOIN orphans o ON s.orphan_id = o.orphan_id 
            JOIN donors dn ON s.donor_id = dn.donor_id 
            WHERE s.status = 'Active'
            ORDER BY s.start_date DESC
        ")->fetchAll();
    } elseif ($report_type === 'staff') {
        $report_title = "Orphanage Staff & Caretakers Directory";
        $report_data = $pdo->query("SELECT * FROM staff ORDER BY full_name ASC")->fetchAll();
    } elseif ($report_type === 'adoptions') {
        $report_title = "Adoption Requests Summary Report";
        $report_data = $pdo->query("
            SELECT ar.*, o.full_name AS orphan_name 
            FROM adoption_requests ar 
            JOIN orphans o ON ar.orphan_id = o.orphan_id 
            ORDER BY ar.request_date DESC
        ")->fetchAll();
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
          <h1 class="m-0 font-weight-bold text-dark">Management Reports Panel</h1>
        </div>
      </div>
    </div>
  </div>

  <!-- Main content -->
  <section class="content">
    <div class="container-fluid">

      <!-- Selection cards row -->
      <div class="row mb-4">
        <!-- 1. Donations Report -->
        <div class="col-md-2 col-sm-4 mb-2">
          <a href="?type=donations" class="card btn btn-outline-primary py-3 <?php echo ($report_type === 'donations') ? 'bg-primary text-white' : ''; ?>">
            <div class="text-center">
              <i class="fas fa-hand-holding-usd mb-2" style="font-size: 1.5rem;"></i>
              <div class="text-xs font-weight-bold text-uppercase">Donations</div>
            </div>
          </a>
        </div>
        
        <!-- 2. Orphans Report -->
        <div class="col-md-2 col-sm-4 mb-2">
          <a href="?type=orphans" class="card btn btn-outline-info py-3 <?php echo ($report_type === 'orphans') ? 'bg-info text-white' : ''; ?>">
            <div class="text-center">
              <i class="fas fa-child mb-2" style="font-size: 1.5rem;"></i>
              <div class="text-xs font-weight-bold text-uppercase">Orphans</div>
            </div>
          </a>
        </div>

        <!-- 3. Sponsorships -->
        <div class="col-md-2 col-sm-4 mb-2">
          <a href="?type=sponsorships" class="card btn btn-outline-success py-3 <?php echo ($report_type === 'sponsorships') ? 'bg-success text-white' : ''; ?>">
            <div class="text-center">
              <i class="fas fa-ribbon mb-2" style="font-size: 1.5rem;"></i>
              <div class="text-xs font-weight-bold text-uppercase">Sponsors</div>
            </div>
          </a>
        </div>

        <!-- 4. Staff -->
        <div class="col-md-2 col-sm-4 mb-2">
          <a href="?type=staff" class="card btn btn-outline-warning py-3 <?php echo ($report_type === 'staff') ? 'bg-warning text-white' : ''; ?>">
            <div class="text-center">
              <i class="fas fa-user-shield mb-2" style="font-size: 1.5rem;"></i>
              <div class="text-xs font-weight-bold text-uppercase">Staff Directory</div>
            </div>
          </a>
        </div>

        <!-- 5. Adoptions -->
        <div class="col-md-2 col-sm-4 mb-2">
          <a href="?type=adoptions" class="card btn btn-outline-purple py-3 <?php echo ($report_type === 'adoptions') ? 'bg-purple text-white' : ''; ?>" style="<?php echo ($report_type === 'adoptions') ? 'background-color: #6f42c1; color: white;' : ''; ?>">
            <div class="text-center">
              <i class="fas fa-home mb-2" style="font-size: 1.5rem;"></i>
              <div class="text-xs font-weight-bold text-uppercase">Adoptions</div>
            </div>
          </a>
        </div>
      </div>

      <!-- Filters depending on selection -->
      <?php if ($report_type !== ''): ?>
        <div class="card mb-4">
          <div class="card-body">
            <form method="GET" action="">
              <input type="hidden" name="type" value="<?php echo escape($report_type); ?>">
              
              <?php if ($report_type === 'donations'): ?>
                <div class="row align-items-end">
                  <div class="col-md-4 form-group mb-0">
                    <label class="text-xs font-weight-medium">Start Date</label>
                    <input type="date" name="start_date" class="form-control" value="<?php echo $start_date; ?>">
                  </div>
                  <div class="col-md-4 form-group mb-0">
                    <label class="text-xs font-weight-medium">End Date</label>
                    <input type="date" name="end_date" class="form-control" value="<?php echo $end_date; ?>">
                  </div>
                  <div class="col-md-4">
                    <button type="submit" class="btn btn-primary btn-block">Generate Report</button>
                  </div>
                </div>
              <?php elseif ($report_type === 'orphans'): ?>
                <div class="row align-items-end">
                  <div class="col-md-8 form-group mb-0">
                    <label class="text-xs font-weight-medium">Filter by Status</label>
                    <select name="orphan_status" class="form-control">
                      <option value="">All Statuses</option>
                      <option value="Active" <?php echo ($orphan_status === 'Active') ? 'selected' : ''; ?>>Active</option>
                      <option value="Sponsored" <?php echo ($orphan_status === 'Sponsored') ? 'selected' : ''; ?>>Sponsored</option>
                      <option value="Adopted" <?php echo ($orphan_status === 'Adopted') ? 'selected' : ''; ?>>Adopted</option>
                      <option value="Inactive" <?php echo ($orphan_status === 'Inactive') ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                  </div>
                  <div class="col-md-4">
                    <button type="submit" class="btn btn-info btn-block text-white">Generate Report</button>
                  </div>
                </div>
              <?php else: ?>
                <p class="text-sm mb-0 text-muted">No additional filters required for this report type. Click to generate/print below.</p>
              <?php endif; ?>
            </form>
          </div>
        </div>

        <!-- Report Results Box -->
        <div class="card">
          <div class="card-header bg-white border-0 py-3 d-flex justify-content-between align-items-center">
            <h4 class="card-title font-weight-bold mb-0 text-dark"><?php echo escape($report_title); ?></h4>
            <div>
              <?php 
                // Build print link
                $print_url = "/orphan_management/modules/reports/print.php?type=" . urlencode($report_type);
                if ($report_type === 'donations') {
                    $print_url .= "&start_date=" . urlencode($start_date) . "&end_date=" . urlencode($end_date);
                } elseif ($report_type === 'orphans') {
                    $print_url .= "&orphan_status=" . urlencode($orphan_status);
                }
              ?>
              <a href="<?php echo $print_url; ?>" target="_blank" class="btn btn-success btn-sm">
                <i class="fas fa-print mr-1"></i> Print-Friendly Report
              </a>
            </div>
          </div>
          <div class="card-body p-0">
            
            <!-- Donations Table -->
            <?php if ($report_type === 'donations'): ?>
              <div class="table-responsive">
                <table class="table table-striped table-hover mb-0 text-sm">
                  <thead>
                    <tr>
                      <th>TXN ID</th>
                      <th>Donor Name</th>
                      <th>Amount</th>
                      <th>Method</th>
                      <th>Date</th>
                      <th>Notes</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if (count($report_data) > 0): $total = 0; ?>
                      <?php foreach ($report_data as $row): $total += $row['amount']; ?>
                        <tr>
                          <td><code>#TXN-<?php echo str_pad($row['donation_id'], 5, '0', STR_PAD_LEFT); ?></code></td>
                          <td><strong><?php echo escape($row['donor_name']); ?></strong></td>
                          <td class="font-weight-bold text-success">$<?php echo number_format($row['amount'], 2); ?></td>
                          <td><span class="badge badge-secondary"><?php echo escape($row['payment_method']); ?></span></td>
                          <td><?php echo date('M d, Y', strtotime($row['donation_date'])); ?></td>
                          <td class="text-xs text-muted"><?php echo escape($row['notes']); ?></td>
                        </tr>
                      <?php endforeach; ?>
                      <tr class="bg-light font-weight-bold">
                        <td colspan="2" class="text-right">Grand Total:</td>
                        <td class="text-success text-md font-weight-bold">$<?php echo number_format($total, 2); ?></td>
                        <td colspan="3"></td>
                      </tr>
                    <?php else: ?>
                      <tr>
                        <td colspan="6" class="text-center py-5 text-muted">No donations matching search range.</td>
                      </tr>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>

            <!-- Orphans Table -->
            <?php elseif ($report_type === 'orphans'): ?>
              <div class="table-responsive">
                <table class="table table-striped table-hover mb-0 text-sm">
                  <thead>
                    <tr>
                      <th>ID</th>
                      <th>Full Name</th>
                      <th>Gender</th>
                      <th>DOB</th>
                      <th>Admission Date</th>
                      <th>Health Status</th>
                      <th>Education Level</th>
                      <th>Status</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if (count($report_data) > 0): ?>
                      <?php foreach ($report_data as $row): ?>
                        <tr>
                          <td><code>#ORP-<?php echo str_pad($row['orphan_id'], 4, '0', STR_PAD_LEFT); ?></code></td>
                          <td><strong><?php echo escape($row['full_name']); ?></strong></td>
                          <td><?php echo escape($row['gender']); ?></td>
                          <td><?php echo date('M d, Y', strtotime($row['date_of_birth'])); ?></td>
                          <td><?php echo date('M d, Y', strtotime($row['admission_date'])); ?></td>
                          <td><?php echo escape($row['health_status']); ?></td>
                          <td><?php echo escape($row['education_level']); ?></td>
                          <td>
                            <?php 
                            $status_class = 'badge-secondary';
                            if ($row['status'] === 'Active') $status_class = 'badge-success';
                            elseif ($row['status'] === 'Sponsored') $status_class = 'badge-info';
                            elseif ($row['status'] === 'Adopted') $status_class = 'badge-primary';
                            ?>
                            <span class="badge <?php echo $status_class; ?>"><?php echo escape($row['status']); ?></span>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    <?php else: ?>
                      <tr>
                        <td colspan="8" class="text-center py-5 text-muted">No orphans registry entries.</td>
                      </tr>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>

            <!-- Sponsors Table -->
            <?php elseif ($report_type === 'sponsorships'): ?>
              <div class="table-responsive">
                <table class="table table-striped table-hover mb-0 text-sm">
                  <thead>
                    <tr>
                      <th>Sponsorship ID</th>
                      <th>Orphan Name</th>
                      <th>Donor Name</th>
                      <th>Monthly Pledge</th>
                      <th>Start Date</th>
                      <th>Status</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if (count($report_data) > 0): $total_monthly = 0; ?>
                      <?php foreach ($report_data as $row): $total_monthly += $row['sponsorship_amount']; ?>
                        <tr>
                          <td><code>#SPO-<?php echo str_pad($row['sponsorship_id'], 5, '0', STR_PAD_LEFT); ?></code></td>
                          <td><strong><?php echo escape($row['orphan_name']); ?></strong></td>
                          <td><?php echo escape($row['donor_name']); ?></td>
                          <td class="font-weight-bold text-success">$<?php echo number_format($row['sponsorship_amount'], 2); ?></td>
                          <td><?php echo date('M d, Y', strtotime($row['start_date'])); ?></td>
                          <td><span class="badge badge-success"><?php echo escape($row['status']); ?></span></td>
                        </tr>
                      <?php endforeach; ?>
                      <tr class="bg-light font-weight-bold">
                        <td colspan="3" class="text-right">Combined Monthly Commitment:</td>
                        <td class="text-success text-md font-weight-bold">$<?php echo number_format($total_monthly, 2); ?>/mo</td>
                        <td colspan="2"></td>
                      </tr>
                    <?php else: ?>
                      <tr>
                        <td colspan="6" class="text-center py-5 text-muted">No active sponsorships.</td>
                      </tr>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>

            <!-- Staff Directory Table -->
            <?php elseif ($report_type === 'staff'): ?>
              <div class="table-responsive">
                <table class="table table-striped table-hover mb-0 text-sm">
                  <thead>
                    <tr>
                      <th>Staff ID</th>
                      <th>Full Name</th>
                      <th>Position</th>
                      <th>Phone</th>
                      <th>Email</th>
                      <th>Monthly Salary</th>
                      <th>Joining Date</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if (count($report_data) > 0): $total_sal = 0; ?>
                      <?php foreach ($report_data as $row): $total_sal += $row['salary']; ?>
                        <tr>
                          <td><code>#STF-<?php echo str_pad($row['staff_id'], 3, '0', STR_PAD_LEFT); ?></code></td>
                          <td><strong><?php echo escape($row['full_name']); ?></strong></td>
                          <td><span class="badge badge-info"><?php echo escape($row['position']); ?></span></td>
                          <td><?php echo escape($row['phone']); ?></td>
                          <td><?php echo escape($row['email']); ?></td>
                          <td class="font-weight-medium text-dark">$<?php echo number_format($row['salary'], 2); ?></td>
                          <td><?php echo date('M d, Y', strtotime($row['joining_date'])); ?></td>
                        </tr>
                      <?php endforeach; ?>
                      <tr class="bg-light font-weight-bold">
                        <td colspan="5" class="text-right">Staff Monthly Payroll:</td>
                        <td class="text-danger text-md font-weight-bold">$<?php echo number_format($total_sal, 2); ?>/mo</td>
                        <td></td>
                      </tr>
                    <?php else: ?>
                      <tr>
                        <td colspan="7" class="text-center py-5 text-muted">No staff records listed.</td>
                      </tr>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>

            <!-- Adoptions Requests Summary -->
            <?php elseif ($report_type === 'adoptions'): ?>
              <div class="table-responsive">
                <table class="table table-striped table-hover mb-0 text-sm">
                  <thead>
                    <tr>
                      <th>Request ID</th>
                      <th>Applicant Name</th>
                      <th>Phone</th>
                      <th>Email</th>
                      <th>Orphan Name</th>
                      <th>Request Date</th>
                      <th>Status</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if (count($report_data) > 0): ?>
                      <?php foreach ($report_data as $row): ?>
                        <tr>
                          <td><code>#REQ-<?php echo str_pad($row['request_id'], 5, '0', STR_PAD_LEFT); ?></code></td>
                          <td><strong><?php echo escape($row['applicant_name']); ?></strong></td>
                          <td><?php echo escape($row['phone']); ?></td>
                          <td><?php echo escape($row['email']); ?></td>
                          <td><strong><?php echo escape($row['orphan_name']); ?></strong></td>
                          <td><?php echo date('M d, Y', strtotime($row['request_date'])); ?></td>
                          <td>
                            <?php 
                            $status_badge = 'badge-warning';
                            if ($row['status'] === 'Approved') $status_badge = 'badge-success';
                            elseif ($row['status'] === 'Rejected') $status_badge = 'badge-danger';
                            ?>
                            <span class="badge <?php echo $status_badge; ?>"><?php echo escape($row['status']); ?></span>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    <?php else: ?>
                      <tr>
                        <td colspan="7" class="text-center py-5 text-muted">No adoption records found.</td>
                      </tr>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>

          </div>
        </div>
      <?php else: ?>
        <!-- Selection hint -->
        <div class="card border-0 py-5 bg-light text-center">
          <div class="card-body">
            <i class="fas fa-chart-bar text-muted mb-3" style="font-size: 3.5rem;"></i>
            <h5 class="text-muted">Choose a report type above to view and filter records.</h5>
          </div>
        </div>
      <?php endif; ?>

    </div>
  </section>
</div>

<?php
include __DIR__ . '/../../includes/footer.php';
?>
