<?php
// client/invoice.php
require_once '../includes/db.php';
require_once '../includes/auth.php';
requireClient();

$user_id = $_SESSION['user_id'];
$id = $_GET['id'] ?? null;

if (!$id) die("ID manquant.");

// Get reservation
$stmt = $pdo->prepare("SELECT * FROM reservations WHERE id = ? AND user_id = ?");
$stmt->execute([$id, $user_id]);
$res = $stmt->fetch();

if (!$res) die("Réservation introuvable.");

// Get items
$stmt = $pdo->prepare("SELECT ri.*, i.name as item_name FROM reservation_items ri JOIN items i ON ri.item_id = i.id WHERE ri.reservation_id = ?");
$stmt->execute([$id]);
$items = $stmt->fetchAll();
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
            Lieu: <?php echo htmlspecialchars($res['event_location']); ?>
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

    <div class="total">
        TOTAL: <?php echo number_format($res['total_price'], 0); ?> FCFA
    </div>

    <div class="footer">
        Merci de votre confiance !<br>
        Sam Event Location - Votre partenaire événementiel à Niamey.
    </div>
</div>

</body>
</html>
