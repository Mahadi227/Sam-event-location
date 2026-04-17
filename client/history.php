<?php
// client/history.php
require_once '../includes/db.php';
require_once '../includes/auth.php';
requireClient();

$user_id = $_SESSION['user_id'];
$reservations = $pdo->prepare("SELECT * FROM reservations WHERE user_id = ? ORDER BY created_at DESC");
$reservations->execute([$user_id]);
$all = $reservations->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes Réservations - Sam Event</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css?v=2">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body style="background: #f4f5f7;">

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
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
            <h2 style="margin: 0;">Historique des Réservations</h2>
            <a href="../booking.php" class="contact-btn"><i class="fas fa-plus"></i> Nouvelle Réservation</a>
        </div>

        <div class="card">
        <?php if (empty($all)): ?>
            <p style="text-align: center; color: #888; padding: 40px 0;">Aucune réservation trouvée.</p>
        <?php else: ?>
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="text-align: left; border-bottom: 2px solid #eee;">
                            <th style="padding: 15px;">ID</th>
                            <th style="padding: 15px;">Date Événement</th>
                            <th style="padding: 15px;">Lieu</th>
                            <th style="padding: 15px;">Total</th>
                            <th style="padding: 15px;">Statut</th>
                            <th style="padding: 15px;">Paiement</th>
                            <th style="padding: 15px;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($all as $res): ?>
                        <tr style="border-bottom: 1px solid #eee;">
                            <td style="padding: 15px;">#<?php echo $res['id']; ?></td>
                            <td style="padding: 15px;"><?php echo date('d/m/Y', strtotime($res['event_date'])); ?></td>
                            <td style="padding: 15px;"><?php echo htmlspecialchars($res['event_location']); ?></td>
                            <td style="padding: 15px; font-weight: 700; color: var(--secondary-orange);"><?php echo number_format($res['total_price'], 0); ?> FCFA</td>
                            <td style="padding: 15px;">
                                <span class="status-badge <?php echo $res['status']; ?>">
                                    <?php echo ucfirst($res['status']); ?>
                                </span>
                            </td>
                            <td style="padding: 15px;">
                                <?php if ($res['amount_paid'] >= $res['total_price']): ?>
                                    <span style="color: #166534;"><i class="fas fa-check-circle"></i> Payé</span>
                                <?php elseif ($res['amount_paid'] > 0): ?>
                                    <span style="color: #854d0e;">Partiel (<?php echo number_format($res['amount_paid'], 0); ?>)</span>
                                <?php else: ?>
                                    <span style="color: #991b1b;">Non payé</span>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 15px;">
                                <a href="details.php?id=<?php echo $res['id']; ?>" class="btn-reserve" style="padding: 5px 15px; font-size: 0.8rem; display: inline-block; background: var(--primary-blue);">Détails</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
</div>

<script src="../assets/js/admin.js"></script>
</body>
</html>
