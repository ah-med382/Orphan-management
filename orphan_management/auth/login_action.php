<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_middleware.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF verification
    $token = $_POST['csrf_token'] ?? '';
    verifyCsrfToken($token);

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $_SESSION['error_message'] = "Please fill in all fields.";
        header("Location: /orphan_management/login.php");
        exit;
    }

    try {
        // Query user details
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            if ($user['status'] !== 'active') {
                $_SESSION['error_message'] = "Your account is currently inactive. Please contact the administrator.";
                header("Location: /orphan_management/login.php");
                exit;
            }

            // Set session variables
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['email'] = $user['email'];

            // Link donor profile if user is a donor
            if ($user['role'] === 'donor') {
                $stmtDonor = $pdo->prepare("SELECT donor_id FROM donors WHERE user_id = ? LIMIT 1");
                $stmtDonor->execute([$user['user_id']]);
                $donor = $stmtDonor->fetch();
                if ($donor) {
                    $_SESSION['donor_id'] = $donor['donor_id'];
                }
            }

            // Link staff profile if user is staff
            if ($user['role'] === 'staff') {
                $stmtStaff = $pdo->prepare("SELECT staff_id FROM staff WHERE user_id = ? LIMIT 1");
                $stmtStaff->execute([$user['user_id']]);
                $staff = $stmtStaff->fetch();
                if ($staff) {
                    $_SESSION['staff_id'] = $staff['staff_id'];
                }
            }

            $_SESSION['success_message'] = "Welcome back, " . $user['full_name'] . "!";
            header("Location: /orphan_management/index.php");
            exit;
        } else {
            $_SESSION['error_message'] = "Invalid email or password.";
            header("Location: /orphan_management/login.php");
            exit;
        }
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "An error occurred. Please try again. " . $e->getMessage();
        header("Location: /orphan_management/login.php");
        exit;
    }
} else {
    header("Location: /orphan_management/login.php");
    exit;
}
?>
