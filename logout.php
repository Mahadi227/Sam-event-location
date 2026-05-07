<?php
// logout.php
require_once 'includes/db.php';
session_start();
if (isset($_SESSION['user_id'])) {
    logActivity($_SESSION['user_id'], $_SESSION['branch_id'] ?? null, 'LOGOUT', 'Déconnexion réussie.');
}
session_destroy();
header("Location: login.php");
exit;
?>
