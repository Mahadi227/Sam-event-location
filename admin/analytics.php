<?php
// admin/analytics.php
require_once '../includes/db.php';
require_once '../includes/auth.php';
requireSuperAdmin();

// Advanced Analytics: Branch Performance Comparison
$branches = $pdo->query("SELECT * FROM branches ORDER BY name")->fetchAll();

$branch_stats = [];
foreach ($branches as $b) {
    $id = $b['id'];
    
    // Total Revenue (Completed Reservations)
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(total_price), 0) FROM reservations WHERE branch_id = ? AND status = 'completed'");
    $stmt->execute([$id]);
    $revenue = $stmt->fetchColumn();

    // Total Active Reservations
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM reservations WHERE branch_id = ? AND status IN ('pending', 'approved', 'in_preparation')");
    $stmt->execute([$id]);
    $active_res = $stmt->fetchColumn();

    // Total Items Value
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(price_per_day * quantity_total), 0) FROM items WHERE branch_id = ?");
    $stmt->execute([$id]);
    $inventory_value = $stmt->fetchColumn();

    $branch_stats[] = [
        'name' => $b['name'],
        'revenue' => (float)$revenue,
        'active_res' => (int)$active_res,
        'inventory_value' => (float)$inventory_value
    ];
}

$chart_labels = json_encode(array_column($branch_stats, 'name'));
$chart_revenue = json_encode(array_column($branch_stats, 'revenue'));

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics Comparatif - Sam SuperAdmin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css?v=8">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body style="background: #f4f5f7;">

<div class="admin-mobile-header">
    <div style="font-weight: 800; color: white;">Sam Management</div>
    <button class="admin-hamburger"><i class="fas fa-bars"></i></button>
</div>

<div class="admin-container">
    <div class="sidebar-overlay"></div>
    <div class="admin-sidebar">
        <h2>Sam Management</h2>
        <a href="dashboard.php"><i class="fas fa-th-large"></i> &nbsp; Dashboard</a>
        <a href="analytics.php" class="active"><i class="fas fa-chart-pie"></i> &nbsp; Analytiques</a>
        <a href="items.php"><i class="fas fa-box"></i> &nbsp; Stock & Produits</a>
        <a href="reservations.php"><i class="fas fa-calendar-check"></i> &nbsp; Réservations</a>
        <a href="payments.php"><i class="fas fa-money-bill-wave"></i> &nbsp; Paiements</a>
        <a href="transfers.php"><i class="fas fa-truck-loading"></i> &nbsp; Transferts Stock</a>
        <a href="caisse.php"><i class="fas fa-cash-register"></i> &nbsp; Caisse</a>
        <a href="branches.php"><i class="fas fa-building"></i> &nbsp; Succursales</a>
        <a href="users.php"><i class="fas fa-users-cog"></i> &nbsp; Utilisateurs</a>
        <a href="settings.php"><i class="fas fa-tools"></i> &nbsp; Paramètres</a>
        <a href="../logout.php" style="margin-top: 50px; color: #ef4444;"><i class="fas fa-sign-out-alt"></i> &nbsp; Déconnexion</a>
    </div>

    <div class="main-content">
        <h2 style="margin-bottom: 5px;">Performance Globale par Succursale</h2>
        <p style="color: #666; margin-bottom: 30px;">Comparaison directe des revenus, réserves, et du stock entre agences.</p>

        <!-- Chart Section -->
        <div style="background: white; padding: 25px; border-radius: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); margin-bottom: 30px;">
            <canvas id="branchComparisonChart" height="100"></canvas>
        </div>

        <!-- Metric Table -->
        <div style="background: white; border-radius: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); overflow: hidden;">
            <table style="width: 100%; border-collapse: collapse;">
                <thead style="background: #f8fafc;">
                    <tr>
                        <th style="padding: 20px; text-align: left; color: #4b5563; font-weight: 600;">Succursale</th>
                        <th style="padding: 20px; text-align: right; color: #4b5563; font-weight: 600;">Chiffre d'Affaires (Généré)</th>
                        <th style="padding: 20px; text-align: right; color: #4b5563; font-weight: 600;">Réservations Actives</th>
                        <th style="padding: 20px; text-align: right; color: #4b5563; font-weight: 600;">Valeur du Stock Fixe</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($branch_stats as $stat): ?>
                        <tr style="border-bottom: 1px solid #f1f5f9;">
                            <td style="padding: 20px; font-weight: 700; color: #1e293b;"><?php echo htmlspecialchars($stat['name']); ?></td>
                            <td style="padding: 20px; text-align: right; font-weight: 600; color: #10b981;"><?php echo number_format($stat['revenue'], 0); ?> <?php echo getCurrency(); ?></td>
                            <td style="padding: 20px; text-align: right; font-weight: 600; color: #4f46e5;"><?php echo $stat['active_res']; ?></td>
                            <td style="padding: 20px; text-align: right; font-weight: 600; color: #64748b;"><?php echo number_format($stat['inventory_value'], 0); ?> <?php echo getCurrency(); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
const ctx = document.getElementById('branchComparisonChart').getContext('2d');
new Chart(ctx, {
    type: 'bar',
    data: {
        labels: <?php echo $chart_labels; ?>,
        datasets: [{
            label: 'Chiffre d\'Affaires Global (FCFA)',
            data: <?php echo $chart_revenue; ?>,
            backgroundColor: 'rgba(67, 56, 202, 0.8)',
            borderColor: 'rgba(67, 56, 202, 1)',
            borderWidth: 1,
            borderRadius: 8
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                position: 'top',
            }
        },
        scales: {
            y: {
                beginAtZero: true
            }
        }
    }
});
</script>
<script src="../assets/js/admin.js?v=8"></script>
</body>
</html>
