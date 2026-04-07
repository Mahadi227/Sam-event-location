<?php
// admin/analytics.php
require_once '../includes/db.php';
require_once '../includes/auth.php';
requireSuperAdmin();

// Advanced Analytics
// Most rented items
$most_rented = $pdo->query("SELECT i.name, SUM(ri.quantity) as total_qty FROM reservation_items ri JOIN items i ON ri.item_id = i.id GROUP BY ri.item_id ORDER BY total_qty DESC LIMIT 5")->fetchAll();

// Revenue by month
$revenue_by_month = $pdo->query("SELECT MONTH(created_at) as month, SUM(amount) as total FROM payments WHERE YEAR(created_at) = YEAR(CURRENT_DATE) GROUP BY MONTH(created_at)")->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Analytics - Sam SuperAdmin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .admin-sidebar { width: 260px; background: #1a1c23; height: 100vh; position: fixed; color: #8a8b9f; padding: 25px; }
        .admin-sidebar a { color: #8a8b9f; text-decoration: none; display: block; padding: 12px; border-radius: 10px; margin-bottom: 5px; }
        .admin-sidebar a.active { background: #2d2f39; color: white; }
        .main-content { margin-left: 260px; padding: 40px; background: #f4f5f7; min-height: 100vh; }
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 30px; }
        .card { background: white; padding: 30px; border-radius: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
    </style>
</head>
<body>

<div class="admin-sidebar">
    <h2>Sam Management</h2>
    <a href="dashboard.php"><i class="fas fa-th-large"></i> &nbsp; Dashboard</a>
    <a href="items.php"><i class="fas fa-box"></i> &nbsp; Stock & Produits</a>
    <a href="reservations.php"><i class="fas fa-calendar-check"></i> &nbsp; Réservations</a>
    <a href="payments.php"><i class="fas fa-money-bill-wave"></i> &nbsp; Paiements</a>
    <a href="caisse.php"><i class="fas fa-cash-register"></i> &nbsp; Caisse</a>
    <a href="users.php"><i class="fas fa-users-cog"></i> &nbsp; Utilisateurs</a>
    <a href="settings.php"><i class="fas fa-tools"></i> &nbsp; Paramètres</a>
    <a href="../logout.php" style="margin-top: 50px; color: #ef4444;"><i class="fas fa-sign-out-alt"></i> &nbsp; Déconnexion</a>
</div>

<div class="main-content">
    <h2>Analyses Avancées</h2>
    <p style="color: #666; margin-bottom: 30px;">Performances du système et statistiques d'utilisation.</p>

    <div class="grid-2">
        <div class="card">
            <h3>Produits les plus loués</h3>
            <ul style="list-style: none; padding: 0; margin-top: 20px;">
                <?php foreach ($most_rented as $item): ?>
                    <li style="padding: 12px 0; border-bottom: 1px solid #eee; display: flex; justify-content: space-between;">
                        <span><?php echo htmlspecialchars($item['name']); ?></span>
                        <span style="font-weight: 800; color: var(--primary-blue);"><?php echo $item['total_qty']; ?> fois</span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <div class="card">
            <h3>Revenus Mensuels (Année en cours)</h3>
            <div style="margin-top: 20px;">
                <?php 
                $months = ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Juin', 'Juil', 'Août', 'Sep', 'Oct', 'Nov', 'Déc'];
                foreach ($revenue_by_month as $rm): ?>
                    <div style="margin-bottom: 15px;">
                        <div style="display: flex; justify-content: space-between; font-size: 0.85rem; margin-bottom: 5px;">
                            <span><?php echo $months[$rm['month']-1]; ?></span>
                            <span><?php echo number_format($rm['total'], 0); ?> F</span>
                        </div>
                        <div style="width: 100%; height: 8px; background: #eee; border-radius: 4px;">
                            <div style="width: <?php echo min(100, $rm['total']/1000000*100); ?>%; height: 100%; background: var(--secondary-orange); border-radius: 4px;"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

</body>
</html>

