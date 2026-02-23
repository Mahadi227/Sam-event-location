<?php
// admin/payments.php
require_once '../includes/db.php';
require_once '../includes/auth.php';
requireAdmin();

$where = [];
$params = [];

if (!empty($_GET['search'])) {
    $where[] = "(r.customer_name LIKE ? OR p.transaction_ref LIKE ?)";
    $params[] = "%".$_GET['search']."%";
    $params[] = "%".$_GET['search']."%";
}
if (!empty($_GET['method'])) {
    $where[] = "p.payment_method = ?";
    $params[] = $_GET['method'];
}
if (!empty($_GET['date'])) {
    $where[] = "DATE(p.created_at) = ?";
    $params[] = $_GET['date'];
}

$whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";
$payments = $pdo->prepare("SELECT p.*, r.customer_name FROM payments p JOIN reservations r ON p.reservation_id = r.id $whereClause ORDER BY p.created_at DESC");
$payments->execute($params);
$payments = $payments->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Paiements - Sam Admin</title>
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
        <a href="payments.php" class="active"><i class="fas fa-money-bill-wave"></i> &nbsp; Paiements</a>
        <?php if (hasRole('super_admin')): ?>
            <a href="users.php"><i class="fas fa-users-cog"></i> &nbsp; Utilisateurs</a>
            <a href="settings.php"><i class="fas fa-tools"></i> &nbsp; Paramètres</a>
        <?php endif; ?>
        <a href="../logout.php" style="margin-top: 50px; color: #ef4444;"><i class="fas fa-sign-out-alt"></i> &nbsp; Déconnexion</a>
    </div>

    <div class="main-content">
        <h2>Journal des Paiements</h2>
    <p style="color: #666; margin-bottom: 30px;">Historique complet des transactions financières.</p>

    <!-- Filter Bar -->
    <div class="card" style="margin-bottom: 25px; padding: 20px;">
        <form method="GET" style="display: flex; gap: 15px; flex-wrap: wrap; align-items: flex-end;">
            <div class="form-group" style="flex: 1; min-width: 200px;">
                <label style="font-size: 0.85rem; font-weight: 700; color: #666;">Recherche</label>
                <input type="text" name="search" value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>" placeholder="Client ou Réf..." style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid #ddd;">
            </div>
            <div class="form-group" style="width: 180px;">
                <label style="font-size: 0.85rem; font-weight: 700; color: #666;">Méthode</label>
                <select name="method" style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid #ddd;">
                    <option value="">Toutes</option>
                    <option value="cash" <?php echo ($_GET['method'] ?? '') == 'cash' ? 'selected' : ''; ?>>CASH</option>
                    <option value="orange_money" <?php echo ($_GET['method'] ?? '') == 'orange_money' ? 'selected' : ''; ?>>Orange Money</option>
                    <option value="moov_money" <?php echo ($_GET['method'] ?? '') == 'moov_money' ? 'selected' : ''; ?>>Moov Money</option>
                    <option value="card" <?php echo ($_GET['method'] ?? '') == 'card' ? 'selected' : ''; ?>>Card</option>
                </select>
            </div>
            <div class="form-group" style="width: 180px;">
                <label style="font-size: 0.85rem; font-weight: 700; color: #666;">Date</label>
                <input type="date" name="date" value="<?php echo htmlspecialchars($_GET['date'] ?? ''); ?>" style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid #ddd;">
            </div>
            <div style="display: flex; gap: 10px;">
                <button type="submit" class="contact-btn" style="padding: 10px 20px; border: none; cursor: pointer;">Filtrer</button>
                <a href="payments.php" class="btn-reserve" style="background: #888; text-decoration: none; padding: 10px 15px; border-radius: 8px;">Reset</a>
            </div>
        </form>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table style="width: 100%; border-collapse: collapse; min-width: 800px;">
                <thead>
                    <tr style="text-align: left; border-bottom: 2px solid #eee;">
                        <th style="padding: 15px;">Date</th>
                        <th style="padding: 15px;">Réservation</th>
                        <th style="padding: 15px;">Client</th>
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
                        <td style="padding: 15px;"><?php echo htmlspecialchars($p['customer_name']); ?></td>
                        <td style="padding: 15px;"><span class="method-tag"><?php echo strtoupper($p['payment_method']); ?></span></td>
                        <td style="padding: 15px; font-weight: 700; color: #166534;"><?php echo number_format($p['amount'], 0); ?> F</td>
                        <td style="padding: 15px; font-size: 0.85rem; color: #666;"><?php echo htmlspecialchars($p['transaction_ref']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="../assets/js/admin.js"></script>
</body>
</html>
