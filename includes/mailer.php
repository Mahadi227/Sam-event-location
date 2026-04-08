<?php
// includes/mailer.php
require_once 'db.php';

function sendReservationEmail($pdo, $reservation_id, $customer_email = null)
{
    // 1. Fetch Reservation Details
    $stmt = $pdo->prepare("
        SELECT r.*, pc.code as promo_code_name 
        FROM reservations r 
        LEFT JOIN promo_codes pc ON r.promo_code_id = pc.id 
        WHERE r.id = ?
    ");
    $stmt->execute([$reservation_id]);
    $res = $stmt->fetch();

    if (!$res)
        return false;

    // 2. Fetch Items
    $stmt_items = $pdo->prepare("
        SELECT ri.quantity, ri.price_at_time, i.name 
        FROM reservation_items ri 
        JOIN items i ON ri.item_id = i.id 
        WHERE ri.reservation_id = ?
    ");
    $stmt_items->execute([$reservation_id]);
    $items = $stmt_items->fetchAll();

    // 3. Build HTML Template
    $admin_email = "faycalsoumana24@gmail.com"; // Change to your actual admin email
    $subject_admin = "Nouvelle Réservation #" . $res['id'] . " - " . $res['customer_name'];
    $subject_client = "Confirmation de votre réservation #" . $res['id'] . " - Sam Event";

    $items_html = "<table style='width:100%; border-collapse: collapse; margin-top:20px;'>
        <tr style='background:#f4f5f7; border-bottom:2px solid #ddd;'>
            <th style='padding:10px;text-align:left;'>Produit</th>
            <th style='padding:10px;text-align:center;'>Qté</th>
            <th style='padding:10px;text-align:right;'>Prix unitaire</th>
        </tr>";

    foreach ($items as $item) {
        $items_html .= "
        <tr style='border-bottom:1px solid #eee;'>
            <td style='padding:10px;'>" . htmlspecialchars($item['name']) . "</td>
            <td style='padding:10px;text-align:center;'>" . $item['quantity'] . "</td>
            <td style='padding:10px;text-align:right;'>" . number_format($item['price_at_time'], 0, ',', ' ') . " F</td>
        </tr>";
    }
    $items_html .= "</table>";

    $html_body = "
    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; color: #333; border: 1px solid #ddd; border-radius: 10px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.05);'>
        <div style='background: #03117a; color: white; padding: 20px; text-align: center;'>
            <h2 style='margin: 0;'>Sam Event Location</h2>
            <p style='margin: 5px 0 0; opacity: 0.8;'>Détails de la réservation #" . $res['id'] . "</p>
        </div>
        <div style='padding: 20px; background: white;'>
            <h3 style='color: #bfa100; border-bottom: 2px solid #eee; padding-bottom: 10px;'>Informations du Client</h3>
            <p><strong>Nom:</strong> " . htmlspecialchars($res['customer_name']) . "</p>
            <p><strong>Téléphone:</strong> " . htmlspecialchars($res['customer_phone']) . "</p>
            " . ($customer_email ? "<p><strong>Email:</strong> " . htmlspecialchars($customer_email) . "</p>" : "") . "
            
            <h3 style='color: #bfa100; border-bottom: 2px solid #eee; padding-bottom: 10px; margin-top: 30px;'>Détails de l'Événement</h3>
            <p><strong>Date:</strong> " . date('d/m/Y', strtotime($res['event_date'])) . "</p>
            <p><strong>Lieu:</strong> " . htmlspecialchars($res['event_location']) . "</p>
            <p><strong>Durée:</strong> " . $res['duration_days'] . " jour(s)</p>
            
            " . $items_html . "
            
            <div style='background: #f4f5f7; padding: 15px; margin-top: 20px; border-radius: 8px;'>
                <p style='margin: 0; font-size: 1.2rem; display: flex; justify-content: space-between;'>
                    <strong>Total:</strong> 
                    <span style='color: #166534;'>" . number_format($res['total_price'], 0, ',', ' ') . " FCFA</span>
                </p>
                " . ($res['promo_code_name'] ? "<p style='margin: 5px 0 0; color: #bfa100; font-size: 0.9rem;'>Code Promo: " . htmlspecialchars($res['promo_code_name']) . " (-" . number_format($res['discount_amount'], 0, ',', ' ') . " F)</p>" : "") . "
            </div>
        </div>
        <div style='background: #f9f9f9; padding: 15px; text-align: center; font-size: 0.8rem; color: #888;'>
            Ceci est un message automatique, merci de ne pas y répondre.
        </div>
    </div>";

    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: Sam Event <noreply@samevent.com>\r\n";

    // 4. Send to Admin
    @mail($admin_email, $subject_admin, $html_body, $headers);

    // 5. Send to Customer
    $client_email = $customer_email;
    if (!$client_email && $res['user_id']) {
        $stmt_email = $pdo->prepare("SELECT email FROM users WHERE id = ?");
        $stmt_email->execute([$res['user_id']]);
        $client_email = $stmt_email->fetchColumn();
    }

    if ($client_email) {
        @mail($client_email, $subject_client, $html_body, $headers);
    }

    return true;
}
?>