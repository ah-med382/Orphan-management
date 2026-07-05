<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth_middleware.php';

// Access restricted to Admin and Staff
checkRole(['admin', 'staff']);

// Fetch search and filter parameters
$search = trim($_GET['search'] ?? '');
$status = trim($_GET['status'] ?? '');
$gender = trim($_GET['gender'] ?? '');

// Build query
$query = "SELECT * FROM orphans WHERE 1=1";
$params = [];

if ($search !== '') {
    $query .= " AND full_name LIKE ?";
    $params[] = "%$search%";
}
if ($status !== '') {
    $query .= " AND status = ?";
    $params[] = $status;
}
if ($gender !== '') {
    $query .= " AND gender = ?";
    $params[] = $gender;
}
$query .= " ORDER BY full_name ASC";

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $orphans = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
  <!-- Content Header -->
  <div class="content-header">
    <div class="container-fluid">
      <div class="row mb-2">
        <div class="col-sm-6">
          <h1 class="m-0 font-weight-bold text-dark">Orphans Directory</h1>
        </div>
        <div class="col-sm-6 text-right">
          <?php if ($_SESSION['role'] === 'admin'): ?>
            <a href="/orphan_management/modules/orphans/create.php" class="btn btn-primary">
              <i class="fas fa-plus mr-1"></i> Add New Orphan
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

      <!-- Search and Filter Form -->
      <div class="card mb-4">
        <div class="card-body">
          <form method="GET" action="">
            <div class="row">
              <div class="col-md-5 mb-2 mb-md-0">
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                  </div>
                  <input type="text" name="search" class="form-control" placeholder="Search by name..." value="<?php echo escape($search); ?>">
                </div>
              </div>
              <div class="col-md-3 mb-2 mb-md-0">
                <select name="status" class="form-control">
                  <option value="">All Statuses</option>
                  <option value="Active" <?php echo ($status === 'Active') ? 'selected' : ''; ?>>Active</option>
                  <option value="Sponsored" <?php echo ($status === 'Sponsored') ? 'selected' : ''; ?>>Sponsored</option>
                  <option value="Adopted" <?php echo ($status === 'Adopted') ? 'selected' : ''; ?>>Adopted</option>
                  <option value="Inactive" <?php echo ($status === 'Inactive') ? 'selected' : ''; ?>>Inactive</option>
                </select>
              </div>
              <div class="col-md-2 mb-2 mb-md-0">
                <select name="gender" class="form-control">
                  <option value="">All Genders</option>
                  <option value="Male" <?php echo ($gender === 'Male') ? 'selected' : ''; ?>>Male</option>
                  <option value="Female" <?php echo ($gender === 'Female') ? 'selected' : ''; ?>>Female</option>
                </select>
              </div>
              <div class="col-md-2">
                <button type="submit" class="btn btn-primary btn-block">Filter</button>
              </div>
            </div>
          </form>
        </div>
      </div>

      <!-- Orphans Grid/List -->
      <div class="row">
        <?php if (count($orphans) > 0): ?>
          <?php foreach ($orphans as $orphan): ?>
            <div class="col-lg-3 col-md-4 col-sm-6 mb-4">
              <div class="card h-100 text-center py-4 px-3">
                <div class="position-absolute" style="top: 15px; right: 15px;">
                  <?php 
                  $status_class = 'badge-secondary';
                  if ($orphan['status'] === 'Active') $status_class = 'badge-success';
                  elseif ($orphan['status'] === 'Sponsored') $status_class = 'badge-info';
                  elseif ($orphan['status'] === 'Adopted') $status_class = 'badge-primary';
                  ?>
                  <span class="badge <?php echo $status_class; ?> role-badge px-2 py-1"><?php echo escape($orphan['status']); ?></span>
                </div>
                
                <div class="mx-auto mb-3">
                  <?php if (!empty($orphan['photo']) && file_exists(__DIR__ . '/../../assets/images/' . $orphan['photo'])): ?>
                    <img src="/orphan_management/assets/images/<?php echo escape($orphan['photo']); ?>" class="img-circle elevation-2" alt="Photo" style="width: 100px; height: 100px; object-fit: cover; border: 3px solid #cbd5e1;">
                  <?php else: ?>
                    <div class="bg-indigo text-white rounded-circle d-flex align-items-center justify-content-center elevation-2 mx-auto" style="width: 100px; height: 100px; font-size: 2.2rem; font-weight: bold; border: 3px solid #cbd5e1;">
                      <?php echo strtoupper(substr($orphan['full_name'], 0, 1)); ?>
                    </div>
                  <?php endif; ?>
                </div>

                <h5 class="font-weight-bold mb-1 text-dark"><?php echo escape($orphan['full_name']); ?></h5>
                <p class="text-muted text-sm mb-2"><?php echo escape($orphan['gender']); ?> | Age: <?php 
                  $dob = new DateTime($orphan['date_of_birth']);
                  $today = new DateTime();
                  $age = $today->diff($dob)->y;
                  echo $age;
                ?></p>

                <div class="border-top pt-3 mt-2 text-left">
                  <div class="text-xs text-muted mb-1">
                    <i class="fas fa-calendar-alt mr-1"></i> Admitted: <?php echo date('M d, Y', strtotime($orphan['admission_date'])); ?>
                  </div>
                  <div class="text-xs text-muted mb-1">
                    <i class="fas fa-graduation-cap mr-1"></i> Education: <?php echo escape($orphan['education_level']); ?>
                  </div>
                  <div class="text-xs text-muted">
                    <i class="fas fa-notes-medical mr-1"></i> Health: <?php echo escape($orphan['health_status']); ?>
                  </div>
                </div>

                <div class="mt-4 pt-2 d-flex justify-content-around">
                  <a href="/orphan_management/modules/orphans/view.php?id=<?php echo $orphan['orphan_id']; ?>" class="btn btn-outline-info btn-xs px-2 py-1">
                    <i class="fas fa-eye mr-1"></i> Profile
                  </a>
                  <a href="/orphan_management/modules/orphans/edit.php?id=<?php echo $orphan['orphan_id']; ?>" class="btn btn-outline-primary btn-xs px-2 py-1">
                    <i class="fas fa-edit mr-1"></i> Edit
                  </a>
                  <?php if ($_SESSION['role'] === 'admin'): ?>
                    <a href="/orphan_management/modules/orphans/delete.php?id=<?php echo $orphan['orphan_id']; ?>" class="btn btn-outline-danger btn-xs px-2 py-1" onclick="return confirm('Are you sure you want to delete this record?');">
                      <i class="fas fa-trash mr-1"></i> Delete
                    </a>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="col-12 text-center py-5">
            <i class="fas fa-child text-muted mb-3" style="font-size: 4rem;"></i>
            <h5 class="text-muted">No orphans found matching your search.</h5>
          </div>
        <?php endif; ?>
      </div>

    </div>
  </section>
</div>

<?php
include __DIR__ . '/../../includes/footer.php';
?>
