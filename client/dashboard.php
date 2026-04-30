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
                <div style="display: flex; flex-direction: column; gap: 15px;">
                    <?php foreach ($recent as $res): ?>
                        <div style="background: white; border: 1px solid #f1f5f9; border-radius: 12px; padding: 20px; display: flex; justify-content: space-between; align-items: center; transition: all 0.3s; box-shadow: 0 2px 8px rgba(0,0,0,0.02);" onmouseover="this.style.boxShadow='0 8px 20px rgba(0,0,0,0.06)'; this.style.borderColor='#e2e8f0';" onmouseout="this.style.boxShadow='0 2px 8px rgba(0,0,0,0.02)'; this.style.borderColor='#f1f5f9';">
                            <div style="display: flex; gap: 20px; align-items: center;">
                                <div style="width: 50px; height: 50px; background: #f8fafc; border-radius: 12px; display: flex; align-items: center; justify-content: center; color: var(--primary-blue); font-size: 1.5rem;">
                                    <i class="fas fa-calendar-day"></i>
                                </div>
                                <div>
                                    <div style="font-weight: 800; color: #1e293b; font-size: 1.1rem; margin-bottom: 5px;">
                                        Evénement du <?php echo date('d/m/Y', strtotime($res['event_date'])); ?>
                                    </div>
                                    <div style="color: #64748b; font-size: 0.9rem; display: flex; align-items: center; gap: 8px;">
                                        <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($res['event_location']); ?>
                                        <span style="color: #cbd5e1;">|</span>
                                        <i class="fas fa-clock"></i> <?php echo htmlspecialchars(($res['duration_days'] ?? 1) . ' jour(s)'); ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div style="display: flex; align-items: center; gap: 25px;">
                                <div style="text-align: right;">
                                    <div style="font-weight: 800; color: var(--primary-blue); font-size: 1.2rem;">
                                        <?php echo number_format($res['total_price'], 0); ?> F
                                    </div>
                                    <?php 
                                        $statusConfig = [
                                            'pending' => ['color' => '#b45309', 'bg' => '#fefce8', 'label' => 'En attente'],
                                            'approved' => ['color' => '#1d4ed8', 'bg' => '#eff6ff', 'label' => 'Approuvé'],
                                            'in_preparation' => ['color' => '#6d28d9', 'bg' => '#f5f3ff', 'label' => 'En préparation'],
                                            'ready' => ['color' => '#0f766e', 'bg' => '#f0fdfa', 'label' => 'Prêt'],
                                            'completed' => ['color' => '#15803d', 'bg' => '#f0fdf4', 'label' => 'Terminé'],
                                            'cancelled' => ['color' => '#b91c1c', 'bg' => '#fef2f2', 'label' => 'Annulé']
                                        ];
                                        $s = $statusConfig[$res['status']] ?? $statusConfig['pending'];
                                    ?>
                                    <span style="background: <?php echo $s['bg']; ?>; color: <?php echo $s['color']; ?>; padding: 4px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 700; margin-top: 5px; display: inline-block;">
                                        <?php echo $s['label']; ?>
                                    </span>
                                </div>
                                <a href="details.php?id=<?php echo $res['id']; ?>" class="contact-btn" style="padding: 10px 15px; border-radius: 8px; font-size: 0.9rem; background: #f8fafc; color: var(--primary-blue); border: 1px solid #e2e8f0;" onmouseover="this.style.background='var(--primary-blue)'; this.style.color='white';" onmouseout="this.style.background='#f8fafc'; this.style.color='var(--primary-blue)';">
                                    Détails <i class="fas fa-arrow-right"></i>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="../assets/js/admin.js?v=7"></script>
</body>
</html>
