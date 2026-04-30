<?php
require_once 'includes/db.php';
try {
    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'profile_picture'");
    if ($stmt->rowCount() > 0) {
        echo "Column exists";
    } else {
        echo "Column does NOT exist";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
