<?php
// includes/auth.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Checks if a user is logged in
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

/**
 * Checks if the current user has a specific role
 */
function hasRole($role) {
    return isset($_SESSION['role']) && $_SESSION['role'] === $role;
}

/**
 * Middleware for Super Admin pages
 */
function requireSuperAdmin() {
    if (!hasRole('super_admin')) {
        header("Location: ../login.php");
        exit;
    }
}

/**
 * Middleware for Mini Admin or Super Admin
 */
function requireAdmin() {
    if (!hasRole('super_admin') && !hasRole('mini_admin')) {
        header("Location: ../login.php");
        exit;
    }
}

/**
 * Middleware for Receptionist or Admins
 */
function requireStaff() {
    if (!hasRole('super_admin') && !hasRole('mini_admin') && !hasRole('receptionist')) {
        header("Location: ../login.php");
        exit;
    }
}

/**
 * Middleware for Client pages
 */
function requireClient() {
    if (!isLoggedIn()) {
        header("Location: login.php");
        exit;
    }
}
?>
