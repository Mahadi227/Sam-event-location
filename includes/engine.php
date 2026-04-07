<?php
// includes/engine.php
require_once 'db.php';

/**
 * Intelligent Pricing Engine
 */
function calculateTotalPrice($items_requested, $params) {
    global $pdo;
    
    $total_base = 0;
    $duration = (int)($params['duration_days'] ?? 1);
    $distance = (int)($params['distance_km'] ?? 0);
    $is_weekend = (bool)($params['is_weekend'] ?? false);
    $promo_code = $params['promo_code'] ?? null;
    
    // 1. Calculate items base price
    foreach ($items_requested as $item_id => $qty) {
        if ($qty <= 0) continue;
        
        $stmt = $pdo->prepare("SELECT price_per_day FROM items WHERE id = ?");
        $stmt->execute([$item_id]);
        $price = $stmt->fetchColumn();
        
        $total_base += ($price * $qty);
    }
    
    $total = $total_base * $duration;
    
    // 2. Weekend Surcharge
    if ($is_weekend) {
        $stmt = $pdo->prepare("SELECT rule_value FROM pricing_rules WHERE rule_key = 'weekend_surcharge'");
        $stmt->execute();
        $multiplier = (float)$stmt->fetchColumn() ?: 1.0;
        $total *= $multiplier;
    }
    
    // 3. Delivery Fee
    $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'delivery_fee'");
    $stmt->execute();
    $base_delivery = (float)$stmt->fetchColumn() ?: 0;
    
    $stmt = $pdo->prepare("SELECT rule_value FROM pricing_rules WHERE rule_key = 'delivery_per_km'");
    $stmt->execute();
    $per_km = (float)$stmt->fetchColumn() ?: 0;
    
    $stmt = $pdo->prepare("SELECT rule_value FROM pricing_rules WHERE rule_key = 'free_delivery_radius'");
    $stmt->execute();
    $free_radius = (int)$stmt->fetchColumn() ?: 0;
    
    $delivery_total = $base_delivery;
    if ($distance > $free_radius) {
        $delivery_total += ($distance - $free_radius) * $per_km;
    }
    $total += $delivery_total;
    
    // 4. Promo Code
    $discount = 0;
    $promo_code_id = null;
    if ($promo_code) {
        $stmt = $pdo->prepare("SELECT * FROM promo_codes WHERE code = ? AND (valid_until >= CURRENT_DATE OR valid_until IS NULL) AND times_used < usage_limit");
        $stmt->execute([$promo_code]);
        $promo = $stmt->fetch();
        
        if ($promo) {
            $discount = ($total * ($promo['discount_percent'] / 100));
            $total -= $discount;
            $promo_code_id = $promo['id'];
        }
    }
    
    return [
        'total' => round($total),
        'base' => $total_base,
        'delivery' => $delivery_total,
        'discount' => round($discount),
        'promo_code_id' => $promo_code_id
    ];
}

/**
 * Atomic Availability Check
 */
function getAvailableStock($item_id, $date) {
    global $pdo;
    
    // Get total stock
    $stmt = $pdo->prepare("SELECT quantity_total FROM items WHERE id = ?");
    $stmt->execute([$item_id]);
    $total = $stmt->fetchColumn();
    
    // Get reserved (Approved or Pending)
    $stmt = $pdo->prepare("
        SELECT SUM(ri.quantity) 
        FROM reservation_items ri 
        JOIN reservations r ON ri.reservation_id = r.id 
        WHERE ri.item_id = ? 
        AND r.event_date = ? 
        AND r.status IN ('approved', 'pending', 'in_preparation')
    ");
    $stmt->execute([$item_id, $date]);
    $reserved = (int)$stmt->fetchColumn() ?: 0;
    
    return max(0, $total - $reserved);
}

/**
 * Atomic Stock Transaction
 */
function logStockMovement($item_id, $res_id, $qty, $reason) {
    global $pdo;
    $stmt = $pdo->prepare("INSERT INTO stock_log (item_id, reservation_id, change_qty, reason) VALUES (?, ?, ?, ?)");
    return $stmt->execute([$item_id, $res_id, $qty, $reason]);
}
?>
