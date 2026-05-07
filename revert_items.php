<?php
require_once 'includes/db.php';

try {
    $pdo->exec("ALTER TABLE items DROP COLUMN quantity_available");
    echo "Dropped quantity_available.\n";
} catch (PDOException $e) {
    echo "quantity_available might not exist: " . $e->getMessage() . "\n";
}

try {
    $pdo->exec("ALTER TABLE items DROP COLUMN quantity_reserved");
    echo "Dropped quantity_reserved.\n";
} catch (PDOException $e) {
    echo "quantity_reserved might not exist: " . $e->getMessage() . "\n";
}

echo "Done.\n";
