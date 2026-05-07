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
    // 5. Tax
    $tax_rate = getTaxRate();
    $tax_amount = 0;
    if ($tax_rate > 0) {
        $tax_amount = $total * ($tax_rate / 100);
        $total += $tax_amount;
    }
    
    return [
        'total' => $total,
        'base' => $total_base,
        'delivery' => $delivery_total,
        'discount' => $discount,
        'tax' => $tax_amount,
        'promo_code_id' => $promo_code_id
    ];
}

/**
 * Atomic Availability Check (Dynamic Daily)
 */
function getAvailableStock($item_id, $start_date = null, $duration_days = 1) {
    global $pdo;
    
    if (empty($start_date)) {
        $start_date = date('Y-m-d');
    }
    
    // Get total and maintenance stock
    $stmt = $pdo->prepare("SELECT quantity_total, quantity_maintenance FROM items WHERE id = ?");
    $stmt->execute([$item_id]);
    $item = $stmt->fetch();
    
    if (!$item) return 0;
    
    $total = (int)$item['quantity_total'];
    $maintenance = (int)$item['quantity_maintenance'];
    
    // Calculate requested end date
    $end_date = date('Y-m-d', strtotime($start_date . " + " . ($duration_days - 1) . " days"));
    
    // Get reserved overlapping this period
    $stmt = $pdo->prepare("
        SELECT SUM(ri.quantity) 
        FROM reservation_items ri 
        JOIN reservations r ON ri.reservation_id = r.id 
        WHERE ri.item_id = ? 
        AND r.status IN ('approved', 'pending', 'in_preparation')
        AND r.event_date <= ? 
        AND DATE_ADD(r.event_date, INTERVAL (r.duration_days - 1) DAY) >= ?
    ");
    $stmt->execute([$item_id, $end_date, $start_date]);
    $reserved = (int)$stmt->fetchColumn() ?: 0;
    
    return max(0, $total - $maintenance - $reserved);
}

/**
 * Helper: Get Today's Reserved Stock (For UI Snapshot)
 */
function getTodayReservedStock($item_id) {
    global $pdo;
    $today = date('Y-m-d');
    $stmt = $pdo->prepare("
        SELECT SUM(ri.quantity) 
        FROM reservation_items ri 
        JOIN reservations r ON ri.reservation_id = r.id 
        WHERE ri.item_id = ? 
        AND r.status IN ('approved', 'pending', 'in_preparation')
        AND r.event_date <= ? 
        AND DATE_ADD(r.event_date, INTERVAL (r.duration_days - 1) DAY) >= ?
    ");
    $stmt->execute([$item_id, $today, $today]);
    return (int)$stmt->fetchColumn() ?: 0;
}

/**
 * Helper: Update Maintenance Stock
 * $action can be 'mark_damaged' (increase maintenance) or 'restore' (decrease maintenance)
 */
function updateItemMaintenance($item_id, $qty, $action) {
    global $pdo;
    if ($action === 'mark_damaged') {
        $stmt = $pdo->prepare("UPDATE items SET quantity_maintenance = quantity_maintenance + ? WHERE id = ?");
    } else {
        $stmt = $pdo->prepare("UPDATE items SET quantity_maintenance = GREATEST(0, quantity_maintenance - ?) WHERE id = ?");
    }
    return $stmt->execute([$qty, $item_id]);
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
