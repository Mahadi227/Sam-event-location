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
    $stmt = $pdo->prepare("SELECT r.*, pc.code as promo_code_name, b.name as branch_name, b.phone as branch_phone FROM reservations r LEFT JOIN promo_codes pc ON r.promo_code_id = pc.id LEFT JOIN branches b ON r.branch_id = b.id WHERE r.id = ?");
    $stmt->execute([$id]);
} else {
    $stmt = $pdo->prepare("SELECT r.*, pc.code as promo_code_name, b.name as branch_name, b.phone as branch_phone FROM reservations r LEFT JOIN promo_codes pc ON r.promo_code_id = pc.id LEFT JOIN branches b ON r.branch_id = b.id WHERE r.id = ? AND r.user_id = ?");
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
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            font-family: 'Outfit', sans-serif;
            background: #e2e8f0;
            color: #334155;
            margin: 0;
            padding: 40px;
        }
        .invoice-box {
            max-width: 800px;
            margin: auto;
            padding: 40px;
            background: white;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            border-radius: 8px;
            position: relative;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px solid #f1f5f9;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .logo {
            font-size: 2rem;
            font-weight: 900;
            color: #1e3a8a;
        }
        .logo span {
            color: #d97706;
        }
        .invoice-title {
            text-align: right;
        }
        .invoice-title h1 {
            color: #1e3a8a;
            margin: 0;
            font-size: 2.2rem;
            text-transform: uppercase;
        }
        .invoice-title p {
            margin: 5px 0 0;
            color: #64748b;
        }
        .info-section {
            display: flex;
            justify-content: space-between;
            margin-bottom: 40px;
        }
        .info-block h3 {
            margin: 0 0 10px;
            color: #94a3b8;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .info-block p {
            margin: 0 0 5px;
            font-weight: 600;
            font-size: 1.1rem;
        }
        table {
            width: 100%;
            line-height: inherit;
            text-align: left;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        table th {
            background: #f8fafc;
            padding: 12px;
            font-size: 0.9rem;
            color: #64748b;
            text-transform: uppercase;
            border-bottom: 2px solid #e2e8f0;
        }
        table td {
            padding: 15px 12px;
            border-bottom: 1px solid #f1f5f9;
        }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .footer {
            text-align: center;
            margin-top: 50px;
            color: #94a3b8;
            font-size: 0.9rem;
            border-top: 1px solid #f1f5f9;
            padding-top: 20px;
        }
        .print-btn {
            display: block;
            width: 300px;
            margin: 0 auto 20px;
            padding: 15px;
            background: #1e3a8a;
            color: white;
            text-align: center;
            border-radius: 8px;
            font-weight: bold;
            cursor: pointer;
            border: none;
            font-size: 1.1rem;
            transition: background 0.3s;
        }
        .print-btn:hover { background: #172554; }
        .stamp-sold {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-15deg);
            font-size: 4rem;
            font-weight: 900;
            color: rgba(22, 101, 52, 0.15);
            border: 8px solid rgba(22, 101, 52, 0.15);
            padding: 20px 40px;
            border-radius: 15px;
            pointer-events: none;
            z-index: 10;
            text-transform: uppercase;
            letter-spacing: 5px;
        }
        @media print {
            body { background: white; padding: 0; }
            .invoice-box { box-shadow: none; padding: 0; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>

<div class="no-print" style="text-align: center; margin-bottom: 20px;">
    <button class="print-btn" onclick="window.print()"><i class="fas fa-print"></i> Imprimer / Sauvegarder en PDF</button>
</div>

<div class="invoice-box">
    <?php if ($reste <= 0): ?>
        <div class="stamp-sold">Facture Soldée</div>
    <?php endif; ?>

    <div class="header">
        <div class="logo">Sam Event <span>LOCATION</span></div>
        <div class="invoice-title">
            <h1>Facture de Location</h1>
            <p>Réf: #RES-<?php echo str_pad($id, 4, '0', STR_PAD_LEFT); ?></p>
            <p>Date: <?php echo date('d/m/Y', strtotime($res['created_at'])); ?></p>
        </div>
    </div>

    <div class="info-section">
        <div class="info-block">
            <h3>Émis par</h3>
            <p>Sam Event Location</p>
            <p><i class="fas fa-building"></i> Succursale: <?php echo htmlspecialchars($res['branch_name'] ?? 'Principale'); ?></p>
            <p><i class="fas fa-phone-alt"></i> Contact: <?php echo htmlspecialchars($res['branch_phone'] ?: '+227 96 12 44 90'); ?></p>
        </div>
        <div class="info-block" style="text-align: right;">
            <h3>Facturé à</h3>
            <p><?php echo htmlspecialchars($res['customer_name']); ?></p>
            <p><i class="fas fa-phone"></i> <?php echo htmlspecialchars($res['customer_phone']); ?></p>
            <p style="margin-top: 10px; font-size: 0.9rem; color: #64748b;">
                <strong>Date de l'événement:</strong> <?php echo date('d/m/Y', strtotime($res['event_date'])); ?><br>
                <strong>Durée:</strong> <?php echo htmlspecialchars($res['duration_days'] ?? 1); ?> jour(s)<br>
                <strong>Lieu:</strong> <?php echo htmlspecialchars($res['event_location']); ?><?php echo $res['distance_km'] ? ' (Distance: '.$res['distance_km'].' km)' : ''; ?>
            </p>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Désignation</th>
                <th class="text-center">Quantité</th>
                <th class="text-right">Prix Unitaire</th>
                <th class="text-right">Sous-total</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $item): ?>
            <tr>
                <td><?php echo htmlspecialchars($item['item_name']); ?></td>
                <td class="text-center"><?php echo $item['quantity']; ?></td>
                <td class="text-right"><?php echo number_format($item['price_at_time'], 0, ',', ' '); ?> <?php echo getCurrency(); ?></td>
                <td class="text-right"><strong><?php echo number_format($item['price_at_time'] * $item['quantity'], 0, ',', ' '); ?> <?php echo getCurrency(); ?></strong></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <?php if ($res['discount_amount'] > 0): ?>
    <div style="text-align: right; font-size: 16px; margin-bottom: 10px; color: #15803d; font-weight: bold;">
        Remise Spéciale <?php echo $res['promo_code_name'] ? '(Code: ' . htmlspecialchars($res['promo_code_name']) . ')' : ''; ?> : -<?php echo number_format($res['discount_amount'], 0); ?> <?php echo getCurrency(); ?>
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
                    <strong><?php echo number_format($total_price, 0); ?> <?php echo getCurrency(); ?></strong>
                </div>
                
                <div style="display: flex; justify-content: space-between; margin-bottom: 5px; font-size: 1.1rem;">
                    <span style="color: #555;">Montant Payé:</span>
                    <strong style="color: #166534;"><?php echo number_format($amount_paid, 0); ?> <?php echo getCurrency(); ?></strong>
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
                    <strong style="color: #ef4444; font-size: 1.4rem;"><?php echo number_format($reste, 0); ?> <?php echo getCurrency(); ?></strong>
                </div>
                <?php endif; ?>
                
                <?php if ($reste <= 0): ?>
                <div style="text-align: right; margin-top: 20px;">
                    <span style="background: #dcfce7; color: #166534; padding: 8px 20px; border-radius: 20px; font-weight: 900; font-size: 1.1rem; border: 1px solid #bbf7d0; display: inline-block;"><i class="fas fa-check-circle"></i> FACTURE SOLDÉE</span>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="footer">
        <p>Merci de votre confiance !</p>
        <p>Sam Event Location - Votre partenaire événementiel à Niamey.</p>
    </div>
</div>

</body>
</html>
