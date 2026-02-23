<?php
// api/check_availability.php
require_once '../includes/db.php';
require_once '../includes/engine.php';
header('Content-Type: application/json');

$item_id = $_GET['item_id'] ?? null;
$date = $_GET['date'] ?? null;

if (!$item_id || !$date) {
    echo json_encode(['error' => 'Paramètres manquants']);
    exit;
}

$available = getAvailableStock($item_id, $date);
echo json_encode(['available' => $available]);
?>
