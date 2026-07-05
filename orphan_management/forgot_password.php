<?php
require_once __DIR__ . '/includes/auth_middleware.php';
redirectIfLoggedIn();
$csrf_token = getCsrfToken();

// Process password reset
$step = 'email'; // default step
$reset_email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/config/db.php';
    $token = $_POST['csrf_token'] ?? '';
    verifyCsrfToken($token);

    $action = $_POST['action'] ?? '';

    if ($action === 'verify_email') {
        $email = trim($_POST['email'] ?? '');
        if (empty($email)) {
            $_SESSION['error_message'] = "Please enter your email address.";
        } else {
            try {
                $stmt = $pdo->prepare("SELECT user_id, full_name FROM users WHERE email = ? AND status = 'active' LIMIT 1");
                $stmt->execute([$email]);
                $user = $stmt->fetch();

                if ($user) {
                    $step = 'reset';
                    $reset_email = $email;
                } else {
                    $_SESSION['error_message'] = "No active account found with that email address.";
                }
            } catch (PDOException $e) {
                $_SESSION['error_message'] = "System error. Please try again later.";
            }
        }
    } elseif ($action === 'reset_password') {
        $email = trim($_POST['email'] ?? '');
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if (empty($email) || empty($new_password) || empty($confirm_password)) {
            $_SESSION['error_message'] = "All fields are required.";
            $step = 'reset';
            $reset_email = $email;
        } elseif (strlen($new_password) < 6) {
            $_SESSION['error_message'] = "Password must be at least 6 characters.";
            $step = 'reset';
            $reset_email = $email;
        } elseif ($new_password !== $confirm_password) {
            $_SESSION['error_message'] = "Passwords do not match.";
            $step = 'reset';
            $reset_email = $email;
        } else {
            try {
                $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ? AND status = 'active' LIMIT 1");
                $stmt->execute([$email]);
                $user = $stmt->fetch();

                if ($user) {
                    $hashed = password_hash($new_password, PASSWORD_DEFAULT);
                    $stmtUpdate = $pdo->prepare("UPDATE users SET password = ? WHERE user_id = ?");
                    $stmtUpdate->execute([$hashed, $user['user_id']]);

                    $_SESSION['success_message'] = "Password has been reset successfully! You can now sign in with your new password.";
                    header("Location: /orphan_management/login.php");
                    exit;
                } else {
                    $_SESSION['error_message'] = "Account not found. Please try again.";
                }
            } catch (PDOException $e) {
                $_SESSION['error_message'] = "System error. Please try again later.";
                $step = 'reset';
                $reset_email = $email;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Forgot Password | Zamzam KidsCare</title>

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
      width: 420px;
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
    .step-indicator {
      display: flex;
      align-items: center;
      justify-content: center;
      margin-bottom: 1.5rem;
    }
    .step-circle {
      width: 36px;
      height: 36px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 700;
      font-size: 0.85rem;
      transition: all 0.3s ease;
    }
    .step-circle.active {
      background: #3b82f6;
      color: #fff;
      box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.2);
    }
    .step-circle.done {
      background: #22c55e;
      color: #fff;
    }
    .step-circle.inactive {
      background: #e2e8f0;
      color: #94a3b8;
    }
    .step-line {
      width: 60px;
      height: 3px;
      background: #e2e8f0;
      margin: 0 8px;
      border-radius: 2px;
      transition: background 0.3s ease;
    }
    .step-line.active {
      background: #22c55e;
    }
  </style>
</head>
<body class="hold-transition login-page">
<div class="login-box">
  <div class="card card-outline card-primary">
    <div class="card-header text-center pb-0">
      <div class="mb-3">
        <img src="/orphan_management/assets/images/zamzam_logo.jpg" alt="Zamzam KidsCare Logo" class="img-circle elevation-2" style="width: 80px; height: 80px; object-fit: cover; border: 3px solid #cbd5e1;">
      </div>
      <h4 class="font-weight-bold text-dark mb-1" style="letter-spacing: -0.5px;">Reset Password</h4>
      <p class="text-muted text-xs text-uppercase font-weight-bold" style="letter-spacing: 1px; color: #64748b !important;">Zamzam KidsCare Foundation</p>
    </div>
    <div class="card-body">

      <!-- Step Indicators -->
      <div class="step-indicator">
        <div class="step-circle <?php echo ($step === 'email') ? 'active' : 'done'; ?>">
          <?php echo ($step === 'email') ? '1' : '<i class="fas fa-check"></i>'; ?>
        </div>
        <div class="step-line <?php echo ($step === 'reset') ? 'active' : ''; ?>"></div>
        <div class="step-circle <?php echo ($step === 'reset') ? 'active' : 'inactive'; ?>">2</div>
      </div>

      <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert" style="border-radius: 8px;">
          <i class="icon fas fa-exclamation-circle mr-2"></i> <?php echo escape($_SESSION['error_message']); unset($_SESSION['error_message']); ?>
          <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
      <?php endif; ?>

      <?php if ($step === 'email'): ?>
        <!-- STEP 1: Enter Email -->
        <p class="text-sm text-muted text-center mb-3">Enter your registered email address to verify your identity.</p>
        <form action="/orphan_management/forgot_password.php" method="post">
          <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
          <input type="hidden" name="action" value="verify_email">

          <div class="form-group mb-3">
            <label class="text-sm font-weight-medium text-secondary">Email Address <span class="text-danger">*</span></label>
            <div class="input-group">
              <input type="email" name="email" class="form-control" placeholder="Enter your registered email" required autofocus>
              <div class="input-group-append">
                <div class="input-group-text">
                  <span class="fas fa-envelope"></span>
                </div>
              </div>
            </div>
          </div>

          <button type="submit" class="btn btn-primary btn-block py-2 font-weight-bold" style="border-radius: 8px;">
            <i class="fas fa-search mr-1"></i> Verify Email
          </button>
        </form>
      <?php endif; ?>

      <?php if ($step === 'reset'): ?>
        <!-- STEP 2: Set New Password -->
        <p class="text-sm text-muted text-center mb-3">Email verified! Set your new password below.</p>
        <form action="/orphan_management/forgot_password.php" method="post">
          <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
          <input type="hidden" name="action" value="reset_password">
          <input type="hidden" name="email" value="<?php echo escape($reset_email); ?>">

          <div class="form-group mb-3">
            <label class="text-sm font-weight-medium text-secondary">Verified Email</label>
            <div class="input-group">
              <input type="email" class="form-control bg-light" value="<?php echo escape($reset_email); ?>" disabled>
              <div class="input-group-append">
                <div class="input-group-text">
                  <span class="fas fa-check-circle text-success"></span>
                </div>
              </div>
            </div>
          </div>

          <div class="form-group mb-3">
            <label class="text-sm font-weight-medium text-secondary">New Password <span class="text-danger">*</span></label>
            <div class="input-group">
              <input type="password" name="new_password" class="form-control" placeholder="Min. 6 characters" required minlength="6">
              <div class="input-group-append">
                <div class="input-group-text">
                  <span class="fas fa-lock"></span>
                </div>
              </div>
            </div>
          </div>

          <div class="form-group mb-4">
            <label class="text-sm font-weight-medium text-secondary">Confirm New Password <span class="text-danger">*</span></label>
            <div class="input-group">
              <input type="password" name="confirm_password" class="form-control" placeholder="Repeat new password" required minlength="6">
              <div class="input-group-append">
                <div class="input-group-text">
                  <span class="fas fa-lock"></span>
                </div>
              </div>
            </div>
          </div>

          <button type="submit" class="btn btn-primary btn-block py-2 font-weight-bold" style="border-radius: 8px;">
            <i class="fas fa-key mr-1"></i> Reset Password
          </button>
        </form>
      <?php endif; ?>

      <p class="mb-0 mt-4 text-center text-sm">
        <a href="/orphan_management/login.php" class="text-primary font-weight-semibold"><i class="fas fa-arrow-left mr-1"></i> Back to Sign In</a>
      </p>
      <div class="mt-3"></div>

    </div>
  </div>
</div>

<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<!-- Bootstrap 4 -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<!-- AdminLTE App -->
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
</body>
</html>
