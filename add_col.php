<?php
require_once 'includes/db.php';
try {
    $pdo->exec("ALTER TABLE users ADD COLUMN profile_picture VARCHAR(255) DEFAULT NULL;");
    echo "Column added. ";
} catch (Exception $e) {
    echo "Error adding column: " . $e->getMessage() . ". ";
}
if (!file_exists('uploads/profiles')) {
    mkdir('uploads/profiles', 0777, true);
    echo "Directory created.";
} else {
    echo "Directory already exists.";
}
?>
