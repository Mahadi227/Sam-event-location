<?php
// client/verify.php
require_once '../includes/db.php';
require_once '../includes/engine.php';

$return_id = isset($_GET['ret']) ? (int)$_GET['ret'] : null;

if (!$return_id) {
    die("Lien de vérification invalide.");
}

$stmt = $pdo->prepare("
    SELECT ret.*, r.customer_name, r.customer_phone, r.event_date, b.name as branch_name, b.phone as branch_phone 
    FROM returns ret 
    JOIN reservations r ON ret.reservation_id = r.id 
    LEFT JOIN branches b ON r.branch_id = b.id 
    WHERE ret.id = ?
");
$stmt->execute([$return_id]);
$return_data = $stmt->fetch();

if (!$return_data) {
    $error = "Ce document n'existe pas ou a été supprimé de notre système. Il s'agit probablement d'un document falsifié.";
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vérification de Facture - Sam Event Location</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { font-family: 'Outfit', sans-serif; background: #f4f5f7; margin: 0; padding: 20px; display: flex; justify-content: center; align-items: center; min-height: 100vh; color: #334155; }
        .card { background: white; padding: 40px; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); max-width: 500px; width: 100%; text-align: center; }
        .logo { font-size: 1.8rem; font-weight: 900; color: #1e3a8a; margin-bottom: 30px; }
        .logo span { color: #d97706; }
        .success-icon { font-size: 4rem; color: #10b981; margin-bottom: 20px; }
        .error-icon { font-size: 4rem; color: #ef4444; margin-bottom: 20px; }
        .info-row { display: flex; justify-content: space-between; padding: 15px 0; border-bottom: 1px dashed #e2e8f0; }
        .info-row:last-child { border-bottom: none; }
        .info-label { color: #64748b; font-weight: 600; text-align: left; }
        .info-val { font-weight: 800; color: #0f172a; text-align: right; }
        .badge { display: inline-block; padding: 5px 12px; border-radius: 20px; font-size: 0.8rem; font-weight: bold; text-transform: uppercase; }
        .badge-paid { background: #dcfce7; color: #166534; }
        .badge-unpaid { background: #fee2e2; color: #b91c1c; }
    </style>
</head>
<body>

<div class="card">
    <div class="logo">Sam Event <span>LOCATION</span></div>
    
    <?php if (isset($error)): ?>
        <i class="fas fa-times-circle error-icon"></i>
        <h2 style="color: #ef4444; margin-top: 0;">Document Invalide</h2>
        <p style="color: #64748b; line-height: 1.6;"><?php echo $error; ?></p>
    <?php else: ?>
        <i class="fas fa-check-circle success-icon"></i>
        <h2 style="color: #10b981; margin-top: 0;">Certificat d'Authenticité</h2>
        <p style="color: #64748b; margin-bottom: 30px;">Ce document correspond bien à un enregistrement officiel dans notre base de données sécurisée.</p>
        
        <div style="background: #f8fafc; border-radius: 12px; padding: 20px;">
            <div class="info-row">
                <span class="info-label">Référence Retour</span>
                <span class="info-val">#RET-<?php echo str_pad($return_data['id'], 4, '0', STR_PAD_LEFT); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Date d'émission</span>
                <span class="info-val"><?php echo date('d/m/Y', strtotime($return_data['returned_date'])); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Client</span>
                <span class="info-val"><?php echo htmlspecialchars($return_data['customer_name']); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Succursale</span>
                <span class="info-val" style="text-align: right; display: flex; flex-direction: column; align-items: flex-end; gap: 4px;">
                    <span><?php echo htmlspecialchars($return_data['branch_name'] ?? 'Principale'); ?></span>
                    <?php if (!empty($return_data['branch_phone'])): ?>
                        <span style="display: inline-flex; align-items: center; gap: 5px; background: #f1f5f9; color: #475569; padding: 4px 8px; border-radius: 6px; font-size: 0.75rem; font-weight: 600; border: 1px solid #e2e8f0;"><i class="fas fa-phone-alt" style="color: #94a3b8; font-size: 0.7rem;"></i> <?php echo htmlspecialchars($return_data['branch_phone']); ?></span>
                    <?php endif; ?>
                </span>
            </div>
            <div class="info-row">
                <span class="info-label">Total Pénalité</span>
                <span class="info-val" style="color: #ef4444;"><?php echo number_format($return_data['penalty_total'], 0, ',', ' '); ?> <?php echo getCurrency(); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Reste à Payer</span>
                <span class="info-val" style="color: #166534;"><?php echo number_format(max(0, $return_data['penalty_total'] - $return_data['penalty_paid']), 0, ',', ' '); ?> <?php echo getCurrency(); ?></span>
            </div>
            <div class="info-row" style="border-top: 2px solid #e2e8f0; padding-top: 20px; margin-top: 5px;">
                <span class="info-label">Statut Comptable</span>
                <span class="info-val">
                    <?php if (($return_data['penalty_total'] - $return_data['penalty_paid']) <= 0 && $return_data['penalty_total'] > 0): ?>
                        <span class="badge badge-paid"><i class="fas fa-check"></i> Facture Soldée</span>
                    <?php elseif ($return_data['penalty_total'] == 0): ?>
                        <span class="badge badge-paid"><i class="fas fa-check"></i> Sans Pénalité</span>
                    <?php else: ?>
                        <span class="badge badge-unpaid"><i class="fas fa-exclamation-triangle"></i> En Attente de Paiement</span>
                    <?php endif; ?>
                </span>
            </div>
        </div>
        
        <p style="margin-top: 30px; font-size: 0.8rem; color: #94a3b8;">
            Scanné le <?php echo date('d/m/Y à H:i'); ?><br>
            Données certifiées et hébergées sur les serveurs de Sam Event Location.
        </p>
    <?php endif; ?>
</div>

</body>
</html>
