<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth_middleware.php';

// Restricted to Admin only
checkRole(['admin']);

// Fetch Adoption Requests
try {
    $requests = $pdo->query("
        SELECT ar.*, o.full_name AS orphan_name, o.gender AS orphan_gender
        FROM adoption_requests ar 
        JOIN orphans o ON ar.orphan_id = o.orphan_id 
        ORDER BY ar.request_id DESC
    ")->fetchAll();
} catch (PDOException $e) {
    die("Database query error: " . $e->getMessage());
}

// Action: Approve or Reject request
if (isset($_GET['action']) && isset($_GET['id'])) {
    $action = $_GET['action'];
    $request_id = (int)$_GET['id'];
    
    if (in_array($action, ['Approve', 'Reject'])) {
        try {
            $pdo->beginTransaction();
            
            // Find orphan_id
            $stmtFind = $pdo->prepare("SELECT orphan_id FROM adoption_requests WHERE request_id = ? LIMIT 1");
            $stmtFind->execute([$request_id]);
            $orphan_id = $stmtFind->fetchColumn();
            
            if ($orphan_id) {
                if ($action === 'Approve') {
                    // Update request status
                    $stmtApprove = $pdo->prepare("UPDATE adoption_requests SET status = 'Approved' WHERE request_id = ?");
                    $stmtApprove->execute([$request_id]);
                    
                    // Update orphan status to Adopted
                    $stmtOrphan = $pdo->prepare("UPDATE orphans SET status = 'Adopted' WHERE orphan_id = ?");
                    $stmtOrphan->execute([$orphan_id]);
                    
                    // Automatically terminate any active sponsorships for this orphan
                    $stmtSpons = $pdo->prepare("UPDATE sponsorships SET status = 'Completed', end_date = CURDATE() WHERE orphan_id = ? AND status = 'Active'");
                    $stmtSpons->execute([$orphan_id]);
                    
                    $_SESSION['success_message'] = "Adoption request approved. Orphan status set to Adopted.";
                } else {
                    // Reject request
                    $stmtReject = $pdo->prepare("UPDATE adoption_requests SET status = 'Rejected' WHERE request_id = ?");
                    $stmtReject->execute([$request_id]);
                    
                    $_SESSION['success_message'] = "Adoption request rejected.";
                }
            }
            
            $pdo->commit();
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $_SESSION['error_message'] = "Action failed: " . $e->getMessage();
        }
    }
    header("Location: /orphan_management/modules/adoptions/index.php");
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
          <h1 class="m-0 font-weight-bold text-dark">Adoption Requests</h1>
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

      <!-- Requests Table -->
      <div class="card">
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-striped table-hover mb-0">
              <thead>
                <tr>
                  <th>Applicant Details</th>
                  <th>Contact Info</th>
                  <th>Orphan Details</th>
                  <th>Request Date</th>
                  <th>Status</th>
                  <th class="text-right">Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php if (count($requests) > 0): ?>
                  <?php foreach ($requests as $req): ?>
                    <tr>
                      <td>
                        <strong><?php echo escape($req['applicant_name']); ?></strong>
                        <span class="text-xs text-muted d-block"><?php echo escape($req['address']); ?></span>
                      </td>
                      <td>
                        <?php echo escape($req['phone']); ?><br>
                        <a href="mailto:<?php echo escape($req['email']); ?>" class="text-sm"><?php echo escape($req['email']); ?></a>
                      </td>
                      <td>
                        <strong><?php echo escape($req['orphan_name']); ?></strong>
                        <span class="text-xs text-muted d-block"><?php echo escape($req['orphan_gender']); ?></span>
                      </td>
                      <td class="text-muted"><?php echo date('M d, Y', strtotime($req['request_date'])); ?></td>
                      <td>
                        <?php 
                        $status_badge = 'badge-warning';
                        if ($req['status'] === 'Approved') $status_badge = 'badge-success';
                        elseif ($req['status'] === 'Rejected') $status_badge = 'badge-danger';
                        ?>
                        <span class="badge <?php echo $status_badge; ?>"><?php echo escape($req['status']); ?></span>
                      </td>
                      <td class="text-right">
                        <?php if ($req['status'] === 'Pending'): ?>
                          <a href="/orphan_management/modules/adoptions/index.php?action=Approve&id=<?php echo $req['request_id']; ?>" class="btn btn-xs btn-success mr-1" onclick="return confirm('Approve this adoption application? This locks orphan status to Adopted.');">
                            <i class="fas fa-check"></i> Approve
                          </a>
                          <a href="/orphan_management/modules/adoptions/index.php?action=Reject&id=<?php echo $req['request_id']; ?>" class="btn btn-xs btn-danger" onclick="return confirm('Reject this adoption application?');">
                            <i class="fas fa-times"></i> Reject
                          </a>
                        <?php else: ?>
                          <span class="text-muted text-xs">Reviewed</span>
                        <?php endif; ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php else: ?>
                  <tr>
                    <td colspan="6" class="text-center py-5 text-muted">No adoption applications found.</td>
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
