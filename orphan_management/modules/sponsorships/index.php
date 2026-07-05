<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth_middleware.php';

// Restricted to Admin, Staff, and Donor
checkRole(['admin', 'staff', 'donor']);

$role = $_SESSION['role'];
$donor_id = $_SESSION['donor_id'] ?? null;

// Query builds
$query = "
    SELECT s.*, o.full_name AS orphan_name, o.gender AS orphan_gender, o.photo AS orphan_photo, dn.full_name AS donor_name, dn.email AS donor_email 
    FROM sponsorships s 
    JOIN orphans o ON s.orphan_id = o.orphan_id 
    JOIN donors dn ON s.donor_id = dn.donor_id 
    WHERE 1=1
";
$params = [];

if ($role === 'donor') {
    $query .= " AND s.donor_id = ?";
    $params[] = $donor_id;
}

$query .= " ORDER BY s.sponsorship_id DESC";

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $sponsorships = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Database query error: " . $e->getMessage());
}

// Action: Cancel sponsorship (Admin only)
if (isset($_GET['cancel']) && $role === 'admin') {
    $sponsorship_id = (int)$_GET['cancel'];
    try {
        $pdo->beginTransaction();
        
        // Find orphan_id
        $stmtFind = $pdo->prepare("SELECT orphan_id FROM sponsorships WHERE sponsorship_id = ?");
        $stmtFind->execute([$sponsorship_id]);
        $orphan_id_spons = $stmtFind->fetchColumn();
        
        // Update sponsorship record
        $stmtCancel = $pdo->prepare("UPDATE sponsorships SET status = 'Cancelled', end_date = CURDATE() WHERE sponsorship_id = ?");
        $stmtCancel->execute([$sponsorship_id]);
        
        // Check if orphan has any other active sponsorships. If not, reset status to 'Active'
        if ($orphan_id_spons) {
            $stmtCheckActive = $pdo->prepare("SELECT COUNT(*) FROM sponsorships WHERE orphan_id = ? AND status = 'Active'");
            $stmtCheckActive->execute([$orphan_id_spons]);
            $actives = $stmtCheckActive->fetchColumn();
            
            if ($actives == 0) {
                $stmtOrphanReset = $pdo->prepare("UPDATE orphans SET status = 'Active' WHERE orphan_id = ?");
                $stmtOrphanReset->execute([$orphan_id_spons]);
            }
        }
        
        $pdo->commit();
        $_SESSION['success_message'] = "Sponsorship cancelled successfully.";
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $_SESSION['error_message'] = "Failed to cancel: " . $e->getMessage();
    }
    header("Location: /orphan_management/modules/sponsorships/index.php");
    exit;
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
          <h1 class="m-0 font-weight-bold text-dark">Sponsorship Allocations</h1>
        </div>
        <div class="col-sm-6 text-right">
          <?php if ($role === 'admin' || $role === 'donor'): ?>
            <a href="/orphan_management/modules/sponsorships/create.php" class="btn btn-primary">
              <i class="fas fa-plus mr-1"></i> Sponsor a Child
            </a>
          <?php endif; ?>
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

      <!-- Sponsorship List Table -->
      <div class="card">
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-striped table-hover mb-0">
              <thead>
                <tr>
                  <th>Orphan</th>
                  <th>Sponsor (Donor)</th>
                  <th>Monthly Amount</th>
                  <th>Start Date</th>
                  <th>End Date</th>
                  <th>Status</th>
                  <?php if ($role === 'admin'): ?>
                    <th class="text-right">Action</th>
                  <?php endif; ?>
                </tr>
              </thead>
              <tbody>
                <?php if (count($sponsorships) > 0): ?>
                  <?php foreach ($sponsorships as $spons): ?>
                    <tr>
                      <td>
                        <div class="d-flex align-items-center">
                          <div class="mr-3">
                            <?php if (!empty($spons['orphan_photo']) && file_exists(__DIR__ . '/../../assets/images/' . $spons['orphan_photo'])): ?>
                              <img src="/orphan_management/assets/images/<?php echo escape($spons['orphan_photo']); ?>" class="img-circle" style="width: 40px; height: 40px; object-fit: cover;">
                            <?php else: ?>
                              <div class="bg-indigo text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px; font-weight: bold; font-size: 0.9rem;">
                                <?php echo strtoupper(substr($spons['orphan_name'], 0, 1)); ?>
                              </div>
                            <?php endif; ?>
                          </div>
                          <div>
                            <?php if ($role === 'admin' || $role === 'staff' || ($role === 'donor' && $spons['donor_id'] == $donor_id)): ?>
                              <a href="/orphan_management/modules/orphans/view.php?id=<?php echo $spons['orphan_id']; ?>">
                                <strong><?php echo escape($spons['orphan_name']); ?></strong>
                              </a>
                            <?php else: ?>
                              <strong><?php echo escape($spons['orphan_name']); ?></strong>
                            <?php endif; ?>
                            <span class="text-xs text-muted d-block"><?php echo escape($spons['orphan_gender']); ?></span>
                          </div>
                        </div>
                      </td>
                      <td>
                        <strong><?php echo escape($spons['donor_name']); ?></strong>
                        <span class="text-xs text-muted d-block"><?php echo escape($spons['donor_email']); ?></span>
                      </td>
                      <td class="font-weight-bold text-success">$<?php echo number_format($spons['sponsorship_amount'], 2); ?>/mo</td>
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
                      <?php if ($role === 'admin'): ?>
                        <td class="text-right">
                          <?php if ($spons['status'] === 'Active'): ?>
                            <a href="/orphan_management/modules/sponsorships/index.php?cancel=<?php echo $spons['sponsorship_id']; ?>" class="btn btn-xs btn-outline-danger" onclick="return confirm('Cancel this sponsorship allocation?');">
                              Cancel Sponsorship
                            </a>
                          <?php else: ?>
                            <span class="text-muted text-xs">-</span>
                          <?php endif; ?>
                        </td>
                      <?php endif; ?>
                    </tr>
                  <?php endforeach; ?>
                <?php else: ?>
                  <tr>
                    <td colspan="<?php echo ($role === 'admin') ? '7' : '6'; ?>" class="text-center py-5 text-muted">No sponsorship allocations found.</td>
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
