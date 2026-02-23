<?php
// admin/user_history.php
require_once '../includes/db.php';
require_once '../includes/auth.php';
requireAdmin();

$user_id = $_GET['id'] ?? null;
if (!$user_id) {
    header("Location: users.php");
    exit;
}

// Fetch User
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    die("Utilisateur introuvable.");
}

// Fetch Reservations
$stmt = $pdo->prepare("SELECT * FROM reservations WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$user_id]);
$reservations = $stmt->fetchAll();

// Fetch Payments
$stmt = $pdo->prepare("SELECT p.*, r.customer_name FROM payments p JOIN reservations r ON p.reservation_id = r.id WHERE r.user_id = ? ORDER BY p.created_at DESC");
$stmt->execute([$user_id]);
$payments = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Historique Utilisateur - Sam Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body style="background: #f4f5f7;">

<div class="admin-mobile-header">
    <div style="font-weight: 800; color: white;">Sam Management</div>
    <button class="admin-hamburger"><i class="fas fa-bars"></i></button>
</div>

<div class="admin-container">
    <div class="admin-sidebar">
        <h2>Sam Management</h2>
        <a href="dashboard.php"><i class="fas fa-th-large"></i> &nbsp; Dashboard</a>
        <a href="items.php"><i class="fas fa-box"></i> &nbsp; Stock & Produits</a>
        <a href="reservations.php"><i class="fas fa-calendar-check"></i> &nbsp; Réservations</a>
        <a href="payments.php"><i class="fas fa-money-bill-wave"></i> &nbsp; Paiements</a>
        <a href="users.php" class="active"><i class="fas fa-users-cog"></i> &nbsp; Utilisateurs</a>
        <a href="settings.php"><i class="fas fa-tools"></i> &nbsp; Paramètres</a>
        <a href="../logout.php" style="margin-top: 50px; color: #ef4444;"><i class="fas fa-sign-out-alt"></i> &nbsp; Déconnexion</a>
    </div>

    <div class="main-content">
        <div style="display: flex; align-items: center; gap: 20px; margin-bottom: 30px;">
            <a href="users.php" style="background: white; width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #333; box-shadow: 0 2px 4px rgba(0,0,0,0.1);"><i class="fas fa-arrow-left"></i></a>
            <div>
                <h2 style="margin: 0;">Historique de <?php echo htmlspecialchars($user['name']); ?></h2>
                <p style="color: #666; margin: 0;"><?php echo htmlspecialchars($user['email']); ?> | <?php echo htmlspecialchars($user['phone'] ?? 'Pas de téléphone'); ?></p>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr; gap: 30px;">
            <!-- Reservations -->
            <div class="card">
                <h3 style="margin-bottom: 20px;"><i class="fas fa-calendar-check" style="color: #4338ca;"></i> Réservations</h3>
                <div class="table-responsive">
                    <table style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr style="text-align: left; border-bottom: 2px solid #eee;">
                                <th style="padding: 15px;">ID</th>
                                <th style="padding: 15px;">Date Event</th>
                                <th style="padding: 15px;">Total</th>
                                <th style="padding: 15px;">Payé</th>
                                <th style="padding: 15px;">Statut</th>
                                <th style="padding: 15px;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reservations as $r): ?>
                            <tr style="border-bottom: 1px solid #f9f9f9;">
                                <td style="padding: 15px;">#<?php echo $r['id']; ?></td>
                                <td style="padding: 15px;"><?php echo date('d/m/Y', strtotime($r['event_date'])); ?></td>
                                <td style="padding: 15px; font-weight: 700;"><?php echo number_format($r['total_price'], 0); ?> F</td>
                                <td style="padding: 15px; color: #166534;"><?php echo number_format($r['amount_paid'], 0); ?> F</td>
                                <td style="padding: 15px;"><span class="status-badge <?php echo $r['status']; ?>"><?php echo $r['status']; ?></span></td>
                                <td style="padding: 15px;">
                                    <a href="reservations.php?search=<?php echo $r['id']; ?>" style="color: #4338ca;"><i class="fas fa-eye"></i></a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($reservations)): ?>
                                <tr><td colspan="6" style="padding: 20px; text-align: center; color: #888;">Aucune réservation trouvée.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Payments -->
            <div class="card">
                <h3 style="margin-bottom: 20px;"><i class="fas fa-money-bill-wave" style="color: #15803d;"></i> Paiements</h3>
                <div class="table-responsive">
                    <table style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr style="text-align: left; border-bottom: 2px solid #eee;">
                                <th style="padding: 15px;">Date</th>
                                <th style="padding: 15px;">Réservation</th>
                                <th style="padding: 15px;">Méthode</th>
                                <th style="padding: 15px;">Montant</th>
                                <th style="padding: 15px;">Référence</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($payments as $p): ?>
                            <tr style="border-bottom: 1px solid #f9f9f9;">
                                <td style="padding: 15px;"><?php echo date('d/m/Y H:i', strtotime($p['created_at'])); ?></td>
                                <td style="padding: 15px;">#<?php echo $p['reservation_id']; ?></td>
                                <td style="padding: 15px;"><span class="method-tag"><?php echo strtoupper($p['payment_method']); ?></span></td>
                                <td style="padding: 15px; font-weight: 700; color: #166534;"><?php echo number_format($p['amount'], 0); ?> F</td>
                                <td style="padding: 15px; font-size: 0.85rem; color: #666;"><?php echo htmlspecialchars($p['transaction_ref']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($payments)): ?>
                                <tr><td colspan="5" style="padding: 20px; text-align: center; color: #888;">Aucun paiement trouvé.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="../assets/js/admin.js"></script>
</body>
</html>
