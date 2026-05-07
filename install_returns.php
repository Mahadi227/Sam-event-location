<?php
require_once 'includes/db.php';

try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS returns (
            id INT AUTO_INCREMENT PRIMARY KEY,
            reservation_id INT NOT NULL,
            returned_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            checked_by INT NOT NULL,
            status ENUM('partial', 'complete') NOT NULL DEFAULT 'complete',
            notes TEXT,
            penalty_total DECIMAL(10,2) DEFAULT 0,
            FOREIGN KEY (reservation_id) REFERENCES reservations(id) ON DELETE CASCADE,
            FOREIGN KEY (checked_by) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    echo "Table 'returns' created or already exists.\n";
    
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS return_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            return_id INT NOT NULL,
            item_id INT NOT NULL,
            qty_expected INT NOT NULL,
            qty_returned INT NOT NULL DEFAULT 0,
            qty_damaged INT NOT NULL DEFAULT 0,
            qty_missing INT NOT NULL DEFAULT 0,
            penalty_amount DECIMAL(10,2) DEFAULT 0,
            FOREIGN KEY (return_id) REFERENCES returns(id) ON DELETE CASCADE,
            FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    echo "Table 'return_items' created or already exists.\n";
    
    // Add returned to reservations status enum if not already there
    // Actually, usually status is a VARCHAR or ENUM. Let's check status column in reservations.
    // If it's ENUM, we need to alter it. It's safer to just alter the ENUM to include 'returned'.
    try {
        $pdo->exec("ALTER TABLE reservations MODIFY COLUMN status ENUM('pending', 'approved', 'in_preparation', 'completed', 'cancelled', 'rejected', 'returned') NOT NULL DEFAULT 'pending'");
        echo "Updated reservations status ENUM to include 'returned'.\n";
    } catch (Exception $e) {
        echo "Error modifying enum: " . $e->getMessage() . "\n";
    }
    
    echo "Database setup complete!\n";
    
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}
?>
