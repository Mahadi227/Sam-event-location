<?php
// client/details.php
require_once '../includes/db.php';
require_once '../includes/auth.php';
// Token-based access check
$id = $_GET['id'] ?? null;
$token = $_GET['token'] ?? null;
$is_token_access = false;

if ($id && $token) {
    $stmt = $pdo->prepare("SELECT customer_phone FROM reservations WHERE id = ?");
    $stmt->execute([$id]);
    $res_check = $stmt->fetch();
    if ($res_check && md5($id . $res_check['customer_phone']) === $token) {
        $is_token_access = true;
    }
}

if (!$is_token_access) {
    requireClient();
    $user_id = $_SESSION['user_id'];
}

if (!$id) {
    header("Location: history.php");
    exit;
}

// Get reservation
if ($is_token_access) {
    $stmt = $pdo->prepare("SELECT * FROM reservations WHERE id = ?");
    $stmt->execute([$id]);
} else {
    $stmt = $pdo->prepare("SELECT * FROM reservations WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $user_id]);
}
$res = $stmt->fetch();

if (!$res) {
    die("Réservation introuvable ou accès non autorisé.");
}

// Get items
$stmt = $pdo->prepare("SELECT ri.*, i.name as item_name FROM reservation_items ri JOIN items i ON ri.item_id = i.id WHERE ri.reservation_id = ?");
$stmt->execute([$id]);
$items = $stmt->fetchAll();

// Handle payment proof upload
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['proof'])) {
    $target_dir = "../uploads/proofs/";
    if (!file_exists($target_dir)) mkdir($target_dir, 0777, true);
    
    $file_ext = strtolower(pathinfo($_FILES["proof"]["name"], PATHINFO_EXTENSION));
    $file_name = "proof_" . $id . "_" . time() . "." . $file_ext;
    $target_file = $target_dir . $file_name;

    if (move_uploaded_file($_FILES["proof"]["tmp_name"], $target_file)) {
        $stmt = $pdo->prepare("UPDATE reservations SET payment_proof = ? WHERE id = ?");
        $stmt->execute([$file_name, $id]);
        $msg = "Preuve de paiement soumise avec succès !";
        $res['payment_proof'] = $file_name;
    } else {
        $msg = "Erreur lors de l'envoi du fichier.";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Détails Réservation #<?php echo $id; ?> - Sam Event</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body style="background: #f4f5f7;">

<?php if (!$is_token_access): ?>
<div class="admin-mobile-header">
    <div style="font-weight: 800; color: white;">Sam Event</div>
    <button class="admin-hamburger"><i class="fas fa-bars"></i></button>
</div>

<div class="admin-container">
    <div class="sidebar-overlay"></div>
    <div class="admin-sidebar">
        <div style="padding: 20px; text-align: center;">
            <div style="width: 60px; height: 60px; background: var(--accent-gold); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; border: 2px solid white; margin: 0 auto 10px;">S</div>
            <h2 style="color: white; font-size: 1.2rem;">Espace Client</h2>
        </div>
        <a href="dashboard.php"><i class="fas fa-home"></i> &nbsp; Tableau de bord</a>
        <a href="../booking.php"><i class="fas fa-calendar-plus"></i> &nbsp; Réserver</a>
        <a href="history.php" class="active"><i class="fas fa-history"></i> &nbsp; Mes Réservations</a>
        <a href="../logout.php" style="margin-top: 50px; color: #ef4444;"><i class="fas fa-sign-out-alt"></i> &nbsp; Déconnexion</a>
    </div>

    <div class="main-content">
<?php else: ?>
<div class="container" style="padding-top: 40px;">
<?php endif; ?>

    <?php if ($msg): ?>
        <div style="background: #d4edda; color: #155724; padding: 15px; border-radius: 10px; margin-bottom: 20px; font-weight: 600;">
            <i class="fas fa-check-circle"></i> <?php echo $msg; ?>
        </div>
    <?php endif; ?>

    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
        <h2 style="margin: 0;">Réservation #<?php echo $id; ?></h2>
        <a href="invoice.php?id=<?php echo $id; ?>" class="contact-btn" style="background: #444;"><i class="fas fa-file-pdf"></i> Facture PDF</a>
    </div>

    <div class="manage-grid">
        <div class="main-details">
            <div class="card">
                <span class="status-badge <?php echo $res['status']; ?>">Statut : <?php echo ucfirst($res['status']); ?></span>
                
                <h3 style="margin-bottom: 20px;">Articles réservés</h3>
                <?php foreach ($items as $item): ?>
                <div class="item-row">
                    <div>
                        <strong><?php echo htmlspecialchars($item['item_name']); ?></strong><br>
                        <small><?php echo number_format($item['price_at_time'], 0); ?> FCFA x <?php echo $item['quantity']; ?></small>
                    </div>
                    <div style="font-weight: 700;">
                        <?php echo number_format($item['price_at_time'] * $item['quantity'], 0); ?> FCFA
                    </div>
                </div>
                <?php endforeach; ?>

                <div style="display: flex; justify-content: space-between; margin-top: 30px; font-size: 1.3rem; font-weight: 800; color: var(--secondary-orange);">
                    <span>Total</span>
                    <span><?php echo number_format($res['total_price'], 0); ?> FCFA</span>
                </div>
            </div>

            <div class="card">
                <h3>Infos Événement</h3>
                <div style="margin-top: 15px;">
                    <p><strong>Date :</strong> <?php echo date('d/m/Y', strtotime($res['event_date'])); ?></p>
                    <p><strong>Durée :</strong> <?php echo htmlspecialchars($res['duration_days'] ?? 1); ?> jour(s)</p>
                    <p><strong>Lieu :</strong> <?php echo htmlspecialchars($res['event_location']); ?></p>
                    <p><strong>Client :</strong> <?php echo htmlspecialchars($res['customer_name']); ?></p>
                    <p><strong>Tel :</strong> <?php echo htmlspecialchars($res['customer_phone']); ?></p>
                </div>
            </div>
        </div>

        <div class="side-panel">
            <div class="card">
                <h3>Paiement</h3>
                <div style="margin: 20px 0;">
                    <div style="font-size: 0.9rem; color: #666;">Montant payé :</div>
                    <div style="font-size: 1.5rem; font-weight: 800; color: #166534;"><?php echo number_format($res['amount_paid'], 0); ?> FCFA</div>
                </div>

                <?php if ($res['amount_paid'] < $res['total_price']): ?>
                    <p style="font-size: 0.85rem; color: #666; margin-bottom: 20px;">
                        Veuillez charger votre preuve de paiement (Orange Money, Moov ou Espèces) ci-dessous.
                    </p>
                    <form method="POST" enctype="multipart/form-data">
                        <div class="form-group">
                            <input type="file" name="proof" required style="width: 100%; border: 1px dashed #ccc; padding: 10px; border-radius: 10px;">
                        </div>
                        <button type="submit" class="contact-btn" style="width: 100%; margin-top: 15px; border: none; cursor: pointer;">Envoyer la preuve</button>
                    </form>
                <?php endif; ?>

                <?php if ($res['payment_proof']): ?>
                    <div style="margin-top: 30px; border-top: 1px solid #eee; pt: 20px;">
                        <p><strong>Preuve soumise :</strong></p>
                        <a href="../uploads/proofs/<?php echo $res['payment_proof']; ?>" target="_blank" style="color: var(--primary-blue); font-size: 0.8rem;">
                            <i class="fas fa-image"></i> Voir le fichier
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php if (!$is_token_access): ?>
</div>
</div>
<?php endif; ?>

<script src="../assets/js/admin.js"></script>
</body>
</html>
