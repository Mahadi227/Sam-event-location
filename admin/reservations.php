<?php
// admin/reservations.php
require_once '../includes/db.php';
require_once '../includes/auth.php';
requireAdmin();

// Handle deletion
if (isset($_GET['delete']) && hasRole('super_admin')) {
    $stmt = $pdo->prepare("DELETE FROM reservations WHERE id = ?");
    $stmt->execute([$_GET['delete']]);
    header("Location: reservations.php");
    exit;
}

// Build Filter Query
$where = [];
$params = [];

if (!empty($_GET['status'])) {
    $where[] = "status = ?";
    $params[] = $_GET['status'];
}

if (!empty($_GET['date'])) {
    $where[] = "event_date = ?";
    $params[] = $_GET['date'];
}

if (!empty($_GET['search'])) {
    $where[] = "(customer_name LIKE ? OR customer_phone LIKE ?)";
    $params[] = "%".$_GET['search']."%";
    $params[] = "%".$_GET['search']."%";
}

$whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";
$reservations = $pdo->prepare("SELECT * FROM reservations $whereClause ORDER BY created_at DESC");
$reservations->execute($params);
$reservations = $reservations->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Reservations - Sam Admin</title>
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
        <a href="reservations.php" class="active"><i class="fas fa-calendar-check"></i> &nbsp; Réservations</a>
        <a href="payments.php"><i class="fas fa-money-bill-wave"></i> &nbsp; Paiements</a>
        <?php if (hasRole('super_admin')): ?>
            <a href="users.php"><i class="fas fa-users-cog"></i> &nbsp; Utilisateurs</a>
            <a href="settings.php"><i class="fas fa-tools"></i> &nbsp; Paramètres</a>
        <?php endif; ?>
        <a href="../logout.php" style="margin-top: 50px; color: #ef4444;"><i class="fas fa-sign-out-alt"></i> &nbsp; Déconnexion</a>
    </div>

    <div class="main-content">
        <h2>Toutes les Réservations</h2>
        <p style="color: #666; margin-bottom: 30px;">Gestion centralisée de toutes les réservations du système.</p>

        <!-- Filter Bar -->
        <div class="card" style="margin-bottom: 30px; padding: 20px;">
            <form method="GET" style="display: flex; gap: 15px; flex-wrap: wrap; align-items: flex-end;">
                <div class="form-group" style="flex: 1; min-width: 200px;">
                    <label style="font-size: 0.85rem; font-weight: 700; color: #666;">Recherche (Nom/Tel)</label>
                    <input type="text" name="search" value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>" placeholder="Rechercher..." style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid #ddd;">
                </div>
                <div class="form-group" style="width: 180px;">
                    <label style="font-size: 0.85rem; font-weight: 700; color: #666;">Statut</label>
                    <select name="status" style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid #ddd;">
                        <option value="">Tous les statuts</option>
                        <option value="pending" <?php echo ($_GET['status'] ?? '') == 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="approved" <?php echo ($_GET['status'] ?? '') == 'approved' ? 'selected' : ''; ?>>Approved</option>
                        <option value="in_preparation" <?php echo ($_GET['status'] ?? '') == 'in_preparation' ? 'selected' : ''; ?>>In Preparation</option>
                        <option value="completed" <?php echo ($_GET['status'] ?? '') == 'completed' ? 'selected' : ''; ?>>Completed</option>
                        <option value="cancelled" <?php echo ($_GET['status'] ?? '') == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                    </select>
                </div>
                <div class="form-group" style="width: 180px;">
                    <label style="font-size: 0.85rem; font-weight: 700; color: #666;">Date Event</label>
                    <input type="date" name="date" value="<?php echo htmlspecialchars($_GET['date'] ?? ''); ?>" style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid #ddd;">
                </div>
                <div style="display: flex; gap: 10px;">
                    <button type="submit" class="contact-btn" style="padding: 10px 20px; border: none; cursor: pointer;">Filtrer</button>
                    <a href="reservations.php" class="btn-reserve" style="background: #888; text-decoration: none; padding: 10px 15px; border-radius: 8px;">Reset</a>
                </div>
            </form>
        </div>

        <div class="card">
            <div class="table-responsive">
                <table style="width: 100%; border-collapse: collapse; min-width: 600px;">
            <thead>
                <tr style="text-align: left; border-bottom: 2px solid #eee;">
                    <th style="padding: 15px;">Date</th>
                    <th style="padding: 15px;">Client</th>
                    <th style="padding: 15px;">Total</th>
                    <th style="padding: 15px;">Payé</th>
                    <th style="padding: 15px;">Statut</th>
                    <th style="padding: 15px;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($reservations as $r): ?>
                <tr style="border-bottom: 1px solid #f9f9f9;">
                    <td style="padding: 15px;"><?php echo date('d/m/y', strtotime($r['event_date'])); ?></td>
                    <td style="padding: 15px;">
                        <strong><?php echo htmlspecialchars($r['customer_name']); ?></strong><br>
                        <small><?php echo htmlspecialchars($r['customer_phone']); ?></small>
                    </td>
                    <td style="padding: 15px;"><?php echo number_format($r['total_price'], 0); ?> F</td>
                    <td style="padding: 15px;"><?php echo number_format($r['amount_paid'], 0); ?> F</td>
                    <td style="padding: 15px;"><span class="status-badge <?php echo $r['status']; ?>"><?php echo strtoupper($r['status']); ?></span></td>
                    <td style="padding: 15px;">
                        <a href="../receptionist/manage.php?id=<?php echo $r['id']; ?>" style="color: #4338ca; margin-right: 15px;"><i class="fas fa-eye"></i></a>
                        <?php if (hasRole('super_admin')): ?>
                            <a href="?delete=<?php echo $r['id']; ?>" onclick="return confirm('Supprimer définitivement ?')" style="color: #ef4444;"><i class="fas fa-trash"></i></a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script src="../assets/js/admin.js"></script>
</body>
</html>
