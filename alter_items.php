<?php
require_once 'includes/db.php';

try {
    $pdo->exec("ALTER TABLE items ADD COLUMN quantity_reserved INT DEFAULT 0 AFTER quantity_total");
    echo "Added quantity_reserved.\n";
} catch (PDOException $e) {
    echo "quantity_reserved might already exist: " . $e->getMessage() . "\n";
}

try {
    $pdo->exec("ALTER TABLE items ADD COLUMN quantity_maintenance INT DEFAULT 0 AFTER quantity_reserved");
    echo "Added quantity_maintenance.\n";
} catch (PDOException $e) {
    echo "quantity_maintenance might already exist: " . $e->getMessage() . "\n";
}

try {
    $pdo->exec("ALTER TABLE items ADD COLUMN quantity_available INT GENERATED ALWAYS AS (quantity_total - quantity_reserved - quantity_maintenance) STORED AFTER quantity_maintenance");
    echo "Added quantity_available.\n";
} catch (PDOException $e) {
    echo "quantity_available might already exist: " . $e->getMessage() . "\n";
}

echo "Done.\n";
