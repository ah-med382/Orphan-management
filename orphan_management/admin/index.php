<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_middleware.php';

// Enforce Admin role
checkRole(['admin']);

// Fetch Summary Statistics
$total_orphans = $pdo->query("SELECT COUNT(*) FROM orphans")->fetchColumn();
$total_donors = $pdo->query("SELECT COUNT(*) FROM donors")->fetchColumn();
$total_staff = $pdo->query("SELECT COUNT(*) FROM staff")->fetchColumn();
$total_donations = $pdo->query("SELECT SUM(amount) FROM donations")->fetchColumn() ?? 0;
$total_sponsorships = $pdo->query("SELECT COUNT(*) FROM sponsorships WHERE status = 'Active'")->fetchColumn();
$pending_adoptions = $pdo->query("SELECT COUNT(*) FROM adoption_requests WHERE status = 'Pending'")->fetchColumn();

// Fetch Recent Donations
$recent_donations = $pdo->query("
    SELECT d.amount, d.donation_date, dn.full_name AS donor_name 
    FROM donations d 
    JOIN donors dn ON d.donor_id = dn.donor_id 
    ORDER BY d.donation_id DESC 
    LIMIT 5
")->fetchAll();

// Fetch Recent Sponsorships
$recent_sponsorships = $pdo->query("
    SELECT s.sponsorship_amount, s.start_date, dn.full_name AS donor_name, o.full_name AS orphan_name 
    FROM sponsorships s 
    JOIN donors dn ON s.donor_id = dn.donor_id 
    JOIN orphans o ON s.orphan_id = o.orphan_id 
    ORDER BY s.sponsorship_id DESC 
    LIMIT 5
")->fetchAll();

// Fetch Recent Orphans
$recent_orphans = $pdo->query("
    SELECT orphan_id, full_name, admission_date, status, gender, date_of_birth 
    FROM orphans 
    ORDER BY orphan_id DESC 
    LIMIT 5
")->fetchAll();

// Fetch Monthly Donations (Last 6 Months) for Chart.js
$monthly_donations_query = $pdo->query("
    SELECT DATE_FORMAT(donation_date, '%b %Y') AS month_label, SUM(amount) AS total 
    FROM donations 
    WHERE donation_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(donation_date, '%Y-%m')
    ORDER BY donation_date ASC
")->fetchAll();

$month_labels = [];
$month_totals = [];
foreach ($monthly_donations_query as $row) {
    $month_labels[] = $row['month_label'];
    $month_totals[] = (float)$row['total'];
}

// Fetch Sponsorships Statistics (Status distribution)
$sponsorship_stats = $pdo->query("
    SELECT status, COUNT(*) AS count 
    FROM sponsorships 
    GROUP BY status
")->fetchAll();
$sponsor_labels = [];
$sponsor_counts = [];
foreach ($sponsorship_stats as $row) {
    $sponsor_labels[] = $row['status'];
    $sponsor_counts[] = (int)$row['count'];
}

// Fetch Adoption Request Statistics
$adoption_stats = $pdo->query("
    SELECT status, COUNT(*) AS count 
    FROM adoption_requests 
    GROUP BY status
")->fetchAll();
$adoption_labels = [];
$adoption_counts = [];
foreach ($adoption_stats as $row) {
    $adoption_labels[] = $row['status'];
    $adoption_counts[] = (int)$row['count'];
}

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
  <!-- Content Header (Page header) -->
  <div class="content-header">
    <div class="container-fluid">
      <div class="row mb-2">
        <div class="col-sm-6">
          <h1 class="m-0 font-weight-bold text-dark">Admin Dashboard</h1>
        </div>
      </div>
    </div>
  </div>
  <!-- /.content-header -->

  <!-- Main content -->
  <section class="content">
    <div class="container-fluid">
      
      <!-- Metric Cards Row -->
      <div class="row">
        <!-- Card 1: Total Orphans -->
        <div class="col-lg-2 col-md-4 col-sm-6">
          <div class="small-box bg-info py-2">
            <div class="inner">
              <h3><?php echo $total_orphans; ?></h3>
              <p>Total Orphans</p>
            </div>
            <div class="icon">
              <i class="fas fa-child"></i>
            </div>
            <a href="/orphan_management/modules/orphans/index.php" class="small-box-footer">View Details <i class="fas fa-arrow-circle-right"></i></a>
          </div>
        </div>
        
        <!-- Card 2: Total Donors -->
        <div class="col-lg-2 col-md-4 col-sm-6">
          <div class="small-box bg-success py-2">
            <div class="inner">
              <h3><?php echo $total_donors; ?></h3>
              <p>Total Donors</p>
            </div>
            <div class="icon">
              <i class="fas fa-user-friends"></i>
            </div>
            <a href="/orphan_management/modules/donors/index.php" class="small-box-footer">View Details <i class="fas fa-arrow-circle-right"></i></a>
          </div>
        </div>

        <!-- Card 3: Total Staff -->
        <div class="col-lg-2 col-md-4 col-sm-6">
          <div class="small-box bg-warning py-2">
            <div class="inner">
              <h3><?php echo $total_staff; ?></h3>
              <p>Total Staff</p>
            </div>
            <div class="icon">
              <i class="fas fa-user-shield"></i>
            </div>
            <a href="#" class="small-box-footer">In Staff Table</a>
          </div>
        </div>

        <!-- Card 4: Total Donations -->
        <div class="col-lg-2 col-md-4 col-sm-6">
          <div class="small-box bg-primary py-2">
            <div class="inner">
              <h3>$<?php echo number_format($total_donations, 2); ?></h3>
              <p>Total Donations</p>
            </div>
            <div class="icon">
              <i class="fas fa-hand-holding-usd"></i>
            </div>
            <a href="/orphan_management/modules/donations/index.php" class="small-box-footer">View History <i class="fas fa-arrow-circle-right"></i></a>
          </div>
        </div>

        <!-- Card 5: Active Sponsorships -->
        <div class="col-lg-2 col-md-4 col-sm-6">
          <div class="small-box bg-danger py-2">
            <div class="inner">
              <h3><?php echo $total_sponsorships; ?></h3>
              <p>Sponsorships</p>
            </div>
            <div class="icon">
              <i class="fas fa-ribbon"></i>
            </div>
            <a href="/orphan_management/modules/sponsorships/index.php" class="small-box-footer">View Assignments <i class="fas fa-arrow-circle-right"></i></a>
          </div>
        </div>

        <!-- Card 6: Pending Adoptions -->
        <div class="col-lg-2 col-md-4 col-sm-6">
          <div class="small-box bg-purple py-2" style="background-color: #6f42c1 !important;">
            <div class="inner text-white">
              <h3><?php echo $pending_adoptions; ?></h3>
              <p>Pending Adoptions</p>
            </div>
            <div class="icon text-white">
              <i class="fas fa-home"></i>
            </div>
            <a href="/orphan_management/modules/adoptions/index.php" class="small-box-footer text-white">Review Requests <i class="fas fa-arrow-circle-right"></i></a>
          </div>
        </div>
      </div>
      <!-- /.row -->

      <!-- Charts Row -->
      <div class="row">
        <!-- Chart 1: Monthly Donations -->
        <div class="col-md-6">
          <div class="card">
            <div class="card-header bg-white border-0 py-3">
              <h3 class="card-title font-weight-bold"><i class="fas fa-chart-area text-primary mr-2"></i>Monthly Donations Chart</h3>
            </div>
            <div class="card-body">
              <canvas id="donationsChart" style="min-height: 250px; height: 250px; max-height: 250px; max-width: 100%;"></canvas>
            </div>
          </div>
        </div>
        
        <!-- Chart 2: Sponsorship and Adoption Status Pie Charts -->
        <div class="col-md-3">
          <div class="card">
            <div class="card-header bg-white border-0 py-3">
              <h3 class="card-title font-weight-bold"><i class="fas fa-chart-pie text-success mr-2"></i>Sponsorships</h3>
            </div>
            <div class="card-body">
              <canvas id="sponsorshipPieChart" style="min-height: 250px; height: 250px; max-height: 250px; max-width: 100%;"></canvas>
            </div>
          </div>
        </div>

        <div class="col-md-3">
          <div class="card">
            <div class="card-header bg-white border-0 py-3">
              <h3 class="card-title font-weight-bold"><i class="fas fa-chart-pie text-indigo mr-2"></i>Adoption Requests</h3>
            </div>
            <div class="card-body">
              <canvas id="adoptionsPieChart" style="min-height: 250px; height: 250px; max-height: 250px; max-width: 100%;"></canvas>
            </div>
          </div>
        </div>
      </div>
      <!-- /.row -->

      <!-- Tables Row -->
      <div class="row mt-3">
        <!-- Table 1: Recent Donations -->
        <div class="col-md-4">
          <div class="card h-100">
            <div class="card-header bg-white border-0 py-3 d-flex align-items-center justify-content-between">
              <h3 class="card-title font-weight-bold mb-0">Recent Donations</h3>
              <a href="/orphan_management/modules/donations/index.php" class="text-xs text-primary font-weight-bold">View All</a>
            </div>
            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table table-striped mb-0">
                  <thead>
                    <tr>
                      <th>Donor</th>
                      <th>Amount</th>
                      <th>Date</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if (count($recent_donations) > 0): ?>
                      <?php foreach ($recent_donations as $donation): ?>
                        <tr>
                          <td><?php echo escape($donation['donor_name']); ?></td>
                          <td class="font-weight-bold text-success">$<?php echo number_format($donation['amount'], 2); ?></td>
                          <td class="text-muted text-sm"><?php echo date('M d, Y', strtotime($donation['donation_date'])); ?></td>
                        </tr>
                      <?php endforeach; ?>
                    <?php else: ?>
                      <tr>
                        <td colspan="3" class="text-center text-muted py-3">No recent donations.</td>
                      </tr>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>

        <!-- Table 2: Recent Sponsorships -->
        <div class="col-md-4">
          <div class="card h-100">
            <div class="card-header bg-white border-0 py-3 d-flex align-items-center justify-content-between">
              <h3 class="card-title font-weight-bold mb-0">Recent Sponsorships</h3>
              <a href="/orphan_management/modules/sponsorships/index.php" class="text-xs text-primary font-weight-bold">View All</a>
            </div>
            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table table-striped mb-0">
                  <thead>
                    <tr>
                      <th>Sponsor</th>
                      <th>Orphan</th>
                      <th>Monthly</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if (count($recent_sponsorships) > 0): ?>
                      <?php foreach ($recent_sponsorships as $sponsor): ?>
                        <tr>
                          <td><?php echo escape($sponsor['donor_name']); ?></td>
                          <td><?php echo escape($sponsor['orphan_name']); ?></td>
                          <td class="font-weight-bold text-danger">$<?php echo number_format($sponsor['sponsorship_amount'], 2); ?></td>
                        </tr>
                      <?php endforeach; ?>
                    <?php else: ?>
                      <tr>
                        <td colspan="3" class="text-center text-muted py-3">No recent sponsorships.</td>
                      </tr>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>

        <!-- Table 3: Recent Orphans -->
        <div class="col-md-4">
          <div class="card h-100">
            <div class="card-header bg-white border-0 py-3 d-flex align-items-center justify-content-between">
              <h3 class="card-title font-weight-bold mb-0">Recent Registrations</h3>
              <a href="/orphan_management/modules/orphans/index.php" class="text-xs text-primary font-weight-bold">View All</a>
            </div>
            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table table-striped mb-0">
                  <thead>
                    <tr>
                      <th>Orphan</th>
                      <th>Gender</th>
                      <th>Status</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if (count($recent_orphans) > 0): ?>
                      <?php foreach ($recent_orphans as $orphan): ?>
                        <tr>
                          <td>
                            <a href="/orphan_management/modules/orphans/view.php?id=<?php echo $orphan['orphan_id']; ?>" class="font-weight-medium text-dark">
                              <?php echo escape($orphan['full_name']); ?>
                            </a>
                          </td>
                          <td class="text-muted text-sm"><?php echo escape($orphan['gender']); ?></td>
                          <td>
                            <?php 
                            $status_class = 'badge-secondary';
                            if ($orphan['status'] === 'Active') $status_class = 'badge-success';
                            elseif ($orphan['status'] === 'Sponsored') $status_class = 'badge-info';
                            elseif ($orphan['status'] === 'Adopted') $status_class = 'badge-primary';
                            ?>
                            <span class="badge <?php echo $status_class; ?>"><?php echo escape($orphan['status']); ?></span>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    <?php else: ?>
                      <tr>
                        <td colspan="3" class="text-center text-muted py-3">No orphans found.</td>
                      </tr>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
      </div>
      <!-- /.row -->

    </div><!-- /.container-fluid -->
  </section>
  <!-- /.content -->
</div>
<!-- /.content-wrapper -->

<!-- Scripts block for charts -->
<script>
  window.addEventListener('DOMContentLoaded', () => {
    // 1. Donations Line Chart
    const donationsCtx = document.getElementById('donationsChart').getContext('2d');
    new Chart(donationsCtx, {
      type: 'line',
      data: {
        labels: <?php echo json_encode($month_labels); ?>,
        datasets: [{
          label: 'Total Donations ($)',
          data: <?php echo json_encode($month_totals); ?>,
          backgroundColor: 'rgba(59, 130, 246, 0.1)',
          borderColor: '#3b82f6',
          borderWidth: 3,
          pointBackgroundColor: '#3b82f6',
          tension: 0.3,
          fill: true
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false }
        },
        scales: {
          y: {
            beginAtZero: true,
            grid: { color: '#f1f5f9' }
          },
          x: {
            grid: { display: false }
          }
        }
      }
    });

    // 2. Sponsorship Status Pie Chart
    const sponsorCtx = document.getElementById('sponsorshipPieChart').getContext('2d');
    new Chart(sponsorCtx, {
      type: 'doughnut',
      data: {
        labels: <?php echo json_encode($sponsor_labels); ?>,
        datasets: [{
          data: <?php echo json_encode($sponsor_counts); ?>,
          backgroundColor: ['#28a745', '#ffc107', '#dc3545'],
          borderWidth: 0
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } }
        }
      }
    });

    // 3. Adoptions Status Pie Chart
    const adoptionsCtx = document.getElementById('adoptionsPieChart').getContext('2d');
    new Chart(adoptionsCtx, {
      type: 'doughnut',
      data: {
        labels: <?php echo json_encode($adoption_labels); ?>,
        datasets: [{
          data: <?php echo json_encode($adoption_counts); ?>,
          backgroundColor: ['#fd7e14', '#28a745', '#dc3545'],
          borderWidth: 0
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } }
        }
      }
    });
  });
</script>

<?php
include __DIR__ . '/../includes/footer.php';
?>
