<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Check if the user is logged in. Redirect to login page if not.
 */
function checkLogin() {
    if (!isset($_SESSION['user_id'])) {
        header("Location: /orphan_management/login.php");
        exit;
    }
}

/**
 * Check if the logged-in user has one of the allowed roles.
 * @param array $allowed_roles
 */
function checkRole($allowed_roles = []) {
    checkLogin();
    if (!in_array($_SESSION['role'], $allowed_roles)) {
        $_SESSION['error_message'] = "Access denied. You do not have permission to view that page.";
        header("Location: /orphan_management/" . $_SESSION['role'] . "/index.php");
        exit;
    }
}

/**
 * Redirect logged in users to their dashboard.
 */
function redirectIfLoggedIn() {
    if (isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
        header("Location: /orphan_management/" . $_SESSION['role'] . "/index.php");
        exit;
    }
}

/**
 * Escapes HTML to protect against XSS.
 */
function escape($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Generates a CSRF token if one does not exist, and returns it.
 */
function getCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verifies a POST CSRF token.
 */
function verifyCsrfToken($token) {
    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        die("CSRF token verification failed.");
    }
}
?>
