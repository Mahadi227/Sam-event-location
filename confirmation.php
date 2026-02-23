<?php
// confirmation.php
require_once 'includes/db.php';

$id = $_GET['id'] ?? null;
if (!$id) {
    header("Location: index.php");
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM reservations WHERE id = ?");
$stmt->execute([$id]);
$reservation = $stmt->fetch();

if (!$reservation) {
    die("Réservation introuvable.");
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirmation - Sam Event Location</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>

<header>
    <a href="index.php" class="logo">Sam Event Location</a>
</header>

<div class="container" style="text-align: center; max-width: 600px;">
    <div class="card" style="border-top: 5px solid green;">
        <i class="fas fa-check-circle" style="font-size: 50px; color: green; margin-bottom: 20px;"></i>
        <h1>Merci, <?php echo htmlspecialchars($reservation['customer_name']); ?> !</h1>
        <p>Votre demande de réservation a été enregistrée avec succès.</p>
        <br>
        <div style="background: #f0f0f0; padding: 20px; border-radius: 5px; text-align: left;">
            <p><strong>Numéro de réservation :</strong> #<?php echo $reservation['id']; ?></p>
            <p><strong>Date prévue :</strong> <?php echo date('d/m/Y', strtotime($reservation['event_date'])); ?></p>
            <p><strong>Montant Total estimé :</strong> <?php echo number_format($reservation['total_price'], 0, ',', ' '); ?> FCFA</p>
            <p><strong>Statut :</strong> <span style="color: orange;">En attente de confirmation</span></p>
        </div>
        <br>
        <p>Notre équipe vous contactera au <strong><?php echo htmlspecialchars($reservation['customer_phone']); ?></strong> très prochainement pour confirmer la disponibilité et finaliser les détails.</p>
        <br>
        <a href="index.php" class="btn btn-gold">Retour à l'accueil</a>
    </div>
</div>

<footer>
    <p>&copy; <?php echo date('Y'); ?> Sam Event Location - Niamey</p>
</footer>

</body>
</html>
