<?php
require_once __DIR__ . '/auth_middleware.php';
checkLogin();

// Set user name and role for display
$current_user_name = $_SESSION['full_name'] ?? 'User';
$current_user_role = $_SESSION['role'] ?? 'staff';
$role_badge_class = 'badge-secondary';
if ($current_user_role === 'admin') {
    $role_badge_class = 'badge-danger';
} elseif ($current_user_role === 'staff') {
    $role_badge_class = 'badge-info';
} elseif ($current_user_role === 'donor') {
    $role_badge_class = 'badge-success';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Online Orphan Management System</title>

  <!-- Google Font: Inter & Source Sans Pro -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  
  <!-- Font Awesome Icons -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
  <!-- Theme style (AdminLTE) -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
  <!-- Custom Project CSS -->
  <link rel="stylesheet" href="/orphan_management/assets/css/custom.css">
  
  <!-- Premium Custom Styling Overrides -->
  <style>
    body {
      font-family: 'Inter', sans-serif;
      background-color: #f4f6f9;
    }
    .main-sidebar {
      background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%) !important;
      box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.3);
    }
    .brand-link {
      border-bottom: 1px solid rgba(255, 255, 255, 0.1) !important;
    }
    .nav-sidebar .nav-item > .nav-link {
      border-radius: 8px;
      margin: 2px 8px;
      padding: 10px 14px;
      transition: all 0.2s ease-in-out;
    }
    .nav-sidebar .nav-item > .nav-link.active {
      background-color: #3b82f6 !important;
      box-shadow: 0 4px 14px 0 rgba(59, 130, 246, 0.4);
    }
    .nav-sidebar .nav-item > .nav-link:hover:not(.active) {
      background-color: rgba(255, 255, 255, 0.08) !important;
      color: #fff !important;
    }
    .card {
      border: none !important;
      border-radius: 12px !important;
      box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03) !important;
      transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .card:hover {
      box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.05), 0 4px 6px -2px rgba(0, 0, 0, 0.03) !important;
    }
    .small-box {
      border-radius: 12px !important;
      overflow: hidden;
      box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05) !important;
      border: none !important;
    }
    .btn {
      border-radius: 8px !important;
      font-weight: 500;
      padding: 8px 16px;
    }
    .table th {
      border-top: none !important;
      text-transform: uppercase;
      font-size: 0.75rem;
      font-weight: 600;
      letter-spacing: 0.05em;
      color: #4b5563;
      background-color: #f9fafb;
    }
    .table td {
      vertical-align: middle !important;
    }
    .user-panel {
      border-bottom: 1px solid rgba(255, 255, 255, 0.1) !important;
    }
    .role-badge {
      font-size: 0.75rem;
      padding: 4px 8px;
      border-radius: 9999px;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.05em;
    }
    /* Smooth Scrollbar */
    ::-webkit-scrollbar {
      width: 8px;
      height: 8px;
    }
    ::-webkit-scrollbar-track {
      background: #f1f5f9;
    }
    ::-webkit-scrollbar-thumb {
      background: #cbd5e1;
      border-radius: 4px;
    }
    ::-webkit-scrollbar-thumb:hover {
      background: #94a3b8;
    }
  </style>
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">

  <!-- Navbar -->
  <nav class="main-header navbar navbar-expand navbar-white navbar-light">
    <!-- Left navbar links -->
    <ul class="navbar-nav">
      <li class="nav-item">
        <a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a>
      </li>
    </ul>

    <!-- Right navbar links -->
    <ul class="navbar-nav ml-auto">
      <!-- User Info Dropdown -->
      <li class="nav-item d-flex align-items-center mr-3">
        <span class="mr-2 text-muted">Signed in as:</span>
        <span class="badge <?php echo $role_badge_class; ?> role-badge mr-2"><?php echo escape(strtoupper($current_user_role)); ?></span>
        <strong class="text-dark mr-3"><?php echo escape($current_user_name); ?></strong>
      </li>
      <li class="nav-item">
        <a class="btn btn-outline-danger btn-sm" href="/orphan_management/logout.php">
          <i class="fas fa-sign-out-alt mr-1"></i> Logout
        </a>
      </li>
    </ul>
  </nav>
  <!-- /.navbar -->
