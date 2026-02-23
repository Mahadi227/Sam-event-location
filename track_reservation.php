<?php
// track_reservation.php
require_once 'includes/db.php';
session_start();

$error = '';
$reservations = [];
$phone = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $phone = $_POST['phone'] ?? '';
    
    if (!empty($phone)) {
        // Find reservations by phone number
        $stmt = $pdo->prepare("SELECT * FROM reservations WHERE customer_phone = ? ORDER BY created_at DESC");
        $stmt->execute([$phone]);
        $reservations = $stmt->fetchAll();
        
        if (empty($reservations)) {
            $error = "Aucune réservation trouvée pour ce numéro.";
        }
    } else {
        $error = "Veuillez entrer votre numéro de téléphone.";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Suivi Rapide - Sam Event Location</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <style>
        .track-box { max-width: 600px; margin: 60px auto; background: white; padding: 40px; border-radius: 20px; box-shadow: var(--shadow-strong); border-top: 6px solid var(--accent-gold); }
        .res-item { background: #f9f9f9; padding: 20px; border-radius: 15px; margin-bottom: 15px; border: 1px solid #eee; display: flex; justify-content: space-between; align-items: center; }
        .status-badge { padding: 5px 12px; border-radius: 15px; font-size: 0.8rem; font-weight: 700; }
        .pending { background: #fef9c3; color: #854d0e; }
        .approved { background: #dcfce7; color: #166534; }
        .rejected { background: #fee2e2; color: #991b1b; }
    </style>
</head>
<body style="background: var(--light-gray);">

<div class="container" style="text-align: center;">
    <a href="index.php" class="logo-container" style="justify-content: center; margin-bottom: 30px; margin-top: 40px;">
        <div style="width: 60px; height: 60px; background: var(--accent-gold); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; border: 4px solid var(--primary-blue); font-size: 1.5rem;">S</div>
        <div class="logo-text" style="font-size: 1.8rem;">Sam Event <span>LOCATION</span></div>
    </a>

    <div class="track-box">
        <h2 style="margin-bottom: 10px;">Suivre mon matériel</h2>
        <p style="color: #666; margin-bottom: 25px;">Accédez rapidement à vos réservations avec votre numéro</p>
        
        <form method="POST" style="margin-bottom: 30px;">
            <div class="form-group" style="text-align: left; display: flex; gap: 10px;">
                <input type="tel" name="phone" class="form-control" placeholder="Votre numéro de téléphone" value="<?php echo htmlspecialchars($phone); ?>" required style="flex: 1; padding: 12px; border-radius: 10px; border: 1px solid #ddd;">
                <button type="submit" class="contact-btn" style="border: none; cursor: pointer; padding: 12px 25px;">Chercher</button>
            </div>
        </form>

        <?php if ($error): ?>
            <div style="background: #fee2e2; color: #991b1b; padding: 15px; border-radius: 10px; margin-bottom: 20px;">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <?php foreach ($reservations as $res): ?>
            <div class="res-item">
                <div style="text-align: left;">
                    <div style="font-weight: 800; color: var(--primary-blue);">Réservation #<?php echo $res['id']; ?></div>
                    <div style="font-size: 0.9rem; color: #666;"><?php echo date('d/m/Y', strtotime($res['event_date'])); ?> - <?php echo htmlspecialchars($res['event_location']); ?></div>
                    <div style="font-weight: 700; color: var(--secondary-orange); margin-top: 5px;"><?php echo number_format($res['total_price'], 0); ?> FCFA</div>
                </div>
                <div>
                    <span class="status-badge <?php echo $res['status']; ?>">
                        <?php echo ucfirst($res['status']); ?>
                    </span>
                    <a href="client/details.php?id=<?php echo $res['id']; ?>&token=<?php echo md5($res['id'] . $res['customer_phone']); ?>" style="display: block; margin-top: 10px; font-size: 0.8rem; color: var(--primary-blue); font-weight: 600;">Voir détails</a>
                </div>
            </div>
        <?php endforeach; ?>

        <p style="margin-top: 30px; color: #666;">
            <a href="index.php" style="color: var(--primary-blue); text-decoration: none; font-weight: 700;">← Retour à l'accueil</a>
        </p>
    </div>
</div>

</body>
</html>
