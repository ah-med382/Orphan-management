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
  <title>Login | OrphanCare</title>

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
    .login-box {
      width: 400px;
      margin: 0;
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
      padding-top: 2rem !important;
    }
    .login-logo a {
      color: #1e293b !important;
      font-weight: 700;
      font-size: 2rem;
    }
    .login-logo i {
      color: #ef4444;
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
    .input-group-text {
      border-radius: 0 8px 8px 0 !important;
      border: 1px solid #cbd5e1;
      border-left: none;
      background-color: transparent;
    }
    .btn-primary {
      border-radius: 8px !important;
      padding: 12px;
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
  </style>
</head>
<body class="hold-transition login-page">
<div class="login-box">
  <!-- /.login-logo -->
  <div class="card card-outline card-primary">
    <div class="card-header text-center pb-0">
      <div class="mb-3">
        <img src="/orphan_management/assets/images/zamzam_logo.jpg" alt="Zamzam KidsCare Logo" class="img-circle elevation-2" style="width: 90px; height: 90px; object-fit: cover; border: 3px solid #cbd5e1;">
      </div>
      <div class="login-logo mb-0">
        <a href="#" class="font-weight-bold text-dark" style="font-size: 1.8rem; letter-spacing: -0.5px;"><b>Zamzam</b> KidsCare</a>
      </div>
      <p class="text-muted text-xs mt-2 text-uppercase font-weight-bold" style="letter-spacing: 1px; color: #64748b !important;">KidsCare Foundation Portal</p>
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
      
      <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
          <i class="icon fas fa-check mr-2"></i> <?php echo escape($_SESSION['success_message']); unset($_SESSION['success_message']); ?>
          <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
      <?php endif; ?>

      <form action="/orphan_management/auth/login_action.php" method="post" class="mt-3">
        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
        
        <div class="form-group mb-3">
          <label class="text-sm font-weight-medium text-secondary">Email Address</label>
          <div class="input-group">
            <input type="email" name="email" class="form-control" placeholder="Email" required autofocus>
            <div class="input-group-append">
              <div class="input-group-text">
                <span class="fas fa-envelope"></span>
              </div>
            </div>
          </div>
        </div>

        <div class="form-group mb-4">
          <label class="text-sm font-weight-medium text-secondary">Password</label>
          <div class="input-group">
            <input type="password" name="password" class="form-control" placeholder="Password" required>
            <div class="input-group-append">
              <div class="input-group-text">
                <span class="fas fa-lock"></span>
              </div>
            </div>
          </div>
        </div>

        <div class="row">
          <div class="col-12">
            <button type="submit" class="btn btn-primary btn-block">Sign In</button>
          </div>
        </div>
      </form>

      <p class="mb-0 mt-3 text-center text-sm">
        <a href="/orphan_management/forgot_password.php" class="text-muted font-weight-medium"><i class="fas fa-key mr-1"></i> Forgot Password?</a>
      </p>
      <p class="mb-0 mt-2 text-center text-sm">
        New Donor? <a href="/orphan_management/register.php" class="text-primary font-weight-semibold">Create an Account</a>
      </p>
      <div class="mt-4"></div>

    </div>
    <!-- /.card-body -->
  </div>
  <!-- /.card -->
</div>
<!-- /.login-box -->

<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<!-- Bootstrap 4 -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<!-- AdminLTE App -->
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
</body>
</html>
