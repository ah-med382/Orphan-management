<?php
require_once __DIR__ . '/includes/auth_middleware.php';
redirectIfLoggedIn();
$csrf_token = getCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Donor Registration | OrphanCare</title>

  <!-- Google Font: Inter -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  
  <!-- Font Awesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
  <!-- Theme style (AdminLTE) -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
  
  <style>
    body {
      font-family: 'Inter', sans-serif;
      background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 100%) !important;
      display: flex;
      align-items: center;
      justify-content: center;
      min-height: 100vh;
      margin: 0;
    }
    .register-box {
      width: 450px;
      margin: 2rem 0;
    }
    .card {
      border: none !important;
      border-radius: 16px !important;
      box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.3), 0 10px 10px -5px rgba(0, 0, 0, 0.2) !important;
      background-color: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(10px);
    }
    .card-header {
      border-bottom: none !important;
      padding-top: 1.5rem !important;
    }
    .register-logo a {
      color: #1e293b !important;
      font-weight: 700;
      font-size: 1.8rem;
    }
    .register-logo i {
      color: #ef4444;
    }
    .form-control {
      border-radius: 8px !important;
      padding: 10px 14px;
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
      padding: 10px;
      font-weight: 600;
      background-color: #3b82f6 !important;
      border-color: #3b82f6 !important;
      box-shadow: 0 4px 14px 0 rgba(59, 130, 246, 0.4) !important;
      transition: all 0.2s ease;
    }
    .btn-primary:hover {
      background-color: #2563eb !important;
      border-color: #2563eb !important;
    }
  </style>
</head>
<body class="hold-transition register-page">
<div class="register-box">
  <div class="card card-outline card-primary">
    <div class="card-header text-center pb-0">
      <div class="mb-2">
        <img src="/orphan_management/assets/images/zamzam_logo.jpg" alt="Zamzam KidsCare Logo" class="img-circle elevation-2" style="width: 80px; height: 80px; object-fit: cover; border: 3px solid #cbd5e1;">
      </div>
      <div class="register-logo mb-0">
        <a href="#" class="font-weight-bold text-dark" style="font-size: 1.6rem; letter-spacing: -0.5px;"><b>Zamzam</b> KidsCare</a>
      </div>
      <p class="text-muted text-xs mt-1 text-uppercase font-weight-bold" style="letter-spacing: 0.5px; color: #64748b !important;">Donor Registration Portal</p>
    </div>
    <div class="card-body">
      
      <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
          <i class="icon fas fa-ban mr-2"></i> <?php echo escape($_SESSION['error_message']); unset($_SESSION['error_message']); ?>
          <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
      <?php endif; ?>

      <form action="/orphan_management/auth/register_action.php" method="post" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
        
        <div class="form-group mb-2">
          <label class="text-xs font-weight-medium text-secondary">Full Name <span class="text-danger">*</span></label>
          <input type="text" name="full_name" class="form-control" placeholder="John Doe" required>
        </div>

        <div class="form-group mb-2">
          <label class="text-xs font-weight-medium text-secondary">Email Address <span class="text-danger">*</span></label>
          <input type="email" name="email" class="form-control" placeholder="john.doe@example.com" required>
        </div>

        <div class="form-group mb-2">
          <label class="text-xs font-weight-medium text-secondary">Phone Number <span class="text-danger">*</span></label>
          <input type="text" name="phone" class="form-control" placeholder="+15550199" required>
        </div>

        <div class="form-group mb-2">
          <label class="text-xs font-weight-medium text-secondary">Physical Address <span class="text-danger">*</span></label>
          <textarea name="address" class="form-control" rows="2" placeholder="123 Main St, Springfield" required></textarea>
        </div>

        <div class="form-group mb-2">
          <label class="text-xs font-weight-medium text-secondary">Password <span class="text-danger">*</span></label>
          <input type="password" name="password" class="form-control" placeholder="Min. 6 characters" required>
        </div>

        <div class="form-group mb-2">
          <label class="text-xs font-weight-medium text-secondary">Profile Image <span class="text-danger">*</span></label>
          <div class="custom-file">
            <input type="file" name="profile_image" class="custom-file-input" id="profileImage" accept="image/*" required>
            <label class="custom-file-label text-xs" for="profileImage">Choose image...</label>
          </div>
        </div>



        <div class="row">
          <div class="col-12">
            <button type="submit" class="btn btn-primary btn-block">Register Account</button>
          </div>
        </div>
      </form>

      <p class="mb-0 mt-3 text-center text-sm">
        Already have an account? <a href="/orphan_management/login.php" class="text-primary font-weight-semibold">Sign In</a>
      </p>
    </div>
    <!-- /.form-box -->
  </div><!-- /.card -->
</div>
<!-- /.register-box -->

<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<!-- Bootstrap 4 -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<!-- AdminLTE App -->
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
<script>
  $(document).ready(function() {
    $('.custom-file-input').on('change', function() {
      let fileName = $(this).val().split('\\').pop();
      $(this).next('.custom-file-label').addClass("selected").html(fileName);
    });
  });
</script>
</body>
</html>
