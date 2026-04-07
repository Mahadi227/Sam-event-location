<?php
require 'includes/db.php';
try {
    $pdo->exec("ALTER TABLE payments MODIFY COLUMN payment_method ENUM('cash', 'orange_money', 'MyNiTa', 'AmanaTa', 'moov_money', 'card') DEFAULT 'cash'");
    echo "Enum updated\n";
} catch (Exception $e) { echo "Enum err: " . $e->getMessage() . "\n"; }
