<?php
// verify.php
require_once 'includes/db.php';

$id = $_GET['id'] ?? null;
$hash = $_GET['hash'] ?? null;

if (!$id || !$hash) {
    die("Paramètres de vérification manquants.");
}

// Fetch reservation
$stmt = $pdo->prepare("SELECT * FROM reservations WHERE id = ?");
$stmt->execute([$id]);
$res = $stmt->fetch();

if (!$res) {
    die("Cette facture/réservation n'existe pas dans le système.");
}

// Security Check
$expected_hash = md5($res['id'] . $res['created_at'] . 'SAM_EVENT_SECRET_2026');
if ($hash !== $expected_hash) {
    die("ALERTE: Code QR falsifié ou corrompu !");
}

// Fetch real-time payment status
$stmt = $pdo->prepare("SELECT SUM(amount) FROM payments WHERE reservation_id = ?");
$stmt->execute([$id]);
$amount_paid = (float)$stmt->fetchColumn();
$total_price = (float)$res['total_price'];
$reste = max(0, $total_price - $amount_paid);
$is_paid = $reste <= 0;

$status_colors = [
    'pending' => '#f59e0b',
    'approved' => '#3b82f6',
    'in_preparation' => '#8b5cf6',
    'completed' => '#10b981',
    'cancelled' => '#ef4444',
    'rejected' => '#ef4444'
];
$status_color = $status_colors[$res['status']] ?? '#9ca3af';

// Layout
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vérification #<?php echo $id; ?></title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f4f5f7; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; padding: 20px; box-sizing: border-box; }
        .card { background: white; padding: 30px; border-radius: 16px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); width: 100%; max-width: 400px; text-align: center; }
        .icon-box { width: 80px; height: 80px; border-radius: 50%; display: flex; justify-content: center; align-items: center; margin: 0 auto 20px; font-size: 2rem; color: white; }
        .valid { background: #10b981; }
        .invalid { background: #ef4444; }
        .data-row { display: flex; justify-content: space-between; padding: 12px 0; border-bottom: 1px solid #f3f4f6; text-align: left; }
        .data-label { color: #6b7280; font-size: 0.9rem; }
        .data-value { color: #111827; font-weight: 600; font-size: 0.95rem; }
    </style>
</head>
<body>

<div class="card">
    <div class="icon-box valid">
        ✓
    </div>
    
    <h2 style="margin: 0 0 5px; color: #111827;">Document Authentique</h2>
    <p style="color: #6b7280; margin: 0 0 25px; font-size: 0.9rem;">Délivré par Sam Event Location</p>

    <div style="background: #f9fafb; border-radius: 12px; padding: 15px; margin-bottom: 20px;">
        <div class="data-row">
            <div class="data-label">N° Facture</div>
            <div class="data-value">#<?php echo $id; ?></div>
        </div>
        <div class="data-row">
            <div class="data-label">Client</div>
            <div class="data-value"><?php echo htmlspecialchars($res['customer_name']); ?></div>
        </div>
        <div class="data-row">
            <div class="data-label">Date de l'événement</div>
            <div class="data-value"><?php echo date('d/m/Y', strtotime($res['event_date'])); ?></div>
        </div>
        <div class="data-row">
            <div class="data-label">Statut Dossier</div>
            <div class="data-value" style="color: <?php echo $status_color; ?>;"><?php echo strtoupper($res['status']); ?></div>
        </div>
    </div>

    <div style="border: 2px solid <?php echo $is_paid ? '#10b981' : '#ef4444'; ?>; border-radius: 12px; padding: 15px;">
        <div style="font-size: 0.9rem; color: #6b7280; margin-bottom: 5px;">État Financier</div>
        <?php if ($is_paid): ?>
            <div style="color: #10b981; font-weight: 900; font-size: 1.4rem;">FACTURE SOLDÉE</div>
        <?php else: ?>
            <div style="color: #ef4444; font-weight: 900; font-size: 1.2rem;">RESTE À PAYER</div>
            <div style="color: #ef4444; font-weight: 900; font-size: 1.4rem; margin-top: 5px;"><?php echo number_format($reste, 0); ?> FCFA</div>
        <?php endif; ?>
    </div>
</div>

</body>
</html>
