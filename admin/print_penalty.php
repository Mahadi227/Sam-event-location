<?php
// admin/print_penalty.php
require_once '../includes/db.php';
require_once '../includes/auth.php';
requireAdmin();

$return_id = $_GET['id'] ?? null;
if (!$return_id) die("ID de retour manquant.");

$stmt = $pdo->prepare("
    SELECT ret.*, r.customer_name, r.customer_phone, r.event_date, r.branch_id, b.name as branch_name, b.phone as branch_phone 
    FROM returns ret 
    JOIN reservations r ON ret.reservation_id = r.id 
    LEFT JOIN branches b ON r.branch_id = b.id 
    WHERE ret.id = ?
");
$stmt->execute([$return_id]);
$return_data = $stmt->fetch();

if (!$return_data) die("Retour introuvable.");

$stmt = $pdo->prepare("
    SELECT ri.*, i.name 
    FROM return_items ri 
    JOIN items i ON ri.item_id = i.id 
    WHERE ri.return_id = ? AND (ri.qty_damaged > 0 OR ri.qty_missing > 0)
");
$stmt->execute([$return_id]);
$penalty_items = $stmt->fetchAll();

$has_penalties = ($return_data['penalty_total'] > 0);

$collector_name = null;
if ($return_data['penalty_paid'] > 0) {
    $stmt = $pdo->prepare("SELECT u.name FROM payments p JOIN users u ON p.processed_by = u.id WHERE p.reservation_id = ? AND p.payment_type = 'penalty' ORDER BY p.created_at DESC LIMIT 1");
    $stmt->execute([$return_data['reservation_id']]);
    $collector_name = $stmt->fetchColumn();
}

$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$domain = $_SERVER['HTTP_HOST'];
$path = dirname(dirname($_SERVER['PHP_SELF'])) . '/client/verify.php';
$verify_url = $protocol . "://" . $domain . $path . "?ret=" . $return_id;
$qr_code_url = "https://api.qrserver.com/v1/create-qr-code/?size=120x120&data=" . urlencode($verify_url);

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Facture de Pénalité #<?php echo htmlspecialchars($return_id); ?></title>
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
            color: #ef4444;
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
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .total-row {
            background: #fef2f2;
        }
        .total-row td {
            font-weight: bold;
            font-size: 1.2rem;
            color: #b91c1c;
            border-top: 2px solid #fca5a5;
            padding: 20px 12px;
        }
        .footer {
            text-align: center;
            margin-top: 50px;
            color: #94a3b8;
            font-size: 0.9rem;
            border-top: 1px solid #f1f5f9;
            padding-top: 20px;
        }
        @media print {
            body { background: white; padding: 0; }
            .invoice-box { box-shadow: none; padding: 0; }
            .print-btn { display: none; }
        }
        .print-btn {
            display: block;
            width: 200px;
            margin: 20px auto;
            padding: 12px;
            background: #1e3a8a;
            color: white;
            text-align: center;
            text-decoration: none;
            border-radius: 6px;
            font-weight: bold;
            cursor: pointer;
        }
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
    </style>
</head>
<body>

<div class="invoice-box" style="position: relative;">
    <?php if ($has_penalties && ($return_data['penalty_total'] - $return_data['penalty_paid']) <= 0): ?>
        <div class="stamp-sold">Facture Soldée</div>
    <?php endif; ?>
    <div class="header">
        <div class="logo">
            Sam Event <span>LOCATION</span>
        </div>
        <div class="invoice-title">
            <h1>Facture de Pénalité</h1>
            <p>Réf Retour : #RET-<?php echo str_pad($return_id, 4, '0', STR_PAD_LEFT); ?></p>
            <p>Date : <?php echo date('d/m/Y', strtotime($return_data['returned_date'])); ?></p>
        </div>
    </div>

    <div class="info-section">
        <div class="info-block">
            <h3>Facturé à</h3>
            <p><?php echo htmlspecialchars($return_data['customer_name']); ?></p>
            <p><i class="fas fa-phone"></i> <?php echo htmlspecialchars($return_data['customer_phone']); ?></p>
            <p style="margin-top: 10px; font-size: 0.9rem; color: #64748b;">Lié à la réservation #<?php echo $return_data['reservation_id']; ?></p>
        </div>
        <div class="info-block" style="text-align: right;">
            <h3>Émis par</h3>
            <p>Sam Event Location</p>
            <p><i class="fas fa-building"></i> Succursale: <?php echo htmlspecialchars($return_data['branch_name'] ?? 'Principale'); ?></p>
            <?php if (!empty($return_data['branch_phone'])): ?>
                <p><i class="fas fa-phone-alt"></i> Contact: <?php echo htmlspecialchars($return_data['branch_phone']); ?></p>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!$has_penalties): ?>
        <div style="background: #ecfdf5; border: 2px solid #10b981; color: #065f46; padding: 20px; text-align: center; border-radius: 8px; font-weight: bold; font-size: 1.2rem;">
            <i class="fas fa-check-circle"></i> Retour complet. Aucune pénalité n'est applicable.
        </div>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Article</th>
                    <th class="text-center">Endommagé</th>
                    <th class="text-center">Manquant</th>
                    <th class="text-right">Montant Pénalité</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($penalty_items as $item): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($item['name']); ?></strong></td>
                        <td class="text-center" style="color: #d97706; font-weight: bold;"><?php echo $item['qty_damaged'] > 0 ? $item['qty_damaged'] : '-'; ?></td>
                        <td class="text-center" style="color: #ef4444; font-weight: bold;"><?php echo $item['qty_missing'] > 0 ? $item['qty_missing'] : '-'; ?></td>
                        <td class="text-right"><?php echo number_format($item['penalty_amount'], 0, ',', ' '); ?> <?php echo getCurrency(); ?></td>
                    </tr>
                <?php endforeach; ?>
                <tr class="total-row">
                    <td colspan="3" class="text-right">TOTAL PÉNALITÉ :</td>
                    <td class="text-right"><?php echo number_format($return_data['penalty_total'], 0, ',', ' '); ?> <?php echo getCurrency(); ?></td>
                </tr>
                <tr>
                    <td colspan="3" class="text-right" style="padding: 10px 12px; font-weight: 600; color: #166534;">MONTANT PAYÉ :</td>
                    <td class="text-right" style="padding: 10px 12px; font-weight: 600; color: #166534;">- <?php echo number_format($return_data['penalty_paid'], 0, ',', ' '); ?> <?php echo getCurrency(); ?></td>
                </tr>
                <tr>
                    <td colspan="3" class="text-right" style="padding: 15px 12px; font-weight: 900; font-size: 1.2rem;">RESTE À PAYER :</td>
                    <td class="text-right" style="padding: 15px 12px; font-weight: 900; font-size: 1.2rem; <?php echo ($return_data['penalty_total'] - $return_data['penalty_paid'] <= 0) ? 'color: #166534;' : 'color: #ef4444;'; ?>"><?php echo number_format(max(0, $return_data['penalty_total'] - $return_data['penalty_paid']), 0, ',', ' '); ?> <?php echo getCurrency(); ?></td>
                </tr>
            </tbody>
        </table>
        
        <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-top: 20px;">
            <div style="text-align: left;">
                <img src="<?php echo $qr_code_url; ?>" alt="QR Code" style="width: 80px; height: 80px; border: 2px solid #e2e8f0; padding: 5px; border-radius: 8px; background: white;">
                <div style="font-size: 0.6rem; color: #64748b; margin-top: 5px; font-weight: bold; line-height: 1.2;">SCANNEZ POUR<br>VÉRIFIER</div>
            </div>
            
            <?php if ($return_data['penalty_paid'] > 0 && $collector_name): ?>
                <div style="text-align: right; font-size: 1rem; color: #166534; padding-bottom: 5px;">
                    <i class="fas fa-check-circle"></i> Encaissé par : <strong><?php echo htmlspecialchars($collector_name); ?></strong>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($return_data['notes'])): ?>
        <div style="margin-top: 30px; background: #f8fafc; padding: 15px; border-radius: 6px; border-left: 4px solid #cbd5e1;">
            <strong>Notes d'inspection :</strong><br>
            <p style="margin: 5px 0 0; color: #475569;"><?php echo nl2br(htmlspecialchars($return_data['notes'])); ?></p>
        </div>
    <?php endif; ?>

    <div class="footer">
        <p>En cas de question concernant cette facture, veuillez nous contacter.</p>
        <p>Merci de votre confiance.</p>
    </div>
</div>

<button class="print-btn" onclick="window.print()">
    <i class="fas fa-print"></i> Imprimer la facture
</button>

</body>
</html>
