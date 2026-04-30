<?php
// receptionist/reservations.php
require_once '../includes/db.php';
require_once '../includes/auth.php';
requireStaff();
$branchSql = getBranchSqlFilter();

// Filter logic
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
    $where[] = "(customer_name LIKE ? OR customer_phone LIKE ? OR id LIKE ?)";
    $params[] = "%".$_GET['search']."%";
    $params[] = "%".$_GET['search']."%";
    $params[] = "%".$_GET['search']."%";
}

$extraWhere = !empty($where) ? " AND " . implode(" AND ", $where) : "";

// Pagination setup
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Count total matching records
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM reservations WHERE 1=1 $branchSql $extraWhere");
$countStmt->execute($params);
$total_records = $countStmt->fetchColumn();
$total_pages = ceil($total_records / $limit);

$stmt = $pdo->prepare("SELECT * FROM reservations WHERE 1=1 $branchSql $extraWhere ORDER BY created_at DESC LIMIT $limit OFFSET $offset");
$stmt->execute($params);
$reservations = $stmt->fetchAll();

// Base URL for pagination
$query_string_params = $_GET;
unset($query_string_params['page']);
$base_url = '?';
if (!empty($query_string_params)) {
    $base_url = '?' . http_build_query($query_string_params) . '&';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion Reservations - Sam Event</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css?v=7">
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
        <a href="dashboard.php"><i class="fas fa-home"></i> &nbsp; Accueil</a>
        <a href="walk_in.php"><i class="fas fa-plus"></i> &nbsp; Nouveau Walk-in</a>
        <a href="reservations.php" class="active"><i class="fas fa-list"></i> &nbsp; Reservations</a>
        <a href="calendar.php"><i class="fas fa-calendar-alt"></i> &nbsp; Calendrier</a>
        <a href="caisse.php"><i class="fas fa-cash-register"></i> &nbsp; Caisse (Shift)</a>
        <a href="profile.php"><i class="fas fa-user"></i> &nbsp; Mon Profil</a>
        <a href="../logout.php" style="margin-top: 50px; color: #ef4444;"><i class="fas fa-sign-out-alt"></i> &nbsp; Déconnexion</a>
    </div>

    <div class="main-content">
        <h2>Gestion des Réservations</h2>

        <!-- Filter Bar -->
        <div class="card" style="margin-bottom: 25px; padding: 20px;">
            <form method="GET" style="display: flex; gap: 15px; flex-wrap: wrap; align-items: flex-end;">
                <div class="form-group" style="flex: 1; min-width: 200px;">
                    <label style="font-size: 0.85rem; font-weight: 700; color: #666;">Recherche (Nom/Tel/ID)</label>
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
                        <th style="padding: 15px;">Date Event</th>
                        <th style="padding: 15px;">Client</th>
                        <th style="padding: 15px;">Lieu</th>
                        <th style="padding: 15px;">Total</th>
                        <th style="padding: 15px;">Paiement</th>
                        <th style="padding: 15px;">Statut</th>
                        <th style="padding: 15px;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reservations as $r): ?>
                    <tr style="border-bottom: 1px solid #fafafa;">
                        <td style="padding: 15px;"><?php echo date('d/m/Y', strtotime($r['event_date'])); ?></td>
                        <td style="padding: 15px;">
                            <strong><?php echo htmlspecialchars($r['customer_name']); ?></strong><br>
                            <small><?php echo htmlspecialchars($r['customer_phone']); ?></small>
                        </td>
                        <td style="padding: 15px;"><?php echo htmlspecialchars($r['event_location']); ?></td>
                        <td style="padding: 15px; font-weight: 700;"><?php echo number_format($r['total_price'], 0); ?> F</td>
                        <td style="padding: 15px;">
                            <div style="font-size: 0.85rem;">Payé: <?php echo number_format($r['amount_paid'], 0); ?> F</div>
                            <div style="width: 100px; height: 5px; background: #eee; border-radius: 5px; margin-top: 5px;">
                                <div style="width: <?php echo min(100, ($r['amount_paid']/$r['total_price']*100)); ?>%; height: 100%; background: #10b981; border-radius: 5px;"></div>
                            </div>
                        </td>
                        <td style="padding: 15px;"><span class="status-badge <?php echo $r['status']; ?>"><?php echo ucfirst($r['status']); ?></span></td>
                        <td style="padding: 15px;">
                            <a href="manage.php?id=<?php echo $r['id']; ?>" class="contact-btn" style="padding: 5px 12px; font-size: 0.8rem; text-decoration: none;">Gérer</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination Links -->
        <?php if (isset($total_pages) && $total_pages > 1): ?>
        <div style="margin-top: 20px; display: flex; justify-content: center; gap: 5px; flex-wrap: wrap;">
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

<script src="../assets/js/admin.js?v=7"></script>
</body>
</html>
