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

/**
 * Get active branch context
 */
function getActiveBranch() {
    if (isset($_SESSION['role']) && in_array($_SESSION['role'], ['mini_admin', 'receptionist'])) {
        return $_SESSION['branch_id'];
    }
    // Super admin context switcher
    if (isset($_SESSION['active_branch']) && $_SESSION['active_branch'] !== 'all') {
        return $_SESSION['active_branch'];
    }
    return null; // Global
}

/**
 * Generates SQL condition for branch filtering implicitly
 */
function getBranchSqlFilter($tableAlias = '') {
    $branch = getActiveBranch();
    if ($branch) {
        $prefix = $tableAlias ? "$tableAlias." : "";
        return " AND {$prefix}branch_id = " . (int)$branch . " ";
    }
    return " ";
}

/**
 * Handle Branch Switching Request globally
 */
if (isset($_GET['switch_branch']) && hasRole('super_admin')) {
    $_SESSION['active_branch'] = $_GET['switch_branch'];
    $redirect = $_SERVER['PHP_SELF'];
    // Remove switch_branch from query string to avoid infinite loop
    $params = $_GET;
    unset($params['switch_branch']);
    if (!empty($params)) {
        $redirect .= '?' . http_build_query($params);
    }
    header("Location: $redirect");
    exit;
}
?>
