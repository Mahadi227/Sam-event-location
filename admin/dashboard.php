<?php
// admin/dashboard.php
require_once '../includes/db.php';
require_once '../includes/auth.php';
requireAdmin();

// Monthly revenue
$month = date('m');
$year = date('Y');
$stmt = $pdo->prepare("SELECT SUM(amount) FROM payments WHERE MONTH(created_at) = ? AND YEAR(created_at) = ?");
$stmt->execute([$month, $year]);
$monthly_rev = $stmt->fetchColumn() ?? 0;

// Pending payments
$stmt = $pdo->query("SELECT SUM(total_price - amount_paid) FROM reservations WHERE status != 'cancelled'");
$pending_payments = $stmt->fetchColumn() ?? 0;

// Counts
$item_count = $pdo->query("SELECT COUNT(*) FROM items")->fetchColumn();
$res_count = $pdo->query("SELECT COUNT(*) FROM reservations WHERE status = 'pending'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard - Sam Event</title>
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
        <a href="dashboard.php" class="active"><i class="fas fa-th-large"></i> &nbsp; Dashboard</a>
        <a href="items.php"><i class="fas fa-box"></i> &nbsp; Stock & Produits</a>
        <a href="reservations.php"><i class="fas fa-calendar-check"></i> &nbsp; Réservations</a>
        <a href="payments.php"><i class="fas fa-money-bill-wave"></i> &nbsp; Paiements</a>
        
        <?php if (hasRole('super_admin')): ?>
            <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #333; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 1px;">Super Admin</div>
            <a href="users.php"><i class="fas fa-users-cog"></i> &nbsp; Utilisateurs</a>
            <a href="settings.php"><i class="fas fa-tools"></i> &nbsp; Paramètres</a>
        <?php endif; ?>

        <a href="../logout.php" style="margin-top: 50px; color: #ef4444;"><i class="fas fa-sign-out-alt"></i> &nbsp; Déconnexion</a>
    </div>

    <div class="main-content">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; flex-wrap: wrap; gap: 15px;">
            <div>
                <h1 style="margin: 0; font-size: 1.8rem; color: #1a1c23;">Dashboard</h1>
                <p style="color: #666; margin: 5px 0 0;">Bienvenue sur votre espace de gestion</p>
            </div>
            <div style="color: #666; font-weight: 600;"><?php echo date('d F Y'); ?></div>
        </div>

    <div class="grid-stats">
        <div class="stat-box">
            <div class="icon-circle blue"><i class="fas fa-wallet"></i></div>
            <div>
                <p style="color: #666; margin: 0; font-size: 0.85rem;">Revenu (Ce mois)</p>
                <h3 style="margin: 5px 0 0; font-size: 1.4rem;"><?php echo number_format($monthly_rev, 0); ?> F</h3>
            </div>
        </div>
        <div class="stat-box">
            <div class="icon-circle orange"><i class="fas fa-exclamation-triangle"></i></div>
            <div>
                <p style="color: #666; margin: 0; font-size: 0.85rem;">Paiements Attentes</p>
                <h3 style="margin: 5px 0 0; font-size: 1.4rem; color: #9a3412;"><?php echo number_format($pending_payments, 0); ?> F</h3>
            </div>
        </div>
        <div class="stat-box">
            <div class="icon-circle purple"><i class="fas fa-boxes"></i></div>
            <div>
                <p style="color: #666; margin: 0; font-size: 0.85rem;">Total Produits</p>
                <h3 style="margin: 5px 0 0; font-size: 1.4rem;"><?php echo $item_count; ?></h3>
            </div>
        </div>
        <div class="stat-box">
            <div class="icon-circle green"><i class="fas fa-clock"></i></div>
            <div>
                <p style="color: #666; margin: 0; font-size: 0.85rem;">Réservations Pending</p>
                <h3 style="margin: 5px 0 0; font-size: 1.4rem; color: #15803d;"><?php echo $res_count; ?></h3>
            </div>
        </div>
    </div>

    <!-- Monthly Report Chart Placeholder or List -->
    <div style="background: white; padding: 30px; border-radius: 20px; box-shadow: 0 4px 6px rgba(0,0,0,0.02);">
        <h3>Rapport d'Activité</h3>
        <p style="color: #888;">Visualisation des revenus et réservations par mois.</p>
        <div style="height: 200px; background: #f9fafb; border: 2px dashed #e5e7eb; border-radius: 15px; display: flex; align-items: center; justify-content: center; color: #aaa;">
            [ Graphique Analytique ]
        </div>
    </div>
</div>

<script src="../assets/js/admin.js"></script>
</body>
</html>
