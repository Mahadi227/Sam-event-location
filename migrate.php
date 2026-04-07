<?php
require 'includes/db.php';
try {
    $pdo->exec("ALTER TABLE payments ADD COLUMN processed_by INT DEFAULT NULL");
    echo "Column added\n";
} catch (Exception $e) { echo "Col err: " . $e->getMessage() . "\n"; }

try {
    $pdo->exec("ALTER TABLE payments ADD FOREIGN KEY (processed_by) REFERENCES users(id)");
    echo "FK added\n";
} catch (Exception $e) { echo "FK err: " . $e->getMessage() . "\n"; }
