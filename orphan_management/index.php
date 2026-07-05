<?php
require_once __DIR__ . '/includes/auth_middleware.php';

// Force login
checkLogin();

// Redirect based on role
if ($_SESSION['role'] === 'admin') {
    header("Location: /orphan_management/admin/index.php");
    exit;
} elseif ($_SESSION['role'] === 'staff') {
    header("Location: /orphan_management/staff/index.php");
    exit;
} elseif ($_SESSION['role'] === 'donor') {
    header("Location: /orphan_management/donor/index.php");
    exit;
}

// Fallback to login
header("Location: /orphan_management/login.php");
exit;
?>
