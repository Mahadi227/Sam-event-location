<?php
// admin/dashboard.php
require_once '../includes/db.php';
require_once '../includes/auth.php';
requireAdmin();

$branchSql = getBranchSqlFilter();
$branchSqlWhere = getBranchSqlFilter() ? "WHERE 1=1 " . getBranchSqlFilter() : "";
$branchSqlWhereR = getBranchSqlFilter('r') ? "WHERE 1=1 " . getBranchSqlFilter('r') : "";

// Monthly revenue
$month = date('m');
$year = date('Y');
$stmt = $pdo->prepare("SELECT SUM(amount) FROM payments p JOIN reservations r ON p.reservation_id = r.id WHERE MONTH(p.created_at) = ? AND YEAR(p.created_at) = ? " . getBranchSqlFilter('r'));
$stmt->execute([$month, $year]);
$monthly_rev = $stmt->fetchColumn() ?? 0;

// Pending payments
$stmt = $pdo->query("SELECT SUM(total_price - amount_paid) FROM reservations WHERE status != 'cancelled' " . getBranchSqlFilter());
$pending_payments = $stmt->fetchColumn() ?? 0;

// Counts
// Note: items are NOT branch specific normally, but reservations are.
$item_count = $pdo->query("SELECT COUNT(*) FROM items")->fetchColumn();
$res_count = $pdo->query("SELECT COUNT(*) FROM reservations WHERE status = 'pending' " . getBranchSqlFilter())->fetchColumn();

// Chart Data (Last 6 Months)
$chart_labels = [];
$chart_revenue = [];
$chart_reservations = [];
$months_fr = ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Juin', 'Juil', 'Aoû', 'Sep', 'Oct', 'Nov', 'Déc'];

for ($i = 5; $i >= 0; $i--) {
    $ts = mktime(0, 0, 0, date('n') - $i, 1);
    $m = date('m', $ts);
    $y = date('Y', $ts);

    $stmt = $pdo->prepare("SELECT SUM(p.amount) FROM payments p JOIN reservations r ON p.reservation_id = r.id WHERE MONTH(p.created_at) = ? AND YEAR(p.created_at) = ? " . getBranchSqlFilter('r'));
    $stmt->execute([$m, $y]);
    $rev = $stmt->fetchColumn() ?? 0;

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM reservations WHERE MONTH(created_at) = ? AND YEAR(created_at) = ? AND status != 'cancelled' " . getBranchSqlFilter());
    $stmt->execute([$m, $y]);
    $res = $stmt->fetchColumn() ?? 0;

    $chart_labels[] = $months_fr[(int) $m - 1] . ' ' . $y;
    $chart_revenue[] = $rev;
    $chart_reservations[] = $res;
}

// Top Products
$top_items_stmt = $pdo->query("SELECT i.name, i.image_url, SUM(ri.quantity) as total_rented FROM reservation_items ri JOIN items i ON ri.item_id = i.id JOIN reservations r ON ri.reservation_id = r.id $branchSqlWhereR GROUP BY ri.item_id ORDER BY total_rented DESC LIMIT 5");
$top_items = $top_items_stmt->fetchAll();

// Recent Payments
$recent_payments_stmt = $pdo->query("SELECT p.amount, p.payment_method, p.created_at, r.customer_name FROM payments p JOIN reservations r ON p.reservation_id = r.id $branchSqlWhereR ORDER BY p.created_at DESC LIMIT 5");
$recent_payments = $recent_payments_stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Sam Event</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css?v=2">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
            <a href="calendar.php" class="active"><i class="fas fa-calendar-alt"></i> &nbsp; Calendrier</a>

            <a href="items.php"><i class="fas fa-box"></i> &nbsp; Stock & Produits</a>
            <a href="reservations.php"><i class="fas fa-calendar-check"></i> &nbsp; Réservations</a>
            <a href="payments.php"><i class="fas fa-money-bill-wave"></i> &nbsp; Paiements</a>
            <a href="caisse.php"><i class="fas fa-cash-register"></i> &nbsp; Caisse</a>
            <a href="profile.php" class="active"><i class="fas fa-user"></i> &nbsp; Mon Profil</a>


            <?php if (hasRole('super_admin')): ?>
                <div
                    style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #e214a4ff; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 1px;">
                    Super Admin</div>
                <a href="branches.php"><i class="fas fa-building"></i> &nbsp; Succursales</a>
            <?php endif; ?>
            <?php if (hasRole('super_admin') || hasRole('mini_admin')): ?>
                <a href="users.php"><i class="fas fa-users-cog"></i> &nbsp; <?php echo hasRole('super_admin') ? 'Utilisateurs' : 'Personnel'; ?></a>
            <?php endif; ?>
            <?php if (hasRole('super_admin')): ?>
                <a href="settings.php"><i class="fas fa-tools"></i> &nbsp; Paramètres</a>
            <?php endif; ?>

            <a href="../logout.php" style="margin-top: 50px; color: #ef4444;"><i class="fas fa-sign-out-alt"></i> &nbsp;
                Déconnexion</a>
        </div>

        <div class="main-content">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; flex-wrap: wrap; gap: 15px;">
            <div>
                <h1 style="margin: 0; font-size: 1.8rem; color: #1a1c23;">Dashboard</h1>
                <p style="color: #666; margin: 5px 0 0;">Bonjour, <?php echo htmlspecialchars($_SESSION['name'] ?? 'Admin'); ?> ! Voici les statistiques récentes.</p>
            </div>
            
            <div style="display: flex; gap: 15px; align-items: center;">
                <?php if(hasRole('super_admin')): ?>
                <div style="background: white; border-radius: 8px; border: 1px solid #e5e7eb; padding: 2px 10px; display: flex; align-items: center;">
                    <i class="fas fa-building" style="color: #6b7280; margin-right: 10px;"></i>
                    <select onchange="window.location.href='?switch_branch=' + this.value" style="border: none; outline: none; padding: 8px 0; font-family: inherit; font-size: 0.95rem; color: #1f2937; background: transparent; cursor: pointer;">
                        <option value="all" <?php echo getActiveBranch() === null ? 'selected' : ''; ?>>Toutes les succursales</option>
                        <?php 
                        $branches_list = $pdo->query("SELECT * FROM branches ORDER BY name ASC")->fetchAll();
                        foreach($branches_list as $b): ?>
                            <option value="<?php echo $b['id']; ?>" <?php echo getActiveBranch() == $b['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($b['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                
                <a href="create_reservation.php" class="contact-btn"><i class="fas fa-plus"></i> Nouvelle Réservation</a>
            </div>
        </div>

            <div class="grid-stats">
                <div class="stat-box">
                    <div class="icon-circle blue"><i class="fas fa-wallet"></i></div>
                    <div>
                        <p style="color: #666; margin: 0; font-size: 0.85rem;">Revenu (Ce mois)</p>
                        <h3 style="margin: 5px 0 0; font-size: 1.4rem;"><?php echo number_format($monthly_rev, 0); ?> F
                        </h3>
                    </div>
                </div>
                <div class="stat-box">
                    <div class="icon-circle orange"><i class="fas fa-exclamation-triangle"></i></div>
                    <div>
                        <p style="color: #666; margin: 0; font-size: 0.85rem;">Paiements Attentes</p>
                        <h3 style="margin: 5px 0 0; font-size: 1.4rem; color: #9a3412;">
                            <?php echo number_format($pending_payments, 0); ?> F
                        </h3>
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

            <!-- Monthly Report Chart -->
            <div style="background: white; padding: 30px; border-radius: 20px; box-shadow: 0 4px 6px rgba(0,0,0,0.02); margin-bottom: 30px;">
                <h3>Rapport d'Activité</h3>
                <p style="color: #888;">Visualisation des revenus et réservations (6 derniers mois).</p>
                <div style="position: relative; height: 350px; width: 100%;">
                    <canvas id="activityChart"></canvas>
                </div>
            </div>

            <!-- Bottom Dashboard Widgets -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 30px; margin-bottom: 40px;">
                <!-- Top Products -->
                <div style="background: white; padding: 30px; border-radius: 20px; box-shadow: 0 4px 6px rgba(0,0,0,0.02);">
                    <h3>Top Produits Réservés</h3>
                    <p style="color: #888; margin-bottom: 20px;">Les articles les plus demandés.</p>
                    <div style="display: flex; flex-direction: column; gap: 15px;">
                        <?php foreach ($top_items as $item): ?>
                        <div style="display: flex; align-items: center; gap: 15px; padding-bottom: 15px; border-bottom: 1px solid #eee;">
                            <?php if (!empty($item['image_url'])): ?>
                                <img src="../<?php echo htmlspecialchars($item['image_url']); ?>" style="width: 50px; height: 50px; border-radius: 10px; object-fit: cover;">
                            <?php else: ?>
                                <div style="width: 50px; height: 50px; background: #f0f0f0; border-radius: 10px; display: flex; align-items: center; justify-content: center; color: #aaa;"><i class="fas fa-box"></i></div>
                            <?php endif; ?>
                            <div style="flex: 1;">
                                <strong style="color: var(--dark-blue); display: block;"><?php echo htmlspecialchars($item['name']); ?></strong>
                            </div>
                            <div style="font-weight: 800; color: var(--accent-gold);">
                                <?php echo $item['total_rented']; ?>x
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php if (empty($top_items)): ?>
                            <p style="color: #999;">Aucune donnée disponible.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Recent Payments -->
                <div style="background: white; padding: 30px; border-radius: 20px; box-shadow: 0 4px 6px rgba(0,0,0,0.02);">
                    <h3>Transactions Récentes</h3>
                    <p style="color: #888; margin-bottom: 20px;">Les 5 derniers paiements reçus.</p>
                    <div style="display: flex; flex-direction: column; gap: 15px;">
                        <?php foreach ($recent_payments as $pay): ?>
                        <div style="display: flex; align-items: center; justify-content: space-between; padding-bottom: 15px; border-bottom: 1px solid #eee;">
                            <div style="display: flex; align-items: center; gap: 15px;">
                                <div style="width: 45px; height: 45px; background: #e0f2fe; color: #0369a1; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.2rem;">
                                    <?php 
                                        if ($pay['payment_method'] === 'cash') echo '<i class="fas fa-money-bill-wave"></i>';
                                        elseif ($pay['payment_method'] === 'card') echo '<i class="fas fa-credit-card"></i>';
                                        else echo '<i class="fas fa-mobile-alt"></i>';
                                    ?>
                                </div>
                                <div>
                                    <strong style="color: var(--dark-blue); display: block;"><?php echo htmlspecialchars($pay['customer_name']); ?></strong>
                                    <small style="color: #888;"><?php echo date('d/m/Y H:i', strtotime($pay['created_at'])); ?> • <?php echo strtoupper($pay['payment_method']); ?></small>
                                </div>
                            </div>
                            <div style="font-weight: 800; color: #15803d;">
                                +<?php echo number_format($pay['amount'], 0); ?> F
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php if (empty($recent_payments)): ?>
                            <p style="color: #999;">Aucun paiement récent.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <script src="../assets/js/admin.js"></script>
        <script>
            const ctx = document.getElementById('activityChart').getContext('2d');
            const activityChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode($chart_labels); ?>,
                    datasets: [
                        {
                            label: 'Revenus (FCFA)',
                            data: <?php echo json_encode($chart_revenue); ?>,
                            backgroundColor: '#bfa100',
                            borderRadius: 6,
                            yAxisID: 'y'
                        },
                        {
                            label: 'Réservations',
                            data: <?php echo json_encode($chart_reservations); ?>,
                            backgroundColor: '#1e3a8a',
                            borderRadius: 6,
                            type: 'line',
                            borderColor: '#1e3a8a',
                            borderWidth: 3,
                            tension: 0.3,
                            yAxisID: 'y1'
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            type: 'linear',
                            display: true,
                            position: 'left',
                            title: {
                                display: true,
                                text: 'Revenus (FCFA)'
                            }
                        },
                        y1: {
                            type: 'linear',
                            display: true,
                            position: 'right',
                            grid: { drawOnChartArea: false },
                            title: {
                                display: true,
                                text: 'Nombre de Réservations'
                            },
                            ticks: { precision: 0 }
                        }
                    },
                    plugins: {
                        legend: { position: 'top' }
                    }
                }
            });
        </script>
</body>

</html>