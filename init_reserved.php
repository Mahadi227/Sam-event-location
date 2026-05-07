<?php
require_once 'includes/db.php';

try {
    $pdo->beginTransaction();

    // Reset reserved to 0 first
    $pdo->exec("UPDATE items SET quantity_reserved = 0");

    // Calculate total reserved quantities from active reservations
    // Active reservations are: 'pending', 'approved', 'in_preparation'
    $stmt = $pdo->query("
        SELECT ri.item_id, SUM(ri.quantity) as total_reserved
        FROM reservation_items ri
        JOIN reservations r ON ri.reservation_id = r.id
        WHERE r.status IN ('pending', 'approved', 'in_preparation')
        GROUP BY ri.item_id
    ");
    $reserved_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $updateStmt = $pdo->prepare("UPDATE items SET quantity_reserved = ? WHERE id = ?");

    foreach ($reserved_data as $row) {
        $updateStmt->execute([$row['total_reserved'], $row['item_id']]);
        echo "Item ID " . $row['item_id'] . " reserved updated to " . $row['total_reserved'] . "\n";
    }

    $pdo->commit();
    echo "Successfully retroactively calculated quantity_reserved.\n";

} catch (Exception $e) {
    $pdo->rollBack();
    echo "Error: " . $e->getMessage() . "\n";
}
