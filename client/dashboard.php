<?php
// client/dashboard.php
require_once '../includes/db.php';
require_once '../includes/auth.php';
requireClient();

$user_id = $_SESSION['user_id'];

// Get counts
$stmt = $pdo->prepare("SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved
FROM reservations WHERE user_id = ?");
$stmt->execute([$user_id]);
$stats = $stmt->fetch();

// Recent reservations
$stmt = $pdo->prepare("SELECT * FROM reservations WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
$stmt->execute([$user_id]);
$recent = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon Espace - Sam Event</title>
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
        <a href="dashboard.php" class="active"><i class="fas fa-home"></i> &nbsp; Tableau de bord</a>
        <a href="../booking.php"><i class="fas fa-calendar-plus"></i> &nbsp; Réserver</a>
        <a href="history.php"><i class="fas fa-history"></i> &nbsp; Mes Réservations</a>
        <a href="../logout.php" style="margin-top: 50px; color: #ef4444;"><i class="fas fa-sign-out-alt"></i> &nbsp; Déconnexion</a>
    </div>

    <div class="main-content">
        <h2 style="margin-bottom: 25px;">Bonjour, <?php echo htmlspecialchars($_SESSION['name']); ?> 👋</h2>

        <div class="grid-stats">
            <div class="stat-box">
                <div class="icon-circle" style="background: #eef2ff; color: #4338ca;"><i class="fas fa-calendar-alt"></i></div>
                <div>
                    <div style="font-size: 0.9rem; color: #666;">Total Réservations</div>
                    <div style="font-size: 1.5rem; font-weight: 800;"><?php echo $stats['total'] ?? 0; ?></div>
                </div>
            </div>
            <div class="stat-box">
                <div class="icon-circle" style="background: #fff7ed; color: #ea580c;"><i class="fas fa-clock"></i></div>
                <div>
                    <div style="font-size: 0.9rem; color: #666;">En Attente</div>
                    <div style="font-size: 1.5rem; font-weight: 800;"><?php echo $stats['pending'] ?? 0; ?></div>
                </div>
            </div>
            <div class="stat-box">
                <div class="icon-circle" style="background: #fefce8; color: #b45309;"><i class="fas fa-check-circle"></i></div>
                <div>
                    <div style="font-size: 0.9rem; color: #666;">Confirmées</div>
                    <div style="font-size: 1.5rem; font-weight: 800;"><?php echo $stats['approved'] ?? 0; ?></div>
                </div>
            </div>
        </div>

        <div class="card">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
                <h3 style="margin: 0;">Réservations Récentes</h3>
                <a href="history.php" class="contact-btn" style="padding: 8px 20px; font-size: 0.9rem;">Voir tout</a>
            </div>

            <?php if (empty($recent)): ?>
                <p style="text-align: center; color: #888; padding: 40px 0;">Vous n'avez pas encore de réservations.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr style="text-align: left; border-bottom: 2px solid #eee;">
                                <th style="padding: 15px;">Date</th>
                                <th style="padding: 15px;">Lieu</th>
                                <th style="padding: 15px;">Total</th>
                                <th style="padding: 15px;">Statut</th>
                                <th style="padding: 15px;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent as $res): ?>
                            <tr style="border-bottom: 1px solid #eee;">
                                <td style="padding: 15px;"><?php echo date('d/m/Y', strtotime($res['event_date'])); ?></td>
                                <td style="padding: 15px;"><?php echo htmlspecialchars($res['event_location']); ?></td>
                                <td style="padding: 15px; font-weight: 700; color: #1f2937;"><?php echo number_format($res['total_price'], 0); ?> F</td>
                                <td style="padding: 15px;">
                                    <span class="status-badge <?php echo $res['status']; ?>">
                                        <?php echo ucfirst($res['status']); ?>
                                    </span>
                                </td>
                                <td style="padding: 15px;">
                                    <a href="details.php?id=<?php echo $res['id']; ?>" style="color: var(--primary-blue); font-weight: 600; text-decoration: none;">Détails</a>
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
