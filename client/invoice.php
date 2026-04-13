<?php
// client/invoice.php
require_once '../includes/db.php';
require_once '../includes/auth.php';
requireClient();

$user_id = $_SESSION['user_id'];
$id = $_GET['id'] ?? null;

if (!$id) die("ID manquant.");

$is_staff = hasRole('super_admin') || hasRole('mini_admin') || hasRole('receptionist');

// Get reservation
if ($is_staff) {
    $stmt = $pdo->prepare("SELECT r.*, pc.code as promo_code_name FROM reservations r LEFT JOIN promo_codes pc ON r.promo_code_id = pc.id WHERE r.id = ?");
    $stmt->execute([$id]);
} else {
    $stmt = $pdo->prepare("SELECT r.*, pc.code as promo_code_name FROM reservations r LEFT JOIN promo_codes pc ON r.promo_code_id = pc.id WHERE r.id = ? AND r.user_id = ?");
    $stmt->execute([$id, $user_id]);
}
$res = $stmt->fetch();

if (!$res) die("Réservation introuvable ou accès refusé.");

// Get items
$stmt = $pdo->prepare("SELECT ri.*, i.name as item_name FROM reservation_items ri JOIN items i ON ri.item_id = i.id WHERE ri.reservation_id = ?");
$stmt->execute([$id]);
$items = $stmt->fetchAll();

// Get recent payment method and processor
$stmt = $pdo->prepare("
    SELECT p.payment_method, u.name as processor_name 
    FROM payments p 
    LEFT JOIN users u ON p.processed_by = u.id 
    WHERE p.reservation_id = ? 
    ORDER BY p.created_at DESC LIMIT 1
");
$stmt->execute([$id]);
$payment_info = $stmt->fetch();
$payment_method = $payment_info ? $payment_info['payment_method'] : 'N/A';
$processor_name = $payment_info ? ($payment_info['processor_name'] ?? 'Système Auto (Client)') : '';

$amount_paid = (float)($res['amount_paid'] ?? 0);
$total_price = (float)($res['total_price'] ?? 0);
$reste = max(0, $total_price - $amount_paid);

// Generate Secure Verification Link
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
// Assume base path is the parent directory of 'client'
$base_dir = dirname(dirname($_SERVER['REQUEST_URI'])) . '/';
$base_url = $protocol . "://" . $_SERVER['HTTP_HOST'] . $base_dir;
$verification_hash = md5($res['id'] . $res['created_at'] . 'SAM_EVENT_SECRET_2026');
$verify_link = $base_url . "verify.php?id=" . $res['id'] . "&hash=" . $verification_hash;
$qr_url = "https://api.qrserver.com/v1/create-qr-code/?size=90x90&data=" . urlencode($verify_link);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Facture #<?php echo $id; ?> - Sam Event</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 40px; color: #333; }
        .invoice-box { border: 1px solid #eee; padding: 30px; border-radius: 10px; max-width: 800px; margin: auto; }
        .header { display: flex; justify-content: space-between; margin-bottom: 40px; border-bottom: 2px solid #0047AB; padding-bottom: 20px; }
        .logo { font-size: 24px; font-weight: bold; color: #0047AB; }
        .info { display: flex; justify-content: space-between; margin-bottom: 40px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 40px; }
        th { background: #f8f9fa; padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        td { padding: 12px; border-bottom: 1px solid #eee; }
        .total { text-align: right; font-size: 20px; font-weight: bold; color: #FF6600; }
        .footer { margin-top: 50px; text-align: center; color: #999; font-size: 12px; }
        @media print {
            .no-print { display: none; }
        }
    </style>
</head>
<body>

<div class="no-print" style="text-align: center; margin-bottom: 20px;">
    <button onclick="window.print()" style="padding: 10px 20px; background: #0047AB; color: white; border: none; border-radius: 5px; cursor: pointer;">Imprimer / Sauvegarder en PDF</button>
</div>

<div class="invoice-box">
    <div class="header">
        <div class="logo">Sam Event LOCATION</div>
        <div style="text-align: right;">
            <strong>Facture #<?php echo $id; ?></strong><br>
            Date: <?php echo date('d/m/Y'); ?>
        </div>
    </div>

    <div class="info">
        <div>
            <strong>De:</strong><br>
            Sam Event Location<br>
            Quartier Aéroport, Niamey<br>
            Niger<br>
            +227 96 12 44 90
        </div>
        <div style="text-align: right;">
            <strong>À:</strong><br>
            <?php echo htmlspecialchars($res['customer_name']); ?><br>
            <?php echo htmlspecialchars($res['customer_phone']); ?><br>
            Date prévue: <?php echo date('d/m/Y', strtotime($res['event_date'])); ?><br>
            Durée: <?php echo htmlspecialchars($res['duration_days'] ?? 1); ?> jour(s)<br>
            Lieu: <?php echo htmlspecialchars($res['event_location']); ?><?php echo $res['distance_km'] ? ' (Distance : '.$res['distance_km'].' km)' : ''; ?>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Désignation</th>
                <th>Quantité</th>
                <th>Prix Unitaire</th>
                <th>Sous-total</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $item): ?>
            <tr>
                <td><?php echo htmlspecialchars($item['item_name']); ?></td>
                <td><?php echo $item['quantity']; ?></td>
                <td><?php echo number_format($item['price_at_time'], 0); ?> FCFA</td>
                <td><?php echo number_format($item['price_at_time'] * $item['quantity'], 0); ?> FCFA</td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <?php if ($res['discount_amount'] > 0): ?>
    <div style="text-align: right; font-size: 16px; margin-bottom: 10px; color: #15803d; font-weight: bold;">
        Remise Spéciale <?php echo $res['promo_code_name'] ? '(Code: ' . htmlspecialchars($res['promo_code_name']) . ')' : ''; ?> : -<?php echo number_format($res['discount_amount'], 0); ?> FCFA
    </div>
    <?php endif; ?>

    <div class="total" style="background: #fafafa; padding: 20px; border-radius: 8px; border: 1px solid #eee;">
        <div style="display: flex; justify-content: space-between; align-items: flex-end;">
            <div style="text-align: center;">
                <img src="<?php echo $qr_url; ?>" alt="Vérification QR" style="border: 1px solid #ccc; border-radius: 8px; padding: 5px; background: white;">
                <div style="font-size: 0.75rem; color: #777; margin-top: 5px; font-weight: bold;">Scanner pour vérifier<br>l'authenticité</div>
            </div>
            <div style="width: 350px;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 5px; font-size: 1.1rem; color: #555;">
                    <span>Montant Total:</span>
                    <strong><?php echo number_format($total_price, 0); ?> FCFA</strong>
                </div>
                
                <div style="display: flex; justify-content: space-between; margin-bottom: 5px; font-size: 1.1rem;">
                    <span style="color: #555;">Montant Payé:</span>
                    <strong style="color: #166534;"><?php echo number_format($amount_paid, 0); ?> FCFA</strong>
                </div>

                <?php if ($amount_paid > 0 && $payment_method !== 'N/A'): ?>
                <div style="display: flex; justify-content: space-between; margin-bottom: 5px; font-size: 0.9rem;">
                    <span style="color: #999;">Moyen de Paiement:</span>
                    <span style="color: #666; font-style: italic;"><?php echo strtoupper($payment_method); ?></span>
                </div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 15px; font-size: 0.85rem;">
                    <span style="color: #999;">Encaissé par:</span>
                    <span style="color: #6b7280; font-weight: 500; font-style: italic;"><i class="fas fa-user-circle" style="font-size: 0.8rem; margin-right: 3px;"></i> <?php echo htmlspecialchars($processor_name); ?></span>
                </div>
                <?php endif; ?>

                <?php if ($reste > 0): ?>
                <div style="display: flex; justify-content: space-between; padding-top: 15px; border-top: 2px dashed #ddd; margin-top: 10px;">
                    <span style="color: #ef4444; font-weight: bold; font-size: 1.2rem;">Reste à payer:</span>
                    <strong style="color: #ef4444; font-size: 1.4rem;"><?php echo number_format($reste, 0); ?> FCFA</strong>
                </div>
                <?php endif; ?>
                
                <?php if ($reste <= 0): ?>
                <div style="text-align: right; margin-top: 20px;">
                    <span style="background: #dcfce7; color: #166534; padding: 8px 20px; border-radius: 20px; font-weight: 900; font-size: 1.1rem; border: 1px solid #bbf7d0; display: inline-block;">FACTURE SOLDÉE</span>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="footer">
        Merci de votre confiance !<br>
        Sam Event Location - Votre partenaire événementiel à Niamey.
    </div>
</div>

</body>
</html>
