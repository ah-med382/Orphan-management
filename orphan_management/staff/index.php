<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_middleware.php';

// Enforce Staff role
checkRole(['staff', 'admin']);

// Fetch operational metrics
$active_orphans = $pdo->query("SELECT COUNT(*) FROM orphans WHERE status IN ('Active', 'Sponsored')")->fetchColumn();
$health_visits_today = $pdo->query("SELECT COUNT(*) FROM health_records WHERE visit_date = CURDATE()")->fetchColumn();
$pending_adoptions = $pdo->query("SELECT COUNT(*) FROM adoption_requests WHERE status = 'Pending'")->fetchColumn();

// Fetch Recent Health Records Updates
$recent_health = $pdo->query("
    SELECT hr.diagnosis, hr.treatment, hr.visit_date, o.full_name AS orphan_name, o.orphan_id
    FROM health_records hr 
    JOIN orphans o ON hr.orphan_id = o.orphan_id 
    ORDER BY hr.record_id DESC 
    LIMIT 5
")->fetchAll();

// Fetch Recent Education Updates
$recent_education = $pdo->query("
    SELECT er.school_name, er.grade, er.performance, er.updated_at, o.full_name AS orphan_name, o.orphan_id
    FROM education_records er 
    JOIN orphans o ON er.orphan_id = o.orphan_id 
    ORDER BY er.education_id DESC 
    LIMIT 5
")->fetchAll();

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
  <!-- Content Header -->
  <div class="content-header">
    <div class="container-fluid">
      <div class="row mb-2">
        <div class="col-sm-6">
          <h1 class="m-0 font-weight-bold text-dark">Staff Operations Dashboard</h1>
        </div>
      </div>
    </div>
  </div>

  <!-- Main content -->
  <section class="content">
    <div class="container-fluid">
      
      <!-- Metrics row -->
      <div class="row">
        <div class="col-md-4">
          <div class="small-box bg-info py-2">
            <div class="inner">
              <h3><?php echo $active_orphans; ?></h3>
              <p>Active Orphans Under Care</p>
            </div>
            <div class="icon">
              <i class="fas fa-child"></i>
            </div>
            <a href="/orphan_management/modules/orphans/index.php" class="small-box-footer">Go to Directory <i class="fas fa-arrow-circle-right"></i></a>
          </div>
        </div>

        <div class="col-md-4">
          <div class="small-box bg-success py-2">
            <div class="inner">
              <h3><?php echo $health_visits_today; ?></h3>
              <p>Health Visits Recorded Today</p>
            </div>
            <div class="icon">
              <i class="fas fa-heartbeat"></i>
            </div>
            <a href="/orphan_management/modules/orphans/index.php" class="small-box-footer">View Orphans <i class="fas fa-arrow-circle-right"></i></a>
          </div>
        </div>

        <div class="col-md-4">
          <div class="small-box bg-warning py-2">
            <div class="inner">
              <h3><?php echo $pending_adoptions; ?></h3>
              <p>Pending Adoption Requests</p>
            </div>
            <div class="icon">
              <i class="fas fa-home"></i>
            </div>
            <a href="#" class="small-box-footer">Admin Review Only</a>
          </div>
        </div>
      </div>

      <!-- Content Row -->
      <div class="row mt-3">
        <!-- Recent Health records -->
        <div class="col-md-6">
          <div class="card">
            <div class="card-header bg-white border-0 py-3 d-flex align-items-center justify-content-between">
              <h3 class="card-title font-weight-bold mb-0"><i class="fas fa-notes-medical text-danger mr-2"></i>Recent Health Updates</h3>
            </div>
            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table table-striped mb-0">
                  <thead>
                    <tr>
                      <th>Orphan</th>
                      <th>Diagnosis</th>
                      <th>Treatment</th>
                      <th>Date</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if (count($recent_health) > 0): ?>
                      <?php foreach ($recent_health as $record): ?>
                        <tr>
                          <td>
                            <a href="/orphan_management/modules/orphans/view.php?id=<?php echo $record['orphan_id']; ?>" class="font-weight-medium text-dark">
                              <?php echo escape($record['orphan_name']); ?>
                            </a>
                          </td>
                          <td><?php echo escape($record['diagnosis']); ?></td>
                          <td class="text-sm"><?php echo escape($record['treatment']); ?></td>
                          <td class="text-muted text-xs"><?php echo date('M d, Y', strtotime($record['visit_date'])); ?></td>
                        </tr>
                      <?php endforeach; ?>
                    <?php else: ?>
                      <tr>
                        <td colspan="4" class="text-center text-muted py-3">No recent health logs.</td>
                      </tr>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>

        <!-- Recent Education records -->
        <div class="col-md-6">
          <div class="card">
            <div class="card-header bg-white border-0 py-3 d-flex align-items-center justify-content-between">
              <h3 class="card-title font-weight-bold mb-0"><i class="fas fa-graduation-cap text-info mr-2"></i>Recent Educational Updates</h3>
            </div>
            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table table-striped mb-0">
                  <thead>
                    <tr>
                      <th>Orphan</th>
                      <th>School</th>
                      <th>Grade</th>
                      <th>Performance</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if (count($recent_education) > 0): ?>
                      <?php foreach ($recent_education as $record): ?>
                        <tr>
                          <td>
                            <a href="/orphan_management/modules/orphans/view.php?id=<?php echo $record['orphan_id']; ?>" class="font-weight-medium text-dark">
                              <?php echo escape($record['orphan_name']); ?>
                            </a>
                          </td>
                          <td><?php echo escape($record['school_name']); ?></td>
                          <td><?php echo escape($record['grade']); ?></td>
                          <td><span class="badge badge-info"><?php echo escape($record['performance']); ?></span></td>
                        </tr>
                      <?php endforeach; ?>
                    <?php else: ?>
                      <tr>
                        <td colspan="4" class="text-center text-muted py-3">No recent educational logs.</td>
                      </tr>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
      </div>

    </div>
  </section>
</div>

<?php
include __DIR__ . '/../includes/footer.php';
?>
