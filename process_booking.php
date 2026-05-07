<?php
// process_booking.php
require_once 'includes/db.php';
require_once 'includes/engine.php';
require_once 'includes/mailer.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user_id'] ?? null;
    $customer_name = $_POST['customer_name'] ?? '';
    $customer_phone = $_POST['customer_phone'] ?? '';
    $customer_email = $_POST['customer_email'] ?? null;
    $event_date = $_POST['event_date'] ?? '';
    $event_location = $_POST['event_location'] ?? '';
    $total_price = $_POST['total_price'] ?? 0;
    $selected_items = $_POST['items'] ?? [];

    if (empty($customer_name) || empty($customer_phone) || empty($event_date)) {
        die("Erreur : Veuillez remplir tous les champs obligatoires.");
    }

    try {
        $pdo->beginTransaction();

        // 1. Insérer la réservation avec user_id (peut être NULL pour visiteur)
        $stmt = $pdo->prepare("INSERT INTO reservations (user_id, customer_name, customer_phone, event_date, event_location, total_price, status) VALUES (?, ?, ?, ?, ?, ?, 'pending')");
        $stmt->execute([$user_id, $customer_name, $customer_phone, $event_date, $event_location, $total_price]);
        $reservation_id = $pdo->lastInsertId();

        // 2. Insérer les items de la réservation (schema: price_at_time)
        foreach ($selected_items as $item_id => $quantity) {
            $quantity = (int)$quantity;
            if ($quantity > 0) {
                // Check stock
                $available = getAvailableStock($item_id, $event_date);
                if ($quantity > $available) {
                    $stName = $pdo->prepare("SELECT name FROM items WHERE id = ?");
                    $stName->execute([$item_id]);
                    $itemName = $stName->fetchColumn() ?: "ID $item_id";
                    throw new Exception("Stock insuffisant pour l'article « $itemName ». Disponible: $available.");
                }

                // Récupérer le prix actuel
                $st = $pdo->prepare("SELECT price_per_day FROM items WHERE id = ?");
                $st->execute([$item_id]);
                $item = $st->fetch();
                
                if ($item) {
                    $item_price = $item['price_per_day'];
                    $stmt_item = $pdo->prepare("INSERT INTO reservation_items (reservation_id, item_id, quantity, price_at_time) VALUES (?, ?, ?, ?)");
                    $stmt_item->execute([$reservation_id, $item_id, $quantity, $item_price]);
                }
            }
        }

        $pdo->commit();
        
        // Log Activity
        logActivity($user_id, null, 'CREATE_RESERVATION', "Réservation #$reservation_id créée pour $customer_name.");
        
        // Send Notification Email
        sendReservationEmail($pdo, $reservation_id, $customer_email);
        
        header("Location: confirmation.php?id=" . $reservation_id);
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        die("Erreur lors de l'enregistrement : " . $e->getMessage());
    }
} else {
    header("Location: booking.php");
    exit;
}
?>
