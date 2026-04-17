<?php
// receptionist/dashboard.php
require_once '../includes/db.php';
require_once '../includes/auth.php';
requireStaff();

// Get today's reservations
$today = date('Y-m-d');
$stmt = $pdo->prepare("SELECT * FROM reservations WHERE event_date = ? ORDER BY created_at DESC");
$stmt->execute([$today]);
$today_res = $stmt->fetchAll();

// Get pending count
$pending_count = $pdo->query("SELECT COUNT(*) FROM reservations WHERE status = 'pending'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reception - Sam Event</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css?v=2">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>

<body style="background: #f4f5f7;">

    <div class="admin-mobile-header">
        <div style="font-weight: 800; color: white;">Sam Reception</div>
        <button class="admin-hamburger"><i class="fas fa-bars"></i></button>
    </div>

    <div class="admin-container">
        <div class="sidebar-overlay"></div>
        <div class="admin-sidebar">
            <h2 style="color: white; margin-bottom: 30px;">Reception Sam</h2>
            <a href="dashboard.php" class="active"><i class="fas fa-home"></i> &nbsp; Accueil</a>
            <a href="walk_in.php"><i class="fas fa-plus"></i> &nbsp; Nouveau Walk-in</a>
            <a href="reservations.php"><i class="fas fa-list"></i> &nbsp; Reservations</a>
            <a href="calendar.php"><i class="fas fa-calendar-alt"></i> &nbsp; Calendrier</a>
            <a href="caisse.php"><i class="fas fa-cash-register"></i> &nbsp; Caisse (Shift)</a>
        <a href="profile.php"><i class="fas fa-user"></i> &nbsp; Mon Profil</a>
            <a href="../logout.php" style="margin-top: 50px; color: #ef4444;"><i class="fas fa-sign-out-alt"></i> &nbsp; Déconnexion</a>
        </div>

        <div class="main-content">
            <h2>Tableau de Bord Réception</h2>

            <div class="grid-stats">
                <div class="stat-box">
                    <div class="icon-circle" style="background: #e0e7ff; color: #4338ca;"><i
                            class="fas fa-calendar-day"></i></div>
                    <div>
                        <div style="font-size: 0.9rem; color: #666;">Réservations du jour</div>
                        <div style="font-size: 1.5rem; font-weight: 800; color: #1f2937;">
                            <?php echo count($today_res); ?></div>
                    </div>
                </div>
                <div class="stat-box">
                    <div class="icon-circle" style="background: #fff7ed; color: #ea580c;"><i class="fas fa-clock"></i>
                    </div>
                    <div>
                        <div style="font-size: 0.9rem; color: #666;">En attente</div>
                        <div style="font-size: 1.5rem; font-weight: 800; color: #1f2937;"><?php echo $pending_count; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <h3 style="margin-bottom: 20px;">Événements d'Aujourd'hui</h3>
                <?php if (empty($today_res)): ?>
                <p style="color: #999;">Pas d'événements prévus aujourd'hui.</p>
                <?php else: ?>
                <div class="table-responsive">
                    <table style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr style="text-align: left; border-bottom: 1px solid #eee;">
                                <th style="padding: 12px;">Client</th>
                                <th style="padding: 12px;">Lieu</th>
                                <th style="padding: 12px;">Total</th>
                                <th style="padding: 12px;">Statut</th>
                                <th style="padding: 12px;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($today_res as $r): ?>
                            <tr style="border-bottom: 1px solid #fafafa;">
                                <td style="padding: 12px;">
                                    <strong><?php echo htmlspecialchars($r['customer_name']); ?></strong></td>
                                <td style="padding: 12px;"><?php echo htmlspecialchars($r['event_location']); ?></td>
                                <td style="padding: 12px;"><?php echo number_format($r['total_price'], 0); ?> FCFA</td>
                                <td style="padding: 12px;"><span
                                        class="status-tag <?php echo $r['status']; ?>"><?php echo ucfirst($r['status']); ?></span>
                                </td>
                                <td style="padding: 12px;"><a href="manage.php?id=<?php echo $r['id']; ?>"
                                        style="color: var(--primary-blue);">Gérer</a></td>
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