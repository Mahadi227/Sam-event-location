<?php
// api/calculate_price.php
require_once '../includes/db.php';
require_once '../includes/engine.php';
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['error' => 'Données invalides']);
    exit;
}

$items = $input['items'] ?? [];
$params = [
    'duration_days' => $input['duration'] ?? 1,
    'distance_km' => $input['distance'] ?? 0,
    'is_weekend' => $input['is_weekend'] ?? false,
    'promo_code' => $input['promo'] ?? null
];

$result = calculateTotalPrice($items, $params);
echo json_encode($result);
?>
