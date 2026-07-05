<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_middleware.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF verification
    $token = $_POST['csrf_token'] ?? '';
    verifyCsrfToken($token);

    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($full_name) || empty($email) || empty($phone) || empty($address) || empty($password)) {
        $_SESSION['error_message'] = "All fields are required.";
        header("Location: /orphan_management/register.php");
        exit;
    }

    if (strlen($password) < 6) {
        $_SESSION['error_message'] = "Password must be at least 6 characters long.";
        header("Location: /orphan_management/register.php");
        exit;
    }

    try {
        // Handle Profile Image Upload
        $profile_image_filename = null;
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
                    $profile_image_filename = null;
                }
            }
        }

        // Start Transaction
        $pdo->beginTransaction();

        // Check if email already exists
        $stmtCheck = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
        $stmtCheck->execute([$email]);
        if ($stmtCheck->fetch()) {
            $_SESSION['error_message'] = "Email address is already registered.";
            $pdo->rollBack();
            header("Location: /orphan_management/register.php");
            exit;
        }

        // Insert into Users table
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $stmtUser = $pdo->prepare("INSERT INTO users (full_name, email, password, role, status) VALUES (?, ?, ?, 'donor', 'active')");
        $stmtUser->execute([$full_name, $email, $hashed_password]);
        $user_id = $pdo->lastInsertId();

        // Insert into Donors table
        $stmtDonor = $pdo->prepare("INSERT INTO donors (user_id, full_name, phone, email, address, profile_image) VALUES (?, ?, ?, ?, ?, ?)");
        $stmtDonor->execute([$user_id, $full_name, $phone, $email, $address, $profile_image_filename]);
        $donor_id = $pdo->lastInsertId();

        // Process Optional Sponsorship 1
        $orphan_id_1 = isset($_POST['orphan_id_1']) ? (int)$_POST['orphan_id_1'] : 0;
        if ($orphan_id_1 > 0) {
            // Check if active
            $stmtO1 = $pdo->prepare("SELECT full_name, status FROM orphans WHERE orphan_id = ?");
            $stmtO1->execute([$orphan_id_1]);
            $orp1 = $stmtO1->fetch();

            if ($orp1 && $orp1['status'] === 'Active') {
                // Insert active sponsorship
                $stmtS1 = $pdo->prepare("INSERT INTO sponsorships (donor_id, orphan_id, sponsorship_amount, start_date, status) VALUES (?, ?, 150.00, CURDATE(), 'Active')");
                $stmtS1->execute([$donor_id, $orphan_id_1]);

                // Update status
                $pdo->prepare("UPDATE orphans SET status = 'Sponsored' WHERE orphan_id = ?")->execute([$orphan_id_1]);

                // Log initial donation
                $stmtD1 = $pdo->prepare("INSERT INTO donations (donor_id, amount, payment_method, donation_date, notes, account_id) VALUES (?, 150.00, 'Sponsorship Payout', CURDATE(), ?, 2)");
                $dNotes = "Initial monthly sponsorship payment for child: " . $orp1['full_name'];
                $stmtD1->execute([$donor_id, $dNotes]);

                // Credit Sponsorship Fund
                $pdo->prepare("UPDATE finance_accounts SET balance = balance + 150.00 WHERE account_id = 2")->execute();
            }
        }

        // Process Optional Sponsorship 2
        $orphan_id_2 = isset($_POST['orphan_id_2']) ? (int)$_POST['orphan_id_2'] : 0;
        if ($orphan_id_2 > 0) {
            // Check if active
            $stmtO2 = $pdo->prepare("SELECT full_name, status FROM orphans WHERE orphan_id = ?");
            $stmtO2->execute([$orphan_id_2]);
            $orp2 = $stmtO2->fetch();

            if ($orp2 && $orp2['status'] === 'Active') {
                // Insert active sponsorship
                $stmtS2 = $pdo->prepare("INSERT INTO sponsorships (donor_id, orphan_id, sponsorship_amount, start_date, status) VALUES (?, ?, 150.00, CURDATE(), 'Active')");
                $stmtS2->execute([$donor_id, $orphan_id_2]);

                // Update status
                $pdo->prepare("UPDATE orphans SET status = 'Sponsored' WHERE orphan_id = ?")->execute([$orphan_id_2]);

                // Log initial donation
                $stmtD2 = $pdo->prepare("INSERT INTO donations (donor_id, amount, payment_method, donation_date, notes, account_id) VALUES (?, 150.00, 'Sponsorship Payout', CURDATE(), ?, 2)");
                $dNotes = "Initial monthly sponsorship payment for child: " . $orp2['full_name'];
                $stmtD2->execute([$donor_id, $dNotes]);

                // Credit Sponsorship Fund
                $pdo->prepare("UPDATE finance_accounts SET balance = balance + 150.00 WHERE account_id = 2")->execute();
            }
        }

        // Commit Transaction
        $pdo->commit();

        $_SESSION['success_message'] = "Registration successful! You can now log in.";
        header("Location: /orphan_management/login.php");
        exit;

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $_SESSION['error_message'] = "An error occurred during registration. Please try again. " . $e->getMessage();
        header("Location: /orphan_management/register.php");
        exit;
    }
} else {
    header("Location: /orphan_management/register.php");
    exit;
}
?>
