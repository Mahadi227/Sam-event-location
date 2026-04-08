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

// Chart Data (Last 6 Months)
$chart_labels = [];
$chart_revenue = [];
$chart_reservations = [];
$months_fr = ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Juin', 'Juil', 'Aoû', 'Sep', 'Oct', 'Nov', 'Déc'];

for ($i = 5; $i >= 0; $i--) {
    $ts = mktime(0, 0, 0, date('n') - $i, 1);
    $m = date('m', $ts);
    $y = date('Y', $ts);

    $stmt = $pdo->prepare("SELECT SUM(amount) FROM payments WHERE MONTH(created_at) = ? AND YEAR(created_at) = ?");
    $stmt->execute([$m, $y]);
    $rev = $stmt->fetchColumn() ?? 0;

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM reservations WHERE MONTH(created_at) = ? AND YEAR(created_at) = ? AND status != 'cancelled'");
    $stmt->execute([$m, $y]);
    $res = $stmt->fetchColumn() ?? 0;

    $chart_labels[] = $months_fr[(int) $m - 1] . ' ' . $y;
    $chart_revenue[] = $rev;
    $chart_reservations[] = $res;
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Sam Event</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
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
                <a href="users.php"><i class="fas fa-users-cog"></i> &nbsp; Utilisateurs</a>
                <a href="settings.php"><i class="fas fa-tools"></i> &nbsp; Paramètres</a>
            <?php endif; ?>

            <a href="../logout.php" style="margin-top: 50px; color: #ef4444;"><i class="fas fa-sign-out-alt"></i> &nbsp;
                Déconnexion</a>
        </div>

        <div class="main-content">
            <div
                style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; flex-wrap: wrap; gap: 15px;">
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
            <div style="background: white; padding: 30px; border-radius: 20px; box-shadow: 0 4px 6px rgba(0,0,0,0.02);">
                <h3>Rapport d'Activité</h3>
                <p style="color: #888;">Visualisation des revenus et réservations (6 derniers mois).</p>
                <div style="position: relative; height: 350px; width: 100%;">
                    <canvas id="activityChart"></canvas>
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