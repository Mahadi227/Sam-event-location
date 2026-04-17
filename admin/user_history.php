<?php
// admin/user_history.php
require_once '../includes/db.php';
require_once '../includes/auth.php';
requireAdmin();

$user_id = $_GET['id'] ?? null;
if (!$user_id) {
    header("Location: users.php");
    exit;
}

// Fetch User
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    die("Utilisateur introuvable.");
}

$is_staff = in_array($user['role'], ['super_admin', 'mini_admin', 'receptionist']);

$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$limit = 10;
$offset = ($page - 1) * $limit;

if ($is_staff) {
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM payments WHERE processed_by = ?");
    $countStmt->execute([$user_id]);
    $total_records = $countStmt->fetchColumn();
    $total_pages = ceil($total_records / $limit);

    // Fetch payments processed by staff
    $stmt = $pdo->prepare("SELECT p.*, r.customer_name FROM payments p JOIN reservations r ON p.reservation_id = r.id WHERE p.processed_by = ? ORDER BY p.created_at DESC LIMIT $limit OFFSET $offset");
    $stmt->execute([$user_id]);
    $payments = $stmt->fetchAll();

    $stmt = $pdo->prepare("SELECT COUNT(*) as total_count, SUM(amount) as total_amount FROM payments WHERE processed_by = ?");
    $stmt->execute([$user_id]);
    $stats = $stmt->fetch();
} else {
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM reservations WHERE user_id = ?");
    $countStmt->execute([$user_id]);
    $total_records = $countStmt->fetchColumn();
    $total_pages = ceil($total_records / $limit);

    // Fetch Reservations made by client
    $stmt = $pdo->prepare("SELECT * FROM reservations WHERE user_id = ? ORDER BY created_at DESC LIMIT $limit OFFSET $offset");
    $stmt->execute([$user_id]);
    $reservations = $stmt->fetchAll();

    // Fetch Payments made by client
    $stmt = $pdo->prepare("SELECT p.*, r.customer_name FROM payments p JOIN reservations r ON p.reservation_id = r.id WHERE r.user_id = ? ORDER BY p.created_at DESC LIMIT $limit OFFSET $offset");
    $stmt->execute([$user_id]);
    $payments = $stmt->fetchAll();
}

$query_string_params = $_GET;
unset($query_string_params['page']);
$base_url = '?';
if (!empty($query_string_params)) $base_url = '?' . http_build_query($query_string_params) . '&';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil Utilisateur - Sam Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css?v=2">
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
        <a href="payments.php"><i class="fas fa-money-bill-wave"></i> &nbsp; Paiements</a>
        <a href="caisse.php"><i class="fas fa-cash-register"></i> &nbsp; Caisse</a>
        <a href="users.php" class="active"><i class="fas fa-users-cog"></i> &nbsp; Utilisateurs</a>
        <a href="settings.php"><i class="fas fa-tools"></i> &nbsp; Paramètres</a>
        <a href="../logout.php" style="margin-top: 50px; color: #ef4444;"><i class="fas fa-sign-out-alt"></i> &nbsp; Déconnexion</a>
    </div>

    <div class="main-content">
        <div style="display: flex; align-items: center; justify-content: space-between; gap: 20px; margin-bottom: 30px; flex-wrap: wrap;">
            <div style="display: flex; align-items: center; gap: 20px;">
                <a href="users.php" style="background: white; width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #333; box-shadow: 0 2px 4px rgba(0,0,0,0.1);"><i class="fas fa-arrow-left"></i></a>
                <div>
                    <h2 style="margin: 0;">Profil de <?php echo htmlspecialchars($user['name']); ?> <span class="role-badge <?php echo $user['role']; ?>" style="font-size: 0.8rem; vertical-align: middle; margin-left: 10px;"><?php echo str_replace('_', ' ', strtoupper($user['role'])); ?></span></h2>
                    <p style="color: #666; margin: 0;"><?php echo htmlspecialchars($user['email']); ?> | <?php echo htmlspecialchars($user['phone'] ?? 'Pas de téléphone'); ?></p>
                </div>
            </div>

            <?php if ($is_staff): ?>
            <div>
                <a href="caisse.php?user_id=<?php echo $user_id; ?>&period=month" class="contact-btn" style="padding: 10px 20px; border: none; cursor: pointer; text-decoration: none;"><i class="fas fa-cash-register"></i> Caisse Mensuelle Utilisateur</a>
            </div>
            <?php endif; ?>
        </div>

        <?php if ($is_staff): ?>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px; margin-bottom: 30px;">
                <div style="background: white; padding: 25px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.03); text-align: center;">
                    <h3 style="color: #6b7280; font-size: 1rem; margin-bottom: 10px; font-weight: 500;">Total Transactions Enregistrées</h3>
                    <h1 style="font-size: 2.5rem; margin: 0; font-weight: 900; color: #111827;"><?php echo $stats['total_count'] ?: 0; ?></h1>
                </div>
                <div style="background: white; padding: 25px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.03); text-align: center; border-bottom: 4px solid #10b981;">
                    <h3 style="color: #6b7280; font-size: 1rem; margin-bottom: 10px; font-weight: 500;">Valeur Globale Encaissée</h3>
                    <h1 style="font-size: 2.5rem; margin: 0; font-weight: 900; color: #10b981;"><?php echo number_format((float)$stats['total_amount'], 0); ?> F</h1>
                </div>
            </div>
        <?php endif; ?>

        <div style="display: grid; grid-template-columns: 1fr; gap: 30px;">
            <?php if (!$is_staff): ?>
            <!-- Reservations -->
            <div class="card">
                <h3 style="margin-bottom: 20px;"><i class="fas fa-calendar-check" style="color: #4338ca;"></i> Réservations</h3>
                <div class="table-responsive">
                    <table style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr style="text-align: left; border-bottom: 2px solid #eee;">
                                <th style="padding: 15px;">ID</th>
                                <th style="padding: 15px;">Date Event</th>
                                <th style="padding: 15px;">Total</th>
                                <th style="padding: 15px;">Payé</th>
                                <th style="padding: 15px;">Statut</th>
                                <th style="padding: 15px;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reservations as $r): ?>
                            <tr style="border-bottom: 1px solid #f9f9f9;">
                                <td style="padding: 15px;">#<?php echo $r['id']; ?></td>
                                <td style="padding: 15px;"><?php echo date('d/m/Y', strtotime($r['event_date'])); ?></td>
                                <td style="padding: 15px; font-weight: 700;"><?php echo number_format($r['total_price'], 0); ?> F</td>
                                <td style="padding: 15px; color: #166534;"><?php echo number_format($r['amount_paid'], 0); ?> F</td>
                                <td style="padding: 15px;"><span class="status-badge <?php echo $r['status']; ?>"><?php echo $r['status']; ?></span></td>
                                <td style="padding: 15px;">
                                    <a href="reservations.php?search=<?php echo $r['id']; ?>" style="color: #4338ca;"><i class="fas fa-eye"></i></a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($reservations)): ?>
                                <tr><td colspan="6" style="padding: 20px; text-align: center; color: #888;">Aucune réservation trouvée.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

            <!-- Payments -->
            <div class="card">
                <h3 style="margin-bottom: 20px;"><i class="fas fa-money-bill-wave" style="color: #15803d;"></i> <?php echo $is_staff ? 'Historique des Encaissements (Shift)' : 'Historique des Paiements'; ?></h3>
                <div class="table-responsive">
                    <table style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr style="text-align: left; border-bottom: 2px solid #eee;">
                                <th style="padding: 15px;">Date</th>
                                <th style="padding: 15px;">Réservation</th>
                                <th style="padding: 15px;">Méthode</th>
                                <th style="padding: 15px;">Montant</th>
                                <th style="padding: 15px;">Référence</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($payments as $p): ?>
                            <tr style="border-bottom: 1px solid #f9f9f9;">
                                <td style="padding: 15px;"><?php echo date('d/m/Y H:i', strtotime($p['created_at'])); ?></td>
                                <td style="padding: 15px;"><a href="manage.php?id=<?php echo $p['reservation_id']; ?>" style="color: var(--primary-blue); font-weight: bold; text-decoration: none;">#<?php echo $p['reservation_id']; ?></a></td>
                                <td style="padding: 15px;"><span class="method-tag"><?php echo strtoupper($p['payment_method']); ?></span></td>
                                <td style="padding: 15px; font-weight: 700; color: #166534;">+ <?php echo number_format($p['amount'], 0); ?> F</td>
                                <td style="padding: 15px; font-size: 0.85rem; color: #666;"><?php echo htmlspecialchars($p['transaction_ref'] ?? ''); ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($payments)): ?>
                                <tr><td colspan="5" style="padding: 20px; text-align: center; color: #888;">Aucun paiement trouvé.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <?php if (isset($total_pages) && $total_pages > 1): ?>
                <div style="margin-top: 20px; display: flex; justify-content: center; gap: 5px; flex-wrap: wrap; padding-bottom: 20px;">
                    <?php if ($page > 1): ?>
                        <a href="<?php echo htmlspecialchars($base_url . 'page=' . ($page - 1)); ?>" style="padding: 8px 12px; border: 1px solid #ddd; border-radius: 5px; text-decoration: none; color: #333; background: white;"><i class="fas fa-chevron-left"></i> Précédent</a>
                    <?php endif; ?>
                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                        <a href="<?php echo htmlspecialchars($base_url . 'page=' . $i); ?>" style="padding: 8px 12px; border: 1px solid #ddd; border-radius: 5px; text-decoration: none; <?php echo $i === $page ? 'background: #03117a; color: white; border-color: #03117a;' : 'background: white; color: #333;'; ?>"><?php echo $i; ?></a>
                    <?php endfor; ?>
                    <?php if ($page < $total_pages): ?>
                        <a href="<?php echo htmlspecialchars($base_url . 'page=' . ($page + 1)); ?>" style="padding: 8px 12px; border: 1px solid #ddd; border-radius: 5px; text-decoration: none; color: #333; background: white;">Suivant <i class="fas fa-chevron-right"></i></a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

            </div>
        </div>
    </div>
</div>

<script src="../assets/js/admin.js"></script>
</body>
</html>
