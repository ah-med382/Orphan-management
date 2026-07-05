<?php
// Active menu helper
$current_uri = $_SERVER['REQUEST_URI'];
function isActive($path) {
    global $current_uri;
    return (strpos($current_uri, $path) !== false) ? 'active' : '';
}

// Fetch user profile avatar dynamically
$user_avatar = null;
if (isset($_SESSION['user_id']) && isset($_SESSION['role']) && isset($pdo)) {
    try {
        if ($_SESSION['role'] === 'donor') {
            $stmtAv = $pdo->prepare("SELECT profile_image FROM donors WHERE user_id = ? LIMIT 1");
            $stmtAv->execute([$_SESSION['user_id']]);
            $user_avatar = $stmtAv->fetchColumn();
        } elseif ($_SESSION['role'] === 'staff') {
            $stmtAv = $pdo->prepare("SELECT profile_image FROM staff WHERE user_id = ? LIMIT 1");
            $stmtAv->execute([$_SESSION['user_id']]);
            $user_avatar = $stmtAv->fetchColumn();
        }
    } catch (PDOException $e) {
        // Fail silently
    }
}
?>
<!-- Main Sidebar Container -->
<aside class="main-sidebar sidebar-dark-primary elevation-4">
  <!-- Brand Logo -->
  <a href="/orphan_management/index.php" class="brand-link d-flex align-items-center justify-content-center py-2" style="background-color: rgba(0, 0, 0, 0.2);">
    <img src="/orphan_management/assets/images/zamzam_logo.jpg" alt="Zamzam KidsCare Logo" class="brand-image img-circle elevation-3" style="opacity: .9; max-height: 38px; width: 38px; height: 38px; object-fit: cover; border: 1.5px solid rgba(255, 255, 255, 0.6); margin-right: 8px;">
    <span class="brand-text font-weight-bold text-white" style="font-size: 1.1rem; letter-spacing: -0.2px;">Zamzam KidsCare</span>
  </a>

  <!-- Sidebar -->
  <div class="sidebar px-0">
    <!-- Sidebar user panel (optional) -->
    <div class="user-panel mt-3 pb-3 mb-3 d-flex align-items-center px-3">
      <div class="image">
        <?php if (!empty($user_avatar) && file_exists(__DIR__ . '/../assets/images/' . $user_avatar)): ?>
          <img src="/orphan_management/assets/images/<?php echo escape($user_avatar); ?>" class="img-circle elevation-2" style="width: 40px; height: 40px; object-fit: cover; border: 1.5px solid rgba(255, 255, 255, 0.4);" alt="User Image">
        <?php else: ?>
          <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px; font-weight: 600; border: 1.5px solid rgba(255, 255, 255, 0.4);">
            <?php echo strtoupper(substr($_SESSION['full_name'] ?? 'U', 0, 1)); ?>
          </div>
        <?php endif; ?>
      </div>
      <div class="info ml-3">
        <a href="#" class="d-block font-weight-medium text-white"><?php echo escape($_SESSION['full_name'] ?? 'User'); ?></a>
        <span class="text-xs text-muted" style="letter-spacing: 0.05em;"><?php echo escape(ucfirst($_SESSION['role'] ?? '')); ?></span>
      </div>
    </div>

    <!-- Sidebar Menu -->
    <nav class="mt-2">
      <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">
        
        <!-- Dashboard Link (Adapts dynamically to roles) -->
        <li class="nav-item">
          <a href="/orphan_management/index.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'index.php' && strpos($current_uri, '/modules/') === false) ? 'active' : ''; ?>">
            <i class="nav-icon fas fa-tachometer-alt"></i>
            <p>Dashboard</p>
          </a>
        </li>

        <?php if ($_SESSION['role'] === 'admin'): ?>
          
          <!-- ADMIN LINKS -->
          <li class="nav-header text-uppercase text-xs text-muted px-4 mt-3">Orphanage Admin</li>
          
          <li class="nav-item">
            <a href="/orphan_management/modules/orphans/index.php" class="nav-link <?php echo isActive('/modules/orphans/'); ?>">
              <i class="nav-icon fas fa-child"></i>
              <p>Manage Orphans</p>
            </a>
          </li>
          
          <li class="nav-item">
            <a href="/orphan_management/modules/donors/index.php" class="nav-link <?php echo isActive('/modules/donors/'); ?>">
              <i class="nav-icon fas fa-user-friends"></i>
              <p>Manage Donors</p>
            </a>
          </li>

          <li class="nav-item">
            <a href="/orphan_management/modules/donations/index.php" class="nav-link <?php echo isActive('/modules/donations/'); ?>">
              <i class="nav-icon fas fa-hand-holding-usd"></i>
              <p>Donations</p>
            </a>
          </li>

          <li class="nav-item">
            <a href="/orphan_management/modules/sponsorships/index.php" class="nav-link <?php echo isActive('/modules/sponsorships/'); ?>">
              <i class="nav-icon fas fa-ribbon"></i>
              <p>Sponsorships</p>
            </a>
          </li>

          <li class="nav-item">
            <a href="/orphan_management/modules/adoptions/index.php" class="nav-link <?php echo isActive('/modules/adoptions/'); ?>">
              <i class="nav-icon fas fa-home"></i>
              <p>Adoptions</p>
            </a>
          </li>

          <li class="nav-item">
            <a href="/orphan_management/modules/reports/index.php" class="nav-link <?php echo isActive('/modules/reports/'); ?>">
              <i class="nav-icon fas fa-chart-line"></i>
              <p>Reports Panel</p>
            </a>
          </li>

          <li class="nav-item">
            <a href="/orphan_management/admin/finance.php" class="nav-link <?php echo isActive('/admin/finance.php'); ?>">
              <i class="nav-icon fas fa-university"></i>
              <p>Central Finance</p>
            </a>
          </li>

          <li class="nav-header text-uppercase text-xs text-muted px-4 mt-3">System</li>

          <li class="nav-item">
            <a href="/orphan_management/admin/staff.php" class="nav-link <?php echo isActive('/admin/staff.php'); ?>">
              <i class="nav-icon fas fa-user-shield"></i>
              <p>Staff Directory</p>
            </a>
          </li>

          <li class="nav-item">
            <a href="/orphan_management/admin/users.php" class="nav-link <?php echo isActive('/admin/users.php'); ?>">
              <i class="nav-icon fas fa-users-cog"></i>
              <p>System Users</p>
            </a>
          </li>

        <?php elseif ($_SESSION['role'] === 'staff'): ?>

          <!-- STAFF LINKS -->
          <li class="nav-header text-uppercase text-xs text-muted px-4 mt-3">Staff Controls</li>
          
          <li class="nav-item">
            <a href="/orphan_management/modules/orphans/index.php" class="nav-link <?php echo isActive('/modules/orphans/'); ?>">
              <i class="nav-icon fas fa-child"></i>
              <p>Orphans Directory</p>
            </a>
          </li>

          <li class="nav-item">
            <a href="/orphan_management/modules/donors/index.php" class="nav-link <?php echo isActive('/modules/donors/'); ?>">
              <i class="nav-icon fas fa-user-friends"></i>
              <p>Manage Donors</p>
            </a>
          </li>

          <li class="nav-item">
            <a href="/orphan_management/modules/donations/index.php" class="nav-link <?php echo isActive('/modules/donations/'); ?>">
              <i class="nav-icon fas fa-hand-holding-usd"></i>
              <p>Donations Ledger</p>
            </a>
          </li>

          <li class="nav-item">
            <a href="/orphan_management/modules/sponsorships/index.php" class="nav-link <?php echo isActive('/modules/sponsorships/'); ?>">
              <i class="nav-icon fas fa-ribbon"></i>
              <p>View Sponsorships</p>
            </a>
          </li>

        <?php elseif ($_SESSION['role'] === 'donor'): ?>

          <!-- DONOR LINKS -->
          <li class="nav-header text-uppercase text-xs text-muted px-4 mt-3">Donor Hub</li>

          <li class="nav-item">
            <a href="/orphan_management/modules/donations/create.php" class="nav-link <?php echo isActive('/modules/donations/create.php'); ?>">
              <i class="nav-icon fas fa-donate"></i>
              <p>Make a Donation</p>
            </a>
          </li>

          <li class="nav-item">
            <a href="/orphan_management/modules/donations/index.php" class="nav-link <?php echo (isActive('/modules/donations/') && !isActive('/modules/donations/create.php')); ?>">
              <i class="nav-icon fas fa-history"></i>
              <p>My Donation History</p>
            </a>
          </li>

          <li class="nav-item">
            <a href="/orphan_management/modules/sponsorships/create.php" class="nav-link <?php echo isActive('/modules/sponsorships/create.php'); ?>">
              <i class="nav-icon fas fa-heart"></i>
              <p>Sponsor an Orphan</p>
            </a>
          </li>

          <li class="nav-item">
            <a href="/orphan_management/modules/sponsorships/index.php" class="nav-link <?php echo (isActive('/modules/sponsorships/') && !isActive('/modules/sponsorships/create.php')); ?>">
              <i class="nav-icon fas fa-award"></i>
              <p>My Sponsorships</p>
            </a>
          </li>

          <li class="nav-item">
            <a href="/orphan_management/modules/adoptions/create.php" class="nav-link <?php echo isActive('/modules/adoptions/create.php'); ?>">
              <i class="nav-icon fas fa-file-signature"></i>
              <p>Apply for Adoption</p>
            </a>
          </li>

        <?php endif; ?>

      </ul>
    </nav>
    <!-- /.sidebar-menu -->
  </div>
  <!-- /.sidebar -->
</aside>
