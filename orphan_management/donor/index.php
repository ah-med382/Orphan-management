<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_middleware.php';

// Enforce Donor role
checkRole(['donor']);

// Fallback safety to check and resolve donor_id
if (!isset($_SESSION['donor_id'])) {
    $stmt = $pdo->prepare("SELECT donor_id FROM donors WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $_SESSION['donor_id'] = $stmt->fetchColumn();
}

$donor_id = $_SESSION['donor_id'];

// If donor record doesn't exist, redirect to error page or profile setup
if (!$donor_id) {
    die("Donor profile not found. Please contact administration.");
}

// Fetch donor dynamic details (Avatar & Name)
$stmtDonorInfo = $pdo->prepare("SELECT full_name, profile_image FROM donors WHERE user_id = ? LIMIT 1");
$stmtDonorInfo->execute([$_SESSION['user_id']]);
$donor_info = $stmtDonorInfo->fetch();
$donor_display_name = $donor_info['full_name'] ?? $_SESSION['full_name'];
$donor_profile_image = $donor_info['profile_image'] ?? '';

// Dismiss notification of departed orphan
if (isset($_GET['dismiss_notification'])) {
    $dismiss_id = (int)$_GET['dismiss_notification'];
    $stmtDismiss = $pdo->prepare("UPDATE sponsorships SET notification_read = 1 WHERE sponsorship_id = ? AND donor_id = ?");
    $stmtDismiss->execute([$dismiss_id, $donor_id]);
    header("Location: /orphan_management/donor/index.php");
    exit;
}

// Fetch unread notifications for departure of sponsored child
$stmtLeft = $pdo->prepare("
    SELECT s.sponsorship_id, o.full_name AS orphan_name 
    FROM sponsorships s
    JOIN orphans o ON s.orphan_id = o.orphan_id
    WHERE s.donor_id = ? AND s.orphan_left_at IS NOT NULL AND s.notification_read = 0
");
$stmtLeft->execute([$donor_id]);
$left_notifications = $stmtLeft->fetchAll();

// Fetch stats for this donor
$stmtDonationsSum = $pdo->prepare("SELECT SUM(amount) FROM donations WHERE donor_id = ?");
$stmtDonationsSum->execute([$donor_id]);
$my_total_donated = $stmtDonationsSum->fetchColumn() ?? 0;

$stmtSponsorshipsCount = $pdo->prepare("SELECT COUNT(*) FROM sponsorships WHERE donor_id = ? AND status = 'Active'");
$stmtSponsorshipsCount->execute([$donor_id]);
$my_active_sponsorships = $stmtSponsorshipsCount->fetchColumn();

// Fetch Donor's Recent Donations
$stmtRecent = $pdo->prepare("
    SELECT amount, payment_method, donation_date, notes 
    FROM donations 
    WHERE donor_id = ? 
    ORDER BY donation_id DESC 
    LIMIT 5
");
$stmtRecent->execute([$donor_id]);
$my_recent_donations = $stmtRecent->fetchAll();

// Fetch Donor's Active Sponsored Orphans
$stmtOrphans = $pdo->prepare("
    SELECT o.orphan_id, o.full_name, o.gender, o.date_of_birth, o.photo, s.sponsorship_amount, s.start_date 
    FROM sponsorships s 
    JOIN orphans o ON s.orphan_id = o.orphan_id 
    WHERE s.donor_id = ? AND s.status = 'Active'
");
$stmtOrphans->execute([$donor_id]);
$my_sponsored_orphans = $stmtOrphans->fetchAll();

// Fetch Active orphans who need sponsors (unsponsored children)
$stmtActiveOrphans = $pdo->prepare("
    SELECT orphan_id, full_name, gender, date_of_birth, photo, health_status, education_level 
    FROM orphans 
    WHERE status = 'Active' 
    ORDER BY orphan_id DESC
");
$stmtActiveOrphans->execute();
$active_orphans_in_need = $stmtActiveOrphans->fetchAll();

$csrf_token = getCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Donor Hub | Zamzam KidsCare</title>

  <!-- Google Font: Inter -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  
  <!-- Font Awesome Icons -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
  <!-- Theme style (AdminLTE / Bootstrap base) -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
  
  <style>
    body {
      font-family: 'Inter', sans-serif;
      background-color: #f8fafc;
      color: #1e293b;
    }
    
    /* Navbar styling */
    .web-navbar {
      background-color: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(10px);
      box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.05);
      border-bottom: 1px solid #e2e8f0;
      position: sticky;
      top: 0;
      z-index: 1030;
      padding: 0.75rem 1.5rem;
    }
    .web-brand {
      display: flex;
      align-items: center;
      text-decoration: none !important;
      color: #1e293b !important;
    }
    .web-brand img {
      width: 40px;
      height: 40px;
      object-fit: cover;
      border-radius: 50%;
      margin-right: 0.75rem;
      border: 2px solid #3b82f6;
    }
    .web-nav-links {
      display: flex;
      align-items: center;
      gap: 1.5rem;
      margin-bottom: 0;
      padding-left: 0;
      list-style: none;
    }
    .web-nav-link {
      color: #64748b !important;
      font-weight: 500;
      font-size: 0.9rem;
      text-decoration: none !important;
      transition: color 0.2s ease;
      padding: 0.5rem 0.25rem;
    }
    .web-nav-link:hover, .web-nav-link.active {
      color: #3b82f6 !important;
    }
    
    /* Profile & Actions */
    .profile-link-nav {
      display: flex;
      align-items: center;
      background-color: #f1f5f9;
      padding: 0.35rem 0.75rem;
      border-radius: 9999px;
      margin-right: 1rem;
      border: 1px solid #e2e8f0;
      text-decoration: none !important;
      transition: all 0.2s ease;
    }
    .profile-link-nav:hover {
      background-color: #e2e8f0;
    }
    .profile-link-nav img {
      width: 28px;
      height: 28px;
      border-radius: 50%;
      object-fit: cover;
      margin-right: 0.5rem;
      border: 1.5px solid #cbd5e1;
    }
    .profile-link-nav .avatar-fallback {
      width: 28px;
      height: 28px;
      border-radius: 50%;
      background-color: #3b82f6;
      color: white;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: bold;
      font-size: 0.75rem;
      margin-right: 0.5rem;
    }
    .profile-link-nav .donor-name {
      font-size: 0.8rem;
      font-weight: 600;
      color: #1e293b;
    }

    /* Hero entrance animations */
    @keyframes fadeInUp {
      from {
        opacity: 0;
        transform: translateY(30px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }
    .animate-fade-up {
      animation: fadeInUp 0.8s cubic-bezier(0.16, 1, 0.3, 1) forwards;
    }
    .animate-delay-1 {
      animation-delay: 0.15s;
      opacity: 0;
    }
    .animate-delay-2 {
      animation-delay: 0.3s;
      opacity: 0;
    }
    
    /* Hero section */
    .web-hero {
      background: linear-gradient(rgba(15, 23, 42, 0.75), rgba(30, 27, 75, 0.85)), url('/orphan_management/assets/images/hero_bg.jpg');
      background-size: cover;
      background-position: center;
      color: white;
      padding: 6.5rem 1rem;
      text-align: center;
    }
    .web-hero-content {
      max-width: 800px;
      margin: 0 auto;
    }
    .web-hero-title {
      font-size: 2.75rem;
      font-weight: 800;
      letter-spacing: -0.05em;
      line-height: 1.15;
      margin-bottom: 1.25rem;
    }
    .web-hero-subtitle {
      font-size: 1.15rem;
      font-weight: 400;
      color: #cbd5e1;
      line-height: 1.6;
      margin-bottom: 2rem;
    }
    
    /* Content sections styling */
    .section-title {
      font-weight: 700;
      letter-spacing: -0.025em;
      margin-bottom: 0.5rem;
    }
    .section-subtitle {
      color: #64748b;
      margin-bottom: 2.25rem;
    }
    .section-block {
      padding: 4rem 0;
    }
    .section-block-alt {
      background-color: #f1f5f9;
      padding: 4rem 0;
    }
    
    /* Orphan Card Custom styling */
    .orphan-card {
      border: none;
      border-radius: 12px;
      box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.025);
      transition: all 0.25s ease;
      background-color: white;
      overflow: hidden;
      display: flex;
      flex-direction: column;
      height: 100%;
    }
    .orphan-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.08), 0 4px 6px -2px rgba(0, 0, 0, 0.03);
    }
    .orphan-card-img-wrapper {
      position: relative;
      height: 220px;
      overflow: hidden;
      background-color: #f1f5f9;
    }
    .orphan-card-img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }
    .orphan-card-badge {
      position: absolute;
      top: 1rem;
      right: 1rem;
      background-color: #10b981;
      color: white;
      font-weight: 600;
      font-size: 0.75rem;
      padding: 0.25rem 0.75rem;
      border-radius: 9999px;
      text-transform: uppercase;
      letter-spacing: 0.05em;
    }
    .orphan-card-body {
      padding: 1.5rem;
      display: flex;
      flex-direction: column;
      flex-grow: 1;
    }
    .orphan-card-name {
      font-size: 1.15rem;
      font-weight: 700;
      margin-bottom: 0.25rem;
    }
    .orphan-card-meta {
      font-size: 0.8rem;
      color: #64748b;
      margin-bottom: 1rem;
    }
    .orphan-card-detail {
      font-size: 0.85rem;
      margin-bottom: 0.5rem;
      display: flex;
      justify-content: space-between;
    }
    .orphan-card-detail span:first-child {
      color: #64748b;
    }
    .orphan-card-detail span:last-child {
      font-weight: 500;
    }
    
    /* Stats box layout */
    .metric-card {
      border: 1px solid #e2e8f0;
      border-radius: 12px;
      background-color: white;
      padding: 1.5rem;
      display: flex;
      align-items: center;
      box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.02);
    }
    .metric-card-icon {
      width: 48px;
      height: 48px;
      border-radius: 10px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.5rem;
      margin-right: 1.25rem;
    }
    .metric-card-val {
      font-size: 1.5rem;
      font-weight: 700;
      margin-bottom: 0;
      line-height: 1.2;
    }
    .metric-card-label {
      font-size: 0.8rem;
      color: #64748b;
      margin-bottom: 0;
      font-weight: 500;
    }
    
    /* Footer Styling */
    .web-footer {
      background-color: #0f172a;
      color: #94a3b8;
      padding: 3rem 1.5rem;
      border-top: 1px solid #1e293b;
    }
    .web-footer-brand {
      color: white !important;
      font-weight: 700;
      text-decoration: none;
      display: flex;
      align-items: center;
      font-size: 1.25rem;
    }
    .web-footer-brand img {
      width: 32px;
      height: 32px;
      border-radius: 50%;
      margin-right: 0.5rem;
    }
  </style>
</head>
<body>

  <!-- Sticky Web Navbar -->
  <nav class="web-navbar navbar navbar-expand-lg">
    <div class="container">
      <a href="/orphan_management/donor/index.php" class="web-brand">
        <img src="/orphan_management/assets/images/zamzam_logo.jpg" alt="Zamzam Logo">
        <span class="font-weight-bold" style="font-size: 1.15rem; letter-spacing: -0.5px;">Zamzam KidsCare</span>
      </a>
      
      <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#webNavbarMenu">
        <span class="fas fa-bars text-secondary"></span>
      </button>

      <div class="collapse navbar-collapse" id="webNavbarMenu">
        <ul class="web-nav-links mx-auto mt-2 mt-lg-0">
          <li><a href="/orphan_management/donor/index.php" class="web-nav-link active">Dashboard</a></li>
          <li><a href="/orphan_management/modules/donations/create.php" class="web-nav-link">Make a Donation</a></li>
          <li><a href="/orphan_management/modules/sponsorships/create.php" class="web-nav-link">Sponsor an Orphan</a></li>
          <li><a href="/orphan_management/modules/adoptions/create.php" class="web-nav-link">Apply for Adoption</a></li>
          <li><a href="/orphan_management/modules/sponsorships/index.php" class="web-nav-link">My Sponsorships</a></li>
        </ul>

        <div class="d-flex align-items-center mt-3 mt-lg-0">
          <a href="/orphan_management/donor/profile.php" class="profile-link-nav">
            <?php if (!empty($donor_profile_image) && file_exists(__DIR__ . '/../assets/images/' . $donor_profile_image)): ?>
              <img src="/orphan_management/assets/images/<?php echo escape($donor_profile_image); ?>" alt="Donor avatar">
            <?php else: ?>
              <div class="avatar-fallback"><?php echo strtoupper(substr($donor_display_name, 0, 1)); ?></div>
            <?php endif; ?>
            <span class="donor-name"><?php echo escape($donor_display_name); ?></span>
          </a>
          <a href="/orphan_management/logout.php" class="btn btn-sm btn-outline-danger font-weight-bold" style="border-radius: 9999px; padding: 0.35rem 1rem;">
            <i class="fas fa-sign-out-alt mr-1"></i> Logout
          </a>
        </div>
      </div>
    </div>
  </nav>

  <!-- Hero Section -->
  <header class="web-hero">
    <div class="container web-hero-content">
      <h1 class="web-hero-title animate-fade-up">Building a Better Future for Orphaned Children Together</h1>
      <p class="web-hero-subtitle animate-fade-up animate-delay-1">We manage and support orphan care by ensuring children receive shelter, education, healthcare, and a dignified life.</p>
      <a href="#orphans-section" class="btn btn-lg btn-primary px-4 py-3 font-weight-bold animate-fade-up animate-delay-2" style="border-radius: 8px; box-shadow: 0 4px 14px 0 rgba(59, 130, 246, 0.5);">
        <i class="fas fa-heart mr-2"></i> Browse Children in Need
      </a>
    </div>
  </header>

  <!-- Notification Center -->
  <?php if (count($left_notifications) > 0): ?>
    <div class="container mt-4">
      <?php foreach ($left_notifications as $notif): ?>
        <div class="alert alert-warning alert-dismissible fade show mb-2 border-0" role="alert" style="border-radius: 12px; box-shadow: 0 2px 4px rgba(0,0,0,0.02);">
          <i class="icon fas fa-exclamation-triangle mr-2 text-warning"></i>
          <strong>Notice:</strong> The child you were sponsoring, <strong><?php echo escape($notif['orphan_name']); ?></strong>, has left the orphanage. Thank you for your support.
          <a href="/orphan_management/donor/index.php?dismiss_notification=<?php echo $notif['sponsorship_id']; ?>" class="close" aria-label="Dismiss" style="color: inherit; opacity: 0.7; text-decoration: none;">
            <span aria-hidden="true">&times;</span>
          </a>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <!-- Section 1: Children in Need of Sponsorship -->
  <section class="section-block" id="orphans-section">
    <div class="container">
      <div class="text-center">
        <h2 class="section-title">Children Awaiting Sponsorship</h2>
        <p class="section-subtitle">Read their stories and support their development journey by sponsoring them.</p>
      </div>

      <div class="row mt-4">
        <?php if (count($active_orphans_in_need) > 0): ?>
          <?php foreach ($active_orphans_in_need as $orphan): ?>
            <?php 
              $dob = new DateTime($orphan['date_of_birth']);
              $age = (new DateTime())->diff($dob)->y;
            ?>
            <div class="col-md-4 mb-4">
              <div class="orphan-card">
                <div class="orphan-card-img-wrapper">
                  <?php if (!empty($orphan['photo']) && file_exists(__DIR__ . '/../assets/images/' . $orphan['photo'])): ?>
                    <img src="/orphan_management/assets/images/<?php echo escape($orphan['photo']); ?>" class="orphan-card-img" alt="Orphan Photo">
                  <?php else: ?>
                    <div class="w-100 h-100 bg-indigo text-white d-flex align-items-center justify-content-center" style="font-size: 3.5rem; font-weight: 700;">
                      <?php echo strtoupper(substr($orphan['full_name'], 0, 1)); ?>
                    </div>
                  <?php endif; ?>
                  <span class="orphan-card-badge">Active</span>
                </div>
                <div class="orphan-card-body">
                  <h3 class="orphan-card-name"><?php echo escape($orphan['full_name']); ?></h3>
                  <div class="orphan-card-meta"><i class="fas fa-birthday-cake mr-1"></i> <?php echo $age; ?> years old</div>
                  
                  <div class="orphan-card-detail">
                    <span>Gender</span>
                    <span><?php echo escape($orphan['gender']); ?></span>
                  </div>
                  <div class="orphan-card-detail">
                    <span>Health Status</span>
                    <span><?php echo escape($orphan['health_status']); ?></span>
                  </div>
                  <div class="orphan-card-detail mb-3">
                    <span>Education Level</span>
                    <span><?php echo escape($orphan['education_level']); ?></span>
                  </div>

                  <a href="/orphan_management/modules/orphans/view.php?id=<?php echo $orphan['orphan_id']; ?>" class="btn btn-success mt-auto font-weight-bold" style="border-radius: 8px;">
                    <i class="fas fa-eye mr-1"></i> View Profile & Sponsor
                  </a>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="col-12 text-center py-5">
            <div class="text-muted"><i class="fas fa-info-circle mb-3" style="font-size: 3rem;"></i></div>
            <h5 class="text-secondary font-weight-bold">All Children Sponsored</h5>
            <p class="text-muted text-sm">Thank you! All orphans currently registered are fully sponsored.</p>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </section>

  <!-- Section 2: Contribution Statistics & History -->
  <section class="section-block-alt">
    <div class="container">
      <div class="text-center mb-5">
        <h2 class="section-title">My Contributions Hub</h2>
        <p class="section-subtitle">Track your donations ledger and children you actively sponsor.</p>
      </div>

      <!-- Quick Metrics row -->
      <div class="row mb-5">
        <div class="col-md-6 mb-3 mb-md-0">
          <div class="metric-card">
            <div class="metric-card-icon bg-success-light text-success" style="background-color: rgba(40, 167, 69, 0.1);">
              <i class="fas fa-hand-holding-usd"></i>
            </div>
            <div>
              <p class="metric-card-val">$<?php echo number_format($my_total_donated, 2); ?></p>
              <p class="metric-card-label">My Total Contributions</p>
            </div>
          </div>
        </div>
        <div class="col-md-6">
          <div class="metric-card">
            <div class="metric-card-icon bg-info-light text-info" style="background-color: rgba(23, 162, 184, 0.1);">
              <i class="fas fa-heart"></i>
            </div>
            <div>
              <p class="metric-card-val"><?php echo $my_active_sponsorships; ?></p>
              <p class="metric-card-label">Active Sponsored Children</p>
            </div>
          </div>
        </div>
      </div>

      <div class="row">
        <!-- My Sponsored Orphans list -->
        <div class="col-lg-7 mb-4">
          <div class="card border-0 h-100" style="border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.02);">
            <div class="card-header bg-white border-0 py-3">
              <h4 class="card-title font-weight-bold mb-0 text-dark"><i class="fas fa-child text-info mr-2"></i>My Sponsored Children</h4>
            </div>
            <div class="card-body">
              <?php if (count($my_sponsored_orphans) > 0): ?>
                <div class="row">
                  <?php foreach ($my_sponsored_orphans as $orphan): ?>
                    <div class="col-md-6 mb-3">
                      <div class="border rounded p-3 bg-light d-flex align-items-center">
                        <div class="mr-3">
                          <?php if (!empty($orphan['photo']) && file_exists(__DIR__ . '/../assets/images/' . $orphan['photo'])): ?>
                            <img src="/orphan_management/assets/images/<?php echo escape($orphan['photo']); ?>" class="img-circle elevation-2" alt="Photo" style="width: 54px; height: 54px; object-fit: cover;">
                          <?php else: ?>
                            <div class="bg-indigo text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 54px; height: 54px; font-size: 1.25rem; font-weight: bold;">
                              <?php echo strtoupper(substr($orphan['full_name'], 0, 1)); ?>
                            </div>
                          <?php endif; ?>
                        </div>
                        <div style="min-width: 0; flex-grow: 1;">
                          <h6 class="font-weight-bold mb-1 text-truncate"><?php echo escape($orphan['full_name']); ?></h6>
                          <div class="text-xs text-muted mb-1">Monthly: <strong>$<?php echo number_format($orphan['sponsorship_amount'], 2); ?></strong></div>
                          <div class="text-xs text-muted mb-2">Since: <?php echo date('M d, Y', strtotime($orphan['start_date'])); ?></div>
                          <a href="/orphan_management/modules/orphans/view.php?id=<?php echo $orphan['orphan_id']; ?>" class="btn btn-xs btn-outline-primary btn-block" style="border-radius: 4px;">
                            <i class="fas fa-eye mr-1"></i> View Profile
                          </a>
                        </div>
                      </div>
                    </div>
                  <?php endforeach; ?>
                </div>
              <?php else: ?>
                <div class="text-center py-5">
                  <i class="fas fa-heartbeat text-muted mb-3" style="font-size: 3rem;"></i>
                  <p class="text-muted mb-0">You are not sponsoring any children currently.</p>
                </div>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <!-- Recent Donations Ledger -->
        <div class="col-lg-5 mb-4">
          <div class="card border-0 h-100" style="border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.02);">
            <div class="card-header bg-white border-0 py-3 d-flex align-items-center justify-content-between">
              <h4 class="card-title font-weight-bold mb-0 text-dark"><i class="fas fa-history text-success mr-2"></i>My Recent Donations</h4>
              <a href="/orphan_management/modules/donations/index.php" class="text-xs text-primary font-weight-bold">View Ledger</a>
            </div>
            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table table-striped mb-0 text-sm">
                  <thead>
                    <tr>
                      <th class="border-top-0">Amount</th>
                      <th class="border-top-0">Method</th>
                      <th class="border-top-0">Date</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if (count($my_recent_donations) > 0): ?>
                      <?php foreach ($my_recent_donations as $donation): ?>
                        <tr>
                          <td class="font-weight-bold text-success">$<?php echo number_format($donation['amount'], 2); ?></td>
                          <td><?php echo escape($donation['payment_method']); ?></td>
                          <td class="text-muted"><?php echo date('M d, Y', strtotime($donation['donation_date'])); ?></td>
                        </tr>
                      <?php endforeach; ?>
                    <?php else: ?>
                      <tr>
                        <td colspan="3" class="text-center text-muted py-5">No contributions found.</td>
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

  <!-- Web Footer -->
  <footer class="web-footer">
    <div class="container">
      <div class="row align-items-center">
        <div class="col-md-6 mb-3 mb-md-0 text-center text-md-left">
          <a href="#" class="web-footer-brand mx-auto mx-md-0">
            <img src="/orphan_management/assets/images/zamzam_logo.jpg" alt="Zamzam Logo">
            <span>Zamzam KidsCare</span>
          </a>
          <p class="text-xs text-muted mt-2 mb-0">Building a Better Future for Orphaned Children Together</p>
        </div>
        <div class="col-md-6 text-center text-md-right">
          <p class="text-sm mb-0">&copy; <?php echo date('Y'); ?> Zamzam KidsCare Foundation. All Rights Reserved.</p>
        </div>
      </div>
    </div>
  </footer>

  <!-- jQuery & Bootstrap Scripts -->
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
