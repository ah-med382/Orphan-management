<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth_middleware.php';

$orphan_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$csrf_token = getCsrfToken();

// Check login and roles
checkLogin();
$role = $_SESSION['role'];

// Restrict access: Admin & Staff can view all; Donor can view if they sponsor this child OR if the child is Active (needs sponsor).
if ($role === 'donor') {
    $donor_id = $_SESSION['donor_id'] ?? 0;
    try {
        $stmtSpons = $pdo->prepare("SELECT COUNT(*) FROM sponsorships WHERE donor_id = ? AND orphan_id = ? AND status = 'Active'");
        $stmtSpons->execute([$donor_id, $orphan_id]);
        $is_sponsor = ($stmtSpons->fetchColumn() > 0);

        // Fetch child status
        $stmtOrphStatus = $pdo->prepare("SELECT status FROM orphans WHERE orphan_id = ?");
        $stmtOrphStatus->execute([$orphan_id]);
        $orph_status = $stmtOrphStatus->fetchColumn();

        if (!$is_sponsor && $orph_status !== 'Active') {
            $_SESSION['error_message'] = "Access denied. You do not sponsor this orphan and they are not active for sponsorships.";
            header("Location: /orphan_management/donor/index.php");
            exit;
        }
    } catch (PDOException $e) {
        die("Database error: " . $e->getMessage());
    }
} else {
    checkRole(['admin', 'staff']);
}

// Fetch Orphan Details
try {
    $stmt = $pdo->prepare("SELECT * FROM orphans WHERE orphan_id = ? LIMIT 1");
    $stmt->execute([$orphan_id]);
    $orphan = $stmt->fetch();

    if (!$orphan) {
        $_SESSION['error_message'] = "Orphan not found.";
        header("Location: /orphan_management/modules/orphans/index.php");
        exit;
    }
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

// Handle Form Submissions (Health & Education additions)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($role === 'donor') {
        $_SESSION['error_message'] = "Access denied.";
        header("Location: /orphan_management/modules/orphans/view.php?id=" . $orphan_id);
        exit;
    }
    verifyCsrfToken($_POST['csrf_token'] ?? '');
    
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add_health') {
        $diagnosis = trim($_POST['diagnosis'] ?? '');
        $treatment = trim($_POST['treatment'] ?? '');
        $vaccination = trim($_POST['vaccination'] ?? '');
        $weight = trim($_POST['weight'] ?? '');
        $height = trim($_POST['height'] ?? '');
        $other_details = trim($_POST['other_details'] ?? '');
        $visit_date = $_POST['visit_date'] ?? '';
        
        if (empty($diagnosis) || empty($treatment) || empty($visit_date)) {
            $_SESSION['error_message'] = "Diagnosis, treatment, and visit date are required.";
        } else {
            try {
                $stmtHealth = $pdo->prepare("INSERT INTO health_records (orphan_id, diagnosis, treatment, vaccination, weight, height, other_details, visit_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmtHealth->execute([$orphan_id, $diagnosis, $treatment, $vaccination, $weight, $height, $other_details, $visit_date]);
                
                // Update basic health status in orphans table
                $stmtUpdateOrphan = $pdo->prepare("UPDATE orphans SET health_status = ? WHERE orphan_id = ?");
                $stmtUpdateOrphan->execute([$diagnosis, $orphan_id]);
                
                $_SESSION['success_message'] = "Health record added successfully!";
            } catch (PDOException $e) {
                $_SESSION['error_message'] = "Database error: " . $e->getMessage();
            }
        }
    } elseif ($action === 'add_education') {
        $school_name = trim($_POST['school_name'] ?? '');
        $grade = trim($_POST['grade'] ?? '');
        $performance = trim($_POST['performance'] ?? '');
        $target_grade = trim($_POST['target_grade'] ?? '');
        $attendance_target = trim($_POST['attendance_target'] ?? '');
        $behavior = trim($_POST['behavior'] ?? '');
        
        if (empty($school_name) || empty($grade) || empty($performance)) {
            $_SESSION['error_message'] = "School name, grade level, and performance notes are required.";
        } else {
            try {
                $stmtEdu = $pdo->prepare("INSERT INTO education_records (orphan_id, school_name, grade, performance, target_grade, attendance_target, behavior) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmtEdu->execute([$orphan_id, $school_name, $grade, $performance, $target_grade, $attendance_target, $behavior]);
                
                // Update basic education level in orphans table
                $stmtUpdateOrphan = $pdo->prepare("UPDATE orphans SET education_level = ? WHERE orphan_id = ?");
                $stmtUpdateOrphan->execute([$grade, $orphan_id]);
                
                $_SESSION['success_message'] = "Education record updated successfully!";
            } catch (PDOException $e) {
                $_SESSION['error_message'] = "Database error: " . $e->getMessage();
            }
        }
    }
    
    // Redirect to reload the page cleanly
    header("Location: /orphan_management/modules/orphans/view.php?id=" . $orphan_id);
    exit;
}

// Fetch Sub-records
// Query total active sponsorship funds for this orphan
$stmtFunds = $pdo->prepare("SELECT SUM(sponsorship_amount) FROM sponsorships WHERE orphan_id = ? AND status = 'Active'");
$stmtFunds->execute([$orphan_id]);
$sponsorship_funds = $stmtFunds->fetchColumn() ?? 0;

$health_records = $pdo->prepare("SELECT * FROM health_records WHERE orphan_id = ? ORDER BY visit_date DESC");
$health_records->execute([$orphan_id]);
$healths = $health_records->fetchAll();

$education_records = $pdo->prepare("SELECT * FROM education_records WHERE orphan_id = ? ORDER BY updated_at DESC");
$education_records->execute([$orphan_id]);
$educations = $education_records->fetchAll();

$sponsQuery = "
    SELECT s.sponsorship_amount, s.start_date, s.end_date, s.status, dn.full_name AS donor_name, dn.phone AS donor_phone
    FROM sponsorships s 
    JOIN donors dn ON s.donor_id = dn.donor_id 
    WHERE s.orphan_id = ?
";
$sponsParams = [$orphan_id];
if ($role === 'donor') {
    $sponsQuery .= " AND s.donor_id = ?";
    $sponsParams[] = $_SESSION['donor_id'] ?? 0;
}
$sponsQuery .= " ORDER BY s.sponsorship_id DESC";
$sponsorship_records = $pdo->prepare($sponsQuery);
$sponsorship_records->execute($sponsParams);
$sponsorships = $sponsorship_records->fetchAll();

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
          <h1 class="m-0 font-weight-bold text-dark">Orphan Profile</h1>
        </div>
        <div class="col-sm-6 text-right">
          <a href="/orphan_management/modules/orphans/index.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left mr-1"></i> Back to Directory
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
      <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
          <i class="icon fas fa-ban mr-2"></i> <?php echo escape($_SESSION['error_message']); unset($_SESSION['error_message']); ?>
          <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
      <?php endif; ?>

      <div class="row">
        <!-- Sidebar profile info card -->
        <div class="col-md-3">
          <div class="card card-primary card-outline py-3 text-center">
            <div class="card-body box-profile">
              <div class="text-center mb-3">
                <?php if (!empty($orphan['photo']) && file_exists(__DIR__ . '/../../assets/images/' . $orphan['photo'])): ?>
                  <img class="profile-user-img img-fluid img-circle" src="/orphan_management/assets/images/<?php echo escape($orphan['photo']); ?>" alt="Photo" style="width: 130px; height: 130px; object-fit: cover;">
                <?php else: ?>
                  <div class="bg-indigo text-white rounded-circle d-flex align-items-center justify-content-center mx-auto" style="width: 130px; height: 130px; font-size: 3rem; font-weight: bold; border: 3px solid #cbd5e1;">
                    <?php echo strtoupper(substr($orphan['full_name'], 0, 1)); ?>
                  </div>
                <?php endif; ?>
              </div>

              <h3 class="profile-username font-weight-bold text-center"><?php echo escape($orphan['full_name']); ?></h3>
              <p class="text-muted text-center"><?php echo escape($orphan['gender']); ?></p>

              <ul class="list-group list-group-unbordered mb-3 text-left text-sm">
                <li class="list-group-item">
                  <b>Status:</b> 
                  <?php 
                  $status_class = 'badge-secondary';
                  if ($orphan['status'] === 'Active') $status_class = 'badge-success';
                  elseif ($orphan['status'] === 'Sponsored') $status_class = 'badge-info';
                  elseif ($orphan['status'] === 'Adopted') $status_class = 'badge-primary';
                  ?>
                  <span class="float-right badge <?php echo $status_class; ?>"><?php echo escape($orphan['status']); ?></span>
                </li>
                <li class="list-group-item">
                  <b>Date of Birth:</b> <span class="float-right"><?php echo date('M d, Y', strtotime($orphan['date_of_birth'])); ?></span>
                </li>
                <li class="list-group-item">
                  <b>Admission:</b> <span class="float-right"><?php echo date('M d, Y', strtotime($orphan['admission_date'])); ?></span>
                </li>
                <li class="list-group-item">
                  <b>Age:</b> <span class="float-right"><?php 
                    $dob = new DateTime($orphan['date_of_birth']);
                    $today = new DateTime();
                    echo $today->diff($dob)->y;
                  ?> years</span>
                </li>
                <?php if ($sponsorship_funds > 0): ?>
                  <li class="list-group-item" style="background-color: rgba(40, 167, 69, 0.05);">
                    <b>Sponsorship Funds:</b> <span class="float-right text-success font-weight-bold">$<?php echo number_format($sponsorship_funds, 2); ?>/mo</span>
                  </li>
                <?php endif; ?>
              </ul>
              
              <?php if ($role !== 'donor'): ?>
                <a href="/orphan_management/modules/orphans/edit.php?id=<?php echo $orphan['orphan_id']; ?>" class="btn btn-primary btn-block"><b>Edit Profile</b></a>
              <?php elseif ($orphan['status'] === 'Active'): ?>
                <a href="/orphan_management/modules/sponsorships/create.php?orphan_id=<?php echo $orphan['orphan_id']; ?>" class="btn btn-success btn-block font-weight-bold">
                  <i class="fas fa-heart mr-1"></i> Sponsor This Child
                </a>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <!-- Profile Tabs -->
        <div class="col-md-9">
          <div class="card card-primary card-tabs">
            <div class="card-header p-0 pt-1 bg-white border-bottom-0">
              <ul class="nav nav-tabs" id="profileTab" role="tablist">
                <li class="nav-item">
                  <a class="nav-link active font-weight-bold text-secondary" id="bio-tab" data-toggle="pill" href="#bio" role="tab">Overview & Bio</a>
                </li>
                <li class="nav-item">
                  <a class="nav-link font-weight-bold text-secondary" id="health-tab" data-toggle="pill" href="#health" role="tab">Health History</a>
                </li>
                <li class="nav-item">
                  <a class="nav-link font-weight-bold text-secondary" id="education-tab" data-toggle="pill" href="#education" role="tab">Education Details</a>
                </li>
                <li class="nav-item">
                  <a class="nav-link font-weight-bold text-secondary" id="sponsorships-tab" data-toggle="pill" href="#sponsorships" role="tab">Sponsorships</a>
                </li>
              </ul>
            </div>
            
            <div class="card-body">
              <div class="tab-content" id="profileTabContent">
                
                <!-- 1. BIO TAB -->
                <div class="tab-pane fade show active" id="bio" role="tabpanel">
                  <h5 class="font-weight-bold text-primary mb-3">Guardian & Case Information</h5>
                  <p class="text-secondary bg-light p-3 rounded" style="white-space: pre-line; line-height: 1.6;">
                    <?php echo !empty($orphan['guardian_information']) ? escape($orphan['guardian_information']) : 'No background story or guardian information is listed.'; ?>
                  </p>
                </div>

                <!-- 2. HEALTH TAB -->
                <div class="tab-pane fade" id="health" role="tabpanel">
                  <div class="row">
                    <div class="<?php echo ($role === 'donor') ? 'col-md-12' : 'col-md-7'; ?>">
                      <h5 class="font-weight-bold text-danger mb-3">Health Tracking Timeline</h5>
                      <?php if (count($healths) > 0): ?>
                        <div class="timeline timeline-inverse">
                          <?php foreach ($healths as $record): ?>
                            <div>
                              <i class="fas fa-heartbeat bg-danger text-white"></i>
                              <div class="timeline-item">
                                <span class="time text-muted"><i class="far fa-clock"></i> <?php echo date('M d, Y', strtotime($record['visit_date'])); ?></span>
                                <h3 class="timeline-header font-weight-bold"><?php echo escape($record['diagnosis']); ?></h3>
                                <div class="timeline-body text-sm">
                                  <div class="mb-1"><strong>Treatment Protocol:</strong> <?php echo escape($record['treatment']); ?></div>
                                  <?php if (!empty($record['vaccination'])): ?>
                                    <div class="mb-1"><strong>Vaccination:</strong> <span class="badge badge-success"><?php echo escape($record['vaccination']); ?></span></div>
                                  <?php endif; ?>
                                  <?php if (!empty($record['weight']) || !empty($record['height'])): ?>
                                    <div class="mb-1"><strong>Vitals:</strong> 
                                      <?php if (!empty($record['weight'])): ?>
                                        Weight: <span class="text-dark font-weight-bold"><?php echo escape($record['weight']); ?></span> kg
                                      <?php endif; ?>
                                      <?php if (!empty($record['weight']) && !empty($record['height'])) echo ' | '; ?>
                                      <?php if (!empty($record['height'])): ?>
                                        Height: <span class="text-dark font-weight-bold"><?php echo escape($record['height']); ?></span> cm
                                      <?php endif; ?>
                                    </div>
                                  <?php endif; ?>
                                  <?php if (!empty($record['other_details'])): ?>
                                    <div><strong>Other Vital Signs/Details:</strong> <?php echo escape($record['other_details']); ?></div>
                                  <?php endif; ?>
                                </div>
                              </div>
                            </div>
                          <?php endforeach; ?>
                        </div>
                      <?php else: ?>
                        <p class="text-muted">No historic health records tracked.</p>
                      <?php endif; ?>
                    </div>
                    
                    <?php if ($role !== 'donor'): ?>
                    <!-- Add health record form -->
                    <div class="col-md-5 border-left pl-4">
                      <h5 class="font-weight-bold mb-3">Record New Medical Checkup</h5>
                      <form action="" method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <input type="hidden" name="action" value="add_health">
                        
                        <div class="form-group">
                          <label class="text-sm">Diagnosis / Observation <span class="text-danger">*</span></label>
                          <input type="text" name="diagnosis" class="form-control" placeholder="e.g. Annual Checkup, Asthmatic check" required>
                        </div>
                        <div class="form-group">
                          <label class="text-sm">Treatment / Prescription <span class="text-danger">*</span></label>
                          <textarea name="treatment" class="form-control" rows="3" placeholder="Describe medication, advice, or therapy..." required></textarea>
                        </div>
                        <div class="form-group">
                          <label class="text-sm">Vaccination Status <span class="text-danger">*</span></label>
                          <input type="text" name="vaccination" class="form-control" placeholder="e.g. BCG, DPT, COVID-19 booster, or None" required>
                        </div>
                        <div class="row">
                          <div class="col-md-6">
                            <div class="form-group">
                              <label class="text-sm">Weight (kg) <span class="text-danger">*</span></label>
                              <input type="text" name="weight" class="form-control" placeholder="e.g. 28.5" required>
                            </div>
                          </div>
                          <div class="col-md-6">
                            <div class="form-group">
                              <label class="text-sm">Height (cm) <span class="text-danger">*</span></label>
                              <input type="text" name="height" class="form-control" placeholder="e.g. 132" required>
                            </div>
                          </div>
                        </div>
                        <div class="form-group">
                          <label class="text-sm">Other Details / Vitals <span class="text-danger">*</span></label>
                          <input type="text" name="other_details" class="form-control" placeholder="e.g. Temp: 36.8°C, Blood Pressure: 110/70" required>
                        </div>
                        <div class="form-group">
                          <label class="text-sm">Visit Date <span class="text-danger">*</span></label>
                          <input type="date" name="visit_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <button type="submit" class="btn btn-danger btn-sm btn-block">Add Health Record</button>
                      </form>
                    </div>
                    <?php endif; ?>
                  </div>
                </div>

                <!-- 3. EDUCATION TAB -->
                <div class="tab-pane fade" id="education" role="tabpanel">
                  <div class="row">
                    <div class="<?php echo ($role === 'donor') ? 'col-md-12' : 'col-md-7'; ?>">
                      <h5 class="font-weight-bold text-info mb-3">School History Timeline</h5>
                      <?php if (count($educations) > 0): ?>
                        <div class="timeline timeline-inverse">
                          <?php foreach ($educations as $record): ?>
                            <div>
                              <i class="fas fa-graduation-cap bg-info text-white"></i>
                              <div class="timeline-item">
                                <span class="time text-muted"><i class="far fa-clock"></i> <?php echo date('M d, Y', strtotime($record['updated_at'])); ?></span>
                                <h3 class="timeline-header font-weight-bold"><?php echo escape($record['school_name']); ?></h3>
                                <div class="timeline-body text-sm">
                                  <div class="mb-1">Grade/Class: <strong><?php echo escape($record['grade']); ?></strong></div>
                                  <div class="mb-1">Academic Performance: <span class="badge badge-info"><?php echo escape($record['performance']); ?></span></div>
                                  <?php if (!empty($record['target_grade'])): ?>
                                    <div class="mb-1">Target Grade: <span class="badge badge-warning text-dark font-weight-bold"><?php echo escape($record['target_grade']); ?></span></div>
                                  <?php endif; ?>
                                  <?php if (!empty($record['attendance_target'])): ?>
                                    <div class="mb-1">Attendance Target: <span class="badge badge-secondary"><?php echo escape($record['attendance_target']); ?></span></div>
                                  <?php endif; ?>
                                  <?php if (!empty($record['behavior'])): ?>
                                    <div>Behavior Notes: <span class="text-secondary"><?php echo escape($record['behavior']); ?></span></div>
                                  <?php endif; ?>
                                </div>
                              </div>
                            </div>
                          <?php endforeach; ?>
                        </div>
                      <?php else: ?>
                        <p class="text-muted">No academic milestones recorded yet.</p>
                      <?php endif; ?>
                    </div>
                    
                    <?php if ($role !== 'donor'): ?>
                    <!-- Add education form -->
                    <div class="col-md-5 border-left pl-4">
                      <h5 class="font-weight-bold mb-3">Record Academic Milestone</h5>
                      <form action="" method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <input type="hidden" name="action" value="add_education">
                        
                        <div class="form-group">
                          <label class="text-sm">School / Learning Center <span class="text-danger">*</span></label>
                          <input type="text" name="school_name" class="form-control" placeholder="e.g. Springfield Primary" required>
                        </div>
                        <div class="form-group">
                          <label class="text-sm">Grade / Class Level <span class="text-danger">*</span></label>
                          <input type="text" name="grade" class="form-control" placeholder="e.g. 4th Grade" required>
                        </div>
                        <div class="form-group">
                          <label class="text-sm">Academic Performance Notes <span class="text-danger">*</span></label>
                          <input type="text" name="performance" class="form-control" placeholder="e.g. Excellent, Good progress, A Grade" required>
                        </div>
                        <div class="row">
                          <div class="col-md-6">
                            <div class="form-group">
                              <label class="text-sm">Target Grade <span class="text-danger">*</span></label>
                              <input type="text" name="target_grade" class="form-control" placeholder="e.g. A Grade, 95%" required>
                            </div>
                          </div>
                          <div class="col-md-6">
                            <div class="form-group">
                              <label class="text-sm">Attendance Target <span class="text-danger">*</span></label>
                              <input type="text" name="attendance_target" class="form-control" placeholder="e.g. 98%, 100%" required>
                            </div>
                          </div>
                        </div>
                        <div class="form-group">
                          <label class="text-sm">Behavior <span class="text-danger">*</span></label>
                          <input type="text" name="behavior" class="form-control" placeholder="e.g. Excellent attention, respectful" required>
                        </div>
                        <button type="submit" class="btn btn-info btn-sm btn-block">Update Education Record</button>
                      </form>
                    </div>
                    <?php endif; ?>
                  </div>
                </div>

                <!-- 4. SPONSORSHIPS TAB -->
                <div class="tab-pane fade" id="sponsorships" role="tabpanel">
                  <h5 class="font-weight-bold text-success mb-3">Donor Sponsorship Details</h5>
                  <div class="table-responsive">
                    <table class="table table-striped text-sm">
                      <thead>
                        <tr>
                          <th>Donor Name</th>
                          <th>Phone</th>
                          <th>Monthly Amount</th>
                          <th>Start Date</th>
                          <th>End Date</th>
                          <th>Status</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php if (count($sponsorships) > 0): ?>
                          <?php foreach ($sponsorships as $spons): ?>
                            <tr>
                              <td><strong><?php echo escape($spons['donor_name']); ?></strong></td>
                              <td><?php echo escape($spons['donor_phone']); ?></td>
                              <td class="text-success font-weight-bold">$<?php echo number_format($spons['sponsorship_amount'], 2); ?></td>
                              <td><?php echo date('M d, Y', strtotime($spons['start_date'])); ?></td>
                              <td><?php echo $spons['end_date'] ? date('M d, Y', strtotime($spons['end_date'])) : '-'; ?></td>
                              <td>
                                <?php 
                                $status_badge = 'badge-success';
                                if ($spons['status'] === 'Completed') $status_badge = 'badge-secondary';
                                elseif ($spons['status'] === 'Cancelled') $status_badge = 'badge-danger';
                                ?>
                                <span class="badge <?php echo $status_badge; ?>"><?php echo escape($spons['status']); ?></span>
                              </td>
                            </tr>
                          <?php endforeach; ?>
                        <?php else: ?>
                          <tr>
                            <td colspan="6" class="text-center text-muted py-4">No active or historic sponsors for this child.</td>
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
      </div>

    </div>
  </section>
</div>

<?php
include __DIR__ . '/../../includes/footer.php';
?>
