<?php
// process_booking_ai.php
require_once 'includes/db.php';
require_once 'includes/engine.php';
require_once 'includes/mailer.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: booking.php");
    exit;
}

try {
    $pdo->beginTransaction();

    $user_id = $_SESSION['user_id'] ?? null;
    $customer_name = $_POST['customer_name'] ?? ($_SESSION['name'] ?? 'Client');
    $customer_phone = $_POST['customer_phone'] ?? '';
    $customer_email = $_POST['customer_email'] ?? null;
    $event_date = $_POST['event_date'];
    $event_location = $_POST['location'];
    $duration = (int)$_POST['duration'];
    $distance = (int)$_POST['distance'];
    $items_requested = $_POST['items'] ?? [];
    $promo_code = $_POST['promo_code'] ?? null;

    // --- AUTOMATIC ACCOUNT CREATION or CUSTOMER LOOKUP ---
    $is_staff = isset($_SESSION['role']) && in_array($_SESSION['role'], ['super_admin', 'mini_admin', 'receptionist']);
    $target_user_id = null;

    if (!$is_staff && $user_id) {
        // Normal logged-in client
        $target_user_id = $user_id;
    } else {
        // Look up customer by phone or name
        $stmt_user = $pdo->prepare("SELECT id, name FROM users WHERE phone = ?");
        $stmt_user->execute([$customer_phone]);
        $existing_user = $stmt_user->fetch();

        if ($existing_user) {
            $target_user_id = $existing_user['id'];
        } else {
            // Create new client user
            $hashed_password = password_hash($customer_phone, PASSWORD_DEFAULT);
            $stmt_new = $pdo->prepare("INSERT INTO users (name, phone, password, role, email) VALUES (?, ?, ?, 'client', NULL)");
            $stmt_new->execute([$customer_name, $customer_phone, $hashed_password]);
            $target_user_id = $pdo->lastInsertId();
        }

        if (!$is_staff) {
            // Auto-login for normal visitor (not already logged in as staff)
            $_SESSION['user_id'] = $target_user_id;
            $_SESSION['name'] = $customer_name;
            $_SESSION['phone'] = $customer_phone;
            $_SESSION['role'] = 'client';
        }
    }
    
    $user_id = $target_user_id; // Set target user for the reservation
    // ---------------------------------

    // 1. Re-calculate total server-side for security
    $pricing = calculateTotalPrice($items_requested, [
        'duration_days' => $duration,
        'distance_km' => $distance,
        'is_weekend' => (date('N', strtotime($event_date)) >= 6),
        'promo_code' => $promo_code
    ]);

    // 2. Atomic Stock Check
    foreach ($items_requested as $item_id => $qty) {
        if ($qty <= 0) continue;

        $available = getAvailableStock($item_id, $event_date);
        if ($qty > $available) {
            $stmt_name = $pdo->prepare("SELECT name FROM items WHERE id = ?");
            $stmt_name->execute([$item_id]);
            $item_name = $stmt_name->fetchColumn() ?: "ID $item_id";
            throw new Exception("Stock insuffisant pour l'article « " . $item_name . " » à cette date (" . $available . " restants en stock quant à la demande de " . $qty . ").");
        }
    }

    // 3. Insert Reservation
    $promo_code_id = $pricing['promo_code_id'] ?? null;
    $stmt = $pdo->prepare("INSERT INTO reservations (user_id, customer_name, customer_phone, event_date, event_location, total_price, duration_days, distance_km, discount_amount, promo_code_id, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
    $stmt->execute([$user_id, $customer_name, $customer_phone, $event_date, $event_location, $pricing['total'], $duration, $distance, $pricing['discount'], $promo_code_id]);
    $reservation_id = $pdo->lastInsertId();

    if ($promo_code_id) {
        $stmt = $pdo->prepare("UPDATE promo_codes SET times_used = times_used + 1 WHERE id = ?");
        $stmt->execute([$promo_code_id]);
    }

    // 4. Insert Items & Log Stock
    foreach ($items_requested as $item_id => $qty) {
        if ($qty <= 0) continue;

        $stmt_price = $pdo->prepare("SELECT price_per_day FROM items WHERE id = ?");
        $stmt_price->execute([$item_id]);
        $price = $stmt_price->fetchColumn();

        $stmt = $pdo->prepare("INSERT INTO reservation_items (reservation_id, item_id, quantity, price_at_time) VALUES (?, ?, ?, ?)");
        $stmt->execute([$reservation_id, $item_id, $qty, $price]);

        // Logical stock logging
        logStockMovement($item_id, $reservation_id, -$qty, "Réservation client #$reservation_id");
    }

    $pdo->commit();

    // Notify Socket Server (Real-time update)
    $ch = curl_init('http://localhost:3000/notify');
    $payload = json_encode(['type' => 'stock_update', 'data' => ['reservation_id' => $reservation_id]]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type:application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 1);
    curl_exec($ch);
    curl_close($ch);

    // Send Validation Email
    sendReservationEmail($pdo, $reservation_id, $customer_email);

    $is_ajax = (!empty($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);

    if ($is_ajax) {
        echo json_encode(['success' => true, 'reservation_id' => $reservation_id, 'message' => "Réservation confirmée avec succès!"]);
        exit;
    }

    // Redirect with success
    header("Location: track_reservation.php?phone=" . urlencode($customer_phone) . "&success=1");
    exit;
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    
    $is_ajax = (!empty($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);
    
    if ($is_ajax) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }

    // Redirect back with error
    header("Location: booking.php?error=" . urlencode($e->getMessage()));
    exit;
}
