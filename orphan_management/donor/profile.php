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

// If donor record doesn't exist, redirect
if (!$donor_id) {
    die("Donor profile not found.");
}

// Fetch donor dynamic details
$stmtDonorInfo = $pdo->prepare("SELECT d.*, u.email FROM donors d JOIN users u ON d.user_id = u.user_id WHERE d.donor_id = ? LIMIT 1");
$stmtDonorInfo->execute([$donor_id]);
$donor_info = $stmtDonorInfo->fetch();

if (!$donor_info) {
    die("Donor details not found.");
}

$donor_display_name = $donor_info['full_name'];
$donor_profile_image = $donor_info['profile_image'];

$csrf_token = getCsrfToken();

// Form processing
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken($_POST['csrf_token'] ?? '');

    $full_name = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($full_name) || empty($phone) || empty($address)) {
        $_SESSION['error_message'] = "Full Name, Phone Number, and Physical Address are required.";
    } else {
        try {
            $pdo->beginTransaction();

            // Handle Profile Image Upload if provided
            $profile_image_filename = $donor_profile_image;
            if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
                $file_tmp = $_FILES['profile_image']['tmp_name'];
                $file_name = $_FILES['profile_image']['name'];
                $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                $allowed_exts = ['jpg', 'jpeg', 'png', 'gif'];

                if (in_array($file_ext, $allowed_exts)) {
                    $upload_dir = __DIR__ . '/../assets/images/';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0777, true);
                    }
                    $profile_image_filename = uniqid('donor_', true) . '.' . $file_ext;
                    if (!move_uploaded_file($file_tmp, $upload_dir . $profile_image_filename)) {
                        $profile_image_filename = $donor_profile_image;
                    }
                }
            }

            // Update donors table
            $stmtUpdateDonor = $pdo->prepare("
                UPDATE donors 
                SET full_name = ?, phone = ?, address = ?, profile_image = ? 
                WHERE donor_id = ?
            ");
            $stmtUpdateDonor->execute([$full_name, $phone, $address, $profile_image_filename, $donor_id]);

            // Update users table
            if (!empty($password)) {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmtUpdateUser = $pdo->prepare("UPDATE users SET full_name = ?, password = ? WHERE user_id = ?");
                $stmtUpdateUser->execute([$full_name, $hashed_password, $donor_info['user_id']]);
            } else {
                $stmtUpdateUser = $pdo->prepare("UPDATE users SET full_name = ? WHERE user_id = ?");
                $stmtUpdateUser->execute([$full_name, $donor_info['user_id']]);
            }

            // Update session values
            $_SESSION['full_name'] = $full_name;

            $pdo->commit();
            $_SESSION['success_message'] = "Your profile has been updated successfully!";
            header("Location: /orphan_management/donor/profile.php");
            exit;
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $_SESSION['error_message'] = "Error updating profile: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>My Profile | Zamzam KidsCare</title>

  <!-- Google Font: Inter -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  
  <!-- Font Awesome Icons -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
  <!-- Bootstrap base / AdminLTE theme -->
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
    
    /* Profile Badge Link */
    .profile-link-nav {
      display: flex;
      align-items: center;
      padding: 0.35rem 0.75rem;
      border-radius: 9999px;
      margin-right: 1rem;
      border: 1px solid #e2e8f0;
      background-color: #f1f5f9;
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
    
    .profile-container {
      max-width: 800px;
      margin: 3.5rem auto;
      padding: 0 1rem;
    }
    .profile-card {
      border: none;
      border-radius: 16px;
      box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.05), 0 4px 6px -2px rgba(0, 0, 0, 0.025);
      background-color: white;
      overflow: hidden;
    }
    .profile-card-header {
      background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 100%);
      color: white;
      padding: 2.5rem 2rem;
      text-align: center;
      position: relative;
    }
    .profile-card-avatar-wrapper {
      position: relative;
      width: 120px;
      height: 120px;
      margin: 0 auto 1rem;
    }
    .profile-card-avatar {
      width: 120px;
      height: 120px;
      border-radius: 50%;
      object-fit: cover;
      border: 4px solid rgba(255, 255, 255, 0.2);
    }
    .profile-card-avatar-fallback {
      width: 120px;
      height: 120px;
      border-radius: 50%;
      background-color: #3b82f6;
      color: white;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: bold;
      font-size: 3rem;
      border: 4px solid rgba(255, 255, 255, 0.2);
    }
    .profile-card-body {
      padding: 2.5rem 2rem;
    }
    .form-control {
      border-radius: 8px !important;
      padding: 12px 16px;
      height: auto;
      border: 1px solid #cbd5e1;
      transition: all 0.2s ease;
    }
    .form-control:focus {
      border-color: #3b82f6;
      box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
    }
    .btn-primary {
      border-radius: 8px !important;
      padding: 12px 24px;
      font-weight: 600;
      background-color: #3b82f6 !important;
      border-color: #3b82f6 !important;
      box-shadow: 0 4px 14px 0 rgba(59, 130, 246, 0.4) !important;
      transition: all 0.2s ease;
    }
    .btn-primary:hover {
      background-color: #2563eb !important;
      border-color: #2563eb !important;
      box-shadow: 0 6px 20px 0 rgba(37, 99, 235, 0.4) !important;
    }
    
    /* Footer Styling */
    .web-footer {
      background-color: #0f172a;
      color: #94a3b8;
      padding: 3rem 1.5rem;
      border-top: 1px solid #1e293b;
      margin-top: 5rem;
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
          <li><a href="/orphan_management/donor/index.php" class="web-nav-link">Dashboard</a></li>
          <li><a href="/orphan_management/modules/donations/create.php" class="web-nav-link">Make a Donation</a></li>
          <li><a href="/orphan_management/modules/sponsorships/create.php" class="web-nav-link">Sponsor an Orphan</a></li>
          <li><a href="/orphan_management/modules/adoptions/index.php" class="web-nav-link">Apply for Adoption</a></li>
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

  <!-- Profile Content Container -->
  <div class="profile-container">
    <?php if (isset($_SESSION['success_message'])): ?>
      <div class="alert alert-success alert-dismissible fade show mb-4 border-0" role="alert" style="border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.02);">
        <i class="icon fas fa-check-circle mr-2"></i> <?php echo escape($_SESSION['success_message']); unset($_SESSION['success_message']); ?>
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error_message'])): ?>
      <div class="alert alert-danger alert-dismissible fade show mb-4 border-0" role="alert" style="border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.02);">
        <i class="icon fas fa-ban mr-2"></i> <?php echo escape($_SESSION['error_message']); unset($_SESSION['error_message']); ?>
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
    <?php endif; ?>

    <div class="profile-card">
      <div class="profile-card-header">
        <div class="profile-card-avatar-wrapper">
          <?php if (!empty($donor_profile_image) && file_exists(__DIR__ . '/../assets/images/' . $donor_profile_image)): ?>
            <img src="/orphan_management/assets/images/<?php echo escape($donor_profile_image); ?>" class="profile-card-avatar" alt="Profile Image">
          <?php else: ?>
            <div class="profile-card-avatar-fallback"><?php echo strtoupper(substr($donor_display_name, 0, 1)); ?></div>
          <?php endif; ?>
        </div>
        <h3 class="font-weight-bold mb-0 text-white"><?php echo escape($donor_display_name); ?></h3>
        <p class="text-xs text-muted mb-0 mt-1" style="color: #94a3b8 !important; letter-spacing: 0.5px;">Registered Donor Account</p>
      </div>
      
      <div class="profile-card-body">
        <form action="/orphan_management/donor/profile.php" method="POST" enctype="multipart/form-data">
          <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
          
          <div class="row">
            <div class="col-md-6 form-group">
              <label class="font-weight-medium text-secondary">Full Name <span class="text-danger">*</span></label>
              <input type="text" name="full_name" class="form-control" value="<?php echo escape($donor_info['full_name']); ?>" required>
            </div>
            
            <div class="col-md-6 form-group">
              <label class="font-weight-medium text-secondary">Email Address</label>
              <input type="email" class="form-control bg-light" value="<?php echo escape($donor_info['email']); ?>" disabled>
              <span class="text-xs text-muted mt-1 d-block">Email address cannot be changed. Contact admin for assistance.</span>
            </div>
          </div>
          
          <div class="row mt-2">
            <div class="col-md-6 form-group">
              <label class="font-weight-medium text-secondary">Phone Number <span class="text-danger">*</span></label>
              <input type="text" name="phone" class="form-control" value="<?php echo escape($donor_info['phone']); ?>" required>
            </div>
            
            <div class="col-md-6 form-group">
              <label class="font-weight-medium text-secondary">Profile Image</label>
              <div class="custom-file">
                <input type="file" name="profile_image" class="custom-file-input" id="profileImageInput" accept="image/*">
                <label class="custom-file-label" for="profileImageInput">Choose new image...</label>
              </div>
            </div>
          </div>
          
          <div class="form-group mt-2">
            <label class="font-weight-medium text-secondary">Physical Address <span class="text-danger">*</span></label>
            <textarea name="address" class="form-control" rows="3" required><?php echo escape($donor_info['address']); ?></textarea>
          </div>
          
          <div class="form-group mt-2">
            <label class="font-weight-medium text-secondary">Change Password</label>
            <input type="password" name="password" class="form-control" placeholder="Leave blank to keep your current password">
            <span class="text-xs text-muted mt-1 d-block">Must be at least 6 characters.</span>
          </div>

          <div class="text-right mt-4">
            <button type="submit" class="btn btn-primary px-4 py-2 font-weight-bold">
              <i class="fas fa-save mr-1"></i> Save Profile Changes
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

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
  <script>
    $(document).ready(function () {
      // Show file name
      $('#profileImageInput').on('change', function() {
        let fileName = $(this).val().split('\\').pop();
        $(this).next('.custom-file-label').addClass("selected").html(fileName);
      });
    });
  </script>
</body>
</html>
