<?php
require 'includes/db.php';

try {
    // 1. Create Branches Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS branches (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        location VARCHAR(255),
        phone VARCHAR(50),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    // Check if Main Branch exists
    $stmt = $pdo->query("SELECT id FROM branches LIMIT 1");
    if (!$stmt->fetch()) {
        $pdo->exec("INSERT INTO branches (name, location, phone) VALUES ('Succursale Principale', 'Siège Social', '')");
    }

    $main_branch_id = 1;

    // 2. Add branch_id to tables safely
    $tables = [
        'users' => 'ADD COLUMN branch_id INT NULL DEFAULT NULL AFTER role',
        'items' => 'ADD COLUMN branch_id INT NULL DEFAULT NULL AFTER category_id',
        'reservations' => 'ADD COLUMN branch_id INT NULL DEFAULT NULL AFTER user_id'
    ];

    foreach ($tables as $table => $alterCmd) {
        try {
            $pdo->exec("ALTER TABLE $table $alterCmd");
            echo "Added branch_id to $table.\n";
        } catch(PDOException $e) {
            // Error 1060: Duplicate column name
            if ($e->getCode() == '42S21' || strpos($e->getMessage(), '1060') !== false) {
                echo "Column branch_id already exists in $table.\n";
            } else {
                throw $e;
            }
        }
    }

    // 3. Assign Default Branch (Main Branch Data Fallback)
    $pdo->exec("UPDATE users SET branch_id = $main_branch_id WHERE branch_id IS NULL AND role != 'super_admin' AND role != 'client'");
    $pdo->exec("UPDATE items SET branch_id = $main_branch_id WHERE branch_id IS NULL");
    $pdo->exec("UPDATE reservations SET branch_id = $main_branch_id WHERE branch_id IS NULL");

    // Optional: add foreign keys
    try {
        $pdo->exec("ALTER TABLE users Add CONSTRAINT fk_user_branch FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE SET NULL");
        $pdo->exec("ALTER TABLE items Add CONSTRAINT fk_item_branch FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE CASCADE");
        $pdo->exec("ALTER TABLE reservations Add CONSTRAINT fk_res_branch FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE CASCADE");
    } catch(Exception $e) {
        // Ignore if FK already exists
    }

    echo "Migration completed successfully!";
} catch(Exception $e) {
    die("Migration failed: " . $e->getMessage());
}
